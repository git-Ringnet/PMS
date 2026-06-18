<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative overflow-hidden">
    
    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-5 shrink-0 overflow-hidden">
      <!-- Tabs Section -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-white">
        <div class="flex items-center gap-8 pt-2">
          <div 
            v-for="tab in tabs" 
            :key="tab"
            @click="activeTab = tab"
            class="group flex items-center gap-1.5 cursor-pointer"
          >
            <h2 
              class="text-[13px] font-bold tracking-wide uppercase pb-2 transition-colors relative" 
              :class="activeTab === tab ? 'text-sky-600' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ tab }}
              <div v-if="activeTab === tab" class="absolute bottom-0 left-0 w-full h-0.5 bg-sky-500 rounded-t-full"></div>
            </h2>
          </div>
        </div>
      </div>

      <!-- Toolbar Section -->
      <div class="px-5 py-4 bg-slate-50/50 flex items-center justify-between">
        <button class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-lg flex items-center gap-2 transition-all text-[12px] font-semibold shadow-sm">
          <PlusCircle class="w-4 h-4" stroke-width="2.5" />
          Tạo nhóm
        </button>

        <div class="flex items-center gap-3">
          <button class="bg-white hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg transition-all text-[12px] font-semibold flex items-center gap-2 shadow-sm">
            <Trash2 class="w-4 h-4" />
            Xóa
          </button>
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg transition-all text-[12px] font-semibold flex items-center gap-2 shadow-sm">
            <Printer class="w-4 h-4 text-slate-500" />
            In
          </button>
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg transition-all text-[12px] font-semibold flex items-center gap-2 shadow-sm">
            <FileSpreadsheet class="w-4 h-4 text-slate-500" />
            Xuất ra Excel
          </button>
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg transition-all text-[12px] font-semibold flex items-center gap-2 shadow-sm">
            <FileDown class="w-4 h-4 text-slate-500" />
            Nhập từ file Excel
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Area (Groups) -->
    <div class="flex-1 overflow-y-auto flex flex-col gap-5 pb-10">
      
      <!-- Group Card -->
      <div v-for="group in groups" :key="group.id" class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col">
        
        <!-- Group Header -->
        <div class="flex items-center gap-3 mb-5 border-b border-slate-100 pb-3">
          <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <span class="text-[13px] font-bold text-slate-700 whitespace-nowrap">Nhóm sản phẩm:</span>
          
          <!-- Inline Edit Group Name -->
          <input 
            v-if="group.isEditing"
            v-model="group.name"
            @blur="group.isEditing = false"
            @keyup.enter="group.isEditing = false"
            class="border border-sky-400 rounded-lg px-3 py-1.5 text-[14px] font-bold text-slate-800 outline-none w-[200px] shadow-sm focus:ring-2 focus:ring-sky-100 transition-all bg-white"
            v-focus
          />
          <span 
            v-else 
            @dblclick="group.isEditing = true"
            class="text-[15px] font-bold text-sky-600 cursor-pointer hover:bg-sky-50 px-2 py-1 rounded-lg select-none transition-colors uppercase tracking-wide"
            title="Nhấp đúp để sửa"
          >
            {{ group.name }}
          </span>

          <button @click="group.expanded = !group.expanded" class="text-slate-400 hover:text-sky-500 transition-colors ml-auto p-1.5 hover:bg-slate-50 rounded-lg">
            <ChevronUp v-if="group.expanded" class="w-5 h-5" />
            <ChevronDown v-else class="w-5 h-5" />
          </button>
        </div>

        <!-- Group Content (Products) -->
        <div v-show="group.expanded" class="flex flex-wrap gap-5 pl-2">
          
          <!-- Create Product Card -->
          <div 
            @click="showProductModal = true"
            class="w-[130px] h-[140px] border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center gap-2 cursor-pointer hover:bg-sky-50 hover:border-sky-300 transition-all bg-slate-50/50 shrink-0 group"
          >
            <div class="w-10 h-10 bg-white rounded-full shadow-sm flex items-center justify-center text-sky-400 group-hover:scale-110 transition-transform border border-slate-100">
              <Plus class="w-5 h-5" stroke-width="2.5" />
            </div>
            <span class="text-[12px] text-sky-600 font-bold">Tạo sản phẩm</span>
          </div>

          <!-- Product Cards -->
          <div v-for="product in group.products" :key="product.id" class="flex flex-col w-[130px] shrink-0 group">
            <!-- Card Image Area -->
            <div class="w-[130px] h-[130px] border border-slate-200 rounded-xl relative bg-white flex flex-col items-center justify-center overflow-hidden shadow-sm group-hover:border-sky-300 group-hover:shadow-md transition-all">
              <!-- Checkbox -->
              <input 
                type="checkbox" 
                v-model="product.checked"
                class="absolute top-2.5 right-2.5 w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer z-10"
              />
              
              <!-- Image Placeholder -->
              <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-6 group-hover:bg-sky-50 transition-colors">
                <ImageOff class="w-6 h-6 text-slate-300 group-hover:text-sky-300" stroke-width="2" />
              </div>

              <!-- Price Bar (Full width) -->
              <div class="absolute bottom-0 left-0 w-full bg-[#7ac7e6] text-white text-[12px] py-1.5 text-center font-bold">
                {{ product.price }}
              </div>
            </div>
            
            <!-- Product Name (Full text, wrapped) -->
            <div class="text-[12px] text-slate-700 font-bold text-center px-1 mt-2 break-words leading-tight">
              {{ product.name }}
            </div>
          </div>

          <!-- "No Data" Placeholder if group is empty -->
          <div v-if="group.products.length === 0" class="flex flex-col items-center justify-center w-[150px] h-[140px] opacity-60">
            <Inbox class="w-10 h-10 text-slate-300 mb-2" stroke-width="1.5" />
            <span class="text-[12px] text-slate-400 font-medium">Chưa có sản phẩm</span>
          </div>

        </div>
      </div>
    </div>

    <!-- PRODUCT MODAL (Modern Redesign) -->
    <div v-if="showProductModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="bg-white w-[800px] rounded-xl shadow-2xl flex flex-col overflow-hidden font-sans transform transition-all">
        
        <!-- Modal Header -->
        <div class="bg-[#7ac7e6] text-white px-5 py-3 flex justify-between items-center shrink-0">
          <h2 class="text-[15px] font-bold">Thêm Sản Phẩm Mới</h2>
          <button @click="showProductModal = false" class="text-white hover:text-sky-100 hover:bg-white/20 p-1 rounded-full transition-colors">
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 grid grid-cols-12 gap-6 text-[13px] text-slate-700 max-h-[80vh] overflow-y-auto bg-slate-50 flex-1">
          
          <!-- Left Column (Image & Status) -->
          <div class="col-span-4 flex flex-col gap-4">
            <!-- Image Upload -->
            <div class="flex flex-col gap-1.5">
              <label class="font-bold text-slate-700 text-[12px]">Hình ảnh sản phẩm</label>
              <div class="border-2 border-dashed border-slate-300 rounded-xl bg-white hover:bg-sky-50 hover:border-sky-300 transition-colors flex flex-col items-center justify-center gap-2 h-[150px] cursor-pointer group shadow-sm">
                <div class="w-10 h-10 bg-slate-50 rounded-full shadow-sm flex items-center justify-center text-sky-500 group-hover:scale-110 transition-transform border border-slate-100 group-hover:bg-white">
                  <Plus class="w-5 h-5" />
                </div>
                <div class="text-center">
                  <p class="text-sky-600 font-bold text-[12px]">Nhấn để tải ảnh lên</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">PNG, JPG tối đa 5MB</p>
                </div>
              </div>
            </div>

            <!-- Status Toggles -->
            <div class="flex flex-col gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" checked class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                <span class="font-bold text-slate-700 text-[12px]">Đang sử dụng (Active)</span>
              </label>
              
              <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                <span class="font-bold text-slate-700 text-[12px]">Giá linh động</span>
              </label>
            </div>
          </div>

          <!-- Right Column (Details & Pricing) -->
          <div class="col-span-8 flex flex-col gap-5">
            
            <!-- Basic Info Section -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-2 gap-4">
              <div class="flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Mã sản phẩm</label>
                <input type="text" value="SP-001" disabled class="border border-slate-200 rounded-lg bg-slate-100 px-3 py-2 text-slate-500 cursor-not-allowed font-mono text-[12px] outline-none" />
              </div>
              
              <div class="flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Tên sản phẩm <span class="text-rose-500">*</span></label>
                <input type="text" placeholder="Nhập tên sản phẩm..." class="border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium text-[13px] bg-white shadow-sm" />
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Nhóm sản phẩm</label>
                <div class="relative">
                  <select class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all text-[13px] shadow-sm">
                    <option>Minibar</option>
                    <option>Ăn vặt</option>
                  </select>
                  <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                </div>
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Đơn vị tính</label>
                <div class="relative">
                  <select class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all text-[13px] shadow-sm">
                    <option>Lon</option>
                    <option>Chai</option>
                    <option>Gói</option>
                  </select>
                  <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                </div>
              </div>
              
              <div class="flex flex-col gap-1.5 col-span-2">
                <label class="font-bold text-slate-700 text-[12px]">Quản lý tồn kho</label>
                <div class="relative">
                  <select class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all text-[13px] shadow-sm">
                    <option>Không theo dõi</option>
                    <option>Có theo dõi (Trừ kho tự động)</option>
                  </select>
                  <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                </div>
              </div>
            </div>

            <!-- Pricing Section (highlighted box) -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-3">
              <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mb-1 text-[13px]">Thiết lập Giá & Thuế</h3>
              <div class="grid grid-cols-2 gap-x-5 gap-y-4">
                
                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Giá gốc chưa thuế (VNĐ)</label>
                  <input type="number" value="0" class="w-full border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 font-medium text-[13px] shadow-sm" />
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-sky-600 text-[12px]">Giá bán cuối cùng (VNĐ)</label>
                  <input type="number" value="0" class="w-full border border-sky-400 rounded-lg bg-sky-50 px-3 py-2 outline-none focus:ring-2 focus:ring-sky-200 font-bold text-sky-700 text-[14px] shadow-sm" />
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 flex justify-between text-[12px]">
                    <span>Phí phục vụ (%)</span>
                    <span class="text-slate-400 text-[11px]">Thành tiền</span>
                  </label>
                  <div class="flex gap-2">
                    <input type="number" value="5" class="w-16 shrink-0 border border-slate-300 rounded-lg px-2 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-center text-[13px] shadow-sm" />
                    <input type="text" value="0" disabled class="flex-1 min-w-0 w-full border border-slate-200 rounded-lg bg-slate-100 px-3 py-2 outline-none text-right font-medium text-slate-500 text-[13px]" />
                  </div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 flex justify-between text-[12px]">
                    <span>Thuế VAT (%)</span>
                    <span class="text-slate-400 text-[11px]">Thành tiền</span>
                  </label>
                  <div class="flex gap-2">
                    <input type="number" value="8" class="w-16 shrink-0 border border-slate-300 rounded-lg px-2 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-center text-[13px] shadow-sm" />
                    <input type="text" value="0" disabled class="flex-1 min-w-0 w-full border border-slate-200 rounded-lg bg-slate-100 px-3 py-2 outline-none text-right font-medium text-slate-500 text-[13px]" />
                  </div>
                </div>

              </div>
            </div>

            <!-- Description -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1.5">
              <label class="font-bold text-slate-700 text-[12px]">Mô tả thêm</label>
              <textarea placeholder="Nhập ghi chú hoặc mô tả về sản phẩm..." class="border border-slate-300 rounded-lg px-3 py-2 outline-none h-[60px] resize-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all text-[13px] shadow-sm"></textarea>
            </div>

          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-white border-t border-slate-200 p-4 px-5 flex justify-end gap-3 shrink-0">
          <button @click="showProductModal = false" class="px-5 py-2 rounded-lg font-bold text-slate-600 bg-slate-100 border border-transparent hover:bg-slate-200 transition-colors shadow-sm text-[13px]">
            Hủy bỏ
          </button>
          <button @click="showProductModal = false" class="px-5 py-2 rounded-lg font-bold text-white bg-sky-500 hover:bg-sky-600 transition-colors shadow-sm flex items-center gap-2 text-[13px]">
            <Save class="w-4 h-4" stroke-width="2.5" />
            Lưu Sản Phẩm
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Trash2, Printer, FileSpreadsheet, FileDown, PlusCircle, ChevronUp, ChevronDown, ImageOff, Inbox, X, Check, Plus, Eye, Save } from '@lucide/vue'

const tabs = ['Minibar', 'Giặt ủi', 'Hàng đền bù', 'Amenity']
const activeTab = ref('Minibar')
const showProductModal = ref(false)

const groups = ref([
  {
    id: 1,
    name: 'ăn vặt',
    expanded: true,
    isEditing: false,
    products: []
  },
  {
    id: 2,
    name: 'Minibar',
    expanded: true,
    isEditing: false,
    products: [
      { id: 1, name: 'Bia Heineken', price: '35.000', checked: true },
      { id: 2, name: 'Nước suối Aquafina 500ml', price: '25.000', checked: false },
    ]
  }
])

// Custom directive for auto-focusing the input when inline editing
const vFocus = {
  mounted: (el) => el.focus()
}
</script>
