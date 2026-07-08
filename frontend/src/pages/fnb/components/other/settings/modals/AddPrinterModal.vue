<script setup>
import { ref, watch, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  mode: {
    type: String,
    default: 'add' // 'add', 'edit'
  },
  printer: {
    type: Object,
    default: null
  },
  outlets: {
    type: Array,
    default: () => []
  },
  selectedOutletId: {
    type: [Number, String],
    default: ''
  }
})

const emit = defineEmits(['close', 'save'])

const outletId = ref('')
const printerName = ref('')
const printerType = ref(1) // 1: In chế biến, hủy món
const numOfPrints = ref(1)
const driverName = ref('')

const printerTypes = [
  { value: 1, label: 'In chế biến, hủy món' },
  { value: 2, label: 'In hóa đơn, tạm tính' },
  { value: 3, label: 'In chuyển bàn' }
]

const initForm = () => {
  if (props.mode === 'edit' && props.printer) {
    outletId.value = props.printer.outlet_id
    printerName.value = props.printer.name
    printerType.value = props.printer.type || 1
    numOfPrints.value = props.printer.num_of_prints || 1
    driverName.value = props.printer.driver_name || ''
  } else {
    outletId.value = props.selectedOutletId || (props.outlets[0]?.id || '')
    printerName.value = ''
    printerType.value = 1
    numOfPrints.value = 1
    driverName.value = ''
  }
}

onMounted(() => {
  initForm()
})

watch(() => [props.show, props.mode, props.printer], () => {
  if (props.show) {
    initForm()
  }
})

const handleSave = () => {
  if (!outletId.value) {
    uiStore.alert('Vui lòng chọn Outlet!')
    return
  }
  if (!printerName.value) {
    uiStore.alert('Vui lòng nhập tên máy in!')
    return
  }
  if (numOfPrints.value < 1) {
    uiStore.alert('Số lần in phải lớn hơn hoặc bằng 1!')
    return
  }

  emit('save', {
    id: props.printer?.id || null,
    outlet_id: outletId.value,
    name: printerName.value,
    type: printerType.value,
    num_of_prints: numOfPrints.value,
    driver_name: driverName.value
  })
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-sm overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      <!-- Modal Header -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">
          {{ mode === 'edit' ? 'Sửa Máy In' : 'Thêm Máy In' }}
        </h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-4">
        <!-- Outlet Selection -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Outlet</label>
          <select 
            v-model="outletId"
            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
          >
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
              {{ outlet.name }}
            </option>
          </select>
        </div>

        <!-- Printer Name -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tên máy In</label>
          <input 
            v-model="printerName"
            type="text" 
            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
            placeholder="Nhập tên máy in..."
          />
        </div>

        <!-- Printer Type -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Loại máy in</label>
          <select 
            v-model="printerType"
            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
          >
            <option v-for="type in printerTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>

        <!-- Num of Prints -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Số lần in</label>
          <input 
            v-model.number="numOfPrints"
            type="number" 
            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
            min="1"
          />
        </div>

        <!-- Printer Driver Name -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Printer Driver Name</label>
          <input 
            v-model="driverName"
            type="text" 
            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
            placeholder="Nhập Driver Name..."
          />
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-650 rounded-lg transition font-semibold active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 px-4 py-2 bg-[#78C5E7] hover:bg-[#60b3d6] text-white rounded-lg transition font-semibold active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
