<script setup>
import { ref, computed } from 'vue'

defineEmits(['back'])

const tabs = ['Thực đơn', 'Máy in', 'Chương trình khuyến mãi']
const activeSubTab = ref('Thực đơn')

const isMenuEnabled = ref(true)
const selectedFilter = ref('Nhà Hàng') // 'Tất cả' | 'Nhà Hàng' | 'Không bán'

const categories = ref([
  { id: 'all', name: 'TẤT CẢ NHÓM MÓN' },
  { id: 'khai-vi', name: 'MÓN KHAI VỊ' },
  { id: 'mon-chinh', name: 'MÓN CHÍNH' },
  { id: 'nuoc-uong', name: 'NƯỚC UỐNG & CAFE' },
  { id: 'trang-mieng', name: 'TRÁNG MIỆNG' }
])
const activeCategory = ref('all')

const handleAddCategory = () => {
  const name = prompt('Nhập tên loại thực đơn mới:')
  if (name) {
    const id = name.toLowerCase().replace(/\s+/g, '-')
    categories.value.push({ id, name: name.toUpperCase() })
  }
}

// Mock Menu Items
const menuItems = ref([
  { id: 'M001', code: 'PHO_BO', name: 'Phở Bò Kobe Đặc Biệt', shortName: 'Phở Bò Special', price: 185000, unit: 'Tô', openItem: false, desc: 'Phở bò Wagyu Kobe nước dùng hầm xương 24h', category: 'mon-chinh' },
  { id: 'M002', code: 'NEM_RAN', name: 'Nem Rán Hà Nội (3 chiếc)', shortName: 'Nem Rán HN', price: 65000, unit: 'Đĩa', openItem: false, desc: 'Nem nhân tôm thịt truyền thống rán giòn', category: 'khai-vi' },
  { id: 'M003', code: 'ESPRESSO', name: 'Cà phê Espresso Single', shortName: 'Espresso', price: 45000, unit: 'Ly', openItem: false, desc: 'Cà phê hạt Arabica pha máy chuẩn Ý', category: 'nuoc-uong' },
  { id: 'M004', code: 'TRA_DAO', name: 'Trà Đào Cam Sả Đá', shortName: 'Trà Đào CS', price: 55000, unit: 'Ly', openItem: false, desc: 'Trà đào thanh mát với cam vàng sả tươi', category: 'nuoc-uong' },
  { id: 'M005', code: 'BANH_FLAN', name: 'Bánh Flan Caramen Dừa', shortName: 'Flan Dừa', price: 40000, unit: 'Cái', openItem: false, desc: 'Bánh flan trứng sữa béo ngậy kèm cốt dừa', category: 'trang-mieng' }
])

// Toggle to simulate image "Trống" state
const forceEmptyState = ref(true)

const searchCode = ref('')
const searchName = ref('')
const searchShort = ref('')

const filteredMenuItems = computed(() => {
  if (forceEmptyState.value) return []
  
  return menuItems.value.filter(item => {
    // Filter by Category
    if (activeCategory.value !== 'all' && item.category !== activeCategory.value) {
      return false
    }
    // Filter by search inputs
    if (searchCode.value && !item.code.toLowerCase().includes(searchCode.value.toLowerCase())) return false
    if (searchName.value && !item.name.toLowerCase().includes(searchName.value.toLowerCase())) return false
    if (searchShort.value && !item.shortName.toLowerCase().includes(searchShort.value.toLowerCase())) return false
    return true
  })
})

const handleAddMenu = () => {
  if (forceEmptyState.value) forceEmptyState.value = false
  const name = prompt('Nhập tên món ăn mới:')
  if (name) {
    const code = name.toUpperCase().replace(/\s+/g, '_')
    const price = parseInt(prompt('Nhập đơn giá (VND):', '100000')) || 100000
    const unit = prompt('Nhập đơn vị tính (ví dụ: Tô, Đĩa, Ly):', 'Đĩa') || 'Đĩa'
    menuItems.value.push({
      id: `M00${menuItems.value.length + 1}`,
      code,
      name,
      shortName: name,
      price,
      unit,
      openItem: false,
      desc: '',
      category: activeCategory.value === 'all' ? 'mon-chinh' : activeCategory.value
    })
  }
}

const handleEditOutlet = () => {
  alert('Đang cấu hình outlet mở bán cho các thực đơn đã chọn...')
}

const handleAction = () => {
  alert('Đang chuẩn bị thao tác import/export excel...')
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-hidden">
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
      <div class="flex items-center gap-4 text-xs font-semibold pb-2">
        <!-- Thực đơn Toggle -->
        <div class="flex items-center gap-2">
          <span class="text-slate-600">Thực đơn</span>
          <button @click="isMenuEnabled = !isMenuEnabled" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="isMenuEnabled ? 'bg-sky-500' : 'bg-slate-300'">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="isMenuEnabled ? 'translate-x-[18px]' : 'translate-x-1'"></span>
          </button>
        </div>

        <!-- Filter Pills Group -->
        <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200">
          <button
            v-for="f in ['Tất cả', 'Nhà Hàng', 'Không bán']"
            :key="f"
            @click="selectedFilter = f"
            class="px-3 py-1 rounded-md text-[11px] font-bold uppercase transition"
            :class="selectedFilter === f ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'"
          >
            {{ f }}
          </button>
        </div>
      </div>
    </div>

    <!-- Layout: Left Sidebar for categories, Right for tables -->
    <div class="flex-1 flex gap-6 overflow-hidden min-h-0">
      <!-- Left sidebar categories -->
      <div class="w-56 bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex flex-col shrink-0">
        <button @click="handleAddCategory" class="w-full flex items-center justify-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition mb-4">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
          </svg>
          Thêm loại thực đơn
        </button>

        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 mb-2 block">Loại thực đơn</span>
        <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
          <button
            v-for="c in categories"
            :key="c.id"
            @click="activeCategory = c.id"
            class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition relative overflow-hidden"
            :class="activeCategory === c.id ? 'bg-sky-50 text-sky-700 font-bold' : 'text-slate-600 hover:bg-slate-100'"
          >
            <span v-if="activeCategory === c.id" class="absolute left-0 top-1 bottom-1 w-[3px] rounded-r-full bg-sky-500"></span>
            {{ c.name }}
          </button>
        </div>
      </div>

      <!-- Right content area -->
      <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative">
        <!-- Toolbar -->
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2">
            <button @click="handleAddMenu" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm hover:shadow active:scale-[0.98] transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Thêm thực đơn
            </button>
            <button @click="handleEditOutlet" class="flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-700 px-3.5 py-2 rounded-lg text-sm font-semibold active:scale-[0.98] transition shadow-sm">
              <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Sửa outlet mở bán
            </button>
            <button @click="handleAction" class="flex items-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-700 px-3.5 py-2 rounded-lg text-sm font-semibold active:scale-[0.98] transition shadow-sm">
              <!-- Excel / operations icon -->
              <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Thao tác
            </button>
          </div>
          
          <!-- Helper empty-state toggler -->
          <div class="flex items-center gap-2 text-xs text-slate-500">
            <span>Trạng thái trống:</span>
            <button @click="forceEmptyState = !forceEmptyState" class="relative inline-flex h-4.5 w-8 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="forceEmptyState ? 'bg-sky-500' : 'bg-slate-300'">
              <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="forceEmptyState ? 'translate-x-[14px]' : 'translate-x-0.5'"></span>
            </button>
          </div>
        </div>

        <!-- Table view -->
        <div class="flex-1 overflow-auto">
          <table class="w-full text-sm border-collapse whitespace-nowrap">
            <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
              <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <th class="px-3 py-3 text-left w-10 border-r border-slate-100">
                  <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                </th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Hình ảnh</th>
                <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[120px]">
                  <div class="flex flex-col gap-1">
                    <span>Mã</span>
                    <input v-if="!forceEmptyState" type="text" v-model="searchCode" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-xs bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                  </div>
                </th>
                <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[200px]">
                  <div class="flex flex-col gap-1">
                    <span>Tên</span>
                    <input v-if="!forceEmptyState" type="text" v-model="searchName" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-xs bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                  </div>
                </th>
                <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[140px]">
                  <div class="flex flex-col gap-1">
                    <span>Short Name</span>
                    <input v-if="!forceEmptyState" type="text" v-model="searchShort" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-xs bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                  </div>
                </th>
                <th class="px-4 py-3 text-right border-r border-slate-100">Đơn giá</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">ĐVT</th>
                <th class="px-4 py-3 text-center border-r border-slate-100">Open item</th>
                <th class="px-4 py-3 text-left">Mô tả</th>
              </tr>
            </thead>
            <tbody v-if="filteredMenuItems.length > 0" class="bg-white">
              <tr v-for="item in filteredMenuItems" :key="item.id" class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                <td class="px-3 py-2.5 border-r border-slate-100 text-center">
                  <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                </td>
                <td class="px-4 py-2.5 border-r border-slate-100">
                  <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200/50 flex items-center justify-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  </div>
                </td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-semibold text-slate-700">{{ item.code }}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 font-medium text-slate-800">{{ item.name }}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-600">{{ item.shortName }}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-right text-slate-900 font-bold">{{ item.price.toLocaleString('vi-VN') }}đ</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-slate-500 font-semibold">{{ item.unit }}</td>
                <td class="px-4 py-2.5 border-r border-slate-100 text-center">
                  <input type="checkbox" v-model="item.openItem" class="w-4 h-4 rounded border-slate-300 accent-sky-500 cursor-pointer" />
                </td>
                <td class="px-4 py-2.5 text-slate-400 text-xs italic">{{ item.desc || 'Chưa có mô tả' }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Empty State (Exactly as cloned from Image 3) -->
          <div v-if="filteredMenuItems.length === 0" class="w-full flex flex-col items-center justify-center py-24 text-slate-400">
            <!-- Box drawer empty icon -->
            <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
              <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
              </svg>
            </div>
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Trống</span>
            <button v-if="forceEmptyState" @click="forceEmptyState = false" class="mt-4 text-xs font-bold text-sky-500 hover:text-sky-600">Xem thử dữ liệu mẫu</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
