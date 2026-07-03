<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const raports = ref([])
const loading = ref(true)

onMounted(async () => {
  await loadRaports()
})

async function loadRaports() {
  try {
    const response = await authStore.api.get('/raports')
    if (response.data.success) {
      raports.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load raports:', error)
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
        <h1 class="text-xl font-bold text-gray-900">Raport</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else-if="raports.length === 0" class="text-center py-8">
        <p class="text-gray-600">Belum ada raport</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="raport in raports" :key="raport.id" class="card">
          <h3 class="font-semibold text-gray-900">{{ raport.semester_name }}</h3>
          <p class="text-sm text-gray-600">Kelas: {{ raport.class_name }}</p>
          <p class="text-sm text-gray-600">Status: {{ raport.status }}</p>
        </div>
      </div>
    </main>
  </div>
</template>
