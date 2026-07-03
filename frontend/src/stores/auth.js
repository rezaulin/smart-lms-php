import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token') || null)
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  const api = axios.create({
    baseURL: '/api',
    headers: {
      'Content-Type': 'application/json'
    }
  })

  // Add token to requests
  api.interceptors.request.use(config => {
    if (token.value) {
      config.headers.Authorization = `Bearer ${token.value}`
    }
    return config
  })

  // Handle 401 responses
  api.interceptors.response.use(
    response => response,
    error => {
      if (error.response?.status === 401) {
        logout()
      }
      return Promise.reject(error)
    }
  )

  async function login(credentials) {
    try {
      const response = await api.post('/auth/login', credentials)
      if (response.data.success && response.data.data.token) {
        token.value = response.data.data.token
        user.value = response.data.data.user
        localStorage.setItem('token', token.value)
        return { success: true }
      }
      return { success: false, message: response.data.message }
    } catch (error) {
      return { 
        success: false, 
        message: error.response?.data?.message || 'Login failed' 
      }
    }
  }

  async function checkAuth() {
    try {
      const response = await api.get('/auth/profile')
      if (response.data.success) {
        user.value = response.data.data
        return true
      }
      logout()
      return false
    } catch (error) {
      logout()
      return false
    }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
  }

  return {
    token,
    user,
    isAuthenticated,
    api,
    login,
    checkAuth,
    logout
  }
})
