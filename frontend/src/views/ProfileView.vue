<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

function goBack() {
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center gap-4">
        <button @click="goBack" class="text-gray-600">←</button>
        <h1 class="text-xl font-bold text-gray-900">Profil</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div class="card">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Informasi Akun</h2>
        <div class="space-y-2">
          <div>
            <p class="text-sm text-gray-600">Nama:</p>
            <p class="font-semibold text-gray-900">{{ authStore.user?.name }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Email:</p>
            <p class="font-semibold text-gray-900">{{ authStore.user?.email }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Role:</p>
            <p class="font-semibold text-gray-900">{{ authStore.user?.role }}</p>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
