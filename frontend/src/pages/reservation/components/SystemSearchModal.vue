<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-xs flex justify-center items-start pt-16 z-[99999]"
    @click="close"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden border border-gray-200"
      @click.stop
    >
      <!-- INPUT HEADER -->
      <div class="flex items-center px-4 py-2.5 border-b border-gray-100">
        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <input 
          type="text" 
          id="gsInput"
          v-model="globalSearchQuery" 
          placeholder="Tìm kiếm" 
          class="flex-1 text-[13px] text-gray-800 placeholder-gray-400 outline-none bg-transparent" 
          autofocus
        />
        
        <!-- Toggle button date filter -->
        <button 
          @click="filterByArrivalDate = !filterByArrivalDate" 
          class="flex items-center space-x-1 px-2.5 py-1 rounded-md text-[10px] font-bold border transition cursor-pointer"
          :class="filterByArrivalDate ? 'bg-sky-50 text-sky-600 border-sky-200' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
        >
          <i class="fa-regular fa-calendar-days text-[11px]"></i>
          <span>Xem theo ngày đến</span>
        </button>

        <span class="w-[1px] h-4 bg-gray-200 mx-2.5"></span>

        <!-- Dropdown status -->
        <div class="relative select-none text-[11px] font-bold text-slate-700">
          <button 
            type="button" 
            @click="isStatusDropdownOpen = !isStatusDropdownOpen"
            class="flex items-center space-x-1.5 px-2.5 py-1 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-md cursor-pointer transition text-left"
          >
            <span>Tình trạng đặt phòng:</span>
            <span class="text-sky-600 font-extrabold max-w-[120px] truncate">{{ searchStatusLabel }}</span>
            <i class="fa-solid fa-chevron-down text-[9px] text-slate-400 ml-1"></i>
          </button>
          
          <div 
            v-if="isStatusDropdownOpen" 
            class="absolute right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg w-52 p-2 z-[1000] space-y-1.5"
          >
            <label class="flex items-center space-x-2 p-1.5 hover:bg-slate-50 rounded cursor-pointer select-none font-bold text-[10.5px]">
              <input type="checkbox" v-model="isAllStatusesChecked" class="rounded border-slate-300 w-3.5 h-3.5" />
              <span>Tất cả</span>
            </label>
            <hr class="border-slate-100 my-1" />
            <div class="max-h-48 overflow-y-auto space-y-1.5">
              <label 
                v-for="st in registrationStatuses" 
                :key="st.id" 
                class="flex items-center space-x-2 p-1 hover:bg-slate-50 rounded cursor-pointer select-none font-semibold text-[10.5px]"
              >
                <input type="checkbox" :value="st.id" v-model="searchStatuses" class="rounded border-slate-300 w-3.5 h-3.5" />
                <span :style="{ color: st.color || '#333' }">{{ st.name }}</span>
              </label>
            </div>
          </div>
        </div>

        <button @click="close" class="ml-3 text-[11px] font-bold text-gray-500 hover:text-gray-800 transition border-none bg-transparent cursor-pointer">Hủy</button>
      </div>

      <!-- DATE RANGE SELECTOR -->
      <div v-if="filterByArrivalDate" class="flex items-center px-4 py-2 bg-slate-50 border-b border-gray-100 space-x-3 text-xs font-bold text-slate-700">
        <div class="flex items-center space-x-2">
          <span class="text-slate-400 text-[10px] uppercase font-black">Từ ngày</span>
          <input 
            type="date" 
            v-model="searchFromDate" 
            class="border border-slate-200 rounded px-2 py-0.5 font-medium text-xs text-slate-800 bg-white"
          />
        </div>
        <div class="flex items-center space-x-2">
          <span class="text-slate-400 text-[10px] uppercase font-black">Đến ngày</span>
          <input 
            type="date" 
            v-model="searchToDate" 
            class="border border-slate-200 rounded px-2 py-0.5 font-medium text-xs text-slate-800 bg-white"
          />
        </div>
      </div>

      <!-- RESULTS / EMPTY STATE -->
      <div class="max-h-[350px] overflow-y-auto">
        <div v-if="globalSearchResults.length > 0">
          <table class="w-full text-left text-[11px] border-collapse">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider h-8">
                <th class="p-2 pl-4">Đoàn/Khách hàng</th>
                <th class="p-2">Ngày đến/đi</th>
                <th class="p-2">Tình trạng</th>
                <th class="p-2 text-right pr-4">Tổng tiền (VND)</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="b in globalSearchResults" 
                :key="b.id"
                class="border-b border-gray-50 hover:bg-slate-50/75 cursor-pointer font-medium text-gray-700 h-10 transition align-middle"
                @click="selectBooking(b)"
              >
                <td class="p-2 pl-4">
                  <div class="font-extrabold text-slate-800 flex items-center gap-1.5">
                    <span class="text-sky-600 font-black font-mono tracking-wide bg-sky-50 px-1 py-0.5 rounded border border-sky-100 text-[9px] uppercase">{{ b.booking_code }}</span>
                    <span>{{ b.booking_name }}</span>
                  </div>
                </td>
                <td class="p-2 font-mono text-[10px] text-slate-600 font-bold">
                  {{ formatDateVi(b.arrival_date || b.check_in) }} 
                  <span class="text-slate-400 font-normal">→</span> 
                  {{ formatDateVi(b.departure_date || b.check_out) }}
                </td>
                <td class="p-2">
                  <span 
                    class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase shadow-xs border"
                    :style="{
                      backgroundColor: (b.registration_status?.color || '#3b82f6') + '15',
                      color: b.registration_status?.color || '#3b82f6',
                      borderColor: (b.registration_status?.color || '#3b82f6') + '30'
                    }"
                  >
                    {{ b.registration_status?.name || 'BT' }}
                  </span>
                </td>
                <td class="p-2 text-right pr-4 font-mono font-black text-slate-900">
                  {{ Number(b.total_amount || 0).toLocaleString('vi-VN') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- EMPTY STATES -->
        <div v-else class="px-4 py-6 text-[11px] text-gray-400 text-center bg-white">
          <div v-if="globalSearchQuery.trim().length >= 2 || filterByArrivalDate">
            Không tìm thấy kết quả phù hợp
          </div>
          <div v-else>
            Nhập ít nhất 2 ký tự hoặc bật Xem theo ngày đến để tìm kiếm...
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { fetchBookings } from '@/services/booking-service'

const props = defineProps({
  show: Boolean,
  registrationStatuses: Array,
  activeTab: Object
})

const emit = defineEmits(['update:show', 'select-booking'])

const globalSearchQuery = ref('')
const globalSearchResults = ref([])
const filterByArrivalDate = ref(false)
const searchFromDate = ref(new Date().toISOString().split('T')[0])
const searchToDate = ref(new Date().toISOString().split('T')[0])
const searchStatuses = ref([])
const isStatusDropdownOpen = ref(false)

const searchStatusLabel = computed(() => {
  const selected = searchStatuses.value
  if (selected.length === 0) return 'Không có'
  if (selected.length === (props.registrationStatuses || []).length) return 'Tất cả'
  
  const names = []
  selected.forEach(id => {
    const s = (props.registrationStatuses || []).find(rs => rs.id === id)
    if (s) names.push(s.name)
  })
  
  if (names.length <= 2) return names.join(', ')
  return 'Nhiều tình trạng'
})

const isAllStatusesChecked = computed({
  get() {
    if (!props.registrationStatuses || props.registrationStatuses.length === 0) return false
    return searchStatuses.value.length === props.registrationStatuses.length
  },
  set(val) {
    if (val) {
      searchStatuses.value = (props.registrationStatuses || []).map(rs => rs.id)
    } else {
      searchStatuses.value = []
    }
  }
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    globalSearchQuery.value = ''
    globalSearchResults.value = []
    filterByArrivalDate.value = false
    searchStatuses.value = []
    isStatusDropdownOpen.value = false
    
    if (props.activeTab) {
      let ci = props.activeTab.checkIn
      let co = props.activeTab.checkOut
      if (ci && ci.includes('/')) ci = parseDateVi(ci)
      if (co && co.includes('/')) co = parseDateVi(co)
      
      searchFromDate.value = ci || new Date().toISOString().split('T')[0]
      searchToDate.value = co || new Date().toISOString().split('T')[0]
    } else {
      const today = new Date().toISOString().split('T')[0]
      searchFromDate.value = today
      searchToDate.value = today
    }
    
    setTimeout(() => {
      const el = document.getElementById('gsInput')
      if (el) el.focus()
    }, 50)
  }
})

async function executeGlobalSearch() {
  const params = {}
  
  if (globalSearchQuery.value.trim().length >= 2) {
    params.search = globalSearchQuery.value.trim()
  } else if (!filterByArrivalDate.value) {
    globalSearchResults.value = []
    return
  }
  
  if (filterByArrivalDate.value) {
    if (searchFromDate.value) params.from_date = searchFromDate.value
    if (searchToDate.value) params.to_date = searchToDate.value
  }
  
  if (searchStatuses.value.length > 0) {
    params.registration_status_id = searchStatuses.value.join(',')
  }
  
  try {
    const res = await fetchBookings(params)
    globalSearchResults.value = res.data?.data || res.data || []
  } catch (err) {
    console.error(err)
  }
}

watch([globalSearchQuery, filterByArrivalDate, searchFromDate, searchToDate, searchStatuses], () => {
  executeGlobalSearch()
}, { deep: true })

function close() {
  emit('update:show', false)
}

function selectBooking(booking) {
  emit('select-booking', booking)
  close()
}

function parseDateVi(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  const parts = dateStr.split('/')
  if (parts.length === 3) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`
  }
  return dateStr
}

function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}
</script>
