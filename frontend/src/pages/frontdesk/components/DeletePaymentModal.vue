<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { X, Trash2 } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  payment: { type: Object, default: null },
  paymentGroup: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'submit'])
const reason = ref('')

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
  if (event.button !== 0 || event.target.closest('button, input, select, textarea')) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('mousemove', onModalDrag)
  window.addEventListener('mouseup', stopModalDrag)
}

function onModalDrag(event) {
  if (!isDraggingModal.value) return
  const rawX = dragStart.value.left + (event.clientX - dragStart.value.x)
  const rawY = dragStart.value.top + (event.clientY - dragStart.value.y)
  modalPosition.value = clampModalPosition(rawX, rawY)
}

function stopModalDrag() {
  if (!isDraggingModal.value) return
  isDraggingModal.value = false
  window.removeEventListener('mousemove', onModalDrag)
  window.removeEventListener('mouseup', stopModalDrag)
}

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, visible => {
  if (visible) {
    reason.value = ''
    modalPosition.value = { x: 0, y: 0 }
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    stopModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
  }
})

onBeforeUnmount(() => {
  stopModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})

const submit = () => {
  const value = reason.value.trim()
  if (!props.loading && value) emit('submit', value)
}

</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 font-sans select-none">
    <div
      ref="modalRef"
      class="w-full max-w-md overflow-hidden rounded-xl border border-slate-300 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      <header
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Xóa Thanh Toán</span>
        <button :disabled="loading" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer" @click="close"><X class="h-4 w-4" /></button>
      </header>
      <div class="space-y-3 p-4">
        <label class="block font-semibold text-[#000000D9]">Lý do xóa <span class="text-red-500">*</span></label>
        <textarea v-model="reason" :disabled="loading" required rows="3" maxlength="1000" placeholder="Nhập lý do xóa thanh toán" class="w-full resize-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2.5 py-2 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-amber-500" />
      </div>
      <footer class="flex justify-end border-t border-gray-300 bg-gray-50 p-3">
        <button :disabled="loading || !reason.trim()" class="btn-pms-danger" @click="submit"><Trash2 class="h-4 w-4" />{{ loading ? 'Đang xử lý...' : 'Xóa Thanh Toán' }}</button>
      </footer>
    </div>
  </div>
</template>
