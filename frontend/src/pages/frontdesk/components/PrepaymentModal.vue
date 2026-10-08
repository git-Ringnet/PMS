<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown, X, Plus, Save } from '@lucide/vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import http from '@/services/http'
import { fetchBankAccounts as fetchConfiguredBankAccounts } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: {
    type: [String, Number],
    default: ''
  },
  bookingCode: {
    type: String,
    default: ''
  },
  bookingName: {
    type: String,
    default: ''
  },
  selectedRoomId: {
    type: [String, Number],
    default: null
  },
  selectedRoomNumber: {
    type: String,
    default: ''
  },
  selectedGuestId: {
    type: [String, Number],
    default: null
  },
  folioId: {
    type: [String, Number],
    default: 1
  },
  systemDate: {
    type: String,
    default: ''
  },
  roomOptions: {
    type: Array,
    default: () => []
  },
  deposits: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'success'])
const uiStore = useUiStore()

const amount = ref(0)
const selectedTargetRoomId = ref(null)
const paymentMethodId = ref('')
const paymentMethods = ref([])
const bankAccountOptions = ref([])
const selectedBankAccount = ref('')
const description = ref('')
const workShift = ref('')
const timeStr = ref(nowTimeStr())
const lastValidTimeStr = ref(timeStr.value)
const dateStr = ref(props.systemDate || todayDateStr())
const workShiftsList = ref([])
const shiftLoadState = ref('idle')
const shiftTimeError = ref('')
const currency = ref('VND')
const isSubmitting = ref(false)
const errorMsg = ref('')

function handleEscape(event) {
  if (event.key === 'Escape' && props.show && !isSubmitting.value) handleClose()
}

function formatMoney(value) {
  const number = Number(value)
  if (!Number.isFinite(number)) return '0'
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(number)
}

const displayAmount = computed({
  get() {
    if (amount.value === 0 || amount.value === '0' || !amount.value) return '0'
    const num = Number(amount.value)
    return formatMoney(num)
  },
  set(val) {
    if (!val) {
      amount.value = 0
      return
    }
    const cleanStr = String(val).replace(/,/g, '').replace(/[^0-9]/g, '')
    amount.value = cleanStr ? Number(cleanStr) : 0
  }
})

function nowTimeStr() {
  const d = new Date()
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

function todayDateStr() {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function normalizeShiftTime(value) {
  const match = String(value || '').match(/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/)
  return match ? `${match[1]}:${match[2]}` : ''
}

function shiftMinuteOfDay(value) {
  const normalized = normalizeShiftTime(value)
  if (!normalized) return null
  const [hours, minutes] = normalized.split(':').map(Number)
  return hours * 60 + minutes
}

const selectedWorkShift = computed(() => workShiftsList.value.find(
  item => String(item.id ?? item.name) === String(workShift.value)
))
const shiftTimeMin = computed(() => {
  const shift = selectedWorkShift.value
  const start = normalizeShiftTime(shift?.start_time)
  const end = normalizeShiftTime(shift?.end_time)
  return start && start !== end ? start : undefined
})
const shiftTimeMax = computed(() => {
  const start = shiftMinuteOfDay(selectedWorkShift.value?.start_time)
  const end = shiftMinuteOfDay(selectedWorkShift.value?.end_time)
  if (start === null || end === null || start === end) return undefined
  const lastMinute = (end + 1439) % 1440
  return `${String(Math.floor(lastMinute / 60)).padStart(2, '0')}:${String(lastMinute % 60).padStart(2, '0')}`
})

function timeMatchesShift(timeValue, shift) {
  const time = shiftMinuteOfDay(timeValue)
  const start = shiftMinuteOfDay(shift?.start_time)
  const end = shiftMinuteOfDay(shift?.end_time)
  if (time === null || start === null || end === null) return false
  if (start === end) return true
  return start < end ? time >= start && time < end : time >= start || time < end
}

function isShiftTimeAllowed() {
  return shiftLoadState.value === 'ready' && Boolean(selectedWorkShift.value && timeMatchesShift(timeStr.value, selectedWorkShift.value))
}

function syncShiftFromTime() {
  if (shiftLoadState.value !== 'ready') return
  const matchingShift = workShiftsList.value.find(shift => timeMatchesShift(timeStr.value, shift))
  workShift.value = matchingShift ? String(matchingShift.id ?? matchingShift.name) : ''
  if (matchingShift) {
    lastValidTimeStr.value = timeStr.value
    shiftTimeError.value = ''
  }
}

function handleWorkShiftChange() {
  const shift = selectedWorkShift.value
  if (!shift) return
  if (!timeMatchesShift(timeStr.value, shift)) timeStr.value = normalizeShiftTime(shift.start_time)
  lastValidTimeStr.value = timeStr.value
  shiftTimeError.value = ''
}

function handleTimeChange() {
  if (shiftLoadState.value !== 'ready') return
  const matchingShift = selectedWorkShift.value && timeMatchesShift(timeStr.value, selectedWorkShift.value)
    ? selectedWorkShift.value
    : workShiftsList.value.find(shift => timeMatchesShift(timeStr.value, shift))
  if (!matchingShift) {
    timeStr.value = lastValidTimeStr.value || normalizeShiftTime(selectedWorkShift.value?.start_time)
    shiftTimeError.value = 'Giờ phải nằm trong thời gian của một ca đã cấu hình.'
    return
  }
  workShift.value = String(matchingShift.id ?? matchingShift.name)
  lastValidTimeStr.value = timeStr.value
  shiftTimeError.value = ''
}

const registrationDisplay = computed(() => {
  if (props.bookingCode && props.bookingName) {
    return `${props.bookingCode} - ${props.bookingName}`
  }
  return props.bookingName || props.bookingCode || 'Select Value'
})

const depositRows = computed(() => props.deposits.filter(deposit => (
  String(deposit.pack4 || '').toUpperCase() === 'AP'
  && Number(deposit.edit_flag || 0) === 0
  && !deposit.deleted_at
)))

const formatDepositDate = (value) => {
  if (!value) return '--'
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : String(value)
}

const formatDepositAmount = (value) => formatMoney(value)

const isBankTransfer = computed(() => {
  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  if (!selectedMethod) return false
  const code = String(selectedMethod.code || '').toUpperCase()
  const name = String(selectedMethod.name || '').toLowerCase()
  const bankName = String(selectedMethod.bank_name || '').toLowerCase()
  return code === 'BT' || name.includes('bank') || name.includes('chuyển khoản') || name.includes('transfer') || bankName.includes('transfer')
})

const selectedBankAccountDetails = computed(() => bankAccountOptions.value.find(account => (
  String(account.id) === String(selectedBankAccount.value)
)) || null)

const fetchPaymentMethods = async () => {
  try {
    const res = await http.get('/payment-methods')
    const list = res.data?.data || res.data || []
    paymentMethods.value = list.filter(m => !inGroupExcluded(m))
    if (paymentMethods.value.length > 0 && !paymentMethodId.value) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
    await fetchBankAccounts()
  } catch (err) {
    console.error('Lỗi khi tải danh sách HTTT:', err)
  }
}

const fetchBankAccounts = async () => {
  try {
    const res = await fetchConfiguredBankAccounts({ is_intermediary: false, is_active: true })
    bankAccountOptions.value = (res.data?.data || res.data || [])
      .filter(account => account.is_active !== false && !account.is_intermediary)
      .map(account => ({
        ...account,
        display: [account.bank_name, account.bank_account_number, account.code]
          .filter(Boolean)
          .join(' - ')
      }))
  } catch (err) {
    console.error('Lỗi khi tải tài khoản ngân hàng:', err)
  }
}

let shiftRequestId = 0
const fetchWorkShifts = async () => {
  const requestId = ++shiftRequestId
  shiftLoadState.value = 'loading'
  workShiftsList.value = []
  workShift.value = ''
  try {
    const res = await http.get('/shifts')
    const list = res.data?.data || res.data || []
    if (requestId !== shiftRequestId) return
    workShiftsList.value = Array.isArray(list)
      ? list.filter(shift => normalizeShiftTime(shift?.start_time) && normalizeShiftTime(shift?.end_time))
      : []
    if (workShiftsList.value.length === 0) {
      shiftLoadState.value = 'missing'
      errorMsg.value = 'Chưa có cấu hình ca làm việc hợp lệ. Vui lòng kiểm tra mục Ca làm việc.'
      return
    }
    shiftLoadState.value = 'ready'
    syncShiftFromTime()
  } catch (err) {
    if (requestId !== shiftRequestId) return
    shiftLoadState.value = 'error'
    errorMsg.value = 'Không tải được cấu hình ca làm việc. Vui lòng thử lại trước khi lưu.'
    console.warn('Không thể nạp danh sách ca làm việc từ API.')
  }
}

function inGroupExcluded(m) {
  const grp = Number(m.payment_group)
  return grp === 4 || grp === 5 || m.is_free === 1 || m.is_free === true
}

watch(() => props.show, (visible) => {
  if (visible) {
    amount.value = 0
    errorMsg.value = ''
    selectedBankAccount.value = ''
    timeStr.value = nowTimeStr()
    lastValidTimeStr.value = timeStr.value
    shiftTimeError.value = ''
    dateStr.value = props.systemDate || todayDateStr()
    workShift.value = ''
    fetchWorkShifts()
    document.addEventListener('keydown', handleEscape)
    const rawRId = props.selectedRoomId
    selectedTargetRoomId.value = (rawRId !== null && rawRId !== undefined && rawRId !== '' && rawRId !== 'null') ? rawRId : null
    if (paymentMethods.value.length === 0) {
      fetchPaymentMethods()
    } else if (!paymentMethodId.value && paymentMethods.value[0]) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
    updateDefaultDescription()
  } else {
    document.removeEventListener('keydown', handleEscape)
  }
}, { immediate: true })

watch(isBankTransfer, (bankTransfer) => {
  if (!bankTransfer) selectedBankAccount.value = ''
})

watch(paymentMethodId, () => {
  updateDefaultDescription()
})

function updateDefaultDescription() {
  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  const methodName = selectedMethod ? selectedMethod.name : 'Cash'
  description.value = `Advance Payment (${methodName})`
}

const handleSubmit = async () => {
  errorMsg.value = ''
  if (!amount.value || Number(amount.value) <= 0) {
    errorMsg.value = 'Vui lòng nhập số tiền hợp lệ (> 0).'
    return
  }
  if (!paymentMethodId.value) {
    errorMsg.value = 'Vui lòng chọn hình thức thanh toán.'
    return
  }
  if (!dateStr.value) {
    errorMsg.value = 'Vui lòng nhập ngày thanh toán.'
    return
  }
  if (!props.bookingId) {
    errorMsg.value = 'Không tìm thấy thông tin Booking.'
    return
  }
  if (shiftLoadState.value !== 'ready') {
    errorMsg.value = shiftLoadState.value === 'loading'
      ? 'Đang tải cấu hình ca làm việc. Vui lòng chờ.'
      : 'Chưa có cấu hình ca làm việc hợp lệ. Vui lòng kiểm tra mục Ca làm việc.'
    return
  }
  if (shiftTimeError.value || !isShiftTimeAllowed()) {
    if (!shiftTimeError.value) shiftTimeError.value = 'Giờ phải nằm trong thời gian của ca đã chọn.'
    return
  }

  isSubmitting.value = true
  try {
    const targetRoomId = (selectedTargetRoomId.value !== null && selectedTargetRoomId.value !== undefined && selectedTargetRoomId.value !== '' && selectedTargetRoomId.value !== 'null')
      ? selectedTargetRoomId.value
      : null

    const finalDesc = description.value.trim() || `Advance Payment`

    const payload = {
      booking_id: props.bookingId,
      booking_room_id: targetRoomId,
      guest_id: props.selectedGuestId || null,
      amount: Number(amount.value),
      payment_method_id: paymentMethodId.value,
      description: finalDesc,
      date: dateStr.value,
      open_time: timeStr.value,
      currency: currency.value,
      shift_id: workShift.value,
      department_id: 'FO',
      folio_id: Number(props.folioId) || 1,
      bank_account_id: isBankTransfer.value ? (selectedBankAccountDetails.value?.id || null) : null,
      debit_account: isBankTransfer.value ? (selectedBankAccountDetails.value?.accounting_account || null) : null,
      pack4: 'AP'
    }

    const res = await http.post(`/bookings/${props.bookingId}/payments`, payload)
    if (res.data?.success) {
      amount.value = 0
      updateDefaultDescription()
      emit('success', res.data.data)
    } else {
      errorMsg.value = res.data?.message || 'Không thể lưu thanh toán trước.'
    }
  } catch (err) {
    const response = err.response
    const backendMsg = response?.data?.message
    const validationErrors = response?.data?.errors ? Object.values(response.data.errors).flat().join('; ') : ''
    errorMsg.value = backendMsg || validationErrors || (response?.status === 403
      ? 'Bạn không có quyền thực hiện thao tác thanh toán này.'
      : 'Có lỗi xảy ra khi lưu thanh toán trước.')
  } finally {
    isSubmitting.value = false
  }
}

const handleClose = () => {
  emit('close')
}

onMounted(() => {
  fetchPaymentMethods()
})

onBeforeUnmount(() => document.removeEventListener('keydown', handleEscape))
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 md:p-4 animate-fadeIn select-none font-sans">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl overflow-hidden border border-sky-400 flex flex-col text-xs">
      
      <!-- Header (Màu xanh dương mạ #0088ff chuẩn Ảnh 2) -->
      <div class="text-white px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-xs" style="background: var(--pms-custom-theme, #006bdb)">
        <span class="text-xs font-semibold tracking-wide">Thanh toán trước</span>
        <button @click="handleClose" class="hover:bg-white/20 p-1 rounded transition-colors text-white cursor-pointer" title="Đóng">
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Body Content (Rộng rãi, không chật chội hay trùng đè) -->
      <div class="p-4 space-y-4 bg-gray-50/50">
        <div v-if="errorMsg" class="p-2.5 bg-red-50 border border-red-200 text-red-600 rounded text-xs font-semibold">
          {{ errorMsg }}
        </div>

        <!-- HÀNG 1: Tên đăng ký & Hình thức thanh toán (kèm Tài khoản ngân hàng nếu chọn CK) -->
        <div class="grid grid-cols-12 gap-4 items-end">
          <!-- Tên đăng ký (Trái - 7 cols đồng bộ với cột thông tin thanh toán bên dưới) -->
          <div class="col-span-7">
            <label class="block font-semibold text-gray-700 mb-1 text-xs">Tên đăng ký</label>
            <select disabled class="w-full px-2.5 py-1.5 bg-gray-100 border border-gray-300 rounded font-medium text-gray-700 focus:outline-none cursor-not-allowed text-xs">
              <option :value="registrationDisplay">{{ registrationDisplay }}</option>
            </select>
          </div>

          <!-- Hình thức thanh toán & Tài khoản ngân hàng (Phải - 5 cols đồng bộ với Mô tả) -->
          <div class="col-span-5">
            <div class="grid grid-cols-12 gap-2">
              <!-- HTTT -->
              <div :class="isBankTransfer ? 'col-span-6' : 'col-span-12'">
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Hình thức thanh toán <span class="text-red-500">*</span></label>
                <div class="flex gap-1">
                  <div class="relative flex-1 min-w-0">
                  <select v-model="paymentMethodId" class="w-full h-8 appearance-none pl-2 pr-12 bg-[#FFF8DB] border border-[#F1DD8A] rounded text-[#000000D9] font-normal focus:outline-none focus:border-yellow-500 text-xs truncate">
                    <option v-for="m in paymentMethods" :key="m.id || m.code" :value="m.id || m.code" class="bg-white">
                      {{ m.name }}
                    </option>
                  </select>
                  <button v-if="paymentMethodId" type="button" class="absolute right-7 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-red-500" title="Xóa hình thức thanh toán" @click="paymentMethodId = ''"><X class="h-3.5 w-3.5" /></button>
                  <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                  </div>
                  <button type="button" class="bg-sky-500 hover:bg-sky-600 text-white px-2 py-1.5 rounded font-bold shadow-xs transition-colors shrink-0" title="Thêm HTTT">
                    <Plus class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <!-- Ô Chọn Tài khoản ngân hàng (Nền vàng #ffffcc) -->
              <div v-if="isBankTransfer" class="col-span-6">
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Tài khoản ngân hàng</label>
                <select v-model="selectedBankAccount" class="w-full px-2 py-1.5 bg-[#ffffcc] border border-gray-300 rounded text-gray-900 font-medium focus:outline-none text-xs truncate" title="Tài khoản ngân hàng">
                  <option value="">-- Không chọn --</option>
                  <option v-for="b in bankAccountOptions" :key="b.id" :value="b.id">
                    {{ b.display }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- HÀNG 2 & 3: Số tiền, Phòng, Ca, Giờ, Ngày, Tiền tệ & Mô tả Textarea -->
        <div class="grid grid-cols-12 gap-4 items-stretch">
          
          <!-- Cột Trái: Số tiền, Phòng, Ca làm việc, Giờ, Ngày, Tiền tệ (7 cols rộng rãi) -->
          <div class="col-span-7 space-y-3 flex flex-col justify-between">
            <!-- Hàng Số tiền & Phòng -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Số tiền <span class="text-red-500">*</span></label>
                <div class="relative">
                <input 
                  type="text" 
                  v-model="displayAmount" 
                  placeholder="0"
                  class="w-full h-8 pl-2.5 pr-8 py-1.5 bg-[#FFF8DB] border border-[#F1DD8A] rounded font-normal text-[#000000D9] focus:outline-none focus:border-yellow-500 text-xs text-left tabular-nums tracking-wide"
                />
                <button v-if="amount" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-700" title="Xóa số tiền" @click="amount = 0"><X class="h-3.5 w-3.5" /></button>
                </div>
              </div>

              <div>
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Phòng</label>
                <div class="relative">
                <select v-model="selectedTargetRoomId" class="h-8 w-full appearance-none rounded border border-gray-300 bg-white py-1.5 pl-2.5 pr-12 text-xs font-normal text-[#000000D9] focus:outline-none">
                  <option :value="null">Master Header</option>
                  <option v-for="r in roomOptions" :key="r.roomId || r.id" :value="r.roomId || r.id">
                    Phòng {{ r.roomNumber || r.room_number || r.id }}
                  </option>
                </select>
                <button v-if="selectedTargetRoomId !== ''" type="button" class="absolute right-7 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-red-500" title="Xóa phòng" @click="selectedTargetRoomId = ''"><X class="h-3.5 w-3.5" /></button>
                <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                </div>
              </div>
            </div>

            <!-- Hàng Ca làm việc, Giờ, Ngày, Tiền tệ (Được phân bổ hợp lý, hiển thị trọn vẹn thông tin) -->
            <div class="grid grid-cols-[85px_155px_1fr_85px] gap-2 items-start">
              <!-- Ca làm việc (85px) -->
              <div class="min-w-0">
                <label class="block font-semibold text-[#000000D9] mb-1 text-xs">Ca <span class="text-red-500">*</span></label>
                <div class="relative">
                <select v-model="workShift" :disabled="shiftLoadState !== 'ready'" @change="handleWorkShiftChange" class="h-8 w-full appearance-none rounded border border-[#F1DD8A] bg-[#FFF8DB] py-1 pl-1.5 pr-8 text-center text-xs font-normal focus:border-yellow-500 focus:outline-none disabled:bg-gray-100">
                  <option v-for="shift in workShiftsList" :key="shift.id ?? shift.name" :value="String(shift.id ?? shift.name)" class="bg-white">
                    {{ shift.name }}
                  </option>
                </select>
                <button v-if="workShift && shiftLoadState === 'ready'" type="button" class="absolute right-5 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500" title="Xóa ca" @click="workShift = ''"><X class="h-3.5 w-3.5" /></button>
                <ChevronDown class="pointer-events-none absolute right-1.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
                </div>
              </div>

              <!-- Giờ (155px rộng rãi, hiển thị trọn vẹn cả 12h AM/PM và 24h, kèm icon đồng hồ & nút xóa) -->
              <div class="min-w-0">
                <label class="block font-semibold text-[#000000D9] mb-1 text-xs">Giờ <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="time" v-model="timeStr" :min="shiftTimeMin" :max="shiftTimeMax" :disabled="shiftLoadState !== 'ready' || !workShift" step="60" @change="handleTimeChange" class="w-full min-w-0 h-8 pl-2 pr-8 bg-[#FFF8DB] border border-[#F1DD8A] rounded text-center text-xs font-normal disabled:bg-gray-100 focus:outline-none focus:border-yellow-500" />
                  <button v-if="timeStr && shiftLoadState === 'ready' && workShift" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent flex items-center justify-center transition-colors" title="Xóa giờ" @click="timeStr = ''"><X class="h-3.5 w-3.5" /></button>
                </div>
                <p v-if="shiftTimeError" class="mt-0.5 text-xs leading-4 text-red-600 truncate">{{ shiftTimeError }}</p>
              </div>

              <!-- Ngày (1fr ~220px hiển thị đẹp mắt dd/mm/yyyy kèm icon lịch và nút xóa) -->
              <div class="min-w-0">
                <label class="block font-semibold text-[#000000D9] mb-1 text-xs">Ngày <span class="text-red-500">*</span></label>
                <SingleDatePicker v-model="dateStr" :max-date="systemDate || undefined" placeholder="dd/mm/yyyy" four-digit-year input-class="!h-8 !bg-[#FFF8DB] !border-[#F1DD8A] !text-xs !font-normal !px-2" text-input-class="!font-normal text-center" />
              </div>

              <!-- Tiền tệ (85px cân đối với ô Ca) -->
              <div class="min-w-0">
                <label class="block font-semibold text-[#000000D9] mb-1 text-xs">Tiền tệ</label>
                <div class="flex h-8 items-center gap-1.5 rounded border border-gray-300 bg-white px-2 py-1">
                  <span class="w-4 h-4 bg-red-600 rounded-full flex items-center justify-center text-xs text-yellow-300 font-bold shrink-0">★</span>
                  <select v-model="currency" class="bg-transparent focus:outline-none font-normal text-xs w-full text-center">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>
            </div>

          </div>

          <!-- Cột Phải: Ô Mô tả Textarea (5 cols rộng rãi) -->
          <div class="col-span-5 flex flex-col">
            <label class="block font-semibold text-gray-700 mb-1 text-xs">Mô tả</label>
            <div class="relative flex-1">
            <textarea 
              v-model="description" 
              class="h-full w-full p-2.5 pr-9 bg-white border border-gray-300 rounded text-xs text-[#000000D9] font-normal focus:outline-none resize-none min-h-[95px]"
            ></textarea>
            <button v-if="description" type="button" title="Xóa mô tả" @click="description = ''" class="absolute right-2 top-2 p-1 text-slate-400 hover:text-red-500"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

        </div>
      </div>

      <div class="border-t border-gray-200 bg-white px-4 py-3">
        <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-700">Danh sách thanh toán trước</div>
        <div class="max-h-36 overflow-auto rounded border border-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="sticky top-0 bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
              <tr>
                <th class="px-2.5 py-1.5 text-center">Ngày</th>
                <th class="px-2.5 py-1.5 text-center">Giờ</th>
                <th class="px-2.5 py-1.5 text-center">HTTT</th>
                <th class="px-2.5 py-1.5 text-center">Mô tả</th>
                <th class="px-2.5 py-1.5 text-center">Số tiền</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="deposit in depositRows" :key="deposit.id" class="border-t border-slate-100 hover:bg-slate-50/50">
                <td class="px-2.5 py-1.5 text-center">{{ formatDepositDate(deposit.date) }}</td>
                <td class="px-2.5 py-1.5 text-center">{{ deposit.open_time || '--' }}</td>
                <td class="px-2.5 py-1.5 text-center font-medium">{{ deposit.payment_method?.name || deposit.payment_method_id || '--' }}</td>
                <td class="px-2.5 py-1.5 text-slate-700">{{ deposit.description || '--' }}</td>
                <td class="px-2.5 py-1.5 text-right tabular-nums font-semibold text-emerald-700">{{ formatDepositAmount(deposit.amount) }}</td>
              </tr>
              <tr v-if="depositRows.length === 0"><td colspan="5" class="px-2 py-3 text-center text-slate-400">Chưa có thanh toán trước.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer Actions (Nút Đóng và Nút Lưu mạ xanh #0088ff chuẩn Ảnh 2) -->
      <div class="border-t border-gray-300 p-3 flex justify-end items-center gap-2 bg-gray-50">
        <button 
          @click="handleSubmit"
          :disabled="isSubmitting || shiftLoadState !== 'ready'"
          class="btn-pms-primary h-8 px-4 flex items-center gap-1.5 disabled:opacity-50 cursor-pointer text-xs"
        >
          <Save class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Đang lưu...' : 'Lưu' }}</span>
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
</style>
