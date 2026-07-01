<script setup>
import { ref } from 'vue'

const props = defineProps({
  tableId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['back', 'openItem'])

const showActionMenu = ref(false)

</script>

<template>
  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white">
    <!-- Main Content (Bill) -->
    <div class="flex-1 flex flex-col overflow-hidden bg-white">
      <!-- Top header -->
      <div class="flex items-center justify-between border-b border-sky-200 bg-white shrink-0 px-1 py-1">
        <div class="flex items-center text-sky-400 font-medium text-sm">
          <span class="text-sky-400 mr-2 ml-2">Bill 1</span>
        </div>
        <div class="flex items-center gap-2">
          <button class="text-emerald-500 hover:text-emerald-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </button>
          <div class="bg-sky-400 text-white font-bold text-lg px-4 py-1 flex items-center justify-center min-w-[3rem]">
            {{ tableId }}
          </div>
        </div>
      </div>

      <!-- Toolbar inputs -->
      <div class="flex items-center gap-2 p-2 bg-[#f8f9fa] border-b border-slate-200 shrink-0 text-sm">
        <button class="bg-sky-300 hover:bg-sky-400 text-white px-3 py-1.5 rounded text-xs font-medium flex items-center gap-1 transition-colors" @click="$emit('openItem')">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
          Open item
        </button>
        <div class="flex-1 flex gap-2">
          <div class="flex items-center flex-1 bg-amber-50/50 border border-slate-200 rounded">
            <span class="text-slate-600 px-2 whitespace-nowrap text-xs">Khách hàng:</span>
            <input type="text" class="bg-transparent border-none focus:outline-none w-full text-xs py-1" />
          </div>
          <div class="flex items-center w-24 bg-slate-100 border border-slate-200 rounded">
            <span class="text-slate-600 px-2 whitespace-nowrap text-xs">SL khách: 0</span>
          </div>
          <div class="flex items-center flex-1 bg-slate-100 border border-slate-200 rounded">
            <span class="text-slate-600 px-2 whitespace-nowrap text-xs">Người tạo:</span>
            <input type="text" class="bg-transparent border-none focus:outline-none w-full text-xs py-1" />
          </div>
          <div class="flex items-center flex-1 bg-slate-100 border border-slate-200 rounded relative">
            <span class="text-slate-600 px-2 whitespace-nowrap text-xs">Ghi chú:</span>
            <input type="text" class="bg-transparent border-none focus:outline-none w-full text-xs py-1" />
            <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
          </div>
          <div class="flex items-center flex-1 bg-slate-100 border border-slate-200 rounded relative">
            <span class="text-slate-600 px-2 whitespace-nowrap text-xs">Ghi chú nội bộ:</span>
            <input type="text" class="bg-transparent border-none focus:outline-none w-full text-xs py-1" />
            <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
          </div>
        </div>
      </div>

      <!-- Bill summary -->
      <div class="bg-amber-100/60 p-2 border-b border-amber-200 flex justify-between items-center text-xs text-amber-900 font-semibold cursor-pointer">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
          <span>KHÁCH LẺ</span>
          <span class="text-amber-700/60 ml-2 font-normal">Chưa áp KM</span>
        </div>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
      </div>

      <!-- Table items -->
      <div class="flex-1 overflow-auto flex flex-col border-b border-slate-200">
        <table class="w-full text-xs border-collapse">
          <thead class="sticky top-0 bg-white shadow-sm z-10">
            <tr class="border-b border-slate-200 text-slate-700">
              <th class="px-2 py-2 text-left font-semibold border-r border-slate-100 w-1/4">Tên SP</th>
              <th class="px-2 py-2 text-left font-semibold border-r border-slate-100">Ghi chú</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Đơn giá</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Số lượng</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Giảm giá</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Phụ thu</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Tổng tiền</th>
              <th class="px-2 py-2 text-center font-semibold border-r border-slate-100">Đơn vị</th>
              <th class="px-2 py-2 text-center font-semibold">Thuế</th>
            </tr>
          </thead>
        </table>
        
        <!-- Empty State -->
        <div class="flex-1 flex flex-col items-center justify-center text-slate-300">
          <div class="relative">
            <svg class="w-12 h-12 text-slate-200 drop-shadow-sm" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <div class="absolute -top-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-sm border border-slate-100">
               <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
            </div>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide mt-2">Trống</span>
        </div>
      </div>

      <!-- Bottom footer -->
      <div class="p-2 shrink-0 bg-white">
        <!-- Total amount -->
        <div class="flex justify-end items-center mb-2 px-2">
          <span class="font-bold text-slate-700 mr-2">0</span>
          <div class="w-4 h-4 rounded-full bg-sky-300 text-white flex items-center justify-center text-[10px] font-bold">i</div>
        </div>
        
        <!-- Action buttons -->
        <div class="flex items-center justify-between relative">
          <button @click="showActionMenu = !showActionMenu" class="bg-sky-400 hover:bg-sky-500 text-white rounded p-2 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
          </button>
          
          <!-- Dropdown menu -->
          <div v-if="showActionMenu" class="absolute bottom-full left-0 mb-2 w-48 bg-white border border-slate-200 shadow-xl rounded-lg py-2 z-50">
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Gộp đơn</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Tách đơn theo món</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Tách đơn theo số tiền</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Chuyển bàn</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Chuyển món</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Tách món</button>
            <button class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">Xóa đơn</button>
          </div>
          
          <div class="flex gap-2">
            <button @click="emit('back')" class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-1.5 rounded text-sm font-semibold transition">
              Hủy
            </button>
            <button class="bg-[#b3b3b3] hover:bg-[#a0a0a0] text-white px-4 py-1.5 rounded text-sm font-semibold flex items-center gap-1 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
              In Bill
            </button>
            <button class="bg-[#b3b3b3] hover:bg-[#a0a0a0] text-white px-6 py-1.5 rounded text-sm font-semibold transition">
              Thanh toán
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar (Menu) -->
    <div class="w-72 border-l border-slate-200 flex flex-col shrink-0 bg-[#f8f9fa] shadow-[-2px_0_8px_rgba(0,0,0,0.05)] z-10">
      <div class="p-3 bg-[#e9ecef] border-b border-slate-200">
        <h2 class="text-center font-bold text-slate-700 text-sm uppercase tracking-wide">NHÓM THỰC ĐƠN</h2>
      </div>
      <div class="p-3">
        <div class="relative w-full">
          <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input type="text" class="block w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded text-sm focus:outline-none focus:border-sky-500 bg-white" placeholder="Tìm món" />
        </div>
      </div>
      <div class="flex-1 overflow-auto p-4 flex flex-col items-center pt-10">
        <div class="relative">
          <div class="w-16 h-12 bg-slate-200 rounded flex items-center justify-center mb-2">
            <div class="w-8 h-8 bg-white border border-slate-300 rounded shadow-sm"></div>
          </div>
          <div class="absolute -top-2 -right-3">
            <div class="bg-slate-300 text-white rounded-full w-6 h-4 flex items-center justify-center text-[10px]">...</div>
          </div>
        </div>
        <span class="text-xs font-semibold text-slate-600">Trống</span>
      </div>
    </div>
  </div>
</template>
