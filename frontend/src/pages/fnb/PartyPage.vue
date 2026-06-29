<template>
  <div class="flex flex-col h-[calc(100vh-48px)] bg-slate-50 p-6 overflow-hidden font-sans">
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-4 mb-6 shrink-0 flex-wrap bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100">
      <div class="flex items-center gap-4 flex-wrap">
        <DateRangePicker v-model="dateRange" />
        
        <select class="py-2 px-3.5 w-40 border border-slate-200 rounded-lg text-sm text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all">
          <option>All Outlet</option>
        </select>
        
        <select class="py-2 px-3.5 w-40 border border-slate-200 rounded-lg text-sm text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all">
          <option>Công ty</option>
        </select>
        
        <select class="py-2 px-3.5 w-40 border border-slate-200 rounded-lg text-sm text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all">
          <option>Tất cả trạng thái</option>
        </select>
        
        <select class="py-2 px-3.5 w-40 border border-slate-200 rounded-lg text-sm text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all">
          <option>Ca</option>
        </select>
      </div>
      
      <div class="flex items-center gap-4">
        <div class="flex items-center p-1 bg-slate-100 rounded-lg text-sm font-medium">
          <button class="px-4 py-1.5 text-sky-600 bg-white shadow-sm rounded-md transition-all">Table</button>
          <button class="px-4 py-1.5 text-slate-500 hover:text-slate-700 rounded-md transition-all">Sheet</button>
        </div>

        <button class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all shadow-sm shadow-sky-200 hover:shadow-sky-300">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Thêm mới
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 flex flex-col border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
      <!-- Table Header -->
      <div class="grid grid-cols-[60px_100px_1fr_120px_80px_1fr_150px_150px_1fr_150px_100px_100px] bg-slate-50/90 backdrop-blur-md border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
        <div class="py-4 px-3 flex items-center justify-center">
          <input type="checkbox" class="w-4 h-4 text-sky-500 border-slate-300 rounded focus:ring-sky-400">
        </div>
        <div class="py-4 px-3 flex items-center">Mã TF</div>
        <div class="py-4 px-3 flex items-center">Outlet</div>
        <div class="py-4 px-3 flex items-center">Ngày đến</div>
        <div class="py-4 px-3 flex items-center justify-center">SL Bàn</div>
        <div class="py-4 px-3 flex items-center">Bàn</div>
        <div class="py-4 px-3 flex items-center">Trạng thái</div>
        <div class="py-4 px-3 flex items-center">Công ty</div>
        <div class="py-4 px-3 flex items-center">Tên khách</div>
        <div class="py-4 px-3 flex items-center justify-end">Tiền đặt cọc</div>
        <div class="py-4 px-3 flex items-center justify-center">Số khách</div>
        <div class="py-4 px-3 flex items-center justify-center">Thao tác</div>
      </div>
      
      <!-- Table Body Empty State -->
      <div class="flex-1 flex flex-col items-center justify-center h-full text-slate-400 gap-4 bg-white">
        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center shadow-inner border border-slate-100">
          <svg class="w-12 h-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
        </div>
        <div class="text-center">
          <h3 class="text-slate-800 font-semibold text-lg mb-1">Chưa có tiệc/sự kiện</h3>
          <p class="text-sm text-slate-500 max-w-sm">Không tìm thấy dữ liệu tiệc nào khớp với điều kiện lọc của bạn.</p>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-between mt-6 shrink-0 text-sm">
      <div class="font-semibold text-slate-700 bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm">
        Tổng số bản ghi: <span class="text-sky-600">0</span>
      </div>
      <div class="flex items-center gap-4 text-slate-600">
        <select class="border border-slate-200 rounded-lg px-3 py-2 bg-white text-slate-700 font-medium focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-200 shadow-sm">
          <option>50 / trang</option>
          <option>100 / trang</option>
        </select>
        
        <div class="flex gap-1.5">
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
          </button>
        </div>

        <div class="flex items-center gap-2 ml-4">
          <span class="font-medium">Đến trang</span>
          <input type="text" class="w-14 border border-slate-200 rounded-lg px-2 py-1.5 text-center bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all" value="1" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'

const dateRange = ref({ start: '24/06/2026', end: '25/06/2026' })
</script>

<style scoped>
</style>
