<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)

const roomRateCodes = ref([])
const roomClasses = ref([])
const roomForms = ref([])

const searchQuery = ref('')
const pagination = reactive({
  page: 1,
  limit: 10
})

const isEditMode = ref(false)
const isRateModalOpen = ref(false)

const rateForm = reactive({
  Ma: '',
  Description: '',
  BeginDate: '',
  EndDate: '',
  Currency: 'VND',
  IncludeBF: false,
  Disable: false,
  AllowChangeRate: false,
  IsChannelManager: false
})

const fetchRoomRateCodeData = async () => {
  loading.value = true
  try {
    const [rrcRes, clRes, fmRes] = await Promise.all([
      http.get('/room-rate-codes'),
      http.get('/room-classes'),
      http.get('/room-forms')
    ])
    roomRateCodes.value = rrcRes.data.data || []
    roomClasses.value = clRes.data.data || []
    roomForms.value = fmRes.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu mã giá phòng:', err)
    uiStore.showToast('Không thể tải dữ liệu mã giá phòng', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchRoomRateCodeData()
})

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

const openAddRate = () => {
  isEditMode.value = false
  Object.assign(rateForm, {
    Ma: '',
    Description: '',
    BeginDate: new Date().toISOString().split('T')[0],
    EndDate: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    Currency: 'VND',
    IncludeBF: false,
    Disable: false,
    AllowChangeRate: false,
    IsChannelManager: false
  })
  isRateModalOpen.value = true
}

const openEditRate = (item) => {
  isEditMode.value = true
  Object.assign(rateForm, {
    Ma: item.Ma,
    Description: item.Description || '',
    BeginDate: item.BeginDate || '',
    EndDate: item.EndDate || '',
    Currency: item.Currency || 'VND',
    IncludeBF: !!item.IncludeBF,
    Disable: !!item.Disable,
    AllowChangeRate: !!item.AllowChangeRate,
    IsChannelManager: !!item.IsChannelManager
  })
  isRateModalOpen.value = true
}

const saveRate = async () => {
  if (!rateForm.Ma) {
    uiStore.showToast('Vui lòng nhập mã giá phòng', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = { ...rateForm }
    if (isEditMode.value) {
      await http.put(`/room-rate-codes/${rateForm.Ma}`, payload)
      uiStore.showToast('Cập nhật mã giá phòng thành công!', 'success')
    } else {
      await http.post('/room-rate-codes', payload)
      uiStore.showToast('Thêm mới mã giá phòng thành công!', 'success')
    }
    isRateModalOpen.value = false
    await fetchRoomRateCodeData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi lưu mã giá phòng', 'error')
  } finally {
    loading.value = false
  }
}

const deleteRate = async (ma) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc muốn xóa mã giá phòng này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/room-rate-codes/${ma}`)
    uiStore.showToast('Xóa mã giá phòng thành công!', 'success')
    roomRateCodes.value = roomRateCodes.value.filter(x => x.Ma !== ma)
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi xóa mã giá phòng', 'error')
  } finally {
    loading.value = false
  }
}

const toggleRateFlag = async (item, field) => {
  try {
    const updatedVal = !item[field]
    await http.put(`/room-rate-codes/${item.Ma}`, {
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

const filteredRates = computed(() => {
  const query = searchQuery.value.toLowerCase()
  return roomRateCodes.value.filter(x => 
    !query || 
    (x.Ma && x.Ma.toLowerCase().includes(query)) || 
    (x.Description && x.Description.toLowerCase().includes(query))
  )
})

const paginatedRates = computed(() => {
  const start = (pagination.page - 1) * pagination.limit
  return filteredRates.value.slice(start, start + pagination.limit)
})

const totalRatePages = computed(() => Math.ceil(filteredRates.value.length / pagination.limit) || 1)
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
      <button @click="openAddRate"
        class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Thêm
      </button>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchQuery" placeholder="Tìm kiếm mã, mô tả..."
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
            <th class="p-3">Mã</th>
            <th class="p-3">Mô tả</th>
            <th class="p-3">Tiền tệ</th>
            <th class="p-3">Ngày bắt đầu</th>
            <th class="p-3">Ngày kết thúc</th>
            <th class="p-3 text-center">Ăn sáng</th>
            <th class="p-3 text-center">Cho phép nhập giá</th>
            <th class="p-3 text-center">Không sử dụng</th>
            <th class="p-3 text-center">Channel Manager</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in paginatedRates" :key="item.Ma" @click="openEditRate(item)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ item.Ma }}</td>
            <td class="p-3 font-bold text-slate-700">{{ item.Description || '-' }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ item.Currency || 'VND' }}</td>
            <td class="p-3 text-slate-500 font-semibold font-mono">{{ formatDate(item.BeginDate) || '-' }}</td>
            <td class="p-3 text-slate-500 font-semibold font-mono">{{ formatDate(item.EndDate) || '-' }}</td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.IncludeBF" @change="toggleRateFlag(item, 'IncludeBF')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.AllowChangeRate" @change="toggleRateFlag(item, 'AllowChangeRate')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.Disable" @change="toggleRateFlag(item, 'Disable')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="item.IsChannelManager" @change="toggleRateFlag(item, 'IsChannelManager')" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </td>
            <td class="p-3 text-right">
              <button @click.stop="deleteRate(item.Ma)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
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
      <button v-for="p in totalRatePages" :key="p" @click="pagination.page = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="pagination.page === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">{{ p }}</button>
      <button @click="pagination.page = Math.min(totalRatePages, pagination.page + 1)" :disabled="pagination.page === totalRatePages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">&gt;</button>
    </div>

    <!-- Modal: MÃ GIÁ PHÒNG -->
    <div v-if="isRateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none animate-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full overflow-hidden" style="max-width: 600px;">
        <div class="px-6 py-4 bg-[#8dcbf4] text-white font-black text-sm flex items-center justify-between">
          <span>{{ isEditMode ? 'Chỉnh sửa rate code' : 'Thêm rate code' }}</span>
          <button @click="isRateModalOpen = false" class="text-white hover:text-sky-100 bg-transparent border-none cursor-pointer text-lg">&times;</button>
        </div>
        <div class="p-6 flex flex-col gap-4 max-h-[80vh] overflow-y-auto">
          <span class="text-xs font-black text-red-500 uppercase border-b border-slate-100 pb-1">Thông tin *</span>
          
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Mã</label>
              <input type="text" v-model="rateForm.Ma" :disabled="isEditMode" placeholder="Mã"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Mô tả</label>
              <input type="text" v-model="rateForm.Description" placeholder="Mô tả"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Từ ngày</label>
              <input type="date" v-model="rateForm.BeginDate"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold font-mono" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Đến ngày</label>
              <input type="date" v-model="rateForm.EndDate"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold font-mono" />
            </div>
            <div class="flex flex-col gap-1">
              <label class="text-xs font-bold text-slate-500">Tiền tệ</label>
              <input type="text" v-model="rateForm.Currency" placeholder="VND"
                class="border border-slate-200 rounded-lg p-2.5 text-sm focus:outline-sky-500 font-semibold" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 mt-2">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <span class="text-xs font-bold text-slate-500">Ăn sáng</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="rateForm.IncludeBF" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <span class="text-xs font-bold text-slate-500">Cho phép nhập giá</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="rateForm.AllowChangeRate" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <span class="text-xs font-bold text-slate-500">Không sử dụng</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="rateForm.Disable" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <span class="text-xs font-bold text-slate-500">Đẩy lên Channel Manager</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="rateForm.IsChannelManager" class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-4 pt-4 border-t border-slate-100">
            <button @click="isRateModalOpen = false" class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Đóng
            </button>
            <button @click="saveRate" class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
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
