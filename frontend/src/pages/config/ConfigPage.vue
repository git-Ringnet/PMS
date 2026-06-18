<script setup>
import { ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import HotelDefinition from './components/HotelDefinition.vue'
import RoomDefinition from './components/RoomDefinition.vue'
import SystemDefinition from './components/SystemDefinition.vue'
import RateSetup from './components/RateSetup.vue'

const router = useRouter()
const route = useRoute()

// View state: 'menu', 'hotel', 'room', 'system', 'rate'
const currentView = ref(route.query.view || 'menu')

// Update currentView when query changes
watch(() => route.query.view, (newVal) => {
  currentView.value = newVal || 'menu'
})

// Update query when currentView changes
watch(currentView, (newVal) => {
  if (route.query.view !== newVal) {
    const query = { ...route.query }
    if (newVal === 'menu') {
      delete query.view
      delete query.tab
    } else {
      query.view = newVal
      delete query.tab // reset tab when changing view
    }
    router.push({ query })
  }
})

// Sync active tab with route query
function updateActiveTab(newTab) {
  if (route.query.tab !== newTab) {
    router.replace({
      query: {
        ...route.query,
        tab: newTab
      }
    })
  }
}

function handleBack() {
  if (currentView.value === 'menu') {
    router.push('/')
  } else {
    currentView.value = 'menu'
  }
}
</script>

<template>
  <div class="h-full flex flex-col">
    <div class="flex-1 bg-slate-50 p-6 flex flex-col gap-4 min-h-0 text-slate-800">
    
    <!-- Header: Dynamic Back Arrow & View Title -->
    <div class="flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <button 
          @click="handleBack"
          class="flex items-center gap-2 text-slate-600 hover:text-slate-900 font-black bg-transparent border-none cursor-pointer text-base"
        >
          <svg class="w-5 h-5 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          <span class="uppercase tracking-wider text-sm">
            {{ 
              currentView === 'menu' ? 'Cấu hình hệ thống' : 
              currentView === 'hotel' ? 'Định nghĩa khách sạn' : 
              currentView === 'room' ? 'Định nghĩa phòng' : 
              currentView === 'system' ? 'Định nghĩa hệ thống' : 'Thiết lập giá'
            }}
          </span>
        </button>
      </div>
    </div>

    <!-- VIEW 1: LANDING CONFIGURATION MENU (6 Cards) -->
    <template v-if="currentView === 'menu'">
      <div class="flex-1 flex items-center justify-center min-h-[400px]">
        <div class="flex flex-wrap items-center justify-center gap-6 p-4">
          <!-- Card 1: Định nghĩa khách sạn -->
          <div 
            @click="currentView = 'hotel'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa khách sạn</span>
          </div>

          <!-- Card 2: Định nghĩa phòng -->
          <div 
            @click="currentView = 'room'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa phòng</span>
          </div>

          <!-- Card 3: Định nghĩa hệ thống -->
          <div 
            @click="currentView = 'system'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa hệ thống</span>
          </div>

          <!-- Card 4: Thiết lập giá -->
          <div 
            @click="currentView = 'rate'"
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Thiết lập giá</span>
          </div>

          <!-- Card 5: Định nghĩa channel manager -->
          <div 
            class="w-48 h-48 bg-white border border-slate-100 rounded-2xl flex flex-col items-center justify-center gap-4 cursor-pointer shadow-xs hover:shadow-lg hover:-translate-y-1 hover:border-slate-200 transition-all duration-300 group"
          >
            <div class="w-16 h-16 rounded-xl bg-slate-50 flex items-center justify-center group-hover:scale-105 transition-transform">
              <svg class="w-10 h-10 text-slate-700" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a3 3 0 00-3-3.12 3 3 0 00-3 3.12m-6-6a3 3 0 00-3-3.12 3 3 0 00-3 3.12M12 6.5a3 3 0 100-6 3 3 0 000 6z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12m-9 0a9 9 0 1118 0 9 9 0 01-18 0z" />
              </svg>
            </div>
            <span class="text-sm font-bold text-slate-700 text-center tracking-wide px-2">Định nghĩa channel manager</span>
          </div>


        </div>
      </div>
    </template>

    <!-- VIEW 2: DỊCH VỤ / TÁC VỤ CHI TIẾT -->
    <template v-else>
      <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 p-6 flex flex-col min-h-0">
        <HotelDefinition 
          v-if="currentView === 'hotel'" 
          :initialTab="route.query.tab || 'THÔNG TIN KHÁCH SẠN'"
          @update:activeTab="updateActiveTab"
        />
        <RoomDefinition 
          v-else-if="currentView === 'room'" 
          :initialTab="route.query.tab || 'TÊN LOẠI PHÒNG'"
          @update:activeTab="updateActiveTab"
        />
        <SystemDefinition 
          v-else-if="currentView === 'system'" 
          :initialTab="route.query.tab || 'HÌNH THỨC THANH TOÁN'"
          @update:activeTab="updateActiveTab"
        />
        <RateSetup 
          v-else-if="currentView === 'rate'" 
          :initialTab="route.query.tab || 'Mã giá phòng'"
          @update:activeTab="updateActiveTab"
        />
      </div>
    </template>
  </div>
</div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
