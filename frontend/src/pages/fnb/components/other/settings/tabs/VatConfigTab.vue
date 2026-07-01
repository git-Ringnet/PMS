<script setup>
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const vatConfigs = ref([])

</script>

<template>
  <div class="flex-1 flex flex-col bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm overflow-x-auto">
    <!-- Actions -->
    <div class="flex items-center gap-4 mb-4">
      <button @click="uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Thêm
      </button>
    </div>

    <!-- Table -->
    <div class="border border-slate-200 rounded-lg overflow-x-auto flex-1 flex flex-col">
      <table class="w-full text-left text-sm text-slate-600 min-w-max">
        <thead class="bg-slate-50 text-slate-700 font-medium border-b border-slate-200">
          <tr>
            <th class="py-3 px-4">Supplier</th>
            <th class="py-3 px-4">Url Api</th>
            <th class="py-3 px-4">User Name</th>
            <th class="py-3 px-4">Password</th>
            <th class="py-3 px-4">ACUser</th>
            <th class="py-3 px-4">ACPassword</th>
            <th class="py-3 px-4">AppId</th>
            <th class="py-3 px-4">Token</th>
            <th class="py-3 px-4">Config Decimal</th>
            <th class="py-3 px-4 text-center w-20">Delete</th>
          </tr>
        </thead>
      </table>
      
      <!-- Empty State -->
      <div v-if="vatConfigs.length === 0" class="flex-1 flex flex-col items-center justify-center p-12 text-slate-300">
        <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
        <span class="text-sm font-medium">Trống</span>
      </div>
    </div>
  </div>
</template>
