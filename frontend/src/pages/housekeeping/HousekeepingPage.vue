<script setup>
import { ref, computed, onMounted, onUnmounted, shallowRef, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  LayoutGrid, Printer, Receipt, FileSearch, Warehouse,
  UtensilsCrossed, PackageSearch, History, ChevronLeft,
  ChevronRight, Plus, X, ClipboardList, AlertTriangle,
  FileText, BedDouble, CalendarRange, Lock, BarChart3
} from '@lucide/vue'

// Tab components (lazy loaded via shallowRef for performance)
import PrintTasksTab from './components/PrintTasksTab.vue'
import PostBillHousekeepingTab from './components/PostBillHousekeepingTab.vue'
import SearchInvoiceTab from './components/SearchInvoiceTab.vue'
import InventoryTab from './components/InventoryTab.vue'
import CreateMenuTab from './components/CreateMenuTab.vue'
import LostAndFound from './components/LostAndFound.vue'
import OperationHistoryTab from './components/OperationHistoryTab.vue'

// Cross-module pages (reused from reservation, read-only)
import RoomMapPage from '@/pages/reservation/RoomMapPage.vue'
import RoomPlanPage from '@/pages/reservation/RoomPlanPage.vue'
import LockRoomPage from '@/pages/reservation/LockRoomPage.vue'
import ReportsPage from '@/pages/reports/ReportsPage.vue'
import { t } from '@/utils/i18n'

const route = useRoute()
const router = useRouter()

// --- Sidebar State ---
const sidebarCollapsed = ref(false)
const showDashboard = ref(true)

const tabs = computed(() => [
  { key: 'room-map', label: t('submenu.roomMap'), icon: LayoutGrid, component: RoomMapPage, group: 'phong' },
  { key: 'room-plan', label: t('submenu.roomPlan'), icon: CalendarRange, component: RoomPlanPage, group: 'phong' },
  { key: 'lock-room', label: t('submenu.lockRoom'), icon: Lock, component: LockRoomPage, group: 'phong' },
  { key: 'print-tasks', label: t('submenu.printRoomAssign'), icon: Printer, component: PrintTasksTab, group: 'service' },
  { key: 'add-service', label: t('submenu.addService'), icon: Receipt, component: PostBillHousekeepingTab, group: 'service' },
  { key: 'invoice-search', label: t('submenu.invoiceSearch'), icon: FileSearch, component: SearchInvoiceTab, group: 'service' },
  { key: 'inventory', label: t('submenu.inventory'), icon: Warehouse, component: InventoryTab, group: 'kho' },
  { key: 'create-menu', label: t('submenu.createMenu'), icon: UtensilsCrossed, component: CreateMenuTab, group: 'kho' },
  { key: 'lost-found', label: t('submenu.lostFound'), icon: PackageSearch, component: LostAndFound, group: 'other' },
  { key: 'history', label: t('submenu.actionHistory'), icon: History, component: OperationHistoryTab, group: 'other' },
  { key: 'reports', label: t('submenu.reports'), icon: BarChart3, component: ReportsPage, group: 'report' },
])

const activeTabKey = computed(() => route.query.tab || 'room-map')

const activeTab = computed(() => tabs.value.find(t => t.key === activeTabKey.value) || tabs.value[0])

const activeComponent = computed(() => activeTab.value.component)

function switchTab(key, queryParams = {}) {
  router.push({ path: '/housekeeping', query: { tab: key, ...queryParams } })
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

// --- Dashboard Mock Data ---
const dashboardStats = ref([
  { key: 'rooms', label: 'Phòng cần dọn', value: 12, icon: BedDouble, color: '#97D5FF', tab: 'print-tasks', query: { quickFilter: 'need_clean' } },
  { key: 'lost', label: 'Đồ thất lạc', value: 3, icon: PackageSearch, color: '#FBB040', tab: 'lost-found', query: { selectedMethod: 'Chưa xử lý' } },
  { key: 'stock', label: 'Tồn kho cảnh báo', value: 5, icon: AlertTriangle, color: '#F87171', tab: 'inventory', query: { warningOnly: 'true' } },
  { key: 'invoices', label: 'Hóa đơn hôm nay', value: 8, icon: FileText, color: '#34D399', tab: 'invoice-search', query: { triggerSearch: 'true' } },
])

// Animated count-up
const displayValues = ref({})
function animateCountUp(key, target) {
  let current = 0
  const step = Math.max(1, Math.ceil(target / 20))
  const interval = setInterval(() => {
    current += step
    if (current >= target) {
      current = target
      clearInterval(interval)
    }
    displayValues.value[key] = current
  }, 40)
}

// --- FAB ---
const fabOpen = ref(false)
const fabActions = [
  { label: 'Post bill nhanh', icon: ClipboardList, tab: 'add-service' },
  { label: 'Báo đồ thất lạc', icon: PackageSearch, tab: 'lost-found', query: { openAdd: 'true' } },
  { label: 'In phân công', icon: Printer, tab: 'print-tasks' },
]

function handleFabAction(action) {
  fabOpen.value = false
  switchTab(action.tab, action.query || {})
}

// --- Keyboard Shortcuts ---
function handleKeyDown(e) {
  // Esc closes FAB or modal
  if (e.key === 'Escape') {
    fabOpen.value = false
  }
  // Ctrl+1..0 switch tabs (1-9 + 0 for 10th)
  if (e.ctrlKey && e.key >= '0' && e.key <= '9') {
    e.preventDefault()
    const idx = e.key === '0' ? 9 : parseInt(e.key) - 1
    if (tabs.value[idx]) switchTab(tabs.value[idx].key)
  }
  // Ctrl+F focus search (if exists in active tab)
  if (e.ctrlKey && e.key === 'f') {
    const searchInput = document.querySelector('[data-hk-search]')
    if (searchInput) {
      e.preventDefault()
      searchInput.focus()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeyDown)
  // Trigger count-up animations
  dashboardStats.value.forEach(stat => {
    animateCountUp(stat.key, stat.value)
  })
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown)
})

// Close FAB on click outside
function closeFab(e) {
  if (fabOpen.value && !e.target.closest('.hk-fab-wrapper')) {
    fabOpen.value = false
  }
}
onMounted(() => document.addEventListener('click', closeFab))
onUnmounted(() => document.removeEventListener('click', closeFab))
</script>

<template>
  <div class="hk-root flex h-full overflow-hidden bg-white">

    <!-- ===== SIDEBAR ===== -->
    <aside
      class="hk-sidebar flex flex-col border-r border-slate-200 bg-white shrink-0 z-20"
      :class="sidebarCollapsed ? 'w-[60px]' : 'w-[220px]'"
    >
      <!-- Toggle Button -->
      <div class="flex items-center px-3 h-11 border-b border-slate-100 shrink-0"
           :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
        <span v-if="!sidebarCollapsed" class="text-[13px] font-extrabold tracking-wide text-slate-700 uppercase">
          Buồng Phòng
        </span>
        <button
          @click="toggleSidebar"
          class="w-7 h-7 flex items-center justify-center rounded-md hover:bg-[var(--hk-primary-bg)] text-slate-500 hover:text-[var(--hk-primary-dark)] transition-colors cursor-pointer bg-transparent border-none"
          :title="sidebarCollapsed ? 'Mở rộng' : 'Thu gọn'"
        >
          <ChevronRight v-if="sidebarCollapsed" class="w-4 h-4" />
          <ChevronLeft v-else class="w-4 h-4" />
        </button>
      </div>

      <!-- Tab Items -->
      <nav class="flex-1 overflow-y-auto py-2 hk-scroll">
        <template v-for="(tab, idx) in tabs" :key="tab.key">
          <!-- Group separator -->
          <div
            v-if="idx > 0 && tab.group !== tabs[idx - 1].group"
            class="mx-3 my-1.5 border-t border-slate-100"
          ></div>

          <button
            @click="switchTab(tab.key)"
            class="hk-sidebar-item w-full flex items-center gap-3 border-none bg-transparent cursor-pointer transition-all duration-200 relative group"
            :class="[
              sidebarCollapsed ? 'px-0 py-3 justify-center' : 'px-4 py-2.5',
              activeTabKey === tab.key
                ? 'bg-[var(--hk-primary-bg)] text-[var(--hk-primary-dark)] font-bold'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800'
            ]"
            :title="sidebarCollapsed ? `${tab.label} (Ctrl+${idx < 9 ? idx + 1 : 0})` : `Ctrl+${idx < 9 ? idx + 1 : 0}`"
          >
            <!-- Active indicator -->
            <div
              v-if="activeTabKey === tab.key"
              class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] rounded-r-full bg-[var(--hk-primary)] transition-all duration-300"
              :class="sidebarCollapsed ? 'h-6' : 'h-7'"
            ></div>

            <component
              :is="tab.icon"
              class="w-[18px] h-[18px] shrink-0 transition-colors duration-200"
              :class="activeTabKey === tab.key ? 'text-[var(--hk-primary-dark)]' : 'text-slate-400 group-hover:text-slate-600'"
              :stroke-width="activeTabKey === tab.key ? 2.5 : 2"
            />
            <span
              v-if="!sidebarCollapsed"
              class="text-[13px] whitespace-nowrap transition-opacity duration-200"
            >
              {{ tab.label }}
            </span>

            <!-- Shortcut badge (shown on hover when expanded) -->
            <span
              v-if="!sidebarCollapsed"
              class="ml-auto text-[10px] text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity font-mono"
            >
              Ctrl+{{ idx < 9 ? idx + 1 : 0 }}
            </span>
          </button>
        </template>
      </nav>

      <!-- Dashboard toggle (bottom) -->
      <div class="border-t border-slate-100 px-3 py-2 shrink-0" :class="sidebarCollapsed ? 'px-2' : ''">
        <button
          @click="showDashboard = !showDashboard"
          class="w-full flex items-center gap-2 bg-transparent border-none cursor-pointer text-slate-500 hover:text-slate-700 transition-colors"
          :class="sidebarCollapsed ? 'justify-center py-2' : 'py-1.5'"
          :title="showDashboard ? 'Ẩn tổng quan' : 'Hiện tổng quan'"
        >
          <LayoutGrid class="w-4 h-4 shrink-0" />
          <span v-if="!sidebarCollapsed" class="text-[12px] font-medium">Tổng quan</span>
        </button>
      </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Breadcrumb: Always visible -->
      <div class="shrink-0 bg-white border-b border-slate-100 px-5 py-2.5 flex items-center justify-between z-10">
        <div class="flex items-center gap-1.5 text-[12px]">
          <button
            @click="switchTab('room-map')"
            class="text-slate-500 hover:text-[var(--hk-primary-dark)] bg-transparent border-none cursor-pointer font-semibold transition-colors"
          >
            Buồng Phòng
          </button>
          <span class="text-slate-400">/</span>
          <span class="text-slate-700 font-bold text-xs uppercase tracking-wider">{{ activeTab.label }}</span>
        </div>
      </div>

      <!-- Dashboard Summary Bar -->
      <Transition name="hk-expand">
        <div v-if="showDashboard" class="shrink-0 border-b border-slate-100 bg-slate-50/50 px-5 py-3">
          <!-- Stat Cards -->
          <div class="grid grid-cols-4 gap-4">
            <button
              v-for="stat in dashboardStats"
              :key="stat.key"
              @click="switchTab(stat.tab, stat.query)"
              class="hk-card-lift flex items-center gap-3 bg-white rounded-xl border border-slate-200 p-3.5 cursor-pointer hover:border-[var(--hk-primary)] group transition-all duration-200"
            >
              <div
                class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:scale-110"
                :style="{ backgroundColor: stat.color + '20' }"
              >
                <component :is="stat.icon" class="w-5 h-5" :style="{ color: stat.color }" stroke-width="2" />
              </div>
              <div class="text-left">
                <div class="text-[22px] font-black text-slate-800 leading-none">
                  {{ displayValues[stat.key] ?? 0 }}
                </div>
                <div class="text-[11px] text-slate-500 font-medium mt-1">{{ stat.label }}</div>
              </div>
            </button>
          </div>
        </div>
      </Transition>

      <!-- Tab Content -->
      <div class="flex-1 overflow-hidden">
        <Transition name="hk-tab" mode="out-in">
          <component :is="activeComponent" :key="activeTabKey" />
        </Transition>
      </div>
    </div>

    <!-- ===== FAB (Floating Action Button) ===== -->
    <div class="hk-fab-wrapper fixed bottom-6 right-6 z-50 flex flex-col items-end gap-2">
      <!-- FAB Actions (shown when open) -->
      <Transition name="hk-fab-menu">
        <div v-if="fabOpen" class="flex flex-col items-end gap-2 mb-2">
          <TransitionGroup name="hk-fab-item" appear>
            <button
              v-for="(action, idx) in fabActions"
              :key="action.label"
              @click="handleFabAction(action)"
              class="flex items-center gap-2.5 bg-white border border-slate-200 rounded-full pl-4 pr-3 py-2.5 shadow-lg hover:shadow-xl hover:border-[var(--hk-primary)] transition-all duration-200 cursor-pointer group"
              :style="{ transitionDelay: `${idx * 60}ms` }"
            >
              <span class="text-[13px] font-bold text-slate-700 whitespace-nowrap group-hover:text-[var(--hk-primary-dark)]">
                {{ action.label }}
              </span>
              <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 transition-colors"
                   style="background-color: var(--hk-primary-bg);">
                <component :is="action.icon" class="w-4 h-4" style="color: var(--hk-primary-dark);" stroke-width="2.5" />
              </div>
            </button>
          </TransitionGroup>
        </div>
      </Transition>

      <!-- Main FAB Button -->
      <button
        @click.stop="fabOpen = !fabOpen"
        class="w-14 h-14 rounded-full flex items-center justify-center border-none cursor-pointer shadow-xl transition-all duration-300 hover:shadow-2xl hover:scale-105"
        :class="fabOpen ? 'bg-slate-700' : ''"
        :style="!fabOpen ? { background: 'var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))' } : {}"
      >
        <Plus
          class="w-6 h-6 text-white transition-transform duration-300"
          :class="fabOpen ? 'rotate-45' : ''"
          stroke-width="2.5"
        />
      </button>
    </div>
  </div>
</template>

<style>
/* ===== HK CSS Variables ===== */
:root {
  --hk-primary: #97D5FF;
  --hk-primary-dark: #4AABE8;
  --hk-primary-light: #D0ECFF;
  --hk-primary-bg: #F0F8FF;
  --hk-gradient: linear-gradient(135deg, #97D5FF, #6BC1F5);
}

/* ===== Sidebar transition ===== */
.hk-sidebar {
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== Modal transitions ===== */
.hk-modal-enter-active {
  animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-modal-leave-active {
  animation: hkModalOut 0.2s ease-in forwards;
}
@keyframes hkModalIn {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes hkModalOut {
  from { opacity: 1; transform: scale(1) translateY(0); }
  to { opacity: 0; transform: scale(0.95) translateY(10px); }
}

/* ===== Expand/Collapse ===== */
.hk-expand-enter-active,
.hk-expand-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}
.hk-expand-enter-from,
.hk-expand-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}
.hk-expand-enter-to,
.hk-expand-leave-from {
  max-height: 200px;
}

/* ===== Dropdown ===== */
.hk-dropdown-enter-active {
  animation: hkDropIn 0.2s ease-out;
}
.hk-dropdown-leave-active {
  animation: hkDropIn 0.15s ease-in reverse forwards;
}
@keyframes hkDropIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ===== Tab content transition ===== */
.hk-tab-enter-active {
  animation: hkTabIn 0.25s ease-out;
}
.hk-tab-leave-active {
  animation: hkTabOut 0.15s ease-in forwards;
}
@keyframes hkTabIn {
  from { opacity: 0; transform: translateX(12px); }
  to { opacity: 1; transform: translateX(0); }
}
@keyframes hkTabOut {
  from { opacity: 1; transform: translateX(0); }
  to { opacity: 0; transform: translateX(-12px); }
}

/* ===== Card hover lift ===== */
.hk-card-lift {
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.hk-card-lift:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px -5px rgb(151 213 255 / 0.3);
}

/* ===== Skeleton loading ===== */
.hk-skeleton {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: hkShimmer 1.5s ease-in-out infinite;
  border-radius: 6px;
}
@keyframes hkShimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ===== Custom scrollbar ===== */
.hk-scroll::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
.hk-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.hk-scroll::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
.hk-scroll::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}

/* ===== FAB animations ===== */
.hk-fab-menu-enter-active,
.hk-fab-menu-leave-active {
  transition: all 0.2s ease;
}
.hk-fab-menu-enter-from,
.hk-fab-menu-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

.hk-fab-item-enter-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-fab-item-leave-active {
  transition: all 0.15s ease-in;
}
.hk-fab-item-enter-from {
  opacity: 0;
  transform: translateX(20px) scale(0.9);
}
.hk-fab-item-leave-to {
  opacity: 0;
  transform: translateX(20px) scale(0.9);
}

/* ===== Pulse animation ===== */
@keyframes hkPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}
.hk-pulse {
  animation: hkPulse 2s ease-in-out infinite;
}

/* ===== Ripple effect ===== */
.hk-ripple {
  position: relative;
  overflow: hidden;
}
.hk-ripple::after {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle, var(--hk-primary) 10%, transparent 10.01%);
  transform: scale(10);
  opacity: 0;
  transition: transform 0.5s, opacity 0.5s;
}
.hk-ripple:active::after {
  transform: scale(0);
  opacity: 0.2;
  transition: 0s;
}
</style>
