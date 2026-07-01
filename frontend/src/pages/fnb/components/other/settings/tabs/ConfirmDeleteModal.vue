<script setup>
defineProps({
  isOpen: Boolean,
  title: {
    type: String,
    default: 'Xác nhận xóa'
  },
  message: {
    type: String,
    default: 'Bạn có chắc chắn muốn xóa dữ liệu này không?'
  }
})

defineEmits(['close', 'confirm'])
</script>

<template>
  <Transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-5">
          <div class="flex items-center gap-3 mb-3 text-red-600">
            <div class="bg-red-100 p-2 rounded-full">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h3 class="text-lg font-bold">{{ title }}</h3>
          </div>
          <p class="text-sm text-slate-600 ml-11">{{ message }}</p>
        </div>
        <div class="bg-slate-50 px-5 py-3 flex justify-end gap-3 border-t border-slate-100">
          <button @click="$emit('close')" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
            Hủy
          </button>
          <button @click="$emit('confirm')" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition shadow-sm">
            Xóa
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
