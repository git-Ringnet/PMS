<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import http from '@/services/http'
import JsBarcode from 'jsbarcode'
import SelectComboItemsModal from './SelectComboItemsModal.vue'

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

const isLoading = ref(false)
const activeTab = ref('info') // info, outlets, combo

// Tab 1: Info Fields
const name = ref('')
const nameEn = ref('')
const shortName = ref('')
const vatBillingName = ref('')
const productCategoryId = ref('')
const productCode = ref('')
const serviceGroup = ref('')
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

// Dynamic fields for Gate Ticket and Dish Exchange
const hotelServices = ref([])
const entranceIp = ref('')
const entranceGateTicketType = ref(0)
const exchangeLimitHours = ref(0)
const ticketType = ref('')
const isPrintOneTicket = ref(false)
const isInStock = ref(1)
const selectedPrinterIds = ref([])
const printersList = ref([])
const isPrinterDropdownOpen = ref(false)
const isCategoryDropdownOpen = ref(false)
const collapsedCategories = ref({})

// Tab 2: Pricing by Outlet (Matching Image 5 UI)
const selectedOutletCode = ref('')
// Structured as: { [outletCode]: { is_active, update_price, update_combo_price, is_expanded, price, original_amount, service_charge_percent, tax_percent, special_tax_percent, combo_price, combo_original, combo_service, combo_tax, combo_special, is_open_sale_counter, selectedCounterOutlets: [] } }
const outletSettings = ref({})
const allLocations = ref([])

// Tab 3: Combo items
const isCombo = ref(false)
const comboItems = ref([]) // array of { child_id, quantity, price, childProduct }
const isGetPriceFromItems = ref(false)
const isCheckCombo = ref(false)
const comboMaxItems = ref(1)
const showSelectComboModal = ref(false)

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

const visibleCategories = computed(() => {
  const result = []
  const checkVisible = (list, pid = null, depth = 0) => {
    const items = list.filter(item => item.parent_id === pid)
    items.forEach(item => {
      const hasChildren = list.some(c => c.parent_id === item.id)
      result.push({
        ...item,
        depth,
        hasChildren,
        isCollapsed: collapsedCategories.value[item.id] === true
      })
      if (collapsedCategories.value[item.id] !== true) {
        checkVisible(list, item.id, depth + 1)
      }
    })
  }
  checkVisible(props.categoriesList, null, 0)
  return result
})

const drawBarcode = () => {
  const code = (productCode.value || '') + (barcode.value || '')
  if (code.trim()) {
    setTimeout(() => {
      try {
        JsBarcode('#barcode-canvas', code, {
          format: 'CODE128',
          displayValue: false,
          height: 35,
          width: 1.5,
          margin: 0,
          background: 'transparent',
          lineColor: '#1e293b'
        })
      } catch (e) {
        console.warn('Lỗi vẽ barcode:', e)
      }
    }, 50)
  }
}

watch(() => props.show, (newVal) => {
  if (newVal && !props.product) {
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
        selectedCounterOutlets: getLocationsForOutlet(ot.code).map(l => l.id)
      }
    })
  }
}, { immediate: true })

watch([barcode, productCode, () => props.show], () => {
  if (props.show) {
    drawBarcode()
  }
}, { flush: 'post' })

// Auto Base pricing calculations (VND rounding)
const calculateAmountsFromOriginal = () => {
  const orig = Math.round(Number(originalAmount.value) || 0)
  const svcRate = Number(serviceChargePercent.value) || 0
  const specRate = Number(specialTaxPercent.value) || 0
  const vatRate = Number(taxPercent.value) || 0

  const svcAmt = Math.round(orig * (svcRate / 100))
  const afterSvc = orig + svcAmt
  const specAmt = Math.round(afterSvc * (specRate / 100))
  const afterSpec = afterSvc + specAmt
  const vatAmt = Math.round(afterSpec * (vatRate / 100))
  const finalPrice = afterSpec + vatAmt

  serviceChargeAmount.value = svcAmt
  specialTaxAmount.value = specAmt
  taxAmount.value = vatAmt
  price.value = finalPrice
}

const calculateOriginalFromPrice = () => {
  const finalPrice = Math.round(Number(price.value) || 0)
  const svcRate = Number(serviceChargePercent.value) || 0
  const specRate = Number(specialTaxPercent.value) || 0
  const vatRate = Number(taxPercent.value) || 0

  const multiplier = (1 + svcRate / 100) * (1 + specRate / 100) * (1 + vatRate / 100)
  const orig = multiplier > 0 ? Math.round(finalPrice / multiplier) : 0

  originalAmount.value = orig
  
  const svcAmt = Math.round(orig * (svcRate / 100))
  const afterSvc = orig + svcAmt
  const specAmt = Math.round(afterSvc * (specRate / 100))
  const afterSpec = afterSvc + specAmt
  const vatAmt = Math.round(afterSpec * (vatRate / 100))

  serviceChargeAmount.value = svcAmt
  specialTaxAmount.value = specAmt
  taxAmount.value = vatAmt
}

function getLocationsForOutlet(outletCode) {
  return allLocations.value.filter(loc => loc.outlet_code === outletCode)
}

const selectAllLocationsForOutlet = (outletCode) => {
  const s = outletSettings.value[outletCode]
  if (!s) return
  const locs = getLocationsForOutlet(outletCode)
  s.selectedCounterOutlets = locs.map(l => l.id)
}

const toggleOutletExpansion = (outletCode) => {
  const s = outletSettings.value[outletCode]
  if (!s) return
  s.is_expanded = !s.is_expanded
  if (s.is_expanded && (!s.selectedCounterOutlets || s.selectedCounterOutlets.length === 0)) {
    selectAllLocationsForOutlet(outletCode)
  }
}

watch([comboItems, isGetPriceFromItems, serviceChargePercent, specialTaxPercent, taxPercent], () => {
  if (isGetPriceFromItems.value) {
    let totalOrig = 0
    comboItems.value.forEach(item => {
      totalOrig += (Number(item.childProduct?.original_amount) || 0) * (Number(item.quantity) || 1)
    })
    originalAmount.value = totalOrig
    calculateAmountsFromOriginal()
  }
}, { deep: true })

const toggleOutletPriceUpdate = (outletCode) => {
  const s = outletSettings.value[outletCode]
  if (!s) return
  s.update_price = !s.update_price
  if (s.update_price && (!s.original_amount || s.original_amount === 0)) {
    s.original_amount = Math.round(Number(originalAmount.value) || 0)
    s.service_charge_percent = Number(serviceChargePercent.value) || 0
    s.tax_percent = Number(taxPercent.value) || 0
    s.special_tax_percent = Number(specialTaxPercent.value) || 0
    s.price = Math.round(Number(price.value) || 0)
  }
}

const toggleOutletComboPriceUpdate = (outletCode) => {
  const s = outletSettings.value[outletCode]
  if (!s) return
  s.update_combo_price = !s.update_combo_price
  if (s.update_combo_price && (!s.combo_original || s.combo_original === 0)) {
    s.combo_original = Math.round(Number(originalAmount.value) || 0)
    s.combo_service = Number(serviceChargePercent.value) || 0
    s.combo_tax = Number(taxPercent.value) || 0
    s.combo_special = Number(specialTaxPercent.value) || 0
    s.combo_price = Math.round(Number(price.value) || 0)
  }
}

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

const calculateOutletOriginalFromPrice = (outletCode, type = 'outlet') => {
  const s = outletSettings.value[outletCode]
  if (!s) return

  if (type === 'outlet') {
    const finalPrice = Math.round(Number(s.price) || 0)
    const svcRate = Number(s.service_charge_percent) || 0
    const specRate = Number(s.special_tax_percent) || 0
    const vatRate = Number(s.tax_percent) || 0

    const multiplier = (1 + svcRate / 100) * (1 + specRate / 100) * (1 + vatRate / 100)
    s.original_amount = multiplier > 0 ? Math.round(finalPrice / multiplier) : 0
  } else {
    const finalPrice = Math.round(Number(s.combo_price) || 0)
    const svcRate = Number(s.combo_service) || 0
    const specRate = Number(s.combo_special) || 0
    const vatRate = Number(s.combo_tax) || 0

    const multiplier = (1 + svcRate / 100) * (1 + specRate / 100) * (1 + vatRate / 100)
    s.combo_original = multiplier > 0 ? Math.round(finalPrice / multiplier) : 0
  }
}

const toggleCategoryCollapse = (catId) => {
  collapsedCategories.value[catId] = !collapsedCategories.value[catId]
}

const selectedCategoryName = computed(() => {
  const found = props.categoriesList.find(cat => cat.id === productCategoryId.value)
  return found ? found.name : 'Chọn loại thực đơn'
})

const toggleSelectAllPrinters = () => {
  if (selectedPrinterIds.value.length === printersList.value.length) {
    selectedPrinterIds.value = []
  } else {
    selectedPrinterIds.value = printersList.value.map(p => p.id)
  }
}

const toggleSelectPrinter = (id) => {
  const index = selectedPrinterIds.value.indexOf(id)
  if (index > -1) {
    selectedPrinterIds.value.splice(index, 1)
  } else {
    selectedPrinterIds.value.push(id)
  }
}

const isAllPrintersSelected = computed(() => {
  return printersList.value.length > 0 && selectedPrinterIds.value.length === printersList.value.length
})

const selectedPrintersText = computed(() => {
  if (selectedPrinterIds.value.length === 0) return 'Chọn máy in'
  if (isAllPrintersSelected.value) return 'Tất cả máy in'
  
  const names = selectedPrinterIds.value
    .map(id => {
      const found = printersList.value.find(p => p.id === id)
      return found ? found.name : ''
    })
    .filter(Boolean)
  return names.join(', ')
})

onMounted(async () => {
  isLoading.value = true
  // Load dynamic hotel services
  try {
    const res = await http.get('/hotel-services')
    hotelServices.value = res.data?.data || []
    if (hotelServices.value.length === 0) {
      hotelServices.value = [
        { id: 1, code: 'Ăn uống', name: 'Ăn uống' },
        { id: 2, code: 'Dịch vụ buồng', name: 'Dịch vụ buồng' },
        { id: 3, code: 'Dịch vụ khác', name: 'Dịch vụ khác' }
      ]
    }
  } catch (err) {
    console.error('Lỗi khi lấy nhóm dịch vụ:', err)
    hotelServices.value = [
      { id: 1, code: 'Ăn uống', name: 'Ăn uống' },
      { id: 2, code: 'Dịch vụ buồng', name: 'Dịch vụ buồng' },
      { id: 3, code: 'Dịch vụ khác', name: 'Dịch vụ khác' }
    ]
  }

  // Load dynamic locations/counters
  try {
    const locRes = await http.get('/fb-locations')
    allLocations.value = locRes.data?.data || []
  } catch (err) {
    console.error('Lỗi khi lấy danh sách khu vực:', err)
  }

  // Load dynamic printers
  try {
    const printerRes = await http.get('/fb-printers')
    printersList.value = Array.isArray(printerRes.data) ? printerRes.data : (printerRes.data?.data || [])
  } catch (err) {
    console.error('Lỗi khi lấy danh sách máy in:', err)
  }

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
      selectedCounterOutlets: getLocationsForOutlet(ot.code).map(l => l.id)
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
    serviceGroup.value = props.product.service_group || ''
    unitId.value = props.product.unit_id || ''
    barcode.value = props.product.barcode || '0000000000000'
    note.value = props.product.note || ''
    
    price.value = Math.round(Number(props.product.price) || 0)
    originalAmount.value = Math.round(Number(props.product.original_amount) || 0)
    serviceChargePercent.value = Number(props.product.service_charge_percent) || 0
    serviceChargeAmount.value = Math.round(Number(props.product.service_charge_amount) || 0)
    taxPercent.value = Number(props.product.tax_percent) || 0
    taxAmount.value = Math.round(Number(props.product.tax_amount) || 0)
    specialTaxPercent.value = Number(props.product.special_tax_percent) || 0
    specialTaxAmount.value = Math.round(Number(props.product.special_tax_amount) || 0)

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

    // New Fields
    entranceIp.value = props.product.entrance_ip || ''
    entranceGateTicketType.value = props.product.entrance_gate_ticket_type || 0
    exchangeLimitHours.value = props.product.exchange_limit_hours || 0
    
    // Combo new configs
    isGetPriceFromItems.value = !!props.product.is_get_price_from_items
    isCheckCombo.value = !!props.product.is_check_combo
    comboMaxItems.value = props.product.combo_max_items !== undefined && props.product.combo_max_items !== null ? Number(props.product.combo_max_items) : 1
    isFixedPrice.value = !!props.product.is_fixed_price
    isPrintOneTicket.value = !!props.product.is_print_one_ticket
    ticketType.value = props.product.ticket_type || ''
    isInStock.value = props.product.is_in_stock !== undefined && props.product.is_in_stock !== null ? props.product.is_in_stock : 1
    
    let pIds = []
    try {
      if (props.product.fb_printer_ids) {
        pIds = typeof props.product.fb_printer_ids === 'string' ? JSON.parse(props.product.fb_printer_ids) : props.product.fb_printer_ids
      }
    } catch (e) {
      console.error('Error parsing fb_printer_ids:', e)
    }
    selectedPrinterIds.value = Array.isArray(pIds) ? pIds.map(Number) : []

    if (props.product.image) {
      imagePreview.value = getImageUrl(props.product.image)
    }

    // Populate saved outlet prices
    if (props.product.outlet_prices && Array.isArray(props.product.outlet_prices)) {
      props.product.outlet_prices.forEach(op => {
        if (outletSettings.value[op.outlet_code]) {
          outletSettings.value[op.outlet_code].is_active = !!op.is_active
          outletSettings.value[op.outlet_code].price = Math.round(Number(op.price) || 0)
          outletSettings.value[op.outlet_code].original_amount = Math.round(Number(op.original_amount) || 0)
          outletSettings.value[op.outlet_code].service_charge_percent = Number(op.service_charge_percent) || 0
          outletSettings.value[op.outlet_code].tax_percent = Number(op.tax_percent) || 0
          outletSettings.value[op.outlet_code].special_tax_percent = Number(op.special_tax_percent) || 0
          
          outletSettings.value[op.outlet_code].combo_price = Math.round(Number(op.combo_price) || 0)
          outletSettings.value[op.outlet_code].combo_original = Math.round(Number(op.combo_original) || 0)
          outletSettings.value[op.outlet_code].combo_service = Number(op.combo_service) || 0
          outletSettings.value[op.outlet_code].combo_tax = Number(op.combo_tax) || 0
          outletSettings.value[op.outlet_code].combo_special = Number(op.combo_special) || 0
          
          outletSettings.value[op.outlet_code].update_price = !!op.update_price
          outletSettings.value[op.outlet_code].update_combo_price = !!op.update_combo_price
          outletSettings.value[op.outlet_code].is_expanded = !!op.is_expanded
          
          let counters = []
          try {
            if (op.selectedCounterOutlets) {
              counters = typeof op.selectedCounterOutlets === 'string' ? JSON.parse(op.selectedCounterOutlets) : op.selectedCounterOutlets
            }
          } catch (e) {
            console.error(e)
          }
          outletSettings.value[op.outlet_code].selectedCounterOutlets = Array.isArray(counters) ? counters : []
        }
      })
    }

    // Populate combo items
    if (props.product.combo_items && Array.isArray(props.product.combo_items)) {
      comboItems.value = props.product.combo_items.map(ci => ({
        child_id: ci.child_id,
        quantity: ci.quantity,
        price: Math.round(Number(ci.price) || 0),
        childProduct: props.productsList.find(p => p.id === ci.child_id)
      }))
    }
  } else {
    // defaults for create mode
    serviceGroup.value = hotelServices.value[0]?.code || ''
  }
  isLoading.value = false
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
    item.price = Math.round(Number(child.price) || 0)
  }
}

const handleAddSelectedComboItems = (selectedProducts) => {
  const selectedIds = selectedProducts.map(p => p.id)
  
  // Xóa những món không còn được chọn
  comboItems.value = comboItems.value.filter(item => selectedIds.includes(Number(item.child_id)))
  
  // Thêm món mới
  selectedProducts.forEach(product => {
    const exists = comboItems.value.find(item => Number(item.child_id) === product.id)
    if (!exists) {
      comboItems.value.push({
        child_id: product.id,
        quantity: 1,
        price: Math.round(Number(product.price) || 0),
        childProduct: product
      })
    }
  })
  
  showSelectComboModal.value = false
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

  // New fields
  formData.append('entrance_ip', entranceIp.value.trim())
  formData.append('entrance_gate_ticket_type', String(entranceGateTicketType.value))
  formData.append('exchange_limit_hours', String(exchangeLimitHours.value))
  formData.append('is_fixed_price', isFixedPrice.value ? '1' : '0')
  formData.append('is_print_one_ticket', isPrintOneTicket.value ? '1' : '0')
  formData.append('ticket_type', ticketType.value)
  formData.append('is_in_stock', String(isInStock.value))
  formData.append('fb_printer_ids', JSON.stringify(selectedPrinterIds.value))
  
  formData.append('is_get_price_from_items', isGetPriceFromItems.value ? '1' : '0')
  formData.append('is_check_combo', isCheckCombo.value ? '1' : '0')
  formData.append('combo_max_items', String(comboMaxItems.value))

  if (imageFile.value) {
    formData.append('image', imageFile.value)
  } else if (!imagePreview.value && props.product && props.product.image) {
    formData.append('remove_image', '1')
  }

  // Format outlet settings
  const formattedOutletSettings = []
  Object.keys(outletSettings.value).forEach(code => {
    const s = outletSettings.value[code]

    // Default logic
    let final_combo_price = s.update_combo_price ? s.combo_price : price.value
    let final_combo_original = s.update_combo_price ? s.combo_original : originalAmount.value
    let final_combo_service = s.update_combo_price ? s.combo_service : serviceChargePercent.value
    let final_combo_tax = s.update_combo_price ? s.combo_tax : taxPercent.value
    let final_combo_special = s.update_combo_price ? s.combo_special : specialTaxPercent.value

    if (isCombo.value) {
      if (s.update_combo_price) {
        final_combo_price = s.combo_price
        final_combo_original = s.combo_original
        final_combo_service = s.combo_service
        final_combo_tax = s.combo_tax
        final_combo_special = s.combo_special
      } else if (s.update_price && !s.update_combo_price) {
        final_combo_price = s.price
        final_combo_original = s.original_amount
        final_combo_service = s.service_charge_percent
        final_combo_tax = s.tax_percent
        final_combo_special = s.special_tax_percent
      }
    }

    formattedOutletSettings.push({
      outlet_code: code,
      is_active: s.is_active,
      price: s.update_price ? s.price : price.value,
      original_amount: s.update_price ? s.original_amount : originalAmount.value,
      service_charge_percent: s.update_price ? s.service_charge_percent : serviceChargePercent.value,
      tax_percent: s.update_price ? s.tax_percent : taxPercent.value,
      special_tax_percent: s.update_price ? s.special_tax_percent : specialTaxPercent.value,
      
      combo_price: final_combo_price,
      combo_original: final_combo_original,
      combo_service: final_combo_service,
      combo_tax: final_combo_tax,
      combo_special: final_combo_special,
      
      update_price: s.update_price,
      update_combo_price: s.update_combo_price,
      is_expanded: s.is_expanded,
      selectedCounterOutlets: s.selectedCounterOutlets || []
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
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-7xl overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs relative">
      
      <!-- Loading overlay -->
      <div v-if="isLoading" class="absolute inset-0 z-50 flex items-center justify-center bg-white/70 backdrop-blur-sm">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>

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
            <div class="mt-4 flex flex-col items-center w-full border border-slate-100 bg-slate-50/50 p-2.5 rounded-xl text-center">
              <span class="text-[9px] font-bold text-slate-700 tracking-wider mb-1 block uppercase truncate max-w-[220px]">{{ name || 'TÊN MÓN ĂN' }}</span>
              <!-- Actual Barcode rendered via JsBarcode -->
              <div class="w-full flex justify-center mb-1 bg-white p-1 rounded border border-slate-150">
                <svg id="barcode-canvas" class="max-w-full h-8"></svg>
              </div>
              <span class="text-[9px] font-bold text-slate-500 tracking-widest">{{ productCode }}{{ barcode || '0000000000000' }}</span>
              <span class="text-[10px] font-extrabold text-slate-800 mt-1 block">Giá: {{ price.toLocaleString() }}</span>
            </div>
            <div class="w-full mt-4 flex flex-col gap-3">
              <!-- Mở bán, Có cồn & Tồn kho row -->
              <div class="flex items-center gap-6 mt-1 flex-wrap">
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Mở bán</span>
                  <button type="button" @click="isActive = !isActive" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isActive ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isActive ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Có cồn</span>
                  <button type="button" @click="isAlcohol = !isAlcohol" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isAlcohol ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isAlcohol ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Tồn kho</span>
                  <button type="button" @click="trackStock = !trackStock" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="trackStock ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="trackStock ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>
              </div>

              <!-- Checkboxes row -->
              <div class="grid grid-cols-2 gap-y-2 gap-x-4 mt-2">
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                  <input type="checkbox" v-model="flexiblePrice" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Thời giá
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                  <input type="checkbox" v-model="openKey" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Open item
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                  <input type="checkbox" v-model="isContra" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Is Contra
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                  <input type="checkbox" v-model="noReinvest" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  Không tái đầu tư
                </label>
              </div>

              <!-- Vé vào cổng Switch -->
              <div class="flex items-center justify-between mt-1.5 border-t border-slate-50 pt-2">
                <span class="font-bold text-slate-500 text-xs">Vé vào cổng</span>
                <button type="button" @click="isGateTicket = !isGateTicket" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isGateTicket ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isGateTicket ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <!-- Phiếu đổi món Switch -->
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-500 text-xs">Phiếu đổi món (Dish Exchange QR)</span>
                <button type="button" @click="isDishExchange = !isDishExchange" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isDishExchange ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isDishExchange ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <!-- Vé in trước Switch -->
              <div class="flex items-center justify-between mt-1">
                <span class="font-bold text-slate-500 text-xs">Vé in trước</span>
                <button type="button" @click="isPrePrinted = !isPrePrinted" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isPrePrinted ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isPrePrinted ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                </button>
              </div>

              <!-- Processing and Serving Time (Always rendered at the bottom) -->
              <div class="grid grid-cols-2 gap-4 mt-2 border-t border-slate-100 pt-3">
                <div class="flex flex-col gap-1.5 justify-between">
                  <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap block min-h-[16px]">Thời gian xử lý</span>
                  <input type="number" v-model.number="processingTime" min="0" class="border border-slate-250 rounded-lg px-2.5 py-1 text-center font-bold focus:outline-none text-xs h-[36px] w-full" />
                </div>
                <div class="flex flex-col gap-1.5 justify-between">
                  <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide whitespace-nowrap block min-h-[16px]">Thời gian phục vụ</span>
                  <input type="number" v-model.number="servingTime" min="0" class="border border-slate-250 rounded-lg px-2.5 py-1 text-center font-bold focus:outline-none text-xs h-[36px] w-full" />
                </div>
              </div>

            </div>
          </div>

          <!-- CỘT GIỮA (FLEX-1): Text Info Fields -->
          <div class="flex-1 flex flex-col gap-4">
            
            <div class="grid grid-cols-2 gap-4">
              <!-- Category select -->
              <div class="flex flex-col gap-1 relative">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Loại thực đơn *</label>
                <div class="flex gap-1.5 items-center">
                  <!-- Custom Tree-select Trigger -->
                  <div 
                    @click="isCategoryDropdownOpen = !isCategoryDropdownOpen"
                    class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold bg-white cursor-pointer flex justify-between items-center h-[32px] select-none"
                  >
                    <span class="truncate" :class="productCategoryId ? 'text-slate-800 font-bold' : 'text-slate-400'">
                      {{ selectedCategoryName }}
                    </span>
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 transition" :class="isCategoryDropdownOpen ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                  <button 
                    type="button" 
                    @click="$emit('openAddCategory')" 
                    class="p-1.5 border border-sky-100 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-full transition shrink-0"
                    title="Thêm loại thực đơn mới"
                  >
                    <span class="text-sm font-bold leading-none">+</span>
                  </button>
                </div>

                <!-- Category Popover Backdrop click away -->
                <div v-if="isCategoryDropdownOpen" @click="isCategoryDropdownOpen = false" class="fixed inset-0 z-10"></div>

                <!-- Custom Tree-select Popover -->
                <div 
                  v-if="isCategoryDropdownOpen" 
                  class="absolute left-0 right-0 mt-12 bg-white border border-slate-200 rounded-lg shadow-lg z-20 max-h-60 overflow-y-auto p-1.5 flex flex-col gap-0.5 animate-fade-in"
                >
                  <div 
                    v-if="visibleCategories.length === 0" 
                    class="text-slate-400 text-center py-2 font-semibold text-xs"
                  >
                    Không có loại thực đơn nào
                  </div>
                  <div 
                    v-for="cat in visibleCategories" 
                    :key="cat.id"
                    class="flex items-center rounded-md hover:bg-slate-50 transition cursor-pointer text-xs h-[30px] pr-2 select-none group"
                    :style="{ paddingLeft: (cat.depth * 14) + 'px' }"
                    :class="productCategoryId === cat.id ? 'bg-sky-50 text-sky-600 font-bold' : 'text-slate-700'"
                  >
                    <!-- Expand/Collapse arrow icon -->
                    <button 
                      type="button"
                      v-if="cat.hasChildren"
                      @click.stop="toggleCategoryCollapse(cat.id)"
                      class="w-5 h-5 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded hover:bg-slate-200/50 shrink-0 mr-1 transition"
                    >
                      <svg 
                        class="w-3.5 h-3.5 transform transition" 
                        :class="cat.isCollapsed ? '' : 'rotate-90'" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                      </svg>
                    </button>
                    <span v-else class="w-5 shrink-0 mr-1"></span>

                    <!-- Label click to select -->
                    <span 
                      @click="productCategoryId = cat.id; isCategoryDropdownOpen = false"
                      class="flex-1 truncate"
                    >
                      {{ cat.name }}
                    </span>
                  </div>
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

            <!-- Row 2: Quản lý tồn kho & Tên * -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Quản lý tồn kho dropdown -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Quản lý tồn kho</label>
                <select 
                  v-model="isInStock" 
                  class="w-full border border-amber-200/60 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none bg-amber-50/30 hover:bg-amber-50/40 focus:bg-amber-50/60 h-[32px]"
                >
                  <option :value="1">Theo dõi thành phần</option>
                  <option :value="2">Theo dõi tồn kho</option>
                  <option :value="0">Không theo dõi</option>
                </select>
              </div>

              <!-- Product Name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên *</label>
                <input 
                  type="text" 
                  v-model="name" 
                  class="w-full border border-amber-200/60 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none bg-amber-50/30 hover:bg-amber-50/40 focus:bg-amber-50/60 font-bold h-[32px]"
                  placeholder="Nhập tên món ăn"
                />
              </div>
            </div>

            <!-- Row 3: Tên tiếng Anh & Nhóm dịch vụ -->
            <div class="grid grid-cols-2 gap-4">
              <!-- English Name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên tiếng Anh</label>
                <input 
                  type="text" 
                  v-model="nameEn" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none h-[32px]"
                  placeholder="English Name"
                />
              </div>

              <!-- Service Group dropdown selection -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Nhóm dịch vụ</label>
                <select 
                  v-model="serviceGroup" 
                  class="w-full border border-amber-200/60 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none bg-amber-50/30 hover:bg-amber-50/40 focus:bg-amber-50/60 h-[32px]"
                >
                  <option value="">Chọn nhóm dịch vụ</option>
                  <option v-for="svc in hotelServices" :key="svc.id" :value="svc.code || svc.name">
                    {{ svc.name }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Row 4: Tên Xuất Hóa Đơn VAT, ĐVT, Mã vạch (Shared horizontal line 2:1:1 ratio) -->
            <div class="grid grid-cols-4 gap-4">
              <!-- VAT bill name (2 cols) -->
              <div class="col-span-2 flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên Xuất Hóa Đơn VAT</label>
                <input 
                  type="text" 
                  v-model="vatBillingName" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none h-[32px]"
                  placeholder="Hóa đơn VAT"
                />
              </div>

              <!-- UOM selection (1 col) -->
              <div class="col-span-1 flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">ĐVT</label>
                <select 
                  v-model="unitId" 
                  class="w-full border border-amber-200/60 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none bg-amber-50/30 hover:bg-amber-50/40 focus:bg-amber-50/60 h-[32px]"
                >
                  <option value="">Chọn ĐVT</option>
                  <option v-for="u in unitsList" :key="u.id" :value="u.id">
                    {{ u.name || u.ten }}
                  </option>
                </select>
              </div>

              <!-- Barcode input (1 col) -->
              <div class="col-span-1 flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mã vạch</label>
                <input 
                  type="text" 
                  v-model="barcode" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none h-[32px]"
                  placeholder="Barcode number"
                />
              </div>
            </div>

            <!-- Row 5: Short Name & Máy in -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Short Name -->
              <div class="flex flex-col gap-1">
                <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên viết tắt (Short Name)</label>
                <input 
                  type="text" 
                  v-model="shortName" 
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-semibold focus:outline-none h-[32px]"
                  placeholder="Short Name viết tắt"
                />
              </div>

              <!-- Máy in Dropdown (Multi-select) -->
              <div class="flex flex-col gap-1 relative">
                <span class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Máy in</span>
                <div 
                  @click="isPrinterDropdownOpen = !isPrinterDropdownOpen"
                  class="w-full border border-slate-250 rounded-lg px-2.5 py-1 text-xs font-semibold bg-white cursor-pointer flex justify-between items-center h-[32px] select-none"
                >
                  <span class="truncate" :class="selectedPrinterIds.length ? 'text-slate-800 font-bold' : 'text-slate-400'">
                    {{ selectedPrintersText }}
                  </span>
                  <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 transition" :class="isPrinterDropdownOpen ? 'transform rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>

                <!-- Backdrop click away for printer drop -->
                <div v-if="isPrinterDropdownOpen" @click="isPrinterDropdownOpen = false" class="fixed inset-0 z-10"></div>

                <!-- Printer Popover Options -->
                <div 
                  v-if="isPrinterDropdownOpen" 
                  class="absolute left-0 right-0 mt-12 bg-white border border-slate-200 rounded-lg shadow-lg z-20 max-h-60 overflow-y-auto p-1.5 flex flex-col gap-0.5 animate-fade-in"
                >
                  <!-- Select All Option -->
                  <label 
                    class="flex items-center gap-2 rounded-md hover:bg-slate-50 px-2 py-1.5 transition cursor-pointer text-xs font-bold text-slate-800 select-none border-b border-slate-100 pb-2 mb-1"
                  >
                    <input 
                      type="checkbox" 
                      :checked="isAllPrintersSelected"
                      @change="toggleSelectAllPrinters"
                      class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer"
                    />
                    <span>Chọn tất cả</span>
                  </label>

                  <!-- Printer List Option -->
                  <div 
                    v-if="printersList.length === 0" 
                    class="text-slate-400 text-center py-2 font-semibold text-xs"
                  >
                    Không tìm thấy máy in nào
                  </div>
                  <label 
                    v-for="p in printersList" 
                    :key="p.id"
                    class="flex items-center gap-2 rounded-md hover:bg-slate-50 px-2 py-1.5 transition cursor-pointer text-xs select-none"
                    :class="selectedPrinterIds.includes(p.id) ? 'bg-sky-50 text-sky-600 font-bold' : 'text-slate-700'"
                  >
                    <input 
                      type="checkbox" 
                      :checked="selectedPrinterIds.includes(p.id)"
                      @change="toggleSelectPrinter(p.id)"
                      class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer"
                    />
                    <span class="truncate">{{ p.name }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Row 6: Mô tả (Note) -->
            <div class="flex flex-col gap-1 mt-2">
              <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mô tả</label>
              <textarea 
                v-model="note" 
                class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none h-[60px] resize-y"
                placeholder="Nhập mô tả món ăn"
              ></textarea>
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

            <!-- Dynamic conditional fields moved to middle column to optimize space -->
            <div class="mt-4 flex flex-col gap-4 border-t border-slate-100 pt-4 w-full" v-if="isGateTicket || isDishExchange || isPrePrinted">
              
              <!-- Phiếu đổi món fields -->
              <div v-if="isDishExchange" class="flex flex-col gap-1 w-full bg-slate-50/50 p-3 border border-slate-100 rounded-xl">
                <span class="text-xs font-bold text-slate-650">Thời hạn đổi (Giờ) - 0: Trong ngày</span>
                <div class="flex items-center justify-between border border-slate-200 rounded-lg p-1 bg-white h-[36px] w-full">
                  <button 
                    type="button"
                    @click.prevent="exchangeLimitHours > 0 ? exchangeLimitHours-- : null" 
                    class="w-10 h-full flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded text-base"
                  >-</button>
                  <span class="font-bold text-sm">{{ exchangeLimitHours }}</span>
                  <button 
                    type="button"
                    @click.prevent="exchangeLimitHours++" 
                    class="w-10 h-full flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded text-base"
                  >+</button>
                </div>
              </div>

              <!-- Vé vào cổng fields -->
              <div v-if="isGateTicket" class="grid grid-cols-2 gap-4 w-full bg-slate-50/50 p-3 border border-slate-100 rounded-xl">
                <div class="flex flex-col gap-1">
                  <span class="text-xs font-bold text-slate-600">Cổng vào IP</span>
                  <button type="button" @click.prevent="entranceIp = '127.0.0.1'" class="flex items-center justify-center gap-1.5 bg-sky-400 hover:bg-sky-500 text-white py-1.5 px-3 rounded-lg font-bold text-xs transition h-[34px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Thêm IP
                  </button>
                </div>
                <div class="flex flex-col gap-1">
                  <span class="text-xs font-bold text-slate-600">Cổng vào vé phát hành</span>
                  <select v-model="entranceGateTicketType" class="w-full border border-slate-250 rounded-lg px-2.5 py-1.5 text-xs bg-white font-semibold focus:outline-none h-[34px]">
                    <option :value="0">Select: 0</option>
                    <option :value="1">Select: 1</option>
                    <option :value="2">Select: 2</option>
                  </select>
                </div>
              </div>

              <!-- Vé in trước fields -->
              <div v-if="isPrePrinted" class="flex flex-col gap-2 w-full bg-slate-50/50 p-3 border border-slate-100 rounded-xl">
                <div class="flex flex-col gap-1">
                  <span class="text-xs font-bold text-slate-600">Loại vé</span>
                  <select v-model="ticketType" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white font-semibold focus:outline-none h-[34px]">
                    <option value="">--Chọn--</option>
                    <option value="standard">Vé thường</option>
                    <option value="vip">Vé VIP</option>
                  </select>
                </div>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs mt-1">
                  <input type="checkbox" v-model="isPrintOneTicket" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                  In vé theo bill
                </label>
              </div>

            </div>

          </div>

          <!-- CỘT RIGHT (W-80): Pricing (Matching Image 4 Right Column) -->
          <div class="w-full md:w-80 shrink-0 flex flex-col gap-4 border-l border-slate-100 pl-6 bg-slate-50/20 p-4 rounded-xl">
            <!-- Title -->
            <div class="flex items-center gap-1 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-2">
              <!-- Help icon -->
              <span class="w-4 h-4 rounded-full border border-sky-400 text-sky-500 flex items-center justify-center font-bold">?</span>
              Giá & Thuế phí
            </div>

            <!-- Original Amount -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Giá gốc</label>
              <div class="flex items-center relative">
                <input 
                  type="number" 
                  v-model.number="originalAmount" 
                  @input="calculateAmountsFromOriginal"
                  :readonly="isGetPriceFromItems"
                  class="w-full border border-slate-200 rounded-lg pl-2.5 pr-8 py-1.5 font-bold focus:outline-none text-right"
                  :class="isGetPriceFromItems ? 'bg-slate-100/70 text-slate-400' : 'bg-slate-50 text-slate-700'"
                />
                <span class="absolute right-3 font-bold text-slate-400">đ</span>
              </div>
            </div>

            <!-- Service Charge % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Phí dịch vụ (%)</label>
              <div class="flex gap-2 items-center">
                <input 
                  type="number" 
                  v-model.number="serviceChargePercent" 
                  @input="calculateAmountsFromOriginal"
                  class="w-16 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none text-center font-semibold shrink-0"
                />
                <div class="flex-1 min-w-0 border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-500 font-semibold text-right truncate">
                  {{ serviceChargeAmount.toLocaleString() }}đ
                </div>
              </div>
            </div>

            <!-- Special Tax % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Thuế Đặc Biệt (%)</label>
              <div class="flex gap-2 items-center">
                <input 
                  type="number" 
                  v-model.number="specialTaxPercent" 
                  @input="calculateAmountsFromOriginal"
                  class="w-16 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none text-center font-semibold shrink-0"
                />
                <div class="flex-1 min-w-0 border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-500 font-semibold text-right truncate">
                  {{ specialTaxAmount.toLocaleString() }}đ
                </div>
              </div>
            </div>

            <!-- VAT % & calculated cash -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">VAT (%)</label>
              <div class="flex gap-2 items-center">
                <input 
                  type="number" 
                  v-model.number="taxPercent" 
                  @input="calculateAmountsFromOriginal"
                  class="w-16 border border-slate-200 rounded-lg px-2 py-1.5 focus:outline-none text-center font-semibold shrink-0"
                />
                <div class="flex-1 min-w-0 border border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-500 font-semibold text-right truncate">
                  {{ taxAmount.toLocaleString() }}đ
                </div>
              </div>
            </div>

            <!-- Final Price -->
            <div class="flex flex-col gap-1">
              <label class="font-extrabold text-sky-600">Đơn giá bán</label>
              <div class="flex items-center relative">
                <input 
                  type="number" 
                  v-model.number="price" 
                  @input="calculateOriginalFromPrice"
                  :readonly="isGetPriceFromItems"
                  class="w-full border border-sky-200 rounded-lg pl-2.5 pr-8 py-1.5 font-extrabold focus:outline-none text-right"
                  :class="isGetPriceFromItems ? 'bg-sky-50/50 text-sky-500' : 'bg-sky-50/20 text-sky-700'"
                />
                <span class="absolute right-3 font-bold text-sky-600">đ</span>
              </div>
            </div>

            <!-- Huge display box of final price -->
            <div class="mt-4 bg-[#fefecc] border border-yellow-200/80 p-4 rounded-xl text-center font-bold text-slate-800 text-base shadow-inner">
              {{ price.toLocaleString() }} đ
            </div>

          </div>

        </div>

        <!-- TAB 2: ĐƠN GIÁ THEO OUTLET (Hiển thị dạng dòng nối tiếp) -->
        <div v-show="activeTab === 'outlets'" class="flex flex-col gap-4">
          <div v-if="outletsList.length > 0" class="flex flex-col gap-4">
            <div 
              v-for="ot in outletsList" 
              :key="ot.code" 
              class="border border-slate-200 rounded-xl bg-white p-4 shadow-sm flex flex-col gap-3"
            >
              <!-- Dòng 1: Header điều khiển của Outlet -->
              <div class="flex flex-wrap items-center gap-6 pb-2 border-b border-slate-100">
                <!-- Tên Outlet -->
                <div class="flex items-center gap-1.5 min-w-[120px]">
                  <span class="font-extrabold text-sm text-slate-800">{{ ot.name }}</span>
                </div>

                <!-- Toggle Mở bán -->
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Mở bán</span>
                  <button 
                    type="button"
                    @click="outletSettings[ot.code].is_active = !outletSettings[ot.code].is_active" 
                    class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" 
                    :class="outletSettings[ot.code]?.is_active ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                  >
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[ot.code]?.is_active ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>

                <!-- Toggle Cập nhật giá outlet -->
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Cập nhật giá outlet</span>
                  <button 
                    type="button"
                    @click="toggleOutletPriceUpdate(ot.code)" 
                    class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" 
                    :class="outletSettings[ot.code]?.update_price ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                  >
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[ot.code]?.update_price ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>

                <!-- Toggle Cập nhật giá combo -->
                <div class="flex items-center gap-2">
                  <span class="font-bold text-slate-500 text-xs">Cập nhật giá combo</span>
                  <button 
                    type="button"
                    @click="toggleOutletComboPriceUpdate(ot.code)" 
                    class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" 
                    :class="outletSettings[ot.code]?.update_combo_price ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                  >
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[ot.code]?.update_combo_price ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>

                <!-- Toggle Mở rộng -->
                <div class="flex items-center gap-2 ml-auto">
                  <span class="font-bold text-slate-500 text-xs">Mở rộng (Khu vực)</span>
                  <button 
                    type="button"
                    @click="toggleOutletExpansion(ot.code)" 
                    class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" 
                    :class="outletSettings[ot.code]?.is_expanded ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                  >
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="outletSettings[ot.code]?.is_expanded ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
                  </button>
                </div>
              </div>

              <!-- Dòng 2: Form Cấu hình đơn giá (nếu bật update_price) -->
              <div v-if="outletSettings[ot.code]?.update_price" class="bg-amber-50/20 border border-amber-200/40 rounded-lg p-3">
                <div class="grid grid-cols-5 gap-3">
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Giá gốc</label>
                    <input type="number" v-model.number="outletSettings[ot.code].original_amount" @input="calculateOutletAmounts(ot.code, 'outlet')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none font-bold text-slate-700 bg-white h-[32px] text-xs text-center" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Phí dịch vụ (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].service_charge_percent" @input="calculateOutletAmounts(ot.code, 'outlet')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Thuế Đặc Biệt (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].special_tax_percent" @input="calculateOutletAmounts(ot.code, 'outlet')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">VAT (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].tax_percent" @input="calculateOutletAmounts(ot.code, 'outlet')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-extrabold text-sky-600 text-[10px] uppercase">Đơn giá</label>
                    <input type="number" v-model.number="outletSettings[ot.code].price" @input="calculateOutletOriginalFromPrice(ot.code, 'outlet')" class="border border-sky-200 rounded-lg px-2 py-1 focus:outline-none font-extrabold text-sky-700 bg-sky-50/20 h-[32px] text-xs text-center" />
                  </div>
                </div>
              </div>

              <!-- Dòng 3: Form Cấu hình Combo (nếu bật update_combo_price) -->
              <div v-if="outletSettings[ot.code]?.update_combo_price" class="bg-sky-50/20 border border-sky-200/40 rounded-lg p-3">
                <div class="grid grid-cols-5 gap-3">
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Giá gốc Combo</label>
                    <input type="number" v-model.number="outletSettings[ot.code].combo_original" @input="calculateOutletAmounts(ot.code, 'combo')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none font-bold text-slate-700 bg-white h-[32px] text-xs text-center" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Phí dịch vụ (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].combo_service" @input="calculateOutletAmounts(ot.code, 'combo')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">Thuế Đặc Biệt (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].combo_special" @input="calculateOutletAmounts(ot.code, 'combo')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-bold text-slate-500 text-[10px] uppercase">VAT (%)</label>
                    <input type="number" v-model.number="outletSettings[ot.code].combo_tax" @input="calculateOutletAmounts(ot.code, 'combo')" class="border border-slate-200 rounded-lg px-2 py-1 focus:outline-none h-[32px] text-xs text-center font-semibold text-slate-700" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <label class="font-extrabold text-sky-600 text-[10px] uppercase">Đơn giá Combo</label>
                    <input type="number" v-model.number="outletSettings[ot.code].combo_price" @input="calculateOutletOriginalFromPrice(ot.code, 'combo')" class="border border-sky-200 rounded-lg px-2 py-1 focus:outline-none font-extrabold text-sky-700 bg-sky-50/20 h-[32px] text-xs text-center" />
                  </div>
                </div>
              </div>

              <!-- Dòng 4: Cấu hình Khu vực áp dụng (nếu bật is_expanded) -->
              <div v-if="outletSettings[ot.code]?.is_expanded" class="bg-slate-50 border border-slate-200 rounded-lg p-3 flex flex-col gap-2">
                <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5">
                  <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Khu vực áp dụng giá đặc thù của Outlet</span>
                  <button 
                    type="button"
                    @click="selectAllLocationsForOutlet(ot.code)"
                    class="text-[10px] font-bold text-sky-600 hover:text-sky-700"
                  >
                    Chọn tất cả
                  </button>
                </div>
                <div class="flex flex-wrap gap-4">
                  <label 
                    v-for="loc in getLocationsForOutlet(ot.code)" 
                    :key="loc.id" 
                    class="flex items-center gap-1.5 font-bold text-slate-600 cursor-pointer hover:text-slate-800 text-[11px]"
                  >
                    <input 
                      type="checkbox" 
                      :value="loc.id" 
                      v-model="outletSettings[ot.code].selectedCounterOutlets"
                      class="w-4 h-4 rounded border-slate-350 accent-sky-500" 
                    />
                    {{ loc.name }}
                  </label>
                  <span v-if="getLocationsForOutlet(ot.code).length === 0" class="text-xs text-slate-400 italic">
                    Không có khu vực nào thuộc Outlet này.
                  </span>
                </div>
              </div>

              <!-- Thông báo mặc định nếu không set giá gì -->
              <div 
                v-if="!outletSettings[ot.code]?.update_price && !outletSettings[ot.code]?.update_combo_price" 
                class="text-xs text-slate-400 font-semibold italic text-center"
              >
                * Áp dụng giá mặc định từ Tab "Thông tin" cho outlet này.
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
            <div class="flex items-center gap-4">
              <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">Cấu hình món Combo</span>
              <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                <input type="checkbox" v-model="isGetPriceFromItems" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                Lấy thông tin giá từng món
              </label>
              <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-500 select-none text-xs">
                <input type="checkbox" v-model="isCheckCombo" class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                Check combo
              </label>
              <div class="flex items-center gap-1.5 ml-4">
                <span class="font-bold text-slate-500 text-xs">Số lượng tối đa được chọn</span>
                <input type="number" v-model.number="comboMaxItems" min="0" class="w-16 border border-slate-200 rounded px-2 py-0.5 text-center font-bold text-xs" />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-500">KÍCH HOẠT COMBO</span>
              <button @click="isCombo = !isCombo" class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none" :class="isCombo ? 'bg-[#78C5E7]' : 'bg-slate-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="isCombo ? 'translate-x-[18px]' : 'translate-x-1'"></span>
              </button>
            </div>
          </div>

          <div v-if="isCombo" class="flex flex-col gap-4">
            <div class="flex justify-end">
              <button 
                type="button" 
                @click="showSelectComboModal = true"
                class="bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-4 py-2 rounded-lg text-xs font-bold transition shadow-sm"
              >
                + Thêm thực đơn
              </button>
            </div>
            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
              <table class="w-full border-collapse bg-white">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200 text-left text-xs">
                    <th class="p-3 w-24">Mã</th>
                    <th class="p-3 min-w-[200px]">Tên</th>
                    <th class="p-3 w-24 text-center">Số lượng</th>
                    <th class="p-3 w-28 text-right">Giá gốc</th>
                    <th class="p-3 w-28 text-right">Đơn giá</th>
                    <th class="p-3 w-28 text-right">Phí phục vụ</th>
                    <th class="p-3 w-28 text-right">Thuế đặc biệt</th>
                    <th class="p-3 w-28 text-right">VAT</th>
                    <th class="p-3 w-16 text-center">Xóa</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <tr v-for="(item, idx) in comboItems" :key="idx" class="hover:bg-slate-50/50">
                    <td class="p-3 font-semibold text-slate-700">
                      {{ item.childProduct?.product_code || '-' }}
                    </td>
                    <td class="p-3 font-semibold text-slate-700">
                      {{ item.childProduct?.name || 'Unknown' }}
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
                      {{ Number((item.childProduct?.original_amount || 0) * item.quantity).toLocaleString() }}
                    </td>
                    <td class="p-3 text-right font-bold">
                      {{ Number((item.childProduct?.price || 0) * item.quantity).toLocaleString() }}
                    </td>
                    <td class="p-3 text-right">
                      {{ Number((item.childProduct?.service_charge_amount || 0) * item.quantity).toLocaleString() }}
                    </td>
                    <td class="p-3 text-right">
                      {{ Number((item.childProduct?.special_tax_amount || 0) * item.quantity).toLocaleString() }}
                    </td>
                    <td class="p-3 text-right">
                      {{ Number((item.childProduct?.tax_amount || 0) * item.quantity).toLocaleString() }}
                    </td>
                    <td class="p-3 text-center">
                      <button @click="removeComboRow(idx)" class="text-rose-500 hover:text-rose-600 transition">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="comboItems.length === 0">
                    <td colspan="9" class="p-8 text-center text-slate-400 font-semibold italic">
                      Chưa có thực đơn nào trong Combo. Vui lòng nhấn "Thêm thực đơn".
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
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

    <SelectComboItemsModal
      :show="showSelectComboModal"
      :initialSelected="comboItems.map(c => c.childProduct)"
      :categoriesList="categoriesList"
      :productsList="productsList"
      @close="showSelectComboModal = false"
      @save="handleAddSelectedComboItems"
    />
  </div>
</template>
