<template>
<AppLayout>
<div style="padding:24px;max-width:1200px;">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <h1 style="font-size:20px;font-weight:700;">Laporan & Analitik</h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <!-- Quick filter -->
      <div style="display:flex;gap:4px;">
        <button v-for="q in quickFilters" :key="q.label"
          class="btn btn-outline btn-sm"
          :style="activeQuick === q.label ? 'background:#1D9E75;color:#fff;border-color:#1D9E75;' : ''"
          @click="applyQuick(q)">
          {{ q.label }}
        </button>
      </div>
      <input type="date" v-model="from" class="form-input" style="width:140px;" />
      <span style="color:#6B7280;">s/d</span>
      <input type="date" v-model="to" class="form-input" style="width:140px;" />
      <button class="btn btn-primary" @click="loadAll" :disabled="loading">
        <span v-if="loading" class="spinner" style="width:14px;height:14px;"></span>
        <span>{{ loading ? '' : 'Tampilkan' }}</span>
      </button>
    </div>
  </div>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div v-for="s in summaryCards" :key="s.label" class="card" style="padding:16px;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:6px;font-weight:500;">{{ s.label }}</div>
      <div style="font-size:20px;font-weight:700;" :style="{ color: s.color }">{{ s.value }}</div>
      <div v-if="s.sub" style="font-size:11px;color:#9CA3AF;margin-top:2px;">{{ s.sub }}</div>
    </div>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:2px;border-bottom:2px solid #E5E7EB;margin-bottom:20px;">
    <button v-for="tab in tabs" :key="tab"
      @click="activeTab = tab"
      :style="{
        padding: '8px 16px', fontSize: '13px', fontWeight: 500,
        background: 'none', border: 'none', cursor: 'pointer',
        borderBottom: activeTab === tab ? '2px solid #1D9E75' : '2px solid transparent',
        marginBottom: '-2px',
        color: activeTab === tab ? '#1D9E75' : '#6B7280',
      }">
      {{ tab }}
    </button>
  </div>

  <!-- ===================== TAB: PENJUALAN ===================== -->
  <div v-if="activeTab === 'Penjualan'">
    <!-- Chart omzet harian -->
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div style="font-weight:600;">Trend Omzet Harian</div>
        <div style="font-size:12px;color:#6B7280;">{{ from }} s/d {{ to }}</div>
      </div>
      <div v-if="!dailyData.length" style="text-align:center;padding:40px;color:#9CA3AF;">
        Tidak ada data di periode ini
      </div>
      <canvas v-else ref="salesChartRef" height="80"></canvas>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <!-- Breakdown payment chart -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:16px;">Metode Pembayaran</div>
        <div v-if="!byPayment.length" style="text-align:center;padding:30px;color:#9CA3AF;">Tidak ada data</div>
        <template v-else>
          <canvas ref="paymentChartRef" height="180"></canvas>
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
            <div v-for="(p, i) in byPayment" :key="p.method"
              style="display:flex;justify-content:space-between;align-items:center;font-size:13px;">
              <div style="display:flex;align-items:center;gap:6px;">
                <div :style="{ width:'10px',height:'10px',borderRadius:'2px',background:paymentColors[i] }"></div>
                <span>{{ p.method.toUpperCase() }}</span>
                <span style="color:#9CA3AF;">{{ p.count }}x</span>
              </div>
              <span style="font-weight:600;">{{ formatRp(p.total) }}</span>
            </div>
          </div>
        </template>
      </div>

      <!-- Tabel harian -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:12px;">Detail Harian</div>
        <div style="overflow-y:auto;max-height:280px;">
          <table class="table">
            <thead><tr><th>Tanggal</th><th class="text-right">Order</th><th class="text-right">Revenue</th></tr></thead>
            <tbody>
              <tr v-for="d in dailyData" :key="d.date">
                <td style="font-size:12px;">{{ formatDate(d.date) }}</td>
                <td class="text-right">{{ d.orders }}</td>
                <td class="text-right" style="font-weight:600;color:#1D9E75;">{{ formatRp(d.revenue) }}</td>
              </tr>
              <tr v-if="!dailyData.length">
                <td colspan="3" class="text-center text-muted" style="padding:20px;">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== TAB: PRODUK ===================== -->
  <div v-if="activeTab === 'Produk'">
    <div class="card" style="margin-bottom:16px;">
      <div style="font-weight:600;margin-bottom:16px;">Top 10 Produk Terlaris</div>
      <div v-if="!topProducts.length" style="text-align:center;padding:40px;color:#9CA3AF;">Tidak ada data</div>
      <canvas v-else ref="productChartRef" height="120"></canvas>
    </div>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>#</th><th>Produk</th>
            <th class="text-right">Qty Terjual</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Kontribusi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(p, i) in topProducts" :key="p.product_name">
            <td style="color:#9CA3AF;font-size:12px;">{{ i + 1 }}</td>
            <td style="font-weight:500;">{{ p.product_name }}</td>
            <td class="text-right">{{ p.total_qty }}</td>
            <td class="text-right" style="font-weight:600;">{{ formatRp(p.total_revenue) }}</td>
            <td class="text-right">
              <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
                <div style="width:60px;height:6px;background:#F3F4F6;border-radius:3px;overflow:hidden;">
                  <div :style="{
                    width: totalProductRevenue > 0 ? (p.total_revenue / totalProductRevenue * 100) + '%' : '0%',
                    height: '100%', background: '#1D9E75', borderRadius: '3px'
                  }"></div>
                </div>
                <span style="font-size:12px;color:#6B7280;min-width:36px;">
                  {{ totalProductRevenue > 0 ? (p.total_revenue / totalProductRevenue * 100).toFixed(1) : 0 }}%
                </span>
              </div>
            </td>
          </tr>
          <tr v-if="!topProducts.length">
            <td colspan="5" class="text-center text-muted" style="padding:30px;">Tidak ada data</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===================== TAB: LABA RUGI ===================== -->
  <div v-if="activeTab === 'Laba Rugi'">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
      <!-- P&L summary -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:16px;">Laba Rugi Periode</div>
        <div v-if="pl">
          <!-- Visual bar P&L -->
          <div style="margin-bottom:20px;">
            <div style="font-size:12px;color:#6B7280;margin-bottom:6px;">Revenue vs HPP</div>
            <div style="position:relative;height:32px;background:#F3F4F6;border-radius:6px;overflow:hidden;">
              <div :style="{
                position:'absolute',left:0,top:0,height:'100%',
                width: pl.net_revenue > 0 ? '100%' : '0%',
                background:'#D1FAE5', borderRadius:'6px'
              }"></div>
              <div :style="{
                position:'absolute',left:0,top:0,height:'100%',
                width: pl.net_revenue > 0 ? (pl.total_hpp / pl.net_revenue * 100) + '%' : '0%',
                background:'#FEE2E2', borderRadius:'6px'
              }"></div>
              <div style="position:absolute;inset:0;display:flex;align-items:center;padding:0 10px;justify-content:space-between;font-size:11px;font-weight:600;">
                <span style="color:#065F46;">Revenue</span>
                <span style="color:#991B1B;">HPP {{ pl.net_revenue > 0 ? (pl.total_hpp / pl.net_revenue * 100).toFixed(0) : 0 }}%</span>
              </div>
            </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:#6B7280;">Net Revenue</span>
              <span style="font-size:15px;font-weight:700;color:#1D9E75;">{{ formatRp(pl.net_revenue) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:#6B7280;">Total HPP</span>
              <span style="font-size:13px;color:#E24B4A;">-{{ formatRp(pl.total_hpp) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:#6B7280;">Total Diskon</span>
              <span style="font-size:13px;color:#EF9F27;">-{{ formatRp(pl.total_discount) }}</span>
            </div>
            <div style="border-top:2px solid #E5E7EB;padding-top:10px;display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">Gross Profit</span>
              <div style="text-align:right;">
                <div style="font-size:18px;font-weight:700;"
                     :style="{ color: pl.gross_profit >= 0 ? '#1D9E75' : '#E24B4A' }">
                  {{ formatRp(pl.gross_profit) }}
                </div>
                <div style="font-size:12px;color:#6B7280;">Margin {{ pl.gross_margin_pct }}%</div>
              </div>
            </div>
          </div>
        </div>
        <div v-else style="text-align:center;padding:30px;color:#9CA3AF;">Tidak ada data</div>
      </div>

      <!-- Efektivitas diskon -->
      <div class="card">
        <div style="font-weight:600;margin-bottom:12px;">Efektivitas Diskon</div>
        <table class="table">
          <thead><tr><th>Nama Diskon</th><th class="text-right">Dipakai</th><th class="text-right">Total</th></tr></thead>
          <tbody>
            <tr v-for="d in discountData" :key="d.discount_name">
              <td style="font-size:12px;">
                {{ d.discount_name }}
                <span v-if="d.discount_code" style="font-size:10px;background:#EDE9FE;color:#5B21B6;padding:1px 5px;border-radius:3px;margin-left:4px;">
                  {{ d.discount_code }}
                </span>
              </td>
              <td class="text-right">{{ d.usage_count }}x</td>
              <td class="text-right" style="color:#EF9F27;font-weight:600;">{{ formatRp(d.total_discount) }}</td>
            </tr>
            <tr v-if="!discountData.length">
              <td colspan="3" class="text-center text-muted" style="padding:20px;">Tidak ada diskon dipakai</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===================== TAB: STOK ===================== -->
  <div v-if="activeTab === 'Stok'">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
      <div class="card" style="text-align:center;">
        <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Total Bahan</div>
        <div style="font-size:22px;font-weight:700;">{{ stockData.items?.length || 0 }}</div>
      </div>
      <div class="card" style="text-align:center;">
        <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Stok Rendah</div>
        <div style="font-size:22px;font-weight:700;color:#E24B4A;">{{ stockData.low_stock || 0 }}</div>
      </div>
      <div class="card" style="text-align:center;">
        <div style="font-size:11px;color:#6B7280;margin-bottom:6px;">Nilai Persediaan</div>
        <div style="font-size:18px;font-weight:700;color:#1D9E75;">{{ formatRp(stockData.total_value) }}</div>
      </div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div style="font-weight:600;">Daftar Stok Bahan Baku</div>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
          <input type="checkbox" v-model="showLowOnly" />
          Tampilkan yang rendah saja
        </label>
      </div>
      <table class="table">
        <thead>
          <tr><th>Kode</th><th>Nama</th><th>Satuan</th><th class="text-right">Stok</th><th class="text-right">Min</th><th class="text-right">Nilai</th><th>Status</th></tr>
        </thead>
        <tbody>
          <tr v-for="m in filteredStock" :key="m.id"
              :style="parseInt(m.is_low) ? 'background:#FFF9F0;' : ''">
            <td><code style="font-size:11px;background:#F3F4F6;padding:2px 5px;border-radius:3px;">{{ m.code }}</code></td>
            <td style="font-weight:500;">{{ m.name }}</td>
            <td style="color:#6B7280;">{{ m.unit }}</td>
            <td class="text-right" :style="{ color: parseInt(m.is_low) ? '#E24B4A' : '#1D9E75', fontWeight:600 }">
              {{ parseFloat(m.stock_qty).toFixed(2) }}
            </td>
            <td class="text-right" style="color:#9CA3AF;">{{ parseFloat(m.min_stock).toFixed(2) }}</td>
            <td class="text-right" style="font-size:12px;">{{ formatRp(m.stock_value) }}</td>
            <td>
              <span :class="parseInt(m.is_low) ? 'badge badge-red' : 'badge badge-green'">
                {{ parseInt(m.is_low) ? 'Rendah' : 'Aman' }}
              </span>
            </td>
          </tr>
          <tr v-if="!filteredStock.length">
            <td colspan="7" class="text-center text-muted" style="padding:30px;">Tidak ada data</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { reportApi, materialApi } from '@/services/api'

// ── State ──────────────────────────────────────────────────────
const today  = new Date().toISOString().split('T')[0]
const from   = ref(today)
const to     = ref(today)
const loading     = ref(false)
const activeTab   = ref('Penjualan')
const activeQuick = ref('Hari ini')
const showLowOnly = ref(false)

const tabs = ['Penjualan', 'Produk', 'Laba Rugi', 'Stok']

const salesSummary  = ref(null)
const dailyData     = ref([])
const byPayment     = ref([])
const topProducts   = ref([])
const pl            = ref(null)
const discountData  = ref([])
const stockData     = ref({ items: [], total_value: 0, low_stock: 0 })

// Chart refs
const salesChartRef   = ref(null)
const paymentChartRef = ref(null)
const productChartRef = ref(null)
let salesChart = null, paymentChart = null, productChart = null

const paymentColors = ['#1D9E75', '#3B82F6', '#EF9F27', '#E24B4A', '#8B5CF6']

// ── Quick filters ──────────────────────────────────────────────
const quickFilters = [
  { label: 'Hari ini',   days: 0  },
  { label: '7 hari',     days: 6  },
  { label: '30 hari',    days: 29 },
  { label: 'Bulan ini',  month: true },
]

function applyQuick(q) {
  activeQuick.value = q.label
  const d = new Date()
  to.value = d.toISOString().split('T')[0]
  if (q.month) {
    from.value = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
  } else {
    const f = new Date(d)
    f.setDate(f.getDate() - q.days)
    from.value = f.toISOString().split('T')[0]
  }
  loadAll()
}

// ── Computed ───────────────────────────────────────────────────
const summaryCards = computed(() => {
  const s = salesSummary.value
  if (!s) return [
    { label: 'Total Order',  value: '-',    color: '#1F2937' },
    { label: 'Gross Revenue',value: '-',    color: '#1D9E75' },
    { label: 'Total Diskon', value: '-',    color: '#EF9F27' },
    { label: 'Net Revenue',  value: '-',    color: '#1D9E75' },
  ]
  const avgOrder = s.total_orders > 0
    ? Math.round(s.net_revenue / s.total_orders)
    : 0
  return [
    { label: 'Total Order',   value: s.total_orders || 0,       color: '#1F2937', sub: `avg ${formatRp(avgOrder)}/order` },
    { label: 'Gross Revenue', value: formatRp(s.gross_revenue),  color: '#1D9E75' },
    { label: 'Total Diskon',  value: formatRp(s.total_discount), color: '#EF9F27' },
    { label: 'Net Revenue',   value: formatRp(s.net_revenue),    color: '#1D9E75' },
  ]
})

const totalProductRevenue = computed(() =>
  topProducts.value.reduce((s, p) => s + parseFloat(p.total_revenue || 0), 0)
)

const filteredStock = computed(() => {
  const items = stockData.value.items || []
  return showLowOnly.value ? items.filter(m => parseInt(m.is_low)) : items
})

// ── Helpers ────────────────────────────────────────────────────
function formatRp(v) {
  return 'Rp ' + Number(v || 0).toLocaleString('id-ID')
}
function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}

// ── Chart rendering ────────────────────────────────────────────
async function renderSalesChart() {
  await nextTick()
  if (!salesChartRef.value || !dailyData.value.length) return

  const { Chart, registerables } = await import('chart.js')
  Chart.register(...registerables)

  if (salesChart) salesChart.destroy()

  const labels  = dailyData.value.map(d => formatDate(d.date))
  const revenue = dailyData.value.map(d => parseFloat(d.revenue || 0))
  const orders  = dailyData.value.map(d => parseInt(d.orders || 0))

  salesChart = new Chart(salesChartRef.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Revenue',
          data: revenue,
          borderColor: '#1D9E75',
          backgroundColor: 'rgba(29,158,117,0.08)',
          fill: true,
          tension: 0.4,
          pointRadius: revenue.length > 14 ? 2 : 4,
          pointHoverRadius: 6,
          yAxisID: 'y',
        },
        {
          label: 'Order',
          data: orders,
          borderColor: '#3B82F6',
          backgroundColor: 'transparent',
          tension: 0.4,
          pointRadius: revenue.length > 14 ? 2 : 4,
          borderDash: [4, 3],
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => ctx.datasetIndex === 0
              ? 'Revenue: ' + formatRp(ctx.raw)
              : 'Order: ' + ctx.raw,
          },
        },
      },
      scales: {
        y:  {
          position: 'left',
          ticks: { callback: v => 'Rp' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : (v/1000).toFixed(0)+'rb'), font: { size: 10 } },
          grid: { color: 'rgba(0,0,0,0.04)' },
        },
        y1: {
          position: 'right',
          ticks: { font: { size: 10 } },
          grid: { drawOnChartArea: false },
        },
        x: { ticks: { font: { size: 10 } } },
      },
    },
  })
}

async function renderPaymentChart() {
  await nextTick()
  if (!paymentChartRef.value || !byPayment.value.length) return

  const { Chart, registerables } = await import('chart.js')
  Chart.register(...registerables)

  if (paymentChart) paymentChart.destroy()

  paymentChart = new Chart(paymentChartRef.value, {
    type: 'doughnut',
    data: {
      labels: byPayment.value.map(p => p.method.toUpperCase()),
      datasets: [{
        data: byPayment.value.map(p => parseFloat(p.total)),
        backgroundColor: paymentColors,
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 6,
      }],
    },
    options: {
      responsive: true,
      cutout: '65%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: { label: ctx => ctx.label + ': ' + formatRp(ctx.raw) },
        },
      },
    },
  })
}

async function renderProductChart() {
  await nextTick()
  if (!productChartRef.value || !topProducts.value.length) return

  const { Chart, registerables } = await import('chart.js')
  Chart.register(...registerables)

  if (productChart) productChart.destroy()

  const sorted = [...topProducts.value].sort((a, b) => b.total_revenue - a.total_revenue)

  productChart = new Chart(productChartRef.value, {
    type: 'bar',
    data: {
      labels: sorted.map(p => p.product_name),
      datasets: [
        {
          label: 'Revenue',
          data: sorted.map(p => parseFloat(p.total_revenue)),
          backgroundColor: 'rgba(29,158,117,0.75)',
          borderRadius: 4,
          yAxisID: 'y',
        },
        {
          label: 'Qty',
          data: sorted.map(p => parseInt(p.total_qty)),
          backgroundColor: 'rgba(59,130,246,0.6)',
          borderRadius: 4,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => ctx.datasetIndex === 0
              ? 'Revenue: ' + formatRp(ctx.raw)
              : 'Qty: ' + ctx.raw,
          },
        },
      },
      scales: {
        y:  { ticks: { callback: v => (v/1000).toFixed(0)+'rb', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
        y1: { position: 'right', ticks: { font: { size: 10 } }, grid: { drawOnChartArea: false } },
        x:  { ticks: { font: { size: 10 }, maxRotation: 30 } },
      },
    },
  })
}

// Re-render charts ketika tab berubah
watch(activeTab, async tab => {
  if (tab === 'Penjualan') { await renderSalesChart(); await renderPaymentChart() }
  if (tab === 'Produk')    await renderProductChart()
})

// ── Load all data ──────────────────────────────────────────────
async function loadAll() {
  loading.value = true
  const params = { from: from.value, to: to.value }

  const [salesRes, prodRes, plRes, stockRes, discRes] = await Promise.allSettled([
    reportApi.sales(params),
    reportApi.products({ ...params, limit: 10 }),
    reportApi.profitLoss(params),
    reportApi.stock(),
    reportApi.discounts(params),
  ])

  if (salesRes.status === 'fulfilled') {
    const d = salesRes.value.data?.data
    salesSummary.value = d?.summary || null
    dailyData.value    = d?.daily   || []
    byPayment.value    = d?.byPayment || []
  }
  if (prodRes.status === 'fulfilled') {
    const d = prodRes.value.data?.data
    topProducts.value = Array.isArray(d) ? d : (d?.items || [])
  }
  if (plRes.status === 'fulfilled')    pl.value       = plRes.value.data?.data
  if (stockRes.status === 'fulfilled') stockData.value = stockRes.value.data?.data || { items:[], total_value:0, low_stock:0 }
  if (discRes.status === 'fulfilled') {
    const d = discRes.value.data?.data
    discountData.value = Array.isArray(d) ? d : []
  }

  loading.value = false

  // Render charts sesuai tab aktif
  await nextTick()
  if (activeTab.value === 'Penjualan') {
    await renderSalesChart()
    await renderPaymentChart()
  } else if (activeTab.value === 'Produk') {
    await renderProductChart()
  }
}

onMounted(loadAll)
</script>
