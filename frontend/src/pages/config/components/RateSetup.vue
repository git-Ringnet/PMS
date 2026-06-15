<script setup>
import { ref, reactive, watch, computed } from 'vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Mã giá phòng'
  }
})

const emit = defineEmits(['update:activeTab'])

const activeRateTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeRateTab.value = newVal
  }
})

watch(activeRateTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const rateTabs = ['Mã giá phòng', 'Gói dịch vụ']

// --- MOCK DATA FOR TABS ---

// 1. Tab Mã giá phòng: Rate Codes List
const rateCodes = ref([
  { id: 'B2B', code: 'B2B', description: 'B2B', currency: 'VND', fromDate: '20/05/2025', toDate: '31/12/2026', isDaily: false, isInactive: false, isAllowManual: false, isPushChannel: false, isBreakfast: false },
  { id: 'FOC', code: 'FOC', description: 'FOC', currency: 'VND', fromDate: '06/02/2025', toDate: '01/02/2026', isDaily: false, isInactive: false, isAllowManual: false, isPushChannel: false, isBreakfast: false },
  { id: 'ROB2B', code: 'ROB2B', description: 'RO-B2B', currency: 'VND', fromDate: '02/01/2026', toDate: '31/12/2026', isDaily: false, isInactive: false, isAllowManual: false, isPushChannel: false, isBreakfast: false },
  { id: 'ROBAR', code: 'ROBAR', description: 'RO-BAR', currency: 'VND', fromDate: '02/01/2026', toDate: '31/12/2026', isDaily: false, isInactive: false, isAllowManual: false, isPushChannel: false, isBreakfast: false },
  { id: 'ST', code: 'ST', description: 'BAR', currency: 'VND', fromDate: '01/01/2025', toDate: '31/12/2026', isDaily: false, isInactive: false, isAllowManual: false, isPushChannel: false, isBreakfast: true }
])

const selectedRateCode = ref(rateCodes.value[0])

// Selected Rate Code Form Fields
const rateFormState = reactive({
  code: 'B2B',
  description: 'B2B',
  fromDate: '20/05/2025',
  toDate: '31/12/2026',
  currency: 'VND',
  isDaily: false,
  isInactive: false,
  isAllowManual: false,
  isPushChannel: false,
  isBreakfast: false
})

// Sync form with selected row
watch(selectedRateCode, (newVal) => {
  if (newVal) {
    Object.assign(rateFormState, {
      code: newVal.code,
      description: newVal.description,
      fromDate: newVal.fromDate,
      toDate: newVal.toDate,
      currency: newVal.currency,
      isDaily: newVal.isDaily,
      isInactive: newVal.isInactive,
      isAllowManual: newVal.isAllowManual,
      isPushChannel: newVal.isPushChannel,
      isBreakfast: newVal.isBreakfast
    })
  }
}, { immediate: true })

const selectRateCode = (rc) => {
  selectedRateCode.value = rc
}

// Room Types Matrix list
const roomTypes = ref([
  { code: 'SUPD', description: 'Superior Double' },
  { code: 'SUPT', description: 'Superior Twin' },
  { code: 'SUPTR', description: 'Superior Triple' },
  { code: 'DLXD', description: 'Deluxe Double City view' },
  { code: 'DLXT', description: 'Deluxe Twin City View' },
  { code: 'DLXDB', description: 'Deluxe Double with Balcony' },
  { code: 'DLXTB', description: 'Deluxe Twin with Balcony' },
  { code: 'FAM', description: 'Family City View' },
  { code: 'JST', description: 'Suite' },
  { code: 'DP', description: 'DỰ PHÒNG' }
])

// Rates values stored per RateCode + RoomType + Occupancy
const rateMatrix = reactive({
  // B2B rates
  'B2B_SUPD_Double': '1.200.000', 'B2B_SUPD_Twin': '1.200.000', 'B2B_SUPD_Triple': '', 'B2B_SUPD_Family': '', 'B2B_SUPD_King': '',
  'B2B_SUPT_Double': '', 'B2B_SUPT_Twin': '1.250.000', 'B2B_SUPT_Triple': '', 'B2B_SUPT_Family': '', 'B2B_SUPT_King': '',
  'B2B_SUPTR_Double': '', 'B2B_SUPTR_Twin': '', 'B2B_SUPTR_Triple': '1.500.000', 'B2B_SUPTR_Family': '', 'B2B_SUPTR_King': '',
  'B2B_DLXD_Double': '1.400.000', 'B2B_DLXD_Twin': '', 'B2B_DLXD_Triple': '', 'B2B_DLXD_Family': '', 'B2B_DLXD_King': '',
  'B2B_DLXT_Double': '', 'B2B_DLXT_Twin': '1.450.000', 'B2B_DLXT_Triple': '', 'B2B_DLXT_Family': '', 'B2B_DLXT_King': '',
  'B2B_DLXDB_Double': '1.600.000', 'B2B_DLXDB_Twin': '', 'B2B_DLXDB_Triple': '', 'B2B_DLXDB_Family': '', 'B2B_DLXDB_King': '',
  'B2B_DLXTB_Double': '', 'B2B_DLXTB_Twin': '1.650.000', 'B2B_DLXTB_Triple': '', 'B2B_DLXTB_Family': '', 'B2B_DLXTB_King': '',
  'B2B_FAM_Double': '', 'B2B_FAM_Twin': '', 'B2B_FAM_Triple': '', 'B2B_FAM_Family': '2.200.000', 'B2B_FAM_King': '',
  'B2B_JST_Double': '', 'B2B_JST_Twin': '', 'B2B_JST_Triple': '', 'B2B_JST_Family': '', 'B2B_JST_King': '3.000.000',
  'B2B_DP_Double': '', 'B2B_DP_Twin': '', 'B2B_DP_Triple': '', 'B2B_DP_Family': '', 'B2B_DP_King': '',

  // ROBAR rates
  'ROBAR_SUPD_Double': '1.500.000', 'ROBAR_SUPD_Twin': '1.500.000', 'ROBAR_SUPD_Triple': '', 'ROBAR_SUPD_Family': '', 'ROBAR_SUPD_King': '',
  'ROBAR_SUPT_Double': '', 'ROBAR_SUPT_Twin': '1.550.000', 'ROBAR_SUPT_Triple': '', 'ROBAR_SUPT_Family': '', 'ROBAR_SUPT_King': '',
  'ROBAR_SUPTR_Double': '', 'ROBAR_SUPTR_Twin': '', 'ROBAR_SUPTR_Triple': '1.900.000', 'ROBAR_SUPTR_Family': '', 'ROBAR_SUPTR_King': '',
  'ROBAR_DLXD_Double': '1.800.000', 'ROBAR_DLXD_Twin': '', 'ROBAR_DLXD_Triple': '', 'ROBAR_DLXD_Family': '', 'ROBAR_DLXD_King': '',
  'ROBAR_DLXT_Double': '', 'ROBAR_DLXT_Twin': '1.850.000', 'ROBAR_DLXT_Triple': '', 'ROBAR_DLXT_Family': '', 'ROBAR_DLXT_King': '',
  'ROBAR_DLXDB_Double': '2.000.000', 'ROBAR_DLXDB_Twin': '', 'ROBAR_DLXDB_Triple': '', 'ROBAR_DLXDB_Family': '', 'ROBAR_DLXDB_King': '',
  'ROBAR_DLXTB_Double': '', 'ROBAR_DLXTB_Twin': '2.050.000', 'ROBAR_DLXTB_Triple': '', 'ROBAR_DLXTB_Family': '', 'ROBAR_DLXTB_King': '',
  'ROBAR_FAM_Double': '', 'ROBAR_FAM_Twin': '', 'ROBAR_FAM_Triple': '', 'ROBAR_FAM_Family': '2.800.000', 'ROBAR_FAM_King': '',
  'ROBAR_JST_Double': '', 'ROBAR_JST_Twin': '', 'ROBAR_JST_Triple': '', 'ROBAR_JST_Family': '', 'ROBAR_JST_King': '4.000.000',
  'ROBAR_DP_Double': '', 'ROBAR_DP_Twin': '', 'ROBAR_DP_Triple': '', 'ROBAR_DP_Family': '', 'ROBAR_DP_King': '',
})

const getMatrixKey = (roomCode, occupancy) => {
  return `${selectedRateCode.value?.id || 'B2B'}_${roomCode}_${occupancy}`
}

// 2. Tab Gói dịch vụ: Service Packages List & Package Details
const servicePackages = ref([
  { id: 'PKG1', code: 'PKG01', currency: 'VND', nights: 1, displayPrice: '200.000', startDate: '01/01/2026', endDate: '31/12/2026', inactive: false, daysOfWeek: 'Thứ 2, Thứ 3, Thứ 4, Thứ 5, Thứ 6, Thứ 7, Chủ Nhật', description: 'Gói ăn sáng Buffet' },
  { id: 'PKG2', code: 'PKG02', currency: 'VND', nights: 1, displayPrice: '450.000', startDate: '01/01/2026', endDate: '31/12/2026', inactive: false, daysOfWeek: 'Thứ 2, Thứ 3, Thứ 4, Thứ 5, Thứ 6, Thứ 7, Chủ Nhật', description: 'Gói ăn tối Set menu' },
  { id: 'PKG3', code: 'PKG03', currency: 'VND', nights: 2, displayPrice: '900.000', startDate: '15/02/2026', endDate: '15/02/2027', inactive: false, daysOfWeek: 'Thứ 6, Thứ 7, Chủ Nhật', description: 'Combo Cuối tuần Spa & Dining' }
])

const selectedPackage = ref(servicePackages.value[0])

const packageDetails = computed(() => {
  if (!selectedPackage.value) return []
  if (selectedPackage.value.id === 'PKG1') {
    return [
      { id: 'd1', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Buffet sáng người lớn', department: 'Restaurant/Nhà Hàng', mealPlan: 'Breakfast', amount: '150.000', calculatedOn: 'Người/Đêm', roomClass: 'Tất cả' },
      { id: 'd2', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Buffet sáng trẻ em', department: 'Restaurant/Nhà Hàng', mealPlan: 'Breakfast', amount: '50.000', calculatedOn: 'Trẻ em/Đêm', roomClass: 'Tất cả' }
    ]
  } else if (selectedPackage.value.id === 'PKG2') {
    return [
      { id: 'd3', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Set ăn tối Deluxe', department: 'Restaurant/Nhà Hàng', mealPlan: 'Dinner', amount: '450.000', calculatedOn: 'Người/Đêm', roomClass: 'Tất cả' }
    ]
  } else {
    return [
      { id: 'd4', validity: 'Theo ngày ở', fromDate: '15/02/2026', endDate: '15/02/2027', service: 'Liệu trình Spa thư giãn', department: 'Spa', mealPlan: 'None', amount: '500.000', calculatedOn: 'Khách/Lần', roomClass: 'Suite' },
      { id: 'd5', validity: 'Theo ngày ở', fromDate: '15/02/2026', endDate: '15/02/2027', service: 'Set ăn tối đặc biệt', department: 'Restaurant/Nhà Hàng', mealPlan: 'Dinner', amount: '400.000', calculatedOn: 'Người/Đêm', roomClass: 'Suite' }
    ]
  }
})

// Calculate total sum of package details
const totalPackageDetailsAmount = computed(() => {
  return packageDetails.value.reduce((sum, item) => {
    const numeric = parseInt(item.amount.replace(/\./g, ''), 10) || 0
    return sum + numeric
  }, 0)
})

const formatNumber = (num) => {
  return new Intl.NumberFormat('vi-VN').format(num)
}

const selectPackage = (pkg) => {
  selectedPackage.value = pkg
}
</script>

<template>
  <div class="flex-1 flex flex-col gap-4 text-slate-800">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button 
          v-for="tab in rateTabs" 
          :key="tab"
          @click="activeRateTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeRateTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- MAIN VIEW WRAPPER -->
    <div class="flex-1 min-h-0 flex flex-col gap-4">
      
      <!-- ================= TAB 1: MÃ GIÁ PHÒNG ================= -->
      <div v-if="activeRateTab === 'Mã giá phòng'" class="flex-1 flex flex-col gap-4 min-h-0">
        
        <!-- Top Half: Table on left (40%), form on right (60%) -->
        <div class="flex flex-col lg:flex-row gap-4 h-[350px] shrink-0 min-h-0">
          
          <!-- Left Table (40%) -->
          <div class="w-full lg:w-[42%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
            <div class="overflow-y-auto flex-1">
              <table class="w-full text-xs text-left border-collapse border border-slate-200">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase sticky top-0 z-10">
                    <th class="p-2 border border-slate-200 w-16">Mã</th>
                    <th class="p-2 border border-slate-200">Mô tả</th>
                    <th class="p-2 border border-slate-200 w-16 text-center">Tiền tệ</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Từ ngày</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Đến ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="rc in rateCodes" 
                    :key="rc.id"
                    @click="selectRateCode(rc)"
                    class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors"
                    :class="selectedRateCode?.id === rc.id ? 'bg-sky-50/40 font-semibold text-sky-700' : 'text-slate-700'"
                  >
                    <td class="p-2 border border-slate-100 font-bold">{{ rc.code }}</td>
                    <td class="p-2 border border-slate-100">{{ rc.description }}</td>
                    <td class="p-2 border border-slate-100 text-center">{{ rc.currency }}</td>
                    <td class="p-2 border border-slate-100 text-center text-slate-500">{{ rc.fromDate }}</td>
                    <td class="p-2 border border-slate-100 text-center text-slate-500">{{ rc.toDate }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Form (58%) -->
          <div class="w-full lg:w-[58%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs justify-between">
            <!-- Form Action Buttons Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Chi tiết thiết lập giá</span>
              <div class="flex items-center gap-1.5">
                <!-- Search Mock Button -->
                <button class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 cursor-pointer bg-white">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </button>
                <!-- Add Button -->
                <button class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                  <span class="text-sm">+</span> Thêm
                </button>
                <!-- Save Button -->
                <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                  Lưu
                </button>
                <!-- Delete Button (using scoped CSS class to prevent HMR/Vite styles caching issues) -->
                <button class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                  Xóa
                </button>
              </div>
            </div>

            <!-- Form Content Fields stacked in rows matching mockup -->
            <div class="flex flex-col gap-3 py-3 overflow-y-auto flex-1">
              <!-- Row 1: Mã (1/3) & Mô tả (2/3) -->
              <div class="flex gap-4">
                <div class="w-1/3">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Mã</label>
                  <input 
                    type="text" 
                    v-model="rateFormState.code"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" 
                  />
                </div>
                <div class="w-2/3">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Mô tả</label>
                  <input 
                    type="text" 
                    v-model="rateFormState.description"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" 
                  />
                </div>
              </div>
              
              <!-- Row 2: Từ ngày - đến ngày (45%) & Tiền tệ (20%) & Ăn sáng checkbox (35%) -->
              <div class="flex gap-4 items-end">
                <div class="w-[45%]">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Từ ngày - đến ngày</label>
                  <div class="relative flex items-center">
                    <input 
                      type="text" 
                      :value="`${rateFormState.fromDate} ~ ${rateFormState.toDate}`"
                      class="w-full pl-3 pr-8 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold bg-white cursor-pointer"
                      readonly 
                    />
                    <svg class="w-4.5 h-4.5 absolute right-2 text-emerald-500 cursor-pointer pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                </div>

                <div class="w-[25%]">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Tiền tệ</label>
                  <div class="relative flex items-center">
                    <select 
                      v-model="rateFormState.currency"
                      class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold appearance-none bg-white"
                    >
                      <option value="VND">VND</option>
                      <option value="USD">USD</option>
                    </select>
                    <span class="absolute left-2.5 flex items-center text-xs font-black">🇻🇳</span>
                    <div class="pointer-events-none absolute right-2 flex items-center text-slate-400">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                  </div>
                </div>

                <div class="w-[30%] pb-2 pl-2">
                  <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" v-model="rateFormState.isBreakfast" class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                    Ăn sáng
                  </label>
                </div>
              </div>

              <!-- Row 3: Remaining Checkboxes Grouped Inline -->
              <div class="flex flex-wrap gap-x-5 gap-y-2 items-center pt-2 border-t border-slate-100">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.isDaily" class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Giá theo ngày
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.isInactive" class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Không sử dụng
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.isAllowManual" class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Cho phép nhập giá
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.isPushChannel" class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Đẩy lên Channel Manager
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Room rate matrix values with full table grid lines -->
        <div class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
          <div class="flex-1 overflow-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase sticky top-0 z-10 text-center">
                  <th class="p-2.5 text-left w-36 border border-slate-200 bg-slate-50">Loại phòng</th>
                  <th class="p-2.5 text-left w-48 border border-slate-200 bg-slate-50">Mô tả</th>
                  <th class="p-2.5 border border-slate-200 w-32">Double</th>
                  <th class="p-2.5 border border-slate-200 w-32">Twin</th>
                  <th class="p-2.5 border border-slate-200 w-32">Triple</th>
                  <th class="p-2.5 border border-slate-200 w-32">Family</th>
                  <th class="p-2.5 border border-slate-200 w-32">King</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="rt in roomTypes" 
                  :key="rt.code"
                  class="hover:bg-slate-50/40"
                >
                  <!-- Room type code -->
                  <td class="p-2.5 font-bold text-slate-700 border border-slate-200 bg-slate-50/20">{{ rt.code }}</td>
                  <!-- Room type description -->
                  <td class="p-2.5 text-slate-500 font-medium border border-slate-200">{{ rt.description }}</td>
                  
                  <!-- Occupancies pricing inputs inside grid -->
                  <td class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      v-model="rateMatrix[getMatrixKey(rt.code, 'Double')]"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                  <td class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      v-model="rateMatrix[getMatrixKey(rt.code, 'Twin')]"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                  <td class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      v-model="rateMatrix[getMatrixKey(rt.code, 'Triple')]"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                  <td class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      v-model="rateMatrix[getMatrixKey(rt.code, 'Family')]"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                  <td class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      v-model="rateMatrix[getMatrixKey(rt.code, 'King')]"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= TAB 2: GÓI DỊCH VỤ ================= -->
      <div v-else-if="activeRateTab === 'Gói dịch vụ'" class="flex-1 flex flex-col gap-4 min-h-0">
        
        <!-- Top Half: Gói dịch vụ list -->
        <div class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <h3 class="text-xs font-black text-slate-600 uppercase tracking-wide">Gói dịch vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Search Mock Button -->
              <button class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 cursor-pointer bg-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </button>
              <!-- Add Button -->
              <button class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                Sửa
              </button>
              <!-- Delete Button (using scoped CSS class) -->
              <button class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-3">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2.5 border border-slate-200 w-24">Mã</th>
                  <th class="p-2.5 border border-slate-200 w-20 text-center">Tiền tệ</th>
                  <th class="p-2.5 border border-slate-200 w-24 text-center">Số đêm</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-right">Giá hiển thị</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Ngày bắt đầu</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Ngày kết thúc</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Không sử dụng</th>
                  <th class="p-2.5 border border-slate-200">Theo ngày trong tuần</th>
                  <th class="p-2.5 border border-slate-200">Mô tả</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="pkg in servicePackages" 
                  :key="pkg.id"
                  @click="selectPackage(pkg)"
                  class="hover:bg-slate-50/70 cursor-pointer transition-colors"
                  :class="selectedPackage?.id === pkg.id ? 'bg-sky-50/40 font-semibold text-sky-700' : 'text-slate-700'"
                >
                  <td class="p-2.5 border border-slate-200 font-bold">{{ pkg.code }}</td>
                  <td class="p-2.5 border border-slate-200 text-center">{{ pkg.currency }}</td>
                  <td class="p-2.5 border border-slate-200 text-center">{{ pkg.nights }}</td>
                  <td class="p-2.5 border border-slate-200 text-right font-semibold text-sky-600">{{ pkg.displayPrice }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-slate-500">{{ pkg.startDate }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-slate-500">{{ pkg.endDate }}</td>
                  <td class="p-2.5 border border-slate-200 text-center">
                    <input 
                      type="checkbox" 
                      :checked="pkg.inactive" 
                      @click.stop 
                      class="rounded border-slate-300 text-sky-500 focus:ring-sky-400"
                    />
                  </td>
                  <td class="p-2.5 border border-slate-200 text-slate-500 truncate max-w-[200px]" :title="pkg.daysOfWeek">{{ pkg.daysOfWeek }}</td>
                  <td class="p-2.5 border border-slate-200 text-slate-600">{{ pkg.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Bottom Half: Chi tiết gói dịch vụ -->
        <div class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs justify-between">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <h3 class="text-xs font-black text-slate-600 uppercase tracking-wide">Chi tiết gói dịch vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Add Button -->
              <button class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                Sửa
              </button>
              <!-- Delete Button (using scoped CSS class) -->
              <button class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-3">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2.5 border border-slate-200">Thời gian hiệu lực</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Từ ngày</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Đến ngày</th>
                  <th class="p-2.5 border border-slate-200">Dịch vụ</th>
                  <th class="p-2.5 border border-slate-200">Bộ phận</th>
                  <th class="p-2.5 border border-slate-200 text-center">Gói bữa ăn</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-right">Số tiền</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Tính trên</th>
                  <th class="p-2.5 border border-slate-200">Loại phòng</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="detail in packageDetails" 
                  :key="detail.id"
                  class="border-b border-slate-100 hover:bg-slate-50/40 text-slate-700"
                >
                  <td class="p-2.5 border border-slate-200 font-medium">{{ detail.validity }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-slate-500">{{ detail.fromDate }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-slate-500">{{ detail.endDate }}</td>
                  <td class="p-2.5 border border-slate-200 font-bold text-slate-800">{{ detail.service }}</td>
                  <td class="p-2.5 border border-slate-200 text-slate-600">{{ detail.department }}</td>
                  <td class="p-2.5 border border-slate-200 text-center"><span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded text-[10px] font-bold uppercase">{{ detail.mealPlan }}</span></td>
                  <td class="p-2.5 border border-slate-200 text-right font-semibold text-slate-800">{{ detail.amount }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-slate-500">{{ detail.calculatedOn }}</td>
                  <td class="p-2.5 border border-slate-200 font-medium text-indigo-600">{{ detail.roomClass }}</td>
                </tr>
                <tr v-if="packageDetails.length === 0">
                  <td colspan="9" class="p-8 text-center text-slate-400">Không có dữ liệu chi tiết</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total Footer Row -->
          <div class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-2.5 flex justify-between items-center text-xs font-bold text-slate-700 shrink-0">
            <span>Tổng</span>
            <span class="text-sm text-sky-600 font-extrabold pr-32">{{ formatNumber(totalPackageDetailsAmount) }}</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Scoped Delete Button style to bypass tailwind overrides */
.btn-delete {
  background-color: #64748b !important;
  color: #ffffff !important;
  border: none !important;
}
.btn-delete:hover {
  background-color: #475569 !important;
}

/* Scrollbar customizations */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
