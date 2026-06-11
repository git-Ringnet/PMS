<script setup>
import { ref, onMounted } from 'vue'
import { fetchMarkets, createMarket, updateMarket, deleteMarket } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const markets = ref([])
const loading = ref(false)

// Modal state
const isModalOpen = ref(false)
const isEditMode = ref(false)
const currentId = ref(null)
const form = ref({ code: '', name: '' })

onMounted(() => {
  loadData()
  document.addEventListener('click', closeAllPopovers)
})

import { onBeforeUnmount, computed } from 'vue'

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
})

const searchQueryCode = ref('')
const searchQueryName = ref('')
const isSearchCodeOpen = ref(false)
const isSearchNameOpen = ref(false)
const sortField = ref('id') // Default sorting by ID
const sortDir = ref('asc')

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isSearchCodeOpen.value = false
    isSearchNameOpen.value = false
  }
}

const toggleSort = () => {
  sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
}

const filteredAndSortedMarkets = computed(() => {
  let result = [...markets.value]

  // Filter Code
  if (searchQueryCode.value.trim()) {
    const q = searchQueryCode.value.toLowerCase().trim()
    result = result.filter(m => m.code && m.code.toLowerCase().includes(q))
  }

  // Filter Name
  if (searchQueryName.value.trim()) {
    const q = searchQueryName.value.toLowerCase().trim()
    result = result.filter(m => m.name && m.name.toLowerCase().includes(q))
  }

  // Sort
  if (sortField.value === 'id') {
    const dir = sortDir.value === 'asc' ? 1 : -1
    result.sort((a, b) => (a.id - b.id) * dir)
  }

  return result
})

const displayedMarkets = computed(() => filteredAndSortedMarkets.value)

const loadData = async () => {
  loading.value = true
  try {
    const res = await fetchMarkets()
    markets.value = res.data.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  isEditMode.value = false
  currentId.value = null
  form.value = { code: '', name: '' }
  isModalOpen.value = true
}

const openEditModal = (item) => {
  isEditMode.value = true
  currentId.value = item.id
  form.value = { code: item.code, name: item.name }
  isModalOpen.value = true
}

const saveItem = async () => {
  if (!form.value.name) {
    uiStore.showToast('Vui lòng nhập tên thị trường', 'warning')
    return
  }
  if (!form.value.code) {
    uiStore.showToast('Vui lòng nhập mã thị trường', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await updateMarket(currentId.value, form.value)
      uiStore.showToast('Cập nhật thị trường thành công!', 'success')
    } else {
      await createMarket(form.value)
      uiStore.showToast('Thêm thị trường thành công!', 'success')
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
    message: `Bạn có chắc chắn muốn xóa thị trường "${item.name}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    await deleteMarket(item.id)
    uiStore.showToast('Xóa thị trường thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa thị trường này', 'error')
  }
}
</script>

<template>
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs">
    <!-- Toolbar -->
    <div class="flex items-center mb-3">
      <button 
        @click="openAddModal"
        class="px-3.5 py-1.5 bg-[#5fa5e6] hover:bg-[#4d92d4] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-xs transition-colors"
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
            <!-- ID -->
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase w-20 cursor-pointer hover:bg-slate-200 select-none transition-colors" @click="toggleSort">
              <div class="flex items-center justify-between gap-1">
                <span>ID</span>
                <span class="flex flex-col text-[9px] leading-[6px] text-slate-400">
                  <span :class="{'text-sky-500': sortField === 'id' && sortDir === 'asc'}">▲</span>
                  <span :class="{'text-sky-500': sortField === 'id' && sortDir === 'desc'}">▼</span>
                </span>
              </div>
            </th>

            <!-- Mã -->
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Mã</span>
                <button 
                  @click.stop="isSearchCodeOpen = !isSearchCodeOpen; isSearchNameOpen = false" 
                  class="p-1 hover:bg-slate-200 rounded text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center transition-colors"
                  :class="{'text-sky-500 bg-sky-50 hover:bg-sky-100': searchQueryCode}"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </button>
              </div>
              <!-- Search Popover -->
              <div v-if="isSearchCodeOpen" class="absolute left-0 top-full mt-1.5 z-30 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[200px] normal-case font-normal text-slate-700">
                <div class="relative flex items-center">
                  <input 
                    v-model="searchQueryCode" 
                    type="text" 
                    placeholder="Tìm kiếm mã..." 
                    class="w-full border border-slate-200 rounded-md p-1.5 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white" 
                    @click.stop
                  />
                  <button 
                    v-if="searchQueryCode" 
                    @click.stop="searchQueryCode = ''" 
                    class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </th>

            <!-- Tên -->
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase relative popover-container select-none">
              <div class="flex items-center justify-between gap-1.5">
                <span>Tên</span>
                <button 
                  @click.stop="isSearchNameOpen = !isSearchNameOpen; isSearchCodeOpen = false" 
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

            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase text-center w-24">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="item in displayedMarkets" 
            :key="item.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors"
            @dblclick="openEditModal(item)"
          >
            <td class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.id }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-800 font-normal">{{ item.code }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-700 font-normal">{{ item.name }}</td>
            <td class="p-2 border-r border-slate-200 text-center">
              <button 
                @click.stop="handleDelete(item)"
                class="p-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="displayedMarkets.length === 0 && !loading">
            <td colspan="4" class="p-8 text-center text-slate-400 text-xs font-semibold">Chưa có dữ liệu thị trường</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="markets.length > 0" class="flex items-center justify-end mt-3 gap-1 select-none">
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
    <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100 animate-in">
      <!-- Modal Header -->
      <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white select-none">
        <h2 class="text-xs font-bold uppercase tracking-wider">{{ isEditMode ? 'Sửa thị trường' : 'Thêm thị trường' }}</h2>
        <button 
          @click="isModalOpen = false" 
          class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-5 flex flex-col gap-3 text-xs">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Mã *</label>
          <input 
            v-model="form.code" 
            type="text" 
            placeholder="Nhập mã thị trường..."
            class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-bold focus:outline-sky-500 text-xs uppercase"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tên *</label>
          <input 
            v-model="form.name" 
            type="text" 
            placeholder="Nhập tên thị trường..."
            class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs"
          />
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
        <!-- Nút Đóng -->
        <button 
          @click="isModalOpen = false" 
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors"
        >
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-[8px] font-extrabold">✕</span>
          Đóng
        </button>
        <!-- Nút Lưu -->
        <button 
          @click="saveItem"
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 shadow-xs transition-colors"
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
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
