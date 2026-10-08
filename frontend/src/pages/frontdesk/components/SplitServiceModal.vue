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
const folio = ref('1')
const amount = ref('')
const availableFolios = computed(() => [1, 2, 3])

watch(() => props.show, (visible) => {
  if (visible) {
    folio.value = String(availableFolios.value[0] || '')
    amount.value = ''
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    window.removeEventListener('keydown', handleKeyDown)
  }
})

const amountLabel = computed(() => props.totalAmount ? new Intl.NumberFormat('vi-VN').format(props.totalAmount) : '0')
const parseAmount = value => Number(String(value || '').replace(/,/g, '')) || 0
const onAmountInput = (event) => {
  const digits = String(event.target.value || '').replace(/\D/g, '')
  amount.value = digits ? Number(digits).toLocaleString('en-US') : ''
}
const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyDown))

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
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-2">
    <div class="w-full max-w-[445px] overflow-hidden rounded-xl border border-sky-500 bg-white shadow-2xl text-xs">
      <div class="flex items-center justify-between px-4 py-2 text-white" :style="{ background: 'var(--pms-custom-theme, #006bdb)' }">
        <span class="font-bold">Tách dịch vụ</span>
        <div class="flex items-center gap-2">
          <CircleHelp class="h-5 w-5" />
          <button type="button" @click="close" class="rounded p-0.5 hover:bg-white/10"><X class="h-5 w-5" /></button>
        </div>
      </div>

      <div class="space-y-5 bg-[#eef6ff] px-5 py-7">
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
