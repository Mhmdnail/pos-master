<template>
<AppLayout>
<div style="padding:24px;max-width:1100px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Bahan Baku & Stok</h1>
    <button class="btn btn-primary" @click="openAdd">+ Tambah Bahan</button>
  </div>

  <!-- Alert stok rendah -->
  <div v-if="lowStock.length" style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
    <span style="font-size:16px;font-weight:700;color:#92400E;">!</span>
    <span style="font-size:13px;color:#92400E;">
      <strong>{{ lowStock.length }} bahan</strong> hampir habis:
      {{ lowStock.slice(0,3).map(m => m.name).join(', ') }}{{ lowStock.length > 3 ? '...' : '' }}
    </span>
  </div>

  <div class="card">
    <input v-model="search" class="form-input" placeholder="Cari bahan baku..." style="width:280px;margin-bottom:16px;" />

    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ error }} <button @click="load" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Kode</th><th>Nama</th><th>Satuan</th>
          <th class="text-right">Stok</th><th class="text-right">Min Stok</th>
          <th class="text-right">HPP/Unit</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in filtered" :key="m.id">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ m.code }}</code></td>
          <td style="font-weight:500;">{{ m.name }}</td>
          <td style="color:#6B7280;">{{ m.unit }}</td>
          <td class="text-right" :style="{ fontWeight:600, color: parseFloat(m.stock_qty) <= parseFloat(m.min_stock) ? '#E24B4A' : '#1D9E75' }">
            {{ parseFloat(m.stock_qty).toFixed(2) }}
          </td>
          <td class="text-right" style="color:#6B7280;">{{ parseFloat(m.min_stock).toFixed(2) }}</td>
          <td class="text-right">{{ formatRp(m.cost_per_unit) }}</td>
          <td>
            <span :class="parseFloat(m.stock_qty) <= parseFloat(m.min_stock) ? 'badge badge-red' : 'badge badge-green'">
              {{ parseFloat(m.stock_qty) <= parseFloat(m.min_stock) ? 'Rendah' : 'Aman' }}
            </span>
          </td>
          <td style="display:flex;gap:6px;">
            <button class="btn btn-outline btn-sm" @click="openEdit(m)">Edit</button>
            <button class="btn btn-outline btn-sm" @click="openAdjust(m)">Adjust</button>
          </td>
        </tr>
        <tr v-if="!loading && !filtered.length">
          <td colspan="8" class="text-center text-muted" style="padding:30px;">Tidak ada bahan baku</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form Tambah/Edit -->
<div v-if="showForm" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:440px;max-height:90vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">
      {{ form.id ? 'Edit Bahan Baku' : 'Tambah Bahan Baku' }}
    </div>

    <div class="form-group">
      <label class="form-label">Nama Bahan *</label>
      <input v-model="form.name" class="form-input" placeholder="Contoh: Biji Kopi Arabika" />
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Satuan *</label>
        <select v-model="form.unit" class="form-select">
          <option value="gram">gram</option>
          <option value="ml">ml</option>
          <option value="pcs">pcs</option>
          <option value="kg">kg</option>
          <option value="liter">liter</option>
          <option value="lembar">lembar</option>
          <option value="butir">butir</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">HPP per Satuan (Rp)</label>
        <input v-model.number="form.cost_per_unit" class="form-input" type="number" min="0" step="0.01" placeholder="0" />
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Stok Awal</label>
        <input v-model.number="form.stock_qty" class="form-input" type="number" min="0" step="0.01" placeholder="0" :disabled="!!form.id" />
        <div v-if="form.id" style="font-size:11px;color:#9CA3AF;margin-top:4px;">Ubah stok via tombol Adjust</div>
      </div>
      <div class="form-group">
        <label class="form-label">Stok Minimum (alert)</label>
        <input v-model.number="form.min_stock" class="form-input" type="number" min="0" step="0.01" placeholder="0" />
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Tanggal Kadaluarsa (opsional)</label>
      <input v-model="form.expired_at" class="form-input" type="date" />
    </div>
    <div v-if="form.id" class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
        <input type="checkbox" v-model="form.active" :true-value="1" :false-value="0" />
        Aktif
      </label>
    </div>

    <div v-if="formError" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ formError }}
    </div>

    <div style="display:flex;gap:8px;margin-top:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showForm = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="save" :disabled="saving">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>
  </div>
</div>

<!-- Modal Adjust Stok -->
<div v-if="adjustTarget" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:360px;">
    <div style="font-weight:600;margin-bottom:4px;">Adjust Stok</div>
    <div style="font-size:13px;color:#6B7280;margin-bottom:16px;">
      {{ adjustTarget.name }} —
      Stok saat ini: <strong>{{ parseFloat(adjustTarget.stock_qty).toFixed(2) }} {{ adjustTarget.unit }}</strong>
    </div>
    <div class="form-group">
      <label class="form-label">Tipe</label>
      <select v-model="adjustForm.type" class="form-select">
        <option value="in">Masuk (tambah stok)</option>
        <option value="out">Keluar (kurangi stok)</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Jumlah ({{ adjustTarget.unit }})</label>
      <input v-model.number="adjustForm.qty" class="form-input" type="number" min="0.01" step="0.01" placeholder="0" />
    </div>
    <div class="form-group">
      <label class="form-label">Keterangan</label>
      <input v-model="adjustForm.notes" class="form-input" placeholder="Contoh: Pembelian stok baru" />
    </div>
    <div v-if="adjustForm.qty > 0" style="background:#F0FDF4;border:1px solid #BBF7D0;padding:8px 12px;border-radius:6px;font-size:12px;color:#166534;margin-bottom:12px;">
      Stok setelah adjust:
      <strong>
        {{ (parseFloat(adjustTarget.stock_qty) + (adjustForm.type === 'in' ? adjustForm.qty : -adjustForm.qty)).toFixed(2) }}
        {{ adjustTarget.unit }}
      </strong>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="adjustTarget = null">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="doAdjust" :disabled="adjusting">
        {{ adjusting ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { materialApi } from '@/services/api'

const materials    = ref([])
const lowStock     = ref([])
const loading      = ref(false)
const error        = ref('')
const search       = ref('')
const showForm     = ref(false)
const saving       = ref(false)
const formError    = ref('')
const adjustTarget = ref(null)
const adjusting    = ref(false)
const adjustForm   = ref({ type: 'in', qty: 0, notes: '' })

function defaultForm() {
  return { id: null, name: '', unit: 'gram', stock_qty: 0, min_stock: 0, cost_per_unit: 0, expired_at: '', active: 1 }
}
const form = ref(defaultForm())

const filtered = computed(() =>
  materials.value.filter(m => !search.value || m.name.toLowerCase().includes(search.value.toLowerCase()))
)

function formatRp(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') }

function openAdd()  { form.value = defaultForm(); formError.value = ''; showForm.value = true }
function openEdit(m) {
  form.value = {
    id: m.id, name: m.name, unit: m.unit,
    stock_qty: m.stock_qty, min_stock: m.min_stock,
    cost_per_unit: m.cost_per_unit, expired_at: m.expired_at || '', active: m.active
  }
  formError.value = ''
  showForm.value  = true
}
function openAdjust(m) {
  adjustTarget.value = m
  adjustForm.value   = { type: 'in', qty: 0, notes: '' }
}

async function save() {
  if (!form.value.name) { formError.value = 'Nama wajib diisi'; return }
  if (!form.value.unit) { formError.value = 'Satuan wajib dipilih'; return }
  saving.value    = true
  formError.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.expired_at) delete payload.expired_at
    if (form.value.id) {
      await materialApi.update(form.value.id, payload)
    } else {
      await materialApi.create(payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal menyimpan'
  } finally {
    saving.value = false
  }
}

async function doAdjust() {
  if (!adjustForm.value.qty || adjustForm.value.qty <= 0) { alert('Jumlah harus lebih dari 0'); return }
  adjusting.value = true
  try {
    await materialApi.adjust(adjustTarget.value.id, adjustForm.value)
    adjustTarget.value = null
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal adjust stok')
  } finally {
    adjusting.value = false
  }
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [matRes, lowRes] = await Promise.allSettled([
      materialApi.list(),
      materialApi.lowStock(),
    ])
    if (matRes.status === 'fulfilled') {
      const d = matRes.value.data?.data
      materials.value = Array.isArray(d) ? d : (d?.items || [])
    } else {
      error.value = matRes.reason?.response?.data?.message || 'Gagal memuat bahan baku'
    }
    if (lowRes.status === 'fulfilled') {
      const d = lowRes.value.data?.data
      lowStock.value = Array.isArray(d) ? d : (d?.items || [])
    }
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
