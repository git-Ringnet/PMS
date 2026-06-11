<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const sidebarCollapsed = ref(false)
const currentDate = ref(new Date())

const menuItems = [
  {
    name: 'Đăng ký',
    route: '/reservation',
  },
  {
    name: 'Kiểm tra phòng trống',
    route: '/frontdesk',
  },
  {
    name: 'Báo cáo',
    route: '/reports',
  },
  {
    name: 'Channel Manager',
    route: '/config',
  },
]

const subMenuItems = computed(() => {
  if (route.path.startsWith('/reservation')) {
    const currentTab = route.query.tab || 'room-map'
    return [
      { name: 'Sơ đồ Phòng', icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: 'Phòng Trống', icon: 'check-circle', tab: 'available', active: currentTab === 'available' },
      { name: 'Kế Hoạch Phòng', icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: 'Tạo Đăng Ký', icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: 'Quản Lý Phòng', icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: 'Khóa Phòng', icon: 'lock', tab: 'lock-room', active: currentTab === 'lock-room' },
      { name: 'Đổi Công Việc', icon: 'briefcase', tab: 'shift-work', active: currentTab === 'shift-work' },
      { name: 'Công Ty', icon: 'building', tab: 'company', active: currentTab === 'company' },
      { name: 'Báo Cáo', icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      { name: 'Lịch Sử Thao Tác', icon: 'clock', tab: 'history', active: currentTab === 'history' },
      { name: 'Tìm Kiếm Chung', icon: 'search', tab: 'search', active: currentTab === 'search' },
    ]
  }
  return []
})

// Custom date/time string to match: Demo - Ca 2 09/06/2026 2:56 CH
const formattedTimeVi = computed(() => {
  const d = currentDate.value
  const dateStr = `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
  let hours = d.getHours()
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const ampm = hours >= 12 ? 'CH' : 'SA'
  hours = hours % 12
  hours = hours ? hours : 12 // the hour '0' should be '12'
  return `${dateStr} ${hours}:${minutes} ${ampm}`
})

// Update time every minute
setInterval(() => {
  currentDate.value = new Date()
}, 60000)

function isActive(menuRoute) {
  return route.path === menuRoute || route.path.startsWith(menuRoute + '/')
}

function navigateTo(menuRoute) {
  router.push(menuRoute)
}

function handleSubMenuClick(item) {
  if (item.tab) {
    router.push({ path: route.path, query: { tab: item.tab } })
  }
}

function goHome() {
  router.push('/')
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
}
</script>

<template>
  <div class="flex flex-col h-screen overflow-hidden bg-slate-50">
    <!-- Top Header Bar (Light Theme) -->
    <header class="grid grid-cols-[1fr_auto_1fr] items-center h-12 bg-white border-b border-slate-200 px-4 shrink-0 z-50">
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
      <nav class="flex items-center gap-1.5">
        <button
          v-for="item in menuItems"
          :key="item.route"
          @click="navigateTo(item.route)"
          class="px-3.5 py-1.5 text-[13.5px] font-extrabold transition-colors cursor-pointer border-none whitespace-nowrap bg-transparent tracking-wide"
          :class="isActive(item.route)
            ? 'text-[#0284c7]'
            : 'text-slate-600 hover:text-slate-900'"
        >
          {{ item.name.toUpperCase() }}
        </button>
      </nav>

      <!-- Right Side: User Info / Date / Time (Right) -->
      <div class="flex items-center justify-end gap-4 text-sm">
        <!-- Search icon button -->
        <button class="p-1 hover:bg-slate-100 rounded text-slate-600 bg-transparent border-none cursor-pointer">
          <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>

        <!-- HKT 1 Dropdown -->
        <div class="flex items-center gap-1 text-slate-700 hover:text-slate-900 cursor-pointer font-bold">
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span class="text-[13px]">HKT 1</span>
          <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </div>

        <!-- User Ca Info -->
        <div class="flex items-center gap-2 text-slate-700 bg-slate-100 hover:bg-slate-200/80 px-3.5 py-1.5 rounded-full text-[13px] font-bold cursor-pointer transition-colors">
          <svg class="w-3.5 h-3.5 text-slate-500" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
          </svg>
          <span>Demo - Ca 2 &nbsp;{{ formattedTimeVi }}</span>
        </div>

        <!-- Vietnamese Flag -->
        <div class="w-6 h-4.5 bg-red-600 flex items-center justify-center rounded-sm shadow-sm relative overflow-hidden shrink-0 border border-red-700/10">
          <svg class="w-2.5 h-2.5 text-yellow-400 fill-current" viewBox="0 0 24 24">
            <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192L12 .587z"/>
          </svg>
        </div>
      </div>
    </header>

    <!-- Sub Navigation (Light Theme Tabs) -->
    <div
      v-if="subMenuItems.length > 0"
      class="flex items-center gap-2 h-11 bg-slate-50 border-b border-slate-200 px-4 shrink-0 overflow-x-auto"
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
        <svg v-if="item.icon === 'grid'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
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
        <svg v-else class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /></svg>
        
        <span>{{ item.name }}</span>

        <!-- Notification Badge specifically for "Đổi Công Việc" -->
        <span
          v-if="item.name === 'Đổi Công Việc'"
          class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center font-bold border border-white"
        >
          7
        </span>
      </button>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden">
      <slot />
    </main>
  </div>
</template>
