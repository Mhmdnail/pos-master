<template>
<AppLayout>
<div style="padding:24px;max-width:1100px;">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h1 style="font-size:20px;font-weight:700;">Kas Kecil (Petty Cash)</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" v-model="from" class="form-input" style="width:145px;" @change="loadAll" />
      <span style="color:#6B7280;font-size:13px;">s/d</span>
      <input type="date" v-model="to" class="form-input" style="width:145px;" @change="loadAll" />
      <button class="btn btn-primary" @click="openAdd('out')">+ Pengeluaran</button>
      <button class="btn btn-outline" @click="openAdd('in')" style="color:#1D9E75;border-color:#6EE7B7;">+ Pengisian</button>
    </div>
  </div>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;border-top:3px solid #1D9E75;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;font-weight:500;">Saldo Kas Kecil</div>
      <div style="font-size:22px;font-weight:700;"
           :style="{ color: summary.saldo >= 0 ? '#1D9E75' : '#E24B4A' }">
        {{ formatRp(summary.saldo) }}
      </div>
    </div>
    <div class="card" style="text-align:center;border-top:3px solid #3B82F6;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Pengisian</div>
      <div style="font-size:18px;font-weight:700;color:#3B82F6;">{{ formatRp(summary.total_in) }}</div>
    </div>
    <div class="card" style="text-align:center;border-top:3px solid #E24B4A;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Pengeluaran</div>
      <div style="font-size:18px;font-weight:700;color:#E24B4A;">{{ formatRp(summary.total_out) }}</div>
    </div>
    <div class="card" style="text-align:center;border-top:3px solid #EF9F27;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Transaksi</div>
      <div style="font-size:22px;font-weight:700;">{{ summary.total_trx || 0 }}</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
    <!-- Tabel transaksi -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:14px;">Riwayat Transaksi</div>
      <div v-if="loading" style="text-align:center;padding:30px;"><span class="spinner"></span></div>
      <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;">
        {{ error }} <button @click="loadAll" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
      </div>
      <table v-else class="table">
        <thead>
          <tr><th>Waktu</th><th>Kategori</th><th>Keterangan</th><th class="text-right">Jumlah</th><th class="text-right">Saldo</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="t in transactions" :key="t.id">
            <td style="font-size:12px;color:#6B7280;white-space:nowrap;">{{ formatTime(t.created_at) }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <span :style="{
                  width:'8px', height:'8px', borderRadius:'50%', display:'inline-block', flexShrink:0,
                  background: t.type === 'in' ? '#3B82F6' : '#E24B4A'
                }"></span>
                <span style="font-size:13px;">{{ t.category }}</span>
              </div>
              <div v-if="t.reference_no" style="font-size:10px;color:#9CA3AF;margin-top:1px;">
                No. {{ t.reference_no }}
              </div>
            </td>
            <td style="font-size:12px;color:#6B7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              {{ t.description || '-' }}
            </td>
            <td class="text-right" style="font-weight:600;white-space:nowrap;"
                :style="{ color: t.type === 'in' ? '#3B82F6' : '#E24B4A' }">
              {{ t.type === 'in' ? '+' : '-' }}{{ formatRp(t.amount) }}
            </td>
            <td class="text-right" style="font-size:12px;color:#6B7280;white-space:nowrap;">
              {{ formatRp(t.balance_after) }}
            </td>
            <td>
              <button v-if="isToday(t.created_at)"
                      class="btn btn-outline btn-sm"
                      style="color:#E24B4A;border-color:#FECACA;"
                      @click="deleteTransaction(t)">✕</button>
            </td>
          </tr>
          <tr v-if="!loading && !transactions.length">
            <td colspan="6" class="text-center text-muted" style="padding:30px;">
              Tidak ada transaksi di periode ini
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Breakdown per kategori -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:14px;">Pengeluaran per Kategori</div>
      <div v-if="!summary.by_category?.length" style="text-align:center;padding:20px;color:#9CA3AF;font-size:13px;">
        Belum ada pengeluaran di periode ini
      </div>
      <div v-else>
        <div v-for="cat in summary.by_category" :key="cat.category"
             style="margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
            <span style="font-weight:500;">{{ cat.category }}</span>
            <span style="color:#E24B4A;font-weight:600;">{{ formatRp(cat.total) }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#9CA3AF;margin-bottom:4px;">
            <span>{{ cat.count }}x transaksi</span>
            <span>{{ summary.total_out > 0 ? (cat.total / summary.total_out * 100).toFixed(1) : 0 }}%</span>
          </div>
          <div style="background:#F3F4F6;border-radius:4px;height:6px;overflow:hidden;">
            <div :style="{
              width: summary.total_out > 0 ? (cat.total / summary.total_out * 100) + '%' : '0%',
              height: '100%', background: '#E24B4A', borderRadius: '4px', transition: 'width .3s'
            }"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Transaksi -->
<div v-if="showForm"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:460px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:4px;">
      {{ form.type === 'out' ? '📤 Catat Pengeluaran' : '📥 Pengisian Kas Kecil' }}
    </div>
    <div style="font-size:13px;color:#6B7280;margin-bottom:16px;">
      Saldo saat ini: <strong>{{ formatRp(summary.saldo) }}</strong>
    </div>

    <!-- Toggle tipe -->
    <div style="display:flex;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;margin-bottom:16px;">
      <button @click="form.type = 'out'"
              :style="{
                flex:1, padding:'10px', fontSize:'13px', fontWeight:600, border:'none', cursor:'pointer',
                background: form.type === 'out' ? '#E24B4A' : '#fff',
                color: form.type === 'out' ? '#fff' : '#6B7280'
              }">📤 Pengeluaran</button>
      <button @click="form.type = 'in'"
              :style="{
                flex:1, padding:'10px', fontSize:'13px', fontWeight:600, border:'none', cursor:'pointer',
                background: form.type === 'in' ? '#3B82F6' : '#fff',
                color: form.type === 'in' ? '#fff' : '#6B7280'
              }">📥 Pengisian</button>
    </div>

    <div class="form-group">
      <label class="form-label">Jumlah (Rp) *</label>
      <input v-model.number="form.amount" class="form-input" type="number" min="1" step="1000"
             placeholder="0" style="font-size:18px;font-weight:600;" />
      <div v-if="form.type === 'out' && form.amount > summary.saldo && form.amount > 0"
           style="margin-top:4px;font-size:12px;color:#E24B4A;">
        Melebihi saldo kas kecil ({{ formatRp(summary.saldo) }})
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Kategori *</label>
      <select v-model="form.category" class="form-select">
        <option value="">-- Pilih kategori --</option>
        <option v-for="c in categories" :key="c.value" :value="c.value">
          {{ c.icon }} {{ c.value }}
        </option>
        <option value="__custom">✏️ Kategori lain (ketik manual)</option>
      </select>
    </div>

    <!-- Input kategori custom -->
    <div v-if="form.category === '__custom'" class="form-group">
      <label class="form-label">Nama Kategori *</label>
      <input v-model="form.customCategory" class="form-input" placeholder="Contoh: Biaya parkir" />
    </div>

    <div class="form-group">
      <label class="form-label">Keterangan</label>
      <input v-model="form.description" class="form-input"
             placeholder="Contoh: Beli gas 3kg untuk dapur" />
    </div>

    <div class="form-group">
      <label class="form-label">No. Nota / Kwitansi (opsional)</label>
      <input v-model="form.reference_no" class="form-input" placeholder="Opsional" />
    </div>

    <div v-if="formError"
         style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ formError }}
    </div>

    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showForm = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="save" :disabled="saving"
              :style="{ background: form.type === 'in' ? '#3B82F6' : undefined }">
        {{ saving ? 'Menyimpan...' : (form.type === 'out' ? 'Catat Pengeluaran' : 'Catat Pengisian') }}
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

const today = new Date().toISOString().split('T')[0]
const from  = ref(today)
const to    = ref(today)

const transactions = ref([])
const summary      = ref({ saldo: 0, total_in: 0, total_out: 0, total_trx: 0, by_category: [] })
const categories   = ref([])
const loading      = ref(false)
const error        = ref('')
const showForm     = ref(false)
const saving       = ref(false)
const formError    = ref('')

function defaultForm(type = 'out') {
  return { type, amount: 0, category: '', customCategory: '', description: '', reference_no: '' }
}
const form = ref(defaultForm())

function formatRp(v)   { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') }
function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
  })
}
function isToday(dt) {
  return dt && dt.startsWith(today)
}

function openAdd(type = 'out') {
  form.value      = defaultForm(type)
  formError.value = ''
  showForm.value  = true
}

async function save() {
  const finalCategory = form.value.category === '__custom'
    ? form.value.customCategory.trim()
    : form.value.category

  if (!form.value.amount || form.value.amount <= 0) { formError.value = 'Jumlah wajib diisi'; return }
  if (!finalCategory) { formError.value = 'Kategori wajib dipilih'; return }
  if (form.value.type === 'out' && form.value.amount > summary.value.saldo) {
    formError.value = `Saldo tidak mencukupi. Saldo saat ini: ${formatRp(summary.value.saldo)}`
    return
  }

  saving.value    = true
  formError.value = ''
  try {
    await api.post('/kas-kecil', {
      type:         form.value.type,
      amount:       form.value.amount,
      category:     finalCategory,
      description:  form.value.description  || null,
      reference_no: form.value.reference_no || null,
    })
    showForm.value = false
    await loadAll()
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal menyimpan transaksi'
  } finally {
    saving.value = false
  }
}

async function deleteTransaction(t) {
  if (!confirm(`Hapus transaksi "${t.category}" senilai ${formatRp(t.amount)}?`)) return
  try {
    await api.delete(`/kas-kecil/${t.id}`)
    await loadAll()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus transaksi')
  }
}

async function loadAll() {
  loading.value = true
  error.value   = ''
  try {
    const params = { from: from.value, to: to.value }
    const [trxRes, sumRes, catRes] = await Promise.allSettled([
      api.get('/kas-kecil', { params }),
      api.get('/kas-kecil/summary', { params }),
      api.get('/kas-kecil/categories'),
    ])

    if (trxRes.status === 'fulfilled') {
      const d = trxRes.value.data?.data
      transactions.value = Array.isArray(d) ? d : (d?.items || [])
    } else {
      error.value = trxRes.reason?.response?.data?.message || 'Gagal memuat transaksi'
    }
    if (sumRes.status === 'fulfilled') summary.value  = sumRes.value.data?.data || summary.value
    if (catRes.status === 'fulfilled') categories.value = catRes.value.data?.data || []

  } finally {
    loading.value = false
  }
}

onMounted(loadAll)
</script>
