<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  initialType: {
    type: String,
    default: 'confirmation_vn' // 'confirmation_vn' | 'confirmation_eng' | 'registration_card' | 'proforma_invoice' | 'receipt'
  },
  initialShowPrice: {
    type: Boolean,
    default: true
  },
  printData: {
    type: Object,
    default: () => ({})
  },
  selectedServices: {
    type: Array,
    default: null
  },
  selectedRooms: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'openServiceSelect'])

const currentType = ref('confirmation_vn')
const isShowPrice = ref(true)

watch(() => props.isOpen, (open) => {
  if (open) {
    currentType.value = props.initialType || 'confirmation_vn'
    isShowPrice.value = props.initialShowPrice !== undefined ? props.initialShowPrice : true
  }
}, { immediate: true })

watch(() => props.initialType, (val) => {
  if (val) currentType.value = val
})

watch(() => props.initialShowPrice, (val) => {
  if (val !== undefined) isShowPrice.value = val
})

// Dữ liệu khách sạn, booking và tổng hợp tài chính
const hotel = computed(() => props.printData?.hotel || {})
const booking = computed(() => props.printData?.booking || {})
const summary = computed(() => props.printData?.summary || {})

// Format tiền tệ
function formatCurrency(val) {
  if (!isShowPrice.value) return '---'
  const num = Number(val) || 0
  return num.toLocaleString('en-US')
}

// Format ngày tháng hiển thị dd/mm/yyyy
function formatDate(dStr) {
  if (!dStr) return ''
  const clean = String(dStr).split('T')[0].split(' ')[0]
  if (clean.includes('-')) {
    const [y, m, d] = clean.split('-')
    return `${d}/${m}/${y}`
  }
  return clean
}

// Thời gian hiện tại phục vụ hiển thị header bản in
const now = new Date()
const printDateStr = computed(() => {
  const d = String(now.getDate()).padStart(2, '0')
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const y = now.getFullYear()
  return `${d}/${m}/${y}`
})

const printTimeStr = computed(() => {
  const h = String(now.getHours()).padStart(2, '0')
  const min = String(now.getMinutes()).padStart(2, '0')
  return `${h}:${min}`
})

const printDateTimeStr = computed(() => {
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')
  const h = String(now.getHours()).padStart(2, '0')
  const min = String(now.getMinutes()).padStart(2, '0')
  const s = String(now.getSeconds()).padStart(2, '0')
  return `${y}-${m}-${d} ${h}:${min}:${s}`
})

// Danh sách phòng cần in (theo tick chọn hoặc tất cả)
const displayRooms = computed(() => {
  if (props.selectedRooms && props.selectedRooms.length > 0) {
    const selectedIds = props.selectedRooms.map(r => r.id || r.bookingRoomId)
    const matched = (props.printData?.rooms || []).filter(r => selectedIds.includes(r.id))
    if (matched.length > 0) return matched
  }
  return props.printData?.rooms || []
})

// Danh sách dịch vụ cần in cho Confirmation & Provisional Invoice
// Dòng 349: danh sách đã lọc rỗng (vd. chọn "In tiền phòng" mà chưa có tiền phòng) thì in rỗng,
// không quay về in toàn bộ dịch vụ.
const displayServices = computed(() => {
  if (Array.isArray(props.selectedServices)) {
    return props.selectedServices
  }
  return props.printData?.services || []
})

// Tính toán tổng tiền của danh sách dịch vụ đang hiển thị
const currentTotalAmount = computed(() => {
  return displayServices.value.reduce((sum, s) => sum + (Number(s.amount) || 0), 0)
})

const currentBalance = computed(() => {
  const deposit = Number(summary.value?.deposit) || 0
  return Math.max(0, currentTotalAmount.value - deposit)
})

function handlePrint() {
  window.print()
}

function handleKeyDown(e) {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close')
  }
}

onMounted(() => window.addEventListener('keydown', handleKeyDown))
onUnmounted(() => window.removeEventListener('keydown', handleKeyDown))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex flex-col bg-slate-900/85 backdrop-blur-xs text-slate-800 animate-in fade-in duration-150 overflow-y-auto"
      style="font-family: Roboto, sans-serif;"
    >
      <!-- Non-printable Top Controls Toolbar -->
      <header class="no-print sticky top-0 z-50 bg-white border-b border-slate-200 px-6 py-2.5 flex flex-wrap items-center justify-between gap-3 shrink-0 shadow-md">
        <div class="flex items-center gap-3">
          <span class="p-2 bg-sky-50 text-sky-600 rounded-lg shadow-2xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
          </span>
          <div>
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <span>Xem Trước & In Ấn</span>
              <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[11px] font-extrabold">
                {{ booking.booking_code || 'BK' }} - {{ booking.booking_name || 'Khách' }}
              </span>
            </h2>
            <p class="text-[11px] text-slate-500">Mẫu in khổ giấy tiêu chuẩn A4, chuẩn xác theo mẫu Excel thực tế của hệ thống</p>
          </div>
        </div>

        <!-- Center Controls: Template Type & Price Toggle -->
        <div class="flex items-center gap-3">
          <!-- Template selector -->
          <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
            <span>Mẫu in:</span>
            <select
              v-model="currentType"
              class="h-8 px-2.5 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#006bdb] shadow-2xs"
            >
              <option value="confirmation_vn">In xác nhận đặt phòng VN (Galliot)</option>
              <option value="confirmation_eng">In xác nhận đặt phòng ENG (Sales Tulip)</option>
              <option value="registration_card">Phiếu đăng ký khách (Registration Card)</option>
              <option value="proforma_invoice">Bảng kê dịch vụ / Hóa đơn tạm (Temporary Invoice)</option>
              <option value="receipt">Phiếu thu / Biên nhận (Receipt)</option>
            </select>
          </div>

          <!-- Show/Hide Price Switch -->
          <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer select-none bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors">
            <input
              type="checkbox"
              v-model="isShowPrice"
              class="rounded text-[#006bdb] focus:ring-[#006bdb] w-4 h-4 cursor-pointer"
            />
            <span>{{ isShowPrice ? 'Đang hiện giá' : 'Đang ẩn giá' }}</span>
          </label>

          <!-- Custom Services Button (for Confirmation or Invoice) -->
          <button
            v-if="['confirmation_vn', 'confirmation_eng', 'proforma_invoice'].includes(currentType)"
            type="button"
            @click="emit('openServiceSelect')"
            class="btn-pms-secondary h-8 px-3 text-xs font-semibold flex items-center gap-1.5 shadow-2xs cursor-pointer"
            title="Chọn các dịch vụ (bao gồm cả Lễ tân post) để đưa vào bản in"
          >
            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span>Chọn dịch vụ in ({{ displayServices.length }})</span>
          </button>
        </div>

        <!-- Right Actions: Print & Close -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="handlePrint"
            class="btn-pms-primary h-8 px-5 font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-md hover:brightness-110 active:scale-95"
            title="In ra máy in hoặc lưu PDF (Ctrl+P)"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            <span>In ngay</span>
          </button>

          <button
            type="button"
            @click="emit('close')"
            class="btn-pms-close h-8 px-4 font-semibold text-xs flex items-center gap-1.5 cursor-pointer"
            title="Đóng cửa sổ xem trước (Esc)"
          >
            <span>Đóng</span>
          </button>
        </div>
      </header>

      <!-- Main Preview Container (Centered A4 Paper Simulation) -->
      <main class="flex-1 py-8 px-4 flex justify-center items-start print:p-0 print:m-0 print:bg-white print:overflow-visible">
        <div
          class="print-paper bg-white text-black shadow-2xl rounded-sm p-10 w-full max-w-[210mm] min-h-[297mm] transition-all print:shadow-none print:rounded-none print:p-0 print:w-full print:max-w-none print:m-0"
          style="font-family: Arial, 'Times New Roman', Roboto, sans-serif; font-size: 11px; line-height: 1.4;"
        >

          <!-- ============================================================== -->
          <!-- MẪU 1: XÁC NHẬN ĐẶT PHÒNG VN (BookingConfirmationGalliotVN_NVD271.xlsx) -->
          <!-- ============================================================== -->
          <div v-if="currentType === 'confirmation_vn'" class="template-confirmation-vn">
            <!-- Header Logo & Tiêu đề -->
            <table class="w-full border-none border-collapse mb-3">
              <tbody>
              <tr>
                <td class="w-[30%] align-top p-0 border-none">
                  <img
                    v-if="hotel.logo_url"
                    :src="hotel.logo_url"
                    class="max-h-[65px] block object-contain"
                    alt="Logo"
                  />
                  <div v-else class="text-base font-black text-[#1b3764]">{{ hotel.name }}</div>
                </td>
                <td class="align-middle text-center p-0 border-none">
                  <div class="text-xl font-black text-[#1b3764] tracking-wider uppercase">
                    XÁC NHẬN ĐẶT PHÒNG
                  </div>
                </td>
              </tr>
              </tbody>
            </table>

            <!-- Thông tin liên hệ & Mã đặt phòng (Chuẩn theo 2 cột Excel) -->
            <table class="w-full border-collapse border border-slate-700 text-[11px] mb-3">
              <tbody>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold w-[18%] bg-slate-100">Người liên hệ</td>
                  <td class="border border-slate-700 p-1.5 w-[32%]">{{ booking.booker_name || booking.booking_name }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold w-[18%] bg-slate-100">Xác nhận bởi</td>
                  <td class="border border-slate-700 p-1.5 w-[32%]">{{ booking.confirmed_by }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Số điện thoại</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.booker_phone || '---' }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Số điện thoại</td>
                  <td class="border border-slate-700 p-1.5">{{ hotel.phone || '---' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Email</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.booker_email || '---' }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Email</td>
                  <td class="border border-slate-700 p-1.5">{{ hotel.email || '---' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Tên công ty</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.company_name || 'Khách lẻ / FIT' }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Ngày xác nhận</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.confirm_date }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Mã tour</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.tour_code || '---' }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Mã đặt phòng</td>
                  <td class="border border-slate-700 p-1.5 font-bold text-sky-900">{{ booking.booking_code }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Tên khách</td>
                  <td colspan="3" class="border border-slate-700 p-1.5 font-bold uppercase">{{ booking.booking_name }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Lời văn xác nhận -->
            <div class="mb-2 text-[11px] leading-relaxed">
              Cảm ơn Quý khách đã tin tưởng lựa chọn khách sạn <strong>{{ hotel.name }}</strong>,<br />
              Chúng tôi rất vinh hạnh được xác nhận đặt phòng của Quý khách với thông tin như sau:
            </div>

            <!-- Bảng chi tiết phòng (9 cột chuẩn theo Excel) -->
            <table class="w-full border-collapse border border-slate-700 text-[10.5px] mb-3 text-center">
              <thead class="bg-[#c6d1e8] font-bold text-slate-900">
                <tr>
                  <th class="border border-slate-700 p-1.5">Nhận phòng</th>
                  <th class="border border-slate-700 p-1.5">Trả phòng</th>
                  <th class="border border-slate-700 p-1.5 text-left">Dịch vụ</th>
                  <th class="border border-slate-700 p-1.5">Số phòng</th>
                  <th class="border border-slate-700 p-1.5">Số lượng</th>
                  <th class="border border-slate-700 p-1.5">Số đêm</th>
                  <th class="border border-slate-700 p-1.5">Số lượng khách</th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">Đơn giá</th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">Thành tiền</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in printData.room_lines || []" :key="idx">
                  <td class="border border-slate-700 p-1.5">{{ line.checkin }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.checkout }}</td>
                  <td class="border border-slate-700 p-1.5 text-left font-semibold">{{ line.service }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold">{{ line.room_number }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.quantity }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.nights }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.guests }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">{{ formatCurrency(line.unit_price) }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right font-bold">{{ formatCurrency(line.amount) }}</td>
                </tr>

                <!-- Dịch vụ bổ sung phát sinh nếu được tick chọn -->
                <template v-if="displayServices.some(s => !s.is_room)">
                  <tr class="bg-slate-50 font-bold text-slate-700">
                    <td :colspan="isShowPrice ? 9 : 7" class="border border-slate-700 p-1 text-left pl-2 text-[10px] uppercase">
                      Dịch vụ bổ sung:
                    </td>
                  </tr>
                  <tr v-for="svc in displayServices.filter(s => !s.is_room)" :key="svc.id">
                    <td class="border border-slate-700 p-1.5">{{ svc.date }}</td>
                    <td class="border border-slate-700 p-1.5">---</td>
                    <td class="border border-slate-700 p-1.5 text-left">{{ svc.name }}</td>
                    <td class="border border-slate-700 p-1.5">{{ svc.room_number || '---' }}</td>
                    <td class="border border-slate-700 p-1.5">{{ svc.quantity }}</td>
                    <td class="border border-slate-700 p-1.5">1</td>
                    <td class="border border-slate-700 p-1.5">---</td>
                    <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">{{ formatCurrency(svc.unit_price) }}</td>
                    <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right font-bold">{{ formatCurrency(svc.amount) }}</td>
                  </tr>
                </template>

                <!-- Tổng kết tài chính theo đúng dòng Excel -->
                <template v-if="isShowPrice">
                  <tr class="font-bold">
                    <td :colspan="isShowPrice ? 7 : 7" class="border border-slate-700 p-1.5 text-left font-bold pl-3">Tổng cộng:</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right font-black text-sky-900 text-xs">
                      {{ formatCurrency(currentTotalAmount) }}
                    </td>
                  </tr>
                  <tr class="font-bold">
                    <td :colspan="isShowPrice ? 7 : 7" class="border border-slate-700 p-1.5 text-left font-bold pl-3">Đặt cọc:</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right text-emerald-800 font-bold">
                      {{ formatCurrency(summary.deposit) }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-[#c6d1e8]">
                    <td :colspan="isShowPrice ? 7 : 7" class="border border-slate-700 p-1.5 text-left font-bold pl-3">Tổng tiền còn lại:</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right font-black text-rose-900 text-xs">
                      {{ formatCurrency(currentBalance) }}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <!-- Ghi chú & Phương thức thanh toán -->
            <div class="text-[11px] mb-2 space-y-1">
              <div><strong>Ghi chú :</strong> {{ booking.notes || '---' }}</div>
              <div><strong>Phương thức thanh toán:</strong> {{ booking.payment_method }}</div>
            </div>

            <!-- Bảng Thông tin chuyển khoản & Xuất hóa đơn -->
            <div class="grid grid-cols-2 gap-3 mb-3 text-[10.5px]">
              <table class="w-full border-collapse border border-slate-700">
                <tbody>
                  <tr class="bg-[#c6d1e8] font-bold">
                    <td colspan="2" class="border border-slate-700 p-1.5 uppercase">Thông tin chuyển khoản</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold w-[35%]">Tên tài khoản:</td>
                    <td class="border border-slate-700 p-1.5">{{ hotel.bank_account_name }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Số tài khoản:</td>
                    <td class="border border-slate-700 p-1.5 font-bold tracking-wide">{{ hotel.bank_account_number }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Ngân hàng:</td>
                    <td class="border border-slate-700 p-1.5">{{ hotel.bank_name }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Nội dung CK:</td>
                    <td class="border border-slate-700 p-1.5">Thanh toán cho đặt phòng mã {{ booking.booking_code }}</td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full border-collapse border border-slate-700">
                <tbody>
                  <tr class="bg-[#c6d1e8] font-bold">
                    <td colspan="2" class="border border-slate-700 p-1.5 uppercase">Thông tin xuất hóa đơn</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold w-[35%]">Tên công ty:</td>
                    <td class="border border-slate-700 p-1.5">{{ booking.company_name || '---' }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Địa chỉ:</td>
                    <td class="border border-slate-700 p-1.5">{{ booking.company_address || '---' }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Mã số thuế:</td>
                    <td class="border border-slate-700 p-1.5">{{ booking.company_tax_code || '---' }}</td>
                  </tr>
                  <tr>
                    <td class="border border-slate-700 p-1.5 font-bold">Email:</td>
                    <td class="border border-slate-700 p-1.5">{{ booking.company_email || '---' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Điều khoản & Quy định khách sạn chuẩn từ Excel -->
            <div class="text-[9.5px] leading-relaxed space-y-1.5 mb-3 text-slate-800 text-justify">
              <div>
                <strong>Thời gian mùa cao điểm/ mùa thấp điểm/ các ngày Lễ, Tết được quy định như sau:</strong><br />
                - Mùa cao điểm: Tháng 01, 02, 06, 07, 08, 12 | Mùa thấp điểm: Tháng 03, 04, 05, 09, 10, 11.<br />
                - Giá này là giá Net, đã bao gồm thuế và phí. Các ngày Lễ, Tết phụ thu theo quy định của khách sạn.
              </div>
              <div>
                <strong>1. Quy định về thời gian nhận/ trả phòng:</strong><br />
                - Thời gian nhận phòng: 14:00 giờ. Thời gian trả phòng: 12:00 giờ.<br />
                - Việc yêu cầu nhận phòng sớm hoặc trả phòng muộn phải được thực hiện trước bằng văn bản và gửi cho phòng kinh doanh hoặc bộ phận đặt phòng của khách sạn xác nhận.<br />
                - Trả phòng trễ trước 18:00 tính phí 50% giá phòng; sau 18:00 tính phí 100% giá phòng.<br />
                - Nhận phòng từ 8:00 tính phí 50% giá phòng; trước 8:00 tính phí 100% giá phòng.
              </div>
              <div>
                <strong>2. Chính sách dành cho trẻ em (dưới 12 tuổi):</strong><br />
                - Miễn phí 2 trẻ em dưới 6 tuổi ở chung phòng bố mẹ (không kê giường phụ và có ăn sáng).<br />
                - Trẻ em từ 6 đến 11 tuổi ở chung phòng bố mẹ phụ thu theo quy định (không kê giường phụ).<br />
                - Trẻ em từ 12 tuổi trở lên được tính như người lớn.
              </div>
              <div>
                <strong>3. Chính sách hủy:</strong><br />
                - Mùa thấp điểm: Trước 07 ngày: phạt 0%; Từ 03 - 07 ngày: 50% tổng tiền phòng; Trong vòng 03 ngày hoặc không đến: 100% tổng tiền phòng.<br />
                - Mùa cao điểm: Trước 15 ngày: phạt 0%; Từ 05 - 15 ngày: 50% tổng tiền phòng; Trong vòng 05 ngày hoặc không đến: 100% tổng tiền phòng.<br />
                - Các ngày lễ tết: Trước 30 ngày: phạt 0%; Từ 10 - 30 ngày: 50% tổng tiền phòng; Trong vòng 10 ngày hoặc không đến: 100% tổng tiền phòng.
              </div>
            </div>

            <!-- Lời kết & Thông tin liên hệ -->
            <div class="text-[10px] mb-4">
              Chúng tôi hân hạnh được chào đón Quý khách đến với khách sạn <strong>{{ hotel.name }}</strong>,<br />
              Nếu Quý khách cần thêm bất kỳ thông tin nào, xin vui lòng liên hệ với chúng tôi để được hỗ trợ.<br />
              <strong>Trân trọng cảm ơn,</strong><br />
              <strong>{{ hotel.name }}</strong> - {{ hotel.address }}<br />
              <span>P. {{ hotel.phone }}</span> | <span>E. {{ hotel.email }}</span> | <span>W. {{ hotel.website }}</span>
            </div>

            <!-- Chữ ký 2 bên -->
            <div class="flex justify-between items-start pt-2 border-t border-slate-700 text-center text-xs">
              <div class="w-1/3">
                <div class="font-bold">Chữ ký lễ tân</div>
                <div class="italic text-[10px] text-slate-500">(Ký và ghi rõ họ tên)</div>
                <div class="h-14"></div>
                <div class="font-semibold">{{ booking.confirmed_by }}</div>
              </div>
              <div class="w-1/3">
                <div class="font-bold">Chữ ký khách hàng</div>
                <div class="italic text-[10px] text-slate-500">(Ký và ghi rõ họ tên)</div>
                <div class="h-14"></div>
                <div class="font-semibold">{{ booking.booking_name }}</div>
              </div>
            </div>
          </div>

          <!-- ============================================================== -->
          <!-- MẪU 2: XÁC NHẬN ĐẶT PHÒNG ENG (BookingConfirmationForSalesTulip.xlsx) -->
          <!-- ============================================================== -->
          <div v-else-if="currentType === 'confirmation_eng'" class="template-confirmation-eng">
            <!-- Header datetime & Title -->
            <div class="flex justify-between items-start mb-2">
              <div class="text-base font-black text-[#1b3764]">{{ hotel.name_en || hotel.name }}</div>
              <div class="text-[10px] text-slate-500">{{ printDateTimeStr }}</div>
            </div>

            <div class="text-center my-3">
              <div class="text-xl font-black text-[#1b3764] tracking-wider uppercase">
                BOOKING CONFIRMATION
              </div>
            </div>

            <!-- Bảng From / To chuẩn theo Excel Tulip -->
            <div class="grid grid-cols-2 gap-4 mb-3 text-[11px] border border-slate-700 p-2.5 bg-slate-50/50">
              <div class="space-y-1">
                <div><strong>From:</strong> {{ hotel.name_en || hotel.name }}</div>
                <div><strong>Tel:</strong> {{ hotel.phone }}</div>
                <div><strong>Fax:</strong> {{ hotel.fax || '---' }}</div>
              </div>
              <div class="space-y-1">
                <div><strong>To:</strong> {{ booking.booking_name }}</div>
                <div><strong>Company:</strong> {{ booking.company_name || 'Individual' }}</div>
                <div><strong>Phone:</strong> {{ booking.booker_phone || '---' }}</div>
                <div><strong>Fax:</strong> ---</div>
                <div><strong>Email:</strong> {{ booking.booker_email || '---' }}</div>
                <div><strong>Confirmation number:</strong> <span class="font-bold text-sky-900">{{ booking.booking_code }}</span></div>
              </div>
            </div>

            <!-- Greeting text -->
            <div class="text-[11px] mb-2 leading-relaxed">
              <strong>Dear</strong> {{ booking.booking_name }} - {{ booking.company_name || 'Valued Guest' }},<br />
              Further to your request, we are pleased to confirm your reservation as follows:
            </div>

            <!-- Bảng đặt phòng 8 cột chuẩn theo Tulip -->
            <table class="w-full border-collapse border border-slate-700 text-[10.5px] mb-3 text-center">
              <thead class="bg-[#c6d1e8] font-bold text-slate-900">
                <tr>
                  <th class="border border-slate-700 p-1.5">Check in</th>
                  <th class="border border-slate-700 p-1.5">Check out</th>
                  <th class="border border-slate-700 p-1.5 text-left">Service</th>
                  <th class="border border-slate-700 p-1.5">Quantity</th>
                  <th class="border border-slate-700 p-1.5">No. of night</th>
                  <th class="border border-slate-700 p-1.5">No. of guest</th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">Price</th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in printData.room_lines || []" :key="idx">
                  <td class="border border-slate-700 p-1.5">{{ line.checkin }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.checkout }}</td>
                  <td class="border border-slate-700 p-1.5 text-left font-semibold">{{ line.service }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.quantity }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.nights }}</td>
                  <td class="border border-slate-700 p-1.5">{{ line.guests }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">{{ formatCurrency(line.unit_price) }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right font-bold">{{ formatCurrency(line.amount) }}</td>
                </tr>

                <template v-if="displayServices.some(s => !s.is_room)">
                  <tr class="bg-slate-50 font-bold text-slate-700">
                    <td :colspan="isShowPrice ? 8 : 6" class="border border-slate-700 p-1 text-left pl-2 text-[10px] uppercase">
                      Additional Services:
                    </td>
                  </tr>
                  <tr v-for="svc in displayServices.filter(s => !s.is_room)" :key="svc.id">
                    <td class="border border-slate-700 p-1.5">{{ svc.date }}</td>
                    <td class="border border-slate-700 p-1.5">---</td>
                    <td class="border border-slate-700 p-1.5 text-left">{{ svc.name }}</td>
                    <td class="border border-slate-700 p-1.5">{{ svc.quantity }}</td>
                    <td class="border border-slate-700 p-1.5">1</td>
                    <td class="border border-slate-700 p-1.5">---</td>
                    <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right">{{ formatCurrency(svc.unit_price) }}</td>
                    <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right font-bold">{{ formatCurrency(svc.amount) }}</td>
                  </tr>
                </template>

                <tr v-if="isShowPrice" class="font-bold bg-[#c6d1e8]">
                  <td colspan="6" class="border border-slate-700 p-1.5 text-left pl-3 font-bold">Total:</td>
                  <td colspan="2" class="border border-slate-700 p-1.5 text-right font-black text-sky-900 text-xs">
                    {{ formatCurrency(currentTotalAmount) }} VND
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Các điều kiện kèm theo chuẩn theo template Tulip -->
            <table class="w-full border-collapse border border-slate-700 text-[10.5px] mb-3">
              <tbody>
                <tr>
                  <td class="border border-slate-700 p-2 w-1/2">
                    <div class="flex items-center gap-4">
                      <span class="font-bold">Buffet breakfast included:</span>
                      <span>☑ Yes</span>
                      <span>☐ No</span>
                    </div>
                  </td>
                  <td class="border border-slate-700 p-2 w-1/2">
                    <div class="flex items-center gap-4">
                      <span class="font-bold">5% Service charge & 8% V.A.T included:</span>
                      <span>☑ Yes</span>
                      <span>☐ No</span>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2">
                    <span class="font-bold">Payment method:</span> {{ booking.payment_method }}
                  </td>
                  <td class="border border-slate-700 p-2">
                    <span class="font-bold">Reservation guaranteed:</span> EXPECTED CONFIRM
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="border border-slate-700 p-2">
                    <div class="font-bold mb-0.5">Note:</div>
                    <div class="text-slate-700">{{ booking.notes || ('Reservation No: ' + booking.booking_code) }}</div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Chữ ký 4 cột chuẩn theo template Tulip -->
            <div class="grid grid-cols-4 gap-2 text-center text-[10.5px] pt-6 border-t border-slate-700">
              <div>
                <div class="font-bold">Chữ ký lễ tân</div>
                <div class="italic text-[9.5px] text-slate-500">Receptionist's Signature</div>
                <div class="h-16"></div>
              </div>
              <div>
                <div class="font-bold">Chữ kí FOM/GM</div>
                <div class="italic text-[9.5px] text-slate-500">Manager's Signature</div>
                <div class="h-16"></div>
              </div>
              <div>
                <div class="font-bold">Chữ kí của khách</div>
                <div class="italic text-[9.5px] text-slate-500">Guest's Signature</div>
                <div class="h-16"></div>
              </div>
              <div>
                <div class="font-bold">Chữ ký Sale</div>
                <div class="italic text-[9.5px] text-slate-500">Sale & Reservation Dept</div>
                <div class="h-16"></div>
                <div class="font-semibold">{{ booking.confirmed_by }}</div>
              </div>
            </div>
          </div>

          <!-- ============================================================== -->
          <!-- MẪU 3: PHIẾU ĐĂNG KÝ KHÁCH (RegistrationFormMultipleRoomTulip.xlsx) -->
          <!-- ============================================================== -->
          <div v-else-if="currentType === 'registration_card'" class="template-registration-card">
            <!-- Header section với Logo & hotline -->
            <table class="w-full border-none border-collapse mb-2">
              <tbody>
              <tr>
                <td class="w-1/2 align-middle p-0 border-none">
                  <img
                    v-if="hotel.logo_url"
                    :src="hotel.logo_url"
                    class="max-h-[55px] block object-contain"
                    alt="Logo"
                  />
                  <div v-else class="text-base font-black text-[#1e3a8a]">{{ hotel.name }}</div>
                </td>
                <td class="w-1/2 align-middle text-right text-[11px] text-[#1e3a8a] p-0 border-none leading-relaxed">
                  <div>
                    <span class="font-bold">📞 {{ hotel.phone }}</span><br />
                    <span class="font-bold">🌐 {{ hotel.website || 'www.pms.vn' }}</span>
                  </div>
                </td>
              </tr>
              </tbody>
            </table>

            <div class="text-center my-3">
              <div class="text-lg font-bold tracking-wide uppercase">PHIẾU ĐĂNG KÝ LƯU TRÚ</div>
              <div class="text-base font-bold tracking-wide uppercase text-slate-700 mt-0.5">GUEST REGISTRATION CARD</div>
            </div>

            <!-- Các dòng thông tin khách theo format Tulip (Đường gạch chân dưới) -->
            <div class="space-y-2 mb-3 text-[11px]">
              <div class="flex gap-5">
                <div class="w-1/2 flex items-end">
                  <span class="font-bold whitespace-nowrap">Số Đặt Phòng/ Booking Code:</span>
                  <div class="flex-1 border-b border-black ml-2 font-bold pl-1 pb-0.5 text-sky-900">{{ booking.booking_code }}</div>
                </div>
                <div class="w-1/2 flex items-end">
                  <span class="font-bold whitespace-nowrap">Công ty/ TA/Com:</span>
                  <div class="flex-1 border-b border-black ml-2 pl-1 pb-0.5">{{ booking.company_name || 'Khách lẻ / FIT' }}</div>
                </div>
              </div>

              <div class="flex items-end">
                <span class="font-bold whitespace-nowrap">Tên Khách / Guest Name :</span>
                <div class="flex-1 border-b border-black ml-2 pl-1 pb-0.5 font-bold uppercase text-slate-900">
                  {{ displayRooms[0]?.customer_name || booking.booking_name }}
                </div>
              </div>

              <div class="flex gap-5">
                <div class="w-1/2 flex items-end">
                  <span class="font-bold whitespace-nowrap">Số điện thoại/ Contact No.:</span>
                  <div class="flex-1 border-b border-black ml-2 pl-1 pb-0.5">{{ displayRooms[0]?.phone || booking.booker_phone || '---' }}</div>
                </div>
                <div class="w-1/2 flex items-end">
                  <span class="font-bold whitespace-nowrap">Email Address:</span>
                  <div class="flex-1 border-b border-black ml-2 pl-1 pb-0.5">{{ displayRooms[0]?.email || booking.booker_email || '---' }}</div>
                </div>
              </div>

              <div class="flex items-end">
                <span class="font-bold whitespace-nowrap">Yêu Cầu đặc biệt/ Special Request:</span>
                <div class="flex-1 border-b border-black ml-2 pl-1 pb-0.5 italic text-slate-600">
                  {{ booking.special_requests || 'Không có yêu cầu đặc biệt' }}
                </div>
              </div>
            </div>

            <!-- Bảng danh sách phòng theo form Multiple Room Tulip -->
            <table class="w-full border-collapse border border-black text-[11px] text-center mb-3">
              <thead class="bg-slate-50 font-bold">
                <tr>
                  <th rowspan="2" class="border border-black p-1.5 w-[24%]">
                    Loại Phòng<br /><span class="font-normal italic text-[9.5px]">Room Type</span>
                  </th>
                  <th rowspan="2" class="border border-black p-1.5 w-[14%]">
                    Số Phòng<br /><span class="font-normal italic text-[9.5px]">Room No.</span>
                  </th>
                  <th rowspan="2" class="border border-black p-1.5 w-[14%]">
                    Ngày Đến<br /><span class="font-normal italic text-[9.5px]">Arrival</span>
                  </th>
                  <th rowspan="2" class="border border-black p-1.5 w-[14%]">
                    Ngày Đi<br /><span class="font-normal italic text-[9.5px]">Departure</span>
                  </th>
                  <th colspan="2" class="border border-black p-1 w-[16%]">
                    Số Khách<br /><span class="font-normal italic text-[9.5px]">No. of Guest</span>
                  </th>
                  <th rowspan="2" class="border border-black p-1.5 w-[18%]">
                    {{ isShowPrice ? 'Giá phòng/đêm' : 'Ăn sáng' }}<br />
                    <span class="font-normal italic text-[9.5px]">{{ isShowPrice ? 'Room Rate' : 'Breakfast' }}</span>
                  </th>
                </tr>
                <tr>
                  <th class="border border-black p-1 text-[10px]">Adult</th>
                  <th class="border border-black p-1 text-[10px]">Child</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="room in displayRooms" :key="room.id" class="h-9">
                  <td class="border border-black p-1.5 text-left font-semibold">{{ room.room_class_name }}</td>
                  <td class="border border-black p-1.5 font-bold text-base text-sky-900">{{ room.room_number || 'Chưa gán' }}</td>
                  <td class="border border-black p-1.5">{{ room.arrival_date }}</td>
                  <td class="border border-black p-1.5">{{ room.departure_date }}</td>
                  <td class="border border-black p-1.5">{{ room.adults }}</td>
                  <td class="border border-black p-1.5">{{ room.children }}</td>
                  <td class="border border-black p-1.5 font-bold">
                    {{ isShowPrice ? formatCurrency(room.rate) + ' VND' : 'Bao gồm' }}
                  </td>
                </tr>

                <!-- Khối Phương thức thanh toán theo layout Tulip -->
                <tr>
                  <td rowspan="2" class="border border-black p-1.5 font-bold text-left align-middle">
                    Hình thức thanh toán/<br /><span class="font-normal italic text-[9.5px]">Payment Method</span>
                  </td>
                  <td colspan="3" class="border border-black p-2 text-left align-middle">
                    <span class="text-sm">☐</span> Tiền mặt / Cash
                  </td>
                  <td colspan="3" class="border border-black p-2 text-left align-middle">
                    <span class="text-sm">☐</span> Thẻ / Credit Card
                  </td>
                </tr>
                <tr>
                  <td colspan="3" class="border border-black p-2 text-left align-middle">
                    <span class="text-sm">☑</span> Chuyển khoản / Bank Transfer
                  </td>
                  <td colspan="3" class="border border-black p-2 text-left align-middle">
                    <span class="text-sm">☐</span> Khác / Others
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-1.5 font-bold text-left align-middle">
                    Đặt cọc/<br /><span class="font-normal italic text-[9.5px]">Deposited</span>
                  </td>
                  <td colspan="6" class="border border-black p-1.5 text-left font-bold text-emerald-800">
                    {{ isShowPrice ? formatCurrency(summary.deposit) + ' VND' : '---' }}
                  </td>
                </tr>
                <tr>
                  <td class="border border-black p-1.5 font-bold text-left align-middle">
                    Số lượng CCCD<br /><span class="font-normal italic text-[9.5px]">No.of ID/Passport</span>
                  </td>
                  <td colspan="6" class="border border-black p-1.5 text-left"></td>
                </tr>
                <tr class="h-16">
                  <td class="border border-black p-1.5 font-bold text-left align-top">
                    Trả CCCD/ Passport<br /><span class="font-normal italic text-[9.5px]">Returned ID/Passport</span>
                  </td>
                  <td colspan="2" class="border border-black p-1.5 align-top"></td>
                  <td class="border border-black p-1.5 font-bold text-center align-top">
                    Chữ ký khách hàng<br /><span class="font-normal italic text-[9.5px]">Guest's signature</span>
                  </td>
                  <td colspan="2" class="border border-black p-1.5 align-top"></td>
                  <td class="border border-black p-1.5 font-bold text-center align-top">
                    Nhân viên / Staff<br /><span class="font-normal italic text-[9.5px]">Staff signature</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Quy định 3 thứ tiếng chuẩn từ file RegistrationFormMultipleRoomTulip.xlsx (Việt, Anh, Nga) -->
            <div class="text-[9px] leading-relaxed text-justify space-y-1.5 mb-3 text-slate-800">
              <div>
                <strong>Tiếng Việt:</strong> Khách sạn không chịu trách nhiệm về tài sản có giá trị và tiền bạc trong khu vực công cộng và trong phòng ở. Quý khách vui lòng tự bảo quản tài sản có giá trị và tiền bạc trong két sắt an toàn đã được trang bị trong phòng. Cá nhân, tổ chức ký xác nhận trên Mẫu Đăng Ký này đồng ý tuân theo mọi quy định của Khách sạn và tự chịu trách nhiệm nếu không tuân thủ các quy định nói trên. Khách sạn có quyền từ chối làm thủ tục nhận khách không tuân thủ các quy định.
              </div>
              <div class="italic text-slate-700">
                <strong>English:</strong> The signing person, company or association of this Registration Card agrees that the Hotel will not be held responsible for any valuables or money left in public areas of the hotel, or in guest rooms. Guests are advised to utilize the in-room electronic safe, located in each guest room for the safe-keeping of such items. The signing person, company or association agrees to abide by the Hotel rules and regulations, and understands that failure to abide by said regulations will result in the voiding of this contract.
              </div>
              <div class="text-slate-600">
                <strong>Русский:</strong> Лицо, компания или организация, подписавшие настоящую Регистрационную карточку, соглашаются с тем, что отель не несёт ответственности за любые ценные вещи hoặc денежные средства, оставленные в общественных зонах отеля либо в номерах гостей. Гостям рекомендуется использовать электронный сейф, установленный в каждом номере, для безопасного хранения таких предметов. Подписавшее лицо, компания или организация также обязуются соблюдать правила и положения отеля.
              </div>
            </div>

            <!-- Chữ ký 3 cột chuẩn Tulip -->
            <div class="grid grid-cols-3 gap-2 text-center text-xs pt-3 border-t border-black">
              <div>
                <div class="font-bold">Chữ ký nhân viên</div>
                <div class="italic text-[10px] text-slate-500">Staff's signature</div>
                <div class="h-16"></div>
                <div class="font-semibold">{{ booking.confirmed_by }}</div>
              </div>
              <div>
                <div class="font-bold">Chữ ký giám sát</div>
                <div class="italic text-[10px] text-slate-500">Supervisor's signature</div>
                <div class="h-16"></div>
              </div>
              <div>
                <div class="font-bold">Chữ ký khách hàng</div>
                <div class="italic text-[10px] text-slate-500">Guest's signature</div>
                <div class="h-16"></div>
                <div class="font-semibold">{{ booking.booking_name }}</div>
              </div>
            </div>
          </div>

          <!-- ============================================================== -->
          <!-- MẪU 4: BẢNG KÊ DỊCH VỤ / INVOICE (TemporaryReport.xlsx)          -->
          <!-- ============================================================== -->
          <div v-else-if="currentType === 'proforma_invoice'" class="template-proforma-invoice">
            <!-- Header khách sạn & Thông tin in ấn -->
            <table class="w-full border-none border-collapse mb-1">
              <tbody>
              <tr>
                <td class="w-1/2 align-top p-0 border-none">
                  <div class="text-base font-black text-slate-900 uppercase">{{ hotel.name }}</div>
                  <div class="text-[10.5px] text-slate-600">{{ hotel.address }}</div>
                </td>
                <td class="w-1/2 align-top text-right text-[10.5px] text-slate-700 p-0 border-none leading-relaxed">
                  <div>Hotline: <b>{{ hotel.phone }}</b></div>
                  <div>Website: <b>{{ hotel.website }}</b></div>
                  <div>{{ booking.confirmed_by }} | {{ printTimeStr }}</div>
                </td>
              </tr>
              </tbody>
            </table>

            <!-- Tiêu đề BẢNG KÊ DỊCH VỤ / INVOICE -->
            <div class="text-center my-3">
              <div class="text-xl font-black text-slate-900 tracking-wider uppercase">BẢNG KÊ DỊCH VỤ</div>
              <div class="text-sm font-bold text-slate-600 tracking-wide uppercase mt-0.5">INVOICE</div>
            </div>

            <!-- Bảng thông tin đặt phòng & khách (Chuẩn theo TemporaryReport) -->
            <table class="w-full border-collapse border border-slate-700 text-[11px] mb-3">
              <tbody>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold w-[20%] bg-slate-100">Ngày/ Date:</td>
                  <td class="border border-slate-700 p-1.5 w-[30%]">{{ printDateStr }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold w-[20%] bg-slate-100">Room:</td>
                  <td class="border border-slate-700 p-1.5 w-[30%] font-bold text-sky-900">
                    {{ displayRooms.map(r => r.room_number).filter(Boolean).join(', ') || '---' }}
                  </td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Mã đặt phòng/ Booking:</td>
                  <td class="border border-slate-700 p-1.5 font-bold text-sky-900">{{ booking.booking_code }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Mã TT/ Payment code:</td>
                  <td class="border border-slate-700 p-1.5">---</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Công ty/ TA/Com:</td>
                  <td colspan="3" class="border border-slate-700 p-1.5">{{ booking.company_name || 'Khách lẻ / FIT' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Tên Khách/ Guest Name:</td>
                  <td class="border border-slate-700 p-1.5 font-bold uppercase">{{ booking.booking_name }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Số ĐT/ Contact No.:</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.booker_phone || '---' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Ngày đến/ Arrival Date:</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.arrival_date }}</td>
                  <td class="border border-slate-700 p-1.5 font-bold bg-slate-100">Ngày đi/ Departure Date:</td>
                  <td class="border border-slate-700 p-1.5">{{ booking.departure_date }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Bảng kê dịch vụ chi tiết (6 cột chuẩn theo TemporaryReport) -->
            <table class="w-full border-collapse border border-slate-700 text-[10.5px] mb-3">
              <thead class="bg-slate-100 font-bold text-slate-900 text-center">
                <tr>
                  <th class="border border-slate-700 p-1.5 w-[14%]">Ngày Dịch Vụ<br /><span class="font-normal italic text-[9px]">Date Service</span></th>
                  <th class="border border-slate-700 p-1.5 text-left">Dịch vụ<br /><span class="font-normal italic text-[9px]">Service</span></th>
                  <th class="border border-slate-700 p-1.5 w-[10%]">Phòng<br /><span class="font-normal italic text-[9px]">Room No</span></th>
                  <th class="border border-slate-700 p-1.5 w-[10%]">Số lượng<br /><span class="font-normal italic text-[9px]">Quantity</span></th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right w-[16%]">Giá<br /><span class="font-normal italic text-[9px]">Rate</span></th>
                  <th v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right w-[18%]">Thành tiền<br /><span class="font-normal italic text-[9px]">Total Amount</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="displayServices.length === 0">
                  <td :colspan="isShowPrice ? 6 : 4" class="border border-slate-700 p-4 text-center text-slate-400 italic">
                    Không có dịch vụ nào để hiển thị
                  </td>
                </tr>
                <tr v-for="svc in displayServices" :key="svc.id">
                  <td class="border border-slate-700 p-1.5 text-center">{{ svc.date }}</td>
                  <td class="border border-slate-700 p-1.5 text-left font-medium">
                    {{ svc.name }}
                    <span v-if="svc.source === 'fo' && !svc.is_room" class="text-[9.5px] text-amber-700 font-bold ml-1">(Lễ tân post)</span>
                  </td>
                  <td class="border border-slate-700 p-1.5 text-center font-bold">{{ svc.room_number || '---' }}</td>
                  <td class="border border-slate-700 p-1.5 text-center">{{ svc.quantity }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right tabular-nums">{{ formatCurrency(svc.unit_price) }}</td>
                  <td v-if="isShowPrice" class="border border-slate-700 p-1.5 text-right font-bold tabular-nums">{{ formatCurrency(svc.amount) }}</td>
                </tr>

                <!-- Khối tổng kết tài chính chuẩn theo TemporaryReport -->
                <template v-if="isShowPrice">
                  <tr class="font-bold">
                    <td colspan="4" class="border border-slate-700 p-1.5 text-left pl-3 uppercase">TỔNG CỘNG/ TOTAL</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right font-black text-sky-900 text-xs">
                      {{ formatCurrency(currentTotalAmount) }}
                    </td>
                  </tr>
                  <tr class="font-bold">
                    <td colspan="4" class="border border-slate-700 p-1.5 text-left pl-3 uppercase">ĐÃ THANH TOÁN / PAID</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right text-emerald-800 font-bold">
                      {{ formatCurrency(summary.deposit) }}
                    </td>
                  </tr>
                  <tr class="font-bold">
                    <td colspan="4" class="border border-slate-700 p-1.5 text-left pl-3 uppercase">ĐẶT CỌC/ DEPOSITED</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right text-emerald-800 font-bold">
                      {{ formatCurrency(summary.deposit) }}
                    </td>
                  </tr>
                  <tr class="font-bold bg-slate-100">
                    <td colspan="4" class="border border-slate-700 p-1.5 text-left pl-3 uppercase">CÒN LẠI/ PAYMENT</td>
                    <td colspan="2" class="border border-slate-700 p-1.5 text-right font-black text-rose-900 text-xs">
                      {{ formatCurrency(currentBalance) }}
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>

            <!-- Số tiền bằng chữ -->
            <div v-if="isShowPrice" class="border border-slate-700 p-2 text-[11px] mb-3 bg-slate-50">
              <strong>Số tiền bằng chữ/ Amount in word:</strong> <span class="italic font-bold">{{ summary.balance_in_words || summary.amount_in_words || 'Không đồng' }}</span>
            </div>

            <!-- Bảng Lịch sử thanh toán Payment Method (nếu có thanh toán) -->
            <div class="mb-3">
              <div class="font-bold text-[11px] mb-1 uppercase">Payment Method:</div>
              <table class="w-full border-collapse border border-slate-700 text-[10px]">
                <thead class="bg-slate-50 font-bold text-center">
                  <tr>
                    <th class="border border-slate-700 p-1">Ngày</th>
                    <th class="border border-slate-700 p-1">Giờ</th>
                    <th class="border border-slate-700 p-1 text-left">Nội dung</th>
                    <th class="border border-slate-700 p-1">Phương thức</th>
                    <th class="border border-slate-700 p-1 text-right">Số tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="(printData.payments || []).length === 0">
                    <td colspan="5" class="border border-slate-700 p-2 text-center text-slate-400 italic">Chưa có giao dịch thanh toán</td>
                  </tr>
                  <tr v-for="pay in printData.payments || []" :key="pay.id">
                    <td class="border border-slate-700 p-1 text-center">{{ pay.date }}</td>
                    <td class="border border-slate-700 p-1 text-center">{{ printTimeStr }}</td>
                    <td class="border border-slate-700 p-1 text-left">{{ pay.description || 'Đặt cọc / Thanh toán' }}</td>
                    <td class="border border-slate-700 p-1 text-center">{{ pay.method_name }}</td>
                    <td class="border border-slate-700 p-1 text-right font-bold">{{ formatCurrency(pay.amount) }}</td>
                  </tr>
                  <tr class="font-bold bg-slate-50">
                    <td colspan="4" class="border border-slate-700 p-1 text-left pl-2">Tổng cộng VNĐ (Total due in VNĐ):</td>
                    <td class="border border-slate-700 p-1 text-right font-black text-sky-900">{{ formatCurrency(summary.deposit) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Chữ ký 2 bên chuẩn theo TemporaryReport -->
            <div class="flex justify-between items-start pt-3 border-t border-slate-700 text-center text-xs">
              <div class="w-1/3">
                <div class="font-bold">Chữ ký nhân viên</div>
                <div class="italic text-[10px] text-slate-500">Staff's signature</div>
                <div class="h-16"></div>
                <div class="font-semibold">{{ booking.confirmed_by }}</div>
              </div>
              <div class="w-1/3">
                <div class="font-bold">Chữ ký khách hàng</div>
                <div class="italic text-[10px] text-slate-500">Guest's signature</div>
                <div class="h-16"></div>
                <div class="font-semibold">{{ booking.booking_name }}</div>
              </div>
            </div>
          </div>

          <!-- ============================================================== -->
          <!-- MẪU 5: PHIẾU THU / RECEIPT (Receipt.xlsx)                       -->
          <!-- ============================================================== -->
          <div v-else-if="currentType === 'receipt'" class="template-receipt">
            <!-- Header khách sạn -->
            <div class="flex justify-between items-start border-b border-slate-300 pb-2 mb-3">
              <div>
                <div class="text-base font-black text-slate-900 uppercase">{{ hotel.name }}</div>
                <div class="text-[10.5px] text-slate-600">{{ hotel.address }}</div>
              </div>
              <div class="text-right text-[10.5px] text-slate-600">
                <div>Hotline: {{ hotel.phone }}</div>
                <div>{{ printDateStr }} {{ printTimeStr }}</div>
              </div>
            </div>

            <!-- Tiêu đề PHIẾU THU / RECEIPT -->
            <div class="text-center my-4">
              <div class="text-xl font-black text-slate-900 tracking-wider uppercase">PHIẾU THU</div>
              <div class="text-sm font-bold text-slate-600 tracking-wide uppercase mt-0.5">RECEIPT</div>
            </div>

            <!-- Thông tin phiếu thu chuẩn theo Receipt.xlsx -->
            <table class="w-full border-collapse border border-slate-700 text-xs mb-4">
              <tbody>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold w-[25%] bg-slate-100">Ngày/ Date:</td>
                  <td class="border border-slate-700 p-2 font-semibold">{{ printDateStr }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Tên Khách/ Guest Name:</td>
                  <td class="border border-slate-700 p-2 font-bold uppercase text-slate-900">{{ booking.booking_name }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Số điện thoại/ Contact No.:</td>
                  <td class="border border-slate-700 p-2">{{ booking.booker_phone || '---' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Tổng tiền/ Amount:</td>
                  <td class="border border-slate-700 p-2 font-black text-base text-sky-900">{{ formatCurrency(summary.deposit) }} VND</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Bằng chữ/ In word:</td>
                  <td class="border border-slate-700 p-2 italic font-bold">{{ summary.balance_in_words || 'Không đồng' }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Nội dung/ Content:</td>
                  <td class="border border-slate-700 p-2">Thu tiền đặt cọc / thanh toán cho đặt phòng mã {{ booking.booking_code }}</td>
                </tr>
                <tr>
                  <td class="border border-slate-700 p-2 font-bold bg-slate-100">Hình thức thanh toán/ Payment method:</td>
                  <td class="border border-slate-700 p-2 font-semibold">{{ booking.payment_method }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Chữ ký nhân viên thu ngân -->
            <div class="flex justify-end pt-6 text-center text-xs">
              <div class="w-1/3">
                <div class="font-bold">Chữ ký nhân viên</div>
                <div class="italic text-[10px] text-slate-500">Staff's signature</div>
                <div class="h-20"></div>
                <div class="font-semibold text-slate-900">{{ booking.confirmed_by }}</div>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </Teleport>
</template>

<style scoped>
@media print {
  body {
    background: #fff !important;
    color: #000 !important;
  }
  .no-print {
    display: none !important;
  }
  .print-paper {
    box-shadow: none !important;
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }
}
</style>
