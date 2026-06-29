<script setup>
import { ref } from 'vue'

import DateRangePicker from '@/components/DateRangePicker.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  reportTitle: { type: String, default: 'Báo cáo' },
  dateType: { type: String, default: 'range' },
  showShift: { type: Boolean, default: false },
  shiftLabel: { type: String, default: 'Ca' },
  showZone: { type: Boolean, default: false },
  showCounter: { type: Boolean, default: false },
  showCompany: { type: Boolean, default: false },
  showBooking: { type: Boolean, default: false },
  showUser: { type: Boolean, default: false },
  showPaymentMethod: { type: Boolean, default: false },
  showGroup: { type: Boolean, default: false },
  showSort: { type: Boolean, default: false },
  showSendToRoom: { type: Boolean, default: false },
  showEndShift: { type: Boolean, default: false },
  showMainGuest: { type: Boolean, default: false },
  showDetailsToggle: { type: Boolean, default: false },
  showRoomCharge: { type: Boolean, default: false },
  showVatToggles: { type: Boolean, default: false },
  showProductType: { type: Boolean, default: false },
  showInvoiceType: { type: Boolean, default: false },
  showOutlet: { type: Boolean, default: false },
  showShiftToggle: { type: Boolean, default: false },
  showTimeRange: { type: Boolean, default: false },
  showReportType: { type: Boolean, default: false },
  showProductSelect: { type: Boolean, default: false },
  showIncludeFO: { type: Boolean, default: false },
  showDetailReportType: { type: Boolean, default: false },
  showTimeFrameToggle: { type: Boolean, default: false },
  showLateCheckin: { type: Boolean, default: false },
  showRoomInfo: { type: Boolean, default: false },
  showGroupByRegistration: { type: Boolean, default: false },
  showNotBreakfast: { type: Boolean, default: false },
  showOrderType: { type: Boolean, default: false },
  showBranch: { type: Boolean, default: false },
  showRoomType: { type: Boolean, default: false }
})

// State variables for filters
const dateRange = ref({ start: '24/06/2026', end: '24/06/2026' })
const singleDate = ref('Hôm nay')
const selectedZone = ref(props.dateType === 'single' ? 'Chọn giá trị' : 'Tất cả')
const selectedCounter = ref('Tất cả')
const selectedShift = ref('Tất cả')
const selectedCompany = ref('Chọn Giá Trị')
const selectedBooking = ref('Chọn giá trị')
const selectedUser = ref('Người dùng')
const selectedPayment = ref('HTTT')
const selectedGroup = ref('PaymentMethod')
const selectedSort = ref('Ma')
const selectedSortOrder = ref('ASC')
const selectedProductType = ref('Tất cả')
const selectedInvoiceType = ref('All')
const selectedOutlet = ref('Tất cả')
const selectedReportType = ref('Chọn giá trị')
const selectedProduct = ref('Chọn sản phẩm')
const selectedDetailReportType = ref('Chi tiết')
const timeStart = ref('--:--')
const timeEnd = ref('--:--')

// Checkboxes and Toggles
const checkSendToRoom = ref(false)
const checkEndShift = ref(false)
const toggleMainGuest = ref(false)
const toggleDetails = ref(false)
const toggleRoomCharge = ref(false)
const toggleVat = ref(false)
const toggleNoVat = ref(false)
const toggleShift = ref(false)
const toggleHideDetails = ref(false)
const checkIncludeFO = ref(true)
const toggleTimeFrame = ref(false)
const toggleLateCheckin = ref(true)
const toggleRoomInfo = ref(false)
const toggleGroupByRegistration = ref(false)
const toggleNotBreakfast = ref(false)
const selectedOrderType = ref('Chọn giá trị')
const selectedBranch = ref('Chi nhánh hiện tại')
const selectedRoomType = ref('Chọn giá trị')

// Options lists
const showSidebar = ref(true)

const zones = ['Tất cả', 'Chọn giá trị', 'Khu vực A', 'Khu vực B']
const counters = ['Tất cả', 'Quầy 1', 'Quầy 2']
const users = ['Tất cả', 'Nguyễn Văn A', 'Trần Thị B']
const paymentMethods = ['Tất cả', 'Tiền mặt', 'Chuyển khoản', 'Thẻ']
const groups = ['Tất cả', 'Nhóm 1', 'Nhóm 2']
const companies = ['Tất cả', 'Công ty A', 'Công ty B']
const bookings = ['Tất cả', 'Booking 1', 'Booking 2']
const orderTypes = ['Chọn giá trị', 'Đơn A', 'Đơn B']
const branches = ['Chi nhánh hiện tại', 'Tất cả các chi nhánh']
const roomTypes = ['Chọn giá trị', 'Standard', 'Deluxe', 'Suite']
const shifts = ['Tất cả', 'Ca sáng', 'Ca chiều', 'Ca tối']
const sorts = ['Ma', 'Ten']
const sortOrders = ['ASC', 'DESC']
const invoiceTypes = ['All', 'Loại 1', 'Loại 2']
const productTypes = ['Tất cả', 'Đồ ăn', 'Đồ uống']
const outlets = ['Tất cả', 'Nhà hàng 1', 'Nhà hàng 2']
const reportTypes = ['Chọn giá trị', 'Loại A', 'Loại B']
const productsList = ['Chọn sản phẩm', 'Sản phẩm 1', 'Sản phẩm 2']
const detailReportTypes = ['Chi tiết', 'Tổng hợp']

const handleViewReport = () => uiStore.showToast(`Đang tạo ${props.reportTitle}...`, 'info')
const handlePrint = () => uiStore.showToast('Đang kết nối máy in...', 'info')
const handleDownload = () => uiStore.showToast('Đang tải file PDF...', 'info')
</script>

<template>
  <div class="flex h-full bg-white overflow-hidden">
    <!-- ── Left Filter Panel ───────────────────────────────── -->
    <div v-show="showSidebar" class="w-[280px] border-r border-slate-200 bg-white flex flex-col overflow-y-auto shrink-0 shadow-sm z-10">
      
      <!-- Panel Header -->
      <div class="px-4 py-3 border-b border-slate-100 bg-slate-50 shrink-0 flex items-center justify-between">
        <h2 class="font-bold text-slate-700 text-sm truncate" :title="reportTitle">{{ reportTitle }}</h2>
        <div class="w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center shrink-0">
          <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
        </div>
      </div>

      <div class="p-4 flex flex-col gap-4">
        
        <!-- Ngày -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Ngày</label>
          <DateRangePicker v-if="dateType === 'range'" v-model="dateRange" class="w-full" />
          <div v-else class="relative">
            <input type="text" v-model="singleDate" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
            <svg class="w-4 h-4 text-emerald-500 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
          </div>
        </div>

        <!-- Chi nhánh -->
        <div v-if="showBranch" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Chi nhánh</label>
          <div class="relative">
            <select v-model="selectedBranch" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="b in branches" :key="b">{{ b }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Outlet -->
        <div v-if="showOutlet" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Outlet</label>
          <div class="relative">
            <select v-model="selectedOutlet" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="o in outlets" :key="o">{{ o }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Ca -->
        <div v-if="showShift" class="flex flex-col gap-1.5">
          <div class="flex items-center gap-2">
            <button v-if="showShiftToggle" @click="toggleShift = !toggleShift" class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleShift ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-2.5 w-2.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleShift ? 'translate-x-[14px]' : 'translate-x-1'"></span>
            </button>
            <label class="text-xs font-medium text-slate-700">{{ shiftLabel }}</label>
          </div>
          <div class="relative">
            <select v-model="selectedShift" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="s in shifts" :key="s">{{ s }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Giờ -->
        <div v-if="showTimeRange" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Giờ</label>
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <input type="text" v-model="timeStart" class="w-full text-center py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
            </div>
            <span class="text-slate-400">-</span>
            <div class="relative flex-1">
              <input type="text" v-model="timeEnd" class="w-full text-center py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute right-1.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
        </div>

        <!-- Xem theo khung giờ -->
        <div v-if="showTimeFrameToggle" class="flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-700">Xem theo khung giờ</label>
            <button @click="toggleTimeFrame = !toggleTimeFrame" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleTimeFrame ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleTimeFrame ? 'translate-x-[18px]' : 'translate-x-1'"></span>
            </button>
          </div>
          <div class="flex items-center gap-2">
            <div class="flex flex-col gap-1.5 flex-1">
              <label class="text-[10px] text-slate-500 truncate">Từ giờ ngày hôm trước</label>
              <div class="relative">
                <input type="text" v-model="timeStart" class="w-full text-center py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
                <svg class="w-3.5 h-3.5 text-slate-400 absolute right-1.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
            </div>
            <div class="flex flex-col gap-1.5 flex-1">
              <label class="text-[10px] text-slate-500 truncate">Đến giờ hôm nay</label>
              <div class="relative">
                <input type="text" v-model="timeEnd" class="w-full text-center py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
                <svg class="w-3.5 h-3.5 text-slate-400 absolute right-1.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Khu vực -->
        <div v-if="showZone" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Khu vực</label>
          <div class="relative">
            <select v-model="selectedZone" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="z in zones" :key="z">{{ z }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Quầy -->
        <div v-if="showCounter" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Quầy</label>
          <div class="relative">
            <select v-model="selectedCounter" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="c in counters" :key="c">{{ c }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Công ty -->
        <div v-if="showCompany" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Công ty</label>
          <div class="relative">
            <select v-model="selectedCompany" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="c in companies" :key="c">{{ c }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Booking -->
        <div v-if="showBooking" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Booking</label>
          <div class="relative">
            <select v-model="selectedBooking" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="b in bookings" :key="b">{{ b }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Người dùng -->
        <div v-if="showUser" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Người dùng</label>
          <div class="relative">
            <select v-model="selectedUser" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="u in users" :key="u">{{ u }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Type Room -->
        <div v-if="showRoomType" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Type Room</label>
          <div class="relative">
            <select v-model="selectedRoomType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="rt in roomTypes" :key="rt">{{ rt }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- HTTT -->
        <div v-if="showPaymentMethod" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">HTTT</label>
          <div class="relative">
            <select v-model="selectedPayment" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="p in paymentMethods" :key="p">{{ p }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Nhóm -->
        <div v-if="showGroup" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Nhóm</label>
          <div class="relative">
            <select v-model="selectedGroup" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="g in groups" :key="g">{{ g }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <!-- Sắp xếp theo -->
        <div v-if="showSort" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Sắp xếp theo</label>
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <select v-model="selectedSort" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
                <option v-for="s in sorts" :key="s">{{ s }}</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div class="relative w-24 shrink-0">
              <select v-model="selectedSortOrder" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
                <option v-for="o in sortOrders" :key="o">{{ o }}</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
          </div>
        </div>

        <!-- Checkboxes -->
        <div v-if="showSendToRoom" class="flex items-center gap-2 mt-1">
          <input type="checkbox" id="chkRoom" v-model="checkSendToRoom" class="w-3.5 h-3.5 border-slate-300 rounded text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <label for="chkRoom" class="text-xs font-medium text-slate-700 cursor-pointer select-none">Gửi về phòng</label>
        </div>
        
        <div v-if="showEndShift" class="flex items-center gap-2 mt-1">
          <input type="checkbox" id="chkShift" v-model="checkEndShift" class="w-3.5 h-3.5 border-slate-300 rounded text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <label for="chkShift" class="text-xs font-medium text-slate-700 cursor-pointer select-none">Báo cáo kết ca</label>
        </div>

        <!-- Toggles -->
        <div v-if="showMainGuest" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Hiển thị khách chính</label>
          <button @click="toggleMainGuest = !toggleMainGuest" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleMainGuest ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleMainGuest ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>
        <div v-if="showDetailsToggle && !showRoomCharge && !showVatToggles" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Hiển thị chi tiết</label>
          <button @click="toggleDetails = !toggleDetails" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleDetails ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleDetails ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>
        <div v-if="showRoomCharge && !showVatToggles" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Giá phòng</label>
          <button @click="toggleRoomCharge = !toggleRoomCharge" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleRoomCharge ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleRoomCharge ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>

        <!-- New Toggles from Images 1 and 2 -->
        <div v-if="showNotBreakfast" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Chưa Ăn Sáng</label>
          <button @click="toggleNotBreakfast = !toggleNotBreakfast" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleNotBreakfast ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleNotBreakfast ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>
        <div v-if="showLateCheckin" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Checkin trễ</label>
          <button @click="toggleLateCheckin = !toggleLateCheckin" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleLateCheckin ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleLateCheckin ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>
        <div v-if="showRoomInfo" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Thông tin phòng</label>
          <button @click="toggleRoomInfo = !toggleRoomInfo" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleRoomInfo ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleRoomInfo ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>
        <div v-if="showGroupByRegistration" class="flex items-center justify-between mt-2">
          <label class="text-xs font-bold text-slate-700">Nhóm theo đăng kí</label>
          <button @click="toggleGroupByRegistration = !toggleGroupByRegistration" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleGroupByRegistration ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleGroupByRegistration ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>

        <!-- Grid of Toggles for Report Guest Staying -->
        <div v-if="showVatToggles" class="grid grid-cols-2 gap-x-4 gap-y-3 mt-2">
          <div v-if="showDetailsToggle" class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-700">Chi Tiết</label>
            <button @click="toggleDetails = !toggleDetails" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleDetails ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleDetails ? 'translate-x-[18px]' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="showRoomCharge" class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-700">Tiền phòng</label>
            <button @click="toggleRoomCharge = !toggleRoomCharge" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleRoomCharge ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleRoomCharge ? 'translate-x-[18px]' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="showVatToggles" class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-700">VAT</label>
            <button @click="toggleVat = !toggleVat" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleVat ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleVat ? 'translate-x-[18px]' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="showVatToggles" class="flex items-center justify-between">
            <label class="text-xs font-medium text-slate-700">Không VAT</label>
            <button @click="toggleNoVat = !toggleNoVat" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleNoVat ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleNoVat ? 'translate-x-[18px]' : 'translate-x-1'"></span>
            </button>
          </div>
        </div>

        <!-- Other Selects & Checkboxes for Order Detail -->
        <div v-if="showReportType" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Loại báo cáo</label>
          <div class="relative">
            <select v-model="selectedReportType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="t in reportTypes" :key="t">{{ t }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <div v-if="showProductSelect" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Sản phẩm</label>
          <div class="relative">
            <select v-model="selectedProduct" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="p in productsList" :key="p">{{ p }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <div v-if="showDetailReportType" class="flex items-center gap-2 mt-2">
          <button @click="toggleHideDetails = !toggleHideDetails" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="toggleHideDetails ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="toggleHideDetails ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
          <label class="text-xs font-bold text-slate-700">Ẩn chi tiết</label>
        </div>

        <div v-if="showIncludeFO" class="flex items-center gap-2 mt-2">
          <input type="checkbox" id="chkFO" v-model="checkIncludeFO" class="w-4 h-4 border-slate-300 rounded text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <label for="chkFO" class="text-xs font-medium text-slate-700 cursor-pointer select-none">Bao gồm FO</label>
        </div>

        <div v-if="showDetailReportType" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Loại báo cáo</label>
          <div class="relative">
            <select v-model="selectedDetailReportType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="t in detailReportTypes" :key="t">{{ t }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <div v-if="showOrderType" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Loại đơn</label>
          <div class="relative">
            <select v-model="selectedOrderType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="t in orderTypes" :key="t">{{ t }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <div v-if="showInvoiceType" class="flex flex-col gap-1.5 mt-2">
          <label class="text-xs font-medium text-slate-700">Loại hoá đơn</label>
          <div class="relative">
            <select v-model="selectedInvoiceType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="t in invoiceTypes" :key="t">{{ t }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <div v-if="showProductType" class="flex flex-col gap-1.5">
          <label class="text-xs font-medium text-slate-700">Loại sản phẩm</label>
          <div class="relative">
            <select v-model="selectedProductType" class="w-full pl-3.5 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option v-for="p in productTypes" :key="p">{{ p }}</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>
        
        <div class="mt-4">
          <button @click="handleViewReport" class="w-full flex items-center justify-center bg-sky-400 text-white py-2 rounded text-sm font-bold shadow-sm hover:bg-sky-500 active:scale-[0.98] transition-all">
            Xem báo cáo
          </button>
        </div>

      </div>
    </div>

    <!-- ── Right PDF Viewer ────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden bg-slate-100 relative">
      <!-- PDF Toolbar -->
      <div class="flex items-center px-3 py-1.5 border-b border-slate-200 bg-white shadow-sm z-10 shrink-0 text-slate-500">
        
        <div class="flex-1 flex items-center justify-start">
          <button @click="showSidebar = !showSidebar" class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded text-slate-500 transition-colors" :title="showSidebar ? 'Thu gọn bộ lọc' : 'Mở rộng bộ lọc'">
             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
               <path v-if="showSidebar" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
               <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
             </svg>
          </button>
        </div>

        <div class="flex items-center justify-center gap-2">
          <!-- Pagination controls -->
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded text-slate-300 pointer-events-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded text-slate-300 pointer-events-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </button>
        
        <div class="flex items-center mx-2 text-xs">
          <div class="relative w-20">
            <select class="w-full text-center py-1 pl-2 pr-6 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 appearance-none focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 font-semibold cursor-pointer">
              <option>0 pages</option>
            </select>
            <svg class="w-3.5 h-3.5 absolute right-1.5 top-1.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </div>

        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded text-slate-300 pointer-events-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded text-slate-300 pointer-events-none">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
        </button>

        <div class="mx-1 w-px h-4 bg-slate-200"></div>

        <!-- Zoom controls -->
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded transition-colors" title="Fit width">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded transition-colors" title="Zoom out">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
        </button>
        <div class="relative w-[76px]">
          <select class="w-full text-center py-1 pl-2 pr-6 border border-slate-200 rounded-lg bg-slate-50 text-slate-600 appearance-none focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-xs font-semibold cursor-pointer">
            <option>100%</option>
          </select>
          <svg class="w-3.5 h-3.5 absolute right-1.5 top-1.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </div>
        <button class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded transition-colors" title="Zoom in">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        </button>

        <div class="mx-1 w-px h-4 bg-slate-200"></div>
        
        <!-- Actions -->
        <button class="w-8 h-8 flex items-center justify-center hover:text-slate-700 hover:bg-slate-100 rounded transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </button>
        <button @click="handlePrint" class="w-8 h-8 flex items-center justify-center hover:text-slate-700 hover:bg-slate-100 rounded transition-colors" title="In">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        </button>
        <button @click="handleDownload" class="w-8 h-8 flex items-center justify-center hover:text-slate-700 hover:bg-slate-100 rounded transition-colors" title="Tải xuống">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center hover:text-slate-700 hover:bg-slate-100 rounded transition-colors" title="Export">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center hover:text-slate-700 hover:bg-slate-100 rounded transition-colors ml-2" title="Fullscreen">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
        </button>
        </div>

        <div class="flex-1 flex items-center justify-end"></div>
      </div>

      <!-- PDF Placeholder Area -->
      <div class="flex-1 flex items-center justify-center p-8 overflow-auto">
        <div class="text-slate-500 text-lg font-medium">
          Waiting for parameter values...
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
