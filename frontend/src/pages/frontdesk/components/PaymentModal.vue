<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { X, Plus, Clock, Save, Inbox, Trash2 } from '@lucide/vue'
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
const workShift = ref('1')
const timeStr = ref(nowTimeStr())
const dateStr = ref(props.systemDate || todayDateStr())
const shiftTimeTouched = ref(false)
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

function isPaymentDateAllowed(value) {
  const date = normalizeDate(value)
  return Boolean(date)
    && (!stayStartDate.value || date >= stayStartDate.value)
    && (!stayEndDate.value || date <= stayEndDate.value)
    && date <= (normalizeDate(props.systemDate) || todayDateStr())
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

function getAutoWorkShift(timeStrVal) {
  const time = timeStrVal || nowTimeStr()
  const matchingShift = workShiftsList.value.find(shift => timeMatchesShift(time, shift))
  if (matchingShift) return String(matchingShift.id ?? matchingShift.name)

  const hour = parseInt(time.slice(0, 2), 10) || 0
  if (hour >= 6 && hour < 14) return '1'
  if (hour >= 14 && hour < 22) return '2'
  return '3'
}

function timeMatchesShift(timeValue, shift) {
  if (!shift?.start_time || !shift?.end_time || !/^\d{2}:\d{2}$/.test(timeValue || '')) return false
  const toMinutes = (value) => {
    const [hours, minutes] = String(value).slice(0, 5).split(':').map(Number)
    return hours * 60 + minutes
  }
  const time = toMinutes(timeValue)
  const start = toMinutes(shift.start_time)
  const end = toMinutes(shift.end_time)
  if (start === end) return true
  return start < end ? time >= start && time < end : time >= start || time < end
}

function isShiftTimeAllowed() {
  if (!/^\d{2}:\d{2}$/.test(timeStr.value || '')) return false
  if (workShiftsList.value.length === 0) return true
  const shift = workShiftsList.value.find(item => String(item.id ?? item.name) === String(workShift.value))
  if (!shift) return false
  if (!shift.start_time || !shift.end_time) return true
  return timeMatchesShift(timeStr.value, shift)
}

const markShiftTimeTouched = () => {
  shiftTimeTouched.value = true
}

const fetchWorkShifts = async () => {
  try {
    const res = await http.get('/shifts')
    const list = res.data?.data || res.data || []
    if (Array.isArray(list) && list.length > 0) {
      workShiftsList.value = list
      if (!shiftTimeTouched.value) {
        const matchingShift = list.find(shift => timeMatchesShift(timeStr.value, shift))
        if (matchingShift) {
          workShift.value = String(matchingShift.id ?? matchingShift.name)
        } else {
          const firstShift = list[0]
          workShift.value = String(firstShift.id ?? firstShift.name)
          const firstShiftStart = String(firstShift.start_time || '').slice(0, 5)
          if (/^\d{2}:\d{2}$/.test(firstShiftStart)) timeStr.value = firstShiftStart
        }
      }
    }
  } catch (err) {
    console.warn('Không thể nạp danh sách ca làm việc từ API, sử dụng ca mặc định.')
  }
}

watch(() => props.show, (visible) => {
  if (visible) {
    errorMsg.value = ''
    addedPayments.value = []
    selectedBankAccount.value = ''
    dateStr.value = props.systemDate || todayDateStr()
    payAmountNum.value = netTotalAmount.value
    timeStr.value = nowTimeStr()
    shiftTimeTouched.value = false
    workShift.value = getAutoWorkShift(timeStr.value)
    fetchWorkShifts()

    if (paymentMethods.value.length === 0) {
      fetchPaymentMethods()
    } else if (!paymentMethodId.value && paymentMethods.value[0]) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
  }
})

watch(isBankTransfer, (bankTransfer) => {
  if (!bankTransfer) selectedBankAccount.value = ''
})

const handleAddPaymentItem = () => {
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

  payAmountNum.value = 0
  errorMsg.value = ''
}

const handleRemovePaymentItem = (index) => {
  addedPayments.value.splice(index, 1)
}

const handleSubmit = async () => {
  errorMsg.value = ''

  if (!isPaymentDateAllowed(dateStr.value)) {
    errorMsg.value = 'Ngày thanh toán phải nằm trong thời gian lưu trú và không lớn hơn ngày hệ thống.'
    return
  }
  if (!isShiftTimeAllowed()) {
    errorMsg.value = 'Giờ thanh toán không thuộc ca đã chọn. Vui lòng chọn lại ca hoặc giờ.'
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
      department_id: 'FO'
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
            <div class="grid grid-cols-12 gap-2 items-end">
              <!-- Tiền tệ -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Tiền tệ</label>
                <div class="flex items-center gap-1 bg-white border border-gray-300 px-1.5 py-1 rounded">
                  <span class="w-3.5 h-3.5 bg-red-600 rounded-full flex items-center justify-center text-[8px] text-yellow-300 font-bold shrink-0">★</span>
                  <select v-model="currency" class="bg-transparent font-bold text-[11px] focus:outline-none w-full">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>

              <!-- Ca làm việc -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ca làm việc</label>
                <select v-model="workShift" @change="markShiftTimeTouched" class="w-full px-1 py-1 bg-[#ffffcc] border border-gray-300 rounded font-bold text-xs focus:outline-none text-center">
                  <template v-if="workShiftsList.length > 0">
                    <option v-for="sh in workShiftsList" :key="sh.id" :value="String(sh.id ?? sh.name)">
                      {{ sh.name }}
                    </option>
                  </template>
                  <template v-else>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                  </template>
                </select>
              </div>

              <!-- Giờ -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Giờ</label>
                <div class="relative">
                  <input type="time" v-model="timeStr" step="60" @change="markShiftTimeTouched" class="w-full pl-1 pr-5 py-1 bg-white border border-gray-300 rounded text-center font-mono text-xs font-semibold" />
                  <Clock class="w-3 h-3 text-sky-400 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Ngày -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ngày</label>
                <div class="relative">
                  <input type="date" v-model="dateStr" :min="stayStartDate || undefined" :max="latestPaymentDate || undefined" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-center text-[11px] font-mono font-semibold" />
                </div>
              </div>

              <!-- Bộ phận -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Bộ phận</label>
                <select v-model="department" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-xs font-semibold focus:outline-none">
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
                  <button @click="handleRemovePaymentItem(idx)" class="text-sky-500 hover:text-sky-700 p-1 rounded" title="Xóa dòng">
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
          :disabled="isSubmitting"
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
