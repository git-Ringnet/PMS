<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { useAuthStore } from '@/stores/auth-store'
import { useDraggableModal } from '@/composables/useDraggableModal'
import { fetchAlerts, updateAlertStatus } from '@/services/alert-service'

defineProps({
  isDark: { type: Boolean, default: true }
})

const route = useRoute()
const authStore = useAuthStore()
const { position: modalPos, isDragging, startDrag, resetPosition } = useDraggableModal()

// Màu tiêu đề modal theo Tùy chỉnh màu nền Topbar
const themeBg = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')
const isOpen = ref(false)
const isLoading = ref(false)
const fromDate = ref('')
const toDate = ref('')
const rows = ref([])
const errorMessage = ref('')

const STATUS_OPTIONS = [
  { value: 0, label: 'Chưa xử lý' },
  { value: 1, label: 'Đã xử lý' }
]

const groups = computed(() => STATUS_OPTIONS
  .map(option => ({ ...option, items: rows.value.filter(row => row.status === option.value) }))
  .filter(group => group.items.length > 0))

const collapsedGroups = ref({})

const formatYmd = (value) => {
  const [y, m, d] = String(value || '').split('-')
  return y && m && d ? `${d}/${m}/${y}` : ''
}

async function loadAlerts({ silent = false } = {}) {
  if (!silent) isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await fetchAlerts({
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined
    })
    rows.value = res.data?.data || []
    fromDate.value = res.data?.meta?.from_date || fromDate.value
    toDate.value = res.data?.meta?.to_date || toDate.value
    return res.data?.meta || null
  } catch (error) {
    rows.value = []
    errorMessage.value = error?.response?.data?.message || 'Không tải được danh sách sự kiện.'
    return null
  } finally {
    isLoading.value = false
  }
}

async function openAlerts() {
  resetPosition()
  isOpen.value = true
  await loadAlerts()
}

async function changeStatus(row, event) {
  const next = Number(event.target.value)
  const previous = row.status
  row.status = next
  try {
    await updateAlertStatus(row.eventKey, next)
    row.statusLabel = STATUS_OPTIONS.find(option => option.value === next)?.label
  } catch (error) {
    row.status = previous
    event.target.value = previous
    errorMessage.value = error?.response?.data?.message || 'Không cập nhật được tình trạng.'
  }
}

// Tự động hiện popup khi vào module (theo thông số AllowShowAlertListView), mỗi module một lần mỗi phiên
const moduleKey = computed(() => String(route.path || '').split('/')[1] || '')
const AUTO_KEYS_STORAGE = 'pms_alert_auto_shown'

function wasAutoShown(key) {
  try {
    return JSON.parse(sessionStorage.getItem(AUTO_KEYS_STORAGE) || '[]').includes(key)
  } catch {
    return false
  }
}

function markAutoShown(key) {
  try {
    const keys = JSON.parse(sessionStorage.getItem(AUTO_KEYS_STORAGE) || '[]')
    sessionStorage.setItem(AUTO_KEYS_STORAGE, JSON.stringify([...new Set([...keys, key])]))
  } catch {
    // sessionStorage không dùng được thì bỏ qua, popup chỉ hiện lại ở lần vào sau
  }
}

async function maybeAutoOpen() {
  const key = moduleKey.value
  if (!['reservation', 'frontdesk', 'housekeeping', 'reports'].includes(key) || wasAutoShown(key)) return
  markAutoShown(key)
  const meta = await loadAlerts({ silent: true })
  if (meta?.auto_popup && meta.pending > 0) {
    resetPosition()
    isOpen.value = true
  }
}

onMounted(maybeAutoOpen)
watch(moduleKey, maybeAutoOpen)
</script>

<template>
  <button
    type="button"
    title="Danh sách sự kiện, sinh nhật khách"
    @click="openAlerts"
    class="p-0.5 rounded bg-transparent border-none cursor-pointer flex items-center justify-center shrink-0 transition-colors duration-200"
    :class="isDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/10'"
  >
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" />
    </svg>
  </button>

  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 z-[200] flex items-start justify-center bg-black/20 pt-20" @mousedown.self="isOpen = false">
      <div
        class="bg-white rounded-xl shadow-2xl w-[min(1100px,94vw)] max-h-[75vh] flex flex-col overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)`, transition: isDragging ? 'none' : '' }"
      >
        <div
          class="flex items-center justify-between px-4 h-10 text-white text-xs font-bold shrink-0 select-none cursor-move"
          :style="{ background: themeBg }"
          @mousedown="startDrag"
        >
          <span>Danh sách sự kiện, sinh nhật khách</span>
          <button type="button" class="border-none bg-transparent text-white cursor-pointer text-lg leading-none hover:bg-white/15 rounded px-1" @click="isOpen = false">&times;</button>
        </div>

        <div class="flex items-center justify-end gap-2 px-4 py-3 shrink-0 text-xs">
          <div class="flex items-center gap-1">
            <div class="w-[150px]"><SingleDatePicker v-model="fromDate" /></div>
            <span class="text-slate-400">~</span>
            <div class="w-[150px]"><SingleDatePicker v-model="toDate" :min-date="fromDate" /></div>
          </div>
          <button
            type="button"
            class="h-8 px-5 rounded-lg text-white font-semibold border-none cursor-pointer hover:opacity-90"
            :style="{ background: themeBg }"
            :disabled="isLoading"
            @click="loadAlerts()"
          >
            Xem
          </button>
        </div>

        <p v-if="errorMessage" class="px-4 pb-2 text-xs text-red-600">{{ errorMessage }}</p>

        <div class="overflow-auto px-4 pb-4 flex-1">
          <table class="w-full border-collapse text-xs table-fixed min-w-[900px]">
            <thead>
              <tr class="bg-slate-100 text-slate-800 font-semibold h-8 sticky top-0">
                <th class="border border-slate-200 w-[90px]">Mã ĐK</th>
                <th class="border border-slate-200 w-[70px]">Phòng</th>
                <th class="border border-slate-200">Tên đăng ký</th>
                <th class="border border-slate-200 w-[170px]">Tên khách</th>
                <th class="border border-slate-200 w-[95px]">Ngày sinh</th>
                <th class="border border-slate-200 w-[95px]">Ngày đến</th>
                <th class="border border-slate-200 w-[95px]">Ngày đi</th>
                <th class="border border-slate-200 w-[170px]">Loại sự kiện</th>
                <th class="border border-slate-200 w-[130px]">Tình trạng</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!isLoading && rows.length === 0">
                <td colspan="9" class="p-6 text-center text-slate-400">
                  Không có sinh nhật hoặc yêu cầu đặc biệt từ {{ formatYmd(fromDate) }} đến {{ formatYmd(toDate) }}.
                </td>
              </tr>
              <template v-for="group in groups" :key="group.value">
                <tr class="bg-white">
                  <td colspan="9" class="border border-slate-200 px-2 h-8 font-semibold">
                    <button
                      type="button"
                      class="w-4 h-4 mr-2 rounded bg-[#c9eeff] text-[#0369a1] font-bold border-none cursor-pointer leading-none"
                      @click="collapsedGroups[group.value] = !collapsedGroups[group.value]"
                    >{{ collapsedGroups[group.value] ? '+' : '-' }}</button>
                    {{ group.label }} ({{ group.items.length }})
                  </td>
                </tr>
                <template v-if="!collapsedGroups[group.value]">
                  <tr v-for="row in group.items" :key="row.eventKey" class="h-8 hover:bg-slate-50">
                    <td class="border border-slate-200 px-2">{{ row.bookingCode }}</td>
                    <td class="border border-slate-200 px-2 text-center">{{ row.roomNumber }}</td>
                    <td class="border border-slate-200 px-2 truncate" :title="row.bookingName">{{ row.bookingName }}</td>
                    <td class="border border-slate-200 px-2 truncate" :title="row.guestName">{{ row.guestName }}</td>
                    <td class="border border-slate-200 px-2 text-center">{{ row.birthday }}</td>
                    <td class="border border-slate-200 px-2 text-center">{{ row.arrivalDate }}</td>
                    <td class="border border-slate-200 px-2 text-center">{{ row.departureDate }}</td>
                    <td class="border border-slate-200 px-2 truncate" :title="row.eventName">{{ row.eventName }}</td>
                    <td class="border border-slate-200 px-1">
                      <select
                        :value="row.status"
                        class="w-full h-7 border border-slate-200 rounded text-xs bg-white"
                        @change="changeStatus(row, $event)"
                      >
                        <option v-for="option in STATUS_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                      </select>
                    </td>
                  </tr>
                </template>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </Teleport>
</template>
