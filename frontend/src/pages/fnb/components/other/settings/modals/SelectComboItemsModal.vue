<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  initialSelected: {
    type: Array,
    default: () => []
  },
  categoriesList: {
    type: Array,
    default: () => []
  },
  productsList: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'save'])

const selectedCategoryId = ref(null)
const selectedProducts = ref([])
const collapsedCategories = ref({})
const searchName = ref('')
const searchCode = ref('')

watch(() => props.show, (newVal) => {
  if (newVal) {
    selectedCategoryId.value = 'all'
    selectedProducts.value = [...props.initialSelected]
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
  checkVisible(props.categoriesList, null, 0)
  return result
})

const filteredProducts = computed(() => {
  if (!selectedCategoryId.value) return []
  let list = props.productsList
  if (selectedCategoryId.value !== 'all') {
    list = list.filter(p => p.fb_product_category_id === selectedCategoryId.value)
  }
  if (searchName.value.trim()) {
    const s = searchName.value.toLowerCase().trim()
    list = list.filter(p => (p.name || '').toLowerCase().includes(s))
  }
  if (searchCode.value.trim()) {
    const s = searchCode.value.toLowerCase().trim()
    list = list.filter(p => (p.product_code || '').toLowerCase().includes(s))
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
    const newProducts = filteredProducts.value.filter(p => !selectedProducts.value.includes(p))
    selectedProducts.value.push(...newProducts)
  } else {
    selectedProducts.value = selectedProducts.value.filter(p => !filteredProducts.value.includes(p))
  }
}

const isAllSelected = computed(() => {
  if (filteredProducts.value.length === 0) return false
  return filteredProducts.value.every(p => selectedProducts.value.includes(p))
})

const handleSave = () => {
  emit('save', selectedProducts.value)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-[90vw] max-w-none overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      
      <!-- Modal Header -->
      <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">Thêm sản phẩm cho combo</h3>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="flex h-[60vh]">
        
        <!-- Left Panel: Categories -->
        <div class="w-1/3 border-r border-slate-100 overflow-y-auto bg-slate-50/30 p-2">
          <div class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px] mb-2 px-2">Loại thực đơn</div>
          
          <div 
            class="flex items-center rounded-md hover:bg-slate-100 transition cursor-pointer text-xs h-[30px] pr-2 select-none group px-2 mb-1"
            :class="selectedCategoryId === 'all' ? 'bg-sky-50 text-sky-600 font-bold' : 'text-slate-700'"
            @click="selectCategory('all')"
          >
            <span class="w-5 shrink-0 mr-1"></span>
            <span class="flex-1 truncate">Tất cả các nhóm món</span>
          </div>

          <div 
            v-if="visibleCategories.length === 0" 
            class="text-slate-400 text-center py-2 font-semibold text-xs"
          >
            Không có loại thực đơn nào
          </div>
          <div 
            v-for="cat in visibleCategories" 
            :key="cat.id"
            class="flex items-center rounded-md hover:bg-slate-100 transition cursor-pointer text-xs h-[30px] pr-2 select-none group"
            :style="{ paddingLeft: (cat.depth * 14) + 'px' }"
            :class="selectedCategoryId === cat.id ? 'bg-sky-50 text-sky-600 font-bold' : 'text-slate-700'"
          >
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

            <span 
              @click="selectCategory(cat.id)"
              class="flex-1 truncate"
            >
              {{ cat.name }}
            </span>
          </div>
        </div>

        <!-- Right Panel: Products -->
        <div class="w-2/3 flex flex-col p-4 bg-white">
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
                        <img v-if="product.image" :src="getImageUrl(product.image)" class="w-full h-full object-cover" />
                        <svg v-else class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                      </div>
                    </td>
                    <td class="p-3 font-semibold text-slate-700">{{ product.name }}</td>
                    <td class="p-3 text-slate-600">{{ product.product_code || '-' }}</td>
                    <td class="p-3 font-medium text-slate-600 text-right">{{ Number(product.original_amount || 0).toLocaleString() }}</td>
                    <td class="p-3 font-bold text-slate-700 text-right">{{ Number(product.price || 0).toLocaleString() }}</td>
                    <td class="p-3 text-right text-slate-600">{{ product.service_charge_percent || 0 }}%</td>
                    <td class="p-3 text-right text-slate-600">{{ product.special_tax_percent || 0 }}%</td>
                    <td class="p-3 text-right text-slate-600">{{ product.tax_percent || 0 }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 bg-slate-50/50">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 bg-[#e2f3fc] hover:bg-[#d0ebfa] text-sky-700 px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          Lưu
        </button>
      </div>

    </div>
  </div>
</template>
