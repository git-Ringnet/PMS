<script setup>
import { computed, ref, watch } from 'vue'
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
  }
})

const amountLabel = computed(() => props.totalAmount ? new Intl.NumberFormat('vi-VN').format(props.totalAmount) : '0')

const submit = () => {
  if (!props.selectedCount || props.loading) return
  if (!folio.value || !(Number(amount.value) > 0) || Number(amount.value) >= props.totalAmount) return
  emit('split', {
    folio: Number(folio.value),
    amount: Number(amount.value)
  })
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
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-2">
    <div class="w-full max-w-[445px] overflow-hidden rounded-xl border border-sky-500 bg-white shadow-2xl text-xs">
      <div class="flex items-center justify-between bg-[#0788f5] px-4 py-2 text-white">
        <span class="font-bold">Tách dịch vụ</span>
        <div class="flex items-center gap-2">
          <CircleHelp class="h-5 w-5" />
          <button type="button" @click="emit('close')"><X class="h-5 w-5" /></button>
        </div>
      </div>

      <div class="space-y-5 bg-[#eef6ff] px-5 py-7">
        <div class="space-y-3">
          <div class="text-center text-gray-700">Tổng đã chọn: <b>{{ amountLabel }}</b> đ</div>
          <label class="flex items-center justify-center gap-4">Số tiền
            <input v-model.number="amount" type="number" min="1" :max="Math.max(0, totalAmount - 1)" class="h-8 w-44 rounded border border-slate-300 bg-[#ffffb5] px-2 text-right outline-none focus:border-sky-500" />
          </label>
        </div>

        <label class="flex items-center justify-center gap-4">Folio
          <select v-model="folio" class="h-8 w-36 rounded border border-slate-300 bg-[#ffffb5] px-2 outline-none">
            <option v-for="item in availableFolios" :key="item" :value="String(item)">{{ item }}</option>
          </select>
        </label>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-200 bg-white px-4 py-3">
        <button type="button" @click="emit('close')" class="flex items-center gap-1 rounded bg-[#0788f5] px-4 py-2 font-semibold text-white"><X class="h-3.5 w-3.5" /> Đóng</button>
        <button type="button" @click="submit" :disabled="!selectedCount" class="flex items-center gap-1 rounded bg-[#0788f5] px-4 py-2 font-semibold text-white disabled:opacity-40"><Scissors class="h-3.5 w-3.5" /> Tách</button>
      </div>
    </div>
  </div>
</template>
