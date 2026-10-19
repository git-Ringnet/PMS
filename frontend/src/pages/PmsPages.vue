<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted, computed } from "vue";
import defaultBg from "@/assets/hotel-bg.png";
import http from "@/services/http";
import PortalWallpaperModal from "@/components/PortalWallpaperModal.vue";
import { usePermission } from "@/composables/usePermission";

const router = useRouter();
const { can, isSuperAdmin } = usePermission();
const isLoaded = ref(false);

const wallpaperUrl = ref(null);
const wallpaperPosition = ref('center center');
const wallpaperFit = ref('cover');
const wallpaperOverlay = ref(15);
const isWallpaperModalOpen = ref(false);

const canChangeWallpaper = computed(() => {
  return isSuperAdmin.value || can('system.setting');
});

const currentBgSrc = computed(() => {
  return wallpaperUrl.value || defaultBg;
});

async function loadWallpaper() {
  try {
    const res = await http.get('/portal-wallpaper');
    if (res.data?.success) {
      wallpaperUrl.value = res.data.wallpaper_url || null;
      wallpaperPosition.value = res.data.wallpaper_position || 'center center';
      wallpaperFit.value = res.data.wallpaper_fit || 'cover';
      wallpaperOverlay.value = typeof res.data.wallpaper_overlay === 'number' ? res.data.wallpaper_overlay : 15;
    }
  } catch (err) {
    console.error('Không thể tải cấu hình hình nền:', err);
  }
}

function handleWallpaperSaved(data) {
  if (typeof data === 'object' && data !== null) {
    wallpaperUrl.value = data.wallpaper_url || null;
    wallpaperPosition.value = data.wallpaper_position || 'center center';
    wallpaperFit.value = data.wallpaper_fit || 'cover';
    wallpaperOverlay.value = typeof data.wallpaper_overlay === 'number' ? data.wallpaper_overlay : 15;
  } else {
    wallpaperUrl.value = data;
  }
}

const menuItems = [
  {
    name: "Đặt Phòng",
    route: "/reservation",
    icon: "calendar",
    description: "Quản lý đặt phòng & sơ đồ phòng",
  },
  {
    name: "Lễ Tân",
    route: "/frontdesk",
    icon: "concierge",
    description: "Check-in, check-out & tiếp khách",
  },
  {
    name: "Buồng Phòng",
    route: "/housekeeping",
    icon: "bed",
    description: "Quản lý dọn phòng & buồng phòng",
  },
  {
    name: "Báo Cáo Quản Lý",
    route: "/reports",
    icon: "chart",
    description: "Thống kê & báo cáo hoạt động",
  },
  {
    name: "Cấu Hình Hệ Thống",
    route: "/config",
    icon: "settings",
    description: "Thiết lập & cấu hình hệ thống",
  },
];

function navigateTo(route) {
  router.push(route);
}

onMounted(() => {
  loadWallpaper();
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
});
</script>

<template>
  <div class="relative w-full h-[calc(100vh-3rem)] overflow-hidden">
    <!-- Background Image -->
    <div class="absolute inset-0">
      <img
        :src="currentBgSrc"
        alt="Hotel Background"
        class="w-full h-full transition-all duration-700"
        :style="{
          objectPosition: wallpaperPosition,
          objectFit: wallpaperFit
        }"
      />
      <!-- Dark Blur & Gradient Overlay -->
      <div class="absolute inset-0 bg-gradient-to-b from-slate-950/75 via-slate-900/60 to-slate-950/80 backdrop-blur-[2px] pointer-events-none"></div>
      <!-- Configurable dark overlay for crisp and clear background -->
      <div
        v-if="wallpaperOverlay > 0"
        class="absolute inset-0 bg-slate-950 transition-opacity duration-300 pointer-events-none"
        :style="{ opacity: wallpaperOverlay / 100 }"
      ></div>
    </div>

    <!-- Center Content - Welcome -->
    <div class="absolute inset-0 flex items-center justify-center z-10">
      <div class="text-center transition-all duration-1000 delay-300" :class="isLoaded
        ? 'opacity-100 translate-y-0 scale-100'
        : 'opacity-0 translate-y-8 scale-95'
        ">
        <!-- Quick Module Cards (Desktop hover) -->
        <div class="flex flex-wrap items-center justify-center gap-3.5 sm:gap-4 mt-8 px-4">
          <div
            v-for="(item, index) in menuItems"
            :key="item.route"
            class="transition-all duration-700"
            :style="{ transitionDelay: `${index * 100 + 400}ms` }"
            :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'"
          >
            <button
              @click="navigateTo(item.route)"
              class="group relative w-36 sm:w-38 h-32 sm:h-34 bg-white/15 backdrop-blur-xl border border-white/30 rounded-2xl flex flex-col items-center justify-center gap-2.5 cursor-pointer shadow-xl transition-all duration-200 ease-out hover:bg-white/25 hover:border-white/50 hover:shadow-2xl hover:scale-105 hover:-translate-y-1.5 active:scale-95"
            >
              <!-- Icon -->
              <div
                class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center group-hover:bg-white/30 transition-all duration-200 ease-out group-hover:scale-110 shadow-inner">
                <svg v-if="item.icon === 'calendar'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <svg v-else-if="item.icon === 'concierge'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                <svg v-else-if="item.icon === 'bed'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <svg v-else-if="item.icon === 'chart'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <svg v-else-if="item.icon === 'settings'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <span class="text-white text-xs sm:text-[13px] tracking-wide drop-shadow-[0_1px_3px_rgba(0,0,0,0.8)] leading-tight text-center px-1 transition-transform duration-200 ease-out group-hover:scale-[1.02]">{{
                item.name
                }}</span>

              <!-- Hover description tooltip -->
              <div
                class="absolute -bottom-10 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap pointer-events-none">
                <span class="text-xs text-white/95 bg-black/70 backdrop-blur-md px-3 py-1.5 rounded-lg border border-white/10 shadow-lg font-medium">{{
                  item.description }}</span>
              </div>
            </button>
          </div>
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
        v-if="canChangeWallpaper"
        type="button"
        @click="isWallpaperModalOpen = true"
        title="Đổi hình nền Desktop"
        class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 cursor-pointer border-none">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
      </button>
    </div>

    <!-- Portal Wallpaper Modal -->
    <PortalWallpaperModal
      :is-open="isWallpaperModalOpen"
      :current-wallpaper="wallpaperUrl"
      :current-position="wallpaperPosition"
      :current-fit="wallpaperFit"
      :current-overlay="wallpaperOverlay"
      @close="isWallpaperModalOpen = false"
      @saved="handleWallpaperSaved"
    />
  </div>
</template>
