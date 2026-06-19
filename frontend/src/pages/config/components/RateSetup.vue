<script setup>
import { ref, reactive, watch, computed, onMounted, nextTick } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  initialTab: { type: String, default: 'Mã giá phòng' }
})
const emit = defineEmits(['update:activeTab'])

const activeRateTab = ref(props.initialTab)
watch(() => props.initialTab, (v) => { if (v) activeRateTab.value = v })
watch(activeRateTab, (v) => emit('update:activeTab', v))
const rateTabs = ['Mã giá phòng', 'Gói dịch vụ']

// ===================== STATE =====================
const loading = ref(false)
const today = new Date().toISOString().split('T')[0]

// --- Rate Codes ---
const rateCodes = ref([])
const selectedRateCode = ref(null)
const isNewMode = ref(false)

const rateForm = reactive({
  code: '',
  description: '',
  begin_date: '',
  end_date: '',
  currency: 'VND',
  include_bf: false,
  disable: false,
  allow_change_rate: false,
  is_channel_manager: false,
  type: 'fixed',
})

// --- Room Classes & Forms (cho grid) ---
const roomClasses = ref([]) // loại phòng: SUPD, DLXD...
const roomForms = ref([])   // dạng phòng: Double, Twin...

// --- Grid giá tĩnh (value JSON) ---
// matrix[roomClassId][roomFormId] = price
const matrix = reactive({})
// plansMatrix[ratePlanCode] = { roomClassId: { roomFormId: price } }
const plansMatrix = reactive({})

// --- Giá theo ngày ---
// Dùng riêng 1 field để track checkbox "Giá theo ngày"
const isGiaTheoNgay = ref(false)
let syncingGiaTheoNgay = false

// --- Rate Plans (mã con BB1/BB2...) ---
const ratePlans = ref([])

// --- Apply form (khi Giá theo ngày) ---
const applyForm = reactive({
  from: '',
  to: '',
  code: '',         // mã con chọn từ ratePlans
  days_of_week: [0, 1, 2, 3, 4, 5, 6], // 0=CN,1=T2...6=T7
})
const dayLabels = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']

// --- Daily data (bảng ngày × loại phòng) ---
const dailyRows = ref([])
const dailyFrom = ref('')
const dailyTo = ref('')

const isRatePlanModalOpen = ref(false)
const ratePlanForm = reactive({
  code: '',
  description: '',
  begin_date: '',
  end_date: '',
})
const selectedRatePlanRow = ref(null)

const fieldLabels = {
  code: 'Mã',
  description: 'Mô tả',
  begin_date: 'Từ ngày',
  end_date: 'Đến ngày',
  currency: 'Tiền tệ',
  period: 'Bảng giá',
  from: 'Ngày bắt đầu',
  to: 'Ngày kết thúc',
  days_of_week: 'Ngày trong tuần',
}

const getErrorMessage = (err, fallback = 'Đã xảy ra lỗi') => {
  const data = err?.response?.data
  if (!data) return fallback
  if (data.errors) {
    const messages = Object.entries(data.errors).flatMap(([field, msgs]) => {
      const label = fieldLabels[field] || field
      return (Array.isArray(msgs) ? msgs : [msgs]).map(m => `${label}: ${m}`)
    })
    return messages.join(' • ')
  }
  return data.message || fallback
}

const validateRateForm = () => {
  if (!rateForm.code?.trim()) {
    uiStore.showToast('Vui lòng nhập Mã giá phòng', 'warning')
    return false
  }
  if (!rateForm.begin_date) {
    uiStore.showToast('Vui lòng chọn Từ ngày', 'warning')
    return false
  }
  if (!rateForm.end_date) {
    uiStore.showToast('Vui lòng chọn Đến ngày', 'warning')
    return false
  }
  if (rateForm.begin_date > rateForm.end_date) {
    uiStore.showToast('Từ ngày không được lớn hơn Đến ngày', 'warning')
    return false
  }
  return true
}

const validateRatePlanForm = () => {
  if (!selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã giá phòng cha trước khi thêm mã con', 'warning')
    return false
  }
  if (!ratePlanForm.code?.trim()) {
    uiStore.showToast('Vui lòng nhập Mã con', 'warning')
    return false
  }
  if (!ratePlanForm.begin_date) {
    uiStore.showToast('Vui lòng chọn Từ ngày cho mã con', 'warning')
    return false
  }
  if (!ratePlanForm.end_date) {
    uiStore.showToast('Vui lòng chọn Đến ngày cho mã con', 'warning')
    return false
  }
  if (ratePlanForm.begin_date > ratePlanForm.end_date) {
    uiStore.showToast('Từ ngày mã con không được lớn hơn Đến ngày', 'warning')
    return false
  }
  return true
}

const validateApplyForm = () => {
  if (!selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã giá phòng', 'warning')
    return false
  }
  if (!applyForm.from) {
    uiStore.showToast('Vui lòng chọn Ngày bắt đầu áp dụng', 'warning')
    return false
  }
  if (!applyForm.to) {
    uiStore.showToast('Vui lòng chọn Ngày kết thúc áp dụng', 'warning')
    return false
  }
  if (applyForm.from > applyForm.to) {
    uiStore.showToast('Ngày bắt đầu áp dụng không được lớn hơn ngày kết thúc', 'warning')
    return false
  }
  if (!applyForm.code) {
    uiStore.showToast('Vui lòng chọn Loại giá (mã con)', 'warning')
    return false
  }
  if (!applyForm.days_of_week.length) {
    uiStore.showToast('Vui lòng chọn ít nhất một ngày trong tuần', 'warning')
    return false
  }
  return true
}

// ===================== FETCH =====================
const fetchRateCodes = async () => {
  loading.value = true
  try {
    const res = await http.get('/rate-codes')
    rateCodes.value = res.data.data || []
    if (rateCodes.value.length > 0) selectRateCode(rateCodes.value[0])
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Không thể tải danh sách mã giá phòng'), 'error')
  } finally {
    loading.value = false
  }
}

const fetchRoomClasses = async () => {
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (e) { console.error(e) }
}

const fetchRoomForms = async () => {
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (e) { console.error(e) }
}

const fetchRatePlans = async (code) => {
  if (!code) return
  try {
    const res = await http.get(`/rate-codes/${code}/plans`)
    ratePlans.value = res.data.data || []

    Object.keys(plansMatrix).forEach(k => delete plansMatrix[k])
    ratePlans.value.forEach(p => {
      plansMatrix[p.code] = {}
      buildMatrix(p.period, plansMatrix[p.code])
    })
  } catch (e) { console.error(e) }
}

const fetchDailies = async () => {
  if (!selectedRateCode.value || !dailyFrom.value || !dailyTo.value) return
  try {
    const res = await http.get(`/rate-codes/${selectedRateCode.value.code}/dailies`, {
      params: { from: dailyFrom.value, to: dailyTo.value }
    })
    const apiRows = res.data.data || []

    const allRows = []
    const current = new Date(dailyFrom.value)
    const end = new Date(dailyTo.value)

    while (current <= end) {
      const dateStr = current.toISOString().split('T')[0]
      const found = apiRows.find(r => r.date === dateStr)
      allRows.push({
        date: dateStr,
        code: found?.code || '',
      })
      current.setDate(current.getDate() + 1)
    }

    dailyRows.value = allRows
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Không thể tải dữ liệu giá theo ngày'), 'error')
  }
}

const handleViewDailies = () => {
  if (!selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã giá phòng', 'warning')
    return
  }
  if (!dailyFrom.value || !dailyTo.value) {
    uiStore.showToast('Vui lòng chọn khoảng ngày cần xem', 'warning')
    return
  }
  if (dailyFrom.value > dailyTo.value) {
    uiStore.showToast('Ngày bắt đầu xem không được lớn hơn ngày kết thúc', 'warning')
    return
  }
  fetchDailies()
}

const openRatePlanModal = () => {
  if (!selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã giá phòng trước khi quản lý mã con', 'warning')
    return
  }
  isRatePlanModalOpen.value = true
}

onMounted(() => {
  fetchRateCodes()
  fetchRoomClasses()
  fetchRoomForms()
  // Mặc định dailyFrom/To là tháng hiện tại
  // const now = new Date()
  // const y = now.getFullYear()
  // const m = String(now.getMonth() + 1).padStart(2, '0')
  // dailyFrom.value = `${y}-${m}-01`
  // const lastDay = new Date(y, now.getMonth() + 1, 0).getDate()
  // dailyTo.value = `${y}-${m}-${lastDay}`
  // applyForm.from = dailyFrom.value
  // applyForm.to = dailyTo.value
})

// ===================== SELECT =====================
const selectRateCode = async (rc) => {
  selectedRateCode.value = rc
  isNewMode.value = false
  syncForm(rc)
  syncingGiaTheoNgay = true
  isGiaTheoNgay.value = rc.type === 'daily'
  await nextTick()
  syncingGiaTheoNgay = false
  buildMatrix(rc.value, matrix)
  fetchRatePlans(rc.code)

  if (rc.begin_date) dailyFrom.value = rc.begin_date
  if (rc.end_date) dailyTo.value = rc.end_date

  if (isGiaTheoNgay.value) fetchDailies()
}

const syncForm = (rc) => {
  Object.assign(rateForm, {
    code: rc.code,
    description: rc.description || '',
    begin_date: rc.begin_date || '',
    end_date: rc.end_date || '',
    currency: rc.currency || 'VND',
    include_bf: !!rc.include_bf,
    disable: !!rc.disable,
    allow_change_rate: !!rc.allow_change_rate,
    is_channel_manager: !!rc.is_channel_manager,
    type: rc.type || 'fixed',
  })
}

const selectRatePlanRow = (plan) => {
  selectedRatePlanRow.value = plan
  Object.assign(ratePlanForm, {
    code: plan.code,
    description: plan.description || '',
    begin_date: plan.begin_date || '',
    end_date: plan.end_date || '',
  })
  if (!plansMatrix[plan.code]) plansMatrix[plan.code] = {}
  buildMatrix(plan.period, plansMatrix[plan.code])
}

// Mã con đang chỉnh trong modal (thêm mới hoặc sửa)
const modalPlanCode = computed(() =>
  selectedRatePlanRow.value?.code || ratePlanForm.code?.trim() || ''
)

const selectedApplyRatePlan = computed(() =>
  ratePlans.value.find(plan => plan.code === applyForm.code) || null
)

// ===================== MATRIX GIÁ =====================
const parseValueArray = (source) => {
  if (!source) return []
  if (Array.isArray(source)) return source
  if (typeof source === 'string') {
    try { return JSON.parse(source) } catch { return [] }
  }
  return []
}

const buildMatrix = (source, targetMatrix) => {
  Object.keys(targetMatrix).forEach(k => delete targetMatrix[k])
  parseValueArray(source).forEach(item => {
    if (!targetMatrix[item.RoomTypeId]) targetMatrix[item.RoomTypeId] = {}
    targetMatrix[item.RoomTypeId][item.RoomKindId] = item.Price
  })
}

const getPrice = (roomClassId, roomFormId) => {
  return matrix[roomClassId]?.[roomFormId] ?? ''
}

const getPlanPrice = (planCode, roomClassId, roomFormId) => {
  if (!planCode) return null
  return plansMatrix[planCode]?.[roomClassId]?.[roomFormId] ?? null
}

const formatCellPrice = (price) => {
  if (price === '' || price === null || price === undefined || Number(price) === 0) return ''
  return formatMoney(price)
}

const setPrice = (roomClassId, roomFormId, val) => {
  if (!matrix[roomClassId]) matrix[roomClassId] = {}
  matrix[roomClassId][roomFormId] = val === '' ? 0 : parseMoney(val) || 0
}

const setPlanPrice = (planCode, roomClassId, roomFormId, val) => {
  if (!planCode) return
  if (!plansMatrix[planCode]) plansMatrix[planCode] = {}
  if (!plansMatrix[planCode][roomClassId]) plansMatrix[planCode][roomClassId] = {}
  plansMatrix[planCode][roomClassId][roomFormId] = val === '' ? 0 : parseMoney(val)
}

// Build value array từ matrix để gửi lên API
const buildValueArray = () => {
  const arr = []
  roomClasses.value.forEach(rc => {
    roomForms.value.forEach(rf => {
      arr.push({
        RoomTypeId: rc.id,
        RoomKindId: rf.id,
        Price: matrix[rc.id]?.[rf.id] ?? 0,
      })
    })
  })
  return arr
}

const buildPlanValueArray = (planCode) => {
  const planMatrix = plansMatrix[planCode]
  if (!planMatrix) return []

  const arr = []
  roomClasses.value.forEach(rc => {
    roomForms.value.forEach(rf => {
      arr.push({
        RoomTypeId: rc.id,
        RoomKindId: rf.id,
        Price: planMatrix[rc.id]?.[rf.id] ?? 0,
      })
    })
  })
  return arr
}

// Save giá matrix (blur input)
const saveMatrix = async () => {
  if (!selectedRateCode.value) return
  try {
    await http.put(`/rate-codes/${selectedRateCode.value.id}`, {
      ...rateForm,
      value: buildValueArray(),
    })
    uiStore.showToast(`Đã lưu bảng giá cho mã ${selectedRateCode.value.code}`, 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Lỗi lưu bảng giá'), 'error')
  }
}

// ===================== THÊM / LƯU / XÓA =====================
const handleAdd = async () => {
  isNewMode.value = true
  selectedRateCode.value = null
  ratePlans.value = []
  dailyRows.value = []
  dailyFrom.value = ''
  dailyTo.value = ''

  Object.assign(applyForm, {
    from: '',
    to: '',
    code: '',
    days_of_week: [0, 1, 2, 3, 4, 5, 6]
  })

  Object.keys(matrix).forEach(k => delete matrix[k])
  syncingGiaTheoNgay = true
  isGiaTheoNgay.value = false
  await nextTick()
  syncingGiaTheoNgay = false
  const today = new Date().toISOString().split('T')[0]
  const nextYear = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
  Object.assign(rateForm, {
    code: '', description: '',
    begin_date: today, end_date: nextYear,
    currency: 'VND', include_bf: false,
    disable: false, allow_change_rate: false,
    is_channel_manager: false, type: 'fixed',
  })
}

const handleSave = async () => {
  if (!validateRateForm()) return
  loading.value = true
  try {
    const payload = { ...rateForm, value: buildValueArray() }
    if (isNewMode.value || !selectedRateCode.value) {
      const res = await http.post('/rate-codes', payload)
      rateCodes.value.push(res.data.data)
      selectRateCode(res.data.data)
      uiStore.showToast(`Thêm mã giá phòng "${rateForm.code}" thành công`, 'success')
    } else {
      const res = await http.put(`/rate-codes/${selectedRateCode.value.id}`, payload)
      const idx = rateCodes.value.findIndex(r => r.id === selectedRateCode.value.id)
      if (idx !== -1) rateCodes.value[idx] = res.data.data
      selectedRateCode.value = res.data.data
      syncForm(res.data.data)
      uiStore.showToast(`Cập nhật mã giá phòng "${rateForm.code}" thành công`, 'success')
    }
    isNewMode.value = false
  } catch (e) {
    console.error('handleSave error:', e)
    uiStore.showToast(getErrorMessage(e, 'Lỗi lưu mã giá phòng'), 'error')
  } finally {
    loading.value = false
  }
}

const handleDelete = async () => {
  if (!selectedRateCode.value) return
  const code = selectedRateCode.value.code
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc muốn xóa mã giá phòng "${code}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy',
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/rate-codes/${selectedRateCode.value.id}`)
    rateCodes.value = rateCodes.value.filter(r => r.id !== selectedRateCode.value.id)
    selectedRateCode.value = null
    ratePlans.value = []
    dailyRows.value = []
    if (rateCodes.value.length > 0) selectRateCode(rateCodes.value[0])
    uiStore.showToast(`Đã xóa mã giá phòng "${code}"`, 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Lỗi khi xóa mã giá phòng'), 'error')
  } finally {
    loading.value = false
  }
}

const saveRatePlan = async () => {
  if (!validateRatePlanForm()) return

  const planCode = ratePlanForm.code.trim()
  const rateCode = selectedRateCode.value.code
  const isCreate = !selectedRatePlanRow.value
  const payload = {
    ...ratePlanForm,
    code: planCode,
    period: buildPlanValueArray(planCode),
  }

  try {
    loading.value = true
    if (isCreate) {
      const res = await http.post(`/rate-codes/${rateCode}/plans`, payload)
      ratePlans.value.push(res.data.data)
      selectRatePlanRow(res.data.data)
      uiStore.showToast(`Thêm mã con "${planCode}" thành công`, 'success')
    } else {
      const planId = selectedRatePlanRow.value.id
      const res = await http.put(`/rate-codes/${rateCode}/plans/${planId}`, payload)
      const idx = ratePlans.value.findIndex(p => p.id === planId)
      if (idx !== -1) ratePlans.value[idx] = res.data.data
      selectRatePlanRow(res.data.data)
      uiStore.showToast(`Cập nhật mã con "${planCode}" thành công`, 'success')
    }
    await fetchRatePlans(rateCode)
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, isCreate ? 'Lỗi thêm mã con' : 'Lỗi cập nhật mã con'), 'error')
  } finally {
    loading.value = false
  }
}

const deleteRatePlan = async () => {
  if (!selectedRatePlanRow.value || !selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã con cần xóa', 'warning')
    return
  }
  const planCode = selectedRatePlanRow.value.code
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc muốn xóa mã con "${planCode}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy',
  })
  if (!confirmed) return
  try {
    await http.delete(`/rate-codes/${selectedRateCode.value.code}/plans/${selectedRatePlanRow.value.id}`)
    ratePlans.value = ratePlans.value.filter(p => p.id !== selectedRatePlanRow.value.id)
    delete plansMatrix[planCode]
    selectedRatePlanRow.value = null
    Object.assign(ratePlanForm, { code: '', description: '', begin_date: '', end_date: '' })
    uiStore.showToast(`Đã xóa mã con "${planCode}"`, 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Lỗi khi xóa mã con'), 'error')
  }
}

// ===================== APPLY DAILY =====================
const handleApply = async () => {
  if (!validateApplyForm()) return

  const selectedPlan = selectedApplyRatePlan.value
  if (!selectedPlan) {
    uiStore.showToast('Không tìm thấy mã con đã chọn. Vui lòng chọn lại Loại giá', 'warning')
    return
  }

  const effectiveFrom = applyForm.from > selectedPlan.begin_date ? applyForm.from : selectedPlan.begin_date
  const effectiveTo = applyForm.to < selectedPlan.end_date ? applyForm.to : selectedPlan.end_date

  if (effectiveFrom > effectiveTo) {
    uiStore.showToast(
      `Khoảng ngày áp dụng không nằm trong thời gian hiệu lực của mã ${selectedPlan.code} (${formatDate(selectedPlan.begin_date)} - ${formatDate(selectedPlan.end_date)})`,
      'warning'
    )
    return
  }

  loading.value = true
  try {
    const res = await http.post(`/rate-codes/${selectedRateCode.value.code}/dailies/apply`, {
      code: applyForm.code,
      from: effectiveFrom,
      to: effectiveTo,
      days_of_week: applyForm.days_of_week,
    })

    if (effectiveFrom !== applyForm.from || effectiveTo !== applyForm.to) {
      uiStore.showToast(
        `Mã ${selectedPlan.code} chỉ có hiệu lực đến ${formatDate(selectedPlan.end_date)}. Đã áp dụng từ ${formatDate(effectiveFrom)} đến ${formatDate(effectiveTo)}.`,
        'warning',
        5000
      )
    } else {
      const count = res.data?.count ?? 0
      uiStore.showToast(
        count > 0
          ? `Áp dụng mã ${applyForm.code} thành công cho ${count} ngày`
          : `Áp dụng mã ${applyForm.code} thành công`,
        'success'
      )
    }

    await fetchDailies()

    Object.assign(applyForm, {
      from: '',
      to: '',
      code: '',
      days_of_week: [0, 1, 2, 3, 4, 5, 6]
    })
  } catch (e) {
    console.error(e)
    uiStore.showToast(getErrorMessage(e, 'Lỗi áp dụng giá theo ngày'), 'error')
  } finally {
    loading.value = false
  }
}

watch(isGiaTheoNgay, async (val) => {
  rateForm.type = val ? 'daily' : 'fixed'
  if (syncingGiaTheoNgay) return

  if (val && selectedRateCode.value) fetchDailies()
  await persistRateType(val)
})

const persistRateType = async (isDaily) => {
  if (!selectedRateCode.value || isNewMode.value) return
  try {
    const res = await http.put(`/rate-codes/${selectedRateCode.value.id}`, {
      ...rateForm,
      type: isDaily ? 'daily' : 'fixed',
      value: buildValueArray(),
    })
    const idx = rateCodes.value.findIndex(r => r.id === selectedRateCode.value.id)
    if (idx !== -1) rateCodes.value[idx] = res.data.data
    selectedRateCode.value = res.data.data
    syncForm(res.data.data)
  } catch (e) {
    console.error(e)
    syncingGiaTheoNgay = true
    isGiaTheoNgay.value = !isDaily
    rateForm.type = isGiaTheoNgay.value ? 'daily' : 'fixed'
    syncingGiaTheoNgay = false
    uiStore.showToast(getErrorMessage(e, 'Lỗi lưu loại giá'), 'error')
  }
}

// ===================== FORMAT =====================
const formatDate = (d) => {
  if (!d) return ''
  const dt = new Date(d)
  return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}/${dt.getFullYear()}`
}

// Hàm format số thành dạng 1,000,000
const formatMoney = (val) => {
  if (val === '' || val === null || val === undefined) return ''
  const num = parseFloat(String(val).replace(/,/g, ''))
  if (isNaN(num)) return ''
  return new Intl.NumberFormat('en-US').format(num)
}

// Hàm parse ngược lại khi lưu
const parseMoney = (val) => {
  return parseFloat(String(val).replace(/,/g, '')) || 0
}

const getDayLabel = (dateStr) => {
  const days = ['CN','T2','T3','T4','T5','T6','T7']
  return days[new Date(dateStr).getDay()]
}

const toggleDay = (dayIndex) => {
  const idx = applyForm.days_of_week.indexOf(dayIndex)
  if (idx === -1) applyForm.days_of_week.push(dayIndex)
  else applyForm.days_of_week.splice(idx, 1)
}

const isDaySelected = (dayIndex) => applyForm.days_of_week.includes(dayIndex)

const openAddRatePlan = () => {
  if (!selectedRateCode.value) {
    uiStore.showToast('Vui lòng chọn mã giá phòng cha trước', 'warning')
    return
  }
  selectedRatePlanRow.value = null
  const today = new Date().toISOString().split('T')[0]
  const parentEnd = selectedRateCode.value?.end_date || today
  Object.assign(ratePlanForm, {
    code: '',
    description: '',
    begin_date: selectedRateCode.value?.begin_date || today,
    end_date: parentEnd,
  })
}

// Khởi tạo matrix rỗng khi nhập mã mới trong modal
watch(() => ratePlanForm.code, (code) => {
  if (selectedRatePlanRow.value) return
  const trimmed = code?.trim()
  if (!trimmed) return
  if (!plansMatrix[trimmed]) {
    plansMatrix[trimmed] = {}
    roomClasses.value.forEach(rc => {
      plansMatrix[trimmed][rc.id] = {}
      roomForms.value.forEach(rf => {
        plansMatrix[trimmed][rc.id][rf.id] = 0
      })
    })
  }
})
</script>

<template>
  <div class="flex-1 flex flex-col gap-4 text-slate-800">

    <!-- Tabs -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button
          v-for="tab in rateTabs" :key="tab"
          @click="activeRateTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeRateTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
        >{{ tab }}</button>
      </div>
    </div>

    <div class="flex-1 min-h-0 flex flex-col gap-4">

      <!-- ===== TAB 1: MÃ GIÁ PHÒNG ===== -->
      <div v-if="activeRateTab === 'Mã giá phòng'" class="flex-1 flex flex-col gap-4 min-h-0">

        <!-- Top: Bảng trái + Form phải -->
        <div class="flex flex-col lg:flex-row gap-4 shrink-0" style="height: 380px;">

          <!-- Bảng trái: danh sách rate codes -->
          <div class="w-full lg:w-[42%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
            <div class="overflow-y-auto flex-1">
              <div v-if="loading" class="text-center py-8 text-xs text-slate-400">Đang tải...</div>
              <table v-else class="w-full text-xs text-left border-collapse border border-slate-200">
                <thead>
                  <tr class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0 z-10">
                    <th class="p-2 border border-slate-200 w-16">Mã</th>
                    <th class="p-2 border border-slate-200">Mô tả</th>
                    <th class="p-2 border border-slate-200 w-14 text-center">Tiền tệ</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Từ ngày</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Đến ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="rc in rateCodes" :key="rc.id"
                    @click="selectRateCode(rc)"
                    class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors"
                    :class="selectedRateCode?.id === rc.id ? 'bg-sky-50/40 font-semibold text-sky-700' : 'text-slate-700'"
                  >
                    <td class="p-2 border border-slate-100 font-bold">{{ rc.code }}</td>
                    <td class="p-2 border border-slate-100">{{ rc.description }}</td>
                    <td class="p-2 border border-slate-100 text-center">{{ rc.currency }}</td>
                    <td class="p-2 border border-slate-100 text-center text-slate-500">{{ formatDate(rc.begin_date) }}</td>
                    <td class="p-2 border border-slate-100 text-center text-slate-500">{{ formatDate(rc.end_date) }}</td>
                  </tr>
                  <tr v-if="rateCodes.length === 0">
                    <td colspan="5" class="p-6 text-center text-slate-400">Chưa có dữ liệu</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Form phải -->
          <div class="w-full lg:w-[58%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
            <!-- Header buttons -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
              <span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Chi tiết thiết lập giá</span>
              <div class="flex items-center gap-1.5">
                <button @click="handleAdd" class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1">
                  <span class="text-sm">+</span> Thêm
                </button>
                <button @click="handleSave" :disabled="loading" class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer disabled:opacity-50">
                  Lưu
                </button>
                <button @click="handleDelete" :disabled="!selectedRateCode || loading" class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer disabled:opacity-40">
                  Xóa
                </button>
              </div>
            </div>

            <!-- Fields -->
            <div class="flex flex-col gap-3 py-3 overflow-y-auto flex-1">
              <!-- Row 1: Mã + Mô tả -->
              <div class="flex gap-3">
                <div class="w-1/4">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Mã</label>
                  <input type="text" v-model="rateForm.code" :disabled="!isNewMode && !!selectedRateCode"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold disabled:bg-slate-50" />
                </div>
                <div class="flex-1">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Mô tả</label>
                  <input type="text" v-model="rateForm.description"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" />
                </div>
              </div>

              <!-- Row 2: Từ ngày ~ Đến ngày + Tiền tệ + Ăn sáng -->
              <div class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-[120px]">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Từ ngày - đến ngày</label>
                  <div class="flex items-center gap-1">
                    <input type="date" :min="today" v-model="rateForm.begin_date"
                      class="flex-1 px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" />
                    <span class="text-slate-400 text-xs">~</span>
                    <input type="date" :min="today" v-model="rateForm.end_date"
                      class="flex-1 px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" />
                  </div>
                </div>
                <div class="w-24">
                  <label class="block text-xs font-bold text-slate-500 mb-1">Tiền tệ</label>
                  <select v-model="rateForm.currency"
                    class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold bg-white">
                    <option value="VND">🇻🇳 VND</option>
                    <option value="USD">🇺🇸 USD</option>
                  </select>
                </div>
                <div class="pb-2">
                  <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                    <input type="checkbox" v-model="rateForm.include_bf" class="rounded border-slate-300 text-sky-500" />
                    Ăn sáng
                  </label>
                </div>
              </div>

              <!-- Row 3: Checkboxes -->
              <div class="flex flex-wrap gap-x-5 gap-y-2 pt-2 border-t border-slate-100">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                  <input type="checkbox" v-model="isGiaTheoNgay" class="rounded border-slate-300 text-sky-500" />
                  Giá theo ngày
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                  <input type="checkbox" v-model="rateForm.disable" class="rounded border-slate-300 text-sky-500" />
                  Không sử dụng
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                  <input type="checkbox" v-model="rateForm.allow_change_rate" class="rounded border-slate-300 text-sky-500" />
                  Cho phép nhập giá
                </label>
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer">
                  <input type="checkbox" v-model="rateForm.is_channel_manager" class="rounded border-slate-300 text-sky-500" />
                  Đẩy lên Channel Manager
                </label>
              </div>

              <!-- Row 4: Apply form (chỉ hiện khi Giá theo ngày) -->
              <div v-if="isGiaTheoNgay" class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 items-end">
                <div>
                  <label class="block text-xs font-bold text-slate-500 mb-1">Ngày áp dụng</label>
                  <div class="flex items-center gap-1">
                    <input type="date" :min="today" v-model="applyForm.from"
                      class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" />
                    <span class="text-slate-400 text-xs">~</span>
                    <input type="date" :min="today" v-model="applyForm.to"
                      class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold" />
                  </div>
                </div>
                <!-- Checkbox ngày trong tuần -->
                <div class="flex items-center gap-1 flex-wrap">
                  <button
                    v-for="(label, idx) in dayLabels" :key="idx"
                    @click="toggleDay(idx)"
                    class="px-2 py-1 rounded text-xs font-bold border cursor-pointer"
                    :class="isDaySelected(idx) ? 'bg-sky-500 text-white border-sky-500' : 'bg-white text-slate-500 border-slate-200'"
                  >{{ label }}</button>
                </div>
                <!-- Loại giá (mã con) -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 mb-1">Loại giá</label>
                  <div class="flex items-center gap-1">
                    <select v-model="applyForm.code"
                      class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold bg-white min-w-[100px]">
                      <option value="">Chọn mã</option>
                      <option v-for="p in ratePlans" :key="p.id" :value="p.code">{{ p.code }}</option>
                    </select>
                    <button @click="openRatePlanModal"
                      class="w-7 h-7 flex items-center justify-center bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-bold border-none cursor-pointer">
                      +
                    </button>
                  </div>
                </div>
                <button @click="handleApply" :disabled="loading"
                  class="px-4 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer disabled:opacity-50">
                  Áp dụng
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom: Grid giá tĩnh HOẶC Bảng theo ngày -->

        <!-- BẢNG THEO NGÀY (Giá theo ngày = true) -->
        <div v-if="isGiaTheoNgay" class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col shadow-xs" style="height: 420px;">
          <!-- Filter ngày xem -->
          <div class="flex items-center gap-3 pb-3 border-b border-slate-100 shrink-0">
            <span class="text-xs font-bold text-slate-500">Xem từ</span>
            <input type="date" :min="today" v-model="dailyFrom" class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold" />
            <span class="text-xs text-slate-400">~</span>
            <input type="date" :min="today" v-model="dailyTo" class="px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold" />
            <button @click="handleViewDailies" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-bold border-none cursor-pointer">
              Xem
            </button>
          </div>
          <div class="flex-1 overflow-auto mt-3">
            <table class="text-xs text-left border-collapse border border-slate-200" style="min-width: max-content;">
              <thead>
                <tr class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2 border border-slate-200 w-24 sticky left-0 bg-slate-50">Ngày</th>
                  <th class="p-2 border border-slate-200 w-16 sticky left-24 bg-slate-50">Mã</th>
                  <template v-for="rc in roomClasses" :key="rc.id">
                    <th :colspan="roomForms.length" class="p-2 border border-slate-200 text-center">{{ rc.code }}</th>
                  </template>
                </tr>
                <tr class="bg-slate-50 text-slate-500 sticky top-7 z-10">
                  <th class="p-2 border border-slate-200 sticky left-0 bg-slate-50"></th>
                  <th class="p-2 border border-slate-200 sticky left-24 bg-slate-50"></th>
                  <template v-for="rc in roomClasses" :key="rc.id">
                    <th v-for="rf in roomForms" :key="rf.id" class="p-2 border border-slate-200 w-24 text-center font-semibold">
                      {{ rf.name }}
                    </th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in dailyRows" :key="row.date + row.code" class="hover:bg-slate-50/40">
                  <td class="p-2 border border-slate-200 font-mono sticky left-0 bg-white">
                    {{ formatDate(row.date) }}
                    <span class="ml-1 text-sky-500 font-bold">{{ getDayLabel(row.date) }}</span>
                  </td>
                  <td class="p-2 border border-slate-200 font-bold text-sky-700 sticky left-24 bg-white">{{ row.code }}</td>
                  <template v-for="rc in roomClasses" :key="rc.id">
                    <td v-for="rf in roomForms" :key="rf.id" class="p-1 border border-slate-200 text-center text-slate-600">
                      {{ formatCellPrice(getPlanPrice(row.code, rc.id, rf.id)) }}
                    </td>
                  </template>
                </tr>
                <tr v-if="dailyRows.length === 0">
                  <td :colspan="2 + roomClasses.length * roomForms.length" class="p-8 text-center text-slate-400">
                    Không có dữ liệu
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- GRID GIÁ TĨNH (Giá theo ngày = false) -->
        <div v-else class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
          <div class="flex-1 overflow-auto">
            <table class="w-full text-xs text-left border-collapse border border-slate-200" style="min-width: 700px;">
              <thead>
                <tr class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2.5 border border-slate-200 w-28">Loại phòng</th>
                  <th class="p-2.5 border border-slate-200 w-44">Mô tả</th>
                  <th v-for="rf in roomForms" :key="rf.id" class="p-2.5 border border-slate-200 text-center w-28">
                    {{ rf.name }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rc in roomClasses" :key="rc.id" class="hover:bg-slate-50/40">
                  <td class="p-2.5 font-bold text-slate-700 border border-slate-200 bg-slate-50/20">{{ rc.code }}</td>
                  <td class="p-2.5 text-slate-500 font-medium border border-slate-200">{{ rc.name }}</td>
                  <td v-for="rf in roomForms" :key="rf.id" class="p-1 border border-slate-200">
                    <input
                      type="text"
                      :value="formatCellPrice(getPrice(rc.id, rf.id))"
                      @input="setPrice(rc.id, rf.id, $event.target.value)"
                      @blur="(e) => { e.target.value = formatMoney(parseMoney(e.target.value)); saveMatrix() }"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white transition-colors"
                    />
                  </td>
                </tr>
                <tr v-if="roomClasses.length === 0">
                  <td colspan="10" class="p-6 text-center text-slate-400">Chưa có loại phòng nào</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ===== TAB 2: GÓI DỊCH VỤ (tạm giữ mock) ===== -->
      <div v-else-if="activeRateTab === 'Gói dịch vụ'" class="flex-1 flex items-center justify-center text-slate-400 text-sm">
        Gói dịch vụ — chưa triển khai
      </div>

    </div>
  </div>

  <!-- ===== MODAL: Chọn mã loại giá ===== -->
  <div v-if="isRatePlanModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl mx-4 overflow-hidden" style="max-height: 85vh;">
      <!-- Header -->
      <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
        <span>Mã con — {{ selectedRateCode?.code }}</span>
        <button @click="isRatePlanModalOpen = false" class="text-white bg-transparent border-none cursor-pointer text-xl">&times;</button>
      </div>

      <!-- Body -->
      <div class="p-4 flex flex-col gap-4 overflow-y-auto" style="max-height: calc(85vh - 60px);">
        <!-- Top: bảng trái + form phải -->
        <div class="flex gap-4" style="height: 280px;">
          <!-- Bảng danh sách mã con -->
          <div class="w-[45%] border border-slate-200 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-y-auto flex-1">
              <table class="w-full text-xs text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0">
                    <th class="p-2.5 border-b border-slate-200 w-20">Mã</th>
                    <th class="p-2.5 border-b border-slate-200">Mô tả</th>
                    <th class="p-2.5 border-b border-slate-200 w-24 text-center">Từ ngày</th>
                    <th class="p-2.5 border-b border-slate-200 w-24 text-center">Đến ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="plan in ratePlans" :key="plan.id"
                    @click="selectRatePlanRow(plan)"
                    class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-colors"
                    :class="selectedRatePlanRow?.id === plan.id ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-700'"
                  >
                    <td class="p-2.5 font-bold">{{ plan.code }}</td>
                    <td class="p-2.5">{{ plan.description }}</td>
                    <td class="p-2.5 text-center text-slate-500">{{ formatDate(plan.begin_date) }}</td>
                    <td class="p-2.5 text-center text-slate-500">{{ formatDate(plan.end_date) }}</td>
                  </tr>
                  <tr v-if="ratePlans.length === 0">
                    <td colspan="4" class="p-6 text-center text-slate-400">Chưa có mã con nào</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Form phải -->
          <div class="flex-1 border border-slate-200 rounded-xl p-4 flex flex-col gap-3">
            <!-- Buttons -->
            <div class="flex items-center justify-end gap-2 pb-3 border-b border-slate-100">
              <button @click="openAddRatePlan"
                class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer">
                + Thêm
              </button>
              <button @click="saveRatePlan"
                class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer">
                Lưu
              </button>
              <button @click="deleteRatePlan" :disabled="!selectedRatePlanRow"
                class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer disabled:opacity-40">
                Xóa
              </button>
            </div>

            <!-- Fields -->
            <div class="flex gap-3">
              <div class="w-1/3">
                <label class="block text-xs font-bold text-slate-500 mb-1">Mã</label>
                <input type="text" v-model="ratePlanForm.code" :disabled="!!selectedRatePlanRow"
                  class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold disabled:bg-slate-50" />
              </div>
              <div class="flex-1">
                <label class="block text-xs font-bold text-slate-500 mb-1">Mô tả</label>
                <input type="text" v-model="ratePlanForm.description"
                  class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-500 mb-1">Từ ngày - đến ngày</label>
              <div class="flex items-center gap-2">
                <input type="date" :min="today" v-model="ratePlanForm.begin_date"
                  class="flex-1 px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold" />
                <span class="text-slate-400 text-xs">~</span>
                <input type="date" :min="today" v-model="ratePlanForm.end_date"
                  class="flex-1 px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold" />
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom: grid giá -->
        <div class="border border-slate-200 rounded-xl overflow-auto" style="max-height: 300px;">
          <table class="w-full text-xs text-left border-collapse" style="min-width: 600px;">
            <thead>
              <tr class="bg-slate-50 text-slate-600 font-bold uppercase sticky top-0">
                <th class="p-2.5 border-b border-slate-200 w-28">Loại phòng</th>
                <th class="p-2.5 border-b border-slate-200 w-40">Mô tả</th>
                <th v-for="rf in roomForms" :key="rf.id" class="p-2.5 border-b border-slate-200 text-center w-24">
                  {{ rf.name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rc in roomClasses" :key="rc.id" class="hover:bg-slate-50/40">
                <td class="p-2.5 font-bold text-slate-700 border-b border-slate-100 bg-slate-50/20">{{ rc.code }}</td>
                <td class="p-2.5 text-slate-500 border-b border-slate-100">{{ rc.name }}</td>
                <td v-for="rf in roomForms" :key="rf.id" class="p-1 border-b border-slate-100">
                  <input
                    type="text"
                    :disabled="!modalPlanCode"
                    :value="modalPlanCode ? formatCellPrice(getPlanPrice(modalPlanCode, rc.id, rf.id)) : ''"
                    @input="setPlanPrice(modalPlanCode, rc.id, rf.id, $event.target.value)"
                    @blur="(e) => { e.target.value = formatMoney(parseMoney(e.target.value)) }"
                    placeholder="-"
                    class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white disabled:bg-slate-50"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-delete { background-color: #64748b !important; color: #fff !important; border: none !important; }
.btn-delete:hover { background-color: #475569 !important; }
.btn-delete:disabled { opacity: 0.4; cursor: not-allowed; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>