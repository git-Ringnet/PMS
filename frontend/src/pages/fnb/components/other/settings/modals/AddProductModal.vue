<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  product: {
    type: Object,
    default: null
  },
  categoriesList: {
    type: Array,
    default: () => []
  },
  unitsList: {
    type: Array,
    default: () => []
  },
  outletsList: {
    type: Array,
    default: () => []
  },
  productsList: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'save', 'openAddCategory'])

const activeTab = ref('info') // info, outlets, combo

// Tab 1: Info Fields
const name = ref('')
const nameEn = ref('')
const shortName = ref('')
const vatBillingName = ref('')
const productCategoryId = ref('')
const productCode = ref('')
const serviceGroup = ref('Ăn uống')
const unitId = ref('')
const barcode = ref('0000000000000')
const note = ref('')
const imageFile = ref(null)
const imagePreview = ref(null)

// Flags & Checkboxes in Info Tab (Matching Image 4)
const isPrint = ref(false)
const changeTable = ref(false)
const openKey = ref(false)
const isAlcohol = ref(false)
const flexiblePrice = ref(false)
const trackStock = ref(false)
const isActive = ref(true)
const isContra = ref(false)
const noReinvest = ref(false)
const isGateTicket = ref(false)
const isDishExchange = ref(false)
const isPrePrinted = ref(false)
const processingTime = ref(0)
const servingTime = ref(0)

// Base Pricing fields
const price = ref(0)
const originalAmount = ref(0)
const serviceChargePercent = ref(5) // Default to 5% as in Image 4
const serviceChargeAmount = ref(0)
const taxPercent = ref(8) // Default to 8% VAT
const taxAmount = ref(0)
const specialTaxPercent = ref(0)
const specialTaxAmount = ref(0)
const isFixedPrice = ref(false) // Giá niêm yết

// Tab 2: Pricing by Outlet (Matching Image 5 UI)
const selectedOutletCode = ref('')
// Structured as: { [outletCode]: { is_active, update_price, update_combo_price, is_expanded, price, original_amount, service_charge_percent, tax_percent, special_tax_percent, combo_price, combo_original, combo_service, combo_tax, combo_special, is_open_sale_counter, selectedCounterOutlets: [] } }
const outletSettings = ref({})

// Tab 3: Combo items
const isCombo = ref(false)
const comboItems = ref([]) // array of { child_id, quantity, price, childProduct }

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    return path
  }
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  let finalPath = cleanPath
  if (!cleanPath.startsWith('storage/')) {
    finalPath = 'storage/' + cleanPath
  }
  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`
}

// Hierarchical category list builder for dropdown
const formattedCategories = computed(() => {
  const buildTree = (list, pid = null, depth = 0) => {
    let result = []
    const items = list.filter(item => item.parent_id === pid)
    items.forEach(item => {
      result.push({
        id: item.id,
        name: item.name,
        indentName: '  '.repeat(depth) + (depth > 0 ? '↳ ' : '') + item.name
      })
      result = result.concat(buildTree(list, item.id, depth + 1))
    })
    return result
  }
  return buildTree(props.categoriesList, null, 0)
})

// Auto Base pricing calculations
const calculateAmountsFromOriginal = () => {
  const orig = Number(originalAmount.value) || 0
  const svcRate = Number(serviceChargePercent.value) || 0
  const specRate = Number(specialTaxPercent.value) || 0
  const vatRate = Number(taxPercent.value) || 0

  const svcAmt = orig * (svcRate / 100)
  const afterSvc = orig + svcAmt
  const specAmt = afterSvc * (specRate / 100)
  const afterSpec = afterSvc + specAmt
  const vatAmt = afterSpec * (vatRate / 100)
  const finalPrice = afterSpec + vatAmt

  serviceChargeAmount.value = Number(svcAmt.toFixed(2))
  specialTaxAmount.value = Number(specAmt.toFixed(2))
  taxAmount.value = Number(vatAmt.toFixed(2))
  price.value = Number(finalPrice.toFixed(2))
}

const calculateOriginalFromPrice = () => {
  const finalPrice = Number(price.value) || 0
  const svcRate = Number(serviceChargePercent.value) || 0
  const specRate = Number(specialTaxPercent.value) || 0
  const vatRate = Number(taxPercent.value) || 0

  const multiplier = (1 + svcRate / 100) * (1 + specRate / 100) * (1 + vatRate / 100)
  const orig = multiplier > 0 ? finalPrice / multiplier : 0

  originalAmount.value = Number(orig.toFixed(2))
  
  const svcAmt = orig * (svcRate / 100)
  const afterSvc = orig + svcAmt
  const specAmt = afterSvc * (specRate / 100)
  const afterSpec = afterSvc + specAmt
  const vatAmt = afterSpec * (vatRate / 100)

  serviceChargeAmount.value = Number(svcAmt.toFixed(2))
  specialTaxAmount.value = Number(specAmt.toFixed(2))
  taxAmount.value = Number(vatAmt.toFixed(2))
}

watch([originalAmount, serviceChargePercent, specialTaxPercent, taxPercent], () => {
  calculateAmountsFromOriginal()
})

// Auto Outlet pricing calculations helper
const calculateOutletAmounts = (outletCode, type = 'outlet') => {
  const s = outletSettings.value[outletCode]
  if (!s) return

  if (type === 'outlet') {
    const orig = Number(s.original_amount) || 0
    const svcRate = Number(s.service_charge_percent) || 0
    const specRate = Number(s.special_tax_percent) || 0
    const vatRate = Number(s.tax_percent) || 0

    const svcAmt = orig * (svcRate / 100)
    const afterSvc = orig + svcAmt
    const specAmt = afterSvc * (specRate / 100)
    const afterSpec = afterSvc + specAmt
    const vatAmt = afterSpec * (vatRate / 100)
    
    s.price = Number((afterSpec + vatAmt).toFixed(2))
  } else {
    // combo
    const orig = Number(s.combo_original) || 0
    const svcRate = Number(s.combo_service) || 0
    const specRate = Number(s.combo_special) || 0
    const vatRate = Number(s.combo_tax) || 0

    const svcAmt = orig * (svcRate / 100)
    const afterSvc = orig + svcAmt
    const specAmt = afterSvc * (specRate / 100)
    const afterSpec = afterSvc + specAmt
    const vatAmt = afterSpec * (vatRate / 100)
    
    s.combo_price = Number((afterSpec + vatAmt).toFixed(2))
  }
}

onMounted(() => {
  // Initialize default outlet settings matching Image 5 structure
  props.outletsList.forEach(ot => {
    outletSettings.value[ot.code] = {
      is_active: true,
      update_price: false,
      update_combo_price: false,
      is_expanded: false,
      price: 0,
      original_amount: 0,
      service_charge_percent: 5,
      tax_percent: 8,
      special_tax_percent: 0,
      combo_price: 0,
      combo_original: 0,
      combo_service: 5,
      combo_tax: 8,
      combo_special: 0,
      is_open_sale_counter: false,
      selectedCounterOutlets: []
    }
  })
  if (props.outletsList.length > 0) {
    selectedOutletCode.value = props.outletsList[0].code
  }

  if (props.product) {
    name.value = props.product.name
    nameEn.value = props.product.name_en || ''
    shortName.value = props.product.short_name || ''
    vatBillingName.value = props.product.vat_billing_name || ''
    productCategoryId.value = props.product.fb_product_category_id || ''
    productCode.value = props.product.product_code || ''
    serviceGroup.value = props.product.service_group || 'Ăn uống'
    unitId.value = props.product.unit_id || ''
    barcode.value = props.product.barcode || '0000000000000'
    note.value = props.product.note || ''
    
    price.value = Number(props.product.price) || 0
    originalAmount.value = Number(props.product.original_amount) || 0
    serviceChargePercent.value = Number(props.product.service_charge_percent) || 0
    serviceChargeAmount.value = Number(props.product.service_charge_amount) || 0
    taxPercent.value = Number(props.product.tax_percent) || 0
    taxAmount.value = Number(props.product.tax_amount) || 0
    specialTaxPercent.value = Number(props.product.special_tax_percent) || 0
    specialTaxAmount.value = Number(props.product.special_tax_amount) || 0

    isPrint.value = !!props.product.is_print
    changeTable.value = !!props.product.change_table
    openKey.value = !!props.product.open_key
    isAlcohol.value = !!props.product.is_alcohol
    flexiblePrice.value = !!props.product.flexible_price
    trackStock.value = !!props.product.track_stock
    isActive.value = props.product.is_active !== false
    isCombo.value = !!props.product.is_combo
    isContra.value = !!props.product.is_contra
    noReinvest.value = !!props.product.no_reinvest
    isGateTicket.value = !!props.product.is_gate_ticket
    isDishExchange.value = !!props.product.is_dish_exchange
    isPrePrinted.value = !!props.product.is_pre_printed
    processingTime.value = Number(props.product.processing_time) || 0
    servingTime.value = Number(props.product.serving_time) || 0

    if (props.product.image) {
      imagePreview.value = getImageUrl(props.product.image)
    }

    // Populate saved outlet prices
    if (props.product.outlet_prices && Array.isArray(props.product.outlet_prices)) {
      props.product.outlet_prices.forEach(op => {
        if (outletSettings.value[op.outlet_code]) {
          outletSettings.value[op.outlet_code].is_active = !!op.is_active
          outletSettings.value[op.outlet_code].price = Number(op.price) || 0
          outletSettings.value[op.outlet_code].original_amount = Number(op.original_amount) || 0
          outletSettings.value[op.outlet_code].service_charge_percent = Number(op.service_charge_percent) || 0
          outletSettings.value[op.outlet_code].tax_percent = Number(op.tax_percent) || 0
          outletSettings.value[op.outlet_code].special_tax_percent = Number(op.special_tax_percent) || 0
          outletSettings.value[op.outlet_code].update_price = Number(op.original_amount) > 0
          
          outletSettings.value[op.outlet_code].is_open_sale_counter = !!op.is_open_sale_counter
        }
      })
    }

    // Populate combo items
    if (props.product.combo_items && Array.isArray(props.product.combo_items)) {
      comboItems.value = props.product.combo_items.map(ci => ({
        child_id: ci.child_id,
        quantity: ci.quantity,
        price: Number(ci.price) || 0,
        childProduct: props.productsList.find(p => p.id === ci.child_id)
      }))
    }
  }
})

const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    imageFile.value = file
    const reader = new FileReader()
    reader.onload = (event) => {
      imagePreview.value = event.target.result
    }
    reader.readAsDataURL(file)
  }
}

const triggerFileInput = () => {
  document.getElementById('product-image-input').click()
}

const removeImage = () => {
  imageFile.value = null
  imagePreview.value = null
}

const addComboRow = () => {
  comboItems.value.push({
    child_id: '',
    quantity: 1,
    price: 0,
    childProduct: null
  })
}

const removeComboRow = (idx) => {
  comboItems.value.splice(idx, 1)
}

const handleComboProductChange = (item) => {
  const child = props.productsList.find(p => p.id === Number(item.child_id))
  if (child) {
    item.childProduct = child
    item.price = Number(child.price) || 0
  }
}

const handleSave = () => {
  if (!name.value.trim()) {
    alert('Vui lòng nhập tên thực đơn!')
    return
  }
  if (!productCategoryId.value) {
    alert('Vui lòng chọn loại thực đơn!')
    return
  }

  const formData = new FormData()
  formData.append('name', name.value.trim())
  formData.append('fb_product_category_id', productCategoryId.value)
  formData.append('product_code', productCode.value.trim())
  formData.append('name_en', nameEn.value.trim())
  formData.append('short_name', shortName.value.trim())
  formData.append('vat_billing_name', vatBillingName.value.trim())
  formData.append('service_group', serviceGroup.value)
  formData.append('unit_id', unitId.value || '')
  formData.append('barcode', barcode.value.trim())
  formData.append('note', note.value.trim())

  formData.append('price', String(price.value))
  formData.append('original_amount', String(originalAmount.value))
  formData.append('service_charge_percent', String(serviceChargePercent.value))
  formData.append('service_charge_amount', String(serviceChargeAmount.value))
  formData.append('tax_percent', String(taxPercent.value))
  formData.append('tax_amount', String(taxAmount.value))
  formData.append('special_tax_percent', String(specialTaxPercent.value))
  formData.append('special_tax_amount', String(specialTaxAmount.value))

  formData.append('is_print', isPrint.value ? '1' : '0')
  formData.append('change_table', changeTable.value ? '1' : '0')
  formData.append('open_key', openKey.value ? '1' : '0')
  formData.append('is_alcohol', isAlcohol.value ? '1' : '0')
  formData.append('flexible_price', flexiblePrice.value ? '1' : '0')
  formData.append('track_stock', trackStock.value ? '1' : '0')
  formData.append('is_active', isActive.value ? '1' : '0')
  formData.append('is_combo', isCombo.value ? '1' : '0')
  formData.append('is_contra', isContra.value ? '1' : '0')
  formData.append('no_reinvest', noReinvest.value ? '1' : '0')
  formData.append('is_gate_ticket', isGateTicket.value ? '1' : '0')
  formData.append('is_dish_exchange', isDishExchange.value ? '1' : '0')
  formData.append('is_pre_printed', isPrePrinted.value ? '1' : '0')
  formData.append('processing_time', String(processingTime.value))
  formData.append('serving_time', String(servingTime.value))

  if (imageFile.value) {
    formData.append('image', imageFile.value)
  } else if (!imagePreview.value && props.product && props.product.image) {
    formData.append('remove_image', '1')
  }

  // Format outlet settings
  const formattedOutletSettings = []
  Object.keys(outletSettings.value).forEach(code => {
    const s = outletSettings.value[code]
    formattedOutletSettings.push({
      outlet_code: code,
      is_active: s.is_active,
      price: s.update_price ? s.price : price.value,
      original_amount: s.update_price ? s.original_amount : originalAmount.value,
      service_charge_percent: s.update_price ? s.service_charge_percent : serviceChargePercent.value,
      tax_percent: s.update_price ? s.tax_percent : taxPercent.value,
      special_tax_percent: s.update_price ? s.special_tax_percent : specialTaxPercent.value,
      is_open_sale_counter: s.is_open_sale_counter
    })
  })
  formData.append('outlet_prices', JSON.stringify(formattedOutletSettings))

  // Format combo settings
  const formattedCombos = comboItems.value
    .filter(item => item.child_id)
    .map(item => ({
      child_id: Number(item.child_id),
      quantity: Number(item.quantity) || 1,
      price: Number(item.price) || 0
    }))
  formData.append('combo_items', JSON.stringify(formattedCombos))

  emit('save', formData)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-5xl overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      
      <!-- Modal Header -->
      <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">{{ product ? 'Chỉnh sửa thực đơn' : 'Thêm thực đơn' }}</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-slate-150 bg-slate-50/50 px-5 gap-4">
        <button 
          @click="activeTab = 'info'" 
          class="py-2.5 px-3 border-b-2 font-bold text-xs transition duration-200"
          :class="activeTab === 'info' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          Thông tin
        </button>
        <button 
          @click="activeTab = 'outlets'" 
          class="py-2.5 px-3 border-b-2 font-bold text-xs transition duration-200"
          :class="activeTab === 'outlets' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          Đơn giá theo outlet
        </button>
        <button 
          @click="activeTab = 'combo'" 
          class="py-2.5 px-3 border-b-2 font-bold text-xs transition duration-200"
          :class="activeTab === 'combo' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
        >
          Combo
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto max-h-[70vh] flex-1">
        
        <!-- TAB 1: THÔNG TIN (Matching Image 4 Layout) -->
        <div v-show="activeTab === 'info'" class="flex gap-6 flex-col md:flex-row">
          
          <!-- CỘT LEFT (W-1/4): Image, Barcode, Checkboxes, Switches -->
          <div class="w-full md:w-60 shrink-0 flex flex-col items-center border-r border-slate-100 pr-6">
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 text-center w-full">Hình ảnh</label>
            <div 
              @click="triggerFileInput" 
              class="w-full aspect-square border border-slate-200 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition overflow-hidden relative"
            >
              <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
              <div v-else class="flex flex-col items-center text-slate-400">
                <span class="text-3xl text-slate-350">+</span>
                <span class="text-[10px] font-bold mt-1 text-slate-400">Chọn file</span>
              </div>
            </div>
            
            <input 
              type="file" 
              id="product-image-input" 
              class="hidden" 
              accept="image/*" 
              @change="handleFileChange" 
            />

            <!-- Image action buttons -->
            <div v-if="imagePreview" class="flex gap-4 mt-2.5 justify-center">
              <button @click="triggerFileInput" class="text-slate-450 hover:text-sky-655 transition" title="Thay đổi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
              <button @click="removeImage" class="text-rose-500 hover:text-rose-600 transition" title="Xóa">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>

            <!-- Barcode Display (Image 4) -->
            <div class="mt-4 flex flex-col items-center w-full border border-slate-100 bg-slate-50/50 p-2.5 rounded-xl">
              <!-- Mock Barcode Stripes -->
              <div class="flex gap-0.5 items-stretch h-8 mb-1">
                <span class="w-0.5 bg-slate-800"></span><span class="w-1 bg-slate-800"></span><span class="w-0.5 bg-transparent"></span>
                <span class="w-0.5 bg-slate-800"></span><span class="w-0.5 bg-slate-800"></span><span class="w-1 bg-slate-800"></span>
                <span class="w-0.5 bg-transparent"></span><span class="w-0.5 bg-slate-800"></span><span class="w-1.5 bg-slate-800"></span>
                <span class="w-0.5 bg-slate-800"></span><span class="w-0.5 bg-transparent"></span><span class="w-1 bg-slate-800"></span>
              </div>
              <span class="text-[9px] font-bold text-slate-500 tracking-widest">{{ barcode }}</span>
              <span class="text-[10px] font-extrabold text-slate-850 mt-1">Giá: {{ price.toLocaleString() }}đ</span>
            </div>

            <!-- Switches list (Matching Image 4 checkboxes/switches) -->
            <div class="w-full mt-4 flex flex-col gap-3">
              <div class="flex gap-4">
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none">
                  <input type="checkbox" v-model="flexiblePrice" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Thời giá
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none">
                  <input type="checkbox" v-model="openKey" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Open item
                </label>
              </div>

              <div class="flex gap-4">
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none">
                  <input type="checkbox" v-model="isContra" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Is Contra
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none">
                  <input type="checkbox" v-model="noReinvest" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Không tái đầu tư
                </label>
              </div>

              <div class="flex items-center justify-between border-t border-slate-50 pt-2.5">
                <span class="font-bold text-slate-500">Có cồn</span>
                <button @click="isAlcohol = !isAlcohol" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isAlcohol ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isAlcohol ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-500">Tồn kho</span>
                <button @click="trackStock = !trackStock" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="trackStock ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="trackStock ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-500">Vé vào cổng</span>
                <button @click="isGateTicket = !isGateTicket" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isGateTicket ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isGateTicket ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-500">Phiếu đổi món</span>
                <button @click="isDishExchange = !isDishExchange" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isDishExchange ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isDishExchange ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-500">Vé in trước</span>
                <button @click="isPrePrinted = !isPrePrinted" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isPrePrinted ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isPrePrinted ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <!-- Processing time -->
              <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex flex-col gap-1">
                  <span class="text-[9px] font-bold text-slate-400 uppercase">Thời gian xử lý (phút)</span>
                  <input type="number" v-model.number="processingTime" min="0" class="border border-slate-200 rounded-lg px-2 py-1 text-center font-bold" />
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-[9px] font-bold text-slate-400 uppercase">Thời gian phục vụ</span>
                  <input type="number" v-model.number="servingTime" min="0" class="border border-slate-200 rounded-lg px-2 py-1 text-center font-bold" />
                </div>
              </div>
            </div>
          </div>

          <!-- CỘT GIỮA (FLEX-1): Text Info Fields -->
          <div class="flex-1 flex flex-col gap-4">
            
            <div class="grid grid-cols-2 gap-4">
              <!-- Category select -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Loại thực đơn *</label>
                <div class="flex gap-1.5 items-center">
                  <select 
                    v-model="productCategoryId" 
                    class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none bg-white"
                  >
                    <option value="">Chọn loại thực đơn</option>
                    <option v-for="cat in formattedCategories" :key="cat.id" :value="cat.id">
                      {{ cat.indentName }}
                    </option>
                  </select>
                  <button 
                    type="button" 
                    @click="$emit('openAddCategory')" 
                    class="p-1.5 border border-sky-100 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-full transition"
                    title="Thêm loại thực đơn mới"
                  >
                    <span class="text-sm font-bold leading-none">+</span>
                  </button>
                </div>
              </div>

              <!-- Product Code -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mã</label>
                <input 
                  type="text" 
                  v-model="productCode" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none bg-slate-50/50"
                  placeholder="Nhập mã thực đơn"
                />
              </div>
            </div>

            <!-- Product Name -->
            <div class="flex flex-col gap-1">
              <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên *</label>
              <input 
                type="text" 
                v-model="name" 
                class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none bg-slate-50/50 font-bold"
                placeholder="Nhập tên món ăn"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- English Name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên tiếng Anh</label>
                <input 
                  type="text" 
                  v-model="nameEn" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none"
                  placeholder="English Name"
                />
              </div>

              <!-- Short Name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Short Name</label>
                <input 
                  type="text" 
                  v-model="shortName" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none"
                  placeholder="Tên viết tắt"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Service Group dropdown selection -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Nhóm dịch vụ</label>
                <select 
                  v-model="serviceGroup" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none bg-white"
                >
                  <option value="Ăn uống">Ăn uống</option>
                  <option value="Dịch vụ buồng">Dịch vụ buồng</option>
                  <option value="Dịch vụ khác">Dịch vụ khác</option>
                </select>
              </div>

              <!-- VAT bill name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên Xuất Hóa Đơn VAT</label>
                <input 
                  type="text" 
                  v-model="vatBillingName" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none"
                  placeholder="Hóa đơn VAT"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- UOM selection -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">ĐVT</label>
                <select 
                  v-model="unitId" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none bg-white"
                >
                  <option value="">Chọn ĐVT</option>
                  <option v-for="u in unitsList" :key="u.id" :value="u.id">
                    {{ u.name || u.ten }}
                  </option>
                </select>
              </div>

              <!-- Barcode input -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mã vạch</label>
                <input 
                  type="text" 
                  v-model="barcode" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none"
                  placeholder="Barcode number"
                />
              </div>
            </div>

            <!-- Print Button (Matching Image 4 blue printer icon) -->
            <div class="mt-4 flex justify-start">
              <button 
                type="button" 
                @click="isPrint = !isPrint"
                class="flex items-center gap-2 bg-[#78C5E7]/25 hover:bg-[#78C5E7]/40 text-sky-700 px-5 py-2.5 rounded-lg font-bold text-xs transition shadow-xs"
              >
                <!-- Printer Icon -->
                <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                In
              </button>
            </div>

          </div>

          <!-- CỘT RIGHT (W-64): Pricing (Matching Image 4 Right Column) -->
          <div class="w-full md:w-64 shrink-0 flex flex-col gap-4 border-l border-slate-100 pl-6 bg-slate-50/20 p-4 rounded-xl">
            <!-- Title -->
            <div class="flex items-center gap-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-2">
              <!-- Help icon -->
              <span class="w-4 h-4 rounded-full border border-sky-400 text-sky-500 flex items-center justify-center font-bold">?</span>
              Giá & Thuế phí
            </div>

            <!-- Original Amount -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Giá gốc</label>
              <input 
                type="number" 
                v-model.number="originalAmount" 
                class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-bold focus:outline-none bg-slate-50"
              />
            </div>

            <!-- Service Charge % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Phí dịch vụ (%)</label>
              <div class="flex gap-2">
                <input 
                  type="number" 
                  v-model.number="serviceChargePercent" 
                  class="w-20 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none"
                />
                <input 
                  type="text" 
                  :value="serviceChargeAmount.toLocaleString()" 
                  disabled 
                  class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none bg-slate-50 text-slate-400 text-right font-semibold"
                />
              </div>
            </div>

            <!-- Special Tax % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Thuế Đặc Biệt (%)</label>
              <div class="flex gap-2">
                <input 
                  type="number" 
                  v-model.number="specialTaxPercent" 
                  class="w-20 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none"
                />
                <input 
                  type="text" 
                  :value="specialTaxAmount.toLocaleString()" 
                  disabled 
                  class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none bg-slate-50 text-slate-400 text-right font-semibold"
                />
              </div>
            </div>

            <!-- VAT % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">VAT (%)</label>
              <div class="flex gap-2">
                <input 
                  type="number" 
                  v-model.number="taxPercent" 
                  class="w-20 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none"
                />
                <input 
                  type="text" 
                  :value="taxAmount.toLocaleString()" 
                  disabled 
                  class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none bg-slate-50 text-slate-400 text-right font-semibold"
                />
              </div>
            </div>

            <!-- Final Price -->
            <div class="flex flex-col gap-1">
              <label class="font-extrabold text-sky-600">Đơn giá bán</label>
              <input 
                type="number" 
                v-model.number="price" 
                @input="calculateOriginalFromPrice"
                class="w-full border border-sky-200 rounded-lg px-2.5 py-1.5 font-extrabold text-sky-700 focus:outline-none bg-sky-50/20"
              />
            </div>

            <!-- Fixed Price flag -->
            <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none mt-2">
              <input type="checkbox" v-model="isFixedPrice" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
              Giá niêm yết
            </label>

            <!-- Huge display box of final price -->
            <div class="mt-4 bg-[#fefecc] border border-yellow-200/80 p-4 rounded-xl text-center font-bold text-slate-800 text-base shadow-inner">
              {{ price.toLocaleString() }}
            </div>

          </div>

        </div>

        <!-- TAB 2: ĐƠN GIÁ THEO OUTLET (Matching Image 5 Layout) -->
        <div v-show="activeTab === 'outlets'" class="flex flex-col gap-6">
          <div v-if="selectedOutletCode && outletSettings[selectedOutletCode]" class="flex flex-col gap-6">
            <!-- Top Row Options -->
            <div class="flex flex-wrap items-center gap-6 bg-slate-50 p-4 rounded-xl border border-slate-150">
              <!-- Active switch -->
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-600">Mở bán</span>
                <button 
                  @click="outletSettings[selectedOutletCode].is_active = !outletSettings[selectedOutletCode].is_active" 
                  class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" 
                  :class="outletSettings[selectedOutletCode]?.is_active ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[selectedOutletCode]?.is_active ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>

              <!-- Outlet select dropdown -->
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-600">Outlet *</span>
                <select 
                  v-model="selectedOutletCode"
                  class="border border-slate-250 rounded-lg px-3 py-1.5 font-bold text-xs bg-white focus:outline-none"
                >
                  <option v-for="ot in outletsList" :key="ot.code" :value="ot.code">
                    {{ ot.name }}
                  </option>
                </select>
              </div>

              <!-- Update price switch -->
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-600">Cập nhật giá outlet</span>
                <button 
                  @click="outletSettings[selectedOutletCode].update_price = !outletSettings[selectedOutletCode].update_price" 
                  class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" 
                  :class="outletSettings[selectedOutletCode]?.update_price ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[selectedOutletCode]?.update_price ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>

              <!-- Update combo price switch -->
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-600">Cập nhật giá combo</span>
                <button 
                  @click="outletSettings[selectedOutletCode].update_combo_price = !outletSettings[selectedOutletCode].update_combo_price" 
                  class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" 
                  :class="outletSettings[selectedOutletCode]?.update_combo_price ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[selectedOutletCode]?.update_combo_price ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>

              <!-- Expand switch -->
              <div class="flex items-center gap-2 ml-auto">
                <span class="font-bold text-slate-600">Mở rộng</span>
                <button 
                  @click="outletSettings[selectedOutletCode].is_expanded = !outletSettings[selectedOutletCode].is_expanded" 
                  class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" 
                  :class="outletSettings[selectedOutletCode]?.is_expanded ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[selectedOutletCode]?.is_expanded ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>
            </div>

            <!-- Price configurations per Row (Matching Image 5 Layout) -->
            <div class="flex flex-col gap-4 bg-white border border-slate-200 rounded-xl p-6 shadow-xs">
              <!-- Row 1: Outlet Pricing -->
              <div v-show="outletSettings[selectedOutletCode]?.update_price" class="flex flex-col gap-3 pb-4 border-b border-slate-100">
                <span class="text-[10px] font-extrabold text-[#e05656] uppercase tracking-wider">Outlet *</span>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <!-- Original Amount -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Giá gốc</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].original_amount" @input="calculateOutletAmounts(selectedOutletCode, 'outlet')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none font-bold text-slate-700 bg-slate-50" />
                  </div>
                  <!-- Service charge -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Phí dịch vụ (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].service_charge_percent" @input="calculateOutletAmounts(selectedOutletCode, 'outlet')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- Special tax -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Thuế Đặc Biệt (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].special_tax_percent" @input="calculateOutletAmounts(selectedOutletCode, 'outlet')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- VAT -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">VAT (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].tax_percent" @input="calculateOutletAmounts(selectedOutletCode, 'outlet')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- Final price -->
                  <div class="flex flex-col gap-1">
                    <label class="font-extrabold text-sky-600">Đơn giá</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].price" class="border border-sky-200 rounded-lg px-2.5 py-1.5 focus:outline-none font-extrabold text-sky-700 bg-sky-50/20" />
                  </div>
                </div>
              </div>

              <!-- Row 2: Combo Pricing -->
              <div v-show="outletSettings[selectedOutletCode]?.update_combo_price" class="flex flex-col gap-3 pb-4">
                <span class="text-[10px] font-extrabold text-slate-800 uppercase tracking-wider">Combo *</span>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <!-- Original Amount -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Giá gốc</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].combo_original" @input="calculateOutletAmounts(selectedOutletCode, 'combo')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none font-bold text-slate-700 bg-slate-50" />
                  </div>
                  <!-- Service charge -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Phí dịch vụ (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].combo_service" @input="calculateOutletAmounts(selectedOutletCode, 'combo')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- Special tax -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">Thuế Đặc Biệt (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].combo_special" @input="calculateOutletAmounts(selectedOutletCode, 'combo')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- VAT -->
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500">VAT (%)</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].combo_tax" @input="calculateOutletAmounts(selectedOutletCode, 'combo')" class="border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none" />
                  </div>
                  <!-- Final price -->
                  <div class="flex flex-col gap-1">
                    <label class="font-extrabold text-sky-600">Đơn giá</label>
                    <input type="number" v-model.number="outletSettings[selectedOutletCode].combo_price" class="border border-sky-200 rounded-lg px-2.5 py-1.5 focus:outline-none font-extrabold text-sky-700 bg-sky-50/20" />
                  </div>
                </div>
              </div>

              <!-- Warning notice if none of switches are active -->
              <div v-show="!outletSettings[selectedOutletCode]?.update_price && !outletSettings[selectedOutletCode]?.update_combo_price" class="text-center py-6 text-slate-400 font-bold">
                Bật các tùy chọn cập nhật giá ở hàng trên để cấu hình riêng cho Outlet này.
              </div>

              <!-- Row 3: Counter sales expansion (Mở bán tại quầy) -->
              <div v-show="outletSettings[selectedOutletCode]?.is_expanded" class="border-t border-slate-100 pt-4 flex flex-col gap-2">
                <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Mở bán tại quầy</span>
                <div class="flex flex-wrap gap-4">
                  <label 
                    v-for="ot in outletsList" 
                    :key="ot.code" 
                    class="flex items-center gap-1.5 font-semibold text-slate-600 cursor-pointer"
                  >
                    <input 
                      type="checkbox" 
                      :value="ot.code" 
                      v-model="outletSettings[selectedOutletCode].selectedCounterOutlets"
                      class="w-4 h-4 rounded border-slate-350 accent-sky-500" 
                    />
                    {{ ot.name }}
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center text-slate-400 py-12 font-bold bg-white rounded-xl border border-slate-200 shadow-sm">
            Dữ liệu outlet trống hoặc chưa được cấu hình.
          </div>
        </div>

        <!-- TAB 3: COMBO CONFIGURATION -->
        <div v-show="activeTab === 'combo'" class="flex flex-col gap-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Cấu hình món Combo</span>
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-500">KÍCH HOẠT COMBO</span>
              <button @click="isCombo = !isCombo" class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" :class="isCombo ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="isCombo ? 'translate-x-[18px]' : 'translate-x-1'"></span>
              </button>
            </div>
          </div>

          <div v-if="isCombo" class="flex flex-col gap-4">
            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
              <table class="w-full border-collapse bg-white">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200 text-left text-xs">
                    <th class="p-3">Món con *</th>
                    <th class="p-3 w-28 text-center">Đơn vị</th>
                    <th class="p-3 w-24 text-center">Số lượng</th>
                    <th class="p-3 w-28 text-right">Đơn giá bán</th>
                    <th class="p-3 w-28 text-right">Thành tiền</th>
                    <th class="p-3 w-16 text-center">Xóa</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <tr v-for="(item, idx) in comboItems" :key="idx" class="hover:bg-slate-50/50">
                    <td class="p-3">
                      <select 
                        v-model="item.child_id"
                        @change="handleComboProductChange(item)"
                        class="w-full border border-slate-200 rounded px-2.5 py-1 text-xs font-semibold bg-white focus:outline-none"
                      >
                        <option value="">Chọn món ăn con</option>
                        <option v-for="p in productsList" :key="p.id" :value="p.id">
                          {{ p.product_code ? `[${p.product_code}] ` : '' }}{{ p.name }}
                        </option>
                      </select>
                    </td>
                    <td class="p-3 text-center text-slate-500 font-semibold">
                      {{ unitsList.find(u => u.id === item.childProduct?.unit_id)?.name || '-' }}
                    </td>
                    <td class="p-3 text-center">
                      <input 
                        type="number" 
                        v-model.number="item.quantity"
                        min="1"
                        class="w-full border border-slate-200 rounded px-2 py-0.5 text-center font-bold"
                      />
                    </td>
                    <td class="p-3 text-right">
                      <input 
                        type="number" 
                        v-model.number="item.price"
                        class="w-24 border border-slate-200 rounded px-2 py-0.5 text-right font-bold"
                      />
                    </td>
                    <td class="p-3 text-right font-extrabold text-slate-700">
                      {{ ((item.price || 0) * (item.quantity || 1)).toLocaleString() }}
                    </td>
                    <td class="p-3 text-center">
                      <button @click="removeComboRow(idx)" class="text-rose-500 hover:text-rose-600 transition">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <button 
              type="button" 
              @click="addComboRow"
              class="w-full border border-dashed border-sky-300 text-sky-600 hover:bg-sky-50 font-bold rounded-lg py-2 transition"
            >
              + Thêm món ăn vào combo
            </button>
          </div>

          <div v-else class="text-center text-slate-400 py-10 font-bold bg-slate-50/50 border border-slate-100 rounded-xl">
            Vui lòng bật switch kích hoạt để định nghĩa cấu trúc combo món ăn này.
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 bg-slate-50/50">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 bg-[#e2f3fc] hover:bg-[#d0ebfa] text-sky-700 px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
          Lưu
        </button>
      </div>

    </div>
  </div>
</template>
