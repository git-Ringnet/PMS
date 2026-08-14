<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Plus, FileSpreadsheet, ClipboardList, ChevronLeft, ChevronRight, X, Trash2, Save, BarChart2, ChevronDown, BarChart3, AlertTriangle, CheckCircle2, TrendingUp } from '@lucide/vue'
import http from '@/services/http'
import { fetchSystemDate } from '@/services/booking-service'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const route = useRoute()
const authStore = useAuthStore()
const uiStore = useUiStore()

// ─── System Date ──────────────────────────────────────────────────
const systemDate = ref('')
function isSystemDate(day) {
  if (!systemDate.value) return false
  const dateStr = `${currentMonth.value}-${String(day).padStart(2, '0')}`
  return dateStr === systemDate.value
}

// ─── Draggable Modal Positions ────────────────────────────────────
const checkModalPos = ref({ x: 0, y: 0 })
const addWhModalPos = ref({ x: 0, y: 0 })
const editWhModalPos = ref({ x: 0, y: 0 })
const prodModalPos = ref({ x: 0, y: 0 })
const transferModalPos = ref({ x: 0, y: 0 })



// ─── UI State ────────────────────────────────────────────────────
const showAddModal         = ref(false)
const showCheckModal       = ref(false)
const showEditModal        = ref(false)
const showProductSearch    = ref(false)
const showAddProductCheckModal = ref(false)
const showTransferModal    = ref(false)
const showGroupDropdown    = ref(false)
const showStatsModal       = ref(false)
const statsType            = ref('check')
const statsTitle           = ref('')
const statsData            = ref({
  total: 0,
  normal: 0,
  discrepancies: 0,
  discrepancyList: [],
  totalReceive: 0,
  totalExport: 0,
  totalTransfer: 0,
  lowStockProducts: [],
  highestActivityName: '',
  highestActivityCount: 0
})
const isLoading            = ref(false)
const isSaving             = ref(false)
const isBillLoading        = ref(false)
const billDate             = ref('')  // ngày lấy bill, default = systemDate

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
const checkForm            = ref({ month: '', note: '', created_by: '' })
const users                = ref([])

watch(currentCheck, (val) => {
  if (val) {
    checkForm.value.month = val.month || currentMonth.value
    checkForm.value.note = val.note || ''
    checkForm.value.created_by = val.created_by || authStore.user?.id || ''
  } else {
    checkForm.value = { month: currentMonth.value, note: '', created_by: authStore.user?.id || '' }
  }
}, { immediate: true })

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
    const localDate = new Date()
    let sysDate = `${localDate.getFullYear()}-${String(localDate.getMonth() + 1).padStart(2, '0')}-${String(localDate.getDate()).padStart(2, '0')}`
    try {
      const dateRes = await fetchSystemDate()
      sysDate = dateRes?.data?.data?.system_date || dateRes?.data?.system_date || sysDate
    } catch (err) {
      console.error('fetchSystemDate error', err)
    }
    systemDate.value = sysDate
    currentMonth.value = sysDate.slice(0, 7)
    transferForm.value.date = sysDate

    const [warehousesRes, outletsRes, usersRes] = await Promise.all([
      http.get('/warehouses'),
      http.get('/outlets/hk'),
      http.get('/users'),
    ])
    warehouses.value = warehousesRes.data.data || []
    hkOutlets.value  = outletsRes.data.data || []
    users.value      = usersRes.data.data || usersRes.data || []
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
    uiStore.showToast('Thêm kho mới thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi thêm kho', 'error')
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
    uiStore.showToast('Cập nhật thông tin kho thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi cập nhật kho', 'error')
  } finally {
    isSaving.value = false
  }
}

async function deleteWarehouse() {
  const confirmed = await uiStore.confirm({ message: `Xóa kho "${editWarehouse.value.name}"?` })
  if (!confirmed) return
  try {
    await http.delete(`/warehouses/${editWarehouse.value.id}`)
    warehouses.value = warehouses.value.filter(w => w.id !== editWarehouse.value.id)
    if (activeWarehouseId.value === editWarehouse.value.id) {
      activeWarehouseId.value = warehouses.value[0]?.id || null
    }
    showEditModal.value = false
    uiStore.showToast('Xóa kho thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi xóa kho', 'error')
  }
}

// ─── Inventory Check ─────────────────────────────────────────────
async function openCheckModal() {
  checkForm.value = { 
    month: currentMonth.value, 
    note: currentCheck.value?.note || '', 
    created_by: currentCheck.value?.created_by || authStore.user?.id || '' 
  }
  showCheckModal.value = true // Open modal immediately
  await loadProductsInStock() // Load products in background
}

async function createOrLoadCheck() {
  isSaving.value = true
  try {
    const res = await http.post('/inventory/checks', {
      warehouse_id: activeWarehouseId.value,
      month: checkForm.value.month,
      note: checkForm.value.note,
      created_by: checkForm.value.created_by || null,
    })
    currentCheck.value = res.data.data
    uiStore.showToast('Lưu phiếu kiểm kê thành công!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lưu phiếu kiểm kê', 'error')
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
    uiStore.showToast('Thêm sản phẩm thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi thêm sản phẩm', 'error')
  } finally {
    isSaving.value = false
  }
}

const updateTimeouts = {}
const pendingFields = {}

async function updateCheckItem(itemId, fields) {
  if (!currentCheck.value) return
  
  // Tích lũy các thay đổi locally
  pendingFields[itemId] = { ...(pendingFields[itemId] || {}), ...fields }
  
  // Cập nhật giá trị UI hiển thị lập tức để không bị lag
  const idx = currentCheck.value.items.findIndex(i => i.id === itemId)
  if (idx !== -1) {
    currentCheck.value.items[idx] = { ...currentCheck.value.items[idx], ...fields }
  }
  
  // Xóa timeout cũ nếu click liên tục
  if (updateTimeouts[itemId]) {
    clearTimeout(updateTimeouts[itemId])
  }
  
  // Đặt timeout mới (500ms) để gom các lần click lại và gửi 1 request cuối cùng
  updateTimeouts[itemId] = setTimeout(async () => {
    const fieldsToSend = { ...pendingFields[itemId] }
    delete pendingFields[itemId]
    delete updateTimeouts[itemId]
    
    try {
      const res = await http.put(
        `/inventory/checks/${currentCheck.value.id}/items/${itemId}`,
        fieldsToSend
      )
      // Chỉ cập nhật từ backend về nếu user không sửa tiếp trong lúc đang gửi request
      if (!pendingFields[itemId]) {
        const currentIdx = currentCheck.value.items.findIndex(i => i.id === itemId)
        if (currentIdx !== -1) {
          currentCheck.value.items[currentIdx] = { ...currentCheck.value.items[currentIdx], ...res.data.data }
        }
      }
    } catch (e) {
      console.error('updateCheckItem error', e)
    }
  }, 500)
}

function getInitialStock(item) {
  if (!item) return 0
  const well = parseFloat(item.well_balance) || 0
  const stoke = parseFloat(item.stoke_take) || 0
  return well > 0 ? well : stoke
}

function getInitialStockLabel(item) {
  const val = getInitialStock(item)
  return val > 0 ? val : ''
}

function onStokeTakeInput(item, val) {
  const stokeTake = parseFloat(val) || 0
  item.stoke_take = stokeTake
  
  const currentWell = parseFloat(item.well_balance) || 0
  if (currentWell === 0) {
    item.well_balance = stokeTake
    item.different_qty = 0
    updateCheckItem(item.id, { well_balance: stokeTake, stoke_take: stokeTake })
  } else {
    item.different_qty = stokeTake - currentWell
    updateCheckItem(item.id, { stoke_take: stokeTake })
  }
}

function onWellBalanceInput(item, val) {
  const wellBalance = parseFloat(val) || 0
  item.well_balance = wellBalance
  
  const currentStoke = parseFloat(item.stoke_take) || 0
  if (currentStoke === 0) {
    item.stoke_take = wellBalance
    item.different_qty = 0
    updateCheckItem(item.id, { well_balance: wellBalance, stoke_take: wellBalance })
  } else {
    item.different_qty = currentStoke - wellBalance
    updateCheckItem(item.id, { well_balance: wellBalance })
  }
}

function increaseQty(item, field) {
  const currentVal = parseFloat(item[field]) || 0
  const newVal = currentVal + 1
  if (field === 'well_balance') {
    onWellBalanceInput(item, newVal)
  } else {
    onStokeTakeInput(item, newVal)
  }
}

function decreaseQty(item, field) {
  const currentVal = parseFloat(item[field]) || 0
  const newVal = Math.max(0, currentVal - 1)
  if (field === 'well_balance') {
    onWellBalanceInput(item, newVal)
  } else {
    onStokeTakeInput(item, newVal)
  }
}

async function exportExcelCheck() {
  if (!currentCheck.value) return
  isSaving.value = true
  try {
    const res = await http.get(`/inventory/checks/${currentCheck.value.id}/export`, {
      responseType: 'blob'
    })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const link = document.createElement('a')
    link.href = window.URL.createObjectURL(blob)
    link.download = `phieu_kiem_ke_${currentMonth.value}.xlsx`
    link.click()
    window.URL.revokeObjectURL(link.href)
    uiStore.showToast('Xuất Excel thành công!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast('Lỗi khi xuất file Excel', 'error')
  } finally {
    isSaving.value = false
  }
}

function openStats() {
  if (!currentCheck.value || !currentCheck.value.items?.length) {
    uiStore.showToast('Không có dữ liệu để thống kê!', 'warning')
    return
  }
  const items = currentCheck.value.items
  const total = items.length
  const discrepancyList = items.filter(i => parseFloat(i.different_qty) !== 0)
  const discrepancies = discrepancyList.length
  const normal = total - discrepancies

  statsType.value = 'check'
  statsTitle.value = 'THỐNG KÊ PHIẾU KIỂM KÊ'
  statsData.value = {
    total,
    normal,
    discrepancies,
    discrepancyList,
    totalReceive: 0,
    totalExport: 0,
    totalTransfer: 0,
    lowStockProducts: [],
    highestActivityName: '',
    highestActivityCount: 0
  }
  showStatsModal.value = true
}

async function exportExcelLogs() {
  if (!activeWarehouseId.value || !currentMonth.value) return
  isLoading.value = true
  try {
    const res = await http.get('/inventory/logs/export', {
      params: { warehouse_id: activeWarehouseId.value, month: currentMonth.value },
      responseType: 'blob'
    })
    const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const link = document.createElement('a')
    link.href = window.URL.createObjectURL(blob)
    const whName = activeWarehouse.value?.name || 'logs'
    link.download = `nhat_ky_kho_${whName.toLowerCase().replace(/\s+/g, '_')}_${currentMonth.value}.xlsx`
    link.click()
    window.URL.revokeObjectURL(link.href)
    uiStore.showToast('Xuất Excel thành công!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast('Lỗi khi xuất file Excel', 'error')
  } finally {
    isLoading.value = false
  }
}

function openMainStats() {
  if (!tableItems.value.length) {
    uiStore.showToast('Không có dữ liệu để thống kê!', 'warning')
    return
  }
  const items = tableItems.value
  const totalProducts = items.length
  
  const totalReceive = items.reduce((sum, i) => sum + (i.totalReceive || 0), 0)
  const totalExport = items.reduce((sum, i) => sum + (i.totalExport || 0), 0)
  const totalTransfer = items.reduce((sum, i) => sum + (i.totalTransfer || 0), 0)
  
  const lowStockProducts = items.filter(i => i.finalStock < 50)
  const lowStockCount = lowStockProducts.length
  
  const highestActivityProd = [...items].sort((a, b) => {
    const actA = (a.totalReceive || 0) + (a.totalExport || 0) + (a.totalTransfer || 0)
    const actB = (b.totalReceive || 0) + (b.totalExport || 0) + (b.totalTransfer || 0)
    return actB - actA
  })[0]
  
  const highestActivityName = highestActivityProd ? highestActivityProd.product_name : ''
  const highestActivityCount = highestActivityProd ? ((highestActivityProd.totalReceive || 0) + (highestActivityProd.totalExport || 0) + (highestActivityProd.totalTransfer || 0)) : 0
  
  statsType.value = 'main'
  statsTitle.value = `THỐNG KÊ KHO THÁNG ${currentMonth.value}`
  statsData.value = {
    total: totalProducts,
    normal: totalProducts - lowStockCount,
    discrepancies: lowStockCount,
    discrepancyList: [],
    totalReceive,
    totalExport,
    totalTransfer,
    lowStockProducts,
    highestActivityName,
    highestActivityCount
  }
  showStatsModal.value = true
}

async function deleteCheck() {
  if (!currentCheck.value) return
  const confirmed = await uiStore.confirm({ message: 'Xóa phiếu kiểm kê tháng này?' })
  if (!confirmed) return
  try {
    await http.delete(`/inventory/checks/${currentCheck.value.id}`)
    currentCheck.value = null
    showCheckModal.value = false
    uiStore.showToast('Xóa phiếu kiểm kê thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi xóa phiếu kiểm kê', 'error')
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
    uiStore.showToast(res.data.message, 'success')
    await loadCheckAndLogs()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lấy dữ liệu bill', 'error')
  } finally {
    isBillLoading.value = false
  }
}

// Khi systemDate thay đổi, cập nhật billDate mặc định
watch(systemDate, (val) => {
  if (val && !billDate.value) billDate.value = val
}, { immediate: true })

function triggerGetBillByDate() {
  const dateStr = billDate.value || systemDate.value
  if (!dateStr) {
    uiStore.showToast('Chưa có ngày để lấy bill!', 'warning')
    return
  }
  if (!dateStr.startsWith(currentMonth.value)) {
    uiStore.showToast(
      `Ngày ${dateStr} không thuộc tháng đang xem (${currentMonth.value}).`,
      'warning'
    )
    return
  }
  const parts = dateStr.split('-')
  const day = parseInt(parts[2], 10)
  getBill(day)
}

// ─── Transfer ────────────────────────────────────────────────────
function openTransferModal(item, day = null) {
  let selectedDate = systemDate.value
  if (day !== null) {
    selectedDate = `${currentMonth.value}-${String(day).padStart(2, '0')}`
  }
  transferForm.value = {
    warehouse_id: activeWarehouseId.value,
    transfer_to_warehouse_id: null,
    product_id: item.product_id,
    date: selectedDate,
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
    uiStore.showToast(res.data.message, 'success')
    showTransferModal.value = false
    await loadCheckAndLogs()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi chuyển kho', 'error')
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
    const initialBalance = getInitialStock(item)
    const finalStock = initialBalance + totalReceive - totalExport - totalTransfer

    return { ...item, totalReceive, totalExport, totalTransfer, finalStock }
  })

  // Warning only
  if (filterState.value.warningOnly) {
    items = items.filter(i => {
      const init = getInitialStock(i)
      return init > 0 && i.finalStock < 50
    })
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

function createDragHandler(posRef) {
  let dragStart = { x: 0, y: 0 }
  let isDragging = false
  let rafId = null

  function onMouseMove(e) {
    if (!isDragging) return
    if (rafId) return
    rafId = requestAnimationFrame(() => {
      posRef.value.x = e.clientX - dragStart.x
      posRef.value.y = e.clientY - dragStart.y
      rafId = null
    })
  }

  function onMouseUp() {
    isDragging = false
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  }

  return function startDrag(e) {
    const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
    if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
    
    isDragging = true
    dragStart.x = e.clientX - posRef.value.x
    dragStart.y = e.clientY - posRef.value.y
    
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
  }
}

const startDragCheckModal = createDragHandler(checkModalPos)
const startDragAddWhModal = createDragHandler(addWhModalPos)
const startDragEditWhModal = createDragHandler(editWhModalPos)
const startDragProdModal = createDragHandler(prodModalPos)
const startDragTransferModal = createDragHandler(transferModalPos)

// Reset modal positions when they close
watch(showCheckModal, (v) => { if (!v) checkModalPos.value = { x: 0, y: 0 } })
watch(showAddModal, (v) => { if (!v) addWhModalPos.value = { x: 0, y: 0 } })
watch(showEditModal, (v) => { if (!v) editWhModalPos.value = { x: 0, y: 0 } })
watch(showAddProductCheckModal, (v) => { if (!v) prodModalPos.value = { x: 0, y: 0 } })
watch(showTransferModal, (v) => { if (!v) transferModalPos.value = { x: 0, y: 0 } })

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
        <button @click="openAddWarehouseModal" class="btn-primary flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg shadow-sm cursor-pointer shrink-0">
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
          <button @click="openMainStats" class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-4 py-2 rounded-lg transition-all text-xs font-bold flex items-center gap-2 shadow-sm cursor-pointer" title="Xem thống kê kho cả tháng">
            <BarChart2 class="w-4 h-4 text-indigo-500" />
            Thống kê
          </button>
          <!-- Get Bill: chọn ngày + nút lấy -->
          <div class="flex items-center gap-1 border border-slate-300 rounded-lg overflow-hidden shadow-sm bg-white">
            <input
              v-model="billDate"
              type="date"
              :min="currentMonth + '-01'"
              :max="currentMonth + '-31'"
              class="px-2 py-1.5 text-[12px] text-slate-700 bg-transparent outline-none border-none"
              :disabled="isBillLoading"
              title="Chọn ngày cần lấy bill"
            />
            <button 
              @click="triggerGetBillByDate" 
              :disabled="!activeWarehouse?.outlet_id || isBillLoading"
              class="bg-sky-50 hover:bg-sky-100 border-l border-slate-300 text-slate-750 px-3 py-1.5 transition-all text-xs font-bold flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" 
              title="Lấy dữ liệu xuất bán từ bill ngày đã chọn"
            >
              <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
              <span v-if="isBillLoading">Đang lấy...</span>
              <span v-else>Lấy Bill</span>
            </button>
          </div>
          <button @click="exportExcelLogs" class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-4 py-2 rounded-lg transition-all text-xs font-bold flex items-center gap-2 shadow-sm cursor-pointer" title="Xuất excel nhật ký kho cả tháng">
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
              <th rowspan="2" class="col-ton-dk py-3 px-4 bg-slate-100 text-center align-middle sticky left-[256px] z-30 text-slate-800 font-bold">Tồn ĐK</th>
              <th v-for="day in days" :key="day" colspan="3" class="py-2 px-2 text-center border-r border-slate-200 transition-colors" :class="isSystemDate(day) ? 'col-today-header font-black' : (day % 2 === 0 ? 'col-alt-header text-slate-800' : 'bg-slate-50/50')">
                <div class="flex flex-col items-center gap-0.5">
                  <span>{{ day }}</span>
                  <button
                    v-if="isSystemDate(day)"
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
              <th rowspan="2" class="col-ton-cuoi py-3 px-4 bg-slate-100 text-center align-middle sticky right-0 text-[var(--hk-primary-dark)] font-black">Tồn Cuối</th>
            </tr>
            <tr class="bg-slate-100/80 text-slate-500 font-bold border-b border-slate-250 text-[10px] uppercase">
              <template v-for="day in days" :key="'sub'+day">
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium transition-colors" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Nhập</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium transition-colors" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Xuất</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium transition-colors" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Chuyển</th>
              </template>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Skeleton -->
            <template v-if="isLoading">
              <tr v-for="i in 4" :key="'sk-'+i" class="animate-pulse">
                <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-40 bg-slate-200 rounded"></div></td>
                <td class="col-ton-dk py-2.5 px-2 sticky left-[256px] bg-white"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <template v-for="day in days" :key="'sk-day-'+day">
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                </template>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-r border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="col-ton-cuoi py-2.5 px-4 sticky right-0 bg-white"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
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
              <td class="col-ton-dk py-2 px-2 text-center text-slate-700 font-semibold bg-white group-hover:bg-slate-50 min-w-[80px]">
                {{ getInitialStockLabel(item) }}
              </td>
              <!-- Nhật ký từng ngày (3 cột: nhập / xuất / chuyển) -->
              <template v-for="day in days" :key="'item-day-'+day">
                <td class="py-0.5 px-0.5 border-r border-slate-200 text-center transition-colors" :class="isSystemDate(day) ? 'col-today-body' : (day % 2 === 0 ? 'col-alt-body' : '')">
                  <input
                    type="number" min="0"
                    :value="getLogVal(item.product_id, day, 'receive') || ''"
                    @change="e => onLogInput(item.product_id, day, 'receive', e.target.value)"
                    class="w-12 text-center text-[11px] border border-transparent hover:border-slate-300 focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] rounded outline-none bg-transparent focus:bg-white transition-all py-1"
                    placeholder=""
                  />
                </td>
                <td class="py-0.5 px-0.5 border-r border-slate-200 text-center transition-colors" :class="isSystemDate(day) ? 'col-today-body' : (day % 2 === 0 ? 'col-alt-body' : '')">
                  <input
                    type="number" min="0"
                    :value="getLogVal(item.product_id, day, 'export') || ''"
                    @change="e => onLogInput(item.product_id, day, 'export', e.target.value)"
                    class="w-12 text-center text-[11px] border border-transparent hover:border-slate-300 focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] rounded outline-none bg-transparent focus:bg-white transition-all py-1"
                    placeholder=""
                  />
                </td>
                <td
                  class="py-0.5 px-0.5 border-r border-slate-200 text-center cursor-pointer transition-colors select-none"
                  :class="isSystemDate(day) ? 'col-today-body hover:bg-amber-100/30' : (day % 2 === 0 ? 'col-alt-body hover:bg-slate-100' : 'hover:bg-slate-100')"
                  @click="openTransferModal(item, day)"
                  title="Click để chuyển kho"
                >
                  <span
                    class="text-[11px] font-semibold"
                    :class="getLogVal(item.product_id, day, 'transfer') ? 'text-sky-600 font-bold' : 'text-slate-400'"
                  >
                    {{ getLogVal(item.product_id, day, 'transfer') || '' }}
                  </span>
                </td>
              </template>
              <!-- Tổng tháng -->
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalReceive || '' }}</td>
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalExport || '' }}</td>
              <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50 font-semibold">{{ item.totalTransfer || '' }}</td>
              <!-- Tồn cuối -->
              <td class="col-ton-cuoi py-2 px-4 text-right font-black sticky right-0 bg-white group-hover:bg-slate-50 text-sm"
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
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + addWhModalPos.x + 'px, ' + addWhModalPos.y + 'px)' }">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800 cursor-move select-none" @mousedown="startDragAddWhModal" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
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
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + editWhModalPos.x + 'px, ' + editWhModalPos.y + 'px)' }">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800 cursor-move select-none" @mousedown="startDragEditWhModal" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
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
      <div v-if="showCheckModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="relative bg-white rounded-xl shadow-2xl w-[1100px] flex flex-col overflow-hidden max-h-[90vh] border border-slate-200" :style="{ transform: 'translate(' + checkModalPos.x + 'px, ' + checkModalPos.y + 'px)' }">
          <LoadingOverlay :show="isSaving" />
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800 cursor-move select-none" @mousedown="startDragCheckModal" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-slate-800 font-bold text-[16px] uppercase tracking-wide">Kiểm Kê Tồn Kho Định Kỳ</h3>
            <button @click="showCheckModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-5 h-5" /></button>
          </div>
          <div class="p-5 flex flex-col gap-5 overflow-y-auto bg-slate-50 flex-1 hk-scroll">
            <!-- Header form -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-5 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Tháng / Năm</label>
                <input type="month" v-model="checkForm.month" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm cursor-pointer bg-white" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Mã Phiếu</label>
                <input type="text" :value="currentCheck?.id ? 'KK-' + currentCheck.id : 'Mới'" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Kho</label>
                <input type="text" :value="activeWarehouse?.name || ''" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Người Kiểm Kho</label>
                <select v-model="checkForm.created_by" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm bg-white cursor-pointer">
                  <option value="">-- Chọn người kiểm --</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-[12px] font-bold text-slate-700">Ghi Chú</label>
                <input type="text" v-model="checkForm.note" placeholder="Nhập ghi chú..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-[var(--hk-primary)] focus:ring-2 focus:ring-[var(--hk-primary-light)] transition-all shadow-sm bg-white" />
              </div>
            </div>
 
            <!-- Items table with max-height & sticky header -->
            <div class="border border-slate-200 rounded-xl overflow-auto bg-white shadow-sm max-h-[320px] hk-scroll">
              <table class="w-full text-left border-collapse text-[12px] whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-600 font-bold sticky top-0 z-10 shadow-[0_1px_0_0_#e2e8f0]">
                  <tr>
                    <th class="py-3 px-3 border-r border-slate-200 text-center w-28">Mã Kiểm Kê</th>
                    <th class="py-3 px-3 border-r border-slate-200 text-center w-24">Mã SP</th>
                    <th class="py-3 px-4 border-r border-slate-200">Tên SP</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-24">Đơn Vị</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-36">Tồn Đầu Kỳ</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-36">Số Lượng Thực Tế</th>
                    <th class="py-3 px-2 border-r border-slate-200 text-center w-28">Số Chênh Lệch</th>
                    <th class="py-3 px-4 text-center">Ghi Chú</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700">
                  <tr v-if="!currentCheck || !currentCheck.items?.length">
                    <td colspan="8" class="py-12 text-center text-slate-400">
                      <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl">📦</span>
                        <p class="font-semibold text-slate-600">Chưa có sản phẩm trong phiếu kiểm kê</p>
                        <p class="text-xs">Nhấn <strong>Thêm SP</strong> để thêm sản phẩm</p>
                      </div>
                    </td>
                  </tr>
                  <tr v-else v-for="(item, idx) in currentCheck.items" :key="item.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-500 font-mono">KK-{{ currentCheck.id }}</td>
                    <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-500 font-mono">{{ item.product_code }}</td>
                    <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">{{ item.product_name }}</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-600">{{ item.unit || '—' }}</td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">
                      <input type="number" min="0"
                        :value="item.well_balance"
                        @change="e => onWellBalanceInput(item, e.target.value)"
                        class="w-20 text-center text-[13px] border border-slate-300 rounded px-2 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white"
                      />
                    </td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center">
                      <input type="number" min="0"
                        :value="item.stoke_take"
                        @change="e => onStokeTakeInput(item, e.target.value)"
                        class="w-20 text-center text-[13px] border border-slate-300 rounded px-2 py-1 focus:outline-none focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] transition-all bg-white"
                      />
                    </td>
                    <td class="py-2 px-2 border-r border-slate-200 text-center font-bold"
                      :class="item.different_qty > 0 ? 'text-emerald-600' : item.different_qty < 0 ? 'text-rose-600' : 'text-[var(--hk-primary-dark)]'"
                    >
                      {{ item.different_qty >= 0 ? '+' : '' }}{{ item.different_qty }}
                    </td>
                    <td class="py-2 px-3">
                      <input type="text" :value="item.note"
                        @change="e => { item.note = e.target.value; updateCheckItem(item.id, { note: e.target.value }) }"
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
              <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm
            </button>
            <button @click="deleteCheck" v-if="currentCheck" class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Trash2 class="w-4 h-4" /> Xóa
            </button>
            <button @click="createOrLoadCheck" :disabled="isSaving" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <Save class="w-4 h-4" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
            </button>
            <button @click="exportExcelCheck" v-if="currentCheck" class="px-5 py-2 flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <FileSpreadsheet class="w-4 h-4" /> Xuất Excel
            </button>
            <button @click="openStats" v-if="currentCheck" class="px-5 py-2 flex items-center gap-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm cursor-pointer">
              <BarChart2 class="w-4 h-4" /> Thống kê
            </button>
            <button @click="showCheckModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors cursor-pointer">
              <X class="w-4 h-4" /> Đóng
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Add Products Modal (Kiểm kê) -->
    <Transition name="hk-modal">
      <div v-if="showAddProductCheckModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/25">
        <div class="relative bg-white rounded-xl shadow-2xl w-[420px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + prodModalPos.x + 'px, ' + prodModalPos.y + 'px)' }">
          <LoadingOverlay :show="isSaving" />
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-slate-800 cursor-move select-none" @mousedown="startDragProdModal" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
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
      <div v-if="showTransferModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="relative bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + transferModalPos.x + 'px, ' + transferModalPos.y + 'px)' }">
          <LoadingOverlay :show="isSaving" />
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-slate-800 cursor-move select-none" @mousedown="startDragTransferModal" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
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

    <!-- Beautiful Statistics Modal -->
    <Transition name="hk-modal">
      <div v-if="showStatsModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="relative bg-white rounded-2xl shadow-2xl w-[500px] max-h-[85vh] flex flex-col overflow-hidden border border-slate-200">
          <!-- Header -->
          <div class="px-6 py-4 flex items-center justify-between text-white shrink-0" style="background: linear-gradient(135deg, #4f46e5, #6366f1)">
            <div class="flex items-center gap-2">
              <BarChart3 class="w-5 h-5" />
              <span class="font-bold text-[15px] tracking-wide">{{ statsTitle }}</span>
            </div>
            <button @click="showStatsModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer outline-none border-none bg-transparent">
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 overflow-y-auto flex-1 space-y-5 text-sm text-slate-650 bg-slate-50/50 hk-scroll">
            
            <!-- For Periodic Check Slip Statistics -->
            <template v-if="statsType === 'check'">
              <!-- Summary Badges Grid -->
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-white p-3.5 rounded-xl border border-slate-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-slate-400 uppercase">Tổng SP</span>
                  <span class="text-2xl font-black text-slate-800 mt-1">{{ statsData.total }}</span>
                </div>
                <div class="bg-emerald-50 p-3.5 rounded-xl border border-emerald-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-emerald-600 uppercase">Khớp</span>
                  <span class="text-2xl font-black text-emerald-700 mt-1">{{ statsData.normal }}</span>
                </div>
                <div class="bg-rose-50 p-3.5 rounded-xl border border-rose-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-rose-600 uppercase">Chênh lệch</span>
                  <span class="text-2xl font-black text-rose-700 mt-1">{{ statsData.discrepancies }}</span>
                </div>
              </div>

              <!-- Detailed Discrepancy List -->
              <div v-if="statsData.discrepancyList && statsData.discrepancyList.length" class="space-y-2">
                <h4 class="font-bold text-slate-700 flex items-center gap-1.5 text-xs uppercase tracking-wide">
                  <AlertTriangle class="w-4 h-4 text-rose-500" />
                  Danh sách hàng chênh lệch
                </h4>
                <div class="border border-slate-200 rounded-xl overflow-hidden bg-white max-h-60 overflow-y-auto shadow-sm">
                  <table class="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-150">
                        <th class="py-2.5 px-3">Sản phẩm</th>
                        <th class="py-2.5 px-3 text-center">Sổ sách</th>
                        <th class="py-2.5 px-3 text-center">Thực tế</th>
                        <th class="py-2.5 px-3 text-right">Chênh lệch</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                      <tr v-for="item in statsData.discrepancyList" :key="item.id" class="hover:bg-slate-50 transition-colors">
                        <td class="py-2 px-3 text-slate-700">{{ item.product_name }}</td>
                        <td class="py-2 px-3 text-center text-slate-500">{{ item.well_balance }}</td>
                        <td class="py-2 px-3 text-center text-slate-700">{{ item.stoke_take }}</td>
                        <td class="py-2 px-3 text-right font-bold" :class="parseFloat(item.different_qty) > 0 ? 'text-emerald-600' : 'text-rose-600'">
                          {{ parseFloat(item.different_qty) > 0 ? '+' : '' }}{{ item.different_qty }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <div v-else class="flex flex-col items-center justify-center py-6 text-center text-slate-400 bg-white border border-slate-100 rounded-xl shadow-sm">
                <CheckCircle2 class="w-8 h-8 text-emerald-500 mb-2" />
                <span class="font-semibold text-slate-650">Tất cả sản phẩm đều khớp số liệu!</span>
              </div>
            </template>

            <!-- For Main Monthly Logs Statistics -->
            <template v-if="statsType === 'main'">
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-indigo-50/50 p-3.5 rounded-xl border border-indigo-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-indigo-600 uppercase">Tổng nhập</span>
                  <span class="text-2xl font-black text-indigo-700 mt-1">{{ statsData.totalReceive }}</span>
                </div>
                <div class="bg-sky-50 p-3.5 rounded-xl border border-sky-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-sky-600 uppercase">Tổng xuất</span>
                  <span class="text-2xl font-black text-sky-700 mt-1">{{ statsData.totalExport }}</span>
                </div>
                <div class="bg-rose-50 p-3.5 rounded-xl border border-rose-100 flex flex-col items-center justify-center shadow-sm">
                  <span class="text-xs font-semibold text-rose-600 uppercase">Tổng chuyển</span>
                  <span class="text-2xl font-black text-rose-700 mt-1">{{ statsData.totalTransfer }}</span>
                </div>
              </div>

              <!-- Low Stock Warnings -->
              <div class="space-y-2">
                <h4 class="font-bold text-slate-700 flex items-center gap-1.5 text-xs uppercase tracking-wide">
                  <AlertTriangle class="w-4 h-4 text-amber-500" />
                  Sản phẩm tồn thấp (&lt; 50)
                </h4>
                <div v-if="statsData.lowStockProducts && statsData.lowStockProducts.length" class="border border-slate-200 rounded-xl overflow-hidden bg-white max-h-40 overflow-y-auto shadow-sm">
                  <table class="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-150">
                        <th class="py-2.5 px-3">Sản phẩm</th>
                        <th class="py-2.5 px-3 text-right">Tồn cuối</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                      <tr v-for="item in statsData.lowStockProducts" :key="item.product_id" class="hover:bg-slate-50 transition-colors">
                        <td class="py-2 px-3 text-slate-700">{{ item.product_name }}</td>
                        <td class="py-2 px-3 text-right text-rose-600">{{ item.finalStock }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="p-3 text-center text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-xl">
                  Không có sản phẩm nào ở mức cảnh báo tồn thấp!
                </div>
              </div>

              <!-- High Activity Info -->
              <div v-if="statsData.highestActivityName" class="p-3.5 bg-slate-100/60 rounded-xl border border-slate-200 flex items-center gap-3">
                <TrendingUp class="w-5 h-5 text-indigo-500 animate-pulse" />
                <div>
                  <div class="text-[11px] font-bold text-slate-400 uppercase">Hoạt động nhiều nhất</div>
                  <div class="font-bold text-slate-750 text-xs">{{ statsData.highestActivityName }} ({{ statsData.highestActivityCount }} lượt phát sinh)</div>
                </div>
              </div>
            </template>
            
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-slate-200 bg-white flex justify-end shrink-0">
            <button @click="showStatsModal = false" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[13px] font-bold transition-all shadow-md cursor-pointer outline-none border-none">
              Đồng ý
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

/* Tách biệt rõ ràng cột Tồn cuối của bảng chính */
.col-ton-cuoi {
  position: sticky !important;
  right: 0 !important;
  border-left: 1px solid #cbd5e1 !important;
  z-index: 5 !important;
}
.col-ton-cuoi::before {
  content: "" !important;
  position: absolute !important;
  top: 0 !important;
  bottom: 0 !important;
  left: -12px !important;
  width: 12px !important;
  background: linear-gradient(to right, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.16)) !important;
  pointer-events: none !important;
}

/* Màu cột xen kẽ (ngày chẵn) dựa trên #0ea5e9 */
.col-alt-header {
  background-color: rgba(14, 165, 233, 0.14) !important;
}
.col-alt-subheader {
  background-color: rgba(14, 165, 233, 0.07) !important;
}
.col-alt-body {
  background-color: rgba(14, 165, 233, 0.04) !important;
}

/* Màu cột ngày hệ thống dựa trên #FFEB3B */
.col-today-header {
  background-color: rgba(255, 235, 59, 0.45) !important;
  color: #78350f !important;
  border-bottom: 2px solid #eab308 !important;
}
.col-today-subheader {
  background-color: rgba(255, 235, 59, 0.28) !important;
}
.col-today-body {
  background-color: rgba(255, 235, 59, 0.15) !important;
}

/* Cột Tồn đầu kỳ đổ bóng bên phải */
.col-ton-dk {
  position: sticky !important;
  left: 256px !important;
  border-right: 1px solid #cbd5e1 !important;
  z-index: 10 !important;
}
.col-ton-dk::after {
  content: "" !important;
  position: absolute !important;
  top: 0 !important;
  bottom: 0 !important;
  right: -12px !important;
  width: 12px !important;
  background: linear-gradient(to right, rgba(0, 0, 0, 0.16), rgba(0, 0, 0, 0)) !important;
  pointer-events: none !important;
}
</style>
