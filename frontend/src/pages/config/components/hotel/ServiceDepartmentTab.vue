<script setup>
import { computed, onMounted, ref, reactive } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const departments = ref([])
const hotelServices = ref([])
const activeDepartmentId = ref(null)
const activeDepartment = computed(() => departments.value.find(department => department.id === activeDepartmentId.value) || null)
const departmentServices = computed(() => activeDepartment.value?.hotel_services || [])

const isDeptServiceModalOpen = ref(false)
const isEditDeptServiceMode = ref(false)
const deptServiceFormState = reactive({
  hotel_service_id: null,
  description: ''
})
const searchDeptServiceQuery = ref('')

const openAddDeptServiceModal = () => {
  isEditDeptServiceMode.value = false
  Object.assign(deptServiceFormState, {
    hotel_service_id: hotelServices.value[0]?.id || null,
    description: ''
  })
  isDeptServiceModalOpen.value = true
}

const openEditDeptServiceModal = (service) => {
  isEditDeptServiceMode.value = true
  Object.assign(deptServiceFormState, {
    hotel_service_id: service.id,
    description: service.pivot?.description || ''
  })
  isDeptServiceModalOpen.value = true
}

const fetchData = async () => {
  const [departmentResponse, serviceResponse] = await Promise.all([
    http.get('/departments'),
    http.get('/hotel-services')
  ])
  departments.value = departmentResponse.data?.data || []
  hotelServices.value = serviceResponse.data?.data || []
  if (!activeDepartmentId.value) activeDepartmentId.value = departments.value[0]?.id || null
}

const saveDeptService = async () => {
  if (!activeDepartment.value || !deptServiceFormState.hotel_service_id) {
    uiStore.showToast('Vui lòng chọn dịch vụ', 'warning')
    return
  }
  try {
    const payload = {
      hotel_service_id: deptServiceFormState.hotel_service_id,
      description: deptServiceFormState.description
    }
    if (isEditDeptServiceMode.value) {
      await http.put(`/departments/${activeDepartment.value.id}/services/${deptServiceFormState.hotel_service_id}`, payload)
    } else {
      await http.post(`/departments/${activeDepartment.value.id}/services`, payload)
    }
    await fetchData()
    uiStore.showToast('Lưu dịch vụ bộ phận thành công!', 'success')
    isDeptServiceModalOpen.value = false
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu dịch vụ bộ phận', 'error')
  }
}

const deleteDeptService = async (serviceId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dịch vụ này khỏi bộ phận?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  await http.delete(`/departments/${activeDepartment.value.id}/services/${serviceId}`)
  await fetchData()
  uiStore.showToast('Xóa dịch vụ thành công!', 'success')
}

onMounted(fetchData)
</script>

<template>
  <div class="flex gap-6 items-stretch relative">
    <!-- Left: Departments -->
    <div class="w-1/4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex flex-col gap-1.5 shrink-0">
      <span
        class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Bộ
        phận</span>
      <button v-for="dept in departments" :key="dept.id" @click="activeDepartmentId = dept.id"
        class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
        :class="activeDepartmentId === dept.id ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
        {{ dept.code }} - {{ dept.name }}
      </button>
    </div>

    <!-- Right: Services under Department -->
    <div class="flex-1 flex flex-col gap-4">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100 flex-wrap gap-2">
        <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
          Dịch vụ thuộc bộ phận: {{ activeDepartment?.name || '-' }}
        </span>
        <div class="flex gap-2">
          <button @click="openAddDeptServiceModal"
            class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-xs font-bold border-none cursor-pointer flex items-center gap-1">
            + Thêm dịch vụ
          </button>
          <input type="text" v-model="searchDeptServiceQuery" placeholder="Tìm tên dịch vụ..."
            class="border border-slate-200 rounded px-2.5 py-1 text-xs focus:outline-sky-500 font-semibold" />
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3 w-1/3">Tên dịch vụ</th>
              <th class="p-3">Mô tả chi tiết</th>
              <th class="p-3 text-right w-24">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in departmentServices.filter(item => !searchDeptServiceQuery || item.name.toLowerCase().includes(searchDeptServiceQuery.toLowerCase()))"
              :key="s.id" @click="openEditDeptServiceModal(s)"
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
              <td class="p-3 font-bold text-slate-800">{{ s.name }}</td>
              <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed">{{ s.pivot?.description || '-' }}</td>
              <td class="p-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click.stop="deleteDeptService(s.id)"
                    class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5"
                      viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!departmentServices.length">
              <td colspan="3" class="p-6 text-center text-slate-400 italic">Chưa cấu hình dịch vụ bộ phận nào.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT DEPARTMENT SERVICE -->
    <div v-if="isDeptServiceModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditDeptServiceMode ? 'Sửa dịch vụ bộ phận' : 'Thêm dịch vụ bộ phận' }}</h2>
          <button @click="isDeptServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Dịch vụ*</span>
            <select v-model="deptServiceFormState.hotel_service_id" :disabled="isEditDeptServiceMode"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm bg-white">
              <option v-for="service in hotelServices" :key="service.id" :value="service.id">
                {{ service.code }} - {{ service.name }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Mô tả dịch vụ</span>
            <textarea v-model="deptServiceFormState.description" rows="3" placeholder="Chi tiết mô tả..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none font-semibold"></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isDeptServiceModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveDeptService"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu dịch vụ
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
