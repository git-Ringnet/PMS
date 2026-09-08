<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  fetchRoles, fetchModules, fetchSystemBranchesList, createRole, deleteRole,
  fetchBranchRoleMatrix, syncBranchRoleMatrix, copyRole, createPermissionScreen,
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const roles = ref([])
const branches = ref([])
const applications = ref([])
const selectedRole = ref(null)
const selectedBranchId = ref('')
const applicationCode = ref('PMS')
const groups = ref({})
const loading = ref(false)
const createModal = ref(false)
const copyModal = ref(false)
const screenModal = ref(false)
const roleForm = ref({ code: '', name: '', level: 3, department_scope: '', description: '', allow_historical_date_actions: false })
const copyForm = ref({ code: '', name: '', description: '' })
const screenForm = ref({ module: 'FO', screen_code: '', screen_name: '', path: '', screen_type: 'screen' })

const screens = computed(() => Object.values(groups.value).flat().reduce((result, permission) => {
  const key = `${permission.module}|${permission.screen_code || permission.code}`
  result[key] ||= { key, module: permission.module, name: permission.screen_name || permission.name, path: permission.path, actions: {} }
  result[key].actions[permission.action || 'view'] = permission
  return result
}, {}))
const screenRows = computed(() => Object.values(screens.value))
const selectedCount = computed(() => Object.values(groups.value).flat().filter(item => item.granted).length)

async function loadBase() {
  loading.value = true
  try {
    const [roleRes, branchRes, moduleRes] = await Promise.all([fetchRoles(), fetchSystemBranchesList(), fetchModules()])
    roles.value = roleRes.data.data || []
    branches.value = branchRes.data.data || []
    applications.value = moduleRes.data.data || []
    selectedBranchId.value ||= branches.value[0]?.id || ''
    selectedRole.value = roles.value.find(item => item.id === selectedRole.value?.id) || roles.value[0] || null
    await loadMatrix()
  } finally { loading.value = false }
}
async function loadMatrix() {
  if (!selectedRole.value || !selectedBranchId.value) { groups.value = {}; return }
  const response = await fetchBranchRoleMatrix(selectedRole.value.id, {
    system_branch_id: selectedBranchId.value, application_code: applicationCode.value,
  })
  groups.value = response.data.data.permissions || {}
}
onMounted(loadBase)
watch([selectedBranchId, applicationCode], loadMatrix)

function chooseRole(role) { selectedRole.value = role; loadMatrix() }
function toggle(row, action, checked) {
  const permission = row.actions[action]
  if (!permission) return
  permission.granted = checked
  if (checked && action !== 'view' && row.actions.view) row.actions.view.granted = true
  if (!checked && action === 'view') Object.values(row.actions).forEach(item => { item.granted = false })
}
async function saveMatrix() {
  await syncBranchRoleMatrix(selectedRole.value.id, {
    system_branch_id: Number(selectedBranchId.value),
    application_code: applicationCode.value,
    permission_ids: Object.values(groups.value).flat().filter(item => item.granted).map(item => item.id),
  })
  uiStore.showToast('Đã lưu phân quyền theo chi nhánh', 'success')
}
async function saveRole() {
  try {
    await createRole({ ...roleForm.value, department_scope: roleForm.value.department_scope || null })
    createModal.value = false
    roleForm.value = { code: '', name: '', level: 3, department_scope: '', description: '', allow_historical_date_actions: false }
    await loadBase()
    uiStore.showToast('Đã tạo vai trò', 'success')
  } catch (error) { uiStore.showToast(error.response?.data?.message || 'Không thể tạo vai trò', 'error') }
}
async function removeRole(role) {
  if (!confirm(`Xóa vai trò "${role.name}"?`)) return
  try { await deleteRole(role.id); await loadBase() }
  catch (error) { uiStore.showToast(error.response?.data?.message || 'Không thể xóa vai trò', 'error') }
}
function openCopy() {
  copyForm.value = { code: '', name: selectedRole.value ? `${selectedRole.value.name} (bản sao)` : '', description: selectedRole.value?.description || '' }
  copyModal.value = true
}
async function saveCopy() {
  await copyRole(selectedRole.value.id, {
    ...copyForm.value, system_branch_id: Number(selectedBranchId.value), application_code: applicationCode.value,
  })
  copyModal.value = false
  await loadBase()
  uiStore.showToast('Đã copy vai trò và quyền tại chi nhánh đang chọn', 'success')
}
async function saveScreen() {
  await createPermissionScreen({ ...screenForm.value, application_code: applicationCode.value })
  screenModal.value = false
  screenForm.value = { module: 'FO', screen_code: '', screen_name: '', path: '', screen_type: 'screen' }
  await loadMatrix()
  uiStore.showToast('Đã thêm màn hình và các quyền View/Add/Edit/Delete', 'success')
}
</script>

<template>
  <div class="h-full flex bg-white text-xs select-none">
    <!-- Left Column: Role List (matching image3.png) -->
    <aside class="w-64 border-r border-slate-200 flex flex-col bg-white">
      <!-- Top button and Header -->
      <div class="p-3 border-b border-slate-200 flex flex-col gap-2 bg-slate-50/50">
        <div class="flex items-center justify-between">
          <span class="font-bold text-slate-800 text-xs uppercase tracking-wide">Role</span>
          <button
            class="px-3 py-1 bg-[#0ea5e9] hover:bg-[#0284c7] text-white rounded font-bold text-xs border-none cursor-pointer shadow-2xs transition-colors inline-flex items-center gap-1"
            @click="createModal = true"
          >
            + Thêm
          </button>
        </div>
      </div>

      <!-- Roles list -->
      <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
        <button
          v-for="role in roles"
          :key="role.id"
          class="w-full text-left px-4 py-2.5 transition-colors cursor-pointer border-none border-l-4 text-xs block"
          :class="selectedRole?.id === role.id
            ? 'bg-sky-50/70 border-l-[#0ea5e9] text-[#0ea5e9] font-bold shadow-2xs'
            : 'border-l-transparent text-slate-700 hover:bg-slate-50 font-normal'"
          @click="chooseRole(role)"
        >
          <div class="truncate text-xs">{{ role.name }}</div>
          <small class="block text-slate-400 text-[10.5px] mt-0.5 truncate">{{ role.code }}</small>
        </button>
      </div>
    </aside>

    <!-- Right Column: Permission Matrix (matching image3.png) -->
    <main class="flex-1 min-w-0 flex flex-col bg-white">
      <!-- Top Header & Filter Controls -->
      <header class="p-3 border-b border-slate-200 flex items-center gap-3 bg-slate-50/90 flex-wrap">
        <!-- Ứng dụng & Chi nhánh -->
        <div class="flex items-center gap-1.5">
          <span class="text-slate-500 font-bold text-[11px]">Ứng dụng:</span>
          <select v-model="applicationCode" class="border border-slate-300 rounded-md px-2 py-1 text-xs font-semibold text-slate-800 bg-white focus:outline-sky-500">
            <option v-for="app in applications" :key="app.code" :value="app.code">{{ app.name }}</option>
          </select>
        </div>

        <div class="flex items-center gap-1.5">
          <span class="text-slate-500 font-bold text-[11px]">Chi nhánh:</span>
          <select v-model="selectedBranchId" class="border border-slate-300 rounded-md px-2 py-1 text-xs font-semibold text-slate-800 bg-white min-w-44 focus:outline-sky-500">
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
          </select>
        </div>

        <span
          v-if="selectedRole?.allow_historical_date_actions"
          class="px-2.5 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 font-semibold text-[11px]"
        >
          Cho phép thao tác ngày cũ
        </span>

        <!-- Right actions -->
        <div class="ml-auto flex items-center gap-2">
          <button
            class="px-3 py-1.5 border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-md text-xs font-semibold cursor-pointer bg-white transition-colors"
            :disabled="!selectedRole"
            @click="openCopy"
          >
            Copy phân quyền
          </button>
          <button
            class="px-3 py-1.5 border border-slate-300 hover:bg-slate-100 text-slate-700 rounded-md text-xs font-semibold cursor-pointer bg-white transition-colors"
            @click="screenModal = true"
          >
            Thêm màn hình
          </button>
          <button
            class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs transition-colors"
            :disabled="!selectedRole"
            @click="saveMatrix"
          >
            Lưu
          </button>
        </div>
      </header>

      <!-- Sub-bar matching image3.png: "Màn hình" -->
      <div class="px-5 py-2 bg-white border-b border-slate-200 flex items-center justify-between">
        <span class="text-[#0ea5e9] font-extrabold text-xs">Màn hình</span>
        <span class="text-slate-400 text-[11px] font-medium">{{ selectedCount }} quyền được cấp cho vai trò này</span>
      </div>

      <!-- Body / Matrix Table -->
      <div v-if="loading" class="flex-1 grid place-items-center text-slate-400 font-medium">
        Đang tải ma trận quyền...
      </div>
      <div v-else class="flex-1 overflow-auto p-4 space-y-4">
        <!-- Group by module matching image3.png -->
        <section
          v-for="(permissions, module) in groups"
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

          <!-- Screen Rows with Columns matching image3.png: Màn hình | View | Add | Delete | Edit -->
          <table class="w-full border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                <th class="text-left p-2.5 pl-6">Màn hình</th>
                <th class="w-20 text-center p-2.5">View</th>
                <th class="w-20 text-center p-2.5">Add</th>
                <th class="w-20 text-center p-2.5">Delete</th>
                <th class="w-20 text-center p-2.5">Edit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in screenRows.filter(r => r.module === module)"
                :key="row.key"
                class="hover:bg-slate-50/80 transition-colors"
              >
                <td class="p-2.5 pl-6">
                  <span class="font-semibold text-slate-800">{{ row.name }}</span>
                  <small v-if="row.path" class="block text-slate-400 text-[10px]">{{ row.path }}</small>
                </td>
                <td v-for="action in ['view', 'add', 'delete', 'edit']" :key="action" class="text-center p-2.5">
                  <input
                    v-if="row.actions[action]"
                    type="checkbox"
                    :checked="row.actions[action].granted"
                    @change="toggle(row, action, $event.target.checked)"
                    class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer"
                  />
                  <span v-else class="text-slate-200 text-xs">—</span>
                </td>
              </tr>
              <tr v-if="!screenRows.filter(r => r.module === module).length">
                <td colspan="5" class="p-4 text-center text-slate-400 text-xs italic">
                  Chưa có màn hình nào trong phân hệ này
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <div v-if="!Object.keys(groups).length" class="p-12 text-center text-slate-400 font-medium">
          Ứng dụng chưa có phân hệ hoặc màn hình nào được cấu hình
        </div>
      </div>
    </main>

    <Teleport to="body">
      <!-- Modal: Thêm vai trò -->
      <div v-if="createModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-[480px] rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <header class="px-5 py-3 bg-[#72c6e6] text-white font-bold text-sm flex items-center justify-between">
            <span>Thêm vai trò</span>
            <button @click="createModal = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
          </header>
          <div class="p-5 space-y-3.5">
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã vai trò (Role Code) *</label>
              <input v-model="roleForm.code" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-[#fffbeb]" placeholder="Ví dụ: FOM, HKM, FO..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Tên vai trò *</label>
              <input v-model="roleForm.name" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Ví dụ: Trưởng Bộ Phận Lễ Tân..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã bộ phận (tùy chọn)</label>
              <input v-model="roleForm.department_scope" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Ví dụ: FO, HK..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mô tả</label>
              <textarea v-model="roleForm.description" rows="2" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Mô tả chức năng vai trò..."></textarea>
            </div>
            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
              <input v-model="roleForm.allow_historical_date_actions" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer" />
              <span>Cho phép xóa / post bill / thanh toán chọn ngày cũ</span>
            </label>
          </div>
          <footer class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2.5">
            <button class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer" @click="createModal = false">Cancel</button>
            <button class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs" @click="saveRole">Tạo</button>
          </footer>
        </div>
      </div>

      <!-- Modal: Copy phân quyền -->
      <div v-if="copyModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-[460px] rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <header class="px-5 py-3 bg-[#72c6e6] text-white font-bold text-sm flex items-center justify-between">
            <span>Copy phân quyền</span>
            <button @click="copyModal = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
          </header>
          <div class="p-5 space-y-3.5">
            <p class="text-slate-500 text-xs leading-relaxed">
              Sao chép toàn bộ ma trận quyền của <b class="text-slate-800">{{ selectedRole?.name }}</b> tại chi nhánh và ứng dụng đang chọn để tạo thành một vai trò mới.
            </p>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã quyền mới *</label>
              <input v-model="copyForm.code" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-[#fffbeb]" placeholder="Ví dụ: FOM_COPY, FO_NIGHT..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Tên quyền mới *</label>
              <input v-model="copyForm.name" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Tên vai trò mới..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mô tả</label>
              <textarea v-model="copyForm.description" rows="2" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Mô tả..."></textarea>
            </div>
          </div>
          <footer class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2.5">
            <button class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer" @click="copyModal = false">Cancel</button>
            <button class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs" @click="saveCopy">Tạo phân quyền</button>
          </footer>
        </div>
      </div>

      <!-- Modal: Thêm màn hình -->
      <div v-if="screenModal" class="fixed inset-0 z-[100] bg-black/50 grid place-items-center p-4">
        <div class="bg-white w-[520px] rounded-lg shadow-2xl overflow-hidden border border-slate-200 animate-in">
          <header class="px-5 py-3 bg-[#72c6e6] text-white font-bold text-sm flex items-center justify-between">
            <span>Thêm màn hình</span>
            <button @click="screenModal = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
          </header>
          <div class="p-5 grid grid-cols-2 gap-3.5">
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Phân hệ (Module) *</label>
              <input v-model="screenForm.module" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-[#fffbeb]" placeholder="Ví dụ: FO, Reservation..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Loại chức năng *</label>
              <select v-model="screenForm.screen_type" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white">
                <option value="screen">Màn hình</option>
                <option value="report">Báo cáo</option>
                <option value="feature">Chức năng</option>
              </select>
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Mã kỹ thuật *</label>
              <input v-model="screenForm.screen_code" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Ví dụ: booking_plan..." />
            </div>
            <div>
              <label class="font-bold text-slate-700 text-xs block mb-1">Tên hiển thị *</label>
              <input v-model="screenForm.screen_name" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Ví dụ: Kế hoạch phòng..." />
            </div>
            <div class="col-span-2">
              <label class="font-bold text-slate-700 text-xs block mb-1">Đường dẫn (Route Path)</label>
              <input v-model="screenForm.path" class="w-full border border-slate-300 rounded-md p-2 text-xs font-semibold focus:outline-sky-500 bg-white" placeholder="Ví dụ: /reservation/plan..." />
            </div>
          </div>
          <footer class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2.5">
            <button class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer" @click="screenModal = false">Cancel</button>
            <button class="px-5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs border-none cursor-pointer shadow-xs" @click="saveScreen">Thêm</button>
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
