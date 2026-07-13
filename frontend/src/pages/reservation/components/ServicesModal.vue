<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[1200px] overflow-hidden border border-gray-300 flex flex-col max-h-[85vh]"
    >
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0 select-none">
        <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
            <i class="fa-solid fa-bell-concierge text-blue-300"></i>
            <span>Dịch vụ bổ sung - PHÒNG {{ room?.roomNumber || 'CHƯA GÁN' }} ({{ room?.type }})</span>
        </div>
        <div class="flex items-center space-x-2 text-gray-300">
            <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
              <i class="fa-solid fa-xmark text-red-400"></i>
            </button>
        </div>
      </div>

      <!-- MODAL BODY -->
      <div class="flex flex-1 overflow-hidden min-h-[450px]">
        <!-- LEFT PANEL: Dịch vụ -->
        <div class="w-1/4 border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
          <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2">Dịch vụ</div>
          
          <!-- Search box -->
          <div class="relative mb-3 shrink-0">
            <input 
              type="text" 
              v-model="servicesModalSearch" 
              placeholder="Tìm kiếm mã, tên..." 
              class="w-full pl-7 pr-3 py-1 bg-white border border-slate-200 rounded-md text-[11px] focus:outline-none focus:ring-1 focus:ring-sky-500"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[11px]"></i>
          </div>

          <!-- Services list -->
          <div class="flex-1 overflow-y-auto space-y-1 pr-1">
            <label 
              v-for="svc in filteredHotelServices" 
              :key="svc.code" 
              class="flex items-start gap-2 p-1.5 hover:bg-slate-100 rounded-md cursor-pointer transition text-[11px] text-slate-700"
            >
              <input 
                type="checkbox" 
                :checked="selectedServiceCodes.includes(svc.code)"
                @change="e => handleServiceCheckboxChange(svc, e.target.checked)"
                class="mt-0.5"
              />
              <div>
                <div class="font-bold text-slate-800">{{ svc.name }}</div>
                <div class="text-[9px] text-slate-400 font-mono">{{ svc.code }} - {{ Number(svc.price).toLocaleString('en-US') }} VND</div>
              </div>
            </label>
          </div>
        </div>

        <!-- MIDDLE PANEL: Ngày -->
        <div class="w-[15%] border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
          <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2">Ngày</div>
          
          <!-- Select All dates checkbox -->
          <label class="flex items-center gap-2 p-1.5 border-b border-slate-200 font-bold cursor-pointer text-[11px] text-slate-700 mb-2 shrink-0">
            <input 
              type="checkbox" 
              :checked="checkedDates.length === stayDatesList.length" 
              @change="toggleAllDates"
            />
            <span>Tất cả</span>
          </label>

          <!-- Stay dates list -->
          <div class="flex-1 overflow-y-auto space-y-1 pr-1">
            <label 
              v-for="d in stayDatesList" 
              :key="d" 
              class="flex items-center gap-2 p-1.5 hover:bg-slate-100 rounded-md cursor-pointer transition text-[11px] text-slate-700 font-mono"
            >
              <input 
                type="checkbox" 
                :value="d" 
                v-model="checkedDates"
              />
              <span>{{ formatDateShort(d) }}</span>
            </label>
          </div>
        </div>

        <!-- RIGHT PANEL: Dịch vụ chọn -->
        <div class="w-[60%] flex flex-col p-3 bg-white">
          <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2 shrink-0">Chi tiết dịch vụ bổ sung</div>

          <!-- Table -->
          <div class="flex-1 overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full border-collapse text-left text-[11px]">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold h-8">
                  <th class="p-2 pl-3">Dịch vụ</th>
                  <th class="p-2 text-center w-24">Số lượng</th>
                  <th class="p-2 text-right w-36">Đơn giá (VND)</th>
                  <th class="p-2 text-center w-28">FIT/GIT</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="(item, index) in serviceItems" 
                  :key="item.service_code"
                  class="border-b border-slate-100 hover:bg-slate-50/50 h-10 align-middle font-medium"
                >
                  <td class="p-2 pl-3 font-bold text-slate-800">
                    {{ item.service_name }}
                    <span class="block text-[9px] text-slate-400 font-mono font-normal">{{ item.service_code }}</span>
                  </td>
                  <td class="p-2 text-center">
                    <input 
                      type="number" 
                      v-model.number="item.quantity" 
                      min="0.01" 
                      step="1"
                      class="w-16 border border-slate-200 rounded-md px-1 py-0.5 text-center font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                  </td>
                  <td class="p-2 text-right">
                    <input 
                      type="text" 
                      :value="formatCurrencyInput(item.rate)" 
                      @input="e => item.rate = cleanCurrencyValue(e.target.value)"
                      class="w-28 border border-slate-200 rounded-md px-2 py-0.5 text-right font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                  </td>
                  <td class="p-2 text-center">
                    <!-- Toggle FIT/GIT -->
                    <div class="flex items-center justify-center space-x-1.5">
                      <span class="text-[9px] font-bold" :class="item.is_room ? 'text-sky-500' : 'text-slate-400'">FIT</span>
                      <div class="relative inline-block w-8 h-4 align-middle select-none transition duration-200 ease-in">
                        <input 
                          type="checkbox" 
                          v-model="item.is_room" 
                          :id="'fit-toggle-' + index"
                          class="sr-only peer"
                        />
                        <label 
                          :for="'fit-toggle-' + index"
                          class="block overflow-hidden h-4 rounded-full bg-slate-300 peer-checked:bg-sky-500 cursor-pointer transition-colors duration-200"
                        ></label>
                        <span class="absolute block w-3 h-3 rounded-full bg-white top-0.5 left-0.5 peer-checked:translate-x-4 transition-transform duration-200 pointer-events-none"></span>
                      </div>
                      <span class="text-[9px] font-bold" :class="!item.is_room ? 'text-sky-500' : 'text-slate-400'">GIT</span>
                    </div>
                  </td>
                </tr>
                <tr v-if="serviceItems.length === 0">
                  <td colspan="4" class="p-8 text-center text-slate-400 italic">
                    Chưa chọn dịch vụ nào. Hãy tích chọn dịch vụ ở cột bên trái!
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-2.5 shrink-0 flex items-center justify-between">
        <div class="bg-[#e2e8f0] px-4 py-1.5 rounded-lg text-slate-700 font-extrabold text-xs shadow-inner">
          Tổng tiền: <span class="text-slate-900 ml-1 font-black">{{ servicesTotalAmount.toLocaleString('en-US') }} VND</span>
        </div>
        <div class="flex items-center space-x-2">
          <button 
            @click="saveServices" 
            class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer shadow-sm flex items-center space-x-1.5 transition border-none"
          >
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {
  fetchBookingRoomServices,
  createBookingRoomService,
  deleteBookingRoomServicesBulk
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  room: Object,
  targetRooms: Array,
  hotelServicesList: Array
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()

const servicesModalSearch = ref('')
const selectedServiceCodes = ref([])
const checkedDates = ref([])
const serviceItems = ref([])

const filteredHotelServices = computed(() => {
  if (!servicesModalSearch.value) return props.hotelServicesList || []
  const q = servicesModalSearch.value.toLowerCase()
  return (props.hotelServicesList || []).filter(s => 
    s.name.toLowerCase().includes(q) || 
    s.code.toLowerCase().includes(q)
  )
})

const stayDatesList = computed(() => {
  if (!props.room) return []
  return getStayDates(props.room.checkIn, props.room.checkOut)
})

const servicesTotalAmount = computed(() => {
  return serviceItems.value.reduce((sum, item) => sum + (item.quantity * item.rate), 0)
})

watch(() => props.show, async (newVal) => {
  if (newVal && props.room) {
    servicesModalSearch.value = ''
    selectedServiceCodes.value = []
    
    const dates = getStayDates(props.room.checkIn, props.room.checkOut)
    checkedDates.value = [...dates]
    
    try {
      const res = await fetchBookingRoomServices(props.room.bookingRoomId)
      const existing = res.data?.data || []
      
      const items = []
      const codes = []
      existing.forEach(svc => {
        if (!codes.includes(svc.service_code)) {
          codes.push(svc.service_code)
          items.push({
            service_code: svc.service_code,
            service_name: svc.service_name || getServiceNameFromCode(svc.service_code),
            quantity: svc.quantity || 1,
            rate: Number(svc.rate) || 0,
            is_room: svc.is_room !== 0
          })
        }
      })
      
      selectedServiceCodes.value = codes
      serviceItems.value = items
    } catch (err) {
      console.error(err)
      serviceItems.value = []
    }
  }
})

function close() {
  emit('update:show', false)
}

function getStayDates(checkIn, checkOut) {
  const dates = []
  if (!checkIn || !checkOut) return dates
  const start = new Date(parseDateVi(checkIn))
  const end = new Date(parseDateVi(checkOut))
  if (isNaN(start) || isNaN(end)) return dates
  
  let curr = new Date(start)
  while (curr < end) {
    dates.push(curr.toISOString().split('T')[0])
    curr.setDate(curr.getDate() + 1)
  }
  return dates
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

function formatDateShort(dateStr) {
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}`
  }
  return dateStr
}

function getServiceNameFromCode(code) {
  const svc = props.hotelServicesList?.find(s => s.code === code)
  return svc ? svc.name : code
}

function handleServiceCheckboxChange(svc, checked) {
  if (checked) {
    if (!selectedServiceCodes.value.includes(svc.code)) {
      selectedServiceCodes.value.push(svc.code)
      serviceItems.value.push({
        service_code: svc.code,
        service_name: svc.name,
        quantity: 1,
        rate: Number(svc.price) || 0,
        is_room: true
      })
    }
  } else {
    selectedServiceCodes.value = selectedServiceCodes.value.filter(c => c !== svc.code)
    serviceItems.value = serviceItems.value.filter(i => i.service_code !== svc.code)
  }
}

function toggleAllDates(event) {
  if (event.target.checked) {
    checkedDates.value = [...stayDatesList.value]
  } else {
    checkedDates.value = []
  }
}

async function saveServices() {
  if (!props.room || !props.targetRooms || props.targetRooms.length === 0) return

  uiStore.showToast('Đang tiến hành lưu dịch vụ bổ sung...', 'info')
  let hasError = false
  let lastErrorMsg = ''

  for (const room of props.targetRooms) {
    const roomId = room.bookingRoomId
    if (!roomId) continue

    try {
      const res = await fetchBookingRoomServices(roomId)
      const existing = res.data?.data || []

      const toDeleteIds = []
      existing.forEach(svc => {
        const isCodeSelected = selectedServiceCodes.value.includes(svc.service_code)
        let svcDateShort = svc.service_date
        if (svcDateShort && svcDateShort.includes('T')) {
          svcDateShort = svcDateShort.split('T')[0]
        }
        const isDateChecked = checkedDates.value.includes(svcDateShort)
        if (!isCodeSelected || !isDateChecked) {
          toDeleteIds.push(svc.id)
        }
      })

      if (toDeleteIds.length > 0) {
        await deleteBookingRoomServicesBulk(roomId, { service_ids: toDeleteIds })
      }

      for (const item of serviceItems.value) {
        for (const d of checkedDates.value) {
          await createBookingRoomService(roomId, {
            service_code: item.service_code,
            service_name: item.service_name,
            service_date: d,
            quantity: item.quantity,
            rate: item.rate,
            is_room: item.is_room ? 1 : 0
          })
        }
      }

      const updatedRes = await fetchBookingRoomServices(roomId)
      room.services = updatedRes.data?.data || []
    } catch (roomErr) {
      console.error(`Lỗi khi lưu dịch vụ cho phòng ${room.roomNumber || roomId}:`, roomErr)
      hasError = true
      lastErrorMsg = roomErr.response?.data?.message || roomErr.message || 'Lỗi khi kết nối server.'
    }
  }

  if (hasError) {
    uiStore.showToast(`Lưu dịch vụ hoàn tất nhưng có lỗi xảy ra: ${lastErrorMsg}`, 'error')
  } else {
    uiStore.showToast('Lưu dịch vụ bổ sung thành công cho tất cả phòng đã chọn!', 'success')
  }

  close()
  emit('saved')
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  let str = String(val).replace(/[^\d.-]/g, '');
  if (!str) return '';
  
  let parts = str.split('.');
  if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
  parts[0] = Number(parts[0]).toLocaleString('en-US');
  return parts.join('.');
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0;
  const cleanStr = String(val).replace(/,/g, '');
  return Number(cleanStr) || 0;
}
</script>
