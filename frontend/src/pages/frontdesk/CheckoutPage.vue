<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
  ArrowRightLeft
} from '@lucide/vue'
import { fetchBookings, transferBookingRoomServicesFolio, splitBookingRoomServicesFolio, fetchQuickTransferCandidates, quickTransferBookingRoomServices, cancelBookingRoomServices, transferPaymentFolio, splitPayment, transferPayments } from '@/services/booking-service'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Sidebar collapse state
const isSidebarCollapsed = ref(false)
const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

// UI State
const searchQuery = ref('')
const registerFilter = ref('current')
const showAllGuestsInRoom = ref(false)

const isNoPost = ref(false)

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

const loadCheckoutBookings = async () => {
  isLoading.value = true
  try {
    const res = await fetchBookings({ status: '0,1' })
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
              .filter(s => s.service_code !== 'RM' && s.service_code !== 'RMS' && !(s.service_name && s.service_name.includes('Tiền phòng')))
              .reduce((acc, s) => {
                const itemTotal = Number(s.total_amount) || (Number(s.quantity || 1) * (Number(s.rate) || Number(s.price) || Number(s.amount) || 0))
                return acc + itemTotal
              }, 0)
          }

          let postedRoomCharge = 0
          let hasBaseRM = false

          if (r.services && r.services.length > 0) {
            r.services.forEach(s => {
              const isRM = s.service_code === 'RM' || (s.service_name && s.service_name.includes('Tiền phòng'))
              const isRMS = s.service_code === 'RMS'
              if (isRM || isRMS) {
                const itemTotal = Number(s.total_amount) || (Number(s.quantity || 1) * Number(s.rate || 0))
                postedRoomCharge += itemTotal
                if (isRM) hasBaseRM = true
              }
            })
          }

          let baseRoomCharge = 0
          if (!hasBaseRM) {
            const roomRateVal = Number(r.room_rate) || Number(r.price) || Number(r.rate) || 0
            if (roomRateVal > 0) {
              const qtyDays = Number(r.ActutalNumOfDays) || 1
              baseRoomCharge = Number(r.total_amount) || (roomRateVal * qtyDays)
            }
          }

          const roomChargeTotal = postedRoomCharge + baseRoomCharge
          const roomSvc = masterSend ? extraSvc : (extraSvc + roomChargeTotal)
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

          roomItems.push({
            id: `R${r.id || b.id}`,
            roomId: r.id,
            code: code,
            roomNumber: roomNo,
            guestName: roomGuests[0].name,
            allGuests: roomGuests,
            primaryGuestId: roomGuests.find(guest => guest.isPrimary)?.id || roomGuests[0].id,
            serviceAmount: roomSvc,
            extraServiceAmount: extraSvc,
            roomChargeAmount: roomChargeTotal,
            paidAmount: roomPaidAmount,
            checked: false,
            rawRoom: r
          })
        })
      }

      const masterBills = (b.master_service_bills && b.master_service_bills.length > 0)
        ? b.master_service_bills.filter(sb => !sb.RentalRoomId1)
        : (b.service_bills ? b.service_bills.filter(sb => !sb.RentalRoomId1) : [])

      const masterServiceTotal = masterBills
        .filter(bill => Number(bill.Edit) !== 1 && (bill.Status === undefined || Number(bill.Status) === 1))
        .reduce((total, bill) => total + (Number(bill.Amount) || 0), 0)
      const masterDepositTotal = (b.payments || [])
        .filter(payment => payment.pack2 === 'DPR' && Number(payment.edit_flag) === 0 && !payment.deleted_at && !payment.booking_room_id)
        .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)

      // Master Header: Tổng toàn bộ tiền cọc/thanh toán của cả đoàn (cả cọc chung lẫn cọc riêng từng phòng)
      const masterPaidAmount = (b.payments || [])
        .filter(p => (!p.edit_flag || Number(p.edit_flag) === 0) && !p.deleted_at)
        .reduce((sum, p) => sum + Number(p.amount || 0), 0)

      formatted.push({
        id: `B${b.id}`,
        bookingId: b.id,
        code: code,
        name: mainGuestName, // Tên nhóm / Tên booking
        // Master chỉ đại diện booking; cộng dồn toàn bộ tiền phòng + dịch vụ lẻ của từng phòng + master direct bills
        totalService: masterSend
          ? roomItems.reduce((total, room) => total + room.roomChargeAmount + room.extraServiceAmount, 0) + masterServiceTotal
          : masterServiceTotal,
        paidAmount: masterPaidAmount,
        arrivalDate: b.arrival_date || '',
        departureDate: b.departure_date || '',
        note: b.note || '',
        checked: false,
        roomItems: roomItems,
        rawBooking: b
      })
    })

    // Lưu toàn bộ danh sách cho ô Tìm kiếm Popup
    allBookingsList.value = formatted
  } catch (err) {
    console.error('Lỗi khi nạp danh sách booking cho Checkout:', err)
  } finally {
    isLoading.value = false
  }
}

const handleServiceAdded = async (data) => {
  showAddServiceModal.value = false
  showHousekeepingServiceModal.value = false
  selectedPaymentIds.value = []

  const currentBookingId = selectedBooking.value ? selectedBooking.value.bookingId : null
  const currentRoomId = selectedRoomItem.value ? selectedRoomItem.value.roomId : null
  const currentGuestId = selectedGuestId.value
  const currentGuestName = selectedGuest.value

  await loadCheckoutBookings()

  if (currentBookingId) {
    const freshB = allBookingsList.value.find(b => b.bookingId === currentBookingId)
    if (freshB) {
      displayedBookingsList.value = [freshB]
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

  uiStore.showToast('Đã thêm dịch vụ thành công!', 'success')
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

  const processServiceItem = (s, idx, defaultDesc, roomNo = '') => {
    const rateVal = Number(s.rate) || Number(s.price) || Number(s.amount) || 0
    const qtyVal = Number(s.quantity) || Number(s.qty) || 1
    const totalVal = Number(s.total_amount) || (rateVal * qtyVal)
    const codeVal = s.service_code || (s.hotel_service && s.hotel_service.code) || (s.service_name === 'Tiền phòng' ? 'RM' : 'DV')

    let descVal = s.note || s.description || defaultDesc
    descVal = String(descVal).replace(/^Post bill\s+/i, '')
    if (codeVal === 'RM' || s.service_name === 'Tiền phòng') {
      descVal = `Dịch vụ phòng nghỉ ${roomNo || s.room_number || ''}`.trim()
    }

    return {
      id: s.id || `S${idx}`,
      serviceDate: s.service_date || s.created_at || null,
      createdAt: s.created_at || null,
      dateTime: formatServiceDateTime(s.service_date, s.created_at, s.open_time || s.openTime || s.service_bill?.OpenTime || s.serviceBill?.OpenTime),
      serviceCode: codeVal,
      serviceBillId: s.service_bill_id || null,
      serviceName: s.service_name || s.name || (s.hotel_service && s.hotel_service.name) || 'Dịch vụ buồng phòng',
      description: descVal,
      department: s.department || 'FO',
      amount: rateVal,
      quantity: qtyVal,
      totalAmount: totalVal,
      unit: s.unit || (codeVal === 'RM' ? 'Đêm' : 'Cái'),
      paymentCode: s.payment_code || '',
      folio: Number(s.folio || 1),
      tax: Number(s.tax) || 0,
      serviceCharge: Number(s.service_charge) || 0,
      invoiceCode: s.invoice_code || '',
      vatNo: s.vat_no || '',
      accounting: s.accounting || 'Đã ghi',
      userName: s.created_by || s.user_name || 'Admin'
    }
  }

  const addRoomChargeIfMissing = (rawR, targetArray) => {
    const hasRoomCharge = rawR.services && rawR.services.some(s => (s.service_code === 'RM' || (s.service_name && s.service_name.includes('Tiền phòng'))))
    if (!hasRoomCharge) {
      const rateVal = Number(rawR.room_rate) || Number(rawR.price) || Number(rawR.rate) || 0
      if (rateVal > 0) {
        const qtyVal = Number(rawR.ActutalNumOfDays) || 1
        const totalVal = Number(rawR.total_amount) || (rateVal * qtyVal)
        targetArray.unshift({
          id: `RM-${rawR.id}`,
          dateTime: formatServiceDateTime(rawR.arrival_date || selectedBooking.value?.arrivalDate || new Date(), rawR.created_at, rawR.open_time),
          serviceCode: 'RM',
          serviceName: 'Tiền phòng',
          description: `Dịch vụ phòng nghỉ ${rawR.room_number || ''}`,
          department: 'FO',
          amount: rateVal,
          quantity: qtyVal,
          totalAmount: totalVal,
          unit: 'Đêm',
          paymentCode: '',
          folio: 1,
          tax: 0,
          serviceCharge: 0,
          invoiceCode: '',
          vatNo: '',
          accounting: 'Đã ghi',
          userName: 'System'
        })
      }
    }
  }

  const processServiceBillRecord = (sb, idx) => {
    const qtyVal = Number(sb.Quantity) || 1
    const totalVal = Number(sb.Amount) || 0
    const rateVal = qtyVal > 0 ? totalVal / qtyVal : totalVal
    const codeVal = sb.ServiceId || 'DV'
    const descVal = sb.DescriptionServive || sb.ServiceId || 'Dịch vụ FO'

    return {
      id: `SB-${sb.Ma || idx}`,
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
      paymentCode: '',
      folio: Number(sb.Folio || 1),
      tax: Number(sb.Tax) || 0,
      serviceCharge: Number(sb.ServiceCharge) || 0,
      invoiceCode: '',
      vatNo: '',
      accounting: 'Đã ghi',
      userName: sb.CreatedUser || sb.Username || 'Admin'
    }
  }

  // Nếu chọn phòng lẻ -> Ẩn danh sách dịch vụ góc dưới bên trái (trả về rỗng theo yêu cầu)
  if (selectedRoomItem.value) {
    return []
  }

  // Khi chọn Phiếu Tổng (GAL1 / Master Booking): Tập hợp TOÀN BỘ dịch vụ + tiền phòng của tất cả các phòng
  if (selectedBooking.value) {
    const rawB = selectedBooking.value.rawBooking

    // 1. Dịch vụ post trực tiếp cho Master Booking Header
    const masterBills = (rawB?.master_service_bills && rawB.master_service_bills.length > 0)
      ? rawB.master_service_bills.filter(sb => !sb.RentalRoomId1)
      : (rawB?.service_bills ? rawB.service_bills.filter(sb => !sb.RentalRoomId1) : [])

    masterBills.forEach((sb, idx) => {
      if (Number(sb.Edit) === 1 || (sb.Status !== undefined && Number(sb.Status) !== 1)) return
      services.push(processServiceBillRecord(sb, `master-${idx}`))
    })

    // 2. Tập hợp tất cả dịch vụ + tiền phòng từ từng phòng thuộc đoàn vào Phiếu Tổng
    if (selectedBooking.value.roomItems) {
      selectedBooking.value.roomItems.forEach(rItem => {
        const rawR = rItem.rawRoom
        const roomNo = rItem.roomNumber
        if (rawR) {
          if (rawR.services && Array.isArray(rawR.services)) {
            rawR.services.forEach((s, idx) => {
              services.push(processServiceItem(withServiceBillTime(rawR, s), `${rItem.id}-${idx}`, `Phòng ${roomNo}`, roomNo))
            })
          }
          addRoomChargeIfMissing(rawR, services)
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

const folioTotal = (folio) => {
  if (folio === 'A') return [1, 2, 3].reduce((total, currentFolio) => total + folioTotal(currentFolio), 0)
  const serviceTotal = servicesList.value
    .filter(service => String(service.folio) === String(folio))
    .reduce((total, service) => total + (Number(service.totalAmount) || 0), 0)
  return serviceTotal - folioDepositTotal(folio)
}

const serviceGroups = computed(() => {
  const groups = new Map()
  visibleServices.value.forEach(service => {
    const meta = getServiceGroup(service)
    // Mỗi lần post bill tạo một thẻ/dòng riêng; chỉ các sản phẩm được gửi cùng lúc
    // (cùng created_at) mới nằm chung trong một hóa đơn.
    const transfers = [...String(service.description || '').matchAll(/\(([^()]+)=>[^()]+\)/g)]
    const sourceKey = transfers.length
      ? transfers[0][1].trim()
      : `bill-${service.serviceBillId || service.createdAt || service.id}`
    const key = [meta.key, sourceKey, service.folio || 'A', service.department || 'FO'].join('|')
    if (!groups.has(key)) {
      groups.set(key, { id: key, ...meta, name: service.description || meta.name, dateTime: service.dateTime, department: service.department, folio: service.folio || 'A', paymentCode: service.paymentCode, totalAmount: 0, quantity: 0, tax: 0, serviceCharge: 0, items: [] })
    }
    const group = groups.get(key)
    group.items.push(service)
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

const canTransferServiceGroup = (group) => (
  Boolean(selectedRoomItem.value) &&
  group.items.every(item => Number.isInteger(Number(item.id)) && Number(item.id) > 0)
)

const selectedServiceItems = computed(() => servicesList.value.filter(service => selectedServiceIds.value.includes(Number(service.id))))
const selectedServiceGroups = computed(() => serviceGroups.value.filter(group => isServiceGroupSelected(group)))
const canTransferSelectedServices = computed(() => Boolean(selectedRoomItem.value) && selectedServiceItems.value.length > 0 && selectedServiceItems.value.every(service => service.serviceCode !== 'RM'))
const canSplitSelectedServices = computed(() => {
  if (!canTransferSelectedServices.value) return false
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
  && selectedServiceItems.value.every(service => service.serviceCode !== 'RM' && service.serviceBillId)
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

const isRoomCharge = (service) => (
  service.service_code === 'RM' || String(service.service_name || '').includes('Tiền phòng')
)

const missingRoomChargePreview = (room) => {
  const rawRoom = room.rawRoom || {}
  const hasRoomCharge = (rawRoom.services || []).some(isRoomCharge)
  const rate = Number(rawRoom.room_rate) || Number(rawRoom.price) || Number(rawRoom.rate) || 0
  if (hasRoomCharge || rate <= 0) return []
  const quantity = Number(rawRoom.ActutalNumOfDays) || 1
  return [{
    id: `RM-preview-${rawRoom.id || room.roomId}`,
    service_code: 'RM',
    service_name: 'Tiền phòng',
    service_date: rawRoom.arrival_date,
    department: 'FO',
    rate,
    quantity,
    total_amount: Number(rawRoom.total_amount) || (rate * quantity),
    unit: 'Đêm',
    folio: 1,
    created_by: 'System'
  }]
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

const roomTransferPreviewServices = (booking, room) => {
  const masterSend = isMasterRoomRateEnabled(booking)
  const roomServices = (room.rawRoom?.services || []).filter(service => {
    const roomCharge = isRoomCharge(service)
    const belongsToPrimaryGuest = roomCharge || !service.guest_id || String(service.guest_id) === String(room.primaryGuestId)
    return (!masterSend || !roomCharge) && belongsToPrimaryGuest
  })
  if (!masterSend) roomServices.push(...missingRoomChargePreview(room))
  return groupTransferPreviewServices(roomServices)
}

const masterTransferPreviewServices = (booking) => {
  if (!isMasterRoomRateEnabled(booking)) return []
  return groupTransferPreviewServices(booking.roomItems.flatMap(room => (
    (room.rawRoom?.services || []).filter(isRoomCharge).concat(missingRoomChargePreview(room))
  )))
}

const transferPreviewPayments = (booking, room = null) => (booking.rawBooking?.payments || [])
  .filter(payment => (
    payment.pack2 === 'DPR'
    && Number(payment.edit_flag) === 0
    && !payment.deleted_at
    && (room
      ? String(payment.booking_room_id) === String(room.roomId)
      : !payment.booking_room_id)
  ))
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

const isTransferEligibleRoom = (room) => [0, 1].includes(Number(room.rawRoom?.status))
const isTransferEligibleBooking = (booking) => [0, 1].includes(Number(booking.rawBooking?.status))

const transferDestinations = computed(() => allBookingsList.value.filter(isTransferEligibleBooking).flatMap(booking => {
  const roomDestinations = booking.roomItems.filter(isTransferEligibleRoom).map(room => ({
    key: `room-${room.roomId}`,
    bookingId: booking.bookingId,
    roomId: room.roomId,
    guestId: room.primaryGuestId || null,
    kind: 'room',
    bookingCode: booking.code,
    bookingName: booking.name,
    roomNumber: room.roomNumber,
    guestName: room.guestName,
    label: `Phòng ${room.roomNumber} - ${room.guestName} (${booking.code})`,
    services: roomTransferPreviewServices(booking, room),
    payments: transferPreviewPayments(booking, room)
  }))
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
}) )

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

  if (group.code === 'RM') {
    roomAdjustment.value = {
      serviceDate: item.serviceDate,
      folio: item.folio || group.folio || 1,
      amount: item.totalAmount || group.totalAmount,
      description: item.description || group.name
    }
    showAddServiceModal.value = true
    return
  }

  housekeepingAdjustment.value = {
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
  if (!hasCurrentSelectedRoom.value) {
    uiStore.showToast('Vui lòng chọn phòng nhận dịch vụ.', 'warning')
    return
  }
  showQuickTransferBillModal.value = true
  quickTransferLoadingText.value = 'Đang tải danh sách dịch vụ...'
  isServiceOperationLoading.value = true
  try {
    const targetId = selectedRoomItem.value.roomId
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
  if (!selectedRoomItem.value || !billIds.length) return
  quickTransferLoadingText.value = 'Đang chuyển bill nhanh...'
  isServiceOperationLoading.value = true
  uiStore.showToast('Đang chuyển bill nhanh...', 'info', 1500)
  try {
    const targetId = selectedRoomItem.value.roomId
    const response = await quickTransferBookingRoomServices(targetId, { bill_ids: billIds })
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

  try {
    await transferBookingRoomServicesFolio(selectedRoomItem.value.roomId, {
      service_ids: group.items.map(item => Number(item.id)),
      folio: targetFolio,
    })
    activeFolioTab.value = String(targetFolio)
    await handleServiceAdded()
  } catch (error) {
    console.error('Không thể chuyển Folio dịch vụ:', error)
  } finally {
    handleServiceDragEnd()
  }
}

const totalServiceAmount = computed(() => {
  return visibleServices.value.reduce((acc, s) => acc + (s.totalAmount || (s.amount * s.quantity)), 0)
})

const isSubGuestSelected = computed(() => {
  if (!showAllGuestsInRoom.value || !selectedRoomItem.value || !selectedRoomItem.value.allGuests || selectedRoomItem.value.allGuests.length <= 1) {
    return false
  }
  const firstGuestId = selectedRoomItem.value.allGuests[0]?.id
  return String(selectedGuestId.value) !== String(firstGuestId)
})

const paymentsList = computed(() => {
  if (!selectedBooking.value || isSubGuestSelected.value) return []

  const payments = []
  const rawB = selectedBooking.value.rawBooking
  const currentRoomId = selectedRoomItem.value?.roomId || selectedRoomItem.value?.rawRoom?.id || null

  if (rawB && rawB.payments && Array.isArray(rawB.payments) && rawB.payments.length > 0) {
    const filteredPayments = rawB.payments.filter(p => {
      if (!p || p.deleted_at || (p.edit_flag !== undefined && Number(p.edit_flag) !== 0)) return false
      // Nếu chọn dòng Phiếu Tổng (không chọn phòng lẻ): hiển thị tất cả cọc của booking
      if (!currentRoomId) return true
      // Nếu chọn phòng lẻ: hiển thị cọc chung (không có booking_room_id) VÀ cọc riêng của đúng phòng này
      if (!p.booking_room_id) return true
      return String(p.booking_room_id) === String(currentRoomId)
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

const folioDepositTotal = (folio) => paymentsList.value
  .filter(payment => String(payment.folio) === String(folio))
  .reduce((total, payment) => total + (Number(payment.amount) || 0), 0)

const totalPaymentAmount = computed(() => {
  return visiblePaymentsList.value.reduce((acc, p) => acc + p.amount, 0)
})

const selectedRoomGuests = computed(() => selectedRoomItem.value?.allGuests || [])
const hasCurrentSelectedRoom = computed(() => {
  const roomId = selectedRoomItem.value?.roomId
  const bookingId = selectedBooking.value?.bookingId
  return Boolean(roomId && bookingId && displayedBookingsList.value.some(booking => (
    booking.bookingId === bookingId
    && booking.roomItems?.some(room => room.roomId === roomId)
  )))
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
}

const filteredSearchBookings = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return allBookingsList.value
  return allBookingsList.value.filter(b => {
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

const handleClickOutside = (e) => {
  if (searchContainerRef.value && !searchContainerRef.value.contains(e.target)) {
    showSearchDropdown.value = false
  }
}

onMounted(() => {
  loadCheckoutBookings()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="flex h-[calc(100vh-48px)] bg-[#ecefe6] text-xs text-gray-700 select-none overflow-hidden font-sans relative">
    <LoadingOverlay :show="isLoading || isServiceOperationLoading" />

    <!-- LEFTSIDE TOOLBAR (Cột nút chức năng dọc bên trái - Hỗ trợ Thu gọn/Mở rộng) -->
    <aside 
      :class="[
        isSidebarCollapsed ? 'w-12' : 'w-44',
        'bg-[#f4f5f0] border-r border-gray-300 flex flex-col justify-between p-1 transition-all duration-200 shrink-0 shadow-sm relative'
      ]"
    >
      <!-- Collapse / Expand Toggle Button -->
      <button 
        @click="toggleSidebar"
        class="absolute -right-3 top-2 bg-white border border-gray-300 rounded-full p-0.5 shadow hover:bg-gray-100 z-30 text-gray-600"
        :title="isSidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      >
        <ChevronRight v-if="isSidebarCollapsed" class="w-3.5 h-3.5" />
        <ChevronLeft v-else class="w-3.5 h-3.5" />
      </button>

      <div class="space-y-0.5 overflow-y-auto overflow-x-hidden pt-4 text-xs">
        <!-- Thêm dịch vụ -->
        <button 
          @click="showAddServiceModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thêm dịch vụ' : ''"
        >
          <Plus class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thêm dịch vụ</span>
        </button>

        <!-- Thêm dịch vụ BP -->
        <button 
          @click="openAddHousekeepingService"
          :disabled="!selectedRoomItem"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded transition-colors border border-transparent text-xs"
          :class="[selectedRoomItem ? 'hover:bg-white text-gray-700 hover:border-gray-300 cursor-pointer' : 'opacity-40 pointer-events-none text-gray-400']"
          :title="isSidebarCollapsed ? 'Thêm dịch vụ BP' : ''"
        >
          <PlusSquare class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thêm dịch vụ BP</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- Tách dịch vụ -->
        <button 
          @click="openSplitAction"
          :disabled="!(canSplitSelectedServices || canSplitSelectedDeposit)"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded border border-transparent px-2 py-1 text-xs text-gray-700 transition-all hover:border-gray-300 hover:bg-white active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
          :class="!(canSplitSelectedServices || canSplitSelectedDeposit) ? 'opacity-40 pointer-events-none text-gray-400' : ''"
          :title="isSidebarCollapsed ? (hasSelectedDeposit ? 'Tách cọc' : 'Tách dịch vụ') : ''"
        >
          <Scissors class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">{{ hasSelectedDeposit ? 'Tách cọc' : 'Tách dịch vụ' }}</span>
        </button>

        <!-- Chuyển dịch vụ / cọc -->
        <button
          @click="hasSelectedDeposit ? openTransferPaymentModal() : openTransferServiceModal()"
          :disabled="hasSelectedDeposit ? !canTransferSelectedDeposit : !canTransferSelectedServices"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded border border-transparent px-2 py-1 text-xs text-gray-700 transition-all hover:border-gray-300 hover:bg-white active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
          :class="(hasSelectedDeposit ? !canTransferSelectedDeposit : !canTransferSelectedServices) ? 'opacity-40 pointer-events-none text-gray-400' : ''"
          :title="isSidebarCollapsed ? (hasSelectedDeposit ? 'Chuyển cọc' : 'Chuyển dịch vụ') : ''"
        >
          <ArrowRightLeft class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">{{ hasSelectedDeposit ? 'Chuyển cọc' : 'Chuyển dịch vụ' }}</span>
        </button>

        <!-- Tập hợp DV -->
        <button 
          @click="openQuickTransferBillModal"
          :disabled="!hasCurrentSelectedRoom || isServiceOperationLoading"
          :class="hasCurrentSelectedRoom && !isServiceOperationLoading ? 'text-gray-700 hover:bg-white hover:border-gray-300 cursor-pointer' : 'text-gray-400 opacity-50 cursor-not-allowed'"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Tập hợp DV' : ''"
        >
          <Layers :class="hasCurrentSelectedRoom ? 'text-gray-600' : 'text-gray-400'" class="w-3.5 h-3.5 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Tập hợp DV</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- In hóa đơn với Dropdown Submenu -->
        <div class="relative">
          <button 
            @click="showInvoiceMenu = !showInvoiceMenu"
            class="w-full flex items-center justify-between px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
            :title="isSidebarCollapsed ? 'In hóa đơn' : ''"
          >
            <div class="flex items-center gap-1.5 truncate">
              <Printer class="w-3.5 h-3.5 text-gray-600 shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">In hóa đơn</span>
            </div>
            <ChevronRight v-if="!isSidebarCollapsed" class="w-3 h-3 text-gray-400 shrink-0" />
          </button>

          <!-- Dropdown Sub-menu Floating -->
          <div 
            v-if="showInvoiceMenu" 
            class="absolute left-full top-0 ml-1 w-32 bg-white border border-gray-300 rounded shadow-lg z-50 py-1 text-xs"
          >
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-gray-700">Hiện giá</button>
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-gray-700">Không hiện giá</button>
            <button class="w-full text-left px-2.5 py-1 hover:bg-sky-50 text-sky-600 font-semibold border-t border-gray-100">In Bill</button>
          </div>
        </div>

        <!-- In VAT -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'In VAT' : ''"
        >
          <FileText class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">In VAT</span>
        </button>

        <!-- Hủy VAT -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50 text-red-600 transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Hủy VAT' : ''"
        >
          <FileX class="w-3.5 h-3.5 text-red-500 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate text-red-600">Hủy VAT</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- Xóa dịch vụ -->
        <button 
          @click="openCancelServiceModal"
          :disabled="!canOpenCancelServiceModal || isServiceOperationLoading"
          :class="canOpenCancelServiceModal && !isServiceOperationLoading ? 'hover:bg-red-50 text-red-600 cursor-pointer' : 'text-gray-400 cursor-not-allowed opacity-60'"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Xóa dịch vụ' : ''"
        >
          <Trash2 :class="canOpenCancelServiceModal ? 'text-red-500' : 'text-gray-400'" class="w-3.5 h-3.5 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Xóa dịch vụ</span>
        </button>

        <!-- Xóa thanh toán -->
        <button 
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-red-50 text-red-600 transition-colors border border-transparent text-xs"
          :title="isSidebarCollapsed ? 'Xóa thanh toán' : ''"
        >
          <RotateCcw class="w-3.5 h-3.5 text-red-500 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate text-red-600">Xóa thanh toán</span>
        </button>

        <div class="border-t border-gray-300 my-1"></div>

        <!-- Thanh toán trước -->
        <button 
          @click="showPrepaymentModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thanh toán trước' : ''"
        >
          <CreditCard class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thanh toán trước</span>
        </button>

        <!-- Thanh toán -->
        <button 
          @click="showPaymentModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Thanh toán' : ''"
        >
          <CreditCard class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Thanh toán</span>
        </button>

        <!-- Lọc -->
        <button 
          @click="showFilterServiceModal = true"
          class="w-full flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white text-gray-700 transition-colors border border-transparent hover:border-gray-300 text-xs"
          :title="isSidebarCollapsed ? 'Lọc' : ''"
        >
          <Filter class="w-3.5 h-3.5 text-gray-600 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Lọc</span>
        </button>
      </div>

      <!-- Bottom Button: Trả phòng -->
      <div class="pt-1.5 border-t border-gray-300">
        <button 
          class="w-full flex items-center justify-center gap-1.5 px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium shadow-sm transition-colors text-xs"
          :title="isSidebarCollapsed ? 'Trả phòng' : ''"
        >
          <LogOut class="w-3.5 h-3.5 rotate-180 shrink-0" />
          <span v-if="!isSidebarCollapsed" class="truncate">Trả phòng</span>
        </button>
      </div>
    </aside>

    <!-- RIGHT MAIN SECTION -->
    <main class="flex-1 flex flex-col min-w-0 bg-[#e8ebe0] p-1.5 gap-1.5 overflow-hidden">

      <!-- TOP CONTROL BAR (Nằm trên cùng toàn chiều rộng, không thuộc panel nào) -->
      <div class="flex items-center justify-between gap-2 px-2 py-1 bg-[#f4f5f0] border border-gray-300 rounded shadow-xs text-xs">
        <div class="flex items-center gap-2 flex-1 max-w-xl">
          <!-- Search Input with Popup Dropdown (Khớp 100% Ảnh 1 & 2) -->
          <div ref="searchContainerRef" class="relative flex-1">
            <Search class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 z-10" />
            <input 
              v-model="searchQuery" 
              @focus="showSearchDropdown = true"
              @input="showSearchDropdown = true"
              type="text" 
              placeholder="Search" 
              class="w-full pl-7 pr-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" 
            />

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
          </div>

          <!-- Filter Dropdown -->
          <select 
            v-model="registerFilter"
            class="px-2 py-0.5 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500"
          >
            <option value="current">Đăng ký hiện tại</option>
            <option value="all">Tất cả đăng ký</option>
          </select>
        </div>

        <div class="flex items-center gap-3">
          <!-- Checkbox Xem tất cả khách trong phòng -->
          <label class="flex items-center gap-1.5 cursor-pointer text-gray-700 hover:text-gray-900 whitespace-nowrap text-xs">
            <input type="checkbox" v-model="showAllGuestsInRoom" class="rounded border-gray-300 text-sky-600 focus:ring-sky-500" />
            <span>Xem tất cả khách trong phòng</span>
          </label>
        </div>
      </div>

      <!-- TOP SPLIT SECTION (Bảng Đăng ký + Panel Thông tin) -->
      <div class="grid grid-cols-12 gap-1.5 h-[40%] min-h-0">

        <!-- TOP LEFT: Bảng Danh sách Đăng ký (7 cols) -->
        <div class="col-span-7 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Danh sách Phòng / Khách (Khớp chính xác Ảnh 2) -->
          <div class="flex-1 overflow-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1 w-7 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1 border-r border-gray-300 min-w-[80px] text-center">Mã ĐK</th>
                  <th class="p-1 border-r border-gray-300 min-w-[60px] text-center">Phòng</th>
                  <th class="p-1 border-r border-gray-300 min-w-[140px] text-center">Tên nhóm/khách</th>
                  <th class="p-1 border-r border-gray-300 text-center min-w-[80px]">Tổng dịch vụ</th>
                  <th class="p-1 text-center min-w-[80px]">Đã thanh toán</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <template v-for="b in displayedBookingsList" :key="b.id">
                  <!-- Row 1: Header Booking (Hiển thị Mã ĐK & Tên nhóm - Khớp màu xanh nhạt Ảnh 2) -->
                  <tr 
                    @click="selectBookingHeader(b)"
                    :class="[
                      selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                      'cursor-pointer transition-colors'
                    ]"
                  >
                    <td class="p-1 text-center border-r border-gray-300">
                      <input type="checkbox" v-model="b.checked" @change="toggleBookingCheck(b)" @click.stop class="rounded border-gray-300 text-sky-600" />
                    </td>
                    <td class="p-1 border-r border-gray-300 font-bold" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-sky-600'">{{ b.code }}</td>
                    <td class="p-1 border-r border-gray-300 text-center font-bold"></td>
                    <td class="p-1 border-r border-gray-300 font-bold" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ b.name }}</td>
                    <td class="p-1 border-r border-gray-300 text-center font-mono" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ formatSummaryMoney(b.totalService) }}</td>
                    <td class="p-1 text-center font-mono" :class="selectedBooking && selectedBooking.id === b.id && !selectedRoomItem ? 'text-white' : 'text-gray-800'">{{ formatMoney(b.paidAmount) }}</td>
                  </tr>

                  <!-- Sub-rows: Room Items (Checkbox không tự động tick) -->
                  <template v-for="r in b.roomItems" :key="r.id">
                    <template v-if="showAllGuestsInRoom && r.allGuests.length > 1">
                      <tr 
                        v-for="(guest, gIdx) in r.allGuests"
                        :key="guest.id || gIdx"
                        @click="selectRoomItemRow(b, r, guest)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id && String(selectedGuestId) === String(guest.id) ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                          'cursor-pointer transition-colors'
                        ]"
                      >
                        <td class="p-1 text-center border-r border-gray-300 pl-4">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 border-r border-gray-300 text-center"></td>
                        <td class="p-1 border-r border-gray-300 text-center font-bold" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && String(selectedGuestId) === String(guest.id) }">{{ r.roomNumber }}</td>
                        <td
                          class="p-1 border-r border-gray-300 text-slate-900"
                          :class="gIdx === 0 ? 'font-bold' : 'font-normal'"
                        >{{ guest.name }}</td>
                        <td class="p-1 border-r border-gray-300 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && String(selectedGuestId) === String(guest.id) }">{{ gIdx === 0 ? formatSummaryMoney(r.serviceAmount) : '0' }}</td>
                        <td class="p-1 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id && String(selectedGuestId) === String(guest.id) }">{{ gIdx === 0 ? formatMoney(r.paidAmount) : '0' }}</td>
                      </tr>
                    </template>
                    <template v-else>
                      <tr 
                        @click="selectRoomItemRow(b, r)"
                        :class="[
                          selectedRoomItem && selectedRoomItem.id === r.id ? 'bg-[#7dd3fc] text-white font-medium' : 'hover:bg-gray-50 text-gray-800',
                          'cursor-pointer transition-colors'
                        ]"
                      >
                        <td class="p-1 text-center border-r border-gray-300 pl-4">
                          <input type="checkbox" v-model="r.checked" @click.stop class="rounded border-gray-300 text-sky-600" />
                        </td>
                        <td class="p-1 border-r border-gray-300 text-center"></td>
                        <td class="p-1 border-r border-gray-300 text-center font-bold" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ r.roomNumber }}</td>
                        <td class="p-1 border-r border-gray-300 font-bold text-slate-900">{{ r.guestName }}</td>
                        <td class="p-1 border-r border-gray-300 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ formatSummaryMoney(r.serviceAmount) }}</td>
                        <td class="p-1 text-center font-mono" :class="{ 'text-white': selectedRoomItem && selectedRoomItem.id === r.id }">{{ formatMoney(r.paidAmount) }}</td>
                      </tr>
                    </template>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TOP RIGHT: Information Panel & Folio Tabs (5 cols) -->
        <div class="col-span-5 bg-white rounded border border-gray-300 flex flex-col p-2 min-h-0 justify-between shadow-xs">
          
          <div class="space-y-1.5 text-xs">
            <!-- Header Controls & Title -->
            <div class="flex items-center justify-between gap-1 border-b border-gray-200 pb-1">
              <div class="flex items-center gap-2">
                <label class="flex items-center gap-1 cursor-pointer">
                  <input type="checkbox" v-model="isNoPost" class="rounded border-gray-300 text-sky-600" />
                  <span class="text-gray-600">No post</span>
                </label>
              </div>

              <!-- Button Electronic Signature -->
              <div class="flex items-center gap-1">
                <button class="bg-[#38bdf8] hover:bg-sky-500 text-white px-2 py-0.5 rounded flex items-center gap-1 text-xs font-medium shadow-xs transition-colors">
                  <span>HĐ chữ ký điện tử</span>
                </button>
                <button class="p-0.5 hover:bg-gray-100 rounded text-gray-500 border border-gray-300">
                  <RefreshCw class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Booking Sub-Header Code (Khớp chính xác Ảnh 2) -->
            <div class="bg-[#f0f2ea] border border-gray-200 rounded px-2 py-1 font-semibold text-gray-800 text-xs truncate">
              {{ selectedBooking ? `BK ${selectedBooking.code} - KHÁCH LẺ - ${selectedBooking.name}` : 'BK -- - KHÁCH LẺ - --' }}
            </div>

            <!-- Dates Range & Guest & Room Input (Khớp chính xác Ảnh 2) -->
            <div class="space-y-1.5">
              <!-- Date Range picker -->
              <div class="flex items-center gap-2 border border-gray-300 rounded px-2 py-0.5 bg-white text-xs">
                <span class="font-mono text-gray-700">{{ selectedBooking ? formatDate(selectedBooking.arrivalDate) : '-- / -- / ----' }}</span>
                <span class="text-gray-400">~</span>
                <span class="font-mono text-gray-700">{{ selectedBooking ? formatDate(selectedBooking.departureDate) : '-- / -- / ----' }}</span>
                <Calendar class="w-3.5 h-3.5 text-gray-400 ml-auto" />
              </div>

              <!-- Guest Select & Room Input -->
              <div class="grid grid-cols-12 gap-1.5">
                <div class="col-span-8 relative">
                  <button
                    type="button"
                    :disabled="!selectedRoomItem"
                    @click="showPanelGuestDropdown = !showPanelGuestDropdown"
                    class="w-full px-2 py-0.5 bg-white border border-gray-300 rounded text-left text-xs focus:outline-none focus:border-sky-500 disabled:bg-gray-100 disabled:text-gray-500 flex items-center justify-between"
                  >
                    <span class="truncate">{{ selectedGuest || 'Tên khách' }}</span>
                    <ChevronDown class="w-3.5 h-3.5 shrink-0 text-gray-500" />
                  </button>
                  <div
                    v-if="showPanelGuestDropdown && selectedRoomItem"
                    class="absolute z-30 top-full mt-1 w-full max-h-40 overflow-y-auto bg-white border border-gray-300 rounded shadow-lg"
                  >
                    <button
                      v-for="(guest, gIdx) in selectedRoomGuests"
                      :key="`${selectedRoomItem.id}-${guest.id || gIdx}`"
                      type="button"
                      @click="selectPanelGuest(guest)"
                      :class="[
                        String(selectedGuestId) === String(guest.id) ? 'bg-sky-100 text-sky-900' : 'text-gray-800 hover:bg-gray-100',
                        'w-full px-2 py-1 text-left text-xs'
                      ]"
                    >
                      {{ guest.name }}
                    </button>
                  </div>
                </div>
                <div class="col-span-4">
                  <input :value="roomNumber" type="text" placeholder="Phòng" readonly class="w-full px-2 py-0.5 bg-gray-100 border border-gray-300 rounded text-xs font-bold text-center text-gray-700" />
                </div>
              </div>

              <!-- Ghi chú Textarea -->
              <div>
                <label class="block text-gray-600 font-medium mb-0.5 text-xs">Ghi chú</label>
                <textarea 
                  :value="noteText"
                  readonly
                  rows="2" 
                  class="w-full p-1.5 bg-gray-100 border border-gray-300 rounded text-xs text-gray-700 resize-none"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Folio Tabs Selector (Bottom of Top-Right Panel) -->
          <div class="pt-1">
            <div class="text-xs font-semibold text-gray-500 mb-0.5">Folio</div>
            <div class="grid grid-cols-4 gap-1">
              <!-- Tab A (Active) -->
              <button 
                @click="activeFolioTab = 'A'"
                :class="[
                  activeFolioTab === 'A' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-200',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">A</span>
                <span class="text-xs font-mono mt-0.5">{{ formatSummaryMoney(folioTotal('A')) }}</span>
              </button>

              <!-- Tab 1 -->
              <button 
                @click="activeFolioTab = '1'"
                @dragover.prevent="draggedOverFolio = 1"
                @dragleave="draggedOverFolio = null"
                @drop.prevent="handleFolioDrop(1)"
                :class="[
                  activeFolioTab === '1' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  draggedOverFolio === 1 ? 'ring-2 ring-sky-500 ring-offset-1' : '',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">1</span>
                <span class="text-xs font-mono mt-0.5">{{ formatSummaryMoney(folioTotal(1)) }}</span>
              </button>

              <!-- Tab 2 -->
              <button 
                @click="activeFolioTab = '2'"
                @dragover.prevent="draggedOverFolio = 2"
                @dragleave="draggedOverFolio = null"
                @drop.prevent="handleFolioDrop(2)"
                :class="[
                  activeFolioTab === '2' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  draggedOverFolio === 2 ? 'ring-2 ring-sky-500 ring-offset-1' : '',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">2</span>
                <span class="text-xs font-mono mt-0.5">{{ formatSummaryMoney(folioTotal(2)) }}</span>
              </button>

              <!-- Tab 3 -->
              <button 
                @click="activeFolioTab = '3'"
                @dragover.prevent="draggedOverFolio = 3"
                @dragleave="draggedOverFolio = null"
                @drop.prevent="handleFolioDrop(3)"
                :class="[
                  activeFolioTab === '3' ? 'bg-[#7dd3fc] border-sky-400 text-sky-950 font-bold' : 'bg-[#e2e8f0] border-gray-300 text-gray-700 hover:bg-gray-300',
                  draggedOverFolio === 3 ? 'ring-2 ring-sky-500 ring-offset-1' : '',
                  'border rounded py-1 px-1.5 text-center transition-colors flex flex-col items-center justify-center'
                ]"
              >
                <span class="text-xs font-bold">3</span>
                <span class="text-xs font-mono mt-0.5">{{ formatSummaryMoney(folioTotal(3)) }}</span>
              </button>
            </div>
          </div>

        </div>

      </div>

      <!-- BOTTOM SPLIT SECTION (2 Bảng dữ liệu song song) -->
      <div class="grid grid-cols-12 gap-1.5 flex-1 min-h-0">

        <!-- BOTTOM LEFT: Bảng Chi tiết Dịch vụ / Phát sinh (6 cols) -->
        <div class="col-span-6 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center border-r border-gray-300">
                    <input
                      type="checkbox"
                      :checked="areAllServicesSelected"
                      :disabled="serviceSelectionIds.length === 0"
                      @change="toggleAllServiceSelection($event.target.checked)"
                      class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                  </th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[120px]">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[110px]">Dịch vụ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Bộ phận</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-center min-w-[55px]">SL</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[75px]">Đơn vị</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã TT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Folio</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[80px]">Tax</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Phí phục vụ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã HĐ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Số VAT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Kế toán</th>
                  <th class="px-2.5 py-1.5 min-w-[105px]">Người dùng</th>
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
                    'border-b border-gray-200 hover:bg-sky-50 text-gray-800 cursor-pointer transition-colors',
                    canTransferServiceGroup(group) ? 'cursor-grab active:cursor-grabbing' : ''
                  ]"
                  :title="canTransferServiceGroup(group) ? 'Kéo sang Folio khác' : 'Xem chi tiết hóa đơn'"
                >
                  <td class="px-2 py-1.5 text-center border-r border-gray-200">
                    <input
                      type="checkbox"
                      :checked="isServiceGroupSelected(group)"
                      @click.stop
                      @change="toggleServiceGroupSelection(group, $event.target.checked)"
                      class="rounded border-gray-300"
                    />
                  </td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono">{{ group.dateTime }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-bold text-sky-600">{{ group.code }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ group.name }} <span class="text-gray-400">({{ group.items.length }})</span></td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ group.department }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold">{{ formatSummaryMoney(group.totalAmount) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-mono">{{ group.quantity }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">VND</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono text-sky-600">{{ group.paymentCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-bold">
                    <span class="bg-[#8fd1d9] text-gray-900 px-2 py-0.5 rounded text-xs font-bold inline-block min-w-[20px]">{{ group.folio }}</span>
                  </td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono">{{ group.tax ? formatMoney(group.tax) : '' }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono">{{ group.serviceCharge ? formatMoney(group.serviceCharge) : '' }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ group.items[0]?.invoiceCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ group.items[0]?.vatNo }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center">
                    <input type="checkbox" disabled class="rounded border-gray-300 text-sky-600 cursor-not-allowed" />
                  </td>
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
          <div class="p-1.5 bg-[#f4f5f0] border-t border-gray-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input
                type="checkbox"
                :checked="areAllServicesSelected"
                :disabled="serviceSelectionIds.length === 0"
                @change="toggleAllServiceSelection($event.target.checked)"
                class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
              />
              <span>Tổng cộng</span>
            </div>
            <span class="font-mono text-xs pr-2">{{ formatSummaryMoney(totalServiceAmount) }}</span>
          </div>
        </div>

        <!-- BOTTOM RIGHT: Bảng Chi tiết Thanh toán (6 cols) -->
        <div class="col-span-6 bg-white rounded border border-gray-300 flex flex-col min-h-0 shadow-xs">
          <!-- Table Container -->
          <div class="flex-1 overflow-auto relative">
            <table class="w-full border-collapse text-left whitespace-nowrap text-xs">
              <thead class="bg-[#f0f2ea] sticky top-0 border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="px-2 py-1.5 w-8 text-center border-r border-gray-300">
                    <input
                      type="checkbox"
                      :checked="areAllPaymentsSelected"
                      :disabled="paymentSelectionIds.length === 0"
                      @change="toggleAllPaymentSelection($event.target.checked)"
                      class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
                    />
                  </th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[120px]">Ngày/giờ</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">Bộ phận</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[140px]">Mô tả</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[100px]">HTTT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 text-right min-w-[100px]">Số tiền</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[75px]">Đơn vị</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[70px]">Folio</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[85px]">Mã TT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[65px]">Xóa</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Số VAT</th>
                  <th class="px-2.5 py-1.5 border-r border-gray-300 min-w-[95px]">Kế toán</th>
                  <th class="px-2.5 py-1.5 min-w-[105px]">Người dùng</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in visiblePaymentsList"
                  :key="p.id"
                  :draggable="canTransferPayment(p)"
                  @dragstart="handlePaymentDragStart(p, $event)"
                  @dragend="handleServiceDragEnd"
                  :class="['border-b border-gray-200 hover:bg-gray-50 text-gray-800', canTransferPayment(p) ? 'cursor-grab active:cursor-grabbing' : '']"
                  :title="canTransferPayment(p) ? 'Kéo sang Folio khác' : 'Cọc đã dùng để thanh toán không thể chuyển Folio'"
                >
                  <td class="px-2 py-1.5 text-center border-r border-gray-200">
                    <input
                      type="checkbox"
                      :checked="isPaymentSelected(p)"
                      @click.stop
                      @change="togglePaymentSelection(p, $event.target.checked)"
                      class="rounded border-gray-300"
                    />
                  </td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono">{{ p.dateTime }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.department }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.description }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-medium text-emerald-600">{{ p.paymentMethod }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-right font-mono font-bold text-emerald-700">{{ formatMoney(p.amount) }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.unit }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center font-bold"><span class="inline-block min-w-[20px] rounded bg-[#8fd1d9] px-2 py-0.5 text-xs text-gray-900">{{ p.folio }}</span></td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 font-mono text-sky-600">{{ p.paymentCode }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200 text-center">{{ p.isDeleted }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.vatNo }}</td>
                  <td class="px-2.5 py-1.5 border-r border-gray-200">{{ p.accounting }}</td>
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
          <div class="p-1.5 bg-[#f4f5f0] border-t border-gray-300 flex items-center justify-between font-bold text-gray-800 text-xs">
            <div class="flex items-center gap-1.5">
              <input
                type="checkbox"
                :checked="areAllPaymentsSelected"
                :disabled="paymentSelectionIds.length === 0"
                @change="toggleAllPaymentSelection($event.target.checked)"
                class="rounded border-gray-300 disabled:cursor-not-allowed disabled:opacity-50"
              />
              <span>Tổng cộng</span>
            </div>
            <span class="font-mono text-xs pr-2">{{ formatMoney(totalPaymentAmount) }}</span>
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
      :roomRate="Number(selectedRoomItem ? (selectedRoomItem.rate ?? selectedRoomItem.roomRate ?? selectedRoomItem.rawRoom?.rate ?? selectedRoomItem.rawRoom?.room_rate ?? 0) : (selectedBooking?.roomItems?.[0]?.rate ?? selectedBooking?.roomItems?.[0]?.roomRate ?? selectedBooking?.roomItems?.[0]?.rawRoom?.rate ?? selectedBooking?.roomItems?.[0]?.rawRoom?.room_rate ?? 0))"
      :roomAdjustment="roomAdjustment"
      @close="showAddServiceModal = false; roomAdjustment = null" 
      @success="handleServiceAdded"
    />

    <AddHousekeepingServiceModal 
      :show="showHousekeepingServiceModal" 
      :bookingInfo="addServiceBookingInfo"
      :roomId="selectedRoomItem ? (selectedRoomItem.roomId || selectedRoomItem.id) : ''"
      :guestId="selectedGuestId"
      :initialAdjustment="housekeepingAdjustment"
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
      @close="showPrepaymentModal = false" 
    />

    <PaymentModal 
      :show="showPaymentModal" 
      @close="showPaymentModal = false" 
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
  </div>
</template>
