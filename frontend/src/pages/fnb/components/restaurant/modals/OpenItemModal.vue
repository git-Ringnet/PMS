<script setup>
import { computed } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  products: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'add'])

const getImageUrl = (path) => {
  if (!path) return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80'
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  let finalPath = cleanPath
  if (!cleanPath.startsWith('storage/')) finalPath = 'storage/' + cleanPath
  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`
}

</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="px-4 py-3 bg-[#f8f9fa] border-b border-slate-200 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-700 text-base uppercase">OPEN ITEM (MÓN MỞ)</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="p-4 flex flex-col flex-1 overflow-auto bg-[#fafafa]">
        <!-- Products Grid -->
        <div v-if="products.length > 0" class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-3">
          <div v-for="prod in products" :key="'open-prod-'+prod.id" @click="emit('add', prod)"
               class="border border-slate-200 rounded-lg overflow-hidden bg-white cursor-pointer hover:border-sky-400 hover:shadow-sm transition-all flex flex-col p-2">
            <div class="w-full h-20 bg-slate-100 rounded mb-2 overflow-hidden flex items-center justify-center">
              <img v-if="prod.image" :src="getImageUrl(prod.image)" class="w-full h-full object-cover" />
              <span v-else class="text-xs text-slate-400 font-bold">{{ prod.code }}</span>
            </div>
            <span class="text-xs font-bold text-slate-800 line-clamp-2 leading-tight">{{ prod.name }}</span>
            <span class="text-xs text-sky-600 font-semibold mt-auto pt-1">{{ prod.price?.toLocaleString() || 0 }}</span>
          </div>
        </div>
        
        <!-- Empty State -->
        <div v-else class="flex-1 flex flex-col items-center justify-center text-slate-400 py-12">
          <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-3">
            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
          </div>
          <span class="text-sm font-medium">Không có món ăn dạng Open Item nào.</span>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 py-3 bg-[#f8f9fa] border-t border-slate-200 flex justify-end shrink-0">
        <button @click="emit('close')" class="px-6 py-2 bg-sky-400 text-white hover:bg-sky-500 rounded-lg text-sm font-medium transition-colors shadow-sm">
          Đóng
        </button>
      </div>
    </div>
  </div>
</template>
