<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const bills = ref([])
const loading = ref(true)

onMounted(async () => {
  await loadBills()
})

async function loadBills() {
  try {
    const response = await authStore.api.get('/billing/tagihan')
    if (response.data.success) {
      bills.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to load bills:', error)
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
        <h1 class="text-xl font-bold text-gray-900">Tagihan & Pembayaran</h1>
      </div>
    </header>

    <main class="max-w-7xl mx-auto p-4">
      <div v-if="loading" class="text-center py-8">
        <p class="text-gray-600">Memuat data...</p>
      </div>

      <div v-else-if="bills.length === 0" class="text-center py-8">
        <p class="text-gray-600">Belum ada tagihan</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="bill in bills" :key="bill.id" class="card">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="font-semibold text-gray-900">{{ bill.jenis_tagihan_name }}</h3>
              <p class="text-sm text-gray-600">{{ bill.bulan }} {{ bill.tahun }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-gray-900">Rp {{ bill.jumlah?.toLocaleString() }}</p>
              <span :class="[
                'text-xs px-2 py-1 rounded',
                bill.status === 'lunas' ? 'bg-success text-white' : 'bg-warning text-white'
              ]">
                {{ bill.status }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
