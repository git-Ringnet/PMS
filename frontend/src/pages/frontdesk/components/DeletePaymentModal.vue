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

watch(() => props.show, visible => {
  if (visible) {
    reason.value = ''
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    window.removeEventListener('keydown', handleKeyDown)
  }
})

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyDown))

const submit = () => {
  const value = reason.value.trim()
  if (!props.loading && value) emit('submit', value)
}

</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-300 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between px-4 py-2 text-white" :style="{ background: 'var(--pms-custom-theme, #006bdb)' }">
        <span class="font-bold">Xóa thanh toán</span>
        <button :disabled="loading" class="rounded transition hover:bg-white/20" @click="close"><X class="h-5 w-5" /></button>
      </header>
      <div class="space-y-3 p-4">
        <label class="block font-semibold text-[#000000D9]">Lý do xóa <span class="text-red-500">*</span></label>
        <textarea v-model="reason" :disabled="loading" required rows="3" maxlength="1000" placeholder="Nhập lý do xóa thanh toán" class="w-full resize-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2.5 py-2 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-amber-500" />
      </div>
      <footer class="flex justify-end border-t border-gray-300 bg-gray-50 p-3">
        <button :disabled="loading || !reason.trim()" class="btn-pms-danger" @click="submit"><Trash2 class="h-4 w-4" />{{ loading ? 'Đang xử lý...' : 'Xóa' }}</button>
      </footer>
    </div>
  </div>
</template>
