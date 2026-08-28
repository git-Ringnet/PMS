<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { CalendarDays, CheckCircle2 } from '@lucide/vue'

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

const toYmd = (date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const localToday = () => {
  if (/^\d{4}-\d{2}-\d{2}$/.test(props.systemDate)) return props.systemDate
  return toYmd(new Date())
}

const addDays = (dateString, days) => {
  const date = new Date(`${dateString}T00:00:00`)
  date.setDate(date.getDate() + days)
  return toYmd(date)
}

const presets = computed(() => {
  const today = localToday()
  const baseDate = new Date(`${today}T00:00:00`)
  const year = baseDate.getFullYear()
  const month = baseDate.getMonth()

  const day = baseDate.getDay()
  const mondayOffset = day === 0 ? -6 : 1 - day
  const weekStart = addDays(today, mondayOffset)

  const qStartMonth = Math.floor(month / 3) * 3

  return [
    { value: 'today', label: 'Hôm nay', start: today, end: today },
    { value: 'this_week', label: 'Tuần này', start: weekStart, end: addDays(weekStart, 6) },
    { value: 'this_month', label: 'Tháng này', start: toYmd(new Date(year, month, 1)), end: toYmd(new Date(year, month + 1, 0)) },
    { value: 'this_quarter', label: 'Quý này', start: toYmd(new Date(year, qStartMonth, 1)), end: toYmd(new Date(year, qStartMonth + 3, 0)) },
    { value: 'this_year', label: 'Năm này', start: toYmd(new Date(year, 0, 1)), end: toYmd(new Date(year, 12, 0)) },

    { value: 'tomorrow', label: 'Ngày mai', start: addDays(today, 1), end: addDays(today, 1) },
    { value: 'next_week', label: 'Tuần tiếp theo', start: addDays(weekStart, 7), end: addDays(weekStart, 13) },
    { value: 'next_month', label: 'Tháng tiếp theo', start: toYmd(new Date(year, month + 1, 1)), end: toYmd(new Date(year, month + 2, 0)) },
    { value: 'next_quarter', label: 'Quý tiếp theo', start: toYmd(new Date(year, qStartMonth + 3, 1)), end: toYmd(new Date(year, qStartMonth + 6, 0)) },
    { value: 'next_year', label: 'Năm tiếp theo', start: toYmd(new Date(year + 1, 0, 1)), end: toYmd(new Date(year + 1, 12, 0)) },

    { value: 'yesterday', label: 'Hôm qua', start: addDays(today, -1), end: addDays(today, -1) },
    { value: 'last_week', label: 'Tuần trước', start: addDays(weekStart, -7), end: addDays(weekStart, -1) },
    { value: 'last_month', label: 'Tháng trước', start: toYmd(new Date(year, month - 1, 1)), end: toYmd(new Date(year, month, 0)) },
    { value: 'last_quarter', label: 'Quý trước', start: toYmd(new Date(year, qStartMonth - 3, 1)), end: toYmd(new Date(year, qStartMonth, 0)) },
    { value: 'last_year', label: 'Năm trước', start: toYmd(new Date(year - 1, 0, 1)), end: toYmd(new Date(year - 1, 12, 0)) },

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
      <CalendarDays class="h-4 w-4 shrink-0 text-sky-400" />
    </button>

    <div v-if="open" class="absolute left-0 top-[calc(100%+6px)] z-30 w-[320px] rounded-lg border border-slate-300 bg-white p-3 shadow-xl" @click.stop>
      <label class="block text-xs font-bold text-slate-700">
        Phạm vi ngày
        <select v-model="preset" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" @change="selectPreset">
          <option v-for="item in presets" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
      </label>

      <div class="mt-3 grid grid-cols-2 gap-2">
        <label class="text-[11px] font-bold text-slate-500">Từ ngày
          <input v-model="draftStart" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-xs" @change="preset = 'custom'" />
        </label>
        <label class="text-[11px] font-bold text-slate-500">Đến ngày
          <input v-model="draftEnd" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-xs" @change="preset = 'custom'" />
        </label>
      </div>

      <div class="mt-3 flex justify-end border-t border-slate-200 pt-3">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border-none bg-blue-500 px-4 py-2 text-xs font-bold text-white hover:bg-blue-600" @click="apply">
          <CheckCircle2 class="h-4 w-4" /> Áp dụng
        </button>
      </div>
    </div>
  </div>
</template>
