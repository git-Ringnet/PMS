<script setup>
import { computed } from 'vue'
import { ROOM_STATUSES } from '@/services/room-service'

const props = defineProps({
  room: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['click'])

const statusColor = computed(() => {
  const map = {
    [ROOM_STATUSES.AVAILABLE]: 'bg-sky-200 hover:bg-sky-300 border-sky-300',
    [ROOM_STATUSES.OCCUPIED]: 'bg-sky-300 hover:bg-sky-400 border-sky-400',
    [ROOM_STATUSES.DIRTY]: 'bg-orange-200 hover:bg-orange-300 border-orange-300',
    [ROOM_STATUSES.MAINTENANCE]: 'bg-red-200 hover:bg-red-300 border-red-300',
    [ROOM_STATUSES.RESERVED]: 'bg-green-200 hover:bg-green-300 border-green-300',
    [ROOM_STATUSES.CHECKOUT]: 'bg-amber-200 hover:bg-amber-300 border-amber-300',
  }
  return map[props.room.status] || 'bg-surface-100 border-surface-200'
})

const statusDotColor = computed(() => {
  const map = {
    [ROOM_STATUSES.OCCUPIED]: 'bg-blue-700',
    [ROOM_STATUSES.DIRTY]: 'bg-orange-500',
    [ROOM_STATUSES.MAINTENANCE]: 'bg-red-600',
    [ROOM_STATUSES.RESERVED]: 'bg-green-600',
    [ROOM_STATUSES.CHECKOUT]: 'bg-amber-600',
  }
  return map[props.room.status] || ''
})

const statusLabel = computed(() => {
  const map = {
    [ROOM_STATUSES.AVAILABLE]: 'Trống',
    [ROOM_STATUSES.OCCUPIED]: 'Có khách',
    [ROOM_STATUSES.DIRTY]: 'Bẩn',
    [ROOM_STATUSES.MAINTENANCE]: 'Bảo trì',
    [ROOM_STATUSES.RESERVED]: 'Đã đặt',
    [ROOM_STATUSES.CHECKOUT]: 'Check-out',
  }
  return map[props.room.status] || ''
})
</script>

<template>
  <div
    class="room-card relative rounded-lg p-2.5 cursor-pointer border transition-all duration-200 min-h-[70px] flex flex-col justify-between"
    :class="statusColor"
    @click="emit('click', room)"
  >
    <!-- Status Dot -->
    <div v-if="statusDotColor" class="absolute top-1.5 right-1.5">
      <span class="w-2.5 h-2.5 rounded-full block shadow-sm" :class="statusDotColor"></span>
    </div>

    <!-- Room Number -->
    <div class="font-bold text-sm text-surface-800">
      {{ room.room_number }}
    </div>

    <!-- Room Type & Guests -->
    <div class="mt-auto">
      <div class="text-[10px] text-surface-600 font-medium leading-snug">
        {{ room.room_type }} - SL khách: {{ room.max_guests }}
      </div>
    </div>
  </div>
</template>
