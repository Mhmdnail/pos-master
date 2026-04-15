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

    <!-- Error -->
    <div v-if="productStore.error"
         style="margin:16px;background:#FEE2E2;color:#991B1B;padding:12px;border-radius:8px;font-size:13px;">
      Gagal memuat produk: {{ productStore.error }}
      <button @click="productStore.loadAll()"
              style="margin-left:8px;text-decoration:underline;background:none;border:none;cursor:pointer;color:#991B1B;">
        Coba lagi
      </button>
    </div>

    <!-- Loading -->
    <div v-else-if="productStore.loading"
         style="flex:1;display:flex;align-items:center;justify-content:center;">
      <span class="spinner"></span>
    </div>

    <!-- Empty -->
    <div v-else-if="filteredProducts.length === 0"
         style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9CA3AF;">
      <div style="font-size:32px;margin-bottom:8px;">☕</div>
      <div>Tidak ada produk ditemukan</div>
      <div style="font-size:12px;margin-top:4px;">
        Total: {{ productStore.products.length }} produk | {{ productStore.categories.length }} kategori
      </div>
    </div>

    <!-- Grid produk -->
    <div v-else style="flex:1;overflow-y:auto;padding:12px;
                       display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
                       gap:10px;align-content:start;">
      <div
        v-for="p in filteredProducts" :key="p.id"
        @click="addToCart(p)"
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

    <!-- Cart header -->
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

    <!-- Nomor meja -->
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
           style="padding:8px 16px;border-bottom:1px solid #F9FAFB;
                  display:flex;gap:10px;align-items:center;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            {{ item.name }}
          </div>
          <div style="font-size:12px;color:#6B7280;">{{ formatRp(item.unit_price) }}</div>
        </div>
        <div style="display:flex;align-items:center;gap:4px;">
          <button class="btn btn-outline btn-sm"
                  @click="cart.updateQty(item._key, item.qty - 1)"
                  style="width:26px;height:26px;padding:0;">-</button>
          <span style="font-size:13px;font-weight:600;min-width:20px;text-align:center;">{{ item.qty }}</span>
          <button class="btn btn-outline btn-sm"
                  @click="cart.updateQty(item._key, item.qty + 1)"
                  style="width:26px;height:26px;padding:0;">+</button>
        </div>
        <div style="font-size:13px;font-weight:600;min-width:70px;text-align:right;">
          {{ formatRp(item.unit_price * item.qty) }}
        </div>
      </div>
    </div>

    <!-- Voucher section -->
    <div style="padding:8px 16px;border-top:1px solid #F3F4F6;">
      <!-- Belum ada voucher yang di-apply -->
      <div v-if="!cart.voucherApplied" style="display:flex;gap:6px;">
        <input
          v-model="cart.voucherCode"
          class="form-input"
          placeholder="Kode voucher"
          style="flex:1;font-size:13px;text-transform:uppercase;"
          :disabled="applyingVoucher"
          @keyup.enter="applyVoucher" />
        <button
          class="btn btn-outline btn-sm"
          @click="applyVoucher"
          :disabled="applyingVoucher || !cart.voucherCode.trim()">
          {{ applyingVoucher ? '...' : 'Pakai' }}
        </button>
      </div>

      <!-- Voucher sudah di-apply — tampilkan info + tombol hapus -->
      <div v-else
           style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:6px;padding:8px 12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;font-weight:600;color:#065F46;">
              Voucher aktif:
              <code style="background:#A7F3D0;padding:1px 6px;border-radius:4px;">{{ cart.voucherApplied.code }}</code>
            </div>
            <div v-for="log in cart.voucherApplied.logs" :key="log.discount_id"
                 style="font-size:11px;color:#065F46;margin-top:2px;">
              {{ log.discount_name }} — -{{ formatRp(log.amount) }}
            </div>
          </div>
          <!-- Tombol hapus voucher — kasir bisa batalkan manual -->
          <button
            @click="cart.clearDiscount()"
            style="background:none;border:none;cursor:pointer;color:#065F46;
                   font-size:18px;line-height:1;padding:0 0 0 8px;"
            title="Hapus voucher">✕</button>
        </div>
      </div>

      <!-- Error voucher -->
      <div v-if="voucherError"
           style="margin-top:6px;font-size:12px;color:#991B1B;background:#FEE2E2;
                  padding:4px 8px;border-radius:4px;">
        {{ voucherError }}
      </div>
    </div>

    <!-- Summary -->
    <div style="padding:12px 16px;border-top:1px solid #E5E7EB;">
      <div style="display:flex;justify-content:space-between;font-size:13px;color:#6B7280;margin-bottom:4px;">
        <span>Subtotal</span><span>{{ formatRp(cart.subtotal) }}</span>
      </div>
      <div v-if="cart.discount.total > 0"
           style="display:flex;justify-content:space-between;font-size:13px;color:#065F46;margin-bottom:4px;">
        <span>Diskon</span><span>-{{ formatRp(cart.discount.total) }}</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;
                  border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
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

<!-- Modal Proses Pembayaran -->
<div v-if="showPayModal"
     style="position:fixed;inset:0;background:rgba(0,0,0,.5);
            display:flex;align-items:center;justify-content:center;z-index:100;">
  <div class="card" style="width:340px;">
    <div style="font-weight:600;font-size:16px;margin-bottom:4px;">Proses Pembayaran</div>

    <!-- Ringkasan harga -->
    <div style="background:#F9FAFB;border-radius:6px;padding:10px 12px;margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
        <span style="color:#6B7280;">Subtotal</span>
        <span>{{ formatRp(cart.subtotal) }}</span>
      </div>
      <!-- Tampilkan detail diskon jika ada -->
      <template v-if="cart.discount.total > 0">
        <div v-for="log in cart.discount.logs" :key="log.discount_id"
             style="display:flex;justify-content:space-between;font-size:12px;color:#065F46;margin-bottom:2px;">
          <span>{{ log.discount_name }}</span>
          <span>-{{ formatRp(log.amount) }}</span>
        </div>
      </template>
      <div style="display:flex;justify-content:space-between;font-size:15px;
                  font-weight:700;border-top:1px solid #E5E7EB;padding-top:8px;margin-top:4px;">
        <span>Total</span>
        <span style="color:#1D9E75;">{{ formatRp(pendingGrandTotal) }}</span>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Uang Diterima</label>
      <input
        ref="payInput"
        v-model.number="payAmount"
        class="form-input"
        type="number"
        :min="pendingGrandTotal"
        step="1000"
        style="font-size:18px;font-weight:600;"
        @keyup.enter="confirmPay" />
    </div>

    <!-- Kembalian / Kekurangan -->
    <div v-if="payAmount >= pendingGrandTotal && payAmount > 0"
         style="background:#D1FAE5;color:#065F46;padding:8px 12px;border-radius:6px;
                font-size:14px;margin-bottom:12px;font-weight:600;">
      Kembalian: {{ formatRp(payAmount - pendingGrandTotal) }}
    </div>
    <div v-else-if="payAmount > 0 && payAmount < pendingGrandTotal"
         style="background:#FEE2E2;color:#991B1B;padding:8px 12px;border-radius:6px;
                font-size:13px;margin-bottom:12px;">
      Kurang: {{ formatRp(pendingGrandTotal - payAmount) }}
    </div>

    <!-- Shortcut nominal -->
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
      <button v-for="n in nominalShortcuts" :key="n"
              class="btn btn-outline btn-sm" @click="payAmount = n">
        {{ formatRp(n) }}
      </button>
      <button class="btn btn-outline btn-sm" @click="payAmount = pendingGrandTotal">Pas</button>
    </div>

    <div style="display:flex;gap:8px;">
      <button class="btn btn-outline" style="flex:1" @click="showPayModal = false">Batal</button>
      <button
        class="btn btn-primary" style="flex:1"
        @click="confirmPay"
        :disabled="payAmount < pendingGrandTotal || paying">
        {{ paying ? 'Memproses...' : 'Konfirmasi' }}
      </button>
    </div>
  </div>
</div>

<!-- Modal Struk Sukses -->
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
import { orderApi, discountApi } from '@/services/api'

const cart         = useCartStore()
const productStore = useProductStore()

const search           = ref('')
const selectedCat      = ref('')
const checkingOut      = ref(false)
const applyingVoucher  = ref(false)
const voucherError     = ref('')
const lastOrder        = ref(null)
const showPayModal     = ref(false)
const payAmount        = ref(0)
const paying           = ref(false)
const payInput         = ref(null)
const pendingGrandTotal = ref(0)

const categories = computed(() => productStore.categories)

const filteredProducts = computed(() => {
  let list = productStore.products.filter(
    p => parseInt(p.active) === 1 && parseInt(p.is_bundle) === 0
  )
  if (selectedCat.value) list = list.filter(p => p.category_id === selectedCat.value)
  if (search.value)      list = list.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()))
  return list
})

const nominalShortcuts = computed(() => {
  const gt  = pendingGrandTotal.value
  const all = [10000, 20000, 50000, 100000, 200000]
  // Tampilkan 3 nominal yang >= grand total
  return all.filter(n => n >= gt).slice(0, 3)
})

onMounted(() => productStore.loadAll())

function formatRp(val) {
  return 'Rp ' + Number(val || 0).toLocaleString('id-ID')
}

function addToCart(product) {
  cart.addItem(product)
}

// ── Apply Voucher ────────────────────────────────────────────────
async function applyVoucher() {
  const code = cart.voucherCode.trim().toUpperCase()
  if (!code || cart.isEmpty) return

  voucherError.value   = ''
  applyingVoucher.value = true
  try {
    const { data } = await discountApi.calculate({
      voucher_code:   code,
      payment_method: cart.paymentMethod,
      items:          cart.items.map(i => ({
        product_id: i.product_id,
        qty:        i.qty,
        unit_price: i.unit_price,
      })),
      subtotal: cart.subtotal,
    })

    if (data.data.discount_total > 0) {
      // Simpan voucher yang valid beserta hasil kalkulasinya
      cart.setDiscount(
        { total: data.data.discount_total, logs: data.data.logs },
        code
      )
      cart.voucherCode = code  // simpan kode yang sudah tervalidasi
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

// ── Checkout — tampilkan modal bayar dulu, BELUM buat order ──────
async function checkout() {
  if (cart.isEmpty) return
  pendingGrandTotal.value = cart.grandTotal
  payAmount.value         = 0
  showPayModal.value      = true
  await nextTick()
  payInput.value?.focus()
}

// ── Confirm Pay — baru buat order + bayar sekaligus ─────────────
async function confirmPay() {
  if (payAmount.value < pendingGrandTotal.value) return
  paying.value = true
  try {
    // Step 1: Buat order
    const { data: orderRes } = await orderApi.create(cart.toOrderPayload())
    const orderId    = orderRes.data.order_id
    const grandTotal = orderRes.data.grand_total

    // Step 2: Langsung bayar
    const { data: payRes } = await orderApi.pay(orderId, {
      method: cart.paymentMethod,
      amount: payAmount.value,
    })

    lastOrder.value = {
      order_number: orderRes.data.order_number,
      grand_total:  grandTotal,
      paid:         payAmount.value,
      change:       payRes.data.change,
    }
    showPayModal.value = false

    // PENTING: clear cart termasuk voucher — transaksi berikutnya mulai fresh
    cart.clear()

  } catch (e) {
    alert(e.response?.data?.message || 'Gagal memproses pembayaran')
  } finally {
    paying.value = false
  }
}

// ── Close Receipt — siap untuk order baru ───────────────────────
function closeReceipt() {
  lastOrder.value = null
  voucherError.value = ''
  productStore.loadAll()  // refresh stok
}
</script>
