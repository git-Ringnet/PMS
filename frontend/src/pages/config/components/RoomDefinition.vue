<script setup>
import { ref, watch } from 'vue'
import RoomClassTab from './room/RoomClassTab.vue'
import RoomFormTab from './room/RoomFormTab.vue'
import StandardRateTab from './room/StandardRateTab.vue'
import RoomTab from './room/RoomTab.vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'TÊN LOẠI PHÒNG'
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
  'TÊN LOẠI PHÒNG',
  'DẠNG PHÒNG',
  'GIÁ PHÒNG CHUẨN',
  'PHÒNG'
]
</script>

<template>
  <div class="flex-1 flex flex-col gap-2.5 min-h-0">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in roomTabs" :key="tab" @click="activeRoomTab = tab"
          class="px-3 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-2 transition-colors"
          :class="activeRoomTab === tab ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9]/70 hover:text-[#000000D9]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-hidden min-h-0 relative flex flex-col">
      <!-- Render component based on selected tab -->
      <RoomClassTab v-if="activeRoomTab === 'TÊN LOẠI PHÒNG'" />
      <RoomFormTab v-else-if="activeRoomTab === 'DẠNG PHÒNG'" />
      <StandardRateTab v-else-if="activeRoomTab === 'GIÁ PHÒNG CHUẨN'" />
      <RoomTab v-else-if="activeRoomTab === 'PHÒNG'" />
    </div>
  </div>
</template>
