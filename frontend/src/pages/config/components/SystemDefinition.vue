<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'HÌNH THỨC THANH TOÁN'
  }
})

const emit = defineEmits(['update:activeTab'])

const uiStore = useUiStore()
const loading = ref(false)
const activeSystemTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeSystemTab.value = newVal
  }
})

watch(activeSystemTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const systemTabs = [
  'HÌNH THỨC THANH TOÁN',
  'TIỀN TỆ',
  'ĐƠN VỊ TÍNH',
  'TÌNH TRẠNG ĐĂNG KÝ',
  'Mẫu'
]

// Dropdown lists
const departments = ['Reception/ Lê Tân', 'Restaurant/Nhà Hàng', 'House Keeping/Buồng Phòng', 'Spa']

// Data lists
const paymentMethods = ref([])
const currencies = ref([])
const unitsOfMeasure = ref([])
const registrationStatuses = ref([])
const templates = ref([])

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

// Active Templates State
const activeTemplateGroup = ref('Booking Confirmation')
const templateGroups = [
  'Booking Confirmation',
  'Registration Card',
  'Deposit',
  'Room Morning Worksheet',
  'Invoice',
  'Total revenue report',
  'Breakfast Ticket',
  'Report'
]

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
    } else if (tab === 'Mẫu') {
      const res = await http.get('/templates')
      templates.value = res.data.data || []
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

// Format Currency
const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

// Format Exchange Rate
const formatExchangeRate = (val) => {
  return new Intl.NumberFormat('vi-VN').format(val)
}

// Modal CRUD Actions

// 1. Payment Method Modal Actions
const openAddPayment = () => {
  isEditMode.value = false
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
    uiStore.showToast('Vui lòng nhập mã và tên hình thức thanh toán', 'warning')
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
    uiStore.showToast('Lỗi khi xóa hình thức thanh toán', 'error')
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
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin tiền tệ', 'warning')
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
    uiStore.showToast('Vui lòng nhập mã và tên đơn vị tính', 'warning')
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
    uiStore.showToast('Lỗi khi xóa đơn vị tính', 'error')
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
  Object.assign(statusForm, {
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
  Object.assign(statusForm, {
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
  if (!statusForm.name) {
    uiStore.showToast('Vui lòng nhập tên tình trạng đăng ký', 'warning')
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
    uiStore.showToast('Lỗi khi xóa tình trạng đăng ký', 'error')
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

// 6. Template Update Actions
const updateTemplateReport = async (template) => {
  try {
    await http.put(`/templates/${template.id}`, {
      group: template.group,
      name: template.name,
      report: template.report
    })
    uiStore.showToast(`Cập nhật báo cáo thành công!`, 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật báo cáo cho mẫu', 'error')
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
  <div class="flex flex-col gap-4 h-full overflow-hidden">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in systemTabs" :key="tab" @click="activeSystemTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeSystemTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 p-6 overflow-y-auto min-h-[400px] relative">
      
      <!-- Loading State (Premium 3D Rotating Rings Loader) -->
      <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>
      
      <!-- Tab 1: HÌNH THỨC THANH TOÁN -->
      <div v-if="activeSystemTab === 'HÌNH THỨC THANH TOÁN'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <div class="flex items-center gap-4">
            <button @click="openAddPayment"
              class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              Thêm
            </button>
            <div class="flex items-center gap-1">
              <input type="checkbox" id="allow_sort_pay" class="cursor-pointer" />
              <label for="allow_sort_pay" class="text-xs text-slate-500 select-none cursor-pointer">Don't allow sort</label>
            </div>
          </div>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchQueries.payment" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Code</th>
                <th class="p-3">Tên</th>
                <th class="p-3">Nhóm thanh toán</th>
                <th class="p-3">Tài khoản</th>
                <th class="p-3">Tên tài khoản</th>
                <th class="p-3">Tên ngân hàng</th>
                <th class="p-3 text-center">Phí phục vụ</th>
                <th class="p-3">Bộ phận</th>
                <th class="p-3 text-center">HT Miễn Phí</th>
                <th class="p-3 text-center">Không sử dụng</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedPaymentMethods" :key="item.id" @click="openEditPayment(item)"
                class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ item.code }}</td>
                <td class="p-3 font-bold text-slate-700">{{ item.name }}</td>
                <td class="p-3 text-slate-600 font-semibold">
                  <span v-if="item.payment_group === 1" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">Tiền mặt</span>
                  <span v-else-if="item.payment_group === 2" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Thẻ CK</span>
                  <span v-else-if="item.payment_group === 3" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Voucher</span>
                  <span v-else-if="item.payment_group === 4" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">Công nợ</span>
                  <span v-else-if="item.payment_group === 5" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-50 text-slate-700 border border-slate-200">Miễn phí</span>
                  <span v-else class="text-slate-400 italic">-</span>
                </td>
                <td class="p-3 text-slate-600 font-semibold">{{ item.account || '-' }}</td>
                <td class="p-3 text-slate-600 font-semibold">{{ item.account_name || '-' }}</td>
                <td class="p-3 text-slate-600 font-semibold">{{ item.bank_name || '-' }}</td>
                <td class="p-3 text-center font-bold text-slate-700">{{ item.service_charge }}</td>
                <td class="p-3 text-slate-500 font-semibold text-xs">{{ item.department || '-' }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_free" @change="togglePaymentFlag(item, 'is_free')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_inactive" @change="togglePaymentFlag(item, 'is_inactive')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-right">
                  <button @click.stop="deletePayment(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button @click="pagination.payment.page = Math.max(1, pagination.payment.page - 1)" :disabled="pagination.payment.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalPaymentPages" :key="p" @click="pagination.payment.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="pagination.payment.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.payment.page = Math.min(totalPaymentPages, pagination.payment.page + 1)" :disabled="pagination.payment.page === totalPaymentPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 2: TIỀN TỆ -->
      <div v-else-if="activeSystemTab === 'TIỀN TỆ'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <button @click="openAddCurrency"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm
          </button>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchQueries.currency" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Mã tiền tệ</th>
                <th class="p-3">Hình ảnh</th>
                <th class="p-3">Tên loại tiền tệ</th>
                <th class="p-3">Quốc gia</th>
                <th class="p-3 text-center">Tiền tệ chính</th>
                <th class="p-3 text-center">Trạng thái sử dụng</th>
                <th class="p-3 text-center">Làm tròn chữ số thập phân</th>
                <th class="p-3 text-right">Tỉ giá</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedCurrencies" :key="item.id" @click="openEditCurrency(item)"
                class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ item.code }}</td>
                <td class="p-3">
                  <img v-if="item.image_path" :src="item.image_path" class="w-8 h-5 object-cover rounded shadow-xs" alt="flag" />
                  <span v-else class="text-slate-400 italic text-xs">Không có</span>
                </td>
                <td class="p-3 font-bold text-slate-700">{{ item.name }}</td>
                <td class="p-3 text-slate-600 font-semibold">{{ item.country }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_main" @change="toggleCurrencyFlag(item, 'is_main')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_active" @change="toggleCurrencyFlag(item, 'is_active')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-center font-bold text-slate-700">{{ item.decimals_to_round }}</td>
                <td class="p-3 text-right font-bold text-sky-700">{{ formatExchangeRate(item.exchange_rate) }}</td>
                <td class="p-3 text-right">
                  <button @click.stop="deleteCurrency(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button @click="pagination.currency.page = Math.max(1, pagination.currency.page - 1)" :disabled="pagination.currency.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalCurrencyPages" :key="p" @click="pagination.currency.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="pagination.currency.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.currency.page = Math.min(totalCurrencyPages, pagination.currency.page + 1)" :disabled="pagination.currency.page === totalCurrencyPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 3: ĐƠN VỊ TÍNH -->
      <div v-else-if="activeSystemTab === 'ĐƠN VỊ TÍNH'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <button @click="openAddUnit"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm
          </button>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchQueries.unit" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Mã</th>
                <th class="p-3">Tên đơn vị tính</th>
                <th class="p-3">Symbol</th>
                <th class="p-3 text-center">Không sử dụng</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedUnits" :key="item.id" @click="openEditUnit(item)"
                class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ item.code }}</td>
                <td class="p-3 font-bold text-slate-700">{{ item.name }}</td>
                <td class="p-3 font-semibold text-slate-600">{{ item.symbol || '-' }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_inactive" @change="toggleUnitFlag(item, 'is_inactive')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-right">
                  <button @click.stop="deleteUnit(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button @click="pagination.unit.page = Math.max(1, pagination.unit.page - 1)" :disabled="pagination.unit.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalUnitPages" :key="p" @click="pagination.unit.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="pagination.unit.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.unit.page = Math.min(totalUnitPages, pagination.unit.page + 1)" :disabled="pagination.unit.page === totalUnitPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 4: TÌNH TRẠNG ĐĂNG KÝ -->
      <div v-if="activeSystemTab === 'TÌNH TRẠNG ĐĂNG KÝ'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <button @click="openAddStatus"
              class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              Thêm
            </button>
            <div class="flex items-center gap-1">
              <input type="checkbox" id="allow_sort_status" class="cursor-pointer" />
              <label for="allow_sort_status" class="text-xs text-slate-500 select-none cursor-pointer">Don't allow sort</label>
            </div>
            <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 hover:text-slate-800 font-bold text-xs rounded-lg cursor-pointer">
              Tự động sắp xếp
            </button>
          </div>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchQueries.status" placeholder="Tìm kiếm tên, mô tả..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Tên</th>
                <th class="p-3">Màu sắc</th>
                <th class="p-3 text-center">Bị ẩn</th>
                <th class="p-3 text-center">Ngày xác nhận</th>
                <th class="p-3">Mô tả</th>
                <th class="p-3 text-center">Is Availability</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedStatuses" :key="item.id" @click="openEditStatus(item)"
                class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ item.name }}</td>
                <td class="p-3">
                  <div class="flex items-center gap-2">
                    <span class="w-6 h-4 border border-slate-200 block rounded" :style="{ backgroundColor: item.color }"></span>
                    <span class="font-mono text-slate-400 text-xs">{{ item.color }}</span>
                  </div>
                </td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_hidden" @change="toggleStatusFlag(item, 'is_hidden')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-center font-bold text-slate-700">{{ item.confirmation_days }}</td>
                <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed max-w-xs">{{ item.description || '-' }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="item.is_availability" @change="toggleStatusFlag(item, 'is_availability')" class="sr-only peer" />
                    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                  </label>
                </td>
                <td class="p-3 text-right">
                  <button @click.stop="deleteStatus(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button @click="pagination.status.page = Math.max(1, pagination.status.page - 1)" :disabled="pagination.status.page === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
          <button v-for="p in totalStatusPages" :key="p" @click="pagination.status.page = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="pagination.status.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
          <button @click="pagination.status.page = Math.min(totalStatusPages, pagination.status.page + 1)" :disabled="pagination.status.page === totalStatusPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
        </div>
      </div>

      <!-- Tab 6: MẪU -->
      <div v-else-if="activeSystemTab === 'Mẫu'" class="flex gap-6 items-stretch">
        <!-- Left panel: Group list -->
        <div class="w-1/4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex flex-col gap-1.5">
          <span class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Nhóm Mẫu</span>
          <button v-for="grp in templateGroups" :key="grp" @click="activeTemplateGroup = grp"
            class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
            :class="activeTemplateGroup === grp ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
            {{ grp }}
          </button>
        </div>

        <!-- Right panel: Template report list -->
        <div class="flex-1 flex flex-col gap-4">
          <span class="text-xs font-black text-slate-700 uppercase tracking-wider pb-2 border-b border-slate-100">
            Mẫu báo cáo tương thích cho nhóm: {{ activeTemplateGroup }}
          </span>

          <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
            <table class="w-full text-sm text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                  <th class="p-3">Tên mẫu cấu hình</th>
                  <th class="p-3">Tên báo cáo liên kết (Report template name)</th>
                  <th class="p-3 text-right">Lưu cấu hình</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in templates.filter(item => item.group === activeTemplateGroup)" :key="t.id"
                  class="border-b border-slate-100 hover:bg-slate-50/55">
                  <td class="p-3 font-bold text-slate-800">{{ t.name }}</td>
                  <td class="p-2">
                    <input type="text" v-model="t.report" placeholder="Nhập tên report..."
                      class="border border-slate-200 rounded-lg px-3 py-1.5 text-xs w-full max-w-sm focus:outline-sky-500 font-mono font-semibold" />
                  </td>
                  <td class="p-3 text-right">
                    <button @click="updateTemplateReport(t)"
                      class="px-3 py-1.5 bg-sky-100 hover:bg-sky-200 border border-sky-300 hover:border-sky-400 text-sky-600 hover:text-sky-700 font-extrabold rounded-lg text-[11px] shadow-2xs cursor-pointer transition-colors uppercase">
                      Cập nhật
                    </button>
                  </td>
                </tr>
                <tr v-if="templates.filter(item => item.group === activeTemplateGroup).length === 0">
                  <td colspan="3" class="p-6 text-center text-slate-400 italic">Chưa có mẫu nào thuộc nhóm này.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- MODALS SECTION -->

    <!-- Modal 1: HÌNH THỨC THANH TOÁN -->
    <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 520px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>HÌNH THỨC THANH TOÁN</span>
          <button @click="isPaymentModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Code</label>
              <input type="text" v-model="paymentForm.code" :disabled="isEditMode"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên</label>
              <input type="text" v-model="paymentForm.name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tài khoản</label>
              <input type="text" v-model="paymentForm.account"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên tài khoản</label>
              <input type="text" v-model="paymentForm.account_name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên ngân hàng</label>
              <input type="text" v-model="paymentForm.bank_name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Phí phục vụ</label>
              <input type="number" v-model="paymentForm.service_charge"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Department</label>
              <select v-model="paymentForm.department"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold bg-white w-full">
                <option value="">Chọn: 0</option>
                <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Nhóm thanh toán</label>
              <select v-model="paymentForm.payment_group"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold bg-white w-full">
                <option :value="null">Chọn nhóm</option>
                <option :value="1">Tiền mặt</option>
                <option :value="2">Thẻ / Chuyển khoản</option>
                <option :value="3">Voucher</option>
                <option :value="4">Công nợ (City ledger)</option>
                <option :value="5">Miễn phí</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-6 mt-2">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-slate-500">HT Miễn Phí</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_free" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-slate-500">Không sử dụng</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_inactive" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-slate-100">
            <button @click="isPaymentModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="savePayment" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 2: TIỀN TỆ -->
    <div v-if="isCurrencyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 600px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>{{ isEditMode ? 'Chỉnh sửa loại tiền tệ' : 'Thêm loại tiền tệ' }}</span>
          <button @click="isCurrencyModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-6 items-start">
            <!-- Left Fields -->
            <div class="flex flex-col gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Mã tiền tệ</label>
                <input type="text" v-model="currencyForm.code" :disabled="isEditMode" placeholder="Mã tiền tệ"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tên loại tiền tệ</label>
                <input type="text" v-model="currencyForm.name" placeholder="Tên loại tiền tệ"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Quốc gia</label>
                <input type="text" v-model="currencyForm.country" placeholder="Quốc gia"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tên viết tắt</label>
                <input type="text" v-model="currencyForm.short_name" placeholder="Tên viết tắt"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Làm tròn chữ số thập phân</label>
                <input type="number" v-model="currencyForm.decimals_to_round"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
              </div>

              <!-- Flag Upload Box -->
              <div class="flex flex-col gap-2 mt-2">
                <label class="text-xs font-bold text-slate-500 block">Hình ảnh</label>
                <div class="border border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center gap-2 bg-slate-50 relative min-h-[140px]">
                  <input type="file" ref="currencyImageInput" class="hidden" accept="image/*" @change="handleCurrencyImageUpload" />
                  
                  <div v-if="currencyForm.imagePreview" class="flex flex-col items-center gap-2">
                    <img :src="currencyForm.imagePreview" class="w-24 h-15 object-cover rounded shadow-md border border-slate-100" />
                    <div class="flex items-center gap-3 mt-1">
                      <button @click="triggerCurrencyImageUpload" class="text-sky-500 hover:text-sky-700 bg-transparent border-none cursor-pointer text-xs font-extrabold">&#x1F441; Thay đổi</button>
                      <button @click="removeCurrencyImage" class="text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer text-xs font-extrabold">&#x1F5D1; Xóa</button>
                    </div>
                  </div>
                  
                  <div v-else @click="triggerCurrencyImageUpload" class="flex flex-col items-center gap-1 cursor-pointer w-full h-full py-4 text-slate-400 hover:text-slate-600">
                    <span class="text-3xl font-light">+</span>
                    <span class="text-xs font-bold">Chọn tệp</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Fields -->
            <div class="flex flex-col gap-4">
              <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-xs font-bold text-slate-500">Tiền tệ chính</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_main" class="sr-only peer" />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>

              <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-xs font-bold text-slate-500">Trạng thái sử dụng</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_active" class="sr-only peer" />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tỉ giá</label>
                <input type="number" v-model="currencyForm.exchange_rate" step="0.0001"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button @click="isCurrencyModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm flex items-center gap-1.5 border-none cursor-pointer transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="saveCurrency" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 3: ĐƠN VỊ TÍNH -->
    <div v-if="isUnitModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 450px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>Thêm đơn vị tính</span>
          <button @click="isUnitModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-500">Mã</label>
            <input type="text" v-model="unitForm.code" :disabled="isEditMode"
              class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-500">Tên đơn vị tính</label>
            <input type="text" v-model="unitForm.name"
              class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-500">Symbol</label>
            <input type="text" v-model="unitForm.symbol"
              class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
          </div>

          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-slate-100">
            <button @click="isUnitModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="saveUnit" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 4: TÌNH TRẠNG ĐĂNG KÝ -->
    <div v-if="isStatusModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 550px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>TÌNH TRẠNG ĐĂNG KÝ</span>
          <button @click="isStatusModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên</label>
              <input type="text" v-model="statusForm.name" placeholder="Tên trạng thái"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Màu sắc</label>
              <div class="flex items-center gap-2 border border-slate-200 rounded-lg px-2.5 py-1">
                <input type="color" v-model="statusForm.color" class="w-8 h-8 rounded border-none cursor-pointer" />
                <input type="text" v-model="statusForm.color" class="flex-1 text-sm font-mono border-none focus:outline-none" />
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Ngày xác nhận</label>
              <input type="number" v-model="statusForm.confirmation_days"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Mô tả</label>
              <select v-model="statusForm.status_value"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold bg-white">
                <option value="Select Value">Select Value</option>
                <option v-for="val in statusValues" :key="val" :value="val">{{ val }}</option>
              </select>
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-500">Mô tả chi tiết</label>
            <textarea v-model="statusForm.description" rows="3" placeholder="Mô tả chi tiết..."
              class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold resize-none"></textarea>
          </div>

          <div class="flex items-center gap-6 mt-2">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-500">Bị ẩn</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="statusForm.is_hidden" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-500">Phòng trống thực tế</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="statusForm.is_availability" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-slate-100">
            <button @click="isStatusModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="saveStatus" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
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
