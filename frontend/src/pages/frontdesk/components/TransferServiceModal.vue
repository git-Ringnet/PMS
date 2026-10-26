<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { HelpCircle, X, Inbox, ArrowRightLeft, ChevronDown } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  fromGuest: { type: String, default: '' },
  error: { type: String, default: '' },
  destinations: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'transfer'])

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
  if (event.button !== 0) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('pointermove', moveModal)
  window.addEventListener('pointerup', endModalDrag)
}

function moveModal(event) {
  if (!isDraggingModal.value) return
  modalPosition.value = clampModalPosition(
    dragStart.value.left + event.clientX - dragStart.value.x,
    dragStart.value.top + event.clientY - dragStart.value.y,
  )
}

function endModalDrag() {
  isDraggingModal.value = false
  window.removeEventListener('pointermove', moveModal)
  window.removeEventListener('pointerup', endModalDrag)
}

function resetModalPosition() {
  endModalDrag()
  modalPosition.value = { x: 0, y: 0 }
}

const destinationKey = ref('')
const destinationQuery = ref('')
const showDestinationDropdown = ref(false)
const isEditingDestination = ref(false)
const destinationInputRef = ref(null)
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

const destinationContainerRef = ref(null)

const selectDestination = (destination) => {
  destinationKey.value = destination.key
  destinationQuery.value = destination.label
  isEditingDestination.value = false
  showDestinationDropdown.value = false
}

const focusDestination = () => {
  if (selectedDestination.value) destinationQuery.value = ''
  isEditingDestination.value = true
  showDestinationDropdown.value = true
}

const inputDestination = () => {
  destinationKey.value = ''
  isEditingDestination.value = true
  showDestinationDropdown.value = true
}

const clearDestination = () => {
  destinationKey.value = ''
  destinationQuery.value = ''
  isEditingDestination.value = true
  showDestinationDropdown.value = true
  destinationInputRef.value?.focus()
}

const handleDestinationClickOutside = (event) => {
  if (destinationContainerRef.value && !destinationContainerRef.value.contains(event.target)) {
    showDestinationDropdown.value = false
    if (selectedDestination.value) destinationQuery.value = selectedDestination.value.label
  }
}

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, (visible) => {
  if (visible) {
    resetModalPosition()
    destinationKey.value = ''
    destinationQuery.value = ''
    isEditingDestination.value = false
    showDestinationDropdown.value = false
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
    document.removeEventListener('pointerdown', handleDestinationClickOutside)
    document.addEventListener('pointerdown', handleDestinationClickOutside)
  } else {
    endModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
    document.removeEventListener('pointerdown', handleDestinationClickOutside)
  }
})
onBeforeUnmount(() => {
  endModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
  document.removeEventListener('pointerdown', handleDestinationClickOutside)
})

const money = (value) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0)
const transfer = () => {
  if (!selectedDestination.value || props.loading) return
  emit('transfer', selectedDestination.value)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div
      ref="modalRef"
      class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate(${modalPosition.x}px, ${modalPosition.y}px)`, transition: isDraggingModal ? 'none' : '' }"
    >
      <header
        class="flex shrink-0 items-center justify-between px-4 py-2.5 text-white cursor-move select-none touch-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @pointerdown="beginModalDrag"
      >
        <span class="font-semibold text-xs tracking-wide">Chuyển Dịch Vụ</span>
        <div class="flex gap-2">
          <button @pointerdown.stop :disabled="loading" @click="close" class="rounded transition hover:bg-white/20 active:scale-90 disabled:opacity-40 p-1 cursor-pointer" title="Đóng">
            <X class="h-4 w-4" />
          </button>
        </div>
      </header>

      <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4">
        <div class="grid shrink-0 grid-cols-[minmax(240px,0.8fr)_minmax(360px,1.2fr)] gap-4">
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Từ khách</span><input :value="fromGuest" readonly class="h-8 w-full rounded-lg border border-slate-200 bg-slate-100 px-2 font-normal text-[#000000D9]" /></label>
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Đến khách</span>
            <div ref="destinationContainerRef" class="relative">
              <input
                ref="destinationInputRef"
                v-model="destinationQuery"
                type="text"
                :placeholder="selectedDestination?.label || 'Chọn khách / booking / phòng'"
                @click="focusDestination"
                @focus="focusDestination"
                @input="inputDestination"
                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 pr-14 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-sky-500"
              />
              <button v-if="destinationQuery || selectedDestination" type="button" title="Xóa khách đích" @mousedown.prevent @click="clearDestination" class="absolute right-7 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer"><X class="h-3.5 w-3.5" /></button>
              <button type="button" title="Mở danh sách khách" @mousedown.prevent @click="showDestinationDropdown = !showDestinationDropdown; destinationInputRef?.focus()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 cursor-pointer">
                <ChevronDown class="h-4 w-4" />
              </button>
              <div v-if="showDestinationDropdown" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-2xl">
                <div v-for="booking in filteredDestinationBookings" :key="booking.key" class="border-b border-gray-100 last:border-b-0">
                  <button type="button" @mousedown.prevent="selectDestination(booking)" class="flex w-full items-center gap-4 px-3 py-2 text-left text-xs transition-colors hover:bg-sky-50">
                    <span class="min-w-[36px] font-bold text-gray-900">BKK:</span><span class="min-w-[75px] font-bold text-gray-900">{{ booking.bookingCode }}</span><span class="truncate font-bold text-gray-800">{{ booking.bookingName }}</span>
                  </button>
                  <button v-for="room in roomsForDestinationBooking(booking)" :key="room.key" type="button" @mousedown.prevent="selectDestination(room)" class="flex w-full items-center gap-4 py-1 pl-[51px] pr-3 text-left text-xs text-gray-700 transition-colors hover:bg-sky-50 hover:text-sky-600">
                    <span class="min-w-[75px] font-bold text-gray-800">{{ room.roomNumber }}</span><span class="text-gray-400">|</span><span class="truncate font-bold text-gray-800">{{ room.guestName }}</span>
                    <span v-if="room.isPrimary" class="shrink-0 text-sky-500 font-normal text-xs">(Khách chính)</span>
                  </button>
                </div>
                <div v-if="filteredDestinations.length === 0" class="px-3 py-2 text-center text-gray-400">Không tìm thấy dữ liệu</div>
              </div>
            </div>
            <select v-model="destinationKey" class="hidden" tabindex="-1" aria-hidden="true">
              <option disabled value="">Chọn khách / booking / phòng</option>
              <option v-for="item in destinations" :key="item.key" :value="item.key">{{ item.label }}</option>
            </select>
          </label>
        </div>
        <p v-if="error" class="shrink-0 rounded border border-red-300 bg-red-50 px-3 py-2 text-red-700">{{ error }}</p>

        <div class="min-h-[200px] flex-1 overflow-auto rounded border border-gray-300">
          <table class="w-full border-collapse text-center text-xs font-['Roboto',sans-serif] text-[#000000D9]">
            <thead class="sticky top-0 z-10 bg-[#f0f2ea]">
              <tr>
                <th class="w-32 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Ngày/Giờ</th>
                <th class="w-20 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Bộ Phận</th>
                <th class="w-20 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Dịch Vụ</th>
                <th class="p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Mô Tả</th>
                <th class="w-28 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Số Tiền</th>
                <th class="w-20 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Đơn Vị</th>
                <th class="w-16 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Folio</th>
                <th class="w-16 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Tax</th>
                <th class="w-24 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Phí Phục Vụ</th>
                <th class="w-28 p-2 text-center align-middle border-r border-slate-200 border font-semibold text-xs text-[#000000D9]">Người Dùng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedDestination?.services || []" :key="item.id" :class="['border-t border-gray-200 transition-colors text-xs font-normal text-[#000000D9]', item.isPaid ? 'bg-rose-100' : 'bg-white hover:bg-slate-200']">
                <td class="p-2 border-slate-200 font-normal text-[#000000D9]">{{ item.dateTime }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9]">{{ item.department }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9]">{{ item.serviceCode }}</td>
                <td class="text-left border-slate-200 font-normal text-[#000000D9] px-2">{{ item.serviceName }}</td>
                <td class="text-right border-slate-200 font-normal text-[#000000D9] tabular-nums px-2">{{ money(item.totalAmount) }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9]">{{ item.unit }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9]">{{ item.folio }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9] tabular-nums">{{ money(item.tax) }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9] tabular-nums">{{ money(item.serviceCharge) }}</td>
                <td class="border-slate-200 font-normal text-[#000000D9]">{{ item.userName }}</td>
              </tr>
              <tr v-if="!selectedDestination || !selectedDestination.services?.length"><td colspan="10" class="h-40 text-gray-400 border-slate-200"><Inbox class="mx-auto mb-1 h-9 w-9" />No data</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="flex shrink-0 justify-end border-t border-gray-200 p-3">
        <button :disabled="!selectedDestination" @click="transfer" class="btn-pms-primary"><ArrowRightLeft class="h-4 w-4" />Chuyển</button>
      </footer>
    </div>
  </div>
</template>
