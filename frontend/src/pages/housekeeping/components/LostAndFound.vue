<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import axios from 'axios'

const route = useRoute()
import { 
  Plus, Trash2, Printer, Search, Calendar, 
  MapPin, User, ArrowUpDown, X, Package, 
  Save, FolderOpen, CheckCircle, Info, Image as ImageIcon
} from '@lucide/vue'

const uiStore = useUiStore()

// --- API URL ---
const API_URL = '/api/lost-and-found'

// --- Data ---
const mockData = ref([])

const fetchItems = async () => {
  try {
    isLoading.value = true
    // Sử dụng token từ localStorage như router
    const token = localStorage.getItem('pms_token')
    const response = await axios.get(API_URL, {
      headers: { Authorization: `Bearer ${token}` }
    })
    mockData.value = response.data
  } catch (error) {
    uiStore.showToast('Không thể tải dữ liệu đồ thất lạc', 'error')
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

// --- Filter & Search States ---
const searchQuery = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const selectedMethod = ref('Tất cả')
const selectedLocation = ref('')
const selectedFinder = ref('')
const sortBy = ref('newest')

onMounted(() => {
  fetchItems()
  
  if (route.query.selectedMethod) {
    selectedMethod.value = route.query.selectedMethod
  }
  if (route.query.openAdd === 'true') {
    openAddModal()
  }
})

watch(() => route.query.selectedMethod, (newVal) => {
  selectedMethod.value = newVal || 'Tất cả'
})

watch(() => route.query.openAdd, (newVal) => {
  if (newVal === 'true') {
    openAddModal()
  }
})

const isLoading = ref(false)
const triggerSearchLoading = () => {
  isLoading.value = true
  setTimeout(() => {
    isLoading.value = false
  }, 400)
}

watch([searchQuery, dateFrom, dateTo, selectedMethod, selectedLocation, selectedFinder, sortBy], () => {
  triggerSearchLoading()
})

const uniqueLocations = computed(() => {
  const locs = mockData.value.map(item => item.where_found).filter(Boolean)
  return [...new Set(locs)]
})

const uniqueFinders = computed(() => {
  const finders = mockData.value.map(item => item.who_found).filter(Boolean)
  return [...new Set(finders)]
})

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || 
         dateFrom.value !== '' || 
         dateTo.value !== '' || 
         selectedMethod.value !== 'Tất cả' || 
         selectedLocation.value !== '' || 
         selectedFinder.value !== ''
})

const resetFilters = () => {
  searchQuery.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  selectedMethod.value = 'Tất cả'
  selectedLocation.value = ''
  selectedFinder.value = ''
}

// --- Filtered Data ---
const filteredData = computed(() => {
  let result = [...mockData.value]

  // 1. Search Query (category, location, finder)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    result = result.filter(item => {
      return (item.item_found && item.item_found.toLowerCase().includes(q)) ||
             (item.where_found && item.where_found.toLowerCase().includes(q)) ||
             (item.who_found && item.who_found.toLowerCase().includes(q))
    })
  }

  // 2. Date Range (foundDate)
  if (dateFrom.value) {
    result = result.filter(item => item.date_found >= dateFrom.value)
  }
  if (dateTo.value) {
    result = result.filter(item => item.date_found <= dateTo.value)
  }

  // 3. Status/Method Chip
  if (selectedMethod.value !== 'Tất cả') {
    if (selectedMethod.value === 'Chưa xử lý') {
      result = result.filter(item => !item.date_handling && item.method_handling !== 'Trả khách')
    } else {
      result = result.filter(item => item.method_handling === selectedMethod.value)
    }
  }

  // 4. Location Dropdown
  if (selectedLocation.value) {
    result = result.filter(item => item.where_found === selectedLocation.value)
  }

  // 5. Finder Dropdown
  if (selectedFinder.value) {
    result = result.filter(item => item.who_found === selectedFinder.value)
  }

  // 6. Sorting
  const getStatusWeight = (item) => {
    if (!item.method_handling) return 0; // chưa có
    if (item.method_handling === 'Lưu kho') return 1;
    if (item.method_handling === 'Trả khách') return 2;
    if (item.method_handling === 'Hủy') return 3;
    return 4;
  };

  result.sort((a, b) => {
    const weightA = getStatusWeight(a);
    const weightB = getStatusWeight(b);
    
    // Đẩy "Trả khách" và "Hủy" xuống dưới
    if (weightA !== weightB) {
      return weightA - weightB;
    }

    if (sortBy.value === 'newest') {
      const dateTimeA = new Date(`${a.date_found}T${a.time_found || '00:00'}`)
      const dateTimeB = new Date(`${b.date_found}T${b.time_found || '00:00'}`)
      return dateTimeB - dateTimeA
    } else if (sortBy.value === 'oldest') {
      const dateTimeA = new Date(`${a.date_found}T${a.time_found || '00:00'}`)
      const dateTimeB = new Date(`${b.date_found}T${b.time_found || '00:00'}`)
      return dateTimeA - dateTimeB
    } else if (sortBy.value === 'status') {
      return (a.method_handling || '').localeCompare(b.method_handling || '')
    }
    return 0
  })

  return result
})

// --- Table State ---
const selectedRows = ref([])
const toggleAll = (e) => {
  if (e.target.checked) {
    selectedRows.value = filteredData.value.map(item => item.id)
  } else {
    selectedRows.value = []
  }
}

// --- Lightbox Image State ---
const lightboxImage = ref(null)
const openLightbox = (url) => {
  lightboxImage.value = url
}
const closeLightbox = () => {
  lightboxImage.value = null
}

// --- Modal State ---
const showModal = ref(false)
const isEditing = ref(false)
const previewImage = ref(null)

const formState = reactive({
  id: null,
  image: '',
  item_found: '',
  time_found: '',
  date_found: '',
  where_found: '',
  who_found: '',
  received: '',
  date_handling: '',
  time_handling: '',
  method_handling: '',
  delieved_handling: '',
  received_handling: '',
  remarks: ''
})

const initialFormString = ref('')

const isDirty = computed(() => {
  return JSON.stringify(formState) !== initialFormString.value || previewImage.value !== null
})

const openAddModal = () => {
  isEditing.value = false
  previewImage.value = null
  const now = new Date()
  const currentDate = now.toISOString().split('T')[0]
  const currentTime = now.toTimeString().slice(0,5)
  
  Object.assign(formState, {
    id: null,
    image: '',
    item_found: '',
    time_found: currentTime,
    date_found: currentDate,
    where_found: '',
    who_found: '',
    received: '',
    date_handling: '',
    time_handling: '',
    method_handling: '',
    delieved_handling: '',
    received_handling: '',
    remarks: ''
  })
  initialFormString.value = JSON.stringify(formState)
  showModal.value = true
}

const openEditModal = (item) => {
  isEditing.value = true
  previewImage.value = null
  Object.assign(formState, JSON.parse(JSON.stringify(item)))
  initialFormString.value = JSON.stringify(formState)
  showModal.value = true
}

const closeModal = async () => {
  if (isDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn đóng không?' })
    if (!confirmed) return
  }
  showModal.value = false
}

const handleSave = async () => {
  // Bắt buộc nhập các trường thông tin tìm thấy và thông tin món đồ
  if (!formState.item_found || !formState.time_found || !formState.date_found || !formState.where_found || !formState.who_found || !formState.received) {
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin bắt buộc (*)', 'warning')
    return
  }

  // Bắt buộc nhập tiến trình xử lý nếu là Trả khách
  if (formState.method_handling === 'Trả khách') {
    if (!formState.date_handling || !formState.time_handling || !formState.delieved_handling || !formState.received_handling) {
      uiStore.showToast('Vui lòng nhập đầy đủ Ngày, Giờ, Người trả và Người nhận ở Tiến trình xử lý', 'warning')
      return
    }
  }

  // Bắt buộc nhập tiến trình xử lý nếu là Hủy (trừ người nhận)
  if (formState.method_handling === 'Hủy') {
    if (!formState.date_handling || !formState.time_handling || !formState.delieved_handling) {
      uiStore.showToast('Vui lòng nhập đầy đủ Ngày, Giờ và Người xử lý ở Tiến trình xử lý', 'warning')
      return
    }
  }
  
  try {
    const token = localStorage.getItem('pms_token')
    const headers = { Authorization: `Bearer ${token}` }
    
    // Gán ảnh nếu có
    if (previewImage.value) {
      formState.image = previewImage.value
    }
    
    if (isEditing.value) {
      await axios.put(`${API_URL}/${formState.id}`, formState, { headers })
      uiStore.showToast('Cập nhật thành công', 'success')
    } else {
      await axios.post(API_URL, formState, { headers })
      uiStore.showToast('Thêm mới thành công', 'success')
    }
    
    await fetchItems()
    initialFormString.value = JSON.stringify(formState)
    previewImage.value = null
    showModal.value = false
  } catch (error) {
    uiStore.showToast('Có lỗi xảy ra khi lưu dữ liệu', 'error')
    console.error(error)
  }
}

const handleDelete = async () => {
  if (selectedRows.value.length === 0) return
  const confirmed = await uiStore.confirm({ message: `Bạn có chắc muốn xóa ${selectedRows.value.length} mục đã chọn không?` })
  if (!confirmed) return
  
  try {
    const token = localStorage.getItem('pms_token')
    const headers = { Authorization: `Bearer ${token}` }
    
    // Xóa từng mục được chọn (nếu có API bulk delete thì tốt hơn, nhưng ở đây gọi từng cái)
    for (const id of selectedRows.value) {
      await axios.delete(`${API_URL}/${id}`, { headers })
    }
    
    selectedRows.value = []
    await fetchItems()
    uiStore.showToast('Xóa thành công', 'success')
  } catch (error) {
    uiStore.showToast('Có lỗi xảy ra khi xóa', 'error')
    console.error(error)
  }
}


// Image upload simulation
const onFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      previewImage.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeImage = () => {
  previewImage.value = null
  formState.image = ''
}

</script>

<template>
  <div class="h-full flex flex-col bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden font-sans">
    
    <!-- TOOLBAR -->
    <div class="p-4 border-b border-slate-200 bg-slate-50 shrink-0 flex flex-col gap-4">
      <!-- Row 1: Actions & Search & Sort -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
          <button @click="openAddModal" class="px-4 py-2 bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 rounded-lg text-sm font-bold shadow-sm transition-all cursor-pointer border-none flex items-center gap-2">
            <Plus class="w-4 h-4" />
            Thêm mới
          </button>

          <button 
            @click="handleDelete"
            :disabled="selectedRows.length === 0"
            class="px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-all cursor-pointer border-none flex items-center gap-2"
            :class="selectedRows.length > 0 ? 'bg-rose-500 hover:bg-rose-600 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
          >
            <Trash2 class="w-4 h-4" />
            Xóa ({{ selectedRows.length }})
          </button>
        </div>

        <div class="flex items-center gap-3 flex-1 max-w-lg">
          <!-- Text search -->
          <div class="relative flex-1">
            <Search class="w-4 h-4 text-slate-450 absolute left-3 top-2.5 pointer-events-none" />
            <input 
              type="text" 
              data-hk-search
              v-model="searchQuery" 
              placeholder="Tìm tên món đồ, địa điểm, người tìm..."
              class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-xs"
            />
          </div>

          <!-- Sort dropdown -->
          <div class="flex items-center gap-1.5 shrink-0">
            <ArrowUpDown class="w-4 h-4 text-slate-400" />
            <select 
              v-model="sortBy"
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all cursor-pointer font-medium"
            >
              <option value="newest">Mới nhất</option>
              <option value="oldest">Cũ nhất</option>
              <option value="status">Theo trạng thái</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-750 border border-slate-300 rounded-lg text-sm font-bold shadow-sm transition-all cursor-pointer flex items-center gap-2">
            <Printer class="w-4 h-4 text-slate-500" />
            In danh sách
          </button>
        </div>
      </div>

      <!-- Row 2: Status chips & Date Pickers & Location/Finder selects -->
      <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-200/80">
        <!-- Status chips -->
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mr-1">Trạng thái:</span>
          <button 
            v-for="chip in [
              { label: 'Tất cả', value: 'Tất cả' },
              { label: 'Lưu kho', value: 'Lưu kho' },
              { label: 'Trả khách', value: 'Trả khách' },
              { label: 'Hủy', value: 'Hủy' },
              { label: 'Chưa xử lý', value: 'Chưa xử lý' }
            ]"
            :key="chip.value"
            @click="selectedMethod = chip.value"
            class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 border cursor-pointer"
            :class="selectedMethod === chip.value
              ? 'bg-[var(--hk-primary-light)] text-slate-800 border-[var(--hk-primary)] shadow-sm font-bold scale-[1.02]' 
              : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
          >
            {{ chip.label }}
          </button>
        </div>

        <!-- Date Range & Location / Finder select filters -->
        <div class="flex items-center gap-3 flex-wrap">
          <!-- Date pickers -->
          <div class="flex items-center gap-1.5">
            <Calendar class="w-4 h-4 text-slate-400" />
            <input 
              type="date" 
              v-model="dateFrom" 
              class="border border-slate-350 rounded-lg px-2 py-1 text-xs text-slate-650 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-xs" 
            />
            <span class="text-slate-400 text-xs">—</span>
            <input 
              type="date" 
              v-model="dateTo" 
              class="border border-slate-350 rounded-lg px-2 py-1 text-xs text-slate-650 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] bg-white transition-all shadow-xs" 
            />
          </div>

          <!-- Location Dropdown -->
          <div class="flex items-center gap-1.5">
            <MapPin class="w-4 h-4 text-slate-400" />
            <select 
              v-model="selectedLocation"
              class="border border-slate-350 rounded-lg px-2 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all cursor-pointer min-w-[120px]"
            >
              <option value="">Tất cả địa điểm</option>
              <option v-for="loc in uniqueLocations" :key="loc" :value="loc">{{ loc }}</option>
            </select>
          </div>

          <!-- Finder Dropdown -->
          <div class="flex items-center gap-1.5">
            <User class="w-4 h-4 text-slate-400" />
            <select 
              v-model="selectedFinder"
              class="border border-slate-350 rounded-lg px-2 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all cursor-pointer min-w-[140px]"
            >
              <option value="">Tất cả người tìm</option>
              <option v-for="finder in uniqueFinders" :key="finder" :value="finder">{{ finder }}</option>
            </select>
          </div>

          <!-- Clear Filters -->
          <button 
            v-if="hasActiveFilters" 
            @click="resetFilters" 
            class="text-xs text-rose-500 hover:text-rose-600 font-bold px-2 py-1.5 transition-colors cursor-pointer border-none bg-transparent"
          >
            Reset bộ lọc
          </button>
        </div>
      </div>
    </div>

    <!-- DATA TABLE -->
    <div class="flex-1 overflow-auto bg-white p-4 print:p-0 hk-scroll">
      <table class="w-full text-sm text-left border-collapse border border-slate-200 min-w-[1200px] relative">
        <thead class="sticky top-0 z-20 bg-white shadow-sm print:shadow-none">
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold uppercase">
            <th rowspan="2" class="p-2 border border-slate-200 w-10 text-center print:hidden">
              <input 
                type="checkbox" 
                @change="toggleAll" 
                :checked="filteredData.length > 0 && selectedRows.length === filteredData.length" 
                class="rounded text-sky-500 w-4 h-4 cursor-pointer" 
              />
            </th>
            <th colspan="3" class="p-2 border border-slate-200 text-center text-[11px]">Vật phẩm</th>
            <th colspan="4" class="p-2 border border-slate-200 text-center text-[11px]">Thông tin liên quan đến vật phẩm được tìm thấy</th>
            <th colspan="1" class="p-2 border border-slate-200 text-center text-[11px]">Người giữ/Vị trí cất giữ</th>
            <th colspan="5" class="p-2 border border-slate-200 text-center text-[11px] bg-emerald-50">Xử lý</th>
            <th rowspan="2" class="p-2 border border-slate-200 text-center text-[11px] w-48">Ghi Chú</th>
          </tr>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold text-xs">
            <th class="p-2 border border-slate-200 w-10 text-center">STT</th>
            <th class="p-2 border border-slate-200 w-14 text-center">Img</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Tên danh mục</th>
            <th class="p-2 border border-slate-200 w-16 text-center">Giờ</th>
            <th class="p-2 border border-slate-200 w-24 text-center">Ngày</th>
            <th class="p-2 border border-slate-200 min-w-[100px]">Địa Điểm</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Người Tìm Thấy</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Người Nhận</th>
            <th class="p-2 border border-slate-200 w-24 text-center bg-emerald-50/50">Ngày xử lý</th>
            <th class="p-2 border border-slate-200 w-16 text-center bg-emerald-50/50">Giờ xử lý</th>
            <th class="p-2 border border-slate-200 min-w-[110px] text-center bg-emerald-50/50">Phương Thức</th>
            <th class="p-2 border border-slate-200 min-w-[120px] bg-emerald-50/50">Người Xử Lý</th>
            <th class="p-2 border border-slate-200 min-w-[120px] bg-emerald-50/50">Tên Người Nhận</th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading Skeleton rows -->
          <template v-if="isLoading">
            <tr v-for="i in 3" :key="'skeleton-'+i" class="animate-pulse">
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-4 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-6 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-8 w-8 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-28 bg-slate-200 rounded"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-10 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-20 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-24 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-24 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-10 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200 text-center"><div class="h-4 w-16 bg-slate-200 rounded mx-auto"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-24 bg-slate-200 rounded"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-24 bg-slate-200 rounded"></div></td>
              <td class="p-2 border border-slate-200"><div class="h-4 w-32 bg-slate-200 rounded"></div></td>
            </tr>
          </template>

          <!-- Empty State -->
          <tr v-else-if="filteredData.length === 0">
            <td colspan="15" class="p-16 text-center text-slate-500 font-medium bg-slate-50">
              <div class="flex flex-col items-center justify-center gap-2 max-w-sm mx-auto">
                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                  <Package class="w-6 h-6" />
                </div>
                <p class="text-sm font-bold text-slate-700">Không tìm thấy dữ liệu đồ thất lạc</p>
                <p class="text-xs text-slate-500">Thử thay đổi từ khóa hoặc bộ lọc của bạn xem sao.</p>
                <button v-if="hasActiveFilters" @click="resetFilters" class="mt-2 px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer">
                  Xóa bộ lọc
                </button>
              </div>
            </td>
          </tr>

          <!-- Data Rows -->
          <tr 
            v-for="(item, index) in filteredData" 
            :key="item.id"
            @dblclick="openEditModal(item)"
            class="border-b border-slate-100 transition-colors group cursor-pointer text-sm"
            :class="selectedRows.includes(item.id) ? 'bg-[rgba(151,213,255,0.15)] hover:bg-[rgba(151,213,255,0.2)]' : 'odd:bg-white even:bg-slate-50/30 hover:bg-[rgba(151,213,255,0.1)]'"
          >
            <td class="p-2 border border-slate-200 text-center print:hidden" @click.stop>
              <input type="checkbox" :value="item.id" v-model="selectedRows" class="rounded text-sky-500 w-4 h-4 cursor-pointer" />
            </td>
            <td class="p-2 border border-slate-200 text-center font-semibold text-slate-500">{{ index + 1 }}</td>
            <td class="p-1 border border-slate-200 text-center">
              <div 
                v-if="item.image" 
                @click.stop="openLightbox(item.image)"
                class="w-10 h-10 mx-auto rounded overflow-hidden border border-slate-200 hover:scale-105 transition-transform cursor-zoom-in"
                title="Click để phóng to ảnh"
              >
                <img :src="item.image" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-10 h-10 mx-auto rounded bg-slate-100 flex items-center justify-center border border-slate-200 text-slate-400">
                <ImageIcon class="w-5 h-5 text-slate-350" />
              </div>
            </td>
            <td class="p-2 border border-slate-200 font-bold text-slate-800">{{ item.item_found }}</td>
            <td class="p-2 border border-slate-200 text-center text-slate-650 font-semibold">{{ item.time_found }}</td>
            <td class="p-2 border border-slate-200 text-center text-slate-650">{{ item.date_found }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.where_found }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.who_found }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.received }}</td>
            <td class="p-2 border border-slate-200 text-center text-emerald-600">{{ item.date_handling || '-' }}</td>
            <td class="p-2 border border-slate-200 text-center text-emerald-600 font-semibold">{{ item.time_handling || '-' }}</td>
            <td class="p-2 border border-slate-200 text-center">
              <span v-if="item.method_handling === 'Trả khách'" class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-250 rounded text-[11px] font-bold uppercase shadow-[0_0_8px_rgba(16,185,129,0.15)]">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 shadow-[0_0_4px_rgba(16,185,129,0.5)]"></span>
                {{ item.method_handling }}
              </span>
              <span v-else-if="item.method_handling === 'Lưu kho'" class="inline-flex items-center px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-250 rounded text-[11px] font-bold uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse mr-1.5 shadow-[0_0_4px_rgba(245,158,11,0.5)]"></span>
                {{ item.method_handling }}
              </span>
              <span v-else-if="item.method_handling === 'Hủy'" class="inline-flex items-center px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded text-[11px] font-bold uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>
                {{ item.method_handling }}
              </span>
              <span v-else-if="item.method_handling" class="inline-flex items-center px-2 py-0.5 bg-slate-50 text-slate-650 border border-slate-200 rounded text-[11px] font-bold uppercase">
                {{ item.method_handling }}
              </span>
              <span v-else class="text-slate-400 italic text-xs">Chưa có</span>
            </td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.delieved_handling || '-' }}</td>
            <td class="p-2 border border-slate-200 text-slate-700 font-semibold">{{ item.received_handling || '-' }}</td>
            <td class="p-2 border border-slate-200 text-slate-500 text-xs" :title="item.remarks">
              <div class="line-clamp-2">{{ item.remarks || '-' }}</div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="mt-4 text-xs text-slate-500 italic print:hidden">Mẹo: Click đúp vào một dòng để xem hoặc cập nhật thông tin. Click vào hình ảnh để phóng to.</div>
    </div>

    <!-- LIGHTBOX PREVIEW -->
    <Teleport to="body">
      <div v-if="lightboxImage" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 print:hidden" @click="closeLightbox">
        <div class="relative max-w-4xl max-h-[90vh] flex items-center justify-center animate-fade-in" @click.stop>
          <img :src="lightboxImage" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl object-contain border border-white/10" />
          <button @click="closeLightbox" class="absolute -top-12 right-0 text-white hover:text-slate-350 bg-black/40 hover:bg-black/60 p-2 rounded-full transition-all border-none cursor-pointer">
            <X class="w-6 h-6" />
          </button>
        </div>
      </div>
    </Teleport>

    <!-- ADD/EDIT MODAL -->
    <Teleport to="body">
      <Transition name="hk-modal">
        <div v-if="showModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6 print:hidden">
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-black/50 backdrop-blur-xs" @click="closeModal"></div>
          
          <!-- Modal Content -->
          <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-full flex flex-col overflow-hidden">
          
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 text-slate-800 shrink-0" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="font-bold text-lg m-0 flex items-center gap-2 text-slate-800">
              <Package class="w-6 h-6 text-slate-700" />
              {{ isEditing ? 'Cập nhật Đồ Thất Lạc' : 'Thêm mới Đồ Thất Lạc' }}
            </h3>
            <button @click="closeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-black/10 transition-colors cursor-pointer bg-transparent border-none text-slate-700">
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              
              <!-- Left Column -->
              <div class="flex flex-col gap-6">
                <!-- Khối 1: Thông tin món đồ -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-sky-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <ImageIcon class="w-4 h-4 text-sky-500" />
                    Thông tin món đồ
                  </h4>
                  <div class="space-y-4">
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Mặt hàng / Tên danh mục <span class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.item_found" placeholder="Ví dụ: Áo khoác, Điện thoại..." class="w-full px-3 py-2 border border-slate-355 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)] focus:ring-1 focus:ring-[var(--hk-primary)] font-semibold text-slate-800" />
                    </div>
                    
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Hình ảnh đính kèm</label>
                      <div class="flex items-start gap-4">
                        <div class="w-24 h-24 shrink-0 bg-slate-100 border-2 border-dashed border-slate-300 rounded-xl overflow-hidden flex items-center justify-center relative group">
                          <img v-if="previewImage || formState.image" :src="previewImage || formState.image" class="w-full h-full object-cover" />
                          <Package v-else class="w-8 h-8 text-slate-350" />
                          
                          <div v-if="previewImage || formState.image" class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="removeImage" class="p-1 bg-rose-500 text-white rounded-full hover:bg-rose-600 border-none cursor-pointer">
                              <X class="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                        <div class="flex-1">
                          <input type="file" id="imageUpload" accept="image/*" @change="onFileChange" class="hidden" />
                          <label for="imageUpload" class="inline-block px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-bold shadow-sm hover:bg-slate-50 cursor-pointer transition-colors">
                            Chọn ảnh
                          </label>
                          <p class="mt-2 text-xs text-slate-500">Hỗ trợ JPG, PNG (Tối đa 5MB).</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Khối 2: Thông tin tìm thấy -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-sky-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <Search class="w-4 h-4 text-sky-500" />
                    Thông tin Tìm thấy
                  </h4>
                  <div class="space-y-4">
                    <div class="flex gap-4">
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Giờ tìm thấy <span class="text-red-500">*</span></label>
                        <input type="time" v-model="formState.time_found" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                      </div>
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Ngày tìm thấy <span class="text-red-500">*</span></label>
                        <input type="date" v-model="formState.date_found" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Địa điểm <span class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.where_found" placeholder="Ví dụ: Phòng 101, Hồ bơi..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người tìm thấy <span class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.who_found" placeholder="Tên nhân viên..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người nhận (Người giữ / Vị trí cất giữ) <span class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.received" placeholder="Ví dụ: Lễ tân, Kho..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column -->
              <div class="flex flex-col gap-6">
                <!-- Khối 3: Xử lý -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-emerald-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <CheckCircle class="w-4 h-4 text-emerald-500" />
                    Tiến trình Xử lý
                  </h4>
                  <div class="space-y-4">
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Phương thức xử lý</label>
                      <select v-model="formState.method_handling" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)] font-semibold" :class="formState.method_handling === 'Trả khách' ? 'text-emerald-700' : formState.method_handling === 'Lưu kho' ? 'text-amber-700' : 'text-slate-800'">
                        <option value="">-- Chọn phương thức --</option>
                        <option value="Lưu kho">Lưu kho chờ xử lý</option>
                        <option value="Trả khách">Trả lại cho khách</option>
                        <option value="Hủy">Tiêu hủy / Bỏ đi</option>
                      </select>
                    </div>
                    <div class="flex gap-4">
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Ngày xử lý <span v-if="formState.method_handling === 'Trả khách' || formState.method_handling === 'Hủy'" class="text-red-500">*</span></label>
                        <input type="date" v-model="formState.date_handling" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                      </div>
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Giờ xử lý <span v-if="formState.method_handling === 'Trả khách' || formState.method_handling === 'Hủy'" class="text-red-500">*</span></label>
                        <input type="time" v-model="formState.time_handling" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người bàn giao (Người xử lý) <span v-if="formState.method_handling === 'Trả khách' || formState.method_handling === 'Hủy'" class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.delieved_handling" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)]" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Tên người nhận (Khách hàng) <span v-if="formState.method_handling === 'Trả khách'" class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.received_handling" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)] font-bold" />
                    </div>
                  </div>
                </div>

                <!-- Khối 4: Ghi chú -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex-1 flex flex-col">
                  <h4 class="text-sm font-bold text-slate-700 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2 shrink-0">
                    <Info class="w-4 h-4 text-slate-500" />
                    Ghi chú thêm
                  </h4>
                  <div class="flex-1 min-h-[100px]">
                    <textarea v-model="formState.remarks" placeholder="Ghi chú các thông tin quan trọng..." class="w-full h-full min-h-[100px] px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-[var(--hk-primary-dark)] focus:border-[var(--hk-primary)] resize-none"></textarea>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50 border-t border-slate-200 shrink-0">
            <button @click="closeModal" class="px-5 py-2.5 rounded-lg text-sm font-bold text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer shadow-sm">
              Đóng
            </button>
            <button @click="handleSave" class="px-6 py-2.5 rounded-lg text-sm font-bold text-slate-900 bg-[var(--hk-primary-dark)] hover:brightness-95 border-none transition-colors cursor-pointer shadow-sm flex items-center gap-2">
              <Save class="w-4 h-4" />
              Lưu Thông Tin
            </button>
          </div>
          
        </div>
      </div>
    </Transition>
  </Teleport>
  </div>
</template>

<style scoped>
@media print {
  @page { margin: 1cm; size: landscape; }
  body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}

.animate-fade-in-up {
  animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.animate-fade-in {
  animation: fadeIn 0.2s ease-out;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}
</style>
