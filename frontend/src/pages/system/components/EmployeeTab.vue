<script setup>
import { ref, onMounted, computed, onBeforeUnmount, watch } from 'vue'
import { 
  fetchUsers, createUser, updateUser, deleteUser,
  uploadUserSignature, deleteUserSignature,
  fetchUserPermissions, syncUserBranches, syncUserRoles,
  fetchSystemBranchesList, fetchRoles,
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const employees = ref([])
const loading = ref(false)

// Pagination states
const currentPage = ref(1)
const perPage = ref(20)
const totalItems = ref(0)
const lastPage = ref(1)

// Search & filter states
const globalSearchQuery = ref('')
const activeSearch = ref('')

const sortField = ref('id') // Default sort
const sortDir = ref('desc')

// Mapping configs for double-select styling in form
const departmentsMap = {
  'FO': 'BỘ PHẬN LỄ TÂN',
  'HK': 'BỘ PHẬN BUỒNG PHÒNG',
  'FB': 'BỘ PHẬN NHÀ HÀNG',
  'MGMT': 'BỘ PHẬN QUẢN LÝ'
}

const jobsMap = {
  'RL017': 'Trưởng Bộ Phận',
  'RL016': 'Trưởng HK',
  'RL015': 'Trưởng nhà hàng',
  'RL001': 'Tổng giám đốc',
  'RL018': 'Nhân viên'
}

// Modal tab state
const activeModalTab = ref('info') // 'info' hoặc 'permission'

// Permission tab state
const permLoading = ref(false)
const allBranches = ref([])
const allRoles = ref([])
const userPermData = ref(null) // { branches, roles, all_permissions }

// Selected branches & roles trong permission tab
const selectedBranches = ref([]) // [{branch_id, is_primary}]
const selectedRoles = ref([]) // [{role_id, system_branch_id}]
const selectedWarehouses = ref([]) // ['bep', 'fo', ...]

const defaultWarehouseList = [
  { id: 'bep', name: 'Kho bộ phận Bếp' },
  { id: 'ccdc', name: 'Kho Công cụ dụng cụ' },
  { id: 'fb', name: 'Kho bộ phận nhà hàng' },
  { id: 'fo', name: 'Kho bộ phận FO' },
  { id: 'hk', name: 'Kho bộ phận HK' },
  { id: 'hr', name: 'Kho bộ phận HR' },
  { id: 'kt', name: 'Kho bộ phận Kỹ Thuật' },
  { id: 'sm', name: 'Kho bộ phận SM' },
  { id: 'all', name: 'Kho Tổng' },
  { id: 'food', name: 'Kho Thực phẩm' },
  { id: 'vpp', name: 'Kho tổng văn phòng phẩm' },
]

const loadPermissionData = async (userId) => {
  if (!userId) return
  permLoading.value = true
  try {
    const [permRes, branchRes, roleRes] = await Promise.all([
      fetchUserPermissions(userId),
      fetchSystemBranchesList(),
      fetchRoles(),
    ])
    userPermData.value = permRes.data.data
    allBranches.value = branchRes.data.data || []
    allRoles.value = roleRes.data.data || []

    // Pre-fill selections from existing data
    selectedBranches.value = (userPermData.value?.branches || []).map(b => ({
      branch_id: b.id,
      is_primary: !!b.is_primary,
    }))
    selectedRoles.value = (userPermData.value?.roles || []).map(r => ({
      role_id: r.role_id,
      system_branch_id: r.system_branch_id,
    }))
  } catch (e) {
    console.error('Lỗi load permissions:', e)
  } finally {
    permLoading.value = false
  }
}

const isBranchSelected = (branchId) => selectedBranches.value.some(b => b.branch_id === branchId)
const isBranchPrimary = (branchId) => selectedBranches.value.some(b => b.branch_id === branchId && b.is_primary)

const toggleBranch = (branch) => {
  const idx = selectedBranches.value.findIndex(b => b.branch_id === branch.id)
  if (idx === -1) {
    selectedBranches.value.push({ branch_id: branch.id, is_primary: selectedBranches.value.length === 0 })
  } else {
    const wasPrimary = selectedBranches.value[idx].is_primary
    selectedBranches.value.splice(idx, 1)
    if (wasPrimary && selectedBranches.value.length > 0) {
      selectedBranches.value[0].is_primary = true
    }
  }
}

const togglePrimary = (branch) => {
  const idx = selectedBranches.value.findIndex(b => b.branch_id === branch.id)
  if (idx === -1) {
    // Nếu chưa chọn chi nhánh -> tự động chọn và đặt làm primary
    selectedBranches.value.forEach(b => b.is_primary = false)
    selectedBranches.value.push({ branch_id: branch.id, is_primary: true })
  } else {
    const currentPrimary = selectedBranches.value[idx].is_primary
    if (currentPrimary) {
      selectedBranches.value[idx].is_primary = false
    } else {
      selectedBranches.value.forEach(b => b.is_primary = false)
      selectedBranches.value[idx].is_primary = true
    }
  }
}

const isWarehouseSelected = (wId) => selectedWarehouses.value.includes(wId)
const toggleWarehouse = (wId) => {
  const idx = selectedWarehouses.value.indexOf(wId)
  if (idx === -1) selectedWarehouses.value.push(wId)
  else selectedWarehouses.value.splice(idx, 1)
}

const getBranchRoleForBranch = (branchId) => {
  return selectedRoles.value.find(r => r.system_branch_id === branchId)?.role_id || null
}
const setRoleForBranch = (branchId, roleId) => {
  const idx = selectedRoles.value.findIndex(r => r.system_branch_id === branchId)
  if (roleId) {
    if (idx === -1) selectedRoles.value.push({ role_id: parseInt(roleId), system_branch_id: branchId })
    else selectedRoles.value[idx].role_id = parseInt(roleId)
  } else {
    if (idx !== -1) selectedRoles.value.splice(idx, 1)
  }
}

const savePermissions = async () => {
  if (!currentId.value) return
  permLoading.value = true
  try {
    await Promise.all([
      syncUserBranches(currentId.value, { branches: selectedBranches.value }),
      syncUserRoles(currentId.value, { roles: selectedRoles.value }),
    ])
    uiStore.showToast('Đã cập nhật phân quyền thành công!', 'success')
    await loadPermissionData(currentId.value)
  } catch (e) {
    uiStore.showToast('Lỗi khi lưu phân quyền', 'error')
  } finally {
    permLoading.value = false
  }
}

const DEPT_COLORS = {
  FO: 'bg-blue-100 text-blue-700',
  HK: 'bg-green-100 text-green-700',
  FB: 'bg-orange-100 text-orange-700',
  MGMT: 'bg-purple-100 text-purple-700',
}

// Signature upload states
const signatureInput = ref(null)
const tempSignatureFile = ref(null)
const signaturePreviewUrl = ref(null)

// Modal state
const isModalOpen = ref(false)
const isEditMode = ref(false)
const currentId = ref(null)

const emptyForm = () => ({
  employee_code: '',
  name: '',
  email: '',
  username: '',
  password: '',
  job_title_code: '',
  job_title: '',
  department_code: '',
  department: '',
  birth_date: '',
  start_date: '',
  phone: '',
  address: '',
  is_active_user: true,
  signature_url: null,
})
const form = ref(emptyForm())

onMounted(() => {
  loadData()
})

const loadData = async () => {
  loading.value = true
  try {
    const params = {
      search: activeSearch.value,
      sort_field: sortField.value,
      sort_dir: sortDir.value,
      page: currentPage.value,
      per_page: perPage.value
    }
    const res = await fetchUsers(params)
    if (res.data) {
      employees.value = res.data.data || []
      const meta = res.data.meta || {}
      currentPage.value = meta.current_page || 1
      lastPage.value = meta.last_page || 1
      totalItems.value = meta.total || 0
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải danh sách nhân viên', 'error')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  activeSearch.value = globalSearchQuery.value
  loadData()
}

const handleClearSearch = () => {
  globalSearchQuery.value = ''
  activeSearch.value = ''
  currentPage.value = 1
  loadData()
}

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'desc'
  }
  loadData()
}

const handleDepartmentChange = (val) => {
  form.value.department_code = val
  form.value.department = departmentsMap[val] || ''
}

const handleJobChange = (val) => {
  form.value.job_title_code = val
  form.value.job_title = jobsMap[val] || ''
}

// Columns metadata
const columns = ref([
  { id: 'employee_code', label: 'Mã NV', visible: true, sortable: true },
  { id: 'name', label: 'Tên Nhân Viên', visible: true, sortable: true },
  { id: 'username', label: 'Username', visible: true, sortable: true },
  { id: 'signature_url', label: 'Chữ Ký', visible: true, sortable: false },
  { id: 'job_title', label: 'Vị Trí Công Việc', visible: true, sortable: true },
  { id: 'department', label: 'Bộ phận', visible: true, sortable: true },
  { id: 'birth_date', label: 'Ngày Sinh', visible: true, sortable: true },
  { id: 'phone', label: 'Điện Thoại', visible: true, sortable: false },
  { id: 'email', label: 'Email', visible: true, sortable: true },
  { id: 'address', label: 'Địa Chỉ', visible: true, sortable: false },
])

const isColumnVisible = (colId) => {
  const col = columns.value.find(c => c.id === colId)
  return col ? col.visible : true
}

const isColumnSelectorOpen = ref(false)
const toggleColumnSelector = (e) => {
  e.stopPropagation()
  isColumnSelectorOpen.value = !isColumnSelectorOpen.value
}

const closePopovers = () => {
  isColumnSelectorOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', closePopovers)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closePopovers)
})

const openAddModal = () => {
  isEditMode.value = false
  currentId.value = null
  form.value = emptyForm()
  activeModalTab.value = 'info'
  tempSignatureFile.value = null
  signaturePreviewUrl.value = null
  isModalOpen.value = true
}

const openEditModal = (item) => {
  isEditMode.value = true
  currentId.value = item.id
  form.value = {
    employee_code: item.employee_code || '',
    name: item.name || '',
    email: item.email || '',
    username: item.username || '',
    password: '', // blank password on edit
    job_title_code: item.job_title_code || '',
    job_title: item.job_title || '',
    department_code: item.department_code || '',
    department: item.department || '',
    birth_date: item.birth_date || '',
    start_date: item.start_date || '',
    phone: item.phone || '',
    address: item.address || '',
    is_active_user: item.is_active_user !== undefined ? !!item.is_active_user : true,
    signature_url: item.signature_url || null
  }
  activeModalTab.value = 'info'
  tempSignatureFile.value = null
  signaturePreviewUrl.value = null
  userPermData.value = null
  isModalOpen.value = true
}

// Khi chuyển sang tab permission và đang edit mode → load data
watch(activeModalTab, async (tab) => {
  if (tab === 'permission' && isEditMode.value && currentId.value) {
    await loadPermissionData(currentId.value)
  }
})

const saveItem = async () => {
  if (!form.value.name) {
    uiStore.showToast('Vui lòng nhập tên nhân viên', 'warning')
    return
  }
  if (!form.value.username) {
    uiStore.showToast('Vui lòng nhập tên đăng nhập (username)', 'warning')
    return
  }
  if (!form.value.email) {
    uiStore.showToast('Vui lòng nhập email nhân viên', 'warning')
    return
  }
  if (!isEditMode.value && !form.value.password) {
    uiStore.showToast('Vui lòng nhập mật khẩu', 'warning')
    return
  }
  if (form.value.password && form.value.password.length < 6) {
    uiStore.showToast('Mật khẩu phải từ 6 ký tự trở lên', 'warning')
    return
  }
  
  loading.value = true
  try {
    const payload = { ...form.value }
    if (!payload.employee_code) payload.employee_code = null
    if (!payload.birth_date) payload.birth_date = null
    if (!payload.start_date) payload.start_date = null
    
    let res
    if (isEditMode.value) {
      res = await updateUser(currentId.value, payload)
      // If signature is selected locally but not uploaded yet, do it
      if (tempSignatureFile.value) {
        await uploadSignatureDirectly(tempSignatureFile.value)
      }
      // Đồng bộ phân quyền chi nhánh & vai trò nếu có thay đổi
      if (selectedBranches.value.length > 0) {
        try {
          await Promise.all([
            syncUserBranches(currentId.value, { branches: selectedBranches.value }),
            syncUserRoles(currentId.value, { roles: selectedRoles.value }),
          ])
        } catch (e) {
          console.error('Lỗi lưu phân quyền:', e)
        }
      }
      uiStore.showToast('Cập nhật nhân viên thành công!', 'success')
    } else {
      res = await createUser(payload)
      const newUserId = res.data.data.id
      if (tempSignatureFile.value) {
        const formData = new FormData()
        formData.append('signature', tempSignatureFile.value)
        await uploadUserSignature(newUserId, formData)
      }
      uiStore.showToast('Thêm nhân viên mới thành công!', 'success')
    }
    isModalOpen.value = false
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu nhân viên'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

const triggerSignatureSelect = () => {
  signatureInput.value.click()
}

const handleSignatureSelected = (e) => {
  const file = e.target.files[0]
  if (!file) return
  tempSignatureFile.value = file
  signaturePreviewUrl.value = URL.createObjectURL(file)
  
  if (isEditMode.value) {
    uploadSignatureDirectly(file)
  }
}

const uploadSignatureDirectly = async (file) => {
  const formData = new FormData()
  formData.append('signature', file)
  try {
    loading.value = true
    const res = await uploadUserSignature(currentId.value, formData)
    form.value.signature_url = res.data.data.signature_url
    tempSignatureFile.value = null
    signaturePreviewUrl.value = null
    uiStore.showToast('Cập nhật chữ ký thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || err.message || 'Lỗi khi tải chữ ký lên'
    uiStore.showToast('Lỗi khi tải chữ ký lên: ' + msg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteSignatureDirectly = async () => {
  if (isEditMode.value) {
    const confirmed = await uiStore.confirm({
      title: 'Xóa chữ ký',
      message: 'Bạn có chắc chắn muốn xóa chữ ký hiện tại?',
      confirmText: 'Xóa',
      cancelText: 'Hủy'
    })
    if (!confirmed) return
    try {
      loading.value = true
      await deleteUserSignature(currentId.value)
      form.value.signature_url = null
      tempSignatureFile.value = null
      signaturePreviewUrl.value = null
      uiStore.showToast('Đã xóa chữ ký thành công!', 'success')
      loadData()
    } catch (err) {
      console.error(err)
      uiStore.showToast('Không thể xóa chữ ký', 'error')
    } finally {
      loading.value = false
    }
  } else {
    tempSignatureFile.value = null
    signaturePreviewUrl.value = null
  }
}

const handleResetPassword = async () => {
  if (!isEditMode.value) return
  const confirmed = await uiStore.confirm({
    title: 'Đặt lại mật khẩu',
    message: 'Bạn có chắc chắn muốn đặt lại mật khẩu của nhân viên này về "password123"?',
    confirmText: 'Đặt lại',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    loading.value = true
    await updateUser(currentId.value, { 
      ...form.value,
      password: 'password123'
    })
    uiStore.showToast('Đặt lại mật khẩu thành công về "password123"!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể đặt lại mật khẩu', 'error')
  } finally {
    loading.value = false
  }
}

const handleDelete = async (item) => {
  // Safe check: prevent deleting currently logged-in user
  if (authStore.user && authStore.user.id === item.id) {
    uiStore.showToast('Bạn không thể tự xóa tài khoản của chính mình!', 'error')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc chắn muốn xóa nhân viên "${item.name}" (${item.email})?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  try {
    await deleteUser(item.id)
    uiStore.showToast('Xóa nhân viên thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa nhân viên này.', 'error')
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  try {
    const parts = dateStr.split('T')[0].split('-') // yyyy-mm-dd
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`
    }
    const d = new Date(dateStr)
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
  } catch (e) {
    return dateStr
  }
}

const changePage = (page) => {
  if (page < 1 || page > lastPage.value) return
  currentPage.value = page
  loadData()
}
</script>

<template>
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs select-none">
    <!-- Toolbar (Matches screenshot layout) -->
    <div class="flex items-center justify-between mb-3 w-full gap-4">
      <!-- Left search box -->
      <div class="flex items-center gap-1.5 flex-1 max-w-lg relative">
        <div class="relative flex-1 flex items-center">
          <input 
            v-model="globalSearchQuery" 
            type="text" 
            placeholder="Tìm kiếm theo mã, tên, email nhân viên..." 
            class="w-full border border-slate-200 rounded-md p-1.5 pl-7 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white h-[30px]" 
            @keyup.enter="handleSearch"
          />
          <svg class="w-3.5 h-3.5 absolute left-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <button 
            v-if="globalSearchQuery" 
            @click="handleClearSearch" 
            class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
          >
            ✕
          </button>
        </div>
        <button 
          @click="handleSearch"
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center justify-center transition-colors shadow-xs h-[30px] whitespace-nowrap"
        >
          Tìm Kiếm
        </button>
      </div>

      <!-- Right actions box -->
      <div class="flex items-center gap-2">
        <!-- Add Button -->
        <button 
          @click="openAddModal"
          class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-xs transition-colors h-[30px]"
        >
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-center text-[10px] font-extrabold leading-none">+</span>
          Thêm
        </button>

        <!-- Help Info -->
        <button class="w-[30px] h-[30px] hover:bg-slate-50 text-slate-400 hover:text-slate-600 border border-slate-200 rounded flex items-center justify-center bg-white cursor-pointer transition-colors shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
          </svg>
        </button>

        <!-- Select column dropdown trigger -->
        <div class="relative popover-container">
          <button 
            @click="toggleColumnSelector"
            class="w-[30px] h-[30px] hover:bg-slate-50 text-slate-400 hover:text-slate-600 border border-slate-200 rounded flex items-center justify-center bg-white cursor-pointer transition-colors shrink-0"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.645-.869l.214-1.28z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>

          <!-- Column Popover Selector -->
          <div v-if="isColumnSelectorOpen" class="absolute right-0 top-full mt-1.5 z-40 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[170px]" @click.stop>
            <div class="font-bold text-slate-700 border-b border-slate-100 pb-1.5 mb-1.5 uppercase tracking-wider text-[10px]">
              Hiển thị cột
            </div>
            <div class="flex flex-col gap-1.5">
              <label v-for="c in columns" :key="c.id" class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
                <input type="checkbox" v-model="c.visible" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5" />
                <span>{{ c.label }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-sm flex-1 max-h-full">
      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
            <!-- Headers dynamically matching visible columns -->
            <th 
              v-for="col in columns" 
              :key="col.id"
              v-show="col.visible"
              @click="col.sortable ? toggleSort(col.id) : null"
              class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase select-none transition-colors relative animate-fade-in"
              :class="{'cursor-pointer hover:bg-slate-200': col.sortable}"
            >
              <div class="flex items-center gap-1.5">
                <span>{{ col.label }}</span>
                <span v-if="col.sortable && sortField === col.id" class="text-[9px] text-sky-500">
                  {{ sortDir === 'asc' ? '▲' : '▼' }}
                </span>
              </div>
            </th>
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase text-center w-16">Xóa</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="item in employees" 
            :key="item.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors font-medium"
            @dblclick="openEditModal(item)"
          >
            <!-- Code -->
            <td v-show="isColumnVisible('employee_code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.employee_code || '-' }}</td>
            <!-- Name -->
            <td v-show="isColumnVisible('name')" class="p-2 border-r border-slate-200 text-slate-700 font-bold">{{ item.name }}</td>
            <!-- Username -->
            <td v-show="isColumnVisible('username')" class="p-2 border-r border-slate-200 text-slate-600 font-semibold">{{ item.username || '-' }}</td>
            <!-- Signature -->
            <td v-show="isColumnVisible('signature_url')" class="p-1 border-r border-slate-200 text-center">
              <div class="w-8 h-8 border border-slate-200 rounded overflow-hidden mx-auto flex items-center justify-center bg-slate-50">
                <img v-if="item.signature_url" :src="item.signature_url" alt="Signature" class="w-full h-full object-contain" />
                <span v-else class="text-[9px] text-slate-400">N/A</span>
              </div>
            </td>
            <!-- Job Title -->
            <td v-show="isColumnVisible('job_title')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.job_title || '-' }}</td>
            <!-- Department -->
            <td v-show="isColumnVisible('department')" class="p-2 border-r border-slate-200 text-slate-600 font-semibold">{{ item.department || '-' }}</td>
            <!-- Birth Date -->
            <td v-show="isColumnVisible('birth_date')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ formatDate(item.birth_date) }}</td>
            <!-- Phone -->
            <td v-show="isColumnVisible('phone')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.phone || '-' }}</td>
            <!-- Email -->
            <td v-show="isColumnVisible('email')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.email }}</td>
            <!-- Address -->
            <td v-show="isColumnVisible('address')" class="p-2 border-r border-slate-200 text-slate-600 font-normal text-ellipsis overflow-hidden whitespace-nowrap max-w-[150px]">{{ item.address || '-' }}</td>
            
            <!-- Actions -->
            <td class="p-2 border-r border-slate-200 text-center">
              <button 
                @click.stop="handleDelete(item)"
                class="p-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center w-6 h-6 disabled:opacity-40"
                :disabled="authStore.user?.id === item.id"
                title="Xóa nhân viên"
              >
                <!-- SVG Delete Trash Can -->
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          
          <tr v-if="employees.length === 0 && !loading">
            <td :colspan="columns.filter(c => c.visible).length + 1" class="p-8 text-center text-slate-400 text-xs font-semibold">
              Chưa có dữ liệu nhân viên
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-end mt-3 gap-1 select-none shrink-0">
      <button 
        @click="changePage(currentPage - 1)" 
        :disabled="currentPage === 1"
        class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
      >
        &lt;
      </button>
      <button 
        v-for="p in lastPage" 
        :key="p"
        @click="changePage(p)"
        class="px-2.5 py-1 border rounded text-xs font-bold cursor-pointer"
        :class="currentPage === p ? 'border-sky-400 text-sky-600 bg-sky-50' : 'border-slate-200 text-slate-500 bg-white hover:bg-slate-50'"
      >
        {{ p }}
      </button>
      <button 
        @click="changePage(currentPage + 1)" 
        :disabled="currentPage === lastPage"
        class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
      >
        &gt;
      </button>
    </div>

    <!-- Modal Add/Edit -->
    <div 
      v-if="isModalOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs"
    >
      <div class="bg-white rounded-lg w-full max-w-4xl shadow-2xl overflow-hidden border border-slate-100 animate-in select-none">
        <!-- Header -->
        <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white border-b border-slate-200">
          <h2 class="text-sm font-bold tracking-wide">
            {{ isEditMode ? 'Chỉnh Sửa Nhân Viên' : 'Thêm Nhân Viên' }}
          </h2>
          <button @click="isModalOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
        </div>

        <!-- Tab bar -->
        <div class="flex border-b border-slate-100 px-6 pt-2 bg-slate-50 gap-4">
          <button 
            type="button"
            @click="activeModalTab = 'info'"
            class="px-4 py-2 bg-transparent border-none cursor-pointer transition-colors text-xs font-bold pb-2.5"
            :class="activeModalTab === 'info' 
              ? 'text-sky-500 border-b-2 border-sky-500 font-extrabold' 
              : 'text-slate-500 hover:text-slate-700'"
          >
            {{ isEditMode ? 'Chỉnh Sửa Nhân Viên' : 'Thêm Nhân Viên' }}
          </button>
          <button 
            type="button"
            @click="activeModalTab = 'permission'"
            class="px-4 py-2 bg-transparent border-none cursor-pointer transition-colors text-xs font-bold pb-2.5"
            :class="activeModalTab === 'permission' 
              ? 'text-sky-500 border-b-2 border-sky-500 font-extrabold' 
              : 'text-slate-500 hover:text-slate-700'"
          >
            Phân quyền đặc thù
          </button>
        </div>
        
        <!-- Tab Content: Info -->
        <div v-if="activeModalTab === 'info'" class="p-6 grid grid-cols-4 gap-6 text-xs max-h-[60vh] overflow-y-auto scrollbar-none">
          <!-- Left fields container -->
          <div class="col-span-3 grid grid-cols-2 gap-4">
            <!-- Mã NV -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mã Nhân Viên</label>
              <input 
                v-model="form.employee_code" 
                type="text" 
                placeholder="Ví dụ: NB0058..." 
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
                :disabled="isEditMode"
              />
            </div>

            <!-- Tên NV -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tên Nhân Viên</label>
              <input 
                v-model="form.name" 
                type="text" 
                placeholder="Nhập họ và tên..." 
                class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
              />
            </div>

            <!-- Bộ phận -->
            <div class="col-span-2 grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-700">Bộ Phận</label>
                <select 
                  :value="form.department_code" 
                  @change="e => handleDepartmentChange(e.target.value)"
                  class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 h-[30px]"
                >
                  <option value="">Chọn bộ phận...</option>
                  <option v-for="(name, code) in departmentsMap" :key="code" :value="code">
                    {{ code }} - {{ name }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-700">Tên Bộ Phận</label>
                <input 
                  :value="form.department" 
                  type="text" 
                  placeholder="Tên bộ phận" 
                  class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs text-slate-500" 
                  disabled
                />
              </div>
            </div>

            <!-- Vị trí công việc -->
            <div class="col-span-2 grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-700">Vị Trí Công Việc</label>
                <select 
                  :value="form.job_title_code" 
                  @change="e => handleJobChange(e.target.value)"
                  class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 h-[30px]"
                >
                  <option value="">Chọn vị trí...</option>
                  <option v-for="(name, code) in jobsMap" :key="code" :value="code">
                    {{ code }} - {{ name }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-700">Tên Vị Trí Công Việc</label>
                <input 
                  :value="form.job_title" 
                  type="text" 
                  placeholder="Tên vị trí" 
                  class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs text-slate-500" 
                  disabled
                />
              </div>
            </div>

            <!-- Username -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tên Đăng Nhập (Username) *</label>
              <input 
                v-model="form.username" 
                type="text" 
                placeholder="Nhập username..." 
                class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
                :disabled="isEditMode"
              />
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Email *</label>
              <input 
                v-model="form.email" 
                type="email" 
                placeholder="Nhập email..." 
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
                :disabled="isEditMode"
              />
            </div>

            <!-- Điện thoại -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Điện Thoại</label>
              <input 
                v-model="form.phone" 
                type="text" 
                placeholder="Nhập số điện thoại..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white" 
              />
            </div>

            <!-- Ngày Sinh -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Sinh</label>
              <input 
                v-model="form.birth_date" 
                type="date" 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white h-[30px]" 
              />
            </div>

            <!-- Ngày Bắt Đầu -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Bắt Đầu</label>
              <input 
                v-model="form.start_date" 
                type="date" 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white h-[30px]" 
              />
            </div>

            <!-- Mật khẩu (chỉ hiển thị khi thêm mới, khi sửa có nút Đặt lại mật khẩu ở dưới) -->
            <div v-if="!isEditMode" class="col-span-2 flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mật khẩu *</label>
              <input 
                v-model="form.password" 
                type="password" 
                placeholder="Nhập mật khẩu..." 
                class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
              />
            </div>

            <!-- Địa Chỉ -->
            <div class="col-span-2 flex flex-col gap-1">
              <label class="font-bold text-slate-700">Địa Chỉ</label>
              <input 
                v-model="form.address" 
                type="text" 
                placeholder="Nhập địa chỉ..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white w-full" 
              />
            </div>
          </div>

          <!-- Right: Signature Upload Box -->
          <div class="col-span-1 flex flex-col gap-4">
            <div class="border border-slate-200 rounded-lg overflow-hidden bg-white flex flex-col items-center shadow-xs">
              <div class="w-full bg-slate-50 border-b border-slate-200 py-1.5 px-3 font-bold text-slate-700 text-center text-xs">
                Chữ Ký
              </div>
              <div class="p-4 flex flex-col items-center justify-center gap-3 w-full">
                <!-- Signature Preview Circle -->
                <div 
                  @click="triggerSignatureSelect"
                  class="w-24 h-24 border border-dashed border-slate-300 rounded-full flex flex-col items-center justify-center cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors overflow-hidden relative"
                >
                  <img 
                    v-if="form.signature_url || signaturePreviewUrl" 
                    :src="signaturePreviewUrl || form.signature_url" 
                    alt="Chữ ký" 
                    class="w-full h-full object-contain" 
                  />
                  <div v-else class="flex flex-col items-center justify-center text-slate-400 gap-1 select-none">
                    <span class="text-base font-light">+</span>
                    <span class="text-[9px] font-bold">Chọn Ảnh</span>
                  </div>
                </div>
                <input 
                  ref="signatureInput" 
                  type="file" 
                  class="hidden" 
                  accept="image/*" 
                  @change="handleSignatureSelected"
                />
                
                <div class="flex gap-4 mt-1">
                  <!-- Select / View button -->
                  <button 
                    type="button"
                    @click="triggerSignatureSelect"
                    class="p-1 hover:bg-slate-100 rounded text-slate-500 cursor-pointer border-none bg-transparent flex items-center justify-center w-6 h-6"
                    title="Chọn ảnh chữ ký"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <!-- Delete button -->
                  <button 
                    type="button"
                    @click="deleteSignatureDirectly"
                    class="p-1 hover:bg-red-50 rounded text-red-500 cursor-pointer border-none bg-transparent flex items-center justify-center w-6 h-6"
                    title="Xóa chữ ký"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab Content: Permissions -->
        <div v-else class="p-6 max-h-[60vh] overflow-y-auto space-y-5">
          <!-- Loading -->
          <div v-if="permLoading" class="flex items-center justify-center h-40">
            <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Chưa mở edit mode -->
          <div v-else-if="!isEditMode" class="flex flex-col items-center justify-center h-32 gap-2 text-slate-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
            <span class="text-xs font-semibold">Lưu thông tin nhân viên trước để phân quyền</span>
          </div>

          <!-- Content -->
          <div v-else class="space-y-5">
            <!-- Section 1: Chi nhánh -->
            <div>
              <div class="text-xs font-bold text-slate-700 mb-2.5">Chi Nhánh</div>
              <div class="border border-slate-200 rounded-md overflow-hidden bg-white shadow-2xs">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 font-bold">
                      <th class="py-2.5 px-4 w-28 text-center border-r border-slate-200">Chi Nhánh</th>
                      <th class="py-2.5 px-4 border-r border-slate-200">Tên Chi Nhánh</th>
                      <th class="py-2.5 px-4 w-44 text-center">Chi Nhánh Chính</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="branch in allBranches" :key="branch.id"
                        class="transition-colors cursor-pointer"
                        :class="isBranchSelected(branch.id) ? 'bg-[#99cff5]/40 hover:bg-[#99cff5]/50' : 'hover:bg-slate-50'">
                      <td class="py-3 px-4 text-center border-r border-slate-100">
                        <input type="checkbox"
                               :checked="isBranchSelected(branch.id)"
                               @change="toggleBranch(branch)"
                               class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer" />
                      </td>
                      <td class="py-3 px-4 font-semibold text-slate-800 border-r border-slate-100" @click="toggleBranch(branch)">
                        {{ branch.name || branch.code }}
                      </td>
                      <td class="py-3 px-4 text-center">
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                          <input type="checkbox"
                                 :checked="isBranchPrimary(branch.id)"
                                 @change="togglePrimary(branch)"
                                 class="sr-only peer" />
                          <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-400"></div>
                        </label>
                      </td>
                    </tr>
                    <tr v-if="allBranches.length === 0">
                      <td colspan="3" class="py-6 text-center text-slate-400 text-xs">
                        Đang tải danh sách chi nhánh...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Section 2: Vai trò theo chi nhánh (RBAC) -->
            <div v-if="selectedBranches.length > 0" class="p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-2.5">
              <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">Vai Trò Theo Chi Nhánh</div>
              <div class="grid grid-cols-2 gap-3">
                <div v-for="sb in selectedBranches" :key="sb.branch_id"
                     class="flex items-center gap-2 p-2 bg-white rounded border border-slate-200 shadow-2xs">
                  <div class="text-xs font-bold text-slate-700 w-24 shrink-0 truncate">
                    {{ allBranches.find(b => b.id === sb.branch_id)?.name || allBranches.find(b => b.id === sb.branch_id)?.code }}
                  </div>
                  <select
                    :value="getBranchRoleForBranch(sb.branch_id)"
                    @change="e => setRoleForBranch(sb.branch_id, e.target.value)"
                    class="flex-1 text-xs border border-slate-200 rounded px-2 py-1 bg-white focus:outline-none focus:border-sky-400">
                    <option value="">-- Chọn vai trò --</option>
                    <option v-for="role in allRoles" :key="role.id" :value="role.id">
                      {{ role.name }} ({{ role.department_scope || 'All' }})
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <hr class="border-slate-200" />

            <!-- Section 3: Phân Quyền Kho -->
            <div>
              <div class="text-xs font-bold text-slate-700 mb-3.5">
                Phân Quyền Kho Cho User: <span class="font-extrabold text-slate-900">{{ form.name || form.username || 'User' }}</span>
              </div>
              <div class="grid grid-cols-3 gap-x-6 gap-y-3.5">
                <label v-for="w in defaultWarehouseList" :key="w.id"
                       class="flex items-center gap-2.5 text-xs text-slate-700 cursor-pointer select-none">
                  <input type="checkbox"
                         :checked="isWarehouseSelected(w.id)"
                         @change="toggleWarehouse(w.id)"
                         class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer" />
                  <span class="font-medium text-slate-700">{{ w.name }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-t border-slate-100">
          <!-- Left actions: is_active_user & reset password -->
          <div class="flex items-center gap-6">
            <label class="relative inline-flex items-center cursor-pointer select-none">
              <input 
                type="checkbox" 
                v-model="form.is_active_user" 
                class="sr-only peer" 
              />
              <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-sky-400"></div>
              <span class="ml-2 text-xs font-bold text-slate-600">Người Sử Dụng</span>
            </label>
            
            <button 
              v-if="isEditMode"
              type="button"
              @click="handleResetPassword"
              class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white border-none rounded-md font-bold text-xs cursor-pointer shadow-xs transition-colors"
            >
              Đặt Lại Mật Khẩu
            </button>
          </div>
          
          <!-- Right actions: Cancel, Save, and Help icon -->
          <div class="flex items-center gap-2">
            <button 
              type="button"
              @click="isModalOpen = false" 
              class="px-5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs cursor-pointer border-none transition-colors"
            >
              Cancel
            </button>
            <button 
              type="button"
              @click="saveItem"
              class="px-5 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none shadow-xs transition-colors"
            >
              Lưu
            </button>
            <button 
              type="button"
              class="w-6 h-6 rounded-full bg-orange-400 text-white flex items-center justify-center border-none font-bold text-xs hover:bg-orange-500 cursor-pointer shadow-xs"
              title="Trợ giúp"
            >
              ?
            </button>
          </div>
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
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
