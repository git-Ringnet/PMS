<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { X, Trash2, PencilLine } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  count: { type: Number, default: 0 },
  canDelete: { type: Boolean, default: false },
  canAdjust: { type: Boolean, default: false }
})
const emit = defineEmits(['close', 'submit', 'adjust'])
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
  if (!props.loading && reason.value.trim()) emit('submit', reason.value.trim())
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 font-sans select-none">
    <div
      ref="modalRef"
      class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      <header
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Xóa Dịch Vụ</span>
        <button :disabled="loading" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer" @click="close"><X class="h-4 w-4" /></button>
      </header>
      <div class="space-y-3 p-4">
        <template v-if="canDelete">
          <label class="block font-semibold text-[#000000D9]">Lý do xóa <span class="text-red-500">*</span></label>
          <textarea v-model="reason" :disabled="loading" required rows="3" maxlength="255" placeholder="Ví dụ: POST NHẦM" class="w-full resize-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-3 py-2 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-amber-500" />
        </template>
        <p v-else class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">Tiền phòng không được xóa; chỉ được điều chỉnh giá.</p>
      </div>
      <footer v-if="canAdjust || canDelete" class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 p-3">
        <button v-if="canAdjust" :disabled="loading" class="btn-pms-secondary" @click="emit('adjust')"><PencilLine class="h-3.5 w-3.5" />Điều Chỉnh Giá</button>
        <button v-if="canDelete" :disabled="loading || !reason.trim()" class="btn-pms-danger" @click="submit"><Trash2 class="h-3.5 w-3.5" />{{ loading ? 'Đang xóa...' : 'Xóa Dịch Vụ' }}</button>
      </footer>
    </div>
  </div>
</template>
