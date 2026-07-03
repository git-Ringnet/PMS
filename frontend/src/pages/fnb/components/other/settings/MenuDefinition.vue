<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchOutlets } from '@/services/outlet-service'
import { 
  fetchProductCategories, 
  createProductCategory, 
  updateProductCategory, 
  deleteProductCategory, 
  fetchProducts, 
  createProduct, 
  updateProduct, 
  deleteProduct, 
  fetchUnitsOfMeasure,
  bulkUpdateProductStatus
} from '@/services/product-service'

import PrinterTab from './tabs/PrinterTab.vue'
import PromotionTab from './tabs/PromotionTab.vue'
import AddProductCategoryModal from './modals/AddProductCategoryModal.vue'
import AddProductModal from './modals/AddProductModal.vue'
import BulkEditOutletActiveModal from './modals/BulkEditOutletActiveModal.vue'
import ConfirmDeleteModal from './tabs/ConfirmDeleteModal.vue'

defineEmits(['back'])

const uiStore = useUiStore()

const tabs = ['Thực đơn', 'Máy in', 'Chương trình khuyến mãi']
const activeSubTab = ref('Thực đơn')

const isMenuEnabled = ref(true)
const selectedFilter = ref('Tất cả')

// Selection state
const selectedProducts = ref(new Set())
const isBulkEditModalOpen = ref(false)

// Data stores
const categories = ref([])
const productsList = ref([])
const outletsList = ref([])
const unitsList = ref([])

const activeCategory = ref('all')
const isLoading = ref(false)

// Confirm Delete Modal State
const confirmDeleteModal = ref({
  isOpen: false,
  title: 'Xác nhận xóa',
  message: '',
  action: null
})

const showConfirmDelete = (title, message, action) => {
  confirmDeleteModal.value = {
    isOpen: true,
    title,
    message,
    action
  }
}

const executeConfirmDelete = async () => {
  if (confirmDeleteModal.value.action) {
    await confirmDeleteModal.value.action()
  }
  confirmDeleteModal.value.isOpen = false
}

const collapsedCategories = ref(new Set())

const toggleCollapse = (id) => {
  if (collapsedCategories.value.has(id)) {
    collapsedCategories.value.delete(id)
  } else {
    collapsedCategories.value.add(id)
  }
}

const isCategoryVisible = (cat) => {
  let pid = cat.parent_id
  while (pid !== null) {
    if (collapsedCategories.value.has(pid)) {
      return false
    }
    const parent = categories.value.find(c => c.id === pid)
    pid = parent ? parent.parent_id : null
  }
  return true
}

const hasChildren = (id) => {
  return categories.value.some(c => c.parent_id === id)
}

// Modals toggles
const isCategoryModalOpen = ref(false)
const editingCategory = ref(null)

const isProductModalOpen = ref(false)
const editingProduct = ref(null)

// Search fields
const searchCode = ref('')
const searchName = ref('')
const searchShort = ref('')
const searchUnit = ref('')
const searchOpenItem = ref('')
const searchNote = ref('')
const searchServiceGroup = ref('')
const searchCategory = ref('')
const searchIsAlcohol = ref('')
const searchTrackStock = ref('')

const sortConfig = ref({ key: '', direction: '' })

const toggleSort = (key) => {
  if (sortConfig.value.key !== key) {
    sortConfig.value = { key, direction: 'asc' }
  } else if (sortConfig.value.direction === 'asc') {
    sortConfig.value = { key, direction: 'desc' }
  } else {
    sortConfig.value = { key: '', direction: '' }
  }
}

const resetFilters = () => {
  searchCode.value = ''
  searchName.value = ''
  searchShort.value = ''
  searchUnit.value = ''
  searchOpenItem.value = ''
  searchNote.value = ''
  searchServiceGroup.value = ''
  searchCategory.value = ''
  searchIsAlcohol.value = ''
  searchTrackStock.value = ''
  sortConfig.value = { key: '', direction: '' }
}

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

// Flat hierarchical categories list helper
const formattedCategoriesList = computed(() => {
  const build = (list, pid = null, depth = 0) => {
    let result = []
    const items = list.filter(item => item.parent_id === pid)
    items.forEach(item => {
      result.push({
        ...item,
        depth
      })
      result = result.concat(build(list, item.id, depth + 1))
    })
    return result
  }
  return build(categories.value, null, 0)
})

// Dynamic filters list (Tất cả + Outlets names + Không bán)
const dynamicFilters = computed(() => {
  const list = ['Tất cả']
  outletsList.value.forEach(o => {
    if (o.name && !list.includes(o.name)) {
      list.push(o.name)
    }
  })
  list.push('Không bán')
  return list
})

// Loading data wrapper
const loadData = async () => {
  isLoading.value = true
  try {
    const [catsRes, prodsRes, outletsRes, unitsRes] = await Promise.all([
      fetchProductCategories(),
      fetchProducts(),
      fetchOutlets(),
      fetchUnitsOfMeasure()
    ])
    categories.value = catsRes.data || []
    productsList.value = prodsRes.data || []
    outletsList.value = (outletsRes.data || []).filter(o => o.is_active)
    unitsList.value = unitsRes.data?.data || unitsRes.data || []
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải dữ liệu thực đơn!', 'error')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadData()
})

// Filtered Menu items
const filteredMenuItems = computed(() => {
  let result = productsList.value.filter(item => {
    // 1. Filter by category
    if (activeCategory.value !== 'all') {
      // Show if it belongs to selected category or its descendants
      const categoryIds = new Set([Number(activeCategory.value)])
      const findDescendants = (pid) => {
        categories.value.forEach(c => {
          if (c.parent_id === pid) {
            categoryIds.add(c.id)
            findDescendants(c.id)
          }
        })
      }
      findDescendants(Number(activeCategory.value))
      if (!categoryIds.has(item.fb_product_category_id)) {
        return false
      }
    }

    // 2. Filter by Pills
    if (selectedFilter.value === 'Không bán') {
      if (item.is_active) return false
    } else if (selectedFilter.value !== 'Tất cả') {
      // An outlet specific tab is selected
      if (!item.is_active) return false // globally inactive means not available anywhere

      const targetOutlet = outletsList.value.find(o => o.name === selectedFilter.value)
      if (targetOutlet) {
        const op = item.outlet_prices?.find(o => o.outlet_id === targetOutlet.id)
        if (op) {
          if (!op.is_active) return false
        } else {
          // If no specific outlet record exists, we already know item.is_active is true
        }
      }
    }

    // 3. Filter by search fields
    if (searchCode.value && !(item.product_code || '').toLowerCase().includes(searchCode.value.toLowerCase())) return false
    if (searchName.value && !item.name.toLowerCase().includes(searchName.value.toLowerCase())) return false
    if (searchShort.value && !(item.short_name || '').toLowerCase().includes(searchShort.value.toLowerCase())) return false

    if (searchUnit.value) {
      const uName = unitsList.value.find(u => u.id === item.unit_id)?.name || ''
      if (!uName.toLowerCase().includes(searchUnit.value.toLowerCase())) return false
    }

    if (searchOpenItem.value !== '') {
      if (searchOpenItem.value === '1' && Number(item.open_key) !== 1) return false
      if (searchOpenItem.value === '0' && Number(item.open_key) === 1) return false
    }

    if (searchNote.value && !(item.note || '').toLowerCase().includes(searchNote.value.toLowerCase())) return false

    if (searchServiceGroup.value && !(item.service_group || '').toLowerCase().includes(searchServiceGroup.value.toLowerCase())) return false

    if (searchCategory.value) {
      const cName = categories.value.find(c => c.id === item.fb_product_category_id)?.name || ''
      if (!cName.toLowerCase().includes(searchCategory.value.toLowerCase())) return false
    }

    if (searchIsAlcohol.value !== '') {
      if (searchIsAlcohol.value === '1' && Number(item.is_alcohol) !== 1) return false
      if (searchIsAlcohol.value === '0' && Number(item.is_alcohol) === 1) return false
    }

    if (searchTrackStock.value !== '') {
      if (searchTrackStock.value === '1' && Number(item.track_stock) !== 1) return false
      if (searchTrackStock.value === '0' && Number(item.track_stock) === 1) return false
    }

    return true
  }).map(item => {
    if (selectedFilter.value !== 'Tất cả' && selectedFilter.value !== 'Không bán') {
      const targetOutlet = outletsList.value.find(o => o.name === selectedFilter.value)
      if (targetOutlet) {
        const op = item.outlet_prices?.find(o => o.outlet_id === targetOutlet.id)
        if (op) {
          let overridePrice = null;
          let overrideOriginal = null;
          let overrideService = null;
          let overrideSpecial = null;
          let overrideTax = null;

          if (item.is_combo) {
            if (op.update_combo_price) {
              overridePrice = op.combo_price;
              overrideOriginal = op.combo_original;
              overrideService = op.combo_service;
              overrideSpecial = op.combo_special;
              overrideTax = op.combo_tax;
            } else if (op.update_price) {
              overridePrice = op.price;
              overrideOriginal = op.original_amount;
              overrideService = op.service_charge_percent;
              overrideSpecial = op.special_tax_percent;
              overrideTax = op.tax_percent;
            }
          } else if (op.update_price) {
            overridePrice = op.price;
            overrideOriginal = op.original_amount;
            overrideService = op.service_charge_percent;
            overrideSpecial = op.special_tax_percent;
            overrideTax = op.tax_percent;
          }

          if (overridePrice !== null) {
            return {
              ...item,
              price: overridePrice,
              original_amount: overrideOriginal,
              service_charge_percent: overrideService,
              special_tax_percent: overrideSpecial,
              tax_percent: overrideTax
            }
          }
        }
      }
    }
    return item
  })

  // Sort inactive items to the bottom always
  result.sort((a, b) => {
    // Primary sort: is_active (true before false)
    if (a.is_active !== b.is_active) {
      return a.is_active ? -1 : 1
    }
    
    // Secondary sort: if sortConfig is set
    if (sortConfig.value.key) {
      const key = sortConfig.value.key
      const dir = sortConfig.value.direction === 'asc' ? 1 : -1
      const valA = Number(a[key]) || 0
      const valB = Number(b[key]) || 0
      return (valA - valB) * dir
    }
    
    // If no sort config and same is_active, maintain original order or sort by id
    return (a.id || 0) - (b.id || 0)
  })
  return result
})

// Categories Operations
const openAddCategory = () => {
  editingCategory.value = null
  isCategoryModalOpen.value = true
}

const openEditCategory = (cat) => {
  editingCategory.value = cat
  isCategoryModalOpen.value = true
}

const confirmDeleteCategory = (cat) => {
  showConfirmDelete(
    'Xóa nhóm món',
    `Bạn có chắc chắn muốn xóa nhóm món "${cat.name}"?`,
    async () => {
      try {
        isLoading.value = true
        await deleteProductCategory(cat.id)
        uiStore.showToast('Xóa nhóm món thành công!', 'success')
        if (activeCategory.value === cat.id) {
          activeCategory.value = 'all'
        }
        await loadData()
      } catch (err) {
        uiStore.showToast('Không thể xóa nhóm món này!', 'error')
      } finally {
        isLoading.value = false
      }
    }
  )
}

const saveCategory = async (formData) => {
  try {
    isLoading.value = true
    if (editingCategory.value) {
      await updateProductCategory(editingCategory.value.id, formData)
      uiStore.showToast('Cập nhật nhóm món thành công!', 'success')
    } else {
      await createProductCategory(formData)
      uiStore.showToast('Thêm nhóm món mới thành công!', 'success')
    }
    isCategoryModalOpen.value = false
    await loadData()
  } catch (err) {
    uiStore.showToast('Lưu thông tin nhóm món thất bại!', 'error')
  } finally {
    isLoading.value = false
  }
}

// Products Operations
const openAddProduct = () => {
  editingProduct.value = null
  isProductModalOpen.value = true
}

const openEditProduct = (prod) => {
  const originalProd = productsList.value.find(p => p.id === prod.id)
  editingProduct.value = originalProd || prod
  isProductModalOpen.value = true
}

const confirmDeleteProduct = (prod) => {
  showConfirmDelete(
    'Xóa món ăn',
    `Bạn có chắc chắn muốn xóa món ăn "${prod.name}"?`,
    async () => {
      try {
        isLoading.value = true
        await deleteProduct(prod.id)
        uiStore.showToast('Xóa món ăn thành công!', 'success')
        await loadData()
      } catch (err) {
        uiStore.showToast('Không thể xóa món ăn này!', 'error')
      } finally {
        isLoading.value = false
      }
    }
  )
}

const saveProduct = async (formData) => {
  try {
    isLoading.value = true
    if (editingProduct.value) {
      await updateProduct(editingProduct.value.id, formData)
      uiStore.showToast('Cập nhật món ăn thành công!', 'success')
    } else {
      await createProduct(formData)
      uiStore.showToast('Thêm món ăn mới thành công!', 'success')
    }
    isProductModalOpen.value = false
    await loadData()
  } catch (err) {
    uiStore.showToast('Lưu thông tin món ăn thất bại!', 'error')
  } finally {
    isLoading.value = false
  }
}

// Bulk Actions Logic
const toggleSelectAll = (e) => {
  if (e.target.checked) {
    filteredMenuItems.value.forEach(item => selectedProducts.value.add(item.id))
  } else {
    selectedProducts.value.clear()
  }
}

const isAllSelected = computed(() => {
  if (filteredMenuItems.value.length === 0) return false
  return filteredMenuItems.value.every(item => selectedProducts.value.has(item.id))
})

const handleBulkEditOutlets = () => {
  if (selectedProducts.value.size === 0) return
  isBulkEditModalOpen.value = true
}

const saveBulkEditOutlets = async (payload) => {
  try {
    isLoading.value = true
    await bulkUpdateProductStatus({
      ids: Array.from(selectedProducts.value),
      action: 'set_outlets',
      outlets: payload
    })
    uiStore.showToast('Cập nhật trạng thái mở bán theo outlet thành công!', 'success')
    isBulkEditModalOpen.value = false
    selectedProducts.value.clear()
    await loadData()
  } catch (err) {
    uiStore.showToast('Có lỗi xảy ra khi cập nhật trạng thái mở bán', 'error')
  } finally {
    isLoading.value = false
  }
}

const handleBulkToggleGlobalActive = async () => {
  if (selectedProducts.value.size === 0) return
  try {
    isLoading.value = true
    await bulkUpdateProductStatus({
      ids: Array.from(selectedProducts.value),
      action: 'toggle_global'
    })
    uiStore.showToast('Tắt/mở bán hàng loạt thành công!', 'success')
    selectedProducts.value.clear()
    await loadData()
  } catch (err) {
    uiStore.showToast('Có lỗi xảy ra khi cập nhật trạng thái mở bán', 'error')
  } finally {
    isLoading.value = false
  }
}

</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-hidden relative">
    
    <!-- Loading overlay (PMS Style spinner) -->
    <div v-if="isLoading" class="absolute inset-0 z-50 flex items-center justify-center bg-white/50 backdrop-blur-xs">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Header -->
    <div class="flex items-center gap-2 mb-4 shrink-0">
      <button @click="$emit('back')" class="p-1.5 rounded-full hover:bg-slate-200 text-slate-600 transition active:scale-95" title="Quay lại">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-base font-bold text-slate-800">Định nghĩa thực đơn</h1>
    </div>

    <!-- Navigation Tabs & Right Filter Toggle row -->
    <div class="flex flex-wrap items-center justify-between border-b border-slate-200 mb-5 pb-0.5 shrink-0 gap-4">
      <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-400">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="activeSubTab = tab"
          class="pb-2 border-b-2 transition-all duration-200 relative"
          :class="activeSubTab === tab ? 'border-sky-500 text-sky-600' : 'border-transparent hover:text-slate-600'"
        >
          {{ tab }}
          <span v-if="activeSubTab === tab" class="absolute bottom-0 left-0 right-0 h-[2px] bg-sky-500 rounded-full"></span>
        </button>
      </div>

      <!-- Right Toggle & Filter Pill select -->
      <div v-if="activeSubTab === 'Thực đơn'" class="flex items-center gap-4 text-xs font-semibold pb-2">
        <!-- Thực đơn Toggle -->
        <div class="flex items-center gap-2">
          <span class="text-slate-600">Thực đơn</span>
          <button @click="isMenuEnabled = !isMenuEnabled" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isMenuEnabled ? 'bg-[#78C5E7]' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isMenuEnabled ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>

        <!-- Filter Pills Group -->
        <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200 shadow-xs">
          <button
            v-for="f in dynamicFilters"
            :key="f"
            @click="selectedFilter = f"
            class="px-3 py-1 rounded-md text-[10px] font-bold uppercase transition active:scale-95"
            :class="selectedFilter === f ? 'bg-[#78C5E7] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'"
          >
            {{ f }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tab Content -->
    <template v-if="activeSubTab === 'Thực đơn'">
      <div v-if="isMenuEnabled" class="flex-1 flex gap-6 overflow-hidden min-h-0">
        
        <!-- Left sidebar categories -->
        <div class="w-56 bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex flex-col shrink-0">
          <button @click="openAddCategory" class="w-full flex items-center justify-center gap-2 bg-[#e2f3fc] border border-sky-100 hover:bg-[#d0ebfa] text-sky-700 py-2 rounded-lg text-xs font-bold shadow-sm active:scale-[0.98] transition mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Thêm loại thực đơn
          </button>

          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider px-2 mb-2 block">Loại thực đơn</span>
          
          <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
            <!-- All categories root button -->
            <button
              @click="activeCategory = 'all'"
              class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition relative overflow-hidden mb-2"
              :class="activeCategory === 'all' ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-100'"
            >
              <span v-if="activeCategory === 'all'" class="absolute left-0 top-1 bottom-1 w-[3px] rounded-r-full bg-sky-500"></span>
              TẤT CẢ NHÓM MÓN
            </button>

            <!-- Hierarchical list categories -->
            <template v-for="c in formattedCategoriesList" :key="c.id">
              <div 
                v-if="isCategoryVisible(c)"
                class="w-full flex items-center justify-between group rounded-lg text-xs font-semibold transition relative overflow-hidden pr-2"
                :class="activeCategory === c.id ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-650 hover:bg-slate-100'"
              >
                <div class="flex items-center flex-1 min-w-0" :style="{ paddingLeft: (c.depth * 12 + 6) + 'px' }">
                  <!-- Toggle Button -->
                  <button
                    v-if="hasChildren(c.id)"
                    @click.stop="toggleCollapse(c.id)"
                    class="p-1 hover:bg-slate-200 rounded text-slate-500 transition-transform duration-150 shrink-0 mr-1"
                    :class="{ 'rotate-0': !collapsedCategories.has(c.id), '-rotate-90': collapsedCategories.has(c.id) }"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                  <span v-else class="w-5 shrink-0"></span>

                  <button
                    @click="activeCategory = c.id"
                    class="flex-1 text-left py-2 px-1 focus:outline-none truncate"
                  >
                    <span v-if="activeCategory === c.id" class="absolute left-0 top-1 bottom-1 w-[3px] rounded-r-full bg-sky-500"></span>
                    {{ c.name }}
                  </button>
                </div>
                
                <!-- Hover Quick Action buttons -->
                <div class="hidden group-hover:flex items-center gap-1.5 shrink-0">
                  <button @click.stop="openEditCategory(c)" class="text-slate-400 hover:text-sky-600 transition" title="Sửa">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                  </button>
                  <button @click.stop="confirmDeleteCategory(c)" class="text-rose-400 hover:text-rose-600 transition" title="Xóa">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Right content area -->
        <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative">
          <!-- Toolbar -->
          <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
              <button @click="openAddProduct" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:shadow active:scale-[0.98] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Thêm thực đơn
              </button>
              
              <template v-if="selectedProducts.size > 0">
                <span class="text-xs font-bold text-sky-600 bg-sky-50 px-3 py-1.5 rounded-md border border-sky-100">
                  Đã chọn: {{ selectedProducts.size }}
                </span>
                
                <button @click="handleBulkEditOutlets" class="flex items-center gap-2 bg-slate-500 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:shadow active:scale-[0.98] transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                  Sửa outlet mở bán
                </button>

                <button @click="handleBulkToggleGlobalActive" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:shadow active:scale-[0.98] transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                  Tắt/mở bán chung
                </button>
              </template>
              
              <template v-else>
                <button class="flex items-center gap-2 bg-slate-300 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm cursor-not-allowed" disabled title="Chọn món ăn để sửa">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                  Sửa outlet mở bán
                </button>

                <button class="flex items-center gap-2 bg-sky-400 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:shadow active:scale-[0.98] transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Thao tác
                </button>
              </template>

              <button @click="resetFilters" class="flex items-center gap-2 bg-rose-400 hover:bg-rose-500 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:shadow active:scale-[0.98] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Reset Filter
              </button>
            </div>
            <!-- Count of items -->
            <div class="flex items-center">
              <span class="text-xs font-bold text-slate-500">
                Tổng số: <span class="text-sky-600 text-sm font-extrabold ml-1">{{ filteredMenuItems.length }}</span> món ăn
              </span>
            </div>
          </div>

          <!-- Table view -->
          <div class="flex-1 overflow-auto">
            <table class="w-full text-xs border-collapse whitespace-nowrap">
              <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
                <tr class="bg-slate-100/50 text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">
                  <th class="px-4 py-3 text-center border-r border-slate-100 min-w-[50px]">
                    <div class="flex items-center justify-center">
                      <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="w-4 h-4 text-sky-500 rounded border-slate-300 focus:ring-sky-500 cursor-pointer">
                    </div>
                  </th>
                  <th class="px-4 py-3 text-center border-r border-slate-100 w-12 shrink-0">
                    Hình ảnh
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[120px]">
                    <div class="flex flex-col gap-1">
                      <span>Mã</span>
                      <input type="text" v-model="searchCode" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[200px]">
                    <div class="flex flex-col gap-1">
                      <span>Tên</span>
                      <input type="text" v-model="searchName" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[140px]">
                    <div class="flex flex-col gap-1">
                      <span>Short Name</span>
                      <input type="text" v-model="searchShort" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-right border-r border-slate-100 min-w-[120px] select-none cursor-pointer hover:bg-slate-50 transition" @click="toggleSort('price')">
                    <div class="flex justify-end items-center gap-1">
                      <span>Đơn giá</span>
                      <div class="flex flex-col">
                        <svg class="w-3 h-3 -mb-1" :class="sortConfig.key === 'price' && sortConfig.direction === 'asc' ? 'text-slate-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l5 5H5l5-5z"/></svg>
                        <svg class="w-3 h-3" :class="sortConfig.key === 'price' && sortConfig.direction === 'desc' ? 'text-sky-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5-5h10l-5 5z"/></svg>
                      </div>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[100px]">
                    <div class="flex flex-col gap-1">
                      <span>ĐVT</span>
                      <input type="text" v-model="searchUnit" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-center border-r border-slate-100 min-w-[100px]">
                    <div class="flex flex-col gap-1 items-center">
                      <span>Open item</span>
                      <select v-model="searchOpenItem" class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full">
                        <option value="">Tất cả</option>
                        <option value="1">Có</option>
                        <option value="0">Không</option>
                      </select>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[150px]">
                    <div class="flex flex-col gap-1">
                      <span>Mô tả</span>
                      <input type="text" v-model="searchNote" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-right border-r border-slate-100 min-w-[120px] select-none cursor-pointer hover:bg-slate-50 transition" @click="toggleSort('original_amount')">
                    <div class="flex justify-end items-center gap-1">
                      <span>Giá gốc</span>
                      <div class="flex flex-col">
                        <svg class="w-3 h-3 -mb-1" :class="sortConfig.key === 'original_amount' && sortConfig.direction === 'asc' ? 'text-slate-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l5 5H5l5-5z"/></svg>
                        <svg class="w-3 h-3" :class="sortConfig.key === 'original_amount' && sortConfig.direction === 'desc' ? 'text-sky-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5-5h10l-5 5z"/></svg>
                      </div>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-right border-r border-slate-100 min-w-[120px] select-none cursor-pointer hover:bg-slate-50 transition" @click="toggleSort('service_charge_percent')">
                    <div class="flex justify-end items-center gap-1">
                      <span>Phí phục vụ</span>
                      <div class="flex flex-col">
                        <svg class="w-3 h-3 -mb-1" :class="sortConfig.key === 'service_charge_percent' && sortConfig.direction === 'asc' ? 'text-slate-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l5 5H5l5-5z"/></svg>
                        <svg class="w-3 h-3" :class="sortConfig.key === 'service_charge_percent' && sortConfig.direction === 'desc' ? 'text-sky-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5-5h10l-5 5z"/></svg>
                      </div>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-right border-r border-slate-100 min-w-[120px] select-none cursor-pointer hover:bg-slate-50 transition" @click="toggleSort('special_tax_percent')">
                    <div class="flex justify-end items-center gap-1">
                      <span>Thuế đặc biệt</span>
                      <div class="flex flex-col">
                        <svg class="w-3 h-3 -mb-1" :class="sortConfig.key === 'special_tax_percent' && sortConfig.direction === 'asc' ? 'text-slate-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l5 5H5l5-5z"/></svg>
                        <svg class="w-3 h-3" :class="sortConfig.key === 'special_tax_percent' && sortConfig.direction === 'desc' ? 'text-sky-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5-5h10l-5 5z"/></svg>
                      </div>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-right border-r border-slate-100 min-w-[100px] select-none cursor-pointer hover:bg-slate-50 transition" @click="toggleSort('tax_percent')">
                    <div class="flex justify-end items-center gap-1">
                      <span>VAT</span>
                      <div class="flex flex-col">
                        <svg class="w-3 h-3 -mb-1" :class="sortConfig.key === 'tax_percent' && sortConfig.direction === 'asc' ? 'text-slate-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5l5 5H5l5-5z"/></svg>
                        <svg class="w-3 h-3" :class="sortConfig.key === 'tax_percent' && sortConfig.direction === 'desc' ? 'text-sky-400' : 'text-slate-300/50'" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5-5h10l-5 5z"/></svg>
                      </div>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[120px]">
                    <div class="flex flex-col gap-1">
                      <span>Service Group</span>
                      <input type="text" v-model="searchServiceGroup" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[150px]">
                    <div class="flex flex-col gap-1">
                      <span>Loại thực đơn</span>
                      <input type="text" v-model="searchCategory" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                    </div>
                  </th>
                  <th class="px-4 py-3 text-center border-r border-slate-100 min-w-[90px]">
                    <div class="flex flex-col gap-1 items-center">
                      <span>Có cồn</span>
                      <select v-model="searchIsAlcohol" class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full">
                        <option value="">Tất cả</option>
                        <option value="1">Có</option>
                        <option value="0">Không</option>
                      </select>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-center border-r border-slate-100 min-w-[90px]">
                    <div class="flex flex-col gap-1 items-center">
                      <span>Tồn kho</span>
                      <select v-model="searchTrackStock" class="px-2 py-0.5 border border-slate-200 rounded text-[10px] bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full">
                        <option value="">Tất cả</option>
                        <option value="1">Có</option>
                        <option value="0">Không</option>
                      </select>
                    </div>
                  </th>
                  <th class="px-4 py-3 text-center">Xoá</th>
                </tr>
              </thead>
              <tbody v-if="filteredMenuItems.length > 0" class="bg-white">
                <tr v-for="item in filteredMenuItems" :key="item.id" @dblclick="openEditProduct(item)" class="border-b border-slate-100 transition-colors cursor-pointer" :class="!item.is_active ? 'bg-slate-50/50 text-slate-500 hover:bg-slate-50' : (selectedProducts.has(item.id) ? 'bg-sky-50/30' : 'hover:bg-slate-50/60')">
                  <!-- Checkbox -->
                  <td class="px-4 py-2 border-r border-slate-100 text-center">
                    <input type="checkbox" :value="item.id" v-model="selectedProducts" class="w-4 h-4 text-sky-500 rounded border-slate-300 focus:ring-sky-500 cursor-pointer" @click.stop>
                  </td>
                  <!-- Image -->
                  <td class="px-4 py-2 border-r border-slate-100">
                    <img v-if="item.image" :src="getImageUrl(item.image)" class="w-10 h-10 rounded-lg object-cover border border-slate-250/50" />
                    <div v-else class="w-10 h-10 rounded-lg bg-slate-50 border border-slate-200/50 flex items-center justify-center text-slate-400">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                  </td>
                  <td class="px-4 py-2 border-r border-slate-100 font-bold text-slate-700">{{ item.product_code || '-' }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 font-bold text-slate-800 whitespace-normal break-words min-w-[200px]">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span :class="!item.is_active ? 'text-slate-500 line-through' : ''">{{ item.name }}</span>
                      <span v-if="!item.is_active" class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-500 border border-rose-100 whitespace-nowrap leading-none">Ngừng bán</span>
                    </div>
                  </td>
                  <td class="px-4 py-2 border-r border-slate-100 text-slate-600 font-semibold whitespace-normal break-words min-w-[150px]">{{ item.short_name || '-' }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-right text-slate-900 font-extrabold">{{ Number(item.price).toLocaleString('vi-VN') }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-slate-500 font-extrabold">
                    {{ unitsList.find(u => u.id === item.unit_id)?.name || '-' }}
                  </td>
                  <!-- Open item -->
                  <td class="px-4 py-2 border-r border-slate-100 text-center">
                    <span :class="Number(item.open_key) === 1 ? 'bg-sky-50 text-sky-600 border border-sky-100' : 'bg-slate-50 text-slate-450'" class="px-2 py-0.5 rounded text-[10px] font-bold">
                      {{ Number(item.open_key) === 1 ? 'CÓ' : 'KHÔNG' }}
                    </span>
                  </td>
                  <td class="px-4 py-2 border-r border-slate-100 text-slate-600 whitespace-normal break-words min-w-[200px]" :title="item.note">{{ item.note || '-' }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-right text-slate-600 font-semibold">{{ Number(item.original_amount || 0).toLocaleString('vi-VN') }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-right text-slate-600 font-semibold">{{ Number(item.service_charge_percent || 0).toLocaleString('vi-VN') }}%</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-right text-slate-600 font-semibold">{{ Number(item.special_tax_percent || 0).toLocaleString('vi-VN') }}%</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-right text-slate-600 font-semibold">{{ Number(item.tax_percent || 0).toLocaleString('vi-VN') }}%</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-slate-600 font-semibold">{{ item.service_group || '-' }}</td>
                  <td class="px-4 py-2 border-r border-slate-100 text-slate-700 font-semibold whitespace-normal break-words min-w-[150px]" :title="categories.find(c => c.id === item.fb_product_category_id)?.name">
                    {{ categories.find(c => c.id === item.fb_product_category_id)?.name || '-' }}
                  </td>
                  <!-- Có cồn -->
                  <td class="px-4 py-2 border-r border-slate-100 text-center">
                    <span :class="Number(item.is_alcohol) === 1 ? 'bg-amber-50 text-amber-600 border border-amber-100' : 'bg-slate-50 text-slate-450'" class="px-2 py-0.5 rounded text-[10px] font-bold">
                      {{ Number(item.is_alcohol) === 1 ? 'CÓ' : 'KHÔNG' }}
                    </span>
                  </td>
                  <!-- Tồn kho -->
                  <td class="px-4 py-2 border-r border-slate-100 text-center">
                    <span :class="Number(item.track_stock) === 1 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-50 text-slate-450'" class="px-2 py-0.5 rounded text-[10px] font-bold">
                      {{ Number(item.track_stock) === 1 ? 'CÓ' : 'KHÔNG' }}
                    </span>
                  </td>
                  <!-- Action row edit/delete -->
                  <td class="px-4 py-2 text-center flex items-center justify-center gap-3">
                    <button @click="openEditProduct(item)" class="text-slate-400 hover:text-sky-600 transition" title="Sửa">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button @click="confirmDeleteProduct(item)" class="text-rose-400 hover:text-rose-600 transition" title="Xóa">
                      <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Empty State -->
            <div v-if="filteredMenuItems.length === 0" class="w-full flex flex-col items-center justify-center py-24 text-slate-400">
              <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
              </div>
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Không tìm thấy món ăn nào</span>
            </div>
          </div>
        </div>

      </div>

      <div v-else class="text-center text-slate-400 py-24 font-bold bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col items-center justify-center gap-3">
        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        <span>Danh mục thực đơn đã bị vô hiệu hóa!</span>
      </div>
    </template>
    
    <PrinterTab v-else-if="activeSubTab === 'Máy in'" />
    <PromotionTab v-else-if="activeSubTab === 'Chương trình khuyến mãi'" />

    <!-- Modals declarations -->
    <AddProductCategoryModal
      v-if="isCategoryModalOpen"
      :show="isCategoryModalOpen"
      :category="editingCategory"
      :categories-list="categories"
      :outlet-code="outletsList[0]?.code || 'MT'"
      @close="isCategoryModalOpen = false"
      @save="saveCategory"
    />

    <AddProductModal
      v-if="isProductModalOpen"
      :show="isProductModalOpen"
      :product="editingProduct"
      :categories-list="categories"
      :units-list="unitsList"
      :outlets-list="outletsList"
      :products-list="productsList"
      @close="isProductModalOpen = false"
      @save="saveProduct"
      @open-add-category="openAddCategory"
    />

    <BulkEditOutletActiveModal
      :isOpen="isBulkEditModalOpen"
      :outletsList="outletsList"
      :selectedCount="selectedProducts.size"
      @close="isBulkEditModalOpen = false"
      @save="saveBulkEditOutlets"
    />

    <ConfirmDeleteModal
      :is-open="confirmDeleteModal.isOpen"
      :title="confirmDeleteModal.title"
      :message="confirmDeleteModal.message"
      @close="confirmDeleteModal.isOpen = false"
      @confirm="executeConfirmDelete"
    />
  </div>
</template>
