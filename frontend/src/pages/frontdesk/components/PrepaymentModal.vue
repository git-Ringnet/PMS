<script setup>
import { ref } from 'vue'
import { X, Plus, Calendar, Clock, Save } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  registrationName: {
    type: String,
    default: 'Test 1'
  },
  roomNumber: {
    type: String,
    default: '602'
  }
})

const emit = defineEmits(['close', 'submit'])

const localRoomNumber = ref(props.roomNumber)
const amount = ref(0)
const paymentMethod = ref('')
const description = ref('')
const workShift = ref('2')
const timeStr = ref('14 : 41')
const dateStr = ref('09 / 07 / 2026')
const currency = ref('VND')

const handleClose = () => {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Thanh toán trước</span>
        <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-3">
        <!-- Main Form Grid (2 Columns: Left Controls & Right Textarea) -->
        <div class="grid grid-cols-12 gap-4">

          <!-- Left Column (6 cols) -->
          <div class="col-span-6 space-y-2.5">
            <!-- Tên đăng ký -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Tên đăng ký</label>
              <select class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-semibold text-gray-800 focus:outline-none">
                <option :value="registrationName">{{ registrationName }}</option>
              </select>
            </div>

            <!-- Số tiền & Phòng -->
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-gray-700 mb-1">Số tiền</label>
                <input 
                  type="number" 
                  v-model="amount" 
                  class="w-full px-2 py-1 bg-[#fef9c3] border border-sky-400 rounded font-bold text-gray-800 focus:outline-none" 
                />
              </div>

              <div>
                <label class="block font-bold text-gray-700 mb-1">Phòng</label>
                <select v-model="localRoomNumber" class="w-full px-2 py-1 bg-white border border-gray-300 rounded font-bold text-gray-800 focus:outline-none">
                  <option :value="roomNumber">{{ roomNumber }}</option>
                </select>
              </div>
            </div>

            <!-- Bottom Row: Ca làm việc, Giờ, Ngày, Tiền tệ -->
            <div class="grid grid-cols-12 gap-1.5 items-end pt-1">
              <!-- Ca làm việc -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Ca làm việc</label>
                <select v-model="workShift" class="w-full px-1.5 py-1 bg-[#fef9c3] border border-gray-300 rounded font-bold text-xs">
                  <option value="2">2</option>
                  <option value="1">1</option>
                </select>
              </div>

              <!-- Giờ -->
              <div class="col-span-3">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Giờ</label>
                <div class="relative">
                  <input type="text" v-model="timeStr" class="w-full px-1.5 py-1 bg-white border border-gray-300 rounded text-center text-xs font-mono" />
                  <Clock class="w-3 h-3 text-sky-500 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Ngày -->
              <div class="col-span-4">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Ngày</label>
                <div class="relative">
                  <input type="text" v-model="dateStr" class="w-full px-1.5 py-1 bg-white border border-gray-300 rounded text-center text-xs font-mono" />
                  <Calendar class="w-3 h-3 text-emerald-600 absolute right-1 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>

              <!-- Tiền tệ -->
              <div class="col-span-2">
                <label class="block font-medium text-gray-700 mb-1 text-[11px]">Tiền tệ</label>
                <div class="flex items-center gap-0.5 bg-white border border-gray-300 px-1 py-1 rounded">
                  <span class="w-2.5 h-2.5 bg-red-600 rounded-full flex items-center justify-center text-[7px] text-yellow-300">★</span>
                  <select v-model="currency" class="bg-transparent focus:outline-none font-bold text-[11px]">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>
            </div>

          </div>

          <!-- Right Column (6 cols) -->
          <div class="col-span-6 space-y-2.5 flex flex-col justify-between">
            <!-- Hình thức thanh toán -->
            <div>
              <label class="block font-bold text-gray-700 mb-1">Hình thức thanh toán</label>
              <div class="flex gap-1">
                <select v-model="paymentMethod" class="flex-1 px-2.5 py-1 bg-[#fef9c3] border border-gray-300 rounded text-gray-500 font-medium focus:outline-none">
                  <option value="">Hình thức thanh toán</option>
                </select>
                <button class="bg-[#fef9c3] border border-gray-300 p-1.5 rounded hover:bg-yellow-200 text-sky-600 font-bold">
                  <Plus class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Mô tả Textarea -->
            <div class="flex-1 flex flex-col">
              <label class="block font-bold text-gray-700 mb-1">Mô tả</label>
              <textarea 
                v-model="description" 
                placeholder="Mô tả"
                class="w-full flex-1 p-2 bg-[#fef9c3] border border-gray-300 rounded text-xs focus:outline-none resize-none min-h-[90px]"
              ></textarea>
            </div>
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
