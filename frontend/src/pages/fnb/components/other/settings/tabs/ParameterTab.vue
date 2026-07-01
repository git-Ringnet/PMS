<script setup>
import { ref } from 'vue'
import ConfirmDeleteModal from './ConfirmDeleteModal.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const parameters = ref([
  { id: 1, name: 'AllowCheckQRCode', value: '0', description: 'AllowCheckQRCode' },
  { id: 2, name: 'AllowEditBillAfterPrint', value: 'Admin,FBM,FOM,FO,ACC', description: 'AllowEditBillAfterPrint' },
  { id: 3, name: 'AllowEnterServiceCode', value: '1', description: 'AllowEnterServiceCode' },
  { id: 4, name: 'AllowFBNightAudit', value: '0', description: 'AllowFBNightAudit' },
  { id: 5, name: 'AllowPaymentTwoTaxGroup', value: '1', description: 'cho phép thanh toán bill có hai nhóm thuế' },
  { id: 6, name: 'API Language', value: 'https://lang.erateq.vn', description: 'API Language' },
  { id: 7, name: 'APP_PASSWORD', value: '123', description: 'APP_PASSWORD' },
])

const isDeleteModalOpen = ref(false)
const itemToDelete = ref(null)

const confirmDelete = (item) => {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

const handleDelete = () => {
  if (itemToDelete.value) {
    parameters.value = parameters.value.filter(p => p.id !== itemToDelete.value.id)
  }
  isDeleteModalOpen.value = false
  itemToDelete.value = null
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm">
    <!-- Actions -->
    <div class="flex items-center gap-4 mb-4">
      <button @click="uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Thêm
      </button>
    </div>

    <!-- Table -->
    <div class="border border-slate-200 rounded-lg overflow-hidden flex-1">
      <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-700 font-medium border-b border-slate-200">
          <tr>
            <th class="py-3 px-4">
              <div class="flex items-center gap-1">
                Tên
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="py-3 px-4">Giá trị</th>
            <th class="py-3 px-4">Mô tả</th>
            <th class="py-3 px-4 w-20 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in parameters" :key="item.id" class="hover:bg-slate-50/50 transition">
            <td class="py-3 px-4 font-medium">{{ item.name }}</td>
            <td class="py-3 px-4">{{ item.value }}</td>
            <td class="py-3 px-4">{{ item.description }}</td>
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
    <div class="flex items-center justify-end mt-4">
      <div class="flex items-center gap-1 text-sm border border-slate-200 rounded-md overflow-hidden bg-white shadow-sm">
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-400 disabled:opacity-50" disabled>&lt;</button>
        <button class="px-3 py-1.5 border-l border-r border-slate-200 bg-sky-50 text-sky-600 font-medium">1</button>
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-600 border-r border-slate-200">2</button>
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-600 border-r border-slate-200">3</button>
        <button class="px-3 py-1.5 hover:bg-slate-50 text-slate-600">&gt;</button>
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
