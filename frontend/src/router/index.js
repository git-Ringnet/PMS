import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'
import { ROUTE_PERMISSIONS, canOpenScreen, hasAccess, hasApplication, requiredApplication } from '@/utils/screen-permissions'

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

// Sau khi deploy bản mới, file chunk cũ (hash cũ) không còn trên server nên import() của route lười bị lỗi,
// router.push thất bại âm thầm => bấm menu "không ăn", F5 thì chạy lại. Tự tải lại đúng trang đích (1 lần/đường dẫn).
const CHUNK_ERROR_RE = /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Unable to preload CSS/i
router.onError((error, to) => {
  const message = String(error?.message || error || '')
  if (!CHUNK_ERROR_RE.test(message)) return
  try {
    const key = `pms_chunk_reload:${to?.fullPath || ''}`
    if (sessionStorage.getItem(key)) return
    sessionStorage.setItem(key, '1')
  } catch (e) {
    // sessionStorage không dùng được: vẫn reload một lần
  }
  window.location.assign(to?.fullPath || window.location.href)
})

// Lỗi preload chunk do Vite phát ra (vd. modulepreload thất bại sau deploy)
window.addEventListener('vite:preloadError', (event) => {
  event.preventDefault()
  try {
    const key = `pms_chunk_reload:${window.location.pathname}`
    if (sessionStorage.getItem(key)) return
    sessionStorage.setItem(key, '1')
  } catch (e) {
    // ignore
  }
  window.location.reload()
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
        if (!hasApplication(authStore, requiredApplication(to.path)) ||
          !hasAccess(authStore, to.meta.permission) || !canOpenScreen(authStore, to.path, to.query)) {
          return { name: 'Forbidden' }
        }
      }

      document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
      return true
    }
  }
})

export default router
