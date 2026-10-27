<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const branches = ref([])
const searchBranchQuery = ref('')
const isSyncingCrm = ref(false)

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

const isEditMode = ref(false)
const isBranchModalOpen = ref(false)
const branchFormState = reactive({
  id: null,
  code: '',
  name: '',
  api_url: '',
  api_report_url: '',
  is_master: false
})

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isBranchModalOpen.value) {
    isBranchModalOpen.value = false
  }
}

const fetchBranches = async () => {
  loading.value = true
  try {
    const res = await http.get('/branches-total')
    if (res.data && res.data.data) {
      branches.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách chi nhánh:', err)
  } finally {
    loading.value = false
  }
}

const openAddBranchModal = () => {
  modalPos.value = { x: 0, y: 0 }
  isEditMode.value = false
  Object.assign(branchFormState, {
    id: null,
    code: '',
    name: '',
    api_url: '',
    api_report_url: '',
    is_master: false
  })
  isBranchModalOpen.value = true
}

const openEditBranchModal = (branch) => {
  modalPos.value = { x: 0, y: 0 }
  isEditMode.value = true
  Object.assign(branchFormState, {
    id: branch.id,
    code: branch.code,
    name: branch.name,
    api_url: branch.api_url,
    api_report_url: branch.api_report_url,
    is_master: !!branch.is_master
  })
  isBranchModalOpen.value = true
}

const saveBranch = async () => {
  if (!branchFormState.code.trim() || !branchFormState.name.trim()) {
    uiStore.showToast('Vui lòng nhập mã và tên chi nhánh', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/branches-total/${branchFormState.id}`, branchFormState)
      uiStore.showToast('Cập nhật chi nhánh thành công!', 'success')
    } else {
      await http.post('/branches-total', branchFormState)
      uiStore.showToast('Thêm chi nhánh mới thành công!', 'success')
    }
    isBranchModalOpen.value = false
    fetchBranches()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu chi nhánh'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteBranch = async (branchId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa chi nhánh này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/branches-total/${branchId}`)
    uiStore.showToast('Xóa chi nhánh thành công!', 'success')
    fetchBranches()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa chi nhánh này', 'error')
  } finally {
    loading.value = false
  }
}

const toggleBranchMaster = async (branch) => {
  loading.value = true
  try {
    const updatedVal = !branch.is_master
    await http.put(`/branches-total/${branch.id}`, {
      ...branch,
      is_master: updatedVal
    })
    uiStore.showToast('Cập nhật chi nhánh Master thành công!', 'success')
    fetchBranches()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật chi nhánh Master', 'error')
  } finally {
    loading.value = false
  }
}

const syncCrm = async () => {
  isSyncingCrm.value = true
  uiStore.showToast('Đang kết nối đồng bộ CRM...', 'info')
  setTimeout(() => {
    isSyncingCrm.value = false
    uiStore.showToast('Đồng bộ CRM thành công!', 'success')
  }, 1500)
}

onMounted(() => {
  fetchBranches()
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
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
      <div class="flex items-center gap-2">
        <button @click="openAddBranchModal" class="btn-pms-primary">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>Thêm Chi Nhánh</span>
        </button>
        <button @click="syncCrm" :disabled="isSyncingCrm" class="btn-pms-secondary">
          <span>Đồng Bộ CRM</span>
        </button>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchBranchQuery" placeholder="Tìm kiếm chi nhánh..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-8 h-8 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <button v-if="searchBranchQuery" @click="searchBranchQuery = ''"
          class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Mã Chi Nhánh</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[180px]">Tên Chi Nhánh</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">API Link</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">API Report Link</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Master</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="b in branches.filter(item => !searchBranchQuery || (item.code && item.code.toLowerCase().includes(searchBranchQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchBranchQuery.toLowerCase())))"
            :key="b.id" @click="openEditBranchModal(b)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="p-2.5 font-normal text-[#000000D9] text-center">{{ b.code }}</td>
            <td class="p-2.5 font-normal text-[#000000D9]">{{ b.name }}</td>
            <td class="p-2.5 font-normal text-[#000000D9] text-xs break-all select-all">{{ b.api_url || '-' }}</td>
            <td class="p-2.5 font-normal text-[#000000D9] text-xs break-all select-all">{{ b.api_report_url || '-' }}</td>
            <td class="p-2.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="b.is_master" @change="toggleBranchMaster(b)"
                  class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-2.5 text-center">
              <div class="flex items-center justify-center">
                <button @click.stop="deleteBranch(b.id)"
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
          <tr v-if="branches.length === 0">
            <td colspan="6" class="p-6 text-center text-slate-400 italic">Chưa có thông tin chi nhánh nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT BRANCH -->
    <div v-if="isBranchModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Sửa Chi Nhánh' : 'Thêm Chi Nhánh' }}
          </h2>
          <button @mousedown.stop @click="isBranchModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-[#000000D9]">
          <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label>Mã Chi Nhánh <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="text" v-model="branchFormState.code" placeholder="HKT1"
                  class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                <button v-if="branchFormState.code" @click="branchFormState.code = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label>Tên Chi Nhánh <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="text" v-model="branchFormState.name" placeholder="HKT 1"
                  class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                <button v-if="branchFormState.name" @click="branchFormState.name = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label>Đường Dẫn Kết Nối API</label>
            <div class="relative">
              <input type="text" v-model="branchFormState.api_url"
                placeholder="https://hotel.hktsolution.vn/branches-total"
                class="w-full border border-slate-200 bg-white rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
              <button v-if="branchFormState.api_url" @click="branchFormState.api_url = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label>Đường Dẫn Kết Nối API Báo Cáo</label>
            <div class="relative">
              <input type="text" v-model="branchFormState.api_report_url"
                placeholder="https://hotel.hktsolution.vn/rppms1/"
                class="w-full border border-slate-200 bg-white rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
              <button v-if="branchFormState.api_report_url" @click="branchFormState.api_report_url = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>
          <div class="flex items-center justify-between border border-slate-100 rounded-lg p-3 bg-slate-50 mt-1">
            <span class="text-xs font-semibold text-[#000000D9]">Đặt Làm Chi Nhánh Chính (Is Master)</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="branchFormState.is_master" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveBranch" class="btn-pms-primary">
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
