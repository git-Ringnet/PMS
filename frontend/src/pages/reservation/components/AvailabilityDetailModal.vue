<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

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
  BBC: 'BBC',
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
  return Number(value).toLocaleString('en-US')
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

function handleKeyDown(event) {
  if (event.key === 'Escape') {
    emit('close')
  }
}

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      resetPosition()
      window.addEventListener('keydown', handleKeyDown)
    } else {
      window.removeEventListener('keydown', handleKeyDown)
    }
  },
  { immediate: true }
)

onMounted(() => {
  resetPosition()
  if (props.show) {
    window.addEventListener('keydown', handleKeyDown)
  }
})

onUnmounted(() => {
  endDrag()
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] bg-slate-950/55 flex items-center justify-center p-3">
      <section
        class="w-[min(1700px,98vw)] h-[78vh] max-h-[78vh] overflow-hidden rounded-xl bg-white shadow-2xl border border-slate-300 font-sans flex flex-col"
        :style="{ transform: `translate(${position.x}px, ${position.y}px)` }"
      >
        <header
          class="h-10 shrink-0 flex items-center justify-between px-4 cursor-move select-none rounded-t-xl"
          style="background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #ffffff)"
          @pointerdown="beginDrag"
        >
          <div class="text-xs font-semibold tracking-wide">
            {{ metricLabel }} — {{ roomClassLabel || 'Tất cả loại phòng' }} — {{ formatDate(date) }}
          </div>
          <button
            type="button"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/20 transition-colors text-white text-xl leading-none"
            title="Đóng (Esc)"
            @click="emit('close')"
          >
            ×
          </button>
        </header>

        <div v-if="loading" class="flex-1 flex items-center justify-center text-xs text-slate-500">Đang tải dữ liệu...</div>
        <div v-else-if="error" class="flex-1 flex items-center justify-center text-xs text-red-600">{{ error }}</div>
        <div v-else class="flex-1 min-h-0 p-2 overflow-hidden text-xs rounded-b-xl bg-white">
          <div class="grid grid-cols-[190px_minmax(760px,1fr)] gap-2 min-w-[980px] h-full">
            <aside class="border border-slate-200 rounded-lg overflow-y-auto self-start h-full">
              <div class="sticky top-0 z-20 px-3 py-1.5 bg-slate-100 text-xs font-semibold text-amber-600 border-b border-slate-200">Phòng trống</div>
              <div v-if="!groupedAvailableRooms.length" class="p-3 text-xs text-[#A8B0BF]">Không có phòng trống</div>
              <div v-for="group in groupedAvailableRooms" :key="group.key" class="grid grid-cols-[55px_1fr] text-xs">
                <span
                  v-for="(room, index) in group.rooms"
                  :key="room.room_number"
                  class="px-2 py-1 text-center font-normal border-t border-slate-200 text-[#000000D9]"
                  :style="{ gridColumn: '1', gridRow: String(index + 1) }"
                >{{ room.room_number }}</span>
                <span
                  class="px-2 py-1 border-l border-t border-slate-200 flex items-center justify-center text-center font-semibold text-[#000000D9]"
                  :style="{ gridColumn: '2', gridRow: `1 / span ${group.rooms.length}` }"
                >{{ group.name }}</span>
              </div>
            </aside>

            <div class="border border-slate-200 rounded-lg overflow-auto h-full">
              <table class="w-full table-fixed border-collapse text-xs">
                <colgroup>
                  <col class="w-[64px]" /><col class="w-[112px]" /><col class="w-[76px]" />
                  <col class="w-[140px]" /><col class="w-[96px]" /><col class="w-[76px]" />
                  <col class="w-[76px]" /><col class="w-[70px]" /><col class="w-[74px]" />
                  <col class="w-[56px]" /><col class="w-[56px]" /><col class="w-[280px]" />
                </colgroup>
                <thead class="bg-slate-100 sticky top-0 z-20 text-[#000000D9] font-semibold">
                  <tr>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Trạng Thái</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Công Ty</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Mã ĐK</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Tên ĐK</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Trạng Thái ĐK</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Ngày Đến</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Ngày Đi</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Loại Phòng</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Giá</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Phòng</th>
                    <th class="p-1 border border-slate-200 text-center align-middle font-semibold text-xs">SL Khách</th>
                    <th class="w-[280px] p-1 border border-slate-200 text-center align-middle font-semibold text-xs">Ghi Chú</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!groupedBookingRooms.length">
                    <td colspan="12" class="p-6 text-center text-[#A8B0BF] border-b border-slate-200">Không có dữ liệu</td>
                  </tr>
                  <template v-for="group in groupedBookingRooms" :key="group.key">
                    <tr class="bg-slate-50 text-[#000000D9]">
                      <td colspan="12" class="p-1 border-b border-slate-200">
                        <button type="button" class="inline-flex items-center gap-1.5 text-left font-semibold text-xs" @click="toggleBooking(group.key)">
                          <span
                            class="inline-flex items-center justify-center w-3.5 h-3.5 text-xs leading-none rounded-[2px]"
                            style="background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #ffffff)"
                          >{{ isBookingCollapsed(group.key) ? '+' : '−' }}</span>
                          <span>Mã ĐK: {{ group.bookingCode }}</span>
                        </button>
                      </td>
                    </tr>
                    <template v-if="!isBookingCollapsed(group.key)">
                    <tr
                      v-for="(row, rowIndex) in group.rooms"
                      :key="`${row.booking_id}-${row.room_number}-${row.arrival_date}`"
                      class="hover:bg-sky-50 text-[#000000D9] transition-colors"
                      @dblclick="openBooking(row)"
                    >
                      <td class="p-1 pl-3 border-b border-slate-200 font-normal">{{ row.room_status }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal">{{ row.company || '' }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal whitespace-nowrap">{{ row.booking_code || row.booking_id }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal">{{ row.booking_name || '' }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal">{{ row.registration_status || '' }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal whitespace-nowrap">{{ formatDate(row.arrival_date) }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal whitespace-nowrap">{{ formatDate(row.departure_date) }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal">{{ row.room_class_code || '' }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal text-right whitespace-nowrap tabular-nums">{{ formatAmount(row.rate) }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal text-center">{{ row.room_number || '' }}</td>
                      <td class="p-1 border-b border-slate-200 font-normal text-center tabular-nums">{{ row.guest_count }}</td>
                      <td v-if="rowIndex === 0" :rowspan="group.rooms.length" class="w-[280px] p-1 border-b border-slate-200 break-words whitespace-pre-wrap align-top font-normal">{{ group.bookingNote }}</td>
                    </tr>
                    <tr class="bg-white font-semibold text-[#000000D9]">
                      <td colspan="9" class="p-1 border-b border-slate-200 text-left pl-3">TỔNG</td>
                      <td class="p-1 border-b border-slate-200 text-center tabular-nums">{{ group.rooms.length }}</td>
                      <td class="p-1 border-b border-slate-200 text-center tabular-nums">{{ group.rooms.reduce((sum, row) => sum + Number(row.guest_count || 0), 0) }}</td>
                      <td class="p-1 border-b border-slate-200"></td>
                    </tr>
                    </template>
                  </template>
                </tbody>
                <tfoot class="sticky bottom-0 z-20">
                  <tr class="bg-slate-100 font-semibold text-[#000000D9]">
                    <td colspan="9" class="p-1 border-t border-b border-slate-300 text-right pr-2">TỔNG</td>
                    <td class="p-1 border-t border-b border-slate-300 text-center tabular-nums">{{ detail?.totals?.booking_rooms || 0 }}</td>
                    <td class="p-1 border-t border-b border-slate-300 text-center tabular-nums">{{ groupedBookingRooms.reduce((sum, group) => sum + group.rooms.reduce((groupSum, row) => groupSum + Number(row.guest_count || 0), 0), 0) }}</td>
                    <td class="p-1 border-t border-b border-slate-300"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>
