<template>
  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white">
    <!-- Sidebar -->
    <div class="w-64 border-r border-slate-200 flex flex-col p-4 shrink-0 bg-white shadow-[2px_0_8px_rgba(0,0,0,0.05)] z-10">
      <div class="rounded-xl overflow-hidden shadow-md border border-slate-200 mb-3 relative pb-[60%]">
        <!-- Mock image for the restaurant -->
        <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Nhà Hàng" class="absolute inset-0 w-full h-full object-cover" />
      </div>
      <h2 class="text-center font-bold text-slate-800 text-lg uppercase tracking-wide">NHÀ HÀNG</h2>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden bg-[#fafafa]">
      <!-- Top toolbar -->
      <div class="flex items-center justify-between p-4 shrink-0">
        <div class="flex-1"></div>
        <div class="flex-1 flex justify-center">
          <!-- Search box -->
          <div class="relative w-80">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input type="text" class="block w-full pl-10 pr-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white" placeholder="Tìm kiếm" />
          </div>
        </div>
        <div class="flex-1 flex justify-end gap-3">
          <!-- Settings Icon -->
          <div class="relative">
            <button @click="showSettings = !showSettings" class="p-1.5 text-slate-500 hover:text-slate-800 transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
            <!-- Settings Popover -->
            <div v-if="showSettings" class="absolute top-full right-0 mt-1 w-64 bg-white border border-slate-200 shadow-xl rounded-lg p-4 z-50">
              <div class="flex justify-between items-center mb-3 border-b border-slate-100 pb-2">
                <h3 class="font-semibold text-slate-800">Cài đặt hiển thị</h3>
                <button @click="resetToDefault" class="text-xs text-sky-500 hover:text-sky-600 font-medium transition-colors">Mặc định</button>
              </div>
              <div class="space-y-3">
                <div>
                  <label class="block text-sm font-medium text-slate-800 mb-3">Rộng</label>
                  <input type="range" v-model="cellWidth" min="100" max="400" step="10" class="w-full custom-range mb-2" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-800 mb-3">Chiều cao</label>
                  <input type="range" v-model="cellHeight" min="50" max="300" step="10" class="w-full custom-range mb-2" />
                </div>
              </div>
            </div>
          </div>
          <!-- Help Icon -->
          <button class="p-1.5 bg-sky-300 text-white rounded-md hover:bg-sky-400 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Grid Content -->
      <div class="flex-1 overflow-auto p-6 pt-2 relative" @click="showSettings = false">
        <div class="flex flex-wrap gap-4">
          <div v-for="table in 10" :key="table" 
               :style="{ width: cellWidth + 'px', height: cellHeight + 'px' }"
               class="bg-[#e0e0e0] rounded-xl shadow-sm border border-slate-200/60 flex flex-col p-3 hover:shadow-md transition-all cursor-pointer">
            <span class="font-semibold text-slate-800 text-sm">A{{ table }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const showSettings = ref(false)
const cellWidth = ref(200) // Default width
const cellHeight = ref(112) // Default height (h-28 equivalent)

const resetToDefault = () => {
  cellWidth.value = 200
  cellHeight.value = 112
}
</script>

<style scoped>
.custom-range {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  width: 100%;
}
.custom-range:focus {
  outline: none;
}
.custom-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 16px;
  width: 16px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #7dd3fc; /* sky-300 */
  cursor: pointer;
  margin-top: -6px; /* align center with the 4px track */
}
.custom-range::-moz-range-thumb {
  height: 14px;
  width: 14px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #7dd3fc; 
  cursor: pointer;
}
.custom-range::-webkit-slider-runnable-track {
  width: 100%;
  height: 4px;
  cursor: pointer;
  background: #bae6fd; /* sky-200 */
  border-radius: 2px;
}
.custom-range::-moz-range-track {
  width: 100%;
  height: 4px;
  cursor: pointer;
  background: #bae6fd;
  border-radius: 2px;
}
</style>
