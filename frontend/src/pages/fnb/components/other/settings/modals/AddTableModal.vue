<script setup>
import { ref, onMounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  mode: {
    type: String,
    default: 'single' // 'single', 'bulk', 'edit'
  },
  location: {
    type: Object,
    required: true
  },
  table: {
    type: Object,
    default: null
  },
  suggestedName: {
    type: String,
    default: ''
  },
  suggestedRow: {
    type: Number,
    default: 1
  },
  suggestedCol: {
    type: Number,
    default: 1
  }
})

const emit = defineEmits(['close', 'saveSingle', 'saveBulk', 'delete'])

// Form fields for Single/Edit Table
const tableName = ref('')
const rowIndex = ref(1)
const colIndex = ref(1)
const isActive = ref(true)

// Form fields for Bulk Table
const bulkPrefix = ref('')
const bulkCount = ref(0)
const bulkRow = ref(0)

const initForm = () => {
  if (props.mode === 'edit' && props.table) {
    tableName.value = props.table.name
    rowIndex.value = props.table.row_index
    colIndex.value = props.table.col_index
    isActive.value = props.table.is_active !== false
  } else if (props.mode === 'single') {
    tableName.value = props.suggestedName || (props.location?.letter || '')
    rowIndex.value = props.suggestedRow
    colIndex.value = props.suggestedCol
    isActive.value = true
  } else if (props.mode === 'bulk') {
    bulkPrefix.value = props.location?.letter || ''
    bulkCount.value = 0
    bulkRow.value = props.suggestedRow
  }
}

onMounted(() => {
  initForm()
})

// Re-init form if props change (e.g. mode changes or table changes)
watch(() => [props.show, props.mode, props.table], () => {
  if (props.show) {
    initForm()
  }
})

const handleSave = () => {
  if (props.mode === 'single' || props.mode === 'edit') {
    if (!tableName.value) {
      uiStore.alert('Vui lòng nhập tên bàn!')
      return
    }
    emit('saveSingle', {
      id: props.table?.id || null,
      name: tableName.value,
      row_index: parseInt(rowIndex.value) || 1,
      col_index: parseInt(colIndex.value) || 1,
      is_active: isActive.value,
      location_id: props.location.id
    })
  } else {
    if (!bulkPrefix.value) {
      uiStore.alert('Vui lòng nhập tên bàn!')
      return
    }
    if (bulkCount.value < 0 || bulkRow.value < 0) {
      uiStore.alert('Giá trị nhập vào phải lớn hơn hoặc bằng 0!')
      return
    }
    emit('saveBulk', {
      location_id: props.location.id,
      prefix_code: bulkPrefix.value,
      total_tables: parseInt(bulkCount.value) || 0,
      row_index: parseInt(bulkRow.value) || 0
    })
  }
}

const handleDelete = () => {
  if (props.table?.id) {
    emit('delete', props.table.id)
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-sm overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      <!-- Modal Header -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">
          {{ mode === 'edit' ? 'Sửa bàn' : (mode === 'single' ? 'Thêm bàn' : 'Thêm Nhanh Bàn') }}
        </h3>
        <div class="flex items-center gap-3">
          <!-- Help Symbol -->
          <button class="w-6 h-6 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm" title="Trợ giúp">
            <span class="text-xs font-bold">?</span>
          </button>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="p-6 space-y-4">
        <!-- Single / Edit Table Form -->
        <div v-if="mode === 'single' || mode === 'edit'" class="space-y-4">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tên bàn</label>
            <input 
              v-model="tableName"
              type="text" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              placeholder="Nhập tên bàn..."
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Hàng</label>
            <input 
              v-model.number="rowIndex"
              type="number" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              min="1"
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Cột</label>
            <input 
              v-model.number="colIndex"
              type="number" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              min="1"
            />
          </div>

          <div class="flex items-center justify-between pt-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Enable</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="isActive" class="sr-only peer" />
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-400"></div>
            </label>
          </div>

          <!-- Delete Button (Only in Edit mode) -->
          <div v-if="mode === 'edit'" class="pt-2">
            <button 
              @click="handleDelete" 
              class="w-32 flex items-center justify-center gap-1.5 px-4 py-2 bg-sky-300 hover:bg-sky-450 text-white rounded-lg transition font-semibold active:scale-95 shadow-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              Xóa
            </button>
          </div>
        </div>

        <!-- Bulk Table Form -->
        <div v-else class="space-y-4">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tên Bàn</label>
            <input 
              v-model="bulkPrefix"
              type="text" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              placeholder="Ví dụ: B"
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Số Bàn</label>
            <input 
              v-model.number="bulkCount"
              type="number" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              min="0"
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Hàng</label>
            <input 
              v-model.number="bulkRow"
              type="number" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              min="0"
            />
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-3 bg-slate-50 shrink-0">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition font-semibold active:scale-95 shadow-sm bg-white"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 px-4 py-2 bg-sky-400 hover:bg-sky-500 text-white rounded-lg transition font-semibold active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
