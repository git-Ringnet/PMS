<script setup>
import { ref } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'
import EmptyState from '../common/EmptyState.vue'
import SkeletonRow from '../common/SkeletonRow.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Phase 1 FIX: replace 2 separate date inputs with DateRangePicker component
const dateRange = ref({ start: '24/06/2026', end: '24/06/2026' })

const columns = [
  'ID', 'Outlet', 'Mã đơn', 'In về bếp', 'Giờ', 'Ngày',
  'Máy tính', 'Màn hình', 'Người dùng', 'Thao tác', 'Đặt trước', 'Mã phòng', 'Mô tả'
]
const records = ref([])
const isLoading = ref(false)

const loadData = () => {
  isLoading.value = true
  // Simulate API call
  setTimeout(() => {
    isLoading.value = false
    records.value = [] // For demo, keep empty to show empty state
    uiStore.showToast('Đã tải dữ liệu thành công', 'success')
  }, 1000)
}

const handleExport = () => {
  if (records.value.length === 0) {
    uiStore.showToast('Không có dữ liệu để xuất', 'warning')
  } else {
    uiStore.showToast('Đang xuất file Excel...', 'info')
  }
}
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 overflow-hidden">

    <!-- ── Toolbar ─────────────────────────────────────────── -->
    <div class="shrink-0 bg-white border-b border-slate-200">
      <div class="flex flex-wrap items-center gap-3 px-6 py-3">

        <!-- Phase 1: dùng DateRangePicker thay 2 input riêng -->
        <DateRangePicker v-model="dateRange" />

        <button @click="loadData" class="bg-sky-500 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-sky-600 active:scale-[0.98] transition-all shadow-sm shadow-sky-100 hover:shadow-md">
          Xem
        </button>

        <button @click="handleExport" class="flex items-center gap-1.5 bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-emerald-600 active:scale-[0.98] transition-all shadow-sm shadow-emerald-100 hover:shadow-md ml-auto">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Xuất excel
        </button>
      </div>
    </div>

    <!-- ── Table ───────────────────────────────────────────── -->
    <div class="flex-1 overflow-hidden bg-white flex flex-col mx-6 mt-6 mb-6 rounded-xl border border-slate-200 shadow-sm relative">
      <div class="overflow-x-auto overflow-y-auto flex-1 relative">
        <table class="w-full text-left border-collapse text-sm whitespace-nowrap">
          <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
              <th
                v-for="col in columns"
                :key="col"
                class="px-5 py-3.5 border-r border-slate-100 last:border-r-0"
              >
                {{ col }}
              </th>
            </tr>
          </thead>
          <tbody>
            <SkeletonRow v-if="isLoading" :columns="columns.length" :rows="5" />
            <tr v-else-if="records.length === 0">
              <td :colspan="columns.length" class="p-0">
                <EmptyState />
              </td>
            </tr>
            <tr
              v-else
              v-for="(item, idx) in records"
              :key="idx"
              class="border-b border-slate-100 hover:bg-sky-50/20 transition-colors cursor-default"
            >
              <td v-for="col in columns" :key="col" class="px-5 py-3 text-slate-700 border-r border-slate-100 last:border-r-0"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
