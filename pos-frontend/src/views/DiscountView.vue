<template>
<AppLayout>
<div style="padding:24px;max-width:1200px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Manajemen Diskon</h1>
    <button class="btn btn-primary" @click="openAdd">+ Tambah Diskon</button>
  </div>

  <div class="card">
    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;">
      {{ error }}
      <button @click="load" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Kode</th><th>Nama</th><th>Tipe</th><th>Nilai</th>
          <th>Voucher</th><th>Priority</th><th>Stackable</th><th>Berlaku</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in discounts" :key="d.id">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ d.code_internal }}</code></td>
          <td style="font-weight:500;">{{ d.name }}</td>
          <td><span class="badge badge-gray" style="font-size:11px;">{{ d.type }}</span></td>
          <td style="font-weight:600;">{{ d.type === 'percentage' ? d.value + '%' : formatRp(d.value) }}</td>
          <td>
            <code v-if="d.code" style="font-size:11px;background:#EDE9FE;color:#5B21B6;padding:2px 6px;border-radius:4px;">{{ d.code }}</code>
            <span v-else style="color:#9CA3AF;">-</span>
          </td>
          <td style="text-align:center;font-weight:600;">{{ d.priority }}</td>
          <td style="text-align:center;">
            <span :class="toInt(d.is_stackable) ? 'badge badge-green' : 'badge badge-gray'">
              {{ toInt(d.is_stackable) ? 'Ya' : 'Tidak' }}
            </span>
          </td>
          <td style="font-size:11px;color:#6B7280;">
            <template v-if="d.valid_from || d.valid_until">
              <div v-if="d.valid_from">{{ formatDate(d.valid_from) }}</div>
              <div v-if="d.valid_until">s/d {{ formatDate(d.valid_until) }}</div>
            </template>
            <span v-else style="color:#9CA3AF;">Selalu aktif</span>
          </td>
          <td>
            <span :class="toInt(d.active) ? 'badge badge-green' : 'badge badge-red'">
              {{ toInt(d.active) ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td><button class="btn btn-outline btn-sm" @click="openEdit(d)">Edit</button></td>
        </tr>
        <tr v-if="!loading && !discounts.length">
          <td colspan="10" class="text-center text-muted" style="padding:30px;">Belum ada diskon</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form Tambah/Edit Diskon -->
<div v-if="showForm" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;overflow-y:auto;">
  <div class="card" style="width:500px;max-height:90vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">
      {{ form.id ? 'Edit Diskon' : 'Tambah Diskon' }}
    </div>

    <div class="form-group">
      <label class="form-label">Nama Diskon *</label>
      <input v-model="form.name" class="form-input" placeholder="Contoh: Happy Hour Pagi" />
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Tipe *</label>
        <select v-model="form.type" class="form-select">
          <option value="percentage">Persentase (%)</option>
          <option value="nominal">Nominal (Rp)</option>
          <option value="buy_x_get_y">Buy X Get Y</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Nilai *</label>
        <input
          v-model.number="form.value"
          class="form-input" type="number" min="0"
          :placeholder="form.type === 'percentage' ? '10 (artinya 10%)' : '5000'"
          :max="form.type === 'percentage' ? 100 : undefined" />
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Max Cap (Rp) — opsional</label>
        <input v-model.number="form.max_cap" class="form-input" type="number" min="0" placeholder="Tidak ada batas" />
      </div>
      <div class="form-group">
        <label class="form-label">Priority (makin besar = duluan)</label>
        <input v-model.number="form.priority" class="form-input" type="number" placeholder="0" />
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Kode Voucher — kosongkan jika berlaku otomatis</label>
      <input v-model="form.code" class="form-input" placeholder="Contoh: KOPI20" style="text-transform:uppercase;" />
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Berlaku Dari</label>
        <input v-model="form.valid_from" class="form-input" type="datetime-local" />
      </div>
      <div class="form-group">
        <label class="form-label">Berlaku Sampai</label>
        <input v-model="form.valid_until" class="form-input" type="datetime-local" />
      </div>
    </div>

    <!-- Checkboxes — pakai boolean murni, bukan 0/1 -->
    <div style="display:flex;gap:20px;margin-bottom:16px;flex-wrap:wrap;">
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
        <input type="checkbox" v-model="form.is_stackable" />
        Bisa digabung (stackable)
      </label>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
        <input type="checkbox" v-model="form.require_member" />
        Khusus member
      </label>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
        <input type="checkbox" v-model="form.active" />
        Aktif
      </label>
    </div>

    <!-- Rules opsional -->
    <div style="background:#F9FAFB;border-radius:6px;padding:12px;margin-bottom:16px;">
      <div style="font-size:12px;font-weight:500;color:#6B7280;margin-bottom:8px;">Aturan berlaku (opsional)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <div>
          <label class="form-label" style="font-size:11px;">Jam mulai (happy hour)</label>
          <input v-model="ruleTime.start" class="form-input" type="time" style="font-size:12px;" />
        </div>
        <div>
          <label class="form-label" style="font-size:11px;">Jam selesai</label>
          <input v-model="ruleTime.end" class="form-input" type="time" style="font-size:12px;" />
        </div>
      </div>
      <div style="margin-top:8px;">
        <label class="form-label" style="font-size:11px;">Min. total belanja (Rp)</label>
        <input v-model.number="ruleMinAmount" class="form-input" type="number" min="0"
               placeholder="0 = tidak ada minimum" style="font-size:12px;" />
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
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import api from '@/services/api'

const discounts     = ref([])
const loading       = ref(false)
const error         = ref('')
const saving        = ref(false)
const showForm      = ref(false)
const formError     = ref('')
const ruleTime      = ref({ start: '', end: '' })
const ruleMinAmount = ref(0)

// Helper: paksa jadi integer untuk display badge
function toInt(val) { return parseInt(val) === 1 }

function formatRp(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') }
function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Default form pakai boolean murni — konsisten dengan v-model checkbox
function defaultForm() {
  return {
    id: null, name: '', type: 'percentage', value: 0,
    max_cap: null, priority: 0, code: '',
    valid_from: '', valid_until: '',
    is_stackable:   false,
    require_member: false,
    active:         true,   // boolean, bukan integer
  }
}
const form = ref(defaultForm())

function openAdd() {
  form.value          = defaultForm()
  formError.value     = ''
  ruleTime.value      = { start: '', end: '' }
  ruleMinAmount.value = 0
  showForm.value      = true
}

function openEdit(d) {
  // Konversi semua nilai dari API (bisa string "0"/"1") ke boolean untuk checkbox
  form.value = {
    ...d,
    code:           d.code || '',
    valid_from:     d.valid_from  || '',
    valid_until:    d.valid_until || '',
    active:         parseInt(d.active)         === 1,
    is_stackable:   parseInt(d.is_stackable)   === 1,
    require_member: parseInt(d.require_member) === 1,
  }
  formError.value     = ''
  ruleTime.value      = { start: '', end: '' }
  ruleMinAmount.value = 0
  showForm.value      = true
}

async function save() {
  if (!form.value.name)  { formError.value = 'Nama wajib diisi'; return }
  if (!form.value.value) { formError.value = 'Nilai wajib diisi'; return }

  saving.value    = true
  formError.value = ''
  try {
    // Konversi boolean kembali ke integer untuk API
    const payload = {
      ...form.value,
      active:         form.value.active         ? 1 : 0,
      is_stackable:   form.value.is_stackable   ? 1 : 0,
      require_member: form.value.require_member ? 1 : 0,
    }
    if (!payload.valid_from)  delete payload.valid_from
    if (!payload.valid_until) delete payload.valid_until
    if (!payload.max_cap)     payload.max_cap = null
    if (!payload.code)        payload.code    = null

    const rules = []
    if (ruleTime.value.start && ruleTime.value.end) {
      rules.push({ rule_type: 'time_range', rule_value: { start: ruleTime.value.start, end: ruleTime.value.end } })
    }
    if (ruleMinAmount.value > 0) {
      rules.push({ rule_type: 'min_amount', rule_value: { amount: ruleMinAmount.value } })
    }
    if (rules.length) payload.rules = rules

    if (form.value.id) {
      await api.put(`/discounts/${form.value.id}`, payload)
    } else {
      await api.post('/discounts', payload)
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
    const { data } = await api.get('/discounts')
    const d = data?.data
    discounts.value = Array.isArray(d) ? d : (d?.items || [])
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat data diskon'
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>
