<template>
  <div v-if="isOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-[400px] overflow-hidden flex flex-col max-h-[80vh]">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
        <h3 class="font-bold text-slate-700">Gộp hóa đơn</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="p-4 flex-1 overflow-y-auto">
        <p class="text-sm text-slate-600 mb-4">Vui lòng chọn các hóa đơn bạn muốn gộp lại với nhau:</p>
        
        <div class="space-y-2">
          <label 
            v-for="bill in bills" 
            :key="bill.id" 
            class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors"
            :class="selectedIds.includes(bill.id) ? 'border-sky-500 bg-sky-50' : 'border-slate-200 hover:bg-slate-50'"
          >
            <input 
              type="checkbox" 
              :value="bill.id" 
              v-model="selectedIds" 
              class="w-4 h-4 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
            />
            <div>
              <div class="font-bold text-slate-700">{{ bill.name }}</div>
              <div class="text-xs text-slate-500">{{ bill.items?.length || 0 }} món ăn</div>
            </div>
            <div class="ml-auto font-semibold text-slate-700">
              {{ (bill.items?.reduce((sum, i) => sum + Math.max(0, (i.price * i.quantity) - (i.discount || 0) + (i.surcharge || 0)), 0) || 0).toLocaleString('vi-VN') }} ₫
            </div>
          </label>
        </div>

        <div v-if="selectedIds.length > 0" class="mt-4 p-3 bg-amber-50 rounded border border-amber-100 text-xs text-amber-800">
          Tất cả các món ăn từ các đơn được chọn sẽ được gộp vào <strong>{{ getFirstSelectedBillName() }}</strong>. Các hóa đơn còn lại sẽ bị xóa.
        </div>
      </div>
      
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2 shrink-0">
        <button @click="$emit('close')" class="px-4 py-1.5 rounded bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors font-medium text-sm">Hủy</button>
        <button 
          @click="handleConfirm" 
          :disabled="selectedIds.length < 2"
          :class="selectedIds.length < 2 ? 'bg-slate-300 text-slate-500 cursor-not-allowed' : 'bg-sky-500 text-white hover:bg-sky-600 cursor-pointer'"
          class="px-4 py-1.5 rounded transition-colors font-medium text-sm"
        >
          Xác nhận gộp đơn
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  bills: { type: Array, required: true }
})

const emit = defineEmits(['close', 'confirm'])

const selectedIds = ref([])

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    selectedIds.value = []
  }
})

const getFirstSelectedBillName = () => {
  if (selectedIds.value.length === 0) return ''
  const firstId = selectedIds.value[0]
  const bill = props.bills.find(b => b.id === firstId)
  return bill ? bill.name : ''
}

const handleConfirm = () => {
  if (selectedIds.value.length >= 2) {
    // Make a copy to pass to parent
    emit('confirm', [...selectedIds.value])
  }
}
</script>
