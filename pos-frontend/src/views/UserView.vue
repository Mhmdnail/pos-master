<template>
<AppLayout>
<div style="padding:24px;max-width:1000px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Manajemen User</h1>
    <button class="btn btn-primary" @click="openAdd">+ Tambah User</button>
  </div>

  <div class="card">
    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error"
         style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;">
      {{ error }}
      <button @click="load"
              style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">
        Coba lagi
      </button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Kode</th><th>Nama</th><th>Username</th><th>Role</th>
          <th>Login Terakhir</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="u in users" :key="u.id"
            :style="parseInt(u.active) === 0 ? 'opacity:0.6;' : ''">
          <td>
            <code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">
              {{ u.code }}
            </code>
          </td>
          <td style="font-weight:500;">{{ u.name }}</td>
          <td style="color:#6B7280;font-family:monospace;font-size:13px;">{{ u.username }}</td>
          <td>
            <span :class="{
              'badge badge-green': u.role_name === 'owner',
              'badge badge-amber': u.role_name === 'manager',
              'badge badge-gray':  u.role_name === 'cashier' || u.role_name === 'inventory',
            }">{{ roleLabel(u.role_name) }}</span>
          </td>
          <td style="font-size:12px;color:#6B7280;">
            {{ u.last_login_at ? formatDateTime(u.last_login_at) : 'Belum pernah' }}
          </td>
          <td>
            <span :class="parseInt(u.active) === 1 ? 'badge badge-green' : 'badge badge-red'">
              {{ parseInt(u.active) === 1 ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td style="display:flex;gap:6px;">
            <button class="btn btn-outline btn-sm" @click="openEdit(u)">Edit</button>
            <button
              class="btn btn-outline btn-sm"
              :style="{
                color:       parseInt(u.active) ? '#E24B4A' : '#1D9E75',
                borderColor: parseInt(u.active) ? '#FECACA' : '#A7F3D0'
              }"
              @click="toggleUser(u)"
              :disabled="u.id === currentUserId">
              {{ parseInt(u.active) ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
          </td>
        </tr>
        <tr v-if="!loading && !users.length">
          <td colspan="7" class="text-center text-muted" style="padding:30px;">Tidak ada user</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Form Tambah/Edit User -->
<div v-if="showForm"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:460px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">
      {{ form.id ? 'Edit User' : 'Tambah User Baru' }}
    </div>

    <div class="form-group">
      <label class="form-label">Nama Lengkap *</label>
      <input v-model="form.name" class="form-input" placeholder="Contoh: Budi Santoso" />
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input
          v-model="form.username"
          class="form-input"
          placeholder="Contoh: kasir2"
          :disabled="!!form.id"
          style="font-family:monospace;" />
        <div v-if="form.id" style="font-size:11px;color:#9CA3AF;margin-top:3px;">
          Username tidak bisa diubah
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Role *</label>
        <select v-model="form.role_id" class="form-select">
          <option value="">-- Pilih Role --</option>
          <option v-for="r in roles" :key="r.id" :value="r.id">
            {{ roleLabel(r.name) }}
          </option>
        </select>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">
          {{ form.id ? 'Password (kosongkan jika tidak diubah)' : 'Password *' }}
        </label>
        <input
          v-model="form.password"
          class="form-input"
          type="password"
          :placeholder="form.id ? 'Isi untuk ubah password' : 'Min. 6 karakter'" />
      </div>
      <div class="form-group">
        <label class="form-label">PIN Manajer (opsional)</label>
        <input
          v-model="form.pin"
          class="form-input"
          type="password"
          maxlength="6"
          placeholder="6 digit" />
        <div style="font-size:11px;color:#9CA3AF;margin-top:3px;">
          Untuk approve diskon melebihi batas
        </div>
      </div>
    </div>

    <!-- Info deskripsi role -->
    <div v-if="form.role_id"
         style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:6px;
                padding:10px 12px;margin-bottom:14px;font-size:12px;color:#0369A1;">
      <strong>{{ roleLabel(roles.find(r => r.id == form.role_id)?.name) }}</strong>:
      {{ roleDesc(roles.find(r => r.id == form.role_id)?.name) }}
    </div>

    <div v-if="formError"
         style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;
                font-size:13px;margin-bottom:12px;">
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
import { userApi } from '@/services/api'
import { useAuthStore } from '@/stores'

const auth          = useAuthStore()
const currentUserId = computed(() => auth.user?.id)

const users     = ref([])
const roles     = ref([])
const loading   = ref(false)
const error     = ref('')
const showForm  = ref(false)
const saving    = ref(false)
const formError = ref('')

function defaultForm() {
  return { id: null, name: '', username: '', role_id: '', password: '', pin: '' }
}
const form = ref(defaultForm())

// ── Helpers ────────────────────────────────────────────────────
function roleLabel(name) {
  const map = { owner: 'Owner', manager: 'Manager', cashier: 'Kasir', inventory: 'Inventory' }
  return map[name] || name || '-'
}
function roleDesc(name) {
  const map = {
    owner:     'Akses penuh ke semua fitur termasuk laporan keuangan',
    manager:   'Kelola order, laporan, inventory, dan approve diskon',
    cashier:   'Input order dan proses pembayaran saja',
    inventory: 'Kelola stok, bahan baku, dan purchase order',
  }
  return map[name] || ''
}
function formatDateTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

// ── Actions ────────────────────────────────────────────────────
function openAdd() {
  form.value      = defaultForm()
  formError.value = ''
  showForm.value  = true
}

function openEdit(u) {
  form.value = {
    id:       u.id,
    name:     u.name,
    username: u.username,
    role_id:  u.role_id,
    password: '',
    pin:      '',
  }
  formError.value = ''
  showForm.value  = true
}

async function save() {
  if (!form.value.name)    { formError.value = 'Nama wajib diisi'; return }
  if (!form.value.role_id) { formError.value = 'Role wajib dipilih'; return }
  if (!form.value.id) {
    if (!form.value.username) { formError.value = 'Username wajib diisi'; return }
    if (!form.value.password || form.value.password.length < 6) {
      formError.value = 'Password minimal 6 karakter'
      return
    }
  }

  saving.value    = true
  formError.value = ''
  try {
    const payload = {
      name:    form.value.name,
      role_id: parseInt(form.value.role_id),
    }
    if (!form.value.id)          payload.username = form.value.username
    if (form.value.password)     payload.password = form.value.password
    if (form.value.pin)          payload.pin      = form.value.pin
    else if (form.value.id)      payload.pin      = null  // clear PIN kalau dikosongkan saat edit

    if (form.value.id) {
      await userApi.update(form.value.id, payload)
    } else {
      await userApi.create(payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = e.response?.data?.message
      || JSON.stringify(e.response?.data?.errors)
      || 'Gagal menyimpan'
  } finally {
    saving.value = false
  }
}

async function toggleUser(u) {
  const action = parseInt(u.active) ? 'nonaktifkan' : 'aktifkan'
  if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} user ${u.name}?`)) return
  try {
    await userApi.toggle(u.id)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal mengubah status user')
  }
}

// ── Load data ──────────────────────────────────────────────────
async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [userRes, roleRes] = await Promise.allSettled([
      userApi.list({ per_page: 100 }),
      userApi.roles(),
    ])

    if (userRes.status === 'fulfilled') {
      const d = userRes.value.data?.data
      users.value = Array.isArray(d) ? d : (d?.items || [])
    } else {
      error.value = userRes.reason?.response?.data?.message || 'Gagal memuat data user'
    }

    if (roleRes.status === 'fulfilled') {
      const d = roleRes.value.data?.data
      roles.value = Array.isArray(d) ? d : (d?.items || [])
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
