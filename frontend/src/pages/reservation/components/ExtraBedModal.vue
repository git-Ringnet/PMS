<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs"
    @click.self="close"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-[480px] overflow-hidden border border-gray-300">
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2">
        <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
          <i class="fa-solid fa-bed text-amber-300"></i>
          <span>Thêm giường - PHÒNG {{ room?.roomNumber || 'CHƯA GÁN' }}</span>
        </div>
        <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 space-y-4">
        <!-- Số lượng giường phụ -->
        <div class="flex items-center gap-4">
          <label class="text-xs font-bold text-slate-700 w-40 shrink-0">Số lượng giường phụ</label>
          <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden">
            <button
              type="button"
              @click="quantity = Math.max(0, quantity - 1)"
              class="w-8 h-8 flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border-none cursor-pointer transition text-base"
            >−</button>
            <input
              type="number"
              v-model.number="quantity"
              min="0"
              max="10"
              class="w-14 h-8 text-center font-bold text-slate-800 border-none focus:outline-none text-sm"
            />
            <button
              type="button"
              @click="quantity = Math.min(10, quantity + 1)"
              class="w-8 h-8 flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold border-none cursor-pointer transition text-base"
            >+</button>
          </div>
        </div>

        <!-- Giá giường phụ / đêm -->
        <div class="flex items-center gap-4">
          <label class="text-xs font-bold text-slate-700 w-40 shrink-0">Giá thêm giường / đêm</label>
          <div class="flex items-center flex-1">
            <input
              type="text"
              :value="formatCurrency(rate)"
              @input="e => rate = cleanCurrency(e.target.value)"
              class="flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 text-right"
              placeholder="0"
            />
            <span class="ml-2 text-xs text-slate-500 font-semibold">VND</span>
          </div>
        </div>

        <!-- Thông tin tổng -->
        <div v-if="quantity > 0" class="bg-sky-50 border border-sky-200 rounded-lg p-3 text-xs text-sky-800">
          <div class="flex justify-between mb-1">
            <span>Số lượng:</span>
            <span class="font-bold">{{ quantity }} giường</span>
          </div>
          <div class="flex justify-between mb-1">
            <span>Đơn giá:</span>
            <span class="font-bold">{{ formatCurrency(rate) }} VND/đêm</span>
          </div>
          <div class="flex justify-between font-bold text-sky-900 border-t border-sky-200 pt-1 mt-1">
            <span>Tổng / đêm:</span>
            <span>{{ formatCurrency(quantity * rate) }} VND</span>
          </div>
        </div>

        <div v-if="quantity === 0" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-700">
          <i class="fa-solid fa-circle-info mr-1"></i>
          Nhập số lượng > 0 để thêm giường phụ. Nếu để 0, sẽ xóa giường phụ khỏi phòng.
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-2.5 flex items-center justify-end space-x-2">
        <button
          @click="close"
          class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition"
        >
          Hủy
        </button>
        <button
          @click="save"
          class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 border-none"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  show: Boolean,
  room: Object
})

const emit = defineEmits(['update:show', 'saved'])

const quantity = ref(0)
const rate = ref(0)

watch(() => props.show, (newVal) => {
  if (newVal && props.room) {
    // Load giá trị hiện tại của phòng
    quantity.value = props.room.extraBedQty || 0
    rate.value = props.room.extraBedPrice || 0
  }
})

function close() {
  emit('update:show', false)
}

function formatCurrency(val) {
  if (!val && val !== 0) return ''
  const num = Number(String(val).replace(/[^0-9.]/g, ''))
  if (isNaN(num)) return ''
  return num.toLocaleString('en-US')
}

function cleanCurrency(val) {
  if (!val) return 0
  return Number(String(val).replace(/,/g, '')) || 0
}

function save() {
  emit('saved', {
    quantity: quantity.value,
    rate: rate.value
  })
  close()
}
</script>
