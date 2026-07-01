<script setup>
import { ref, watch } from 'vue'
import { fetchDepartments, fetchHotelServices } from '@/services/outlet-service'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  outlet: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'save'])

const departments = ref([])
const services = ref([])

const defaultForm = {
  code: '',
  name: '',
  department_code: '',
  service_code: '',
  is_active: true,
  check_voucher: false,
  check_combo: false,
  account_number: '',
  account_name: '',
  bank_name: '',
  payment_content: 'Thanh toan don hang [BillCode]',
  connector: '',
  vat_config_id: ''
}

const form = ref({ ...defaultForm })

const banks = [
  'Vietcombank',
  'BIDV',
  'VietinBank',
  'Agribank',
  'Techcombank',
  'MB Bank',
  'ACB',
  'VPBank',
  'Sacombank'
]

const vatConfigs = [
  { id: 1, name: 'VAT 8%' },
  { id: 2, name: 'VAT 10%' },
  { id: 3, name: 'Không tính VAT' }
]

const loadData = async () => {
  try {
    const [deptRes, svcRes] = await Promise.all([
      fetchDepartments(),
      fetchHotelServices()
    ])
    departments.value = deptRes.data.data || deptRes.data
    services.value = svcRes.data.data || svcRes.data
  } catch (error) {
    console.error('Lỗi khi tải danh mục bộ phận/dịch vụ:', error)
  }
}

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    loadData()
    if (props.outlet) {
      form.value = { ...props.outlet }
    } else {
      form.value = { ...defaultForm }
    }
  }
})

const handleSave = () => {
  if (!form.value.code || !form.value.name) {
    alert('Vui lòng nhập Mã và Tên!')
    return
  }
  emit('save', { ...form.value })
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-800 text-base">{{ outlet ? 'Sửa outlet' : 'Thêm outlet' }}</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Form Content -->
      <div class="flex-1 overflow-y-auto p-5 space-y-4 text-sm text-slate-700">
        <!-- Mã & Tên -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Mã <span class="text-red-500">*</span></label>
            <input type="text" v-model="form.code" :disabled="!!outlet" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500 disabled:bg-slate-100" />
          </div>
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Tên <span class="text-red-500">*</span></label>
            <input type="text" v-model="form.name" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500" />
          </div>
        </div>

        <!-- Bộ phận & Dịch vụ -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Bộ phận</label>
            <select v-model="form.department_code" class="w-full px-3 py-1.5 border border-slate-300 rounded-md bg-white focus:outline-none focus:border-sky-500">
              <option value="">Chọn giá trị</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.code">
                {{ dept.name }}
              </option>
            </select>
          </div>
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Dịch vụ</label>
            <select v-model="form.service_code" class="w-full px-3 py-1.5 border border-slate-300 rounded-md bg-white focus:outline-none focus:border-sky-500">
              <option value="">Chọn giá trị</option>
              <option v-for="svc in services" :key="svc.id" :value="svc.code">
                {{ svc.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Toggles -->
        <div class="flex items-center justify-between border-t border-b border-slate-100 py-3 mt-4">
          <!-- Kích hoạt -->
          <div class="flex flex-col items-center gap-1">
            <span class="text-xs font-semibold text-slate-500 uppercase">Kích hoạt</span>
            <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': form.is_active}" @click="form.is_active = !form.is_active">
              <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': form.is_active}"></div>
            </div>
          </div>
          <!-- Check vé voucher -->
          <div class="flex flex-col items-center gap-1">
            <span class="text-xs font-semibold text-slate-500 uppercase">Check vé voucher</span>
            <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': form.check_voucher}" @click="form.check_voucher = !form.check_voucher">
              <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': form.check_voucher}"></div>
            </div>
          </div>
          <!-- Check vé combo -->
          <div class="flex flex-col items-center gap-1">
            <span class="text-xs font-semibold text-slate-500 uppercase">Check vé combo</span>
            <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': form.check_combo}" @click="form.check_combo = !form.check_combo">
              <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': form.check_combo}"></div>
            </div>
          </div>
        </div>

        <!-- Số tài khoản & Tên tài khoản -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Số tài khoản</label>
            <input type="text" v-model="form.account_number" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500" />
          </div>
          <div class="space-y-1">
            <label class="font-semibold text-slate-600">Tên tài khoản</label>
            <input type="text" v-model="form.account_name" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500" />
          </div>
        </div>

        <!-- Ngân hàng -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-600">Ngân hàng</label>
          <select v-model="form.bank_name" class="w-full px-3 py-1.5 border border-slate-300 rounded-md bg-white focus:outline-none focus:border-sky-500">
            <option value="">Chọn giá trị</option>
            <option v-for="bank in banks" :key="bank" :value="bank">{{ bank }}</option>
          </select>
        </div>

        <!-- Nội dung thanh toán -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-600">Nội dung thanh toán</label>
          <input type="text" v-model="form.payment_content" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500" />
        </div>

        <!-- Connector (DevicePrinter) -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-600">Connector (DevicePrinter)</label>
          <input type="text" v-model="form.connector" class="w-full px-3 py-1.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500" />
        </div>

        <!-- Config VAT -->
        <div class="space-y-1">
          <label class="font-semibold text-slate-600">Config VAT</label>
          <select v-model="form.vat_config_id" class="w-full px-3 py-1.5 border border-slate-300 rounded-md bg-white focus:outline-none focus:border-sky-500">
            <option value="">Chọn giá trị</option>
            <option v-for="vc in vatConfigs" :key="vc.id" :value="vc.id">{{ vc.name }}</option>
          </select>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-150 flex justify-end gap-3 shrink-0">
        <button @click="emit('close')" class="flex items-center gap-1 bg-white hover:bg-slate-50 border border-slate-300 text-slate-600 px-4 py-1.5 rounded-lg text-sm font-semibold transition active:scale-[0.98]">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
          Hủy
        </button>
        <button @click="handleSave" class="flex items-center gap-1 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-4 py-1.5 rounded-lg text-sm font-semibold transition active:scale-[0.98]">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>
