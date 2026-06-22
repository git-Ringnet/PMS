<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const branches = ref([])
const searchBranchQuery = ref('')
const isSyncingCrm = ref(false)

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
  if (!branchFormState.code || !branchFormState.name) {
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
      <div class="flex items-center gap-2">
        <button @click="openAddBranchModal"
          class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Thêm chi nhánh
        </button>
        <button @click="syncCrm" :disabled="isSyncingCrm"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200/80 disabled:opacity-50 text-slate-600 rounded-lg text-sm font-bold flex items-center gap-1.5 border border-slate-200 cursor-pointer transition-colors shadow-2xs">
          Đồng bộ CRM
        </button>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchBranchQuery" placeholder="Tìm kiếm chi nhánh..."
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
            <th class="p-3">Mã CN</th>
            <th class="p-3">Tên chi nhánh</th>
            <th class="p-3">API Link</th>
            <th class="p-3">API Report Link</th>
            <th class="p-3 text-center">Master</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="b in branches.filter(item => !searchBranchQuery || (item.code && item.code.toLowerCase().includes(searchBranchQuery.toLowerCase())) || (item.name && item.name.toLowerCase().includes(searchBranchQuery.toLowerCase())))"
            :key="b.id" @click="openEditBranchModal(b)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ b.code }}</td>
            <td class="p-3 font-bold text-slate-700">{{ b.name }}</td>
            <td class="p-3 font-semibold text-sky-700 text-xs break-all select-all">{{ b.api_url || '-' }}</td>
            <td class="p-3 font-semibold text-sky-700 text-xs break-all select-all">{{ b.api_report_url || '-' }}</td>
            <td class="p-3 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="b.is_master" @change="toggleBranchMaster(b)"
                  class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="p-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click.stop="deleteBranch(b.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Sửa' : 'Thêm' }}</h2>
          <button @click="isBranchModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Mã chi nhánh*</span>
              <input type="text" v-model="branchFormState.code" placeholder="HKT1"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Tên chi nhánh*</span>
              <input type="text" v-model="branchFormState.name" placeholder="HKT 1"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>API Connection URL</span>
            <input type="text" v-model="branchFormState.api_url"
              placeholder="https://hotel.hktsolution.vn/branches-total"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>API Report Connection URL</span>
            <input type="text" v-model="branchFormState.api_report_url"
              placeholder="https://hotel.hktsolution.vn/rppms1/"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex items-center justify-between border border-slate-100 rounded-lg p-3 bg-slate-50 mt-2">
            <span>Đặt làm chi nhánh chính (Is Master)</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="branchFormState.is_master" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-white px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
          <button @click="isBranchModalOpen = false"
            class="px-5 py-2.5 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm cursor-pointer transition-colors border-none flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-6 6M9 9l6 6" />
            </svg>
            Đóng
          </button>
          <button @click="saveBranch"
            class="px-5 py-2.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-sm transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8l-4-4H8z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 100-6 3 3 0 000 6z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 4v4h6V4" />
            </svg>
            Lưu
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
