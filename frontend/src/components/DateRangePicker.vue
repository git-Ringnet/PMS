<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'

const props = defineProps({
  startDate: {
    type: String,
    required: true
  },
  endDate: {
    type: String,
    required: true
  },
  systemDate: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:startDate', 'update:endDate', 'change'])

// States
const selectedRangeKey = ref('custom')
const localStartDate = ref(null)
const localEndDate = ref(null)
const isDark = ref(false)

// Formatting helpers
const parseYMD = (ymdStr) => {
  if (!ymdStr) return new Date()
  const [y, m, d] = ymdStr.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const formatDateYMD = (date) => {
  if (!date) return ''
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateDMY = (ymdStr) => {
  if (!ymdStr) return ''
  const parts = ymdStr.split('-')
  if (parts.length !== 3) return ymdStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

// Preset range calculations
const getRanges = () => {
  const today = props.systemDate ? parseYMD(props.systemDate) : new Date()
  const getStartOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const todayStart = getStartOfDay(today)

  const todayStr = formatDateYMD(todayStart)

  const yesterday = new Date(todayStart)
  yesterday.setDate(todayStart.getDate() - 1)
  const yesterdayStr = formatDateYMD(yesterday)

  const tomorrow = new Date(todayStart)
  tomorrow.setDate(todayStart.getDate() + 1)
  const tomorrowStr = formatDateYMD(tomorrow)

  const day = todayStart.getDay()
  const diffToMonday = day === 0 ? -6 : 1 - day
  const thisMonday = new Date(todayStart)
  thisMonday.setDate(todayStart.getDate() + diffToMonday)
  const thisSunday = new Date(thisMonday)
  thisSunday.setDate(thisMonday.getDate() + 6)

  const lastMonday = new Date(thisMonday)
  lastMonday.setDate(thisMonday.getDate() - 7)
  const lastSunday = new Date(lastMonday)
  lastSunday.setDate(lastMonday.getDate() + 6)

  const nextMonday = new Date(thisMonday)
  nextMonday.setDate(thisMonday.getDate() + 7)
  const nextSunday = new Date(nextMonday)
  nextSunday.setDate(nextMonday.getDate() + 6)

  const thisMonthStart = new Date(todayStart.getFullYear(), todayStart.getMonth(), 1)
  const thisMonthEnd = new Date(todayStart.getFullYear(), todayStart.getMonth() + 1, 0)

  const lastMonthStart = new Date(todayStart.getFullYear(), todayStart.getMonth() - 1, 1)
  const lastMonthEnd = new Date(todayStart.getFullYear(), todayStart.getMonth(), 0)

  const nextMonthStart = new Date(todayStart.getFullYear(), todayStart.getMonth() + 1, 1)
  const nextMonthEnd = new Date(todayStart.getFullYear(), todayStart.getMonth() + 2, 0)

  return {
    today: [todayStr, todayStr],
    thisWeek: [formatDateYMD(thisMonday), formatDateYMD(thisSunday)],
    thisMonth: [formatDateYMD(thisMonthStart), formatDateYMD(thisMonthEnd)],
    tomorrow: [tomorrowStr, tomorrowStr],
    nextWeek: [formatDateYMD(nextMonday), formatDateYMD(nextSunday)],
    nextMonth: [formatDateYMD(nextMonthStart), formatDateYMD(nextMonthEnd)],
    yesterday: [yesterdayStr, yesterdayStr],
    lastWeek: [formatDateYMD(lastMonday), formatDateYMD(lastSunday)],
    lastMonth: [formatDateYMD(lastMonthStart), formatDateYMD(lastMonthEnd)]
  }
}

// Map key to label for preset options
const rangePresets = [
  { key: 'today', label: 'Hôm nay' },
  { key: 'thisWeek', label: 'Tuần này' },
  { key: 'thisMonth', label: 'Tháng này' },
  { key: 'tomorrow', label: 'Ngày mai' },
  { key: 'nextWeek', label: 'Tuần tiếp theo' },
  { key: 'nextMonth', label: 'Tháng tiếp theo' },
  { key: 'yesterday', label: 'Hôm qua' },
  { key: 'lastWeek', label: 'Tuần trước' },
  { key: 'lastMonth', label: 'Tháng trước' },
  { key: 'custom', label: 'Tùy chỉnh' }
]

// Initialize selectedRangeKey based on props
const updatePresetSelection = () => {
  const ranges = getRanges()
  const found = Object.keys(ranges).find(
    (key) => ranges[key][0] === props.startDate && ranges[key][1] === props.endDate
  )
  if (found) {
    selectedRangeKey.value = found
  } else {
    selectedRangeKey.value = 'custom'
  }
  localStartDate.value = parseYMD(props.startDate)
  localEndDate.value = parseYMD(props.endDate)
}

watch([() => props.startDate, () => props.endDate], () => {
  updatePresetSelection()
}, { immediate: true })

// Dark mode tracking
let observer = null
onMounted(() => {
  updatePresetSelection()

  // Track dark mode class
  isDark.value = document.documentElement.classList.contains('dark')
  observer = new MutationObserver(() => {
    isDark.value = document.documentElement.classList.contains('dark')
  })
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  })
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})

// Triggered when dropdown preset selection changes
const handlePresetChange = () => {
  if (selectedRangeKey.value !== 'custom') {
    const ranges = getRanges()
    const [start, end] = ranges[selectedRangeKey.value]
    localStartDate.value = parseYMD(start)
    localEndDate.value = parseYMD(end)
    emit('update:startDate', start)
    emit('update:endDate', end)
    emit('change', { start, end })
  }
}

// When calendar selection changes
const onStartDateChange = (date) => {
  if (date) {
    selectedRangeKey.value = 'custom'
  }
}

const onEndDateChange = (date) => {
  if (date) {
    selectedRangeKey.value = 'custom'
  }
}

// Apply the custom date picker selection
const applyRange = () => {
  if (localStartDate.value && localEndDate.value) {
    const start = formatDateYMD(localStartDate.value)
    const end = formatDateYMD(localEndDate.value)
    emit('update:startDate', start)
    emit('update:endDate', end)
    emit('change', { start, end })
  }
}
</script>

<template>
  <div class="flex items-center gap-2">
    <!-- Presets Dropdown -->
    <div class="flex items-center border border-slate-200 dark:border-zinc-800 rounded-lg bg-slate-50 dark:bg-zinc-900 px-3 py-1 shadow-sm h-[32px]">
      <span class="text-gray-400 dark:text-zinc-500 mr-2 text-[11px] font-semibold whitespace-nowrap uppercase tracking-wider">Phạm vi ngày</span>
      <select
        v-model="selectedRangeKey"
        @change="handlePresetChange"
        class="bg-transparent border-none focus:outline-none text-xs font-semibold text-gray-900 dark:text-white cursor-pointer w-[120px]"
      >
        <option v-for="preset in rangePresets" :key="preset.key" :value="preset.key">
          {{ preset.label }}
        </option>
      </select>
    </div>

    <!-- Start Date Input -->
    <div class="flex items-center border border-slate-200 dark:border-zinc-800 rounded-lg bg-slate-50 dark:bg-zinc-900 px-3 py-1 shadow-sm h-[32px] w-[140px]">
      <span class="text-gray-400 dark:text-zinc-500 mr-1 text-[11px] font-semibold whitespace-nowrap">Từ:</span>
      <VueDatePicker
        v-model="localStartDate"
        :locale="vi"
        :dark="isDark"
        :enable-time-picker="false"
        auto-apply
        @update:model-value="onStartDateChange"
        class="custom-datepicker-single"
      >
        <template #trigger>
          <div class="flex items-center justify-between w-full cursor-pointer text-xs font-semibold text-gray-900 dark:text-white select-none">
            <span>{{ formatDateDMY(formatDateYMD(localStartDate)) }}</span>
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-500 shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
        </template>
      </VueDatePicker>
    </div>

    <!-- End Date Input -->
    <div class="flex items-center border border-slate-200 dark:border-zinc-800 rounded-lg bg-slate-50 dark:bg-zinc-900 px-3 py-1 shadow-sm h-[32px] w-[140px]">
      <span class="text-gray-400 dark:text-zinc-500 mr-1 text-[11px] font-semibold whitespace-nowrap">Đến:</span>
      <VueDatePicker
        v-model="localEndDate"
        :locale="vi"
        :dark="isDark"
        :enable-time-picker="false"
        auto-apply
        @update:model-value="onEndDateChange"
        class="custom-datepicker-single"
      >
        <template #trigger>
          <div class="flex items-center justify-between w-full cursor-pointer text-xs font-semibold text-gray-900 dark:text-white select-none">
            <span>{{ formatDateDMY(formatDateYMD(localEndDate)) }}</span>
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-500 shrink-0 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </div>
        </template>
      </VueDatePicker>
    </div>

    <!-- Apply Button -->
    <button
      type="button"
      @click="applyRange"
      class="px-3.5 py-1 bg-[#8ecefa] hover:bg-[#72b5f7] dark:bg-blue-600 dark:hover:bg-blue-700 text-slate-800 dark:text-white text-xs font-bold rounded-lg shadow-sm cursor-pointer transition-all border border-[#7ec0f3] dark:border-blue-700 flex items-center justify-center gap-1.5 h-[32px]"
    >
      <span class="w-3.5 h-3.5 rounded-full border border-current flex items-center justify-center shrink-0">
        <svg class="w-2 h-2" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12" />
        </svg>
      </span>
      Áp dụng
    </button>
  </div>
</template>

<style>
/* Custom styled overrides for vue-datepicker to match theme */
.dp__theme_light {
  --dp-primary-color: #3b82f6 !important;
  --dp-hover-color: #f1f5f9 !important;
  --dp-hover-text-color: #1e293b !important;
  --dp-hover-icon-color: #94a3b8 !important;
  --dp-primary-text-color: #ffffff !important;
  --dp-secondary-color: #cbd5e1 !important;
  --dp-border-color: #e2e8f0 !important;
  --dp-menu-border-color: #e2e8f0 !important;
}

.dp__theme_dark {
  --dp-primary-color: #0096ff !important;
  --dp-hover-color: #121212 !important;
  --dp-hover-text-color: #ffffff !important;
  --dp-hover-icon-color: #71717a !important;
  --dp-primary-text-color: #ffffff !important;
  --dp-secondary-color: #1c1c1c !important;
  --dp-border-color: #1c1c1c !important;
  --dp-menu-border-color: #1c1c1c !important;
  --dp-background-color: #080808 !important;
  --dp-text-color: #ffffff !important;
}

.dp__menu {
  border-radius: 12px !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
  border: 1px solid var(--dp-border-color) !important;
  font-family: inherit !important;
}

.dp__calendar_header_item {
  font-weight: 600 !important;
  font-size: 11px !important;
}

.dp__cell_inner {
  font-size: 12px !important;
  border-radius: 6px !important;
}
</style>
