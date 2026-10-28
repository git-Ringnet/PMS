<script setup>
import { useRouter } from "vue-router";
import { ref, onMounted, computed } from "vue";
import { useUiStore } from "@/stores/ui-store";
import { usePermission } from "@/composables/usePermission";
import PortalWallpaperModal from "@/components/PortalWallpaperModal.vue";
import { usePortalWallpaper } from "@/composables/usePortalWallpaper";
import { ROUTE_PERMISSIONS } from "@/utils/screen-permissions";

const router = useRouter();
const uiStore = useUiStore();
const { can, canAny, isSuperAdmin, isAdmin } = usePermission();
const isLoaded = ref(false);

const {
  wallpaperUrl,
  wallpaperPosition,
  wallpaperFit,
  wallpaperOverlay,
  isWallpaperModalOpen,
  currentBgSrc,
  loadWallpaper,
  handleWallpaperSaved,
} = usePortalWallpaper();

const canChangeWallpaper = computed(() => {
  return isSuperAdmin.value || isAdmin.value || canAny(['system.setting', 'config.hotel.edit']);
});

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
    <div v-if="canChangeWallpaper" class="absolute bottom-4 right-4 z-20">
      <button
        type="button"
        @click="isWallpaperModalOpen = true"
        class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300 cursor-pointer border-none"
        title="Đổi hình nền Desktop / Portal"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
      </button>
    </div>

    <!-- Main Portal Navigation Cards -->
    <div class="relative z-10 flex flex-wrap items-center justify-center gap-12 sm:gap-16 md:gap-20 px-6 max-w-5xl">
      <!-- 1. PROVISTA PMS Card -->
      <div 
        v-if="isSuperAdmin || canAny(ROUTE_PERMISSIONS['/pms'])"
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
            <img 
              src="/HKT_LOGO.png" 
              alt="PROVISTA PMS" 
              class="w-14 h-14 sm:w-16 sm:h-16 object-contain transform-gpu transition-transform duration-500 group-hover:scale-110 drop-shadow-sm"
            />
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
        v-if="isSuperAdmin || canAny(ROUTE_PERMISSIONS['/fnb'])"
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
            <img 
              src="/HKT_LOGO.png" 
              alt="PROVISTA F&B" 
              class="w-14 h-14 sm:w-16 sm:h-16 object-contain transform-gpu transition-transform duration-500 group-hover:scale-110 drop-shadow-sm"
            />
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
