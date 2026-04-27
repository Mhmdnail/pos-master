<template>
<AppLayout>
<div style="padding:24px;max-width:1100px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Manajemen Supplier</h1>
    <button class="btn btn-primary" @click="openAdd">+ Tambah Supplier</button>
  </div>

  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <input v-model="search" class="form-input" placeholder="Cari nama, kontak, telepon..."
             style="width:280px;" @input="load" />
    </div>

    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;">
      {{ error }} <button @click="load" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>
    <table v-else class="table">
      <thead>
        <tr><th>Kode</th><th>Nama Supplier</th><th>Kontak</th><th>Telepon</th><th>Bank</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <tr v-for="s in suppliers" :key="s.id" :style="!parseInt(s.active) ? 'opacity:0.55;' : ''">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ s.code }}</code></td>
          <td style="font-weight:500;">{{ s.name }}</td>
          <td style="font-size:12px;color:#6B7280;">{{ s.contact_name || '-' }}</td>
          <td style="font-size:12px;">{{ s.phone || '-' }}</td>
          <td style="font-size:12px;color:#6B7280;">
            <span v-if="s.bank_name">{{ s.bank_name }} · {{ s.bank_account }}</span>
            <span v-else>-</span>
          </td>
          <td>
            <span :class="parseInt(s.active) ? 'badge badge-green' : 'badge badge-red'">
              {{ parseInt(s.active) ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td style="display:flex;gap:6px;">
            <button class="btn btn-outline btn-sm" @click="openEdit(s)">Edit</button>
            <button class="btn btn-outline btn-sm"
                    :style="{ color: parseInt(s.active) ? '#E24B4A' : '#1D9E75' }"
                    @click="toggle(s)">
              {{ parseInt(s.active) ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
          </td>
        </tr>
        <tr v-if="!loading && !suppliers.length">
          <td colspan="7" class="text-center text-muted" style="padding:30px;">Belum ada supplier</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form -->
<div v-if="showForm"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;
            align-items:center;justify-content:center;z-index:100;padding:20px;overflow-y:auto;">
  <div class="card" style="width:520px;max-height:90vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">
      {{ form.id ? 'Edit Supplier' : 'Tambah Supplier' }}
    </div>

    <div class="form-group">
      <label class="form-label">Nama Supplier *</label>
      <input v-model="form.name" class="form-input" placeholder="Contoh: CV Sumber Bahan" />
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Nama Kontak</label>
        <input v-model="form.contact_name" class="form-input" placeholder="PIC supplier" />
      </div>
      <div class="form-group">
        <label class="form-label">Telepon / WA</label>
        <input v-model="form.phone" class="form-input" placeholder="08xx-xxxx-xxxx" />
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input v-model="form.email" class="form-input" type="email" placeholder="Opsional" />
      </div>
      <div class="form-group">
        <label class="form-label">NPWP</label>
        <input v-model="form.npwp" class="form-input" placeholder="Opsional" />
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Alamat</label>
      <input v-model="form.address" class="form-input" placeholder="Opsional" />
    </div>

    <div style="background:#F9FAFB;border-radius:8px;padding:12px;margin-bottom:14px;">
      <div style="font-size:12px;font-weight:600;color:#6B7280;margin-bottom:10px;">Info Rekening Bank</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">Nama Bank</label>
          <input v-model="form.bank_name" class="form-input" placeholder="BCA, BRI, Mandiri..." style="font-size:12px;" />
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:11px;">No. Rekening</label>
          <input v-model="form.bank_account" class="form-input" placeholder="1234567890" style="font-size:12px;" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" style="font-size:11px;">Atas Nama</label>
        <input v-model="form.bank_holder" class="form-input" placeholder="Nama pemilik rekening" style="font-size:12px;" />
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Catatan</label>
      <input v-model="form.notes" class="form-input" placeholder="Opsional" />
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
import { supplierApi } from '@/services/api'

const suppliers = ref([])
const loading   = ref(false)
const error     = ref('')
const search    = ref('')
const showForm  = ref(false)
const saving    = ref(false)
const formError = ref('')

function defaultForm() {
  return { id: null, name: '', contact_name: '', phone: '', email: '', address: '',
           npwp: '', bank_name: '', bank_account: '', bank_holder: '', notes: '' }
}
const form = ref(defaultForm())

function openAdd()  { form.value = defaultForm(); formError.value = ''; showForm.value = true }
function openEdit(s) { form.value = { ...s }; formError.value = ''; showForm.value = true }

async function save() {
  if (!form.value.name) { formError.value = 'Nama supplier wajib diisi'; return }
  saving.value = true; formError.value = ''
  try {
    if (form.value.id) await supplierApi.update(form.value.id, form.value)
    else               await supplierApi.create(form.value)
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal menyimpan'
  } finally { saving.value = false }
}

async function toggle(s) {
  const action = parseInt(s.active) ? 'nonaktifkan' : 'aktifkan'
  if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} supplier ${s.name}?`)) return
  try { await supplierApi.toggle(s.id); await load() }
  catch (e) { alert(e.response?.data?.message || 'Gagal mengubah status') }
}

async function load() {
  loading.value = true; error.value = ''
  try {
    const { data } = await supplierApi.list({ search: search.value, per_page: 100 })
    const d = data?.data
    suppliers.value = Array.isArray(d) ? d : (d?.items || [])
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat data'
  } finally { loading.value = false }
}
onMounted(load)
</script>
