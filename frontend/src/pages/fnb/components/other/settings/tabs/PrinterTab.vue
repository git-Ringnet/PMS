<script setup>
import { ref, onMounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'
import AddPrinterModal from '../modals/AddPrinterModal.vue'
import ConfirmDeleteModal from './ConfirmDeleteModal.vue'

const uiStore = useUiStore()
const isLoading = ref(false)

const outlets = ref([])
const printers = ref([])
const activeGroup = ref('all') // 'all' đại diện cho "Máy In Chuyển Bán" (tất cả các máy in), hoặc ID outlet cụ thể

const showModal = ref(false)
const modalMode = ref('add')
const editingPrinter = ref(null)

// Confirm Delete Modal State
const confirmDeleteModal = ref({
  isOpen: false,
  title: 'Xác nhận xóa',
  message: '',
  action: null
})

const showConfirmDelete = (title, message, action) => {
  confirmDeleteModal.value = {
    isOpen: true,
    title,
    message,
    action
  }
}

const executeConfirmDelete = async () => {
  if (confirmDeleteModal.value.action) {
    await confirmDeleteModal.value.action()
  }
  confirmDeleteModal.value.isOpen = false
}

const fetchOutlets = async () => {
  try {
    const response = await http.get('/outlets')
    // Lọc các outlet đang hoạt động
    outlets.value = response.data.filter(o => o.is_active)
  } catch (error) {
    console.error('Lỗi khi lấy danh sách outlets:', error)
    uiStore.showToast('Không thể lấy danh sách Outlet', 'error')
  }
}

const fetchPrinters = async () => {
  try {
    let url = '/fb-printers'
    const params = {}
    if (activeGroup.value !== 'all') {
      params.outlet_id = activeGroup.value
    }
    const response = await http.get(url, { params })
    printers.value = response.data
  } catch (error) {
    console.error('Lỗi khi lấy danh sách máy in:', error)
    uiStore.showToast('Không thể lấy danh sách máy in', 'error')
  }
}

onMounted(async () => {
  isLoading.value = true
  await fetchOutlets()
  await fetchPrinters()
  isLoading.value = false
})

watch(activeGroup, async () => {
  isLoading.value = true
  await fetchPrinters()
  isLoading.value = false
})

const getPrinterTypeName = (type) => {
  switch (type) {
    case 1: return 'In chế biến, hủy món'
    case 2: return 'In hóa đơn, tạm tính'
    case 3: return 'In chuyển bàn'
    default: return 'Khác'
  }
}

const openAddModal = () => {
  modalMode.value = 'add'
  editingPrinter.value = null
  showModal.value = true
}

const openEditModal = (printer) => {
  modalMode.value = 'edit'
  editingPrinter.value = printer
  showModal.value = true
}

const handleSavePrinter = async (printerData) => {
  try {
    isLoading.value = true
    if (modalMode.value === 'edit' && printerData.id) {
      await http.put(`/fb-printers/${printerData.id}`, printerData)
      uiStore.showToast('Cập nhật máy in thành công!', 'success')
    } else {
      await http.post('/fb-printers', printerData)
      uiStore.showToast('Thêm máy in thành công!', 'success')
    }
    showModal.value = false
    await fetchPrinters()
  } catch (error) {
    console.error('Lỗi khi lưu máy in:', error)
    uiStore.showToast('Lưu máy in thất bại!', 'error')
  } finally {
    isLoading.value = false
  }
}

const handleDeletePrinter = (id) => {
  showConfirmDelete(
    'Xóa máy in',
    'Bạn có chắc chắn muốn xóa máy in này?',
    async () => {
      try {
        isLoading.value = true
        await http.delete(`/fb-printers/${id}`)
        uiStore.showToast('Xóa máy in thành công!', 'success')
        await fetchPrinters()
      } catch (error) {
        console.error('Lỗi khi xóa máy in:', error)
        uiStore.showToast('Xóa máy in thất bại!', 'error')
      } finally {
        isLoading.value = false
      }
    }
  )
}
</script>

<template>
  <div class="flex-1 flex gap-6 overflow-hidden min-h-0 w-full text-xs relative">
    <!-- Loading overlay -->
    <div v-if="isLoading" class="absolute inset-0 z-50 flex items-center justify-center bg-white/50 backdrop-blur-xs">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- Left sidebar -->
    <div class="w-56 bg-white rounded-xl border border-slate-200/80 p-0 shadow-sm flex flex-col shrink-0 overflow-hidden">
      <!-- Special Tab: Máy In Chuyến Bán (Displays all printers sorted by outlet) -->
      <button
        @click="activeGroup = 'all'"
        class="w-full text-left px-4 py-3 border-b border-slate-200 transition relative overflow-hidden font-bold"
        :class="activeGroup === 'all' ? 'bg-[#bfeaf9] text-sky-800' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
      >
        <span>Máy In Chuyển Bán</span>
      </button>

      <div class="bg-slate-100 px-4 py-2 border-b border-slate-200 shrink-0">
        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Outlets</span>
      </div>
      
      <div class="flex-1 overflow-y-auto flex flex-col p-1 gap-0.5">
        <button
          v-for="outlet in outlets"
          :key="outlet.id"
          @click="activeGroup = outlet.id"
          class="w-full text-left px-3 py-2 rounded-lg transition relative overflow-hidden font-semibold"
          :class="activeGroup === outlet.id ? 'bg-[#bfeaf9]/60 text-sky-700 font-bold' : 'text-slate-650 hover:bg-slate-50'"
        >
          <span>{{ outlet.name }}</span>
        </button>
      </div>
    </div>

    <!-- Right content area -->
    <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative">
      <!-- Toolbar -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0">
        <h4 class="font-bold text-slate-700">
          Danh sách máy in - {{ activeGroup === 'all' ? 'Tất cả' : (outlets.find(o => o.id === activeGroup)?.name || '') }}
        </h4>
        <button @click="openAddModal" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md font-medium transition cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          Thêm máy in
        </button>
      </div>

      <!-- Table view -->
      <div class="flex-1 overflow-auto flex flex-col">
        <table class="w-full border-collapse whitespace-nowrap text-left text-slate-600">
          <thead class="sticky top-0 z-10 bg-slate-50">
            <tr class="border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
              <th class="px-5 py-3 w-16">Mã</th>
              <th class="px-5 py-3">Outlet</th>
              <th class="px-5 py-3">Tên máy In</th>
              <th class="px-5 py-3">Loại máy in</th>
              <th class="px-5 py-3">Printer Driver Name</th>
              <th class="px-5 py-3 text-center">Số lần in</th>
              <th class="px-5 py-3 text-center w-24">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(printer, idx) in printers" :key="printer.id" class="border-b border-slate-150 hover:bg-slate-50 transition">
              <td class="px-5 py-3 font-semibold text-slate-500">#{{ idx + 1 }}</td>
              <td class="px-5 py-3 font-semibold text-slate-700">{{ printer.outlet?.name || 'N/A' }}</td>
              <td class="px-5 py-3 font-semibold text-slate-800">{{ printer.name }}</td>
              <td class="px-5 py-3 text-slate-600">{{ getPrinterTypeName(printer.type) }}</td>
              <td class="px-5 py-3 font-mono text-slate-600">{{ printer.driver_name || 'N/A' }}</td>
              <td class="px-5 py-3 text-center font-bold text-slate-700">{{ printer.num_of_prints }}</td>
              <td class="px-5 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button @click="openEditModal(printer)" class="p-1 text-sky-500 hover:bg-sky-50 rounded transition cursor-pointer" title="Sửa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                  <button @click="handleDeletePrinter(printer.id)" class="p-1 text-rose-500 hover:bg-rose-50 rounded transition cursor-pointer" title="Xóa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Empty State -->
        <div v-if="printers.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-slate-300">
          <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide">Trống</span>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <AddPrinterModal 
      :show="showModal"
      :mode="modalMode"
      :printer="editingPrinter"
      :outlets="outlets"
      :selected-outlet-id="activeGroup === 'all' ? '' : activeGroup"
      @close="showModal = false"
      @save="handleSavePrinter"
    />

    <ConfirmDeleteModal
      :is-open="confirmDeleteModal.isOpen"
      :title="confirmDeleteModal.title"
      :message="confirmDeleteModal.message"
      @close="confirmDeleteModal.isOpen = false"
      @confirm="executeConfirmDelete"
    />
  </div>
</template>

