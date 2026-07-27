<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Plus,
  PlusSquare,
  Scissors,
  ArrowRightLeft,
  Layers,
  Printer,
  FileText,
  FileX,
  Trash2,
  RotateCcw,
  CreditCard,
  Filter,
  LogOut,
  Search,
  Calendar,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Inbox
} from '@lucide/vue'
import { fetchBookings } from '@/services/booking-service'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

// Sidebar collapse state
const isSidebarCollapsed = ref(false)
const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// UI State
const searchQuery = ref('')
const registerFilter = ref('current')
const showAllGuestsInRoom = ref(false)

const isNoPost = ref(false)
const isRegistered = ref(true)

const selectedGuest = ref('Guest 1')
const roomNumber = ref('')
const noteText = ref('')
const activeFolioTab = ref('A')

// Dropdown In hóa đơn state
const showInvoiceMenu = ref(false)

// Modal states
import AddServiceModal from './components/AddServiceModal.vue'
import TransferServiceModal from './components/TransferServiceModal.vue'
import QuickTransferBillModal from './components/QuickTransferBillModal.vue'
import PrepaymentModal from './components/PrepaymentModal.vue'
import PaymentModal from './components/PaymentModal.vue'
import FilterServiceModal from './components/FilterServiceModal.vue'
const showAddServiceModal = ref(false)
const showTransferServiceModal = ref(false)
const showQuickTransferBillModal = ref(false)
const showPrepaymentModal = ref(false)
const showPaymentModal = ref(false)
const showFilterServiceModal = ref(false)

// State dữ liệu thực từ CSDL
const allBookingsList = ref([])
const displayedBookingsList = ref([])
const selectedBooking = ref(null)
const selectedRoomItem = ref(null)
const isLoading = ref(true)
const showSearchDropdown = ref(false)
const searchContainerRef = ref(null)

function formatDate(dateStr) {
  if (!dateStr) return '-- / -- / ----'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day} / ${month} / ${year}`
}

function formatMoney(num) {
  if (!num) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}

const loadCheckoutBookings = async () => {
  isLoading.value = true
  try {
    const res = await fetchBookings({ status: '0,1' })
    const list = res.data?.data || res.data || []

    const formatted = []
    list.forEach(b => {
      const code = b.booking_code || b.code || `GAL${b.id}`
      const mainGuestName = (
        (b.booking_name && b.booking_name.trim()) ||
        (b.guest_name && b.guest_name.trim()) ||
        (b.contact_name && b.contact_name.trim()) ||
        (b.customer?.full_name && b.customer.full_name.trim()) ||
        (b.customer?.name && b.customer.name.trim()) ||
        (b.company?.name && b.company.name.trim()) ||
        (b.booker?.full_name && b.booker.full_name.trim()) ||
        'Khách lẻ'
      )

      const roomItems = []
      let totalBookingService = 0

      if (b.booking_rooms && b.booking_rooms.length > 0) {
        b.booking_rooms.forEach(r => {
          const roomNo = r.room_number || r.room || (r.room && r.room.room_number) || ''
          
          const roomGuests = []
          if (r.guest_name && r.guest_name.trim()) {
            roomGuests.push(r.guest_name.trim())
          }
          if (r.guests && Array.isArray(r.guests) && r.guests.length > 0) {
            r.guests.forEach(g => {
              const gName = g.guest?.full_name || g.full_name || (g.first_name ? `${g.first_name} ${g.last_name || ''}`.trim() : '')
              if (gName && !roomGuests.includes(gName)) {
                roomGuests.push(gName)
              }
            })
          }
          if (roomGuests.length === 0) {
            roomGuests.push(mainGuestName)
          }

          let totalSvc = 0
          if (r.services && r.services.length > 0) {
            totalSvc = r.services.reduce((acc, s) => acc + (Number(s.amount) || Number(s.price) || 0), 0)
          }
          totalBookingService += totalSvc

          roomItems.push({
            id: `R${r.id || b.id}`,
            roomId: r.id,
            code: code,
            roomNumber: roomNo,
            guestName: roomGuests[0],
            allGuests: roomGuests,
            serviceAmount: totalSvc,
            paidAmount: 0,
            checked: false,
            rawRoom: r
          })
        })
      }

      formatted.push({
        id: `B${b.id}`,
        bookingId: b.id,
        code: code,
        name: mainGuestName, // Tên nhóm / Tên booking
        totalService: totalBookingService,
        paidAmount: Number(b.paid_amount) || 0,
        arrivalDate: b.arrival_date || '',
        departureDate: b.departure_date || '',
        note: b.note || '',
        checked: false,
        roomItems: roomItems,
        rawBooking: b
      })
    })

    // Lưu toàn bộ danh sách cho ô Tìm kiếm Popup
    allBookingsList.value = formatted

    // Ban đầu TRỐNG HOÀN TOÀN (chưa chọn gì cả)
    displayedBookingsList.value = []
    selectedBooking.value = null
    selectedRoomItem.value = null
    roomNumber.value = ''
    selectedGuest.value = ''
    noteText.value = ''
  } catch (err) {
    console.error('Lỗi khi nạp danh sách booking cho Checkout:', err)
  } finally {
    isLoading.value = false
  }
}

const toggleBookingCheck = (b) => {
  if (b.roomItems) {
    b.roomItems.forEach(r => {
      r.checked = b.checked
    })
  }
}

const servicesList = computed(() => {
  if (!selectedBooking.value) return []

  const services = []

  if (selectedRoomItem.value && selectedRoomItem.value.rawRoom) {
    const rawR = selectedRoomItem.value.rawRoom
    if (rawR.services && Array.isArray(rawR.services)) {
      rawR.services.forEach((s, idx) => {
        services.push({
          id: s.id || `S${idx}`,
          dateTime: formatDate(s.created_at || s.service_date || new Date()),
          serviceName: s.service_name || s.name || (s.hotel_service && s.hotel_service.name) || 'Dịch vụ buồng phòng',
          description: s.note || s.description || 'Phát sinh dịch vụ phòng',
          department: s.department || 'Buồng phòng',
          amount: Number(s.amount) || Number(s.price) || 0,
          quantity: Number(s.quantity) || Number(s.qty) || 1,
          unit: s.unit || 'Lần',
          paymentCode: s.payment_code || `TT${s.id || idx}`,
          folio: s.folio || 'A',
          tax: Number(s.tax) || 0,
          serviceCharge: Number(s.service_charge) || 0,
          invoiceCode: s.invoice_code || '',
          vatNo: s.vat_no || '',
          accounting: s.accounting || 'Đã ghi',
          userName: s.user_name || 'Admin'
        })
      })
    }
  } else if (selectedBooking.value.roomItems) {
    selectedBooking.value.roomItems.forEach(rItem => {
      const rawR = rItem.rawRoom
      if (rawR && rawR.services && Array.isArray(rawR.services)) {
        rawR.services.forEach((s, idx) => {
          services.push({
            id: s.id || `S${rItem.id}-${idx}`,
            dateTime: formatDate(s.created_at || s.service_date || new Date()),
            serviceName: s.service_name || s.name || (s.hotel_service && s.hotel_service.name) || 'Dịch vụ buồng phòng',
            description: s.note || s.description || `Phòng ${rItem.roomNumber}`,
            department: s.department || 'Buồng phòng',
            amount: Number(s.amount) || Number(s.price) || 0,
            quantity: Number(s.quantity) || Number(s.qty) || 1,
            unit: s.unit || 'Lần',
            paymentCode: s.payment_code || `TT${s.id || idx}`,
            folio: s.folio || 'A',
            tax: Number(s.tax) || 0,
            serviceCharge: Number(s.service_charge) || 0,
            invoiceCode: s.invoice_code || '',
            vatNo: s.vat_no || '',
            accounting: s.accounting || 'Đã ghi',
            userName: s.user_name || 'Admin'
          })
        })
      }
    })
  }

  return services
})

const totalServiceAmount = computed(() => {
  return servicesList.value.reduce((acc, s) => acc + (s.amount * s.quantity), 0)
})

const paymentsList = computed(() => {
  if (!selectedBooking.value) return []

  const payments = []
  const rawB = selectedBooking.value.rawBooking

  if (rawB && rawB.payments && Array.isArray(rawB.payments) && rawB.payments.length > 0) {
    rawB.payments.forEach((p, idx) => {
      payments.push({
        id: p.id || `P${idx}`,
        dateTime: formatDate(p.created_at || p.payment_date || new Date()),
        department: p.department || 'Lễ tân',
        description: p.description || p.note || 'Thanh toán đặt cọc / Tiền phòng',
        paymentMethod: p.payment_method?.name || p.payment_method || 'Tiền mặt',
        amount: Number(p.amount) || 0,
        unit: p.currency || 'VND',
        folio: p.folio || 'A',
        paymentCode: p.code || p.payment_code || `PT${p.id || idx}`,
        isDeleted: p.deleted_at ? 'Có' : 'Không',
        vatNo: p.vat_no || '',
        accounting: p.accounting || 'Đã thu',
        userName: p.user_name || 'Admin'
      })
    })
  } else if (selectedBooking.value.paidAmount > 0) {
    payments.push({
      id: `P-fallback`,
      dateTime: formatDate(selectedBooking.value.arrivalDate),
      department: 'Lễ tân',
      description: 'Thanh toán đặt cọc phòng',
      paymentMethod: 'Chuyển khoản',
      amount: selectedBooking.value.paidAmount,
      unit: 'VND',
      folio: 'A',
      paymentCode: `PT${selectedBooking.value.bookingId}`,
      isDeleted: 'Không',
      vatNo: '',
      accounting: 'Đã thu',
      userName: 'Admin'
    })
  }

  return payments
})

const totalPaymentAmount = computed(() => {
  return paymentsList.value.reduce((acc, p) => acc + p.amount, 0)
})

const selectBookingHeader = (b) => {
  selectedBooking.value = b
  noteText.value = b.note || ''
  if (b.roomItems && b.roomItems.length > 0) {
    selectedRoomItem.value = b.roomItems[0]
    roomNumber.value = b.roomItems[0].roomNumber
    selectedGuest.value = b.roomItems[0].guestName
  } else {
    selectedRoomItem.value = null
    roomNumber.value = ''
    selectedGuest.value = b.name
  }
}

const selectRoomItemRow = (b, r, specificGuest = null) => {
  selectedBooking.value = b
  selectedRoomItem.value = r
  noteText.value = b.note || ''
  roomNumber.value = r.roomNumber
  selectedGuest.value = specificGuest || r.guestName
}

const filteredSearchBookings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return allBookingsList.value
  return allBookingsList.value.filter(b => {
    const matchCode = b.code && b.code.toLowerCase().includes(q)
    const matchName = b.name && b.name.toLowerCase().includes(q)
    const matchRoom = b.roomItems.some(r => {
      const matchNo = r.roomNumber && r.roomNumber.toLowerCase().includes(q)
      const matchGuests = r.allGuests && r.allGuests.some(g => g.toLowerCase().includes(q))
      return matchNo || matchGuests
    })
    return matchCode || matchName || matchRoom
  })
})

const selectBookingFromSearch = (b, r = null, specificGuest = null) => {
  // Đổ dữ liệu booking được chọn vào Bảng 1 (Top-Left Table)
  displayedBookingsList.value = [b]

  if (r) {
    selectRoomItemRow(b, r, specificGuest)
  } else {
    selectBookingHeader(b)
  }
  searchQuery.value = b.code || (r ? r.roomNumber : b.name)
  showSearchDropdown.value = false
}

const handleClickOutside = (e) => {
  if (searchContainerRef.value && !searchContainerRef.value.contains(e.target)) {
    showSearchDropdown.value = false
  }
}

onMounted(() => {
  loadCheckoutBookings()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="flex h-[calc(100vh-48px)] bg-[#ecefe6] text-xs text-gray-700 select-none overflow-hidden font-sans relative">
    <LoadingOverlay :show="isLoading" />

    <!-- LEFTSIDE TOOLBAR (Cột nút chức năng dọc bên trái - Hỗ trợ Thu gọn/Mở rộng) -->
    <aside 
      :class="[
        isSidebarCollapsed ? 'w-12' : 'w-44',
        'bg-[#f4f5f0] border-r border-gray-300 flex flex-col justify-between p-1 transition-all duration-200 shrink-0 shadow-sm relative'
      ]"
    >
      <!-- Collapse / Expand Toggle Button -->
      <button 
        @click="toggleSidebar"
        class="absolute -right-3 top-2 bg-white border border-gray-300 rounded-full p-0.5 shadow hover:bg-gray-100 z-30 text-gray-600"
        :title="isSidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      >
        <ChevronRight v-if="isSidebarCollapsed" class="w-3.5 h-3.5" />
        <ChevronLeft v-else class="w-3.5 h-3.5" />
      </button>

      <div class="space-y-0.5 overflow-y-auto overflow-x-hidden pt-4 text-xs">
        <!-- Thêm dịch vụ -->
        <button 
          @click="showAddServiceModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thêm dịch vụ' : ''"
        >
          <Plus class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thêm dịch vụ</span>
        </button>

        <!-- Thêm dịch vụ BP -->
        <button 
          @click="openAddHousekeepingService"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thêm dịch vụ BP' : ''"
        >
          <PlusSquare class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thêm dịch vụ BP</span>
        </button>

        <!-- Tách dịch vụ -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Tách dịch vụ' : ''"
        >
          <Scissors class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Tách dịch vụ</span>
        </button>

        <!-- Chuyển dịch vụ -->
        <button 
          @click="showTransferServiceModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Chuyển dịch vụ' : ''"
        >
          <ArrowRightLeft class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Chuyển dịch vụ</span>
        </button>

        <!-- Tập hợp DV -->
        <button 
          @click="showQuickTransferBillModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Tập hợp DV' : ''"
        >
          <Layers class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Tập hợp DV</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- In hóa đơn với Dropdown Submenu -->
        <div class="relative">
          <button 
            @click="showInvoiceMenu = !showInvoiceMenu"
            class="w-full flex items-center justify-between px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
            :title="isSidebarCollapsed ? 'In hóa đơn' : ''"
          >
            <div class="flex items-center gap-1.5 truncate">
              <Printer class="w-3.5 h-3.5 text-gray-600 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">In hóa đơn</span>
            </div>
            <ChevronRight v-if="!isSidebarCollapsed" class="w-3 h-3 text-gray-400 shrink-0" />
          </button>

          <!-- Dropdown Sub-menu Floating -->
          <div 
            v-if="showInvoiceMenu" 
            class="absolute left-full top-0 ml-1 w-32 bg-white border border-gray-300 rounded shadow-lg z-50 py-1 text-xs"
          >
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-gray-700">Hiện giá</button>
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-gray-700">Không hiện giá</button>
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-sky-600 font-semibold border-t border-gray-100">In Bill</button>
          </div>
        </div>

        <!-- In VAT -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'In VAT' : ''"
        >
          <FileText class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">In VAT</span>
        </button>

        <!-- Hủy VAT -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50 text-red-600 transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Hủy VAT' : ''"
        >
          <FileX class="w-3.5 h-3.5 text-red-500 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate text-red-600">Hủy VAT</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- Xóa dịch vụ -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50 text-red-600 transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Xóa dịch vụ' : ''"
        >
          <Trash2 class="w-3.5 h-3.5 text-red-500 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate text-red-600">Xóa dịch vụ</span>
        </button>

        <!-- Xóa thanh toán -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50 text-red-600 transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Xóa thanh toán' : ''"
        >
          <RotateCcw class="w-3.5 h-3.5 text-red-500 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate text-red-600">Xóa thanh toán</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- Thanh toán trước -->
        <button 
          @click="showPrepaymentModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thanh toán trước' : ''"
        >
          <CreditCard class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thanh toán trước</span>
        </button>

        <!-- Thanh toán -->
        <button 
          @click="showPaymentModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thanh toán' : ''"
        >
          <CreditCard class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thanh toán</span>
        </button>

        <!-- Lọc -->
        <button 
          @click="showFilterServiceModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Lọc' : ''"
        >
          <Filter class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Lọc</span>
        </button>
      </div>

      <!-- Bottom Button: Trả phòng -->
      <div class="pt-1.5 border-t border-gray-300">
        <button 
          class="w-full flex items-center justify-center gap-1.5 px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium shadow-sm transition-colors text-xs"
          :title="isSidebarCollapsed ? 'Trả phòng' : ''"
        >
          <LogOut class="w-3.5 h-3.5 rotate-180 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Trả phòng</span>
        </button>
      </div>
    </aside>

    <!-- RIGHT MAIN SECTION -->
    <main class="flex-1 flex flex-col min-w-0 bg-[#e8ebe0] p-1.5 gap-1.5 overflow-hidden">

      <!-- TOP CONTROL BAR (Nằm trên cùng toàn chiều rộng, không thuộc panel nào) -->
      <div class="flex items-center justify-between gap-2 px-2 py-1 bg-[#f4f5f0] border border-gray-300 rounded shadow-xs text-xs">
        <div class="flex items-center gap-2 flex-1 max-w-xl">
          <!-- Search Input with Popup Dropdown (Khớp 100% Ảnh 1 & 2) -->
          <div ref="searchContainerRef" class="relative flex-1">
            <Search class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 z-10" />
            <input 
              v-model="searchQuery" 
              @focus="showSearchDropdown = true"
              @input="showSearchDropdown = true"
              type="text" 
              placeholder="Search" 
              class="w-full pl-7 pr-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" 
            />

            <!-- Floating Search Dropdown Popup List (Khớp chính xác Ảnh 1) -->
            <div 
              v-if="showSearchDropdown" 
              class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-300 rounded-md shadow-2xl z-50 max-h-72 overflow-y-auto divide-y divide-gray-100"
            >
              <div 
                v-for="b in filteredSearchBookings" 
                :key="b.id"
                class="px-3 py-2 hover:bg-sky-50 cursor-pointer text-xs transition-colors"
                @click="selectBookingFromSearch(b)"
              >
                <!-- Line 1: BKK:  <mã booking>    <tên đoàn / tên booking> -->
                <div class="flex items-center gap-4">
                  <span class="font-bold text-gray-900 shrink-0 min-w-[36px]">BKK:</span>
                  <span class="font-bold text-gray-900 shrink-0 min-w-[75px]">{{ b.code }}</span>
                  <span class="font-bold text-gray-800 truncate">{{ b.name }}</span>
                </div>
                <!-- Sublines for each room in booking (Khớp chính xác Ảnh 1) -->
                <template v-for="r in b.roomItems" :key="r.id">
                  <template v-if="showAllGuestsInRoom && r.allGuests.length > 1">
                    <div 
                      v-for="(gName, gIdx) in r.allGuests"
                      :key="gIdx"
                      @click.stop="selectBookingFromSearch(b, r, gName)"
                      class="flex items-center gap-4 mt-1.5 text-gray-700 pl-[36px] hover:text-sky-600"
                    >
                      <span class="font-bold text-gray-800 shrink-0 min-w-[75px]">{{ r.roomNumber }}</span>
                      <span class="text-gray-400">|</span>
                      <span class="font-bold text-gray-800 truncate">{{ gName }}</span>
                    </div>
                  </template>
                  <template v-else>
                    <div 
                      @click.stop="selectBookingFromSearch(b, r)"
                      class="flex items-center gap-4 mt-1.5 text-gray-700 pl-[36px] hover:text-sky-600"
                    >
                      <span class="font-bold text-gray-800 shrink-0 min-w-[75px]">{{ r.roomNumber }}</span>
                      <span class="text-gray-400">|</span>
                      <span class="font-bold text-gray-800 truncate">{{ r.guestName }}</span>
                    </div>
                  </template>
                </template>
              </div>
              <div v-if="filteredSearchBookings.length === 0" class="p-3 text-center text-gray-400 italic">
                Không tìm thấy dữ liệu đăng ký nào
              </div>
            </div>
          </div>

          <!-- Filter Dropdown -->
          <select 
            v-model="registerFilter"
            class="px-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500"
          >
            <option value="current">Đăng ký hiện tại</option>
            <option value="all">Tất cả đăng ký</option>
          </select>
        </div>

        <!-- Checkbox Xem tất cả khách trong phòng (Căn phải) -->
        <label class="flex items-center gap-1.5 cursor-pointer text-gray-700 hover:text-gray-900 whitespace-nowrap text-xs">
          <input type="checkbox" v-model="showAllGuestsInRoom" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
          <span>Xem tất cả khách trong phòng</span>
        </label>
      </div>

      <!-- TOP SPLIT SECTION (Bảng Đăng ký + Panel Thông tin) -->
      <div class="grid grid-cols-12 gap-1.5 h-[40%] min-h-0">

        <!-- TOP LEFT: Bảng Danh sách Đăng ký (7 cols) -->
        <div class="col-span-7 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Danh sách Phòng / Khách (Khớp chính xác Ảnh 2) -->
          <div class="flex-1 overflow-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1 w-7 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1 border-r border-gray-300 min-w-[80px] text-center">Mã ĐK</th>
                  <th class="p-1 border-r border-gray-300 min-w-[60px] text-center">Phòng</th>
                  <th class="p-1 border-r border-gray-300 min-w-[140px] text-center">Tên nhóm/khách</th>
                  <th class="p-1 border-r border-gray-300 text-center min-w-[80px]">Tổng dịch vụ</th>
                  <th class="p-1 text-center min-w-[80px]">Đã thanh toán</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <template v-for="b in displayedBookingsList" :key="b.id">
                  <!-- Row 1: Header Booking (Hiển thị Mã ĐK & Tên nhóm - Khớp màu xanh nhạt Ảnh 2) -->
                  <tr 
                    @click="selectBookingHeader(b)"
                    :class="[
                      selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                      'cursor-pointer transition-colors'
                    ]"
                  >
                    <td class="p-1 text-center border-r border-gray-300">
                      <input type="checkbox" v-model="b.checked" @change="toggleBookingCheck(b)" @click.stop class="rounded border-gray-300 text-sky-600" />
                    </td>
                    <td class="p-1 border-r border-gray-300 font-bold" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-sky-600'">{{ b.code }}</td>
                    <td class="p-1 border-r border-gray-300 text-center font-bold"></td>
                    <td class="p-1 border-r border-gray-300 font-bold" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ b.name }}</td>
                    <td class="p-1 border-r border-gray-300 text-center font-mono" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ formatMoney(b.totalService) }}</td>
                    <td class="p-1 text-center font-mono" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ formatMoney(b.paidAmount) }}</td>
                  </tr>

                  <!-- Sub-rows: Room Items (Checkbox không tự động tick) -->
                  <template v-for="r in b.roomItems" :key="r.id">
                    <template v-if="showAllGuestsInRoom && r.allGuests.length > 1">
                      <tr 
                        v-for="(gName, gIdx) in r.allGuests"
                        :key="gIdx"
                        @click="selectRoomItemRow(b, r, gName)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id && selectedGuest === gName ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                          'cursor-pointer transition-colors'
                        ]"
                      >
                        <td class="p-1 text-center border-r border-gray-300 pl-4">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 border-r border-gray-300 text-center"></td>
                        <td class="p-1 border-r border-gray-300 text-center font-bold" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && selectedGuest === gName }">{{ r.roomNumber }}</td>
                        <td class="p-1 border-r border-gray-300" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && selectedGuest === gName }">{{ gName }}</td>
                        <td class="p-1 border-r border-gray-300 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && selectedGuest === gName }">{{ formatMoney(r.serviceAmount) }}</td>
                        <td class="p-1 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && selectedGuest === gName }">{{ formatMoney(r.paidAmount) }}</td>
                      </tr>
                    </template>
                    <template v-else>
                      <tr 
                        @click="selectRoomItemRow(b, r)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                          'cursor-pointer transition-colors'
                        ]"
                      >
                        <td class="p-1 text-center border-r border-gray-300 pl-4">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 border-r border-gray-300 text-center"></td>
                        <td class="p-1 border-r border-gray-300 text-center font-bold" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ r.roomNumber }}</td>
                        <td class="p-1 border-r border-gray-300" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ r.guestName }}</td>
                        <td class="p-1 border-r border-gray-300 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ formatMoney(r.serviceAmount) }}</td>
                        <td class="p-1 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ formatMoney(r.paidAmount) }}</td>
                      </tr>
                    </template>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TOP RIGHT: Information Panel & Folio Tabs (5 cols) -->
        <div class="col-span-5 bg-white rounded border border-gray-300 flex flex-col p-2 min-h-0 justify-between shadow-xs">
          
          <div class="space-y-1.5 text-xs">
            <!-- Header Controls & Title -->
            <div class="flex items-center justify-between gap-1 border-b border-gray-200 pb-1">
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-1 cursor-pointer">
                  <input type="checkbox" v-model="isRegistered" class="rounded border-gray-300 text-sky-600" />
                  <span class="font-medium text-gray-700">Đăng ký</span>
                </label>
                <label class="flex items-center gap-1 cursor-pointer">
                  <input type="checkbox" v-model="isNoPost" class="rounded border-gray-300 text-sky-600" />
                  <span class="text-gray-600">No post</span>
                </label>
              </div>

              <!-- Button Electronic Signature -->
              <div class="flex items-center gap-1">
                <button class="bg-[#38bdf8] hover:bg-sky-500 text-white px-2 py-0.5 rounded flex items-center gap-1 text-xs font-medium shadow-xs transition-colors">
                  <span>HĐ chữ ký điện tử</span>
                </button>
                <button class="p-0.5 hover:bg-gray-100 rounded text-gray-500 border border-gray-300">
                  <RefreshCw class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Booking Sub-Header Code (Khớp chính xác Ảnh 2) -->
            <div class="bg-[#f0f2ea] border border-gray-200 rounded px-2 py-1 font-semibold text-gray-800 text-xs truncate">
              {{ selectedBooking ? `BK ${selectedBooking.code} - KHÁCH LẺ - ${selectedBooking.name}` : 'BK -- - KHÁCH LẺ - --' }}
            </div>

            <!-- Dates Range & Guest & Room Input (Khớp chính xác Ảnh 2) -->
            <div class="space-y-1.5">
              <!-- Date Range picker -->
              <div class="flex items-center gap-2 border border-gray-300 rounded px-2 py-0.5 bg-white text-xs">
                <span class="font-mono text-gray-700">{{ selectedBooking ? formatDate(selectedBooking.arrivalDate) : '-- / -- / ----' }}</span>
                <span class="text-gray-400">~</span>
                <span class="font-mono text-gray-700">{{ selectedBooking ? formatDate(selectedBooking.departureDate) : '-- / -- / ----' }}</span>
                <Calendar class="w-3.5 h-3.5 text-gray-400 ml-auto" />
              </div>

              <!-- Guest Select & Room Input -->
              <div class="grid grid-cols-12 gap-1.5">
                <div class="col-span-8">
                  <select v-model="selectedGuest" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500">
                    <option v-if="!selectedBooking" value="">Tên khách</option>
                    <template v-if="selectedBooking">
                      <template v-for="r in selectedBooking.roomItems" :key="r.id">
                        <option v-for="(gName, gIdx) in r.allGuests" :key="`${r.id}-${gIdx}`" :value="gName">
                          {{ gName }} (Phòng {{ r.roomNumber }})
                        </option>
                      </template>
                    </template>
                  </select>
                </div>
                <div class="col-span-4">
                  <input v-model="roomNumber" type="text" placeholder="Phòng" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-xs font-bold text-center focus:outline-none focus:border-sky-500" />
                </div>
              </div>

              <!-- Ghi chú Textarea -->
              <div>
                <label class="block text-gray-600 font-medium mb-0.5 text-xs">Ghi chú</label>
                <textarea 
                  v-model="noteText" 
                  rows="2" 
                  class="w-full p-1.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500 resize-none"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Folio Tabs Selector (Bottom of Top-Right Panel) -->
          <div class="pt-1">
            <div class="text-xs font-semibold text-gray-500 mb-0.5">Folio</div>
            <div class="grid grid-cols-4 gap-1">
              <!-- Tab A (Active) -->
              <button 
                @click="activeFolioTab = 'A'"
                :class="[
                  activeFolioTab === 'A' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-200',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">A</span>
                <span class="text-xs font-mono mt-0.5">0</span>
              </button>

              <!-- Tab 1 -->
              <button 
                @click="activeFolioTab = '1'"
                :class="[
                  activeFolioTab === '1' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">1</span>
                <span class="text-xs font-mono mt-0.5">0</span>
              </button>

              <!-- Tab 2 -->
              <button 
                @click="activeFolioTab = '2'"
                :class="[
                  activeFolioTab === '2' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">2</span>
                <span class="text-xs font-mono mt-0.5">0</span>
              </button>

              <!-- Tab 3 -->
              <button 
                @click="activeFolioTab = '3'"
                :class="[
                  activeFolioTab === '3' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">3</span>
                <span class="text-xs font-mono mt-0.5">0</span>
              </button>
            </div>
          </div>

        </div>

      </div>

      <!-- BOTTOM SPLIT SECTION (2 Bảng dữ liệu song song) -->
      <div class="grid grid-cols-12 gap-1.5 flex-1 min-h-0">

        <!-- BOTTOM LEFT: Bảng Chi tiết Dịch vụ / Phát sinh (6 cols) -->
        <div class="col-span-6 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[120px]">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[110px]">Dịch vụ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Bộ phận</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-center min-w-[55px]">SL</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[75px]">Đơn vị</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã TT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Folio</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[80px]">Tax</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Phí phục vụ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã HĐ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Số VAT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Kế toán</th>
                  <th class="px-2.5 py-1.5 min-w-[105px]">Người dùng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in servicesList" :key="s.id" class="border-b border-gray-200 hover:bg-gray-50 text-gray-800">
                  <td class="px-2 py-1.5 text-center border-r border-gray-200">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono">{{ s.dateTime }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-bold text-sky-600">{{ s.serviceName }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.description }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.department }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold">{{ formatMoney(s.amount) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-mono">{{ s.quantity }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.unit }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono text-sky-600">{{ s.paymentCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-bold">{{ s.folio }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono">{{ formatMoney(s.tax) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono">{{ formatMoney(s.serviceCharge) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.invoiceCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.vatNo }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ s.accounting }}</td>
                  <td class="px-2.5 py-1.5">{{ s.userName }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div v-if="servicesList.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
              <Inbox class="w-9 h-9 stroke-1 mb-1 text-gray-300" />
              <span class="text-xs text-gray-400">No data</span>
            </div>
          </div>

          <!-- Table Footer Total -->
          <div class="p-1.5 bg-[#f4f5f0] border-t border-gray-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input type="checkbox" class="rounded border-gray-300" />
              <span>Tổng cộng</span>
            </div>
            <span class="font-mono text-xs pr-2">{{ formatMoney(totalServiceAmount) }}</span>
          </div>
        </div>

        <!-- BOTTOM RIGHT: Bảng Chi tiết Thanh toán (6 cols) -->
        <div class="col-span-6 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[120px]">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Bộ phận</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">HTTT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[75px]">Đơn vị</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Folio</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã TT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[65px]">Xóa</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Số VAT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Kế toán</th>
                  <th class="px-2.5 py-1.5 min-w-[105px]">Người dùng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in paymentsList" :key="p.id" class="border-b border-gray-200 hover:bg-gray-50 text-gray-800">
                  <td class="px-2 py-1.5 text-center border-r border-gray-200">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono">{{ p.dateTime }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.department }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.description }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-medium text-emerald-600">{{ p.paymentMethod }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold text-emerald-700">{{ formatMoney(p.amount) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.unit }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-bold">{{ p.folio }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono text-sky-600">{{ p.paymentCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center">{{ p.isDeleted }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.vatNo }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.accounting }}</td>
                  <td class="px-2.5 py-1.5">{{ p.userName }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div v-if="paymentsList.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
              <Inbox class="w-9 h-9 stroke-1 mb-1 text-gray-300" />
              <span class="text-xs text-gray-400">No data</span>
            </div>
          </div>

          <!-- Table Footer Total -->
          <div class="p-1.5 bg-[#f4f5f0] border-t border-gray-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input type="checkbox" class="rounded border-gray-300" />
              <span>Tổng cộng</span>
            </div>
            <span class="font-mono text-xs pr-2">{{ formatMoney(totalPaymentAmount) }}</span>
          </div>
        </div>

      </div>

    </main>

    <!-- Modals -->
    <AddServiceModal 
      :show="showAddServiceModal" 
      @close="showAddServiceModal = false" 
    />

    <TransferServiceModal 
      :show="showTransferServiceModal" 
      @close="showTransferServiceModal = false" 
    />

    <QuickTransferBillModal 
      :show="showQuickTransferBillModal" 
      @close="showQuickTransferBillModal = false" 
    />

    <PrepaymentModal 
      :show="showPrepaymentModal" 
      @close="showPrepaymentModal = false" 
    />

    <PaymentModal 
      :show="showPaymentModal" 
      @close="showPaymentModal = false" 
    />

    <FilterServiceModal 
      :show="showFilterServiceModal" 
      @close="showFilterServiceModal = false" 
    />
  </div>
</template>
