<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted, onUnmounted, computed } from "vue";
import { fetchOutlets } from "@/services/outlet-service";
import defaultBg from "@/assets/hotel-bg.png";
import http from "@/services/http";
import PortalWallpaperModal from "@/components/PortalWallpaperModal.vue";
import { usePermission } from "@/composables/usePermission";

const router = useRouter();
const { can, isSuperAdmin } = usePermission();
const isLoaded = ref(false);
const activeOutlets = ref([]);

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

const loadActiveOutlets = async () => {
  try {
    const res = await fetchOutlets();
    activeOutlets.value = (res.data || []).filter(o => o.is_active);
    updateMenuItems();
  } catch (err) {
    console.error("Lỗi khi tải danh sách outlet hoạt động:", err);
  }
};

const menuItems = ref([]);

const updateMenuItems = () => {
  const items = [];
  if (activeOutlets.value.length > 0) {
    activeOutlets.value.forEach(outlet => {
      items.push({
        name: outlet.name,
        route: `/fnb/restaurant?outlet_code=${outlet.code}`,
        icon: "restaurant",
        description: `Quản lý ${outlet.name}`,
      });
    });
  }

  items.push(
    {
      name: "Party",
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
  );

  menuItems.value = items;
};

function navigateTo(route) {
  router.push(route);
}

onMounted(async () => {
  loadWallpaper();
  updateMenuItems();
  await loadActiveOutlets();
  window.addEventListener('outlet-updated', loadActiveOutlets);
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
});

onUnmounted(() => {
  window.removeEventListener('outlet-updated', loadActiveOutlets);
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
        <div class="flex flex-wrap items-center justify-center gap-3.5 sm:gap-4 mt-8 px-4 max-w-6xl">
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
                <svg v-if="item.icon === 'restaurant'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 21h18" />
                  <path d="M3 7V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2" />
                  <path d="M5 21V7" />
                  <path d="M19 21V7" />
                  <path d="M9 21v-6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v6" />
                </svg>
                
                <svg v-else-if="item.icon === 'party'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8" />
                  <path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2 1 2 1" />
                  <path d="M2 21h20" />
                  <path d="M7 8v2" />
                  <path d="M12 8v2" />
                  <path d="M17 8v2" />
                  <path d="M7 4h.01" />
                  <path d="M12 4h.01" />
                  <path d="M17 4h.01" />
                </svg>
                
                <svg v-else-if="item.icon === 'search'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                
                <svg v-else-if="item.icon === 'chart'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                
                <svg v-else-if="item.icon === 'other'" class="w-6 h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="1.5" />
                  <circle cx="19" cy="12" r="1.5" />
                  <circle cx="5" cy="12" r="1.5" />
                </svg>
              </div>

              <!-- Title -->
              <span class="text-white text-xs sm:text-[13px] tracking-wide drop-shadow-[0_1px_3px_rgba(0,0,0,0.8)] leading-tight text-center px-1 transition-transform duration-200 ease-out group-hover:scale-[1.02]">
                {{ item.name }}
              </span>

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
      <div class="flex items-center gap-2 text-white/90 text-xs font-semibold bg-black/30 backdrop-blur-sm rounded-full px-3.5 py-1.5 border border-white/10">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
        </svg>
        <span>0236 566 000</span>
      </div>

      <!-- Version -->
      <div class="text-white/70 text-xs font-semibold flex items-center gap-2">
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
