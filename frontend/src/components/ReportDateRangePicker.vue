<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { CalendarDays, CheckCircle2 } from '@lucide/vue'
import { addLocalDays, localMonthRange, localQuarterRange, localYearRange } from '@/utils/report-date-range'

const props = defineProps({
  startDate: { type: String, default: '' },
  endDate: { type: String, default: '' },
  systemDate: { type: String, default: '' },
})

const emit = defineEmits(['update:startDate', 'update:endDate', 'change'])

const root = ref(null)
const open = ref(false)
const preset = ref('today')
const draftStart = ref('')
const draftEnd = ref('')
const calendarTarget = ref(null)
const calendarMonth = ref('')

const localToday = () => {
  if (/^\d{4}-\d{2}-\d{2}$/.test(props.systemDate)) return props.systemDate
  const date = new Date()
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

const presets = computed(() => {
  const today = localToday()
  const day = new Date(`${today}T00:00:00`).getDay()
  const mondayOffset = day === 0 ? -6 : 1 - day
  const weekStart = addLocalDays(today, mondayOffset)
  const previousWeekStart = addLocalDays(weekStart, -7)
  const nextWeekStart = addLocalDays(weekStart, 7)
  const [thisMonthStart, thisMonthEnd] = localMonthRange(today)
  const [previousMonthStart, previousMonthEnd] = localMonthRange(addLocalDays(thisMonthStart, -1))
  const [nextMonthStart, nextMonthEnd] = localMonthRange(addLocalDays(thisMonthEnd, 1))
  const [thisQuarterStart, thisQuarterEnd] = localQuarterRange(today)
  const [previousQuarterStart, previousQuarterEnd] = localQuarterRange(today, -1)
  const [nextQuarterStart, nextQuarterEnd] = localQuarterRange(today, 1)
  const [thisYearStart, thisYearEnd] = localYearRange(today)
  const [previousYearStart, previousYearEnd] = localYearRange(today, -1)
  const [nextYearStart, nextYearEnd] = localYearRange(today, 1)

  return [
    { value: 'today', label: 'Hôm nay', start: today, end: today },
    { value: 'this_week', label: 'Tuần này', start: weekStart, end: addLocalDays(weekStart, 6) },
    { value: 'this_month', label: 'Tháng này', start: thisMonthStart, end: thisMonthEnd },
    { value: 'this_quarter', label: 'Quý này', start: thisQuarterStart, end: thisQuarterEnd },
    { value: 'this_year', label: 'Năm nay', start: thisYearStart, end: thisYearEnd },
    { value: 'tomorrow', label: 'Ngày mai', start: addLocalDays(today, 1), end: addLocalDays(today, 1) },
    { value: 'next_week', label: 'Tuần tiếp theo', start: nextWeekStart, end: addLocalDays(nextWeekStart, 6) },
    { value: 'next_month', label: 'Tháng tiếp theo', start: nextMonthStart, end: nextMonthEnd },
    { value: 'next_quarter', label: 'Quý tiếp theo', start: nextQuarterStart, end: nextQuarterEnd },
    { value: 'next_year', label: 'Năm tiếp theo', start: nextYearStart, end: nextYearEnd },
    { value: 'yesterday', label: 'Hôm qua', start: addLocalDays(today, -1), end: addLocalDays(today, -1) },
    { value: 'previous_week', label: 'Tuần trước', start: previousWeekStart, end: addLocalDays(previousWeekStart, 6) },
    { value: 'previous_month', label: 'Tháng trước', start: previousMonthStart, end: previousMonthEnd },
    { value: 'previous_quarter', label: 'Quý trước', start: previousQuarterStart, end: previousQuarterEnd },
    { value: 'previous_year', label: 'Năm trước', start: previousYearStart, end: previousYearEnd },
    { value: 'custom', label: 'Tùy chỉnh', start: '', end: '' },
  ]
})

const formatDate = (value) => {
  if (!value) return '-- / -- / ----'
  const [year, month, day] = String(value).split('-')
  return year && month && day ? `${day} / ${month} / ${year}` : value
}

const matchingPreset = () => presets.value.find(item => item.start === props.startDate && item.end === props.endDate)?.value || 'custom'

const displayText = computed(() => {
  const selected = presets.value.find(item => item.value === matchingPreset())
  return selected && selected.value !== 'custom'
    ? selected.label
    : `${formatDate(props.startDate)}  ~  ${formatDate(props.endDate)}`
})

const syncDraft = () => {
  draftStart.value = props.startDate || localToday()
  draftEnd.value = props.endDate || draftStart.value
  preset.value = matchingPreset()
}

const selectPreset = () => {
  const item = presets.value.find(option => option.value === preset.value)
  if (!item || item.value === 'custom') return
  draftStart.value = item.start
  draftEnd.value = item.end
}

const markCustomDate = () => {
  preset.value = 'custom'
}

const calendarMonthLabel = computed(() => {
  if (!calendarMonth.value) return ''
  const [year, month] = calendarMonth.value.split('-').map(Number)
  return new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1))
})

const calendarCells = computed(() => {
  if (!calendarMonth.value) return []
  const [year, month] = calendarMonth.value.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1).getDay()
  const offset = firstDay === 0 ? 6 : firstDay - 1
  const daysInMonth = new Date(year, month, 0).getDate()
  return [...Array(offset).fill(null), ...Array.from({ length: daysInMonth }, (_, index) => index + 1)]
})

const calendarLeadingCells = computed(() => calendarCells.value.filter(day => day === null))
const calendarDays = computed(() => calendarCells.value.filter(day => day !== null))

const openCalendar = (target) => {
  calendarTarget.value = target
  const value = target === 'start' ? draftStart.value : draftEnd.value
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(String(value)) ? String(value) : localToday()
  calendarMonth.value = normalized.slice(0, 7)
}

const shiftCalendarMonth = (amount) => {
  const [year, month] = calendarMonth.value.split('-').map(Number)
  const date = new Date(year, month - 1 + amount, 1)
  calendarMonth.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

const chooseCalendarDay = (day) => {
  if (!day || !calendarTarget.value) return
  const [year, month] = calendarMonth.value.split('-').map(Number)
  const value = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  if (calendarTarget.value === 'start') draftStart.value = value
  else draftEnd.value = value
  markCustomDate()
  calendarTarget.value = null
}

const isCalendarDaySelected = (day) => {
  if (!calendarTarget.value || !calendarMonth.value || !day) return false
  const [year, month] = calendarMonth.value.split('-').map(Number)
  const value = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return (calendarTarget.value === 'start' ? draftStart.value : draftEnd.value) === value
}

const apply = () => {
  if (!draftStart.value || !draftEnd.value) return
  if (draftStart.value > draftEnd.value) {
    const start = draftStart.value
    draftStart.value = draftEnd.value
    draftEnd.value = start
  }
  emit('update:startDate', draftStart.value)
  emit('update:endDate', draftEnd.value)
  emit('change', { start: draftStart.value, end: draftEnd.value })
  open.value = false
}

const onDocumentClick = (event) => {
  if (root.value && !root.value.contains(event.target)) open.value = false
}

watch(() => [props.startDate, props.endDate, props.systemDate], syncDraft, { immediate: true })
onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <div ref="root" class="relative mt-1">
    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-xs font-medium text-slate-700 hover:border-sky-300" @click="open = !open">
      <span class="truncate">{{ displayText }}</span>
      <CalendarDays class="h-4 w-4 shrink-0 cursor-pointer text-sky-400" title="Mở bộ chọn ngày" @click.stop="open = true" />
    </button>

    <div v-if="open" class="absolute left-0 top-[calc(100%+6px)] z-30 w-[320px] rounded-lg border border-slate-300 bg-white p-3 shadow-xl" @click.stop>
      <label class="block text-xs font-bold text-slate-700">
        Phạm vi ngày
        <select v-model="preset" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @change="selectPreset">
          <option v-for="item in presets" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
      </label>

      <div class="mt-3 grid grid-cols-2 gap-2">
        <div class="text-[11px] font-bold text-slate-500">Từ ngày
          <span class="relative mt-1 block">
            <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-2 text-left text-xs" @click.stop="openCalendar('start')"><span>{{ formatDate(draftStart) }}</span><CalendarDays class="h-4 w-4 text-sky-500" /></button>
          </span>
        </div>
        <div class="text-[11px] font-bold text-slate-500">Đến ngày
          <span class="relative mt-1 block">
            <button type="button" class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-white px-2 py-2 text-left text-xs" @click.stop="openCalendar('end')"><span>{{ formatDate(draftEnd) }}</span><CalendarDays class="h-4 w-4 text-sky-500" /></button>
          </span>
        </div>
      </div>

      <div v-if="calendarTarget" class="mt-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg" @click.stop>
        <div class="mb-2 flex items-center justify-between">
          <button type="button" class="rounded border-none bg-transparent px-2 py-1 text-slate-500 hover:bg-slate-100" @click="shiftCalendarMonth(-1)">‹</button>
          <span class="text-xs font-bold capitalize text-slate-700">{{ calendarMonthLabel }}</span>
          <button type="button" class="rounded border-none bg-transparent px-2 py-1 text-slate-500 hover:bg-slate-100" @click="shiftCalendarMonth(1)">›</button>
        </div>
        <div class="mb-1 grid grid-cols-7 text-center text-[10px] font-bold text-slate-400"><span v-for="day in ['T2','T3','T4','T5','T6','T7','CN']" :key="day">{{ day }}</span></div>
        <div class="grid grid-cols-7 gap-1 text-center text-xs">
          <span v-for="(day, index) in calendarLeadingCells" :key="`${calendarMonth}-empty-${index}`" class="h-7"></span>
          <button v-for="day in calendarDays" :key="day" type="button" class="h-7 rounded border-none bg-transparent text-slate-700 hover:bg-sky-100" :class="isCalendarDaySelected(day) ? '!bg-sky-500 !text-white' : ''" @click="chooseCalendarDay(day)">{{ day }}</button>
        </div>
      </div>

      <div class="mt-3 flex justify-end border-t border-slate-200 pt-3">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border-none bg-blue-500 px-4 py-2 text-xs font-bold text-white hover:bg-blue-600" @click="apply">
          <CheckCircle2 class="h-4 w-4" /> Áp dụng
        </button>
      </div>
    </div>
  </div>
</template>
