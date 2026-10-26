<script setup>
import { ref, reactive, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { normalizeRateMatrix } from '@/utils/rate-matrix'

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')

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
    if (showRatePlanModal.value) closeRatePlanModal()
    else if (showCurrencyModal.value) showCurrencyModal.value = false
  }
}

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Mã Giá Phòng'
  }
})

const emit = defineEmits(['update:activeTab'])

const activeRateTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  if (newVal) {
    activeRateTab.value = newVal
  }
})

watch(activeRateTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const rateTabs = ['Mã Giá Phòng', 'Gói Dịch Vụ']

const isTabActive = (tabName) => {
  if (!activeRateTab.value) return false
  const cur = String(activeRateTab.value).trim().toUpperCase()
  const target = String(tabName).trim().toUpperCase()
  return cur === target
}

// --- MOCK DATA FOR TABS ---

// 1. Tab Mã giá phòng: Rate Codes List
const rateCodes = ref([])
const selectedRateCode = ref(null)

const fetchRoomData = async () => {
  try {
    const [classesRes, formsRes] = await Promise.all([
      http.get('/room-classes'),
      http.get('/room-forms')
    ])
    if (classesRes.status === 200) {
      roomTypes.value = classesRes.data.data
        .filter(item => item.is_active !== false && item.is_active !== 0 && item.is_active !== '0')
        .map(item => ({
        code: item.code,
        description: item.name
      }))
    }
    if (formsRes.status === 200) {
      occupancies.value = formsRes.data.data.map(item => item.name)
    }
  } catch (e) {
    console.error(e)
  }
}

const fetchRateCodes = async (preventAutoSelect = false) => {
  try {
    const res = await http.get('/room-rate-codes')
    if (res.status === 200) {
      rateCodes.value = res.data.data || []
      if (rateCodes.value.length > 0 && !selectedRateCode.value && !preventAutoSelect) {
        selectedRateCode.value = rateCodes.value[0]
      }
    }
  } catch (e) {
    console.error(e)
  }
}

const systemDate = ref('')

const fetchSystemDate = async () => {
  try {
    const res = await http.get('/system-date')
    if (res.data?.data?.system_date) {
      systemDate.value = res.data.data.system_date
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(async () => {
  window.addEventListener('keydown', handleKeyDown)
  await Promise.all([fetchRoomData(), fetchSystemDate()])
  await fetchRateCodes()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})

// Selected Rate Code Form Fields
const rateFormState = reactive({
  Ma: '',
  Description: '',
  BeginDate: '',
  EndDate: '',
  Currency: 'VND',
  IsDaily: false, // Using this as UI toggle flag
  Disable: false,
  AllowChangeRate: false,
  IsChannelManager: false,
  IncludeBF: false
})

const rateMatrix = reactive({})

// --- CURRENCY MODAL ---
const showCurrencyModal = ref(false)
const currencySearch = ref('')
const currencyList = [
  { code: 'VND', name: 'Vietnamese Dong', symbol: '₫', flag: '🇻🇳' },
  { code: 'CNY', name: 'Chinese Yuan', symbol: '¥', flag: '🇨🇳' },
  { code: 'RUB', name: 'Russian Ruble', symbol: '₽', flag: '🇷🇺' },
  { code: 'USD', name: 'United States Dollar', symbol: '$', flag: '🇺🇸' },
  { code: 'THB', name: 'Thai Baht', symbol: '฿', flag: '🇹🇭' },
  { code: 'KHR', name: 'Cambodian Riel', symbol: '៛', flag: '🇰🇭' },
  { code: 'EUR', name: 'Euro', symbol: '€', flag: '🇪🇺' },
  { code: 'GBP', name: 'British Pound', symbol: '£', flag: '🇬🇧' },
  { code: 'JPY', name: 'Japanese Yen', symbol: '¥', flag: '🇯🇵' },
  { code: 'KRW', name: 'South Korean Won', symbol: '₩', flag: '🇰🇷' },
  { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$', flag: '🇸🇬' },
  { code: 'AUD', name: 'Australian Dollar', symbol: 'A$', flag: '🇦🇺' },
]

const filteredCurrencies = computed(() => {
  if (!currencySearch.value) return currencyList;
  const q = currencySearch.value.toLowerCase();
  return currencyList.filter(c => c.code.toLowerCase().includes(q) || c.name.toLowerCase().includes(q));
})

const selectedCurrencyObj = computed(() => {
  return currencyList.find(c => c.code === rateFormState.Currency) || currencyList[0];
})

const selectCurrency = (code) => {
  rateFormState.Currency = code;
  showCurrencyModal.value = false;
  currencySearch.value = '';
}

// --- DAILY MAPPING STATE ---
const batchFromDate = ref('')
const batchToDate = ref('')
const batchRateType = ref('')
const batchDaysOfWeek = reactive({
  1: true, // Thứ 2
  2: true, // Thứ 3
  3: true, // Thứ 4
  4: true, // Thứ 5
  5: true, // Thứ 6
  6: true, // Thứ 7
  0: true  // Chủ nhật
})
const dailyMappingsList = ref([])
const isSavingDailyMappings = ref(false)

const markDailyMappingsPersisted = () => {
  try {
    const initial = JSON.parse(initialRateStateString.value || '{}')
    initial.daily = JSON.parse(JSON.stringify(dailyMappingsList.value))
    initialRateStateString.value = JSON.stringify(initial)
  } catch {
    // The full save flow will reset the dirty-state snapshot.
  }
}

const persistDailyMappingChanges = async ({ mappings = [], deleteDates = [], mode = 'merge' } = {}) => {
  if (!rateFormState.Ma || !rateCodes.value.some(rateCode => rateCode.Ma === rateFormState.Ma)) {
    uiStore.showToast('Vui lòng lưu Mã giá phòng trước khi thiết lập giá theo ngày.', 'warning')
    return false
  }

  isSavingDailyMappings.value = true
  try {
    const res = await http.post(`/room-rate-codes/${rateFormState.Ma}/daily-mappings`, {
      mode,
      mappings,
      delete_dates: deleteDates,
    })
    dailyMappingsList.value = JSON.parse(JSON.stringify(res.data?.data || []))

    const savedRateCode = rateCodes.value.find(rateCode => rateCode.Ma === rateFormState.Ma)
    if (savedRateCode) savedRateCode.daily_mappings = JSON.parse(JSON.stringify(dailyMappingsList.value))
    if (selectedRateCode.value?.Ma === rateFormState.Ma) {
      selectedRateCode.value.daily_mappings = JSON.parse(JSON.stringify(dailyMappingsList.value))
    }
    markDailyMappingsPersisted()
    return true
  } catch (error) {
    console.error(error)
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu giá theo ngày.', 'error')
    return false
  } finally {
    isSavingDailyMappings.value = false
  }
}

const occupancies = ref([])
const defaultOccupancies = ['Double', 'Twin', 'Triple', 'Family', 'King']
const occupanciesList = computed(() => {
  return occupancies.value.length > 0 ? occupancies.value : defaultOccupancies
})

const availableRatePlans = computed(() => {
  const currentRc = rateCodes.value.find(r => r.Ma === rateFormState.Ma) || selectedRateCode.value;
  if (!currentRc || !currentRc.rate_plans) return [];
  return currentRc.rate_plans
    .filter(p => p.Code !== 'DEFAULT')
    .map(p => ({
      Code: p.Code,
      Description: p.Description,
      BeginDate: p.BeginDate,
      EndDate: p.EndDate
    }));
})

const selectedBatchPlan = computed(() => {
  if (!batchRateType.value) return null;
  return availableRatePlans.value.find(p => p.Code === batchRateType.value) || null;
})

// --- RATE PLAN MODAL STATE ---
const showRatePlanModal = ref(false)
const modalRatePlans = ref([])
const modalFormState = reactive({
  Code: '',
  Description: '',
  BeginDate: '',
  EndDate: ''
})
const modalRateMatrix = reactive({})

const initialModalStateString = ref('');

const isModalDirty = computed(() => {
  if (!modalFormState.Code) return false;
  const current = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
  return current !== initialModalStateString.value;
});

const closeRatePlanModal = async () => {
  if ((isModalEditing.value || modalFormState.Code) && isModalDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn đóng bảng không?' });
    if (!confirmed) return;
  }
  showRatePlanModal.value = false;
}

const openRatePlanModal = async () => {
  if (!selectedRateCode.value) {
    if (!rateFormState.Ma) {
      uiStore.showToast('Vui lòng nhập Mã và Lưu giá phòng trước khi thêm loại giá.', 'warning');
      return;
    }

    const saved = await handleSave();
    if (!saved || !selectedRateCode.value) return;
  }
  if (roomTypes.value.length === 0) {
    await fetchRoomData()
  }
  modalPos.value = { x: 0, y: 0 }
  showRatePlanModal.value = true;
  const plans = selectedRateCode.value.rate_plans || [];
  modalRatePlans.value = JSON.parse(JSON.stringify(plans.filter(p => p.Code !== 'DEFAULT')));

  Object.assign(modalFormState, { Code: '', Description: '', BeginDate: '', EndDate: '' });
  for (const key in modalRateMatrix) delete modalRateMatrix[key];
  isModalEditing.value = false;
  
  if (modalRatePlans.value.length > 0) {
    await selectModalPlan(modalRatePlans.value[0]);
  } else {
    initialModalStateString.value = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
  }
}

const formatCurrencyInput = (val, currency) => {
  if (val === null || val === undefined || val === '') return '';
  let clean = String(val).replace(/[^\d.,]/g, '');
  if (!clean) return '';

  if (currency === 'VND') {
    clean = clean.replace(/\D/g, '');
    if (!clean) return '';
    return Number(clean).toLocaleString('en-US');
  } else {
    clean = clean.replace(/,/g, '');
    let parts = clean.split('.');
    if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }
}

const cleanCurrencyValue = (val, currency) => {
  if (val === null || val === undefined || val === '') return '';
  let clean = String(val).replace(/[^\d.,]/g, '');
  if (currency === 'VND') {
    return clean.replace(/\D/g, '');
  } else {
    return clean.replace(/,/g, '');
  }
}

const resetModalForm = async () => {
  if ((isModalEditing.value || modalFormState.Code) && isModalDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn tạo mới không?' });
    if (!confirmed) return;
  }
  Object.assign(modalFormState, { Code: '', Description: '', BeginDate: '', EndDate: '' });
  for (const key in modalRateMatrix) delete modalRateMatrix[key];
  isModalEditing.value = false;
  initialModalStateString.value = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
}

const selectModalPlan = async (plan) => {
  if (isModalEditing.value && isModalDirty.value && modalFormState.Code !== plan.Code) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn chọn loại giá khác?' });
    if (!confirmed) return;
  }
  Object.assign(modalFormState, {
    Code: plan.Code || '',
    Description: plan.Description || '',
    BeginDate: plan.BeginDate || '',
    EndDate: plan.EndDate || ''
  });
  for (const key in modalRateMatrix) delete modalRateMatrix[key];
  if (plan.Period) {
    try {
      const parsed = typeof plan.Period === 'string' ? JSON.parse(plan.Period) : plan.Period;
      const normalized = normalizeRateMatrix(parsed, plan.Code, roomTypes.value, occupanciesList.value);
      for (const key in normalized) {
        modalRateMatrix[key] = normalized[key];
      }
    } catch (e) { }
  }
  isModalEditing.value = true;
  initialModalStateString.value = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
}

const isModalEditing = ref(false);

const getModalMatrixKey = (roomCode, occupancy) => {
  return `${modalFormState.Code || 'NEW'}_${roomCode}_${occupancy}`
}

const saveModalPlan = async () => {
  if (!modalFormState.Code) {
    uiStore.showToast('Vui lòng nhập Mã loại giá', 'warning');
    return;
  }
  if (modalFormState.BeginDate && modalFormState.EndDate && modalFormState.EndDate < modalFormState.BeginDate) {
    uiStore.showToast('Ngày kết thúc phải lớn hơn hoặc bằng Ngày bắt đầu!', 'warning');
    return;
  }
  if (modalFormState.Code.toUpperCase() === 'DEFAULT') {
    uiStore.showToast('Mã loại giá DEFAULT được hệ thống dành riêng, vui lòng chọn mã khác.', 'warning');
    return;
  }

  if (!isModalEditing.value && modalRatePlans.value.some(p => p.Code === modalFormState.Code)) {
    uiStore.showToast('Mã loại giá này đã tồn tại, vui lòng nhập mã khác.', 'warning');
    return;
  }

  if (isModalEditing.value) {
    const confirmed = await uiStore.confirm({ message: 'Bạn có chắc chắn muốn cập nhật loại giá này không?' });
    if (!confirmed) return;
  }

  try {
    const normalizedPeriod = normalizeRateMatrix(
      modalRateMatrix,
      modalFormState.Code,
      roomTypes.value,
      occupanciesList.value
    );
    const res = await http.post(`/room-rate-codes/${selectedRateCode.value.Ma}/plans`, {
      ...modalFormState,
      Period: normalizedPeriod
    });
    if (res.status === 200 || res.status === 201) {
      uiStore.showToast('Lưu loại giá thành công!', 'success');
      await fetchRateCodes(); // Refresh to get updated plans
      const updatedCode = rateCodes.value.find(r => r.Ma === selectedRateCode.value.Ma);
      if (updatedCode) {
        selectedRateCode.value.rate_plans = updatedCode.rate_plans;
        loadRateMatrixFromPlans(selectedRateCode.value.Ma, updatedCode.rate_plans);
        const plans = updatedCode.rate_plans || [];
        modalRatePlans.value = JSON.parse(JSON.stringify(plans.filter(p => p.Code !== 'DEFAULT')));

        // Update state string after save
        initialModalStateString.value = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
      }
    } else {
      uiStore.showToast('Lỗi lưu loại giá', 'error');
    }
  } catch (e) {
    console.error(e);
    uiStore.showToast(e.response?.data?.message || 'Không thể lưu loại giá.', 'error');
  }
}

const deletePlanItem = async (plan) => {
  const code = plan?.Code || modalFormState.Code
  if (!code) return
  if (code.toUpperCase() === 'DEFAULT') {
    uiStore.showToast('Không thể xóa loại giá mặc định (DEFAULT)!', 'warning')
    return
  }

  // 1. Kiểm tra xem loại giá có đang được áp dụng trong bảng giá theo ngày của mã giá hiện tại
  const currentMappings = selectedRateCode.value?.daily_mappings || dailyMappingsList.value || []
  if (currentMappings.some(m => m.Code === code)) {
    uiStore.showToast(`Loại giá "${code}" đang được áp dụng trong bảng giá theo ngày. Không thể xóa!`, 'error')
    return
  }

  // 2. Kiểm tra các mã giá khác xem có mã giá nào đang gán loại giá này vào giá theo ngày không
  const isUsedElsewhere = rateCodes.value.some(rc => {
    const rcMappings = rc.daily_mappings || []
    return rcMappings.some(m => m.Code === code)
  })
  if (isUsedElsewhere) {
    uiStore.showToast(`Loại giá "${code}" đang được sử dụng ở mã giá phòng khác. Không thể xóa!`, 'error')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc chắn muốn xóa loại giá ${code}?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    const res = await http.delete(`/room-rate-codes/${selectedRateCode.value.Ma}/plans/${code}`)
    if (res.status === 200) {
      uiStore.showToast('Xóa loại giá thành công!', 'success')
      if (modalFormState.Code === code) {
        resetModalForm()
      }
      await fetchRateCodes()
      const updatedCode = rateCodes.value.find(r => r.Ma === selectedRateCode.value.Ma)
      if (updatedCode) {
        selectedRateCode.value.rate_plans = updatedCode.rate_plans
        loadRateMatrixFromPlans(selectedRateCode.value.Ma, updatedCode.rate_plans)
        const plans = updatedCode.rate_plans || []
        modalRatePlans.value = JSON.parse(JSON.stringify(plans.filter(p => p.Code !== 'DEFAULT')))
      }
    }
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể xóa loại giá này', 'error')
  }
}

const deleteModalPlan = async () => {
  if (!modalFormState.Code) return
  await deletePlanItem({ Code: modalFormState.Code })
}

const loadRateMatrixFromPlans = (rateCodeMa, plans) => {
  for (const key in rateMatrix) delete rateMatrix[key];
  if (!plans?.length) return;

  plans.forEach(plan => {
    if (!plan.Period) return;
    try {
      const parsed = typeof plan.Period === 'string' ? JSON.parse(plan.Period) : plan.Period;
      const normalized = normalizeRateMatrix(parsed, plan.Code, roomTypes.value, occupanciesList.value);
      for (const key in normalized) {
        rateMatrix[key] = normalized[key];
      }
    } catch (e) { }
  });
}

const getDayLabel = (dayOfWeek) => {
  const labels = { 0: 'CN', 1: 'T2', 2: 'T3', 3: 'T4', 4: 'T5', 5: 'T6', 6: 'T7' };
  return labels[dayOfWeek] || '';
}

const getDayOfWeekFromDate = (dateStr) => {
  return new Date(dateStr).getDay();
}

const formatDateVN = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}

const getTodayString = () => {
  if (systemDate.value) {
    return systemDate.value
  }
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const isEditing = computed(() => {
  return rateCodes.value.some(r => r.Ma === rateFormState.Ma);
})

const setDefaultBatchDates = () => {
  const sysDate = getTodayString()
  const plan = selectedBatchPlan.value
  if (plan && plan.BeginDate && plan.EndDate) {
    batchFromDate.value = plan.BeginDate >= sysDate ? plan.BeginDate : sysDate
    batchToDate.value = plan.EndDate
    return
  }
  const begin = rateFormState.BeginDate
  const end = rateFormState.EndDate

  if (begin && end) {
    batchFromDate.value = begin >= sysDate ? begin : sysDate
    batchToDate.value = end
  } else if (begin) {
    batchFromDate.value = begin >= sysDate ? begin : sysDate
    batchToDate.value = end || ''
  } else {
    batchFromDate.value = sysDate
    batchToDate.value = ''
  }
}

watch(batchRateType, (newCode) => {
  if (!newCode) return
  const plan = availableRatePlans.value.find(p => p.Code === newCode)
  if (plan && plan.BeginDate && plan.EndDate) {
    const sysDate = getTodayString()
    batchFromDate.value = plan.BeginDate >= sysDate ? plan.BeginDate : sysDate
    batchToDate.value = plan.EndDate
  } else if (plan && plan.BeginDate) {
    const sysDate = getTodayString()
    batchFromDate.value = plan.BeginDate >= sysDate ? plan.BeginDate : sysDate
    batchToDate.value = rateFormState.EndDate || ''
  } else {
    setDefaultBatchDates()
  }
})

const applyBatchUpdate = async () => {
  if (availableRatePlans.value.length === 0) {
    uiStore.showToast('Bạn chưa thiết lập loại giá nào. Vui lòng bấm dấu [+] ở Loại giá để thiết lập thêm loại giá.', 'warning');
    return;
  }
  if (!batchRateType.value) {
    uiStore.showToast('Vui lòng chọn Loại giá', 'warning');
    return;
  }
  if (!batchFromDate.value || !batchToDate.value) {
    uiStore.showToast('Vui lòng chọn ngày cập nhật thông tin', 'warning');
    return;
  }

  const selectedPlan = selectedBatchPlan.value;
  if (selectedPlan) {
    if (selectedPlan.BeginDate && batchFromDate.value < selectedPlan.BeginDate) {
      uiStore.showToast(`Từ ngày áp dụng (${formatDateVN(batchFromDate.value)}) không được nhỏ hơn Ngày bắt đầu của loại giá (${formatDateVN(selectedPlan.BeginDate)})!`, 'warning');
      return;
    }
    if (selectedPlan.EndDate && batchToDate.value > selectedPlan.EndDate) {
      uiStore.showToast(`Đến ngày áp dụng (${formatDateVN(batchToDate.value)}) không được lớn hơn Ngày kết thúc của loại giá (${formatDateVN(selectedPlan.EndDate)})!`, 'warning');
      return;
    }
  }

  const sysDateStr = getTodayString();
  if (batchFromDate.value < sysDateStr) {
    uiStore.showToast(`Ngày áp dụng từ (${formatDateVN(batchFromDate.value)}) không được nhỏ hơn Ngày hệ thống (${formatDateVN(sysDateStr)})!`, 'warning');
    return;
  }

  const from = new Date(batchFromDate.value);
  const to = new Date(batchToDate.value);
  if (from > to) {
    uiStore.showToast('Từ ngày không được lớn hơn Đến ngày', 'warning');
    return;
  }

  const confirmed = await uiStore.confirm({ message: 'Bạn có chắc chắn muốn áp dụng thông tin giá theo ngày không?' });
  if (!confirmed) return;

  const previousMappings = JSON.parse(JSON.stringify(dailyMappingsList.value));
  const changedMappings = [];
  let currentDate = new Date(from);
  while (currentDate <= to) {
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    const day = String(currentDate.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;
    const dayOfWeek = currentDate.getDay();

    const existingIdx = dailyMappingsList.value.findIndex(m => m.Date === dateStr);

    if (batchDaysOfWeek[dayOfWeek]) {
      if (existingIdx !== -1) {
        dailyMappingsList.value[existingIdx].Code = batchRateType.value;
      } else {
        dailyMappingsList.value.push({ Date: dateStr, Code: batchRateType.value });
      }
      changedMappings.push({ Date: dateStr, Code: batchRateType.value });
    }
    currentDate.setDate(currentDate.getDate() + 1);
  }

  dailyMappingsList.value.sort((a, b) => a.Date.localeCompare(b.Date));
  dailyMappingsList.value = dailyMappingsList.value.map(m => ({ ...m }));

  if (changedMappings.length === 0) {
    uiStore.showToast('Không có ngày nào phù hợp để áp dụng.', 'warning');
    return;
  }

  const saved = await persistDailyMappingChanges({ mappings: changedMappings, mode: 'merge' });
  if (!saved) {
    dailyMappingsList.value = previousMappings;
    return;
  }

  uiStore.showToast('Áp dụng và lưu loại giá theo ngày thành công!', 'success');

  if (batchFromDate.value) {
    scrollToDailyDate(batchFromDate.value);
  }
}

const displayedDailyMappings = computed(() => {
  if (!rateFormState.BeginDate || !rateFormState.EndDate) {
    return [];
  }
  const from = new Date(rateFormState.BeginDate);
  const to = new Date(rateFormState.EndDate);
  if (from > to) return [];

  const mappingMap = new Map();
  dailyMappingsList.value.forEach(m => {
    if (m.Date) {
      mappingMap.set(m.Date, m);
    }
  });

  const list = [];
  let currentDate = new Date(from.getTime());
  let safetyCounter = 0;
  while (currentDate <= to && safetyCounter < 10000) {
    safetyCounter++;
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    const day = String(currentDate.getDate()).padStart(2, '0');
    const dateStr = `${year}-${month}-${day}`;

    const existing = mappingMap.get(dateStr);
    if (existing) {
      list.push(existing);
    } else {
      list.push({ Date: dateStr, Code: '-' });
    }
    currentDate.setDate(currentDate.getDate() + 1);
  }
  return list;
});

const dailyPagination = reactive({
  page: 1,
  limit: 200
});

const paginatedDailyMappings = computed(() => {
  const start = (dailyPagination.page - 1) * dailyPagination.limit;
  return displayedDailyMappings.value.slice(start, start + dailyPagination.limit);
});

const totalDailyPages = computed(() => {
  return Math.ceil(displayedDailyMappings.value.length / dailyPagination.limit) || 1;
});

const focusedTargetDate = ref('')

const scrollToDailyDate = async (targetDateStr) => {
  if (!targetDateStr || !displayedDailyMappings.value.length) return

  const index = displayedDailyMappings.value.findIndex(m => m.Date === targetDateStr)
  if (index === -1) return

  focusedTargetDate.value = targetDateStr
  const targetPage = Math.floor(index / dailyPagination.limit) + 1
  dailyPagination.page = targetPage

  await nextTick()

  const rowEl = document.getElementById(`daily-row-${targetDateStr}`)
  if (rowEl) {
    rowEl.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }
}

const focusDailyMappingDate = () => {
  if (!rateFormState.IsDaily) return

  const today = getTodayString()
  const begin = rateFormState.BeginDate
  const end = rateFormState.EndDate

  if (begin && end) {
    if (begin <= today && today <= end) {
      // Trường hợp 1: Chứa ngày hiện tại của hệ thống -> trỏ chính xác vào ngày hiện tại
      scrollToDailyDate(today)
    } else if (begin > today) {
      // Trường hợp 2: Set up rate code tương lai (BeginDate > Today) -> hiển thị từ giai đoạn bắt đầu
      scrollToDailyDate(begin)
    } else {
      // Trường hợp quá khứ (EndDate < Today) -> trỏ vào ngày bắt đầu giai đoạn
      scrollToDailyDate(begin)
    }
  } else if (begin) {
    scrollToDailyDate(begin)
  }
}

watch(() => rateFormState.IsDaily, (newVal) => {
  if (newVal) {
    setDefaultBatchDates()
    nextTick(() => focusDailyMappingDate())
  } else {
    dailyPagination.page = 1
  }
})
watch(() => rateFormState.BeginDate, (newVal) => {
  if (newVal && rateFormState.EndDate && rateFormState.EndDate < newVal) {
    rateFormState.EndDate = newVal
  }
  dailyPagination.page = 1
  setDefaultBatchDates()
  nextTick(() => focusDailyMappingDate())
})
watch(() => rateFormState.EndDate, (newVal) => {
  if (newVal && rateFormState.BeginDate && newVal < rateFormState.BeginDate) {
    uiStore.showToast('Ngày kết thúc không được nhỏ hơn Ngày bắt đầu!', 'warning')
    rateFormState.EndDate = rateFormState.BeginDate
  }
  dailyPagination.page = 1
  setDefaultBatchDates()
  nextTick(() => focusDailyMappingDate())
})
watch(() => modalFormState.BeginDate, (newVal) => {
  if (newVal && modalFormState.EndDate && modalFormState.EndDate < newVal) {
    modalFormState.EndDate = newVal
  }
})
watch(() => modalFormState.EndDate, (newVal) => {
  if (newVal && modalFormState.BeginDate && newVal < modalFormState.BeginDate) {
    uiStore.showToast('Ngày kết thúc không được nhỏ hơn Ngày bắt đầu!', 'warning')
    modalFormState.EndDate = modalFormState.BeginDate
  }
})
watch(() => batchFromDate.value, (newVal) => {
  if (newVal && batchToDate.value && batchToDate.value < newVal) {
    batchToDate.value = newVal
  }
})

const updateSingleMapping = async (dateStr, newCode) => {
  if (isSavingDailyMappings.value) return;
  const previousMappings = JSON.parse(JSON.stringify(dailyMappingsList.value));
  const existingIdx = dailyMappingsList.value.findIndex(m => m.Date === dateStr);
  if (newCode === '-' || !newCode) {
    if (existingIdx !== -1) {
      dailyMappingsList.value.splice(existingIdx, 1);
    }
  } else {
    if (existingIdx !== -1) {
      dailyMappingsList.value[existingIdx].Code = newCode;
    } else {
      dailyMappingsList.value.push({ Date: dateStr, Code: newCode });
    }
  }
  dailyMappingsList.value.sort((a, b) => a.Date.localeCompare(b.Date));
  dailyMappingsList.value = [...dailyMappingsList.value];

  const saved = await persistDailyMappingChanges({
    mappings: newCode === '-' || !newCode ? [] : [{ Date: dateStr, Code: newCode }],
    deleteDates: newCode === '-' || !newCode ? [dateStr] : [],
    mode: 'merge',
  });
  if (!saved) {
    dailyMappingsList.value = previousMappings;
    return;
  }
  uiStore.showToast('Đã lưu loại giá cho ngày đã chọn.', 'success');
};

const initialRateStateString = ref('');

const isRateDirty = computed(() => {
  if (!selectedRateCode.value && !rateFormState.Ma) return false;
  const current = JSON.stringify({ form: rateFormState, matrix: rateMatrix, daily: dailyMappingsList.value });
  return current !== initialRateStateString.value;
});

// Sync form with selected row
watch(selectedRateCode, (newVal) => {
  dailyPagination.page = 1;
  if (newVal) {
    Object.assign(rateFormState, {
      Ma: newVal.Ma || '',
      Description: newVal.Description || '',
      BeginDate: newVal.BeginDate || '',
      EndDate: newVal.EndDate || '',
      Currency: newVal.Currency || 'VND',
      IsDaily: newVal.IsDaily ? true : false,
      Disable: newVal.Disable || false,
      AllowChangeRate: newVal.AllowChangeRate || false,
      IsChannelManager: newVal.IsChannelManager || false,
      IncludeBF: newVal.IncludeBF || false
    })

    loadRateMatrixFromPlans(newVal.Ma, newVal.rate_plans);

    // Load Daily Mappings
    dailyMappingsList.value = newVal.daily_mappings ? JSON.parse(JSON.stringify(newVal.daily_mappings)) : [];

    // Auto select first rate type if available
    if (availableRatePlans.value.length > 0) {
      batchRateType.value = availableRatePlans.value[0].Code;
    } else {
      batchRateType.value = '';
    }
    setDefaultBatchDates();
  } else {
    // Reset
    Object.assign(rateFormState, { Ma: '', Description: '', BeginDate: '', EndDate: '', IsDaily: false })
    for (const key in rateMatrix) delete rateMatrix[key];
    dailyMappingsList.value = [];
  }
  initialRateStateString.value = JSON.stringify({ form: rateFormState, matrix: rateMatrix, daily: dailyMappingsList.value });
  nextTick(() => focusDailyMappingDate());
}, { immediate: true })

const selectRateCode = async (rc) => {
  if (selectedRateCode.value?.Ma === rc.Ma) return;
  if (isRateDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn bỏ qua và chuyển mã khác?' });
    if (!confirmed) return;
  }
  selectedRateCode.value = rc
}

const handleAdd = async () => {
  if (isRateDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn bỏ qua để tạo mới?' });
    if (!confirmed) return;
  }
  selectedRateCode.value = null
  Object.assign(rateFormState, { Ma: '', Description: '', BeginDate: '', EndDate: '', Currency: 'VND', IsDaily: false, Disable: false, AllowChangeRate: false, IsChannelManager: false, IncludeBF: false })
  for (const key in rateMatrix) delete rateMatrix[key];
}

const handleSave = async () => {
  if (!rateFormState.Ma) {
    uiStore.showToast('Vui lòng nhập Mã giá phòng trước khi lưu!', 'warning');
    return false;
  }
  if (rateFormState.BeginDate && rateFormState.EndDate && rateFormState.EndDate < rateFormState.BeginDate) {
    uiStore.showToast('Ngày kết thúc phải lớn hơn hoặc bằng Ngày bắt đầu!', 'warning');
    return false;
  }
  try {
    const isNew = !rateCodes.value.find(r => r.Ma === rateFormState.Ma);

    if (!isNew) {
      const confirmed = await uiStore.confirm({ message: 'Bạn có chắc chắn muốn cập nhật mã giá phòng này không?' });
      if (!confirmed) return;
    }

    const url = isNew ? '/room-rate-codes' : `/room-rate-codes/${rateFormState.Ma}`;
    const method = isNew ? 'POST' : 'PUT';

    const res = await http({
      url,
      method,
      data: rateFormState
    });

    const savedMa = rateFormState.Ma;

    if (res.status === 200 || res.status === 201) {
      // Save Matrix
      if (!rateFormState.IsDaily) {
        const normalizedDefaultMatrix = normalizeRateMatrix(
          rateMatrix,
          'DEFAULT',
          roomTypes.value,
          occupanciesList.value
        );
        await http.post(`/room-rate-codes/${savedMa}/plans`, {
          Code: 'DEFAULT',
          Period: normalizedDefaultMatrix
        });
        await http.post(`/room-rate-codes/${savedMa}/daily-mappings`, {
          mappings: []
        });
      } else {
        // Save Daily Mappings
        await http.post(`/room-rate-codes/${savedMa}/daily-mappings`, {
          mappings: dailyMappingsList.value
        });
      }
      uiStore.showToast('Lưu thành công!', 'success');
      await fetchRateCodes(true);

      // Auto-select the newly saved code
      const newlySaved = rateCodes.value.find(r => r.Ma === savedMa);
      if (newlySaved) {
        selectedRateCode.value = newlySaved;
      }

      await nextTick();
      focusDailyMappingDate();

      return true;
    } else {
      uiStore.showToast('Lỗi lưu dữ liệu', 'error');
      return false;
    }
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể lưu mã giá phòng.', 'error');
    return false;
  }
}

const handleDelete = async () => {
  if (!rateFormState.Ma) return;
  const confirmed = await uiStore.confirm({ message: 'Bạn có chắc chắn muốn xóa mã giá này?' });
  if (!confirmed) return;
  try {
    const res = await http.delete(`/room-rate-codes/${rateFormState.Ma}`);
    if (res.status === 200) {
      uiStore.showToast('Xóa thành công!', 'success');
      handleAdd();
      selectedRateCode.value = null;
      fetchRateCodes();
    }
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể xóa mã giá phòng.', 'error');
  }
}

// Room Types Matrix list
const roomTypes = ref([])


const getMatrixKey = (roomCode, occupancy) => {
  return `DEFAULT_${roomCode}_${occupancy}`
}

// 2. Tab Gói dịch vụ: Service Packages List & Package Details
const servicePackages = ref([
  { id: 'PKG1', code: 'PKG01', currency: 'VND', nights: 1, displayPrice: '200.000', startDate: '01/01/2026', endDate: '31/12/2026', inactive: false, daysOfWeek: 'Thứ 2, Thứ 3, Thứ 4, Thứ 5, Thứ 6, Thứ 7, Chủ Nhật', description: 'Gói ăn sáng Buffet' },
  { id: 'PKG2', code: 'PKG02', currency: 'VND', nights: 1, displayPrice: '450.000', startDate: '01/01/2026', endDate: '31/12/2026', inactive: false, daysOfWeek: 'Thứ 2, Thứ 3, Thứ 4, Thứ 5, Thứ 6, Thứ 7, Chủ Nhật', description: 'Gói ăn tối Set menu' },
  { id: 'PKG3', code: 'PKG03', currency: 'VND', nights: 2, displayPrice: '900.000', startDate: '15/02/2026', endDate: '15/02/2027', inactive: false, daysOfWeek: 'Thứ 6, Thứ 7, Chủ Nhật', description: 'Combo Cuối tuần Spa & Dining' }
])

const selectedPackage = ref(servicePackages.value[0])

const packageDetails = computed(() => {
  if (!selectedPackage.value) return []
  if (selectedPackage.value.id === 'PKG1') {
    return [
      { id: 'd1', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Buffet sáng người lớn', department: 'Restaurant/Nhà Hàng', mealPlan: 'Breakfast', amount: '150.000', calculatedOn: 'Người/Đêm', roomClass: 'Tất cả' },
      { id: 'd2', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Buffet sáng trẻ em', department: 'Restaurant/Nhà Hàng', mealPlan: 'Breakfast', amount: '50.000', calculatedOn: 'Trẻ em/Đêm', roomClass: 'Tất cả' }
    ]
  } else if (selectedPackage.value.id === 'PKG2') {
    return [
      { id: 'd3', validity: 'Theo ngày ở', fromDate: '01/01/2026', endDate: '31/12/2026', service: 'Set ăn tối Deluxe', department: 'Restaurant/Nhà Hàng', mealPlan: 'Dinner', amount: '450.000', calculatedOn: 'Người/Đêm', roomClass: 'Tất cả' }
    ]
  } else {
    return [
      { id: 'd4', validity: 'Theo ngày ở', fromDate: '15/02/2026', endDate: '15/02/2027', service: 'Liệu trình Spa thư giãn', department: 'Spa', mealPlan: 'None', amount: '500.000', calculatedOn: 'Khách/Lần', roomClass: 'Suite' },
      { id: 'd5', validity: 'Theo ngày ở', fromDate: '15/02/2026', endDate: '15/02/2027', service: 'Set ăn tối đặc biệt', department: 'Restaurant/Nhà Hàng', mealPlan: 'Dinner', amount: '400.000', calculatedOn: 'Người/Đêm', roomClass: 'Suite' }
    ]
  }
})

// Calculate total sum of package details
const totalPackageDetailsAmount = computed(() => {
  return packageDetails.value.reduce((sum, item) => {
    const numeric = parseInt(item.amount.replace(/\./g, ''), 10) || 0
    return sum + numeric
  }, 0)
})

const formatNumber = (num) => {
  return new Intl.NumberFormat('vi-VN').format(num)
}

const selectPackage = (pkg) => {
  selectedPackage.value = pkg
}
</script>

<template>
  <div class="flex-1 flex flex-col gap-2.5 text-slate-800 font-roboto min-h-0 overflow-hidden">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in rateTabs" :key="tab" @click="activeRateTab = tab"
          class="px-2.5 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-1.5 transition-colors font-roboto"
          :class="isTabActive(tab) ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- MAIN VIEW WRAPPER -->
    <div class="flex-1 min-h-0 flex flex-col gap-2.5">

      <!-- ================= TAB 1: MÃ GIÁ PHÒNG ================= -->
      <div v-if="isTabActive('Mã Giá Phòng')" class="flex-1 flex flex-col gap-2.5 min-h-0">

        <!-- Top Half: Table on left (42%), form on right (58%) - Compact height to leave room for bottom panel -->
        <div class="flex flex-col lg:flex-row gap-2.5 h-[225px] shrink-0">

          <!-- Left Table (42%) -->
          <div
            class="w-full lg:w-[42%] bg-white border border-slate-200 rounded-lg p-2.5 flex flex-col min-h-0 shadow-2xs">
            <div class="overflow-y-auto flex-1 border border-slate-200 rounded-lg">
              <table class="w-full text-xs text-left border-collapse font-roboto">
                <thead>
                  <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs sticky top-0 z-10">
                    <th class="p-2 text-center align-middle border border-slate-200 w-16">Mã</th>
                    <th class="p-2 text-center align-middle border border-slate-200">Mô Tả</th>
                    <th class="p-2 text-center align-middle border border-slate-200 w-16">Tiền Tệ</th>
                    <th class="p-2 text-center align-middle border border-slate-200 w-24">Từ Ngày</th>
                    <th class="p-2 text-center align-middle border border-slate-200 w-24">Đến Ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rc in rateCodes" :key="rc.Ma" @click="selectRateCode(rc)"
                    class="border-b border-slate-100 hover:bg-slate-50/70 cursor-pointer transition-colors"
                    :class="selectedRateCode?.Ma === rc.Ma ? 'bg-sky-50/60 font-semibold text-sky-800' : 'text-[#000000D9]'">
                    <td class="p-2 border-r border-slate-100 font-normal text-[#000000D9]">{{ rc.Ma }}</td>
                    <td class="p-2 border-r border-slate-100 font-normal text-[#000000D9]">{{ rc.Description }}</td>
                    <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ rc.Currency }}</td>
                    <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ formatDateVN(rc.BeginDate) }}</td>
                    <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ formatDateVN(rc.EndDate) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Form (58%) -->
          <div
            class="w-full lg:w-[58%] bg-white border border-slate-200 rounded-lg p-2.5 flex flex-col min-h-0 shadow-2xs justify-between">
            <!-- Form Action Buttons Header -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 shrink-0">
              <span class="text-xs font-semibold text-[#000000D9]">Chi Tiết Thiết Lập Giá</span>
              <div class="flex items-center gap-1.5">
                <!-- Add Button -->
                <button @click="handleAdd"
                  class="btn-pms-primary h-7.5 px-2.5 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  Thêm
                </button>
                <!-- Save Button -->
                <button @click="handleSave"
                  class="btn-pms-primary h-7.5 px-2.5 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                  </svg>
                  {{ isEditing ? 'Cập Nhật' : 'Lưu' }}
                </button>
                <!-- Delete Button -->
                <button @click="handleDelete"
                  class="btn-pms-danger h-7.5 px-2.5 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Xóa
                </button>
              </div>
            </div>

            <!-- Form Content Fields stacked in rows matching mockup -->
            <div class="flex flex-col gap-2 py-1.5 overflow-y-auto flex-1 text-xs">
              <!-- Row 1: Mã (1/3) & Mô tả (2/3) -->
              <div class="flex gap-2.5">
                <div class="w-1/3">
                  <label class="block text-xs font-semibold text-[#000000D9] mb-0.5">
                    Mã <span v-if="!isEditing" class="text-rose-500">*</span>
                  </label>
                  <div class="relative flex items-center">
                    <input type="text" v-model="rateFormState.Ma" :disabled="isEditing" placeholder="Mã giá phòng..."
                      class="w-full px-2 py-1 rounded-lg text-xs transition-colors"
                      :class="isEditing ? 'bg-slate-100 border border-slate-200 text-[#000000D9] font-normal cursor-not-allowed' : 'bg-[#FFF8DB] border border-[#F1DD8A] focus:border-[#eab308] text-[#000000D9] font-normal pr-7'" />
                    <button v-if="rateFormState.Ma && !isEditing" @click="rateFormState.Ma = ''"
                      class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                      title="Xóa nhanh">✕</button>
                  </div>
                </div>
                <div class="w-2/3">
                  <label class="block text-xs font-semibold text-[#000000D9] mb-0.5">Mô Tả</label>
                  <div class="relative flex items-center">
                    <input type="text" v-model="rateFormState.Description" placeholder="Mô tả giá phòng..."
                      class="w-full px-2 py-1 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-normal text-[#000000D9] pr-7" />
                    <button v-if="rateFormState.Description" @click="rateFormState.Description = ''"
                      class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                      title="Xóa nhanh">✕</button>
                  </div>
                </div>
              </div>

              <!-- Row 2: Từ ngày - đến ngày (45%) & Tiền tệ (25%) & Ăn sáng checkbox (30%) -->
              <div class="flex gap-2.5 items-end">
                <div class="w-[45%] flex gap-2">
                  <div class="w-1/2">
                    <label class="block text-xs font-semibold text-[#000000D9] mb-0.5">Từ Ngày</label>
                    <SingleDatePicker v-model="rateFormState.BeginDate" placeholder="dd/mm/yyyy" />
                  </div>
                  <div class="w-1/2">
                    <label class="block text-xs font-semibold text-[#000000D9] mb-0.5">Đến Ngày</label>
                    <SingleDatePicker v-model="rateFormState.EndDate" :min-date="rateFormState.BeginDate" placeholder="dd/mm/yyyy" />
                  </div>
                </div>

                <div class="w-[25%]">
                  <label class="block text-xs font-semibold text-[#000000D9] mb-0.5">Tiền Tệ</label>
                  <button type="button" @click="showCurrencyModal = true"
                    class="w-full flex items-center gap-1.5 px-2 py-1 border border-slate-200 rounded-lg text-xs font-normal bg-white hover:border-sky-300 transition-colors cursor-pointer text-left">
                    <span class="text-sm">{{ selectedCurrencyObj.flag }}</span>
                    <span class="font-normal text-[#000000D9]">{{ rateFormState.Currency }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400 ml-auto" fill="none" stroke="currentColor" stroke-width="2"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>

                <div class="w-[30%] pb-1 pl-1">
                  <label
                    class="inline-flex items-center gap-1.5 text-xs text-[#000000D9] font-normal cursor-pointer select-none">
                    <input type="checkbox" v-model="rateFormState.IncludeBF"
                      class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                    Ăn Sáng
                  </label>
                </div>
              </div>

              <!-- Row 3: Remaining Checkboxes Grouped Inline -->
              <div class="flex flex-wrap gap-x-4 gap-y-1 items-center pt-1.5 border-t border-slate-100">
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-[#000000D9] font-normal cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.IsDaily"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                  Giá Theo Ngày
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-[#000000D9] font-normal cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.Disable"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                  Không Sử Dụng
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-[#000000D9] font-normal cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.AllowChangeRate"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                  Cho Phép Nhập Giá
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-[#000000D9] font-normal cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.IsChannelManager"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                  Đẩy Lên Channel Manager
                </label>
              </div>

              <!-- Row 4: Daily-only fields: Ngày áp dụng + Weekday checkboxes + Loại giá -->
              <div v-if="rateFormState.IsDaily" class="flex flex-col gap-2 pt-2 border-t border-slate-200">
                <div class="flex gap-3 items-end flex-wrap">
                  <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold text-[#000000D9] whitespace-nowrap">Ngày Áp Dụng</label>
                    <div class="w-32">
                      <SingleDatePicker v-model="batchFromDate" :min-date="selectedBatchPlan?.BeginDate || getTodayString()" :max-date="selectedBatchPlan?.EndDate" placeholder="dd/mm/yyyy" />
                    </div>
                    <span class="text-xs text-slate-400">~</span>
                    <div class="w-32">
                      <SingleDatePicker v-model="batchToDate" :min-date="batchFromDate || selectedBatchPlan?.BeginDate || getTodayString()" :max-date="selectedBatchPlan?.EndDate" placeholder="dd/mm/yyyy" />
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[1]" class="rounded text-sky-500 cursor-pointer" />T2</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[2]" class="rounded text-sky-500 cursor-pointer" />T3</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[3]" class="rounded text-sky-500 cursor-pointer" />T4</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[4]" class="rounded text-sky-500 cursor-pointer" />T5</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[5]" class="rounded text-sky-500 cursor-pointer" />T6</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[6]" class="rounded text-sky-500 cursor-pointer" />T7</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-normal text-[#000000D9] cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[0]" class="rounded text-sky-500 cursor-pointer" />CN</label>

                  <div class="flex items-center gap-1.5 border-l border-slate-300 pl-2.5 ml-1">
                    <span class="text-xs font-semibold text-[#000000D9]">Loại Giá</span>
                    <select v-model="batchRateType"
                      class="border border-slate-200 px-2 py-1 rounded text-xs focus:outline-sky-400 w-28 bg-white font-normal text-[#000000D9]">
                      <option v-if="availableRatePlans.length === 0" value="" disabled>Trống</option>
                      <option v-for="plan in availableRatePlans" :key="plan.Code" :value="plan.Code">{{ plan.Code }}
                      </option>
                    </select>
                    <button @click="openRatePlanModal"
                      class="bg-sky-500 hover:bg-sky-600 text-white w-7 h-7 rounded-lg flex items-center justify-center font-bold text-xs shadow-xs transition-colors cursor-pointer"
                      title="Quản lý các loại giá">...</button>
                  </div>

                  <button @click="applyBatchUpdate" :disabled="isSavingDailyMappings"
                    class="ml-auto btn-pms-primary h-8 px-3 text-xs flex items-center gap-1 shadow-xs disabled:opacity-50 disabled:cursor-not-allowed">
                    Áp Dụng
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Room rate matrix values with full table grid lines -->
        <div v-if="!rateFormState.IsDaily"
          class="flex-1 min-h-[220px] bg-white border border-slate-200 rounded-xl p-3 flex flex-col shadow-xs overflow-hidden">
          <div class="flex-1 overflow-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200 font-roboto">
              <thead>
                <tr
                  class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold sticky top-0 z-10 text-center">
                  <th class="p-2 text-center align-middle w-36 border border-slate-200 bg-slate-50 font-semibold text-[#000000D9]">Loại Phòng</th>
                  <th class="p-2 text-center align-middle w-48 border border-slate-200 bg-slate-50 font-semibold text-[#000000D9]">Mô Tả</th>
                  <th v-for="occ in occupancies" :key="occ" class="p-2 text-center align-middle border border-slate-200 w-32 font-semibold text-[#000000D9]">{{ occ }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rt in roomTypes" :key="rt.code" class="hover:bg-slate-50/40">
                  <!-- Room type code -->
                  <td class="p-2 font-normal text-[#000000D9] border border-slate-200 bg-slate-50/20 text-center align-middle">{{ rt.code }}</td>
                  <!-- Room type description -->
                  <td class="p-2 text-[#000000D9] font-normal border border-slate-200">{{ rt.description }}</td>

                  <!-- Occupancies pricing inputs inside grid -->
                  <td v-for="occ in occupancies" :key="rt.code + '-' + occ" class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      :value="formatCurrencyInput(rateMatrix[getMatrixKey(rt.code, occ)], rateFormState.Currency)"
                      @input="e => rateMatrix[getMatrixKey(rt.code, occ)] = cleanCurrencyValue(e.target.value, rateFormState.Currency)"
                      placeholder="-"
                      class="w-full px-2 py-1 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-normal bg-white text-[#000000D9] transition-colors" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else class="flex-1 min-h-[220px] bg-white border border-slate-200 rounded-xl p-0 flex flex-col shadow-xs overflow-hidden font-roboto">
          <!-- Lưới giá theo ngày (Daily Grid) - giống hệ thống cũ -->
          <div class="flex-1 overflow-auto">
            <table class="text-xs text-left border-collapse min-w-[1200px] border border-slate-200 font-roboto">
              <thead class="bg-slate-100 sticky top-0 z-20">
                <!-- Header Row 1: Room Type Groups -->
                <tr class="text-[#000000D9]">
                  <th rowspan="2"
                    class="p-2 border border-slate-200 w-28 text-center align-middle bg-slate-100 sticky left-0 z-30 font-semibold text-[#000000D9]">Ngày
                  </th>
                  <th rowspan="2" class="p-2 border border-slate-200 w-10 text-center align-middle bg-slate-100 font-semibold text-[#000000D9]"></th>
                  <th rowspan="2" class="p-2 border border-slate-200 w-16 text-center align-middle bg-slate-100 font-semibold text-[#000000D9]">Mã</th>
                  <th v-for="rt in roomTypes" :key="rt.code" :colspan="occupancies.length"
                    class="p-2 border border-slate-200 text-center align-middle font-semibold text-[#000000D9] bg-slate-100">
                    {{ rt.code }}
                  </th>
                </tr>
                <!-- Header Row 2: Occupancy sub-columns -->
                <tr>
                  <template v-for="rt in roomTypes" :key="'occ-' + rt.code">
                    <th v-for="occ in occupancies" :key="rt.code + '-' + occ"
                      class="p-1.5 border border-slate-200 text-center align-middle text-[11px] font-semibold text-[#000000D9] bg-slate-50 w-20">
                      {{ occ }}
                    </th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="mapping in paginatedDailyMappings" :key="mapping.Date + '-' + mapping.Code" :id="'daily-row-' + mapping.Date"
                  class="border-b border-slate-100 hover:bg-sky-100 transition-colors" :class="{
                    'bg-amber-100/90 font-medium border-y-2 border-amber-400 text-amber-950': mapping.Date === focusedTargetDate,
                    'bg-emerald-50/60 font-medium': mapping.Date !== focusedTargetDate && mapping.Date === getTodayString(),
                    'bg-sky-50/30': mapping.Date !== focusedTargetDate && mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                    'text-rose-500': mapping.Date !== focusedTargetDate && mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                    'text-orange-500': mapping.Date !== focusedTargetDate && mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 6
                  }">
                  <td
                    class="p-2 border border-slate-100 font-normal text-[#000000D9] text-center whitespace-nowrap sticky left-0 z-10"
                    :class="{
                      'bg-amber-300 text-amber-950 font-semibold shadow-sm': mapping.Date === focusedTargetDate,
                      'bg-emerald-100 text-emerald-900 font-semibold': mapping.Date !== focusedTargetDate && mapping.Date === getTodayString(),
                      'bg-sky-50/30': mapping.Date !== focusedTargetDate && mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                      'bg-white': mapping.Date !== focusedTargetDate && mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) !== 0
                    }">
                    {{ formatDateVN(mapping.Date) }}
                  </td>
                  <td class="p-2 border border-slate-100 text-center font-normal text-[#000000D9] text-[10px]"
                    :class="{
                      'bg-emerald-100/50 text-emerald-900': mapping.Date === getTodayString(),
                      'text-rose-500': mapping.Date !== getTodayString() && (getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6)
                    }">
                    {{ getDayLabel(getDayOfWeekFromDate(mapping.Date)) }}
                  </td>
                  <td class="p-1.5 border border-slate-100 text-center">
                    <select :key="mapping.Date + '-' + mapping.Code" :value="mapping.Code" :disabled="isSavingDailyMappings" @change="e => updateSingleMapping(mapping.Date, e.target.value)"
                      class="border px-1 py-0.5 rounded text-xs focus:outline-sky-400 bg-white"
                      :class="[getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6 ? 'text-red-600 font-normal border-red-200' : 'text-[#000000D9] font-normal border-slate-200']">
                      <option value="-">-</option>
                      <option v-for="plan in availableRatePlans" :key="plan.Code" :value="plan.Code">{{ plan.Code }}
                      </option>
                    </select>
                  </td>
                  <template v-for="rt in roomTypes" :key="'val-' + rt.code">
                    <td v-for="occ in occupancies" :key="`${mapping.Date}-${rt.code}-${occ}`"
                      class="p-0.5 border border-slate-100">
                      <span class="block text-center text-[11px] py-1"
                        :class="[getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6 ? 'text-red-600 font-normal' : 'text-[#000000D9] font-normal']">
                        {{ mapping.Code !== '-' ? (formatCurrencyInput(rateMatrix[`${mapping.Code}_${rt.code}_${occ}`],
                          rateFormState.Currency) || '-') : '-' }}
                      </span>
                    </td>
                  </template>
                </tr>
                <tr v-if="paginatedDailyMappings.length === 0">
                  <td :colspan="3 + roomTypes.length * occupancies.length"
                    class="p-8 text-center text-[#000000D9] font-normal text-xs">
                    Vui lòng chọn Mã giá phòng và thiết lập giai đoạn (Từ ngày - Đến ngày) để hiển thị chi tiết giá hằng
                    ngày.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination cho Daily Grid -->
          <div v-if="displayedDailyMappings.length > dailyPagination.limit"
            class="flex items-center justify-between px-4 py-2 border-t border-slate-200 bg-slate-50 shrink-0 select-none">
            <span class="text-xs text-gray-900 font-semibold">
              Hiển thị {{ (dailyPagination.page - 1) * dailyPagination.limit + 1 }} - {{ Math.min(dailyPagination.page *
                dailyPagination.limit, displayedDailyMappings.length) }} trong tổng số {{ displayedDailyMappings.length }}
              ngày
            </span>
            <div class="flex items-center gap-1.5">
              <button @click="dailyPagination.page = Math.max(1, dailyPagination.page - 1)"
                :disabled="dailyPagination.page === 1"
                class="px-2 py-1 border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">&lt;
                Trước</button>

              <span class="text-xs text-gray-900 font-bold px-2">Trang {{ dailyPagination.page }} / {{ totalDailyPages
                }}</span>

              <button @click="dailyPagination.page = Math.min(totalDailyPages, dailyPagination.page + 1)"
                :disabled="dailyPagination.page === totalDailyPages"
                class="px-2 py-1 border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">Sau
                &gt;</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= TAB 2: GÓI DỊCH VỤ ================= -->
      <div v-else-if="isTabActive('Gói Dịch Vụ')" class="flex-1 flex flex-col gap-4 min-h-0">

        <!-- Top Half: Gói dịch vụ list -->
        <div class="flex-1 bg-white border border-slate-200 rounded-xl p-3 flex flex-col min-h-0 shadow-xs">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 shrink-0">
            <h3 class="text-xs font-semibold text-[#000000D9] uppercase tracking-wide m-0">Gói Dịch Vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Search Mock Button -->
              <button
                class="btn-pms-secondary h-8 px-2.5 text-xs flex items-center justify-center cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </button>
              <!-- Add Button -->
              <button
                class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button
                class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                Sửa
              </button>
              <!-- Delete Button -->
              <button
                class="btn-pms-danger h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-2.5">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200 font-roboto">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold sticky top-0 z-10">
                  <th class="p-2 border border-slate-200 w-24 text-center align-middle">Mã</th>
                  <th class="p-2 border border-slate-200 w-20 text-center align-middle">Tiền Tệ</th>
                  <th class="p-2 border border-slate-200 w-24 text-center align-middle">Số Đêm</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Giá Hiển Thị</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Ngày Bắt Đầu</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Ngày Mở Khóa</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Không Sử Dụng</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Theo Ngày Trong Tuần</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Mô Tả</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="pkg in servicePackages" :key="pkg.id" @click="selectPackage(pkg)"
                  class="hover:bg-sky-100 cursor-pointer transition-colors"
                  :class="selectedPackage?.id === pkg.id ? 'bg-sky-50/60 font-semibold text-sky-700' : 'text-[#000000D9]'">
                  <td class="p-2 border border-slate-200 font-normal text-[#000000D9] text-center">{{ pkg.code }}</td>
                  <td class="p-2 border border-slate-200 text-center font-normal text-[#000000D9]">{{ pkg.currency }}</td>
                  <td class="p-2 border border-slate-200 text-center font-normal text-[#000000D9]">{{ pkg.nights }}</td>
                  <td class="p-2 border border-slate-200 text-right font-normal text-[#000000D9]">{{ pkg.displayPrice }}
                  </td>
                  <td class="p-2 border border-slate-200 text-center text-[#000000D9] font-normal">{{ pkg.startDate }}
                  </td>
                  <td class="p-2 border border-slate-200 text-center text-[#000000D9] font-normal">{{ pkg.endDate }}
                  </td>
                  <td class="p-2 border border-slate-200 text-center">
                    <input type="checkbox" :checked="pkg.inactive" @click.stop
                      class="rounded border-slate-300 text-sky-500 focus:ring-sky-400 cursor-pointer" />
                  </td>
                  <td class="p-2 border border-slate-200 text-[#000000D9] font-normal truncate max-w-[200px]"
                    :title="pkg.daysOfWeek">{{ pkg.daysOfWeek }}</td>
                  <td class="p-2 border border-slate-200 text-[#000000D9] font-normal">{{ pkg.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Bottom Half: Chi tiết gói dịch vụ -->
        <div
          class="flex-1 bg-white border border-slate-200 rounded-xl p-3 flex flex-col min-h-0 shadow-xs justify-between">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 shrink-0">
            <h3 class="text-xs font-semibold text-[#000000D9] tracking-wide m-0">Chi Tiết Gói Dịch Vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Add Button -->
              <button
                class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button
                class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                Sửa
              </button>
              <!-- Delete Button -->
              <button
                class="btn-pms-danger h-8 px-3 text-xs flex items-center gap-1.5 cursor-pointer shadow-xs">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-2.5">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200 font-roboto">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold sticky top-0 z-10">
                  <th class="p-2 border border-slate-200 text-center align-middle">Thời Gian Hiệu Lực</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Từ Ngày</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Đến Ngày</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Dịch Vụ</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Bộ Phận</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Gói Bữa Ăn</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Số Tiền</th>
                  <th class="p-2 border border-slate-200 w-32 text-center align-middle">Tính Trên</th>
                  <th class="p-2 border border-slate-200 text-center align-middle">Loại Phòng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in packageDetails" :key="detail.id"
                  class="border-b border-slate-100 hover:bg-sky-100 text-[#000000D9]">
                  <td class="p-2 border border-slate-200 font-normal text-[#000000D9]">{{ detail.validity }}</td>
                  <td class="p-2 border border-slate-200 text-center text-[#000000D9] font-normal">{{ detail.fromDate }}</td>
                  <td class="p-2 border border-slate-200 text-center text-[#000000D9] font-normal">{{ detail.endDate }}</td>
                  <td class="p-2 border border-slate-200 font-normal text-[#000000D9]">{{ detail.service }}</td>
                  <td class="p-2 border border-slate-200 text-[#000000D9] font-normal">{{ detail.department }}</td>
                  <td class="p-2 border border-slate-200 text-center"><span
                      class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded text-[10px] font-normal uppercase">{{
                      detail.mealPlan }}</span></td>
                  <td class="p-2 border border-slate-200 text-right font-normal text-[#000000D9]">{{ detail.amount }}</td>
                  <td class="p-2 border border-slate-200 text-center text-[#000000D9] font-normal">{{ detail.calculatedOn }}</td>
                  <td class="p-2 border border-slate-200 font-normal text-sky-700 text-center">{{ detail.roomClass }}</td>
                </tr>
                <tr v-if="packageDetails.length === 0">
                  <td colspan="9" class="p-8 text-center text-[#000000D9] font-normal text-xs">Không có dữ liệu chi tiết
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total Footer Row -->
          <div
            class="mt-2.5 bg-slate-50 border border-slate-200 rounded-lg p-2.5 flex justify-between items-center text-xs font-semibold text-[#000000D9] shrink-0">
            <span>Tổng</span>
            <span class="text-xs text-[#006bdb] font-bold pr-32">{{ formatNumber(totalPackageDetailsAmount) }}</span>
          </div>
        </div>
      </div>

    </div>

    <!-- RATE PLAN MODAL -->
    <Teleport to="body">
      <div v-if="showRatePlanModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-6">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40" @click="closeRatePlanModal"></div>
        <!-- Modal -->
        <div
          class="relative bg-white rounded-xl shadow-2xl w-full max-w-6xl h-full max-h-[90vh] flex flex-col overflow-hidden"
          :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-2.5 text-white shrink-0 cursor-move select-none"
            :style="{ background: topbarThemeBg }"
            @mousedown="startDragModal">
            <span class="text-xs font-semibold tracking-wide text-white">Quản Lý Các Loại Giá</span>
            <button @click="closeRatePlanModal"
              class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content: Top split (List & Form) -->
          <div class="flex h-[280px] border-b border-slate-200 shrink-0">
            <!-- Left: List -->
            <div class="w-1/2 border-r border-slate-200 overflow-y-auto p-4 bg-slate-50/50">
              <table class="w-full text-xs text-left border-collapse border border-slate-200 bg-white shadow-sm">
                <thead class="bg-slate-100/80 sticky top-0">
                  <tr>
                    <th class="p-2 border border-slate-200 text-[#000000D9] font-semibold text-center align-middle">Mã Loại Giá</th>
                    <th class="p-2 border border-slate-200 text-[#000000D9] font-semibold text-center align-middle">Mô Tả</th>
                    <th class="p-2 border border-slate-200 text-[#000000D9] font-semibold text-center align-middle">Từ Ngày</th>
                    <th class="p-2 border border-slate-200 text-[#000000D9] font-semibold text-center align-middle">Đến Ngày</th>
                    <th class="p-2 border border-slate-200 text-[#000000D9] font-semibold text-center align-middle w-16">Hành Động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="plan in modalRatePlans" :key="plan.Code" @click="selectModalPlan(plan)"
                    class="cursor-pointer hover:bg-sky-50 transition-colors text-xs"
                    :class="{ 'bg-sky-100': modalFormState.Code === plan.Code }">
                    <td class="p-2 border border-slate-200 font-normal text-[#000000D9]">{{ plan.Code }}</td>
                    <td class="p-2 border border-slate-200 font-normal text-[#000000D9]">{{ plan.Description }}</td>
                    <td class="p-2 border border-slate-200 text-center font-normal text-[#000000D9]">{{
                      formatDateVN(plan.BeginDate) }}</td>
                    <td class="p-2 border border-slate-200 text-center font-normal text-[#000000D9]">{{
                      formatDateVN(plan.EndDate) }}</td>
                    <td class="p-2 border border-slate-200 text-center" @click.stop>
                      <button @click="deletePlanItem(plan)" title="Xóa loại giá"
                        class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded transition-colors inline-flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="modalRatePlans.length === 0">
                    <td colspan="5" class="p-6 text-center text-gray-500 font-medium text-xs">Chưa có loại giá nào
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Right: Form -->
            <div class="w-1/2 p-5 flex flex-col">
              <div class="flex justify-end gap-2 mb-4">
                <button @click="resetModalForm" type="button"
                  class="btn-pms-secondary h-8 px-3 text-xs flex items-center gap-1.5 font-medium cursor-pointer">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  <span>Thêm Mới</span>
                </button>
                <button v-if="isModalEditing" @click="deleteModalPlan" type="button"
                  class="btn-pms-danger h-8 px-3 text-xs flex items-center gap-1.5 font-medium cursor-pointer shadow-xs">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  <span>Xóa</span>
                </button>
                <button @click="saveModalPlan" type="button"
                  class="btn-pms-primary h-8 px-4 text-xs flex items-center gap-1.5 font-medium cursor-pointer">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>Lưu</span>
                </button>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Mã Loại Giá <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input type="text" v-model="modalFormState.Code" :disabled="isModalEditing"
                      class="w-full border rounded px-2.5 py-1.5 text-xs focus:outline-none transition-colors disabled:bg-slate-100 disabled:border-slate-200 disabled:text-[#000000D9] text-[#000000D9] font-normal"
                      :class="isModalEditing ? '' : 'bg-[#FFF8DB] border-[#F1DD8A] focus:border-amber-500'"
                      placeholder="Nhập mã loại giá" />
                    <button v-if="modalFormState.Code && !isModalEditing" @click="modalFormState.Code = ''" type="button"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">✕</button>
                  </div>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Mô Tả</label>
                  <div class="relative">
                    <input type="text" v-model="modalFormState.Description"
                      class="w-full border border-slate-200 rounded px-2.5 py-1.5 text-xs focus:outline-sky-400 text-[#000000D9] font-normal"
                      placeholder="Mô tả loại giá" />
                    <button v-if="modalFormState.Description" @click="modalFormState.Description = ''" type="button"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">✕</button>
                  </div>
                </div>
              </div>

              <div class="flex flex-col gap-1 mt-3">
                <label class="text-xs font-semibold text-[#000000D9]">Thời Gian Áp Dụng (Từ Ngày - Đến Ngày)</label>
                <div class="flex items-center gap-2">
                  <div class="w-full">
                    <SingleDatePicker v-model="modalFormState.BeginDate" placeholder="dd/mm/yyyy" />
                  </div>
                  <span class="text-slate-400">~</span>
                  <div class="w-full">
                    <SingleDatePicker v-model="modalFormState.EndDate" :min-date="modalFormState.BeginDate" placeholder="dd/mm/yyyy" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Content: Bottom (Matrix Grid) -->
          <div class="flex-1 relative bg-white flex flex-col min-h-0 font-roboto">
            <div v-if="!modalFormState.Code"
              class="absolute inset-0 z-20 bg-white/60 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
              <span
                class="bg-slate-800 text-white px-4 py-2 rounded-lg font-semibold text-xs shadow-xl pointer-events-auto">Vui
                lòng nhập Mã loại giá để tiếp tục</span>
            </div>
            <div class="flex-1 p-4 overflow-auto">
              <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200 font-roboto">
                <thead>
                  <tr
                    class="bg-slate-100 border-b border-slate-200 text-[#000000D9] font-semibold sticky top-0 z-10 text-center">
                    <th class="p-2 border border-slate-200 bg-slate-100 text-center align-middle w-36">Loại Phòng</th>
                    <th class="p-2 border border-slate-200 bg-slate-100 text-center align-middle w-48">Mô Tả</th>
                    <th v-for="occ in occupanciesList" :key="occ" class="p-2 border border-slate-200 text-center align-middle w-32">{{ occ }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rt in roomTypes" :key="rt.code" class="hover:bg-slate-50/50">
                    <td class="p-2 font-normal text-[#000000D9] border border-slate-200 bg-slate-50 text-center align-middle">{{ rt.code }}</td>
                    <td class="p-2 text-[#000000D9] font-normal border border-slate-200">{{ rt.description }}</td>

                    <td v-for="occ in occupanciesList" :key="rt.code + '-' + occ" class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, occ)], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, occ)] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-normal text-[#000000D9] transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                    </td>
                  </tr>
                  <tr v-if="roomTypes.length === 0">
                    <td :colspan="2 + occupanciesList.length" class="p-8 text-center text-[#000000D9] font-normal text-xs">
                      Đang tải danh sách loại phòng...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- CURRENCY MODAL -->
    <Teleport to="body">
      <div v-if="showCurrencyModal" class="fixed inset-0 z-[9999] flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40" @click="showCurrencyModal = false; currencySearch = ''"></div>
        <!-- Modal -->
        <div class="relative bg-white rounded-xl shadow-2xl w-[520px] max-h-[80vh] flex flex-col overflow-hidden"
          :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-2.5 text-white shrink-0 select-none cursor-move"
            :style="{ background: topbarThemeBg }"
            @mousedown="startDragModal">
            <span class="text-xs font-semibold tracking-wide text-white">Chọn Tiền Tệ</span>
            <button @click="showCurrencyModal = false; currencySearch = ''"
              class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <!-- Search -->
          <div class="px-5 py-3 border-b border-slate-100 shrink-0">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none"
                stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input type="text" v-model="currencySearch" placeholder="Search"
                class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-sky-400 bg-slate-50/50 font-semibold text-gray-900" />
            </div>
          </div>
          <!-- Currency List -->
          <div class="px-5 py-3 overflow-y-auto flex-1">
            <p class="text-[11px] font-bold text-gray-900/60 uppercase tracking-wider mb-3">Tiền tệ phổ biến</p>
            <div class="grid grid-cols-2 gap-2">
              <button v-for="cur in filteredCurrencies" :key="cur.code" @click="selectCurrency(cur.code)"
                class="flex items-center gap-3 px-3.5 py-3 rounded-xl border transition-all cursor-pointer text-left"
                :class="rateFormState.Currency === cur.code
                  ? 'border-sky-300 bg-sky-50/50 shadow-sm'
                  : 'border-slate-100 bg-white hover:border-slate-200 hover:bg-slate-50/50'">
                <span class="text-2xl leading-none">{{ cur.flag }}</span>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-bold text-gray-900 truncate">{{ cur.name }}</div>
                  <div class="text-xs text-gray-900/60 font-semibold">{{ cur.code }} - {{ cur.symbol }}</div>
                </div>
                <svg v-if="rateFormState.Currency === cur.code" class="w-5 h-5 text-emerald-500 shrink-0"
                  fill="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                </svg>
              </button>
            </div>
            <p v-if="filteredCurrencies.length === 0" class="text-center text-gray-900/60 py-6 text-sm font-semibold">
              Không
              tìm thấy tiền tệ phù hợp</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Scoped Delete Button style to bypass tailwind overrides */
.btn-delete {
  background-color: #64748b !important;
  color: #ffffff !important;
  border: none !important;
}

.btn-delete:hover {
  background-color: #475569 !important;
}

/* Scrollbar customizations */
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
