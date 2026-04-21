// =============================================================================
// TAMBAHKAN ke pos-frontend/src/services/api.js
// Tambahkan di bagian bawah file, setelah export reportApi
// =============================================================================

export const shiftApi = {
  list:     params  => api.get('/shifts', { params }),
  active:   ()      => api.get('/shifts/active'),
  show:     id      => api.get(`/shifts/${id}`),
  open:     data    => api.post('/shifts/open', data),
  close:    (id, d) => api.post(`/shifts/${id}/close`, d),
  zreport:  id      => api.get(`/shifts/${id}/zreport`),
}

export const userApi = {
  list:    params   => api.get('/users', { params }),
  show:    id       => api.get(`/users/${id}`),
  create:  data     => api.post('/users', data),
  update:  (id, d)  => api.put(`/users/${id}`, d),
  toggle:  id       => api.patch(`/users/${id}/toggle`),
  roles:   ()       => api.get('/roles'),
}
