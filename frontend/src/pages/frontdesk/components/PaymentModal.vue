<script setup>
import { ref } from 'vue'
import { HelpCircle, X, Plus, Calendar, Clock, Save, Inbox } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  totalAmount: {
    type: Number,
    default: -960000
  },
  depositAmount: {
    type: Number,
    default: 1000000
  }
})

const emit = defineEmits(['close', 'submit'])

const paymentMethod = ref('Cash')
const company = ref('KHÁCH LẺ')
const isCard = ref(false)
const cardCode = ref('')
const expiryDate = ref('')
const noteText = ref('')

const currency = ref('VND')
const workShift = ref('2')
const timeStr = ref('14 : 48')
const dateStr = ref('09 / 07 / 2026')
const department = ref('FO')

const payAmount = ref('-960,000')
const deposit = ref('1,000,000')
const remaining = ref('-960,000')
const total = ref('-960,000')

const handleClose = () => {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Thanh toán</span>
        <div class="flex items-center gap-2">
          <button class="hover:text-gray-100"><HelpCircle class="w-4 h-4" /></button>
          <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
        </div>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-4">
        
        <!-- Top Split Section: Form Trái & Bảng Giá Phải -->
        <div class="grid grid-cols-12 gap-4">
          
          <!-- LEFT CARD (6 cols) -->
          <div class="col-span-6 border border-gray-300 rounded-lg p-3 space-y-2.5 bg-white">
            <!-- Phương thức thanh toán -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Phương thức thanh toán</label>
              <select v-model="paymentMethod" class="w-full px-2.5 py-1 bg-[#fef9c3] border border-gray-300 rounded font-bold text-gray-800 focus:outline-none">
                <option value="Cash">Cash</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Credit Card">Credit Card</option>
              </select>
            </div>

            <!-- Công ty -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Công ty</label>
              <div class="flex gap-1">
                <select v-model="company" class="flex-1 px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold focus:outline-none">
                  <option value="KHÁCH LẺ">KHÁCH LẺ</option>
                </select>
                <button class="bg-sky-100 border border-sky-300 p-1 rounded text-sky-600 font-bold hover:bg-sky-200">
                  <Plus class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Checkbox Thẻ & Mã thẻ -->
            <div class="grid grid-cols-12 gap-2 items-center pt-1">
              <div class="col-span-4 flex items-center gap-1.5">
                <input type="checkbox" v-model="isCard" class="rounded border-gray-300" />
                <label class="font-bold text-gray-700">Thẻ</label>
              </div>
              <div class="col-span-8">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Mã thẻ</label>
                <input 
                  type="text" 
                  v-model="cardCode" 
                  :disabled="!isCard"
                  class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded text-xs" 
                />
              </div>
            </div>

            <!-- Ngày hết hạn & Ghi chú -->
            <div class="grid grid-cols-12 gap-2">
              <div class="col-span-5">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Ngày hết hạn</label>
                <div class="relative">
                  <input type="text" v-model="expiryDate" placeholder="/  /" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-xs" />
                  <Calendar class="w-3 h-3 text-emerald-600 absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <div class="col-span-7">
                <label class="block font-bold text-gray-700 mb-0.5 text-[11px]">Ghi chú</label>
                <input type="text" v-model="noteText" class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded text-xs" />
              </div>
            </div>
          </div>

          <!-- RIGHT SUMMARY CONTROLS (6 cols) -->
          <div class="col-span-6 space-y-2">
            
            <!-- Top Inputs Row: Tiền tệ, Ca, Giờ, Ngày, Bộ phận -->
            <div class="grid grid-cols-12 gap-1 items-end">
              <!-- Tiền tệ -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Tiền tệ</label>
                <div class="flex items-center gap-0.5 bg-white border border-gray-300 px-1 py-1 rounded">
                  <span class="w-2.5 h-2.5 bg-red-600 rounded-full flex items-center justify-center text-[7px] text-yellow-300">★</span>
                  <select v-model="currency" class="bg-transparent font-bold text-[10px] focus:outline-none">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>

              <!-- Ca làm việc -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ca làm việc</label>
                <select v-model="workShift" class="w-full px-1 py-1 bg-[#fef9c3] border border-gray-300 rounded font-bold text-[11px]">
                  <option value="2">2</option>
                  <option value="1">1</option>
                </select>
              </div>

              <!-- Giờ -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Giờ</label>
                <div class="relative">
                  <input type="text" v-model="timeStr" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-center font-mono text-[11px]" />
                  <Clock class="w-3 h-3 text-sky-500 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Ngày -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Ngày</label>
                <div class="relative">
                  <input type="text" v-model="dateStr" class="w-full px-1 py-1 bg-white border border-gray-300 rounded text-center font-mono text-[11px]" />
                  <Calendar class="w-3 h-3 text-emerald-600 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Bộ phận -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-0.5 text-[10px]">Bộ phận</label>
                <select v-model="department" class="w-full px-1 py-1 bg-white border border-gray-300 rounded font-bold text-[11px]">
                  <option value="FO">FO</option>
                </select>
              </div>
            </div>

            <!-- Value Rows -->
            <div class="space-y-1.5 pt-1">
              <!-- Thanh toán + Thêm button -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Thanh toán</label>
                <div class="col-span-6">
                  <input type="text" v-model="payAmount" class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded font-mono font-bold text-gray-800" />
                </div>
                <div class="col-span-3">
                  <button class="w-full bg-[#38bdf8] hover:bg-sky-500 text-white px-2 py-1 rounded flex items-center justify-center gap-1 font-bold shadow-xs transition-colors">
                    <Plus class="w-3.5 h-3.5" />
                    <span>Thêm</span>
                  </button>
                </div>
              </div>

              <!-- Đặt cọc -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Đặt cọc</label>
                <div class="col-span-6">
                  <input type="text" v-model="deposit" readonly class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-gray-800" />
                </div>
              </div>

              <!-- Còn Lại -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Còn Lại</label>
                <div class="col-span-6">
                  <input type="text" v-model="remaining" readonly class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-gray-800" />
                </div>
              </div>

              <!-- Tổng tiền -->
              <div class="grid grid-cols-12 gap-2 items-center">
                <label class="col-span-3 font-bold text-gray-700 text-right pr-1">Tổng tiền</label>
                <div class="col-span-6">
                  <input type="text" v-model="total" readonly class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded font-mono font-bold text-gray-800" />
                </div>
              </div>
            </div>

          </div>

        </div>

        <!-- Bottom Table Section -->
        <div class="border border-gray-300 rounded-lg overflow-x-auto min-h-[180px] max-h-[260px] relative bg-white">
          <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
            <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
              <tr>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Phương thức thanh toán</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Tài khoản ngân hàng</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[90px]">Tiền tệ</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[130px]">Số tiền tương đương</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[80px]">Phí</th>
                <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[110px]">Tổng tiền</th>
                <th class="px-2.5 py-1.5 min-w-[50px]"></th>
              </tr>
            </thead>
            <tbody>
              <!-- Empty state -->
            </tbody>
          </table>

          <!-- Empty Data Placeholder -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
            <Inbox class="w-9 h-9 stroke-1 mb-1 text-gray-300" />
            <span class="text-xs text-gray-400">No data</span>
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
          <Save class="w-4 h-4" />
          <span>Lưu</span>
        </button>
      </div>

    </div>
  </div>
</template>
