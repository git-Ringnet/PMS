<script setup>
import { ref, onMounted, computed, onBeforeUnmount, watch } from 'vue'
import {
  fetchUsers, createUser, updateUser, deleteUser,
  uploadUserSignature, deleteUserSignature,
  fetchOrganization, fetchUserOrganization, syncUserOrganization,
  syncUserWarehouses, fetchWarehouses,
  fetchSystemBranchesList, fetchModules, resetUserPassword,
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

// Organization & Department & Position states (dynamically loaded)
const organization = ref([])

const allPositions = computed(() => {
  return organization.value.flatMap(dept => (dept.positions || []).map(p => ({
    ...p,
    department_name: dept.name,
    department_code: dept.code,
  })))
})

const departmentsMap = computed(() => {
  return Object.fromEntries(organization.value.map(d => [d.code, d.name]))
})

const jobsMap = computed(() => {
  const selectedDept = organization.value.find(d => d.code === form.value.department_code)
  if (selectedDept && selectedDept.positions && selectedDept.positions.length > 0) {
    return Object.fromEntries(selectedDept.positions.map(p => [p.code, p.name]))
  }
  return Object.fromEntries(allPositions.value.map(p => [p.code, p.name]))
})

// Modal tab state
const activeModalTab = ref('info') // 'info' hoặc 'permission'

// Permission tab state
const permLoading = ref(false)
const allBranches = ref([])
const applications = ref([])
const warehousesByBranch = ref({})
const selectedWarehouseBranchId = ref(null)
const userPermData = ref(null)

// Selected branches & warehouses trong permission tab
// selectedBranches: [{ branch_id, position_id, is_primary }]
const selectedBranches = ref([])
const selectedWarehouses = ref([]) // [{ system_branch_id, warehouse_id }]

const warehouseList = computed(() =>
  warehousesByBranch.value[Number(selectedWarehouseBranchId.value)] || []
)

const positionsForBranch = (_branchId) => {
  // Trả về toàn bộ vị trí công việc từ tất cả bộ phận.
  // Không lọc theo branch_roles vì nhân viên phải có thể chọn vị trí
  // ngay cả khi vị trí đó chưa được cấu hình ứng dụng ở Cơ cấu tổ chức.
  return allPositions.value
}

const loadWarehousesForBranch = async (branchId) => {
  const branch = allBranches.value.find(item => Number(item.id) === Number(branchId))
  if (!branch || warehousesByBranch.value[Number(branch.id)]) return
  const response = await fetchWarehouses(branch)
  warehousesByBranch.value = {
    ...warehousesByBranch.value,
    [Number(branch.id)]: response.data.data || [],
  }
}

const isBranchPositionsOpen = ref(true)

const getBranchName = (branchId) => {
  const b = allBranches.value.find(item => Number(item.id) === Number(branchId))
  return b ? (b.name || b.code) : `Chi nhánh #${branchId}`
}

const getBranchPositionName = (branchId) => {
  const branch = selectedBranches.value.find(b => b.branch_id === branchId)
  let posId = branch?.position_id
  if (!posId) {
    const mainPos = allPositions.value.find(p => p.code === form.value.job_title_code)
    if (mainPos) {
      if (branch) branch.position_id = mainPos.id
      posId = mainPos.id
    }
  }
  if (!posId) return form.value.job_title || 'Chưa gán vị trí'
  const pos = allPositions.value.find(p => Number(p.id) === Number(posId))
  return pos ? `${pos.name}` : (form.value.job_title || 'Chưa gán vị trí')
}

const getBranchPosition = (branchId) => {
  return selectedBranches.value.find(b => b.branch_id === branchId)?.position_id || ''
}

const setPositionForBranch = (branchId, positionId) => {
  const branch = selectedBranches.value.find(b => b.branch_id === branchId)
  if (branch) {
    branch.position_id = positionId ? Number(positionId) : null
  }
}

const loadPermissionData = async (userId) => {
  if (!userId) return
  permLoading.value = true
  try {
    const [orgRes, userOrgRes, branchRes] = await Promise.all([
      organization.value.length ? Promise.resolve({ data: { data: organization.value } }) : fetchOrganization(),
      fetchUserOrganization(userId),
      allBranches.value.length ? Promise.resolve({ data: { data: allBranches.value } }) : fetchSystemBranchesList(),
    ])
    organization.value = orgRes.data.data || []
    userPermData.value = userOrgRes.data.data || {}
    allBranches.value = branchRes.data.data || []
    const primaryBranchId = userPermData.value.primary_branch_id
    const assignmentsByBranch = new Map()
    for (const item of userPermData.value.assignments || []) {
      if (!assignmentsByBranch.has(Number(item.system_branch_id))) {
        assignmentsByBranch.set(Number(item.system_branch_id), {
          branch_id: Number(item.system_branch_id),
          position_id: Number(item.position_id),
          is_primary: Number(item.system_branch_id) === Number(primaryBranchId),
        })
      }
    }
    selectedBranches.value = Array.from(assignmentsByBranch.values())
    selectedWarehouses.value = (userPermData.value.warehouses || []).map(item => ({
      system_branch_id: Number(item.system_branch_id),
      warehouse_id: Number(item.warehouse_id),
    }))
    selectedWarehouseBranchId.value = Number(primaryBranchId || selectedBranches.value[0]?.branch_id) || null
    if (selectedWarehouseBranchId.value) await loadWarehousesForBranch(selectedWarehouseBranchId.value)
  } catch (e) {
    console.error('Lỗi load permissions:', e)
    uiStore.showToast('Không thể tải phân quyền nhân viên', 'error')
  } finally {
    permLoading.value = false
  }
}

const isBranchSelected = (branchId) => selectedBranches.value.some(b => b.branch_id === branchId)
const isBranchPrimary = (branchId) => selectedBranches.value.some(b => b.branch_id === branchId && b.is_primary)

const toggleBranch = (branch) => {
  const idx = selectedBranches.value.findIndex(b => b.branch_id === branch.id)
  if (idx === -1) {
    const mainPos = allPositions.value.find(p => p.code === form.value.job_title_code)
    const defaultPos = mainPos?.id || positionsForBranch(branch.id)[0]?.id || null
    selectedBranches.value.push({
      branch_id: branch.id,
      position_id: defaultPos,
      is_primary: selectedBranches.value.length === 0,
    })
    selectedWarehouseBranchId.value ||= Number(branch.id)
    loadWarehousesForBranch(branch.id)
  } else {
    const wasPrimary = selectedBranches.value[idx].is_primary
    selectedBranches.value.splice(idx, 1)
    selectedWarehouses.value = selectedWarehouses.value.filter(item =>
      Number(item.system_branch_id) !== Number(branch.id)
    )
    if (Number(selectedWarehouseBranchId.value) === Number(branch.id)) {
      selectedWarehouseBranchId.value = selectedBranches.value[0]?.branch_id || null
    }
    if (wasPrimary && selectedBranches.value.length > 0) {
      selectedBranches.value[0].is_primary = true
    }
  }
}

const togglePrimary = (branch) => {
  const idx = selectedBranches.value.findIndex(b => b.branch_id === branch.id)
  if (idx === -1) {
    const defaultPos = positionsForBranch(branch.id)[0]?.id || null
    selectedBranches.value.forEach(b => b.is_primary = false)
    selectedBranches.value.push({ branch_id: branch.id, position_id: defaultPos, is_primary: true })
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

const isWarehouseSelected = (wId) => selectedWarehouses.value.some(item =>
  Number(item.system_branch_id) === Number(selectedWarehouseBranchId.value)
  && Number(item.warehouse_id) === Number(wId)
)
const toggleWarehouse = (wId) => {
  if (!selectedWarehouseBranchId.value) return
  const idx = selectedWarehouses.value.findIndex(item =>
    Number(item.system_branch_id) === Number(selectedWarehouseBranchId.value)
    && Number(item.warehouse_id) === Number(wId)
  )
  if (idx === -1) {
    selectedWarehouses.value.push({
      system_branch_id: Number(selectedWarehouseBranchId.value),
      warehouse_id: Number(wId),
    })
  } else {
    selectedWarehouses.value.splice(idx, 1)
  }
}

const savePermissions = async (targetUserId) => {
  const userId = targetUserId || currentId.value
  if (!userId) return false

  if (selectedBranches.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất một chi nhánh và vị trí công việc', 'warning')
    return false
  }
  if (selectedBranches.value.some(item => !item.position_id)) {
    uiStore.showToast('Vui lòng chọn vị trí công việc cho từng chi nhánh đã chọn', 'warning')
    return false
  }

  permLoading.value = true
  try {
    const primaryBranch = selectedBranches.value.find(b => b.is_primary)
    const primaryBranchId = primaryBranch ? primaryBranch.branch_id : (selectedBranches.value[0]?.branch_id || null)

    const assignments = selectedBranches.value.flatMap(item => {
      const position = allPositions.value.find(pos => Number(pos.id) === Number(item.position_id))
      return (position?.branch_roles || [])
        .filter(role => Number(role.system_branch_id) === Number(item.branch_id) && role.is_active)
        .map(role => ({
          system_branch_id: Number(item.branch_id),
          application_code: role.application_code,
          position_id: Number(item.position_id),
        }))
    })
    const applicationCodes = [...new Set([
      ...applications.value.map(item => item.code),
      ...(userPermData.value?.assignments || []).map(item => item.application_code),
      ...assignments.map(item => item.application_code),
    ].filter(Boolean))]

    if (assignments.length === 0 || applicationCodes.length === 0) {
      uiStore.showToast('Vị trí đã chọn chưa được cấu hình ứng dụng và Role tại chi nhánh', 'warning')
      return false
    }

    await syncUserOrganization(userId, {
      application_codes: applicationCodes,
      assignments,
      primary_branch_id: primaryBranchId,
    })

    await syncUserWarehouses(userId, { warehouses: selectedWarehouses.value })

    uiStore.showToast('Đã cập nhật chi nhánh, vị trí công việc và quyền kho thành công!', 'success')
    if (currentId.value) {
      await loadPermissionData(currentId.value)
    }
    return true
  } catch (e) {
    console.error('Lỗi khi lưu phân quyền:', e)
    uiStore.showToast(e.response?.data?.message || 'Lỗi khi lưu phân quyền tổ chức', 'error')
    return false
  } finally {
    permLoading.value = false
  }
}

const hasValidPermissionSelection = () =>
  selectedBranches.value.length > 0 && selectedBranches.value.every(item => item.position_id)

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

onMounted(async () => {
  loadData()
  try {
    const [orgRes, branchRes, moduleRes] = await Promise.all([
      fetchOrganization(),
      fetchSystemBranchesList(),
      fetchModules(),
    ])
    organization.value = orgRes.data.data || []
    allBranches.value = branchRes.data.data || []
    applications.value = moduleRes.data.data || []
  } catch (err) {
    console.error('Lỗi tải dữ liệu tổ chức ban đầu:', err)
  }
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

// Chuyển chuỗi tiếng Việt có dấu thành username viết thường không dấu, không khoảng trắng
const toUsernameSlug = (str) => {
  if (!str) return ''
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'd')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '')
}

const isUsernameCustomized = ref(false)

const handleNameInput = () => {
  if (!isEditMode.value && !isUsernameCustomized.value) {
    form.value.username = toUsernameSlug(form.value.name)
  }
}

const handleUsernameInput = (e) => {
  const val = e.target.value
  if (!val || val.trim() === '') {
    isUsernameCustomized.value = false
    form.value.username = toUsernameSlug(form.value.name)
  } else {
    isUsernameCustomized.value = true
  }
}

// Columns metadata matching image5.png
const columns = ref([
  { id: 'employee_code', label: 'Mã Nhân Viên', visible: true, sortable: true },
  { id: 'name', label: 'Tên Nhân Viên', visible: true, sortable: true },
  { id: 'username', label: 'Tên Đăng Nhập', visible: true, sortable: true },
  { id: 'job_title', label: 'Vị Trí Công Việc', visible: true, sortable: true },
  { id: 'department', label: 'Bộ phận', visible: true, sortable: true },
  { id: 'birth_date', label: 'Ngày Sinh', visible: true, sortable: true },
  { id: 'phone', label: 'Điện Thoại', visible: true, sortable: false },
  { id: 'email', label: 'Email', visible: true, sortable: true },
  { id: 'address', label: 'Địa Chỉ', visible: true, sortable: false },
  { id: 'signature_url', label: 'Chữ Ký', visible: false, sortable: false },
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

const openAddModal = async () => {
  isEditMode.value = false
  currentId.value = null
  isUsernameCustomized.value = false
  form.value = emptyForm()
  activeModalTab.value = 'info'
  tempSignatureFile.value = null
  signaturePreviewUrl.value = null
  selectedBranches.value = []
  selectedWarehouses.value = []
  selectedWarehouseBranchId.value = null
  userPermData.value = null
  isModalOpen.value = true
  // Load tổ chức để dropdown vị trí có dữ liệu ngay
  if (organization.value.length === 0) {
    try {
      const orgRes = await fetchOrganization()
      organization.value = orgRes.data.data || []
    } catch (e) { /* ignore */ }
  }
  if (allBranches.value.length === 0) {
    try {
      const branchRes = await fetchSystemBranchesList()
      allBranches.value = branchRes.data.data || []
    } catch (e) { /* ignore */ }
  }
}

const openEditModal = async (item) => {
  isEditMode.value = true
  currentId.value = item.id
  isUsernameCustomized.value = true
  form.value = {
    employee_code: item.employee_code || '',
    name: item.name || '',
    email: item.email || '',
    username: item.username || toUsernameSlug(item.name) || '',
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
  // Load tổ chức để dropdown vị trí có dữ liệu ngay
  if (organization.value.length === 0) {
    try {
      const orgRes = await fetchOrganization()
      organization.value = orgRes.data.data || []
    } catch (e) { /* ignore */ }
  }
  if (allBranches.value.length === 0) {
    try {
      const branchRes = await fetchSystemBranchesList()
      allBranches.value = branchRes.data.data || []
    } catch (e) { /* ignore */ }
  }
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
    uiStore.showToast('Vui lòng nhập tên đăng nhập (Username)', 'warning')
    return
  }
  if (!form.value.email) {
    uiStore.showToast('Vui lòng nhập email nhân viên', 'warning')
    return
  }
  if (form.value.password && form.value.password.length < 6) {
    uiStore.showToast('Mật khẩu phải từ 6 ký tự trở lên', 'warning')
    return
  }

  if (!hasValidPermissionSelection()) {
    activeModalTab.value = 'permission'
    uiStore.showToast('Vui lòng chọn chi nhánh và vị trí công việc trước khi lưu nhân viên', 'warning')
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
      // Đồng bộ phân quyền chi nhánh, vị trí công việc & kho nếu có chọn
      if (!await savePermissions(currentId.value)) return
      uiStore.showToast('Cập nhật nhân viên thành công!', 'success')
    } else {
      res = await createUser(payload)
      const newUserId = res.data.data.id
      if (tempSignatureFile.value) {
        const formData = new FormData()
        formData.append('signature', tempSignatureFile.value)
        await uploadUserSignature(newUserId, formData)
      }
      if (!await savePermissions(newUserId)) return
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
  const userEmail = form.value.email || 'email của nhân viên'
  const confirmed = await uiStore.confirm({
    title: 'Đặt lại mật khẩu',
    message: `Bạn có chắc chắn muốn đặt lại mật khẩu của nhân viên này về Email (${userEmail}) và yêu cầu đổi mật khẩu khi đăng nhập?`,
    confirmText: 'Đặt lại',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    loading.value = true
    const res = await resetUserPassword(currentId.value)
    const msg = res.data?.message || `Đã đặt lại mật khẩu về email (${userEmail}) và bật yêu cầu đổi mật khẩu lần đầu!`
    uiStore.showToast(msg, 'success')
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
    <!-- Toolbar (Matches image5.png) -->
    <div class="flex items-center justify-between mb-3 w-full gap-4">
      <!-- Left search box matching image5.png -->
      <div class="flex items-center gap-1.5 flex-1 max-w-lg relative">
        <div class="relative flex-1 flex items-center">
          <input
            v-model="globalSearchQuery"
            type="text"
            placeholder="Tìm kiếm theo mã, tên, email nhân viên..."
            class="w-full border border-slate-300 rounded-md p-1.5 pl-7 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white h-[30px]"
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
          class="px-4 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center justify-center transition-colors shadow-2xs h-[30px] whitespace-nowrap"
        >
          Tìm Kiếm
        </button>
      </div>

      <!-- Right actions box matching image5.png -->
      <div class="flex items-center gap-2">
        <!-- Add Button with round plus icon -->
        <button
          @click="openAddModal"
          class="w-[30px] h-[30px] rounded-full border border-[#72c6e6] text-[#0ea5e9] hover:bg-sky-50 font-bold text-sm flex items-center justify-center bg-white cursor-pointer transition-colors shrink-0 shadow-2xs"
          title="Thêm nhân viên mới"
        >
          +
        </button>
        <button
          @click="openAddModal"
          class="px-3.5 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-2xs transition-colors h-[30px]"
        >
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
            title="Tùy chọn cột hiển thị"
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

    <!-- Table (Matches image5.png) -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-2xs flex-1 max-h-full">
      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100/90 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
            <!-- Headers dynamically matching visible columns -->
            <th
              v-for="col in columns"
              :key="col.id"
              v-show="col.visible"
              @click="col.sortable ? toggleSort(col.id) : null"
              class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase select-none transition-colors relative"
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
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="item in employees"
            :key="item.id"
            class="hover:bg-[#bdecfe]/40 cursor-pointer h-9 transition-colors font-medium"
            @dblclick="openEditModal(item)"
          >
            <!-- Code -->
            <td v-show="isColumnVisible('employee_code')" class="p-2 border-r border-slate-100 text-slate-600 font-normal">{{ item.employee_code || '-' }}</td>
            <!-- Name -->
            <td v-show="isColumnVisible('name')" class="p-2 border-r border-slate-100 text-slate-800 font-bold">{{ item.name }}</td>
            <!-- Username -->
            <td v-show="isColumnVisible('username')" class="p-2 border-r border-slate-100 text-sky-700 font-mono font-semibold">{{ item.username || '-' }}</td>
            <!-- Job Title -->
            <td v-show="isColumnVisible('job_title')" class="p-2 border-r border-slate-100 text-slate-600 font-normal">{{ item.job_title || '-' }}</td>
            <!-- Department -->
            <td v-show="isColumnVisible('department')" class="p-2 border-r border-slate-100 text-slate-600 font-semibold">{{ item.department || '-' }}</td>
            <!-- Birth Date -->
            <td v-show="isColumnVisible('birth_date')" class="p-2 border-r border-slate-100 text-slate-600 font-normal">{{ formatDate(item.birth_date) }}</td>
            <!-- Phone -->
            <td v-show="isColumnVisible('phone')" class="p-2 border-r border-slate-100 text-slate-600 font-normal">{{ item.phone || '-' }}</td>
            <!-- Email -->
            <td v-show="isColumnVisible('email')" class="p-2 border-r border-slate-100 text-slate-600 font-normal">{{ item.email }}</td>
            <!-- Address -->
            <td v-show="isColumnVisible('address')" class="p-2 border-r border-slate-100 text-slate-600 font-normal text-ellipsis overflow-hidden whitespace-nowrap max-w-[150px]">{{ item.address || '-' }}</td>
            <!-- Optional Signature -->
            <td v-show="isColumnVisible('signature_url')" class="p-1 border-r border-slate-100 text-center">
              <div class="w-8 h-8 border border-slate-200 rounded overflow-hidden mx-auto flex items-center justify-center bg-slate-50">
                <img v-if="item.signature_url" :src="item.signature_url" alt="Signature" class="w-full h-full object-contain" />
                <span v-else class="text-[9px] text-slate-400">N/A</span>
              </div>
            </td>

            <!-- Actions matching image5.png: red trash can icon -->
            <td class="p-2 border-r border-slate-100 text-center">
              <button
                @click.stop="handleDelete(item)"
                class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded cursor-pointer border-none bg-transparent transition-colors inline-flex items-center justify-center disabled:opacity-30"
                :disabled="authStore.user?.id === item.id"
                title="Xóa nhân viên"
              >
                <svg class="w-4 h-4 text-rose-500 fill-current" viewBox="0 0 24 24">
                  <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
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

    <!-- Modal Add/Edit (Matches image4.png) -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs p-4"
    >
      <div class="bg-white rounded-lg w-full max-w-4xl shadow-2xl overflow-hidden border border-slate-200 animate-in select-none">
        <!-- Sky-blue Header matching image4.png -->
        <div class="bg-[#72c6e6] px-5 py-3 flex items-center justify-between text-white">
          <h2 class="text-sm font-bold tracking-wide text-white m-0">
            {{ isEditMode ? 'Chỉnh Sửa Nhân Viên' : 'Thêm Nhân Viên' }}
          </h2>
          <button @click="isModalOpen = false" class="text-white hover:text-slate-100 bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
        </div>

        <!-- Tab bar matching image4.png -->
        <div class="flex border-b border-slate-200 px-6 pt-2 bg-slate-50/60 gap-4">
          <button
            type="button"
            @click="activeModalTab = 'info'"
            class="px-4 py-2 bg-transparent border-none cursor-pointer transition-colors text-xs font-bold pb-2.5"
            :class="activeModalTab === 'info'
              ? 'text-[#0ea5e9] border-b-2 border-[#0ea5e9] font-extrabold'
              : 'text-slate-500 hover:text-slate-700'"
          >
            {{ isEditMode ? 'Chỉnh Sửa Nhân Viên' : 'Thêm Nhân Viên' }}
          </button>
          <button
            type="button"
            @click="activeModalTab = 'permission'"
            class="px-4 py-2 bg-transparent border-none cursor-pointer transition-colors text-xs font-bold pb-2.5"
            :class="activeModalTab === 'permission'
              ? 'text-[#0ea5e9] border-b-2 border-[#0ea5e9] font-extrabold'
              : 'text-slate-500 hover:text-slate-700'"
          >
            Phân quyền đặc thù
          </button>
        </div>

        <!-- Tab Content: Info (matching image4.png) -->
        <div v-if="activeModalTab === 'info'" class="p-6 grid grid-cols-4 gap-6 text-xs max-h-[62vh] overflow-y-auto">
          <!-- Left fields container (2 columns) -->
          <div class="col-span-3 grid grid-cols-2 gap-4">
            <!-- Row 1: Mã NV | Tên NV -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mã Nhân Viên</label>
              <input
                v-model="form.employee_code"
                type="text"
                placeholder="Ví dụ: NB0058..."
                class="border border-slate-300 bg-slate-100 rounded-md p-1.5 font-semibold text-xs text-slate-600"
                disabled
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tên Nhân Viên *</label>
              <input
                v-model="form.name"
                @input="handleNameInput"
                type="text"
                placeholder="Nhập họ và tên..."
                class="border border-slate-300 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 text-slate-800"
              />
            </div>

            <!-- Row 2: Tên Đăng Nhập (Username) | Email -->
            <div class="flex flex-col gap-1">
              <div class="flex items-center justify-between">
                <label class="font-bold text-slate-700">Tên Đăng Nhập (Username) *</label>
                <span v-if="!isEditMode && !isUsernameCustomized && form.username" class="text-[10px] text-sky-600 font-semibold bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
                  Tự động theo tên
                </span>
              </div>
              <input
                v-model="form.username"
                @input="handleUsernameInput"
                type="text"
                placeholder="Ví dụ: thaovy, nguyenvana..."
                class="border border-slate-300 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 text-slate-800 font-mono"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Email *</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="Nhập email..."
                class="border border-slate-300 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500"
                :class="isEditMode ? 'bg-slate-100 text-slate-500' : 'bg-[#fffbeb] text-slate-800'"
                :disabled="isEditMode"
              />
            </div>

            <!-- Row 3: Bộ phận dropdown | Tên Bộ Phận display text -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Bộ Phận *</label>
              <select
                :value="form.department_code"
                @change="e => handleDepartmentChange(e.target.value)"
                class="border border-slate-300 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 h-[32px] text-slate-800"
              >
                <option value="">Chọn bộ phận...</option>
                <option v-for="(name, code) in departmentsMap" :key="code" :value="code">
                  {{ code }} - {{ name }}
                </option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Bộ Phận</label>
              <input
                :value="form.department"
                type="text"
                placeholder="Tên bộ phận"
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs text-slate-500"
                disabled
              />
            </div>

            <!-- Row 4: Vị trí công việc dropdown (lọc theo bộ phận) | Tên Vị Trí display text -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Vị Trí Công Việc *</label>
              <select
                :value="form.job_title_code"
                @change="e => handleJobChange(e.target.value)"
                class="border border-slate-300 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 h-[32px] text-slate-800"
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

            <!-- Row 5: Điện Thoại | Địa Chỉ -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Điện Thoại</label>
              <input
                v-model="form.phone"
                type="text"
                placeholder="Nhập số điện thoại..."
                class="border border-slate-300 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Địa Chỉ</label>
              <input
                v-model="form.address"
                type="text"
                placeholder="Nhập địa chỉ..."
                class="border border-slate-300 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white"
              />
            </div>

            <!-- Row 6: Ngày Sinh | Ngày Bắt Đầu -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Sinh</label>
              <input
                v-model="form.birth_date"
                type="date"
                class="border border-slate-300 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white h-[32px]"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Bắt Đầu</label>
              <input
                v-model="form.start_date"
                type="date"
                class="border border-slate-300 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white h-[32px]"
              />
            </div>

            <!-- Row 7: Mật khẩu khởi tạo (chỉ hiển thị khi tạo mới) -->
            <div v-if="!isEditMode" class="col-span-2 flex flex-col gap-1.5 p-3 bg-sky-50/60 border border-sky-200 rounded-lg">
              <div class="flex items-center justify-between">
                <label class="font-bold text-slate-800 text-xs">Mật khẩu khởi tạo (Tùy chọn)</label>
                <span class="text-[11px] text-sky-700 font-bold bg-white px-2.5 py-0.5 rounded border border-sky-300 shadow-2xs">
                  Mật khẩu mặc định: <span class="text-sky-900 underline">{{ form.email || '(Chính là Email nhân viên)' }}</span>
                </span>
              </div>
              <input
                v-model="form.password"
                type="text"
                :placeholder="form.email ? 'Mặc định nếu để trống: ' + form.email : 'Mặc định nếu để trống chính là Email của nhân viên...'"
                class="border border-slate-300 bg-white rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 text-slate-800"
              />
              <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-sky-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                  Lưu ý: Nếu để trống ô này, mật khẩu đăng nhập ban đầu sẽ là <strong>Email</strong> của nhân viên. Hệ thống sẽ bắt buộc đổi mật khẩu ở lần đăng nhập đầu tiên.
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Signature Upload Box matching image4.png -->
          <div class="col-span-1 flex flex-col gap-4">
            <div class="border border-slate-200 rounded-lg overflow-hidden bg-white flex flex-col items-center shadow-2xs">
              <div class="w-full bg-slate-50/90 border-b border-slate-200 py-2 px-3 font-bold text-slate-700 text-center text-xs">
                Chữ Ký
              </div>
              <div class="p-5 flex flex-col items-center justify-center gap-3 w-full">
                <!-- Signature Preview Dashed Circle matching image4.png -->
                <div
                  @click="triggerSignatureSelect"
                  class="w-24 h-24 border-2 border-dashed border-slate-300 rounded-full flex flex-col items-center justify-center cursor-pointer bg-white hover:bg-slate-50 transition-colors overflow-hidden relative shadow-2xs"
                >
                  <img
                    v-if="form.signature_url || signaturePreviewUrl"
                    :src="signaturePreviewUrl || form.signature_url"
                    alt="Chữ ký"
                    class="w-full h-full object-contain"
                  />
                  <div v-else class="flex flex-col items-center justify-center text-slate-400 gap-0.5 select-none text-center">
                    <span class="text-xl font-light leading-none">+</span>
                    <span class="text-[10px] font-bold text-slate-500">Chọn Ảnh</span>
                  </div>
                </div>
                <input
                  ref="signatureInput"
                  type="file"
                  class="hidden"
                  accept="image/*"
                  @change="handleSignatureSelected"
                />

                <!-- Eye & Trash buttons matching image4.png -->
                <div class="flex items-center gap-4 mt-1">
                  <button
                    type="button"
                    @click="triggerSignatureSelect"
                    class="p-1 hover:bg-slate-100 rounded text-slate-500 cursor-pointer border-none bg-transparent flex items-center justify-center"
                    title="Chọn / Xem ảnh chữ ký"
                  >
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    @click="deleteSignatureDirectly"
                    class="p-1 hover:bg-rose-50 rounded text-rose-500 cursor-pointer border-none bg-transparent flex items-center justify-center"
                    title="Xóa chữ ký"
                  >
                    <svg class="w-4 h-4 text-rose-500 fill-current" viewBox="0 0 24 24">
                      <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab Content: Permissions (matching image7.png) -->
        <div v-else class="p-6 pb-12 max-h-[62vh] overflow-y-auto space-y-5">
          <!-- Loading -->
          <div v-if="permLoading" class="flex items-center justify-center h-40">
            <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Content -->
          <div v-else class="space-y-5">
            <!-- Section 1: Chi nhánh matching image7.png -->
            <div>
              <div class="text-xs font-bold text-slate-800 mb-2">Chi Nhánh</div>
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
                        :class="isBranchSelected(branch.id) ? 'bg-[#99cff5]/45 hover:bg-[#99cff5]/60' : 'hover:bg-slate-50'">
                      <td class="py-2.5 px-4 text-center border-r border-slate-100">
                        <input type="checkbox"
                                :checked="isBranchSelected(branch.id)"
                                @change="toggleBranch(branch)"
                                class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer" />
                      </td>
                      <td class="py-2.5 px-4 font-semibold text-slate-800 border-r border-slate-100" @click="toggleBranch(branch)">
                        {{ branch.name || branch.code }}
                      </td>
                      <td class="py-2.5 px-4 text-center">
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                          <input type="checkbox"
                                 :checked="isBranchPrimary(branch.id)"
                                 @change="togglePrimary(branch)"
                                 class="sr-only peer" />
                          <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#72c6e6]"></div>
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

            <!-- Section 2: Vị trí công việc theo chi nhánh (Chế độ xem gọn gàng, có nút đóng/mở) -->
            <div v-if="selectedBranches.length > 0" class="p-3 bg-slate-50 border border-slate-200 rounded-lg w-full overflow-hidden space-y-2.5 box-border">
              <!-- Header với nút mũi tên đóng/mở -->
              <div
                @click="isBranchPositionsOpen = !isBranchPositionsOpen"
                class="flex items-center justify-between cursor-pointer select-none py-0.5 hover:opacity-85 transition-opacity"
              >
                <div class="flex items-center gap-2">
                  <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                    Vị Trí Công Việc Theo Chi Nhánh (Chỉ Xem)
                  </span>
                  <span class="text-[10px] bg-[#e0f2fe] text-[#0369a1] font-extrabold px-2 py-0.2 rounded-full border border-sky-200">
                    {{ selectedBranches.length }} chi nhánh
                  </span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-500 text-xs">
                  <span class="text-[11px] font-medium">
                    {{ isBranchPositionsOpen ? 'Thu gọn' : 'Mở rộng' }}
                  </span>
                  <svg
                    class="w-4 h-4 transition-transform duration-200"
                    :class="{ 'rotate-180': isBranchPositionsOpen }"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>

              <!-- Nội dung danh sách vị trí khi mở rộng -->
              <div v-show="isBranchPositionsOpen" class="space-y-2 pt-1 border-t border-slate-200/70">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                  <div
                    v-for="sb in selectedBranches"
                    :key="sb.branch_id"
                    class="flex items-center justify-between gap-2 p-2.5 bg-white rounded-md border border-slate-200 shadow-2xs min-w-0"
                  >
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                      <div class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></div>
                      <span class="text-xs font-bold text-slate-800 truncate" :title="getBranchName(sb.branch_id)">
                        {{ getBranchName(sb.branch_id) }}
                      </span>
                    </div>

                    <!-- Badge vị trí công việc (Read-only, tinh gọn không tràn) -->
                    <div class="shrink-0 max-w-[65%]">
                      <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-[#f0f9ff] text-[#0284c7] font-semibold text-[11px] border border-[#bae6fd] truncate"
                        :title="getBranchPositionName(sb.branch_id)"
                      >
                        <svg class="w-3 h-3 text-[#0284c7] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="truncate">{{ getBranchPositionName(sb.branch_id) }}</span>
                      </span>
                    </div>
                  </div>
                </div>

                <div class="text-[10.5px] text-slate-500 flex items-center gap-1.5 pt-0.5">
                  <svg class="w-3.5 h-3.5 text-sky-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>
                    Vị trí công việc và vai trò được phân công tập trung tại <strong>Cơ cấu tổ chức</strong> (modal Sửa ứng dụng &amp; Cấu hình).
                  </span>
                </div>
              </div>
            </div>

            <hr class="border-slate-200" />

            <!-- Section 3: Phân Quyền Kho matching image7.png: "Phân Quyền Kho Cho User:[Tên Nhân Viên]" -->
            <div>
              <div class="text-xs font-bold text-slate-800 mb-1">
                Phân Quyền Kho Cho User: <span class="font-extrabold text-slate-900">{{ form.name || form.username || 'User' }}</span>
              </div>
              <p class="text-[11px] text-slate-500 mb-3.5 italic">
                * Lưu ý: Quyền thủ kho / quản trị kho áp dụng cho phân hệ Kế toán &amp; Mua hàng (ACC / Purchase), hoạt động độc lập với quyền tác nghiệp màn hình lễ tân PMS.
              </p>
              <div v-if="selectedBranches.length > 0" class="mb-3 max-w-sm">
                <label class="block mb-1 text-[11px] font-bold text-slate-700">Chi nhánh áp dụng quyền kho</label>
                <select
                  v-model.number="selectedWarehouseBranchId"
                  @change="loadWarehousesForBranch(selectedWarehouseBranchId)"
                  class="w-full border border-slate-300 rounded-md px-2.5 py-2 text-xs font-semibold bg-white focus:outline-sky-500"
                >
                  <option v-for="item in selectedBranches" :key="item.branch_id" :value="item.branch_id">
                    {{ allBranches.find(branch => Number(branch.id) === Number(item.branch_id))?.name || item.branch_id }}
                  </option>
                </select>
              </div>              <div v-if="warehouseList.length > 0" class="grid grid-cols-3 gap-x-6 gap-y-3">
                <label v-for="w in warehouseList" :key="w.id"
                       class="flex items-center gap-2.5 text-xs text-slate-700 cursor-pointer select-none hover:text-slate-900">
                  <input type="checkbox"
                         :checked="isWarehouseSelected(w.id)"
                         @change="toggleWarehouse(w.id)"
                         class="w-4 h-4 rounded border-slate-300 text-sky-500 accent-sky-500 cursor-pointer" />
                  <span class="font-medium text-slate-700">{{ w.name }}</span>
                </label>
              </div>
              <div v-else class="text-xs text-slate-400 italic py-2">
                Không có kho nào được cấu hình cho chi nhánh này.
              </div>
            </div>
          </div>
        </div>

        <!-- Footer matching image4.png -->
        <div class="bg-slate-50/90 px-6 py-3.5 flex items-center justify-between border-t border-slate-200">
          <!-- Left actions: is_active_user & reset password -->
          <div class="flex items-center gap-6">
            <label class="relative inline-flex items-center cursor-pointer select-none">
              <input
                type="checkbox"
                v-model="form.is_active_user"
                class="sr-only peer"
              />
              <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#72c6e6]"></div>
              <span class="ml-2.5 text-xs font-bold text-slate-700">Người Sử Dụng</span>
            </label>

            <button
              v-if="isEditMode"
              type="button"
              @click="handleResetPassword"
              class="px-4 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white border-none rounded-md font-bold text-xs cursor-pointer shadow-2xs transition-colors"
            >
              Đặt Lại Mật Khẩu
            </button>
          </div>

          <!-- Right actions: Cancel, Save, and Help orange ? button -->
          <div class="flex items-center gap-2.5">
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
              class="px-6 py-1.5 bg-[#72c6e6] hover:bg-[#5db3d4] text-white rounded-md font-bold text-xs cursor-pointer border-none shadow-xs transition-colors"
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
