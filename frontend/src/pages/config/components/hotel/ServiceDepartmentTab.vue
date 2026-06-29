<script setup>
import { ref, reactive } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const departments = ref([
  'Restaurant/Nhà Hàng',
  'Reception/ Lê Tân',
  'House Keeping/Buồng Phòng',
  'Spa'
])
const activeDepartment = ref('Restaurant/Nhà Hàng')
const departmentServices = ref({
  'Restaurant/Nhà Hàng': [
    { id: 1, name: 'Buffet sáng người lớn', description: 'Buffet sáng tiêu chuẩn cho người lớn' },
    { id: 2, name: 'Buffet sáng trẻ em', description: 'Buffet sáng tiêu chuẩn cho trẻ em' },
    { id: 3, name: 'Nước ngọt lon', description: 'Coca, Pepsi, Fanta các loại' },
    { id: 4, name: 'Bia Heineken', description: 'Bia lon Heineken' }
  ],
  'Reception/ Lê Tân': [
    { id: 5, name: 'Đưa đón sân bay', description: 'Xe đưa đón sân bay Cam Ranh 4-7 chỗ' },
    { id: 6, name: 'Giặt ủi nhanh', description: 'Giặt ủi lấy liền trong 4 tiếng' },
    { id: 7, name: 'Thuê xe máy', description: 'Cho thuê xe máy tay ga/xe số theo ngày' }
  ],
  'House Keeping/Buồng Phòng': [
    { id: 8, name: 'Dọn phòng thêm giờ', description: 'Yêu cầu dọn dẹp phòng ngoài giờ định kỳ' },
    { id: 9, name: 'Thêm gối phụ', description: 'Yêu cầu thêm gối nằm phụ' },
    { id: 10, name: 'Thêm chăn/mền phụ', description: 'Yêu cầu thêm chăn mền phụ' }
  ],
  'Spa': [
    { id: 11, name: 'Massage body đá nóng', description: 'Massage toàn thân bằng đá nóng 60 phút' },
    { id: 12, name: 'Xông hơi tinh dầu', description: 'Xông hơi ướt/khô kết hợp tinh dầu sả chanh' }
  ]
})

const isDeptServiceModalOpen = ref(false)
const isEditDeptServiceMode = ref(false)
const deptServiceFormState = reactive({
  id: null,
  name: '',
  description: ''
})
const searchDeptServiceQuery = ref('')

const openAddDeptServiceModal = () => {
  isEditDeptServiceMode.value = false
  Object.assign(deptServiceFormState, {
    id: null,
    name: '',
    description: ''
  })
  isDeptServiceModalOpen.value = true
}

const openEditDeptServiceModal = (service) => {
  isEditDeptServiceMode.value = true
  Object.assign(deptServiceFormState, {
    id: service.id,
    name: service.name,
    description: service.description
  })
  isDeptServiceModalOpen.value = true
}

const saveDeptService = () => {
  if (!deptServiceFormState.name) {
    uiStore.showToast('Vui lòng nhập tên dịch vụ', 'warning')
    return
  }
  const activeList = departmentServices.value[activeDepartment.value] || []
  if (isEditDeptServiceMode.value) {
    const idx = activeList.findIndex(s => s.id === deptServiceFormState.id)
    if (idx !== -1) {
      activeList[idx] = { ...activeList[idx], name: deptServiceFormState.name, description: deptServiceFormState.description }
    }
    uiStore.showToast('Cập nhật dịch vụ bộ phận thành công!', 'success')
  } else {
    const newId = Date.now()
    activeList.push({
      id: newId,
      name: deptServiceFormState.name,
      description: deptServiceFormState.description
    })
    uiStore.showToast('Thêm dịch vụ vào bộ phận thành công!', 'success')
  }
  isDeptServiceModalOpen.value = false
}

const deleteDeptService = async (serviceId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dịch vụ này khỏi bộ phận?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const activeList = departmentServices.value[activeDepartment.value] || []
  const idx = activeList.findIndex(s => s.id === serviceId)
  if (idx !== -1) {
    activeList.splice(idx, 1)
    uiStore.showToast('Xóa dịch vụ thành công!', 'success')
  }
}
</script>

<template>
  <div class="flex gap-6 items-stretch relative">
    <!-- Left: Departments -->
    <div class="w-1/4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex flex-col gap-1.5 shrink-0">
      <span
        class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Bộ
        phận</span>
      <button v-for="dept in departments" :key="dept" @click="activeDepartment = dept"
        class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
        :class="activeDepartment === dept ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
        {{ dept }}
      </button>
    </div>

    <!-- Right: Services under Department -->
    <div class="flex-1 flex flex-col gap-4">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100 flex-wrap gap-2">
        <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
          Dịch vụ thuộc bộ phận: {{ activeDepartment }}
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
              v-for="s in (departmentServices[activeDepartment] || []).filter(item => !searchDeptServiceQuery || item.name.toLowerCase().includes(searchDeptServiceQuery.toLowerCase()))"
              :key="s.id" @click="openEditDeptServiceModal(s)"
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
              <td class="p-3 font-bold text-slate-800">{{ s.name }}</td>
              <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed">{{ s.description || '-' }}</td>
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
            <tr v-if="!(departmentServices[activeDepartment] || []).length">
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
            <span>Tên dịch vụ bộ phận*</span>
            <input type="text" v-model="deptServiceFormState.name" placeholder="Ví dụ: Buffet sáng, Massage..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
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
