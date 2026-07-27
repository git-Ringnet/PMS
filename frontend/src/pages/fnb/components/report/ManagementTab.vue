<script setup>
import { ref } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'

const dateRange = ref({ start: '24/06/2026', end: '24/06/2026' })

const hours = Array.from({ length: 24 }, (_, i) => i)

const stats = {
  doneOrders: 0,
  servingTables: 0,
  customers: 0,
  revenue: '0 VND'
}
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 overflow-hidden">
    <!-- Toolbar -->
    <div class="shrink-0 flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-slate-200 bg-white shadow-sm relative z-20">
      <DateRangePicker v-model="dateRange" />
      <button class="bg-sky-500 text-white px-5 py-1.5 rounded-md text-sm font-semibold hover:bg-sky-600 active:scale-95 transition-all shadow-sm">
        Xem
      </button>
    </div>

    <!-- Dashboard Content -->
    <div class="flex-1 overflow-auto p-5">
      <!-- Top Stats Row -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
        <!-- Revenue Stat -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
          <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-50 rounded-bl-full -mr-4 -mt-4 opacity-50 group-hover:scale-110 transition-transform"></div>
          <div class="relative z-10">
            <div class="text-sm font-semibold text-slate-500 mb-1 flex items-center gap-2">
              <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Tổng doanh thu
            </div>
            <div class="text-2xl font-bold text-slate-800">{{ stats.revenue }}</div>
            <div class="text-xs text-emerald-600 mt-2 font-medium bg-emerald-50 inline-block px-2 py-0.5 rounded-md">+0% so với kỳ trước</div>
          </div>
        </div>

        <!-- Orders Stat -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
          <div class="absolute right-0 top-0 w-24 h-24 bg-sky-50 rounded-bl-full -mr-4 -mt-4 opacity-50 group-hover:scale-110 transition-transform"></div>
          <div class="relative z-10">
            <div class="text-sm font-semibold text-slate-500 mb-1 flex items-center gap-2">
              <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
              Đơn hàng
            </div>
            <div class="flex items-end gap-3">
              <div class="text-2xl font-bold text-slate-800">{{ stats.doneOrders }} <span class="text-sm text-slate-400 font-medium">hoàn thành</span></div>
            </div>
            <div class="text-xs text-sky-600 mt-2 font-medium bg-sky-50 inline-block px-2 py-0.5 rounded-md">{{ stats.servingTables }} bàn đang phục vụ</div>
          </div>
        </div>

        <!-- Customers Stat -->
        <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
          <div class="absolute right-0 top-0 w-24 h-24 bg-amber-50 rounded-bl-full -mr-4 -mt-4 opacity-50 group-hover:scale-110 transition-transform"></div>
          <div class="relative z-10">
            <div class="text-sm font-semibold text-slate-500 mb-1 flex items-center gap-2">
              <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              Khách hàng
            </div>
            <div class="text-2xl font-bold text-slate-800">{{ stats.customers }} <span class="text-sm text-slate-400 font-medium">người</span></div>
            <div class="text-xs text-amber-600 mt-2 font-medium bg-amber-50 inline-block px-2 py-0.5 rounded-md">Khách dùng bữa hôm nay</div>
          </div>
        </div>
      </div>

      <!-- Charts Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <!-- Chart 1: Revenue over time -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-700">Biểu đồ doanh thu</h3>
            <span class="text-xs font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded">Hôm nay</span>
          </div>
          <div class="p-5 flex-1 min-h-[220px] flex items-end gap-2">
            <!-- Animated Skeleton Chart -->
            <div v-for="i in 12" :key="i" class="flex-1 bg-slate-100 rounded-t-sm animate-pulse" :style="{ height: `${Math.random() * 80 + 20}%`, animationDelay: `${i * 0.1}s` }"></div>
          </div>
        </div>

        <!-- Chart 2: Top Products -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-700">Sản phẩm bán chạy</h3>
            <span class="text-xs font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded">Top 5</span>
          </div>
          <div class="p-5 flex-1 min-h-[220px] flex flex-col justify-center gap-4">
            <!-- Animated Skeleton Bars -->
            <div v-for="i in 5" :key="i" class="w-full flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-slate-100 shrink-0 animate-pulse"></div>
              <div class="flex-1 space-y-2">
                <div class="h-2 bg-slate-100 rounded-full w-1/3 animate-pulse"></div>
                <div class="h-2 bg-sky-100 rounded-full animate-pulse" :style="{ width: `${100 - i * 15}%` }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Chart 3: Revenue by Zone -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-700">Doanh thu theo khu vực</h3>
          </div>
          <div class="p-5 flex-1 min-h-[200px] flex items-center justify-center">
            <!-- Animated Skeleton Pie Chart -->
            <div class="w-32 h-32 rounded-full border-[16px] border-slate-100 border-t-sky-100 border-r-emerald-100 animate-[spin_3s_linear_infinite]"></div>
          </div>
        </div>

        <!-- Chart 4: Revenue by Staff -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-700">Doanh thu theo nhân viên</h3>
          </div>
          <div class="p-5 flex-1 min-h-[200px] flex flex-col justify-center gap-3">
             <div v-for="i in 4" :key="i" class="w-full flex items-center gap-3">
              <div class="flex-1 h-3 bg-slate-100 rounded-full animate-pulse" :style="{ width: `${Math.random() * 50 + 20}%` }"></div>
              <div class="w-12 h-3 bg-slate-100 rounded animate-pulse"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Time axis (Timeline) -->
      <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
        <h3 class="font-semibold text-slate-700 mb-4 text-sm">Hoạt động trong ngày</h3>
        <div class="flex items-center overflow-x-auto pb-2 relative">
          <div class="absolute top-1/2 left-0 right-0 h-px bg-slate-100 -translate-y-1/2 z-0"></div>
          <div v-for="h in hours" :key="h" class="flex-1 text-center min-w-[40px] relative z-10 flex flex-col items-center gap-1 group">
            <div class="w-2 h-2 rounded-full bg-slate-200 group-hover:bg-sky-400 group-hover:scale-150 transition-all cursor-pointer"></div>
            <div class="text-[10px] text-slate-400 font-medium">{{ h }}h</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
