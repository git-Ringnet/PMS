<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted } from "vue";

const router = useRouter();
const isLoaded = ref(false);

const menuItems = [
  {
    name: "Nhà Hàng",
    route: "/fnb/restaurant",
    icon: "restaurant",
    description: "Quản lý nhà hàng & khu vực ăn uống",
  },
  {
    name: "PARTY",
    route: "/fnb/party",
    icon: "party",
    description: "Quản lý sự kiện & tiệc",
  },
  {
    name: "Tìm Kiếm Đơn Hàng",
    route: "/fnb/search",
    icon: "search",
    description: "Tra cứu & tìm kiếm đơn hàng",
  },
  {
    name: "Báo Cáo",
    route: "/fnb/report",
    icon: "chart",
    description: "Thống kê doanh thu & báo cáo F&B",
  },
  {
    name: "Khác",
    route: "/fnb/other",
    icon: "other",
    description: "Các chức năng mở rộng khác",
  },
];

function navigateTo(route) {
  router.push(route);
}

onMounted(() => {
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
});
</script>

<template>
  <div class="relative w-full h-[calc(100vh-3rem)] overflow-hidden">
    <!-- Background Image -->
    <div class="absolute inset-0">
      <img src="@/assets/hotel-bg.png" alt="Hotel Background" class="w-full h-full object-cover" />
      <!-- Gradient Overlay -->
      <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/10 to-black/50"></div>
    </div>

    <!-- Center Content - Welcome -->
    <div class="absolute inset-0 flex items-center justify-center z-10">
      <div class="text-center transition-all duration-1000 delay-300" :class="isLoaded
        ? 'opacity-100 translate-y-0 scale-100'
        : 'opacity-0 translate-y-8 scale-95'
        ">
        <!-- Quick Module Cards (Desktop hover) -->
        <div class="flex gap-4 mt-8">
          <button v-for="(item, index) in menuItems" :key="item.route" @click="navigateTo(item.route)"
            class="group relative w-36 h-32 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl flex flex-col items-center justify-center gap-3 cursor-pointer transition-all duration-500 hover:bg-white/20 hover:scale-105 hover:border-white/40 hover:shadow-2xl"
            :style="{ transitionDelay: `${index * 100 + 500}ms` }" :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'
              ">
            <!-- Icon -->
            <div
              class="w-12 h-12 rounded-xl bg-white/15 flex items-center justify-center group-hover:bg-white/25 transition-all duration-300 group-hover:scale-110">
              
              <svg v-if="item.icon === 'restaurant'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
                <path d="M7 2v20" />
                <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
              </svg>
              
              <svg v-else-if="item.icon === 'party'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8" />
                <path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2 1 2 1" />
                <path d="M2 21h20" />
                <path d="M7 8v2" />
                <path d="M12 8v2" />
                <path d="M17 8v2" />
                <path d="M7 4h.01" />
                <path d="M12 4h.01" />
                <path d="M17 4h.01" />
              </svg>
              
              <svg v-else-if="item.icon === 'search'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              
              <svg v-else-if="item.icon === 'chart'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              
              <svg v-else-if="item.icon === 'other'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1.5" />
                <circle cx="19" cy="12" r="1.5" />
                <circle cx="5" cy="12" r="1.5" />
              </svg>

            </div>
            <span class="text-white text-xs font-semibold tracking-wide">{{ item.name }}</span>

            <!-- Hover description tooltip -->
            <div class="absolute -bottom-10 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
              <span class="text-[10px] text-white/70 bg-black/40 backdrop-blur-sm px-2 py-1 rounded-md">{{ item.description }}</span>
            </div>
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom Bar -->
    <div
      class="absolute bottom-0 left-0 right-0 z-20 flex items-center justify-between px-6 py-3 transition-all duration-700 delay-500"
      :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
        ">
      <!-- Phone -->
      <div class="flex items-center gap-2 text-white/60 text-xs bg-black/20 backdrop-blur-sm rounded-full px-3 py-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
        </svg>
        <span>0236 566 000</span>
      </div>

      <!-- Version -->
      <div class="text-white/40 text-[10px] flex items-center gap-2">
        <span>Version 4.0.8</span>
      </div>

      <!-- FAB Button -->
      <button
        class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 cursor-pointer border-none">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
      </button>
    </div>
  </div>
</template>
