<script setup>
import { ref, computed, defineAsyncComponent, watch, watchEffect, onMounted, onUnmounted } from 'vue'
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

const menuList = ref([
  { id: 'breakfast', name: 'XÁC NHẬN KHÁCH ĂN SÁNG' },
  { id: 'management', name: 'BÁO CÁO QUẢN LÝ' },
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
])

const searchQuery = ref('')
const isSidebarCollapsed = ref(localStorage.getItem('fnb_report_sidebar_collapsed') === 'true')

watch(isSidebarCollapsed, (val) => {
  localStorage.setItem('fnb_report_sidebar_collapsed', val.toString())
})

const expandedGroups = ref({
  group_product: true
})

const filteredMenu = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return menuList.value
  
  return menuList.value.map(item => {
    if (item.children) {
      const children = item.children.filter(c => c.name.toLowerCase().includes(query))
      if (children.length > 0) {
        return { ...item, children }
      }
    } else if (item.name.toLowerCase().includes(query)) {
      return item
    }
    return null
  }).filter(Boolean)
})

// Open tabs management (for closeable multi-tab) from store
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

function openReport(id) {
  const tabDef = allTabs.find(t => t.id === id)
  if (tabDef) {
    if (!reportStore.openTabs.find(o => o.id === id)) {
      reportStore.openTabs.push({ id: tabDef.id, name: tabDef.name })
    }
    selectTab(id)
  }
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

const handleKeydown = (e) => {
  if (e.key === 'Escape') {
    e.preventDefault()
    isSidebarCollapsed.value = !isSidebarCollapsed.value
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white font-sans text-xs">
    <!-- Left Sidebar: Report Categories -->
    <aside 
      class="border-r border-slate-200 flex flex-col shrink-0 bg-white select-none transition-all duration-300 ease-in-out"
      :class="isSidebarCollapsed ? 'w-14' : 'w-64'"
    >
      <!-- Header + Collapse Button -->
      <div class="flex items-center justify-between px-3 py-3 border-b border-slate-100">
        <span
          class="text-[11px] font-bold text-slate-400 tracking-widest uppercase transition-all duration-200 overflow-hidden whitespace-nowrap"
          :class="isSidebarCollapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
        >Báo cáo</span>
        <button
          @click="isSidebarCollapsed = !isSidebarCollapsed"
          class="p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors focus:outline-none cursor-pointer"
          :title="isSidebarCollapsed ? 'Mở rộng' : 'Thu gọn'"
        >
          <svg v-if="!isSidebarCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
          </svg>
        </button>
      </div>

      <!-- Search bar -->
      <div v-if="!isSidebarCollapsed" class="p-3 border-b border-slate-100 bg-slate-50/50">
        <div class="relative group">
          <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            class="pl-8 pr-3 py-1.5 w-full border border-slate-200 rounded-lg text-[11px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold"
            placeholder="Tìm kiếm báo cáo..."
          />
        </div>
      </div>

      <!-- Menu List -->
      <nav class="flex-1 overflow-y-auto p-2 space-y-1 scrollbar-thin">
        <div v-for="item in filteredMenu" :key="item.id" class="space-y-0.5">
          <!-- Item with children (Group) -->
          <div v-if="item.children">
            <button
              @click="expandedGroups[item.id] = !expandedGroups[item.id]"
              class="w-full flex items-center justify-between rounded-lg font-bold text-slate-500 hover:bg-slate-50 transition-colors text-left"
              :class="isSidebarCollapsed ? 'px-0 py-2 justify-center' : 'px-2.5 py-1.5'"
              :title="isSidebarCollapsed ? item.name : ''"
            >
              <div class="flex items-center gap-2">
                <!-- Folder Icon -->
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                </svg>
                <span v-if="!isSidebarCollapsed" class="text-[10px] uppercase tracking-wider">{{ item.name }}</span>
              </div>
              <svg
                v-if="!isSidebarCollapsed"
                class="w-3.5 h-3.5 transition-transform duration-200"
                :class="expandedGroups[item.id] ? 'rotate-90' : ''"
                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
            <div v-if="expandedGroups[item.id]" :class="isSidebarCollapsed ? 'space-y-1 pl-0 ml-0 border-none' : 'pl-2.5 mt-0.5 space-y-0.5 border-l border-slate-100 ml-3'">
              <button
                v-for="sub in item.children"
                :key="sub.id"
                @click="openReport(sub.id)"
                class="w-full text-left rounded-lg transition-all font-medium cursor-pointer flex items-center gap-2"
                :class="[
                  reportStore.activeTabId === sub.id
                    ? 'bg-[#e0f2fe] text-[#0369a1] border border-[#bae6fd] font-semibold'
                    : 'text-slate-600 hover:bg-slate-50 border border-transparent',
                  isSidebarCollapsed ? 'px-0 py-2 justify-center' : 'px-2.5 py-1.5'
                ]"
                :title="isSidebarCollapsed ? sub.name : ''"
              >
                <svg class="w-3.5 h-3.5 shrink-0" :class="reportStore.activeTabId === sub.id ? 'text-[#0369a1]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span v-if="!isSidebarCollapsed">{{ sub.name }}</span>
              </button>
            </div>
          </div>
 
          <!-- Single Item -->
          <button
            v-else
            @click="openReport(item.id)"
            class="w-full text-left rounded-lg transition-all font-bold cursor-pointer flex items-center gap-2"
            :class="[
              reportStore.activeTabId === item.id
                ? 'bg-[#e0f2fe] text-[#0369a1] border border-[#bae6fd] font-bold'
                : 'text-slate-600 hover:bg-slate-50 border border-transparent',
              isSidebarCollapsed ? 'px-0 py-2 justify-center' : 'px-2.5 py-1.5'
            ]"
            :title="isSidebarCollapsed ? item.name : ''"
          >
            <svg class="w-4 h-4 shrink-0" :class="reportStore.activeTabId === item.id ? 'text-[#0369a1]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span v-if="!isSidebarCollapsed">{{ item.name }}</span>
          </button>
        </div>
      </nav>
    </aside>

    <!-- Right Content: Tabs & Tab View -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
      <!-- ── Tab Bar ───────────────────────────────────────── -->
      <div class="flex items-end bg-slate-50 border-b border-slate-200 overflow-x-auto shrink-0 scrollbar-thin">
        <!-- Tabs -->
        <div
          v-for="tab in reportStore.openTabs"
          :key="tab.id"
          @click="selectTab(tab.id)"
          class="flex items-center gap-1.5 px-3 h-[38px] cursor-pointer border-r border-slate-200 shrink-0 text-xs font-semibold transition-all select-none group relative"
          :class="reportStore.activeTabId === tab.id
            ? 'bg-[#bdecfe] text-sky-800 shadow-sm border-t-2 border-t-sky-500 font-bold'
            : 'bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
        >
          <span class="whitespace-nowrap max-w-[180px] truncate">{{ tab.name }}</span>
          <button
            @click.stop="closeTab(tab.id, $event)"
            class="rounded p-0.5 transition-colors shrink-0"
            :class="reportStore.activeTabId === tab.id
              ? 'text-sky-600 hover:text-sky-900 hover:bg-sky-200/50'
              : 'text-slate-300 hover:text-slate-600 hover:bg-slate-200'"
          >
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
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
        <div v-else class="h-full flex items-center justify-center text-slate-400 text-sm flex-col gap-3">
          <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center border border-slate-100 text-slate-300 shadow-inner">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          </div>
          <div class="text-slate-500 font-semibold">Không có báo cáo nào đang mở.</div>
        </div>
      </div>
    </div>
  </div>
</template>
