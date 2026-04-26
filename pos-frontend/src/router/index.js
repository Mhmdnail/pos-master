import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores'

const routes = [
  { path: '/login',    name: 'Login',    component: () => import('@/views/LoginView.vue'),    meta: { guest: true  } },
  { path: '/',         name: 'Kasir',    component: () => import('@/views/KasirView.vue'),    meta: { auth: true   } },
  { path: '/products', name: 'Products', component: () => import('@/views/ProductView.vue'),  meta: { auth: true   } },
  { path: '/materials',name: 'Materials',component: () => import('@/views/MaterialView.vue'), meta: { auth: true   } },
  { path: '/orders',   name: 'Orders',   component: () => import('@/views/OrderView.vue'),    meta: { auth: true   } },
  { path: '/reports',  name: 'Reports',  component: () => import('@/views/ReportView.vue'),   meta: { auth: true   } },
  { path: '/discounts',name: 'Discounts',component: () => import('@/views/DiscountView.vue'), meta: { auth: true   } },
  { path: '/shifts', name: 'Shifts', component: () => import('@/views/ShiftView.vue'), meta: { auth: true } },
  { path: '/users',  name: 'Users',  component: () => import('@/views/UserView.vue'),  meta: { auth: true } },
  { path: '/:pathMatch(.*)*', redirect: '/' },
  { path: '/kas-kecil', name: 'KasKecil', component: () => import('@/views/KasKecilView.vue'), meta: { auth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(to => {
  const auth = useAuthStore()
  if (to.meta.auth  && !auth.isLoggedIn) return '/login'
  if (to.meta.guest && auth.isLoggedIn)  return '/'
})

export default router
