<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const exam = ref(null)
const loading = ref(true)

onMounted(async () => {
  await loadExam()
})

async function loadExam() {
  try {
    const response = await authStore.api.get(`/exams/${route.params.id}`)
    if (response.data.success) {
      exam.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load exam:', error)
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/exams')
}

function startExam() {
  alert('Exam start functionality - coming soon')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-4 flex items-center gap-4">
        <button @click="goBack" class="text-gray-600">←</button>
        <h1 class="text-xl font-bold text-gray-900">Detail Ujian</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else-if="!exam" class="text-center py-8">
        <p class="text-gray-600">Ujian tidak ditemukan</p>
      </div>

      <div v-else class="card">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ exam.title }}</h2>
        <div class="space-y-2 mb-6">
          <p class="text-gray-600">Mata Pelajaran: {{ exam.subject_name }}</p>
          <p class="text-gray-600">Durasi: {{ exam.duration }} menit</p>
          <p class="text-gray-600">Jumlah Soal: {{ exam.question_count }}</p>
        </div>
        <button @click="startExam" class="btn btn-primary w-full">
          Mulai Ujian
        </button>
      </div>
    </main>
  </div>
</template>
