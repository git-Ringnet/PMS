<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Plus,
  PlusSquare,
  Scissors,
  Layers,
  Printer,
  FileText,
  FileX,
  Trash2,
  RotateCcw,
  CreditCard,
  Filter,
  LogOut,
  Search,
  Calendar,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  RefreshCw,
  Inbox,
  ArrowRightLeft,
  X
} from '@lucide/vue'
import { fetchBookings, transferBookingRoomServicesFolio, splitBookingRoomServicesFolio, fetchQuickTransferCandidates, quickTransferBookingRoomServices, cancelBookingRoomServices, transferPaymentFolio, splitPayment, transferPayments, fetchSystemDate, deleteBookingPayment, updateBookingNoPost, updateBookingRoomNoPost } from '@/services/booking-service'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import echo from '@/services/echo'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const route = useRoute()
const router = useRouter()

// Sidebar collapse state
const isSidebarCollapsed = ref(false)
const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// UI State
const searchQuery = ref('')
const registerFilter = ref('old')
const showRegisterFilterDropdown = ref(false)
const filterDateScope = ref('today')
const filterDepartureChecked = ref(true)
const filterDateFrom = ref('')
const filterDateTo = ref('')
const showAllGuestsInRoom = ref(false)
const filterHasBeenApplied = ref(false)
const appliedCheckoutFilters = ref({
  registerFilter: 'old',
  dateScope: 'today',
  departureChecked: true,
  dateFrom: '',
  dateTo: ''
})

function localDateInput(value = new Date()) {
  const d = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function shiftDate(dateInput, days) {
  const date = new Date(`${dateInput}T00:00:00`)
  if (Number.isNaN(date.getTime())) return dateInput
  date.setDate(date.getDate() + days)
  return localDateInput(date)
}

function setFilterDatesForScope(scope = filterDateScope.value) {
  const base = localDateInput(systemDate.value || new Date())
  if (scope === 'today') {
    filterDateFrom.value = base
    filterDateTo.value = base
  } else if (scope === 'yesterday') {
    const yesterday = shiftDate(base, -1)
    filterDateFrom.value = yesterday
    filterDateTo.value = yesterday
  } else if (scope === 'this_week') {
    const d = new Date(`${base}T00:00:00`)
    const mondayOffset = (d.getDay() + 6) % 7
    filterDateFrom.value = shiftDate(base, -mondayOffset)
    filterDateTo.value = shiftDate(filterDateFrom.value, 6)
  } else if (scope === 'this_month') {
    const d = new Date(`${base}T00:00:00`)
    const first = new Date(d.getFullYear(), d.getMonth(), 1)
    const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
    filterDateFrom.value = localDateInput(first)
    filterDateTo.value = localDateInput(last)
  }
}

function dateOnly(value) {
  if (!value) return ''
  const text = String(value)
  const iso = text.match(/^(\d{4}-\d{2}-\d{2})/)
  if (iso) return iso[1]
  const vn = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  return vn ? `${vn[3]}-${vn[2]}-${vn[1]}` : localDateInput(value)
}

function isDateInRange(value, from, to) {
  const date = dateOnly(value)
  if (!date || !from || !to) return true
  return date >= from && date <= to
}

function isVirtualBooking(booking) {
  return Boolean(
    booking.rawBooking?.is_virtual ||
    booking.roomItems?.some(room => room.isVirtual)
  )
}

function matchesCheckoutFilter(booking, filters) {
  const rawStatus = Number(booking.rawBooking?.status)
  if (filters.registerFilter === 'current' && rawStatus !== 1) return false
  if (filters.registerFilter === 'old' && rawStatus !== 0) return false
  if (filters.registerFilter === 'virtual' && !isVirtualBooking(booking)) return false

  const dateValue = filters.departureChecked ? booking.departureDate : booking.arrivalDate
  return isDateInRange(dateValue, filters.dateFrom, filters.dateTo)
}

async function applyCheckoutFilters(closeDropdown = true) {
  appliedCheckoutFilters.value = {
    registerFilter: registerFilter.value,
    dateScope: filterDateScope.value,
    departureChecked: filterDepartureChecked.value,
    dateFrom: dateOnly(filterDateFrom.value),
    dateTo: dateOnly(filterDateTo.value)
  }
  filterHasBeenApplied.value = true
  await loadCheckoutBookings()
  if (closeDropdown) showRegisterFilterDropdown.value = false
}

function resetCheckoutFilterDraft() {
  const current = appliedCheckoutFilters.value
  registerFilter.value = current.registerFilter
  filterDateScope.value = current.dateScope
  filterDepartureChecked.value = current.departureChecked
  filterDateFrom.value = current.dateFrom
  filterDateTo.value = current.dateTo
  showRegisterFilterDropdown.value = false
}

function copyFilterDate(source) {
  if (source === 'from' && filterDateFrom.value) {
    filterDateTo.value = filterDateFrom.value
  } else if (source === 'to' && filterDateTo.value) {
    filterDateFrom.value = filterDateTo.value
  }
  filterDateScope.value = 'custom'
}
function openDatePicker(event) {
  const input = event.currentTarget?.querySelector('input')
  if (!input) return
  try {
    if (typeof input.showPicker === 'function') input.showPicker()
    else input.focus()
  } catch (_) {
    input.focus()
  }
}
function handleFilterScopeChange() {
  if (filterDateScope.value !== 'custom') setFilterDatesForScope(filterDateScope.value)
}

const isNoPost = ref(false)
const noPostSaving = ref(false)

const selectedGuest = ref('Guest 1')
const selectedGuestId = ref(null)
const roomNumber = ref('')
const noteText = ref('')
const activeFolioTab = ref('A')

// Dropdown In hóa đơn state
const showInvoiceMenu = ref(false)

// Modal states
import AddServiceModal from './components/AddServiceModal.vue'
import AddHousekeepingServiceModal from './components/AddHousekeepingServiceModal.vue'
import QuickTransferBillModal from './components/QuickTransferBillModal.vue'
import PrepaymentModal from './components/PrepaymentModal.vue'
import PaymentModal from './components/PaymentModal.vue'
import FilterServiceModal from './components/FilterServiceModal.vue'
import TransferServiceModal from './components/TransferServiceModal.vue'
import TransferPaymentModal from './components/TransferPaymentModal.vue'
import SplitServiceModal from './components/SplitServiceModal.vue'
import CancelServiceModal from './components/CancelServiceModal.vue'
import SplitDepositModal from './components/SplitDepositModal.vue'
import DeletePaymentModal from './components/DeletePaymentModal.vue'
const showAddServiceModal = ref(false)
const showHousekeepingServiceModal = ref(false)
const showQuickTransferBillModal = ref(false)
const quickTransferCandidates = ref([])
const quickTransferLoadingText = ref('')
const showPrepaymentModal = ref(false)
const showPaymentModal = ref(false)
const showFilterServiceModal = ref(false)
const showTransferServiceModal = ref(false)
const transferServiceError = ref('')
const showTransferPaymentModal = ref(false)
const showDeletePaymentModal = ref(false)
const transferPaymentError = ref('')
const showSplitServiceModal = ref(false)
const showCancelServiceModal = ref(false)
const showSplitDepositModal = ref(false)
const roomAdjustment = ref(null)
const housekeepingAdjustment = ref(null)

const openAddHousekeepingService = () => {
  // Dòng master chỉ đại diện booking; dịch vụ BP luôn hạch toán cho một phòng cụ thể.
  if (!selectedRoomItem.value) return
  showHousekeepingServiceModal.value = true
}

// State dữ liệu thực từ CSDL
const allBookingsList = ref([])
const displayedBookingsList = ref([])
const selectedBooking = ref(null)
const selectedRoomItem = ref(null)
const systemDate = ref('')

const addServiceBookingInfo = computed(() => {
  if (selectedRoomItem.value) {
    const guest = selectedGuest.value || selectedRoomItem.value.guestName || (selectedBooking.value ? selectedBooking.value.name : '')
    const roomNo = selectedRoomItem.value.roomNumber ? selectedRoomItem.value.roomNumber : ''
    return roomNo ? `${guest} - ${roomNo}` : `${selectedBooking.value?.code || ''} - ${guest}`
  }
  if (selectedBooking.value) {
    return `${selectedBooking.value.code} - ${selectedBooking.value.name}`
  }
  return ''
})
const selectedServiceGroup = ref(null)
const selectedServiceIds = ref([])
const selectedPaymentIds = ref([])
const draggedServiceGroup = ref(null)
const draggedPayment = ref(null)
const draggedOverFolio = ref(null)
const isLoading = ref(true)
const isServiceOperationLoading = ref(false)
const showSearchDropdown = ref(false)
const showPanelGuestDropdown = ref(false)
const searchContainerRef = ref(null)
const filterContainerRef = ref(null)

function formatDate(dateStr) {
  if (!dateStr) return '-- / -- / ----'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day} / ${month} / ${year}`
}

function formatTime(value) {
  if (!value) return ''
  const text = String(value).trim()
  const directTime = text.match(/^(\d{1,2}):(\d{2})/)
  const dateTime = text.match(/[T\s](\d{1,2}):(\d{2})/)
  const match = directTime || dateTime
  if (match) return `${String(match[1]).padStart(2, '0')}:${match[2]}`

  const date = new Date(value)
  if (isNaN(date.getTime())) return ''
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function formatServiceDateTime(serviceDate, createdAt, openTime = null) {
  const date = formatDate(serviceDate || createdAt)
  const time = formatTime(openTime) || formatTime(createdAt)
  return time ? `${date} ${time}` : date
}

function formatMoney(num) {
  if (!num) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}

function formatSummaryMoney(num) {
  if (!num) return '0'
  return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(Math.round(Number(num) || 0))
}

function formatInvoiceMoney(num) {
  const value = Number(num) || 0
  const hasFraction = Math.abs(value % 1) > 0.000001
  return new Intl.NumberFormat('vi-VN', {
    minimumFractionDigits: hasFraction ? 2 : 0,
    maximumFractionDigits: 2
  }).format(value)
}

function formatInvoiceQuantity(num) {
  const value = Number(num) || 0
  return new Intl.NumberFormat('vi-VN', {
    maximumFractionDigits: 6
  }).format(value)
}

const loadSystemDate = async () => {
  try {
    const res = await fetchSystemDate()
    if (res.data?.data?.system_date) {
      systemDate.value = res.data.data.system_date
      if (filterDateScope.value !== 'custom') setFilterDatesForScope(filterDateScope.value)
    }
  } catch (err) {
    console.error('Lỗi khi tải ngày hệ thống:', err)
  }
}

const loadCheckoutBookings = async () => {
  isLoading.value = true
  try {
    const params = {}
    if (filterHasBeenApplied.value) {
      if (appliedCheckoutFilters.value.registerFilter === 'current') {
        params.status = '1'
      } else if (appliedCheckoutFilters.value.registerFilter === 'old') {
        params.status = '0'
      } else {
        params.status = '0,1'
      }

      if (appliedCheckoutFilters.value.dateFrom && appliedCheckoutFilters.value.dateTo) {
        params.from_date = appliedCheckoutFilters.value.dateFrom
        params.to_date = appliedCheckoutFilters.value.dateTo
        params.date_type = appliedCheckoutFilters.value.departureChecked ? 'departure' : 'arrival'
      }
    } else {
      params.status = '0,1'
    }

    const res = await fetchBookings(params)
    const list = res.data?.data || res.data || []

    const formatted = []
    list.forEach(b => {
      const code = b.booking_code || b.code || `GAL${b.id}`
      const mainGuestName = (
        (b.booking_name && b.booking_name.trim()) ||
        (b.guest_name && b.guest_name.trim()) ||
        (b.contact_name && b.contact_name.trim()) ||
        (b.customer?.full_name && b.customer.full_name.trim()) ||
        (b.customer?.name && b.customer.name.trim()) ||
        (b.company?.name && b.company.name.trim()) ||
        (b.booker?.full_name && b.booker.full_name.trim()) ||
        'Khách lẻ'
      )

      const roomItems = []
      const masterSend = b.is_master_room_rate !== undefined ? Boolean(b.is_master_room_rate) : true

      if (b.booking_rooms && b.booking_rooms.length > 0) {
        b.booking_rooms.forEach(r => {
          const roomNo = r.room_number || r.room || (r.room && r.room.room_number) || ''
          const isVirtualRoom = Boolean(r.is_virtual || r.is_internal || r.room?.is_virtual || r.room?.is_internal || !roomNo)
          if ((!roomNo && !isVirtualRoom) || ![0, 1].includes(Number(r.status))) return
          const displayRoomNo = roomNo || 'PM'
          
          const roomGuests = []
          if (r.guest_name && r.guest_name.trim()) {
            roomGuests.push({ id: null, name: r.guest_name.trim(), isPrimary: true })
          }
          if (r.guests && Array.isArray(r.guests) && r.guests.length > 0) {
            r.guests.forEach(g => {
              const gName = g.guest?.full_name || g.full_name || (g.first_name ? `${g.first_name} ${g.last_name || ''}`.trim() : '')
              // Mỗi liên kết khách-phòng phải hiện thành một lựa chọn riêng.
              // Không loại trùng theo tên vì hai khách có thể cùng tên.
              const guestId = g.guest_id || g.guest?.id || g.id || null
              if (gName && guestId) {
                roomGuests.push({ id: guestId, name: gName, isPrimary: Boolean(g.is_primary) })
              }
            })
          }
          if (roomGuests.length === 0) {
            roomGuests.push({ id: null, name: mainGuestName, isPrimary: true })
          }

          let extraSvc = 0
          if (r.services && r.services.length > 0) {
            extraSvc = r.services
              .filter(s => !isRoomCharge(s))
              .reduce((acc, s) => {
                const itemTotal = Number(s.total_amount) || (Number(s.quantity || 1) * (Number(s.rate) || Number(s.price) || Number(s.amount) || 0))
                return acc + itemTotal
              }, 0)
          }

          let postedRoomCharge = 0
          let unpaidRoomCharge = 0
          const processedBillIdsInRoom = new Set()

          // 1. Lấy từ master_service_bills / service_bills của booking nếu bill đó gắn với phòng này
          const allBookingBills = b.master_service_bills || b.service_bills || []
          allBookingBills.forEach(sb => {
            if (Number(sb.Edit) === 1) return
            if (!isRoomCharge(sb)) return

            const isCurrentRoomOwner = String(sb.RentalRoomId2) === String(r.id)
            const isOriginalRoomOwner = !sb.RentalRoomId2 && String(sb.RentalRoomId1) === String(r.id)
            if (isCurrentRoomOwner || isOriginalRoomOwner) {
              if (sb.Ma) processedBillIdsInRoom.add(String(sb.Ma))
              const amt = Number(sb.Amount) || 0
              postedRoomCharge += amt
              const isPaid = Number(sb.Status) === 2 || Boolean(sb.PaymentID || sb.PaymentId)
              if (!isPaid) {
                unpaidRoomCharge += amt
              }
            }
          })

          // 2. Lấy thêm dịch vụ phòng từ r.services nếu chưa có trong service_bills
          if (r.services && r.services.length > 0) {
            r.services.forEach(s => {
              if (isRoomCharge(s)) {
                if (s.service_bill_id && processedBillIdsInRoom.has(String(s.service_bill_id))) return
                if (!s.service_bill_id && processedBillIdsInRoom.size > 0) return
                const itemTotal = Number(s.total_amount) || (Number(s.quantity || 1) * Number(s.rate || 0))
                postedRoomCharge += itemTotal
                const isPaid = Number(s.status) === 2 || Boolean(s.payment_id || s.payment_code)
                if (!isPaid) {
                  unpaidRoomCharge += itemTotal
                }
              }
            })
          }

          const roomChargeTotal = postedRoomCharge
          const unpaidRoomChargeTotal = unpaidRoomCharge
          const roomSvc = extraSvc + roomChargeTotal
          const roomDepositTotal = (b.payments || [])
            .filter(payment => (
              payment.pack2 === 'DPR'
              && Number(payment.edit_flag) === 0
              && !payment.deleted_at
              && String(payment.booking_room_id) === String(r.id)
            ))
            .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)

          // Tính tổng cọc/thanh toán riêng cho từng phòng
          const roomPaidAmount = (b.payments || [])
            .filter(p => (!p.edit_flag || Number(p.edit_flag) === 0) && !p.deleted_at && String(p.booking_room_id) === String(r.id))
            .reduce((sum, p) => sum + Number(p.amount || 0), 0)

          const primaryGuestId = roomGuests.find(guest => guest.isPrimary)?.id || roomGuests[0].id
          roomGuests.forEach(guest => {
            let guestSvcTotal = 0
            const processedBillIds = new Set()

            if (r.services && r.services.length > 0) {
              r.services.forEach(s => {
                const sGuestId = s.guest_id || s.guestId || s.CustomerId1 || s.customerId1 || s.customer_id_1 || null
                const belongsToThisGuest = sGuestId ? (String(sGuestId) === String(guest.id)) : (String(guest.id) === String(primaryGuestId))
                
                if (belongsToThisGuest) {
                  if (s.service_bill_id) processedBillIds.add(String(s.service_bill_id))
                  const itemTotal = Number(s.total_amount) || (Number(s.quantity || 1) * (Number(s.rate) || Number(s.price) || Number(s.amount) || 0))
                  guestSvcTotal += itemTotal
                }
              })
            }

            allBookingBills.forEach(sb => {
              if (Number(sb.Edit) === 1) return
              if (sb.Ma && processedBillIds.has(String(sb.Ma))) return
              const isCurrentRoomOwner = String(sb.RentalRoomId2) === String(r.id)
              const isOriginalRoomOwner = !sb.RentalRoomId2 && String(sb.RentalRoomId1) === String(r.id)
              if (isCurrentRoomOwner || isOriginalRoomOwner) {
                const billGuestId = sb.CustomerId2 || sb.CustomerId1
                const belongsToThisGuest = billGuestId ? (String(billGuestId) === String(guest.id)) : (String(guest.id) === String(primaryGuestId))
                if (belongsToThisGuest) {
                  guestSvcTotal += Number(sb.Amount) || 0
                }
              }
            })

            guest.serviceAmount = guestSvcTotal

            const guestPaidTotal = (b.payments || [])
              .filter(p => {
                if (p.edit_flag && Number(p.edit_flag) !== 0) return false
                if (p.deleted_at) return false
                if (String(p.booking_room_id) !== String(r.id)) return false
                const pGuestId = p.guest_id || p.guestId || p.customer_id || null
                if (!pGuestId) return true
                return String(pGuestId) === String(guest.id) || !guest.id
              })
              .reduce((sum, p) => sum + Number(p.amount || 0), 0)
            guest.paidAmount = guestPaidTotal
          })

          roomItems.push({
            id: `R${r.id || b.id}`,
            roomId: r.id,
            code: code,
            roomNumber: displayRoomNo,
            isVirtual: isVirtualRoom,
            guestName: roomGuests[0].name,
            allGuests: roomGuests,
            primaryGuestId: primaryGuestId,
            serviceAmount: roomSvc,
            extraServiceAmount: extraSvc,
            roomChargeAmount: roomChargeTotal,
            unpaidRoomChargeAmount: unpaidRoomChargeTotal,
            paidAmount: roomPaidAmount,
            checked: false,
            rawRoom: r
          })
        })
      }

      const masterBills = (b.master_service_bills && b.master_service_bills.length > 0)
        ? b.master_service_bills.filter(sb => !sb.RentalRoomId2 || String(sb.RentalRoomId2) === '0')
        : (b.service_bills ? b.service_bills.filter(sb => !sb.RentalRoomId2 || String(sb.RentalRoomId2) === '0') : [])

      const masterOnlyServices = masterBills
        .filter(bill => Number(bill.Edit) !== 1 && Number(bill.Status) !== 2 && !bill.PaymentID && !bill.PaymentId)
        .reduce((total, bill) => total + (Number(bill.Amount) || 0), 0)

      const sumUnpaidRoomCharges = roomItems.reduce((acc, rItem) => acc + (Number(rItem.unpaidRoomChargeAmount) || 0), 0)
      const masterServiceTotal = masterSend ? (masterOnlyServices + sumUnpaidRoomCharges) : masterOnlyServices

      const masterDepositTotal = (b.payments || [])
        .filter(payment => payment.pack2 === 'DPR' && Number(payment.edit_flag) === 0 && !payment.deleted_at && !payment.booking_room_id)
        .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)

      // Master Header: Chỉ tính cọc chung (không có booking_room_id)
      const masterPaidAmount = (b.payments || [])
        .filter(p => (!p.edit_flag || Number(p.edit_flag) === 0) && !p.deleted_at && !p.booking_room_id)
        .reduce((sum, p) => sum + Number(p.amount || 0), 0)

      formatted.push({
        id: `B${b.id}`,
        bookingId: b.id,
        code: code,
        name: mainGuestName, // Tên nhóm / Tên booking
        // Master Header đại diện cho Folio Master: Tổng dịch vụ = masterServiceTotal
        totalService: masterServiceTotal,
        paidAmount: masterPaidAmount,
        arrivalDate: b.arrival_date || '',
        departureDate: b.departure_date || '',
        isVirtual: Boolean(b.is_virtual || roomItems.some(room => room.isVirtual)),
        note: b.note || '',
        checked: false,
        roomItems: roomItems,
        rawBooking: b
      })
    })

    // Lưu toàn bộ danh sách cho ô Tìm kiếm Popup
    allBookingsList.value = formatted
    displayedBookingsList.value = filterHasBeenApplied.value
      ? formatted.filter(booking => matchesCheckoutFilter(booking, appliedCheckoutFilters.value))
      : formatted
  } catch (err) {
    console.error('Lỗi khi nạp danh sách booking cho Checkout:', err)
  } finally {
    isLoading.value = false
  }
}

const refreshCheckoutData = async () => {
  const currentBookingId = selectedBooking.value ? selectedBooking.value.bookingId : null
  const currentRoomId = selectedRoomItem.value ? selectedRoomItem.value.roomId : null
  const currentGuestId = selectedGuestId.value
  const currentGuestName = selectedGuest.value

  await loadCheckoutBookings()

  if (currentBookingId) {
    const freshB = allBookingsList.value.find(b => b.bookingId === currentBookingId)
    if (freshB) {
      // Cập nhật lại trong displayedBookingsList nếu nó đang chứa booking này
      const displayedIdx = displayedBookingsList.value.findIndex(b => b.bookingId === currentBookingId)
      if (displayedIdx !== -1) {
        displayedBookingsList.value[displayedIdx] = freshB
      } else {
        displayedBookingsList.value = [freshB]
      }
      selectedBooking.value = freshB
      if (currentRoomId) {
        const freshR = freshB.roomItems.find(r => r.roomId === currentRoomId)
        if (freshR) {
          selectedRoomItem.value = freshR
          roomNumber.value = freshR.roomNumber
          const freshGuest = freshR.allGuests.find(guest => (
            currentGuestId && String(guest.id) === String(currentGuestId)
          )) || freshR.allGuests.find(guest => guest.name === currentGuestName)
          selectedGuest.value = freshGuest?.name || currentGuestName || freshR.guestName
          selectedGuestId.value = freshGuest?.id || currentGuestId || freshR.primaryGuestId
        }
      }
    }
  }
}

const handleServiceAdded = async (data) => {
  showAddServiceModal.value = false
  showHousekeepingServiceModal.value = false
  selectedPaymentIds.value = []

  await refreshCheckoutData()

  uiStore.showToast('Đã thêm dịch vụ thành công!', 'success')
}

const handlePrepaymentSuccess = async () => {
  showPrepaymentModal.value = false
  await handleServiceAdded()
}

const toggleBookingCheck = (b) => {
  if (b.roomItems) {
    b.roomItems.forEach(r => {
      r.checked = b.checked
    })
  }
}

const servicesList = computed(() => {
  if (!selectedBooking.value) return []

  const services = []

  const withServiceBillTime = (room, service) => {
    const bills = room?.service_bills || room?.serviceBills || []
    const bill = bills.find(candidate => String(candidate.Ma || candidate.id) === String(service.service_bill_id))
    return bill ? { ...service, openTime: service.open_time || service.openTime || bill.OpenTime || bill.CreatedHour } : service
  }

  const findLinkedBill = (s, roomNo = '', roomId = '') => {
    if (!selectedBooking.value?.rawBooking) return null
    const rawB = selectedBooking.value.rawBooking
    const roomBills = selectedRoomItem.value?.rawRoom?.service_bills || selectedRoomItem.value?.rawRoom?.serviceBills || []
    const allBills = [
      ...(rawB.master_service_bills || []),
      ...(rawB.service_bills || []),
      ...roomBills
    ]

    if (s.service_bill_id || s.serviceBillId) {
      const targetId = String(s.service_bill_id || s.serviceBillId)
      const found = allBills.find(sb => String(sb.Ma) === targetId)
      if (found) return found
    }

    const codeVal = String(s.service_code || s.serviceCode || '').toUpperCase()
    const folioVal = String(s.folio || 1)
    const roomStr = String(roomNo || s.room_number || '').trim()

    let found = allBills.find(sb => {
      const sbFolio = String(sb.Folio || 1)
      const sbCode = String(sb.ServiceId || '').toUpperCase()
      const codeMatches = (codeVal === 'RM' && (sbCode === 'RM' || sbCode === 'RMS')) ||
                          (codeVal === sbCode) ||
                          (s.department && sb.DepartmentId && String(s.department).toUpperCase() === String(sb.DepartmentId).toUpperCase())
      const folioMatches = sbFolio === folioVal || String(sb.Folio) === '3' || String(sb.Folio) === String(s.folio)
      const sbRoom = String(sb.RentalRoomId2 || sb.RentalRoomId1 || '')
      const roomMatches = (roomId && sbRoom === String(roomId)) ||
                          (roomStr && (String(sb.DescriptionServive || '').includes(roomStr) || sbRoom === roomStr))
      return codeMatches && folioMatches && roomMatches
    })

    if (found) return found

    return allBills.find(sb => {
      const sbFolio = String(sb.Folio || 1)
      const sbCode = String(sb.ServiceId || '').toUpperCase()
      const codeMatches = (codeVal === 'RM' && (sbCode === 'RM' || sbCode === 'RMS')) ||
                          (codeVal === sbCode) ||
                          (s.department && sb.DepartmentId && String(s.department).toUpperCase() === String(sb.DepartmentId).toUpperCase())
      const folioMatches = sbFolio === folioVal || String(sb.Folio) === '3' || String(sb.Folio) === String(s.folio)
      return codeMatches && folioMatches
    })
  }

  const processServiceItem = (s, idx, defaultDesc, roomNo = '') => {
    const rateVal = Number(s.rate) || Number(s.price) || Number(s.amount) || 0
    const qtyVal = Number(s.quantity) || Number(s.qty) || 1
    const totalVal = Number(s.total_amount) || (rateVal * qtyVal)
    const codeVal = s.service_code || (s.hotel_service && s.hotel_service.code) || (s.service_name === 'Tiền phòng' ? 'RM' : 'DV')

    let descVal = s.note || s.description || defaultDesc
    descVal = String(descVal).replace(/^Post bill\s+/i, '')
    if (codeVal === 'RM' || s.service_name === 'Tiền phòng') {
      const transferTrail = descVal.match(/\([^()]+=>[^()]+\)\s*$/)?.[0] || ''
      descVal = [`Dịch vụ phòng nghỉ ${roomNo || s.room_number || ''}`.trim(), transferTrail].filter(Boolean).join(' ')
    }

    const linkedBill = findLinkedBill(s, roomNo, s.booking_room_id || s.roomId)
    const rawPayCode = s.payment_id || s.payment_code || s.PaymentID || s.PaymentId || s.service_bill?.PaymentID || s.service_bill?.PaymentId || s.serviceBill?.PaymentID || s.serviceBill?.PaymentId || linkedBill?.PaymentID || linkedBill?.PaymentId || linkedBill?.payment_id || ''
    const isItemPaid = Number(s.status || linkedBill?.Status || 1) === 2 || Boolean(rawPayCode)
    const statusVal = isItemPaid ? 2 : Number(s.status || linkedBill?.Status || 1)
    const payCode = isItemPaid ? rawPayCode : ''
    const invCode = s.invoice_code || s.service_bill?.InvoiceId || s.serviceBill?.InvoiceId || s.service_bill?.InvoiceID || s.serviceBill?.InvoiceID || linkedBill?.InvoiceId || linkedBill?.invoice_id || linkedBill?.InvoiceID || ''
    const effectiveFolio = linkedBill?.Folio ? Number(linkedBill.Folio) : Number(s.folio || 1)

    return {
      id: s.id || `S${idx}`,
      serviceDate: s.service_date || s.created_at || null,
      createdAt: s.created_at || null,
      dateTime: formatServiceDateTime(s.service_date, s.created_at, s.open_time || s.openTime || s.service_bill?.OpenTime || s.serviceBill?.OpenTime || linkedBill?.OpenTime),
      serviceCode: codeVal,
      serviceBillId: s.service_bill_id || linkedBill?.Ma || null,
      serviceName: s.service_name || s.name || (s.hotel_service && s.hotel_service.name) || 'Dịch vụ buồng phòng',
      description: descVal,
      department: s.department || 'FO',
      amount: rateVal,
      quantity: qtyVal,
      totalAmount: totalVal,
      unit: s.unit || (codeVal === 'RM' ? 'Đêm' : 'Cái'),
      status: statusVal,
      isPaid: isItemPaid,
      paymentCode: payCode ? String(payCode) : '',
      folio: effectiveFolio,
      tax: Number(s.tax) || 0,
      serviceCharge: Number(s.service_charge) || 0,
      invoiceCode: invCode ? String(invCode) : '',
      vatNo: s.vat_no || '',
      accounting: s.accounting || 'Đã ghi',
      userName: s.created_by || s.user_name || 'Admin',
      guestId: (s.guest_id || s.guestId || s.CustomerId1 || s.customerId1 || s.customer_id_1) ? String(s.guest_id || s.guestId || s.CustomerId1 || s.customerId1 || s.customer_id_1) : null,
    }
  }

  const processServiceBillRecord = (sb, idx) => {
    const qtyVal = Number(sb.Quantity) || 1
    const totalVal = Number(sb.Amount) || 0
    const rateVal = qtyVal > 0 ? totalVal / qtyVal : totalVal
    const codeVal = sb.ServiceId || 'DV'
    const descVal = sb.DescriptionServive || sb.ServiceId || 'Dịch vụ FO'
    const rawPayCode = sb.PaymentID || sb.PaymentId || sb.payment_id || sb.payment_code || ''
    const isBillPaid = Number(sb.Status) === 2 || Boolean(rawPayCode)
    const payCode = isBillPaid ? rawPayCode : ''
    const invCode = sb.InvoiceId || sb.invoice_id || sb.InvoiceID || ''

    return {
      id: `SB-${sb.Ma || idx}`,
      serviceBillId: sb.Ma || null,
      serviceDate: sb.Date || sb.CreatedDate || null,
      createdAt: sb.CreatedDate || sb.created_at || null,
      dateTime: formatServiceDateTime(sb.Date || sb.CreatedDate, sb.CreatedDate, sb.OpenTime || sb.CreatedHour),
      serviceCode: codeVal,
      serviceName: descVal,
      description: descVal,
      department: sb.DepartmentId || 'FO',
      amount: rateVal,
      quantity: qtyVal,
      totalAmount: totalVal,
      unit: 'Lần',
      status: isBillPaid ? 2 : Number(sb.Status || 1),
      isPaid: isBillPaid,
      paymentCode: payCode ? String(payCode) : '',
      folio: Number(sb.Folio || 1),
      tax: Number(sb.Tax) || 0,
      serviceCharge: Number(sb.ServiceCharge) || 0,
      invoiceCode: invCode ? String(invCode) : '',
      vatNo: '',
      accounting: 'Đã ghi',
      userName: sb.CreatedUser || sb.Username || 'Admin'
    }
  }

  // Nếu chọn phòng lẻ: chỉ hiển thị dịch vụ của đúng phòng lẻ đó
  if (selectedRoomItem.value) {
    const room = selectedRoomItem.value
    const rawB = selectedBooking.value?.rawBooking
    const masterSend = isMasterRoomRateEnabled(selectedBooking.value)
    const guestId = selectedGuestId.value || room.primaryGuestId
    const processedBillIds = new Set()

    const isPrimary = String(guestId) === String(room.primaryGuestId)

    ;(room.rawRoom?.services || []).forEach((service, idx) => {
      const roomCharge = isRoomCharge(service)
      const linkedBill = findLinkedBill(service, room.roomNumber, room.roomId)
      const isPaid = Number(service.status || linkedBill?.Status || 1) === 2 || Boolean(service.payment_id || service.payment_code || linkedBill?.PaymentID || linkedBill?.PaymentId)
      const belongsToGuest = String(service.guest_id || room.primaryGuestId) === String(guestId)
      const shouldSendToMaster = masterSend && roomCharge && !isPaid

      if (!shouldSendToMaster && belongsToGuest) {
        if (service.service_bill_id) processedBillIds.add(String(service.service_bill_id))
        services.push(processServiceItem(withServiceBillTime(room.rawRoom, service), `${room.id}-${idx}`, `Phòng ${room.roomNumber}`, room.roomNumber))
      }
    })

    // Lấy thêm các bill ServiceBill thuộc về phòng này
    if (rawB) {
      const allBills = rawB.master_service_bills || rawB.service_bills || []
      allBills.forEach((sb, idx) => {
        if (Number(sb.Edit) === 1) return
        if (sb.ServiceId !== 'RM' && sb.ServiceId !== 'RMS') return
        if (sb.Ma && processedBillIds.has(String(sb.Ma))) return

        const isPaid = Number(sb.Status) === 2 || Boolean(sb.PaymentID || sb.PaymentId)
        const shouldSendToMaster = masterSend && !isPaid

        const isCurrentRoomOwner = String(sb.RentalRoomId2) === String(room.roomId)
        const billGuestId = sb.CustomerId2 || sb.CustomerId1
        const belongsToSelectedGuest = billGuestId
          ? String(billGuestId) === String(guestId)
          : isPrimary

        if (!shouldSendToMaster && isCurrentRoomOwner && belongsToSelectedGuest) {
          services.push(processServiceBillRecord(sb, `room-sb-${idx}`))
        }
      })
    }

    return services
  }

  // Khi chọn Phiếu Tổng (GAL1 / Master Booking): chỉ gom các dịch vụ/tiền phòng chưa thanh toán chuyển lên phiếu tổng
  if (selectedBooking.value) {
    const rawB = selectedBooking.value.rawBooking
    const masterSend = rawB?.is_master_room_rate !== undefined ? Boolean(rawB.is_master_room_rate) : true

    // 1. Dịch vụ post trực tiếp hoặc tiền phòng CHƯA THANH TOÁN gửi về Master Booking Header
    const allBillsSource = (rawB?.master_service_bills && rawB.master_service_bills.length > 0)
      ? rawB.master_service_bills
      : (rawB?.service_bills || [])

    const masterBills = allBillsSource.filter(sb => {
      if (Number(sb.Edit) === 1) return false
      if (!sb.RentalRoomId2 || String(sb.RentalRoomId2) === '0') return true
      const isPaid = Number(sb.Status) === 2 || Boolean(sb.PaymentID || sb.PaymentId)
      if (masterSend && (sb.ServiceId === 'RM' || sb.ServiceId === 'RMS') && !isPaid) return true
      return false
    })

    const masterBillIds = new Set(masterBills.map(sb => String(sb.Ma)))

    masterBills.forEach((sb, idx) => {
      services.push(processServiceBillRecord(sb, `master-${idx}`))
    })

    if (isMasterRoomRateEnabled(selectedBooking.value) && selectedBooking.value.roomItems) {
      selectedBooking.value.roomItems.forEach(rItem => {
        const rawR = rItem.rawRoom
        const roomNo = rItem.roomNumber
        if (rawR && rawR.services && Array.isArray(rawR.services)) {
          rawR.services.forEach((s, idx) => {
            if (!isRoomCharge(s)) return
            const linkedBill = findLinkedBill(s, roomNo, rItem.roomId)
            const isPaid = Number(s.status || linkedBill?.Status || 1) === 2 || Boolean(s.payment_id || s.payment_code || linkedBill?.PaymentID || linkedBill?.PaymentId)
            if (isPaid) return
            if (s.service_bill_id && masterBillIds.has(String(s.service_bill_id))) return
            services.push(processServiceItem(withServiceBillTime(rawR, s), `${rItem.id}-${idx}`, `Phòng ${roomNo}`, roomNo))
          })
        }
      })
    }
  }

  return services
})

const serviceGroupMeta = {
  RM: { code: 'RM', name: 'Tiền phòng' },
  MINIBAR: { code: 'MB', name: 'Minibar/Phí Minibar' },
  GIATUI: { code: 'LA', name: 'Laundry/Giặt ủi' },
  DENGBU: { code: 'BR', name: 'Broken/Phí hư hỏng' },
}

const getServiceGroup = (service) => {
  const serviceCode = String(service.serviceCode || '').toUpperCase()
  const prefix = serviceCode.split('_')[0]
  if (serviceCode === 'RM' || service.serviceName === 'Tiền phòng') return { key: 'RM', ...serviceGroupMeta.RM }
  const matchedKey = Object.keys(serviceGroupMeta).find(key => prefix === key)
  if (matchedKey) return { key: matchedKey, ...serviceGroupMeta[matchedKey] }
  return { key: prefix || serviceCode || 'DV', code: prefix || serviceCode || 'DV', name: service.serviceName || 'Dịch vụ khác' }
}

const visibleServices = computed(() => {
  if (activeFolioTab.value === 'A') return servicesList.value
  return servicesList.value.filter(service => String(service.folio) === activeFolioTab.value)
})

const folioPaidTotal = (folio) => {
  const validPayments = paymentsList.value.filter(payment => (
    Number(payment.editFlag) === 0 && !payment.isDeleted
  ))
  if (String(folio) === 'A') {
    return validPayments.reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
  }
  return validPayments
    .filter(payment => String(payment.folio) === String(folio))
    .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
}

const folioTotal = (folio) => {
  if (String(folio) === 'A') return [1, 2, 3].reduce((total, currentFolio) => total + folioTotal(currentFolio), 0)
  const serviceTotal = servicesList.value
    .filter(service => String(service.folio) === String(folio))
    .reduce((total, service) => total + (Number(service.totalAmount) || 0), 0)
  const paidTotal = folioPaidTotal(folio)
  return serviceTotal - paidTotal
}

const serviceGroups = computed(() => {
  const groups = new Map()
  visibleServices.value.forEach(service => {
    const meta = getServiceGroup(service)
    // Mỗi bill ServiceBill hoặc dịch vụ lẻ có định danh duy nhất (serviceBillId / id)
    // để mỗi bill đêm phòng đứng riêng 1 dòng độc lập và hiển thị chuẩn xác ngày/giờ tương ứng.
    const sourceKey = service.serviceBillId ? `sb-${service.serviceBillId}` : `svc-${service.id || service.createdAt}`
    const key = [meta.key, sourceKey, service.folio || 'A', service.department || 'FO'].join('|')
    if (!groups.has(key)) {
      groups.set(key, { id: key, ...meta, name: service.description || meta.name, dateTime: service.dateTime, department: service.department, folio: service.folio || 'A', paymentCode: service.paymentCode || '', invoiceCode: service.invoiceCode || '', totalAmount: 0, quantity: 0, tax: 0, serviceCharge: 0, items: [] })
    }
    const group = groups.get(key)
    group.items.push(service)
    if (!group.paymentCode && service.paymentCode) {
      group.paymentCode = service.paymentCode
    }
    if (!group.invoiceCode && service.invoiceCode) {
      group.invoiceCode = service.invoiceCode
    }
    group.totalAmount += Number(service.totalAmount) || 0
    group.quantity += Number(service.quantity) || 0
    group.tax += Number(service.tax) || 0
    group.serviceCharge += Number(service.serviceCharge) || 0
  })
  return Array.from(groups.values())
})

const openServiceInvoice = (group) => { selectedServiceGroup.value = group }
const closeServiceInvoice = () => { selectedServiceGroup.value = null }
const formatInvoiceProductName = (name) => String(name || '').replace(/^\[[^\]]+\]\s*/, '')

const isServiceGroupSelected = (group) => group.items.every(item => selectedServiceIds.value.includes(Number(item.id)))

const toggleServiceGroupSelection = (group, checked) => {
  const ids = group.items.map(item => Number(item.id)).filter(id => Number.isInteger(id) && id > 0)
  selectedServiceIds.value = checked
    ? [...new Set([...selectedServiceIds.value, ...ids])]
    : selectedServiceIds.value.filter(id => !ids.includes(id))
  if (checked) selectedPaymentIds.value = []
}

const serviceSelectionIds = computed(() => serviceGroups.value
  .flatMap(group => group.items)
  .map(item => Number(item.id))
  .filter(id => Number.isInteger(id) && id > 0))
const areAllServicesSelected = computed(() => (
  serviceSelectionIds.value.length > 0
  && serviceSelectionIds.value.every(id => selectedServiceIds.value.includes(id))
))
const toggleAllServiceSelection = (checked) => {
  selectedServiceIds.value = checked ? [...new Set(serviceSelectionIds.value)] : []
  if (checked) selectedPaymentIds.value = []
}

const canTransferServiceGroup = (group) => {
  if (!group || !group.items || group.items.length === 0) return false
  return group.items.every(item => !item.isPaid && Number(item.status) !== 2)
}

const selectedServiceItems = computed(() => servicesList.value.filter(service => selectedServiceIds.value.includes(Number(service.id))))
const selectedServiceGroups = computed(() => serviceGroups.value.filter(group => isServiceGroupSelected(group)))
const canTransferSelectedServices = computed(() => Boolean(selectedRoomItem.value) && selectedServiceItems.value.length > 0)
const canSplitSelectedServices = computed(() => {
  if (!canTransferSelectedServices.value || selectedServiceItems.value.some(service => service.serviceCode === 'RM')) return false
  const billIds = selectedServiceItems.value.map(service => service.serviceBillId).filter(Boolean)
  return billIds.length === selectedServiceItems.value.length && new Set(billIds).size === 1
})
const selectedPaymentItems = computed(() => paymentsList.value.filter(payment => selectedPaymentIds.value.includes(Number(payment.id))))
const canSplitSelectedDeposit = computed(() => selectedPaymentItems.value.length === 1 && canTransferPayment(selectedPaymentItems.value[0]))
const canTransferSelectedDeposit = computed(() => selectedPaymentItems.value.length > 0 && selectedPaymentItems.value.every(canTransferPayment))
const hasSelectedDeposit = computed(() => selectedPaymentItems.value.length > 0)
const canCancelSelectedServices = computed(() => (
  Boolean(selectedRoomItem.value)
  && selectedServiceItems.value.length > 0
  && selectedServiceItems.value.every(service => service.serviceBillId)
))
const selectedServicesTotal = computed(() => selectedServiceItems.value.reduce((sum, service) => sum + (Number(service.totalAmount) || 0), 0))
const toTransferPreviewService = (service) => {
  const rate = Number(service.rate ?? service.price ?? service.amount) || 0
  const quantity = Number(service.quantity ?? service.qty) || 1
  return {
    id: service.id,
    dateTime: formatServiceDateTime(service.service_date, service.created_at),
    department: service.department || 'FO',
    serviceCode: service.service_code || 'DV',
    description: String(service.note || service.description || '').replace(/^Post bill\s+/i, ''),
    serviceName: service.service_name || service.name || 'Dịch vụ',
    totalAmount: Number(service.total_amount) || (rate * quantity),
    unit: service.unit || 'VND',
    folio: service.folio || 1,
    tax: service.tax || 0,
    serviceCharge: service.service_charge || 0,
    userName: service.created_by || service.user_name || ''
  }
}

const isMasterRoomRateEnabled = (booking) => {
  const value = booking.rawBooking?.is_master_room_rate
  return value === undefined || value === true || value === 1 || value === '1'
}

const isRoomCharge = (service) => {
  const code = String(service.service_code || service.serviceCode || service.ServiceId || '').toUpperCase()
  const name = String(service.service_name || service.serviceName || service.DescriptionServive || '')
  return code === 'RM' || code === 'RMS' || name.includes('Tiền phòng')
}

const groupTransferPreviewServices = (services) => {
  const groups = new Map()
  services.forEach((service, index) => {
    const preview = toTransferPreviewService(service)
    const meta = getServiceGroup({
      serviceCode: preview.serviceCode,
      serviceName: preview.serviceName
    })
    const key = service.service_bill_id || [meta.key, service.created_at || service.id || index, preview.folio, preview.department].join('|')
    if (!groups.has(key)) {
      groups.set(key, {
        id: `preview-${key}`,
        dateTime: preview.dateTime,
        department: preview.department,
        serviceCode: meta.code,
        serviceName: preview.description || preview.serviceName,
        totalAmount: 0,
        unit: preview.unit,
        folio: preview.folio,
        tax: 0,
        serviceCharge: 0,
        userName: preview.userName,
        itemCount: 0
      })
    }
    const group = groups.get(key)
    group.totalAmount += preview.totalAmount
    group.tax += preview.tax
    group.serviceCharge += preview.serviceCharge
    group.itemCount += 1
  })
  return Array.from(groups.values()).map(group => ({
    ...group,
    serviceName: group.itemCount > 1 ? `${group.serviceName} (${group.itemCount})` : group.serviceName
  }))
}

const roomTransferPreviewServices = (booking, room, targetGuestId = null) => {
  const masterSend = isMasterRoomRateEnabled(booking)
  const guestId = targetGuestId || room.primaryGuestId
  const isPrimary = String(guestId) === String(room.primaryGuestId)

  const roomServices = (room.rawRoom?.services || []).filter(service => {
    const roomCharge = isRoomCharge(service)
    const belongsToGuest = service.guest_id
      ? String(service.guest_id) === String(guestId)
      : isPrimary
    return (!masterSend || !roomCharge) && belongsToGuest
  })
  return groupTransferPreviewServices(roomServices)
}

const guestRoomServiceAmount = (booking, room, guestId) => {
  const sendRoomRateToMaster = isMasterRoomRateEnabled(booking)
  const targetGuestId = guestId || room.primaryGuestId
  const isPrimary = String(targetGuestId) === String(room.primaryGuestId)

  let total = 0
  const processedBillIds = new Set()

  ;(room.rawRoom?.services || []).forEach(service => {
    const roomCharge = isRoomCharge(service)
    const belongsToGuest = service.guest_id
      ? String(service.guest_id) === String(targetGuestId)
      : isPrimary
    const isPaid = Number(service.status) === 2 || Boolean(service.payment_id || service.payment_code)
    // Chỉ chuyển tiền phòng chưa thanh toán lên Master; tiền phòng đã thanh toán
    // vẫn thuộc tổng dịch vụ của phòng/khách.
    const shouldSendToMaster = sendRoomRateToMaster && roomCharge && !isPaid
    if (!shouldSendToMaster && belongsToGuest) {
      if (service.service_bill_id) processedBillIds.add(String(service.service_bill_id))
      total += Number(service.total_amount) || (Number(service.quantity || 1) * Number(service.rate || service.price || service.amount || 0))
    }
  })

  const allBookingBills = booking.rawBooking?.master_service_bills || booking.rawBooking?.service_bills || []
  allBookingBills.forEach(sb => {
    if (Number(sb.Edit) === 1) return
    const roomCharge = isRoomCharge(sb)
    const isPaid = Number(sb.Status) === 2 || Boolean(sb.PaymentID || sb.PaymentId || sb.payment_id || sb.payment_code)
    if (sendRoomRateToMaster && roomCharge && !isPaid) return
    if (sb.Ma && processedBillIds.has(String(sb.Ma))) return

    const isCurrentRoomOwner = String(sb.RentalRoomId2) === String(room.roomId)
    const isOriginalRoomOwner = !sb.RentalRoomId2 && String(sb.RentalRoomId1) === String(room.roomId)
    if (isCurrentRoomOwner || isOriginalRoomOwner) {
      const billGuestId = sb.CustomerId2 || sb.CustomerId1
      const belongsToGuest = billGuestId ? String(billGuestId) === String(targetGuestId) : isPrimary
      if (belongsToGuest) {
        total += Number(sb.Amount) || 0
      }
    }
  })

  return total
}

const guestRoomPaidAmount = (booking, room, guestId) => {
  const targetGuestId = guestId || room.primaryGuestId
  const isPrimary = String(targetGuestId) === String(room.primaryGuestId)
  return (booking.rawBooking?.payments || [])
    .filter(payment => (
      (!payment.edit_flag || Number(payment.edit_flag) === 0)
      && !payment.deleted_at
      && String(payment.booking_room_id) === String(room.roomId)
      && (payment.guest_id || payment.customer_id
        ? (String(payment.guest_id) === String(targetGuestId) || String(payment.customer_id) === String(targetGuestId))
        : isPrimary)
    ))
    .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
}

const getGuestsToDisplay = (b, r) => {
  if (!r || !r.allGuests || r.allGuests.length === 0) return []
  if (showAllGuestsInRoom.value) return r.allGuests

  const primaryGuest = r.allGuests[0]
  const list = [primaryGuest]

  const rawR = r.rawRoom
  const rawB = b.rawBooking

  r.allGuests.slice(1).forEach(g => {
    if (!g.id) return
    const hasService = (rawR?.services || []).some(s => String(s.guest_id) === String(g.id))
    const hasPayment = (rawB?.payments || []).some(p => (
      (!p.edit_flag || Number(p.edit_flag) === 0)
      && !p.deleted_at
      && String(p.booking_room_id) === String(r.roomId)
      && (String(p.guest_id) === String(g.id) || String(p.customer_id) === String(g.id))
    ))
    if (hasService || hasPayment) {
      list.push(g)
    }
  })

  return list
}

const masterTransferPreviewServices = (booking) => {
  if (!isMasterRoomRateEnabled(booking)) return []
  return groupTransferPreviewServices(booking.roomItems.flatMap(room => (
    (room.rawRoom?.services || []).filter(isRoomCharge)
  )))
}

const transferPreviewPayments = (booking, room = null, targetGuestId = null) => {
  const targetId = targetGuestId || room?.primaryGuestId
  const isPrimary = String(targetId) === String(room?.primaryGuestId)

  return (booking.rawBooking?.payments || [])
    .filter(payment => {
      if (payment.pack2 !== 'DPR' || Number(payment.edit_flag) !== 0 || payment.deleted_at) return false

      if (room) {
        if (String(payment.booking_room_id) !== String(room.roomId)) return false
        return payment.guest_id || payment.customer_id
          ? (String(payment.guest_id) === String(targetId) || String(payment.customer_id) === String(targetId))
          : isPrimary
      }

      return !payment.booking_room_id
    })
    .map((payment, index) => ({
      id: payment.id || `payment-${index}`,
      dateTime: formatServiceDateTime(payment.date || payment.created_at, payment.created_at, payment.open_time || payment.openTime),
      department: payment.department_id || '',
      description: payment.description || 'Thanh toán đặt cọc / Tiền phòng',
      paymentMethod: payment.payment_method_id || '',
      amount: Number(payment.amount) || 0,
      unit: payment.currency || 'VND',
      folio: Number(payment.folio_id) || 1,
      paymentCode: payment.payment_id || '',
      userName: payment.user_name || payment.created_by || 'Admin'
    }))
}

const isTransferEligibleRoom = (room) => [0, 1].includes(Number(room.rawRoom?.status))
const isTransferEligibleBooking = (booking) => [0, 1].includes(Number(booking.rawBooking?.status))

const transferDestinations = computed(() => allBookingsList.value.filter(isTransferEligibleBooking).flatMap(booking => {
  const roomDestinations = booking.roomItems.filter(isTransferEligibleRoom).flatMap(room => {
    const guests = room.allGuests && room.allGuests.length > 0
      ? room.allGuests
      : [{ id: room.primaryGuestId || null, name: room.guestName }]

    return guests.map(g => {
      const gId = g.id || room.primaryGuestId || null
      return {
        key: `room-${room.roomId}-guest-${gId || 'primary'}`,
        bookingId: booking.bookingId,
        roomId: room.roomId,
        guestId: gId,
        kind: 'room',
        bookingCode: booking.code,
        bookingName: booking.name,
        roomNumber: room.roomNumber,
        guestName: g.name,
        label: `Phòng ${room.roomNumber} - ${g.name} (${booking.code})`,
        services: roomTransferPreviewServices(booking, room, gId),
        payments: transferPreviewPayments(booking, room, gId)
      }
    })
  })
  return [
    {
      key: `booking-${booking.bookingId}`,
      bookingId: booking.bookingId,
      roomId: null,
      guestId: null,
      kind: 'booking',
      bookingCode: booking.code,
      bookingName: booking.name,
      label: `BK ${booking.code} - ${booking.name}`,
      services: masterTransferPreviewServices(booking),
      payments: transferPreviewPayments(booking)
    },
    ...roomDestinations
  ]
}))

const openSplitServiceModal = () => {
  if (canSplitSelectedServices.value) showSplitServiceModal.value = true
}

const openSplitAction = () => {
  if (canSplitSelectedDeposit.value) {
    showSplitDepositModal.value = true
    return
  }
  openSplitServiceModal()
}

const isPaymentSelected = payment => selectedPaymentIds.value.includes(Number(payment.id))
const togglePaymentSelection = (payment, checked) => {
  selectedPaymentIds.value = checked ? [Number(payment.id)] : []
  if (checked) selectedServiceIds.value = []
}
const paymentSelectionIds = computed(() => visiblePaymentsList.value
  .map(payment => Number(payment.id))
  .filter(id => Number.isInteger(id) && id > 0))
const areAllPaymentsSelected = computed(() => (
  paymentSelectionIds.value.length > 0
  && paymentSelectionIds.value.every(id => selectedPaymentIds.value.includes(id))
))
const canAdjustSelectedService = computed(() => Boolean(selectedRoomItem.value) && selectedServiceGroups.value.length === 1)
const canOpenCancelServiceModal = computed(() => canCancelSelectedServices.value || canAdjustSelectedService.value)
const toggleAllPaymentSelection = (checked) => {
  selectedPaymentIds.value = checked ? [...new Set(paymentSelectionIds.value)] : []
  if (checked) selectedServiceIds.value = []
}

const openCancelServiceModal = () => {
  if (canOpenCancelServiceModal.value) showCancelServiceModal.value = true
}

const openServiceAdjustment = () => {
  if (!canAdjustSelectedService.value) return
  const group = selectedServiceGroups.value[0]
  const item = group.items[0]
  showCancelServiceModal.value = false
  const targetBillId = item?.serviceBillId || group?.serviceBillId || (group?.id && String(group.id).startsWith('sb-') ? Number(String(group.id).replace('sb-', '')) : null)

  if (group.code === 'RM') {
    roomAdjustment.value = {
      serviceBillId: targetBillId,
      serviceDate: item.serviceDate,
      folio: item.folio || group.folio || 1,
      amount: item.totalAmount || group.totalAmount,
      description: item.description || group.name
    }
    showAddServiceModal.value = true
    return
  }

  housekeepingAdjustment.value = {
    serviceBillId: targetBillId,
    serviceDate: item.serviceDate,
    folio: item.folio || group.folio || 1,
    note: item.description || group.name,
    items: group.items
  }
  showHousekeepingServiceModal.value = true
}

const cancelSelectedServices = async (reason) => {
  if (!canCancelSelectedServices.value) return
  isServiceOperationLoading.value = true
  try {
    const serviceIds = selectedServiceItems.value.map(service => Number(service.id))
    const response = await cancelBookingRoomServices(selectedRoomItem.value.roomId, { service_ids: serviceIds, reason })
    showCancelServiceModal.value = false
    await refreshAfterServiceOperation()
    uiStore.showToast(response.data?.message || 'Đã xóa dịch vụ thành công!', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa dịch vụ.', 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}

const openTransferServiceModal = () => {
  if (canTransferSelectedServices.value) {
    transferServiceError.value = ''
    showTransferServiceModal.value = true
  }
}

const openTransferPaymentModal = () => {
  if (canTransferSelectedDeposit.value) {
    transferPaymentError.value = ''
    showTransferPaymentModal.value = true
  }
}

const openQuickTransferBillModal = async () => {
  if (!hasQuickTransferTarget.value) {
    uiStore.showToast('Vui lòng chọn phòng nhận dịch vụ.', 'warning')
    return
  }
  showQuickTransferBillModal.value = true
  quickTransferLoadingText.value = 'Đang tải danh sách dịch vụ...'
  isServiceOperationLoading.value = true
  try {
    const targetId = selectedRoomItem.value?.roomId || `master-${selectedBooking.value.bookingId}`
    const response = await fetchQuickTransferCandidates(targetId)
    quickTransferCandidates.value = response.data?.data || []
  } catch (error) {
    quickTransferCandidates.value = []
    uiStore.showToast(error.response?.data?.message || 'Không thể tải danh sách dịch vụ.', 'error')
  } finally {
    isServiceOperationLoading.value = false
    quickTransferLoadingText.value = ''
  }
}

const submitQuickTransferBills = async (billIds) => {
  if (!hasQuickTransferTarget.value || !billIds.length) return
  quickTransferLoadingText.value = 'Đang chuyển bill nhanh...'
  isServiceOperationLoading.value = true
  uiStore.showToast('Đang chuyển bill nhanh...', 'info', 1500)
  try {
    const targetId = selectedRoomItem.value?.roomId || `master-${selectedBooking.value.bookingId}`
    const payload = { bill_ids: billIds }
    if (selectedRoomItem.value && selectedGuestId.value) payload.target_guest_id = selectedGuestId.value
    const response = await quickTransferBookingRoomServices(targetId, payload)
    showQuickTransferBillModal.value = false
    await refreshAfterServiceOperation(1)
    uiStore.showToast(response.data?.message || 'Đã tập hợp dịch vụ thành công!', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tập hợp dịch vụ.', 'error')
  } finally {
    isServiceOperationLoading.value = false
    quickTransferLoadingText.value = ''
  }
}

const refreshAfterServiceOperation = async (folio = null) => {
  selectedServiceIds.value = []
  if (folio) activeFolioTab.value = String(folio)
  await handleServiceAdded()
}

const splitSelectedServices = async (payload) => {
  if (!canSplitSelectedServices.value) return
  isServiceOperationLoading.value = true
  try {
    await splitBookingRoomServicesFolio(selectedRoomItem.value.roomId, {
      service_ids: selectedServiceIds.value.map(Number),
      ...payload
    })
    showSplitServiceModal.value = false
    await refreshAfterServiceOperation(payload.folio)
    uiStore.showToast('Tách dịch vụ thành công!', 'success')
  } catch (error) {
    console.error('Không thể tách dịch vụ:', error)
    uiStore.showToast(error.response?.data?.message || 'Không thể tách dịch vụ.', 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}

const splitSelectedDeposit = async ({ amount, folio }) => {
  if (!canSplitSelectedDeposit.value) return
  const payment = selectedPaymentItems.value[0]
  const targetAmount = Number(amount)
  const sourceAmount = Number(((Number(payment.amount) || 0) - targetAmount).toFixed(2))
  if (!(targetAmount > 0) || !(sourceAmount > 0)) return
  isServiceOperationLoading.value = true
  try {
    const response = await splitPayment(payment.id, {
      amounts: [sourceAmount, targetAmount],
      folio_id: Number(folio)
    })
    showSplitDepositModal.value = false
    selectedPaymentIds.value = []
    activeFolioTab.value = String(folio)
    await handleServiceAdded()
    uiStore.showToast(response.data?.message || 'Đã tách cọc thành công!', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tách cọc.', 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}

const transferSelectedServices = async (destination) => {
  if (!canTransferSelectedServices.value) return
  isServiceOperationLoading.value = true
  try {
    const response = await transferBookingRoomServicesFolio(selectedRoomItem.value.roomId, {
      service_ids: selectedServiceIds.value.map(Number),
      target_booking_id: destination.bookingId,
      target_room_id: destination.roomId,
      target_guest_id: destination.guestId
    })
    showTransferServiceModal.value = false
    await refreshAfterServiceOperation()
    uiStore.showToast(response.data?.message || 'Chuyển dịch vụ thành công!', 'success')
  } catch (error) {
    transferServiceError.value = error.response?.data?.message || 'Không thể chuyển dịch vụ. Vui lòng kiểm tra lại bill và nơi nhận.'
    console.error('Không thể chuyển dịch vụ:', error)
    uiStore.showToast(transferServiceError.value, 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}

const transferSelectedPayment = async (destination) => {
  if (!canTransferSelectedDeposit.value) return
  isServiceOperationLoading.value = true
  try {
    const response = await transferPayments({
      payment_ids: selectedPaymentItems.value.map(payment => Number(payment.id)),
      target_booking_id: destination.bookingId,
      target_room_id: destination.roomId,
      target_guest_id: destination.guestId
    })
    showTransferPaymentModal.value = false
    selectedPaymentIds.value = []
    await handleServiceAdded()
    uiStore.showToast(response.data?.message || 'Chuyển cọc thành công!', 'success')
  } catch (error) {
    transferPaymentError.value = error.response?.data?.message || 'Không thể chuyển cọc. Vui lòng kiểm tra lại cọc và nơi nhận.'
    uiStore.showToast(transferPaymentError.value, 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}


const handleServiceDragStart = (group, event) => {
  if (!canTransferServiceGroup(group)) {
    event.preventDefault()
    return
  }
  draggedServiceGroup.value = group
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', group.id)
}

const handleServiceDragEnd = () => {
  draggedServiceGroup.value = null
  draggedPayment.value = null
  draggedOverFolio.value = null
}

const canTransferPayment = (payment) => !payment.paymentId && payment.status === 1 && payment.editFlag === 0

const handlePaymentDragStart = (payment, event) => {
  if (!canTransferPayment(payment)) {
    event.preventDefault()
    return
  }
  draggedPayment.value = payment
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(payment.id))
}

const handleFolioDrop = async (folio) => {
  const group = draggedServiceGroup.value
  const payment = draggedPayment.value
  const targetFolio = Number(folio)
  draggedOverFolio.value = null
  if (payment) {
    if (!canTransferPayment(payment) || Number(payment.folio) === targetFolio) {
      handleServiceDragEnd()
      return
    }
    isServiceOperationLoading.value = true
    try {
      const response = await transferPaymentFolio(payment.id, { folio_id: targetFolio })
      activeFolioTab.value = String(targetFolio)
      await handleServiceAdded()
      uiStore.showToast(response.data?.message || 'Đã chuyển cọc sang Folio mới.', 'success')
    } catch (error) {
      uiStore.showToast(error.response?.data?.message || 'Không thể chuyển Folio cọc.', 'error')
    } finally {
      isServiceOperationLoading.value = false
      handleServiceDragEnd()
    }
    return
  }

  if (!group || !canTransferServiceGroup(group) || Number(group.folio) === targetFolio) {
    handleServiceDragEnd()
    return
  }

  // Thu thập ID các bill chưa thanh toán được tick chọn + nhóm kéo thả
  const extractIds = (items) => {
    const ids = []
    items.forEach(item => {
      if (item.isPaid || Number(item.status) === 2) return
      if (item.id && Number.isInteger(Number(item.id)) && Number(item.id) > 0) {
        ids.push(Number(item.id))
      }
      if (item.serviceBillId && Number.isInteger(Number(item.serviceBillId)) && Number(item.serviceBillId) > 0) {
        ids.push(Number(item.serviceBillId))
      }
    })
    return ids
  }

  const selectedUnpaidIds = extractIds(selectedServiceItems.value)
  const draggedGroupIds = extractIds(group.items)

  const serviceIdsToTransfer = selectedUnpaidIds.length > 0
    ? [...new Set([...selectedUnpaidIds, ...draggedGroupIds])]
    : [...new Set(draggedGroupIds)]

  if (serviceIdsToTransfer.length === 0) {
    handleServiceDragEnd()
    return
  }

  isServiceOperationLoading.value = true
  try {
    const targetRoomId = selectedRoomItem.value?.roomId || selectedBooking.value?.bookingId
    await transferBookingRoomServicesFolio(targetRoomId, {
      service_ids: serviceIdsToTransfer,
      folio: targetFolio,
    })
    selectedServiceIds.value = []
    activeFolioTab.value = String(targetFolio)
    await handleServiceAdded()
    uiStore.showToast(`Đã chuyển ${serviceIdsToTransfer.length} dịch vụ sang Folio ${targetFolio}!`, 'success')
  } catch (error) {
    console.error('Không thể chuyển Folio dịch vụ:', error)
    uiStore.showToast(error.response?.data?.message || 'Không thể chuyển Folio dịch vụ.', 'error')
  } finally {
    isServiceOperationLoading.value = false
    handleServiceDragEnd()
  }
}

const totalServiceAmount = computed(() => {
  return visibleServices.value.reduce((acc, s) => acc + (s.totalAmount || (s.amount * s.quantity)), 0)
})

const paymentsList = computed(() => {
  if (!selectedBooking.value) return []

  const payments = []
  const rawB = selectedBooking.value.rawBooking
  const currentRoomId = selectedRoomItem.value?.roomId || selectedRoomItem.value?.rawRoom?.id || null

  if (rawB && rawB.payments && Array.isArray(rawB.payments) && rawB.payments.length > 0) {
    const room = selectedRoomItem.value
    const currentGuestId = selectedGuestId.value || room?.primaryGuestId
    const isPrimaryGuest = room ? (String(currentGuestId) === String(room.primaryGuestId)) : false

    const filteredPayments = rawB.payments.filter(p => {
      if (!p || p.deleted_at || (p.edit_flag !== undefined && Number(p.edit_flag) !== 0)) return false
      // Nếu chọn dòng Phiếu Tổng (Master Header): chỉ hiển thị cọc của Master (không có booking_room_id)
      if (!currentRoomId) return !p.booking_room_id

      // Chọn phòng lẻ: chỉ hiển thị cọc thuộc đúng phòng đó
      if (!p.booking_room_id || String(p.booking_room_id) !== String(currentRoomId)) return false

      const pGuestId = p.guest_id || p.customer_id || p.guestId || null
      // Cọc chung thuộc phòng lẻ đó -> luôn hiển thị cho phòng lẻ đó
      if (!pGuestId) return true

      // Cọc có chỉ định guest_id -> hiển thị khi trùng guest_id hoặc khi ở khách đại diện/chưa chọn guest_id
      if (currentGuestId && String(pGuestId) === String(currentGuestId)) return true
      if (!currentGuestId || isPrimaryGuest) return true

      return false
    })

    filteredPayments.forEach((p, idx) => {
      payments.push({
        id: p.id || `P${idx}`,
        dateTime: formatServiceDateTime(p.date || p.payment_date || p.created_at || new Date(), p.created_at, p.open_time || p.openTime),
        department: p.department_id || '',
        description: p.description || p.note || 'Thanh toán đặt cọc / Tiền phòng',
        paymentMethod: p.payment_method_id || '',
        amount: Number(p.amount) || 0,
        unit: p.currency || 'VND',
        folio: Number(p.folio_id) || 1,
        paymentCode: p.payment_id || '',
        paymentId: p.payment_id || null,
        status: Number(p.status),
        editFlag: Number(p.edit_flag),
        isDeleted: p.deleted_at ? 'Có' : '',
        vatNo: p.vat_no || '',
        accounting: p.accounting || 'Đã thu',
        userName: p.user_name || 'Admin'
      })
    })
  } else if (!selectedRoomItem.value && selectedBooking.value.paidAmount > 0) {
    payments.push({
      id: `P-fallback`,
      dateTime: formatDate(selectedBooking.value.arrivalDate),
      department: 'Lễ tân',
      description: 'Thanh toán đặt cọc phòng',
      paymentMethod: 'Chuyển khoản',
      amount: selectedBooking.value.paidAmount,
      unit: 'VND',
      folio: 1,
      paymentCode: '',
      paymentId: null,
      status: 1,
      editFlag: 0,
      isDeleted: '',
      vatNo: '',
      accounting: 'Đã thu',
      userName: 'Admin'
    })
  }

  return payments
})

const visiblePaymentsList = computed(() => activeFolioTab.value === 'A'
  ? paymentsList.value
  : paymentsList.value.filter(payment => String(payment.folio) === String(activeFolioTab.value))
)

const folioDepositTotal = (folio) => {
  const unusedPayments = paymentsList.value.filter(payment => (
    !payment.paymentCode && Number(payment.status) !== 2 && Number(payment.editFlag) === 0 && !payment.isDeleted
  ))
  if (String(folio) === 'A') {
    return unusedPayments.reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
  }
  return unusedPayments
    .filter(payment => String(payment.folio) === String(folio))
    .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)
}

const totalPaymentAmount = computed(() => {
  return visiblePaymentsList.value.reduce((acc, p) => acc + p.amount, 0)
})

const currentFolioUnpaidServices = computed(() => {
  return visibleServices.value.filter(s => !s.paymentCode && String(s.status) !== '2')
})

const currentFolioUnpaidServiceTotal = computed(() => {
  return currentFolioUnpaidServices.value.reduce((sum, s) => sum + (Number(s.totalAmount) || 0), 0)
})

const currentFolioDepositTotal = computed(() => {
  return folioDepositTotal(activeFolioTab.value)
})

const openPaymentModal = () => {
  if (!selectedBooking.value) {
    uiStore.showToast('Vui lòng chọn Booking hoặc phòng cần thanh toán.', 'warning')
    return
  }

  showPaymentModal.value = true
}

const handlePaymentSuccess = async () => {
  showPaymentModal.value = false
  await handleServiceAdded()
}

const openDeletePaymentModal = async () => {
  if (selectedPaymentItems.value.length === 0) {
    uiStore.showToast('Vui lòng chọn bản ghi thanh toán/cọc cần xóa.', 'warning')
    return
  }

  const payment = selectedPaymentItems.value[0]
  if (!payment || !payment.id || String(payment.id).startsWith('P-fallback')) {
    uiStore.showToast('Bản ghi thanh toán không hợp lệ.', 'warning')
    return
  }

  showDeletePaymentModal.value = true
}

const deleteSelectedPayment = async (reason) => {
  const payment = selectedPaymentItems.value[0]
  if (!payment?.id) return
  isServiceOperationLoading.value = true
  try {
    const res = await deleteBookingPayment(payment.id, { reason })
    showDeletePaymentModal.value = false
    selectedPaymentIds.value = []
    await handleServiceAdded()
    uiStore.showToast(res.data?.message || 'Đã xóa thanh toán thành công.', 'success')
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể xóa thanh toán.', 'error')
  } finally {
    isServiceOperationLoading.value = false
  }
}

const selectedRoomGuests = computed(() => selectedRoomItem.value?.allGuests || [])
const hasCurrentSelectedRoom = computed(() => {
  const roomId = selectedRoomItem.value?.roomId
  const bookingId = selectedBooking.value?.bookingId
  return Boolean(roomId && bookingId && displayedBookingsList.value.some(booking => (
    booking.bookingId === bookingId
    && booking.roomItems?.some(room => room.roomId === roomId)
  )))
})

const hasQuickTransferTarget = computed(() => {
  if (hasCurrentSelectedRoom.value) return true
  const bookingId = selectedBooking.value?.bookingId
  return Boolean(bookingId && !selectedRoomItem.value && displayedBookingsList.value.some(booking => booking.bookingId === bookingId))
})

const handlePanelGuestChange = () => {
  if (!selectedBooking.value || !selectedRoomItem.value) return

  const guest = selectedRoomGuests.value.find(item => String(item.id) === String(selectedGuestId.value))
  if (guest) {
    selectRoomItemRow(selectedBooking.value, selectedRoomItem.value, guest)
  }
}

const selectPanelGuest = (guest) => {
  selectedGuest.value = guest.name
  selectedGuestId.value = guest.id
  showPanelGuestDropdown.value = false
  handlePanelGuestChange()
}

const selectBookingHeader = (b) => {
  selectedBooking.value = b
  selectedRoomItem.value = null
  selectedServiceIds.value = []
  selectedPaymentIds.value = []
  activeFolioTab.value = 'A'
  noteText.value = b.note || ''
  roomNumber.value = ''
  selectedGuest.value = b.name
  selectedGuestId.value = null
  isNoPost.value = Boolean(b.rawBooking?.no_post)
}

const selectRoomItemRow = (b, r, specificGuest = null) => {
  selectedBooking.value = b
  selectedRoomItem.value = r
  selectedServiceIds.value = []
  selectedPaymentIds.value = []
  activeFolioTab.value = 'A'
  noteText.value = b.note || ''
  roomNumber.value = r.roomNumber
  const guest = specificGuest || r.allGuests[0]
  selectedGuest.value = guest?.name || r.guestName
  selectedGuestId.value = guest?.id || r.primaryGuestId || null
  isNoPost.value = Boolean(r.rawRoom?.no_post)
}

const handleNoPostChange = async (event) => {
  if (!selectedBooking.value || noPostSaving.value) return

  const noPost = event.target.checked
  noPostSaving.value = true

  try {
    if (selectedRoomItem.value) {
      await updateBookingRoomNoPost(selectedRoomItem.value.roomId, noPost)
      selectedRoomItem.value.rawRoom.no_post = noPost
      selectedRoomItem.value.no_post = noPost
    } else {
      await updateBookingNoPost(selectedBooking.value.bookingId, noPost)
      selectedBooking.value.rawBooking.no_post = noPost
      selectedBooking.value.roomItems.forEach(room => {
        room.rawRoom.no_post = noPost
        room.no_post = noPost
      })
    }

    isNoPost.value = noPost
    uiStore.showToast(noPost ? 'Đã bật No Post.' : 'Đã tắt No Post.', 'success')
  } catch (error) {
    const currentNoPost = selectedRoomItem.value
      ? Boolean(selectedRoomItem.value.rawRoom?.no_post)
      : Boolean(selectedBooking.value.rawBooking?.no_post)
    isNoPost.value = currentNoPost
    uiStore.showToast(error.response?.data?.message || 'Không thể cập nhật No Post.', 'error')
  } finally {
    noPostSaving.value = false
  }
}

const filteredSearchBookings = computed(() => {
  const q = String(searchQuery.value || '').trim().toLowerCase()
  const source = allBookingsList.value
  if (!q) return source
  return source.filter(b => {
    const matchCode = b.code && b.code.toLowerCase().includes(q)
    const matchName = b.name && b.name.toLowerCase().includes(q)
    const matchRoom = b.roomItems.some(r => {
      const matchNo = r.roomNumber && r.roomNumber.toLowerCase().includes(q)
      const matchGuests = r.allGuests && r.allGuests.some(g => String(g?.name || g || '').toLowerCase().includes(q))
      return matchNo || matchGuests
    })
    return matchCode || matchName || matchRoom
  })
})

const selectBookingFromSearch = (b, r = null, specificGuest = null) => {
  // Đổ dữ liệu booking được chọn vào Bảng 1 (Top-Left Table)
  displayedBookingsList.value = [b]

  if (r) {
    selectRoomItemRow(b, r, specificGuest)
  } else {
    selectBookingHeader(b)
  }
  searchQuery.value = b.code || (r ? r.roomNumber : b.name)
  showSearchDropdown.value = false
}

const openRegistrationFromCheckout = () => {
  if (!selectedBooking.value?.code) {
    uiStore.showToast('Vui lòng chọn đăng ký trước.', 'warning')
    return
  }
  router.push({ path: '/frontdesk', query: { tab: 'create-res', bookingCode: selectedBooking.value.code } })
}

const selectCheckoutBookingFromRoute = () => {
  const bookingCode = String(route.query.bookingCode || '').trim()
  if (!bookingCode) return
  const booking = allBookingsList.value.find(item => (
    String(item.code) === bookingCode || String(item.bookingId) === bookingCode
  ))
  if (booking) selectBookingFromSearch(booking)
}

const handleClickOutside = (e) => {
  if (searchContainerRef.value && !searchContainerRef.value.contains(e.target)) {
    showSearchDropdown.value = false
  }
  if (filterContainerRef.value && !filterContainerRef.value.contains(e.target)) {
    showRegisterFilterDropdown.value = false
  }
}

onMounted(async () => {
  await loadSystemDate()
  await loadCheckoutBookings()
  selectCheckoutBookingFromRoute()
  document.addEventListener('click', handleClickOutside)
  // Lắng nghe sự kiện realtime qua Laravel Echo
  if (echo) {
    echo.channel('pms-channel')
      .listen('.room.status.updated', () => {
        refreshCheckoutData()
      })
      .listen('.reservation.updated', () => {
        refreshCheckoutData()
      })
  }
})

watch(() => route.query.bookingCode, async () => {
  await loadCheckoutBookings()
  selectCheckoutBookingFromRoute()
})

watch(searchQuery, (newVal) => {
  const q = String(newVal || '').trim()
  if (!q) {
    displayedBookingsList.value = allBookingsList.value
    if (route.query.bookingCode) {
      router.replace({ query: { ...route.query, bookingCode: undefined } })
    }
  }
})

const clearSearch = () => {
  searchQuery.value = ''
  showSearchDropdown.value = false
}

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
    // Hủy lắng nghe sự kiện realtime qua Laravel Echo
  if (echo) {
    echo.channel('pms-channel').stopListening('.room.status.updated')
    echo.channel('pms-channel').stopListening('.reservation.updated')
  }
})
</script>

<template>
  <div class="checkout-shell flex h-[calc(100vh-48px)] bg-[#f1f5f9] text-xs text-slate-700 select-none overflow-hidden font-sans relative">
    <LoadingOverlay :show="isLoading || isServiceOperationLoading" />

    <!-- LEFTSIDE TOOLBAR (Cột nút chức năng dọc bên trái - Hỗ trợ Thu gọn/Mở rộng) -->
    <aside 
      :class="[
        isSidebarCollapsed ? 'w-12' : 'w-[170px]',
        'checkout-actions order-last bg-[#1e293b] border-l border-[#475569] flex flex-col justify-between py-2 px-2 transition-all duration-200 shrink-0 shadow-sm relative text-slate-100 overflow-x-hidden overflow-y-auto'
      ]"
    >
      <!-- Collapse / Expand Toggle Button -->
      <button 
        @click="toggleSidebar"
        class="absolute -left-3 top-2 bg-[#1e293b] border border-[#475569] rounded-full p-0.5 shadow hover:bg-slate-700 z-30 text-slate-200"
        :title="isSidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      >
        <ChevronRight v-if="isSidebarCollapsed" class="w-3.5 h-3.5" />
        <ChevronLeft v-else class="w-3.5 h-3.5" />
      </button>

      <div class="flex-1 overflow-y-auto overflow-x-hidden pt-3 checkout-action-menu flex flex-col gap-0">

        <!-- NHÓM: Dịch Vụ & Phí -->
        <div class="pb-2 mb-1 border-b border-[#334155]">
          <div v-if="!isSidebarCollapsed" class="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-[#94a3b8] whitespace-nowrap">Dịch Vụ & Phí</div>

          <!-- Thêm dịch vụ -->
          <button 
            @click="showAddServiceModal = true"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs"
            :title="isSidebarCollapsed ? 'Thêm dịch vụ' : ''"
          >
            <Plus class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Thêm Dịch Vụ</span>
          </button>

          <!-- Thêm dịch vụ BP -->
          <button 
            @click="openAddHousekeepingService"
            :disabled="!selectedRoomItem"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded transition-colors text-xs"
            :class="[selectedRoomItem ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'opacity-40 cursor-not-allowed text-[#64748b]']"
            :title="isSidebarCollapsed ? 'Thêm dịch vụ BP' : ''"
          >
            <PlusSquare class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Thêm DV Buồng Phòng</span>
          </button>

          <!-- Tách dịch vụ -->
          <button 
            @click="openSplitAction"
            :disabled="!(canSplitSelectedServices || canSplitSelectedDeposit)"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-xs transition-colors"
            :class="(canSplitSelectedServices || canSplitSelectedDeposit) ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'opacity-40 cursor-not-allowed text-[#64748b]'"
            :title="isSidebarCollapsed ? (hasSelectedDeposit ? 'Tách cọc' : 'Tách dịch vụ') : ''"
          >
            <Scissors class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">{{ hasSelectedDeposit ? 'Tách Dịch Vụ' : 'Tách Dịch Vụ' }}</span>
          </button>

          <!-- Chuyển dịch vụ / cọc -->
          <button
            @click="hasSelectedDeposit ? openTransferPaymentModal() : openTransferServiceModal()"
            :disabled="hasSelectedDeposit ? !canTransferSelectedDeposit : !canTransferSelectedServices"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-xs transition-colors"
            :class="(hasSelectedDeposit ? canTransferSelectedDeposit : canTransferSelectedServices) ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'opacity-40 cursor-not-allowed text-[#64748b]'"
            :title="isSidebarCollapsed ? (hasSelectedDeposit ? 'Chuyển cọc' : 'Chuyển dịch vụ') : ''"
          >
            <ArrowRightLeft class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">{{ hasSelectedDeposit ? 'Chuyển Dịch Vụ' : 'Chuyển Dịch Vụ' }}</span>
          </button>

          <!-- Tập hợp DV -->
          <button 
            @click="openQuickTransferBillModal"
            :disabled="!hasQuickTransferTarget || isServiceOperationLoading"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-xs transition-colors"
            :class="hasQuickTransferTarget && !isServiceOperationLoading ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'text-[#64748b] opacity-50 cursor-not-allowed'"
            :title="isSidebarCollapsed ? 'Tập hợp DV' : ''"
          >
            <Layers class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Tập Hợp Dịch Vụ</span>
          </button>

          <!-- Xóa dịch vụ -->
          <button 
            @click="openCancelServiceModal"
            :disabled="!canOpenCancelServiceModal || isServiceOperationLoading"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-xs transition-colors"
            :class="canOpenCancelServiceModal && !isServiceOperationLoading ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'text-[#64748b] cursor-not-allowed opacity-60'"
            :title="isSidebarCollapsed ? 'Xóa dịch vụ' : ''"
          >
            <Trash2 class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Xóa Dịch Vụ</span>
          </button>
        </div>

        <!-- NHÓM: Thanh Toán & Cọc -->
        <div class="pb-2 mb-1 border-b border-[#334155]">
          <div v-if="!isSidebarCollapsed" class="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-[#94a3b8] whitespace-nowrap">Thanh Toán & Cọc</div>

          <!-- Thanh toán trước -->
          <button 
            @click="showPrepaymentModal = true"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs cursor-pointer"
            :title="isSidebarCollapsed ? 'Thanh toán trước' : ''"
          >
            <CreditCard class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Thanh Toán Trước</span>
          </button>

          <!-- Thanh toán -->
          <button 
            @click="openPaymentModal"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs cursor-pointer"
            :title="isSidebarCollapsed ? 'Thanh toán' : ''"
          >
            <CreditCard class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Thanh Toán</span>
          </button>

          <!-- Xóa thanh toán -->
          <button 
            @click="openDeletePaymentModal"
            :disabled="selectedPaymentItems.length === 0 || isServiceOperationLoading"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-xs transition-colors"
            :class="selectedPaymentItems.length > 0 && !isServiceOperationLoading ? 'text-[#cbd5e1] hover:bg-[#334155] hover:text-white cursor-pointer' : 'text-[#64748b] cursor-not-allowed opacity-60'"
            :title="isSidebarCollapsed ? 'Xóa thanh toán' : ''"
          >
            <RotateCcw class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Xóa Thanh Toán</span>
          </button>
        </div>

        <!-- NHÓM: In & Hóa Đơn VAT -->
        <div class="pb-2 mb-1 border-b border-[#334155]">
          <div v-if="!isSidebarCollapsed" class="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-[#94a3b8] whitespace-nowrap">In & Hóa Đơn VAT</div>

          <!-- In hóa đơn với Dropdown Submenu -->
          <div class="relative">
            <button 
              @click="showInvoiceMenu = !showInvoiceMenu"
              class="w-full flex items-center justify-between px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs"
              :title="isSidebarCollapsed ? 'In hóa đơn' : ''"
            >
              <div class="flex items-center gap-1.5 truncate">
                <Printer class="w-3.5 h-3.5 text-white shrink-0" />
                <span v-if="!isSidebarCollapsed" class="truncate">In Hóa Đơn Folio</span>
              </div>
              <ChevronRight v-if="!isSidebarCollapsed" class="w-3 h-3 text-[#94a3b8] shrink-0" />
            </button>

            <!-- Dropdown Sub-menu Floating -->
            <div 
              v-if="showInvoiceMenu" 
              class="absolute left-full top-0 ml-1 w-36 bg-white border border-gray-300 rounded shadow-lg z-50 py-1 text-xs"
            >
              <button class="w-full text-left px-2.5 py-1.5 hover:bg-sky-50 text-gray-700">Hiện giá</button>
              <button class="w-full text-left px-2.5 py-1.5 hover:bg-sky-50 text-gray-700">Không hiện giá</button>
              <button class="w-full text-left px-2.5 py-1.5 hover:bg-sky-50 text-sky-600 font-semibold border-t border-gray-100">In Bill</button>
            </div>
          </div>

          <!-- In VAT -->
          <button 
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs"
            :title="isSidebarCollapsed ? 'In VAT' : ''"
          >
            <FileText class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">In VAT</span>
          </button>

          <!-- Hủy VAT -->
          <button 
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#f87171] hover:bg-[#334155] transition-colors text-xs"
            :title="isSidebarCollapsed ? 'Hủy VAT' : ''"
          >
            <FileX class="w-3.5 h-3.5 text-[#f87171] shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Hủy VAT</span>
          </button>
        </div>

        <!-- NHÓM: Tiện ích -->
        <div class="pb-1">
          <!-- Lọc -->
          <button 
            @click="showFilterServiceModal = true"
            class="w-full flex items-center gap-1.5 px-2 py-[5px] rounded text-[#cbd5e1] hover:bg-[#334155] hover:text-white transition-colors text-xs"
            :title="isSidebarCollapsed ? 'Lọc' : ''"
          >
            <Filter class="w-3.5 h-3.5 text-white shrink-0" />
            <span v-if="!isSidebarCollapsed" class="truncate">Lọc</span>
          </button>
        </div>

      </div>

      <!-- Bottom Button: Trả phòng -->
      <div class="pt-2 border-t border-[#475569] shrink-0">
        <button 
          class="w-full flex items-center justify-center gap-1.5 px-2 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded font-semibold shadow-sm transition-colors text-xs"
          :title="isSidebarCollapsed ? 'Trả phòng' : ''"
        >
          <LogOut class="w-3.5 h-3.5 rotate-180 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Trả phòng</span>
        </button>
      </div>
    </aside>

    <!-- RIGHT MAIN SECTION -->
    <main class="checkout-main flex-1 grid min-w-0 grid-cols-[minmax(360px,380px)_minmax(0,1fr)] grid-rows-[45px_minmax(0,1fr)] gap-0 bg-[#f1f5f9] overflow-hidden">

      <!-- TOP CONTROL BAR (Nằm trên cùng toàn chiều rộng, không thuộc panel nào) -->
      <div class="checkout-header col-span-2 flex items-center justify-between gap-2 px-4 py-1.5 bg-white border-b border-slate-300 text-xs">
        <div class="flex items-center gap-2 flex-1 max-w-xl">
          <!-- Search Input with Popup Dropdown (Khớp 100% Ảnh 1 & 2) -->
          <div ref="searchContainerRef" class="relative flex-1">
            <Search class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 z-10" />
            <input 
              v-model="searchQuery" 
              @focus="showSearchDropdown = true"
              @input="showSearchDropdown = true"
              type="text" 
              placeholder="Nh&#7853;p s&#7889; ph&#242;ng, t&#234;n kh&#225;ch, m&#227; Booking..." 
              class="w-full pl-7 pr-7 py-1 bg-white border border-slate-300 rounded text-xs focus:outline-none focus:border-blue-500" 
            />
            <button 
              v-if="searchQuery" 
              @click="clearSearch"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 z-10"
              title="Xóa tìm kiếm"
            >
              <X class="w-3.5 h-3.5" />
            </button>

            <!-- Floating Search Dropdown Popup List (Khớp chính xác Ảnh 1) -->
            <div 
              v-if="showSearchDropdown" 
              class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-300 rounded-md shadow-2xl z-50 max-h-72 overflow-y-auto divide-y divide-gray-100"
            >
              <div 
                v-for="b in filteredSearchBookings" 
                :key="b.id"
                class="px-3 py-2 hover:bg-sky-50 cursor-pointer text-xs transition-colors"
                @click="selectBookingFromSearch(b)"
              >
                <!-- Line 1: BKK:  <mã booking>    <tên đoàn / tên booking> -->
                <div class="flex items-center gap-4">
                  <span class="font-bold text-gray-900 shrink-0 min-w-[36px]">BKK:</span>
                  <span class="font-bold text-gray-900 shrink-0 min-w-[75px]">{{ b.code }}</span>
                  <span class="font-bold text-gray-800 truncate">{{ b.name }}</span>
                </div>
                <!-- Sublines for each room in booking (Khớp chính xác Ảnh 1) -->
                <template v-for="r in b.roomItems" :key="r.id">
                  <template v-if="showAllGuestsInRoom && r.allGuests.length > 1">
                    <div 
                      v-for="(gName, gIdx) in r.allGuests"
                      :key="gIdx"
                      @click.stop="selectBookingFromSearch(b, r, gName)"
                      class="flex items-center gap-4 mt-1.5 text-gray-700 pl-[36px] hover:text-sky-600"
                    >
                      <span class="font-bold text-gray-800 shrink-0 min-w-[75px]">{{ r.roomNumber }}</span>
                      <span class="text-gray-400">|</span>
                      <span
                        class="text-gray-800 truncate"
                        :class="gIdx === 0 ? 'font-bold' : 'font-normal'"
                      >{{ gName }}</span>
                    </div>
                  </template>
                  <template v-else>
                    <div 
                      @click.stop="selectBookingFromSearch(b, r)"
                      class="flex items-center gap-4 mt-1.5 text-gray-700 pl-[36px] hover:text-sky-600"
                    >
                      <span class="font-bold text-gray-800 shrink-0 min-w-[75px]">{{ r.roomNumber }}</span>
                      <span class="text-gray-400">|</span>
                      <span class="font-bold text-gray-800 truncate">{{ r.guestName }}</span>
                    </div>
                  </template>
                </template>
              </div>
              <div v-if="filteredSearchBookings.length === 0" class="p-3 text-center text-gray-400 italic">
                Không tìm thấy dữ liệu đăng ký nào
              </div>
            </div>
          </div>                    <div ref="filterContainerRef" class="checkout-register-filter relative">
            <button type="button" @click.stop="showRegisterFilterDropdown = !showRegisterFilterDropdown" class="checkout-filter-button flex items-center gap-1 px-2 py-1 text-xs font-semibold text-white bg-blue-600 border border-blue-600 rounded">
              <Filter class="w-3.5 h-3.5" />
              <span>{{ registerFilter === 'current' ? 'Đăng ký hiện tại' : registerFilter === 'virtual' ? 'Phòng ảo' : 'Đăng ký cũ' }}</span>
              <ChevronDown class="w-3 h-3" />
            </button>
            <div v-if="showRegisterFilterDropdown" class="checkout-filter-dropdown" @click.stop>
              <div class="checkout-filter-tabs">
                <button type="button" @click="registerFilter = 'current'" :class="['checkout-filter-tab', registerFilter === 'current' ? 'active' : '']">Đăng ký hiện tại</button>
                <button type="button" @click="registerFilter = 'old'" :class="['checkout-filter-tab', registerFilter === 'old' ? 'active' : '']">Đăng ký cũ</button>
                <button type="button" @click="registerFilter = 'virtual'" :class="['checkout-filter-tab', registerFilter === 'virtual' ? 'active' : '']">Phòng ảo</button>
              </div>
              <div class="checkout-filter-box">
                <div class="checkout-filter-box-title">Phạm vi ngày</div>
                                <select v-model="filterDateScope" @change="handleFilterScopeChange" class="checkout-filter-scope">
                  <option value="today">Hôm nay</option>
                  <option value="yesterday">Hôm qua</option>
                  <option value="this_week">Tuần này</option>
                  <option value="this_month">Tháng này</option>
                  <option value="custom">Tùy chọn</option>
                </select>
                <div class="checkout-filter-date-row">
                  <label class="checkout-filter-date-label"><input type="checkbox" v-model="filterDepartureChecked" class="rounded border-gray-300 text-blue-600" /> Ngày đi ĐK</label>
                  <div class="checkout-filter-date-inputs">
                    <div class="checkout-filter-date-wrap" @click="openDatePicker"><input type="date" v-model="filterDateFrom" @change="filterDateScope = 'custom'" /><i class="fa-regular fa-calendar-days"></i><i class="fa-regular fa-copy" @click.stop="copyFilterDate('from')" title="Chép ngày sang ô bên phải"></i></div>
                    <div class="checkout-filter-date-wrap" @click="openDatePicker"><input type="date" v-model="filterDateTo" @change="filterDateScope = 'custom'" /><i class="fa-regular fa-calendar-days"></i><i class="fa-regular fa-copy" @click.stop="copyFilterDate('to')" title="Chép ngày sang ô bên trái"></i></div>
                  </div>
                </div>
              </div>
              <div class="checkout-filter-actions">
                <button type="button" @click="resetCheckoutFilterDraft"><i class="fa-solid fa-circle-xmark"></i> Đóng</button>
                <button type="button" @click="applyCheckoutFilters()"><i class="fa-solid fa-circle-check"></i> Áp dụng</button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Checkbox Xem tất cả khách trong phòng -->
          <label class="flex items-center gap-1.5 cursor-pointer text-gray-700 hover:text-gray-900 whitespace-nowrap text-xs">
            <input type="checkbox" v-model="showAllGuestsInRoom" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
            <span>Xem tất cả khách trong phòng</span>
          </label>          <button @click="openRegistrationFromCheckout" :disabled="!selectedBooking" class="checkout-registration-button inline-flex items-center gap-1 px-2 py-1 text-xs text-blue-600 bg-white border border-blue-500 rounded hover:bg-blue-50 disabled:opacity-40 disabled:cursor-not-allowed">
            <i class="fa-solid fa-address-card text-[11px]"></i>
            <span>Xem đăng ký</span>
          </button>
        </div>
      </div>

      <!-- TOP SPLIT SECTION (Bảng Đăng ký + Panel Thông tin) -->
      <div class="checkout-left-pane col-start-1 row-start-2 flex min-h-0 flex-col gap-0 border-r border-slate-300">

        <!-- TOP LEFT: Bảng Danh sách Đăng ký (7 cols) -->
        <div class="checkout-bookings-panel flex-[1.65] bg-white rounded-none border-0 border-b border-slate-300 flex flex-col min-h-0 shadow-none">
          <!-- Table Danh sách Phòng / Khách (Khớp chính xác Ảnh 2) -->
          <div class="flex-1 overflow-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1 w-[25px] text-center"></th>
                  <th class="p-1 text-center">ĐK/Phòng</th>
                  <th class="p-1 text-center">Tên khách</th>
                  <th class="p-1 text-right">Tổng DV</th>
                  <th class="p-1 text-right">Đã TT</th>
                </tr>
              </thead>
                            <tbody class="divide-y divide-gray-200">
                <template v-for="b in displayedBookingsList" :key="b.id">
                  <tr
                    @click="selectBookingHeader(b)"
                    :class="[
                      selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'bg-[#eff6ff] border-l-[3px] border-blue-600' : 'bg-[#f0f4ff] border-l-[3px] border-indigo-500',
                      'cursor-pointer transition-colors'
                    ]"
                  >
                    <td class="p-1 w-[25px] text-center">
                      <input type="checkbox" v-model="b.checked" @change="toggleBookingCheck(b)" @click.stop class="rounded border-gray-300 text-sky-600" />
                    </td>
                    <td colspan="2" class="p-1 font-bold text-slate-900">
                      <div class="flex items-center gap-1 whitespace-nowrap">
                        <i class="fa-solid fa-layer-group text-[10px] text-indigo-500"></i>
                        <span class="rounded bg-slate-200 px-1 text-[9px] font-bold">{{ b.code }}</span>
                        <span class="truncate">{{ b.name }}</span>
                      </div>
                    </td>
                    <td class="p-1 text-right font-mono text-slate-900">{{ formatSummaryMoney(b.totalService) }}</td>
                    <td class="p-1 text-right font-mono text-slate-900">{{ formatMoney(b.paidAmount) }}</td>
                  </tr>

                  <template v-for="r in b.roomItems" :key="r.id">
                    <template v-if="getGuestsToDisplay(b, r).length > 1">
                      <tr
                        v-for="(guest, gIdx) in getGuestsToDisplay(b, r)"
                        :key="`${r.id}-${guest.id || gIdx}`"
                        @click="selectRoomItemRow(b, r, guest)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id && String(selectedGuestId) === String(guest.id) ? 'bg-[#eff6ff] border-l-[3px] border-blue-600' : 'hover:bg-slate-50',
                          'cursor-pointer transition-colors text-slate-900'
                        ]"
                      >
                        <td class="p-1 w-[25px] text-center">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 font-bold text-slate-900">{{ r.roomNumber }}</td>
                        <td class="p-1" :class="gIdx === 0 ? 'font-bold' : 'pl-4 italic text-slate-700'">{{ guest.name }}</td>
                        <td class="p-1 text-right font-mono">{{ formatSummaryMoney(guestRoomServiceAmount(b, r, guest.id)) }}</td>
                        <td class="p-1 text-right font-mono">{{ formatMoney(guestRoomPaidAmount(b, r, guest.id)) }}</td>
                      </tr>
                    </template>
                    <template v-else>
                      <tr
                        @click="selectRoomItemRow(b, r)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id ? 'bg-[#eff6ff] border-l-[3px] border-blue-600' : 'hover:bg-slate-50',
                          'cursor-pointer transition-colors text-slate-900'
                        ]"
                      >
                        <td class="p-1 w-[25px] text-center">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 font-bold">{{ r.roomNumber }}</td>
                        <td class="p-1 font-bold">{{ r.guestName }}</td>
                        <td class="p-1 text-right font-mono">{{ formatSummaryMoney(guestRoomServiceAmount(b, r, r.primaryGuestId)) }}</td>
                        <td class="p-1 text-right font-mono">{{ formatMoney(guestRoomPaidAmount(b, r, r.primaryGuestId)) }}</td>
                      </tr>
                    </template>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- BOOKING INFORMATION + FOLIO (reference layout) -->
        <div class="checkout-info-panel bg-white rounded-none border-0 flex flex-col min-h-0 shadow-none">
          <div class="checkout-info-heading flex items-center justify-between border-b border-slate-300 px-2 py-1">
            <span class="checkout-info-title"><i class="fa-solid fa-bed"></i> Thông Tin Đăng Ký</span>
            <label class="flex items-center gap-1 text-[10px] font-bold text-red-600">
              <input type="checkbox" v-model="isNoPost" :disabled="!selectedBooking || noPostSaving" @change="handleNoPostChange" class="rounded border-gray-300 text-sky-600" />
              No post
            </label>
          </div>
          <div class="grid grid-cols-2 gap-x-3 gap-y-1 px-2 py-1 text-[10px] leading-tight">
            <div class="col-span-2"><label class="block text-slate-400">Tên đăng ký</label><span class="font-semibold text-slate-700 truncate block">{{ selectedBooking ? `${selectedBooking.code}-${selectedBooking.name}` : '--' }}</span></div>
            <div><label class="block text-slate-400">Tên khách</label><span class="font-semibold text-slate-700 truncate block">{{ selectedGuest || '--' }}</span></div>
            <div><label class="block text-slate-400">Ngày đến ~ Ngày đi</label><span class="font-semibold text-slate-700">{{ selectedBooking ? `${formatDate(selectedBooking.arrivalDate)} - ${formatDate(selectedBooking.departureDate)}` : '--' }}</span></div>
            <div><label class="block text-slate-400">Phòng / Hạng</label><span class="font-semibold text-slate-700">{{ roomNumber || '--' }} - {{ selectedRoomItem?.roomType || selectedRoomItem?.roomName || 'SUPT' }}</span></div>
            <div><label class="block text-slate-400">Trạng thái</label><span class="font-semibold text-emerald-600">In-House</span></div>
            <div class="col-span-2"><label class="block text-slate-400"><i class="fa-regular fa-note-sticky"></i> Ghi chú:</label><textarea :value="noteText" readonly class="w-full h-7 px-1 py-0.5 bg-white border border-slate-300 rounded text-[10px] resize-none"></textarea></div>
          </div>
          <div class="checkout-folio-section border-t border-slate-300 mb-auto px-2 py-1">
            <div class="checkout-folio-title"><i class="fa-solid fa-wallet"></i> Folio</div>
            <div class="grid grid-cols-2 gap-1">
              <button @click="activeFolioTab = 'A'" :class="[activeFolioTab === 'A' ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-white', 'checkout-folio-card border rounded px-1.5 py-0.5 text-left']"><div class="font-bold text-[10px]">Folio A</div><div class="text-right text-[15px] leading-none font-bold text-red-500">{{ formatSummaryMoney(folioTotal('A')) }}</div></button>
              <button @click="activeFolioTab = '1'" @dragover.prevent="draggedOverFolio = 1" @dragleave="draggedOverFolio = null" @drop.prevent="handleFolioDrop(1)" :class="[activeFolioTab === '1' ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-white', 'checkout-folio-card border rounded px-1.5 py-0.5 text-left']"><div class="font-bold text-[10px]">Folio 1</div><div class="text-right text-[15px] leading-none font-bold text-blue-600">{{ formatSummaryMoney(folioTotal(1)) }}</div></button>
              <button @click="activeFolioTab = '2'" @dragover.prevent="draggedOverFolio = 2" @dragleave="draggedOverFolio = null" @drop.prevent="handleFolioDrop(2)" :class="[activeFolioTab === '2' ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-white', 'checkout-folio-card border rounded px-1.5 py-0.5 text-left']"><div class="font-bold text-[10px]">Folio 2</div><div class="text-right text-[15px] leading-none font-bold text-blue-600">{{ formatSummaryMoney(folioTotal(2)) }}</div></button>
              <button @click="activeFolioTab = '3'" @dragover.prevent="draggedOverFolio = 3" @dragleave="draggedOverFolio = null" @drop.prevent="handleFolioDrop(3)" :class="[activeFolioTab === '3' ? 'border-blue-500 bg-blue-50' : 'border-slate-300 bg-white', 'checkout-folio-card border rounded px-1.5 py-0.5 text-left']"><div class="font-bold text-[10px]">Folio 3</div><div class="text-right text-[15px] leading-none font-bold text-blue-600">{{ formatSummaryMoney(folioTotal(3)) }}</div></button>
            </div>
          </div>
        </div>
      </div>

      <!-- BOTTOM SPLIT SECTION (2 Bảng dữ liệu song song) -->
      <div class="checkout-billing-pane col-start-2 row-start-2 flex min-h-0 flex-col gap-0">

        <!-- BOTTOM LEFT: Bảng Chi tiết Dịch vụ / Phát sinh (6 cols) -->
        <div class="checkout-services-panel flex-[1.7] bg-white rounded-none border-0 border-b border-slate-300 flex flex-col min-h-0 shadow-none overflow-hidden">
          <div class="checkout-service-title flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">
            <i class="fa-solid fa-list-check text-slate-900 text-[12px]"></i>
            <span class="text-slate-900">Dịch vụ</span>
          </div>
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center">
                    <input
                      type="checkbox"
                      :checked="areAllServicesSelected"
                      :disabled="serviceSelectionIds.length === 0"
                      @change="toggleAllServiceSelection($event.target.checked)"
                      class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                  </th>
                  <th class="px-2.5 py-1.5">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5">Dịch vụ</th>
                  <th class="px-2.5 py-1.5">Mô tả</th>
                  <th class="px-2.5 py-1.5">Bộ phận</th>
                  <th class="px-2.5 py-1.5 text-right">Số tiền</th>
                  <th class="px-2.5 py-1.5 text-center">SL</th>
                  <th class="px-2.5 py-1.5">Mã TT</th>
                  <th class="px-2.5 py-1.5">Folio</th>
                  <th class="px-2.5 py-1.5 text-right">Tax</th>
                  <th class="px-2.5 py-1.5 text-right">Phí phục vụ</th>
                  <th class="px-2.5 py-1.5">Số VAT</th>
                  <th class="px-2.5 py-1.5">Người dùng</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="group in serviceGroups"
                  :key="group.id"
                  :draggable="canTransferServiceGroup(group)"
                  @click="openServiceInvoice(group)"
                  @dragstart="handleServiceDragStart(group, $event)"
                  @dragend="handleServiceDragEnd"
                  :class="[
                    'border-b border-gray-200 transition-colors',
                    group.paymentCode ? 'bg-[#fde8e8] text-rose-950 font-medium hover:bg-rose-100' : 'hover:bg-sky-50 text-gray-800 cursor-pointer',
                    canTransferServiceGroup(group) ? 'cursor-grab active:cursor-grabbing' : ''
                  ]"
                  :title="canTransferServiceGroup(group) ? 'Kéo sang Folio khác' : 'Xem chi tiết hóa đơn'"
                >
                  <td class="px-2 py-1.5 text-center">
                    <input
                      type="checkbox"
                      :checked="isServiceGroupSelected(group)"
                      @click.stop
                      @change="toggleServiceGroupSelection(group, $event.target.checked)"
                      class="rounded border-gray-300"
                    />
                  </td>
                  <td class="px-2.5 py-1.5 font-mono">{{ group.dateTime }}</td>
                  <td class="px-2.5 py-1.5 font-bold text-sky-600">{{ group.code }}</td>
                  <td class="px-2.5 py-1.5">{{ group.name }} <span class="text-gray-400">({{ group.items.length }})</span></td>
                  <td class="px-2.5 py-1.5">{{ group.department }}</td>
                  <td class="px-2.5 py-1.5 text-right font-mono font-bold">{{ formatSummaryMoney(group.totalAmount) }}</td>
                  <td class="px-2.5 py-1.5 text-center font-mono">{{ group.quantity }}</td>
                  <td class="px-2.5 py-1.5 font-mono font-bold text-red-600">{{ group.paymentCode }}</td>
                  <td class="px-2.5 py-1.5 text-center font-bold">
                    <span class="bg-[#8fd1d9] text-gray-900 px-2 py-0.5 rounded text-xs font-bold inline-block">{{ group.folio }}</span>
                  </td>
                  <td class="px-2.5 py-1.5 text-right font-mono">{{ group.tax ? formatMoney(group.tax) : '' }}</td>
                  <td class="px-2.5 py-1.5 text-right font-mono">{{ group.serviceCharge ? formatMoney(group.serviceCharge) : '' }}</td>
                  <td class="px-2.5 py-1.5">{{ group.items[0]?.vatNo }}</td>
                  <td class="px-2.5 py-1.5">{{ group.items[0]?.userName }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div v-if="serviceGroups.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
              <Inbox class="w-9 h-9 stroke-1 mb-1 text-gray-300" />
              <span class="text-xs text-gray-400">No data</span>
            </div>
          </div>

          <!-- Table Footer Total -->
          <div class="px-3 py-1.5 bg-[#f4f5f0] border-t border-gray-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input
                type="checkbox"
                :checked="areAllServicesSelected"
                :disabled="serviceSelectionIds.length === 0"
                @change="toggleAllServiceSelection($event.target.checked)"
                class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
              />
              <span class="uppercase text-[10px] text-slate-500 tracking-wide">Tổng dịch vụ:</span>
            </div>
            <span class="font-mono text-xs pr-2 text-blue-600 font-bold text-sm">{{ formatSummaryMoney(totalServiceAmount) }}</span>
          </div>
        </div>

        <!-- BOTTOM RIGHT: Bảng Chi tiết Thanh toán (6 cols) -->
        <div class="checkout-payments-panel flex-1 bg-white rounded-none border-0 flex flex-col min-h-0 shadow-none overflow-hidden">
          <div class="checkout-payment-title flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">
            <i class="fa-solid fa-money-bill-transfer text-slate-900 text-[12px]"></i>
            <span class="text-slate-900">Thanh Toán</span>
          </div>
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="checkout-payment-table w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center"><input type="checkbox" :checked="areAllPaymentsSelected" :disabled="paymentSelectionIds.length === 0" @change="toggleAllPaymentSelection($event.target.checked)" class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50" /></th>
                  <th class="px-2.5 py-1.5">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5">Bộ phận</th>
                  <th class="px-2.5 py-1.5">Mô tả</th>
                  <th class="px-2.5 py-1.5">HTTT</th>
                  <th class="px-2.5 py-1.5 text-right">Số tiền</th>
                  <th class="px-2.5 py-1.5">Folio</th>
                  <th class="px-2.5 py-1.5">Mã thanh toán</th>
                  <th class="px-2.5 py-1.5">Số VAT</th>
                  <th class="px-2.5 py-1.5">Giải trừ CN</th>
                  <th class="px-2.5 py-1.5">Người dùng</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in visiblePaymentsList"
                  :key="p.id"
                  :draggable="canTransferPayment(p)"
                  @dragstart="handlePaymentDragStart(p, $event)"
                  @dragend="handleServiceDragEnd"
                  :class="[
                    'border-b border-gray-200 transition-colors',
                    p.paymentCode ? 'bg-[#fde8e8] text-rose-950 font-medium hover:bg-rose-100' : 'hover:bg-gray-50 text-gray-800',
                    canTransferPayment(p) ? 'cursor-grab active:cursor-grabbing' : ''
                  ]"
                  :title="canTransferPayment(p) ? 'Kéo sang Folio khác' : 'Cọc đã dùng để thanh toán không thể chuyển Folio'"
                >
                  <td class="px-2 py-1.5 text-center">
                    <input
                      type="checkbox"
                      :checked="isPaymentSelected(p)"
                      @click.stop
                      @change="togglePaymentSelection(p, $event.target.checked)"
                      class="rounded border-gray-300"
                    />
                  </td>
                  <td class="px-2.5 py-1.5 font-mono">{{ p.dateTime }}</td>
                  <td class="px-2.5 py-1.5">{{ p.department }}</td>
                  <td class="px-2.5 py-1.5" :class="p.paymentCode ? 'text-red-600 font-medium' : 'text-gray-800'">{{ p.description }}</td>
                  <td class="px-2.5 py-1.5 font-medium text-emerald-600">{{ p.paymentMethod }}</td>
                  <td class="px-2.5 py-1.5 text-right font-mono font-bold" :class="p.paymentCode ? 'text-red-600' : 'text-emerald-700'">{{ formatMoney(p.amount) }}</td>
                  <td class="px-2.5 py-1.5 text-center font-bold"><span class="inline-block rounded bg-[#8fd1d9] px-2 py-0.5 text-xs text-gray-900">{{ p.folio }}</span></td>
                  <td class="px-2.5 py-1.5 font-mono font-bold text-red-600">{{ p.paymentCode }}</td>
                  <td class="px-2.5 py-1.5">{{ p.vatNo }}</td>
                  <td class="px-2.5 py-1.5">{{ p.accounting }}</td>
                  <td class="px-2.5 py-1.5">{{ p.userName }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Empty Data Placeholder -->
            <div v-if="visiblePaymentsList.length === 0" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 pt-6">
              <Inbox class="w-9 h-9 stroke-1 mb-1 text-gray-300" />
              <span class="text-xs text-gray-400">No data</span>
            </div>
          </div>

          <!-- Table Footer Total -->
          <div class="checkout-payment-footer px-3 py-1.5 bg-[#f8fafc] border-t border-slate-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input
                type="checkbox"
                :checked="areAllPaymentsSelected"
                :disabled="paymentSelectionIds.length === 0"
                @change="toggleAllPaymentSelection($event.target.checked)"
                class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
              />
              <span class="uppercase text-[10px] text-slate-500 tracking-wide">Tổng thanh toán:</span>
            </div>
            <span class="font-mono text-xs pr-2 text-emerald-600 font-bold text-sm">{{ formatMoney(totalPaymentAmount) }}</span>
          </div>
        </div>

      </div>

    </main>

    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div v-if="selectedServiceGroup" class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-slate-900/25" @click="closeServiceInvoice"></div>
        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-200 ease-in"
          enter-from-class="translate-x-full"
          leave-to-class="translate-x-full"
        >
          <aside v-if="selectedServiceGroup" class="absolute right-0 top-0 h-full w-full max-w-[650px] bg-white shadow-2xl flex flex-col">
            <header class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
              <div>
                <h2 class="text-base font-semibold text-gray-900">Hóa đơn</h2>
                <p class="mt-1 text-xs text-gray-500">{{ selectedServiceGroup.name }} · {{ selectedServiceGroup.dateTime }}</p>
              </div>
              <button @click="closeServiceInvoice" class="w-8 h-8 rounded hover:bg-gray-100 text-xl text-gray-500" aria-label="Đóng">×</button>
            </header>

            <section class="grid grid-cols-2 gap-x-8 gap-y-3 px-5 py-4 border-b border-gray-100 text-xs text-gray-700">
              <div><span class="font-semibold">Mã:</span> {{ selectedBooking?.code || '--' }}</div>
              <div><span class="font-semibold">Phòng:</span> {{ roomNumber || selectedRoomItem?.roomNumber || '--' }}</div>
              <div><span class="font-semibold">Khu vực:</span> {{ selectedServiceGroup.code }}</div>
              <div><span class="font-semibold">Folio:</span> {{ selectedServiceGroup.folio }}</div>
            </section>

            <div class="flex-1 overflow-auto p-5">
              <table class="w-full border-collapse text-xs">
                <thead class="bg-gray-50 text-gray-600">
                  <tr>
                    <th class="border border-gray-200 px-3 py-2 text-center w-12">STT</th>
                    <th class="border border-gray-200 px-3 py-2 text-left">Sản phẩm</th>
                    <th class="border border-gray-200 px-3 py-2 text-center w-16">SL</th>
                    <th class="border border-gray-200 px-3 py-2 text-right w-24">Giá</th>
                    <th class="border border-gray-200 px-3 py-2 text-right w-28">Số tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in selectedServiceGroup.items" :key="item.id" class="text-gray-800">
                    <td class="border border-gray-200 px-3 py-2 text-center">{{ index + 1 }}</td>
                    <td class="border border-gray-200 px-3 py-2">{{ formatInvoiceProductName(item.serviceName) }}</td>
                    <td class="border border-gray-200 px-3 py-2 text-center">{{ formatInvoiceQuantity(item.quantity) }}</td>
                    <td class="border border-gray-200 px-3 py-2 text-right font-mono">{{ formatInvoiceMoney(item.amount) }}</td>
                    <td class="border border-gray-200 px-3 py-2 text-right font-mono font-semibold">{{ formatInvoiceMoney(item.totalAmount) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="font-semibold text-gray-900">
                    <td colspan="4" class="border border-gray-200 px-3 py-3 text-right">Tổng tiền</td>
                    <td class="border border-gray-200 px-3 py-3 text-right font-mono">{{ formatInvoiceMoney(selectedServiceGroup.totalAmount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </aside>
        </Transition>
      </div>
    </Transition>

    <!-- Modals -->
    <AddServiceModal 
      :show="showAddServiceModal" 
      :bookingInfo="addServiceBookingInfo"
      :bookingRoomId="selectedRoomItem ? (selectedRoomItem.roomId || selectedRoomItem.id) : ''"
      :bookingId="selectedBooking ? selectedBooking.bookingId : ''"
      :arrivalDate="selectedBooking?.arrivalDate || selectedRoomItem?.rawRoom?.arrival_date || ''"
      :departureDate="selectedBooking?.departureDate || selectedRoomItem?.rawRoom?.departure_date || ''"
      :roomRate="Number(selectedRoomItem ? (selectedRoomItem.rate ?? selectedRoomItem.roomRate ?? selectedRoomItem.rawRoom?.rate ?? selectedRoomItem.rawRoom?.room_rate ?? 0) : (selectedBooking?.roomItems?.[0]?.rate ?? selectedBooking?.roomItems?.[0]?.roomRate ?? selectedBooking?.roomItems?.[0]?.rawRoom?.rate ?? selectedBooking?.roomItems?.[0]?.rawRoom?.room_rate ?? 0))"
      :roomAdjustment="roomAdjustment"
      :systemDate="systemDate"
      @close="showAddServiceModal = false; roomAdjustment = null" 
      @success="handleServiceAdded"
    />

    <AddHousekeepingServiceModal 
      :show="showHousekeepingServiceModal" 
      :bookingInfo="addServiceBookingInfo"
      :roomId="selectedRoomItem ? (selectedRoomItem.roomId || selectedRoomItem.id) : ''"
      :guestId="selectedGuestId"
      :initialAdjustment="housekeepingAdjustment"
      :folioId="activeFolioTab === 'A' ? 1 : (Number(activeFolioTab) || 1)"
      @close="showHousekeepingServiceModal = false; housekeepingAdjustment = null" 
      @submit="handleServiceAdded"
    />

    <QuickTransferBillModal 
      :show="showQuickTransferBillModal" 
      :target-label="selectedRoomItem ? `${selectedGuest || ''} - ${roomNumber || ''}` : `Master - ${selectedBooking?.code || ''}`"
      :candidates="quickTransferCandidates"
      :loading="isServiceOperationLoading"
      :loading-text="quickTransferLoadingText"
      @close="showQuickTransferBillModal = false" 
      @submit="submitQuickTransferBills"
    />

    <CancelServiceModal
      :show="showCancelServiceModal"
      :loading="isServiceOperationLoading"
      :count="selectedServiceItems.length"
      :canDelete="canCancelSelectedServices"
      :canAdjust="canAdjustSelectedService"
      @close="showCancelServiceModal = false"
      @submit="cancelSelectedServices"
      @adjust="openServiceAdjustment"
    />

    <PrepaymentModal 
      :show="showPrepaymentModal" 
      :bookingId="selectedBooking?.bookingId"
      :bookingCode="selectedBooking?.code"
      :bookingName="selectedBooking?.name"
      :selectedRoomId="selectedRoomItem?.roomId || null"
      :selectedRoomNumber="selectedRoomItem?.roomNumber || ''"
      :selectedGuestId="selectedGuestId"
      :systemDate="systemDate"
      :roomOptions="selectedBooking?.roomItems || []"
      @close="showPrepaymentModal = false" 
      @success="handlePrepaymentSuccess"
    />

    <PaymentModal 
      :show="showPaymentModal" 
      :bookingId="selectedBooking?.bookingId"
      :bookingCode="selectedBooking?.code"
      :bookingName="selectedBooking?.name"
      :selectedRoomId="selectedRoomItem?.roomId || null"
      :selectedGuestId="selectedGuestId"
      :folioId="activeFolioTab"
      :totalServiceAmount="currentFolioUnpaidServiceTotal"
      :totalDepositAmount="currentFolioDepositTotal"
      :systemDate="systemDate"
      @close="showPaymentModal = false" 
      @success="handlePaymentSuccess"
    />

    <FilterServiceModal 
      :show="showFilterServiceModal" 
      @close="showFilterServiceModal = false" 
    />

    <TransferServiceModal
      :show="showTransferServiceModal"
      :fromGuest="selectedGuest"
      :destinations="transferDestinations"
      :error="transferServiceError"
      :loading="isServiceOperationLoading"
      @close="showTransferServiceModal = false; transferServiceError = ''"
      @transfer="transferSelectedServices"
    />

    <TransferPaymentModal
      :show="showTransferPaymentModal"
      :payment="selectedPaymentItems[0] || null"
      :from-label="selectedRoomItem ? `${selectedGuest || ''} - ${roomNumber || ''}` : `Master - ${selectedBooking?.code || ''}`"
      :destinations="transferDestinations"
      :error="transferPaymentError"
      :loading="isServiceOperationLoading"
      @close="showTransferPaymentModal = false; transferPaymentError = ''"
      @transfer="transferSelectedPayment"
    />

    <SplitServiceModal
      :show="showSplitServiceModal"
      :selectedCount="selectedServiceItems.length"
      :totalAmount="selectedServicesTotal"
      :loading="isServiceOperationLoading"
      @close="showSplitServiceModal = false"
      @split="splitSelectedServices"
    />

    <SplitDepositModal
      :show="showSplitDepositModal"
      :loading="isServiceOperationLoading"
      :totalAmount="selectedPaymentItems[0]?.amount || 0"
      :folio="selectedPaymentItems[0]?.folio || 1"
      @close="showSplitDepositModal = false"
      @split="splitSelectedDeposit"
    />

    <DeletePaymentModal
      :show="showDeletePaymentModal"
      :loading="isServiceOperationLoading"
      :payment="selectedPaymentItems[0] || null"
      @close="showDeletePaymentModal = false"
      @submit="deleteSelectedPayment"
    />
  </div>
</template>

<style scoped>
.checkout-action-menu :deep(button) {
  color: #e2e8f0;
  border-color: transparent;
  padding-top: 0.38rem;
  padding-bottom: 0.38rem;
}

.checkout-action-menu :deep(button:hover) {
  color: #ffffff;
  background: #334155;
  border-color: #475569;
}

.checkout-action-menu :deep(svg) {
  color: #cbd5e1;
}

.checkout-action-menu :deep(.border-t) {
  border-color: #334155;
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
}

/* Invoice layout aligned with the customer-provided reference screen. */
.checkout-shell {
  --checkout-header-height: 45px;
  --checkout-bottom-height: 285px;
  min-width: 1024px;
}
.checkout-main { min-width: 0; }
.checkout-header { min-height: var(--checkout-header-height); }
.checkout-header select { height: 28px; border-color: #cbd5e1; background: #fff; }
.checkout-left-pane, .checkout-billing-pane { min-height: 0; }
.checkout-bookings-panel { min-height: 180px; }
.checkout-billing-pane { padding: 8px 8px 8px 8px; background: #f1f5f9; }
.checkout-services-panel { min-height: 0; }
.checkout-payments-panel { flex: 0 0 var(--checkout-bottom-height); }
.checkout-actions { width: 170px; min-width: 170px; }
.checkout-actions button { font-size: 11px; }
.checkout-actions .checkout-action-menu { padding-top: 0.5rem; }
.checkout-actions .checkout-action-menu > div { border-color: #334155; }
.checkout-actions .checkout-action-menu > div:last-child { margin-top: 0; }
.checkout-bookings-panel table thead th,
.checkout-services-panel table thead th,
.checkout-payments-panel table thead th { background: #f8fafc; color: #64748b; }
@media (max-height: 768px) {
  .checkout-shell { --checkout-bottom-height: 275px; }
}
.checkout-bookings-heading { height: 24px; flex: 0 0 24px; }
.checkout-info-panel { font-size: 10px; }
.checkout-info-heading { height: 24px; flex: 0 0 24px; }
.checkout-folio-section { flex: 0 0 88px; }
.checkout-folio-card { height: 36px; }
.checkout-header > div:first-child { max-width: 430px; }
.checkout-header select { background: #2563eb; color: #fff; border-color: #2563eb; font-weight: 700; min-width: 108px; }
.checkout-header select option { background: #fff; color: #0f172a; }
.checkout-left-pane { width: 100%; }
.checkout-bookings-panel { flex: 1 1 auto; min-height: 0; }
.checkout-info-panel { flex: 0 0 285px; }
.checkout-billing-pane { flex: 1 1 auto; }
.checkout-services-panel { flex: 1 1 auto; }
.checkout-payments-panel { flex: 0 0 285px; }
/* Match the reference invoice cards and payment footer exactly. */
.checkout-billing-pane { gap: 8px; padding: 8px; background: #f1f5f9; }
.checkout-services-panel,
.checkout-payments-panel { border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); }
.checkout-services-panel > div:first-child,
.checkout-payments-panel > div:first-child { background: #f8fafc; padding: 6px 10px; min-height: 29px; }
.checkout-services-panel table th,
.checkout-payments-panel table th { padding: 5px 8px; font-size: 11px; font-weight: 600; color: #64748b; background: #f8fafc; }
.checkout-services-panel table td,
.checkout-payments-panel table td { padding: 5px 8px; font-size: 11px; }
.checkout-services-panel > div:last-child,
.checkout-payments-panel > div:last-child { justify-content: flex-end; padding: 5px 12px; background: #f8fafc; }
.checkout-services-panel > div:last-child input,
.checkout-payments-panel > div:last-child input { display: none; }
.checkout-payments-panel > div:first-child svg { color: #0f172a; }
.checkout-payments-panel > div:last-child span:last-child { color: #2563eb; }
/* The reference HTML uses intrinsic table widths (no per-column min-widths). */
.checkout-payment-table { table-layout: auto; width: 100%; }
.checkout-payment-table th,
.checkout-payment-table td { min-width: 0 !important; white-space: nowrap; }
.checkout-payment-table th:first-child,
.checkout-payment-table td:first-child { width: 25px; }
.checkout-payment-title { padding: 6px 10px !important; min-height: 29px; }
.checkout-payment-title svg { color: #0f172a !important; }
.checkout-payment-footer { padding: 5px 12px !important; min-height: 29px; }
.checkout-payment-footer > div { gap: 0; }
.checkout-payment-footer > div > span:first-of-type { margin-right: 12px; color: #64748b; font-size: 10px; text-transform: uppercase; }
.checkout-payment-footer > span:last-child { color: #2563eb !important; font-size: 12px; }
/* Fallback selectors for the existing payment markup. */
.checkout-payments-panel > div:nth-child(2) > table { table-layout: auto; width: 100%; }
.checkout-payments-panel > div:nth-child(2) > table th,
.checkout-payments-panel > div:nth-child(2) > table td { min-width: 0 !important; white-space: nowrap; padding: 5px 8px; font-size: 11px; }
.checkout-payments-panel > div:nth-child(2) > table th:first-child,
.checkout-payments-panel > div:nth-child(2) > table td:first-child { width: 25px; }
.checkout-payments-panel > div:first-child { padding: 6px 10px; min-height: 29px; background: #f8fafc; }
.checkout-payments-panel > div:first-child svg { color: #0f172a !important; }
.checkout-payments-panel > div:last-child { justify-content: flex-end; padding: 5px 12px; min-height: 29px; background: #f8fafc; }
.checkout-payments-panel > div:last-child input { display: none; }
.checkout-payments-panel > div:last-child > span:last-child { color: #2563eb !important; font-size: 12px; }
/* Service table: exact 13-column structure from MÀN HÌNH HÓA ĐƠN.html. */
.checkout-services-panel table { table-layout: auto; width: 100%; }
.checkout-services-panel table th,
.checkout-services-panel table td { min-width: 0 !important; white-space: nowrap; padding: 5px 8px; font-size: 11px; }
.checkout-services-panel table th:first-child,
.checkout-services-panel table td:first-child { width: 25px !important; }
.checkout-service-title { padding: 6px 10px !important; min-height: 29px; }
.checkout-service-title svg,
.checkout-service-title i { color: #0f172a; }
/* Booking list: exact 5-column layout from the reference HTML. */
.checkout-bookings-heading { height: 29px; flex: 0 0 29px; padding: 6px 10px !important; background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.checkout-bookings-panel table { table-layout: auto; width: 100%; border-collapse: collapse; }
.checkout-bookings-panel table th { padding: 6px 6px; background: #f8fafc; color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.checkout-bookings-panel table td { padding: 6px 6px; font-size: 11px; border-bottom: 1px solid #cbd5e1; white-space: nowrap; }
.checkout-bookings-panel table th:first-child,
.checkout-bookings-panel table td:first-child { width: 25px !important; }
.checkout-bookings-panel table tr:hover { background: #f1f5f9; }
/* Header: same 380px search/filter cluster and right actions as the reference. */
.checkout-header { height: 45px; padding: 8px 16px !important; }
.checkout-header > div:first-child { flex: 0 0 390px; width: 390px; max-width: 390px; }
.checkout-header > div:first-child > div:first-child { flex: 0 0 282px; width: 282px; }
.checkout-register-filter select { width: 108px; height: 28px; }
.checkout-header > div:last-child { gap: 12px; }
.checkout-header > div:last-child label { font-size: 11px; font-weight: 600; }
.checkout-registration-button { height: 28px; min-width: 107px; font-weight: 600; }
/* Reference layout: header is a separate full-width row; action sidebar starts below it. */
.checkout-shell { display: block !important; position: relative; }
.checkout-main { width: calc(100% - 170px); height: 100%; margin-right: 170px; }
.checkout-header { width: calc(100% + 170px); position: relative; z-index: 20; }
.checkout-actions { position: absolute !important; top: 45px; right: 0; bottom: 0; width: 170px !important; min-width: 170px; height: auto; z-index: 30; border-radius: 0 !important; }
.checkout-actions > button:first-child { display: none; }
/* Final header/sidebar dimensions copied from the reference HTML tokens. */
.checkout-header { height: 45px !important; min-height: 45px !important; padding: 8px 16px !important; background: #ffffff; border-bottom: 1px solid #cbd5e1; }
.checkout-header input[type="text"] { height: 28px; padding: 5px 10px 5px 30px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px; }
.checkout-filter-select { height: 28px !important; padding-top: 5px !important; padding-bottom: 5px !important; border-radius: 4px !important; }
.checkout-registration-button { height: 28px !important; padding: 5px 10px !important; border-radius: 4px !important; font-size: 11px; }
.checkout-actions { top: 48px !important; right: 2px !important; bottom: 0 !important; width: 170px !important; min-width: 170px !important; padding: 8px !important; background: #1e293b !important; border: 1px solid #475569 !important; border-radius: 6px !important; box-shadow: -2px 0 8px rgba(0,0,0,.15); overflow-x: hidden; overflow-y: auto; }
.checkout-actions .checkout-action-menu { padding-top: 0 !important; gap: 8px !important; }
.checkout-actions .checkout-action-menu > div { padding: 2px 0; border-bottom: 1px solid #334155; }
.checkout-actions .checkout-action-menu > div:last-child { border-bottom: 0; }
.checkout-actions .menu-title { font-size: 9px; color: #94a3b8; padding: 4px 8px 2px; }
.checkout-actions button { border-radius: 4px; }
/* Correct 3-column grid: header spans all columns, sidebar occupies column 3 below it. */
.checkout-shell { display: grid !important; grid-template-columns: minmax(360px, 380px) minmax(0, 1fr) 170px; grid-template-rows: 45px minmax(0, 1fr); width: 100%; height: calc(100vh - 48px); min-width: 1024px; }
.checkout-main { display: contents !important; width: auto !important; height: auto !important; margin: 0 !important; }
.checkout-header { grid-column: 1 / 4; grid-row: 1; width: auto !important; height: 45px !important; min-width: 0; }
.checkout-left-pane { grid-column: 1; grid-row: 2; min-width: 0; }
.checkout-billing-pane { grid-column: 2; grid-row: 2; min-width: 0; }
.checkout-actions { position: static !important; grid-column: 3; grid-row: 2; align-self: stretch; width: auto !important; min-width: 0 !important; height: calc(100% - 3px); margin: 3px 2px 0 0; }
/* Registration/Folio panels copied from the reference sidebar-detail sections. */
.checkout-info-panel { background: #f8fafc !important; font-size: 10px; }
.checkout-info-heading { height: auto; min-height: 24px; padding: 4px 8px !important; border-bottom: 0 !important; color: #64748b; }
.checkout-info-title,
.checkout-folio-title { display: flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.checkout-info-title i,
.checkout-folio-title i { color: #64748b; }
.checkout-info-panel > div:nth-child(2) { padding: 4px 8px !important; gap: 2px 4px; }
.checkout-info-panel > div:nth-child(2) label { font-size: 9px; color: #64748b; display: block; }
.checkout-info-panel > div:nth-child(2) span { font-size: 11px; font-weight: 600; color: #0f172a; }
.checkout-info-panel > div:nth-child(2) textarea { height: 28px; padding: 2px 6px; font-size: 11px; border: 1px solid #cbd5e1; border-radius: 4px; }
.checkout-info-panel > div:nth-child(2) label i { margin-right: 3px; }
.checkout-folio-section { padding: 4px 8px !important; background: #f8fafc; border-top: 1px solid #cbd5e1; }
.checkout-folio-title { margin-bottom: 2px; }
.checkout-folio-section > div:last-child { gap: 4px; }
.checkout-folio-card { height: 38px !important; padding: 3px 6px !important; background: #ffffff !important; border: 1px solid #cbd5e1; border-radius: 4px; }
.checkout-folio-card div:first-child { font-size: 10px; font-weight: 700; color: #0f172a; }
.checkout-folio-card div:last-child { font-size: 15px; font-weight: 700; line-height: 1; text-align: right; color: #2563eb; }
.checkout-folio-card:first-child div:last-child { color: #ef4444; }
/* Filter popup matching .filter-dropdown in MÀN HÌNH HÓA ĐƠN.html. */
.checkout-register-filter { position: relative !important; }
.checkout-filter-dropdown { position: absolute; top: 40px; left: 0; width: 380px; padding: 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,.15); z-index: 1000; }
.checkout-filter-tabs { display: flex; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 10px; background: #f8fafc; }
.checkout-filter-tab { flex: 1; padding: 5px 0; border: 0; border-right: 1px solid #cbd5e1; background: transparent; color: #0f172a; font-size: 10px; font-weight: 600; }
.checkout-filter-tab:last-child { border-right: 0; }
.checkout-filter-tab.active { background: #2563eb; color: #fff; }
.checkout-filter-box { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 10px; }
.checkout-filter-box-title { margin-bottom: 4px; color: #0f172a; font-size: 10px; font-weight: 700; }
.checkout-filter-scope { width: 100%; height: 29px; margin-bottom: 8px; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; font-size: 11px; }
.checkout-filter-date-row { display: flex; flex-direction: column; gap: 6px; }
.checkout-filter-date-label { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.checkout-filter-date-inputs { display: flex; gap: 4px; }
.checkout-filter-date-wrap { display: flex; align-items: center; flex: 1; min-width: 0; padding: 3px 4px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; }
.checkout-filter-date-wrap input { width: 100%; min-width: 0; height: 22px !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; font-size: 10px !important; }
.checkout-filter-date-wrap i { margin-left: 2px; color: #10b981; font-size: 11px; }
.checkout-filter-actions { display: flex; justify-content: flex-end; gap: 6px; padding-top: 8px; border-top: 1px solid #f1f5f9; }
.checkout-filter-actions button { padding: 4px 10px; border: 0; border-radius: 4px; background: #2563eb; color: #fff; font-size: 10px; font-weight: 600; }
/* Match the sample: filter popup is anchored to the whole search-bar, not the button. */
.checkout-header > div:first-child { position: relative; }
.checkout-register-filter { position: static !important; }
.checkout-filter-dropdown { top: 40px; left: 0; width: 380px; }
.checkout-filter-button { height: 28px; min-width: 108px; justify-content: center; }
.checkout-filter-tabs { height: 34px; }
.checkout-filter-tab { height: 32px; }
/* Final pixel alignment with the supplied MÀN HÌNH HÓA ĐƠN.html search-bar. */
.checkout-header > div:first-child { flex: 0 0 380px !important; width: 380px !important; max-width: 380px !important; position: relative !important; }
.checkout-header > div:first-child > div:first-child { flex: 1 1 auto !important; width: auto !important; min-width: 0 !important; }
.checkout-header > div:first-child > div:first-child > input { height: 28px !important; padding: 5px 10px 5px 30px !important; font-size: 12px !important; }
.checkout-filter-button { flex: 0 0 auto !important; height: 28px !important; padding: 5px 10px !important; border-radius: 4px !important; font-size: 11px !important; }
.checkout-filter-dropdown { top: 40px !important; left: 0 !important; width: 380px !important; padding: 10px !important; border-radius: 8px !important; }
.checkout-filter-tabs { height: auto !important; margin-bottom: 10px !important; }
.checkout-filter-tab { height: auto !important; min-height: 32px !important; padding: 5px 0 !important; font-size: 10px !important; }
.checkout-filter-box { padding: 8px 10px !important; margin-bottom: 10px !important; }
.checkout-filter-scope { height: auto !important; padding: 4px 8px !important; margin-bottom: 8px !important; }
.checkout-filter-date-wrap input { height: auto !important; padding: 0 !important; font-size: 10px !important; }
.checkout-filter-date-wrap i:last-child { color: #64748b !important; font-size: 10px !important; }
.checkout-filter-actions button { padding: 4px 10px !important; font-size: 10px !important; }
/* Native date controls and the compact blue scope selector from the reference popup. */
.checkout-filter-scope { width: 136px !important; background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; font-weight: 700; cursor: pointer; }
.checkout-filter-scope option { background: #fff; color: #0f172a; font-weight: 400; }
.checkout-filter-date-wrap { cursor: pointer; }
.checkout-filter-date-wrap input { cursor: pointer; color: #0f172a; }
/* Keep the reference green calendar/copy icons and hide the duplicate native date icon. */
.checkout-filter-date-wrap input[type="date"]::-webkit-calendar-picker-indicator { opacity: 0 !important; width: 0 !important; margin: 0 !important; padding: 0 !important; }
.checkout-filter-date-wrap input[type="date"] { appearance: none; -webkit-appearance: none; }
.checkout-filter-date-wrap i.fa-copy { cursor: pointer; }
.checkout-filter-date-wrap i.fa-copy:hover { color: #2563eb !important; }
</style>
