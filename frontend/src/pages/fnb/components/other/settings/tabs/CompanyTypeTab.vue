<script setup>
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Empty mock data to match Image 2
const types = ref([])

</script>

<template>
  <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative w-full">
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
            <th class="px-6 py-3 w-32 border-r border-slate-100">
              <div class="flex items-center justify-between">
                ID
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="px-6 py-3 border-r border-slate-100 w-1/4">
              <div class="flex items-center justify-between">
                Tên loại
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="px-6 py-3 border-r border-slate-100 w-1/4">
              <div class="flex items-center justify-between">
                Tiền tố
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="px-6 py-3 border-r border-slate-100 w-32">Kích hoạt</th>
            <th class="px-6 py-3 w-20 text-center">Xoá</th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-for="item in types" :key="item.id" class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
            <td class="px-6 py-3 border-r border-slate-100 text-slate-500">{{ item.id }}</td>
            <td class="px-6 py-3 border-r border-slate-100 font-medium text-slate-700">{{ item.name }}</td>
            <td class="px-6 py-3 border-r border-slate-100 text-slate-600">{{ item.prefix }}</td>
            <td class="px-6 py-3 border-r border-slate-100 text-slate-600 text-center">
              <button @click="item.active = !item.active" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="item.active ? 'bg-sky-500' : 'bg-slate-300'">
                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="item.active ? 'translate-x-[18px]' : 'translate-x-1'"></span>
              </button>
            </td>
            <td class="px-6 py-3 text-center">
              <button @click="uiStore.showToast('Chức năng xoá đang phát triển', 'warning')" class="text-white bg-[#78C5E7] hover:bg-[#60b3d6] p-1.5 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Empty State -->
      <div v-if="types.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-slate-300">
        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
          <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <span class="text-xs font-semibold uppercase tracking-wide">Trống</span>
      </div>
    </div>
  </div>
</template>
