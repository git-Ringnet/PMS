<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { fetchRoles } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const hotelConfigs = ref([])
const searchConfigQuery = ref('')
const roles = ref([])
const oldDayRuleConfigKey = 'RuleUserCorrectOrPostBillPaymentOldDay'

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
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

// Standard hotel roles/positions to ensure all common roles are available in dropdown
const defaultRoleOptions = [
  { code: 'Admin', name: 'Quản trị viên (Administrator)' },
  { code: 'FO', name: 'Nhân viên lễ tân (Front Office)' },
  { code: 'FOM', name: 'Trưởng bộ phận lễ tân (FO Manager)' },
  { code: 'HK', name: 'Nhân viên buồng phòng (Housekeeping)' },
  { code: 'HKM', name: 'Trưởng buồng phòng (HK Manager)' },
  { code: 'Sales', name: 'Kinh doanh (Sales)' },
  { code: 'MGMT', name: 'Quản lý (Management)' },
  { code: 'FB', name: 'Nhân viên nhà hàng (F&B Staff)' },
  { code: 'FBM', name: 'Trưởng nhà hàng (F&B Manager)' },
  { code: 'ACC', name: 'Kế toán (Accounting)' },
]

const isEditMode = ref(false)
const isConfigModalOpen = ref(false)
const configFormState = reactive({
  id: null,
  name: '',
  value: '',
  description: ''
})

// Role dropdown state
const roleDropdownOpen = ref(false)
const roleDropdownRef = ref(null)
const roleSearchQuery = ref('')
const customRoleInput = ref('')
const selectedRoleCodes = ref([])
const registrationStatusOptions = ref([])

const fetchRegistrationStatusOptions = async () => {
  try {
    const res = await http.get('/registration-statuses')
    registrationStatusOptions.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Lỗi khi tải registration-statuses:', err)
  }
}

const getRegistrationStatusName = (code) => {
  if (!code || String(code) === '0') return 'Giữ nguyên'
  const st = registrationStatusOptions.value.find(s => String(s.booking_status_id) === String(code) || String(s.id) === String(code))
  return st ? (st.vietnamese || st.name) : code
}

const isRoleConfig = (key) => {
  if (!key) return false
  const k = String(key).trim()
  return (
    k === 'RoleUserUnlockRoomOOO/OOS' ||
    k === 'OOORoleUserUnlock' ||
    k === 'OOSRoleUserUnlock' ||
    k === oldDayRuleConfigKey ||
    k.startsWith('RoleUser') ||
    k.startsWith('RuleUser') ||
    k.includes('RoleUser') ||
    k.includes('RoleUnlock')
  )
}

// Merge fetched roles, positions, default options, and current selections
const availableRoleOptions = computed(() => {
  const map = new Map()

  // 1. Add default common roles
  defaultRoleOptions.forEach(opt => {
    map.set(opt.code.toLowerCase(), { code: opt.code, name: opt.name })
  })

  // 2. Add roles from API
  if (Array.isArray(roles.value)) {
    roles.value.forEach(r => {
      if (!r || !r.code) return
      const key = String(r.code).toLowerCase()
      if (map.has(key)) {
        const existing = map.get(key)
        if (r.name && (!existing.name || existing.name === existing.code)) {
          existing.name = r.name
        }
      } else {
        map.set(key, { code: r.code, name: r.name || r.code })
      }
    })
  }

  // 3. Ensure any selected code in current value is present in options
  selectedRoleCodes.value.forEach(code => {
    const key = String(code).toLowerCase()
    if (!map.has(key)) {
      map.set(key, { code, name: code })
    }
  })

  return Array.from(map.values())
})

const filteredRoleOptions = computed(() => {
  const q = roleSearchQuery.value.trim().toLowerCase()
  if (!q) return availableRoleOptions.value
  return availableRoleOptions.value.filter(opt =>
    opt.code.toLowerCase().includes(q) || (opt.name && opt.name.toLowerCase().includes(q))
  )
})

const syncRoleCodesToValue = () => {
  configFormState.value = selectedRoleCodes.value.join(',')
}

const toggleRole = (code) => {
  const idx = selectedRoleCodes.value.findIndex(c => c.toLowerCase() === code.toLowerCase())
  if (idx >= 0) {
    selectedRoleCodes.value.splice(idx, 1)
  } else {
    selectedRoleCodes.value.push(code)
  }
  syncRoleCodesToValue()
}

const removeRole = (code) => {
  const idx = selectedRoleCodes.value.findIndex(c => c.toLowerCase() === code.toLowerCase())
  if (idx >= 0) {
    selectedRoleCodes.value.splice(idx, 1)
    syncRoleCodesToValue()
  }
}

const selectAllRoles = () => {
  filteredRoleOptions.value.forEach(opt => {
    if (!selectedRoleCodes.value.some(c => c.toLowerCase() === opt.code.toLowerCase())) {
      selectedRoleCodes.value.push(opt.code)
    }
  })
  syncRoleCodesToValue()
}

const clearAllRoles = () => {
  selectedRoleCodes.value = []
  syncRoleCodesToValue()
}

const addCustomRole = () => {
  const code = customRoleInput.value.trim()
  if (!code) return
  if (!selectedRoleCodes.value.some(c => c.toLowerCase() === code.toLowerCase())) {
    selectedRoleCodes.value.push(code)
    syncRoleCodesToValue()
  }
  customRoleInput.value = ''
}

const handleClickOutside = (e) => {
  if (roleDropdownOpen.value && roleDropdownRef.value && !roleDropdownRef.value.contains(e.target)) {
    roleDropdownOpen.value = false
  }
}

const fetchHotelConfigs = async () => {
  loading.value = true
  try {
    const res = await http.get('/hotel-configs')
    if (res.data && res.data.data) {
      hotelConfigs.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  } finally {
    loading.value = false
  }
}

const fetchRoleOptions = async () => {
  try {
    const [rolesRes, orgRes] = await Promise.allSettled([
      fetchRoles(),
      http.get('/organization')
    ])

    const roleData = rolesRes.status === 'fulfilled' ? (rolesRes.value.data?.data ?? rolesRes.value.data ?? []) : []
    const loadedRoles = Array.isArray(roleData) ? [...roleData] : []

    // If organization endpoint available, extract positions as roles
    if (orgRes.status === 'fulfilled' && Array.isArray(orgRes.value.data?.data)) {
      orgRes.value.data.data.forEach(dept => {
        if (Array.isArray(dept.positions)) {
          dept.positions.forEach(pos => {
            if (pos && pos.code) {
              loadedRoles.push({ code: pos.code, name: pos.name })
            }
          })
        }
      })
    }

    roles.value = loadedRoles
  } catch (err) {
    console.error('Unable to load role options:', err)
  }
}

const openAddConfigModal = () => {
  modalPos.value = { x: 0, y: 0 }
  fetchRoleOptions()
  isEditMode.value = false
  Object.assign(configFormState, {
    id: null,
    name: '',
    value: '',
    description: ''
  })
  selectedRoleCodes.value = []
  roleDropdownOpen.value = false
  roleSearchQuery.value = ''
  isConfigModalOpen.value = true
}

const openEditConfigModal = (config) => {
  modalPos.value = { x: 0, y: 0 }
  fetchRoleOptions()
  isEditMode.value = true
  Object.assign(configFormState, {
    id: config.id,
    name: config.name,
    value: config.value,
    description: config.description
  })
  if (isRoleConfig(config.name)) {
    selectedRoleCodes.value = String(config.value || '')
      .split(',')
      .map(code => code.trim())
      .filter(Boolean)
  } else {
    selectedRoleCodes.value = []
  }
  roleDropdownOpen.value = false
  roleSearchQuery.value = ''
  isConfigModalOpen.value = true
}

const saveConfig = async () => {
  if (!configFormState.name) {
    uiStore.showToast('Vui lòng nhập tên cấu hình', 'warning')
    return
  }
  if (isRoleConfig(configFormState.name)) {
    syncRoleCodesToValue()
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/hotel-configs/${configFormState.id}`, configFormState)
      uiStore.showToast('Cập nhật cấu hình thành công!', 'success')
    } else {
      await http.post('/hotel-configs', configFormState)
      uiStore.showToast('Thêm cấu hình mới thành công!', 'success')
    }
    isConfigModalOpen.value = false
    fetchHotelConfigs()
    if (typeof BroadcastChannel !== 'undefined') {
      const bcNotify = new BroadcastChannel('pms-room-updates')
      bcNotify.postMessage({
        type: 'hotel-config-updated',
        name: configFormState.name,
        value: configFormState.value
      })
      bcNotify.close()
    }
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hotel-config-updated', {
        detail: { name: configFormState.name, value: configFormState.value }
      }))
    }
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu cấu hình'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteConfig = async (configId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa cấu hình này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/hotel-configs/${configId}`)
    uiStore.showToast('Xóa cấu hình thành công!', 'success')
    fetchHotelConfigs()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa cấu hình này', 'error')
  } finally {
    loading.value = false
  }
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isConfigModalOpen.value) {
    if (roleDropdownOpen.value) {
      roleDropdownOpen.value = false
    } else {
      isConfigModalOpen.value = false
    }
  }
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
  window.addEventListener('keydown', handleKeyDown)
  fetchHotelConfigs()
  fetchRoleOptions()
  fetchRegistrationStatusOptions()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
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
      <button @click="openAddConfigModal" class="btn-pms-primary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        <span>Thêm Cấu Hình</span>
      </button>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchConfigQuery" placeholder="Tìm kiếm cấu hình..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-8 h-8 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <button v-if="searchConfigQuery" @click="searchConfigQuery = ''"
          class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[220px]">Tên Cấu Hình</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[180px]">Giá Trị</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[250px]">Mô Tả</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="cfg in hotelConfigs.filter(item => !searchConfigQuery || (item.name && item.name.toLowerCase().includes(searchConfigQuery.toLowerCase())) || (item.description && item.description.toLowerCase().includes(searchConfigQuery.toLowerCase())))"
            :key="cfg.id" @click="openEditConfigModal(cfg)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="p-2.5 font-semibold text-slate-800">{{ cfg.name }}</td>
            <td class="p-2.5 font-normal text-sky-700 font-mono text-center">
              <span v-if="cfg.name === 'RegistrationStatusId_BookingCancel'">
                {{ cfg.value === '0' || !cfg.value ? '0 (Giữ nguyên)' : `${cfg.value} (${getRegistrationStatusName(cfg.value)})` }}
              </span>
              <span v-else>{{ cfg.value || '-' }}</span>
            </td>
            <td class="p-2.5 text-slate-600 font-normal leading-relaxed">{{ cfg.description || '-' }}</td>
            <td class="p-2.5 text-center">
              <div class="flex items-center justify-center">
                <button @click.stop="deleteConfig(cfg.id)"
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
          <tr v-if="hotelConfigs.length === 0">
            <td colspan="4" class="p-6 text-center text-slate-400 italic">Chưa cấu hình thông số nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT CONFIG -->
    <div v-if="isConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Cấu Hình' : 'Thêm Cấu Hình' }}
          </h2>
          <button @mousedown.stop @click="isConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-[#000000D9]">
          <div class="flex flex-col gap-1">
            <label>Tên Cấu Hình (Key) <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" v-model="configFormState.name" :disabled="isEditMode"
                placeholder="AllowChangeRoomStatus..."
                class="w-full border rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:ring-1 disabled:bg-slate-100 disabled:cursor-not-allowed"
                :class="isEditMode ? 'border-slate-200 bg-slate-50' : 'border-[#F1DD8A] bg-[#FFF8DB] focus:border-[#eab308] focus:ring-[#eab308]'" />
              <button v-if="configFormState.name && !isEditMode" @click="configFormState.name = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <!-- Khi cấu hình là loại phân quyền RoleUser... -->
          <div v-if="isRoleConfig(configFormState.name)" class="flex flex-col gap-1.5 relative" ref="roleDropdownRef">
            <div class="flex items-center justify-between">
              <label>Giá Trị (Vai trò / Role được cấp quyền)</label>
              <span class="text-[11px] font-normal text-slate-400">Đã chọn: {{ selectedRoleCodes.length }}</span>
            </div>

            <!-- Custom Multi-select Dropdown Trigger -->
            <div
              @click="roleDropdownOpen = !roleDropdownOpen"
              class="border border-slate-200 rounded-lg p-2 bg-white hover:border-sky-400 cursor-pointer flex items-center justify-between min-h-[38px] transition-all shadow-2xs"
              :class="roleDropdownOpen ? 'border-sky-500 ring-1 ring-sky-200' : ''"
            >
              <div class="flex flex-wrap gap-1.5 items-center flex-1 pr-2">
                <span v-if="selectedRoleCodes.length === 0" class="text-slate-400 text-xs font-normal">
                  -- Nhấp để chọn vai trò được phép (Admin, FO, FOM,...) --
                </span>
                <span
                  v-for="code in selectedRoleCodes"
                  :key="code"
                  class="inline-flex items-center gap-1 bg-sky-50 text-sky-700 border border-sky-200 rounded-md px-2 py-0.5 text-xs font-semibold"
                  @click.stop
                >
                  {{ code }}
                  <button
                    type="button"
                    @click.stop="removeRole(code)"
                    class="hover:text-red-500 font-bold text-xs leading-none bg-transparent border-none cursor-pointer p-0 text-slate-400"
                  >×</button>
                </span>
              </div>
              <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="roleDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>

            <!-- Dropdown Menu with Checkboxes -->
            <div
              v-if="roleDropdownOpen"
              class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-2.5 flex flex-col gap-2 max-h-72 overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            >
              <!-- Search box + Quick actions -->
              <div class="flex items-center gap-1.5 pb-2 border-b border-slate-100">
                <div class="relative flex-1">
                  <input
                    type="text"
                    v-model="roleSearchQuery"
                    placeholder="Tìm vai trò (mã, tên)..."
                    class="w-full border border-slate-200 rounded-lg pl-2.5 pr-6 py-1.5 text-xs font-normal focus:outline-sky-500"
                    @click.stop
                  />
                  <button v-if="roleSearchQuery" @click.stop="roleSearchQuery = ''"
                    class="absolute right-2 top-1.5 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
                </div>
                <button
                  type="button"
                  @click.stop="selectAllRoles"
                  class="text-xs text-sky-600 hover:text-sky-800 font-semibold bg-sky-50 hover:bg-sky-100 px-2 py-1 rounded border-none cursor-pointer whitespace-nowrap"
                >Chọn hết</button>
                <button
                  type="button"
                  @click.stop="clearAllRoles"
                  class="text-xs text-slate-500 hover:text-slate-700 font-semibold bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded border-none cursor-pointer whitespace-nowrap"
                >Bỏ chọn</button>
              </div>

              <!-- Roles List with Checkboxes -->
              <div class="overflow-y-auto max-h-40 flex flex-col gap-0.5 pr-1">
                <label
                  v-for="opt in filteredRoleOptions"
                  :key="opt.code"
                  @click.stop
                  class="flex items-center gap-2.5 p-1.5 rounded-lg hover:bg-sky-50/60 cursor-pointer select-none transition-colors"
                >
                  <input
                    type="checkbox"
                    :checked="selectedRoleCodes.some(c => c.toLowerCase() === opt.code.toLowerCase())"
                    @change="toggleRole(opt.code)"
                    class="w-3.5 h-3.5 rounded text-sky-600 focus:ring-sky-500 border-slate-300 cursor-pointer"
                  />
                  <div class="flex items-center gap-1.5 flex-1 text-xs">
                    <span class="font-semibold text-slate-800 font-mono">{{ opt.code }}</span>
                    <span v-if="opt.name && opt.name.toLowerCase() !== opt.code.toLowerCase()" class="text-slate-500 text-[11px] font-normal truncate">
                      - {{ opt.name }}
                    </span>
                  </div>
                </label>
                <div v-if="filteredRoleOptions.length === 0" class="p-3 text-center text-xs text-slate-400 italic">
                  Không tìm thấy vai trò phù hợp
                </div>
              </div>

              <!-- Add custom role row -->
              <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5" @click.stop>
                <input
                  type="text"
                  v-model="customRoleInput"
                  placeholder="Thêm mã khác (vd: Auditor)..."
                  class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-mono focus:outline-sky-500 uppercase"
                  @keydown.enter.prevent="addCustomRole"
                />
                <button
                  type="button"
                  @click="addCustomRole"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold border-none cursor-pointer"
                >+ Thêm</button>
              </div>
            </div>

            <!-- Value preview / instruction -->
            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-normal bg-slate-50 p-2 rounded-lg border border-slate-100">
              <span class="text-slate-400 font-semibold shrink-0">Giá trị lưu:</span>
              <span class="font-mono text-sky-700 font-semibold break-all">{{ configFormState.value || '(Trống)' }}</span>
            </div>
          </div>

          <!-- Khi cấu hình là RegistrationStatusId_BookingCancel (Section 18) -->
          <div v-else-if="configFormState.name === 'RegistrationStatusId_BookingCancel'" class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
              <label>Giá Trị Tình Trạng Đăng Ký Khi Hủy</label>
              <span class="text-[11px] font-normal text-slate-400">0: Giữ nguyên | khác 0: Mã trạng thái</span>
            </div>
            <select
              v-model="configFormState.value"
              class="border border-slate-200 rounded-lg p-2 focus:outline-sky-500 text-xs bg-white font-normal text-[#000000D9]"
            >
              <option value="0">0 - Giữ nguyên không thay đổi tình trạng (Mặc định)</option>
              <option
                v-for="st in registrationStatusOptions"
                :key="st.booking_status_id"
                :value="String(st.booking_status_id)"
              >
                {{ st.booking_status_id }} - {{ st.vietnamese || st.name }}
              </option>
            </select>
            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-normal bg-slate-50 p-2 rounded-lg border border-slate-100">
              <span class="text-slate-400 font-semibold shrink-0">Giá trị lưu:</span>
              <span class="font-mono text-sky-700 font-semibold">{{ configFormState.value ?? '0' }}</span>
              <span class="text-slate-400 text-xs ml-auto">({{ getRegistrationStatusName(configFormState.value) }})</span>
            </div>
          </div>

          <div v-else class="flex flex-col gap-1">
            <label>Giá Trị</label>
            <div class="relative">
              <input type="text" v-model="configFormState.value" placeholder="1 hoặc 0 hoặc bỏ trống"
                class="w-full border border-slate-200 rounded-lg p-2 pr-7 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] bg-white" />
              <button v-if="configFormState.value" @click="configFormState.value = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label>Mô Tả</label>
            <div class="relative">
              <textarea v-model="configFormState.description" rows="3" placeholder="Chi tiết mô tả chức năng..."
                class="w-full border border-slate-200 rounded-lg p-2 pr-7 focus:outline-none focus:border-sky-500 text-xs font-normal text-[#000000D9] resize-none bg-white"></textarea>
              <button v-if="configFormState.description" @click="configFormState.description = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveConfig" class="btn-pms-primary">
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
