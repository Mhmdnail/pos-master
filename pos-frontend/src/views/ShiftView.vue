<template>
<AppLayout>
<div style="padding:24px;max-width:1100px;">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Shift Kasir</h1>
    <input type="date" v-model="date" class="form-input" style="width:160px;" @change="loadShifts" />
  </div>

  <!-- Banner: Shift Aktif -->
  <div v-if="activeShift"
       style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:10px;
              padding:16px 20px;margin-bottom:20px;
              display:flex;justify-content:space-between;align-items:center;">
    <div>
      <div style="font-size:12px;font-weight:500;color:#065F46;margin-bottom:4px;">
        SHIFT AKTIF SEKARANG
      </div>
      <div style="font-size:18px;font-weight:700;color:#065F46;">
        {{ activeShift.code }}
        <span style="font-size:13px;font-weight:400;margin-left:8px;">
          Kasir: {{ activeShift.cashier_name }}
        </span>
      </div>
      <div style="font-size:12px;color:#065F46;margin-top:4px;">
        Dibuka: {{ formatDateTime(activeShift.opened_at) }}
        &nbsp;·&nbsp; Modal: {{ formatRp(activeShift.opening_cash) }}
      </div>
    </div>
    <button class="btn btn-danger" @click="openCloseModal">Tutup Shift</button>
  </div>

  <!-- Banner: Tidak ada shift aktif -->
  <div v-else
       style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:10px;
              padding:16px 20px;margin-bottom:20px;
              display:flex;justify-content:space-between;align-items:center;">
    <div>
      <div style="font-size:13px;font-weight:600;color:#92400E;">Belum ada shift aktif</div>
      <div style="font-size:12px;color:#92400E;margin-top:2px;">
        Buka shift terlebih dahulu sebelum memulai transaksi
      </div>
    </div>
    <button class="btn btn-primary" @click="openOpenModal">Buka Shift</button>
  </div>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Shift</div>
      <div style="font-size:24px;font-weight:700;">{{ shifts.length }}</div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Order</div>
      <div style="font-size:24px;font-weight:700;">
        {{ shifts.reduce((s, x) => s + (parseInt(x.total_orders) || 0), 0) }}
      </div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Omzet</div>
      <div style="font-size:16px;font-weight:700;color:#1D9E75;">
        {{ formatRp(shifts.reduce((s, x) => s + parseFloat(x.total_revenue || 0), 0)) }}
      </div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Cash</div>
      <div style="font-size:16px;font-weight:700;">
        {{ formatRp(shifts.reduce((s, x) => s + parseFloat(x.total_cash || 0), 0)) }}
      </div>
    </div>
  </div>

  <!-- Tabel riwayat shift -->
  <div class="card">
    <div style="font-weight:600;margin-bottom:14px;">Riwayat Shift</div>
    <div v-if="loading" style="text-align:center;padding:30px;"><span class="spinner"></span></div>
    <div v-else-if="error"
         style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;">
      {{ error }}
      <button @click="loadAll"
              style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">
        Coba lagi
      </button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>Kode</th><th>Kasir</th><th>Buka</th><th>Tutup</th>
          <th class="text-right">Modal</th><th class="text-right">Order</th>
          <th class="text-right">Omzet</th><th class="text-right">Selisih</th>
          <th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in shifts" :key="s.id">
          <td>
            <code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">
              {{ s.code }}
            </code>
          </td>
          <td>{{ s.cashier_name }}</td>
          <td style="font-size:12px;">{{ formatTime(s.opened_at) }}</td>
          <td style="font-size:12px;">{{ s.closed_at ? formatTime(s.closed_at) : '-' }}</td>
          <td class="text-right">{{ formatRp(s.opening_cash) }}</td>
          <td class="text-right font-bold">{{ s.total_orders || 0 }}</td>
          <td class="text-right" style="color:#1D9E75;font-weight:600;">
            {{ formatRp(s.total_revenue) }}
          </td>
          <td class="text-right">
            <span v-if="s.difference !== null && s.difference !== undefined"
                  :style="{
                    color:       parseFloat(s.difference) < 0 ? '#E24B4A' : parseFloat(s.difference) > 0 ? '#EF9F27' : '#1D9E75',
                    fontWeight:  600
                  }">
              {{ parseFloat(s.difference) >= 0 ? '+' : '' }}{{ formatRp(s.difference) }}
            </span>
            <span v-else style="color:#9CA3AF;">-</span>
          </td>
          <td>
            <span :class="s.status === 'open' ? 'badge badge-green' : 'badge badge-gray'">
              {{ s.status === 'open' ? 'Aktif' : 'Selesai' }}
            </span>
          </td>
          <td>
            <button class="btn btn-outline btn-sm" @click="viewZReport(s)">Z-Report</button>
          </td>
        </tr>
        <tr v-if="!loading && !shifts.length">
          <td colspan="10" class="text-center text-muted" style="padding:30px;">
            Tidak ada shift hari ini
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal Buka Shift ──────────────────────────────────── -->
<div v-if="showOpenModal"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:400px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">Buka Shift Baru</div>

    <!-- Kalau kasir biasa, tampilkan nama saja (tidak perlu dropdown) -->
<div class="form-group">
  <label class="form-label">Kasir</label>
  <div v-if="!isManager"
       style="padding:10px 12px;background:#F9FAFB;border:1px solid #E5E7EB;
              border-radius:6px;font-size:14px;font-weight:500;">
    {{ auth.user?.name }}
    <span style="font-size:12px;color:#6B7280;margin-left:6px;">(Anda)</span>
  </div>
  <!-- Kalau manager/owner, tampilkan dropdown -->
  <select v-else v-model="openForm.cashier_id" class="form-select">
    <option value="">-- Pilih Kasir --</option>
    <option v-for="u in cashiers" :key="u.id" :value="u.id">
      {{ u.name }} ({{ roleLabel(u.role_name) }})
    </option>
  </select>
</div>
    <div class="form-group">
      <label class="form-label">Modal Awal Kas (Rp)</label>
      <input v-model.number="openForm.opening_cash" class="form-input"
             type="number" min="0" step="10000" placeholder="0"
             style="font-size:16px;" />
    </div>
    <div class="form-group">
      <label class="form-label">Catatan (opsional)</label>
      <input v-model="openForm.notes_open" class="form-input" placeholder="Opsional" />
    </div>

    <div v-if="openError"
         style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;
                font-size:13px;margin-bottom:12px;">
      {{ openError }}
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showOpenModal = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="doOpenShift" :disabled="opening">
        {{ opening ? 'Membuka...' : 'Buka Shift' }}
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Tutup Shift ─────────────────────────────────── -->
<div v-if="showCloseModal && activeShift"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:440px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:4px;">
      Tutup Shift {{ activeShift.code }}
    </div>
    <div style="font-size:13px;color:#6B7280;margin-bottom:16px;">
      Kasir: {{ activeShift.cashier_name }} &nbsp;·&nbsp;
      Dibuka: {{ formatDateTime(activeShift.opened_at) }}
    </div>

    <!-- Ringkasan live -->
    <div style="background:#F9FAFB;border-radius:8px;padding:14px;margin-bottom:16px;">
      <div style="font-size:12px;font-weight:600;color:#6B7280;margin-bottom:10px;">
        Ringkasan Transaksi
      </div>
      <div v-if="loadingSummary" style="text-align:center;padding:10px;">
        <span class="spinner"></span>
      </div>
      <template v-else>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
          <span>Total Order</span>
          <span style="font-weight:600;">{{ liveSummary.total_orders || 0 }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
          <span>Total Omzet</span>
          <span style="font-weight:600;color:#1D9E75;">{{ formatRp(liveSummary.total_revenue) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>Cash</span><span>{{ formatRp(liveSummary.total_cash) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>QRIS</span><span>{{ formatRp(liveSummary.total_qris) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>EDC / Debit</span><span>{{ formatRp(liveSummary.total_edc) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>E-Wallet</span><span>{{ formatRp(liveSummary.total_ewallet) }}</span>
        </div>
        <div style="border-top:1px solid #E5E7EB;padding-top:8px;margin-top:6px;
                    display:flex;justify-content:space-between;font-size:13px;">
          <span>Ekspektasi kas (modal + cash)</span>
          <span style="font-weight:600;">
            {{ formatRp(parseFloat(activeShift.opening_cash || 0) + parseFloat(liveSummary.total_cash || 0)) }}
          </span>
        </div>
      </template>
    </div>

    <div class="form-group">
      <label class="form-label">Uang Kas Saat Tutup (hitung fisik)</label>
      <input v-model.number="closeForm.closing_cash" class="form-input"
             type="number" min="0" step="10000" placeholder="0"
             style="font-size:16px;" />
    </div>

    <!-- Preview selisih -->
    <div v-if="closeForm.closing_cash > 0 && !loadingSummary"
         :style="{
           background: selisih === 0 ? '#D1FAE5' : selisih < 0 ? '#FEE2E2' : '#FEF3C7',
           color:      selisih === 0 ? '#065F46' : selisih < 0 ? '#991B1B' : '#92400E',
           padding: '8px 12px', borderRadius: '6px', fontSize: '13px', marginBottom: '12px'
         }">
      Selisih: <strong>{{ selisih >= 0 ? '+' : '' }}{{ formatRp(selisih) }}</strong>
      <span v-if="selisih === 0"> — Pas ✓</span>
      <span v-else-if="selisih < 0"> — Kurang</span>
      <span v-else> — Lebih</span>
    </div>

    <div class="form-group">
      <label class="form-label">Catatan (opsional)</label>
      <input v-model="closeForm.notes_close" class="form-input"
             placeholder="Contoh: ada kembalian yang tidak tercatat" />
    </div>

    <div v-if="closeError"
         style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;
                font-size:13px;margin-bottom:12px;">
      {{ closeError }}
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showCloseModal = false">Batal</button>
      <button class="btn btn-danger" style="flex:1" @click="doCloseShift" :disabled="closing">
        {{ closing ? 'Menutup...' : 'Tutup Shift' }}
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Z-Report ────────────────────────────────────── -->
<div v-if="zreportData"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:480px;max-height:85vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:700;font-size:16px;">Z-Report — {{ zreportData.code }}</div>
      <button class="btn btn-outline btn-sm" @click="zreportData = null">Tutup</button>
    </div>

    <!-- Info shift -->
    <div style="background:#F9FAFB;border-radius:8px;padding:14px;margin-bottom:14px;font-size:13px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:#6B7280;">Kasir</span>
        <span style="font-weight:600;">{{ zreportData.cashier_name }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:#6B7280;">Dibuka</span>
        <span>{{ formatDateTime(zreportData.opened_at) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:#6B7280;">Ditutup</span>
        <span>{{ zreportData.closed_at ? formatDateTime(zreportData.closed_at) : 'Masih aktif' }}</span>
      </div>
    </div>

    <!-- Ringkasan transaksi -->
    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Ringkasan Transaksi</div>
    <table class="table" style="margin-bottom:14px;">
      <tbody>
        <tr>
          <td style="color:#6B7280;">Total Order</td>
          <td class="text-right font-bold">{{ zreportData.total_orders }}</td>
        </tr>
        <tr>
          <td style="color:#6B7280;">Total Omzet</td>
          <td class="text-right font-bold" style="color:#1D9E75;">{{ formatRp(zreportData.total_revenue) }}</td>
        </tr>
        <tr>
          <td style="color:#6B7280;">Total Diskon</td>
          <td class="text-right" style="color:#EF9F27;">-{{ formatRp(zreportData.total_discount) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Breakdown pembayaran -->
    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Breakdown Pembayaran</div>
    <table class="table" style="margin-bottom:14px;">
      <tbody>
        <tr><td>Cash</td><td class="text-right">{{ formatRp(zreportData.total_cash) }}</td></tr>
        <tr><td>QRIS</td><td class="text-right">{{ formatRp(zreportData.total_qris) }}</td></tr>
        <tr><td>EDC / Debit</td><td class="text-right">{{ formatRp(zreportData.total_edc) }}</td></tr>
        <tr><td>E-Wallet</td><td class="text-right">{{ formatRp(zreportData.total_ewallet) }}</td></tr>
      </tbody>
    </table>

    <!-- Rekonsiliasi kas -->
    <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Rekonsiliasi Kas</div>
    <table class="table" style="margin-bottom:14px;">
      <tbody>
        <tr>
          <td style="color:#6B7280;">Modal awal</td>
          <td class="text-right">{{ formatRp(zreportData.opening_cash) }}</td>
        </tr>
        <tr>
          <td style="color:#6B7280;">Cash masuk dari transaksi</td>
          <td class="text-right">{{ formatRp(zreportData.total_cash) }}</td>
        </tr>
        <tr>
          <td style="font-weight:600;">Ekspektasi kas</td>
          <td class="text-right font-bold">{{ formatRp(zreportData.expected_cash) }}</td>
        </tr>
        <tr>
          <td style="font-weight:600;">Kas aktual (hitung fisik)</td>
          <td class="text-right font-bold">{{ formatRp(zreportData.closing_cash) }}</td>
        </tr>
        <tr>
          <td style="font-weight:600;">Selisih</td>
          <td class="text-right font-bold"
              :style="{
                color: parseFloat(zreportData.difference || 0) < 0
                  ? '#E24B4A'
                  : parseFloat(zreportData.difference || 0) > 0
                    ? '#EF9F27'
                    : '#1D9E75'
              }">
            {{ parseFloat(zreportData.difference || 0) >= 0 ? '+' : '' }}{{ formatRp(zreportData.difference) }}
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Top produk -->
    <template v-if="zreportData.top_products?.length">
      <div style="font-weight:600;font-size:13px;margin-bottom:10px;">Top 5 Produk</div>
      <table class="table">
        <thead>
          <tr><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr>
        </thead>
        <tbody>
          <tr v-for="p in zreportData.top_products" :key="p.name_snapshot">
            <td>{{ p.name_snapshot }}</td>
            <td class="text-right">{{ p.qty }}</td>
            <td class="text-right">{{ formatRp(p.revenue) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { shiftApi, userApi } from '@/services/api'
import { useAuthStore } from '@/stores'

// ── State ──────────────────────────────────────────────────────
const shifts       = ref([])
const activeShift  = ref(null)
const cashiers     = ref([])
const loading      = ref(false)
const error        = ref('')
const date         = ref(new Date().toISOString().split('T')[0])
const auth = useAuthStore()
const isManager = computed(() => ['owner','manager'].includes(auth.user?.role))

const showOpenModal  = ref(false)
const showCloseModal = ref(false)
const opening        = ref(false)
const closing        = ref(false)
const openError      = ref('')
const closeError     = ref('')
const loadingSummary = ref(false)
const liveSummary    = ref({})
const zreportData    = ref(null)

const openForm  = ref({ cashier_id: '', opening_cash: 0, notes_open: '' })
const closeForm = ref({ closing_cash: 0, notes_close: '' })

// ── Computed ───────────────────────────────────────────────────
const selisih = computed(() => {
  if (!activeShift.value) return 0
  const expected = parseFloat(activeShift.value.opening_cash || 0)
                 + parseFloat(liveSummary.value.total_cash   || 0)
  return closeForm.value.closing_cash - expected
})

// ── Helpers ────────────────────────────────────────────────────
function formatRp(v) {
  return 'Rp ' + Number(v || 0).toLocaleString('id-ID')
}
function formatTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
function formatDateTime(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}
function roleLabel(name) {
  return { owner: 'Owner', manager: 'Manager', cashier: 'Kasir', inventory: 'Inventory' }[name] || name || '-'
}

// ── Modal actions ──────────────────────────────────────────────
function openOpenModal() {
  openForm.value  = { 
    // Kalau kasir biasa → otomatis pakai ID diri sendiri
    // Kalau manager/owner → bisa pilih kasir lain
    cashier_id:   isManager.value ? '' : auth.user?.id,
    opening_cash: 0,
    notes_open:   '' }
  openError.value = ''
  showOpenModal.value = true
}

async function openCloseModal() {
  closeForm.value  = { closing_cash: 0, notes_close: '' }
  closeError.value = ''
  showCloseModal.value = true
  // Load ringkasan live dari shift aktif
  if (activeShift.value) {
    loadingSummary.value = true
    try {
      const { data } = await shiftApi.show(activeShift.value.id)
      liveSummary.value = data.data?.summary || {}
    } catch {
      liveSummary.value = {}
    } finally {
      loadingSummary.value = false
    }
  }
}

async function doOpenShift() {
  if (!openForm.value.cashier_id) { openError.value = 'Pilih kasir terlebih dahulu'; return }
  opening.value   = true
  openError.value = ''
  try {
    await shiftApi.open(openForm.value)
    showOpenModal.value = false
    await loadAll()
  } catch (e) {
    openError.value = e.response?.data?.message || 'Gagal membuka shift'
  } finally {
    opening.value = false
  }
}

async function doCloseShift() {
  if (!activeShift.value) return
  closing.value   = true
  closeError.value = ''
  try {
    await shiftApi.close(activeShift.value.id, closeForm.value)
    showCloseModal.value = false
    await loadAll()
  } catch (e) {
    closeError.value = e.response?.data?.message || 'Gagal menutup shift'
  } finally {
    closing.value = false
  }
}

async function viewZReport(shift) {
  try {
    const { data } = await shiftApi.zreport(shift.id)
    zreportData.value = data.data
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal memuat Z-Report')
  }
}

// ── Load data ──────────────────────────────────────────────────
async function loadShifts() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await shiftApi.list({ date: date.value })
    const d = data?.data
    shifts.value = Array.isArray(d) ? d : (d?.items || [])
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat data shift'
    shifts.value = []
  } finally {
    loading.value = false
  }
}

async function loadAll() {
  const promises = [loadShifts(), shiftApi.active()]

  // Hanya load daftar user kalau manager/owner
  // Kasir biasa tidak perlu dan tidak punya akses
  if (isManager.value) {
    promises.push(userApi.list({ per_page: 100 }))
  }

  const results = await Promise.allSettled(promises)

  if (results[1].status === 'fulfilled') {
    activeShift.value = results[1].value.data?.data || null
  }
  if (isManager.value && results[2]?.status === 'fulfilled') {
    const d = results[2].value.data?.data
    cashiers.value = (Array.isArray(d) ? d : (d?.items || []))
      .filter(u => parseInt(u.active) === 1)
  }
}

onMounted(loadAll)
</script>
