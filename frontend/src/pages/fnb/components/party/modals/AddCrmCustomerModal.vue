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
  name: '',
  phone: '',
  email: '',
  address: ''
})

const handleSave = () => {
  if (!form.value.name) return
  emit('save', { ...form.value })
  form.value = { name: '', phone: '', email: '', address: '' }
}

const handleClose = () => {
  emit('close')
  form.value = { name: '', phone: '', email: '', address: '' }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
        <h3 class="font-bold text-slate-800 text-lg">Thêm khách hàng (CRM)</h3>
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tên khách hàng <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập tên khách hàng" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Số điện thoại</label>
          <input v-model="form.phone" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập số điện thoại" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email</label>
          <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập email" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">Địa chỉ</label>
          <input v-model="form.address" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập địa chỉ" />
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/50">
        <button @click="handleClose" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-all">
          Hủy
        </button>
        <button @click="handleSave" :disabled="!form.name" class="px-4 py-2 text-sm font-medium text-white bg-sky-500 rounded-lg hover:bg-sky-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
