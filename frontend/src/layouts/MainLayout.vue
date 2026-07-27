<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'
import { fetchSystemBranches } from '@/services/company-service'
import http from '@/services/http'
import { t, currentLang } from '@/utils/i18n'
import ActivityLogTab from '@/pages/system/components/ActivityLogTab.vue'
import { useUiStore } from '@/stores/ui-store'

const route = useRoute()
const router = useRouter()
const sidebarCollapsed = ref(false)
const currentDate = ref(new Date())
const timeOffset = ref(0)
const systemDate = ref('')
const dbShift = ref('')

const authStore = useAuthStore()

// Topbar custom background color (default #006bdb)
const headerBgColor = computed(() => authStore.settings?.topbar_color || '#006bdb')

const isColorPickerOpen = ref(false)
const colorPickerRef = ref(null)
const activeColorTab = ref('solid')

const gradientPresets = [
  { name: 'Hoàng hôn', value: 'linear-gradient(135deg, #f43f5e, #fb923c, #facc15)' },
  { name: 'Đại dương', value: 'linear-gradient(135deg, #0ea5e9, #2563eb, #1d4ed8)' },
  { name: 'Cực quang', value: 'linear-gradient(135deg, #059669, #10b981, #06b6d4)' },
  { name: 'Tinh vân', value: 'linear-gradient(135deg, #4f46e5, #a855f7, #ec4899)' },
  { name: 'Kẹo ngọt', value: 'linear-gradient(135deg, #f472b6, #fb7185, #ec4899)' },
  { name: 'Thạch anh', value: 'linear-gradient(135deg, #1e293b, #475569, #0f172a)' },
  { name: 'Ngọc bích', value: 'linear-gradient(135deg, #10b981, #14b8a6, #0f766e)' },
  { name: 'Hổ phách', value: 'linear-gradient(135deg, #d97706, #f59e0b, #fbbf24)' }
]

const customSolidColor = ref('#006bdb')
const customGradientStart = ref('#0ea5e9')
const customGradientMiddle = ref('#a855f7')
const customGradientEnd = ref('#2563eb')
const customGradientAngle = ref(135)

function updateCssVariables(colorVal) {
  if (colorVal === '#006bdb') {
    document.documentElement.style.removeProperty('--pms-custom-theme')
    document.documentElement.style.removeProperty('--pms-custom-theme-text')
    document.documentElement.style.removeProperty('--pms-custom-theme-border')
  } else {
    document.documentElement.style.setProperty('--pms-custom-theme', colorVal)
    
    // Check if background is dark
    let isDark = false
    if (colorVal.includes('linear-gradient')) {
      isDark = true
    } else if (colorVal.startsWith('#')) {
      const hex = colorVal.substring(1)
      const rgb = parseInt(hex.length === 3 ? hex.split('').map(c => c + c).join('') : hex, 16)
      if (!isNaN(rgb)) {
        const r = (rgb >> 16) & 0xff
        const g = (rgb >> 8) & 0xff
        const b = (rgb >> 0) & 0xff
        const brightness = (r * 299 + g * 587 + b * 114) / 1000
        isDark = brightness < 185
      }
    }
    
    document.documentElement.style.setProperty('--pms-custom-theme-text', isDark ? '#ffffff' : '#003d66')
    document.documentElement.style.setProperty('--pms-custom-theme-border', 'transparent')
  }
}

function setHeaderColor(colorVal) {
  authStore.updateUserSettings({ topbar_color: colorVal })
  updateCssVariables(colorVal)
}

function resetHeaderColor() {
  setHeaderColor('#006bdb')
  customSolidColor.value = '#006bdb'
  customGradientStart.value = '#0ea5e9'
  customGradientMiddle.value = '#a855f7'
  customGradientEnd.value = '#2563eb'
  customGradientAngle.value = 135
  activeColorTab.value = 'solid'
}

watch(() => authStore.settings?.topbar_color, (newColor) => {
  if (newColor) {
    updateCssVariables(newColor)
  }
}, { immediate: true })

function updateCustomSolid() {
  setHeaderColor(customSolidColor.value)
}

function updateCustomGradient() {
  const gradStr = `linear-gradient(${customGradientAngle.value}deg, ${customGradientStart.value}, ${customGradientMiddle.value}, ${customGradientEnd.value})`
  setHeaderColor(gradStr)
}

// Compute if header bg is dark to set high-contrast white text
const isHeaderBgDark = computed(() => {
  if (route.path === '/pms' || route.path === '/' || route.path === '/login') {
    return false
  }
  const bg = headerBgColor.value
  if (!bg) return false
  if (bg.includes('linear-gradient')) {
    return true
  }
  if (bg.startsWith('#')) {
    const hex = bg.substring(1)
    const rgb = parseInt(hex.length === 3 ? hex.split('').map(c => c + c).join('') : hex, 16)
    if (isNaN(rgb)) return false
    const r = (rgb >> 16) & 0xff
    const g = (rgb >> 8) & 0xff
    const b = (rgb >> 0) & 0xff
    const brightness = (r * 299 + g * 587 + b * 114) / 1000
    return brightness < 185
  }
  return false
})

const uiStore = useUiStore()
const currentUser = computed(() => authStore.user)

const shifts = ref([])
const activeShiftName = ref('2')

function isTimeInShift(currentTime, startTimeStr, endTimeStr) {
  if (!startTimeStr || !endTimeStr) return false
  const [startH, startM] = startTimeStr.split(':').map(Number)
  const [endH, endM] = endTimeStr.split(':').map(Number)
  
  const currentH = currentTime.getHours()
  const currentM = currentTime.getMinutes()
  const currentS = currentTime.getSeconds()
  
  const currentSeconds = currentH * 3600 + currentM * 60 + currentS
  const startSeconds = startH * 3600 + startM * 60
  const endSeconds = endH * 3600 + endM * 60 + 59
  
  if (startSeconds <= endSeconds) {
    return currentSeconds >= startSeconds && currentSeconds <= endSeconds
  } else {
    return currentSeconds >= startSeconds || currentSeconds <= endSeconds
  }
}

const fetchShifts = async () => {
  try {
    const res = await http.get('/shifts')
    if (res.data && res.data.data) {
      shifts.value = res.data.data
      updateActiveShift()
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách ca làm việc:', err)
  }
}

const fetchServerTime = async () => {
  try {
    const res = await http.get('/system-time')
    if (res.data && res.data.time) {
      const serverTime = new Date(res.data.time)
      const clientTime = new Date()
      timeOffset.value = serverTime.getTime() - clientTime.getTime()
      currentDate.value = new Date(Date.now() + timeOffset.value)
      updateActiveShift()
    }
  } catch (err) {
    console.error('Lỗi khi tải giờ hệ thống:', err)
  }
}

const fetchSystemDate = async () => {
  try {
    const res = await http.get('/system-date')
    if (res.data && res.data.success && res.data.data) {
      systemDate.value = res.data.data.system_date
      if (res.data.data.shift) {
        dbShift.value = res.data.data.shift
        activeShiftName.value = res.data.data.shift
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải ngày hệ thống:', err)
  }
}

const updateActiveShift = () => {
  if (dbShift.value) {
    activeShiftName.value = dbShift.value
    return
  }
  if (shifts.value.length === 0) return
  const now = currentDate.value
  const activeShift = shifts.value.find(s => isTimeInShift(now, s.start_time, s.end_time))
  if (activeShift) {
    activeShiftName.value = activeShift.name
  }
}
const isDropdownOpen = ref(false)
const dropdownRef = ref(null)
const isDark = ref(false)

// Branch selector state
const selectedBranch = ref(null)
const branchesList = ref([])
const isBranchDropdownOpen = ref(false)
const branchDropdownRef = ref(null)
const isSwitchingBranch = ref(sessionStorage.getItem('switching_branch') === 'true')
const switchingToName = ref(sessionStorage.getItem('switching_to_name') || '')

// Language selector state
const isLangDropdownOpen = ref(false)
const langDropdownRef = ref(null)

const loadBranches = async () => {
  if (!authStore.token) return
  try {
    const res = await fetchSystemBranches()
    branchesList.value = res.data.data || []
    
    // Khôi phục chi nhánh đã lưu từ localStorage hoặc lấy chi nhánh đầu tiên
    const savedBranchId = localStorage.getItem('selected_branch_id')
    if (savedBranchId && branchesList.value.some(b => b.id === Number(savedBranchId))) {
      selectedBranch.value = branchesList.value.find(b => b.id === Number(savedBranchId))
    } else if (branchesList.value.length > 0) {
      const defaultBranch = branchesList.value.find(b => b.code === 'HKT1') || branchesList.value[0]
      selectedBranch.value = defaultBranch
      localStorage.setItem('selected_branch_id', defaultBranch.id)
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách chi nhánh:', err)
  }
}

function handleSelectBranch(branch) {
  selectedBranch.value = branch
  localStorage.setItem('selected_branch_id', branch.id)
  isBranchDropdownOpen.value = false
  
  sessionStorage.setItem('switching_branch', 'true')
  sessionStorage.setItem('switching_to_name', branch.name)
  isSwitchingBranch.value = true
  
  setTimeout(() => {
    window.location.reload()
  }, 100)
}

function handleSelectLang(lang) {
  currentLang.value = lang
  localStorage.setItem('pms_lang', lang)
  isLangDropdownOpen.value = false
  
  document.documentElement.setAttribute('lang', lang)
  
  // Hiển thị màn hình chờ chuyển tiếp mượt mà khi đổi ngôn ngữ
  sessionStorage.setItem('switching_branch', 'true')
  sessionStorage.setItem('switching_to_name', lang === 'vi' ? 'Tiếng Việt' : 'English')
  isSwitchingBranch.value = true
  
  setTimeout(() => {
    window.location.reload()
  }, 100)
}

async function handleLogout() {
  if (confirm(t('header.logoutConfirm'))) {
    isDropdownOpen.value = false
    await authStore.logout()
    router.push('/login')
  }
}

function closeDropdown(e) {
  if (isDropdownOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isDropdownOpen.value = false
  }
  if (isBranchDropdownOpen.value && branchDropdownRef.value && !branchDropdownRef.value.contains(e.target)) {
    isBranchDropdownOpen.value = false
  }
  if (isLangDropdownOpen.value && langDropdownRef.value && !langDropdownRef.value.contains(e.target)) {
    isLangDropdownOpen.value = false
  }
  if (isColorPickerOpen.value && colorPickerRef.value && !colorPickerRef.value.contains(e.target)) {
    if (e.target && e.target.tagName === 'INPUT' && e.target.type === 'color') {
      return
    }
    isColorPickerOpen.value = false
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

let timeInterval = null

onMounted(() => {
  window.addEventListener('click', closeDropdown)
  
  // Thiết lập thuộc tính lang của HTML document
  document.documentElement.setAttribute('lang', currentLang.value)
  
  const savedTheme = localStorage.getItem('theme')
  if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true
    document.documentElement.classList.add('dark')
  } else {
    isDark.value = false
    document.documentElement.classList.remove('dark')
  }
  
  // Initialize color customization tabs/values
  const savedBg = authStore.settings?.topbar_color || '#006bdb'
  if (savedBg.includes('linear-gradient')) {
    activeColorTab.value = 'gradient'
    const angleMatch = savedBg.match(/(\d+)deg/)
    if (angleMatch) customGradientAngle.value = parseInt(angleMatch[1])
    
    const hexColors = savedBg.match(/#[a-fA-F0-9]{6}/g) || savedBg.match(/#[a-fA-F0-9]{3}/g)
    if (hexColors) {
      if (hexColors.length >= 3) {
        customGradientStart.value = hexColors[0]
        customGradientMiddle.value = hexColors[1]
        customGradientEnd.value = hexColors[2]
      } else if (hexColors.length === 2) {
        customGradientStart.value = hexColors[0]
        customGradientMiddle.value = '#a855f7' // default middle fallback
        customGradientEnd.value = hexColors[1]
      }
    }
  } else {
    activeColorTab.value = 'solid'
    customSolidColor.value = savedBg
  }
  
  // Apply initial custom CSS variables
  updateCssVariables(savedBg)
  
  fetchServerTime()
  fetchSystemDate()
  fetchShifts()
  loadBranches().finally(() => {
    setTimeout(() => {
      isSwitchingBranch.value = false
      sessionStorage.removeItem('switching_branch')
      sessionStorage.removeItem('switching_to_name')
    }, 450)
  })
  
  // Cập nhật giờ và ca làm việc mỗi giây
  timeInterval = setInterval(() => {
    currentDate.value = new Date(Date.now() + timeOffset.value)
    updateActiveShift()
  }, 1000)
})

onUnmounted(() => {
  window.removeEventListener('click', closeDropdown)
  if (timeInterval) {
    clearInterval(timeInterval)
  }
})

const menuItems = computed(() => {
  if (route.path.startsWith('/frontdesk')) {
    //trang lễ tân frontdesk
    return [
      { name: t('menu.checkIn'), route: '/frontdesk' },
      { name: t('menu.checkVacancy'), route: '/frontdesk?tab=available' },
      { name: t('menu.invoiceManage'), route: '/frontdesk?tab=invoices' },
      {
        name: t('menu.customerInfo'),
        route: '/frontdesk?tab=customers',
        dropdown: [
          { name: t('menu.residenceDeclaration'), tab: 'residence-declaration' }
        ]
      },
      { name: t('menu.dayClose'), route: '/frontdesk?tab=day-close' },
      { name: t('menu.reports'), route: '/reports' },
      { name: t('menu.channelManager'), route: '/config' },
    ]
  }
  //trang buồng phòng housekeeping
  if (route.path.startsWith('/housekeeping')) {
    return [
      { name: t('menu.minibar'), route: '/housekeeping?tab=minibar' },
      { name: t('menu.laundry'), route: '/housekeeping?tab=laundry' },
      { name: t('menu.compensation'), route: '/housekeeping?tab=compensation' },
      { name: t('menu.reports'), route: '/reports' },
    ]
  }
  //trang báo cáo reports
  if (route.path.startsWith('/reports')) {
    return [
      { name: t('menu.revReport'), route: '/reports?type=revenue' },
      { name: t('menu.statReport'), route: '/reports?type=stats' },
      { name: t('menu.cancelReport'), route: '/reports?type=cancel' },
      { name: t('menu.management'), route: '/reports?type=manage' },
    ]
  }
  // trang PMS chính
  if (route.path.startsWith('/config')) {
    return [
      { name: t('menu.reservation'), route: '/reservation' },
      { name: t('menu.frontdesk'), route: '/frontdesk' },
      { name: t('menu.housekeeping'), route: '/housekeeping' },
      { name: t('menu.mgtReports'), route: '/reports' },
      { name: t('menu.sysConfig'), route: '/config' },
    ]
  }
  // trang đặt phòng
  return [
    {
      name: t('menu.registration'),
      route: '/reservation',
      dropdown: [
        { name: t('menu.createReg'), tab: 'create-res' },
        { name: t('menu.allotment'), tab: 'allotment' },
        { name: t('menu.allotmentDetail'), tab: 'allotment-detail' },
        { name: t('menu.allotmentReport'), tab: 'allotment-report' }
      ]
    },
    {
      name: t('menu.checkVacancy'),
      route: '/reservation?tab=available',
      dropdown: [
        { name: t('menu.vacantRoom'), tab: 'available' },
        { name: t('menu.roomPlan'), tab: 'room-plan' },
        { name: t('menu.roomManage'), tab: 'manage-rooms' },
        { name: t('menu.lockRoom'), tab: 'lock-room' }
      ]
    },
    {
      name: t('menu.reports'),
      route: '/reservation?tab=reports',
      dropdown: [
        { name: t('menu.regReport'), tab: 'report-reg', hasChevron: true },
        { name: t('menu.statReport'), tab: 'report-stats', hasChevron: true },
        { name: t('menu.roomReport'), tab: 'report-rooms', hasChevron: true },
        { name: t('menu.cancelReportTitle'), tab: 'report-cancel', hasChevron: true }
      ]
    },
    {
      name: t('menu.channelManager'),
      route: '/reservation?tab=channel-manager',
      dropdown: [
        { name: t('menu.channelManagerRegReport'), tab: 'channel-manager' }
      ]
    }
  ]
})

const getQueryParam = (name) => {
  if (typeof window === 'undefined') return null
  const params = new URLSearchParams(window.location.search)
  return params.get(name)
}

const subMenuItems = computed(() => {
  const currentTab = route.path.startsWith('/reports')
    ? (route.query.tab || getQueryParam('tab') || 'overview')
    : (route.query.tab || getQueryParam('tab') || 'room-map')
  
  if (route.path.startsWith('/reservation')) {
    return [
      { name: t('submenu.roomMap'), icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: t('submenu.vacantRooms'), icon: 'check-circle', tab: 'available', active: currentTab === 'available' },
      { name: t('submenu.roomPlan'), icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: t('submenu.createReg'), icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: t('submenu.roomManage'), icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: t('submenu.lockRoom'), icon: 'lock', tab: 'lock-room', active: currentTab === 'lock-room' },
      { name: t('submenu.taskHistory'), icon: 'briefcase', tab: 'shift-work', active: currentTab === 'shift-work' },
      { name: t('submenu.company'), icon: 'building', tab: 'company', active: currentTab === 'company' },
      { name: t('submenu.reports'), icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      // { name: t('submenu.actionHistory'), icon: 'clock', tab: 'history', active: currentTab === 'history' },
      { name: t('submenu.generalSearch'), icon: 'search', tab: 'search', active: currentTab === 'search' },
    ]
  }
  
  if (route.path.startsWith('/frontdesk')) {
    return [
      { name: t('submenu.roomMap'), icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: t('submenu.vacantRooms'), icon: 'check-circle', tab: 'available', active: currentTab === 'available' },
      { name: t('submenu.roomPlan'), icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: t('submenu.createReg'), icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: t('submenu.checkout'), icon: 'dollar-sign', tab: 'checkout', active: currentTab === 'checkout' },
      { name: t('submenu.roomManage'), icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: t('submenu.generalSearch'), icon: 'search', tab: 'search', active: currentTab === 'search' },
      { name: t('submenu.dayClose'), icon: 'calendar-range', action: 'dayClose', active: false },
      { name: t('submenu.taskHistory'), icon: 'briefcase', tab: 'shift-work', active: currentTab === 'shift-work' },
      { name: t('submenu.reports'), icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      // { name: t('submenu.actionHistory'), icon: 'clock', tab: 'history', active: currentTab === 'history' },
    ]
  }
  
  if (route.path.startsWith('/housekeeping')) {
    return [
      { name: t('submenu.roomMap'), icon: 'grid', tab: 'room-map', active: currentTab === 'room-map' },
      { name: t('submenu.roomPlan'), icon: 'calendar-range', tab: 'room-plan', active: currentTab === 'room-plan' },
      { name: t('submenu.printRoomAssign'), icon: 'printer', tab: 'print-tasks', active: currentTab === 'print-tasks' },
      { name: t('submenu.addService'), icon: 'plus-circle', tab: 'add-service', active: currentTab === 'add-service' },
      { name: t('submenu.lostFound'), icon: 'briefcase', tab: 'lost-found', active: currentTab === 'lost-found' },
      { name: t('submenu.inventory'), icon: 'box', tab: 'inventory', active: currentTab === 'inventory' },
      { name: t('submenu.lockRoom'), icon: 'lock', tab: 'lock-room', active: currentTab === 'lock-room' },
      { name: t('submenu.invoiceSearch'), icon: 'search', tab: 'invoice-search', active: currentTab === 'invoice-search' },
      { name: t('submenu.createMenu'), icon: 'settings', tab: 'create-menu', active: currentTab === 'create-menu' },
      // { name: t('submenu.actionHistory'), icon: 'clock', tab: 'history', active: currentTab === 'history' },
      { name: t('submenu.reports'), icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
    ]
  }
  
  if (route.path.startsWith('/reports')) {
    return [
      { name: t('submenu.overview'), icon: 'pie-chart', tab: 'overview', active: currentTab === 'overview' },
      { name: t('submenu.roomManage'), icon: 'settings', tab: 'manage-rooms', active: currentTab === 'manage-rooms' },
      { name: t('submenu.createReg'), icon: 'plus-circle', tab: 'create-res', active: currentTab === 'create-res' },
      { name: t('submenu.checkout'), icon: 'dollar-sign', tab: 'checkout', active: currentTab === 'checkout' },
      { name: t('submenu.reports'), icon: 'bar-chart', tab: 'reports', active: currentTab === 'reports' },
      // { name: t('submenu.actionHistory'), icon: 'clock', tab: 'history', active: currentTab === 'history' },
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
    second: '2-digit',
    hour12: true
  })
  
  const parts = formatter.formatToParts(d)
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  const year = parts.find(p => p.type === 'year').value
  const hour = parts.find(p => p.type === 'hour').value
  const minute = parts.find(p => p.type === 'minute').value
  const second = parts.find(p => p.type === 'second').value
  const dayPeriod = parts.find(p => p.type === 'dayPeriod').value // "AM" or "PM"
  
  let dateStr = ''
  if (systemDate.value) {
    const sParts = systemDate.value.split('-')
    if (sParts.length === 3) {
      dateStr = `${sParts[2]}/${sParts[1]}/${sParts[0]}`
    }
  }
  if (!dateStr) {
    dateStr = `${day}/${month}/${year}`
  }

  const period = currentLang.value === 'vi'
    ? (dayPeriod === 'PM' ? 'CH' : 'SA')
    : dayPeriod
  return `${dateStr} ${hour}:${minute}:${second} ${period}`
})



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

async function triggerDayClose() {
  const confirmed = await uiStore.confirm({
    title: t('submenu.dayClose') || 'Xác nhận sang ngày',
    message: currentLang.value === 'vi' 
      ? 'Bạn có chắc chắn muốn chuyển hệ thống sang ngày tiếp theo?'
      : 'Are you sure you want to roll the system date to the next day?',
    confirmText: currentLang.value === 'vi' ? 'Đồng ý' : 'Confirm',
    cancelText: currentLang.value === 'vi' ? 'Hủy' : 'Cancel'
  })
  
  if (confirmed) {
    try {
      uiStore.showToast(currentLang.value === 'vi' ? 'Đang chuyển ngày...' : 'Rolling day...', 'info')
      const res = await http.post('/system-date/roll')
      if (res.data && res.data.success) {
        uiStore.showToast(
          currentLang.value === 'vi' 
            ? 'Đã chuyển sang ngày tiếp theo thành công!' 
            : 'Rolled system date successfully!', 
          'success'
        )
        setTimeout(() => {
          window.location.reload()
        }, 800)
      } else {
        uiStore.showToast(
          currentLang.value === 'vi' ? 'Không thể chuyển ngày hệ thống.' : 'Failed to roll system date.', 
          'error'
        )
      }
    } catch (err) {
      console.error(err)
      uiStore.showToast(
        err.response?.data?.message || 
          (currentLang.value === 'vi' ? 'Có lỗi xảy ra khi chuyển ngày hệ thống.' : 'An error occurred while rolling system date.'),
        'error'
      )
    }
  }
}

async function navigateTo(menuRoute) {
  if (menuRoute === '/frontdesk?tab=day-close') {
    await triggerDayClose()
    return
  }
  router.push(menuRoute)
}

async function handleSubMenuClick(item) {
  if (item.action === 'dayClose') {
    await triggerDayClose()
    return
  }
  if (item.tab) {
    router.push({ path: route.path, query: { tab: item.tab } })
  }
}

function handleDropdownClick(sub) {
  if (sub.route) {
    router.push(sub.route)
  } else if (sub.tab) {
    const basePath = route.path.startsWith('/frontdesk') ? '/frontdesk' : '/reservation'
    router.push({ path: basePath, query: { tab: sub.tab } })
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
  <!-- Fullscreen Branch Transition Overlay -->
  <transition name="fade">
    <div v-if="isSwitchingBranch" class="fixed inset-0 bg-slate-50 dark:bg-black z-[99999] flex flex-col items-center justify-center">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
      <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-20 animate-pulse">
        {{ t('header.loadingBranch', { name: switchingToName }) }}
      </p>
    </div>
  </transition>

  <div class="flex flex-col h-screen w-full max-w-full overflow-hidden bg-white">
    <!-- Top Header Bar (Light Theme) -->
    <header 
      class="flex items-center justify-between gap-4 h-12 border-b border-slate-200 px-4 shrink-0 z-50 transition-all duration-200 w-full max-w-full"
      :class="route.path !== '/pms' && route.path !== '/' && route.path !== '/login' ? '' : 'bg-white'"
      :style="{ background: (route.path !== '/pms' && route.path !== '/' && route.path !== '/login') ? headerBgColor : '' }"
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
          <span 
            class="text-base font-bold tracking-wider transition-colors duration-200"
            :class="isHeaderBgDark ? 'text-white' : 'text-gray-900 dark:text-white'"
          >PMS</span>
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
            class="px-3.5 py-1.5 text-[13.5px] font-bold transition-colors cursor-pointer border-none whitespace-nowrap bg-transparent tracking-wide"
            :class="isActive(item.route)
              ? (isHeaderBgDark ? 'text-white border-b-2 border-white' : 'text-gray-900 border-b-2 border-gray-900/80 dark:text-white dark:border-white')
              : (isHeaderBgDark ? 'text-white/80 hover:text-white' : 'text-gray-900/75 hover:text-gray-900 dark:text-white/70 dark:hover:text-white')"
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
        <button 
          class="p-0.5 rounded bg-transparent border-none cursor-pointer flex items-center justify-center shrink-0 transition-colors duration-200"
          :class="isHeaderBgDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/10'"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </button>

        <!-- Dark Mode Toggle Button -->
        <button 
          @click="toggleDarkMode" 
          class="p-0.5 rounded bg-transparent border-none cursor-pointer transition-all duration-300 transform active:scale-95 flex items-center justify-center shrink-0"
          :class="isHeaderBgDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/10'"
          :title="t('header.toggleDark')"
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

        <!-- Color Palette Button (Topbar Custom Background Color Popover) -->
        <div class="relative shrink-0 flex items-center" ref="colorPickerRef">
          <button 
            @click="isColorPickerOpen = !isColorPickerOpen" 
            class="p-0.5 rounded bg-transparent border-none cursor-pointer transition-all duration-300 transform active:scale-95 flex items-center justify-center shrink-0"
            :class="isHeaderBgDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/10'"
            :title="t('header.customizeTopbar')"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.005 11.5a8 8 0 1116 0c0 1.93-1.57 3.5-3.5 3.5h-.79c-.5 0-.91.41-.91.91 0 .24.1.47.27.64.33.34.53.8.53 1.3 0 1.1-.9 2-2 2a8 8 0 01-9.6-8.35zm3-.5a1 1 0 100-2 1 1 0 000 2zm3-3a1 1 0 100-2 1 1 0 000 2zm4 0a1 1 0 100-2 1 1 0 000 2zm3 3a1 1 0 100-2 1 1 0 000 2z" />
            </svg>
          </button>

          <!-- Color Customizer Popover -->
          <div 
            v-if="isColorPickerOpen" 
            class="absolute right-0 mt-2 top-full w-72 bg-white border border-slate-200 rounded-xl shadow-xl p-4 z-[100] dark:bg-[#0c0c0c] dark:border-[#1c1c1c] text-slate-800 dark:text-slate-100"
          >
            <!-- Header -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800 mb-3 gap-2">
              <span class="text-xs font-bold mr-auto">{{ t('header.customizeTopbar') }}</span>
              <button 
                @click="resetHeaderColor" 
                class="text-[10px] text-sky-500 hover:text-sky-700 dark:hover:text-sky-400 font-bold border-none bg-transparent cursor-pointer p-0 mr-1"
                title="Khôi phục mặc định"
              >
                {{ currentLang === 'vi' ? 'Mặc định' : 'Default' }}
              </button>
              <button 
                @click="isColorPickerOpen = false" 
                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 border-none bg-transparent cursor-pointer p-0 flex items-center justify-center transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Tabs: Solid vs Gradient -->
            <div class="flex bg-slate-100 dark:bg-zinc-900 rounded-lg p-0.5 mb-3 text-xs">
              <button 
                @click="activeColorTab = 'solid'" 
                class="flex-1 py-1 rounded-md border-none cursor-pointer text-center font-bold transition-all"
                :class="activeColorTab === 'solid' ? 'bg-white shadow-xs text-slate-900 dark:bg-zinc-800 dark:text-white' : 'text-slate-500 bg-transparent'"
              >
                {{ t('header.solidColor') }}
              </button>
              <button 
                @click="activeColorTab = 'gradient'" 
                class="flex-1 py-1 rounded-md border-none cursor-pointer text-center font-bold transition-all"
                :class="activeColorTab === 'gradient' ? 'bg-white shadow-xs text-slate-900 dark:bg-zinc-800 dark:text-white' : 'text-slate-500 bg-transparent'"
              >
                {{ t('header.gradientColor') }}
              </button>
            </div>

            <!-- Solid Tab Content -->
            <div v-show="activeColorTab === 'solid'">
              <!-- Custom Color Picker -->
              <div class="flex items-center gap-2 py-2">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ t('header.chooseSolidColor') }}:</span>
                <div class="flex items-center gap-1.5 ml-auto">
                  <div class="relative w-7 h-7 rounded border border-slate-200 dark:border-zinc-800 overflow-hidden cursor-pointer shadow-xs" :style="{ backgroundColor: customSolidColor }">
                    <input 
                      type="color" 
                      v-model="customSolidColor" 
                      @input="updateCustomSolid"
                      class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                    />
                  </div>
                  <input 
                    type="text" 
                    v-model="customSolidColor" 
                    @input="updateCustomSolid"
                    class="w-20 px-1 py-0.5 text-[11px] font-bold bg-slate-50 border border-slate-200 rounded dark:bg-zinc-900 dark:border-zinc-800 uppercase text-center focus:outline-none focus:border-[#0ea5e9]"
                  />
                </div>
              </div>
            </div>

            <!-- Gradient Tab Content -->
            <div v-show="activeColorTab === 'gradient'">
              <!-- Preset Gradients Grid -->
              <span class="text-[10px] text-slate-400 font-bold block mb-1.5">{{ t('header.presets') }}</span>
              <div class="grid grid-cols-2 gap-1.5 mb-3">
                <button 
                  v-for="preset in gradientPresets" 
                  :key="preset.value"
                  @click="setHeaderColor(preset.value)"
                  class="h-8 rounded border border-transparent cursor-pointer hover:scale-102 transition-transform text-[10px] font-bold text-white flex items-center justify-center shadow-xs"
                  :style="{ background: preset.value }"
                  :title="preset.name"
                  :class="headerBgColor === preset.value ? 'ring-2 ring-[#0ea5e9] ring-offset-1 dark:ring-offset-black' : ''"
                >
                  {{ preset.name }}
                </button>
              </div>

              <!-- Custom Gradient Builder -->
              <div class="border-t border-slate-100 dark:border-zinc-800 pt-3 flex flex-col gap-2">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ t('header.chooseGradientColor') }} (3 màu):</span>
                
                <!-- Color Controls -->
                <div class="flex flex-col gap-1.5 text-[11px]">
                  <!-- Start Color -->
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] text-slate-400 w-12 shrink-0">Bắt đầu</span>
                    <div class="relative w-6 h-6 rounded border border-slate-200 dark:border-zinc-800 overflow-hidden cursor-pointer shadow-xs" :style="{ backgroundColor: customGradientStart }">
                      <input 
                        type="color" 
                        v-model="customGradientStart" 
                        @input="updateCustomGradient"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                      />
                    </div>
                    <input 
                      type="text" 
                      v-model="customGradientStart" 
                      @input="updateCustomGradient"
                      class="w-20 px-1 py-0.5 text-[10px] font-bold bg-slate-50 border border-slate-200 rounded dark:bg-zinc-900 dark:border-zinc-800 uppercase text-center focus:outline-none focus:border-[#0ea5e9]"
                    />
                  </div>

                  <!-- Middle Color -->
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] text-slate-400 w-12 shrink-0">Giữa</span>
                    <div class="relative w-6 h-6 rounded border border-slate-200 dark:border-zinc-800 overflow-hidden cursor-pointer shadow-xs" :style="{ backgroundColor: customGradientMiddle }">
                      <input 
                        type="color" 
                        v-model="customGradientMiddle" 
                        @input="updateCustomGradient"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                      />
                    </div>
                    <input 
                      type="text" 
                      v-model="customGradientMiddle" 
                      @input="updateCustomGradient"
                      class="w-20 px-1 py-0.5 text-[10px] font-bold bg-slate-50 border border-slate-200 rounded dark:bg-zinc-900 dark:border-zinc-800 uppercase text-center focus:outline-none focus:border-[#0ea5e9]"
                    />
                  </div>

                  <!-- End Color -->
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] text-slate-400 w-12 shrink-0">Kết thúc</span>
                    <div class="relative w-6 h-6 rounded border border-slate-200 dark:border-zinc-800 overflow-hidden cursor-pointer shadow-xs" :style="{ backgroundColor: customGradientEnd }">
                      <input 
                        type="color" 
                        v-model="customGradientEnd" 
                        @input="updateCustomGradient"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                      />
                    </div>
                    <input 
                      type="text" 
                      v-model="customGradientEnd" 
                      @input="updateCustomGradient"
                      class="w-20 px-1 py-0.5 text-[10px] font-bold bg-slate-50 border border-slate-200 rounded dark:bg-zinc-900 dark:border-zinc-800 uppercase text-center focus:outline-none focus:border-[#0ea5e9]"
                    />
                  </div>
                </div>

                <!-- Angle control -->
                <div class="flex items-center justify-between gap-2 mt-1">
                  <span class="text-[10px] text-slate-400 font-bold shrink-0">Góc ({{ customGradientAngle }}°)</span>
                  <input 
                    type="range" 
                    min="0" 
                    max="360" 
                    v-model="customGradientAngle" 
                    @input="updateCustomGradient"
                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#0ea5e9] dark:bg-zinc-800"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Branch Dropdown -->
        <div class="relative shrink-0" ref="branchDropdownRef">
          <div 
            @click="isBranchDropdownOpen = !isBranchDropdownOpen" 
            class="flex items-center gap-0.5 px-2 py-0.5 rounded cursor-pointer font-bold shrink-0 whitespace-nowrap select-none transition-colors duration-200"
            :class="isHeaderBgDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/5 dark:hover:bg-white/10'"
          >
            <svg class="w-3 h-3 transition-colors" :class="isHeaderBgDark ? 'text-white' : 'text-gray-900 dark:text-white'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span class="text-[11.5px] leading-none">{{ selectedBranch?.code || 'HKT 1' }}</span>
            <svg class="w-2.5 h-2.5 transition-transform duration-200" :class="[isBranchDropdownOpen ? 'rotate-180' : '', isHeaderBgDark ? 'text-white/70' : 'text-gray-900/60 dark:text-white/60']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Dropdown Options -->
          <div 
            v-if="isBranchDropdownOpen && branchesList.length > 0" 
            class="absolute left-0 mt-2 w-52 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-[100] dark:bg-[#080808] dark:border-[#1c1c1c]"
          >
            <div 
              v-for="branch in branchesList" 
              :key="branch.id"
              @click="handleSelectBranch(branch)"
              class="px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#121212] cursor-pointer flex items-center justify-between transition-colors font-semibold"
              :class="selectedBranch?.id === branch.id ? 'text-[#0ea5e9] bg-slate-50/50 dark:bg-[#121212]/50 font-bold' : ''"
            >
              <span>{{ branch.name }} ({{ branch.code }})</span>
              <svg v-if="selectedBranch?.id === branch.id" class="w-3.5 h-3.5 text-[#0ea5e9]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
        </div>

        <!-- User Profile Dropdown -->
        <div class="relative shrink-0" ref="dropdownRef">
          <div 
            @click="isDropdownOpen = !isDropdownOpen" 
            class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[11.5px] font-bold cursor-pointer transition-all select-none whitespace-nowrap"
            :class="isHeaderBgDark ? 'text-white bg-white/15 hover:bg-white/25' : 'text-gray-900 dark:text-white bg-white/25 hover:bg-white/40'"
          >
            <img 
              v-if="currentUser?.avatar" 
              :src="currentUser.avatar" 
              alt="Avatar" 
              class="w-3.5 h-3.5 rounded-full object-cover border border-white/20 shrink-0"
            />
            <svg v-else class="w-3 h-3 shrink-0" :class="isHeaderBgDark ? 'text-white' : 'text-gray-900 dark:text-white'" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
            </svg>
            <span class="leading-none">{{ currentUser?.name || t('header.guest') }}</span>
            <svg class="w-2.5 h-2.5 transition-transform duration-200 shrink-0" :class="[isDropdownOpen ? 'rotate-180' : '', isHeaderBgDark ? 'text-white/70' : 'text-gray-900/60 dark:text-white/60']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Dropdown Menu -->
          <div 
            v-if="isDropdownOpen" 
            class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-[100]"
          >
            <div class="px-4 py-2 border-b border-slate-100 mb-1.5">
              <p class="text-xs font-bold text-slate-800 truncate">{{ currentUser?.name || t('header.notLoggedIn') }}</p>
              <p class="text-[10px] text-slate-500 truncate mt-0.5">{{ currentUser?.email || currentUser?.zalo_id || 'guest@pms.com' }}</p>
            </div>

            <button 
              @click="router.push('/'); isDropdownOpen = false"
              class="w-full text-left px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              <span>{{ t('header.portalHome') }}</span>
            </button>

            <div class="border-t border-slate-100 my-1"></div>

            <button 
              @click="handleLogout"
              class="w-full text-left px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>{{ t('header.logout') }}</span>
            </button>
          </div>
        </div>

        <!-- Shift & Date Time in Header (Mockup style) -->
        <div 
          class="flex items-center gap-1.5 font-bold text-[11px] whitespace-nowrap px-0.5 shrink-0 transition-colors duration-200"
          :class="isHeaderBgDark ? 'text-white' : 'text-gray-900 dark:text-white'"
        >
          <span>{{ t('header.shift') }}: {{ activeShiftName }}</span>
          <span>{{ formattedTimeVi }}</span>
        </div>

        <!-- Language Selector Dropdown -->
        <div class="relative shrink-0 flex items-center" ref="langDropdownRef">
          <button 
            @click="isLangDropdownOpen = !isLangDropdownOpen"
            class="focus:outline-none flex items-center cursor-pointer bg-transparent border-none p-0 transition-transform duration-200 hover:scale-105 active:scale-95"
            :title="t('header.selectLanguage')"
          >
            <!-- Việt Nam Flag -->
            <svg v-if="currentLang === 'vi'" class="w-5 h-3.5 rounded-sm shadow-xs border border-red-700/10" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect width="30" height="20" fill="#DA251D"/>
              <path d="M15 4L16.2361 8.23607H20.6762L17.0863 10.8427L18.3224 15.0788L14.7325 12.4722L11.1427 15.0788L12.3788 10.8427L8.78885 8.23607H13.229L15 4Z" fill="#FFFF00"/>
            </svg>
            <!-- UK Flag -->
            <svg v-else class="w-5 h-3.5 rounded-sm shadow-xs border border-slate-300/10" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect width="30" height="20" fill="#012169"/>
              <path d="M0 0L30 20M30 0L0 20" stroke="#FFFFFF" stroke-width="3"/>
              <path d="M0 0L30 20M30 0L0 20" stroke="#C8102E" stroke-width="1.5"/>
              <path d="M15 0V20M0 10H30" stroke="#FFFFFF" stroke-width="5"/>
              <path d="M15 0V20M0 10H30" stroke="#C8102E" stroke-width="3"/>
            </svg>
          </button>

          <!-- Dropdown Options -->
          <div 
            v-if="isLangDropdownOpen" 
            class="absolute right-0 mt-2 top-full w-36 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-[100] dark:bg-[#080808] dark:border-[#1c1c1c] overflow-hidden"
          >
            <!-- Tiếng Việt -->
            <div 
              @click="handleSelectLang('vi')"
              class="px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#121212] cursor-pointer flex items-center gap-2 transition-colors font-semibold"
              :class="currentLang === 'vi' ? 'text-[#0ea5e9] bg-slate-50/50 dark:bg-[#121212]/50 font-bold' : ''"
            >
              <svg class="w-4 h-2.5 rounded-xs shrink-0 border border-red-700/10" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="30" height="20" fill="#DA251D"/>
                <path d="M15 4L16.2361 8.23607H20.6762L17.0863 10.8427L18.3224 15.0788L14.7325 12.4722L11.1427 15.0788L12.3788 10.8427L8.78885 8.23607H13.229L15 4Z" fill="#FFFF00"/>
              </svg>
              <span>Tiếng Việt</span>
              <svg v-if="currentLang === 'vi'" class="w-3 h-3 text-[#0ea5e9] ml-auto" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <!-- English -->
            <div 
              @click="handleSelectLang('en')"
              class="px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-[#121212] cursor-pointer flex items-center gap-2 transition-colors font-semibold"
              :class="currentLang === 'en' ? 'text-[#0ea5e9] bg-slate-50/50 dark:bg-[#121212]/50 font-bold' : ''"
            >
              <svg class="w-4 h-2.5 rounded-xs shrink-0 border border-slate-300/10" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="30" height="20" fill="#012169"/>
                <path d="M0 0L30 20M30 0L0 20" stroke="#FFFFFF" stroke-width="3"/>
                <path d="M0 0L30 20M30 0L0 20" stroke="#C8102E" stroke-width="1.5"/>
                <path d="M15 0V20M0 10H30" stroke="#FFFFFF" stroke-width="5"/>
                <path d="M15 0V20M0 10H30" stroke="#C8102E" stroke-width="3"/>
              </svg>
              <span>English</span>
              <svg v-if="currentLang === 'en'" class="w-3 h-3 text-[#0ea5e9] ml-auto" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Sub Navigation (Light Theme Tabs) -->
    <div
      v-if="subMenuItems.length > 0"
      class="flex items-center gap-1 xl:gap-1.5 h-11 bg-white border-b border-slate-200 px-2 xl:px-4 shrink-0 overflow-x-auto scrollbar-thin dark:bg-[#000000] dark:border-[#1c1c1c]"
    >
      <button
        v-for="item in subMenuItems"
        :key="item.name"
        @click="handleSubMenuClick(item)"
        class="flex items-center gap-1 px-2.5 py-1 xl:px-3 text-[11.5px] xl:text-[12px] rounded-full transition-all duration-200 cursor-pointer border whitespace-nowrap relative font-semibold"
        :class="item.active
          ? 'bg-[#bdecfe] text-gray-900 border-[#7dd3fc] dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800'
          : 'bg-transparent text-gray-900/70 border-transparent hover:bg-slate-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-[#121212] dark:hover:text-white'"
        :style="item.active && headerBgColor !== '#006bdb' ? { background: headerBgColor, color: isHeaderBgDark ? '#ffffff' : '#003d66', borderColor: 'transparent' } : {}"
      >
        <!-- Icons inline SVG for each type -->
        <svg v-if="item.icon === 'pie-chart'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
        </svg>
        <svg v-else-if="item.icon === 'grid'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
        <svg v-else-if="item.icon === 'check-circle'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'calendar-range'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        <svg v-else-if="item.icon === 'plus-circle'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'settings'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <svg v-else-if="item.icon === 'lock'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
        <svg v-else-if="item.icon === 'briefcase'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
        <svg v-else-if="item.icon === 'building'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
        <svg v-else-if="item.icon === 'bar-chart'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
        <svg v-else-if="item.icon === 'clock'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'search'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        <svg v-else-if="item.icon === 'dollar-sign'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <svg v-else-if="item.icon === 'printer'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        <svg v-else-if="item.icon === 'box'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
        <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /></svg>
        
        <span>{{ item.name }}</span>

        <!-- Notification Badge specifically for "D.S Công Việc" -->
        <span
          v-if="item.tab === 'shift-work'"
          class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center font-bold border border-white"
        >
          2
        </span>
      </button>
    </div>

    <!-- Main Content Area -->
    <main class="flex-1 min-h-0 min-w-0 overflow-hidden flex flex-col bg-white">
      <ActivityLogTab v-if="route.query.tab === 'history'" />
      <slot v-else />
    </main>
  </div>
</template>
