<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  initialTab: {
    type: String,
    default: 'Mã giá phòng'
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

const rateTabs = ['Mã giá phòng', 'Gói dịch vụ']

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

onMounted(() => {
  fetchRoomData()
  fetchRateCodes()
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

const occupancies = ref([])

const availableRatePlans = computed(() => {
  const currentRc = rateCodes.value.find(r => r.Ma === rateFormState.Ma) || selectedRateCode.value;
  if (!currentRc || !currentRc.rate_plans) return [];
  return currentRc.rate_plans
    .filter(p => p.Code !== 'DEFAULT')
    .map(p => ({ Code: p.Code }));
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
  showRatePlanModal.value = true;
  const plans = selectedRateCode.value.rate_plans || [];
  modalRatePlans.value = JSON.parse(JSON.stringify(plans.filter(p => p.Code !== 'DEFAULT')));

  Object.assign(modalFormState, { Code: '', Description: '', BeginDate: '', EndDate: '' });
  for (const key in modalRateMatrix) delete modalRateMatrix[key];
  isModalEditing.value = false;
  initialModalStateString.value = JSON.stringify({ form: modalFormState, matrix: modalRateMatrix });
}

const formatCurrencyInput = (val, currency) => {
  if (val === null || val === undefined || val === '') return '';
  let clean = String(val).replace(/[^\d.,]/g, '');
  if (!clean) return '';

  if (currency === 'VND') {
    clean = clean.replace(/\D/g, '');
    if (!clean) return '';
    return Number(clean).toLocaleString('vi-VN');
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
      for (const k in parsed) {
        let newKey = k;
        if (k.startsWith(selectedRateCode.value.Ma + '_')) {
          newKey = plan.Code + '_' + k.substring(selectedRateCode.value.Ma.length + 1);
        }
        modalRateMatrix[newKey] = parsed[k];
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
    const res = await http.post(`/room-rate-codes/${selectedRateCode.value.Ma}/plans`, {
      ...modalFormState,
      Period: modalRateMatrix
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
  }
}

const deleteModalPlan = async () => {
  if (!modalFormState.Code) return;
  const confirmed = await uiStore.confirm({ message: `Bạn có chắc muốn xóa loại giá ${modalFormState.Code}?` });
  if (!confirmed) return;
  try {
    const res = await http.delete(`/room-rate-codes/${selectedRateCode.value.Ma}/plans/${modalFormState.Code}`);
    if (res.status === 200) {
      uiStore.showToast('Xóa thành công!', 'success');
      resetModalForm();
      await fetchRateCodes();
      const updatedCode = rateCodes.value.find(r => r.Ma === selectedRateCode.value.Ma);
      if (updatedCode) {
        selectedRateCode.value.rate_plans = updatedCode.rate_plans;
        loadRateMatrixFromPlans(selectedRateCode.value.Ma, updatedCode.rate_plans);
        const plans = updatedCode.rate_plans || [];
        modalRatePlans.value = JSON.parse(JSON.stringify(plans.filter(p => p.Code !== 'DEFAULT')));
      }
    }
  } catch (e) {
    console.error(e);
  }
}

const loadRateMatrixFromPlans = (rateCodeMa, plans) => {
  for (const key in rateMatrix) delete rateMatrix[key];
  if (!plans?.length) return;

  plans.forEach(plan => {
    if (!plan.Period) return;
    try {
      const parsed = typeof plan.Period === 'string' ? JSON.parse(plan.Period) : plan.Period;
      for (const k in parsed) {
        let newKey = k;
        if (k.startsWith(rateCodeMa + '_')) {
          newKey = plan.Code + '_' + k.substring(rateCodeMa.length + 1);
        }
        rateMatrix[newKey] = parsed[k];
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
  const d = new Date()
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  const parts = formatter.formatToParts(d)
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  const year = parts.find(p => p.type === 'year').value
  return `${year}-${month}-${day}`
}

const isEditing = computed(() => {
  return rateCodes.value.some(r => r.Ma === rateFormState.Ma);
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

  const from = new Date(batchFromDate.value);
  const to = new Date(batchToDate.value);
  if (from > to) {
    uiStore.showToast('Từ ngày không được lớn hơn Đến ngày', 'warning');
    return;
  }

  const confirmed = await uiStore.confirm({ message: 'Bạn có chắc chắn muốn áp dụng thông tin giá theo ngày không?' });
  if (!confirmed) return;

  let appliedCount = 0;
  let currentDate = new Date(from);
  while (currentDate <= to) {
    const dayOfWeek = currentDate.getDay();
    if (batchDaysOfWeek[dayOfWeek]) {
      const dateStr = currentDate.toISOString().split('T')[0];

      const existingIdx = dailyMappingsList.value.findIndex(m => m.Date === dateStr);
      if (existingIdx !== -1) {
        dailyMappingsList.value[existingIdx].Code = batchRateType.value;
      } else {
        dailyMappingsList.value.push({ Date: dateStr, Code: batchRateType.value });
      }
      appliedCount++;
    }
    currentDate.setDate(currentDate.getDate() + 1);
  }

  if (appliedCount === 0) {
    uiStore.showToast('Không có ngày nào được chọn theo thứ trong tuần để áp dụng.', 'warning');
    return;
  }

  dailyMappingsList.value.sort((a, b) => a.Date.localeCompare(b.Date));
  dailyMappingsList.value = dailyMappingsList.value.map(m => ({ ...m }));
  uiStore.showToast('Áp dụng loại giá thành công!', 'success');
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
  while (currentDate <= to && safetyCounter < 2000) {
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

watch(() => rateFormState.IsDaily, () => {
  dailyPagination.page = 1;
});
watch(() => rateFormState.BeginDate, () => {
  dailyPagination.page = 1;
});
watch(() => rateFormState.EndDate, () => {
  dailyPagination.page = 1;
});

const updateSingleMapping = (dateStr, newCode) => {
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
  } else {
    // Reset
    Object.assign(rateFormState, { Ma: '', Description: '', BeginDate: '', EndDate: '', IsDaily: false })
    for (const key in rateMatrix) delete rateMatrix[key];
    dailyMappingsList.value = [];
  }
  initialRateStateString.value = JSON.stringify({ form: rateFormState, matrix: rateMatrix, daily: dailyMappingsList.value });
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
        await http.post(`/room-rate-codes/${savedMa}/plans`, {
          Code: 'DEFAULT',
          Period: rateMatrix
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

      return true;
    } else {
      uiStore.showToast('Lỗi lưu dữ liệu', 'error');
      return false;
    }
  } catch (e) {
    console.error(e)
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
  } catch (e) { console.error(e) }
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
  <div class="flex-1 flex flex-col gap-4 text-gray-900 font-semibold min-h-0 overflow-y-auto">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in rateTabs" :key="tab" @click="activeRateTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeRateTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-gray-900/60 hover:text-gray-900 font-semibold'">
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- MAIN VIEW WRAPPER -->
    <div class="flex-1 min-h-0 flex flex-col gap-4">

      <!-- ================= TAB 1: MÃ GIÁ PHÒNG ================= -->
      <div v-if="activeRateTab === 'Mã giá phòng'" class="flex-1 flex flex-col gap-4 min-h-0">

        <!-- Top Half: Table on left (40%), form on right (60%) -->
        <div class="flex flex-col lg:flex-row gap-4 h-[350px] shrink-0 min-h-0">

          <!-- Left Table (40%) -->
          <div
            class="w-full lg:w-[42%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
            <div class="overflow-y-auto flex-1">
              <table class="w-full text-xs text-left border-collapse border border-slate-200">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold uppercase sticky top-0 z-10">
                    <th class="p-2 border border-slate-200 w-16">Mã</th>
                    <th class="p-2 border border-slate-200">Mô tả</th>
                    <th class="p-2 border border-slate-200 w-16 text-center">Tiền tệ</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Từ ngày</th>
                    <th class="p-2 border border-slate-200 w-24 text-center">Đến ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rc in rateCodes" :key="rc.Ma" @click="selectRateCode(rc)"
                    class="border-b border-slate-100 hover:bg-sky-100 cursor-pointer transition-colors"
                    :class="selectedRateCode?.Ma === rc.Ma ? 'bg-sky-50/40 font-bold text-sky-700' : 'text-gray-900 font-semibold'">
                    <td class="p-2 border border-slate-100 font-bold">{{ rc.Ma }}</td>
                    <td class="p-2 border border-slate-100">{{ rc.Description }}</td>
                    <td class="p-2 border border-slate-100 text-center">{{ rc.Currency }}</td>
                    <td class="p-2 border border-slate-100 text-center text-gray-900 font-semibold">{{ formatDateVN(rc.BeginDate) }}
                    </td>
                    <td class="p-2 border border-slate-100 text-center text-gray-900 font-semibold">{{ formatDateVN(rc.EndDate) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Form (58%) -->
          <div
            class="w-full lg:w-[58%] bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs justify-between">
            <!-- Form Action Buttons Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
              <span class="text-xs font-bold text-gray-900 uppercase tracking-wide">Chi tiết thiết lập giá</span>
              <div class="flex items-center gap-1.5">
                <!-- Add Button -->
                <button @click="handleAdd"
                  class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                  <span class="text-sm">+</span> Thêm
                </button>
                <!-- Save Button -->
                <button @click="handleSave"
                  class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                  {{ isEditing ? 'Cập nhật' : 'Lưu' }}
                </button>
                <!-- Delete Button -->
                <button @click="handleDelete"
                  class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                  Xóa
                </button>
              </div>
            </div>

            <!-- Form Content Fields stacked in rows matching mockup -->
            <div class="flex flex-col gap-3 py-3 overflow-y-auto flex-1">
              <!-- Row 1: Mã (1/3) & Mô tả (2/3) -->
              <div class="flex gap-4">
                <div class="w-1/3">
                  <label class="block text-xs font-bold text-gray-900 mb-1">Mã</label>
                  <input type="text" v-model="rateFormState.Ma"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                </div>
                <div class="w-2/3">
                  <label class="block text-xs font-bold text-gray-900 mb-1">Mô tả</label>
                  <input type="text" v-model="rateFormState.Description"
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                </div>
              </div>

              <!-- Row 2: Từ ngày - đến ngày (45%) & Tiền tệ (20%) & Ăn sáng checkbox (35%) -->
              <div class="flex gap-4 items-end">
                <div class="w-[45%] flex gap-2">
                  <div class="w-1/2">
                    <label class="block text-xs font-bold text-gray-900 mb-1">Từ ngày</label>
                    <input type="date" v-model="rateFormState.BeginDate"
                      class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                  </div>
                  <div class="w-1/2">
                    <label class="block text-xs font-bold text-gray-900 mb-1">Đến ngày</label>
                    <input type="date" v-model="rateFormState.EndDate"
                      class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                  </div>
                </div>

                <div class="w-[25%]">
                  <label class="block text-xs font-bold text-gray-900 mb-1">Tiền tệ</label>
                  <button type="button" @click="showCurrencyModal = true"
                    class="w-full flex items-center gap-2 px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold bg-white hover:border-sky-300 transition-colors cursor-pointer text-left">
                    <span class="text-base">{{ selectedCurrencyObj.flag }}</span>
                    <span class="font-bold text-gray-900">{{ rateFormState.Currency }}</span>
                    <svg class="w-3.5 h-3.5 text-slate-400 ml-auto" fill="none" stroke="currentColor" stroke-width="2"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>

                <div class="w-[30%] pb-2 pl-2">
                  <label
                    class="inline-flex items-center gap-1.5 text-xs text-gray-900 font-semibold cursor-pointer select-none">
                    <input type="checkbox" v-model="rateFormState.IncludeBF"
                      class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                    Ăn sáng
                  </label>
                </div>
              </div>

              <!-- Row 3: Remaining Checkboxes Grouped Inline -->
              <div class="flex flex-wrap gap-x-5 gap-y-2 items-center pt-2 border-t border-slate-100">
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-gray-900 font-semibold cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.IsDaily"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Giá theo ngày
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-gray-900 font-semibold cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.Disable"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Không sử dụng
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-gray-900 font-semibold cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.AllowChangeRate"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Cho phép nhập giá
                </label>
                <label
                  class="inline-flex items-center gap-1.5 text-xs text-gray-900 font-semibold cursor-pointer select-none">
                  <input type="checkbox" v-model="rateFormState.IsChannelManager"
                    class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  Đẩy lên Channel Manager
                </label>
              </div>

              <!-- Row 4: Daily-only fields: Ngày áp dụng + Weekday checkboxes + Loại giá -->
              <div v-if="rateFormState.IsDaily" class="flex flex-col gap-3 pt-2 border-t border-slate-200">
                <div class="flex gap-4 items-end flex-wrap">
                  <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-gray-900 whitespace-nowrap">Ngày áp dụng</label>
                    <input type="date" v-model="batchFromDate"
                      class="border border-slate-200 px-2 py-1.5 rounded text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                    <span class="text-xs text-slate-400">~</span>
                    <input type="date" v-model="batchToDate"
                      class="border border-slate-200 px-2 py-1.5 rounded text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                  </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[1]" class="rounded text-sky-500" />T2</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[2]" class="rounded text-sky-500" />T3</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[3]" class="rounded text-sky-500" />T4</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[4]" class="rounded text-sky-500" />T5</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[5]" class="rounded text-sky-500" />T6</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[6]" class="rounded text-sky-500" />T7</label>
                  <label
                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-900 cursor-pointer"><input
                      type="checkbox" v-model="batchDaysOfWeek[0]" class="rounded text-sky-500" />CN</label>

                  <div class="flex items-center gap-2 border-l border-slate-300 pl-3 ml-2">
                    <span class="text-xs font-bold text-gray-900">Loại giá</span>
                    <select v-model="batchRateType"
                      class="border border-slate-200 px-2 py-1.5 rounded text-xs focus:outline-sky-400 w-28 bg-white font-bold text-gray-900">
                      <option v-if="availableRatePlans.length === 0" value="" disabled>Trống</option>
                      <option v-for="plan in availableRatePlans" :key="plan.Code" :value="plan.Code">{{ plan.Code }}
                      </option>
                    </select>
                    <button @click="openRatePlanModal"
                      class="bg-sky-400 hover:bg-sky-500 text-white w-6 h-6 rounded-full flex items-center justify-center font-bold text-sm"
                      title="Quản lý các loại giá">+</button>
                  </div>

                  <button @click="applyBatchUpdate"
                    class="ml-auto px-4 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer shadow-sm">
                    Áp dụng
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Room rate matrix values with full table grid lines -->
        <div v-if="!rateFormState.IsDaily"
          class="h-[520px] shrink-0 bg-white border border-slate-200 rounded-xl p-4 flex flex-col shadow-xs">
          <div class="flex-1 overflow-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr
                  class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold uppercase sticky top-0 z-10 text-center">
                  <th class="p-2.5 text-left w-36 border border-slate-200 bg-slate-50">Loại phòng</th>
                  <th class="p-2.5 text-left w-48 border border-slate-200 bg-slate-50">Mô tả</th>
                  <th v-for="occ in occupancies" :key="occ" class="p-2.5 border border-slate-200 w-32">{{ occ }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rt in roomTypes" :key="rt.code" class="hover:bg-slate-50/40">
                  <!-- Room type code -->
                  <td class="p-2.5 font-bold text-gray-900 border border-slate-200 bg-slate-50/20">{{ rt.code }}</td>
                  <!-- Room type description -->
                  <td class="p-2.5 text-gray-900 font-semibold border border-slate-200">{{ rt.description }}</td>

                  <!-- Occupancies pricing inputs inside grid -->
                  <td v-for="occ in occupancies" :key="rt.code + '-' + occ" class="p-1 border border-slate-200">
                    <input 
                      type="text" 
                      :value="formatCurrencyInput(rateMatrix[getMatrixKey(rt.code, occ)], rateFormState.Currency)"
                      @input="e => rateMatrix[getMatrixKey(rt.code, occ)] = cleanCurrencyValue(e.target.value, rateFormState.Currency)"
                      placeholder="-"
                      class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold bg-white text-gray-900 transition-colors" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else class="h-[520px] shrink-0 bg-white border border-slate-200 rounded-xl p-0 flex flex-col shadow-xs">
          <!-- Lưới giá theo ngày (Daily Grid) - giống hệ thống cũ -->
          <div class="flex-1 overflow-auto">
            <table class="text-xs text-left border-collapse min-w-[1200px] border border-slate-200">
              <thead class="bg-slate-100 sticky top-0 z-20">
                <!-- Header Row 1: Room Type Groups -->
                <tr class="text-gray-900">
                  <th rowspan="2"
                    class="p-2 border border-slate-200 w-28 text-center bg-slate-100 sticky left-0 z-30 font-bold">Ngày
                  </th>
                  <th rowspan="2" class="p-2 border border-slate-200 w-10 text-center bg-slate-100 font-bold"></th>
                  <th rowspan="2" class="p-2 border border-slate-200 w-16 text-center bg-slate-100 font-bold">Mã</th>
                  <th v-for="rt in roomTypes" :key="rt.code" :colspan="occupancies.length"
                    class="p-2 border border-slate-200 text-center font-bold text-gray-900 bg-slate-100">
                    {{ rt.code }}
                  </th>
                </tr>
                <!-- Header Row 2: Occupancy sub-columns -->
                <tr>
                  <template v-for="rt in roomTypes" :key="'occ-' + rt.code">
                    <th v-for="occ in occupancies" :key="rt.code + '-' + occ"
                      class="p-1.5 border border-slate-200 text-center text-[10px] font-semibold text-gray-900 bg-slate-50 w-20">
                      {{ occ }}
                    </th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="mapping in paginatedDailyMappings" :key="mapping.Date"
                  class="border-b border-slate-100 hover:bg-sky-100 transition-colors" :class="{
                    'bg-emerald-50/60 font-bold': mapping.Date === getTodayString(),
                    'bg-sky-50/30': mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                    'text-rose-500': mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                    'text-orange-500': mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 6
                  }">
                  <td
                    class="p-2 border border-slate-100 font-semibold text-gray-900 text-center whitespace-nowrap sticky left-0 z-10"
                    :class="{
                      'bg-emerald-100 text-emerald-900 font-bold': mapping.Date === getTodayString(),
                      'bg-sky-50/30': mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) === 0,
                      'bg-white': mapping.Date !== getTodayString() && getDayOfWeekFromDate(mapping.Date) !== 0
                    }">
                    {{ formatDateVN(mapping.Date) }}
                  </td>
                  <td class="p-2 border border-slate-100 text-center font-semibold text-gray-900 text-[10px]"
                    :class="{
                      'bg-emerald-100/50 text-emerald-900': mapping.Date === getTodayString(),
                      'text-rose-500': mapping.Date !== getTodayString() && (getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6)
                    }">
                    {{ getDayLabel(getDayOfWeekFromDate(mapping.Date)) }}
                  </td>
                  <td class="p-1.5 border border-slate-100 text-center">
                    <select :value="mapping.Code" @change="e => updateSingleMapping(mapping.Date, e.target.value)"
                      class="border px-1 py-0.5 rounded text-xs focus:outline-sky-400 bg-white"
                      :class="[getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6 ? 'text-red-600 font-bold border-red-200' : 'text-gray-900 font-semibold border-slate-200']">
                      <option value="-">-</option>
                      <option v-for="plan in availableRatePlans" :key="plan.Code" :value="plan.Code">{{ plan.Code }}
                      </option>
                    </select>
                  </td>
                  <template v-for="rt in roomTypes" :key="'val-' + rt.code">
                    <td v-for="occ in occupancies" :key="`${mapping.Date}-${rt.code}-${occ}`"
                      class="p-0.5 border border-slate-100">
                      <span class="block text-center text-[11px] py-1"
                        :class="[getDayOfWeekFromDate(mapping.Date) === 0 || getDayOfWeekFromDate(mapping.Date) === 6 ? 'text-red-600 font-bold' : 'text-gray-900 font-semibold']">
                        {{ mapping.Code !== '-' ? (formatCurrencyInput(rateMatrix[`${mapping.Code}_${rt.code}_${occ}`],
                          rateFormState.Currency) || '-') : '-' }}
                      </span>
                    </td>
                  </template>
                </tr>
                <tr v-if="paginatedDailyMappings.length === 0">
                  <td :colspan="3 + roomTypes.length * occupancies.length"
                    class="p-8 text-center text-gray-900 font-semibold text-sm">
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
      <div v-else-if="activeRateTab === 'Gói dịch vụ'" class="flex-1 flex flex-col gap-4 min-h-0">

        <!-- Top Half: Gói dịch vụ list -->
        <div class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <h3 class="text-xl font-bold text-gray-900 uppercase tracking-wide">Gói dịch vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Search Mock Button -->
              <button
                class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-gray-900 cursor-pointer bg-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </button>
              <!-- Add Button -->
              <button
                class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button
                class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                Sửa
              </button>
              <!-- Delete Button (using scoped CSS class) -->
              <button
                class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-3">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2.5 border border-slate-200 w-24">Mã</th>
                  <th class="p-2.5 border border-slate-200 w-20 text-center">Tiền tệ</th>
                  <th class="p-2.5 border border-slate-200 w-24 text-center">Số đêm</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-right">Giá hiển thị</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Ngày bắt đầu</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Ngày mở khóa</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Không sử dụng</th>
                  <th class="p-2.5 border border-slate-200">Theo ngày trong tuần</th>
                  <th class="p-2.5 border border-slate-200">Mô tả</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="pkg in servicePackages" :key="pkg.id" @click="selectPackage(pkg)"
                  class="hover:bg-sky-100 cursor-pointer transition-colors"
                  :class="selectedPackage?.id === pkg.id ? 'bg-sky-50/40 font-bold text-sky-700' : 'text-gray-900 font-semibold'">
                  <td class="p-2.5 border border-slate-200 font-bold">{{ pkg.code }}</td>
                  <td class="p-2.5 border border-slate-200 text-center">{{ pkg.currency }}</td>
                  <td class="p-2.5 border border-slate-200 text-center">{{ pkg.nights }}</td>
                  <td class="p-2.5 border border-slate-200 text-right font-bold text-sky-600">{{ pkg.displayPrice }}
                  </td>
                  <td class="p-2.5 border border-slate-200 text-center text-gray-900 font-semibold">{{ pkg.startDate }}
                  </td>
                  <td class="p-2.5 border border-slate-200 text-center text-gray-900 font-semibold">{{ pkg.endDate }}
                  </td>
                  <td class="p-2.5 border border-slate-200 text-center">
                    <input type="checkbox" :checked="pkg.inactive" @click.stop
                      class="rounded border-slate-300 text-sky-500 focus:ring-sky-400" />
                  </td>
                  <td class="p-2.5 border border-slate-200 text-gray-900 font-semibold truncate max-w-[200px]"
                    :title="pkg.daysOfWeek">{{ pkg.daysOfWeek }}</td>
                  <td class="p-2.5 border border-slate-200 text-gray-900 font-semibold">{{ pkg.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Bottom Half: Chi tiết gói dịch vụ -->
        <div
          class="flex-1 bg-white border border-slate-200 rounded-xl p-4 flex flex-col min-h-0 shadow-xs justify-between">
          <!-- Header with buttons -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
            <h3 class="text-xl font-bold text-gray-900 uppercase tracking-wide">Chi tiết gói dịch vụ</h3>
            <div class="flex items-center gap-1.5">
              <!-- Add Button -->
              <button
                class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#78bce8] text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                <span class="text-sm">+</span> Thêm
              </button>
              <!-- Edit Button -->
              <button
                class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-sm">
                Sửa
              </button>
              <!-- Delete Button (using scoped CSS class) -->
              <button
                class="px-3 py-1.5 btn-delete rounded-lg text-xs font-bold cursor-pointer flex items-center gap-1 shadow-sm">
                Xóa
              </button>
            </div>
          </div>

          <!-- Table Container with grid lines -->
          <div class="flex-1 overflow-y-auto mt-3">
            <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold uppercase sticky top-0 z-10">
                  <th class="p-2.5 border border-slate-200">Thời gian hiệu lực</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Từ ngày</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Đến ngày</th>
                  <th class="p-2.5 border border-slate-200">Dịch vụ</th>
                  <th class="p-2.5 border border-slate-200">Bộ phận</th>
                  <th class="p-2.5 border border-slate-200 text-center">Gói bữa ăn</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-right">Số tiền</th>
                  <th class="p-2.5 border border-slate-200 w-32 text-center">Tính trên</th>
                  <th class="p-2.5 border border-slate-200">Loại phòng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in packageDetails" :key="detail.id"
                  class="border-b border-slate-100 hover:bg-sky-100 text-gray-900 font-semibold">
                  <td class="p-2.5 border border-slate-200 font-semibold">{{ detail.validity }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-gray-900 font-semibold">{{ detail.fromDate
                    }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-gray-900 font-semibold">{{ detail.endDate }}
                  </td>
                  <td class="p-2.5 border border-slate-200 font-bold text-gray-900">{{ detail.service }}</td>
                  <td class="p-2.5 border border-slate-200 text-gray-900 font-semibold">{{ detail.department }}</td>
                  <td class="p-2.5 border border-slate-200 text-center"><span
                      class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded text-[10px] font-bold uppercase">{{
                      detail.mealPlan }}</span></td>
                  <td class="p-2.5 border border-slate-200 text-right font-bold text-gray-900">{{ detail.amount }}</td>
                  <td class="p-2.5 border border-slate-200 text-center text-gray-900 font-semibold">{{
                    detail.calculatedOn }}
                  </td>
                  <td class="p-2.5 border border-slate-200 font-bold text-indigo-600">{{ detail.roomClass }}</td>
                </tr>
                <tr v-if="packageDetails.length === 0">
                  <td colspan="9" class="p-8 text-center text-gray-900 font-semibold text-sm">Không có dữ liệu chi tiết
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total Footer Row -->
          <div
            class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-2.5 flex justify-between items-center text-xs font-bold text-gray-900 shrink-0">
            <span>Tổng</span>
            <span class="text-sm text-sky-600 font-extrabold pr-32">{{ formatNumber(totalPackageDetailsAmount) }}</span>
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
          class="relative bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-full max-h-[90vh] flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 bg-sky-400 text-white shrink-0">
            <span class="text-xl font-bold text-white">Quản lý Các Loại Giá</span>
            <button @click="closeRatePlanModal"
              class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
                    <th class="p-2 border border-slate-200 text-gray-900 font-bold">Mã</th>
                    <th class="p-2 border border-slate-200 text-gray-900 font-bold">Mô tả</th>
                    <th class="p-2 border border-slate-200 text-gray-900 font-bold text-center">Từ ngày</th>
                    <th class="p-2 border border-slate-200 text-gray-900 font-bold text-center">Đến ngày</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="plan in modalRatePlans" :key="plan.Code" @click="selectModalPlan(plan)"
                    class="cursor-pointer hover:bg-sky-50 transition-colors"
                    :class="{ 'bg-sky-100': modalFormState.Code === plan.Code }">
                    <td class="p-2 border border-slate-200 font-bold text-gray-900">{{ plan.Code }}</td>
                    <td class="p-2 border border-slate-200 text-gray-900 font-semibold">{{ plan.Description }}</td>
                    <td class="p-2 border border-slate-200 text-center text-gray-900 font-semibold">{{
                      formatDateVN(plan.BeginDate) }}</td>
                    <td class="p-2 border border-slate-200 text-center text-gray-900 font-semibold">{{
                      formatDateVN(plan.EndDate) }}</td>
                  </tr>
                  <tr v-if="modalRatePlans.length === 0">
                    <td colspan="4" class="p-6 text-center text-gray-900 font-semibold text-sm">Chưa có loại giá nào
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Right: Form -->
            <div class="w-1/2 p-5 flex flex-col">
              <div class="flex justify-end gap-2 mb-6">
                <button @click="resetModalForm"
                  class="px-4 py-1.5 rounded-md text-xs font-bold border border-slate-200 hover:bg-slate-50 text-gray-900 transition-colors cursor-pointer">Thêm
                  Mới</button>
                <button @click="saveModalPlan"
                  class="px-5 py-1.5 bg-sky-400 hover:bg-sky-500 text-white rounded-md text-xs font-bold border-none transition-colors cursor-pointer shadow-sm">
                  {{ isModalEditing ? 'Cập nhật' : 'Lưu' }}
                </button>
                <button v-if="isModalEditing" @click="deleteModalPlan"
                  class="px-5 py-1.5 bg-slate-400 hover:bg-slate-500 text-white rounded-md text-xs font-bold border-none transition-colors cursor-pointer shadow-sm">
                  Xóa
                </button>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold text-gray-900">Mã</label>
                  <input type="text" v-model="modalFormState.Code" :disabled="isModalEditing"
                    class="border border-slate-200 rounded p-1.5 text-xs focus:outline-sky-400 disabled:bg-slate-100 font-semibold text-gray-900" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold text-gray-900">Mô tả</label>
                  <input type="text" v-model="modalFormState.Description"
                    class="border border-slate-200 rounded p-1.5 text-xs focus:outline-sky-400 font-semibold text-gray-900" />
                </div>
              </div>

              <div class="flex flex-col gap-1.5 mt-4">
                <label class="text-xs font-bold text-gray-900">Từ ngày - đến ngày</label>
                <div class="flex items-center gap-2">
                  <input type="date" v-model="modalFormState.BeginDate"
                    class="border border-slate-200 rounded p-1.5 text-xs focus:outline-sky-400 w-full font-semibold text-gray-900" />
                  <span class="text-slate-400">~</span>
                  <input type="date" v-model="modalFormState.EndDate"
                    class="border border-slate-200 rounded p-1.5 text-xs focus:outline-sky-400 w-full font-semibold text-gray-900" />
                </div>
              </div>
            </div>
          </div>

          <!-- Content: Bottom (Matrix Grid) -->
          <div class="flex-1 relative bg-white flex flex-col min-h-0">
            <div v-if="!modalFormState.Code"
              class="absolute inset-0 z-20 bg-white/60 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
              <span
                class="bg-slate-800 text-white px-4 py-2 rounded-lg font-semibold text-sm shadow-xl pointer-events-auto">Vui
                lòng nhập Mã loại giá để tiếp tục</span>
            </div>
            <div class="flex-1 p-4 overflow-auto">
              <table class="w-full text-xs text-left border-collapse min-w-[800px] border border-slate-200">
                <thead>
                  <tr
                    class="bg-slate-100 border-b border-slate-200 text-gray-900 font-bold sticky top-0 z-10 text-center">
                    <th class="p-2 border border-slate-200 bg-slate-100 text-left">Loại phòng</th>
                    <th class="p-2 border border-slate-200 bg-slate-100 text-left">Mô tả</th>
                    <th class="p-2 border border-slate-200">Double</th>
                    <th class="p-2 border border-slate-200">Twin</th>
                    <th class="p-2 border border-slate-200">Triple</th>
                    <th class="p-2 border border-slate-200">Family</th>
                    <th class="p-2 border border-slate-200">King</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rt in roomTypes" :key="rt.code" class="hover:bg-slate-50/50">
                    <td class="p-2 font-bold text-gray-900 border border-slate-200 bg-slate-50">{{ rt.code }}</td>
                    <td class="p-2 text-gray-900 font-semibold border border-slate-200">{{ rt.description }}</td>

                    <td class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, 'Double')], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, 'Double')] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold text-gray-900 transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                    </td>
                    <td class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, 'Twin')], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, 'Twin')] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold text-gray-900 transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                    </td>
                    <td class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, 'Triple')], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, 'Triple')] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold text-gray-900 transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                    </td>
                    <td class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, 'Family')], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, 'Family')] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold text-gray-900 transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
                    </td>
                    <td class="p-1 border border-slate-200">
                      <input type="text" :disabled="!modalFormState.Code"
                        :value="formatCurrencyInput(modalRateMatrix[getModalMatrixKey(rt.code, 'King')], selectedRateCode?.Currency)"
                        @input="e => modalRateMatrix[getModalMatrixKey(rt.code, 'King')] = cleanCurrencyValue(e.target.value, selectedRateCode?.Currency)"
                        class="w-full px-2 py-1.5 border border-slate-100 hover:border-slate-300 focus:border-sky-300 rounded text-center text-xs focus:outline-none font-semibold text-gray-900 transition-colors disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed" />
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
        <div class="relative bg-white rounded-2xl shadow-2xl w-[520px] max-h-[80vh] flex flex-col overflow-hidden">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3.5 bg-sky-500 text-white shrink-0">
            <span class="text-xl font-bold text-white">Chọn tiền tệ</span>
            <button @click="showCurrencyModal = false; currencySearch = ''"
              class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
