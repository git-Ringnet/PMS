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
    meta: { title: 'Đặt phòng - PMS', permission: 'fo.booking.view' },
  },
  {
    path: '/frontdesk',
    name: 'FrontDesk',
    component: () => import('@/pages/frontdesk/FrontDeskPage.vue'),
    meta: { title: 'Lễ tân - PMS', permission: 'fo.frontdesk.view' },
  },
  {
    path: '/housekeeping',
    name: 'Housekeeping',
    component: () => import('@/pages/housekeeping/HousekeepingPage.vue'),
    meta: { title: 'Buồng phòng - PMS', permission: 'hk.view' },
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('@/pages/reports/ReportsPage.vue'),
    meta: { title: 'Báo cáo quản lý - PMS', permission: 'mgmt.report.view' },
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
    meta: { title: 'Cấu hình hệ thống - Provista', noLayout: true, permission: 'system.user.view' },
  },
  {
    path: '/fnb',
    name: 'FnbPages',
    component: () => import('@/pages/FnbPages.vue'),
    meta: { title: 'Trang chủ - F&B' },
  },
  {
    path: '/fnb/restaurant',
    name: 'FnbRestaurant',
    component: () => import('@/pages/fnb/RestaurantPage.vue'),
    meta: { title: 'Nhà Hàng - F&B', permission: 'fb.view' }
  },
  {
    path: '/fnb/party',
    name: 'party',
    component: () => import('@/pages/fnb/PartyPage.vue'),
    meta: { title: 'PARTY - F&B', permission: 'fb.party.manage' }
  },
  {
    path: '/fnb/search',
    name: 'search',
    component: () => import('@/pages/fnb/SearchPage.vue'),
    meta: { title: 'Tìm kiếm đơn hàng - F&B', permission: 'fb.order.view' }
  },
  {
    path: '/fnb/other',
    name: 'fnb-other',
    component: () => import('@/pages/fnb/OtherPages.vue'),
    meta: { title: 'Khác - F&B' }
  },
  {
    path: '/fnb/report',
    name: 'fnb-report',
    component: () => import('@/pages/fnb/ReportPage.vue'),
    meta: { title: 'Báo cáo - F&B', permission: 'mgmt.report.view' }
  },
  {
    // Trang 403 - không có quyền
    path: '/forbidden',
    name: 'Forbidden',
    component: () => import('@/pages/ForbiddenPage.vue'),
    meta: { title: 'Không có quyền truy cập', noLayout: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach(async (to, from) => {
  const token = localStorage.getItem('pms_token') || sessionStorage.getItem('pms_token')

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

      // Kiểm tra permission nếu route có meta.permission
      if (to.meta.permission) {
        const isSuperAdmin = authStore.roles.some(r => r.role_code === 'super_admin')
        const hasAccess = isSuperAdmin || authStore.permissions.includes(to.meta.permission)
        if (!hasAccess) {
          return { name: 'Forbidden' }
        }
      }

      document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
      return true
    }
  }
})

export default router
