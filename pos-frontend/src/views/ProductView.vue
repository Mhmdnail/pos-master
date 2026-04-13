<template>
<AppLayout>
<div style="padding:24px;max-width:1200px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Manajemen Produk</h1>
    <button class="btn btn-primary" @click="openAdd">+ Tambah Produk</button>
  </div>

  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <input v-model="search" class="form-input" placeholder="Cari produk..." style="width:240px;" />
      <select v-model="catFilter" class="form-select" style="width:200px;">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>

    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ error }} <button @click="load" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Kode</th><th>Nama</th><th>Kategori</th>
          <th class="text-right">Harga</th><th>BOM</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in filtered" :key="p.id">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ p.code }}</code></td>
          <td style="font-weight:500;">{{ p.name }}</td>
          <td style="color:#6B7280;">{{ p.category_name }}</td>
          <td class="text-right">{{ formatRp(p.base_price) }}</td>
          <td><span :class="p.has_bom ? 'badge badge-green' : 'badge badge-gray'">{{ p.has_bom ? 'Ya' : '-' }}</span></td>
          <td><span :class="p.active ? 'badge badge-green' : 'badge badge-red'">{{ p.active ? 'Aktif' : 'Nonaktif' }}</span></td>
          <td><button class="btn btn-outline btn-sm" @click="openEdit(p)">Edit</button></td>
        </tr>
        <tr v-if="!loading && !filtered.length">
          <td colspan="7" class="text-center text-muted" style="padding:30px;">Tidak ada produk</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form Produk -->
<div v-if="showForm" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:540px;max-height:92vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">
      {{ form.id ? 'Edit Produk' : 'Tambah Produk' }}
    </div>

    <div class="form-group">
      <label class="form-label">Nama Produk *</label>
      <input v-model="form.name" class="form-input" placeholder="Contoh: Caffe Latte" />
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">SKU *</label>
        <input v-model="form.sku" class="form-input" placeholder="Contoh: KP-004" />
      </div>
      <div class="form-group">
        <label class="form-label">Kategori *</label>
        <select v-model="form.category_id" class="form-select">
          <option value="">-- Pilih Kategori --</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Harga Jual (Rp) *</label>
        <input v-model.number="form.base_price" class="form-input" type="number" min="0" placeholder="0" />
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:12px;padding-top:20px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
          <input type="checkbox" v-model="form.active" :true-value="1" :false-value="0" /> Aktif
        </label>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Deskripsi</label>
      <input v-model="form.description" class="form-input" placeholder="Opsional" />
    </div>

    <!-- BOM / Resep -->
    <div style="background:#F9FAFB;border-radius:8px;padding:14px;margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div style="font-size:13px;font-weight:600;">Resep Bahan Baku (BOM)</div>
        <button class="btn btn-outline btn-sm" type="button" @click="addBomLine">+ Bahan</button>
      </div>
      <div v-if="bomLines.length === 0" style="font-size:12px;color:#9CA3AF;">Belum ada bahan baku — klik "+ Bahan" untuk menambahkan</div>
      <div v-for="(line, i) in bomLines" :key="i" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
        <select v-model="line.material_id" class="form-select" style="flex:2;font-size:12px;">
          <option value="">-- Pilih bahan --</option>
          <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.unit }})</option>
        </select>
        <input v-model.number="line.qty_required" class="form-input" type="number" min="0.01" step="0.01"
               placeholder="Qty" style="width:80px;font-size:12px;" />
        <span style="font-size:11px;color:#9CA3AF;min-width:36px;">
          {{ materials.find(m => m.id === line.material_id)?.unit || '' }}
        </span>
        <button @click="removeBomLine(i)" style="background:none;border:none;cursor:pointer;color:#E24B4A;font-size:16px;padding:0;">✕</button>
      </div>
    </div>

    <div v-if="formError" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ formError }}
    </div>

    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showForm = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="save" :disabled="saving">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { productApi, categoryApi, materialApi } from '@/services/api'

const products   = ref([])
const categories = ref([])
const materials  = ref([])
const loading    = ref(false)
const error      = ref('')
const search     = ref('')
const catFilter  = ref('')
const showForm   = ref(false)
const saving     = ref(false)
const formError  = ref('')
const bomLines   = ref([])

function defaultForm() {
  return { id: null, name: '', sku: '', category_id: '', base_price: 0, description: '', active: 1 }
}
const form = ref(defaultForm())

const filtered = computed(() => products.value.filter(p => {
  const s = !search.value   || p.name.toLowerCase().includes(search.value.toLowerCase())
  const c = !catFilter.value || p.category_id === catFilter.value
  return s && c
}))

function formatRp(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') }

function addBomLine()    { bomLines.value.push({ material_id: '', qty_required: 0 }) }
function removeBomLine(i){ bomLines.value.splice(i, 1) }

function openAdd() {
  form.value = defaultForm(); formError.value = ''; bomLines.value = []; showForm.value = true
}
async function openEdit(p) {
  form.value     = { id: p.id, name: p.name, sku: p.sku, category_id: p.category_id, base_price: p.base_price, description: p.description || '', active: p.active }
  formError.value = ''
  bomLines.value  = []
  showForm.value  = true
  // Load BOM existing
  try {
    const { data } = await productApi.show(p.id)
    if (data.data?.recipe?.lines) {
      bomLines.value = data.data.recipe.lines.map(l => ({
        material_id:  l.material_id,
        qty_required: parseFloat(l.qty_required),
      }))
    }
  } catch {}
}

async function save() {
  if (!form.value.name)        { formError.value = 'Nama wajib diisi'; return }
  if (!form.value.sku)         { formError.value = 'SKU wajib diisi'; return }
  if (!form.value.category_id) { formError.value = 'Kategori wajib dipilih'; return }
  if (!form.value.base_price)  { formError.value = 'Harga wajib diisi'; return }

  const invalidBom = bomLines.value.find(l => !l.material_id || !l.qty_required)
  if (invalidBom) { formError.value = 'Lengkapi semua baris resep atau hapus yang kosong'; return }

  saving.value    = true
  formError.value = ''
  try {
    const payload = { ...form.value }
    if (bomLines.value.length > 0) {
      payload.recipe = { name: 'Default', lines: bomLines.value }
      payload.has_bom = 1
    }
    if (form.value.id) {
      await productApi.update(form.value.id, payload)
    } else {
      await productApi.create(payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'Gagal menyimpan'
  } finally {
    saving.value = false
  }
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [pRes, cRes, mRes] = await Promise.all([
      productApi.list({ per_page: 200 }),
      categoryApi.list(),
      materialApi.list(),
    ])
    const pd = pRes.data?.data; products.value   = Array.isArray(pd) ? pd : (pd?.items || [])
    const cd = cRes.data?.data; categories.value = Array.isArray(cd) ? cd : (cd?.items || [])
    const md = mRes.data?.data; materials.value  = Array.isArray(md) ? md : (md?.items || [])
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat data'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
