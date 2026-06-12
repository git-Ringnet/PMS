<script setup>
import { ref, computed } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Sub-tabs list
const tabs = [
  { key: 'arrivals', name: 'Phòng đến' },
  { key: 'departures', name: 'Phòng đi' },
  { key: 'pending', name: 'Đăng ký chờ xác nhận' },
  { key: 'shuttle', name: 'Đón tiễn khách' },
  { key: 'noshow', name: 'Phòng không đến (Noshow)' },
  { key: 'birthdays', name: 'Sinh nhật khách' }
]

const activeTab = ref('arrivals')
const searchDate = ref('2026-06-09') // Default date matching screenshots
const dateInputRef = ref(null)

// Expand/Collapse state: map of booking ID -> boolean (true if collapsed)
const collapsedBookings = ref({
  'GAL4819': true,
  'GAL4854': true,
  'GAL5196': true,
  'GAL5308': true,
  'GAL5330': true,
  'GAL5414': true,
  'GAL5418': true,
  'GAL5486': true,
  // Departures default state (all expanded by default as in screenshot 3)
})

function toggleCollapse(bookingId) {
  collapsedBookings.value[bookingId] = !collapsedBookings.value[bookingId]
}

function formatDateInput(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length !== 3) return dateStr
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

function triggerDatePicker() {
  if (dateInputRef.value) {
    if (typeof dateInputRef.value.showPicker === 'function') {
      dateInputRef.value.showPicker()
    } else {
      dateInputRef.value.click()
    }
  }
}

function handleCopyDate() {
  const formatted = formatDateInput(searchDate.value)
  navigator.clipboard.writeText(formatted)
    .then(() => {
      uiStore.showToast(`Đã sao chép ngày "${formatted}" vào clipboard!`, 'success')
    })
    .catch(() => {
      uiStore.showToast('Không thể sao chép ngày. Hãy thử sao chép thủ công!', 'error')
    })
}

function handleSearch() {
  uiStore.showToast(`Đang tìm kiếm danh sách công việc ngày ${formatDateInput(searchDate.value)}...`, 'info')
}

// Mock Data for "Phòng đến" (Arrivals)
const arrivalsData = ref([
  {
    id: 'GAL4739',
    bookingName: 'ODEON TOUR - 11909175',
    status: 'Guaranteed',
    roomNight: 11,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '20/06/2026',
    notes: 'Ghi chú: 1 DELUXE CITY VIEW (11 ĐÊM)*630.000/R/N 1 EXTRA BED ADULT (11 ĐÊM)*300.000/EB/N FOC 1 ĐÊM CUỐI CTY THANH TOÁN + K VAT TÊN KHÁCH: PETROV/SERGEI Adult-Mr. 15.12.1969 - 56 PETROVA/SVETLANA Adult-Mrs. 04.06.1975 - 51 PETROV/ALEKSEI Adult-Mr. 31.05.1994 - 32 NOTE: TOP FLOOR',
    deposit: 0,
    totalAmount: 9300000,
    rooms: [
      {
        roomNumber: '',
        price: 630000,
        rateCode: '',
        roomTotal: 9300000,
        company: 'ODEON TOURS'
      }
    ]
  },
  {
    id: 'GAL4819',
    bookingName: 'ODEON TOUR - 11914421',
    status: 'Guaranteed',
    roomNight: 11,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '20/06/2026',
    notes: 'Ghi chú: 1 DELUXE CITY VIEW (11 ĐÊM)*630.000/R/N FOC 1 ĐÊM CUỐI CTY THANH TOÁN + K VAT TÊN KHÁCH: MINGALEEVA/LIAISAN Adult-Mrs. 25.08.1991 - 34 MINGALEEV/ARTUR Adult-Mr. 06.11.1991 - 34',
    deposit: 0,
    totalAmount: 6300000,
    rooms: [
      {
        roomNumber: '901',
        price: 630000,
        rateCode: '',
        roomTotal: 6300000,
        company: 'ODEON TOURS'
      }
    ]
  },
  {
    id: 'GAL4854',
    bookingName: 'AB TOUR - 90026994',
    status: 'Guaranteed',
    roomNight: 14,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '23/06/2026',
    notes: 'Ghi chú: 1 Superior Room - DBL (14 ĐÊM)*490.000/R/N FOC 1 ĐÊM CUỐI CÔNG NỢ + VAT TÊN KHÁCH: MRS TATSIANA ALIAKSANDRAVA 66 03.05.1960 MR ULADZIMIR ALIAKSANDRAU 66 28.12.1959',
    deposit: 0,
    totalAmount: 6370000,
    rooms: [
      {
        roomNumber: '706',
        price: 490000,
        rateCode: '',
        roomTotal: 6370000,
        company: 'AB TOUR'
      }
    ]
  },
  {
    id: 'GAL5096',
    bookingName: 'Trip.com - 1658111816718262 - KATHY DIEU TRINH',
    status: 'Guaranteed',
    roomNight: 2,
    roomsCount: 2,
    arrivalDate: '09/06/2026',
    departureDate: '11/06/2026',
    notes: 'Ghi chú: Superior Twin - BF-B2C Special request: same floor, adjoinning rooms Other names: SAI HONG TE, TEONG BENG TE, MEOW LAN CHIA, TONG SENG TE, CHEE SIANG HENG.',
    deposit: 0,
    totalAmount: 2232000,
    rooms: [
      {
        roomNumber: '504',
        price: 544000,
        rateCode: 'ST',
        roomTotal: 1144000,
        company: 'TRIP.COM'
      },
      {
        roomNumber: '510',
        price: 544000,
        rateCode: 'ST',
        roomTotal: 1088000,
        company: 'TRIP.COM'
      }
    ]
  },
  {
    id: 'GAL5196',
    bookingName: 'CT XÂY DỰNG BHS - MR. THÀNH',
    status: 'Guaranteed',
    roomNight: 2,
    roomsCount: 8,
    arrivalDate: '09/06/2026',
    departureDate: '11/06/2026',
    notes: 'Ghi chú: 2 SUPERIOR * 650.000/R/N (1TWN, 1DBL) 2 SUPERIOR TRIPLE*950.000/R/N 1 DELUXE TRIPLE*1.200.000/R/N 3 FAMILY*1.310.000/R/N GIÁ BAO GỒM VAT & BF CTY TT',
    deposit: 5000000,
    totalAmount: 16660000,
    rooms: [
      {
        roomNumber: '801',
        price: 650000,
        rateCode: 'ST',
        roomTotal: 1300000,
        company: 'BHS CONST'
      }
    ]
  },
  {
    id: 'GAL5308',
    bookingName: 'ANEX TOUR - 112453739',
    status: 'Guaranteed',
    roomNight: 9,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '18/06/2026',
    notes: 'Ghi chú: 1 Superior Room - Double ( 9 ĐÊM)*540.000/R/N CÔNG NỢ + VAT TÊN KHÁCH: MR FAZLIAKHMETOV AZAT 34 12.02.1992 MRS FAZLIAKHMETOVA IANA 28 05.04.1998 CHD FAZLIAKHMETOVA ADELIIA 5 09.11.2020',
    deposit: 0,
    totalAmount: 4860000,
    rooms: [
      {
        roomNumber: '1102',
        price: 540000,
        rateCode: 'ST',
        roomTotal: 4860000,
        company: 'ANEX TOUR'
      }
    ]
  },
  {
    id: 'GAL5330',
    bookingName: 'ANEX TOUR - 112459435',
    status: 'Guaranteed',
    roomNight: 9,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '18/06/2026',
    notes: 'Ghi chú: 1 Superior Room - Twin ( 9 ĐÊM)*540.000/R/N CÔNG NỢ + VAT TÊN KHÁCH: MR KAPLIN PAVEL 32 07.09.1993 MR KAPLIN ALEKSANDR 31 18.03.1995',
    deposit: 0,
    totalAmount: 4860000,
    rooms: [
      {
        roomNumber: '1104',
        price: 540000,
        rateCode: 'ST',
        roomTotal: 4860000,
        company: 'ANEX TOUR'
      }
    ]
  },
  {
    id: 'GAL5414',
    bookingName: 'Agoda - 1734007815 - YEHONG TANG',
    status: 'Guaranteed',
    roomNight: 1,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '10/06/2026',
    notes: 'Ghi chú: Superior Twin - RO-BAR Benefits: Drinking water, Free pool access, Welcome drink, Coffee & tea, Free WiFi, Free Premium Wifi, Late check-in, Cable TV channels, Free fitness center access',
    deposit: 0,
    totalAmount: 500320,
    rooms: [
      {
        roomNumber: '602',
        price: 500320,
        rateCode: 'ROBAR',
        roomTotal: 500320,
        company: 'AGODA'
      }
    ]
  },
  {
    id: 'GAL5418',
    bookingName: 'Booking.com - 6935835140 - Thien Y Nguyen',
    status: 'Guaranteed',
    roomNight: 2,
    roomsCount: 2,
    arrivalDate: '09/06/2026',
    departureDate: '11/06/2026',
    notes: 'Ghi chú: Phòng Superior 2 Giường Đơn - General: 2 adults Phòng Superior 2 Giường Đơn - General: 2 adults NonSmoke',
    deposit: 0,
    totalAmount: 2360000,
    rooms: [
      {
        roomNumber: '702',
        price: 590000,
        rateCode: 'ST',
        roomTotal: 1180000,
        company: 'BOOKING.COM'
      }
    ]
  },
  {
    id: 'GAL5486',
    bookingName: 'Walkin Guest',
    status: 'Guaranteed',
    roomNight: 1,
    roomsCount: 1,
    arrivalDate: '09/06/2026',
    departureDate: '10/06/2026',
    notes: 'Ghi chú:',
    deposit: 0,
    totalAmount: 600000,
    rooms: [
      {
        roomNumber: '402',
        price: 600000,
        rateCode: 'ST',
        roomTotal: 600000,
        company: 'KHÁCH LẺ'
      }
    ]
  }
])

// Mock Data for "Phòng đi" (Departures) - Matches exact screenshot 3
const departuresData = ref([
  {
    id: 'GAL3646',
    bookingName: 'PEGAS / 10440167',
    status: 'Guaranteed',
    roomNight: 11,
    roomsCount: 1,
    arrivalDate: '29/05/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 Deluxe City View With Balcony * 29/05 - 31/05 (3 ĐÊM) * 690K/R/N * 1/6 - 8/6 (8 ĐÊM) * 890K/R/N FOC 1 ĐÊM CUỐI CÔNG NỢ + VAT TÊN KHÁCH: MR OTDELNOV ARTUR 01.04.1969 MRS OTDELNOVA OKSANA 24.12.1972 MR PETROV IURII 10.03.1995',
    serviceCharge: 8300000,
    paidAmount: 0,
    rooms: [
      {
        roomNumber: '1002',
        price: 890000,
        rateCode: 'PEGAS',
        company: 'PEGAS',
        serviceTotal: 150000,
        totalPaid: 150000
      }
    ]
  },
  {
    id: 'GAL4081',
    bookingName: 'ODEON TOUR/ 11867146',
    status: 'Guaranteed',
    roomNight: 12,
    roomsCount: 1,
    arrivalDate: '28/05/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 DELUXE ROOM WITH BALCONY - DBL 28 - 31/05: 580,000/R/N 01 - 09/6: 630,000/R/N FOC 1 ĐÊM CUỐI CTY TT, KO VAT TÊN KHÁCH: 1 STAROSTINA/ANNA Adult-Mrs. 05.06.1987 - 38 2 STAROSTIN/STEPAN Adult-Mr. 05.03.2018 - 8',
    serviceCharge: 6730000,
    paidAmount: 0,
    rooms: [
      {
        roomNumber: '1012',
        price: 580000,
        rateCode: 'ODEON TOURS',
        company: 'ODEON TOURS',
        serviceTotal: 0,
        totalPaid: 0
      }
    ]
  },
  {
    id: 'GAL4942',
    bookingName: 'AB TOUR - 90027915',
    status: 'Guaranteed',
    roomNight: 10,
    roomsCount: 1,
    arrivalDate: '30/05/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 Superior Room 30/5 - 31/5 (2 ĐÊM) * 470.000/R/N 1/6 - 8/6 (8 ĐÊM) * 490.000/R/N FOC 1 ĐÊM CUỐI CÔNG NỢ + VAT TÊN KHÁCH: MRS KOSTROMINA SVETLANA 52 14.01.1974 MRS GASANOVA SEVIL 31 04.07.1994',
    serviceCharge: 4370000,
    paidAmount: 0,
    rooms: [
      {
        roomNumber: '705',
        price: 490000,
        rateCode: 'AB TOUR',
        company: 'AB TOUR',
        serviceTotal: 0,
        totalPaid: 0
      }
    ]
  },
  {
    id: 'GAL5010',
    bookingName: 'Trip.com - 1400825993015360 - MINHYEOK HAN',
    status: 'Guaranteed',
    roomNight: 1,
    roomsCount: 1,
    arrivalDate: '08/06/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: Family Room With View - BF-B2C Near elevator;',
    serviceCharge: 1105000,
    paidAmount: 0,
    rooms: [
      {
        roomNumber: '1105',
        price: 1105000,
        rateCode: 'ST',
        company: 'TRIP.COM',
        serviceTotal: 0,
        totalPaid: 0
      }
    ]
  },
  {
    id: 'GAL5178',
    bookingName: 'AB TOUR - 90029779',
    status: 'Guaranteed',
    roomNight: 12,
    roomsCount: 1,
    arrivalDate: '28/05/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 Deluxe City View Room - DBL (12 ĐÊM)*650.000/R/N CÔNG NỢ + VAT TÊN KHÁCH: MRS PATSUKEVICH MARYNA 52 19.09.1973 MRS ZHUK TATSIANA 53 29.12.1972',
    serviceCharge: 7800000,
    paidAmount: 0,
    rooms: [
      {
        roomNumber: '805',
        price: 650000,
        rateCode: 'AB TOUR',
        company: 'AB TOUR',
        serviceTotal: 0,
        totalPaid: 0
      }
    ]
  },
  {
    id: 'GAL5184',
    bookingName: 'ANEX TOUR - 112422963',
    status: 'Guaranteed',
    roomNight: 8,
    roomsCount: 1,
    arrivalDate: '01/06/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 Superior Room - Twin (9 ĐÊM)*540.000/R/N CÔNG NỢ + VAT TÊN KHÁCH: MRS LIKHOVETER EKATERINA 35 27.03.1991 CHD LIKHOVETER MAKSIM 10 17.06.2015 NO SHOW',
    serviceCharge: 4860000,
    paidAmount: 100000,
    rooms: [
      {
        roomNumber: '1509',
        price: 540000,
        rateCode: 'ANEX TOUR',
        company: 'ANEX TOUR',
        serviceTotal: 100000,
        totalPaid: 100000
      }
    ]
  },
  {
    id: 'GAL5193',
    bookingName: 'TRAVEL CONCIERGE - 2607856 (COMMITMENT)',
    status: 'Guaranteed',
    roomNight: 14,
    roomsCount: 1,
    arrivalDate: '26/05/2026',
    departureDate: '09/06/2026',
    notes: 'Ghi chú: 1 SUPERIOR ROOM - DBL (14 ĐÊM)*490.000/R/N ABF CHD (14 ĐÊM)*90.000/CHD/N CTY THANH TOÁN + VAT TÊN KHÁCH: MR BRAGINSKIY ALEXANDR 35 20.09.1990 MRS BRAGINSKAYA MADINA 34 31.12.1991 CHD BRAGINSKIY DANIIL 11 08.09.2014 NOTE: kingsize bed if possible At high floor',
    serviceCharge: 7940000,
    paidAmount: 8120000,
    rooms: [
      {
        roomNumber: '',
        price: null,
        rateCode: 'TRAVEL CONCIERGE',
        company: 'TRAVEL CONCIERGE',
        serviceTotal: null,
        totalPaid: null
      }
    ]
  }
])

// Totals Computed
const arrivalsTotalRegistrations = computed(() => {
  return arrivalsData.value.length
})

const arrivalsTotalRooms = computed(() => {
  return arrivalsData.value.reduce((sum, item) => sum + item.roomsCount, 0)
})

const departuresTotalRegistrations = computed(() => {
  return departuresData.value.length
})

const departuresTotalRooms = computed(() => {
  return departuresData.value.reduce((sum, item) => sum + item.roomsCount, 0)
})

// New range date selector state
const searchDateFrom = ref('2026-06-09')
const searchDateTo = ref('2026-06-12')
const dateFromInputRef = ref(null)
const dateToInputRef = ref(null)

const isRangeTab = computed(() => {
  return ['pending', 'shuttle', 'noshow', 'birthdays'].includes(activeTab.value)
})

function triggerDateFromPicker() {
  if (dateFromInputRef.value) {
    if (typeof dateFromInputRef.value.showPicker === 'function') {
      dateFromInputRef.value.showPicker()
    } else {
      dateFromInputRef.value.click()
    }
  }
}

function triggerDateToPicker() {
  if (dateToInputRef.value) {
    if (typeof dateToInputRef.value.showPicker === 'function') {
      dateToInputRef.value.showPicker()
    } else {
      dateToInputRef.value.click()
    }
  }
}

function handleCopyDateFrom() {
  const formatted = formatDateInput(searchDateFrom.value)
  navigator.clipboard.writeText(formatted)
    .then(() => {
      uiStore.showToast(`Đã sao chép ngày bắt đầu "${formatted}"!`, 'success')
    })
}

function handleCopyDateTo() {
  const formatted = formatDateInput(searchDateTo.value)
  navigator.clipboard.writeText(formatted)
    .then(() => {
      uiStore.showToast(`Đã sao chép ngày kết thúc "${formatted}"!`, 'success')
    })
}

// Mock Data for "Đăng ký chờ xác nhận" (Pending Confirmation)
const pendingConfirmationData = ref([
  { id: 'GAL5485', name: 'ANH TRUNG', status: 'Allotment', company: 'Travel Concierge', pendingDate: '09/06/2026', arrivalDate: '11/06/2026', departureDate: '14/06/2026', nights: 3, deposit: 0, notes: '' },
  { id: 'GAL5486', name: 'Walkin Guest', status: 'None Guaranteed', company: 'KHÁCH LẺ', pendingDate: '09/06/2026', arrivalDate: '09/06/2026', departureDate: '10/06/2026', nights: 1, deposit: 0, notes: '' },
  { id: 'GAL5452', name: 'Trip.Com - 1400826538862536 - DOJUN LEE', status: 'Noshow with Penalty', company: 'TRIP.COM', pendingDate: '05/06/2026', arrivalDate: '06/06/2026', departureDate: '07/06/2026', nights: 1, deposit: 0, notes: '' },
  { id: 'GAL5189', name: 'PRESTIGE DMC / 13696308', status: 'Noshow with Penalty', company: 'PRESTIGE DMC VIET NAM TRADING AND TRAVEL COMPANY LIMITED', pendingDate: '23/05/2026', arrivalDate: '23/05/2026', departureDate: '30/05/2026', nights: 7, deposit: 3850000, notes: '' },
  { id: 'GAL4920', name: 'GREEN TRAVEL GROUP - 400934', status: 'Noshow with Penalty', company: 'GREEN TRAVEL GROUP', pendingDate: '06/05/2026', arrivalDate: '06/05/2026', departureDate: '07/05/2026', nights: 1, deposit: 0, notes: '' },
  { id: 'GAL4908', name: 'TRAVEL CONCIERGE - 2616509 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '21/05/2026', departureDate: '28/05/2026', nights: 7, deposit: 3430000, notes: '' },
  { id: 'GAL4909', name: 'TRAVEL CONCIERGE - 2616508 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '21/05/2026', departureDate: '28/05/2026', nights: 7, deposit: 3430000, notes: '' },
  { id: 'GAL4911', name: 'TRAVEL CONCIERGE - 2616505 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '21/05/2026', departureDate: '28/05/2026', nights: 7, deposit: 3430000, notes: '' },
  { id: 'GAL4912', name: 'TRAVEL CONCIERGE - 2616500 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '21/05/2026', departureDate: '28/05/2026', nights: 7, deposit: 3430000, notes: '' },
  { id: 'GAL4913', name: 'TRAVEL CONCIERGE - 2616498 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '21/05/2026', departureDate: '28/05/2026', nights: 7, deposit: 3430000, notes: '' },
  { id: 'GAL4914', name: 'TRAVEL CONCIERGE - 2616495 (COMMITMENT)', status: 'Noshow with Penalty', company: 'TRAVEL CONCIERGE - (COMMITMENT)', pendingDate: '05/05/2026', arrivalDate: '27/05/2026', departureDate: '28/05/2026', nights: 1, deposit: 3430000, notes: '' }
])

const pendingGroups = computed(() => {
  const groups = {}
  pendingConfirmationData.value.forEach(item => {
    const d = item.pendingDate
    if (!groups[d]) {
      groups[d] = []
    }
    groups[d].push(item)
  })
  return groups
})

// Mock Data for "Đón tiễn khách" (Shuttle Service)
const shuttleData = ref([
  { id: 'GAL5512', vehicleArrival: 'Ô tô 4 chỗ', flightNo: 'VN123', date: '09/06/2026', time: '14:30', roomPrice: 500000, vehicleDetail: 'Đưa đón bằng taxi', notes: 'Khách VIP, đón sảnh A' },
  { id: 'GAL5513', vehicleArrival: 'Ô tô 7 chỗ', flightNo: 'VJ456', date: '10/06/2026', time: '08:15', roomPrice: 700000, vehicleDetail: 'Xe khách sạn đưa tiễn', notes: 'Hỗ trợ hành lý nhiều' }
])

// Mock Data for "Phòng không đến (Noshow)"
const noshowFilter = ref('all') // 'all', 'charged', 'foc'
const noshowData = ref([
  { roomNumber: '1508', id: 'GAL3593', groupName: 'PEGAS / 10427634', confirmDate: '23/02/2026', arrivalDate: '10/06/2026', nights: 10, absentDate: '09/06/2026', time: '10:25', price: 630000, username: 'admin', shift: '1', reason: 'NightAudit No Show One Day Charge Room', type: 'No Show One Day', isCharged: true },
  { roomNumber: '', id: 'GAL4670', groupName: 'ODEON TOUR - 4469973', confirmDate: '21/04/2026', arrivalDate: '09/06/2026', nights: 11, absentDate: '09/06/2026', time: '10:26', price: 890000, username: 'admin', shift: '1', reason: 'NightAudit NoShow Charge Room', type: 'No Show By Stage', isCharged: true },
  { roomNumber: '', id: 'GAL4670', groupName: 'ODEON TOUR - 4469973', confirmDate: '21/04/2026', arrivalDate: '10/06/2026', nights: 11, absentDate: '10/06/2026', time: '10:26', price: 890000, username: 'admin', shift: '1', reason: 'NightAudit NoShow Charge Room', type: 'No Show By Stage', isCharged: true },
  { roomNumber: '', id: 'GAL4670', groupName: 'ODEON TOUR - 4469973', confirmDate: '21/04/2026', arrivalDate: '11/06/2026', nights: 11, absentDate: '11/06/2026', time: '10:26', price: 890000, username: 'admin', shift: '1', reason: 'NightAudit NoShow Charge Room', type: 'No Show By Stage', isCharged: false },
  { roomNumber: '', id: 'GAL4670', groupName: 'ODEON TOUR - 4469973', confirmDate: '21/04/2026', arrivalDate: '12/06/2026', nights: 11, absentDate: '12/06/2026', time: '10:26', price: 890000, username: 'admin', shift: '1', reason: 'NightAudit NoShow Charge Room', type: 'No Show By Stage', isCharged: false }
])

const filteredNoshowData = computed(() => {
  if (noshowFilter.value === 'charged') {
    return noshowData.value.filter(item => item.isCharged)
  } else if (noshowFilter.value === 'foc') {
    return noshowData.value.filter(item => !item.isCharged)
  }
  return noshowData.value
})

const noshowGroups = computed(() => {
  const groups = {}
  filteredNoshowData.value.forEach(item => {
    const t = item.type
    if (!groups[t]) {
      groups[t] = []
    }
    groups[t].push(item)
  })
  return groups
})

// Mock Data for "Sinh nhật khách" (Guest Birthdays)
const birthdaysData = ref([
  { id: 'GAL5520', roomNumber: '902', name: 'NGUYỄN VĂN A', birthday: '09/06/1988', docType: 'CCCD', docNo: '0123456789', email: 'a.nguyen@gmail.com', phone: '0901234567', nationality: 'Việt Nam', arrivalDate: '09/06/2026', departureDate: '12/06/2026', nights: 3, price: 850000, rateCode: 'ST', company: 'AGODA' },
  { id: 'GAL5521', roomNumber: '1105', name: 'JOHN SMITH', birthday: '10/06/1990', docType: 'Hộ chiếu', docNo: 'B123456', email: 'john.smith@gmail.com', phone: '+1234567890', nationality: 'United States', arrivalDate: '08/06/2026', departureDate: '11/06/2026', nights: 3, price: 1105000, rateCode: 'ST', company: 'TRIP.COM' }
])

const collapsedGroups = ref({})

function toggleGroupCollapse(groupKey) {
  collapsedGroups.value[groupKey] = !collapsedGroups.value[groupKey]
}

function isGroupCollapsed(groupKey) {
  return !!collapsedGroups.value[groupKey]
}

// Shuttle Groups
const shuttleGroups = computed(() => {
  const groups = {}
  shuttleData.value.forEach(item => {
    const d = item.date
    if (!groups[d]) {
      groups[d] = []
    }
    groups[d].push(item)
  })
  return groups
})

const sortedShuttleDates = computed(() => {
  return Object.keys(shuttleGroups.value).sort((a, b) => {
    const partsA = a.split('/')
    const partsB = b.split('/')
    const dateA = new Date(partsA[2], parseInt(partsA[1]) - 1, partsA[0])
    const dateB = new Date(partsB[2], parseInt(partsB[1]) - 1, partsB[0])
    return dateB - dateA // descending
  })
})

// Birthday Groups
const birthdayGroups = computed(() => {
  const groups = {}
  birthdaysData.value.forEach(item => {
    const d = item.birthday
    if (!groups[d]) {
      groups[d] = []
    }
    groups[d].push(item)
  })
  return groups
})

const sortedBirthdayDates = computed(() => {
  return Object.keys(birthdayGroups.value).sort((a, b) => {
    const partsA = a.split('/')
    const partsB = b.split('/')
    const dateA = new Date(partsA[2], parseInt(partsA[1]) - 1, partsA[0])
    const dateB = new Date(partsB[2], parseInt(partsB[1]) - 1, partsB[0])
    return dateB - dateA // descending
  })
})

const sortedPendingDates = computed(() => {
  return Object.keys(pendingGroups.value).sort((a, b) => {
    const partsA = a.split('/')
    const partsB = b.split('/')
    const dateA = new Date(partsA[2], parseInt(partsA[1]) - 1, partsA[0])
    const dateB = new Date(partsB[2], parseInt(partsB[1]) - 1, partsB[0])
    return dateB - dateA // descending
  })
})

const footerStats = computed(() => {
  if (activeTab.value === 'arrivals') {
    return {
      label1: 'Tổng đăng ký:',
      val1: arrivalsTotalRegistrations.value,
      label2: 'Phòng:',
      val2: arrivalsTotalRooms.value
    }
  } else if (activeTab.value === 'departures') {
    return {
      label1: 'Tổng đăng ký:',
      val1: departuresTotalRegistrations.value,
      label2: 'Phòng:',
      val2: departuresTotalRooms.value
    }
  } else if (activeTab.value === 'pending') {
    const totalNights = pendingConfirmationData.value.reduce((sum, item) => sum + item.nights, 0)
    return {
      label1: 'Tổng đăng ký:',
      val1: pendingConfirmationData.value.length,
      label2: 'Đêm phòng:',
      val2: totalNights
    }
  } else if (activeTab.value === 'shuttle') {
    return {
      label1: 'Tổng lượt:',
      val1: shuttleData.value.length,
      label2: 'Phòng:',
      val2: shuttleData.value.length
    }
  } else if (activeTab.value === 'noshow') {
    const totalNights = filteredNoshowData.value.reduce((sum, item) => sum + item.nights, 0)
    return {
      label1: 'Tổng số phòng:',
      val1: filteredNoshowData.value.length,
      label2: 'Tổng đêm vắng:',
      val2: totalNights
    }
  } else if (activeTab.value === 'birthdays') {
    return {
      label1: 'Tổng khách:',
      val1: birthdaysData.value.length,
      label2: 'Phòng:',
      val2: birthdaysData.value.filter(item => item.roomNumber).length
    }
  }
  return { label1: 'Tổng:', val1: 0, label2: 'Phòng:', val2: 0 }
})

const editingNotes = ref({})

function toggleEditNote(itemId) {
  editingNotes.value[itemId] = !editingNotes.value[itemId]
}

function saveNote(item) {
  editingNotes.value[item.id] = false
  uiStore.showToast(`Đã lưu ghi chú cho đăng ký ${item.id} thành công!`, 'success')
}

function formatMoney(num) {
  if (num === undefined || num === null) return '0'
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full">
    <!-- Sub tabs header (3rd Level Menu) -->
    <div class="flex items-center border-b border-slate-200 px-4 bg-slate-50/50 shrink-0 overflow-x-auto scrollbar-none">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="py-3 px-4 text-xs font-bold transition-all relative border-b-2 whitespace-nowrap cursor-pointer"
        :class="activeTab === tab.key
          ? 'border-sky-500 text-sky-600'
          : 'border-transparent text-slate-700 hover:text-slate-900 hover:border-slate-300'"
      >
        {{ tab.name }}
      </button>
    </div>

    <!-- Custom Date Selector / Range Selector -->
    <div class="p-3 border-b border-slate-100 flex items-center gap-2 bg-white shrink-0 flex-wrap">
      <!-- Case 1: Single Date Picker for arrivals / departures -->
      <div v-if="!isRangeTab" class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
        <!-- Date Display / Input click trigger -->
        <span 
          @click="triggerDatePicker"
          class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
        >
          {{ formatDateInput(searchDate) }}
        </span>
        
        <!-- Native Hidden Date Input -->
        <input
          ref="dateInputRef"
          type="date"
          v-model="searchDate"
          class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
        />

        <!-- Green Calendar trigger button -->
        <button
          @click="triggerDatePicker"
          type="button"
          class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
          title="Chọn ngày"
        >
          <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
        </button>

        <!-- Document copy icon button -->
        <button
          @click="handleCopyDate"
          type="button"
          class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
          title="Sao chép ngày"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
          </svg>
        </button>
      </div>

      <!-- Case 2: Date Range Picker for range-based tabs -->
      <div v-else class="flex items-center gap-1.5 flex-wrap">
        <!-- Date From Picker -->
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDateFromPicker"
            class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
          >
            {{ formatDateInput(searchDateFrom) }}
          </span>
          <input
            ref="dateFromInputRef"
            type="date"
            v-model="searchDateFrom"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />
          <button
            @click="triggerDateFromPicker"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
            title="Chọn ngày bắt đầu"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>
          <button
            @click="handleCopyDateFrom"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
            title="Sao chép ngày bắt đầu"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
          </button>
        </div>

        <span class="text-xs font-bold text-slate-400 mx-1">~</span>

        <!-- Date To Picker -->
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-sm hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDateToPicker"
            class="text-xs font-bold text-slate-700 px-3 py-1 cursor-pointer select-none"
          >
            {{ formatDateInput(searchDateTo) }}
          </span>
          <input
            ref="dateToInputRef"
            type="date"
            v-model="searchDateTo"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />
          <button
            @click="triggerDateToPicker"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-[#10b981] bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors"
            title="Chọn ngày kết thúc"
          >
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          </button>
          <button
            @click="handleCopyDateTo"
            type="button"
            class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors ml-0.5 border-l border-slate-100"
            title="Sao chép ngày kết thúc"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
          </button>
        </div>

        <!-- Noshow Radio Group Filter -->
        <div v-if="activeTab === 'noshow'" class="flex items-center gap-3 ml-3 border-l border-slate-200 pl-4 text-xs font-bold text-slate-700">
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="all" v-model="noshowFilter" class="accent-sky-500" />
            Tất cả
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="charged" v-model="noshowFilter" class="accent-sky-500" />
            Tính phí
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="radio" value="foc" v-model="noshowFilter" class="accent-sky-500" />
            Không tính phí
          </label>
        </div>
      </div>

      <!-- Search button -->
      <button
        @click="handleSearch"
        class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold transition-colors shadow-sm cursor-pointer border-none"
      >
        Tìm kiếm
      </button>
    </div>

    <!-- Main List/Table Content -->
    <div class="flex-1 overflow-hidden bg-slate-50/40 flex flex-col">
      <!-- Arrivals Page ("Phòng đến") -->
      <div v-if="activeTab === 'arrivals'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1250px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[130px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[260px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tình trạng đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[85px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[85px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đêm phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[115px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giá Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã giá phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[125px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tổng phòng</th>
                <th class="p-2 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="booking in arrivalsData" :key="booking.id">
                <!-- Group Header Row (Wrapping Notes perfectly, layout matches image 1) -->
                <tr class="group border-b border-slate-200">
                  <!-- Spanned cell for Toggle icon + Booking title + Full Wrapped Notes -->
                  <td colspan="4" class="p-3 border-r border-slate-200 align-top bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="flex items-start gap-2.5">
                      <!-- Blue Square Plus/Minus toggle icon matching screenshot -->
                      <button
                        @click="toggleCollapse(booking.id)"
                        type="button"
                        class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mt-0.5"
                      >
                        {{ collapsedBookings[booking.id] ? '+' : '-' }}
                      </button>
                      
                      <!-- Booking details & fully wrapped notes container -->
                      <div class="flex-1 min-w-0">
                        <span class="text-xs font-black text-slate-800 block leading-normal">
                          Booking {{ booking.id }}: {{ booking.bookingName }} _ {{ booking.arrivalDate }}~{{ booking.departureDate }} _ Room Night: {{ booking.roomNight }}_Phòng : {{ booking.roomsCount }}
                        </span>
                        <p class="text-[11px] font-semibold text-slate-500 mt-1.5 leading-relaxed break-words whitespace-normal max-w-[580px]">
                          {{ booking.notes }}
                        </p>
                      </div>
                    </div>
                  </td>
                  
                  <!-- Dummy middle cells to maintain grid layout lines for columns 5, 6, 7 -->
                  <td colspan="3" class="p-3 border-r border-slate-200 align-top text-center text-slate-700 font-bold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="mt-0.5 text-[11.5px]">
                      Đặt cọc : {{ formatMoney(booking.deposit) }}
                    </div>
                  </td>

                  <!-- Rightmost cells for deposit / total money displays aligned with columns 8-11 -->
                  <td colspan="4" class="p-3 align-top text-left text-slate-700 font-bold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="mt-0.5 text-[11.5px] pl-4">
                      Tổng tiền : {{ formatMoney(booking.totalAmount) }}
                    </div>
                  </td>
                </tr>

                <!-- Child Room Rows (Rendered only if NOT collapsed) -->
                <tr
                  v-if="!collapsedBookings[booking.id]"
                  v-for="(room, rIdx) in booking.rooms"
                  :key="`${booking.id}-room-${rIdx}`"
                  class="border-b border-slate-200 h-8"
                >
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.id }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.bookingName }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.status }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-black text-slate-700 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.roomNumber }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.arrivalDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.departureDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.roomNight }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-700 font-semibold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ formatMoney(room.price) }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.rateCode }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-800 font-black transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ formatMoney(room.roomTotal) }}
                  </td>
                  <td
                    class="p-2 text-slate-600 break-words whitespace-normal leading-tight font-semibold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.company }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Departures Page ("Phòng đi") -->
      <div v-else-if="activeTab === 'departures'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1250px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[130px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[260px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tình trạng đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[85px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[85px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đêm phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[115px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giá Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã giá phòng</th>
                <th class="p-2 border-r border-slate-200 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
                <th class="p-2 border-r border-slate-200 text-right w-[125px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tổng dịch vụ</th>
                <th class="p-2 text-right w-[125px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tổng thanh toán</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="booking in departuresData" :key="booking.id">
                <!-- Group Header Row (Wrapping Notes perfectly, matches image 3) -->
                <tr class="group border-b border-slate-200">
                  <!-- Spanned cell for Toggle icon + Booking title + Full Wrapped Notes -->
                  <td colspan="4" class="p-3 border-r border-slate-200 align-top bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="flex items-start gap-2.5">
                      <!-- Blue Square Plus/Minus toggle icon -->
                      <button
                        @click="toggleCollapse(booking.id)"
                        type="button"
                        class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mt-0.5"
                      >
                        {{ collapsedBookings[booking.id] ? '+' : '-' }}
                      </button>
                      
                      <!-- Booking details & fully wrapped notes container -->
                      <div class="flex-1 min-w-0">
                        <span class="text-xs font-black text-slate-800 block leading-normal">
                          Booking {{ booking.id }}: {{ booking.bookingName }} _ {{ booking.arrivalDate }}~{{ booking.departureDate }} _ Room Night: {{ booking.roomNight }}_Phòng : {{ booking.roomsCount }}
                        </span>
                        <p class="text-[11px] font-semibold text-slate-500 mt-1.5 leading-relaxed break-words whitespace-normal max-w-[580px]">
                          {{ booking.notes }}
                        </p>
                      </div>
                    </div>
                  </td>

                  <!-- Service charge align columns 5-8 -->
                  <td colspan="4" class="p-3 border-r border-slate-200 align-top text-center text-slate-700 font-bold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="mt-0.5 text-[11.5px]">
                      Tiền dịch vụ: {{ formatMoney(booking.serviceCharge) }}
                    </div>
                  </td>

                  <!-- Paid amount align columns 9-12 -->
                  <td colspan="4" class="p-3 align-top text-left text-slate-700 font-bold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="mt-0.5 text-[11.5px] pl-4">
                      Tiền đã thanh toán: {{ formatMoney(booking.paidAmount) }}
                    </div>
                  </td>
                </tr>

                <!-- Child Room Rows (Rendered only if NOT collapsed) -->
                <tr
                  v-if="!collapsedBookings[booking.id]"
                  v-for="(room, rIdx) in booking.rooms"
                  :key="`${booking.id}-room-${rIdx}`"
                  class="border-b border-slate-200 h-8"
                >
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.id }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.bookingName }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.status }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-black text-slate-700 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.roomNumber }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.arrivalDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.departureDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ booking.roomNight }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-700 font-semibold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.price !== null ? formatMoney(room.price) : '' }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.rateCode }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight font-semibold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.company }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-800 font-bold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.serviceTotal !== null ? formatMoney(room.serviceTotal) : '' }}
                  </td>
                  <td
                    class="p-2 text-right text-emerald-600 font-bold transition-colors"
                    :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ room.totalPaid !== null ? formatMoney(room.totalPaid) : '' }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pending Registration / Confirmation Page -->
      <div v-else-if="activeTab === 'pending'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1600px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]"></th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[200px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tình trạng đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công ty</th>
                <th class="p-2 border-r border-slate-200 text-center w-[150px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đăng ký chờ xác nhận</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đêm phòng</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đặt cọc</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Liên hệ</th>
                <th class="p-2 w-[250px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="dateGroup in sortedPendingDates" :key="dateGroup">
                <!-- Group Header Row (Date group) -->
                <tr class="group border-b border-slate-200 h-9">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <button
                      @click="toggleGroupCollapse('pending-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('pending-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="12" class="p-2.5 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>
 
                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('pending-' + dateGroup)"
                  v-for="item in pendingGroups[dateGroup]"
                  :key="item.id"
                  class="group border-b border-slate-200 h-8"
                >
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ pendingConfirmationData.indexOf(item) + 1 }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.id }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.name }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.status }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight font-semibold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.company }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.pendingDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.arrivalDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.departureDate }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.nights }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-right font-semibold text-slate-700 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ formatMoney(item.deposit) }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-black text-slate-700 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.room || '' }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 truncate bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                  </td>
                  <td class="p-1 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <div class="flex items-center gap-1 w-full">
                      <input
                        type="text"
                        v-model="item.notes"
                        :disabled="!editingNotes[item.id]"
                        placeholder="Ghi chú"
                        class="flex-1 min-w-0 border rounded px-1.5 py-0.5 text-[11px] transition-all h-7 shadow-sm"
                        :class="[editingNotes[item.id] ? 'bg-white border-sky-400 text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500' : 'bg-[#f1f5f9] border-[#e2e8f0] text-slate-500 cursor-not-allowed select-none']"
                      />
                      
                      <!-- Save Button -->
                      <button
                        @click="saveNote(item)"
                        :disabled="!editingNotes[item.id]"
                        type="button"
                        class="w-7 h-7 rounded flex items-center justify-center border-none transition-all shadow-sm shrink-0"
                        :class="[editingNotes[item.id] ? 'bg-[#7aa0b5] hover:bg-[#5b859e] text-white cursor-pointer' : 'bg-[#94a3b8] text-slate-100 cursor-not-allowed opacity-60']"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v5h8" />
                        </svg>
                      </button>
                      
                      <!-- Edit Button -->
                      <button
                        @click="toggleEditNote(item.id)"
                        type="button"
                        class="w-7 h-7 rounded flex items-center justify-center border-none transition-all shadow-sm shrink-0 bg-[#7dd3fc] hover:bg-[#38bdf8] text-[#0369a1] cursor-pointer"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
 
      <!-- Shuttle Service Page -->
      <div v-else-if="activeTab === 'shuttle'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1250px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]"></th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã đăng ký</th>
                <th class="p-2 border-r border-slate-200 w-[220px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đến bằng/ Đi bằng phương tiện</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã số chuyến bay</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày</th>
                <th class="p-2 border-r border-slate-200 text-center w-[140px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Thời gian đón/ tiễn</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giá Phòng</th>
                <th class="p-2 border-r border-slate-200 w-[220px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đưa/ tiễn bằng phương tiện</th>
                <th class="p-2 w-[250px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ghi chú</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="dateGroup in sortedShuttleDates" :key="dateGroup">
                <!-- Group Header Row -->
                <tr class="group border-b border-slate-200 h-9">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <button
                      @click="toggleGroupCollapse('shuttle-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('shuttle-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="8" class="p-2 border-r border-slate-200 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>

                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('shuttle-' + dateGroup)"
                  v-for="item in shuttleGroups[dateGroup]"
                  :key="item.id"
                  class="group border-b border-slate-200 h-8"
                >
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ shuttleData.indexOf(item) + 1 }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.id }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.vehicleArrival }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.flightNo }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.date }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.time }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-right text-slate-700 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ formatMoney(item.roomPrice) }}
                  </td>
                  <td class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ item.vehicleDetail }}
                  </td>
                  <td class="p-1 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <input
                      type="text"
                      v-model="item.notes"
                      class="w-full border border-slate-200 rounded px-1.5 py-0.5 text-[11px] bg-slate-50 focus:bg-white focus:border-sky-500 outline-none transition-all"
                    />
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
 
      <!-- Noshow Rooms Page -->
      <div v-else-if="activeTab === 'noshow'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1500px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]"></th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã BK</th>
                <th class="p-2 border-r border-slate-200 w-[200px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên nhóm</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày XN</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Số Đêm</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày Vắng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giờ</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giá Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Người Dùng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ca</th>
                <th class="p-2 w-[250px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Lý Do</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(items, category) in noshowGroups" :key="category">
                <!-- Group Header Row (Category group) -->
                <tr class="group border-b border-slate-200 h-9">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <button
                      @click="toggleGroupCollapse('noshow-' + category)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('noshow-' + category) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="12" class="p-2.5 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ category }}
                  </td>
                </tr>
 
                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('noshow-' + category)"
                  v-for="item in items"
                  :key="item.id + '-' + item.arrivalDate"
                  class="group border-b border-slate-200 h-8"
                >
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ filteredNoshowData.indexOf(item) + 1 }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-black text-slate-700 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.roomNumber }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.id }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.groupName }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.confirmDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.arrivalDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.nights }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.absentDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.time }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-700 font-semibold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ formatMoney(item.price) }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.username }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.shift }}
                  </td>
                  <td
                    class="p-2 text-slate-600 break-words whitespace-normal leading-tight font-semibold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.reason }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
 
      <!-- Guest Birthdays Page -->
      <div v-else-if="activeTab === 'birthdays'" class="p-3 flex-1 flex flex-col overflow-hidden">
        <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-auto flex-1 max-h-full">
          <table class="w-full text-left border-collapse text-xs min-w-[1800px] table-fixed">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
                <th class="p-2 border-r border-slate-200 text-center w-[50px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]"></th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã đăng ký</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Phòng</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Tên khách</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Sinh nhật</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Loại giấy tờ</th>
                <th class="p-2 border-r border-slate-200 text-center w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Hộ chiếu</th>
                <th class="p-2 border-r border-slate-200 w-[180px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Email</th>
                <th class="p-2 border-r border-slate-200 w-[120px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Số điện thoại</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Quốc tịch</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đến</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Ngày đi</th>
                <th class="p-2 border-r border-slate-200 text-center w-[80px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Đêm ở</th>
                <th class="p-2 border-r border-slate-200 text-right w-[110px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Giá Phòng</th>
                <th class="p-2 border-r border-slate-200 text-center w-[100px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Mã giá phòng</th>
                <th class="p-2 w-[160px] sticky top-0 bg-slate-100 z-10 shadow-[0_1px_0_0_rgba(226,232,240,1)]">Công Ty DL</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="dateGroup in sortedBirthdayDates" :key="dateGroup">
                <!-- Group Header Row -->
                <tr class="group border-b border-slate-200 h-9">
                  <td class="p-2 border-r border-slate-200 text-center bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    <button
                      @click="toggleGroupCollapse('birthdays-' + dateGroup)"
                      type="button"
                      class="w-4 h-4 rounded bg-[#c9eeff] hover:bg-[#8ecefa] text-[#0369a1] font-black flex items-center justify-center select-none cursor-pointer border-none transition-colors shrink-0 text-[11px] mx-auto"
                    >
                      {{ isGroupCollapsed('birthdays-' + dateGroup) ? '+' : '-' }}
                    </button>
                  </td>
                  <td colspan="15" class="p-2 border-r border-slate-200 text-left font-black text-slate-800 bg-white group-hover:bg-[#bdecfe]/50 transition-colors">
                    {{ dateGroup }}
                  </td>
                </tr>

                <!-- Child Rows -->
                <tr
                  v-if="!isGroupCollapsed('birthdays-' + dateGroup)"
                  v-for="item in birthdayGroups[dateGroup]"
                  :key="item.id"
                  class="group border-b border-slate-200 h-8"
                >
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ birthdaysData.indexOf(item) + 1 }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.id }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-black text-slate-700 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.roomNumber }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-700 font-semibold break-words whitespace-normal leading-tight transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.name }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.birthday }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.docType }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.docNo }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.email }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-slate-600 break-words whitespace-normal leading-tight transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.phone }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.nationality }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.arrivalDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.departureDate }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center text-slate-600 font-bold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.nights }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-right text-slate-700 font-semibold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ formatMoney(item.price) }}
                  </td>
                  <td
                    class="p-2 border-r border-slate-200 text-center font-bold text-slate-600 transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.rateCode }}
                  </td>
                  <td
                    class="p-2 text-slate-600 break-words whitespace-normal leading-tight font-semibold transition-colors group-hover:bg-[#bdecfe]/50"
                    :class="[item.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
                  >
                    {{ item.company }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Sticky footer info bar (Matching screenshots summary) -->
    <div class="px-4 py-2 border-t border-slate-200 bg-slate-50/50 flex items-center gap-6 shrink-0 text-xs font-bold text-slate-700 select-none">
      <div class="flex items-center gap-1.5">
        <span class="text-slate-400 uppercase tracking-wide text-[10px]">{{ footerStats.label1 }}</span>
        <span class="text-slate-800 text-sm font-black">{{ footerStats.val1 }}</span>
      </div>
      <div class="flex items-center gap-1.5 border-l border-slate-200 pl-6">
        <span class="text-slate-400 uppercase tracking-wide text-[10px]">{{ footerStats.label2 }}</span>
        <span class="text-slate-800 text-sm font-black">{{ footerStats.val2 }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
