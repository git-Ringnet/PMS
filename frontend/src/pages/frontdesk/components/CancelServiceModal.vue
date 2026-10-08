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

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}
watch(() => props.show, visible => {
  if (visible) {
    reason.value = ''
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    window.removeEventListener('keydown', handleKeyDown)
  }
})
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyDown))
const submit = () => {
  if (!props.loading && reason.value.trim()) emit('submit', reason.value.trim())
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between px-4 py-2 text-white" :style="{ background: 'var(--pms-custom-theme, #006bdb)' }">
        <span class="font-bold">Xóa dịch vụ</span>
        <button :disabled="loading" class="rounded transition hover:bg-white/20 active:scale-90" @click="close"><X class="h-5 w-5" /></button>
      </header>
      <div class="space-y-3 p-4">
        <template v-if="canDelete">
          <label class="block font-semibold text-[#000000D9]">Lý do xóa <span class="text-red-500">*</span></label>
          <textarea v-model="reason" :disabled="loading" required rows="3" maxlength="255" placeholder="Ví dụ: POST NHẦM" class="w-full resize-none rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-2.5 py-2 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-amber-500" />
        </template>
        <p v-else class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">Tiền phòng không được xóa; chỉ được điều chỉnh giá.</p>
      </div>
      <footer v-if="canAdjust || canDelete" class="flex justify-end gap-2 border-t border-gray-300 bg-gray-50 p-3">
        <button v-if="canAdjust" :disabled="loading" class="btn-pms-secondary" @click="emit('adjust')"><PencilLine class="h-4 w-4" />Điều chỉnh giá</button>
        <button v-if="canDelete" :disabled="loading || !reason.trim()" class="btn-pms-danger" @click="submit"><Trash2 class="h-4 w-4" />{{ loading ? 'Đang xóa...' : 'Xóa dịch vụ' }}</button>
      </footer>
    </div>
  </div>
</template>
