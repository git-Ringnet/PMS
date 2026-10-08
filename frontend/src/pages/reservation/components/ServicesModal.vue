<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in select-none"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[1200px] overflow-hidden border border-gray-300 flex flex-col max-h-[85vh]"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- MODAL HEADER -->
      <div 
        class="text-white flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move rounded-t-xl"
        :style="{ background: topbarThemeBg }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-bold text-xs uppercase tracking-wider text-white">
          <i class="fa-solid fa-bell-concierge text-white text-sm"></i>
          <span v-if="targetRooms.length <= 1">
            DỊCH VỤ BỔ SUNG - PHÒNG {{ room?.roomNumber || '(Chưa xếp phòng)' }} ({{ room?.type }})
          </span>
          <span v-else>
            DỊCH VỤ BỔ SUNG - {{ targetRooms.length }} PHÒNG ĐÃ CHỌN
            <span class="ml-2 text-xs text-white/80 font-normal normal-case">
              ({{ targetRooms.map(r => r.roomNumber || 'Chưa xếp').join(', ') }})
            </span>
          </span>
        </div>
        <div class="flex items-center space-x-2">
          <button 
            type="button"
            class="hover:bg-white/10 p-1 rounded-md cursor-pointer border-none bg-transparent text-white transition-colors" 
            @click="close"
            title="Đóng (Esc)"
          >
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>
      </div>

      <!-- MODAL BODY -->
      <div class="flex flex-1 overflow-hidden min-h-[450px]">
        <!-- LEFT PANEL: Dịch vụ -->
        <div class="w-1/4 border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
          <div class="text-xs font-semibold text-[#000000D9] uppercase tracking-wider mb-2">Dịch vụ</div>
          
          <!-- Search box -->
          <div class="relative mb-3 shrink-0">
            <input 
              type="text" 
              v-model="servicesModalSearch" 
              placeholder="Tìm kiếm theo tên dịch vụ..." 
              class="w-full h-8 pl-8 pr-3 bg-white border border-slate-300 rounded text-xs text-[#000000D9] placeholder-[#A8B0BF] focus:outline-none focus:border-blue-500"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
          </div>

          <!-- Services list -->
          <div class="flex-1 overflow-y-auto space-y-0.5 pr-1">
            <label 
              v-for="svc in filteredHotelServices" 
              :key="svc.code" 
              class="flex items-center gap-2 p-1.5 hover:bg-slate-100 rounded cursor-pointer transition text-xs text-[#000000D9]"
            >
              <input 
                type="checkbox" 
                :checked="selectedServiceCodes.includes(svc.code)"
                @change="e => handleServiceCheckboxChange(svc, e.target.checked)"
                class="cursor-pointer accent-blue-600 rounded"
              />
              <span class="font-normal text-[#000000D9] select-none leading-snug">{{ svc.name }}</span>
            </label>
          </div>
        </div>

        <!-- MIDDLE PANEL: Ngày -->
        <div class="w-[15%] border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
          <div class="text-xs font-semibold text-[#000000D9] uppercase tracking-wider mb-2">Ngày</div>
          
          <!-- Select All dates checkbox -->
          <label class="flex items-center gap-2 p-1.5 border-b border-slate-200 font-semibold cursor-pointer text-xs text-[#000000D9] mb-2 shrink-0">
            <input 
              type="checkbox" 
              :checked="checkedDates.length === stayDatesList.filter(d => d >= props.systemDate).length && checkedDates.length > 0" 
              :disabled="stayDatesList.filter(d => d >= props.systemDate).length === 0"
              @change="toggleAllDates"
              class="cursor-pointer accent-blue-600 rounded"
            />
            <span>Tất cả</span>
          </label>

          <!-- Stay dates list -->
          <div class="flex-1 overflow-y-auto space-y-0.5 pr-1">
            <label 
              v-for="d in stayDatesList" 
              :key="d" 
              class="flex items-center gap-2 p-1.5 rounded transition text-xs text-[#000000D9]"
              :class="d < props.systemDate ? 'opacity-50 cursor-not-allowed bg-slate-50/20' : 'hover:bg-slate-100 cursor-pointer'"
            >
              <input 
                type="checkbox" 
                :value="d" 
                v-model="checkedDates"
                :disabled="d < props.systemDate"
                class="cursor-pointer accent-blue-600 rounded"
              />
              <span class="font-normal">{{ formatDateShort(d) }}</span>
              <span v-if="d < props.systemDate" class="ml-auto text-[10px] font-semibold text-rose-500 bg-rose-50 px-1 py-0.5 rounded border border-rose-200">Quá khứ</span>
            </label>
          </div>
        </div>

        <!-- RIGHT PANEL: Dịch vụ chọn -->
        <div class="w-[60%] flex flex-col p-3 bg-white">
          <div class="text-xs font-semibold text-[#000000D9] uppercase tracking-wider mb-2 shrink-0">Chi tiết dịch vụ bổ sung</div>

          <!-- Multi-room note -->
          <div v-if="targetRooms.length > 1" class="mb-2 px-2.5 py-1.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700 font-medium shrink-0">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            Dịch vụ sẽ được áp dụng cho {{ targetRooms.length }} phòng đã chọn. Ngày hiển thị theo phòng đầu tiên ({{ room?.roomNumber }}).
          </div>

          <!-- Table -->
          <div class="flex-1 overflow-y-auto border border-slate-200 rounded-lg">
            <table class="w-full border-collapse text-left text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold h-8">
                  <th class="p-2 pl-3">Dịch vụ</th>
                  <th class="p-2 text-center w-24">Số lượng</th>
                  <th class="p-2 text-right w-36">Đơn giá (VND)</th>
                  <th class="p-2 text-right w-36">Thành tiền</th>
                  <th class="p-2 text-center w-36">Phòng / Master</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="(item, index) in serviceItems" 
                  :key="item.service_code"
                  class="border-b border-slate-100 hover:bg-slate-50/50 h-10 align-middle"
                >
                  <td class="p-2 pl-3 font-semibold text-[#000000D9]">
                    {{ item.service_name }}
                  </td>
                  <td class="p-2 text-center">
                    <input 
                      type="number" 
                      v-model.number="item.quantity" 
                      min="0.01" 
                      step="1"
                      class="w-16 border border-slate-300 rounded px-1.5 py-0.5 text-center font-normal text-xs text-[#000000D9] focus:outline-none focus:border-blue-500"
                    />
                  </td>
                  <td class="p-2 text-right">
                    <input 
                      type="text" 
                      :value="formatCurrencyInput(item.rate)" 
                      @input="e => item.rate = cleanCurrencyValue(e.target.value)"
                      class="w-28 border border-slate-300 rounded px-2 py-0.5 text-right font-normal text-xs text-[#000000D9] focus:outline-none focus:border-blue-500"
                    />
                  </td>
                  <td class="p-2 text-right font-semibold text-blue-700">
                    {{ formatCurrencyInput(item.quantity * item.rate) }}
                  </td>
                  <td class="p-2 text-center">
                    <!-- Toggle Phòng / Master -->
                    <div class="flex items-center justify-center space-x-1.5 select-none">
                      <span 
                        class="text-xs transition-colors cursor-pointer" 
                        :class="item.is_room ? 'font-semibold text-blue-600' : 'text-slate-400 font-normal'"
                        @click="item.is_room = true"
                        title="Tính vào phòng"
                      >
                        Phòng
                      </span>
                      <div class="relative inline-block w-8 h-4 align-middle transition duration-200 ease-in">
                        <input 
                          type="checkbox" 
                          :checked="!item.is_room" 
                          @change="item.is_room = !$event.target.checked"
                          :id="'fit-toggle-' + index"
                          class="sr-only peer"
                        />
                        <label 
                          :for="'fit-toggle-' + index"
                          class="block overflow-hidden h-4 rounded-full bg-blue-500 peer-checked:bg-slate-400 cursor-pointer transition-colors duration-200"
                          title="Gạt để chuyển Phòng / Master"
                        ></label>
                        <span class="absolute block w-3 h-3 rounded-full bg-white top-0.5 left-0.5 peer-checked:translate-x-4 transition-transform duration-200 pointer-events-none shadow-sm"></span>
                      </div>
                      <span 
                        class="text-xs transition-colors cursor-pointer" 
                        :class="!item.is_room ? 'font-semibold text-blue-600' : 'text-slate-400 font-normal'"
                        @click="item.is_room = false"
                        title="Tính vào Master"
                      >
                        Master
                      </span>
                    </div>
                  </td>
                </tr>
                <tr v-if="serviceItems.length === 0">
                  <td colspan="5" class="p-8 text-center text-slate-400 italic text-xs">
                    Chưa chọn dịch vụ nào. Hãy tích chọn dịch vụ ở cột bên trái!
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-2.5 shrink-0 flex items-center justify-between rounded-b-xl">
        <div class="bg-slate-100 border border-slate-200 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-[#000000D9]">
          Tổng tiền: <span class="text-blue-700 ml-1 font-bold">{{ Number(servicesTotalAmount).toLocaleString('en-US') }} VND</span>
          <span v-if="targetRooms.length > 1" class="ml-2 text-slate-500 font-normal">
            (x{{ targetRooms.length }} phòng = {{ Number(servicesTotalAmount * targetRooms.length).toLocaleString('en-US') }} VND)
          </span>
        </div>
        <div class="flex items-center space-x-2">
          <button 
            type="button" 
            @click="close" 
            class="btn-pms-close h-8 text-xs px-3"
          >
            <i class="fa-solid fa-xmark"></i>
            <span>Đóng</span>
          </button>
          <button 
            type="button" 
            @click="saveServices" 
            class="btn-pms-primary h-8 text-xs px-4"
          >
            <i class="fa-solid fa-floppy-disk"></i>
            <span>Lưu{{ targetRooms.length > 1 ? ` (${targetRooms.length} phòng)` : '' }}</span>
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
import { useAuthStore } from '@/stores/auth-store'

const props = defineProps({
  show: Boolean,
  room: Object,
  targetRooms: { type: Array, default: () => [] },
  hotelServicesList: Array,
  systemDate: String
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => {
  return authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)'
})

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

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

// Use union of all target rooms' dates (or fall back to primary room)
const stayDatesList = computed(() => {
  const rooms = props.targetRooms?.length > 0 ? props.targetRooms : (props.room ? [props.room] : [])
  if (rooms.length === 0) return []
  const dateSet = new Set()
  rooms.forEach(r => {
    getStayDates(r.checkIn, r.checkOut).forEach(d => dateSet.add(d))
  })
  return Array.from(dateSet).sort()
})

const servicesTotalAmount = computed(() => {
  return serviceItems.value.reduce((sum, item) => sum + (item.quantity * item.rate), 0)
})

watch(() => props.show, async (newVal) => {
  if (newVal && props.room) {
    modalPos.value = { x: 0, y: 0 }
    servicesModalSearch.value = ''
    selectedServiceCodes.value = []
    
    const dates = getStayDates(props.room.checkIn, props.room.checkOut)
    checkedDates.value = dates.filter(d => d >= formatLocalYYYYMMDD(props.systemDate))
    
    try {
      // Load existing services from the primary room to prefill
      if (props.room.bookingRoomId) {
        const res = await fetchBookingRoomServices(props.room.bookingRoomId)
        const existing = res.data?.data || []
        
        const items = []
        const codes = []
        existing.forEach(svc => {
          // Bỏ qua các dịch vụ hệ thống (giường phụ, tiền phòng, ăn sáng trẻ em)
          if (['EB', 'RM', 'BD', 'ROOM_CHARGE'].includes(svc.service_code)) return

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
      } else {
        serviceItems.value = []
      }
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
  const parsedCheckIn = parseDateVi(checkIn)
  const parsedCheckOut = parseDateVi(checkOut)
  if (!parsedCheckIn || !parsedCheckOut) return dates
  
  const startParts = parsedCheckIn.split('-').map(Number)
  const endParts = parsedCheckOut.split('-').map(Number)
  const start = new Date(startParts[0], startParts[1] - 1, startParts[2])
  const end = new Date(endParts[0], endParts[1] - 1, endParts[2])
  if (isNaN(start) || isNaN(end)) return dates
  
  let curr = new Date(start)
  while (curr < end) {
    const y = curr.getFullYear()
    const m = String(curr.getMonth() + 1).padStart(2, '0')
    const d = String(curr.getDate()).padStart(2, '0')
    dates.push(`${y}-${m}-${d}`)
    curr.setDate(curr.getDate() + 1)
  }
  return dates
}

function parseDateVi(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  const parts = dateStr.split('/')
  if (parts.length === 3) {
    let year = parts[2]
    if (year.length === 2) {
      year = `20${year}`
    }
    const month = parts[1].padStart(2, '0')
    const day = parts[0].padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  return dateStr
}

function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    const d = String(parts[2]).padStart(2, '0')
    const m = String(parts[1]).padStart(2, '0')
    return `${d}/${m}`
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
    checkedDates.value = stayDatesList.value.filter(d => d >= formatLocalYYYYMMDD(props.systemDate))
  } else {
    checkedDates.value = []
  }
}

function formatLocalYYYYMMDD(dVal) {
  if (!dVal) return ''
  if (typeof dVal === 'string') {
    if (/^\d{4}-\d{2}-\d{2}$/.test(dVal)) return dVal
    if (dVal.includes('/')) return parseDateVi(dVal)
  }
  const d = new Date(dVal)
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

async function saveServices() {
  const rooms = props.targetRooms?.length > 0 ? props.targetRooms : (props.room ? [props.room] : [])
  if (rooms.length === 0) return

  uiStore.confirm({
    title: 'Xác nhận lưu dịch vụ bổ sung',
    message: `Bạn có chắc chắn muốn lưu các dịch vụ bổ sung đã chọn cho ${rooms.length} phòng?`,
    confirmText: 'Lưu',
    cancelText: 'Hủy'
  }).then(async (confirmed) => {
    if (!confirmed) return
    uiStore.showToast('Đang tiến hành lưu dịch vụ bổ sung...', 'info')
    let hasError = false
    let lastErrorMsg = ''

    for (const room of rooms) {
      const roomId = room.bookingRoomId
      if (!roomId) continue

      try {
        // 1. Fetch existing services for this room
        const res = await fetchBookingRoomServices(roomId)
        const existing = res.data?.data || []

        // 2. Determine which existing services to delete:
        //    - service code not in our selected list, OR
        //    - service date not in our checked dates
        const toDeleteIds = []
        existing.forEach(svc => {
          // Bỏ qua các dịch vụ hệ thống (giường phụ, tiền phòng, ăn sáng trẻ em)
          if (['EB', 'RM', 'BD', 'ROOM_CHARGE'].includes(svc.service_code)) return

          const isCodeSelected = selectedServiceCodes.value.includes(svc.service_code)
          const svcDateShort = formatLocalYYYYMMDD(svc.service_date)
          const isDateChecked = checkedDates.value.includes(svcDateShort)
          if (!isCodeSelected || !isDateChecked) {
            const isDeletable = svcDateShort >= formatLocalYYYYMMDD(props.systemDate) && svc.is_posted !== 1
            if (isDeletable) {
              toDeleteIds.push(svc.id)
            }
          }
        })

        if (toDeleteIds.length > 0) {
          await deleteBookingRoomServicesBulk(roomId, { service_ids: toDeleteIds })
        }

        // 3. Build a set of (service_code, date) that already exist after deletion
        const remainingExisting = existing.filter(s => !toDeleteIds.includes(s.id))
        const existingKeys = new Set(remainingExisting.map(s => {
          const d = formatLocalYYYYMMDD(s.service_date)
          return `${s.service_code}__${d}`
        }))

        // 4. Create or update services
        for (const item of serviceItems.value) {
          // Determine dates for this room specifically
          const roomDates = getStayDates(room.checkIn, room.checkOut)
          const datesToCreate = checkedDates.value.filter(d => roomDates.includes(d))

          for (const d of datesToCreate) {
            const existingSvc = remainingExisting.find(s => {
              const sd = formatLocalYYYYMMDD(s.service_date)
              return s.service_code === item.service_code && sd === d
            })
            
            if (existingSvc) {
              const isUnchanged = Number(existingSvc.quantity) === Number(item.quantity) &&
                                  Number(existingSvc.rate) === Number(item.rate) &&
                                  !!existingSvc.is_room === !!item.is_room
              if (isUnchanged) continue
            }

            if (d < formatLocalYYYYMMDD(props.systemDate)) {
              continue
            }

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

        // 5. Update local room services cache
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
      uiStore.showToast(`Lưu dịch vụ bổ sung thành công cho ${rooms.length} phòng!`, 'success')
    }

    close()
    emit('saved')
  })
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
