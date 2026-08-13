<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Plus, FileSpreadsheet, ClipboardList, ChevronLeft, ChevronRight, X, Trash2, Save, BarChart2, ChevronDown } from '@lucide/vue'
import http from '@/services/http'

const route = useRoute()

// ─── UI State ────────────────────────────────────────────────────
const showAddModal         = ref(false)
const showCheckModal       = ref(false)
const showEditModal        = ref(false)
const showProductSearch    = ref(false)
const showAddProductCheckModal = ref(false)
const showTransferModal    = ref(false)
const showGroupDropdown    = ref(false)
const isLoading            = ref(false)
const isSaving             = ref(false)
const isBillLoading        = ref(false)

// ─── Warehouses ───────────────────────────────────────────────────
const warehouses        = ref([])         // danh sách kho
const activeWarehouseId = ref(null)       // kho đang xem
const activeWarehouse   = computed(() => warehouses.value.find(w => w.id === activeWarehouseId.value))

// Thêm kho form
const newWarehouse = ref({ name: '', outlet_id: '' })
const hkOutlets    = ref([])   // danh sách outlet của HK department

// Sửa kho
const editWarehouse = ref({ id: null, name: '', outlet_id: '' })

// ─── Month / Calendar ─────────────────────────────────────────────
const currentMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM

const days = computed(() => {
  if (!currentMonth.value) return []
  const [year, month] = currentMonth.value.split('-')
  const daysInMonth = new Date(year, month, 0).getDate()
  return Array.from({ length: daysInMonth }, (_, i) => i + 1)
})

// ─── Inventory Check (Phiếu kiểm kê) ─────────────────────────────
const currentCheck         = ref(null)    // phiếu kiểm kê tháng hiện tại
const productsInStock      = ref([])      // sản phẩm isInStock=1 để chọn
const selectedProductIds   = ref([])      // checkbox chọn SP để thêm vào phiếu
const checkForm            = ref({ month: '', note: '' })

// ─── Daily Logs ──────────────────────────────────────────────────
// logsMap: { product_id: { 'YYYY-MM-DD': { receive, export, transfer } } }
const logsMap = ref({})

// ─── Transfer ────────────────────────────────────────────────────
const transferForm = ref({
  warehouse_id: null,
  transfer_to_warehouse_id: null,
  product_id: null,
  date: new Date().toISOString().slice(0, 10),
  quantity: '',
})
const transferProductLabel = ref('')

// ─── Filter State ────────────────────────────────────────────────
const filterState = ref({
  search: '',
  warningOnly: false,
  sortBy: 'name_asc',
  activity: 'all',
})

onMounted(() => {
  if (route.query.warningOnly === 'true') filterState.value.warningOnly = true
  document.addEventListener('click', handleOutsideClick)
  loadInitialData()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
})

watch(() => route.query.warningOnly, v => { filterState.value.warningOnly = (v === 'true') })

// Khi đổi kho hoặc tháng → reload
watch([activeWarehouseId, currentMonth], ([wId, month]) => {
  if (wId && month) {
    loadCheckAndLogs()
  }
})

// ─── Loading ─────────────────────────────────────────────────────
const triggerSearchLoading = () => {
  isLoading.value = true
  setTimeout(() => { isLoading.value = false }, 400)
}

watch(filterState, () => { triggerSearchLoading() }, { deep: true })

// ─── API Calls ───────────────────────────────────────────────────
async function loadInitialData() {
  isLoading.value = true
  try {
    const [warehousesRes, outletsRes] = await Promise.all([
      http.get('/warehouses'),
      http.get('/outlets/hk'),
    ])
    warehouses.value = warehousesRes.data.data || []
    hkOutlets.value  = outletsRes.data.data || []
    if (warehouses.value.length > 0) {
      activeWarehouseId.value = warehouses.value[0].id
    }
  } catch (e) {
    console.error('loadInitialData error', e)
  } finally {
    isLoading.value = false
  }
}

async function loadCheckAndLogs() {
  if (!activeWarehouseId.value || !currentMonth.value) return
  isLoading.value = true
  try {
    const [checkRes, logsRes] = await Promise.all([
      http.get('/inventory/checks', {
        params: { warehouse_id: activeWarehouseId.value, month: currentMonth.value }
      }),
      http.get('/inventory/logs', {
        params: { warehouse_id: activeWarehouseId.value, month: currentMonth.value }
      }),
    ])
    currentCheck.value = checkRes.data.data
    logsMap.value = logsRes.data.data || {}
  } catch (e) {
    console.error('loadCheckAndLogs error', e)
  } finally {
    isLoading.value = false
  }
}

async function loadProductsInStock() {
  try {
    const res = await http.get('/inventory/products-in-stock')
    productsInStock.value = res.data.data || []
  } catch (e) {
    console.error('loadProductsInStock error', e)
  }
}

// ─── Warehouse CRUD ───────────────────────────────────────────────
async function addWarehouse() {
  if (!newWarehouse.value.name.trim()) return
  isSaving.value = true
  try {
    const res = await http.post('/warehouses', newWarehouse.value)
    warehouses.value.push(res.data.data)
    if (!activeWarehouseId.value) activeWarehouseId.value = res.data.data.id
    showAddModal.value = false
    newWarehouse.value = { name: '', outlet_id: '' }
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi thêm kho')
  } finally {
    isSaving.value = false
  }
}

function openEditModal(warehouse) {
  editWarehouse.value = { id: warehouse.id, name: warehouse.name, outlet_id: warehouse.outlet_id || '' }
  showEditModal.value = true
}

async function updateWarehouse() {
  isSaving.value = true
  try {
    const res = await http.put(`/warehouses/${editWarehouse.value.id}`, {
      name: editWarehouse.value.name,
      outlet_id: editWarehouse.value.outlet_id,
    })
    const idx = warehouses.value.findIndex(w => w.id === editWarehouse.value.id)
    if (idx !== -1) warehouses.value[idx] = res.data.data
    showEditModal.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi cập nhật kho')
  } finally {
    isSaving.value = false
  }
}

async function deleteWarehouse() {
  if (!confirm(`Xóa kho "${editWarehouse.value.name}"?`)) return
  try {
    await http.delete(`/warehouses/${editWarehouse.value.id}`)
    warehouses.value = warehouses.value.filter(w => w.id !== editWarehouse.value.id)
    if (activeWarehouseId.value === editWarehouse.value.id) {
      activeWarehouseId.value = warehouses.value[0]?.id || null
    }
    showEditModal.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi xóa kho')
  }
}

// ─── Inventory Check ─────────────────────────────────────────────
async function openCheckModal() {
  checkForm.value = { month: currentMonth.value, note: '' }
  showCheckModal.value = true // Open modal immediately
  await loadProductsInStock() // Load products in background
}

async function createOrLoadCheck() {
  isSaving.value = true
  try {
    if (!currentCheck.value) {
      const res = await http.post('/inventory/checks', {
        warehouse_id: activeWarehouseId.value,
        month: checkForm.value.month,
        note: checkForm.value.note,
      })
      currentCheck.value = res.data.data
    }
  } catch (e) {
    // Phiếu đã tồn tại thì reload
    await loadCheckAndLogs()
  } finally {
    isSaving.value = false
  }
}

async function addProductsToCheck() {
  if (!selectedProductIds.value.length) return
  isSaving.value = true
  try {
    if (!currentCheck.value) await createOrLoadCheck()
    const res = await http.post(`/inventory/checks/${currentCheck.value.id}/items`, {
      product_ids: selectedProductIds.value,
    })
    currentCheck.value = res.data.data
    selectedProductIds.value = []
    showAddProductCheckModal.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi thêm sản phẩm')
  } finally {
    isSaving.value = false
  }
}

async function updateCheckItem(itemId, field, value) {
  if (!currentCheck.value) return
  try {
    const payload = {}
    payload[field] = parseFloat(value) || 0
    const res = await http.put(
      `/inventory/checks/${currentCheck.value.id}/items/${itemId}`,
      payload
    )
    const idx = currentCheck.value.items.findIndex(i => i.id === itemId)
    if (idx !== -1) currentCheck.value.items[idx] = res.data.data
  } catch (e) {
    console.error('updateCheckItem error', e)
  }
}

async function deleteCheck() {
  if (!currentCheck.value) return
  if (!confirm('Xóa phiếu kiểm kê tháng này?')) return
  try {
    await http.delete(`/inventory/checks/${currentCheck.value.id}`)
    currentCheck.value = null
    showCheckModal.value = false
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi xóa phiếu kiểm kê')
  }
}

// ─── Daily Log Upsert (inline editing) ───────────────────────────
// debounce map: { key: timerId }
const debounceMap = {}

function onLogInput(productId, day, field, value) {
  const dateStr = `${currentMonth.value}-${String(day).padStart(2, '0')}`
  const key = `${productId}-${dateStr}-${field}`
  clearTimeout(debounceMap[key])
  debounceMap[key] = setTimeout(() => saveLog(productId, dateStr, field, value), 700)
}

async function saveLog(productId, date, field, value) {
  try {
    const payload = { warehouse_id: activeWarehouseId.value, date, product_id: productId }
    payload[field] = parseFloat(value) || 0
    const res = await http.put('/inventory/logs', payload)
    const logEntry = res.data.data
    if (!logsMap.value[productId]) logsMap.value[productId] = {}
    if (!logsMap.value[productId][date]) logsMap.value[productId][date] = {}
    logsMap.value[productId][date][field] = logEntry[field]
  } catch (e) {
    console.error('saveLog error', e)
  }
}

// Lấy giá trị log cho 1 ô
function getLogVal(productId, day, field) {
  const dateStr = `${currentMonth.value}-${String(day).padStart(2, '0')}`
  return logsMap.value[productId]?.[dateStr]?.[field] || ''
}

// ─── Get Bill ────────────────────────────────────────────────────
async function getBill(day) {
  const date = `${currentMonth.value}-${String(day).padStart(2, '0')}`
  isBillLoading.value = true
  try {
    const res = await http.post('/inventory/get-bill', {
      warehouse_id: activeWarehouseId.value,
      date,
    })
    alert(res.data.message)
    await loadCheckAndLogs()
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi lấy dữ liệu bill')
  } finally {
    isBillLoading.value = false
  }
}

// ─── Transfer ────────────────────────────────────────────────────
function openTransferModal(item) {
  transferForm.value = {
    warehouse_id: activeWarehouseId.value,
    transfer_to_warehouse_id: null,
    product_id: item.product_id,
    date: new Date().toISOString().slice(0, 10),
    quantity: '',
  }
  transferProductLabel.value = item.product_name
  showTransferModal.value = true
}

async function submitTransfer() {
  isSaving.value = true
  try {
    const res = await http.post('/inventory/transfer', {
      ...transferForm.value,
      quantity: parseFloat(transferForm.value.quantity),
    })
    alert(res.data.message)
    showTransferModal.value = false
    await loadCheckAndLogs()
  } catch (e) {
    alert(e.response?.data?.message || 'Lỗi khi chuyển kho')
  } finally {
    isSaving.value = false
  }
}

// ─── Computed: table items từ phiếu kiểm kê ──────────────────────
const tableItems = computed(() => {
  if (!currentCheck.value?.items) return []
  let items = [...currentCheck.value.items]

  // Filter search
  if (filterState.value.search) {
    const q = filterState.value.search.toLowerCase()
    items = items.filter(i => i.product_name?.toLowerCase().includes(q))
  }

  // Tính tồn cuối cho mỗi sản phẩm
  items = items.map(item => {
    const totalReceive  = sumLogMonth(item.product_id, 'receive')
    const totalExport   = sumLogMonth(item.product_id, 'export')
    const totalTransfer = sumLogMonth(item.product_id, 'transfer')
    const finalStock = Math.max(0, (item.well_balance || 0) + totalReceive - totalExport - totalTransfer)

    return { ...item, totalReceive, totalExport, totalTransfer, finalStock }
  })

  // Warning only
  if (filterState.value.warningOnly) {
    items = items.filter(i => i.finalStock < (i.well_balance * 0.2 || 100))
  }

  // Activity filter
  if (filterState.value.activity === 'has_activity') {
    items = items.filter(i => i.totalReceive > 0 || i.totalExport > 0 || i.totalTransfer > 0)
  } else if (filterState.value.activity === 'no_activity') {
    items = items.filter(i => i.totalReceive === 0 && i.totalExport === 0 && i.totalTransfer === 0)
  }

  // Sort
  items.sort((a, b) => {
    if (filterState.value.sortBy === 'name_asc')   return (a.product_name||'').localeCompare(b.product_name||'')
    if (filterState.value.sortBy === 'name_desc')  return (b.product_name||'').localeCompare(a.product_name||'')
    if (filterState.value.sortBy === 'stock_asc')  return a.finalStock - b.finalStock
    if (filterState.value.sortBy === 'stock_desc') return b.finalStock - a.finalStock
    return 0
  })

  return items
})

// Tính tổng log cả tháng cho 1 sản phẩm
function sumLogMonth(productId, field) {
  const productLogs = logsMap.value[productId] || {}
  return Object.values(productLogs).reduce((sum, dayLog) => sum + (dayLog[field] || 0), 0)
}

// ─── Helpers ─────────────────────────────────────────────────────
const selectedGroupLabel = computed(() => 'Tất cả')

const handleOutsideClick = (e) => {
  if (!e.target.closest('.group-dropdown-wrapper')) showGroupDropdown.value = false
}

// toggle tất cả SP checkbox
// Expand/collapse states for products selection tree
const expandedOutlets = ref({})
const expandedCategories = ref({})

function toggleOutlet(outletId) {
  expandedOutlets.value[outletId] = !expandedOutlets.value[outletId]
}

function toggleCategory(catId) {
  expandedCategories.value[catId] = !expandedCategories.value[catId]
}

// Flat list of all products in stock tree (for selection logic)
const allProducts = computed(() => {
  if (!productsInStock.value) return []
  return productsInStock.value.flatMap(wh => 
    wh.categories.flatMap(cat => cat.products)
  )
})

const isAllSelected = computed(() => {
  const list = allProducts.value
  return list.length > 0 && selectedProductIds.value.length === list.length
})

function toggleSelectAll(checked) {
  if (checked) {
    selectedProductIds.value = allProducts.value.map(p => p.id)
  } else {
    selectedProductIds.value = []
  }
}

// Outlet select helpers
function isOutletSelected(outlet) {
  const productIds = outlet.categories.flatMap(c => c.products.map(p => p.id))
  return productIds.length > 0 && productIds.every(id => selectedProductIds.value.includes(id))
}

function toggleOutletSelect(outlet, checked) {
  const productIds = outlet.categories.flatMap(c => c.products.map(p => p.id))
  if (checked) {
    productIds.forEach(id => {
      if (!selectedProductIds.value.includes(id)) selectedProductIds.value.push(id)
    })
  } else {
    selectedProductIds.value = selectedProductIds.value.filter(id => !productIds.includes(id))
  }
}

// Category select helpers
function isCategorySelected(cat) {
  const productIds = cat.products.map(p => p.id)
  return productIds.length > 0 && productIds.every(id => selectedProductIds.value.includes(id))
}

function toggleCategorySelect(cat, checked) {
  const productIds = cat.products.map(p => p.id)
  if (checked) {
    productIds.forEach(id => {
      if (!selectedProductIds.value.includes(id)) selectedProductIds.value.push(id)
    })
  } else {
    selectedProductIds.value = selectedProductIds.value.filter(id => !productIds.includes(id))
  }
}

const otherWarehouses = computed(() =>
  warehouses.value.filter(w => w.id !== activeWarehouseId.value && w.is_active)
)
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">

    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-4 shrink-0">
      <!-- Warehouse Tabs -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-white rounded-t-xl">
        <div class="flex items-center gap-6 pt-2 overflow-x-auto hk-scroll">
          <!-- Tab từng kho -->
          <div
            v-for="wh in warehouses" :key="wh.id"
            class="group flex items-center gap-1.5 cursor-pointer shrink-0"
            @click="activeWarehouseId = wh.id"
          >
            <h2
              class="text-[13px] font-black tracking-wide uppercase pb-2 transition-colors relative"
              :class="activeWarehouseId === wh.id ? 'text-[var(--hk-primary-dark)]' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ wh.name }}
              <div v-if="activeWarehouseId === wh.id" class="absolute bottom-0 left-0 w-full h-0.5 bg-[var(--hk-primary-dark)] rounded-t-full"></div>
            </h2>
            <button
              @click.stop="openEditModal(wh)"
              class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-[var(--hk-primary-dark)] hover:bg-slate-50 rounded-full -mt-2 bg-white cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
          <!-- Placeholder nếu chưa có kho -->
          <span v-if="warehouses.length === 0" class="text-sm text-slate-400 italic pb-2">Chưa có kho nào</span>
        </div>
        <button @click="showAddModal = true" class="btn-primary flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg shadow-sm cursor-pointer shrink-0">
          <Plus class="w-4 h-4" stroke-width="2.5" />
          Thêm Kho
        </button>
      </div>

      <!-- Toolbar -->
      <div class="px-5 py-4 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Month Picker -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500 uppercase tracking-wide">Tháng:</span>
            <input type="month" v-model="currentMonth" class="w-36 text-[12px] font-bold text-slate-705 bg-white border border-slate-350 rounded-lg px-2.5 py-1.5 outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer" />
          </div>

          <!-- Warning Only Switch -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500">Cảnh báo tồn:</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="filterState.warningOnly" class="sr-only peer" />
              <div class="w-8 h-4.5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-[var(--hk-primary-dark)]"></div>
            </label>
          </div>

          <!-- Sort -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-500">Sắp xếp:</span>
            <select v-model="filterState.sortBy" class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-705 bg-white focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] cursor-pointer shadow-sm font-medium">
              <option value="name_asc">Tên A-Z</option>
              <option value="name_desc">Tên Z-A</option>
              <option value="stock_asc">Tồn cuối tăng dần</option>
              <option value="stock_desc">Tồn cuối giảm dần</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-4 py-2 rounded-lg transition-all text-xs font-bold flex items-center gap-2 shadow-sm cursor-pointer">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
            Xuất Excel
          </button>
          <button @click="openCheckModal" class="btn-primary px-5 py-2 rounded-lg transition-all text-xs font-bold shadow-sm flex items-center gap-2 cursor-pointer">
            <ClipboardList class="w-4 h-4" stroke-width="2.5" />
            Kiểm Kê Định Kỳ
          </button>
        </div>
      </div>

      <!-- Activity Filter Chips -->
      <div class="px-5 py-2 border-t border-slate-100 flex items-center gap-2 text-xs bg-slate-50/25 rounded-b-xl">
        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mr-1">Phát sinh:</span>
        <button
          v-for="st in [
            { label: 'Tất cả sản phẩm', value: 'all' },
            { label: 'Có phát sinh', value: 'has_activity' },
            { label: 'Không phát sinh', value: 'no_activity' },
          ]"
          :key="st.value"
          @click="filterState.activity = st.value"
          class="px-2.5 py-1 rounded-full text-xs transition-all duration-200 cursor-pointer border"
          :class="filterState.activity === st.value
            ? 'bg-[var(--hk-primary-light)] text-sky-850 border-[var(--hk-primary)] font-bold shadow-sm'
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
        >
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse text-[13px] whitespace-nowrap min-w-max">
          <thead class="sticky top-0 z-20 backdrop-blur-md bg-white/95">
            <tr class="bg-slate-100 text-slate-750 font-bold border-b border-slate-300 uppercase tracking-wider text-[11px]">
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-64 min-w-[256px] text-center align-middle sticky left-0 z-30 shadow-[1px_0_0_0_#e2e8f0] bg-slate-100">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="flex items-center">
                    Sản Phẩm <Search @click="showProductSearch = !showProductSearch" class="w-3.5 h-3.5 inline-block ml-1.5 text-slate-400 cursor-pointer hover:text-[var(--hk-primary-dark)] transition-colors" />
                  </div>
                  <div v-if="showProductSearch" class="relative w-full mt-1 px-2">
                    <Search class="absolute left-4 top-1.5 w-3.5 h-3.5 text-slate-400" />
                    <input type="text" v-model="filterState.search" placeholder="Tìm kiếm sản phẩm..." class="w-full pl-8 pr-3 py-1.5 text-xs font-normal bg-white border border-slate-300 rounded-md focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] outline-none transition-all shadow-sm" />
                  </div>
                </div>
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-20 min-w-[80px] text-center align-middle sticky left-[256px] z-30 shadow-[1px_0_0_0_#e2e8f0] bg-slate-100">Tồn ĐK</th>
              <th v-for="day in days" :key="day" colspan="3" class="py-2 px-2 text-center border-r border-slate-200" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.08)]' : 'bg-slate-50/50'">
                <div class="flex flex-col items-center gap-0.5">
                  <span>{{ day }}</span>
                  <button
                    @click="getBill(day)"
                    :disabled="!activeWarehouse?.outlet_id || isBillLoading"
                    class="text-[9px] px-1 py-0.5 rounded bg-sky-100 hover:bg-sky-200 text-sky-700 font-bold transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                    title="Lấy dữ liệu xuất từ bill"
                  >
                    📋 Bill
                  </button>
                </div>
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLN</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLX</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLC</th>
              <th rowspan="2" class="py-3 px-4 border-l border-slate-200 bg-slate-100 text-center align-middle sticky right-0 shadow-[-1px_0_0_0_#e2e8f0] text-[var(--hk-primary-dark)] font-black">Tồn Cuối</th>
            </tr>
            <tr class="bg-slate-100/80 text-slate-500 font-bold border-b border-slate-250 text-[10px] uppercase">
              <template v-for="day in days" :key="'sub'+day">
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Nhập</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Xuất</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.04)]' : 'bg-slate-50/20'">Chuyển</th>
              </template>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Skeleton -->
            <template v-if="isLoading">
              <tr v-for="i in 4" :key="'sk-'+i" class="animate-pulse">
                <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-40 bg-slate-200 rounded"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <template v-for="day in days" :key="'sk-day-'+day">
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                </template>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-white shadow-[-1px_0_0_0_#e2e8f0]"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
              </tr>
            </template>

            <!-- Chưa có kho -->
            <tr v-else-if="!activeWarehouseId">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl">🏪</span>
                  <p class="font-bold text-slate-700 text-sm">Chưa có kho nào</p>
                  <p class="text-xs">Nhấn <strong>Thêm Kho</strong> để bắt đầu</p>
                </div>
              </td>
            </tr>

            <!-- Chưa có phiếu kiểm kê -->
            <tr v-else-if="!currentCheck">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl">📋</span>
                  <p class="font-bold text-slate-700 text-sm">Chưa có phiếu kiểm kê tháng {{ currentMonth }}</p>
                  <p class="text-xs">Nhấn <strong>Kiểm Kê Định Kỳ</strong> để tạo phiếu và thêm sản phẩm</p>
                </div>
              </td>
            </tr>

            <!-- Không có SP sau filter -->
            <tr v-else-if="tableItems.length === 0">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl animate-bounce">📦</span>
                  <p class="font-bold text-slate-700 text-sm">Không có dữ liệu tồn kho trùng khớp</p>
                  <p class="text-xs text-slate-550">Thử tắt bộ lọc cảnh báo hoặc thay đổi từ khóa tìm kiếm.</p>
                </div>
              </td>
            </tr>

            <!-- Data Rows -->
            <tr
              v-else
              v-for="item in tableItems" :key="'item-'+item.id"
              class="hover:bg-slate-50 transition-colors border-b border-slate-100 group"
            >
              <!-- Product name + actions -->
              <td class="py-2 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white group-hover:bg-slate-50 font-semibold min-w-[256px] text-slate-600 shadow-[1px_0_0_0_#e2e8f0]">
                <div class="flex items-center justify-between gap-1">
                  <span>{{ item.product_name }}</span>
                  <div class="flex items-center gap-0.5">
                    <span v-if="item.finalStock < 50 && item.well_balance > 0" class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-800 border border-rose-200 shrink-0">🚨 LOW</span>
                    <button @click="openTransferModal(item)" title="Chuyển kho" class="opacity-0 group-hover:opacity-100 transition-opacity ml-1 p-1 rounded hover:bg-sky-100 text-slate-400 hover:text-sky-600 cursor-pointer">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </button>
                  </div>
                </div>
              </td>
              <!-- Tồn đầu kỳ -->
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 font-semibold sticky left-[256px] z-10 bg-white group-hover:bg-slate-50 min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]">
                {{ item.well_balance || '' }}
              </td>
              <!-- Nhật ký từng ngày (3 cột: nhập / xuất / chuyển) -->
              <template v-for="day in days" :key="'item-day-'+day">
                <td class="py-0.5 px-0.5 border-r border-slate-200 text-center" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''">
                  <input
                    type="number" min="0"
                    :value="getLogVal(item.product_id, day, 'receive')"
                    @change="e => onLogInput(item.product_id, day, 'receive', e.target.value)"
                    class="w-12 text-center text-[11px] border border-transparent hover:border-slate-300 focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] rounded outline-none bg-transparent focus:bg-white transition-all py-1"
                    placeholder="0"
                  />
                </td>
                <td class="py-0.5 px-0.5 border-r border-slate-200 text-center" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''">
                  <input
                    type="number" min="0"
                    :value="getLogVal(item.product_id, day, 'export')"
                    @change="e => onLogInput(item.product_id, day, 'export', e.target.value)"
                    class="w-12 text-center text-[11px] border border-transparent hover:border-slate-300 focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] rounded outline-none bg-transparent focus:bg-white transition-all py-1"
                    placeholder="0"
                  />
                </td>
                <td class="py-0.5 px-0.5 border-r border-slate-200 text-center" :class="day % 2 === 0 ? 'bg-[rgba(151,213,255,0.02)]' : ''">
                  <input
                    type="number" min="0"
                    :value="getLogVal(item.product_id, day, 'transfer')"
                    @change="e => onLogInput(item.product_id, day, 'transfer', e.target.value)"
                    class="w-12 text-center text-[11px] border border-transparent hover:border-slate-300 focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] rounded outline-none bg-transparent focus:bg-white transition-all py-1"
                    placeholder="0"
                  />
                </td>
              </template>
              <!-- Tổng tháng -->
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalReceive || '' }}</td>
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalExport || '' }}</td>
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalTransfer || '' }}</td>
              <!-- Tồn cuối -->
              <td class="py-2 px-4 border-l border-slate-200 text-right font-black sticky right-0 bg-white group-hover:bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0] text-sm"
                :class="item.finalStock < 50 ? 'text-rose-600' : 'text-[var(--hk-primary-dark)]'"
              >
                {{ item.finalStock?.toLocaleString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Footer -->
    <div class="mt-4 flex items-center justify-between shrink-0">
      <div class="text-[12px] text-slate-550 font-semibold uppercase tracking-wider">
        {{ currentCheck ? `${tableItems.length} sản phẩm · Tháng ${currentMonth}` : 'Chưa có phiếu kiểm kê' }}
      </div>
    </div>

    <!-- ═══ MODALS ════════════════════════════════════════════════ -->

    <!-- Add Warehouse Modal -->
    <Transition name="hk-modal">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Thêm Kho Mới</h3>
            <button @click="showAddModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50">
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Tên kho <span class="text-rose-500">*</span></label>
              <input type="text" v-model="newWarehouse.name" placeholder="VD: Kho Minibar Tầng 1..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Outlet (để lấy Bill)</label>
              <select v-model="newWarehouse.outlet_id" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm">
                <option value="">-- Chọn outlet --</option>
                <option v-for="ol in hkOutlets" :key="ol.OutletId" :value="ol.OutletId">{{ ol.Name }}</option>
              </select>
              <p class="text-[11px] text-slate-500">Gán outlet để sử dụng tính năng "Get Bill" tự động</p>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3 font-semibold">
            <button @click="showAddModal = false" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">Đóng</button>
            <button @click="addWarehouse" :disabled="isSaving || !newWarehouse.name.trim()" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Edit Warehouse Modal -->
    <Transition name="hk-modal">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Cập nhật Kho</h3>
            <button @click="showEditModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50">
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Tên kho</label>
              <input type="text" v-model="editWarehouse.name" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Outlet (để lấy Bill)</label>
              <select v-model="editWarehouse.outlet_id" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm">
                <option value="">-- Không gán --</option>
                <option v-for="ol in hkOutlets" :key="ol.OutletId" :value="ol.OutletId">{{ ol.Name }}</option>
              </select>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3">
            <button @click="showEditModal = false" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">Đóng</button>
            <button @click="deleteWarehouse" class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Trash2 class="w-4 h-4" /> Xóa
            </button>
            <button @click="updateWarehouse" :disabled="isSaving" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Periodic Inventory Check Modal -->
    <Transition name="hk-modal">
      <div v-if="showCheckModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[1000px] flex flex-col overflow-hidden transform transition-all max-h-[90vh] border border-slate-200">
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[16px] uppercase tracking-wide">Kiểm Kê Tồn Kho Định Kỳ</h3>
            <button @click="showCheckModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-5 flex flex-col gap-5 overflow-y-auto bg-slate-50 flex-1 hk-scroll">
            <!-- Header form -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-4 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Tháng / Năm</label>
                <input type="month" v-model="checkForm.month" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm cursor-pointer bg-white" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Mã Phiếu</label>
                <input type="text" :value="currentCheck?.id || 'Mới'" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Kho</label>
                <input type="text" :value="activeWarehouse?.name || ''" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Ghi Chú</label>
                <input type="text" v-model="checkForm.note" placeholder="Nhập ghi chú..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm bg-white" />
              </div>
            </div>

            <!-- Items table -->
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
              <table class="w-full text-left border-collapse text-[12px] whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-600 font-bold">
                  <tr>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-12">#</th>
                    <th class="py-3 px-4 border-r border-slate-200">Tên SP</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center">Đơn Vị</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-28">Tồn Đầu Kì</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-28">SL Thực Tế</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-24">Chênh Lệch</th>
                    <th class="py-3 px-4 text-center">Ghi Chú</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700">
                  <tr v-if="!currentCheck || !currentCheck.items?.length">
                    <td colspan="7" class="py-12 text-center text-slate-400">
                      <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl">📦</span>
                        <p class="font-semibold text-slate-600">Chưa có sản phẩm trong phiếu kiểm kê</p>
                        <p class="text-xs">Nhấn <strong>Thêm SP</strong> để thêm sản phẩm</p>
                      </div>
                    </td>
                  </tr>
                  <tr v-else v-for="(item, idx) in currentCheck.items" :key="item.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500 font-mono">{{ idx + 1 }}</td>
                    <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">{{ item.product_name }}</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-600">{{ item.unit || '—' }}</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">
                      <input type="number" min="0"
                        :value="item.well_balance"
                        @change="e => updateCheckItem(item.id, 'well_balance', e.target.value)"
                        class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white"
                      />
                    </td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">
                      <input type="number" min="0"
                        :value="item.stoke_take"
                        @change="e => updateCheckItem(item.id, 'stoke_take', e.target.value)"
                        class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white"
                      />
                    </td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center font-bold"
                      :class="item.different_qty > 0 ? 'text-emerald-600' : item.different_qty < 0 ? 'text-rose-600' : 'text-[var(--hk-primary-dark)]'"
                    >
                      {{ item.different_qty >= 0 ? '+' : '' }}{{ item.different_qty }}
                    </td>
                    <td class="py-2 px-3">
                      <input type="text" :value="item.note"
                        @change="e => updateCheckItem(item.id, 'note', e.target.value)"
                        placeholder="..." class="w-full text-[13px] border border-slate-300 rounded-md px-2 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white"
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-end gap-3 shrink-0">
            <button @click="showAddProductCheckModal = true; loadProductsInStock()" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm SP
            </button>
            <button @click="deleteCheck" v-if="currentCheck" class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Trash2 class="w-4 h-4" /> Xóa Phiếu
            </button>
            <button @click="createOrLoadCheck" :disabled="isSaving" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu Phiếu' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add Products Modal (Kiểm kê) -->
    <Transition name="hk-modal">
      <div v-if="showAddProductCheckModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[420px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Chọn Sản Phẩm</h3>
            <button @click="showAddProductCheckModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-0 flex flex-col max-h-[60vh] overflow-y-auto bg-white hk-scroll">
            <table class="w-full text-left border-collapse text-[13px]">
              <thead class="bg-slate-100 sticky top-0 z-10 shadow-sm border-b border-slate-200 font-bold uppercase">
                <tr>
                  <th class="py-2.5 px-3 border-r border-slate-200 w-12 text-center">
                    <input type="checkbox" :checked="isAllSelected" @change="e => toggleSelectAll(e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  </th>
                  <th class="py-2.5 px-4 text-slate-700 text-[11px]">Sản Phẩm Buồng Phòng</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-700">
                <tr v-if="productsInStock.length === 0">
                  <td colspan="2" class="py-8 text-center text-slate-400 text-sm">Không có sản phẩm buồng phòng nào</td>
                </tr>
                <template v-else v-for="wh in productsInStock" :key="'wh-'+wh.id">
                  <!-- Outlet Row (e.g. Minibar, Giặt ủi...) -->
                  <tr class="bg-slate-50 hover:bg-slate-100 transition-colors">
                    <td class="py-2.5 px-3 border-r border-slate-200 text-center w-12">
                      <input type="checkbox" :checked="isOutletSelected(wh)" @change="e => toggleOutletSelect(wh, e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                    </td>
                    <td class="py-2.5 px-4 cursor-pointer" @click="toggleOutlet(wh.id)">
                      <div class="flex items-center gap-2 select-none">
                        <div class="w-5 h-5 bg-[var(--hk-primary-dark)] flex items-center justify-center text-white text-[12px] leading-none pb-[1.5px] rounded-md shadow-sm font-bold">{{ expandedOutlets[wh.id] ? '-' : '+' }}</div>
                        <span class="font-bold text-slate-800">{{ wh.name }} ({{ wh.code }})</span>
                      </div>
                    </td>
                  </tr>
                  <!-- Categories and Products if Outlet is expanded -->
                  <template v-if="expandedOutlets[wh.id]">
                    <template v-for="cat in wh.categories" :key="'cat-'+cat.id">
                      <!-- Category Row (e.g. Nước ngọt, Bia...) -->
                      <tr class="bg-white hover:bg-slate-50/50 transition-colors">
                        <td class="py-2.5 px-3 border-r border-slate-200 text-center">
                          <input type="checkbox" :checked="isCategorySelected(cat)" @change="e => toggleCategorySelect(cat, e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                        </td>
                        <td class="py-2.5 px-4 pl-8 cursor-pointer" @click="toggleCategory(cat.id)">
                          <div class="flex items-center gap-2 select-none">
                            <div class="w-4 h-4 bg-[var(--hk-primary)] flex items-center justify-center text-white text-[11px] leading-none pb-[1px] rounded-sm shadow-sm font-bold">{{ expandedCategories[cat.id] ? '-' : '+' }}</div>
                            <span class="font-bold text-slate-700">{{ cat.name }}</span>
                          </div>
                        </td>
                      </tr>
                      <!-- Products if Category is expanded -->
                      <template v-if="expandedCategories[cat.id]">
                        <tr v-for="p in cat.products" :key="'prod-'+p.id" class="hover:bg-slate-50 transition-colors bg-white">
                          <td class="py-2.5 px-3 border-r border-slate-200 text-center">
                            <input type="checkbox" :value="p.id" v-model="selectedProductIds" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                          </td>
                          <td class="py-2.5 px-4 pl-14 text-slate-600 font-semibold select-none">{{ p.name }}</td>
                        </tr>
                      </template>
                    </template>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-between items-center shrink-0">
            <span class="text-[12px] text-slate-500">{{ selectedProductIds.length }} SP được chọn</span>
            <div class="flex gap-3">
              <button @click="showAddProductCheckModal = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">Đóng</button>
              <button @click="addProductsToCheck" :disabled="isSaving || !selectedProductIds.length" class="px-4 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
                <Save class="w-4 h-4" /> Thêm
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Transfer Modal -->
    <Transition name="hk-modal">
      <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden transform transition-all border border-slate-200">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[15px] uppercase tracking-wide">Chuyển Kho</h3>
            <button @click="showTransferModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50">
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Sản Phẩm</label>
              <input type="text" :value="transferProductLabel" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Kho nguồn</label>
                <input type="text" :value="activeWarehouse?.name" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Kho đích <span class="text-rose-500">*</span></label>
                <select v-model="transferForm.transfer_to_warehouse_id" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] transition-all bg-white shadow-sm">
                  <option :value="null">-- Chọn kho đích --</option>
                  <option v-for="w in otherWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Ngày</label>
                <input type="date" v-model="transferForm.date" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] transition-all bg-white shadow-sm" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Số lượng <span class="text-rose-500">*</span></label>
                <input type="number" min="0.001" v-model="transferForm.quantity" placeholder="0" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all bg-white shadow-sm" />
              </div>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3">
            <button @click="showTransferModal = false" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-705 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">Đóng</button>
            <button @click="submitTransfer" :disabled="isSaving || !transferForm.transfer_to_warehouse_id || !transferForm.quantity" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> {{ isSaving ? 'Đang xử lý...' : 'Chuyển Kho' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.btn-primary {
  background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5));
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
}
.btn-primary:hover {
  transform: translateY(-1px) scale(1.02);
  box-shadow: 0 4px 12px rgba(151, 213, 255, 0.4);
  filter: brightness(1.03);
}
.btn-primary:active { transform: translateY(0); }

.hk-modal-enter-active { animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.hk-modal-leave-active { animation: hkModalOut 0.2s ease-in forwards; }
@keyframes hkModalIn {
  from { opacity: 0; transform: scale(0.97) translateY(8px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes hkModalOut {
  from { opacity: 1; transform: scale(1) translateY(0); }
  to   { opacity: 0; transform: scale(0.97) translateY(8px); }
}
.hk-dropdown-enter-active { animation: hkDropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.hk-dropdown-leave-active { animation: hkDropIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) reverse; }
@keyframes hkDropIn {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.row-fade-enter-active, .row-fade-leave-active { transition: all 0.2s ease; }
.row-fade-enter-from, .row-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Input cells trong bảng */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button { opacity: 0; }
input[type="number"]:hover::-webkit-inner-spin-button,
input[type="number"]:hover::-webkit-outer-spin-button { opacity: 1; }
</style>
