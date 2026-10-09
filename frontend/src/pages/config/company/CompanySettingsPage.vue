<script setup>
import { ref } from 'vue'
import CompanyTab from './CompanyTab.vue'
import MarketTab from './MarketTab.vue'
import CustomerSourceTab from './CustomerSourceTab.vue'
import BranchTab from './BranchTab.vue'
import BookerTab from './BookerTab.vue'

const activeTab = ref('Công Ty')

const tabs = [
  'Công Ty',
  'Thị Trường',
  'Nguồn Khách',
  'Chi Nhánh',
  'Người Đặt Phòng'
]
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full">
    <!-- Tab Navigation Bar -->
    <div class="flex items-center border-b border-slate-200 px-4 bg-slate-50/50 shrink-0 overflow-x-auto scrollbar-none">
      <button 
        v-for="tab in tabs" 
        :key="tab"
        @click="activeTab = tab"
        class="py-3 px-4 text-xs font-bold transition-all relative border-b-2 whitespace-nowrap cursor-pointer bg-transparent"
        :class="activeTab === tab 
          ? 'border-sky-500 text-sky-600' 
          : 'border-transparent text-slate-700 hover:text-slate-900 hover:border-slate-300'"
      >
        {{ tab }}
      </button>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-hidden flex flex-col">
      <CompanyTab v-if="activeTab === 'Công Ty'" />
      <MarketTab v-else-if="activeTab === 'Thị Trường'" />
      <CustomerSourceTab v-else-if="activeTab === 'Nguồn Khách'" />
      <BranchTab v-else-if="activeTab === 'Chi Nhánh'" />
      <BookerTab v-else-if="activeTab === 'Người Đặt Phòng'" />
    </div>
  </div>
</template>
