<script setup>
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const printers = ref([])
const activeGroup = ref('Nha Hang')

</script>

<template>
  <div class="flex-1 flex gap-6 overflow-hidden min-h-0 w-full">
    <!-- Left sidebar -->
    <div class="w-56 bg-white rounded-xl border border-slate-200/80 p-0 shadow-sm flex flex-col shrink-0 overflow-hidden">
      <div class="bg-slate-100 px-4 py-3 border-b border-slate-200">
        <span class="text-sm font-semibold text-slate-700">Máy In Chuyến Bán</span>
      </div>
      <div class="flex-1 overflow-y-auto flex flex-col p-1">
        <button
          @click="activeGroup = 'Nha Hang'"
          class="w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold transition relative overflow-hidden"
          :class="activeGroup === 'Nha Hang' ? 'bg-[#bfeaf9] text-white font-bold' : 'text-slate-600 hover:bg-slate-50'"
        >
          <span :class="activeGroup === 'Nha Hang' ? 'text-white' : 'text-slate-600'">Nhà Hàng</span>
        </button>
      </div>
    </div>

    <!-- Right content area -->
    <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative">
      <!-- Toolbar -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center shrink-0">
        <button @click="uiStore.showToast('Chức năng đang phát triển', 'info')" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          Thêm
        </button>
      </div>

      <!-- Table view -->
      <div class="flex-1 overflow-auto flex flex-col">
        <table class="w-full text-sm border-collapse whitespace-nowrap text-left text-slate-600">
          <thead class="sticky top-0 z-10 bg-slate-50">
            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-700">
              <th class="px-4 py-3">Mã</th>
              <th class="px-4 py-3">Outlet</th>
              <th class="px-4 py-3">Tên máy In</th>
              <th class="px-4 py-3">Loại máy in</th>
              <th class="px-4 py-3">Printer Driver Name</th>
              <th class="px-4 py-3 text-center">Số lần in</th>
              <th class="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>
        </table>
        
        <!-- Empty State -->
        <div v-if="printers.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-slate-300">
          <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide">Trống</span>
        </div>
      </div>
    </div>
  </div>
</template>
