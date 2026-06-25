<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { t } from '@/utils/i18n'
import RoomIcon from './RoomIcon.vue'

const showHelpPopover = ref(false)
const containerRef = ref(null)

function toggleHelpPopover(event) {
  event.stopPropagation()
  showHelpPopover.value = !showHelpPopover.value
}

function handleOutsideClick(event) {
  if (showHelpPopover.value && containerRef.value && !containerRef.value.contains(event.target)) {
    showHelpPopover.value = false
  }
}

function handleScroll() {
  if (showHelpPopover.value) {
    showHelpPopover.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', handleOutsideClick)
  window.addEventListener('scroll', handleScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleOutsideClick)
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <div ref="containerRef" class="relative inline-block text-left">
    <!-- Help / Guide Trigger Button -->
    <button 
      @click="toggleHelpPopover"
      class="p-2 border border-slate-200 bg-white hover:bg-slate-50 rounded-lg cursor-pointer transition-colors text-slate-500 flex items-center justify-center shadow-xs"
      :title="t('roomMap.helpTitle')"
    >
      <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
      </svg>
    </button>
    
    <!-- Help Popover Dropdown (Soft Light Gray Premium Styling) -->
    <div 
      v-if="showHelpPopover" 
      class="absolute right-0 mt-3 w-[500px] bg-slate-50 border border-slate-200 rounded-2xl shadow-xl z-[9999] text-slate-800 p-6 font-sans text-left select-text overflow-hidden"
    >
      <!-- Premium glowing accent top border -->
      <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-sky-500 via-indigo-500 to-emerald-500"></div>

      <!-- Title / Header -->
      <div class="flex items-center gap-2 border-b border-slate-200 pb-3 mb-4 text-slate-800 select-text">
        <svg class="w-5 h-5 text-sky-600 drop-shadow-[0_0_8px_rgba(14,165,233,0.2)]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
        </svg>
        <span class="text-[13px] font-black tracking-widest uppercase text-slate-700">
          {{ t('roomMap.helpTitle') }}
        </span>
      </div>

      <!-- Two Columns Grid -->
      <div class="grid grid-cols-2 gap-x-8 gap-y-4 select-text">
        
        <!-- Column 1: Trạng thái phòng -->
        <div class="flex flex-col gap-2 select-text">
          <div class="text-[10px] font-bold text-sky-600 uppercase tracking-widest mb-1.5 select-text">
            {{ t('roomMap.helpRoomStatuses') }}
          </div>

          <!-- 1. Phòng đến -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="w-3.5 h-3.5 rounded-full bg-[#5ac35a] shadow-xs shrink-0"></span>
            <span class="select-text">{{ t('roomMap.helpArrival') }}</span>
          </div>

          <!-- 2. Phòng đi -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="w-3.5 h-3.5 rounded-full bg-[#e05c5c] shadow-xs shrink-0"></span>
            <span class="select-text">{{ t('roomMap.helpDeparture') }}</span>
          </div>

          <!-- 3. Khách lẻ -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0">
              <RoomIcon name="walkin" class="w-4.5 h-4.5 text-slate-500" />
            </div>
            <span class="select-text">{{ t('roomMap.helpWalkin') }}</span>
          </div>

          <!-- 3b. Hơn 2 khách -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0">
              <RoomIcon name="more-than-2-guests" class="w-4.5 h-4.5 text-slate-500" />
            </div>
            <span class="select-text">{{ t('roomMap.helpMoreThan2Guests') }}</span>
          </div>

          <!-- Separator -->
          <div class="border-b border-slate-200 my-1 select-text"></div>

          <!-- 4. Phòng bẩn -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0">
              <RoomIcon name="dirty" class="w-4.5 h-4.5 text-amber-600" />
            </div>
            <span class="select-text">{{ t('roomMap.helpDirty') }}</span>
          </div>

          <!-- 5. Phòng sạch -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="clean" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpClean') }}</span>
          </div>

          <!-- 6. Phòng OOO -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-[#ffc107]">
              <RoomIcon name="ooo" class="w-4 h-4" />
            </div>
            <span class="select-text">{{ t('roomMap.helpOoo') }}</span>
          </div>

          <!-- 7. Phòng OOS -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-[#4caf50]">
              <RoomIcon name="oos" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpOos') }}</span>
          </div>

          <!-- 8. Ưu tiên -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="priority" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpPriority') }}</span>
          </div>

          <!-- 9. Ưu tiên tính phí -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0">
              <RoomIcon name="priority-paid" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpPriorityPaid') }}</span>
          </div>

          <!-- 10. Thêm giường -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="extra-bed" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpExtraBed') }}</span>
          </div>

          <!-- 11. Ngày sinh -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-[#fd0404]">
              <RoomIcon name="birthday" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpBirthday') }}</span>
          </div>

          <!-- 12. Trăng mật -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-[#f06292]">
              <RoomIcon name="honeymoon" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpHoneymoon') }}</span>
          </div>
        </div>

        <!-- Column 2: Các phím tắt -->
        <div class="flex flex-col gap-2 select-text">
          <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mb-1.5 select-text">
            {{ t('roomMap.helpShortcuts') }}
          </div>

          <!-- 1. Hóa đơn -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="flex items-center gap-3 select-text">
              <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
                <RoomIcon name="invoice" class="w-4.5 h-4.5" />
              </div>
              <span class="select-text">{{ t('roomMap.helpShortcutInvoice') }}</span>
            </div>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">B</kbd>
          </div>

          <!-- 2. Nhóm hóa đơn -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="flex items-center gap-3 select-text">
              <div class="w-4 h-4 flex items-center justify-center shrink-0 text-[#fd0404]">
                <RoomIcon name="group-invoice" class="w-4.5 h-4.5" />
              </div>
              <span class="select-text">{{ t('roomMap.helpShortcutGroupInvoice') }}</span>
            </div>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">G</kbd>
          </div>

          <!-- 3. Tạo ĐK -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="flex items-center gap-3 select-text">
              <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
                <RoomIcon name="create-reg" class="w-4.5 h-4.5" />
              </div>
              <span class="select-text">{{ t('roomMap.helpShortcutCreateReg') }}</span>
            </div>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">C</kbd>
          </div>

          <!-- 4. Thông tin -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="flex items-center gap-3 select-text">
              <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
                <RoomIcon name="info" class="w-4.5 h-4.5" />
              </div>
              <span class="select-text">{{ t('roomMap.helpShortcutInfo') }}</span>
            </div>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">I</kbd>
          </div>

          <!-- 5. Sẵn sàng -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="select-text">{{ t('roomMap.helpShortcutAvailable') }}</span>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">1</kbd>
          </div>

          <!-- 6. Phòng sạch -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="select-text">{{ t('roomMap.helpShortcutClean') }}</span>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">2</kbd>
          </div>

          <!-- 7. Phòng bẩn -->
          <div class="flex items-center justify-between text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="select-text">{{ t('roomMap.helpShortcutDirty') }}</span>
            <kbd class="min-w-[20px] h-5 px-1.5 flex items-center justify-center bg-white border border-slate-300 rounded-md font-mono text-[10px] font-black text-sky-600 shadow-xs select-text">3</kbd>
          </div>

          <!-- Separator -->
          <div class="border-b border-slate-200 my-1 select-text"></div>

          <!-- 8. Phòng không làm phiền -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="dnd" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpDnd') }}</span>
          </div>

          <!-- 9. Có người trong phòng -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="in-house" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpInHouse') }}</span>
          </div>

          <!-- 10. Yêu cầu dọn phòng -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="hk-request" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpHkRequest') }}</span>
          </div>

          <!-- 10b. Dịch vụ dọn phòng -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <div class="w-4 h-4 flex items-center justify-center shrink-0 text-slate-600">
              <RoomIcon name="housekeeping-service" class="w-4.5 h-4.5" />
            </div>
            <span class="select-text">{{ t('roomMap.helpHkService') }}</span>
          </div>

          <!-- 11. Phòng khách đã nhận trong ngày -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="text-rose-500 font-extrabold w-[22px] text-center shrink-0 select-text">101</span>
            <span class="select-text">{{ t('roomMap.helpGuestCheckedInToday') }}</span>
          </div>

          <!-- 12. Phòng khách đến vào ngày mai -->
          <div class="flex items-center gap-3 text-[12px] font-semibold text-slate-700 hover:bg-slate-100 px-2 py-1 -mx-2 rounded-lg transition-colors cursor-pointer select-text">
            <span class="text-slate-800 font-extrabold underline w-[22px] text-center shrink-0 select-text">101</span>
            <span class="select-text">{{ t('roomMap.helpGuestArrivingTomorrow') }}</span>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.grid-cols-2 > div > div.flex:hover {
  background-color: #e0f2ff !important;
  color: #005b99 !important;
}
.grid-cols-2 > div > div.flex:hover .text-slate-500,
.grid-cols-2 > div > div.flex:hover .text-slate-600 {
  color: #005b99 !important;
}
</style>

