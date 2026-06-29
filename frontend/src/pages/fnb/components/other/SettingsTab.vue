<script setup>
import { ref } from 'vue'
import OutletDefinition from './settings/OutletDefinition.vue'
import TableDefinition from './settings/TableDefinition.vue'
import MenuDefinition from './settings/MenuDefinition.vue'
import CompanyDefinition from './settings/CompanyDefinition.vue'

const currentSubView = ref(null)

const settings = [
  { 
    id: 'outlet', 
    name: 'Định nghĩa điểm bán', 
    desc: 'Quản lý danh sách các điểm bán hàng',
    icon: 'shop',
    color: 'sky'
  },
  { 
    id: 'table', 
    name: 'Định nghĩa bàn', 
    desc: 'Sơ đồ và vị trí bàn theo khu vực',
    icon: 'table',
    color: 'emerald'
  },
  { 
    id: 'menu', 
    name: 'Định nghĩa thực đơn', 
    desc: 'Quản lý món ăn, đồ uống và giá bán',
    icon: 'book',
    color: 'amber'
  },
  { 
    id: 'company', 
    name: 'Định nghĩa công ty', 
    desc: 'Quản lý thông tin công ty và đối tác',
    icon: 'building',
    color: 'indigo'
  },
  { 
    id: 'other', 
    name: 'Khác', 
    desc: 'Các cài đặt cấu hình phụ trợ khác',
    icon: 'list',
    color: 'slate'
  }
]

const handleSelectSetting = (id) => {
  if (id === 'other') {
    alert('Tính năng cài đặt cấu hình phụ trợ khác đang được phát triển...')
    return
  }
  currentSubView.value = id
}
</script>

<template>
  <div class="h-full bg-slate-50 flex flex-col overflow-hidden">
    <Transition name="fade" mode="out-in">
      <!-- Main configuration list -->
      <div v-if="!currentSubView" class="h-full overflow-y-auto flex items-start justify-center p-6">
        <div class="w-full max-w-5xl mt-12">
          <!-- Header -->
          <div class="mb-10 text-center">
            <h2 class="text-2xl font-bold text-slate-800">Cài đặt hệ thống F&B</h2>
            <p class="text-slate-500 mt-2 text-sm">Quản lý cấu hình, điểm bán, thực đơn và các định nghĩa khác</p>
          </div>

          <!-- Grid Layout -->
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 justify-center">
            <div 
              v-for="item in settings" 
              :key="item.id"
              @click="handleSelectSetting(item.id)"
              class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col items-center text-center cursor-pointer shadow-sm hover:shadow-xl hover:-translate-y-1.5 hover:border-sky-300 transition-all duration-300 group relative overflow-hidden"
            >
              <!-- Hover background effect -->
              <div 
                class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"
                :class="{
                  'bg-gradient-to-b from-sky-50/50 to-transparent': item.color === 'sky',
                  'bg-gradient-to-b from-emerald-50/50 to-transparent': item.color === 'emerald',
                  'bg-gradient-to-b from-amber-50/50 to-transparent': item.color === 'amber',
                  'bg-gradient-to-b from-indigo-50/50 to-transparent': item.color === 'indigo',
                  'bg-gradient-to-b from-slate-50/50 to-transparent': item.color === 'slate'
                }"
              ></div>

              <!-- Icon container -->
              <div 
                class="w-16 h-16 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300 shadow-sm relative z-10"
                :class="{
                  'bg-sky-100 text-sky-600': item.color === 'sky',
                  'bg-emerald-100 text-emerald-600': item.color === 'emerald',
                  'bg-amber-100 text-amber-600': item.color === 'amber',
                  'bg-indigo-100 text-indigo-600': item.color === 'indigo',
                  'bg-slate-100 text-slate-600': item.color === 'slate'
                }"
              >
                <svg v-if="item.icon === 'shop'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <svg v-else-if="item.icon === 'table'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18M9 21V9" />
                </svg>
                <svg v-else-if="item.icon === 'book'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <svg v-else-if="item.icon === 'list'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg v-else-if="item.icon === 'building'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              
              <!-- Text -->
              <h3 class="text-[15px] font-bold text-slate-700 tracking-wide mb-1.5 relative z-10 group-hover:text-slate-900 transition-colors">
                {{ item.name }}
              </h3>
              <p class="text-xs text-slate-500 leading-relaxed relative z-10 group-hover:text-slate-600 transition-colors">
                {{ item.desc }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Definition screens views -->
      <OutletDefinition v-else-if="currentSubView === 'outlet'" @back="currentSubView = null" />
        <TableDefinition v-else-if="currentSubView === 'table'" @back="currentSubView = null" />
        <MenuDefinition v-else-if="currentSubView === 'menu'" @back="currentSubView = null" />
        <CompanyDefinition v-else-if="currentSubView === 'company'" @back="currentSubView = null" />
    </Transition>
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
