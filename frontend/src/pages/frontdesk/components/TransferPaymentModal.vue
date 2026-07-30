<script setup>
import { computed, ref, watch } from 'vue'
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
const money = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0)

watch(() => props.show, visible => {
  if (visible) {
    destinationKey.value = ''
    destinationQuery.value = ''
    showDestinationDropdown.value = false
  }
})
</script>

<style scoped>
footer button { transition: background-color 150ms ease, transform 100ms ease; }
footer button:hover:not(:disabled) { background-color: #0577d7; }
footer button:active:not(:disabled) { transform: scale(.95); }
footer button:disabled { cursor: not-allowed; opacity: .4; }
</style>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex w-full max-w-[1725px] flex-col overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between bg-[#0788f5] px-4 py-2 text-white">
        <span class="font-bold">Chuyển cọc</span>
        <div class="flex gap-2"><HelpCircle class="h-5 w-5" /><button :disabled="loading" @click="emit('close')" class="rounded transition hover:bg-white/20 active:scale-90 disabled:opacity-40"><X class="h-5 w-5" /></button></div>
      </header>

      <div class="space-y-4 p-4">
        <div class="grid grid-cols-2 gap-10">
          <label class="block"><span class="mb-1 block font-semibold">Từ khách</span><input :value="fromLabel" readonly class="h-7 w-full rounded border border-sky-400 bg-slate-100 px-2" /></label>
          <label class="block"><span class="mb-1 block font-semibold">Đến khách</span>
            <div class="relative">
              <input v-model="destinationQuery" type="text" placeholder="Chọn khách / booking / phòng" @focus="showDestinationDropdown = true" @input="destinationKey = ''; showDestinationDropdown = true" class="h-7 w-full rounded border border-gray-300 bg-white px-2 pr-7 outline-none focus:border-sky-500" />
              <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
              <div v-if="showDestinationDropdown" class="absolute left-0 right-0 top-full z-50 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-2xl">
                <div v-for="booking in filteredDestinationBookings" :key="booking.key" class="border-b border-gray-100 last:border-b-0">
                  <button type="button" @mousedown.prevent="selectDestination(booking)" class="flex w-full items-center gap-4 px-3 py-2 text-left transition-colors hover:bg-sky-50"><span class="min-w-[36px] font-bold text-gray-900">BKK:</span><span class="min-w-[75px] font-bold text-gray-900">{{ booking.bookingCode }}</span><span class="truncate font-bold text-gray-800">{{ booking.bookingName }}</span></button>
                  <button v-for="room in roomsForDestinationBooking(booking)" :key="room.key" type="button" @mousedown.prevent="selectDestination(room)" class="flex w-full items-center gap-4 py-1 pl-[51px] pr-3 text-left text-gray-700 transition-colors hover:bg-sky-50 hover:text-sky-600"><span class="min-w-[75px] font-bold text-gray-800">{{ room.roomNumber }}</span><span class="text-gray-400">|</span><span class="truncate font-bold text-gray-800">{{ room.guestName }}</span></button>
                </div>
                <div v-if="filteredDestinations.length === 0" class="px-3 py-2 text-center text-gray-400">Không tìm thấy dữ liệu</div>
              </div>
            </div>
          </label>
        </div>

        <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-red-700">{{ error }}</p>

        <div class="min-h-[200px] overflow-auto rounded border border-gray-300">
          <table class="w-full border-collapse text-center"><thead class="bg-[#f0f2ea]"><tr>
            <th class="p-2">Ngày/giờ</th><th class="p-2">Bộ phận</th><th class="p-2">Mô tả</th><th class="p-2">HTTT</th><th class="p-2 text-right">Số tiền</th><th class="p-2">Đơn vị</th><th class="p-2">Folio</th><th class="p-2">Mã TT</th><th class="p-2">Người dùng</th>
          </tr></thead><tbody>
            <tr v-for="item in selectedDestination?.payments || []" :key="item.id" class="border-t border-gray-200 bg-sky-100"><td class="p-2">{{ item.dateTime }}</td><td>{{ item.department }}</td><td class="text-left">{{ item.description }}</td><td>{{ item.paymentMethod }}</td><td class="text-right font-mono text-green-600">{{ money(item.amount) }}</td><td>{{ item.unit }}</td><td>{{ item.folio }}</td><td>{{ item.paymentCode }}</td><td>{{ item.userName }}</td></tr>
            <tr v-if="!selectedDestination || !selectedDestination.payments?.length"><td colspan="9" class="h-40 text-gray-400"><Inbox class="mx-auto mb-1 h-9 w-9" />No data</td></tr>
          </tbody></table>
        </div>
      </div>

      <footer class="flex justify-end gap-2 border-t border-gray-200 p-3"><button @click="emit('close')" class="rounded bg-[#0788f5] px-4 py-1.5 font-semibold text-white"><X class="mr-1 inline h-4 w-4" />Đóng</button><button :disabled="!selectedDestination || loading" @click="emit('transfer', selectedDestination)" class="rounded bg-[#0788f5] px-4 py-1.5 font-semibold text-white disabled:opacity-40"><ArrowRightLeft class="mr-1 inline h-4 w-4" />Chuyển</button></footer>
    </div>
  </div>
</template>
