<script setup>
import { ref } from 'vue'
import ConfirmDeleteModal from './ConfirmDeleteModal.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const paymentMethods = ref([
  { id: 1, code: 'CL', name: 'Complimentary', account: '', accountName: '', bankName: 'Complimentary', serviceFee: '0', department: '', isFree: true, isUnused: true },
  { id: 2, code: 'CA', name: 'Cash', account: '', accountName: '', bankName: 'Cash', serviceFee: '0', department: '', isFree: false, isUnused: false },
  { id: 3, code: 'AC', name: 'City ledger', account: '', accountName: '', bankName: 'City ledger', serviceFee: '0', department: '', isFree: false, isUnused: false },
  { id: 4, code: 'BT', name: 'Bank transfer', account: '', accountName: '', bankName: 'Bank transfer', serviceFee: '0', department: 'Reception/ Lễ Tân,\nRestaurant/Nhà Hàng', isFree: false, isUnused: false },
  { id: 5, code: 'VC', name: 'Voucher', account: '', accountName: '', bankName: 'Voucher', serviceFee: '0', department: ',', isFree: false, isUnused: false },
  { id: 6, code: 'CD', name: 'Credit Card', account: '', accountName: '', bankName: 'Credit Card', serviceFee: '0', department: ',', isFree: false, isUnused: false }
])

const allowSort = ref(false)

const isDeleteModalOpen = ref(false)
const itemToDelete = ref(null)

const confirmDelete = (item) => {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

const handleDelete = () => {
  if (itemToDelete.value) {
    paymentMethods.value = paymentMethods.value.filter(p => p.id !== itemToDelete.value.id)
  }
  isDeleteModalOpen.value = false
  itemToDelete.value = null
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm overflow-x-auto">
    <!-- Actions -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-4">
        <button @click="uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          Thêm
        </button>
        <div class="flex items-center gap-2 bg-slate-200/50 px-3 py-1.5 rounded-full cursor-pointer hover:bg-slate-200 transition" @click="allowSort = !allowSort">
          <div class="relative w-8 h-4 bg-slate-300 rounded-full transition-colors duration-200" :class="{'bg-sky-400': allowSort}">
            <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-4': allowSort}"></div>
          </div>
          <span class="text-sm text-slate-600 font-medium select-none">Don't allow sort</span>
        </div>
      </div>
      
      <button class="p-1.5 text-slate-600 hover:text-slate-900 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
      </button>
    </div>

    <!-- Table -->
    <div class="border border-slate-200 rounded-lg overflow-x-auto min-h-[300px]">
      <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap min-w-max">
        <thead class="bg-slate-50 text-slate-700 font-medium border-b border-slate-200">
          <tr>
            <th class="py-3 px-4">
              <div class="flex items-center gap-1">
                Code
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="py-3 px-4">Tên</th>
            <th class="py-3 px-4 text-center">Tài khoản</th>
            <th class="py-3 px-4 text-center">Tên tài khoản</th>
            <th class="py-3 px-4">Tên ngân hàng</th>
            <th class="py-3 px-4 text-center">Phí phục vụ</th>
            <th class="py-3 px-4">Bộ phận</th>
            <th class="py-3 px-4 text-center">HT Miễn phí</th>
            <th class="py-3 px-4 text-center">Không sử dụng</th>
            <th class="py-3 px-4 text-center w-20">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="item in paymentMethods" :key="item.id" class="hover:bg-slate-50/50 transition">
            <td class="py-3 px-4 font-medium">{{ item.code }}</td>
            <td class="py-3 px-4">{{ item.name }}</td>
            <td class="py-3 px-4 text-center">{{ item.account }}</td>
            <td class="py-3 px-4 text-center">{{ item.accountName }}</td>
            <td class="py-3 px-4">{{ item.bankName }}</td>
            <td class="py-3 px-4 text-center">{{ item.serviceFee }}</td>
            <td class="py-3 px-4 whitespace-pre-line text-[13px] leading-snug">{{ item.department }}</td>
            <td class="py-3 px-4">
              <div class="flex justify-center">
                <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': item.isFree}" @click="item.isFree = !item.isFree">
                  <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': item.isFree}"></div>
                </div>
              </div>
            </td>
            <td class="py-3 px-4">
              <div class="flex justify-center">
                <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': item.isUnused}" @click="item.isUnused = !item.isUnused">
                  <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': item.isUnused}"></div>
                </div>
              </div>
            </td>
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
