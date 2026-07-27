<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { fetchProducts } from '@/services/product-service'
import { fetchOutlets, fetchFbLocations } from '@/services/outlet-service'
import PosProductSelector from './PosProductSelector.vue'
import http from '@/services/http'
import { checkFbPartyConflict } from '@/services/fb-party-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  parentCustomer: { type: String, default: '' },
  parentArrivalDate: { type: String, default: '' },
  subPartyData: { type: Object, default: null }
})
const emit = defineEmits(['close', 'save'])

const form = ref({
  bookingCode: '0',
  arrivalDate: '',
  adults: 1,
  children: 0,
  tables: 1,
  extra: 0,
  arrivalTime: '09:41',
  departureTime: '10:41',
  outlet: '',
  location: '',
  partyType: '',
  groupCode: '',
  note: ''
})

const spinnerFields = [
  { key: 'adults', label: 'Người lớn' },
  { key: 'children', label: 'Trẻ em' },
  { key: 'tables', label: 'Số lượng bàn' },
  { key: 'extra', label: 'Phát sinh' }
]

const toolbarBtns = ['¶', '"', 'B', 'U', 'I', 'S', 'X²', 'X₂', 'A', 'Ā', 'T']

const newDeposit = ref({ date: '', method: '', amount: '', note: '' })
const deposits = ref([])
const depositErrors = ref({})
const showCancelledDeposits = ref(false)

const addDeposit = () => {
  depositErrors.value = {}
  if (!newDeposit.value.date) depositErrors.value.date = true
  if (!newDeposit.value.method) depositErrors.value.method = true
  if (!newDeposit.value.amount) depositErrors.value.amount = true
  if (Object.keys(depositErrors.value).length > 0) return
  deposits.value.push({ ...newDeposit.value, status: 'active' })
  newDeposit.value = { date: form.value.arrivalDate, method: '', amount: '', note: '', status: 'active' }
}

const cancelDeposit = (idx) => {
  if (deposits.value[idx]) {
    deposits.value[idx].status = 'cancelled'
  }
}

const filteredDeposits = computed(() => {
  let list = deposits.value
  if (!showCancelledDeposits.value) {
    list = list.filter(d => d.status !== 'cancelled')
  }
  // Sort cancelled to the bottom
  return [...list].sort((a, b) => {
    if (a.status === 'cancelled' && b.status !== 'cancelled') return 1
    if (a.status !== 'cancelled' && b.status === 'cancelled') return -1
    return 0
  })
})

const formatNum = (val) => Number(val || 0).toLocaleString('vi-VN')

// Menu items
const menuItems = ref([])
const newMenuItem = ref({ name: '', quantity: 1, unit: 'Phần', price: '', note: '', product_id: null })
const menuItemErrors = ref({})

// DB products selection logic
const dbProducts = ref([])
const searchResults = ref([])
const showSuggestions = ref(false)

// POS Modal
const showPosModal = ref(false)
const handlePosSave = (selectedItems) => {
  menuItems.value = selectedItems.map(item => ({
    id: item.id || (Date.now() + Math.random()),
    name: item.name,
    product_id: item.product_id,
    code: item.code || item.product_code,
    quantity: item.quantity,
    unit: item.unit,
    price: item.price,
    discount: item.discount || 0,
    note: item.note || ''
  }))
}

// Outlets & Locations & Party Types & Payment Methods
const outlets = ref([])
const locations = ref([])
const partyTypes = ref([])
const paymentMethods = ref([])

// Party type CRUD popup states
const showPartyTypeModal = ref(false)
const newPartyTypeName = ref('')
const editingPartyTypeIdx = ref(-1)
const editingPartyTypeName = ref('')

// Custom Confirm Delete Party Type state
const showConfirmDeletePartyType = ref(false)
const partyTypeIdxToDelete = ref(-1)

const loadDbProducts = async () => {
  try {
    const res = await fetchProducts()
    dbProducts.value = res.data || []
  } catch (err) {
    console.error('Lỗi tải sản phẩm:', err)
  }
}

const loadPaymentMethods = async () => {
  try {
    const res = await http.get('/payment-methods')
    paymentMethods.value = res.data?.data || []
  } catch (err) {
    console.error('Lỗi tải payment methods:', err)
  }
}

const loadOutletsAndTypes = async () => {
  try {
    const resOut = await fetchOutlets()
    outlets.value = resOut.data || []

    const savedTypes = localStorage.getItem('fb_party_types')
    if (savedTypes) {
      partyTypes.value = JSON.parse(savedTypes)
    } else {
      const defaultTypes = ['Gala Dinner', 'Buffet', 'Set Menu', 'Teabreak', 'Hội nghị', 'Khác']
      partyTypes.value = defaultTypes
      localStorage.setItem('fb_party_types', JSON.stringify(defaultTypes))
    }
  } catch (err) {
    console.error('Lỗi tải outlets/types:', err)
  }
}

const onOutletChange = async () => {
  form.value.location = ''
  locations.value = []
  if (!form.value.outlet) return

  const selectedOutlet = outlets.value.find(o => o.id == form.value.outlet)
  const outletCode = selectedOutlet ? selectedOutlet.code : ''

  try {
    const resLoc = await fetchFbLocations(outletCode)
    locations.value = resLoc.data?.data || []
  } catch (err) {
    console.error('Lỗi tải khu vực:', err)
  }
}

// Party Type local CRUD operations
const addPartyType = () => {
  const name = newPartyTypeName.value.trim()
  if (!name) return
  if (partyTypes.value.includes(name)) {
    uiStore.alert('Loại tiệc này đã tồn tại!')
    return
  }
  partyTypes.value.push(name)
  localStorage.setItem('fb_party_types', JSON.stringify(partyTypes.value))
  newPartyTypeName.value = ''
}

const deletePartyType = (idx) => {
  partyTypeIdxToDelete.value = idx
  showConfirmDeletePartyType.value = true
}

const confirmDeletePartyType = () => {
  const idx = partyTypeIdxToDelete.value
  if (idx !== -1) {
    const oldName = partyTypes.value[idx]
    partyTypes.value.splice(idx, 1)
    localStorage.setItem('fb_party_types', JSON.stringify(partyTypes.value))
    if (form.value.partyType === oldName) {
      form.value.partyType = ''
    }
  }
  showConfirmDeletePartyType.value = false
  partyTypeIdxToDelete.value = -1
}

const startEditPartyType = (idx) => {
  editingPartyTypeIdx.value = idx
  editingPartyTypeName.value = partyTypes.value[idx]
}

const saveEditPartyType = () => {
  const name = editingPartyTypeName.value.trim()
  if (!name) return
  const idx = editingPartyTypeIdx.value
  const oldName = partyTypes.value[idx]
  partyTypes.value[idx] = name
  localStorage.setItem('fb_party_types', JSON.stringify(partyTypes.value))
  if (form.value.partyType === oldName) {
    form.value.partyType = name
  }
  editingPartyTypeIdx.value = -1
  editingPartyTypeName.value = ''
}

const isLoading = ref(false)
const initialState = ref(null)
const showUnsavedWarning = ref(false)

watch(() => props.isOpen, async (newVal) => {
  if (newVal) {
    isLoading.value = true
    try {
      await loadDbProducts()
      await loadOutletsAndTypes()
      await loadPaymentMethods()
      
      if (props.subPartyData) {
        const d = props.subPartyData
        
        let outletVal = d.outlet_id || d.outlet || '';
        if (outletVal && !isNaN(outletVal)) {
          outletVal = Number(outletVal);
        } else if (outletVal && isNaN(outletVal)) {
          const f = outlets.value.find(o => o.name === outletVal || o.code === outletVal);
          if (f) outletVal = f.id;
        }

        form.value = {
          id: d.id || null,
          bookingCode: d.bookingCode || '0',
          arrivalDate: d.arrivalDate || new Date().toISOString().split('T')[0],
          adults: Number(d.adults || 0),
          children: Number(d.children || 0),
          tables: Number(d.tables || 0),
          extra: Number(d.extra || 0),
          arrivalTime: d.arrivalTime || '09:41',
          departureTime: d.departureTime || '10:41',
          outlet: outletVal,
          location: d.location || '',
          partyType: d.partyType || '',
          groupCode: d.groupCode || '',
          note: d.note || ''
        }
        menuItems.value = Array.isArray(d.menuItems) ? JSON.parse(JSON.stringify(d.menuItems)) : []
        deposits.value = Array.isArray(d.deposits) ? JSON.parse(JSON.stringify(d.deposits)).map(dep => ({...dep, status: dep.status || 'active'})) : []
        
        if (form.value.outlet) {
          const selectedOutlet = outlets.value.find(o => o.id == form.value.outlet)
          const outletCode = selectedOutlet ? selectedOutlet.code : ''
          try {
            const resLoc = await fetchFbLocations(outletCode)
            locations.value = resLoc.data?.data || []
            // await DOM update for select options
            await nextTick()
            // assign location after locations list is populated and rendered
            form.value.location = d.location || ''
          } catch (err) {
            console.error('Lỗi tải khu vực cũ:', err)
          }
        }
      } else {
        const today = new Date().toISOString().split('T')[0]
        form.value = {
          bookingCode: '0',
          arrivalDate: today,
          adults: 1,
          children: 0,
          tables: 1,
          extra: 0,
          arrivalTime: '09:41',
          departureTime: '10:41',
          outlet: '',
          location: '',
          partyType: '',
          groupCode: '',
          note: ''
        }
        menuItems.value = []
        deposits.value = []
      }
      
      newMenuItem.value = { name: '', quantity: 1, unit: 'Phần', price: '', note: '', product_id: null, code: null }
      newDeposit.value = { date: form.value.arrivalDate || new Date().toISOString().split('T')[0], method: '', amount: '', note: '' }
      
      initialState.value = {
        form: JSON.parse(JSON.stringify(form.value)),
        menuItems: JSON.parse(JSON.stringify(menuItems.value)),
        deposits: JSON.parse(JSON.stringify(deposits.value))
      }
    } catch (err) {
      console.error('Lỗi load dữ liệu tiệc con:', err)
    } finally {
      isLoading.value = false
    }
  }
})

const formattedDepositAmount = computed({
  get() {
    if (!newDeposit.value.amount) return ''
    return Number(newDeposit.value.amount).toLocaleString('vi-VN')
  },
  set(val) {
    const num = Number(val.replace(/[^0-9]/g, ''))
    newDeposit.value.amount = isNaN(num) ? '' : num
  }
})

const handleSearchInput = () => {
  const keyword = newMenuItem.value.name.trim().toLowerCase()
  if (!keyword) {
    searchResults.value = dbProducts.value
  } else {
    searchResults.value = dbProducts.value.filter(p => 
      p.name.toLowerCase().includes(keyword) || 
      (p.product_code && p.product_code.toLowerCase().includes(keyword))
    )
  }
  showSuggestions.value = true
}

const selectProduct = (prod) => {
  newMenuItem.value.name = prod.name
  newMenuItem.value.price = prod.price
  newMenuItem.value.product_id = prod.id
  newMenuItem.value.code = prod.product_code || prod.code
  newMenuItem.value.unit = prod.unit?.name || 'Phần'
  showSuggestions.value = false
}

const handleBlur = () => {
  setTimeout(() => {
    showSuggestions.value = false
  }, 200)
}

const addMenuItem = () => {
  menuItemErrors.value = {}
  if (!newMenuItem.value.name) menuItemErrors.value.name = true
  if (!newMenuItem.value.price) menuItemErrors.value.price = true
  if (Object.keys(menuItemErrors.value).length > 0) return
  menuItems.value.push({ id: Date.now(), ...newMenuItem.value })
  newMenuItem.value = { name: '', quantity: 1, unit: 'Phần', price: '', discount: 0, note: '', product_id: null, code: null }
}

const removeMenuItem = (idx) => menuItems.value.splice(idx, 1)

const menuTotal = computed(() =>
  menuItems.value.reduce((s, m) => s + (Number(m.price) * Number(m.quantity || 1) - Number(m.discount || 0)), 0)
)

const isDirty = computed(() => {
  if (!initialState.value) return false
  const formChanged = JSON.stringify(form.value) !== JSON.stringify(initialState.value.form)
  const menuChanged = JSON.stringify(menuItems.value) !== JSON.stringify(initialState.value.menuItems)
  const depositsChanged = JSON.stringify(deposits.value) !== JSON.stringify(initialState.value.deposits)
  return formChanged || menuChanged || depositsChanged
})

const isServingOrCompleted = computed(() => {
  if (!props.subPartyData?.id) return false
  if (props.subPartyData.status === 'completed' || props.subPartyData.status === 'serving') return true
  
  const arrDateStr = props.subPartyData.arrivalDate
  if (!arrDateStr) return false
  
  const startTime = new Date(`${arrDateStr}T${props.subPartyData.arrivalTime || '00:00'}:00`).getTime()
  const now = new Date().getTime()
  
  return now >= startTime
})

const conflictStatus = ref(null)
const conflictMessage = ref('')

const checkConflict = async () => {
  if (!form.value.arrivalDate || !form.value.arrivalTime || !form.value.departureTime || !form.value.outlet || !form.value.location) {
    uiStore.alert('Vui lòng điền đủ Ngày, Giờ, Outlet và Địa điểm trước khi kiểm tra.')
    return
  }
  
  // Validate parent date
  if (props.parentArrivalDate) {
    const pDate = new Date(props.parentArrivalDate)
    pDate.setHours(0, 0, 0, 0)
    const arrDate = new Date(form.value.arrivalDate)
    arrDate.setHours(0, 0, 0, 0)
    if (arrDate < pDate) {
      conflictStatus.value = 'conflict'
      conflictMessage.value = 'Ngày của tiệc con không được trước ngày của tiệc chính.'
      return
    }
  }

  // Validate at least 1 hour in advance (only if adding a new sub-party, or if they change time)
  if (!props.subPartyData?.id) {
    const arrDateTime = new Date(form.value.arrivalDate)
    const [h, m] = form.value.arrivalTime.split(':')
    arrDateTime.setHours(parseInt(h), parseInt(m), 0, 0)
    
    const oneHourFromNow = new Date()
    oneHourFromNow.setHours(oneHourFromNow.getHours() + 1)
    
    if (arrDateTime < oneHourFromNow) {
      conflictStatus.value = 'conflict'
      conflictMessage.value = 'Giờ diễn ra tiệc con phải sau hiện tại ít nhất 1 tiếng.'
      return
    }
  }

  // Validate departure time >= arrival time + 30 mins
  const [arrH, arrM] = form.value.arrivalTime.split(':').map(Number)
  const [depH, depM] = form.value.departureTime.split(':').map(Number)
  const arrTotalMins = arrH * 60 + arrM
  let depTotalMins = depH * 60 + depM
  
  // Handle cross-midnight by adding 24h if departure is smaller than arrival
  if (depTotalMins < arrTotalMins) {
    depTotalMins += 24 * 60
  }
  
  if (depTotalMins - arrTotalMins < 30) {
    conflictStatus.value = 'conflict'
    conflictMessage.value = 'Giờ đi phải sau giờ đến ít nhất 30 phút.'
    return
  }

  conflictStatus.value = 'checking'
  try {
    const res = await checkFbPartyConflict({
      arrival_date: form.value.arrivalDate,
      arrival_time: form.value.arrivalTime,
      departure_time: form.value.departureTime,
      outlet_id: form.value.outlet,
      outlet_name: outlets.value.find(o => o.id === form.value.outlet)?.name || '',
      location: form.value.location,
      exclude_id: props.subPartyData?.id || null
    })
    
    if (res.data?.conflict) {
      conflictStatus.value = 'conflict'
      conflictMessage.value = res.data.message
    } else {
      conflictStatus.value = 'ok'
      conflictMessage.value = res.data?.message || 'Không trùng lịch'
    }
  } catch (err) {
    conflictStatus.value = null
    uiStore.alert('Lỗi kiểm tra trùng lịch: ' + (err.response?.data?.message || err.message))
  }
}

watch([
  () => form.value.arrivalDate,
  () => form.value.arrivalTime,
  () => form.value.departureTime,
  () => form.value.outlet,
  () => form.value.location
], () => {
  if (form.value.arrivalDate && form.value.arrivalTime && form.value.departureTime && form.value.outlet && form.value.location) {
    // Auto check if all fields are filled
    checkConflict()
  } else {
    conflictStatus.value = null
    conflictMessage.value = ''
  }
})

const handleSave = () => {
  if (conflictStatus.value === 'conflict') {
    uiStore.alert('Không thể lưu vì trùng lịch. Vui lòng chọn lại thời gian hoặc địa điểm khác.')
    return
  }
  
  if (!form.value.arrivalDate) {
    uiStore.alert('Vui lòng chọn Ngày đến.')
    return
  }
  if (props.parentArrivalDate) {
    const parentDate = new Date(props.parentArrivalDate)
    const arrDate = new Date(form.value.arrivalDate)
    if (arrDate < parentDate) {
      uiStore.alert('Ngày đến không được trước ngày đặt tiệc.')
      return
    }
  }
  if (!form.value.outlet) {
    uiStore.alert('Vui lòng chọn Outlet.')
    return
  }
  if (!form.value.location) {
    uiStore.alert('Vui lòng chọn Địa điểm / Block.')
    return
  }
  if (!form.value.partyType) {
    uiStore.alert('Vui lòng chọn Loại tiệc.')
    return
  }

  const selectedOutlet = outlets.value.find(o => o.id === form.value.outlet)
  emit('save', { 
    ...form.value, 
    outletName: selectedOutlet ? selectedOutlet.name : '',
    deposits: deposits.value, 
    menuItems: menuItems.value 
  })
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

const inc = (key) => form.value[key]++
const dec = (key) => { if (form.value[key] > 0) form.value[key]-- }
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-[70] flex items-start justify-center bg-black/30 backdrop-blur-sm overflow-y-auto py-6">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl mx-4 flex flex-col relative overflow-hidden">
      <!-- Loading overlay (PMS Style spinner) -->
      <div v-if="isLoading" class="absolute inset-0 z-[80] flex items-center justify-center bg-white/60 backdrop-blur-xs">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 shrink-0">
        <span class="text-sm font-extrabold text-slate-800">Thêm tiệc con</span>
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 space-y-6 overflow-y-auto max-h-[80vh]">

        <!-- THÔNG TIN ĐẶT CHỖ -->
        <section>
          <h3 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-4">Thông tin đặt chỗ</h3>

          <!-- Row 1: Mã đặt chỗ + Ngày đến + spinners -->
          <div class="grid grid-cols-6 gap-3 mb-3">
            <!-- Mã đặt chỗ -->
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Mã đặt chỗ</label>
              <input
                v-model="form.bookingCode"
                disabled
                class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-slate-50 text-slate-400 cursor-not-allowed"
              />
            </div>
            <!-- Ngày đến -->
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Ngày đến <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input
                  type="date"
                  :min="props.parentArrivalDate"
                  :disabled="isServingOrCompleted"
                  v-model="form.arrivalDate"
                  placeholder="dd/mm/yyyy"
                  class="w-full px-2.5 py-1.5 border border-amber-300 rounded-lg text-xs bg-amber-50 focus:outline-none focus:border-sky-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed disabled:border-slate-200"
                />
              </div>
            </div>
            <!-- Spinner fields -->
            <div v-for="sf in spinnerFields" :key="sf.key">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">{{ sf.label }}</label>
              <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden">
                <input
                  v-model.number="form[sf.key]"
                  type="number"
                  min="0"
                  class="flex-1 px-1 py-1.5 text-xs text-center focus:outline-none w-0 min-w-0"
                />
                <div class="flex flex-col border-l border-slate-200 shrink-0">
                  <button @click="inc(sf.key)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-500 cursor-pointer text-[9px] border-b border-slate-200 leading-none">▲</button>
                  <button @click="dec(sf.key)" class="px-1.5 py-0.5 hover:bg-slate-100 text-slate-500 cursor-pointer text-[9px] leading-none">▼</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Row 2: Giờ đến / Giờ đi / Outlet / Địa điểm / Loại tiệc -->
          <div class="grid grid-cols-5 gap-3 mb-3">
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Giờ đến</label>
              <div class="relative">
                <input 
                  type="time" 
                  :disabled="isServingOrCompleted"
                  v-model="form.arrivalTime"
                  class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 font-semibold disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed" 
                />
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Giờ đi</label>
              <div class="relative">
                <input 
                  type="time" 
                  v-model="form.departureTime"
                  class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 font-semibold" 
                />
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Outlet <span class="text-rose-500">*</span></label>
              <div class="relative">
                <select 
                  v-model="form.outlet"
                  @change="onOutletChange"
                  class="w-full px-2.5 py-1.5 border border-amber-300 rounded-lg text-xs bg-amber-50 focus:outline-none focus:border-sky-500 appearance-none cursor-pointer text-slate-700 font-semibold"
                >
                  <option value="">Chọn outlet</option>
                  <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <svg class="w-3 h-3 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Địa điểm / Block <span class="text-rose-500">*</span></label>
              <div class="relative">
                <select 
                  v-model="form.location"
                  :disabled="!form.outlet"
                  class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 appearance-none cursor-pointer text-slate-700 font-semibold disabled:bg-slate-50 disabled:cursor-not-allowed"
                >
                  <option value="">{{ form.outlet ? 'Chọn địa điểm' : 'Chọn outlet trước' }}</option>
                  <option v-for="l in locations" :key="l.id" :value="l.name">{{ l.name }}</option>
                </select>
                <svg class="w-3 h-3 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </div>
            <div class="flex gap-1.5">
              <div class="flex-1 min-w-0">
                <label class="block text-[10px] font-semibold text-slate-500 mb-1">Loại tiệc <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <select 
                    v-model="form.partyType"
                    class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 appearance-none cursor-pointer text-slate-700 font-semibold"
                  >
                    <option value="">Chọn loại tiệc</option>
                    <option v-for="t in partyTypes" :key="t" :value="t">{{ t }}</option>
                  </select>
                  <svg class="w-3 h-3 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </div>
              </div>
              <div class="flex items-end pb-0.5 shrink-0">
                <button 
                  @click="showPartyTypeModal = true"
                  class="w-7 h-[30px] flex items-center justify-center rounded-lg bg-sky-400 hover:bg-sky-500 text-white cursor-pointer shadow-sm"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Kiểm tra trùng lịch -->
          <div class="flex items-center gap-3">
            <button 
              @click="checkConflict"
              :disabled="conflictStatus === 'checking'"
              class="px-4 py-1.5 border border-sky-400 text-sky-600 rounded-lg text-xs font-semibold hover:bg-sky-50 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
              {{ conflictStatus === 'checking' ? 'Đang kiểm tra...' : 'Kiểm tra trùng lịch' }}
            </button>
            <span v-if="conflictStatus === 'conflict'" class="text-xs font-semibold text-rose-500">
              <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ conflictMessage }}
            </span>
            <span v-if="conflictStatus === 'ok'" class="text-xs font-semibold text-emerald-500">
              <i class="fa-solid fa-circle-check mr-1"></i> {{ conflictMessage }}
            </span>
          </div>
        </section>

        <!-- THÔNG TIN KHÁCH -->
        <section>
          <h3 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-4">Thông tin khách</h3>

          <div class="mb-3">
            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Mã đoàn</label>
            <div class="relative">
              <input v-model="form.groupCode" placeholder="Chọn hoặc thêm khách hàng"
                class="w-full px-3 py-1.5 pr-7 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 text-slate-500" />
              <svg class="w-3 h-3 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Khách hàng (từ đặt tiệc)</label>
            <div class="px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 font-semibold">
              {{ parentCustomer || 'Thảo Vy · 0367861541' }}
            </div>
          </div>

          <!-- Ghi chú -->
          <div>
            <label class="block text-[10px] font-semibold text-slate-500 mb-1">Ghi chú</label>
            <textarea
              v-model="form.note"
              rows="4"
              placeholder="Nhập ghi chú cho tiệc..."
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 font-semibold placeholder:text-slate-400"
            ></textarea>
          </div>
        </section>

        <!-- QUẢN LÝ ĐẶT CỌC -->
        <section>
          <h3 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-4">Quản lý Đặt cọc</h3>

          <p class="text-[10px] font-semibold text-slate-500 mb-2">Thông tin đặt cọc</p>
          <div class="flex items-end gap-3 mb-5">
            <div class="w-32 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-700 mb-1">Ngày cọc <span class="text-rose-500">*</span></label>
              <input 
                type="date"
                v-model="newDeposit.date"
                :class="depositErrors.date ? 'border-rose-400 bg-rose-50' : 'border-amber-300 bg-amber-50'"
                class="w-full px-2.5 py-1.5 border rounded-lg text-xs focus:outline-none focus:border-sky-500 font-semibold"
              />
            </div>
            <div class="w-36 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-700 mb-1">Phương thức <span class="text-rose-500">*</span></label>
              <div class="relative">
                <select 
                  v-model="newDeposit.method"
                  :class="depositErrors.method ? 'border-rose-400 bg-rose-50' : 'border-slate-200 bg-white'"
                  class="w-full px-2.5 py-1.5 border rounded-lg text-xs focus:outline-none focus:border-sky-500 appearance-none cursor-pointer text-slate-700 font-semibold"
                >
                  <option value="">--Chọn--</option>
                  <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.name">{{ pm.name }}</option>
                </select>
                <svg class="w-3 h-3 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </div>
            <div class="w-40 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-700 mb-1">Số Tiền <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input 
                  v-model="formattedDepositAmount" 
                  placeholder="Nhập số tiền VNĐ"
                  :class="depositErrors.amount ? 'border-rose-400 bg-rose-50' : 'border-amber-300 bg-amber-50'"
                  class="w-full px-2.5 py-1.5 pr-7 border rounded-lg text-xs focus:outline-none focus:border-sky-500 text-right font-bold text-slate-700"
                />
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">₫</span>
              </div>
            </div>
            <div class="flex-1">
              <label class="block text-[10px] font-semibold text-slate-700 mb-1">Ghi chú</label>
              <input 
                v-model="newDeposit.note"
                class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 font-semibold"
              />
            </div>
            <button @click="addDeposit"
              class="px-4 py-1.5 bg-sky-400 hover:bg-sky-500 text-white text-xs font-extrabold rounded-lg transition-all cursor-pointer shadow-sm h-[34px]">
              Thêm
            </button>
          </div>

          <!-- Deposit list -->
          <div class="flex items-center justify-between mb-2">
            <p class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wide">Danh sách đặt cọc</p>
            <label class="flex items-center gap-2 cursor-pointer text-[10px] font-semibold text-slate-600">
              Hiển thị cọc đã hủy
              <button
                @click="showCancelledDeposits = !showCancelledDeposits"
                :class="showCancelledDeposits ? 'bg-sky-500' : 'bg-slate-300'"
                class="relative inline-flex w-8 h-4 rounded-full transition-colors focus:outline-none"
              >
                <span
                  :class="showCancelledDeposits ? 'translate-x-4' : 'translate-x-0.5'"
                  class="inline-block w-3 h-3 bg-white rounded-full shadow transform transition-transform mt-0.5"
                ></span>
              </button>
            </label>
          </div>
          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500">Ngày</td>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500">Phương thức</td>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right">Số tiền</td>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500 w-full">Ghi chú</td>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500">Trạng thái</td>
                  <td class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right w-16">Hành động</td>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredDeposits.length === 0">
                  <td colspan="6" class="py-8 text-center text-slate-400 text-[11px] font-semibold">Không thấy dữ liệu</td>
                </tr>
                <tr v-for="(dep, i) in filteredDeposits" v-else :key="i" class="border-t border-slate-100 hover:bg-slate-50" :class="{'opacity-50 bg-slate-50': dep.status === 'cancelled'}">
                  <td class="px-3 py-2">{{ dep.date }}</td>
                  <td class="px-3 py-2">{{ dep.method }}</td>
                  <td class="px-3 py-2 text-right font-bold whitespace-nowrap" :class="dep.status === 'cancelled' ? 'text-slate-500 line-through' : 'text-emerald-600'">{{ formatNum(dep.amount) }} ₫</td>
                  <td class="px-3 py-2 text-slate-400">{{ dep.note }}</td>
                  <td class="px-3 py-2">
                    <span v-if="dep.status === 'cancelled'" class="px-2 py-0.5 rounded text-[9px] font-extrabold bg-rose-50 text-rose-500 border border-rose-100 uppercase tracking-wide">Đã huỷ</span>
                    <span v-else class="px-2 py-0.5 rounded text-[9px] font-extrabold bg-emerald-50 text-emerald-500 border border-emerald-100 uppercase tracking-wide">Hoạt động</span>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <button v-if="dep.status !== 'cancelled'" @click="cancelDeposit(deposits.indexOf(dep))" class="text-rose-500 hover:text-rose-700 font-bold text-xs bg-rose-50 hover:bg-rose-100 px-2 py-1 rounded transition-colors cursor-pointer border border-rose-100">Huỷ</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- DANH SÁCH MÓN ĂN -->
        <section>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Danh sách món ăn</h3>
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-extrabold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                Tổng: {{ formatNum(menuTotal) }} ₫
              </span>
            </div>
          </div>

          <!-- Add row -->
          <div class="flex items-end gap-2 mb-3 bg-slate-50 border border-slate-200 rounded-xl p-3">
            <div class="flex-1 min-w-0 relative">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Tên món <span class="text-rose-500">*</span></label>
              <input
                v-model="newMenuItem.name"
                @input="handleSearchInput"
                @focus="handleSearchInput"
                @blur="handleBlur"
                placeholder="Nhập hoặc chọn món ăn..."
                :class="menuItemErrors.name ? 'border-rose-400 bg-rose-50' : 'border-slate-200 bg-white'"
                class="w-full px-2.5 py-1.5 border rounded-lg text-xs focus:outline-none focus:border-sky-500"
              />
              <!-- Suggestions Dropdown -->
              <div 
                v-if="showSuggestions && searchResults.length > 0"
                class="absolute left-0 right-0 z-50 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-xs"
              >
                <div 
                  v-for="prod in searchResults" 
                  :key="prod.id"
                  @mousedown="selectProduct(prod)"
                  class="px-3 py-2 hover:bg-slate-100 cursor-pointer flex justify-between items-center border-b border-slate-50"
                >
                  <span class="font-bold text-slate-700">{{ prod.name }}</span>
                  <span class="text-emerald-600 font-extrabold">{{ formatNum(prod.price) }} ₫</span>
                </div>
              </div>
            </div>
            <div class="w-20 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Số lượng</label>
              <input
                v-model.number="newMenuItem.quantity"
                type="number" min="1"
                class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 text-center"
              />
            </div>
            <div class="w-24 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Đơn vị</label>
              <select
                v-model="newMenuItem.unit"
                class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500 cursor-pointer appearance-none"
              >
                <option>Phần</option>
                <option>Đĩa</option>
                <option>Tô</option>
                <option>Ly</option>
                <option>Chai</option>
                <option>Kg</option>
              </select>
            </div>
            <div class="w-36 shrink-0">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Đơn giá (₫) <span class="text-rose-500">*</span></label>
              <input
                v-model="newMenuItem.price"
                type="number" min="0"
                placeholder="0"
                :class="menuItemErrors.price ? 'border-rose-400 bg-rose-50' : 'border-amber-300 bg-amber-50'"
                class="w-full px-2.5 py-1.5 border rounded-lg text-xs focus:outline-none focus:border-sky-500 text-right"
              />
            </div>
            <div class="flex-1 min-w-0">
              <label class="block text-[10px] font-semibold text-slate-500 mb-1">Ghi chú</label>
              <input
                v-model="newMenuItem.note"
                placeholder="Ghi chú món..."
                class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500"
              />
            </div>
            <div class="shrink-0 pb-0.5">
              <button
                @click="addMenuItem"
                class="flex items-center gap-1 px-3 py-1.5 bg-sky-500 hover:bg-sky-600 text-white text-xs font-extrabold rounded-lg cursor-pointer shadow-sm transition-all"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Thêm
              </button>
            </div>
          </div>

          <!-- Table -->
          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left w-8">#</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left">Tên món</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-center w-20">SL</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-center w-20">Đơn vị</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right w-32">Đơn giá</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right w-24">Giảm giá</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-right w-32">Thành tiền</th>
                  <th class="px-3 py-2 text-[10px] font-semibold text-slate-500 text-left">Ghi chú</th>
                  <th class="px-3 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="menuItems.length === 0">
                  <td colspan="8" class="py-8 text-center text-slate-400 text-[11px] font-semibold">Chưa có món ăn. Nhập thông tin và nhấn Thêm.</td>
                </tr>
                <tr v-for="(item, idx) in menuItems" v-else :key="item.id" class="border-t border-slate-100 hover:bg-slate-50 transition-colors">
                  <td class="px-3 py-2 text-slate-400 font-semibold">{{ idx + 1 }}</td>
                  <td class="px-3 py-2 font-semibold text-slate-800">
                    <span v-if="item.code" class="text-slate-500 font-normal mr-1">[{{ item.code }}]</span>
                    {{ item.name }}
                  </td>
                  <td class="px-3 py-2 text-center text-slate-600">{{ item.quantity }}</td>
                  <td class="px-3 py-2 text-center">
                    <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[9px] font-bold">{{ item.unit }}</span>
                  </td>
                  <td class="px-3 py-2 text-right text-slate-700 font-semibold whitespace-nowrap">{{ formatNum(item.price) }} ₫</td>
                  <td class="px-3 py-2 text-right text-rose-500 font-semibold whitespace-nowrap">{{ formatNum(item.discount || 0) }} ₫</td>
                  <td class="px-3 py-2 text-right font-extrabold text-emerald-700 whitespace-nowrap">
                    {{ formatNum(Number(item.price) * Number(item.quantity || 1) - Number(item.discount || 0)) }} ₫
                  </td>
                  <td class="px-3 py-2 text-slate-400 text-[11px]">{{ item.note }}</td>
                  <td class="px-3 py-2 text-center">
                    <button @click="removeMenuItem(idx)" class="text-slate-300 hover:text-rose-500 transition-colors cursor-pointer">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
              <!-- Footer total row -->
              <tfoot v-if="menuItems.length > 0" class="bg-slate-50 border-t-2 border-slate-200">
                <tr>
                  <td colspan="5" class="px-3 py-2 text-[10px] font-extrabold text-slate-600 uppercase tracking-wide text-right">Tổng cộng</td>
                  <td class="px-3 py-2 text-right font-extrabold text-emerald-700 whitespace-nowrap">{{ formatNum(menuTotal) }} ₫</td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>

      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-200 bg-slate-50 shrink-0 rounded-b-xl">
        <button @click="showPosModal = true" class="flex items-center gap-1.5 px-3 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 text-[11px] font-extrabold rounded-lg transition-all cursor-pointer border border-sky-200">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          Thêm sản phẩm
        </button>
        <div class="flex items-center gap-2">
          <button @click="handleClose" class="px-4 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-800 transition-colors cursor-pointer">
            Hủy
          </button>
          <button @click="handleSave" :disabled="conflictStatus === 'conflict'" :class="conflictStatus === 'conflict' ? 'opacity-50 cursor-not-allowed' : ''" class="px-5 py-1.5 bg-sky-500 hover:bg-sky-600 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all cursor-pointer">
            Lưu tiệc con
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- Manage Party Types Modal -->
  <div v-if="showPartyTypeModal" class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 backdrop-blur-xs">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
      <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-700 uppercase">Quản lý danh mục loại tiệc</span>
        <button @click="showPartyTypeModal = false; editingPartyTypeIdx = -1;" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="p-4 space-y-4">
        <!-- Add new type -->
        <div class="flex gap-2">
          <input 
            v-model="newPartyTypeName"
            @keyup.enter="addPartyType"
            placeholder="Nhập tên loại tiệc mới..." 
            class="flex-1 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-sky-500 font-semibold"
          />
          <button 
            @click="addPartyType"
            class="px-4 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-xs font-bold shadow-sm transition-colors cursor-pointer"
          >
            Thêm
          </button>
        </div>

        <!-- Types List -->
        <div class="border border-slate-200 rounded-lg divide-y divide-slate-100 max-h-60 overflow-y-auto">
          <div 
            v-for="(t, idx) in partyTypes" 
            :key="t"
            class="px-3 py-2 flex items-center justify-between text-xs hover:bg-slate-50"
          >
            <!-- Normal mode -->
            <div v-if="editingPartyTypeIdx !== idx" class="font-bold text-slate-700">
              {{ t }}
            </div>
            <div v-if="editingPartyTypeIdx !== idx" class="flex items-center gap-2">
              <button @click="startEditPartyType(idx)" class="text-sky-600 hover:text-sky-700 font-semibold cursor-pointer">Sửa</button>
              <button @click="deletePartyType(idx)" class="text-rose-500 hover:text-rose-600 font-semibold cursor-pointer">Xóa</button>
            </div>

            <!-- Edit mode -->
            <input 
              v-if="editingPartyTypeIdx === idx"
              v-model="editingPartyTypeName"
              @keyup.enter="saveEditPartyType"
              class="flex-1 mr-2 px-2 py-1 border border-sky-400 rounded focus:outline-none text-xs font-semibold"
            />
            <div v-if="editingPartyTypeIdx === idx" class="flex items-center gap-1.5">
              <button @click="saveEditPartyType" class="text-emerald-600 hover:text-emerald-700 font-semibold cursor-pointer">Lưu</button>
              <button @click="editingPartyTypeIdx = -1" class="text-slate-400 hover:text-slate-500 font-semibold cursor-pointer">Hủy</button>
            </div>
          </div>
        </div>
      </div>
      <div class="px-4 py-2.5 bg-slate-50 border-t border-slate-100 flex justify-end text-xs">
        <button 
          @click="showPartyTypeModal = false; editingPartyTypeIdx = -1;" 
          class="px-4 py-1.5 border border-slate-200 hover:bg-slate-100 rounded-lg text-slate-600 font-semibold cursor-pointer"
        >
          Đóng
        </button>
      </div>
    </div>
  </div>

  <!-- Custom Confirm Delete Party Type Modal -->
  <div v-if="showConfirmDeletePartyType" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 backdrop-blur-xs">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden border border-slate-200">
      <div class="p-6 text-center space-y-4">
        <div class="w-12 h-12 bg-rose-50 rounded-full flex items-center justify-center mx-auto text-rose-500">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
        </div>
        <div class="space-y-1">
          <h4 class="text-sm font-bold text-slate-800">Xác nhận xóa loại tiệc</h4>
          <p class="text-xs text-slate-500 font-semibold">Bạn có chắc chắn muốn xóa loại tiệc này?</p>
        </div>
      </div>
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 text-xs">
        <button 
          @click="showConfirmDeletePartyType = false; partyTypeIdxToDelete = -1;" 
          class="px-4 py-1.5 border border-slate-200 hover:bg-slate-100 rounded-lg text-slate-600 font-semibold cursor-pointer"
        >
          Hủy
        </button>
        <button 
          @click="confirmDeletePartyType" 
          class="px-4 py-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg font-bold shadow-sm cursor-pointer"
        >
          Xác nhận xóa
        </button>
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
          <p class="text-xs text-slate-500 font-medium">Bạn có các thay đổi chưa được lưu cho tiệc con này. Bạn có chắc chắn muốn thoát mà không lưu không?</p>
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

  <PosProductSelector
    :is-open="showPosModal"
    :initial-cart="menuItems"
    @close="showPosModal = false"
    @save="handlePosSave"
  />
</template>
