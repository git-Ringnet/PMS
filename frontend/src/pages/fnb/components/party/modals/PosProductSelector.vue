<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { fetchProducts, fetchProductCategories } from '@/services/product-service'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  initialCart: { type: Array, default: () => [] }
})
const emit = defineEmits(['close', 'save'])

const isLoading = ref(false)
const categories = ref([])
const products = ref([])
const cart = ref([])
const searchQuery = ref('')
const selectedCategoryId = ref(null)

const isSearchDropdownOpen = ref(false)
const isCategoryDropdownOpen = ref(false)

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    cart.value = JSON.parse(JSON.stringify(props.initialCart))
    loadData()
  }
})

const handleClickOutside = (e) => {
  const searchContainer = document.getElementById('pos-search-container')
  if (searchContainer && !searchContainer.contains(e.target) && !e.target.closest('#pos-search-container')) {
    isSearchDropdownOpen.value = false
  }
  const categoryContainer = document.getElementById('pos-category-container')
  if (categoryContainer && !categoryContainer.contains(e.target) && !e.target.closest('#pos-category-container')) {
    isCategoryDropdownOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  loadData()
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

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
  if (!path) return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80'
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  let finalPath = cleanPath
  if (!cleanPath.startsWith('storage/')) finalPath = 'storage/' + cleanPath
  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`
}

const isProductInCart = (id) => {
  return cart.value.some(item => item.product_id === id)
}

const loadData = async () => {
  isLoading.value = true
  try {
    const [catsRes, prodsRes] = await Promise.all([
      fetchProductCategories(),
      fetchProducts()
    ])
    categories.value = catsRes.data?.data || catsRes.data || []
    products.value = prodsRes.data?.data || prodsRes.data || []
  } catch (err) {
    console.error('Lỗi tải dữ liệu POS:', err)
  } finally {
    isLoading.value = false
  }
}

const filteredProducts = computed(() => {
  let filtered = products.value
  if (selectedCategoryId.value) {
    filtered = filtered.filter(p => p.category_id === selectedCategoryId.value)
  }
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p => 
      p.name.toLowerCase().includes(query) || 
      (p.product_code && p.product_code.toLowerCase().includes(query))
    )
  }
  return filtered
})

const addToCart = (prod) => {
  const existing = cart.value.find(item => item.product_id === prod.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({
      id: Date.now() + Math.random(),
      product_id: prod.id,
      code: prod.code || prod.product_code,
      name: prod.name,
      price: prod.price,
      quantity: 1,
      discount: 0,
      unit: prod.unit?.name || 'Phần',
      note: ''
    })
  }
}

const removeFromCart = (idx) => {
  cart.value.splice(idx, 1)
}

const incQty = (item) => item.quantity++
const decQty = (item) => {
  if (item.quantity > 1) item.quantity--
}

const cartTotalAmount = computed(() => {
  return cart.value.reduce((sum, item) => {
    return sum + (Number(item.price) * Number(item.quantity) - Number(item.discount || 0))
  }, 0)
})

const cartTotalItems = computed(() => {
  return cart.value.reduce((sum, item) => sum + Number(item.quantity), 0)
})

const handleSave = () => {
  emit('save', JSON.parse(JSON.stringify(cart.value)))
  cart.value = []
  emit('close')
}

const handleClose = () => {
  cart.value = []
  emit('close')
}

const formatNum = (val) => {
  if (!val) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-[100] flex bg-slate-100 flex-col overflow-hidden">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shrink-0 shadow-sm z-10">
      <h2 class="text-lg font-extrabold text-slate-800">Thêm món ăn</h2>
      <button @click="handleClose" class="text-slate-400 hover:text-rose-500 transition-colors p-2 rounded-full hover:bg-slate-100 cursor-pointer">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </header>

    <div class="flex-1 flex overflow-hidden">
      <!-- Left side: Menu -->
      <div class="w-[320px] flex flex-col border-r border-slate-200 bg-white shrink-0">
        <!-- Replaced with TableDetail Sidebar style -->
        <div class="p-3 bg-white border-b border-slate-200 flex flex-col items-center">
          <h2 class="text-center font-bold text-slate-500 text-sm tracking-wide flex-1 w-full uppercase">NHÓM THỰC ĐƠN</h2>
          <h3 class="text-center font-bold text-slate-800 text-sm tracking-wide mt-1 uppercase">{{ categories.find(c => c.id === selectedCategoryId)?.name || 'TẤT CẢ' }}</h3>
        </div>
        <div class="p-3 border-b border-slate-200 flex flex-col gap-3 relative">
          <div class="flex items-center gap-2">
            <div id="pos-search-container" class="relative flex-1">
              <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <input type="text" v-model="searchQuery" @focus="isSearchDropdownOpen = true" class="block w-full pl-8 pr-3 py-1.5 border border-slate-300 rounded text-sm focus:outline-none focus:border-sky-500 bg-white shadow-inner" placeholder="Tìm món" />
              
              <!-- Search Dropdown -->
              <div v-if="isSearchDropdownOpen" class="absolute top-full left-0 mt-1 w-[35rem] max-w-[85vw] bg-white border border-slate-200 shadow-xl rounded-lg z-[110] max-h-[60vh] overflow-y-auto flex flex-col gap-0.5 p-1">
                <div v-if="filteredProducts.length === 0" class="p-3 text-center text-slate-500 text-sm">Không tìm thấy món ăn</div>
                <div v-for="prod in filteredProducts" :key="'search-'+prod.id" class="flex items-center justify-between p-1.5 border-b border-slate-100 bg-white hover:bg-sky-50 transition-colors cursor-pointer" @click.stop="addToCart(prod); isSearchDropdownOpen = false; searchQuery = ''">
                  <div class="flex items-center gap-2">
                     <span class="text-[11px] font-bold text-slate-400 w-10 truncate">{{ prod.code || prod.product_code }}</span>
                     <span class="text-xs font-semibold text-slate-800 line-clamp-1">{{ prod.name }}</span>
                  </div>
                  <div class="flex items-center gap-3 shrink-0">
                     <span class="text-xs font-bold text-emerald-600 whitespace-nowrap">{{ formatNum(prod.price) }} ₫</span>
                     <button class="bg-sky-100 text-sky-600 hover:bg-sky-500 hover:text-white p-1 rounded transition-colors" title="Thêm vào giỏ">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
                     </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Custom Dropdown for Categories -->
          <div id="pos-category-container" class="relative w-full">
            <button @click.stop="isCategoryDropdownOpen = !isCategoryDropdownOpen" class="w-full flex items-center justify-between border border-slate-300 rounded px-2 py-1.5 text-sm bg-white font-semibold text-slate-700 uppercase focus:outline-none focus:border-sky-500 cursor-pointer">
              <span class="break-words text-left flex-1 pr-2">{{ categories.find(c => c.id === selectedCategoryId)?.name || 'TẤT CẢ DANH MỤC' }}</span>
              <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            
            <div v-if="isCategoryDropdownOpen" @click.stop class="absolute top-full left-0 mt-1 w-full sm:w-[400px] bg-white border border-slate-200 shadow-xl rounded-lg z-[100] p-4 max-h-[60vh] overflow-y-auto">
              <div @click="selectCategory(null)" class="mb-4 text-center py-2 bg-slate-100 hover:bg-slate-200 cursor-pointer rounded font-semibold text-slate-700 uppercase transition-colors">Tất cả danh mục</div>
              <div v-for="root in rootCategories" :key="root.id" class="mb-6">
                <div @click="selectCategory(root.id)" class="font-bold text-sky-700 text-sm mb-3 pb-1 border-b border-sky-100 uppercase cursor-pointer hover:text-sky-500 transition-colors">{{ root.name }}</div>
                <div class="grid grid-cols-4 gap-3">
                  <div v-for="child in getChildren(root.id)" :key="child.id" @click="selectCategory(child.id)" 
                       class="border border-slate-200 rounded-lg overflow-hidden bg-white cursor-pointer hover:border-sky-400 hover:shadow-md transition-all flex flex-col items-center p-2 group h-28">
                    <img v-if="child.image" :src="getImageUrl(child.image)" class="w-10 h-10 object-cover rounded mb-2 group-hover:scale-110 transition-transform duration-300 shrink-0" />
                    <div v-else class="w-10 h-10 bg-slate-100 rounded mb-2 flex items-center justify-center text-slate-400 font-bold text-xs group-hover:bg-slate-200 transition-colors shrink-0">{{ child.name.substring(0,2).toUpperCase() }}</div>
                    <span class="text-[11px] font-semibold text-slate-700 text-center leading-tight">{{ child.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="flex-1 overflow-auto p-2 bg-slate-50">
          <!-- Loading State -->
          <div v-if="isLoading" class="flex flex-col items-center justify-center pt-10 text-slate-400">
            <div class="loader mb-2">
              <div class="inner one"></div>
              <div class="inner two"></div>
              <div class="inner three"></div>
            </div>
            <span class="text-xs font-semibold uppercase tracking-wide mt-2">Đang tải...</span>
          </div>
          
          <!-- Products Grid (3-column) -->
          <div v-else-if="filteredProducts.length > 0" class="grid grid-cols-3 gap-2">
            <div v-for="prod in filteredProducts" :key="'prod-'+prod.id" @click="addToCart(prod)"
                 class="border border-slate-200 rounded-lg overflow-hidden bg-white cursor-pointer hover:border-emerald-400 hover:shadow-sm transition-all flex flex-col p-2 relative h-[140px]">
              <div v-if="isProductInCart(prod.id)" class="absolute top-1 right-1 w-5 h-5 bg-sky-500 rounded-full flex items-center justify-center text-white shadow-sm z-10">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <div class="w-full h-16 bg-slate-100 rounded mb-1.5 overflow-hidden flex items-center justify-center shrink-0">
                <img v-if="prod.image" :src="getImageUrl(prod.image)" class="w-full h-full object-cover" />
                <span v-else class="text-[10px] text-slate-400 font-bold px-1 text-center truncate">{{ prod.code || prod.product_code }}</span>
              </div>
              <span class="text-[10px] font-bold text-slate-800 line-clamp-2 leading-tight flex-1">[{{ prod.code || prod.product_code }}] {{ prod.name }}</span>
              <span class="text-[11px] text-emerald-600 font-bold mt-1 text-right whitespace-nowrap">{{ formatNum(prod.price) }} ₫</span>
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

      <!-- Right side: Cart -->
      <div class="flex-1 flex flex-col bg-white">
        <!-- Cart Table Header -->
        <div class="p-4 shrink-0 border-b border-slate-100">
          <table class="w-full text-xs">
            <thead>
              <tr class="text-slate-500 font-semibold border-b-2 border-slate-200">
                <th class="py-2 px-2 text-left w-6">#</th>
                <th class="py-2 px-2 text-left w-48">Tên món</th>
                <th class="py-2 px-2 text-center w-14">SL</th>
                <th class="py-2 px-2 text-center w-12">ĐVT</th>
                <th class="py-2 px-2 text-right w-24">Đơn giá</th>
                <th class="py-2 px-2 text-right w-20">Giảm</th>
                <th class="py-2 px-2 text-right w-24">Thành tiền</th>
                <th class="py-2 px-2 pl-4 text-left">Ghi chú</th>
                <th class="py-2 px-2 text-center w-8"></th>
              </tr>
            </thead>
          </table>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto px-4">
          <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
            <svg class="w-16 h-16 mb-4 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="text-sm font-semibold">Chưa có sản phẩm nào</span>
          </div>
          
          <table v-else class="w-full text-xs">
            <tbody>
              <tr v-for="(item, idx) in cart" :key="item.id" class="border-b border-slate-100 hover:bg-slate-50 group transition-colors">
                <!-- Index -->
                <td class="py-3 w-6 text-slate-400 font-semibold">{{ idx + 1 }}</td>
                <!-- Tên SP -->
                <td class="py-3 font-semibold text-slate-800 pr-2 w-32">
                  <div class="line-clamp-2 leading-tight text-xs">
                    <span v-if="item.code" class="text-slate-500 font-normal mr-1">[{{ item.code }}]</span>
                    {{ item.name }}
                  </div>
                </td>
                <!-- Số lượng -->
                <td class="py-3 text-center w-14 pr-2">
                  <div class="flex items-center justify-center border border-slate-200 rounded overflow-hidden w-full bg-white">
                    <input v-model.number="item.quantity" type="number" min="1" class="w-full text-center text-xs py-1 focus:outline-none"/>
                  </div>
                </td>
                <!-- Đơn vị -->
                <td class="py-3 text-center w-12">
                  <span class="bg-slate-100 text-slate-600 px-1 py-0.5 rounded text-[10px] font-bold">{{ item.unit }}</span>
                </td>
                <!-- Giá -->
                <td class="py-3 text-right font-semibold text-slate-700 w-24 whitespace-nowrap">
                  {{ formatNum(item.price) }} ₫
                </td>
                <!-- Giảm giá -->
                <td class="py-3 px-1 w-20">
                  <input v-model.number="item.discount" type="number" min="0" placeholder="0" class="w-full px-1 py-1 text-xs border border-slate-200 rounded focus:border-sky-500 focus:outline-none text-right text-rose-500 font-semibold bg-white"/>
                </td>
                <!-- Số tiền -->
                <td class="py-3 text-right font-extrabold text-emerald-700 w-24 pr-2 whitespace-nowrap">
                  <div class="flex items-center justify-end gap-0.5">
                    <span>{{ formatNum(Number(item.price) * Number(item.quantity) - Number(item.discount || 0)) }}</span>
                    <span class="text-[10px]">₫</span>
                  </div>
                </td>
                <!-- Ghi chú -->
                <td class="py-3 pr-2">
                  <input v-model="item.note" placeholder="Thêm..." class="w-full px-2 py-1 text-[11px] border border-slate-200 rounded focus:border-sky-500 focus:outline-none bg-white"/>
                </td>
                <!-- Delete -->
                <td class="py-3 text-center w-8">
                  <button @click="removeFromCart(idx)" class="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-all cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Cart Footer -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 shrink-0 flex items-center justify-between">
          <div class="flex items-center gap-6">
            <div>
              <span class="text-xs text-slate-500 font-semibold mr-2">Tổng đơn:</span>
              <span class="text-sm font-extrabold text-slate-800">{{ cartTotalItems }}</span>
            </div>
            <div>
              <span class="text-xs text-slate-500 font-semibold mr-2">Thành tiền:</span>
              <span class="text-lg font-black text-emerald-600">{{ formatNum(cartTotalAmount) }} ₫</span>
            </div>
          </div>
          <button 
            @click="handleSave"
            class="px-8 py-2.5 bg-sky-500 hover:bg-sky-600 text-white text-sm font-extrabold rounded-xl shadow-md shadow-sky-200 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cart.length === 0"
          >
            Xong
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
