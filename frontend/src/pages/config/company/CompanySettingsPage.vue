<script setup>
import { ref } from 'vue'
import CompanyTab from './CompanyTab.vue'
import MarketTab from './MarketTab.vue'
import CustomerSourceTab from './CustomerSourceTab.vue'
import BranchTab from './BranchTab.vue'
import BookerTab from './BookerTab.vue'

const activeTab = ref('CÔNG TY')

const tabs = [
  'CÔNG TY',
  'THỊ TRƯỜNG',
  'NGUỒN KHÁCH',
  'CHI NHÁNH',
  'Người đặt phòng'
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
      <CompanyTab v-if="activeTab === 'CÔNG TY'" />
      <MarketTab v-else-if="activeTab === 'THỊ TRƯỜNG'" />
      <CustomerSourceTab v-else-if="activeTab === 'NGUỒN KHÁCH'" />
      <BranchTab v-else-if="activeTab === 'CHI NHÁNH'" />
      <BookerTab v-else-if="activeTab === 'Người đặt phòng'" />
    </div>
  </div>
</template>
