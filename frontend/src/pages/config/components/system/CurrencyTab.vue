<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const currencies = ref([])

const searchQuery = ref('')
const pagination = reactive({
  page: 1,
  limit: 10
})

const isEditMode = ref(false)
const isCurrencyModalOpen = ref(false)
const currencyImageInput = ref(null)

const currencyForm = reactive({
  id: null,
  code: '',
  name: '',
  country: '',
  short_name: '',
  decimals_to_round: 0,
  is_main: false,
  is_active: true,
  exchange_rate: 1,
  imageFile: null,
  imagePreview: null,
  remove_image: false
})

const fetchCurrencies = async () => {
  loading.value = true
  try {
    const res = await http.get('/currencies')
    currencies.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải danh sách tiền tệ:', err)
    uiStore.showToast('Không thể tải danh sách tiền tệ', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchCurrencies()
})

const triggerCurrencyImageUpload = () => {
  if (currencyImageInput.value) {
    currencyImageInput.value.click()
  }
}

const handleCurrencyImageUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return
  currencyForm.imageFile = file
  currencyForm.remove_image = false
  const reader = new FileReader()
  reader.onload = (event) => {
    currencyForm.imagePreview = event.target.result
  }
  reader.readAsDataURL(file)
}

const removeCurrencyImage = () => {
  currencyForm.imageFile = null
  currencyForm.imagePreview = null
  currencyForm.remove_image = true
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
}

const formatExchangeRate = (val) => {
  return new Intl.NumberFormat('vi-VN').format(val)
}

const openAddCurrency = () => {
  isEditMode.value = false
  Object.assign(currencyForm, {
    id: null,
    code: '',
    name: '',
    country: '',
    short_name: '',
    decimals_to_round: 0,
    is_main: false,
    is_active: true,
    exchange_rate: 1,
    imageFile: null,
    imagePreview: null,
    remove_image: false
  })
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
  isCurrencyModalOpen.value = true
}

const openEditCurrency = (item) => {
  isEditMode.value = true
  Object.assign(currencyForm, {
    id: item.id,
    code: item.code,
    name: item.name,
    country: item.country,
    short_name: item.short_name || '',
    decimals_to_round: item.decimals_to_round,
    is_main: !!item.is_main,
    is_active: !!item.is_active,
    exchange_rate: item.exchange_rate,
    imageFile: null,
    imagePreview: item.image_path || null,
    remove_image: false
  })
  if (currencyImageInput.value) {
    currencyImageInput.value.value = ''
  }
  isCurrencyModalOpen.value = true
}

const saveCurrency = async () => {
  if (!currencyForm.code || !currencyForm.name || !currencyForm.country) {
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin tiền tệ', 'warning')
    return
  }
  loading.value = true
  try {
    const fd = new FormData()
    fd.append('code', currencyForm.code)
    fd.append('name', currencyForm.name)
    fd.append('country', currencyForm.country)
    fd.append('short_name', currencyForm.short_name)
    fd.append('decimals_to_round', currencyForm.decimals_to_round)
    fd.append('is_main', currencyForm.is_main ? '1' : '0')
    fd.append('is_active', currencyForm.is_active ? '1' : '0')
    fd.append('exchange_rate', currencyForm.exchange_rate)
    if (currencyForm.imageFile) {
      fd.append('image', currencyForm.imageFile)
    }
    if (currencyForm.remove_image) {
      fd.append('remove_image', 'true')
    }

    if (isEditMode.value) {
      fd.append('_method', 'PUT')
      await http.post(`/currencies/${currencyForm.id}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      uiStore.showToast('Cập nhật tiền tệ thành công!', 'success')
    } else {
      await http.post('/currencies', fd, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      uiStore.showToast('Thêm mới tiền tệ thành công!', 'success')
    }
    isCurrencyModalOpen.value = false
    await fetchCurrencies()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu tiền tệ', 'error')
  } finally {
    loading.value = false
  }
}

const deleteCurrency = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa tiền tệ này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/currencies/${id}`)
    uiStore.showToast('Xóa tiền tệ thành công!', 'success')
    currencies.value = currencies.value.filter(x => x.id !== id)
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi xóa tiền tệ', 'error')
  } finally {
    loading.value = false
  }
}

const toggleCurrencyFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/currencies/${item.id}`, {
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

const filteredCurrencies = computed(() => {
  const query = searchQuery.value.toLowerCase()
  return currencies.value.filter(x => 
    !query || 
    x.code.toLowerCase().includes(query) || 
    x.name.toLowerCase().includes(query) ||
    x.country.toLowerCase().includes(query)
  )
})

const paginatedCurrencies = computed(() => {
  const start = (pagination.page - 1) * pagination.limit
  return filteredCurrencies.value.slice(start, start + pagination.limit)
})

const totalCurrencyPages = computed(() => Math.ceil(filteredCurrencies.value.length / pagination.limit) || 1)
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
      <button @click="openAddCurrency"
        class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Thêm
      </button>
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
            <th class="p-3">Mã tiền tệ</th>
            <th class="p-3">Hình ảnh</th>
            <th class="p-3">Tên loại tiền tệ</th>
            <th class="p-3">Quốc gia</th>
            <th class="p-3 text-center">Tiền tệ chính</th>
            <th class="p-3 text-center">Trạng thái sử dụng</th>
            <th class="p-3 text-center">Làm tròn chữ số thập phân</th>
            <th class="p-3 text-right">Tỉ giá</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in paginatedCurrencies" :key="item.id" @click="openEditCurrency(item)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ item.code }}</td>
            <td class="p-3">
              <img v-if="item.image_path" :src="item.image_path" class="w-8 h-5 object-cover rounded shadow-xs" alt="flag" />
              <span v-else class="text-slate-400 italic text-xs">Không có</span>
            </td>
            <td class="p-3 font-bold text-slate-700">{{ item.name }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ item.country }}</td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.is_main" @change="toggleCurrencyFlag(item, 'is_main')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.is_active" @change="toggleCurrencyFlag(item, 'is_active')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center font-bold text-slate-700">{{ item.decimals_to_round }}</td>
            <td class="p-3 text-right font-bold text-sky-700">{{ formatExchangeRate(item.exchange_rate) }}</td>
            <td class="p-3 text-right">
              <button @click.stop="deleteCurrency(item.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
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
      <button v-for="p in totalCurrencyPages" :key="p" @click="pagination.page = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="pagination.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
      <button @click="pagination.page = Math.min(totalCurrencyPages, pagination.page + 1)" :disabled="pagination.page === totalCurrencyPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
    </div>

    <!-- Modal: TIỀN TỆ -->
    <div v-if="isCurrencyModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 600px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>{{ isEditMode ? 'Chỉnh sửa loại tiền tệ' : 'Thêm loại tiền tệ' }}</span>
          <button @click="isCurrencyModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-6 items-start">
            <!-- Left Fields -->
            <div class="flex flex-col gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Mã tiền tệ</label>
                <input type="text" v-model="currencyForm.code" :disabled="isEditMode" placeholder="Mã tiền tệ"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tên loại tiền tệ</label>
                <input type="text" v-model="currencyForm.name" placeholder="Tên loại tiền tệ"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Quốc gia</label>
                <input type="text" v-model="currencyForm.country" placeholder="Quốc gia"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tên viết tắt</label>
                <input type="text" v-model="currencyForm.short_name" placeholder="Tên viết tắt"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Làm tròn chữ số thập phân</label>
                <input type="number" v-model="currencyForm.decimals_to_round"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
              </div>

              <!-- Flag Upload Box -->
              <div class="flex flex-col gap-2 mt-2">
                <label class="text-xs font-bold text-slate-500 block">Hình ảnh</label>
                <div class="border border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center gap-2 bg-slate-50 relative min-h-[140px]">
                  <input type="file" ref="currencyImageInput" class="hidden" accept="image/*" @change="handleCurrencyImageUpload" />
                  
                  <div v-if="currencyForm.imagePreview" class="flex flex-col items-center gap-2">
                    <img :src="currencyForm.imagePreview" class="w-24 h-15 object-cover rounded shadow-md border border-slate-100" />
                    <div class="flex items-center gap-3 mt-1">
                      <button @click="triggerCurrencyImageUpload" class="text-sky-500 hover:text-sky-700 bg-transparent border-none cursor-pointer text-xs font-extrabold">&#x1F441; Thay đổi</button>
                      <button @click="removeCurrencyImage" class="text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer text-xs font-extrabold">&#x1F5D1; Xóa</button>
                    </div>
                  </div>
                  
                  <div v-else @click="triggerCurrencyImageUpload" class="flex flex-col items-center gap-1 cursor-pointer w-full h-full py-4 text-slate-400 hover:text-slate-600">
                    <span class="text-3xl font-light">+</span>
                    <span class="text-xs font-bold">Chọn tệp</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Fields -->
            <div class="flex flex-col gap-4">
              <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-xs font-bold text-slate-500">Tiền tệ chính</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_main" class="sr-only peer" />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>

              <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-xs font-bold text-slate-500">Trạng thái sử dụng</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="currencyForm.is_active" class="sr-only peer" />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-xs font-bold text-slate-500">Tỉ giá</label>
                <input type="number" v-model="currencyForm.exchange_rate" step="0.0001"
                  class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-bold" />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button @click="isCurrencyModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm flex items-center gap-1.5 border-none cursor-pointer transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="saveCurrency" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
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
