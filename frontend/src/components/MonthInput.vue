<script setup>
// Ô chọn tháng (task 372): gõ tay mm/yyyy, bấm vào bất kỳ vị trí nào trong ô cũng mở lịch chọn tháng.
// v-model là chuỗi 'YYYY-MM' (khớp input type="month").
import { ref, watch } from 'vue'
import { Calendar } from '@lucide/vue'
import { formatMonthDisplay, parseMonthInput } from '@/utils/month-input'

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'mm/yyyy' },
})
const emit = defineEmits(['update:modelValue', 'change'])

const text = ref(formatMonthDisplay(props.modelValue))
const pickerRef = ref(null)

watch(() => props.modelValue, v => { text.value = formatMonthDisplay(v) })

function commit() {
  const parsed = parseMonthInput(text.value)
  if (parsed === null) {
    // Gõ sai định dạng thì trả về giá trị đang có
    text.value = formatMonthDisplay(props.modelValue)
    return
  }
  text.value = formatMonthDisplay(parsed)
  if (parsed !== props.modelValue) {
    emit('update:modelValue', parsed)
    emit('change', parsed)
  }
}

function openPicker() {
  if (props.disabled) return
  const el = pickerRef.value
  if (!el) return
  try { el.showPicker() } catch { el.focus() }
}

function onPicked(e) {
  const v = e.target.value
  if (!v) return
  text.value = formatMonthDisplay(v)
  emit('update:modelValue', v)
  emit('change', v)
}
</script>

<template>
  <div
    class="relative flex items-center justify-between border border-slate-300 rounded-lg bg-white px-2.5 h-8 w-full shadow-2xs hover:border-[var(--pms-custom-theme,#006bdb)] transition-colors"
    :class="disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-text'"
    data-testid="month-input"
    @click="openPicker"
  >
    <input
      v-model="text"
      type="text"
      inputmode="numeric"
      maxlength="7"
      :placeholder="placeholder"
      :disabled="disabled"
      class="flex-1 min-w-0 bg-transparent outline-none text-xs font-semibold text-[#000000D9]"
      @blur="commit"
      @keydown.enter.prevent="commit"
      @click.stop
      @focus="$event.target.select()"
    />
    <button type="button" tabindex="-1" class="shrink-0 border-none bg-transparent p-0 cursor-pointer" @click.stop="openPicker">
      <Calendar class="w-3.5 h-3.5 text-slate-400" />
    </button>
    <input
      ref="pickerRef"
      type="month"
      :value="modelValue"
      class="absolute left-0 bottom-0 w-0 h-0 opacity-0 pointer-events-none"
      tabindex="-1"
      aria-hidden="true"
      @change="onPicked"
    />
  </div>
</template>
