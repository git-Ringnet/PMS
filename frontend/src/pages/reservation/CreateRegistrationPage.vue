<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
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
  checkAvailability,
  fetchVacantRooms,
  autoAssignRoom,
  checkInRoom,
  upgradeRoom,
  unassignRoom,
  cancelBookingRoom,
  fetchHotelServices,
  fetchBookingRoomServices,
  createBookingRoomService,
  deleteBookingRoomServicesBulk
} from '@/services/booking-service'

const route = useRoute()
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
const hotelServicesList = ref([])
const selectedServiceFilter = ref('all')

// ==================== TAB MANAGEMENT ====================
const tabs = ref([])
const activeTabId = ref(null)
const isLoadingBookings = ref(false)
const isLoading = ref(true)

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
function handleBlurChucNang() {
  setTimeout(() => {
    showChucNangMenu.value = false
  }, 200)
}
const selectedRoomAction = ref('0')

// ==================== REDESIGN GLOBAL SEARCH & DOCK ====================
const isGlobalSearchOpen = ref(false)
const globalSearchQuery = ref('')
const globalSearchResults = ref([])
const isSubListOpen = ref(false)
const isPrintPrice = ref(true)

const tableWrapRef = ref(null)
const footerScrollRef = ref(null)

function handleTableScroll() {
  if (tableWrapRef.value && footerScrollRef.value) {
    footerScrollRef.value.scrollLeft = tableWrapRef.value.scrollLeft
  }
}

watch(globalSearchQuery, async (newVal) => {
  if (!newVal || newVal.trim().length < 2) {
    globalSearchResults.value = []
    return
  }
  try {
    const res = await fetchBookings({ search: newVal.trim() })
    const list = res.data?.data || res.data || []
    globalSearchResults.value = list
  } catch (err) {
    console.error(err)
  }
})

function handleGlobalSearchResultClick(booking) {
  isGlobalSearchOpen.value = false
  globalSearchQuery.value = ''
  
  const existing = tabs.value.find(t => t.dbId === booking.id)
  if (existing) {
    activeTabId.value = existing.id
  } else {
    const newTab = { ...bookingToTab(booking) }
    tabs.value.push(newTab)
    activeTabId.value = newTab.id
  }
}

function openGlobalSearch() {
  isGlobalSearchOpen.value = true
  setTimeout(() => {
    const el = document.getElementById('gsInput')
    if (el) el.focus()
  }, 50)
}

function closeGlobalSearch() {
  isGlobalSearchOpen.value = false
  globalSearchQuery.value = ''
  globalSearchResults.value = []
}

function getColWidthPx(col) {
  if (!col || !col.width) return 'auto'
  const match = col.width.match(/w-\[(\d+)px\]/)
  return match ? `${match[1]}px` : 'auto'
}

// ==================== MODAL FORM ====================
const emptyForm = () => ({
  dbId: null,
  bookingCode: '',
  bookingName: '',
  color: '#000000',
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
const isColorChanged = ref(false)

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

function parseApiDate(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  if (dateStr.includes('T')) {
    const d = new Date(dateStr)
    if (!isNaN(d)) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  return dateStr.substring(0, 10)
}

async function syncDepositsFromBackend() {
  if (!modalForm.value.dbId) return
  try {
    const res = await fetchPayments(modalForm.value.dbId)
    const paymentsList = res.data?.data || res.data || []
    modalForm.value.deposits = paymentsList.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
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
  { key: 'upgrade', label: 'Nâng hạng', visible: true, width: 'w-[130px]', center: true },
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
const expandedRooms = ref([])

async function toggleRoomExpand(room) {
  const roomId = room.id
  const index = expandedRooms.value.indexOf(roomId)
  if (index === -1) {
    if (room.bookingRoomId) {
      try {
        const res = await fetchBookingRoomServices(room.bookingRoomId)
        room.services = res.data?.data || []
      } catch (err) {
        console.error('Lỗi khi tải dịch vụ bổ sung:', err)
      }
    }
    expandedRooms.value.push(roomId)
  } else {
    expandedRooms.value.splice(index, 1)
  }
}

function getRoomDisplayServices(room) {
  const list = []
  // 1. Dịch vụ phòng nghỉ mặc định (Room Charge)
  list.push({
    id: `room-charge-${room.id}`,
    service_date: room.checkIn,
    service_name: 'Dịch vụ phòng nghỉ',
    service_code: 'ROOM_CHARGE',
    quantity: 1,
    rate: room.price,
    is_room: true
  })
  // 2. Các dịch vụ bổ sung
  if (room.services && room.services.length > 0) {
    room.services.forEach(svc => {
      list.push({
        id: svc.id,
        service_date: svc.service_date,
        service_name: svc.service_name || getServiceNameFromCode(svc.service_code),
        service_code: svc.service_code,
        quantity: svc.quantity || 1,
        rate: svc.rate || 0,
        is_room: svc.is_room !== 0
      })
    })
  }
  return list
}

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

const isCheckInDisabled = computed(() => {
  const tab = activeTab.value
  if (!tab || !tab.rooms || tab.rooms.length === 0) return true

  const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
  if (selected.length > 0) {
    return selected.every(r => r.bookingRoomStatus === 1)
  }
  
  const targetList = tab.rooms.filter(r => r.roomNumber)
  if (targetList.length === 0) return true
  
  return targetList.every(r => r.bookingRoomStatus === 1)
})

const filteredActiveRooms = computed(() => {
  if (!activeTab.value || !activeTab.value.rooms) return []
  let list = activeTab.value.rooms

  if (selectedServiceFilter.value && selectedServiceFilter.value !== 'all') {
    list = list.filter(r => r.services && r.services.some(s => s.service_code === selectedServiceFilter.value))
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(r =>
      r.type.toLowerCase().includes(q) ||
      r.guestName.toLowerCase().includes(q) ||
      (r.roomNumber && r.roomNumber.toLowerCase().includes(q))
    )
  }

  return list
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
  try {
    isLoading.value = true
    await Promise.all([loadDropdowns(), loadBookings()])
    
    if (route.query.bookingCode) {
      const foundTab = tabs.value.find(t => t.id === route.query.bookingCode)
      if (foundTab) {
        activeTabId.value = foundTab.id
        if (route.query.openModal === 'true') {
          await openEditModal()
        }
      }
    }
  } catch (err) {
    console.error('Lỗi khi khởi tạo dữ liệu trang:', err)
  } finally {
    isLoading.value = false
  }
})

watch(() => route.query.bookingCode, async (newCode) => {
  if (newCode) {
    const foundTab = tabs.value.find(t => t.id === newCode)
    if (foundTab) {
      activeTabId.value = foundTab.id
      if (route.query.openModal === 'true') {
        await openEditModal()
      }
    }
  }
})

async function loadDropdowns() {
  try {
    const [
      mRes,
      csRes,
      bRes,
      cRes,
      pmRes,
      rsRes,
      uRes,
      rcRes,
      rrcRes,
      currRes,
      hsRes,
      settingsRes,
      sysTimeRes
    ] = await Promise.allSettled([
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
      fetchHotelServices(),
      fetchHotelSettings(),
      fetchSystemTime(),
    ])
    markets.value              = mRes.status  === 'fulfilled' ? (mRes.value.data?.data  || mRes.value.data  || []) : []
    customerSources.value      = csRes.status === 'fulfilled' ? (csRes.value.data?.data || csRes.value.data || []) : []
    bookers.value              = bRes.status  === 'fulfilled' ? (bRes.value.data?.data  || bRes.value.data  || []) : []
    companies.value            = cRes.status  === 'fulfilled' ? (cRes.value.data?.data  || cRes.value.data  || []) : []
    paymentMethods.value       = pmRes.status === 'fulfilled' ? (pmRes.value.data?.data || pmRes.value.data || []) : []
    registrationStatuses.value = rsRes.status === 'fulfilled' ? (rsRes.value.data?.data || rsRes.value.data || []) : []
    users.value                = uRes.status  === 'fulfilled' ? (uRes.value.data?.data  || uRes.value.data  || []) : []
    roomClasses.value          = (rcRes.status === 'fulfilled' ? (rcRes.value.data?.data || rcRes.value.data || []) : []).filter(c => c.is_active !== false)
    roomRateCodes.value        = rrcRes.status === 'fulfilled' ? (rrcRes.value.data?.data || rrcRes.value.data || []) : []
    currenciesList.value       = currRes.status === 'fulfilled' ? (currRes.value.data?.data || currRes.value.data || []) : []
    hotelServicesList.value    = hsRes.status === 'fulfilled' ? (hsRes.value.data?.data  || hsRes.value.data  || []) : []
    hotelSettings.value        = settingsRes.status === 'fulfilled' ? (settingsRes.value.data?.data || settingsRes.value.data || {}) : {}
    systemDate.value           = sysTimeRes.status === 'fulfilled' ? (sysTimeRes.value.data?.system_date || new Date().toISOString().split('T')[0]) : new Date().toISOString().split('T')[0]
  } catch (err) {
    console.error('Error loading dropdowns:', err)
  }
}

async function loadBookings() {
  isLoadingBookings.value = true
  try {
    const prevActiveId = activeTabId.value
    const res = await fetchBookings({ status: '0,1' })
    const list = res.data?.data || res.data || []
    tabs.value = list.map(b => bookingToTab(b))
    
    if (tabs.value.some(t => t.id === prevActiveId)) {
      activeTabId.value = prevActiveId
    } else if (tabs.value.length > 0) {
      activeTabId.value = tabs.value[0].id
    } else {
      tabs.value = [makeBlankTab()]
      activeTabId.value = tabs.value[0].id
    }
  } catch (err) {
    if (tabs.value.length === 0) {
      tabs.value = [makeBlankTab()]
      activeTabId.value = tabs.value[0].id
    }
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
      if (Number(br.status) === 3) return // Bỏ qua phòng đã hủy (STATUS_CANCELLED = 3)
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
        services: br.services || [],
        bookingRoomStatus: br.status !== undefined ? Number(br.status) : 0,
        upgradeClassId: null,
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
          bookingRoomStatus: 0,
          upgradeClassId: alloc.upgradeRoomClassId || null,
        })
      }
    })
  }

  const roomAllocations = []
  if (b.booking_rooms && b.booking_rooms.length > 0) {
    const grouped = {}
    b.booking_rooms.forEach(br => {
      const classId = br.room_class_id
      if (!grouped[classId]) {
        grouped[classId] = {
          roomClassId: classId,
          roomClassCode: br.room_class?.code || '',
          roomClassName: br.room_class?.name || '',
          arrivalDate: parseApiDate(br.arrival_date),
          departureDate: parseApiDate(br.departure_date),
          quantity: 0,
          price: Number(br.rate) || 0,
          ratePlanCode: '',
          discount: 'Tăng/Giảm giá',
          upgradeRoomClassId: null,
          adults: br.adults || 2,
          babies: 0,
          children: 0,
          childBreakfastRate: 90000,
          breakfastIncluded: true,
        }
      }
      grouped[classId].quantity++
      const childCount = br.children ? br.children.filter(c => c.age_group === 'child').length : 0
      const babyCount = br.children ? br.children.filter(c => c.age_group === 'baby').length : 0
      grouped[classId].children += childCount
      grouped[classId].babies += babyCount
    })
    Object.values(grouped).forEach(g => {
      roomAllocations.push(g)
    })
  } else {
    const dbAlloc = b.room_allocations || []
    dbAlloc.forEach(alloc => {
      roomAllocations.push({
        roomClassId: alloc.roomClassId,
        roomClassCode: alloc.roomClassCode,
        roomClassName: alloc.roomClassName,
        arrivalDate: alloc.arrivalDate,
        departureDate: alloc.departureDate,
        quantity: Number(alloc.quantity) || 0,
        price: Number(alloc.price) || 0,
        ratePlanCode: alloc.ratePlanCode || '',
        discount: alloc.discount || 'Tăng/Giảm giá',
        upgradeRoomClassId: alloc.upgradeRoomClassId || null,
        adults: Number(alloc.adults) || 2,
        babies: Number(alloc.babies) || 0,
        children: Number(alloc.children) || 0,
        childBreakfastRate: Number(alloc.childBreakfastRate) || 90000,
        breakfastIncluded: alloc.breakfastIncluded !== undefined ? !!alloc.breakfastIncluded : true,
      })
    })
  }

  return {
    id: b.booking_code,
    dbId: b.id,
    title: `Booking ${b.booking_code}`,
    bookingName: b.booking_name,
    statusLabel: b.registration_status?.name || '—',
    registrationStatusId: b.registration_status_id,
    checkIn: parseApiDate(b.arrival_date),
    checkOut: parseApiDate(b.departure_date),
    nights: b.num_of_days,
    deposit: b.payment_value || 0,
    company: b.company?.name || '—',
    companyId: b.company_id,
    confirmDate: parseApiDate(b.confirm_date),
    expiredDate: parseApiDate(b.expired_date),
    color: b.color || '#000000',
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
    roomAllocations: roomAllocations,
    deposits: b.payments ? b.payments.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
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
  isColorChanged.value = false
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
  isColorChanged.value = false
  modalForm.value = {
    dbId: tab.dbId,
    bookingCode: tab.id,
    bookingName: tab.bookingName,
    color: tab.color || '#000000',
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
        bookingRoomStatus: 0,
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
      color:                  (isColorChanged.value || modalForm.value.color !== '#000000') ? modalForm.value.color : null,
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

const vacantRoomsMap = ref({})

async function loadVacantRoomsForRoom(room) {
  const roomClassId = room.roomClassId
  const ci = parseDateVi(room.checkIn)
  const co = parseDateVi(room.checkOut)
  if (!roomClassId || !ci || !co) return

  const cacheKey = `${roomClassId}_${ci}_${co}`
  if (vacantRoomsMap.value[cacheKey]) return

  try {
    const res = await fetchVacantRooms({
      room_class_id: roomClassId,
      arrival_date: ci,
      departure_date: co,
      exclude_booking_room_id: room.bookingRoomId || undefined
    })
    vacantRoomsMap.value[cacheKey] = res.data?.data || res.data || []
  } catch (err) {
    console.error('Không thể tải danh sách phòng trống:', err)
  }
}

function getVacantRoomsList(room) {
  const ci = parseDateVi(room.checkIn)
  const co = parseDateVi(room.checkOut)
  const cacheKey = `${room.roomClassId}_${ci}_${co}`
  const list = vacantRoomsMap.value[cacheKey] || []
  return list.filter(r => r.room_number !== room.roomNumber)
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
  } else if (actionName === 'Cập nhật' || actionName === 'Thông tin đăng ký' || actionName === 'Thông tin khách hàng') {
    openEditModal()
  } else if (actionName === 'Hóa đơn' || actionName === 'Hoá đơn') {
    uiStore.showToast('Đang tiến hành tạo hóa đơn...', 'success')
  } else if (actionName === 'Nhân bản') {
    uiStore.showToast('Nhân bản thông tin đăng ký thành công!', 'success')
  } else if (actionName === 'GIAO PHÒNG') {
    if (isCheckInDisabled.value) {
      uiStore.showToast('Tất cả phòng được chọn (hoặc tất cả phòng gán số) đã được giao phòng!', 'warning')
      return
    }
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi giao phòng!', 'warning')
      return
    }
    if (!tab.rooms || tab.rooms.length === 0) {
      uiStore.showToast('Không có phòng nào để giao!', 'info')
      return
    }

    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetList = selected.length > 0 ? selected : tab.rooms.filter(r => r.roomNumber)

    if (targetList.length === 0) {
      uiStore.showToast('Vui lòng gán số phòng trước khi giao phòng!', 'warning')
      return
    }

    uiStore.showToast('Đang tiến hành giao phòng cho khách...', 'info')
    let successCount = 0
    let failCount = 0
    let failMessages = []

    try {
      await Promise.all(targetList.map(async (r) => {
        if (!r.bookingRoomId) return
        try {
          const res = await checkInRoom(tab.dbId, r.bookingRoomId)
          if (res.data?.success) {
            successCount++
            r.roomStatus = 'Đang ở'
          } else {
            failCount++
            failMessages.push(res.data?.message || `Phòng ${r.roomNumber} thất bại.`)
          }
        } catch (err) {
          console.error(err)
          failCount++
          failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber} thất bại.`)
        }
      }))

      await loadBookings()
      selectedRows.value = []

      if (successCount > 0) {
        uiStore.showToast(`Giao phòng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
      } else {
        uiStore.showToast(`Giao phòng thất bại: ${failMessages.join(', ')}`, 'error')
      }
    } catch(err) {
      console.error(err)
      uiStore.showToast('Có lỗi xảy ra khi giao phòng!', 'error')
    }
  } else if (actionName === 'Nâng hạng phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi nâng hạng phòng!', 'warning')
      return
    }
    if (!tab.rooms || tab.rooms.length === 0) {
      uiStore.showToast('Không có phòng nào để nâng hạng!', 'info')
      return
    }

    const targetList = tab.rooms.filter(r => selectedRows.value.includes(r.id) && r.upgradeClassId)
    if (targetList.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng và chọn hạng phòng muốn nâng lên!', 'warning')
      return
    }

    uiStore.showToast('Đang tiến hành nâng hạng phòng...', 'info')
    let successCount = 0
    let failCount = 0
    let failMessages = []

    try {
      await Promise.all(targetList.map(async (r) => {
        if (!r.bookingRoomId) return
        try {
          const data = { room_class_id: r.upgradeClassId }
          if (r.price) data.rate = Number(r.price)
          const res = await upgradeRoom(tab.dbId, r.bookingRoomId, data)
          if (res.data?.success) {
            successCount++
            r.roomNumber = ''
            r.roomClassId = r.upgradeClassId
          } else {
            failCount++
            failMessages.push(res.data?.message || `Phòng ${r.roomNumber || r.id} thất bại.`)
          }
        } catch (err) {
          console.error(err)
          failCount++
          failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber || r.id} thất bại.`)
        }
      }))

      if (successCount > 0) {
        await loadBookings()
        selectedRows.value = []
        uiStore.showToast(`Nâng hạng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
      } else {
        uiStore.showToast(`Nâng hạng thất bại: ${failMessages.join(', ')}`, 'error')
      }
    } catch(err) {
      console.error(err)
      uiStore.showToast('Có lỗi xảy ra khi nâng hạng phòng!', 'error')
    }
  } else if (actionName === 'Tự động gán phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi gán số phòng!', 'warning')
      return
    }
    if (!tab.rooms || tab.rooms.length === 0) {
      uiStore.showToast('Không có phòng nào để gán!', 'info')
      return
    }

    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetList = selected.length > 0 ? selected : tab.rooms

    uiStore.showToast('Đang tiến hành tự động gán số phòng...', 'info')
    let successCount = 0
    let failCount = 0

    try {
      await Promise.all(targetList.map(async (r) => {
        if (!r.bookingRoomId) return
        try {
          const res = await autoAssignRoom(tab.dbId, r.bookingRoomId)
          if (res.data?.success) {
            r.roomNumber = res.data.room_number || ''
            r.isPreassigned = true
            successCount++
          } else {
            failCount++
          }
        } catch (err) {
          console.error(err)
          failCount++
        }
      }))

      // Reset checkbox selection
      selectedRows.value = []

      if (successCount > 0) {
        uiStore.showToast(`Tự động gán thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng)` : ''}`, 'success')
      } else {
        uiStore.showToast('Không tìm thấy số phòng trống phù hợp cho các phòng đã chọn!', 'error')
      }
    } catch(err) {
      console.error(err)
      uiStore.showToast('Có lỗi xảy ra khi tự động gán phòng!', 'error')
    }
  } else if (actionName === 'Gỡ số phòng') {
    const tab = activeTab.value
    if (tab && tab.rooms) {
      const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
      const targetList = selected.length > 0 ? selected : tab.rooms
      
      if (tab.dbId) {
        uiStore.showToast('Đang tiến hành gỡ số phòng...', 'info')
        try {
          await Promise.all(targetList.map(async (r) => {
            if (!r.bookingRoomId) return
            await unassignRoom(tab.dbId, r.bookingRoomId)
            r.roomNumber = ''
            r.isPreassigned = false
          }))
          uiStore.showToast('Đã gỡ số phòng thành công!', 'success')
        } catch(err) {
          console.error(err)
          uiStore.showToast('Lỗi khi gỡ số phòng trên hệ thống!', 'error')
        }
      } else {
        targetList.forEach(r => {
          r.roomNumber = ''
          r.isPreassigned = false
        })
        uiStore.showToast('Đã gỡ số phòng thành công!', 'success')
      }
      selectedRows.value = []
    }
  } else if (actionName === 'Hủy phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi hủy phòng!', 'warning')
      return
    }
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetList = selected.length > 0 ? selected : tab.rooms
    if (targetList.length === 0) {
      uiStore.showToast('Không có phòng nào để hủy!', 'info')
      return
    }

    uiStore.confirm({
      title: 'Hủy phòng',
      message: selected.length > 0 
        ? `Bạn có chắc chắn muốn hủy ${selected.length} phòng đã chọn?` 
        : 'Bạn có chắc chắn muốn hủy toàn bộ phòng trong đăng ký này?',
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang tiến hành hủy phòng...', 'info')
        let successCount = 0
        let failCount = 0
        let failMessages = []

        try {
          await Promise.all(targetList.map(async (r) => {
            if (!r.bookingRoomId) return
            try {
              const res = await cancelBookingRoom(tab.dbId, r.bookingRoomId)
              if (res.data?.success) {
                successCount++
              } else {
                failCount++
                failMessages.push(res.data?.message || `Phòng ${r.roomNumber || r.type} thất bại.`)
              }
            } catch (err) {
              console.error(err)
              failCount++
              failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber || r.type} thất bại.`)
            }
          }))

          await loadBookings()
          selectedRows.value = []

          if (successCount > 0) {
            uiStore.showToast(`Hủy phòng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
          } else {
            uiStore.showToast(`Hủy phòng thất bại: ${failMessages.join(', ')}`, 'error')
          }
        } catch (err) {
          console.error(err)
          uiStore.showToast('Có lỗi xảy ra khi hủy phòng!', 'error')
        }
      }
    })
  } else if (actionName === 'Dịch vụ bổ sung') {
    const tab = activeTab.value
    if (!tab || !tab.rooms || tab.rooms.length === 0) return
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng muốn thêm dịch vụ bổ sung trên bảng!', 'warning')
      return
    }
    openServicesModal(selected[0])
  } else if (actionName === 'Xóa dịch vụ bổ sung') {
    const tab = activeTab.value
    if (!tab || !tab.rooms || tab.rooms.length === 0) return
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetRoom = selected.length > 0 ? selected[0] : null
    if (!targetRoom || !targetRoom.bookingRoomId) {
      uiStore.showToast('Vui lòng tích chọn phòng muốn xóa dịch vụ bổ sung!', 'warning')
      return
    }
    
    uiStore.confirm({
      title: 'Xóa dịch vụ bổ sung',
      message: `Bạn có chắc chắn muốn xóa toàn bộ dịch vụ bổ sung của phòng ${targetRoom.roomNumber || ''}?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang tiến hành xóa dịch vụ...', 'info')
        try {
          const res = await fetchBookingRoomServices(targetRoom.bookingRoomId)
          const existing = res.data?.data || []
          const ids = existing.map(x => x.id)
          if (ids.length > 0) {
            await deleteBookingRoomServicesBulk(targetRoom.bookingRoomId, { service_ids: ids })
          }
          uiStore.showToast('Đã xóa toàn bộ dịch vụ bổ sung thành công!', 'success')
          await loadBookings()
        } catch (err) {
          console.error(err)
          uiStore.showToast('Lỗi khi xóa dịch vụ bổ sung!', 'error')
        }
      }
    })
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

// ==================== DỊCH VỤ BỔ SUNG MODAL ====================
const isServicesModalOpen = ref(false)
const servicesModalRoom = ref(null)
const servicesModalSearch = ref('')
const selectedServiceCodes = ref([])
const checkedDates = ref([])
const serviceItems = ref([])

const filteredHotelServices = computed(() => {
  if (!servicesModalSearch.value) return hotelServicesList.value
  const q = servicesModalSearch.value.toLowerCase()
  return hotelServicesList.value.filter(s => 
    s.name.toLowerCase().includes(q) || 
    s.code.toLowerCase().includes(q)
  )
})

const stayDatesList = computed(() => {
  if (!servicesModalRoom.value) return []
  return getStayDates(servicesModalRoom.value.checkIn, servicesModalRoom.value.checkOut)
})

const servicesTotalAmount = computed(() => {
  return serviceItems.value.reduce((sum, item) => sum + (item.quantity * item.rate), 0)
})

function getStayDates(checkIn, checkOut) {
  const dates = []
  const start = new Date(parseDateVi(checkIn))
  const end = new Date(parseDateVi(checkOut))
  if (isNaN(start) || isNaN(end)) return dates
  
  let curr = new Date(start)
  while (curr < end) {
    dates.push(curr.toISOString().split('T')[0])
    curr.setDate(curr.getDate() + 1)
  }
  return dates
}



function formatDateShort(dateStr) {
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}`
  }
  return dateStr
}

function getServiceNameFromCode(code) {
  const svc = hotelServicesList.value.find(s => s.code === code)
  return svc ? svc.name : code
}

async function openServicesModal(room) {
  servicesModalRoom.value = room
  servicesModalSearch.value = ''
  selectedServiceCodes.value = []
  
  const dates = getStayDates(room.checkIn, room.checkOut)
  checkedDates.value = [...dates]
  
  try {
    const res = await fetchBookingRoomServices(room.bookingRoomId)
    const existing = res.data?.data || []
    
    const items = []
    const codes = []
    existing.forEach(svc => {
      if (!codes.includes(svc.service_code)) {
        codes.push(svc.service_code)
        items.push({
          service_code: svc.service_code,
          service_name: svc.service_name || getServiceNameFromCode(svc.service_code),
          quantity: svc.quantity || 1,
          rate: Number(svc.rate) || 0,
          is_room: svc.is_room !== 0
        })
      }
    })
    
    selectedServiceCodes.value = codes
    serviceItems.value = items
  } catch (err) {
    console.error(err)
    serviceItems.value = []
  }
  
  isServicesModalOpen.value = true
}

function handleServiceCheckboxChange(svc, checked) {
  if (checked) {
    if (!selectedServiceCodes.value.includes(svc.code)) {
      selectedServiceCodes.value.push(svc.code)
      serviceItems.value.push({
        service_code: svc.code,
        service_name: svc.name,
        quantity: 1,
        rate: Number(svc.price) || 0,
        is_room: true
      })
    }
  } else {
    selectedServiceCodes.value = selectedServiceCodes.value.filter(c => c !== svc.code)
    serviceItems.value = serviceItems.value.filter(i => i.service_code !== svc.code)
  }
}

function toggleAllDates(event) {
  if (event.target.checked) {
    checkedDates.value = [...stayDatesList.value]
  } else {
    checkedDates.value = []
  }
}

async function saveServices() {
  if (!servicesModalRoom.value) return
  const tab = activeTab.value
  if (!tab) return

  // Tìm danh sách các phòng được chọn trên bảng
  const selectedRooms = tab.rooms.filter(r => selectedRows.value.includes(r.id))
  
  // Áp dụng hàng loạt cho tất cả các phòng được tick chọn hoặc phòng đang sửa lẻ
  const targetRooms = selectedRooms.length > 0 ? selectedRooms : [servicesModalRoom.value]

  uiStore.showToast('Đang tiến hành lưu dịch vụ bổ sung...', 'info')
  let hasError = false
  let lastErrorMsg = ''

  for (const room of targetRooms) {
    const roomId = room.bookingRoomId
    if (!roomId) continue

    try {
      const res = await fetchBookingRoomServices(roomId)
      const existing = res.data?.data || []

      const toDeleteIds = []
      existing.forEach(svc => {
        const isCodeSelected = selectedServiceCodes.value.includes(svc.service_code)
        let svcDateShort = svc.service_date
        if (svcDateShort && svcDateShort.includes('T')) {
          svcDateShort = svcDateShort.split('T')[0]
        }
        const isDateChecked = checkedDates.value.includes(svcDateShort)
        if (!isCodeSelected || !isDateChecked) {
          toDeleteIds.push(svc.id)
        }
      })

      if (toDeleteIds.length > 0) {
        await deleteBookingRoomServicesBulk(roomId, { service_ids: toDeleteIds })
      }

      for (const item of serviceItems.value) {
        for (const d of checkedDates.value) {
          await createBookingRoomService(roomId, {
            service_code: item.service_code,
            service_name: item.service_name,
            service_date: d,
            quantity: item.quantity,
            rate: item.rate,
            is_room: item.is_room ? 1 : 0
          })
        }
      }

      // Đồng bộ local services cho từng phòng
      const updatedRes = await fetchBookingRoomServices(roomId)
      room.services = updatedRes.data?.data || []
    } catch (roomErr) {
      console.error(`Lỗi khi lưu dịch vụ cho phòng ${room.roomNumber || roomId}:`, roomErr)
      hasError = true
      lastErrorMsg = roomErr.response?.data?.message || roomErr.message || 'Lỗi khi kết nối server.'
    }
  }

  if (hasError) {
    uiStore.showToast(`Lưu dịch vụ hoàn tất nhưng có lỗi xảy ra: ${lastErrorMsg}`, 'error')
  } else {
    uiStore.showToast('Lưu dịch vụ bổ sung thành công cho tất cả phòng đã chọn!', 'success')
  }

  isServicesModalOpen.value = false
  await loadBookings()
  // Reset selection sau khi thực hiện xong
  selectedRows.value = []
}

async function openBookingModalByCode(bookingCode) {
  const foundTab = tabs.value.find(t => t.id === bookingCode)
  if (foundTab) {
    activeTabId.value = foundTab.id
    await openEditModal()
  } else {
    try {
      const res = await fetchBookings({ search: bookingCode })
      const list = res.data?.data || res.data || []
      if (list.length > 0) {
        const tabObj = bookingToTab(list[0])
        tabs.value.push(tabObj)
        activeTabId.value = tabObj.id
        await openEditModal()
      }
    } catch (err) {
      console.error(err)
    }
  }
}

defineExpose({
  openBookingModalByCode
})
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 animate-in select-none">
    
    <!-- DYNAMIC TABS HEADER BAR (Redesigned Top Bar) -->
    <div class="topbar shrink-0">
      <!-- Tabs list -->
      <div class="flex items-center gap-1.5 overflow-x-auto max-w-[40%] scrollbar-none">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="handleTabClick(tab.id)"
          class="booking-tab cursor-pointer whitespace-nowrap"
          :style="tab.id === activeTabId ? 'background: var(--navy-3); border: 1px solid rgba(255,255,255,0.15)' : 'background: rgba(255,255,255,0.06); color: #c7d2e0'"
        >
          <span>{{ tab.title }}</span>
          <span
            @click.stop="handleCloseTab(tab.id, $event)"
            class="x hover:text-white ml-1.5 transition-colors font-bold"
          >
            ✕
          </span>
        </button>

        <!-- ADD TAB BUTTON -->
        <button
          @click="handleAddTabClick"
          class="add-booking-btn"
          title="Tạo đăng ký mới"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          <span>Booking mới</span>
        </button>
      </div>

      <div class="spacer"></div>

      <!-- Action Buttons -->
      <div v-if="activeTab" class="flex items-center gap-2">
        <button class="btn" @click="triggerAction('Thông tin đăng ký')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
          Thông tin đăng ký
        </button>
        
        <button v-if="!isEditing" class="btn" @click="triggerAction('Sửa')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
          Sửa
        </button>
        <button v-else class="btn" @click="triggerAction('Quay lại')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
          Quay lại
        </button>

        <button 
          class="btn blue" 
          @click="triggerAction('Lưu')"
          :disabled="!isEditing"
          :class="{ 'opacity-50 cursor-not-allowed': !isEditing }"
        >
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
          Lưu
        </button>

        <button class="btn" @click="triggerAction('Nhân bản')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Nhân bản
        </button>

        <button class="btn red" @click="triggerAction('Xóa')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg>
          Xoá
        </button>

        <button class="btn" @click="triggerAction('In')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
          In
        </button>

        <button class="btn" @click="triggerAction('Thông báo')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg>
          Thông báo
        </button>
      </div>

      <div class="topbar-divider"></div>

      <button class="global-search-btn" @click="openGlobalSearch" title="Tìm kiếm toàn hệ thống">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
        <span>Tìm kiếm toàn hệ thống</span>
      </button>

      <!-- Hotel Service Filter + Column Selector in Top Bar -->
      <div v-if="activeTab" class="flex items-center gap-1.5 ml-2.5 text-white/90 text-xs font-bold shrink-0">
        <select 
          v-model="selectedServiceFilter" 
          class="topbar-service-select border border-white/10 rounded px-2.5 py-1 focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer text-xs text-white font-bold h-7"
          style="width: 140px !important; background-color: var(--navy) !important;"
        >
          <option value="all">Tất cả dịch vụ</option>
          <option v-for="svc in hotelServicesList" :key="svc.code" :value="svc.code">
            {{ svc.name }} ({{ svc.code }})
          </option>
        </select>

        <!-- Column Selector in Top Bar -->
        <div class="relative inline-block text-left">
          <button 
            @click="showTableColumnSelector = !showTableColumnSelector" 
            class="flex items-center justify-center p-1.5 rounded hover:bg-white/10 transition-colors h-7 w-7 text-white/70 hover:text-white" 
            title="Tùy chọn cột hiển thị"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
          </button>
          
          <div 
            v-if="showTableColumnSelector" 
            class="fixed inset-0 z-45 cursor-default" 
            @click="showTableColumnSelector = false"
          ></div>
          
          <div 
            v-if="showTableColumnSelector" 
            class="absolute right-0 mt-1.5 w-60 bg-white border border-slate-200 rounded-lg shadow-xl z-50 py-1.5 max-h-80 overflow-y-auto"
          >
            <div class="text-[11px] font-bold text-slate-500 mb-1 px-3 border-b pb-1 select-none">ẨN/HIỆN CỘT BẢNG</div>
            <div 
              v-for="col in columns" 
              :key="col.key" 
              class="px-3.5 py-1.5 hover:bg-slate-50 flex items-center gap-2 text-xs font-semibold text-slate-700 select-none cursor-grab active:cursor-grabbing"
              draggable="true"
              @dragstart="handleDragStart(col.key)"
              @dragover.prevent
              @drop="handleDrop(col.key)"
            >
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
      </div>
    </div>

    <!-- MAIN TAB CONTENT PANEL -->
    <div v-if="activeTab" class="flex-1 flex flex-col overflow-hidden">
      
      <!-- SUMMARY BAR Redesigned -->
      <div 
        class="summary-bar shrink-0 cursor-pointer" 
        @click="openEditModal"
        :title="`Chi tiết phiếu đăng ký: ${activeTab.bookingName || 'Trống'}\nTrạng thái: ${activeTabStatusName || 'Trống'}\nNgày đến/đi: ${formatDateVi(activeTab.checkIn)} ~ ${formatDateVi(activeTab.checkOut)}\nĐặt cọc: ${(activeTab.deposit || 0).toLocaleString('vi-VN')} VND\nCông ty: ${activeTab.company || '---'}\nXác nhận: ${formatDateVi(activeTab.confirmDate) || '---'}\n\n-> Click để mở chi tiết thông tin đăng ký (Edit Modal)`"
      >
        <div>
          <span class="label">Tên đăng ký:</span>
          <b class="uppercase font-black text-slate-800">{{ activeTab.bookingName || 'Trống' }}</b>
        </div>
        <div><span class="label">Trạng thái:</span><span class="status-pill select-none">{{ activeTabStatusName || 'Trống' }}</span></div>
        <div><span class="label">Ngày đến/đi:</span><b class="font-black text-slate-800">{{ formatDateVi(activeTab.checkIn) }} ~ {{ formatDateVi(activeTab.checkOut) }}</b></div>
        <div><span class="label">Đặt cọc:</span><b class="font-black text-slate-800">{{ (activeTab.deposit || 0).toLocaleString('vi-VN') }}</b></div>
        <div><span class="label">Công ty:</span><b class="font-black text-[#0f7d8c]">{{ activeTab.company || '---' }}</b></div>
        <div><span class="label">Xác nhận:</span><b class="font-bold text-slate-500">{{ formatDateVi(activeTab.confirmDate) || '---' }}</b></div>
        
        <div class="flex items-center gap-2" style="margin-left:auto;">
          <span class="view-detail-btn text-sky-600 hover:text-sky-800 text-[10.5px] font-black bg-sky-50 px-2.5 py-1.5 rounded border border-sky-200 inline-flex items-center gap-0.5 shadow-2xs select-none transition-all duration-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
            Xem chi tiết
          </span>
          <button class="chip-plain primary font-black" style="border-radius: 20px; padding: 7px 16px; background: var(--teal) !important; color: #fff !important;" @click.stop="triggerAction('Hóa đơn')">Hoá đơn</button>
        </div>
      </div>



      <!-- MAIN LAYOUT -->
      <div class="main-layout flex-1 flex min-h-0 relative bg-[#eef1f5]">
        <!-- ROOMS DATA TABLE LIST -->
        <div class="table-wrap flex-1 min-h-[300px]" id="tableWrap" ref="tableWrapRef" @scroll="handleTableScroll">
          <table class="w-full text-left border-collapse text-xs table-fixed min-w-[2450px]">
            <colgroup>
              <col style="width: 35px" />
              <col style="width: 50px" />
              <col style="width: 45px" />
              <col v-for="col in columns.filter(c => c.visible)" :key="col.key" :style="{ width: getColWidthPx(col) }" />
              <col style="width: 120px" />
            </colgroup>
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold select-none whitespace-nowrap h-9 text-xs">
                <th class="p-2 border-r border-slate-200 text-center w-[35px]"></th>
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
                  <button 
                    @click="collapsedSections.registrationStatus = !collapsedSections.registrationStatus" 
                    class="w-5 h-5 flex items-center justify-center rounded bg-[#8cc3f3] hover:bg-[#6baae6] text-white font-bold select-none cursor-pointer"
                    style="font-size: 13px; line-height: 1;"
                  >
                    {{ collapsedSections.registrationStatus ? '+' : '−' }}
                  </button>
                </td>
                <td class="p-2 border-r border-slate-200 text-center">
                  <input type="checkbox" :checked="selectRangeVal === roomsTotalSummary.count" disabled />
                </td>
                <td :colspan="columns.filter(c => c.visible).length + 2" class="p-2">
                  <div class="flex items-center gap-2.5">
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
                      <button 
                        @click="toggleGroupCollapse(typeName)" 
                        class="w-5 h-5 flex items-center justify-center rounded bg-[#8cc3f3] hover:bg-[#6baae6] text-white font-bold select-none cursor-pointer"
                        style="font-size: 13px; line-height: 1;"
                      >
                        {{ collapsedSections[typeName] ? '+' : '−' }}
                      </button>
                    </td>
                    <td class="p-2 border-r border-slate-200 text-center"></td>
                    <td :colspan="columns.filter(c => c.visible).length + 2" class="p-2 text-gray-900 font-bold text-xs uppercase tracking-wider">
                      {{ typeName }} ({{ roomsInGroup.length }})
                    </td>
                    <td class="sticky right-0 bg-[#f8fafc] border-l border-slate-200 z-10"></td>
                  </tr>

                  <!-- Rooms in Group -->
                  <template v-if="!collapsedSections[typeName]">
                    <template v-for="(room, idx) in roomsInGroup" :key="room.id">
                      <tr 
                        class="border-b border-slate-200 hover:bg-sky-50/30 transition-colors h-9 group cursor-pointer"
                        :class="{ 'bg-sky-50/60 ring-1 ring-inset ring-sky-200': selectedRows.includes(room.id) }"
                        @click="handleRowSelect(room.id)"
                        @dblclick.stop="openServicesModal(room)"
                        :title="`Phòng: ${room.roomNumber || '(chưa gán)'} | Khách: ${room.guestName || ''} | CI: ${room.checkIn} → CO: ${room.checkOut} | ${room.nights} đêm | ${(Number(room.total)||0).toLocaleString('vi-VN')}đ\nDouble-click → Dịch vụ bổ sung`"
                      >
                        <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                          <button 
                            @click="toggleRoomExpand(room)"
                            class="w-5 h-5 flex items-center justify-center rounded transition-colors text-white font-bold select-none cursor-pointer"
                            :class="expandedRooms.includes(room.id) ? 'bg-[#ff9e3b] hover:bg-[#e08422]' : 'bg-[#8cc3f3] hover:bg-[#6baae6]'"
                            style="font-size: 13px; line-height: 1;"
                          >
                            {{ expandedRooms.includes(room.id) ? '−' : '+' }}
                          </button>
                        </td>
                        <td class="p-2 border-r border-slate-200 text-center" @click.stop>
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
                          <select 
                            v-if="isEditing" 
                            v-model="room.roomNumber" 
                            @focus="loadVacantRoomsForRoom(room)"
                            class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none text-center cursor-pointer"
                          >
                            <option value="">— Gỡ số phòng —</option>
                            <option v-if="room.roomNumber" :value="room.roomNumber">{{ room.roomNumber }}</option>
                            <option 
                              v-for="vRoom in getVacantRoomsList(room)" 
                              :key="vRoom.room_number" 
                              :value="vRoom.room_number"
                            >
                              {{ vRoom.room_number }} ({{ vRoom.status }})
                            </option>
                          </select>
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
                          <button @click.stop="openServicesModal(room)" class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
                        </template>
                        <template v-else-if="col.key === 'breakfast'">
                          <label class="relative inline-flex items-center cursor-pointer scale-75">
                            <input type="checkbox" v-model="room.breakfast" class="sr-only peer" :disabled="!isEditing">
                            <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                          </label>
                        </template>
                        <template v-else-if="col.key === 'upgrade'">
                          <select v-model="room.upgradeClassId" @click.stop class="w-full border border-slate-300 rounded-md h-[26px] pl-1.5 pr-4 appearance-none focus:outline-none text-slate-700 bg-white shadow-sm cursor-pointer text-[10px]">
                            <option :value="null">Chọn hạng</option>
                            <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
                          </select>
                        </template>
                        <template v-else-if="col.key === 'extraBed'">
                          <button @click.stop="openServicesModal(room)" class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
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
                          <button @click.stop="openServicesModal(room)" class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Yêu cầu đặc biệt</button>
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
                        <td class="p-2 text-right text-gray-900 font-semibold sticky right-0 bg-white group-hover:bg-sky-50/30 border-l border-slate-200 z-10" @click.stop @dblclick.stop="openServicesModal(room)">{{ (Number(room.total) || 0).toLocaleString('vi-VN') }}</td>
                      </tr>

                      <!-- Expanded Services Row -->
                      <tr v-if="expandedRooms.includes(room.id)" :key="`services-${room.id}`" class="bg-slate-50/30">
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td :colspan="columns.filter(c => c.visible).length + 2" class="p-3 border-b border-slate-200 bg-slate-50/20 text-left">
                          <div class="max-w-[750px] border border-slate-200 rounded shadow-xs overflow-hidden bg-white my-1" @click.stop>
                            <table class="w-full text-left border-collapse text-[11px] table-fixed">
                              <colgroup>
                                <col style="width: 100px;" />
                                <col style="width: 200px;" />
                                <col style="width: 110px;" />
                                <col style="width: 110px;" />
                                <col style="width: 70px;" />
                                <col style="width: 100px;" />
                                <col style="width: 80px;" />
                              </colgroup>
                              <thead>
                                <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200 select-none">
                                  <th class="p-2 border-r border-slate-200">Ngày</th>
                                  <th class="p-2 border-r border-slate-200">Dịch vụ</th>
                                  <th class="p-2 border-r border-slate-200">Mã Giá Phòng</th>
                                  <th class="p-2 border-r border-slate-200">Tăng/giảm giá</th>
                                  <th class="p-2 border-r border-slate-200 text-center">Số lượng</th>
                                  <th class="p-2 border-r border-slate-200 text-right">Giá</th>
                                  <th class="p-2 text-center">GIT/FIT</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr 
                                  v-for="svc in getRoomDisplayServices(room)" 
                                  :key="svc.id" 
                                  class="border-b border-slate-100 hover:bg-slate-50/80 text-slate-600 font-semibold"
                                >
                                  <td class="p-2 border-r border-slate-100">{{ formatDateVi(svc.service_date) }}</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-800 font-bold">{{ svc.service_name }}</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-400 italic">—</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-400 italic">Tăng/Giảm giá</td>
                                  <td class="p-2 border-r border-slate-100 text-center text-slate-700">{{ svc.quantity || 1 }}</td>
                                  <td class="p-2 border-r border-slate-100 text-right text-slate-800 font-bold">{{ (Number(svc.rate) || 0).toLocaleString('vi-VN') }}</td>
                                  <td class="p-2 text-center">
                                    <span class="px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[9px] font-bold uppercase select-none">FIT</span>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- ACTION DOCK (Redesigned Sidebar Dock) -->
        <aside class="dock shrink-0" id="dock">
          <div class="dock-head"><span class="dot"></span>Chức năng</div>

          <div class="dock-group">
            <div class="dock-group-label">Cập nhật đăng ký</div>
            <div class="dock-item" @click="triggerAction('Cập nhật')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
              </span>
              <span class="lbl">Cập nhật</span>
            </div>
            <div class="dock-item" @click="triggerAction('Tự động gán phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h.01M15 9h.01M9 15h6"/></svg>
              </span>
              <span class="lbl">Tự động gán phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Nâng hạng phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
              </span>
              <span class="lbl">Nâng hạng phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Thông tin khách hàng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0114 0v1"/></svg>
              </span>
              <span class="lbl">Thông tin khách hàng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Giao phòng</div>
            <div 
              class="dock-item" 
              :class="{ 'opacity-50 pointer-events-none cursor-not-allowed': isCheckInDisabled }" 
              @click="!isCheckInDisabled && triggerAction('GIAO PHÒNG')"
            >
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
              </span>
              <span class="lbl">Giao phòng (Check-in)</span>
            </div>
            <div class="dock-item" @click="triggerAction('Gỡ số phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </span>
              <span class="lbl">Gỡ số phòng</span>
            </div>
            <div class="dock-item danger" @click="triggerAction('Hủy phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="9"/><path d="M9 15l6-6M9 9l6 6"/></svg>
              </span>
              <span class="lbl">Hủy phòng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Dịch vụ bổ sung</div>
            <div class="dock-item" @click="triggerAction('Dịch vụ bổ sung')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
              </span>
              <span class="lbl">Thêm dịch vụ bổ sung</span>
            </div>
            <div class="dock-item danger" @click="triggerAction('Xóa dịch vụ bổ sung')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg>
              </span>
              <span class="lbl">Xóa dịch vụ bổ sung</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Chọn hàng</div>
            <div class="dock-item" @click="selectedRows = activeTab?.rooms?.map(r => r.id) || []">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="11" width="4" height="4" rx="1"/><rect x="3" y="17" width="4" height="4" rx="1"/><path d="M10 7h11M10 13h11M10 19h11"/></svg>
              </span>
              <span class="lbl">Chọn tất cả</span>
            </div>
            <div class="dock-item" @click="selectedRows = []">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="11" width="4" height="4" rx="1"/><rect x="3" y="17" width="4" height="4" rx="1"/><path d="M10 7h11M10 13h5M10 19h8"/><path d="M19 16l3 3-3 3" stroke-linecap="round"/></svg>
              </span>
              <span class="lbl">Bỏ chọn tất cả</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Chuyển phòng</div>
            <div class="dock-item" @click="triggerAction('Khóa chuyển phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
              </span>
              <span class="lbl">Khóa chuyển phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Mở chuyển phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V9a4 4 0 018 0"/></svg>
              </span>
              <span class="lbl">Mở chuyển phòng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Xuất / In ấn</div>
            <div class="dock-item" @click="triggerAction('Xuất Excel')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M8 3H4v18h16V9l-6-6H8z"/><path d="M8 13l2 2 4-4"/></svg>
              </span>
              <span class="lbl">Xuất Excel</span>
            </div>
            <div class="dock-item expandable" @click="isSubListOpen = !isSubListOpen">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
              </span>
              <span class="lbl">In phiếu đăng ký khách<br><span class="sub-current">Đang chọn: {{ isPrintPrice ? 'Hiện giá' : 'Không hiện giá' }}</span></span>
              <span class="chevron" :class="{ open: isSubListOpen }">›</span>
            </div>
            <div class="sub-list" :class="{ open: isSubListOpen }">
              <div class="sub-item" :class="{ selected: isPrintPrice }" @click.stop="isPrintPrice = true"><span class="sub-dot"></span>Hiện giá</div>
              <div class="sub-item" :class="{ selected: !isPrintPrice }" @click.stop="isPrintPrice = false"><span class="sub-dot"></span>Không hiện giá</div>
            </div>
            <div class="dock-item" @click="triggerAction('In hoá đơn tạm')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
              </span>
              <span class="lbl">In hoá đơn tạm</span>
            </div>
          </div>

          <div class="dock-foot" @click="triggerAction('Hóa đơn')">
            <div class="dock-invoice">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/></svg>
              <span class="lbl">Hoá đơn</span>
            </div>
          </div>
        </aside>
      </div>

      <!-- PAGE FOOTER (Redesigned with Synchronized Horizontal Scroll) -->
      <div class="page-footer shrink-0">
        <div class="footer-scroll" id="footerScroll" ref="footerScrollRef">
          <table class="footer-table" style="min-width: 2450px; table-layout: fixed;">
            <colgroup>
              <col style="width: 50px" />
              <col style="width: 45px" />
              <col v-for="col in columns.filter(c => c.visible)" :key="col.key" :style="{ width: getColWidthPx(col) }" />
              <col style="width: 120px" />
            </colgroup>
            <tr class="h-9">
              <td class="p-2 border-r border-slate-200 text-center w-[50px]"></td>
              <td class="p-2 border-r border-slate-200 text-center w-[45px]"></td>
              <td v-for="col in columns.filter(c => c.visible)" 
                  :key="col.key" 
                  class="p-2 border-r border-slate-200 font-bold" 
                  :class="[col.width, col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
              >
                <template v-if="col.key === 'type'">
                  Tổng cộng: {{ roomsTotalSummary.count }}
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
              <td class="p-2 text-right text-sky-700 font-bold text-sm sticky right-0 bg-[#dde3ea] border-l border-slate-200 z-10 w-[120px] shadow-[-2px_0_5px_rgba(0,0,0,0.02)]">
                {{ roomsTotalSummary.total.toLocaleString('vi-VN') }}
              </td>
            </tr>
          </table>
        </div>
      </div>

    </div>

    <!-- GLOBAL SYSTEM SEARCH OVERLAY -->
    <div class="global-search-overlay" :class="{ show: isGlobalSearchOpen }" @click="closeGlobalSearch">
      <div class="global-search-modal animate-in" @click.stop>
        <div class="gs-input-row">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
          <input type="text" id="gsInput" v-model="globalSearchQuery" placeholder="Tìm phòng, tên khách, mã booking... trên toàn hệ thống" />
          <button class="gs-close" @click="closeGlobalSearch">✕</button>
        </div>
        <div class="gs-hint">Tìm kiếm này quét toàn bộ dữ liệu hệ thống — không giới hạn trong booking đang mở.</div>
        <div class="gs-results border-t border-slate-100">
          <div class="gs-section-label font-black text-xs text-slate-500 mb-2" v-if="globalSearchResults.length > 0">Kết quả tìm kiếm</div>
          <div class="gs-section-label font-bold text-slate-400 py-4 text-center italic" v-else-if="globalSearchQuery.trim().length >= 2">Không tìm thấy kết quả phù hợp</div>
          <div class="gs-section-label font-bold text-slate-400 py-4 text-center italic" v-else>Nhập ít nhất 2 ký tự để tìm kiếm...</div>
          
          <div 
            v-for="b in globalSearchResults" 
            :key="b.id" 
            class="gs-result border-b border-slate-50 last:border-none"
            @click="handleGlobalSearchResultClick(b)"
          >
            <span class="gs-tag guest">Booking</span> 
            <span class="font-extrabold text-slate-800">{{ b.booking_code }}</span> 
            · {{ b.booking_name }} · {{ b.company_name || 'Khách lẻ' }}
            <span class="gs-booking font-bold text-slate-500">Đến: {{ formatDateVi(b.check_in) }}</span>
          </div>
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
                    <input type="color" v-model="modalForm.color" @change="isColorChanged = true" class="w-3.5 h-3.5 cursor-pointer bg-transparent border-none p-0 outline-none">
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

    <!-- DỊCH VỤ BỔ SUNG MODAL (Screenshot 4 Match) -->
    <Teleport to="body">
      <div 
        v-if="isServicesModalOpen" 
        class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
      >
        <div 
          class="bg-white rounded-xl shadow-2xl w-full max-w-[1200px] overflow-hidden border border-gray-300 flex flex-col max-h-[85vh]"
        >
          <!-- MODAL HEADER -->
          <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0 select-none">
            <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
                <i class="fa-solid fa-bell-concierge text-blue-300"></i>
                <span>Dịch vụ bổ sung - PHÒNG {{ servicesModalRoom?.roomNumber || 'CHƯA GÁN' }} ({{ servicesModalRoom?.type }})</span>
            </div>
            <div class="flex items-center space-x-2 text-gray-300">
                <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="isServicesModalOpen = false">
                  <i class="fa-solid fa-xmark text-red-400"></i>
                </button>
            </div>
          </div>

          <!-- MODAL BODY -->
          <div class="flex flex-1 overflow-hidden min-h-[450px]">
            <!-- LEFT PANEL: Dịch vụ -->
            <div class="w-1/4 border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
              <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2">Dịch vụ</div>
              
              <!-- Search box -->
              <div class="relative mb-3 shrink-0">
                <input 
                  type="text" 
                  v-model="servicesModalSearch" 
                  placeholder="Tìm kiếm mã, tên..." 
                  class="w-full pl-7 pr-3 py-1 bg-white border border-slate-200 rounded-md text-[11px] focus:outline-none focus:ring-1 focus:ring-sky-500"
                />
                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[11px]"></i>
              </div>

              <!-- Services list -->
              <div class="flex-1 overflow-y-auto space-y-1 pr-1">
                <label 
                  v-for="svc in filteredHotelServices" 
                  :key="svc.code" 
                  class="flex items-start gap-2 p-1.5 hover:bg-slate-100 rounded-md cursor-pointer transition text-[11px] text-slate-700"
                >
                  <input 
                    type="checkbox" 
                    :checked="selectedServiceCodes.includes(svc.code)"
                    @change="e => handleServiceCheckboxChange(svc, e.target.checked)"
                    class="mt-0.5"
                  />
                  <div>
                    <div class="font-bold text-slate-800">{{ svc.name }}</div>
                    <div class="text-[9px] text-slate-400 font-mono">{{ svc.code }} - {{ Number(svc.price).toLocaleString('vi-VN') }} VND</div>
                  </div>
                </label>
              </div>
            </div>

            <!-- MIDDLE PANEL: Ngày -->
            <div class="w-[15%] border-r border-slate-200 flex flex-col p-3 bg-slate-50/50">
              <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2">Ngày</div>
              
              <!-- Select All dates checkbox -->
              <label class="flex items-center gap-2 p-1.5 border-b border-slate-200 font-bold cursor-pointer text-[11px] text-slate-700 mb-2 shrink-0">
                <input 
                  type="checkbox" 
                  :checked="checkedDates.length === stayDatesList.length" 
                  @change="toggleAllDates"
                />
                <span>Tất cả</span>
              </label>

              <!-- Stay dates list -->
              <div class="flex-1 overflow-y-auto space-y-1 pr-1">
                <label 
                  v-for="d in stayDatesList" 
                  :key="d" 
                  class="flex items-center gap-2 p-1.5 hover:bg-slate-100 rounded-md cursor-pointer transition text-[11px] text-slate-700 font-mono"
                >
                  <input 
                    type="checkbox" 
                    :value="d" 
                    v-model="checkedDates"
                  />
                  <span>{{ formatDateShort(d) }}</span>
                </label>
              </div>
            </div>

            <!-- RIGHT PANEL: Dịch vụ chọn -->
            <div class="w-[60%] flex flex-col p-3 bg-white">
              <div class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider mb-2 shrink-0">Chi tiết dịch vụ bổ sung</div>

              <!-- Table -->
              <div class="flex-1 overflow-y-auto border border-slate-200 rounded-lg">
                <table class="w-full border-collapse text-left text-[11px]">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold h-8">
                      <th class="p-2 pl-3">Dịch vụ</th>
                      <th class="p-2 text-center w-24">Số lượng</th>
                      <th class="p-2 text-right w-36">Đơn giá (VND)</th>
                      <th class="p-2 text-center w-28">FIT/GIT</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="(item, index) in serviceItems" 
                      :key="item.service_code"
                      class="border-b border-slate-100 hover:bg-slate-50/50 h-10 align-middle font-medium"
                    >
                      <td class="p-2 pl-3 font-bold text-slate-800">
                        {{ item.service_name }}
                        <span class="block text-[9px] text-slate-400 font-mono font-normal">{{ item.service_code }}</span>
                      </td>
                      <td class="p-2 text-center">
                        <input 
                          type="number" 
                          v-model.number="item.quantity" 
                          min="0.01" 
                          step="1"
                          class="w-16 border border-slate-200 rounded-md px-1 py-0.5 text-center font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                      </td>
                      <td class="p-2 text-right">
                        <input 
                          type="text" 
                          :value="formatCurrencyInput(item.rate)" 
                          @input="e => item.rate = cleanCurrencyValue(e.target.value)"
                          class="w-28 border border-slate-200 rounded-md px-2 py-0.5 text-right font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                      </td>
                      <td class="p-2 text-center">
                        <!-- Toggle FIT/GIT -->
                        <div class="flex items-center justify-center space-x-1.5">
                          <span class="text-[9px] font-bold" :class="item.is_room ? 'text-sky-500' : 'text-slate-400'">FIT</span>
                          <div class="relative inline-block w-8 h-4 align-middle select-none transition duration-200 ease-in">
                            <input 
                              type="checkbox" 
                              v-model="item.is_room" 
                              :id="'fit-toggle-' + index"
                              class="sr-only peer"
                            />
                            <label 
                              :for="'fit-toggle-' + index"
                              class="block overflow-hidden h-4 rounded-full bg-slate-300 peer-checked:bg-sky-500 cursor-pointer transition-colors duration-200"
                            ></label>
                            <span class="absolute block w-3 h-3 rounded-full bg-white top-0.5 left-0.5 peer-checked:translate-x-4 transition-transform duration-200 pointer-events-none"></span>
                          </div>
                          <span class="text-[9px] font-bold" :class="!item.is_room ? 'text-sky-500' : 'text-slate-400'">GIT</span>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="serviceItems.length === 0">
                      <td colspan="4" class="p-8 text-center text-slate-400 italic">
                        Chưa chọn dịch vụ nào. Hãy tích chọn dịch vụ ở cột bên trái!
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- MODAL FOOTER -->
          <div class="bg-slate-50 border-t border-slate-200 px-4 py-2.5 shrink-0 flex items-center justify-between">
            <div class="bg-[#e2e8f0] px-4 py-1.5 rounded-lg text-slate-700 font-extrabold text-xs shadow-inner">
              Tổng tiền: <span class="text-slate-900 ml-1 font-black">{{ servicesTotalAmount.toLocaleString('vi-VN') }} VND</span>
            </div>
            <div class="flex items-center space-x-2">
              <button 
                @click="saveServices" 
                class="bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer shadow-sm flex items-center space-x-1.5 transition border-none"
              >
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Lưu</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Global Loading Overlay -->
    <LoadingOverlay :show="isLoading" />
  </div>
</template>

<style>
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

:root {
  --navy: #12233d;
  --navy-2: #1b3357;
  --navy-3: #22406b;
  --teal: #0f7d8c;
  --teal-light: #e4f4f6;
  --ink: #1c2733;
  --ink-soft: #5c6b7a;
  --line: #dde3ea;
  --bg: #eef1f5;
  --panel: #ffffff;
  --amber: #c8862a;
  --amber-bg: #fbf1e2;
  --green: #1f8a52;
  --green-bg: #e7f6ee;
  --danger: #c1403f;
  --danger-bg: #fbeaea;
  --blue: #1c5aa6;
  --blue-bg: #eaf3ff;
}

/* ---------- TOP BAR ---------- */
.topbar {
  background: var(--navy);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
}
.booking-tab {
  background: var(--navy-2);
  color: #8fa6c4 !important;
  font-weight: 600;
  font-size: 12.5px;
  padding: 8px 14px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.booking-tab.active {
  background: var(--navy-3) !important;
  color: #fff !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.booking-tab:hover {
  background: var(--navy-3);
  color: #fff !important;
}
.booking-tab .x {
  color: #9db3d1;
  font-size: 13px;
  cursor: pointer;
}
.booking-tab .x:hover {
  color: #ffb8b6;
}
.add-booking-btn {
  display: flex;
  align-items: center;
  gap: 7px;
  background: rgba(255, 255, 255, 0.07);
  border: 1px dashed rgba(255, 255, 255, 0.35);
  color: #c7d2e0 !important;
  padding: 8px 13px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.add-booking-btn:hover {
  background: var(--teal);
  border-color: var(--teal);
  border-style: solid;
  color: #fff !important;
}
.add-booking-btn svg {
  flex: none;
}
.spacer {
  flex: 1;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 13px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.06);
  color: #dbe4ee !important;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}
.btn:hover {
  background: rgba(255, 255, 255, 0.12);
}
.btn.blue {
  background: var(--blue);
  border-color: var(--blue);
  color: #fff !important;
}
.btn.red {
  background: var(--danger);
  border-color: var(--danger);
  color: #fff !important;
}
.icon {
  width: 14px;
  height: 14px;
  flex: none;
}

.global-search-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.16);
  color: #dbe4ee !important;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}
.global-search-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff !important;
}
.topbar-divider {
  width: 1px;
  align-self: stretch;
  background: rgba(255, 255, 255, 0.14);
  margin: 2px 4px;
}

.global-search-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 20, 35, 0.5);
  display: none;
  align-items: flex-start;
  justify-content: center;
  z-index: 100;
  padding-top: 90px;
  backdrop-filter: blur(2px);
}
.global-search-overlay.show {
  display: flex;
}
.global-search-modal {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 650px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow: hidden;
}
.gs-input-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--line);
  color: var(--ink-soft);
}
.gs-input-row input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 15px;
  color: var(--ink);
}
.gs-close {
  border: none;
  background: none;
  font-size: 15px;
  color: var(--ink-soft);
  cursor: pointer;
}
.gs-hint {
  padding: 10px 18px;
  font-size: 11.5px;
  color: var(--ink-soft);
  background: #f8f9fb;
  border-bottom: 1px solid var(--line);
}
.gs-results {
  padding: 8px;
  max-height: 400px;
  overflow-y: auto;
}
.gs-section-label {
  font-size: 10.5px;
  font-weight: 700;
  color: var(--ink-soft);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  padding: 8px 10px 4px;
}
.gs-result {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 7px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.15s;
}
.gs-result:hover {
  background: #f4f7f9;
}
.gs-tag {
  font-size: 10.5px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 5px;
}
.gs-tag.room {
  background: var(--blue-bg);
  color: var(--blue);
}
.gs-tag.guest {
  background: var(--teal-light);
  color: #0c6a77;
}
.gs-booking {
  margin-left: auto;
  font-size: 11.5px;
  color: var(--ink-soft);
}

/* ---------- SUMMARY ---------- */
.summary-bar {
  background: var(--panel);
  padding: 10px 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: center;
  border-bottom: 1px solid var(--line);
  font-size: 12.5px;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.summary-bar:hover {
  background: #f1f5f9;
  border-bottom-color: #cbd5e1;
  box-shadow: inset 0 -2px 4px rgba(0, 0, 0, 0.02);
}
.summary-bar .view-detail-btn {
  opacity: 0;
  transform: translateX(8px);
  pointer-events: none;
  transition: all 0.2s ease-in-out;
}
.summary-bar:hover .view-detail-btn {
  opacity: 1;
  transform: translateX(0);
  pointer-events: auto;
}
.summary-bar .label {
  color: var(--ink-soft);
  margin-right: 4px;
}
.summary-bar b {
  font-weight: 700;
  color: var(--ink);
}
.topbar-service-select {
  max-width: 140px;
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
  padding-right: 20px !important;
}
.topbar-service-select option {
  background-color: var(--navy) !important;
  color: #fff !important;
}
.status-pill {
  background: var(--green-bg);
  color: var(--green);
  padding: 2px 10px;
  border-radius: 20px;
  font-weight: 700;
  font-size: 11.5px;
  text-transform: uppercase;
}

/* ---------- SEARCH + QUICK ACTIONS ROW ---------- */
.quick-row {
  background: var(--panel);
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--line);
}
.search-box {
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 6px 10px;
  background: #fff;
  min-width: 240px;
}
.search-box input {
  border: none;
  outline: none;
  font-size: 12px;
  width: 100%;
  background: transparent;
  color: var(--ink);
}
.search-box input::placeholder {
  color: var(--ink-soft);
}
.quick-row .right-group {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 10px;
}
.chip-plain {
  padding: 7px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s;
}
.chip-plain:hover {
  background: #f8fafc;
}
.chip-plain.primary {
  background: var(--teal);
  border-color: var(--teal);
  color: #fff;
}
.chip-plain.primary:hover {
  background: #0c6a77;
}

/* ---------- MAIN LAYOUT ---------- */
.main-layout {
  display: flex;
  position: relative;
}

/* ---------- TABLE ---------- */
.table-wrap {
  background: var(--panel);
  margin: 14px 16px 90px 16px;
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: auto;
  flex: 1;
  min-width: 0;
}
.table-wrap table {
  border-collapse: collapse;
  width: 100%;
}
.table-wrap thead th {
  position: sticky;
  top: 0;
  z-index: 10;
  background: #f5f7fa;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: 10.8px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  padding: 9px 7px;
  border-bottom: 1px solid var(--line);
  border-right: 1px solid rgba(18, 35, 61, 0.05);
  text-align: left;
  white-space: nowrap;
}
.table-wrap tbody td {
  padding: 7px;
  border-bottom: 1px solid #eef1f5;
  border-right: 1px solid rgba(18, 35, 61, 0.05);
  white-space: nowrap;
  vertical-align: middle;
  font-weight: 500 !important;
  color: #475569 !important;
}
.table-wrap tbody td span,
.table-wrap tbody td input,
.table-wrap tbody td select {
  font-weight: 500 !important;
  color: #475569 !important;
}
.table-wrap tbody tr:hover {
  background: #f7fbfc;
}
tr.group-row td {
  background: #f0f4f8;
  font-weight: 700;
  color: var(--navy-2);
  padding: 8px 7px;
}
tr.status-row td {
  background: #e4eaf1;
  font-weight: 700;
  color: var(--navy);
  padding: 9px 7px;
  border-bottom: 1px solid var(--line);
}
.toggle-mini {
  width: 16px;
  height: 16px;
  border: 1px solid #c3cdd8;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: var(--ink-soft);
  background: #fff;
  cursor: pointer;
}
.page-footer {
  position: fixed;
  left: 16px;
  right: 16px;
  bottom: 14px;
  z-index: 30;
  background: #dde3ea;
  border: 1px solid var(--line);
  border-radius: 8px;
  box-shadow: 0 -6px 16px rgba(18, 35, 61, 0.14);
  overflow: hidden;
}
.footer-scroll {
  overflow: hidden;
}
table.footer-table {
  border-collapse: collapse;
  font-size: 12px;
}
table.footer-table td {
  padding: 10px 7px;
  font-weight: 700;
  color: var(--navy-2);
  border-right: 1px solid rgba(18, 35, 61, 0.06);
  white-space: nowrap;
}
table.footer-table td.f-total {
  text-align: right;
  color: var(--navy);
  font-size: 13px;
}
.stepper-mini {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 5px;
  overflow: hidden;
  background: #fff;
}
.stepper-mini span {
  width: 24px;
  text-align: center;
  font-size: 12px;
}
.stepper-mini button {
  width: 16px;
  border: none;
  background: #f5f7fa;
  font-size: 10px;
  cursor: pointer;
  color: var(--ink-soft);
  height: 22px;
}
.price-input {
  border: 1px solid var(--line);
  border-radius: 5px;
  padding: 5px 7px;
  font-size: 11.5px;
  color: var(--ink-soft);
  width: 140px;
  background: #fbfcfd;
}
.chip {
  padding: 4px 9px;
  border-radius: 5px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  cursor: pointer;
}
.chip.blue {
  background: var(--blue-bg);
  border-color: #bcd7f7;
  color: var(--blue);
}
.chip.teal {
  background: var(--teal-light);
  border-color: #bfe3e7;
  color: #0c6a77;
}
.switch {
  width: 30px;
  height: 16px;
  border-radius: 20px;
  background: var(--teal);
  position: relative;
  display: inline-block;
  cursor: pointer;
  vertical-align: middle;
}
.switch::after {
  content: "";
  position: absolute;
  top: 2px;
  right: 2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
}
.switch.off {
  background: #d7dde3;
}
.switch.off::after {
  left: 2px;
  right: auto;
}
.vacant {
  color: var(--green);
  font-weight: 700;
}
.stt {
  color: var(--ink-soft);
}
.guest {
  font-weight: 600;
}
.amount {
  font-weight: 700;
  color: var(--navy-2);
}

/* ---------- ACTION DOCK ---------- */
.dock {
  position: sticky;
  top: 14px;
  align-self: flex-start;
  margin: 14px 16px 90px 0;
  width: 52px;
  background: var(--navy);
  border-radius: 10px;
  overflow: hidden;
  transition: width 0.22s ease;
  z-index: 40;
  box-shadow: 0 4px 14px rgba(18, 35, 61, 0.18);
}
.dock:hover {
  width: 246px;
}
.dock-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  color: #fff;
  font-weight: 700;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  white-space: nowrap;
}
.dock-head .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--teal);
  flex: none;
}
.dock-group {
  padding: 5px 8px 1px 8px;
}
.dock-group-label {
  font-size: 9px;
  font-weight: 700;
  color: #7d93b3;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 2px 6px;
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.18s ease;
}
.dock:hover .dock-group-label {
  opacity: 1;
  transition-delay: 0.08s;
}
.dock-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 5px 8px;
  border-radius: 7px;
  cursor: pointer;
  color: #c7d2e0;
  white-space: nowrap;
  overflow: hidden;
}
.dock-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
}
.dock-item.danger:hover {
  background: rgba(193, 64, 63, 0.25);
  color: #ffb8b6;
}
.dock-item .di {
  width: 18px;
  height: 18px;
  flex: none;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dock-item .lbl {
  font-size: 12px;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.16s ease;
}
.dock:hover .dock-item .lbl {
  opacity: 1;
  transition-delay: 0.06s;
}
.dock-foot {
  padding: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  margin-top: 4px;
}
.dock-item.expandable {
  justify-content: space-between;
}
.chevron {
  margin-left: auto;
  font-size: 12px;
  color: #9db3d1;
  opacity: 0;
  transition: opacity 0.16s ease, transform 0.18s ease;
  flex: none;
}
.dock:hover .chevron {
  opacity: 1;
}
.chevron.open {
  transform: rotate(90deg);
}
.sub-current {
  opacity: 0;
  font-size: 10px;
  color: #8fa6c4;
  font-weight: 500;
  transition: opacity 0.16s ease;
  white-space: nowrap;
}
.dock:hover .sub-current {
  opacity: 1;
}
.sub-list {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.2s ease;
}
.sub-list.open {
  max-height: 120px;
}
.sub-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 8px 8px 34px;
  border-radius: 6px;
  color: #c7d2e0;
  font-size: 11.5px;
  cursor: pointer;
  white-space: nowrap;
}
.sub-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
}
.sub-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 1.5px solid #7d93b3;
  flex: none;
  position: relative;
}
.sub-item.selected .sub-dot {
  border-color: var(--teal);
}
.sub-item.selected .sub-dot::after {
  content: "";
  position: absolute;
  inset: 2px;
  border-radius: 50%;
  background: var(--teal);
}
.sub-item.selected {
  color: #fff;
}
.dock-invoice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: var(--teal);
  color: #fff;
  border-radius: 7px;
  padding: 10px 8px;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  font-weight: 700;
  font-size: 12px;
}
.dock-invoice:hover {
  background: #0c6a77;
}
.dock-invoice .lbl {
  opacity: 0;
  transition: opacity 0.16s ease;
}
.dock:hover .dock-invoice .lbl {
  opacity: 1;
  transition-delay: 0.06s;
}
</style>
