<script setup>
import { ref, computed } from 'vue'

const searchQuery = ref('')

const industries = ref([
  { id: 1, name: 'NÔNG NGHIỆP, LÂM NGHIỆP VÀ THUỶ SẢN' },
  { id: 2, name: 'KHAI KHOÁNG' },
  { id: 3, name: 'CÔNG NGHIỆP CHẾ BIẾN, CHẾ TẠO' },
  { id: 4, name: 'SẢN XUẤT VÀ PHÂN PHỐI ĐIỆN, KHÍ ĐỐT, NƯỚC NÓNG, HƠI NƯỚC VÀ ĐIỀU HOÀ KHÔNG KHÍ' },
  { id: 5, name: 'CUNG CẤP NƯỚC; HOẠT ĐỘNG QUẢN LÝ VÀ XỬ LÝ RÁC THẢI, NƯỚC THẢI' },
  { id: 6, name: 'XÂY DỰNG' },
  { id: 7, name: 'BÁN BUÔN VÀ BÁN LẺ; SỬA CHỮA Ô TÔ, MÔ TÔ, XE MÁY VÀ XE CÓ ĐỘNG CƠ KHÁC' },
  { id: 8, name: 'VẬN TẢI KHO BÃI' },
  { id: 9, name: 'DỊCH VỤ LƯU TRÚ VÀ ĂN UỐNG' }
])

const filteredIndustries = computed(() => {
  if (!searchQuery.value) return industries.value
  return industries.value.filter(i => i.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
})
</script>

<template>
  <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative w-full">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/50">
      <span class="text-sm font-bold text-slate-700">Ngành Nghề</span>
      <div class="relative w-64">
        <input 
          type="text" 
          v-model="searchQuery" 
          class="w-full pl-3 pr-8 py-1.5 border border-slate-200 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-sky-200"
        />
        <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
      </div>
    </div>

    <!-- List -->
    <div class="flex-1 overflow-auto flex flex-col">
      <div v-for="item in filteredIndustries" :key="item.id" class="px-6 py-4 border-b border-slate-100 hover:bg-slate-50 flex items-center gap-3 transition cursor-pointer">
        <span class="text-slate-400 font-mono">+</span>
        <span class="text-sm font-medium text-slate-700">{{ item.name }}</span>
      </div>
    </div>

    <!-- Pagination -->
    <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-center bg-white">
      <div class="flex items-center gap-1">
        <button class="w-8 h-8 flex items-center justify-center rounded border border-slate-200 text-slate-400 hover:bg-slate-50 transition" disabled>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="w-8 h-8 flex items-center justify-center rounded border border-[#78C5E7] text-[#78C5E7] font-medium bg-sky-50">1</button>
        <button class="w-8 h-8 flex items-center justify-center rounded border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>
  </div>
</template>
