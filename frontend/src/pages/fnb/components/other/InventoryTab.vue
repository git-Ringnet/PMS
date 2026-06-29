<script setup>
import { ref, computed } from 'vue'
import EmptyState from '../common/EmptyState.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const currentMonth = ref('06/2026')

const daysInMonth = computed(() => {
  if (!currentMonth.value) return 30
  const [month, year] = currentMonth.value.split('/')
  if (!month || !year) return 30
  return new Date(parseInt(year), parseInt(month), 0).getDate()
})

const days = computed(() =>
  Array.from({ length: daysInMonth.value }, (_, i) => i + 1)
)

// Phase 1 FIX: add real search input state
const searchProduct = ref('')
const records = ref([])

const handleAdd = () => uiStore.showToast('Chức năng thêm sản phẩm kiểm kê đang phát triển', 'info')
const handleExport = () => uiStore.showToast('Đang tải file Excel...', 'info')
const handleAudit = () => uiStore.showToast('Tính năng kiểm kê hàng loạt đang bảo trì', 'warning')
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 overflow-hidden">

    <!-- ── Toolbar ─────────────────────────────────────────── -->
    <div class="shrink-0 bg-white border-b border-slate-200">
      <div class="flex flex-wrap items-center gap-3 px-6 py-3">

        <!-- Thêm -->
        <button @click="handleAdd" class="flex items-center gap-1.5 bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-600 active:scale-[0.98] transition-all shadow-sm shadow-sky-100 hover:shadow-md">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Thêm
        </button>

        <div class="w-px h-6 bg-slate-200 mx-0.5"></div>

        <!-- Tháng -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">Tháng</span>
          <div class="relative">
            <input
              type="text"
              v-model="currentMonth"
              placeholder="MM/YYYY"
              class="pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 w-28 bg-slate-50 focus:bg-white text-center transition font-medium text-slate-700"
            />
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <!-- Badge: số ngày trong tháng -->
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-100">
            {{ daysInMonth }} ngày
          </span>
        </div>

        <!-- Phase 1: search product input -->
        <div class="flex items-center gap-1.5 flex-1 min-w-[200px] max-w-sm">
          <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">Sản phẩm</span>
          <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              type="text"
              v-model="searchProduct"
              placeholder="Tìm sản phẩm..."
              class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 w-full transition text-slate-700"
            />
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2 ml-auto">
          <button @click="handleExport" class="flex items-center gap-1.5 bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-emerald-600 active:scale-[0.98] transition-all shadow-sm shadow-emerald-100 hover:shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Xuất excel
          </button>
          <button @click="handleAudit" class="flex items-center gap-1.5 bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-amber-600 active:scale-[0.98] transition-all shadow-sm shadow-amber-100 hover:shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Kiểm Kê Tồn Kho
          </button>
        </div>
      </div>
    </div>

    <!-- ── Table ───────────────────────────────────────────── -->
    <div class="flex-1 overflow-hidden bg-white flex flex-col mx-6 mt-6 mb-6 rounded-xl border border-slate-200 shadow-sm relative">
      <div class="overflow-x-auto overflow-y-auto flex-1 relative">
        <table class="min-w-max text-center border-collapse text-sm whitespace-nowrap">
          <thead class="sticky top-0 z-30">
            <!-- Header Row 1 -->
            <tr class="bg-slate-50">
              <th rowspan="2" class="px-4 py-3 border-b border-r border-slate-200/60 min-w-[250px] sticky left-0 bg-slate-50 z-50 shadow-[2px_0_5px_rgba(0,0,0,0.04)] text-center font-bold text-slate-600 text-xs uppercase tracking-wider">
                Sản phẩm
              </th>
              <th rowspan="2" class="px-4 py-3 border-b border-r border-slate-200/60 min-w-[80px] sticky left-[250px] bg-slate-50 z-50 shadow-[2px_0_5px_rgba(0,0,0,0.04)] text-center font-bold text-slate-600 text-xs uppercase tracking-wider">
                SLDK
              </th>

              <th
                v-for="day in days"
                :key="day"
                colspan="3"
                class="px-2.5 py-2 border-b border-r border-slate-200/60 text-center font-bold text-slate-600 text-xs z-20 relative"
              >
                {{ day }}
              </th>

              <th rowspan="2" class="px-4 py-3 border-b border-r border-slate-200/60 min-w-[72px] text-center font-bold text-slate-600 text-xs uppercase tracking-wider bg-slate-50 z-20 relative">SLN</th>
              <th rowspan="2" class="px-4 py-3 border-b border-r border-slate-200/60 min-w-[72px] text-center font-bold text-slate-600 text-xs uppercase tracking-wider bg-slate-50 z-20 relative">SLX</th>
              <th rowspan="2" class="px-4 py-3 border-b border-r border-slate-200/60 min-w-[72px] text-center font-bold text-slate-600 text-xs uppercase tracking-wider bg-slate-50 z-20 relative">SLC</th>
              <th rowspan="2" class="px-4 py-3 border-b border-slate-200/60 min-w-[100px] sticky right-0 bg-sky-50 z-50 shadow-[-2px_0_5px_rgba(0,0,0,0.04)] text-center font-bold text-sky-700 text-xs uppercase tracking-wider">
                Tồn Cuối
              </th>
            </tr>

            <!-- Header Row 2 -->
            <tr class="bg-slate-50/90">
              <template v-for="day in days" :key="'sub_'+day">
                <th class="px-2 py-1.5 border-b border-r border-slate-200/60 text-[10px] font-bold text-emerald-700 bg-slate-50/90 z-20 relative">Nhập</th>
                <th class="px-2 py-1.5 border-b border-r border-slate-200/60 text-[10px] font-bold text-rose-600 bg-slate-50/90 z-20 relative">Xuất</th>
                <th class="px-2 py-1.5 border-b border-r border-slate-200/60 text-[10px] font-bold text-amber-600 bg-slate-50/90 z-20 relative">Chuyển</th>
              </template>
            </tr>
          </thead>

          <tbody>
            <!-- Phase 1 FIX: search row with real input -->
            <tr class="bg-slate-50 sticky top-[73px] z-30 shadow-sm">
              <td class="px-3 py-1.5 border-b border-r border-slate-200/60 sticky left-0 bg-slate-50 z-50">
                <div class="relative">
                  <svg class="w-4 h-4 text-slate-400 absolute left-2 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  <input
                    v-model="searchProduct"
                    type="text"
                    placeholder="Tìm sản phẩm..."
                    class="pl-7 pr-2 py-1 w-full text-xs border border-slate-200 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition"
                  />
                </div>
              </td>
              <td class="px-3 py-1.5 border-b border-r border-slate-200/60 sticky left-[250px] bg-slate-50 z-50"></td>
              <td :colspan="days.length * 3 + 4" class="px-3 py-1.5 border-b border-slate-200/60 bg-slate-50 relative z-20"></td>
            </tr>

            <!-- Empty state -->
            <tr v-if="records.length === 0">
              <td :colspan="6 + (days.length * 3)" class="p-0">
                <EmptyState title="Kho đang trống" subtitle="Chưa có dữ liệu kiểm kê tồn kho cho tháng này." />
              </td>
            </tr>
            <tr
              v-else
              v-for="(item, idx) in records"
              :key="idx"
              class="border-b border-slate-100 hover:bg-sky-50/20 transition-colors cursor-default"
            >
              <!-- Cell data would go here -->
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
