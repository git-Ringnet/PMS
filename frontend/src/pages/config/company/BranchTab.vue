<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { fetchBranches, createBranch, updateBranch, deleteBranch } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const branches = ref([])
const loading = ref(false)

// Modal state
const isModalOpen = ref(false)
const isEditMode = ref(false)
const currentId = ref(null)
const form = ref({ name: '' })
const modalPosition = ref({ x: 0, y: 0 })
let modalDragState = null

const startModalDrag = (event) => {
  if (event.button !== 0 || event.target.closest('button, input, select, textarea, label')) return
  event.preventDefault()
  modalDragState = {
    startX: event.clientX,
    startY: event.clientY,
    initX: modalPosition.value.x,
    initY: modalPosition.value.y,
  }
  window.addEventListener('mousemove', moveModalDrag)
  window.addEventListener('mouseup', stopModalDrag)
}

const moveModalDrag = (event) => {
  if (!modalDragState) return
  const { startX, startY, initX, initY } = modalDragState
  modalPosition.value = {
    x: initX + (event.clientX - startX),
    y: initY + (event.clientY - startY),
  }
}

const stopModalDrag = () => {
  modalDragState = null
  window.removeEventListener('mousemove', moveModalDrag)
  window.removeEventListener('mouseup', stopModalDrag)
}

const handleEscape = (e) => {
  if (e.key === 'Escape' && isModalOpen.value) {
    isModalOpen.value = false
  }
}

onMounted(() => {
  loadData()
  document.addEventListener('click', closeAllPopovers)
  window.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
  window.removeEventListener('keydown', handleEscape)
  stopModalDrag()
})

const searchQueryId = ref('')
const searchQueryName = ref('')
const isSearchIdOpen = ref(false)
const isSearchNameOpen = ref(false)
const sortField = ref('id') // Default sorting by ID
const sortDir = ref('asc')

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isSearchIdOpen.value = false
    isSearchNameOpen.value = false
  }
}

const toggleSort = () => {
  sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
}

const filteredAndSortedBranches = computed(() => {
  let result = [...branches.value]

  // Filter ID/Code
  if (searchQueryId.value.trim()) {
    const q = searchQueryId.value.toLowerCase().trim()
    result = result.filter(b => b.id && b.id.toString().toLowerCase().includes(q))
  }

  // Filter Name
  if (searchQueryName.value.trim()) {
    const q = searchQueryName.value.toLowerCase().trim()
    result = result.filter(b => b.name && b.name.toLowerCase().includes(q))
  }

  // Sort
  if (sortField.value === 'id') {
    const dir = sortDir.value === 'asc' ? 1 : -1
    result.sort((a, b) => (a.id - b.id) * dir)
  }

  return result
})

const displayedBranches = computed(() => filteredAndSortedBranches.value)

const loadData = async () => {
  loading.value = true
  try {
    const res = await fetchBranches()
    branches.value = res.data.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  isEditMode.value = false
  currentId.value = null
  form.value = { name: '' }
  modalPosition.value = { x: 0, y: 0 }
  isModalOpen.value = true
}

const openEditModal = (item) => {
  isEditMode.value = true
  currentId.value = item.id
  form.value = { name: item.name }
  modalPosition.value = { x: 0, y: 0 }
  isModalOpen.value = true
}

const saveItem = async () => {
  if (!form.value.name) {
    uiStore.showToast('Vui lòng nhập tên chi nhánh', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await updateBranch(currentId.value, form.value)
      uiStore.showToast('Cập nhật chi nhánh thành công!', 'success')
    } else {
      await createBranch(form.value)
      uiStore.showToast('Thêm chi nhánh thành công!', 'success')
    }
    isModalOpen.value = false
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Có lỗi xảy ra'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

const handleDelete = async (item) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc chắn muốn xóa chi nhánh "${item.name}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    await deleteBranch(item.id)
    uiStore.showToast('Xóa chi nhánh thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Không thể xóa chi nhánh này', 'error')
  }
}
</script>

<template>
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs">
    <!-- Toolbar -->
    <div class="flex items-center mb-3">
      <button 
        @click="openAddModal"
        class="btn-pms-primary"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Thêm
      </button>
    </div>

    <!-- Table -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-sm flex-1 max-h-full">
      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
            <!-- Mã / ID -->
            <th class="p-2 border border-slate-200 text-slate-700 font-semibold text-xs text-center align-middle whitespace-nowrap w-20 cursor-pointer hover:bg-slate-200 select-none relative popover-container transition-colors">
              <div class="flex items-center justify-center gap-1" @click="toggleSort">
                <span>Mã</span>
                <div class="flex items-center gap-1">
                  <span class="flex flex-col text-[9px] leading-[6px] text-slate-400">
                    <span :class="{'text-sky-500': sortField === 'id' && sortDir === 'asc'}">▲</span>
                    <span :class="{'text-sky-500': sortField === 'id' && sortDir === 'desc'}">▼</span>
                  </span>
                  <button 
                    @click.stop="isSearchIdOpen = !isSearchIdOpen; isSearchNameOpen = false" 
                    class="p-0.5 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                    :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueryId}"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </button>
                </div>
              </div>
              <!-- Search Popover -->
              <div v-if="isSearchIdOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                <div class="relative flex items-center">
                  <input 
                    v-model="searchQueryId" 
                    type="text" 
                    placeholder="Tìm kiếm mã..." 
                    class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                    @click.stop
                  />
                  <button 
                    v-if="searchQueryId" 
                    @click.stop="searchQueryId = ''" 
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </th>

            <!-- Tên -->
            <th class="p-2 border border-slate-200 text-slate-700 font-semibold text-xs text-center align-middle whitespace-nowrap relative popover-container select-none">
              <div class="flex items-center justify-center gap-1.5">
                <span>Tên Chi Nhánh</span>
                <button 
                  @click.stop="isSearchNameOpen = !isSearchNameOpen; isSearchIdOpen = false" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueryName}"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </button>
              </div>
              <!-- Search Popover -->
              <div v-if="isSearchNameOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                <div class="relative flex items-center">
                  <input 
                    v-model="searchQueryName" 
                    type="text" 
                    placeholder="Tìm kiếm tên..." 
                    class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                    @click.stop
                  />
                  <button 
                    v-if="searchQueryName" 
                    @click.stop="searchQueryName = ''" 
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </th>

            <th class="p-2 border border-slate-200 text-slate-700 font-semibold text-xs text-center align-middle whitespace-nowrap w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="item in displayedBranches" 
            :key="item.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors"
            @dblclick="openEditModal(item)"
          >
            <td class="p-2 text-slate-600 font-normal text-center whitespace-nowrap">{{ item.id }}</td>
            <td class="p-2 text-slate-700 font-normal whitespace-nowrap">{{ item.name }}</td>
            <td class="p-2 text-center whitespace-nowrap">
              <button 
                @click.stop="handleDelete(item)"
                class="w-7 h-7 bg-red-600 hover:bg-red-700 text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center shadow-xs"
                title="Xóa chi nhánh"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="displayedBranches.length === 0 && !loading">
            <td colspan="3" class="p-8 text-center text-slate-400 text-xs font-semibold">Chưa có dữ liệu chi nhánh</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="branches.length > 0" class="flex items-center justify-end mt-3 gap-1 select-none">
      <button class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40">&lt;</button>
      <button class="px-2.5 py-1 border border-sky-400 rounded text-xs text-sky-600 bg-sky-50 font-bold cursor-pointer">1</button>
      <button class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40">&gt;</button>
    </div>
  </div>

  <!-- Modal Add/Edit -->
  <div 
    v-if="isModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs"
  >
    <div 
      class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100 company-modal-fade"
      :style="{ transform: `translate(${modalPosition.x}px, ${modalPosition.y}px)` }"
    >
      <!-- Modal Header -->
      <div 
        @mousedown="startModalDrag"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }" 
        class="px-5 py-3 flex items-center justify-between text-white select-none cursor-move"
      >
        <h2 class="text-xs font-semibold tracking-wide">{{ isEditMode ? 'Sửa Chi Nhánh' : 'Thêm Chi Nhánh' }}</h2>
        <button 
          @mousedown.stop
          @click="isModalOpen = false" 
          title="Đóng (Esc)"
          class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <span class="font-semibold text-[#000000D9]">Tên Chi Nhánh<span class="text-red-500">*</span></span>
          <div class="relative">
            <input 
              v-model="form.name" 
              required
              aria-required="true"
              type="text" 
              placeholder="Nhập tên chi nhánh..." 
              class="input-required w-full h-8 rounded-lg px-2 pr-8 text-xs font-normal"
            />
            <button v-if="form.name" @click.stop="form.name = ''" type="button" class="input-clear-button" title="Xóa">✕</button>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <!-- Nút Lưu -->
        <button 
          @click="saveItem" 
          class="btn-pms-primary"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.company-modal-fade {
  animation: companyModalOpacityFade 0.12s ease-out;
}
@keyframes companyModalOpacityFade {
  from { opacity: 0; }
  to { opacity: 1; }
}
.input-clear-button {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  z-index: 10;
  transform: translateY(-50%);
  border: 0;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  font-size: 12px;
  line-height: 1;
}
.input-clear-button:hover { color: #475569; }
</style>
