<template>
<AppLayout>
<div style="padding:24px;max-width:1200px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h1 style="font-size:20px;font-weight:700;">Purchase Order</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" v-model="from" class="form-input" style="width:140px;" @change="load" />
      <span style="color:#6B7280;font-size:13px;">s/d</span>
      <input type="date" v-model="to" class="form-input" style="width:140px;" @change="load" />
      <select v-model="statusFilter" class="form-select" style="width:140px;" @change="load">
        <option value="">Semua Status</option>
        <option value="draft">Draft</option>
        <option value="ordered">Ordered</option>
        <option value="partial">Partial</option>
        <option value="received">Received</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button class="btn btn-primary" @click="openCreate">+ Buat PO</button>
    </div>
  </div>

  <div class="card">
    <div v-if="loading" style="text-align:center;padding:40px;"><span class="spinner"></span></div>
    <div v-else-if="error" style="background:#FEE2E2;color:#991B1B;padding:12px;border-radius:6px;font-size:13px;">
      {{ error }} <button @click="load" style="text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>
    <table v-else class="table">
      <thead>
        <tr>
          <th>No. PO</th><th>Supplier</th><th>Tgl Order</th>
          <th class="text-right">Total</th><th>Status</th><th>Dibuat</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="po in orders" :key="po.id">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ po.po_number }}</code></td>
          <td style="font-weight:500;">{{ po.supplier_name }}</td>
          <td style="font-size:12px;color:#6B7280;">{{ formatDate(po.order_date) }}</td>
          <td class="text-right font-bold">{{ formatRp(po.total_amount) }}</td>
          <td>
            <span :class="{
              'badge badge-gray':   po.status === 'draft',
              'badge badge-amber':  po.status === 'ordered' || po.status === 'partial',
              'badge badge-green':  po.status === 'received',
              'badge badge-red':    po.status === 'cancelled',
            }">{{ statusLabel(po.status) }}</span>
          </td>
          <td style="font-size:12px;color:#6B7280;">{{ po.created_by_name }}</td>
          <td style="display:flex;gap:6px;">
            <button class="btn btn-outline btn-sm" @click="viewDetail(po)">Detail</button>
            <button v-if="['ordered','partial'].includes(po.status)"
                    class="btn btn-primary btn-sm" @click="openReceive(po)">
              Terima
            </button>
            <button v-if="po.status === 'draft'"
                    class="btn btn-outline btn-sm"
                    style="color:#E24B4A;border-color:#FECACA;"
                    @click="cancelPO(po)">Batal</button>
          </td>
        </tr>
        <tr v-if="!loading && !orders.length">
          <td colspan="7" class="text-center text-muted" style="padding:30px;">Tidak ada PO di periode ini</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal Buat PO ─────────────────────────────────────── -->
<div v-if="showCreate"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;
            align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:640px;max-height:92vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:16px;">Buat Purchase Order Baru</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Supplier *</label>
        <select v-model="createForm.supplier_id" class="form-select">
          <option value="">-- Pilih Supplier --</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal Order *</label>
        <input v-model="createForm.order_date" class="form-input" type="date" />
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group">
        <label class="form-label">Estimasi Tiba</label>
        <input v-model="createForm.expected_date" class="form-input" type="date" />
      </div>
      <div class="form-group">
        <label class="form-label">PPN / Pajak (Rp)</label>
        <input v-model.number="createForm.tax_amount" class="form-input" type="number" min="0" placeholder="0" />
      </div>
    </div>

    <!-- Lines -->
    <div style="background:#F9FAFB;border-radius:8px;padding:14px;margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div style="font-size:13px;font-weight:600;">Item yang Dipesan</div>
        <button class="btn btn-outline btn-sm" @click="addLine">+ Item</button>
      </div>
      <div v-if="!createForm.lines.length" style="font-size:12px;color:#9CA3AF;text-align:center;padding:12px;">
        Klik "+ Item" untuk menambahkan bahan baku
      </div>
      <!-- Header kolom -->
      <div v-if="createForm.lines.length"
           style="display:grid;grid-template-columns:2fr 80px 100px 90px 24px;gap:8px;
                  font-size:11px;color:#9CA3AF;font-weight:500;margin-bottom:6px;padding:0 2px;">
        <span>Bahan Baku</span><span>Qty</span><span>Harga/unit</span><span class="text-right">Subtotal</span><span></span>
      </div>
      <div v-for="(line, i) in createForm.lines" :key="i"
           style="display:grid;grid-template-columns:2fr 80px 100px 90px 24px;gap:8px;align-items:center;margin-bottom:8px;">
        <select v-model="line.material_id" class="form-select" style="font-size:12px;">
          <option value="">-- Pilih bahan --</option>
          <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.unit }})</option>
        </select>
        <input v-model.number="line.qty_ordered" class="form-input" type="number" min="0.01" step="0.01"
               placeholder="0" style="font-size:12px;" />
        <input v-model.number="line.unit_price" class="form-input" type="number" min="0" step="100"
               placeholder="0" style="font-size:12px;" />
        <div class="text-right" style="font-size:12px;font-weight:600;color:#6B7280;">
          {{ formatRp(line.qty_ordered * line.unit_price) }}
        </div>
        <button @click="removeLine(i)" style="background:none;border:none;cursor:pointer;color:#E24B4A;font-size:16px;padding:0;">✕</button>
      </div>

      <!-- Total -->
      <div v-if="createForm.lines.length"
           style="border-top:1px solid #E5E7EB;padding-top:10px;margin-top:6px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span style="color:#6B7280;">Subtotal</span>
          <span>{{ formatRp(createSubtotal) }}</span>
        </div>
        <div v-if="createForm.tax_amount > 0"
             style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span style="color:#6B7280;">PPN / Pajak</span>
          <span>{{ formatRp(createForm.tax_amount) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;">
          <span>Total</span>
          <span style="color:#1D9E75;">{{ formatRp(createSubtotal + (createForm.tax_amount || 0)) }}</span>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Catatan</label>
      <input v-model="createForm.notes" class="form-input" placeholder="Opsional" />
    </div>

    <div v-if="createError" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ createError }}
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showCreate = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="savePO" :disabled="creating">
        {{ creating ? 'Menyimpan...' : 'Buat PO' }}
      </button>
    </div>
  </div>
</div>

<!-- ── Modal Detail PO ───────────────────────────────────── -->
<div v-if="detailPO"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;
            align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:580px;max-height:85vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <div style="font-weight:700;font-size:16px;">{{ detailPO.po_number }}</div>
        <div style="font-size:12px;color:#6B7280;">{{ detailPO.supplier_name }} · {{ formatDate(detailPO.order_date) }}</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <span :class="{
          'badge badge-gray':  detailPO.status === 'draft',
          'badge badge-amber': detailPO.status === 'ordered' || detailPO.status === 'partial',
          'badge badge-green': detailPO.status === 'received',
          'badge badge-red':   detailPO.status === 'cancelled',
        }">{{ statusLabel(detailPO.status) }}</span>
        <button class="btn btn-outline btn-sm" @click="detailPO = null">Tutup</button>
      </div>
    </div>

    <!-- Konfirmasi ke supplier kalau masih draft -->
    <div v-if="detailPO.status === 'draft'"
         style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:6px;padding:10px 12px;margin-bottom:14px;
                display:flex;justify-content:space-between;align-items:center;">
      <div style="font-size:13px;color:#92400E;">PO masih berstatus Draft — konfirmasi ke supplier?</div>
      <button class="btn btn-primary btn-sm" @click="confirmPO(detailPO)">Konfirmasi</button>
    </div>

    <table class="table" style="margin-bottom:14px;">
      <thead>
        <tr><th>Bahan Baku</th><th>Satuan</th><th class="text-right">Qty Order</th><th class="text-right">Qty Terima</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr>
      </thead>
      <tbody>
        <tr v-for="l in detailPO.lines" :key="l.id">
          <td style="font-weight:500;">{{ l.material_name }}</td>
          <td style="color:#6B7280;">{{ l.unit }}</td>
          <td class="text-right">{{ l.qty_ordered }}</td>
          <td class="text-right" :style="{ color: l.qty_received >= l.qty_ordered ? '#1D9E75' : '#EF9F27' }">
            {{ l.qty_received }}
          </td>
          <td class="text-right">{{ formatRp(l.unit_price) }}</td>
          <td class="text-right font-bold">{{ formatRp(l.subtotal) }}</td>
        </tr>
      </tbody>
    </table>
    <div style="background:#F9FAFB;border-radius:6px;padding:12px;font-size:13px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:#6B7280;">Subtotal</span><span>{{ formatRp(detailPO.subtotal) }}</span>
      </div>
      <div v-if="detailPO.tax_amount > 0" style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="color:#6B7280;">Pajak</span><span>{{ formatRp(detailPO.tax_amount) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-weight:700;font-size:15px;border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
        <span>Total</span><span style="color:#1D9E75;">{{ formatRp(detailPO.total_amount) }}</span>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Terima Barang ────────────────────────────────── -->
<div v-if="receivePO"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;
            align-items:center;justify-content:center;z-index:100;padding:20px;">
  <div class="card" style="width:520px;max-height:85vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:4px;">Terima Barang</div>
    <div style="font-size:13px;color:#6B7280;margin-bottom:16px;">
      {{ receivePO.po_number }} — {{ receivePO.supplier_name }}
    </div>

    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:#166534;">
      Isi qty yang diterima untuk setiap bahan. Stok akan otomatis bertambah setelah disimpan.
    </div>

    <div v-for="item in receiveItems" :key="item.line_id"
         style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #F3F4F6;">
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:500;">{{ item.material_name }}</div>
        <div style="font-size:11px;color:#6B7280;margin-top:2px;">
          Dipesan: {{ item.qty_ordered }} {{ item.unit }} ·
          Sudah terima: {{ item.qty_received }} {{ item.unit }}
        </div>
      </div>
      <div style="width:120px;">
        <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:3px;">Terima sekarang</label>
        <input v-model.number="item.qty_to_receive" class="form-input" type="number"
               min="0" :max="item.qty_ordered - item.qty_received" step="0.01"
               style="font-size:13px;" />
      </div>
      <div style="font-size:11px;color:#6B7280;min-width:40px;">{{ item.unit }}</div>
    </div>

    <div v-if="receiveError" style="background:#FEE2E2;color:#991B1B;padding:10px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      {{ receiveError }}
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="receivePO = null">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="saveReceive" :disabled="receiving">
        {{ receiving ? 'Menyimpan...' : 'Simpan Penerimaan' }}
      </button>
    </div>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { poApi, supplierApi, materialApi } from '@/services/api'

const today = new Date().toISOString().split('T')[0]
const from  = ref(new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0])
const to    = ref(today)

const orders       = ref([])
const suppliers    = ref([])
const materials    = ref([])
const loading      = ref(false)
const error        = ref('')
const statusFilter = ref('')

const showCreate  = ref(false)
const creating    = ref(false)
const createError = ref('')
const createForm  = ref({ supplier_id:'', order_date: today, expected_date:'', tax_amount:0, notes:'', lines:[] })

const detailPO    = ref(null)
const receivePO   = ref(null)
const receiveItems = ref([])
const receiveError = ref('')
const receiving    = ref(false)

const createSubtotal = computed(() =>
  createForm.value.lines.reduce((s, l) => s + (l.qty_ordered || 0) * (l.unit_price || 0), 0)
)

function formatRp(v)  { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') }
function formatDate(d){ return d ? new Date(d).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' }) : '-' }
function statusLabel(s) {
  return { draft:'Draft', ordered:'Ordered', partial:'Partial', received:'Received', cancelled:'Dibatalkan' }[s] || s
}

function openCreate() {
  createForm.value = { supplier_id:'', order_date: today, expected_date:'', tax_amount:0, notes:'', lines:[] }
  createError.value = ''
  showCreate.value  = true
}
function addLine()     { createForm.value.lines.push({ material_id:'', qty_ordered:0, unit_price:0 }) }
function removeLine(i) { createForm.value.lines.splice(i, 1) }

async function savePO() {
  if (!createForm.value.supplier_id) { createError.value = 'Pilih supplier'; return }
  if (!createForm.value.lines.length) { createError.value = 'Tambahkan minimal 1 item'; return }
  const invalid = createForm.value.lines.find(l => !l.material_id || !l.qty_ordered)
  if (invalid) { createError.value = 'Lengkapi semua baris item'; return }

  creating.value = true; createError.value = ''
  try {
    await poApi.create(createForm.value)
    showCreate.value = false
    await load()
  } catch (e) {
    createError.value = e.response?.data?.message || 'Gagal membuat PO'
  } finally { creating.value = false }
}

async function viewDetail(po) {
  const { data } = await poApi.show(po.id)
  detailPO.value = data.data
}

async function confirmPO(po) {
  try {
    await poApi.update(po.id, { status: 'ordered' })
    detailPO.value = null
    await load()
  } catch (e) { alert(e.response?.data?.message || 'Gagal konfirmasi PO') }
}

async function openReceive(po) {
  const { data } = await poApi.show(po.id)
  const detail = data.data
  receiveItems.value = detail.lines
    .filter(l => l.qty_received < l.qty_ordered)
    .map(l => ({
      line_id:        l.id,
      material_name:  l.material_name,
      unit:           l.unit,
      qty_ordered:    parseFloat(l.qty_ordered),
      qty_received:   parseFloat(l.qty_received),
      qty_to_receive: parseFloat(l.qty_ordered) - parseFloat(l.qty_received),
    }))
  receivePO.value    = po
  receiveError.value = ''
}

async function saveReceive() {
  const items = receiveItems.value
    .filter(i => i.qty_to_receive > 0)
    .map(i => ({ line_id: i.line_id, qty_received: i.qty_to_receive }))

  if (!items.length) { receiveError.value = 'Isi minimal 1 qty yang diterima'; return }

  receiving.value = true; receiveError.value = ''
  try {
    await poApi.receive(receivePO.value.id, { items })
    receivePO.value = null
    await load()
  } catch (e) {
    receiveError.value = e.response?.data?.message || 'Gagal menyimpan penerimaan'
  } finally { receiving.value = false }
}

async function cancelPO(po) {
  if (!confirm(`Batalkan PO ${po.po_number}?`)) return
  try { await poApi.cancel(po.id); await load() }
  catch (e) { alert(e.response?.data?.message || 'Gagal membatalkan PO') }
}

async function load() {
  loading.value = true; error.value = ''
  try {
    const params = { from: from.value, to: to.value, status: statusFilter.value, per_page: 100 }
    const [poRes, supRes, matRes] = await Promise.allSettled([
      poApi.list(params),
      supplierApi.list({ per_page: 200 }),
      materialApi.list(),
    ])
    if (poRes.status  === 'fulfilled') { const d = poRes.value.data?.data;  orders.value    = Array.isArray(d) ? d : (d?.items || []) }
    if (supRes.status === 'fulfilled') { const d = supRes.value.data?.data; suppliers.value = Array.isArray(d) ? d : (d?.items || []) }
    if (matRes.status === 'fulfilled') { const d = matRes.value.data?.data; materials.value = Array.isArray(d) ? d : (d?.items || []) }
    if (poRes.status  === 'rejected')  error.value = 'Gagal memuat data PO'
  } finally { loading.value = false }
}
onMounted(load)
</script>
