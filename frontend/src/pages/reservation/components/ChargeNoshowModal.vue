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
        class="text-white flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move rounded-t-xl"
        :style="{ background: topbarThemeBg, color: 'var(--pms-custom-theme-text, #ffffff)' }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-semibold text-xs tracking-wider">
          <i class="fa-solid fa-ban"></i>
          <span>Charge No-Show</span>
        </div>
        <button 
          class="hover:opacity-80 p-1 rounded cursor-pointer border-none bg-transparent text-white" 
          @click="close"
          title="Đóng"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
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
            <div class="grid grid-cols-2 gap-2 items-center">
              <SingleDatePicker
                v-model="dateFrom"
                :start-date="dateFrom || authStore.systemDate"
                placeholder="dd/mm/yyyy"
                four-digit-year
              />
              <SingleDatePicker
                v-model="dateTo"
                :start-date="dateTo || dateFrom || authStore.systemDate"
                placeholder="dd/mm/yyyy"
                four-digit-year
              />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">Tiền phòng tự động</label>
            <input
              :value="nightlyRateText"
              :title="nightlyRateText"
              data-testid="noshow-nightly-rate"
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
                :disabled="isMultiRoom"
                :title="isMultiRoom ? 'Charge nhiều phòng: tiền phòng tự động theo giá từng phòng' : ''"
                :class="['relative w-9 h-5 rounded-full transition-colors flex-shrink-0',
                  customRateMode ? 'bg-blue-600' : 'bg-gray-300',
                  isMultiRoom ? 'opacity-50 cursor-not-allowed' : '']"
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
              data-testid="noshow-total"
              readonly
              disabled
              class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm bg-[#f1f5f9] text-gray-500 font-semibold cursor-not-allowed"
            />
          </div>
        </div>

        <p class="text-[11px] font-medium text-slate-500 -mt-2" data-testid="noshow-split-hint">
          <template v-if="isMultiRoom">
            Hệ thống post {{ chargeNightCount }} bill tiền phòng cho mỗi phòng ({{ targetRooms.length }} phòng), mỗi đêm một bill theo tiền phòng đã thiết lập của từng phòng.
          </template>
          <template v-else-if="chargeNightCount > 1">
            Hệ thống post {{ chargeNightCount }} bill tiền phòng, mỗi đêm một bill theo đúng ngày
            {{ customRateMode ? '(tổng nhập được chia đều theo số đêm).' : '(tiền phòng đã thiết lập của từng đêm).' }}
          </template>
          <template v-else>Hệ thống post 1 bill tiền phòng cho ngày đã chọn.</template>
        </p>

        <!-- Mô tả -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1.5">Mô tả</label>
          <input
            v-if="isMultiRoom"
            type="text"
            :value="multiRoomDescriptionText"
            disabled
            data-testid="noshow-multi-description"
            class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-xs font-medium bg-[#f1f5f9] text-gray-500 cursor-not-allowed"
          />
          <input
            v-else
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
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 flex justify-end space-x-2 shrink-0 rounded-b-xl">
        <button 
          type="button"
          @click="close" 
          class="btn-pms-close"
        >
          <i class="fa-solid fa-xmark"></i>
          <span>Đóng</span>
        </button>
        <button 
          type="button"
          @click="handleSave" 
          class="btn-pms-primary"
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
import { useAuthStore } from '@/stores/auth-store'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { noshowAutoAmount, noshowChargeNights, noshowDescription, noshowNightlyRate, noshowRoomsTotal } from '@/utils/noshow-charge'

const authStore = useAuthStore()
const topbarThemeBg = computed(() => {
  return authStore.themeColor || 'var(--pms-custom-theme, #006bdb)'
})

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  bookingName: String,
  guestName: String,
  roomId: [Number, String],
  roomNumber: String,
  arrivalDate: String,
  departureDate: String,
  roomRate: Number,
  dailyRoomPrices: { type: Object, default: null },
  // Dòng 532: ngày mặc định để charge (màn Sang ngày truyền ngày noshow của dòng), mặc định ngày hệ thống
  defaultDate: { type: String, default: '' },
  // Dòng 508 (note 26/11): charge một lúc nhiều phòng noshow đã chọn.
  // Mỗi phần tử { roomId, roomNumber, guestName, roomRate, dailyRoomPrices }; rỗng thì dùng các prop của 1 phòng ở trên.
  rooms: { type: Array, default: () => [] }
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

// Dòng 508 (note 26/11): danh sách phòng cần charge (1 phòng hoặc nhiều phòng đã chọn)
const targetRooms = computed(() => (props.rooms?.length
  ? props.rooms
  : [{ roomId: props.roomId, roomNumber: props.roomNumber, guestName: props.guestName, roomRate: props.roomRate, dailyRoomPrices: props.dailyRoomPrices }]))
const isMultiRoom = computed(() => targetRooms.value.length > 1)

// Tên Đăng ký: Tên khách chính - Số phòng
const displayName = computed(() => targetRooms.value
  .map(room => `${room.guestName || props.bookingName || 'Khách lẻ'} - ${room.roomNumber || ''}`.trim())
  .join(', '))

// Dòng 508 (note 22/11): mỗi ngày trong khoảng đã chọn là một bill, tính cả hai đầu.
const chargeNightCount = computed(() => Math.max(1, noshowChargeNights(dateFrom.value, dateTo.value)))

const formatMoney = value => Math.round(Number(value) || 0).toLocaleString('vi-VN')

// Dòng 508 (note 26/11): "Tiền phòng tự động" = tiền 1 đêm của phòng (nhiều phòng khác giá thì liệt kê theo phòng)
const nightlyRateText = computed(() => {
  const rates = targetRooms.value.map(room => ({
    roomNumber: room.roomNumber,
    rate: noshowNightlyRate(dateFrom.value, room.roomRate, room.dailyRoomPrices),
  }))
  if (new Set(rates.map(item => item.rate)).size <= 1) return formatMoney(rates[0]?.rate)
  return rates.map(item => `${item.roomNumber}: ${formatMoney(item.rate)}`).join(' · ')
})

// "Tổng tiền" = tổng tiền từng đêm trong khoảng chọn của tất cả phòng
const autoRateAmount = computed(() => noshowRoomsTotal(targetRooms.value, dateFrom.value, dateTo.value))

// Định dạng tổng tiền tự động sang vi-VN
const autoRateText = computed(() => formatMoney(autoRateAmount.value))

const multiRoomDescriptionText = computed(() => `${noshowDescription('<số phòng>')} (theo từng phòng)`)

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
    // Dòng 508 (note 22/11): mặc định charge đúng ngày hệ thống, tiền = 1 đêm phòng.
    const defaultDate = ensureYmd(props.defaultDate || authStore.systemDate || props.arrivalDate)
    dateFrom.value = defaultDate
    dateTo.value = defaultDate
    customRateMode.value = false
    isSurcharge.value = true
    customRate.value = Math.round(props.roomRate || 0)
    // Dòng 540/543: mô tả "Dịch vụ phòng nghỉ noshow 102"
    description.value = noshowDescription(targetRooms.value[0]?.roomNumber)
    reason.value = ''
  }
})

watch(() => props.roomRate, (newVal) => {
  if (newVal !== undefined) customRate.value = Math.round(newVal || 0)
})

function close() {
  emit('update:show', false)
}

function toggleCustomRateMode() {
  if (isMultiRoom.value) return
  customRateMode.value = !customRateMode.value
  if (customRateMode.value && (!customRate.value || customRate.value === 0)) {
    customRate.value = Math.round(autoRateAmount.value)
  }
}

function toggleSurcharge() {
  isSurcharge.value = !isSurcharge.value
}

const isSaving = ref(false)

async function handleSave() {
  if (!props.bookingId || targetRooms.value.some(room => !room.roomId) || isSaving.value) return

  if (!dateFrom.value || !dateTo.value) {
    uiStore.showToast('Vui lòng chọn khoảng ngày cần charge!', 'warning')
    return
  }

  if (dateTo.value < dateFrom.value) {
    uiStore.showToast('Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu!', 'warning')
    return
  }

  const useCustomRate = customRateMode.value && !isMultiRoom.value

  uiStore.showToast('Đang thực hiện charge noshow...', 'info')

  // Dòng 508 (note 26/11): charge lần lượt từng phòng đã chọn, báo phòng nào lỗi.
  isSaving.value = true
  const failed = []
  let successCount = 0
  try {
    for (const room of targetRooms.value) {
      try {
        const res = await chargeRoomNoshow(props.bookingId, room.roomId, {
          date_from: dateFrom.value,
          date_to: dateTo.value,
          rate: useCustomRate
            ? customRate.value
            : noshowAutoAmount(dateFrom.value, dateTo.value, room.roomRate, room.dailyRoomPrices),
          auto_rate: !useCustomRate,
          is_room_night: isSurcharge.value ? 0 : 1, // Bật (isSurcharge=true) => is_room_night=0; Tắt (isSurcharge=false) => is_room_night=1
          description: isMultiRoom.value ? noshowDescription(room.roomNumber) : description.value,
          reason: reason.value
        })
        if (res.data?.success) successCount++
        else failed.push(`${room.roomNumber}: ${res.data?.message || 'Thao tác thất bại!'}`)
      } catch (err) {
        console.error('Charge noshow error:', err)
        failed.push(`${room.roomNumber}: ${err.response?.data?.message || 'Có lỗi xảy ra khi charge noshow!'}`)
      }
    }
  } finally {
    isSaving.value = false
  }

  if (failed.length === 0) {
    uiStore.showToast(isMultiRoom.value ? `Charge noshow thành công ${successCount} phòng!` : 'Charge noshow thành công!', 'success')
    close()
  } else {
    uiStore.showToast(failed.join(' | '), 'error')
  }
  if (successCount > 0) emit('saved')
}
</script>
