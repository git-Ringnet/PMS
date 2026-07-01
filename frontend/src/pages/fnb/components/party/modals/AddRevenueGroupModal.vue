<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'save'])

const form = ref({
  code: '',
  name: '',
  description: ''
})

const handleSave = () => {
  if (!form.value.name) return
  emit('save', { ...form.value })
  form.value = { code: '', name: '', description: '' }
}

const handleClose = () => {
  emit('close')
  form.value = { code: '', name: '', description: '' }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-lg">Thêm nhóm doanh thu</h3>
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mã nhóm <span class="text-red-500">*</span></label>
          <input v-model="form.code" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập mã nhóm" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tên nhóm <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập tên nhóm" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mô tả</label>
          <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Mô tả nhóm doanh thu"></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
        <button @click="handleClose" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-all">
          Hủy
        </button>
        <button @click="handleSave" :disabled="!form.name || !form.code" class="px-4 py-2 text-sm font-medium text-white bg-sky-500 rounded-lg hover:bg-sky-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
