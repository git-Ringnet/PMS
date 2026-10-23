<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Search, Plus, Minus, FileSpreadsheet, ClipboardList, ChevronLeft, ChevronRight, X, Trash2, Save, BarChart2, ChevronDown, BarChart3, AlertTriangle, CheckCircle2, TrendingUp, Calendar, ArrowRightLeft, Receipt } from '@lucide/vue'
import http from '@/services/http'
import { fetchSystemDate } from '@/services/booking-service'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'

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

// Dòng 372: phiếu kiểm kê chỉnh sửa trên bản nháp, bấm Lưu mới ghi xuống DB
const checkDraft           = ref([])
const checkDraftDirty      = ref(false)
const isCheckLocked        = computed(() => !!currentCheck.value?.has_logs)
// Định mức cảnh báo tồn theo thông số Inventory_LowStockWarningQty (mặc định 50)
const lowStockQty          = ref(50)
const isBillLoading        = ref(false)
const billDate             = ref('')  // ngày lấy bill, default = systemDate

// ─── Warehouses ───────────────────────────────────────────────────
const warehouses        = ref([])         // danh sách kho
const activeWarehouseId = ref(null)       // kho đang xem
const activeWarehouse   = computed(() => warehouses.value.find(w => w.id === activeWarehouseId.value))

// Thêm kho form
const newWarehouse = ref({ name: '', outlet_ids: [] })
const hkOutlets    = ref([])   // danh sách outlet của HK department

// Sửa kho
const editWarehouse = ref({ id: null, name: '', outlet_ids: [] })

// ─── Month / Calendar ─────────────────────────────────────────────
const currentMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM

const formattedMonth = computed(() => {
  if (!currentMonth.value) return ''
  const [year, month] = currentMonth.value.split('-')
  return `${month}/${year}`
})

const days = computed(() => {
  if (!currentMonth.value) return []
  const [year, month] = currentMonth.value.split('-')
  const daysInMonth = new Date(year, month, 0).getDate()
  return Array.from({ length: daysInMonth }, (_, i) => i + 1)
})

const formattedCheckMonth = computed(() => {
  if (!checkForm.value.month) return ''
  const [year, month] = checkForm.value.month.split('-')
  return `${month}/${year}`
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

function handleEscKey(e) {
  if (e.key === 'Escape') {
    if (showAddProductCheckModal.value) {
      showAddProductCheckModal.value = false
    } else if (showCheckModal.value) {
      closeCheckModal()
    } else if (showTransferModal.value) {
      showTransferModal.value = false
    } else if (showStatsModal.value) {
      showStatsModal.value = false
    } else if (showAddModal.value) {
      showAddModal.value = false
    } else if (showEditModal.value) {
      showEditModal.value = false
    }
  }
}

onMounted(() => {
  if (route.query.warningOnly === 'true') filterState.value.warningOnly = true
  document.addEventListener('click', handleOutsideClick)
  window.addEventListener('keydown', handleEscKey)
  loadInitialData()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  window.removeEventListener('keydown', handleEscKey)
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
    let sysDate = authStore.systemDate || localStorage.getItem('pms_system_date') || `${localDate.getFullYear()}-${String(localDate.getMonth() + 1).padStart(2, '0')}-${String(localDate.getDate()).padStart(2, '0')}`
    try {
      const dateRes = await fetchSystemDate()
      sysDate = dateRes?.data?.data?.system_date || dateRes?.data?.system_date || sysDate
      authStore.setSystemDate(sysDate)
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
    try {
      const settingsRes = await http.get('/hotel-settings')
      const qty = Number(settingsRes.data?.data?.Inventory_LowStockWarningQty)
      if (Number.isFinite(qty) && qty >= 0) lowStockQty.value = qty
    } catch (err) {
      console.error('load Inventory_LowStockWarningQty error', err)
    }
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
  // Dòng 372: chỉ lấy sản phẩm của kho đang kiểm kê, chưa có kho thì không tải danh sách chung
  if (!activeWarehouseId.value) {
    productsInStock.value = []
    return
  }
  try {
    const res = await http.get('/inventory/products-in-stock', { params: { warehouse_id: activeWarehouseId.value } })
    productsInStock.value = res.data.data || []
    // Chỉ có một kho thì mở sẵn danh sách nhóm
    productsInStock.value.forEach(wh => { expandedOutlets.value[wh.id] = true })
  } catch (e) {
    console.error('loadProductsInStock error', e)
  }
}

// ─── Warehouse CRUD ───────────────────────────────────────────────
function openAddWarehouseModal() {
  newWarehouse.value = { name: '', outlet_ids: [] }
  showAddModal.value = true
}

async function addWarehouse() {
  if (!newWarehouse.value.name.trim()) return
  isSaving.value = true
  try {
    const res = await http.post('/warehouses', {
      name: newWarehouse.value.name,
      outlet_ids: newWarehouse.value.outlet_ids,
    })
    warehouses.value.push(res.data.data)
    if (!activeWarehouseId.value) activeWarehouseId.value = res.data.data.id
    showAddModal.value = false
    newWarehouse.value = { name: '', outlet_ids: [] }
    uiStore.showToast('Thêm kho mới thành công!', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi thêm kho', 'error')
  } finally {
    isSaving.value = false
  }
}

function openEditModal(warehouse) {
  let ids = []
  if (Array.isArray(warehouse.outlet_ids)) {
    ids = [...warehouse.outlet_ids]
  } else if (warehouse.outlet_id) {
    ids = warehouse.outlet_id.split(',').map(s => s.trim()).filter(Boolean)
  }
  editWarehouse.value = { id: warehouse.id, name: warehouse.name, outlet_ids: ids }
  showEditModal.value = true
}

async function updateWarehouse() {
  isSaving.value = true
  try {
    const res = await http.put(`/warehouses/${editWarehouse.value.id}`, {
      name: editWarehouse.value.name,
      outlet_ids: editWarehouse.value.outlet_ids,
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
function resetCheckDraft() {
  checkDraft.value = (currentCheck.value?.items || []).map(item => ({ ...item }))
  checkDraftDirty.value = false
}

watch(currentCheck, resetCheckDraft, { immediate: true })

async function confirmDiscardDraft() {
  if (!checkDraftDirty.value) return true
  return uiStore.confirm({
    title: 'Chưa lưu phiếu kiểm kê',
    message: 'Phiếu kiểm kê có thay đổi chưa lưu. Bạn có muốn bỏ các thay đổi này?',
    confirmText: 'Bỏ thay đổi',
    cancelText: 'Quay lại',
  })
}

async function closeCheckModal() {
  if (!(await confirmDiscardDraft())) return
  resetCheckDraft()
  showCheckModal.value = false
}

async function openCheckModal() {
  if (!activeWarehouseId.value) {
    uiStore.showToast('Vui lòng chọn kho cần kiểm kê.', 'warning')
    return
  }
  checkForm.value = {
    month: currentMonth.value,
    note: currentCheck.value?.note || '',
    created_by: currentCheck.value?.created_by || authStore.user?.id || ''
  }
  resetCheckDraft()
  showCheckModal.value = true // Open modal immediately
  await loadProductsInStock() // Load products in background
}

async function onCheckMonthChange(newMonth) {
  if (!activeWarehouseId.value || !newMonth) return
  if (!(await confirmDiscardDraft())) {
    checkForm.value.month = currentCheck.value?.month || currentMonth.value
    return
  }
  isSaving.value = true
  try {
    const res = await http.get('/inventory/checks', {
      params: { warehouse_id: activeWarehouseId.value, month: newMonth }
    })
    currentCheck.value = res.data.data
    resetCheckDraft()
  } catch (e) {
    console.error('onCheckMonthChange error', e)
  } finally {
    isSaving.value = false
  }
}

// Thống kê: lấy tồn cuối tháng trước điền vào phiếu (chưa lưu)
async function syncPreviousMonth() {
  if (!activeWarehouseId.value || !checkForm.value.month || isCheckLocked.value) return
  isSaving.value = true
  try {
    const res = await http.post('/inventory/checks/sync-previous-month', {
      warehouse_id: activeWarehouseId.value,
      month: checkForm.value.month,
    })
    const byProduct = new Map(checkDraft.value.map(item => [item.product_id, item]))
    for (const row of res.data.items || []) {
      const existing = byProduct.get(row.product_id)
      if (existing) {
        Object.assign(existing, { well_balance: row.well_balance, stoke_take: row.stoke_take, different_qty: 0 })
      } else {
        checkDraft.value.push({ ...row, id: null, note: '' })
      }
    }
    checkDraftDirty.value = true
    uiStore.showToast(res.data.message, (res.data.negative_count || 0) > 0 ? 'warning' : 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lấy số liệu tháng trước', 'error')
  } finally {
    isSaving.value = false
  }
}

// Lưu phiếu: tạo mới hoặc cập nhật một lần với toàn bộ sản phẩm trong bản nháp
async function saveCheck(confirmNegative = false) {
  if (isCheckLocked.value) return
  isSaving.value = true
  try {
    const res = await http.post('/inventory/checks/save', {
      warehouse_id: activeWarehouseId.value,
      month: checkForm.value.month,
      note: checkForm.value.note,
      created_by: checkForm.value.created_by || null,
      confirm_negative: confirmNegative,
      items: checkDraft.value.map(item => ({
        product_id: item.product_id,
        well_balance: Number(item.well_balance) || 0,
        stoke_take: Number(item.stoke_take) || 0,
        unit: item.unit || null,
        note: item.note || null,
      })),
    })

    if (res.data.needs_negative_confirm) {
      isSaving.value = false
      const names = (res.data.negative_products || []).join(', ')
      const proceed = await uiStore.confirm({
        title: 'Tồn đầu kỳ âm',
        message: `Một số sản phẩm có tồn đầu âm${names ? ` (${names})` : ''}, bạn có muốn tiếp tục?`,
        confirmText: 'Có',
        cancelText: 'Không',
      })
      if (proceed) await saveCheck(true)
      return
    }

    currentCheck.value = res.data.data
    resetCheckDraft()
    if (checkForm.value.month === currentMonth.value) {
      await loadCheckAndLogs()
    }
    uiStore.showToast(res.data.message || 'Lưu phiếu kiểm kê thành công!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lưu phiếu kiểm kê', 'error')
  } finally {
    isSaving.value = false
  }
}

// Màn Thêm sản phẩm chỉ thêm vào phiếu (bản nháp), chưa tạo phiếu
function addProductsToCheck() {
  if (!selectedProductIds.value.length) return
  const existing = new Set(checkDraft.value.map(item => item.product_id))
  let added = 0
  for (const productId of selectedProductIds.value) {
    if (existing.has(productId)) continue
    const product = allProducts.value.find(p => p.id === productId)
    if (!product) continue
    checkDraft.value.push({
      id: null,
      product_id: product.id,
      product_code: product.product_code || product.id,
      product_name: product.name,
      category_name: product.category_name || 'Chưa phân nhóm',
      unit: product.unit || 'Cái',
      well_balance: 0,
      stoke_take: 0,
      different_qty: 0,
      note: '',
    })
    added++
  }
  selectedProductIds.value = []
  showAddProductCheckModal.value = false
  if (added) {
    checkDraftDirty.value = true
    uiStore.showToast(`Đã thêm ${added} sản phẩm vào phiếu. Nhập số lượng kiểm kê rồi bấm Lưu để lưu phiếu.`, 'success')
  } else {
    uiStore.showToast('Các sản phẩm đã chọn đều có trong phiếu.', 'warning')
  }
}

function removeDraftItem(item) {
  checkDraft.value = checkDraft.value.filter(row => row.product_id !== item.product_id)
  checkDraftDirty.value = true
}

function setDraftNote(item, note) {
  item.note = note
  checkDraftDirty.value = true
}

function getInitialStock(item) {
  if (!item) return 0
  if (item.stoke_take !== null && item.stoke_take !== undefined && item.stoke_take !== '') {
    return parseFloat(item.stoke_take) || 0
  }
  return parseFloat(item.well_balance) || 0
}

function getInitialStockLabel(item) {
  if (!item) return ''
  const val = getInitialStock(item)
  // Dòng 372: tồn đầu âm vẫn hiển thị số lượng (trước đây bị ẩn)
  if (val !== 0) return val
  return item.well_balance !== null && item.well_balance !== undefined && item.well_balance !== '' ? '0' : ''
}

function onStokeTakeInput(item, val) {
  if (isCheckLocked.value) return
  const stokeTake = parseFloat(val) || 0
  item.stoke_take = stokeTake

  const currentWell = parseFloat(item.well_balance) || 0
  if (currentWell === 0) {
    item.well_balance = stokeTake
    item.different_qty = 0
  } else {
    item.different_qty = stokeTake - currentWell
  }
  checkDraftDirty.value = true
}

function onWellBalanceInput(item, val) {
  if (isCheckLocked.value) return
  const wellBalance = parseFloat(val) || 0
  item.well_balance = wellBalance

  const currentStoke = parseFloat(item.stoke_take) || 0
  if (currentStoke === 0) {
    item.stoke_take = wellBalance
    item.different_qty = 0
  } else {
    item.different_qty = currentStoke - wellBalance
  }
  checkDraftDirty.value = true
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
  statsTitle.value = 'Thống Kê Phiếu Kiểm Kê'
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
  
  const lowStockProducts = items.filter(i => i.finalStock < lowStockQty.value)
  const lowStockCount = lowStockProducts.length
  
  const highestActivityProd = [...items].sort((a, b) => {
    const actA = (a.totalReceive || 0) + (a.totalExport || 0) + (a.totalTransfer || 0)
    const actB = (b.totalReceive || 0) + (b.totalExport || 0) + (b.totalTransfer || 0)
    return actB - actA
  })[0]
  
  const highestActivityName = highestActivityProd ? highestActivityProd.product_name : ''
  const highestActivityCount = highestActivityProd ? ((highestActivityProd.totalReceive || 0) + (highestActivityProd.totalExport || 0) + (highestActivityProd.totalTransfer || 0)) : 0
  
  statsType.value = 'main'
  statsTitle.value = `Thống Kê Kho Tháng ${formattedMonth.value}`
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

async function getBillMonth() {
  if (!activeWarehouseId.value || !currentMonth.value) return
  isBillLoading.value = true
  try {
    const res = await http.post('/inventory/get-bill', {
      warehouse_id: activeWarehouseId.value,
      month: currentMonth.value,
    })
    uiStore.showToast(res.data.message, 'success')
    await loadCheckAndLogs()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lấy dữ liệu bill cả tháng', 'error')
  } finally {
    isBillLoading.value = false
  }
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
  if (!transferForm.value.transfer_to_warehouse_id) {
    uiStore.showToast('Vui lòng chọn Kho đích.', 'warning')
    return
  }
  const transferQuantity = Number(transferForm.value.quantity)
  if (!Number.isFinite(transferQuantity) || transferQuantity <= 0) {
    uiStore.showToast('Vui lòng nhập Số lượng lớn hơn 0.', 'warning')
    return
  }
  isSaving.value = true
  try {
    const res = await http.post('/inventory/transfer', {
      ...transferForm.value,
      quantity: transferQuantity,
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
      return init > 0 && i.finalStock < lowStockQty.value
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

const groupedTableItems = computed(() => {
  const groups = []
  const map = new Map()

  tableItems.value.forEach(item => {
    const catName = item.category_name || 'Chưa phân nhóm'
    if (!map.has(catName)) {
      map.set(catName, [])
    }
    map.get(catName).push(item)
  })

  for (const [categoryName, items] of map.entries()) {
    groups.push({
      categoryName,
      items,
      count: items.length
    })
  }

  return groups
})

const modalCheckItems = computed(() => {
  return [...checkDraft.value].sort((a, b) => {
    return (a.product_name || '').localeCompare(b.product_name || '', 'vi', { sensitivity: 'base' })
  })
})

// Tính tổng log cả tháng cho 1 sản phẩm
function sumLogMonth(productId, field) {
  const productLogs = logsMap.value[productId] || {}
  return Object.values(productLogs).reduce((sum, dayLog) => sum + (dayLog[field] || 0), 0)
}

// ─── Helpers ─────────────────────────────────────────────────────
function toTitleCase(str) {
  if (!str) return ''
  return str
    .toLowerCase()
    .split(' ')
    .map(w => w ? w.charAt(0).toUpperCase() + w.slice(1) : '')
    .join(' ')
}

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
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans text-xs text-[#000000D9] relative">

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
              class="text-xs font-bold tracking-wide pb-2 transition-colors relative"
              :class="activeWarehouseId === wh.id ? 'text-[var(--pms-custom-theme,#006bdb)]' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ toTitleCase(wh.name) }}
              <div v-if="activeWarehouseId === wh.id" class="absolute bottom-0 left-0 w-full h-0.5 bg-[var(--pms-custom-theme,#006bdb)] rounded-t-full"></div>
            </h2>
            <button
              @click.stop="openEditModal(wh)"
              class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-[var(--pms-custom-theme,#006bdb)] hover:bg-slate-50 rounded-full -mt-2 bg-white cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
          <!-- Placeholder nếu chưa có kho -->
          <span v-if="warehouses.length === 0" class="text-xs text-slate-400 italic pb-2">Chưa có kho nào</span>
        </div>
        <button @click="openAddWarehouseModal" class="btn-pms-primary flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-lg shadow-sm cursor-pointer shrink-0">
          <Plus class="w-4 h-4" stroke-width="2.5" />
          Thêm Kho
        </button>
      </div>

      <!-- Toolbar -->
      <div class="px-5 py-3 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-4">
          <!-- Month Picker (mm/yyyy overlay) -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-700 text-xs">Tháng:</span>
            <div class="relative flex items-center justify-between border border-slate-300 rounded-lg bg-white px-2.5 h-8 w-32 shadow-2xs cursor-pointer hover:border-[var(--pms-custom-theme,#006bdb)] transition-colors">
              <span class="text-xs font-semibold text-[#000000D9]">{{ formattedMonth }}</span>
              <Calendar class="w-3.5 h-3.5 text-slate-400" />
              <input type="month" v-model="currentMonth" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
            </div>
          </div>

          <!-- Warning Only Switch -->
          <div class="flex items-center gap-1.5 select-none">
            <span class="text-xs text-[#000000D9] font-normal">Cảnh Báo Tồn</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="filterState.warningOnly" class="sr-only peer">
              <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
            </label>
          </div>

          <!-- Sort -->
          <div class="flex items-center gap-2">
            <span class="font-semibold text-slate-700 text-xs">Sắp Xếp:</span>
            <select v-model="filterState.sortBy" class="h-8 border border-slate-300 rounded-lg px-2.5 text-xs text-[#000000D9] bg-white focus:outline-none focus:ring-1 focus:ring-[var(--pms-custom-theme,#006bdb)] cursor-pointer shadow-2xs font-normal">
              <option value="name_asc">Tên A-Z</option>
              <option value="name_desc">Tên Z-A</option>
              <option value="stock_asc">Tồn Cuối Tăng Dần</option>
              <option value="stock_desc">Tồn Cuối Giảm Dần</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button @click="openMainStats" class="btn-pms-secondary flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer" title="Xem thống kê kho cả tháng">
            <BarChart2 class="w-4 h-4 text-indigo-500" />
            Thống Kê
          </button>
          <!-- Nút Lấy Bill cả tháng -->
          <button 
            @click="getBillMonth" 
            :disabled="!activeWarehouse?.outlet_id || isBillLoading"
            class="btn-pms-secondary flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" 
            :title="`Tự động quét và lấy toàn bộ hóa đơn xuất kho trong tháng ${currentMonth}`"
          >
            <svg class="w-4 h-4 text-sky-600 animate-spin" v-if="isBillLoading" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <Receipt v-else class="w-4 h-4 text-sky-600" />
            <span>{{ isBillLoading ? 'Đang lấy bill...' : 'Lấy Bill Tháng' }}</span>
          </button>
          <button @click="exportExcelLogs" class="btn-pms-secondary flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer" title="Xuất excel nhật ký kho cả tháng">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
            Xuất Excel
          </button>
          <button @click="openCheckModal" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
            <ClipboardList class="w-4 h-4" stroke-width="2.5" />
            Kiểm Kê Định Kỳ
          </button>
        </div>
      </div>

      <!-- Activity Filter Chips -->
      <div class="px-5 py-2 border-t border-slate-100 flex items-center gap-2 text-xs bg-slate-50/25 rounded-b-xl">
        <span class="text-xs font-semibold text-slate-600 mr-1">Phát Sinh:</span>
        <button
          v-for="st in [
            { label: 'Tất Cả Sản Phẩm', value: 'all' },
            { label: 'Có Phát Sinh', value: 'has_activity' },
            { label: 'Không Phát Sinh', value: 'no_activity' },
          ]"
          :key="st.value"
          @click="filterState.activity = st.value"
          class="px-2.5 py-1 rounded-full text-xs transition-all duration-200 cursor-pointer border"
          :class="filterState.activity === st.value
            ? 'bg-sky-100 text-sky-850 border-sky-300 font-semibold shadow-2xs'
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
        >
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Table Section -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse text-xs whitespace-nowrap min-w-max">
          <thead class="sticky top-0 z-20 backdrop-blur-md bg-white/95">
            <tr class="bg-slate-100 text-[#000000D9] font-semibold border-b border-slate-300 text-xs">
              <th rowspan="2" class="py-2.5 px-3 border-r border-slate-200 w-64 min-w-[256px] text-center align-middle sticky left-0 z-30 shadow-[1px_0_0_0_#e2e8f0] bg-slate-100 border">
                <div class="flex flex-col items-center justify-center gap-1.5">
                  <div class="flex items-center text-xs font-semibold text-[#000000D9]">
                    Sản Phẩm <Search @click="showProductSearch = !showProductSearch" class="w-3.5 h-3.5 inline-block ml-1.5 text-slate-400 cursor-pointer hover:text-[var(--pms-custom-theme,#006bdb)] transition-colors" />
                  </div>
                  <div v-if="showProductSearch" class="relative w-full mt-1 px-1">
                    <Search class="absolute left-3 top-2 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                    <input 
                      type="text" 
                      v-model="filterState.search" 
                      placeholder="Tìm kiếm sản phẩm..." 
                      class="w-full pl-7 pr-7 py-1 text-xs font-normal text-[#000000D9] bg-white border border-slate-300 rounded-md focus:border-[var(--pms-custom-theme,#006bdb)] outline-none transition-all shadow-2xs" 
                    />
                    <button 
                      v-if="filterState.search" 
                      type="button"
                      @click="filterState.search = ''"
                      class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"
                      title="Xóa nhanh"
                    >
                      <X class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </th>
              <th rowspan="2" class="col-ton-dk py-2.5 px-3 bg-slate-100 text-center align-middle sticky left-[256px] z-30 text-[#000000D9] font-semibold border-r border-slate-200 border text-xs">Tồn Đầu Kỳ</th>
              <th v-for="day in days" :key="day" colspan="3" class="py-1 px-1 text-center border-r border-slate-200 transition-colors group/day align-middle border text-xs" :class="isSystemDate(day) ? 'col-today-header font-bold' : (day % 2 === 0 ? 'col-alt-header text-slate-800' : 'bg-slate-50/50')">
                <div class="flex flex-col items-center gap-0.5">
                  <span class="font-semibold text-[#000000D9] text-xs">{{ day }}</span>
                  <button
                    @click="getBill(day)"
                    :disabled="!activeWarehouse?.outlet_id || isBillLoading"
                    class="text-xs px-1.5 py-0.5 rounded transition-all disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer whitespace-nowrap flex items-center gap-1 border shadow-2xs"
                    :class="isSystemDate(day) ? 'bg-amber-100 hover:bg-amber-200 text-amber-900 border-amber-300 font-semibold' : 'bg-sky-50 hover:bg-sky-100 text-sky-800 border-sky-200 font-medium'"
                    :title="`Lấy dữ liệu xuất bill ngày ${day}`"
                  >
                    <Receipt class="w-3 h-3 text-sky-600" />
                    <span>Bill</span>
                  </button>
                </div>
              </th>
              <th rowspan="2" class="py-2.5 px-3 border-r border-slate-200 bg-slate-100 text-center align-middle border text-xs font-semibold text-[#000000D9]">SL Nhập</th>
              <th rowspan="2" class="py-2.5 px-3 border-r border-slate-200 bg-slate-100 text-center align-middle border text-xs font-semibold text-[#000000D9]">SL Xuất</th>
              <th rowspan="2" class="py-2.5 px-3 border-r border-slate-200 bg-slate-100 text-center align-middle border text-xs font-semibold text-[#000000D9]">SL Chuyển</th>
              <th rowspan="2" class="col-ton-cuoi py-2.5 px-4 bg-slate-100 text-center align-middle sticky right-0 text-[var(--pms-custom-theme,#006bdb)] font-bold border-r border-slate-200 border text-xs">Tồn Cuối</th>
            </tr>
            <tr class="bg-slate-100/90 text-slate-600 font-semibold border-b border-slate-250 text-xs">
              <template v-for="day in days" :key="'sub'+day">
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-semibold transition-colors align-middle border text-xs" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Nhập</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-semibold transition-colors align-middle border text-xs" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Xuất</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-semibold transition-colors align-middle border text-xs" :class="isSystemDate(day) ? 'col-today-subheader text-amber-900' : (day % 2 === 0 ? 'col-alt-subheader text-slate-700' : 'bg-slate-50/20')">Chuyển</th>
              </template>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 text-slate-700">
            <!-- Loading Skeleton -->
            <template v-if="isLoading">
              <tr v-for="i in 4" :key="'sk-'+i" class="animate-pulse">
                <td class="py-2.5 px-4 border-slate-200 sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e2e8f0]"><div class="h-4 w-40 bg-slate-200 rounded"></div></td>
                <td class="col-ton-dk py-2.5 px-2 sticky left-[256px] bg-white border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <template v-for="day in days" :key="'sk-day-'+day">
                  <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                  <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-6 bg-slate-100 rounded mx-auto"></div></td>
                </template>
                <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-2.5 px-2 border-slate-200"><div class="h-4 w-8 bg-slate-200 rounded mx-auto"></div></td>
                <td class="col-ton-cuoi py-2.5 px-4 sticky right-0 bg-white border-slate-200"><div class="h-4 w-12 bg-slate-200 rounded mx-auto"></div></td>
              </tr>
            </template>

            <!-- Chưa có kho -->
            <tr v-else-if="!activeWarehouseId">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400 border-slate-200">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl">🏪</span>
                  <p class="font-semibold text-slate-700 text-xs">Chưa có kho nào</p>
                  <p class="text-xs">Nhấn <strong>Thêm Kho</strong> để bắt đầu</p>
                </div>
              </td>
            </tr>

            <!-- Chưa có phiếu kiểm kê -->
            <tr v-else-if="!currentCheck">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400 border-slate-200">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl">📋</span>
                  <p class="font-semibold text-slate-700 text-xs">Chưa có phiếu kiểm kê tháng {{ formattedMonth }}</p>
                  <p class="text-xs">Nhấn <strong>Kiểm Kê Định Kỳ</strong> để tạo phiếu và thêm sản phẩm</p>
                </div>
              </td>
            </tr>

            <!-- Không có SP sau filter -->
            <tr v-else-if="tableItems.length === 0">
              <td :colspan="6 + days.length * 3" class="py-20 text-center text-slate-400 border-slate-200">
                <div class="flex flex-col items-center gap-3">
                  <span class="text-4xl animate-bounce">📦</span>
                  <p class="font-semibold text-slate-700 text-xs">Không có dữ liệu tồn kho trùng khớp</p>
                  <p class="text-xs text-slate-500">Thử tắt bộ lọc cảnh báo hoặc thay đổi từ khóa tìm kiếm.</p>
                </div>
              </td>
            </tr>

            <!-- Grouped Data Rows -->
            <template v-else v-for="group in groupedTableItems" :key="'group-'+group.categoryName">
              <!-- Category Group Header Row -->
              <tr class="bg-slate-100 font-semibold border-y border-slate-200">
                <td 
                  :colspan="6 + days.length * 3" 
                  class="py-2 px-4 sticky left-0 z-10 bg-slate-100/95 text-slate-800 text-xs shadow-[1px_0_0_0_#e2e8f0]"
                >
                  <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-[var(--pms-custom-theme,#006bdb)]"></span>
                    <span class="font-bold text-slate-800 text-xs tracking-wide">{{ toTitleCase(group.categoryName) }}</span>
                    <span class="text-slate-500 font-normal text-xs">({{ group.count }} sản phẩm)</span>
                  </div>
                </td>
              </tr>

              <!-- Items in Group -->
              <tr
                v-for="item in group.items" :key="'item-'+item.id"
                class="hover:bg-slate-50 transition-colors border-b border-slate-100 group"
              >
                <!-- Product name + actions -->
                <td class="py-2 px-4 border-slate-200 sticky left-0 z-10 bg-white group-hover:bg-slate-50 font-normal min-w-[256px] text-[#000000D9] shadow-[1px_0_0_0_#e2e8f0] text-xs">
                  <div class="flex items-center justify-between gap-1">
                    <span>{{ item.product_name }}</span>
                    <div class="flex items-center gap-1">
                      <span v-if="item.finalStock < 50 && item.well_balance > 0" class="px-1.5 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200 shrink-0">🚨 Cảnh Báo Tồn</span>
                      <button @click="openTransferModal(item)" title="Chuyển kho" class="opacity-0 group-hover:opacity-100 transition-opacity ml-1 p-1 rounded hover:bg-sky-100 text-slate-400 hover:text-[var(--pms-custom-theme,#006bdb)] cursor-pointer">
                        <ArrowRightLeft class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </td>
                <!-- Tồn đầu kỳ -->
                <td class="col-ton-dk py-2 px-2 text-center text-[#000000D9] font-medium bg-white group-hover:bg-slate-50 min-w-[80px] border-slate-200 text-xs">
                  {{ getInitialStockLabel(item) }}
                </td>
                <!-- Nhật ký từng ngày (3 cột: nhập / xuất / chuyển) -->
                <template v-for="day in days" :key="'item-day-'+day">
                  <td class="py-0.5 px-0.5 border-slate-200 text-center transition-colors" :class="isSystemDate(day) ? 'col-today-body' : (day % 2 === 0 ? 'col-alt-body' : '')">
                    <input
                      type="number" min="0"
                      :value="getLogVal(item.product_id, day, 'receive') || ''"
                      @change="e => onLogInput(item.product_id, day, 'receive', e.target.value)"
                      class="w-12 text-center text-xs border border-transparent hover:border-slate-300 focus:border-[var(--pms-custom-theme,#006bdb)] focus:ring-1 focus:ring-[var(--pms-custom-theme,#006bdb)] rounded outline-none bg-transparent focus:bg-white transition-all py-1 text-[#000000D9]"
                      placeholder=""
                    />
                  </td>
                  <td class="py-0.5 px-0.5 border-slate-200 text-center transition-colors" :class="isSystemDate(day) ? 'col-today-body' : (day % 2 === 0 ? 'col-alt-body' : '')">
                    <input
                      type="number" min="0"
                      :value="getLogVal(item.product_id, day, 'export') || ''"
                      @change="e => onLogInput(item.product_id, day, 'export', e.target.value)"
                      class="w-12 text-center text-xs border border-transparent hover:border-slate-300 focus:border-[var(--pms-custom-theme,#006bdb)] focus:ring-1 focus:ring-[var(--pms-custom-theme,#006bdb)] rounded outline-none bg-transparent focus:bg-white transition-all py-1 text-[#000000D9]"
                      placeholder=""
                    />
                  </td>
                  <td
                    class="py-0.5 px-0.5 border-slate-200 text-center cursor-pointer transition-colors select-none"
                    :class="isSystemDate(day) ? 'col-today-body hover:bg-amber-100/30' : (day % 2 === 0 ? 'col-alt-body hover:bg-slate-100' : 'hover:bg-slate-100')"
                    @click="openTransferModal(item, day)"
                    title="Click để chuyển kho"
                  >
                    <span
                      class="text-xs font-semibold"
                      :class="getLogVal(item.product_id, day, 'transfer') ? 'text-sky-600 font-bold' : 'text-slate-400'"
                    >
                      {{ getLogVal(item.product_id, day, 'transfer') || '' }}
                    </span>
                  </td>
                </template>
                <!-- Tổng tháng -->
                <td class="py-2 px-2 border-slate-200 text-center text-[#000000D9] bg-white group-hover:bg-slate-50 font-medium text-xs">{{ item.totalReceive || '' }}</td>
                <td class="py-2 px-2 border-slate-200 text-center text-[#000000D9] bg-white group-hover:bg-slate-50 font-medium text-xs">{{ item.totalExport || '' }}</td>
                <td class="py-2 px-2 border-slate-200 text-center text-[#000000D9] bg-white group-hover:bg-slate-50 font-medium text-xs">{{ item.totalTransfer || '' }}</td>
                <!-- Tồn cuối -->
                <td class="col-ton-cuoi py-2 px-4 text-right font-bold sticky right-0 bg-white group-hover:bg-slate-50 text-xs border-slate-200"
                  :class="item.finalStock < 50 ? 'text-rose-600' : 'text-[var(--pms-custom-theme,#006bdb)]'"
                >
                  {{ item.finalStock?.toLocaleString() }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Footer -->
    <div class="mt-4 flex items-center justify-between shrink-0">
      <div class="text-xs text-slate-600 font-semibold tracking-wider">
        {{ currentCheck ? `${tableItems.length} sản phẩm · Tháng ${formattedMonth}` : 'Chưa có phiếu kiểm kê' }}
      </div>
    </div>

    <!-- ═══ MODALS ════════════════════════════════════════════════ -->

    <!-- Add Warehouse Modal -->
    <Transition name="hk-modal">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + addWhModalPos.x + 'px, ' + addWhModalPos.y + 'px)' }">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-white cursor-move select-none rounded-t-xl" @mousedown="startDragAddWhModal" style="background: var(--pms-custom-theme, #006bdb)">
            <h3 class="text-white font-bold text-xs tracking-wide">Thêm Kho Mới</h3>
            <button @click="showAddModal = false" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-4 h-4" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50 text-xs">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-700">Tên Kho <span class="text-rose-500 font-bold">*</span></label>
              <input type="text" v-model="newWarehouse.name" placeholder="VD: Kho Minibar Tầng 1..." class="input-required w-full text-xs h-8 rounded-lg px-3 outline-none font-medium text-[#000000D9]" />
            </div>
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center justify-between">
                <label class="text-xs font-semibold text-slate-700">Outlet (Để Lấy Bill)</label>
                <span class="text-xs text-slate-400">Chọn Nhiều</span>
              </div>
              <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                <label
                  v-for="ol in hkOutlets" :key="ol.id || ol.code"
                  class="flex items-center gap-2 p-2 rounded-md border cursor-pointer select-none transition-all text-xs"
                  :class="newWarehouse.outlet_ids.includes(ol.code) ? 'bg-sky-50 border-sky-300 font-semibold text-sky-800 shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                >
                  <input
                    type="checkbox"
                    :value="ol.code"
                    v-model="newWarehouse.outlet_ids"
                    class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer"
                  />
                  <span>{{ ol.name }} <span class="text-slate-400 font-normal">({{ ol.code }})</span></span>
                </label>
              </div>
              <p class="text-xs text-slate-500">Gán các outlet để tính năng "Lấy Bill" tự động gom hóa đơn xuất kho</p>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex justify-end gap-2.5 font-semibold">
            <button @click="showAddModal = false" class="btn-pms-close flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer">
              <X class="w-3.5 h-3.5" /> Đóng
            </button>
            <button @click="addWarehouse" :disabled="isSaving || !newWarehouse.name.trim()" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Save class="w-3.5 h-3.5" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Edit Warehouse Modal -->
    <Transition name="hk-modal">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/25">
        <div class="bg-white rounded-xl shadow-2xl w-[440px] flex flex-col overflow-hidden border border-slate-200" :style="{ transform: 'translate(' + editWhModalPos.x + 'px, ' + editWhModalPos.y + 'px)' }">
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-white cursor-move select-none rounded-t-xl" @mousedown="startDragEditWhModal" style="background: var(--pms-custom-theme, #006bdb)">
            <h3 class="text-white font-bold text-xs tracking-wide">Cập Nhật Kho</h3>
            <button @click="showEditModal = false" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-4 h-4" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50 text-xs">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-700">Tên Kho <span class="text-rose-500 font-bold">*</span></label>
              <input type="text" v-model="editWarehouse.name" class="input-required w-full text-xs h-8 rounded-lg px-3 outline-none font-medium text-[#000000D9]" />
            </div>
            <div class="flex flex-col gap-1.5">
              <div class="flex items-center justify-between">
                <label class="text-xs font-semibold text-slate-700">Outlet (Để Lấy Bill)</label>
                <span class="text-xs text-slate-400">Chọn Nhiều</span>
              </div>
              <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                <label
                  v-for="ol in hkOutlets" :key="ol.id || ol.code"
                  class="flex items-center gap-2 p-2 rounded-md border cursor-pointer select-none transition-all text-xs"
                  :class="editWarehouse.outlet_ids.includes(ol.code) ? 'bg-sky-50 border-sky-300 font-semibold text-sky-800 shadow-2xs' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                >
                  <input
                    type="checkbox"
                    :value="ol.code"
                    v-model="editWarehouse.outlet_ids"
                    class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer"
                  />
                  <span>{{ ol.name }} <span class="text-slate-400 font-normal">({{ ol.code }})</span></span>
                </label>
              </div>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex justify-end gap-2.5">
            <button @click="showEditModal = false" class="btn-pms-close flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer">
              <X class="w-3.5 h-3.5" /> Đóng
            </button>
            <button @click="deleteWarehouse" class="btn-pms-danger flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Trash2 class="w-3.5 h-3.5" /> Xóa
            </button>
            <button @click="updateWarehouse" :disabled="isSaving" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Save class="w-3.5 h-3.5" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
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
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-white cursor-move select-none rounded-t-xl" @mousedown="startDragCheckModal" style="background: var(--pms-custom-theme, #006bdb)">
            <h3 class="text-white font-bold text-xs tracking-wide">Kiểm Kê Tồn Kho Định Kỳ<span v-if="checkDraftDirty" class="ml-2 font-normal opacity-90">(chưa lưu)</span></h3>
            <button @click="closeCheckModal" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-4 h-4" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 overflow-y-auto bg-slate-50 flex-1 hk-scroll text-xs">
            <div v-if="isCheckLocked" class="px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 font-medium">
              Tháng này đã phát sinh dữ liệu nhập/xuất tồn kho nên phiếu kiểm kê chỉ được xem, không được chỉnh sửa.
            </div>
            <!-- Header form -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs grid grid-cols-5 gap-3.5">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Tháng / Năm</label>
                <div class="relative flex items-center justify-between border border-slate-300 rounded-lg bg-white px-2.5 h-8 w-full shadow-2xs cursor-pointer hover:border-[var(--pms-custom-theme,#006bdb)] transition-colors">
                  <span class="text-xs font-semibold text-[#000000D9]">{{ formattedCheckMonth }}</span>
                  <Calendar class="w-3.5 h-3.5 text-slate-400" />
                  <input type="month" v-model="checkForm.month" @change="e => onCheckMonthChange(e.target.value)" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                </div>
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Mã Phiếu</label>
                <input type="text" :value="currentCheck?.id ? 'KK-' + currentCheck.id : 'Mới'" disabled class="w-full text-xs h-8 border border-slate-200 rounded-lg px-2.5 bg-slate-100 text-slate-500 cursor-not-allowed outline-none font-medium" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Kho</label>
                <input type="text" :value="activeWarehouse?.name || ''" disabled class="w-full text-xs h-8 border border-slate-200 rounded-lg px-2.5 bg-slate-100 text-slate-500 cursor-not-allowed outline-none font-medium" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Người Kiểm Kho</label>
                <input type="text" :value="authStore.user?.name || 'Chưa xác định'" disabled class="w-full text-xs h-8 border border-slate-200 rounded-lg px-2.5 bg-slate-100 text-slate-600 font-medium cursor-not-allowed outline-none" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Ghi Chú</label>
                <div class="relative w-full">
                  <input 
                    type="text" 
                    v-model="checkForm.note"
                    :disabled="isCheckLocked"
                    @input="checkDraftDirty = true"
                    placeholder="Nhập ghi chú..."  
                    class="w-full text-xs h-8 border border-slate-300 rounded-lg px-2.5 pr-7 outline-none focus:border-[var(--pms-custom-theme,#006bdb)] transition-all shadow-2xs bg-white text-[#000000D9]" 
                  />
                  <button 
                    v-if="checkForm.note"
                    type="button"
                    @click="checkForm.note = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full cursor-pointer border-none bg-transparent"
                    title="Xóa nhanh"
                  >
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
 
            <!-- Items table with max-height & sticky header -->
            <div class="border border-slate-200 rounded-xl overflow-auto bg-white shadow-2xs max-h-[340px] hk-scroll">
              <table class="w-full text-left border-collapse text-xs whitespace-nowrap">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs text-[#000000D9] font-semibold sticky top-0 z-10 shadow-[0_1px_0_0_#e2e8f0]">
                  <tr>
                    <th class="py-2.5 px-3 border-r border-slate-200 text-center w-28 align-middle border">Mã Kiểm Kê</th>
                    <th class="py-2.5 px-3 border-r border-slate-200 text-center w-24 align-middle border">Mã SP</th>
                    <th class="py-2.5 px-4 border-r border-slate-200 text-center align-middle border">Tên SP</th>
                    <th class="py-2.5 px-2 border-r border-slate-200 text-center w-20 align-middle border">Đơn Vị</th>
                    <th class="py-2.5 px-2 border-r border-slate-200 text-center w-40 align-middle border">Tồn Đầu Kỳ</th>
                    <th class="py-2.5 px-2 border-r border-slate-200 text-center w-40 align-middle border">Số Lượng Thực Tế</th>
                    <th class="py-2.5 px-2 border-r border-slate-200 text-center w-28 align-middle border">Số Chênh Lệch</th>
                    <th class="py-2.5 px-4 text-center align-middle border-r border-slate-200 border">Ghi Chú</th>
                    <th v-if="!isCheckLocked" class="py-2.5 px-2 text-center align-middle border-r border-slate-200 border w-10"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-[#000000D9]">
                  <tr v-if="!modalCheckItems.length">
                    <td colspan="9" class="py-12 text-center text-slate-400 border-slate-200">
                      <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl">📦</span>
                        <p class="font-semibold text-slate-600 text-xs">Chưa có sản phẩm trong phiếu kiểm kê</p>
                        <p class="text-xs">Nhấn <strong>Thêm</strong> để chọn sản phẩm</p>
                      </div>
                    </td>
                  </tr>
                  <tr v-else v-for="(item, idx) in modalCheckItems" :key="item.product_id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-2 px-3 border-slate-200 text-center text-slate-500 font-mono text-xs">{{ currentCheck?.id ? 'KK-' + currentCheck.id : 'Mới' }}</td>
                    <td class="py-2 px-3 border-slate-200 text-center text-slate-500 font-mono text-xs">{{ item.product_code }}</td>
                    <td class="py-2 px-4 border-slate-200 font-medium text-slate-800 text-xs">{{ item.product_name }}</td>
                    <td class="py-2 px-2 border-slate-200 text-center text-slate-600 text-xs">{{ item.unit || '—' }}</td>
                    <td class="py-2 px-2 border-slate-200 text-center">
                      <div class="inline-flex items-center border border-slate-300 rounded overflow-hidden shadow-2xs bg-white mx-auto">
                        <button 
                          type="button" 
                          :disabled="isCheckLocked"
                          @click="decreaseQty(item, 'well_balance')" 
                          class="w-6 h-6 flex items-center justify-center bg-slate-50 hover:bg-slate-200 text-slate-600 border-r border-slate-300 cursor-pointer select-none transition-colors"
                          title="Giảm 1"
                        >
                          <Minus class="w-3 h-3" />
                        </button>
                        <input 
                          type="number" 
                          min="0"
                          :value="item.well_balance"
                          :disabled="isCheckLocked"
                          :class="Number(item.well_balance) < 0 ? 'text-rose-600' : ''"
                          @change="e => onWellBalanceInput(item, e.target.value)"
                          class="w-12 text-center text-xs px-1 py-0.5 outline-none font-medium text-[#000000D9]"
                        />
                        <button 
                          type="button" 
                          :disabled="isCheckLocked"
                          @click="increaseQty(item, 'well_balance')" 
                          class="w-6 h-6 flex items-center justify-center bg-slate-50 hover:bg-slate-200 text-slate-600 border-l border-slate-300 cursor-pointer select-none transition-colors"
                          title="Tăng 1"
                        >
                          <Plus class="w-3 h-3" />
                        </button>
                      </div>
                    </td>
                    <td class="py-2 px-2 border-slate-200 text-center">
                      <div class="inline-flex items-center border border-slate-300 rounded overflow-hidden shadow-2xs bg-white mx-auto">
                        <button 
                          type="button" 
                          :disabled="isCheckLocked"
                          @click="decreaseQty(item, 'stoke_take')" 
                          class="w-6 h-6 flex items-center justify-center bg-slate-50 hover:bg-slate-200 text-slate-600 border-r border-slate-300 cursor-pointer select-none transition-colors"
                          title="Giảm 1"
                        >
                          <Minus class="w-3 h-3" />
                        </button>
                        <input 
                          type="number" 
                          min="0"
                          :value="item.stoke_take"
                          :disabled="isCheckLocked"
                          @change="e => onStokeTakeInput(item, e.target.value)"
                          class="w-12 text-center text-xs px-1 py-0.5 outline-none font-medium text-[#000000D9]"
                        />
                        <button 
                          type="button" 
                          :disabled="isCheckLocked"
                          @click="increaseQty(item, 'stoke_take')" 
                          class="w-6 h-6 flex items-center justify-center bg-slate-50 hover:bg-slate-200 text-slate-600 border-l border-slate-300 cursor-pointer select-none transition-colors"
                          title="Tăng 1"
                        >
                          <Plus class="w-3 h-3" />
                        </button>
                      </div>
                    </td>
                    <td class="py-2 px-2 border-slate-200 text-center font-bold text-xs"
                      :class="item.different_qty > 0 ? 'text-emerald-600' : item.different_qty < 0 ? 'text-rose-600' : 'text-[var(--pms-custom-theme,#006bdb)]'"
                    >
                      {{ item.different_qty >= 0 ? '+' : '' }}{{ item.different_qty }}
                    </td>
                    <td class="py-2 px-3 border-slate-200">
                      <div class="relative w-full">
                        <input 
                          type="text" 
                          :value="item.note"
                          :disabled="isCheckLocked"
                          @change="e => setDraftNote(item, e.target.value)"
                          placeholder="..." 
                          class="w-full text-xs border border-slate-300 rounded-md px-2 py-1 pr-6 focus:outline-none focus:border-[var(--pms-custom-theme,#006bdb)] transition-all bg-white text-[#000000D9]"
                        />
                        <button 
                          v-if="item.note && !isCheckLocked"
                          type="button"
                          @click="setDraftNote(item, '')"
                          class="absolute right-1.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full cursor-pointer border-none bg-transparent"
                          title="Xóa nhanh"
                        >
                          <X class="w-3 h-3" />
                        </button>
                      </div>
                    </td>
                    <td v-if="!isCheckLocked" class="py-2 px-2 border-slate-200 text-center">
                      <button type="button" @click="removeDraftItem(item)" class="text-slate-400 hover:text-rose-600 p-0.5 rounded cursor-pointer border-none bg-transparent" title="Bỏ sản phẩm khỏi phiếu">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
 
          <!-- Footer -->
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex items-center justify-end gap-2.5 shrink-0">
            <button v-if="!isCheckLocked" @click="showAddProductCheckModal = true; loadProductsInStock()" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm
            </button>
            <button @click="deleteCheck" v-if="currentCheck && !isCheckLocked" class="btn-pms-danger flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Trash2 class="w-3.5 h-3.5" /> Xóa
            </button>
            <button v-if="!isCheckLocked" @click="saveCheck()" :disabled="isSaving" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <Save class="w-3.5 h-3.5" /> {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
            </button>
            <button @click="exportExcelCheck" v-if="currentCheck" class="btn-pms-secondary flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <FileSpreadsheet class="w-3.5 h-3.5" /> Xuất Excel
            </button>
            <button v-if="!isCheckLocked" @click="syncPreviousMonth" :disabled="isSaving" class="btn-pms-secondary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer" title="Lấy danh sách sản phẩm và tồn cuối tháng trước điền vào Tồn đầu kỳ & Thực tế">
              <BarChart2 class="w-3.5 h-3.5" /> {{ isSaving ? 'Đang lấy...' : 'Thống Kê' }}
            </button>
            <button @click="closeCheckModal" class="btn-pms-close flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer">
              <X class="w-3.5 h-3.5" /> Đóng
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
          <div class="px-5 py-3 flex items-center justify-between shrink-0 shadow-sm text-white cursor-move select-none rounded-t-xl" @mousedown="startDragProdModal" style="background: var(--pms-custom-theme, #006bdb)">
            <h3 class="text-white font-bold text-xs tracking-wide">Chọn Sản Phẩm</h3>
            <button @click="showAddProductCheckModal = false" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-4 h-4" /></button>
          </div>
          <div class="p-0 flex flex-col max-h-[60vh] overflow-y-auto bg-white hk-scroll">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="bg-slate-100 sticky top-0 z-10 shadow-sm font-semibold">
                <tr>
                  <th class="py-2.5 px-3 border border-slate-200 w-12 text-center align-middle">
                    <input type="checkbox" :checked="isAllSelected" @change="e => toggleSelectAll(e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-[var(--pms-custom-theme,#006bdb)] focus:ring-[var(--pms-custom-theme,#006bdb)] cursor-pointer" />
                  </th>
                  <th class="py-2.5 px-4 text-[#000000D9] text-xs font-semibold text-center align-middle border border-slate-200">Sản Phẩm Buồng Phòng</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-[#000000D9] text-xs">
                <tr v-if="productsInStock.length === 0">
                  <td colspan="2" class="py-8 text-center text-slate-400 text-xs border-slate-200">Kho này chưa có sản phẩm nào chọn "Có theo dõi" tồn kho</td>
                </tr>
                <template v-else v-for="wh in productsInStock" :key="'wh-'+wh.id">
                  <!-- Outlet Row (e.g. Minibar, Giặt ủi...) -->
                  <tr class="bg-slate-50 hover:bg-slate-100 transition-colors">
                    <td class="py-2.5 px-3 border-slate-200 text-center w-12">
                      <input type="checkbox" :checked="isOutletSelected(wh)" @change="e => toggleOutletSelect(wh, e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-[var(--pms-custom-theme,#006bdb)] focus:ring-[var(--pms-custom-theme,#006bdb)] cursor-pointer" />
                    </td>
                    <td class="py-2.5 px-4 cursor-pointer border-slate-200" @click="toggleOutlet(wh.id)">
                      <div class="flex items-center gap-2 select-none">
                        <div class="w-5 h-5 bg-[var(--pms-custom-theme,#006bdb)] flex items-center justify-center text-white text-xs leading-none pb-[1.5px] rounded-md shadow-sm font-bold">{{ expandedOutlets[wh.id] ? '-' : '+' }}</div>
                        <span class="font-bold text-slate-800">{{ toTitleCase(wh.name) }}<template v-if="wh.code && !String(wh.code).startsWith('warehouse-')"> ({{ wh.code }})</template></span>
                      </div>
                    </td>
                  </tr>
                  <!-- Categories and Products if Outlet is expanded -->
                  <template v-if="expandedOutlets[wh.id]">
                    <template v-for="cat in wh.categories" :key="'cat-'+cat.id">
                      <!-- Category Row (e.g. Nước ngọt, Bia...) -->
                      <tr class="bg-white hover:bg-slate-50/50 transition-colors">
                        <td class="py-2.5 px-3 border-slate-200 text-center">
                          <input type="checkbox" :checked="isCategorySelected(cat)" @change="e => toggleCategorySelect(cat, e.target.checked)" class="w-4 h-4 rounded border-slate-300 text-[var(--pms-custom-theme,#006bdb)] focus:ring-[var(--pms-custom-theme,#006bdb)] cursor-pointer" />
                        </td>
                        <td class="py-2.5 px-4 pl-8 cursor-pointer border-slate-200" @click="toggleCategory(cat.id)">
                          <div class="flex items-center gap-2 select-none">
                            <div class="w-4 h-4 bg-sky-600 flex items-center justify-center text-white text-xs leading-none pb-[1px] rounded-sm shadow-sm font-bold">{{ expandedCategories[cat.id] ? '-' : '+' }}</div>
                            <span class="font-bold text-slate-700">{{ toTitleCase(cat.name) }}</span>
                          </div>
                        </td>
                      </tr>
                      <!-- Products if Category is expanded -->
                      <template v-if="expandedCategories[cat.id]">
                        <tr v-for="p in cat.products" :key="'prod-'+p.id" class="hover:bg-slate-50 transition-colors bg-white">
                          <td class="py-2.5 px-3 border-slate-200 text-center">
                            <input type="checkbox" :value="p.id" v-model="selectedProductIds" class="w-4 h-4 rounded border-slate-300 text-[var(--pms-custom-theme,#006bdb)] focus:ring-[var(--pms-custom-theme,#006bdb)] cursor-pointer" />
                          </td>
                          <td class="py-2.5 px-4 pl-14 text-slate-700 font-normal select-none border-slate-200">{{ p.name }}</td>
                        </tr>
                      </template>
                    </template>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex justify-between items-center shrink-0">
            <span class="text-xs text-slate-500 font-semibold">{{ selectedProductIds.length }} SP Được Chọn</span>
            <div class="flex gap-2.5">
              <button @click="showAddProductCheckModal = false" class="btn-pms-close flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer">
                <X class="w-3.5 h-3.5" /> Đóng
              </button>
              <button @click="addProductsToCheck" :disabled="isSaving || !selectedProductIds.length" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer" title="Thêm vào phiếu kiểm kê (bấm Lưu ở phiếu để lưu)">
                <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm vào phiếu
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
          <div class="px-5 py-3 flex items-center justify-between shadow-sm text-white cursor-move select-none rounded-t-xl" @mousedown="startDragTransferModal" style="background: var(--pms-custom-theme, #006bdb)">
            <h3 class="text-white font-bold text-xs tracking-wide">Chuyển Kho</h3>
            <button @click="showTransferModal = false" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"><X class="w-4 h-4" /></button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50 text-xs">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-semibold text-slate-700">Sản Phẩm</label>
              <input type="text" :value="transferProductLabel" disabled class="w-full text-xs h-8 border border-slate-200 rounded-lg px-3 bg-slate-100 text-slate-500 cursor-not-allowed outline-none font-medium" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Kho Nguồn</label>
                <input type="text" :value="toTitleCase(activeWarehouse?.name || '')" disabled class="w-full text-xs h-8 border border-slate-200 rounded-lg px-3 bg-slate-100 text-slate-500 cursor-not-allowed outline-none font-medium" />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Kho Đích <span class="text-rose-500 font-bold">*</span></label>
                <select v-model="transferForm.transfer_to_warehouse_id" required class="input-required w-full text-xs h-8 rounded-lg px-3 outline-none font-medium text-[#000000D9]">
                  <option :value="null">-- Chọn Kho Đích --</option>
                  <option v-for="w in otherWarehouses" :key="w.id" :value="w.id">{{ toTitleCase(w.name) }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Ngày</label>
                <SingleDatePicker
                  v-model="transferForm.date"
                  :start-date="transferForm.date || systemDate"
                  placeholder="dd/mm/yyyy"
                  four-digit-year
                  input-class="w-full text-xs h-8 border border-slate-300 rounded-lg px-3 outline-none focus:border-[var(--pms-custom-theme,#006bdb)] transition-all bg-white shadow-2xs text-[#000000D9]"
                />
              </div>
              <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-slate-700">Số Lượng <span class="text-rose-500 font-bold">*</span></label>
                <input type="number" min="0.001" required v-model="transferForm.quantity" placeholder="0" class="input-required w-full text-xs h-8 rounded-lg px-3 outline-none font-medium text-[#000000D9]" />
              </div>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex justify-end gap-2.5">
            <button @click="showTransferModal = false" class="btn-pms-close flex items-center gap-1.5 h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer">
              <X class="w-3.5 h-3.5" /> Đóng
            </button>
            <button @click="submitTransfer" :disabled="isSaving || !transferForm.transfer_to_warehouse_id || !transferForm.quantity" class="btn-pms-primary flex items-center gap-1.5 h-8 px-4 disabled:opacity-50 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
              <ArrowRightLeft class="w-3.5 h-3.5" /> {{ isSaving ? 'Đang xử lý...' : 'Chuyển Kho' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Beautiful Statistics Modal -->
    <Transition name="hk-modal">
      <div v-if="showStatsModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-xs">
        <div class="relative bg-white rounded-xl shadow-2xl w-[500px] max-h-[85vh] flex flex-col overflow-hidden border border-slate-200">
          <!-- Header -->
          <div class="px-5 py-3 flex items-center justify-between text-white shrink-0 rounded-t-xl" style="background: var(--pms-custom-theme, #006bdb)">
            <div class="flex items-center gap-2">
              <BarChart3 class="w-4 h-4" />
              <span class="font-bold text-xs tracking-wide">{{ statsTitle }}</span>
            </div>
            <button @click="showStatsModal = false" class="text-white/80 hover:text-white p-1 rounded-full transition-colors cursor-pointer outline-none border-none bg-transparent">
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 overflow-y-auto flex-1 space-y-4 text-xs text-[#000000D9] bg-slate-50/50 hk-scroll">
            
            <!-- For Periodic Check Slip Statistics -->
            <template v-if="statsType === 'check'">
              <!-- Summary Badges Grid -->
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-white p-3 rounded-lg border border-slate-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-slate-500">Tổng SP</span>
                  <span class="text-xl font-bold text-slate-800 mt-1">{{ statsData.total }}</span>
                </div>
                <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-emerald-600">Khớp</span>
                  <span class="text-xl font-bold text-emerald-700 mt-1">{{ statsData.normal }}</span>
                </div>
                <div class="bg-rose-50 p-3 rounded-lg border border-rose-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-rose-600">Chênh Lệch</span>
                  <span class="text-xl font-bold text-rose-700 mt-1">{{ statsData.discrepancies }}</span>
                </div>
              </div>

              <!-- Detailed Discrepancy List -->
              <div v-if="statsData.discrepancyList && statsData.discrepancyList.length" class="space-y-2">
                <h4 class="font-semibold text-slate-700 flex items-center gap-1.5 text-xs tracking-wide">
                  <AlertTriangle class="w-3.5 h-3.5 text-rose-500" />
                  Danh Sách Hàng Chênh Lệch
                </h4>
                <div class="border border-slate-200 rounded-lg overflow-hidden bg-white max-h-60 overflow-y-auto shadow-2xs">
                  <table class="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr class="bg-slate-100 text-[#000000D9] font-semibold border-b border-slate-200">
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Sản Phẩm</th>
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Sổ Sách</th>
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Thực Tế</th>
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Chênh Lệch</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-normal">
                      <tr v-for="item in statsData.discrepancyList" :key="item.id" class="hover:bg-slate-50 transition-colors">
                        <td class="py-2 px-3 text-[#000000D9] border-slate-200">{{ item.product_name }}</td>
                        <td class="py-2 px-3 text-center text-slate-600 border-slate-200">{{ item.well_balance }}</td>
                        <td class="py-2 px-3 text-center text-[#000000D9] border-slate-200">{{ item.stoke_take }}</td>
                        <td class="py-2 px-3 text-center font-bold border-slate-200" :class="parseFloat(item.different_qty) > 0 ? 'text-emerald-600' : 'text-rose-600'">
                          {{ parseFloat(item.different_qty) > 0 ? '+' : '' }}{{ item.different_qty }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <div v-else class="flex flex-col items-center justify-center py-6 text-center text-slate-400 bg-white border border-slate-200 rounded-lg shadow-2xs">
                <CheckCircle2 class="w-8 h-8 text-emerald-500 mb-2" />
                <span class="font-semibold text-slate-600 text-xs">Tất cả sản phẩm đều khớp số liệu!</span>
              </div>
            </template>

            <!-- For Main Monthly Logs Statistics -->
            <template v-if="statsType === 'main'">
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-indigo-50/50 p-3 rounded-lg border border-indigo-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-indigo-600">Tổng Nhập</span>
                  <span class="text-xl font-bold text-indigo-700 mt-1">{{ statsData.totalReceive }}</span>
                </div>
                <div class="bg-sky-50 p-3 rounded-lg border border-sky-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-sky-600">Tổng Xuất</span>
                  <span class="text-xl font-bold text-sky-700 mt-1">{{ statsData.totalExport }}</span>
                </div>
                <div class="bg-rose-50 p-3 rounded-lg border border-rose-200 flex flex-col items-center justify-center shadow-2xs">
                  <span class="text-xs font-semibold text-rose-600">Tổng Chuyển</span>
                  <span class="text-xl font-bold text-rose-700 mt-1">{{ statsData.totalTransfer }}</span>
                </div>
              </div>

              <!-- Low Stock Warnings -->
              <div class="space-y-2">
                <h4 class="font-semibold text-slate-700 flex items-center gap-1.5 text-xs tracking-wide">
                  <AlertTriangle class="w-3.5 h-3.5 text-amber-500" />
                  Sản Phẩm Tồn Thấp (&lt; 50)
                </h4>
                <div v-if="statsData.lowStockProducts && statsData.lowStockProducts.length" class="border border-slate-200 rounded-lg overflow-hidden bg-white max-h-40 overflow-y-auto shadow-2xs">
                  <table class="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr class="bg-slate-100 text-[#000000D9] font-semibold border-b border-slate-200">
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Sản Phẩm</th>
                        <th class="py-2 px-3 text-center align-middle border border-slate-200">Tồn Cuối</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-normal">
                      <tr v-for="item in statsData.lowStockProducts" :key="item.product_id" class="hover:bg-slate-50 transition-colors">
                        <td class="py-2 px-3 text-[#000000D9] border-slate-200">{{ item.product_name }}</td>
                        <td class="py-2 px-3 text-center text-rose-600 font-semibold border-slate-200">{{ item.finalStock }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="p-3 text-center text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg">
                  Không có sản phẩm nào ở mức cảnh báo tồn thấp!
                </div>
              </div>

              <!-- High Activity Info -->
              <div v-if="statsData.highestActivityName" class="p-3 bg-slate-100/60 rounded-lg border border-slate-200 flex items-center gap-3">
                <TrendingUp class="w-4 h-4 text-indigo-500 shrink-0" />
                <div>
                  <div class="text-xs font-semibold text-slate-500">Hoạt Động Nhiều Nhất</div>
                  <div class="font-semibold text-[#000000D9] text-xs">{{ statsData.highestActivityName }} ({{ statsData.highestActivityCount }} lượt phát sinh)</div>
                </div>
              </div>
            </template>
            
          </div>

          <!-- Footer -->
          <div class="px-5 py-3 border-t border-slate-200 bg-white flex justify-end shrink-0">
            <button @click="showStatsModal = false" class="btn-pms-primary flex items-center justify-center h-8 px-5 rounded-lg text-xs font-semibold shadow-2xs cursor-pointer">
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
