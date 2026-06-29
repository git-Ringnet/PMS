<script setup>
import { ref, computed, defineAsyncComponent, watchEffect, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useReportStore } from '@/stores/report-store'

const router = useRouter()
const route = useRoute()
const reportStore = useReportStore()

// Lazy-load tab components
const BreakfastTab = defineAsyncComponent(() => import('./components/report/BreakfastTab.vue'))
const ManagementTab = defineAsyncComponent(() => import('./components/report/ManagementTab.vue'))
const PdfReportTab = defineAsyncComponent(() => import('./components/report/PdfReportTab.vue'))

// Tab definitions (matching the system's multi-tab structure)
const allTabs = [
  { id: 'breakfast', name: 'Xác nhận khách ăn sáng', component: 'breakfast' },
  { id: 'management', name: 'Báo cáo quản lý', component: 'management' },
  { id: 'invoice', name: 'Báo cáo doanh thu theo hoá đơn', component: 'pdf', config: { showPaymentMethod: true, showInvoiceType: true, showProductType: false, shiftLabel: 'Ca', dateType: 'range' } },
  { id: 'product', name: 'Báo cáo doanh thu sản phẩm', component: 'pdf', config: { dateType: 'range', showZone: true, showCounter: true, showShiftToggle: true, showShift: true, showTimeRange: true, showProductType: true, showInvoiceType: true } },
  { id: 'product-by-day', name: 'Báo cáo doanh thu sản phẩm theo ngày', component: 'pdf', config: { dateType: 'range', showZone: true, showCounter: true, showShiftToggle: true, showShift: true, showTimeRange: true, showProductType: true, showInvoiceType: true } },
  { id: 'product-by-item', name: 'Báo cáo doanh thu hàng bán theo sản phẩm', component: 'pdf', config: { dateType: 'range', showZone: true, showCounter: true, showShiftToggle: true, showShift: true, showTimeRange: true, showProductType: true, showInvoiceType: true } },
  { id: 'cashier', name: 'Báo cáo thu ngân FB', component: 'pdf', config: { dateType: 'range', showShift: true, showZone: true, showCounter: true, showUser: true, showPaymentMethod: true, showGroup: true, showSort: true, showSendToRoom: true, showEndShift: true } },
  { id: 'guest-arrival', name: 'Báo cáo khách đến', component: 'pdf', config: { dateType: 'single', showZone: true, showCompany: true, showBooking: true, showMainGuest: true } },
  { id: 'guest-departure', name: 'Báo cáo khách đi', component: 'pdf', config: { dateType: 'single', showZone: true, showCompany: true, showBooking: true, showMainGuest: true, showDetailsToggle: true, showRoomCharge: true } },
  { id: 'guest-staying', name: 'Báo cáo khách ở', component: 'pdf', config: { dateType: 'single', showZone: true, showCompany: true, showBooking: true, showMainGuest: true, showDetailsToggle: true, showRoomCharge: true, showVatToggles: true } },
  { id: 'breakfast-forecast', name: 'Báo cáo dự kiến khách ăn sáng', component: 'pdf', config: { dateType: 'range', showUser: true, showSort: true, showLateCheckin: true, showRoomInfo: true, showGroupByRegistration: true } },
  { id: 'breakfast-report', name: 'Báo cáo khách ăn sáng', component: 'pdf', config: { dateType: 'single', showNotBreakfast: true, showLateCheckin: true } },
  { id: 'revenue-detail', name: 'Báo cáo doanh thu chi tiết', component: 'pdf', config: { dateType: 'single', showOutlet: true, showCounter: true, showShiftToggle: true, showShift: true, showTimeRange: true, showReportType: true, showProductSelect: true, showDetailReportType: true } },
  { id: 'total-revenue', name: 'Báo cáo tổng doanh thu', component: 'pdf', config: { dateType: 'range', showTimeFrameToggle: true, showReportType: true, showIncludeFO: true } },
  { id: 'order-detail', name: 'Báo cáo chi tiết đơn FB', component: 'pdf', config: { dateType: 'range', showOutlet: true, showCounter: true, showShift: true, showShiftToggle: true, showTimeRange: true, showReportType: true, showProductSelect: true, showDetailReportType: true, showIncludeFO: true } },
  { id: 'room-charge-invoice', name: 'Báo cáo hoá đơn thanh toán về phòng', component: 'pdf', config: { dateType: 'range', showZone: true, showCounter: true, showShift: true, shiftLabel: 'Ca' } },
  { id: 'invoice-type', name: 'Báo cáo loại hoá đơn', component: 'pdf', config: { dateType: 'range', showZone: true, showCounter: true, showShift: true, showOrderType: true } },
  { id: 'room-sales-forecast', name: 'Báo cáo dự đoán bán phòng', component: 'pdf', config: { dateType: 'single', showBranch: true, showUser: true, showZone: true, showRoomType: true } }
]

const menuStructure = [
  { id: 'invoice', name: 'BÁO CÁO DOANH THU THEO HOÁ ĐƠN' },
  { 
    id: 'group_product', name: 'BÁO CÁO DOANH THU SẢN PHẨM', 
    children: [
      { id: 'product', name: 'BÁO CÁO DOANH THU SẢN PHẨM' },
      { id: 'product-by-day', name: 'BÁO CÁO DOANH THU SẢN PHẨM THEO NGÀY' },
      { id: 'product-by-item', name: 'BÁO CÁO DOANH THU HÀNG BÁN THEO SẢN PHẨM' }
    ]
  },
  { id: 'cashier', name: 'BÁO CÁO THU NGÂN FB' },
  { id: 'guest-arrival', name: 'BÁO CÁO KHÁCH ĐẾN' },
  { id: 'guest-departure', name: 'BÁO CÁO KHÁCH ĐI' },
  { id: 'guest-staying', name: 'BÁO CÁO KHÁCH Ở' },
  { id: 'breakfast-forecast', name: 'BÁO CÁO DỰ KIẾN KHÁCH ĂN SÁNG' },
  { id: 'breakfast-report', name: 'BÁO CÁO KHÁCH ĂN SÁNG' },
  { id: 'revenue-detail', name: 'BÁO CÁO DOANH THU CHI TIẾT' },
  { id: 'total-revenue', name: 'BÁO CÁO TỔNG DOANH THU' },
  { id: 'order-detail', name: 'BÁO CÁO CHI TIẾT ĐƠN FB' },
  { id: 'room-charge-invoice', name: 'BÁO CÁO HOÁ ĐƠN THANH TOÁN VỀ PHÒNG' },
  { id: 'invoice-type', name: 'BÁO CÁO LOẠI HOÁ ĐƠN' },
  { id: 'room-sales-forecast', name: 'BÁO CÁO DỰ ĐOÁN BÁN PHÒNG' }
]

// Open tabs management (for closeable multi-tab) from store
// If first time load and no open tabs, open management by default
if (reportStore.openTabs.length === 0) {
  reportStore.openTabs.push({ id: 'management', name: 'Báo cáo quản lý' })
  reportStore.activeTabId = 'management'
}

const activeTab = computed(() => allTabs.find(t => t.id === reportStore.activeTabId))

// Sync with route query when navigating from dropdown menu
watch(() => route.query.tab, (tabFromQuery) => {
  if (tabFromQuery && tabFromQuery !== reportStore.activeTabId) {
    // Find tab definition
    const tabDef = allTabs.find(t => t.id === tabFromQuery)
    if (tabDef) {
      // Add to open tabs if not already open
      if (!reportStore.openTabs.find(o => o.id === tabFromQuery)) {
        reportStore.openTabs.push({ id: tabDef.id, name: tabDef.name })
      }
      reportStore.activeTabId = tabFromQuery
    }
  } else if (!tabFromQuery && reportStore.activeTabId) {
    // Sync URL with active tab when just clicking "Báo cáo" from the top menu
    router.replace({ query: { tab: reportStore.activeTabId } })
  }
}, { immediate: true })

function selectTab(id) {
  reportStore.activeTabId = id
  router.replace({ query: { tab: id } })
}

function closeTab(id, e) {
  e.stopPropagation()
  const idx = reportStore.openTabs.findIndex(t => t.id === id)
  reportStore.openTabs = reportStore.openTabs.filter(t => t.id !== id)
  
  if (reportStore.activeTabId === id) {
    if (reportStore.openTabs.length > 0) {
      const newIdx = Math.max(0, idx - 1)
      selectTab(reportStore.openTabs[newIdx].id)
    } else {
      reportStore.activeTabId = null
      router.replace({ query: {} })
    }
  }
}
</script>

<template>
  <div class="flex flex-col h-full overflow-hidden bg-white">
    <!-- ── Tab Bar ───────────────────────────────────────── -->
    <div class="flex items-end bg-white border-b border-slate-200 overflow-x-auto shrink-0">
      
      <!-- Tabs -->
      <div
        v-for="tab in reportStore.openTabs"
        :key="tab.id"
        @click="selectTab(tab.id)"
        class="flex items-center gap-1.5 px-3 h-[41px] cursor-pointer border-r border-slate-200 shrink-0 text-xs font-medium transition-all select-none group relative"
        :class="reportStore.activeTabId === tab.id
          ? 'bg-sky-600 text-white shadow-inner'
          : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-800'"
      >
        <!-- Active underline accent -->
        <span v-if="reportStore.activeTabId === tab.id" class="absolute bottom-0 left-0 right-0 h-0.5 bg-sky-400"></span>
        <span class="whitespace-nowrap max-w-[180px] truncate">{{ tab.name }}</span>
        <button
          @click.stop="closeTab(tab.id, $event)"
          class="rounded p-0.5 transition-colors shrink-0"
          :class="reportStore.activeTabId === tab.id
            ? 'text-sky-200 hover:text-white hover:bg-sky-500'
            : 'text-slate-300 hover:text-slate-600 hover:bg-slate-200'"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 overflow-hidden">
      <template v-if="activeTab">
        <BreakfastTab v-if="activeTab.component === 'breakfast'" class="h-full" />
        <ManagementTab v-else-if="activeTab.component === 'management'" class="h-full" />
        <PdfReportTab
          v-else-if="activeTab.component === 'pdf'"
          :key="activeTab.id"
          :report-title="activeTab.name"
          v-bind="activeTab.config"
          class="h-full"
        />
        <div v-else class="h-full flex items-center justify-center text-slate-400 text-sm">
          Chọn tab để xem báo cáo
        </div>
      </template>
      <div v-else class="h-full flex items-center justify-center text-slate-400 text-sm flex-col gap-4">
        <div class="text-slate-500">Không có báo cáo nào đang mở.</div>
      </div>
    </div>
  </div>
</template>
