<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[9999] flex items-center justify-center p-4 animate-in"
    @click.self="close"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[680px] overflow-hidden border border-slate-350 flex flex-col max-h-[90vh]"
      :style="{ 
        transform: `translate(${modalPos.x}px, ${modalPos.y}px)`,
        transition: isDraggingModal ? 'none' : ''
      }"
    >
      <!-- HEADER -->
      <div 
        class="flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move"
        :style="{
          background: 'var(--pms-custom-theme, #006bdb)',
          color: 'var(--pms-custom-theme-text, #ffffff)'
        }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
          <i class="fa-solid fa-pen-to-square"></i>
          <span>Cập nhật nhanh nhiều phòng</span>
        </div>
        <div class="flex items-center space-x-2">
          <button 
            type="button"
            class="p-1 hover:bg-white/10 rounded-md cursor-pointer border-none bg-transparent flex items-center justify-center"
            :style="{ color: 'var(--pms-custom-theme-text, #ffffff)' }"
            title="Trợ giúp"
          >
            <i class="fa-regular fa-circle-question text-base"></i>
          </button>
          <button 
            type="button" 
            class="p-1 hover:bg-white/10 rounded-md cursor-pointer border-none bg-transparent flex items-center justify-center" 
            :style="{ color: 'var(--pms-custom-theme-text, #ffffff)' }"
            @click="close"
          >
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>
      </div>

      <!-- BODY -->
      <div class="p-5 space-y-4 flex-1 overflow-y-auto text-xs font-semibold text-slate-700">
        <!-- List of selected rooms -->
        <div class="text-[10px] font-black text-slate-400 tracking-wider uppercase">
          Phòng được chọn ({{ targetRooms.length }})
        </div>
        <div class="flex flex-wrap gap-2 py-1">
          <span 
            v-for="r in targetRooms" 
            :key="r.id" 
            class="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-md font-bold text-slate-700 text-[11px]"
          >
            P. {{ r.roomNumber || 'Chưa gán' }} 
            <span class="text-[9px] text-slate-400 font-normal">({{ Number(roomStatus(r)) === 1 || roomStatus(r) === 'Checked In' ? 'Đang ở' : 'Đăng ký' }})</span>
          </span>
        </div>

        <!-- Warn if Checked-In rooms limit edits -->
        <div 
          v-if="hasCheckedInRoom" 
          class="bg-amber-50 border border-amber-200 text-amber-850 p-2.5 rounded-lg flex items-start gap-2"
        >
          <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 shrink-0"></i>
          <div>
            <strong>Lưu ý:</strong> Danh sách chọn chứa phòng đang lưu trú (Inhouse). Theo quy định:
            <ul class="list-disc pl-4 mt-1 space-y-0.5">
              <li>Không được chỉnh sửa ngày đến và giờ đến.</li>
              <li v-if="allowSaleInhouseRateDeparture">
                Cấu hình <span class="font-mono font-bold">AllowReserUpdateRate_DeptDateRoomInhouse = 1</span>: Cho phép điều chỉnh Ngày đi, Giờ đi và Giá phòng.
              </li>
              <li v-else class="text-red-700 font-semibold">
                Cấu hình <span class="font-mono font-bold">AllowReserUpdateRate_DeptDateRoomInhouse = 0</span>: Không được phép điều chỉnh Ngày đi, Giờ đi và Giá phòng.
              </li>
            </ul>
          </div>
        </div>

        <!-- Form fields layout -->
        <div class="grid grid-cols-2 gap-4">
          <!-- Arrival Date -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Ngày nhận phòng</label>
            <SingleDatePicker
              v-model="form.arrival_date"
              :start-date="systemDateNormalized"
              :min-date="minArrivalDate"
              :disabled="isArrivalDisabled"
              placeholder="dd/mm/yyyy"
              input-class="h-9 rounded-lg border-slate-300 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500"
            />
          </div>

          <!-- Arrival Time -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giờ đến</label>
            <div class="relative flex items-center">
              <input 
                type="time" 
                v-model="form.arrival_time" 
                :disabled="isArrivalDisabled"
                class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center font-semibold cursor-pointer"
              />
            </div>
          </div>

          <!-- Departure Date -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Ngày trả phòng</label>
            <SingleDatePicker
              v-model="form.departure_date"
              :start-date="systemDateNormalized"
              :min-date="minDepartureDate"
              :disabled="isDepartureDisabled"
              placeholder="dd/mm/yyyy"
              input-class="h-9 rounded-lg border-slate-300 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500"
            />
          </div>

          <!-- Departure Time -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giờ đi</label>
            <div class="relative flex items-center">
              <input 
                type="time" 
                v-model="form.departure_time" 
                :disabled="isDepartureDisabled"
                class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center font-semibold cursor-pointer"
              />
            </div>
          </div>

          <!-- Rate -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giá</label>
            <input 
              type="text" 
              placeholder="Để trống nếu không đổi"
              :disabled="isRateDisabled"
              :value="formatCurrencyInput(form.rate)"
              @input="e => form.rate = cleanCurrencyValue(e.target.value)"
              class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-right font-bold text-slate-800"
            />
          </div>

          <!-- Occupants (Adults / Children) -->
          <div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-slate-600 mb-1 font-bold">Người lớn</label>
                <input 
                  type="number" 
                  v-model.number="form.adults"
                  placeholder="Không đổi"
                  min="1"
                  :disabled="isOccupantsDisabled"
                  class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center"
                />
              </div>
              <div>
                <label class="block text-slate-600 mb-1 font-bold">Trẻ em</label>
                <input 
                  type="number" 
                  v-model.number="form.children_qty"
                  placeholder="Không đổi"
                  min="0"
                  :disabled="isOccupantsDisabled"
                  class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center"
                />
              </div>
            </div>
          </div>

          <!-- Extra Bed Qty -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Thêm giường</label>
            <input 
              type="number" 
              v-model.number="form.extra_bed_qty"
              placeholder="Không đổi"
              min="0"
              :disabled="isExtraBedDisabled"
              class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center"
            />
          </div>

          <!-- Extra Bed Price -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giá thêm giường</label>
            <input 
              type="text" 
              placeholder="Để trống nếu không đổi"
              :value="formatCurrencyInput(form.extra_bed_rate)"
              @input="e => form.extra_bed_rate = cleanCurrencyValue(e.target.value)"
              :disabled="isExtraBedDisabled"
              class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-right font-bold text-slate-800"
            />
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 shrink-0 flex justify-end items-center space-x-2">
        <button 
          @click="close" 
          :disabled="isSaving"
          class="px-4 py-2 border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition text-xs cursor-pointer bg-white disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Hủy bỏ
        </button>
        <button 
          @click="submitSave" 
          :disabled="isSaving"
          class="px-5 py-2 text-white font-bold rounded-xl transition flex items-center space-x-1.5 shadow-md text-xs cursor-pointer border-none disabled:opacity-50 disabled:cursor-not-allowed"
          :style="{
            background: 'var(--pms-custom-theme, #006bdb)',
            color: 'var(--pms-custom-theme-text, #ffffff)'
          }"
        >
          <i v-if="isSaving" class="fa-solid fa-circle-notch animate-spin"></i>
          <i v-else class="fa-regular fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'
import SingleDatePicker from '@/components/SingleDatePicker.vue'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  targetRooms: { type: Array, default: () => [] },
  systemDate: { type: String, default: '' },
  currentModule: { type: String, default: 'SALE' },
  allowSaleInhouseRateDeparture: { type: Boolean, default: false }
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()

// ==================== STATE DECLARATIONS ====================
const isSaving = ref(false)
const form = ref({
  arrival_date: '',
  arrival_time: '',
  departure_date: '',
  departure_time: '',
  rate: '',
  adults: '',
  children_qty: '',
  extra_bed_qty: '',
  extra_bed_rate: '',
  confirm_overbooking: false
})

// ==================== COMPUTED PROPERTIES ====================
const systemDateNormalized = computed(() => {
  return normalizeToYmd(props.systemDate) || new Date().toISOString().split('T')[0]
})

const hasCheckedInRoom = computed(() => {
  return props.targetRooms.some(r => Number(roomStatus(r)) === 1 || roomStatus(r) === 'Checked In')
})

// CreateRegistrationPage stores the persisted booking-room status as
// `bookingRoomStatus`; Room Map callers historically used `status`. Keep both
// shapes readable so in-house restrictions are applied consistently.
function roomStatus(room) {
  return room?.bookingRoomStatus ?? room?.status
}

const canEditInhouseRateDeparture = computed(() => {
  if (props.currentModule === 'SALE') {
    return props.allowSaleInhouseRateDeparture === true
  }
  return props.currentModule === 'FO'
})

// Field disabled states. The module prop only narrows the client UI; the
// bulk-update endpoint independently checks permissions and the hotel config.
const isArrivalDisabled = computed(() => hasCheckedInRoom.value)
const isOccupantsDisabled = computed(() => hasCheckedInRoom.value)
const isExtraBedDisabled = computed(() => hasCheckedInRoom.value)
const isDepartureDisabled = computed(() => hasCheckedInRoom.value && !canEditInhouseRateDeparture.value)
const isRateDisabled = computed(() => hasCheckedInRoom.value && !canEditInhouseRateDeparture.value)

const minArrivalDate = computed(() => systemDateNormalized.value)
const minDepartureDate = computed(() => {
  if (form.value.arrival_date) {
    const parts = form.value.arrival_date.split('-')
    if (parts.length === 3) {
      const y = parseInt(parts[0], 10)
      const m = parseInt(parts[1], 10) - 1
      const d = parseInt(parts[2], 10)
      const dateObj = new Date(y, m, d + 1)
      
      const year = dateObj.getFullYear()
      const month = String(dateObj.getMonth() + 1).padStart(2, '0')
      const day = String(dateObj.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  return systemDateNormalized.value
})

// ==================== WATCHERS ====================
watch(() => form.value.arrival_date, (newArrival) => {
  if (newArrival && form.value.departure_date) {
    if (form.value.departure_date <= newArrival) {
      const parts = newArrival.split('-')
      if (parts.length === 3) {
        const y = parseInt(parts[0], 10)
        const m = parseInt(parts[1], 10) - 1
        const d = parseInt(parts[2], 10)
        const dateObj = new Date(y, m, d + 1)
        
        const year = dateObj.getFullYear()
        const month = String(dateObj.getMonth() + 1).padStart(2, '0')
        const day = String(dateObj.getDate()).padStart(2, '0')
        form.value.departure_date = `${year}-${month}-${day}`
      }
    }
  }
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
    // Bulk update is intentionally a blank patch. Do not copy values from
    // the first selected room into every selected room.
    form.value = {
      arrival_date: '',
      arrival_time: '',
      departure_date: '',
      departure_time: '',
      rate: '',
      adults: '',
      children_qty: '',
      extra_bed_qty: '',
      extra_bed_rate: '',
      confirm_overbooking: false
    }
  }
})

// ==================== FUNCTIONS ====================
function normalizeToYmd(dateStr) {
  if (!dateStr) return ''
  const str = String(dateStr).trim()
  
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    return str.substring(0, 10)
  }
  
  if (/^\d{2}\/\d{2}\/\d{4}/.test(str)) {
    const parts = str.substring(0, 10).split('/')
    return `${parts[2]}-${parts[1]}-${parts[0]}`
  }
  
  try {
    const d = new Date(str)
    if (!isNaN(d.getTime())) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  } catch (e) {}
  
  return ''
}


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

function close() {
  emit('update:show', false)
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  let str = String(val).replace(/[^\d.-]/g, '');
  if (!str) return '';
  let parts = str.split('.');
  parts[0] = Number(parts[0]).toLocaleString('en-US');
  return parts.join('.');
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return '';
  const cleanStr = String(val).replace(/,/g, '');
  return Number(cleanStr) || '';
}

async function submitSave() {
  isSaving.value = true
  try {
    // Collect non-empty payload attributes
    const payload = {
      room_ids: props.targetRooms.map(r => String(r.bookingRoomId)),
      confirm_overbooking: form.value.confirm_overbooking,
      current_module: props.currentModule
    }

    if (form.value.arrival_date) payload.arrival_date = form.value.arrival_date
    if (form.value.arrival_time) payload.arrival_time = form.value.arrival_time
    if (form.value.departure_date) payload.departure_date = form.value.departure_date
    if (form.value.departure_time) payload.departure_time = form.value.departure_time
    if (form.value.rate !== '') payload.rate = Number(form.value.rate)
    if (form.value.adults !== '') payload.adults = Number(form.value.adults)
    if (form.value.children_qty !== '') payload.children_qty = Number(form.value.children_qty)
    if (form.value.extra_bed_qty !== '') payload.extra_bed_qty = Number(form.value.extra_bed_qty)
    if (form.value.extra_bed_rate !== '') payload.extra_bed_rate = Number(form.value.extra_bed_rate)

    const editableKeys = ['arrival_date', 'arrival_time', 'departure_date', 'departure_time', 'rate', 'adults', 'children_qty', 'extra_bed_qty', 'extra_bed_rate']
    const hasAnyField = editableKeys.some(k => payload[k] !== undefined)
    if (!hasAnyField) {
      uiStore.showToast('Vui lòng nhập ít nhất một thông tin cần cập nhật.', 'warning')
      isSaving.value = false
      return
    }

    const res = await http.post(`/bookings/${props.bookingId}/rooms/bulk-update`, payload)
    
    if (res.data?.success || res.data?.code === 'OVERBOOKING_WARNING') {
      // Check if overbooking warning triggered
      if (res.data?.code === 'OVERBOOKING_WARNING') {
        isSaving.value = false
        uiStore.confirm({
          title: 'Cảnh báo âm phòng trống',
          message: res.data.message || 'Cập nhật ngày sẽ dẫn đến âm phòng trống. Bạn có muốn tiếp tục?',
          confirmText: 'Tiếp tục',
          cancelText: 'Hủy bỏ'
        }).then(confirmed => {
          if (confirmed) {
            form.value.confirm_overbooking = true
            submitSave()
          }
        })
        return
      }

      uiStore.showToast(res.data?.message || 'Cập nhật nhanh thành công!', 'success')
      try {
        if (typeof BroadcastChannel !== 'undefined') {
          const bc = new BroadcastChannel('pms-room-updates')
          bc.postMessage({ type: 'booking-updated', bookingId: props.bookingId, timestamp: Date.now() })
          bc.postMessage('rooms-updated')
          bc.close()
        }
      } catch (e) {}
      emit('saved', payload)
      close()
    } else {
      uiStore.showToast(res.data?.message || 'Không thể cập nhật thông tin phòng!', 'error')
    }
  } catch (err) {
    console.error(err)
    const errMsg = err.response?.data?.message || 'Có lỗi xảy ra khi cập nhật nhanh!'
    uiStore.showToast(errMsg, 'error')
  } finally {
    isSaving.value = false
  }
}
</script>
