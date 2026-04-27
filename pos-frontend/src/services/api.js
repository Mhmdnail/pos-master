import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
})

// Request: inject token
api.interceptors.request.use(cfg => {
  const token = localStorage.getItem('pos_token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  return cfg
})

// Response: handle 401 global
api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      localStorage.removeItem('pos_token')
      localStorage.removeItem('pos_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

// ── Auth ───────────────────────────────────────────────────────
export const authApi = {
  login:   data => api.post('/auth/login', data),
  logout:  ()   => api.post('/auth/logout'),
  me:      ()   => api.get('/auth/me'),
}

// ── Kategori ───────────────────────────────────────────────────
export const categoryApi = {
  list:   ()        => api.get('/categories'),
  create: data      => api.post('/categories', data),
  update: (id, d)   => api.put(`/categories/${id}`, d),
  remove: id        => api.delete(`/categories/${id}`),
}

// ── Produk ─────────────────────────────────────────────────────
export const productApi = {
  list:   params    => api.get('/products', { params }),
  show:   id        => api.get(`/products/${id}`),
  stock:  id        => api.get(`/products/${id}/stock`),
  create: data      => api.post('/products', data),
  update: (id, d)   => api.put(`/products/${id}`, d),
  remove: id        => api.delete(`/products/${id}`),
}

// ── Bahan Baku ─────────────────────────────────────────────────
export const materialApi = {
  list:     ()      => api.get('/materials'),
  lowStock: ()      => api.get('/materials/low-stock'),
  create:   data    => api.post('/materials', data),
  update:   (id, d) => api.put(`/materials/${id}`, d),
  adjust:   (id, d) => api.post(`/materials/${id}/adjust`, d),
}

// ── Order ──────────────────────────────────────────────────────
export const orderApi = {
  list:    params   => api.get('/orders', { params }),
  show:    id       => api.get(`/orders/${id}`),
  create:  data     => api.post('/orders', data),
  pay:     (id, d)  => api.post(`/orders/${id}/payment`, d),
  cancel:  (id, d)  => api.post(`/orders/${id}/cancel`, d),
  receipt: id       => api.get(`/orders/${id}/receipt`),
  status:  (id, d)  => api.put(`/orders/${id}/status`, d),
}

// ── Diskon ─────────────────────────────────────────────────────
export const discountApi = {
  list:      ()     => api.get('/discounts'),
  validate:  data   => api.post('/discounts/validate', data),
  calculate: data   => api.post('/discounts/calculate', data),
}

// ── Pelanggan ──────────────────────────────────────────────────
export const customerApi = {
  list:   params    => api.get('/customers', { params }),
  show:   id        => api.get(`/customers/${id}`),
  create: data      => api.post('/customers', data),
}

// ── Laporan ────────────────────────────────────────────────────
export const reportApi = {
  sales:      params => api.get('/reports/sales',       { params }),
  products:   params => api.get('/reports/products',    { params }),
  stock:      ()     => api.get('/reports/stock'),
  hpp:        params => api.get('/reports/hpp',         { params }),
  cashflow:   params => api.get('/reports/cashflow',    { params }),
  profitLoss: params => api.get('/reports/profit-loss', { params }),
  discounts:  params => api.get('/reports/discounts',   { params }),
}

// ── Shift Kasir ────────────────────────────────────────────────
export const shiftApi = {
  list:    params   => api.get('/shifts', { params }),
  active:  ()       => api.get('/shifts/active'),
  show:    id       => api.get(`/shifts/${id}`),
  open:    data     => api.post('/shifts/open', data),
  close:   (id, d)  => api.post(`/shifts/${id}/close`, d),
  zreport: id       => api.get(`/shifts/${id}/zreport`),
}

// ── User Management ────────────────────────────────────────────
export const userApi = {
  list:   params    => api.get('/users', { params }),
  show:   id        => api.get(`/users/${id}`),
  create: data      => api.post('/users', data),
  update: (id, d)   => api.put(`/users/${id}`, d),
  toggle: id        => api.patch(`/users/${id}/toggle`),
  roles:  ()        => api.get('/roles'),
}

// ── Kas Kecil Management ────────────────────────────────────────────
export const kasKecilApi = {
  list:       params => api.get('/kas-kecil', { params }),
  summary:    params => api.get('/kas-kecil/summary', { params }),
  categories: ()     => api.get('/kas-kecil/categories'),
  create:     data   => api.post('/kas-kecil', data),
  delete:     id     => api.delete(`/kas-kecil/${id}`),
}

// ── Supplier ────────────────────────────────────────────
export const supplierApi = {
  list:   params   => api.get('/suppliers', { params }),
  show:   id       => api.get(`/suppliers/${id}`),
  create: data     => api.post('/suppliers', data),
  update: (id, d)  => api.put(`/suppliers/${id}`, d),
  toggle: id       => api.patch(`/suppliers/${id}/toggle`),
}

// ── PO ────────────────────────────────────────────
export const poApi = {
  list:    params   => api.get('/purchase-orders', { params }),
  show:    id       => api.get(`/purchase-orders/${id}`),
  create:  data     => api.post('/purchase-orders', data),
  update:  (id, d)  => api.put(`/purchase-orders/${id}`, d),
  receive: (id, d)  => api.post(`/purchase-orders/${id}/receive`, d),
  cancel:  id       => api.post(`/purchase-orders/${id}/cancel`),
}