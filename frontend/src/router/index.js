import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { title: 'Đăng nhập - PMS', noLayout: true, guest: true },
  },
  {
    path: '/',
    name: 'Home',
    component: () => import('@/pages/HomePage.vue'),
    meta: { title: 'Trang chủ - Provista', noLayout: true },
  },
  {
    path: '/pms',
    name: 'PmsPages',
    component: () => import('@/pages/PmsPages.vue'),
    meta: { title: 'Trang chủ - PMS' },
  },
  {
    path: '/reservation',
    name: 'Reservation',
    component: () => import('@/pages/reservation/RoomMapPage.vue'),
    meta: { title: 'Đặt phòng - PMS' },
  },
  {
    path: '/frontdesk',
    name: 'FrontDesk',
    component: () => import('@/pages/reservation/RoomMapPage.vue'),
    meta: { title: 'Lễ tân - PMS' },
  },
  {
    path: '/housekeeping',
    name: 'Housekeeping',
    component: () => import('@/pages/reservation/RoomMapPage.vue'),
    meta: { title: 'Buồng phòng - PMS' },
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('@/pages/reports/ReportsPage.vue'),
    meta: { title: 'Báo cáo quản lý - PMS' },
  },
  {
    path: '/config',
    name: 'Config',
    component: () => import('@/pages/config/ConfigPage.vue'),
    meta: { title: 'Cấu hình hệ thống - PMS' },
  },
  {
    path: '/system',
    name: 'SystemConfig',
    component: () => import('@/pages/system/SystemPage.vue'),
    meta: { title: 'Cấu hình hệ thống - Provista', noLayout: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach(async (to, from) => {
  const token = localStorage.getItem('pms_token')

  if (to.meta.guest) {
    if (token) {
      return '/'
    } else {
      document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
      return true
    }
  } else {
    if (!token) {
      return '/login'
    } else {
      const authStore = useAuthStore()
      if (!authStore.user) {
        try {
          await authStore.initialize()
        } catch (err) {
          return '/login'
        }
      }
      document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
      return true
    }
  }
})

export default router

