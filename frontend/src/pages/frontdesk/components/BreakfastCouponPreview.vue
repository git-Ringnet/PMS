<script setup>
import { ref, computed, onMounted, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  coupons: {
    type: Array,
    default: () => []
  },
  hotelSettings: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close'])

const STORAGE_KEY = 'pms_breakfast_coupon_template_config'

// Default template configuration
const defaultConfig = {
  // Layout & Dimensions
  columns: 3, // 1, 2, 3
  minHeight: 220, // in px
  fontSize: 'normal', // 'small' (10px), 'normal' (12px), 'large' (13.5px), 'xlarge' (15px)
  roomFontSize: 'large', // 'normal' (16px), 'large' (20px), 'xlarge' (24px)
  borderWidth: 2, // 1, 2, 3
  borderStyle: 'solid', // 'solid', 'dashed', 'double'
  couponGap: 8, // in px
  
  // Header Content
  showLogo: true,
  showHotelName: true,
  customHotelName: '',
  titleLine1: 'PHIẾU ĂN SÁNG',
  titleLine2: 'BREAKFAST COUPON',
  
  // Body Fields
  showBookingInfo: true,
  showGuestName: false,
  showRoomNumber: true,
  showDate: true,
  roomLabel: 'Phòng / Room:',
  dateLabel: 'Ngày / Date:',
  
  // Footer
  showFooterNote: true,
  footerNote: '• Áp dụng cho khách lưu trú tại khách sạn / Applied to guest in-house only\n• Mỗi khách hàng sử dụng 01 phiếu trong 01 ngày / One coupon per guest per day\n• Chúc quý khách ngon miệng / Enjoy your meal'
}

const config = ref({ ...defaultConfig })
const isPanelOpen = ref(true)
const activeConfigTab = ref('layout') // 'layout' | 'content' | 'footer'
const isLogoError = ref(false)

// Load saved configuration from localStorage
onMounted(() => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) {
      config.value = { ...defaultConfig, ...JSON.parse(saved) }
    }
  } catch (err) {
    console.warn('Cannot load saved template config:', err)
  }
})

// Auto-save when config changes
watch(config, (newVal) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(newVal))
  } catch (err) {
    console.warn('Cannot save template config:', err)
  }
}, { deep: true })

const hotelName = computed(() => {
  if (config.value.customHotelName?.trim()) {
    return config.value.customHotelName.trim()
  }
  return props.hotelSettings?.hotel_name || ''
})

const hotelLogo = computed(() => {
  return props.hotelSettings?.logo_url || ''
})

const hasValidLogo = computed(() => {
  return config.value.showLogo && !!hotelLogo.value && !isLogoError.value
})

function onLogoError() {
  isLogoError.value = true
}

function resetToDefault() {
  if (confirm('Bạn có chắc chắn muốn khôi phục mẫu in về thiết lập mặc định ban đầu?')) {
    config.value = { ...defaultConfig }
    isLogoError.value = false
    try {
      localStorage.removeItem(STORAGE_KEY)
    } catch (e) {}
  }
}

function handlePrint() {
  window.print()
}

function formatDate(dStr) {
  if (!dStr) return ''
  const clean = String(dStr).split('T')[0].split(' ')[0]
  if (clean.includes('-')) {
    const [y, m, d] = clean.split('-')
    return `${d}/${m}/${y}`
  }
  return clean
}

// Split footer notes by newline for clean display
const footerLines = computed(() => {
  if (!config.value.footerNote) return []
  return config.value.footerNote.split('\n').filter(line => line.trim().length > 0)
})
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex flex-col bg-slate-900/85 backdrop-blur-xs text-slate-800 animate-in fade-in duration-150">
    <!-- Non-printable Top Toolbar -->
    <header class="no-print bg-white border-b border-slate-200 px-5 py-2.5 flex flex-wrap items-center justify-between gap-3 shrink-0 shadow-md">
      <div class="flex items-center gap-3">
        <span class="p-2 bg-sky-50 text-sky-600 rounded-lg shadow-2xs">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
        </span>
        <div>
          <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <span>Tùy Chỉnh Mẫu & In Phiếu Ăn Sáng</span>
            <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[11px] font-extrabold">{{ coupons.length }} phiếu</span>
          </h2>
          <p class="text-[11px] text-slate-500">Mẫu in linh hoạt tự động lưu cấu hình theo từng khách sạn / đơn vị</p>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          @click="isPanelOpen = !isPanelOpen"
          :class="isPanelOpen ? 'bg-sky-50 border-sky-300 text-sky-700 font-bold' : 'bg-slate-50 border-slate-200 text-slate-700'"
          class="h-8 px-3 border rounded-lg text-xs font-semibold hover:bg-sky-100 transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
          title="Bật/tắt thanh công cụ tùy chỉnh mẫu"
        >
          <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
          <span>{{ isPanelOpen ? 'Thu gọn cài đặt' : 'Mở tùy chỉnh mẫu' }}</span>
        </button>

        <button 
          @click="resetToDefault"
          class="h-8 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
          title="Khôi phục mẫu về mặc định"
        >
          Khôi phục gốc
        </button>

        <div class="h-5 w-[1px] bg-slate-200 mx-1"></div>

        <button 
          @click="emit('close')"
          class="h-8 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors cursor-pointer"
        >
          Đóng
        </button>
        <button 
          @click="handlePrint"
          class="h-8 px-5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>In Phiếu (Print)</span>
        </button>
      </div>
    </header>

    <!-- Workspace: Customization Panel + Live Printable Sheet -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left Sidebar: Report Customizer (Collapsible) -->
      <aside 
        v-if="isPanelOpen"
        class="no-print w-84 bg-white border-r border-slate-200 flex flex-col shrink-0 shadow-lg z-10 text-xs overflow-hidden select-none"
      >
        <!-- Panel Tabs -->
        <div class="flex border-b border-slate-200 bg-slate-50 text-[11px] font-bold">
          <button 
            @click="activeConfigTab = 'layout'"
            :class="activeConfigTab === 'layout' ? 'bg-white border-b-2 border-sky-500 text-sky-600' : 'text-slate-600 hover:bg-slate-100'"
            class="flex-1 py-2.5 text-center cursor-pointer transition-colors"
          >
            Bố cục & Khổ
          </button>
          <button 
            @click="activeConfigTab = 'content'"
            :class="activeConfigTab === 'content' ? 'bg-white border-b-2 border-sky-500 text-sky-600' : 'text-slate-600 hover:bg-slate-100'"
            class="flex-1 py-2.5 text-center cursor-pointer transition-colors"
          >
            Tiêu đề & Trường
          </button>
          <button 
            @click="activeConfigTab = 'footer'"
            :class="activeConfigTab === 'footer' ? 'bg-white border-b-2 border-sky-500 text-sky-600' : 'text-slate-600 hover:bg-slate-100'"
            class="flex-1 py-2.5 text-center cursor-pointer transition-colors"
          >
            Ghi chú chân
          </button>
        </div>

        <!-- Panel Form Content -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
          <!-- TAB 1: BỐ CỤC & KHỔ -->
          <div v-show="activeConfigTab === 'layout'" class="space-y-4">
            <!-- Columns Option -->
            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Số phiếu trên 1 hàng ngang:</label>
              <div class="grid grid-cols-3 gap-1.5 bg-slate-100 p-1 rounded-lg">
                <button
                  type="button"
                  @click="config.columns = 1"
                  :class="config.columns === 1 ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  1 Cột
                </button>
                <button
                  type="button"
                  @click="config.columns = 2"
                  :class="config.columns === 2 ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  2 Cột
                </button>
                <button
                  type="button"
                  @click="config.columns = 3"
                  :class="config.columns === 3 ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  3 Cột (Chuẩn)
                </button>
              </div>
            </div>

            <!-- Cỡ chữ tổng thể -->
            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Cỡ chữ nội dung phiếu:</label>
              <div class="grid grid-cols-4 gap-1 bg-slate-100 p-1 rounded-lg">
                <button
                  type="button"
                  @click="config.fontSize = 'small'"
                  :class="config.fontSize === 'small' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-[11px] text-center cursor-pointer transition-all"
                >
                  Nhỏ
                </button>
                <button
                  type="button"
                  @click="config.fontSize = 'normal'"
                  :class="config.fontSize === 'normal' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-[11px] text-center cursor-pointer transition-all"
                >
                  Chuẩn
                </button>
                <button
                  type="button"
                  @click="config.fontSize = 'large'"
                  :class="config.fontSize === 'large' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-[11px] text-center cursor-pointer transition-all"
                >
                  Lớn
                </button>
                <button
                  type="button"
                  @click="config.fontSize = 'xlarge'"
                  :class="config.fontSize === 'xlarge' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-[11px] text-center cursor-pointer transition-all"
                >
                  Rất lớn
                </button>
              </div>
            </div>

            <!-- Cỡ số phòng -->
            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Cỡ hiển thị Số phòng:</label>
              <div class="grid grid-cols-3 gap-1.5 bg-slate-100 p-1 rounded-lg">
                <button
                  type="button"
                  @click="config.roomFontSize = 'normal'"
                  :class="config.roomFontSize === 'normal' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  Vừa
                </button>
                <button
                  type="button"
                  @click="config.roomFontSize = 'large'"
                  :class="config.roomFontSize === 'large' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  To nổi bật
                </button>
                <button
                  type="button"
                  @click="config.roomFontSize = 'xlarge'"
                  :class="config.roomFontSize === 'xlarge' ? 'bg-white font-bold text-sky-600 shadow-xs' : 'text-slate-600'"
                  class="py-1 rounded text-center cursor-pointer transition-all"
                >
                  Siêu to
                </button>
              </div>
            </div>

            <!-- Chiều cao tối thiểu phiếu -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="font-bold text-slate-700">Chiều cao tối thiểu phiếu:</label>
                <span class="font-mono font-bold text-sky-600">{{ config.minHeight }}px</span>
              </div>
              <input 
                type="range" 
                v-model.number="config.minHeight" 
                min="180" 
                max="320" 
                step="5" 
                class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-sky-600"
              />
            </div>

            <!-- Đường viền -->
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Kiểu viền:</label>
                <select v-model="config.borderStyle" class="w-full h-8 px-2 bg-slate-50 border border-slate-300 rounded-md outline-none">
                  <option value="solid">Nét liền (Solid)</option>
                  <option value="dashed">Nét đứt (Dashed)</option>
                  <option value="double">Nét đôi (Double)</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Độ dày viền:</label>
                <select v-model.number="config.borderWidth" class="w-full h-8 px-2 bg-slate-50 border border-slate-300 rounded-md outline-none">
                  <option :value="1">Mỏng (1px)</option>
                  <option :value="2">Chuẩn (2px)</option>
                  <option :value="3">Đậm (3px)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- TAB 2: TIÊU ĐỀ & TRƯỜNG THÔNG TIN -->
          <div v-show="activeConfigTab === 'content'" class="space-y-3.5">
            <!-- Header items -->
            <div class="space-y-2 pb-2 border-b border-slate-200">
              <label class="font-bold text-slate-700 block">Header & Logo:</label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="config.showLogo" class="rounded text-sky-600 focus:ring-sky-500" />
                <span class="font-semibold text-slate-700">Hiển thị Logo công ty / KS</span>
              </label>

              <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tùy chỉnh tên hiển thị (nếu không có logo):</label>
                <input 
                  type="text" 
                  v-model="config.customHotelName" 
                  :placeholder="hotelSettings?.hotel_name || 'GALLIOT HOTEL'"
                  class="w-full h-7 px-2 bg-white border border-slate-300 rounded text-xs outline-none focus:border-sky-500"
                />
              </div>
            </div>

            <!-- Titles -->
            <div class="space-y-2 pb-2 border-b border-slate-200">
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Tiêu đề chính dòng 1:</label>
                <input 
                  type="text" 
                  v-model="config.titleLine1" 
                  class="w-full h-7 px-2 bg-white border border-slate-300 rounded text-xs font-bold outline-none focus:border-sky-500"
                />
              </div>
              <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Tiêu đề phụ dòng 2:</label>
                <input 
                  type="text" 
                  v-model="config.titleLine2" 
                  class="w-full h-7 px-2 bg-white border border-slate-300 rounded text-xs outline-none focus:border-sky-500"
                />
              </div>
            </div>

            <!-- Content fields -->
            <div class="space-y-2">
              <label class="font-bold text-slate-700 block">Các trường thông tin hiển thị:</label>
              
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="config.showBookingInfo" class="rounded text-sky-600 focus:ring-sky-500" />
                <span class="font-semibold text-slate-700">Hiển thị Mã / Tên Đăng Ký (Booking)</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="config.showGuestName" class="rounded text-sky-600 focus:ring-sky-500" />
                <span class="font-semibold text-slate-700">Hiển thị Tên Khách Hàng (Guest Name)</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="config.showRoomNumber" class="rounded text-sky-600 focus:ring-sky-500" />
                <span class="font-semibold text-slate-700">Hiển thị Số Phòng (Room Number)</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="config.showDate" class="rounded text-sky-600 focus:ring-sky-500" />
                <span class="font-semibold text-slate-700">Hiển thị Ngày Ăn Sáng (Date)</span>
              </label>
            </div>
          </div>

          <!-- TAB 3: GHI CHÚ CHÂN TRANG -->
          <div v-show="activeConfigTab === 'footer'" class="space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="config.showFooterNote" class="rounded text-sky-600 focus:ring-sky-500" />
              <span class="font-bold text-slate-800">Hiển thị Ghi chú / Điều khoản chân phiếu</span>
            </label>

            <div v-if="config.showFooterNote" class="space-y-1">
              <label class="block text-[11px] font-semibold text-slate-600">Nội dung ghi chú (mỗi dòng 1 điều khoản):</label>
              <textarea 
                v-model="config.footerNote" 
                rows="6"
                placeholder="Nhập nội dung dặn dò khách..."
                class="w-full p-2 bg-white border border-slate-300 rounded text-xs leading-relaxed outline-none focus:border-sky-500"
              ></textarea>
            </div>
          </div>
        </div>
      </aside>

      <!-- Right Area: Live Printable Sheet Preview -->
      <main class="flex-1 overflow-y-auto p-6 bg-slate-800/60 flex justify-center items-start">
        <div 
          id="breakfast-print-sheet" 
          class="bg-white shadow-2xl p-5 w-full max-w-[1150px] transition-all rounded-sm"
          :class="{
            'text-[10.5px]': config.fontSize === 'small',
            'text-[12px]': config.fontSize === 'normal',
            'text-[13.5px]': config.fontSize === 'large',
            'text-[15px]': config.fontSize === 'xlarge',
          }"
        >
          <!-- Empty state -->
          <div v-if="coupons.length === 0" class="text-center py-20 text-slate-400">
            Không có phiếu nào được chọn để in.
          </div>

          <!-- Customizable Coupons Grid -->
          <div 
            v-else 
            class="coupon-grid grid gap-3"
            :style="{
              gridTemplateColumns: `repeat(${config.columns}, minmax(0, 1fr))`,
              gap: `${config.couponGap}px`
            }"
          >
            <div 
              v-for="(coupon, idx) in coupons" 
              :key="idx"
              class="coupon-card p-2.5 flex flex-col justify-between bg-white relative break-inside-avoid"
              :style="{
                minHeight: `${config.minHeight}px`,
                borderWidth: `${config.borderWidth}px`,
                borderStyle: config.borderStyle,
                borderColor: '#000000',
                pageBreakInside: 'avoid'
              }"
            >
              <!-- Top: Header (Logo + Title) -->
              <div class="flex items-center justify-between border-b-2 border-black pb-1.5 gap-2">
                <div class="flex items-center gap-1.5 min-w-[70px] max-w-[110px]">
                  <img 
                    v-if="hasValidLogo" 
                    :src="hotelLogo" 
                    alt="Logo" 
                    class="h-8 max-w-[100px] object-contain"
                    @error="onLogoError"
                  />
                  <span v-else-if="config.showHotelName" class="font-black text-[11px] tracking-tight uppercase leading-tight text-slate-900">
                    {{ hotelName }}
                  </span>
                </div>
                <div class="text-right flex-1">
                  <div 
                    v-if="config.titleLine1" 
                    class="font-black uppercase tracking-wide leading-tight"
                    :class="config.fontSize === 'large' || config.fontSize === 'xlarge' ? 'text-[14px]' : 'text-[12px]'"
                  >
                    {{ config.titleLine1 }}
                  </div>
                  <div 
                    v-if="config.titleLine2" 
                    class="font-extrabold uppercase text-[10px] tracking-wider text-slate-700 leading-none mt-0.5"
                  >
                    {{ config.titleLine2 }}
                  </div>
                </div>
              </div>

              <!-- Middle: Info Details -->
              <div class="py-2 space-y-1.5 text-center flex-1 flex flex-col justify-center">
                <!-- Booking code / name -->
                <div 
                  v-if="config.showBookingInfo" 
                  class="text-[11px] font-semibold text-slate-800 truncate border-b border-dotted border-slate-400 pb-0.5"
                >
                  {{ coupon.booking_info }}
                </div>

                <!-- Guest name -->
                <div 
                  v-if="config.showGuestName && coupon.guest_name" 
                  class="text-[11px] font-bold text-slate-700 truncate"
                >
                  Khách: {{ coupon.guest_name }}
                </div>

                <!-- Room number -->
                <div v-if="config.showRoomNumber" class="pt-0.5">
                  <span class="text-[10px] text-slate-500 uppercase mr-1">{{ config.roomLabel }}</span>
                  <span 
                    class="font-black text-black tracking-wider leading-none"
                    :class="{
                      'text-[17px]': config.roomFontSize === 'normal',
                      'text-[21px]': config.roomFontSize === 'large',
                      'text-[26px]': config.roomFontSize === 'xlarge',
                    }"
                  >
                    {{ coupon.room_number }}
                  </span>
                </div>

                <!-- Date -->
                <div v-if="config.showDate" class="border-b border-dotted border-slate-400 pb-1">
                  <span class="text-[10px] text-slate-500 uppercase mr-1">{{ config.dateLabel }}</span>
                  <span class="font-black text-[13px] text-black">
                    {{ formatDate(coupon.date) }}
                  </span>
                </div>
              </div>

              <!-- Bottom: Footer Notes -->
              <div 
                v-if="config.showFooterNote && footerLines.length > 0" 
                class="text-[8.5px] leading-tight text-slate-700 italic border-t border-slate-300 pt-1 space-y-0.5"
              >
                <div v-for="(line, lIdx) in footerLines" :key="lIdx">
                  {{ line }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
@media print {
  /* Hide all UI except print sheet */
  body * {
    visibility: hidden;
  }
  .no-print {
    display: none !important;
  }
  #breakfast-print-sheet, #breakfast-print-sheet * {
    visibility: visible;
  }
  #breakfast-print-sheet {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    max-width: none !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    background: white !important;
  }

  .coupon-grid {
    display: grid !important;
    width: 100% !important;
  }

  .coupon-card {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    margin-bottom: 6px !important;
    background: white !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
