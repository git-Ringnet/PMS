<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'THÔNG TIN KHÁCH SẠN'
  }
})

const emit = defineEmits(['update:activeTab'])

const uiStore = useUiStore()
const loading = ref(false)

const activeHotelTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeHotelTab.value = newVal
  }
})

watch(activeHotelTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const hotelTabs = [
  'THÔNG TIN KHÁCH SẠN',
  'DỊCH VỤ KHÁCH SẠN',
  'CA LÀM VIỆC',
  'CẤU HÌNH',
  'CHI NHÁNH',
  'MẪU',
  'BỘ PHẬN DỊCH VỤ',
  'THIẾT LẬP BÁO CÁO TỔNG HỢP'
]

// Data States
const hotelForm = reactive({
  first_name: '',
  hotel_name: '',
  hotel_name1: '',
  address: '',
  address1: '',
  phone: '',
  fax: '',
  email: '',
  website: '',
  account: '',
  bank_code: '',
  bank: '',
  tax_code: '',
  account_name: '',
  invoice_address: '',
  breakfast_adult_rate: 0,
  breakfast_child_rate: 0,
  extra_bed_rate: 0,
  room_number: 0,
  division: '',
  currency: 'VND',
  prefix_booking_id: '',
  channel_manager: '',
  facebook: '',
  hotel_link: '',
  serial: '',
  invoice_number: '',
  invoice_number_length: null,
  form_no: '',
  logo: '',
  pos_serial: '',
  pos_invoice_number: '',
  pos_invoice_number_length: null,
  pos_invoice_form_no: '',
  pos_invoice_symbol: '',
  logo_url: '',
  qr_code_url: ''
})

const hotelServices = ref([])
const shifts = ref([])
const hotelConfigs = ref([])
const branches = ref([])
const templates = ref([])

// Search state
const searchServiceQuery = ref('')
const searchConfigQuery = ref('')
const searchBranchQuery = ref('')

// Template Group
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

// Modal states for CRUD
const isEditMode = ref(false)
const isServiceModalOpen = ref(false)
const serviceFormState = reactive({
  id: null,
  code: '',
  name: '',
  service_charge: 5,
  tax: 8,
  special_tax: 0,
  include_service_charge: true,
  include_tax: true,
  include_special_tax: true,
  folio: 1,
  short_name: '',
  unit: '',
  price: 0,
  department: 'Reception/ Lê Tân'
})

const isShiftModalOpen = ref(false)
const shiftFormState = reactive({
  id: null,
  name: '',
  start_time: '',
  end_time: ''
})

const isConfigModalOpen = ref(false)
const configFormState = reactive({
  id: null,
  name: '',
  value: '',
  description: ''
})

const isBranchModalOpen = ref(false)
const branchFormState = reactive({
  id: null,
  code: '',
  name: '',
  api_url: '',
  api_report_url: '',
  is_master: false
})

// Pagination states
const currentPageConfig = ref(1)
const pageSizeConfig = ref(10)

const currentPageShift = ref(1)
const pageSizeShift = ref(10)

const currentPageBranch = ref(1)
const pageSizeBranch = ref(10)

// --- State for BỘ PHẬN DỊCH VỤ ---
const departments = ref([
  'Restaurant/Nhà Hàng',
  'Reception/ Lê Tân',
  'House Keeping/Buồng Phòng',
  'Spa'
])
const activeDepartment = ref('Restaurant/Nhà Hàng')
const departmentServices = ref({
  'Restaurant/Nhà Hàng': [
    { id: 1, name: 'Buffet sáng người lớn', description: 'Buffet sáng tiêu chuẩn cho người lớn' },
    { id: 2, name: 'Buffet sáng trẻ em', description: 'Buffet sáng tiêu chuẩn cho trẻ em' },
    { id: 3, name: 'Nước ngọt lon', description: 'Coca, Pepsi, Fanta các loại' },
    { id: 4, name: 'Bia Heineken', description: 'Bia lon Heineken' }
  ],
  'Reception/ Lê Tân': [
    { id: 5, name: 'Đưa đón sân bay', description: 'Xe đưa đón sân bay Cam Ranh 4-7 chỗ' },
    { id: 6, name: 'Giặt ủi nhanh', description: 'Giặt ủi lấy liền trong 4 tiếng' },
    { id: 7, name: 'Thuê xe máy', description: 'Cho thuê xe máy tay ga/xe số theo ngày' }
  ],
  'House Keeping/Buồng Phòng': [
    { id: 8, name: 'Dọn phòng thêm giờ', description: 'Yêu cầu dọn dẹp phòng ngoài giờ định kỳ' },
    { id: 9, name: 'Thêm gối phụ', description: 'Yêu cầu thêm gối nằm phụ' },
    { id: 10, name: 'Thêm chăn/mền phụ', description: 'Yêu cầu thêm chăn mền phụ' }
  ],
  'Spa': [
    { id: 11, name: 'Massage body đá nóng', description: 'Massage toàn thân bằng đá nóng 60 phút' },
    { id: 12, name: 'Xông hơi tinh dầu', description: 'Xông hơi ướt/khô kết hợp tinh dầu sả chanh' }
  ]
})

const isDeptServiceModalOpen = ref(false)
const isEditDeptServiceMode = ref(false)
const deptServiceFormState = reactive({
  id: null,
  name: '',
  description: ''
})
const searchDeptServiceQuery = ref('')

const openAddDeptServiceModal = () => {
  isEditDeptServiceMode.value = false
  Object.assign(deptServiceFormState, {
    id: null,
    name: '',
    description: ''
  })
  isDeptServiceModalOpen.value = true
}

const openEditDeptServiceModal = (service) => {
  isEditDeptServiceMode.value = true
  Object.assign(deptServiceFormState, {
    id: service.id,
    name: service.name,
    description: service.description
  })
  isDeptServiceModalOpen.value = true
}

const saveDeptService = () => {
  if (!deptServiceFormState.name) {
    uiStore.showToast('Vui lòng nhập tên dịch vụ', 'warning')
    return
  }
  const activeList = departmentServices.value[activeDepartment.value] || []
  if (isEditDeptServiceMode.value) {
    const idx = activeList.findIndex(s => s.id === deptServiceFormState.id)
    if (idx !== -1) {
      activeList[idx] = { ...activeList[idx], name: deptServiceFormState.name, description: deptServiceFormState.description }
    }
    uiStore.showToast('Cập nhật dịch vụ bộ phận thành công!', 'success')
  } else {
    const newId = Date.now()
    activeList.push({
      id: newId,
      name: deptServiceFormState.name,
      description: deptServiceFormState.description
    })
    uiStore.showToast('Thêm dịch vụ vào bộ phận thành công!', 'success')
  }
  isDeptServiceModalOpen.value = false
}

const deleteDeptService = async (serviceId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dịch vụ này khỏi bộ phận?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const activeList = departmentServices.value[activeDepartment.value] || []
  const idx = activeList.findIndex(s => s.id === serviceId)
  if (idx !== -1) {
    activeList.splice(idx, 1)
    uiStore.showToast('Xóa dịch vụ thành công!', 'success')
  }
}

// --- State for THIẾT LẬP BÁO CÁO TỔNG HỢP ---
const synthesisReports = ref([
  { id: 1, code: 'DT', name: 'Báo cáo tổng doanh thu', description: 'Báo cáo tổng doanh thu', is_hidden: false },
  { id: 2, code: 'CP', name: 'Báo cáo chi phí hoạt động', description: 'Báo cáo chi phí hoạt động chi tiết', is_hidden: false },
  { id: 3, code: 'LN', name: 'Báo cáo lợi nhuận gộp', description: 'Báo cáo lợi nhuận gộp tạm tính', is_hidden: true }
])
const selectedReportId = ref(1)

const isReportConfigModalOpen = ref(false)
const isEditReportConfigMode = ref(false)
const reportConfigFormState = reactive({
  id: null,
  code: '',
  name: '',
  description: '',
  is_hidden: false
})

const openAddReportConfigModal = () => {
  isEditReportConfigMode.value = false
  Object.assign(reportConfigFormState, {
    id: null,
    code: '',
    name: '',
    description: '',
    is_hidden: false
  })
  isReportConfigModalOpen.value = true
}

const openEditReportConfigModal = (report) => {
  isEditReportConfigMode.value = true
  Object.assign(reportConfigFormState, {
    id: report.id,
    code: report.code,
    name: report.name,
    description: report.description,
    is_hidden: !!report.is_hidden
  })
  isReportConfigModalOpen.value = true
}

const saveReportConfig = () => {
  if (!reportConfigFormState.code || !reportConfigFormState.name) {
    uiStore.showToast('Vui lòng nhập mã và tên báo cáo', 'warning')
    return
  }
  if (isEditReportConfigMode.value) {
    const idx = synthesisReports.value.findIndex(r => r.id === reportConfigFormState.id)
    if (idx !== -1) {
      synthesisReports.value[idx] = { ...synthesisReports.value[idx], ...reportConfigFormState }
    }
    uiStore.showToast('Cập nhật báo cáo thành công!', 'success')
  } else {
    const newId = Date.now()
    synthesisReports.value.push({
      id: newId,
      ...reportConfigFormState
    })
    selectedReportId.value = newId
    uiStore.showToast('Thêm báo cáo mới thành công!', 'success')
  }
  isReportConfigModalOpen.value = false
}

const deleteReportConfig = async (reportId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa báo cáo này cùng toàn bộ thiết lập?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const idx = synthesisReports.value.findIndex(r => r.id === reportId)
  if (idx !== -1) {
    synthesisReports.value.splice(idx, 1)
    synthesisReportLines.value = synthesisReportLines.value.filter(l => l.report_id !== reportId)
    if (selectedReportId.value === reportId) {
      selectedReportId.value = synthesisReports.value[0]?.id || null
    }
    uiStore.showToast('Xóa báo cáo thành công!', 'success')
  }
}

const synthesisReportLines = ref([
  {
    id: 1,
    report_id: 1,
    line_no: '01',
    line_code: 'REV_ROOM',
    line_desc: 'Báo cáo tổng doanh thu',
    level: 1,
    operator: '+',
    line_id_pm: '',
    departments: 'Reception/ Lê Tân',
    outlets: '',
    area_code: '',
    service_code: '',
    color: '#5cbeff',
    is_printed: true,
    is_bold: true
  }
])

const selectedReportLineId = ref(null)

const reportLineFormState = reactive({
  line_no: '',
  line_code: '',
  line_desc: '',
  level: 1,
  operator: '+',
  line_id_pm: '',
  departments: '',
  outlets: '',
  area_code: '',
  service_code: '',
  color: '#5cbeff',
  is_printed: true,
  is_bold: false
})

const selectReportLine = (line) => {
  selectedReportLineId.value = line.id
  Object.assign(reportLineFormState, {
    line_no: line.line_no,
    line_code: line.line_code,
    line_desc: line.line_desc,
    level: line.level,
    operator: line.operator,
    line_id_pm: line.line_id_pm,
    departments: line.departments,
    outlets: line.outlets,
    area_code: line.area_code,
    service_code: line.service_code,
    color: line.color,
    is_printed: !!line.is_printed,
    is_bold: !!line.is_bold
  })
}

const clearReportLineForm = () => {
  selectedReportLineId.value = null
  Object.assign(reportLineFormState, {
    line_no: '',
    line_code: '',
    line_desc: '',
    level: 1,
    operator: '+',
    line_id_pm: '',
    departments: '',
    outlets: '',
    area_code: '',
    service_code: '',
    color: '#5cbeff',
    is_printed: true,
    is_bold: false
  })
}

const addReportLine = () => {
  clearReportLineForm()
  const activeLines = synthesisReportLines.value.filter(l => l.report_id === selectedReportId.value)
  const nextNo = String(activeLines.length + 1).padStart(2, '0')
  reportLineFormState.line_no = nextNo
  uiStore.showToast('Vui lòng cấu hình dòng báo cáo mới ở bảng bên trái', 'info')
}

const saveReportLine = () => {
  if (!selectedReportId.value) {
    uiStore.showToast('Vui lòng chọn hoặc thêm một báo cáo trước', 'warning')
    return
  }
  if (!reportLineFormState.line_no || !reportLineFormState.line_desc) {
    uiStore.showToast('Vui lòng nhập mã dòng và diễn giải dòng', 'warning')
    return
  }

  if (selectedReportLineId.value) {
    const idx = synthesisReportLines.value.findIndex(l => l.id === selectedReportLineId.value)
    if (idx !== -1) {
      synthesisReportLines.value[idx] = {
        ...synthesisReportLines.value[idx],
        ...reportLineFormState
      }
      uiStore.showToast('Cập nhật dòng báo cáo thành công!', 'success')
    }
  } else {
    const newId = Date.now()
    synthesisReportLines.value.push({
      id: newId,
      report_id: selectedReportId.value,
      ...reportLineFormState
    })
    selectedReportLineId.value = newId
    uiStore.showToast('Thêm dòng báo cáo mới thành công!', 'success')
  }
}

const deleteReportLine = async () => {
  if (!selectedReportLineId.value) {
    uiStore.showToast('Vui lòng chọn một dòng báo cáo ở bảng dưới để xóa', 'warning')
    return
  }
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dòng báo cáo này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const idx = synthesisReportLines.value.findIndex(l => l.id === selectedReportLineId.value)
  if (idx !== -1) {
    synthesisReportLines.value.splice(idx, 1)
    clearReportLineForm()
    uiStore.showToast('Xóa dòng báo cáo thành công!', 'success')
  }
}

// Service CRUD operations
const openAddServiceModal = () => {
  isEditMode.value = false
  Object.assign(serviceFormState, {
    id: null,
    code: '',
    name: '',
    service_charge: 5,
    tax: 8,
    special_tax: 0,
    include_service_charge: true,
    include_tax: true,
    include_special_tax: true,
    folio: 1,
    short_name: '',
    unit: '',
    price: 0,
    department: 'Reception/ Lê Tân'
  })
  isServiceModalOpen.value = true
}

const openEditServiceModal = (service) => {
  isEditMode.value = true
  Object.assign(serviceFormState, {
    id: service.id,
    code: service.code,
    name: service.name,
    service_charge: service.service_charge,
    tax: service.tax,
    special_tax: service.special_tax,
    include_service_charge: !!service.include_service_charge,
    include_tax: !!service.include_tax,
    include_special_tax: !!service.include_special_tax,
    folio: service.folio,
    short_name: service.short_name,
    unit: service.unit,
    price: service.price,
    department: service.department
  })
  isServiceModalOpen.value = true
}

const saveService = async () => {
  if (!serviceFormState.code || !serviceFormState.name) {
    uiStore.showToast('Vui lòng điền mã và tên dịch vụ', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/hotel-services/${serviceFormState.id}`, serviceFormState)
      uiStore.showToast('Cập nhật dịch vụ thành công!', 'success')
    } else {
      await http.post('/hotel-services', serviceFormState)
      uiStore.showToast('Thêm dịch vụ mới thành công!', 'success')
    }
    isServiceModalOpen.value = false
    fetchHotelServices()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu dịch vụ'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteService = async (serviceId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dịch vụ này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/hotel-services/${serviceId}`)
    uiStore.showToast('Xóa dịch vụ thành công!', 'success')
    fetchHotelServices()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa dịch vụ này', 'error')
  }
}

const toggleServiceFlag = async (service, field) => {
  try {
    const updatedVal = !service[field]
    await http.put(`/hotel-services/${service.id}`, {
      ...service,
      [field]: updatedVal
    })
    service[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

const isSyncingCrm = ref(false)
const syncCrm = async () => {
  isSyncingCrm.value = true
  uiStore.showToast('Đang kết nối đồng bộ CRM...', 'info')
  setTimeout(() => {
    isSyncingCrm.value = false
    uiStore.showToast('Đồng bộ CRM thành công!', 'success')
  }, 1500)
}

// Shift CRUD
const openAddShiftModal = () => {
  isEditMode.value = false
  Object.assign(shiftFormState, {
    id: null,
    name: '',
    start_time: '',
    end_time: ''
  })
  isShiftModalOpen.value = true
}

const openEditShiftModal = (shift) => {
  isEditMode.value = true
  const formatTime = (timeStr) => {
    if (!timeStr) return ''
    const parts = timeStr.split(':')
    if (parts.length >= 2) {
      return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`
    }
    return timeStr
  }
  Object.assign(shiftFormState, {
    id: shift.id,
    name: shift.name,
    start_time: formatTime(shift.start_time),
    end_time: formatTime(shift.end_time)
  })
  isShiftModalOpen.value = true
}

const saveShift = async () => {
  if (shiftFormState.name === '' || !shiftFormState.start_time || !shiftFormState.end_time) {
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin ca làm việc', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/shifts/${shiftFormState.id}`, shiftFormState)
      uiStore.showToast('Cập nhật ca làm việc thành công!', 'success')
    } else {
      await http.post('/shifts', shiftFormState)
      uiStore.showToast('Thêm ca làm việc mới thành công!', 'success')
    }
    isShiftModalOpen.value = false
    fetchShifts()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu ca làm việc'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteShift = async (shiftId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa ca làm việc này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/shifts/${shiftId}`)
    uiStore.showToast('Xóa ca làm việc thành công!', 'success')
    fetchShifts()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa ca làm việc này', 'error')
  }
}

// Config CRUD
const openAddConfigModal = () => {
  isEditMode.value = false
  Object.assign(configFormState, {
    id: null,
    name: '',
    value: '',
    description: ''
  })
  isConfigModalOpen.value = true
}

const openEditConfigModal = (config) => {
  isEditMode.value = true
  Object.assign(configFormState, {
    id: config.id,
    name: config.name,
    value: config.value,
    description: config.description
  })
  isConfigModalOpen.value = true
}

const saveConfig = async () => {
  if (!configFormState.name) {
    uiStore.showToast('Vui lòng nhập tên cấu hình', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/hotel-configs/${configFormState.id}`, configFormState)
      uiStore.showToast('Cập nhật cấu hình thành công!', 'success')
    } else {
      await http.post('/hotel-configs', configFormState)
      uiStore.showToast('Thêm cấu hình mới thành công!', 'success')
    }
    isConfigModalOpen.value = false
    fetchHotelConfigs()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu cấu hình'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteConfig = async (configId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa cấu hình này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/hotel-configs/${configId}`)
    uiStore.showToast('Xóa cấu hình thành công!', 'success')
    fetchHotelConfigs()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa cấu hình này', 'error')
  }
}

// Branch CRUD
const openAddBranchModal = () => {
  isEditMode.value = false
  Object.assign(branchFormState, {
    id: null,
    code: '',
    name: '',
    api_url: '',
    api_report_url: '',
    is_master: false
  })
  isBranchModalOpen.value = true
}

const openEditBranchModal = (branch) => {
  isEditMode.value = true
  Object.assign(branchFormState, {
    id: branch.id,
    code: branch.code,
    name: branch.name,
    api_url: branch.api_url,
    api_report_url: branch.api_report_url,
    is_master: !!branch.is_master
  })
  isBranchModalOpen.value = true
}

const saveBranch = async () => {
  if (!branchFormState.code || !branchFormState.name) {
    uiStore.showToast('Vui lòng nhập mã và tên chi nhánh', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/branches-total/${branchFormState.id}`, branchFormState)
      uiStore.showToast('Cập nhật chi nhánh thành công!', 'success')
    } else {
      await http.post('/branches-total', branchFormState)
      uiStore.showToast('Thêm chi nhánh mới thành công!', 'success')
    }
    isBranchModalOpen.value = false
    fetchBranches()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu chi nhánh'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteBranch = async (branchId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa chi nhánh này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/branches-total/${branchId}`)
    uiStore.showToast('Xóa chi nhánh thành công!', 'success')
    fetchBranches()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa chi nhánh này', 'error')
  }
}

const toggleBranchMaster = async (branch) => {
  try {
    const updatedVal = !branch.is_master
    await http.put(`/branches-total/${branch.id}`, {
      ...branch,
      is_master: updatedVal
    })
    uiStore.showToast('Cập nhật chi nhánh Master thành công!', 'success')
    fetchBranches()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật chi nhánh Master', 'error')
  }
}

// Template update
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

// Image Upload & Utility functions
const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  const isDev = import.meta.env.DEV
  const backendUrl = 'http://localhost:8000'
  return isDev ? `${backendUrl}/${path}` : `/${path}`
}

const logoInput = ref(null)
const qrInput = ref(null)

const onLogoSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('logo', file)

  loading.value = true
  try {
    const res = await http.post('/hotel-settings/logo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    if (res.data && res.data.data) {
      hotelForm.logo_url = res.data.data.logo_url
      uiStore.showToast('Tải lên logo thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi tải lên logo:', err)
    uiStore.showToast('Không thể tải lên logo', 'error')
  } finally {
    loading.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

const removeLogo = async () => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa logo',
    message: 'Bạn có chắc chắn muốn xóa logo của khách sạn?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  try {
    const res = await http.delete('/hotel-settings/logo')
    if (res.data && res.data.data) {
      hotelForm.logo_url = res.data.data.logo_url
      uiStore.showToast('Xóa logo thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi xóa logo:', err)
    uiStore.showToast('Không thể xóa logo', 'error')
  } finally {
    loading.value = false
  }
}

const onQrSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('qr_code', file)

  loading.value = true
  try {
    const res = await http.post('/hotel-settings/qr-code', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    if (res.data && res.data.data) {
      hotelForm.qr_code_url = res.data.data.qr_code_url
      uiStore.showToast('Tải lên mã QR thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi tải lên mã QR:', err)
    uiStore.showToast('Không thể tải lên mã QR', 'error')
  } finally {
    loading.value = false
    if (qrInput.value) qrInput.value.value = ''
  }
}

const removeQrCode = async () => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa mã QR',
    message: 'Bạn có chắc chắn muốn xóa mã QR thanh toán?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  try {
    const res = await http.delete('/hotel-settings/qr-code')
    if (res.data && res.data.data) {
      hotelForm.qr_code_url = res.data.data.qr_code_url
      uiStore.showToast('Xóa mã QR thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi xóa mã QR:', err)
    uiStore.showToast('Không thể xóa mã QR', 'error')
  } finally {
    loading.value = false
  }
}

// API Functions
const fetchHotelSettings = async () => {
  try {
    const res = await http.get('/hotel-settings')
    if (res.data && res.data.data) {
      Object.assign(hotelForm, res.data.data)
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  }
}

const saveHotelSettings = async () => {
  if (hotelForm.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(hotelForm.email)) {
    uiStore.showToast('Email không đúng định dạng', 'warning')
    return
  }
  loading.value = true
  try {
    await http.put('/hotel-settings', hotelForm)
    uiStore.showToast('Lưu thông tin khách sạn thành công!', 'success')
  } catch (err) {
    console.error('Lỗi khi lưu cấu hình khách sạn:', err)
    const errorMsg = err.response?.data?.message || 'Không thể lưu cấu hình khách sạn'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const fetchHotelServices = async () => {
  try {
    const res = await http.get('/hotel-services')
    if (res.data && res.data.data) {
      hotelServices.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách dịch vụ:', err)
  }
}

const fetchShifts = async () => {
  try {
    const res = await http.get('/shifts')
    if (res.data && res.data.data) {
      shifts.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách ca làm việc:', err)
  }
}

const fetchHotelConfigs = async () => {
  try {
    const res = await http.get('/hotel-configs')
    if (res.data && res.data.data) {
      hotelConfigs.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  }
}

const fetchBranches = async () => {
  try {
    const res = await http.get('/branches-total')
    if (res.data && res.data.data) {
      branches.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách chi nhánh:', err)
  }
}

const fetchTemplates = async () => {
  try {
    const res = await http.get('/templates')
    if (res.data && res.data.data) {
      templates.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách mẫu báo cáo:', err)
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

// Initial fetches
const isLoaded = ref(false)

onMounted(async () => {
  try {
    await Promise.all([
      fetchHotelSettings(),
      fetchHotelServices(),
      fetchShifts(),
      fetchHotelConfigs(),
      fetchBranches(),
      fetchTemplates()
    ])
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu định nghĩa khách sạn:', err)
  } finally {
    isLoaded.value = true
  }
})
</script>

<template>
  <div class="flex flex-col gap-4 h-full overflow-hidden">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in hotelTabs" :key="tab" @click="activeHotelTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeHotelTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 overflow-y-auto min-h-[400px] relative" :class="activeHotelTab === 'THÔNG TIN KHÁCH SẠN'
      ? ''
      : 'bg-white rounded-xl shadow-xs border border-slate-200 p-6'">

      <!-- Loading State (Premium 3D Rotating Rings Loader) -->
      <div v-if="!isLoaded" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>

      <!-- Tab 1: THÔNG TIN KHÁCH SẠN -->
      <div v-if="activeHotelTab === 'THÔNG TIN KHÁCH SẠN'" class="flex flex-col gap-4 py-2">
        <!-- Action Save Button for Hotel Info Form -->
        <div class="flex justify-start">
          <button @click="saveHotelSettings"
            class="px-4 py-1.5 bg-sky-100 hover:bg-sky-200 border border-sky-300 hover:border-sky-400 text-sky-600 hover:text-sky-700 font-bold rounded-lg text-sm flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors">
            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="2"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v5h8" />
            </svg>
            Lưu
          </button>
        </div>

        <!-- Grid Content -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <!-- Col 1: Basic settings -->
          <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-col gap-4">
            <div class="grid grid-cols-12 items-center gap-2 text-sm font-bold text-slate-600">
              <span class="col-span-2">Mã KS</span>
              <input type="text" v-model="hotelForm.division"
                class="col-span-3 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
              <span class="col-span-3 text-right pr-2">Tên KS/KNM</span>
              <input type="text" v-model="hotelForm.hotel_name"
                class="col-span-4 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Địa chỉ</span>
              <textarea v-model="hotelForm.address" rows="2"
                class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm resize-none"></textarea>
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Thuế</span>
              <input type="text" v-model="hotelForm.tax_code"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Số điện thoại</span>
              <input type="text" v-model="hotelForm.phone"
                class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Số fax</span>
              <input type="text" v-model="hotelForm.fax"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Email</span>
              <input type="email" v-model="hotelForm.email"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Facebook</span>
              <input type="text" v-model="hotelForm.facebook"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Kênh quản lý</span>
              <input type="text" v-model="hotelForm.channel_manager"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Tiền tệ</span>
              <select v-model="hotelForm.currency"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold bg-white text-sm">
                <option value="VND">🇻🇳 VND</option>
                <option value="USD">🇺🇸 USD</option>
              </select>
            </div>
          </div>

          <!-- Col 2: Bank details and prices -->
          <div class="lg:col-span-4 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-col gap-4">
            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Tên ngân hàng</span>
              <input type="text" v-model="hotelForm.bank"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Tên tài khoản</span>
              <input type="text" v-model="hotelForm.account_name"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Số tài khoản</span>
              <input type="text" v-model="hotelForm.account"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Giá ăn sáng người lớn</span>
              <input type="number" v-model="hotelForm.breakfast_adult_rate"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Giá ăn sáng trẻ em</span>
              <input type="number" v-model="hotelForm.breakfast_child_rate"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Giá Thêm Giường</span>
              <input type="number" v-model="hotelForm.extra_bed_rate"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Số phòng</span>
              <input type="number" v-model="hotelForm.room_number"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 bg-yellow-50 focus:outline-sky-500 font-bold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Web Hotel</span>
              <input type="text" v-model="hotelForm.website"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
            </div>

            <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
              <span>Tiền tố mã đăng ký</span>
              <input type="text" v-model="hotelForm.prefix_booking_id"
                class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold uppercase text-sm" />
            </div>
          </div>

          <!-- Col 3: Logo and QR -->
          <div class="lg:col-span-3 flex flex-col items-center">
            <!-- Logo Box (Card style) -->
            <div
              class="w-full bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col overflow-hidden">
              <div class="p-3 bg-slate-50 border-b border-slate-100 text-center font-bold text-slate-700 text-sm">
                Hình ảnh
              </div>
              <div class="p-6 flex flex-col items-center justify-center gap-4">
                <!-- Logo Image -->
                <div v-if="hotelForm.logo_url" class="w-24 h-24 rounded-full overflow-hidden shadow-inner border border-slate-200">
                  <img :src="getImageUrl(hotelForm.logo_url)" alt="Logo" class="w-full h-full object-cover" />
                </div>
                <div v-else
                  class="w-24 h-24 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 shadow-inner">
                  <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                </div>
                <!-- Action buttons -->
                <div class="flex items-center gap-4 text-slate-400">
                  <label class="p-1 text-slate-400 hover:text-sky-600 bg-transparent border-none cursor-pointer flex items-center justify-center">
                    <input type="file" ref="logoInput" @change="onLogoSelected" class="hidden" accept="image/*" />
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                  </label>
                  <a v-if="hotelForm.logo_url" :href="getImageUrl(hotelForm.logo_url)" target="_blank"
                    class="p-1 text-slate-400 hover:text-slate-700 bg-transparent border-none cursor-pointer flex items-center justify-center">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </a>
                  <button v-if="hotelForm.logo_url" @click="removeLogo" class="p-1 text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- QR Box -->
            <div class="flex flex-col items-center p-2 mt-4 gap-2">
              <div v-if="hotelForm.qr_code_url" class="w-32 h-32 rounded-xl overflow-hidden border border-slate-200 bg-white p-2 shadow-2xs">
                <img :src="getImageUrl(hotelForm.qr_code_url)" alt="QR Code" class="w-full h-full object-contain" />
              </div>
              <svg v-else class="w-32 h-32 text-slate-700 bg-white p-2 rounded-xl border border-slate-100 shadow-2xs"
                fill="currentColor" viewBox="0 0 24 24">
                <path
                  d="M0 0h6v6H0V0zm1 1v4h4V1H1zm7-1h6v6H8V0zm1 1v4h4V1H9zm7-1h6v6h-6V0zm1 1v4h4V1h-4zM0 8h6v6H0V8zm1 1v4h4V9H1zm7 0h6v6H8V9zm1 1v4h4v-4H9zm7-1h1v1h-1V9zm1 1h1v1h-1v-1zm-1 1h1v1h-1v-1zm2-2h1v1h-1V9zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm-1 3h1v1h-1v-1zm-1-1h1v1h-1v-1zm-1 1h1v1h-1v-1zm2-1h1v1h-1v-1zm1 1h1v1h-1v-1zm-6 2h1v1H8v-1zm1 1h1v1H9v-1zm-1 1h1v1H8v-1zm2-3h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm3 0h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1zm-6 3h1v1h-1v-1zm1 1h1v1H9v-1zm-1 1h1v1H8v-1zm2-3h1v1h-1v-1zm0 2h1v1h-1v-1zm1-1h1v1h-1v-1z" />
              </svg>
              <span class="text-xs text-slate-500 font-extrabold tracking-wider">MÃ QR THANH TOÁN / ĐẶT PHÒNG</span>
              
              <!-- QR Action Buttons -->
              <div class="flex items-center gap-4 text-slate-400 mt-1">
                <label class="p-1 text-slate-400 hover:text-sky-600 bg-transparent border-none cursor-pointer flex items-center justify-center">
                  <input type="file" ref="qrInput" @change="onQrSelected" class="hidden" accept="image/*" />
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                </label>
                <a v-if="hotelForm.qr_code_url" :href="getImageUrl(hotelForm.qr_code_url)" target="_blank"
                  class="p-1 text-slate-400 hover:text-slate-700 bg-transparent border-none cursor-pointer flex items-center justify-center">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </a>
                <button v-if="hotelForm.qr_code_url" @click="removeQrCode" class="p-1 text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: DỊCH VỤ KHÁCH SẠN -->
      <div v-else-if="activeHotelTab === 'DỊCH VỤ KHÁCH SẠN'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <button @click="openAddServiceModal"
              class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              Thêm
            </button>
          </div>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchServiceQuery" placeholder="Tìm kiếm mã, tên..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3 whitespace-nowrap">Mã</th>
                <th class="p-3 min-w-[200px]">Tên dịch vụ đầy đủ</th>
                <th class="p-3 text-center whitespace-nowrap">Phí phục vụ (%)</th>
                <th class="p-3 text-center whitespace-nowrap">Thuế (%)</th>
                <th class="p-3 text-center whitespace-nowrap">Thuế đặc biệt (%)</th>
                <th class="p-3 text-center whitespace-nowrap">Bao gồm phí dịch vụ</th>
                <th class="p-3 text-center whitespace-nowrap">Bao gồm thuế</th>
                <th class="p-3 text-center whitespace-nowrap">Bao gồm thuế đặc biệt</th>
                <th class="p-3 text-center whitespace-nowrap">Folio</th>
                <th class="p-3 whitespace-nowrap">Tên ngắn</th>
                <th class="p-3 whitespace-nowrap">Đơn vị</th>
                <th class="p-3 text-right whitespace-nowrap">Giá</th>
                <th class="p-3 whitespace-nowrap">Bộ phận</th>
                <th class="p-3 text-right whitespace-nowrap">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="s in hotelServices.filter(item => !searchServiceQuery || (item.code && item.code.toLowerCase().includes(searchServiceQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchServiceQuery.toLowerCase())))"
                :key="s.id" @click="openEditServiceModal(s)" class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ s.code }}</td>
                <td class="p-3 font-bold text-slate-700">{{ s.name }}</td>
                <td class="p-3 text-center font-bold text-slate-600">{{ s.service_charge }}</td>
                <td class="p-3 text-center font-bold text-slate-600">{{ s.tax }}</td>
                <td class="p-3 text-center font-bold text-slate-600">{{ s.special_tax }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="s.include_service_charge"
                      @change="toggleServiceFlag(s, 'include_service_charge')" class="sr-only peer" />
                    <div
                      class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                    </div>
                  </label>
                </td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="s.include_tax" @change="toggleServiceFlag(s, 'include_tax')"
                      class="sr-only peer" />
                    <div
                      class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                    </div>
                  </label>
                </td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="s.include_special_tax"
                      @change="toggleServiceFlag(s, 'include_special_tax')" class="sr-only peer" />
                    <div
                      class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                    </div>
                  </label>
                </td>
                <td class="p-3 text-center font-bold text-slate-700">{{ s.folio }}</td>
                <td class="p-3 text-slate-600 font-semibold">{{ s.short_name || '-' }}</td>
                <td class="p-3 text-slate-600 font-semibold">{{ s.unit || '-' }}</td>
                <td class="p-3 text-right font-extrabold text-sky-700">{{ formatCurrency(s.price) }}</td>
                <td class="p-3 text-slate-500 font-semibold text-xs">{{ s.department || '-' }}</td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click.stop="deleteService(s.id)"
                      class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 3: CA LÀM VIỆC -->
      <div v-else-if="activeHotelTab === 'CA LÀM VIỆC'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <button @click="openAddShiftModal"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm ca làm việc
          </button>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Tên ca làm việc</th>
                <th class="p-3">Thời gian bắt đầu</th>
                <th class="p-3">Thời gian kết thúc</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sh in shifts" :key="sh.id" @click="openEditShiftModal(sh)" class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ sh.name }}</td>
                <td class="p-3 font-bold text-slate-600 font-mono">{{ sh.start_time }}</td>
                <td class="p-3 font-bold text-slate-600 font-mono">{{ sh.end_time }}</td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click.stop="deleteShift(sh.id)"
                      class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="shifts.length === 0">
                <td colspan="4" class="p-6 text-center text-slate-400 italic">Chưa cấu hình ca làm việc nào.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 4: CẤU HÌNH -->
      <div v-else-if="activeHotelTab === 'CẤU HÌNH'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <button @click="openAddConfigModal"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Thêm cấu hình
          </button>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchConfigQuery" placeholder="Tìm kiếm cấu hình..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Tên cấu hình</th>
                <th class="p-3">Giá trị</th>
                <th class="p-3">Mô tả</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="cfg in hotelConfigs.filter(item => !searchConfigQuery || (item.name && item.name.toLowerCase().includes(searchConfigQuery.toLowerCase())) || (item.description && item.description.toLowerCase().includes(searchConfigQuery.toLowerCase())))"
                :key="cfg.id" @click="openEditConfigModal(cfg)" class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ cfg.name }}</td>
                <td class="p-3 font-bold text-sky-700 font-mono">{{ cfg.value || '-' }}</td>
                <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed max-w-xs">{{ cfg.description || '-' }}</td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click.stop="deleteConfig(cfg.id)"
                      class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="hotelConfigs.length === 0">
                <td colspan="4" class="p-6 text-center text-slate-400 italic">Chưa cấu hình thông số nào.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 5: CHI NHÁNH -->
      <div v-else-if="activeHotelTab === 'CHI NHÁNH'" class="flex flex-col gap-4">
        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
          <div class="flex items-center gap-2">
            <button @click="openAddBranchModal"
              class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              Thêm chi nhánh
            </button>
            <button @click="syncCrm" :disabled="isSyncingCrm"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200/80 disabled:opacity-50 text-slate-600 rounded-lg text-sm font-bold flex items-center gap-1.5 border border-slate-200 cursor-pointer transition-colors shadow-2xs">
              Đồng bộ CRM
            </button>
          </div>
          <div class="relative max-w-xs w-full">
            <input type="text" v-model="searchBranchQuery" placeholder="Tìm kiếm chi nhánh..."
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3">Mã CN</th>
                <th class="p-3">Tên chi nhánh</th>
                <th class="p-3">API Link</th>
                <th class="p-3">API Report Link</th>
                <th class="p-3 text-center">Master</th>
                <th class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="b in branches.filter(item => !searchBranchQuery || (item.code && item.code.toLowerCase().includes(searchBranchQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchBranchQuery.toLowerCase())))"
                :key="b.id" @click="openEditBranchModal(b)" class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                <td class="p-3 font-bold text-slate-800">{{ b.code }}</td>
                <td class="p-3 font-bold text-slate-700">{{ b.name }}</td>
                <td class="p-3 font-semibold text-sky-700 text-xs break-all select-all">{{ b.api_url || '-' }}</td>
                <td class="p-3 font-semibold text-sky-700 text-xs break-all select-all">{{ b.api_report_url || '-' }}</td>
                <td class="p-3 text-center">
                  <label @click.stop class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" :checked="b.is_master" @change="toggleBranchMaster(b)"
                      class="sr-only peer" />
                    <div
                      class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                    </div>
                  </label>
                </td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click.stop="deleteBranch(b.id)"
                      class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="branches.length === 0">
                <td colspan="6" class="p-6 text-center text-slate-400 italic">Chưa có thông tin chi nhánh nào.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 6: MẪU -->
      <div v-else-if="activeHotelTab === 'MẪU'" class="flex gap-6 items-stretch">
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

      <!-- Tab 7: BỘ PHẬN DỊCH VỤ -->
      <div v-else-if="activeHotelTab === 'BỘ PHẬN DỊCH VỤ'" class="flex gap-6 items-stretch">
        <!-- Left: Departments -->
        <div class="w-1/4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex flex-col gap-1.5">
          <span class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Bộ phận</span>
          <button v-for="dept in departments" :key="dept" @click="activeDepartment = dept"
            class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
            :class="activeDepartment === dept ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
            {{ dept }}
          </button>
        </div>

        <!-- Right: Services under Department -->
        <div class="flex-1 flex flex-col gap-4">
          <div class="flex justify-between items-center pb-2 border-b border-slate-100 flex-wrap gap-2">
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
              Dịch vụ thuộc bộ phận: {{ activeDepartment }}
            </span>
            <div class="flex gap-2">
              <button @click="openAddDeptServiceModal"
                class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-xs font-bold border-none cursor-pointer flex items-center gap-1">
                + Thêm dịch vụ
              </button>
              <input type="text" v-model="searchDeptServiceQuery" placeholder="Tìm tên dịch vụ..."
                class="border border-slate-200 rounded px-2.5 py-1 text-xs focus:outline-sky-500 font-semibold" />
            </div>
          </div>

          <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
            <table class="w-full text-sm text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                  <th class="p-3 w-1/3">Tên dịch vụ</th>
                  <th class="p-3">Mô tả chi tiết</th>
                  <th class="p-3 text-right w-24">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in (departmentServices[activeDepartment] || []).filter(item => !searchDeptServiceQuery || item.name.toLowerCase().includes(searchDeptServiceQuery.toLowerCase()))" :key="s.id"
                  @click="openEditDeptServiceModal(s)" class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
                  <td class="p-3 font-bold text-slate-800">{{ s.name }}</td>
                  <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed">{{ s.description || '-' }}</td>
                  <td class="p-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      <button @click.stop="deleteDeptService(s.id)"
                        class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!(departmentServices[activeDepartment] || []).length">
                  <td colspan="3" class="p-6 text-center text-slate-400 italic">Chưa cấu hình dịch vụ bộ phận nào.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tab 8: THIẾT LẬP BÁO CÁO TỔNG HỢP -->
      <div v-else-if="activeHotelTab === 'THIẾT LẬP BÁO CÁO TỔNG HỢP'" class="flex flex-col gap-6">
        <!-- Top bar layout: Select synthesis report -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-wrap gap-4">
          <div class="flex items-center gap-3">
            <span class="text-xs font-black text-slate-600 uppercase">Chọn báo cáo tổng hợp:</span>
            <select v-model="selectedReportId"
              class="border border-slate-200 rounded-lg px-3 py-1.5 bg-white text-sm font-bold text-slate-700 focus:outline-sky-500 cursor-pointer min-w-[200px]">
              <option v-for="rep in synthesisReports" :key="rep.id" :value="rep.id">{{ rep.code }} - {{ rep.name }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddReportConfigModal"
              class="px-3.5 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
              Thêm báo cáo mới
            </button>
            <button
              @click="selectedReportId && openEditReportConfigModal(synthesisReports.find(r => r.id === selectedReportId))"
              :disabled="!selectedReportId"
              class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
              Sửa báo cáo
            </button>
            <button @click="deleteReportConfig(selectedReportId)" :disabled="!selectedReportId"
              class="px-3.5 py-1.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
              Xóa báo cáo
            </button>
          </div>
        </div>

        <!-- Inner Config content split: Left forms and Right lines table -->
        <div class="flex flex-col lg:flex-row gap-6 items-stretch">
          <!-- Left side layout: Configure Report Line Form -->
          <div class="w-full lg:w-1/3 bg-slate-50 border border-slate-200 rounded-xl p-5 shadow-inner flex flex-col gap-4">
            <span class="text-xs font-black text-slate-500 uppercase tracking-wide border-b border-slate-200 pb-2">
              {{ selectedReportLineId ? 'Cấu hình dòng báo cáo' : 'Thêm dòng báo cáo' }}
            </span>

            <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
              <div class="flex flex-col gap-1">
                <span>Số dòng*</span>
                <input type="text" v-model="reportLineFormState.line_no"
                  class="border border-slate-200 bg-yellow-50 rounded px-2.5 py-1.5 focus:outline-sky-500 font-bold" />
              </div>
              <div class="flex flex-col gap-1">
                <span>Mã dòng*</span>
                <input type="text" v-model="reportLineFormState.line_code"
                  class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-bold" />
              </div>
            </div>

            <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
              <span>Diễn giải dòng*</span>
              <input type="text" v-model="reportLineFormState.line_desc"
                class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-semibold" />
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
              <div class="flex flex-col gap-1">
                <span>Level</span>
                <select v-model="reportLineFormState.level"
                  class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
                  <option :value="1">1</option>
                  <option :value="2">2</option>
                  <option :value="3">3</option>
                  <option :value="4">4</option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <span>Toán tử</span>
                <select v-model="reportLineFormState.operator"
                  class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
                  <option value="+">+</option>
                  <option value="-">-</option>
                  <option value="*">*</option>
                  <option value="/">/</option>
                </select>
              </div>
            </div>

            <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
              <span>Line ID PM</span>
              <input type="text" v-model="reportLineFormState.line_id_pm"
                class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-mono" />
            </div>

            <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
              <span>Bộ phận liên kết</span>
              <select v-model="reportLineFormState.departments"
                class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
                <option value="">-- Bỏ trống --</option>
                <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
              <div class="flex flex-col gap-1">
                <span>Outlets</span>
                <input type="text" v-model="reportLineFormState.outlets"
                  class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500" />
              </div>
              <div class="flex flex-col gap-1">
                <span>Khu vực (Area)</span>
                <input type="text" v-model="reportLineFormState.area_code"
                  class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500" />
              </div>
            </div>

            <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
              <span>Mã dịch vụ khách sạn</span>
              <select v-model="reportLineFormState.service_code"
                class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
                <option value="">-- Bỏ trống --</option>
                <option v-for="srv in hotelServices" :key="srv.id" :value="srv.code">{{ srv.code }} - {{ srv.name }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-1 text-xs font-bold text-slate-600 select-none">
              <div class="flex items-center justify-between">
                <span>Có in ra</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="reportLineFormState.is_printed" class="sr-only peer">
                  <div
                    class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                  </div>
                </label>
              </div>

              <div class="flex items-center justify-between">
                <span>Chữ in đậm</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="reportLineFormState.is_bold" class="sr-only peer">
                  <div
                    class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                  </div>
                </label>
              </div>
            </div>

            <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
              <span>Màu sắc hiển thị</span>
              <div class="flex gap-2">
                <input type="color" v-model="reportLineFormState.color" class="w-8 h-8 rounded cursor-pointer border-none bg-transparent" />
                <input type="text" v-model="reportLineFormState.color"
                  class="border border-slate-200 rounded px-2 py-1.5 focus:outline-sky-500 flex-1 font-mono uppercase font-bold" />
              </div>
            </div>
          </div>

          <!-- Right side layout: Synthesis Report Lines Table -->
          <div class="flex-1 flex flex-col gap-4">
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
              Danh sách dòng báo cáo thiết lập
            </span>

            <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs max-h-[500px]">
              <table class="w-full text-sm text-left border-collapse min-w-[900px]">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                    <th class="p-3 w-12 text-center">#</th>
                    <th class="p-3 w-16 text-center">Số dòng</th>
                    <th class="p-3 w-24">Mã dòng</th>
                    <th class="p-3 min-w-[150px]">Diễn giải</th>
                    <th class="p-3 text-center">Level</th>
                    <th class="p-3 text-center">Toán tử</th>
                    <th class="p-3">Line ID PM</th>
                    <th class="p-3">Bộ phận</th>
                    <th class="p-3">Outlets</th>
                    <th class="p-3">Area</th>
                    <th class="p-3">Mã DV</th>
                    <th class="p-3 text-right w-24">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in synthesisReportLines.filter(l => l.report_id === selectedReportId)"
                    :key="line.id" @click="selectReportLine(line)"
                    class="border-b border-slate-100 cursor-pointer transition-colors"
                    :class="selectedReportLineId === line.id ? 'bg-sky-50 ring-1 ring-inset ring-sky-200' : 'hover:bg-slate-50/55'">
                    <td class="p-3 text-slate-400 text-center font-mono">{{ line.id }}</td>
                    <td class="p-3 text-center font-extrabold text-slate-700 font-mono">{{ line.line_no }}</td>
                    <td class="p-3 font-bold text-slate-800" :style="{ color: line.color }">{{ line.line_code }}</td>
                    <td class="p-3" :class="line.is_bold ? 'font-black text-slate-900' : 'text-slate-600 font-medium'">
                      {{ line.line_desc }}
                    </td>
                    <td class="p-3 text-center">
                      <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 font-black text-[10px] text-slate-500">{{ line.level }}</span>
                    </td>
                    <td class="p-3 text-center font-black text-slate-700 font-mono">{{ line.operator }}</td>
                    <td class="p-3 text-slate-500 font-mono font-semibold">{{ line.line_id_pm || '-' }}</td>
                    <td class="p-3 text-slate-500">{{ line.departments || '-' }}</td>
                    <td class="p-3 text-slate-500">{{ line.outlets || '-' }}</td>
                    <td class="p-3 text-slate-500 font-semibold">{{ line.area_code || '-' }}</td>
                    <td class="p-3 font-bold text-sky-700 text-center">{{ line.service_code || '-' }}</td>
                    <td class="p-3 text-right">
                      <div class="flex items-center justify-end gap-2 text-xs font-extrabold uppercase">
                        <span v-if="line.is_printed"
                          class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[10px]">In</span>
                        <span v-if="line.is_bold"
                          class="px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-[10px]">Đậm</span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="synthesisReportLines.filter(l => l.report_id === selectedReportId).length === 0"
                    class="text-center">
                    <td colspan="12" class="p-12 text-slate-400 italic">
                      <div class="flex flex-col items-center justify-center gap-2">
                        <svg class="w-12 h-12 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.879a1.5 1.5 0 00-1.06.44l-2.122 2.12a1.5 1.5 0 01-1.06.44H9.782a1.5 1.5 0 01-1.06-.44L6.6 13.44a1.5 1.5 0 00-1.06-.44H2" />
                        </svg>
                        <span>No data</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Buttons under bottom table -->
            <div class="flex items-center gap-2 mt-2">
              <button @click="addReportLine"
                class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center justify-center"
                title="Thêm dòng mới">
                +
              </button>
              <button @click="deleteReportLine"
                class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center justify-center"
                title="Xóa dòng">
                -
              </button>
              <button
                @click="selectedReportLineId && selectReportLine(synthesisReportLines.find(l => l.id === selectedReportLineId))"
                :disabled="!selectedReportLineId"
                class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] disabled:opacity-50 text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
                Sửa
              </button>
              <button @click="deleteReportLine" :disabled="!selectedReportLineId"
                class="px-4 py-2 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
                Xóa
              </button>
              <button @click="saveReportLine"
                class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
                Lưu
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Fallback block for remaining tabs -->
      <div v-else class="text-center py-12 flex flex-col items-center justify-center gap-3">
        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </div>
        <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">{{ activeHotelTab }}</span>
        <p class="text-sm text-slate-400 max-w-xs leading-relaxed">Chức năng cấu hình chi tiết đang được phát triển
          để đồng bộ hệ thống PMS.</p>
      </div>

    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SERVICE -->
    <div v-if="isServiceModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">Hotel Service</h2>
          <button @click="isServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-6 overflow-y-auto max-h-[85vh] text-sm font-bold text-slate-600">
          <h3 class="text-sm font-black text-slate-700 pb-1.5 border-b border-slate-100 uppercase tracking-wide">
            Thông tin dịch vụ <span class="text-red-500">*</span>
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="flex flex-col gap-4">
              <div class="flex flex-col gap-1.5">
                <span>Mã</span>
                <input type="text" v-model="serviceFormState.code" placeholder="BC"
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm bg-white" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Tên dịch vụ đầy đủ</span>
                <input type="text" v-model="serviceFormState.name" placeholder="Ăn sáng buffet trẻ em"
                  class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm bg-white font-semibold" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Tên ngắn</span>
                <input type="text" v-model="serviceFormState.short_name" placeholder="Ăn sáng trẻ em"
                  class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm bg-white font-semibold" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Bộ phận</span>
                <select v-model="serviceFormState.department"
                  class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                  <option value="Reception/ Lê Tân">Reception/ Lê Tân</option>
                  <option value="House Keeping/Buồng Phòng">House Keeping/Buồng Phòng</option>
                  <option value="Restaurant/Nhà Hàng">Restaurant/Nhà Hàng</option>
                  <option value="SPA">SPA</option>
                </select>
              </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col gap-4">
              <!-- Row 1: Phí phục vụ, Thuế, Thuế đặc biệt numbers -->
              <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1.5">
                  <span>Phí phục vụ</span>
                  <input type="number" v-model="serviceFormState.service_charge"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế</span>
                  <input type="number" v-model="serviceFormState.tax"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế đặc biệt</span>
                  <input type="number" v-model="serviceFormState.special_tax"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
              </div>

              <!-- Row 2: Toggles -->
              <div class="grid grid-cols-3 gap-3 py-1">
                <div class="flex flex-col gap-1.5">
                  <span>Phí phục vụ</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" v-model="serviceFormState.include_service_charge" class="sr-only peer">
                    <div
                      class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" v-model="serviceFormState.include_tax" class="sr-only peer">
                    <div
                      class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế đặc biệt</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" v-model="serviceFormState.include_special_tax" class="sr-only peer">
                    <div
                      class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
              </div>

              <!-- Row 3: Giá, Đơn vị, Folio -->
              <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1.5">
                  <span>Giá</span>
                  <input type="number" v-model="serviceFormState.price"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Đơn vị</span>
                  <select v-model="serviceFormState.unit"
                    class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                    <option value="Người">Người</option>
                    <option value="Lần">Lần</option>
                    <option value="Phòng">Phòng</option>
                    <option value="Cái">Cái</option>
                    <option value="Giờ">Giờ</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Folio</span>
                  <select v-model="serviceFormState.folio"
                    class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                    <option :value="1">1</option>
                    <option :value="2">2</option>
                    <option :value="3">3</option>
                    <option :value="4">4</option>
                    <option :value="5">5</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isServiceModalOpen = false"
            class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-bold text-sm cursor-pointer transition-colors flex items-center gap-1.5 border-none">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Đóng
          </button>
          <button @click="saveService"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors flex items-center gap-1.5">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            Lưu
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SHIFT -->
    <div v-if="isShiftModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa ca' : 'Thêm ca' }}</h2>
          <button @click="isShiftModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên ca làm việc*</span>
            <input type="text" v-model="shiftFormState.name" placeholder="Ví dụ: 0, 1, 2, Hành chính"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Thời gian bắt đầu*</span>
              <input type="time" v-model="shiftFormState.start_time"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Thời gian kết thúc*</span>
              <input type="time" v-model="shiftFormState.end_time"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isShiftModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveShift"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu ca
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT CONFIG -->
    <div v-if="isConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa cấu hình' : 'Thêm cấu hình' }}</h2>
          <button @click="isConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên cấu hình (Key)*</span>
            <input type="text" v-model="configFormState.name" :disabled="isEditMode"
              placeholder="AllowChangeRoomStatus..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm disabled:bg-slate-100 disabled:cursor-not-allowed" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Giá trị</span>
            <input type="text" v-model="configFormState.value" placeholder="1 hoặc 0 hoặc bỏ trống"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Mô tả</span>
            <textarea v-model="configFormState.description" rows="3" placeholder="Chi tiết mô tả chức năng..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none"></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isConfigModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveConfig"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu cấu hình
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT BRANCH -->
    <div v-if="isBranchModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Sửa' : 'Thêm' }}</h2>
          <button @click="isBranchModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Mã chi nhánh*</span>
              <input type="text" v-model="branchFormState.code" placeholder="HKT1"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Tên chi nhánh*</span>
              <input type="text" v-model="branchFormState.name" placeholder="HKT 1"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>API Connection URL</span>
            <input type="text" v-model="branchFormState.api_url" placeholder="https://hotel.hktsolution.vn/branches-total"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>API Report Connection URL</span>
            <input type="text" v-model="branchFormState.api_report_url" placeholder="https://hotel.hktsolution.vn/rppms1/"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex items-center justify-between border border-slate-100 rounded-lg p-3 bg-slate-50 mt-2">
            <span>Đặt làm chi nhánh chính (Is Master)</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="branchFormState.is_master" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-white px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
          <button @click="isBranchModalOpen = false"
            class="px-5 py-2.5 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm cursor-pointer transition-colors border-none flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-6 6M9 9l6 6" />
            </svg>
            Đóng
          </button>
          <button @click="saveBranch"
            class="px-5 py-2.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-sm transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8l-4-4H8z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 100-6 3 3 0 000 6z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 4v4h6V4" />
            </svg>
            Lưu
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT DEPARTMENT SERVICE -->
    <div v-if="isDeptServiceModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditDeptServiceMode ? 'Sửa dịch vụ bộ phận' : 'Thêm dịch vụ bộ phận' }}</h2>
          <button @click="isDeptServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên dịch vụ bộ phận*</span>
            <input type="text" v-model="deptServiceFormState.name" placeholder="Ví dụ: Buffet sáng, Massage..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Mô tả dịch vụ</span>
            <textarea v-model="deptServiceFormState.description" rows="3" placeholder="Chi tiết mô tả..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none font-semibold"></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isDeptServiceModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveDeptService"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu dịch vụ
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SYNTHESIS REPORT -->
    <div v-if="isReportConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditReportConfigMode ? 'Sửa báo cáo' : 'Thêm báo cáo mới' }}</h2>
          <button @click="isReportConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="grid grid-cols-3 gap-4">
            <div class="flex flex-col gap-1.5 col-span-1">
              <span>Mã báo cáo*</span>
              <input type="text" v-model="reportConfigFormState.code" placeholder="DT"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-bold" />
            </div>
            <div class="flex flex-col gap-1.5 col-span-2">
              <span>Tên báo cáo*</span>
              <input type="text" v-model="reportConfigFormState.name" placeholder="Báo cáo doanh thu..."
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold" />
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Diễn giải</span>
            <textarea v-model="reportConfigFormState.description" rows="2" placeholder="Diễn giải thêm..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none font-semibold"></textarea>
          </div>
          <div class="flex items-center justify-between border border-slate-100 rounded-lg p-3 bg-slate-50 mt-2">
            <span>Ẩn báo cáo này</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="reportConfigFormState.is_hidden" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isReportConfigModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveReportConfig"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu báo cáo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped custom animations for modal overlay */
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }

  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
