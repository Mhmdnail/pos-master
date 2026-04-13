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
  res  => res,
  err  => {
    if (err.response?.status === 401) {
      localStorage.removeItem('pos_token')
      localStorage.removeItem('pos_user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

// ── API helpers ────────────────────────────────────────────────

export const authApi = {
  login:   data => api.post('/auth/login', data),
  logout:  ()   => api.post('/auth/logout'),
  me:      ()   => api.get('/auth/me'),
}

export const categoryApi = {
  list:    ()       => api.get('/categories'),
  create:  data     => api.post('/categories', data),
  update:  (id,d)   => api.put(`/categories/${id}`, d),
  remove:  id       => api.delete(`/categories/${id}`),
}

export const productApi = {
  list:    params   => api.get('/products', { params }),
  show:    id       => api.get(`/products/${id}`),
  stock:   id       => api.get(`/products/${id}/stock`),
  create:  data     => api.post('/products', data),
  update:  (id,d)   => api.put(`/products/${id}`, d),
  remove:  id       => api.delete(`/products/${id}`),
}

export const materialApi = {
  list:    ()       => api.get('/materials'),
  lowStock:()       => api.get('/materials/low-stock'),
  create:  data     => api.post('/materials', data),
  update:  (id,d)   => api.put(`/materials/${id}`, d),
  adjust:  (id,d)   => api.post(`/materials/${id}/adjust`, d),
}

export const orderApi = {
  list:    params   => api.get('/orders', { params }),
  show:    id       => api.get(`/orders/${id}`),
  create:  data     => api.post('/orders', data),
  pay:     (id,d)   => api.post(`/orders/${id}/payment`, d),
  cancel:  (id,d)   => api.post(`/orders/${id}/cancel`, d),
  receipt: id       => api.get(`/orders/${id}/receipt`),
  status:  (id,d)   => api.put(`/orders/${id}/status`, d),
}

export const discountApi = {
  list:     ()      => api.get('/discounts'),
  validate: data    => api.post('/discounts/validate', data),
  calculate:data    => api.post('/discounts/calculate', data),
}

export const customerApi = {
  list:    params   => api.get('/customers', { params }),
  show:    id       => api.get(`/customers/${id}`),
  create:  data     => api.post('/customers', data),
}

export const reportApi = {
  sales:      params => api.get('/reports/sales', { params }),
  products:   params => api.get('/reports/products', { params }),
  stock:      ()     => api.get('/reports/stock'),
  hpp:        params => api.get('/reports/hpp', { params }),
  cashflow:   params => api.get('/reports/cashflow', { params }),
  profitLoss: params => api.get('/reports/profit-loss', { params }),
  discounts:  params => api.get('/reports/discounts', { params }),
}
