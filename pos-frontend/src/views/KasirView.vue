<template>
<AppLayout>
<div style="display:flex;height:100vh;overflow:hidden;">

  <!-- LEFT: Menu produk -->
  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;border-right:1px solid #E5E7EB;">
    <div style="padding:12px 16px;background:#fff;border-bottom:1px solid #E5E7EB;">
      <div style="display:flex;gap:8px;align-items:center;">
        <input v-model="search" class="form-input" placeholder="Cari menu..." style="flex:1;" />
        <select v-model="selectedCat" class="form-select" style="width:180px;">
          <option value="">Semua Kategori</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
    </div>

    <div v-if="productStore.error"
         style="margin:16px;background:#FEE2E2;color:#991B1B;padding:12px;border-radius:8px;font-size:13px;">
      Gagal memuat produk: {{ productStore.error }}
      <button @click="productStore.loadAll()"
              style="margin-left:8px;text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">
        Coba lagi
      </button>
    </div>
    <div v-else-if="productStore.loading"
         style="flex:1;display:flex;align-items:center;justify-content:center;">
      <span class="spinner"></span>
    </div>
    <div v-else-if="filteredProducts.length === 0"
         style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9CA3AF;">
      <div style="font-size:32px;margin-bottom:8px;">☕</div>
      <div>Tidak ada produk ditemukan</div>
      <div style="font-size:12px;margin-top:4px;">
        Total: {{ productStore.products.length }} produk | {{ productStore.categories.length }} kategori
      </div>
    </div>
    <div v-else style="flex:1;overflow-y:auto;padding:12px;
                       display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
                       gap:10px;align-content:start;">
      <div v-for="p in filteredProducts" :key="p.id" @click="addToCart(p)"
           style="background:#fff;border:1px solid #E5E7EB;border-radius:8px;padding:12px;
                  cursor:pointer;transition:all .15s;user-select:none;"
           onmouseover="this.style.borderColor='#1D9E75';this.style.boxShadow='0 0 0 2px #D1FAE5'"
           onmouseout="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
        <div style="font-size:24px;text-align:center;margin-bottom:6px;">☕</div>
        <div style="font-size:12px;font-weight:600;color:#1F2937;text-align:center;line-height:1.3;">{{ p.name }}</div>
        <div style="font-size:13px;font-weight:700;color:#1D9E75;text-align:center;margin-top:4px;">
          {{ formatRp(p.base_price) }}
        </div>
        <div v-if="p.category_name" style="font-size:10px;color:#9CA3AF;text-align:center;margin-top:2px;">
          {{ p.category_name }}
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div style="width:360px;display:flex;flex-direction:column;background:#fff;">
    <div style="padding:12px 16px;border-bottom:1px solid #E5E7EB;
                display:flex;justify-content:space-between;align-items:center;">
      <div style="font-weight:600;">Pesanan</div>
      <div style="display:flex;gap:8px;align-items:center;">
        <select v-model="cart.orderType" class="form-select" style="width:110px;font-size:12px;">
          <option value="dine_in">Dine In</option>
          <option value="takeaway">Takeaway</option>
        </select>
        <button v-if="!cart.isEmpty" class="btn btn-outline btn-sm" @click="cart.clear()">Batal</button>
      </div>
    </div>

    <div v-if="cart.orderType === 'dine_in'" style="padding:8px 16px;border-bottom:1px solid #F3F4F6;">
      <input v-model="cart.tableNumber" class="form-input"
             placeholder="Nomor meja (opsional)" style="font-size:13px;" />
    </div>

    <!-- Items -->
    <div style="flex:1;overflow-y:auto;padding:8px 0;">
      <div v-if="cart.isEmpty" style="padding:40px;text-align:center;color:#9CA3AF;">
        <div style="font-size:32px;margin-bottom:8px;">🛒</div>
        <div>Belum ada item</div>
      </div>
      <div v-for="item in cart.items" :key="item._key"
           style="padding:8px 16px;border-bottom:1px solid #F9FAFB;display:flex;gap:10px;align-items:center;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ item.name }}</div>
          <div style="font-size:12px;color:#6B7280;">{{ formatRp(item.unit_price) }}</div>
        </div>
        <div style="display:flex;align-items:center;gap:4px;">
          <button class="btn btn-outline btn-sm" @click="cart.updateQty(item._key, item.qty - 1)" style="width:26px;height:26px;padding:0;">-</button>
          <span style="font-size:13px;font-weight:600;min-width:20px;text-align:center;">{{ item.qty }}</span>
          <button class="btn btn-outline btn-sm" @click="cart.updateQty(item._key, item.qty + 1)" style="width:26px;height:26px;padding:0;">+</button>
        </div>
        <div style="font-size:13px;font-weight:600;min-width:70px;text-align:right;">{{ formatRp(item.unit_price * item.qty) }}</div>
      </div>
    </div>

    <!-- Voucher -->
    <div style="padding:8px 16px;border-top:1px solid #F3F4F6;">
      <div v-if="!cart.voucherCode || !cart.voucherApplied" style="display:flex;gap:6px;">
        <input v-model="cart.voucherCode" class="form-input"
               placeholder="Kode voucher" style="flex:1;font-size:13px;text-transform:uppercase;"
               :disabled="applyingVoucher" @keyup.enter="applyVoucher" />
        <button class="btn btn-outline btn-sm" @click="applyVoucher"
                :disabled="applyingVoucher || !cart.voucherCode.trim()">
          {{ applyingVoucher ? '...' : 'Pakai' }}
        </button>
      </div>
      <div v-else style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:6px;padding:8px 12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;font-weight:600;color:#065F46;">
              Voucher: <code style="background:#A7F3D0;padding:1px 6px;border-radius:4px;">{{ cart.voucherCode }}</code>
            </div>
            <!-- Tampilkan hanya log yang punya discount_code (pure voucher) -->
            <div v-for="log in cart.voucherApplied.logs.filter(l => l.discount_code)"
                 :key="log.discount_id || log.discount_name"
                 style="font-size:11px;color:#065F46;margin-top:2px;">
              {{ log.discount_name }} — -{{ formatRp(log.amount) }}
            </div>
          </div>
          <button @click="cart.clearDiscount()"
                  style="background:none;border:none;cursor:pointer;color:#065F46;font-size:18px;line-height:1;padding:0 0 0 8px;">✕</button>
        </div>
      </div>
      <div v-if="voucherError"
           style="margin-top:6px;font-size:12px;color:#991B1B;background:#FEE2E2;padding:4px 8px;border-radius:4px;">
        {{ voucherError }}
      </div>
    </div>

    <!-- Summary cart (hanya subtotal + diskon voucher dari cart) -->
    <div style="padding:12px 16px;border-top:1px solid #E5E7EB;">
      <div style="display:flex;justify-content:space-between;font-size:13px;color:#6B7280;margin-bottom:4px;">
        <span>Subtotal</span><span>{{ formatRp(cart.subtotal) }}</span>
      </div>
      <div v-if="voucherDiscTotal > 0"
           style="display:flex;justify-content:space-between;font-size:13px;color:#065F46;margin-bottom:4px;">
        <span>Diskon Voucher</span><span>-{{ formatRp(voucherDiscTotal) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;
                  border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
        <span>Total</span>
        <span>{{ formatRp(cart.subtotal - voucherDiscTotal) }}</span>
      </div>
    </div>

    <!-- Checkout -->
<div style="padding:12px 16px;border-top:1px solid #E5E7EB;">

  <!-- Banner peringatan kalau shift belum buka -->
  <div v-if="!activeShift"
       style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:6px;
              padding:8px 12px;margin-bottom:8px;font-size:12px;color:#92400E;
              display:flex;align-items:center;gap:6px;">
    <span>⚠️</span>
    <span>Shift belum dibuka. <router-link to="/shifts" style="color:#92400E;font-weight:600;text-decoration:underline;">Buka shift</router-link> terlebih dahulu.</span>
  </div>

  <select v-model="cart.paymentMethod" class="form-select" style="margin-bottom:8px;">
    <option value="cash">Cash</option>
    <option value="qris">QRIS</option>
    <option value="edc">EDC / Debit</option>
    <option value="ewallet">E-Wallet</option>
  </select>
  <button class="btn btn-primary w-full btn-lg" @click="checkout"
          :disabled="cart.isEmpty || checkingOut || !activeShift"
          style="font-size:15px;"
          :title="!activeShift ? 'Buka shift terlebih dahulu' : ''">
    <span v-if="checkingOut" class="spinner"></span>
    <span>{{ checkingOut ? 'Memuat diskon...' : `Bayar ${formatRp(cart.subtotal - voucherDiscTotal)}` }}</span>
  </button>
</div>
  </div>
</div>

<!-- ── Modal Proses Pembayaran ─────────────────────────────── -->
<div v-if="showPayModal"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;padding:16px;">
  <div class="card" style="width:420px;max-height:92vh;overflow-y:auto;">
    <div style="font-weight:600;font-size:16px;margin-bottom:12px;">Proses Pembayaran</div>

    <div style="background:#F9FAFB;border-radius:6px;padding:10px 12px;margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
        <span style="color:#6B7280;">Subtotal</span>
        <span>{{ formatRp(cart.subtotal) }}</span>
      </div>

      <!-- Auto discounts — dihitung fresh saat modal dibuka -->
      <template v-if="autoDiscounts.length">
        <div style="font-size:11px;color:#6B7280;margin:4px 0;font-weight:500;">Diskon berlaku otomatis:</div>
        <div v-for="d in autoDiscounts" :key="d.discount_id || d.discount_name"
             style="display:flex;justify-content:space-between;font-size:12px;color:#065F46;margin-bottom:3px;">
          <div style="display:flex;align-items:center;gap:4px;">
            <span style="width:6px;height:6px;background:#1D9E75;border-radius:50%;display:inline-block;"></span>
            <span>{{ d.discount_name }}</span>
          </div>
          <span style="font-weight:600;">-{{ formatRp(d.amount) }}</span>
        </div>
      </template>

      <!-- Voucher — hanya log dengan discount_code, dihitung fresh saat modal dibuka -->
      <template v-if="freshVoucherLogs.length">
        <div v-for="log in freshVoucherLogs" :key="log.discount_id || log.discount_name"
             style="display:flex;justify-content:space-between;font-size:12px;color:#5B21B6;margin-bottom:3px;">
          <div style="display:flex;align-items:center;gap:4px;">
            <span style="width:6px;height:6px;background:#7F77DD;border-radius:50%;display:inline-block;"></span>
            <span>{{ log.discount_name }}</span>
            <code style="font-size:10px;background:#EDE9FE;color:#5B21B6;padding:1px 4px;border-radius:3px;">
              {{ cart.voucherCode }}
            </code>
          </div>
          <span style="font-weight:600;">-{{ formatRp(log.amount) }}</span>
        </div>
      </template>

      <!-- Diskon manual -->
      <div v-if="manualDiscountAmount > 0"
           style="display:flex;justify-content:space-between;font-size:12px;color:#92400E;margin-bottom:3px;">
        <div style="display:flex;align-items:center;gap:4px;">
          <span style="width:6px;height:6px;background:#EF9F27;border-radius:50%;display:inline-block;"></span>
          <span>Diskon Manual {{ manualDiscType === 'pct' ? `(${manualDiscValue}%)` : '' }}</span>
        </div>
        <span style="font-weight:600;">-{{ formatRp(manualDiscountAmount) }}</span>
      </div>

      <!-- Total -->
      <div style="border-top:1px solid #E5E7EB;padding-top:8px;margin-top:6px;
                  display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:15px;font-weight:700;">Total</span>
        <span style="font-size:18px;font-weight:700;color:#1D9E75;">{{ formatRp(finalGrandTotal) }}</span>
      </div>
      <div v-if="totalAllDiscount > 0"
           style="text-align:right;font-size:11px;color:#065F46;margin-top:2px;">
        Hemat {{ formatRp(totalAllDiscount) }}
      </div>
    </div>

    <!-- Diskon Manual -->
    <div style="background:#FFFBEB;border:1px solid #FCD34D;border-radius:8px;padding:10px 12px;margin-bottom:12px;">
      <div style="font-size:12px;font-weight:600;color:#92400E;margin-bottom:8px;">Diskon Manual (opsional)</div>
      <div style="display:flex;gap:6px;align-items:center;">
        <div style="display:flex;border:1px solid #E5E7EB;border-radius:6px;overflow:hidden;">
          <button @click="manualDiscType = 'pct'"
                  :style="{padding:'6px 10px',fontSize:'12px',border:'none',cursor:'pointer',fontWeight:600,
                           background: manualDiscType==='pct' ? '#EF9F27' : '#fff',
                           color: manualDiscType==='pct' ? '#fff' : '#6B7280'}">%</button>
          <button @click="manualDiscType = 'rp'"
                  :style="{padding:'6px 10px',fontSize:'12px',border:'none',cursor:'pointer',fontWeight:600,
                           background: manualDiscType==='rp' ? '#EF9F27' : '#fff',
                           color: manualDiscType==='rp' ? '#fff' : '#6B7280'}">Rp</button>
        </div>
        <input v-model.number="manualDiscValue" class="form-input" type="number" min="0" step="1"
               :max="manualDiscType === 'pct' ? 100 : cart.subtotal"
               :placeholder="manualDiscType === 'pct' ? 'Contoh: 10 (%)' : 'Contoh: 5000'"
               style="flex:1;font-size:13px;" />
        <button v-if="manualDiscValue > 0" class="btn btn-outline btn-sm"
                @click="manualDiscValue = 0" style="color:#E24B4A;border-color:#FECACA;">✕</button>
      </div>
      <div v-if="manualDiscountAmount > 0"
           style="margin-top:6px;font-size:12px;color:#92400E;">
        Potongan: <strong>{{ formatRp(manualDiscountAmount) }}</strong>
        · Total: <strong>{{ formatRp(finalGrandTotal) }}</strong>
      </div>
    </div>

    <!-- Input uang -->
    <div class="form-group">
      <label class="form-label">Uang Diterima</label>
      <input ref="payInput" v-model.number="payAmount" class="form-input"
             type="number" :min="finalGrandTotal" step="1000"
             style="font-size:18px;font-weight:600;" @keyup.enter="confirmPay" />
    </div>

    <div v-if="payAmount >= finalGrandTotal && payAmount > 0"
         style="background:#D1FAE5;color:#065F46;padding:8px 12px;border-radius:6px;
                font-size:14px;margin-bottom:12px;font-weight:600;">
      Kembalian: {{ formatRp(payAmount - finalGrandTotal) }}
    </div>
    <div v-else-if="payAmount > 0 && payAmount < finalGrandTotal"
         style="background:#FEE2E2;color:#991B1B;padding:8px 12px;border-radius:6px;
                font-size:13px;margin-bottom:12px;">
      Kurang: {{ formatRp(finalGrandTotal - payAmount) }}
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
      <button v-for="n in nominalShortcuts" :key="n" class="btn btn-outline btn-sm" @click="payAmount = n">
        {{ formatRp(n) }}
      </button>
      <button class="btn btn-outline btn-sm" @click="payAmount = finalGrandTotal">Pas</button>
    </div>

    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showPayModal = false">Batal</button>
      <button class="btn btn-primary" style="flex:1" @click="confirmPay"
              :disabled="payAmount < finalGrandTotal || paying">
        {{ paying ? 'Memproses...' : 'Konfirmasi' }}
      </button>
    </div>
  </div>
</div>

<!-- Modal Struk -->
<div v-if="lastOrder"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:320px;text-align:center;">
    <div style="font-size:40px;margin-bottom:8px;">✅</div>
    <div style="font-size:18px;font-weight:700;">Pembayaran Berhasil!</div>
    <div style="color:#6B7280;font-size:13px;margin:4px 0 16px;">{{ lastOrder.order_number }}</div>
    <div style="background:#F9FAFB;border-radius:8px;padding:12px;margin-bottom:16px;text-align:left;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
        <span>Total</span><span style="font-weight:600;">{{ formatRp(lastOrder.grand_total) }}</span>
      </div>
      <div v-if="lastOrder.total_discount > 0"
           style="display:flex;justify-content:space-between;font-size:12px;color:#065F46;margin-bottom:6px;">
        <span>Hemat</span><span>{{ formatRp(lastOrder.total_discount) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
        <span>Dibayar</span><span>{{ formatRp(lastOrder.paid) }}</span>
      </div>
      <div v-if="lastOrder.change > 0"
           style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;color:#1D9E75;">
        <span>Kembalian</span><span>{{ formatRp(lastOrder.change) }}</span>
      </div>
    </div>
    <button class="btn btn-primary w-full" @click="closeReceipt">Order Baru</button>
  </div>
</div>
</AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useCartStore, useProductStore } from '@/stores'
import { orderApi, discountApi, shiftApi } from '@/services/api'

const cart         = useCartStore()
const productStore = useProductStore()

const search          = ref('')
const selectedCat     = ref('')
const checkingOut     = ref(false)
const applyingVoucher = ref(false)
const voucherError    = ref('')
const lastOrder       = ref(null)
const showPayModal    = ref(false)
const payAmount       = ref(0)
const paying          = ref(false)
const payInput        = ref(null)
const activeShift = ref(null)

// ── State modal — SELALU fresh setiap buka modal ─────────────────
// autoDiscounts: diskon otomatis dari backend (tanpa voucher), fresh setiap checkout()
const autoDiscounts  = ref([])
const autoDiscTotal  = ref(0)
// freshVoucherLogs: hasil recalculate voucher pada saat checkout(), fresh dan tidak stale
const freshVoucherLogs  = ref([])
const freshVoucherTotal = ref(0)

const manualDiscType  = ref('pct')
const manualDiscValue = ref(0)

// ── Computed ────────────────────────────────────────────────────
const categories = computed(() => productStore.categories)

const filteredProducts = computed(() => {
  let list = productStore.products.filter(
    p => parseInt(p.active) === 1 && parseInt(p.is_bundle) === 0
  )
  if (selectedCat.value) list = list.filter(p => p.category_id === selectedCat.value)
  if (search.value)      list = list.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()))
  return list
})

// Total diskon voucher untuk display di sidebar (pakai data cart yang ada)
// Hanya ambil log dengan discount_code supaya tidak mix dengan auto discount
const voucherDiscTotal = computed(() => {
  if (!cart.voucherApplied) return 0
  return cart.voucherApplied.logs
    .filter(l => l.discount_code)
    .reduce((s, l) => s + (l.amount || 0), 0)
})

// Grand total setelah semua diskon (dihitung fresh dari modal state)
const baseAfterAutoAndVoucher = computed(() =>
  Math.max(0, cart.subtotal - autoDiscTotal.value - freshVoucherTotal.value)
)

const manualDiscountAmount = computed(() => {
  if (!manualDiscValue.value || manualDiscValue.value <= 0) return 0
  if (manualDiscType.value === 'pct') {
    return Math.round(baseAfterAutoAndVoucher.value * Math.min(manualDiscValue.value, 100) / 100)
  }
  return Math.min(manualDiscValue.value, baseAfterAutoAndVoucher.value)
})

const finalGrandTotal = computed(() =>
  Math.max(0, baseAfterAutoAndVoucher.value - manualDiscountAmount.value)
)

const totalAllDiscount = computed(() =>
  cart.subtotal - finalGrandTotal.value
)

const nominalShortcuts = computed(() => {
  const gt  = finalGrandTotal.value
  const all = [10000, 20000, 50000, 100000, 200000]
  return all.filter(n => n >= gt).slice(0, 3)
})

// ── Helpers ─────────────────────────────────────────────────────
function formatRp(val) { return 'Rp ' + Number(val || 0).toLocaleString('id-ID') }
function addToCart(p)  { cart.addItem(p) }

// ── Apply Voucher (input di sidebar) ────────────────────────────
async function applyVoucher() {
  const code = cart.voucherCode.trim().toUpperCase()
  if (!code || cart.isEmpty) return

  voucherError.value    = ''
  applyingVoucher.value = true
  try {
    const { data } = await discountApi.calculate({
      voucher_code:   code,
      payment_method: cart.paymentMethod,
      items:          cart.items.map(i => ({ product_id: i.product_id, qty: i.qty, unit_price: i.unit_price })),
      subtotal:       cart.subtotal,
    })
    if (data.data.discount_total > 0) {
      // Simpan semua log — nanti saat modal dibuka akan di-recalculate
      cart.setDiscount({ total: data.data.discount_total, logs: data.data.logs }, code)
      cart.voucherCode = code
    } else {
      voucherError.value = 'Voucher tidak berlaku untuk item ini atau syarat tidak terpenuhi'
      cart.clearDiscount()
    }
  } catch (e) {
    voucherError.value = e.response?.data?.message || 'Kode voucher tidak valid'
    cart.clearDiscount()
  } finally {
    applyingVoucher.value = false
  }
}

// ── Checkout — recalculate SEMUA diskon fresh saat modal dibuka ──
async function checkout() {
  if (cart.isEmpty) return
  checkingOut.value = true

  // Reset semua state modal
  autoDiscounts.value     = []
  autoDiscTotal.value     = 0
  freshVoucherLogs.value  = []
  freshVoucherTotal.value = 0
  manualDiscType.value    = 'pct'
  manualDiscValue.value   = 0
  payAmount.value         = 0

  const itemsPayload = cart.items.map(i => ({
    product_id: i.product_id, qty: i.qty, unit_price: i.unit_price,
  }))

  try {
    // Panggil dua kali secara PARALEL dan TERPISAH:
    // 1. Hitung diskon otomatis (tanpa voucher)
    // 2. Hitung diskon voucher saja (dengan voucher, tanpa auto)
    // Ini memastikan tidak ada overlap/double-count

    const promises = [
      // Auto discount — tanpa voucher
      discountApi.calculate({
        voucher_code:   null,
        payment_method: cart.paymentMethod,
        items:          itemsPayload,
        subtotal:       cart.subtotal,
      }),
    ]

    // Recalculate voucher jika ada — fresh sesuai kondisi jam sekarang
    if (cart.voucherCode && cart.voucherApplied) {
      promises.push(
        discountApi.calculate({
          voucher_code:   cart.voucherCode,
          payment_method: cart.paymentMethod,
          items:          itemsPayload,
          subtotal:       cart.subtotal,
        })
      )
    }

    const results = await Promise.allSettled(promises)

    // Auto discount result
    if (results[0].status === 'fulfilled') {
      const d = results[0].value.data?.data
      autoDiscounts.value = d?.logs || []
      autoDiscTotal.value = d?.discount_total || 0
    }

    // Voucher result — hanya ambil log yang punya discount_code
    if (results[1]?.status === 'fulfilled') {
      const d = results[1].value.data?.data
      const allLogs = d?.logs || []
      // Filter: hanya log yang murni dari voucher (punya discount_code)
      // Ini mencegah Happy Hour yang ikut terbawa saat calculate voucher
      freshVoucherLogs.value  = allLogs.filter(l => l.discount_code)
      freshVoucherTotal.value = freshVoucherLogs.value.reduce((s, l) => s + (l.amount || 0), 0)

      // Update cart.voucherApplied dengan data fresh
      if (freshVoucherTotal.value > 0) {
        cart.voucherApplied = {
          code: cart.voucherCode,
          logs: allLogs,
          total: freshVoucherTotal.value,
        }
      } else {
        // Voucher sudah tidak berlaku (mungkin expired atau limit habis)
        freshVoucherLogs.value  = []
        freshVoucherTotal.value = 0
      }
    }

  } catch (e) {
    console.error('Checkout discount error:', e)
  }

  checkingOut.value  = false
  showPayModal.value = true
  await nextTick()
  payInput.value?.focus()
}

// ── Confirm Pay ──────────────────────────────────────────────────
async function confirmPay() {
  if (payAmount.value < finalGrandTotal.value) return
  paying.value = true
  try {
    const payload = {
      ...cart.toOrderPayload(),
      // Override voucher_code dengan yang sudah tervalidasi fresh
      voucher_code:           cart.voucherCode || null,
      manual_discount_type:   manualDiscValue.value > 0 ? manualDiscType.value  : null,
      manual_discount_value:  manualDiscValue.value > 0 ? manualDiscValue.value : null,
      manual_discount_amount: manualDiscountAmount.value,
    }

    const { data: orderRes } = await orderApi.create(payload)
    const { data: payRes }   = await orderApi.pay(orderRes.data.order_id, {
      method: cart.paymentMethod,
      amount: payAmount.value,
    })

    lastOrder.value = {
      order_number:   orderRes.data.order_number,
      grand_total:    orderRes.data.grand_total,
      total_discount: orderRes.data.discount_total || 0,
      paid:           payAmount.value,
      change:         payRes.data.change,
    }

    showPayModal.value      = false
    autoDiscounts.value     = []
    autoDiscTotal.value     = 0
    freshVoucherLogs.value  = []
    freshVoucherTotal.value = 0
    manualDiscValue.value   = 0
    cart.clear()

  } catch (e) {
    alert(e.response?.data?.message || 'Gagal memproses pembayaran')
  } finally {
    paying.value = false
  }
}

function closeReceipt() {
  lastOrder.value    = null
  voucherError.value = ''
  productStore.loadAll()
}

onMounted(async () => {
  productStore.loadAll()
  try {
    const { data } = await shiftApi.active()
    activeShift.value = data.data || null
  } catch {
    activeShift.value = null
  }
})
</script>
