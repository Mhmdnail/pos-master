<template>
<AppLayout>
<div style="padding:24px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Riwayat Order</h1>
    <div style="display:flex;gap:8px;">
      <input type="date" v-model="date" class="form-input" style="width:160px;" @change="load" />
      <select v-model="statusFilter" class="form-select" style="width:140px;" @change="load">
        <option value="">Semua Status</option>
        <option value="completed">Selesai</option>
        <option value="pending">Pending</option>
        <option value="cancelled">Dibatalkan</option>
      </select>
    </div>
  </div>

  <!-- Summary bar -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:4px;">Total Order</div>
      <div style="font-size:22px;font-weight:700;">{{ orders.length }}</div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:4px;">Selesai</div>
      <div style="font-size:22px;font-weight:700;color:#1D9E75;">{{ orders.filter(o=>o.payment_status==='paid').length }}</div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:4px;">Belum Bayar</div>
      <div style="font-size:22px;font-weight:700;color:#EF9F27;">{{ orders.filter(o=>o.payment_status==='unpaid').length }}</div>
    </div>
    <div class="card" style="text-align:center;">
      <div style="font-size:11px;color:#6B7280;margin-bottom:4px;">Total Omzet</div>
      <div style="font-size:16px;font-weight:700;color:#1D9E75;">{{ formatRp(totalRevenue) }}</div>
    </div>
  </div>

  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>No. Order</th><th>Kasir</th><th>Tipe</th>
          <th class="text-right">Total</th><th>Diskon</th>
          <th>Bayar</th><th>Status</th><th>Waktu</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading"><td colspan="9" class="text-center" style="padding:30px;"><span class="spinner"></span></td></tr>
        <tr v-for="o in orders" :key="o.id">
          <td><code style="font-size:11px;background:#F3F4F6;padding:2px 6px;border-radius:4px;">{{ o.order_number }}</code></td>
          <td style="color:#6B7280;font-size:12px;">{{ o.cashier_name }}</td>
          <td><span class="badge badge-gray" style="font-size:11px;">{{ o.order_type }}</span></td>
          <td class="text-right font-bold">{{ formatRp(o.grand_total) }}</td>
          <td class="text-right" style="color:#EF9F27;">{{ o.discount_total > 0 ? formatRp(o.discount_total) : '-' }}</td>
          <td>
            <span :class="{
              'badge badge-green': o.payment_status === 'paid',
              'badge badge-amber': o.payment_status === 'unpaid',
              'badge badge-red':   o.payment_status === 'refunded',
            }">{{ o.payment_status }}</span>
          </td>
          <td>
            <span :class="{
              'badge badge-green': o.status === 'completed',
              'badge badge-amber': o.status === 'pending' || o.status === 'preparing',
              'badge badge-red':   o.status === 'cancelled',
            }">{{ o.status }}</span>
          </td>
          <td style="font-size:12px;color:#6B7280;">{{ formatTime(o.created_at) }}</td>
          <td>
            <button class="btn btn-outline btn-sm" @click="viewDetail(o)">Detail</button>
          </td>
        </tr>
        <tr v-if="!loading && !orders.length">
          <td colspan="9" class="text-center text-muted" style="padding:30px;">Tidak ada order hari ini</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Modal Detail -->
  <div v-if="detail" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;">
    <div class="card" style="width:420px;max-height:80vh;overflow-y:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div style="font-weight:600;">{{ detail.order_number }}</div>
        <button class="btn btn-outline btn-sm" @click="detail = null">Tutup</button>
      </div>
      <table class="table" style="margin-bottom:12px;">
        <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
          <tr v-for="item in detail.items" :key="item.id">
            <td style="font-size:13px;">{{ item.name_snapshot }}</td>
            <td class="text-right">{{ item.qty }}</td>
            <td class="text-right">{{ formatRp(item.unit_price) }}</td>
            <td class="text-right font-bold">{{ formatRp(item.subtotal) }}</td>
          </tr>
        </tbody>
      </table>
      <div style="background:#F9FAFB;border-radius:6px;padding:12px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>Subtotal</span><span>{{ formatRp(detail.subtotal) }}</span>
        </div>
        <div v-if="detail.discount_total > 0" style="display:flex;justify-content:space-between;font-size:13px;color:#EF9F27;margin-bottom:4px;">
          <span>Diskon</span><span>-{{ formatRp(detail.discount_total) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
          <span>Total</span><span>{{ formatRp(detail.grand_total) }}</span>
        </div>
      </div>
    </div>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { orderApi } from '@/services/api'

const orders       = ref([])
const loading      = ref(false)
const date         = ref(new Date().toISOString().split('T')[0])
const statusFilter = ref('')
const detail       = ref(null)

const totalRevenue = computed(() =>
  orders.value.filter(o => o.payment_status === 'paid').reduce((s,o) => s + Number(o.grand_total), 0)
)

function formatRp(v) { return 'Rp ' + Number(v||0).toLocaleString('id-ID') }
function formatTime(dt) { return dt ? new Date(dt).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) : '-' }

async function viewDetail(o) {
  const { data } = await orderApi.show(o.id)
  detail.value = data.data
}

async function load() {
  loading.value = true
  const params = { date: date.value }
  if (statusFilter.value) params.status = statusFilter.value
  const { data } = await orderApi.list(params)
  orders.value = data.data?.items || []
  loading.value = false
}
onMounted(load)
</script>
