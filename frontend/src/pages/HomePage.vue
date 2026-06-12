<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth-store";

const router = useRouter();
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const currentTime = ref("");
const currentDate = ref("");
const isLoaded = ref(false);

const menuItems = [
  {
    name: "Đặt phòng",
    route: "/reservation",
    icon: "calendar",
    description: "Quản lý đặt phòng & sơ đồ phòng",
  },
  {
    name: "Lễ tân",
    route: "/frontdesk",
    icon: "concierge",
    description: "Check-in, check-out & tiếp khách",
  },
  {
    name: "Buồng phòng",
    route: "/housekeeping",
    icon: "bed",
    description: "Quản lý dọn phòng & buồng phòng",
  },
  {
    name: "Báo cáo quản lý",
    route: "/reports",
    icon: "chart",
    description: "Thống kê & báo cáo hoạt động",
  },
  {
    name: "Cấu hình hệ thống",
    route: "/config",
    icon: "settings",
    description: "Thiết lập & cấu hình hệ thống",
  },
];

function navigateTo(route) {
  router.push(route);
}

async function handleLogout() {
  if (confirm("Bạn có chắc chắn muốn đăng xuất?")) {
    await authStore.logout();
    router.push("/login");
  }
}

function updateTime() {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString("vi-VN", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
  currentDate.value = now.toLocaleDateString("vi-VN", {
    weekday: "long",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
}

let timer = null;
onMounted(() => {
  updateTime();
  timer = setInterval(updateTime, 1000);
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <div class="relative w-full h-screen overflow-hidden">
    <!-- Background Image -->
    <div class="absolute inset-0">
      <img src="@/assets/hotel-bg.png" alt="Hotel Background" class="w-full h-full object-cover" />
      <!-- Gradient Overlay -->
      <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/10 to-black/50"></div>
    </div>

    <!-- Top Navigation Bar -->
    <header class="relative z-20 flex items-center justify-between px-6 h-12 transition-all duration-700" :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'
      ">
      <!-- Logo -->
      <div class="flex items-center gap-3">
        <div
          class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-400 to-primary-700 flex items-center justify-center shadow-lg backdrop-blur-sm">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
      </div>

      <!-- Center Navigation -->
      <nav class="flex items-center gap-1 bg-black/30 backdrop-blur-md rounded-full px-2 py-1 border border-white/10">
        <button v-for="item in menuItems" :key="item.route" @click="navigateTo(item.route)"
          class="px-4 py-1.5 text-[13px] font-semibold text-white/90 rounded-full transition-all duration-300 cursor-pointer border-none bg-transparent hover:bg-white/15 hover:text-white hover:shadow-lg tracking-wide">
          {{ item.name.toUpperCase() }}
        </button>
      </nav>

      <!-- Right Side -->
      <div class="flex items-center gap-3">
        <!-- Notifications -->
        <button
          class="relative p-2 rounded-full bg-black/20 backdrop-blur-sm hover:bg-black/30 transition-all cursor-pointer border border-white/10">
          <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
          <span
            class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-[9px] flex items-center justify-center font-bold text-white shadow-lg">4</span>
        </button>

        <!-- User Box & Logout button -->
        <div class="flex items-center gap-2 animate-fade">
          <div
            class="flex items-center gap-2 text-xs text-white/80 bg-black/20 backdrop-blur-sm rounded-full px-3 py-1.5 border border-white/10">
            <span class="font-medium">MKT 1</span>
            <span class="text-white/40">•</span>
            <span>{{ currentUser?.name || 'Khách' }}</span>
            <span class="text-white/40">•</span>
            <span>Ca 2</span>
            <span class="text-white/40">•</span>
            <span class="text-primary-300 font-semibold">{{
              currentTime.slice(0, 5)
              }}</span>
          </div>
          <!-- Quick Logout Button -->
          <button 
            @click="handleLogout"
            class="p-2 rounded-full bg-black/20 backdrop-blur-sm hover:bg-red-600/20 text-white/80 hover:text-red-400 hover:border-red-500/30 transition-all cursor-pointer border border-white/10"
            title="Đăng xuất"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>

        <div
          class="w-6 h-4.5 bg-red-600 flex items-center justify-center rounded-sm shadow-sm relative overflow-hidden shrink-0 border border-red-700/10">
          <svg class="w-2.5 h-2.5 text-yellow-400 fill-current" viewBox="0 0 24 24">
            <path
              d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192L12 .587z">
            </path>
          </svg>
        </div>
      </div>
    </header>

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
              <svg v-if="item.icon === 'calendar'" class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <svg v-else-if="item.icon === 'concierge'" class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
              </svg>
              <svg v-else-if="item.icon === 'bed'" class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              <svg v-else-if="item.icon === 'chart'" class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <svg v-else-if="item.icon === 'settings'" class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <span class="text-white text-xs font-semibold tracking-wide">{{
              item.name
              }}</span>

            <!-- Hover description tooltip -->
            <div
              class="absolute -bottom-10 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
              <span class="text-[10px] text-white/70 bg-black/40 backdrop-blur-sm px-2 py-1 rounded-md">{{
                item.description }}</span>
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
