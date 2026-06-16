<script setup>
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { fetchSystemBranches, createSystemBranch, updateSystemBranch, deleteSystemBranch } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const branches = ref([])
const loading = ref(false)

// Search & filter states
const globalSearchQuery = ref('')
const activeSearch = ref('')

const sortField = ref('id') // Default sort
const sortDir = ref('desc')

// Modal state
const isModalOpen = ref(false)
const isEditMode = ref(false)
const currentId = ref(null)
const emptyForm = () => ({
  code: '',
  name: '',
  tax_code: '',
  email: '',
  phone: '',
  address: '',
  accounting_month: 11,
  accounting_year: 2024,
  is_active: true
})
const form = ref(emptyForm())

onMounted(() => {
  loadData()
})

const loadData = async () => {
  loading.value = true
  try {
    const res = await fetchSystemBranches({ search: activeSearch.value })
    branches.value = res.data.data || []
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải danh sách chi nhánh', 'error')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  activeSearch.value = globalSearchQuery.value
  loadData()
}

const handleClearSearch = () => {
  globalSearchQuery.value = ''
  activeSearch.value = ''
  loadData()
}

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'desc'
  }
}

// Columns metadata
const columns = ref([
  { id: 'code', label: 'Mã Chi Nhánh', visible: true, sortable: true },
  { id: 'name', label: 'Tên Chi Nhánh', visible: true, sortable: true },
  { id: 'tax_code', label: 'Mã Số Thuế', visible: true, sortable: false },
  { id: 'email', label: 'Email', visible: true, sortable: false },
  { id: 'phone', label: 'Điện Thoại', visible: true, sortable: false },
  { id: 'address', label: 'Địa Chi', visible: true, sortable: false },
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

const sortedBranches = computed(() => {
  let result = [...branches.value]
  
  if (sortField.value) {
    const field = sortField.value
    const dir = sortDir.value === 'asc' ? 1 : -1
    result.sort((a, b) => {
      let valA = a[field] || ''
      let valB = b[field] || ''
      if (typeof valA === 'string') valA = valA.toLowerCase()
      if (typeof valB === 'string') valB = valB.toLowerCase()
      
      if (valA < valB) return -1 * dir
      if (valA > valB) return 1 * dir
      return 0
    })
  }
  return result
})

const openAddModal = () => {
  isEditMode.value = false
  currentId.value = null
  form.value = emptyForm()
  isModalOpen.value = true
}

const openEditModal = (item) => {
  isEditMode.value = true
  currentId.value = item.id
  form.value = {
    code: item.code || '',
    name: item.name || '',
    tax_code: item.tax_code || '',
    email: item.email || '',
    phone: item.phone || '',
    address: item.address || '',
    accounting_month: item.accounting_month !== undefined ? item.accounting_month : 11,
    accounting_year: item.accounting_year !== undefined ? item.accounting_year : 2024,
    is_active: item.is_active !== undefined ? item.is_active : true
  }
  isModalOpen.value = true
}

const saveItem = async () => {
  if (!form.value.code) {
    uiStore.showToast('Vui lòng nhập mã chi nhánh', 'warning')
    return
  }
  if (!form.value.name) {
    uiStore.showToast('Vui lòng nhập tên chi nhánh', 'warning')
    return
  }
  
  loading.value = true
  try {
    if (isEditMode.value) {
      await updateSystemBranch(currentId.value, form.value)
      uiStore.showToast('Cập nhật chi nhánh thành công!', 'success')
    } else {
      await createSystemBranch(form.value)
      uiStore.showToast('Thêm chi nhánh mới thành công!', 'success')
    }
    isModalOpen.value = false
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu chi nhánh'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

const handleDelete = async (item) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc chắn muốn xóa chi nhánh "${item.name}" (${item.code})?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  try {
    await deleteSystemBranch(item.id)
    uiStore.showToast('Xóa chi nhánh thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa chi nhánh này. Có thể chi nhánh đang liên kết với thông tin công ty.', 'error')
  } finally {
    loading.value = false
  }
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
            placeholder="Tìm kiếm theo mã, tên chi nhánh..." 
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
        <tbody>
          <tr 
            v-for="item in sortedBranches" 
            :key="item.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors font-medium"
            @dblclick="openEditModal(item)"
          >
            <!-- Code -->
            <td v-show="isColumnVisible('code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.code }}</td>
            <!-- Name -->
            <td v-show="isColumnVisible('name')" class="p-2 border-r border-slate-200 text-slate-700 font-bold">{{ item.name }}</td>
            <!-- Tax Code -->
            <td v-show="isColumnVisible('tax_code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.tax_code || '-' }}</td>
            <!-- Email -->
            <td v-show="isColumnVisible('email')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.email || '-' }}</td>
            <!-- Phone -->
            <td v-show="isColumnVisible('phone')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.phone || '-' }}</td>
            <!-- Address -->
            <td v-show="isColumnVisible('address')" class="p-2 border-r border-slate-200 text-slate-600 font-normal">{{ item.address || '-' }}</td>
            
            <!-- Actions -->
            <td class="p-2 border-r border-slate-200 text-center">
              <button 
                @click.stop="handleDelete(item)"
                class="p-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center w-6 h-6"
                title="Xóa chi nhánh"
              >
                <!-- SVG Delete Trash Can -->
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          
          <tr v-if="sortedBranches.length === 0 && !loading">
            <td :colspan="columns.filter(c => c.visible).length + 1" class="p-8 text-center text-slate-400 text-xs font-semibold">
              Chưa có dữ liệu chi nhánh
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination dummy mimicking layout -->
    <div v-if="sortedBranches.length > 0" class="flex items-center justify-end mt-3 gap-1 select-none shrink-0">
      <button class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40">&lt;</button>
      <button class="px-2.5 py-1 border border-sky-400 rounded text-xs text-sky-600 bg-sky-50 font-bold cursor-pointer">1</button>
      <button class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40">&gt;</button>
    </div>

    <!-- Modal Add/Edit -->
    <div 
      v-if="isModalOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs"
    >
      <div class="bg-white rounded-lg w-full max-w-2xl shadow-2xl overflow-hidden border border-slate-100 animate-in select-none">
        <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white border-b border-slate-200">
          <h2 class="text-sm font-bold tracking-wide">
            {{ isEditMode ? 'Chỉnh Sửa Chi Nhánh' : 'Thêm Chi Nhánh' }}
          </h2>
          <button @click="isModalOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
        </div>
        
        <div class="p-6 flex flex-col gap-4 text-xs max-h-[75vh] overflow-y-auto scrollbar-none">
          <!-- Row 1: Code and Name -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mã Chi Nhánh</label>
              <input 
                v-model="form.code" 
                type="text" 
                placeholder="Ví dụ: HKT4..." 
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
                :disabled="isEditMode"
              />
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tên Chi Nhánh</label>
              <input 
                v-model="form.name" 
                type="text" 
                placeholder="Nhập tên chi nhánh..." 
                class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" 
              />
            </div>
          </div>

          <!-- Row 2: Email and Phone -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Email</label>
              <input 
                v-model="form.email" 
                type="email" 
                placeholder="Nhập email..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white" 
              />
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Điện Thoại</label>
              <input 
                v-model="form.phone" 
                type="text" 
                placeholder="Nhập số điện thoại..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white" 
              />
            </div>
          </div>

          <!-- Row 3: Address -->
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-700">Địa Chỉ</label>
            <input 
              v-model="form.address" 
              type="text" 
              placeholder="Nhập địa chỉ chi nhánh..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white w-full" 
            />
          </div>

          <!-- Row 4: Kỳ kế toán and MST -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Kỳ Kế Toán</label>
              <div class="flex gap-2">
                <input 
                  v-model.number="form.accounting_month" 
                  type="number" 
                  placeholder="Tháng" 
                  min="1" max="12"
                  class="w-24 border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 text-center" 
                />
                <input 
                  v-model.number="form.accounting_year" 
                  type="number" 
                  placeholder="Năm" 
                  min="1900" max="2100"
                  class="w-32 border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 text-center" 
                />
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mã Số Thuế</label>
              <input 
                v-model="form.tax_code" 
                type="text" 
                placeholder="Mã số thuế..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white" 
              />
            </div>
          </div>
        </div>

        <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-t border-slate-100">
          <button 
            type="button"
            class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none shadow-xs transition-colors"
          >
            Nâng Cao
          </button>
          
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
