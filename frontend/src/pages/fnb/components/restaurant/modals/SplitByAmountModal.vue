<template>
  <div v-if="isOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-96 overflow-hidden flex flex-col">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
        <h3 class="font-bold text-slate-700">Tách đơn theo số tiền</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="p-5 flex-1">
        <div class="mb-4 text-sm text-slate-600">
          <div class="flex justify-between mb-1">
            <span>Tổng tiền hiện tại:</span>
            <span class="font-bold text-slate-800">{{ currentTotal.toLocaleString('vi-VN') }} ₫</span>
          </div>
          <p class="text-xs text-slate-500 mt-2">Nhập số tiền bạn muốn tách sang hóa đơn mới. Các món ăn sẽ được chia tỷ lệ tự động.</p>
        </div>
        
        <div class="space-y-1">
          <label class="text-xs font-semibold text-slate-600">Số tiền tách sang đơn mới</label>
          <div class="relative">
            <input 
              type="number" 
              v-model="splitAmount" 
              class="w-full border border-slate-300 rounded-lg px-3 py-2 pr-12 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 font-semibold text-right"
              placeholder="0"
            />
            <span class="absolute right-3 top-2 text-slate-400 font-semibold text-sm">₫</span>
          </div>
          <p v-if="errorMsg" class="text-xs text-rose-500 mt-1">{{ errorMsg }}</p>
        </div>

        <div v-if="!errorMsg && splitAmount > 0" class="mt-4 pt-3 border-t border-slate-100 text-sm flex justify-between text-slate-600">
          <span>Tiền còn lại ở đơn cũ:</span>
          <span class="font-bold text-slate-800">{{ (currentTotal - splitAmount).toLocaleString('vi-VN') }} ₫</span>
        </div>
      </div>
      
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 shrink-0">
        <button @click="$emit('close')" class="px-4 py-1.5 rounded bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors font-medium text-sm">Hủy</button>
        <button @click="handleConfirm" class="px-4 py-1.5 rounded bg-sky-500 text-white hover:bg-sky-600 transition-colors font-medium text-sm">Xác nhận tách đơn</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  currentTotal: { type: Number, required: true }
})

const emit = defineEmits(['close', 'confirm'])

const splitAmount = ref('')
const errorMsg = ref('')

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    splitAmount.value = ''
    errorMsg.value = ''
  }
})

watch(splitAmount, (newVal) => {
  if (newVal < 0) {
    errorMsg.value = 'Số tiền không được âm'
  } else if (newVal > props.currentTotal) {
    errorMsg.value = 'Số tiền tách không được vượt quá tổng hóa đơn'
  } else {
    errorMsg.value = ''
  }
})

const handleConfirm = () => {
  const amount = Number(splitAmount.value)
  if (amount <= 0) {
    errorMsg.value = 'Vui lòng nhập số tiền hợp lệ lớn hơn 0'
    return
  }
  if (amount >= props.currentTotal) {
    errorMsg.value = 'Số tiền tách không được lớn hơn hoặc bằng tổng hóa đơn'
    return
  }
  
  emit('confirm', amount)
}
</script>
