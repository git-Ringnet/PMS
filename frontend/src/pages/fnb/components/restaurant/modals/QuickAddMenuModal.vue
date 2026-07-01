<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'add'])

</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="px-4 py-3 bg-[#f8f9fa] border-b border-slate-200 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-700 text-base uppercase">THÊM THỰC ĐƠN NHANH</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="p-4 flex flex-col flex-1 overflow-hidden bg-white">
        <!-- Toolbar -->
        <div class="flex items-center gap-3 mb-4 shrink-0">
          <button class="bg-sky-500 hover:bg-sky-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium flex items-center gap-1.5 transition-colors" @click="emit('add')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Thêm
          </button>
          
          <div class="relative w-72">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input type="text" class="block w-full pl-10 pr-3 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500" placeholder="Tìm kiếm" />
          </div>
        </div>

        <!-- Table Area -->
        <div class="flex-1 border border-slate-200 rounded-lg flex flex-col overflow-hidden bg-[#fafafa]">
          <table class="w-full text-sm text-left">
            <thead class="bg-slate-100/50 border-b border-slate-200 text-slate-700">
              <tr>
                <th class="px-4 py-2 font-medium border-r border-slate-200 w-12 text-center"></th>
                <th class="px-4 py-2 font-medium border-r border-slate-200">Mã</th>
                <th class="px-4 py-2 font-medium border-r border-slate-200">Tên</th>
                <th class="px-4 py-2 font-medium border-r border-slate-200 text-right">Đơn giá</th>
                <th class="px-4 py-2 font-medium">Nhóm dịch vụ</th>
              </tr>
            </thead>
          </table>
          
          <!-- Empty State -->
          <div class="flex-1 flex flex-col items-center justify-center text-slate-400">
            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-3">
              <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <span class="text-sm font-medium">Trống</span>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 py-3 bg-[#f8f9fa] border-t border-slate-200 flex justify-end gap-3 shrink-0">
        <button @click="emit('close')" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium transition-colors">
          Hủy
        </button>
        <button class="px-4 py-2 bg-slate-300 text-slate-500 rounded-lg text-sm font-medium cursor-not-allowed">
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
