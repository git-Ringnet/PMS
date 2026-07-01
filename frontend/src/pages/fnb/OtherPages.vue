<script setup>
import { ref, computed, watch, defineAsyncComponent, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const isSidebarCollapsed = ref(localStorage.getItem('fnb_other_sidebar_collapsed') === 'true')

watch(isSidebarCollapsed, (val) => {
  localStorage.setItem('fnb_other_sidebar_collapsed', val.toString())
})

const activeTab = computed({
  get: () => route.query.tab || 'log',
  set: (val) => {
    router.replace({ query: { ...route.query, tab: val } })
  }
})

// Sidebar Menu Items
const sidebarItems = [
  { id: 'log', name: 'GHI LOG', icon: 'file-text', shortcut: '1' },
  { id: 'inventory', name: 'KHO', icon: 'box', shortcut: '2' },
  { id: 'vat', name: 'IN VAT', icon: 'printer', shortcut: '3' },
  { id: 'settings', name: 'CÀI ĐẶT', icon: 'settings', shortcut: '4' }
]

const handleKeydown = (e) => {
  if (e.key === 'Escape') {
    e.preventDefault()
    isSidebarCollapsed.value = !isSidebarCollapsed.value
    return
  }
  if (e.ctrlKey) {
    const key = e.key
    const item = sidebarItems.find(i => i.shortcut === key)
    if (item) {
      e.preventDefault()
      activeTab.value = item.id
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// Lazy load components
const LogTab = defineAsyncComponent(() => import('./components/other/LogTab.vue'))
const InventoryTab = defineAsyncComponent(() => import('./components/other/InventoryTab.vue'))
const VatTab = defineAsyncComponent(() => import('./components/other/VatTab.vue'))
const SettingsTab = defineAsyncComponent(() => import('./components/other/SettingsTab.vue'))

</script>

<template>
  <div class="flex h-[calc(100vh-48px)] overflow-hidden bg-slate-50 font-sans select-none text-slate-800">
    <!-- ── Left Sidebar ──────────────────────────────────── -->
    <aside
      class="bg-white border-r border-slate-200 flex flex-col shrink-0 overflow-y-auto transition-all duration-300 ease-in-out"
      :class="isSidebarCollapsed ? 'w-14' : 'w-52'"
    >
      <!-- Section header + collapse btn -->
      <div class="flex items-center justify-between px-3 py-3 border-b border-slate-100">
        <span
          class="text-[11px] font-bold text-slate-400 tracking-widest uppercase transition-all duration-200 overflow-hidden"
          :class="isSidebarCollapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
        >Khác</span>
        <button
          @click="isSidebarCollapsed = !isSidebarCollapsed"
          class="p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors focus:outline-none"
          :title="isSidebarCollapsed ? 'Mở rộng sidebar' : 'Thu gọn sidebar'"
        >
          <svg v-if="!isSidebarCollapsed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
          </svg>
        </button>
      </div>

      <!-- Nav Items -->
      <nav class="flex-1 py-2 px-2 flex flex-col gap-0.5">
        <div
          v-for="item in sidebarItems"
          :key="item.id"
          @click="activeTab = item.id"
          class="relative flex items-center gap-2.5 rounded-lg cursor-pointer transition-all duration-150 font-medium text-[13px] group overflow-hidden"
          :class="[
            activeTab === item.id
              ? 'bg-[#e0f2fe] text-[#0369a1] border border-[#bae6fd] font-semibold'
              : 'text-slate-600 hover:bg-slate-50 border border-transparent',
            isSidebarCollapsed ? 'px-0 py-2 justify-center' : 'px-3 py-2'
          ]"
          :title="isSidebarCollapsed ? item.name + ' · Ctrl+' + item.shortcut : ''"
        >
          <!-- Active indicator bar -->
          <span
            v-if="activeTab === item.id"
            class="absolute left-0 top-2 bottom-2 w-[3px] rounded-r-full bg-[#0369a1]"
          ></span>

          <!-- Icon -->
          <svg v-if="item.icon === 'file-text'" class="w-[18px] h-[18px] shrink-0 transition-colors" :class="activeTab === item.id ? 'text-[#0369a1]' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <svg v-if="item.icon === 'box'" class="w-[18px] h-[18px] shrink-0 transition-colors" :class="activeTab === item.id ? 'text-[#0369a1]' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
          <svg v-if="item.icon === 'printer'" class="w-[18px] h-[18px] shrink-0 transition-colors" :class="activeTab === item.id ? 'text-[#0369a1]' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
          </svg>
          <svg v-if="item.icon === 'settings'" class="w-[18px] h-[18px] shrink-0 transition-colors" :class="activeTab === item.id ? 'text-[#0369a1]' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>

          <!-- Label -->
          <span
            class="truncate transition-all duration-200 flex-1"
            :class="isSidebarCollapsed ? 'w-0 opacity-0 pointer-events-none' : 'opacity-100'"
          >{{ item.name }}</span>

          <!-- Shortcut badge -->
          <span
            v-if="!isSidebarCollapsed"
            class="text-[9px] px-1.5 py-0.5 rounded border font-bold shrink-0 transition-colors"
            :class="activeTab === item.id
              ? 'bg-sky-100 border-[#bae6fd] text-[#0369a1]'
              : 'bg-slate-100 border-slate-200 text-slate-400'"
          >⌃{{ item.shortcut }}</span>
        </div>
      </nav>
    </aside>

    <!-- ── Main Content ───────────────────────────────────── -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-50 relative overflow-hidden">
      <Transition name="fade" mode="out-in">
        <LogTab      v-if="activeTab === 'log'"       key="log" />
        <InventoryTab v-else-if="activeTab === 'inventory'" key="inventory" />
        <VatTab      v-else-if="activeTab === 'vat'"   key="vat" />
        <SettingsTab v-else-if="activeTab === 'settings'" key="settings" />
      </Transition>
    </main>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateX(6px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateX(-6px);
}
</style>
