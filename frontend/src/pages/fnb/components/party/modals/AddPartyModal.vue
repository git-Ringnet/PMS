<script setup>
import { ref, computed, watch } from 'vue'
import AddSubPartyModal from './AddSubPartyModal.vue'
import { fetchCompanies, fetchUsers, fetchBookers, createBooker } from '@/services/company-service'
import { getParty, cancelParty, completeSubParty } from '@/services/fb-party-service'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  partyId: { type: [Number, String], default: null }
})
const emit = defineEmits(['close', 'save', 'refresh'])

// Form state
const form = ref({
  id: null,
  partyCode: '',
  partyName: '',
  arrivalDate: '',
  confirmationType: 'byDate',
  confirmationDate: '',
  saleStaff: '',
  totalDeposit: 0,
  groupCode: '',
  company: 'KHÁCH LẺ',
  customer: '',
  email: '',
  note: '',
  vatNote: ''
})

const subParties = ref([])
const isAddSubPartyOpen = ref(false)

// Initial DB data lists
const companyList = ref([])
const saleUsers = ref([])
const bookersList = ref([])
const filteredBookers = ref([])
const showBookerSuggestions = ref(false)
const todayDate = ref('')
const confirmationDays = ref(1)

// Quick Booker modal state
const isAddBookerOpen = ref(false)
const newBooker = ref({ name: '', phone: '', email: '' })

const fetchInitialData = async () => {
  try {
    // 1. Get system time
    const timeRes = await http.get('/system-time')
    todayDate.value = timeRes.data?.time 
      ? timeRes.data.time.split('T')[0] 
      : new Date().toISOString().split('T')[0]
      
    // Set default arrivalDate to today if empty
    if (!form.value.arrivalDate) {
      form.value.arrivalDate = todayDate.value
    }

    // 2. Load users
    const usersRes = await fetchUsers()
    saleUsers.value = usersRes.data?.data || []

    // 3. Load companies
    const compRes = await fetchCompanies()
    companyList.value = compRes.data?.data || []

    // 4. Load bookers
    const bookRes = await fetchBookers()
    bookersList.value = bookRes.data?.data || []
  } catch (err) {
    console.error('Lỗi tải dữ liệu khởi tạo:', err)
  }
}

const isLoading = ref(false)
const initialState = ref(null)
const showUnsavedWarning = ref(false)

const loadPartyDetails = async (id) => {
  isLoading.value = true
  try {
    const res = await getParty(id)
    const party = res.data?.data
    if (party) {
      form.value = {
        id: party.id,
        partyCode: party.partyCode,
        partyName: party.partyName,
        arrivalDate: party.arrivalDate,
        confirmationType: party.confirmationType || 'byDate',
        confirmationDate: party.confirmationDate || '',
        saleStaff: party.saleStaff || '',
        totalDeposit: party.depositAmount || 0,
        groupCode: party.groupCode || '',
        company: party.company || 'KHÁCH LẺ',
        customer: party.customer || '',
        email: party.email || '',
        note: party.note || '',
        vatNote: party.vatNote || '',
        status: party.status || 'confirmed'
      }
      subParties.value = (party.subParties || []).map(sub => ({
        id: sub.id,
        bookingCode: sub.bookingCode,
        arrivalDate: sub.arrivalDate,
        arrivalTime: sub.arrivalTime,
        departureTime: sub.departureTime,
        adults: sub.adults,
        children: sub.children,
        tables: sub.tables,
        extra: sub.extra,
        outlet_id: sub.outlet_id,
        outlet: sub.outlet_id || sub.outlet,
        outletName: sub.outlet,
        location: sub.location,
        partyType: sub.partyType,
        groupCode: sub.groupCode,
        note: sub.note,
        deposits: sub.deposits || [],
        menuItems: sub.menuItems || [],
        status: sub.status || 'confirmed'
      }))
      saveInitialState()
    }
  } catch (err) {
    console.error('Lỗi tải chi tiết tiệc:', err)
  } finally {
    isLoading.value = false
  }
}

const saveInitialState = () => {
  initialState.value = {
    form: JSON.parse(JSON.stringify(form.value)),
    subParties: JSON.parse(JSON.stringify(subParties.value))
  }
}

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    isLoading.value = true
    form.value = {
      id: null,
      partyCode: '',
      partyName: '',
      arrivalDate: '',
      confirmationType: 'byDate',
      confirmationDate: '',
      saleStaff: '',
      totalDeposit: 0,
      groupCode: '',
      company: 'KHÁCH LẺ',
      customer: '',
      email: '',
      note: '',
      vatNote: '',
      status: 'confirmed'
    }
    subParties.value = []
    fetchInitialData().then(() => {
      if (props.partyId) {
        loadPartyDetails(props.partyId)
      } else {
        saveInitialState()
        isLoading.value = false
      }
    }).catch(() => {
      isLoading.value = false
    })
  }
})

watch(() => props.partyId, (newVal) => {
  if (props.isOpen && newVal) {
    loadPartyDetails(newVal)
  }
})

// Max confirmation date calculation (must be at least 1 day before arrival date)
const maxConfirmationDate = computed(() => {
  if (!form.value.arrivalDate) return null
  const arrDate = new Date(form.value.arrivalDate)
  arrDate.setDate(arrDate.getDate() - 1)
  try {
    return arrDate.toISOString().split('T')[0]
  } catch (e) {
    return null
  }
})

// Autocomplete bookers methods
const handleBookerInput = () => {
  const keyword = form.value.customer.trim().toLowerCase()
  if (!keyword) {
    filteredBookers.value = bookersList.value
  } else {
    filteredBookers.value = bookersList.value.filter(b => 
      (b.name && b.name.toLowerCase().includes(keyword)) ||
      (b.phone && b.phone.toLowerCase().includes(keyword))
    )
  }
  showBookerSuggestions.value = true
}

const selectBooker = (b) => {
  form.value.customer = b.name
  form.value.email = b.email || ''
  showBookerSuggestions.value = false
}

const handleBookerBlur = () => {
  setTimeout(() => {
    showBookerSuggestions.value = false
  }, 200)
}

// Add new booker quickly
const saveNewBooker = async () => {
  if (!newBooker.value.name || !newBooker.value.phone) {
    uiStore.alert('Vui lòng điền tên và số điện thoại!')
    return
  }
  try {
    const res = await createBooker(newBooker.value)
    const created = res.data?.data || res.data
    bookersList.value.push(created)
    form.value.customer = created.name
    form.value.email = created.email || ''
    isAddBookerOpen.value = false
    newBooker.value = { name: '', phone: '', email: '' }
  } catch (err) {
    console.error('Lỗi tạo khách hàng:', err)
    uiStore.alert('Lỗi: ' + (err.response?.data?.message || err.message))
  }
}

// Watch confirmationType to automatically compute confirmationDate if beforeN
watch([() => form.value.arrivalDate, () => form.value.confirmationType, confirmationDays], () => {
  if (form.value.confirmationType === 'beforeN') {
    if (form.value.arrivalDate) {
      const arrDate = new Date(form.value.arrivalDate)
      arrDate.setDate(arrDate.getDate() - Number(confirmationDays.value || 0))
      try {
        form.value.confirmationDate = arrDate.toISOString().split('T')[0]
      } catch (e) {
        form.value.confirmationDate = ''
      }
    }
  }
})

const selectedSubPartyIdx = ref(-1)
const selectedSubPartyData = ref(null)

const addSubParty = () => {
  if (!form.value.customer || form.value.customer.trim() === '') {
    uiStore.alert('Vui lòng nhập/chọn Khách hàng ở tiệc chính trước khi thêm tiệc con.')
    return
  }
  selectedSubPartyIdx.value = -1
  selectedSubPartyData.value = null
  isAddSubPartyOpen.value = true
}

const editSubParty = (idx) => {
  selectedSubPartyIdx.value = idx
  selectedSubPartyData.value = JSON.parse(JSON.stringify(subParties.value[idx]))
  isAddSubPartyOpen.value = true
}

const handleSubPartySave = (data) => {
  if (selectedSubPartyIdx.value !== -1) {
    subParties.value[selectedSubPartyIdx.value] = {
      ...subParties.value[selectedSubPartyIdx.value],
      ...data
    }
  } else {
    subParties.value.push({ id: Date.now(), ...data })
  }
  isAddSubPartyOpen.value = false
}

const stats = computed(() => ({
  totalAmount: subParties.value.reduce((s, p) => {
    const subTotal = (p.menuItems || []).reduce((sum, item) => sum + (item.price * item.quantity), 0)
    return s + subTotal
  }, 0),
  totalDeposit: subParties.value.reduce((s, p) => {
    const subDep = (p.deposits || []).reduce((sum, dep) => {
      if (dep.status === 'cancelled') return sum
      return sum + Number(dep.amount || 0)
    }, 0)
    return s + subDep
  }, 0),
  subCount: subParties.value.length,
  totalGuests: subParties.value.reduce((s, p) => s + ((p.adults || 0) + (p.children || 0)), 0),
  totalTables: subParties.value.reduce((s, p) => s + (p.tables || 0), 0)
}))

// Override totalDeposit from subParties deposits stats
watch(() => stats.value.totalDeposit, (newVal) => {
  form.value.totalDeposit = newVal
})

const formatCurrency = (val) => Number(val || 0).toLocaleString('vi-VN') + ' đ'
const getSubPartyTotal = (sub) => {
  return (sub.menuItems || []).reduce((total, item) => total + (item.price * item.quantity), 0)
}
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`
  return dateStr
}

const getStatusLabelAndClass = (status) => {
  switch (status) {
    case 'confirmed':
      return { text: 'Xác nhận', class: 'bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-bold shadow-xs' }
    case 'completed':
      return { text: 'Hoàn thành', class: 'bg-sky-50 text-sky-600 border border-sky-200 px-2 py-0.5 rounded text-[10px] font-bold shadow-xs' }
    case 'cancelled':
      return { text: 'Hủy', class: 'bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded text-[10px] font-bold shadow-xs' }
    default:
      return { text: 'Xác nhận', class: 'bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded text-[10px] font-bold shadow-xs' }
  }
}

const getSubPartyDynamicStatusInfo = (sub) => {
  if (sub.status === 'completed') {
    return { text: 'Hoàn thành', class: 'text-sky-600 bg-sky-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-sky-300' }
  }
  const arrDateStr = sub.arrivalDate || form.value.arrivalDate
  if (!arrDateStr) return { text: 'Xác nhận', class: 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-emerald-300' }

  const parts = arrDateStr.split('-')
  if (parts.length !== 3) return { text: 'Xác nhận', class: 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-emerald-300' }
  
  const startTime = new Date(`${arrDateStr}T${sub.arrivalTime || '00:00'}:00`).getTime()
  const endTime = new Date(`${arrDateStr}T${sub.departureTime || '23:59'}:00`).getTime()
  const now = new Date().getTime()

  if (now > endTime) {
    return { text: 'Hoàn thành', class: 'text-sky-600 bg-sky-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-sky-300' }
  } else if (now >= startTime && now <= endTime) {
    return { text: 'Đang phục vụ', class: 'text-amber-600 bg-amber-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-amber-400' }
  }
  return { text: 'Xác nhận', class: 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-extrabold text-[10px]', borderClass: 'border-emerald-300' }
}

const viewConfirmationSlip = () => {
  uiStore.alert('Đang hiển thị phiếu xác nhận của đặt tiệc ' + (form.value.partyCode || ''))
}
const printConfirmationSlip = () => {
  uiStore.alert('Đang in phiếu xác nhận của đặt tiệc ' + (form.value.partyCode || ''))
}
const handleCancelParty = async () => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận hủy tiệc',
    message: 'Bạn có chắc chắn muốn hủy đặt tiệc này không?'
  })
  if (!confirmed) return
  try {
    const res = await cancelParty(form.value.id)
    uiStore.alert(res.data?.message || 'Đã huỷ tiệc thành công.')
    emit('refresh')
    emit('close')
  } catch (err) {
    uiStore.alert('Lỗi hủy tiệc: ' + (err.response?.data?.message || err.message))
  }
}

const handleCompleteSubParty = async (subId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận hoàn thành',
    message: 'Bạn xác nhận tiệc con này đã hoàn thành?'
  })
  if (!confirmed) return
  try {
    const res = await completeSubParty(form.value.id, subId)
    uiStore.alert(res.data?.message || 'Đã hoàn thành tiệc con.')
    if (res.data?.partyCompleted) {
      emit('refresh')
      emit('close')
    } else {
      loadPartyDetails(form.value.id)
      emit('refresh')
    }
  } catch (err) {
    uiStore.alert('Lỗi hoàn thành: ' + (err.response?.data?.message || err.message))
  }
}

const isDirty = computed(() => {
  if (!initialState.value) return false
  const formChanged = JSON.stringify(form.value) !== JSON.stringify(initialState.value.form)
  const subChanged = JSON.stringify(subParties.value) !== JSON.stringify(initialState.value.subParties)
  return formChanged || subChanged
})

const handleSave = () => {
  if (!form.value.saleStaff) {
    uiStore.alert('Vui lòng chọn Nhân viên sale.')
    return
  }
  if (!form.value.customer) {
    uiStore.alert('Vui lòng nhập Khách hàng.')
    return
  }
  if (form.value.confirmationType === 'byDate' && !form.value.confirmationDate) {
    uiStore.alert('Vui lòng chọn Ngày xác nhận.')
    return
  }
  if (form.value.confirmationType === 'beforeN' && !confirmationDays.value) {
    uiStore.alert('Vui lòng nhập số ngày xác nhận trước.')
    return
  }

  // Update confirmationDate based on beforeN if needed
  if (form.value.confirmationType === 'beforeN') {
    if (form.value.arrivalDate && confirmationDays.value) {
      const arrDate = new Date(form.value.arrivalDate)
      arrDate.setDate(arrDate.getDate() - confirmationDays.value)
      try {
        form.value.confirmationDate = arrDate.toISOString().split('T')[0]
      } catch (e) {
        form.value.confirmationDate = ''
      }
    }
  }

  emit('save', { ...form.value, subParties: subParties.value })
  emit('close')
}

const handleClose = () => {
  if (isDirty.value) {
    showUnsavedWarning.value = true
  } else {
    emit('close')
  }
}

const confirmCloseWithoutSaving = () => {
  showUnsavedWarning.value = false
  emit('close')
}

defineExpose({
  isDirty
})
</script>

<template>
  <!-- Full-screen overlay form -->
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[45] bg-slate-100 flex flex-col overflow-hidden animate-in slide-in-from-bottom duration-150 relative"
  >
    <!-- Loading overlay (PMS Style spinner) -->
    <div v-if="isLoading" class="absolute inset-0 z-[80] flex items-center justify-center bg-white/60 backdrop-blur-xs">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- ===== TOP HEADER ===== -->
    <div class="flex items-center justify-between px-5 py-3 bg-white border-b border-slate-200 shrink-0 shadow-sm">
      <div class="flex items-center gap-3">
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors p-1.5 rounded-lg hover:bg-slate-100 cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <span class="text-sm font-extrabold text-slate-800">
          {{ form.id ? `Chỉnh sửa đặt tiệc: ${form.partyCode || ''}` : 'Tạo đặt tiệc mới' }}
        </span>
        <span 
          v-if="form.id" 
          :class="getStatusLabelAndClass(form.status).class"
        >
          {{ getStatusLabelAndClass(form.status).text }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <button
          v-if="form.id"
          @click="viewConfirmationSlip"
          class="border border-slate-200 hover:bg-slate-150 text-slate-600 text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-all cursor-pointer bg-white"
        >
          Xem phiếu xác nhận
        </button>
        <button
          v-if="form.id"
          @click="printConfirmationSlip"
          class="border border-slate-200 hover:bg-slate-150 text-slate-600 text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-all cursor-pointer bg-white"
        >
          In phiếu xác nhận
        </button>
        <button
          v-if="form.id && form.status !== 'cancelled' && stats.totalDeposit === 0"
          @click="handleCancelParty"
          class="border border-rose-200 hover:bg-rose-50 text-rose-600 hover:text-rose-700 text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition-all cursor-pointer bg-white"
        >
          Hủy tiệc
        </button>
        <button
          v-else-if="form.id && form.status !== 'cancelled' && stats.totalDeposit > 0"
          title="Không thể hủy tiệc đã có tiền cọc"
          disabled
          class="border border-slate-200 text-slate-400 text-xs font-bold px-4 py-2 rounded-lg shadow-sm cursor-not-allowed bg-slate-50"
        >
          Hủy tiệc
        </button>
        <button
          @click="handleSave"
          class="bg-sky-500 hover:bg-sky-600 text-white text-xs font-extrabold px-5 py-2 rounded-lg shadow-sm transition-all cursor-pointer"
        >
          Lưu
        </button>
      </div>
    </div>

    <!-- ===== SCROLLABLE BODY ===== -->
    <div class="flex-1 overflow-y-auto px-6 py-5">
      <div class="max-w-4xl mx-auto space-y-5">

        <!-- ===== THÔNG TIN CHUNG ===== -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 bg-slate-50">
            <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Thông tin chung</span>
          </div>

          <div class="p-5 space-y-4">
            <!-- Stats row -->
            <div class="flex gap-3">
              <div class="flex-1 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Tổng tiền tiệc</div>
                <div class="text-sm font-extrabold text-slate-800">{{ formatCurrency(stats.totalAmount) }}</div>
              </div>
              <div class="flex-1 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Tổng cọc</div>
                <div class="text-sm font-extrabold text-slate-800">{{ formatCurrency(stats.totalDeposit) }}</div>
              </div>
              <div class="flex-1 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Số tiệc con</div>
                <div class="text-sm font-extrabold text-slate-800">{{ stats.subCount }}</div>
              </div>
              <div class="flex-1 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Tổng khách</div>
                <div class="text-sm font-extrabold text-slate-800">{{ stats.totalGuests }}</div>
              </div>
              <div class="flex-1 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-semibold mb-0.5">Tổng bàn</div>
                <div class="text-sm font-extrabold text-slate-800">{{ stats.totalTables }}</div>
              </div>
            </div>

            <!-- Row 1: Mã / Tên / Ngày đến -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Mã đặt tiệc</label>
                <input
                  v-model="form.partyCode"
                  disabled
                  placeholder="Tự động tạo"
                  class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-400 bg-slate-50 cursor-not-allowed"
                />
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-700 mb-1">Tên đặt tiệc <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.partyName"
                  placeholder=""
                  class="w-full px-3 py-2 border border-amber-300 rounded-lg text-xs focus:outline-none focus:border-sky-500 bg-amber-50"
                />
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-700 mb-1">Ngày đến <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input
                    type="date"
                    v-model="form.arrivalDate"
                    :min="todayDate"
                    :disabled="['completed', 'serving'].includes(form.status)"
                    class="w-full px-3 py-2 border border-amber-300 rounded-lg text-xs focus:outline-none focus:border-sky-500 bg-amber-50 font-semibold"
                    :class="{'opacity-60 cursor-not-allowed': ['completed', 'serving'].includes(form.status)}"
                  />
                </div>
              </div>
            </div>

            <!-- Row 2: Hình thức xác nhận / Ngày xác nhận -->
            <div class="grid grid-cols-3 gap-4 items-end">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1.5">Hình thức xác nhận</label>
                <div class="flex items-center gap-4 text-xs font-semibold text-slate-700 h-[38px]">
                  <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" v-model="form.confirmationType" value="byDate" class="accent-sky-500" />
                    Theo ngày
                  </label>
                  <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" v-model="form.confirmationType" value="beforeN" class="accent-sky-500" />
                    Trước N ngày
                  </label>
                </div>
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">
                  {{ form.confirmationType === 'byDate' ? 'Ngày xác nhận' : 'Trước số ngày (N)' }}
                  <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <input
                    v-if="form.confirmationType === 'byDate'"
                    type="date"
                    v-model="form.confirmationDate"
                    :max="maxConfirmationDate"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500 bg-white focus:outline-none focus:border-sky-500 font-semibold"
                  />
                  <input
                    v-else
                    type="number"
                    v-model.number="confirmationDays"
                    min="1"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500 bg-white focus:outline-none focus:border-sky-500 font-semibold"
                  />
                </div>
              </div>
            </div>

            <!-- Row 3: Nhân viên sale / Tổng đặt cọc / Mã nhóm -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Nhân viên sale <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select v-model="form.saleStaff" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-600 bg-white focus:outline-none focus:border-sky-500 appearance-none cursor-pointer font-semibold">
                    <option value="">Chọn nhân viên</option>
                    <option v-for="u in saleUsers" :key="u.id" :value="u.username">{{ u.name || u.username }}</option>
                  </select>
                  <svg class="w-3 h-3 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Tổng đặt cọc</label>
                <input
                  :value="formatCurrency(form.totalDeposit)"
                  disabled
                  class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500 bg-slate-50 cursor-not-allowed font-semibold"
                />
                <p class="text-[10px] text-slate-400 mt-0.5">Tự tổng hợp từ cọc của các tiệc con</p>
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Mã nhóm</label>
                <input
                  v-model="form.groupCode"
                  placeholder="Tự động / theo mã đoàn"
                  class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-500 bg-white focus:outline-none focus:border-sky-500 font-semibold"
                />
              </div>
            </div>

            <!-- Row 4: Công ty -->
            <div>
              <label class="block text-[11px] font-semibold text-slate-500 mb-1">Công ty</label>
              <div class="flex items-center gap-2">
                <div class="relative flex-1">
                  <select v-model="form.company" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 appearance-none cursor-pointer text-slate-700 font-semibold">
                    <option value="KHÁCH LẺ">KHÁCH LẺ</option>
                    <option v-for="c in companyList" :key="c.id" :value="c.name">{{ c.name }}</option>
                  </select>
                  <svg class="w-3 h-3 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-400 hover:bg-sky-500 text-white transition-colors shrink-0 cursor-pointer shadow-sm">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 transition-colors shrink-0 cursor-pointer">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
              </div>
            </div>

            <!-- Row 5: Khách hàng -->
            <div>
              <label class="block text-[11px] font-semibold text-slate-500 mb-1">Khách hàng <span class="text-rose-500">*</span></label>
              <div class="flex items-center gap-2">
                <div class="relative flex-1">
                  <input
                    v-model="form.customer"
                    @input="handleBookerInput"
                    @focus="handleBookerInput"
                    @blur="handleBookerBlur"
                    placeholder="Tìm khách hàng từ bookers theo tên / SĐT..."
                    class="w-full px-3 py-2 pr-7 border border-amber-300 rounded-lg text-xs bg-amber-50 focus:outline-none focus:border-sky-500 text-slate-700 font-semibold"
                  />
                  <svg v-if="form.customer" @click="form.customer=''" class="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  
                  <!-- Booker Suggestions Dropdown -->
                  <div 
                    v-if="showBookerSuggestions && filteredBookers.length > 0"
                    class="absolute left-0 right-0 z-50 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-xs"
                  >
                    <div 
                      v-for="b in filteredBookers" 
                      :key="b.id"
                      @mousedown="selectBooker(b)"
                      class="px-3 py-2 hover:bg-slate-100 cursor-pointer flex justify-between items-center border-b border-slate-50"
                    >
                      <span class="font-bold text-slate-700">{{ b.name }}</span>
                      <span class="text-slate-400 font-semibold">{{ b.phone }}</span>
                    </div>
                  </div>
                </div>
                <button 
                  @click="isAddBookerOpen = true"
                  class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-400 hover:bg-sky-500 text-white transition-colors shrink-0 cursor-pointer shadow-sm"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 transition-colors shrink-0 cursor-pointer">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
              </div>
              <p class="text-[10px] text-slate-400 mt-0.5">Dữ liệu lấy từ Bookers hệ thống</p>
            </div>

            <!-- Row 6: Email / Ghi chú -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Email</label>
                <input v-model="form.email" type="email" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 font-semibold" />
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Ghi chú</label>
                <textarea v-model="form.note" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 resize-none font-semibold"></textarea>
              </div>
            </div>

            <!-- Row 7: Ghi chú xuất hóa đơn VAT -->
            <div>
              <label class="block text-[11px] font-semibold text-slate-500 mb-1">Ghi chú xuất hóa đơn VAT</label>
              <textarea v-model="form.vatNote" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 resize-none font-semibold"></textarea>
            </div>
          </div>
        </div>

        <!-- ===== DANH SÁCH TIỆC CON ===== -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Danh sách tiệc con</span>
              <p v-if="!form.partyName || !form.arrivalDate" class="text-[10px] text-rose-500 font-bold animate-pulse">
                (* Vui lòng điền Tên đặt tiệc và Ngày đến trước)
              </p>
            </div>
            <button
              @click="addSubParty"
              :disabled="!form.partyName || !form.arrivalDate"
              :class="(!form.partyName || !form.arrivalDate) ? 'opacity-40 cursor-not-allowed bg-slate-300 text-slate-500' : 'bg-sky-500 hover:bg-sky-600 text-white'"
              class="flex items-center gap-1.5 text-[11px] font-extrabold px-3 py-1.5 rounded-lg transition-all shadow-sm cursor-pointer"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              Thêm tiệc
            </button>
          </div>

          <div class="p-5">
            <div v-if="subParties.length === 0" class="py-10 text-center text-slate-400">
              <p class="text-xs font-semibold">Chưa có tiệc con. Nhấn nút Thêm tiệc để thêm.</p>
            </div>
            <div v-else class="flex flex-col gap-3">
              <div
                v-for="(sub, idx) in subParties"
                :key="sub.id"
                :class="['border-[3px] rounded-xl p-4 bg-white flex flex-col gap-3 hover:bg-sky-50/10 transition-colors cursor-pointer group', getSubPartyDynamicStatusInfo(sub).borderClass]"
                @click="editSubParty(idx)"
              >
                <!-- Row 1 -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 border-dashed">
                  <div class="flex items-center gap-4 text-xs font-bold text-slate-600 flex-wrap">
                    <span>Mã: {{ sub.bookingCode || ('#' + (idx + 1)) }}</span>
                    <span>Ngày: {{ formatDate(sub.arrivalDate || form.arrivalDate) || '—' }}</span>
                    <span>Thời gian: {{ sub.arrivalTime || '—' }} - {{ sub.departureTime || '—' }}</span>
                    <span>Loại tiệc: {{ sub.partyType || '—' }}</span>
                    <span>Outlet: {{ sub.outletName || sub.outlet || '—' }}</span>
                  </div>
                  <div class="flex items-center gap-2" @click.stop>
                    <span :class="getSubPartyDynamicStatusInfo(sub).class" class="mr-1 border border-current/20">
                      {{ getSubPartyDynamicStatusInfo(sub).text }}
                    </span>
                    <button 
                      @click="editSubParty(idx)" 
                      class="border border-slate-200 text-slate-500 text-[10px] font-bold px-2 py-1 rounded hover:bg-slate-100 transition-colors cursor-pointer"
                    >
                      Sửa
                    </button>
                    <button 
                      @click="subParties.splice(idx, 1)" 
                      class="text-slate-400 hover:text-rose-500 transition-colors cursor-pointer p-1 rounded hover:bg-slate-100"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>

                <!-- Row 2 -->
                <div class="flex items-center gap-5 text-[11px] font-bold text-slate-600 flex-wrap border-b border-slate-100 border-dashed pb-2">
                  <span class="bg-sky-50 text-sky-600 px-3 py-1 rounded-full">Khu vực: {{ sub.location || '—' }}</span>
                  <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full">Bàn: {{ sub.tables || 0 }}</span>
                  <span>Khách: NL: {{ sub.adults || 0 }} | TE: {{ sub.children || 0 }} | Tổng: {{ Number(sub.adults || 0) + Number(sub.children || 0) }}</span>
                  <span>Phát sinh: {{ sub.extra || 0 }}</span>
                  <span class="text-sky-400">Cọc: {{ formatCurrency((sub.deposits || []).reduce((s,d)=>s+d.amount,0)) }}</span>
                  <span class="text-amber-600 text-xs">Tổng tiền: {{ formatCurrency(getSubPartyTotal(sub)) }}</span>
                </div>

                <!-- Row 3 -->
                <div class="flex flex-col gap-2">
                  <span class="text-[10px] font-bold text-slate-400">Menu ({{ (sub.menuItems || []).length }} món)</span>
                  <div class="flex flex-wrap gap-2">
                    <span 
                      v-for="(item, i) in sub.menuItems" 
                      :key="i"
                      class="text-[10px] font-bold px-2.5 py-1 rounded-md"
                      :class="['bg-emerald-50 text-emerald-600', 'bg-blue-50 text-blue-600', 'bg-orange-50 text-orange-600', 'bg-amber-50 text-amber-600'][i % 4]"
                    >
                      {{ item.name }} x{{ item.quantity }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Sub-party modal -->
  <AddSubPartyModal
    :isOpen="isAddSubPartyOpen"
    :parentCustomer="form.customer"
    :parentArrivalDate="form.arrivalDate"
    :subPartyData="selectedSubPartyData"
    @close="isAddSubPartyOpen = false"
    @save="handleSubPartySave"
  />

  <!-- Quick Add Booker Modal -->
  <div v-if="isAddBookerOpen" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 backdrop-blur-xs">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden border border-slate-200">
      <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-700 uppercase">Thêm nhanh Khách hàng mới</span>
        <button @click="isAddBookerOpen = false" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-4 space-y-3">
        <div>
          <label class="block text-[10px] font-bold text-slate-500 mb-1">Tên khách hàng <span class="text-rose-500">*</span></label>
          <input v-model="newBooker.name" placeholder="Tên khách hàng..." class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-sky-500" />
        </div>
        <div>
          <label class="block text-[10px] font-bold text-slate-500 mb-1">Số điện thoại <span class="text-rose-500">*</span></label>
          <input v-model="newBooker.phone" placeholder="Số điện thoại..." class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-sky-500" />
        </div>
        <div>
          <label class="block text-[10px] font-bold text-slate-500 mb-1">Email (tùy chọn)</label>
          <input v-model="newBooker.email" placeholder="Email..." class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-sky-500" />
        </div>
      </div>
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 text-xs">
        <button @click="isAddBookerOpen = false" class="px-3 py-1.5 border border-slate-200 hover:bg-slate-100 rounded-lg text-slate-600 font-semibold cursor-pointer">Hủy</button>
        <button @click="saveNewBooker" class="px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg font-bold cursor-pointer shadow-sm">Lưu khách</button>
      </div>
    </div>
  </div>

  <!-- Custom Unsaved Warning Modal -->
  <div v-if="showUnsavedWarning" class="fixed inset-0 z-[95] flex items-center justify-center bg-black/40 backdrop-blur-xs">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
      <div class="p-6 text-center space-y-4">
        <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center mx-auto text-amber-500">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <div class="space-y-1">
          <h4 class="text-sm font-bold text-slate-800">Thay đổi chưa lưu</h4>
          <p class="text-xs text-slate-500 font-medium">Bạn có các thay đổi chưa được lưu cho đặt tiệc này. Bạn có chắc chắn muốn thoát mà không lưu không?</p>
        </div>
      </div>
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 text-xs">
        <button 
          @click="showUnsavedWarning = false" 
          class="px-4 py-1.5 border border-slate-200 hover:bg-slate-100 rounded-lg text-slate-600 font-semibold cursor-pointer"
        >
          Quay lại chỉnh sửa
        </button>
        <button 
          @click="confirmCloseWithoutSaving" 
          class="px-4 py-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg font-bold shadow-sm cursor-pointer"
        >
          Thoát không lưu
        </button>
      </div>
    </div>
  </div>
</template>
