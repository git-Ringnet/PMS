<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[550px] overflow-hidden border border-slate-200 flex flex-col"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- MODAL HEADER -->
      <div 
        class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0 select-none cursor-move"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
          <i class="fa-solid fa-ban text-red-400"></i>
          <span>CHARGE NOSHOW</span>
        </div>
        <button 
          class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" 
          @click="close"
        >
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-slate-700">
        <!-- Đăng ký -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Đăng ký</label>
          <input 
            type="text" 
            :value="displayName" 
            disabled
            class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-xs font-medium bg-[#f1f5f9] text-gray-500 cursor-not-allowed focus:outline-none"
          />
        </div>

        <!-- Ngày đến ~ Ngày đi và Tiền phòng tự động -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">Ngày *</label>
            <div class="flex items-center space-x-2 border border-gray-200 rounded-lg px-3 py-2 bg-white">
              <input 
                type="date" 
                v-model="dateFrom"
                class="w-full text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer"
              />
              <span class="text-gray-400">~</span>
              <input 
                type="date" 
                v-model="dateTo"
                class="w-full text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer"
              />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">Tiền phòng tự động</label>
            <input 
              :value="autoRateText" 
              readonly 
              disabled
              class="w-full border border-blue-500/80 rounded-lg px-3.5 py-2.5 text-sm bg-white text-gray-800 font-semibold cursor-not-allowed shadow-xs"
            />
          </div>
        </div>

        <!-- Card Toggles + Tổng tiền -->
        <div class="bg-[#f8fafc] border border-gray-200/80 rounded-xl p-4 grid grid-cols-2 gap-4 items-center">
          <!-- Left Side: Toggles -->
          <div class="space-y-3">
            <!-- Toggle 1: Tự nhập tiền phòng -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-gray-800">Tự nhập tiền phòng</span>
                <i class="fa-solid fa-circle-info text-gray-400 cursor-help" title="Bật để tự nhập giá tiền phòng mới cho khoảng ngày đã chọn"></i>
              </div>
              <button 
                type="button" 
                @click="toggleCustomRateMode"
                :class="['relative w-9 h-5 rounded-full transition-colors flex-shrink-0',
                  customRateMode ? 'bg-blue-600' : 'bg-gray-300']"
              >
                <div 
                  :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    customRateMode ? 'translate-x-4.5' : 'translate-x-0.5']" 
                />
              </button>
            </div>

            <!-- Toggle 2: Bổ sung tiền phòng - không ảnh hưởng công suất (chỉ hiện khi Tự nhập bật) -->
            <div v-if="customRateMode" class="flex items-center justify-between pt-2 border-t border-gray-200/60">
              <div class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-gray-800">Bổ sung tiền phòng - không ảnh hưởng công suất</span>
                <i class="fa-solid fa-circle-info text-gray-400 cursor-help" title="Lưu IsRoomNight=0 (không tính vào công suất phòng)"></i>
              </div>
              <button 
                type="button" 
                @click="toggleSurcharge"
                :class="['relative w-9 h-5 rounded-full transition-colors flex-shrink-0 ml-2',
                  isSurcharge ? 'bg-blue-600' : 'bg-gray-300']"
              >
                <div 
                  :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    isSurcharge ? 'translate-x-4.5' : 'translate-x-0.5']" 
                />
              </button>
            </div>
          </div>

          <!-- Right Side: Tổng tiền -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">Tổng tiền</label>
            <input 
              v-if="customRateMode" 
              v-model.number="customRate" 
              type="number" 
              min="0"
              class="w-full border border-blue-500 rounded-lg px-3.5 py-2.5 text-sm bg-white text-gray-800 font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <input 
              v-else 
              :value="autoRateText" 
              readonly 
              disabled
              class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm bg-[#f1f5f9] text-gray-500 font-semibold cursor-not-allowed"
            />
          </div>
        </div>

        <!-- Mô tả -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Mô tả</label>
          <input 
            type="text"
            v-model="description"
            class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-xs font-medium bg-white text-gray-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            placeholder="Dịch vụ phòng nghỉ noshow..."
          />
        </div>

        <!-- Lý do -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Lý do</label>
          <input 
            type="text" 
            v-model="reason"
            class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-xs font-medium bg-white text-gray-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            placeholder="Nhập lý do"
          />
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 flex justify-end space-x-2.5 shrink-0">
        <button 
          @click="close" 
          class="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-lg hover:bg-slate-100 cursor-pointer transition bg-white"
        >
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer shadow-sm flex items-center space-x-1.5 transition border-none"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { chargeRoomNoshow } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  bookingName: String,
  guestName: String,
  roomId: Number,
  roomNumber: String,
  arrivalDate: String,
  departureDate: String,
  roomRate: Number
})

const emit = defineEmits(['update:show', 'saved'])
const uiStore = useUiStore()

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

const dateFrom = ref('')
const dateTo = ref('')
const customRate = ref(0)
const customRateMode = ref(false)
const isSurcharge = ref(true) // Toggle 2: Bổ sung tiền phòng - không ảnh hưởng công suất
const description = ref('')
const reason = ref('')

// Tên Đăng ký: Tên khách chính - Số phòng
const displayName = computed(() => {
  const name = props.guestName || props.bookingName || 'Khách lẻ'
  return `${name} - ${props.roomNumber || ''}`.trim()
})

// Tính số đêm giữa hai ngày
const numNights = computed(() => {
  if (!dateFrom.value || !dateTo.value) return 0
  const d1 = new Date(dateFrom.value)
  const d2 = new Date(dateTo.value)
  const diffTime = d2.getTime() - d1.getTime()
  if (isNaN(diffTime) || diffTime <= 0) return 0
  return Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)))
})

// Tiền phòng tự động tính toán
const autoRateAmount = computed(() => {
  const nights = numNights.value || 1
  return (props.roomRate || 0) * nights
})

// Định dạng tiền phòng tự động sang vi-VN
const autoRateText = computed(() => {
  return Math.round(autoRateAmount.value).toLocaleString('vi-VN')
})

function ensureYmd(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  const parts = dateStr.split('/')
  if (parts.length === 3) {
    const day = parts[0].padStart(2, '0')
    const month = parts[1].padStart(2, '0')
    const year = parts[2]
    return `${year}-${month}-${day}`
  }
  try {
    const d = new Date(dateStr)
    if (!isNaN(d)) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  } catch (e) {}
  return dateStr
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
    dateFrom.value = ensureYmd(props.arrivalDate)
    dateTo.value = ensureYmd(props.departureDate)
    customRateMode.value = false
    isSurcharge.value = true
    customRate.value = Math.round(props.roomRate || 0)
    description.value = `Dịch vụ phòng nghỉ noshow phòng ${props.roomNumber || ''}`.trim()
    reason.value = ''
  }
})

watch(() => props.arrivalDate, (newVal) => {
  if (newVal) dateFrom.value = ensureYmd(newVal)
})

watch(() => props.departureDate, (newVal) => {
  if (newVal) dateTo.value = ensureYmd(newVal)
})

watch(() => props.roomRate, (newVal) => {
  if (newVal !== undefined) customRate.value = Math.round(newVal || 0)
})

function close() {
  emit('update:show', false)
}

function toggleCustomRateMode() {
  customRateMode.value = !customRateMode.value
  if (customRateMode.value && (!customRate.value || customRate.value === 0)) {
    customRate.value = Math.round(autoRateAmount.value)
  }
}

function toggleSurcharge() {
  isSurcharge.value = !isSurcharge.value
}

async function handleSave() {
  if (!props.bookingId || !props.roomId) return

  if (!dateFrom.value || !dateTo.value) {
    uiStore.showToast('Vui lòng chọn khoảng ngày cần charge!', 'warning')
    return
  }

  if (dateTo.value < dateFrom.value) {
    uiStore.showToast('Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu!', 'warning')
    return
  }

  const finalRate = customRateMode.value ? customRate.value : autoRateAmount.value

  uiStore.showToast('Đang thực hiện charge noshow...', 'info')

  try {
    const res = await chargeRoomNoshow(props.bookingId, props.roomId, {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      rate: finalRate,
      auto_rate: !customRateMode.value,
      is_room_night: isSurcharge.value ? 0 : 1, // Bật (isSurcharge=true) => is_room_night=0; Tắt (isSurcharge=false) => is_room_night=1
      description: description.value,
      reason: reason.value
    })

    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Charge noshow thành công!', 'success')
      close()
      emit('saved')
    } else {
      uiStore.showToast(res.data?.message || 'Thao tác thất bại!', 'error')
    }
  } catch (err) {
    console.error('Charge noshow error:', err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi charge noshow!', 'error')
  }
}
</script>
