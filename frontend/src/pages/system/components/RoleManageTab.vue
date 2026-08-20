<script setup>
import { ref, onMounted, computed } from 'vue'
import { fetchRoles, fetchAllPermissions, createRole, updateRole, deleteRole, syncRolePermissions } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const roles = ref([])
const allPermissions = ref({}) // grouped by module
const loading = ref(false)
const selectedRole = ref(null)
const showPermModal = ref(false)
const showCreateModal = ref(false)
const pendingPermIds = ref([])

const newRoleForm = ref({ code: '', name: '', level: 3, department_scope: '', description: '' })

const MODULE_LABELS = {
  FO: 'Lễ Tân (FO)',
  HK: 'Buồng Phòng (HK)',
  FB: 'Nhà Hàng (FB)',
  MGMT: 'Quản Lý (MGMT)',
  SYSTEM: 'Hệ Thống',
}
const MODULE_COLORS = {
  FO: 'bg-blue-100 text-blue-700 border-blue-200',
  HK: 'bg-green-100 text-green-700 border-green-200',
  FB: 'bg-orange-100 text-orange-700 border-orange-200',
  MGMT: 'bg-purple-100 text-purple-700 border-purple-200',
  SYSTEM: 'bg-slate-100 text-slate-700 border-slate-200',
}
const LEVEL_LABELS = { 1: 'System', 2: 'Chi Nhánh', 3: 'Bộ Phận' }
const DEPT_LABELS = { FO: 'Lễ Tân', HK: 'Buồng Phòng', FB: 'Nhà Hàng', MGMT: 'Quản Lý' }

const loadData = async () => {
  loading.value = true
  try {
    const [r, p] = await Promise.all([fetchRoles(), fetchAllPermissions()])
    roles.value = r.data.data || []
    allPermissions.value = p.data.data || {}
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

const openPermModal = (role) => {
  selectedRole.value = role
  pendingPermIds.value = role.permissions?.map(p => p.id) || []
  showPermModal.value = true
}

const togglePerm = (permId) => {
  const idx = pendingPermIds.value.indexOf(permId)
  if (idx === -1) pendingPermIds.value.push(permId)
  else pendingPermIds.value.splice(idx, 1)
}

const savePermissions = async () => {
  try {
    await syncRolePermissions(selectedRole.value.id, { permission_ids: pendingPermIds.value })
    uiStore.showToast('Đã cập nhật quyền thành công!', 'success')
    showPermModal.value = false
    await loadData()
  } catch {
    uiStore.showToast('Lỗi khi lưu quyền', 'error')
  }
}

const saveNewRole = async () => {
  if (!newRoleForm.value.code || !newRoleForm.value.name) {
    uiStore.showToast('Vui lòng nhập đầy đủ mã và tên vai trò', 'warning')
    return
  }
  try {
    await createRole({ ...newRoleForm.value, department_scope: newRoleForm.value.department_scope || null })
    uiStore.showToast('Đã tạo vai trò mới!', 'success')
    showCreateModal.value = false
    newRoleForm.value = { code: '', name: '', level: 3, department_scope: '', description: '' }
    await loadData()
  } catch (e) {
    uiStore.showToast(e.response?.data?.errors?.code?.[0] || 'Lỗi tạo vai trò', 'error')
  }
}

const deleteRoleConfirm = async (role) => {
  if (!confirm(`Xóa vai trò "${role.name}"?`)) return
  try {
    await deleteRole(role.id)
    uiStore.showToast('Đã xóa vai trò', 'success')
    await loadData()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Không thể xóa vai trò này', 'error')
  }
}

const builtInCodes = ['super_admin','branch_admin','fo_manager','fo_staff','hk_manager','hk_staff','fb_manager','fb_staff','mgmt']
const isBuiltIn = (role) => builtInCodes.includes(role.code)

const totalPermCount = computed(() => Object.values(allPermissions.value).flat().length)
</script>

<template>
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
      <div>
        <h2 class="text-sm font-black text-slate-800">Quản Lý Vai Trò & Phân Quyền</h2>
        <p class="text-xs text-slate-500 mt-0.5">Định nghĩa vai trò và gán quyền chi tiết cho từng bộ phận</p>
      </div>
      <button @click="showCreateModal = true"
        class="flex items-center gap-1.5 px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white text-xs font-bold rounded-lg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Tạo Vai Trò Mới
      </button>
    </div>

    <!-- Stats -->
    <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex gap-4 shrink-0">
      <div class="text-xs text-slate-500">Tổng <span class="font-black text-slate-700">{{ roles.length }}</span> vai trò</div>
      <div class="text-xs text-slate-500">Tổng <span class="font-black text-slate-700">{{ totalPermCount }}</span> quyền</div>
    </div>

    <!-- Roles Grid -->
    <div class="flex-1 overflow-y-auto p-6">
      <div v-if="loading" class="flex items-center justify-center h-40">
        <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <div v-else class="grid grid-cols-1 gap-3">
        <!-- Group by level -->
        <template v-for="level in [1, 2, 3]" :key="level">
          <div v-if="roles.filter(r => r.level === level).length > 0">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
              <div class="h-px flex-1 bg-slate-100"></div>
              <span>Cấp {{ level }} — {{ LEVEL_LABELS[level] }}</span>
              <div class="h-px flex-1 bg-slate-100"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div v-for="role in roles.filter(r => r.level === level)" :key="role.id"
                class="bg-white border border-slate-200 rounded-xl p-4 hover:border-sky-300 hover:shadow-sm transition-all">
                <!-- Role header -->
                <div class="flex items-start justify-between mb-3">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-black text-slate-800">{{ role.name }}</span>
                      <span v-if="isBuiltIn(role)"
                        class="text-[9px] font-bold bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full border border-slate-200">
                        Built-in
                      </span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-0.5 font-mono">{{ role.code }}</p>
                  </div>
                  <div v-if="role.department_scope"
                    :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', MODULE_COLORS[role.department_scope]]">
                    {{ DEPT_LABELS[role.department_scope] || role.department_scope }}
                  </div>
                </div>

                <!-- Description -->
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">{{ role.description || '—' }}</p>

                <!-- Permission count -->
                <div class="flex items-center justify-between">
                  <span class="text-[10px] text-slate-400">
                    <span class="font-black text-sky-600">{{ role.permissions?.length || 0 }}</span> quyền
                  </span>
                  <div class="flex gap-1.5">
                    <button @click="openPermModal(role)"
                      class="text-[10px] font-bold text-sky-600 hover:text-sky-700 bg-sky-50 hover:bg-sky-100 px-2 py-1 rounded-lg transition-colors border border-sky-200">
                      Gán Quyền
                    </button>
                    <button v-if="!isBuiltIn(role)" @click="deleteRoleConfirm(role)"
                      class="text-[10px] font-bold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2 py-1 rounded-lg transition-colors border border-red-200">
                      Xóa
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Modal: Gán quyền -->
    <Teleport to="body">
      <div v-if="showPermModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <div>
              <h3 class="text-sm font-black text-slate-800">Phân Quyền: {{ selectedRole?.name }}</h3>
              <p class="text-xs text-slate-500 mt-0.5">Chọn các quyền cho vai trò này</p>
            </div>
            <button @click="showPermModal = false" class="text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div v-for="(perms, module) in allPermissions" :key="module">
              <div :class="['inline-flex items-center gap-1 text-[10px] font-black px-2 py-1 rounded-full border mb-2', MODULE_COLORS[module] || 'bg-slate-100 text-slate-700 border-slate-200']">
                {{ MODULE_LABELS[module] || module }}
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <label v-for="perm in perms" :key="perm.id"
                  class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 cursor-pointer group">
                  <input type="checkbox" :value="perm.id" v-model="pendingPermIds"
                    class="w-3.5 h-3.5 rounded accent-sky-500"/>
                  <span class="text-xs text-slate-700 group-hover:text-slate-900 leading-tight">{{ perm.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="px-5 py-3 border-t border-slate-100 flex justify-between items-center shrink-0 bg-slate-50">
            <span class="text-xs text-slate-500">
              <span class="font-black text-sky-600">{{ pendingPermIds.length }}</span> quyền đã chọn
            </span>
            <div class="flex gap-2">
              <button @click="showPermModal = false"
                class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
                Hủy
              </button>
              <button @click="savePermissions"
                class="px-4 py-1.5 text-xs font-bold text-white bg-sky-500 hover:bg-sky-600 rounded-lg">
                Lưu Quyền
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Tạo role mới -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-800">Tạo Vai Trò Mới</h3>
            <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-5 space-y-3">
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Mã vai trò *</label>
              <input v-model="newRoleForm.code" placeholder="vd: fo_cashier"
                class="mt-1 w-full px-3 py-2 text-xs font-mono border border-slate-200 rounded-lg focus:outline-none focus:border-sky-400 bg-slate-50"/>
            </div>
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Tên hiển thị *</label>
              <input v-model="newRoleForm.name" placeholder="vd: Thu Ngân Lễ Tân"
                class="mt-1 w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-sky-400"/>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Cấp độ</label>
                <select v-model.number="newRoleForm.level"
                  class="mt-1 w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-sky-400 bg-white">
                  <option :value="2">Chi Nhánh</option>
                  <option :value="3">Bộ Phận</option>
                </select>
              </div>
              <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Bộ phận</label>
                <select v-model="newRoleForm.department_scope"
                  class="mt-1 w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-sky-400 bg-white">
                  <option value="">Tất cả</option>
                  <option value="FO">FO — Lễ Tân</option>
                  <option value="HK">HK — Buồng Phòng</option>
                  <option value="FB">FB — Nhà Hàng</option>
                  <option value="MGMT">MGMT — Quản Lý</option>
                </select>
              </div>
            </div>
            <div>
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Mô tả</label>
              <input v-model="newRoleForm.description" placeholder="Mô tả vai trò..."
                class="mt-1 w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-sky-400"/>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-2 bg-slate-50 rounded-b-2xl">
            <button @click="showCreateModal = false"
              class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50">
              Hủy
            </button>
            <button @click="saveNewRole"
              class="px-4 py-1.5 text-xs font-bold text-white bg-sky-500 hover:bg-sky-600 rounded-lg">
              Tạo Vai Trò
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
