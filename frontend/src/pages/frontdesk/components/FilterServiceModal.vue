<script setup>
import { ref } from 'vue'
import { X, Calendar, RotateCcw, Filter } from '@lucide/vue'

const props = defineProps({
  show: Boolean
})

const emit = defineEmits(['close', 'filter'])

const startDate = ref('')
const endDate = ref('')
const displayFilter = ref('unpaid') // 'unpaid' | 'vat' | 'unprinted_vat' | 'paid' | 'deleted'

const serviceCodeChecked = ref(false)
const serviceFolio = ref('')

const deptChecked = ref(false)

const handleClose = () => {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Lọc dịch vụ</span>
        <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-4 max-h-[80vh] overflow-y-auto">
        
        <!-- Row 1: Ngày bắt đầu & Ngày kết thúc -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-gray-700 mb-1">Ngày bắt đầu</label>
            <div class="relative">
              <input type="text" v-model="startDate" placeholder="/  /" class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-gray-700 mb-1">Ngày kết thúc</label>
            <div class="relative">
              <input type="text" v-model="endDate" placeholder="/  /" class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 1: Chỉ hiển thị (Radio group) -->
        <div class="grid grid-cols-12 gap-2">
          <label class="col-span-3 font-bold text-gray-700">Chỉ hiển thị</label>
          <div class="col-span-9 space-y-2">
            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="unpaid" class="text-sky-500 border-gray-300" />
              <span>Dịch vụ chưa thanh toán</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="vat" class="text-sky-500 border-gray-300" />
              <span>In VAT</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="unprinted_vat" class="text-sky-500 border-gray-300" />
              <span>Hoá đơn chưa in VAT</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="paid" class="text-sky-500 border-gray-300" />
              <span>Đã thanh toán</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="deleted" class="text-sky-500 border-gray-300" />
              <span>Đã xoá</span>
            </label>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 2: Lọc theo dịch vụ -->
        <div class="space-y-1.5">
          <label class="block font-bold text-gray-700">Lọc theo dịch vụ</label>
          <div class="border border-gray-300 rounded overflow-hidden">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-[#f0f2ea] border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1.5 border-r border-gray-300 w-24">Code</th>
                  <th class="p-1.5 border-r border-gray-300">Dịch vụ</th>
                  <th class="p-1.5 w-32">Folio</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-gray-200">
                  <td class="p-1.5 text-center border-r border-gray-300">
                    <input type="checkbox" v-model="serviceCodeChecked" class="rounded border-gray-300" />
                  </td>
                  <td class="p-1.5 font-semibold border-r border-gray-300">MB</td>
                  <td class="p-1.5 border-r border-gray-300">Minibar/Phí Minibar</td>
                  <td class="p-1">
                    <select v-model="serviceFolio" class="w-full p-1 bg-white border border-gray-300 rounded text-gray-500 focus:outline-none">
                      <option value="">Select Value</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="h-16 bg-white"></div>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 3: Lọc theo bộ phận -->
        <div class="space-y-1.5">
          <label class="block font-bold text-gray-700">Lọc theo bộ phận</label>
          <div class="border border-gray-300 rounded overflow-hidden">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-[#f0f2ea] border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1.5 border-r border-gray-300 w-32">Bộ phận</th>
                  <th class="p-1.5">Bộ phận</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-gray-200">
                  <td class="p-1.5 text-center border-r border-gray-300">
                    <input type="checkbox" v-model="deptChecked" class="rounded border-gray-300" />
                  </td>
                  <td class="p-1.5 font-semibold border-r border-gray-300">FO</td>
                  <td class="p-1.5">Reception/ Lễ Tân</td>
                </tr>
              </tbody>
            </table>
            <div class="h-16 bg-white"></div>
          </div>
        </div>

      </div>

      <!-- Footer Actions -->
      <div class="border-t border-gray-300 p-3 flex justify-end items-center gap-2 bg-gray-50">
        <button 
          @click="handleClose" 
          class="bg-[#38bdf8] hover:bg-sky-500 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors"
        >
          <X class="w-4 h-4" />
          <span>Đóng</span>
        </button>

        <button 
          class="bg-[#38bdf8] hover:bg-sky-500 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors"
        >
          <RotateCcw class="w-4 h-4" />
          <span>Khôi phục</span>
        </button>

        <button 
          class="bg-[#38bdf8] hover:bg-sky-500 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors"
        >
          <Filter class="w-4 h-4" />
          <span>Filter</span>
        </button>
      </div>

    </div>
  </div>
</template>
