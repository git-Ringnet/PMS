<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  detail: { type: Object, default: null },
  date: { type: String, default: '' },
  metric: { type: String, default: '' },
  roomClassLabel: { type: String, default: '' },
})

const emit = defineEmits(['close', 'open-booking'])
const position = ref({ x: 0, y: 0 })
const dragging = ref(false)
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 })
const collapsedBookingKeys = ref(new Set())

const metricLabel = computed(() => ({
  AV: 'Phòng trống',
  OCC: 'Booking OCC',
  ALM: 'Allotment',
  OOO: 'Phòng khóa OOO',
  OOS: 'Phòng khóa OOS',
  EB: 'Extra Bed',
  BBC: 'Baby Cot',
}[props.metric] || props.metric))

const groupedAvailableRooms = computed(() => {
  const groups = new Map()
  for (const room of props.detail?.available_rooms || []) {
    const key = room.room_class_code || room.room_class_name || 'Khác'
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        name: room.room_class_name || room.room_class_code || 'Khác',
        rooms: [],
      })
    }
    groups.get(key).rooms.push(room)
  }
  return Array.from(groups.values())
})

const groupedBookingRooms = computed(() => {
  const groups = new Map()
  for (const room of props.detail?.booking_rooms || []) {
    const key = String(room.booking_id || room.booking_code || 'unknown')
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        bookingCode: room.booking_code || room.booking_id || '',
        bookingNote: room.note || '',
        rooms: [],
      })
    }
    groups.get(key).rooms.push(room)
  }
  return Array.from(groups.values())
})

const isBookingCollapsed = (key) => collapsedBookingKeys.value.has(key)

function toggleBooking(key) {
  const next = new Set(collapsedBookingKeys.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  collapsedBookingKeys.value = next
}

function openBooking(row) {
  if (!row?.booking_code) return
  emit('open-booking', row)
}

const formatDate = (value) => {
  if (!value) return ''
  const [year, month, day] = value.slice(0, 10).split('-')
  return year && month && day ? `${day}/${month}/${year}` : value
}

const formatAmount = (value) => {
  if (value === null || value === undefined || value === '') return ''
  return Number(value).toLocaleString('vi-VN')
}

function beginDrag(event) {
  if (event.button !== 0) return
  dragging.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: position.value.x,
    top: position.value.y,
  }
  window.addEventListener('pointermove', moveDrag)
  window.addEventListener('pointerup', endDrag)
}

function moveDrag(event) {
  if (!dragging.value) return
  position.value = {
    x: dragStart.value.left + event.clientX - dragStart.value.x,
    y: dragStart.value.top + event.clientY - dragStart.value.y,
  }
}

function endDrag() {
  dragging.value = false
  window.removeEventListener('pointermove', moveDrag)
  window.removeEventListener('pointerup', endDrag)
}

function resetPosition() {
  position.value = { x: 0, y: 0 }
}

onMounted(() => resetPosition())
onUnmounted(() => endDrag())
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] bg-slate-950/55 flex items-center justify-center p-3">
      <section
        class="w-[min(1700px,98vw)] h-[78vh] max-h-[78vh] overflow-hidden rounded-lg bg-white shadow-2xl border border-slate-300 font-sans"
        :style="{ transform: `translate(${position.x}px, ${position.y}px)` }"
      >
        <header
          class="h-10 flex items-center justify-between px-4 cursor-move select-none"
          style="background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #ffffff)"
          @pointerdown="beginDrag"
        >
          <div class="text-sm font-semibold">
            {{ metricLabel }} — {{ roomClassLabel || 'Tất cả loại phòng' }} — {{ formatDate(date) }}
          </div>
          <button type="button" class="text-2xl leading-none px-1 hover:text-white" @click="emit('close')">×</button>
        </header>

        <div v-if="loading" class="h-72 flex items-center justify-center text-sm text-slate-500">Đang tải dữ liệu...</div>
        <div v-else-if="error" class="h-72 flex items-center justify-center text-sm text-red-600">{{ error }}</div>
        <div v-else class="p-2 h-[calc(78vh-7rem)] overflow-hidden">
          <div class="grid grid-cols-[190px_minmax(760px,1fr)] gap-2 min-w-[980px] h-full">
            <aside class="border border-slate-200 rounded-sm overflow-y-auto self-start h-full">
              <div class="sticky top-0 z-20 px-3 py-1 bg-slate-100 text-xs font-semibold text-amber-600 border-b border-slate-200">Phòng trống</div>
              <div v-if="!groupedAvailableRooms.length" class="p-3 text-xs text-slate-500">Không có phòng trống</div>
              <div v-for="group in groupedAvailableRooms" :key="group.key" class="grid grid-cols-[55px_1fr] text-xs">
                <span
                  v-for="(room, index) in group.rooms"
                  :key="room.room_number"
                  class="px-2 py-1 text-center font-semibold border-t border-slate-200"
                  :style="{ gridColumn: '1', gridRow: String(index + 1) }"
                >{{ room.room_number }}</span>
                <span
                  class="px-2 py-1 border-l border-t border-slate-200 flex items-center justify-center text-center font-bold"
                  :style="{ gridColumn: '2', gridRow: `1 / span ${group.rooms.length}` }"
                >{{ group.name }}</span>
              </div>
            </aside>

            <div class="border border-slate-200 rounded-sm overflow-auto h-full">
              <table class="w-full border-collapse text-[11px]">
                <colgroup>
                  <col class="w-[64px]" /><col class="w-[112px]" /><col class="w-[76px]" />
                  <col class="w-[140px]" /><col class="w-[96px]" /><col class="w-[76px]" />
                  <col class="w-[76px]" /><col class="w-[70px]" /><col class="w-[74px]" />
                  <col class="w-[56px]" /><col class="w-[56px]" /><col class="min-w-[240px]" />
                </colgroup>
                <thead class="bg-slate-100 sticky top-0 z-20">
                  <tr>
                    <th class="p-1 border-r border-b">Trạng thái</th>
                    <th class="p-1 border-r border-b">Công ty</th>
                    <th class="p-1 border-r border-b">Mã ĐK</th>
                    <th class="p-1 border-r border-b">Tên ĐK</th>
                    <th class="p-1 border-r border-b">Trạng thái ĐK</th>
                    <th class="p-1 border-r border-b">Ngày đến</th>
                    <th class="p-1 border-r border-b">Ngày đi</th>
                    <th class="p-1 border-r border-b">Loại phòng</th>
                    <th class="p-1 border-r border-b">Giá</th>
                    <th class="p-1 border-r border-b">Phòng</th>
                    <th class="p-1 border-r border-b">SL khách</th>
                    <th class="p-1 border-b min-w-[200px]">Ghi chú</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!groupedBookingRooms.length">
                    <td colspan="12" class="p-6 text-center text-slate-500">Không có dữ liệu</td>
                  </tr>
                  <template v-for="group in groupedBookingRooms" :key="group.key">
                    <tr class="bg-slate-100 text-slate-800">
                      <td colspan="12" class="p-1 border-b border-slate-300">
                        <button type="button" class="inline-flex items-center gap-1 text-left font-bold" @click="toggleBooking(group.key)">
                          <span
                            class="inline-flex items-center justify-center w-3 h-3 text-[10px] leading-none"
                            style="background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #ffffff)"
                          >{{ isBookingCollapsed(group.key) ? '+' : '−' }}</span>
                          Mã ĐK: {{ group.bookingCode }}
                        </button>
                      </td>
                    </tr>
                    <template v-if="!isBookingCollapsed(group.key)">
                    <tr v-for="(row, rowIndex) in group.rooms" :key="`${row.booking_id}-${row.room_number}-${row.arrival_date}`" class="hover:bg-sky-50" @dblclick="openBooking(row)">
                      <td class="p-1 pl-3 border-r border-b">{{ row.room_status }}</td>
                      <td class="p-1 border-r border-b">{{ row.company || '' }}</td>
                      <td class="p-1 border-r border-b whitespace-nowrap">{{ row.booking_code || row.booking_id }}</td>
                      <td class="p-1 border-r border-b">{{ row.booking_name || '' }}</td>
                      <td class="p-1 border-r border-b">{{ row.registration_status || '' }}</td>
                      <td class="p-1 border-r border-b whitespace-nowrap">{{ formatDate(row.arrival_date) }}</td>
                      <td class="p-1 border-r border-b whitespace-nowrap">{{ formatDate(row.departure_date) }}</td>
                      <td class="p-1 border-r border-b">{{ row.room_class_code || '' }}</td>
                      <td class="p-1 border-r border-b text-right whitespace-nowrap">{{ formatAmount(row.rate) }}</td>
                      <td class="p-1 border-r border-b">{{ row.room_number || '' }}</td>
                      <td class="p-1 border-r border-b text-center">{{ row.guest_count }}</td>
                      <td v-if="rowIndex === 0" :rowspan="group.rooms.length" class="p-1 border-b whitespace-pre-wrap align-top">{{ group.bookingNote }}</td>
                    </tr>
                    <tr class="bg-white font-semibold text-slate-700">
                      <td colspan="9" class="p-1 border-b text-left pl-3">TỔNG</td>
                      <td class="p-1 border-r border-b text-center">{{ group.rooms.length }}</td>
                      <td class="p-1 border-r border-b text-center">{{ group.rooms.reduce((sum, row) => sum + Number(row.guest_count || 0), 0) }}</td>
                      <td class="p-1 border-b"></td>
                    </tr>
                    </template>
                  </template>
                </tbody>
                <tfoot class="sticky bottom-0 z-20">
                  <tr class="bg-slate-200 font-semibold">
                    <td colspan="9" class="p-1 border-r border-t text-right">TỔNG</td>
                    <td class="p-1 border-r border-t text-center">{{ detail?.totals?.booking_rooms || 0 }}</td>
                    <td class="p-1 border-r border-t text-center">{{ groupedBookingRooms.reduce((sum, group) => sum + group.rooms.reduce((groupSum, row) => groupSum + Number(row.guest_count || 0), 0), 0) }}</td>
                    <td class="p-1 border-t"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <footer class="flex justify-end gap-2 px-3 py-2 bg-white border-t border-slate-200">
          <button
            type="button"
            class="px-3 py-1.5 rounded text-xs hover:opacity-90"
            style="background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #ffffff)"
            @click="emit('close')"
          >Đóng</button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
