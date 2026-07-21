<script setup>
import { ref, onMounted } from 'vue'
import DateRangePicker from '@/components/DateRangePicker.vue'
import EmptyState from '../common/EmptyState.vue'
import SkeletonRow from '../common/SkeletonRow.vue'
import LogDetailModal from './modals/LogDetailModal.vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchActivityLogs } from '@/services/activity-log-service'

const uiStore = useUiStore()

const today = new Date()
const dd = String(today.getDate()).padStart(2, '0')
const mm = String(today.getMonth() + 1).padStart(2, '0')
const yyyy = today.getFullYear()
const todayStr = `${dd}/${mm}/${yyyy}`

// Phase 1 FIX: replace 2 separate date inputs with DateRangePicker component
const dateRange = ref({ start: todayStr, end: todayStr })

const columns = [
  'ID', 'Outlet', 'Mã đơn', 'In về bếp', 'Giờ', 'Ngày',
  'Máy tính', 'Màn hình', 'Người dùng', 'Thao tác', 'Đặt trước', 'Mã phòng', 'Mô tả'
]
const records = ref([])
const isLoading = ref(false)

const showDetailModal = ref(false)
const selectedLog = ref(null)

const viewDetails = (item) => {
  selectedLog.value = item
  showDetailModal.value = true
}

const formatDateForApi = (dateStr) => {
  if (!dateStr) return ''
  const [d, m, y] = dateStr.split('/')
  return `${y}-${m}-${d}`
}

const loadData = async () => {
  isLoading.value = true
  try {
    const filters = {}
    if (dateRange.value?.start) {
      filters.date_from = formatDateForApi(dateRange.value.start)
    }
    if (dateRange.value?.end) {
      filters.date_to = formatDateForApi(dateRange.value.end)
    }
    filters.per_page = 100 // Tạm thời tải tối đa 100 log

    const response = await fetchActivityLogs(filters)
    if (response.data?.success) {
      // Decode JSON strings in old_values/new_values if needed
      records.value = response.data.data.map(item => {
        if (typeof item.old_values === 'string') {
          try { item.old_values = JSON.parse(item.old_values) } catch (e) {}
        }
        if (typeof item.new_values === 'string') {
          try { item.new_values = JSON.parse(item.new_values) } catch (e) {}
        }
        return item
      })
      
      if (records.value.length > 0) {
        uiStore.showToast('Đã tải dữ liệu thành công', 'success')
      }
    }
  } catch (error) {
    console.error('Lỗi khi tải log:', error)
    uiStore.showToast('Không thể tải lịch sử', 'error')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadData()
})

const getOrderCode = (item) => {
  if (item.target_type === 'FbOrder') {
    return item.target_id || '-'
  }
  if (item.target_type === 'FbOrderItem') {
    const val = item.new_values || item.old_values
    if (val && val.order_id) return val.order_id
  }
  return '-'
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
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">{{ item.id }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">
                <span v-if="item.module === 'fnb'">F&B</span>
                <span v-else>-</span>
              </td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100 font-semibold">{{ getOrderCode(item) }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">-</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">{{ item.created_time }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">{{ item.created_date }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">{{ item.ip_address }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">{{ item.component || '-' }}</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">
                <div class="flex flex-col">
                  <span class="font-medium">{{ item.user_name || '-' }}</span>
                  <span class="text-xs text-slate-500">{{ item.employee_code || '' }}</span>
                </div>
              </td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">
                <span 
                  class="px-2 py-0.5 text-xs font-semibold rounded-full"
                  :class="{
                    'bg-emerald-100 text-emerald-700': item.action === 'CREATE',
                    'bg-blue-100 text-blue-700': item.action === 'UPDATE',
                    'bg-rose-100 text-rose-700': item.action === 'DELETE'
                  }"
                >
                  {{ item.action }}
                </span>
              </td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">-</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100">-</td>
              <td class="px-5 py-3 text-slate-700 border-r border-slate-100 whitespace-normal min-w-[300px]">
                <div class="flex items-start justify-between gap-2">
                  <span>{{ item.description || '-' }}</span>
                  <button 
                    v-if="item.action === 'UPDATE' || item.action === 'DELETE' || item.old_values || item.new_values"
                    @click="viewDetails(item)"
                    class="shrink-0 px-2 py-1 text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 rounded border border-sky-200 transition-colors"
                  >
                    Chi tiết
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Detail Modal -->
    <LogDetailModal 
      :is-open="showDetailModal"
      :log-item="selectedLog"
      @close="showDetailModal = false"
    />
  </div>
</template>
