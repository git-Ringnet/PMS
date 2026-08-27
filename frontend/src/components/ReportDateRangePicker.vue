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

const localToday = () => {
  if (/^\d{4}-\d{2}-\d{2}$/.test(props.systemDate)) return props.systemDate
  const date = new Date()
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

const addDays = (dateString, days) => {
  const date = new Date(`${dateString}T00:00:00`)
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

const monthRange = (dateString) => {
  const date = new Date(`${dateString}T00:00:00`)
  const start = new Date(date.getFullYear(), date.getMonth(), 1)
  const end = new Date(date.getFullYear(), date.getMonth() + 1, 0)
  return [start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)]
}

const presets = computed(() => {
  const today = localToday()
  const day = new Date(`${today}T00:00:00`).getDay()
  const mondayOffset = day === 0 ? -6 : 1 - day
  const weekStart = addDays(today, mondayOffset)

  return [
    { value: 'today', label: 'Hôm nay', start: today, end: today },
    { value: 'yesterday', label: 'Hôm qua', start: addDays(today, -1), end: addDays(today, -1) },
    { value: 'this_week', label: 'Tuần này', start: weekStart, end: addDays(weekStart, 6) },
    { value: 'this_month', label: 'Tháng này', start: monthRange(today)[0], end: monthRange(today)[1] },
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
