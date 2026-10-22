<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ArrowRightLeft, ChevronDown, HelpCircle, Inbox, X } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  fromLabel: { type: String, default: '' },
  error: { type: String, default: '' },
  payment: { type: Object, default: null },
  destinations: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'transfer'])
const destinationContainerRef = ref(null)
const destinationInputRef = ref(null)
const destinationKey = ref('')
const destinationQuery = ref('')
const showDestinationDropdown = ref(false)
const selectedDestination = computed(() => props.loading ? null : (props.destinations.find(item => item.key === destinationKey.value) || null))
const filteredDestinations = computed(() => {
  const query = destinationQuery.value.trim().toLowerCase()
  return query ? props.destinations.filter(item => item.label.toLowerCase().includes(query)) : props.destinations
})
const filteredDestinationBookings = computed(() => {
  const query = destinationQuery.value.trim().toLowerCase()
  return props.destinations.filter(item => item.kind === 'booking').filter(booking => !query || (
    booking.label.toLowerCase().includes(query) || props.destinations.some(room => room.kind === 'room' && room.bookingId === booking.bookingId && room.label.toLowerCase().includes(query))
  ))
})
const roomsForDestinationBooking = (booking) => {
  const query = destinationQuery.value.trim().toLowerCase()
  const bookingMatches = booking.label.toLowerCase().includes(query)
  return props.destinations.filter(room => room.kind === 'room' && room.bookingId === booking.bookingId && (!query || bookingMatches || room.label.toLowerCase().includes(query)))
}
const selectDestination = (destination) => {
  destinationKey.value = destination.key
  destinationQuery.value = destination.label
  showDestinationDropdown.value = false
}
const focusDestination = () => {
  if (selectedDestination.value) destinationQuery.value = ''
  showDestinationDropdown.value = true
}
const clearDestination = () => {
  destinationKey.value = ''
  destinationQuery.value = ''
  showDestinationDropdown.value = true
  destinationInputRef.value?.focus()
}
const handleDestinationClickOutside = (event) => {
  if (destinationContainerRef.value && !destinationContainerRef.value.contains(event.target)) {
    showDestinationDropdown.value = false
    if (selectedDestination.value) destinationQuery.value = selectedDestination.value.label
  }
}
const money = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0)

const modalRef = ref(null)
const modalPosition = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 })

function clampModalPosition(x, y) {
  const modal = modalRef.value
  if (!modal) return { x, y }

  const margin = 16
  const width = modal.offsetWidth
  const height = modal.offsetHeight
  const baseLeft = (window.innerWidth - width) / 2
  const baseTop = (window.innerHeight - height) / 2
  const minX = margin - baseLeft
  const maxX = window.innerWidth - margin - width - baseLeft
  const minY = margin - baseTop
  const maxY = window.innerHeight - margin - height - baseTop

  return {
    x: minX <= maxX ? Math.min(Math.max(x, minX), maxX) : 0,
    y: minY <= maxY ? Math.min(Math.max(y, minY), maxY) : 0,
  }
}

function beginModalDrag(event) {
  if (event.button !== 0 || event.target.closest('button, input, select, textarea')) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('mousemove', onModalDrag)
  window.addEventListener('mouseup', stopModalDrag)
}

function onModalDrag(event) {
  if (!isDraggingModal.value) return
  const rawX = dragStart.value.left + (event.clientX - dragStart.value.x)
  const rawY = dragStart.value.top + (event.clientY - dragStart.value.y)
  modalPosition.value = clampModalPosition(rawX, rawY)
}

function stopModalDrag() {
  if (!isDraggingModal.value) return
  isDraggingModal.value = false
  window.removeEventListener('mousemove', onModalDrag)
  window.removeEventListener('mouseup', stopModalDrag)
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) emit('close')
}

watch(() => props.show, visible => {
  if (visible) {
    destinationKey.value = ''
    destinationQuery.value = ''
    showDestinationDropdown.value = false
    modalPosition.value = { x: 0, y: 0 }
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
    document.removeEventListener('pointerdown', handleDestinationClickOutside)
    document.addEventListener('pointerdown', handleDestinationClickOutside)
  } else {
    stopModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
    document.removeEventListener('pointerdown', handleDestinationClickOutside)
  }
})

onBeforeUnmount(() => {
  stopModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
  document.removeEventListener('pointerdown', handleDestinationClickOutside)
})
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 font-sans select-none">
    <div
      ref="modalRef"
      class="flex w-full max-w-[1200px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      <header
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Chuyển Cọc</span>
        <div class="flex gap-2">
          <HelpCircle class="h-4 w-4" />
          <button :disabled="loading" @click="emit('close')" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer disabled:opacity-40"><X class="h-4 w-4" /></button>
        </div>
      </header>

      <div class="space-y-4 p-4">
        <div class="grid grid-cols-2 gap-10">
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Từ khách</span><input :value="fromLabel" readonly class="h-8 w-full rounded border border-slate-300 bg-slate-100 px-2.5 text-xs text-[#000000D9] outline-none select-none" /></label>
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Đến khách</span>
            <div ref="destinationContainerRef" class="relative">
              <input
                ref="destinationInputRef"
                v-model="destinationQuery"
                type="text"
                placeholder="Chọn khách / booking / phòng"
                @click="focusDestination"
                @focus="focusDestination"
                @input="destinationKey = ''; showDestinationDropdown = true"
                class="h-8 w-full rounded border border-slate-300 bg-white px-2.5 pr-14 text-xs text-[#000000D9] outline-none focus:border-sky-500"
              />
              <button v-if="destinationQuery || selectedDestination" type="button" title="Xóa khách đích" @mousedown.prevent @click="clearDestination" class="absolute right-7 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer"><X class="h-3.5 w-3.5" /></button>
              <button type="button" title="Mở danh sách khách" @mousedown.prevent @click="showDestinationDropdown = !showDestinationDropdown; destinationInputRef?.focus()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 cursor-pointer">
                <ChevronDown class="h-4 w-4" />
              </button>
              <div v-if="showDestinationDropdown" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-2xl">
                <div v-for="booking in filteredDestinationBookings" :key="booking.key" class="border-b border-gray-100 last:border-b-0">
                  <button type="button" @mousedown.prevent="selectDestination(booking)" class="flex w-full items-center gap-4 px-3 py-2 text-left transition-colors hover:bg-sky-50"><span class="min-w-[36px] font-bold text-gray-900">BKK:</span><span class="min-w-[75px] font-bold text-gray-900">{{ booking.bookingCode }}</span><span class="truncate font-bold text-gray-800">{{ booking.bookingName }}</span></button>
                  <button v-for="room in roomsForDestinationBooking(booking)" :key="room.key" type="button" @mousedown.prevent="selectDestination(room)" class="flex w-full items-center gap-4 py-1 pl-[51px] pr-3 text-left text-gray-700 transition-colors hover:bg-sky-50 hover:text-sky-600">
                    <span class="min-w-[75px] font-bold text-gray-800">{{ room.roomNumber }}</span>
                    <span class="text-gray-400">|</span>
                    <span class="truncate font-bold text-gray-800">{{ room.guestName }}</span>
                    <span v-if="room.isPrimary" class="shrink-0 text-sky-500 font-normal text-xs">(Khách chính)</span>
                  </button>
                </div>
                <div v-if="filteredDestinations.length === 0" class="px-3 py-2 text-center text-gray-400">Không tìm thấy dữ liệu</div>
              </div>
            </div>
          </label>
        </div>

        <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-red-700">{{ error }}</p>

        <div class="min-h-[200px] overflow-auto rounded border border-gray-300">
          <table class="w-full border-collapse text-center">
            <thead class="bg-[#f0f2ea]">
              <tr>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Ngày/Giờ</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Bộ Phận</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Mô Tả</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">HTTT</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Số Tiền</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Đơn Vị</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Folio</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Mã TT</th>
                <th class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9]">Người Dùng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedDestination?.payments || []" :key="item.id" class="border-t border-gray-200 bg-sky-100 font-normal text-xs text-[#000000D9]"><td class="p-2">{{ item.dateTime }}</td><td>{{ item.department }}</td><td class="text-left">{{ item.description }}</td><td>{{ item.paymentMethod }}</td><td class="text-right tabular-nums">{{ money(item.amount) }}</td><td>{{ item.unit }}</td><td>{{ item.folio }}</td><td>{{ item.paymentCode }}</td><td>{{ item.userName }}</td></tr>
              <tr v-if="!selectedDestination || !selectedDestination.payments?.length"><td colspan="9" class="h-40 text-gray-400"><Inbox class="mx-auto mb-1 h-9 w-9" />No data</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="flex justify-end border-t border-gray-200 bg-slate-50 p-3">
        <button :disabled="!selectedDestination || loading" @click="emit('transfer', selectedDestination)" class="btn-pms-primary"><ArrowRightLeft class="h-3.5 w-3.5" />Chuyển</button>
      </footer>
    </div>
  </div>
</template>
