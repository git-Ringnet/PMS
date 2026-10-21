<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { CircleHelp, X, Scissors } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  selectedCount: { type: Number, default: 0 },
  totalAmount: { type: Number, default: 0 }
})

const emit = defineEmits(['close', 'split'])

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

const folio = ref('1')
const amount = ref('')
const availableFolios = computed(() => [1, 2, 3])

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, (visible) => {
  if (visible) {
    resetModalPosition()
    folio.value = String(availableFolios.value[0] || '')
    amount.value = ''
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    endModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
  }
})

onBeforeUnmount(() => {
  endModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})

const amountLabel = computed(() => props.totalAmount ? new Intl.NumberFormat('vi-VN').format(props.totalAmount) : '0')
const parseAmount = value => Number(String(value || '').replace(/,/g, '')) || 0
const onAmountInput = (event) => {
  const digits = String(event.target.value || '').replace(/\D/g, '')
  amount.value = digits ? Number(digits).toLocaleString('en-US') : ''
}

const submit = () => {
  if (!props.selectedCount || props.loading) return
  const numericAmount = parseAmount(amount.value)
  if (!folio.value || !(numericAmount > 0) || numericAmount >= props.totalAmount) return
  emit('split', {
    folio: Number(folio.value),
    amount: numericAmount
  })
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-2 font-sans select-none">
    <div
      ref="modalRef"
      class="w-full max-w-[445px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl text-xs"
      :style="{ transform: `translate(${modalPosition.x}px, ${modalPosition.y}px)`, transition: isDraggingModal ? 'none' : '' }"
    >
      <div
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none touch-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @pointerdown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Tách Dịch Vụ</span>
        <div class="flex items-center gap-2">
          <CircleHelp class="h-4 w-4 text-white/80" />
          <button @pointerdown.stop type="button" @click="close" class="rounded p-0.5 hover:bg-white/20 text-white cursor-pointer"><X class="h-4 w-4" /></button>
        </div>
      </div>

      <div class="space-y-5 bg-[#f8fafc] px-5 py-6">
        <div class="space-y-3">
          <div class="text-center font-semibold text-[#000000D9]">Tổng đã chọn: <span class="text-[#155DFC]">{{ amountLabel }}</span> đ</div>
          <label class="flex items-center justify-center gap-4 font-semibold text-[#000000D9]">Số tiền
            <div class="relative w-44">
              <input :value="amount" type="text" inputmode="numeric" required @input="onAmountInput" class="h-8 w-full rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2 pr-7 text-right font-normal text-[#000000D9] outline-none focus:border-amber-500" />
              <button v-if="amount" type="button" title="Xóa số tiền" @click="amount = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" /></button>
            </div>
          </label>
        </div>

        <label class="flex items-center justify-center gap-4 font-semibold text-[#000000D9]">Folio
          <div class="relative w-36">
            <select v-model="folio" required class="h-8 w-full appearance-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2 pr-7 font-normal text-[#000000D9] outline-none focus:border-amber-500">
              <option v-for="item in availableFolios" :key="item" :value="String(item)" class="bg-white">{{ item }}</option>
            </select>
            <button v-if="folio" type="button" title="Xóa Folio" @click="folio = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" /></button>
          </div>
        </label>
      </div>

      <div class="flex justify-end border-t border-slate-200 bg-white px-4 py-3">
        <button type="button" @click="submit" :disabled="!selectedCount || !folio || !parseAmount(amount)" class="btn-pms-primary"><Scissors class="h-3.5 w-3.5" /> Tách</button>
      </div>
    </div>
  </div>
</template>
