<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const paymentMethods = ref([])

const departments = ['Reception/ Lê Tân', 'Restaurant/Nhà Hàng', 'House Keeping/Buồng Phòng', 'Spa']

const searchQuery = ref('')
const pagination = reactive({
  page: 1,
  limit: 10
})

const isEditMode = ref(false)
const isPaymentModalOpen = ref(false)
const paymentForm = reactive({
  id: null,
  code: '',
  name: '',
  account: '',
  account_name: '',
  bank_name: '',
  service_charge: 0,
  department: '',
  is_free: false,
  is_inactive: false
})

const fetchPaymentMethods = async () => {
  loading.value = true
  try {
    const res = await http.get('/payment-methods')
    paymentMethods.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu hình thức thanh toán:', err)
    uiStore.showToast('Không thể tải dữ liệu hình thức thanh toán', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchPaymentMethods()
})

const openAddPayment = () => {
  isEditMode.value = false
  Object.assign(paymentForm, {
    id: null,
    code: '',
    name: '',
    account: '',
    account_name: '',
    bank_name: '',
    service_charge: 0,
    department: '',
    is_free: false,
    is_inactive: false
  })
  isPaymentModalOpen.value = true
}

const openEditPayment = (item) => {
  isEditMode.value = true
  Object.assign(paymentForm, {
    id: item.id,
    code: item.code,
    name: item.name,
    account: item.account || '',
    account_name: item.account_name || '',
    bank_name: item.bank_name || '',
    service_charge: item.service_charge,
    department: item.department || '',
    is_free: !!item.is_free,
    is_inactive: !!item.is_inactive
  })
  isPaymentModalOpen.value = true
}

const savePayment = async () => {
  if (!paymentForm.code || !paymentForm.name) {
    uiStore.showToast('Vui lòng nhập mã và tên hình thức thanh toán', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/payment-methods/${paymentForm.id}`, paymentForm)
      uiStore.showToast('Cập nhật hình thức thanh toán thành công!', 'success')
    } else {
      await http.post('/payment-methods', paymentForm)
      uiStore.showToast('Thêm mới hình thức thanh toán thành công!', 'success')
    }
    isPaymentModalOpen.value = false
    await fetchPaymentMethods()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu hình thức thanh toán', 'error')
  } finally {
    loading.value = false
  }
}

const deletePayment = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa hình thức thanh toán này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/payment-methods/${id}`)
    uiStore.showToast('Xóa hình thức thanh toán thành công!', 'success')
    paymentMethods.value = paymentMethods.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi xóa hình thức thanh toán', 'error')
  } finally {
    loading.value = false
  }
}

const togglePaymentFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/payment-methods/${item.id}`, {
      ...item,
      [field]: updatedVal
    })
    item[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

const filteredPaymentMethods = computed(() => {
  const query = searchQuery.value.toLowerCase()
  return paymentMethods.value.filter(x => 
    !query || 
    x.code.toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query)
  )
})

const paginatedPaymentMethods = computed(() => {
  const start = (pagination.page - 1) * pagination.limit
  return filteredPaymentMethods.value.slice(start, start + pagination.limit)
})

const totalPaymentPages = computed(() => Math.ceil(filteredPaymentMethods.value.length / pagination.limit) || 1)
</script>

<template>
  <div class="flex flex-col gap-4 relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[400px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <div class="flex items-center gap-4">
        <button @click="openAddPayment"
          class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Thêm
        </button>
        <div class="flex items-center gap-1">
          <input type="checkbox" id="allow_sort_pay" class="cursor-pointer" />
          <label for="allow_sort_pay" class="text-xs text-slate-500 select-none cursor-pointer">Don't allow sort</label>
        </div>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchQuery" placeholder="Tìm kiếm mã, tên..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th class="p-3">Code</th>
            <th class="p-3">Tên</th>
            <th class="p-3">Tài khoản</th>
            <th class="p-3">Tên tài khoản</th>
            <th class="p-3">Tên ngân hàng</th>
            <th class="p-3 text-center">Phí phục vụ</th>
            <th class="p-3">Bộ phận</th>
            <th class="p-3 text-center">HT Miễn Phí</th>
            <th class="p-3 text-center">Không sử dụng</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in paginatedPaymentMethods" :key="item.id" @click="openEditPayment(item)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ item.code }}</td>
            <td class="p-3 font-bold text-slate-700">{{ item.name }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ item.account || '-' }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ item.account_name || '-' }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ item.bank_name || '-' }}</td>
            <td class="p-3 text-center font-bold text-slate-700">{{ item.service_charge }}</td>
            <td class="p-3 text-slate-500 font-semibold text-xs">{{ item.department || '-' }}</td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.is_free" @change="togglePaymentFlag(item, 'is_free')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.is_inactive" @change="togglePaymentFlag(item, 'is_inactive')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-right">
              <button @click.stop="deletePayment(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-end gap-2 mt-4 select-none">
      <button @click="pagination.page = Math.max(1, pagination.page - 1)" :disabled="pagination.page === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&lt;</button>
      <button v-for="p in totalPaymentPages" :key="p" @click="pagination.page = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="pagination.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
      <button @click="pagination.page = Math.min(totalPaymentPages, pagination.page + 1)" :disabled="pagination.page === totalPaymentPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
    </div>

    <!-- Modal: HÌNH THỨC THANH TOÁN -->
    <div v-if="isPaymentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 520px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>HÌNH THỨC THANH TOÁN</span>
          <button @click="isPaymentModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Code</label>
              <input type="text" v-model="paymentForm.code" :disabled="isEditMode"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên</label>
              <input type="text" v-model="paymentForm.name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tài khoản</label>
              <input type="text" v-model="paymentForm.account"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên tài khoản</label>
              <input type="text" v-model="paymentForm.account_name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tên ngân hàng</label>
              <input type="text" v-model="paymentForm.bank_name"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Phí phục vụ</label>
              <input type="number" v-model="paymentForm.service_charge"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-slate-500">Department</label>
            <select v-model="paymentForm.department"
              class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold bg-white">
              <option value="">Chọn: 0</option>
              <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
            </select>
          </div>
          <div class="flex items-center gap-6 mt-2">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-slate-500">HT Miễn Phí</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_free" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-slate-500">Không sử dụng</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="paymentForm.is_inactive" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-slate-100">
            <button @click="isPaymentModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="savePayment" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
              </svg>
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.15s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
</style>
