<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'

const route = useRoute()
const router = useRouter()
const sidebarCollapsed = ref(false)
const currentDate = ref(new Date())

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)
const isDropdownOpen = ref(false)
const dropdownRef = ref(null)
const isDark = ref(false)

async function handleLogout() {
  if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
    isDropdownOpen.value = false
    await authStore.logout()
    router.push('/login')
  }
}

function closeDropdown(e) {
  if (isDropdownOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isDropdownOpen.value = false
  }
}

function toggleDarkMode() {
  isDark.value = !isDark.value
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

onMounted(() => {
  window.addEventListener('click', closeDropdown)
  
  const savedTheme = localStorage.getItem('theme')
  if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true
    document.documentElement.classList.add('dark')
  } else {
    isDark.value = false
    document.documentElement.classList.remove('dark')
  }
})

onUnmounted(() => {
  window.removeEventListener('click', closeDropdown)
})

const menuItems = computed(() => {
  if (route.path.startsWith('/frontdesk')) {
    //trang lễ tân frontdesk
    return [
      { name: 'Giao phòng', route: '/frontdesk' },
      { name: 'Kiểm tra phòng trống', route: '/frontdesk?tab=available' },
      { name: 'Quản lý hóa đơn', route: '/frontdesk?tab=invoices' },
      { name: 'Thông tin khách hàng', route: '/frontdesk?tab=customers' },
      { name: 'Sang ngày', route: '/frontdesk?tab=day-close' },
      { name: 'Báo cáo', route: '/reports' },
      { name: 'Channel Manager', route: '/config' },
    ]
  }
  //trang buồng phòng housekeeping
  if (route.path.startsWith('/housekeeping')) {
    return [
      { name: 'Minibar', route: '/housekeeping?tab=minibar' },
      { name: 'Giặt ủi', route: '/housekeeping?tab=laundry' },
      { name: 'Hàng đền bù', route: '/housekeeping?tab=compensation' },
      { name: 'Báo cáo', route: '/reports' },
    ]
  }
  //trang báo cáo reports
  if (route.path.startsWith('/reports')) {
    return [
      { name: 'Báo cáo doanh thu', route: '/reports?type=revenue' },
      { name: 'Báo cáo thống kê', route: '/reports?type=stats' },
      { name: 'Báo cáo hủy/xóa', route: '/reports?type=cancel' },
      { name: 'Quản lý', route: '/reports?type=manage' },
    ]
  }
  // trang PMS chính
  if (route.path.startsWith('/config')) {
    return [
      { name: 'Đặt phòng', route: '/reservation' },
      { name: 'Lễ tân', route: '/frontdesk' },
      { name: 'Buồng phòng', route: '/housekeeping' },
      { name: 'Báo cáo quản lý', route: '/reports' },
      { name: 'Cấu hình hệ thống', route: '/config' },
    ]
  }
  // trang đặt phòng
  return [
    {
      name: 'Đăng ký',
      route: '/reservation',
      dropdown: [
        { name: 'TẠO ĐĂNG KÝ', tab: 'create-res' },
        { name: 'ALLOTMENT', tab: 'allotment' },
        { name: 'CHI TIẾT ALLOTMENT', tab: 'allotment-detail' },
        { name: 'BÁO CÁO PHÂN BỔ PHÒNG ALLOTMENT', tab: 'allotment-report' }
      ]
    },
    {
      name: 'Kiểm tra phòng trống',
      route: '/reservation?tab=available',
      dropdown: [
        { name: 'PHÒNG TRỐNG', tab: 'available' },
        { name: 'KẾ HOẠCH PHÒNG', tab: 'room-plan' },
        { name: 'QUẢN LÝ PHÒNG', tab: 'manage-rooms' },
        { name: 'PHÒNG KHÓA', tab: 'lock-room' }
      ]
    },
    {
      name: 'Báo cáo',
      route: '/reservation?tab=reports',
      dropdown: [
        { name: 'BÁO CÁO ĐĂNG KÝ', tab: 'report-reg', hasChevron: true },
        { name: 'BÁO CÁO THỐNG KÊ', tab: 'report-stats', hasChevron: true },
        { name: 'BÁO CÁO PHÒNG', tab: 'report-rooms', hasChevron: true },
        { name: 'BÁO CÁO HỦY PHÒNG', tab: 'report-cancel', hasChevron: true }
      ]
    },
    {
      name: 'Channel Manager',
      route: '/reservation?tab=channel-manager',
      dropdown: [
        { name: 'BÁO CÁO ĐĂNG KÝ CHANNEL MANAGER', tab: 'channel-manager' }
      ]
    }
  ]
})

const subMenuItems = computed(() => {
  const currentTab = route.path.startsWith('/reports')
    ? (route.query.tab || 'overview')
    : (route.query.tab || 'room-map')
  
  if (route.path.startsWith('/reservation')) {
    return [
      { name: 'Sơ đồ Phòng', icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: 'Phòng Trống', icon: 'check-circle', tab: 'available', active: currentTab === 'available' },
      { name: 'Kế Hoạch Phòng', icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: 'Tạo Đăng Ký', icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: 'Quản Lý Phòng', icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: 'Khóa Phòng', icon: 'lock', tab: 'lock-room', active: currentTab === 'lock-room' },
      { name: 'D.S Công Việc', icon: 'briefcase', tab: 'shift-work', active: currentTab === 'shift-work' },
      { name: 'Công Ty', icon: 'building', tab: 'company', active: currentTab === 'company' },
      { name: 'Báo Cáo', icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      { name: 'Lịch Sử Thao Tác', icon: 'clock', tab: 'history', active: currentTab === 'history' },
      { name: 'Tìm Kiếm Chung', icon: 'search', tab: 'search', active: currentTab === 'search' },
    ]
  }
  
  if (route.path.startsWith('/frontdesk')) {
    return [
      { name: 'Sơ đồ Phòng', icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: 'Phòng Trống', icon: 'check-circle', tab: 'available', active: currentTab === 'available' },
      { name: 'Kế Hoạch Phòng', icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: 'Tạo Đăng Ký', icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: 'Trả Phòng', icon: 'dollar-sign', tab: 'checkout', active: currentTab === 'checkout' },
      { name: 'Quản Lý Phòng', icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: 'Tìm Kiếm Chung', icon: 'search', tab: 'search', active: currentTab === 'search' },
      { name: 'Sang Ngày', icon: 'calendar-range', tab: 'day-close', active: currentTab === 'day-close' },
      { name: 'D.S Công Việc', icon: 'briefcase', tab: 'shift-work', active: currentTab === 'shift-work' },
      { name: 'Báo Cáo', icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      { name: 'Lịch Sử Thao Tác', icon: 'clock', tab: 'history', active: currentTab === 'history' },
    ]
  }
  
  if (route.path.startsWith('/housekeeping')) {
    return [] // Navigation handled by HousekeepingPage sidebar
  }
  
  if (route.path.startsWith('/reports')) {
    return [
      { name: 'Tổng Quan', icon: 'pie-chart', tab: 'overview', active: currentTab === 'overview' },
      { name: 'Quản Lý Phòng', icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: 'Tạo Đăng Ký', icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: 'Trả Phòng', icon: 'dollar-sign', tab: 'checkout', active: currentTab === 'checkout' },
      { name: 'Báo Cáo', icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      { name: 'Lịch Sử Thao Tác', icon: 'clock', tab: 'history', active: currentTab === 'history' },
    ]
  }
  
  return []
})

// Custom date/time string to match: Demo - Ca 2 09/06/2026 2:56 CH (Bound to Asia/Ho_Chi_Minh timezone)
const formattedTimeVi = computed(() => {
  const d = currentDate.value
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })
  
  const parts = formatter.formatToParts(d)
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  const year = parts.find(p => p.type === 'year').value
  const hour = parts.find(p => p.type === 'hour').value
  const minute = parts.find(p => p.type === 'minute').value
  const dayPeriod = parts.find(p => p.type === 'dayPeriod').value // "AM" or "PM"
  
  const dateStr = `${day}/${month}/${year}`
  const period = dayPeriod === 'PM' ? 'CH' : 'SA'
  return `${dateStr} ${hour}:${minute} ${period}`
})

// Update time every minute
setInterval(() => {
  currentDate.value = new Date()
}, 60000)

function isActive(menuRoute) {
  const [path, query] = menuRoute.split('?')
  const pathMatch = route.path === path || route.path.startsWith(path + '/')
  if (!query) return pathMatch
  
  const searchParams = new URLSearchParams(query)
  for (const [key, val] of searchParams.entries()) {
    if (key === 'type' && val === 'revenue' && !route.query.type) {
      continue
    }
    if (route.query[key] !== val) {
      return false
    }
  }
  return pathMatch
}

function navigateTo(menuRoute) {
  router.push(menuRoute)
}

function handleSubMenuClick(item) {
  if (item.tab) {
    router.push({ path: route.path, query: { tab: item.tab } })
  }
}

function handleDropdownClick(sub) {
  if (sub.tab) {
    router.push({ path: '/reservation', query: { tab: sub.tab } })
  }
}

function goHome() {
  router.push('/pms')
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
}
</script>

<template>
  <div class="flex flex-col h-screen overflow-hidden bg-slate-50">
    <!-- Top Header Bar (Light Theme) -->
    <header 
      class="grid grid-cols-[1fr_auto_1fr] items-center h-12 border-b border-slate-200 px-4 shrink-0 z-50 transition-colors duration-200"
      :class="route.path !== '/pms' && route.path !== '/' && route.path !== '/login' ? 'bg-[#e0f2fe]' : 'bg-white'"
    >
      <!-- Logo (Left) -->
      <div class="flex items-center justify-start">
        <button
          @click="goHome"
          class="flex items-center gap-1.5 hover:opacity-80 transition-opacity cursor-pointer bg-transparent border-none p-0"
        >
          <div class="w-7 h-7 bg-[#0ea5e9] flex items-center justify-center rounded-sm rotate-45 transform-gpu overflow-hidden shadow-sm">
            <div class="w-3.5 h-3.5 bg-white -rotate-45 transform-gpu"></div>
          </div>
          <span class="text-base font-black text-slate-800 tracking-wider">PMS</span>
        </button>
      </div>

      <!-- Main Navigation (Center) -->
      <nav v-if="route.path !== '/pms'" class="flex items-center gap-1.5">
        <div
          v-for="item in menuItems"
          :key="item.route"
          class="relative group py-2"
        >
          <button
            @click="navigateTo(item.route)"
            class="px-3.5 py-1.5 text-[13.5px] font-extrabold transition-colors cursor-pointer border-none whitespace-nowrap bg-transparent tracking-wide"
            :class="isActive(item.route)
              ? 'text-[#0284c7]'
              : 'text-slate-600 hover:text-slate-900'"
          >
            {{ item.name.toUpperCase() }}
          </button>
          
          <!-- Dropdown Menu -->
          <div
            v-if="item.dropdown"
            class="absolute left-1/2 -translate-x-1/2 mt-1.5 w-72 bg-white border border-slate-200 rounded-lg shadow-lg py-1.5 z-[100] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 transform translate-y-1 group-hover:translate-y-0 text-slate-800"
          >
            <!-- Tip decoration arrow -->
            <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-white border-t border-l border-slate-200 rotate-45"></div>
            
            <button
              v-for="sub in item.dropdown"
              :key="sub.name"
              @click="handleDropdownClick(sub)"
              class="w-full text-left px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-sky-50 hover:text-sky-700 transition-colors border-none bg-transparent cursor-pointer flex items-center justify-between relative z-10"
            >
              <span>{{ sub.name }}</span>
              <svg v-if="sub.hasChevron" class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>
          </div>
        </div>
      </nav>

      <!-- Right Side: User Info / Date / Time (Right) -->
      <div class="flex items-center justify-end gap-1.5 text-sm whitespace-nowrap shrink-0">
        <!-- Search icon button -->
        <button class="p-0.5 hover:bg-slate-100 rounded text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center shrink-0">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>

        <!-- Dark Mode Toggle Button -->
        <button 
          @click="toggleDarkMode" 
          class="p-0.5 hover:bg-slate-100 rounded text-slate-600 bg-transparent border-none cursor-pointer transition-all duration-300 transform active:scale-95 flex items-center justify-center shrink-0"
          title="Bật/Tắt Chế độ tối"
        >
          <!-- Moon Icon (for Light Mode) -->
          <svg v-if="!isDark" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
          </svg>
          <!-- Sun Icon (for Dark Mode) -->
          <svg v-else class="w-3.5 h-3.5 text-yellow-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </button>

        <!-- HKT 1 Dropdown -->
        <div class="flex items-center gap-0.5 text-slate-700 hover:text-slate-900 cursor-pointer font-bold shrink-0 whitespace-nowrap">
          <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span class="text-[11.5px] leading-none">HKT 1</span>
          <svg class="w-2.5 h-2.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </div>

        <!-- User Profile Dropdown -->
        <div class="relative shrink-0" ref="dropdownRef">
          <div 
            @click="isDropdownOpen = !isDropdownOpen" 
            class="flex items-center gap-1 text-slate-700 bg-slate-100 hover:bg-slate-200/80 px-2 py-0.5 rounded-full text-[11.5px] font-bold cursor-pointer transition-colors select-none whitespace-nowrap"
          >
            <img 
              v-if="currentUser?.avatar" 
              :src="currentUser.avatar" 
              alt="Avatar" 
              class="w-3.5 h-3.5 rounded-full object-cover border border-slate-300 shrink-0"
            />
            <svg v-else class="w-3 h-3 text-slate-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
            </svg>
            <span class="leading-none">{{ currentUser?.name || 'Khách' }}</span>
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform duration-200 shrink-0" :class="isDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Dropdown Menu -->
          <div 
            v-if="isDropdownOpen" 
            class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-[100]"
          >
            <div class="px-4 py-2 border-b border-slate-100 mb-1.5">
              <p class="text-xs font-bold text-slate-800 truncate">{{ currentUser?.name || 'Chưa Đăng Nhập' }}</p>
              <p class="text-[10px] text-slate-500 truncate mt-0.5">{{ currentUser?.email || currentUser?.zalo_id || 'guest@pms.com' }}</p>
            </div>

            <button 
              @click="router.push('/'); isDropdownOpen = false"
              class="w-full text-left px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              <span>Trang chủ Portal</span>
            </button>

            <div class="border-t border-slate-100 my-1"></div>

            <button 
              @click="handleLogout"
              class="w-full text-left px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>Đăng xuất</span>
            </button>
          </div>
        </div>

        <!-- Shift & Date Time in Header (Mockup style) -->
        <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 font-bold text-[11px] whitespace-nowrap px-0.5 shrink-0">
          <span>Ca: 2</span>
          <span>{{ formattedTimeVi }}</span>
        </div>

        <!-- Vietnamese Flag -->
        <div class="w-4.5 h-3 bg-red-600 flex items-center justify-center rounded-xs shadow-xs relative overflow-hidden shrink-0 border border-red-700/10">
          <svg class="w-1.5 h-1.5 text-yellow-400 fill-current" viewBox="0 0 24 24">
            <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192L12 .587z"/>
          </svg>
        </div>
      </div>
    </header>

    <!-- Sub Navigation (Light Theme Tabs) -->
    <div
      v-if="subMenuItems.length > 0"
      class="flex items-center gap-2 h-11 bg-white border-b border-slate-200 px-4 shrink-0 overflow-x-auto"
    >
      <button
        v-for="item in subMenuItems"
        :key="item.name"
        @click="handleSubMenuClick(item)"
        class="flex items-center gap-1.5 px-3.5 py-1.5 text-[13px] rounded-full transition-all duration-200 cursor-pointer border whitespace-nowrap relative font-bold"
        :class="item.active
          ? 'bg-[#bdecfe] text-[#0369a1] border-[#7dd3fc]'
          : 'bg-transparent text-slate-600 border-transparent hover:bg-slate-100 hover:text-slate-900'"
      >
        <!-- Icons inline SVG for each type -->
        <svg v-if="item.icon === 'pie-chart'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        <svg v-else-if="item.icon === 'grid'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
        <svg v-else-if="item.icon === 'check-circle'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'calendar-range'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        <svg v-else-if="item.icon === 'plus-circle'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'settings'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <svg v-else-if="item.icon === 'lock'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
        <svg v-else-if="item.icon === 'briefcase'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
        <svg v-else-if="item.icon === 'building'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
        <svg v-else-if="item.icon === 'bar-chart'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
        <svg v-else-if="item.icon === 'clock'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'search'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        <svg v-else-if="item.icon === 'dollar-sign'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'printer'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        <svg v-else-if="item.icon === 'box'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
        <svg v-else class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /></svg>
        
        <span>{{ item.name }}</span>

        <!-- Notification Badge specifically for "D.S Công Việc" -->
        <span
          v-if="item.name === 'D.S Công Việc'"
          class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center font-bold border border-white"
        >
          2
        </span>
      </button>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-auto">
      <slot />
    </main>
  </div>
</template>
