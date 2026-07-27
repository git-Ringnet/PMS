<script setup>
defineProps({
  icon: {
    type: String,
    default: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4'
  },
  title: {
    type: String,
    default: 'Chưa có dữ liệu'
  },
  subtitle: {
    type: String,
    default: 'Hãy điều chỉnh bộ lọc và nhấn Xem để tải dữ liệu.'
  },
  actionText: {
    type: String,
    default: ''
  }
})
</script>

<template>
  <div class="flex flex-col items-center justify-center p-12 text-center animate-[fadeIn_0.3s_ease-out] w-full h-full min-h-[300px]">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4 relative z-10 shadow-inner">
      <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="icon" />
      </svg>
    </div>
    <h3 class="text-base font-bold text-slate-700 mb-1">{{ title }}</h3>
    <p class="text-sm text-slate-500 max-w-sm mx-auto">{{ subtitle }}</p>
    <button v-if="actionText" @click="$emit('action')" class="mt-4 px-4 py-2 bg-sky-50 text-sky-600 font-medium text-sm rounded-md hover:bg-sky-100 transition-colors">
      {{ actionText }}
    </button>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
