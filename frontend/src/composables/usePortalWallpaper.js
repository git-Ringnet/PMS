import { ref, computed } from 'vue'
import defaultBg from '@/assets/hotel-bg.png'
import http from '@/services/http'

const STORAGE_KEY = 'pms_portal_wallpaper'

/**
 * Đọc cấu hình hình nền đã lưu trong localStorage (nếu có)
 * Giúp hiển thị ngay lập tức hình nền đã đổi mà không bị giật/nháy hình nền mặc định
 */
function getInitialConfig() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw)
      return {
        wallpaperUrl: parsed.wallpaperUrl || null,
        wallpaperPosition: parsed.wallpaperPosition || 'center center',
        wallpaperFit: parsed.wallpaperFit || 'cover',
        wallpaperOverlay: typeof parsed.wallpaperOverlay === 'number' ? parsed.wallpaperOverlay : 15,
      }
    }
  } catch (e) {
    console.error('Lỗi đọc cache hình nền:', e)
  }
  return {
    wallpaperUrl: null,
    wallpaperPosition: 'center center',
    wallpaperFit: 'cover',
    wallpaperOverlay: 15,
  }
}

const initial = getInitialConfig()

// State singleton dùng chung cho toàn bộ các trang Portal (HomePage, PmsPages, FnbPages)
const wallpaperUrl = ref(initial.wallpaperUrl)
const wallpaperPosition = ref(initial.wallpaperPosition)
const wallpaperFit = ref(initial.wallpaperFit)
const wallpaperOverlay = ref(initial.wallpaperOverlay)
const isWallpaperModalOpen = ref(false)
let isFetching = false

function saveToCache(data) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      wallpaperUrl: data.wallpaper_url || null,
      wallpaperPosition: data.wallpaper_position || 'center center',
      wallpaperFit: data.wallpaper_fit || 'cover',
      wallpaperOverlay: typeof data.wallpaper_overlay === 'number' ? data.wallpaper_overlay : 15,
    }))
  } catch (e) {
    console.error('Lỗi lưu cache hình nền:', e)
  }
}

export function usePortalWallpaper() {
  const currentBgSrc = computed(() => {
    return wallpaperUrl.value || defaultBg
  })

  async function loadWallpaper(force = false) {
    if (isFetching && !force) return
    isFetching = true
    try {
      const res = await http.get('/portal-wallpaper')
      if (res.data?.success) {
        wallpaperUrl.value = res.data.wallpaper_url || null
        wallpaperPosition.value = res.data.wallpaper_position || 'center center'
        wallpaperFit.value = res.data.wallpaper_fit || 'cover'
        wallpaperOverlay.value = typeof res.data.wallpaper_overlay === 'number' ? res.data.wallpaper_overlay : 15

        saveToCache(res.data)
      }
    } catch (err) {
      console.error('Không thể tải cấu hình hình nền:', err)
    } finally {
      isFetching = false
    }
  }

  function handleWallpaperSaved(data) {
    if (typeof data === 'object' && data !== null) {
      wallpaperUrl.value = data.wallpaper_url || null
      wallpaperPosition.value = data.wallpaper_position || 'center center'
      wallpaperFit.value = data.wallpaper_fit || 'cover'
      wallpaperOverlay.value = typeof data.wallpaper_overlay === 'number' ? data.wallpaper_overlay : 15
      saveToCache(data)
    } else {
      wallpaperUrl.value = data
      saveToCache({ wallpaper_url: data })
    }
  }

  return {
    wallpaperUrl,
    wallpaperPosition,
    wallpaperFit,
    wallpaperOverlay,
    isWallpaperModalOpen,
    currentBgSrc,
    loadWallpaper,
    handleWallpaperSaved,
  }
}
