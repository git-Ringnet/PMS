<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '12:00' },
  disabled: { type: Boolean, default: false },
  defaultTime: { type: String, default: '12:00' },
  dropUp: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const containerRef = ref(null)
const popoverStyle = ref({})

const currentHour = computed(() => {
  const val = props.modelValue || props.defaultTime || '12:00'
  const parts = String(val).split(':')
  return parseInt(parts[0], 10) || 0
})

const currentMinute = computed(() => {
  const val = props.modelValue || props.defaultTime || '12:00'
  const parts = String(val).split(':')
  return parseInt(parts[1], 10) || 0
})

const formattedDisplay = computed(() => {
  const val = props.modelValue || props.defaultTime || '12:00'
  const str = String(val).trim()
  const parts = str.split(':')
  if (parts.length >= 2) {
    const hh = parts[0].padStart(2, '0')
    const mm = parts[1].padStart(2, '0')
    return `${hh}:${mm}`
  }
  return str.substring(0, 5)
})

function updatePopoverPos() {
  if (!containerRef.value) return
  const rect = containerRef.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const popHeight = 180

  if (props.dropUp || (spaceBelow < popHeight && rect.top > popHeight)) {
    popoverStyle.value = {
      position: 'fixed',
      top: `${rect.top - popHeight - 4}px`,
      left: `${rect.left}px`,
      width: '150px',
      height: '180px',
      zIndex: 999999,
    }
  } else {
    popoverStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${rect.left}px`,
      width: '150px',
      height: '180px',
      zIndex: 999999,
    }
  }
}

async function togglePicker() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    updatePopoverPos()
  }
}

function selectHour(h) {
  const hh = String(h).padStart(2, '0')
  const mm = String(currentMinute.value).padStart(2, '0')
  emit('update:modelValue', `${hh}:${mm}`)
}

function selectMinute(m) {
  const hh = String(currentHour.value).padStart(2, '0')
  const mm = String(m).padStart(2, '0')
  emit('update:modelValue', `${hh}:${mm}`)
  isOpen.value = false
}

function handleGlobalClick(e) {
  if (!isOpen.value) return
  if (containerRef.value && containerRef.value.contains(e.target)) return
  isOpen.value = false
}

function handleScrollOrResize() {
  if (isOpen.value) updatePopoverPos()
}

onMounted(() => {
  window.addEventListener('click', handleGlobalClick, true)
  window.addEventListener('scroll', handleScrollOrResize, true)
  window.addEventListener('resize', handleScrollOrResize, true)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleGlobalClick, true)
  window.removeEventListener('scroll', handleScrollOrResize, true)
  window.removeEventListener('resize', handleScrollOrResize, true)
})
</script>

<template>
  <div class="time-picker-24h-rel" ref="containerRef">
    <div class="time-input-box" @click="togglePicker">
      <input
        type="text"
        :value="formattedDisplay"
        :disabled="disabled"
        readonly
        class="time-input-field"
        style="text-align: center; cursor: pointer;"
      />
      <span class="clock-icon">🕒</span>
    </div>

    <!-- TELEPORT TO BODY SO POPUP IS NEVER CLIPPED BY TABLE SCROLL CONTAINERS -->
    <Teleport to="body">
      <div
        v-if="isOpen && !disabled"
        class="time-picker-popover-24h"
        :style="popoverStyle"
        @click.stop
      >
        <div class="tp-col">
          <div class="tp-head">GIỜ (24H)</div>
          <div class="tp-list">
            <div
              v-for="h in 24"
              :key="h - 1"
              class="tp-item"
              :class="{ active: currentHour === (h - 1) }"
              @click="selectHour(h - 1)"
            >
              {{ String(h - 1).padStart(2, '0') }}
            </div>
          </div>
        </div>
        <div class="tp-col">
          <div class="tp-head">PHÚT</div>
          <div class="tp-list">
            <div
              v-for="m in [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55]"
              :key="m"
              class="tp-item"
              :class="{ active: currentMinute === m }"
              @click="selectMinute(m)"
            >
              {{ String(m).padStart(2, '0') }}
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.time-picker-24h-rel {
  position: relative;
  width: 100%;
  display: inline-block;
}
.time-input-box {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}
.time-input-field {
  border: 1.5px solid #cbd5e1;
  border-radius: 6px;
  padding: 4px 18px 4px 4px;
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  background: #fff;
  height: 30px;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.time-input-field:disabled {
  background-color: #f1f5f9 !important;
  color: #64748b !important;
  border-color: #cbd5e1 !important;
  cursor: not-allowed !important;
}
.clock-icon {
  position: absolute;
  right: 4px;
  font-size: 11px;
  cursor: pointer;
  user-select: none;
  opacity: 0.75;
}
.clock-icon:hover { opacity: 1; }

.time-picker-popover-24h {
  display: flex;
  background: #fff;
  border: 1.5px solid #2563eb;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}
.tp-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
}
.tp-col:last-child { border-right: none; }
.tp-head {
  background: #2563eb;
  color: #fff;
  font-size: 9.5px;
  font-weight: 700;
  text-align: center;
  padding: 4px 0;
  text-transform: uppercase;
}
.tp-list {
  flex: 1;
  overflow-y: auto;
}
.tp-item {
  padding: 4px 0;
  text-align: center;
  font-size: 12.5px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: background 0.1s;
}
.tp-item:hover { background: #eff6ff; color: #2563eb; }
.tp-item.active { background: #2563eb; color: #fff; }
</style>
