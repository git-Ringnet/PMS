<script setup>
import { computed, ref, watch } from 'vue'
import { CircleHelp, Scissors, X } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  totalAmount: { type: Number, default: 0 },
  folio: { type: Number, default: 1 }
})

const emit = defineEmits(['close', 'split'])
const amount = ref('')
const targetFolio = ref('1')
const availableFolios = computed(() => [1, 2, 3])

watch(() => props.show, visible => {
  if (visible) {
    amount.value = ''
    targetFolio.value = String(availableFolios.value[0])
  }
})

const amountLabel = computed(() => new Intl.NumberFormat('vi-VN').format(Number(props.totalAmount) || 0))
const splitAmounts = computed(() => {
  const selectedAmount = Number(amount.value)
  const total = Number(props.totalAmount) || 0
  return selectedAmount > 0 && selectedAmount < total
    ? [selectedAmount, Number((total - selectedAmount).toFixed(2))]
    : []
})

const submit = () => {
  if (!props.loading && splitAmounts.value.length === 2) {
    emit('split', { amount: Number(amount.value), folio: Number(targetFolio.value) })
  }
}
</script>

<style scoped>
button {
  transition: background-color 150ms ease, transform 100ms ease;
}
button:hover:not(:disabled) { background-color: #0577d7; }
button:active:not(:disabled) { transform: scale(.95); }
button:disabled { cursor: not-allowed; opacity: .4; }
</style>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-2">
    <div class="w-full max-w-[445px] overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <div class="flex items-center justify-between bg-[#0788f5] px-4 py-2 text-white">
        <span class="font-bold">Tách cọc</span>
        <div class="flex items-center gap-2">
          <CircleHelp class="h-5 w-5" />
          <button type="button" :disabled="loading" @click="emit('close')"><X class="h-5 w-5" /></button>
        </div>
      </div>

      <div class="space-y-5 bg-[#eef6ff] px-5 py-7">
        <div class="space-y-3">
          <div class="text-center text-gray-700">Tổng cọc: <b>{{ amountLabel }}</b> đ</div>
          <label class="flex items-center justify-center gap-4">Số tiền
            <input v-model.number="amount" type="number" min="0.01" :max="Math.max(0, totalAmount - 0.01)" class="h-8 w-44 rounded border border-slate-300 bg-[#ffffb5] px-2 text-right outline-none focus:border-sky-500" />
          </label>
        </div>
        <label class="flex items-center justify-center gap-4">Folio
          <select v-model="targetFolio" class="h-8 w-36 rounded border border-slate-300 bg-[#ffffb5] px-2 outline-none">
            <option v-for="item in availableFolios" :key="item" :value="String(item)">{{ item }}</option>
          </select>
        </label>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 bg-white px-4 py-3">
        <button type="button" :disabled="loading" @click="emit('close')" class="flex items-center gap-1 rounded bg-[#0788f5] px-4 py-2 font-semibold text-white"><X class="h-3.5 w-3.5" /> Đóng</button>
        <button type="button" :disabled="loading || splitAmounts.length !== 2" @click="submit" class="flex items-center gap-1 rounded bg-[#0788f5] px-4 py-2 font-semibold text-white disabled:opacity-40"><Scissors class="h-3.5 w-3.5" />{{ loading ? 'Đang tách...' : 'Tách' }}</button>
      </div>
    </div>
  </div>
</template>
