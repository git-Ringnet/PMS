<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const hotelServices = ref([])
const departments = ref([])
const searchServiceQuery = ref('')

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null
let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const isEditMode = ref(false)
const isServiceModalOpen = ref(false)
const serviceFormState = reactive({
  id: null,
  code: '',
  name: '',
  service_charge: 5,
  tax: 8,
  special_tax: 0,
  include_service_charge: true,
  include_tax: true,
  include_special_tax: true,
  folio: 1,
  short_name: '',
  unit: 'Lần',
  price: 0,
  is_active: true,
  department_id: null,
  department_ids: []
})

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isServiceModalOpen.value) {
    isServiceModalOpen.value = false
  }
}

const fetchDepartments = async () => {
  const res = await http.get('/departments')
  departments.value = res.data?.data || []
}

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
  modalPos.value = { x: 0, y: 0 }
  isEditMode.value = false
  Object.assign(serviceFormState, {
    id: null,
    code: '',
    name: '',
    service_charge: 5,
    tax: 8,
    special_tax: 0,
    include_service_charge: true,
    include_tax: true,
    include_special_tax: true,
    folio: 1,
    short_name: '',
    unit: 'Lần',
    price: 0,
    is_active: true,
    department_id: departments.value.find(department => department.code === 'FO')?.id || departments.value[0]?.id || null,
    department_ids: [departments.value.find(department => department.code === 'FO')?.id || departments.value[0]?.id].filter(Boolean)
  })
  isServiceModalOpen.value = true
}

const openEditServiceModal = (service) => {
  modalPos.value = { x: 0, y: 0 }
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
    is_active: service.is_active !== false,
    department_id: service.department_id,
    department_ids: service.department_ids?.length ? [...service.department_ids] : [service.department_id].filter(Boolean)
  })
  isServiceModalOpen.value = true
}

const saveService = async () => {
  if (!serviceFormState.code || !serviceFormState.name || !serviceFormState.department_ids.length) {
    uiStore.showToast('Vui lòng điền mã, tên dịch vụ và chọn ít nhất một bộ phận', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      ...serviceFormState,
      // Keep a stable primary value for legacy records while allowing many outlets.
      department_id: serviceFormState.department_ids.includes(serviceFormState.department_id)
        ? serviceFormState.department_id
        : serviceFormState.department_ids[0],
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
    uiStore.showToast(err.response?.data?.message || 'Không thể xóa dịch vụ này', 'error')
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
  Promise.all([fetchDepartments(), fetchHotelServices()])
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
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

    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <button @click="openAddServiceModal" class="btn-pms-primary">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>Thêm</span>
        </button>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchServiceQuery" placeholder="Tìm kiếm mã, tên..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-8 h-8 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <button v-if="searchServiceQuery" @click="searchServiceQuery = ''"
          class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Mã</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">Tên Dịch Vụ Đầy Đủ</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Trạng Thái</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Thuế (%)</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Thuế Đặc Biệt (%)</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Phí Phục Vụ (%)</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Bao Gồm Thuế</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Bao Gồm Thuế ĐB</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Bao Gồm Phí PV</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Folio</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Tên Ngắn</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Đơn Vị</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Giá</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Bộ Phận</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 whitespace-nowrap">Thao Tác</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in hotelServices.filter(item => !searchServiceQuery || (item.code && item.code.toLowerCase().includes(searchServiceQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchServiceQuery.toLowerCase())))"
            :key="s.id" @click="openEditServiceModal(s)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="p-2.5 font-semibold text-slate-800 text-center">{{ s.code }}</td>
            <td class="p-2.5 font-normal text-slate-700">{{ s.name }}</td>
            <td class="p-2.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.is_active" @change="toggleServiceFlag(s, 'is_active')"
                  class="sr-only peer" />
                <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-emerald-500"></div>
              </label>
            </td>
            <td class="p-2.5 text-center font-normal text-slate-600">{{ s.tax }}</td>
            <td class="p-2.5 text-center font-normal text-slate-600">{{ s.special_tax }}</td>
            <td class="p-2.5 text-center font-normal text-slate-600">{{ s.service_charge }}</td>
            <td class="p-2.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.include_tax" @change="toggleServiceFlag(s, 'include_tax')"
                  class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-2.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.include_special_tax"
                  @change="toggleServiceFlag(s, 'include_special_tax')" class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-2.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="s.include_service_charge"
                  @change="toggleServiceFlag(s, 'include_service_charge')" class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-2.5 text-center font-semibold text-slate-700">{{ s.folio }}</td>
            <td class="p-2.5 text-slate-600 font-normal">{{ s.short_name || '-' }}</td>
            <td class="p-2.5 text-slate-600 font-normal text-center">{{ s.unit || '-' }}</td>
            <td class="p-2.5 text-right font-semibold text-sky-700">{{ formatCurrency(s.price) }}</td>
            <td class="p-2.5 text-slate-500 font-normal text-xs">
              {{ s.departments?.length ? s.departments.map(department => department.code).join(', ') : (s.department_name || '-') }}
            </td>
            <td class="p-2.5 text-center">
              <button @click.stop="deleteService(s.id)"
                class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                title="Xóa dịch vụ">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="hotelServices.length === 0">
            <td colspan="15" class="p-6 text-center text-slate-400 italic">Không tìm thấy dịch vụ nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SERVICE -->
    <div v-if="isServiceModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-3xl shadow-2xl overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Sửa Dịch Vụ Khách Sạn' : 'Thêm Dịch Vụ Khách Sạn' }}
          </h2>
          <button @mousedown.stop @click="isServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 overflow-y-auto max-h-[80vh] text-xs font-semibold text-[#000000D9]">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Left Column -->
            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-1">
                <label>Mã <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="text" v-model="serviceFormState.code" placeholder="BC" :disabled="isEditMode"
                    class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                  <button v-if="serviceFormState.code && !isEditMode" @click="serviceFormState.code = ''"
                    class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label>Tên Dịch Vụ Đầy Đủ <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input type="text" v-model="serviceFormState.name" placeholder="Ăn sáng buffet trẻ em"
                    class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                  <button v-if="serviceFormState.name" @click="serviceFormState.name = ''"
                    class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label>Tên Ngắn</label>
                <div class="relative">
                  <input type="text" v-model="serviceFormState.short_name" placeholder="Ăn sáng trẻ em"
                    class="w-full border border-slate-200 bg-white rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                  <button v-if="serviceFormState.short_name" @click="serviceFormState.short_name = ''"
                    class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label>Bộ Phận Sử Dụng Khi Post Bill</label>
                <div class="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 p-2.5 bg-slate-50/50">
                  <label v-for="department in departments" :key="department.id" class="flex items-center gap-2 cursor-pointer font-normal text-xs">
                    <input v-model="serviceFormState.department_ids" type="checkbox" :value="department.id" class="rounded border-slate-300 text-sky-500" />
                    <span>{{ department.code }} - {{ department.name }}</span>
                  </label>
                </div>
                <p class="text-[11px] font-normal text-slate-400 mt-0.5">Dịch vụ chỉ xuất hiện ở màn hình post bill của các bộ phận được chọn.</p>
              </div>

              <label class="flex items-center gap-2 cursor-pointer font-normal mt-1">
                <input type="checkbox" v-model="serviceFormState.is_active" class="rounded border-slate-300 text-emerald-500" />
                <span>Hiển thị khi post bill</span>
              </label>
            </div>

            <!-- Right Column -->
            <div class="flex flex-col gap-3">
              <!-- Row 1: Sắp xếp theo thứ tự: Phí phục vụ, Thuế đặc biệt, Thuế -->
              <div class="grid grid-cols-3 gap-2.5">
                <div class="flex flex-col gap-1">
                  <label>Phí Phục Vụ (%)</label>
                  <input type="number" min="0" max="100" v-model="serviceFormState.service_charge"
                    class="border border-slate-200 rounded-lg p-2 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] bg-white" />
                </div>
                <div class="flex flex-col gap-1">
                  <label>Thuế Đặc Biệt (%)</label>
                  <input type="number" min="0" max="100" v-model="serviceFormState.special_tax"
                    class="border border-slate-200 rounded-lg p-2 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] bg-white" />
                </div>
                <div class="flex flex-col gap-1">
                  <label>Thuế (%)</label>
                  <input type="number" min="0" max="100" v-model="serviceFormState.tax"
                    class="border border-slate-200 rounded-lg p-2 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] bg-white" />
                </div>
              </div>

              <!-- Row 2: Toggles tương ứng -->
              <div class="grid grid-cols-3 gap-2.5 py-1">
                <div class="flex flex-col gap-1 items-start">
                  <span class="text-[11px] text-slate-500">Bao gồm phí PV</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                    <input type="checkbox" v-model="serviceFormState.include_service_charge" class="sr-only peer">
                    <div
                      class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
                <div class="flex flex-col gap-1 items-start">
                  <span class="text-[11px] text-slate-500">Bao gồm thuế ĐB</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                    <input type="checkbox" v-model="serviceFormState.include_special_tax" class="sr-only peer">
                    <div
                      class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
                <div class="flex flex-col gap-1 items-start">
                  <span class="text-[11px] text-slate-500">Bao gồm thuế</span>
                  <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                    <input type="checkbox" v-model="serviceFormState.include_tax" class="sr-only peer">
                    <div
                      class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500">
                    </div>
                  </label>
                </div>
              </div>

              <!-- Row 3: Giá, Đơn vị, Folio (chỉ 1-3) -->
              <div class="grid grid-cols-3 gap-2.5">
                <div class="flex flex-col gap-1">
                  <label>Giá</label>
                  <input type="number" min="0" v-model="serviceFormState.price"
                    class="border border-slate-200 rounded-lg p-2 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] bg-white" />
                </div>
                <div class="flex flex-col gap-1">
                  <label>Đơn Vị</label>
                  <select v-model="serviceFormState.unit"
                    class="border border-slate-200 rounded-lg p-2 bg-white focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9]">
                    <option value="Người">Người</option>
                    <option value="Lần">Lần</option>
                    <option value="Phòng">Phòng</option>
                    <option value="Cái">Cái</option>
                    <option value="Giờ">Giờ</option>
                  </select>
                </div>
                <div class="flex flex-col gap-1">
                  <label>Folio</label>
                  <select v-model="serviceFormState.folio"
                    class="border border-slate-200 rounded-lg p-2 bg-white focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9]">
                    <option :value="1">1</option>
                    <option :value="2">2</option>
                    <option :value="3">3</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveService" class="btn-pms-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            <span>Lưu</span>
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
