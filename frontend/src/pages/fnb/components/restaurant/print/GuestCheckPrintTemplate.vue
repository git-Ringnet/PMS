<template>
  <div id="guest-check-print-area" class="hidden print:block bg-white text-black font-sans p-4 absolute top-0 left-0 w-full min-h-screen">
    <div class="max-w-[400px] mx-auto border border-dashed border-gray-400 p-6">
      <!-- Header -->
      <div class="flex justify-between items-start mb-6">
        <div>
          <!-- Logo -->
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-slate-800 rotate-45 flex items-center justify-center rounded-sm">
              <span class="text-white font-bold -rotate-45 text-xs">POS</span>
            </div>
          </div>
        </div>
        <div class="text-right text-xs leading-relaxed">
          <p>Invoice No: FB{{ String(bill?.id || '').padStart(6, '0') }}</p>
          <p>Date: {{ formattedDate }}</p>
          <p>Hotline: (0258) 3 846 267</p>
        </div>
      </div>

      <!-- Title -->
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold uppercase mb-1">Nhà Hàng</h2>
        <h3 class="text-lg font-bold uppercase">Guest Check</h3>
      </div>

      <!-- Info -->
      <div class="text-xs mb-4 leading-relaxed">
        <p><span class="font-semibold">Table:</span> {{ tableName }}</p>
        <p><span class="font-semibold">Guest Name:</span> {{ bill?.customer_name || '' }}</p>
        <p><span class="font-semibold">Cashier:</span> admin</p>
        <p><span class="font-semibold">Guest Notes:</span> {{ bill?.notes || '' }}</p>
        <p><span class="font-semibold">Discount Notes:</span> {{ bill?.discount_note || '' }}</p>
        <p><span class="font-semibold">Print No:</span> 1</p>
      </div>

      <!-- Table -->
      <table class="w-full text-xs border-collapse border border-black mb-6">
        <thead>
          <tr class="border-b border-black">
            <th class="border-r border-black p-1.5 text-left font-bold">Item</th>
            <th class="border-r border-black p-1.5 text-center font-bold w-12">Qty</th>
            <th class="border-r border-black p-1.5 text-right font-bold w-20">Unit Price</th>
            <th class="p-1.5 text-right font-bold w-24">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(item, i) in (bill?.items || [])" :key="i">
            <tr class="border-b border-black">
              <td class="border-r border-black p-1.5 font-medium">{{ item.name || item.product?.name }}</td>
              <td class="border-r border-black p-1.5 text-center">{{ item.quantity }}</td>
              <td class="border-r border-black p-1.5 text-right">{{ formatNumber(item.price) }}</td>
              <td class="p-1.5 text-right">{{ formatNumber(item.price * item.quantity) }}</td>
            </tr>
            <tr v-for="(subItem, j) in (item.sub_items || [])" :key="`sub_${i}_${j}`" class="border-b border-black text-gray-700">
              <td class="border-r border-black p-1.5 pl-4 text-[11px] italic">- {{ subItem.name || subItem.product?.name }}</td>
              <td class="border-r border-black p-1.5 text-center text-[11px]">{{ subItem.quantity }}</td>
              <td class="border-r border-black p-1.5 text-right text-[11px]">{{ formatNumber(subItem.price) }}</td>
              <td class="p-1.5 text-right text-[11px]">{{ formatNumber(subItem.price * subItem.quantity) }}</td>
            </tr>
          </template>
          
          <tr class="font-bold border-b border-black">
            <td colspan="2" class="border-r border-black p-1.5 text-right"></td>
            <td class="border-r border-black p-1.5 text-right">Total Qty</td>
            <td class="p-1.5 text-right">{{ totalQty }}</td>
          </tr>
          <tr class="font-bold border-b border-black">
            <td colspan="2" class="border-r border-black p-1.5 text-right"></td>
            <td class="border-r border-black p-1.5 text-right">Total (VND)</td>
            <td class="p-1.5 text-right">{{ formatNumber(subtotal) }}</td>
          </tr>
          <tr v-if="discountAmount > 0" class="font-bold border-b border-black">
            <td colspan="2" class="border-r border-black p-1.5 text-right"></td>
            <td class="border-r border-black p-1.5 text-right">Discount {{ bill?.discount_percent ? bill.discount_percent + '%' : '' }}</td>
            <td class="p-1.5 text-right">{{ formatNumber(discountAmount) }}</td>
          </tr>
          <tr v-if="vatAmount > 0" class="font-bold border-b border-black">
            <td colspan="2" class="border-r border-black p-1.5 text-right"></td>
            <td class="border-r border-black p-1.5 text-right">VAT ({{ bill?.tax_percent || 8 }}%)</td>
            <td class="p-1.5 text-right">{{ formatNumber(vatAmount) }}</td>
          </tr>
          <tr class="font-bold border-b border-black text-sm">
            <td colspan="2" class="border-r border-black p-1.5 text-right"></td>
            <td class="border-r border-black p-1.5 text-right">Amount Due</td>
            <td class="p-1.5 text-right">{{ formatNumber(amountDue) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Footer -->
      <div class="flex justify-between text-xs mt-12 mb-16 px-4">
        <div class="text-center font-semibold">
          <p>Guest Name & Signature</p>
        </div>
        <div class="text-center font-semibold">
          <p>Cashier</p>
        </div>
      </div>

      <div class="text-center text-xs italic border-t border-dashed border-gray-400 pt-3">
        Thank You!
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  bill: { type: Object, default: () => ({}) },
  tableName: { type: String, default: '' }
})

const formattedDate = computed(() => {
  const d = new Date()
  const pad = n => n.toString().padStart(2, '0')
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
})

const formatNumber = (val) => {
  if (!val) return '0'
  return Math.round(val).toLocaleString('vi-VN')
}

const totalQty = computed(() => {
  if (!props.bill?.items) return 0
  return props.bill.items.reduce((sum, item) => sum + (item.quantity || 0), 0)
})

const subtotal = computed(() => {
  if (!props.bill?.items) return 0
  return props.bill.items.reduce((sum, item) => sum + ((item.price || 0) * (item.quantity || 0)), 0)
})

const discountAmount = computed(() => {
  return props.bill?.discount_amount || 0
})

const vatAmount = computed(() => {
  return props.bill?.tax_amount || 0
})

const amountDue = computed(() => {
  // If the backend already calculated total_amount including everything, use it.
  if (props.bill?.total_amount) {
    return props.bill.total_amount
  }
  // Otherwise, fallback manual calculation
  return subtotal.value - discountAmount.value + vatAmount.value
})
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  #guest-check-print-area, #guest-check-print-area * {
    visibility: visible;
  }
  #guest-check-print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
  }
}
</style>
