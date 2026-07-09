<script setup>
import { computed } from 'vue'

const props = defineProps({
  room: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['close'])

const formattedRate = computed(() => {
  return formatVND(props.room.rate)
})

const formattedDeposit = computed(() => {
  return formatVND(props.room.payment_value)
})

function formatVND(value) {
  if (value === undefined || value === null) return '0 đ'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) {
    emit('close')
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-xs transition-opacity duration-200"
      @click="handleOverlayClick"
    >
      <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-[800px] mx-4 overflow-hidden border border-gray-300 flex flex-col max-h-[85vh] animate-[modalIn_0.15s_ease-out]"
      >
        <!-- MODAL HEADER (Matching Registration Modal Style exactly) -->
        <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0 select-none">
          <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
            <i class="fa-solid fa-file-lines text-blue-300"></i>
            <span>Thông Tin Đăng Ký Phòng {{ room.room_number }}</span>
            <span class="text-[9px] bg-white/10 px-1.5 py-0.5 rounded font-medium border border-white/20 uppercase tracking-wide">
              {{ room.room_type || room.room_class?.code || 'SUPD' }}
            </span>
          </div>

          <div class="flex items-center space-x-3">
            <div class="flex items-center space-x-1.5 bg-white/10 border border-white/20 rounded-lg h-[24px] px-2 shadow-inner">
              <span class="text-[10px] font-medium text-gray-300">Màu BK</span>
              <div 
                class="w-3 h-3 rounded-full border border-white/30" 
                :style="{ backgroundColor: room.booking_color || '#eab308' }"
              ></div>
            </div>

            <div class="flex items-center space-x-1.5">
              <span class="text-[10px] font-medium text-gray-300">Đoàn/Lẻ</span>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded" :class="room.is_git ? 'bg-blue-500 text-white' : 'bg-gray-400 text-white'">
                {{ room.is_git ? 'ĐOÀN (GIT)' : 'LẺ (FIT)' }}
              </span>
            </div>

            <button 
              @click="emit('close')" 
              class="hover:text-white ml-2 bg-red-500/20 hover:bg-red-500/30 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent transition-colors"
            >
              <i class="fa-solid fa-xmark text-red-400"></i>
            </button>
          </div>
        </div>

        <!-- Scrollable details grid -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50/30">
          
          <!-- Block 1: Thông tin chung & Khách hàng -->
          <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-xs space-y-3">
            <div class="text-[11px] font-bold text-[#243c5a] pb-1.5 border-b border-gray-200 uppercase tracking-wide flex items-center space-x-1.5">
              <i class="fa-solid fa-id-card text-blue-500"></i>
              <span>Thông tin chung & Khách hàng</span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3">
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã Đăng ký</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1 select-all">{{ room.booking_code || '-' }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã tham chiếu</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1 select-all">{{ room.external_booking_code || '-' }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tình trạng ĐK</div>
                <div class="h-[32px] flex items-center px-1">
                  <span class="text-[10px] font-semibold uppercase text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                    {{ room.registration_status || 'Guaranteed' }}
                  </span>
                </div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tên Đăng ký</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1 uppercase">{{ room.booking_name || room.guest_name || '-' }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Khách hàng chính</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1 uppercase">{{ room.guest_name || '-' }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tên Công ty</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ room.company_name || 'Không có công ty' }}</div>
              </div>
            </div>
          </div>

          <!-- Block 2: Thời gian lưu trú & Thành viên phòng -->
          <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-xs space-y-3">
            <div class="text-[11px] font-bold text-[#243c5a] pb-1.5 border-b border-gray-200 uppercase tracking-wide flex items-center space-x-1.5">
              <i class="fa-solid fa-calendar-days text-indigo-500"></i>
              <span>Thời gian lưu trú & Thành viên phòng</span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3">
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày check-in</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ formatDate(room.arrival_date) }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày check-out</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ formatDate(room.departure_date) }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Số đêm lưu trú</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">
                  <span class="text-blue-600 font-semibold mr-1">{{ room.nights || 1 }}</span> đêm
                </div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày xác nhận</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ formatDate(room.confirm_date) }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Người bán</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ room.sales_person || '-' }}</div>
              </div>
              <div class="flex flex-col col-span-1 md:col-span-3">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Khách ở chung & Số lượng</div>
                <div class="h-[32px] flex items-center gap-1.5 px-1 overflow-x-auto">
                  <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 font-semibold text-xs">🧑 {{ room.adults || 2 }} Lớn</span>
                  <span v-if="room.children > 0" class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 font-semibold text-xs">👶 {{ room.children }} Trẻ em</span>
                  <span v-if="room.babies > 0" class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 font-semibold text-xs">🍼 {{ room.babies }} Em bé</span>
                  
                  <template v-if="room.guest_details && room.guest_details.length > 0">
                    <span 
                      v-for="(gName, idx) in room.guest_details" 
                      :key="idx" 
                      class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 rounded font-medium text-xs uppercase"
                    >
                      {{ gName }}
                    </span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Block 3: Payments, Deposits and Pricing Details -->
          <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-xs space-y-3">
            <div class="text-[11px] font-bold text-[#243c5a] pb-1.5 border-b border-gray-200 uppercase tracking-wide flex items-center space-x-1.5">
              <i class="fa-solid fa-receipt text-amber-500"></i>
              <span>Thông tin thanh toán & Doanh thu</span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-3">
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Giá phòng (1 đêm)</div>
                <div class="text-xs font-semibold text-rose-600 h-[32px] flex items-center px-1">{{ formattedRate }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tiền cọc trước</div>
                <div class="text-xs font-semibold text-emerald-600 h-[32px] flex items-center px-1">{{ formattedDeposit }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Phương thức cọc</div>
                <div class="text-xs font-medium text-gray-900 h-[32px] flex items-center px-1">{{ room.payment_method || 'Chưa chọn' }}</div>
              </div>
              <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Xuất hóa đơn VAT</div>
                <div class="h-[32px] flex items-center px-1">
                  <span 
                    class="font-medium px-2 py-0.5 rounded text-xs border"
                    :class="room.has_vat ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-500 border-slate-200'"
                  >
                    {{ room.has_vat ? 'Có' : 'Không' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Block 4: Notes and special requests -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-xs space-y-2">
              <div class="text-xs font-bold text-gray-500 uppercase tracking-wide border-b border-gray-100 pb-1">
                📌 Ghi chú booking
              </div>
              <div class="text-xs font-medium text-gray-800 whitespace-pre-line leading-relaxed min-h-[40px] px-1">
                {{ room.booking_note || 'Không có ghi chú.' }}
              </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-3 shadow-xs space-y-2">
              <div class="text-xs font-bold text-gray-500 uppercase tracking-wide border-b border-gray-100 pb-1">
                ⭐ Yêu cầu đặc biệt
              </div>
              <div class="text-xs font-medium text-gray-800 whitespace-pre-line leading-relaxed min-h-[40px] px-1">
                {{ room.special_requests || 'Không có yêu cầu đặc biệt.' }}
              </div>
            </div>
          </div>

        </div>

        <!-- Footer actions (Matching registration tab exactly) -->
        <div class="bg-gray-100 px-4 py-2 border-t border-gray-300 flex justify-end shrink-0 rounded-b-xl">
          <button
            @click="emit('close')"
            class="px-4 py-1.5 bg-[#243c5a] hover:bg-[#1a2d44] text-white font-bold rounded-lg text-xs uppercase tracking-wider transition-colors border-none cursor-pointer shadow-sm"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes modalIn {
  0% {
    opacity: 0;
    transform: scale(0.97);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
