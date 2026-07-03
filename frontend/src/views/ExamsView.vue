<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const exams = ref([])
const loading = ref(true)

onMounted(async () => {
  await loadExams()
})

async function loadExams() {
  try {
    const response = await authStore.api.get('/exams')
    if (response.data.success) {
      exams.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load exams:', error)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/dashboard')
}

function viewExam(id) {
  router.push(`/exams/${id}`)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center gap-4">
        <button @click="goBack" class="text-gray-600">←</button>
        <h1 class="text-xl font-bold text-gray-900">Ujian</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else-if="exams.length === 0" class="text-center py-8">
        <p class="text-gray-600">Belum ada ujian</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="exam in exams" :key="exam.id" class="card" @click="viewExam(exam.id)">
          <h3 class="font-semibold text-gray-900">{{ exam.title }}</h3>
          <p class="text-sm text-gray-600">{{ exam.subject_name }}</p>
          <p class="text-sm text-gray-600">Durasi: {{ exam.duration }} menit</p>
          <button class="btn btn-primary mt-2">Lihat Detail</button>
        </div>
      </div>
    </main>
  </div>
</template>
