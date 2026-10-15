<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative overflow-hidden">
    <LoadingOverlay :show="isLoading" />
    
    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-4 shrink-0 overflow-hidden">
      <!-- Tabs Section -->
      <div class="px-5 py-2.5 border-b border-slate-100 flex items-center justify-between bg-white">
        <div class="flex items-center gap-6 pt-1">
          <div 
            v-for="outlet in housekeepingOutlets" 
            :key="outlet.code"
            @click="activeTab = outlet.code"
            class="group flex items-center gap-1.5 cursor-pointer select-none"
          >
            <h2 
              class="text-xs font-semibold tracking-wide uppercase pb-2 transition-colors relative" 
              :class="activeTab === outlet.code ? 'text-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'"
            >
              {{ outlet.name }}
              <div v-if="activeTab === outlet.code" class="absolute bottom-0 left-0 w-full h-0.5 bg-[#006bdb] rounded-t-full"></div>
            </h2>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="file" ref="fileInput" @change="handleFileUpload" accept=".xlsx, .xls, .csv" class="hidden" />
          <button @click="$refs.fileInput.click()" class="btn-pms-secondary h-8">
            <Upload class="w-3.5 h-3.5 text-emerald-600" />
            Nhập Excel
          </button>
          <button @click="exportExcel" class="btn-pms-secondary h-8">
            <Download class="w-3.5 h-3.5 text-sky-600" />
            Xuất Excel
          </button>
        </div>
      </div>

      <!-- Toolbar Section -->
      <div class="px-5 py-3 bg-slate-50/70 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-3">
          <button @click="createGroup" class="btn-pms-primary h-8">
            <PlusCircle class="w-3.5 h-3.5" />
            Tạo nhóm
          </button>

          <!-- Search product -->
          <div class="relative flex items-center">
            <input 
              v-model="filterState.search" 
              type="text" 
              placeholder="Tìm sản phẩm..."
              class="h-8 border border-slate-300 rounded-lg pl-3 pr-7 text-xs text-[#000000D9] bg-white focus:outline-none focus:ring-1 focus:ring-[#006bdb] focus:border-[#006bdb] placeholder:text-[#A8B0BF] shadow-xs w-44" 
            />
            <button 
              v-if="filterState.search" 
              type="button" 
              @click="filterState.search = ''" 
              class="absolute right-2 text-slate-400 hover:text-slate-600"
              title="Xóa tìm kiếm"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Status filter (appearance-none with ChevronDown & X toggle) -->
          <div class="flex items-center gap-1.5">
            <span class="font-medium text-slate-600">Trạng thái:</span>
            <div class="relative flex items-center">
              <select 
                v-model="filterState.status" 
                :class="[
                  'h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white cursor-pointer focus:outline-none focus:ring-1 focus:ring-[#006bdb] shadow-xs appearance-none',
                  filterState.status ? 'text-[#000000D9] font-medium' : 'text-[#A8B0BF]'
                ]"
              >
                <option value="" disabled hidden>Tất cả</option>
                <option value="active" class="text-[#000000D9]">Active</option>
                <option value="inactive" class="text-[#000000D9]">Inactive</option>
              </select>
              <ChevronDown v-if="!filterState.status" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
              <button 
                v-else 
                type="button" 
                @click="filterState.status = ''" 
                class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                title="Xóa bộ lọc"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Price range (Formatted with comma ',' and quick clear 'x') -->
          <div class="flex items-center gap-1.5">
            <span class="font-medium text-slate-600">Giá từ:</span>
            <div class="relative flex items-center">
              <input 
                type="text" 
                placeholder="Min" 
                v-model="displayPriceMin" 
                class="w-24 h-8 border border-slate-300 rounded-lg pl-2.5 pr-6 text-xs focus:outline-none focus:ring-1 focus:ring-[#006bdb] bg-white text-[#000000D9] placeholder:text-[#A8B0BF] shadow-xs" 
              />
              <button 
                v-if="filterState.priceMin !== null && filterState.priceMin !== ''" 
                type="button" 
                @click="filterState.priceMin = null" 
                class="absolute right-1.5 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                title="Xóa giá min"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
            <span class="font-medium text-slate-600">đến</span>
            <div class="relative flex items-center">
              <input 
                type="text" 
                placeholder="Max" 
                v-model="displayPriceMax" 
                class="w-24 h-8 border border-slate-300 rounded-lg pl-2.5 pr-6 text-xs focus:outline-none focus:ring-1 focus:ring-[#006bdb] bg-white text-[#000000D9] placeholder:text-[#A8B0BF] shadow-xs" 
              />
              <button 
                v-if="filterState.priceMax !== null && filterState.priceMax !== ''" 
                type="button" 
                @click="filterState.priceMax = null" 
                class="absolute right-1.5 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                title="Xóa giá max"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Inventory tracking (appearance-none with ChevronDown & X toggle) -->
          <div class="flex items-center gap-1.5">
            <span class="font-medium text-slate-600">Tồn kho:</span>
            <div class="relative flex items-center">
              <select 
                v-model="filterState.trackStock" 
                :class="[
                  'h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white cursor-pointer focus:outline-none focus:ring-1 focus:ring-[#006bdb] shadow-xs appearance-none',
                  filterState.trackStock ? 'text-[#000000D9] font-medium' : 'text-[#A8B0BF]'
                ]"
              >
                <option value="" disabled hidden>Tất cả</option>
                <option value="tracked" class="text-[#000000D9]">Có theo dõi</option>
                <option value="untracked" class="text-[#000000D9]">Không theo dõi</option>
              </select>
              <ChevronDown v-if="!filterState.trackStock" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
              <button 
                v-else 
                type="button" 
                @click="filterState.trackStock = ''" 
                class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                title="Xóa bộ lọc"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Sort dropdown -->
          <div class="flex items-center gap-1.5">
            <span class="font-medium text-slate-600">Sắp xếp:</span>
            <div class="relative flex items-center">
              <select v-model="filterState.sortBy" class="h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs focus:outline-none focus:ring-1 focus:ring-[#006bdb] bg-white cursor-pointer text-[#000000D9] shadow-xs appearance-none">
                <option value="name_asc">Tên A-Z</option>
                <option value="name_desc">Tên Z-A</option>
                <option value="price_asc">Giá tăng dần</option>
                <option value="price_desc">Giá giảm dần</option>
              </select>
              <ChevronDown class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button @click="toggleActiveSelected" class="btn-pms-secondary h-8">
            <Power class="w-3.5 h-3.5" />
            Bật / Tắt
          </button>
          <button @click="deleteSelected" class="btn-pms-danger h-8">
            <Trash2 class="w-3.5 h-3.5" />
            Xóa
          </button>
          <button class="btn-pms-secondary h-8">
            <Printer class="w-3.5 h-3.5 text-slate-500" />
            In
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Area (Groups) -->
    <div class="flex-1 overflow-y-auto flex flex-col gap-4 pb-10 hk-scroll">
      
      <!-- Group Card -->
      <div v-for="group in filteredGroups" :key="group.id" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col shrink-0">
        
        <!-- Group Header -->
        <div class="flex items-center gap-3 mb-4 border-b border-slate-100 pb-2.5">
          <input type="checkbox" :checked="group.checked" @change="toggleGroupCheck(group.id, $event.target.checked, group.products)" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nhóm sản phẩm:</span>
          
          <!-- Inline Edit Group Name -->
          <input 
            v-if="group.isEditing"
            :value="group.name"
            @blur="updateGroupName(group.id, $event.target.value)"
            @keyup.enter="updateGroupName(group.id, $event.target.value)"
            class="border border-sky-400 rounded-lg px-2.5 py-1 text-xs font-semibold text-[#000000D9] outline-none w-[200px] shadow-xs focus:ring-1 focus:ring-sky-200 transition-all bg-white"
            v-focus
          />
          <span 
            v-else 
            @dblclick="setEditing(group.id)"
            class="text-xs font-bold text-[#006bdb] cursor-pointer hover:bg-slate-50 px-2 py-1 rounded-lg select-none transition-colors uppercase tracking-wider"
            title="Nhấp đúp để sửa"
          >
            {{ group.name }}
          </span>

          <button @click="deleteGroup(group.id)" class="text-rose-400 hover:text-rose-600 transition-colors ml-auto p-1.5 hover:bg-rose-50 rounded-lg cursor-pointer" title="Xóa nhóm">
            <Trash2 class="w-4 h-4" />
          </button>
          <button @click="toggleExpanded(group.id)" class="text-slate-400 hover:text-[#006bdb] transition-colors p-1.5 hover:bg-slate-50 rounded-lg cursor-pointer">
            <ChevronUp v-if="group.expanded" class="w-4 h-4" />
            <ChevronDown v-else class="w-4 h-4" />
          </button>
        </div>

        <!-- Group Content (Products) -->
        <Transition name="hk-expand">
          <div v-show="group.expanded" class="flex flex-wrap gap-3.5 pl-1">
            
            <!-- Create Product Card -->
            <div 
              @click="openCreateProduct(group.id)"
              class="w-[110px] h-[125px] border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center gap-1.5 cursor-pointer hover:bg-sky-50 hover:border-sky-300 transition-all bg-slate-50/50 shrink-0 group"
            >
              <div class="w-8 h-8 bg-white rounded-full shadow-xs flex items-center justify-center text-sky-500 transition-all border border-slate-100 animate-pulse-subtle group-hover:brightness-95">
                <Plus class="w-4 h-4 text-[#006bdb]" stroke-width="2.5" />
              </div>
              <span class="text-xs text-sky-700 font-semibold">Tạo sản phẩm</span>
            </div>

            <!-- Product Cards -->
            <template v-if="isLoading">
              <div v-for="i in 3" :key="'skeleton-'+i" class="w-[110px] h-[130px] flex flex-col shrink-0 animate-pulse">
                <div class="w-[110px] h-[95px] border border-slate-200 bg-slate-200/50 rounded-xl flex items-center justify-center shadow-xs">
                  <div class="w-8 h-8 rounded-full bg-slate-300/60"></div>
                </div>
                <div class="h-2.5 bg-slate-200 rounded w-14 mx-auto mt-2"></div>
              </div>
            </template>
            <template v-else>
              <div 
                v-for="product in group.products" 
                :key="product.id" 
                class="flex flex-col w-[110px] shrink-0 group relative select-none" 
                @dblclick="editProduct(product)"
              >
                <!-- Card Image Area (w-[110px] h-[95px]) -->
                <div :class="[
                  'w-[110px] h-[95px] border rounded-xl relative flex flex-col items-center justify-center overflow-hidden shadow-xs transition-all hk-card-lift cursor-pointer hover:border-sky-300',
                  product.isActive ? 'bg-white border-slate-200' : 'bg-slate-50 border-slate-300 grayscale opacity-75'
                ]">
                  <!-- Checkbox -->
                  <input 
                    type="checkbox" 
                    v-model="product.checked"
                    class="absolute top-2 right-2 w-3.5 h-3.5 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer z-10"
                  />
                  
                  <!-- Product Image / Outlet Fallback Image -->
                  <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mb-5 group-hover:bg-sky-50 transition-colors overflow-hidden">
                    <img 
                      v-if="getProductImage(product)" 
                      :src="getProductImage(product)" 
                      class="w-full h-full object-cover" 
                      @error="$event.target.style.display = 'none'"
                    />
                    <ImageOff v-else class="w-5 h-5 text-slate-300 group-hover:text-[#006bdb]" stroke-width="2" />
                  </div>

                  <!-- Price Bar (Formatted with commas ',') -->
                  <div class="absolute bottom-0 left-0 right-0 text-slate-800 text-xs py-0.5 text-center font-bold" style="background: linear-gradient(135deg, #c7e8ff, #97d5ff)">
                    {{ formatNumber(product.priceNum) }} VNĐ
                  </div>
                </div>
                
                <!-- Product Name -->
                <div class="text-xs text-[#000000D9] font-medium text-center px-0.5 mt-1.5 break-words leading-tight flex flex-col gap-0.5">
                  <span class="line-clamp-2" :title="product.name">{{ product.name }}</span>
                  <span v-if="!product.isActive" class="text-xs bg-slate-100 text-slate-500 px-1 py-0.5 rounded border border-slate-200 self-center leading-none">Inactive</span>
                </div>
              </div>
            </template>

            <!-- "No Data" Placeholder if group is empty -->
            <div v-if="group.products.length === 0" class="flex flex-col items-center justify-center w-[130px] h-[125px] opacity-60">
              <Inbox class="w-8 h-8 text-slate-300 mb-1" stroke-width="1.5" />
              <span class="text-xs text-slate-400 font-normal">Chưa có sản phẩm</span>
            </div>

          </div>
        </Transition>
      </div>
    </div>

    <!-- PRODUCT MODAL (Draggable & Theme Synchronized) -->
    <Transition name="hk-modal">
      <div v-if="showProductModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div 
          class="relative bg-white w-[820px] rounded-xl shadow-2xl flex flex-col overflow-hidden font-sans border border-slate-200"
          :style="{ transform: 'translate(' + productModalPos.x + 'px, ' + productModalPos.y + 'px)' }"
        >
          <!-- Modal Header (Draggable, Title Case, Theme Synchronized) -->
          <div 
            class="px-5 py-3 flex justify-between items-center shrink-0 shadow-sm text-white cursor-move select-none rounded-t-xl" 
            @mousedown="startDragProductModal"
            :style="{ background: topbarThemeBg, color: 'var(--pms-custom-theme-text, #ffffff)' }"
          >
            <h2 class="text-xs font-semibold tracking-wide text-white">
              {{ newProduct.id ? 'Cập Nhật Sản Phẩm' : 'Thêm Sản Phẩm Mới' }}
            </h2>
            <button 
              type="button"
              @click="showProductModal = false" 
              class="text-white hover:bg-white/20 p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"
              title="Đóng (Esc)"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 grid grid-cols-12 gap-5 text-xs text-[#000000D9] max-h-[82vh] overflow-y-auto bg-slate-50 flex-1 hk-scroll">
            
            <!-- Left Column (Image & Status) -->
            <div class="col-span-4 flex flex-col gap-3.5">
              <!-- Image Upload -->
              <div class="flex flex-col gap-1">
                <label class="font-semibold text-xs text-[#000000D9]">Hình Ảnh Sản Phẩm</label>
                <div class="dashed-upload rounded-xl bg-white flex flex-col items-center justify-center gap-2 h-[145px] cursor-pointer group shadow-xs relative overflow-hidden">
                  <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                  <img v-if="newProduct.imageUrl" :src="newProduct.imageUrl" class="absolute inset-0 w-full h-full object-cover" />
                  <div v-else class="flex flex-col items-center w-full">
                    <div class="w-9 h-9 bg-slate-50 rounded-full shadow-xs flex items-center justify-center text-sky-500 group-hover:scale-105 transition-transform border border-slate-100 group-hover:bg-white mb-1.5">
                      <Plus class="w-4 h-4 text-[#006bdb]" stroke-width="2.5" />
                    </div>
                    <div class="text-center px-2">
                      <p class="text-[#006bdb] font-semibold text-xs">Tải Ảnh Sản Phẩm Lên</p>
                      <p class="text-xs text-slate-400 mt-0.5">Nhấp Chuột Hoặc Kéo Thả</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Status Toggles -->
              <div class="flex flex-col gap-2.5 bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="newProduct.isActive" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  <span class="font-semibold text-xs text-[#000000D9]">Đang Sử Dụng (Active)</span>
                </label>

                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="newProduct.openKey" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  <span class="font-semibold text-xs text-[#000000D9]">Mở Bán (OpenKey)</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="newProduct.flexiblePrice" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  <span class="font-semibold text-xs text-[#000000D9]">Giá Bán Linh Động</span>
                </label>
              </div>
            </div>

            <!-- Right Column (Details & Pricing) -->
            <div class="col-span-8 flex flex-col gap-4">
              
              <!-- Basic Info Section -->
              <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs grid grid-cols-2 gap-3.5">
                <!-- Mã sản phẩm (Real code / next code, never SP - AUTO) -->
                <div class="flex flex-col gap-1">
                  <label class="font-semibold text-xs text-[#000000D9]">Mã Sản Phẩm</label>
                  <input 
                    type="text" 
                    :value="newProduct.product_code" 
                    disabled 
                    placeholder="Mã tự sinh..." 
                    class="w-full h-8 border border-slate-200 rounded-lg bg-slate-100 px-2.5 text-slate-600 cursor-not-allowed font-mono text-xs outline-none" 
                  />
                </div>
                
                <!-- Tên sản phẩm (Required yellow #FFF8DB / #F1DD8A with clear button) -->
                <div class="flex flex-col gap-1">
                  <label class="font-semibold text-xs text-[#000000D9]">Tên Sản Phẩm <span class="text-rose-500">*</span></label>
                  <div class="relative flex items-center">
                    <input 
                      type="text" 
                      required 
                      v-model="newProduct.name" 
                      placeholder="Nhập tên sản phẩm..." 
                      class="w-full h-8 border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg pl-2.5 pr-7 text-xs text-[#000000D9] font-normal outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308] transition-all shadow-xs" 
                    />
                    <button 
                      v-if="newProduct.name" 
                      type="button" 
                      @click="newProduct.name = ''" 
                      class="absolute right-2 text-slate-400 hover:text-slate-600"
                      title="Xóa tên"
                    >
                      <X class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Nhóm sản phẩm (Searchable combobox, Required yellow) -->
                <div class="flex flex-col gap-1">
                  <label class="font-semibold text-xs text-[#000000D9]">Nhóm Sản Phẩm <span class="text-rose-500">*</span></label>
                  <SearchableSelect
                    v-model="newProduct.groupId"
                    :options="groupSelectOptions"
                    placeholder="Chọn nhóm sản phẩm..."
                    search-placeholder="Tìm kiếm nhóm..."
                    required
                    @change="onGroupChanged"
                  />
                </div>

                <!-- Đơn vị tính (Searchable combobox, Required yellow) -->
                <div class="flex flex-col gap-1">
                  <label class="font-semibold text-xs text-[#000000D9]">Đơn Vị Tính <span class="text-rose-500">*</span></label>
                  <SearchableSelect
                    v-model="newProduct.currency"
                    :options="unitSelectOptions"
                    placeholder="Chọn đơn vị tính..."
                    search-placeholder="Tìm đơn vị..."
                    required
                  />
                </div>
                
                <!-- Quản lý tồn kho -->
                <div class="flex flex-col gap-1" :class="{'col-span-2': !newProduct.trackStock}">
                  <label class="font-semibold text-xs text-[#000000D9]">Quản Lý Tồn Kho</label>
                  <div class="relative flex items-center">
                    <select v-model="newProduct.trackStock" class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs text-[#000000D9] outline-none cursor-pointer focus:ring-1 focus:ring-[#006bdb] focus:border-[#006bdb] shadow-xs appearance-none bg-white">
                      <option :value="false">Không theo dõi</option>
                      <option :value="true">Có theo dõi (Trừ kho tự động)</option>
                    </select>
                    <ChevronDown class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
                  </div>
                </div>

                <!-- Chọn kho (Searchable combobox) -->
                <div v-if="newProduct.trackStock" class="flex flex-col gap-1">
                  <label class="font-semibold text-xs text-[#000000D9]">Chọn Kho</label>
                  <SearchableSelect
                    v-model="newProduct.inventory_id"
                    :options="inventorySelectOptions"
                    placeholder="Chọn kho lưu trữ..."
                    search-placeholder="Tìm kiếm kho..."
                  />
                </div>
              </div>

              <!-- Pricing Section (Title Case, comma-separated numbers, clear buttons) -->
              <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs flex flex-col gap-2.5">
                <h3 class="font-semibold text-[#000000D9] border-b border-slate-100 pb-1.5 text-xs tracking-wide">Thiết Lập Giá & Thuế</h3>
                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                  
                  <!-- Giá gốc chưa thuế (VNĐ) -->
                  <div class="flex flex-col gap-1">
                    <label class="font-semibold text-xs text-slate-700">Giá Gốc Chưa Thuế (VNĐ)</label>
                    <div class="relative flex items-center">
                      <input 
                        type="text" 
                        v-model="displayBasePrice" 
                        placeholder="0" 
                        class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs text-[#000000D9] outline-none focus:ring-1 focus:ring-[#006bdb] focus:border-[#006bdb] shadow-xs" 
                      />
                      <button 
                        v-if="newProduct.original_amount" 
                        type="button" 
                        @click="clearBasePrice" 
                        class="absolute right-2 text-slate-400 hover:text-slate-600"
                        title="Xóa giá gốc"
                      >
                        <X class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>

                  <!-- Giá bán cuối cùng (VNĐ) -->
                  <div class="flex flex-col gap-1">
                    <label class="font-semibold text-xs text-[#006bdb]">Giá Bán Cuối Cùng (VNĐ)</label>
                    <div class="relative flex items-center">
                      <input 
                        type="text" 
                        v-model="displayFinalPrice" 
                        placeholder="0" 
                        class="w-full h-8 border border-sky-300 rounded-lg bg-sky-50 pl-2.5 pr-7 text-xs text-[#006bdb] font-bold outline-none focus:ring-1 focus:ring-[#006bdb] focus:border-[#006bdb] shadow-xs" 
                      />
                      <button 
                        v-if="newProduct.price" 
                        type="button" 
                        @click="clearFinalPrice" 
                        class="absolute right-2 text-slate-400 hover:text-slate-600"
                        title="Xóa giá bán"
                      >
                        <X class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>

                  <!-- Phí phục vụ (%) -->
                  <div class="flex flex-col gap-1">
                    <label class="font-semibold text-xs text-[#000000D9]">Phí Phục Vụ (%)</label>
                    <div class="flex items-center gap-1.5">
                      <div class="inline-flex items-center border border-slate-300 rounded-lg bg-white shadow-xs h-8 overflow-hidden focus-within:ring-1 focus-within:ring-[#006bdb] focus-within:border-[#006bdb]">
                        <div class="relative flex items-center w-16">
                          <input 
                            type="number" 
                            v-model.number="newProduct.service_charge_percent" 
                            @input="onTaxPercentInput" 
                            placeholder="0"
                            min="0"
                            max="100"
                            class="w-full h-full text-center text-xs text-[#000000D9] outline-none bg-transparent font-medium pl-1.5 pr-4 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                          />
                          <button 
                            v-if="newProduct.service_charge_percent" 
                            type="button" 
                            @click="clearServiceCharge" 
                            class="absolute right-0.5 text-slate-300 hover:text-slate-600 p-0.5 cursor-pointer"
                            title="Xóa"
                          >
                            <X class="w-3 h-3" />
                          </button>
                        </div>

                        <!-- Nút Lên/Xuống xếp dọc -->
                        <div class="flex flex-col w-5 h-full border-l border-slate-200 divide-y divide-slate-200">
                          <button 
                            type="button" 
                            @click="adjustServiceCharge(1)" 
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Tăng 1%"
                          >
                            <ChevronUp class="w-3 h-3" />
                          </button>
                          <button 
                            type="button" 
                            @click="adjustServiceCharge(-1)" 
                            :disabled="!newProduct.service_charge_percent || newProduct.service_charge_percent <= 0"
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Giảm 1%"
                          >
                            <ChevronDown class="w-3 h-3" />
                          </button>
                        </div>
                      </div>

                      <span v-if="scAmount > 0" class="text-xs text-sky-600 font-semibold whitespace-nowrap">
                        (+ {{ formatNumber(scAmount) }}đ)
                      </span>
                    </div>
                  </div>

                  <!-- Thuế đặc biệt (%) -->
                  <div class="flex flex-col gap-1">
                    <label class="font-semibold text-xs text-[#000000D9]">Thuế Đặc Biệt (%)</label>
                    <div class="flex items-center gap-1.5">
                      <div class="inline-flex items-center border border-slate-300 rounded-lg bg-white shadow-xs h-8 overflow-hidden focus-within:ring-1 focus-within:ring-[#006bdb] focus-within:border-[#006bdb]">
                        <div class="relative flex items-center w-16">
                          <input 
                            type="number" 
                            v-model.number="newProduct.special_tax_percent" 
                            @input="onTaxPercentInput" 
                            placeholder="0"
                            min="0"
                            max="100"
                            class="w-full h-full text-center text-xs text-[#000000D9] outline-none bg-transparent font-medium pl-1.5 pr-4 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                          />
                          <button 
                            v-if="newProduct.special_tax_percent" 
                            type="button" 
                            @click="clearSpecialTaxPercent" 
                            class="absolute right-0.5 text-slate-300 hover:text-slate-600 p-0.5 cursor-pointer"
                            title="Xóa"
                          >
                            <X class="w-3 h-3" />
                          </button>
                        </div>

                        <!-- Nút Lên/Xuống xếp dọc -->
                        <div class="flex flex-col w-5 h-full border-l border-slate-200 divide-y divide-slate-200">
                          <button 
                            type="button" 
                            @click="adjustSpecialTax(1)" 
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Tăng 1%"
                          >
                            <ChevronUp class="w-3 h-3" />
                          </button>
                          <button 
                            type="button" 
                            @click="adjustSpecialTax(-1)" 
                            :disabled="!newProduct.special_tax_percent || newProduct.special_tax_percent <= 0"
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Giảm 1%"
                          >
                            <ChevronDown class="w-3 h-3" />
                          </button>
                        </div>
                      </div>

                      <span v-if="specialAmount > 0" class="text-xs text-sky-600 font-semibold whitespace-nowrap">
                        (+ {{ formatNumber(specialAmount) }}đ)
                      </span>
                    </div>
                  </div>

                  <!-- Thuế VAT (%) -->
                  <div class="flex flex-col gap-1 col-span-2">
                    <label class="font-semibold text-xs text-[#000000D9]">Thuế VAT (%)</label>
                    <div class="flex items-center gap-1.5">
                      <div class="inline-flex items-center border border-slate-300 rounded-lg bg-white shadow-xs h-8 overflow-hidden focus-within:ring-1 focus-within:ring-[#006bdb] focus-within:border-[#006bdb]">
                        <div class="relative flex items-center w-16">
                          <input 
                            type="number" 
                            v-model.number="newProduct.tax_percent" 
                            @input="onTaxPercentInput" 
                            placeholder="0"
                            min="0"
                            max="100"
                            class="w-full h-full text-center text-xs text-[#000000D9] outline-none bg-transparent font-medium pl-1.5 pr-4 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                          />
                          <button 
                            v-if="newProduct.tax_percent" 
                            type="button" 
                            @click="clearTaxPercent" 
                            class="absolute right-0.5 text-slate-300 hover:text-slate-600 p-0.5 cursor-pointer"
                            title="Xóa"
                          >
                            <X class="w-3 h-3" />
                          </button>
                        </div>

                        <!-- Nút Lên/Xuống xếp dọc -->
                        <div class="flex flex-col w-5 h-full border-l border-slate-200 divide-y divide-slate-200">
                          <button 
                            type="button" 
                            @click="adjustTaxPercent(1)" 
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Tăng 1%"
                          >
                            <ChevronUp class="w-3 h-3" />
                          </button>
                          <button 
                            type="button" 
                            @click="adjustTaxPercent(-1)" 
                            :disabled="!newProduct.tax_percent || newProduct.tax_percent <= 0"
                            class="flex-1 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-[#006bdb] active:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer border-none bg-transparent p-0"
                            title="Giảm 1%"
                          >
                            <ChevronDown class="w-3 h-3" />
                          </button>
                        </div>
                      </div>

                      <span v-if="vatAmount > 0" class="text-xs text-sky-600 font-semibold whitespace-nowrap">
                        (+ {{ formatNumber(vatAmount) }}đ)
                      </span>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Description with quick clear (x) -->
              <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-xs flex flex-col gap-1">
                <div class="flex items-center justify-between">
                  <label class="font-semibold text-xs text-[#000000D9]">Mô Tả Thêm</label>
                  <button 
                    v-if="newProduct.note" 
                    type="button" 
                    @click="newProduct.note = ''" 
                    class="text-xs text-slate-400 hover:text-slate-600 flex items-center gap-0.5"
                  >
                    <X class="w-3 h-3" />
                    <span>Xóa</span>
                  </button>
                </div>
                <textarea 
                  v-model="newProduct.note" 
                  placeholder="Nhập mô tả chi tiết sản phẩm, thành phần, quy cách phục vụ..." 
                  class="border border-slate-300 rounded-lg p-2.5 outline-none h-[64px] resize-none focus:ring-1 focus:ring-[#006bdb] focus:border-[#006bdb] transition-all text-xs text-[#000000D9] bg-white shadow-xs placeholder:text-[#A8B0BF]"
                ></textarea>
              </div>

            </div>
          </div>

          <!-- Modal Footer (Standard Buttons) -->
          <div class="bg-white border-t border-slate-200 p-3 px-5 flex justify-end gap-2.5 shrink-0">
            <button @click="saveProduct" class="btn-pms-primary h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer flex items-center gap-1.5">
              <Save class="w-3.5 h-3.5" />
              <span>Lưu Sản Phẩm</span>
            </button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- GROUP MODAL (Draggable & Theme Synchronized) -->
    <Transition name="hk-modal">
      <div v-if="showGroupModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
        <div 
          class="relative bg-white w-[420px] rounded-xl shadow-2xl flex flex-col overflow-hidden font-sans border border-slate-200"
          :style="{ transform: 'translate(' + groupModalPos.x + 'px, ' + groupModalPos.y + 'px)' }"
        >
          <!-- Header (Draggable, Title Case, Theme Synchronized) -->
          <div 
            class="px-5 py-3 flex justify-between items-center shrink-0 shadow-sm text-white cursor-move select-none rounded-t-xl" 
            @mousedown="startDragGroupModal"
            :style="{ background: topbarThemeBg, color: 'var(--pms-custom-theme-text, #ffffff)' }"
          >
            <h2 class="text-xs font-semibold tracking-wide text-white">Thêm Nhóm Sản Phẩm Mới</h2>
            <button 
              type="button"
              @click="showGroupModal = false" 
              class="text-white hover:bg-white/20 p-1 rounded-full transition-colors cursor-pointer border-none bg-transparent"
              title="Đóng (Esc)"
            >
              <X class="w-4 h-4" />
            </button>
          </div>
          <div class="p-5 flex flex-col gap-3.5 bg-slate-50">
            <div class="flex flex-col gap-1 bg-white p-4 rounded-lg border border-slate-200 shadow-xs">
              <label class="font-semibold text-xs text-[#000000D9]">Tên nhóm <span class="text-rose-500">*</span></label>
              <div class="relative flex items-center">
                <input 
                  v-model="newGroupName" 
                  type="text" 
                  placeholder="Nhập tên nhóm..." 
                  @keyup.enter="saveGroup" 
                  class="w-full h-8 border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg pl-2.5 pr-7 text-xs text-[#000000D9] outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308] transition-all shadow-xs" 
                  v-focus 
                />
                <button 
                  v-if="newGroupName" 
                  type="button" 
                  @click="newGroupName = ''" 
                  class="absolute right-2 text-slate-400 hover:text-slate-600"
                  title="Xóa tên nhóm"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
          <div class="bg-white border-t border-slate-200 p-3 px-5 flex justify-end gap-2.5 shrink-0">
            <button @click="saveGroup" class="btn-pms-primary h-8 px-4 rounded-lg text-xs font-semibold cursor-pointer flex items-center gap-1.5">
              <Save class="w-3.5 h-3.5" />
              <span>Lưu Nhóm</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Trash2, Printer, Download, Upload, PlusCircle, ChevronUp, ChevronDown, ImageOff, Inbox, X, Save, Power, Plus, Minus } from '@lucide/vue'
import http from '@/services/http'
import { fetchHousekeepingOutlets } from '@/services/housekeeping-outlet-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import SearchableSelect from './SearchableSelect.vue'

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => {
  return authStore.settings?.topbar_color || authStore.themeColor || 'var(--pms-custom-theme, #006bdb)'
})

const tabs = ref([])
const activeTab = ref('')
const housekeepingOutlets = ref([])
const showProductModal = ref(false)
const showGroupModal = ref(false)
const newGroupName = ref('')

const fileInput = ref(null)

// ─── Drag Handling for Modals ─────────────────────────────────────────────
const productModalPos = ref({ x: 0, y: 0 })
const groupModalPos = ref({ x: 0, y: 0 })

function createDragHandler(posRef) {
  let dragStart = { x: 0, y: 0 }
  let isDragging = false
  let rafId = null

  function onMouseMove(e) {
    if (!isDragging) return
    if (rafId) return
    rafId = requestAnimationFrame(() => {
      posRef.value.x = e.clientX - dragStart.x
      posRef.value.y = e.clientY - dragStart.y
      rafId = null
    })
  }

  function onMouseUp() {
    isDragging = false
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  }

  return function startDrag(e) {
    const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL', 'SVG', 'PATH']
    if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
    
    isDragging = true
    dragStart.x = e.clientX - posRef.value.x
    dragStart.y = e.clientY - posRef.value.y
    
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
  }
}

const startDragProductModal = createDragHandler(productModalPos)
const startDragGroupModal = createDragHandler(groupModalPos)

watch(showProductModal, (visible) => {
  if (!visible) productModalPos.value = { x: 0, y: 0 }
})
watch(showGroupModal, (visible) => {
  if (!visible) groupModalPos.value = { x: 0, y: 0 }
})

// ─── Export / Import ─────────────────────────────────────────────
const exportExcel = async () => {
  try {
    const res = await http.get('/products/export', { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'products.xlsx')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    uiStore.showToast('Lỗi khi xuất dữ liệu', 'error')
  }
}

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)

  uiStore.showToast('Đang nhập dữ liệu...', 'info')

  try {
    await http.post('/products/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    uiStore.showToast('Nhập dữ liệu thành công', 'success')
    fetchGroupsAndProducts()
    if (fileInput.value) fileInput.value.value = ''
    
  } catch (error) {
    if (fileInput.value) fileInput.value.value = ''
    uiStore.showToast('Lỗi khi nhập dữ liệu', 'error')
  }
}

const filterState = ref({
  search: '',
  status: '',
  priceMin: null,
  priceMax: null,
  trackStock: '',
  sortBy: 'name_asc'
})

const isLoading = ref(true)
const groups = ref([])
const units = ref([])
const inventories = ref([])

const groupSelectOptions = computed(() => {
  return groups.value.map(g => ({
    value: g.id,
    label: g.name
  }))
})

const unitSelectOptions = computed(() => {
  return units.value.map(u => ({
    value: u.code,
    label: u.name,
    sublabel: u.code !== u.name ? u.code : undefined
  }))
})

const inventorySelectOptions = computed(() => {
  return inventories.value.map(inv => ({
    value: inv.id,
    label: inv.name,
    sublabel: inv.code ? `Mã: ${inv.code}` : undefined
  }))
})

const fetchUnitsAndInventories = async () => {
  try {
    const [resUnits, resInv] = await Promise.all([
      http.get('/units-of-measure'),
      http.get('/inventories')
    ])
    units.value = resUnits.data.data || resUnits.data
    inventories.value = resInv.data.data || resInv.data
  } catch (error) {
    console.error('Error fetching dropdown data', error)
  }
}

const normalizeImageUrl = (url) => {
  if (!url) return null
  const clean = String(url).trim()
  if (clean.startsWith('/storage/')) return clean
  if (clean.startsWith('storage/')) return '/' + clean
  const match = clean.match(/\/storage\/.+$/i)
  if (match) return match[0]
  if (!clean.startsWith('http') && !clean.startsWith('/')) {
    return '/storage/' + clean
  }
  return clean
}

const fetchGroupsAndProducts = async () => {
  isLoading.value = true
  try {
    const [resOutlets, resCategories, resProducts] = await Promise.all([
      fetchHousekeepingOutlets(),
      http.get('/product-categories'),
      http.get('/products')
    ])

    housekeepingOutlets.value = (resOutlets.data || []).filter(outlet => outlet.is_active).map(o => ({
      ...o,
      image_url: normalizeImageUrl(o.image_url || o.image)
    }))
    tabs.value = housekeepingOutlets.value.map(outlet => outlet.code)
    if (!tabs.value.includes(activeTab.value)) activeTab.value = tabs.value[0] || ''
    const categories = Array.isArray(resCategories.data) ? resCategories.data : (resCategories.data?.data || [])
    const products = Array.isArray(resProducts.data) ? resProducts.data : (resProducts.data?.data || [])
    const resolveOutletName = (value) => {
      const normalized = String(value || '').toLowerCase()
      const outlet = housekeepingOutlets.value.find(item =>
        String(item.code).toLowerCase() === normalized ||
        String(item.name).toLowerCase() === normalized
      )
      return outlet?.code || value
    }
    
    // Map data
    const categoriesMap = categories.map(cat => ({
      id: cat.id,
      name: cat.name,
      expanded: true,
      isEditing: false,
      outlet: resolveOutletName(cat.outlet),
      products: products.filter(p => p.product_category_id === cat.id).map(p => {
        const hasStoredBasePrice = p.original_amount !== null && p.original_amount !== undefined
        let basePrice = Number(hasStoredBasePrice ? p.original_amount : p.price) || 0;
        let svcPercent = Number(p.service_charge_percent) || 0;
        let taxPercent = Number(p.tax_percent) || 0;
        let spcTaxPercent = Number(p.special_tax_percent) || 0;
        const svcAmount = Math.round(basePrice * svcPercent / 100)
        const specialTaxAmount = Math.round(basePrice * spcTaxPercent / 100)
        const taxAmount = Math.round(basePrice * taxPercent / 100)
        const finalPrice = hasStoredBasePrice ? Number(p.price) || 0 : basePrice + svcAmount + specialTaxAmount + taxAmount

        return {
          ...p,
          basePriceNum: basePrice,
          priceNum: finalPrice,
          checked: false,
          isActive: !!p.is_active,
          trackStock: !!p.track_stock,
          flexiblePrice: !!p.flexible_price,
          tab: resolveOutletName(cat.outlet),
          imageUrl: p.image ? normalizeImageUrl(p.image) : null
        }
      })
    }))
    
    groups.value = categoriesMap
  } catch (error) {
    console.error('Error fetching data:', error)
  } finally {
    isLoading.value = false
  }
}

const getProductImage = (product) => {
  if (product.imageUrl) return normalizeImageUrl(product.imageUrl)
  const outlet = housekeepingOutlets.value.find(o => String(o.code).toLowerCase() === String(product.tab || activeTab.value).toLowerCase())
  if (outlet?.image_url) return normalizeImageUrl(outlet.image_url)
  if (outlet?.image) return normalizeImageUrl(outlet.image)
  return null
}

const filteredGroups = computed(() => {
  return groups.value.map(group => {
    let products = group.products.filter(p => {
      // 1. Tab check
      if (p.tab && p.tab !== activeTab.value) return false
      
      // 2. Search check
      if (filterState.value.search && !p.name.toLowerCase().includes(filterState.value.search.toLowerCase())) return false
      
      // 3. Status check
      if (filterState.value.status === 'active' && !p.isActive) return false
      if (filterState.value.status === 'inactive' && p.isActive) return false

      // 4. Price range check
      if (filterState.value.priceMin !== null && filterState.value.priceMin !== '' && p.priceNum < filterState.value.priceMin) return false
      if (filterState.value.priceMax !== null && filterState.value.priceMax !== '' && p.priceNum > filterState.value.priceMax) return false

      // 5. Track stock check
      if (filterState.value.trackStock === 'tracked' && !p.trackStock) return false
      if (filterState.value.trackStock === 'untracked' && p.trackStock) return false

      return true
    })

    // Sort products
    products.sort((a, b) => {
      if (filterState.value.sortBy === 'name_asc') {
        return a.name.localeCompare(b.name)
      }
      if (filterState.value.sortBy === 'name_desc') {
        return b.name.localeCompare(a.name)
      }
      if (filterState.value.sortBy === 'price_asc') {
        return a.priceNum - b.priceNum
      }
      if (filterState.value.sortBy === 'price_desc') {
        return b.priceNum - a.priceNum
      }
      return 0
    })

    return {
      ...group,
      products
    }
  }).filter(g => g.products.length > 0 || g.outlet === activeTab.value)
})

const newProduct = ref({
  id: null,
  name: '',
  price: 0,
  original_amount: 0,
  currency: '',
  groupId: null,
  isActive: true,
  openKey: true,
  trackStock: false,
  flexiblePrice: false,
  inventory_id: '',
  service_charge_percent: 0,
  service_charge_amount: 0,
  tax_percent: 0,
  tax_amount: 0,
  special_tax_percent: 0,
  special_tax_amount: 0,
  note: '',
  imageFile: null,
  imageUrl: null,
  product_code: ''
})

const calculatePriceBreakdown = (baseAmount) => {
  const base = Math.round(Number(baseAmount) || 0)
  const svcPercent = Number(newProduct.value.service_charge_percent) || 0
  const spcPercent = Number(newProduct.value.special_tax_percent) || 0
  const taxPercent = Number(newProduct.value.tax_percent) || 0

  const serviceCharge = Math.round(base * svcPercent / 100)
  const specialTax = Math.round(base * spcPercent / 100)
  const tax = Math.round(base * taxPercent / 100)

  return {
    base,
    serviceCharge,
    specialTax,
    tax,
    finalPrice: base + serviceCharge + specialTax + tax
  }
}

const applyPriceBreakdown = (breakdown, keepFinalPrice = false) => {
  newProduct.value.original_amount = breakdown.base
  newProduct.value.service_charge_amount = breakdown.serviceCharge
  newProduct.value.special_tax_amount = breakdown.specialTax
  newProduct.value.tax_amount = breakdown.tax
  if (!keepFinalPrice) newProduct.value.price = breakdown.finalPrice
}

const recalculateFromBase = () => {
  applyPriceBreakdown(calculatePriceBreakdown(newProduct.value.original_amount))
}

const recalculateFromFinal = () => {
  const total = Number(newProduct.value.price) || 0
  const totalPercent = (Number(newProduct.value.service_charge_percent) || 0)
    + (Number(newProduct.value.special_tax_percent) || 0)
    + (Number(newProduct.value.tax_percent) || 0)
  const multiplier = 1 + totalPercent / 100
  const base = multiplier > 0 ? Math.round(total / multiplier) : 0

  newProduct.value.price = total
  applyPriceBreakdown(calculatePriceBreakdown(base), true)
}

const formatNumber = (val) => {
  if (val === null || val === undefined || val === '') return ''
  const num = Number(val)
  if (isNaN(num)) return ''
  return num.toLocaleString('en-US')
}

const displayBasePrice = computed({
  get() {
    return newProduct.value.original_amount ? formatNumber(newProduct.value.original_amount) : ''
  },
  set(val) {
    const num = String(val).replace(/,/g, '').replace(/\D/g, '')
    newProduct.value.original_amount = num ? parseInt(num, 10) : 0
    recalculateFromBase()
  }
})

const displayFinalPrice = computed({
  get() {
    return newProduct.value.price ? formatNumber(newProduct.value.price) : ''
  },
  set(val) {
    const total = Number(String(val).replace(/,/g, '').replace(/\D/g, '')) || 0
    newProduct.value.price = total
    recalculateFromFinal()
  }
})

const displayPriceMin = computed({
  get() {
    return filterState.value.priceMin !== null && filterState.value.priceMin !== ''
      ? formatNumber(filterState.value.priceMin)
      : ''
  },
  set(val) {
    const raw = String(val).replace(/,/g, '').replace(/\D/g, '')
    filterState.value.priceMin = raw ? parseInt(raw, 10) : null
  }
})

const displayPriceMax = computed({
  get() {
    return filterState.value.priceMax !== null && filterState.value.priceMax !== ''
      ? formatNumber(filterState.value.priceMax)
      : ''
  },
  set(val) {
    const raw = String(val).replace(/,/g, '').replace(/\D/g, '')
    filterState.value.priceMax = raw ? parseInt(raw, 10) : null
  }
})

const clearBasePrice = () => {
  newProduct.value.original_amount = 0
  recalculateFromBase()
}

const clearFinalPrice = () => {
  newProduct.value.price = 0
  recalculateFromFinal()
}

const onTaxPercentInput = () => {
  if (Number(newProduct.value.price) > 0) {
    recalculateFromFinal()
  } else if (Number(newProduct.value.original_amount) > 0) {
    recalculateFromBase()
  } else {
    recalculateFromFinal()
  }
}

const adjustServiceCharge = (delta) => {
  const current = Number(newProduct.value.service_charge_percent) || 0
  newProduct.value.service_charge_percent = Math.max(0, Math.min(100, Math.round((current + delta) * 100) / 100))
  onTaxPercentInput()
}

const adjustSpecialTax = (delta) => {
  const current = Number(newProduct.value.special_tax_percent) || 0
  newProduct.value.special_tax_percent = Math.max(0, Math.min(100, Math.round((current + delta) * 100) / 100))
  onTaxPercentInput()
}

const adjustTaxPercent = (delta) => {
  const current = Number(newProduct.value.tax_percent) || 0
  newProduct.value.tax_percent = Math.max(0, Math.min(100, Math.round((current + delta) * 100) / 100))
  onTaxPercentInput()
}

const clearServiceCharge = () => {
  newProduct.value.service_charge_percent = 0
  onTaxPercentInput()
}

const clearTaxPercent = () => {
  newProduct.value.tax_percent = 0
  onTaxPercentInput()
}

const clearSpecialTaxPercent = () => {
  newProduct.value.special_tax_percent = 0
  onTaxPercentInput()
}

const scAmount = computed(() => {
  return Number(newProduct.value.service_charge_amount) || 0
})
const vatAmount = computed(() => {
  return Number(newProduct.value.tax_amount) || 0
})
const specialAmount = computed(() => {
  return Number(newProduct.value.special_tax_amount) || 0
})

const fetchNextProductCode = async (categoryId, outlet) => {
  try {
    const res = await http.get('/products/next-code', {
      params: { product_category_id: categoryId, outlet: outlet }
    })
    if (res.data?.code) {
      newProduct.value.product_code = res.data.code
    }
  } catch (error) {
    console.error('Failed to get next code:', error)
  }
}

const onGroupChanged = async (groupId) => {
  if (!newProduct.value.id && groupId) {
    await fetchNextProductCode(groupId, activeTab.value)
  }
}

const activeOutletTaxDefaults = () => {
  const outlet = housekeepingOutlets.value.find(item => String(item.code) === String(activeTab.value))

  return {
    service_charge_percent: Number(outlet?.default_service_charge_percent) || 0,
    tax_percent: Number(outlet?.default_tax_percent) || 0,
    special_tax_percent: Number(outlet?.default_special_tax_percent) || 0
  }
}

const openCreateProduct = async (groupId) => {
  const taxDefaults = activeOutletTaxDefaults()
  const defaultGroupId = groupId || (groups.value.length > 0 ? groups.value[0].id : null)
  newProduct.value = {
    id: null,
    name: '',
    price: 0,
    original_amount: 0,
    currency: units.value.length > 0 ? units.value[0].code : '',
    groupId: defaultGroupId,
    isActive: true,
    openKey: true,
    trackStock: false,
    flexiblePrice: false,
    inventory_id: inventories.value.length > 0 ? inventories.value[0].id : '',
    service_charge_percent: taxDefaults.service_charge_percent,
    service_charge_amount: 0,
    tax_percent: taxDefaults.tax_percent,
    tax_amount: 0,
    special_tax_percent: taxDefaults.special_tax_percent,
    special_tax_amount: 0,
    note: '',
    imageFile: null,
    imageUrl: null,
    product_code: ''
  }
  showProductModal.value = true
  if (defaultGroupId) {
    await fetchNextProductCode(defaultGroupId, activeTab.value)
  }
}

const editProduct = (product) => {
  const hasStoredBasePrice = product.original_amount !== null && product.original_amount !== undefined
  const storedBasePrice = hasStoredBasePrice ? Number(product.original_amount) || 0 : Number(product.basePriceNum) || 0
  const storedFinalPrice = hasStoredBasePrice ? Number(product.priceNum) || 0 : Number(product.priceNum) || 0

  newProduct.value = {
    id: product.id,
    name: product.name,
    price: storedFinalPrice,
    original_amount: storedBasePrice,
    currency: product.currency || (units.value.length > 0 ? units.value[0].code : ''),
    groupId: product.product_category_id,
    isActive: product.isActive,
    openKey: Number(product.open_key) === 1,
    trackStock: product.trackStock,
    flexiblePrice: product.flexiblePrice,
    inventory_id: product.inventory_id || '',
    service_charge_percent: product.service_charge_percent || 0,
    service_charge_amount: product.service_charge_amount || 0,
    tax_percent: product.tax_percent || 0,
    tax_amount: product.tax_amount || 0,
    special_tax_percent: product.special_tax_percent || 0,
    special_tax_amount: product.special_tax_amount || 0,
    note: product.note || '',
    imageFile: null,
    imageUrl: product.imageUrl || null,
    product_code: product.product_code || ''
  }
  applyPriceBreakdown(calculatePriceBreakdown(storedBasePrice), true)
  showProductModal.value = true
}

const saveProduct = async () => {
  if (!String(newProduct.value.name || '').trim()) {
    uiStore.showToast('Vui lòng nhập Tên sản phẩm.', 'warning')
    return
  }
  if (!newProduct.value.groupId) {
    uiStore.showToast('Vui lòng chọn Nhóm sản phẩm.', 'warning')
    return
  }
  if (!newProduct.value.currency) {
    uiStore.showToast('Vui lòng chọn Đơn vị tính.', 'warning')
    return
  }
  
  const formData = new FormData()
  formData.append('product_category_id', newProduct.value.groupId)
  formData.append('name', newProduct.value.name)
  formData.append('price', newProduct.value.price)
  formData.append('original_amount', newProduct.value.original_amount)
  formData.append('currency', newProduct.value.currency || '')
  formData.append('is_active', newProduct.value.isActive ? 1 : 0)
  formData.append('open_key', newProduct.value.openKey ? 1 : 0)
  formData.append('track_stock', newProduct.value.trackStock ? 1 : 0)
  formData.append('flexible_price', newProduct.value.flexiblePrice ? 1 : 0)
  formData.append('inventory_id', newProduct.value.trackStock ? newProduct.value.inventory_id : '')
  formData.append('service_charge_percent', newProduct.value.service_charge_percent || 0)
  formData.append('service_charge_amount', newProduct.value.service_charge_amount || 0)
  formData.append('tax_percent', newProduct.value.tax_percent || 0)
  formData.append('tax_amount', newProduct.value.tax_amount || 0)
  formData.append('special_tax_percent', newProduct.value.special_tax_percent || 0)
  formData.append('special_tax_amount', newProduct.value.special_tax_amount || 0)
  formData.append('note', newProduct.value.note || '')

  if (newProduct.value.imageFile) {
    formData.append('image', newProduct.value.imageFile)
  }

  try {
    if (newProduct.value.id) {
      formData.append('_method', 'PUT')
      await http.post('/products/' + newProduct.value.id, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Cập nhật sản phẩm thành công', 'success')
    } else {
      await http.post('/products', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Đã thêm sản phẩm thành công', 'success')
    }
    showProductModal.value = false
    fetchGroupsAndProducts()
  } catch (error) {
    console.error('Error saving product', error)
    uiStore.showToast('Lỗi lưu sản phẩm', 'error')
  }
}

const createGroup = () => {
  newGroupName.value = ''
  showGroupModal.value = true
}

const saveGroup = async () => {
  if (!newGroupName.value.trim()) {
    uiStore.showToast('Vui lòng nhập tên nhóm', 'error')
    return
  }
  try {
    await http.post('/product-categories', {
      name: newGroupName.value.trim(),
      outlet: activeTab.value
    })
    showGroupModal.value = false
    uiStore.showToast('Đã tạo nhóm sản phẩm thành công!', 'success')
    fetchGroupsAndProducts()
  } catch (e) {
    console.error(e)
    uiStore.showToast('Lỗi khi tạo nhóm', 'error')
  }
}

const toggleExpanded = (id) => {
  const g = groups.value.find(g => g.id === id)
  if (g) g.expanded = !g.expanded
}

const toggleGroupCheck = (id, checked, filteredProducts) => {
  const g = groups.value.find(g => g.id === id)
  if (g) {
    g.checked = checked
    if (filteredProducts) {
      filteredProducts.forEach(p => p.checked = checked)
    } else {
      g.products.forEach(p => p.checked = checked)
    }
  }
}

const setEditing = (id) => {
  const g = groups.value.find(g => g.id === id)
  if (g) g.isEditing = true
}

const updateGroupName = async (id, newName) => {
  const g = groups.value.find(g => g.id === id)
  if (!g) return
  g.isEditing = false
  try {
    await http.put(`/product-categories/${g.id}`, {
      name: newName,
      outlet: g.outlet || activeTab.value
    })
    g.name = newName
  } catch (e) {
    console.error(e)
  }
}

const deleteGroup = async (id) => {
  const isConfirmed = await uiStore.confirm({
    title: 'Xóa Nhóm Sản Phẩm',
    message: 'Bạn có chắc chắn muốn xóa nhóm này? Các sản phẩm trong nhóm cũng sẽ bị ảnh hưởng.',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!isConfirmed) return
  try {
    await http.delete(`/product-categories/${id}`)
    fetchGroupsAndProducts()
    uiStore.showToast('Xóa nhóm thành công', 'success')
  } catch (e) {
    uiStore.showToast('Lỗi xóa nhóm', 'error')
  }
}

const deleteSelected = async () => {
  const idsToDelete = []
  groups.value.forEach(group => {
    group.products.filter(p => p.checked).forEach(p => idsToDelete.push(p.id))
  })
  
  if (idsToDelete.length === 0) return
  
  const isConfirmed = await uiStore.confirm({
    title: 'Xóa Sản Phẩm',
    message: `Bạn có chắc chắn muốn xóa ${idsToDelete.length} sản phẩm đã chọn?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  
  if (!isConfirmed) return

  try {
    for (const id of idsToDelete) {
      await http.delete(`/products/${id}`)
    }
    fetchGroupsAndProducts()
    uiStore.showToast('Xóa thành công', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast('Lỗi xóa sản phẩm', 'error')
  }
}

const toggleActiveSelected = async () => {
  const idsToToggle = []
  groups.value.forEach(group => {
    group.products.filter(p => p.checked).forEach(p => idsToToggle.push(p.id))
  })
  
  if (idsToToggle.length === 0) {
    uiStore.showToast('Vui lòng chọn sản phẩm để thao tác', 'warning')
    return
  }

  try {
    await http.post('/products/bulk-toggle-active', { ids: idsToToggle })
    fetchGroupsAndProducts()
    uiStore.showToast('Cập nhật trạng thái thành công', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast('Lỗi cập nhật trạng thái', 'error')
  }
}

const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    newProduct.value.imageFile = file
    newProduct.value.imageUrl = URL.createObjectURL(file)
  }
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape') {
    if (showProductModal.value) {
      showProductModal.value = false
    } else if (showGroupModal.value) {
      showGroupModal.value = false
    }
  }
}

const vFocus = {
  mounted: (el) => el.focus()
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
  fetchUnitsAndInventories()
  fetchGroupsAndProducts()
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<style scoped>
/* Card Hover Lift */
.hk-card-lift {
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.25s ease;
}
.hk-card-lift:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 8px 20px -4px rgba(151, 213, 255, 0.35);
  border-color: #006bdb;
}
.hk-card-lift:active {
  transform: translateY(-1px) scale(0.99);
}

/* Image Upload drag & drop dash hover animation */
.dashed-upload {
  border: 2px dashed #cbd5e1;
  transition: all 0.25s ease;
}
.dashed-upload:hover {
  border-color: #006bdb;
  background-color: #f0f8ff;
}

/* Pulse animation on "+" card button */
.animate-pulse-subtle {
  animation: pulseSubtle 2s infinite;
}
@keyframes pulseSubtle {
  0%, 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(151, 213, 255, 0.4); }
  50% { transform: scale(1.05); opacity: 0.95; box-shadow: 0 0 0 5px rgba(151, 213, 255, 0); }
}

/* hk-modal transitions */
.hk-modal-enter-active {
  animation: hkModalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-modal-leave-active {
  animation: hkModalOut 0.2s ease-in forwards;
}
@keyframes hkModalIn {
  from { opacity: 0; transform: scale(0.97) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes hkModalOut {
  from { opacity: 1; transform: scale(1) translateY(0); }
  to { opacity: 0; transform: scale(0.97) translateY(8px); }
}

/* hk-expand transition */
.hk-expand-enter-active, .hk-expand-leave-active {
  transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
  overflow: hidden;
  max-height: 800px;
}
.hk-expand-enter-from, .hk-expand-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
