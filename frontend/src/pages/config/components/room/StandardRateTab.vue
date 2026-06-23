<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const isLoaded = ref(false)

const standardRates = ref([])
const roomClasses = ref([])
const roomForms = ref([])

const ratePage = ref(1)
const ratePageSize = ref(25)

const paginatedStandardRates = computed(() => {
  const start = (ratePage.value - 1) * ratePageSize.value
  return standardRates.value.slice(start, start + ratePageSize.value)
})
const totalRatePages = computed(() => Math.ceil(standardRates.value.length / ratePageSize.value) || 1)

// Standard Rate Modal State
const isStandardRateModalOpen = ref(false)
const isEditStandardRateMode = ref(false)
const currentStandardRateId = ref(null)
const standardRateFormState = reactive({
  room_class_id: '',
  room_form_id: '',
  room_price: 0,
  extra_bed_price: 0
})

// Column selector states and helper functions
const isRateColumnSelectorOpen = ref(false)
const rateConfigRecord = ref(null)

const rateColumns = ref([
  { id: 'room_class', label: 'Loại phòng', visible: true },
  { id: 'room_form', label: 'Dạng phòng', visible: true },
  { id: 'room_price', label: 'Giá phòng', visible: true },
  { id: 'extra_bed_price', label: 'Giá thêm giường', visible: true },
  { id: 'action', label: 'Hành động', visible: true },
])

const isRateColumnVisible = (columnId) => {
  const col = rateColumns.value.find(c => c.id === columnId)
  return col ? col.visible : true
}

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isRateColumnSelectorOpen.value = false
  }
}

let bc = null

onMounted(async () => {
  fetchRoomClasses()
  fetchRoomForms()
  fetchStandardRates()
  await fetchConfigs()
  isLoaded.value = true
  document.addEventListener('click', closeAllPopovers)
  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
  if (bc) {
    bc.close()
  }
})

const fetchRoomClasses = async () => {
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải tên loại phòng:', err)
  }
}

const fetchRoomForms = async () => {
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dạng phòng:', err)
  }
}

const fetchStandardRates = async () => {
  loading.value = true
  try {
    const res = await http.get('/standard-rates')
    standardRates.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải giá phòng chuẩn:', err)
  } finally {
    loading.value = false
  }
}

const fetchConfigs = async () => {
  try {
    const res = await http.get('/hotel-configs')
    const configs = res.data.data || []

    const rateConfig = configs.find(c => c.name === 'rate_columns_visibility')
    if (rateConfig) {
      rateConfigRecord.value = rateConfig
      if (rateConfig.value) {
        const visibleIds = rateConfig.value.split(',')
        rateColumns.value.forEach(c => {
          c.visible = visibleIds.includes(c.id)
        })
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình ẩn hiện cột:', err)
  }
}

const saveConfig = async () => {
  const name = 'rate_columns_visibility'
  const value = rateColumns.value.filter(c => c.visible).map(c => c.id).join(',')

  try {
    if (rateConfigRecord.value) {
      const res = await http.put(`/hotel-configs/${rateConfigRecord.value.id}`, {
        name,
        value,
        description: 'Cột hiển thị của bảng giá phòng chuẩn'
      })
      rateConfigRecord.value = res.data.data
    } else {
      const res = await http.post('/hotel-configs', {
        name,
        value,
        description: 'Cột hiển thị của bảng giá phòng chuẩn'
      })
      rateConfigRecord.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi lưu cấu hình hiển thị cột:', err)
  }
}

watch(rateColumns, (newCols) => {
  if (isLoaded.value) {
    saveConfig()
  }
}, { deep: true })

const getErrorMessage = (err, defaultMsg = 'Có lỗi xảy ra') => {
  if (err.response?.status === 422 && err.response?.data?.errors) {
    const errors = err.response.data.errors
    const messages = []
    for (const key in errors) {
      if (Array.isArray(errors[key])) {
        errors[key].forEach(msg => {
          let translated = msg
          if (msg.toLowerCase().includes('already been taken') || msg.toLowerCase().includes('đã tồn tại')) {
            translated = 'Dữ liệu này đã tồn tại trong hệ thống'
          } else if (msg.toLowerCase().includes('field is required') || msg.toLowerCase().includes('bắt buộc')) {
            translated = 'Trường thông tin này là bắt buộc'
          }
          messages.push(translated)
        })
      }
    }
    if (messages.length > 0) {
      return messages.join(', ')
    }
  }
  return err.response?.data?.message || err.message || defaultMsg
}

const openAddStandardRateModal = () => {
  isEditStandardRateMode.value = false
  currentStandardRateId.value = null
  Object.assign(standardRateFormState, {
    room_class_id: roomClasses.value.filter(item => item.is_active)[0]?.id || roomClasses.value[0]?.id || '',
    room_form_id: roomForms.value[0]?.id || '',
    room_price: 0,
    extra_bed_price: 0
  })
  isStandardRateModalOpen.value = true
}

const openEditStandardRateModal = (rate) => {
  isEditStandardRateMode.value = true
  currentStandardRateId.value = rate.id
  Object.assign(standardRateFormState, {
    room_class_id: rate.room_class_id,
    room_form_id: rate.room_form_id,
    room_price: rate.room_price,
    extra_bed_price: rate.extra_bed_price
  })
  isStandardRateModalOpen.value = true
}

const saveStandardRate = async () => {
  if (!standardRateFormState.room_class_id || !standardRateFormState.room_form_id) {
    uiStore.showToast('Vui lòng chọn Loại phòng và Dạng phòng (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditStandardRateMode.value) {
      await http.put(`/standard-rates/${currentStandardRateId.value}`, standardRateFormState)
      uiStore.showToast('Cập nhật giá phòng chuẩn thành công!', 'success')
    } else {
      await http.post('/standard-rates', standardRateFormState)
      uiStore.showToast('Thêm giá phòng chuẩn thành công!', 'success')
    }
    isStandardRateModalOpen.value = false
    fetchStandardRates()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = getErrorMessage(err, 'Có lỗi xảy ra khi lưu giá phòng chuẩn')
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteStandardRate = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa giá phòng chuẩn này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/standard-rates/${id}`)
    uiStore.showToast('Xóa giá phòng chuẩn thành công!', 'success')
    fetchStandardRates()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa giá phòng chuẩn', 'error')
  } finally {
    loading.value = false
  }
}

const formatCurrency = (val) => {
  if (val === null || val === undefined || isNaN(Number(val))) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val))
}
</script>

<template>
  <div class="relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-4">
      <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Giá phòng chuẩn</h3>
      <div class="flex items-center gap-2 relative popover-container">
        <button @click="openAddStandardRateModal"
          class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+
          Thêm</button>
        <button @click.stop="isRateColumnSelectorOpen = !isRateColumnSelectorOpen"
          class="p-1.5 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded cursor-pointer flex items-center justify-center transition-colors shadow-xs"
          title="Ẩn/hiện cột">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
        </button>

        <!-- Column Selector Dropdown -->
        <div v-if="isRateColumnSelectorOpen"
          class="absolute right-0 top-full mt-1.5 z-45 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1">
          <label v-for="col in rateColumns" :key="col.id"
            class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
            <input type="checkbox" v-model="col.visible"
              class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
            <span class="text-xs text-slate-700 font-semibold">{{ col.label }}</span>
          </label>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th v-if="isRateColumnVisible('room_class')" class="p-3">Loại phòng</th>
            <th v-if="isRateColumnVisible('room_form')" class="p-3">Dạng phòng</th>
            <th v-if="isRateColumnVisible('room_price')" class="p-3">Giá phòng</th>
            <th v-if="isRateColumnVisible('extra_bed_price')" class="p-3">Giá thêm giường</th>
            <th v-if="isRateColumnVisible('action')" class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rate in paginatedStandardRates" :key="rate.id" @click="openEditStandardRateModal(rate)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td v-if="isRateColumnVisible('room_class')" class="p-3 font-bold text-slate-800">{{
              rate.room_class?.name }}</td>
            <td v-if="isRateColumnVisible('room_form')" class="p-3 font-bold text-slate-600">{{ rate.room_form?.name
              }}</td>
            <td v-if="isRateColumnVisible('room_price')" class="p-3 font-extrabold text-sky-700">{{
              formatCurrency(rate.room_price) }}</td>
            <td v-if="isRateColumnVisible('extra_bed_price')" class="p-3 font-bold text-amber-600">{{
              formatCurrency(rate.extra_bed_price) }}</td>
            <td v-if="isRateColumnVisible('action')" class="p-3 text-right">
              <button @click.stop="deleteStandardRate(rate.id)"
                class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="standardRates.length === 0">
            <td colspan="5" class="p-6 text-center text-slate-400 italic">Không có giá phòng chuẩn nào được tìm thấy.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center justify-end gap-2 mt-4 select-none">
      <button @click="ratePage = Math.max(1, ratePage - 1)" :disabled="ratePage === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &lt;
      </button>

      <button v-for="p in totalRatePages" :key="p" @click="ratePage = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="ratePage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
        {{ p }}
      </button>

      <button @click="ratePage = Math.min(totalRatePages, ratePage + 1)" :disabled="ratePage === totalRatePages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &gt;
      </button>

      <select v-model="ratePageSize" @change="ratePage = 1"
        class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
        <option :value="10">10 / page</option>
        <option :value="25">25 / page</option>
        <option :value="50">50 / page</option>
        <option :value="100">100 / page</option>
      </select>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT STANDARD RATE -->
    <div v-if="isStandardRateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider text-white">
            {{ isEditStandardRateMode ? 'Cập nhật giá phòng chuẩn' : 'Thêm giá phòng chuẩn' }}
          </h2>
          <button @click="isStandardRateModalOpen = false"
            class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-6 text-sm text-slate-700 font-bold">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span class="font-bold text-slate-600 text-xs uppercase">Loại phòng*</span>
              <select v-model="standardRateFormState.room_class_id"
                class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
                <option value="" disabled>Chọn loại phòng</option>
                <option
                  v-for="c in roomClasses.filter(item => item.is_active || item.id === standardRateFormState.room_class_id)"
                  :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="flex flex-col gap-1.5">
              <span class="font-bold text-slate-600 text-xs uppercase">Dạng phòng*</span>
              <select v-model="standardRateFormState.room_form_id"
                class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
                <option value="" disabled>Chọn dạng phòng</option>
                <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span class="font-bold text-slate-600 text-xs uppercase">Giá phòng</span>
              <input type="number" v-model="standardRateFormState.room_price" min="0" placeholder="0"
                class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
            </div>

            <div class="flex flex-col gap-1.5">
              <span class="font-bold text-slate-600 text-xs uppercase">Giá thêm giường</span>
              <input type="number" v-model="standardRateFormState.extra_bed_price" min="0" placeholder="0"
                class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
          <button @click="isStandardRateModalOpen = false"
            class="px-5 py-2 bg-slate-200 hover:bg-slate-350 text-slate-600 rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors">
            Đóng
          </button>
          <button @click="saveStandardRate"
            class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
            Lưu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
