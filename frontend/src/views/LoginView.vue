<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  if (!email.value || !password.value) {
    error.value = 'Email dan password harus diisi'
    return
  }

  error.value = ''
  loading.value = true

  const result = await authStore.login({
    email: email.value,
    password: password.value
  })

  loading.value = false

  if (result.success) {
    router.push('/dashboard')
  } else {
    error.value = result.message || 'Login gagal'
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="card max-w-md w-full">
      <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Smart LMS</h1>
        <p class="text-gray-600 text-sm mt-2">Sistem Digital Sekolah</p>
      </div>

      <form @submit.prevent="handleLogin">
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Email / NIS / NIP
          </label>
          <input
            v-model="email"
            type="text"
            class="input"
            placeholder="Masukkan email, NIS, atau NIP"
            :disabled="loading"
          />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Password
          </label>
          <input
            v-model="password"
            type="password"
            class="input"
            placeholder="Masukkan password"
            :disabled="loading"
            @keyup.enter="handleLogin"
          />
        </div>

        <div v-if="error" class="mb-4 p-3 bg-danger text-white rounded text-sm">
          {{ error }}
        </div>

        <button
          type="submit"
          class="btn btn-primary w-full"
          :disabled="loading"
        >
          {{ loading ? 'Memproses...' : 'Login' }}
        </button>
      </form>

      <div class="mt-4 text-center text-sm text-gray-600">
        <p>Demo: admin@school.com / password</p>
      </div>
    </div>
  </div>
</template>
