<template>
<AppLayout>
<div style="padding:24px;max-width:1100px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Laporan</h1>
    <div style="display:flex;gap:8px;align-items:center;">
      <input type="date" v-model="from" class="form-input" style="width:140px;" />
      <span style="color:#6B7280;">s/d</span>
      <input type="date" v-model="to" class="form-input" style="width:140px;" />
      <button class="btn btn-primary" @click="loadAll" :disabled="loading">
        <span v-if="loading" class="spinner"></span>
        <span>Tampilkan</span>
      </button>
    </div>
  </div>

  <!-- Summary cards -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">
    <div class="card" v-for="s in summaryCards" :key="s.label">
      <div style="font-size:11px;font-weight:500;color:#6B7280;margin-bottom:6px;">{{ s.label }}</div>
      <div style="font-size:20px;font-weight:700;" :style="{ color: s.color || '#1F2937' }">{{ s.value }}</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <!-- Produk terlaris -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:12px;">Produk Terlaris</div>
      <table class="table">
        <thead><tr><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead>
        <tbody>
          <tr v-for="p in topProducts" :key="p.product_name">
            <td>{{ p.product_name }}</td>
            <td class="text-right">{{ p.total_qty }}</td>
            <td class="text-right">{{ formatRp(p.total_revenue) }}</td>
          </tr>
          <tr v-if="!topProducts.length">
            <td colspan="3" class="text-center text-muted" style="padding:20px;">Tidak ada data</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Metode pembayaran -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:12px;">Metode Pembayaran</div>
      <table class="table">
        <thead><tr><th>Metode</th><th class="text-right">Transaksi</th><th class="text-right">Total</th></tr></thead>
        <tbody>
          <tr v-for="p in byPayment" :key="p.method">
            <td><span class="badge badge-green">{{ p.method.toUpperCase() }}</span></td>
            <td class="text-right">{{ p.count }}</td>
            <td class="text-right">{{ formatRp(p.total) }}</td>
          </tr>
          <tr v-if="!byPayment.length">
            <td colspan="3" class="text-center text-muted" style="padding:20px;">Tidak ada data</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- P&L ringkasan -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:12px;">Laba Rugi Ringkasan</div>
      <div v-if="pl" style="display:flex;flex-direction:column;gap:8px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;">
          <span>Net Revenue</span><span class="font-bold">{{ formatRp(pl.net_revenue) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#6B7280;">
          <span>Total HPP</span><span>{{ formatRp(pl.total_hpp) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#6B7280;">
          <span>Total Diskon</span><span>{{ formatRp(pl.total_discount) }}</span>
        </div>
        <div style="border-top:1px solid #E5E7EB;padding-top:8px;display:flex;justify-content:space-between;">
          <span class="font-bold">Gross Profit</span>
          <span class="font-bold" :style="{ color: pl.gross_profit >= 0 ? '#1D9E75' : '#E24B4A' }">
            {{ formatRp(pl.gross_profit) }} ({{ pl.gross_margin_pct }}%)
          </span>
        </div>
      </div>
    </div>

    <!-- Stok rendah -->
    <div class="card">
      <div style="font-weight:600;margin-bottom:12px;">Stok Hampir Habis</div>
      <table class="table">
        <thead><tr><th>Bahan</th><th class="text-right">Stok</th><th>Satuan</th></tr></thead>
        <tbody>
          <tr v-for="m in lowStock" :key="m.id">
            <td>{{ m.name }}</td>
            <td class="text-right" style="color:#E24B4A;font-weight:600;">{{ m.stock_qty }}</td>
            <td>{{ m.unit }}</td>
          </tr>
          <tr v-if="!lowStock.length">
            <td colspan="3" class="text-center text-muted" style="padding:20px;">Stok aman</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { reportApi, materialApi } from '@/services/api'

const from = ref(new Date().toISOString().split('T')[0])
const to   = ref(new Date().toISOString().split('T')[0])

const loading     = ref(false)
const salesData   = ref(null)
const topProducts = ref([])
const byPayment   = ref([])
const pl          = ref(null)
const lowStock    = ref([])

const summaryCards = computed(() => {
  const s = salesData.value
  if (!s) return []
  return [
    { label: 'Total Order',    value: s.total_orders || 0 },
    { label: 'Gross Revenue',  value: formatRp(s.gross_revenue), color:'#1D9E75' },
    { label: 'Total Diskon',   value: formatRp(s.total_discount), color:'#EF9F27' },
    { label: 'Net Revenue',    value: formatRp(s.net_revenue), color:'#1D9E75' },
  ]
})

function formatRp(v) {
  return 'Rp ' + Number(v || 0).toLocaleString('id-ID')
}

async function loadAll() {
  loading.value = true
  const params = { from: from.value, to: to.value }
  const [salesRes, prodRes, plRes, stockRes] = await Promise.allSettled([
    reportApi.sales(params),
    reportApi.products({ ...params, limit: 5 }),
    reportApi.profitLoss(params),
    materialApi.lowStock(),
  ])
  if (salesRes.status === 'fulfilled') {
    salesData.value = salesRes.value.data.data.summary
    byPayment.value = salesRes.value.data.data.byPayment || []
  }
  if (prodRes.status === 'fulfilled')  topProducts.value = prodRes.value.data.data || []
  if (plRes.status === 'fulfilled')    pl.value          = plRes.value.data.data
  if (stockRes.status === 'fulfilled') lowStock.value    = stockRes.value.data.data || []
  loading.value = false
}

onMounted(loadAll)
</script>
