import { defineStore } from 'pinia'
import { authApi } from '@/services/api'

// ── AUTH STORE ──────────────────────────────────────────────────
export const useAuthStore = defineStore('auth', {
  state: () => ({
    token:   localStorage.getItem('pos_token') || null,
    user:    JSON.parse(localStorage.getItem('pos_user') || 'null'),
    loading: false,
  }),
  getters: {
    isLoggedIn: s => !!s.token,
    isOwner:    s => s.user?.role === 'owner',
    isManager:  s => ['owner','manager'].includes(s.user?.role),
  },
  actions: {
    async login(credentials) {
      this.loading = true
      try {
        const { data } = await authApi.login(credentials)
        this.token = data.data.token
        this.user  = data.data.user
        localStorage.setItem('pos_token', this.token)
        localStorage.setItem('pos_user',  JSON.stringify(this.user))
        return data
      } finally {
        this.loading = false
      }
    },
    async logout() {
      try { await authApi.logout() } catch {}
      this.token = null
      this.user  = null
      localStorage.removeItem('pos_token')
      localStorage.removeItem('pos_user')
    },
    can(permission) {
      const perms = this.user?.permissions || []
      if (perms.includes('*')) return true
      if (perms.includes(permission)) return true
      const prefix = permission.split('.')[0]
      return perms.includes(`${prefix}.*`)
    }
  }
})

// ── HELPER ──────────────────────────────────────────────────────
function extractList(responseData) {
  const d = responseData?.data
  if (!d) return []
  if (Array.isArray(d)) return d
  if (Array.isArray(d.items)) return d.items
  if (Array.isArray(d.data)) return d.data
  return []
}

// ── CART STORE ──────────────────────────────────────────────────
export const useCartStore = defineStore('cart', {
  state: () => ({
    items:          [],
    customer:       null,
    orderType:      'dine_in',
    tableNumber:    '',
    paymentMethod:  'cash',
    // Voucher state — HARUS di-reset setelah setiap transaksi selesai
    voucherCode:    '',
    voucherApplied: null,   // { code, discount_name, amount } — info voucher yang sudah valid
    discount:       { total: 0, logs: [] },
  }),
  getters: {
    subtotal:        s => s.items.reduce((sum, i) => sum + i.unit_price * i.qty, 0),
    grandTotal:      s => Math.max(0, s.subtotal - s.discount.total),
    itemCount:       s => s.items.reduce((sum, i) => sum + i.qty, 0),
    isEmpty:         s => s.items.length === 0,
    hasVoucher:      s => !!s.voucherApplied,
  },
  actions: {
    addItem(product, qty = 1, modifiers = []) {
      const key      = product.id + JSON.stringify(modifiers)
      const existing = this.items.find(i => i._key === key)
      if (existing) {
        existing.qty += qty
      } else {
        const modDelta = modifiers.reduce((s, m) => s + (m.price_delta || 0), 0)
        this.items.push({
          _key:       key,
          product_id: product.id,
          name:       product.name,
          qty,
          unit_price: parseFloat(product.base_price) + modDelta,
          unit_hpp:   0,
          modifiers,
        })
      }
    },
    removeItem(key) {
      this.items = this.items.filter(i => i._key !== key)
    },
    updateQty(key, qty) {
      if (qty <= 0) { this.removeItem(key); return }
      const item = this.items.find(i => i._key === key)
      if (item) item.qty = qty
    },

    // Set hasil kalkulasi diskon
    setDiscount(result, voucherCode = '') {
      this.discount = result
      // Simpan info voucher yang sudah di-apply untuk ditampilkan
      if (voucherCode && result.logs?.length) {
        this.voucherApplied = {
          code:   voucherCode,
          logs:   result.logs,
          total:  result.discount_total,
        }
      }
    },

    // Clear HANYA bagian voucher/diskon (tanpa clear item)
    clearDiscount() {
      this.discount       = { total: 0, logs: [] }
      this.voucherCode    = ''
      this.voucherApplied = null
    },

    // Clear SEMUA setelah transaksi selesai — termasuk voucher
    clear() {
      this.items          = []
      this.customer       = null
      this.tableNumber    = ''
      this.discount       = { total: 0, logs: [] }
      // PENTING: voucher wajib di-clear setelah transaksi selesai
      // supaya transaksi berikutnya tidak otomatis dapat diskon yang sama
      this.voucherCode    = ''
      this.voucherApplied = null
      // paymentMethod dan orderType sengaja TIDAK di-reset
      // supaya kasir tidak perlu re-set pilihan setiap order
    },

    toOrderPayload() {
      return {
        customer_id:    this.customer?.id || null,
        order_type:     this.orderType,
        table_number:   this.tableNumber || null,
        // Kirim voucher code hanya jika sudah di-apply dan tervalidasi
        voucher_code:   this.voucherApplied?.code || null,
        payment_method: this.paymentMethod,
        notes:          null,
        items: this.items.map(i => ({
          product_id: i.product_id,
          qty:        i.qty,
          unit_price: i.unit_price,
          modifiers:  i.modifiers,
        })),
      }
    }
  }
})

// ── PRODUCT STORE ───────────────────────────────────────────────
export const useProductStore = defineStore('product', {
  state: () => ({
    categories: [],
    products:   [],
    loading:    false,
    error:      null,
  }),
  actions: {
    async loadAll() {
      this.loading = true
      this.error   = null
      try {
        const { categoryApi, productApi } = await import('@/services/api')
        const [catRes, prodRes] = await Promise.all([
          categoryApi.list(),
          productApi.list({ per_page: 200 }),
        ])
        this.categories = extractList(catRes.data)
        this.products   = extractList(prodRes.data)
      } catch (e) {
        this.error = e.response?.data?.message || e.message || 'Gagal memuat produk'
        console.error('ProductStore error:', e)
      } finally {
        this.loading = false
      }
    },
    byCategory(catId) {
      return this.products.filter(p => p.category_id === catId)
    }
  }
})
