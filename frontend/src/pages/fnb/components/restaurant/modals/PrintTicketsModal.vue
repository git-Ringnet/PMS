<template>
  <div v-if="isOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-[1000px] overflow-hidden flex flex-col max-h-[80vh]">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
        <h3 class="font-bold text-slate-700">Danh sách phiếu in món</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <div class="p-0 flex-1 overflow-y-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-100 border-b border-slate-200 text-xs text-slate-600 font-semibold">
              <th class="py-2 px-3 border-r border-slate-200">Mã món</th>
              <th class="py-2 px-3 border-r border-slate-200">Tên món</th>
              <th class="py-2 px-3 border-r border-slate-200">Mã hóa đơn</th>
              <th class="py-2 px-3 border-r border-slate-200">Mã Corder</th>
              <th class="py-2 px-3 border-r border-slate-200">Máy in</th>
              <th class="py-2 px-3 border-r border-slate-200">Loại máy in</th>
              <th class="py-2 px-3 border-r border-slate-200">Tình trạng</th>
              <th class="py-2 px-3 border-r border-slate-200">Ngày in</th>
              <th class="py-2 px-3 w-16 text-center">In</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tickets || tickets.length === 0">
              <td colspan="9" class="py-8 text-center text-slate-400 text-sm italic">
                Chưa có món ăn nào trong hóa đơn
              </td>
            </tr>
            <tr v-else v-for="ticket in tickets" :key="ticket.id" class="border-b border-slate-100 hover:bg-slate-50 text-sm text-slate-700 transition-colors">
              <td class="py-2 px-3 border-r border-slate-100 font-mono">{{ ticket.code }}</td>
              <td class="py-2 px-3 border-r border-slate-100 font-semibold">{{ ticket.name }}</td>
              <td class="py-2 px-3 border-r border-slate-100">{{ ticket.billCode }}</td>
              <td class="py-2 px-3 border-r border-slate-100">{{ ticket.corderCode }}</td>
              <td class="py-2 px-3 border-r border-slate-100">{{ ticket.printer }}</td>
              <td class="py-2 px-3 border-r border-slate-100">{{ ticket.printerType }}</td>
              <td class="py-2 px-3 border-r border-slate-100">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="ticket.status === 'Đã in' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                  {{ ticket.status }}
                </span>
              </td>
              <td class="py-2 px-3 border-r border-slate-100">{{ ticket.printedDate }}</td>
              <td class="py-2 px-3 text-center">
                <button @click="$emit('print-item', ticket)" class="text-sky-500 hover:text-sky-600 transition-colors" title="In món này">
                  <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex justify-between items-center shrink-0">
        <button @click="$emit('print-all')" :disabled="!tickets || tickets.length === 0" class="px-4 py-1.5 rounded bg-sky-500 text-white hover:bg-sky-600 disabled:bg-slate-300 disabled:cursor-not-allowed transition-colors font-semibold text-sm flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
          In tất cả
        </button>
        <button @click="$emit('close')" class="px-4 py-1.5 rounded bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 transition-colors font-medium text-sm">Đóng</button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  isOpen: { type: Boolean, required: true },
  tickets: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'print-item', 'print-all'])
</script>
