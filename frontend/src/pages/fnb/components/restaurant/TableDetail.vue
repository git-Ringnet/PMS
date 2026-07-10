<script setup>

import { ref, onMounted, computed, watch, onUnmounted } from 'vue'

import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute } from 'vue-router'

import { fetchProductCategories, fetchProducts, fetchPromotions, fetchUnitsOfMeasure } from '@/services/product-service'
import { fetchActiveOrders, transferTable, transferItems, syncOrders } from '@/services/outlet-service'

import CustomerInfoModal from './modals/CustomerInfoModal.vue'

import InternalNoteModal from './modals/InternalNoteModal.vue'

import OpenItemModal from './modals/OpenItemModal.vue'

import SplitByItemModal from './modals/SplitByItemModal.vue'

import SplitByAmountModal from './modals/SplitByAmountModal.vue'

import MergeBillsModal from './modals/MergeBillsModal.vue'

import TransferTableModal from './modals/TransferTableModal.vue'

import TransferItemsModal from './modals/TransferItemsModal.vue'

import PrintTicketsModal from './modals/PrintTicketsModal.vue'

import GuestCheckPrintTemplate from './print/GuestCheckPrintTemplate.vue'

import ComboSelectionModal from './modals/ComboSelectionModal.vue'

import { useUiStore } from '@/stores/ui-store'



const uiStore = useUiStore()

const route = useRoute()
const currentOutletCode = computed(() => route.query.outlet_code || null)

const getProductPrice = (prod) => {
  if (!currentOutletCode.value) return prod.price || 0;
  const outletPricesArray = prod.outlet_prices || prod.outletPrices;
  if (!outletPricesArray) return prod.price || 0;
  // Use op.outlet?.code since backend returns the outlet relation object
  const outletPrice = outletPricesArray.find(op => op.outlet?.code === currentOutletCode.value);
  if (outletPrice) {
    if (prod.is_combo && outletPrice.update_combo_price) {
      return outletPrice.combo_price || 0;
    }
    if (!prod.is_combo && outletPrice.update_price) {
      return outletPrice.price || 0;
    }
  }
  return prod.price || 0;
}

// activePromotions moved below activeBill



const props = defineProps({

  table: {

    type: Object,

    required: true

  }

})



const emit = defineEmits(['back', 'save', 'split-success', 'transfer-success', 'transfer-items-success'])



const showActionMenu = ref(false)



// Tabs for Bills
const isLoadingBills = ref(false)

const bills = ref(
  props.table.bills && props.table.bills.length > 0
    ? JSON.parse(JSON.stringify(props.table.bills))
    : [{ id: Date.now(), name: 'Bill 1', items: [] }]
)

const activeBillId = ref(bills.value[0].id)

const activeBill = computed(() => bills.value.find(b => b.id === activeBillId.value))



const addBill = () => {
  const newId = bills.value.length + 1
  const newBill = { id: newId, name: `Bill ${newId}`, items: [] }
  
  // Auto-apply promotion
  if (activePromotions.value) {
    const validAutoPromo = activePromotions.value.find(p => p.is_auto_apply);
    if (validAutoPromo) {
      newBill.promotionId = validAutoPromo.id;
    }
  }
  
  bills.value.push(newBill)
  activeBillId.value = newId
}



// Menu and Products
const isLoadingMenu = ref(false)

const categories = ref([])

const products = ref([])

const promotions = ref([])

const searchQuery = ref('')

const isSearchDropdownOpen = ref(false)

const selectedCategoryId = ref(null) // null = showing all root categories

const isCategoryDropdownOpen = ref(false)

const isDirty = ref(false)

const units = ref([])



const rootCategories = computed(() => {

  return categories.value.filter(c => !c.parent_id)

})

const getChildren = (parentId) => {

  return categories.value.filter(c => c.parent_id === parentId)

}

const selectCategory = (id) => {

  selectedCategoryId.value = id

  isCategoryDropdownOpen.value = false

  isSearchDropdownOpen.value = false

}



const getImageUrl = (path) => {

  if (!path) return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80' // default food image

  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path

  const isDev = import.meta.env.DEV

  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'

  const cleanPath = path.startsWith('/') ? path.substring(1) : path

  let finalPath = cleanPath

  if (!cleanPath.startsWith('storage/')) finalPath = 'storage/' + cleanPath

  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`

}



const loadMenuData = async () => {
  isLoadingMenu.value = true

  try {

    const [catRes, prodRes, promRes, unitRes] = await Promise.all([

      fetchProductCategories(),

      fetchProducts(),

      fetchPromotions(),

      fetchUnitsOfMeasure()

    ])

    categories.value = catRes.data.data || catRes.data || []

    products.value = prodRes.data.data || prodRes.data || []

    promotions.value = promRes.data.data || promRes.data || []
    units.value = unitRes.data.data || unitRes.data || []

    // If opening an empty table, auto-apply promotion
    if (bills.value.length === 1 && bills.value[0].items.length === 0 && !bills.value[0].promotionId) {
      const validAutoPromo = activePromotions.value.find(p => p.is_auto_apply);
      if (validAutoPromo) {
        bills.value[0].promotionId = validAutoPromo.id;
        if (activeBillId.value === bills.value[0].id) {
          billInfo.value.promotionId = validAutoPromo.id;
        }
      }
    }

  } catch (err) {

    console.error('Lỗi khi tải dữ liệu:', err)

  } finally {
    isLoadingMenu.value = false
  }

}



const getUnitName = (unitId) => {

  if (!unitId) return '-'

  const u = units.value.find(x => x.id === unitId)

  return u ? u.name : unitId

}

const loadBillsFromServer = async () => {
  if (props.table.status === 'empty') return
  isLoadingBills.value = true
  try {
    const res = await fetchActiveOrders(props.table.id)
    const orders = Array.isArray(res.data) ? res.data : (res.data?.orders || res.data?.data || [])
    if (orders.length > 0) {
      bills.value = orders.map((order, idx) => ({
        id: order.id,
        name: order.name || `Bill ${idx + 1}`,
        customerName: order.customerName || order.customer_name || '',
        customerPhone: order.customerPhone || order.customer_phone || '',
        customerEmail: order.customerEmail || order.customer_email || '',
        customerAddress: order.customerAddress || order.customer_address || '',
        guestCount: order.guestCount || order.guest_count || 0,
        publicNote: order.publicNote || order.public_note || '',
        internalNote: order.internalNote || order.internal_note || '',
        internalNoteDiscount: order.internalNoteDiscount || order.internal_note_discount || '',
        promotionId: order.promotionId || order.promotion_id,
        items: (order.items || []).map(i => ({
          id: i.id,
          product: i.product || { id: i.product_id, name: i.name || i.product_name },
          product_id: i.product_id,
          name: i.name || i.product_name,
          quantity: i.quantity,
          price: i.price,
          discount: i.discount || 0,
          surcharge: i.surcharge || 0,
          baseDiscount: i.baseDiscount ?? i.base_discount ?? 0,
          baseSurcharge: i.baseSurcharge ?? i.base_surcharge ?? 0,
          note: i.note || '',
          selected: false,
          sub_items: i.sub_items || []
        }))
      }))
      activeBillId.value = bills.value[0].id
      isDirty.value = false
    } else {
      const validAutoPromo = activePromotions.value.find(p => p.is_auto_apply);
      if (validAutoPromo && bills.value.length > 0 && !bills.value[0].promotionId) {
        bills.value[0].promotionId = validAutoPromo.id;
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải orders:', err)
  } finally {
    isLoadingBills.value = false
  }
}

const closeDropdowns = (e) => {

  const searchEl = document.getElementById('search-container')

  if (searchEl && !searchEl.contains(e.target)) isSearchDropdownOpen.value = false

  

  const catEl = document.getElementById('category-container')

  if (catEl && !catEl.contains(e.target)) isCategoryDropdownOpen.value = false

  

  const discountEl = document.getElementById('discount-container')

  if (discountEl && !discountEl.contains(e.target)) isDiscountGroupDropdownOpen.value = false

  

  const actionEl = document.getElementById('action-menu-container')

  if (actionEl && !actionEl.contains(e.target)) showActionMenu.value = false

}



onMounted(() => {

  loadMenuData()

  loadBillsFromServer()

  window.addEventListener('beforeunload', handleBeforeUnload)

  window.addEventListener('click', closeDropdowns)

})




const isProductActiveForOutlet = (p) => {
  if (!p.is_active || p.is_active === '0' || p.is_active === 0) return false
  const outletCode = currentOutletCode.value
  const outletPricesArray = p.outlet_prices || p.outletPrices
  if (outletCode && outletPricesArray) {
    const op = outletPricesArray.find(o => o.outlet?.code === outletCode)
    if (op && (!op.is_active || op.is_active === '0' || op.is_active === 0)) return false
  }
  return true
}

const currentProducts = computed(() => {
  let result = products.value.filter(p => !p.open_key && isProductActiveForOutlet(p))
  if (selectedCategoryId.value) {
    result = result.filter(p => p.fb_product_category_id === selectedCategoryId.value)
  }
  return result
})

const openItems = computed(() => {
  return products.value.filter(p => p.open_key && isProductActiveForOutlet(p))
})

const searchFilteredProducts = computed(() => {
  let result = products.value.filter(p => !p.open_key && isProductActiveForOutlet(p))
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(p => (p.name || '').toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q))
  }
  return result
})



const isComboModalOpen = ref(false)
const selectedComboProduct = ref(null)
const editingBillItem = ref(null)

const handleEditCombo = (item) => {
  if (item.product?.is_combo && item.product?.is_check_combo) {
    editingBillItem.value = item
    const fullProduct = products.value.find(p => p.id === item.product.id) || item.product
    selectedComboProduct.value = fullProduct
    isComboModalOpen.value = true
  }
}

const handleComboClose = () => {
  isComboModalOpen.value = false
  editingBillItem.value = null
}

const handleComboConfirm = (payload) => {
  const { product, subItems } = payload
  isComboModalOpen.value = false
  
  if (editingBillItem.value) {
    editingBillItem.value.sub_items = subItems
    editingBillItem.value = null
  } else {
    _addProductToBill(product, subItems, false)
  }
}

const _addProductToBill = (product, subItems = [], allowMerge = true) => {
  const bill = activeBill.value
  if (!bill) return

  let existingItem = null
  if (allowMerge) {
    existingItem = bill.items.find(i => i.product.id === product.id)
  }

  if (existingItem) {
    existingItem.quantity += 1
  } else {
    bill.items.push({
      id: Date.now() + Math.floor(Math.random() * 1000),
      product: product,
      quantity: 1,
      price: getProductPrice(product),
      discount: 0,
      surcharge: 0,
      selected: false,
      sub_items: subItems
    })
  }

  if (bill.promotionId) {
    handleApplyPromotion(bill.promotionId)
  }
}

const handleAddProduct = (product) => {
  editingBillItem.value = null // reset if open from menu
  if (product.is_combo) {
    if (product.is_check_combo) {
      selectedComboProduct.value = product
      isComboModalOpen.value = true
      return
    } else {
      const comboItems = product.comboItems || product.combo_items || []
      const subItems = comboItems.map(item => ({
        id: Date.now() + Math.floor(Math.random() * 1000),
        product: item.child,
        product_id: item.child_id,
        quantity: item.quantity,
        price: item.price || 0,
        discount: 0,
        surcharge: 0,
        note: ''
      }))
      _addProductToBill(product, subItems, false)
      return
    }
  }

  _addProductToBill(product, [], true)
}



const groupedBillItems = computed(() => {

  const bill = activeBill.value

  if (!bill || !bill.items) return []

  

  const groups = {}

  bill.items.forEach(item => {

    let catName = 'Khác'

    if (item.product.fb_product_category_id) {

      const cat = categories.value.find(c => c.id === item.product.fb_product_category_id)

      if (cat) {

        if (cat.parent_id) {

           const parentCat = categories.value.find(c => c.id === cat.parent_id)

           if (parentCat) catName = parentCat.name

           else catName = cat.name

        } else {

           catName = cat.name

        }

      }

    }

    

    if (!groups[catName]) {

      groups[catName] = { name: catName, items: [] }

    }

    groups[catName].items.push(item)

  })

  

  return Object.values(groups)

})



const expandedGroups = ref({})

const toggleGroup = (catName) => {

  if (expandedGroups.value[catName] === undefined) {

    expandedGroups.value[catName] = false

  } else {

    expandedGroups.value[catName] = !expandedGroups.value[catName]

  }

}

const isGroupExpanded = (catName) => {

  return expandedGroups.value[catName] !== false

}



const isAllSelected = computed(() => {

  const bill = activeBill.value

  if (!bill || bill.items.length === 0) return false

  return bill.items.every(i => i.selected)

})



const toggleAllSelection = (e) => {

  const isChecked = e.target.checked

  const bill = activeBill.value

  if (bill) {

    bill.items.forEach(i => i.selected = isChecked)

  }

}



const isGroupFullySelected = (group) => {

  if (!group || !group.items || group.items.length === 0) return false

  return group.items.every(i => i.selected)

}



const toggleGroupSelection = (group, e) => {

  const isChecked = e.target.checked

  if (group && group.items) {

    group.items.forEach(i => i.selected = isChecked)

  }

}



watch(() => activeBill.value?.items, (newItems, oldItems) => {
  isDirty.value = true
  if (activeBill.value && activeBill.value.promotionId) {
    // Only apply if length is same, meaning it's a deep change like quantity update
    // Add/remove handled separately to avoid loop if possible
    if (newItems && oldItems && newItems.length === oldItems.length) {
       handleApplyPromotion(activeBill.value.promotionId);
    }
  }
}, { deep: true })



const removeBillItem = (item) => {

  const bill = activeBill.value

  if (!bill) return

  bill.items = bill.items.filter(i => i.id !== item.id)

  if (bill.promotionId) {
    handleApplyPromotion(bill.promotionId);
  }

  isDirty.value = true

}



const isProductInBill = (productId) => {

  return activeBill.value?.items.some(i => i.product.id === productId)

}



const handleBeforeUnload = (e) => {

  if (isDirty.value) {

    e.preventDefault()

    e.returnValue = ''

  }

}



// --- Tách đơn ---

const isSplitByItemModalOpen = ref(false)

const isSplitByAmountModalOpen = ref(false)

const showMergeBillsModal = ref(false)

const isTransferTableModalOpen = ref(false)

const isTransferItemsModalOpen = ref(false)

const isPrintTicketsModalOpen = ref(false)
const printedTickets = ref([])
const isLoadingPrintTickets = ref(false)

const handlePrintBill = async () => {
  const bill = activeBill.value
  if (!bill || !bill.items) return
  
  isPrintTicketsModalOpen.value = true
  
  // Map current items to printable tickets
  printedTickets.value = bill.items.map(item => {
    // Determine printer name (could fetch from fb_printers in a real app, here we show IDs if available)
    let printerIds = item.product?.fb_printer_ids || []
    if (typeof printerIds === 'string') {
      try {
        printerIds = JSON.parse(printerIds)
      } catch (e) {
        printerIds = [printerIds]
      }
    }
    
    let printerStr = 'Chưa cài đặt'
    if (Array.isArray(printerIds) && printerIds.length > 0) {
      printerStr = 'Máy in ' + printerIds.join(', ')
    }

    return {
      id: item.id,
      code: item.product?.code || item.product?.id || '-',
      name: item.product?.name || 'Món chưa rõ',
      billCode: bill.name || ('HD' + bill.id),
      corderCode: '-',
      printer: printerStr,
      printerType: 'Bếp/Bar',
      status: item.is_printed ? 'Đã in' : 'Chưa in',
      printedDate: item.is_printed ? item.updated_at : '-',
      originalItem: item // Keep reference for actual printing logic
    }
  })
}

const handlePrintItem = (ticket) => {
  uiStore.showToast(`Đã gửi lệnh in cho món: ${ticket.name}`, 'success')
  // Call API to print this specific item
  ticket.status = 'Đã in'
  ticket.printedDate = new Date().toLocaleString()
}

const handlePrintAll = () => {
  uiStore.showToast('Đã gửi lệnh in tất cả các món chưa in', 'success')
  // Call API to print all unprinted items
  printedTickets.value.forEach(t => {
    if (t.status === 'Chưa in') {
      t.status = 'Đã in'
      t.printedDate = new Date().toLocaleString()
    }
  })
}

const handlePrintGuestCheck = () => {
  if (!activeBill.value) return
  window.print()
}

const itemsToSplit = ref([])



const hasSelectedItems = computed(() => {

  const bill = activeBill.value

  if (!bill || !bill.items) return false

  return bill.items.some(i => i.selected)

})



const openSplitByItemModal = () => {

  if (!hasSelectedItems.value) return

  const bill = activeBill.value

  if (!bill) return

  itemsToSplit.value = bill.items.filter(i => i.selected)

  isSplitByItemModalOpen.value = true

  showActionMenu.value = false

}



const openTransferItemsModal = () => {
  if (!hasSelectedItems.value) return
  const bill = activeBill.value
  if (!bill) return
  itemsToSplit.value = bill.items.filter(i => i.selected)
  isTransferItemsModalOpen.value = true
  showActionMenu.value = false
}

const openSplitByAmountModal = () => {

  isSplitByAmountModalOpen.value = true

  showActionMenu.value = false

}



const handleConfirmSplitByItem = () => {

  const bill = activeBill.value

  if (!bill) return

  // Convert promotion discounts to base discounts first
  if (bill.items) {
    bill.items.forEach(item => {
      item.baseDiscount = item.discount || 0
      item.baseSurcharge = item.surcharge || 0
    })
  }

  // Clear promotion on split to avoid recalculation loops
  bill.promotionId = undefined

  // Copy items & unselect

  const itemsToMove = itemsToSplit.value.map(item => ({ 

    ...item, 

    selected: false,

    baseDiscount: item.discount,

    baseSurcharge: item.surcharge

  }))

  

  // Set base discount for remaining items in the old bill

  const idsToRemove = itemsToMove.map(i => i.id)

  bill.items.forEach(item => {

    if (!idsToRemove.includes(item.id)) {

      item.baseDiscount = item.discount

      item.baseSurcharge = item.surcharge

    }

  })

  

  // Remove from old bill

  bill.items = bill.items.filter(i => !idsToRemove.includes(i.id))

  

  // Create new bill

  const newBillId = Date.now()

  bills.value.push({ id: newBillId, name: `Bill ${bills.value.length + 1}`, items: itemsToMove })

  

  activeBillId.value = newBillId

  isSplitByItemModalOpen.value = false

  isDirty.value = true

  handleSave()

}



const currentBillTotal = computed(() => {

  const bill = activeBill.value

  if (!bill || !bill.items) return 0
  return bill.items.reduce((sum, i) => sum + Math.max(0, Math.round((i.price * i.quantity) - (i.discount || 0) + (i.surcharge || 0))), 0)
})



const handleConfirmSplitByAmount = (amount) => {

  const bill = activeBill.value

  if (!bill || !bill.items) return

  

  const total = currentBillTotal.value

  if (total <= 0) return

  

  const ratio = amount / total

  

  // 1. Calculate target row totals using Largest Remainder Method
  const rowTotals = bill.items.map(i => Math.max(0, Math.round((i.price * i.quantity) - (i.discount || 0) + (i.surcharge || 0))))

  const exactTargets = rowTotals.map(rt => (rt / total) * amount)

  const targetRowTotals = exactTargets.map(et => Math.floor(et))

  

  let currentSum = targetRowTotals.reduce((a, b) => a + b, 0)

  let remainder = amount - currentSum

  

  const fractionalParts = exactTargets.map((et, index) => ({ index, frac: et - Math.floor(et) }))

  fractionalParts.sort((a, b) => b.frac - a.frac)

  

  for (let i = 0; i < remainder; i++) {

    targetRowTotals[fractionalParts[i].index] += 1

  }

  // Convert promotion discounts to base discounts first
  if (bill.items) {
    bill.items.forEach(item => {
      item.baseDiscount = item.discount || 0
      item.baseSurcharge = item.surcharge || 0
    })
  }

  // Clear promotion on split to avoid recalculation loops
  bill.promotionId = undefined

  // 2. Build new bill items
  const newBillItems = bill.items.map((item, index) => {
    const targetRT = targetRowTotals[index]
    const originalRT = rowTotals[index]
    
    // Keep price unchanged, split quantity proportionally
    const newPrice = item.price
    const newQuantity = Number((item.quantity * ratio).toFixed(2))

    // Split sub_items quantity proportionally if they exist
    let newSubItems = undefined
    if (item.sub_items && item.sub_items.length) {
      newSubItems = item.sub_items.map(subItem => {
        const newSubQty = Number((subItem.quantity * ratio).toFixed(2))
        return {
          ...subItem,
          id: Date.now() + Math.random(),
          quantity: newSubQty
        }
      })
    }
    
    // Calculate the raw price*qty value (may be fractional)
    const rawPriceQty = newPrice * newQuantity
    
    // Split base discount/surcharge proportionally
    let newBaseDiscount = (item.baseDiscount || 0) * ratio
    let newBaseSurcharge = (item.baseSurcharge || 0) * ratio
    
    // The actual row total from price*qty and proportional base values
    // We need: rawPriceQty - newDiscount + newSurcharge = targetRT (exact integer)
    // So: newDiscount - newSurcharge = rawPriceQty - targetRT
    let adjustmentNeeded = rawPriceQty - targetRT
    
    // Start with proportional promo discount/surcharge
    let promoDiscount = ((item.discount || 0) - (item.baseDiscount || 0)) * ratio
    let promoSurcharge = ((item.surcharge || 0) - (item.baseSurcharge || 0)) * ratio
    
    // Total discount and surcharge before final adjustment
    let newDiscount = newBaseDiscount + promoDiscount
    let newSurcharge = newBaseSurcharge + promoSurcharge
    
    // Current net = rawPriceQty - newDiscount + newSurcharge
    // We need net = targetRT
    // So adjustment = (rawPriceQty - newDiscount + newSurcharge) - targetRT
    let currentNet = rawPriceQty - newDiscount + newSurcharge
    let finalAdjustment = currentNet - targetRT
    
    if (finalAdjustment > 0) {
      // Need to increase discount to bring total down
      newDiscount += finalAdjustment
    } else if (finalAdjustment < 0) {
      // Need to increase surcharge to bring total up
      newSurcharge += Math.abs(finalAdjustment)
    }
    
    // Normalize negatives
    if (newDiscount < 0) { newSurcharge += Math.abs(newDiscount); newDiscount = 0; }
    if (newSurcharge < 0) { newDiscount += Math.abs(newSurcharge); newSurcharge = 0; }
    if (newBaseDiscount < 0) { newBaseSurcharge += Math.abs(newBaseDiscount); newBaseDiscount = 0; }
    if (newBaseSurcharge < 0) { newBaseDiscount += Math.abs(newBaseSurcharge); newBaseSurcharge = 0; }
    
    // Cancel out redundant
    const minVal = Math.min(newDiscount, newSurcharge)
    if (minVal > 0) {
      newDiscount -= minVal
      newSurcharge -= minVal
    }
    const baseMinVal = Math.min(newBaseDiscount, newBaseSurcharge)
    if (baseMinVal > 0) {
      newBaseDiscount -= baseMinVal
      newBaseSurcharge -= baseMinVal
    }

    // Format to 2 decimal places to avoid float precision issues and match DB
    newDiscount = Number(newDiscount.toFixed(2))
    newSurcharge = Number(newSurcharge.toFixed(2))
    newBaseDiscount = Number(newBaseDiscount.toFixed(2))
    newBaseSurcharge = Number(newBaseSurcharge.toFixed(2))

    return {
      ...item,
      id: Date.now() + Math.random(),
      selected: false,
      price: newPrice,
      quantity: newQuantity,
      discount: newDiscount,
      surcharge: newSurcharge,
      baseDiscount: newBaseDiscount,
      baseSurcharge: newBaseSurcharge,
      sub_items: newSubItems
    }
  })

  

  // 3. Update old bill items by subtracting

  bill.items = bill.items.map((item, index) => {

    const newItem = newBillItems[index]

    const oldQuantity = Number((item.quantity - newItem.quantity).toFixed(2))

    

    let oldDiscount = (item.discount || 0) - newItem.discount

    let oldSurcharge = (item.surcharge || 0) - newItem.surcharge

    

    let oldBaseDiscount = (item.baseDiscount || 0) - newItem.baseDiscount

    let oldBaseSurcharge = (item.baseSurcharge || 0) - newItem.baseSurcharge
    
    // Subtract sub_items quantity if they exist
    let oldSubItems = undefined
    if (item.sub_items && item.sub_items.length) {
      oldSubItems = item.sub_items.map((subItem, sIdx) => {
        const newSubItem = newItem.sub_items[sIdx]
        const oldSubQty = Number((subItem.quantity - newSubItem.quantity).toFixed(2))
        return {
          ...subItem,
          quantity: oldSubQty
        }
      })
    }

    

    // Normalize negatives

    if (oldDiscount < 0) { oldSurcharge += Math.abs(oldDiscount); oldDiscount = 0; }

    if (oldSurcharge < 0) { oldDiscount += Math.abs(oldSurcharge); oldSurcharge = 0; }

    if (oldBaseDiscount < 0) { oldBaseSurcharge += Math.abs(oldBaseDiscount); oldBaseDiscount = 0; }

    if (oldBaseSurcharge < 0) { oldBaseDiscount += Math.abs(oldBaseSurcharge); oldBaseSurcharge = 0; }

    

    // Cancel out redundant

    const minVal = Math.min(oldDiscount, oldSurcharge)

    if (minVal > 0) {

      oldDiscount -= minVal

      oldSurcharge -= minVal

    }

    const baseMinVal = Math.min(oldBaseDiscount, oldBaseSurcharge)

    if (baseMinVal > 0) {

      oldBaseDiscount -= baseMinVal

      oldBaseSurcharge -= baseMinVal

    }

    
    // Format to 2 decimal places to avoid float precision issues and match DB
    oldDiscount = Number(oldDiscount.toFixed(2))
    oldSurcharge = Number(oldSurcharge.toFixed(2))
    oldBaseDiscount = Number(oldBaseDiscount.toFixed(2))
    oldBaseSurcharge = Number(oldBaseSurcharge.toFixed(2))

    return {

      ...item,

      quantity: oldQuantity,

      discount: oldDiscount,

      surcharge: oldSurcharge,

      baseDiscount: oldBaseDiscount,

      baseSurcharge: oldBaseSurcharge,
      sub_items: oldSubItems

    }

  })

  

  const newBillId = Date.now()

  bills.value.push({ id: newBillId, name: `Bill ${bills.value.length + 1}`, items: newBillItems })

  

  activeBillId.value = newBillId

  isSplitByAmountModalOpen.value = false

  isDirty.value = true

  handleSave()

}



const handleConfirmMergeBills = (selectedIds) => {

  if (selectedIds.length < 2) return

  

  const primaryBillId = selectedIds[0]

  const primaryBill = bills.value.find(b => b.id === primaryBillId)

  

  if (!primaryBill) return

  

  for (let i = 1; i < selectedIds.length; i++) {

    const billToMerge = bills.value.find(b => b.id === selectedIds[i])

    if (!billToMerge) continue

    

    billToMerge.items.forEach(item => {

      const existingItem = primaryBill.items.find(pi => pi.product.id === item.product.id && pi.price === item.price)

      

      if (existingItem) {
        existingItem.quantity = Number((existingItem.quantity + item.quantity).toFixed(2))
        existingItem.discount = Math.round((existingItem.discount || 0) + (item.discount || 0))
        existingItem.surcharge = Math.round((existingItem.surcharge || 0) + (item.surcharge || 0))
        existingItem.baseDiscount = Math.round((existingItem.baseDiscount || 0) + (item.baseDiscount || 0))
        existingItem.baseSurcharge = Math.round((existingItem.baseSurcharge || 0) + (item.baseSurcharge || 0))

        

        // Cancel out redundant

        const minVal = Math.min(existingItem.discount, existingItem.surcharge)

        if (minVal > 0) {

          existingItem.discount -= minVal

          existingItem.surcharge -= minVal

        }

        

        const minBase = Math.min(existingItem.baseDiscount || 0, existingItem.baseSurcharge || 0)
        if (minBase > 0) {
          existingItem.baseDiscount -= minBase
          existingItem.baseSurcharge -= minBase
        }

      } else {

        primaryBill.items.push({

          ...item,

          id: Date.now() + Math.random()

        })

      }

    })

  }

  

  const idsToRemove = selectedIds.slice(1)

  bills.value = bills.value.filter(b => !idsToRemove.includes(b.id))

  

  showMergeBillsModal.value = false

  activeBillId.value = primaryBill.id

  isDirty.value = true

  

  showActionMenu.value = false

  handleSave()

}


const handleConfirmTransferItems = async (targetTable, transferredItems) => {
  try {
    isTransferItemsModalOpen.value = false

    // Auto-save if there are unsaved changes
    if (isDirty.value) {
      await syncOrders(props.table.id, {
        bills: JSON.parse(JSON.stringify(bills.value))
      })
      isDirty.value = false
    }
    // Build payload from selected items with adjusted quantities
    const sourceItems = transferredItems || itemsToSplit.value
    const payload = {
      items: sourceItems.map(item => {
        const origQty = item.quantity || 1
        const transferQty = (item.transferQty != null) ? item.transferQty : origQty
        const ratio = origQty > 0 ? transferQty / origQty : 1
        return {
          product_id: item.product_id || item.product?.id || item.id,
          quantity: transferQty,
          price: item.price,
          product_name: item.name || item.product?.name || item.product_name,
          discount: Math.round((item.discount || 0) * ratio),
          surcharge: Math.round((item.surcharge || 0) * ratio),
          base_discount: Math.round((item.baseDiscount || 0) * ratio),
          base_surcharge: Math.round((item.baseSurcharge || 0) * ratio),
          note: item.note
        }
      })
    }

    await transferItems(props.table.id, targetTable.id, payload)

    uiStore.alert('Chuyển món thành công!')

    // Clear selection state
    itemsToSplit.value = []

    // Reload orders from backend to reflect the updated bill of THIS table
    try {
      const res = await fetchActiveOrders(props.table.id)
      const orders = Array.isArray(res.data) ? res.data : (res.data?.orders || res.data?.data || [])
      if (orders.length > 0) {
        bills.value = orders.map((order, idx) => ({
          id: order.id,
          name: order.name || `Bill ${idx + 1}`,
          customerName: order.customerName || order.customer_name || '',
          customerPhone: order.customerPhone || order.customer_phone || '',
          customerEmail: order.customerEmail || order.customer_email || '',
          customerAddress: order.customerAddress || order.customer_address || '',
          guestCount: order.guestCount || order.guest_count || 0,
          publicNote: order.publicNote || order.public_note || '',
          internalNote: order.internalNote || order.internal_note || '',
          promotionId: order.promotionId || order.promotion_id,
          items: (order.items || []).map(i => ({
            id: i.id,
            product: i.product || { id: i.product_id, name: i.name || i.product_name },
            product_id: i.product_id,
            name: i.name || i.product_name,
            quantity: i.quantity,
            price: i.price,
            discount: i.discount || 0,
            surcharge: i.surcharge || 0,
            baseDiscount: i.baseDiscount ?? i.base_discount ?? 0,
            baseSurcharge: i.baseSurcharge ?? i.base_surcharge ?? 0,
            note: i.note || '',
            selected: false
          }))
        }))
        if (bills.value.length > 0) {
          activeBillId.value = bills.value[0].id
        }
      } else {
        // No more orders on this table
        bills.value = [{ id: Date.now(), name: 'Bill 1', items: [] }]
        activeBillId.value = bills.value[0].id
      }
      isDirty.value = false
    } catch (reloadErr) {
      console.error('Lỗi khi reload orders:', reloadErr)
    }

    // Notify parent to refresh table list (statuses)
    emit('transfer-items-success')
  } catch (error) {
    console.error('Lỗi khi chuyển món:', error)
    uiStore.alert(error.response?.data?.message || 'Có lỗi xảy ra khi chuyển món: ' + error.message, 'error')
  }
}

const handleTransferTable = async (targetTable) => {
  try {
    // Call API to transfer
    await transferTable(props.table.id, targetTable.id)
    
    isTransferTableModalOpen.value = false
    uiStore.alert(`Chuyển bàn thành công tới: ${targetTable.name}`)
    // Emit an event that tells the parent (RestaurantPage) that a transfer occurred.
    // The parent should refresh both tables' status and switch the selected table or close the detail.
    emit('transfer-success', { from: props.table.id, to: targetTable.id })
  } catch (error) {
    console.error('Lỗi khi chuyển bàn:', error)
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi chuyển bàn!'
    uiStore.alert(msg, 'error')
  }
}

onBeforeRouteLeave(async (to, from) => {
  if (isDirty.value) {
    const confirmed = await uiStore.confirm({
      title: 'Xác nhận rời trang', 
      message: 'Bạn có thay đổi chưa lưu, bạn có chắc chắn muốn rời khỏi trang?'
    })
    if (confirmed) {
      return true
    } else {
      return false
    }
  } else {
    return true
  }
})

onBeforeRouteUpdate(async (to, from) => {
  if (isDirty.value && to.query.outlet_code !== from.query.outlet_code) {
    const confirmed = await uiStore.confirm({
      title: 'Xác nhận rời trang', 
      message: 'Bạn có thay đổi chưa lưu, bạn có chắc chắn muốn rời khỏi trang?'
    })
    if (confirmed) {
      return true
    } else {
      return false
    }
  } else {
    return true
  }
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
  window.removeEventListener('click', closeDropdowns)
})

const handleBack = async () => {
  if (isDirty.value) {
    const confirmed = await uiStore.confirm({
      title: 'Xác nhận rời trang', 
      message: 'Bạn có thay đổi chưa lưu, bạn có chắc chắn muốn rời khỏi trang?'
    })
    if (confirmed) {
      emit('back')
    }
  } else {
    emit('back')
  }
}

const handleSave = () => {
  isDirty.value = false
  uiStore.alert('Đã lưu bill thành công!')
  
  // Calculate total amount for all bills
  const totalAmount = bills.value.reduce((total, b) => {
    const billTotal = b.items.reduce((sum, item) => {
      const p = item.price || 0
      const q = item.quantity || 1
      const d = item.discount || 0
      const s = item.surcharge || 0
      return sum + (p * q) - d + s
    }, 0)
    return total + billTotal
  }, 0)
  
  // Emit save event to update table status in parent
  emit('save', {
    status: bills.value[0]?.items?.length > 0 ? 'serving' : 'empty',
    guest: billInfo.value.guestCount || 1,
    time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
    totalAmount: totalAmount,
    bills: JSON.parse(JSON.stringify(bills.value))
  })
}

// Bill Info
const billInfo = ref({
  customerName: '',
  customerPhone: '',
  customerEmail: '',
  customerAddress: '',
  guestCount: 0,
  publicNote: '',
  internalNote: '',
  internalNoteDiscount: '',
  promotionId: undefined
})

const creatorName = ref('Demo')

watch(() => activeBill.value, (newBill) => {
  if (newBill) {
    billInfo.value.customerName = newBill.customerName || ''
    billInfo.value.customerPhone = newBill.customerPhone || ''
    billInfo.value.customerEmail = newBill.customerEmail || ''
    billInfo.value.customerAddress = newBill.customerAddress || ''
    billInfo.value.guestCount = newBill.guestCount || 0
    billInfo.value.publicNote = newBill.publicNote || ''
    billInfo.value.internalNote = newBill.internalNote || ''
    billInfo.value.internalNoteDiscount = newBill.internalNoteDiscount || ''
    billInfo.value.promotionId = newBill.promotionId || undefined
    if (newBill.creator_name) creatorName.value = newBill.creator_name
  }
}, { immediate: true })

watch(() => billInfo.value, (newInfo) => {
  const bill = activeBill.value
  if (bill) {
    let changed = false
    if (bill.customerName !== newInfo.customerName) { bill.customerName = newInfo.customerName; changed = true }
    if (bill.customerPhone !== newInfo.customerPhone) { bill.customerPhone = newInfo.customerPhone; changed = true }
    if (bill.customerEmail !== newInfo.customerEmail) { bill.customerEmail = newInfo.customerEmail; changed = true }
    if (bill.customerAddress !== newInfo.customerAddress) { bill.customerAddress = newInfo.customerAddress; changed = true }
    if (bill.guestCount !== newInfo.guestCount) { bill.guestCount = newInfo.guestCount; changed = true }
    if (bill.publicNote !== newInfo.publicNote) { bill.publicNote = newInfo.publicNote; changed = true }
    if (bill.internalNote !== newInfo.internalNote) { bill.internalNote = newInfo.internalNote; changed = true }
    if (bill.internalNoteDiscount !== newInfo.internalNoteDiscount) { bill.internalNoteDiscount = newInfo.internalNoteDiscount; changed = true }
    if (bill.promotionId !== newInfo.promotionId) { 
      bill.promotionId = newInfo.promotionId; 
      changed = true;
      handleApplyPromotion(newInfo.promotionId);
    }
    if (changed) isDirty.value = true
  }
}, { deep: true })

const activePromotions = computed(() => {
  const bill = activeBill.value;
  return promotions.value.filter(p => {
    if (!p.is_active) return false;
    if (p.outlet_id && (!p.outlet || p.outlet.code !== currentOutletCode.value)) return false;

    const now = new Date();
    if (p.start_date) {
      const startDate = new Date(p.start_date);
      startDate.setHours(0, 0, 0, 0);
      if (now < startDate) return false;
    }
    if (p.end_date) {
      const endDate = new Date(p.end_date);
      endDate.setHours(23, 59, 59, 999);
      if (now > endDate) return false;
    }

    if (p.apply_by_time && p.start_time && p.end_time) {
      const currentHours = now.getHours().toString().padStart(2, '0');
      const currentMinutes = now.getMinutes().toString().padStart(2, '0');
      const currentTimeStr = `${currentHours}:${currentMinutes}:00`;
      if (currentTimeStr < p.start_time || currentTimeStr > p.end_time) return false;
    }

    if (p.company_id) {
      if (!bill || bill.companyId !== p.company_id) return false;
    }
    if (p.customer_source_id) {
      if (!bill || bill.customerSourceId !== p.customer_source_id) return false;
    }

    return true;
  });
})

const handleApplyPromotion = (promoId) => {
  const bill = activeBill.value;
  if (!bill || !bill.items) return;
  
  let changed = false;

  const promo = promoId ? promotions.value.find(p => p.id === promoId) : null;
  const isAll = promo ? promo.is_all_product : false;
  const promoProductIds = promo && promo.products ? promo.products.map(pp => Number(pp.fb_product_id)) : [];

  let totalTargetPrice = 0;
  if (promo) {
    bill.items.forEach(item => {
      if (isAll || promoProductIds.includes(Number(item.product.id))) {
        totalTargetPrice += Math.max(0, (item.price * item.quantity) - (item.baseDiscount || 0) + (item.baseSurcharge || 0));
      }
    });
  }

  bill.items.forEach(item => {
    let newDisc = 0;
    let newSur = 0;

    if (promo && (isAll || promoProductIds.includes(Number(item.product.id)))) {
      const itemTotal = Math.max(0, (item.price * item.quantity) - (item.baseDiscount || 0) + (item.baseSurcharge || 0));

      if (promo.discount_percent > 0) {
        newDisc = Math.round(itemTotal * (promo.discount_percent / 100));
      } else if (promo.discount_amount > 0) {
        if (totalTargetPrice > 0) {
          const proportion = itemTotal / totalTargetPrice;
          newDisc = Math.round(promo.discount_amount * proportion);
        }
      } else if (promo.increase_percent > 0) {
        newSur = Math.round(itemTotal * (promo.increase_percent / 100));
      } else if (promo.increase_amount > 0) {
        if (totalTargetPrice > 0) {
          const proportion = itemTotal / totalTargetPrice;
          newSur = Math.round(promo.increase_amount * proportion);
        }
      }
    }

    const finalDiscount = (item.baseDiscount || 0) + newDisc;
    const finalSurcharge = (item.baseSurcharge || 0) + newSur;

    if (item.discount !== finalDiscount) {
      item.discount = finalDiscount;
      changed = true;
    }
    if (item.surcharge !== finalSurcharge) {
      item.surcharge = finalSurcharge;
      changed = true;
    }
  });

  if (changed) {
    isDirty.value = true;
  }
};

// Modals
const isCustomerModalOpen = ref(false)
const isInternalNoteModalOpen = ref(false)
const isOpenItemModalOpen = ref(false)

// Discount logic
const discountAmount = ref(0)
const discountType = ref('discount_amount')
const discountGroups = ref(['ALL'])
const isDiscountGroupDropdownOpen = ref(false)

const availableGroupsInBill = computed(() => {
  return groupedBillItems.value.map(g => g.name)
})

const isDiscountValid = computed(() => {
  return discountAmount.value >= 0 && discountGroups.value.length > 0
})

const toggleAllDiscountGroups = (e) => {
  if (e.target.checked) {
    discountGroups.value = ['ALL']
  } else {
    discountGroups.value = []
  }
}

const isGroupInDiscount = (grp) => {
  if (discountGroups.value.includes('ALL')) return true
  return discountGroups.value.includes(grp)
}

const toggleSpecificDiscountGroup = (grp, e) => {
  const isChecked = e.target.checked
  let current = [...discountGroups.value]
  
  if (current.includes('ALL')) {
    if (!isChecked) {
      current = availableGroupsInBill.value.filter(g => g !== grp)
    }
  } else {
    if (isChecked) {
      current.push(grp)
    } else {
      current = current.filter(g => g !== grp)
    }
  }
  
  if (current.length > 0 && current.length === availableGroupsInBill.value.length) {
    discountGroups.value = ['ALL']
  } else {
    discountGroups.value = current
  }
}

const applyDiscount = () => {
  if (!isDiscountValid.value) return
  const bill = activeBill.value
  if (!bill) return
  
  let targetItems = []
  if (discountGroups.value.includes('ALL')) {
    targetItems = bill.items
  } else {
    targetItems = bill.items.filter(item => {
       let catName = 'Khác'
       if (item.product.fb_product_category_id) {
         const cat = categories.value.find(c => c.id === item.product.fb_product_category_id)
         if (cat) {
           if (cat.parent_id) {
              const parentCat = categories.value.find(c => c.id === cat.parent_id)
              if (parentCat) catName = parentCat.name
              else catName = cat.name
           } else {
              catName = cat.name
           }
         }
       }
       return discountGroups.value.includes(catName)
    })
  }

  if (targetItems.length > 0) {
    const isZero = discountAmount.value === 0
    const totalTargetPrice = targetItems.reduce((sum, item) => sum + Math.max(0, (item.price * item.quantity) - (item.baseDiscount || 0) + (item.baseSurcharge || 0)), 0)
    targetItems.forEach(item => {
      if (isZero) {
        if (discountType.value.startsWith('discount')) {
          item.discount = 0
          item.baseDiscount = 0
        } else {
          item.surcharge = 0
          item.baseSurcharge = 0
        }
        return
      }
      
      const itemTotal = Math.max(0, (item.price * item.quantity) - (item.baseDiscount || 0) + (item.baseSurcharge || 0))
      let newDisc = 0
      let newSur = 0
      
      if (discountType.value === 'discount_amount') {
         if (totalTargetPrice > 0) {
           const proportion = itemTotal / totalTargetPrice
           newDisc = Math.min(itemTotal, Math.round(discountAmount.value * proportion))
         }
      } else if (discountType.value === 'discount_amount_per_item') {
         newDisc = Math.min(itemTotal, discountAmount.value)
      } else if (discountType.value === 'discount_percent') {
         newDisc = Math.min(itemTotal, Math.round(itemTotal * (discountAmount.value / 100)))
      } else if (discountType.value === 'surcharge_amount') {
         if (totalTargetPrice > 0) {
           const proportion = itemTotal / totalTargetPrice
           newSur = Math.round(discountAmount.value * proportion)
         }
      } else if (discountType.value === 'surcharge_amount_per_item') {
         newSur = discountAmount.value
      } else if (discountType.value === 'surcharge_percent') {
         newSur = Math.round(itemTotal * (discountAmount.value / 100))
      }
      
      if (discountType.value.startsWith('discount')) {
         item.discount = (item.baseDiscount || 0) + newDisc
      } else {
         item.surcharge = (item.baseSurcharge || 0) + newSur
      }
    })
  }
  isDirty.value = true
  isDiscountGroupDropdownOpen.value = false
}

const handleSaveCustomerInfo = (data) => {
    billInfo.value.customerName = data.customerName || data.customerPhone
    billInfo.value.customerPhone = data.customerPhone
    billInfo.value.customerEmail = data.customerEmail || ''
    billInfo.value.customerAddress = data.customerAddress || ''
    billInfo.value.guestCount = data.guestCount
    billInfo.value.publicNote = data.publicNote
    billInfo.value.companyId = data.companyId || null
    billInfo.value.customerSourceId = data.customerSourceId || null
    billInfo.value.bookerId = data.bookerId || null
    
    if (activeBill.value) {
      activeBill.value.customerName = billInfo.value.customerName
      activeBill.value.customerPhone = billInfo.value.customerPhone
      activeBill.value.customerEmail = billInfo.value.customerEmail
      activeBill.value.customerAddress = billInfo.value.customerAddress
      activeBill.value.guestCount = billInfo.value.guestCount
      activeBill.value.publicNote = billInfo.value.publicNote
      activeBill.value.companyId = billInfo.value.companyId
      activeBill.value.customerSourceId = billInfo.value.customerSourceId
      activeBill.value.bookerId = billInfo.value.bookerId
    }

    isCustomerModalOpen.value = false
    
    // Auto save customer info changes immediately
    setTimeout(() => {
      handleSave()
    }, 100)
  }

const handleSaveInternalNote = (data) => {
  billInfo.value.internalNote = data.internalNote
  billInfo.value.internalNoteDiscount = data.internalNoteDiscount
  
  if (activeBill.value) {
    activeBill.value.internalNote = data.internalNote
    activeBill.value.internalNoteDiscount = data.internalNoteDiscount
  }
  
  isInternalNoteModalOpen.value = false
  
  // Auto save internal note changes immediately
  setTimeout(() => {
    handleSave()
  }, 100)
}
const handleClickOutside = (e) => {
  const searchContainer = document.getElementById('search-container')
  if (searchContainer && !searchContainer.contains(e.target)) {
    isSearchDropdownOpen.value = false
  }
  const categoryContainer = document.getElementById('category-container')
  if (categoryContainer && !categoryContainer.contains(e.target)) {
    isCategoryDropdownOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

</script>

<template>
  <div class="flex h-[calc(100vh-48px)] w-full overflow-hidden bg-white relative">
    <!-- Loading overlay (PMS Style spinner) -->
    <div v-if="isLoadingBills" class="absolute inset-0 z-[100] flex items-center justify-center bg-white/50 backdrop-blur-xs">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- Sidebar (Menu) - Moved to LEFT -->
    <div class="w-[30rem] border-r border-slate-200 flex flex-col shrink-0 bg-[#f8f9fa] shadow-[2px_0_8px_rgba(0,0,0,0.05)] z-50 relative">
      <div class="p-3 bg-white border-b border-slate-200 flex flex-col items-center">
        <h2 class="text-center font-bold text-slate-500 text-sm tracking-wide flex-1 w-full uppercase">NHÓM THỰC ĐƠN</h2>
        <h3 class="text-center font-bold text-slate-800 text-sm tracking-wide mt-1 uppercase">{{ categories.find(c => c.id === selectedCategoryId)?.name || 'TẤT CẢ' }}</h3>
      </div>
      <div class="p-3 border-b border-slate-200 flex flex-col gap-3 relative">
        <div class="flex items-center gap-2">
          <div id="search-container" class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input type="text" v-model="searchQuery" @focus="isSearchDropdownOpen = true" class="block w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded text-sm focus:outline-none focus:border-sky-500 bg-white shadow-inner" placeholder="Tìm món" />
            
            <!-- Search Dropdown -->
            <div v-if="isSearchDropdownOpen" class="absolute top-full left-0 mt-1 w-[35rem] max-w-[85vw] bg-white border border-slate-200 shadow-xl rounded-lg z-[110] max-h-[60vh] overflow-y-auto flex flex-col gap-0.5 p-1">
              <div v-if="searchFilteredProducts.length === 0" class="p-3 text-center text-slate-500 text-sm">Không tìm thấy món ăn</div>
              <div v-for="prod in searchFilteredProducts" :key="'search-'+prod.id" class="flex items-center justify-between p-1.5 border-b border-slate-100 bg-white hover:bg-sky-50 transition-colors cursor-pointer" @click.stop="handleAddProduct(prod); isSearchDropdownOpen = false; searchQuery = ''">
                <div class="flex items-center gap-2">
                   <span class="text-[11px] font-bold text-slate-400 w-10 truncate">{{ prod.code || prod.product_code }}</span>
                   <span class="text-xs font-semibold text-slate-800 line-clamp-1">{{ prod.name }}</span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                   <span class="text-xs font-bold text-emerald-600">{{ Number(getProductPrice(prod)).toLocaleString('vi-VN') }} ₫</span>
                   <button class="bg-sky-100 text-sky-600 hover:bg-sky-500 hover:text-white p-1 rounded transition-colors" title="Thêm vào bill">
                      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                   </button>
                </div>
              </div>
            </div>
          </div>
          <button @click="isOpenItemModalOpen = true" class="bg-sky-400 hover:bg-sky-500 text-white px-3 py-1.5 rounded flex items-center gap-1 text-xs font-semibold whitespace-nowrap transition-colors shadow-sm h-[34px]">
            Open item
          </button>
        </div>
        
        <!-- Custom Dropdown for Categories -->
        <div id="category-container" class="relative w-full">
          <button @click="isCategoryDropdownOpen = !isCategoryDropdownOpen" class="w-full flex items-center justify-between border border-slate-300 rounded px-2 py-1.5 text-sm bg-white font-semibold text-slate-700 uppercase focus:outline-none focus:border-sky-500">
            <span>{{ categories.find(c => c.id === selectedCategoryId)?.name || 'TẤT CẢ DANH MỤC' }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </button>
          
          <div v-if="isCategoryDropdownOpen" class="absolute top-full left-0 mt-1 w-[40rem] max-w-[85vw] bg-white border border-slate-200 shadow-xl rounded-lg z-[100] p-4 max-h-[60vh] overflow-y-auto">
            <div @click="selectCategory(null)" class="mb-4 text-center py-2 bg-slate-100 hover:bg-slate-200 cursor-pointer rounded font-semibold text-slate-700 uppercase transition-colors">Tất cả danh mục</div>
            <div v-for="root in rootCategories" :key="root.id" class="mb-6">
              <div @click="selectCategory(root.id)" class="font-bold text-sky-700 text-sm mb-3 pb-1 border-b border-sky-100 uppercase cursor-pointer hover:text-sky-500 transition-colors">{{ root.name }}</div>
              <div class="grid grid-cols-5 gap-3">
                <div v-for="child in getChildren(root.id)" :key="child.id" @click="selectCategory(child.id)" 
                     class="aspect-square border border-slate-200 rounded-lg overflow-hidden bg-white cursor-pointer hover:border-sky-400 hover:shadow-md transition-all flex flex-col items-center justify-center p-2 group">
                  <img v-if="child.image" :src="getImageUrl(child.image)" class="w-12 h-12 object-cover rounded mb-2 group-hover:scale-110 transition-transform duration-300" />
                  <div v-else class="w-12 h-12 bg-slate-100 rounded mb-2 flex items-center justify-center text-slate-400 font-bold text-xs group-hover:bg-slate-200 transition-colors">{{ child.name.substring(0,2).toUpperCase() }}</div>
                  <span class="text-[10px] font-semibold text-slate-700 text-center line-clamp-2 leading-tight uppercase">{{ child.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex-1 overflow-auto p-2">
        
        <!-- Loading State -->
        <div v-if="isLoadingMenu" class="flex flex-col items-center justify-center pt-10 text-slate-400">
          <div class="loader mb-2">
            <div class="inner one"></div>
            <div class="inner two"></div>
            <div class="inner three"></div>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide mt-2">Đang tải...</span>
        </div>
        
        <!-- Products Grid (Normal 3-column) -->
        <div v-else-if="currentProducts.length > 0" class="grid grid-cols-3 gap-2">
          <div v-for="prod in currentProducts" :key="'prod-'+prod.id" @click="handleAddProduct(prod)"
               class="border border-slate-200 rounded-lg overflow-hidden bg-white cursor-pointer hover:border-emerald-400 hover:shadow-sm transition-all flex flex-col p-2 relative">
            <div v-if="isProductInBill(prod.id)" class="absolute top-1 right-1 w-5 h-5 bg-sky-500 rounded-full flex items-center justify-center text-white shadow-sm z-10">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div class="w-full h-20 bg-slate-100 rounded mb-2 overflow-hidden flex items-center justify-center shrink-0">
              <img v-if="prod.image" :src="getImageUrl(prod.image)" class="w-full h-full object-cover" />
              <span v-else class="text-xs text-slate-400 font-bold">{{ prod.code || prod.product_code }}</span>
            </div>
            <span class="text-[11px] font-bold text-slate-800 line-clamp-2 leading-tight">{{ prod.name }}</span>
            <div class="flex-1"></div>
            <span class="text-xs text-emerald-600 font-bold mt-2">{{ Number(getProductPrice(prod)).toLocaleString('vi-VN') }} ₫</span>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center pt-10 text-slate-300">
          <svg class="w-10 h-10 text-slate-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <span class="text-xs font-semibold uppercase tracking-wide">Trống</span>
        </div>
      </div>
    </div>

    <!-- Main Content (Bill) -->
    <div class="flex-1 flex flex-col overflow-hidden bg-white">
      <!-- Top header with Tabs -->
      <div class="flex items-center justify-between bg-gradient-to-r from-sky-600 to-sky-700 shrink-0 px-2 py-1.5 shadow-md">
        <div class="flex items-center flex-1 overflow-x-auto gap-1">
          <button @click="handleBack" class="mr-1 p-1.5 rounded-lg hover:bg-white/20 text-white transition-colors shrink-0" title="Quảy lại sơ đồ bàn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          </button>
          <div v-for="bill in bills" :key="bill.id"
               @click="activeBillId = bill.id"
               class="px-3 py-1 cursor-pointer whitespace-nowrap rounded-full text-sm font-semibold transition-all duration-150 shrink-0"
               :class="activeBillId === bill.id
                 ? 'bg-white text-sky-700 shadow-sm'
                 : 'text-white/80 hover:bg-white/20 hover:text-white'">
            {{ bill.name }}
          </div>
        </div>
        <div class="flex items-center gap-2 px-2 shrink-0">
          <div class="bg-white/20 text-white font-extrabold text-sm px-4 py-1 rounded-full shadow-sm border border-white/30">
            {{ table.name || table.id }}
          </div>
        </div>
      </div>

      <!-- Toolbar inputs (Customer & Notes) -->
      <div class="flex items-center gap-1.5 px-2 py-1.5 bg-slate-50 border-b border-slate-200 shrink-0 text-xs">
        <div :title="`Tên: ${billInfo.customerName || '---'} | SĐT: ${billInfo.customerPhone || '---'}`" class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg px-2 py-1 flex-1 min-w-0 cursor-help">
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span class="text-slate-500 text-[10px] shrink-0">KH:</span>
          <span class="font-semibold text-slate-800 truncate text-[11px]">{{ billInfo.customerName || '---' }}</span>
        </div>
        <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg px-2 py-1 w-20 shrink-0">
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span class="font-bold text-slate-800 text-[11px]">{{ billInfo.guestCount || 1 }}</span>
        </div>
        <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg px-2 py-1 flex-1 min-w-0">
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span class="text-slate-500 text-[10px] shrink-0">Tạo:</span>
          <span class="font-semibold text-slate-800 truncate text-[11px]">{{ creatorName }}</span>
        </div>
        <button @click="isCustomerModalOpen = true" class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg px-2 py-1 flex-1 min-w-0 hover:border-sky-400 hover:bg-sky-50 transition-all group">
          <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
          <span class="text-slate-500 text-[10px] shrink-0">Ghi chú:</span>
          <span class="font-semibold text-slate-700 truncate text-[11px]">{{ billInfo.publicNote || '---' }}</span>
        </button>
        <button @click="isInternalNoteModalOpen = true" class="flex items-center gap-1 bg-white border border-slate-200 rounded-lg px-2 py-1 flex-1 min-w-0 hover:border-sky-400 hover:bg-sky-50 transition-all group" title="Ghi chú nội bộ & Giảm giá">
          <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          <span class="text-slate-500 text-[10px] shrink-0">Nội bộ:</span>
          <span class="font-semibold text-slate-700 truncate text-[11px]">
            {{ [billInfo.internalNote, billInfo.internalNoteDiscount].filter(Boolean).join(' | ') || '---' }}
          </span>
        </button>
      </div>

      <!-- Promo / Price Policy section (Replaced Khách lẻ) -->
      <div class="bg-white p-2 border-b border-slate-200 flex flex-col gap-2 text-xs bg-amber-50/30">
        <div class="flex gap-4">
          <div class="flex-1 flex flex-col gap-1">
            <span class="text-slate-500 font-semibold">Chương trình khuyến mãi</span>
            <select v-model="billInfo.promotionId" class="border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:border-sky-400 bg-white">
              <option :value="undefined">Normal</option>
              <option v-for="promo in activePromotions" :key="promo.id" :value="promo.id">{{ promo.name }}</option>
            </select>
          </div>
          <div class="flex-1 flex flex-col gap-1">
            <span class="text-slate-500 font-semibold">Chính sách giá</span>
            <select class="border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:border-sky-400 bg-white">
              <option>Mặc định</option>
            </select>
          </div>
        </div>
        <div class="flex-1 flex flex-col gap-1 bg-slate-100/50 p-2 rounded border border-slate-100">
          <span class="text-slate-500 font-semibold">Tăng giảm giá</span>
          <div class="flex gap-2 items-center">
            <select v-model="discountType" class="border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:border-sky-400 bg-white flex-1 max-w-[200px]">
              <option value="discount_amount">Giảm tổng tiền (chia đều)</option>
              <option value="discount_amount_per_item">Giảm tiền trên từng món</option>
              <option value="discount_percent">Giảm giá theo %</option>
              <option value="surcharge_amount">Phụ thu tổng tiền (chia đều)</option>
              <option value="surcharge_amount_per_item">Phụ thu tiền trên từng món</option>
              <option value="surcharge_percent">Phụ thu theo %</option>
            </select>
            <input type="number" v-model="discountAmount" class="border border-slate-200 rounded px-2 py-1.5 w-24 text-right focus:outline-none focus:border-sky-400" />
            <span class="text-slate-500 font-semibold">{{ discountType.includes('percent') ? '%' : 'VNĐ' }}</span>
            
            <div id="discount-container" class="relative w-40">
              <div @click="isDiscountGroupDropdownOpen = !isDiscountGroupDropdownOpen" class="border border-slate-200 rounded px-2 py-1.5 focus:outline-none focus:border-sky-400 bg-white cursor-pointer flex justify-between items-center text-slate-700 text-xs h-[34px]">
                <span class="truncate">{{ discountGroups.includes('ALL') ? 'Tất cả' : (discountGroups.length + ' nhóm') }}</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
              <div v-if="isDiscountGroupDropdownOpen" class="absolute top-full mt-1 right-0 w-48 bg-white border border-slate-200 shadow-xl rounded z-50 py-1">
                <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-xs">
                  <input type="checkbox" :checked="discountGroups.includes('ALL')" @change="toggleAllDiscountGroups" class="rounded border-slate-300 text-sky-500" />
                  <span class="font-semibold">Tất cả nhóm</span>
                </label>
                <div class="border-t border-slate-100 my-1"></div>
                <label v-for="grp in availableGroupsInBill" :key="grp" class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-xs">
                  <input type="checkbox" :checked="isGroupInDiscount(grp)" @change="toggleSpecificDiscountGroup(grp, $event)" class="rounded border-slate-300 text-sky-500" />
                  <span>{{ grp }}</span>
                </label>
              </div>
            </div>
            <button @click="applyDiscount" :class="isDiscountValid ? 'bg-sky-500 hover:bg-sky-600' : 'bg-slate-300 cursor-not-allowed'" class="text-white px-4 py-1.5 rounded font-semibold transition-colors">Xác nhận</button>
          </div>
        </div>
      </div>
        <!-- Table items -->
        <div class="flex-1 overflow-auto flex flex-col border-b border-slate-200">
          <table v-if="activeBill && activeBill.items.length > 0" class="w-full text-xs border-collapse">
            <thead class="sticky top-0 bg-white z-10">
            <tr class="border-b-2 border-sky-100 text-sky-800 bg-sky-50">
              <th class="px-2 py-2 text-center border-r border-sky-100 w-8">
                <input type="checkbox" :checked="isAllSelected" @change="toggleAllSelection" class="rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
              </th>
              <th class="px-3 py-2 text-left font-bold border-r border-sky-100 w-1/4 text-[11px] uppercase tracking-wide">Tên món</th>
              <th class="px-2 py-2 text-right font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Đơn giá</th>
              <th class="px-2 py-2 text-center font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Số lượng</th>
              <th class="px-2 py-2 text-right font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Giảm giá</th>
              <th class="px-2 py-2 text-right font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Phụ thu</th>
              <th class="px-2 py-2 text-center font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">ĐVT</th>
              <th class="px-2 py-2 text-center font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Thuế</th>
              <th class="px-2 py-2 text-right font-bold border-r border-sky-100 text-[11px] uppercase tracking-wide">Tổng tiền</th>
              <th class="px-2 py-2 text-center font-bold text-[11px] uppercase tracking-wide w-8"></th>
            </tr>
          </thead>
          <tbody v-for="group in groupedBillItems" :key="group.name">
            <!-- Group Header -->
            <tr class="bg-sky-50/50 border-b border-sky-100 cursor-pointer" @click="toggleGroup(group.name)">
               <td class="px-2 py-1.5 text-center border-r border-sky-100 w-8" @click.stop>
                  <input type="checkbox" :checked="isGroupFullySelected(group)" @change="toggleGroupSelection(group, $event)" class="rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
               </td>
               <td colspan="10" class="px-2 py-1.5 font-bold text-sky-800 text-xs uppercase tracking-wide">
                  <div class="flex items-center gap-2">
                    <button class="w-4 h-4 bg-sky-200 text-sky-700 rounded-sm inline-flex items-center justify-center font-bold pb-0.5">
                       {{ isGroupExpanded(group.name) ? '-' : '+' }}
                    </button>
                    <span>{{ group.name }}</span>
                  </div>
               </td>
            </tr>
            <!-- Group Items -->
            <template v-for="item in group.items" :key="item.id">
              <tr v-show="isGroupExpanded(group.name)" class="border-b border-slate-100 hover:bg-sky-50 transition-colors cursor-pointer" @dblclick="handleEditCombo(item)">
                <td class="px-2 py-2 text-center border-r border-slate-100">
                  <input type="checkbox" v-model="item.selected" class="rounded border-slate-300 text-sky-500 focus:ring-sky-500" />
                </td>
                <td class="px-3 py-2 border-r border-slate-100 text-[12px]">
                  <div class="flex flex-col gap-0.5">
                    <span class="font-semibold text-slate-800">{{ item.product?.name || item.name }}</span>
                    <input 
                      type="text" 
                      v-model="item.note" 
                      placeholder="Thêm ghi chú món..." 
                      class="text-[10px] text-slate-400 italic bg-transparent border-b border-transparent hover:border-slate-200 focus:border-sky-300 focus:outline-none w-full py-0 px-0 h-4"
                      @change="isDirty = true"
                    />
                  </div>
                </td>
                <td class="px-2 py-2 text-right border-r border-slate-100 text-slate-600 text-[11px]">{{ Number(item.price).toLocaleString('vi-VN') }}</td>
                <td class="px-2 py-2 text-center border-r border-slate-100">
                  <input type="number" step="any" min="0" v-model="item.quantity" class="w-14 text-center bg-transparent border border-slate-200 rounded focus:outline-none focus:ring-1 focus:ring-sky-300 py-0.5 font-bold text-slate-800" />
                </td>
                <td class="px-2 py-2 text-right border-r border-slate-100 text-rose-500 text-[11px]">{{ Number(item.discount || 0).toLocaleString('vi-VN') }}</td>
                <td class="px-2 py-2 text-right border-r border-slate-100 text-emerald-600 text-[11px]">{{ Number(item.surcharge || 0).toLocaleString('vi-VN') }}</td>
                <td class="px-2 py-2 text-center border-r border-slate-100 text-slate-600 text-[11px]">{{ item.product?.unit?.name || '' }}</td>
                <td class="px-2 py-2 text-center border-r border-slate-100 text-slate-600 text-[11px]">{{ item.product?.tax_percent ? item.product.tax_percent + '%' : '' }}</td>
                <td class="px-2 py-2 text-right border-r border-slate-100 font-bold text-emerald-700 text-[12px]">{{ Number(Math.max(0, item.price * item.quantity - (item.discount || 0) + (item.surcharge || 0))).toLocaleString('vi-VN') }}</td>
                <td class="px-2 py-2 text-center">
                  <button @click="removeBillItem(item)" class="text-rose-300 hover:text-rose-600 transition-colors p-0.5 rounded hover:bg-rose-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </td>
              </tr>
              <!-- Sub Items -->
              <template v-if="item.sub_items && item.sub_items.length">
                <tr v-show="isGroupExpanded(group.name)" v-for="(subItem, subIdx) in item.sub_items" :key="'sub_' + (subItem.id || subIdx)" class="border-b border-slate-50 bg-slate-50 hover:bg-sky-50 transition-colors">
                  <td class="px-2 py-1.5 text-center border-r border-slate-100"></td>
                  <td class="px-3 py-1.5 border-r border-slate-100 text-[11px] pl-6">
                    <div class="flex items-center gap-2">
                      <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                      <div class="flex flex-col gap-0.5 flex-1 min-w-0">
                        <span class="text-slate-600 font-medium">{{ subItem.product?.name || subItem.name }}</span>
                        <input 
                          type="text" 
                          v-model="subItem.note" 
                          placeholder="Thêm ghi chú..." 
                          class="text-[9px] text-slate-400 italic bg-transparent border-b border-transparent hover:border-slate-200 focus:border-sky-300 focus:outline-none w-full py-0 px-0 h-3.5"
                          @change="isDirty = true"
                        />
                      </div>
                    </div>
                  </td>
                  <td class="px-2 py-1.5 text-right border-r border-slate-100 text-slate-500 text-[11px]">{{ Number(subItem.price).toLocaleString('vi-VN') }}</td>
                  <td class="px-2 py-1.5 text-center border-r border-slate-100 text-slate-700 text-[11px] font-medium">{{ subItem.quantity }}</td>
                  <td class="px-2 py-1.5 text-right border-r border-slate-100"></td>
                  <td class="px-2 py-1.5 text-right border-r border-slate-100"></td>
                  <td class="px-2 py-1.5 text-center border-r border-slate-100 text-slate-500 text-[11px]">{{ subItem.product?.unit?.name || '' }}</td>
                  <td class="px-2 py-1.5 text-center border-r border-slate-100 text-slate-500 text-[11px]">{{ subItem.product?.tax_percent ? subItem.product.tax_percent + '%' : '' }}</td>
                  <td class="px-2 py-1.5 text-right border-r border-slate-100 font-medium text-slate-600 text-[11px]">{{ Number(subItem.price * subItem.quantity).toLocaleString('vi-VN') }}</td>
                  <td class="px-2 py-1.5 text-center"></td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-else-if="!activeBill || activeBill.items.length === 0" class="flex-1 flex flex-col items-center justify-center text-slate-300">
          <div class="relative">
            <svg class="w-12 h-12 text-slate-200 drop-shadow-sm" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <div class="absolute -top-1 -right-1 w-5 h-5 bg-white rounded-full flex items-center justify-center shadow-sm border border-slate-100">
               <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
            </div>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide mt-2">Trống</span>
        </div>
      </div>

      <!-- Bottom footer -->
      <div class="px-3 py-2.5 shrink-0 bg-white border-t border-slate-200 shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
        <!-- Total amount -->
        <div class="flex justify-between items-center mb-2.5">
          <span class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Tổng cộng</span>
          <div class="flex items-center gap-2">
            <span class="font-extrabold text-emerald-600 text-2xl tracking-tight">
              {{ activeBill?.items.reduce((sum, i) => sum + Math.max(0, (i.price * i.quantity) - (i.discount || 0) + (i.surcharge || 0)), 0).toLocaleString('vi-VN') }}
            </span>
            <span class="text-slate-400 font-bold text-sm">đ</span>
            <div class="w-4 h-4 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold cursor-help" title="Thông tin chi tiết thuế phí">i</div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center justify-between relative w-full">
          <div id="action-menu-container" class="flex items-center gap-2 relative">
            <button @click="showActionMenu = !showActionMenu" class="bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg p-2 transition shadow-sm border border-slate-200">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
            </button>
            <button @click="handleSave" class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-lg text-sm font-bold transition shadow-sm flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
              Lưu
            </button>

            <!-- Dropdown menu -->
            <div v-if="showActionMenu" class="absolute bottom-full left-0 mb-2 w-52 bg-white border border-slate-200 shadow-xl rounded-xl py-1.5 z-50">
              <button @click="addBill" class="w-full text-left px-3 py-2 text-xs font-semibold text-sky-600 hover:bg-sky-50 transition-colors flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tạo bill mới
              </button>
              <button
                @click="showMergeBillsModal = true"
                :disabled="bills.length <= 1"
                :class="bills.length <= 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-50 cursor-pointer'"
                class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold transition-colors"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Gộp đơn
              </button>
              <div class="border-t border-slate-100 my-1"></div>
              <button @click="openSplitByItemModal" :disabled="!hasSelectedItems" :class="hasSelectedItems ? 'text-slate-600 hover:bg-slate-50 cursor-pointer' : 'text-slate-300 cursor-not-allowed'" class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                Tách đơn theo món
              </button>
              <button @click="openSplitByAmountModal" class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Tách đơn theo số tiền
              </button>
              <div class="border-t border-slate-100 my-1"></div>
              <button @click="isTransferTableModalOpen = true" class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m-4 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Chuyển bàn
              </button>
              <button @click="openTransferItemsModal" :disabled="!hasSelectedItems" :class="hasSelectedItems ? 'text-slate-600 hover:bg-slate-50 cursor-pointer' : 'text-slate-300 cursor-not-allowed'" class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                Chuyển món
              </button>
              <div class="border-t border-slate-100 my-1"></div>
              <button class="w-full text-left px-3 py-2 text-xs flex items-center gap-2 font-semibold text-rose-500 hover:bg-rose-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Xóa đơn
              </button>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="handlePrintBill" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-1.5 transition shadow-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
              In Bill
            </button>
            <button @click="handlePrintGuestCheck" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
              Tạm tính
            </button>
            <button class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2 rounded-lg text-sm font-bold transition shadow-sm flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              Thanh toán
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Hidden Print Templates -->
    <GuestCheckPrintTemplate 
      v-if="activeBill"
      :bill="activeBill"
      :table-name="props.table?.name || 'Mang đi'"
    />

    <!-- Modals -->
    <CustomerInfoModal
      :is-open="isCustomerModalOpen"
      :initial-data="billInfo"
      @close="isCustomerModalOpen = false"
      @save="handleSaveCustomerInfo"
    />
    
    <InternalNoteModal
      :is-open="isInternalNoteModalOpen"
      :initial-data="billInfo"
      @close="isInternalNoteModalOpen = false"
      @save="handleSaveInternalNote"
    />

    <SplitByItemModal
      :is-open="isSplitByItemModalOpen"
      :items="itemsToSplit"
      @close="isSplitByItemModalOpen = false"
      @confirm="handleConfirmSplitByItem"
    />

    <SplitByAmountModal
      :is-open="isSplitByAmountModalOpen"
      :current-total="currentBillTotal"
      @close="isSplitByAmountModalOpen = false"
      @confirm="handleConfirmSplitByAmount"
    />

    <OpenItemModal
      :is-open="isOpenItemModalOpen"
      :products="openItems"
      @close="isOpenItemModalOpen = false"
      @add="handleAddProduct"
    />

    <PrintTicketsModal
      :is-open="isPrintTicketsModalOpen"
      :tickets="printedTickets"
      @close="isPrintTicketsModalOpen = false"
      @print-item="handlePrintItem"
      @print-all="handlePrintAll"
    />

    <MergeBillsModal 
      :is-open="showMergeBillsModal" 
      :bills="bills" 
      @close="showMergeBillsModal = false" 
      @confirm="handleConfirmMergeBills" 
    />
    
    <TransferItemsModal
      :is-open="isTransferItemsModalOpen"
      :current-table="table"
      :items="itemsToSplit"
      @close="isTransferItemsModalOpen = false"
      @transfer="handleConfirmTransferItems"
    />

    <TransferTableModal
      :is-open="isTransferTableModalOpen"
      :current-table="table"
      @close="isTransferTableModalOpen = false"
      @transfer="handleTransferTable"
    />

    <ComboSelectionModal
      :is-open="isComboModalOpen"
      :product="selectedComboProduct"
      :editing-item="editingBillItem"
      @close="handleComboClose"
      @confirm="handleComboConfirm"
    />

  </div>
</template>





