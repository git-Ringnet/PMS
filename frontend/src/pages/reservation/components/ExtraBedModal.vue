<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
    @click.self="close"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[640px] overflow-hidden border border-slate-300 flex flex-col max-h-[90vh]"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- MODAL HEADER -->
      <div 
        class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0 select-none cursor-move"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
          <i class="fa-solid fa-bed text-sky-400"></i>
          <span>Chi tiết thêm giường - PHÒNG {{ room?.roomNumber || room?.type || room?.shape || 'CHƯA GÁN' }}</span>
        </div>
        <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 space-y-4 flex-1 overflow-y-auto text-xs font-semibold text-slate-700">
        <!-- TABLE GIÁ THÊM GIƯỜNG -->
        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-extrabold h-10 text-center">
                <th class="p-2.5 text-left pl-4 w-28">Ngày</th>
                <th class="p-2.5 text-center w-28">Số Lượng</th>
                <th class="p-2.5 text-center w-36">Thành Tiền</th>
                <th class="p-2.5 text-right pr-4 w-32">Tổng Tiền</th>
                <th class="p-2.5 text-center w-28">FIT/GIT</th>
              </tr>
            </thead>
            <tbody>
              <!-- HÀNG TOTAL (TỔNG CHUNG) -->
              <tr class="bg-white font-bold border-b border-slate-200 h-12 text-slate-800">
                <td class="p-2.5 text-left pl-4 font-bold text-sky-600">Total</td>
                
                <!-- SỐ LƯỢNG (VỚI MŨI TÊN TĂNG GIẢM LÊN XUỐNG) -->
                <td class="p-2.5 text-center">
                  <div class="relative inline-flex items-center justify-center">
                    <div 
                      v-if="isAllPastOrLocked" 
                      class="absolute inset-0 z-10 cursor-not-allowed" 
                      @click.stop="triggerConstraintAlert()"
                      title="Không thể chỉnh sửa đêm quá khứ"
                    ></div>
                    <input
                      type="number"
                      v-model.number="totalQuantity"
                      @input="handleTotalQuantityChange"
                      :disabled="isAllPastOrLocked"
                      min="0"
                      max="10"
                      class="w-20 h-8 text-center font-bold border rounded-md focus:outline-none pr-5 text-xs shadow-2xs"
                      :class="isAllPastOrLocked ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800 focus:ring-1 focus:ring-sky-500'"
                    />
                    <div class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none" :class="{ 'opacity-30 pointer-events-none': isAllPastOrLocked }">
                      <button type="button" @click="totalQuantity = Math.min(10, (totalQuantity || 0) + 1); handleTotalQuantityChange()" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        <i class="fa-solid fa-chevron-up"></i>
                      </button>
                      <button type="button" @click="totalQuantity = Math.max(0, (totalQuantity || 0) - 1); handleTotalQuantityChange()" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        <i class="fa-solid fa-chevron-down"></i>
                      </button>
                    </div>
                  </div>
                </td>

                <!-- THÀNH TIỀN (VỚI MŨI TÊN TĂNG GIẢM LÊN XUỐNG) -->
                <td class="p-2.5 text-center">
                  <div class="relative inline-flex items-center justify-center w-full">
                    <div 
                      v-if="isAllPastOrLocked" 
                      class="absolute inset-0 z-10 cursor-not-allowed" 
                      @click.stop="triggerConstraintAlert()"
                      title="Không thể chỉnh sửa đêm quá khứ"
                    ></div>
                    <input
                      type="text"
                      :value="formatCurrencyInput(totalRate)"
                      @input="e => { totalRate = cleanCurrencyValue(e.target.value); handleTotalRateChange(); }"
                      :disabled="isAllPastOrLocked"
                      class="w-full h-8 text-right font-bold border rounded-md px-2 pr-5 focus:outline-none text-xs shadow-2xs"
                      :class="isAllPastOrLocked ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800 focus:ring-1 focus:ring-sky-500'"
                    />
                    <div class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none" :class="{ 'opacity-30 pointer-events-none': isAllPastOrLocked }">
                      <button type="button" @click="totalRate = (totalRate || 0) + 50000; handleTotalRateChange()" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        <i class="fa-solid fa-chevron-up"></i>
                      </button>
                      <button type="button" @click="totalRate = Math.max(0, (totalRate || 0) - 50000); handleTotalRateChange()" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        <i class="fa-solid fa-chevron-down"></i>
                      </button>
                    </div>
                  </div>
                </td>

                <!-- TỔNG TIỀN -->
                <td class="p-2.5 text-right font-extrabold text-slate-800 pr-4">
                  {{ formatCurrencyInput(computedTotalSum) }}
                </td>

                <!-- FIT / GIT TOGGLE SWITCH -->
                <td class="p-2.5 text-center">
                  <div class="relative inline-flex items-center justify-center">
                    <div 
                      v-if="isAllPastOrLocked" 
                      class="absolute inset-0 z-10 cursor-not-allowed" 
                      @click.stop="triggerConstraintAlert()"
                    ></div>
                    <label class="relative inline-flex items-center cursor-pointer select-none gap-1.5 justify-center" :class="{ 'opacity-40 pointer-events-none': isAllPastOrLocked }" title="Bật/Tắt Gửi HĐ về Master hoặc Phòng">
                      <input
                        type="checkbox"
                        v-model="totalIsRoom"
                        @change="handleTotalIsRoomChange"
                        :disabled="isAllPastOrLocked"
                        class="sr-only peer"
                      />
                      <div class="w-8 h-4 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4 shadow-2xs"></div>
                      <span class="text-[10px] font-extrabold uppercase min-w-[42px] text-left" :class="totalIsRoom ? 'text-sky-600' : 'text-slate-500'">
                        {{ totalIsRoom ? 'Phòng' : 'Master' }}
                      </span>
                    </label>
                  </div>
                </td>
              </tr>

              <!-- CHI TIẾT GIÁ TỪNG ĐÊM -->
              <template v-if="dailyRates.length > 0">
                <tr
                  v-for="(night, idx) in dailyRates"
                  :key="idx"
                  class="border-b border-slate-100 hover:bg-slate-50/50 h-12 transition-colors"
                  :class="(night.isLocked || night.isPast) ? 'bg-slate-50/80 opacity-60' : 'bg-white'"
                >
                  <!-- NGÀY -->
                  <td class="p-2.5 text-left pl-4 font-semibold text-slate-700">
                    <span>{{ night.displayDate }}</span>
                    <span v-if="night.isLocked && !night.isPast" class="ml-1.5 text-[9px] text-slate-600 bg-slate-100 border border-slate-300 px-1 py-0.2 rounded font-bold">
                      Đã chốt
                    </span>
                    <span v-else-if="night.isPast" class="ml-1.5 text-[9px] text-amber-600 bg-amber-50 border border-amber-200 px-1 py-0.2 rounded font-bold">
                      Quá khứ
                    </span>
                  </td>

                  <!-- SỐ LƯỢNG NGHỈ TỪNG ĐÊM -->
                  <td class="p-2.5 text-center">
                    <div class="relative inline-flex items-center justify-center">
                      <div 
                        v-if="night.isLocked || night.isPast" 
                        class="absolute inset-0 z-10 cursor-not-allowed" 
                        @click.stop="triggerConstraintAlert(night)"
                        title="Đêm quá khứ không được phép chỉnh sửa"
                      ></div>
                      <input
                        type="number"
                        v-model.number="night.quantity"
                        @input="updateNightTotal(night)"
                        :disabled="night.isLocked || night.isPast"
                        min="0"
                        max="10"
                        class="w-20 h-8 text-center font-bold border rounded-md focus:outline-none pr-5 text-xs shadow-2xs"
                        :class="(night.isLocked || night.isPast) ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800 focus:ring-1 focus:ring-sky-500'"
                      />
                      <div v-if="!night.isLocked && !night.isPast" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
                        <button type="button" @click="night.quantity = Math.min(10, (night.quantity || 0) + 1); updateNightTotal(night)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                          <i class="fa-solid fa-chevron-up"></i>
                        </button>
                        <button type="button" @click="night.quantity = Math.max(0, (night.quantity || 0) - 1); updateNightTotal(night)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                          <i class="fa-solid fa-chevron-down"></i>
                        </button>
                      </div>
                    </div>
                  </td>

                  <!-- THÀNH TIỀN TỪNG ĐÊM -->
                  <td class="p-2.5 text-center">
                    <div class="relative inline-flex items-center justify-center w-full">
                      <div 
                        v-if="night.isLocked || night.isPast" 
                        class="absolute inset-0 z-10 cursor-not-allowed" 
                        @click.stop="triggerConstraintAlert(night)"
                        title="Đêm quá khứ không được phép chỉnh sửa"
                      ></div>
                      <input
                        type="text"
                        :value="formatCurrencyInput(night.rate)"
                        @input="e => { night.rate = cleanCurrencyValue(e.target.value); updateNightTotal(night); }"
                        :disabled="night.isLocked || night.isPast"
                        class="w-full h-8 text-right font-bold border rounded-md px-2 pr-5 focus:outline-none text-xs shadow-2xs"
                        :class="(night.isLocked || night.isPast) ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800 focus:ring-1 focus:ring-sky-500'"
                      />
                      <div v-if="!night.isLocked && !night.isPast" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
                        <button type="button" @click="night.rate = (night.rate || 0) + 50000; updateNightTotal(night)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                          <i class="fa-solid fa-chevron-up"></i>
                        </button>
                        <button type="button" @click="night.rate = Math.max(0, (night.rate || 0) - 50000); updateNightTotal(night)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                          <i class="fa-solid fa-chevron-down"></i>
                        </button>
                      </div>
                    </div>
                  </td>

                  <!-- TỔNG TIỀN TỪNG ĐÊM -->
                  <td class="p-2.5 text-right font-bold pr-4" :class="(night.isLocked || night.isPast) ? 'text-slate-400' : 'text-slate-800'">
                    {{ formatCurrencyInput(night.total) }}
                  </td>

                  <!-- FIT/GIT TOGGLE SWITCH TỪNG ĐÊM -->
                  <td class="p-2.5 text-center">
                    <div class="relative inline-flex items-center justify-center">
                      <div 
                        v-if="night.isLocked || night.isPast" 
                        class="absolute inset-0 z-10 cursor-not-allowed" 
                        @click.stop="triggerConstraintAlert(night)"
                      ></div>
                      <label class="relative inline-flex items-center cursor-pointer select-none gap-1.5 justify-center" :class="(night.isLocked || night.isPast) ? 'opacity-40 pointer-events-none' : ''">
                        <input
                          type="checkbox"
                          v-model="night.isRoom"
                          :disabled="night.isLocked || night.isPast"
                          class="sr-only peer"
                        />
                        <div class="w-8 h-4 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4 shadow-2xs"></div>
                        <span class="text-[10px] font-extrabold uppercase min-w-[42px] text-left" :class="night.isRoom ? 'text-sky-600' : 'text-slate-500'">
                          {{ night.isRoom ? 'Phòng' : 'Master' }}
                        </span>
                      </label>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-else>
                <td colspan="5" class="p-6 text-center text-slate-400 font-medium italic">
                  Không tìm thấy danh sách đêm lưu trú.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-5 py-3 flex items-center justify-end space-x-2 shrink-0">
        <button 
          @click="close" 
          class="bg-[#72c0e5] hover:bg-[#5bb2dc] text-white border-none rounded-lg font-bold text-xs px-4 py-2 cursor-pointer shadow-sm flex items-center space-x-1.5 transition"
        >
          <i class="fa-solid fa-rotate-left"></i>
          <span>Quay lại</span>
        </button>
        <button 
          @click="save" 
          class="bg-[#72c0e5] hover:bg-[#5bb2dc] text-white border-none rounded-lg font-bold text-xs px-4 py-2 cursor-pointer shadow-sm flex items-center space-x-1.5 transition"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
      </div>
    </div>

    <!-- TELEPORT POPUP RÀNG BUỘC NGHIỆP VỤ -->
    <Teleport to="body">
      <div
        v-if="constraintModal.show"
        class="fixed inset-0 z-[9999999] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 animate-[fade_0.2s_ease-out]"
        @click="constraintModal.show = false"
      >
        <div
          class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-[420px] overflow-hidden border border-slate-200/90 dark:border-slate-800 animate-[zoom_0.25s_cubic-bezier(0.34,1.56,0.64,1)]"
          @click.stop
        >
          <div class="p-6 text-center">
            <!-- Icon cảnh báo -->
            <div class="relative mx-auto w-14 h-14 flex items-center justify-center mb-3">
              <div class="absolute inset-0 rounded-full bg-amber-400/20 animate-ping opacity-25"></div>
              <div class="relative w-12 h-12 rounded-full bg-amber-50 border-2 border-amber-200/80 flex items-center justify-center shadow-xs">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 text-xl"></i>
              </div>
            </div>

            <!-- Title -->
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 tracking-tight">
              {{ constraintModal.title }}
            </h3>

            <!-- Message -->
            <p class="text-xs text-slate-600 dark:text-slate-300 font-medium leading-relaxed mt-2.5 px-2 whitespace-pre-line text-center">
              {{ constraintModal.message }}
            </p>
          </div>

          <!-- Action button -->
          <div class="px-6 pb-6 pt-1 flex items-center justify-center">
            <button
              type="button"
              @click="constraintModal.show = false"
              class="w-full py-2.5 px-5 bg-sky-500 hover:bg-sky-600 active:bg-sky-700 text-white font-bold rounded-xl transition text-xs cursor-pointer border-none shadow-md shadow-sky-500/25 active:scale-98 flex items-center justify-center gap-1.5"
            >
              <span>Đã hiểu</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: Boolean,
  room: Object,
  systemDate: String
})

const emit = defineEmits(['update:show', 'saved'])

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

// ==================== POPUP RÀNG BUỘC NGHIỆP VỤ ====================
const constraintModal = ref({
  show: false,
  title: 'RÀNG BUỘC THÊM GIƯỜNG',
  message: ''
})

function triggerConstraintAlert(night = null) {
  const sysDateFormatted = formatDisplayDate(props.systemDate) || 'hôm nay'
  if (night && night.displayDate) {
    constraintModal.value = {
      show: true,
      title: 'RÀNG BUỘC THÊM GIƯỜNG',
      message: `Đêm ${night.displayDate} thuộc quá khứ (nhỏ hơn Ngày hệ thống ${sysDateFormatted}) không được phép thêm mới hoặc chỉnh sửa Extra Bed.\n\nTrường hợp cần phát sinh chi phí quá khứ, vui lòng tạo hóa đơn tại Modun Lễ tân.`
    }
  } else {
    constraintModal.value = {
      show: true,
      title: 'RÀNG BUỘC THÊM GIƯỜNG',
      message: `Đêm lưu trú thuộc quá khứ (nhỏ hơn Ngày hệ thống ${sysDateFormatted}) không được phép thêm mới hoặc chỉnh sửa Extra Bed.\n\nTrường hợp cần phát sinh chi phí quá khứ, vui lòng tạo hóa đơn tại Modun Lễ tân.`
    }
  }
}

const totalQuantity = ref(0)
const totalRate = ref(0)
const totalIsRoom = ref(false) // false = MASTER (FIT), true = PHÒNG (GIT)
const dailyRates = ref([])

const isAllPastOrLocked = computed(() => {
  return dailyRates.value.length > 0 && dailyRates.value.every(d => d.isPast || d.isLocked)
})

const computedTotalSum = computed(() => {
  if (!dailyRates.value || dailyRates.value.length === 0) {
    return (Number(totalQuantity.value) || 0) * (Number(totalRate.value) || 0)
  }
  return dailyRates.value.reduce((sum, d) => sum + (Number(d.total) || 0), 0)
})

function formatDisplayDate(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return ''
  let str = String(val).replace(/[^\d.-]/g, '')
  if (!str) return ''
  let parts = str.split('.')
  parts[0] = Number(parts[0]).toLocaleString('en-US')
  return parts.join('.')
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0
  const cleanStr = String(val).replace(/,/g, '')
  return Number(cleanStr) || 0
}

function buildStayDates(checkInStr, checkOutStr) {
  const dates = []
  if (!checkInStr) return [new Date().toISOString().split('T')[0]]

  let curr = new Date(checkInStr)
  let end = checkOutStr ? new Date(checkOutStr) : new Date(curr.getTime() + 86400000)

  if (isNaN(curr.getTime())) curr = new Date()
  if (isNaN(end.getTime()) || curr >= end) {
    const dStr = checkInStr || new Date().toISOString().split('T')[0]
    return [dStr]
  }

  while (curr < end) {
    const yyyy = curr.getFullYear()
    const mm = String(curr.getMonth() + 1).padStart(2, '0')
    const dd = String(curr.getDate()).padStart(2, '0')
    dates.push(`${yyyy}-${mm}-${dd}`)
    curr.setDate(curr.getDate() + 1)
  }
  return dates.length ? dates : [checkInStr || new Date().toISOString().split('T')[0]]
}

watch(() => props.show, (newVal) => {
  if (newVal && props.room) {
    modalPos.value = { x: 0, y: 0 }
    const checkIn = props.room.checkIn || props.room.arrivalDate || props.room.arrival_date
    const checkOut = props.room.checkOut || props.room.departureDate || props.room.departure_date
    const stayDates = buildStayDates(checkIn, checkOut)
    let sysDateStr = ''
    if (props.systemDate) {
      if (/^\d{4}-\d{2}-\d{2}$/.test(props.systemDate)) {
        sysDateStr = props.systemDate
      } else if (props.systemDate.includes('T')) {
        const d = new Date(props.systemDate)
        if (!isNaN(d)) {
          sysDateStr = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
        }
      } else {
        sysDateStr = props.systemDate.substring(0, 10)
      }
    }
    const defaultRate = Number(props.room.extraBedPrice) || 300000
    const defaultQty = Number(props.room.extraBedQty) || 0

    const existingDaily = props.room.dailyExtraBeds || []
    const ebServices = props.room.services ? props.room.services.filter(s => s.service_code === 'EB') : []
    const hasExplicitDaily = existingDaily.length > 0 || ebServices.length > 0

    dailyRates.value = stayDates.map(dStr => {
      const isPastDate = sysDateStr ? dStr < sysDateStr : false
      const found = existingDaily.find(ed => ed.dateStr === dStr || ed.date === dStr)
      const foundSvc = ebServices.find(s => s.service_date === dStr || s.dateStr === dStr)
      const isPosted = Boolean(foundSvc && Number(foundSvc.is_posted) === 1)
      const isLocked = isPastDate || isPosted

      let q = 0
      let r = defaultRate
      let isRoom = false

      if (found) {
        q = Number(found.quantity) || 0
        r = Number(found.rate) || 0
        isRoom = !!found.isRoom
      } else if (foundSvc) {
        q = Number(foundSvc.quantity) || 0
        r = Number(foundSvc.rate) || 0
        isRoom = foundSvc.is_room !== 0
      } else if (!isPastDate && !hasExplicitDaily) {
        q = defaultQty
        r = defaultRate
      }

      return {
        dateStr: dStr,
        displayDate: formatDisplayDate(dStr),
        quantity: q,
        rate: r,
        total: q * r,
        isRoom: isRoom,
        isPast: isPastDate,
        isLocked: isLocked
      }
    })

    const hasActiveEB = dailyRates.value.some(d => Number(d.quantity) > 0)
    if (hasActiveEB) {
      const activeNight = dailyRates.value.find(d => Number(d.quantity) > 0)
      totalQuantity.value = activeNight ? activeNight.quantity : 0
      totalRate.value = activeNight ? activeNight.rate : defaultRate
      totalIsRoom.value = activeNight ? activeNight.isRoom : false
    } else {
      const validNight = dailyRates.value.find(d => !d.isLocked && !d.isPast)
      if (validNight) {
        totalQuantity.value = validNight.quantity
        totalRate.value = validNight.rate || defaultRate
        totalIsRoom.value = validNight.isRoom
      } else {
        totalQuantity.value = 0
        totalRate.value = defaultRate > 0 ? defaultRate : 300000
        totalIsRoom.value = false
      }
    }
  }
})

function handleTotalQuantityChange() {
  if (isAllPastOrLocked.value) {
    triggerConstraintAlert()
    totalQuantity.value = 0
    return
  }
  const qty = Number(totalQuantity.value) || 0
  if (qty > 0 && (Number(totalRate.value) || 0) === 0) {
    totalRate.value = Number(props.room?.extraBedPrice) || 300000
  }
  dailyRates.value.forEach(d => {
    if (!d.isLocked && !d.isPast) {
      d.quantity = qty
      if (qty > 0 && (Number(d.rate) || 0) === 0) {
        d.rate = totalRate.value
      }
      d.total = d.quantity * d.rate
    }
  })
}

function handleTotalRateChange() {
  if (isAllPastOrLocked.value) {
    triggerConstraintAlert()
    return
  }
  const r = Number(totalRate.value) || 0
  dailyRates.value.forEach(d => {
    if (!d.isLocked && !d.isPast) {
      d.rate = r
      d.total = d.quantity * d.rate
    }
  })
}

function handleTotalIsRoomChange() {
  if (isAllPastOrLocked.value) {
    triggerConstraintAlert()
    return
  }
  dailyRates.value.forEach(d => {
    if (!d.isLocked && !d.isPast) {
      d.isRoom = totalIsRoom.value
    }
  })
}

function updateNightTotal(night) {
  if (night.isLocked || night.isPast) {
    triggerConstraintAlert(night)
    return
  }
  night.total = (Number(night.quantity) || 0) * (Number(night.rate) || 0)
}

function close() {
  emit('update:show', false)
}

function save() {
  const activeNights = dailyRates.value.filter(d => (Number(d.quantity) || 0) > 0)
  const effQty = activeNights.length > 0 
    ? Math.max(...activeNights.map(d => Number(d.quantity) || 0)) 
    : (isAllPastOrLocked.value ? 0 : (Number(totalQuantity.value) || 0))
  const effRate = activeNights.length > 0 
    ? (Number(activeNights[0]?.rate) || Number(totalRate.value) || 0) 
    : (Number(totalRate.value) || Number(props.room?.extraBedPrice) || 300000)

  emit('saved', {
    quantity: effQty,
    rate: effRate,
    totalExtraBedPrice: computedTotalSum.value || (effQty * effRate),
    dailyRates: dailyRates.value.map(d => ({
      dateStr: d.dateStr,
      date: d.dateStr,
      quantity: Number(d.quantity) || 0,
      rate: Number(d.rate) || 0,
      total: Number(d.total) || 0,
      isRoom: d.isRoom,
      isPast: d.isPast,
      isLocked: d.isLocked
    }))
  })
  close()
}
</script>
