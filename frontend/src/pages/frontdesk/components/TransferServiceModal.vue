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
const destinationKey = ref('')
const destinationQuery = ref('')
const showDestinationDropdown = ref(false)
const isEditingDestination = ref(false)
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

const blurDestination = () => {
  window.setTimeout(() => {
    if (selectedDestination.value) destinationQuery.value = selectedDestination.value.label
    isEditingDestination.value = false
    showDestinationDropdown.value = false
  }, 0)
}

const clearDestination = () => {
  destinationKey.value = ''
  destinationQuery.value = ''
  isEditingDestination.value = true
  showDestinationDropdown.value = true
}

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, (visible) => {
  if (visible) {
    destinationKey.value = ''
    destinationQuery.value = ''
    isEditingDestination.value = false
    showDestinationDropdown.value = false
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    window.removeEventListener('keydown', handleKeyDown)
  }
})
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeyDown))

const money = (value) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0)
const transfer = () => {
  if (!selectedDestination.value || props.loading) return
  emit('transfer', selectedDestination.value)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <header class="flex shrink-0 items-center justify-between px-4 py-2 text-white" :style="{ background: 'var(--pms-custom-theme, #006bdb)' }">
        <span class="font-bold">Chuyển dịch vụ</span>
        <div class="flex gap-2"><HelpCircle class="h-5 w-5" /><button :disabled="loading" @click="close" class="rounded transition hover:bg-white/20 active:scale-90 disabled:opacity-40"><X class="h-5 w-5" /></button></div>
      </header>

      <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4">
        <div class="grid shrink-0 grid-cols-[minmax(240px,0.8fr)_minmax(360px,1.2fr)] gap-4">
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Từ khách</span><input :value="fromGuest" readonly class="h-8 w-full rounded-lg border border-slate-200 bg-slate-100 px-2 font-normal text-[#000000D9]" /></label>
          <label class="block"><span class="mb-1 block font-semibold text-[#000000D9]">Đến khách</span>
            <div class="relative">
              <input
                v-model="destinationQuery"
                type="text"
                :placeholder="selectedDestination?.label || 'Chọn khách / booking / phòng'"
                @focus="focusDestination"
                @input="inputDestination"
                @blur="blurDestination"
                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 pr-14 font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-sky-500"
              />
              <button v-if="destinationQuery || selectedDestination" type="button" title="Xóa khách đích" @mousedown.prevent @click="clearDestination" class="absolute right-7 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" /></button>
              <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
              <div v-if="showDestinationDropdown" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-2xl">
                <div v-for="booking in filteredDestinationBookings" :key="booking.key" class="border-b border-gray-100 last:border-b-0">
                  <button type="button" @mousedown.prevent="selectDestination(booking)" class="flex w-full items-center gap-4 px-3 py-2 text-left text-xs transition-colors hover:bg-sky-50">
                    <span class="min-w-[36px] font-bold text-gray-900">BKK:</span><span class="min-w-[75px] font-bold text-gray-900">{{ booking.bookingCode }}</span><span class="truncate font-bold text-gray-800">{{ booking.bookingName }}</span>
                  </button>
                  <button v-for="room in roomsForDestinationBooking(booking)" :key="room.key" type="button" @mousedown.prevent="selectDestination(room)" class="flex w-full items-center gap-4 py-1 pl-[51px] pr-3 text-left text-xs text-gray-700 transition-colors hover:bg-sky-50 hover:text-sky-600">
                    <span class="min-w-[75px] font-bold text-gray-800">{{ room.roomNumber }}</span><span class="text-gray-400">|</span><span class="truncate font-bold text-gray-800">{{ room.guestName }}</span>
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
          <table class="w-full border-collapse text-center"><thead class="sticky top-0 z-10 bg-[#f0f2ea]"><tr>
            <th class="w-32 p-2">Ngày/giờ</th><th class="w-20 p-2">Bộ phận</th><th class="w-20 p-2">Dịch vụ</th><th class="p-2">Mô tả</th><th class="w-28 p-2 text-right">Số tiền</th><th class="w-20 p-2">Đơn vị</th><th class="w-16 p-2">Folio</th><th class="w-16 p-2">Tax</th><th class="w-24 p-2">Phí phục vụ</th><th class="w-28 p-2">Người dùng</th>
          </tr></thead><tbody>
            <tr v-for="item in selectedDestination?.services || []" :key="item.id" :class="['border-t border-gray-200 transition-colors', item.isPaid ? 'bg-rose-100' : 'bg-white hover:bg-slate-200']"><td class="p-2">{{ item.dateTime }}</td><td>{{ item.department }}</td><td>{{ item.serviceCode }}</td><td class="text-left">{{ item.serviceName }}</td><td class="text-right text-green-600">{{ money(item.totalAmount) }}</td><td>{{ item.unit }}</td><td>{{ item.folio }}</td><td>{{ money(item.tax) }}</td><td>{{ money(item.serviceCharge) }}</td><td>{{ item.userName }}</td></tr>
            <tr v-if="!selectedDestination || !selectedDestination.services?.length"><td colspan="10" class="h-40 text-gray-400"><Inbox class="mx-auto mb-1 h-9 w-9" />No data</td></tr>
          </tbody></table>
        </div>
      </div>

      <footer class="flex shrink-0 justify-end border-t border-gray-200 p-3"><button :disabled="!selectedDestination" @click="transfer" class="btn-pms-primary"><ArrowRightLeft class="h-4 w-4" />Chuyển</button></footer>
    </div>
  </div>
</template>
