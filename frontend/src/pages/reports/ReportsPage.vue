<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const currentType = computed(() => route.query.type || 'revenue')
const currentTab = computed(() => route.query.tab || 'overview')

// Date filtering ref
const startDate = ref('2026-06-01')
const endDate = ref('2026-06-30')
const selectedExportType = ref('pdf')
const isExporting = ref(false)

function handleExport() {
  isExporting.value = true
  setTimeout(() => {
    isExporting.value = false
    alert(`Xuất báo cáo thành công dưới dạng ${selectedExportType.value.toUpperCase()}!`)
  }, 1200)
}

// 1. MOCK REVENUE DATA
const revenueKPIs = [
  { name: 'Doanh thu phòng', value: '142.500.000 đ', change: '+12.4%', up: true, desc: 'So với tuần trước' },
  { name: 'Dịch vụ & Phụ thu', value: '24.800.000 đ', change: '+8.1%', up: true, desc: 'Minibar, Giặt ủi, Xe đưa đón' },
  { name: 'Ăn uống (F&B)', value: '45.200.000 đ', change: '-2.3%', up: false, desc: 'Nhà hàng & Room service' },
  { name: 'Tổng doanh thu', value: '212.500.000 đ', change: '+10.2%', up: true, desc: 'Tổng kết doanh thu thực tế' }
]

const roomRevenueBreakdown = [
  { room: '101', type: 'Standard Double', nights: 12, rate: '450.000', total: '5.400.000', occupancy: '80%' },
  { room: '102', type: 'Standard Twin', nights: 15, rate: '480.000', total: '7.200.000', occupancy: '92%' },
  { room: '201', type: 'Deluxe Double', nights: 18, rate: '650.000', total: '11.700.000', occupancy: '85%' },
  { room: '202', type: 'Deluxe Twin', nights: 14, rate: '680.000', total: '9.520.000', occupancy: '78%' },
  { room: '301', type: 'Executive Suite', nights: 10, rate: '1.200.000', total: '12.000.000', occupancy: '65%' },
  { room: '302', type: 'Presidential', nights: 8, rate: '2.500.000', total: '20.000.000', occupancy: '50%' }
]

const depositData = [
  { id: 'BK-4819', guest: 'Mr. Rachid Boufarki', date: '12-06-2026', amount: '2.000.000', status: 'Đã nhận', type: 'Chuyển khoản' },
  { id: 'BK-4822', guest: 'Ms. Ivanova Daria', date: '14-06-2026', amount: '1.500.000', status: 'Đã nhận', type: 'Thẻ tín dụng' },
  { id: 'BK-4831', guest: 'Nguyễn Thị Hồng Phương', date: '15-06-2026', amount: '5.000.000', status: 'Chờ xử lý', type: 'Tiền mặt' },
  { id: 'BK-4840', guest: 'Ms. Kovaleva Natalia', date: '16-06-2026', amount: '3.000.000', status: 'Đã nhận', type: 'Chuyển khoản' }
]

const checkoutBillingData = [
  { invoice: 'INV-9021', room: '201', guest: 'Ms. Ivanova Daria', payment: 'Thẻ', total: '3.250.000', cashier: 'Lễ tân Ca 2' },
  { invoice: 'INV-9022', room: '102', guest: 'Mr. Rybchuk Alexandr', payment: 'Tiền mặt', total: '1.440.000', cashier: 'Lễ tân Ca 2' },
  { invoice: 'INV-9023', room: '301', guest: 'Ms. Munaitpashova Lyazzat', payment: 'Chuyển khoản', total: '6.700.000', cashier: 'Lễ tân Ca 1' },
  { invoice: 'INV-9024', room: '204', guest: 'Walkin Guest', payment: 'Tiền mặt', total: '980.000', cashier: 'Lễ tân Ca 3' }
]

// 2. MOCK STATS DATA
const statsKPIs = [
  { name: 'Công suất phòng', value: '72.8%', change: '+4.5%', up: true, desc: 'Tăng so với tháng trước' },
  { name: 'Lưu trú trung bình', value: '2.4 ngày', change: '+0.2 ngày', up: true, desc: 'Thời gian lưu trú của khách' },
  { name: 'Tổng lượt khách', value: '348 lượt', change: '+12.7%', up: true, desc: 'Tổng khách checkin thành công' },
  { name: 'Đặt phòng mới', value: '89 đặt phòng', change: '-1.4%', up: false, desc: 'Lượt đặt mới phát sinh' }
]

const floorStats = [
  { floor: 'Tầng 1', clean: 18, dirty: 2, maintenance: 1, total: 21, rate: '85.7%' },
  { floor: 'Tầng 2', clean: 20, dirty: 1, maintenance: 0, total: 21, rate: '95.2%' },
  { floor: 'Tầng 3', clean: 16, dirty: 4, maintenance: 2, total: 22, rate: '72.7%' },
  { floor: 'Tầng 4', clean: 19, dirty: 2, maintenance: 1, total: 22, rate: '86.4%' },
  { floor: 'Tầng 5', clean: 17, dirty: 3, maintenance: 1, total: 21, rate: '81.0%' }
]

const checkinStatsData = [
  { date: '12-06-2026', checkins: 14, guests: 28, checkouts: 11, cleanNeeded: 12 },
  { date: '13-06-2026', checkins: 19, guests: 42, checkouts: 15, cleanNeeded: 18 },
  { date: '14-06-2026', checkins: 22, guests: 48, checkouts: 20, cleanNeeded: 21 },
  { date: '15-06-2026', checkins: 16, guests: 31, checkouts: 14, cleanNeeded: 15 },
  { date: '16-06-2026', checkins: 12, guests: 22, checkouts: 18, cleanNeeded: 14 }
]

// 3. MOCK CANCEL DATA
const cancelKPIs = [
  { name: 'Đặt phòng bị hủy', value: '12 lượt', change: '-2 lượt', up: true, desc: 'Giảm so với tuần trước' },
  { name: 'Tỷ lệ hủy phòng', value: '4.2%', change: '-0.8%', up: true, desc: 'Tỷ lệ trên tổng số phòng đặt' },
  { name: 'Doanh thu thất thoát', value: '18.400.000 đ', change: '-15.2%', up: true, desc: 'Ước tính từ các booking bị hủy' },
  { name: 'Yêu cầu xóa giao dịch', value: '3 yêu cầu', change: '+1 lượt', up: false, desc: 'Giao dịch đã lập bị xóa bỏ' }
]

const cancelReasons = [
  { reason: 'Thay đổi kế hoạch du lịch/cá nhân', count: 5, percentage: '41.7%', color: 'bg-red-500' },
  { reason: 'Thời tiết xấu / Trễ chuyến bay', count: 3, percentage: '25.0%', color: 'bg-amber-500' },
  { reason: 'Đăng ký sai thông tin / Trùng lặp', count: 2, percentage: '16.7%', color: 'bg-blue-500' },
  { reason: 'Không lý do cụ thể (No-show)', count: 2, percentage: '16.7%', color: 'bg-slate-400' }
]

const cancelledReservations = [
  { id: 'BK-3918', guest: 'Mr. David Lee', roomType: 'Deluxe Double', date: '08-06-2026', refund: '500.000 đ', reason: 'Thay đổi lịch bay' },
  { id: 'BK-3940', guest: 'Ms. Nguyen Kim Chi', roomType: 'Executive Suite', date: '10-06-2026', refund: '0 đ', reason: 'Hủy sát giờ (No-show)' },
  { id: 'BK-3955', guest: 'Mr. Park Jung Woo', roomType: 'Standard Twin', date: '12-06-2026', refund: '1.200.000 đ', reason: 'Lý do sức khỏe' },
  { id: 'BK-3968', guest: 'Ms. Tran Thu Ha', roomType: 'Deluxe Twin', date: '15-06-2026', refund: '450.000 đ', reason: 'Đổi sang khách sạn khác' }
]

// 4. MOCK MANAGEMENT DATA
const manageKPIs = [
  { name: 'Ca làm việc đã chốt', value: '18 ca', change: '100%', up: true, desc: 'Không có ca lỗi bàn giao' },
  { name: 'Sự cố phòng phát sinh', value: '2 sự cố', change: '-4 lượt', up: true, desc: 'Thiết bị hư hỏng cần bảo trì' },
  { name: 'Đánh giá hài lòng', value: '4.8 / 5.0', change: '+0.2', up: true, desc: 'Từ khảo sát check-out của khách' },
  { name: 'Hiệu suất nhân viên', value: '96.5%', change: '+1.5%', up: true, desc: 'Tỷ lệ hoàn thành công việc đúng giờ' }
]

const staffPerformance = [
  { staff: 'Lê Thị Mai (Lễ tân)', shift: 'Ca sáng', rating: '4.9', tasks: '12/12', status: 'Hoạt động' },
  { staff: 'Trần Văn Hùng (Buồng phòng)', shift: 'Ca chiều', rating: '4.7', tasks: '18/19', status: 'Hoạt động' },
  { staff: 'Nguyễn Tuấn Anh (Lễ tân)', shift: 'Ca tối', rating: '4.8', tasks: '8/8', status: 'Ngoại tuyến' },
  { staff: 'Phạm Minh Trí (Giám sát)', shift: 'Hành chính', rating: '5.0', tasks: '15/15', status: 'Hoạt động' }
]

// 5. AUDIT HISTORY LOGS
const auditLogs = [
  { time: '16-06-2026 10:42', user: 'Lễ tân Ca 2', action: 'Hủy phòng đặt', details: 'BK-3968 - Khách Tran Thu Ha (Lý do: Đổi khách sạn)', type: 'cancel' },
  { time: '16-06-2026 09:15', user: 'Admin', action: 'Cập nhật giá phòng', details: 'Deluxe Double tăng 50.000 đ cho ngày cuối tuần', type: 'manage' },
  { time: '15-06-2026 21:30', user: 'Lễ tân Ca 3', action: 'Thanh toán hóa đơn', details: 'INV-9024 - Phòng 204 - Trả 980.000 đ tiền mặt', type: 'revenue' },
  { time: '15-06-2026 18:10', user: 'Buồng phòng', action: 'Đổi trạng thái phòng', details: 'Phòng 304 sửa chữa hoàn tất -> Sẵn sàng', type: 'manage' },
  { time: '15-06-2026 14:00', user: 'Lễ tân Ca 2', action: 'Tạo đặt phòng mới', details: 'BK-4840 - Khách Ms. Kovaleva Natalia (Deluxe Twin)', type: 'revenue' },
  { time: '14-06-2026 11:20', user: 'Lễ tân Ca 1', action: 'Xóa giao dịch phụ thu', details: 'Xóa hóa đơn phụ thu giặt ủi phòng 105 (nhập nhầm)', type: 'cancel' }
]
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 animate-in">
    <!-- Reports Content Panel -->
    <div class="flex-1 p-6 flex flex-col gap-6 overflow-y-auto">
      
      <!-- PAGE HEADER: Showing Active Selection -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
        <div>
          <h1 class="text-xl font-black text-slate-800 uppercase tracking-wide flex items-center gap-2">
            <span class="w-2.5 h-6 bg-sky-500 rounded-full"></span>
            {{ 
              currentType === 'revenue' ? 'Báo cáo doanh thu' :
              currentType === 'stats' ? 'Báo cáo thống kê hoạt động' :
              currentType === 'cancel' ? 'Báo cáo hủy / xóa phòng đặt' : 'Báo cáo quản lý chung' 
            }}
          </h1>
          <p class="text-xs text-slate-500 font-bold mt-1 uppercase tracking-wider">
            Tab hiện tại: {{ 
              currentTab === 'overview' ? 'Tổng quan chi tiết' :
              currentTab === 'manage-rooms' ? 'Báo cáo buồng phòng' :
              currentTab === 'create-res' ? 'Lịch sử nhận/đặt phòng' :
              currentTab === 'checkout' ? 'Báo cáo thanh toán & trả phòng' :
              currentTab === 'reports' ? 'Trình xuất báo cáo' : 'Lịch sử thao tác hệ thống'
            }}
          </p>
        </div>

        <!-- Date Filters & Export Action -->
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-2">
            <input 
              type="date" 
              v-model="startDate" 
              class="text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
            />
            <span class="text-slate-400 font-bold text-xs">đến</span>
            <input 
              type="date" 
              v-model="endDate" 
              class="text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
            />
          </div>
          <button 
            @click="handleExport"
            class="px-4 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-extrabold shadow-sm transition-all duration-200 active:scale-95 flex items-center gap-1.5 cursor-pointer border-none"
            :disabled="isExporting"
          >
            <svg v-if="isExporting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span>{{ isExporting ? 'Đang xuất...' : 'Tải xuống' }}</span>
          </button>
        </div>
      </div>

      <!-- VIEW STATE 1: OVERVIEW (Tổng Quan) -->
      <div v-if="currentTab === 'overview'" class="flex flex-col gap-6">
        
        <!-- KPI METRICS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div 
            v-for="kpi in (
              currentType === 'revenue' ? revenueKPIs :
              currentType === 'stats' ? statsKPIs :
              currentType === 'cancel' ? cancelKPIs : manageKPIs
            )" 
            :key="kpi.name"
            class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex flex-col justify-between hover:shadow-md transition-shadow"
          >
            <div>
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">{{ kpi.name }}</span>
              <span class="text-2xl font-black text-slate-800 tracking-tight block">{{ kpi.value }}</span>
            </div>
            <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-slate-100 text-[11px]">
              <span 
                class="font-black px-1.5 py-0.5 rounded-sm"
                :class="kpi.up ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500'"
              >
                {{ kpi.change }}
              </span>
              <span class="text-slate-500 font-medium truncate">{{ kpi.desc }}</span>
            </div>
          </div>
        </div>

        <!-- INTERACTIVE CHART & VISUALIZATION ROW -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Column 1 & 2: Graphical representation -->
          <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex flex-col h-[320px]">
            <div class="flex justify-between items-center mb-4">
              <span class="text-sm font-black uppercase text-slate-700 tracking-wider">Xu hướng phân tích (Tháng 6)</span>
              <span class="text-xs font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded">Cập nhật hàng ngày</span>
            </div>
            
            <!-- Dynamic Custom Bar Graphic representing Trends -->
            <div class="flex-1 flex items-end justify-between gap-3 px-2 pt-6">
              <div 
                v-for="(stat, idx) in [35, 52, 68, 75, 62, 85, 92, 70, 60, 80, 89, 95]" 
                :key="idx"
                class="flex-1 flex flex-col items-center gap-2 group cursor-pointer"
              >
                <div class="w-full relative bg-slate-100 rounded-md overflow-hidden h-40">
                  <div 
                    class="absolute bottom-0 left-0 right-0 rounded-md transition-all duration-500 group-hover:opacity-90"
                    :class="[
                      currentType === 'revenue' ? 'bg-sky-500' :
                      currentType === 'stats' ? 'bg-emerald-500' :
                      currentType === 'cancel' ? 'bg-rose-500' : 'bg-indigo-500'
                    ]"
                    :style="{ height: `${stat}%` }"
                  ></div>
                  <!-- Tooltip -->
                  <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] font-bold px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">
                    {{ stat }}%
                  </div>
                </div>
                <span class="text-[10px] font-bold text-slate-400">T{{ idx + 1 }}</span>
              </div>
            </div>
          </div>

          <!-- Column 3: Distribution Breakdown -->
          <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs flex flex-col">
            <span class="text-sm font-black uppercase text-slate-700 tracking-wider mb-4">Phân bổ chi tiết</span>
            
            <div class="flex-1 flex flex-col justify-center gap-4.5">
              <!-- If Cancel Category, display cancellation reasons -->
              <template v-if="currentType === 'cancel'">
                <div v-for="item in cancelReasons" :key="item.reason" class="flex flex-col gap-1.5">
                  <div class="flex justify-between text-xs font-bold text-slate-600">
                    <span class="truncate pr-2">{{ item.reason }}</span>
                    <span>{{ item.count }} lượt ({{ item.percentage }})</span>
                  </div>
                  <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" :class="item.color" :style="{ width: item.percentage }"></div>
                  </div>
                </div>
              </template>

              <!-- Default categories: Revenue/Stats room ratios -->
              <template v-else>
                <div v-for="item in [
                  { label: 'Deluxe (Cao Cấp)', val: '45%', amount: '95.600.000 đ', color: 'bg-sky-500' },
                  { label: 'Standard (Thường)', val: '30%', amount: '63.750.000 đ', color: 'bg-emerald-500' },
                  { label: 'Suite (Thương Gia)', val: '18%', amount: '38.250.000 đ', color: 'bg-indigo-500' },
                  { label: 'Presidential (Tổng Thống)', val: '7%', amount: '14.900.000 đ', color: 'bg-amber-500' }
                ]" :key="item.label" class="flex flex-col gap-1.5">
                  <div class="flex justify-between text-xs font-bold text-slate-600">
                    <span>{{ item.label }}</span>
                    <span>{{ item.val }} - {{ item.amount }}</span>
                  </div>
                  <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" :class="item.color" :style="{ width: item.val }"></div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- RECENT AUDIT LIST (Bottom) -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs">
          <div class="flex justify-between items-center mb-4">
            <span class="text-sm font-black uppercase text-slate-700 tracking-wider">Lịch sử giao dịch & cập nhật gần đây</span>
            <button 
              @click="router.push({ query: { ...route.query, tab: 'history' } })"
              class="text-xs font-bold text-sky-600 hover:text-sky-700 bg-transparent border-none cursor-pointer"
            >
              Xem tất cả
            </button>
          </div>
          <div class="divide-y divide-slate-100">
            <div 
              v-for="(log, idx) in auditLogs.slice(0, 4)" 
              :key="idx" 
              class="py-3.5 flex items-center justify-between gap-4"
            >
              <div class="flex items-center gap-3">
                <!-- Mini icon tag -->
                <span 
                  class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                  :class="[
                    log.type === 'cancel' ? 'bg-red-50 text-red-500' :
                    log.type === 'revenue' ? 'bg-sky-50 text-sky-500' : 'bg-slate-100 text-slate-500'
                  ]"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                </span>
                <div>
                  <span class="text-xs font-bold text-slate-800">{{ log.action }}</span>
                  <span class="text-[11px] text-slate-400 font-bold block mt-0.5">{{ log.details }}</span>
                </div>
              </div>
              <div class="text-right whitespace-nowrap">
                <span class="text-[11px] font-bold text-slate-500 block">{{ log.user }}</span>
                <span class="text-[10px] text-slate-400 block mt-0.5">{{ log.time }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- VIEW STATE 2: TABULAR DETAILS -->
      <div v-else class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden flex flex-col flex-1">
        
        <!-- SUB-TAB 2.1: QUẢN LÝ PHÒNG (Room breakdown / Floor breakdown) -->
        <template v-if="currentTab === 'manage-rooms'">
          <!-- If STATS category, show floor metrics -->
          <div v-if="currentType === 'stats'" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Khu vực / Tầng</th>
                  <th class="p-3 text-center">Phòng sạch</th>
                  <th class="p-3 text-center">Phòng bẩn</th>
                  <th class="p-3 text-center">Phòng đang bảo trì</th>
                  <th class="p-3 text-center">Tổng số phòng</th>
                  <th class="p-3 text-center">Tỷ lệ sẵn sàng</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="stat in floorStats" :key="stat.floor" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-900 font-black text-sm">{{ stat.floor }}</td>
                  <td class="p-3 text-center text-emerald-600">{{ stat.clean }}</td>
                  <td class="p-3 text-center text-amber-500">{{ stat.dirty }}</td>
                  <td class="p-3 text-center text-rose-500">{{ stat.maintenance }}</td>
                  <td class="p-3 text-center">{{ stat.total }}</td>
                  <td class="p-3 text-center">
                    <span class="bg-sky-50 text-sky-700 px-2 py-0.5 rounded text-[11px] font-black border border-sky-100">
                      {{ stat.rate }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- If REVENUE or default, show room type revenue -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Số phòng</th>
                  <th class="p-3">Loại phòng</th>
                  <th class="p-3 text-center">Số đêm đã bán</th>
                  <th class="p-3 text-right">Đơn giá trung bình</th>
                  <th class="p-3 text-center">Tỷ lệ công suất</th>
                  <th class="p-3 text-right">Tổng doanh thu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="room in roomRevenueBreakdown" :key="room.room" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-900 font-black text-sm">{{ room.room }}</td>
                  <td class="p-3">{{ room.type }}</td>
                  <td class="p-3 text-center">{{ room.nights }} đêm</td>
                  <td class="p-3 text-right">{{ room.rate }} đ</td>
                  <td class="p-3 text-center text-sky-600">{{ room.occupancy }}</td>
                  <td class="p-3 text-right text-slate-900 font-black">{{ room.total }} đ</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- SUB-TAB 2.2: TẠO ĐĂNG KÝ (Registrations/Reservations) -->
        <template v-if="currentTab === 'create-res'">
          <!-- If CANCEL category, show cancelled booking details -->
          <div v-if="currentType === 'cancel'" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Mã hủy</th>
                  <th class="p-3">Tên khách hàng</th>
                  <th class="p-3">Loại phòng</th>
                  <th class="p-3 text-center">Ngày yêu cầu</th>
                  <th class="p-3 text-right">Hoàn cọc</th>
                  <th class="p-3">Lý do hủy</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="res in cancelledReservations" :key="res.id" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-rose-600 font-black text-sm">{{ res.id }}</td>
                  <td class="p-3 text-slate-900">{{ res.guest }}</td>
                  <td class="p-3">{{ res.roomType }}</td>
                  <td class="p-3 text-center">{{ res.date }}</td>
                  <td class="p-3 text-right text-emerald-600">{{ res.refund }}</td>
                  <td class="p-3 text-slate-500 font-medium truncate max-w-[200px]">{{ res.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- If STATS category, show check-in activities stats -->
          <div v-else-if="currentType === 'stats'" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Ngày</th>
                  <th class="p-3 text-center">Tổng check-in</th>
                  <th class="p-3 text-center">Số khách lưu trú</th>
                  <th class="p-3 text-center">Dự kiến check-out</th>
                  <th class="p-3 text-center">Dọn dẹp phát sinh</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="day in checkinStatsData" :key="day.date" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-900 font-black">{{ day.date }}</td>
                  <td class="p-3 text-center text-sky-600">{{ day.checkins }} phòng</td>
                  <td class="p-3 text-center">{{ day.guests }} khách</td>
                  <td class="p-3 text-center text-amber-500">{{ day.checkouts }} phòng</td>
                  <td class="p-3 text-center text-rose-500">{{ day.cleanNeeded }} phòng</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Default: Show deposit logs -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Mã đặt phòng</th>
                  <th class="p-3">Tên khách hàng</th>
                  <th class="p-3 text-center">Ngày đặt cọc</th>
                  <th class="p-3 text-right">Số tiền đặt cọc</th>
                  <th class="p-3">Phương thức</th>
                  <th class="p-3 text-center">Trạng thái</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="dep in depositData" :key="dep.id" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-900 font-black text-sm">{{ dep.id }}</td>
                  <td class="p-3 text-slate-800">{{ dep.guest }}</td>
                  <td class="p-3 text-center">{{ dep.date }}</td>
                  <td class="p-3 text-right text-slate-900">{{ dep.amount }} đ</td>
                  <td class="p-3">{{ dep.type }}</td>
                  <td class="p-3 text-center">
                    <span 
                      class="px-2 py-0.5 rounded text-[10px] font-black border"
                      :class="dep.status === 'Đã nhận' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'"
                    >
                      {{ dep.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- SUB-TAB 2.3: TRẢ PHÒNG (Checkout settlements & Invoices) -->
        <template v-if="currentTab === 'checkout'">
          <!-- If MANAGEMENT category, show staff shifts list -->
          <div v-if="currentType === 'manage'" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Nhân sự thực hiện</th>
                  <th class="p-3">Ca trực bàn giao</th>
                  <th class="p-3 text-center">Đánh giá trung bình</th>
                  <th class="p-3 text-center">Tác vụ hoàn thành</th>
                  <th class="p-3 text-center">Trạng thái hệ thống</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="staff in staffPerformance" :key="staff.staff" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-900 font-black">{{ staff.staff }}</td>
                  <td class="p-3">{{ staff.shift }}</td>
                  <td class="p-3 text-center text-amber-500">{{ staff.rating }} ★</td>
                  <td class="p-3 text-center text-sky-600">{{ staff.tasks }}</td>
                  <td class="p-3 text-center">
                    <span 
                      class="px-2 py-0.5 rounded text-[10px] font-black"
                      :class="staff.status === 'Hoạt động' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                    >
                      {{ staff.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Default: Show checkout bills list -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3">Số hóa đơn</th>
                  <th class="p-3 text-center">Số phòng</th>
                  <th class="p-3">Tên khách hàng</th>
                  <th class="p-3">Thanh toán</th>
                  <th class="p-3 text-right">Tổng thanh toán</th>
                  <th class="p-3">Thu ngân chốt ca</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="bill in checkoutBillingData" :key="bill.invoice" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-sky-600 font-black text-sm">{{ bill.invoice }}</td>
                  <td class="p-3 text-center">{{ bill.room }}</td>
                  <td class="p-3 text-slate-900">{{ bill.guest }}</td>
                  <td class="p-3">{{ bill.payment }}</td>
                  <td class="p-3 text-right text-slate-900 font-black">{{ bill.total }} đ</td>
                  <td class="p-3 text-slate-500 font-medium">{{ bill.cashier }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- SUB-TAB 2.4: TRÌNH XUẤT BÁO CÁO (Reports Exporter Form) -->
        <template v-if="currentTab === 'reports'">
          <div class="p-8 max-w-2xl mx-auto flex flex-col gap-6 w-full">
            <h2 class="text-sm font-black uppercase text-slate-700 tracking-wider">Trình biên dịch & Xuất bản báo cáo</h2>
            
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[11px] font-extrabold text-slate-400 uppercase">Định dạng file xuất</label>
                <select v-model="selectedExportType" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500">
                  <option value="pdf">Tập tin PDF (.pdf)</option>
                  <option value="xlsx">Bảng tính Excel (.xlsx)</option>
                  <option value="csv">Dữ liệu CSV (.csv)</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[11px] font-extrabold text-slate-400 uppercase">Đối tượng phân loại</label>
                <select class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500">
                  <option>Toàn bộ hoạt động khách sạn</option>
                  <option>Riêng doanh thu dịch vụ phòng</option>
                  <option>Riêng các sự vụ sửa chữa & bảo trì</option>
                </select>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-[11px] font-extrabold text-slate-400 uppercase">Ghi chú chân trang báo cáo (Footer)</label>
              <textarea placeholder="Nhập ghi chú hoặc chữ ký phê duyệt báo cáo..." rows="3" class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"></textarea>
            </div>

            <button 
              @click="handleExport"
              class="w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-black shadow-sm transition-colors cursor-pointer border-none flex items-center justify-center gap-2"
              :disabled="isExporting"
            >
              <svg v-if="isExporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isExporting ? 'Đang thực hiện xuất file...' : 'Tạo Báo Cáo & Tải Về' }}</span>
            </button>
          </div>
        </template>

        <!-- SUB-TAB 2.5: LỊCH SỬ THAO TÁC (Audit Trails) -->
        <template v-if="currentTab === 'history'">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-10">
                  <th class="p-3 w-[150px]">Thời gian</th>
                  <th class="p-3 w-[120px]">Người thực hiện</th>
                  <th class="p-3 w-[150px]">Hành vi thao tác</th>
                  <th class="p-3">Chi tiết nội dung cập nhật</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="(log, idx) in auditLogs" :key="idx" class="hover:bg-slate-50 h-11">
                  <td class="p-3 text-slate-400">{{ log.time }}</td>
                  <td class="p-3 text-slate-900">{{ log.user }}</td>
                  <td class="p-3">
                    <span 
                      class="px-2 py-0.5 rounded text-[10px] font-black border"
                      :class="[
                        log.type === 'cancel' ? 'bg-rose-50 text-rose-600 border-rose-100' :
                        log.type === 'revenue' ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-50 text-slate-500 border-slate-200'
                      ]"
                    >
                      {{ log.action }}
                    </span>
                  </td>
                  <td class="p-3 text-slate-500 font-medium truncate max-w-[400px]">{{ log.details }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
