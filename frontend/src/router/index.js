import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('@/pages/HomePage.vue'),
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
    component: () => import('@/pages/frontdesk/FrontDeskPage.vue'),
    meta: { title: 'Lễ tân - PMS' },
  },
  {
    path: '/housekeeping',
    name: 'Housekeeping',
    component: () => import('@/pages/housekeeping/HousekeepingPage.vue'),
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
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard - cập nhật title
router.beforeEach((to, from, next) => {
  document.title = to.meta.title || 'PMS - Hệ thống Quản lý Khách sạn'
  next()
})

export default router
