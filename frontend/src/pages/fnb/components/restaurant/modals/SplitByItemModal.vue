<template>
  <div v-if="isOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-[500px] overflow-hidden flex flex-col max-h-[80vh]">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
        <h3 class="font-bold text-slate-700">Tách đơn theo món</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="p-4 flex-1 overflow-y-auto">
        <p class="text-sm text-slate-600 mb-4">Các món sau sẽ được chuyển sang một hóa đơn mới:</p>
        
        <table class="w-full text-xs border-collapse">
          <thead class="bg-slate-50 border-b border-slate-200 text-slate-700">
            <tr>
              <th class="py-2 px-2 text-left font-semibold w-1/2">Tên SP</th>
              <th class="py-2 px-2 text-center font-semibold">Số lượng</th>
              <th class="py-2 px-2 text-right font-semibold">Tổng tiền</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 hover:bg-slate-50">
              <td class="py-2 px-2 font-medium text-slate-800">{{ item.product.name }}</td>
              <td class="py-2 px-2 text-center font-semibold text-slate-700">{{ item.quantity }}</td>
              <td class="py-2 px-2 text-right text-slate-700">
                {{ Number(Math.max(0, item.price * item.quantity - (item.discount || 0) + (item.surcharge || 0))).toLocaleString('vi-VN') }}
              </td>
            </tr>
          </tbody>
        </table>
        
        <div class="mt-4 flex justify-between items-center bg-sky-50 p-3 rounded border border-sky-100">
          <span class="font-bold text-sky-800 text-sm">Tổng cộng tách sang bill mới:</span>
          <span class="font-extrabold text-sky-600 text-lg">{{ totalAmount.toLocaleString('vi-VN') }} ₫</span>
        </div>

        <div class="mt-4 space-y-2">
          <label class="block text-sm font-medium text-slate-700">
            Lý do <span class="text-rose-500">*</span>
          </label>
          <input 
            type="text" 
            v-model="reason"
            class="w-full rounded border px-3 py-2 text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
            :class="showError && !reason.trim() ? 'border-red-500 bg-red-50' : 'border-slate-300'"
            placeholder="Nhập lý do tách đơn..."
            @keyup.enter="handleConfirm"
          />
          <p v-if="showError && !reason.trim()" class="text-[10px] text-red-500 mt-0.5">Vui lòng nhập lý do tách đơn.</p>
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
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  items: { type: Array, required: true }
})

const emit = defineEmits(['close', 'confirm'])

const reason = ref('')
const showError = ref(false)

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    reason.value = ''
    showError.value = false
  }
})

const totalAmount = computed(() => {
  return props.items.reduce((sum, item) => sum + Math.max(0, item.price * item.quantity - (item.discount || 0) + (item.surcharge || 0)), 0)
})

const handleConfirm = () => {
  if (!reason.value.trim()) {
    showError.value = true
    return
  }
  emit('confirm', reason.value.trim())
}
</script>
