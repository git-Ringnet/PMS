<template>
  <div
    v-if="show"
    class="fixed inset-0 z-[99990] flex items-center justify-center p-4 bg-transparent pointer-events-none select-none"
  >
    <div
      class="pointer-events-auto bg-white rounded-2xl shadow-2xl w-full max-w-[760px] overflow-hidden border border-slate-300 flex flex-col animate-[fadeIn_0.15s_ease-out]"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- HEADER -->
      <div
        class="text-white flex justify-between items-center px-4 py-3 shrink-0 cursor-move select-none"
        :style="{ background: themeBg }"
        @mousedown="startDragModal"
      >
        <h3 class="text-sm font-bold tracking-wide">Nhận phòng nhanh</h3>
        <button
          @click="close"
          class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg leading-none p-1 transition-colors"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      <!-- BODY -->
      <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3.5 text-xs text-slate-700 bg-white">
        <!-- LEFT COLUMN: THÔNG TIN -->
        <div class="border border-slate-300 rounded-xl p-3 flex flex-col gap-2.5 relative">
          <div class="font-bold text-slate-800 text-xs">Thông tin</div>

          <!-- Row 1: Ngày đến & Ngày đi -->
          <div class="grid grid-cols-2 gap-2">
            <!-- Ngày đến -->
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Ngày đến</label>
              <div
                @click="openDatePicker('arrival')"
                class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:border-sky-400 cursor-pointer transition-colors relative"
              >
                <span class="font-bold text-slate-800">{{ formatDisplayDate(arrivalDate) }}</span>
                <div class="flex items-center gap-1 text-slate-400">
                  <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <button
                    type="button"
                    @click.stop="copyDateToClipboard(arrivalDate)"
                    class="p-0.5 hover:text-sky-600 bg-transparent border-none cursor-pointer"
                    title="Sao chép ngày đến"
                  >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                  </button>
                </div>
                <input
                  ref="arrivalInputRef"
                  type="date"
                  v-model="arrivalDate"
                  @change="onArrivalDateChange"
                  class="absolute inset-0 opacity-0 pointer-events-none w-full h-full"
                />
              </div>
            </div>

            <!-- Ngày đi -->
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Ngày đi</label>
              <div
                @click="openDatePicker('departure')"
                class="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:border-sky-400 cursor-pointer transition-colors relative"
              >
                <span class="font-bold text-slate-800">{{ formatDisplayDate(departureDate) }}</span>
                <div class="flex items-center gap-1 text-slate-400">
                  <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <button
                    type="button"
                    @click.stop="copyArrivalToDeparture"
                    class="p-0.5 hover:text-sky-600 bg-transparent border-none cursor-pointer"
                    title="Sao chép từ ngày đến (Ở theo giờ)"
                  >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                  </button>
                </div>
                <input
                  ref="departureInputRef"
                  type="date"
                  v-model="departureDate"
                  @change="onDepartureDateChange"
                  class="absolute inset-0 opacity-0 pointer-events-none w-full h-full"
                />
              </div>
            </div>
          </div>

          <!-- Row 2: Loại phòng & Dạng phòng -->
          <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Loại phòng</label>
              <select
                v-model="selectedRoomClassId"
                @change="onRoomClassChange"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              >
                <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">
                  {{ rc.name || rc.code }}
                </option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Dạng phòng</label>
              <select
                v-model="selectedRoomKind"
                @change="onRoomKindChange"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              >
                <option v-for="rf in roomForms" :key="rf.id || rf.name" :value="rf.name">
                  {{ rf.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Row 3: Phòng & Số đêm -->
          <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Phòng:</label>
              <select
                v-model="selectedRoomNumber"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              >
                <option v-for="r in availableRoomsList" :key="r.room_number || r.id" :value="r.room_number">
                  {{ r.room_number }}
                </option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Số đêm</label>
              <input
                type="number"
                min="0"
                v-model.number="nights"
                @input="onNightsChange"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              />
            </div>
          </div>

          <!-- Khách hàng Box -->
          <div class="border border-slate-300 rounded-xl p-2.5 flex flex-col gap-2 mt-0.5">
            <div class="font-bold text-slate-800 text-[11px]">Khách hàng</div>
            <div class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-4 flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-500 flex items-center gap-1">
                  <span>N.Lớn</span>
                  <svg class="w-2.5 h-2.5 text-slate-700" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                  </svg>
                </label>
                <input
                  type="number"
                  min="1"
                  v-model.number="adults"
                  class="w-full px-2 py-1 text-center rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs"
                />
              </div>
              <div class="col-span-4 flex flex-col gap-1">
                <label class="text-[10px] font-bold text-slate-500 flex items-center gap-1">
                  <span>Trẻ em</span>
                  <svg class="w-2.5 h-2.5 text-slate-700" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                  </svg>
                </label>
                <input
                  type="number"
                  min="0"
                  v-model.number="children"
                  class="w-full px-2 py-1 text-center rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs"
                />
              </div>
              <div class="col-span-4 flex flex-col items-center justify-center gap-1">
                <label class="text-[10px] font-bold text-slate-500 whitespace-nowrap">Ở theo giờ</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="isDayUse"
                    @change="toggleDayUse"
                    class="sr-only peer"
                  />
                  <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: GIÁ -->
        <div class="border border-slate-300 rounded-xl p-3 flex flex-col gap-2.5 relative">
          <div class="font-bold text-slate-800 text-xs">Giá</div>

          <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Giá phòng</label>
              <input
                type="number"
                min="0"
                v-model.number="roomRate"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Mã giá phòng</label>
              <select
                v-model="selectedRateCode"
                @change="onRateCodeChange"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400 truncate"
              >
                <option value="">Vui lòng chọn giá phòng</option>
                <option v-for="rc in rateCodesList" :key="rc.id || rc.Ma" :value="rc.Ma || rc.id">
                  {{ rc.TenGia || rc.name || rc.Ma }}
                </option>
              </select>
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-bold text-slate-600">Tăng/Giảm giá</label>
            <div class="flex items-center gap-1.5">
              <input
                type="number"
                v-model.number="discountValue"
                class="flex-1 px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              />
              <select
                v-model="discountType"
                class="w-14 px-2 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              >
                <option value="percentage">%</option>
                <option value="amount">VNĐ</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Thêm giường</label>
              <input
                type="number"
                min="0"
                v-model.number="extraBedQty"
                @input="onExtraBedChange"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white font-bold text-slate-800 text-xs focus:outline-none focus:ring-1 focus:ring-sky-400"
              />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-600">Giá thêm giường</label>
              <input
                type="number"
                min="0"
                v-model.number="extraBedRate"
                class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 bg-slate-100 font-bold text-slate-800 text-xs"
              />
            </div>
          </div>

          <div class="mt-auto pt-1">
            <button
              type="button"
              @click="openSpecialRequestsModal"
              class="w-full py-2 text-white font-bold rounded-lg text-xs transition-all shadow-xs border-none cursor-pointer flex items-center justify-center gap-1.5 hover:opacity-90"
              :style="{ background: themeBg }"
            >
              <span>Yêu cầu đặc biệt</span>
              <span v-if="specialRequestsList.length > 0" class="bg-white/20 px-1.5 py-0.5 rounded text-[10px]">
                ({{ specialRequestsList.length }})
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="px-4 py-3 bg-white border-t border-slate-100 flex items-center justify-end gap-2.5 shrink-0">
        <button
          type="button"
          @click="close"
          class="px-5 py-2 text-white rounded-lg font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all border-none cursor-pointer hover:opacity-90"
          :style="{ background: themeBg }"
        >
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
          <span>Đóng</span>
        </button>

        <button
          type="button"
          @click="handleSave"
          :disabled="isSubmitting"
          class="px-5 py-2 disabled:opacity-50 text-white rounded-lg font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all border-none cursor-pointer hover:opacity-90"
          :style="{ background: themeBg }"
        >
          <svg v-if="!isSubmitting" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
            <polyline points="17 21 17 13 7 13 7 21"></polyline>
            <polyline points="7 3 7 8 15 8"></polyline>
          </svg>
          <span v-if="isSubmitting" class="animate-spin text-xs">⏳</span>
          <span>Lưu</span>
        </button>
      </div>
    </div>

    <!-- Special Requests Modal Popup -->
    <SpecialRequestsModal
      v-if="showSpecialRequests"
      v-model:show="showSpecialRequests"
      :room="{ roomNumber: selectedRoomNumber, specialRequests: specialRequestsList }"
      @close="showSpecialRequests = false"
      @save="onSpecialRequestsSave"
      @saved="onSpecialRequestsSave"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { resolveRateCodePrice } from '@/utils/rate-code-pricing.js'
import { useRoomStore } from '@/stores/room-store'
import { useAuthStore } from '@/stores/auth-store'
import http from '@/services/http'
import { fetchBookingInitDropdowns, createBooking, checkInRoom, syncBookingRoomSpecialRequests } from '@/services/booking-service'
import SpecialRequestsModal from './SpecialRequestsModal.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  room: {
    type: Object,
    default: null
  },
  initialDate: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'success'])

const uiStore = useUiStore()
const roomStore = useRoomStore()
const authStore = useAuthStore()

// Theme Topbar Background Color
const themeBg = computed(() => authStore.settings?.topbar_color || '#006bdb')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (e.target.closest('button, input, select, textarea, a, label')) return

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

// State
const arrivalDate = ref('')
const departureDate = ref('')
const selectedRoomClassId = ref(null)
const selectedRoomKind = ref('')
const selectedRoomNumber = ref('')
const nights = ref(1)
const adults = ref(2)
const children = ref(0)
const isDayUse = ref(false)

const roomRate = ref(0)
const selectedRateCode = ref('')
const discountValue = ref(0)
const discountType = ref('percentage')
const extraBedQty = ref(0)
const extraBedRate = ref(0)
const specialRequestsList = ref([])

const showSpecialRequests = ref(false)
const isSubmitting = ref(false)

// Dropdown data
const roomClasses = ref([])
const roomForms = ref([])
const standardRatesList = ref([])
const rateCodesList = ref([])
const companies = ref([])
const markets = ref([])
const customerSources = ref([])
const registrationStatuses = ref([])
const paymentMethods = ref([])

const arrivalInputRef = ref(null)
const departureInputRef = ref(null)

// Computed available rooms for chosen room class
const availableRoomsList = computed(() => {
  if (!roomStore.rooms || roomStore.rooms.length === 0) return []
  if (!selectedRoomClassId.value) return roomStore.rooms

  const list = roomStore.rooms.filter(r => {
    return (r.room_class_id === selectedRoomClassId.value || r.room_class?.id === selectedRoomClassId.value)
  })
  return list.length > 0 ? list : roomStore.rooms
})

// Format helpers
function formatDisplayDate(dateStr) {
  if (!dateStr) return '__ / __ / ____'
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]} / ${parts[1]} / ${parts[0]}`
  }
  return dateStr
}

function openDatePicker(type) {
  if (type === 'arrival' && arrivalInputRef.value) {
    if (typeof arrivalInputRef.value.showPicker === 'function') {
      arrivalInputRef.value.showPicker()
    } else {
      arrivalInputRef.value.focus()
    }
  } else if (type === 'departure' && departureInputRef.value) {
    if (typeof departureInputRef.value.showPicker === 'function') {
      departureInputRef.value.showPicker()
    } else {
      departureInputRef.value.focus()
    }
  }
}

function copyArrivalToDeparture() {
  departureDate.value = arrivalDate.value
  nights.value = 0
  isDayUse.value = true
  uiStore.showToast('Đã sao chép Ngày đến sang Ngày đi (Ở theo giờ)!', 'info')
}

function copyDateToClipboard(dateStr) {
  if (!dateStr) return
  if (navigator.clipboard) {
    navigator.clipboard.writeText(dateStr)
    uiStore.showToast(`Đã sao chép ngày: ${formatDisplayDate(dateStr)}`, 'info')
  }
}

// Logic switch Ở theo giờ
function toggleDayUse(e) {
  isDayUse.value = e.target.checked
  if (isDayUse.value) {
    departureDate.value = arrivalDate.value
    nights.value = 0
  } else {
    nights.value = 1
    const d = new Date(arrivalDate.value)
    d.setDate(d.getDate() + 1)
    departureDate.value = d.toISOString().split('T')[0]
  }
}

function onNightsChange() {
  const n = parseInt(nights.value, 10) || 0
  if (n <= 0) {
    isDayUse.value = true
    departureDate.value = arrivalDate.value
  } else {
    isDayUse.value = false
    const d = new Date(arrivalDate.value)
    d.setDate(d.getDate() + n)
    departureDate.value = d.toISOString().split('T')[0]
  }
}

function onArrivalDateChange() {
  if (isDayUse.value) {
    departureDate.value = arrivalDate.value
  } else {
    const n = parseInt(nights.value, 10) || 1
    const d = new Date(arrivalDate.value)
    d.setDate(d.getDate() + n)
    departureDate.value = d.toISOString().split('T')[0]
  }
}

function onDepartureDateChange() {
  const d1 = new Date(arrivalDate.value)
  const d2 = new Date(departureDate.value)
  const diffTime = d2.getTime() - d1.getTime()
  const diffDays = Math.round(diffTime / (1000 * 3600 * 24))

  if (diffDays <= 0) {
    nights.value = 0
    isDayUse.value = true
    departureDate.value = arrivalDate.value
  } else {
    nights.value = diffDays
    isDayUse.value = false
  }
}

// Đồng bộ tự động Loại phòng -> Dạng phòng & Giá phòng & Giá thêm giường theo Giá phòng chuẩn
function onRoomClassChange() {
  const rc = roomClasses.value.find(c => c.id === selectedRoomClassId.value)
  const std = standardRatesList.value.find(r => r.room_class_id === selectedRoomClassId.value)

  if (rc || std) {
    selectedRoomKind.value = rc?.room_form_name || std?.room_form?.name || selectedRoomKind.value
    roomRate.value = Number(rc?.room_price || std?.room_price || 0)
    adults.value = rc?.max_adults || std?.room_form?.max_adults || adults.value

    const baseExtraBedPrice = rc?.extra_bed_price || std?.extra_bed_price || 300000
    if (extraBedQty.value > 0) {
      extraBedRate.value = baseExtraBedPrice * extraBedQty.value
    } else {
      extraBedRate.value = 0
    }
  }

  // Tự chọn phòng đầu tiên trong loại phòng mới nếu phòng hiện tại không thuộc loại này
  const validRoom = availableRoomsList.value.find(r => r.room_number === selectedRoomNumber.value)
  if (!validRoom && availableRoomsList.value.length > 0) {
    selectedRoomNumber.value = availableRoomsList.value[0].room_number
  }
}

function onRoomKindChange() {
  const std = standardRatesList.value.find(r =>
    r.room_class_id === selectedRoomClassId.value &&
    (r.room_form?.name === selectedRoomKind.value || r.room_form_id === selectedRoomKind.value)
  )
  if (std) {
    roomRate.value = Number(std.room_price || 0)
    adults.value = std.room_form?.max_adults || adults.value
    if (extraBedQty.value > 0) {
      extraBedRate.value = Number(std.extra_bed_price || 300000) * extraBedQty.value
    }
  }
}

function onRateCodeChange() {
  const rc = rateCodesList.value.find(c => (c.Ma || c.id) === selectedRateCode.value)
  if (rc) {
    const price = resolveRateCodePrice(rateCodesList.value, {
      rateCode: selectedRateCode.value,
      date: arrivalDate.value,
      roomClassId: selectedRoomClassId.value,
      roomClassCode: roomClasses.value.find(c => c.id === selectedRoomClassId.value)?.code,
    })
    if (price !== null) roomRate.value = Number(price) || 0
  }
}

function onExtraBedChange() {
  const rc = roomClasses.value.find(c => c.id === selectedRoomClassId.value)
  const std = standardRatesList.value.find(r => r.room_class_id === selectedRoomClassId.value)
  const baseExtraBedPrice = rc?.extra_bed_price || std?.extra_bed_price || 300000

  if (extraBedQty.value > 0) {
    extraBedRate.value = baseExtraBedPrice * extraBedQty.value
  } else {
    extraBedRate.value = 0
  }
}

function openSpecialRequestsModal() {
  showSpecialRequests.value = true
}

function onSpecialRequestsSave(selectedRequests) {
  specialRequestsList.value = selectedRequests || []
  showSpecialRequests.value = false
}

// Khởi tạo dữ liệu khi mở modal
watch(
  () => props.show,
  async (newVal) => {
    if (newVal) {
      modalPos.value = { x: 0, y: 0 }
      await loadDropdowns()
      initModalValues()
    }
  },
  { immediate: true }
)

async function loadDropdowns() {
  try {
    const [initRes, ratesRes] = await Promise.all([
      fetchBookingInitDropdowns(),
      http.get('/standard-rates').catch(() => ({ data: { data: [] } }))
    ])
    if (initRes?.data?.data) {
      const d = initRes.data.data
      roomClasses.value = d.room_classes || []
      roomForms.value = d.room_forms || []
      rateCodesList.value = d.room_rate_codes || []
      companies.value = d.companies || []
      markets.value = d.markets || []
      customerSources.value = d.customer_sources || []
      registrationStatuses.value = d.registration_statuses || []
      paymentMethods.value = d.payment_methods || []
    }
    if (ratesRes?.data?.data) {
      standardRatesList.value = ratesRes.data.data || []
    }
  } catch (err) {
    console.error('Failed to load init dropdowns', err)
  }
}

function initModalValues() {
  const today = props.initialDate || new Date().toISOString().split('T')[0]
  arrivalDate.value = today

  // Mặc định 1 đêm
  nights.value = 1
  isDayUse.value = false

  const d = new Date(today)
  d.setDate(d.getDate() + 1)
  departureDate.value = d.toISOString().split('T')[0]

  if (props.room) {
    selectedRoomNumber.value = props.room.room_number || ''
    selectedRoomClassId.value = props.room.room_class_id || props.room.room_class?.id || null

    // Tìm giá phòng chuẩn & dạng phòng chuẩn tương ứng
    const rc = roomClasses.value.find(c => c.id === selectedRoomClassId.value)
    const std = standardRatesList.value.find(r => r.room_class_id === selectedRoomClassId.value)

    selectedRoomKind.value = rc?.room_form_name || std?.room_form?.name || props.room.room_type || 'Double'
    adults.value = rc?.max_adults || std?.room_form?.max_adults || props.room.max_guests || 2
    children.value = 0

    // Giá phòng & Giá thêm giường theo giá phòng chuẩn
    if (rc?.room_price || std?.room_price) {
      roomRate.value = Number(rc?.room_price || std?.room_price)
    } else if (props.room.rate) {
      roomRate.value = Number(props.room.rate)
    } else {
      roomRate.value = 0
    }
    extraBedRate.value = 0
  } else {
    selectedRoomNumber.value = ''
    selectedRoomClassId.value = roomClasses.value[0]?.id || null
    selectedRoomKind.value = roomClasses.value[0]?.room_form_name || 'Double'
    adults.value = roomClasses.value[0]?.max_adults || 2
    children.value = 0
    roomRate.value = roomClasses.value[0]?.room_price || 0
    extraBedRate.value = 0
  }

  discountValue.value = 0
  discountType.value = 'percentage'
  extraBedQty.value = 0
  specialRequestsList.value = []
  selectedRateCode.value = ''
}

function close() {
  emit('close')
}

// Lưu & Nhận phòng nhanh
async function handleSave() {
  if (!selectedRoomNumber.value) {
    uiStore.showToast('Vui lòng chọn số phòng!', 'warning')
    return
  }

  if (!arrivalDate.value || !departureDate.value) {
    uiStore.showToast('Vui lòng chọn ngày đến và ngày đi hợp lệ!', 'warning')
    return
  }

  isSubmitting.value = true

  try {
    const defaultCompany = companies.value[0]?.id || 1
    const defaultMarket = markets.value[0]?.id || 1
    const defaultSource = customerSources.value[0]?.id || 1
    const defaultRegStatus = registrationStatuses.value[0]?.id || 1
    const defaultPaymentMethod = paymentMethods.value[0]?.id || 1

    const payload = {
      booking_name: `Khách lẻ (P.${selectedRoomNumber.value})`,
      arrival_date: arrivalDate.value,
      departure_date: departureDate.value,
      num_of_days: isDayUse.value ? 0 : nights.value,
      is_day_use: isDayUse.value,
      company_id: defaultCompany,
      market_id: defaultMarket,
      customer_source_id: defaultSource,
      registration_status_id: defaultRegStatus,
      payment_method_id: defaultPaymentMethod,
      room_allocations: [
        {
          roomClassId: selectedRoomClassId.value,
          quantity: 1,
          price: roomRate.value,
          basePrice: roomRate.value,
          rateCode: selectedRateCode.value || null,
          discountType: discountValue.value > 0 ? discountType.value : null,
          discountValue: discountValue.value || 0,
          rooms: [
            {
              roomNumber: selectedRoomNumber.value,
              arrivalDate: arrivalDate.value,
              departureDate: departureDate.value,
              adults: adults.value,
              children: children.value,
              extraBedQty: extraBedQty.value,
              extraBedPrice: extraBedRate.value,
              is_day_use: isDayUse.value,
              guestName: `Khách lẻ (Phòng ${selectedRoomNumber.value})`
            }
          ]
        }
      ]
    }

    const res = await createBooking(payload)
    if (res.data && res.data.success !== false) {
      const createdBooking = res.data.data
      const bRoom = createdBooking?.booking_rooms?.[0] || createdBooking?.bookingRooms?.[0]

      // Nếu ngày đến bằng ngày nghiệp vụ -> tự động Check-in ngay
      if (bRoom && bRoom.id) {
        if (specialRequestsList.value.length > 0) {
          const specialRequestIds = specialRequestsList.value.map(r => r.id || r.special_request_id || r).filter(Boolean)
          if (specialRequestIds.length > 0) {
            try {
              await syncBookingRoomSpecialRequests(bRoom.id, { special_request_ids: specialRequestIds })
            } catch (srErr) {
              console.warn('Failed to sync special requests:', srErr)
            }
          }
        }

        try {
          await checkInRoom(createdBooking.id, bRoom.id)
        } catch (checkinErr) {
          console.warn('Auto checkin skipped or deferred:', checkinErr)
        }
      }

      uiStore.showToast(`Nhận phòng nhanh ${selectedRoomNumber.value} thành công!`, 'success')
      emit('success', { booking: createdBooking, roomNumber: selectedRoomNumber.value })
      close()
    } else {
      const msg = res.data?.message || 'Không thể tạo nhận phòng nhanh.'
      uiStore.showToast(msg, 'error')
    }
  } catch (err) {
    const msg = err.response?.data?.message || 'Lỗi khi lưu nhận phòng nhanh.'
    uiStore.showToast(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}
</script>
