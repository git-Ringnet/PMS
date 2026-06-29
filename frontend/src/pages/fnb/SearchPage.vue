<template>
  <div class="flex flex-col h-[calc(100vh-48px)] bg-slate-50 overflow-hidden font-sans">
    <!-- Bento Stats -->
    <div class="grid grid-cols-5 gap-4 px-6 py-4 shrink-0 bg-slate-50 border-b border-slate-200">
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-xs text-slate-500 font-semibold mb-1 tracking-wider uppercase">Tổng đơn</div>
          <div class="text-2xl font-bold text-slate-800">0</div>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 shadow-inner">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-xs text-slate-500 font-semibold mb-1 tracking-wider uppercase">Đã thanh toán</div>
          <div class="text-2xl font-bold text-emerald-600">0</div>
        </div>
        <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 shadow-inner">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-xs text-slate-500 font-semibold mb-1 tracking-wider uppercase">Chưa thanh toán</div>
          <div class="text-2xl font-bold text-orange-500">0</div>
        </div>
        <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-500 shadow-inner">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-xs text-slate-500 font-semibold mb-1 tracking-wider uppercase">Tổng tiền</div>
          <div class="text-2xl font-bold text-sky-600">0</div>
        </div>
        <div class="w-10 h-10 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 shadow-inner">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-xs text-slate-500 font-semibold mb-1 tracking-wider uppercase">Tổng khách</div>
          <div class="text-2xl font-bold text-slate-700">0</div>
        </div>
        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shadow-inner">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
      </div>
    </div>

    <!-- Actions Bar -->
    <div class="flex items-center justify-between px-6 py-4 shrink-0 bg-white border-b border-slate-200 shadow-sm z-10">
      <div class="flex items-center gap-3">
        <button class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all duration-200 shadow-sm shadow-sky-200 hover:shadow-sky-300">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          Lọc dữ liệu
        </button>
        <div class="relative group">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-slate-400 group-focus-within:text-sky-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input type="text" class="pl-10 pr-4 py-2.5 w-72 border border-slate-200 rounded-lg text-sm text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all placeholder-slate-400" placeholder="Tìm kiếm mã đơn, quầy..." />
        </div>
      </div>
      <button class="bg-white border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-700 px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 transition-all duration-200 shadow-sm">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Xuất Excel
      </button>
    </div>

    <!-- Table Container -->
    <div class="flex-1 flex flex-col bg-white overflow-hidden mx-6 mt-6 mb-2 rounded-xl border border-slate-200 shadow-sm relative">
      <div class="flex-1 overflow-auto relative">
        <table class="w-max min-w-full text-sm text-left border-collapse">
          <thead class="sticky top-0 z-20 bg-slate-50/90 backdrop-blur-md">
            <tr>
              <th class="sticky left-0 z-30 bg-slate-50/90 backdrop-blur-md border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs w-[140px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                <div class="flex items-center gap-1.5 cursor-pointer hover:text-slate-800 transition-colors">
                  Mã tham chiếu
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </div>
              </th>
              <th class="sticky left-[140px] z-30 bg-slate-50/90 backdrop-blur-md border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs w-[120px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                <div class="flex items-center gap-1.5 cursor-pointer hover:text-slate-800 transition-colors">
                  Mã đơn
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </div>
              </th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[120px]">Mã hoá đơn</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[130px]">Mã thanh toán</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[150px]">Quầy</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Bàn</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Phòng</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[130px] text-right">Tổng tiền</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[100px] text-center">Số khách</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[100px] text-center">SL món</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Ngày tạo</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Ca</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Tên khách</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Nhân viên tạo</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Thu ngân</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[150px]">Trạng thái</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[120px]">HTTT</th>
              <th class="border-b border-slate-200 px-5 py-4 font-semibold text-slate-500 uppercase tracking-wider text-xs min-w-[130px]">Ghi chú</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <!-- Empty state row that takes full height -->
            <tr>
              <td colspan="18" class="p-0 h-[400px]">
                <div class="flex flex-col items-center justify-center h-full text-slate-400 gap-4">
                  <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center shadow-inner border border-slate-100">
                    <svg class="w-12 h-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                  </div>
                  <div class="text-center">
                    <h3 class="text-slate-800 font-semibold text-lg mb-1">Chưa có dữ liệu</h3>
                    <p class="text-sm text-slate-500 max-w-sm">Không tìm thấy đơn hàng nào khớp với điều kiện lọc của bạn. Hãy thử thay đổi bộ lọc.</p>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination & Bottom Actions -->
    <div class="flex items-center justify-between shrink-0 px-6 py-4 bg-[#f5f5f5]">
      <!-- Bottom Buttons -->
      <div class="flex items-center gap-3">
        <button class="bg-white border border-slate-200 text-slate-400 px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          Xóa đơn
        </button>
        <button class="bg-white border border-slate-200 text-slate-400 px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
          Sửa đơn
        </button>
        <button class="bg-white border border-slate-200 text-slate-400 px-5 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-2 cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          Lịch sử
        </button>
      </div>

      <!-- Pagination -->
      <div class="flex items-center gap-4 text-sm text-slate-600">
        <span class="font-medium">1-1 trên 1 đơn</span>
        <div class="flex gap-1.5">
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center border border-sky-500 rounded-md bg-sky-50 text-sky-600 font-semibold shadow-sm">1</button>
          <button class="w-8 h-8 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 cursor-not-allowed shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
        <select class="border border-slate-200 rounded-md px-3 py-1.5 bg-white text-slate-700 font-medium focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-200 shadow-sm">
          <option>100 / trang</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
</style>
