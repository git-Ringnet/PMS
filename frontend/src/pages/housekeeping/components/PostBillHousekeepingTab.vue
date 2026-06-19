<template>
  <div class="flex flex-col h-full bg-slate-50/50 p-4 font-sans text-sm">
    <!-- Top Controls -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-4 bg-white p-4 rounded-lg shadow-sm border border-slate-200">
      <div class="col-span-1 md:col-span-2 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Chọn phòng</label>
        <select v-model="form.roomId" class="bg-amber-50/60 border border-amber-200 text-slate-700 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all">
          <option value="">Chọn phòng</option>
          <option v-for="room in mockRooms" :key="room.id" :value="room.id">{{ room.name }}</option>
        </select>
      </div>
      
      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Ngày</label>
        <input type="date" v-model="form.date" class="border border-slate-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Tăng Giá</label>
        <div class="relative">
          <input type="number" v-model="form.surcharge" class="w-full border border-slate-300 rounded p-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-slate-100/50 transition-all" />
          <span class="absolute right-2 top-1.5 text-slate-400 font-semibold">%</span>
        </div>
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Giảm giá</label>
        <div class="relative">
          <input type="number" v-model="form.discount" class="w-full border border-slate-300 rounded p-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-slate-100/50 transition-all" />
          <span class="absolute right-2 top-1.5 text-slate-400 font-semibold">%</span>
        </div>
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Mã hóa đơn</label>
        <input type="text" v-model="form.invoiceCode" class="border border-slate-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col items-center justify-center">
        <label class="text-xs font-semibold text-slate-500 mb-1">Miễn phí</label>
        <input type="checkbox" v-model="form.isFree" class="w-4 h-4 text-sky-500 border-slate-300 rounded focus:ring-sky-500 cursor-pointer" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-slate-500 mb-1">Ghi chú</label>
        <input type="text" v-model="form.note" placeholder="Ghi chú" class="border border-slate-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all" />
      </div>
    </div>

    <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-4">
      <!-- Left Panel: Products -->
      <div class="w-full lg:w-[42%] flex flex-col bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <!-- Tabs & Search -->
        <div class="flex items-center border-b border-slate-250 p-2 gap-4">
          <div class="flex space-x-4 px-2">
            <button 
              v-for="tab in tabs" 
              :key="tab"
              @click="activeTab = tab"
              class="pb-1 font-semibold transition-all border-b-2 cursor-pointer text-xs uppercase tracking-wider"
              :class="[
                activeTab === tab ? 'text-[var(--hk-primary-dark)] border-[var(--hk-primary-dark)] font-bold' : 'text-slate-500 border-transparent hover:text-slate-700'
              ]"
            >
              {{ tab }}
            </button>
          </div>
          <div class="flex-1 relative flex items-center search-container">
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Tìm sản phẩm..."
              @focus="showSuggestions = true"
              data-hk-search
              class="w-full border border-slate-300 rounded-l p-1.5 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary-dark)] text-xs bg-white" 
            />
            <button class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-white px-3 py-1.5 rounded-r transition-all cursor-pointer">
              <Search class="w-4 h-4" />
            </button>
            
            <!-- Autocomplete suggestions dropdown -->
            <Transition name="hk-dropdown">
              <div v-if="showSuggestions && searchSuggestions.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 shadow-xl rounded-md z-40 max-h-60 overflow-y-auto">
                <div 
                  v-for="p in searchSuggestions" 
                  :key="'suggest-'+p.id" 
                  @click="selectSuggestion(p)"
                  class="p-2.5 hover:bg-slate-50 cursor-pointer flex items-center justify-between border-b border-slate-100 last:border-0"
                >
                  <span class="font-medium text-slate-700 text-xs" v-html="highlightKeyword(p.name, searchQuery)"></span>
                  <span class="text-[var(--hk-primary-dark)] font-bold text-xs">{{ formatCurrency(p.price) }}</span>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Filters Sub-row -->
        <div class="flex flex-wrap items-center gap-3 p-2.5 bg-slate-50 border-b border-slate-200 text-xs text-slate-650 shrink-0">
          <!-- Multi-select Group Filter -->
          <div class="relative group-filter-wrapper">
            <button @click="showGroupDropdown = !showGroupDropdown" class="bg-white border border-slate-300 rounded px-2.5 py-1 flex items-center gap-1 hover:bg-slate-50 transition-colors cursor-pointer font-medium text-slate-600">
              <span>Nhóm: {{ selectedGroupLabel }}</span>
              <ChevronDown class="w-3.5 h-3.5 text-slate-400" />
            </button>
            <Transition name="hk-dropdown">
              <div v-if="showGroupDropdown" class="absolute top-full left-0 mt-1 bg-white border border-slate-200 shadow-lg rounded-md p-2 z-30 min-w-[170px] flex flex-col gap-1">
                <label v-for="gName in availableGroups" :key="gName" class="flex items-center gap-2 hover:bg-slate-50 p-1.5 rounded cursor-pointer font-medium text-slate-700">
                  <input type="checkbox" :value="gName" v-model="filterGroups" class="rounded text-[var(--hk-primary-dark)] focus:ring-[var(--hk-primary)] w-3.5 h-3.5 cursor-pointer" />
                  <span>{{ gName }}</span>
                </label>
                <div class="border-t border-slate-100 mt-1 pt-1.5 flex justify-between text-[10px]">
                  <button @click="filterGroups = []" class="text-slate-500 hover:text-slate-700 font-semibold px-1 py-0.5 cursor-pointer">Reset</button>
                  <button @click="showGroupDropdown = false" class="bg-[var(--hk-primary-dark)] text-white font-semibold px-2 py-0.5 rounded shadow-sm hover:brightness-95 cursor-pointer">OK</button>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Price range -->
          <div class="flex items-center gap-1">
            <span>Giá từ:</span>
            <input type="number" placeholder="Min" v-model.number="priceRange.min" class="w-16 border border-slate-300 rounded p-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white placeholder-slate-400 text-slate-750" />
            <span>đến</span>
            <input type="number" placeholder="Max" v-model.number="priceRange.max" class="w-16 border border-slate-300 rounded p-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white placeholder-slate-400 text-slate-750" />
          </div>

          <!-- Sort dropdown -->
          <div class="flex items-center gap-1 ml-auto">
            <span>Sắp xếp:</span>
            <select v-model="sortOrder" class="border border-slate-300 rounded p-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white cursor-pointer text-slate-700 font-medium">
              <option value="name_asc">Tên A-Z</option>
              <option value="name_desc">Tên Z-A</option>
              <option value="price_asc">Giá tăng dần</option>
              <option value="price_desc">Giá giảm dần</option>
              <option value="id_desc">Mới nhất</option>
            </select>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 bg-slate-50/20 hk-scroll">
          <template v-if="isLoading">
            <div class="mb-4 animate-pulse">
              <div class="h-4 bg-slate-200 rounded w-28 mb-3"></div>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4">
                <div v-for="i in 3" :key="'skeleton-'+i" class="flex flex-col items-center p-2.5 bg-slate-200/40 rounded">
                  <div class="w-24 h-24 bg-slate-200 rounded flex items-center justify-center mb-2"></div>
                  <div class="h-3 bg-slate-200 rounded w-16 mx-auto"></div>
                </div>
              </div>
            </div>
          </template>

          <template v-else>
            <div v-if="filteredGroups.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
              <Inbox class="w-12 h-12 mb-2 opacity-50 text-slate-350" />
              <p class="font-medium text-slate-400 text-[13px]">Không có sản phẩm phù hợp</p>
            </div>

            <div v-for="group in filteredGroups" :key="group.name" class="mb-4">
              <div class="flex items-center mb-2 w-full text-left">
                <input type="checkbox" v-model="groupChecked[group.name]" @change="handleGroupCheck(group, $event.target.checked)" class="mr-2 rounded text-[var(--hk-primary-dark)] focus:ring-[var(--hk-primary)] w-4 h-4 cursor-pointer" />
                <button 
                  @click="toggleGroup(group.name)"
                  class="flex flex-1 items-center text-slate-750 font-bold text-xs hover:text-[var(--hk-primary-dark)] transition-colors focus:outline-none cursor-pointer uppercase tracking-wider"
                >
                  {{ group.name }}
                  <component :is="expandedGroups[group.name] ? ChevronUp : ChevronDown" class="w-4 h-4 ml-1 text-slate-400" />
                </button>
              </div>
              
              <Transition name="hk-expand">
                <div v-show="expandedGroups[group.name]" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4 py-2 border-t border-slate-100">
                  <div 
                    v-for="product in group.items" 
                    :key="product.id"
                    @click="addProduct(product)"
                    class="flex flex-col items-center p-2.5 hover:bg-white rounded transition-all prod-card relative cursor-pointer"
                  >
                    <div class="w-24 h-24 bg-slate-50 rounded flex items-center justify-center mb-2 relative overflow-hidden border border-slate-200">
                      <Image class="w-9 h-9 text-slate-300" />
                      <div class="absolute bottom-0 left-0 right-0 text-slate-800 text-[11px] text-center py-1 font-bold" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
                        {{ formatCurrency(product.price) }}
                      </div>
                      <!-- Dấu tick nếu đã chọn -->
                      <button 
                        v-if="isProductSelected(product.id)"
                        @click.stop="removeProductById(product.id)"
                        class="absolute top-1 right-1 bg-emerald-500 text-white rounded-full p-1 z-15 hover:bg-rose-500 transition-colors shadow-md border border-white animate-bounce-in"
                        title="Bỏ chọn"
                      >
                        <Check class="w-3.5 h-3.5" stroke-width="3" />
                      </button>
                    </div>
                    <span class="text-xs text-center font-bold text-slate-750 line-clamp-2" :title="product.name">
                      {{ product.name }}
                    </span>
                  </div>
                </div>
              </Transition>
            </div>
          </template>
        </div>
      </div>

      <!-- Right Panel: Invoice/Table -->
      <div class="w-full lg:w-[58%] flex flex-col bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-2.5 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
          <button 
            @click="sendToRoom"
            :disabled="isSending"
            class="btn-primary px-4 py-2 rounded-lg flex items-center text-xs font-bold shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <span v-if="isSending" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
            <Send v-else class="w-4 h-4 mr-2" />
            {{ isSending ? 'Đang gửi...' : 'Gửi về phòng' }}
          </button>
        </div>
        
        <div class="flex-1 overflow-auto hk-scroll">
          <table class="w-full text-left border-collapse whitespace-nowrap min-w-max text-xs">
            <thead class="bg-slate-100/90 sticky top-0 text-slate-600 border-b border-slate-200 z-10 font-bold uppercase tracking-wider text-[11px]">
              <tr>
                <th class="p-2.5 w-10 border-r border-slate-200"></th>
                <th class="p-2.5 w-14 text-center">STT</th>
                <th class="p-2.5">Sản phẩm</th>
                <th class="p-2.5 w-32">Ghi chú</th>
                <th class="p-2.5 text-right w-24">Giá</th>
                <th class="p-2.5 text-center w-20">Số lượng</th>
                <th class="p-2.5 text-center w-28 cursor-pointer hover:bg-slate-200 select-none transition-colors" @click="toggleSurchargeMode">
                  {{ isSurchargeMode ? 'Phần trăm phụ thu' : 'Phần trăm giảm giá' }}
                </th>
                <th class="p-2.5 text-right w-28">
                  {{ isSurchargeMode ? 'Tiền phụ thu' : 'Tiền giảm giá' }}
                </th>
                <th class="p-2.5 text-right w-24 text-[var(--hk-primary-dark)] font-black">Tổng cộng</th>
                <th class="p-2.5 w-8 border-l border-slate-200"></th>
              </tr>
            </thead>
            <tbody v-if="selectedItems.length > 0">
              <template v-for="(items, tabName) in groupedSelectedItems" :key="tabName">
                <!-- Group Header -->
                <tr class="bg-slate-50 border-b border-slate-100 font-bold">
                  <td class="p-2 text-center border-r border-slate-200 w-10">
                    <button @click="toggleTableGroup(tabName)" class="bg-[var(--hk-primary-light)] hover:bg-[var(--hk-primary)] text-slate-800 rounded p-1 transition-colors w-6 h-6 flex items-center justify-center cursor-pointer">
                      <div v-if="tableExpandedGroups[tabName] !== false" class="w-3 h-0.5 bg-sky-700 rounded-sm"></div>
                      <div v-else class="relative w-3 h-3 flex items-center justify-center">
                        <div class="absolute w-3 h-0.5 bg-sky-700 rounded-sm"></div>
                        <div class="absolute h-3 w-0.5 bg-sky-700 rounded-sm"></div>
                      </div>
                    </button>
                  </td>
                  <td colspan="9" class="p-2.5 text-slate-700 text-xs font-black uppercase">{{ getTabCode(tabName) }}</td>
                </tr>
                <!-- Items -->
                <tr v-for="item in items" :key="item.uuid" v-show="tableExpandedGroups[tabName] !== false" class="border-b border-slate-100 hover:bg-slate-50/50">
                  <td class="p-2 border-r border-slate-200 w-10"></td>
                  <td class="p-2 text-center text-slate-500">{{ item.id }}</td>
                  <td class="p-2 text-slate-700 font-semibold">{{ item.name }}</td>
                  <td class="p-2">
                    <div v-if="item.editingNote">
                      <textarea 
                        v-model="item.note" 
                        @keydown.enter.prevent="item.editingNote = false"
                        @blur="item.editingNote = false"
                        class="w-full border-b border-slate-350 bg-transparent focus:outline-none focus:border-[var(--hk-primary)] text-[11px] resize-none"
                        rows="2"
                        autofocus
                      ></textarea>
                    </div>
                    <div v-else @click="item.editingNote = true" class="text-[11px] text-slate-500 cursor-pointer min-h-[20px] whitespace-pre-wrap break-words border-b border-dashed border-slate-200 hover:border-[var(--hk-primary)] pb-0.5">
                      {{ item.note || 'Thêm ghi chú...' }}
                    </div>
                  </td>
                  <td class="p-2 text-right font-medium text-slate-700">{{ formatCurrency(item.price) }}</td>
                  <td class="p-2 text-center">
                    <input type="number" min="1" v-model="item.quantity" class="w-12 border border-slate-300 rounded text-center focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] p-0.5" />
                  </td>
                  <td class="p-2 text-center">
                    <input type="number" min="0" max="100" v-model="item.percent" class="w-16 border border-slate-300 rounded text-center focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] p-0.5" />
                  </td>
                  <td class="p-2 text-right bg-slate-50/50 text-slate-500">
                    <span v-if="getLineModifier(item) > 0 && !isSurchargeMode">-</span>{{ formatCurrency(getLineModifier(item)) }}
                  </td>
                  <td class="p-2 text-right font-bold text-[var(--hk-primary-dark)]">
                    {{ formatCurrency(getLineTotal(item)) }}
                  </td>
                  <td class="p-2 text-center border-l border-slate-100">
                    <button @click="removeProductById(item.id)" class="text-rose-400 hover:text-rose-600 hover:bg-rose-50 p-1 rounded transition-colors cursor-pointer">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
            <tbody v-else>
              <tr>
                <td colspan="10" class="p-16 text-center text-slate-400">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <Inbox class="w-12 h-12 opacity-40 text-slate-300 animate-bounce" />
                    <p class="font-medium text-slate-400">Chưa chọn sản phẩm/dịch vụ nào</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer Total -->
        <div class="bg-slate-100 p-3.5 flex justify-between font-bold text-slate-750 border-t border-slate-200">
          <span>Tổng cộng</span>
          <div class="flex gap-8">
            <span>Số lượng: <span class="text-[var(--hk-primary-dark)]">{{ totalQuantity }}</span></span>
            <span>Tổng tiền: <span class="text-[var(--hk-primary-dark)]">{{ formatCurrency(animatedTotalAmount) }} VNĐ</span></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Search, ChevronDown, ChevronUp, Image, Trash2, Send, Inbox, Check } from '@lucide/vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// Mock Data
const mockRooms = [
  { id: 1, name: '902 - Ms. VINOKUROVA MARINA' },
  { id: 2, name: '902 - Ms. SAKHAROVA ANNA' },
  { id: 3, name: '902 - Ms. SALYKINA IULLA' },
  { id: 4, name: '607 - Mr. Guest 1' }
]

const tabs = ['Minibar', 'Giặt ủi', 'Hàng đền bù']

const mockCatalog = [
  { id: 1, name: '1. Nước suối', price: 15000, tab: 'Minibar', group: 'Nhóm sản phẩm Minibar' },
  { id: 2, name: '2. Nước ngọt', price: 25000, tab: 'Minibar', group: 'Nhóm sản phẩm Minibar' },
  { id: 3, name: 'Bia Heineken', price: 35000, tab: 'Minibar', group: 'Nhóm sản phẩm Minibar' },
  { id: 4, name: 'Snack khoai tây', price: 20000, tab: 'Minibar', group: 'Nhóm sản phẩm ăn vặt' },
  { id: 5, name: 'Mì ly', price: 15000, tab: 'Minibar', group: 'Nhóm sản phẩm ăn vặt' },
  { id: 6, name: 'Áo sơ mi', price: 50000, tab: 'Giặt ủi', group: 'Giặt thường' },
  { id: 7, name: 'Quần tây', price: 60000, tab: 'Giặt ủi', group: 'Giặt thường' },
  { id: 8, name: 'Áo vest', price: 120000, tab: 'Giặt ủi', group: 'Giặt hấp' },
  { id: 9, name: 'Cốc thủy tinh', price: 50000, tab: 'Hàng đền bù', group: 'Vật dụng phòng' },
  { id: 10, name: 'Khăn tắm', price: 150000, tab: 'Hàng đền bù', group: 'Vật dụng phòng' }
]

// State
const form = ref({
  roomId: '',
  date: new Date().toISOString().split('T')[0],
  surcharge: 0,
  discount: 0,
  invoiceCode: '',
  isFree: false,
  note: ''
})

const activeTab = ref('Minibar')
const searchQuery = ref('')
const debouncedQuery = ref('')
const expandedGroups = ref({})
const tableExpandedGroups = ref({})
const groupChecked = ref({})
const selectedItems = ref([])
const isSurchargeMode = ref(false)
const isSending = ref(false)

// Suggestions state
const showSuggestions = ref(false)

// Group & Price & Sort filter states
const showGroupDropdown = ref(false)
const filterGroups = ref([])
const priceRange = ref({ min: null, max: null })
const sortOrder = ref('name_asc')

const isLoading = ref(false)
const triggerSearchLoading = () => {
  isLoading.value = true
  setTimeout(() => {
    isLoading.value = false
  }, 400)
}

watch([searchQuery, activeTab, () => priceRange.value.min, () => priceRange.value.max, sortOrder, filterGroups], () => {
  triggerSearchLoading()
}, { deep: true })

// Debounce search query
let debounceTimeout = null
watch(searchQuery, (newVal) => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    debouncedQuery.value = newVal
  }, 200)
})

// Suggestions computation
const searchSuggestions = computed(() => {
  if (!searchQuery.value) return []
  const q = searchQuery.value.toLowerCase()
  return mockCatalog.filter(p => p.tab === activeTab.value && p.name.toLowerCase().includes(q)).slice(0, 5)
})

const selectSuggestion = (product) => {
  addProduct(product)
  searchQuery.value = ''
  showSuggestions.value = false
}

const highlightKeyword = (text, keyword) => {
  if (!keyword) return text
  const escKeyword = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const regex = new RegExp(`(${escKeyword})`, 'gi')
  return text.replace(regex, '<mark class="bg-amber-100 text-amber-950 font-semibold px-0.5 rounded">$1</mark>')
}

const availableGroups = computed(() => {
  const groups = new Set()
  mockCatalog.forEach(p => {
    if (p.tab === activeTab.value) {
      groups.add(p.group)
    }
  })
  return Array.from(groups)
})

const selectedGroupLabel = computed(() => {
  if (filterGroups.value.length === 0) return 'Tất cả'
  if (filterGroups.value.length === 1) return filterGroups.value[0]
  return `${filterGroups.value.length} nhóm`
})

// Reset group selections when changing tab
watch(activeTab, () => {
  filterGroups.value = []
})

// Computed
const filteredGroups = computed(() => {
  const groupsMap = {}
  
  // Apply filtering
  let filteredCatalog = mockCatalog.filter(product => {
    // Tab filter
    if (product.tab !== activeTab.value) return false
    // Search query filter (debounced)
    if (debouncedQuery.value && !product.name.toLowerCase().includes(debouncedQuery.value.toLowerCase())) return false
    // Group filter
    if (filterGroups.value.length > 0 && !filterGroups.value.includes(product.group)) return false
    // Price range filter
    if (priceRange.value.min !== null && priceRange.value.min !== '' && product.price < priceRange.value.min) return false
    if (priceRange.value.max !== null && priceRange.value.max !== '' && product.price > priceRange.value.max) return false
    return true
  })

  // Apply sorting
  filteredCatalog.sort((a, b) => {
    if (sortOrder.value === 'name_asc') {
      return a.name.localeCompare(b.name)
    }
    if (sortOrder.value === 'name_desc') {
      return b.name.localeCompare(a.name)
    }
    if (sortOrder.value === 'price_asc') {
      return a.price - b.price
    }
    if (sortOrder.value === 'price_desc') {
      return b.price - a.price
    }
    if (sortOrder.value === 'id_desc') {
      return b.id - a.id
    }
    return 0
  })

  filteredCatalog.forEach(product => {
    if (!groupsMap[product.group]) {
      groupsMap[product.group] = []
      if (expandedGroups.value[product.group] === undefined) {
        expandedGroups.value[product.group] = true
      }
    }
    groupsMap[product.group].push(product)
  })

  return Object.keys(groupsMap).map(groupName => ({
    name: groupName,
    items: groupsMap[groupName]
  }))
})

// Group selected items by tab
const groupedSelectedItems = computed(() => {
  const groupsMap = {}
  selectedItems.value.forEach(item => {
    if (!groupsMap[item.tab]) {
      groupsMap[item.tab] = []
    }
    groupsMap[item.tab].push(item)
  })
  return groupsMap
})

const getLineModifier = (item) => {
  return (item.price * item.quantity * item.percent) / 100
}

const getLineTotal = (item) => {
  const base = item.price * item.quantity
  const modifier = getLineModifier(item)
  return isSurchargeMode.value ? base + modifier : base - modifier
}

const totalQuantity = computed(() => {
  return selectedItems.value.reduce((sum, item) => sum + Number(item.quantity), 0)
})

const totalAmount = computed(() => {
  return selectedItems.value.reduce((sum, item) => sum + getLineTotal(item), 0)
})

// Smooth Count-Up Animation for total amount
const animatedTotalAmount = ref(0)
watch(totalAmount, (newVal) => {
  const start = animatedTotalAmount.value
  const end = newVal
  const duration = 250 // ms
  const startTime = performance.now()

  const step = (now) => {
    const elapsed = now - startTime
    const progress = Math.min(elapsed / duration, 1)
    animatedTotalAmount.value = Math.round(start + (end - start) * progress)
    if (progress < 1) {
      requestAnimationFrame(step)
    }
  }
  requestAnimationFrame(step)
}, { immediate: true })

// Watchers for global Surcharge / Discount
watch(() => form.value.surcharge, (newVal) => {
  if (newVal > 0) {
    form.value.discount = 0
    isSurchargeMode.value = true
    selectedItems.value.forEach(item => item.percent = newVal)
  } else if (newVal === 0 && form.value.discount === 0) {
    selectedItems.value.forEach(item => item.percent = 0)
  }
})

watch(() => form.value.discount, (newVal) => {
  if (newVal > 0) {
    form.value.surcharge = 0
    isSurchargeMode.value = false
    selectedItems.value.forEach(item => item.percent = newVal)
  } else if (newVal === 0 && form.value.surcharge === 0) {
    selectedItems.value.forEach(item => item.percent = 0)
  }
})

// Methods
const toggleGroup = (groupName) => {
  expandedGroups.value[groupName] = !expandedGroups.value[groupName]
}

const toggleSurchargeMode = () => {
  isSurchargeMode.value = !isSurchargeMode.value
}

const getTabCode = (tabName) => {
  if (tabName === 'Giặt ủi') return 'LA'
  if (tabName === 'Minibar') return 'BR'
  if (tabName === 'Hàng đền bù') return 'CO'
  return tabName
}

const addProduct = (product) => {
  const existing = selectedItems.value.find(i => i.id === product.id)
  if (existing) {
    existing.quantity++
  } else {
    selectedItems.value.push({
      ...product,
      quantity: 1,
      note: '',
      editingNote: false,
      percent: form.value.surcharge > 0 ? form.value.surcharge : (form.value.discount > 0 ? form.value.discount : 0),
      uuid: Date.now() + Math.random()
    })
  }
}

const handleGroupCheck = (group, checked) => {
  if (checked) {
    group.items.forEach(product => {
      const existing = selectedItems.value.find(i => i.id === product.id)
      if (!existing) {
        addProduct(product)
      }
    })
  } else {
    selectedItems.value = selectedItems.value.filter(item => item.group !== group.name)
  }
}

const isProductSelected = (id) => {
  return selectedItems.value.some(item => item.id === id)
}

const removeProductById = (id) => {
  selectedItems.value = selectedItems.value.filter(item => item.id !== id)
}

const toggleTableGroup = (tabName) => {
  tableExpandedGroups.value[tabName] = tableExpandedGroups.value[tabName] === false ? true : false
}

const sendToRoom = async () => {
  if (!form.value.roomId) {
    uiStore.showToast('Vui lòng chọn phòng trước khi gửi.', 'warning')
    return
  }
  if (selectedItems.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất 1 sản phẩm/dịch vụ.', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận gửi bill',
    message: `Bạn có chắc chắn muốn gửi hóa đơn trị giá ${formatCurrency(totalAmount.value)} VNĐ về phòng không?`
  })
  if (!confirmed) return

  isSending.value = true
  setTimeout(() => {
    isSending.value = false
    console.log('Sending payload:', {
      form: form.value,
      items: selectedItems.value,
      total: totalAmount.value
    })
    uiStore.showToast('Đã gửi hóa đơn về phòng thành công!', 'success')
    // Reset
    selectedItems.value = []
    form.value.note = ''
    form.value.invoiceCode = ''
    groupChecked.value = {}
  }, 800)
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN').format(value)
}

// Click outside handling for suggestions & filters dropdown
const handleOutsideClick = (e) => {
  if (!e.target.closest('.search-container')) {
    showSuggestions.value = false
  }
  if (!e.target.closest('.group-filter-wrapper')) {
    showGroupDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
})
</script>

<style scoped>
/* Product Card visual lift feedback */
.prod-card {
  border: 1px solid #e2e8f0;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.prod-card:hover {
  transform: translateY(-2.5px);
  box-shadow: 0 8px 20px -6px rgba(148, 163, 184, 0.16);
  border-color: var(--hk-primary);
}
.prod-card:active {
  transform: scale(0.96);
}

/* Animated checkmark bounce in */
.animate-bounce-in {
  animation: bounceIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
}
@keyframes bounceIn {
  0% { transform: scale(0); }
  70% { transform: scale(1.2) rotate(10deg); }
  100% { transform: scale(1) rotate(0deg); }
}

/* Primary actions colors styling */
.btn-primary {
  background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5));
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px) scale(1.02);
  box-shadow: 0 4px 12px rgba(151, 213, 255, 0.4);
  filter: brightness(1.03);
}
.btn-primary:active:not(:disabled) {
  transform: translateY(0);
}

/* Accordion expand/collapse transition */
.hk-expand-enter-active,
.hk-expand-leave-active {
  transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
  overflow: hidden;
  max-height: 500px;
}
.hk-expand-enter-from,
.hk-expand-leave-to {
  max-height: 0;
  opacity: 0;
}

/* Autocomplete suggestion highlights */
mark {
  background-color: rgba(254, 243, 199, 0.8) !important;
}

/* Custom Dropdown Transition */
.hk-dropdown-enter-active {
  animation: hkDropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-dropdown-leave-active {
  animation: hkDropIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) reverse;
}
@keyframes hkDropIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>

