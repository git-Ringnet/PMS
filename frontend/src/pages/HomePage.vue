<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted, computed } from "vue";
import { useUiStore } from "@/stores/ui-store";
import { usePermission } from "@/composables/usePermission";
import defaultBg from "@/assets/hotel-bg.png";
import http from "@/services/http";
import PortalWallpaperModal from "@/components/PortalWallpaperModal.vue";

const router = useRouter();
const uiStore = useUiStore();
const { can, canAny, isSuperAdmin } = usePermission();
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
  <div class="relative w-full h-screen flex flex-col items-center justify-center overflow-hidden font-sans select-none">
    <!-- Fullscreen Background Image -->
    <div class="absolute inset-0 z-0">
      <img
        :src="currentBgSrc"
        alt="Hotel Background"
        class="w-full h-full transition-all duration-700"
        :style="{
          objectPosition: wallpaperPosition,
          objectFit: wallpaperFit
        }"
      />
      <!-- Configurable dark overlay for crisp and clear background -->
      <div
        class="absolute inset-0 bg-slate-950 transition-opacity duration-300 pointer-events-none"
        :style="{ opacity: wallpaperOverlay / 100 }"
      ></div>
    </div>

    <!-- Admin Wallpaper Button (chỉ hiển thị với SuperAdmin hoặc có quyền system.setting) -->
    <div v-if="canChangeWallpaper" class="absolute top-4 right-4 z-20">
      <button
        type="button"
        @click="isWallpaperModalOpen = true"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-black/40 hover:bg-black/60 text-white text-xs border border-white/20 backdrop-blur-md transition-all shadow-md cursor-pointer hover:scale-105 active:scale-95 select-none"
        title="Đổi hình nền Desktop / Portal"
      >
        <i class="fa-solid fa-image text-xs"></i>
        <span>Đổi hình nền</span>
      </button>
    </div>

    <!-- Main Portal Navigation Cards -->
    <div class="relative z-10 flex flex-wrap items-center justify-center gap-12 sm:gap-16 md:gap-20 px-6 max-w-5xl">
      <!-- 1. PROVISTA PMS Card -->
      <div 
        v-if="isSuperAdmin || canAny(['fo.booking.view', 'fo.frontdesk.view', 'hk.view', 'mgmt.report.view', 'system.setting'])"
        class="flex flex-col items-center group transition-all duration-1000"
        :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
        style="transition-delay: 100ms;"
      >
        <!-- Icon Square -->
        <button 
          @click="navigateTo('/pms')"
          class="w-36 h-36 sm:w-40 sm:h-40 bg-white rounded-3xl shadow-xl hover:shadow-2xl flex flex-col items-center justify-between p-6 sm:p-7 cursor-pointer border border-white/20 transition-all duration-500 hover:-translate-y-2 hover:scale-[1.03] active:scale-95 group-hover:shadow-sky-500/20"
        >
          <!-- Logo container -->
          <div class="flex-1 flex items-center justify-center">
            <div class="w-14 h-14 bg-[#0ea5e9] flex items-center justify-center rounded-lg rotate-45 transform-gpu transition-all duration-700 group-hover:rotate-[135deg]">
              <div class="w-7 h-7 bg-white -rotate-45 transform-gpu transition-all duration-700 group-hover:-rotate-[135deg]"></div>
            </div>
          </div>
          <!-- App label inside square -->
          <span class="text-[#0ea5e9] font-black text-xs sm:text-[13px] tracking-wider mt-1">PMS</span>
        </button>
        <!-- Label outside -->
        <span class="text-white text-xs sm:text-sm font-black tracking-widest uppercase mt-4 opacity-90 group-hover:opacity-100 group-hover:text-[#0ea5e9] transition-all duration-300">
          PROVISTA PMS
        </span>
      </div>

      <!-- 2. PROVISTA F&B Card -->
      <div 
        v-if="isSuperAdmin || canAny(['fb.view', 'fb.order.view', 'fb.order.create', 'fb.party.manage', 'fb.payment'])"
        class="flex flex-col items-center group transition-all duration-1000"
        :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
        style="transition-delay: 200ms;"
      >
        <!-- Icon Square -->
        <button 
          @click="navigateTo('/fnb')"
          class="w-36 h-36 sm:w-40 sm:h-40 bg-white rounded-3xl shadow-xl hover:shadow-2xl flex flex-col items-center justify-between p-6 sm:p-7 cursor-pointer border border-white/20 transition-all duration-500 hover:-translate-y-2 hover:scale-[1.03] active:scale-95 group-hover:shadow-sky-500/20"
        >
          <!-- Logo container -->
          <div class="flex-1 flex items-center justify-center">
            <div class="w-14 h-14 bg-[#0ea5e9] flex items-center justify-center rounded-lg rotate-45 transform-gpu transition-all duration-700 group-hover:rotate-[135deg]">
              <div class="w-7 h-7 bg-white -rotate-45 transform-gpu transition-all duration-700 group-hover:-rotate-[135deg]"></div>
            </div>
          </div>
          <!-- App label inside square -->
          <span class="text-[#0ea5e9] font-black text-xs sm:text-[13px] tracking-wider mt-1">POS</span>
        </button>
        <!-- Label outside -->
        <span class="text-white text-xs sm:text-sm font-black tracking-widest uppercase mt-4 opacity-90 group-hover:opacity-100 group-hover:text-[#0ea5e9] transition-all duration-300">
          PROVISTA F&B
        </span>
      </div>

      <!-- 3. PROVISTA SYSTEM Card -->
      <div 
        v-if="isSuperAdmin || canAny(['system.user.view', 'system.user.manage', 'system.setting', 'system.branch.manage'])"
        class="flex flex-col items-center group transition-all duration-1000"
        :class="isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8'"
        style="transition-delay: 300ms;"
      >
        <!-- Icon Square -->
        <button 
          @click="navigateTo('/system')"
          class="w-36 h-36 sm:w-40 sm:h-40 bg-slate-900/40 backdrop-blur-md rounded-3xl shadow-xl hover:shadow-2xl flex items-center justify-center p-6 sm:p-7 cursor-pointer border border-white/10 transition-all duration-500 hover:-translate-y-2 hover:scale-[1.03] hover:bg-slate-900/50 active:scale-95 group-hover:shadow-white/10 group-hover:border-white/20"
        >
          <!-- Gear setting SVG icon -->
          <svg class="w-14 h-14 text-white stroke-[1.25] transition-all duration-700 group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
        <!-- Label outside -->
        <span class="text-white text-xs sm:text-sm font-black tracking-widest uppercase mt-4 opacity-90 group-hover:opacity-100 group-hover:text-[#0ea5e9] transition-all duration-300">
          PROVISTA SYSTEM
        </span>
      </div>
    </div>

    <!-- Modal Cài đặt hình nền Portal -->
    <PortalWallpaperModal
      v-model:show="isWallpaperModalOpen"
      :current-wallpaper="wallpaperUrl"
      :current-position="wallpaperPosition"
      :current-fit="wallpaperFit"
      :current-overlay="wallpaperOverlay"
      @saved="handleWallpaperSaved"
    />
  </div>
</template>
