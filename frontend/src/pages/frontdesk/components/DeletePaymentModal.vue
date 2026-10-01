<script setup>
import { ref, watch } from 'vue'
import { X, RotateCcw } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  payment: { type: Object, default: null },
  paymentGroup: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'submit'])
const reason = ref('')

watch(() => props.show, visible => {
  if (visible) reason.value = ''
})

const submit = () => {
  const value = reason.value.trim()
  if (!props.loading && value) emit('submit', value)
}

const isSettlementCancellation = () => Boolean(
  props.payment?.paymentId
  || props.payment?.paymentCode
  || props.payment?.rawPayment?.payment_id
  || props.payment?.rawPayment?.invoice_id
)

const formatAmount = amount => new Intl.NumberFormat('vi-VN').format(Number(amount) || 0)
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md overflow-hidden rounded-xl border border-red-500 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between bg-red-500 px-4 py-2 text-white">
        <span class="font-bold">{{ isSettlementCancellation() ? 'Hủy lần thanh toán' : 'Xóa thanh toán' }}</span>
        <button :disabled="loading" class="rounded transition hover:bg-white/20" @click="emit('close')"><X class="h-5 w-5" /></button>
      </header>
      <div class="space-y-3 p-4">
        <p v-if="isSettlementCancellation()" class="text-gray-700">
          Thao tác này hủy toàn bộ lần thanh toán, gồm tất cả phương thức đã thu. Cọc và thanh toán trước đã dùng sẽ được trả về trạng thái chưa sử dụng; các bill liên quan sẽ mở lại.
        </p>
        <p v-else class="text-gray-700">Hệ thống sẽ tạo dòng âm đối trừ cho khoản này.</p>
        <p v-if="payment" class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">{{ payment.description || payment.id }}</p>
        <ul v-if="isSettlementCancellation() && paymentGroup.length" class="max-h-28 space-y-1 overflow-y-auto rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-600">
          <li v-for="line in paymentGroup" :key="line.id" class="flex justify-between gap-3">
            <span>{{ line.paymentMethod || (line.isDepositOrAdvance ? 'Cọc / thanh toán trước' : 'Thanh toán') }}</span>
            <span class="shrink-0 tabular-nums">{{ formatAmount(line.amount) }}</span>
          </li>
        </ul>
        <label class="block font-medium text-gray-700">Lý do xoá <span class="text-red-500">*</span></label>
        <textarea v-model="reason" :disabled="loading" rows="3" maxlength="1000" placeholder="Nhập lý do xoá thanh toán" class="w-full resize-none rounded border border-gray-300 px-2.5 py-2 outline-none focus:border-red-500" />
      </div>
      <footer class="flex justify-end gap-2 border-t border-gray-300 bg-gray-50 p-3">
        <button :disabled="loading" class="rounded bg-gray-400 px-4 py-1.5 font-bold text-white transition hover:bg-gray-500 disabled:opacity-40" @click="emit('close')">Đóng</button>
        <button :disabled="loading || !reason.trim()" class="rounded bg-red-500 px-4 py-1.5 font-bold text-white transition hover:bg-red-600 disabled:opacity-40" @click="submit"><RotateCcw class="mr-1 inline h-4 w-4" />{{ loading ? 'Đang xử lý...' : (isSettlementCancellation() ? 'Hủy toàn bộ' : 'Xóa khoản') }}</button>
      </footer>
    </div>
  </div>
</template>
