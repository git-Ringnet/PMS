<script setup>
import { ref } from 'vue'
import { HelpCircle, X, Inbox, ArrowRightLeft } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  fromGuest: {
    type: String,
    default: 'Mr. Guest 1 - 602'
  }
})

const emit = defineEmits(['close', 'submit'])

const handleClose = () => {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Chuyển bill nhanh</span>
        <div class="flex items-center gap-2">
          <button class="hover:text-gray-100"><HelpCircle class="w-4 h-4" /></button>
          <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
        </div>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-3">
        <!-- Readonly guest box -->
        <input 
          type="text" 
          :value="fromGuest" 
          readonly 
          class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold"
        />

        <!-- Table Data Container -->
        <div class="border border-gray-300 rounded-lg overflow-x-auto min-h-[260px] max-h-[360px] relative bg-white">
          <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
            <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
              <tr>
                <th class="px-2 py-1.5 w-10 text-center border-r border-gray-300">
                  <input type="checkbox" class="rounded border-gray-300" />
                </th>
                <th class="px-3 py-1.5 border-r border-gray-300 min-w-[140px]">Mã đăng ký</th>
                <th class="px-3 py-1.5 border-r border-gray-300 min-w-[200px]">Tên khách</th>
                <th class="px-3 py-1.5 border-r border-gray-300 min-w-[120px]">Phòng</th>
                <th class="px-3 py-1.5 text-right min-w-[140px]">Số tiền</th>
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
          <ArrowRightLeft class="w-4 h-4" />
          <span>Chuyển bill nhanh</span>
        </button>
      </div>

    </div>
  </div>
</template>
