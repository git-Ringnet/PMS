<script setup>
import { ref, watch } from 'vue'
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

watch(() => props.show, visible => { if (visible) reason.value = '' })
const submit = () => {
  if (!props.loading && reason.value.trim()) emit('submit', reason.value.trim())
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between bg-[#0788f5] px-4 py-2 text-white">
        <span class="font-bold">Xóa dịch vụ</span>
        <button :disabled="loading" class="rounded transition hover:bg-white/20 active:scale-90" @click="emit('close')"><X class="h-5 w-5" /></button>
      </header>
      <div class="space-y-3 p-4">
        <p class="text-gray-700">Xóa {{ count }} dịch vụ đã chọn. Hệ thống sẽ tạo dòng âm đối trừ.</p>
        <template v-if="canDelete">
          <label class="block font-medium text-gray-700">Lý do xóa <span class="text-red-500">*</span></label>
          <textarea v-model="reason" :disabled="loading" rows="3" maxlength="255" placeholder="Ví dụ: POST NHẦM" class="w-full resize-none rounded border border-gray-300 px-2.5 py-2 outline-none focus:border-sky-500" />
        </template>
        <p v-else class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">Tiền phòng không được xóa; chỉ được điều chỉnh giá.</p>
      </div>
      <footer class="flex justify-end gap-2 border-t border-gray-300 bg-gray-50 p-3">
        <button :disabled="loading" class="rounded bg-gray-400 px-4 py-1.5 font-bold text-white transition hover:bg-gray-500 active:scale-95 disabled:opacity-40" @click="emit('close')">Đóng</button>
        <button v-if="canAdjust" :disabled="loading" class="rounded bg-amber-500 px-4 py-1.5 font-bold text-white transition hover:bg-amber-600 active:scale-95 disabled:opacity-40" @click="emit('adjust')"><PencilLine class="mr-1 inline h-4 w-4" />Điều chỉnh giá</button>
        <button v-if="canDelete" :disabled="loading || !reason.trim()" class="rounded bg-red-500 px-4 py-1.5 font-bold text-white transition hover:bg-red-600 active:scale-95 disabled:opacity-40" @click="submit"><Trash2 class="mr-1 inline h-4 w-4" />{{ loading ? 'Đang xóa...' : 'Xóa dịch vụ' }}</button>
      </footer>
    </div>
  </div>
</template>
