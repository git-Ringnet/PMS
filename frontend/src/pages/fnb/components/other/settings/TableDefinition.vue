<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { 
  fetchOutlets, 
  fetchFbLocations, 
  createFbLocation, 
  updateFbLocation, 
  deleteFbLocation,
  fetchFbTables,
  createFbTable,
  updateFbTable,
  deleteFbTable,
  bulkCreateFbTables,
  deleteFbTableRow
} from '@/services/outlet-service'
import { useUiStore } from '@/stores/ui-store'
import AddLocationModal from './modals/AddLocationModal.vue'
import AddTableModal from './modals/AddTableModal.vue'
import ConfirmReasonModal from '@/pages/fnb/components/restaurant/modals/ConfirmReasonModal.vue'

defineEmits(['back'])

const uiStore = useUiStore()

const isLoading = ref(false)
const showReasonModal = ref(false)
const cancelActionType = ref('')
const cancelTargetData = ref(null)

// Outlets
const selectedOutlet = ref('')
const outlets = ref([])

// Locations (Areas)
const areas = ref([])
const activeAreaId = ref('')

// Tables
const tables = ref([])

// Modals state
const showLocationModal = ref(false)
const editingLocation = ref(null)
const showTableModal = ref(false)
const tableModalMode = ref('single') // 'single', 'bulk', 'edit'
const selectedTable = ref(null)

// Computed active area object
const activeArea = computed(() => {
  return areas.value.find(a => a.id === activeAreaId.value) || null
})

// Group tables by row_index for layout grid
const rows = computed(() => {
  const group = {}
  tables.value.forEach(table => {
    const rIdx = table.row_index || 1
    if (!group[rIdx]) {
      group[rIdx] = {
        id: rIdx,
        name: `HÀNG ${rIdx}`,
        tables: []
      }
    }
    group[rIdx].tables.push(table)
  })
  
  // Sort tables within each row by col_index ascending
  return Object.values(group)
    .map(row => {
      row.tables.sort((a, b) => (a.col_index || 0) - (b.col_index || 0))
      return row
    })
    .sort((a, b) => a.id - b.id)
})

// Suggestions for new tables
const suggestedName = computed(() => {
  const prefix = activeArea.value?.letter || 'B'
  const nextNum = tables.value.length + 1
  return `${prefix}${nextNum}`
})

const suggestedRow = computed(() => {
  if (tables.value.length === 0) return 1
  return Math.max(...tables.value.map(t => t.row_index))
})

const suggestedCol = computed(() => {
  // Suggest column position as next column in Row 1 (or highest row)
  const targetRow = suggestedRow.value
  const tablesInRow = tables.value.filter(t => t.row_index === targetRow)
  return tablesInRow.length + 1
})

// Load all Outlets
const loadOutlets = async () => {
  isLoading.value = true
  try {
    const res = await fetchOutlets()
    outlets.value = res.data || []
    if (outlets.value.length > 0) {
      selectedOutlet.value = outlets.value[0].code
    }
  } catch (error) {
    console.error('Lỗi tải outlets:', error)
  } finally {
    isLoading.value = false
  }
}

// Load Locations for selected Outlet
const loadLocations = async () => {
  if (!selectedOutlet.value) return
  isLoading.value = true
  try {
    const res = await fetchFbLocations(selectedOutlet.value)
    areas.value = res.data.data || res.data || []
    if (areas.value.length > 0) {
      const exists = areas.value.some(a => a.id === activeAreaId.value)
      if (!exists) {
        activeAreaId.value = areas.value[0].id
      }
    } else {
      activeAreaId.value = ''
      tables.value = []
    }
  } catch (error) {
    console.error('Lỗi tải khu vực:', error)
  } finally {
    isLoading.value = false
  }
}

// Load Tables for active Location
const loadTables = async () => {
  if (!activeAreaId.value) {
    tables.value = []
    return
  }
  isLoading.value = true
  try {
    const res = await fetchFbTables(activeAreaId.value)
    tables.value = res.data.data || res.data || []
  } catch (error) {
    console.error('Lỗi tải bàn:', error)
  } finally {
    isLoading.value = false
  }
}

// Watch outlet changes to update zones
watch(selectedOutlet, () => {
  loadLocations()
})

// Watch active zone changes to update tables
watch(activeAreaId, () => {
  loadTables()
})

onMounted(() => {
  loadOutlets()
})

// Location (Area) CRUD
const handleAddArea = () => {
  editingLocation.value = null
  showLocationModal.value = true
}

const handleEditArea = (area, e) => {
  e.stopPropagation()
  editingLocation.value = area
  showLocationModal.value = true
}

const handleDeleteArea = async (area, e) => {
  e.stopPropagation()
  const confirm = await uiStore.confirm({
    title: 'Xác nhận xóa khu vực',
    message: `Bạn có chắc chắn muốn xóa khu vực "${area.name}"?`
  })
  if (!confirm) return
  try {
    if (activeAreaId.value === area.id) {
      const other = areas.value.find(a => a.id !== area.id)
      activeAreaId.value = other ? other.id : ''
    }
    await deleteFbLocation(area.id)
    await loadLocations()
    uiStore.showToast('Xóa khu vực thành công!', 'success')
  } catch (error) {
    uiStore.showToast('Không thể xóa khu vực này!', 'error')
  }
}

const handleSaveLocation = async (formData) => {
  try {
    if (editingLocation.value) {
      const confirm = await uiStore.confirm({
        title: 'Xác nhận cập nhật',
        message: `Bạn có chắc chắn muốn lưu các thay đổi cho khu vực "${editingLocation.value.name}"?`
      })
      if (!confirm) return
      await updateFbLocation(editingLocation.value.id, formData)
      uiStore.showToast('Cập nhật khu vực thành công!', 'success')
    } else {
      await createFbLocation(formData)
      uiStore.showToast('Thêm khu vực thành công!', 'success')
    }
    showLocationModal.value = false
    await loadLocations()
  } catch (error) {
    uiStore.showToast('Lỗi khi lưu thông tin khu vực!', 'error')
  }
}

// Table CRUD
const handleAddTable = () => {
  if (!activeAreaId.value) {
    uiStore.showToast('Vui lòng thêm khu vực trước!', 'warning')
    return
  }
  tableModalMode.value = 'single'
  selectedTable.value = null
  showTableModal.value = true
}

const handleAddTableQuick = () => {
  if (!activeAreaId.value) {
    uiStore.showToast('Vui lòng thêm khu vực trước!', 'warning')
    return
  }
  tableModalMode.value = 'bulk'
  selectedTable.value = null
  showTableModal.value = true
}

const handleEditTable = (table) => {
  selectedTable.value = table
  tableModalMode.value = 'edit'
  showTableModal.value = true
}

const handleSaveTableSingle = async (data) => {
  try {
    if (data.id) {
      const confirm = await uiStore.confirm({
        title: 'Xác nhận cập nhật',
        message: `Bạn có chắc chắn muốn lưu các thay đổi cho bàn "${data.name}"?`
      })
      if (!confirm) return
      await updateFbTable(data.id, data)
      uiStore.showToast('Cập nhật thông tin bàn thành công!', 'success')
    } else {
      await createFbTable(data)
      uiStore.showToast('Thêm bàn thành công!', 'success')
    }
    showTableModal.value = false
    await loadTables()
  } catch (error) {
    uiStore.showToast('Lỗi khi lưu thông tin bàn!', 'error')
  }
}

const handleSaveTableBulk = async (data) => {
  try {
    await bulkCreateFbTables(data)
    uiStore.showToast('Thêm nhanh bàn thành công!', 'success')
    showTableModal.value = false
    await loadTables()
  } catch (error) {
    uiStore.showToast('Lỗi khi thêm nhanh bàn!', 'error')
  }
}

const handleQuickAddTables = async (formData) => {
  try {
    await bulkCreateFbTables({
      location_id: activeAreaId.value,
      ...formData
    })
    uiStore.showToast('Thêm nhanh bàn thành công!', 'success')
    await loadTables()
  } catch (error) {
    uiStore.showToast('Lỗi khi thêm nhanh bàn!', 'error')
  }
}

const handleDeleteTable = (tableId) => {
  cancelActionType.value = 'table'
  cancelTargetData.value = tableId
  showReasonModal.value = true
}

const handleDeleteRow = (rowIndex) => {
  cancelActionType.value = 'row'
  cancelTargetData.value = rowIndex
  showReasonModal.value = true
}

const handleConfirmDelete = async (reason) => {
  showReasonModal.value = false
  try {
    if (cancelActionType.value === 'area') {
      const area = cancelTargetData.value
      await deleteFbLocation(area.id, reason)
      uiStore.showToast('Xóa khu vực thành công!', 'success')
      if (activeAreaId.value === area.id) activeAreaId.value = null
      await loadLocations()
    } else if (cancelActionType.value === 'table') {
      const tableId = cancelTargetData.value
      await deleteFbTable(tableId, reason)
      uiStore.showToast('Xóa bàn thành công!', 'success')
      showTableModal.value = false
      await loadTables()
    } else if (cancelActionType.value === 'row') {
      const rowIndex = cancelTargetData.value
      await deleteFbTableRow({
        location_id: activeAreaId.value,
        row_index: rowIndex,
        reason
      })
      uiStore.showToast(`Xóa toàn bộ bàn ở HÀNG ${rowIndex} thành công!`, 'success')
      await loadTables()
    }
  } catch (error) {
    uiStore.showToast('Lỗi khi xóa!', 'error')
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-hidden min-h-0 font-sans text-xs relative">
    <!-- Loading Overlay -->
    <div v-if="isLoading" class="absolute inset-0 bg-white/80 backdrop-blur-[2px] flex items-center justify-center z-50 transition-all duration-200">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- Header -->
    <div class="flex items-center justify-between shrink-0 mb-4">
      <div class="flex items-center gap-2">
        <button @click="$emit('back')" class="p-1.5 rounded-full hover:bg-slate-200 text-slate-600 transition active:scale-95" title="Quay lại">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 class="text-base font-bold text-slate-800">Định nghĩa bàn</h1>
      </div>

      <!-- Help symbol -->
      <button class="w-7 h-7 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm" title="Trợ giúp">
        <span class="text-sm font-bold">?</span>
      </button>
    </div>

    <!-- Outlet picker at top center -->
    <div class="flex justify-center mb-6 shrink-0">
      <div class="relative w-full max-w-lg bg-white rounded-xl shadow-sm border border-slate-200/80 p-2 flex items-center gap-3">
        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider pl-2 whitespace-nowrap">Outlet</label>
        <div class="relative flex-1">
          <select v-model="selectedOutlet" class="w-full text-center py-2 pl-3 pr-8 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition cursor-pointer font-semibold uppercase tracking-wider">
            <option v-for="o in outlets" :key="o.code" :value="o.code">{{ o.name }}</option>
          </select>
          <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Layout: Left Sidebar for Areas, Right for Grid -->
    <div class="flex-1 flex gap-6 overflow-hidden min-h-0">
      <!-- Left Areas Sidebar -->
      <div class="w-60 bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex flex-col shrink-0">
        <button @click="handleAddArea" class="w-full flex items-center justify-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition mb-4">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Thêm khu vực
        </button>

        <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
          <div
            v-for="a in areas"
            :key="a.id"
            @click="activeAreaId = a.id"
            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-xs font-bold tracking-wide uppercase transition relative overflow-hidden group cursor-pointer"
            :class="activeAreaId === a.id ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'"
          >
            <!-- Highlight bar -->
            <span v-if="activeAreaId === a.id" class="absolute left-0 top-1 bottom-1 w-[3px] rounded-r-full bg-sky-500"></span>
            
            <span class="truncate pr-2">{{ a.name }}</span>

            <!-- Edit/Delete actions on hover -->
            <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
              <button @click="handleEditArea(a, $event)" class="p-1 hover:text-sky-500 rounded hover:bg-sky-200/30 text-slate-400 transition" title="Sửa">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
              </button>
              <button @click="handleDeleteArea(a, $event)" class="p-1 hover:text-rose-500 rounded hover:bg-rose-100/30 text-slate-400 transition" title="Xóa">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>
          
          <div v-if="areas.length === 0" class="text-slate-400 italic text-center py-6 text-[10px]">
            Chưa có khu vực nào
          </div>
        </div>
      </div>

      <!-- Right Main Grid area -->
      <div class="flex-1 bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm flex flex-col overflow-hidden">
        <!-- Top Toolbar -->
        <div class="flex items-center justify-between mb-6 shrink-0">
          <div class="text-xs font-bold text-slate-700 uppercase tracking-wider">
            {{ activeArea ? `Sơ đồ bàn: ${activeArea.name}` : 'Chi tiết bàn ăn' }}
          </div>
          <div class="flex items-center gap-3">
            <button @click="handleAddTable" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm hover:shadow active:scale-[0.98] transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Thêm bàn
            </button>
            <button @click="handleAddTableQuick" class="flex items-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 px-4 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition">
              <!-- Lightning icon -->
              <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              Thêm bàn nhanh
            </button>
          </div>
        </div>

        <!-- Grid Container -->
        <div class="flex-1 overflow-y-auto pr-2 flex flex-col gap-6">
          <div v-for="row in rows" :key="row.id" class="flex flex-col gap-3 border-b border-slate-100 pb-4 last:border-0 last:pb-0">
            <!-- Row header with label and Delete button -->
            <div class="flex items-center gap-3">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ row.name }}</span>
              <button @click="handleDeleteRow(row.id)" class="flex items-center gap-1 bg-rose-50 hover:bg-rose-100/80 text-rose-600 px-2.5 py-1 rounded-md text-[10px] font-bold active:scale-[0.95] transition uppercase tracking-wide">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Xóa
              </button>
            </div>

            <!-- List of tables in row -->
            <div class="flex flex-wrap gap-4">
              <div v-for="tbl in row.tables" :key="tbl.id" @click="handleEditTable(tbl)" class="relative group cursor-pointer">
                <div class="w-28 h-16 rounded-xl border border-slate-200/80 bg-white hover:bg-sky-50/20 hover:border-sky-300 flex flex-col items-center justify-center shadow-sm transition font-bold text-sky-600">
                  <span class="text-sm font-bold">{{ tbl.name }}</span>
                  <span class="text-[9px] text-slate-400 font-medium">Ghế: {{ tbl.max_seats }}</span>
                </div>
              </div>

              <!-- Placeholder if row empty -->
              <div v-if="row.tables.length === 0" class="text-xs text-slate-400 italic py-2 pl-2">
                Hàng này chưa có bàn nào. Nhấn "+ Thêm bàn" để bắt đầu.
              </div>
            </div>
          </div>

          <!-- Empty fallback -->
          <div v-if="rows.length === 0" class="flex-1 flex flex-col items-center justify-center text-slate-400 py-12">
            <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9h18M9 21V9m6 12V9m3-6H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2z" />
            </svg>
            <span class="text-sm font-semibold text-slate-500">Chưa có bàn ăn nào được định nghĩa trong khu vực này.</span>
            <div class="flex gap-3 mt-3">
              <button @click="handleAddTable" class="text-xs bg-sky-500 hover:bg-sky-600 text-white px-3 py-1.5 rounded-lg font-semibold active:scale-95 transition">Thêm bàn</button>
              <button @click="handleAddTableQuick" class="text-xs bg-sky-50 border border-sky-200 text-sky-700 hover:bg-sky-100 px-3 py-1.5 rounded-lg font-semibold active:scale-95 transition">Thêm nhanh</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <AddLocationModal 
      v-if="showLocationModal"
      :show="showLocationModal"
      :location="editingLocation"
      :outlet-code="selectedOutlet"
      @close="showLocationModal = false"
      @save="handleSaveLocation"
    />

    <AddTableModal 
      v-if="showTableModal"
      :show="showTableModal"
      :mode="tableModalMode"
      :location="activeArea"
      :table="selectedTable"
      :suggested-name="suggestedName"
      :suggested-row="suggestedRow"
      :suggested-col="suggestedCol"
      @close="showTableModal = false"
      @saveSingle="handleSaveTableSingle"
      @saveBulk="handleSaveTableBulk"
      @delete="handleDeleteTable"
    />

    <ConfirmReasonModal
      :is-open="showReasonModal"
      title="Xác nhận xóa"
      @close="showReasonModal = false"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>
