<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { 
  fetchCompanies, createCompany, updateCompany, deleteCompany,
  fetchMarkets, fetchCustomerSources, fetchBranches, fetchBookers, fetchUsers,
  createMarket, createCustomerSource, createBranch, createBooker,
  syncCompanies, exportCompaniesExcel, importCompaniesExcel, companyTemplateExcel
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const allCompanies = ref([]) // Lưu toàn bộ công ty tải về
const markets = ref([])
const customerSources = ref([])
const branches = ref([])
const bookers = ref([])
const users = ref([])
const loading = ref(false)

// Pagination state
const currentPage = ref(1)
const perPage = ref(20)

// Column selector state
const isColumnSelectorOpen = ref(false)
const columns = ref([
  { id: 'code', label: 'Mã', visible: true },
  { id: 'name', label: 'Tên', visible: true },
  { id: 'trading_name', label: 'Tên giao dịch', visible: true },
  { id: 'address', label: 'Địa chỉ', visible: true },
  { id: 'tax_code', label: 'Tax', visible: true },
  { id: 'phone', label: 'Số điện thoại', visible: true },
  { id: 'email', label: 'Email', visible: true },
  { id: 'customer_source', label: 'Nguồn khách', visible: true },
  { id: 'market', label: 'Thị trường', visible: true },
  { id: 'max_debt', label: 'Công nợ tối đa', visible: true },
  { id: 'bank_account', label: 'Tài khoản ngân hàng', visible: true },
  { id: 'booker', label: 'Người đặt phòng', visible: true },
  { id: 'sales_person', label: 'Người bán', visible: true },
  { id: 'rate_code', label: 'Mã giá phòng', visible: true },
  { id: 'branch', label: 'Chi nhánh', visible: true },
])

const isColumnVisible = (columnId) => {
  const col = columns.value.find(c => c.id === columnId)
  return col ? col.visible : true
}

// Filter, Search, Sort states
const searchQuery = ref('')
const isSearchOpen = ref(false)
const sortField = ref('') // 'code' hoặc 'trading_name'
const sortDir = ref('asc') // 'asc' hoặc 'desc'

const activeFilterCol = ref('') // 'market', 'source', 'booker', 'branch'
const selectedMarkets = ref([])
const selectedSources = ref([])
const selectedBookers = ref([])
const selectedBranches = ref([])

const tempSelectedMarkets = ref([])
const tempSelectedSources = ref([])
const tempSelectedBookers = ref([])
const tempSelectedBranches = ref([])

// Popovers click outside helper
const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    activeFilterCol.value = ''
    isSearchOpen.value = false
    isColumnSelectorOpen.value = false
  }
}

// Modal state
const isModalOpen = ref(false)
const isEditMode = ref(false)
const currentId = ref(null)

const emptyForm = () => ({
  code: '',
  name: '',
  trading_name: '',
  address: '',
  tax_code: '',
  phone: '',
  email: '',
  customer_source_id: '',
  market_id: '',
  sync_acc: false,
  max_debt: 0,
  bank_account: '',
  booker_id: '',
  sales_person_id: '',
  rate_code: '',
  branch_id: '',
  is_active: true,
})

const form = ref(emptyForm())

onMounted(() => {
  loadData()
  loadLookups()
  document.addEventListener('click', closeAllPopovers)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
})

const loadLookups = async () => {
  try {
    const [mRes, csRes, bRes, bkRes, uRes] = await Promise.all([
      fetchMarkets(),
      fetchCustomerSources(),
      fetchBranches(),
      fetchBookers(),
      fetchUsers({ per_page: 1000 })
    ])
    markets.value = mRes.data.data || []
    customerSources.value = csRes.data.data || []
    branches.value = bRes.data.data || []
    bookers.value = bkRes.data.data || []
    users.value = (uRes.data.data || []).filter(u => u.is_active_user !== false && u.is_active_user !== 0)
  } catch (err) {
    console.error('Error loading lookups:', err)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await fetchCompanies({ per_page: 1000 })
    allCompanies.value = res.data.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  isEditMode.value = false
  currentId.value = null
  form.value = emptyForm()
  isModalOpen.value = true
}

const openEditModal = (item) => {
  isEditMode.value = true
  currentId.value = item.id
  form.value = {
    code: item.code || '',
    name: item.name || '',
    trading_name: item.trading_name || '',
    address: item.address || '',
    tax_code: item.tax_code || '',
    phone: item.phone || '',
    email: item.email || '',
    customer_source_id: item.customer_source_id || '',
    market_id: item.market_id || '',
    sync_acc: item.sync_acc || false,
    max_debt: item.max_debt || 0,
    bank_account: item.bank_account || '',
    booker_id: item.booker_id || '',
    sales_person_id: item.sales_person_id || '',
    rate_code: item.rate_code || '',
    branch_id: item.branch_id || '',
    is_active: item.is_active !== undefined ? item.is_active : true,
  }
  isModalOpen.value = true
}

const saveItem = async () => {
  if (!form.value.name) {
    uiStore.showToast('Vui lòng nhập tên công ty', 'warning')
    return
  }
  if (!form.value.trading_name) {
    uiStore.showToast('Vui lòng nhập tên giao dịch', 'warning')
    return
  }
  if (!form.value.market_id) {
    uiStore.showToast('Vui lòng chọn thị trường', 'warning')
    return
  }
  if (!form.value.customer_source_id) {
    uiStore.showToast('Vui lòng chọn nguồn khách', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = { ...form.value }
    if (!payload.customer_source_id) payload.customer_source_id = null
    if (!payload.market_id) payload.market_id = null
    if (!payload.branch_id) payload.branch_id = null
    if (!payload.booker_id) payload.booker_id = null
    if (!payload.sales_person_id) payload.sales_person_id = null

    if (isEditMode.value) {
      await updateCompany(currentId.value, payload)
      uiStore.showToast('Cập nhật công ty thành công!', 'success')
    } else {
      await createCompany(payload)
      uiStore.showToast('Thêm công ty thành công!', 'success')
    }
    isModalOpen.value = false
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Có lỗi xảy ra'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

const handleDelete = async (item) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc chắn muốn xóa công ty "${item.name}" (${item.code})?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    await deleteCompany(item.id)
    uiStore.showToast('Xóa công ty thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa công ty này', 'error')
  }
}

const toggleSyncAcc = async (item) => {
  try {
    await updateCompany(item.id, { ...item, sync_acc: !item.sync_acc })
    item.sync_acc = !item.sync_acc
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể cập nhật đồng bộ ACC', 'error')
  }
}

// Quick create states
const isQuickMarketOpen = ref(false)
const quickMarketForm = ref({ code: '', name: '' })

const isQuickCustomerSourceOpen = ref(false)
const quickCustomerSourceForm = ref({ code: '', name: '' })

const isQuickBranchOpen = ref(false)
const quickBranchForm = ref({ name: '' })

const isQuickBookerOpen = ref(false)
const quickBookerForm = ref({ name: '', email: '', phone: '', address: '', notes: '' })

// Quick create functions
const saveQuickMarket = async () => {
  if (!quickMarketForm.value.name) {
    uiStore.showToast('Vui lòng nhập tên thị trường', 'warning')
    return
  }
  if (!quickMarketForm.value.code) {
    uiStore.showToast('Vui lòng nhập mã thị trường', 'warning')
    return
  }
  try {
    const res = await createMarket(quickMarketForm.value)
    uiStore.showToast('Thêm thị trường thành công!', 'success')
    const mRes = await fetchMarkets()
    markets.value = mRes.data.data || []
    if (res.data?.data?.id) {
      form.value.market_id = res.data.data.id
    }
    isQuickMarketOpen.value = false
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra', 'error')
  }
}

const saveQuickCustomerSource = async () => {
  if (!quickCustomerSourceForm.value.name) {
    uiStore.showToast('Vui lòng nhập tên nguồn khách', 'warning')
    return
  }
  if (!quickCustomerSourceForm.value.code) {
    uiStore.showToast('Vui lòng nhập mã nguồn khách', 'warning')
    return
  }
  try {
    const res = await createCustomerSource(quickCustomerSourceForm.value)
    uiStore.showToast('Thêm nguồn khách thành công!', 'success')
    const csRes = await fetchCustomerSources()
    customerSources.value = csRes.data.data || []
    if (res.data?.data?.id) {
      form.value.customer_source_id = res.data.data.id
    }
    isQuickCustomerSourceOpen.value = false
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra', 'error')
  }
}

const saveQuickBranch = async () => {
  if (!quickBranchForm.value.name) {
    uiStore.showToast('Vui lòng nhập tên chi nhánh', 'warning')
    return
  }
  try {
    const res = await createBranch(quickBranchForm.value)
    uiStore.showToast('Thêm chi nhánh thành công!', 'success')
    const bRes = await fetchBranches()
    branches.value = bRes.data.data || []
    if (res.data?.data?.id) {
      form.value.branch_id = res.data.data.id
    }
    isQuickBranchOpen.value = false
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra', 'error')
  }
}

const saveQuickBooker = async () => {
  if (!quickBookerForm.value.name) {
    uiStore.showToast('Vui lòng nhập tên người đặt phòng', 'warning')
    return
  }
  try {
    const res = await createBooker(quickBookerForm.value)
    uiStore.showToast('Thêm người đặt phòng thành công!', 'success')
    const bkRes = await fetchBookers()
    bookers.value = bkRes.data.data || []
    if (res.data?.data?.id) {
      form.value.booker_id = res.data.data.id
    }
    isQuickBookerOpen.value = false
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra', 'error')
  }
}

const goToPage = (page) => {
  if (page >= 1 && page <= computedMeta.value.last_page) {
    currentPage.value = page
  }
}

const changePerPage = () => {
  currentPage.value = 1
}

// Sorting logic
const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
  currentPage.value = 1
}

// Filtering logic
const openFilter = (col) => {
  if (activeFilterCol.value === col) {
    activeFilterCol.value = ''
  } else {
    activeFilterCol.value = col
    if (col === 'market') tempSelectedMarkets.value = [...selectedMarkets.value]
    if (col === 'source') tempSelectedSources.value = [...selectedSources.value]
    if (col === 'booker') tempSelectedBookers.value = [...selectedBookers.value]
    if (col === 'branch') tempSelectedBranches.value = [...selectedBranches.value]
  }
}

const applyFilter = (col) => {
  if (col === 'market') selectedMarkets.value = [...tempSelectedMarkets.value]
  if (col === 'source') selectedSources.value = [...tempSelectedSources.value]
  if (col === 'booker') selectedBookers.value = [...tempSelectedBookers.value]
  if (col === 'branch') selectedBranches.value = [...tempSelectedBranches.value]
  activeFilterCol.value = ''
  currentPage.value = 1
}

const resetFilter = (col) => {
  if (col === 'market') {
    tempSelectedMarkets.value = []
    selectedMarkets.value = []
  }
  if (col === 'source') {
    tempSelectedSources.value = []
    selectedSources.value = []
  }
  if (col === 'booker') {
    tempSelectedBookers.value = []
    selectedBookers.value = []
  }
  if (col === 'branch') {
    tempSelectedBranches.value = []
    selectedBranches.value = []
  }
  activeFilterCol.value = ''
  currentPage.value = 1
}

// Computed filter/sort/page
const filteredAndSortedCompanies = computed(() => {
  let result = [...allCompanies.value]

  // 1. Search Query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    result = result.filter(c => c.name.toLowerCase().includes(q))
  }

  // 2. Filter Markets
  if (selectedMarkets.value.length > 0) {
    result = result.filter(c => selectedMarkets.value.includes(c.market_id))
  }

  // 3. Filter Customer Sources
  if (selectedSources.value.length > 0) {
    result = result.filter(c => selectedSources.value.includes(c.customer_source_id))
  }

  // 4. Filter Bookers
  if (selectedBookers.value.length > 0) {
    result = result.filter(c => selectedBookers.value.includes(c.booker_id))
  }

  // 5. Filter Branches
  if (selectedBranches.value.length > 0) {
    result = result.filter(c => selectedBranches.value.includes(c.branch_id))
  }

  // 6. Sort
  if (sortField.value) {
    const field = sortField.value
    const dir = sortDir.value === 'asc' ? 1 : -1
    result.sort((a, b) => {
      let valA = ''
      let valB = ''
      
      if (field === 'code') {
        valA = a.code || ''
        valB = b.code || ''
      } else if (field === 'trading_name') {
        valA = a.trading_name || ''
        valB = b.trading_name || ''
      } else {
        valA = a[field] || ''
        valB = b[field] || ''
      }
      
      return valA.toString().localeCompare(valB.toString(), undefined, { numeric: true, sensitivity: 'base' }) * dir
    })
  }

  return result
})

const paginatedCompanies = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  const end = start + perPage.value
  return filteredAndSortedCompanies.value.slice(start, end)
})

const computedMeta = computed(() => {
  const total = filteredAndSortedCompanies.value.length
  const lastPage = Math.ceil(total / perPage.value) || 1
  return {
    current_page: currentPage.value,
    last_page: lastPage,
    total: total
  }
})

// Computed helper mappings for template backward compatibility
const companies = computed(() => paginatedCompanies.value)
const meta = computed(() => computedMeta.value)

const visibleColumnsCount = computed(() => {
  return columns.value.filter(c => c.visible).length + 1
})

const isSourceFiltered = computed(() => selectedSources.value.length > 0)
const isMarketFiltered = computed(() => selectedMarkets.value.length > 0)
const isBookerFiltered = computed(() => selectedBookers.value.length > 0)
const isBranchFiltered = computed(() => selectedBranches.value.length > 0)

const fileInput = ref(null)

const handleSync = async () => {
  loading.value = true
  try {
    const res = await syncCompanies()
    uiStore.showToast(res.data.message || 'Đồng bộ dữ liệu thành công!', 'success')
    await loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Không thể đồng bộ dữ liệu', 'error')
  } finally {
    loading.value = false
  }
}

const handleExport = async () => {
  loading.value = true
  try {
    const res = await exportCompaniesExcel()
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'companies.csv')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    uiStore.showToast('Xuất excel thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xuất dữ liệu excel', 'error')
  } finally {
    loading.value = false
  }
}

const triggerImport = () => {
  if (fileInput.value) {
    fileInput.value.click()
  }
}

const handleImport = async (e) => {
  const files = e.target.files
  if (!files || files.length === 0) return

  const file = files[0]
  e.target.value = ''

  const formData = new FormData()
  formData.append('file', file)

  loading.value = true
  try {
    const res = await importCompaniesExcel(formData)
    uiStore.showToast(res.data.message || 'Nhập excel thành công!', 'success')
    await loadData()
  } catch (err) {
    console.error(err)
    const errMsg = err.response?.data?.message || 'Lỗi khi nhập file excel/csv'
    uiStore.showToast(errMsg, 'error')
  } finally {
    loading.value = false
  }
}
const handleDownloadTemplate = async () => {
  loading.value = true
  try {
    const res = await companyTemplateExcel()
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'company_template.csv')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    uiStore.showToast('Tải file mẫu thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải file mẫu excel', 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs">
    <!-- Toolbar -->
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
      <div class="flex items-center gap-2 flex-wrap">
        <button 
          @click="openAddModal"
          class="px-3.5 py-1.5 bg-[#5fa5e6] hover:bg-[#4d92d4] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-xs transition-colors"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Thêm
        </button>
        <button @click="handleSync" class="px-3.5 py-1.5 bg-[#10b981] hover:bg-[#059669] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1.5 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Đồng bộ
        </button>
        <button @click="handleExport" class="px-3.5 py-1.5 bg-[#5fa5e6] hover:bg-[#4d92d4] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1.5 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Xuất excel
        </button>
        <button @click="triggerImport" class="px-3.5 py-1.5 bg-[#5fa5e6] hover:bg-[#4d92d4] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1.5 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          Nhập excel
        </button>
        <button @click="handleDownloadTemplate" class="px-3.5 py-1.5 bg-[#f8fafc] hover:bg-slate-100 text-slate-650 rounded-md text-xs font-bold border border-slate-200 cursor-pointer flex items-center gap-1.5 shadow-xs transition-colors" title="Tải file CSV mẫu để nhập liệu">
          <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Tải file mẫu
        </button>
        <input type="file" ref="fileInput" class="hidden" accept=".csv" @change="handleImport" />
      </div>

      <!-- Column Selector & Help -->
      <div class="flex items-center gap-2 relative popover-container">
        <!-- Sliders Button -->
        <button 
          @click.stop="isColumnSelectorOpen = !isColumnSelectorOpen"
          class="p-1.5 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-md cursor-pointer flex items-center justify-center transition-colors shadow-xs"
          title="Ẩn/hiện cột"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </button>

        <!-- Help Button -->
        <button class="p-1.5 bg-white border border-slate-200 text-[#5fa5e6] hover:text-[#4d92d4] hover:bg-slate-50 rounded-md cursor-pointer flex items-center justify-center transition-colors shadow-xs" title="Trợ giúp">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>

        <!-- Column Selector Dropdown -->
        <div 
          v-if="isColumnSelectorOpen" 
          class="absolute right-0 top-full mt-1.5 z-40 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1"
        >
          <label v-for="col in columns" :key="col.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
            <input type="checkbox" v-model="col.visible" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
            <span class="text-xs text-slate-700 font-semibold">{{ col.label }}</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-sm flex-1 max-h-full">
      <table class="w-full text-left border-collapse text-xs min-w-[1400px]">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
            <!-- Mã -->
            <th v-if="isColumnVisible('code')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap w-24 cursor-pointer hover:bg-slate-200 select-none transition-colors" @click="toggleSort('code')">
              <div class="flex items-center justify-between gap-1.5">
                <span>Mã</span>
                <span class="flex flex-col text-[9px] leading-[6px] text-slate-400">
                  <span :class="{'text-sky-500': sortField === 'code' && sortDir === 'asc'}">▲</span>
                  <span :class="{'text-sky-500': sortField === 'code' && sortDir === 'desc'}">▼</span>
                </span>
              </div>
            </th>

            <!-- Tên -->
            <th v-if="isColumnVisible('name')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Tên</span>
                <button 
                  @click.stop="isSearchOpen = !isSearchOpen" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQuery}"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </button>
              </div>
              <!-- Search Popover -->
              <div v-if="isSearchOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[220px] normal-case font-normal text-slate-700">
                <div class="relative flex items-center">
                  <input 
                    v-model="searchQuery" 
                    type="text" 
                    placeholder="Tìm kiếm tên..." 
                    class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                    @click.stop
                  />
                  <button 
                    v-if="searchQuery" 
                    @click.stop="searchQuery = ''" 
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </th>

            <!-- Tên giao dịch -->
            <th v-if="isColumnVisible('trading_name')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap cursor-pointer hover:bg-slate-200 select-none transition-colors" @click="toggleSort('trading_name')">
              <div class="flex items-center justify-between gap-1.5">
                <span>Tên giao dịch</span>
                <span class="flex flex-col text-[9px] leading-[6px] text-slate-400">
                  <span :class="{'text-sky-500': sortField === 'trading_name' && sortDir === 'asc'}">▲</span>
                  <span :class="{'text-sky-500': sortField === 'trading_name' && sortDir === 'desc'}">▼</span>
                </span>
              </div>
            </th>

            <!-- Địa chỉ -->
            <th v-if="isColumnVisible('address')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Địa chỉ</th>

            <!-- Tax -->
            <th v-if="isColumnVisible('tax_code')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap w-24">Tax</th>

            <!-- Số điện thoại -->
            <th v-if="isColumnVisible('phone')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Số điện thoại</th>

            <!-- Email -->
            <th v-if="isColumnVisible('email')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Email</th>

            <!-- Nguồn khách -->
            <th v-if="isColumnVisible('customer_source')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Nguồn khách</span>
                <button 
                  @click.stop="openFilter('source')" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': isSourceFiltered}"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <!-- Filter Popover -->
              <div v-if="activeFilterCol === 'source'" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] max-h-[250px] overflow-hidden flex flex-col normal-case font-normal text-slate-700">
                <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
                  <label v-for="s in customerSources" :key="s.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                    <input type="checkbox" :value="s.id" v-model="tempSelectedSources" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                    <span class="text-xs text-slate-700 font-semibold">{{ s.name }}</span>
                  </label>
                  <div v-if="customerSources.length === 0" class="text-slate-400 text-center py-2 text-xs">Không có dữ liệu</div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-2 gap-2">
                  <button @click.stop="resetFilter('source')" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 bg-transparent border-none rounded text-xs cursor-pointer font-bold transition-colors">Reset</button>
                  <button @click.stop="applyFilter('source')" class="px-3.5 py-1 bg-sky-500 hover:bg-sky-600 text-white border-none rounded text-xs cursor-pointer font-bold shadow-xs transition-colors">OK</button>
                </div>
              </div>
            </th>

            <!-- Thị trường -->
            <th v-if="isColumnVisible('market')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Thị trường</span>
                <button 
                  @click.stop="openFilter('market')" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': isMarketFiltered}"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <!-- Filter Popover -->
              <div v-if="activeFilterCol === 'market'" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] max-h-[250px] overflow-hidden flex flex-col normal-case font-normal text-slate-700">
                <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
                  <label v-for="m in markets" :key="m.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                    <input type="checkbox" :value="m.id" v-model="tempSelectedMarkets" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                    <span class="text-xs text-slate-700 font-semibold">{{ m.name }}</span>
                  </label>
                  <div v-if="markets.length === 0" class="text-slate-400 text-center py-2 text-xs">Không có dữ liệu</div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-2 gap-2">
                  <button @click.stop="resetFilter('market')" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 bg-transparent border-none rounded text-xs cursor-pointer font-bold transition-colors">Reset</button>
                  <button @click.stop="applyFilter('market')" class="px-3.5 py-1 bg-sky-500 hover:bg-sky-600 text-white border-none rounded text-xs cursor-pointer font-bold shadow-xs transition-colors">OK</button>
                </div>
              </div>
            </th>

            <!-- Công nợ tối đa -->
            <th v-if="isColumnVisible('max_debt')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap text-right">Công nợ tối đa</th>

            <!-- Tài khoản ngân hàng -->
            <th v-if="isColumnVisible('bank_account')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Tài khoản ngân hàng</th>

            <!-- Người đặt phòng -->
            <th v-if="isColumnVisible('booker')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Người đặt phòng</span>
                <button 
                  @click.stop="openFilter('booker')" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': isBookerFiltered}"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <!-- Filter Popover -->
              <div v-if="activeFilterCol === 'booker'" class="absolute right-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] max-h-[250px] overflow-hidden flex flex-col normal-case font-normal text-slate-700">
                <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
                  <label v-for="b in bookers" :key="b.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                    <input type="checkbox" :value="b.id" v-model="tempSelectedBookers" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                    <span class="text-xs text-slate-700 font-semibold">{{ b.name }}</span>
                  </label>
                  <div v-if="bookers.length === 0" class="text-slate-400 text-center py-2 text-xs">Không có dữ liệu</div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-2 gap-2">
                  <button @click.stop="resetFilter('booker')" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 bg-transparent border-none rounded text-xs cursor-pointer font-bold transition-colors">Reset</button>
                  <button @click.stop="applyFilter('booker')" class="px-3.5 py-1 bg-sky-500 hover:bg-sky-600 text-white border-none rounded text-xs cursor-pointer font-bold shadow-xs transition-colors">OK</button>
                </div>
              </div>
            </th>

            <!-- Người bán -->
            <th v-if="isColumnVisible('sales_person')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Người bán</th>

            <!-- Mã giá phòng -->
            <th v-if="isColumnVisible('rate_code')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap">Mã giá phòng</th>

            <!-- Chi nhánh -->
            <th v-if="isColumnVisible('branch')" class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Chi nhánh</span>
                <button 
                  @click.stop="openFilter('branch')" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': isBranchFiltered}"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
              <!-- Filter Popover -->
              <div v-if="activeFilterCol === 'branch'" class="absolute right-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] max-h-[250px] overflow-hidden flex flex-col normal-case font-normal text-slate-700">
                <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
                  <label v-for="b in branches" :key="b.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                    <input type="checkbox" :value="b.id" v-model="tempSelectedBranches" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                    <span class="text-xs text-slate-700 font-semibold">{{ b.name }}</span>
                  </label>
                  <div v-if="branches.length === 0" class="text-slate-400 text-center py-2 text-xs">Không có dữ liệu</div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-2 gap-2">
                  <button @click.stop="resetFilter('branch')" class="px-2.5 py-1 text-slate-400 hover:text-slate-600 hover:bg-slate-50 bg-transparent border-none rounded text-xs cursor-pointer font-bold transition-colors">Reset</button>
                  <button @click.stop="applyFilter('branch')" class="px-3.5 py-1 bg-sky-500 hover:bg-sky-600 text-white border-none rounded text-xs cursor-pointer font-bold shadow-xs transition-colors">OK</button>
                </div>
              </div>
            </th>

            <!-- Xóa -->
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase whitespace-nowrap text-center w-20">Xóa</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="item in companies" 
            :key="item.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors"
            @dblclick="openEditModal(item)"
          >
            <td v-if="isColumnVisible('code')" class="p-2 border-r border-slate-200 text-slate-700 font-semibold whitespace-nowrap">{{ item.code }}</td>
            <td v-if="isColumnVisible('name')" class="p-2 border-r border-slate-200 text-slate-800 font-semibold whitespace-nowrap">{{ item.name }}</td>
            <td v-if="isColumnVisible('trading_name')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap">{{ item.trading_name || '' }}</td>
            <td v-if="isColumnVisible('address')" class="p-2 border-r border-slate-200 text-slate-600 font-normal max-w-[200px] truncate" :title="item.address">{{ item.address || '' }}</td>
            <td v-if="isColumnVisible('tax_code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap">{{ item.tax_code || '' }}</td>
            <td v-if="isColumnVisible('phone')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap">{{ item.phone || '' }}</td>
            <td v-if="isColumnVisible('email')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.email || '' }}</td>
            <td v-if="isColumnVisible('customer_source')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.customer_source?.name || '' }}</td>
            <td v-if="isColumnVisible('market')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.market?.name || '' }}</td>
            <td v-if="isColumnVisible('max_debt')" class="p-2 border-r border-slate-200 text-slate-600 font-semibold whitespace-nowrap text-right">{{ item.max_debt ? Number(item.max_debt).toLocaleString('vi-VN') : '' }}</td>
            <td v-if="isColumnVisible('bank_account')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.bank_account || '' }}</td>
            <td v-if="isColumnVisible('booker')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.booker?.name || '' }}</td>
            <td v-if="isColumnVisible('sales_person')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.sales_person?.name || '' }}</td>
            <td v-if="isColumnVisible('rate_code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap">{{ item.rate_code || '' }}</td>
            <td v-if="isColumnVisible('branch')" class="p-2 border-r border-slate-200 text-slate-600 font-normal whitespace-nowrap text-xs">{{ item.branch?.name || '' }}</td>
            <td class="p-2 border-r border-slate-200 text-center">
              <button 
                @click.stop="handleDelete(item)"
                class="p-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="companies.length === 0 && !loading">
            <td :colspan="visibleColumnsCount" class="p-8 text-center text-slate-400 text-xs font-semibold">Chưa có dữ liệu công ty</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-end mt-3 gap-1 select-none">
      <button 
        @click="goToPage(meta.current_page - 1)"
        :disabled="meta.current_page <= 1"
        class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
      >&lt;</button>
      <button 
        v-for="p in meta.last_page" 
        :key="p"
        @click="goToPage(p)"
        class="px-2.5 py-1 border rounded text-xs font-bold cursor-pointer"
        :class="p === meta.current_page 
          ? 'border-sky-400 text-sky-600 bg-sky-50' 
          : 'border-slate-200 text-slate-500 bg-white hover:bg-slate-50'"
      >{{ p }}</button>
      <button 
        @click="goToPage(meta.current_page + 1)"
        :disabled="meta.current_page >= meta.last_page"
        class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
      >&gt;</button>
      <span class="text-xs text-slate-400 ml-2 mr-1">Tổng: {{ meta.total }}</span>
      <select v-model="perPage" @change="changePerPage" class="border border-slate-200 rounded px-1.5 py-0.5 bg-white text-[11px] text-slate-500 focus:outline-none cursor-pointer">
        <option :value="10">10 / page</option>
        <option :value="20">20 / page</option>
        <option :value="50">50 / page</option>
        <option :value="100">100 / page</option>
      </select>
    </div>
  </div>

  <!-- Modal Add/Edit Company -->
  <div 
    v-if="isModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs"
  >
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl overflow-hidden border border-slate-100 animate-in">
      <!-- Header -->
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">{{ isEditMode ? 'Sửa công ty' : 'Thêm công ty' }}</h2>
        <button @click="isModalOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
      </div>

      <!-- Body -->
      <div class="p-5 flex flex-col gap-4 overflow-y-auto max-h-[75vh] text-xs text-slate-700">
        
        <!-- Nhóm Thông tin -->
        <div class="flex flex-col gap-2.5">
          <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
            <h3 class="text-xs font-bold text-slate-800">Thông tin<span class="text-red-500">*</span></h3>
            <!-- Toggle Không sử dụng -->
            <div class="flex items-center gap-2 select-none">
              <span class="text-xs text-slate-500 font-semibold">Không sử dụng</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="!form.is_active" @change="form.is_active = !$event.target.checked" class="sr-only peer" />
                <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-x-5 gap-y-2.5">
            <!-- Cột trái -->
            <div class="flex flex-col gap-2.5">
              <!-- Mã -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Mã</span>
                <input :value="isEditMode ? form.code : 'Code'" type="text" readonly class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-slate-500 focus:outline-none text-xs" />
              </div>
              <!-- Tên -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Tên</span>
                <input v-model="form.name" type="text" placeholder="Tên" class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
              </div>
              <!-- Tên giao dịch -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Tên giao dịch</span>
                <input v-model="form.trading_name" type="text" placeholder="Tên giao dịch" class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
              </div>
              <!-- Công nợ tối đa -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Công nợ tối đa</span>
                <input v-model="form.max_debt" type="number" placeholder="Công nợ tối đa" class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
              </div>
              <!-- Người đặt phòng -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Người đặt phòng</span>
                <div class="flex items-center gap-1">
                  <select v-model="form.booker_id" class="flex-1 border border-slate-200 rounded-md p-1.5 bg-white font-semibold focus:outline-sky-500 text-xs">
                    <option value="">Người đặt phòng</option>
                    <option v-for="bk in bookers" :key="bk.id" :value="bk.id">{{ bk.name }}</option>
                  </select>
                  <button @click="isQuickBookerOpen = true; quickBookerForm = { name: '', email: '', phone: '', address: '', notes: '' }" type="button" class="w-7 h-7 rounded-full bg-white border border-[#8dcbf4] hover:bg-[#8dcbf4]/10 text-[#5fa5e6] flex items-center justify-center font-bold text-base transition-colors select-none cursor-pointer">
                    +
                  </button>
                </div>
              </div>
              <!-- Người bán -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Người bán</span>
                <select v-model="form.sales_person_id" class="border border-slate-200 rounded-md p-1.5 bg-white font-semibold focus:outline-sky-500 text-xs">
                  <option value="">Người bán</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <!-- Cột phải -->
            <div class="flex flex-col gap-2.5">
              <!-- Địa chỉ -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Địa chỉ</span>
                <div class="relative flex items-center">
                  <span class="absolute left-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 014 0m-5 8a2 2 0 100-4 2 2 0 000 4zm5.333-2c0-.737-.324-1.4-.84-1.847A3.001 3.001 0 0012 11h.012a3.001 3.001 0 002.828 1.847c-.516.447-.84 1.11-.84 1.847" />
                    </svg>
                  </span>
                  <input v-model="form.address" type="text" placeholder="Địa chỉ" class="pl-8 w-full border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
                </div>
              </div>
              <!-- Tax -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Tax</span>
                <div class="relative flex items-center">
                  <input v-model="form.tax_code" type="text" placeholder="Tax" class="pr-8 w-full border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
                  <span class="absolute right-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </span>
                </div>
              </div>
              <!-- Số điện thoại -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Số điện thoại</span>
                <div class="relative flex items-center">
                  <span class="absolute left-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                  </span>
                  <input v-model="form.phone" type="text" placeholder="Số điện thoại" class="pl-8 w-full border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
                </div>
              </div>
              <!-- Email -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Email</span>
                <div class="relative flex items-center">
                  <span class="absolute left-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </span>
                  <input v-model="form.email" type="email" placeholder="Email" class="pl-8 w-full border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
                </div>
              </div>
              <!-- Tài khoản ngân hàng -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Tài khoản ngân hàng</span>
                <input v-model="form.bank_account" type="text" placeholder="Tài khoản ngân hàng" class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
              </div>
              <!-- Mã giá phòng -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Mã giá phòng</span>
                <select v-model="form.rate_code" class="border border-slate-200 rounded-md p-1.5 bg-white font-semibold focus:outline-sky-500 text-xs">
                  <option value="">Mã giá phòng</option>
                  <option value="STANDARD">Standard Rate</option>
                  <option value="CORP">Corporate Rate</option>
                  <option value="PROMO">Promotion Rate</option>
                </select>
              </div>
              <!-- Cho phép thanh toán công nợ -->
              <div class="flex items-center justify-between pt-1 select-none">
                <span class="font-bold text-slate-600">Cho phép thanh toán công nợ</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.sync_acc" class="sr-only peer" />
                  <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#8dcbf4]"></div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Đường kẻ ngang phân cách -->
        <hr class="border-t border-slate-200 my-1" />

        <!-- Nhóm Thống kê -->
        <div class="flex flex-col gap-2.5">
          <h3 class="text-xs font-bold text-slate-800 border-b border-slate-100 pb-1.5">Thống kê<span class="text-red-500">*</span></h3>
          
          <div class="grid grid-cols-2 gap-x-5 gap-y-2.5">
            <!-- Cột trái -->
            <div class="flex flex-col gap-2.5">
              <!-- Thị trường -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Thị trường</span>
                <div class="flex items-center gap-1">
                  <select v-model="form.market_id" class="flex-1 border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs">
                    <option value="">Thị trường</option>
                    <option v-for="m in markets" :key="m.id" :value="m.id">{{ m.name }}</option>
                  </select>
                  <button @click="isQuickMarketOpen = true; quickMarketForm = { code: '', name: '' }" type="button" class="w-7 h-7 rounded-full bg-white border border-[#8dcbf4] hover:bg-[#8dcbf4]/10 text-[#5fa5e6] flex items-center justify-center font-bold text-base transition-colors select-none cursor-pointer">
                    +
                  </button>
                </div>
              </div>
              <!-- Nguồn khách -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Nguồn khách</span>
                <div class="flex items-center gap-1">
                  <select v-model="form.customer_source_id" class="flex-1 border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs">
                    <option value="">Nguồn khách</option>
                    <option v-for="s in customerSources" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <button @click="isQuickCustomerSourceOpen = true; quickCustomerSourceForm = { code: '', name: '' }" type="button" class="w-7 h-7 rounded-full bg-white border border-[#8dcbf4] hover:bg-[#8dcbf4]/10 text-[#5fa5e6] flex items-center justify-center font-bold text-base transition-colors select-none cursor-pointer">
                    +
                  </button>
                </div>
              </div>
            </div>

            <!-- Cột phải -->
            <div class="flex flex-col gap-2.5">
              <!-- Chi nhánh -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Chi nhánh</span>
                <div class="flex items-center gap-1">
                  <select v-model="form.branch_id" class="flex-1 border border-slate-200 rounded-md p-1.5 bg-white font-semibold focus:outline-sky-500 text-xs">
                    <option value="">Chi nhánh</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                  <button @click="isQuickBranchOpen = true; quickBranchForm = { name: '' }" type="button" class="w-7 h-7 rounded-full bg-white border border-[#8dcbf4] hover:bg-[#8dcbf4]/10 text-[#5fa5e6] flex items-center justify-center font-bold text-base transition-colors select-none cursor-pointer">
                    +
                  </button>
                </div>
              </div>
              <!-- Mã OTA -->
              <div class="flex flex-col gap-1">
                <span class="font-bold text-slate-600">Mã OTA</span>
                <input v-model="form.rate_code" type="text" placeholder="Mã OTA" class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <!-- Nút Đóng -->
        <button 
          @click="isModalOpen = false" 
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors"
        >
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <!-- Nút Lưu -->
        <button 
          @click="saveItem" 
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Quick Add Market -->
  <div 
    v-if="isQuickMarketOpen" 
    class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 backdrop-blur-xs animate-fade-in animate-in"
  >
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100">
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">Thêm thị trường</h2>
        <button @click="isQuickMarketOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
      </div>
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tên *</label>
          <input v-model="quickMarketForm.name" type="text" placeholder="Nhập tên thị trường..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Mã *</label>
          <input v-model="quickMarketForm.code" type="text" placeholder="Nhập mã thị trường..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-bold focus:outline-sky-500 text-xs uppercase" />
        </div>
      </div>
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button @click="isQuickMarketOpen = false" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <button @click="saveQuickMarket" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Quick Add Customer Source -->
  <div 
    v-if="isQuickCustomerSourceOpen" 
    class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 backdrop-blur-xs animate-fade-in animate-in"
  >
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100">
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">Thêm nguồn khách</h2>
        <button @click="isQuickCustomerSourceOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
      </div>
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tên *</label>
          <input v-model="quickCustomerSourceForm.name" type="text" placeholder="Nhập tên nguồn khách..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Mã *</label>
          <input v-model="quickCustomerSourceForm.code" type="text" placeholder="Nhập mã nguồn khách..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-bold focus:outline-sky-500 text-xs uppercase" />
        </div>
      </div>
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button @click="isQuickCustomerSourceOpen = false" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <button @click="saveQuickCustomerSource" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Quick Add Branch -->
  <div 
    v-if="isQuickBranchOpen" 
    class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 backdrop-blur-xs animate-fade-in animate-in"
  >
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100">
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">Thêm chi nhánh</h2>
        <button @click="isQuickBranchOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
      </div>
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tên chi nhánh *</label>
          <input v-model="quickBranchForm.name" type="text" placeholder="Nhập tên chi nhánh..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
        </div>
      </div>
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button @click="isQuickBranchOpen = false" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <button @click="saveQuickBranch" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Quick Add Booker -->
  <div 
    v-if="isQuickBookerOpen" 
    class="fixed inset-0 z-60 flex items-center justify-center bg-black/60 backdrop-blur-xs animate-fade-in animate-in"
  >
    <div class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-100">
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">Thêm người đặt phòng</h2>
        <button @click="isQuickBookerOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
      </div>
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tên *</label>
          <input v-model="quickBookerForm.name" type="text" placeholder="Nhập tên..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Email</label>
            <input v-model="quickBookerForm.email" type="email" placeholder="email@example.com" class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Số điện thoại</label>
            <input v-model="quickBookerForm.phone" type="text" placeholder="0xxx xxx xxx" class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Địa chỉ</label>
          <textarea v-model="quickBookerForm.address" rows="2" placeholder="Nhập địa chỉ..." class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs resize-none"></textarea>
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Ghi chú</label>
          <textarea v-model="quickBookerForm.notes" rows="2" placeholder="Ghi chú..." class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs resize-none"></textarea>
        </div>
      </div>
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <button @click="isQuickBookerOpen = false" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <button @click="saveQuickBooker" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
