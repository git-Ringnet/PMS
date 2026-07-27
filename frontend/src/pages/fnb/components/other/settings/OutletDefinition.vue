<script setup>
import { ref } from 'vue'
import StoreInfoTab from './tabs/StoreInfoTab.vue'
import OutletTab from './tabs/OutletTab.vue'
import ParameterTab from './tabs/ParameterTab.vue'
import PaymentMethodTab from './tabs/PaymentMethodTab.vue'
import InvoiceConfigTab from './tabs/InvoiceConfigTab.vue'
import VatConfigTab from './tabs/VatConfigTab.vue'
import GateConfigTab from './tabs/GateConfigTab.vue'
import { useUiStore } from '@/stores/ui-store'

defineEmits(['back'])

const uiStore = useUiStore()

const tabs = [
  'Thông tin điểm bán hàng',
  'Outlet',
  'Thông số',
  'Hình thức thanh toán',
  'Cấu hình số ký hiệu',
  'VAT Config',
  'Gate Config'
]

const activeSubTab = ref('Thông tin điểm bán hàng')

const handleSave = () => {
  uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')
}

const handleConnect = () => {
  uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-y-auto">
    <!-- Header -->
    <div class="flex items-center gap-2 mb-4 shrink-0">
      <button @click="$emit('back')" class="p-1.5 rounded-full hover:bg-slate-200 text-slate-600 transition active:scale-95" title="Quay lại">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-base font-bold text-slate-800">Định nghĩa điểm bán hàng</h1>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 mb-5 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-400 shrink-0">
      <button
        v-for="tab in tabs"
        :key="tab"
        @click="activeSubTab = tab"
        class="pb-2 border-b-2 transition-all duration-200 relative"
        :class="activeSubTab === tab ? 'border-sky-500 text-sky-600' : 'border-transparent hover:text-slate-600'"
      >
        {{ tab }}
        <span v-if="activeSubTab === tab" class="absolute bottom-0 left-0 right-0 h-[2px] bg-sky-500 rounded-full"></span>
      </button>
    </div>

    <!-- Actions toolbar (Only for StoreInfoTab based on original design) -->
    <div v-if="activeSubTab === 'Thông tin điểm bán hàng'" class="flex items-center gap-3 mb-6 shrink-0">
      <button @click="handleSave" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm hover:shadow active:scale-[0.98] transition">
        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
        </svg>
        Lưu
      </button>
      <button @click="handleConnect" class="flex items-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 px-4 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition">
        <svg class="w-4.5 h-4.5 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
        </svg>
        Kết nối ứng dụng order
      </button>
    </div>

    <!-- Tab Content -->
    <StoreInfoTab v-if="activeSubTab === 'Thông tin điểm bán hàng'" />
    <OutletTab v-else-if="activeSubTab === 'Outlet'" />
    <ParameterTab v-else-if="activeSubTab === 'Thông số'" />
    <PaymentMethodTab v-else-if="activeSubTab === 'Hình thức thanh toán'" />
    <InvoiceConfigTab v-else-if="activeSubTab === 'Cấu hình số ký hiệu'" />
    <VatConfigTab v-else-if="activeSubTab === 'VAT Config'" />
    <GateConfigTab v-else-if="activeSubTab === 'Gate Config'" />
    <div v-else class="flex-1 flex items-center justify-center text-slate-400">
      Chưa hỗ trợ tab này
    </div>
  </div>
</template>

