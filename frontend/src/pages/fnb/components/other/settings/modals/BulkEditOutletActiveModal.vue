<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  outletsList: {
    type: Array,
    required: true
  },
  selectedCount: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'save'])

const outletsStatus = ref([])

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    // Reset status when modal opens
    outletsStatus.value = props.outletsList.map(o => ({
      outlet_id: o.id,
      name: o.name,
      is_active: true // default to active for all selected items when batch editing
    }))
  }
})

const closeModal = () => {
  emit('close')
}

const handleSave = () => {
  const payload = outletsStatus.value.map(o => ({
    outlet_id: o.outlet_id,
    is_active: o.is_active
  }))
  emit('save', payload)
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeModal"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden animate-fade-in-up">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <div>
          <h2 class="text-lg font-bold text-slate-800">Cập nhật Mở bán theo Outlet</h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Áp dụng cho <span class="text-sky-600 font-bold">{{ selectedCount }}</span> món ăn đã chọn</p>
        </div>
        <button @click="closeModal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-4 overflow-y-auto custom-scrollbar flex flex-col gap-3">
        <p class="text-[11px] text-slate-500 mb-2 leading-relaxed">
          Bật công tắc tại các Outlet bạn muốn các món ăn này được <b>Mở bán</b>. Tắt công tắc nếu muốn ngừng bán.
        </p>

        <div v-for="outlet in outletsStatus" :key="outlet.outlet_id" class="flex items-center justify-between p-3 rounded-lg border border-slate-100 bg-white hover:bg-slate-50 transition">
          <span class="text-sm font-bold text-slate-700">{{ outlet.name }}</span>
          
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="outlet.is_active" class="sr-only peer">
            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
          </label>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
        <button @click="closeModal" class="px-5 py-2 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
          Hủy
        </button>
        <button @click="handleSave" class="px-5 py-2 text-sm font-bold text-white bg-sky-500 rounded-lg hover:bg-sky-600 transition shadow-sm shadow-sky-200">
          Cập nhật
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
</style>
