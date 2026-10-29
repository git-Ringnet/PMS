<script setup>
// Dòng 282: ô nhập Tên khách / Số giấy tờ có gợi ý khách đã lưu trú.
// Danh sách gợi ý teleport ra body để không bị bảng có thanh cuộn che mất.
import { ref, onBeforeUnmount } from 'vue'
import { searchGuests } from '@/services/booking-service'
import { formatGuestSuggestion } from '@/utils/guest-suggestion'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  inputClass: { type: [String, Array, Object], default: '' },
  placeholder: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'select'])

const inputRef = ref(null)
const items = ref([])
const open = ref(false)
const query = ref('')
const position = ref({ top: 0, left: 0 })
let timer = null

function place() {
  const rect = inputRef.value?.getBoundingClientRect()
  if (rect) position.value = { top: rect.bottom + 2, left: rect.left }
}

function onInput(event) {
  const value = event.target.value
  emit('update:modelValue', value)
  query.value = value
  clearTimeout(timer)
  if (!value.trim()) {
    items.value = []
    open.value = false
    return
  }
  timer = setTimeout(async () => {
    try {
      const res = await searchGuests(value.trim())
      items.value = res.data?.success ? (res.data.data || []) : []
      place()
      open.value = items.value.length > 0
    } catch (e) {
      console.error('searchGuests error', e)
    }
  }, 250)
}

function onFocus() {
  if (items.value.length && String(props.modelValue || '').trim()) {
    place()
    open.value = true
  }
}

function choose(guest) {
  open.value = false
  emit('select', guest)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <input
    ref="inputRef"
    :value="modelValue"
    type="text"
    :disabled="disabled"
    :placeholder="placeholder"
    :class="inputClass"
    autocomplete="off"
    v-bind="$attrs"
    @input="onInput"
    @focus="onFocus"
    @blur="open = false"
    @keydown.esc="open = false"
  />
  <Teleport to="body">
    <div
      v-if="open && items.length"
      class="guest-suggestion-dropdown"
      :style="{ top: `${position.top}px`, left: `${position.left}px` }"
    >
      <div
        v-for="guest in items"
        :key="guest.id"
        class="guest-suggestion-item"
        @mousedown.prevent="choose(guest)"
        v-html="formatGuestSuggestion(guest, query)"
      ></div>
    </div>
  </Teleport>
</template>

<style scoped>
.guest-suggestion-dropdown {
  position: fixed;
  width: 440px;
  max-width: 90vw;
  max-height: 220px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #94a3b8;
  border-radius: 6px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25);
  z-index: 10050;
}

.guest-suggestion-item {
  padding: 6px 10px;
  font-size: 12px;
  color: #000000d9;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.guest-suggestion-item:last-child {
  border-bottom: none;
}

.guest-suggestion-item:hover {
  background-color: #38bdf8;
  color: #ffffff;
}

.guest-suggestion-item:hover :deep(span) {
  color: #fef08a !important;
}
</style>
