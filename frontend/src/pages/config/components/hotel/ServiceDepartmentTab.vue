<script setup>
import { computed, onMounted, onBeforeUnmount, ref, reactive } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')

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

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isDeptServiceModalOpen.value) {
    isDeptServiceModalOpen.value = false
  }
}

const openAddDeptServiceModal = () => {
  modalPos.value = { x: 0, y: 0 }
  isEditDeptServiceMode.value = false
  Object.assign(deptServiceFormState, {
    hotel_service_id: hotelServices.value[0]?.id || null,
    description: ''
  })
  isDeptServiceModalOpen.value = true
}

const openEditDeptServiceModal = (service) => {
  modalPos.value = { x: 0, y: 0 }
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

onMounted(() => {
  fetchData()
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})
</script>

<template>
  <div class="flex gap-6 items-stretch relative">
    <!-- Left: Departments -->
    <div class="w-1/4 bg-slate-50 rounded-xl p-3.5 border border-slate-200/80 flex flex-col gap-1.5 shrink-0">
      <span class="text-xs font-semibold text-[#000000D9] uppercase tracking-wider px-2 pb-2 block border-b border-slate-200">
        Bộ Phận
      </span>
      <button v-for="dept in departments" :key="dept.id" @click="activeDepartmentId = dept.id"
        class="w-full text-left px-3 py-2 rounded-lg font-medium text-xs border-none bg-transparent cursor-pointer transition-colors"
        :class="activeDepartmentId === dept.id ? 'bg-sky-50 text-[#006bdb] font-semibold ring-1 ring-inset ring-sky-200' : 'text-[#000000D9] hover:bg-slate-100'">
        {{ dept.code }} - {{ dept.name }}
      </button>
    </div>

    <!-- Right: Services under Department -->
    <div class="flex-1 flex flex-col gap-3">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100 flex-wrap gap-2">
        <span class="text-xs font-semibold text-[#000000D9] uppercase tracking-wider">
          Dịch Vụ Thuộc Bộ Phận: <span class="text-sky-700">{{ activeDepartment?.name || '-' }}</span>
        </span>
        <div class="flex items-center gap-2">
          <button @click="openAddDeptServiceModal" class="btn-pms-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Thêm Dịch Vụ</span>
          </button>
          <div class="relative w-56">
            <input type="text" v-model="searchDeptServiceQuery" placeholder="Tìm tên dịch vụ..."
              class="w-full border border-slate-200 rounded-lg pl-8 pr-7 h-8 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:bg-white" />
            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button v-if="searchDeptServiceQuery" @click="searchDeptServiceQuery = ''"
              class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-xs text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
              <th class="p-2.5 text-center align-middle border border-slate-200 w-1/3 min-w-[180px]">Tên Dịch Vụ</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">Mô Tả Chi Tiết</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in departmentServices.filter(item => !searchDeptServiceQuery || item.name.toLowerCase().includes(searchDeptServiceQuery.toLowerCase()))"
              :key="s.id" @click="openEditDeptServiceModal(s)"
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
              <td class="p-2.5 font-normal text-[#000000D9]">{{ s.name }}</td>
              <td class="p-2.5 font-normal text-[#000000D9] leading-relaxed">{{ s.pivot?.description || '-' }}</td>
              <td class="p-2.5 text-center">
                <div class="flex items-center justify-center">
                  <button @click.stop="deleteDeptService(s.id)"
                    class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                    title="Xóa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditDeptServiceMode ? 'Sửa Dịch Vụ Bộ Phận' : 'Thêm Dịch Vụ Bộ Phận' }}
          </h2>
          <button @mousedown.stop @click="isDeptServiceModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-[#000000D9]">
          <div class="flex flex-col gap-1">
            <label>Dịch Vụ <span class="text-red-500">*</span></label>
            <select v-model="deptServiceFormState.hotel_service_id" :disabled="isEditDeptServiceMode"
              class="w-full border rounded-lg p-2 text-xs font-normal text-[#000000D9] focus:outline-none focus:ring-1 disabled:bg-slate-100 disabled:cursor-not-allowed"
              :class="isEditDeptServiceMode ? 'border-slate-200 bg-slate-50' : 'border-[#F1DD8A] bg-[#FFF8DB] focus:border-[#eab308] focus:ring-[#eab308]'">
              <option v-for="service in hotelServices" :key="service.id" :value="service.id">
                {{ service.code }} - {{ service.name }}
              </option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <label>Mô Tả Dịch Vụ</label>
            <div class="relative">
              <textarea v-model="deptServiceFormState.description" rows="3" placeholder="Chi tiết mô tả..."
                class="w-full border border-slate-200 rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 bg-white resize-none"></textarea>
              <button v-if="deptServiceFormState.description" @click="deptServiceFormState.description = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveDeptService" class="btn-pms-primary">
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
