<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div class="w-full max-w-5xl bg-white shadow-2xl rounded-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
        
        <!-- HEADER -->
        <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 border-b border-[#1a2d42]">
            <div class="flex items-center space-x-2">
                <div class="bg-blue-400/20 p-1.5 rounded-lg">
                    <i class="fa-solid fa-file-invoice-dollar text-blue-200 text-xs"></i>
                </div>
                <span class="font-bold text-xs tracking-wide uppercase">Thêm đặt cọc</span>
            </div>
            <button @click="close" class="text-slate-300 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10 cursor-pointer border-none bg-transparent">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- SCROLLABLE CONTENT -->
        <div class="overflow-y-auto p-4 bg-white flex flex-col space-y-3 shrink-0">
            
            <div class="w-1/2 pr-2">
                <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tên đăng ký</label>
                <div class="relative">
                    <select disabled class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs font-medium bg-slate-50 text-slate-800 appearance-none focus:outline-none shadow-sm cursor-not-allowed">
                        <option>{{ bookingName || 'Chưa có tên' }}</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Số tiền <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <input 
                          type="text" 
                          :value="formatCurrencyInput(depositForm.amount)"
                          @input="e => depositForm.amount = cleanCurrencyValue(e.target.value)"
                          class="w-full border border-blue-200 rounded-lg px-3 h-[30px] text-xs font-bold bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm"
                        >
                        <div class="absolute right-1 top-0.5 flex flex-col">
                            <button type="button" @click="depositForm.amount++" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-up"></i></button>
                            <button type="button" @click="depositForm.amount = Math.max(0, depositForm.amount - 1)" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Phương thức đặt cọc <span class="text-rose-500">*</span></label>
                    <div class="relative h-[30px]">
                        <select 
                          v-model="depositForm.paymentMethodId"
                          class="w-full border border-blue-200 rounded-lg px-3 h-full text-xs font-medium bg-blue-50/70 text-black appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option :value="null">Phương thức đặt cọc</option>
                            <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tài khoản ngân hàng</label>
                    <div class="relative">
                        <select 
                          v-model="depositForm.bankAccountId"
                          class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs bg-white text-slate-800 appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option value="Tài khoản ngân hàng">Tài khoản ngân hàng</option>
                            <option value="Vietcombank - 1012345678">Vietcombank - 1012345678</option>
                            <option value="BIDV - 2012345678">BIDV - 2012345678</option>
                            <option value="Techcombank - 3012345678">Techcombank - 3012345678</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Ngày <span class="text-rose-500">*</span></label>
                    <div class="flex items-center space-x-2 border border-slate-300 rounded-lg px-3 h-[30px] bg-white shadow-sm text-xs font-medium text-slate-800 relative">
                        <input 
                          type="date" 
                          v-model="depositForm.date" 
                          class="date-span-input flex-1 text-left w-full border-none focus:outline-none bg-transparent"
                        />
                        <i class="fa-regular fa-calendar-days text-blue-500 pointer-events-none"></i>
                        <i @click="copyToClipboard(depositForm.date)" class="fa-regular fa-copy text-slate-400 hover:text-slate-600 cursor-pointer"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Mô tả</label>
                    <textarea 
                      v-model="depositForm.note"
                      placeholder="Nhập mô tả..." 
                      class="w-full border border-blue-200 rounded-lg p-2 text-xs font-medium bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm h-[56px] resize-none"
                    ></textarea>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Lưu hình ảnh (Chứng từ / Biên lai)</label>
                    <div class="border border-dashed border-slate-300 rounded-lg h-[56px] bg-slate-50 flex items-center justify-center hover:bg-slate-100 hover:border-blue-400 transition cursor-pointer relative overflow-hidden group shadow-sm">
                        <input type="file" @change="handleDepositImageUpload" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*">
                        <div class="flex flex-col items-center space-y-1" v-if="!depositForm.image">
                            <i class="fa-solid fa-cloud-arrow-up text-slate-400 group-hover:text-blue-500 transition text-xs"></i>
                            <span class="text-[10px] text-slate-500 font-medium group-hover:text-blue-600 transition">Nhấp để tải ảnh lên hoặc kéo thả vào đây</span>
                        </div>
                        <div class="flex items-center space-x-2 p-1" v-else>
                            <img :src="depositForm.image" class="h-10 w-10 object-cover rounded border" />
                            <span class="text-xs text-green-600 font-bold">Hình ảnh đã chọn</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE AND LIST -->
        <div class="bg-slate-50 p-4 border-t border-slate-200 flex-1 flex flex-col overflow-y-auto">
            
            <div class="flex justify-between items-end mb-1.5 shrink-0">
                <h3 class="font-bold text-slate-800 text-[11px] uppercase tracking-wider flex items-center">
                    Danh sách đặt cọc <span class="text-rose-500 ml-1">*</span>
                </h3>
                
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-[11px] text-slate-500 font-medium">Hiển thị xoá</span>
                        <div class="relative inline-block w-6 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" name="toggle" id="show-deleted" class="toggle-checkbox absolute block w-3 h-3 rounded-full bg-white border-2 border-slate-300 appearance-none cursor-pointer top-0 bottom-0 m-auto z-10 transition-transform duration-200 ease-in-out left-0"/>
                            <label for="show-deleted" class="toggle-label block overflow-hidden h-3 rounded-full bg-slate-300 cursor-pointer transition-colors duration-200"></label>
                        </div>
                    </div>
                    <button class="text-slate-400 hover:text-blue-600 transition border-none bg-transparent cursor-pointer">
                        <i class="fa-solid fa-sliders text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm mb-1">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                            <th class="p-2 w-10 text-center">
                                <input 
                                  type="checkbox" 
                                  class="rounded border-slate-300 font-normal"
                                  :checked="selectedDepositIds.length === localDeposits?.length"
                                  @change="selectedDepositIds = $event.target.checked ? localDeposits.map(d => d.id) : []"
                                >
                            </th>
                            <th class="p-2 min-w-[80px]">Ngày</th>
                            <th class="p-2 min-w-[60px]">Giờ</th>
                            <th class="p-2 min-w-[130px]">Phương thức thanh toán</th>
                            <th class="p-2 min-w-[150px]">Mô tả</th>
                            <th class="p-2 min-w-[90px] text-right">Số tiền</th>
                            <th class="p-2 min-w-[60px] text-center">Tiền tệ</th>
                            <th class="p-2 min-w-[110px]">Người nhận</th>
                            <th class="p-2 min-w-[100px] text-center">Chứng từ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr 
                          v-for="dep in localDeposits" 
                          :key="dep.id" 
                          class="border-b border-slate-100 hover:bg-slate-50/80 transition"
                          :class="{ 'bg-blue-50/30': selectedDepositIds.includes(dep.id) }"
                        >
                            <td class="p-2 text-center align-middle">
                                <input 
                                  type="checkbox" 
                                  :value="dep.id" 
                                  v-model="selectedDepositIds"
                                  class="rounded border-slate-300 font-normal"
                                >
                            </td>
                            <td class="p-2 font-medium text-slate-800 align-middle">{{ dep.date }}</td>
                            <td class="p-2 text-slate-600 align-middle">{{ dep.time }}</td>
                            <td class="p-2 text-slate-800 align-middle">{{ paymentMethods.find(x => x.id === dep.paymentMethodId)?.name || 'BT' }}</td>
                            <td class="p-2 text-slate-600 align-middle">{{ dep.note }}</td>
                            <td class="p-2 text-right font-mono font-semibold text-slate-900 align-middle">{{ dep.amount.toLocaleString('vi-VN') }}</td>
                            <td class="p-2 text-center text-slate-500 align-middle">{{ dep.currency }}</td>
                            <td class="p-2 text-slate-700 font-medium align-middle">{{ dep.recipient }}</td>
                            <td class="p-2 text-center align-middle">
                                <div class="flex items-center justify-center space-x-1.5">
                                    <div 
                                      v-for="(img, iIdx) in (dep.images || [])" 
                                      :key="iIdx"
                                      class="relative group w-7 h-7 rounded border border-slate-200 overflow-hidden shadow-sm bg-white cursor-pointer flex-shrink-0"
                                    >
                                        <div class="w-full h-full flex items-center justify-center bg-slate-100 text-[8px] font-bold text-slate-500 uppercase">
                                            {{ img }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!localDeposits || localDeposits.length === 0" class="border-b border-slate-100">
                            <td colspan="9" class="p-4 text-center text-slate-400 italic">Chưa có thông tin đặt cọc.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FOOTER ACTIONS -->
        <div class="bg-white border-t border-slate-200 p-2.5 px-4 flex justify-between items-center shrink-0">
            
            <div class="flex items-center space-x-2">
                <button type="button" @click="splitDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-code-branch text-[10px]"></i>
                    <span>Tách</span>
                </button>
                <button type="button" @click="transferDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                    <span>Chuyển</span>
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <button type="button" @click="deleteDeposits" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                    <span>Xóa</span>
                </button>
                <button type="button" @click="editDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                    <span>Sửa</span>
                </button>
                <button type="button" @click="saveDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-regular fa-floppy-disk text-[10px]"></i>
                    <span>Lưu</span>
                </button>
                <button type="button" @click="addDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center space-x-1.5 shadow-md text-xs tracking-wide cursor-pointer border-none">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Thêm</span>
                </button>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {
  fetchPayments,
  createPayment,
  updatePayment,
  deletePayment,
  splitPayment,
  transferPayment,
  fetchBookings
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  bookingName: String,
  paymentMethods: Array,
  currenciesList: Array,
  deposits: Array
})

const emit = defineEmits(['update:show', 'update:deposits', 'update:paymentValue'])

const uiStore = useUiStore()

const localDeposits = ref([])
const selectedDepositIds = ref([])

const activeCurrency = computed(() => {
  return props.currenciesList?.find(c => c.is_main) || { code: 'VND', decimals_to_round: 0 }
})

const depositForm = ref({
  id: null,
  amount: 0,
  paymentMethodId: null,
  bankAccountId: 'Tài khoản ngân hàng',
  date: new Date().toISOString().split('T')[0],
  note: '',
  recipient: 'Admin',
  image: null
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    resetForm()
    selectedDepositIds.value = []
    if (props.bookingId) {
      await syncDepositsFromBackend()
    } else {
      localDeposits.value = JSON.parse(JSON.stringify(props.deposits || []))
    }
  }
})

function close() {
  emit('update:show', false)
}

function resetForm() {
  depositForm.value = {
    id: null,
    amount: 0,
    paymentMethodId: props.paymentMethods?.[0]?.id || null,
    bankAccountId: 'Tài khoản ngân hàng',
    date: new Date().toISOString().split('T')[0],
    note: '',
    recipient: 'Admin',
    image: null
  }
}

function parseApiDate(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  if (dateStr.includes('T')) {
    const d = new Date(dateStr)
    if (!isNaN(d)) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  return dateStr.substring(0, 10)
}

async function syncDepositsFromBackend() {
  if (!props.bookingId) return
  try {
    const res = await fetchPayments(props.bookingId)
    const paymentsList = res.data?.data || res.data || []
    
    localDeposits.value = paymentsList.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: [],
      status: p.status,
      edit_flag: p.edit_flag,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account,
      pack2: p.pack2
    }))

    const activeDeposits = localDeposits.value.filter(p => p.edit_flag === 0 && p.pack2 === 'DPR')
    const totalValue = activeDeposits.reduce((sum, d) => sum + Number(d.amount), 0)

    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
  } catch (err) {
    console.error('Lỗi đồng bộ cọc:', err)
  }
}

function handleDepositImageUpload(event) {
  const file = event.target.files[0]
  if (file) {
    depositForm.value.image = URL.createObjectURL(file)
  }
}

async function addDeposit() {
  if (!depositForm.value.amount || depositForm.value.amount <= 0) {
    uiStore.showToast('Vui lòng nhập số tiền đặt cọc hợp lệ!', 'warning')
    return
  }
  
  if (props.bookingId) {
    try {
      const payload = {
        date: depositForm.value.date,
        amount: Number(depositForm.value.amount),
        payment_method_id: depositForm.value.paymentMethodId,
        description: depositForm.value.note || 'Đặt cọc',
        debit_account: depositForm.value.bankAccountId || 'Tài khoản ngân hàng',
      }
      await createPayment(props.bookingId, payload)
      await syncDepositsFromBackend()
      uiStore.showToast('Đã thêm đặt cọc mới thành công!', 'success')
      resetForm()
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể thêm cọc!', 'error')
    }
  } else {
    const now = new Date()
    const timeStr = now.toTimeString().split(' ')[0].substring(0, 5)
    
    const newDep = {
      id: Date.now(),
      date: depositForm.value.date.split('-').reverse().join('/'),
      time: timeStr,
      paymentMethodId: depositForm.value.paymentMethodId,
      note: depositForm.value.note || 'Đặt cọc',
      amount: Number(depositForm.value.amount),
      currency: activeCurrency.value.code || 'VND',
      recipient: depositForm.value.recipient || 'Admin',
      images: depositForm.value.image ? ['Chứng từ'] : [],
      status: 1,
      edit_flag: 0,
      reversal_ref: null,
      debit_account: depositForm.value.bankAccountId || 'Tài khoản ngân hàng',
      pack2: 'DPR'
    }
    
    localDeposits.value.push(newDep)
    const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    
    resetForm()
    uiStore.showToast('Đã thêm đặt cọc mới!', 'success')
  }
}

function editDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để sửa!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = localDeposits.value.find(d => d.id === targetId)
  if (dep) {
    let dateVal = dep.date
    if (dateVal.includes('/')) {
      dateVal = dateVal.split('/').reverse().join('-')
    }
    depositForm.value = {
      id: dep.id,
      amount: dep.amount,
      paymentMethodId: dep.paymentMethodId,
      bankAccountId: dep.bankAccountId || 'Tài khoản ngân hàng',
      date: dateVal,
      note: dep.note,
      recipient: dep.recipient,
      image: dep.images?.[0] || null
    }
  }
}

async function saveDeposit() {
  if (!depositForm.value.id) {
    close()
    return
  }
  
  if (props.bookingId) {
    try {
      const payload = {
        date: depositForm.value.date,
        amount: Number(depositForm.value.amount),
        payment_method_id: depositForm.value.paymentMethodId,
        description: depositForm.value.note,
        debit_account: depositForm.value.bankAccountId,
      }
      await updatePayment(depositForm.value.id, payload)
      await syncDepositsFromBackend()
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
      resetForm()
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể lưu cọc!', 'error')
    }
  } else {
    const idx = localDeposits.value.findIndex(d => d.id === depositForm.value.id)
    if (idx !== -1) {
      localDeposits.value[idx].amount = Number(depositForm.value.amount)
      localDeposits.value[idx].paymentMethodId = depositForm.value.paymentMethodId
      localDeposits.value[idx].date = depositForm.value.date.split('-').reverse().join('/')
      localDeposits.value[idx].note = depositForm.value.note
      localDeposits.value[idx].images = depositForm.value.image ? ['Chứng từ'] : []
      
      const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
      emit('update:deposits', localDeposits.value)
      emit('update:paymentValue', totalValue)
      
      resetForm()
      selectedDepositIds.value = []
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
    }
  }
}

async function deleteDeposits() {
  if (selectedDepositIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn các cọc muốn xóa!', 'warning')
    return
  }
  
  if (props.bookingId) {
    uiStore.confirm({
      title: 'Hủy/Xóa đặt cọc',
      message: 'Bạn có chắc chắn muốn xóa đặt cọc này? Hệ thống sẽ tạo dòng đối trừ âm.',
      confirmText: 'Đồng ý',
      cancelText: 'Quay lại'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        for (const depId of selectedDepositIds.value) {
          await deletePayment(depId)
        }
        await syncDepositsFromBackend()
        uiStore.showToast('Đã xóa đặt cọc (tạo đối trừ) thành công!', 'success')
        selectedDepositIds.value = []
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa cọc!', 'error')
      }
    })
  } else {
    localDeposits.value = localDeposits.value.filter(d => !selectedDepositIds.value.includes(d.id))
    const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    selectedDepositIds.value = []
    uiStore.showToast('Đã xóa cọc thành công!', 'success')
  }
}

async function splitDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để tách!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = localDeposits.value.find(d => d.id === targetId)
  if (!dep) return

  if (!props.bookingId) {
    uiStore.showToast('Chỉ có thể tách cọc của booking đã lưu.', 'warning')
    return
  }

  const amtStr = window.prompt(`Nhập các số tiền sau khi tách, cách nhau bởi dấu phẩy (Ví dụ: 500000, 400000). Tổng phải bằng ${dep.amount.toLocaleString()} VND:`)
  if (!amtStr) return

  const amounts = amtStr.split(',').map(s => Number(s.trim())).filter(n => !isNaN(n) && n > 0)
  if (amounts.length < 2) {
    uiStore.showToast('Vui lòng nhập ít nhất 2 số tiền hợp lệ!', 'warning')
    return
  }

  try {
    await splitPayment(targetId, { amounts })
    await syncDepositsFromBackend()
    uiStore.showToast('Tách cọc thành công!', 'success')
    selectedDepositIds.value = []
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể tách cọc!', 'error')
  }
}

async function transferDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để chuyển!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  if (!props.bookingId) {
    uiStore.showToast('Chỉ có thể chuyển cọc của booking đã lưu.', 'warning')
    return
  }

  const destCode = window.prompt('Nhập mã booking đích muốn chuyển cọc sang (Ví dụ: GAL0012):')
  if (!destCode) return

  try {
    const res = await fetchBookings({ search: destCode.trim() })
    const bookings = res.data?.data || res.data || []
    const targetBooking = bookings.find(b => b.booking_code.toUpperCase() === destCode.trim().toUpperCase())
    
    if (!targetBooking) {
      uiStore.showToast(`Không tìm thấy booking có mã "${destCode}"!`, 'error')
      return
    }

    uiStore.confirm({
      title: 'Chuyển đặt cọc',
      message: `Bạn có chắc chắn muốn chuyển cọc sang booking "${targetBooking.booking_code} - ${targetBooking.booking_name}"?`,
      confirmText: 'Chuyển', cancelText: 'Hủy'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        await transferPayment(targetId, { target_booking_id: targetBooking.id })
        await syncDepositsFromBackend()
        uiStore.showToast(`Đã chuyển cọc sang booking ${targetBooking.booking_code} thành công!`, 'success')
        selectedDepositIds.value = []
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Lỗi khi chuyển cọc!', 'error')
      }
    })
  } catch (err) {
    uiStore.showToast('Có lỗi xảy ra khi tìm kiếm booking đích!', 'error')
  }
}

function formatCurrencyInput(val) {
  if (!val && val !== 0) return ''
  return Number(val).toLocaleString('vi-VN')
}

function cleanCurrencyValue(val) {
  if (!val) return 0
  return Number(val.replace(/\./g, '').replace(/,/g, ''))
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  uiStore.showToast('Đã copy ngày đặt cọc!', 'success')
}
</script>
