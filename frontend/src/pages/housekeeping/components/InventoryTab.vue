<script setup>
import { ref, computed } from 'vue'
import { Search, Plus, FileSpreadsheet, ClipboardList, ChevronLeft, ChevronRight, X, Trash2, Save, BarChart2 } from '@lucide/vue'

const showAddModal = ref(false)
const showCheckModal = ref(false)
const showEditModal = ref(false)
const showProductSearch = ref(false)
const showAddProductCheckModal = ref(false)
const editTabName = ref('')

const mbExpanded = ref(true)
const minibarExpanded = ref(true)

const activeTab = ref('minibar')

// Mock Data
const currentMonth = ref('2026-06')
const minibarCategories = ref([
  {
    name: 'Minibar',
    isExpanded: true,
    subgroups: [
      {
        name: 'Minibar',
        isExpanded: true,
        items: [
          { id: 1, name: 'Nước suối Aqua 500ml', startStock: 1000, sln: 500, slx: 40, slc: 0, finalStock: 1460, days: {} },
          { id: 2, name: 'Nước suối Aqua 1,5l', startStock: 1200, sln: 0, slx: 0, slc: 0, finalStock: 1200, days: {} }
        ]
      }
    ]
  }
])

const categories = computed(() => {
  if (activeTab.value === 'minibar') return minibarCategories.value;
  return []; // KHO BẾP trống
})

const openEditModal = (name) => {
  editTabName.value = name;
  showEditModal.value = true;
}

// Tự động tính số ngày trong tháng được chọn
const days = computed(() => {
  if (!currentMonth.value) return []
  const [year, month] = currentMonth.value.split('-')
  const daysInMonth = new Date(year, month, 0).getDate()
  return Array.from({ length: daysInMonth }, (_, i) => i + 1)
})

</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">
    
    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-5 shrink-0 overflow-hidden">
      <!-- Tabs Section -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-white">
        <div class="flex items-center gap-8 pt-2">
          <div class="group flex items-center gap-1.5 cursor-pointer" @click="activeTab = 'minibar'">
            <h2 class="text-[13px] font-bold tracking-wide uppercase pb-2 transition-colors relative" :class="activeTab === 'minibar' ? 'text-sky-600' : 'text-slate-500 hover:text-slate-700'">
              KHO MINIBAR
              <div v-if="activeTab === 'minibar'" class="absolute bottom-0 left-0 w-full h-0.5 bg-sky-500 rounded-t-full"></div>
            </h2>
            <button @click.stop="openEditModal('KHO MINIBAR')" class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-sky-500 hover:bg-sky-50 rounded-full -mt-2 bg-white">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
          <div class="group flex items-center gap-1.5 cursor-pointer" @click="activeTab = 'bep'">
            <h2 class="text-[13px] font-bold tracking-wide uppercase pb-2 transition-colors relative" :class="activeTab === 'bep' ? 'text-sky-600' : 'text-slate-500 hover:text-slate-700'">
              KHO BẾP
              <div v-if="activeTab === 'bep'" class="absolute bottom-0 left-0 w-full h-0.5 bg-sky-500 rounded-t-full"></div>
            </h2>
            <button @click.stop="openEditModal('KHO BẾP')" class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 text-slate-400 hover:text-sky-500 hover:bg-sky-50 rounded-full -mt-2 bg-white">
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </button>
          </div>
        </div>
        <button @click="showAddModal = true" class="flex items-center gap-1.5 px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[12px] font-semibold transition-all shadow-sm">
          <Plus class="w-4 h-4" stroke-width="2.5" />
          Thêm Kho
        </button>
      </div>

      <!-- Toolbar Section -->
      <div class="px-5 py-4 bg-slate-50/50 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <label class="text-[12px] font-medium text-slate-600">Tháng xem:</label>
          <input type="month" v-model="currentMonth" class="w-40 text-[13px] font-bold text-slate-700 bg-white border border-slate-300 rounded-lg px-3 py-1.5 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all shadow-sm cursor-pointer" />
        </div>
        <div class="flex items-center gap-3">
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg transition-all text-[12px] font-semibold flex items-center gap-2 shadow-sm">
            <FileSpreadsheet class="w-4 h-4 text-slate-500" />
            Xuất Excel
          </button>
          <button @click="showCheckModal = true" class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-lg transition-all text-[12px] font-semibold shadow-sm flex items-center gap-2">
            <ClipboardList class="w-4 h-4" stroke-width="2" />
            Kiểm Kê Định Kỳ
          </button>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1">
        <table class="w-full text-left border-collapse text-[13px] whitespace-nowrap min-w-max">
          <thead class="sticky top-0 z-20">
            <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-64 min-w-[256px] bg-slate-100 text-center align-middle sticky left-0 z-30 shadow-[1px_0_0_0_#e2e8f0]">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="flex items-center">
                    Sản Phẩm <Search @click="showProductSearch = !showProductSearch" class="w-3.5 h-3.5 inline-block ml-1.5 text-slate-400 cursor-pointer hover:text-sky-500 transition-colors" />
                  </div>
                  <div v-if="showProductSearch" class="relative w-full mt-1 px-2">
                    <Search class="absolute left-4 top-1.5 w-3.5 h-3.5 text-slate-400" />
                    <input type="text" placeholder="Tìm kiếm..." class="w-full pl-8 pr-3 py-1.5 text-xs font-normal bg-white border border-slate-300 rounded-md focus:border-sky-400 focus:ring-1 focus:ring-sky-400 outline-none transition-all shadow-sm" />
                  </div>
                </div>
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 w-20 min-w-[80px] bg-slate-100 text-center align-middle sticky left-[256px] z-30 shadow-[1px_0_0_0_#e2e8f0]">SLĐK</th>
              <th v-for="day in days" :key="day" colspan="3" class="py-2 px-2 text-center border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50' : 'bg-slate-50'">
                {{ day }}
              </th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLN</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLX</th>
              <th rowspan="2" class="py-3 px-4 border-r border-slate-200 bg-slate-100 text-center align-middle">SLC</th>
              <th rowspan="2" class="py-3 px-4 border-l border-slate-200 bg-slate-100 text-center align-middle sticky right-0 shadow-[-1px_0_0_0_#e2e8f0]">Tồn Cuối</th>
            </tr>
            <tr class="bg-white text-slate-500 font-bold border-b border-slate-200 text-[10px] uppercase">
              <template v-for="day in days" :key="'sub'+day">
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-emerald-50/50' : 'bg-slate-50/50'">Nhập</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-emerald-50/50' : 'bg-slate-50/50'">Xuất</th>
                <th class="py-1.5 px-2 text-center border-r border-slate-200 font-medium" :class="day % 2 === 0 ? 'bg-emerald-50/50' : 'bg-slate-50/50'">Chuyển</th>
              </template>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            <template v-for="(cat, idx) in categories" :key="idx">
              <!-- Parent Category Row -->
              <tr class="bg-slate-50 hover:bg-slate-100 font-bold border-b border-slate-200 transition-colors">
                <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-slate-50 flex items-center gap-2 cursor-pointer min-w-[256px] shadow-[1px_0_0_0_#e2e8f0]" @click="cat.isExpanded = !cat.isExpanded">
                  <div class="w-4 h-4 bg-sky-500 flex items-center justify-center text-white text-[12px] leading-none pb-[1px] rounded-sm shadow-sm">{{ cat.isExpanded ? '-' : '+' }}</div>
                  <span class="text-[13px] text-slate-800">{{ cat.name }}</span>
                </td>
                <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-slate-50 min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]"></td>
                <template v-for="day in days" :key="'cat-sub-'+day">
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/30' : ''"></td>
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/30' : ''"></td>
                  <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/30' : ''"></td>
                </template>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-2 border-r border-slate-200 bg-slate-50"></td>
                <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]"></td>
              </tr>
              <!-- Subgroups & Item Rows -->
              <template v-if="cat.isExpanded">
                <template v-for="(sub, subIdx) in cat.subgroups" :key="'sub-'+subIdx">
                  <!-- Subcategory Row -->
                  <tr class="bg-white hover:bg-slate-50 font-semibold border-b border-slate-100 transition-colors">
                    <td class="py-2.5 px-4 border-r border-slate-200 sticky left-0 z-10 bg-white flex items-center gap-2 cursor-pointer min-w-[256px] pl-8 shadow-[1px_0_0_0_#e2e8f0]" @click="sub.isExpanded = !sub.isExpanded">
                      <div class="w-4 h-4 bg-sky-400 flex items-center justify-center text-white text-[12px] leading-none pb-[1px] rounded-sm shadow-sm">{{ sub.isExpanded ? '-' : '+' }}</div>
                      <span class="text-[13px] text-slate-700">{{ sub.name }}</span>
                    </td>
                    <td class="py-2.5 px-2 border-r border-slate-200 sticky left-[256px] z-10 bg-white min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]"></td>
                    <template v-for="day in days" :key="'subcat-sub-'+day">
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                      <td class="py-2.5 px-2 border-r border-slate-200" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                    </template>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-2 border-r border-slate-200 bg-white"></td>
                    <td class="py-2.5 px-4 border-l border-slate-200 sticky right-0 bg-white shadow-[-1px_0_0_0_#e2e8f0]"></td>
                  </tr>
                  <!-- Items -->
                  <template v-if="sub.isExpanded">
                    <tr v-for="item in sub.items" :key="item.id" class="hover:bg-slate-50 transition-colors border-b border-slate-100 group">
                      <td class="py-2.5 px-4 border-r border-slate-200 pl-14 sticky left-0 z-10 bg-white group-hover:bg-slate-50 font-medium min-w-[256px] text-slate-600 shadow-[1px_0_0_0_#e2e8f0]">{{ item.name }}</td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 font-semibold sticky left-[256px] z-10 bg-white group-hover:bg-slate-50 min-w-[80px] shadow-[1px_0_0_0_#e2e8f0]">{{ item.startStock || '' }}</td>
                      <template v-for="day in days" :key="'item-day-'+day">
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                        <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-500" :class="day % 2 === 0 ? 'bg-emerald-50/20' : ''"></td>
                      </template>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50">{{ item.sln || '' }}</td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50">{{ item.slx || '' }}</td>
                      <td class="py-2.5 px-2 border-r border-slate-200 text-center text-slate-700 bg-white group-hover:bg-slate-50">{{ item.slc || '' }}</td>
                      <td class="py-2.5 px-4 border-l border-slate-200 text-right text-sky-600 font-black sticky right-0 bg-white group-hover:bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]">{{ item.finalStock ? item.finalStock.toLocaleString() : '' }}</td>
                    </tr>
                  </template>
                </template>
              </template>
            </template>
          </tbody>
        </table>
      </div>
      <!-- Horizontal Scrollbar Hint -->
      <div class="absolute bottom-1 left-1 right-1 h-1.5 bg-slate-200 rounded-full pointer-events-none opacity-50 z-40"></div>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4 flex items-center justify-between">
      <div class="text-[12px] text-slate-500 font-medium">Hiển thị dữ liệu kho</div>
      <div class="flex items-center gap-1.5">
        <button class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors shadow-sm" disabled>
          <ChevronLeft class="w-4 h-4" />
        </button>
        <button class="w-8 h-8 flex items-center justify-center border border-sky-500 bg-sky-500 text-white font-bold rounded-lg shadow-sm">
          1
        </button>
        <button class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition-colors shadow-sm" disabled>
          <ChevronRight class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-[#7ac7e6] px-5 py-3 flex items-center justify-between">
          <h3 class="text-white font-bold text-[15px]">Cập nhật tên kho</h3>
          <button @click="showEditModal = false" class="text-white hover:text-sky-100 hover:bg-white/20 p-1 rounded-full transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
        <!-- Body -->
        <div class="p-5 flex flex-col gap-2 bg-slate-50">
          <label class="text-[12px] font-bold text-slate-700">Tên kho</label>
          <input type="text" v-model="editTabName" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all bg-white shadow-sm" />
        </div>
        <!-- Footer -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3">
          <button @click="showEditModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors">
            Đóng
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Trash2 class="w-4 h-4" /> Xóa
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Save class="w-4 h-4" /> Lưu
          </button>
        </div>
      </div>
    </div>

    <!-- Add Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-[#7ac7e6] px-5 py-3 flex items-center justify-between">
          <h3 class="text-white font-bold text-[15px]">Thêm Kho Mới</h3>
          <button @click="showAddModal = false" class="text-white hover:text-sky-100 hover:bg-white/20 p-1 rounded-full transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
        <!-- Body -->
        <div class="p-5 flex flex-col gap-2 bg-slate-50">
          <label class="text-[12px] font-bold text-slate-700">Tên kho</label>
          <input type="text" placeholder="Nhập tên kho..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all bg-white shadow-sm" />
        </div>
        <!-- Footer -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3">
          <button @click="showAddModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors">
            Đóng
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Save class="w-4 h-4" /> Lưu
          </button>
        </div>
      </div>
    </div>

    <!-- Periodic Inventory Check Modal -->
    <div v-if="showCheckModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl w-[950px] flex flex-col overflow-hidden transform transition-all max-h-[90vh]">
        <!-- Header -->
        <div class="bg-[#7ac7e6] px-5 py-3 flex items-center justify-between shrink-0">
          <h3 class="text-white font-bold text-[16px]">Kiểm Kê Tồn Kho Định Kỳ</h3>
          <button @click="showCheckModal = false" class="text-white hover:text-sky-100 hover:bg-white/20 p-1 rounded-full transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
        <!-- Body -->
        <div class="p-5 flex flex-col gap-5 overflow-y-auto bg-slate-50 flex-1">
          <!-- Top Form -->
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-5 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Tháng / Năm</label>
              <input type="month" value="2026-06" class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all shadow-sm cursor-pointer" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Mã Kiểm Kê</label>
              <input type="text" value="9" disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Kho</label>
              <select disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none appearance-none">
                <option>KHO MINIBAR</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Người Kiểm Kho</label>
              <select disabled class="w-full text-[13px] border border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed outline-none appearance-none">
                <option>Admin</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-bold text-slate-700">Ghi Chú</label>
              <input type="text" placeholder="Nhập ghi chú..." class="w-full text-[13px] border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 transition-all shadow-sm" />
            </div>
          </div>

          <!-- Inventory Items Table -->
          <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
            <table class="w-full text-left border-collapse text-[12px]">
              <thead class="bg-slate-50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-600">
                <tr>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">Mã Kiểm Kê</th>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">Mã SP</th>
                  <th class="py-3 px-4 font-bold border-r border-slate-200">Tên SP</th>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">Đơn Vị</th>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">Tồn Đầu Kì</th>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">SL Thực Tế</th>
                  <th class="py-3 px-2 font-bold border-r border-slate-200 text-center">Chênh Lệch</th>
                  <th class="py-3 px-4 font-bold text-center">Ghi Chú</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-[13px] text-slate-700">
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500">9</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500">1</td>
                  <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">Nước suối Aqua 500ml</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center">Chai</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="0" class="w-16 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="1000" class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center font-bold text-sky-600">1000</td>
                  <td class="py-2 px-3"><input type="text" placeholder="..." class="w-full text-[13px] border border-slate-300 rounded-md px-2 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                </tr>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500">9</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center text-slate-500">2</td>
                  <td class="py-2 px-4 border-r border-slate-200 font-semibold text-slate-800">Nước suối Aqua 1,5l</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center">Chai</td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="0" class="w-16 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center"><input type="number" value="1200" class="w-20 text-center text-[13px] border border-slate-300 rounded-md px-1 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                  <td class="py-2 px-2 border-r border-slate-200 text-center font-bold text-sky-600">1200</td>
                  <td class="py-2 px-3"><input type="text" placeholder="..." class="w-full text-[13px] border border-slate-300 rounded-md px-2 py-1 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-all" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-end gap-3 shrink-0">
          <button class="px-5 py-2 flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors">
            <BarChart2 class="w-4 h-4 text-slate-500" /> Thống kê
          </button>
          <button class="px-5 py-2 flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors">
            <FileSpreadsheet class="w-4 h-4 text-slate-500" /> Xuất Excel
          </button>
          <button @click="showAddProductCheckModal = true" class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm ml-auto">
            <Plus class="w-4 h-4" stroke-width="2.5" /> Thêm SP
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Trash2 class="w-4 h-4" /> Xóa
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Save class="w-4 h-4" /> Lưu
          </button>
        </div>
      </div>
    </div>
    
    <!-- Thêm Sản Phẩm (Kiểm Kê) Modal -->
    <div v-if="showAddProductCheckModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl w-[400px] flex flex-col overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="bg-[#7ac7e6] px-5 py-3 flex items-center justify-between shrink-0">
          <h3 class="text-white font-bold text-[15px]">Thêm sản phẩm</h3>
          <button @click="showAddProductCheckModal = false" class="text-white hover:text-sky-100 hover:bg-white/20 p-1 rounded-full transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
        <!-- Body -->
        <div class="p-0 flex flex-col max-h-[60vh] overflow-y-auto bg-slate-50">
          <table class="w-full text-left border-collapse text-[13px]">
            <thead class="bg-slate-100 sticky top-0 z-10 shadow-sm border-b border-slate-200">
              <tr>
                <th class="py-2.5 px-3 border-r border-slate-200 w-12 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" /></th>
                <th class="py-2.5 px-4 font-bold text-slate-700">Sản Phẩm</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700">
              <tr class="bg-slate-50 hover:bg-slate-100 transition-colors">
                <td class="py-2.5 px-3 border-r border-slate-200 bg-transparent cursor-pointer" colspan="2" @click="mbExpanded = !mbExpanded">
                  <div class="flex items-center gap-2">
                    <div class="w-5 h-5 bg-sky-500 flex items-center justify-center text-white text-[14px] leading-none pb-[2px] rounded-md shadow-sm">{{ mbExpanded ? '-' : '+' }}</div>
                    <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" @click.stop />
                    <span class="font-bold text-slate-800">MB</span>
                  </div>
                </td>
              </tr>
              <template v-if="mbExpanded">
                <tr class="bg-white hover:bg-slate-50 transition-colors">
                  <td class="py-2.5 px-3 border-r border-slate-200 bg-transparent pl-8 cursor-pointer" colspan="2" @click="minibarExpanded = !minibarExpanded">
                    <div class="flex items-center gap-2">
                      <div class="w-4 h-4 bg-sky-400 flex items-center justify-center text-white text-[12px] leading-none pb-[1px] rounded-sm shadow-sm">{{ minibarExpanded ? '-' : '+' }}</div>
                      <span class="font-semibold text-slate-700">Minibar</span>
                    </div>
                  </td>
                </tr>
                <template v-if="minibarExpanded">
                  <tr class="hover:bg-slate-50 transition-colors bg-white">
                    <td class="py-2.5 px-3 border-r border-slate-200 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" /></td>
                    <td class="py-2.5 px-4 pl-14 text-slate-600">Nước suối Aqua 500ml</td>
                  </tr>
                  <tr class="hover:bg-slate-50 transition-colors bg-white">
                    <td class="py-2.5 px-3 border-r border-slate-200 text-center"><input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500" /></td>
                    <td class="py-2.5 px-4 pl-14 text-slate-600">Nước suối Aqua 1,5l</td>
                  </tr>
                </template>
              </template>
            </tbody>
          </table>
        </div>
        <!-- Footer -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex justify-end gap-3 shrink-0">
          <button @click="showAddProductCheckModal = false" class="px-5 py-2 flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[13px] font-bold transition-colors">
            Đóng
          </button>
          <button class="px-5 py-2 flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-[13px] font-bold transition-colors shadow-sm">
            <Save class="w-4 h-4" /> Lưu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
