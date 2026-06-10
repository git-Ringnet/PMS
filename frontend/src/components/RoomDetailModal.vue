<script setup>
import { computed } from 'vue'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import { useRoomStore } from '@/stores/room-store'

const props = defineProps({
  room: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['close'])

const uiStore = useUiStore()
const roomStore = useRoomStore()

const statusConfig = computed(() => {
  const map = {
    [ROOM_STATUSES.AVAILABLE]: { label: 'Trống', color: 'bg-sky-100 text-sky-700', dot: 'bg-sky-400' },
    [ROOM_STATUSES.OCCUPIED]: { label: 'Có khách', color: 'bg-blue-100 text-blue-700', dot: 'bg-blue-600' },
    [ROOM_STATUSES.DIRTY]: { label: 'Phòng bẩn', color: 'bg-orange-100 text-orange-700', dot: 'bg-orange-500' },
    [ROOM_STATUSES.MAINTENANCE]: { label: 'Bảo trì', color: 'bg-red-100 text-red-700', dot: 'bg-red-500' },
    [ROOM_STATUSES.RESERVED]: { label: 'Đã đặt', color: 'bg-green-100 text-green-700', dot: 'bg-green-500' },
    [ROOM_STATUSES.CHECKOUT]: { label: 'Check-out', color: 'bg-amber-100 text-amber-700', dot: 'bg-amber-500' },
  }
  return map[props.room.status] || { label: 'N/A', color: 'bg-gray-100 text-gray-700', dot: 'bg-gray-400' }
})

const allStatuses = [
  { key: ROOM_STATUSES.AVAILABLE, label: 'Phòng trống' },
  { key: ROOM_STATUSES.OCCUPIED, label: 'Có khách' },
  { key: ROOM_STATUSES.DIRTY, label: 'Phòng bẩn' },
  { key: ROOM_STATUSES.MAINTENANCE, label: 'Bảo trì' },
  { key: ROOM_STATUSES.RESERVED, label: 'Đã đặt' },
  { key: ROOM_STATUSES.CHECKOUT, label: 'Check-out' }
]

async function changeStatus(event) {
  const newStatus = event.target.value
  if (newStatus === props.room.status) return

  const statusLabel = allStatuses.find(s => s.key === newStatus)?.label || newStatus
  
  // Custom Promise-based confirmation popup trigger
  const confirmed = await uiStore.confirm({
    title: 'Đổi trạng thái phòng',
    message: `Bạn có chắc chắn muốn chuyển phòng ${props.room.room_number} sang trạng thái "${statusLabel}" không?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await roomStore.updateRoomStatus(props.room.id, newStatus)
      uiStore.showToast(`Đã đổi trạng thái phòng ${props.room.room_number} sang "${statusLabel}" thành công!`, 'success')
      emit('close')
    } catch (err) {
      uiStore.showToast('Không thể cập nhật trạng thái phòng. Vui lòng thử lại!', 'error')
    }
  } else {
    // Reset selection back
    event.target.value = props.room.status
    uiStore.showToast('Đã hủy thao tác đổi trạng thái.', 'info')
  }
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) {
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm"
      @click="handleOverlayClick"
    >
      <div
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-[modalIn_0.3s_ease-out] border border-slate-100"
      >
        <!-- Header -->
        <div class="relative bg-gradient-to-r from-blue-600 to-sky-600 text-white p-5">
          <button
            @click="emit('close')"
            class="absolute top-3 right-3 p-1.5 rounded-lg bg-white/20 hover:bg-white/30 transition-colors cursor-pointer border-none"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl bg-white/20 flex items-center justify-center">
              <span class="text-2xl font-bold">{{ room.room_number }}</span>
            </div>
            <div>
              <h2 class="text-lg font-bold m-0">Phòng {{ room.room_number }}</h2>
              <p class="text-sm text-white/80 m-0">Tầng {{ room.floor }} • {{ room.room_type }}</p>
            </div>
          </div>
        </div>

        <!-- Status Badge + Selector -->
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <span
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold shadow-sm"
            :class="statusConfig.color"
          >
            <span class="w-2 h-2 rounded-full" :class="statusConfig.dot"></span>
            {{ statusConfig.label }}
          </span>

          <div class="flex items-center gap-1.5">
            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Trạng thái:</label>
            <select
              :value="room.status"
              @change="changeStatus"
              class="text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer shadow-sm"
            >
              <option v-for="st in allStatuses" :key="st.key" :value="st.key">
                {{ st.label }}
              </option>
            </select>
          </div>
        </div>

        <!-- Details -->
        <div class="p-5 space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50 border border-slate-100 rounded-lg p-3">
              <div class="text-[9px] text-slate-400 uppercase tracking-wider font-bold">Loại phòng</div>
              <div class="text-sm font-bold text-slate-800 mt-1">{{ room.room_type_name || room.room_type }}</div>
            </div>
            <div class="bg-slate-50 border border-slate-100 rounded-lg p-3">
              <div class="text-[9px] text-slate-400 uppercase tracking-wider font-bold">Số khách tối đa</div>
              <div class="text-sm font-bold text-slate-800 mt-1">{{ room.max_guests }} người</div>
            </div>
          </div>

          <div v-if="room.guest_name" class="bg-blue-50/50 border border-blue-100 rounded-lg p-3">
            <div class="text-[9px] text-blue-500 uppercase tracking-wider font-bold">Khách lưu trú</div>
            <div class="text-sm font-bold text-blue-900 mt-1">{{ room.guest_name }}</div>
            <div v-if="room.check_in" class="text-xs text-blue-600 mt-1 font-semibold">
              {{ room.check_in }} → {{ room.check_out }}
            </div>
          </div>

          <div v-if="room.notes" class="bg-amber-50/50 border border-amber-100 rounded-lg p-3">
            <div class="text-[9px] text-amber-500 uppercase tracking-wider font-bold">Ghi chú</div>
            <div class="text-sm text-slate-700 mt-1">{{ room.notes }}</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="px-5 pb-5 flex gap-2">
          <button class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors cursor-pointer border-none shadow-md">
            Chi tiết phòng
          </button>
          <button
            @click="emit('close')"
            class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition-colors cursor-pointer border-none"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes modalIn {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
