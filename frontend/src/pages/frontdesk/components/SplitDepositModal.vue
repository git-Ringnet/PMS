<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronDown, CircleHelp, Scissors, X } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  totalAmount: { type: Number, default: 0 },
  folio: { type: Number, default: 1 }
})

const emit = defineEmits(['close', 'split'])
const amount = ref('')
const targetFolio = ref('1')
const availableFolios = computed(() => [1, 2, 3].filter(f => f !== Number(props.folio)))

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

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) emit('close')
}

watch(() => props.show, visible => {
  if (visible) {
    amount.value = ''
    targetFolio.value = String(availableFolios.value[0] || (props.folio === 1 ? '2' : '1'))
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

const parseAmount = (val) => {
  const clean = String(val || '').replace(/,/g, '').trim()
  const num = Number(clean)
  return Number.isFinite(num) ? num : 0
}

const onAmountInput = (event) => {
  const digits = String(event.target.value || '').replace(/\D/g, '')
  amount.value = digits ? Number(digits).toLocaleString('en-US') : ''
}

const amountLabel = computed(() => new Intl.NumberFormat('vi-VN').format(Number(props.totalAmount) || 0))
const splitAmounts = computed(() => {
  const selectedAmount = parseAmount(amount.value)
  const total = Number(props.totalAmount) || 0
  return selectedAmount > 0 && selectedAmount < total
    ? [selectedAmount, Number((total - selectedAmount).toFixed(2))]
    : []
})

const submit = () => {
  const numericAmount = parseAmount(amount.value)
  if (!props.loading && targetFolio.value && numericAmount > 0 && numericAmount < props.totalAmount) {
    emit('split', { amount: numericAmount, folio: Number(targetFolio.value) })
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-2 font-sans select-none">
    <div
      ref="modalRef"
      class="w-full max-w-[445px] overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      <div
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Tách Cọc</span>
        <div class="flex items-center gap-2">
          <CircleHelp class="h-4 w-4" />
          <button type="button" :disabled="loading" @click="emit('close')" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer disabled:opacity-40"><X class="h-4 w-4" /></button>
        </div>
      </div>

      <div class="space-y-5 bg-[#f8fafc] px-5 py-6">
        <div class="space-y-3">
          <div class="text-center font-semibold text-[#000000D9]">Tổng cọc: <span class="text-[#155DFC]">{{ amountLabel }}</span> đ</div>
          <label class="flex items-center justify-center gap-4 font-semibold text-[#000000D9]">
            Số tiền <span class="text-red-500">*</span>
            <div class="relative w-44">
              <input
                :value="amount"
                type="text"
                inputmode="numeric"
                required
                placeholder="Nhập số tiền..."
                @input="onAmountInput"
                class="h-8 w-full rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2.5 pr-7 text-right font-normal text-[#000000D9] outline-none focus:border-amber-500 placeholder:text-[#A8B0BF]"
              />
              <button v-if="amount" type="button" title="Xóa số tiền" @click="amount = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" /></button>
            </div>
          </label>
        </div>

        <label class="flex items-center justify-center gap-4 font-semibold text-[#000000D9]">
          Folio <span class="text-red-500">*</span>
          <div class="relative w-36">
            <select
              v-model="targetFolio"
              required
              class="h-8 w-full appearance-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2.5 pr-7 font-normal text-[#000000D9] outline-none focus:border-amber-500"
            >
              <option v-for="item in availableFolios" :key="item" :value="String(item)" class="bg-white">Folio {{ item }}</option>
            </select>
            <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
          </div>
        </label>
      </div>

      <div class="flex justify-end border-t border-slate-200 bg-white px-4 py-3">
        <button
          type="button"
          :disabled="loading || splitAmounts.length !== 2"
          @click="submit"
          class="btn-pms-primary"
        >
          <Scissors class="h-3.5 w-3.5" />
          {{ loading ? 'Đang tách...' : 'Tách' }}
        </button>
      </div>
    </div>
  </div>
</template>
