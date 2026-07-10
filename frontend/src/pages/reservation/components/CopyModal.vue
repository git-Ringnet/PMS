<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[450px] overflow-hidden border border-slate-200 flex flex-col"
    >
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0 select-none">
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
            <i class="fa-solid fa-clone text-sky-400"></i>
            <span>Nhân bản đăng ký phòng</span>
        </div>
        <button 
          class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" 
          @click="close"
        >
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-slate-700">
        <p class="text-slate-500 leading-relaxed font-medium">
          Nhân bản đăng ký này sang một thời gian mới. Toàn bộ thông tin khách hàng, loại phòng, số lượng phòng và đơn giá sẽ được sao chép tự động.
        </p>

        <div class="grid grid-cols-2 gap-3 mt-2">
          <div>
            <label class="block text-slate-500 mb-1 font-bold">Ngày đến mới</label>
            <input 
              type="date" 
              v-model="arrivalDate"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 bg-white"
            />
          </div>
          <div>
            <label class="block text-slate-500 mb-1 font-bold">Ngày đi mới</label>
            <input 
              type="date" 
              v-model="departureDate"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 bg-white"
            />
          </div>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 flex justify-end space-x-2.5 shrink-0">
        <button 
          @click="close" 
          class="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-lg hover:bg-slate-100 cursor-pointer transition bg-white"
        >
          Hủy
        </button>
        <button 
          @click="handleConfirmCopy" 
          class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer shadow-sm flex items-center space-x-1.5 transition border-none"
        >
          <i class="fa-solid fa-check"></i>
          <span>Xác nhận nhân bản</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { copyBooking } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  defaultArrival: String,
  defaultDeparture: String
})

const emit = defineEmits(['update:show', 'copied'])

const uiStore = useUiStore()

const arrivalDate = ref('')
const departureDate = ref('')

watch(() => props.show, (newVal) => {
  if (newVal) {
    arrivalDate.value = props.defaultArrival || ''
    departureDate.value = props.defaultDeparture || ''
  }
})

function close() {
  emit('update:show', false)
}

async function handleConfirmCopy() {
  if (!props.bookingId) return
  
  if (!arrivalDate.value || !departureDate.value) {
    uiStore.showToast('Vui lòng chọn đầy đủ ngày đến và ngày đi!', 'warning')
    return
  }
  
  if (departureDate.value <= arrivalDate.value) {
    uiStore.showToast('Ngày đi phải lớn hơn ngày đến!', 'warning')
    return
  }
  
  uiStore.showToast('Đang thực hiện nhân bản đăng ký...', 'info')
  try {
    const res = await copyBooking(props.bookingId, {
      arrival_date: arrivalDate.value,
      departure_date: departureDate.value
    })
    
    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Nhân bản đăng ký thành công!', 'success')
      close()
      emit('copied', res.data.data)
    } else {
      uiStore.showToast(res.data?.message || 'Nhân bản thất bại!', 'error')
    }
  } catch (err) {
    console.error('Copy booking error:', err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi nhân bản đăng ký!', 'error')
  }
}
</script>
