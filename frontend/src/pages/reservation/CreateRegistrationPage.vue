<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import {
  fetchMarkets,
  fetchCustomerSources,
  fetchBookers,
  fetchCompanies,
  fetchUsers,
} from '@/services/company-service'
import {
  fetchBookings,
  createBooking,
  updateBooking,
  deleteBooking,
  fetchPaymentMethods,
  fetchRegistrationStatuses,
  fetchRoomClasses,
  fetchRoomRateCodes,
  fetchHotelSettings,
  fetchSystemTime,
  fetchPayments,
  createPayment,
  updatePayment,
  deletePayment,
  splitPayment,
  transferPayment,
  fetchCurrencies,
  fetchAvailability,
  checkAvailability
} from '@/services/booking-service'

const uiStore = useUiStore()

// ==================== CONFIG & SYSTEM ====================
const systemDate = ref(new Date().toISOString().split('T')[0])
const hotelSettings = ref({})
const currenciesList = ref([])
const activeCurrency = computed(() => {
  return currenciesList.value.find(c => c.is_main) || { code: 'VND', decimals_to_round: 0 }
})

// ==================== DROPDOWN DATA (từ API) ====================
const markets = ref([])
const customerSources = ref([])
const bookers = ref([])
const companies = ref([])
const paymentMethods = ref([])
const registrationStatuses = ref([])
const users = ref([])
const roomClasses = ref([])
const roomRateCodes = ref([])

// ==================== TAB MANAGEMENT ====================
const tabs = ref([])
const activeTabId = ref(null)
const isLoadingBookings = ref(false)

// ==================== MODAL STATES ====================
const isModalOpen = ref(false)
const isEditModal = ref(false)
const isSavingModal = ref(false)
const modalSubTab = ref('info')

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

function closeModal() {
  isModalOpen.value = false
}

// ==================== UI STATES ====================
const showChucNangMenu = ref(false)
const selectedRoomAction = ref('0')

// ==================== MODAL FORM ====================
const emptyForm = () => ({
  dbId: null,
  bookingCode: '',
  bookingName: '',
  checkIn: new Date().toISOString().split('T')[0],
  checkOut: '',
  nights: 1,
  registrationStatusId: null,
  confirmDate: '',
  expiredDate: '',
  companyId: null,
  paymentMethodId: null,
  paymentValue: 0,
  externalBookingCode: '',
  salesPerson: '',
  isGit: false,
  hasVat: false,
  marketId: null,
  customerSourceId: null,
  bookerId: null,
  contactName: '',
  contactEmail: '',
  contactPhone: '',
  note: '',
  specialRequests: '',
  shuttleInfo: [],
  roomAllocations: [],
  deposits: [],
})

const modalForm = ref(emptyForm())

// ==================== DEPOSIT MODAL STATE & ACTIONS ====================
const isDepositModalOpen = ref(false)
const selectedDepositIds = ref([])
const depositForm = ref({
  id: null,
  amount: 0,
  paymentMethodId: null,
  bankAccountId: 'Tài khoản ngân hàng',
  date: new Date().toISOString().split('T')[0],
  note: '',
  recipient: 'Admin',
  image: null
})

async function syncDepositsFromBackend() {
  if (!modalForm.value.dbId) return
  try {
    const res = await fetchPayments(modalForm.value.dbId)
    const paymentsList = res.data?.data || res.data || []
    modalForm.value.deposits = paymentsList.map(p => ({
      id: p.id,
      date: p.date ? p.date.substring(0, 10).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: [],
      status: p.status,
      edit_flag: p.edit_flag,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account
    }))
    // Tính tổng các cọc active (edit_flag = 0 và pack2 = DPR)
    const activeDeposits = paymentsList.filter(p => p.edit_flag === 0 && p.pack2 === 'DPR')
    modalForm.value.paymentValue = activeDeposits.reduce((sum, d) => sum + Number(d.amount), 0)
    
    // Đồng bộ lại paymentValue cho Tab active hiện tại
    const activeTab = tabs.value.find(t => t.dbId === modalForm.value.dbId)
    if (activeTab) {
      activeTab.deposit = modalForm.value.paymentValue
      activeTab.paymentValue = modalForm.value.paymentValue
    }
  } catch (err) {
    console.error('Lỗi đồng bộ cọc:', err)
  }
}

async function openDepositModal() {
  depositForm.value = {
    id: null,
    amount: 0,
    paymentMethodId: paymentMethods.value[0]?.id || null,
    bankAccountId: 'Tài khoản ngân hàng',
    date: new Date().toISOString().split('T')[0],
    note: '',
    recipient: 'Admin',
    image: null
  }
  selectedDepositIds.value = []
  
  if (modalForm.value.dbId) {
    await syncDepositsFromBackend()
  } else {
    if (!modalForm.value.deposits) {
      modalForm.value.deposits = []
      modalForm.value.paymentValue = 0
    }
  }
  isDepositModalOpen.value = true
}

function handleDepositImageUpload(event) {
  const file = event.target.files[0]
  if (file) {
    depositForm.value.image = URL.createObjectURL(file)
  }
}

async function addDeposit() {
  if (!depositForm.value.amount || depositForm.value.amount <= 0) {
    uiStore.showToast('Vui lòng nhập số tiền đặt cọc hợp lệ!', 'warning')
    return
  }
  
  if (modalForm.value.dbId) {
    try {
      const payload = {
        date: depositForm.value.date,
        amount: Number(depositForm.value.amount),
        payment_method_id: depositForm.value.paymentMethodId,
        description: depositForm.value.note || 'Đặt cọc',
        debit_account: depositForm.value.bankAccountId || 'Tài khoản ngân hàng',
      }
      await createPayment(modalForm.value.dbId, payload)
      await syncDepositsFromBackend()
      uiStore.showToast('Đã thêm đặt cọc mới thành công!', 'success')
      depositForm.value.amount = 0
      depositForm.value.note = ''
      depositForm.value.image = null
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể thêm cọc!', 'error')
    }
  } else {
    const now = new Date()
    const timeStr = now.toTimeString().split(' ')[0].substring(0, 5)
    
    const newDep = {
      id: Date.now(),
      date: depositForm.value.date.split('-').reverse().join('/'),
      time: timeStr,
      paymentMethodId: depositForm.value.paymentMethodId,
      note: depositForm.value.note || 'Đặt cọc',
      amount: Number(depositForm.value.amount),
      currency: activeCurrency.value.code || 'VND',
      recipient: depositForm.value.recipient || 'Admin',
      images: depositForm.value.image ? ['Chứng từ'] : []
    }
    
    if (!modalForm.value.deposits) {
      modalForm.value.deposits = []
    }
    modalForm.value.deposits.push(newDep)
    modalForm.value.paymentValue = modalForm.value.deposits.reduce((sum, d) => sum + d.amount, 0)
    
    depositForm.value.amount = 0
    depositForm.value.note = ''
    depositForm.value.image = null
    uiStore.showToast('Đã thêm đặt cọc mới!', 'success')
  }
}

function editDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để sửa!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = modalForm.value.deposits.find(d => d.id === targetId)
  if (dep) {
    let dateVal = dep.date
    if (dateVal.includes('/')) {
      dateVal = dateVal.split('/').reverse().join('-')
    }
    depositForm.value = {
      id: dep.id,
      amount: dep.amount,
      paymentMethodId: dep.paymentMethodId,
      bankAccountId: dep.bankAccountId || 'Tài khoản ngân hàng',
      date: dateVal,
      note: dep.note,
      recipient: dep.recipient,
      image: dep.images?.[0] || null
    }
  }
}

async function saveDeposit() {
  if (!depositForm.value.id) {
    isDepositModalOpen.value = false
    return
  }
  
  if (modalForm.value.dbId) {
    try {
      const payload = {
        date: depositForm.value.date,
        amount: Number(depositForm.value.amount),
        payment_method_id: depositForm.value.paymentMethodId,
        description: depositForm.value.note,
        debit_account: depositForm.value.bankAccountId,
      }
      await updatePayment(depositForm.value.id, payload)
      await syncDepositsFromBackend()
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
      depositForm.value.id = null
      depositForm.value.amount = 0
      depositForm.value.note = ''
      depositForm.value.image = null
      selectedDepositIds.value = []
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể sửa cọc!', 'error')
    }
  } else {
    const idx = modalForm.value.deposits.findIndex(d => d.id === depositForm.value.id)
    if (idx !== -1) {
      modalForm.value.deposits[idx].amount = Number(depositForm.value.amount)
      modalForm.value.deposits[idx].paymentMethodId = depositForm.value.paymentMethodId
      modalForm.value.deposits[idx].date = depositForm.value.date.split('-').reverse().join('/')
      modalForm.value.deposits[idx].note = depositForm.value.note
      modalForm.value.deposits[idx].images = depositForm.value.image ? ['Chứng từ'] : []
      
      modalForm.value.paymentValue = modalForm.value.deposits.reduce((sum, d) => sum + d.amount, 0)
      
      depositForm.value.id = null
      depositForm.value.amount = 0
      depositForm.value.note = ''
      depositForm.value.image = null
      selectedDepositIds.value = []
      uiStore.showToast('Đã cập nhật đặt cọc thành công!', 'success')
    }
  }
}

async function deleteDeposits() {
  if (selectedDepositIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn các cọc muốn xóa!', 'warning')
    return
  }
  
  if (modalForm.value.dbId) {
    uiStore.confirm({
      title: 'Hủy/Xóa đặt cọc',
      message: 'Bạn có chắc chắn muốn xóa đặt cọc này? Hệ thống sẽ tạo dòng đối trừ âm.',
      confirmText: 'Đồng ý',
      cancelText: 'Quay lại'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        for (const depId of selectedDepositIds.value) {
          await deletePayment(depId)
        }
        await syncDepositsFromBackend()
        uiStore.showToast('Đã xóa đặt cọc (tạo đối trừ) thành công!', 'success')
        selectedDepositIds.value = []
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa cọc!', 'error')
      }
    })
  } else {
    modalForm.value.deposits = modalForm.value.deposits.filter(d => !selectedDepositIds.value.includes(d.id))
    modalForm.value.paymentValue = modalForm.value.deposits.reduce((sum, d) => sum + d.amount, 0)
    selectedDepositIds.value = []
    uiStore.showToast('Đã xóa cọc thành công!', 'success')
  }
}

async function splitDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để tách!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = modalForm.value.deposits.find(d => d.id === targetId)
  if (!dep) return

  if (!modalForm.value.dbId) {
    uiStore.showToast('Chỉ có thể tách cọc của booking đã lưu.', 'warning')
    return
  }

  const amtStr = window.prompt(`Nhập các số tiền sau khi tách, cách nhau bởi dấu phẩy (Ví dụ: 500000, 400000). Tổng phải bằng ${dep.amount.toLocaleString()} VND:`)
  if (!amtStr) return

  const amounts = amtStr.split(',').map(s => Number(s.trim())).filter(n => !isNaN(n) && n > 0)
  if (amounts.length < 2) {
    uiStore.showToast('Vui lòng nhập ít nhất 2 số tiền hợp lệ!', 'warning')
    return
  }

  try {
    await splitPayment(targetId, { amounts })
    await syncDepositsFromBackend()
    uiStore.showToast('Tách cọc thành công!', 'success')
    selectedDepositIds.value = []
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể tách cọc!', 'error')
  }
}

async function transferDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để chuyển!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  if (!modalForm.value.dbId) {
    uiStore.showToast('Chỉ có thể chuyển cọc của booking đã lưu.', 'warning')
    return
  }

  const destCode = window.prompt('Nhập mã booking đích muốn chuyển cọc sang (Ví dụ: GAL0012):')
  if (!destCode) return

  try {
    const res = await fetchBookings({ search: destCode.trim() })
    const bookings = res.data?.data || res.data || []
    const targetBooking = bookings.find(b => b.booking_code.toUpperCase() === destCode.trim().toUpperCase())
    
    if (!targetBooking) {
      uiStore.showToast(`Không tìm thấy booking có mã "${destCode}"!`, 'error')
      return
    }

    uiStore.confirm({
      title: 'Chuyển đặt cọc',
      message: `Bạn có chắc chắn muốn chuyển cọc sang booking "${targetBooking.booking_code} - ${targetBooking.booking_name}"?`,
      confirmText: 'Chuyển', cancelText: 'Hủy'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        await transferPayment(targetId, { target_booking_id: targetBooking.id })
        await syncDepositsFromBackend()
        uiStore.showToast(`Đã chuyển cọc sang booking ${targetBooking.booking_code} thành công!`, 'success')
        selectedDepositIds.value = []
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Lỗi khi chuyển cọc!', 'error')
      }
    })
  } catch (err) {
    uiStore.showToast('Không thể xác thực booking đích!', 'error')
  }
}

// ==================== TABLE UI STATES ====================
const isEditing = ref(false)
const searchQuery = ref('')
const selectedRows = ref([])
const collapsedSections = ref({
  registrationStatus: false,
  superiorTriple: false,
  deluxeDouble: false
})

const visibleColumns = ref({
  roomType: true,
  dates: true,
  occupancy: false, // Chiếm dụng
  availability: true, // Phòng trống
  quantity: true, // Số lượng
  price: true, // Giá phòng
  rateCode: true, // Mã giá phòng/Gói
  discount: true, // Tăng/Giảm giá
  upgrade: true, // Nâng hạng phòng
  adults: true, // Người lớn
  babies: true, // Em bé
  children: true, // Trẻ em
  childBreakfastRate: true, // Giá ăn sáng trẻ em
  breakfast: true, // Ăn sáng
})
const showColumnSelector = ref(false)

const columns = ref([
  { key: 'type', label: 'Loại phòng', visible: true, width: 'w-[140px]' },
  { key: 'shape', label: 'Dạng phòng', visible: true, width: 'w-[90px]', center: true },
  { key: 'roomNumber', label: 'Số phòng', visible: true, width: 'w-[80px]', center: true },
  { key: 'checkIn', label: 'Ngày đến', visible: true, width: 'w-[95px]', center: true },
  { key: 'checkOut', label: 'Ngày đi', visible: true, width: 'w-[95px]', center: true },
  { key: 'nights', label: 'Đêm', visible: true, width: 'w-[60px]', center: true },
  { key: 'price', label: 'Giá', visible: true, width: 'w-[95px]', right: true },
  { key: 'rateCode', label: 'Mã giá phòng', visible: true, width: 'w-[160px]' },
  { key: 'adjustment', label: 'Giảm/tăng giá', visible: true, width: 'w-[110px]', right: true },
  { key: 'guestName', label: 'Tên khách', visible: true, width: 'w-[160px]' },
  { key: 'adults', label: 'N.Lớn', visible: true, width: 'w-[65px]', center: true },
  { key: 'babies', label: 'Em bé', visible: true, width: 'w-[65px]', center: true },
  { key: 'children', label: 'Trẻ em', visible: true, width: 'w-[65px]', center: true },
  { key: 'childBreakfast', label: 'Chi tiết ăn sáng trẻ', visible: true, width: 'w-[130px]', center: true },
  { key: 'breakfast', label: 'Ăn sáng', visible: true, width: 'w-[75px]', center: true },
  { key: 'extraBed', label: 'Thêm giường', visible: true, width: 'w-[100px]', center: true },
  { key: 'extraBedPrice', label: 'Giá thêm giường', visible: true, width: 'w-[115px]', right: true },
  { key: 'hourly', label: 'Ở theo giờ', visible: true, width: 'w-[85px]', center: true },
  { key: 'specialRequests', label: 'Yêu cầu đặc biệt', visible: true, width: 'w-[120px]', center: true },
  { key: 'arrivalTime', label: 'Giờ đến', visible: true, width: 'w-[75px]', center: true },
  { key: 'hoursOut', label: 'Giờ đi', visible: true, width: 'w-[75px]', center: true },
  { key: 'isPreassigned', label: 'Đặt trước', visible: true, width: 'w-[80px]', center: true },
  { key: 'initialRoomClass', label: 'LP Khởi tạo', visible: true, width: 'w-[110px]' },
  { key: 'transferredFrom', label: 'Phòng chuyển', visible: true, width: 'w-[100px]', center: true },
  { key: 'roomStatus', label: 'Trạng thái phòng', visible: true, width: 'w-[120px]', center: true },
  { key: 'allotmentCode', label: 'Mã ALM', visible: true, width: 'w-[100px]' },
  { key: 'roomCode', label: 'Mã phòng', visible: true, width: 'w-[100px]' },
])
const showTableColumnSelector = ref(false)
const draggedColKey = ref(null)

function handleDragStart(key) {
  draggedColKey.value = key
}

function handleDrop(targetKey) {
  if (!draggedColKey.value || draggedColKey.value === targetKey) return
  const fromIdx = columns.value.findIndex(c => c.key === draggedColKey.value)
  const toIdx = columns.value.findIndex(c => c.key === targetKey)
  if (fromIdx !== -1 && toIdx !== -1) {
    const [moved] = columns.value.splice(fromIdx, 1)
    columns.value.splice(toIdx, 0, moved)
  }
  draggedColKey.value = null
}

// ==================== COMPUTEDS ====================
const activeTab = computed(() => tabs.value.find(t => t.id === activeTabId.value))

const filteredActiveRooms = computed(() => {
  if (!activeTab.value) return []
  if (!searchQuery.value) return activeTab.value.rooms
  const q = searchQuery.value.toLowerCase()
  return activeTab.value.rooms.filter(r =>
    r.type.toLowerCase().includes(q) ||
    r.guestName.toLowerCase().includes(q) ||
    (r.roomNumber && r.roomNumber.toLowerCase().includes(q))
  )
})

const selectRangeVal = computed({
  get() {
    if (!activeTab.value) return 0
    return activeTab.value.rooms.filter(r => selectedRows.value.includes(r.id)).length
  },
  set(val) {
    if (!activeTab.value) return
    const rooms = activeTab.value.rooms
    const countToSelect = Math.min(Number(val), rooms.length)
    const newSelected = []
    for (let i = 0; i < countToSelect; i++) newSelected.push(rooms[i].id)
    selectedRows.value = newSelected
  }
})

const roomsTotalSummary = computed(() => {
  if (!activeTab.value) return { count: 0, priceSum: 0, adults: 0, babies: 0, children: 0, extraBed: 0, total: 0 }
  let priceSum = 0, adults = 0, babies = 0, children = 0, extraBed = 0, total = 0
  activeTab.value.rooms.forEach(r => {
    priceSum += Number(r.price) || 0
    adults   += Number(r.adults) || 0
    babies   += Number(r.babies) || 0
    children += Number(r.children) || 0
    extraBed += Number(r.extraBedPrice) || 0
    total    += Number(r.total) || 0
  })
  return { count: activeTab.value.rooms.length, priceSum, adults, babies, children, extraBed, total }
})

const activeTabStatusName = computed(() => {
  if (!activeTab.value) return '—'
  if (activeTab.value.registrationStatusId) {
    const s = registrationStatuses.value.find(rs => rs.id === activeTab.value.registrationStatusId)
    if (s) return s.name
  }
  return activeTab.value.statusLabel || '—'
})

const allocationsSummary = computed(() => {
  let availSum = 0
  let qtySum = 0
  let adultSum = 0
  let babySum = 0
  let childSum = 0
  
  if (modalForm.value && modalForm.value.roomAllocations) {
    modalForm.value.roomAllocations.forEach(row => {
      availSum += Number(row.availableRooms) || 0
      qtySum += Number(row.quantity) || 0
      adultSum += Number(row.adults) || 0
      babySum += Number(row.babies) || 0
      childSum += Number(row.children) || 0
    })
  }

  return {
    count: modalForm.value?.roomAllocations?.length || 0,
    availableRooms: availSum,
    quantity: qtySum,
    adults: adultSum,
    babies: babySum,
    children: childSum
  }
})

const groupedRooms = computed(() => {
  if (!activeTab.value || !activeTab.value.rooms) return {}
  const groups = {}
  filteredActiveRooms.value.forEach(room => {
    if (!groups[room.type]) {
      groups[room.type] = []
    }
    groups[room.type].push(room)
  })
  return groups
})

function toggleGroupCollapse(typeName) {
  if (collapsedSections.value[typeName] === undefined) {
    collapsedSections.value[typeName] = true
  } else {
    collapsedSections.value[typeName] = !collapsedSections.value[typeName]
  }
}

function syncRoomsToAllocations(tab) {
  if (!tab.roomAllocations || !tab.rooms) return tab.roomAllocations
  return tab.roomAllocations.map(alloc => {
    const matchingRooms = tab.rooms.filter(r => r.roomClassId === alloc.roomClassId)
    const roomsDetail = matchingRooms.map(r => ({
      roomNumber: r.roomNumber || '',
      rateCode: r.rateCode || '',
      guestName: r.guestName || '',
      adults: Number(r.adults) || 2,
      babies: Number(r.babies) || 0,
      children: Number(r.children) || 0,
      breakfast: r.breakfast !== undefined ? !!r.breakfast : true,
      extraBedPrice: Number(r.extraBedPrice) || 0,
      hourly: !!r.hourly,
      arrivalTime: r.arrivalTime || '14:00',
      hoursOut: r.hoursOut || '12:00',
      isPreassigned: !!r.isPreassigned,
      initialRoomClass: r.initialRoomClass || '',
      transferredFrom: r.transferredFrom || '',
      roomStatus: r.roomStatus || 'Sạch',
      allotmentCode: r.allotmentCode || '',
      roomCode: r.roomCode || '',
      total: Number(r.total) || 0,
    }))
    return {
      ...alloc,
      rooms: roomsDetail,
    }
  })
}

// ==================== LIFECYCLE ====================
onMounted(async () => {
  await Promise.all([loadDropdowns(), loadBookings()])
})

async function loadDropdowns() {
  try {
    const [mRes, csRes, bRes, cRes, pmRes, rsRes, uRes, rcRes, rrcRes, currRes] = await Promise.allSettled([
      fetchMarkets(),
      fetchCustomerSources(),
      fetchBookers(),
      fetchCompanies(),
      fetchPaymentMethods(),
      fetchRegistrationStatuses(),
      fetchUsers(),
      fetchRoomClasses(),
      fetchRoomRateCodes(),
      fetchCurrencies(),
    ])
    markets.value              = mRes.status  === 'fulfilled' ? (mRes.value.data?.data  || mRes.value.data  || []) : []
    customerSources.value      = csRes.status === 'fulfilled' ? (csRes.value.data?.data || csRes.value.data || []) : []
    bookers.value              = bRes.status  === 'fulfilled' ? (bRes.value.data?.data  || bRes.value.data  || []) : []
    companies.value            = cRes.status  === 'fulfilled' ? (cRes.value.data?.data  || cRes.value.data  || []) : []
    paymentMethods.value       = pmRes.status === 'fulfilled' ? (pmRes.value.data?.data || pmRes.value.data || []) : []
    registrationStatuses.value = rsRes.status === 'fulfilled' ? (rsRes.value.data?.data || rsRes.value.data || []) : []
    users.value                = uRes.status  === 'fulfilled' ? (uRes.value.data?.data  || uRes.value.data  || []) : []
    roomClasses.value          = rcRes.status === 'fulfilled' ? (rcRes.value.data?.data || rcRes.value.data || []) : []
    roomRateCodes.value        = rrcRes.status === 'fulfilled' ? (rrcRes.value.data?.data || rrcRes.value.data || []) : []
    currenciesList.value       = currRes.status === 'fulfilled' ? (currRes.value.data?.data || currRes.value.data || []) : []
    hotelSettings.value        = await fetchHotelSettings().then(r => r.data?.data || r.data || {}).catch(() => ({}))
    
    try {
        const sysTimeRes = await fetchSystemTime();
        systemDate.value = sysTimeRes.data?.system_date || new Date().toISOString().split('T')[0];
    } catch(e) {}
  } catch (err) {
    console.error('Error loading dropdowns:', err)
  }
}

async function loadBookings() {
  isLoadingBookings.value = true
  try {
    const res = await fetchBookings({ status: 0 })
    const list = res.data?.data || res.data || []
    tabs.value = list.map(b => bookingToTab(b))
    if (tabs.value.length > 0) activeTabId.value = tabs.value[0].id
    else { tabs.value = [makeBlankTab()]; activeTabId.value = tabs.value[0].id }
  } catch (err) {
    if (tabs.value.length === 0) { tabs.value = [makeBlankTab()]; activeTabId.value = tabs.value[0].id }
    console.error('Lỗi khi tải bookings:', err)
  } finally {
    isLoadingBookings.value = false
  }
}

function bookingToTab(b) {
  const rooms = []
  let idCounter = 1

  // Nếu API trả về booking_rooms (dữ liệu mới)
  if (b.booking_rooms && b.booking_rooms.length > 0) {
    b.booking_rooms.forEach(br => {
      const rc = br.room_class || {}
      const physicalRoom = br.room || {}
      
      let nightsCount = 1
      const ci = new Date(br.arrival_date || b.arrival_date)
      const co = new Date(br.departure_date || b.departure_date)
      if (!isNaN(ci) && !isNaN(co)) {
        const diff = Math.ceil((co - ci) / 86400000)
        nightsCount = diff > 0 ? diff : 1
      }
      const priceNum = Number(br.rate) || 0
      const totalNum = priceNum * nightsCount

      const primaryGuestPivot = br.guests ? br.guests.find(g => g.is_primary) || br.guests[0] : null
      const guestName = primaryGuestPivot && primaryGuestPivot.guest ? primaryGuestPivot.guest.full_name : ''
      const childrenCount = br.children ? br.children.filter(c => c.age_group === 'child').length : 0
      const babiesCount = br.children ? br.children.filter(c => c.age_group === 'baby').length : 0

      rooms.push({
        id: idCounter++,
        bookingRoomId: br.id, // lưu lại id để edit nếu cần
        type: rc.name || 'Unknown Class',
        shape: rc.code || '',
        roomNumber: physicalRoom.room_number || '',
        checkIn: formatDateVi(br.arrival_date || b.arrival_date),
        checkOut: formatDateVi(br.departure_date || b.departure_date),
        nights: nightsCount,
        price: priceNum,
        rateCode: 'Vui lòng chọn giá phòng',
        guestName: guestName,
        adults: br.adults || 2,
        babies: babiesCount,
        children: childrenCount,
        breakfast: true,
        extraBedPrice: br.extra_bed_rate || 0,
        hourly: false,
        arrivalTime: br.arrival_time || '14:00',
        hoursOut: br.departure_time || '12:00',
        isPreassigned: !!physicalRoom.room_number,
        initialRoomClass: rc.code || '',
        transferredFrom: '',
        roomStatus: 'Sạch',
        allotmentCode: '',
        roomCode: '',
        total: totalNum,
        roomClassId: br.room_class_id,
      })
    })
  } else {
    // Dữ liệu cũ (nếu còn)
    const roomAllocations = b.room_allocations || []
    roomAllocations.forEach(alloc => {
      const qty = Number(alloc.quantity) || 0
      if (qty <= 0) return

      let nightsCount = 1
      const ci = new Date(alloc.arrivalDate || b.arrival_date)
      const co = new Date(alloc.departureDate || b.departure_date)
      if (!isNaN(ci) && !isNaN(co)) {
        const diff = Math.ceil((co - ci) / 86400000)
        nightsCount = diff > 0 ? diff : 1
      }

      const priceNum = Number(alloc.price) || 0
      const totalNum = priceNum * nightsCount

      const rc = roomClasses.value.find(c => c.id === alloc.roomClassId || c.code === alloc.roomClassCode)
      const typeName = alloc.roomClassName || (rc ? rc.name : (alloc.roomClassCode || 'Unknown Class'))
      const shapeName = alloc.roomClassCode || (rc ? rc.code : '')

      const roomsInAlloc = alloc.rooms || alloc.roomsDetail || []
      for (let i = 0; i < qty; i++) {
        const roomDetail = roomsInAlloc[i] || {}
        rooms.push({
          id: idCounter++,
          type: typeName,
          shape: shapeName,
          roomNumber: roomDetail.roomNumber || '',
          checkIn: formatDateVi(alloc.arrivalDate || b.arrival_date),
          checkOut: formatDateVi(alloc.departureDate || b.departure_date),
          nights: nightsCount,
          price: priceNum,
          rateCode: roomDetail.rateCode || alloc.ratePlanCode || 'Vui lòng chọn giá phòng',
          guestName: roomDetail.guestName || '',
          adults: Number(roomDetail.adults || alloc.adults) || (rc?.code?.toLowerCase().includes('tr') || rc?.code?.toLowerCase().includes('t') ? 3 : 2),
          babies: Number(roomDetail.babies || alloc.babies) || 0,
          children: Number(roomDetail.children || alloc.children) || 0,
          breakfast: roomDetail.breakfast !== undefined ? !!roomDetail.breakfast : !!alloc.breakfastIncluded,
          extraBedPrice: Number(roomDetail.extraBedPrice) || 0,
          hourly: !!roomDetail.hourly,
          arrivalTime: roomDetail.arrivalTime || '14:00',
          hoursOut: roomDetail.hoursOut || '12:00',
          isPreassigned: roomDetail.isPreassigned !== undefined ? !!roomDetail.isPreassigned : false,
          initialRoomClass: roomDetail.initialRoomClass || shapeName,
          transferredFrom: roomDetail.transferredFrom || '',
          roomStatus: roomDetail.roomStatus || 'Sạch',
          allotmentCode: roomDetail.allotmentCode || '',
          roomCode: roomDetail.roomCode || '',
          total: Number(roomDetail.total) || totalNum,
          roomClassId: alloc.roomClassId,
        })
      }
    })
  }

  return {
    id: b.booking_code,
    dbId: b.id,
    title: `Booking ${b.booking_code}`,
    bookingName: b.booking_name,
    statusLabel: b.registration_status?.name || '—',
    registrationStatusId: b.registration_status_id,
    checkIn: b.arrival_date,
    checkOut: b.departure_date,
    nights: b.num_of_days,
    deposit: b.payment_value || 0,
    company: b.company?.name || '—',
    companyId: b.company_id,
    confirmDate: b.confirm_date || '',
    marketId: b.market_id,
    market: b.market?.name || '—',
    customerSourceId: b.customer_source_id,
    customerSource: b.customer_source?.name || '—',
    bookerId: b.booker_id,
    contactName: b.contact_name || '',
    contactEmail: b.contact_email || '',
    contactPhone: b.contact_phone || '',
    paymentMethodId: b.payment_method_id,
    paymentValue: b.payment_value || 0,
    externalBookingCode: b.external_booking_code || '',
    salesPerson: b.sales_person || '',
    isGit: b.is_git || false,
    hasVat: b.has_vat || false,
    note: b.note || '',
    specialRequests: b.special_requests || '',
    shuttleInfo: b.shuttle_info || [],
    roomAllocations: b.room_allocations || [],
    deposits: b.payments ? b.payments.map(p => ({
      id: p.id,
      date: p.date ? p.date.substring(0, 10).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: [],
      status: p.status,
      edit_flag: p.edit_flag,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account
    })) : [],
    rooms: rooms,
  }
}

function makeBlankTab() {
  const today = new Date().toISOString().split('T')[0]
  const tomorrowDate = new Date(); tomorrowDate.setDate(tomorrowDate.getDate() + 1)
  const tomorrow = tomorrowDate.toISOString().split('T')[0]
  return {
    id: 'NEW_BOOKING', dbId: null, title: 'Đăng ký mới', bookingName: 'Chưa có thông tin',
    statusLabel: 'Mới', registrationStatusId: null,
    checkIn: today, checkOut: tomorrow, nights: 1, deposit: 0,
    company: '—', companyId: null, confirmDate: today,
    marketId: null, market: '—', customerSourceId: null, customerSource: '—',
    bookerId: null, contactName: '', contactEmail: '', contactPhone: '',
    paymentMethodId: null, paymentValue: 0, externalBookingCode: '',
    salesPerson: '', isGit: false, hasVat: false, note: '', specialRequests: '',
    rooms: [],
  }
}

// ==================== TAB HANDLERS ====================
function handleTabClick(tabId) { activeTabId.value = tabId }

function handleCloseTab(tabId, event) {
  event.stopPropagation()
  const index = tabs.value.findIndex(t => t.id === tabId)
  tabs.value = tabs.value.filter(t => t.id !== tabId)
  if (activeTabId.value === tabId) {
    if (tabs.value.length > 0) activeTabId.value = tabs.value[Math.max(0, index - 1)].id
    else {
      tabs.value = [makeBlankTab()]
      activeTabId.value = tabs.value[0].id
    }
  }
}

function initRoomAllocations(existing = [], checkInDate, checkOutDate) {
  return roomClasses.value.map(rc => {
    const found = existing.find(e => e.roomClassId === rc.id || e.roomClassCode === rc.code)
    if (found) {
      return {
        ...found,
        roomClassId: rc.id,
        roomClassCode: rc.code,
        roomClassName: rc.name,
      }
    }
    // Mock available room count for view
    let defaultAvail = 3
    if (rc.code === 'SUPD') defaultAvail = 0
    else if (rc.code === 'SUPTR' || rc.code === 'DLXDB') defaultAvail = 9
    else if (rc.code === 'DLXTB') defaultAvail = 8
    else if (rc.code === 'DLXT') defaultAvail = 4
    else if (rc.code === 'JST') defaultAvail = 1

    return {
      roomClassId: rc.id,
      roomClassCode: rc.code,
      roomClassName: rc.name,
      arrivalDate: checkInDate,
      departureDate: checkOutDate,
      availableRooms: defaultAvail,
      quantity: 0,
      price: 0,
      ratePlanCode: '',
      discount: 'Tăng/Giảm giá',
      upgradeRoomClassId: null,
      adults: rc.code.toLowerCase().includes('tr') || rc.code.toLowerCase().includes('t') ? 3 : 2,
      babies: 0,
      children: 0,
      childBreakfastRate: hotelSettings.value?.breakfast_child_rate || 90000,
      breakfastIncluded: hotelSettings.value?.booking_auto_extra_charge_bf_child ? true : (rc.code.toLowerCase().includes('tr') || rc.code.toLowerCase().includes('t') ? true : false),
    }
  })
}

async function handleAddTabClick() {
  isEditModal.value = false
  modalPos.value = { x: 0, y: 0 }
  const today = new Date().toISOString().split('T')[0]
  const tomorrowDate = new Date(); tomorrowDate.setDate(tomorrowDate.getDate() + 1)
  const tomorrow = tomorrowDate.toISOString().split('T')[0]

  modalForm.value = {
    ...emptyForm(),
    checkIn: today,
    checkOut: tomorrow,
    nights: 1,
    registrationStatusId: registrationStatuses.value[0]?.id || null,
    paymentMethodId: paymentMethods.value[0]?.id || null,
    marketId: markets.value[0]?.id || null,
    customerSourceId: customerSources.value[0]?.id || null,
    shuttleInfo: [
      { id: Date.now(), type: 'Đón', vehicle: '7 Seater car', code: '', date: today, time: '00:00', price: 0, location: '', note: '' }
    ],
    roomAllocations: initRoomAllocations([], today, tomorrow),
  }
  await updateRoomAvailability()
  modalSubTab.value = 'info'
  isModalOpen.value = true
}

async function openEditModal() {
  const tab = activeTab.value
  if (!tab) return
  modalPos.value = { x: 0, y: 0 }
  isEditModal.value = true
  modalForm.value = {
    dbId: tab.dbId,
    bookingCode: tab.id,
    bookingName: tab.bookingName,
    checkIn: tab.checkIn,
    checkOut: tab.checkOut,
    nights: tab.nights,
    registrationStatusId: tab.registrationStatusId,
    confirmDate: tab.confirmDate || '',
    expiredDate: tab.expiredDate || '',
    companyId: tab.companyId,
    paymentMethodId: tab.paymentMethodId,
    paymentValue: tab.paymentValue || 0,
    externalBookingCode: tab.externalBookingCode || '',
    salesPerson: tab.salesPerson || '',
    isGit: tab.isGit || false,
    hasVat: tab.hasVat || false,
    marketId: tab.marketId,
    customerSourceId: tab.customerSourceId,
    bookerId: tab.bookerId,
    contactName: tab.contactName || '',
    contactEmail: tab.contactEmail || '',
    contactPhone: tab.contactPhone || '',
    note: tab.note || '',
    specialRequests: tab.specialRequests || '',
    shuttleInfo: JSON.parse(JSON.stringify(tab.shuttleInfo || [])),
    roomAllocations: initRoomAllocations(tab.roomAllocations || [], tab.checkIn, tab.checkOut),
    deposits: JSON.parse(JSON.stringify(tab.deposits || [])),
    rooms: JSON.parse(JSON.stringify(tab.rooms || [])),
  }
  await updateRoomAvailability()
  modalSubTab.value = 'info'
  isModalOpen.value = true
}

function handleBookerChange() {
  const selected = bookers.value.find(b => b.id === Number(modalForm.value.bookerId))
  if (selected) {
    modalForm.value.contactName  = selected.name
    modalForm.value.contactEmail = selected.email || ''
    modalForm.value.contactPhone = selected.phone || ''
  }
}

function handleCompanyChange() {
  const selected = companies.value.find(c => c.id === Number(modalForm.value.companyId))
  if (selected) {
    if (selected.market_id) modalForm.value.marketId = selected.market_id
    if (selected.customer_source_id) modalForm.value.customerSourceId = selected.customer_source_id
  }
}

watch(() => modalForm.value.registrationStatusId, (newId) => {
  if (newId) {
    const st = registrationStatuses.value.find(rs => rs.id === newId)
    if (st && st.is_availability === false) {
      uiStore.showToast('Chú ý: Tình trạng đăng ký này không giữ phòng trống (is_availability = 0)', 'info')
    }
  }
})

async function updateRoomAvailability() {
  if (!modalForm.value.checkIn || !modalForm.value.checkOut) return

  if (modalForm.value.checkIn >= modalForm.value.checkOut) {
    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        alloc.availableRooms = 0
      })
    }
    return
  }

  try {
    const res = await fetchAvailability({
      start_date: modalForm.value.checkIn,
      end_date: modalForm.value.checkOut
    })
    const grid = res.data?.data?.grid || res.data?.grid || {}

    const dates = []
    let curr = new Date(modalForm.value.checkIn)
    const end = new Date(modalForm.value.checkOut)
    while (curr < end) {
      dates.push(curr.toISOString().split('T')[0])
      curr.setDate(curr.getDate() + 1)
    }

    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        const classCode = alloc.roomClassCode
        const dayData = grid[classCode] || {}
        
        let minAv = 999
        dates.forEach(dStr => {
          const av = dayData[dStr]?.av !== undefined ? Number(dayData[dStr].av) : null
          if (av !== null && av < minAv) {
            minAv = av
          }
        })

        if (minAv === 999) {
          minAv = 0
        }
        
        if (isEditModal.value && modalForm.value.dbId) {
          const originalBooking = tabs.value.find(t => t.dbId === modalForm.value.dbId)
          if (originalBooking) {
            const matchingAlloc = originalBooking.roomAllocations?.find(a => a.roomClassId === alloc.roomClassId)
            if (matchingAlloc) {
              minAv += Number(matchingAlloc.quantity) || 0
            }
          }
        }

        alloc.availableRooms = minAv
      })
    }
  } catch (err) {
    console.error('Lỗi khi tải tình trạng phòng trống:', err)
  }
}

function updateAllocatedRooms(row) {
  if (row.quantity === undefined || row.quantity === null || row.quantity < 0) {
    row.quantity = 0
  }

  validateRoomQuantity(row)

  if (!modalForm.value.rooms) {
    modalForm.value.rooms = []
  }

  const currentRooms = modalForm.value.rooms.filter(r => r.roomClassId === row.roomClassId)
  const diff = row.quantity - currentRooms.length

  if (diff > 0) {
    for (let i = 0; i < diff; i++) {
      modalForm.value.rooms.push({
        id: Date.now() + Math.random(),
        roomClassId: row.roomClassId,
        roomClassName: row.roomClassName,
        shape: row.roomClassCode,
        roomNumber: '',
        checkIn: row.arrivalDate || modalForm.value.checkIn,
        checkOut: row.departureDate || modalForm.value.checkOut,
        nights: row.nights || modalForm.value.nights,
        price: row.price || 0,
        rateCode: row.ratePlanCode || 'Vui lòng chọn giá phòng',
        guestName: '',
        adults: row.adults || 2,
        babies: row.babies || 0,
        children: row.children || 0,
        breakfast: row.breakfastIncluded !== undefined ? !!row.breakfastIncluded : true,
        extraBedPrice: 0,
        hourly: false,
        arrivalTime: '14:00',
        hoursOut: '12:00',
        isPreassigned: false,
        initialRoomClass: row.roomClassCode,
        transferredFrom: '',
        roomStatus: 'Sạch',
        allotmentCode: '',
        roomCode: '',
        total: (row.price || 0) * (row.nights || modalForm.value.nights || 1),
      })
    }
  } else if (diff < 0) {
    const toRemoveCount = Math.abs(diff)
    let removed = 0
    modalForm.value.rooms = modalForm.value.rooms.filter(r => {
      if (r.roomClassId === row.roomClassId && removed < toRemoveCount) {
        removed++
        return false
      }
      return true
    })
  }
}

watch(() => modalForm.value.roomAllocations, (newAllocations) => {
  if (!newAllocations || !modalForm.value.rooms) return
  newAllocations.forEach(alloc => {
    modalForm.value.rooms.forEach(r => {
      if (r.roomClassId === alloc.roomClassId) {
        if (r.price !== alloc.price) {
          r.price = alloc.price
          r.total = (alloc.price || 0) * (r.nights || 1)
        }
        r.adults = alloc.adults
        r.babies = alloc.babies
        r.children = alloc.children
        r.breakfast = !!alloc.breakfastIncluded
      }
    })
  })
}, { deep: true })

function validateRoomQuantity(alloc) {
  if (hotelSettings.value?.allow_over_room_type === 0 || hotelSettings.value?.allow_over_room_type === false) {
    if (alloc.quantity > alloc.availableRooms) {
      uiStore.showToast('Cảnh báo: Đã vượt quá số lượng phòng trống cho phép!', 'error')
      if (alloc.availableRooms === 0) {
        alloc.quantity = 0
      }
    }
  }
}

async function handleDateChange() {
  const ci = new Date(modalForm.value.checkIn)
  const co = new Date(modalForm.value.checkOut)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    modalForm.value.nights = diff > 0 ? diff : 1
    
    // Đồng bộ ngày check-in/check-out cho tất cả các phòng & allocations
    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        alloc.arrivalDate = modalForm.value.checkIn
        alloc.departureDate = modalForm.value.checkOut
        alloc.nights = modalForm.value.nights
      })
    }
    if (modalForm.value.rooms) {
      modalForm.value.rooms.forEach(r => {
        r.checkIn = modalForm.value.checkIn
        r.checkOut = modalForm.value.checkOut
        r.nights = modalForm.value.nights
        r.total = (r.price || 0) * (r.nights || 1)
      })
    }
    
    await updateRoomAvailability()
  }
}

async function handleRowDateChange(row) {
  const ci = new Date(row.arrivalDate)
  const co = new Date(row.departureDate)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    row.nights = diff > 0 ? diff : 1
    
    if (modalForm.value.rooms) {
      modalForm.value.rooms.forEach(r => {
        if (r.roomClassId === row.roomClassId) {
          r.checkIn = row.arrivalDate
          r.checkOut = row.departureDate
          r.nights = row.nights
          r.total = (r.price || 0) * (r.nights || 1)
        }
      })
    }

    try {
      const res = await checkAvailability({
        room_class_id: row.roomClassId,
        arrival_date: row.arrivalDate,
        departure_date: row.departureDate,
        exclude_booking_room_id: modalForm.value.dbId || undefined
      })
      row.availableRooms = res.data?.av !== undefined ? Number(res.data.av) : 0
    } catch(e) {
      console.error(e)
    }
  }
}

function handleNightsChange() {
  const ci = new Date(modalForm.value.checkIn)
  if (!isNaN(ci) && modalForm.value.nights > 0) {
    const co = new Date(ci)
    co.setDate(ci.getDate() + Number(modalForm.value.nights))
    modalForm.value.checkOut = co.toISOString().split('T')[0]
  }
}

function addShuttleRow() {
  modalForm.value.shuttleInfo.push({
    id: Date.now() + Math.random(),
    type: 'Đón',
    vehicle: '7 Seater car',
    code: '',
    date: modalForm.value.checkIn,
    time: '12:00',
    price: 0,
    location: '',
    note: ''
  })
}

function removeShuttleRow(index) {
  modalForm.value.shuttleInfo.splice(index, 1)
}

function copyConfirmDate() {
  if (modalForm.value.confirmDate) {
    navigator.clipboard.writeText(modalForm.value.confirmDate)
    uiStore.showToast('Đã sao chép ngày xác nhận!', 'info')
  }
}

async function handleSaveNewBooking() {
  if (!modalForm.value.bookingName.trim()) { uiStore.showToast('Vui lòng nhập tên đăng ký!', 'warning'); return }
  if (!modalForm.value.checkIn || !modalForm.value.checkOut) { uiStore.showToast('Vui lòng chọn ngày đến và ngày đi!', 'warning'); return }
  isSavingModal.value = true
  try {
    const payload = {
      booking_name:           modalForm.value.bookingName.toUpperCase(),
      arrival_date:           modalForm.value.checkIn,
      departure_date:         modalForm.value.checkOut,
      num_of_days:            modalForm.value.nights,
      registration_status_id: modalForm.value.registrationStatusId || null,
      confirm_date:           modalForm.value.confirmDate || null,
      expired_date:           modalForm.value.expiredDate || null,
      company_id:             modalForm.value.companyId || null,
      market_id:              modalForm.value.marketId || null,
      customer_source_id:     modalForm.value.customerSourceId || null,
      booker_id:              modalForm.value.bookerId || null,
      contact_name:           modalForm.value.contactName || null,
      contact_email:          modalForm.value.contactEmail || null,
      contact_phone:          modalForm.value.contactPhone || null,
      payment_method_id:      modalForm.value.paymentMethodId || null,
      payment_value:          modalForm.value.paymentValue || 0,
      external_booking_code:  modalForm.value.externalBookingCode || null,
      sales_person:           modalForm.value.salesPerson || null,
      is_git:                 modalForm.value.isGit ? 1 : 0,
      has_vat:                modalForm.value.hasVat ? 1 : 0,
      note:                   modalForm.value.note || null,
      special_requests:       modalForm.value.specialRequests || null,
      shuttle_info:           modalForm.value.shuttleInfo || [],
      room_allocations:       syncRoomsToAllocations(modalForm.value),
      deposit_details:        modalForm.value.deposits || [],
    }
    if (isEditModal.value && modalForm.value.dbId) {
      const res = await updateBooking(modalForm.value.dbId, payload)
      const updated = res.data?.data || res.data
      const idx = tabs.value.findIndex(t => t.dbId === modalForm.value.dbId)
      if (idx !== -1) { tabs.value[idx] = { ...tabs.value[idx], ...bookingToTab(updated) }; activeTabId.value = tabs.value[idx].id }
      uiStore.showToast(`Cập nhật đăng ký ${updated.booking_code} thành công!`, 'success')
    } else {
      const res = await createBooking(payload)
      const created = res.data?.data || res.data
      const newTab = bookingToTab(created)
      tabs.value.push(newTab)
      activeTabId.value = newTab.id
      uiStore.showToast(`Tạo đăng ký ${created.booking_code} thành công!`, 'success')
    }
    closeModal()
  } catch (err) {
    const errors = err.response?.data?.errors
    const msg = errors ? Object.values(errors)[0]?.[0] : (err.response?.data?.message || 'Đã xảy ra lỗi!')
    uiStore.showToast(msg, 'error')
  } finally {
    isSavingModal.value = false
  }
}

function parseDateVi(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('/')
  if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`
  return dateStr
}

function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  const currencyCode = activeCurrency.value.code || 'VND'

  let clean = String(val).replace(/[^\d.,-]/g, '');
  if (!clean) return '';

  if (currencyCode === 'VND') {
    clean = clean.replace(/\D/g, '');
    if (!clean) return '';
    return Number(clean).toLocaleString('vi-VN');
  } else {
    clean = clean.replace(/,/g, '');
    let parts = clean.split('.');
    if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.join('.');
  }
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0;
  const currencyCode = activeCurrency.value.code || 'VND'
  let clean = String(val).replace(/[^\d.,-]/g, '');
  if (currencyCode === 'VND') {
    return Number(clean.replace(/\D/g, '')) || 0;
  } else {
    return Number(clean.replace(/,/g, '')) || 0;
  }
}

function handleRowSelectAll(event) {
  selectedRows.value = event.target.checked ? activeTab.value.rooms.map(r => r.id) : []
}

function handleRowSelect(roomId) {
  if (selectedRows.value.includes(roomId)) selectedRows.value = selectedRows.value.filter(id => id !== roomId)
  else selectedRows.value.push(roomId)
}

async function triggerAction(actionName) {
  if (actionName === 'Sửa') {
    isEditing.value = true
    uiStore.showToast('Bạn có thể trực tiếp chỉnh sửa tên khách hàng hoặc mã giá phòng!', 'info')
  } else if (actionName === 'Quay lại') {
    isEditing.value = false
  } else if (actionName === 'Lưu') {
    isEditing.value = false
    const tab = activeTab.value
    if (tab && tab.dbId) {
      try {
        const payload = {
          booking_name:           tab.bookingName.toUpperCase(),
          arrival_date:           tab.checkIn,
          departure_date:         tab.checkOut,
          num_of_days:            tab.nights,
          registration_status_id: tab.registrationStatusId,
          confirm_date:           tab.confirmDate || null,
          company_id:             tab.companyId || null,
          market_id:              tab.marketId || null,
          customer_source_id:     tab.customerSourceId || null,
          booker_id:              tab.bookerId || null,
          contact_name:           tab.contactName || null,
          contact_email:          tab.contactEmail || null,
          contact_phone:          tab.contactPhone || null,
          payment_method_id:      tab.paymentMethodId || null,
          payment_value:          tab.paymentValue || 0,
          external_booking_code:  tab.externalBookingCode || null,
          sales_person:           tab.salesPerson || null,
          is_git:                 tab.isGit ? 1 : 0,
          has_vat:                tab.hasVat ? 1 : 0,
          note:                   tab.note || null,
          special_requests:       tab.specialRequests || null,
          shuttle_info:           tab.shuttleInfo || [],
          room_allocations:       syncRoomsToAllocations(tab),
        }
        const res = await updateBooking(tab.dbId, payload)
        const updated = res.data?.data || res.data
        const idx = tabs.value.findIndex(t => t.dbId === tab.dbId)
        if (idx !== -1) {
          tabs.value[idx] = { ...tabs.value[idx], ...bookingToTab(updated) }
        }
        uiStore.showToast('Lưu thông tin đăng ký thành công!', 'success')
      } catch (err) {
        console.error(err)
        uiStore.showToast('Không thể lưu thông tin đăng ký!', 'error')
      }
    } else {
      uiStore.showToast('Lưu thông tin đăng ký thành công!', 'success')
    }
  } else if (actionName === 'Thông tin đăng ký' || actionName === 'Thông tin khách hàng') {
    openEditModal()
  } else if (actionName === 'Nhân bản') {
    uiStore.showToast('Nhân bản thông tin đăng ký thành công!', 'success')
  } else if (actionName === 'GIAO PHÒNG') {
    uiStore.showToast('Đang tiến hành giao phòng cho khách...', 'success')
  } else if (actionName === 'Nâng hạng phòng') {
    uiStore.showToast('Vui lòng tích chọn phòng muốn nâng hạng trên bảng!', 'info')
  } else if (actionName === 'Gỡ số phòng') {
    const tab = activeTab.value
    if (tab && tab.rooms) {
      const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
      const targetList = selected.length > 0 ? selected : tab.rooms
      targetList.forEach(r => r.roomNumber = '')
      uiStore.showToast('Đã gỡ số phòng của các phòng được chọn thành công!', 'success')
    }
  } else if (actionName === 'Hủy phòng') {
    uiStore.confirm({
      title: 'Hủy phòng',
      message: 'Bạn có chắc chắn muốn hủy các phòng đã chọn?',
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(confirmed => {
      if (confirmed) {
        uiStore.showToast('Hủy phòng thành công!', 'success')
      }
    })
  } else if (actionName === 'Xóa dịch vụ bổ sung') {
    uiStore.showToast('Đã xóa các dịch vụ bổ sung đi kèm!', 'success')
  } else if (actionName === 'Khóa chuyển phòng') {
    uiStore.showToast('Đã thiết lập khóa chuyển phòng!', 'success')
  } else if (actionName === 'Mở chuyển phòng') {
    uiStore.showToast('Đã mở khóa chuyển phòng!', 'success')
  } else if (actionName === 'Xuất Excel') {
    uiStore.showToast('Đang tải xuống tệp Excel danh sách phòng...', 'success')
  } else if (actionName === 'In phiếu đăng ký khách') {
    uiStore.showToast('Đang in phiếu đăng ký khách (Registration Card)...', 'success')
    setTimeout(() => window.print(), 500)
  } else if (actionName === 'In hoá đơn tạm') {
    uiStore.showToast('Đang in hóa đơn tạm tính (Pro-forma Invoice)...', 'success')
    setTimeout(() => window.print(), 500)
  } else if (actionName === 'Xóa') {
    const tab = activeTab.value
    if (!tab) return
    uiStore.confirm({
      title: 'Xóa đăng ký',
      message: `Bạn có chắc chắn muốn xóa đăng ký ${tab.id}?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        if (tab.dbId) await deleteBooking(tab.dbId)
        const idx = tabs.value.findIndex(t => t.id === activeTabId.value)
        if (idx !== -1) tabs.value.splice(idx, 1)
        if (tabs.value.length > 0) activeTabId.value = tabs.value[tabs.value.length - 1].id
        else {
          tabs.value = [makeBlankTab()]
          activeTabId.value = tabs.value[0].id
        }
        uiStore.showToast('Đã xóa đăng ký thành công!', 'success')
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Không thể xóa đăng ký!', 'error')
      }
    })
  } else {
    uiStore.showToast(`Tính năng "${actionName}" đang được thực hiện!`, 'info')
  }
}
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 animate-in select-none">
    
    <!-- DYNAMIC TABS HEADER BAR -->
    <div class="bg-slate-100 border-b border-slate-200 px-4 pt-1.5 flex items-end justify-between shrink-0">
      <div class="flex items-center gap-1.5 overflow-x-auto max-w-[50%] scrollbar-none">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="handleTabClick(tab.id)"
          class="flex items-center gap-2 px-4 py-2 text-sm font-black rounded-t-lg transition-all duration-200 border-t border-x cursor-pointer whitespace-nowrap"
          :class="tab.id === activeTabId
            ? 'bg-white text-sky-700 border-slate-200 border-b-white z-10 translate-y-[1px]'
            : 'bg-slate-100 text-slate-500 border-transparent hover:bg-slate-200/60'"
        >
          <span>{{ tab.title }}</span>
          <span
            @click="handleCloseTab(tab.id, $event)"
            class="w-3.5 h-3.5 rounded-full flex items-center justify-center text-xs text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition-colors"
          >
            &times;
          </span>
        </button>

        <!-- ADD TAB BUTTON (+ Icon) -->
        <button
          @click="handleAddTabClick"
          class="w-7 h-7 mb-1 bg-white hover:bg-slate-50 text-[#006bdb] rounded-lg flex items-center justify-center cursor-pointer border border-slate-200 transition-all active:scale-95 shadow-xs"
          title="Tạo đăng ký mới"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
        </button>
      </div>

      <!-- Action Buttons in Header -->
      <div v-if="activeTab" class="flex items-center gap-2 pb-1.5 text-slate-600">
        <button 
          @click="triggerAction('Thông tin đăng ký')"
          :class="isEditing ? 'bg-slate-500 hover:bg-slate-600' : 'bg-[#006bdb] hover:bg-[#0052a3]'"
          class="px-3.5 py-1.5 text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 011.063.853l-2.036 1.018a.75.75 0 01-1.063-.853l.708-2.836-.041.02a.75.75 0 01-1.063-.853l2.036-1.018zM12 8.25a.75.75 0 110-1.5.75.75 0 010 1.5z" clip-rule="evenodd" />
          </svg>
          Thông tin đăng ký
        </button>
        
        <button 
          v-if="!isEditing"
          @click="triggerAction('Sửa')"
          class="px-3.5 py-1.5 bg-[#006bdb] hover:bg-[#0052a3] text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
          </svg>
          Sửa
        </button>

        <button 
          v-else
          @click="triggerAction('Quay lại')"
          class="px-3.5 py-1.5 bg-[#006bdb] hover:bg-[#0052a3] text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
          </svg>
          Quay lại
        </button>

        <button 
          @click="triggerAction('Lưu')"
          :class="isEditing ? 'bg-[#006bdb] hover:bg-[#0052a3] cursor-pointer' : 'bg-slate-300 text-slate-500 opacity-90 cursor-not-allowed'"
          class="px-3.5 py-1.5 text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 shadow-sm border border-transparent"
          :disabled="!isEditing"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 3H6.75A2.25 2.25 0 0 0 4.5 5.25v13.5A2.25 2.25 0 0 0 6.75 21h10.5A2.25 2.25 0 0 0 19.5 18.75V5.25l-2.25-2.25ZM7.5 3v5.25h6V3m-3 14.25a2.25 2.25 0 1 1 0-4.5 2.25 2.25 0 0 1 0 4.5Z" />
          </svg>
          Lưu
        </button>

        <button 
          @click="triggerAction('Nhân bản')"
          :class="isEditing ? 'bg-slate-500 hover:bg-slate-600' : 'bg-[#006bdb] hover:bg-[#0052a3]'"
          class="px-3.5 py-1.5 text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376A8.965 8.965 0 0012 12.75c-.131-1.178-.377-2.322-.75-3.414m9 9.375a9.015 9.015 0 01-1.5-.124M15 3.75H9v1.5H6.75c-.621 0-1.125.504-1.125 1.125v3.375c0 .621.504 1.125 1.125 1.125h1.5v1.5h1.5v-1.5h6v1.5h1.5v-1.5h1.5c.621 0 1.125-.504 1.125-1.125V7.875c0-.621-.504-1.125-1.125-1.125H15V3.75z" />
          </svg>
          Nhân bản
        </button>

        <button 
          @click="triggerAction('Xóa')"
          :class="isEditing ? 'bg-slate-500 hover:bg-slate-600' : 'bg-[#006bdb] hover:bg-[#0052a3]'"
          class="px-3.5 py-1.5 text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
          Xóa
        </button>

        <button 
          @click="triggerAction('In')"
          class="px-3.5 py-1.5 bg-[#006bdb] hover:bg-[#0052a3] text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.82l-.24 2.18m11.04-2.18l.24 2.18m0 0a2.25 2.25 0 104.44-.11L21 11.25M17.76 16h-.008m0 0a2.25 2.25 0 11-4.44-.11L13.5 11.25m-6.78 2.57l-.24-2.18m11.04 2.18l.24-2.18M6.72 13.82h10.56M6.72 13.82l-.24-2.18m11.04 2.18l.24-2.18m-11.04 0h11.04m-11.04 0l.24-2.18m10.56 2.18l-.24-2.18m0 0a2.25 2.25 0 10-4.44-.11L10.5 5.25m-3.78 2.57l.24-2.18" />
          </svg>
          In
          <span class="w-[1px] h-3.5 bg-white/60 ml-0.5"></span>
          <svg class="w-3.5 h-3.5 -ml-0.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
          </svg>
        </button>

        <button 
          @click="triggerAction('Thông báo')"
          class="px-3.5 py-1.5 bg-[#006bdb] hover:bg-[#0052a3] text-white rounded-md text-sm font-semibold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm border border-transparent"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
          </svg>
          Thông báo
        </button>
      </div>
    </div>

    <!-- MAIN TAB CONTENT PANEL -->
    <div v-if="activeTab" class="flex-1 flex flex-col overflow-y-auto">
      
      <div 
        @click="openEditModal"
        class="bg-white border-b border-slate-200 px-5 py-3 flex items-center justify-between text-xs text-slate-500 shadow-xs shrink-0 cursor-pointer hover:bg-slate-50 transition-colors group relative"
      >
        <div class="flex items-center gap-2 overflow-hidden whitespace-nowrap">
          <div>Tên đăng ký: <span class="text-slate-900 font-extrabold uppercase">{{ activeTab.bookingName || 'Trống' }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Trạng thái: <span class="font-extrabold uppercase" :class="activeTab.statusLabel === 'Allotment' ? 'text-orange-600' : 'text-sky-600'">{{ activeTabStatusName || 'Trống' }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Ngày đến: <span class="text-slate-700 font-extrabold">{{ formatDateVi(activeTab.checkIn) }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Ngày đi: <span class="text-slate-700 font-extrabold">{{ formatDateVi(activeTab.checkOut) }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Đặt cọc: <span class="text-slate-700 font-extrabold">{{ (activeTab.deposit || 0).toLocaleString('vi-VN') }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Công ty: <span class="text-slate-900 font-extrabold">{{ activeTab.company || '---' }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Thị trường: <span class="text-slate-700 font-extrabold">{{ activeTab.market || '---' }}</span></div>
          <span class="text-slate-300">|</span>
          <div>Xác nhận: <span class="text-slate-700 font-extrabold">{{ formatDateVi(activeTab.confirmDate) || '---' }}</span></div>
        </div>
        
        <!-- Hover View Details button -->
        <button 
          @click.stop="openEditModal"
          class="opacity-0 group-hover:opacity-100 absolute right-4 px-3 py-1 bg-[#006bdb]/10 text-[#006bdb] rounded text-sm font-bold transition-opacity cursor-pointer hover:bg-[#006bdb]/20"
        >
          Xem chi tiết
        </button>
      </div>

      <!-- SEARCH & CONTROLS ACTION PANEL -->
      <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4 shrink-0">
        <!-- Search bar with funnel filter icon -->
        <div class="flex items-center gap-2 max-w-[280px] w-full bg-white border border-slate-200 rounded-md px-2 py-1 shadow-xs focus-within:ring-2 focus-within:ring-sky-500/20">
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input 
            type="text" 
            v-model="searchQuery" 
            placeholder="Tìm kiếm..." 
            class="border-none bg-transparent text-sm w-full focus:outline-none font-bold text-slate-700"
          />
        </div>

        <!-- Right Side Toolbar Actions -->
        <div class="flex items-center gap-3 text-sm">
          <!-- Chức năng Dropdown -->
          <div class="relative">
            <button 
              @click="showChucNangMenu = !showChucNangMenu"
              @blur="setTimeout(() => showChucNangMenu = false, 200)"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-md text-slate-700 font-bold hover:bg-slate-50 cursor-pointer shadow-xs"
            >
              <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
              </svg>
              Chức năng
              <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
              </svg>
            </button>
            <div v-if="showChucNangMenu" class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-lg shadow-xl py-1.5 z-50 text-slate-800 animate-in">
              <!-- Group 1 -->
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Cập nhật')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Cập nhật
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Tự động gán phòng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 9.152c.582.448 1.148.89 1.676 1.345m-7.464-.326c.55-.429 1.13-.855 1.733-1.272m-3.415 8.78c.325.27.7.472 1.103.587l3.666 1.05a2.25 2.25 0 002.593-1.61l1.05-3.667" />
                </svg>
                Tự động gán phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Dịch vụ bổ sung')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.251 0-4.37-.6-6.195-1.655z" />
                </svg>
                Dịch vụ bổ sung
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('GIAO PHÒNG')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
                GIAO PHÒNG
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Nâng hạng phòng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l7.5-7.5 7.5 7.5m-15 6l7.5-7.5 7.5 7.5" />
                </svg>
                Nâng hạng phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Thông tin khách hàng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm-1.2 6.452a8.3 8.3 0 00-2.4 0v.09a2.051 2.051 0 002.4 0v-.09z" />
                </svg>
                Thông tin khách hàng
              </button>

              <hr class="border-slate-100 my-1" />

              <!-- Group 2 -->
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Gỡ số phòng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122l9.37-9.37m-1.312 13.568l2.25-2.25a2.25 2.25 0 000-3.181l-6.254-6.254a2.25 2.25 0 00-3.181 0l-2.25 2.25a2.25 2.25 0 000 3.181l6.254 6.254a2.25 2.25 0 003.181 0z" />
                </svg>
                Gỡ số phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-rose-600" @click="triggerAction('Hủy phòng')">
                <svg class="w-4.5 h-4.5 text-rose-500 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Hủy phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Xóa dịch vụ bổ sung')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6M19 7.5v3m0 0v3m-3-3h3m-3 0h-3" />
                </svg>
                Xóa dịch vụ bổ sung
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Khóa chuyển phòng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Khóa chuyển phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Mở chuyển phòng')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Mở chuyển phòng
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('Xuất Excel')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125V3.375c0-.621.504-1.125 1.125-1.125h11.25c.621 0 1.125.504 1.125 1.125v1.5m-13.5 15.25v-1.5m13.5-13.75v1.5M6.75 7.5h10.5m-10.5 3h10.5m-10.5 3h10.5m-10.5 3h10.5" />
                </svg>
                Xuất Excel
              </button>

              <hr class="border-slate-100 my-1" />

              <!-- Group 3 -->
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('In phiếu đăng ký khách')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.82l-.24 2.18m11.04-2.18l.24 2.18m0 0a2.25 2.25 0 104.44-.11L21 11.25M17.76 16h-.008m0 0a2.25 2.25 0 11-4.44-.11L13.5 11.25" />
                </svg>
                In phiếu đăng ký khách
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-slate-50 cursor-pointer flex items-center text-sm font-bold text-slate-700" @click="triggerAction('In hoá đơn tạm')">
                <svg class="w-4.5 h-4.5 text-slate-600 mr-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879-.659c1.171-.879 3.07-.879 4.242 0 1.172.879 1.172 2.303 0 3.182s-3.07.879-4.242 0a1.75 1.75 0 01-.504-.511m.504-10.13a1.75 1.75 0 00-.504-.511m0 0l-.879-.659m.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182s-3.07-.879-4.242 0a1.75 1.75 0 01-.504-.51" />
                </svg>
                In hoá đơn tạm
              </button>
            </div>
          </div>

          <!-- Bulk Selection Dropdown -->
          <div class="flex items-center gap-1">
            <span class="text-slate-500 font-medium">Chọn:</span>
            <select v-model="selectedRoomAction" class="bg-white border border-slate-200 rounded-md px-2 py-1.5 text-slate-700 font-bold focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer shadow-xs">
              <option value="0">0</option>
              <option value="1">1</option>
              <option value="all">Tất cả</option>
            </select>
          </div>

          <!-- Filter Button -->
          <button class="p-1.5 bg-white border border-slate-200 rounded-md hover:bg-slate-50 cursor-pointer shadow-xs text-slate-500" title="Lọc">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
            </svg>
          </button>

          <!-- Settings Column Selector Popover -->
          <div class="relative inline-block text-left">
            <button 
              @click="showTableColumnSelector = !showTableColumnSelector" 
              class="p-1.5 bg-white border border-slate-200 rounded-md hover:bg-slate-50 cursor-pointer shadow-xs text-slate-500 flex items-center" 
              title="Tùy chọn"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </button>
            
            <div 
              v-if="showTableColumnSelector" 
              class="fixed inset-0 z-40 cursor-default" 
              @click="showTableColumnSelector = false"
            ></div>
            
            <div 
              v-if="showTableColumnSelector" 
              class="absolute right-0 mt-1 w-60 bg-white border border-slate-200 rounded-lg shadow-xl z-50 py-1.5 max-h-80 overflow-y-auto animate-in"
            >
              <div 
                v-for="col in columns" 
                :key="col.key" 
                class="px-3.5 py-1.5 hover:bg-slate-50 flex items-center gap-2 text-xs font-semibold text-slate-700 select-none cursor-grab active:cursor-grabbing"
                draggable="true"
                @dragstart="handleDragStart(col.key)"
                @dragover.prevent
                @drop="handleDrop(col.key)"
              >
                <!-- Drag Grip Icon -->
                <span class="text-slate-400 font-bold cursor-grab active:cursor-grabbing text-xs leading-none">⋮⋮</span>
                
                <input 
                  type="checkbox" 
                  v-model="col.visible" 
                  class="rounded text-[#006bdb] focus:ring-[#006bdb] w-3.5 h-3.5 cursor-pointer"
                />
                <span class="flex-1 cursor-pointer" @click="col.visible = !col.visible">{{ col.label }}</span>
              </div>
            </div>
          </div>

          <!-- Hoa don Button -->
          <button 
            @click="triggerAction('Hóa đơn')"
            class="px-4 py-1.5 bg-[#006bdb] hover:bg-[#0052a3] text-white rounded-md text-sm font-bold shadow-xs transition-colors cursor-pointer ml-1"
          >
            Hóa đơn
          </button>
        </div>
      </div>

      <!-- ROOMS DATA TABLE LIST -->
      <div class="flex-1 p-5 overflow-y-auto overflow-x-hidden">
        <div class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-x-auto min-h-[300px]">
          <table class="w-full text-left border-collapse text-xs table-fixed min-w-[2450px]">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold select-none whitespace-nowrap h-9 text-xs">
                <th class="p-2 border-r border-slate-200 text-center w-[50px]">
                  <input type="checkbox" @change="handleRowSelectAll" :checked="selectedRows.length === activeTab.rooms.length && activeTab.rooms.length > 0" />
                </th>
                <th class="p-2 border-r border-slate-200 text-center w-[45px]">STT</th>
                <th v-for="col in columns.filter(c => c.visible)" 
                    :key="col.key"
                    draggable="true"
                    @dragstart="handleDragStart(col.key)"
                    @dragover.prevent
                    @drop="handleDrop(col.key)"
                    class="p-2 border-r border-slate-200 cursor-move select-none hover:bg-slate-100 transition-colors"
                    :class="[col.width, col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                >
                  <div class="flex items-center justify-between gap-1 w-full">
                    <span class="truncate">{{ col.label }}</span>
                    <span class="text-slate-300 text-[10px] font-normal cursor-grab select-none">⋮⋮</span>
                  </div>
                </th>
                <th class="p-2 text-right w-[120px] sticky right-0 bg-slate-50 border-l border-slate-200 z-20 shadow-[-2px_0_5px_rgba(0,0,0,0.02)]">Tổng cộng</th>
              </tr>
            </thead>
            <tbody class="font-semibold text-gray-900 select-text">
              <!-- Collapsible Section: Tình trạng Đăng Ký (3) -->
              <tr class="bg-slate-100/60 border-b border-slate-200 font-bold h-9 text-gray-900">
                <td class="p-2 border-r border-slate-200 text-center">
                  <span 
                    @click="collapsedSections.registrationStatus = !collapsedSections.registrationStatus" 
                    class="cursor-pointer text-slate-500 hover:text-slate-800 px-1 font-black text-sm select-none"
                  >
                    {{ collapsedSections.registrationStatus ? '+' : '-' }}
                  </span>
                </td>
                <td :colspan="columns.filter(c => c.visible).length + 1" class="p-2">
                  <div class="flex items-center gap-2.5">
                    <input type="checkbox" :checked="selectRangeVal === roomsTotalSummary.count" disabled />
                    <span class="text-gray-900 text-xs font-bold uppercase tracking-wider">Tình trạng: Đăng ký ({{ roomsTotalSummary.count }})</span>
                    <!-- Range Slider for room selection -->
                    <div class="flex items-center gap-2 ml-3">
                      <input 
                        type="range" 
                        min="0" 
                        :max="roomsTotalSummary.count" 
                        step="1" 
                        v-model.number="selectRangeVal"
                        class="w-32 h-1 bg-slate-300 rounded-lg appearance-none cursor-pointer accent-sky-500 focus:outline-none"
                      />
                      <span class="text-xs font-bold text-sky-700 bg-sky-50 border border-sky-200 px-1.5 py-0.5 rounded shadow-xs select-none">
                        {{ selectRangeVal }} / {{ roomsTotalSummary.count }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="sticky right-0 bg-[#f1f5f9] border-l border-slate-200 z-10"></td>
              </tr>

              <!-- Nested Rows of Rooms -->
              <template v-if="!collapsedSections.registrationStatus">
                <template v-for="(roomsInGroup, typeName) in groupedRooms" :key="typeName">
                  <!-- Group Header Row -->
                  <tr class="bg-slate-50/70 border-b border-slate-200 font-bold h-8 text-gray-900">
                    <td class="p-2 border-r border-slate-200 text-center">
                      <span 
                        @click="toggleGroupCollapse(typeName)" 
                        class="cursor-pointer text-slate-400 hover:text-slate-700 px-1 select-none"
                      >
                        {{ collapsedSections[typeName] ? '+' : '-' }}
                      </span>
                    </td>
                    <td :colspan="columns.filter(c => c.visible).length + 1" class="p-2 text-gray-900 font-bold text-xs uppercase tracking-wider">
                      {{ typeName }} ({{ roomsInGroup.length }})
                    </td>
                    <td class="sticky right-0 bg-[#f8fafc] border-l border-slate-200 z-10"></td>
                  </tr>

                  <!-- Rooms in Group -->
                  <template v-if="!collapsedSections[typeName]">
                    <tr 
                      v-for="(room, idx) in roomsInGroup" 
                      :key="room.id"
                      class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors h-9 group"
                    >
                      <td class="p-2 border-r border-slate-200 text-center">
                        <input type="checkbox" :checked="selectedRows.includes(room.id)" @change="handleRowSelect(room.id)" />
                      </td>
                      <td class="p-2 border-r border-slate-200 text-center text-gray-500 font-semibold">{{ idx + 1 }}</td>
                      <td v-for="col in columns.filter(c => c.visible)" 
                          :key="col.key" 
                          class="p-2 border-r border-slate-200" 
                          :class="[col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                      >
                        <template v-if="col.key === 'type'">
                          <span class="text-gray-900 font-semibold">{{ room.type }}</span>
                        </template>
                        <template v-else-if="col.key === 'shape'">
                          <span class="text-gray-900 font-semibold">{{ room.shape }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomNumber'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.roomNumber" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.roomNumber || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'checkIn'">
                          <span class="text-gray-500 font-semibold">{{ room.checkIn }}</span>
                        </template>
                        <template v-else-if="col.key === 'checkOut'">
                          <span class="text-gray-500 font-semibold">{{ room.checkOut }}</span>
                        </template>
                        <template v-else-if="col.key === 'nights'">
                          <span class="text-gray-900 font-semibold">{{ room.nights }}</span>
                        </template>
                        <template v-else-if="col.key === 'price'">
                          <input 
                            v-if="isEditing" 
                            type="number" 
                            v-model.number="room.price" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-right" 
                            @input="room.total = (Number(room.price) || 0) * (Number(room.nights) || 1)"
                          />
                          <span v-else>{{ room.price.toLocaleString('vi-VN') }}</span>
                        </template>
                        <template v-else-if="col.key === 'rateCode'">
                          <select 
                            v-if="isEditing" 
                            v-model="room.rateCode" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none"
                          >
                            <option value="Vui lòng chọn giá phòng">Vui lòng chọn giá phòng</option>
                            <option v-for="rc in roomRateCodes" :key="rc.id" :value="rc.Ma">{{ rc.Ma }}</option>
                          </select>
                          <span v-else class="text-slate-400 font-semibold">{{ room.rateCode }}</span>
                        </template>
                        <template v-else-if="col.key === 'adjustment'">
                          <span class="text-gray-500 font-semibold">-</span>
                        </template>
                        <template v-else-if="col.key === 'guestName'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.guestName" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else class="text-gray-900 font-semibold">{{ room.guestName }}</span>
                        </template>
                        <template v-else-if="col.key === 'adults'">
                          <input 
                            v-if="isEditing" 
                            type="number" 
                            v-model.number="room.adults" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.adults }}</span>
                        </template>
                        <template v-else-if="col.key === 'babies'">
                          <input 
                            v-if="isEditing" 
                            type="number" 
                            v-model.number="room.babies" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.babies }}</span>
                        </template>
                        <template v-else-if="col.key === 'children'">
                          <input 
                            v-if="isEditing" 
                            type="number" 
                            v-model.number="room.children" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.children }}</span>
                        </template>
                        <template v-else-if="col.key === 'childBreakfast'">
                          <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
                        </template>
                        <template v-else-if="col.key === 'breakfast'">
                          <label class="relative inline-flex items-center cursor-pointer scale-75">
                            <input type="checkbox" v-model="room.breakfast" class="sr-only peer" :disabled="!isEditing">
                            <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                          </label>
                        </template>
                        <template v-else-if="col.key === 'extraBed'">
                          <button class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
                        </template>
                        <template v-else-if="col.key === 'extraBedPrice'">
                          <span class="text-gray-900 font-semibold">{{ room.extraBedPrice.toLocaleString('vi-VN') }}</span>
                        </template>
                        <template v-else-if="col.key === 'hourly'">
                          <label class="relative inline-flex items-center cursor-pointer scale-75">
                            <input type="checkbox" v-model="room.hourly" class="sr-only peer" :disabled="!isEditing">
                            <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                          </label>
                        </template>
                        <template v-else-if="col.key === 'specialRequests'">
                          <button class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Yêu cầu đặc biệt</button>
                        </template>
                        <template v-else-if="col.key === 'arrivalTime'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.arrivalTime" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.arrivalTime || '14:00' }}</span>
                        </template>
                        <template v-else-if="col.key === 'hoursOut'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.hoursOut" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.hoursOut || '12:00' }}</span>
                        </template>
                        <template v-else-if="col.key === 'isPreassigned'">
                          <input 
                            type="checkbox" 
                            v-model="room.isPreassigned" 
                            :disabled="!isEditing"
                            class="rounded text-sky-500 focus:ring-sky-500 w-3.5 h-3.5 cursor-pointer inline-block"
                          />
                        </template>
                        <template v-else-if="col.key === 'initialRoomClass'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.initialRoomClass" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.initialRoomClass || room.shape || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'transferredFrom'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.transferredFrom" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.transferredFrom || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomStatus'">
                          <select 
                            v-if="isEditing" 
                            v-model="room.roomStatus" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center"
                          >
                            <option value="Sạch">Sạch</option>
                            <option value="Bẩn">Bẩn</option>
                            <option value="Đang dọn">Đang dọn</option>
                            <option value="Kiểm tra">Kiểm tra</option>
                          </select>
                          <span v-else 
                                :class="{
                                  'text-green-600': room.roomStatus === 'Sạch',
                                  'text-rose-600': room.roomStatus === 'Bẩn',
                                  'text-amber-600': room.roomStatus === 'Đang dọn',
                                  'text-sky-600': room.roomStatus === 'Kiểm tra'
                                }">
                            {{ room.roomStatus || 'Sạch' }}
                          </span>
                        </template>
                        <template v-else-if="col.key === 'allotmentCode'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.allotmentCode" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else>{{ room.allotmentCode || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomCode'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.roomCode" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else>{{ room.roomCode || '-' }}</span>
                        </template>
                      </td>
                      <td class="p-2 text-right text-gray-900 font-semibold sticky right-0 bg-white group-hover:bg-slate-50/50 border-l border-slate-200 z-10">{{ (Number(room.total) || 0).toLocaleString('vi-VN') }}</td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
            <!-- Footer row with summaries -->
            <tfoot class="border-t border-slate-300 font-bold text-gray-900 bg-slate-100 select-none">
              <tr class="h-9">
                <td class="p-2 border-r border-slate-200"></td>
                <td class="p-2 border-r border-slate-200"></td>
                <td v-for="col in columns.filter(c => c.visible)" 
                    :key="col.key" 
                    class="p-2 border-r border-slate-200 font-bold" 
                    :class="[col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                >
                  <template v-if="col.key === 'type'">
                    Tổng: {{ roomsTotalSummary.count }}
                  </template>
                  <template v-else-if="col.key === 'price'">
                    {{ roomsTotalSummary.priceSum.toLocaleString('vi-VN') }}
                  </template>
                  <template v-else-if="col.key === 'adults'">
                    {{ roomsTotalSummary.adults }}
                  </template>
                  <template v-else-if="col.key === 'babies'">
                    {{ roomsTotalSummary.babies }}
                  </template>
                  <template v-else-if="col.key === 'children'">
                    {{ roomsTotalSummary.children }}
                  </template>
                  <template v-else-if="col.key === 'extraBedPrice'">
                    {{ roomsTotalSummary.extraBed.toLocaleString('vi-VN') }}
                  </template>
                  <template v-else>
                    -
                  </template>
                </td>
                <td class="p-2 text-right text-sky-700 font-bold text-sm sticky right-0 bg-[#f1f5f9] border-l border-slate-200 z-10 shadow-[-2px_0_5px_rgba(0,0,0,0.02)]">
                  {{ roomsTotalSummary.total.toLocaleString('vi-VN') }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>

    <!-- MOCKUP CREATE REGISTRATION MODAL (Image 2 Match) -->
    <Teleport to="body">
      <div 
        v-if="isModalOpen" 
        class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
      >
        <div 
          class="bg-white rounded-xl shadow-2xl w-full max-w-[1400px] overflow-hidden border border-gray-300 flex flex-col max-h-[90vh]"
          :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
        >
          
          <!-- MODAL HEADER (FINAL.html Style) -->
          <div 
            class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0 cursor-move select-none"
            @mousedown="startDragModal"
          >
            <div class="flex items-center space-x-2 font-semibold text-sm">
                <i class="fa-solid fa-file-lines text-blue-300"></i>
                <span>{{ isEditModal ? 'THÔNG TIN ĐĂNG KÝ' : 'TẠO ĐĂNG KÝ' }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-1.5 bg-white/10 border border-white/20 rounded-lg h-[26px] px-2 shadow-inner">
                    <span class="text-xs font-medium text-gray-300 select-none">Màu BK</span>
                    <input type="color" v-model="modalForm.color" class="w-3.5 h-3.5 cursor-pointer bg-transparent border-none p-0 outline-none">
                </div>

                <div class="flex items-center space-x-1.5">
                    <span class="text-xs font-medium text-gray-300">Tiền phòng gửi Master</span>
                    <div class="relative inline-block w-8 h-4 align-middle select-none transition duration-200 ease-in">
                        <input type="checkbox" v-model="modalForm.isGit" id="git-toggle" class="sr-only peer"/>
                        <label for="git-toggle" class="block overflow-hidden h-4 rounded-full bg-gray-400 peer-checked:bg-blue-500 cursor-pointer transition-colors duration-200"></label>
                        <span class="absolute block w-3 h-3 rounded-full bg-white top-0.5 left-0.5 peer-checked:translate-x-4 transition-transform duration-200 pointer-events-none"></span>
                    </div>
                </div>
                <div class="flex items-center space-x-2 text-gray-300">
                    <button class="flex items-center space-x-1 text-xs bg-white/10 hover:bg-white/20 px-2 py-0.5 rounded-md transition text-gray-200 hover:text-white font-medium shadow-sm cursor-pointer border-none bg-transparent">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Lịch sử booking</span>
                    </button>
                    <button class="hover:text-white cursor-pointer border-none bg-transparent"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <button @click="closeModal" class="hover:text-white ml-1 bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none"><i class="fa-solid fa-xmark text-red-400"></i></button>
                </div>
            </div>
          </div>

          <!-- SECOND ACTION STRIP (GENERAL DETAILS) -->
          <div class="px-4 py-2 border-b border-gray-200 flex flex-wrap items-end justify-between gap-2 bg-gray-50/50 shrink-0">
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã booking</div>
                <div class="font-bold text-sm text-gray-900 h-[32px] flex items-center px-1">{{ modalForm.bookingCode || 'Tự động sinh' }}</div>
            </div>
            <div class="flex flex-col flex-1 min-w-[140px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tên đăng ký <span class="text-red-500">*</span></div>
                <input 
                  type="text" 
                  v-model="modalForm.bookingName" 
                  placeholder="Nhập tên đăng ký..."
                  class="font-bold text-sm text-black border border-blue-200 rounded-xl px-3 h-[32px] flex items-center bg-blue-50/70 shadow-sm w-full outline-none focus:border-blue-400 focus:bg-blue-50/90 uppercase"
                />
            </div>
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã tham chiếu</div>
                <input 
                  type="text" 
                  v-model="modalForm.externalBookingCode" 
                  placeholder="Mã tham chiếu" 
                  class="border border-gray-300 rounded-xl px-3 text-xs focus:outline-none focus:border-blue-500 w-28 h-[32px] shadow-sm font-semibold"
                />
            </div>
            <div class="flex flex-col shrink-0 w-[230px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày lưu trú</div>
                <div class="flex items-center space-x-2 text-xs font-semibold text-gray-800 border border-gray-300 rounded-xl px-2 h-[32px] bg-white shadow-sm w-full">
                    <i class="fa-regular fa-calendar text-blue-500 shrink-0"></i>
                    <input 
                      type="date" 
                      v-model="modalForm.checkIn" 
                      :min="systemDate"
                      @change="handleDateChange"
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input checkin-date-input"
                    />
                    <i class="fa-solid fa-arrow-right text-gray-400 text-xs shrink-0"></i>
                    <input 
                      type="date" 
                      v-model="modalForm.checkOut" 
                      :min="modalForm.checkIn"
                      @change="handleDateChange"
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input checkout-date-input"
                    />
                </div>
            </div>
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Đêm</div>
                <div class="font-bold text-sm text-gray-900 border border-gray-300 rounded-xl h-[32px] flex items-center justify-center min-w-[40px] bg-white shadow-sm">
                    <input 
                      type="number" 
                      v-model="modalForm.nights" 
                      @input="handleNightsChange"
                      min="1"
                      class="border-none bg-transparent text-center w-8 text-sm font-bold focus:outline-none focus:ring-0 p-0"
                    />
                </div>
            </div>
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tình trạng đăng ký</div>
                <select 
                  v-model="modalForm.registrationStatusId"
                  class="bg-blue-50/70 border border-blue-200 text-blue-800 rounded-xl px-3 text-xs focus:outline-none focus:border-blue-400 appearance-none font-bold h-[32px] shadow-sm cursor-pointer"
                >
                    <option :value="null">— Chọn —</option>
                    <option v-for="rs in registrationStatuses" :key="rs.id" :value="rs.id">{{ rs.name }}</option>
                </select>
            </div>
            <div class="flex flex-col shrink-0 w-[150px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày xác nhận</div>
                <div class="flex items-center space-x-2 text-xs text-gray-800 border border-gray-300 rounded-xl px-2 h-[32px] bg-white shadow-sm relative w-full">
                    <input 
                      type="date" 
                      v-model="modalForm.confirmDate" 
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input w-full"
                    />
                    <i class="fa-regular fa-calendar text-gray-400 pointer-events-none shrink-0"></i>
                    <button @click="copyConfirmDate" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer border-none bg-transparent p-0 shrink-0">
                      <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>
          </div>

          <!-- TABS STRIP -->
          <div class="flex px-4 border-b border-gray-200 bg-white shrink-0">
              <button 
                @click="modalSubTab = 'info'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'info' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-address-card"></i>
                  <span>Thông tin chung</span>
              </button>
              <button 
                @click="modalSubTab = 'shuttle'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'shuttle' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-van-shuttle"></i>
                  <span>Thông tin đưa đón</span>
              </button>
              <button 
                @click="modalSubTab = 'rooms'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'rooms' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-bed"></i>
                  <span>Lấy phòng</span>
                  <span class="ml-1.5 bg-blue-100 text-blue-600 text-[10px] font-extrabold px-1.5 py-0.5 rounded-full" v-if="modalForm.roomAllocations?.length">
                    {{ modalForm.roomAllocations.length }}
                  </span>
              </button>
          </div>

          <!-- MODAL CONTENT SCROLL -->
          <div class="flex-1 p-3 overflow-y-auto flex flex-col gap-3 bg-gray-50">
            
            <!-- Tab 1: Thông tin chung -->
            <div v-if="modalSubTab === 'info'" class="flex flex-col space-y-2.5 w-full">
              <!-- Section 1: Công ty -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-blue-500 rounded-full mr-1.5"></div>
                      Công ty
                  </h3>
                  <div class="flex flex-wrap lg:flex-nowrap gap-2 items-end">
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Công ty <span class="text-red-500">*</span></label>
                          <select 
                            v-model="modalForm.companyId"
                            @change="handleCompanyChange"
                            class="w-full border border-blue-200 rounded-xl px-2.5 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs font-bold bg-blue-50/70 text-black h-[32px] cursor-pointer"
                          >
                              <option :value="null">— Chọn công ty —</option>
                              <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                          </select>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Thị trường</label>
                          <select 
                            v-model="modalForm.marketId"
                            class="w-full border border-blue-200 rounded-xl px-2.5 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs bg-blue-50/70 text-black h-[32px] cursor-pointer font-bold"
                          >
                              <option :value="null">— Chọn thị trường —</option>
                              <option v-for="m in markets" :key="m.id" :value="m.id">{{ m.name }}</option>
                          </select>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Nguồn khách</label>
                          <select 
                            v-model="modalForm.customerSourceId"
                            class="w-full border border-blue-200 rounded-xl px-2.5 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs bg-blue-50/70 text-black h-[32px] cursor-pointer font-bold"
                          >
                              <option :value="null">— Chọn nguồn khách —</option>
                              <option v-for="s in customerSources" :key="s.id" :value="s.id">{{ s.name }}</option>
                          </select>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Người bán</label>
                          <select 
                            v-model="modalForm.salesPerson"
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white h-[32px] font-bold"
                          >
                              <option value="">— Chọn người bán —</option>
                              <option v-for="u in users" :key="u.id" :value="u.username || u.name">{{ u.name || u.username }}</option>
                          </select>
                      </div>
                      <div class="flex-1 min-w-[150px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Phương thức thanh toán</label>
                          <select 
                            v-model="modalForm.paymentMethodId"
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white h-[32px] font-bold"
                          >
                              <option :value="null">Chọn phương thức...</option>
                              <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                          </select>
                      </div>
                  </div>
              </div>

              <!-- Section 2: Người liên hệ -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-purple-500 rounded-full mr-1.5"></div>
                      Người liên hệ
                  </h3>
                  <div class="flex flex-wrap md:flex-nowrap gap-2 items-end">
                      <div class="flex-2 min-w-[260px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Người đặt phòng</label>
                          <div class="flex space-x-1.5 h-[32px]">
                              <select 
                                v-model="modalForm.bookerId"
                                @change="handleBookerChange"
                                class="flex-1 border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white font-bold"
                              >
                                  <option :value="null">Chọn người đặt...</option>
                                  <option v-for="b in bookers" :key="b.id" :value="b.id">{{ b.name }}</option>
                              </select>
                              <button type="button" class="bg-gray-100 border border-gray-300 px-2.5 py-1 rounded-xl text-gray-600 hover:bg-gray-200 transition cursor-pointer">
                                  <i class="fa-solid fa-user-plus text-xs"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Số điện thoại</label>
                          <input 
                            type="text" 
                            v-model="modalForm.contactPhone"
                            placeholder="Số điện thoại" 
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs h-[32px] font-bold"
                          >
                      </div>
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Email</label>
                          <input 
                            type="email" 
                            v-model="modalForm.contactEmail"
                            placeholder="Email" 
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs h-[32px] font-bold"
                          >
                      </div>
                  </div>
              </div>

              <!-- Section 3: Đặt cọc -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs relative">
                  <div class="flex justify-between items-center mb-1.5">
                      <h3 class="font-bold text-gray-800 flex items-center text-xs uppercase tracking-wider">
                          <div class="w-1 h-3 bg-green-500 rounded-full mr-1.5"></div>
                          Đặt cọc
                      </h3>
                      <button @click="openDepositModal" type="button" class="text-gray-500 bg-gray-100 hover:bg-gray-200 rounded-md px-2 py-0.5 text-[11px] transition flex items-center gap-1 cursor-pointer">
                          <i class="fa-solid fa-plus"></i> Thêm cọc
                      </button>
                  </div>
                  <div class="bg-gray-50 border border-gray-200 rounded-xl p-2 flex flex-wrap md:flex-nowrap gap-4 items-center shadow-inner">
                      <div class="text-xs font-semibold text-gray-800 flex items-center">
                          <span class="text-[11px] text-gray-400 font-medium mr-1.5">Ngày:</span> {{ formatDateVi(systemDate) }}
                      </div>
                      <div class="text-xs text-gray-800 flex items-center font-bold">
                          <span class="text-[11px] text-gray-400 font-medium mr-1.5">Số tiền:</span>
                          <input 
                            type="text" 
                            :value="formatCurrencyInput(modalForm.paymentValue)"
                            @input="e => modalForm.paymentValue = cleanCurrencyValue(e.target.value)"
                            class="border border-gray-300 rounded-lg px-2.5 py-0.5 text-xs font-black text-slate-800 focus:outline-none w-28 bg-white mr-1.5 text-right shadow-inner"
                          />
                          <i class="fa-solid fa-money-bill-wave text-green-600 mr-1.5 text-xs"></i> (Ghi nhận cọc)
                      </div>
                      <div class="font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg text-xs md:ml-auto border border-blue-100">
                          NB0021
                      </div>
                  </div>
              </div>

              <!-- Section 4: Ghi chú -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs flex flex-col">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-yellow-400 rounded-full mr-1.5"></div>
                      Ghi chú
                  </h3>
                  <textarea 
                    v-model="modalForm.note"
                    placeholder="Nhập ghi chú tại đây..." 
                    class="w-full border border-gray-300 rounded-xl p-2 focus:outline-none focus:border-blue-500 text-xs resize-none min-h-[54px] shadow-inner bg-gray-50/30 font-semibold"
                  ></textarea>
              </div>
            </div>

            <!-- Tab 2: Thông tin đưa đón -->
            <div v-else-if="modalSubTab === 'shuttle'" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col gap-4 animate-in">
              <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs table-auto">
                  <thead class="bg-slate-50 text-slate-500 font-semibold border-y border-slate-200">
                    <tr>
                      <th class="py-2 px-2 text-center w-[8%] font-semibold text-[11px]">Đón/Đưa</th>
                      <th class="py-2 px-2 text-center w-[12%] font-semibold text-[11px]">Phương tiện</th>
                      <th class="py-2 px-2 text-center w-[11%] font-semibold text-[11px]">Mã hiệu / Biển số</th>
                      <th class="py-2 px-2 text-center w-[12%] font-semibold text-[11px]">Ngày đón/đưa</th>
                      <th class="py-2 px-2 text-center w-[8%] font-semibold text-[11px]">Giờ</th>
                      <th class="py-2 px-2 text-center w-[10%] font-semibold text-[11px]">Hiện giá</th>
                      <th class="py-2 px-2 text-center w-[15%] font-semibold text-[11px]">Địa điểm</th>
                      <th class="py-2 px-2 text-center w-[18%] font-semibold text-[11px]">Ghi chú</th>
                      <th class="py-2 px-2 w-[6%] text-center font-semibold text-[11px]">Hành động</th>
                    </tr>
                  </thead>
                  <tbody class="text-[11px] text-slate-700 font-medium">
                    <tr v-for="(row, idx) in modalForm.shuttleInfo" :key="row.id" class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors">
                      
                      <!-- Đón/Đưa -->
                      <td class="py-2 px-2">
                        <select v-model="row.type" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px] font-bold">
                          <option value="Đón">Đón</option>
                          <option value="Đưa">Đưa</option>
                        </select>
                      </td>

                      <!-- Phương tiện -->
                      <td class="py-2 px-2">
                        <select v-model="row.vehicle" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px]">
                          <option value="Select Value">— Chọn xe —</option>
                          <option value="4 Seater car">4 Seater car</option>
                          <option value="7 Seater car">7 Seater car</option>
                          <option value="16 Seater car">16 Seater car</option>
                          <option value="Bus">Bus</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </td>

                      <!-- Mã hiệu / Biển số -->
                      <td class="py-2 px-2">
                        <input type="text" v-model="row.code" placeholder="Mã hiệu / Biển số" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white shadow-sm text-[11px] font-bold" />
                      </td>

                      <!-- Ngày -->
                      <td class="py-2 px-2">
                        <div class="flex items-center justify-between border border-slate-300 rounded-md px-2 bg-white h-[30px] shadow-sm">
                          <input type="date" v-model="row.date" @click="$event.target.showPicker && $event.target.showPicker()" class="border-none bg-transparent p-0 text-[11px] font-bold text-slate-700 w-full focus:outline-none cursor-pointer date-span-input" />
                          <button @click.prevent="row.date = modalForm.checkIn" class="p-0.5 hover:bg-slate-100 rounded text-slate-400 border-none bg-transparent cursor-pointer ml-1" title="Sao chép ngày check-in">
                            <i class="fa-regular fa-calendar-days text-[11px]"></i>
                          </button>
                        </div>
                      </td>

                      <!-- Giờ -->
                      <td class="py-2 px-2">
                        <input type="time" v-model="row.time" class="border border-slate-300 rounded-md px-2 text-[11px] font-bold text-slate-700 w-full focus:outline-none bg-white cursor-pointer shadow-sm h-[30px]" />
                      </td>

                      <!-- Hiện giá -->
                      <td class="py-2 px-2">
                        <div class="relative w-full border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="text" :value="formatCurrencyInput(row.price)" @input="e => row.price = cleanCurrencyValue(e.target.value)" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.price = (Number(row.price) || 0) + 10000" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.price >= 10000 ? row.price = (Number(row.price) || 0) - 10000 : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Địa điểm -->
                      <td class="py-2 px-2">
                        <select v-model="row.location" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px]">
                          <option value="">— Chọn địa điểm —</option>
                          <option value="Sân bay Cam Ranh">Sân bay Cam Ranh</option>
                          <option value="Ga Nha Trang">Ga Nha Trang</option>
                          <option value="Bến xe Nha Trang">Bến xe Nha Trang</option>
                          <option value="Khách sạn">Khách sạn</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </td>

                      <!-- Ghi chú -->
                      <td class="py-2 px-2">
                        <input type="text" v-model="row.note" placeholder="Nhập ghi chú..." class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white shadow-sm text-[11px]" />
                      </td>

                      <!-- Hành động (Xóa) -->
                      <td class="py-2 px-2 text-center">
                        <button @click.prevent="removeShuttleRow(idx)" class="text-rose-500 hover:text-rose-700 hover:underline border-none bg-transparent cursor-pointer font-bold text-xs">
                          <i class="fa-regular fa-trash-can mr-1"></i>Xóa
                        </button>
                      </td>
                    </tr>
                    <tr v-if="modalForm.shuttleInfo.length === 0">
                      <td colspan="9" class="py-8 text-center text-slate-400 font-bold text-xs bg-slate-50/10">
                        Chưa có lịch đưa đón nào được thêm. Bấm nút (+) bên dưới để thêm mới.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Add Row Button -->
              <div class="flex">
                <button
                  @click.prevent="addShuttleRow"
                  class="w-7 h-7 bg-[#006bdb]/10 hover:bg-[#006bdb]/20 text-[#006bdb] rounded-full flex items-center justify-center cursor-pointer border-none transition-all active:scale-90"
                  title="Thêm dòng mới"
                >
                  <span class="font-black text-base">+</span>
                </button>
              </div>
            </div>

            <!-- Tab 3: Lấy phòng -->
            <div v-else-if="modalSubTab === 'rooms'" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col gap-4 relative animate-in">
              
              <!-- Column Selector Icon at Top Right -->
              <div class="flex justify-end items-center relative z-20 shrink-0">
                <button
                  @click="showColumnSelector = !showColumnSelector"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 border border-slate-200 bg-white shadow-xs cursor-pointer transition-colors"
                  title="Cấu hình hiển thị cột"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                  </svg>
                </button>

                <!-- Column Toggle Dropdown -->
                <div v-if="showColumnSelector" class="absolute right-0 top-10 bg-white border border-slate-200 rounded-lg shadow-xl p-3 w-56 flex flex-col gap-2 z-30 select-none animate-in">
                  <span class="text-xs font-black text-slate-400 uppercase tracking-wider border-b pb-1">Cột hiển thị</span>
                  
                  <div class="flex flex-col gap-1.5 overflow-y-auto max-h-72 text-sm font-bold text-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.roomType" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Loại/Dạng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.dates" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Ngày đến -> Ngày đi</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.occupancy" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Chiếm dụng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.availability" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Phòng trống</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.quantity" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Số lượng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.price" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Giá phòng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.rateCode" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Mã giá phòng/Gói</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.discount" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Tăng/Giảm giá</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.upgrade" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Nâng hạng phòng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.adults" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Người lớn</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.babies" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Em bé</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.children" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Trẻ em</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.childBreakfastRate" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Giá ăn sáng TE</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.breakfast" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Ăn sáng</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Grid rooms table allocations -->
              <div class="w-full px-4 pb-4 overflow-x-auto">
                <table class="min-w-[1300px] w-full border-collapse text-left text-xs table-auto">
                  <thead class="bg-slate-50 text-slate-500 font-semibold border-y border-slate-200">
                    <tr>
                      <th v-if="visibleColumns.roomType" class="py-2 px-2 text-center w-[6%] font-semibold text-[11px]">Loại/Dạng</th>
                      <th v-if="visibleColumns.dates" class="py-2 px-2 text-center w-[18%] font-semibold text-[11px]">Ngày đến ~ Ngày đi</th>
                      <th v-if="visibleColumns.occupancy" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Chiếm dụng</th>
                      <th v-if="visibleColumns.availability" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Trống</th>
                      <th v-if="visibleColumns.quantity" class="py-2 px-1 text-center w-[7%] font-semibold text-[11px] bg-slate-100/50">Số lượng</th>
                      <th v-if="visibleColumns.price" class="py-2 px-1 text-center w-[10%] font-semibold text-[11px]">Giá phòng</th>
                      <th v-if="visibleColumns.rateCode" class="py-2 px-2 text-center w-[13%] font-semibold text-[11px]">Mã giá phòng</th>
                      <th v-if="visibleColumns.discount" class="py-2 px-2 text-center w-[10%] font-semibold text-[11px]">Tăng/Giảm</th>
                      <th v-if="visibleColumns.upgrade" class="py-2 px-2 text-center w-[11%] font-semibold text-[11px]">Nâng hạng</th>
                      <th v-if="visibleColumns.adults" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Người lớn</th>
                      <th v-if="visibleColumns.babies" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Em bé</th>
                      <th v-if="visibleColumns.children" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Trẻ em</th>
                      <th v-if="visibleColumns.childBreakfastRate" class="py-2 px-1 text-center w-[9%] font-semibold text-[11px]">Giá ăn sáng trẻ em</th>
                      <th v-if="visibleColumns.breakfast" class="py-2 px-1 text-center w-[4%] font-semibold text-[11px]">Ăn sáng</th>
                    </tr>
                  </thead>
                  
                  <tbody class="text-[11px] text-slate-700 font-medium select-none">
                    <tr v-for="row in modalForm.roomAllocations" :key="row.roomClassId" class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors">
                      
                      <!-- Loại/Dạng -->
                      <td v-if="visibleColumns.roomType" class="py-2 px-2 font-bold text-slate-900">{{ row.roomClassCode }}</td>
                      
                      <!-- Ngày đến ~ Ngày đi -->
                      <td v-if="visibleColumns.dates" class="py-2 px-2">
                        <div class="flex items-center justify-center space-x-1 border border-slate-300 rounded-md px-1.5 py-0.5 bg-white h-[30px] shadow-sm text-center whitespace-nowrap">
                          <input 
                            type="date" 
                            v-model="row.arrivalDate" 
                            :min="systemDate"
                            @change="handleRowDateChange(row)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input text-[11px] font-bold text-slate-700 focus:outline-none cursor-pointer bg-transparent border-none p-0 outline-none w-[76px]"
                          />
                          <span class="text-slate-400">~</span>
                          <input 
                            type="date" 
                            v-model="row.departureDate" 
                            :min="row.arrivalDate"
                            @change="handleRowDateChange(row)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input text-[11px] font-bold text-slate-700 focus:outline-none cursor-pointer bg-transparent border-none p-0 outline-none w-[76px]"
                          />
                        </div>
                      </td>
                      
                      <!-- Chiếm dụng -->
                      <td v-if="visibleColumns.occupancy" class="py-2 px-1 text-center font-semibold text-slate-600">{{ row.occupancy || 0 }}%</td>
                      
                      <!-- Phòng trống -->
                      <td v-if="visibleColumns.availability" class="py-2 px-1 text-center font-bold" :class="row.availableRooms <= 0 ? 'text-rose-600' : 'text-slate-800'">
                        {{ row.availableRooms }}
                      </td>
                      
                      <!-- Số lượng -->
                      <td v-if="visibleColumns.quantity" class="py-2 px-1 bg-slate-50/30">
                        <div class="relative w-full min-w-[40px] max-w-[60px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.quantity" min="0" @input="updateAllocatedRooms(row)" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.quantity++; updateAllocatedRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.quantity > 0 ? (row.quantity--, updateAllocatedRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Giá phòng -->
                      <td v-if="visibleColumns.price" class="py-2 px-1">
                        <div class="relative w-full min-w-[70px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="text" :value="formatCurrencyInput(row.price)" @input="e => row.price = cleanCurrencyValue(e.target.value)" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.price = (Number(row.price) || 0) + 50000" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.price >= 50000 ? row.price = (Number(row.price) || 0) - 50000 : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Mã giá phòng -->
                      <td v-if="visibleColumns.rateCode" class="py-2 px-2">
                        <input type="text" v-model="row.rateCode" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none shadow-sm bg-white text-[11px]">
                      </td>

                      <!-- Tăng/Giảm -->
                      <td v-if="visibleColumns.discount" class="py-2 px-2">
                        <input type="text" v-model="row.discount" placeholder="Nhập giá" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none shadow-sm text-[11px]">
                      </td>

                      <!-- Nâng hạng -->
                      <td v-if="visibleColumns.upgrade" class="py-2 px-2">
                        <div class="relative">
                          <select v-model="row.upgradeClassId" class="w-full border border-slate-300 rounded-md h-[30px] pl-2 pr-5 appearance-none focus:outline-none text-slate-700 bg-white shadow-sm cursor-pointer text-[11px]">
                            <option :value="null">Select</option>
                            <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
                          </select>
                          <i class="fa-solid fa-chevron-down absolute right-2.5 top-2.5 text-slate-400 text-[10px] pointer-events-none"></i>
                        </div>
                      </td>

                      <!-- Người lớn -->
                      <td v-if="visibleColumns.adults" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.adults" min="1" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.adults++" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.adults > 1 ? row.adults-- : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Em bé -->
                      <td v-if="visibleColumns.babies" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.babies" min="0" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.babies++" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.babies > 0 ? row.babies-- : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Trẻ em -->
                      <td v-if="visibleColumns.children" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.children" min="0" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.children++" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.children > 0 ? row.children-- : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Giá ăn sáng trẻ em -->
                      <td v-if="visibleColumns.childBreakfastRate" class="py-2 px-1">
                        <div class="relative w-full min-w-[65px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="text" :value="formatCurrencyInput(row.childBreakfastRate)" @input="e => row.childBreakfastRate = cleanCurrencyValue(e.target.value)" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.childBreakfastRate = (Number(row.childBreakfastRate) || 0) + 5000" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.childBreakfastRate >= 5000 ? row.childBreakfastRate = (Number(row.childBreakfastRate) || 0) - 5000 : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Ăn sáng -->
                      <td v-if="visibleColumns.breakfast" class="py-2 px-1 text-center">
                        <input type="checkbox" v-model="row.breakfastIncluded" class="w-4 h-4 accent-blue-500 cursor-pointer rounded border-slate-300">
                      </td>

                    </tr>
                  </tbody>
                  
                  <tfoot class="bg-white font-bold text-slate-900 border-t border-slate-300 text-[11px]">
                    <tr>
                      <td v-if="visibleColumns.roomType" class="py-2.5 px-2 text-left text-slate-800" colspan="2">Tổng: {{ allocationsSummary.count }}</td>
                      <td v-if="visibleColumns.dates" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.occupancy" class="py-2.5 px-1 text-center"></td>
                      <td v-if="visibleColumns.availability" class="py-2.5 px-1 text-center text-[11px] font-semibold">{{ allocationsSummary.availableRooms }}</td>
                      <td v-if="visibleColumns.quantity" class="py-2.5 px-1 text-center text-[11px] font-semibold text-sky-600 bg-slate-100/30">{{ allocationsSummary.quantity }}</td>
                      <td v-if="visibleColumns.price" class="py-2.5 px-1"></td>
                      <td v-if="visibleColumns.rateCode" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.discount" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.upgrade" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.adults" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.adults }}</td>
                      <td v-if="visibleColumns.babies" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.babies }}</td>
                      <td v-if="visibleColumns.children" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.children }}</td>
                      <td v-if="visibleColumns.childBreakfastRate" class="py-2.5 px-1"></td>
                      <td v-if="visibleColumns.breakfast" class="py-2.5 px-1"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

          </div>

          <!-- MODAL FOOTER -->
          <div class="bg-white border-t border-gray-200 p-2.5 flex flex-col sm:flex-row justify-between items-center gap-2 shrink-0 rounded-b-xl">
              <div class="text-xs text-gray-400 flex items-center space-x-1.5 w-full sm:w-auto justify-center sm:justify-start pl-1">
                  <i class="fa-solid fa-user-pen text-gray-300 text-xs"></i>
                  <span>Tạo bởi: <strong class="text-gray-500 font-bold">{{ modalForm.dbId ? (booking?.created_by || 'system') : (currentUser?.username || 'system') }}</strong></span>
              </div>
              <div class="flex items-center space-x-2 w-full sm:w-auto justify-center sm:justify-end">
                  <button 
                    @click="closeModal"
                    class="px-4 py-1.5 border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition text-xs cursor-pointer bg-white"
                  >
                      Hủy bỏ
                  </button>
                  <button 
                    @click="handleSaveNewBooking"
                    class="px-4 py-1.5 bg-[#2563eb] text-white font-bold rounded-xl hover:bg-blue-700 transition flex items-center space-x-1.5 shadow-md text-xs cursor-pointer border-none"
                  >
                      <i class="fa-regular fa-floppy-disk"></i>
                      <span>{{ modalForm.dbId ? 'Cập nhật Booking' : 'Lưu Booking' }}</span>
                  </button>
              </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- DEPOSIT MODAL MATCHING ĐẶT CỌC.html -->
    <Teleport to="body">
      <div 
        v-if="isDepositModalOpen" 
        class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
      >
        <div class="w-full max-w-5xl bg-white shadow-2xl rounded-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
            
            <!-- HEADER -->
            <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 border-b border-[#1a2d42]">
                <div class="flex items-center space-x-2">
                    <div class="bg-blue-400/20 p-1.5 rounded-lg">
                        <i class="fa-solid fa-file-invoice-dollar text-blue-200 text-xs"></i>
                    </div>
                    <span class="font-bold text-xs tracking-wide uppercase">Thêm đặt cọc</span>
                </div>
                <button @click="isDepositModalOpen = false" class="text-slate-300 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10 cursor-pointer border-none bg-transparent">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- SCROLLABLE CONTENT -->
            <div class="overflow-y-auto p-4 bg-white flex flex-col space-y-3 shrink-0">
                
                <div class="w-1/2 pr-2">
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tên đăng ký</label>
                    <div class="relative">
                        <select disabled class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs font-medium bg-slate-50 text-slate-800 appearance-none focus:outline-none shadow-sm cursor-not-allowed">
                            <option>{{ modalForm.bookingName || '123131' }}</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Số tiền <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input 
                              type="text" 
                              :value="formatCurrencyInput(depositForm.amount)"
                              @input="e => depositForm.amount = cleanCurrencyValue(e.target.value)"
                              class="w-full border border-blue-200 rounded-lg px-3 h-[30px] text-xs font-bold bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm"
                            >
                            <div class="absolute right-1 top-0.5 flex flex-col">
                                <button type="button" @click="depositForm.amount++" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-up"></i></button>
                                <button type="button" @click="depositForm.amount = Math.max(0, depositForm.amount - 1)" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-down"></i></button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Phương thức đặt cọc <span class="text-rose-500">*</span></label>
                        <div class="relative h-[30px]">
                            <select 
                              v-model="depositForm.paymentMethodId"
                              class="w-full border border-blue-200 rounded-lg px-3 h-full text-xs font-medium bg-blue-50/70 text-black appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                            >
                                <option :value="null">Phương thức đặt cọc</option>
                                <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tài khoản ngân hàng</label>
                        <div class="relative">
                            <select 
                              v-model="depositForm.bankAccountId"
                              class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs bg-white text-slate-800 appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                            >
                                <option value="Tài khoản ngân hàng">Tài khoản ngân hàng</option>
                                <option value="Vietcombank - 1012345678">Vietcombank - 1012345678</option>
                                <option value="BIDV - 2012345678">BIDV - 2012345678</option>
                                <option value="Techcombank - 3012345678">Techcombank - 3012345678</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Ngày <span class="text-rose-500">*</span></label>
                        <div class="flex items-center space-x-2 border border-slate-300 rounded-lg px-3 h-[30px] bg-white shadow-sm text-xs font-medium text-slate-800 relative">
                            <input 
                              type="date" 
                              v-model="depositForm.date" 
                              class="date-span-input flex-1 text-left w-full"
                            />
                            <i class="fa-regular fa-calendar-days text-blue-500 cursor-pointer pointer-events-none"></i>
                            <i @click="navigator.clipboard.writeText(depositForm.date)" class="fa-regular fa-copy text-slate-400 hover:text-slate-600 cursor-pointer"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Mô tả</label>
                        <textarea 
                          v-model="depositForm.note"
                          placeholder="Nhập mô tả..." 
                          class="w-full border border-blue-200 rounded-lg p-2 text-xs font-medium bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm h-[56px] resize-none"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Lưu hình ảnh (Chứng từ / Biên lai)</label>
                        <div class="border border-dashed border-slate-300 rounded-lg h-[56px] bg-slate-50 flex items-center justify-center hover:bg-slate-100 hover:border-blue-400 transition cursor-pointer relative overflow-hidden group shadow-sm">
                            <input type="file" @change="handleDepositImageUpload" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*">
                            <div class="flex flex-col items-center space-y-1" v-if="!depositForm.image">
                                <i class="fa-solid fa-cloud-arrow-up text-slate-400 group-hover:text-blue-500 transition text-xs"></i>
                                <span class="text-[10px] text-slate-500 font-medium group-hover:text-blue-600 transition">Nhấp để tải ảnh lên hoặc kéo thả vào đây</span>
                            </div>
                            <div class="flex items-center space-x-2 p-1" v-else>
                                <img :src="depositForm.image" class="h-10 w-10 object-cover rounded border" />
                                <span class="text-xs text-green-600 font-bold">Hình ảnh đã chọn</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLE AND LIST -->
            <div class="bg-slate-50 p-4 border-t border-slate-200 flex-1 flex flex-col overflow-y-auto">
                
                <div class="flex justify-between items-end mb-1.5 shrink-0">
                    <h3 class="font-bold text-slate-800 text-[11px] uppercase tracking-wider flex items-center">
                        Danh sách đặt cọc <span class="text-rose-500 ml-1">*</span>
                    </h3>
                    
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <span class="text-[11px] text-slate-500 font-medium">Hiển thị xoá</span>
                            <div class="relative inline-block w-6 align-middle select-none transition duration-200 ease-in">
                                <input type="checkbox" name="toggle" id="show-deleted" class="toggle-checkbox absolute block w-3 h-3 rounded-full bg-white border-2 border-slate-300 appearance-none cursor-pointer top-0 bottom-0 m-auto z-10 transition-transform duration-200 ease-in-out left-0"/>
                                <label for="show-deleted" class="toggle-label block overflow-hidden h-3 rounded-full bg-slate-300 cursor-pointer transition-colors duration-200"></label>
                            </div>
                        </div>
                        <button class="text-slate-400 hover:text-blue-600 transition border-none bg-transparent cursor-pointer">
                            <i class="fa-solid fa-sliders text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm mb-1">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                                <th class="p-2 w-10 text-center">
                                    <input 
                                      type="checkbox" 
                                      class="rounded border-slate-300 font-normal"
                                      :checked="selectedDepositIds.length === modalForm.deposits?.length"
                                      @change="selectedDepositIds = $event.target.checked ? modalForm.deposits.map(d => d.id) : []"
                                    >
                                </th>
                                <th class="p-2 min-w-[80px]">Ngày</th>
                                <th class="p-2 min-w-[60px]">Giờ</th>
                                <th class="p-2 min-w-[130px]">Phương thức thanh toán</th>
                                <th class="p-2 min-w-[150px]">Mô tả</th>
                                <th class="p-2 min-w-[90px] text-right">Số tiền</th>
                                <th class="p-2 min-w-[60px] text-center">Tiền tệ</th>
                                <th class="p-2 min-w-[110px]">Người nhận</th>
                                <th class="p-2 min-w-[100px] text-center">Chứng từ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr 
                              v-for="dep in modalForm.deposits" 
                              :key="dep.id" 
                              class="border-b border-slate-100 hover:bg-slate-50/80 transition"
                              :class="{ 'bg-blue-50/30': selectedDepositIds.includes(dep.id) }"
                            >
                                <td class="p-2 text-center align-middle">
                                    <input 
                                      type="checkbox" 
                                      :value="dep.id" 
                                      v-model="selectedDepositIds"
                                      class="rounded border-slate-300 font-normal"
                                    >
                                </td>
                                <td class="p-2 font-medium text-slate-800 align-middle">{{ dep.date }}</td>
                                <td class="p-2 text-slate-600 align-middle">{{ dep.time }}</td>
                                <td class="p-2 text-slate-800 align-middle">{{ paymentMethods.find(x => x.id === dep.paymentMethodId)?.name || 'BT' }}</td>
                                <td class="p-2 text-slate-600 align-middle">{{ dep.note }}</td>
                                <td class="p-2 text-right font-mono font-semibold text-slate-900 align-middle">{{ dep.amount.toLocaleString('vi-VN') }}</td>
                                <td class="p-2 text-center text-slate-500 align-middle">{{ dep.currency }}</td>
                                <td class="p-2 text-slate-700 font-medium align-middle">{{ dep.recipient }}</td>
                                <td class="p-2 text-center align-middle">
                                    <div class="flex items-center justify-center space-x-1.5">
                                        <div 
                                          v-for="(img, iIdx) in (dep.images || [])" 
                                          :key="iIdx"
                                          class="relative group w-7 h-7 rounded border border-slate-200 overflow-hidden shadow-sm bg-white cursor-pointer flex-shrink-0"
                                        >
                                            <div class="w-full h-full flex items-center justify-center bg-slate-100 text-[8px] font-bold text-slate-500 uppercase">
                                                {{ img }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!modalForm.deposits || modalForm.deposits.length === 0" class="border-b border-slate-100">
                                <td colspan="9" class="p-4 text-center text-slate-400 italic">Chưa có thông tin đặt cọc.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER ACTIONS -->
            <div class="bg-white border-t border-slate-200 p-2.5 px-4 flex justify-between items-center shrink-0">
                
                <div class="flex items-center space-x-2">
                    <button type="button" @click="splitDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                        <i class="fa-solid fa-code-branch text-[10px]"></i>
                        <span>Tách</span>
                    </button>
                    <button type="button" @click="transferDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                        <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                        <span>Chuyển</span>
                    </button>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="button" @click="deleteDeposits" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                        <span>Xóa</span>
                    </button>
                    <button type="button" @click="editDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        <span>Sửa</span>
                    </button>
                    <button type="button" @click="saveDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                        <i class="fa-regular fa-floppy-disk text-[10px]"></i>
                        <span>Lưu</span>
                    </button>
                    <button type="button" @click="addDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center space-x-1.5 shadow-md text-xs tracking-wide cursor-pointer border-none">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>Thêm</span>
                    </button>
                </div>
                
            </div>

        </div>
      </div>
    </Teleport>
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
.animate-in {
  animation: fadeIn 0.15s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(2px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Ghi đè để làm tròn ô chọn màu hệ thống */
input[type="color"]::-webkit-color-swatch-wrapper {
  padding: 0;
}
input[type="color"]::-webkit-color-swatch {
  border: none;
  border-radius: 50%;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.1);
}

/* Custom date input styling to look like plain text spans */
.date-span-input {
  border: none;
  background: transparent;
  padding: 0;
  margin: 0;
  font-size: inherit;
  font-weight: inherit;
  color: inherit;
  outline: none;
  cursor: pointer;
  width: 82px;
  text-align: center;
}
.date-span-input::-webkit-calendar-picker-indicator {
  display: none;
  -webkit-appearance: none;
}
.date-span-input::-webkit-inner-spin-button {
  display: none;
  -webkit-appearance: none;
}

/* Hide number input spinners */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type="number"] {
  -moz-appearance: textfield;
}
</style>
