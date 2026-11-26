<script setup>
// Dòng 532: tab "Phòng noshow" ở màn Sang ngày.
// Liệt kê phòng noshow by date (one day / late check-in) và noshow by stage theo ngày noshow;
// tích chọn 1 phòng rồi bấm Charge No-Show để mở màn charge giống màn đăng ký.
import { ref, computed, onMounted } from 'vue'
import { Search, RefreshCw, UserX } from '@lucide/vue'
import { fetchNoshow } from '@/services/shift-work-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import ChargeNoshowModal from '@/pages/reservation/components/ChargeNoshowModal.vue'
import { splitNoshowRows, noshowRowKey, noshowSelectionError, noshowChargeModalProps } from '@/utils/noshow-room-list'

const uiStore = useUiStore()
const authStore = useAuthStore()

const fromDate = ref('')
const toDate = ref('')
const search = ref('')
const rows = ref([])
const isLoading = ref(false)
const selectedKeys = ref(new Set())

const showChargeModal = ref(false)
const chargeProps = ref({})

const groups = computed(() => {
  const { byDate, byStage } = splitNoshowRows(rows.value)
  return [
    { id: 'by-date', title: 'Noshow by date (one day / late check-in)', rows: byDate },
    { id: 'by-stage', title: 'Noshow by stage', rows: byStage },
  ]
})

const selectedRows = computed(() => rows.value.filter(row => selectedKeys.value.has(noshowRowKey(row))))

function formatMoney(value) {
  return Math.round(Number(value) || 0).toLocaleString('en-US')
}

function formatDmy(iso) {
  const [y, m, d] = String(iso || '').split('-')
  return y && m && d ? `${d}/${m}/${y}` : ''
}

async function load() {
  isLoading.value = true
  try {
    const res = await fetchNoshow({ from_date: fromDate.value, to_date: toDate.value || fromDate.value, search: search.value })
    rows.value = res.data?.data || []
    selectedKeys.value = new Set()
  } catch (err) {
    console.error('Lỗi tải danh sách phòng noshow:', err)
    uiStore.showToast(err.response?.data?.message || 'Không tải được danh sách phòng noshow.', 'error')
  } finally {
    isLoading.value = false
  }
}

function toggleRow(row) {
  const next = new Set(selectedKeys.value)
  const key = noshowRowKey(row)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  selectedKeys.value = next
}

function openCharge() {
  const message = noshowSelectionError(selectedRows.value)
  if (message) {
    uiStore.showToast(message, 'warning')
    return
  }
  chargeProps.value = noshowChargeModalProps(selectedRows.value[0])
  showChargeModal.value = true
}

onMounted(() => {
  const sys = String(authStore.systemDate || '').substring(0, 10)
  fromDate.value = sys
  toDate.value = sys
  load()
})
</script>

<template>
  <div class="flex flex-col gap-3 text-xs" data-testid="day-close-noshow-tab">
    <!-- Bộ lọc theo ngày noshow -->
    <div class="bg-white border border-gray-300 rounded-lg shadow-xs px-3 py-2 flex flex-wrap items-center gap-3">
      <span class="font-semibold text-[#000000D9]">Ngày noshow:</span>
      <div class="w-36"><SingleDatePicker v-model="fromDate" placeholder="dd/mm/yyyy" four-digit-year /></div>
      <span class="font-semibold text-[#000000D9]">đến</span>
      <div class="w-36"><SingleDatePicker v-model="toDate" placeholder="dd/mm/yyyy" four-digit-year /></div>
      <div class="relative">
        <Search class="w-3.5 h-3.5 text-gray-400 absolute left-2 top-1/2 -translate-y-1/2" />
        <input
          v-model="search"
          type="text"
          placeholder="Tìm mã BK, tên, số phòng, công ty..."
          class="h-8 w-64 border border-gray-300 rounded-lg pl-7 pr-2 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-blue-500"
          @keyup.enter="load"
        />
      </div>
      <button type="button" class="btn-pms-secondary h-8 px-3 text-xs flex items-center gap-1.5" data-testid="noshow-search" @click="load">
        <RefreshCw class="w-3.5 h-3.5" />
        <span>Tìm kiếm</span>
      </button>
      <button type="button" class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 ml-auto" data-testid="noshow-charge" @click="openCharge">
        <UserX class="w-3.5 h-3.5" />
        <span>Charge No-Show</span>
      </button>
    </div>

    <!-- Danh sách theo nhóm -->
    <div class="bg-white border border-gray-300 rounded-lg shadow-xs overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-[1100px]">
        <thead>
          <tr class="bg-[#e9ecef] text-gray-800 font-semibold border-b border-gray-300 text-xs">
            <th class="py-2.5 px-3 w-12 text-center border border-gray-300"></th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Phòng</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Mã BK</th>
            <th class="py-2.5 px-3 border border-gray-300">Tên đăng ký</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Loại phòng</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Ngày noshow</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Ngày đến</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Số đêm</th>
            <th class="py-2.5 px-3 text-right border border-gray-300">Giá phòng</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Tiền phòng</th>
            <th class="py-2.5 px-3 border border-gray-300">Lý do</th>
            <th class="py-2.5 px-3 text-center border border-gray-300">Người dùng</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in groups" :key="group.id">
            <tr class="bg-slate-100 font-semibold text-[#000000D9]" :data-testid="'noshow-group-' + group.id">
              <td colspan="12" class="py-2 px-3 border border-gray-300">{{ group.title }} ({{ group.rows.length }})</td>
            </tr>
            <tr v-if="group.rows.length === 0">
              <td colspan="12" class="py-3 px-3 text-center text-gray-400 italic border border-gray-200">Không có phòng noshow</td>
            </tr>
            <tr
              v-for="row in group.rows"
              :key="noshowRowKey(row)"
              class="hover:bg-sky-50/50 cursor-pointer text-[#000000D9] font-normal"
              :class="{ 'bg-sky-50': selectedKeys.has(noshowRowKey(row)) }"
              data-testid="noshow-row"
              @click="toggleRow(row)"
            >
              <td class="py-2 px-3 text-center border border-gray-200" @click.stop>
                <input type="checkbox" class="w-4 h-4 cursor-pointer" :checked="selectedKeys.has(noshowRowKey(row))" @change="toggleRow(row)" />
              </td>
              <td class="py-2 px-3 text-center font-semibold border border-gray-200">{{ row.roomNumber || 'Chưa gán' }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ row.id }}</td>
              <td class="py-2 px-3 border border-gray-200">{{ row.bookingName }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ row.roomType }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ formatDmy(row.noshowDateIso) || row.noshowDate }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ row.arrivalDate }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ row.nights }}</td>
              <td class="py-2 px-3 text-right border border-gray-200">{{ formatMoney(row.rate) }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">
                <span v-if="row.charged" class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">Đã charge</span>
                <span v-else class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 font-semibold">Chưa charge</span>
              </td>
              <td class="py-2 px-3 border border-gray-200">{{ row.reason }}</td>
              <td class="py-2 px-3 text-center border border-gray-200">{{ row.username }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Màn charge noshow dùng chung với màn đăng ký -->
    <ChargeNoshowModal
      v-model:show="showChargeModal"
      :booking-id="chargeProps.bookingId"
      :booking-name="chargeProps.bookingName"
      :guest-name="chargeProps.guestName"
      :room-id="chargeProps.roomId"
      :room-number="chargeProps.roomNumber"
      :arrival-date="chargeProps.arrivalDate"
      :departure-date="chargeProps.departureDate"
      :room-rate="chargeProps.roomRate"
      :default-date="chargeProps.defaultDate"
      @saved="load"
    />
  </div>
</template>
