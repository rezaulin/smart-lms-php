<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const students = ref([])
const loading = ref(true)

onMounted(async () => {
  await loadStudents()
})

async function loadStudents() {
  try {
    const response = await authStore.api.get('/students')
    if (response.data.success) {
      students.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load students:', error)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center gap-4">
        <button @click="goBack" class="text-gray-600">←</button>
        <h1 class="text-xl font-bold text-gray-900">Data Siswa</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else-if="students.length === 0" class="text-center py-8">
        <p class="text-gray-600">Belum ada data siswa</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="student in students" :key="student.id" class="card">
          <h3 class="font-semibold text-gray-900">{{ student.name }}</h3>
          <p class="text-sm text-gray-600">NIS: {{ student.nis }}</p>
          <p class="text-sm text-gray-600">Kelas: {{ student.class_name }}</p>
        </div>
      </div>
    </main>
  </div>
</template>
