<script setup>
import { computed } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  minDate: {
    type: [String, Date],
    default: null
  },
  maxDate: {
    type: [String, Date],
    default: null
  },
  placeholder: {
    type: String,
    default: 'dd/mm/yyyy'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  inputClass: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Parse "YYYY-MM-DD" string to Date object
const dateValue = computed({
  get() {
    if (!props.modelValue) return null
    if (props.modelValue instanceof Date) return props.modelValue
    const parts = String(props.modelValue).split('-')
    if (parts.length === 3) {
      const y = parseInt(parts[0], 10)
      const m = parseInt(parts[1], 10) - 1
      const d = parseInt(parts[2], 10)
      return new Date(y, m, d)
    }
    return null
  },
  set(val) {
    if (!val) {
      emit('update:modelValue', '')
      emit('change', '')
      return
    }
    const d = new Date(val)
    if (isNaN(d.getTime())) {
      emit('update:modelValue', '')
      emit('change', '')
      return
    }
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    const ymd = `${year}-${month}-${day}`
    emit('update:modelValue', ymd)
    emit('change', ymd)
  }
})

// Parse minDate prop to Date object
const parsedMinDate = computed(() => {
  if (!props.minDate) return null
  if (props.minDate instanceof Date) return props.minDate
  const parts = String(props.minDate).split('-')
  if (parts.length === 3) {
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10))
  }
  return null
})

// Parse maxDate prop to Date object
const parsedMaxDate = computed(() => {
  if (!props.maxDate) return null
  if (props.maxDate instanceof Date) return props.maxDate
  const parts = String(props.maxDate).split('-')
  if (parts.length === 3) {
    return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10))
  }
  return null
})

const formatDateDMY = (dateStr) => {
  if (!dateStr) return ''
  const parts = String(dateStr).split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}
</script>

<template>
  <div class="relative w-full">
    <VueDatePicker
      v-model="dateValue"
      :locale="vi"
      :enable-time-picker="false"
      :min-date="parsedMinDate"
      :max-date="parsedMaxDate"
      :disabled="disabled"
      :teleport="true"
      auto-apply
      format="dd/MM/yyyy"
      menu-class-name="custom-datepicker-menu"
      class="custom-single-datepicker"
    >
      <template #trigger>
        <div
          class="w-full flex items-center justify-between px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold bg-white text-gray-900 cursor-pointer hover:border-sky-400 focus-within:border-sky-500 transition-colors shadow-2xs select-none"
          :class="[disabled ? 'bg-slate-100 opacity-60 cursor-not-allowed' : '', inputClass]"
        >
          <span :class="modelValue ? 'text-gray-900 font-semibold' : 'text-slate-400 font-normal'">
            {{ modelValue ? formatDateDMY(modelValue) : placeholder }}
          </span>
          <svg class="w-4 h-4 text-slate-400 ml-1 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </div>
      </template>
    </VueDatePicker>
  </div>
</template>

<style>
.dp__menu.custom-datepicker-menu {
  border-radius: 12px !important;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
  border: 1px solid #e2e8f0 !important;
  font-family: inherit !important;
  z-index: 999999 !important;
}

.dp__theme_light {
  --dp-primary-color: #0284c7 !important;
  --dp-hover-color: #f0f9ff !important;
  --dp-hover-text-color: #0369a1 !important;
  --dp-hover-icon-color: #0284c7 !important;
  --dp-primary-text-color: #ffffff !important;
  --dp-secondary-color: #cbd5e1 !important;
  --dp-border-color: #e2e8f0 !important;
  --dp-menu-border-color: #e2e8f0 !important;
}

.custom-datepicker-menu .dp__calendar_header_item {
  font-weight: 700 !important;
  font-size: 11px !important;
  color: #475569 !important;
}

.custom-datepicker-menu .dp__cell_inner {
  font-size: 12px !important;
  border-radius: 6px !important;
  font-weight: 600 !important;
}
</style>
