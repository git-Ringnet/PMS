<template>
  <div class="flex flex-col h-[calc(100vh-48px)] bg-slate-50 p-6 overflow-hidden font-sans">
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-4 mb-6 shrink-0 flex-wrap bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100">
      <div class="flex items-center gap-3 flex-wrap">
        <DateRangePicker v-model="dateRange" />
        
        <select class="py-1.5 px-3 w-36 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold">
          <option>All Outlet</option>
        </select>
        
        <select class="py-1.5 px-3 w-36 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold">
          <option>Công ty</option>
        </select>
        
        <select class="py-1.5 px-3 w-36 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold">
          <option>Tất cả trạng thái</option>
        </select>
        
        <select class="py-1.5 px-3 w-32 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold">
          <option>Ca</option>
        </select>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="flex items-center p-1 bg-slate-100 rounded-lg text-xs font-bold">
          <button class="px-3.5 py-1 text-sky-700 bg-white shadow-xs rounded-md transition-all cursor-pointer">Table</button>
          <button class="px-3.5 py-1 text-slate-500 hover:text-slate-700 rounded-md transition-all cursor-pointer">Sheet</button>
        </div>

        <button @click="isAddPartyModalOpen = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all shadow-sm shadow-blue-100 hover:shadow-blue-200 border-none cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Thêm mới
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 flex flex-col border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
      <div class="flex-1 overflow-auto scrollbar-thin">
        <table class="w-max min-w-full text-xs text-left border-collapse">
          <thead class="sticky top-0 z-20 bg-slate-100 border-b border-slate-200">
            <tr class="text-slate-500 uppercase tracking-wider font-bold">
              <th class="py-3 px-3 text-center w-[60px]">
                <input type="checkbox" class="w-4 h-4 text-sky-500 border-slate-300 rounded focus:ring-sky-400">
              </th>
              <th class="py-3 px-3 w-[100px]">Mã TF</th>
              <th class="py-3 px-3 min-w-[120px]">Outlet</th>
              <th class="py-3 px-3 w-[120px]">Ngày đến</th>
              <th class="py-3 px-3 text-center w-[80px]">SL Bàn</th>
              <th class="py-3 px-3 min-w-[100px]">Bàn</th>
              <th class="py-3 px-3 w-[120px]">Trạng thái</th>
              <th class="py-3 px-3 min-w-[150px]">Công ty</th>
              <th class="py-3 px-3 min-w-[150px]">Tên khách</th>
              <th class="py-3 px-3 text-right w-[120px]">Tiền đặt cọc</th>
              <th class="py-3 px-3 text-center w-[90px]">Số khách</th>
              <th class="py-3 px-3 text-center w-[100px]">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="12" class="py-16 text-center text-slate-400 bg-white">
                <div class="flex flex-col items-center justify-center gap-3">
                  <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center shadow-inner border border-slate-100 text-slate-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-slate-800 font-bold text-sm mb-0.5">Chưa có tiệc/sự kiện</h3>
                    <p class="text-xs text-slate-500 max-w-sm">Không tìm thấy dữ liệu tiệc nào khớp với điều kiện lọc của bạn.</p>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between mt-6 shrink-0 text-xs">
      <div class="font-bold text-slate-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
        Tổng số bản ghi: <span class="text-sky-600">0</span>
      </div>
      <div class="flex items-center gap-4 text-slate-600">
        <select class="border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-700 text-xs font-semibold focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-200 shadow-sm">
          <option>50 / trang</option>
          <option>100 / trang</option>
        </select>
        
        <div class="flex gap-1.5">
          <button class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
          </button>
          <button class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <button class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
          <button class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
          </button>
        </div>

        <div class="flex items-center gap-2 ml-4">
          <span class="font-medium">Đến trang</span>
          <input type="text" class="w-10 border border-slate-200 rounded-lg px-1.5 py-1 text-center bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold" value="1" />
        </div>
      </div>
    </div>

    <!-- Modals -->
    <AddPartyModal :isOpen="isAddPartyModalOpen" @close="isAddPartyModalOpen = false" @save="handleSaveParty" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'
import AddPartyModal from './components/party/modals/AddPartyModal.vue'

const dateRange = ref({ start: '24/06/2026', end: '25/06/2026' })
const isAddPartyModalOpen = ref(false)

const handleSaveParty = (partyData) => {
  console.log('Saved party data:', partyData)
  // Logic to update party list
}
</script>

<style scoped>
</style>
