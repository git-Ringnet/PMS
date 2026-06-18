<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import {
  fetchMarkets,
  fetchCustomerSources,
  fetchBookers,
  fetchCompanies
} from '@/services/company-service'

const uiStore = useUiStore()

// State lists for dropdowns (fetched from API or fallback to mock)
const markets = ref([])
const customerSources = ref([])
const bookers = ref([])
const companies = ref([])

// Fallback lists if API data is empty
const defaultMarkets = [
  { id: 1, name: 'Online Travel Agency (OTA)' },
  { id: 2, name: 'B2B / Lữ hành' },
  { id: 3, name: 'Khách lẻ (Walk-in)' },
  { id: 4, name: 'Doanh nghiệp' }
]

const defaultCustomerSources = [
  { id: 1, name: 'Source code' },
  { id: 2, name: 'Agoda.com' },
  { id: 3, name: 'Booking.com' },
  { id: 4, name: 'Direct / Phone' }
]

const defaultBookers = [
  { id: 1, name: 'Guest 1', email: 'guest1@pms.com', phone: '0901234567' },
  { id: 2, name: 'Ivanova Daria', email: 'daria@pms.com', phone: '0907654321' },
  { id: 3, name: 'Kovaleva Natalia', email: 'natalia@pms.com', phone: '0908888888' },
  { id: 4, name: 'Thái Hòa', email: 'thaihoa@pms.com', phone: '0909999999' }
]

const defaultCompanies = [
  { id: 1, name: 'Travel Concierge' },
  { id: 2, name: 'Pegas' },
  { id: 3, name: 'Fun & Sun Travel' },
  { id: 4, name: 'Anex Tour' },
  { id: 5, name: 'Khách lẻ' }
]

// Tab management state
const tabs = ref([
  {
    id: 'GAL5490',
    title: 'Booking GAL5490',
    bookingName: 'THÁI HÒA',
    status: 'Allotment',
    checkIn: '2026-06-15',
    checkOut: '2026-06-19',
    deposit: 0,
    company: 'Travel Concierge',
    confirmDate: '2026-06-12',
    rooms: [
      {
        id: 1,
        type: 'Superior Triple',
        shape: 'Triple',
        roomNumber: '1',
        checkIn: '15/06/2026',
        checkOut: '19/06/2026',
        nights: 4,
        price: 500000,
        rateCode: 'Vui lòng chọn giá phòng',
        guestName: 'Guest 1',
        adults: 3,
        babies: 0,
        children: 0,
        breakfast: true,
        extraBedPrice: 0,
        hourly: false,
        hoursOut: '14:00',
        total: 2000000
      },
      {
        id: 2,
        type: 'Superior Triple',
        shape: 'Triple',
        roomNumber: '2',
        checkIn: '15/06/2026',
        checkOut: '19/06/2026',
        nights: 4,
        price: 500000,
        rateCode: 'Vui lòng chọn giá phòng',
        guestName: 'Guest 1',
        adults: 3,
        babies: 0,
        children: 0,
        breakfast: true,
        extraBedPrice: 0,
        hourly: false,
        hoursOut: '14:00',
        total: 2000000
      },
      {
        id: 3,
        type: 'Deluxe Double with Balcony',
        shape: 'Double',
        roomNumber: '3',
        checkIn: '15/06/2026',
        checkOut: '19/06/2026',
        nights: 4,
        price: 500000,
        rateCode: 'Vui lòng chọn giá phòng',
        guestName: 'Guest 1',
        adults: 2,
        babies: 0,
        children: 0,
        breakfast: true,
        extraBedPrice: 0,
        hourly: false,
        hoursOut: '14:00',
        total: 2000000
      }
    ]
  }
])

const activeTabId = ref('GAL5490')

// Modal and Form States
const isModalOpen = ref(false)
const isEditModal = ref(false)
const modalSubTab = ref('info') // info, shuttle, rooms
const nextBookingIdNum = ref(5491)

const modalForm = ref({
  id: '',
  bookingName: '',
  checkIn: '2026-06-15',
  checkOut: '2026-06-17',
  nights: 2,
  status: 'Guaranteed',
  confirmDate: '2026-06-16',
  company: '',
  paymentMethod: '',
  referenceCode: '',
  deposit: 0,
  seller: 'Demo',
  gitToggle: false,
  vatToggle: false,
  market: '',
  customerSource: '',
  bookerId: '',
  contactName: '',
  email: '',
  notes: '',
  specialRequests: ''
})

// UI States
const isEditing = ref(false)
const searchQuery = ref('')
const selectedRows = ref([])
const showStatusDropdown = ref(false)
const collapsedSections = ref({
  registrationStatus: false,
  superiorTriple: false,
  deluxeDouble: false
})

const selectRangeVal = computed({
  get() {
    if (!activeTab.value) return 0
    return activeTab.value.rooms.filter(r => selectedRows.value.includes(r.id)).length
  },
  set(val) {
    if (!activeTab.value) return
    const rooms = activeTab.value.rooms
    const countToSelect = Math.min(Number(val), rooms.length)
    const newSelected = []
    for (let i = 0; i < countToSelect; i++) {
      newSelected.push(rooms[i].id)
    }
    selectedRows.value = newSelected
  }
})

// Computeds
const activeTab = computed(() => {
  return tabs.value.find(t => t.id === activeTabId.value)
})

const filteredActiveRooms = computed(() => {
  if (!activeTab.value) return []
  if (!searchQuery.value) return activeTab.value.rooms
  const q = searchQuery.value.toLowerCase()
  return activeTab.value.rooms.filter(r => 
    r.type.toLowerCase().includes(q) || 
    r.guestName.toLowerCase().includes(q) || 
    (r.roomNumber && r.roomNumber.toLowerCase().includes(q))
  )
})

const roomsTotalSummary = computed(() => {
  if (!activeTab.value) return { count: 0, priceSum: 0, adults: 0, babies: 0, children: 0, extraBed: 0, total: 0 }
  let priceSum = 0
  let adults = 0
  let babies = 0
  let children = 0
  let extraBed = 0
  let total = 0

  activeTab.value.rooms.forEach(r => {
    priceSum += Number(r.price) || 0
    adults += Number(r.adults) || 0
    babies += Number(r.babies) || 0
    children += Number(r.children) || 0
    extraBed += Number(r.extraBedPrice) || 0
    total += Number(r.total) || 0
  })

  return {
    count: activeTab.value.rooms.length,
    priceSum,
    adults,
    babies,
    children,
    extraBed,
    total
  }
})

// Methods
onMounted(async () => {
  try {
    const [mRes, csRes, bRes, cRes] = await Promise.all([
      fetchMarkets().catch(() => ({ data: [] })),
      fetchCustomerSources().catch(() => ({ data: [] })),
      fetchBookers().catch(() => ({ data: [] })),
      fetchCompanies().catch(() => ({ data: [] }))
    ])
    
    markets.value = mRes.data?.length ? mRes.data : defaultMarkets
    customerSources.value = csRes.data?.length ? csRes.data : defaultCustomerSources
    bookers.value = bRes.data?.length ? bRes.data : defaultBookers
    companies.value = cRes.data?.length ? cRes.data : defaultCompanies
  } catch (err) {
    console.error('Error fetching registration options:', err)
    markets.value = defaultMarkets
    customerSources.value = defaultCustomerSources
    bookers.value = defaultBookers
    companies.value = defaultCompanies
  }
})

function handleTabClick(tabId) {
  activeTabId.value = tabId
}

function handleCloseTab(tabId, event) {
  event.stopPropagation()
  if (tabs.value.length === 1) {
    uiStore.showToast('Không thể đóng tab cuối cùng!', 'warning')
    return
  }
  const index = tabs.value.findIndex(t => t.id === tabId)
  tabs.value = tabs.value.filter(t => t.id !== tabId)
  if (activeTabId.value === tabId) {
    activeTabId.value = tabs.value[Math.max(0, index - 1)].id
  }
}

function handleAddTabClick() {
  isEditModal.value = false
  // Reset modal values and show modal
  modalForm.value = {
    id: `GAL${nextBookingIdNum.value}`,
    bookingName: '',
    checkIn: '2026-06-15',
    checkOut: '2026-06-17',
    nights: 2,
    status: 'Guaranteed',
    confirmDate: '2026-06-16',
    company: companies.value[0]?.name || 'Travel Concierge',
    paymentMethod: 'Chuyển khoản',
    referenceCode: '',
    deposit: 0,
    seller: 'Demo',
    gitToggle: false,
    vatToggle: false,
    market: markets.value[0]?.name || 'Online Travel Agency (OTA)',
    customerSource: customerSources.value[0]?.name || 'Source code',
    bookerId: bookers.value[0]?.id || '',
    contactName: bookers.value[0]?.name || '',
    email: bookers.value[0]?.email || '',
    notes: '',
    specialRequests: ''
  }
  modalSubTab.value = 'info'
  isModalOpen.value = true
}

function handleBookerChange() {
  const selected = bookers.value.find(b => b.id === Number(modalForm.value.bookerId))
  if (selected) {
    modalForm.value.contactName = selected.name
    modalForm.value.email = selected.email || ''
  }
}

function handleDateChange() {
  const checkInDate = new Date(modalForm.value.checkIn)
  const checkOutDate = new Date(modalForm.value.checkOut)
  if (!isNaN(checkInDate.getTime()) && !isNaN(checkOutDate.getTime())) {
    const diffTime = checkOutDate.getTime() - checkInDate.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    modalForm.value.nights = diffDays > 0 ? diffDays : 1
  }
}

function handleNightsChange() {
  const checkInDate = new Date(modalForm.value.checkIn)
  if (!isNaN(checkInDate.getTime()) && modalForm.value.nights > 0) {
    const nextDate = new Date(checkInDate)
    nextDate.setDate(checkInDate.getDate() + Number(modalForm.value.nights))
    modalForm.value.checkOut = nextDate.toISOString().split('T')[0]
  }
}

function handleSaveNewBooking() {
  if (!modalForm.value.bookingName.trim()) {
    uiStore.showToast('Vui lòng nhập tên đăng ký!', 'warning')
    return
  }

  if (isEditModal.value) {
    const tabIndex = tabs.value.findIndex(t => t.id === modalForm.value.id)
    if (tabIndex !== -1) {
      const tab = tabs.value[tabIndex]
      tab.bookingName = modalForm.value.bookingName.toUpperCase()
      tab.status = modalForm.value.status
      tab.checkIn = formatDateVi(modalForm.value.checkIn)
      tab.checkOut = formatDateVi(modalForm.value.checkOut)
      tab.deposit = modalForm.value.deposit || 0
      tab.company = modalForm.value.company || 'Khách lẻ'
      tab.confirmDate = formatDateVi(modalForm.value.confirmDate)
      tab.market = modalForm.value.market
      tab.customerSource = modalForm.value.customerSource
      tab.bookerId = modalForm.value.bookerId
      tab.contactName = modalForm.value.contactName
      tab.email = modalForm.value.email
      tab.notes = modalForm.value.notes
      tab.specialRequests = modalForm.value.specialRequests
      tab.paymentMethod = modalForm.value.paymentMethod
      tab.referenceCode = modalForm.value.referenceCode
      tab.seller = modalForm.value.seller
      tab.gitToggle = modalForm.value.gitToggle
      tab.vatToggle = modalForm.value.vatToggle
      
      // Update room dates and nights to match new booking dates
      tab.rooms.forEach(r => {
        r.checkIn = tab.checkIn
        r.checkOut = tab.checkOut
        r.nights = modalForm.value.nights
        r.total = r.price * modalForm.value.nights
      })
      
      uiStore.showToast(`Đã cập nhật thành công đăng ký ${tab.id}!`, 'success')
    }
    isModalOpen.value = false
    return
  }

  // Pre-populate mock room rows matching dates
  const newRooms = [
    {
      id: 1,
      type: 'Superior Triple',
      shape: 'Triple',
      roomNumber: '1',
      checkIn: formatDateVi(modalForm.value.checkIn),
      checkOut: formatDateVi(modalForm.value.checkOut),
      nights: modalForm.value.nights,
      price: 500000,
      rateCode: 'Vui lòng chọn giá phòng',
      guestName: 'Guest 1',
      adults: 3,
      babies: 0,
      children: 0,
      breakfast: true,
      extraBedPrice: 0,
      hourly: false,
      hoursOut: '14:00',
      total: 500000 * modalForm.value.nights
    },
    {
      id: 2,
      type: 'Deluxe Double with Balcony',
      shape: 'Double',
      roomNumber: '2',
      checkIn: formatDateVi(modalForm.value.checkIn),
      checkOut: formatDateVi(modalForm.value.checkOut),
      nights: modalForm.value.nights,
      price: 600000,
      rateCode: 'Vui lòng chọn giá phòng',
      guestName: 'Guest 2',
      adults: 2,
      babies: 0,
      children: 0,
      breakfast: true,
      extraBedPrice: 0,
      hourly: false,
      hoursOut: '14:00',
      total: 600000 * modalForm.value.nights
    }
  ]

  const newBooking = {
    id: modalForm.value.id,
    title: `Booking ${modalForm.value.id}`,
    bookingName: modalForm.value.bookingName.toUpperCase(),
    status: modalForm.value.status,
    checkIn: formatDateVi(modalForm.value.checkIn),
    checkOut: formatDateVi(modalForm.value.checkOut),
    deposit: modalForm.value.deposit || 0,
    company: modalForm.value.company || 'Khách lẻ',
    confirmDate: formatDateVi(modalForm.value.confirmDate),
    rooms: newRooms
  }

  tabs.value.push(newBooking)
  activeTabId.value = newBooking.id
  nextBookingIdNum.value++
  isModalOpen.value = false
  uiStore.showToast(`Đã tạo thành công đăng ký ${newBooking.id}!`, 'success')
}

function parseDateVi(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('/')
  if (parts.length === 3) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`
  }
  return dateStr
}

function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

function handleRowSelectAll(event) {
  if (event.target.checked) {
    selectedRows.value = activeTab.value.rooms.map(r => r.id)
  } else {
    selectedRows.value = []
  }
}

function handleRowSelect(roomId) {
  if (selectedRows.value.includes(roomId)) {
    selectedRows.value = selectedRows.value.filter(id => id !== roomId)
  } else {
    selectedRows.value.push(roomId)
  }
}

function triggerAction(actionName) {
  if (actionName === 'Sửa') {
    isEditing.value = true
    uiStore.showToast('Bạn có thể trực tiếp chỉnh sửa tên khách hàng hoặc mã giá phòng!', 'info')
  } else if (actionName === 'Lưu') {
    isEditing.value = false
    uiStore.showToast('Lưu thông tin đăng ký thành công!', 'success')
  } else if (actionName === 'Thông tin đăng ký') {
    isEditModal.value = true
    const tab = activeTab.value
    if (!tab) return
    
    const parsedCheckIn = parseDateVi(tab.checkIn)
    const parsedCheckOut = parseDateVi(tab.checkOut)
    let nights = 1
    const checkInDate = new Date(parsedCheckIn)
    const checkOutDate = new Date(parsedCheckOut)
    if (!isNaN(checkInDate.getTime()) && !isNaN(checkOutDate.getTime())) {
      const diffTime = checkOutDate.getTime() - checkInDate.getTime()
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
      nights = diffDays > 0 ? diffDays : 1
    }

    modalForm.value = {
      id: tab.id,
      bookingName: tab.bookingName,
      checkIn: parsedCheckIn,
      checkOut: parsedCheckOut,
      nights: nights,
      status: tab.status || 'Guaranteed',
      confirmDate: parseDateVi(tab.confirmDate) || new Date().toISOString().split('T')[0],
      company: tab.company || '',
      paymentMethod: tab.paymentMethod || '',
      referenceCode: tab.referenceCode || '',
      deposit: tab.deposit || 0,
      seller: tab.seller || 'Demo',
      gitToggle: tab.gitToggle || false,
      vatToggle: tab.vatToggle || false,
      market: tab.market || '',
      customerSource: tab.customerSource || '',
      bookerId: tab.bookerId || '',
      contactName: tab.contactName || '',
      email: tab.email || '',
      notes: tab.notes || '',
      specialRequests: tab.specialRequests || ''
    }
    
    modalSubTab.value = 'info'
    isModalOpen.value = true
  } else if (actionName === 'Nhân bản') {
    uiStore.showToast('Nhân bản thông tin đăng ký thành công!', 'success')
  } else if (actionName === 'Xóa') {
    uiStore.confirm({
      title: 'Xóa đăng ký',
      message: `Bạn có chắc chắn muốn xóa đăng ký này không?`,
      confirmText: 'Đồng ý',
      cancelText: 'Hủy'
    }).then(confirmed => {
      if (confirmed) {
        tabs.value = tabs.value.filter(t => t.id !== activeTabId.value)
        if (tabs.value.length === 0) {
          // recreate default
          tabs.value = [
            {
              id: 'GAL5490',
              title: 'Booking GAL5490',
              bookingName: 'THÁI HÒA',
              status: 'Allotment',
              checkIn: '15/06/2026',
              checkOut: '19/06/2026',
              deposit: 0,
              company: 'Travel Concierge',
              confirmDate: '12/06/2026',
              rooms: []
            }
          ]
        }
        activeTabId.value = tabs.value[0].id
        uiStore.showToast('Đã xóa đăng ký thành công!', 'success')
      }
    })
  } else {
    uiStore.showToast(`Tính năng "${actionName}" đang được thực hiện!`, 'info')
  }
}
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 animate-in select-none">
    
    <!-- DYNAMIC TABS HEADER BAR -->
    <div class="bg-slate-100 border-b border-slate-200 px-4 pt-1.5 flex items-end justify-between shrink-0">
      <div class="flex items-center gap-1.5 overflow-x-auto max-w-[85%] scrollbar-none">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="handleTabClick(tab.id)"
          class="flex items-center gap-2 px-4 py-2 text-xs font-black rounded-t-lg transition-all duration-200 border-t border-x cursor-pointer whitespace-nowrap"
          :class="tab.id === activeTabId
            ? 'bg-white text-sky-700 border-slate-200 border-b-white z-10 translate-y-[1px]'
            : 'bg-slate-100 text-slate-500 border-transparent hover:bg-slate-200/60'"
        >
          <span>{{ tab.title }}</span>
          <span
            @click="handleCloseTab(tab.id, $event)"
            class="w-3.5 h-3.5 rounded-full flex items-center justify-center text-[10px] text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors"
          >
            &times;
          </span>
        </button>

        <!-- ADD TAB BUTTON (+ Icon) -->
        <button
          @click="handleAddTabClick"
          class="w-7 h-7 mb-1 bg-white hover:bg-sky-50 text-sky-600 rounded-lg flex items-center justify-center cursor-pointer border border-slate-200 transition-all active:scale-95 shadow-xs"
          title="Tạo đăng ký mới"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </button>
      </div>

      <!-- Action Status Label -->
      <div v-if="activeTab" class="pb-1 text-[11px] font-black text-slate-400 uppercase tracking-wider hidden sm:block">
        Trạng thái: <span class="text-sky-600">{{ activeTab.status }}</span>
      </div>
    </div>

    <!-- MAIN TAB CONTENT PANEL -->
    <div v-if="activeTab" class="flex-1 flex flex-col overflow-y-auto">
      
      <!-- SUB-HEADER METADATA BAR -->
      <div class="bg-white border-b border-slate-200 px-5 py-3.5 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-bold text-slate-500 shadow-xs shrink-0 select-text">
        <div>
          Tên đăng ký: <span class="text-slate-900 font-extrabold text-sm uppercase">{{ activeTab.bookingName }}</span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Trạng thái: 
          <span 
            class="px-2 py-0.5 rounded text-[10px] font-black border uppercase"
            :class="activeTab.status === 'Allotment' ? 'bg-orange-50 text-orange-600 border-orange-100' : 'bg-sky-50 text-sky-600 border-sky-100'"
          >
            {{ activeTab.status }}
          </span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Ngày đến: <span class="text-slate-700 font-extrabold">{{ activeTab.checkIn }}</span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Ngày đi: <span class="text-slate-700 font-extrabold">{{ activeTab.checkOut }}</span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Đặt cọc: <span class="text-slate-700 font-extrabold">{{ activeTab.deposit.toLocaleString('vi-VN') }}</span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Công ty: <span class="text-slate-900 font-extrabold">{{ activeTab.company }}</span>
        </div>
        <div class="h-4 w-px bg-slate-200"></div>
        <div>
          Xác nhận: <span class="text-slate-700 font-extrabold">{{ activeTab.confirmDate }}</span>
        </div>
      </div>

      <!-- SEARCH & CONTROLS ACTION PANEL -->
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-4 shrink-0">
        <!-- Search bar with funnel filter icon -->
        <div class="flex items-center gap-2 max-w-sm w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 shadow-xs focus-within:ring-2 focus-within:ring-sky-500/20">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Tìm kiếm..." 
            class="border-none bg-transparent text-xs w-full focus:outline-none font-bold text-slate-700"
          />
          <button class="p-0.5 hover:bg-slate-100 rounded text-slate-400 cursor-pointer border-none bg-transparent">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
            </svg>
          </button>
        </div>

        <!-- Upper right action buttons -->
        <div class="flex items-center gap-2 flex-wrap text-slate-600">
          <button 
            @click="triggerAction('Thông tin đăng ký')"
            class="px-3.5 py-1.5 border border-slate-200 hover:border-slate-300 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-black shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
          >
            <span class="w-2 h-2 rounded-full bg-sky-600 block shadow-sm"></span>
            Thông tin đăng ký
          </button>
          
          <button 
            @click="triggerAction('Sửa')"
            class="px-3.5 py-1.5 border border-slate-200 hover:border-slate-300 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-black shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
            :disabled="isEditing"
          >
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
            </svg>
            Sửa
          </button>

          <button 
            @click="triggerAction('Lưu')"
            class="px-3.5 py-1.5 border border-slate-200 text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 rounded-lg text-xs font-black shadow-xs transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!isEditing"
          >
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75h1.5m9 0h-9" />
            </svg>
            Lưu
          </button>

          <button 
            @click="triggerAction('Nhân bản')"
            class="px-3 py-1.5 hover:bg-slate-200/50 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer border-none bg-transparent"
          >
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376A8.965 8.965 0 0012 12.75c-.131-1.178-.377-2.322-.75-3.414m9 9.375a9.015 9.015 0 01-1.5-.124M15 3.75H9v1.5H6.75c-.621 0-1.125.504-1.125 1.125v3.375c0 .621.504 1.125 1.125 1.125h1.5v1.5h1.5v-1.5h6v1.5h1.5v-1.5h1.5c.621 0 1.125-.504 1.125-1.125V7.875c0-.621-.504-1.125-1.125-1.125H15V3.75z" />
            </svg>
            Nhân bản
          </button>

          <button 
            @click="triggerAction('Xóa')"
            class="px-3 py-1.5 hover:bg-rose-50 text-rose-600 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer border-none bg-transparent"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
            Xóa
          </button>

          <button 
            @click="triggerAction('In')"
            class="px-3 py-1.5 hover:bg-slate-200/50 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer border-none bg-transparent"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.82l-.24 2.18m11.04-2.18l.24 2.18m0 0a2.25 2.25 0 104.44-.11L21 11.25M17.76 16h-.008m0 0a2.25 2.25 0 11-4.44-.11L13.5 11.25m-6.78 2.57l-.24-2.18m11.04 2.18l.24-2.18M6.72 13.82h10.56M6.72 13.82l-.24-2.18m11.04 2.18l.24-2.18m-11.04 0h11.04m-11.04 0l.24-2.18m10.56 2.18l-.24-2.18m0 0a2.25 2.25 0 10-4.44-.11L10.5 5.25m-3.78 2.57l.24-2.18" />
            </svg>
            In
            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </button>

          <button 
            @click="triggerAction('Thông báo')"
            class="px-3 py-1.5 hover:bg-slate-200/50 rounded-lg text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer border-none bg-transparent"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a9.04 9.04 0 01-1.67 1.127m2.1-3.23a.75.75 0 00-1.077-.107m0 0a.75.75 0 01-1.077-.107m1.282-3.187a.75.75 0 00-1.077-.107m0 0a.75.75 0 01-1.077-.107M14.857 8.528A9.04 9.04 0 0113.188 7.4m2.1 3.23a.75.75 0 00-1.077-.107m0 0a.75.75 0 01-1.077-.107m1.282-3.187a.75.75 0 00-1.077-.107M9 12H3.75M21 12h-5.25M12 9V3.75m0 16.5V15" />
            </svg>
            Thông báo
          </button>
        </div>
      </div>

      <!-- ROOMS DATA TABLE LIST -->
      <div class="flex-1 p-5 overflow-y-auto overflow-x-hidden">
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-x-auto min-h-[300px]">
          <table class="w-full text-left border-collapse text-[11px] table-fixed min-w-[1700px]">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-extrabold select-none whitespace-nowrap h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px]">
                  <input type="checkbox" @change="handleRowSelectAll" :checked="selectedRows.length === activeTab.rooms.length && activeTab.rooms.length > 0" />
                </th>
                <th class="p-2 border-r border-slate-200 w-[140px]">Loại phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[90px]">Dạng phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px]">Số phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[95px]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[95px]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[60px]">Đêm</th>
                <th class="p-2 border-r border-slate-200 text-right w-[95px]">Giá</th>
                <th class="p-2 border-r border-slate-200 w-[160px]">Mã giá phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px]">Giảm/tăng giá</th>
                <th class="p-2 border-r border-slate-200 w-[160px]">Tên khách</th>
                <th class="p-2 border-r border-slate-200 text-center w-[65px]">N.Lớn</th>
                <th class="p-2 border-r border-slate-200 text-center w-[65px]">Em bé</th>
                <th class="p-2 border-r border-slate-200 text-center w-[65px]">Trẻ em</th>
                <th class="p-2 border-r border-slate-200 text-center w-[130px]">Chi tiết ăn sáng trẻ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[75px]">Ăn sáng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px]">Thêm giường</th>
                <th class="p-2 border-r border-slate-200 text-right w-[115px]">Giá thêm giường</th>
                <th class="p-2 border-r border-slate-200 text-center w-[85px]">Ở theo giờ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px]">Yêu cầu đặc biệt</th>
                <th class="p-2 border-r border-slate-200 text-center w-[75px]">Giờ đi</th>
                <th class="p-2 text-right w-[120px] sticky right-0 bg-slate-50 border-l border-slate-200 z-20 shadow-[-2px_0_5px_rgba(0,0,0,0.02)]">Tổng cộng</th>
              </tr>
            </thead>
            <tbody class="font-bold text-slate-700 select-text">
              <!-- Collapsible Section: Tình trạng Đăng Ký (3) -->
              <tr class="bg-slate-100/60 border-b border-slate-200 font-extrabold h-9">
                <td class="p-2 border-r border-slate-200 text-center">
                  <span 
                    @click="collapsedSections.registrationStatus = !collapsedSections.registrationStatus" 
                    class="cursor-pointer text-slate-500 hover:text-slate-800 px-1 font-black text-sm select-none"
                  >
                    {{ collapsedSections.registrationStatus ? '+' : '-' }}
                  </span>
                </td>
                <td colspan="20" class="p-2">
                  <div class="flex items-center gap-2.5">
                    <input type="checkbox" :checked="selectRangeVal === roomsTotalSummary.count" disabled />
                    <span class="text-slate-800 text-[11px] font-black uppercase tracking-wider">Tình trạng: Đăng ký ({{ roomsTotalSummary.count }})</span>
                    <!-- Range Slider for room selection -->
                    <div class="flex items-center gap-2 ml-3">
                      <input 
                        type="range" 
                        min="0" 
                        :max="roomsTotalSummary.count" 
                        step="1" 
                        v-model.number="selectRangeVal"
                        class="w-32 h-1 bg-slate-300 rounded-lg appearance-none cursor-pointer accent-sky-500 focus:outline-none"
                      />
                      <span class="text-[10px] font-black text-sky-700 bg-sky-50 border border-sky-200 px-1.5 py-0.5 rounded shadow-xs select-none">
                        {{ selectRangeVal }} / {{ roomsTotalSummary.count }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="sticky right-0 bg-[#f1f5f9] border-l border-slate-200 z-10"></td>
              </tr>

              <!-- Nested Rows of Rooms -->
              <template v-if="!collapsedSections.registrationStatus">
                <!-- Group 1: Superior Triple -->
                <tr class="bg-slate-50/70 border-b border-slate-200 font-bold h-8">
                  <td class="p-2 border-r border-slate-200 text-center">
                    <span 
                      @click="collapsedSections.superiorTriple = !collapsedSections.superiorTriple" 
                      class="cursor-pointer text-slate-400 hover:text-slate-700 px-1 select-none"
                    >
                      {{ collapsedSections.superiorTriple ? '+' : '-' }}
                    </span>
                  </td>
                  <td colspan="20" class="p-2 text-slate-600 font-extrabold text-[10px]">
                    Superior Triple ({{ activeTab.rooms.filter(r => r.type === 'Superior Triple').length }})
                  </td>
                  <td class="sticky right-0 bg-[#f8fafc] border-l border-slate-200 z-10"></td>
                </tr>

                <template v-if="!collapsedSections.superiorTriple">
                  <tr 
                    v-for="room in filteredActiveRooms.filter(r => r.type === 'Superior Triple')" 
                    :key="room.id"
                    class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors h-9 group"
                  >
                    <td class="p-2 border-r border-slate-200 text-center">
                      <input type="checkbox" :checked="selectedRows.includes(room.id)" @change="handleRowSelect(room.id)" />
                    </td>
                    <td class="p-2 border-r border-slate-200 text-slate-800 font-black">{{ room.type }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.shape }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-800 font-black">{{ room.roomNumber || '-' }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.checkIn }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.checkOut }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.nights }}</td>
                    <td class="p-2 border-r border-slate-200 text-right text-slate-900 font-black">{{ room.price.toLocaleString('vi-VN') }}</td>
                    <td class="p-2 border-r border-slate-200">
                      <select 
                        v-if="isEditing" 
                        v-model="room.rateCode" 
                        class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[10px] w-full font-bold focus:outline-none"
                      >
                        <option>Vui lòng chọn giá phòng</option>
                        <option>Standard Rate</option>
                        <option>Promo Rate</option>
                      </select>
                      <span v-else class="text-slate-400 font-semibold">{{ room.rateCode }}</span>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-right text-slate-500">-</td>
                    <td class="p-2 border-r border-slate-200">
                      <input 
                        v-if="isEditing" 
                        type="text" 
                        v-model="room.guestName" 
                        class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[10px] w-full font-bold focus:outline-none" 
                      />
                      <span v-else class="text-slate-800 font-extrabold">{{ room.guestName }}</span>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.adults }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.babies }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.children }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Chi tiết</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <label class="relative inline-flex items-center cursor-pointer scale-75">
                        <input type="checkbox" v-model="room.breakfast" class="sr-only peer">
                        <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Chi tiết</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-right">{{ room.extraBedPrice.toLocaleString('vi-VN') }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <label class="relative inline-flex items-center cursor-pointer scale-75">
                        <input type="checkbox" v-model="room.hourly" class="sr-only peer">
                        <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Yêu cầu đặc biệt</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.hoursOut }}</td>
                    <td class="p-2 text-right text-slate-900 font-black sticky right-0 bg-white group-hover:bg-slate-50/50 border-l border-slate-200 z-10">{{ room.total.toLocaleString('vi-VN') }}</td>
                  </tr>
                </template>

                <!-- Group 2: Deluxe Double with Balcony -->
                <tr class="bg-slate-50/70 border-b border-slate-200 font-bold h-8">
                  <td class="p-2 border-r border-slate-200 text-center">
                    <span 
                      @click="collapsedSections.deluxeDouble = !collapsedSections.deluxeDouble" 
                      class="cursor-pointer text-slate-400 hover:text-slate-700 px-1 select-none"
                    >
                      {{ collapsedSections.deluxeDouble ? '+' : '-' }}
                    </span>
                  </td>
                  <td colspan="20" class="p-2 text-slate-600 font-extrabold text-[10px]">
                    Deluxe Double with Balcony ({{ activeTab.rooms.filter(r => r.type === 'Deluxe Double with Balcony').length }})
                  </td>
                  <td class="sticky right-0 bg-[#f8fafc] border-l border-slate-200 z-10"></td>
                </tr>

                <template v-if="!collapsedSections.deluxeDouble">
                  <tr 
                    v-for="room in filteredActiveRooms.filter(r => r.type === 'Deluxe Double with Balcony')" 
                    :key="room.id"
                    class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors h-9 group"
                  >
                    <td class="p-2 border-r border-slate-200 text-center">
                      <input type="checkbox" :checked="selectedRows.includes(room.id)" @change="handleRowSelect(room.id)" />
                    </td>
                    <td class="p-2 border-r border-slate-200 text-slate-800 font-black">{{ room.type }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.shape }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-800 font-black">{{ room.roomNumber || '-' }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.checkIn }}</td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.checkOut }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.nights }}</td>
                    <td class="p-2 border-r border-slate-200 text-right text-slate-900 font-black">{{ room.price.toLocaleString('vi-VN') }}</td>
                    <td class="p-2 border-r border-slate-200">
                      <select 
                        v-if="isEditing" 
                        v-model="room.rateCode" 
                        class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[10px] w-full font-bold focus:outline-none"
                      >
                        <option>Vui lòng chọn giá phòng</option>
                        <option>Standard Rate</option>
                        <option>Promo Rate</option>
                      </select>
                      <span v-else class="text-slate-400 font-semibold">{{ room.rateCode }}</span>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-right text-slate-500">-</td>
                    <td class="p-2 border-r border-slate-200">
                      <input 
                        v-if="isEditing" 
                        type="text" 
                        v-model="room.guestName" 
                        class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[10px] w-full font-bold focus:outline-none" 
                      />
                      <span v-else class="text-slate-800 font-extrabold">{{ room.guestName }}</span>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.adults }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.babies }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">{{ room.children }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Chi tiết</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <label class="relative inline-flex items-center cursor-pointer scale-75">
                        <input type="checkbox" v-model="room.breakfast" class="sr-only peer">
                        <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Chi tiết</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-right">{{ room.extraBedPrice.toLocaleString('vi-VN') }}</td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <label class="relative inline-flex items-center cursor-pointer scale-75">
                        <input type="checkbox" v-model="room.hourly" class="sr-only peer">
                        <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center">
                      <button class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-black cursor-pointer">Yêu cầu đặc biệt</button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center text-slate-500">{{ room.hoursOut }}</td>
                    <td class="p-2 text-right text-slate-900 font-black sticky right-0 bg-white group-hover:bg-slate-50/50 border-l border-slate-200 z-10">{{ room.total.toLocaleString('vi-VN') }}</td>
                  </tr>
                </template>
              </template>
            </tbody>
            <!-- Footer row with summaries -->
            <tfoot class="border-t border-slate-300 font-black text-slate-800 bg-slate-100 select-none">
              <tr class="h-9">
                <td class="p-2 border-r border-slate-200"></td>
                <td colspan="6" class="p-2 border-r border-slate-200 text-slate-900 font-black">
                  Tổng: {{ roomsTotalSummary.count }}
                </td>
                <td class="p-2 border-r border-slate-200 text-right">
                  {{ roomsTotalSummary.priceSum.toLocaleString('vi-VN') }}
                </td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200 text-right">-</td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200 text-center">{{ roomsTotalSummary.adults }}</td>
                <td class="p-2 border-r border-slate-200 text-center">{{ roomsTotalSummary.babies }}</td>
                <td class="p-2 border-r border-slate-200 text-center">{{ roomsTotalSummary.children }}</td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200 text-right">
                  {{ roomsTotalSummary.extraBed.toLocaleString('vi-VN') }}
                </td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200 text-center">-</td>
                <td class="p-2 text-right text-sky-700 font-black text-xs sticky right-0 bg-[#f1f5f9] border-l border-slate-200 z-10 shadow-[-2px_0_5px_rgba(0,0,0,0.02)]">
                  {{ roomsTotalSummary.total.toLocaleString('vi-VN') }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>

    <!-- MOCKUP CREATE REGISTRATION MODAL (Image 2 Match) -->
    <Teleport to="body">
      <div 
        v-if="isModalOpen" 
        class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
      >
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col max-h-[90vh]">
          
          <!-- MODAL HEADER -->
          <div class="bg-sky-500 text-white px-5 py-3.5 flex items-center justify-between shrink-0 font-bold">
            <span class="text-sm font-black tracking-wide uppercase">
              {{ isEditModal ? 'Thông tin đăng ký' : 'Tạo đăng ký' }}
            </span>
            <button 
              @click="isModalOpen = false" 
              class="text-white hover:text-sky-100 text-xl font-black transition-colors cursor-pointer border-none bg-transparent"
            >
              &times;
            </button>
          </div>

          <!-- MODAL CONTENT SCROLL -->
          <div class="flex-1 p-5 overflow-y-auto flex flex-col gap-5 bg-slate-50">
            
            <!-- Upper Form Grid System (Image 2 style) -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col gap-4">
              <!-- ROW 1 -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-4">
                <!-- Mã đăng ký -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Mã đăng ký</label>
                  <input 
                    type="text" 
                    v-model="modalForm.id" 
                    disabled
                    class="bg-slate-100 border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-500 focus:outline-none cursor-not-allowed"
                  />
                </div>
                <!-- Tên đăng ký (yellow bg input) -->
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Tên đăng ký</label>
                  <input 
                    type="text" 
                    v-model="modalForm.bookingName" 
                    placeholder="Nhập tên đăng ký..."
                    class="bg-yellow-50 border border-yellow-200 rounded px-2.5 py-1.5 text-xs font-black text-slate-800 uppercase focus:outline-none focus:ring-1 focus:ring-yellow-400"
                  />
                </div>
                <!-- Ngày đến - Ngày đi -->
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Ngày đến - Ngày đi</label>
                  <div class="flex items-center gap-1.5">
                    <input 
                      type="date" 
                      v-model="modalForm.checkIn" 
                      @change="handleDateChange"
                      class="border border-slate-200 rounded px-2 py-1 text-xs font-bold text-slate-700 w-full focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                    <span class="text-slate-400 text-xs font-bold">~</span>
                    <input 
                      type="date" 
                      v-model="modalForm.checkOut" 
                      @change="handleDateChange"
                      class="border border-slate-200 rounded px-2 py-1 text-xs font-bold text-slate-700 w-full focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                  </div>
                </div>
                <!-- Đêm (yellow bg input) -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Đêm</label>
                  <div class="relative">
                    <input 
                      type="number" 
                      v-model="modalForm.nights" 
                      @input="handleNightsChange"
                      min="1"
                      class="bg-yellow-50 border border-yellow-200 rounded px-2.5 py-1.5 text-xs font-black text-slate-800 w-full focus:outline-none focus:ring-1 focus:ring-yellow-400 pr-6"
                    />
                    <!-- Moon Icon inside input right -->
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                      </svg>
                    </span>
                  </div>
                </div>
              </div>

              <!-- ROW 2 -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-4 items-end">
                <!-- CÔNG TY (yellow bg select with + blue button) -->
                <div class="flex flex-col gap-1 md:col-span-2">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Công ty</label>
                  <div class="flex items-center gap-1.5">
                    <select 
                      v-model="modalForm.company" 
                      class="bg-yellow-50 border border-yellow-200 rounded px-2.5 py-1.5 text-xs font-black text-slate-800 flex-1 focus:outline-none focus:ring-1 focus:ring-yellow-400"
                    >
                      <option value="">Công ty</option>
                      <option v-for="c in companies" :key="c.id" :value="c.name">{{ c.name }}</option>
                    </select>
                    <button class="w-7 h-7 bg-sky-100 hover:bg-sky-200 text-sky-600 rounded flex items-center justify-center cursor-pointer border-none transition-colors shrink-0">
                      <span class="font-black text-base">+</span>
                    </button>
                  </div>
                </div>
                <!-- Phương thức thanh toán -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Phương thức thanh toán</label>
                  <select 
                    v-model="modalForm.paymentMethod" 
                    class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  >
                    <option value="">Phương thức thanh toán</option>
                    <option value="Chuyển khoản">Chuyển khoản</option>
                    <option value="Tiền mặt">Tiền mặt</option>
                    <option value="Thẻ tín dụng">Thẻ tín dụng</option>
                  </select>
                </div>
                <!-- Mã tham chiếu -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Mã tham chiếu</label>
                  <input 
                    type="text" 
                    v-model="modalForm.referenceCode" 
                    placeholder="Mã tham chiếu"
                    class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  />
                </div>
                <!-- Đặt cọc -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Đặt cọc</label>
                  <div class="relative">
                    <input 
                      type="number" 
                      v-model="modalForm.deposit" 
                      class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 w-full focus:outline-none focus:ring-1 focus:ring-sky-500 pr-7"
                    />
                    <!-- Money bag icon inside input -->
                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182.553-.44 1.278-.659 2.003-.659.725 0 1.45.22 2.003.659m-6 2.015h12M12 6V3.75m0 16.5V18" />
                      </svg>
                    </span>
                  </div>
                </div>
                <!-- Người bán -->
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Người bán</label>
                  <select 
                    v-model="modalForm.seller" 
                    class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  >
                    <option value="Demo">Demo</option>
                    <option value="Lễ tân Ca 1">Lễ tân Ca 1</option>
                    <option value="Lễ tân Ca 2">Lễ tân Ca 2</option>
                    <option value="Admin">Admin</option>
                  </select>
                </div>
              </div>

              <!-- RIGHT ACTIONS TOGGLES & CONTEXT MENU STRIP -->
              <div class="flex justify-between items-center mt-3 pt-3 border-t border-slate-100 flex-wrap gap-4">
                <!-- Left: Status select, confirm date -->
                <div class="flex items-center gap-4 flex-wrap">
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase">Trạng thái</span>
                    <select 
                      v-model="modalForm.status" 
                      class="border border-slate-200 rounded px-2 py-1 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    >
                      <option value="Guaranteed">Guaranteed</option>
                      <option value="Allotment">Allotment</option>
                      <option value="Tentative">Tentative</option>
                    </select>
                  </div>

                  <div class="flex items-center gap-2">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase">Xác nhận</span>
                    <div class="flex items-center gap-1">
                      <input 
                        type="date" 
                        v-model="modalForm.confirmDate" 
                        class="border border-slate-200 rounded px-2 py-0.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                      />
                      <button class="p-1 hover:bg-slate-100 rounded text-slate-400 cursor-pointer border-none bg-transparent" title="Sao chép ngày">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376A8.965 8.965 0 0012 12.75c-.131-1.178-.377-2.322-.75-3.414m9 9.375a9.015 9.015 0 01-1.5-.124M15.75 6.125h.008v.008h-.008V6.125z" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Right: GIT, VAT, icons -->
                <div class="flex items-center gap-4 text-slate-500">
                  <div class="flex items-center gap-1.5">
                    <label class="relative inline-flex items-center cursor-pointer scale-90">
                      <input type="checkbox" v-model="modalForm.gitToggle" class="sr-only peer">
                      <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                    <span class="text-xs font-black">GIT</span>
                  </div>

                  <div class="flex items-center gap-1.5">
                    <label class="relative inline-flex items-center cursor-pointer scale-90">
                      <input type="checkbox" v-model="modalForm.vatToggle" class="sr-only peer">
                      <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                    <span class="text-xs font-black">VAT</span>
                  </div>

                  <div class="h-5 w-px bg-slate-200"></div>

                  <button class="p-1 hover:bg-slate-100 rounded text-slate-500 cursor-pointer border-none bg-transparent" title="Lịch sử">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>

                  <button class="p-1 hover:bg-slate-100 rounded text-slate-500 cursor-pointer border-none bg-transparent" title="In nháp">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.82l-.24 2.18m11.04-2.18l.24 2.18m0 0a2.25 2.25 0 104.44-.11L21 11.25M17.76 16h-.008m0 0a2.25 2.25 0 11-4.44-.11L13.5 11.25m-6.78 2.57l-.24-2.18m11.04 2.18l.24-2.18M6.72 13.82h10.56M6.72 13.82l-.24-2.18m11.04 2.18l.24-2.18m-11.04 0h11.04m-11.04 0l.24-2.18m10.56 2.18l-.24-2.18m0 0a2.25 2.25 0 10-4.44-.11L10.5 5.25m-3.78 2.57l.24-2.18" />
                    </svg>
                  </button>

                  <button class="p-1 hover:bg-slate-100 rounded text-slate-500 cursor-pointer border-none bg-transparent">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- TAB LIST INSIDE MODAL -->
            <div class="flex items-center gap-1 border-b border-slate-200 shrink-0">
              <button 
                @click="modalSubTab = 'info'"
                class="px-4 py-2 text-xs font-black border-b-2 transition-colors cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'info' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
              >
                Thông tin chung
              </button>
              <button 
                @click="modalSubTab = 'shuttle'"
                class="px-4 py-2 text-xs font-black border-b-2 transition-colors cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'shuttle' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
              >
                Thông tin đưa đón
              </button>
              <button 
                @click="modalSubTab = 'rooms'"
                class="px-4 py-2 text-xs font-black border-b-2 transition-colors cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'rooms' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
              >
                Lấy phòng
              </button>
            </div>

            <!-- SUB-TAB CONTENT -->
            <!-- Tab 1: Thông tin chung -->
            <div v-if="modalSubTab === 'info'" class="grid grid-cols-1 lg:grid-cols-4 gap-5">
              <!-- Đối tượng -->
              <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-col gap-3">
                <span class="text-xs font-black text-slate-600 uppercase border-b border-slate-100 pb-1.5">Đối tượng</span>
                
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Thị trường</label>
                  <select 
                    v-model="modalForm.market" 
                    class="bg-yellow-50 border border-yellow-200 rounded px-2 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-1 focus:ring-yellow-400"
                  >
                    <option value="">Thị trường</option>
                    <option v-for="m in markets" :key="m.id" :value="m.name">{{ m.name }}</option>
                  </select>
                </div>

                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Nguồn khách</label>
                  <select 
                    v-model="modalForm.customerSource" 
                    class="bg-yellow-50 border border-yellow-200 rounded px-2 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-1 focus:ring-yellow-400"
                  >
                    <option value="">Nguồn khách</option>
                    <option v-for="s in customerSources" :key="s.id" :value="s.name">{{ s.name }}</option>
                  </select>
                </div>
              </div>

              <!-- Liên hệ -->
              <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-col gap-3">
                <span class="text-xs font-black text-slate-600 uppercase border-b border-slate-100 pb-1.5">Liên hệ</span>

                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Người đặt phòng</label>
                  <div class="flex items-center gap-1">
                    <select 
                      v-model="modalForm.bookerId" 
                      @change="handleBookerChange"
                      class="border border-slate-200 rounded px-2 py-1.5 text-xs font-bold text-slate-700 flex-1 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    >
                      <option value="">Người đặt phòng</option>
                      <option v-for="b in bookers" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                    <!-- Blue user profile search icon button -->
                    <button class="w-7 h-7 bg-sky-100 hover:bg-sky-200 text-sky-600 rounded flex items-center justify-center cursor-pointer border-none transition-colors shrink-0">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Liên hệ</label>
                  <input 
                    type="text" 
                    v-model="modalForm.contactName" 
                    placeholder="Liên hệ"
                    class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  />
                </div>

                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-extrabold text-slate-400 uppercase">Email</label>
                  <input 
                    type="email" 
                    v-model="modalForm.email" 
                    placeholder="Email"
                    class="border border-slate-200 rounded px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  />
                </div>
              </div>

              <!-- Ghi chú -->
              <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-col gap-3">
                <span class="text-xs font-black text-slate-600 uppercase border-b border-slate-100 pb-1.5">Ghi chú</span>
                <textarea 
                  v-model="modalForm.notes" 
                  placeholder="Ghi chú" 
                  rows="6"
                  class="border border-slate-200 rounded-lg p-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500 w-full resize-none flex-1"
                ></textarea>
              </div>

              <!-- Yêu cầu đặc biệt (BK Confirm) -->
              <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-xs flex flex-col gap-3">
                <span class="text-xs font-black text-slate-600 uppercase border-b border-slate-100 pb-1.5">Yêu cầu đặc biệt (BK Confirm)</span>
                <textarea 
                  v-model="modalForm.specialRequests" 
                  placeholder="Yêu cầu đặc biệt (BK Confirm)" 
                  rows="6"
                  class="border border-slate-200 rounded-lg p-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500 w-full resize-none flex-1"
                ></textarea>
              </div>
            </div>

            <!-- Tab 2: Thông tin đưa đón -->
            <div v-else-if="modalSubTab === 'shuttle'" class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs text-center text-slate-400 py-10 font-bold">
              Thông tin đưa đón xe khách (Đang phát triển hoặc chưa có dữ liệu)
            </div>

            <!-- Tab 3: Lấy phòng -->
            <div v-else-if="modalSubTab === 'rooms'" class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs text-center text-slate-400 py-10 font-bold">
              Lấy phòng trực tiếp (Đang phát triển hoặc chưa có dữ liệu)
            </div>

          </div>

          <!-- MODAL FOOTER -->
          <div class="bg-slate-100 px-5 py-3 border-t border-slate-200 flex items-center justify-end shrink-0">
            <button 
              @click="handleSaveNewBooking"
              class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-black shadow-md transition-all active:scale-95 flex items-center gap-1.5 cursor-pointer border-none"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75h1.5m9 0h-9" />
              </svg>
              Lưu
            </button>
          </div>

        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.animate-in {
  animation: fadeIn 0.15s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(2px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
