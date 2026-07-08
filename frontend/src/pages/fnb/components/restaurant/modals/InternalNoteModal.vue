<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  initialData: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'save'])

const formData = ref({
  internalNote: '',
  internalNoteDiscount: ''
})

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.initialData) {
    formData.value.internalNote = props.initialData.internalNote || ''
    formData.value.internalNoteDiscount = props.initialData.internalNoteDiscount || ''
  }
})

const handleSave = () => {
  emit('save', { ...formData.value })
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-700 text-lg">Thông tin</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="p-6 flex flex-col gap-6 overflow-y-auto">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Ghi chú nội bộ</label>
          <div class="border border-slate-300 rounded-lg overflow-hidden flex flex-col">
            <!-- Fake Rich Text Toolbar -->
            <div class="bg-slate-50 border-b border-slate-300 p-2 flex flex-wrap gap-2 items-center">
              <div class="flex gap-1 border-r border-slate-300 pr-2">
                <button class="p-1 hover:bg-slate-200 rounded text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></button>
                <button class="p-1 hover:bg-slate-200 rounded text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"></path></svg></button>
              </div>
              <div class="flex gap-1 border-r border-slate-300 pr-2 text-xs font-semibold text-slate-700 items-center">
                <span class="px-2 cursor-pointer hover:bg-slate-200 rounded py-1">Font</span>
                <span class="px-2 cursor-pointer hover:bg-slate-200 rounded py-1">Size</span>
                <span class="px-2 cursor-pointer hover:bg-slate-200 rounded py-1">Formats</span>
              </div>
              <div class="flex gap-1">
                <button class="p-1 hover:bg-slate-200 rounded text-slate-800 font-serif font-bold w-7 h-7">B</button>
                <button class="p-1 hover:bg-slate-200 rounded text-slate-800 font-serif italic w-7 h-7">I</button>
                <button class="p-1 hover:bg-slate-200 rounded text-slate-800 font-serif underline w-7 h-7">U</button>
              </div>
            </div>
            <textarea v-model="formData.internalNote" rows="8" class="w-full p-4 focus:outline-none resize-none text-sm"></textarea>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Ghi chú giảm giá</label>
          <textarea v-model="formData.internalNoteDiscount" rows="4" class="w-full border border-slate-300 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-sky-500 resize-none"></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 flex justify-end gap-3 shrink-0 border-t border-slate-100">
        <button @click="emit('close')" class="px-6 py-2 bg-sky-300 text-white hover:bg-sky-400 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          Hủy
        </button>
        <button @click="handleSave" class="px-6 py-2 bg-sky-400 text-white hover:bg-sky-500 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
