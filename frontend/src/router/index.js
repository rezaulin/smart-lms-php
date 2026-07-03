import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login'
    },
    {
      path: '/login',
      name: 'Login',
      component: () => import('../views/LoginView.vue'),
      meta: { requiresGuest: true }
    },
    {
      path: '/dashboard',
      name: 'Dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/attendance',
      name: 'Attendance',
      component: () => import('../views/AttendanceView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/attendance/scan',
      name: 'AttendanceScan',
      component: () => import('../views/AttendanceScanView.vue'),
      meta: { requiresAuth: true, role: 'siswa' }
    },
    {
      path: '/exams',
      name: 'Exams',
      component: () => import('../views/ExamsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/exams/:id',
      name: 'ExamDetail',
      component: () => import('../views/ExamDetailView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/billing',
      name: 'Billing',
      component: () => import('../views/BillingView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/raport',
      name: 'Raport',
      component: () => import('../views/RaportView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/students',
      name: 'Students',
      component: () => import('../views/StudentsView.vue'),
      meta: { requiresAuth: true, role: ['admin_pusat', 'admin_cabang', 'guru'] }
    },
    {
      path: '/profile',
      name: 'Profile',
      component: () => import('../views/ProfileView.vue'),
      meta: { requiresAuth: true }
    }
  ]
})

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else if (to.meta.requiresGuest && isAuthenticated) {
    next('/dashboard')
  } else if (to.meta.role && isAuthenticated) {
    const userRole = authStore.user?.role
    const allowedRoles = Array.isArray(to.meta.role) ? to.meta.role : [to.meta.role]
    if (!allowedRoles.includes(userRole)) {
      next('/dashboard')
    } else {
      next()
    }
  } else {
    next()
  }
})

export default router
