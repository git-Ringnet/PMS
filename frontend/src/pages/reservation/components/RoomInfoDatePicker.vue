<script setup>
import { computed, ref, watch } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'
import {
  formatShortDate,
  maskDateInput,
  parseDmyInput,
  parseYmd,
  toYmd,
} from './room-info-date-utils'

const props = defineProps({
  modelValue: { type: String, default: '' },
  minDate: { type: [String, Date], default: null },
  maxDate: { type: [String, Date], default: null },
  placeholder: { type: String, default: 'dd/mm/yyyy' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])
const datepickerRef = ref(null)
const isFocused = ref(false)

const textInput = ref(formatShortDate(props.modelValue))

watch(() => props.modelValue, (newValue) => {
  if (!isFocused.value) textInput.value = formatShortDate(newValue)
})

const dateValue = computed({
  get: () => parseYmd(props.modelValue),
  set(value) {
    if (!value) {
      clearDate()
      return
    }
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return
    const ymd = toYmd(date)
    emit('update:modelValue', ymd)
    emit('change', ymd)
  },
})

const parsedMinDate = computed(() => parseYmd(props.minDate))
const parsedMaxDate = computed(() => parseYmd(props.maxDate))

let isDeletingKey = false

function handleKeydown(e) {
  if (e.key === 'Backspace' || e.key === 'Delete') {
    isDeletingKey = true
  } else {
    isDeletingKey = false
  }
}

function handleInput(event) {
  if (props.disabled) return
  const isDeleting = (event && event.inputType && event.inputType.startsWith('delete')) || isDeletingKey
  isDeletingKey = false

  textInput.value = maskDateInput(event.target.value, isDeleting)
  const digits = textInput.value.replace(/\D/g, '')
  if (digits.length === 8) {
    const parsed = parseDmyInput(textInput.value)
    if (parsed) {
      emit('update:modelValue', parsed)
      emit('change', parsed)
    }
  }
}

function handleFocus() {
  if (!props.disabled) isFocused.value = true
}

function handleBlur() {
  if (props.disabled) return
  isFocused.value = false
  if (!textInput.value.trim()) {
    clearDate()
    return
  }

  const parsed = parseDmyInput(textInput.value)
  if (parsed) {
    emit('update:modelValue', parsed)
    emit('change', parsed)
    textInput.value = formatShortDate(parsed)
  } else {
    textInput.value = formatShortDate(props.modelValue)
  }
}

function clearDate() {
  if (props.disabled) return
  textInput.value = ''
  emit('update:modelValue', '')
  emit('change', '')
}

function openCalendar() {
  if (props.disabled) return
  datepickerRef.value?.openMenu?.()
}
</script>

<template>
  <div
    class="room-info-date-picker"
    :class="[disabled ? 'is-disabled' : '', required ? 'is-required' : '']"
  >
    <input
      :value="textInput"
      type="text"
      :disabled="disabled"
      :placeholder="placeholder"
      autocomplete="off"
      @keydown="handleKeydown"
      @input="handleInput"
      @focus="handleFocus"
      @blur="handleBlur"
      @keydown.enter.prevent="handleBlur"
    />
    <div class="date-actions">
      <button
        v-if="!disabled && modelValue"
        type="button"
        class="date-action clear-date"
        title="Xóa ngày"
        aria-label="Xóa ngày"
        @mousedown.prevent
        @click.stop="clearDate"
      >
        &times;
      </button>
      <VueDatePicker
        ref="datepickerRef"
        v-model="dateValue"
        :locale="vi"
        :enable-time-picker="false"
        :min-date="parsedMinDate"
        :max-date="parsedMaxDate"
        :disabled="disabled"
        :teleport="true"
        auto-apply
        format="dd/MM/yyyy"
        menu-class-name="room-info-datepicker-menu"
      >
        <template #trigger>
          <button
            type="button"
            class="date-action calendar-action"
            :disabled="disabled"
            title="Chọn từ lịch"
            aria-label="Chọn từ lịch"
            @click.stop="openCalendar"
          >
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>
        </template>
      </VueDatePicker>
    </div>
  </div>
</template>

<style scoped>
.room-info-date-picker {
  width: 100%;
  height: 32px;
  display: flex;
  align-items: center;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.room-info-date-picker:focus-within {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}
.room-info-date-picker.is-required {
  background: #FFF8DB !important;
  background-color: #FFF8DB !important;
  border-color: #F1DD8A !important;
}
.room-info-date-picker.is-required:focus-within {
  border-color: #eab308 !important;
  box-shadow: 0 0 0 2px rgba(234, 179, 8, 0.2) !important;
}
.room-info-date-picker.is-disabled:not(.is-required) {
  background: #f1f5f9;
  border-color: #cbd5e1;
  opacity: 0.75;
}
.room-info-date-picker.is-disabled.is-required {
  background: #FFF8DB !important;
  background-color: #FFF8DB !important;
  border-color: #F1DD8A !important;
}
.room-info-date-picker input,
.room-info-date-picker input:disabled,
.room-info-date-picker.is-required input,
.room-info-date-picker.is-required input:disabled {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: 0 !important;
  border-color: transparent !important;
  outline: 0 !important;
  padding: 0 4px 0 6px !important;
  background: transparent !important;
  background-color: transparent !important;
  color: #000000d9;
  font-family: inherit;
  font-size: 12px;
  font-weight: 400;
  box-shadow: none !important;
}
.room-info-date-picker input::placeholder {
  color: #a8b0bf;
  font-weight: 400;
}
.room-info-date-picker input:disabled {
  color: #64748b;
  cursor: not-allowed;
}
.date-actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  padding-right: 3px;
  gap: 1px;
}
.date-action {
  width: 19px;
  height: 19px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  padding: 0;
}
.date-action:hover:not(:disabled) { color: #2563eb; }
.clear-date:hover { color: #ef4444; }
.date-action:disabled { cursor: not-allowed; }
</style>

<style>
.dp__menu.room-info-datepicker-menu {
  border-radius: 12px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15) !important;
  font-family: 'Roboto', system-ui, sans-serif !important;
  z-index: 999999 !important;
}
</style>
