<script setup>
import { ref } from 'vue'
import ConfirmDeleteModal from './ConfirmDeleteModal.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const invoiceConfigs = ref([
  { id: 1, formNo: '1/003', serial: 'C26MGL', invoiceNumber: '00003030', length: 8, function: 'POS,PMS', action: ',', position: 1 }
])

const isDeleteModalOpen = ref(false)
const itemToDelete = ref(null)

const confirmDelete = (item) => {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

const handleDelete = () => {
  if (itemToDelete.value) {
    invoiceConfigs.value = invoiceConfigs.value.filter(p => p.id !== itemToDelete.value.id)
  }
  isDeleteModalOpen.value = false
  itemToDelete.value = null
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm overflow-x-auto">
    <!-- Actions -->
    <div class="flex items-center gap-4 mb-4">
      <button @click="uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Thêm
      </button>
    </div>

    <!-- Table -->
    <div class="border border-slate-200 rounded-lg overflow-x-auto flex-1">
      <table class="w-full text-left text-sm text-slate-600 min-w-max">
        <thead class="bg-slate-50 text-slate-700 font-medium border-b border-slate-200">
          <tr>
            <th class="py-3 px-4">Form No</th>
            <th class="py-3 px-4">Serial</th>
            <th class="py-3 px-4">Invoice Number</th>
            <th class="py-3 px-4">Invoice Number Length</th>
            <th class="py-3 px-4">Function</th>
            <th class="py-3 px-4">Action</th>
            <th class="py-3 px-4 text-center">Position</th>
            <th class="py-3 px-4 text-center w-20">Delete</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in invoiceConfigs" :key="item.id" class="hover:bg-slate-50/50 transition">
            <td class="py-3 px-4 font-medium">{{ item.formNo }}</td>
            <td class="py-3 px-4">{{ item.serial }}</td>
            <td class="py-3 px-4">{{ item.invoiceNumber }}</td>
            <td class="py-3 px-4 text-center">{{ item.length }}</td>
            <td class="py-3 px-4">{{ item.function }}</td>
            <td class="py-3 px-4">{{ item.action }}</td>
            <td class="py-3 px-4 text-center">{{ item.position }}</td>
            <td class="py-3 px-4 text-center">
              <button @click="confirmDelete(item)" class="p-1.5 bg-[#78C5E7] hover:bg-sky-500 text-white rounded transition" title="Xóa">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-end mt-4 shrink-0">
      <div class="flex items-center gap-1 text-sm border border-slate-200 rounded-md overflow-hidden bg-white shadow-sm">
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-400 disabled:opacity-50" disabled>&lt;</button>
        <button class="px-3 py-1.5 border-l border-r border-slate-200 bg-sky-50 text-sky-600 font-medium">1</button>
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-600 border-r border-slate-200">&gt;</button>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <ConfirmDeleteModal 
      :is-open="isDeleteModalOpen" 
      @close="isDeleteModalOpen = false"
      @confirm="handleDelete"
    />
  </div>
</template>
