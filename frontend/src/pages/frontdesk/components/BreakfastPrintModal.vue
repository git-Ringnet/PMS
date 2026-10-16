<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth-store'

const authStore = useAuthStore()
const themeBg = computed(() => authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  selectedRooms: {
    type: Array,
    default: () => []
  },
  defaultFromDate: {
    type: String,
    default: ''
  },
  defaultToDate: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'confirm'])

const printMode = ref('all') // 'all' | 'range'
const fromDate = ref('')
const toDate = ref('')
const fromDateDisplay = ref('')
const toDateDisplay = ref('')

const modalFromDateRef = ref(null)
const modalToDateRef = ref(null)

function openModalFromDatePicker() {
  if (modalFromDateRef.value) {
    try {
      if (typeof modalFromDateRef.value.showPicker === 'function') {
        modalFromDateRef.value.showPicker()
      } else {
        modalFromDateRef.value.click()
      }
    } catch (e) {
      modalFromDateRef.value.click()
    }
  }
}

function openModalToDatePicker() {
  if (modalToDateRef.value) {
    try {
      if (typeof modalToDateRef.value.showPicker === 'function') {
        modalToDateRef.value.showPicker()
      } else {
        modalToDateRef.value.click()
      }
    } catch (e) {
      modalToDateRef.value.click()
    }
  }
}

function formatDateDisplay(dStr) {
  if (!dStr) return ''
  const clean = String(dStr).split('T')[0]
  if (clean.includes('-')) {
    const [y, m, d] = clean.split('-')
    return `${d}/${m}/${y}`
  }
  return clean
}

function handleDateManualInput(e, field) {
  let val = e.target.value.replace(/\D/g, '')
  if (val.length > 8) val = val.slice(0, 8)

  let formatted = ''
  if (val.length <= 2) {
    formatted = val
    if (val.length === 2) formatted += '/'
  } else if (val.length <= 4) {
    const day = val.slice(0, 2)
    let month = val.slice(2)
    if (month.length === 1 && parseInt(month, 10) > 1) {
      formatted = `${day}/0${month}/`
    } else {
      formatted = `${day}/${month}`
      if (month.length === 2) {
        if (parseInt(month, 10) > 12) month = '12'
        if (parseInt(month, 10) === 0) month = '01'
        formatted = `${day}/${month}/`
      }
    }
  } else {
    const day = val.slice(0, 2)
    let month = val.slice(2, 4)
    if (parseInt(month, 10) > 12) month = '12'
    if (parseInt(month, 10) === 0) month = '01'
    const year = val.slice(4, 8)
    formatted = `${day}/${month}/${year}`
  }

  if (field === 'from') {
    fromDateDisplay.value = formatted
    const raw = formatted.replace(/\D/g, '')
    if (raw.length === 8) {
      const parts = formatted.split('/')
      fromDate.value = `${parts[2]}-${parts[1]}-${parts[0]}`
    }
  } else {
    toDateDisplay.value = formatted
    const raw = formatted.replace(/\D/g, '')
    if (raw.length === 8) {
      const parts = formatted.split('/')
      toDate.value = `${parts[2]}-${parts[1]}-${parts[0]}`
    }
  }
}

function handleNativeFromDateChange(e) {
  const val = e.target.value
  if (val) {
    fromDate.value = val
    fromDateDisplay.value = formatDateDisplay(val)
  }
}

function handleNativeToDateChange(e) {
  const val = e.target.value
  if (val) {
    toDate.value = val
    toDateDisplay.value = formatDateDisplay(val)
  }
}

watch(fromDate, (newVal) => {
  if (newVal && toDate.value && newVal > toDate.value) {
    toDate.value = newVal
    toDateDisplay.value = formatDateDisplay(newVal)
  }
})

watch(() => props.isOpen, (val) => {
  if (val) {
    printMode.value = 'all'
    fromDate.value = props.defaultFromDate || new Date().toISOString().slice(0, 10)
    toDate.value = props.defaultToDate || fromDate.value
    fromDateDisplay.value = formatDateDisplay(fromDate.value)
    toDateDisplay.value = formatDateDisplay(toDate.value)
  }
})

function handleKeydown(e) {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

function handleConfirm() {
  if (printMode.value === 'range') {
    if (!fromDate.value || !toDate.value) {
      alert('Vui lòng chọn đầy đủ giai đoạn ngày cần in!')
      return
    }
  }
  emit('confirm', {
    mode: printMode.value,
    fromDate: fromDate.value,
    toDate: toDate.value
  })
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150 text-slate-800 font-sans">
      <!-- Header -->
      <div 
        class="px-5 py-3 flex items-center justify-between text-white select-none shrink-0"
        :style="{ background: themeBg, color: '#ffffff' }"
      >
        <div class="flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-lg bg-white/20 text-white flex items-center justify-center font-bold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
          </span>
          <div>
            <h3 class="text-xs font-semibold text-white tracking-wide">Tùy Chọn In Phiếu Ăn Sáng</h3>
            <p class="text-[11px] text-white/80">Đã chọn: <span class="font-bold text-white">{{ selectedRooms.length }}</span> phòng</p>
          </div>
        </div>
        <button @click="emit('close')" class="text-white/80 hover:text-white p-1 rounded transition-colors cursor-pointer border-none bg-transparent" title="Đóng (Esc)">
          ✕
        </button>
      </div>

      <!-- Body -->
      <div class="p-5 space-y-3.5 text-xs">
        <p class="text-slate-600 font-semibold">Vui lòng chọn 1 trong 2 hình thức in phiếu ăn sáng:</p>

        <!-- Option 1: In All Card -->
        <label 
          @click="printMode = 'all'"
          :class="printMode === 'all' ? 'border-sky-500 bg-sky-50/40 ring-2 ring-sky-500/20' : 'border-slate-200 hover:bg-slate-50'"
          class="flex items-start gap-3 p-3.5 rounded-xl border transition-all cursor-pointer block select-none"
        >
          <input 
            type="radio" 
            name="printMode" 
            value="all" 
            v-model="printMode" 
            class="mt-0.5 text-sky-600 focus:ring-sky-500"
          />
          <div class="font-bold text-slate-900 text-xs">
            1. In tất cả (In All)
          </div>
        </label>

        <!-- Option 2: In Giai Đoạn Card -->
        <label 
          @click="printMode = 'range'"
          :class="printMode === 'range' ? 'border-sky-500 bg-sky-50/40 ring-2 ring-sky-500/20' : 'border-slate-200 hover:bg-slate-50'"
          class="flex flex-col gap-2.5 p-3.5 rounded-xl border transition-all cursor-pointer block select-none"
        >
          <div class="flex items-start gap-3">
            <input 
              type="radio" 
              name="printMode" 
              value="range" 
              v-model="printMode" 
              class="mt-0.5 text-sky-600 focus:ring-sky-500"
            />
            <div class="font-bold text-slate-900 text-xs">
              2. In theo giai đoạn ngày
            </div>
          </div>

          <!-- Date inputs inside Range Option -->
          <div v-if="printMode === 'range'" class="pt-2 pl-7 grid grid-cols-2 gap-3 border-t border-sky-100 animate-in fade-in duration-150">
            <div>
              <label class="block font-semibold text-slate-700 mb-1 text-xs">Từ ngày:</label>
              <div class="flex items-center justify-between h-8 px-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-800 hover:border-sky-500 shadow-2xs">
                <input 
                  type="text" 
                  :value="fromDateDisplay" 
                  @input="e => handleDateManualInput(e, 'from')" 
                  placeholder="dd/mm/yyyy" 
                  maxlength="10" 
                  class="w-24 border-none outline-none font-bold text-xs bg-transparent"
                />
                <button type="button" @click="openModalFromDatePicker" class="p-1 text-sky-600 hover:text-sky-800 cursor-pointer" title="Chọn ngày">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                    <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </button>
                <input 
                  ref="modalFromDateRef"
                  :value="fromDate" 
                  @change="handleNativeFromDateChange"
                  type="date" 
                  class="sr-only"
                />
              </div>
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1 text-xs">Đến ngày:</label>
              <div class="flex items-center justify-between h-8 px-2 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-800 hover:border-sky-500 shadow-2xs">
                <input 
                  type="text" 
                  :value="toDateDisplay" 
                  @input="e => handleDateManualInput(e, 'to')" 
                  placeholder="dd/mm/yyyy" 
                  maxlength="10" 
                  class="w-24 border-none outline-none font-bold text-xs bg-transparent"
                />
                <button type="button" @click="openModalToDatePicker" class="p-1 text-sky-600 hover:text-sky-800 cursor-pointer" title="Chọn ngày">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/>
                    <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </button>
                <input 
                  ref="modalToDateRef"
                  :value="toDate" 
                  @change="handleNativeToDateChange"
                  type="date" 
                  class="sr-only"
                />
              </div>
            </div>
          </div>
        </label>
      </div>

      <!-- Footer: Only Print Button (Close button in header + Esc key) -->
      <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 text-xs font-semibold">
        <button 
          @click="handleConfirm"
          class="btn-pms-primary px-5 py-2 flex items-center gap-1.5 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          In
        </button>
      </div>
    </div>
  </div>
</template>
