<script setup>
import { ref, watch } from 'vue'
import RoomClassTab from './room/RoomClassTab.vue'
import RoomFormTab from './room/RoomFormTab.vue'
import StandardRateTab from './room/StandardRateTab.vue'
import RoomTab from './room/RoomTab.vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Tên Loại Phòng'
  }
})

const emit = defineEmits(['update:activeTab'])

const activeRoomTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeRoomTab.value = newVal
  }
})

watch(activeRoomTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const roomTabs = [
  'Tên Loại Phòng',
  'Dạng Phòng',
  'Giá Phòng Chuẩn',
  'Phòng'
]

const isTabActive = (tabName) => {
  if (!activeRoomTab.value) return false
  const cur = String(activeRoomTab.value).trim().toUpperCase()
  const target = String(tabName).trim().toUpperCase()
  return cur === target
}
</script>

<template>
  <div class="flex-1 flex flex-col gap-2.5 min-h-0 font-roboto">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in roomTabs" :key="tab" @click="activeRoomTab = tab"
          class="px-2.5 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-1.5 transition-colors font-roboto"
          :class="isTabActive(tab) ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-hidden min-h-0 relative flex flex-col">
      <!-- Render component based on selected tab -->
      <RoomClassTab v-if="isTabActive('Tên Loại Phòng')" />
      <RoomFormTab v-else-if="isTabActive('Dạng Phòng')" />
      <StandardRateTab v-else-if="isTabActive('Giá Phòng Chuẩn')" />
      <RoomTab v-else-if="isTabActive('Phòng')" />
    </div>
  </div>
</template>
