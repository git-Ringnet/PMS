<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[450px] overflow-hidden border border-slate-200 flex flex-col"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- MODAL HEADER -->
      <div 
        class="text-white flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move rounded-t-xl"
        :style="{ background: topbarThemeBg, color: 'var(--pms-custom-theme-text, #ffffff)' }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-semibold text-xs tracking-wider">
          <i class="fa-solid fa-clone"></i>
          <span>Nhân Bản Đăng Ký Phòng</span>
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
      <div class="p-5 flex flex-col gap-4 text-xs font-normal text-[#000000D9]">
        <p v-if="isCopyModeLoading" class="text-slate-600 leading-relaxed text-xs">
          Đang tải cấu hình nhân bản đăng ký...
        </p>
        <template v-else-if="copyModeLoadError">
          <p class="text-red-600 leading-relaxed text-xs">
            Không thể tải cấu hình nhân bản. Vui lòng thử lại.
          </p>
          <button type="button" class="btn-pms-secondary self-start" @click="loadCopyMode">
            Tải lại
          </button>
        </template>
        <template v-else>
          <!-- Dòng 500: IsCopyAllBooking = 0 chỉ xác nhận, không chọn lại ngày -->
          <p v-if="copyHeaderOnly" class="text-slate-600 leading-relaxed text-xs">
            Bạn có chắc chắn muốn nhân bản đăng ký này không? Bấm xác nhận để nhân bản hoặc nút tắt để thoát màn hình xác nhận.
          </p>
          <p v-else class="text-slate-600 leading-relaxed text-xs">
            Nhân bản đăng ký này sang một thời gian mới. Toàn bộ thông tin khách hàng, loại phòng, số lượng phòng và đơn giá sẽ được sao chép tự động.
          </p>

          <div v-if="!copyHeaderOnly" class="grid grid-cols-2 gap-3 mt-2">
            <div>
              <label class="block text-[#000000D9] mb-1 font-semibold text-xs">Ngày đến mới <span class="text-red-500">*</span></label>
              <SingleDatePicker
                v-model="arrivalDate"
                placeholder="dd/mm/yyyy"
                four-digit-year
                input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-slate-200 !text-xs !font-normal"
              />
            </div>
            <div>
              <label class="block text-[#000000D9] mb-1 font-semibold text-xs">Ngày đi mới <span class="text-red-500">*</span></label>
              <SingleDatePicker
                v-model="departureDate"
                :min-date="arrivalDate"
                placeholder="dd/mm/yyyy"
                four-digit-year
                input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-slate-200 !text-xs !font-normal"
              />
            </div>
          </div>
        </template>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 flex justify-end space-x-2 shrink-0 rounded-b-xl">
        <button 
          @click="close" 
          type="button"
          class="btn-pms-close"
        >
          <i class="fa-solid fa-xmark"></i>
          <span>Đóng</span>
        </button>
        <button 
          @click="handleConfirmCopy" 
          type="button"
          class="btn-pms-primary"
          :disabled="isCopyModeLoading || copyModeLoadError"
        >
          <i class="fa-solid fa-check"></i>
          <span>Xác nhận nhân bản</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount, watch } from 'vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { copyBooking, fetchHotelSettings } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  defaultArrival: String,
  defaultDeparture: String
})

const emit = defineEmits(['update:show', 'copied'])

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => {
  return authStore.themeColor || 'var(--pms-custom-theme, #006bdb)'
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

const arrivalDate = ref('')
const departureDate = ref('')
// IsCopyAllBooking = 0: chỉ nhân bản thông tin đăng ký, không chọn lại ngày.
const copyHeaderOnly = ref(false)
const isCopyModeLoading = ref(false)
const copyModeLoadError = ref(false)
let copyModeRequestId = 0

async function loadCopyMode() {
  const requestId = ++copyModeRequestId
  isCopyModeLoading.value = true
  copyModeLoadError.value = false
  copyHeaderOnly.value = false
  try {
    const res = await fetchHotelSettings()
    if (requestId !== copyModeRequestId) return
    copyHeaderOnly.value = String(res.data?.data?.IsCopyAllBooking ?? '1') === '0'
  } catch {
    if (requestId !== copyModeRequestId) return
    copyModeLoadError.value = true
  } finally {
    if (requestId === copyModeRequestId) isCopyModeLoading.value = false
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
    arrivalDate.value = props.defaultArrival || ''
    departureDate.value = props.defaultDeparture || ''
    loadCopyMode()
  } else {
    copyModeRequestId++
  }
})

function close() {
  copyModeRequestId++
  emit('update:show', false)
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, (isShown) => {
  if (isShown) window.addEventListener('keydown', handleKeyDown)
  else window.removeEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyDown))

async function executeCopy(payload = {}) {
  const requestData = copyHeaderOnly.value
    ? { ...payload }
    : {
        arrival_date: arrivalDate.value,
        departure_date: departureDate.value,
        ...payload,
      }

  try {
    const res = await copyBooking(props.bookingId, requestData)
    if (res.data?.success) {
      uiStore.showToast(res.data?.message ? res.data.message.replace(/booking/i, 'đăng ký') : 'Nhân bản đăng ký thành công!', 'success')
      close()
      emit('copied', res.data.data)
      return true
    } else {
      uiStore.showToast(res.data?.message || 'Nhân bản thất bại!', 'error')
      return false
    }
  } catch (err) {
    const errorData = err.response?.data
    if (errorData?.require_confirm === 'over_warning') {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo phòng âm',
        message: errorData.message || 'Phòng âm bạn có muốn tiếp tục thao tác',
        confirmText: 'Có',
        cancelText: 'Không',
      })
      if (confirmed) {
        return await executeCopy({ confirm_over: true })
      }
      return false
    }

    if (errorData?.require_confirm === 'no_rooms_available') {
      const confirmed = await uiStore.confirm({
        title: 'Không còn phòng trống',
        message: errorData.message || 'Không còn phòng trống, bạn có muốn tiếp tục thao tác',
        confirmText: 'Có',
        cancelText: 'Không',
      })
      if (confirmed) {
        return await executeCopy({ copy_header_only: true })
      }
      return false
    }

    console.error('Copy booking error:', err)
    uiStore.showToast(errorData?.message || 'Có lỗi xảy ra khi nhân bản đăng ký!', 'error')
    return false
  }
}

async function handleConfirmCopy() {
  if (!props.bookingId || isCopyModeLoading.value || copyModeLoadError.value) return

  if (!copyHeaderOnly.value) {
    if (!arrivalDate.value || !departureDate.value) {
      uiStore.showToast('Vui lòng chọn đầy đủ ngày đến và ngày đi!', 'warning')
      return
    }

    if (departureDate.value <= arrivalDate.value) {
      uiStore.showToast('Ngày đi phải lớn hơn ngày đến!', 'warning')
      return
    }
  }

  uiStore.showToast('Đang thực hiện nhân bản đăng ký...', 'info')
  await executeCopy()
}
</script>
