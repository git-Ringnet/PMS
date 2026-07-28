<script setup>
import { X } from '@lucide/vue'
import PostBillHousekeepingTab from '@/pages/housekeeping/components/PostBillHousekeepingTab.vue'

const props = defineProps({
  show: Boolean,
  bookingInfo: {
    type: String,
    default: ''
  },
  roomId: {
    type: [String, Number],
    default: ''
  }
})

const emit = defineEmits(['close', 'submit'])

const handleClose = () => {
  emit('close')
}

const handleSuccess = (data) => {
  emit('submit', data)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 md:p-4 animate-fadeIn">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-6xl h-[90vh] overflow-hidden border border-slate-300 flex flex-col text-xs">
      <!-- Header -->
      <div class="bg-[#1a6b8a] text-white px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-sm">
        <div class="flex items-center gap-2">
          <span class="text-sm font-bold">Thêm dịch vụ buồng phòng</span>
          <span v-if="bookingInfo" class="bg-white/20 px-2 py-0.5 rounded text-[11px] font-normal tracking-wide">{{ bookingInfo }}</span>
        </div>
        <div class="flex items-center gap-2">
          <button @click="handleClose" class="hover:bg-white/20 p-1 rounded transition-colors text-white cursor-pointer" title="Đóng">
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Full Housekeeping Post Bill Form Component -->
      <div class="flex-1 overflow-hidden">
        <PostBillHousekeepingTab
          :initialRoomId="roomId"
          :isModal="true"
          :department="'HK'"
          @close="handleClose"
          @success="handleSuccess"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
</style>
