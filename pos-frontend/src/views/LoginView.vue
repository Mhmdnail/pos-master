<template>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F3F4F6;">
  <div class="card" style="width:360px;">
    <div style="text-align:center;margin-bottom:24px;">
      <div style="font-size:32px;margin-bottom:8px;">☕</div>
      <h1 style="font-size:20px;font-weight:700;color:#1F2937;">POS Coffee Shop</h1>
      <p style="color:#6B7280;font-size:13px;margin-top:4px;">Masuk ke sistem kasir</p>
    </div>

    <form @submit.prevent="doLogin">
      <div class="form-group">
        <label class="form-label">Username</label>
        <input v-model="form.username" class="form-input" type="text" placeholder="Masukkan username" autofocus required />
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input v-model="form.password" class="form-input" type="password" placeholder="Masukkan password" required />
      </div>

      <div v-if="error" style="background:#FEE2E2;color:#991B1B;padding:10px 12px;border-radius:6px;font-size:13px;margin-bottom:14px;">
        {{ error }}
      </div>

      <button class="btn btn-primary w-full btn-lg" type="submit" :disabled="loading">
        <span v-if="loading" class="spinner"></span>
        <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
      </button>
    </form>
  </div>
</div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores'

const auth   = useAuthStore()
const router = useRouter()

const form    = ref({ username: '', password: '' })
const error   = ref('')
const loading = ref(false)

async function doLogin() {
  error.value   = ''
  loading.value = true
  try {
    await auth.login(form.value)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Login gagal, periksa username dan password'
  } finally {
    loading.value = false
  }
}
</script>
