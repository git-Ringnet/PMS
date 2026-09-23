<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'
import {
  fetchRoomGuests,
  addRoomGuest,
  addBookingChild,
  fetchBookingChildren,
  updateBookingRoomGuest,
  updateBookingChild,
  removeRoomGuest,
  removeBookingChild,
  fetchRoomRateCodes,
  fetchSystemDate,
  uploadGuestAvatar,
  fetchNationalities,
  searchGuests,
  fetchBookingRoomSpecialRequests,
  syncBookingRoomSpecialRequests,
  fetchGuestDefinitions,
  fetchBookingRoomServices,
} from '@/services/booking-service'

import SpecialRequestsModal from '@/pages/reservation/components/SpecialRequestsModal.vue'
import ChildBreakfastModal from '@/pages/reservation/components/ChildBreakfastModal.vue'
import ExtraBedModal from '@/pages/reservation/components/ExtraBedModal.vue'
import TimePicker24h from '@/components/TimePicker24h.vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { resolveRateCodePrice } from '@/utils/rate-code-pricing.js'

const props = defineProps({
  room: { type: Object, required: true },
})
const emit = defineEmits(['close', 'refresh'])

const router = useRouter()
const route = useRoute()
const uiStore = useUiStore()

// ── Dropdowns Catalog ──────────────────────────────
const titlesList = ['Mr.', 'Ms.', 'Mrs.', 'Miss.', 'Kid.', 'Baby.', 'Dr.', 'Prof.']

const nationalitiesList = ref([])
const nationalitySearch = ref('')
const showNationalitySuggestions = ref(false)

const filteredNationalities = computed(() => {
  const q = (nationalitySearch.value || '').trim().toLowerCase()
  if (!q) return nationalitiesList.value.slice(0, 35)
  return nationalitiesList.value.filter(n =>
    n.code.toLowerCase().includes(q) || n.label.toLowerCase().includes(q)
  ).slice(0, 35)
})

function syncNationalityDisplay() {
  const code = formGuest.value.nationality
  if (!code) {
    nationalitySearch.value = ''
    return
  }
  const found = nationalitiesList.value.find(n => n.code.toUpperCase() === code.toUpperCase())
  nationalitySearch.value = found ? found.label : code
}

function onNationalityInput(e) {
  nationalitySearch.value = e.target.value
  showNationalitySuggestions.value = true
  const q = e.target.value.trim().toUpperCase()
  const match = nationalitiesList.value.find(n => n.code.toUpperCase() === q)
  if (match) {
    formGuest.value.nationality = match.code
  }
}

function onNationalityFocus() {
  if (!isEditingMode.value) return
  showNationalitySuggestions.value = true
}

function onNationalityBlur() {
  setTimeout(() => {
    showNationalitySuggestions.value = false
    syncNationalityDisplay()
  }, 200)
}

function selectNationality(n) {
  formGuest.value.nationality = n.code
  nationalitySearch.value = n.label
  showNationalitySuggestions.value = false
}

async function loadNationalities() {
  if (nationalitiesList.value.length > 0) {
    syncNationalityDisplay()
    return
  }
  try {
    const res = await fetchNationalities()
    if (res.data?.success) {
      const list = res.data.data || []
      nationalitiesList.value = list.map(item => ({
        code: item.asm_code || item.nationality_id || '',
        label: `${item.nationality_id || item.asm_code || '—'} - ${item.asm_name || item.nationality_name || ''}`
      })).filter(item => item.code !== '')
      syncNationalityDisplay()
    }
  } catch (err) {
    console.error('Lỗi tải danh sách quốc tịch:', err)
  }
}

// ── Residence Types Catalog (Thường trú / Tạm trú) ───
const residenceTypesList = ref([
  { id: 1, name: 'Địa chỉ thường trú', name_new_form: 'Thường trú' },
  { id: 2, name: 'Địa chỉ tạm trú', name_new_form: 'Tạm trú' },
  { id: 3, name: 'Địa chỉ khác', name_new_form: 'Khác' },
])

async function loadResidenceTypes() {
  try {
    const res = await fetchGuestDefinitions()
    if (res.data?.success && res.data.data?.residence_types?.length > 0) {
      residenceTypesList.value = res.data.data.residence_types
    }
  } catch (err) {
    console.error('loadResidenceTypes error:', err)
  }
}

// ── Booking Room Services & Extra Bed State ─────────
const roomServices = ref([])

async function loadRoomServices() {
  const brId = bookingRoomId.value
  if (!brId) return
  try {
    const res = await fetchBookingRoomServices(brId)
    if (res.data?.success) {
      roomServices.value = res.data.data || []
      const ebList = roomServices.value.filter(s => s.service_code === 'EB')
      if (ebList.length > 0) {
        const maxQty = Math.max(...ebList.map(s => Number(s.quantity) || 0))
        const activeEB = ebList.find(s => Number(s.rate) > 0)
        if (maxQty > 0) {
          pricingInfo.value.extra_bed_qty = maxQty
          if (activeEB) {
            pricingInfo.value.extra_bed_price = formatNumber(activeEB.rate)
          }
        }
      }
    }
  } catch (err) {
    console.error('loadRoomServices error:', err)
  }
}

// ── Data ──────────────────────────────────────────
const adults   = ref([])
const children = ref([])
const babies   = ref([])
const loading  = ref(false)
const submitting = ref(false)

// Active selection
const selectedGuest = ref(null)
const selectedChild = ref(null)

// Sub-modals state
const showSpecialRequestsModal = ref(false)
const showChildBreakfastModal  = ref(false)
const showExtraBedModal        = ref(false)

// Custom 24h TimePicker state
const showTimePicker = ref(false)
const timePickerRef  = ref(null)

// Edit mode state (Readonly / Vùng xám when false)
const isEditingMode = ref(false)
const backupFormGuest = ref(null)

// Draft guest state
const draftGuest = ref(null)

// Room special requests state (Section 2)
const roomSpecialRequests = ref([])

// Guest autocomplete suggestions state (Section 1)
const nameSuggestions = ref([])
const showNameSuggestions = ref(false)
const searchQueryName = ref('')
const idNumberSuggestions = ref([])
const showIdNumberSuggestions = ref(false)
const searchQueryIdNumber = ref('')
let searchNameTimeout = null
let searchIdTimeout = null

// Add-form inline states
const addingAdult  = ref(false)
const addingChild  = ref(false)
const addingBaby   = ref(false)
const newAdultName = ref('')
const newChildName = ref('')
const newBabyName  = ref('')

// Form fields state
const formGuest = ref({
  title: 'Mr.',
  name: '',
  nationality: 'VN',
  dob: '',
  email: '',
  phone: '',
  stay_count: 1,
  id_type: 'CCCD',
  id_number: '',
  passport_number: '',
  id_issue_date: '',
  residence_type: 'Thường trú',
  address: '',
  avatar: '',
})

const stayInfo = ref({
  arrival_date: '',
  arrival_time: '14:00',
  departure_date: '',
  departure_time: '12:00',
  nights: 1,
  occupants_str: '1 / 0 / 0',
  breakfast: true,
  hourly: false,
  notes: '',
})

const pricingInfo = ref({
  rate: '0',
  rate_code: '',
  discount_type: 'Tăng/Giảm giá',
  extra_bed: 'Không thêm',
  extra_bed_price: '0',
})

// ── Computed ───────────────────────────────────────
const bookingRoomId = computed(() => props.room.booking_room_id || props.room.id)
const bookingId     = computed(() => props.room.booking_id)

const rateCodes = ref([])
const systemDate = ref(new Date().toISOString().split('T')[0])

async function loadSystemDate() {
  try {
    const res = await fetchSystemDate()
    if (res.data && res.data.success && res.data.data) {
      systemDate.value = res.data.data.system_date
    }
  } catch (err) {
    console.error('loadSystemDate error:', err)
  }
}

async function loadRateCodes() {
  try {
    const res = await fetchRoomRateCodes()
    const list = res.data?.data || res.data || []
    rateCodes.value = list
  } catch (e) {
    console.error('Failed to load rate codes in BookingDetailModal', e)
  }
}

function onRateCodeChange(selectedValue = pricingInfo.value.rate_code) {
  pricingInfo.value.rate_code = String(selectedValue || '')
  const selectedCode = pricingInfo.value.rate_code
  if (!selectedCode) {
    const standardRate = Number(props.room.standard_rate || 0)
    const roomClassRate = Number(props.room.room_class?.room_price || 0)
    pricingInfo.value.rate = formatNumber(standardRate > 0 ? standardRate : roomClassRate)
    return
  }

  const found = rateCodes.value.find(rc => (rc.code || rc.Ma) === selectedCode)
  if (found) {
    const arrivalDate = formatDateForInput(props.room.arrival_date || props.room.arrivalDate)
    const date = systemDate.value && systemDate.value > arrivalDate ? systemDate.value : arrivalDate
    const newPrice = resolveRateCodePrice([found], {
      rateCode: selectedCode,
      date,
      roomClassId: props.room.room_class_id || props.room.roomClassId,
      roomClassCode: props.room.room_class?.code || props.room.room_type || props.room.room_type_code,
      roomForm: props.room.room_form?.name || props.room.room_class?.room_form_name || props.room.shape,
    })
    if (newPrice !== null) {
      pricingInfo.value.rate = formatNumber(newPrice)
    }
  }
}

async function onExtraBedSaved(data) {
  if (data) {
    if (data.quantity !== undefined) {
      pricingInfo.value.extra_bed_qty = data.quantity
    }
    if (data.rate !== undefined) {
      pricingInfo.value.extra_bed_price = formatNumber(data.rate)
    }
    if (bookingRoomId.value) {
      try {
        uiStore.showToast('Đang lưu thông tin Thêm giường...', 'info')
        if (bookingId.value) {
          await http.put(`/bookings/${bookingId.value}/rooms/${bookingRoomId.value}`, {
            extra_bed_qty: data.quantity,
            extra_bed_rate: data.rate
          })
        }
        if (data.dailyRates && data.dailyRates.length > 0) {
          for (const d of data.dailyRates) {
            if (d.isLocked || d.isPast) continue
            try {
              await http.post(`/booking-rooms/${bookingRoomId.value}/services`, {
                service_code: 'EB',
                service_name: 'Extra Bed',
                service_date: d.dateStr,
                quantity: d.quantity || 0,
                rate: d.rate || 0,
                is_room: d.isRoom ? 1 : 0
              })
            } catch (svcErr) {
              console.warn('Post EB service note:', svcErr.response?.data?.message || svcErr.message)
            }
          }
        }
        await loadRoomServices()
        pricingInfo.value.extra_bed_qty = data.quantity
        pricingInfo.value.extra_bed_price = formatNumber(data.rate)
        uiStore.showToast('Đã lưu thông tin Thêm giường thành công!', 'success')
        emit('refresh')
      } catch (err) {
        console.error('Lỗi khi lưu extra bed:', err)
        uiStore.showToast('Lỗi khi lưu thêm giường: ' + (err.response?.data?.message || err.message), 'error')
      }
    }
  }
}

async function loadRoomSpecialRequests() {
  // 1. Khởi tạo ngay từ props.room để hiển thị tức thì không bị giật
  if (Array.isArray(props.room?.special_request_types) && props.room.special_request_types.length > 0) {
    roomSpecialRequests.value = props.room.special_request_types.map((item, idx) => ({
      id: item.id || item.code || idx,
      code: item.code || '',
      name: item.name || item.code || '',
    }))
  } else if (typeof props.room?.special_requests === 'string' && props.room.special_requests.trim()) {
    roomSpecialRequests.value = props.room.special_requests.split(',')
      .map(s => s.trim())
      .filter(Boolean)
      .map((s, idx) => ({ id: idx, code: '', name: s }))
  }

  // 2. Tải dữ liệu mới nhất từ CSDL qua API
  const brId = bookingRoomId.value
  if (!brId) return

  try {
    const res = await fetchBookingRoomSpecialRequests(brId)
    if (res.data?.success) {
      const list = res.data.data || []
      if (list.length > 0) {
        roomSpecialRequests.value = list.map(item => {
          const sr = item.special_request || item.specialRequest || {}
          return {
            id: item.special_request_id || sr.id || item.id,
            code: sr.code || item.code || '',
            name: sr.name || item.name || sr.code || item.code || '',
          }
        }).filter(item => item.name || item.code)
      } else if (!Array.isArray(props.room?.special_request_types) || props.room.special_request_types.length === 0) {
        roomSpecialRequests.value = []
      }
    }
  } catch (err) {
    console.error('Failed to load room special requests', err)
  }
}

async function onSpecialRequestsSaved(data) {
  if (Array.isArray(data) && data.length > 0) {
    roomSpecialRequests.value = data.map(item => {
      const sr = item.special_request || item.specialRequest || {}
      return {
        id: item.special_request_id || sr.id || item.id,
        code: sr.code || item.code || '',
        name: sr.name || item.name || sr.code || item.code || '',
      }
    }).filter(item => item.name || item.code)
  }
  await loadRoomSpecialRequests()
  emit('refresh')
}

async function quickRemoveSpecialRequest(req) {
  if (!isEditingMode.value) return
  const remainingRequests = roomSpecialRequests.value.filter(r => String(r.id) !== String(req.id))
  roomSpecialRequests.value = remainingRequests

  if (bookingRoomId.value) {
    try {
      const remainingIds = remainingRequests.map(r => r.id).filter(Boolean)
      await syncBookingRoomSpecialRequests(bookingRoomId.value, {
        special_request_ids: remainingIds,
      })
      uiStore.showToast(`Đã gỡ yêu cầu: ${req.name || req.code}`, 'success')
      emit('refresh')
    } catch (e) {
      console.error('Quick remove special request error', e)
      await loadRoomSpecialRequests()
    }
  }
}

// ── Autocomplete Functions (Section 1) ─────────────────
function onNameInput(val) {
  if (!isEditingMode.value) return
  searchQueryName.value = val || ''
  clearTimeout(searchNameTimeout)
  if (!val || val.trim().length < 1) {
    nameSuggestions.value = []
    showNameSuggestions.value = false
    return
  }
  searchNameTimeout = setTimeout(async () => {
    try {
      const res = await searchGuests(val.trim())
      if (res.data?.success) {
        nameSuggestions.value = res.data.data || []
        showNameSuggestions.value = nameSuggestions.value.length > 0
      }
    } catch (e) {
      console.error('searchGuests error', e)
    }
  }, 250)
}

function onNameFocus() {
  if (!isEditingMode.value) return
  if (nameSuggestions.value.length > 0 && formGuest.value.name?.length >= 1) {
    showNameSuggestions.value = true
  }
}

function onIdNumberInput(val) {
  if (!isEditingMode.value) return
  searchQueryIdNumber.value = val || ''
  clearTimeout(searchIdTimeout)
  if (!val || val.trim().length < 1) {
    idNumberSuggestions.value = []
    showIdNumberSuggestions.value = false
    return
  }
  searchIdTimeout = setTimeout(async () => {
    try {
      const res = await searchGuests(val.trim())
      if (res.data?.success) {
        idNumberSuggestions.value = res.data.data || []
        showIdNumberSuggestions.value = idNumberSuggestions.value.length > 0
      }
    } catch (e) {
      console.error('searchGuests error', e)
    }
  }, 250)
}

function onIdNumberFocus() {
  if (!isEditingMode.value) return
  if (idNumberSuggestions.value.length > 0 && formGuest.value.id_number?.length >= 1) {
    showIdNumberSuggestions.value = true
  }
}

function selectSuggestion(guest) {
  if (!guest) return
  // Kế thừa thông tin khách cũ vào form hiện tại (không thay đổi ID slot khách của phòng)
  formGuest.value.name = guest.full_name ? guest.full_name.toUpperCase() : formGuest.value.name
  if (guest.title) formGuest.value.title = guest.title
  if (guest.dob) formGuest.value.dob = guest.dob
  if (guest.nationality_code) {
    formGuest.value.nationality = guest.nationality_code === 'VNM' ? 'VN' : guest.nationality_code
  }
  if (guest.phone) formGuest.value.phone = guest.phone
  if (guest.email) formGuest.value.email = guest.email
  if (guest.id_type) formGuest.value.id_type = guest.id_type
  if (guest.id_number) formGuest.value.id_number = guest.id_number
  if (guest.passport_number) formGuest.value.passport_number = guest.passport_number
  if (guest.id_issue_date) formGuest.value.id_issue_date = guest.id_issue_date
  if (guest.residence_type) formGuest.value.residence_type = guest.residence_type
  if (guest.address) formGuest.value.address = guest.address
  if (guest.avatar) formGuest.value.avatar = guest.avatar
  if (guest.stay_count) formGuest.value.stay_count = guest.stay_count

  syncNationalityDisplay()
  showNameSuggestions.value = false
  showIdNumberSuggestions.value = false
  uiStore.showToast(`Đã kế thừa thông tin khách "${guest.full_name}".`, 'info')
}

function highlightMatch(text, query) {
  if (!text || !query) return text || ''
  const q = String(query).trim()
  if (!q) return text
  const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const regex = new RegExp(`(${escaped})`, 'gi')
  return text.replace(regex, '<span class="text-orange-600 font-extrabold">$1</span>')
}

function highlightGuestSuggestion(g, query) {
  const parts = []
  parts.push(highlightMatch(g.full_name || 'Khách', query))
  if (g.dob) {
    const dParts = g.dob.split('-')
    if (dParts.length === 3) {
      parts.push(`${dParts[2]}/${dParts[1]}/${dParts[0]}`)
    } else {
      parts.push(g.dob)
    }
  }
  if (g.id_number) {
    parts.push(highlightMatch(g.id_number, query))
  }
  const bkCount = g.stay_count || 1
  parts.push(`${bkCount} BK`)
  if (g.total_revenue && g.total_revenue > 0) {
    parts.push(`${formatNumber(g.total_revenue)} VND`)
  }
  return parts.join(' - ')
}

const formattedRoomForModals = computed(() => ({
  ...props.room,
  bookingRoomId: bookingRoomId.value,
  booking_room_id: bookingRoomId.value,
  bookingId: bookingId.value,
  booking_id: bookingId.value,
  roomNumber: props.room.room_number || props.room.roomNumber || '',
  type: props.room.room_type_name || props.room.room_type || '',
  arrival_date: stayInfo.value.arrival_date || props.room.arrival_date,
  departure_date: stayInfo.value.departure_date || props.room.departure_date,
  arrivalDate: stayInfo.value.arrival_date || props.room.arrival_date,
  departureDate: stayInfo.value.departure_date || props.room.departure_date,
  extraBedQty: Number(pricingInfo.value.extra_bed_qty || 0),
  extraBedPrice: parseNumber(pricingInfo.value.extra_bed_price) || 300000,
  rate: props.room.rate,
  services: roomServices.value.length > 0 ? roomServices.value : (props.room.services || []),
  specialRequests: Array.isArray(props.room.specialRequests)
    ? props.room.specialRequests
    : (Array.isArray(props.room.special_requests) ? props.room.special_requests : []),
}))

// Custom 24h TimePicker Computed & Functions
const currentHour = computed(() => {
  if (!stayInfo.value.departure_time) return 12
  const parts = stayInfo.value.departure_time.split(':')
  return parseInt(parts[0], 10) || 0
})

const currentMinute = computed(() => {
  if (!stayInfo.value.departure_time) return 0
  const parts = stayInfo.value.departure_time.split(':')
  return parseInt(parts[1], 10) || 0
})

function selectHour(h) {
  const hh = String(h).padStart(2, '0')
  const mm = String(currentMinute.value).padStart(2, '0')
  stayInfo.value.departure_time = `${hh}:${mm}`
}

function selectMinute(m) {
  const hh = String(currentHour.value).padStart(2, '0')
  const mm = String(m).padStart(2, '0')
  stayInfo.value.departure_time = `${hh}:${mm}`
  showTimePicker.value = false
}

// Click outside to close dropdowns / 24h TimePicker (Use Capture Phase true to bypass @click.stop)
function handleGlobalClick(e) {
  if (!e.target.closest('.suggestion-container')) {
    showNameSuggestions.value = false
    showIdNumberSuggestions.value = false
  }
  if (!e.target.closest('.nationality-container')) {
    showNationalitySuggestions.value = false
  }
  if (!showTimePicker.value) return
  const el = timePickerRef.value || document.querySelector('.time-picker-rel')
  if (el && !el.contains(e.target)) {
    showTimePicker.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', handleGlobalClick, true)
  loadRateCodes()
  loadSystemDate()
  loadResidenceTypes()
  loadRoomServices()
})

onBeforeUnmount(() => {
  window.removeEventListener('click', handleGlobalClick, true)
})

// Helper: Calculate next incremental default name (Guest 1, Guest 2, Child 1...)
function getNextDefaultName(type) {
  let list = []
  let prefix = 'Guest'
  if (type === 'adult') {
    list = adults.value
    prefix = 'Guest'
  } else if (type === 'child') {
    list = children.value
    prefix = 'Child'
  } else if (type === 'baby') {
    list = babies.value
    prefix = 'Baby'
  }

  let maxNum = 0
  const regex = new RegExp(`^${prefix}\\s+(\\d+)$`, 'i')
  list.forEach(item => {
    const match = item.name.match(regex)
    if (match) {
      const num = parseInt(match[1], 10)
      if (num > maxNum) maxNum = num
    }
  })
  return `${prefix} ${maxNum + 1}`
}

// ── Fetch Guests ───────────────────────────────────
async function loadGuests(autoSelectId = null) {
  if (!bookingRoomId.value) return
  loading.value = true
  try {
    const [gRes, cRes] = await Promise.all([
      fetchRoomGuests(bookingRoomId.value),
      bookingId.value
        ? fetchBookingChildren(bookingId.value, { booking_room_id: bookingRoomId.value })
        : Promise.resolve({ data: { data: [] } }),
    ])

    const pivots = gRes.data?.data ?? []
    adults.value = pivots.map(p => ({
      id:            p.guest_id,
      name:          p.guest?.full_name ?? 'Khách',
      is_primary:    p.is_primary,
      pivot_id:      p.id,
      title:         p.guest?.title ?? 'Mr.',
      nationality:   p.guest?.nationality_code ?? 'VN',
      dob:           formatDateForInput(p.guest?.dob) || '',
      phone:         p.guest?.phone ?? '',
      email:         p.guest?.email ?? '',
      id_type:       p.guest?.id_type ?? 'CCCD',
      id_number:     p.guest?.id_number || p.guest?.passport_number || '',
      passport_number:p.guest?.passport_number || '',
      id_issue_date: formatDateForInput(p.guest?.id_issue_date) || '',
      residence_type:p.guest?.residence_type ?? 'Thường trú',
      address:       p.guest?.address ?? '',
      avatar:        p.guest?.avatar ?? '',
      stay_count:    p.guest?.booking_room_guests_count || 1,
      actual_checkout_time: p.actual_checkout_time ? formatTime24h(p.actual_checkout_time) : null,
      actual_arrival_time:  p.actual_arrival_time ? formatTime24h(p.actual_arrival_time) : null,
      actual_checkout_date: p.actual_checkout_date ? formatDateForInput(p.actual_checkout_date) : null,
      actual_arrival_date:  p.actual_arrival_date ? formatDateForInput(p.actual_arrival_date) : null,
    }))

    const rawChildren = cRes.data?.data ?? []
    const roomChildren = rawChildren.filter(c =>
      !c.booking_room_id || String(c.booking_room_id) === String(bookingRoomId.value)
    )

    children.value = roomChildren
      .filter(c => c.age_group === 'child')
      .map(c => ({
        id: c.id,
        name: c.full_name || 'Child',
        title: c.title || 'Mr.',
        dob: formatDateForInput(c.dob) || '',
        nationality: c.nationality_code || 'VN',
        age_group: 'child',
      }))

    babies.value = roomChildren
      .filter(c => c.age_group === 'baby')
      .map(c => ({
        id: c.id,
        name: c.full_name || 'Baby',
        title: c.title || 'Mr.',
        dob: formatDateForInput(c.dob) || '',
        nationality: c.nationality_code || 'VN',
        age_group: 'baby',
      }))

    // Dynamically update occupants count string
    stayInfo.value.occupants_str = `${adults.value.length} / ${children.value.length} / ${babies.value.length}`

    // Handle Selection
    if (autoSelectId) {
      const foundAdult = adults.value.find(a => String(a.id) === String(autoSelectId))
      if (foundAdult) {
        selectGuest(foundAdult)
      } else {
        const foundChild = [...children.value, ...babies.value].find(c => String(c.id) === String(autoSelectId))
        if (foundChild) selectChild(foundChild)
      }
    } else if (!selectedGuest.value && !selectedChild.value && adults.value.length) {
      selectGuest(adults.value.find(a => a.is_primary) ?? adults.value[0])
    }
  } catch (e) {
    console.error('loadGuests error', e)
  } finally {
    loading.value = false
  }
}

watch(() => props.room, (newRoom) => {
  if (newRoom) {
    stayInfo.value = {
      arrival_date: formatDateForInput(newRoom.arrival_date) || '',
      arrival_time: formatTime24h(newRoom.check_in_time) || '14:00',
      departure_date: formatDateForInput(newRoom.departure_date) || '',
      departure_time: formatTime24h(newRoom.check_out_time) || '12:00',
      nights: newRoom.nights || newRoom.ActutalNumOfDays || 1,
      occupants_str: `${adults.value.length} / ${children.value.length} / ${babies.value.length}`,
      breakfast: newRoom.breakfast !== false,
      hourly: newRoom.is_hourly || false,
      notes: newRoom.booking_note || '',
    }

    pricingInfo.value = {
      rate: formatNumber(newRoom.rate) || '0',
      rate_code: newRoom.rate_code || '',
      discount_type: 'Tăng/Giảm giá',
      extra_bed_qty: Number(newRoom.extra_bed_qty || 0),
      extra_bed_price: formatNumber(newRoom.extra_bed_rate || newRoom.extra_bed_price || 0),
    }
  }
  loadGuests()
  loadNationalities()
  loadResidenceTypes()
  loadRoomServices()
  loadRoomSpecialRequests()
}, { immediate: true })

watch(() => [stayInfo.value.arrival_date, stayInfo.value.departure_date], ([arr, dep]) => {
  if (arr && dep) {
    const dArr = new Date(arr)
    const dDep = new Date(dep)
    const diffTime = dDep.getTime() - dArr.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    stayInfo.value.nights = diffDays > 0 ? diffDays : 1
  }
})

const UNIT_EXTRA_BED_PRICE = 300000

function handleExtraBedQtyChange(delta) {
  let qty = Number(pricingInfo.value.extra_bed_qty || 0) + delta
  if (qty < 0) qty = 0
  pricingInfo.value.extra_bed_qty = qty
  if (qty === 0) {
    pricingInfo.value.extra_bed_price = '0'
  } else {
    pricingInfo.value.extra_bed_price = formatNumber(qty * UNIT_EXTRA_BED_PRICE)
  }
}

function onExtraBedQtyInput(e) {
  let qty = parseInt(e.target.value, 10)
  if (isNaN(qty) || qty < 0) qty = 0
  pricingInfo.value.extra_bed_qty = qty
  if (qty === 0) {
    pricingInfo.value.extra_bed_price = '0'
  } else {
    pricingInfo.value.extra_bed_price = formatNumber(qty * UNIT_EXTRA_BED_PRICE)
  }
}

function handleExtraBedPriceChange(delta) {
  let current = parseNumber(pricingInfo.value.extra_bed_price) || 0
  current += delta
  if (current < 0) current = 0
  pricingInfo.value.extra_bed_price = formatNumber(current)
}

function onExtraBedPriceInput(e) {
  let num = parseNumber(e.target.value) || 0
  pricingInfo.value.extra_bed_price = formatNumber(num)
}

function selectGuest(g) {
  if (draftGuest.value && g.id !== draftGuest.value.id) {
    adults.value = adults.value.filter(a => !a.isDraft)
    children.value = children.value.filter(c => !c.isDraft)
    babies.value = babies.value.filter(b => !b.isDraft)
    draftGuest.value = null
    isEditingMode.value = false
  }
  selectedGuest.value = g
  selectedChild.value = null
  showNameSuggestions.value = false
  showIdNumberSuggestions.value = false
  if (g) {
    formGuest.value = {
      title: g.title || 'Mr.',
      name: g.name ? g.name.toUpperCase() : '',
      nationality: g.nationality || 'VN',
      dob: formatDateForInput(g.dob) || '',
      email: g.email || '',
      phone: g.phone || '',
      stay_count: Number(g.stay_count || 1),
      id_type: g.id_type || 'CCCD',
      id_number: g.id_number || '',
      passport_number: g.passport_number || '',
      id_issue_date: formatDateForInput(g.id_issue_date) || '',
      residence_type: g.residence_type || 'Thường trú',
      address: g.address || '',
      avatar: g.avatar || '',
    }
    syncNationalityDisplay()
  }
}

function selectChild(c) {
  if (draftGuest.value && c.id !== draftGuest.value.id) {
    adults.value = adults.value.filter(a => !a.isDraft)
    children.value = children.value.filter(c => !c.isDraft)
    babies.value = babies.value.filter(b => !b.isDraft)
    draftGuest.value = null
    isEditingMode.value = false
  }
  selectedChild.value = c
  selectedGuest.value = null
  showNameSuggestions.value = false
  showIdNumberSuggestions.value = false
  formGuest.value = {
    title: c.title || 'Mr.',
    name: c.name ? c.name.toUpperCase() : '',
    nationality: c.nationality || 'VN',
    dob: formatDateForInput(c.dob) || '',
    email: '',
    phone: '',
    stay_count: 1,
    id_type: 'CCCD',
    id_number: '',
    passport_number: '',
    id_issue_date: '',
    residence_type: 'Thường trú',
    address: '',
    avatar: '',
  }
  syncNationalityDisplay()
}

function isPassportType(value) {
  const normalized = String(value || '').trim().toLowerCase()
  return normalized.includes('passport') || normalized.includes('hộ chiếu')
}

const avatarInput = ref(null)

function triggerAvatarUpload() {
  if (!isEditingMode.value) {
    isEditingMode.value = true
    backupFormGuest.value = JSON.parse(JSON.stringify(formGuest.value))
  }
  avatarInput.value?.click()
}

async function handleAvatarFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return

  if (!selectedGuest.value || !selectedGuest.value.id) {
    uiStore.showToast('Vui lòng chọn khách hàng để tải ảnh đại diện!', 'warning')
    return
  }

  const formData = new FormData()
  formData.append('avatar', file)

  try {
    uiStore.showToast('Đang tải ảnh lên...', 'info')
    const res = await uploadGuestAvatar(selectedGuest.value.id, formData)
    if (res.data?.success) {
      const avatarUrl = res.data.avatar
      formGuest.value.avatar = avatarUrl
      selectedGuest.value.avatar = avatarUrl
      uiStore.showToast('Tải ảnh đại diện thành công!', 'success')
      emit('refresh')
    }
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || err.message || 'Lỗi khi tải ảnh'
    uiStore.showToast('Lỗi khi tải ảnh: ' + msg, 'error')
  } finally {
    if (event.target) event.target.value = ''
  }
}

// ── Actions ────────────────────────────────────────
function doAddAdult() {
  if (draftGuest.value) {
    adults.value = adults.value.filter(a => !a.isDraft)
    children.value = children.value.filter(c => !c.isDraft)
    babies.value = babies.value.filter(b => !b.isDraft)
  }

  const nameToAdd = newAdultName.value.trim() || getNextDefaultName('adult')
  newAdultName.value = ''
  addingAdult.value = false

  const newDraft = {
    id: 'draft_adult_' + Date.now(),
    isDraft: true,
    type: 'adult',
    name: nameToAdd,
    title: 'Mr.',
    nationality: 'VN',
    dob: '',
    phone: '',
    email: '',
    stay_count: 1,
    id_type: 'CCCD',
    id_number: '',
    passport_number: '',
    id_issue_date: '',
    residence_type: 'Thường trú',
    address: '',
    avatar: '',
    is_primary: adults.value.length === 0,
  }
  draftGuest.value = newDraft
  adults.value.push(newDraft)
  selectGuest(newDraft)
  isEditingMode.value = true
  backupFormGuest.value = null
  uiStore.showToast(`Đang thêm người lớn "${nameToAdd}". Vui lòng kiểm tra thông tin và bấm Lưu.`, 'info')
}

function doAddChild(ageGroup) {
  if (draftGuest.value) {
    adults.value = adults.value.filter(a => !a.isDraft)
    children.value = children.value.filter(c => !c.isDraft)
    babies.value = babies.value.filter(b => !b.isDraft)
  }

  const isChild = ageGroup === 'child'
  const inputVal = isChild ? newChildName.value : newBabyName.value
  const nameToAdd = inputVal.trim() || getNextDefaultName(ageGroup)
  if (isChild) {
    newChildName.value = ''
    addingChild.value = false
  } else {
    newBabyName.value = ''
    addingBaby.value = false
  }

  const newDraft = {
    id: 'draft_' + ageGroup + '_' + Date.now(),
    isDraft: true,
    type: ageGroup,
    age_group: ageGroup,
    name: nameToAdd,
    title: 'Mr.',
    nationality: 'VN',
    dob: '',
    stay_count: 1,
    id_type: 'CCCD',
    id_number: '',
    passport_number: '',
    id_issue_date: '',
    residence_type: 'Thường trú',
    address: '',
    avatar: '',
  }
  draftGuest.value = newDraft
  if (isChild) {
    children.value.push(newDraft)
  } else {
    babies.value.push(newDraft)
  }
  selectChild(newDraft)
  isEditingMode.value = true
  backupFormGuest.value = null
  uiStore.showToast(`Đang thêm ${isChild ? 'trẻ em' : 'em bé'} "${nameToAdd}". Vui lòng kiểm tra thông tin và bấm Lưu.`, 'info')
}

const backupStayInfo = ref(null)
const backupPricingInfo = ref(null)

function startEditing() {
  isEditingMode.value = true
  backupFormGuest.value   = JSON.parse(JSON.stringify(formGuest.value))
  backupStayInfo.value    = JSON.parse(JSON.stringify(stayInfo.value))
  backupPricingInfo.value = JSON.parse(JSON.stringify(pricingInfo.value))
  uiStore.showToast('Vùng chỉnh sửa đã được mở. Vui lòng cập nhật thông tin và bấm Lưu.', 'info')
}

function cancelEditing() {
  if (draftGuest.value) {
    adults.value = adults.value.filter(a => !a.isDraft)
    children.value = children.value.filter(c => !c.isDraft)
    babies.value = babies.value.filter(b => !b.isDraft)
    draftGuest.value = null

    if (adults.value.length > 0) {
      selectGuest(adults.value.find(a => a.is_primary) || adults.value[0])
    } else if (children.value.length > 0) {
      selectChild(children.value[0])
    } else if (babies.value.length > 0) {
      selectChild(babies.value[0])
    }
    isEditingMode.value = false
    showTimePicker.value = false
    showNameSuggestions.value = false
    showIdNumberSuggestions.value = false
    uiStore.showToast('Đã hủy thêm khách mới.', 'info')
    return
  }

  if (backupFormGuest.value) {
    formGuest.value = JSON.parse(JSON.stringify(backupFormGuest.value))
  }
  if (backupStayInfo.value) {
    stayInfo.value = JSON.parse(JSON.stringify(backupStayInfo.value))
  }
  if (backupPricingInfo.value) {
    pricingInfo.value = JSON.parse(JSON.stringify(backupPricingInfo.value))
  }
  isEditingMode.value = false
  showTimePicker.value = false
  showNameSuggestions.value = false
  showIdNumberSuggestions.value = false
  uiStore.showToast('Đã hủy bỏ thay đổi.', 'info')
}

async function handleSave() {
  if (!isEditingMode.value) return

  const confirmed = await uiStore.confirm({
    title: draftGuest.value ? 'Xác nhận thêm khách mới' : 'Xác nhận lưu thông tin',
    message: draftGuest.value
      ? `Bạn có chắc chắn muốn lưu khách mới "${formGuest.value.name || 'Khách'}" vào phòng không?`
      : 'Bạn có chắc chắn muốn lưu các thay đổi thông tin khách hàng này không?',
    confirmText: 'Lưu ngay',
    cancelText: 'Hủy',
  })
  if (!confirmed) return

  submitting.value = true
  try {
    const validRateCode = pricingInfo.value.rate_code && pricingInfo.value.rate_code !== 'Vui lòng chọn giá phòng'
      ? pricingInfo.value.rate_code
      : null

    const roomFields = {
      arrival_date: stayInfo.value.arrival_date,
      arrival_time: stayInfo.value.arrival_time,
      departure_date: stayInfo.value.departure_date,
      departure_time: stayInfo.value.departure_time,
      rate: pricingInfo.value.rate ? Number(String(pricingInfo.value.rate).replace(/\D/g, '')) : 0,
      rate_code: validRateCode,
      extra_bed_qty: Number(pricingInfo.value.extra_bed_qty || 0),
      extra_bed_rate: pricingInfo.value.extra_bed_price ? Number(String(pricingInfo.value.extra_bed_price).replace(/\D/g, '')) : 0,
    }

    if (draftGuest.value) {
      let newId = null
      if (draftGuest.value.type === 'adult') {
        if (bookingRoomId.value) {
          const res = await addRoomGuest(bookingRoomId.value, {
            full_name: formGuest.value.name,
            title: formGuest.value.title,
            nationality_code: formGuest.value.nationality,
            dob: formGuest.value.dob,
            phone: formGuest.value.phone,
            email: formGuest.value.email,
            id_type: formGuest.value.id_type,
            id_number: formGuest.value.id_number,
            passport_number: isPassportType(formGuest.value.id_type)
              ? formGuest.value.id_number
              : formGuest.value.passport_number,
            id_issue_date: formGuest.value.id_issue_date,
            residence_type: formGuest.value.residence_type,
            address: formGuest.value.address,
            avatar: formGuest.value.avatar,
            ...roomFields,
          })
          newId = res.data?.data?.guest_id || res.data?.data?.id
        }
      } else {
        if (bookingId.value) {
          const res = await addBookingChild(bookingId.value, {
            booking_room_id: bookingRoomId.value,
            full_name: formGuest.value.name,
            title: formGuest.value.title,
            nationality_code: formGuest.value.nationality,
            dob: formGuest.value.dob,
            age_group: draftGuest.value.age_group,
            ...roomFields,
          })
          newId = res.data?.data?.id
        }
      }
      draftGuest.value = null
      isEditingMode.value = false
      showTimePicker.value = false
      showNameSuggestions.value = false
      showIdNumberSuggestions.value = false
      uiStore.showToast('Đã thêm và lưu thông tin khách thành công!', 'success')
      await loadGuests(newId)
      emit('refresh')
      return
    }

    if (selectedGuest.value && bookingRoomId.value) {
      await updateBookingRoomGuest(bookingRoomId.value, selectedGuest.value.id, {
        full_name: formGuest.value.name,
        title: formGuest.value.title,
        nationality_code: formGuest.value.nationality,
        dob: formGuest.value.dob,
        phone: formGuest.value.phone,
        email: formGuest.value.email,
        id_type: formGuest.value.id_type,
        id_number: formGuest.value.id_number,
        passport_number: isPassportType(formGuest.value.id_type)
          ? formGuest.value.id_number
          : formGuest.value.passport_number,
        id_issue_date: formGuest.value.id_issue_date,
        residence_type: formGuest.value.residence_type,
        address: formGuest.value.address,
        avatar: formGuest.value.avatar,
        ...roomFields,
      })
    } else if (selectedChild.value) {
      await updateBookingChild(selectedChild.value.id, {
        full_name: formGuest.value.name,
        title: formGuest.value.title,
        nationality_code: formGuest.value.nationality,
        dob: formGuest.value.dob,
        ...roomFields,
      })
    }
    isEditingMode.value = false
    showTimePicker.value = false
    showNameSuggestions.value = false
    showIdNumberSuggestions.value = false
    uiStore.showToast('Đã lưu thông tin khách thành công!', 'success')
    await loadGuests(selectedGuest.value?.id || selectedChild.value?.id)
    emit('refresh')
  } catch (e) {
    console.error('Lỗi khi lưu thông tin:', e)
    const errData = e.response?.data
    let msg = errData?.message || 'Đã xảy ra lỗi khi lưu thông tin.'
    if (errData?.errors) {
      const firstKey = Object.keys(errData.errors)[0]
      if (firstKey && errData.errors[firstKey]?.[0]) {
        msg = errData.errors[firstKey][0]
      }
    }
    uiStore.showToast(msg, 'error')
  } finally {
    submitting.value = false
  }
}

async function handleDeleteGuest() {
  if (selectedGuest.value?.isDraft || selectedChild.value?.isDraft) {
    cancelEditing()
    return
  }
  if (!selectedGuest.value && !selectedChild.value) {
    uiStore.showToast('Vui lòng chọn khách cần xóa!', 'warning')
    return
  }
  const targetName = selectedGuest.value ? selectedGuest.value.name : selectedChild.value.name
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa khách',
    message: `Bạn có chắc chắn muốn xóa khách "${targetName}" khỏi phòng này và CSDL không?`,
    confirmText: 'Xóa ngay',
    cancelText: 'Hủy',
  })
  if (!confirmed) return

  submitting.value = true
  try {
    if (selectedGuest.value) {
      const guestIdToDelete = selectedGuest.value.id
      if (bookingRoomId.value) {
        await removeRoomGuest(bookingRoomId.value, guestIdToDelete)
      }
    } else if (selectedChild.value) {
      const childIdToDelete = selectedChild.value.id
      if (bookingId.value) {
        await removeBookingChild(bookingId.value, childIdToDelete)
      }
    }
    selectedGuest.value = null
    selectedChild.value = null
    isEditingMode.value = false
    uiStore.showToast('Đã xóa khách thành công khỏi phòng và CSDL!', 'success')
    await loadGuests()
    emit('refresh')
  } catch (e) {
    const errorMsg = e.response?.data?.message || e.message || 'Lỗi khi xóa khách.'
    uiStore.showToast(errorMsg, 'error')
    await loadGuests()
  } finally {
    submitting.value = false
  }
}

function handleScan() {
  uiStore.showToast('Đã kết nối máy quét CCCD/VNeID!', 'success')
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) {
    showTimePicker.value = false
    emit('close')
  }
}

function formatDateForInput(d) {
  if (!d) return ''
  const str = String(d)
  if (str.includes('T')) {
    const parsedDate = new Date(str)
    if (!isNaN(parsedDate.getTime())) {
      const year = parsedDate.getTimezoneOffset() > 0 && str.endsWith('Z') 
        ? parsedDate.getFullYear() 
        : parsedDate.getFullYear()
      // Let's just use timezone-safe methods or get local values
      const month = String(parsedDate.getMonth() + 1).padStart(2, '0')
      const day = String(parsedDate.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  const datePart = str.split('T')[0]
  if (datePart.includes('/')) {
    const parts = datePart.split('/')
    if (parts.length === 3) {
      return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`
    }
  }
  return datePart
}

// 24H Format Helper (HH:mm)
function formatTime24h(t) {
  if (!t) return '12:00'
  const parts = String(t).split(':')
  if (parts.length >= 2) {
    const hh = parts[0].padStart(2, '0')
    const mm = parts[1].padStart(2, '0')
    return `${hh}:${mm}`
  }
  return t
}

function formatNumber(val) {
  if (!val) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}

function parseNumber(val) {
  if (!val) return 0
  const cleanStr = String(val).replace(/\D/g, '')
  return Number(cleanStr) || 0
}
</script>

<template>
  <Teleport to="body">
    <div class="modal-overlay" @click="handleOverlayClick">
      <div class="card" @click.stop>
        
        <!-- HEADER -->
        <div class="card-header">
          <div class="header-left">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>THÔNG TIN ĐẶT PHÒNG</span>
            <span class="badge-room">Mã: {{ room.booking_code || room.booking_id || '' }} &nbsp;·&nbsp; Phòng {{ room.room_number || '' }} — {{ room.room_type_name || room.room_type || '' }}</span>
          </div>

          <div class="header-actions">
            <!-- Xóa khách -->
            <button @click="handleDeleteGuest" class="btn-hd delete">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/>
              </svg>Xoá khách
            </button>

            <!-- Sửa / Quay lại -->
            <button v-if="!isEditingMode" @click="startEditing" class="btn-hd edit">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>Sửa
            </button>
            <button v-else @click="cancelEditing" class="btn-hd cancel">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
              </svg>Quay lại
            </button>

            <!-- Scan -->
            <button @click="handleScan" class="btn-hd scan">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
              </svg>Scan
            </button>

            <!-- Lưu (Enabled when editing) -->
            <button @click="handleSave" class="btn-hd save" :disabled="!isEditingMode" :class="{ 'disabled-btn': !isEditingMode }">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
              </svg>Lưu
            </button>
            <button @click="emit('close')" class="close-x-btn">&times;</button>
          </div>
        </div>

        <!-- BODY -->
        <div class="card-body">
          <div v-if="draftGuest" class="draft-alert-banner">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-amber-600 flex-shrink-0">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>Đang thêm khách mới (<strong>{{ formGuest.name || 'Khách' }}</strong>). Vui lòng kiểm tra/nhập thông tin, sau đó bấm <strong>Lưu</strong> trên thanh tiêu đề để lưu vào hệ thống, hoặc bấm <strong>Quay lại</strong> để hủy.</span>
          </div>

          <div class="main-grid" :class="{ 'readonly-mode': !isEditingMode }">

            <!-- Ô 1 (CỘT 1, TRẢI 3 HÀNG): DANH SÁCH KHÁCH -->
            <div class="cell-guests">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                </svg>
                DANH SÁCH KHÁCH
              </div>

              <!-- NGƯỜI LỚN -->
              <div class="g-group">
                <div class="g-group-label">NGƯỜI LỚN ({{ adults.length }})</div>
                <div
                  v-for="g in adults"
                  :key="g.id"
                  @click="selectGuest(g)"
                  class="g-item"
                  :class="{ active: selectedGuest?.id === g.id }"
                >
                  <span :class="g.is_primary ? 'icon-rep' : 'icon-sub'">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">
                    {{ g.name }}
                    <span v-if="g.isDraft" class="draft-badge">(Mới)</span>
                  </span>
                </div>

                <button v-if="!addingAdult" @click="doAddAdult" class="btn-add">+ Thêm người lớn</button>
              </div>

              <!-- TRẺ EM -->
              <div class="g-group">
                <div class="g-group-label">TRẺ EM ({{ children.length }})</div>
                <div
                  v-for="c in children"
                  :key="c.id"
                  @click="selectChild(c)"
                  class="g-item"
                  :class="{ active: selectedChild?.id === c.id }"
                >
                  <span class="icon-sub">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">
                    {{ c.name }}
                    <span v-if="c.isDraft" class="draft-badge">(Mới)</span>
                  </span>
                </div>

                <button v-if="!addingChild" @click="doAddChild('child')" class="btn-add">+ Thêm trẻ em</button>
              </div>

              <!-- EM BÉ -->
              <div class="g-group">
                <div class="g-group-label">EM BÉ ({{ babies.length }})</div>
                <div
                  v-for="b in babies"
                  :key="b.id"
                  @click="selectChild(b)"
                  class="g-item"
                  :class="{ active: selectedChild?.id === b.id }"
                >
                  <span class="icon-sub">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">
                    {{ b.name }}
                    <span v-if="b.isDraft" class="draft-badge">(Mới)</span>
                  </span>
                </div>

                <button v-if="!addingBaby" @click="doAddChild('baby')" class="btn-add">+ Thêm em bé</button>
              </div>
            </div>

            <!-- Ô 2 (HÀNG 1, CỘT 2-3): THÔNG TIN CÁ NHÂN -->
            <div class="cell-personal">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                THÔNG TIN CÁ NHÂN
              </div>

              <div class="avatar-block">
                <div class="avatar-col">
                  <div class="id-avatar overflow-hidden flex items-center justify-center bg-slate-100 rounded-lg cursor-pointer hover:bg-slate-200 transition-colors" @click="triggerAvatarUpload" title="Nhấp để tải/thay đổi ảnh">
                    <img v-if="formGuest.avatar" :src="formGuest.avatar.startsWith('/') ? formGuest.avatar : '/' + formGuest.avatar" class="w-full h-full object-cover" />
                    <svg v-else width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                  </div>
                  <div class="avatar-btns">
                    <input type="file" ref="avatarInput" @change="handleAvatarFileChange" accept="image/*" class="hidden" />
                    <button type="button" class="btn-xs cursor-pointer" @click="triggerAvatarUpload">
                      <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                      </svg>Ảnh
                    </button>
                  </div>
                </div>

                <div class="personal-fields-wrapper">
                  <!-- Row 1 -->
                  <div class="personal-grid">
                    <div class="f">
                      <label>Danh xưng</label>
                      <select v-model="formGuest.title" :disabled="!isEditingMode">
                        <option v-for="t in titlesList" :key="t" :value="t">{{ t }}</option>
                      </select>
                    </div>
                    <div class="f relative suggestion-container">
                      <label>Họ tên <span class="req">*</span></label>
                      <input 
                        type="text" 
                        v-model="formGuest.name" 
                        @input="onNameInput($event.target.value)"
                        @focus="onNameFocus"
                        :disabled="!isEditingMode" 
                        style="font-weight: 700; text-transform: uppercase;"
                        autocomplete="off"
                      >
                      <!-- Dropdown gợi ý Tên khách -->
                      <div 
                        v-if="showNameSuggestions && nameSuggestions.length > 0"
                        class="suggestion-dropdown"
                      >
                        <div 
                          v-for="sug in nameSuggestions" 
                          :key="sug.id"
                          @mousedown.prevent="selectSuggestion(sug)"
                          class="suggestion-item"
                          v-html="highlightGuestSuggestion(sug, searchQueryName)"
                        ></div>
                      </div>
                    </div>
                    <div class="f relative nationality-container suggestion-container">
                      <label>Quốc tịch</label>
                      <input 
                        type="text" 
                        :value="nationalitySearch" 
                        @input="onNationalityInput" 
                        @focus="onNationalityFocus" 
                        :disabled="!isEditingMode" 
                        placeholder="Nhập mã hoặc tên nước..." 
                        autocomplete="off"
                      />
                      <!-- Dropdown gợi ý Quốc tịch -->
                      <div 
                        v-if="showNationalitySuggestions && filteredNationalities.length > 0" 
                        class="suggestion-dropdown" 
                        style="width: 320px;"
                      >
                        <div 
                          v-for="n in filteredNationalities" 
                          :key="n.code" 
                          @mousedown.prevent="selectNationality(n)" 
                          class="suggestion-item"
                        >
                          {{ n.label }}
                        </div>
                      </div>
                    </div>
                    <div class="f">
                      <label>Sinh nhật</label>
                      <SingleDatePicker
                        v-model="formGuest.dob"
                        :disabled="!isEditingMode"
                        placeholder="dd/mm/yyyy"
                      />
                    </div>
                  </div>

                  <!-- Row 2 -->
                  <div class="personal-grid-2">
                    <div class="f">
                      <label>Email</label>
                      <input type="email" v-model="formGuest.email" :disabled="!isEditingMode" placeholder="name@example.com">
                    </div>
                    <div class="f">
                      <label>Điện thoại</label>
                      <input type="text" v-model="formGuest.phone" :disabled="!isEditingMode" placeholder="+84...">
                    </div>
                    <!-- SỐ LƯỢT LƯU TRÚ: ALWAYS DISABLED / XÁM -->
                    <div class="f">
                      <label>Số lượt lưu trú</label>
                      <input type="number" v-model.number="formGuest.stay_count" disabled class="always-gray" style="text-align: center;">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ô 3 (HÀNG 2, CỘT 2-3): GIẤY TỜ TÙY THÂN -->
            <div class="cell-docs">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                GIẤY TỜ TÙY THÂN
              </div>
              <div class="g docs-grid">
                <div class="f">
                  <label>Loại giấy tờ</label>
                  <select v-model="formGuest.id_type" :disabled="!isEditingMode">
                    <option value="CCCD">CCCD</option>
                    <option value="Passport - Hộ chiếu">Passport - Hộ chiếu</option>
                    <option value="CMND">CMND</option>
                    <option value="Giấy khai sinh">Giấy khai sinh</option>
                    <option value="Khác">Khác</option>
                  </select>
                </div>
                <div class="f relative suggestion-container">
                  <label>Số giấy tờ <span class="req">*</span></label>
                  <input 
                    type="text" 
                    v-model="formGuest.id_number" 
                    @input="onIdNumberInput($event.target.value)"
                    @focus="onIdNumberFocus"
                    :disabled="!isEditingMode"
                    autocomplete="off"
                  >
                  <!-- Dropdown gợi ý Số giấy tờ -->
                  <div 
                    v-if="showIdNumberSuggestions && idNumberSuggestions.length > 0"
                    class="suggestion-dropdown"
                  >
                    <div 
                      v-for="sug in idNumberSuggestions" 
                      :key="sug.id"
                      @mousedown.prevent="selectSuggestion(sug)"
                      class="suggestion-item"
                      v-html="highlightGuestSuggestion(sug, searchQueryIdNumber)"
                    ></div>
                  </div>
                </div>
                <div class="f">
                  <label>Ngày phát hành</label>
                  <SingleDatePicker
                    v-model="formGuest.id_issue_date"
                    :disabled="!isEditingMode"
                    placeholder="dd/mm/yyyy"
                  />
                </div>
                <div class="f">
                  <label>Thường trú / Tạm trú</label>
                  <select v-model="formGuest.residence_type" :disabled="!isEditingMode">
                    <option 
                      v-for="rt in residenceTypesList" 
                      :key="rt.id" 
                      :value="rt.name_new_form || rt.name || rt.id"
                    >
                      {{ rt.name_new_form || rt.name }}
                    </option>
                  </select>
                </div>
                <div class="f span-4">
                  <label>Địa chỉ</label>
                  <input type="text" v-model="formGuest.address" :disabled="!isEditingMode" placeholder="Số nhà, đường, phường/xã...">
                </div>
              </div>
            </div>

            <!-- Ô 4 (HÀNG 3, CỘT 2): THÔNG TIN LƯU TRÚ -->
            <div class="cell-stay">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                THÔNG TIN LƯU TRÚ
              </div>
              <div class="g stay-grid">
                <!-- NGÀY ĐẾN: ALWAYS DISABLED / XÁM -->
                <div class="f">
                  <label>Ngày đến <span class="req">*</span></label>
                  <SingleDatePicker
                    v-model="stayInfo.arrival_date"
                    disabled
                    placeholder="dd/mm/yyyy"
                  />
                </div>
                <!-- GIỜ ĐẾN (24H FORMAT HH:mm): ALWAYS DISABLED / XÁM -->
                <div class="f">
                  <label>Giờ đến</label>
                  <input type="text" v-model="stayInfo.arrival_time" disabled class="always-gray" placeholder="14:00" style="text-align: center;">
                </div>
                <!-- NGÀY ĐỊ -->
                <div class="f">
                  <label>Ngày đi <span class="req">*</span></label>
                  <SingleDatePicker
                    v-model="stayInfo.departure_date"
                    :min-date="stayInfo.arrival_date"
                    :disabled="!isEditingMode"
                    placeholder="dd/mm/yyyy"
                  />
                </div>
                <!-- GIỜ ĐỊ (CUSTOM 24H PICKER COMPONENT: 00 -> 23 HOURS) -->
                <div class="f">
                  <label>Giờ đi</label>
                  <TimePicker24h
                    v-model="stayInfo.departure_time"
                    default-time="12:00"
                    :disabled="!isEditingMode"
                    :drop-up="true"
                  />
                </div>

                <!-- ĐÊM: ALWAYS DISABLED / XÁM -->
                <div class="f">
                  <label>Đêm</label>
                  <input type="number" v-model.number="stayInfo.nights" disabled class="always-gray" style="text-align: center;">
                </div>
              </div>

              <div class="stay-row-2">
                <div class="f flex-shrink-0">
                  <label>N.lớn / T.em / E.bé</label>
                  <div class="occupant-box">
                    <input type="text" v-model="stayInfo.occupants_str" disabled style="width: 82px; text-align: center; font-weight: 700;">
                    <!-- ALWAYS ENABLED SUB-FEATURE BUTTON -->
                    <button class="btn-act" @click="showChildBreakfastModal = true" style="font-size: 12px; padding: 0 10px;">Chi tiết trẻ em</button>
                  </div>
                </div>
                <div class="checkbox-row">
                  <label class="cb"><input type="checkbox" v-model="stayInfo.breakfast" :disabled="!isEditingMode"> Ăn sáng</label>
                  <label class="cb"><input type="checkbox" v-model="stayInfo.hourly" :disabled="!isEditingMode"> Phòng theo giờ</label>
                </div>
              </div>
              <div class="f" style="margin-top: 10px;">
                <label>Ghi chú</label>
                <input type="text" v-model="stayInfo.notes" :disabled="!isEditingMode" placeholder="Ghi chú thêm cho lưu trú...">
              </div>
            </div>

            <!-- Ô 5 (HÀNG 3, CỘT 3): GIÁ PHÒNG & YÊU CẦU -->
            <div class="cell-price">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                </svg>
                GIÁ PHÒNG &amp; YÊU CẦU
              </div>
              <div class="g price-grid-1">
                <div class="f">
                  <label>Giá phòng <span class="req">*</span></label>
                  <input type="text" v-model="pricingInfo.rate" :disabled="!isEditingMode" style="font-weight: 700;">
                </div>
                <div class="f">
                  <label>Rate code</label>
                    <select v-model="pricingInfo.rate_code" :disabled="!isEditingMode" @change="onRateCodeChange($event.target.value)">
                    <option value="" disabled>-- Chọn Mã Giá --</option>
                    <option v-for="rc in rateCodes" :key="rc.id || rc.code || rc.Ma" :value="rc.code || rc.Ma">
                      {{ rc.code || rc.Ma }}{{ (rc.name || rc.Ten) ? ' - ' + (rc.name || rc.Ten) : '' }}
                    </option>
                  </select>
                </div>
                <div class="f">
                  <label>Khuyến mãi / Tăng giảm</label>
                  <select v-model="pricingInfo.discount_type" :disabled="!isEditingMode">
                    <option>Tăng/Giảm giá</option><option>Giảm 10%</option><option>Early Bird</option>
                  </select>
                </div>
                <div class="btn-wrapper">
                  <!-- ALWAYS ENABLED SUB-FEATURE BUTTON -->
                  <button class="btn-act" @click="showSpecialRequestsModal = true">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                      <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>Yêu cầu đặc biệt<span v-if="roomSpecialRequests.length > 0" class="ml-1 px-1.5 py-0.2 text-[10px] bg-white/20 text-white rounded-full font-bold">({{ roomSpecialRequests.length }})</span>
                  </button>
                </div>
              </div>

              <!-- DANH SÁCH YÊU CẦU ĐẶC BIỆT ĐÃ CHỌN -->
              <div v-if="roomSpecialRequests.length > 0" class="special-requests-tags">
                <span class="sr-label">
                  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-slate-400">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                  </svg>
                  Yêu cầu:
                </span>
                <span 
                  v-for="req in roomSpecialRequests" 
                  :key="req.id"
                  class="sr-badge"
                  :title="req.name || req.code"
                  @click="showSpecialRequestsModal = true"
                >
                  <span class="sr-dot"></span>
                  <span class="sr-text">{{ req.name || req.code }}</span>
                  <button
                    v-if="isEditingMode"
                    type="button"
                    @click.stop="quickRemoveSpecialRequest(req)"
                    class="sr-remove-btn"
                    title="Gỡ yêu cầu này"
                  >
                    &times;
                  </button>
                </span>
              </div>
              
              <div class="g price-grid-2">
                <!-- THÊM GIƯỜNG (SỐ LƯỢNG) -->
                <div class="f">
                  <label>Thêm giường</label>
                  <div class="relative flex items-center">
                    <input
                      type="number"
                      :value="pricingInfo.extra_bed_qty || 0"
                      :disabled="!isEditingMode"
                      min="0"
                      max="10"
                      @input="onExtraBedQtyInput"
                      class="w-full h-8 px-2.5 pr-6 font-semibold text-slate-800 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-sky-500 bg-white text-xs disabled:bg-slate-100 disabled:text-slate-500 shadow-2xs"
                    />
                    <div v-if="isEditingMode" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
                      <button type="button" @click="handleExtraBedQtyChange(1)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        ▲
                      </button>
                      <button type="button" @click="handleExtraBedQtyChange(-1)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        ▼
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- GIÁ THÊM GIƯỜNG (THÀNH TIỀN) -->
                <div class="f">
                  <label>Giá thêm giường</label>
                  <div class="relative flex items-center">
                    <input
                      type="text"
                      :value="pricingInfo.extra_bed_price"
                      :disabled="!isEditingMode"
                      @input="onExtraBedPriceInput"
                      class="w-full h-8 px-2.5 pr-6 font-semibold text-slate-800 border border-slate-300 rounded-md focus:outline-none focus:ring-1 focus:ring-sky-500 bg-white text-xs text-right disabled:bg-slate-100 disabled:text-slate-500 shadow-2xs"
                    />
                    <div v-if="isEditingMode" class="absolute right-1.5 flex flex-col justify-center gap-0.5 select-none">
                      <button type="button" @click="handleExtraBedPriceChange(50000)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        ▲
                      </button>
                      <button type="button" @click="handleExtraBedPriceChange(-50000)" class="hover:text-sky-600 text-slate-400 cursor-pointer p-0 text-[8px] leading-none border-none bg-transparent">
                        ▼
                      </button>
                    </div>
                  </div>
                </div>

                <div class="btn-wrapper">
                  <!-- ALWAYS ENABLED SUB-FEATURE BUTTON -->
                  <button class="btn-act" @click="showExtraBedModal = true">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>Chi tiết thêm giường
                  </button>
                </div>
                <div></div>
              </div>
            </div>

          </div><!-- /main-grid -->
        </div><!-- /card-body -->

      </div>
    </div>
  </Teleport>

  <!-- SUB-MODALS LINKED DIRECTLY TO ROOM DATA -->
  <SpecialRequestsModal
    v-model:show="showSpecialRequestsModal"
    :room="formattedRoomForModals"
    @saved="onSpecialRequestsSaved"
  />

  <ChildBreakfastModal
    v-model:show="showChildBreakfastModal"
    :room="formattedRoomForModals"
    :bookingId="bookingId"
    @saved="emit('refresh')"
  />

  <ExtraBedModal
    v-model:show="showExtraBedModal"
    :room="formattedRoomForModals"
    :system-date="systemDate"
    @saved="onExtraBedSaved"
  />
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  padding: 24px 16px;
  overflow-y: auto;
}

.card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
  width: 100%;
  max-width: 1440px;
  overflow: hidden;
  margin: auto;
  animation: modalIn 0.15s ease-out;
}

/* HEADER */
.card-header {
  background: #1a2e4a;
  color: #fff;
  padding: 14px 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15.5px;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.badge-room {
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 6px;
  padding: 3px 12px;
  font-size: 13.5px;
  font-weight: 500;
  color: #cfe8ff;
}
.header-actions {
  display: flex;
  gap: 9px;
  align-items: center;
}
.btn-hd {
  border-radius: 6px;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
  padding: 7px 15px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  border: none;
  transition: opacity 0.15s, background-color 0.15s;
}
.btn-hd:hover:not(:disabled) { opacity: 0.88; }
.btn-hd.save { background: #2563eb; }
.btn-hd.edit { background: #2563eb; }
.btn-hd.cancel { background: #475569; }
.btn-hd.delete { background: #dc2626; }
.btn-hd.scan { background: #0284c7; }
.btn-hd.disabled-btn {
  background: #94a3b8 !important;
  opacity: 0.6;
  cursor: not-allowed;
}

.close-x-btn {
  background: none;
  border: none;
  color: #9ca3af;
  font-size: 24px;
  cursor: pointer;
  margin-left: 10px;
  line-height: 1;
}
.close-x-btn:hover { color: #fff; }

/* BODY & MAIN GRID */
.card-body { padding: 18px 22px 20px; }

.main-grid {
  display: grid;
  grid-template-columns: 240px 1.2fr 1fr;
  grid-template-rows: auto auto auto;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  overflow: visible;
  gap: 0;
  background: #fff;
  transition: background-color 0.2s;
}

/* READONLY / VÙNG XÁM MODE FOR FORM INPUTS */
.main-grid.readonly-mode input:disabled,
.main-grid.readonly-mode select:disabled {
  background-color: #f1f5f9 !important;
  color: #64748b !important;
  border-color: #cbd5e1 !important;
  cursor: not-allowed !important;
}

/* ALWAYS GRAY FIELDS (THÁO CHỨC NĂNG EDIT HOÀN TOÀN) */
input.always-gray,
input.always-gray:disabled {
  background-color: #f1f5f9 !important;
  color: #64748b !important;
  border-color: #cbd5e1 !important;
  cursor: not-allowed !important;
}

/* CELLS */
.cell-guests {
  grid-column: 1;
  grid-row: 1 / 4;
  border-right: 1.5px solid #cbd5e1;
  padding: 14px 15px;
  background: #fff;
}
.cell-personal {
  grid-column: 2 / 4;
  grid-row: 1;
  border-bottom: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-docs {
  grid-column: 2 / 4;
  grid-row: 2;
  border-bottom: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-stay {
  grid-column: 2;
  grid-row: 3;
  border-right: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-price {
  grid-column: 3;
  grid-row: 3;
  padding: 14px 18px;
}

/* SECTION LABEL */
.sec-label {
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-bottom: 8px;
  margin-bottom: 12px;
  border-bottom: 1.5px solid #e2e8f0;
}

/* FIELD STYLING */
.f { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.f label {
  font-size: 11.5px;
  color: #475569;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.f label .req { color: #ef4444; }
.f input, .f select {
  border: 1.5px solid #cbd5e1;
  border-radius: 6px;
  padding: 5px 8px;
  font-size: 13px;
  color: #0f172a;
  background: #fff;
  height: 35px;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: border-color 0.15s, background-color 0.15s;
}
.f input:focus:not(:disabled), .f select:focus:not(:disabled) { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }

.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }

/* PERSONAL FIELDS FLEX/GRID WRAPPER */
.personal-fields-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 9px;
  min-width: 0;
}
.personal-grid {
  display: grid;
  grid-template-columns: 85px 1.5fr 1.6fr 145px;
  gap: 9px 12px;
}
.personal-grid-2 {
  display: grid;
  grid-template-columns: 1.5fr 1.2fr 110px;
  gap: 9px 12px;
}

/* CUSTOM 24H TIME PICKER STYLING */
.time-picker-rel {
  position: relative;
  width: 100%;
}
.time-input-box {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}
.clock-ic {
  position: absolute;
  right: 6px;
  font-size: 12px;
  cursor: pointer;
  user-select: none;
  opacity: 0.7;
}
.clock-ic:hover { opacity: 1; }

.time-picker-popover {
  position: absolute;
  bottom: calc(100% + 4px);
  left: 0;
  z-index: 9999;
  display: flex;
  background: #fff;
  border: 1.5px solid #2563eb;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
  width: 160px;
  height: 180px;
  overflow: hidden;
}
.tp-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
}
.tp-col:last-child { border-right: none; }
.tp-head {
  background: #2563eb;
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  text-align: center;
  padding: 4px 0;
  text-transform: uppercase;
}
.tp-list {
  flex: 1;
  overflow-y: auto;
}
.tp-item {
  padding: 4px 0;
  text-align: center;
  font-size: 12.5px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: background 0.1s;
}
.tp-item:hover { background: #eff6ff; color: #2563eb; }
.tp-item.active { background: #2563eb; color: #fff; }

/* GRID HELPERS */
.g { display: grid; gap: 9px 12px; }
.docs-grid { grid-template-columns: 1.2fr 1.2fr 140px 1fr; gap: 9px 12px; }
.stay-grid { grid-template-columns: 1.2fr 80px 1.2fr 80px 65px; gap: 9px 10px; margin-bottom: 10px; }
.price-grid-1 { grid-template-columns: 120px 95px 1fr auto; gap: 9px 10px; margin-bottom: 10px; align-items: end; }
.price-grid-2 { grid-template-columns: 120px 115px auto 1fr; gap: 9px 10px; align-items: end; }
.btn-wrapper { display: flex; align-items: flex-end; }

/* SIDEBAR GUESTS */
.g-group { margin-bottom: 12px; }
.g-group-label {
  font-size: 10.5px;
  font-weight: 700;
  color: #94a3b8;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.g-item {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
  border: 1.5px solid transparent;
  margin-bottom: 3px;
  transition: background 0.12s;
}
.g-item:hover { background: #f0f4ff; }
.g-item.active { background: #eff6ff; border-color: #93c5fd; }
.g-name { font-size: 13px; font-weight: 600; color: #1e293b; flex: 1; }
.icon-rep { color: #f59e0b; display: flex; align-items: center; flex-shrink: 0; }
.icon-sub { color: #cbd5e1; display: flex; align-items: center; flex-shrink: 0; }

.btn-add {
  width: 100%;
  border: 1.5px dashed #93c5fd;
  background: none;
  border-radius: 6px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  padding: 6px;
  cursor: pointer;
  margin-top: 4px;
  transition: background 0.12s;
}
.btn-add:hover { background: #eff6ff; }

/* AVATAR */
.avatar-block { display: flex; gap: 14px; align-items: flex-start; }
.avatar-col { display: flex; flex-direction: column; align-items: center; }
.id-avatar {
  width: 86px; height: 86px;
  border-radius: 8px;
  background: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.id-avatar svg { color: #94a3b8; }
.avatar-btns { display: flex; gap: 5px; margin-top: 6px; }
.btn-xs {
  font-size: 11.5px;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 5px;
  padding: 4px 10px;
  cursor: pointer;
  color: #334155;
  display: flex; align-items: center; gap: 4px;
  transition: background 0.12s;
}
.btn-xs:hover:not(:disabled) { background: #f1f5f9; }

/* BUTTON ACTION BLUE (ALWAYS BLUE & CLICKABLE) */
.btn-act {
  height: 35px;
  background: #2563eb !important;
  color: #fff !important;
  border: none !important;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 600;
  padding: 0 14px;
  cursor: pointer !important;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  flex-shrink: 0;
  opacity: 1 !important;
  transition: background-color 0.15s;
}
.btn-act:hover { background: #1d4ed8 !important; }

/* CHECKBOX & STAY ROW */
.stay-row-2 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.occupant-box { display: flex; gap: 8px; align-items: center; }
.checkbox-row { display: flex; gap: 14px; align-items: center; padding-top: 16px; }
.cb {
  display: flex; align-items: center; gap: 6px;
  font-size: 13.5px; font-weight: 600; color: #334155; cursor: pointer;
}
.cb input { accent-color: #2563eb; width: 15px; height: 15px; }

@keyframes modalIn {
  0% { opacity: 0; transform: scale(0.98); }
  100% { opacity: 1; transform: scale(1); }
}

.f :deep(.custom-single-datepicker div[class*="border"]) {
  height: 35px;
  border: 1.5px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
}
.f :deep(.custom-single-datepicker div[class*="bg-slate-100"]) {
  background-color: #f1f5f9 !important;
  color: #64748b !important;
  border-color: #cbd5e1 !important;
}

/* SECTION 1 & 2 EXTENSIONS */
.draft-badge {
  margin-left: 6px;
  padding: 1px 5px;
  font-size: 10px;
  font-weight: 700;
  color: #b45309;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 4px;
  display: inline-block;
}

.suggestion-container {
  position: relative;
}

.suggestion-dropdown {
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  width: 440px;
  max-width: 90vw;
  max-height: 220px;
  overflow-y: auto;
  background: #ffffff;
  border: 1px solid #94a3b8;
  border-radius: 6px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25);
  z-index: 9999;
}

.suggestion-item {
  padding: 6px 10px;
  font-size: 11.5px;
  font-weight: 600;
  color: #1e293b;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: background 0.12s, color 0.12s;
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item:hover {
  background-color: #38bdf8;
  color: #ffffff;
}

.suggestion-item:hover :deep(span) {
  color: #fef08a !important;
}

.special-requests-tags {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: -2px;
  margin-bottom: 9px;
  padding: 1px 0;
}

.sr-label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin-right: 2px;
}

.sr-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  font-size: 11.5px;
  font-weight: 500;
  color: #0369a1;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 9999px;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sr-badge:hover {
  background: #e0f2fe;
  border-color: #7dd3fc;
  color: #0284c7;
}

.sr-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background-color: #0284c7;
  display: inline-block;
  flex-shrink: 0;
}

.sr-text {
  line-height: 1.2;
}

.sr-remove-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  height: 14px;
  margin-left: 2px;
  border-radius: 50%;
  font-size: 13px;
  line-height: 1;
  color: #0284c7;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: all 0.12s;
}

.sr-remove-btn:hover {
  background: #0284c7;
  color: #ffffff;
}

.draft-alert-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fef3c7;
  border: 1px solid #fde68a;
  color: #92400e;
  font-size: 13px;
  font-weight: 500;
  padding: 8px 14px;
  border-radius: 6px;
  margin-bottom: 12px;
}
</style>
