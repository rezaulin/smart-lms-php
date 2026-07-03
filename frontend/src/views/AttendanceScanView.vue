<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { Html5Qrcode } from 'html5-qrcode'

const router = useRouter()
const authStore = useAuthStore()

const scanner = ref(null)
const scanning = ref(false)
const result = ref(null)
const error = ref('')
const cameraId = ref(null)

let html5QrCode = null

onMounted(async () => {
  try {
    // Get user location for GPS validation
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        position => {
          console.log('GPS:', position.coords.latitude, position.coords.longitude)
        },
        err => console.error('GPS error:', err)
      )
    }

    // Initialize QR scanner
    html5QrCode = new Html5Qrcode('qr-reader')
    
    // Get cameras
    const devices = await Html5Qrcode.getCameras()
    if (devices && devices.length) {
      cameraId.value = devices[0].id
      startScanning()
    } else {
      error.value = 'Kamera tidak ditemukan'
    }
  } catch (err) {
    error.value = 'Gagal mengakses kamera: ' + err.message
  }
})

onUnmounted(() => {
  stopScanning()
})

async function startScanning() {
  if (!html5QrCode || scanning.value) return

  scanning.value = true
  error.value = ''

  try {
    await html5QrCode.start(
      cameraId.value,
      { fps: 10, qrbox: { width: 250, height: 250 } },
      onScanSuccess,
      onScanError
    )
  } catch (err) {
    error.value = 'Gagal memulai scanner: ' + err.message
    scanning.value = false
  }
}

function stopScanning() {
  if (html5QrCode && scanning.value) {
    html5QrCode.stop()
      .then(() => {
        scanning.value = false
      })
      .catch(err => console.error('Stop error:', err))
  }
}

async function onScanSuccess(decodedText) {
  stopScanning()

  // Get GPS coordinates
  navigator.geolocation.getCurrentPosition(
    async position => {
      await submitAttendance(decodedText, {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracy: position.coords.accuracy
      })
    },
    async err => {
      console.error('GPS error:', err)
      await submitAttendance(decodedText, null)
    }
  )
}

function onScanError(err) {
  // Ignore scan errors (happens frequently during scanning)
}

async function submitAttendance(qrToken, gps) {
  try {
    const payload = { qr_token: qrToken }
    if (gps) {
      payload.lat = gps.lat
      payload.lng = gps.lng
      payload.accuracy = gps.accuracy
    }

    const response = await authStore.api.post('/attendance/checkin', payload)
    
    if (response.data.success) {
      result.value = {
        success: true,
        message: response.data.message || 'Absensi berhasil dicatat'
      }
    } else {
      result.value = {
        success: false,
        message: response.data.message || 'Absensi gagal'
      }
    }
  } catch (err) {
    result.value = {
      success: false,
      message: err.response?.data?.message || 'Gagal mencatat absensi'
    }
  }

  // Auto close result after 3 seconds
  setTimeout(() => {
    result.value = null
    startScanning()
  }, 3000)
}

function goBack() {
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-gray-900">
    <!-- Header -->
    <header class="bg-gray-800 p-4 flex justify-between items-center">
      <button @click="goBack" class="text-white">
        ← Kembali
      </button>
      <h1 class="text-white font-bold">Scan QR Absensi</h1>
      <div class="w-16"></div>
    </header>

    <!-- Scanner -->
    <div class="flex flex-col items-center justify-center p-4">
      <div id="qr-reader" class="w-full max-w-md mb-4"></div>

      <!-- Error -->
      <div v-if="error" class="card bg-danger text-white max-w-md w-full mb-4">
        <p class="text-center">{{ error }}</p>
      </div>

      <!-- Result -->
      <div v-if="result" :class="[
        'card max-w-md w-full mb-4',
        result.success ? 'bg-success text-white' : 'bg-danger text-white'
      ]">
        <p class="text-center font-semibold">{{ result.message }}</p>
      </div>

      <!-- Instructions -->
      <div v-if="!result && !error" class="card max-w-md w-full">
        <h3 class="font-semibold text-gray-900 mb-2">Cara Absen:</h3>
        <ol class="text-sm text-gray-600 space-y-1">
          <li>1. Arahkan kamera ke QR code</li>
          <li>2. Pastikan QR code terlihat jelas</li>
          <li>3. Tunggu hingga terdeteksi otomatis</li>
        </ol>
      </div>
    </div>
  </div>
</template>
