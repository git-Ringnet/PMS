<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { X, Plus, Calendar, Clock, Save } from '@lucide/vue'
import http from '@/services/http'
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
const workShift = ref('1')
const timeStr = ref(nowTimeStr())
const dateStr = ref(props.systemDate || todayDateStr())
const currency = ref('VND')
const isSubmitting = ref(false)
const errorMsg = ref('')

const displayAmount = computed({
  get() {
    if (amount.value === 0 || amount.value === '0' || !amount.value) return '0'
    const num = Number(amount.value)
    if (isNaN(num)) return '0'
    return num.toLocaleString('en-US')
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

const formatDepositAmount = (value) => Number(value || 0).toLocaleString('en-US')

const isBankTransfer = computed(() => {
  const selectedMethod = paymentMethods.value.find(m => String(m.id) === String(paymentMethodId.value) || String(m.code) === String(paymentMethodId.value))
  if (!selectedMethod) return false
  const code = String(selectedMethod.code || '').toUpperCase()
  const name = String(selectedMethod.name || '').toLowerCase()
  const bankName = String(selectedMethod.bank_name || '').toLowerCase()
  return code === 'BT' || name.includes('bank') || name.includes('chuyển khoản') || name.includes('transfer') || bankName.includes('transfer')
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
  return grp === 4 || grp === 5 || m.is_free === 1 || m.is_free === true
}

watch(() => props.show, (visible) => {
  if (visible) {
    amount.value = 0
    errorMsg.value = ''
    timeStr.value = nowTimeStr()
    dateStr.value = props.systemDate || todayDateStr()
    const rawRId = props.selectedRoomId
    selectedTargetRoomId.value = (rawRId !== null && rawRId !== undefined && rawRId !== '' && rawRId !== 'null') ? rawRId : null
    if (paymentMethods.value.length === 0) {
      fetchPaymentMethods()
    } else if (!paymentMethodId.value && paymentMethods.value[0]) {
      paymentMethodId.value = paymentMethods.value[0].id || paymentMethods.value[0].code
    }
    updateDefaultDescription()
  }
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
  if (!props.bookingId) {
    errorMsg.value = 'Không tìm thấy thông tin Booking.'
    return
  }

  isSubmitting.value = true
  try {
    const targetRoomId = (selectedTargetRoomId.value !== null && selectedTargetRoomId.value !== undefined && selectedTargetRoomId.value !== '' && selectedTargetRoomId.value !== 'null')
      ? selectedTargetRoomId.value
      : null

    let finalDesc = description.value.trim() || `Advance Payment`
    if (isBankTransfer.value && selectedBankAccount.value) {
      finalDesc += ` [TK: ${selectedBankAccount.value}]`
    }

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
    const backendMsg = err.response?.data?.message
    const validationErrors = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('; ') : ''
    errorMsg.value = backendMsg || validationErrors || 'Có lỗi xảy ra khi lưu thanh toán trước.'
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
  <div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 md:p-4 animate-fadeIn select-none font-sans">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl overflow-hidden border border-sky-400 flex flex-col text-xs">
      
      <!-- Header (Màu xanh dương mạ #0088ff chuẩn Ảnh 2) -->
      <div class="bg-[#0088ff] text-white px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-xs">
        <span class="text-sm font-bold tracking-wide">Thanh toán trước</span>
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
          <!-- Tên đăng ký (Trái - 6 cols) -->
          <div class="col-span-6">
            <label class="block font-semibold text-gray-700 mb-1 text-xs">Tên đăng ký</label>
            <select disabled class="w-full px-2.5 py-1.5 bg-gray-100 border border-gray-300 rounded font-medium text-gray-700 focus:outline-none cursor-not-allowed text-xs">
              <option :value="registrationDisplay">{{ registrationDisplay }}</option>
            </select>
          </div>

          <!-- Hình thức thanh toán & Tài khoản ngân hàng (Phải - 6 cols) -->
          <div class="col-span-6">
            <div class="grid grid-cols-12 gap-2">
              <!-- HTTT -->
              <div :class="isBankTransfer ? 'col-span-6' : 'col-span-12'">
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Hình thức thanh toán <span class="text-red-500">*</span></label>
                <div class="flex gap-1">
                  <select v-model="paymentMethodId" class="flex-1 min-w-0 px-2 py-1.5 bg-[#ffffcc] border border-gray-300 rounded text-gray-900 font-bold focus:outline-none text-xs truncate">
                    <option value="" disabled>-- Chọn hình thức --</option>
                    <option v-for="m in paymentMethods" :key="m.id || m.code" :value="m.id || m.code">
                      {{ m.name }}
                    </option>
                  </select>
                  <button type="button" class="bg-sky-500 hover:bg-sky-600 text-white px-2 py-1.5 rounded font-bold shadow-xs transition-colors shrink-0" title="Thêm HTTT">
                    <Plus class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              <!-- Ô Chọn Tài khoản ngân hàng (Nền vàng #ffffcc) -->
              <div v-if="isBankTransfer" class="col-span-6">
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Tài khoản ngân hàng</label>
                <select v-model="selectedBankAccount" class="w-full px-2 py-1.5 bg-[#ffffcc] border border-gray-300 rounded text-gray-900 font-medium focus:outline-none text-xs truncate" title="Tài khoản ngân hàng">
                  <option value="" disabled>Tài khoản ngân hàng</option>
                  <option v-for="b in bankAccountOptions" :key="b.id" :value="b.display">
                    {{ b.display }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- HÀNG 2 & 3: Số tiền, Phòng, Ca, Giờ, Ngày, Tiền tệ & Mô tả Textarea -->
        <div class="grid grid-cols-12 gap-4 items-stretch">
          
          <!-- Cột Trái: Số tiền, Phòng, Ca làm việc, Giờ, Ngày, Tiền tệ (6 cols) -->
          <div class="col-span-6 space-y-3 flex flex-col justify-between">
            <!-- Hàng Số tiền & Phòng -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Số tiền <span class="text-red-500">*</span></label>
                <input 
                  type="text" 
                  v-model="displayAmount" 
                  placeholder="0"
                  class="w-full px-2.5 py-1.5 bg-[#ffffcc] border border-gray-300 rounded font-bold text-gray-900 focus:outline-none text-sm text-right font-mono tracking-wide" 
                />
              </div>

              <div>
                <label class="block font-semibold text-gray-700 mb-1 text-xs">Phòng</label>
                <select v-model="selectedTargetRoomId" class="w-full px-2.5 py-1.5 bg-white border border-gray-300 rounded font-semibold text-gray-800 focus:outline-none text-xs">
                  <option :value="null">Master Header</option>
                  <option v-for="r in roomOptions" :key="r.roomId || r.id" :value="r.roomId || r.id">
                    Phòng {{ r.roomNumber || r.room_number || r.id }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Hàng Ca làm việc, Giờ, Ngày, Tiền tệ (Khoảng cách rộng rãi không bị chật) -->
            <div class="grid grid-cols-12 gap-2 items-end">
              <!-- Ca làm việc (Nền vàng #ffffcc) -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Ca</label>
                <select v-model="workShift" class="w-full px-1.5 py-1 bg-[#ffffcc] border border-gray-300 rounded font-bold text-xs focus:outline-none text-center">
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                </select>
              </div>

              <!-- Giờ -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Giờ</label>
                <div class="relative">
                  <input type="text" v-model="timeStr" class="w-full pl-1.5 pr-6 py-1 bg-white border border-gray-300 rounded text-center text-xs font-mono font-semibold" />
                  <Clock class="w-3.5 h-3.5 text-sky-400 absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Ngày -->
              <div class="col-span-4">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Ngày</label>
                <div class="relative">
                  <input type="date" v-model="dateStr" class="w-full px-1.5 py-1 bg-white border border-gray-300 rounded text-center text-[11px] font-mono font-semibold" />
                </div>
              </div>

              <!-- Tiền tệ -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Tiền tệ</label>
                <div class="flex items-center gap-1 bg-white border border-gray-300 px-1.5 py-1 rounded">
                  <span class="w-3.5 h-3.5 bg-red-600 rounded-full flex items-center justify-center text-[8px] text-yellow-300 font-bold shrink-0">★</span>
                  <select v-model="currency" class="bg-transparent focus:outline-none font-bold text-[11px] w-full">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>
            </div>

          </div>

          <!-- Cột Phải: Ô Mô tả Textarea (Nền vàng #ffffcc phủ toàn bộ chiều cao) -->
          <div class="col-span-6 flex flex-col">
            <label class="block font-semibold text-gray-700 mb-1 text-xs">Mô tả</label>
            <textarea 
              v-model="description" 
              placeholder="Mô tả..."
              class="w-full flex-1 p-2.5 bg-[#ffffcc] border border-gray-300 rounded text-xs text-gray-900 font-medium focus:outline-none resize-none min-h-[95px]"
            ></textarea>
          </div>

        </div>
      </div>

      <div class="border-t border-gray-200 bg-white px-4 py-3">
        <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-700">Danh sách thanh toán trước</div>
        <div class="max-h-36 overflow-auto rounded border border-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="sticky top-0 bg-slate-100 text-slate-600">
              <tr><th class="px-2 py-1.5">Ngày</th><th class="px-2 py-1.5">Giờ</th><th class="px-2 py-1.5">HTTT</th><th class="px-2 py-1.5">Mô tả</th><th class="px-2 py-1.5 text-right">Số tiền</th></tr>
            </thead>
            <tbody>
              <tr v-for="deposit in depositRows" :key="deposit.id" class="border-t border-slate-100">
                <td class="px-2 py-1.5">{{ formatDepositDate(deposit.date) }}</td>
                <td class="px-2 py-1.5">{{ deposit.open_time || '--' }}</td>
                <td class="px-2 py-1.5">{{ deposit.payment_method?.name || deposit.payment_method_id || '--' }}</td>
                <td class="px-2 py-1.5">{{ deposit.description || '--' }}</td>
                <td class="px-2 py-1.5 text-right font-mono font-semibold">{{ formatDepositAmount(deposit.amount) }}</td>
              </tr>
              <tr v-if="depositRows.length === 0"><td colspan="5" class="px-2 py-3 text-center text-slate-400">Chưa có thanh toán trước.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer Actions (Nút Đóng và Nút Lưu mạ xanh #0088ff chuẩn Ảnh 2) -->
      <div class="border-t border-gray-300 p-3 flex justify-end items-center gap-2 bg-gray-50">
        <button 
          @click="handleClose" 
          :disabled="isSubmitting"
          class="bg-[#0088ff] hover:bg-sky-600 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors disabled:opacity-50 cursor-pointer text-xs"
        >
          <X class="w-4 h-4" />
          <span>Đóng</span>
        </button>

        <button 
          @click="handleSubmit"
          :disabled="isSubmitting"
          class="bg-[#0088ff] hover:bg-sky-600 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors disabled:opacity-50 cursor-pointer text-xs"
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
