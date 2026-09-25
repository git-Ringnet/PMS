<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { X, Plus, Save, Inbox, Trash2, CalendarDays } from '@lucide/vue'
import http from '@/services/http'
import { settleBookingPayment } from '@/services/booking-service'
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
  companyName: {
    type: String,
    default: 'KHÁCH LẺ'
  },
  companyAllowsDebt: {
    type: Boolean,
    default: false
  },
  arrivalDate: {
    type: String,
    default: ''
  },
  departureDate: {
    type: String,
    default: ''
  },
  selectedRoomId: {
    type: [String, Number],
    default: null
  },
  selectedGuestId: {
    type: [String, Number],
    default: null
  },
  folioId: {
    type: [Number, String],
    default: 1
  },
  totalServiceAmount: {
    type: Number,
    default: 0
  },
  totalDepositAmount: {
    type: Number,
    default: 0
  },
  serviceBillIds: {
    type: Array,
    default: () => []
  },
  systemDate: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'success'])
const uiStore = useUiStore()

const paymentMethodId = ref('')
const paymentMethods = ref([])
const bankAccountOptions = ref([])
const selectedBankAccount = ref('')

const currency = ref('VND')
const workShift = ref('')
const timeStr = ref(nowTimeStr())
const lastValidTimeStr = ref(timeStr.value)
const shiftTimeError = ref('')
const dateStr = ref(props.systemDate || todayDateStr())
const draftPaymentDate = ref(dateStr.value)
const shiftLoadState = ref('idle')
const paymentDateInput = ref(null)
const paymentDateError = ref('')
const paymentDateRangeError = 'Ngày không hợp lệ'
const department = ref('FO')

const payAmountNum = ref(0)
const addedPayments = ref([])
const isSubmitting = ref(false)
const errorMsg = ref('')

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

function normalizeDate(value) {
  return String(value || '').trim().slice(0, 10)
}

const stayStartDate = computed(() => normalizeDate(props.arrivalDate))
const stayEndDate = computed(() => normalizeDate(props.departureDate))
const latestPaymentDate = computed(() => {
  const limits = [stayEndDate.value, normalizeDate(props.systemDate) || todayDateStr()].filter(Boolean)
  return limits.sort()[0] || ''
})
const paymentDateRangeAvailable = computed(() => (
  !stayStartDate.value || !latestPaymentDate.value || stayStartDate.value <= latestPaymentDate.value
))
function openPaymentDatePicker() {
  const input = paymentDateInput.value
  if (!input) return
  input.focus()
  if (typeof input.showPicker === 'function') {
    try {
      input.showPicker()
      return
    } catch {}
  }
  input.focus()
}

function isPaymentDateAllowed(value) {
  const date = normalizeDate(value)
  return Boolean(date)
    && (!stayStartDate.value || date >= stayStartDate.value)
    && (!stayEndDate.value || date <= stayEndDate.value)
    && date <= (normalizeDate(props.systemDate) || todayDateStr())
}

function validatePaymentDateInput(event) {
  const candidate = normalizeDate(event?.target?.value ?? draftPaymentDate.value)
  if (!isPaymentDateAllowed(candidate)) {
    draftPaymentDate.value = dateStr.value
    if (event?.target) event.target.value = dateStr.value
    paymentDateError.value = paymentDateRangeError
    return false
  }
  dateStr.value = candidate
  draftPaymentDate.value = candidate
  paymentDateError.value = ''
  return true
}

function formatMoney(num) {
  const n = Number(num)
  if (!Number.isFinite(n)) return '0'
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n)
}

const isBankTransfer = computed(() => {
  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  if (!selectedMethod) return false
  const code = String(selectedMethod.code || '').toUpperCase()
  const name = String(selectedMethod.name || '').toLowerCase()
  const bankName = String(selectedMethod.bank_name || '').toLowerCase()
  return code === 'BT' || name.includes('bank') || name.includes('chuyển khoản') || name.includes('transfer') || bankName.includes('transfer')
})

const isCityLedgerMethod = (method) => (
  String(method?.code || '').toUpperCase() === 'AC' || Number(method?.payment_group) === 4
)

const visiblePaymentMethods = computed(() => paymentMethods.value.filter(method => (
  props.companyAllowsDebt || !isCityLedgerMethod(method)
)))

watch(visiblePaymentMethods, (methods) => {
  if (!methods.some(method => String(method.id || method.code) === String(paymentMethodId.value))) {
    paymentMethodId.value = methods[0] ? (methods[0].id || methods[0].code) : ''
  }
})

const selectedBankAccountDetails = computed(() => bankAccountOptions.value.find(account => (
  String(account.id) === String(selectedBankAccount.value)
)) || null)

const netTotalAmount = computed(() => {
  return (Number(props.totalServiceAmount) || 0) - (Number(props.totalDepositAmount) || 0)
})
const isZeroBalanceSettlement = computed(() => (
  Math.abs(netTotalAmount.value) <= 0.01
  && Number(props.totalServiceAmount) > 0
  && Number(props.totalDepositAmount) > 0
))

const totalAddedInModal = computed(() => {
  return addedPayments.value.reduce((acc, item) => acc + (Number(item.amount) || 0), 0)
})

const remainingAmount = computed(() => {
  return netTotalAmount.value - totalAddedInModal.value
})

const displayPayAmount = computed({
  get() {
    if (payAmountNum.value === null || payAmountNum.value === undefined) return '0'
    return formatMoney(payAmountNum.value)
  },
  set(val) {
    if (!val && val !== 0) {
      payAmountNum.value = 0
      return
    }
    const str = String(val).trim()
    const isNegative = str.startsWith('-')
    const cleanStr = str.replace(/[^0-9]/g, '')
    let num = cleanStr ? Number(cleanStr) : 0
    if (isNegative) num = -num
    payAmountNum.value = num
  }
})

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
    const list = (res.data?.data || res.data || [])
      .filter(account => account.is_active !== false && !account.is_intermediary)
      .map(account => ({
        ...account,
        display: [account.bank_name, account.bank_account_number, account.code]
          .filter(Boolean)
          .join(' - ')
      }))
    bankAccountOptions.value = list
  } catch (err) {
    console.error('Lỗi khi tải tài khoản ngân hàng:', err)
  }
}

function inGroupExcluded(m) {
  const grp = Number(m.payment_group)
  return grp === 5 || m.is_free === 1 || m.is_free === true
}

const workShiftsList = ref([])

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
    errorMsg.value = 'Không tải được cấu hình ca làm việc. Vui lòng thử lại trước khi thanh toán.'
    console.warn('Không thể nạp danh sách ca làm việc từ API.')
  }
}

watch(() => props.show, (visible) => {
  if (visible) {
    errorMsg.value = ''
    addedPayments.value = []
    selectedBankAccount.value = ''
    dateStr.value = props.systemDate || todayDateStr()
    draftPaymentDate.value = dateStr.value
    paymentDateError.value = ''
    payAmountNum.value = netTotalAmount.value
    timeStr.value = nowTimeStr()
    lastValidTimeStr.value = timeStr.value
    shiftTimeError.value = ''
    workShift.value = ''
    fetchWorkShifts()

    if (paymentMethods.value.length === 0) {
      fetchPaymentMethods()
    } else if (!paymentMethodId.value && paymentMethods.value[0]) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
  }
}, { immediate: true })

watch(isBankTransfer, (bankTransfer) => {
  if (!bankTransfer) selectedBankAccount.value = ''
})

const handleAddPaymentItem = () => {
  if (isZeroBalanceSettlement.value) {
    if (addedPayments.value.length > 0) {
      errorMsg.value = 'Đã có dòng chốt thanh toán 0đ.'
      return
    }

    const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
    const methodName = selectedMethod ? selectedMethod.name : 'Tiền mặt'
    const methodCode = selectedMethod ? (selectedMethod.code || selectedMethod.id) : 'CA'
    addedPayments.value.push({
      id: Date.now(),
      payment_method_id: paymentMethodId.value || 'CA',
      method_code: methodCode,
      method_name: methodName,
      bank_account: '',
      bank_account_id: null,
      debit_account: null,
      amount: 0,
      currency: currency.value,
      note: 'Thanh toán 0đ - Dùng cọc/tạm ứng'
    })
    payAmountNum.value = 0
    errorMsg.value = ''
    return
  }

  if (payAmountNum.value === 0) {
    errorMsg.value = 'Vui lòng nhập số tiền thanh toán.'
    return
  }

  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  const methodName = selectedMethod ? selectedMethod.name : 'Tiền mặt'
  const methodCode = selectedMethod ? (selectedMethod.code || selectedMethod.id) : 'CA'

  let desc = ''
  if (payAmountNum.value < 0 && (String(methodCode).toUpperCase() === 'CA' || methodName.toLowerCase().includes('tiền mặt') || methodName.toLowerCase().includes('cash'))) {
    desc = 'Refund Cash (Tiền mặt)'
  } else if (payAmountNum.value < 0) {
    desc = `Refund ${methodName}`
  } else {
    desc = `Thanh toán - ${methodName}`
  }

  addedPayments.value.push({
    id: Date.now(),
    payment_method_id: paymentMethodId.value,
    method_code: methodCode,
    method_name: methodName,
    bank_account: isBankTransfer.value ? (selectedBankAccountDetails.value?.display || '') : '',
    bank_account_id: isBankTransfer.value ? (selectedBankAccountDetails.value?.id || null) : null,
    debit_account: isBankTransfer.value ? (selectedBankAccountDetails.value?.accounting_account || null) : null,
    amount: Number(payAmountNum.value),
    currency: currency.value,
    note: desc
  })

  payAmountNum.value = remainingAmount.value
  errorMsg.value = ''
}

const handleRemovePaymentItem = (index) => {
  addedPayments.value.splice(index, 1)
  payAmountNum.value = remainingAmount.value
}

const handleSubmit = async () => {
  errorMsg.value = ''

  if (paymentDateError.value || !validatePaymentDateInput({ target: paymentDateInput.value })) return
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

  let finalPayments = []
  if (addedPayments.value.length > 0) {
    finalPayments = addedPayments.value.map(p => ({
      payment_method_id: p.payment_method_id,
      amount: p.amount,
      bank_account_id: p.bank_account_id,
      debit_account: p.debit_account,
      note: p.note
    }))
  } else if (payAmountNum.value !== 0) {
    const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
    const methodName = selectedMethod ? selectedMethod.name : 'Tiền mặt'
    const methodCode = selectedMethod ? (selectedMethod.code || selectedMethod.id) : 'CA'
    let desc = ''
    if (payAmountNum.value < 0) {
      desc = `Refund ${methodName}`
    } else {
      desc = `Thanh toán - ${methodName}`
    }
    finalPayments.push({
      payment_method_id: paymentMethodId.value,
      amount: Number(payAmountNum.value),
      bank_account_id: isBankTransfer.value ? (selectedBankAccountDetails.value?.id || null) : null,
      debit_account: isBankTransfer.value ? (selectedBankAccountDetails.value?.accounting_account || null) : null,
      note: desc
    })
  }

  if (finalPayments.length === 0) {
    errorMsg.value = 'Vui lòng nhập số tiền hoặc bấm "Thêm" để tạo dòng thanh toán.'
    return
  }

  const settlementAmount = finalPayments.reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
  const difference = netTotalAmount.value - settlementAmount
  if (Math.abs(difference) > 0.01) {
    errorMsg.value = difference > 0
      ? `Còn thiếu ${formatMoney(difference)} VND. Vui lòng thanh toán đủ trước khi lưu.`
      : `Số tiền thanh toán vượt ${formatMoney(Math.abs(difference))} VND. Vui lòng nhập đúng số tiền cần thanh toán.`
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      booking_room_id: props.selectedRoomId || null,
      guest_id: props.selectedGuestId || null,
      folio_id: String(props.folioId).toUpperCase() === 'A' ? 'A' : (Number(props.folioId) || 1),
      payments: finalPayments,
      service_bill_ids: props.serviceBillIds,
      date: dateStr.value,
      open_time: timeStr.value,
      shift_id: workShift.value,
      currency: currency.value,
      department_id: 'FO',
      ...(isZeroBalanceSettlement.value ? { zero_balance_close: true } : {})
    }

    const res = await settleBookingPayment(props.bookingId, payload)
    if (res.data?.success) {
      emit('success', res.data.data)
      emit('close')
    } else {
      errorMsg.value = res.data?.message || 'Không thể thực hiện thanh toán.'
    }
  } catch (err) {
    const response = err.response
    const backendMsg = response?.data?.message
    const validationErrors = response?.data?.errors ? Object.values(response.data.errors).flat().join('; ') : ''
    errorMsg.value = backendMsg || validationErrors || (response?.status === 403
      ? 'Bạn không có quyền thực hiện thao tác thanh toán này.'
      : 'Có lỗi xảy ra khi lưu thanh toán.')
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
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-2 md:p-4 animate-fadeIn select-none font-sans">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl overflow-hidden border border-sky-400 flex flex-col text-xs">
      
      <!-- Header (Màu xanh dương mạ #0088ff) -->
      <div class="bg-[#0088ff] text-white px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-xs">
        <span class="text-sm font-bold tracking-wide">Thanh toán</span>
        <div class="flex items-center gap-2">
          <button @click="handleClose" class="hover:bg-white/20 p-1 rounded transition-colors text-white cursor-pointer" title="Đóng">
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-4 bg-gray-50/50">
        
        <div v-if="errorMsg" class="p-2.5 bg-red-50 border border-red-200 text-red-600 rounded text-xs font-semibold">
          {{ errorMsg }}
        </div>

        <!-- Top Split Section: Form Trái & Bảng Giá Phải -->
        <div class="grid grid-cols-12 gap-4">
          
          <!-- LEFT CARD (6 cols) -->
          <div class="col-span-6 border border-gray-300 rounded-lg p-3 space-y-2.5 bg-white">
            <!-- Phương thức thanh toán -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Phương thức thanh toán <span class="text-red-500">*</span></label>
              <div class="flex gap-1">
                <select v-model="paymentMethodId" class="flex-1 min-w-0 px-2.5 py-1.5 bg-[#ffffcc] border border-gray-300 rounded font-bold text-gray-900 focus:outline-none text-xs truncate">
                  <option value="" disabled>-- Chọn phương thức --</option>
                    <option v-for="m in visiblePaymentMethods" :key="m.id || m.code" :value="m.id || m.code">
                    {{ m.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Ô Chọn Tài khoản ngân hàng (Nền vàng #ffffcc) nếu chọn Chuyển khoản -->
            <div v-if="isBankTransfer">
              <label class="block font-bold text-gray-700 mb-1">Tài khoản ngân hàng</label>
              <select v-model="selectedBankAccount" class="w-full px-2 py-1.5 bg-[#ffffcc] border border-gray-300 rounded text-gray-900 font-medium focus:outline-none text-xs truncate">
                <option value="">-- Không chọn --</option>
                <option v-for="b in bankAccountOptions" :key="b.id" :value="b.id">
                  {{ b.display }}
                </option>
              </select>
            </div>

            <!-- Công ty -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Công ty</label>
              <div class="flex gap-1">
                <div class="flex-1 px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold text-xs" aria-readonly="true">
                  {{ companyName || 'KHÁCH LẺ' }}
                </div>
              </div>
            </div>

          </div>

          <!-- RIGHT SUMMARY CONTROLS (6 cols) -->
          <div class="col-span-6 space-y-2 flex flex-col justify-between">
            
            <!-- Top Inputs Row: Tiền tệ, Ca, Giờ, Ngày, Bộ phận -->
            <div class="grid gap-2 items-start" style="grid-template-columns: minmax(4rem, 1fr) minmax(3.5rem, .9fr) minmax(7rem, 1.55fr) minmax(7.5rem, 1.5fr) minmax(3.5rem, .9fr)">
              <!-- Tiền tệ -->
              <div class="min-w-0">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Tiền tệ</label>
                <div class="h-8 box-border flex items-center gap-1 bg-white border border-gray-300 px-1.5 rounded">
                  <span class="w-3.5 h-3.5 bg-red-600 rounded-full flex items-center justify-center text-[8px] text-yellow-300 font-bold shrink-0">★</span>
                  <select v-model="currency" class="h-full min-w-0 bg-transparent font-bold text-[11px] focus:outline-none w-full">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>

              <!-- Ca làm việc -->
              <div class="min-w-0">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ca làm việc</label>
                <select v-model="workShift" :disabled="shiftLoadState !== 'ready'" @change="handleWorkShiftChange" class="h-8 box-border w-full min-w-0 px-1 bg-[#ffffcc] border border-gray-300 rounded font-bold text-xs focus:outline-none text-center disabled:bg-gray-100">
                  <option value="" disabled>Chọn ca</option>
                  <option v-for="sh in workShiftsList" :key="sh.id ?? sh.name" :value="String(sh.id ?? sh.name)">
                    {{ sh.name }}
                  </option>
                </select>
              </div>

              <!-- Giờ -->
              <div class="min-w-0">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Giờ</label>
                <input type="time" v-model="timeStr" :min="shiftTimeMin" :max="shiftTimeMax" :disabled="shiftLoadState !== 'ready' || !workShift" step="60" @change="handleTimeChange" class="h-8 box-border w-full min-w-0 px-1 bg-white border border-gray-300 rounded text-center font-mono text-xs font-semibold disabled:bg-gray-100" />
                <p v-if="shiftTimeError" class="mt-0.5 text-[9px] leading-3 text-red-600">{{ shiftTimeError }}</p>
              </div>

              <!-- Ngày -->
              <div class="min-w-0">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ngày</label>
                <div class="relative">
                  <input ref="paymentDateInput" type="date" v-model="draftPaymentDate" :min="stayStartDate || undefined" :max="latestPaymentDate || undefined" :disabled="!paymentDateRangeAvailable" :aria-invalid="Boolean(paymentDateError)" :aria-describedby="paymentDateError ? 'payment-date-error-message' : undefined" @blur="validatePaymentDateInput" @keydown.enter.prevent="validatePaymentDateInput" class="h-8 box-border w-full min-w-0 pl-1 pr-7 bg-white border border-gray-300 rounded text-center text-[11px] font-mono font-semibold disabled:bg-gray-100" />
                  <button type="button" :disabled="!paymentDateRangeAvailable" aria-label="Mở lịch chọn ngày thanh toán" title="Chọn ngày" @click.stop="openPaymentDatePicker" class="absolute right-1 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-sky-600 disabled:cursor-not-allowed">
                    <CalendarDays class="h-3.5 w-3.5" />
                  </button>
                </div>
                <p v-if="paymentDateError" id="payment-date-error-message" role="alert" class="mt-0.5 text-[9px] leading-3 text-red-600">
                  {{ paymentDateError }}
                </p>
              </div>

              <!-- Bộ phận -->
              <div class="min-w-0">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Bộ phận</label>
                <select v-model="department" class="h-8 box-border w-full px-1 bg-white border border-gray-300 rounded text-xs font-semibold focus:outline-none">
                  <option value="FO">FO</option>
                </select>
              </div>
            </div>

            <!-- Value Rows (Khớp chính xác Ảnh 2 & Ảnh 3) -->
            <div class="space-y-2 pt-1">
              <!-- Thanh toán + Thêm button -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Thanh toán</label>
                <div class="col-span-6">
                  <input 
                    type="text" 
                    v-model="displayPayAmount" 
                    :readonly="isZeroBalanceSettlement"
                    class="w-full px-2 py-1 bg-[#ffffcc] border border-gray-300 rounded tabular-nums font-bold text-gray-900 text-right text-sm"
                  />
                </div>
                <div class="col-span-3">
                  <button 
                    type="button" 
                    @click="handleAddPaymentItem"
                    class="w-full bg-[#0088ff] hover:bg-sky-600 text-white px-2 py-1.5 rounded flex items-center justify-center gap-1 font-bold shadow-xs transition-colors cursor-pointer text-xs"
                  >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Thêm</span>
                  </button>
                </div>
              </div>

              <!-- Đặt cọc / Tạm ứng -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Đặt cọc</label>
                <div class="col-span-6">
                  <input type="text" :value="formatMoney(totalDepositAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded tabular-nums font-bold text-gray-700 text-right text-xs" />
                </div>
              </div>

              <!-- Còn Lại -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Còn Lại</label>
                <div class="col-span-6">
                  <input type="text" :value="formatMoney(remainingAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded tabular-nums font-bold text-gray-900 text-right text-xs" />
                </div>
              </div>

              <!-- Tổng tiền -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Tổng tiền</label>
                <div class="col-span-6">
                  <input type="text" :value="formatMoney(netTotalAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded tabular-nums font-bold text-sky-700 text-right text-xs" />
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Bottom Table Section (Danh sách khoản thanh toán được thêm - Khớp chính xác Ảnh 3) -->
        <div class="border border-gray-300 rounded-lg overflow-x-auto min-h-[140px] max-h-[220px] relative bg-white">
          <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
            <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
              <tr>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[160px]">Mô tả</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[120px]">Phương thức thanh toán</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Tài khoản ngân hàng</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Tiền tệ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Tổng tiền</th>
                <th class="px-2.5 py-1.5 text-center min-w-[50px]">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in addedPayments" :key="item.id" class="hover:bg-amber-50/50 border-b border-gray-200">
                <td class="px-2.5 py-1 border-r border-gray-200">
                  <input type="text" v-model="item.note" class="w-full px-1.5 py-0.5 border border-gray-300 rounded text-xs" />
                </td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 font-bold text-gray-800">{{ item.method_code }}</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-gray-700">{{ item.bank_account }}</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 font-bold text-gray-800">{{ item.currency }}</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-right tabular-nums font-bold text-emerald-700">{{ formatMoney(item.amount) }}</td>
                <td class="px-2.5 py-1.5 text-center">
                  <button @click="handleRemovePaymentItem(idx)" :disabled="isZeroBalanceSettlement" class="text-sky-500 hover:text-sky-700 p-1 rounded disabled:cursor-not-allowed disabled:opacity-40" title="Xóa dòng">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Empty Data Placeholder -->
          <div v-if="addedPayments.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-4 pointer-events-none">
            <Inbox class="w-8 h-8 stroke-1 mb-1 text-gray-300" />
            <span class="text-xs text-gray-400">Chưa thêm khoản thanh toán nào</span>
          </div>
        </div>

      </div>

      <!-- Footer Actions -->
      <div class="border-t border-gray-300 p-3 flex justify-end items-center gap-2 bg-gray-50">
        <button 
          @click="handleClose" 
          :disabled="isSubmitting"
          class="bg-[#0088ff] hover:bg-sky-600 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50 text-xs"
        >
          <X class="w-4 h-4" />
          <span>Đóng</span>
        </button>

        <button 
          @click="handleSubmit"
          :disabled="isSubmitting || shiftLoadState !== 'ready'"
          class="bg-[#0088ff] hover:bg-sky-600 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors cursor-pointer disabled:opacity-50 text-xs"
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
