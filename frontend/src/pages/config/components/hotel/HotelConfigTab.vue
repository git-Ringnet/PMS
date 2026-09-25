<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { fetchRoles } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const hotelConfigs = ref([])
const searchConfigQuery = ref('')
const roles = ref([])
const oldDayRuleConfigKey = 'RuleUserCorrectOrPostBillPaymentOldDay'

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
      bcNotify.postMessage('settings-updated')
      bcNotify.close()
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

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
  fetchHotelConfigs()
  fetchRoleOptions()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleClickOutside)
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

    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <button @click="openAddConfigModal"
        class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Thêm cấu hình
      </button>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchConfigQuery" placeholder="Tìm kiếm cấu hình..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th class="p-3">Tên cấu hình</th>
            <th class="p-3">Giá trị</th>
            <th class="p-3">Mô tả</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="cfg in hotelConfigs.filter(item => !searchConfigQuery || (item.name && item.name.toLowerCase().includes(searchConfigQuery.toLowerCase())) || (item.description && item.description.toLowerCase().includes(searchConfigQuery.toLowerCase())))"
            :key="cfg.id" @click="openEditConfigModal(cfg)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ cfg.name }}</td>
            <td class="p-3 font-bold text-sky-700 font-mono">{{ cfg.value || '-' }}</td>
            <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed max-w-xs">{{ cfg.description || '-' }}</td>
            <td class="p-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click.stop="deleteConfig(cfg.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa cấu hình' : 'Thêm cấu hình' }}</h2>
          <button @click="isConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên cấu hình (Key)*</span>
            <input type="text" v-model="configFormState.name" :disabled="isEditMode"
              placeholder="AllowChangeRoomStatus..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm disabled:bg-slate-100 disabled:cursor-not-allowed" />
          </div>
          <!-- Khi cấu hình là loại phân quyền RoleUser... -->
          <div v-if="isRoleConfig(configFormState.name)" class="flex flex-col gap-1.5 relative" ref="roleDropdownRef">
            <div class="flex items-center justify-between">
              <span>Giá trị (Vai trò / Role được cấp quyền)</span>
              <span class="text-xs font-semibold text-slate-400">Đã chọn: {{ selectedRoleCodes.length }}</span>
            </div>

            <!-- Custom Multi-select Dropdown Trigger -->
            <div
              @click="roleDropdownOpen = !roleDropdownOpen"
              class="border border-slate-200 rounded-lg p-2 bg-white hover:border-sky-400 cursor-pointer flex items-center justify-between min-h-[42px] transition-all shadow-2xs"
              :class="roleDropdownOpen ? 'border-sky-500 ring-2 ring-sky-100' : ''"
            >
              <div class="flex flex-wrap gap-1.5 items-center flex-1 pr-2">
                <span v-if="selectedRoleCodes.length === 0" class="text-slate-400 text-xs font-normal">
                  -- Nhấp để chọn vai trò được phép (Admin, FO, FOM,...) --
                </span>
                <span
                  v-for="code in selectedRoleCodes"
                  :key="code"
                  class="inline-flex items-center gap-1 bg-sky-50 text-sky-700 border border-sky-200 rounded-md px-2 py-0.5 text-xs font-bold"
                  @click.stop
                >
                  {{ code }}
                  <button
                    type="button"
                    @click.stop="removeRole(code)"
                    class="hover:text-red-500 font-bold text-sm leading-none bg-transparent border-none cursor-pointer p-0 text-slate-400"
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
                <input
                  type="text"
                  v-model="roleSearchQuery"
                  placeholder="Tìm vai trò (mã, tên)..."
                  class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-medium focus:outline-sky-500"
                  @click.stop
                />
                <button
                  type="button"
                  @click.stop="selectAllRoles"
                  class="text-xs text-sky-600 hover:text-sky-800 font-bold bg-sky-50 hover:bg-sky-100 px-2 py-1 rounded border-none cursor-pointer whitespace-nowrap"
                >Chọn hết</button>
                <button
                  type="button"
                  @click.stop="clearAllRoles"
                  class="text-xs text-slate-500 hover:text-slate-700 font-bold bg-slate-100 hover:bg-slate-200 px-2 py-1 rounded border-none cursor-pointer whitespace-nowrap"
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
                    class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 border-slate-300 cursor-pointer"
                  />
                  <div class="flex items-center gap-1.5 flex-1 text-xs">
                    <span class="font-bold text-slate-800 font-mono">{{ opt.code }}</span>
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
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold border-none cursor-pointer"
                >+ Thêm</button>
              </div>
            </div>

            <!-- Value preview / instruction -->
            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-medium bg-slate-50 p-2 rounded-lg border border-slate-100">
              <span class="text-slate-400 font-semibold shrink-0">Giá trị lưu:</span>
              <span class="font-mono text-sky-700 font-bold break-all">{{ configFormState.value || '(Trống)' }}</span>
            </div>
          </div>
          <div v-else class="flex flex-col gap-1.5">
            <span>Giá trị</span>
            <input type="text" v-model="configFormState.value" placeholder="1 hoặc 0 hoặc bỏ trống"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Mô tả</span>
            <textarea v-model="configFormState.description" rows="3" placeholder="Chi tiết mô tả chức năng..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none"></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isConfigModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveConfig"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu cấu hình
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
