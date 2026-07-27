<script setup>
import { ref } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const dateRange = ref({ start: '24/06/2026', end: '24/06/2026' })
// Phase 1 FIX: replace CSS-hack toggle with proper button toggle (same pattern as BreakfastTab)
const notPrinted = ref(true)
const currentPage = ref(1)
const perPage = ref(10)

const columns = [
  { name: 'Mã HĐ',         search: true  },
  { name: 'Mã thanh toán', search: true  },
  { name: 'Ngày',          search: false },
  { name: 'Ngày thanh toán', search: false },
  { name: 'Tên khách',     search: true  },
  { name: 'Công ty',       search: true  },
  { name: 'Ghi chú',       search: true  },
  { name: 'HTTT',          search: true  },
  { name: 'Outlet',        search: true  },
  { name: 'Khu vực',       search: true  },
  { name: '% VAT',         search: false },
  { name: 'Thành tiền',    search: false },
]

const searchCustomer = ref('')

const handleAdd = () => uiStore.showToast('Chức năng thêm hoá đơn VAT đang phát triển', 'info')
const handleExport = () => uiStore.showToast('Đang tải file Excel...', 'info')
const handleSearch = () => uiStore.showToast('Đang tìm kiếm...', 'info')

// Phase 1 FIX: use explicit structure for VAT records
const records = ref([])
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 overflow-hidden">

    <!-- ── Toolbar ─────────────────────────────────────────── -->
    <div class="shrink-0 bg-white border-b border-slate-200">
      <div class="flex flex-wrap items-center gap-3 px-6 py-3 relative z-20">

        <!-- Date Range Picker -->
        <DateRangePicker v-model="dateRange" />

        <div class="w-px h-6 bg-slate-200 mx-0.5 hidden sm:block"></div>

        <!-- Toggle: Chưa in VAT -->
        <button
          @click="notPrinted = !notPrinted"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-semibold transition-all active:scale-[0.98]"
          :class="notPrinted
            ? 'bg-sky-50 border-sky-300 text-sky-700 shadow-sm shadow-sky-50'
            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
        >
          <span
            class="relative inline-flex items-center rounded-full transition-colors shrink-0"
            :class="notPrinted ? 'bg-sky-500' : 'bg-slate-200'"
            style="height:18px;width:32px;"
          >
            <span
              class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform"
              :class="notPrinted ? 'translate-x-4' : 'translate-x-1'"
            ></span>
          </span>
          Chưa in VAT
        </button>

        <!-- Tìm kiếm -->
        <button @click="handleSearch" class="bg-sky-500 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-sky-600 active:scale-[0.98] transition-all shadow-sm shadow-sky-100 hover:shadow-md">
          Tìm kiếm
        </button>

        <!-- Action buttons -->
        <div class="flex gap-2 ml-auto">
          <button disabled class="flex items-center gap-1.5 bg-slate-50 text-slate-400 px-3.5 py-2 rounded-lg text-sm font-medium border border-slate-200 cursor-not-allowed opacity-60">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Tạo hoá đơn điện tử
          </button>
          <button disabled class="flex items-center gap-1.5 bg-slate-50 text-slate-400 px-3.5 py-2 rounded-lg text-sm font-medium border border-slate-200 cursor-not-allowed opacity-60">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Huỷ hoá đơn
          </button>
          <button @click="handleExport" class="flex items-center gap-1.5 bg-emerald-500 text-white px-3.5 py-2 rounded-lg text-sm font-bold hover:bg-emerald-600 active:scale-[0.98] transition-all shadow-sm shadow-emerald-100 hover:shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Xuất excel
          </button>
        </div>
      </div>
    </div>

    <!-- ── Table ───────────────────────────────────────────── -->
    <div class="flex-1 overflow-hidden bg-white flex flex-col mx-6 mt-6 mb-6 rounded-xl border border-slate-200 shadow-sm relative">
      <div class="overflow-x-auto overflow-y-auto flex-1">
        <table class="w-full text-sm border-collapse whitespace-nowrap">
          <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
              <th class="px-3 py-3.5 text-center w-10 border-r border-slate-100">
                <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500" />
              </th>
              <th
                v-for="col in columns"
                :key="col.name"
                class="px-4 py-3.5 text-left border-r border-slate-100 last:border-r-0"
              >
                <div class="flex items-center gap-1.5">
                  <span>{{ col.name }}</span>
                  <svg v-if="col.search" class="w-3.5 h-3.5 text-slate-400 cursor-pointer hover:text-sky-500 shrink-0 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.length === 0">
              <td :colspan="columns.length + 1" class="h-64 text-center">
                <div class="flex flex-col items-center justify-center text-slate-400 gap-3">
                  <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-slate-500">Không có dữ liệu</p>
                    <p class="text-xs text-slate-400 mt-0.5">Chọn ngày và nhấn Tìm kiếm</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr
              v-else
              v-for="(item, idx) in records"
              :key="idx"
              class="border-b border-slate-100 hover:bg-sky-50/20 transition-colors cursor-default"
            >
              <td class="px-3 py-2.5 border-r border-slate-100 text-center">
                <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500" />
              </td>
              <td v-for="col in columns" :key="col.name" class="px-4 py-2.5 text-slate-700 border-r border-slate-100 last:border-r-0"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer & Pagination -->
      <div class="bg-slate-50/50 border-t border-slate-200 px-5 py-3 flex items-center justify-between shrink-0">
        <div class="text-sm font-semibold text-slate-600">
          Tổng: <span class="text-sky-600">0</span>
        </div>
        <div class="flex items-center gap-1.5">
          <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50 active:scale-95 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-sky-500 bg-sky-500 text-white text-sm font-semibold shadow-sm">1</button>
          <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 hover:bg-slate-50 active:scale-95 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </button>
          <select v-model="perPage" class="ml-2 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-600 font-medium transition cursor-pointer">
            <option :value="10">10 / trang</option>
            <option :value="20">20 / trang</option>
            <option :value="50">50 / trang</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>
