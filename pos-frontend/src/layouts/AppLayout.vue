<template>
<div style="display:flex;min-height:100vh;">
  <!-- Sidebar -->
  <aside style="width:200px;background:#1F2937;display:flex;flex-direction:column;flex-shrink:0;">
    <div style="padding:20px 16px;border-bottom:1px solid #374151;">
      <div style="color:#fff;font-weight:700;font-size:16px;">POS Coffee</div>
      <div style="color:#9CA3AF;font-size:11px;margin-top:2px;">{{ user?.name }}</div>
    </div>

    <nav style="flex:1;padding:12px 8px;">
      <router-link v-for="m in menu" :key="m.to" :to="m.to" class="nav-link" active-class="nav-link-active">
        <span>{{ m.icon }}</span> {{ m.label }}
      </router-link>
    </nav>

    <div style="padding:12px 8px;border-top:1px solid #374151;">
      <button class="nav-link" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;color:#9CA3AF;" @click="logout">
        &#x2190; Logout
      </button>
    </div>
  </aside>

  <!-- Main -->
  <main style="flex:1;overflow:auto;">
    <slot />
  </main>
</div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores'

const auth   = useAuthStore()
const router = useRouter()
const user   = computed(() => auth.user)

const menu = [
  { to:'/',          icon:'⬛', label:'Kasir'    },
  { to:'/orders',    icon:'📋', label:'Riwayat'  },
  { to:'/products',  icon:'☕', label:'Produk'   },
  { to:'/materials', icon:'🌾', label:'Bahan'    },
  { to:'/discounts', icon:'%',  label:'Diskon'   },
  { to:'/reports',   icon:'📊', label:'Laporan'  },
]

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.nav-link {
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border-radius:6px;
  color:#9CA3AF; text-decoration:none;
  font-size:13px; font-weight:500;
  transition:all .15s; margin-bottom:2px;
}
.nav-link:hover, .nav-link-active {
  background:#374151; color:#fff;
}
</style>
