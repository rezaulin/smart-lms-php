<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const stats = ref({
  students: 0,
  teachers: 0,
  classes: 0,
  attendance_today: 0
})

const loading = ref(true)

onMounted(async () => {
  try {
    const response = await authStore.api.get('/dashboard')
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    loading.value = false
  }
})

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

function navigateTo(path) {
  router.push(path)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-gray-900">Smart LMS</h1>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">
            {{ authStore.user?.name }}
          </span>
          <button @click="handleLogout" class="btn btn-primary">
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto p-4">
      <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Dashboard</h2>
        <p class="text-gray-600">
          Selamat datang, {{ authStore.user?.name }}
        </p>
      </div>

      <!-- Stats Grid -->
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else class="grid grid-cols-2 gap-4 mb-6">
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">Siswa</h3>
          <p class="text-2xl font-bold text-gray-900">{{ stats.students }}</p>
        </div>
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">Guru</h3>
          <p class="text-2xl font-bold text-gray-900">{{ stats.teachers }}</p>
        </div>
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">Kelas</h3>
          <p class="text-2xl font-bold text-gray-900">{{ stats.classes }}</p>
        </div>
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">Absensi Hari Ini</h3>
          <p class="text-2xl font-bold text-gray-900">{{ stats.attendance_today }}</p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="mb-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Menu</h3>
        <div class="grid grid-cols-2 gap-4">
          <button @click="navigateTo('/attendance')" class="card text-left">
            <div class="text-primary text-2xl mb-2">📝</div>
            <h4 class="font-semibold text-gray-900">Absensi</h4>
            <p class="text-sm text-gray-600">Kelola absensi siswa</p>
          </button>
          
          <button v-if="authStore.user?.role === 'siswa'" @click="navigateTo('/attendance/scan')" class="card text-left">
            <div class="text-primary text-2xl mb-2">📷</div>
            <h4 class="font-semibold text-gray-900">Scan QR</h4>
            <p class="text-sm text-gray-600">Absen dengan QR code</p>
          </button>

          <button @click="navigateTo('/exams')" class="card text-left">
            <div class="text-primary text-2xl mb-2">📚</div>
            <h4 class="font-semibold text-gray-900">Ujian</h4>
            <p class="text-sm text-gray-600">Kelola ujian online</p>
          </button>

          <button @click="navigateTo('/billing')" class="card text-left">
            <div class="text-primary text-2xl mb-2">💰</div>
            <h4 class="font-semibold text-gray-900">Tagihan</h4>
            <p class="text-sm text-gray-600">SPP dan pembayaran</p>
          </button>

          <button @click="navigateTo('/raport')" class="card text-left">
            <div class="text-primary text-2xl mb-2">📊</div>
            <h4 class="font-semibold text-gray-900">Raport</h4>
            <p class="text-sm text-gray-600">Nilai dan raport siswa</p>
          </button>

          <button 
            v-if="['admin_pusat', 'admin_cabang', 'guru'].includes(authStore.user?.role)"
            @click="navigateTo('/students')" 
            class="card text-left"
          >
            <div class="text-primary text-2xl mb-2">👥</div>
            <h4 class="font-semibold text-gray-900">Data Siswa</h4>
            <p class="text-sm text-gray-600">Kelola data siswa</p>
          </button>
        </div>
      </div>
    </main>
  </div>
</template>
