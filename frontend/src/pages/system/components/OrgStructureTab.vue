<script setup>
import { ref, computed, onMounted } from 'vue'
import { 
  fetchDepartments, 
  createDepartment, 
  fetchModules, 
  fetchRoles, 
  fetchUsers 
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// State dữ liệu từ Database
const loading = ref(false)
const departmentsList = ref([])
const modulesList = ref([])
const rolesList = ref([])
const usersList = ref([])

// State chọn vị trí và bộ phận hiện tại
const selectedDept = ref(null)
const selectedPosition = ref(null)

// Tab bên phải: 'apps' (Ứng dụng) | 'users' (Người dùng)
const activeRightTab = ref('apps')

// Gán 3 ứng dụng chính (PMS, POS, SYS) theo vai trò
const roleAppAssignments = ref({
  'fo_manager': ['PMS'],
  'fo_staff': ['PMS'],
  'hk_manager': ['PMS'],
  'hk_staff': ['PMS'],
  'super_admin': ['SYS', 'PMS', 'POS'],
  'branch_admin': ['SYS', 'PMS', 'POS'],
  'mgmt': ['SYS', 'PMS'],
  'fb_manager': ['POS'],
  'fb_staff': ['POS'],
})

// Cấu trúc cây dữ liệu phòng ban & vị trí
const treeData = ref([])
const loadError = ref(null)

const loadAllDataFromDB = async () => {
  loading.value = true
  loadError.value = null
  try {
    const results = await Promise.allSettled([
      fetchDepartments(),
      fetchModules(),
      fetchRoles(),
      fetchUsers({ page: 1, per_page: 100 }),
    ])

    const deptRes = results[0].status === 'fulfilled' ? results[0].value : null
    const modRes = results[1].status === 'fulfilled' ? results[1].value : null
    const roleRes = results[2].status === 'fulfilled' ? results[2].value : null
    const userRes = results[3].status === 'fulfilled' ? results[3].value : null

    // Kiểm tra nếu tất cả request đều lỗi (ví dụ rớt mạng hoặc timeout)
    const hasAnySuccess = results.some(r => r.status === 'fulfilled')
    if (!hasAnySuccess && results[0].reason) {
      throw results[0].reason
    }

    departmentsList.value = deptRes?.data?.data || []
    modulesList.value = modRes?.data?.data || []
    rolesList.value = roleRes?.data?.data || []
    usersList.value = userRes?.data?.data || []

    // Xây dựng cây thư mục từ Database: BỘ PHẬN -> VỊ TRÍ
    treeData.value = departmentsList.value.map(dept => {
      let positions = []

      if (dept.code === 'FO') {
        positions = [
          { id: 'fo_mgr', name: 'Trưởng Lễ Tân', code: 'fo_manager', apps: ['PMS'] },
          { id: 'fo_stf', name: 'Nhân Viên Lễ Tân', code: 'fo_staff', apps: ['PMS'] },
        ]
      } else if (dept.code === 'HK') {
        positions = [
          { id: 'hk_mgr', name: 'Trưởng Buồng Phòng', code: 'hk_manager', apps: ['PMS'] },
          { id: 'hk_stf', name: 'Nhân Viên Buồng Phòng', code: 'hk_staff', apps: ['PMS'] },
        ]
      } else if (dept.code === 'SYS') {
        positions = [
          { id: 'sys_sa', name: 'Super Administrator', code: 'super_admin', apps: ['SYS', 'PMS', 'POS'] },
          { id: 'sys_ba', name: 'Quản Trị Chi Nhánh', code: 'branch_admin', apps: ['SYS', 'PMS', 'POS'] },
          { id: 'sys_mg', name: 'Quản Lý (MGMT)', code: 'mgmt', apps: ['SYS', 'PMS'] },
        ]
      } else if (dept.code === 'FB') {
        positions = [
          { id: 'fb_mgr', name: 'Trưởng Nhà Hàng', code: 'fb_manager', apps: ['POS'] },
          { id: 'fb_stf', name: 'Nhân Viên F&B', code: 'fb_staff', apps: ['POS'] },
        ]
      } else {
        positions = [
          { id: `${dept.code}_pos`, name: `Nhân viên ${dept.name}`, code: `${dept.code.toLowerCase()}_staff`, apps: ['PMS'] }
        ]
      }

      return {
        id: dept.id,
        code: dept.code,
        name: dept.name,
        isOpen: true,
        positions,
      }
    })

    // Chọn vị trí đầu tiên mặc định
    if (treeData.value.length > 0) {
      selectedDept.value = treeData.value[0]
      if (treeData.value[0].positions.length > 0) {
        selectedPosition.value = treeData.value[0].positions[0]
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu từ database:', err)
    loadError.value = err.message?.includes('timeout') 
      ? 'Hết thời gian chờ phản hồi từ máy chủ (Timeout). Vui lòng thử lại.' 
      : 'Không thể tải dữ liệu từ máy chủ. Vui lòng thử lại.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadAllDataFromDB()
})

const selectPos = (dept, pos) => {
  selectedDept.value = dept
  selectedPosition.value = pos
}

const toggleDept = (dept) => {
  dept.isOpen = !dept.isOpen
}

// Ứng dụng gán cho vị trí đang chọn (lấy đúng từ 3 app trong DB modules)
const currentApps = computed(() => {
  if (!selectedPosition.value?.apps) return []
  return selectedPosition.value.apps.map(code => {
    const found = modulesList.value.find(m => m.code === code)
    if (found) {
      return { code: found.code, name: found.name, version: 'Version 2.0' }
    }
    return { code, name: `PROVISTA ${code}`, version: 'Version 1.0' }
  })
})

// Người dùng tương ứng từ Database
const currentUsers = computed(() => {
  if (!usersList.value.length || !selectedDept.value) return []
  const deptCode = (selectedDept.value.code || '').toLowerCase()
  const deptName = (selectedDept.value.name || '').toLowerCase()
  const posName = (selectedPosition.value?.name || '').toLowerCase()
  const posCode = (selectedPosition.value?.code || '').toLowerCase()

  return usersList.value.filter(u => {
    const uDeptCode = (u.department_code || '').toLowerCase()
    const uDeptName = (u.department || '').toLowerCase()
    const uJobCode = (u.job_title_code || '').toLowerCase()
    const uJobName = (u.job_title || '').toLowerCase()

    return uDeptCode === deptCode || 
           uDeptName.includes(deptName) || 
           deptName.includes(uDeptName) ||
           uJobCode === posCode ||
           uJobName.includes(posName)
  })
})

// Modals
const showAddAppModal = ref(false)
const selectedAppToAdd = ref('')

const openAddAppModal = () => {
  selectedAppToAdd.value = ''
  showAddAppModal.value = true
}

const confirmAddApp = () => {
  if (!selectedAppToAdd.value) return
  if (!selectedPosition.value.apps.includes(selectedAppToAdd.value)) {
    selectedPosition.value.apps.push(selectedAppToAdd.value)
    if (selectedPosition.value.code) {
      roleAppAssignments.value[selectedPosition.value.code] = [...selectedPosition.value.apps]
    }
    uiStore.showToast('Đã thêm ứng dụng thành công!', 'success')
  }
  showAddAppModal.value = false
}

const removeApp = (appCode) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa ứng dụng ${appCode} khỏi vị trí này?`)) return
  selectedPosition.value.apps = selectedPosition.value.apps.filter(c => c !== appCode)
  if (selectedPosition.value.code) {
    roleAppAssignments.value[selectedPosition.value.code] = [...selectedPosition.value.apps]
  }
  uiStore.showToast('Đã xóa ứng dụng', 'success')
}

// Modal Thêm Bộ Phận / Vị Trí mới
const showAddDeptModal = ref(false)
const newDeptCode = ref('')
const newDeptName = ref('')
const newPosName = ref('')
const selectedParentDeptId = ref(null)
const isSubmittingDept = ref(false)

const openAddDeptModal = () => {
  newDeptCode.value = ''
  newDeptName.value = ''
  newPosName.value = ''
  selectedParentDeptId.value = selectedDept.value?.id || departmentsList.value[0]?.id
  showAddDeptModal.value = true
}

const saveNewDeptOrPos = async () => {
  if (newDeptName.value.trim()) {
    isSubmittingDept.value = true
    try {
      const code = (newDeptCode.value.trim() || newDeptName.value.substring(0, 3)).toUpperCase()
      await createDepartment({
        code,
        name: newDeptName.value.trim(),
      })
      uiStore.showToast('Đã tạo phòng ban mới vào Database!', 'success')
      await loadAllDataFromDB()
      showAddDeptModal.value = false
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Lỗi khi tạo phòng ban', 'error')
    } finally {
      isSubmittingDept.value = false
    }
  } else if (newPosName.value.trim() && selectedParentDeptId.value) {
    const targetDept = treeData.value.find(d => d.id === selectedParentDeptId.value)
    if (targetDept) {
      const newPos = {
        id: Date.now(),
        name: newPosName.value.trim(),
        code: `pos_${Date.now()}`,
        apps: ['PMS'],
      }
      targetDept.positions.push(newPos)
      selectedPosition.value = newPos
      uiStore.showToast('Đã thêm vị trí mới!', 'success')
    }
    showAddDeptModal.value = false
  }
}
</script>

<template>
  <div class="flex-1 flex overflow-hidden bg-white select-none text-xs">
    
    <!-- ==================== CỘT TRÁI: CƠ CẤU TỔ CHỨC ==================== -->
    <div class="w-64 border-r border-slate-200 flex flex-col shrink-0 bg-[#f8fafc]">
      <!-- Header Sidebar Trái -->
      <div class="flex items-center justify-between px-3 py-2 bg-[#e0f2fe] border-b border-slate-200">
        <div class="flex items-center gap-1.5 font-bold text-slate-800 text-xs">
          <svg class="w-3.5 h-3.5 text-sky-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
          </svg>
          <span>Cơ cấu tổ chức</span>
        </div>
        <button 
          @click="openAddDeptModal"
          class="w-4.5 h-4.5 rounded-full border border-sky-400 text-sky-600 hover:bg-sky-100 flex items-center justify-center font-black text-xs cursor-pointer transition-colors bg-white shadow-2xs"
          title="Thêm bộ phận / vị trí mới"
        >
          +
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center h-48">
        <div class="w-5 h-5 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Error State with Retry Button -->
      <div v-else-if="loadError" class="p-4 flex flex-col items-center justify-center text-center gap-2 text-slate-600 h-48">
        <span class="text-amber-500 text-lg">⚠️</span>
        <p class="text-[11px] text-slate-500 leading-tight">{{ loadError }}</p>
        <button 
          @click="loadAllDataFromDB" 
          class="mt-1 px-3 py-1 bg-sky-500 hover:bg-sky-600 text-white rounded text-[11px] font-bold cursor-pointer transition-colors flex items-center gap-1"
        >
          <span>🔄 Thử lại</span>
        </button>
      </div>

      <!-- Danh sách Phòng Ban & Vị Trí (Tree View) -->
      <div v-else class="flex-1 overflow-y-auto py-1">
        <div v-for="dept in treeData" :key="dept.id" class="flex flex-col">
          <!-- Item Bộ Phận -->
          <div 
            @click="toggleDept(dept)"
            class="flex items-center gap-1.5 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200/60 cursor-pointer font-bold transition-colors select-none text-[11px]"
          >
            <!-- Biểu tượng Thu nhỏ / Mở rộng -->
            <button class="w-3.5 h-3.5 flex items-center justify-center bg-[#93c5fd] hover:bg-sky-400 text-white rounded-xs border-none cursor-pointer p-0 shrink-0">
              <span class="text-[10px] leading-none">{{ dept.isOpen ? '−' : '+' }}</span>
            </button>
            <span class="truncate">{{ dept.name }}</span>
          </div>

          <!-- Danh sách Vị Trí trong Bộ Phận -->
          <div v-show="dept.isOpen" class="flex flex-col ml-5 pl-1 border-l border-slate-200">
            <div 
              v-for="pos in dept.positions" 
              :key="pos.id"
              @click="selectPos(dept, pos)"
              :class="[
                'px-3 py-1.5 cursor-pointer font-medium text-[11.5px] transition-colors rounded-xs select-none',
                selectedPosition?.id === pos.id 
                  ? 'bg-[#7dd3fc] text-white font-bold shadow-2xs' 
                  : 'text-slate-600 hover:bg-slate-100'
              ]"
            >
              {{ pos.name }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== CỘT PHẢI: CHI TIẾT ỨNG DỤNG / NGƯỜI DÙNG ==================== -->
    <div class="flex-1 flex flex-col overflow-hidden bg-white">
      
      <!-- Top Navigation Tabs (Ứng dụng | Người dùng) -->
      <div class="flex items-center px-4 pt-1 bg-[#e0f2fe] border-b border-slate-200 shrink-0 gap-1">
        <button 
          @click="activeRightTab = 'apps'"
          :class="[
            'px-4 py-1.5 border-none font-bold text-xs cursor-pointer rounded-t-sm transition-all shadow-2xs',
            activeRightTab === 'apps'
              ? 'bg-white text-slate-800 border-t-2 border-sky-500'
              : 'bg-transparent text-slate-600 hover:text-slate-900 hover:bg-sky-100/50'
          ]"
        >
          Ứng dụng
        </button>
        <button 
          @click="activeRightTab = 'users'"
          :class="[
            'px-4 py-1.5 border-none font-bold text-xs cursor-pointer rounded-t-sm transition-all shadow-2xs',
            activeRightTab === 'users'
              ? 'bg-white text-slate-800 border-t-2 border-sky-500'
              : 'bg-transparent text-slate-600 hover:text-slate-900 hover:bg-sky-100/50'
          ]"
        >
          Người dùng ({{ currentUsers.length }})
        </button>
      </div>

      <!-- Action Button Toolbar -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
        <div class="flex items-center gap-2">
          <button 
            v-if="activeRightTab === 'apps'"
            @click="openAddAppModal"
            class="px-3 py-1.5 bg-[#7dd3fc] hover:bg-[#38bdf8] text-white border-none rounded-md font-bold text-xs cursor-pointer shadow-xs transition-colors"
          >
            Thêm ứng dụng
          </button>
          <div class="text-xs text-slate-500 font-semibold ml-2">
            Đang chọn: <span class="text-slate-800 font-bold">{{ selectedDept?.name }}</span> ➔ <span class="text-sky-600 font-black">{{ selectedPosition?.name }}</span>
          </div>
        </div>
      </div>

      <!-- Content Area -->
      <div class="flex-1 overflow-y-auto p-6 bg-white">
        
        <!-- ==================== TAB 1: DANH SÁCH ỨNG DỤNG ==================== -->
        <div v-if="activeRightTab === 'apps'">
          <div v-if="!currentApps.length" class="flex flex-col items-center justify-center h-48 text-slate-400 gap-2">
            <svg class="w-10 h-10 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span class="text-xs">Chưa có ứng dụng nào được gán cho vị trí này.</span>
          </div>

          <!-- Lưới các thẻ ứng dụng (3 ứng dụng chính PROVISTA PMS, F&B, SYSTEM) -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <div 
              v-for="app in currentApps" 
              :key="app.code"
              class="flex items-center gap-3.5 p-3 rounded-lg border border-slate-100 hover:border-sky-200 hover:bg-sky-50/40 transition-all shadow-2xs"
            >
              <!-- Icon Kim Cương Logo Provista -->
              <div class="w-11 h-11 flex flex-col items-center justify-center shrink-0">
                <div class="w-7 h-7 bg-[#0ea5e9] rounded-sm rotate-45 flex items-center justify-center shadow-xs">
                  <div class="w-4 h-4 bg-white -rotate-45 flex items-center justify-center">
                    <span class="text-[8px] font-black text-[#0ea5e9]">{{ app.code }}</span>
                  </div>
                </div>
                <span class="text-[9px] font-black text-[#0ea5e9] mt-0.5 tracking-tighter">{{ app.code }}</span>
              </div>

              <!-- Chi tiết Ứng dụng -->
              <div class="flex-1 min-w-0">
                <div class="font-black text-slate-800 text-xs tracking-tight uppercase truncate">
                  {{ app.name }}
                </div>
                <div class="text-[10.5px] text-slate-400 font-medium mt-0.5">
                  {{ app.version }}
                </div>
                <!-- Links Hành Động -->
                <div class="flex items-center gap-2.5 mt-1.5 text-[11px] font-bold">
                  <button 
                    @click="removeApp(app.code)" 
                    class="text-red-500 hover:text-red-700 bg-transparent border-none p-0 cursor-pointer transition-colors"
                  >
                    Xóa
                  </button>
                  <button 
                    @click="uiStore.showToast('Thông tin ứng dụng hệ thống chuẩn', 'info')" 
                    class="text-sky-600 hover:text-sky-800 bg-transparent border-none p-0 cursor-pointer transition-colors"
                  >
                    Sửa
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== TAB 2: DANH SÁCH NGƯỜI DÙNG ==================== -->
        <div v-else-if="activeRightTab === 'users'">
          <div v-if="!currentUsers.length" class="flex flex-col items-center justify-center h-48 text-slate-400 gap-2">
            <svg class="w-10 h-10 stroke-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span class="text-xs">Chưa có nhân viên nào thuộc vị trí này trong Database.</span>
          </div>

          <div v-else class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-600">
                  <th class="py-2 px-3">MÃ NV</th>
                  <th class="py-2 px-3">HỌ VÀ TÊN</th>
                  <th class="py-2 px-3">EMAIL</th>
                  <th class="py-2 px-3">BỘ PHẬN</th>
                  <th class="py-2 px-3">VỊ TRÍ</th>
                  <th class="py-2 px-3 text-center">TRẠNG THÁI</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="user in currentUsers" :key="user.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-sky-600">{{ user.employee_code || user.id }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ user.name }}</td>
                  <td class="py-2 px-3 text-slate-500">{{ user.email || '—' }}</td>
                  <td class="py-2 px-3 text-slate-600">{{ user.department || selectedDept?.name }}</td>
                  <td class="py-2 px-3 text-slate-600">{{ user.job_title || selectedPosition?.name }}</td>
                  <td class="py-2 px-3 text-center">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold', user.is_active_user ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500']">
                      {{ user.is_active_user ? 'Hoạt động' : 'Tạm khóa' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <!-- ==================== MODAL: THÊM ỨNG DỤNG ==================== -->
    <Teleport to="body">
      <div v-if="showAddAppModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden">
          <div class="px-4 py-3 bg-[#e0f2fe] border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800">Thêm ứng dụng cho vị trí</h3>
            <button @click="showAddAppModal = false" class="text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer font-bold">✕</button>
          </div>
          <div class="p-4 flex flex-col gap-3">
            <label class="text-[11px] font-bold text-slate-600">Chọn ứng dụng (Database):</label>
            <select v-model="selectedAppToAdd" class="border border-slate-300 rounded-md p-2 text-xs focus:outline-sky-500 bg-white">
              <option value="">-- Chọn ứng dụng cần gán --</option>
              <option v-for="m in modulesList" :key="m.code" :value="m.code" :disabled="selectedPosition?.apps?.includes(m.code)">
                {{ m.name }} ({{ m.code }}) {{ selectedPosition?.apps?.includes(m.code) ? '— Đã gán' : '' }}
              </option>
            </select>
          </div>
          <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showAddAppModal = false" class="px-3 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer">
              Hủy
            </button>
            <button @click="confirmAddApp" :disabled="!selectedAppToAdd" class="px-3 py-1 bg-[#7dd3fc] hover:bg-[#38bdf8] disabled:opacity-50 text-white rounded-md font-bold text-xs border-none cursor-pointer">
              Thêm
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ==================== MODAL: THÊM BỘ PHẬN / VỊ TRÍ ==================== -->
    <Teleport to="body">
      <div v-if="showAddDeptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
          <div class="px-4 py-3 bg-[#e0f2fe] border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800">Thêm Bộ Phận / Vị Trí Mới</h3>
            <button @click="showAddDeptModal = false" class="text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer font-bold">✕</button>
          </div>
          <div class="p-4 flex flex-col gap-3">
            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-700">Tạo Bộ Phận Mới:</label>
              <div class="grid grid-cols-3 gap-2">
                <input v-model="newDeptCode" type="text" placeholder="Mã (ví dụ: MKT)..." class="border border-slate-200 rounded-md p-2 text-xs focus:outline-sky-500 uppercase" maxlength="10" />
                <input v-model="newDeptName" type="text" placeholder="Tên bộ phận..." class="col-span-2 border border-slate-200 rounded-md p-2 text-xs focus:outline-sky-500" />
              </div>
            </div>
            
            <div class="text-center text-[10px] text-slate-400 font-bold uppercase">— HOẶC THÊM VỊ TRÍ VÀO BỘ PHẬN —</div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-700">Chọn Bộ Phận Cha:</label>
              <select v-model="selectedParentDeptId" class="border border-slate-200 rounded-md p-2 text-xs focus:outline-sky-500 bg-white">
                <option v-for="d in treeData" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="text-[11px] font-bold text-slate-700">Tên Vị Trí Công Việc:</label>
              <input v-model="newPosName" type="text" placeholder="Nhập tên vị trí..." class="border border-slate-200 rounded-md p-2 text-xs focus:outline-sky-500" />
            </div>
          </div>
          <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button @click="showAddDeptModal = false" class="px-3 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer">
              Hủy
            </button>
            <button @click="saveNewDeptOrPos" :disabled="isSubmittingDept" class="px-3 py-1 bg-[#7dd3fc] hover:bg-[#38bdf8] text-white rounded-md font-bold text-xs border-none cursor-pointer">
              {{ isSubmittingDept ? 'Đang lưu...' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>
