<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { X, Plus, Info, Calendar } from '@lucide/vue'
import { fetchFOServicesList, postFoServiceBill, postRoomCharge } from '@/services/booking-service'

// ─────────────────────────────────────────────
// Props & Emits
// ─────────────────────────────────────────────
const props = defineProps({
  show: Boolean,
  bookingRoomId: { type: String, default: null },
  bookingId:     { type: [String, Number], default: '' },
  bookingInfo:   { type: String, default: '' },
  // Giá phòng hiện tại (từ booking_rooms.rate hoặc booking_room_services RM)
  roomRate:      { type: Number, default: 0 },
})

const emit = defineEmits(['close', 'success'])

// ─────────────────────────────────────────────
// State chung
// ─────────────────────────────────────────────
const activeTab    = ref('service') // 'service' | 'room'
const isSubmitting = ref(false)
const errorMsg     = ref('')

// Ngày mặc định = YYYY-MM-DD
function todayYmd() {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Mở lịch trình duyệt khi click vào ô date
function openDatePicker(e) {
  if (e.target && typeof e.target.showPicker === 'function') {
    try {
      e.target.showPicker()
    } catch {}
  }
}

// ─────────────────────────────────────────────
// TAB 1 — DỊCH VỤ
// ─────────────────────────────────────────────
const serviceFrom     = ref(todayYmd())
const serviceTo       = ref(todayYmd())
const folio           = ref(1)
const currency        = ref('VND')
const selectedService = ref(null)
const quantity        = ref(1)
const unitPrice       = ref(0)
const description     = ref('')
const isPriceLocked   = ref(false) // khoá Đơn giá nếu service có giá cố định > 0

// Danh sách dịch vụ FO
const foServices      = ref([])
const loadingServices = ref(false)

async function loadFoServices() {
  loadingServices.value = true
  try {
    const res = await fetchFOServicesList()
    const dataList = res.data?.data ?? res.data ?? []
    foServices.value = Array.isArray(dataList) ? dataList : []
  } catch (err) {
    console.error('Lỗi nạp dịch vụ FO:', err)
  } finally {
    loadingServices.value = false
  }
}

// Khi chọn dịch vụ → điền tự động & bật các ô bên dưới
watch(selectedService, (svc) => {
  if (!svc) { 
    quantity.value = 1
    unitPrice.value = 0
    description.value = ''
    isPriceLocked.value = false
    return 
  }
  description.value = svc.name
  if (svc.price && Number(svc.price) > 0) {
    unitPrice.value = Number(svc.price)
    isPriceLocked.value = true
  } else {
    unitPrice.value = 0
    isPriceLocked.value = false
  }
})

// Tổng tiền Tab 1
const totalPrice = computed(() => {
  if (!selectedService.value) return 0
  return (parseFloat(quantity.value) || 0) * (parseFloat(unitPrice.value) || 0)
})

// ─────────────────────────────────────────────
// TAB 2 — TIỀN PHÒNG
// ─────────────────────────────────────────────
const roomFrom        = ref(todayYmd())
const roomTo          = ref(todayYmd())
const roomFolio       = ref(1)
const roomCurrency    = ref('VND')
const roomDescription = ref('Dịch vụ phòng nghỉ')
const roomUpdateMode  = ref(false)   // Toggle: Cập nhật tiền phòng
const roomSurcharge   = ref(false)   // Toggle: Phụ thu tiền phòng
const customRoomRate  = ref(0)

// Toggle 1: Tự nhập tiền phòng
function toggleRoomUpdateMode() {
  roomUpdateMode.value = !roomUpdateMode.value
  if (roomUpdateMode.value) {
    roomSurcharge.value = true
  } else {
    roomSurcharge.value = false
  }
  if (roomUpdateMode.value && (!customRoomRate.value || customRoomRate.value === 0)) {
    customRoomRate.value = Math.round(Number(props.roomRate || 0))
  }
}

// Toggle 2: Bổ sung (IsRoomNight=0) vs Điều chỉnh (IsRoomNight=1)
function toggleRoomSurcharge() {
  roomSurcharge.value = !roomSurcharge.value
}

// Hiển thị Tiền phòng tự động (không thập phân .00)
const roomAutoText = computed(() => {
  if (props.roomRate && props.roomRate > 0) {
    return Math.round(Number(props.roomRate)).toLocaleString('vi-VN')
  }
  return '0'
})

// Tổng tiền Tab 2
const roomTotalPriceText = computed(() => {
  if (roomUpdateMode.value) {
    return Math.round(parseFloat(customRoomRate.value) || 0).toLocaleString('vi-VN')
  }
  return roomAutoText.value
})

// mode gửi API
const roomPostMode = computed(() => {
  if (!roomUpdateMode.value) return 'auto'
  return roomSurcharge.value ? 'surcharge' : 'update'
})

// ─────────────────────────────────────────────
// Reset & Load khi modal mở
// ─────────────────────────────────────────────
onMounted(() => {
  loadFoServices()
})

watch(() => props.show, (v) => {
  if (v) {
    errorMsg.value = ''
    activeTab.value = 'service'
    serviceFrom.value = todayYmd()
    serviceTo.value   = todayYmd()
    folio.value       = 1
    selectedService.value = null
    quantity.value    = 1
    unitPrice.value   = 0
    description.value = ''
    roomFrom.value    = todayYmd()
    roomTo.value      = todayYmd()
    roomUpdateMode.value  = false
    roomSurcharge.value   = false
    customRoomRate.value  = 0
    roomDescription.value = 'Dịch vụ phòng nghỉ'
    if (foServices.value.length === 0) {
      loadFoServices()
    }
  }
}, { immediate: true })

// ─────────────────────────────────────────────
// Submit
// ─────────────────────────────────────────────
async function handleSubmit() {
  errorMsg.value  = ''
  isSubmitting.value = true
  try {
    if (activeTab.value === 'service') {
      if (!selectedService.value) { errorMsg.value = 'Vui lòng chọn dịch vụ.'; isSubmitting.value = false; return }
      if (!quantity.value || quantity.value <= 0) { errorMsg.value = 'Số lượng phải > 0.'; isSubmitting.value = false; return }
      await postFoServiceBill({
        booking_room_id: props.bookingRoomId || undefined,
        booking_id:      props.bookingId || undefined,
        date_from:    serviceFrom.value,
        date_to:      serviceTo.value,
        service_code: selectedService.value.code,
        quantity:     parseFloat(quantity.value),
        rate:         parseFloat(unitPrice.value),
        folio:        parseInt(folio.value),
        description:  description.value,
        currency:     currency.value,
      })
    } else {
      if ((roomUpdateMode.value || roomSurcharge.value) && (!customRoomRate.value || customRoomRate.value < 0)) {
        errorMsg.value = 'Vui lòng nhập giá phòng.'
        isSubmitting.value = false
        return
      }
      await postRoomCharge({
        booking_room_id: props.bookingRoomId || undefined,
        booking_id:      props.bookingId || undefined,
        date_from:   roomFrom.value,
        date_to:     roomTo.value,
        mode:        roomPostMode.value,
        rate:        (roomUpdateMode.value || roomSurcharge.value) ? parseFloat(customRoomRate.value) : undefined,
        folio:       parseInt(roomFolio.value),
        description: roomDescription.value,
        currency:    roomCurrency.value,
      })
    }
    emit('success')
    emit('close')
  } catch (err) {
    errorMsg.value = err?.response?.data?.message ?? 'Có lỗi xảy ra, vui lòng thử lại.'
  } finally {
    isSubmitting.value = false
  }
}

function handleClose() {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <!-- Modal Dialog với width rộng hơn (max-w-xl) -->
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col text-sm border border-gray-100">

      <!-- Header Navy Dark -->
      <div class="bg-[#1e293b] text-white px-5 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="font-semibold text-base">Thêm dịch vụ</span>
        </div>
        <button @click="handleClose" class="text-gray-300 hover:text-white transition-colors p-1">
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-5 space-y-4.5 overflow-y-auto max-h-[82vh]">

        <!-- Field Đăng ký -->
        <div>
          <label class="block text-xs font-normal text-gray-500 mb-1.5">Đăng ký</label>
          <input type="text" :value="bookingInfo" readonly
            class="w-full px-3.5 py-2.5 bg-[#f8fafc] border border-gray-200 rounded-lg text-sm text-gray-800 font-medium cursor-default focus:outline-none" />
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-6 border-b border-gray-200">
          <button @click="activeTab = 'service'"
            :class="['pb-2.5 text-sm font-medium transition-all relative -mb-px',
              activeTab === 'service'
                ? 'text-blue-600 font-semibold border-b-2 border-blue-600'
                : 'text-gray-500 hover:text-gray-800 border-b-2 border-transparent']">
            Dịch vụ
          </button>
          <button @click="activeTab = 'room'"
            :class="['pb-2.5 text-sm font-medium transition-all relative -mb-px',
              activeTab === 'room'
                ? 'text-blue-600 font-semibold border-b-2 border-blue-600'
                : 'text-gray-500 hover:text-gray-800 border-b-2 border-transparent']">
            Tiền phòng
          </button>
        </div>

        <!-- Error Alert -->
        <div v-if="errorMsg" class="bg-red-50 border border-red-200 text-red-700 px-3.5 py-2.5 rounded-lg text-xs font-medium">
          {{ errorMsg }}
        </div>

        <!-- ─── TAB DỊCH VỤ ─── -->
        <template v-if="activeTab === 'service'">

          <!-- Field Ngày: 2 ô Từ ngày ~ Đến ngày rõ ràng, click mở lịch -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">
              Ngày <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
              <div class="relative flex items-center border border-gray-200 rounded-lg px-3 py-2 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                <span class="text-gray-400 text-xs font-medium mr-2 flex-shrink-0">Từ</span>
                <input v-model="serviceFrom" type="date" @click="openDatePicker"
                  class="w-full text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer" />
              </div>
              <div class="relative flex items-center border border-gray-200 rounded-lg px-3 py-2 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                <span class="text-gray-400 text-xs font-medium mr-2 flex-shrink-0">Đến</span>
                <input v-model="serviceTo" type="date" @click="openDatePicker"
                  class="w-full text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer" />
              </div>
            </div>
          </div>

          <!-- Field Dịch vụ + Folio -->
          <div class="grid grid-cols-4 gap-3">
            <div class="col-span-3">
              <label class="block text-xs font-medium text-gray-700 mb-1.5">
                Dịch vụ <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <select v-model="selectedService"
                  class="w-full border border-gray-200 rounded-lg pl-3.5 pr-9 py-2.5 text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-800 font-medium appearance-none">
                  <option :value="null" disabled>{{ loadingServices ? 'Đang tải danh sách...' : 'Chọn dịch vụ' }}</option>
                  <option v-for="svc in foServices" :key="svc.code" :value="svc">
                    {{ svc.name }} {{ Number(svc.price) > 0 ? `(${Number(svc.price).toLocaleString('vi-VN')} đ)` : '' }}
                  </option>
                </select>

                <!-- Nút X đỏ hủy chọn dịch vụ -->
                <button v-if="selectedService" @click="selectedService = null" type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-700 font-bold p-0.5 focus:outline-none"
                  title="Bỏ chọn dịch vụ">
                  <X class="w-4 h-4" />
                </button>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Folio</label>
              <select v-model.number="folio"
                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-800 font-medium">
                <option :value="1">Folio 1</option>
                <option :value="2">Folio 2</option>
                <option :value="3">Folio 3</option>
              </select>
            </div>
          </div>

          <!-- Grid: Số lượng | Đơn giá | Tổng tiền -->
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">
                Số lượng <span class="text-red-500">*</span>
              </label>
              <input v-model.number="quantity" type="number" min="0.01" step="1"
                :disabled="!selectedService"
                :class="['w-full border rounded-lg px-3.5 py-2.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-blue-500',
                  !selectedService ? 'bg-[#f8fafc] text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white border-gray-200 text-gray-800']" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">
                Đơn giá <span class="text-red-500">*</span>
              </label>
              <input v-model.number="unitPrice" type="number" min="0"
                :disabled="!selectedService || isPriceLocked"
                :class="['w-full border rounded-lg px-3.5 py-2.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-blue-500',
                  (!selectedService || isPriceLocked) ? 'bg-[#f8fafc] text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white border-gray-200 text-gray-800']" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Tổng tiền</label>
              <input :value="totalPrice.toLocaleString('vi-VN')" readonly disabled
                class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm bg-[#f8fafc] text-gray-600 font-semibold cursor-not-allowed" />
            </div>
          </div>

          <!-- Field Mô tả -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1.5">Mô tả</label>
            <input v-model="description" type="text"
              :disabled="!selectedService"
              :class="['w-full border rounded-lg px-3.5 py-2.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-blue-500',
                !selectedService ? 'bg-[#f8fafc] text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white border-gray-200 text-gray-800']" />
          </div>

        </template>

        <!-- ─── TAB TIỀN PHÒNG ─── -->
        <template v-else>

          <!-- Grid: Ngày | Tiền phòng tự động -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">
                Ngày <span class="text-red-500">*</span>
              </label>
              <div class="flex items-center justify-between border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                <div class="flex items-center gap-1 text-xs font-medium text-gray-800 min-w-0">
                  <input v-model="roomFrom" type="date" @click="openDatePicker"
                    class="w-[110px] text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer" />
                  <span class="text-gray-400">~</span>
                  <input v-model="roomTo" type="date" @click="openDatePicker"
                    class="w-[110px] text-xs font-medium bg-transparent border-none p-0 focus:outline-none text-gray-800 cursor-pointer" />
                </div>
                <Calendar class="w-4 h-4 text-indigo-400 flex-shrink-0 ml-1" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Tiền phòng tự động</label>
              <input :value="roomAutoText" readonly disabled
                class="w-full border border-blue-500/80 rounded-lg px-3.5 py-2.5 text-sm bg-white text-gray-800 font-semibold cursor-not-allowed shadow-xs" />
            </div>
          </div>

          <!-- Card Toggles + Tổng tiền -->
          <div class="bg-[#f8fafc] border border-gray-200/80 rounded-xl p-4 grid grid-cols-2 gap-4 items-center">
            <!-- Left Side: Toggles -->
            <div class="space-y-3">
              <!-- Toggle 1: Tự nhập tiền phòng -->
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-gray-800">Tự nhập tiền phòng</span>
                  <div class="relative group">
                    <Info class="w-3.5 h-3.5 text-gray-400 cursor-help" />
                    <div class="absolute bottom-5 left-0 bg-gray-800 text-white text-xs rounded px-2.5 py-1.5 w-60 opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none shadow-md">
                      Bật để tự nhập giá tiền phòng mới cho khoảng ngày đã chọn
                    </div>
                  </div>
                </div>
                <button type="button" @click="toggleRoomUpdateMode"
                  :class="['relative w-9 h-5 rounded-full transition-colors flex-shrink-0',
                    roomUpdateMode ? 'bg-blue-600' : 'bg-gray-300']">
                  <div :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    roomUpdateMode ? 'translate-x-4.5' : 'translate-x-0.5']" />
                </button>
              </div>

              <!-- Toggle 2: Sub-option (chỉ xuất hiện khi bật Tự nhập tiền phòng) -->
              <div v-if="roomUpdateMode" class="flex items-center justify-between pt-1 border-t border-gray-200/60">
                <div class="flex items-center gap-1.5">
                  <span :class="['text-xs font-medium', roomSurcharge ? 'text-blue-700 font-semibold' : 'text-gray-700']">
                    {{ roomSurcharge ? 'Bổ sung tiền phòng - không ảnh hưởng công suất' : 'Điều chỉnh tiền phòng - tính công suất phòng' }}
                  </span>
                  <div class="relative group">
                    <Info class="w-3.5 h-3.5 text-gray-400 cursor-help" />
                    <div class="absolute bottom-5 left-0 bg-gray-800 text-white text-xs rounded px-2.5 py-1.5 w-64 opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none shadow-md">
                      {{ roomSurcharge ? 'Lưu IsRoomNight=0 trong SP3004 (không ảnh hưởng công suất phòng)' : 'Lưu IsRoomNight=1 trong SP3004 (có tính công suất phòng)' }}
                    </div>
                  </div>
                </div>
                <button type="button" @click="toggleRoomSurcharge"
                  :class="['relative w-9 h-5 rounded-full transition-colors flex-shrink-0 ml-2',
                    roomSurcharge ? 'bg-blue-600' : 'bg-gray-300']">
                  <div :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    roomSurcharge ? 'translate-x-4.5' : 'translate-x-0.5']" />
                </button>
              </div>
            </div>

            <!-- Right Side: Tổng tiền -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Tổng tiền</label>
              <input v-if="roomUpdateMode" v-model.number="customRoomRate" type="number" min="0"
                class="w-full border border-blue-500 rounded-lg px-3.5 py-2.5 text-sm bg-white text-gray-800 font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500" />
              <input v-else :value="roomAutoText" readonly disabled
                class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm bg-[#f1f5f9] text-gray-500 font-semibold cursor-not-allowed" />
            </div>
          </div>

          <!-- Field Mô tả + Folio -->
          <div class="grid grid-cols-4 gap-3">
            <div class="col-span-3">
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Mô tả</label>
              <input v-model="roomDescription" type="text"
                class="w-full border border-gray-200 rounded-lg px-3.5 py-2.5 text-sm bg-white text-gray-800 font-medium focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Folio</label>
              <select v-model.number="roomFolio"
                class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-gray-800 font-medium">
                <option :value="1">Folio 1</option>
                <option :value="2">Folio 2</option>
                <option :value="3">Folio 3</option>
              </select>
            </div>
          </div>

        </template>

      </div>

      <!-- Footer -->
      <div class="border-t border-gray-100 px-6 py-3.5 flex justify-end gap-3 bg-white">
        <button @click="handleClose"
          class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          Hủy
        </button>
        <button @click="handleSubmit" :disabled="isSubmitting || (activeTab === 'service' && !selectedService)"
          class="px-6 py-2 text-sm font-semibold text-white bg-[#2563eb] rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <Plus class="w-4 h-4" />
          <span>{{ isSubmitting ? 'Đang xử lý...' : '+ Thêm' }}</span>
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Style chỉ hiển thị icon lịch mặc định ở góc phải ô date, click mở bộ chọn ngày */
input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
  filter: opacity(0.6);
}
</style>
