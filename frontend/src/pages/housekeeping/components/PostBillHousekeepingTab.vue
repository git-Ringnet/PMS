<template>
  <div class="flex flex-col h-full bg-gray-50/50 p-4 font-sans text-sm">
    <!-- Top Controls -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-4 bg-white p-4 rounded-lg shadow-sm border border-gray-100">
      <div class="col-span-1 md:col-span-2 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Chọn phòng</label>
        <select v-model="form.roomId" class="bg-yellow-50 border border-yellow-200 text-gray-700 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="">Chọn phòng</option>
          <option v-for="room in mockRooms" :key="room.id" :value="room.id">{{ room.name }}</option>
        </select>
      </div>
      
      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Ngày</label>
        <input type="date" v-model="form.date" class="border border-gray-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Tăng Giá</label>
        <div class="relative">
          <input type="number" v-model="form.surcharge" class="w-full border border-gray-300 rounded p-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-100/50" />
          <span class="absolute right-2 top-1.5 text-gray-400">%</span>
        </div>
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Giảm giá</label>
        <div class="relative">
          <input type="number" v-model="form.discount" class="w-full border border-gray-300 rounded p-1.5 pr-6 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-100/50" />
          <span class="absolute right-2 top-1.5 text-gray-400">%</span>
        </div>
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Mã hóa đơn</label>
        <input type="text" v-model="form.invoiceCode" class="border border-gray-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col items-center justify-center">
        <label class="text-xs font-semibold text-gray-500 mb-1">Miễn phí</label>
        <input type="checkbox" v-model="form.isFree" class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-blue-400" />
      </div>

      <div class="col-span-1 md:col-span-1 flex flex-col">
        <label class="text-xs font-semibold text-gray-500 mb-1">Ghi chú</label>
        <input type="text" v-model="form.note" placeholder="Ghi chú" class="border border-gray-300 rounded p-1.5 focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>
    </div>

    <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-4">
      <!-- Left Panel: Products -->
      <div class="w-full lg:w-1/2 flex flex-col bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <!-- Tabs & Search -->
        <div class="flex items-center border-b border-gray-200 p-2 gap-4">
          <div class="flex space-x-4 px-2">
            <button 
              v-for="tab in tabs" 
              :key="tab"
              @click="activeTab = tab"
              :class="[
                'pb-2 font-medium transition-colors border-b-2',
                activeTab === tab ? 'text-sky-500 border-sky-500' : 'text-gray-500 border-transparent hover:text-gray-700'
              ]"
            >
              {{ tab }}
            </button>
          </div>
          <div class="flex-1 relative flex items-center">
            <input 
              v-model="searchQuery" 
              type="text" 
              class="w-full border border-gray-300 rounded-l p-1.5 focus:outline-none focus:ring-1 focus:ring-sky-400" 
            />
            <button class="bg-sky-400 text-white px-3 py-1.5 rounded-r hover:bg-sky-500 transition-colors">
              <Search class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Product List Accordion -->
        <div class="flex-1 overflow-y-auto p-4 bg-gray-50/30">
          <div v-if="filteredGroups.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
            <Inbox class="w-12 h-12 mb-2 opacity-50" />
            <p>No data</p>
          </div>

          <div v-for="group in filteredGroups" :key="group.name" class="mb-4">
            <div class="flex items-center mb-2 w-full text-left">
              <input type="checkbox" v-model="groupChecked[group.name]" @change="handleGroupCheck(group, $event.target.checked)" class="mr-2 rounded text-sky-500 focus:ring-sky-400 w-4 h-4 cursor-pointer" />
              <button 
                @click="toggleGroup(group.name)"
                class="flex flex-1 items-center text-gray-700 font-medium hover:text-sky-600 transition-colors focus:outline-none"
              >
                {{ group.name }}
                <component :is="expandedGroups[group.name] ? ChevronUp : ChevronDown" class="w-4 h-4 ml-1 text-gray-400" />
              </button>
            </div>
            
            <div v-show="expandedGroups[group.name]" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 py-2 border-t border-gray-100">
              <div 
                v-for="product in group.items" 
                :key="product.id"
                @click="addProduct(product)"
                class="flex flex-col items-center cursor-pointer group p-2 hover:bg-white hover:shadow-sm rounded transition-all"
              >
                <div class="w-24 h-24 bg-gray-100 rounded flex items-center justify-center mb-2 relative overflow-hidden border border-gray-200 group-hover:border-sky-300">
                  <Image class="w-10 h-10 text-gray-300" />
                  <div class="absolute bottom-0 left-0 right-0 bg-sky-400/90 text-white text-[11px] text-center py-1 font-medium">
                    {{ formatCurrency(product.price) }}
                  </div>
                  <!-- Dấu tick nếu đã chọn -->
                  <button 
                    v-if="isProductSelected(product.id)"
                    @click.stop="removeProductById(product.id)"
                    class="absolute top-1 right-1 bg-sky-500 text-white rounded-full p-1 z-10 hover:bg-red-500 transition-colors shadow-sm"
                    title="Bỏ chọn"
                  >
                    <Check class="w-4 h-4" />
                  </button>
                </div>
                <span class="text-sm text-center font-medium text-gray-700 group-hover:text-sky-600 line-clamp-2" :title="product.name">
                  {{ product.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Invoice/Table -->
      <div class="w-full lg:w-1/2 flex flex-col bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-2 border-b border-gray-200">
          <button 
            @click="sendToRoom"
            class="bg-sky-400 text-white px-4 py-1.5 rounded flex items-center text-sm font-medium hover:bg-sky-500 transition-colors shadow-sm"
          >
            <Send class="w-4 h-4 mr-2" />
            Gửi về phòng
          </button>
        </div>
        
        <div class="flex-1 overflow-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-gray-100/80 sticky top-0 text-gray-600 border-b border-gray-200 z-10">
              <tr>
                <th class="p-2 font-medium w-10 border-r border-gray-200"></th>
                <th class="p-2 font-medium w-14 text-center">STT</th>
                <th class="p-2 font-medium">Sản phẩm</th>
                <th class="p-2 font-medium w-32">Ghi chú</th>
                <th class="p-2 font-medium text-right w-24">Giá</th>
                <th class="p-2 font-medium text-center w-20">Số lượng</th>
                <th class="p-2 font-medium text-center w-28 cursor-pointer hover:bg-gray-200 select-none transition-colors" @click="toggleSurchargeMode">
                  {{ isSurchargeMode ? 'Phần trăm phụ thu' : 'Phần trăm giảm giá' }}
                </th>
                <th class="p-2 font-medium text-right w-28">
                  {{ isSurchargeMode ? 'Tiền phụ thu' : 'Tiền giảm giá' }}
                </th>
                <th class="p-2 font-medium text-right w-24 text-sky-700">Tổng cộng</th>
                <th class="p-2 font-medium w-8 border-l border-gray-200"></th>
              </tr>
            </thead>
            <tbody v-if="selectedItems.length > 0">
              <template v-for="(items, tabName) in groupedSelectedItems" :key="tabName">
                <!-- Group Header -->
                <tr class="bg-gray-50 border-b border-gray-100 font-medium">
                  <td class="p-2 text-center border-r border-gray-200 w-10">
                    <button @click="toggleTableGroup(tabName)" class="bg-sky-100 hover:bg-sky-200 rounded p-1 transition-colors w-6 h-6 flex items-center justify-center">
                      <div v-if="tableExpandedGroups[tabName] !== false" class="w-3 h-0.5 bg-sky-500 rounded-sm"></div>
                      <div v-else class="relative w-3 h-3 flex items-center justify-center">
                        <div class="absolute w-3 h-0.5 bg-sky-500 rounded-sm"></div>
                        <div class="absolute h-3 w-0.5 bg-sky-500 rounded-sm"></div>
                      </div>
                    </button>
                  </td>
                  <td colspan="9" class="p-2 text-gray-700">{{ getTabCode(tabName) }}</td>
                </tr>
                <!-- Items -->
                <tr v-for="item in items" :key="item.uuid" v-show="tableExpandedGroups[tabName] !== false" class="border-b border-gray-100 hover:bg-gray-50/50">
                  <td class="p-2 border-r border-gray-200 w-10"></td>
                  <td class="p-2 text-center text-gray-500">{{ item.id }}</td>
                  <td class="p-2 text-gray-700">{{ item.name }}</td>
                  <td class="p-2">
                    <div v-if="item.editingNote">
                      <textarea 
                        v-model="item.note" 
                        @keydown.enter.prevent="item.editingNote = false"
                        @blur="item.editingNote = false"
                        class="w-full border-b border-gray-200 bg-transparent focus:outline-none focus:border-sky-400 text-xs resize-none"
                        rows="2"
                        autofocus
                      ></textarea>
                    </div>
                    <div v-else @click="item.editingNote = true" class="text-xs text-gray-600 cursor-pointer min-h-[20px] whitespace-pre-wrap break-words border-b border-dashed border-gray-200 hover:border-sky-400 pb-1">
                      {{ item.note || 'Thêm ghi chú...' }}
                    </div>
                  </td>
                  <td class="p-2 text-right">{{ formatCurrency(item.price) }}</td>
                  <td class="p-2 text-center">
                    <input type="number" min="1" v-model="item.quantity" class="w-12 border border-gray-300 rounded text-center focus:outline-none focus:ring-1 focus:ring-sky-400" />
                  </td>
                  <td class="p-2 text-center">
                    <input type="number" min="0" max="100" v-model="item.percent" class="w-16 border border-gray-300 rounded text-center focus:outline-none focus:ring-1 focus:ring-sky-400" />
                  </td>
                  <td class="p-2 text-right bg-gray-50/50 text-gray-600">
                    <span v-if="getLineModifier(item) > 0 && !isSurchargeMode">-</span>{{ formatCurrency(getLineModifier(item)) }}
                  </td>
                  <td class="p-2 text-right font-medium text-sky-600">
                    {{ formatCurrency(getLineTotal(item)) }}
                  </td>
                  <td class="p-2 text-center border-l border-gray-100">
                    <button @click="removeProductById(item.id)" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-1 rounded transition-colors">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
            <tbody v-else>
              <tr>
                <td colspan="9" class="p-12 text-center text-gray-400">
                  <div class="flex flex-col items-center justify-center">
                    <Inbox class="w-12 h-12 mb-2 opacity-50" />
                    <p>No data</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer Total -->
        <div class="bg-gray-100 p-3 flex justify-between font-bold text-gray-800 border-t border-gray-200">
          <span>Tổng cộng</span>
          <div class="flex gap-8">
            <span>Số lượng: <span class="text-sky-600">{{ totalQuantity }}</span></span>
            <span>Tổng tiền: <span class="text-sky-600">{{ formatCurrency(totalAmount) }}</span></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Search, ChevronDown, ChevronUp, Image, Trash2, Send, Inbox, Check } from '@lucide/vue'

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
const expandedGroups = ref({})
const tableExpandedGroups = ref({})
const groupChecked = ref({})
const selectedItems = ref([])
const isSurchargeMode = ref(false)

// Computed
const filteredGroups = computed(() => {
  const groupsMap = {}
  
  mockCatalog.forEach(product => {
    if (product.tab !== activeTab.value) return
    if (searchQuery.value && !product.name.toLowerCase().includes(searchQuery.value.toLowerCase())) return
    
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
    // Add all products in the group
    group.items.forEach(product => {
      // Ensure it's added at least once
      const existing = selectedItems.value.find(i => i.id === product.id)
      if (!existing) {
        addProduct(product)
      }
    })
  } else {
    // Remove all products in the group
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

const sendToRoom = () => {
  if (!form.value.roomId) {
    alert('Vui lòng chọn phòng trước khi gửi.')
    return
  }
  if (selectedItems.value.length === 0) {
    alert('Vui lòng chọn ít nhất 1 sản phẩm/dịch vụ.')
    return
  }
  console.log('Sending payload:', {
    form: form.value,
    items: selectedItems.value,
    total: totalAmount.value
  })
  alert('Đã gửi về phòng thành công (Mock)')
  // Reset
  selectedItems.value = []
  form.value.note = ''
  form.value.invoiceCode = ''
  groupChecked.value = {}
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN').format(value)
}
</script>

<style scoped>
::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
