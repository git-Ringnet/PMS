<script setup>
import { ref } from 'vue'
import { HelpCircle, X, Inbox } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  fromGuest: {
    type: String,
    default: 'Mr. Guest 1 - 602'
  }
})

const emit = defineEmits(['close'])

const selectedToGuest = ref('')

const handleClose = () => {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Chuyển dịch vụ</span>
        <div class="flex items-center gap-2">
          <button class="hover:text-gray-100"><HelpCircle class="w-4 h-4" /></button>
          <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
        </div>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-3">
        <!-- Controls: Từ khách | Đến khách -->
        <div class="flex items-center justify-between gap-6">
          <!-- Từ khách -->
          <div class="flex-1">
            <label class="block font-bold text-gray-700 mb-1">Từ khách</label>
            <input 
              type="text" 
              :value="fromGuest" 
              readonly 
              class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold"
            />
          </div>

          <!-- Đến khách -->
          <div class="flex-1">
            <label class="block font-bold text-gray-700 mb-1">Đến khách</label>
            <select 
              v-model="selectedToGuest" 
              class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-gray-500 focus:outline-none focus:border-sky-500"
            >
              <option value="">Tên khách</option>
            </select>
          </div>
        </div>

        <!-- Table Data Container -->
        <div class="border border-gray-300 rounded-lg overflow-x-auto min-h-[220px] max-h-[320px] relative bg-white">
          <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
            <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
              <tr>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Ngày/giờ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[90px]">Bộ phận</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Dịch vụ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[95px]">Số tiền</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Đơn vị</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[80px]">Mã TT</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[65px]">Folio</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[75px]">Tax</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[95px]">Phí phục vụ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[80px]">Mã HĐ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[60px]">Xóa</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[90px]">Số VAT</th>
                <th class="px-2.5 py-1.5 min-w-[100px]">Người dùng</th>
              </tr>
            </thead>
            <tbody>
              <!-- Empty state -->
            </tbody>
          </table>

          <!-- Empty Data Placeholder -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-8">
            <Inbox class="w-10 h-10 stroke-1 mb-1 text-gray-300" />
            <span class="text-xs text-gray-400">No data</span>
          </div>
        </div>

      </div>

      <!-- Footer Actions -->
      <div class="border-t border-gray-300 p-3 flex justify-end bg-gray-50">
        <button 
          @click="handleClose" 
          class="bg-[#38bdf8] hover:bg-sky-500 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors"
        >
          <X class="w-4 h-4" />
          <span>Đóng</span>
        </button>
      </div>

    </div>
  </div>
</template>
