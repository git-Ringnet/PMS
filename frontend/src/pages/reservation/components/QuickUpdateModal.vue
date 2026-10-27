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
        class="flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move rounded-t-xl"
        :style="{
          background: topbarThemeBg,
          color: '#ffffff'
        }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-bold text-xs tracking-wider text-white">
          <i class="fa-solid fa-pen-to-square"></i>
          <span>Cập Nhật Nhanh Phòng</span>
        </div>
        <div class="flex items-center space-x-2">
          <button 
            type="button"
            class="p-1 hover:bg-white/10 rounded-md cursor-pointer border-none bg-transparent flex items-center justify-center text-white"
            title="Trợ giúp"
          >
            <i class="fa-regular fa-circle-question text-base"></i>
          </button>
          <button 
            type="button" 
            class="p-1 hover:bg-white/10 rounded-md cursor-pointer border-none bg-transparent flex items-center justify-center text-white" 
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


        <!-- Form fields layout -->
        <div class="grid grid-cols-2 gap-4">
          <!-- Arrival Date -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Ngày nhận phòng</label>
            <SingleDatePicker
              v-model="form.arrival_date"
              :start-date="form.arrival_date || systemDateNormalized"
              :min-date="minArrivalDate"
              :disabled="isArrivalDisabled"
              placeholder="dd/mm/yyyy"
              four-digit-year
              input-class="h-9 rounded-lg border-slate-300 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500"
            />
          </div>

          <!-- Arrival Time -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giờ đến</label>
            <div 
              class="w-full flex items-center justify-between px-2.5 h-9 border rounded-lg text-xs bg-white transition-colors border-slate-300 shadow-2xs"
              :class="[
                isArrivalDisabled 
                  ? 'bg-slate-100 opacity-60 cursor-not-allowed' 
                  : 'hover:border-sky-400 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500 cursor-pointer'
              ]"
              @click="arrivalTimeInputRef?.focus()"
            >
              <input 
                ref="arrivalTimeInputRef"
                type="time" 
                v-model="form.arrival_time" 
                :disabled="isArrivalDisabled"
                class="time-clean-input flex-1 min-w-0 bg-transparent border-none outline-none text-xs font-semibold text-gray-900 p-0 cursor-pointer disabled:cursor-not-allowed disabled:text-slate-400"
              />
              <div class="flex items-center shrink-0 ml-1 space-x-1">
                <button 
                  v-if="!isArrivalDisabled && form.arrival_time" 
                  type="button" 
                  @click.stop="form.arrival_time = ''" 
                  class="p-0.5 text-slate-400 hover:text-slate-600 shrink-0 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors select-none"
                  title="Xóa giờ đến"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
                <button 
                  type="button" 
                  :disabled="isArrivalDisabled"
                  @click.stop="openArrivalTimePicker" 
                  class="p-0.5 text-slate-400 hover:text-sky-600 shrink-0 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors select-none"
                  :class="isArrivalDisabled ? 'cursor-not-allowed' : ''"
                  title="Chọn giờ đến"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Departure Date -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Ngày trả phòng</label>
            <SingleDatePicker
              v-model="form.departure_date"
              :start-date="form.departure_date || form.arrival_date || systemDateNormalized"
              :min-date="minDepartureDate"
              :disabled="isDepartureDisabled"
              placeholder="dd/mm/yyyy"
              four-digit-year
              input-class="h-9 rounded-lg border-slate-300 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500"
            />
          </div>

          <!-- Departure Time -->
          <div>
            <label class="block text-slate-600 mb-1 font-bold">Giờ đi</label>
            <div 
              class="w-full flex items-center justify-between px-2.5 h-9 border rounded-lg text-xs bg-white transition-colors border-slate-300 shadow-2xs"
              :class="[
                isDepartureDisabled 
                  ? 'bg-slate-100 opacity-60 cursor-not-allowed' 
                  : 'hover:border-sky-400 focus-within:border-sky-500 focus-within:ring-1 focus-within:ring-sky-500 cursor-pointer'
              ]"
              @click="departureTimeInputRef?.focus()"
            >
              <input 
                ref="departureTimeInputRef"
                type="time" 
                v-model="form.departure_time" 
                :disabled="isDepartureDisabled"
                class="time-clean-input flex-1 min-w-0 bg-transparent border-none outline-none text-xs font-semibold text-gray-900 p-0 cursor-pointer disabled:cursor-not-allowed disabled:text-slate-400"
              />
              <div class="flex items-center shrink-0 ml-1 space-x-1">
                <button 
                  v-if="!isDepartureDisabled && form.departure_time" 
                  type="button" 
                  @click.stop="form.departure_time = ''" 
                  class="p-0.5 text-slate-400 hover:text-slate-600 shrink-0 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors select-none"
                  title="Xóa giờ đi"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
                <button 
                  type="button" 
                  :disabled="isDepartureDisabled"
                  @click.stop="openDepartureTimePicker" 
                  class="p-0.5 text-slate-400 hover:text-sky-600 shrink-0 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors select-none"
                  :class="isDepartureDisabled ? 'cursor-not-allowed' : ''"
                  title="Chọn giờ đi"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Rate -->
          <div>
            <label class="block text-slate-600 mb-1 font-semibold text-xs">Giá</label>
            <div class="relative flex items-center">
              <input 
                type="text" 
                :disabled="isRateDisabled"
                :value="formatCurrencyInput(form.rate)"
                @input="e => form.rate = cleanCurrencyValue(e.target.value)"
                :class="[!isRateDisabled && form.rate !== '' && form.rate !== null && form.rate !== undefined ? 'pr-8' : '']"
                class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-right font-medium text-slate-800"
              />
              <button 
                v-if="!isRateDisabled && form.rate !== '' && form.rate !== null && form.rate !== undefined" 
                type="button" 
                @click.stop="form.rate = ''" 
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                title="Xóa giá"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Occupants (Adults / Children) -->
          <div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-slate-600 mb-1 font-semibold text-xs">Người lớn</label>
                <div class="relative flex items-center">
                  <input 
                    type="number" 
                    v-model.number="form.adults"
                    min="1"
                    :disabled="isOccupantsDisabled"
                    :class="[!isOccupantsDisabled && form.adults !== '' && form.adults !== null ? 'pr-7' : '']"
                    class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                  />
                  <button 
                    v-if="!isOccupantsDisabled && form.adults !== '' && form.adults !== null" 
                    type="button" 
                    @click.stop="form.adults = ''" 
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                    title="Xóa người lớn"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-slate-600 mb-1 font-semibold text-xs">Trẻ em</label>
                <div class="relative flex items-center">
                  <input 
                    type="number" 
                    v-model.number="form.children_qty"
                    min="0"
                    :disabled="isOccupantsDisabled"
                    :class="[!isOccupantsDisabled && form.children_qty !== '' && form.children_qty !== null ? 'pr-7' : '']"
                    class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                  />
                  <button 
                    v-if="!isOccupantsDisabled && form.children_qty !== '' && form.children_qty !== null" 
                    type="button" 
                    @click.stop="form.children_qty = ''" 
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                    title="Xóa trẻ em"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Extra Bed Qty -->
          <div>
            <label class="block text-slate-600 mb-1 font-semibold text-xs">Thêm giường</label>
            <div class="relative flex items-center">
              <input 
                type="number" 
                v-model.number="form.extra_bed_qty"
                min="0"
                :disabled="isExtraBedDisabled"
                :class="[!isExtraBedDisabled && form.extra_bed_qty !== '' && form.extra_bed_qty !== null ? 'pr-7' : '']"
                class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
              />
              <button 
                v-if="!isExtraBedDisabled && form.extra_bed_qty !== '' && form.extra_bed_qty !== null" 
                type="button" 
                @click.stop="form.extra_bed_qty = ''" 
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                title="Xóa số lượng thêm giường"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Extra Bed Price -->
          <div>
            <label class="block text-slate-600 mb-1 font-semibold text-xs">Giá thêm giường</label>
            <div class="relative flex items-center">
              <input 
                type="text" 
                :value="formatCurrencyInput(form.extra_bed_rate)"
                @input="e => form.extra_bed_rate = cleanCurrencyValue(e.target.value)"
                :disabled="isExtraBedDisabled"
                :class="[!isExtraBedDisabled && form.extra_bed_rate !== '' && form.extra_bed_rate !== null && form.extra_bed_rate !== undefined ? 'pr-8' : '']"
                class="w-full border rounded-lg h-9 px-3 text-xs focus:outline-none transition-colors border-slate-300 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed text-right font-medium text-slate-800"
              />
              <button 
                v-if="!isExtraBedDisabled && form.extra_bed_rate !== '' && form.extra_bed_rate !== null && form.extra_bed_rate !== undefined" 
                type="button" 
                @click.stop="form.extra_bed_rate = ''" 
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                title="Xóa giá thêm giường"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 shrink-0 flex justify-end items-center space-x-2 rounded-b-xl">
        <button 
          @click="submitSave" 
          :disabled="isSaving"
          class="btn-pms-primary"
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
import { useAuthStore } from '@/stores/auth-store'
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

const authStore = useAuthStore()
const uiStore = useUiStore()

const topbarThemeBg = computed(() => {
  return authStore.themeColor || 'var(--pms-custom-theme, #006bdb)'
})

// ==================== STATE DECLARATIONS ====================
const isSaving = ref(false)
const arrivalTimeInputRef = ref(null)
const departureTimeInputRef = ref(null)

function openArrivalTimePicker() {
  if (isArrivalDisabled.value) return
  try {
    arrivalTimeInputRef.value?.showPicker()
  } catch (e) {
    arrivalTimeInputRef.value?.focus()
  }
}

function openDepartureTimePicker() {
  if (isDepartureDisabled.value) return
  try {
    departureTimeInputRef.value?.showPicker()
  } catch (e) {
    departureTimeInputRef.value?.focus()
  }
}

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
  return normalizeToYmd(props.systemDate) || authStore.systemDate || localStorage.getItem('pms_system_date') || new Date().toISOString().split('T')[0]
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
// Ngày đi chỉ không được nhỏ hơn ngày đến. Ngày đi = ngày đến được phép
// (phòng cùng ngày; muốn day use thì tự bật "Ở theo giờ" ở lưới).
const minDepartureDate = computed(() => form.value.arrival_date || systemDateNormalized.value)

// ==================== WATCHERS ====================
watch(() => form.value.arrival_date, (newArrival) => {
  if (newArrival && form.value.departure_date) {
    if (form.value.departure_date < newArrival) {
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

<style scoped>
.time-clean-input::-webkit-calendar-picker-indicator {
  display: none !important;
  -webkit-appearance: none;
}
</style>
