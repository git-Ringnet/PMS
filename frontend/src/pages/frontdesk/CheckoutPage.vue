<script setup>
import { ref } from 'vue'
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

// Sidebar collapse state
const isSidebarCollapsed = ref(false)
const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// UI State (Chỉ mockup UI, chưa thực hiện logic backend)
const searchQuery = ref('')
const registerFilter = ref('current')
const showAllGuestsInRoom = ref(false)

const isNoPost = ref(false)
const isRegistered = ref(true)

const selectedGuest = ref('Guest 1')
const roomNumber = ref('602')
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

// Data Mockup cho danh sách phòng/khách góc trên bên trái (Khớp chính xác ảnh 1 & 2)
const bookingsList = ref([
  {
    id: 'GAL6131',
    code: 'GAL6131',
    room: '',
    name: 'Test 1',
    totalService: 0,
    paidAmount: 0,
    selected: false
  },
  {
    id: '602',
    code: '',
    room: '602',
    name: 'Mr. Guest 1',
    totalService: 0,
    paidAmount: 0,
    selected: true
  }
])

const selectBookingRow = (item) => {
  bookingsList.value.forEach(b => b.selected = (b.id === item.id))
}
</script>

<template>
  <div class="flex h-[calc(100vh-48px)] bg-[#ecefe6] text-xs text-gray-700 select-none overflow-hidden font-sans">
    
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

      <!-- TOP CONTROL BAR (Nằm trên cùng toàn chiều rộng, không thuộc panel nào - Khớp chính xác Ảnh 2) -->
      <div class="flex items-center justify-between gap-2 px-2 py-1 bg-[#f4f5f0] border border-gray-300 rounded shadow-xs text-xs">
        <div class="flex items-center gap-2 flex-1 max-w-xl">
          <!-- Search Input -->
          <div class="relative flex-1">
            <Search class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400" />
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Search" 
              class="w-full pl-7 pr-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" 
            />
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
          <!-- Table Danh sách Phòng / Khách -->
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
                <tr 
                  v-for="item in bookingsList" 
                  :key="item.id"
                  @click="selectBookingRow(item)"
                  :class="[
                    item.selected ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                    'cursor-pointer transition-colors'
                  ]"
                >
                  <td class="p-1 text-center border-r border-gray-300">
                    <input type="checkbox" :checked="item.selected" class="rounded border-gray-300" />
                  </td>
                  <td class="p-1 border-r border-gray-300 text-sky-600 font-medium" :class="{ 'text-white': item.selected }">{{ item.code }}</td>
                  <td class="p-1 border-r border-gray-300 text-center font-bold" :class="{ 'text-white': item.selected }">{{ item.room }}</td>
                  <td class="p-1 border-r border-gray-300" :class="{ 'text-white': item.selected }">{{ item.name }}</td>
                  <td class="p-1 border-r border-gray-300 text-center font-mono" :class="{ 'text-white': item.selected }">{{ item.totalService }}</td>
                  <td class="p-1 text-center font-mono" :class="{ 'text-white': item.selected }">{{ item.paidAmount }}</td>
                </tr>
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

            <!-- Booking Sub-Header Code -->
            <div class="bg-[#f0f2ea] border border-gray-200 rounded px-2 py-1 font-semibold text-gray-800 text-xs">
              BK GAL6131 - KHÁCH LẺ - Test 1
            </div>

            <!-- Dates Range & Guest & Room Input -->
            <div class="space-y-1.5">
              <!-- Date Range picker mockup -->
              <div class="flex items-center gap-2 border border-gray-300 rounded px-2 py-0.5 bg-white text-xs">
                <span class="font-mono text-gray-700">03 / 07 / 2026</span>
                <span class="text-gray-400">~</span>
                <span class="font-mono text-gray-700">11 / 07 / 2026</span>
                <Calendar class="w-3.5 h-3.5 text-gray-400 ml-auto" />
              </div>

              <!-- Guest Select & Room Input -->
              <div class="grid grid-cols-12 gap-1.5">
                <div class="col-span-8">
                  <select v-model="selectedGuest" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500">
                    <option value="Guest 1">Guest 1</option>
                  </select>
                </div>
                <div class="col-span-4">
                  <input v-model="roomNumber" type="text" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-xs font-bold text-center focus:outline-none focus:border-sky-500" />
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
                <!-- Empty state -->
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
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
            <span class="font-mono text-xs pr-2">0</span>
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
                <!-- Empty state -->
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
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
            <span class="font-mono text-xs pr-2">0</span>
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
