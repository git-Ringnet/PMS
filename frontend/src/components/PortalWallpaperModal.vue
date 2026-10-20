<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { usePermission } from '@/composables/usePermission'
import http from '@/services/http'
import defaultBg from '@/assets/hotel-bg.png'

const { canAny, isSuperAdmin, isAdmin } = usePermission()
const canModifyWallpaper = computed(() => {
  return isSuperAdmin.value || isAdmin.value || canAny(['system.setting', 'config.hotel.edit'])
})

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  currentWallpaper: {
    type: String,
    default: null
  },
  currentPosition: {
    type: String,
    default: 'center center'
  },
  currentFit: {
    type: String,
    default: 'cover'
  },
  currentOverlay: {
    type: Number,
    default: 15
  }
})

const emit = defineEmits(['update:show', 'saved'])
const uiStore = useUiStore()

const activeTab = ref('upload') // 'upload' | 'url'
const previewUrl = ref(null)
const selectedFile = ref(null)
const inputUrl = ref('')
const fileInputRef = ref(null)
const isSubmitting = ref(false)

const verticalPercent = ref(50) // 0 (top) to 100 (bottom)
const wallpaperFit = ref('cover') // 'cover' | 'contain'
const overlayPercent = ref(15) // 0 to 80%
const imageDimensions = ref(null) // { width: 0, height: 0 }

const appliedPosition = computed(() => `50% ${verticalPercent.value}%`)

function updateImageDimensions(url) {
  if (!url) {
    imageDimensions.value = null
    return
  }
  const img = new Image()
  img.onload = () => {
    imageDimensions.value = { width: img.naturalWidth, height: img.naturalHeight }
  }
  img.onerror = () => {
    imageDimensions.value = null
  }
  img.src = url
}

function parsePositionToPercent(posStr) {
  if (!posStr) return 50
  const str = String(posStr).toLowerCase().trim()
  if (str === 'center top' || str === 'top') return 15
  if (str === 'center bottom' || str === 'bottom') return 85
  if (str === 'center center' || str === 'center') return 50
  const parts = str.split(' ')
  if (parts.length >= 2) {
    const yPart = parts[1]
    const match = yPart.match(/(\d+)%/)
    if (match) return parseInt(match[1], 10)
  }
  const anyMatch = str.match(/(\d+)%/)
  if (anyMatch) return parseInt(anyMatch[1], 10)
  return 50
}

watch(() => props.show, (isOpen) => {
  if (isOpen) {
    const initialUrl = props.currentWallpaper || defaultBg
    previewUrl.value = initialUrl
    updateImageDimensions(initialUrl)
    selectedFile.value = null
    inputUrl.value = props.currentWallpaper && !props.currentWallpaper.startsWith('/uploads/') ? props.currentWallpaper : ''
    activeTab.value = 'upload'
    verticalPercent.value = parsePositionToPercent(props.currentPosition)
    wallpaperFit.value = props.currentFit || 'cover'
    overlayPercent.value = typeof props.currentOverlay === 'number' ? props.currentOverlay : 15
  }
})

function handleFileSelect(e) {
  const file = e.target.files?.[0]
  if (!file) return

  if (file.size > 10 * 1024 * 1024) {
    uiStore.showToast('Dung lượng ảnh không được vượt quá 10MB!', 'error')
    return
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg']
  if (!validTypes.includes(file.type)) {
    uiStore.showToast('Chỉ chấp nhận định dạng ảnh JPG, PNG hoặc WEBP!', 'error')
    return
  }

  selectedFile.value = file
  const reader = new FileReader()
  reader.onload = (event) => {
    const dataUrl = event.target?.result
    previewUrl.value = dataUrl
    updateImageDimensions(dataUrl)
  }
  reader.readAsDataURL(file)
}

function handleUrlInput() {
  const url = inputUrl.value.trim()
  if (url) {
    previewUrl.value = url
    updateImageDimensions(url)
    selectedFile.value = null
  } else {
    previewUrl.value = defaultBg
    updateImageDimensions(defaultBg)
  }
}

function setPositionPreset(pct) {
  verticalPercent.value = pct
}

function setOverlayPreset(pct) {
  overlayPercent.value = pct
}

async function handleResetDefault() {
  if (!canModifyWallpaper.value) {
    uiStore.showToast('Chỉ tài khoản Quản trị viên (Admin) mới có quyền đổi hình nền.', 'error')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Khôi phục hình nền mặc định',
    message: 'Bạn có chắc chắn muốn xóa hình nền tùy chỉnh và trở về hình nền mặc định?',
    confirmText: 'Khôi phục',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  isSubmitting.value = true
  try {
    const res = await http.delete('/portal-wallpaper')
    if (res.data?.success) {
      uiStore.showToast('Đã khôi phục hình nền mặc định!', 'success')
      emit('saved', {
        wallpaper_url: null,
        wallpaper_position: 'center center',
        wallpaper_fit: 'cover',
        wallpaper_overlay: 15,
      })
      close()
    }
  } catch (err) {
    console.error('Lỗi khi khôi phục hình nền:', err)
    const msg = err.response?.status === 403
      ? (err.response?.data?.message || 'Chỉ tài khoản Quản trị viên (Admin) mới có quyền đổi hình nền.')
      : (err.response?.data?.message || 'Không thể khôi phục hình nền mặc định.')
    uiStore.showToast(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

async function handleSave() {
  if (!canModifyWallpaper.value) {
    uiStore.showToast('Chỉ tài khoản Quản trị viên (Admin) mới có quyền đổi hình nền.', 'error')
    return
  }

  isSubmitting.value = true
  try {
    let res
    if (selectedFile.value) {
      const formData = new FormData()
      formData.append('wallpaper', selectedFile.value)
      formData.append('wallpaper_position', appliedPosition.value)
      formData.append('wallpaper_fit', wallpaperFit.value)
      formData.append('wallpaper_overlay', overlayPercent.value)
      res = await http.post('/portal-wallpaper', formData)
    } else if (inputUrl.value.trim()) {
      res = await http.post('/portal-wallpaper', {
        wallpaper_url: inputUrl.value.trim(),
        wallpaper_position: appliedPosition.value,
        wallpaper_fit: wallpaperFit.value,
        wallpaper_overlay: overlayPercent.value,
      })
    } else if (props.currentWallpaper) {
      res = await http.post('/portal-wallpaper', {
        wallpaper_position: appliedPosition.value,
        wallpaper_fit: wallpaperFit.value,
        wallpaper_overlay: overlayPercent.value,
      })
    } else {
      uiStore.showToast('Vui lòng chọn ảnh từ máy hoặc nhập đường dẫn URL!', 'error')
      isSubmitting.value = false
      return
    }

    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Cập nhật hình nền thành công!', 'success')
      emit('saved', {
        wallpaper_url: res.data.wallpaper_url,
        wallpaper_position: res.data.wallpaper_position,
        wallpaper_fit: res.data.wallpaper_fit,
        wallpaper_overlay: res.data.wallpaper_overlay,
      })
      close()
    }
  } catch (err) {
    console.error('Lỗi khi lưu hình nền:', err)
    const msg = err.response?.status === 403
      ? (err.response?.data?.message || 'Chỉ tài khoản Quản trị viên (Admin) mới có quyền đổi hình nền.')
      : (err.response?.data?.message || 'Không thể cập nhật hình nền.')
    uiStore.showToast(msg, 'error')
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  if (isSubmitting.value) return
  emit('update:show', false)
}

function handleKeyDown(e) {
  if (!props.show) return
  if (e.key === 'Escape') {
    close()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[999999] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 select-none font-sans"
      @click="close"
    >
      <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 flex flex-col animate-[zoom_0.2s_ease-out] max-h-[95vh]"
        @click.stop
      >
        <!-- Modal Topbar / Header -->
        <div
          class="h-10 px-4 flex items-center justify-between text-white shrink-0"
          style="background-color: var(--pms-custom-theme, #006bdb);"
        >
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-image text-xs"></i>
            <span class="text-xs font-semibold uppercase tracking-wider">Cài đặt hình nền Portal</span>
          </div>
          <button
            type="button"
            @click="close"
            :disabled="isSubmitting"
            class="text-white/80 hover:text-white p-1 rounded transition-colors cursor-pointer border-none bg-transparent"
            title="Đóng (Esc)"
          >
            <i class="fa-solid fa-xmark text-sm"></i>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-4 space-y-3 text-xs text-[#000000D9] overflow-y-auto">
          <!-- Khung xem trước hình nền -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="font-semibold text-[#000000D9]">Xem trước hiển thị (Live Preview)</label>
              
              <!-- Badge độ phân giải ảnh -->
              <div v-if="imageDimensions" class="flex items-center gap-1.5 text-[11px]">
                <span
                  class="px-2 py-0.5 rounded font-medium flex items-center gap-1"
                  :class="(imageDimensions.width < 1280 || imageDimensions.height < 720) 
                    ? 'bg-amber-100 text-amber-800 border border-amber-300' 
                    : 'bg-emerald-100 text-emerald-800 border border-emerald-300'"
                >
                  <i :class="(imageDimensions.width < 1280 || imageDimensions.height < 720) ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check'"></i>
                  <span>{{ imageDimensions.width }} × {{ imageDimensions.height }} px</span>
                  <span v-if="imageDimensions.width < 1280 || imageDimensions.height < 720" class="hidden sm:inline font-normal">(Ảnh nhỏ, có thể mờ)</span>
                  <span v-else class="hidden sm:inline font-normal">(Đạt chuẩn nét)</span>
                </span>
              </div>
            </div>

            <div class="relative w-full h-44 rounded-lg overflow-hidden border border-slate-300 bg-slate-900 shadow-inner flex items-center justify-center">
              <img
                :src="previewUrl"
                alt="Preview Wallpaper"
                class="w-full h-full transition-all duration-150"
                :style="{
                  objectPosition: appliedPosition,
                  objectFit: wallpaperFit
                }"
              />
              <!-- Dynamic dark overlay preview -->
              <div
                class="absolute inset-0 bg-slate-950 pointer-events-none transition-opacity duration-150"
                :style="{ opacity: overlayPercent / 100 }"
              ></div>
              <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between text-[11px] text-white/90 drop-shadow-md">
                <span class="truncate max-w-[260px]">
                  {{ selectedFile ? selectedFile.name : (inputUrl || 'Hình nền hiện tại') }}
                </span>
                <span class="text-white/80 font-medium">Vị trí: {{ verticalPercent }}% | Phủ tối: {{ overlayPercent }}%</span>
              </div>
            </div>
          </div>

          <!-- Bảng công cụ căn chỉnh vị trí & Lớp phủ tối -->
          <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-2.5">
            <!-- 1. Vị trí ảnh -->
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <label class="font-semibold text-[#000000D9] flex items-center gap-1.5">
                  <i class="fa-solid fa-arrows-up-down text-sky-600"></i>
                  <span>Vị trí ảnh (Dịch chuyển lên/xuống)</span>
                </label>
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    @click="setPositionPreset(15)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="verticalPercent <= 25 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Hiển thị phần trên - Phù hợp nhất cho khuôn mặt / chân dung"
                  >
                    <i class="fa-solid fa-arrow-up mr-0.5"></i> Đỉnh (Mặt)
                  </button>
                  <button
                    type="button"
                    @click="setPositionPreset(50)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="verticalPercent > 25 && verticalPercent < 75 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Hiển thị chính giữa - Mặc định phong cảnh"
                  >
                    <i class="fa-solid fa-arrows-to-dot mr-0.5"></i> Giữa
                  </button>
                  <button
                    type="button"
                    @click="setPositionPreset(85)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="verticalPercent >= 75 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Hiển thị phần đáy"
                  >
                    <i class="fa-solid fa-arrow-down mr-0.5"></i> Đáy
                  </button>
                </div>
              </div>
              <div class="flex justify-between text-[11px] text-slate-500">
                <span>0% (Đỉnh/Mặt)</span>
                <span class="font-bold text-sky-600">{{ verticalPercent }}%</span>
                <span>100% (Đáy)</span>
              </div>
              <input
                type="range"
                min="0"
                max="100"
                step="1"
                v-model.number="verticalPercent"
                class="w-full accent-sky-600 cursor-pointer h-1.5 bg-slate-200 rounded-lg appearance-none"
              />
            </div>

            <!-- 2. Độ tối lớp phủ (Overlay) -->
            <div class="space-y-1 pt-2 border-t border-slate-200">
              <div class="flex items-center justify-between">
                <label class="font-semibold text-[#000000D9] flex items-center gap-1.5">
                  <i class="fa-solid fa-circle-half-stroke text-sky-600"></i>
                  <span>Độ tối lớp phủ (Overlay)</span>
                </label>
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    @click="setOverlayPreset(0)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="overlayPercent === 0 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Không phủ tối - Màu ảnh gốc trong trẻo và nét 100%"
                  >
                    Sáng nét (0%)
                  </button>
                  <button
                    type="button"
                    @click="setOverlayPreset(15)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="overlayPercent === 15 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Phủ nhẹ 15% - Vừa sáng vừa dễ nhìn icon"
                  >
                    Nhẹ (15%)
                  </button>
                  <button
                    type="button"
                    @click="setOverlayPreset(40)"
                    class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                    :class="overlayPercent === 40 ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                    title="Phủ tối 40% - Làm dịu mắt"
                  >
                    Tối (40%)
                  </button>
                </div>
              </div>
              <div class="flex justify-between text-[11px] text-slate-500">
                <span>0% (Sáng trong nguyên bản)</span>
                <span class="font-bold text-sky-600">{{ overlayPercent }}%</span>
                <span>80% (Tối hẳn)</span>
              </div>
              <input
                type="range"
                min="0"
                max="80"
                step="5"
                v-model.number="overlayPercent"
                class="w-full accent-sky-600 cursor-pointer h-1.5 bg-slate-200 rounded-lg appearance-none"
              />
            </div>

            <!-- 3. Kiểu hiển thị (Fit Mode) -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-200">
              <label class="font-semibold text-[#000000D9] flex items-center gap-1.5">
                <i class="fa-solid fa-expand text-sky-600"></i>
                <span>Kiểu hiển thị (Fit)</span>
              </label>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  @click="wallpaperFit = 'cover'"
                  class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                  :class="wallpaperFit === 'cover' ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                >
                  Phủ kín (Cover)
                </button>
                <button
                  type="button"
                  @click="wallpaperFit = 'contain'"
                  class="h-6 px-2 text-[11px] font-medium rounded border transition-colors cursor-pointer"
                  :class="wallpaperFit === 'contain' ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                >
                  Toàn vẹn (Contain)
                </button>
              </div>
            </div>
          </div>

          <!-- Nguồn hình nền: Chọn tab -->
          <div class="flex border-b border-slate-200">
            <button
              type="button"
              @click="activeTab = 'upload'"
              class="pb-2 px-3 font-semibold transition-colors border-b-2 cursor-pointer"
              :class="activeTab === 'upload' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
              <i class="fa-solid fa-arrow-up-from-bracket mr-1.5"></i>
              Tải ảnh từ máy
            </button>
            <button
              type="button"
              @click="activeTab = 'url'"
              class="pb-2 px-3 font-semibold transition-colors border-b-2 cursor-pointer"
              :class="activeTab === 'url' ? 'border-sky-600 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
              <i class="fa-solid fa-link mr-1.5"></i>
              Nhập link ảnh (URL)
            </button>
          </div>

          <!-- Tab Tải ảnh từ máy -->
          <div v-if="activeTab === 'upload'" class="space-y-2">
            <input
              ref="fileInputRef"
              type="file"
              accept="image/png, image/jpeg, image/jpg, image/webp"
              class="hidden"
              @change="handleFileSelect"
            />
            <div
              @click="fileInputRef?.click()"
              class="border-2 border-dashed border-slate-300 hover:border-sky-500 rounded-lg p-3.5 flex flex-col items-center justify-center gap-1 cursor-pointer bg-slate-50 hover:bg-sky-50/40 transition-colors"
            >
              <div class="w-7 h-7 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center">
                <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
              </div>
              <div class="text-center">
                <span class="font-semibold text-sky-600 hover:underline">Bấm để chọn ảnh từ máy</span>
                <p class="text-[11px] text-[#A8B0BF]">Khuyến nghị ảnh Full HD (1920×1080) trở lên để đạt độ nét cao nhất</p>
              </div>
            </div>
          </div>

          <!-- Tab Nhập link URL -->
          <div v-else class="space-y-2">
            <label class="block font-semibold text-[#000000D9]">Đường dẫn hình ảnh</label>
            <div class="flex gap-2">
              <input
                type="text"
                v-model="inputUrl"
                @input="handleUrlInput"
                placeholder="https://example.com/wallpaper.jpg"
                class="flex-1 h-8 px-2.5 rounded-lg border border-slate-300 text-xs text-[#000000D9] placeholder-[#A8B0BF] focus:outline-none focus:border-sky-500"
              />
              <button
                type="button"
                @click="handleUrlInput"
                class="btn-pms-secondary h-8 px-3 text-xs"
              >
                Xem trước
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="h-12 px-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
          <button
            type="button"
            @click="handleResetDefault"
            :disabled="isSubmitting"
            class="btn-pms-secondary h-8 px-3 text-xs flex items-center gap-1.5"
            title="Khôi phục hình nền khách sạn mặc định"
          >
            <i class="fa-solid fa-rotate-left"></i>
            <span>Mặc định</span>
          </button>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="close"
              :disabled="isSubmitting"
              class="btn-pms-close h-8 px-3 text-xs flex items-center gap-1.5"
            >
              <i class="fa-solid fa-xmark"></i>
              <span>Đóng</span>
            </button>
            <button
              type="button"
              @click="handleSave"
              :disabled="isSubmitting"
              class="btn-pms-primary h-8 px-4 text-xs flex items-center gap-1.5"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-floppy-disk"></i>
              <span>{{ isSubmitting ? 'Đang lưu...' : 'Lưu' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
