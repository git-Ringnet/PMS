<script setup>
import { computed, ref, watch } from 'vue'
import { X, XCircle, Save, Calendar } from '@lucide/vue'
import { adjustRoomRate } from '@/services/booking-service'

const props = defineProps({ show: Boolean, booking: Object, systemDate: String })
const emit = defineEmits(['close', 'success'])
const roomId = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const rate = ref(0)
const description = ref('Dịch vụ phòng nghỉ')
const reason = ref('')
const updateRoomRate = ref(false)
const updateRoomRateScope = ref('room')
const isNight = ref(false)
const selectedNightDates = ref([])
const error = ref('')
const saving = ref(false)

const rooms = computed(() => (props.booking?.roomItems || []).filter(room => [1, 2].includes(Number(room.rawRoom?.status ?? room.status))))
const selectedRoom = computed(() => rooms.value.find(room => String(room.roomId || room.id) === String(roomId.value)))

const bookingName = computed(() => {
  if (!props.booking) return ''
  const parts = []
  if (props.booking.code) parts.push(props.booking.code)
  if (props.booking.name) parts.push(props.booking.name)
  if (props.booking.companyName && props.booking.companyName !== props.booking.name) parts.push(props.booking.companyName)
  if (props.booking.phone) parts.push(props.booking.phone)
  return parts.filter(Boolean).join(' - ') || props.booking.code || ''
})

function normalizeYmd(value) {
  const raw = String(value || '').trim()
  if (raw.includes('T')) {
    const date = new Date(raw)
    if (!Number.isNaN(date.getTime())) return toYmd(date)
  }
  if (/^\d{4}-\d{2}-\d{2}/.test(raw)) return raw.slice(0, 10)
  const match = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)
  if (!match) return ''
  return `${match[3]}-${match[1].padStart(2, '0')}-${match[2].padStart(2, '0')}`
}

const roomArrivalDate = computed(() => normalizeYmd(selectedRoom.value?.rawRoom?.arrival_date || selectedRoom.value?.arrivalDate || props.booking?.arrivalDate))
const roomDepartureDate = computed(() => normalizeYmd(selectedRoom.value?.rawRoom?.departure_date || selectedRoom.value?.departureDate || props.booking?.departureDate))
const maxDate = computed(() => {
  const systemDate = normalizeYmd(props.systemDate)
  if (!systemDate) return ''
  return systemDate
})
const minDate = computed(() => roomArrivalDate.value)
const departureDate = computed(() => roomDepartureDate.value)
const rangeEndMax = computed(() => {
  if (!departureDate.value) return maxDate.value
  const systemDate = normalizeYmd(props.systemDate)
  return !systemDate || departureDate.value < systemDate ? departureDate.value : systemDate
})
const availableNights = computed(() => {
  if (!minDate.value || !departureDate.value || !maxDate.value) return []
  const cursor = new Date(`${minDate.value}T00:00:00`)
  const end = new Date(`${departureDate.value}T00:00:00`)
  const latest = new Date(`${maxDate.value}T00:00:00`)
  const nights = []
  while (cursor < end && cursor <= latest) {
    nights.push(toYmd(cursor))
    cursor.setDate(cursor.getDate() + 1)
  }
  return nights
})

function formatToDisplayDate(ymdStr) {
  if (!ymdStr || typeof ymdStr !== 'string') return ''
  const parts = ymdStr.split('-')
  if (parts.length !== 3) return ymdStr
  return `${parts[1]}/${parts[2]}/${parts[0]}`
}

function parseDisplayToYmd(displayStr) {
  if (!displayStr) return ''
  const clean = displayStr.replace(/\s+/g, '')
  const parts = clean.split('/')
  if (parts.length === 3) {
    const month = parts[0].padStart(2, '0')
    const day = parts[1].padStart(2, '0')
    const year = parts[2]
    if (year.length === 4) return `${year}-${month}-${day}`
  }
  return ''
}

const displayDateFrom = ref('')
const displayDateTo = ref('')

watch(dateFrom, val => {
  displayDateFrom.value = formatToDisplayDate(val)
}, { immediate: true })

watch(dateTo, val => {
  displayDateTo.value = formatToDisplayDate(val)
}, { immediate: true })

function onDisplayDateFromInput(e) {
  const val = e.target.value
  displayDateFrom.value = val
  const parsed = parseDisplayToYmd(val)
  if (parsed && parsed.length === 10) {
    dateFrom.value = parsed
  }
}

function onDisplayDateToInput(e) {
  const val = e.target.value
  displayDateTo.value = val
  const parsed = parseDisplayToYmd(val)
  if (parsed && parsed.length === 10) {
    dateTo.value = parsed
  }
}

function toYmd(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function roomDates() {
  if (isNight.value) return [...selectedNightDates.value].sort()
  if (!dateFrom.value || !dateTo.value) return []
  const from = new Date(`${dateFrom.value}T00:00:00`)
  const to = new Date(`${dateTo.value}T00:00:00`)
  if (to < from) return []
  if (to.getTime() === from.getTime()) return availableNights.value.includes(dateFrom.value) ? [dateFrom.value] : []
  const dates = []
  while (from < to) {
    const date = toYmd(from)
    if (availableNights.value.includes(date)) dates.push(date)
    from.setDate(from.getDate() + 1)
  }
  return dates
}

function initializeStayDates(room) {
  const arrival = normalizeYmd(room?.rawRoom?.arrival_date || room?.arrivalDate || props.booking?.arrivalDate || maxDate.value)
  const departure = normalizeYmd(room?.rawRoom?.departure_date || room?.departureDate || props.booking?.departureDate || maxDate.value)
  const systemDate = normalizeYmd(props.systemDate)
  if (!arrival || !departure || !systemDate) return false
  dateFrom.value = arrival
  dateTo.value = departure
  return true
}

watch(() => props.show, visible => {
  if (!visible) return
  const room = rooms.value[0]
  roomId.value = String(room?.roomId || room?.id || '')
  initializeStayDates(room)
  rate.value = Number(room?.rate || room?.roomRate || room?.rawRoom?.rate || 0)
  description.value = 'Dịch vụ phòng nghỉ'
  reason.value = ''
  updateRoomRate.value = false
  updateRoomRateScope.value = 'room'
  isNight.value = false
  selectedNightDates.value = []
  error.value = ''
})

watch([() => props.show, () => props.systemDate, selectedRoom], ([visible, systemDate, room]) => {
  if (!visible || !systemDate || !room) return
  initializeStayDates(room)
}, { flush: 'post' })

watch(selectedRoom, room => {
  if (!room) return
  rate.value = Number(room.rate || room.roomRate || room.rawRoom?.rate || 0)
  const arrival = normalizeYmd(room.rawRoom?.arrival_date || room.arrivalDate || props.booking?.arrivalDate)
  const departure = normalizeYmd(room.rawRoom?.departure_date || room.departureDate || props.booking?.departureDate)
  if (dateFrom.value < arrival || dateFrom.value >= departure) dateFrom.value = arrival
  if (dateTo.value <= dateFrom.value || dateTo.value > departure) dateTo.value = departure
  selectedNightDates.value = selectedNightDates.value.filter(date => availableNights.value.includes(date))
})

async function submit() {
  error.value = ''
  const dates = roomDates()
  if (!roomId.value || dates.length === 0 || rate.value < 0 || !reason.value.trim()) {
    error.value = 'Nhập đủ phòng, ngày, giá và lý do điều chỉnh.'
    return
  }
  saving.value = true
  try {
    for (const serviceDate of dates) {
      await adjustRoomRate(props.booking.bookingId, {
        booking_room_id: roomId.value,
        service_date: serviceDate,
        rate: Number(rate.value),
        description: description.value.trim() || 'Dịch vụ phòng nghỉ',
        reason: reason.value.trim(),
        update_room_rate: updateRoomRate.value,
        update_room_rate_scope: updateRoomRateScope.value,
      })
    }
    emit('success')
  } catch (e) {
    error.value = e.response?.data?.message || 'Không thể điều chỉnh tiền phòng.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4">
    <div class="w-[720px] max-w-[calc(100vw-32px)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
      <!-- Header Bar -->
      <div
        class="flex h-[36px] items-center justify-between px-4"
        style="background-color: #0788eb !important; color: #ffffff !important;"
      >
        <span class="text-[13px] font-bold tracking-wide">Điều chỉnh tiền phòng</span>
        <button
          @click="emit('close')"
          class="flex items-center justify-center text-white/90 hover:opacity-80 transition cursor-pointer text-lg font-bold leading-none"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Form Body -->
      <div class="space-y-3 p-5 text-[12px] text-slate-700">
        <!-- Row 1: Tên BK & Phòng -->
        <div class="grid grid-cols-12 gap-3">
          <div class="col-span-7">
            <label class="mb-1 block text-slate-700">Tên <span class="font-bold text-slate-900">BK</span></label>
            <input
              :value="bookingName"
              readonly
              class="h-[32px] w-full rounded-[6px] border border-slate-300 bg-[#e9ecef] px-2.5 text-[12px] font-normal text-slate-700 outline-none select-none"
            />
          </div>
          <div class="col-span-5">
            <label class="mb-1 block text-slate-700"><span class="font-bold text-slate-900">Phòng</span></label>
            <select
              v-model="roomId"
              class="h-[32px] w-full rounded-[6px] border border-slate-300 bg-white px-2.5 text-[12px] text-slate-800 shadow-sm outline-none focus:border-[#0788eb]"
            >
              <option v-for="room in rooms" :key="room.roomId || room.id" :value="String(room.roomId || room.id)">
                Chọn: {{ room.roomNumber || room.rawRoom?.room_number || 0 }}
              </option>
            </select>
            <label v-if="updateRoomRate" class="mt-2 flex cursor-pointer items-center gap-1.5 text-[12px] text-slate-700 select-none">
              <input v-model="updateRoomRateScope" true-value="booking" false-value="room" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0788eb] focus:ring-[#0788eb]" />
              <span>Cập nhật giá toàn bộ phòng trong booking</span>
            </label>
          </div>
        </div>

        <!-- Row 2: Ngày & Đêm -->
        <div>
          <label class="mb-1 block text-slate-700"><span class="font-bold text-slate-900">Ngày</span></label>
          <div class="flex items-center gap-3">
            <div class="flex h-[32px] items-center gap-1 rounded-[6px] border border-slate-300 bg-white px-2.5 shadow-sm focus-within:border-[#0788eb]">
              <input
                :value="displayDateFrom"
                @input="onDisplayDateFromInput"
                type="text"
                placeholder="MM/DD/YYYY"
                :disabled="isNight"
                class="w-[85px] bg-transparent text-[12px] text-slate-800 outline-none text-center disabled:cursor-not-allowed disabled:text-slate-400"
              />
              <span class="font-medium text-slate-400 px-0.5">~</span>
              <input
                :value="displayDateTo"
                @input="onDisplayDateToInput"
                type="text"
                placeholder="MM/DD/YYYY"
                :disabled="isNight"
                class="w-[85px] bg-transparent text-[12px] text-slate-800 outline-none text-center disabled:cursor-not-allowed disabled:text-slate-400"
              />
              <div class="relative flex items-center ml-1">
                <Calendar class="h-4 w-4 shrink-0 text-emerald-500 stroke-[1.8] cursor-pointer" />
                <input
                  type="date"
                  :value="dateFrom"
                  :min="minDate"
                  :max="rangeEndMax"
                  @input="dateFrom = $event.target.value"
                  :disabled="isNight"
                  class="absolute inset-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                />
              </div>
            </div>
            <label class="flex shrink-0 cursor-pointer items-center gap-1.5 text-[12px] text-slate-700 select-none ml-1">
              <input v-model="isNight" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0788eb] focus:ring-[#0788eb]" />
              <span>Đêm</span>
            </label>
          </div>
          <div v-if="isNight" class="mt-2 rounded-[6px] border border-slate-300 bg-slate-50 px-2.5 py-2">
            <p class="mb-2 text-[12px] text-slate-700">Chọn đêm thay đổi ({{ selectedNightDates.length }}/{{ availableNights.length }})</p>
            <div class="grid grid-cols-5 gap-x-3 gap-y-1.5">
              <label v-for="night in availableNights" :key="night" class="flex cursor-pointer items-center gap-1.5 text-[12px] text-slate-700">
                <input v-model="selectedNightDates" :value="night" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-[#0788eb] focus:ring-[#0788eb]" />
                <span>{{ formatToDisplayDate(night) }}</span>
              </label>
              <span v-if="availableNights.length === 0" class="col-span-5 text-slate-400">Không có đêm hợp lệ để điều chỉnh.</span>
            </div>
          </div>
        </div>

        <!-- Row 3: Giá Phòng & Cập nhật giá -->
        <div>
          <label class="mb-1 block text-slate-700">Giá <span class="font-bold text-slate-900">Phòng</span></label>
          <div class="flex items-center gap-3">
            <input
              v-model.number="rate"
              min="0"
              type="number"
              class="h-[32px] w-[200px] rounded-[6px] border border-slate-300 bg-white px-2.5 text-[12px] text-slate-800 shadow-sm outline-none focus:border-[#0788eb]"
            />
            <label class="flex shrink-0 cursor-pointer items-center gap-1.5 text-[12px] text-slate-700 select-none">
              <input v-model="updateRoomRate" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0788eb] focus:ring-[#0788eb]" />
              <span>Cập nhật giá vào đăng ký</span>
            </label>
          </div>
        </div>

        <!-- Row 4: Mô tả -->
        <div>
          <label class="mb-1 block text-slate-700"><span class="font-bold text-slate-900">Mô tả</span></label>
          <textarea
            v-model="description"
            rows="2"
            placeholder="Dịch vụ phòng nghỉ"
            class="h-[46px] w-full resize-none rounded-[6px] border border-slate-300 bg-white px-2.5 py-1.5 text-[12px] text-slate-800 shadow-sm outline-none focus:border-[#0788eb]"
          />
        </div>

        <!-- Row 5: Lý do -->
        <div>
          <label class="mb-1 block text-slate-700"><span class="font-bold text-slate-900">Lý do</span></label>
          <input
            v-model="reason"
            type="text"
            class="h-[32px] w-full rounded-[6px] border border-slate-300 bg-white px-2.5 text-[12px] text-slate-800 shadow-sm outline-none focus:border-[#0788eb]"
          />
        </div>

        <!-- Error Message -->
        <p v-if="error" class="rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
          {{ error }}
        </p>
      </div>

      <!-- Footer Buttons -->
      <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-white px-5 py-3">
        <button
          type="button"
          @click="emit('close')"
          style="background-color: #0788eb !important; color: #ffffff !important;"
          class="flex h-[32px] min-w-[80px] items-center justify-center gap-1.5 rounded-[6px] px-3.5 text-[12px] font-semibold text-white shadow-sm hover:opacity-90 active:scale-95 transition cursor-pointer"
        >
          <XCircle class="h-4 w-4 stroke-[2] text-white" />
          <span>Đóng</span>
        </button>
        <button
          type="button"
          :disabled="saving"
          @click="submit"
          style="background-color: #0788eb !important; color: #ffffff !important;"
          class="flex h-[32px] min-w-[76px] items-center justify-center gap-1.5 rounded-[6px] px-3.5 text-[12px] font-semibold text-white shadow-sm hover:opacity-90 active:scale-95 transition disabled:opacity-50 cursor-pointer"
        >
          <Save class="h-4 w-4 stroke-[2] text-white" />
          <span>{{ saving ? 'Đang lưu...' : 'Lưu' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
