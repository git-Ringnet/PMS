<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  fetchOrganization,
  fetchModules,
  fetchRoles,
  fetchSystemBranchesList,
  createOrganizationDepartment,
  updateOrganizationDepartment,
  deleteOrganizationDepartment,
  createPosition,
  updatePosition,
  deletePosition,
  syncPositionBranches,
  fetchBranchRoleMatrix,
  syncBranchRoleMatrix,
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'
import { applicationChoices } from '@/utils/position-applications.js'

const uiStore = useUiStore()
const loading = ref(false)
const departments = ref([])
const applications = ref([])
const branches = ref([])
const roles = ref([])
const selectedDepartment = ref(null)
const selectedPosition = ref(null)
const activeRightTab = ref('apps')
const collapsedDepts = ref(new Set()) // Set<department.id>

const toggleDept = (id) => {
  if (collapsedDepts.value.has(id)) {
    collapsedDepts.value.delete(id)
  } else {
    collapsedDepts.value.add(id)
  }
  // trigger reactivity
  collapsedDepts.value = new Set(collapsedDepts.value)
}

const showDepartmentModal = ref(false)
const departmentForm = ref({ id: null, code: '', name: '' })
const showPositionModal = ref(false)
const positionForm = ref({ id: null, department_id: null, code: '', name: '' })

const showApplicationModal = ref(false)
const selectedApplicationCode = ref('PMS')
const applicationModalMode = ref('add') // 'add' | 'edit'
const branchAssignments = ref([])

const showPermissionModal = ref(false)
const permissionLoading = ref(false)
const permissionContext = ref(null)
const permissionGroups = ref({})

const loadData = async () => {
  loading.value = true
  try {
    const [organizationRes, moduleRes, branchRes, roleRes] = await Promise.all([
      fetchOrganization(),
      fetchModules(),
      fetchSystemBranchesList(),
      fetchRoles(),
    ])
    departments.value = organizationRes.data.data || []
    applications.value = moduleRes.data.data || []
    branches.value = branchRes.data.data || []
    roles.value = roleRes.data.data || []

    const selectedId = selectedPosition.value?.id
    const restored = departments.value
      .flatMap(department => department.positions || [])
      .find(position => position.id === selectedId)
    selectedPosition.value = restored || departments.value[0]?.positions?.[0] || null
    selectedDepartment.value = departments.value.find(department =>
      department.positions?.some(position => position.id === selectedPosition.value?.id)
    ) || departments.value[0] || null
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải cơ cấu tổ chức', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

const selectPosition = (department, position) => {
  selectedDepartment.value = department
  selectedPosition.value = position
}

const applicationCards = computed(() => {
  if (!selectedPosition.value) return []
  const codes = [...new Set((selectedPosition.value.branch_roles || []).map(item => item.application_code))]
  return codes.map(code => {
    const application = applications.value.find(item => item.code === code)
    const assignments = selectedPosition.value.branch_roles.filter(item => item.application_code === code)
    return {
      code,
      name: application?.name || `PROVISTA ${code}`,
      portal_key: application?.portal_key || code.toLowerCase(),
      branchCount: assignments.length,
      assignments,
    }
  })
})

const currentUsers = computed(() => {
  if (!selectedPosition.value) return []
  const uniqueUsers = new Map()
  for (const assignment of selectedPosition.value.user_assignments || []) {
    if (assignment.user) uniqueUsers.set(assignment.user.id, assignment.user)
  }
  return [...uniqueUsers.values()]
})

const openCreateDepartment = () => {
  departmentForm.value = { id: null, code: '', name: '' }
  showDepartmentModal.value = true
}

// Dòng 469: sửa bộ phận chỉ đổi tên, mã bộ phận giữ nguyên.
const openEditDepartment = (department) => {
  departmentForm.value = { id: department.id, code: department.code, name: department.name }
  showDepartmentModal.value = true
}

// Dòng 469: chỉ xóa được bộ phận chưa có vị trí công việc (backend kiểm tra và trả thông báo).
const removeDepartment = async (department) => {
  if ((department.positions || []).length) {
    uiStore.showToast('Bộ phận đã có vị trí công việc, không thể xóa.', 'warning')
    return
  }
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Xóa bộ phận "${department.name}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await deleteOrganizationDepartment(department.id)
    await loadData()
    uiStore.showToast('Đã xóa bộ phận', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa bộ phận', 'error')
  }
}

const saveDepartment = async () => {
  if (!String(departmentForm.value.code || '').trim() || !String(departmentForm.value.name || '').trim()) {
    uiStore.showToast('Vui lòng nhập Mã bộ phận và Tên bộ phận.', 'warning')
    return
  }
  try {
    if (departmentForm.value.id) {
      await updateOrganizationDepartment(departmentForm.value.id, { name: departmentForm.value.name })
    } else {
      await createOrganizationDepartment({
        code: departmentForm.value.code.toUpperCase(),
        name: departmentForm.value.name,
      })
    }
    showDepartmentModal.value = false
    await loadData()
    uiStore.showToast(departmentForm.value.id ? 'Đã lưu bộ phận' : 'Đã tạo bộ phận', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu bộ phận', 'error')
  }
}

const openPosition = (department, position = null) => {
  positionForm.value = {
    id: position?.id || null,
    department_id: department.id,
    code: position?.code || '',
    name: position?.name || '',
  }
  showPositionModal.value = true
}

const savePosition = async () => {
  if (!String(positionForm.value.code || '').trim() || !String(positionForm.value.name || '').trim()) {
    uiStore.showToast('Vui lòng nhập Mã vị trí và Tên vị trí công việc.', 'warning')
    return
  }
  try {
    // Dòng 469: sửa vị trí chỉ đổi tên, không gửi mã.
    if (positionForm.value.id) {
      await updatePosition(positionForm.value.id, { name: positionForm.value.name })
    } else {
      await createPosition({
        organization_department_id: positionForm.value.department_id,
        code: positionForm.value.code.toUpperCase(),
        name: positionForm.value.name,
      })
    }
    showPositionModal.value = false
    await loadData()
    uiStore.showToast('Đã lưu vị trí công việc', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu vị trí', 'error')
  }
}

const removePosition = async () => {
  if (!selectedPosition.value) return
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Xóa vị trí "${selectedPosition.value.name}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await deletePosition(selectedPosition.value.id)
    selectedPosition.value = null
    await loadData()
    uiStore.showToast('Đã xóa vị trí', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa vị trí', 'error')
  }
}

const loadApplicationAssignments = applicationCode => {
  selectedApplicationCode.value = applicationCode
  branchAssignments.value = branches.value.map(branch => {
    const current = selectedPosition.value?.branch_roles?.find(item =>
      item.application_code === applicationCode && item.system_branch_id === branch.id
    )
    return {
      branch,
      enabled: !!current,
      role_id: current?.role_id || '',
    }
  })
}

// Checklist TC_HT_03 dòng 18 / task 471: Thêm ứng dụng chọn trong danh sách ứng dụng vị trí chưa có (1 vị trí nhiều ứng dụng);
// Sửa chỉ sửa đúng ứng dụng của thẻ đang bấm.
const applicationOptions = computed(() =>
  applicationChoices(applications.value, selectedPosition.value, applicationModalMode.value, selectedApplicationCode.value)
)

const openAddApplication = () => {
  const choices = applicationChoices(applications.value, selectedPosition.value, 'add')
  if (!choices.length) {
    uiStore.showToast('Vị trí đã được gán tất cả ứng dụng, bấm Sửa trên từng ứng dụng để điều chỉnh.', 'warning')
    return
  }
  applicationModalMode.value = 'add'
  loadApplicationAssignments(choices[0].code)
  showApplicationModal.value = true
}

const openApplication = (applicationCode) => {
  applicationModalMode.value = 'edit'
  loadApplicationAssignments(applicationCode)
  showApplicationModal.value = true
}

const saveApplication = async () => {
  const invalid = branchAssignments.value.some(item => item.enabled && !item.role_id)
  if (invalid) {
    uiStore.showToast('Vui lòng chọn Role cho tất cả chi nhánh đã tích', 'warning')
    return
  }
  try {
    await syncPositionBranches(selectedPosition.value.id, {
      application_code: selectedApplicationCode.value,
      assignments: branchAssignments.value
        .filter(item => item.enabled)
        .map(item => ({ system_branch_id: item.branch.id, role_id: Number(item.role_id) })),
    })
    showApplicationModal.value = false
    await loadData()
    uiStore.showToast('Đã cập nhật ứng dụng cho vị trí', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu ứng dụng', 'error')
  }
}

const removeApplication = async (applicationCode) => {
  const confirmed = await uiStore.confirm({
    title: 'Gỡ ứng dụng',
    message: `Gỡ ứng dụng ${applicationCode} khỏi vị trí này?`,
    confirmText: 'Gỡ',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  await syncPositionBranches(selectedPosition.value.id, {
    application_code: applicationCode,
    assignments: [],
  })
  await loadData()
}

const openPermission = async (assignment) => {
  permissionLoading.value = true
  showPermissionModal.value = true
  permissionContext.value = assignment
  try {
    const response = await fetchBranchRoleMatrix(assignment.role_id, {
      system_branch_id: assignment.system_branch_id,
      application_code: assignment.application_code,
    })
    permissionGroups.value = response.data.data.permissions || {}
  } catch (error) {
    uiStore.showToast('Không thể tải ma trận quyền', 'error')
  } finally {
    permissionLoading.value = false
  }
}

const screensForModule = permissions => {
  const map = {}
  for (const permission of permissions || []) {
    const key = permission.screen_code || permission.code
    if (!map[key]) {
      map[key] = {
        code: key,
        name: permission.screen_name || permission.name,
        path: permission.path,
        actions: {},
      }
    }
    map[key].actions[permission.action || 'view'] = permission
  }
  return Object.values(map)
}

const togglePermission = (screen, action, checked) => {
  const permission = screen.actions[action]
  if (!permission) return
  permission.granted = checked
  if (checked && action !== 'view' && screen.actions.view) screen.actions.view.granted = true
  if (!checked && action === 'view') {
    for (const item of Object.values(screen.actions)) item.granted = false
  }
}

const savePermissionMatrix = async () => {
  const permissionIds = Object.values(permissionGroups.value)
    .flat()
    .filter(permission => permission.granted)
    .map(permission => permission.id)
  try {
    await syncBranchRoleMatrix(permissionContext.value.role_id, {
      system_branch_id: permissionContext.value.system_branch_id,
      application_code: permissionContext.value.application_code,
      permission_ids: permissionIds,
    })
    showPermissionModal.value = false
    uiStore.showToast('Đã lưu ma trận phân quyền', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu phân quyền', 'error')
  }
}
</script>

<template>
  <div class="flex h-full min-h-0 bg-white text-xs select-none">
    <!-- Left column: Cây cơ cấu tổ chức (image1.png) -->
    <aside class="w-[300px] shrink-0 border-r border-slate-200 flex flex-col bg-white">
      <!-- Header bar -->
      <div class="h-10 px-3 flex items-center justify-between bg-slate-50/90 border-b border-slate-200">
        <span class="font-bold text-slate-800 text-xs">Cơ cấu tổ chức</span>
        <button
          class="w-5 h-5 rounded-full bg-[#72c6e6] hover:bg-[#5db3d4] text-white font-bold text-xs flex items-center justify-center border-none cursor-pointer transition-colors shadow-2xs"
          title="Thêm bộ phận mới"
          @click="openCreateDepartment"
        >
          +
        </button>
      </div>

      <!-- Department & Position Tree -->
      <div v-if="loading" class="p-6 text-center text-slate-400">Đang tải cơ cấu tổ chức...</div>
      <div v-else class="flex-1 overflow-y-auto py-1">
        <section v-for="department in departments" :key="department.id" class="mb-0.5">
          <!-- Department row -->
          <div class="group flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 transition-colors cursor-pointer" @click="toggleDept(department.id)">
            <span
              class="w-3.5 h-3.5 rounded-xs bg-[#72c6e6] text-white flex items-center justify-center font-black text-[10px] shrink-0 select-none leading-none transition-colors hover:bg-[#5db3d4]"
              :title="collapsedDepts.has(department.id) ? 'Mở rộng' : 'Thu gọn'"
            >
              {{ collapsedDepts.has(department.id) ? '+' : '-' }}
            </span>
            <span class="font-bold text-slate-800 uppercase text-[11.5px] tracking-wide flex-1 truncate" :title="department.name">
              {{ department.name }}
            </span>
            <button
              class="opacity-0 group-hover:opacity-100 px-1 rounded text-[#0ea5e9] hover:bg-sky-100 text-[11px] font-semibold border-none bg-transparent cursor-pointer transition-opacity"
              title="Sửa bộ phận (chỉ sửa tên)"
              data-testid="edit-department"
              @click.stop="openEditDepartment(department)"
            >
              Sửa
            </button>
            <button
              class="opacity-0 group-hover:opacity-100 px-1 rounded text-rose-500 hover:bg-rose-50 text-[11px] font-semibold border-none bg-transparent cursor-pointer transition-opacity"
              title="Xóa bộ phận (chỉ xóa khi chưa có vị trí công việc)"
              data-testid="delete-department"
              @click.stop="removeDepartment(department)"
            >
              Xóa
            </button>
            <button
              class="opacity-0 group-hover:opacity-100 w-4 h-4 rounded text-[#0ea5e9] hover:bg-sky-100 flex items-center justify-center font-bold text-xs border-none bg-transparent cursor-pointer transition-opacity"
              title="Thêm vị trí vào bộ phận này"
              @click.stop="openPosition(department)"
            >
              +
            </button>
          </div>

          <!-- Position rows — ẩn khi bộ phận đang đóng -->
          <template v-if="!collapsedDepts.has(department.id)">
            <button
              v-for="position in department.positions"
              :key="position.id"
              class="w-full text-left pl-8 pr-3 py-1.5 transition-colors cursor-pointer border-none text-xs block truncate"
              :class="selectedPosition?.id === position.id
                ? 'bg-[#72c6e6] text-white font-bold shadow-2xs'
                : 'bg-transparent text-slate-700 hover:bg-slate-100/70 font-normal'"
              @click="selectPosition(department, position)"
            >
              {{ position.name }}
            </button>
          </template>
        </section>
      </div>
    </aside>

    <!-- Right main area: Tabs Ứng dụng & Người dùng (image1.png) -->
    <main class="flex-1 min-w-0 flex flex-col bg-white">
      <!-- Tab Header -->
      <div class="h-10 px-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
        <div class="flex items-center gap-1 h-full">
          <button
            class="h-full px-4 text-xs font-bold border-none cursor-pointer transition-colors flex items-center"
            :class="activeRightTab === 'apps'
              ? 'bg-white text-[#0ea5e9] border-t-2 border-t-[#0ea5e9] border-x border-slate-200 font-extrabold -mb-[1px]'
              : 'bg-transparent text-slate-600 hover:text-slate-900'"
            @click="activeRightTab = 'apps'"
          >
            Ứng dụng
          </button>
          <button
            class="h-full px-4 text-xs font-bold border-none cursor-pointer transition-colors flex items-center"
            :class="activeRightTab === 'users'
              ? 'bg-white text-[#0ea5e9] border-t-2 border-t-[#0ea5e9] border-x border-slate-200 font-extrabold -mb-[1px]'
              : 'bg-transparent text-slate-600 hover:text-slate-900'"
            @click="activeRightTab = 'users'"
          >
            Người dùng
          </button>
        </div>

        <div v-if="selectedPosition" class="flex items-center gap-3">
          <button
            class="text-[#0ea5e9] hover:text-[#0284c7] font-semibold text-xs border-none bg-transparent cursor-pointer"
            @click="openPosition(selectedDepartment, selectedPosition)"
          >
            Sửa vị trí
          </button>
          <button
            class="text-rose-500 hover:text-rose-700 font-semibold text-xs border-none bg-transparent cursor-pointer"
            @click="removePosition"
          >
            Xóa vị trí
          </button>
        </div>
      </div>

      <!-- Tab Content: Applications (image1.png) -->
      <div v-if="!selectedPosition" class="flex-1 grid place-items-center text-slate-400">
        Chọn một vị trí công việc từ cây bên trái để xem cấu hình
      </div>

      <div v-else-if="activeRightTab === 'apps'" class="flex-1 overflow-y-auto p-5">
        <!-- Button Thêm ứng dụng (matching image1.png: sky-blue rounded button) -->
        <button
          class="px-4 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-2xs transition-colors mb-5 inline-flex items-center gap-1.5"
          data-testid="position-add-application"
          @click="openAddApplication"
        >
          Thêm ứng dụng
        </button>

        <!-- Grid Cards (matching image1.png) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <article
            v-for="application in applicationCards"
            :key="application.code"
            class="border border-slate-200/80 rounded-lg p-4 flex items-center gap-4 bg-white shadow-2xs hover:shadow-xs transition-all"
          >
            <!-- Logo Icon: Rhombus diamond matching image1.png -->
            <div class="flex flex-col items-center justify-center shrink-0 w-12">
              <div class="w-8 h-8 bg-[#0ea5e9] rounded-sm rotate-45 flex items-center justify-center shadow-2xs">
                <div class="w-4 h-4 bg-white -rotate-45 rounded-2xs"></div>
              </div>
              <span class="text-[10px] font-black text-[#0ea5e9] tracking-wider mt-1 uppercase">
                {{ application.code }}
              </span>
            </div>

            <!-- Details -->
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-slate-800 text-sm truncate">{{ application.name }}</h3>
              <p class="text-slate-400 text-xs font-medium mt-0.5">Version</p>
              <p class="text-[11px] text-slate-500 mt-0.5">{{ application.branchCount }} chi nhánh được cấu hình</p>
              <div class="mt-2.5 flex items-center gap-3">
                <button
                  class="text-rose-500 hover:text-rose-700 font-bold text-xs border-none bg-transparent cursor-pointer p-0"
                  @click="removeApplication(application.code)"
                >
                  Xóa
                </button>
                <button
                  class="text-[#0ea5e9] hover:text-[#0284c7] font-bold text-xs border-none bg-transparent cursor-pointer p-0"
                  @click="openApplication(application.code)"
                >
                  Sửa
                </button>
              </div>
            </div>
          </article>
        </div>
      </div>

      <!-- Tab Content: Users -->
      <div v-else class="flex-1 overflow-y-auto p-5">
        <div class="border border-slate-200 rounded-lg overflow-hidden shadow-2xs">
          <table class="w-full border-collapse text-xs">
            <thead>
              <tr class="bg-slate-100/90 text-left border-b border-slate-200 text-slate-700 font-bold">
                <th class="p-2.5 w-28 border-r border-slate-200 text-center align-middle border">Mã NV</th>
                <th class="p-2.5 border-r border-slate-200 text-center align-middle border">Tên Nhân Viên</th>
                <th class="p-2.5 text-center align-middle border-r border-slate-200 border">Email</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="user in currentUsers" :key="user.id" class="hover:bg-slate-50 transition-colors">
                <td class="p-2.5 font-medium text-slate-600 border-slate-100">{{ user.employee_code || '-' }}</td>
                <td class="p-2.5 font-bold text-slate-800 border-slate-100">{{ user.name }}</td>
                <td class="p-2.5 text-slate-600 border-slate-200">{{ user.email }}</td>
              </tr>
              <tr v-if="!currentUsers.length">
                <td colspan="3" class="p-8 text-center text-slate-400 font-medium border-slate-200">Chưa có nhân viên ở vị trí này</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>

    <Teleport to="body">
      <!-- Modal: Sửa ứng dụng (Matching image2.png) -->
      <div v-if="showApplicationModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-full max-w-4xl rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <!-- Sky-blue banner header matching image2.png -->
          <header class="px-5 py-3 bg-[#72c6e6] text-white flex items-center justify-between">
            <h3 class="text-sm font-bold tracking-wide text-white m-0">{{ applicationModalMode === 'add' ? 'Thêm ứng dụng' : 'Sửa ứng dụng' }}</h3>
            <button
              @click="showApplicationModal = false"
              class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none"
            >
              ✕
            </button>
          </header>

          <div class="p-5 space-y-4">
            <!-- Ứng dụng select -->
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1.5">Ứng dụng</label>
              <select
                v-model="selectedApplicationCode"
                data-testid="position-application-select"
                :disabled="applicationModalMode === 'edit'"
                @change="loadApplicationAssignments(selectedApplicationCode)"
                class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold text-slate-800 bg-white focus:outline-sky-500"
              >
                <option v-for="application in applicationOptions" :key="application.code" :value="application.code">
                  {{ application.name }}
                </option>
              </select>
            </div>

            <!-- Table Chi nhánh, Vị Trí Công Việc, Phân Quyền (image2.png) -->
            <div class="border border-slate-200 rounded-md overflow-hidden">
              <table class="w-full border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-100/90 text-slate-700 font-bold border-b border-slate-200">
                    <th class="p-2.5 w-12 text-center border-r border-slate-200 align-middle border"></th>
                    <th class="text-center p-2.5 border-r border-slate-200 align-middle border">Chi Nhánh</th>
                    <th class="text-center p-2.5 border-r border-slate-200 align-middle border">Vị Trí Công Việc</th>
                    <th class="p-2.5 text-center w-36 align-middle border-r border-slate-200 border">Phân Quyền</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="row in branchAssignments"
                    :key="row.branch.id"
                    class="transition-colors"
                    :class="row.enabled ? 'bg-sky-50/40 hover:bg-sky-50/60' : 'hover:bg-slate-50'"
                  >
                    <td class="p-2.5 text-center border-slate-100">
                      <input
                        v-model="row.enabled"
                        type="checkbox"
                        class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer"
                      />
                    </td>
                    <td class="p-2.5 font-semibold text-slate-800 border-slate-100">
                      {{ row.branch.name }}
                    </td>
                    <td class="p-2.5 border-slate-100">
                      <select
                        v-model="row.role_id"
                        :disabled="!row.enabled"
                        class="w-full border border-slate-300 rounded-md p-1.5 text-xs font-semibold focus:outline-sky-500 disabled:bg-slate-100 disabled:text-slate-400"
                      >
                        <option value="">Vị Trí Công Việc (Role)</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                      </select>
                    </td>
                    <td class="p-2.5 text-center border-slate-200">
                      <button
                        v-if="row.enabled && row.role_id"
                        class="px-4 py-1 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md text-xs font-bold border-none cursor-pointer transition-colors shadow-2xs"
                        @click="openPermission({ role_id: Number(row.role_id), system_branch_id: row.branch.id, application_code: selectedApplicationCode })"
                      >
                        Cấu hình
                      </button>
                      <span v-else class="text-slate-300 text-[11px]">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer matching image2.png: Cancel & Lưu -->
          <footer class="px-5 py-3 bg-slate-50/90 border-t border-slate-200 flex justify-end gap-2.5">
            <button
              class="px-5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer transition-colors"
              @click="showApplicationModal = false"
            >
              Cancel
            </button>
            <button
              class="px-6 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs transition-colors"
              @click="saveApplication"
            >
              Lưu
            </button>
          </footer>
        </div>
      </div>

      <!-- Modal: Phân quyền chi tiết Màn hình (Matching image3.png) -->
      <div v-if="showPermissionModal" class="fixed inset-0 z-[110] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-full max-w-5xl h-[85vh] rounded-lg shadow-2xl flex flex-col overflow-hidden border border-slate-200 animate-in">
          <!-- Sky-blue header banner matching image3.png -->
          <header class="px-5 py-3 bg-[#72c6e6] text-white flex items-center justify-between">
            <h3 class="text-sm font-bold tracking-wide text-white m-0">Phân Quyền</h3>
            <button
              @click="showPermissionModal = false"
              class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none"
            >
              ✕
            </button>
          </header>

          <!-- Sub-bar matching image3.png -->
          <div class="px-5 py-2 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
            <span class="text-[#0ea5e9] font-bold text-xs">Màn hình</span>
          </div>

          <!-- Body -->
          <div v-if="permissionLoading" class="flex-1 grid place-items-center text-slate-400 font-medium">
            Đang tải ma trận quyền...
          </div>
          <div v-else class="flex-1 overflow-auto p-4 space-y-4">
            <section
              v-for="(permissions, module) in permissionGroups"
              :key="module"
              class="border border-slate-200 rounded-md overflow-hidden bg-white shadow-2xs"
            >
              <!-- Module Header Bar matching image3.png: [-] [x] ModuleName + [Thêm] -->
              <div class="flex items-center gap-2.5 px-3 py-2 bg-slate-100/90 border-b border-slate-200">
                <span class="w-3.5 h-3.5 rounded-xs bg-[#72c6e6] text-white flex items-center justify-center font-black text-[10px] shrink-0 leading-none">
                  -
                </span>
                <span class="font-bold text-slate-800 text-xs uppercase tracking-wide">
                  {{ module }}
                </span>
              </div>

              <!-- Screens table matching image3.png columns: Màn hình | View | Add | Delete | Edit -->
              <table class="w-full border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                    <th class="text-center p-2.5 pl-6 align-middle border-r border-slate-200 border">Màn Hình</th>
                    <th class="w-20 text-center p-2.5 align-middle border-r border-slate-200 border">View</th>
                    <th class="w-20 text-center p-2.5 align-middle border-r border-slate-200 border">Add</th>
                    <th class="w-20 text-center p-2.5 align-middle border-r border-slate-200 border">Delete</th>
                    <th class="w-20 text-center p-2.5 align-middle border-r border-slate-200 border">Edit</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr
                    v-for="screen in screensForModule(permissions)"
                    :key="screen.code"
                    class="hover:bg-slate-50/80 transition-colors"
                  >
                    <td class="p-2.5 pl-6 border-slate-200">
                      <span class="font-semibold text-slate-800">{{ screen.name }}</span>
                      <small v-if="screen.path" class="block text-slate-400 text-[10px]">{{ screen.path }}</small>
                    </td>
                    <!-- Checkbox cells in order: View, Add, Delete, Edit -->
                    <td v-for="action in ['view', 'add', 'delete', 'edit']" :key="action" class="text-center p-2.5 border-slate-200">
                      <input
                        v-if="screen.actions[action]"
                        type="checkbox"
                        :checked="screen.actions[action].granted"
                        @change="togglePermission(screen, action, $event.target.checked)"
                        class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer"
                      />
                      <span v-else class="text-slate-200 text-xs">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </section>
          </div>

          <!-- Footer -->
          <footer class="px-5 py-3 bg-slate-50/90 border-t border-slate-200 flex justify-end gap-2.5">
            <button
              class="px-5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer transition-colors"
              @click="showPermissionModal = false"
            >
              Cancel
            </button>
            <button
              class="px-6 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs transition-colors"
              @click="savePermissionMatrix"
            >
              Lưu
            </button>
          </footer>
        </div>
      </div>

      <!-- Modal: Thêm bộ phận -->
      <div v-if="showDepartmentModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-[420px] rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <header class="px-5 py-3 bg-[#72c6e6] text-white font-bold text-sm flex items-center justify-between">
            <span>{{ departmentForm.id ? 'Sửa' : 'Thêm' }} bộ phận</span>
            <button @click="showDepartmentModal = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
          </header>
          <div class="p-5 space-y-3">
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã bộ phận *</label>
              <input
                v-model="departmentForm.code"
                :required="!departmentForm.id"
                :readonly="!!departmentForm.id"
                :title="departmentForm.id ? 'Mã bộ phận không được sửa' : ''"
                class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500"
                :class="departmentForm.id ? 'bg-slate-100 text-slate-500' : 'bg-[#fffbeb]'"
                placeholder="Ví dụ: FO, HK, FB..."
              />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Tên bộ phận *</label>
              <input
                v-model="departmentForm.name"
                required
                class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white"
                placeholder="Ví dụ: Bộ phận Lễ Tân"
              />
            </div>
          </div>
          <footer class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2.5">
            <button class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer" @click="showDepartmentModal = false">Cancel</button>
            <button class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs" @click="saveDepartment">Lưu</button>
          </footer>
        </div>
      </div>

      <!-- Modal: Thêm/Sửa vị trí công việc -->
      <div v-if="showPositionModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-[460px] rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <header class="px-5 py-3 bg-[#72c6e6] text-white font-bold text-sm flex items-center justify-between">
            <span>{{ positionForm.id ? 'Sửa' : 'Thêm' }} vị trí công việc</span>
            <button @click="showPositionModal = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
          </header>
          <div class="p-5 space-y-3">
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Bộ phận</label>
              <input
                class="w-full border border-slate-200 rounded-md p-2 text-xs bg-slate-100 text-slate-600 font-semibold"
                :value="departments.find(item => item.id === positionForm.department_id)?.name"
                readonly
              />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã vị trí *</label>
              <input
                v-model="positionForm.code"
                :required="!positionForm.id"
                :readonly="!!positionForm.id"
                :title="positionForm.id ? 'Mã vị trí không được sửa' : ''"
                class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500"
                :class="positionForm.id ? 'bg-slate-100 text-slate-500' : 'bg-[#fffbeb]'"
                placeholder="Ví dụ: FOM, FOS, FO..."
              />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Tên vị trí công việc *</label>
              <input
                v-model="positionForm.name"
                required
                class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white"
                placeholder="Ví dụ: Trưởng Bộ Phận, Nhân Viên Lễ Tân..."
              />
            </div>
          </div>
          <footer class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2.5">
            <button class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer" @click="showPositionModal = false">Cancel</button>
            <button class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs" @click="savePosition">Lưu</button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.animate-in {
  animation: modalFadeIn 0.2s ease-out forwards;
}
@keyframes modalFadeIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}
</style>
