<script setup>
import { ref, watch } from 'vue'
import HotelInfoTab from './hotel/HotelInfoTab.vue'
import HotelServiceTab from './hotel/HotelServiceTab.vue'
import WorkShiftTab from './hotel/WorkShiftTab.vue'
import HotelConfigTab from './hotel/HotelConfigTab.vue'
import BranchTab from './hotel/BranchTab.vue'
import TemplateTab from './hotel/TemplateTab.vue'
import ServiceDepartmentTab from './hotel/ServiceDepartmentTab.vue'
import SynthesisReportTab from './hotel/SynthesisReportTab.vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'THÔNG TIN KHÁCH SẠN'
  }
})

const emit = defineEmits(['update:activeTab'])

const activeHotelTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeHotelTab.value = newVal
  }
})

watch(activeHotelTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const hotelTabs = [
  'THÔNG TIN KHÁCH SẠN',
  'DỊCH VỤ KHÁCH SẠN',
  'CA LÀM VIỆC',
  'CẤU HÌNH',
  'CHI NHÁNH',
  'MẪU',
  'BỘ PHẬN DỊCH VỤ',
  'THIẾT LẬP BÁO CÁO TỔNG HỢP'
]
</script>

<template>
  <div class="flex flex-col gap-4 h-full overflow-hidden">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in hotelTabs" :key="tab" @click="activeHotelTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeHotelTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-y-auto min-h-[400px] relative" :class="activeHotelTab === 'THÔNG TIN KHÁCH SẠN'
      ? ''
      : 'bg-white rounded-xl shadow-xs border border-slate-200 p-6'">

      <!-- Render component based on selected tab -->
      <HotelInfoTab v-if="activeHotelTab === 'THÔNG TIN KHÁCH SẠN'" />
      <HotelServiceTab v-else-if="activeHotelTab === 'DỊCH VỤ KHÁCH SẠN'" />
      <WorkShiftTab v-else-if="activeHotelTab === 'CA LÀM VIỆC'" />
      <HotelConfigTab v-else-if="activeHotelTab === 'CẤU HÌNH'" />
      <BranchTab v-else-if="activeHotelTab === 'CHI NHÁNH'" />
      <TemplateTab v-else-if="activeHotelTab === 'MẪU'" />
      <ServiceDepartmentTab v-else-if="activeHotelTab === 'BỘ PHẬN DỊCH VỤ'" />
      <SynthesisReportTab v-else-if="activeHotelTab === 'THIẾT LẬP BÁO CÁO TỔNG HỢP'" />
      
      <!-- Fallback block for remaining tabs -->
      <div v-else class="text-center py-12 flex flex-col items-center justify-center gap-3">
        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </div>
        <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">{{ activeHotelTab }}</span>
        <p class="text-sm text-slate-400 max-w-xs leading-relaxed">Chức năng cấu hình chi tiết đang được phát triển
          để đồng bộ hệ thống PMS.</p>
      </div>

    </div>
  </div>
</template>
