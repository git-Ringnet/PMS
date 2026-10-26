<script setup>
import { ref, watch } from 'vue'
import HotelInfoTab from './hotel/HotelInfoTab.vue'
import HotelServiceTab from './hotel/HotelServiceTab.vue'
import WorkShiftTab from './hotel/WorkShiftTab.vue'
import HotelConfigTab from './hotel/HotelConfigTab.vue'
import BranchTab from './hotel/BranchTab.vue'
import TemplateTab from './hotel/TemplateTab.vue'
import ServiceDepartmentTab from './hotel/ServiceDepartmentTab.vue'
// import SynthesisReportTab from './hotel/SynthesisReportTab.vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Thông Tin Khách Sạn'
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
  'Thông Tin Khách Sạn',
  'Dịch Vụ Khách Sạn',
  'Ca Làm Việc',
  'Cấu Hình',
  'Chi Nhánh',
  'Mẫu In',
  'Bộ Phận Dịch Vụ'
]

const isTabActive = (tabName) => {
  if (!activeHotelTab.value) return false
  const cur = String(activeHotelTab.value).trim().toUpperCase()
  const target = String(tabName).trim().toUpperCase()
  return cur === target
}

const selectTab = (tab) => {
  activeHotelTab.value = tab
}
</script>

<template>
  <div class="flex flex-col gap-2.5 flex-1 min-h-0 overflow-hidden font-roboto">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in hotelTabs" :key="tab" @click="selectTab(tab)"
          class="px-2.5 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-1.5 transition-colors font-roboto"
          :class="isTabActive(tab) ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-y-auto min-h-0 relative">

      <!-- Render component based on selected tab -->
      <HotelInfoTab v-if="isTabActive('Thông Tin Khách Sạn')" />
      <HotelServiceTab v-else-if="isTabActive('Dịch Vụ Khách Sạn')" />
      <WorkShiftTab v-else-if="isTabActive('Ca Làm Việc')" />
      <HotelConfigTab v-else-if="isTabActive('Cấu Hình')" />
      <BranchTab v-else-if="isTabActive('Chi Nhánh')" />
      <TemplateTab v-else-if="isTabActive('Mẫu In') || String(activeHotelTab).toUpperCase() === 'MẪU'" />
      <ServiceDepartmentTab v-else-if="isTabActive('Bộ Phận Dịch Vụ')" />
      
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
