<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import http from '@/services/http'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'select'])

const productsList = ref([])
const categoriesList = ref([])

const selectedCategoryId = ref('all')
const selectedProducts = ref([])
const collapsedCategories = ref({})
const searchName = ref('')
const searchCode = ref('')

onMounted(async () => {
    try {
        const [prodRes, catRes] = await Promise.all([
            http.get('/fb-products', { params: { per_page: 1000 } }),
            http.get('/fb-product-categories', { params: { per_page: 1000 } })
        ]);
        productsList.value = prodRes.data?.data || prodRes.data || [];
        categoriesList.value = catRes.data?.data || catRes.data || [];
    } catch (e) {
        console.error('Lỗi tải danh sách sản phẩm:', e);
    }
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    selectedCategoryId.value = 'all'
    selectedProducts.value = []
  }
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
  checkVisible(categoriesList.value, null, 0)
  return result
})

const filteredProducts = computed(() => {
  if (!selectedCategoryId.value) return []
  let list = productsList.value
  if (selectedCategoryId.value !== 'all') {
    list = list.filter(p => p.fb_product_category_id === selectedCategoryId.value)
  }
  if (searchName.value.trim()) {
    const s = searchName.value.toLowerCase().trim()
    list = list.filter(p => (p.name || '').toLowerCase().includes(s))
  }
  if (searchCode.value.trim()) {
    const s = searchCode.value.toLowerCase().trim()
    list = list.filter(p => (p.code || '').toLowerCase().includes(s))
  }
  return list
})

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

const toggleCategoryCollapse = (catId) => {
  collapsedCategories.value[catId] = !collapsedCategories.value[catId]
}

const selectCategory = (catId) => {
  selectedCategoryId.value = catId
}

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    const newSelected = new Set(selectedProducts.value.map(p => p.id))
    filteredProducts.value.forEach(p => newSelected.add(p.id))
    selectedProducts.value = Array.from(newSelected).map(id => productsList.value.find(p => p.id === id))
  } else {
    const currentIds = new Set(filteredProducts.value.map(p => p.id))
    selectedProducts.value = selectedProducts.value.filter(p => !currentIds.has(p.id))
  }
}

const isProductSelected = (productId) => {
  return selectedProducts.value.some(p => p.id === productId)
}

const isAllSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false
  return filteredProducts.value.every(p => isProductSelected(p.id))
})

const handleSave = () => {
  emit('select', selectedProducts.value)
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="$emit('close')"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-[1200px] max-w-[95vw] flex flex-col h-[600px] max-h-[90vh]">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
        <h3 class="text-lg font-semibold text-slate-800">Thêm sản phẩm khuyến mãi</h3>
        <button @click="$emit('close')" class="p-1 hover:bg-slate-100 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-hidden flex">
        <!-- Sidebar: Categories -->
        <div class="w-64 border-r border-slate-100 bg-slate-50/50 flex flex-col shrink-0">
          <div class="px-4 py-3 border-b border-slate-100 font-medium text-sm text-slate-700 bg-slate-50">
            Nhóm thực đơn
          </div>
          <div class="flex-1 overflow-auto p-2">
            <div 
              @click="selectCategory('all')"
              class="flex items-center px-3 py-2 rounded-md text-sm cursor-pointer mb-1 transition-colors"
              :class="selectedCategoryId === 'all' ? 'bg-sky-100 text-sky-700 font-medium' : 'text-slate-600 hover:bg-slate-100'"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
              Tất cả
            </div>

            <div v-for="cat in visibleCategories" :key="cat.id" 
                 class="flex flex-col mb-0.5">
              <div 
                class="flex items-center rounded-md cursor-pointer transition-colors group"
                :class="selectedCategoryId === cat.id ? 'bg-sky-100 text-sky-700' : 'hover:bg-slate-100 text-slate-600'"
              >
                <!-- Indentation spacer -->
                <div :style="{ width: `${cat.depth * 16}px` }" class="shrink-0"></div>
                
                <!-- Collapse/Expand Icon -->
                <div class="w-6 h-6 flex items-center justify-center shrink-0" @click.stop="toggleCategoryCollapse(cat.id)">
                  <svg v-if="cat.hasChildren" class="w-3.5 h-3.5 transition-transform" :class="{'rotate-90': !cat.isCollapsed}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>

                <!-- Category Name -->
                <div class="flex-1 py-1.5 px-1 text-sm truncate" @click="selectCategory(cat.id)" :class="selectedCategoryId === cat.id ? 'font-medium' : ''">
                  {{ cat.name }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main content: Products list -->
        <div class="flex-1 flex flex-col p-4 bg-white min-w-0">
          <div class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px] mb-2">Thực đơn</div>
          <div class="border border-slate-200 rounded-lg overflow-hidden flex-1 flex flex-col">
            <div class="overflow-y-auto flex-1">
              <table class="w-full border-collapse">
                <thead class="sticky top-0 bg-slate-50 z-10 shadow-sm">
                  <tr class="text-slate-500 font-bold border-b border-slate-200 text-left text-xs">
                    <th class="p-3 w-10 text-center">
                      <input 
                        type="checkbox" 
                        :checked="isAllSelected"
                        @change="toggleSelectAll"
                        class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" 
                      />
                    </th>
                    <th class="p-3 w-12">Hình ảnh</th>
                    <th class="p-3">
                      <div>Tên</div>
                      <input type="text" v-model="searchName" placeholder="Lọc theo tên..." class="mt-1 w-full border border-slate-200 rounded px-1.5 py-0.5 text-[10px] font-normal" />
                    </th>
                    <th class="p-3">
                      <div>Mã</div>
                      <input type="text" v-model="searchCode" placeholder="Lọc theo mã..." class="mt-1 w-full border border-slate-200 rounded px-1.5 py-0.5 text-[10px] font-normal" />
                    </th>
                    <th class="p-3 text-right">Giá gốc</th>
                    <th class="p-3 text-right">Đơn giá</th>
                    <th class="p-3 text-right">Phí phục vụ</th>
                    <th class="p-3 text-right">Thuế đặc biệt</th>
                    <th class="p-3 text-right">VAT</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                  <tr v-if="!selectedCategoryId" class="hover:bg-slate-50">
                    <td colspan="9" class="p-8 text-center text-slate-400 font-semibold italic">
                      Vui lòng chọn loại thực đơn bên trái để hiển thị món ăn.
                    </td>
                  </tr>
                  <tr v-else-if="filteredProducts.length === 0" class="hover:bg-slate-50">
                    <td colspan="9" class="p-8 text-center text-slate-400 font-semibold italic">
                      Không có món ăn nào trong loại thực đơn này.
                    </td>
                  </tr>
                  <tr v-for="product in filteredProducts" :key="product.id" class="hover:bg-slate-50">
                    <td class="p-3 text-center">
                      <input 
                        type="checkbox" 
                        :value="product"
                        v-model="selectedProducts"
                        class="w-3.5 h-3.5 rounded border-slate-300 accent-sky-500 cursor-pointer" 
                      />
                    </td>
                    <td class="p-3">
                      <div class="w-8 h-8 rounded border border-slate-200 overflow-hidden bg-slate-50 flex items-center justify-center shrink-0">
                        <img v-if="product.image_path || product.image" :src="getImageUrl(product.image_path || product.image)" class="w-full h-full object-cover" />
                        <svg v-else class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                      </div>
                    </td>
                    <td class="p-3 font-semibold text-slate-700">{{ product.name }}</td>
                    <td class="p-3 text-slate-600">{{ product.code || product.product_code || '-' }}</td>
                    <td class="p-3 text-slate-500 text-right">{{ Number(product.original_amount || 0).toLocaleString() }}</td>
                    <td class="p-3 font-bold text-slate-700 text-right">{{ Number(product.price || 0).toLocaleString() }}</td>
                    <td class="p-3 text-slate-600 text-right">{{ product.service_charge_percent || 0 }}%</td>
                    <td class="p-3 text-slate-600 text-right">{{ product.special_tax_percent || 0 }}%</td>
                    <td class="p-3 text-slate-600 text-right">{{ product.tax_percent || 0 }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center rounded-b-xl shrink-0">
        <div class="text-sm font-medium text-slate-600">
          Đã chọn <span class="text-sky-600 px-1">{{ selectedProducts.length }}</span> sản phẩm
        </div>
        <div class="flex gap-3">
          <button @click="$emit('close')" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 transition-colors">
            Hủy
          </button>
          <button @click="handleSave" :disabled="selectedProducts.length === 0" class="px-4 py-2 text-sm font-medium text-white bg-[#78C5E7] border border-transparent rounded-lg hover:bg-[#60b3d6] disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-[#78C5E7] focus:ring-offset-2 transition-colors">
            Đồng ý
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
