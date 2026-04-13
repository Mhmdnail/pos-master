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

    <!-- Error state -->
    <div v-if="productStore.error" style="margin:16px;background:#FEE2E2;color:#991B1B;padding:12px;border-radius:8px;font-size:13px;">
      Gagal memuat produk: {{ productStore.error }}
      <button @click="productStore.loadAll()" style="margin-left:8px;text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">Coba lagi</button>
    </div>

    <!-- Loading state -->
    <div v-else-if="productStore.loading" style="flex:1;display:flex;align-items:center;justify-content:center;">
      <span class="spinner"></span>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredProducts.length === 0" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9CA3AF;">
      <div style="font-size:32px;margin-bottom:8px;">☕</div>
      <div style="font-size:14px;">Tidak ada produk ditemukan</div>
      <div style="font-size:12px;margin-top:4px;">
        Total produk di store: {{ productStore.products.length }} |
        Kategori: {{ productStore.categories.length }}
      </div>
    </div>

    <!-- Grid produk -->
    <div v-else style="flex:1;overflow-y:auto;padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;align-content:start;">
      <div
        v-for="p in filteredProducts"
        :key="p.id"
        @click="addToCart(p)"
        style="background:#fff;border:1px solid #E5E7EB;border-radius:8px;padding:12px;cursor:pointer;transition:all .15s;user-select:none;"
        onmouseover="this.style.borderColor='#1D9E75';this.style.boxShadow='0 0 0 2px #D1FAE5'"
        onmouseout="this.style.borderColor='#E5E7EB';this.style.boxShadow='none'">
        <div style="font-size:24px;text-align:center;margin-bottom:6px;">☕</div>
        <div style="font-size:12px;font-weight:600;color:#1F2937;text-align:center;line-height:1.3;">{{ p.name }}</div>
        <div style="font-size:13px;font-weight:700;color:#1D9E75;text-align:center;margin-top:4px;">
          {{ formatRp(p.base_price) }}
        </div>
        <div v-if="p.category_name" style="font-size:10px;color:#9CA3AF;text-align:center;margin-top:2px;">{{ p.category_name }}</div>
      </div>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div style="width:360px;display:flex;flex-direction:column;background:#fff;">
    <div style="padding:12px 16px;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center;">
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
      <input v-model="cart.tableNumber" class="form-input" placeholder="Nomor meja (opsional)" style="font-size:13px;" />
    </div>

    <!-- Items -->
    <div style="flex:1;overflow-y:auto;padding:8px 0;">
      <div v-if="cart.isEmpty" style="padding:40px;text-align:center;color:#9CA3AF;">
        <div style="font-size:32px;margin-bottom:8px;">🛒</div>
        <div>Belum ada item</div>
      </div>

      <div
        v-for="item in cart.items"
        :key="item._key"
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
      <div style="display:flex;gap:6px;">
        <input v-model="cart.voucherCode" class="form-input" placeholder="Kode voucher" style="flex:1;font-size:13px;" @keyup.enter="applyVoucher" />
        <button class="btn btn-outline btn-sm" @click="applyVoucher" :disabled="applyingVoucher">Pakai</button>
      </div>
      <div v-if="cart.discount.total > 0" style="margin-top:6px;font-size:12px;color:#065F46;background:#D1FAE5;padding:4px 8px;border-radius:4px;">
        Diskon: -{{ formatRp(cart.discount.total) }}
        <button @click="cart.clearDiscount()" style="margin-left:6px;background:none;border:none;cursor:pointer;color:#065F46;font-size:11px;">✕</button>
      </div>
    </div>

    <!-- Summary -->
    <div style="padding:12px 16px;border-top:1px solid #E5E7EB;">
      <div style="display:flex;justify-content:space-between;font-size:13px;color:#6B7280;margin-bottom:4px;">
        <span>Subtotal</span><span>{{ formatRp(cart.subtotal) }}</span>
      </div>
      <div v-if="cart.discount.total > 0" style="display:flex;justify-content:space-between;font-size:13px;color:#065F46;margin-bottom:4px;">
        <span>Diskon</span><span>-{{ formatRp(cart.discount.total) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
        <span>Total</span><span>{{ formatRp(cart.grandTotal) }}</span>
      </div>
    </div>

    <!-- Checkout -->
    <div style="padding:12px 16px;border-top:1px solid #E5E7EB;">
      <select v-model="cart.paymentMethod" class="form-select" style="margin-bottom:8px;">
        <option value="cash">Cash</option>
        <option value="qris">QRIS</option>
        <option value="edc">EDC / Debit</option>
        <option value="ewallet">E-Wallet</option>
      </select>
      <button
        class="btn btn-primary w-full btn-lg"
        @click="checkout"
        :disabled="cart.isEmpty || checkingOut"
        style="font-size:15px;">
        <span v-if="checkingOut" class="spinner"></span>
        <span>{{ checkingOut ? 'Memproses...' : `Bayar ${formatRp(cart.grandTotal)}` }}</span>
      </button>
    </div>
  </div>
</div>

<!-- Modal bayar cash (input nominal) -->
<div v-if="showPayModal" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:320px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:4px;">Proses Pembayaran</div>
    <div style="font-size:13px;color:#6B7280;margin-bottom:16px;">Total: <strong>{{ formatRp(cart.grandTotal) }}</strong></div>
    <div class="form-group">
      <label class="form-label">Uang Diterima</label>
      <input
        ref="payInput"
        v-model.number="payAmount"
        class="form-input"
        type="number"
        :min="cart.grandTotal"
        style="font-size:18px;font-weight:600;"
        @keyup.enter="confirmPay" />
    </div>
    <div v-if="payAmount >= cart.grandTotal" style="background:#D1FAE5;color:#065F46;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      Kembalian: <strong>{{ formatRp(payAmount - cart.grandTotal) }}</strong>
    </div>
    <div v-else-if="payAmount > 0" style="background:#FEE2E2;color:#991B1B;padding:8px 12px;border-radius:6px;font-size:13px;margin-bottom:12px;">
      Kurang: {{ formatRp(cart.grandTotal - payAmount) }}
    </div>
    <!-- Shortcut nominal -->
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
      <button v-for="n in nominalShortcuts" :key="n" class="btn btn-outline btn-sm" @click="payAmount = n">
        {{ formatRp(n) }}
      </button>
      <button class="btn btn-outline btn-sm" @click="payAmount = cart.grandTotal">Pas</button>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showPayModal = false">Batal</button>
      <button
        class="btn btn-primary" style="flex:1"
        @click="confirmPay"
        :disabled="payAmount < cart.grandTotal || paying">
        {{ paying ? 'Memproses...' : 'Konfirmasi' }}
      </button>
    </div>
  </div>
</div>

<!-- Modal struk sukses -->
<div v-if="lastOrder" style="position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:320px;text-align:center;">
    <div style="font-size:40px;margin-bottom:8px;">✅</div>
    <div style="font-size:18px;font-weight:700;">Pembayaran Berhasil!</div>
    <div style="color:#6B7280;font-size:13px;margin:4px 0 16px;">{{ lastOrder.order_number }}</div>
    <div style="background:#F9FAFB;border-radius:8px;padding:12px;margin-bottom:16px;text-align:left;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
        <span>Total</span><span style="font-weight:600;">{{ formatRp(lastOrder.grand_total) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
        <span>Dibayar</span><span>{{ formatRp(lastOrder.paid) }}</span>
      </div>
      <div v-if="lastOrder.change > 0" style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;color:#1D9E75;">
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
import { orderApi, discountApi } from '@/services/api'

const cart         = useCartStore()
const productStore = useProductStore()

const search      = ref('')
const selectedCat = ref('')
const checkingOut = ref(false)
const applyingVoucher = ref(false)
const lastOrder   = ref(null)
const showPayModal = ref(false)
const payAmount    = ref(0)
const paying       = ref(false)
const payInput     = ref(null)
const pendingOrderId = ref(null)
const pendingGrandTotal = ref(0)

const categories = computed(() => productStore.categories)

const filteredProducts = computed(() => {
  let list = productStore.products.filter(p => p.active != 0 && !p.is_bundle)
  if (selectedCat.value) list = list.filter(p => p.category_id === selectedCat.value)
  if (search.value)      list = list.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()))
  return list
})

// Shortcut nominal uang
const nominalShortcuts = computed(() => {
  const gt = cart.grandTotal
  const options = [10000, 20000, 50000, 100000]
  return options.filter(n => n >= gt).slice(0, 3)
})

onMounted(() => productStore.loadAll())

function formatRp(val) {
  return 'Rp ' + Number(val || 0).toLocaleString('id-ID')
}

function addToCart(product) {
  cart.addItem(product)
}

async function applyVoucher() {
  if (!cart.voucherCode || cart.isEmpty) return
  applyingVoucher.value = true
  try {
    const { data } = await discountApi.calculate({
      voucher_code:   cart.voucherCode,
      payment_method: cart.paymentMethod,
      items:          cart.items.map(i => ({ product_id: i.product_id, qty: i.qty, unit_price: i.unit_price })),
      subtotal:       cart.subtotal,
    })
    cart.setDiscount({ total: data.data.discount_total, logs: data.data.logs })
  } catch (e) {
    alert(e.response?.data?.message || 'Voucher tidak valid')
    cart.clearDiscount()
  } finally {
    applyingVoucher.value = false
  }
}

async function checkout() {
  if (cart.isEmpty) return
  checkingOut.value = true
  try {
    const { data } = await orderApi.create(cart.toOrderPayload())
    pendingOrderId.value    = data.data.order_id
    pendingGrandTotal.value = data.data.grand_total
    payAmount.value = data.data.grand_total
    showPayModal.value = true
    await nextTick()
    payInput.value?.focus()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal membuat order')
  } finally {
    checkingOut.value = false
  }
}

async function confirmPay() {
  if (payAmount.value < pendingGrandTotal.value) return
  paying.value = true
  try {
    const { data } = await orderApi.pay(pendingOrderId.value, {
      method: cart.paymentMethod,
      amount: payAmount.value,
    })
    lastOrder.value = {
      order_number: data.data.order_number || '-',
      grand_total:  pendingGrandTotal.value,
      paid:         payAmount.value,
      change:       data.data.change,
    }
    showPayModal.value = false
    cart.clear()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal memproses pembayaran')
  } finally {
    paying.value = false
  }
}

function closeReceipt() {
  lastOrder.value = null
  productStore.loadAll()
}
</script>
