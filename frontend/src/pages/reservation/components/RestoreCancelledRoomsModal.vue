<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
    @click="close"
  >
    <div
      class="bg-white rounded-xl shadow-2xl w-full max-w-[860px] max-h-[85vh] overflow-hidden border border-slate-200 flex flex-col"
      @click.stop
    >
      <!-- HEADER -->
      <div
        class="flex justify-between items-center px-4 py-3 shrink-0 select-none rounded-t-xl"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }"
      >
        <div class="flex items-center gap-2 font-semibold text-xs tracking-wider">
          <i class="fa-solid fa-rotate-left"></i>
          <span>Khôi Phục Phòng Đã Hủy</span>
        </div>
        <button
          class="hover:bg-white/20 p-1 rounded-md cursor-pointer border-none transition flex items-center justify-center"
          :style="{ color: 'inherit' }"
          @click="close"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <!-- BODY -->
      <div class="p-4 flex flex-col gap-3 text-xs text-[#000000D9] overflow-hidden">
        <div class="flex flex-wrap gap-x-8 gap-y-1">
          <div><span class="font-semibold">Mã đặt phòng:</span> {{ bookingCode }}</div>
          <div><span class="font-semibold">Tên đăng ký:</span> {{ bookingName }}</div>
        </div>

        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-800">
          <i class="fa-solid fa-circle-info mr-1.5"></i>
          Chỉ chọn những phòng cần khôi phục. Hệ thống kiểm tra lại số phòng, phòng khóa và tồn phòng theo loại phòng;
          nếu có một phòng không đủ điều kiện thì không khôi phục phòng nào.
        </div>

        <div class="flex items-center justify-between">
          <span class="font-semibold">Danh sách phòng đã hủy ({{ rooms.length }})</span>
          <button
            v-if="restorableRooms.length"
            type="button"
            class="text-sky-600 hover:underline font-semibold cursor-pointer bg-transparent border-none"
            @click="toggleAll"
          >
            {{ allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
          </button>
        </div>

        <div class="border border-slate-200 rounded-lg overflow-auto min-h-[120px]">
          <div v-if="isLoading" class="p-6 text-center text-slate-500">Đang tải danh sách phòng đã hủy...</div>
          <div v-else-if="!rooms.length" class="p-6 text-center text-slate-500">Đăng ký không có phòng đã hủy.</div>
          <table v-else class="w-full border-collapse text-xs">
            <thead class="bg-slate-50 sticky top-0">
              <tr>
                <th class="p-2 border-b border-slate-200 w-8 text-center">
                  <input type="checkbox" :checked="allSelected" @change="toggleAll" class="cursor-pointer" />
                </th>
                <th
                  v-for="col in columns"
                  :key="col.key"
                  class="p-2 border-b border-slate-200 text-left font-semibold whitespace-nowrap cursor-pointer select-none hover:text-sky-700"
                  @click="sortBy(col.key)"
                >
                  {{ col.label }}
                  <i
                    class="fa-solid ml-1 text-[11px]"
                    :class="sortKey === col.key ? (sortDirection === 'asc' ? 'fa-sort-up text-sky-600' : 'fa-sort-down text-sky-600') : 'fa-sort text-slate-300'"
                  ></i>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="room in sortedRooms"
                :key="room.id"
                class="border-b border-slate-100"
                :class="{
                  'hover:bg-sky-50/60 cursor-pointer': isRestorable(room),
                  'text-slate-400 bg-slate-50 cursor-not-allowed': !isRestorable(room),
                  'bg-sky-50': selectedIds.includes(room.id),
                  'bg-rose-50': ineligibleIds.has(room.id)
                }"
                :title="room.not_restorable_reason || ''"
                @click="toggleRoom(room.id)"
              >
                <td class="p-2 text-center" @click.stop>
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(room.id)"
                    :disabled="!isRestorable(room)"
                    @change="toggleRoom(room.id)"
                    :class="isRestorable(room) ? 'cursor-pointer' : 'cursor-not-allowed'"
                  />
                </td>
                <td class="p-2 font-semibold">{{ room.room_number || '—' }}</td>
                <td class="p-2">{{ room.room_class_code || '' }}</td>
                <td class="p-2">{{ room.room_class_name || '' }}</td>
                <td class="p-2 whitespace-nowrap">{{ formatDate(room.arrival_date) }}</td>
                <td class="p-2 whitespace-nowrap">{{ formatDate(room.departure_date) }}</td>
                <td class="p-2 whitespace-nowrap">{{ formatDateTime(room.cancelled_at) }}</td>
                <td class="p-2">
                  {{ room.cancelled_by || '' }}
                  <div v-if="!isRestorable(room)" class="text-rose-600 font-semibold whitespace-nowrap">{{ room.not_restorable_reason }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="errorLines.length" class="p-3 bg-rose-50 rounded-lg border border-rose-200 text-rose-700">
          <div class="font-semibold mb-1">Phòng không đủ điều kiện khôi phục:</div>
          <div v-for="line in errorLines" :key="line">- {{ line }}</div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="border-t border-slate-100 px-4 py-3 flex items-center justify-between shrink-0 rounded-b-xl">
        <span class="text-xs text-slate-600">Đã chọn {{ selectedIds.length }}/{{ restorableRooms.length }} phòng có thể khôi phục</span>
        <button
          v-if="!isLoading && rooms.length && !restorableRooms.length"
          class="btn-pms-primary h-8"
          :disabled="isSaving"
          @click="restoreBookingOnly"
        >
          <i class="fa-solid fa-rotate-left text-xs"></i>
          <span>{{ isSaving ? 'Đang khôi phục...' : 'Khôi phục thông tin đăng ký' }}</span>
        </button>
        <button
          v-else
          class="btn-pms-primary h-8"
          :disabled="!selectedIds.length || isSaving"
          @click="restoreSelected"
        >
          <i class="fa-solid fa-rotate-left text-xs"></i>
          <span>{{ isSaving ? 'Đang khôi phục...' : 'Khôi phục phòng đã chọn' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { fetchCancelledBookingRooms, restoreBooking } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { sortCancelledRooms } from '@/utils/booking-room-services'

const props = defineProps({
  show: Boolean,
  bookingId: [Number, String],
  bookingCode: String,
  bookingName: String,
})
const emit = defineEmits(['update:show', 'restored'])
const uiStore = useUiStore()

const columns = [
  { key: 'room_number', label: 'Số phòng' },
  { key: 'room_class_code', label: 'Mã loại phòng' },
  { key: 'room_class_name', label: 'Tên loại phòng' },
  { key: 'arrival_date', label: 'Ngày đến' },
  { key: 'departure_date', label: 'Ngày đi' },
  { key: 'cancelled_at', label: 'Ngày hủy' },
  { key: 'cancelled_by', label: 'Người hủy' },
]

const rooms = ref([])
const selectedIds = ref([])
const isLoading = ref(false)
const isSaving = ref(false)
const sortKey = ref('cancelled_at')
const sortDirection = ref('desc')
const errorLines = ref([])
const ineligibleIds = ref(new Set())

const sortedRooms = computed(() => sortCancelledRooms(rooms.value, sortKey.value, sortDirection.value))
// Phòng có ngày đến đã qua ngày hệ thống: hiển thị nhưng không cho chọn.
const isRestorable = room => room?.restorable !== false
const restorableRooms = computed(() => rooms.value.filter(isRestorable))
const allSelected = computed(() => restorableRooms.value.length > 0 && selectedIds.value.length === restorableRooms.value.length)

function onKeydown(event) {
  if (event.key === 'Escape' && props.show) close()
}

watch(() => props.show, async (visible) => {
  if (visible) {
    window.addEventListener('keydown', onKeydown)
    selectedIds.value = []
    errorLines.value = []
    ineligibleIds.value = new Set()
    sortKey.value = 'cancelled_at'
    sortDirection.value = 'desc'
    await loadRooms()
  } else {
    window.removeEventListener('keydown', onKeydown)
  }
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

async function loadRooms() {
  if (!props.bookingId) return
  isLoading.value = true
  try {
    const res = await fetchCancelledBookingRooms(props.bookingId)
    rooms.value = res.data?.data || []
  } catch (err) {
    console.error(err)
    rooms.value = []
    uiStore.showToast(err.response?.data?.message || 'Không tải được danh sách phòng đã hủy!', 'error')
  } finally {
    isLoading.value = false
  }
}

function sortBy(key) {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDirection.value = key === 'cancelled_at' ? 'desc' : 'asc'
  }
}

function toggleRoom(id) {
  const room = rooms.value.find(item => item.id === id)
  if (!isRestorable(room)) return
  const index = selectedIds.value.indexOf(id)
  if (index === -1) selectedIds.value.push(id)
  else selectedIds.value.splice(index, 1)
}

function toggleAll() {
  selectedIds.value = allSelected.value ? [] : restorableRooms.value.map(room => room.id)
}

function markIneligible(lines, roomIds = []) {
  errorLines.value = lines
  ineligibleIds.value = new Set(roomIds)
}

// Không còn phòng nào khôi phục được (đều đã qua ngày đến): chỉ khôi phục thông tin đăng ký.
async function restoreBookingOnly() {
  if (isSaving.value) return
  const confirmed = await uiStore.confirm({
    title: 'Khôi phục thông tin đăng ký',
    message: 'Tất cả phòng đã hủy đều có ngày đến nhỏ hơn ngày hệ thống nên không thể khôi phục phòng. Bạn có muốn chỉ khôi phục thông tin đăng ký?',
    confirmText: 'Có',
    cancelText: 'Không',
  })
  if (!confirmed) return
  isSaving.value = true
  try {
    const res = await restoreBooking(props.bookingId)
    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Khôi phục đăng ký thành công!', 'success')
      emit('restored', res.data)
      close()
    } else {
      uiStore.showToast(res.data?.message || 'Khôi phục đăng ký thất bại!', 'error')
    }
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Khôi phục đăng ký thất bại!', 'error')
  } finally {
    isSaving.value = false
  }
}

async function restoreSelected() {
  if (!selectedIds.value.length || isSaving.value) return
  isSaving.value = true
  errorLines.value = []
  ineligibleIds.value = new Set()
  const payload = { room_ids: [...selectedIds.value] }
  try {
    let res = await restoreBooking(props.bookingId, payload)
    // Dòng 294: trùng số phòng với đăng ký khác hoặc phòng khóa -> Có thì xóa số phòng trùng rồi khôi phục.
    if (res.data?.needs_duplicate_confirm) {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo trùng số phòng',
        message: res.data.message,
        confirmText: 'Có',
        cancelText: 'Không',
      })
      if (!confirmed) {
        uiStore.showToast('Đã hủy khôi phục, đăng ký giữ nguyên.', 'info')
        return
      }
      payload.clear_duplicate_rooms = true
      res = await restoreBooking(props.bookingId, payload)
    }
    if (res.data?.needs_over_confirm || res.data?.needs_confirm) {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo Over phòng',
        message: res.data.message || 'Loại phòng đang bị over bạn có muốn tiếp tục',
        confirmText: 'Có',
        cancelText: 'Không',
      })
      if (!confirmed) {
        uiStore.showToast('Đã hủy khôi phục, đăng ký giữ nguyên.', 'info')
        return
      }
      res = await restoreBooking(props.bookingId, { ...payload, force: true, force_over: true })
    }

    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Khôi phục phòng thành công!', 'success')
      emit('restored', res.data)
      close()
    } else {
      uiStore.showToast(res.data?.message || 'Khôi phục phòng thất bại!', 'error')
    }
  } catch (err) {
    const data = err.response?.data || {}
    if (Array.isArray(data.ineligible_rooms) && data.ineligible_rooms.length) {
      markIneligible(data.ineligible_rooms, data.ineligible_room_ids || [])
      uiStore.showToast('Có phòng không đủ điều kiện khôi phục, chưa khôi phục phòng nào.', 'error')
    } else {
      uiStore.showToast(data.message || 'Khôi phục phòng thất bại!', 'error')
    }
  } finally {
    isSaving.value = false
  }
}

function formatDate(value) {
  if (!value) return ''
  const [y, m, d] = String(value).slice(0, 10).split('-')
  return y && m && d ? `${d}/${m}/${y}` : value
}

function formatDateTime(value) {
  if (!value) return ''
  const [datePart, timePart = ''] = String(value).split(' ')
  return `${formatDate(datePart)} ${timePart.slice(0, 5)}`.trim()
}

function close() {
  emit('update:show', false)
}
</script>
