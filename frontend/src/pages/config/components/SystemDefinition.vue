<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import HkConfigTab from './HkConfigTab.vue'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Hình Thức Thanh Toán'
  }
})

const emit = defineEmits(['update:activeTab'])

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const activeSystemTab = ref(props.initialTab === 'Mẫu' ? 'Hình Thức Thanh Toán' : props.initialTab)

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null
let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape') {
    if (isPaymentModalOpen.value) isPaymentModalOpen.value = false
    else if (isCurrencyModalOpen.value) isCurrencyModalOpen.value = false
    else if (isUnitModalOpen.value) isUnitModalOpen.value = false
    else if (isStatusModalOpen.value) isStatusModalOpen.value = false
  }
}

watch(() => props.initialTab, (newVal) => {
  if (newVal && newVal !== 'Mẫu') {
    activeSystemTab.value = newVal
  }
})

watch(activeSystemTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const systemTabs = [
  'Hình Thức Thanh Toán',
  'Tiền Tệ',
  'Đơn Vị Tính',
  'Tình Trạng Đăng Ký',
  'Cấu Hình Housekeeping'
]

const isTabActive = (tabName) => {
  if (!activeSystemTab.value) return false
  const cur = String(activeSystemTab.value).trim().toUpperCase()
  const target = String(tabName).trim().toUpperCase()
  return cur === target
}

// Dropdown lists
const departments = ['Reception/ Lê Tân', 'Restaurant/Nhà Hàng', 'House Keeping/Buồng Phòng', 'Spa']

// Data lists
const paymentMethods = ref([])
const currencies = ref([])
const unitsOfMeasure = ref([])
const registrationStatuses = ref([])

// Search queries
const searchQueries = reactive({
  payment: '',
  currency: '',
  unit: '',
  status: ''
})

// Pagination
const pagination = reactive({
  payment: { page: 1, limit: 10 },
  currency: { page: 1, limit: 10 },
  unit: { page: 1, limit: 10 },
  status: { page: 1, limit: 10 }
})

// Modal visibility & form states
const isEditMode = ref(false)

// 1. Payment Method Modal
const isPaymentModalOpen = ref(false)
const paymentForm = reactive({
  id: null,
  code: '',
  name: '',
  account: '',
  account_name: '',
  bank_name: '',
  service_charge: 0,
  department: '',
  payment_group: null,
  is_free: false,
  is_inactive: false
})

// 2. Currency Modal
const isCurrencyModalOpen = ref(false)
const currencyForm = reactive({
  id: null,
  code: '',
  name: '',
  country: '',
  short_name: '',
  decimals_to_round: 0,
  is_main: false,
  is_active: true,
  exchange_rate: 1,
  imageFile: null,
  imagePreview: null,
  remove_image: false
})
const currencyImageInput = ref(null)

// 3. Unit of Measure Modal
const isUnitModalOpen = ref(false)
const unitForm = reactive({
  id: null,
  code: '',
  name: '',
  symbol: '',
  is_inactive: false
})

// 4. Registration Status Modal
const isStatusModalOpen = ref(false)
const statusForm = reactive({
  booking_status_id: null,
  id: null,
  name: '',
  color: '#4086F7',
  confirmation_days: 0,
  description: '',
  status_value: 'Guaranteed',
  is_hidden: false,
  is_availability: true
})
const statusValues = ['Guaranteed', 'None Guaranteed', 'Cancelled', 'No Show', 'Waiting', 'Allotment']

// Fetch helper functions
const loadedTabs = ref(new Set())

const fetchDataForTab = async (tab) => {
  if (loadedTabs.value.has(tab)) return
  
  loading.value = true
  try {
    if (tab === 'HÌNH THỨC THANH TOÁN') {
      const res = await http.get('/payment-methods')
      paymentMethods.value = res.data.data || []
      loadedTabs.value.add(tab)
    } else if (tab === 'TIỀN TỆ') {
      const res = await http.get('/currencies')
      currencies.value = res.data.data || []
      loadedTabs.value.add(tab)
    } else if (tab === 'ĐƠN VỊ TÍNH') {
      const res = await http.get('/units-of-measure')
      unitsOfMeasure.value = res.data.data || []
      loadedTabs.value.add(tab)
    } else if (tab === 'TÌNH TRẠNG ĐĂNG KÝ') {
      const res = await http.get('/registration-statuses')
      registrationStatuses.value = res.data.data || []
      loadedTabs.value.add(tab)
    }
  } catch (err) {
    console.error(`Lỗi khi tải dữ liệu tab ${tab}:`, err)
    uiStore.showToast(`Không thể tải dữ liệu cho tab ${tab}`, 'error')
  } finally {
    loading.value = false
  }
}

watch(activeSystemTab, (newVal) => {
  if (newVal) {
    fetchDataForTab(newVal)
  }
}, { immediate: true })

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})

// Currency Image Handlers
const triggerCurrencyImageUpload = () => {
  currencyImageInput.value.click()
}
const handleCurrencyImageUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return
  currencyForm.imageFile = file
  currencyForm.remove_image = false
  const reader = new FileReader()
  reader.onload = (event) => {
    currencyForm.imagePreview = event.target.result
  }
  reader.readAsDataURL(file)
}
const removeCurrencyImage = () => {
  currencyForm.imageFile = null
  currencyForm.imagePreview = null
  currencyForm.remove_image = true
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
}

// Format Currency & Rate
const formatExchangeRate = (val) => {
  return new Intl.NumberFormat('vi-VN').format(val)
}

// Modal CRUD Actions

// 1. Payment Method Modal Actions
const openAddPayment = () => {
  isEditMode.value = false
  modalPos.value = { x: 0, y: 0 }
  Object.assign(paymentForm, {
    id: null,
    code: '',
    name: '',
    account: '',
    account_name: '',
    bank_name: '',
    service_charge: 0,
    department: '',
    payment_group: null,
    is_free: false,
    is_inactive: false
  })
  isPaymentModalOpen.value = true
}
const openEditPayment = (item) => {
  isEditMode.value = true
  modalPos.value = { x: 0, y: 0 }
  Object.assign(paymentForm, {
    id: item.id,
    code: item.code,
    name: item.name,
    account: item.account || '',
    account_name: item.account_name || '',
    bank_name: item.bank_name || '',
    service_charge: item.service_charge,
    department: item.department || '',
    payment_group: item.payment_group || null,
    is_free: !!item.is_free,
    is_inactive: !!item.is_inactive
  })
  isPaymentModalOpen.value = true
}
const savePayment = async () => {
  if (!paymentForm.code || !paymentForm.name) {
    uiStore.showToast('Vui lòng nhập mã và tên hình thức thanh toán (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/payment-methods/${paymentForm.id}`, paymentForm)
      uiStore.showToast('Cập nhật hình thức thanh toán thành công!', 'success')
    } else {
      await http.post('/payment-methods', paymentForm)
      uiStore.showToast('Thêm mới hình thức thanh toán thành công!', 'success')
    }
    isPaymentModalOpen.value = false
    const res = await http.get('/payment-methods')
    paymentMethods.value = res.data.data
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu hình thức thanh toán', 'error')
  } finally {
    loading.value = false
  }
}
const deletePayment = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa hình thức thanh toán này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/payment-methods/${id}`)
    uiStore.showToast('Xóa hình thức thanh toán thành công!', 'success')
    paymentMethods.value = paymentMethods.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa hình thức thanh toán', 'error')
  }
}
const togglePaymentFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/payment-methods/${item.id}`, {
      ...item,
      [field]: updatedVal
    })
    item[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

// 2. Currency Modal Actions
const openAddCurrency = () => {
  isEditMode.value = false
  modalPos.value = { x: 0, y: 0 }
  Object.assign(currencyForm, {
    id: null,
    code: '',
    name: '',
    country: '',
    short_name: '',
    decimals_to_round: 0,
    is_main: false,
    is_active: true,
    exchange_rate: 1,
    imageFile: null,
    imagePreview: null,
    remove_image: false
  })
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
  isCurrencyModalOpen.value = true
}
const openEditCurrency = (item) => {
  isEditMode.value = true
  modalPos.value = { x: 0, y: 0 }
  Object.assign(currencyForm, {
    id: item.id,
    code: item.code,
    name: item.name,
    country: item.country,
    short_name: item.short_name || '',
    decimals_to_round: item.decimals_to_round,
    is_main: !!item.is_main,
    is_active: !!item.is_active,
    exchange_rate: item.exchange_rate,
    imageFile: null,
    imagePreview: item.image_path || null,
    remove_image: false
  })
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
  isCurrencyModalOpen.value = true
}
const saveCurrency = async () => {
  if (!currencyForm.code || !currencyForm.name || !currencyForm.country) {
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin tiền tệ (*)', 'warning')
    return
  }
  loading.value = true
  try {
    const fd = new FormData()
    fd.append('code', currencyForm.code)
    fd.append('name', currencyForm.name)
    fd.append('country', currencyForm.country)
    fd.append('short_name', currencyForm.short_name)
    fd.append('decimals_to_round', currencyForm.decimals_to_round)
    fd.append('is_main', currencyForm.is_main ? '1' : '0')
    fd.append('is_active', currencyForm.is_active ? '1' : '0')
    fd.append('exchange_rate', currencyForm.exchange_rate)
    if (currencyForm.imageFile) {
      fd.append('image', currencyForm.imageFile)
    }
    if (currencyForm.remove_image) {
      fd.append('remove_image', 'true')
    }

    if (isEditMode.value) {
      fd.append('_method', 'PUT')
      await http.post(`/currencies/${currencyForm.id}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      uiStore.showToast('Cập nhật tiền tệ thành công!', 'success')
    } else {
      await http.post('/currencies', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      uiStore.showToast('Thêm mới tiền tệ thành công!', 'success')
    }
    isCurrencyModalOpen.value = false
    const res = await http.get('/currencies')
    currencies.value = res.data.data
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu tiền tệ', 'error')
  } finally {
    loading.value = false
  }
}
const deleteCurrency = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa tiền tệ này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/currencies/${id}`)
    uiStore.showToast('Xóa tiền tệ thành công!', 'success')
    currencies.value = currencies.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi xóa tiền tệ', 'error')
  }
}
const toggleCurrencyFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/currencies/${item.id}`, {
      ...item,
      [field]: updatedVal
    })
    item[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

// 3. Unit of Measure Modal Actions
const openAddUnit = () => {
  isEditMode.value = false
  modalPos.value = { x: 0, y: 0 }
  Object.assign(unitForm, {
    id: null,
    code: '',
    name: '',
    symbol: '',
    is_inactive: false
  })
  isUnitModalOpen.value = true
}
const openEditUnit = (item) => {
  isEditMode.value = true
  modalPos.value = { x: 0, y: 0 }
  Object.assign(unitForm, {
    id: item.id,
    code: item.code,
    name: item.name,
    symbol: item.symbol || '',
    is_inactive: !!item.is_inactive
  })
  isUnitModalOpen.value = true
}
const saveUnit = async () => {
  if (!unitForm.code || !unitForm.name) {
    uiStore.showToast('Vui lòng nhập mã và tên đơn vị tính (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/units-of-measure/${unitForm.id}`, unitForm)
      uiStore.showToast('Cập nhật đơn vị tính thành công!', 'success')
    } else {
      await http.post('/units-of-measure', unitForm)
      uiStore.showToast('Thêm mới đơn vị tính thành công!', 'success')
    }
    isUnitModalOpen.value = false
    const res = await http.get('/units-of-measure')
    unitsOfMeasure.value = res.data.data
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu đơn vị tính', 'error')
  } finally {
    loading.value = false
  }
}
const deleteUnit = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa đơn vị tính này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/units-of-measure/${id}`)
    uiStore.showToast('Xóa đơn vị tính thành công!', 'success')
    unitsOfMeasure.value = unitsOfMeasure.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa đơn vị tính', 'error')
  }
}
const toggleUnitFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/units-of-measure/${item.id}`, {
      ...item,
      [field]: updatedVal
    })
    item[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

// 4. Registration Status Modal Actions
const openAddStatus = () => {
  isEditMode.value = false
  modalPos.value = { x: 0, y: 0 }
  Object.assign(statusForm, {
    booking_status_id: null,
    id: null,
    name: '',
    color: '#4086F7',
    confirmation_days: 0,
    description: '',
    status_value: 'Guaranteed',
    is_hidden: false,
    is_availability: true
  })
  isStatusModalOpen.value = true
}
const openEditStatus = (item) => {
  isEditMode.value = true
  modalPos.value = { x: 0, y: 0 }
  Object.assign(statusForm, {
    booking_status_id: item.booking_status_id,
    id: item.id,
    name: item.name,
    color: item.color || '#4086F7',
    confirmation_days: item.confirmation_days !== undefined && item.confirmation_days !== null ? item.confirmation_days : (item.cut_off_day || 0),
    description: item.description || '',
    status_value: item.status_value || 'Guaranteed',
    is_hidden: !!item.is_hidden,
    is_availability: !!item.is_availability
  })
  isStatusModalOpen.value = true
}
const saveStatus = async () => {
  if (!Number.isInteger(Number(statusForm.booking_status_id)) || Number(statusForm.booking_status_id) < 1) {
    uiStore.showToast('Vui lòng nhập mã tình trạng là số nguyên dương (*)', 'warning')
    return
  }
  if (!statusForm.name) {
    uiStore.showToast('Vui lòng nhập tên tình trạng đăng ký (*)', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      ...statusForm,
      confirmation_days: Number(statusForm.confirmation_days) || 0,
      cut_off_day: Number(statusForm.confirmation_days) || 0
    }
    if (isEditMode.value) {
      await http.put(`/registration-statuses/${statusForm.id}`, payload)
      uiStore.showToast('Cập nhật tình trạng đăng ký thành công!', 'success')
    } else {
      await http.post('/registration-statuses', payload)
      uiStore.showToast('Thêm mới tình trạng đăng ký thành công!', 'success')
    }
    isStatusModalOpen.value = false
    const res = await http.get('/registration-statuses')
    registrationStatuses.value = res.data.data
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu tình trạng đăng ký', 'error')
  } finally {
    loading.value = false
  }
}
const deleteStatus = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa tình trạng đăng ký này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/registration-statuses/${id}`)
    uiStore.showToast('Xóa tình trạng đăng ký thành công!', 'success')
    registrationStatuses.value = registrationStatuses.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa tình trạng đăng ký', 'error')
  }
}
const toggleStatusFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/registration-statuses/${item.id}`, {
      ...item,
      [field]: updatedVal
    })
    item[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

// Filters & Paginations Computed
const filteredPaymentMethods = computed(() => {
  const query = searchQueries.payment.toLowerCase()
  return paymentMethods.value.filter(x => 
    !query || 
    x.code.toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query)
  )
})
const paginatedPaymentMethods = computed(() => {
  const start = (pagination.payment.page - 1) * pagination.payment.limit
  return filteredPaymentMethods.value.slice(start, start + pagination.payment.limit)
})
const totalPaymentPages = computed(() => Math.ceil(filteredPaymentMethods.value.length / pagination.payment.limit) || 1)

const filteredCurrencies = computed(() => {
  const query = searchQueries.currency.toLowerCase()
  return currencies.value.filter(x => 
    !query || 
    x.code.toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query) ||
    x.country.toLowerCase().includes(query)
  )
})
const paginatedCurrencies = computed(() => {
  const start = (pagination.currency.page - 1) * pagination.currency.limit
  return filteredCurrencies.value.slice(start, start + pagination.currency.limit)
})
const totalCurrencyPages = computed(() => Math.ceil(filteredCurrencies.value.length / pagination.currency.limit) || 1)

const filteredUnits = computed(() => {
  const query = searchQueries.unit.toLowerCase()
  return unitsOfMeasure.value.filter(x => 
    !query || 
    x.code.toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query)
  )
})
const paginatedUnits = computed(() => {
  const start = (pagination.unit.page - 1) * pagination.unit.limit
  return filteredUnits.value.slice(start, start + pagination.unit.limit)
})
const totalUnitPages = computed(() => Math.ceil(filteredUnits.value.length / pagination.unit.limit) || 1)

const filteredStatuses = computed(() => {
  const query = searchQueries.status.toLowerCase()
  return registrationStatuses.value.filter(x => 
    !query || 
    String(x.booking_status_id).toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query) ||
    (x.description && x.description.toLowerCase().includes(query))
  )
})
const paginatedStatuses = computed(() => {
  const start = (pagination.status.page - 1) * pagination.status.limit
  return filteredStatuses.value.slice(start, start + pagination.status.limit)
})
const totalStatusPages = computed(() => Math.ceil(filteredStatuses.value.length / pagination.status.limit) || 1)
</script>

<template>
  <div class="flex flex-col gap-2.5 flex-1 min-h-0 overflow-hidden font-roboto">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in systemTabs" :key="tab" @click="activeSystemTab = tab"
          class="px-2.5 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-1.5 transition-colors font-roboto"
          :class="isTabActive(tab) ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-y-auto min-h-0 relative">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>
      
      <!-- Tab 1: HÌNH THỨC THANH TOÁN -->
      <div v-if="isTabActive('Hình Thức Thanh Toán')" class="flex flex-col gap-2.5">
        <div class="flex justify-between items-center mb-0.5 flex-wrap gap-2">
          <div class="flex items-center gap-3">
            <button @click="openAddPayment" class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              Thêm Hình Thức
            </button>
          </div>
          <div class="relative max-w-xs w-full flex items-center">
            <input type="text" v-model="searchQueries.payment" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-8 pr-7 h-8 text-xs font-normal focus:outline-sky-400 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="searchQueries.payment" @click="searchQueries.payment = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
              title="Xóa nhanh">✕</button>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white shadow-2xs">
          <table class="w-full text-xs text-left border-collapse font-roboto">
            <thead>
              <tr class="bg-slate-50 text-slate-700 font-semibold text-xs">
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Mã</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Tên Hình Thức</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Nhóm Thanh Toán</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Tài Khoản</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Tên Tài Khoản</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Tên Ngân Hàng</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Phí Phục Vụ</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Bộ Phận</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">HT Miễn Phí</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200">Không Sử Dụng</th>
                <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 w-20">Hành Động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedPaymentMethods" :key="item.id" @click="openEditPayment(item)"
                class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors">
                <td class="px-2.5 py-1.5 font-semibold text-[#000000D9]">{{ item.code }}</td>
                <td class="px-2.5 py-1.5 font-semibold text-[#000000D9]">{{ item.name }}</td>
                <td class="px-2.5 py-1.5 text-slate-700">
                  <span v-if="item.payment_group === 1" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700 border border-green-200">Tiền mặt</span>
                  <span v-else-if="item.payment_group === 2" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">Thẻ CK</span>
                  <span v-else-if="item.payment_group === 3" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">Voucher</span>
                  <span v-else-if="item.payment_group === 4" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">Công nợ</span>
                  <span v-else-if="item.payment_group === 5" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-50 text-slate-700 border border-slate-200">Miễn phí</span>
                  <span v-else class="text-slate-400 italic">-</span>
                </td>
                <td class="px-2.5 py-1.5 text-slate-700">{{ item.account || '-' }}</td>
                <td class="px-2.5 py-1.5 text-slate-700">{{ item.account_name || '-' }}</td>
                <td class="px-2.5 py-1.5 text-slate-700">{{ item.bank_name || '-' }}</td>
                <td class="px-2.5 py-1.5 text-center font-semibold text-slate-700">{{ item.service_charge }}</td>
                <td class="px-2.5 py-1.5 text-slate-600 text-xs">{{ item.department || '-' }}</td>
                <td class="px-2.5 py-1.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_free" @change="togglePaymentFlag(item, 'is_free')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="px-2.5 py-1.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_inactive" @change="togglePaymentFlag(item, 'is_inactive')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="px-2.5 py-1.5 text-center" @click.stop>
                  <button @click="deletePayment(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa hình thức thanh toán">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredPaymentMethods.length === 0">
                <td colspan="11" class="p-6 text-center text-slate-400 italic">Không tìm thấy hình thức thanh toán nào.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-2 select-none">
          <button @click="pagination.payment.page = Math.max(1, pagination.payment.page - 1)" :disabled="pagination.payment.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalPaymentPages" :key="p" @click="pagination.payment.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
            :class="pagination.payment.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.payment.page = Math.min(totalPaymentPages, pagination.payment.page + 1)" :disabled="pagination.payment.page === totalPaymentPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 2: TIỀN TỆ -->
      <div v-else-if="isTabActive('Tiền Tệ')" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-1 flex-wrap gap-2">
          <button @click="openAddCurrency" class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm Tiền Tệ
          </button>
          <div class="relative max-w-xs w-full flex items-center">
            <input type="text" v-model="searchQueries.currency" placeholder="Tìm kiếm mã, tên, quốc gia..."
              class="w-full border border-slate-200 rounded-lg pl-8 pr-7 h-8 text-xs font-normal focus:outline-sky-400 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="searchQueries.currency" @click="searchQueries.currency = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
              title="Xóa nhanh">✕</button>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white shadow-2xs">
          <table class="w-full text-xs text-left border-collapse font-roboto">
            <thead>
              <tr class="bg-slate-50 text-slate-700 font-semibold text-xs">
                <th class="p-2.5 text-center align-middle border border-slate-200">Mã Tiền Tệ</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tên Tiền Tệ</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Quốc Gia</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tên Viết Tắt</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Làm Tròn</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tiền Tệ Chính</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Trạng Thái Sử Dụng</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tỉ Giá</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Hình Ảnh</th>
                <th class="p-2.5 text-center align-middle border border-slate-200 w-20">Hành Động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedCurrencies" :key="item.id" @click="openEditCurrency(item)"
                class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors">
                <td class="p-2.5 font-semibold text-[#000000D9]">{{ item.code }}</td>
                <td class="p-2.5 font-semibold text-[#000000D9]">{{ item.name }}</td>
                <td class="p-2.5 text-slate-700">{{ item.country }}</td>
                <td class="p-2.5 text-slate-700">{{ item.short_name || '-' }}</td>
                <td class="p-2.5 text-center font-semibold text-slate-700">{{ item.decimals_to_round }}</td>
                <td class="p-2.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_main" @change="toggleCurrencyFlag(item, 'is_main')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="p-2.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_active" @change="toggleCurrencyFlag(item, 'is_active')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="p-2.5 text-right font-semibold text-sky-700">{{ formatExchangeRate(item.exchange_rate) }}</td>
                <td class="p-2.5 text-center">
                  <img v-if="item.image_path" :src="item.image_path" class="w-6 h-4 object-cover inline-block rounded shadow-2xs border border-slate-200" />
                  <span v-else class="text-slate-400 italic text-[11px]">-</span>
                </td>
                <td class="p-2.5 text-center" @click.stop>
                  <button @click="deleteCurrency(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa tiền tệ">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredCurrencies.length === 0">
                <td colspan="10" class="p-6 text-center text-slate-400 italic">Không tìm thấy tiền tệ nào.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-2 select-none">
          <button @click="pagination.currency.page = Math.max(1, pagination.currency.page - 1)" :disabled="pagination.currency.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalCurrencyPages" :key="p" @click="pagination.currency.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
            :class="pagination.currency.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.currency.page = Math.min(totalCurrencyPages, pagination.currency.page + 1)" :disabled="pagination.currency.page === totalCurrencyPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 3: ĐƠN VỊ TÍNH -->
      <div v-else-if="isTabActive('Đơn Vị Tính')" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-1 flex-wrap gap-2">
          <button @click="openAddUnit" class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm Đơn Vị Tính
          </button>
          <div class="relative max-w-xs w-full flex items-center">
            <input type="text" v-model="searchQueries.unit" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-8 pr-7 h-8 text-xs font-normal focus:outline-sky-400 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="searchQueries.unit" @click="searchQueries.unit = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
              title="Xóa nhanh">✕</button>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white shadow-2xs">
          <table class="w-full text-xs text-left border-collapse font-roboto">
            <thead>
              <tr class="bg-slate-50 text-slate-700 font-semibold text-xs">
                <th class="p-2.5 text-center align-middle border border-slate-200">Mã</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tên Đơn Vị Tính</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Ký Hiệu</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Không Sử Dụng</th>
                <th class="p-2.5 text-center align-middle border border-slate-200 w-20">Hành Động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedUnits" :key="item.id" @click="openEditUnit(item)"
                class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors">
                <td class="p-2.5 font-semibold text-[#000000D9]">{{ item.code }}</td>
                <td class="p-2.5 font-semibold text-[#000000D9]">{{ item.name }}</td>
                <td class="p-2.5 text-slate-700">{{ item.symbol || '-' }}</td>
                <td class="p-2.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_inactive" @change="toggleUnitFlag(item, 'is_inactive')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="p-2.5 text-center" @click.stop>
                  <button @click="deleteUnit(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa đơn vị tính">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredUnits.length === 0">
                <td colspan="5" class="p-6 text-center text-slate-400 italic">Không tìm thấy đơn vị tính nào.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-2 select-none">
          <button @click="pagination.unit.page = Math.max(1, pagination.unit.page - 1)" :disabled="pagination.unit.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalUnitPages" :key="p" @click="pagination.unit.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
            :class="pagination.unit.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.unit.page = Math.min(totalUnitPages, pagination.unit.page + 1)" :disabled="pagination.unit.page === totalUnitPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 4: TÌNH TRẠNG ĐĂNG KÝ -->
      <div v-else-if="isTabActive('Tình Trạng Đăng Ký')" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-1 flex-wrap gap-2">
          <button @click="openAddStatus" class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm Tình Trạng
          </button>
          <div class="relative max-w-xs w-full flex items-center">
            <input type="text" v-model="searchQueries.status" placeholder="Tìm kiếm mã, tên, mô tả..."
              class="w-full border border-slate-200 rounded-lg pl-8 pr-7 h-8 text-xs font-normal focus:outline-sky-400 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="searchQueries.status" @click="searchQueries.status = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
              title="Xóa nhanh">✕</button>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-lg bg-white shadow-2xs">
          <table class="w-full text-xs text-left border-collapse font-roboto">
            <thead>
              <tr class="bg-slate-50 text-slate-700 font-semibold text-xs">
                <th class="p-2.5 text-center align-middle border border-slate-200">Mã Tình Trạng</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Tên Trạng Thái</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Màu Sắc</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Bị Ẩn</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Số Ngày Xác Nhận</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Mô Tả</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Phòng Trống Thực Tế</th>
                <th class="p-2.5 text-center align-middle border border-slate-200 w-20">Hành Động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedStatuses" :key="item.id" @click="openEditStatus(item)"
                class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors">
                <td class="p-2.5 font-semibold text-[#000000D9] text-center">{{ item.booking_status_id }}</td>
                <td class="p-2.5 font-semibold text-[#000000D9]">{{ item.name }}</td>
                <td class="p-2.5 text-center">
                  <div class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded-full inline-block border border-slate-300" :style="{ backgroundColor: item.color }"></span>
                    <span class="font-mono text-slate-600 text-xs">{{ item.color }}</span>
                  </div>
                </td>
                <td class="p-2.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_hidden" @change="toggleStatusFlag(item, 'is_hidden')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="p-2.5 text-center font-semibold text-slate-700">{{ item.confirmation_days }}</td>
                <td class="p-2.5 text-slate-600 leading-relaxed max-w-xs">{{ item.description || '-' }}</td>
                <td class="p-2.5 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_availability" @change="toggleStatusFlag(item, 'is_availability')" class="sr-only peer" />
                    <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </td>
                <td class="p-2.5 text-center" @click.stop>
                  <button @click="deleteStatus(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa tình trạng đăng ký">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredStatuses.length === 0">
                <td colspan="8" class="p-6 text-center text-slate-400 italic">Không tìm thấy tình trạng đăng ký nào.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-2 select-none">
          <button @click="pagination.status.page = Math.max(1, pagination.status.page - 1)" :disabled="pagination.status.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalStatusPages" :key="p" @click="pagination.status.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
            :class="pagination.status.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.status.page = Math.min(totalStatusPages, pagination.status.page + 1)" :disabled="pagination.status.page === totalStatusPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 5: CẤU HÌNH HOUSEKEEPING -->
      <div v-else-if="isTabActive('Cấu Hình Housekeeping')" class="flex flex-col gap-4">
        <HkConfigTab />
      </div>

    </div>

    <!-- MODALS SECTION -->

    <!-- Modal 1: HÌNH THỨC THANH TOÁN -->
    <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs select-none">
      <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full overflow-hidden font-roboto"
        :style="{ maxWidth: '520px', transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <div class="px-5 py-3 text-white flex items-center justify-between cursor-move select-none"
          :style="{ background: topbarThemeBg }"
          @mousedown="startDragModal">
          <span class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Cập Nhật Hình Thức Thanh Toán' : 'Thêm Hình Thức Thanh Toán' }}
          </span>
          <button @click="isPaymentModalOpen = false" class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-base font-bold leading-none"
            title="Đóng (Esc)">✕</button>
        </div>
        <div class="p-5 flex flex-col gap-3.5 text-xs text-slate-700 font-normal">
          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">
                Mã <span class="text-red-500">*</span>
              </label>
              <div class="relative flex items-center">
                <input type="text" v-model="paymentForm.code" :disabled="isEditMode" placeholder="Mã..."
                  class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A] disabled:bg-slate-100 disabled:border-slate-200 disabled:cursor-not-allowed" />
                <button v-if="paymentForm.code && !isEditMode" @click="paymentForm.code = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">
                Tên Hình Thức <span class="text-red-500">*</span>
              </label>
              <div class="relative flex items-center">
                <input type="text" v-model="paymentForm.name" placeholder="Tên hình thức..."
                  class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                <button v-if="paymentForm.name" @click="paymentForm.name = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Tài Khoản</label>
              <div class="relative flex items-center">
                <input type="text" v-model="paymentForm.account" placeholder="Số tài khoản..."
                  class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal w-full" />
                <button v-if="paymentForm.account" @click="paymentForm.account = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Tên Tài Khoản</label>
              <div class="relative flex items-center">
                <input type="text" v-model="paymentForm.account_name" placeholder="Chủ tài khoản..."
                  class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal w-full" />
                <button v-if="paymentForm.account_name" @click="paymentForm.account_name = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Tên Ngân Hàng</label>
              <div class="relative flex items-center">
                <input type="text" v-model="paymentForm.bank_name" placeholder="Tên ngân hàng..."
                  class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal w-full" />
                <button v-if="paymentForm.bank_name" @click="paymentForm.bank_name = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Phí Phục Vụ (%)</label>
              <input type="number" v-model="paymentForm.service_charge" min="0"
                class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Bộ Phận</label>
              <select v-model="paymentForm.department"
                class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal bg-white w-full">
                <option value="">-- Chọn bộ phận --</option>
                <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Nhóm Thanh Toán</label>
              <select v-model="paymentForm.payment_group"
                class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal bg-white w-full">
                <option :value="null">-- Chọn nhóm --</option>
                <option :value="1">Tiền mặt</option>
                <option :value="2">Thẻ / Chuyển khoản</option>
                <option :value="3">Voucher</option>
                <option :value="4">Công nợ (City ledger)</option>
                <option :value="5">Miễn phí</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-6 mt-1">
            <div class="flex items-center gap-2">
              <label class="font-semibold text-xs text-[#000000D9]">HT Miễn Phí</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_free" class="sr-only peer" />
                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <label class="font-semibold text-xs text-[#000000D9]">Không Sử Dụng</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_inactive" class="sr-only peer" />
                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 mt-2 pt-3 border-t border-slate-100">
            <button @click="savePayment" class="btn-pms-primary h-8 px-4 text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 2: TIỀN TỆ -->
    <div v-if="isCurrencyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs select-none">
      <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full overflow-hidden font-roboto"
        :style="{ maxWidth: '580px', transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <div class="px-5 py-3 text-white flex items-center justify-between cursor-move select-none"
          :style="{ background: topbarThemeBg }"
          @mousedown="startDragModal">
          <span class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Loại Tiền Tệ' : 'Thêm Loại Tiền Tệ' }}
          </span>
          <button @click="isCurrencyModalOpen = false" class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-base font-bold leading-none"
            title="Đóng (Esc)">✕</button>
        </div>
        <div class="p-5 flex flex-col gap-3.5 text-xs text-slate-700 font-normal">
          <div class="grid grid-cols-2 gap-4 items-start">
            <!-- Left Fields -->
            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">
                  Mã Tiền Tệ <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                  <input type="text" v-model="currencyForm.code" :disabled="isEditMode" placeholder="VND, USD..."
                    class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A] disabled:bg-slate-100 disabled:border-slate-200 disabled:cursor-not-allowed" />
                  <button v-if="currencyForm.code && !isEditMode" @click="currencyForm.code = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">
                  Tên Loại Tiền Tệ <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                  <input type="text" v-model="currencyForm.name" placeholder="Việt Nam Đồng..."
                    class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                  <button v-if="currencyForm.name" @click="currencyForm.name = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">
                  Quốc Gia <span class="text-red-500">*</span>
                </label>
                <div class="relative flex items-center">
                  <input type="text" v-model="currencyForm.country" placeholder="Việt Nam..."
                    class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                  <button v-if="currencyForm.country" @click="currencyForm.country = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Tên Viết Tắt</label>
                <div class="relative flex items-center">
                  <input type="text" v-model="currencyForm.short_name" placeholder="đ, $..."
                    class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal w-full" />
                  <button v-if="currencyForm.short_name" @click="currencyForm.short_name = ''"
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                    title="Xóa nhanh">✕</button>
                </div>
              </div>
            </div>

            <!-- Right Fields -->
            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Làm Tròn Số Thập Phân</label>
                <input type="number" v-model="currencyForm.decimals_to_round" min="0" max="6"
                  class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal w-full" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Tỉ Giá</label>
                <input type="number" v-model="currencyForm.exchange_rate" step="0.0001" min="0"
                  class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-semibold w-full" />
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-1.5">
                <span class="font-semibold text-xs text-[#000000D9]">Tiền Tệ Chính</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_main" class="sr-only peer" />
                  <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-1.5">
                <span class="font-semibold text-xs text-[#000000D9]">Trạng Thái Sử Dụng</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_active" class="sr-only peer" />
                  <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
              </div>

              <!-- Flag Upload Box -->
              <div class="flex flex-col gap-1 mt-1">
                <label class="font-semibold text-xs text-[#000000D9]">Hình Ảnh / Cờ</label>
                <div class="border border-dashed border-slate-200 rounded-lg p-2.5 flex flex-col items-center justify-center gap-1.5 bg-slate-50 relative min-h-[85px]">
                  <input type="file" ref="currencyImageInput" class="hidden" accept="image/*" @change="handleCurrencyImageUpload" />
                  
                  <div v-if="currencyForm.imagePreview" class="flex flex-col items-center gap-1.5">
                    <img :src="currencyForm.imagePreview" class="w-16 h-10 object-cover rounded shadow-xs border border-slate-200" />
                    <div class="flex items-center gap-2">
                      <button @click="triggerCurrencyImageUpload" class="text-sky-600 hover:text-sky-700 bg-transparent border-none cursor-pointer text-xs font-semibold">Thay đổi</button>
                      <button @click="removeCurrencyImage" class="text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer text-xs font-semibold">Xóa</button>
                    </div>
                  </div>
                  
                  <div v-else @click="triggerCurrencyImageUpload" class="flex flex-col items-center gap-1 cursor-pointer w-full py-2 text-slate-400 hover:text-slate-600">
                    <span class="text-xl leading-none">+</span>
                    <span class="text-[11px] font-semibold">Chọn hình ảnh</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 mt-2 pt-3 border-t border-slate-100">
            <button @click="saveCurrency" class="btn-pms-primary h-8 px-4 text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 3: ĐƠN VỊ TÍNH -->
    <div v-if="isUnitModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs select-none">
      <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full overflow-hidden font-roboto"
        :style="{ maxWidth: '420px', transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <div class="px-5 py-3 text-white flex items-center justify-between cursor-move select-none"
          :style="{ background: topbarThemeBg }"
          @mousedown="startDragModal">
          <span class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Đơn Vị Tính' : 'Thêm Đơn Vị Tính' }}
          </span>
          <button @click="isUnitModalOpen = false" class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-base font-bold leading-none"
            title="Đóng (Esc)">✕</button>
        </div>
        <div class="p-5 flex flex-col gap-3.5 text-xs text-slate-700 font-normal">
          <div class="flex flex-col gap-1">
            <label class="font-semibold text-xs text-[#000000D9]">
              Mã <span class="text-red-500">*</span>
            </label>
            <div class="relative flex items-center">
              <input type="text" v-model="unitForm.code" :disabled="isEditMode" placeholder="KG, CAI, HOP..."
                class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A] disabled:bg-slate-100 disabled:border-slate-200 disabled:cursor-not-allowed" />
              <button v-if="unitForm.code && !isEditMode" @click="unitForm.code = ''"
                class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                title="Xóa nhanh">✕</button>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label class="font-semibold text-xs text-[#000000D9]">
              Tên Đơn Vị Tính <span class="text-red-500">*</span>
            </label>
            <div class="relative flex items-center">
              <input type="text" v-model="unitForm.name" placeholder="Kilogram, Cái, Hộp..."
                class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
              <button v-if="unitForm.name" @click="unitForm.name = ''"
                class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                title="Xóa nhanh">✕</button>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label class="font-semibold text-xs text-[#000000D9]">Ký Hiệu (Symbol)</label>
            <div class="relative flex items-center">
              <input type="text" v-model="unitForm.symbol" placeholder="kg, pcs..."
                class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal w-full" />
              <button v-if="unitForm.symbol" @click="unitForm.symbol = ''"
                class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                title="Xóa nhanh">✕</button>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 mt-2 pt-3 border-t border-slate-100">
            <button @click="saveUnit" class="btn-pms-primary h-8 px-4 text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 4: TÌNH TRẠNG ĐĂNG KÝ -->
    <div v-if="isStatusModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs select-none">
      <div class="bg-white rounded-xl shadow-2xl border border-slate-200 w-full overflow-hidden font-roboto"
        :style="{ maxWidth: '520px', transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <div class="px-5 py-3 text-white flex items-center justify-between cursor-move select-none"
          :style="{ background: topbarThemeBg }"
          @mousedown="startDragModal">
          <span class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Tình Trạng Đăng Ký' : 'Thêm Tình Trạng Đăng Ký' }}
          </span>
          <button @click="isStatusModalOpen = false" class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-base font-bold leading-none"
            title="Đóng (Esc)">✕</button>
        </div>
        <div class="p-5 flex flex-col gap-3.5 text-xs text-slate-700 font-normal">
          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">
                Mã Tình Trạng <span class="text-red-500">*</span>
              </label>
              <input type="number" v-model.number="statusForm.booking_status_id" min="1" step="1" placeholder="1, 2, 3..."
                class="rounded-lg p-2 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">
                Tên Trạng Thái <span class="text-red-500">*</span>
              </label>
              <div class="relative flex items-center">
                <input type="text" v-model="statusForm.name" placeholder="Tên trạng thái..."
                  class="rounded-lg p-2 pr-7 font-normal focus:outline-none focus:border-amber-400 text-xs w-full bg-[#FFF8DB] border border-[#F1DD8A]" />
                <button v-if="statusForm.name" @click="statusForm.name = ''"
                  class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  title="Xóa nhanh">✕</button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3.5">
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Màu Sắc</label>
              <div class="flex items-center gap-2 border border-slate-200 rounded-lg px-2 py-1">
                <input type="color" v-model="statusForm.color" class="w-6 h-6 rounded border-none cursor-pointer p-0 bg-transparent" />
                <input type="text" v-model="statusForm.color" class="flex-1 text-xs font-mono border-none focus:outline-none" />
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-semibold text-xs text-[#000000D9]">Số Ngày Xác Nhận</label>
              <input type="number" v-model="statusForm.confirmation_days" min="0"
                class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal" />
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-semibold text-xs text-[#000000D9]">Nhóm Giá Trị (Status Value)</label>
            <select v-model="statusForm.status_value"
              class="border border-slate-200 rounded-lg p-2 text-xs focus:outline-sky-400 font-normal bg-white">
              <option v-for="val in statusValues" :key="val" :value="val">{{ val }}</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-semibold text-xs text-[#000000D9]">Mô Tả Chi Tiết</label>
            <div class="relative flex items-center">
              <textarea v-model="statusForm.description" rows="2" placeholder="Mô tả chi tiết..."
                class="border border-slate-200 rounded-lg p-2 pr-7 text-xs focus:outline-sky-400 font-normal resize-none w-full"></textarea>
              <button v-if="statusForm.description" @click="statusForm.description = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                title="Xóa nhanh">✕</button>
            </div>
          </div>

          <div class="flex items-center gap-6 mt-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-xs text-[#000000D9]">Bị Ẩn</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="statusForm.is_hidden" class="sr-only peer" />
                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-xs text-[#000000D9]">Phòng Trống Thực Tế</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="statusForm.is_availability" class="sr-only peer" />
                <div class="w-7 h-4 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 mt-2 pt-3 border-t border-slate-100">
            <button @click="saveStatus" class="btn-pms-primary h-8 px-4 text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.15s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
</style>
