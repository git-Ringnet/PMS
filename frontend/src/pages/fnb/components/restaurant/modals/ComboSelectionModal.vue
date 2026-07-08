<template>
  <div v-if="isOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
        <h3 class="font-bold text-slate-800 flex items-center gap-2">
          <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
          Chọn món cho Combo: {{ product?.name }}
        </h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 flex-1 overflow-y-auto max-h-[60vh]">
        <div v-if="localSubItems.length === 0" class="text-center text-slate-500 py-8">
          Combo này chưa được cấu hình món con.
        </div>
        <div v-else class="space-y-4">
          <div v-for="(item, index) in localSubItems" :key="index" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
            <div>
              <div class="font-medium text-slate-800">{{ item.product?.name || item.child?.name || 'Món con' }}</div>
              <div class="text-xs text-slate-500" v-if="item.price > 0">+{{ item.price.toLocaleString() }}đ</div>
            </div>
            
            <!-- Quantity Control -->
            <div class="flex items-center gap-3">
              <button @click="decreaseQty(index)" class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
              </button>
              <span class="w-4 text-center font-semibold text-slate-800">{{ item.quantity }}</span>
              <button @click="increaseQty(index)" class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
        <button @click="$emit('close')" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">
          Hủy
        </button>
        <button @click="handleConfirm" class="px-6 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition">
          Xác nhận
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  product: Object,
  editingItem: Object
})

const emit = defineEmits(['close', 'confirm'])

const localSubItems = ref([])

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.product) {
    const comboItems = props.product.comboItems || props.product.combo_items || []
    localSubItems.value = comboItems.map(item => {
      let qty = item.quantity
      if (props.editingItem && props.editingItem.sub_items) {
        const existing = props.editingItem.sub_items.find(s => s.product_id === item.child_id)
        qty = existing ? existing.quantity : 0
      }
      return {
        product_id: item.child_id,
        product: item.child,
        quantity: qty,
        price: item.price || 0
      }
    })
  }
})

const increaseQty = (index) => {
  localSubItems.value[index].quantity++
}

const decreaseQty = (index) => {
  if (localSubItems.value[index].quantity > 0) {
    localSubItems.value[index].quantity--
  }
}

const handleConfirm = () => {
  // Filter out items with 0 quantity
  const selectedItems = localSubItems.value.filter(item => item.quantity > 0).map(item => ({
    id: Date.now() + Math.floor(Math.random() * 1000), // temp id
    product: item.product,
    product_id: item.product_id,
    quantity: item.quantity,
    price: item.price,
    discount: 0,
    surcharge: 0,
    note: ''
  }))
  
  emit('confirm', { product: props.product, subItems: selectedItems })
}
</script>
