<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { HelpCircle, X, Plus, Calendar, Clock, Save, Inbox, Trash2 } from '@lucide/vue'
import http from '@/services/http'
import { settleBookingPayment } from '@/services/booking-service'
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
const company = ref('KHÁCH LẺ')
const isCard = ref(false)
const cardCode = ref('')
const expiryDate = ref('')
const noteText = ref('')

const currency = ref('VND')
const workShift = ref('1')
const timeStr = ref(nowTimeStr())
const dateStr = ref(props.systemDate || todayDateStr())
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

function formatMoney(num) {
  const n = Number(num) || 0
  return new Intl.NumberFormat('en-US').format(n)
}

const isBankTransfer = computed(() => {
  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  if (!selectedMethod) return false
  const code = String(selectedMethod.code || '').toUpperCase()
  const name = String(selectedMethod.name || '').toLowerCase()
  const bankName = String(selectedMethod.bank_name || '').toLowerCase()
  return code === 'BT' || name.includes('bank') || name.includes('chuyển khoản') || name.includes('transfer') || bankName.includes('transfer')
})

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
    const res = await http.get('/hotel-settings')
    const settings = res.data?.data || res.data || {}
    const list = []
    if (settings.bank || settings.account) {
      list.push({
        id: 'hotel_bank_1',
        display: `${settings.bank || 'Ngân hàng'} - ${settings.account || ''} (${settings.account_name || ''})`.trim()
      })
    }
    list.push(
      { id: 'mb_bank', display: 'MB Bank - 7451100001168 (Chi nhánh Lâm Đồng)' },
      { id: 'vcb_bank', display: 'Vietcombank - 0071001234567 (CN Nha Trang)' },
      { id: 'tcb_bank', display: 'Techcombank - 1903567890123' }
    )
    bankAccountOptions.value = list
    if (list.length > 0 && !selectedBankAccount.value) {
      selectedBankAccount.value = list[0].display
    }
  } catch (err) {
    console.error('Lỗi khi tải tài khoản ngân hàng:', err)
  }
}

function inGroupExcluded(m) {
  const grp = Number(m.payment_group)
  return grp === 5 || m.is_free === 1 || m.is_free === true
}

const workShiftsList = ref([])
let clockInterval = null

function getAutoWorkShift(timeStrVal) {
  const [hhStr, mmStr] = (timeStrVal || nowTimeStr()).split(':')
  const totalMinutes = (parseInt(hhStr, 10) || 0) * 60 + (parseInt(mmStr, 10) || 0)

  if (workShiftsList.value.length > 0) {
    for (const sh of workShiftsList.value) {
      if (!sh.start_time || !sh.end_time) continue
      const [sH, sM] = sh.start_time.split(':').map(Number)
      const [eH, eM] = sh.end_time.split(':').map(Number)
      const startMin = sH * 60 + sM
      const endMin = eH * 60 + eM

      if (startMin <= endMin) {
        if (totalMinutes >= startMin && totalMinutes <= endMin) {
          return String(sh.name || sh.id)
        }
      } else {
        if (totalMinutes >= startMin || totalMinutes <= endMin) {
          return String(sh.name || sh.id)
        }
      }
    }
  }

  const hour = parseInt(hhStr, 10) || 0
  if (hour >= 6 && hour < 14) return '1'
  if (hour >= 14 && hour < 22) return '2'
  return '3'
}

const fetchWorkShifts = async () => {
  try {
    const res = await http.get('/shifts')
    const list = res.data?.data || res.data || []
    if (Array.isArray(list) && list.length > 0) {
      workShiftsList.value = list
    }
  } catch (err) {
    console.warn('Không thể nạp danh sách ca làm việc từ API, sử dụng ca mặc định.')
  }
}

function startRealtimeClock() {
  stopRealtimeClock()
  timeStr.value = nowTimeStr()
  workShift.value = getAutoWorkShift(timeStr.value)
  clockInterval = setInterval(() => {
    timeStr.value = nowTimeStr()
    workShift.value = getAutoWorkShift(timeStr.value)
  }, 1000)
}

function stopRealtimeClock() {
  if (clockInterval) {
    clearInterval(clockInterval)
    clockInterval = null
  }
}

watch(() => props.show, (visible) => {
  if (visible) {
    errorMsg.value = ''
    addedPayments.value = []
    dateStr.value = props.systemDate || todayDateStr()
    payAmountNum.value = netTotalAmount.value

    startRealtimeClock()
    fetchWorkShifts().then(() => {
      workShift.value = getAutoWorkShift(timeStr.value)
    })

    if (paymentMethods.value.length === 0) {
      fetchPaymentMethods()
    } else if (!paymentMethodId.value && paymentMethods.value[0]) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
  } else {
    stopRealtimeClock()
  }
})

onUnmounted(() => {
  stopRealtimeClock()
})

watch(isCard, (val) => {
  if (!val) {
    cardCode.value = ''
    expiryDate.value = ''
    noteText.value = ''
  }
})

const handleAddPaymentItem = () => {
  if (payAmountNum.value === 0) {
    errorMsg.value = 'Vui lòng nhập số tiền thanh toán.'
    return
  }

  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  const methodName = selectedMethod ? selectedMethod.name : 'Tiền mặt'
  const methodCode = selectedMethod ? (selectedMethod.code || selectedMethod.id) : 'CA'

  let desc = noteText.value.trim()
  if (!desc) {
    if (payAmountNum.value < 0 && (String(methodCode).toUpperCase() === 'CA' || methodName.toLowerCase().includes('tiền mặt') || methodName.toLowerCase().includes('cash'))) {
      desc = 'Refund Cash (Tiền mặt)'
    } else if (payAmountNum.value < 0) {
      desc = `Refund ${methodName}`
    } else {
      desc = `Thanh toán - ${methodName}`
    }
  }

  if (isBankTransfer.value && selectedBankAccount.value) {
    desc += ` [TK: ${selectedBankAccount.value}]`
  }
  if (isCard.value && cardCode.value) {
    desc += ` [Thẻ: ${cardCode.value}]`
  }

  addedPayments.value.push({
    id: Date.now(),
    payment_method_id: paymentMethodId.value,
    method_code: methodCode,
    method_name: methodName,
    bank_account: isBankTransfer.value ? selectedBankAccount.value : '',
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

  let finalPayments = []
  if (addedPayments.value.length > 0) {
    finalPayments = addedPayments.value.map(p => ({
      payment_method_id: p.payment_method_id,
      amount: p.amount,
      bank_account: p.bank_account,
      note: p.note
    }))
  } else if (payAmountNum.value !== 0) {
    const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
    const methodName = selectedMethod ? selectedMethod.name : 'Tiền mặt'
    const methodCode = selectedMethod ? (selectedMethod.code || selectedMethod.id) : 'CA'
    let desc = noteText.value.trim()
    if (!desc) {
      if (payAmountNum.value < 0) {
        desc = `Refund ${methodName}`
      } else {
        desc = `Thanh toán - ${methodName}`
      }
    }
    if (isBankTransfer.value && selectedBankAccount.value) {
      desc += ` [TK: ${selectedBankAccount.value}]`
    }
    if (isCard.value && cardCode.value) {
      desc += ` [Thẻ: ${cardCode.value}]`
    }
    finalPayments.push({
      payment_method_id: paymentMethodId.value,
      amount: Number(payAmountNum.value),
      bank_account: isBankTransfer.value ? selectedBankAccount.value : '',
      note: desc
    })
  }

  if (finalPayments.length === 0) {
    errorMsg.value = 'Vui lòng nhập số tiền hoặc bấm "Thêm" để tạo dòng thanh toán.'
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      booking_room_id: props.selectedRoomId || null,
      guest_id: props.selectedGuestId || null,
      folio_id: String(props.folioId).toUpperCase() === 'A' ? 'A' : (Number(props.folioId) || 1),
      payments: finalPayments,
      date: dateStr.value,
      open_time: timeStr.value,
      shift_id: workShift.value,
      currency: currency.value
    }

    const res = await settleBookingPayment(props.bookingId, payload)
    if (res.data?.success) {
      uiStore.showToast('Đã thực hiện thanh toán thành công!', 'success')
      emit('success', res.data.data)
      emit('close')
    } else {
      errorMsg.value = res.data?.message || 'Không thể thực hiện thanh toán.'
    }
  } catch (err) {
    const backendMsg = err.response?.data?.message
    const validationErrors = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('; ') : ''
    errorMsg.value = backendMsg || validationErrors || 'Có lỗi xảy ra khi lưu thanh toán.'
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
                  <option v-for="m in paymentMethods" :key="m.id || m.code" :value="m.id || m.code">
                    {{ m.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Ô Chọn Tài khoản ngân hàng (Nền vàng #ffffcc) nếu chọn Chuyển khoản -->
            <div v-if="isBankTransfer">
              <label class="block font-bold text-gray-700 mb-1">Tài khoản ngân hàng</label>
              <select v-model="selectedBankAccount" class="w-full px-2 py-1.5 bg-[#ffffcc] border border-gray-300 rounded text-gray-900 font-medium focus:outline-none text-xs truncate">
                <option value="" disabled>Tài khoản ngân hàng</option>
                <option v-for="b in bankAccountOptions" :key="b.id" :value="b.display">
                  {{ b.display }}
                </option>
              </select>
            </div>

            <!-- Công ty -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Công ty</label>
              <div class="flex gap-1">
                <select v-model="company" class="flex-1 px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold focus:outline-none text-xs">
                  <option value="KHÁCH LẺ">KHÁCH LẺ</option>
                </select>
                <button type="button" class="bg-sky-100 border border-sky-300 p-1 rounded text-sky-600 font-bold hover:bg-sky-200">
                  <Plus class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Checkbox Thẻ & Mã thẻ -->
            <div class="grid grid-cols-12 gap-2 items-center pt-1">
              <div class="col-span-4 flex items-center gap-1.5">
                <input type="checkbox" v-model="isCard" id="chkCard" class="rounded border-gray-300 cursor-pointer" />
                <label for="chkCard" class="font-bold text-gray-700 cursor-pointer">Thẻ</label>
              </div>
              <div class="col-span-8">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Mã thẻ</label>
                <input 
                  type="text" 
                  v-model="cardCode" 
                  :disabled="!isCard"
                  class="w-full px-2 py-1 bg-white border border-gray-300 rounded text-xs disabled:bg-gray-100 disabled:cursor-not-allowed" 
                />
              </div>
            </div>

            <!-- Ngày hết hạn & Ghi chú -->
            <div class="grid grid-cols-12 gap-2">
              <div class="col-span-5">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Ngày hết hạn</label>
                <div class="relative">
                  <input 
                    type="text" 
                    v-model="expiryDate" 
                    :disabled="!isCard"
                    placeholder="MM/YY" 
                    class="w-full px-2 py-1 bg-white border border-gray-300 rounded text-xs disabled:bg-gray-100 disabled:cursor-not-allowed" 
                  />
                  <Calendar class="w-3 h-3 text-emerald-600 absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <div class="col-span-7">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Ghi chú</label>
                <input 
                  type="text" 
                  v-model="noteText" 
                  :disabled="!isCard"
                  placeholder="Ghi chú thanh toán..." 
                  class="w-full px-2 py-1 bg-white border border-gray-300 rounded text-xs disabled:bg-gray-100 disabled:cursor-not-allowed" 
                />
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
                <select v-model="workShift" class="w-full px-1 py-1 bg-[#ffffcc] border border-gray-300 rounded font-bold text-xs focus:outline-none text-center">
                  <template v-if="workShiftsList.length > 0">
                    <option v-for="sh in workShiftsList" :key="sh.id" :value="String(sh.name || sh.id)">
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
                  <input type="text" v-model="timeStr" class="w-full pl-1 pr-5 py-1 bg-white border border-gray-300 rounded text-center font-mono text-xs font-semibold" />
                  <Clock class="w-3 h-3 text-sky-400 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Ngày -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ngày</label>
                <div class="relative">
                  <input type="date" v-model="dateStr" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-center text-[11px] font-mono font-semibold" />
                </div>
              </div>

              <!-- Bộ phận -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Bộ phận</label>
                <select v-model="department" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-xs font-semibold focus:outline-none">
                  <option value="FO">FO</option>
                  <option value="HK">HK</option>
                  <option value="FB">FB</option>
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
                    class="w-full px-2 py-1 bg-[#ffffcc] border border-gray-300 rounded font-mono font-bold text-gray-900 text-right text-sm" 
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
                  <input type="text" :value="formatMoney(totalDepositAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-gray-700 text-right text-xs" />
                </div>
              </div>

              <!-- Còn Lại -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Còn Lại</label>
                <div class="col-span-6">
                  <input type="text" :value="formatMoney(remainingAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-gray-900 text-right text-xs" />
                </div>
              </div>

              <!-- Tổng tiền -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Tổng tiền</label>
                <div class="col-span-6">
                  <input type="text" :value="formatMoney(netTotalAmount)" readonly class="w-full px-2 py-1 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-sky-700 text-right text-xs" />
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
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[110px]">Số tiền tương đương</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[60px]">Phí</th>
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
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold text-emerald-700">{{ formatMoney(item.amount) }}</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold text-emerald-700">{{ formatMoney(item.amount) }}</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono">0</td>
                <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold text-emerald-700">{{ formatMoney(item.amount) }}</td>
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
