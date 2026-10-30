import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'
import { ROUTE_PERMISSIONS, canOpenScreen, hasAccess } from '@/utils/screen-permissions'

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
    meta: { title: 'Trang chủ - PMS', permission: ROUTE_PERMISSIONS['/pms'] },
  },
  {
    path: '/reservation',
    name: 'Reservation',
    component: () => import('@/pages/reservation/RoomMapPage.vue'),
    meta: { title: 'Đặt phòng - PMS', permission: 'fo.booking.view', keepAlive: true },
  },
  {
    path: '/frontdesk',
    name: 'FrontDesk',
    component: () => import('@/pages/frontdesk/FrontDeskPage.vue'),
    meta: { title: 'Lễ tân - PMS', permission: 'fo.frontdesk.view', keepAlive: true },
  },
  {
    path: '/housekeeping',
    name: 'Housekeeping',
    component: () => import('@/pages/housekeeping/HousekeepingPage.vue'),
    meta: { title: 'Buồng phòng - PMS', permission: 'hk.view', keepAlive: true },
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
    meta: { title: 'Cấu hình hệ thống - PMS', permission: ROUTE_PERMISSIONS['/config'] },
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
    meta: { title: 'Trang chủ - F&B', permission: ROUTE_PERMISSIONS['/fnb'] },
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
    meta: { title: 'PARTY - F&B', permission: ROUTE_PERMISSIONS['/fnb/party'] }
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
    meta: { title: 'Khác - F&B', permission: ROUTE_PERMISSIONS['/fnb/other'] }
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

      // Kiểm tra permission của route (meta.permission) và của tab/màn hình con (dòng 406)
      const isTemporaryExpectedRevenueAccess = to.name === 'Reports'
        && to.query.report === 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT'
      if (!isTemporaryExpectedRevenueAccess) {
        if (!hasAccess(authStore, to.meta.permission) || !canOpenScreen(authStore, to.path, to.query)) {
          return { name: 'Forbidden' }
        }
      }

      document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
      return true
    }
  }
})

export default router
