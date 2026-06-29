<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const hotelServices = ref([])
const searchServiceQuery = ref('')

const isEditMode = ref(false)
const isServiceModalOpen = ref(false)
const serviceFormState = reactive({
  id: null,
  code: '',
  name: '',
  service_charge: 0,
  tax: 8,
  special_tax: 0,
  include_service_charge: false,
  include_tax: true,
  include_special_tax: true,
  folio: 1,
  short_name: '',
  unit: '',
  price: 0,
  department: 'Reception/ Lê Tân'
})

const fetchHotelServices = async () => {
  loading.value = true
  try {
    const res = await http.get('/hotel-services')
    if (res.data && res.data.data) {
      hotelServices.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách dịch vụ:', err)
  } finally {
    loading.value = false
  }
}

const openAddServiceModal = () => {
  isEditMode.value = false
  Object.assign(serviceFormState, {
    id: null,
    code: '',
    name: '',
    service_charge: 0,
    tax: 8,
    special_tax: 0,
    include_service_charge: false,
    include_tax: true,
    include_special_tax: true,
    folio: 1,
    short_name: '',
    unit: '',
    price: 0,
    department: 'Reception/ Lê Tân'
  })
  isServiceModalOpen.value = true
}

const openEditServiceModal = (service) => {
  isEditMode.value = true
  Object.assign(serviceFormState, {
    id: service.id,
    code: service.code,
    name: service.name,
    service_charge: service.service_charge,
    tax: service.tax,
    special_tax: service.special_tax,
    include_service_charge: !!service.include_service_charge,
    include_tax: !!service.include_tax,
    include_special_tax: !!service.include_special_tax,
    folio: service.folio,
    short_name: service.short_name,
    unit: service.unit,
    price: service.price,
    department: service.department
  })
  isServiceModalOpen.value = true
}

const saveService = async () => {
  if (!serviceFormState.code || !serviceFormState.name) {
    uiStore.showToast('Vui lòng điền mã và tên dịch vụ', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      ...serviceFormState,
      service_charge: 0,
      include_service_charge: false,
    }
    if (isEditMode.value) {
      await http.put(`/hotel-services/${serviceFormState.id}`, payload)
      uiStore.showToast('Cập nhật dịch vụ thành công!', 'success')
    } else {
      await http.post('/hotel-services', payload)
      uiStore.showToast('Thêm dịch vụ mới thành công!', 'success')
    }
    isServiceModalOpen.value = false
    fetchHotelServices()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu dịch vụ'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteService = async (serviceId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dịch vụ này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/hotel-services/${serviceId}`)
    uiStore.showToast('Xóa dịch vụ thành công!', 'success')
    fetchHotelServices()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa dịch vụ này', 'error')
  } finally {
    loading.value = false
  }
}

const toggleServiceFlag = async (service, field) => {
  try {
    const updatedVal = !service[field]
    await http.put(`/hotel-services/${service.id}`, {
      ...service,
      [field]: updatedVal
    })
    service[field] = updatedVal
    uiStore.showToast('Cập nhật trạng thái thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}

onMounted(() => {
  fetchHotelServices()
})
</script>

<template>
  <div class="flex flex-col gap-4 relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <button @click="openAddServiceModal"
          class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Thêm
        </button>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchServiceQuery" placeholder="Tìm kiếm mã, tên..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th class="p-3 whitespace-nowrap">Mã</th>
            <th class="p-3 min-w-[200px]">Tên dịch vụ đầy đủ</th>
            <th class="p-3 text-center whitespace-nowrap">Thuế (%)</th>
            <th class="p-3 text-center whitespace-nowrap">Thuế đặc biệt (%)</th>
            <th class="p-3 text-center whitespace-nowrap">Bao gồm thuế</th>
            <th class="p-3 text-center whitespace-nowrap">Bao gồm thuế đặc biệt</th>
            <th class="p-3 text-center whitespace-nowrap">Folio</th>
            <th class="p-3 whitespace-nowrap">Tên ngắn</th>
            <th class="p-3 whitespace-nowrap">Đơn vị</th>
            <th class="p-3 text-right whitespace-nowrap">Giá</th>
            <th class="p-3 whitespace-nowrap">Bộ phận</th>
            <th class="p-3 text-right whitespace-nowrap">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in hotelServices.filter(item => !searchServiceQuery || (item.code && item.code.toLowerCase().includes(searchServiceQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchServiceQuery.toLowerCase())))"
            :key="s.id" @click="openEditServiceModal(s)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ s.code }}</td>
            <td class="p-3 font-bold text-slate-700">{{ s.name }}</td>
            <td class="p-3 text-center font-bold text-slate-600">{{ s.tax }}</td>
            <td class="p-3 text-center font-bold text-slate-600">{{ s.special_tax }}</td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.include_tax" @change="toggleServiceFlag(s, 'include_tax')"
                  class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.include_special_tax"
                  @change="toggleServiceFlag(s, 'include_special_tax')" class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-3 text-center font-bold text-slate-700">{{ s.folio }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ s.short_name || '-' }}</td>
            <td class="p-3 text-slate-600 font-semibold">{{ s.unit || '-' }}</td>
            <td class="p-3 text-right font-extrabold text-sky-700">{{ formatCurrency(s.price) }}</td>
            <td class="p-3 text-slate-500 font-semibold text-xs">{{ s.department || '-' }}</td>
            <td class="p-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click.stop="deleteService(s.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="hotelServices.length === 0">
            <td colspan="12" class="p-6 text-center text-slate-400 italic">Không tìm thấy dịch vụ nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SERVICE -->
    <div v-if="isServiceModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">Hotel Service</h2>
          <button @click="isServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-6 overflow-y-auto max-h-[85vh] text-sm font-bold text-slate-600">
          <h3 class="text-sm font-black text-slate-700 pb-1.5 border-b border-slate-100 uppercase tracking-wide">
            Thông tin dịch vụ <span class="text-red-500">*</span>
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="flex flex-col gap-4">
              <div class="flex flex-col gap-1.5">
                <span>Mã</span>
                <input type="text" v-model="serviceFormState.code" placeholder="BC"
                  class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm bg-white" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Tên dịch vụ đầy đủ</span>
                <input type="text" v-model="serviceFormState.name" placeholder="Ăn sáng buffet trẻ em"
                  class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm bg-white font-semibold" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Tên ngắn</span>
                <input type="text" v-model="serviceFormState.short_name" placeholder="Ăn sáng trẻ em"
                  class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm bg-white font-semibold" />
              </div>

              <div class="flex flex-col gap-1.5">
                <span>Bộ phận</span>
                <select v-model="serviceFormState.department"
                  class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                  <option value="Reception/ Lê Tân">Reception/ Lê Tân</option>
                  <option value="House Keeping/Buồng Phòng">House Keeping/Buồng Phòng</option>
                  <option value="Restaurant/Nhà Hàng">Restaurant/Nhà Hàng</option>
                  <option value="SPA">SPA</option>
                </select>
              </div>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col gap-4">
              <!-- Row 1: Thuế, Thuế đặc biệt -->
              <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                  <span>Thuế</span>
                  <input type="number" v-model="serviceFormState.tax"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế đặc biệt</span>
                  <input type="number" v-model="serviceFormState.special_tax"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
              </div>

              <!-- Row 2: Toggles -->
              <div class="grid grid-cols-2 gap-3 py-1">
                <div class="flex flex-col gap-1.5">
                  <span>Thuế</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" v-model="serviceFormState.include_tax" class="sr-only peer">
                    <div
                      class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Thuế đặc biệt</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input type="checkbox" v-model="serviceFormState.include_special_tax" class="sr-only peer">
                    <div
                      class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
              </div>

              <!-- Row 3: Giá, Đơn vị, Folio -->
              <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1.5">
                  <span>Giá</span>
                  <input type="number" v-model="serviceFormState.price"
                    class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold bg-white" />
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Đơn vị</span>
                  <select v-model="serviceFormState.unit"
                    class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                    <option value="Người">Người</option>
                    <option value="Lần">Lần</option>
                    <option value="Phòng">Phòng</option>
                    <option value="Cái">Cái</option>
                    <option value="Giờ">Giờ</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1.5">
                  <span>Folio</span>
                  <select v-model="serviceFormState.folio"
                    class="border border-slate-200 rounded-lg p-2.5 bg-white focus:outline-sky-500 text-sm font-semibold">
                    <option :value="1">1</option>
                    <option :value="2">2</option>
                    <option :value="3">3</option>
                    <option :value="4">4</option>
                    <option :value="5">5</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isServiceModalOpen = false"
            class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-bold text-sm cursor-pointer transition-colors flex items-center gap-1.5 border-none">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Đóng
          </button>
          <button @click="saveService"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors flex items-center gap-1.5">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
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
