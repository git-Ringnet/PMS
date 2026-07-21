<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
    @click.self="close"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-[640px] overflow-hidden border border-slate-300 flex flex-col max-h-[90vh]">
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0 select-none">
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
                    <input
                      type="number"
                      v-model.number="totalQuantity"
                      @input="handleTotalQuantityChange"
                      min="0"
                      max="10"
                      class="w-20 h-8 text-center font-bold text-slate-800 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-sky-500 bg-white pr-5 text-xs shadow-2xs"
                    />
                    <div class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
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
                    <input
                      type="text"
                      :value="formatCurrencyInput(totalRate)"
                      @input="e => { totalRate = cleanCurrencyValue(e.target.value); handleTotalRateChange(); }"
                      class="w-full h-8 text-right font-bold text-slate-800 border border-slate-300 rounded-md px-2 pr-5 focus:outline-none focus:ring-1 focus:ring-sky-500 bg-white text-xs shadow-2xs"
                    />
                    <div class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
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
                  <label class="relative inline-flex items-center cursor-pointer select-none gap-1.5 justify-center" title="Bật/Tắt Gửi HĐ về Master hoặc Phòng">
                    <input
                      type="checkbox"
                      v-model="totalIsRoom"
                      @change="handleTotalIsRoomChange"
                      class="sr-only peer"
                    />
                    <div class="w-8 h-4 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4 shadow-2xs"></div>
                    <span class="text-[10px] font-extrabold uppercase min-w-[42px] text-left" :class="totalIsRoom ? 'text-sky-600' : 'text-slate-500'">
                      {{ totalIsRoom ? 'Phòng' : 'Master' }}
                    </span>
                  </label>
                </td>
              </tr>

              <!-- CHI TIẾT GIÁ TỪNG ĐÊM -->
              <tr
                v-for="(night, idx) in dailyRates"
                :key="idx"
                class="border-b border-slate-100 hover:bg-slate-50/50 h-12 transition-colors"
                :class="night.isPast ? 'bg-slate-50/80 opacity-60' : 'bg-white'"
              >
                <!-- NGÀY -->
                <td class="p-2.5 text-left pl-4 font-semibold text-slate-700">
                  <span>{{ night.displayDate }}</span>
                  <span v-if="night.isPast" class="ml-1.5 text-[9px] text-amber-600 bg-amber-50 border border-amber-200 px-1 py-0.2 rounded font-bold">
                    Quá khứ
                  </span>
                </td>

                <!-- SỐ LƯỢNG NGHỈ TỪNG ĐÊM -->
                <td class="p-2.5 text-center">
                  <div class="relative inline-flex items-center justify-center">
                    <input
                      type="number"
                      v-model.number="night.quantity"
                      @input="updateNightTotal(night)"
                      :disabled="night.isPast"
                      min="0"
                      max="10"
                      class="w-20 h-8 text-center font-bold border rounded-md focus:outline-none focus:ring-1 focus:ring-sky-500 pr-5 text-xs shadow-2xs"
                      :class="night.isPast ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800'"
                    />
                    <div v-if="!night.isPast" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
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
                    <input
                      type="text"
                      :value="formatCurrencyInput(night.rate)"
                      @input="e => { night.rate = cleanCurrencyValue(e.target.value); updateNightTotal(night); }"
                      :disabled="night.isPast"
                      class="w-full h-8 text-right font-bold border rounded-md px-2 pr-5 focus:outline-none focus:ring-1 focus:ring-sky-500 text-xs shadow-2xs"
                      :class="night.isPast ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-white border-slate-300 text-slate-800'"
                    />
                    <div v-if="!night.isPast" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
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
                <td class="p-2.5 text-right font-bold pr-4" :class="night.isPast ? 'text-slate-400' : 'text-slate-800'">
                  {{ formatCurrencyInput(night.total) }}
                </td>

                <!-- FIT/GIT TOGGLE SWITCH TỪNG ĐÊM -->
                <td class="p-2.5 text-center">
                  <label class="relative inline-flex items-center cursor-pointer select-none gap-1.5 justify-center" :class="night.isPast ? 'opacity-40 pointer-events-none' : ''">
                    <input
                      type="checkbox"
                      v-model="night.isRoom"
                      :disabled="night.isPast"
                      class="sr-only peer"
                    />
                    <div class="w-8 h-4 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4 shadow-2xs"></div>
                    <span class="text-[10px] font-extrabold uppercase min-w-[42px] text-left" :class="night.isRoom ? 'text-sky-600' : 'text-slate-500'">
                      {{ night.isRoom ? 'Phòng' : 'Master' }}
                    </span>
                  </label>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- GHI CHÚ QUÁ KHỨ -->
        <div v-if="hasPastNights" class="bg-amber-50/80 border border-amber-200 rounded-lg p-2.5 text-[11px] text-amber-800 flex items-start gap-2">
          <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 shrink-0"></i>
          <div>
            <strong>Lưu ý về phòng đang ở:</strong> Đêm thuộc quá khứ (nhỏ hơn Ngày hệ thống <span class="font-bold">{{ formatDisplayDate(systemDate) }}</span>) không được phép thêm mới/chỉnh sửa Extra Bed. Trường hợp cần phát sinh chi phí quá khứ, vui lòng tạo hóa đơn tại Modun Lễ tân.
          </div>
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

const totalQuantity = ref(0)
const totalRate = ref(0)
const totalIsRoom = ref(false) // false = MASTER (FIT), true = PHÒNG (GIT)
const dailyRates = ref([])

const hasPastNights = computed(() => dailyRates.value.some(d => d.isPast))

const computedTotalSum = computed(() => {
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
  if (!checkInStr || !checkOutStr) return dates

  let curr = new Date(checkInStr)
  const end = new Date(checkOutStr)

  if (isNaN(curr.getTime()) || isNaN(end.getTime()) || curr >= end) {
    const todayStr = checkInStr || new Date().toISOString().split('T')[0]
    return [todayStr]
  }

  while (curr < end) {
    const yyyy = curr.getFullYear()
    const mm = String(curr.getMonth() + 1).padStart(2, '0')
    const dd = String(curr.getDate()).padStart(2, '0')
    dates.push(`${yyyy}-${mm}-${dd}`)
    curr.setDate(curr.getDate() + 1)
  }
  return dates
}

watch(() => props.show, (newVal) => {
  if (newVal && props.room) {
    const checkIn = props.room.checkIn || props.room.arrivalDate
    const checkOut = props.room.checkOut || props.room.departureDate
    const stayDates = buildStayDates(checkIn, checkOut)
    
    const sysDateStr = props.systemDate || new Date().toISOString().split('T')[0]
    const defaultRate = Number(props.room.extraBedPrice) || 300000
    const defaultQty = Number(props.room.extraBedQty) || 0

    const existingDaily = props.room.dailyExtraBeds || []

    dailyRates.value = stayDates.map(dStr => {
      const isPast = dStr < sysDateStr
      const found = existingDaily.find(ed => ed.dateStr === dStr || ed.date === dStr)

      let q = found ? Number(found.quantity) : defaultQty
      let r = found ? Number(found.rate) : defaultRate
      let isRoom = found ? !!found.isRoom : false

      if (isPast) {
        q = 0
        r = 0
      }

      return {
        dateStr: dStr,
        displayDate: formatDisplayDate(dStr),
        quantity: q,
        rate: r,
        total: q * r,
        isRoom: isRoom,
        isPast: isPast
      }
    })

    // Compute total row values from first valid night or defaults
    const validNight = dailyRates.value.find(d => !d.isPast)
    totalQuantity.value = validNight ? validNight.quantity : defaultQty
    totalRate.value = validNight ? validNight.rate : defaultRate
    totalIsRoom.value = validNight ? validNight.isRoom : false
  }
})

function handleTotalQuantityChange() {
  const qty = Number(totalQuantity.value) || 0
  dailyRates.value.forEach(d => {
    if (!d.isPast) {
      d.quantity = qty
      if (qty > 0 && d.rate === 0) {
        d.rate = totalRate.value || 300000
      }
      d.total = d.quantity * d.rate
    }
  })
}

function handleTotalRateChange() {
  const r = Number(totalRate.value) || 0
  dailyRates.value.forEach(d => {
    if (!d.isPast) {
      d.rate = r
      d.total = d.quantity * d.rate
    }
  })
}

function handleTotalIsRoomChange() {
  dailyRates.value.forEach(d => {
    if (!d.isPast) {
      d.isRoom = totalIsRoom.value
    }
  })
}

function updateNightTotal(night) {
  if (night.isPast) {
    night.quantity = 0
    night.rate = 0
    night.total = 0
    return
  }
  night.total = (Number(night.quantity) || 0) * (Number(night.rate) || 0)
}

function close() {
  emit('update:show', false)
}

function save() {
  const validNights = dailyRates.value.filter(d => !d.isPast)
  const effQty = validNights.length > 0 ? Math.max(...validNights.map(d => d.quantity)) : 0
  const effRate = validNights.length > 0 ? (validNights.find(d => d.quantity > 0)?.rate || validNights[0]?.rate || 0) : 0

  emit('saved', {
    quantity: effQty,
    rate: effRate,
    totalExtraBedPrice: computedTotalSum.value,
    dailyRates: dailyRates.value.map(d => ({
      dateStr: d.dateStr,
      date: d.dateStr,
      quantity: d.quantity,
      rate: d.rate,
      total: d.total,
      isRoom: d.isRoom,
      isPast: d.isPast
    }))
  })
  close()
}
</script>
