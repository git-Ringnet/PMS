<script setup>
import { ref, onBeforeUnmount, watch } from 'vue'
import { X } from '@lucide/vue'
import PostBillHousekeepingTab from '@/pages/housekeeping/components/PostBillHousekeepingTab.vue'

const props = defineProps({
  show: Boolean,
  bookingInfo: {
    type: String,
    default: ''
  },
  roomId: {
    type: [String, Number],
    default: ''
  },
  includeInitialCheckedOutRoom: {
    type: Boolean,
    default: false
  },
  guestId: {
    type: [String, Number],
    default: ''
  },
  initialAdjustment: {
    type: Object,
    default: null
  },
  folioId: {
    type: [String, Number],
    default: 1
  }
})

const emit = defineEmits(['close', 'submit'])

const modalRef = ref(null)
const modalPosition = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 })

function clampModalPosition(x, y) {
  const modal = modalRef.value
  if (!modal) return { x, y }

  const margin = 16
  const width = modal.offsetWidth
  const height = modal.offsetHeight
  const baseLeft = (window.innerWidth - width) / 2
  const baseTop = (window.innerHeight - height) / 2
  const minX = margin - baseLeft
  const maxX = window.innerWidth - margin - width - baseLeft
  const minY = margin - baseTop
  const maxY = window.innerHeight - margin - height - baseTop

  return {
    x: minX <= maxX ? Math.min(Math.max(x, minX), maxX) : 0,
    y: minY <= maxY ? Math.min(Math.max(y, minY), maxY) : 0,
  }
}

function beginModalDrag(event) {
  if (event.button !== 0) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('pointermove', moveModal)
  window.addEventListener('pointerup', endModalDrag)
}

function moveModal(event) {
  if (!isDraggingModal.value) return
  modalPosition.value = clampModalPosition(
    dragStart.value.left + event.clientX - dragStart.value.x,
    dragStart.value.top + event.clientY - dragStart.value.y,
  )
}

function endModalDrag() {
  isDraggingModal.value = false
  window.removeEventListener('pointermove', moveModal)
  window.removeEventListener('pointerup', endModalDrag)
}

function resetModalPosition() {
  endModalDrag()
  modalPosition.value = { x: 0, y: 0 }
}

const handleClose = () => {
  emit('close')
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) handleClose()
}

watch(() => props.show, (visible) => {
  window.removeEventListener('keydown', handleKeyDown)
  if (visible) {
    resetModalPosition()
    window.addEventListener('keydown', handleKeyDown)
  }
})
onBeforeUnmount(() => {
  endModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})

const handleSuccess = (data) => {
  emit('submit', data)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 md:p-4 animate-fadeIn">
    <div
      ref="modalRef"
      class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[90vh] overflow-hidden border border-slate-200 flex flex-col text-xs"
      :style="{ transform: `translate(${modalPosition.x}px, ${modalPosition.y}px)`, transition: isDraggingModal ? 'none' : '' }"
    >
      <!-- Header -->
      <div
        class="text-white px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-sm cursor-move select-none touch-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @pointerdown="beginModalDrag"
      >
        <span class="font-semibold text-xs tracking-wide">Thêm Dịch Vụ Buồng Phòng</span>
        <div class="flex items-center gap-2">
          <button @pointerdown.stop @click="handleClose" class="hover:bg-white/20 p-1 rounded transition-colors text-white cursor-pointer" title="Đóng">
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Full Housekeeping Post Bill Form Component -->
      <div class="flex-1 overflow-hidden">
        <PostBillHousekeepingTab
          :initialRoomId="roomId"
          :includeInitialCheckedOutRoom="includeInitialCheckedOutRoom"
          :initialGuestId="guestId"
          :initialAdjustment="initialAdjustment"
          :initialFolioId="folioId"
          :isModal="true"
          :department="'FO'"
          :postingSource="'FO'"
          @close="handleClose"
          @success="handleSuccess"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
</style>
