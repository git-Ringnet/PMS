<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative overflow-hidden">
    
    <!-- Top Control Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-4 shrink-0 overflow-hidden">
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
              class="text-[13px] font-black tracking-wide uppercase pb-2 transition-colors relative" 
              :class="activeTab === tab ? 'text-[var(--hk-primary-dark)]' : 'text-slate-500 hover:text-slate-700'"
            >
              {{ tab }}
              <div v-if="activeTab === tab" class="absolute bottom-0 left-0 w-full h-0.5 bg-[var(--hk-primary-dark)] rounded-t-full"></div>
            </h2>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="file" ref="fileInput" @change="handleFileUpload" accept=".xlsx, .xls, .csv" class="hidden" />
          <button @click="$refs.fileInput.click()" class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Upload class="w-4 h-4 text-emerald-550" />
            Nhập Excel
          </button>
          <button @click="exportExcel" class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Download class="w-4 h-4 text-sky-550" />
            Xuất Excel
          </button>
        </div>
      </div>

      <!-- Toolbar Section -->
      <div class="px-5 py-4 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-3">
          <button @click="createGroup" class="btn-primary px-4 py-1.5 rounded-lg flex items-center gap-1.5 text-[12px] font-bold shadow-sm cursor-pointer h-[32px]">
            <PlusCircle class="w-4 h-4" stroke-width="2.5" />
            Tạo nhóm
          </button>

          <!-- Search product -->
          <div class="relative flex items-center">
            <input 
              v-model="filterState.search" 
              type="text" 
              placeholder="Tìm sản phẩm..."
              data-hk-search
              class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-705 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] outline-none transition-all shadow-sm w-44" 
            />
          </div>

          <!-- Status filter -->
          <div class="flex items-center gap-1.5">
            <span class="font-semibold text-slate-500">Trạng thái:</span>
            <select v-model="filterState.status" class="border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white cursor-pointer font-semibold text-slate-700 shadow-sm">
              <option value="all">Tất cả</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <!-- Price range -->
          <div class="flex items-center gap-1">
            <span>Giá từ:</span>
            <input type="number" placeholder="Min" v-model.number="filterState.priceMin" class="w-16 border border-slate-300 rounded p-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white text-slate-750" />
            <span>đến</span>
            <input type="number" placeholder="Max" v-model.number="filterState.priceMax" class="w-16 border border-slate-300 rounded p-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white text-slate-750" />
          </div>

          <!-- Inventory tracking -->
          <div class="flex items-center gap-1.5">
            <span class="font-semibold text-slate-500">Tồn kho:</span>
            <select v-model="filterState.trackStock" class="border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white cursor-pointer font-semibold text-slate-700 shadow-sm">
              <option value="all">Tất cả</option>
              <option value="tracked">Có theo dõi</option>
              <option value="untracked">Không theo dõi</option>
            </select>
          </div>

          <!-- Sort dropdown -->
          <div class="flex items-center gap-1.5">
            <span class="font-semibold text-slate-500">Sắp xếp:</span>
            <select v-model="filterState.sortBy" class="border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[var(--hk-primary)] bg-white cursor-pointer font-semibold text-slate-700 shadow-sm">
              <option value="name_asc">Tên A-Z</option>
              <option value="name_desc">Tên Z-A</option>
              <option value="price_asc">Giá tăng dần</option>
              <option value="price_desc">Giá giảm dần</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button @click="enableTooltip = !enableTooltip" :class="enableTooltip ? 'bg-sky-50 text-sky-600 border-sky-300' : 'bg-white text-slate-500 hover:bg-slate-50 border-slate-300'" class="border px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Eye v-if="enableTooltip" class="w-4 h-4" />
            <EyeOff v-else class="w-4 h-4" />
          
          </button>
          <button @click="toggleActiveSelected" class="bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-250 border border-slate-300 text-slate-700 px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Power class="w-4 h-4" />
            Bật / Tắt
          </button>
          <button @click="deleteSelected" class="bg-white hover:bg-rose-50 hover:text-rose-600 hover:border-rose-250 border border-slate-300 text-slate-700 px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Trash2 class="w-4 h-4" />
            Xóa
          </button>
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-3.5 py-1.5 rounded-lg transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm cursor-pointer h-[32px]">
            <Printer class="w-4 h-4 text-slate-550" />
            In
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content Area (Groups) -->
    <div class="flex-1 overflow-y-auto flex flex-col gap-4 pb-10 hk-scroll">
      
      <!-- Group Card -->
      <div v-for="group in filteredGroups" :key="group.id" class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col shrink-0">
        
        <!-- Group Header -->
        <div class="flex items-center gap-3 mb-5 border-b border-slate-100 pb-3">
          <input type="checkbox" :checked="group.checked" @change="toggleGroupCheck(group.id, $event.target.checked, group.products)" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
          <span class="text-[12px] font-black text-slate-500 uppercase tracking-wider">Nhóm sản phẩm:</span>
          
          <!-- Inline Edit Group Name -->
          <input 
            v-if="group.isEditing"
            :value="group.name"
            @blur="updateGroupName(group.id, $event.target.value)"
            @keyup.enter="updateGroupName(group.id, $event.target.value)"
            class="border border-sky-400 rounded-lg px-3 py-1.5 text-[14px] font-bold text-slate-800 outline-none w-[200px] shadow-sm focus:ring-2 focus:ring-sky-100 transition-all bg-white"
            v-focus
          />
          <span 
            v-else 
            @dblclick="setEditing(group.id)"
            class="text-[14px] font-black text-[var(--hk-primary-dark)] cursor-pointer hover:bg-slate-50 px-2 py-1 rounded-lg select-none transition-colors uppercase tracking-wider"
            title="Nhấp đúp để sửa"
          >
            {{ group.name }}
          </span>

          <button @click="deleteGroup(group.id)" class="text-rose-400 hover:text-rose-600 transition-colors ml-auto p-1.5 hover:bg-rose-50 rounded-lg cursor-pointer" title="Xóa nhóm">
            <Trash2 class="w-4 h-4" />
          </button>
          <button @click="toggleExpanded(group.id)" class="text-slate-400 hover:text-[var(--hk-primary-dark)] transition-colors p-1.5 hover:bg-slate-50 rounded-lg cursor-pointer">
            <ChevronUp v-if="group.expanded" class="w-5 h-5" />
            <ChevronDown v-else class="w-5 h-5" />
          </button>
        </div>

        <!-- Group Content (Products) -->
        <Transition name="hk-expand">
          <div v-show="group.expanded" class="flex flex-wrap gap-5 pl-2">
            
            <!-- Create Product Card -->
            <div 
              @click="openCreateProduct(group.id)"
              class="w-[130px] h-[155px] border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center gap-2 cursor-pointer hover:bg-sky-50 hover:border-sky-300 transition-all bg-slate-50/50 shrink-0 group"
            >
              <div class="w-10 h-10 bg-white rounded-full shadow-sm flex items-center justify-center text-sky-400 transition-all border border-slate-100 animate-pulse-subtle group-hover:brightness-95">
                <Plus class="w-5 h-5 text-[var(--hk-primary-dark)]" stroke-width="3" />
              </div>
              <span class="text-[12px] text-sky-700 font-bold">Tạo sản phẩm</span>
            </div>

            <!-- Product Cards -->
            <template v-if="isLoading">
              <div v-for="i in 3" :key="'skeleton-'+i" class="w-[130px] h-[155px] flex flex-col shrink-0 animate-pulse">
                <div class="w-[130px] h-[130px] border border-slate-200 bg-slate-200/50 rounded-xl flex items-center justify-center shadow-sm">
                  <div class="w-10 h-10 rounded-full bg-slate-300/60"></div>
                </div>
                <div class="h-3 bg-slate-200 rounded w-16 mx-auto mt-2.5"></div>
              </div>
            </template>
            <template v-else>
              <div v-for="product in group.products" :key="product.id" class="flex flex-col w-[130px] shrink-0 group relative" @dblclick="editProduct(product)" @mouseenter="showTooltip(product, group.name, $event)" @mouseleave="hideTooltip">
                
                <!-- Card Image Area -->
                <div :class="[
                  'w-[130px] h-[130px] border rounded-xl relative flex flex-col items-center justify-center overflow-hidden shadow-sm transition-all hk-card-lift cursor-pointer hover:border-sky-300',
                  product.isActive ? 'bg-white border-slate-200' : 'bg-slate-50 border-slate-300 grayscale opacity-75'
                ]">
                  <!-- Checkbox -->
                  <input 
                    type="checkbox" 
                    v-model="product.checked"
                    class="absolute top-2.5 right-2.5 w-4 h-4 rounded border-slate-350 text-sky-500 focus:ring-sky-500 cursor-pointer z-10"
                  />
                  
                  <!-- Image Placeholder -->
                  <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-6 group-hover:bg-sky-50 transition-colors overflow-hidden">
                    <img v-if="product.imageUrl" :src="product.imageUrl" class="w-full h-full object-cover" />
                    <ImageOff v-else class="w-6 h-6 text-slate-300 group-hover:text-[var(--hk-primary-dark)]" stroke-width="2" />
                  </div>

                  <!-- Price Bar (Full width gradient) -->
                  <div class="absolute bottom-0 left-0 right-0 text-slate-800 text-[12px] py-1.5 text-center font-bold" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
                    {{ product.price }} VNĐ
                  </div>
                </div>
                
                <!-- Product Name (Full text, wrapped) -->
                <div class="text-[12px] text-slate-707 font-bold text-center px-1 mt-2 break-words leading-tight flex flex-col gap-0.5">
                  <span>{{ product.name }}</span>
                  <span v-if="!product.isActive" class="text-[9px] bg-slate-100 text-slate-500 px-1 py-0.5 rounded border border-slate-200 self-center">Inactive</span>
                </div>
              </div>
            </template>

            <!-- "No Data" Placeholder if group is empty -->
            <div v-if="group.products.length === 0" class="flex flex-col items-center justify-center w-[150px] h-[155px] opacity-60">
              <Inbox class="w-10 h-10 text-slate-300 mb-2" stroke-width="1.5" />
              <span class="text-[12px] text-slate-400 font-medium">Chưa có sản phẩm</span>
            </div>

          </div>
        </Transition>
      </div>
    </div>

    <!-- PRODUCT MODAL -->
    <Transition name="hk-modal">
      <div v-if="showProductModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white w-[800px] rounded-xl shadow-2xl flex flex-col overflow-hidden font-sans transform transition-all border border-slate-200">
          
          <!-- Modal Header -->
          <div class="px-5 py-3 flex justify-between items-center shrink-0 shadow-sm text-slate-850" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h2 class="text-[15px] font-bold uppercase tracking-wider text-slate-800">Thêm Sản Phẩm Mới</h2>
            <button @click="showProductModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 grid grid-cols-12 gap-6 text-[13px] text-slate-707 max-h-[80vh] overflow-y-auto bg-slate-50 flex-1 hk-scroll">
            
            <!-- Left Column (Image & Status) -->
            <div class="col-span-4 flex flex-col gap-4">
              <!-- Image Upload -->
              <div class="flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Hình ảnh sản phẩm</label>
                <div class="dashed-upload rounded-xl bg-white flex flex-col items-center justify-center gap-2 h-[150px] cursor-pointer group shadow-sm relative overflow-hidden">
                  <input type="file" @change="handleImageUpload" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                  <img v-if="newProduct.imageUrl" :src="newProduct.imageUrl" class="absolute inset-0 w-full h-full object-cover" />
                  <div v-else class="flex flex-col items-center w-full">
                    <div class="w-10 h-10 bg-slate-50 rounded-full shadow-sm flex items-center justify-center text-sky-500 group-hover:scale-110 transition-transform border border-slate-100 group-hover:bg-white mb-2">
                      <Plus class="w-5 h-5 text-[var(--hk-primary-dark)]" stroke-width="2.5" />
                    </div>
                    <div class="text-center px-2">
                      <p class="text-[var(--hk-primary-dark)] font-bold text-[12px]">Tải ảnh sản phẩm lên</p>
                      <p class="text-[11px] text-slate-405 mt-0.5">Nhấp chuột hoặc kéo thả</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Status Toggles -->
              <div class="flex flex-col gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input type="checkbox" v-model="newProduct.isActive" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  <span class="font-bold text-slate-700 text-[12px]">Đang sử dụng (Active)</span>
                </label>
                
                <label class="flex items-center gap-2.5 cursor-pointer">
                  <input type="checkbox" v-model="newProduct.flexiblePrice" class="w-4 h-4 rounded border-slate-300 text-sky-500 focus:ring-sky-500 cursor-pointer" />
                  <span class="font-bold text-slate-700 text-[12px]">Giá bán linh động</span>
                </label>
              </div>
            </div>

            <!-- Right Column (Details & Pricing) -->
            <div class="col-span-8 flex flex-col gap-5">
              
              <!-- Basic Info Section -->
              <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Mã sản phẩm</label>
                  <input type="text" :value="newProduct.product_code || 'SP - AUTO'" disabled class="border border-slate-200 rounded-lg bg-slate-100 px-3 py-2 text-slate-500 cursor-not-allowed font-mono text-[12px] outline-none" />
                </div>
                
                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Tên sản phẩm <span class="text-rose-500">*</span></label>
                  <input type="text" v-model="newProduct.name" placeholder="Nhập tên sản phẩm..." class="border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all font-semibold text-[13px] bg-white shadow-sm" />
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Nhóm sản phẩm</label>
                  <div class="relative">
                    <select v-model="newProduct.groupId" class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all text-[13px] shadow-sm font-medium">
                      <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </select>
                    <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                  </div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Đơn vị tính</label>
                  <div class="relative">
                    <select v-model="newProduct.currency" class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all text-[13px] shadow-sm font-medium">
                      <option v-for="unit in units" :key="unit.id" :value="unit.code">{{ unit.name }}</option>
                    </select>
                    <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                  </div>
                </div>
                
                <div class="flex flex-col gap-1.5" :class="{'col-span-2': !newProduct.trackStock}">
                  <label class="font-bold text-slate-700 text-[12px]">Quản lý tồn kho</label>
                  <div class="relative">
                    <select v-model="newProduct.trackStock" class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all text-[13px] shadow-sm font-medium">
                      <option :value="false">Không theo dõi</option>
                      <option :value="true">Có theo dõi (Trừ kho tự động)</option>
                    </select>
                    <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                  </div>
                </div>

                <div v-if="newProduct.trackStock" class="flex flex-col gap-1.5">
                  <label class="font-bold text-slate-700 text-[12px]">Chọn kho</label>
                  <div class="relative">
                    <select v-model="newProduct.inventory_id" class="w-full border border-slate-300 rounded-lg bg-white px-3 py-2 outline-none appearance-none cursor-pointer focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all text-[13px] shadow-sm font-medium">
                      <option v-for="inv in inventories" :key="inv.id" :value="inv.id">{{ inv.name }}</option>
                    </select>
                    <ChevronDown class="w-4 h-4 absolute right-3 top-2.5 text-slate-400 pointer-events-none" />
                  </div>
                </div>
              </div>

              <!-- Pricing Section (highlighted box) -->
              <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-3">
                <h3 class="font-bold text-slate-800 border-b border-slate-100 pb-2 mb-1 text-[13px] uppercase tracking-wide">Thiết lập Giá & Thuế</h3>
                <div class="grid grid-cols-2 gap-x-5 gap-y-4">
                  
                  <div class="flex flex-col gap-1.5">
                    <label class="font-bold text-slate-700 text-[12px]">Giá gốc chưa thuế (VNĐ)</label>
                    <input type="text" v-model="displayBasePrice" placeholder="0" class="w-full border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] font-medium text-[13px] shadow-sm" />
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="font-bold text-sky-700 text-[12px]">Giá bán cuối cùng (VNĐ)</label>
                    <input type="text" :value="formattedFinalPrice" disabled class="w-full border border-sky-300 rounded-lg bg-sky-50 px-3 py-2 outline-none font-bold text-sky-700 text-[14px] shadow-sm cursor-not-allowed" />
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="font-bold text-slate-700 flex justify-between text-[12px]">
                      <span>Phí phục vụ (%)</span>
                      <span v-if="scAmount > 0" class="text-sky-600 font-semibold">(+ {{ new Intl.NumberFormat('vi-VN').format(scAmount) }}đ)</span>
                    </label>
                    <input type="number" v-model="newProduct.service_charge_percent" class="w-full border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-[13px] shadow-sm bg-white" />
                  </div>

                  <div class="flex flex-col gap-1.5">
                    <label class="font-bold text-slate-700 flex justify-between text-[12px]">
                      <span>Thuế VAT (%)</span>
                      <span v-if="vatAmount > 0" class="text-sky-600 font-semibold">(+ {{ new Intl.NumberFormat('vi-VN').format(vatAmount) }}đ)</span>
                    </label>
                    <input type="number" v-model="newProduct.tax_percent" class="w-full border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-[13px] shadow-sm bg-white" />
                  </div>

                  <div class="flex flex-col gap-1.5 col-span-2">
                    <label class="font-bold text-slate-700 flex justify-between text-[12px]">
                      <span>Thuế đặc biệt (%)</span>
                      <span v-if="specialAmount > 0" class="text-sky-600 font-semibold">(+ {{ new Intl.NumberFormat('vi-VN').format(specialAmount) }}đ)</span>
                    </label>
                    <input type="number" v-model="newProduct.special_tax_percent" class="w-full border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-[13px] shadow-sm bg-white" />
                  </div>

                </div>
              </div>

              <!-- Description -->
              <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1.5">
                <label class="font-bold text-slate-700 text-[12px]">Mô tả thêm</label>
                <textarea v-model="newProduct.note" placeholder="Nhập ghi chú hoặc mô tả về sản phẩm..." class="border border-slate-300 rounded-lg px-3 py-2 outline-none h-[60px] resize-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all text-[13px] bg-white shadow-sm"></textarea>
              </div>

            </div>
          </div>

          <!-- Modal Footer -->
          <div class="bg-white border-t border-slate-200 p-4 px-5 flex justify-end gap-3 shrink-0">
            <button @click="showProductModal = false" class="px-5 py-2 rounded-lg font-bold text-slate-600 bg-slate-100 border border-transparent hover:bg-slate-200 transition-colors shadow-sm text-[13px] cursor-pointer">
              Hủy bỏ
            </button>
            <button @click="saveProduct" class="btn-primary px-5 py-2 rounded-lg font-bold transition-colors shadow-sm flex items-center gap-2 text-[13px] cursor-pointer">
              <Save class="w-4 h-4" stroke-width="2.5" />
              Lưu Sản Phẩm
            </button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- GROUP MODAL -->
    <Transition name="hk-modal">
      <div v-if="showGroupModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white w-[400px] rounded-xl shadow-2xl flex flex-col overflow-hidden font-sans transform transition-all border border-slate-200">
          <div class="px-5 py-3 flex justify-between items-center shrink-0 shadow-sm text-slate-850" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h2 class="text-[15px] font-bold uppercase tracking-wider text-slate-800">Thêm Nhóm Sản Phẩm Mới</h2>
            <button @click="showGroupModal = false" class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
              <X class="w-4 h-4" />
            </button>
          </div>
          <div class="p-5 flex flex-col gap-4 bg-slate-50">
            <div class="flex flex-col gap-1.5 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
              <label class="font-bold text-slate-700 text-[12px]">Tên nhóm <span class="text-rose-500">*</span></label>
              <input v-model="newGroupName" type="text" placeholder="Nhập tên nhóm..." @keyup.enter="saveGroup" class="border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all font-semibold text-[13px] bg-white shadow-sm" v-focus />
            </div>
          </div>
          <div class="bg-white border-t border-slate-200 p-4 px-5 flex justify-end gap-3 shrink-0">
            <button @click="showGroupModal = false" class="px-4 py-2 rounded-lg font-bold text-slate-600 bg-slate-100 border border-transparent hover:bg-slate-200 transition-colors shadow-sm text-[13px] cursor-pointer">
              Hủy bỏ
            </button>
            <button @click="saveGroup" class="btn-primary px-4 py-2 rounded-lg font-bold transition-colors shadow-sm flex items-center gap-2 text-[13px] cursor-pointer">
              <Save class="w-4 h-4" stroke-width="2.5" />
              Lưu Nhóm
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Teleported Tooltip -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="hoveredProduct" :style="{ left: tooltipX + 'px', top: tooltipY + 'px' }" class="fixed -translate-x-1/2 -translate-y-full w-fit min-w-[160px] bg-slate-800 text-white text-[11px] p-2.5 rounded-lg z-[9999] pointer-events-none shadow-xl border border-slate-700">
          <div class="flex flex-col gap-1.5 whitespace-nowrap">
            <div class="font-bold border-b border-slate-600 pb-1 mb-1 text-[12px] text-sky-300">{{ hoveredProduct.name }}</div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-400">Mã SP:</span>
              <span class="font-bold text-slate-200">{{ hoveredProduct.product_code || 'Chưa có' }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-400">Giá gốc:</span>
              <span class="font-bold">{{ hoveredProduct.priceNum ? new Intl.NumberFormat('vi-VN').format(hoveredProduct.priceNum) : '0' }}đ</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-400">Nhóm:</span>
              <span>{{ hoveredGroupName }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-400">Tồn kho:</span>
              <span :class="hoveredProduct.trackStock ? 'text-green-400 font-bold' : ''">{{ hoveredProduct.trackStock ? 'Có theo dõi' : 'Không' }}</span>
            </div>
            <div class="flex flex-col gap-0.5 mt-1 border-t border-slate-600 pt-1" v-if="hoveredProduct.note">
              <span class="text-slate-400">Mô tả:</span>
              <span class="whitespace-normal leading-relaxed text-slate-200" style="max-width: 250px;">{{ hoveredProduct.note }}</span>
            </div>
          </div>
          <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 border-l-4 border-r-4 border-t-4 border-transparent border-t-slate-800"></div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Trash2, Printer, FileSpreadsheet, Download, Upload, PlusCircle, ChevronUp, ChevronDown, ImageOff, Inbox, X, Check, Plus, Eye, EyeOff, Save, Power } from '@lucide/vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const tabs = ['Minibar', 'Giặt ủi', 'Hàng đền bù', 'Amenity']
const activeTab = ref('Minibar')
const showProductModal = ref(false)
const showGroupModal = ref(false)
const newGroupName = ref('')

const tooltipX = ref(0)
const tooltipY = ref(0)
const hoveredProduct = ref(null)
const hoveredGroupName = ref('')
const enableTooltip = ref(true)

const fileInput = ref(null)

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

const showTooltip = (product, groupName, event) => {
  if (!enableTooltip.value) return
  hoveredProduct.value = product
  hoveredGroupName.value = groupName
  const rect = event.currentTarget.getBoundingClientRect()
  tooltipX.value = rect.left + rect.width / 2
  tooltipY.value = rect.top - 8 // spacing above the card
}

const hideTooltip = () => {
  hoveredProduct.value = null
}

const filterState = ref({
  search: '',
  status: 'all',
  priceMin: null,
  priceMax: null,
  trackStock: 'all',
  sortBy: 'name_asc'
})

const isLoading = ref(false)
const groups = ref([])
const units = ref([])
const inventories = ref([])

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

const fetchGroupsAndProducts = async () => {
  isLoading.value = true
  try {
    const resCategories = await http.get('/product-categories')
    const resProducts = await http.get('/products')
    
    // Map data
    const categoriesMap = resCategories.data.map(cat => ({
      id: cat.id,
      name: cat.name,
      expanded: true,
      isEditing: false,
      outlet: cat.outlet,
      products: resProducts.data.filter(p => p.product_category_id === cat.id).map(p => {
        let basePrice = Number(p.price) || 0;
        let svcPercent = Number(p.service_charge_percent) || 0;
        let taxPercent = Number(p.tax_percent) || 0;
        let spcTaxPercent = Number(p.special_tax_percent) || 0;
        let finalPrice = basePrice + (basePrice * svcPercent / 100) + (basePrice * taxPercent / 100) + (basePrice * spcTaxPercent / 100);

        return {
          ...p,
          priceNum: basePrice,
          price: new Intl.NumberFormat('vi-VN').format(finalPrice),
        checked: false,
        isActive: !!p.is_active,
        trackStock: !!p.track_stock,
        flexiblePrice: !!p.flexible_price,
        tab: cat.outlet || 'Minibar',
        imageUrl: p.image ? `http://localhost:8000/storage/${p.image}` : null
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

onMounted(() => {
  fetchUnitsAndInventories()
  fetchGroupsAndProducts()
})

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
  name: '',
  price: 0,
  currency: '',
  groupId: null,
  isActive: true,
  trackStock: false,
  flexiblePrice: false,
  inventory_id: '',
  service_charge_percent: 0,
  tax_percent: 0,
  special_tax_percent: 0,
  note: '',
  imageFile: null
})

const finalPrice = computed(() => {
    let base = Number(newProduct.value.price) || 0
    let sc = (base * (newProduct.value.service_charge_percent || 0)) / 100
    let vat = (base * (newProduct.value.tax_percent || 0)) / 100
    let special = (base * (newProduct.value.special_tax_percent || 0)) / 100
    return base + sc + vat + special
})

const formattedFinalPrice = computed(() => {
    return new Intl.NumberFormat('vi-VN').format(finalPrice.value)
})

const displayBasePrice = computed({
  get() {
    return newProduct.value.price ? new Intl.NumberFormat('vi-VN').format(newProduct.value.price) : ''
  },
  set(val) {
    const num = val.replace(/\D/g, '')
    newProduct.value.price = num ? parseInt(num, 10) : 0
  }
})

const scAmount = computed(() => {
    let base = Number(newProduct.value.price) || 0
    return (base * (newProduct.value.service_charge_percent || 0)) / 100
})
const vatAmount = computed(() => {
    let base = Number(newProduct.value.price) || 0
    return (base * (newProduct.value.tax_percent || 0)) / 100
})
const specialAmount = computed(() => {
    let base = Number(newProduct.value.price) || 0
    return (base * (newProduct.value.special_tax_percent || 0)) / 100
})

const openCreateProduct = (groupId) => {
  newProduct.value = {
    id: null,
    name: '',
    price: 0,
    currency: units.value.length > 0 ? units.value[0].code : '',
    groupId: groupId,
    isActive: true,
    trackStock: false,
    flexiblePrice: false,
    inventory_id: inventories.value.length > 0 ? inventories.value[0].id : '',
    service_charge_percent: 0,
    tax_percent: 0,
    special_tax_percent: 0,
    note: '',
    imageFile: null,
    imageUrl: null,
    product_code: ''
  }
  showProductModal.value = true
}

const editProduct = (product) => {
  newProduct.value = {
    id: product.id,
    name: product.name,
    price: product.priceNum || 0,
    currency: product.currency || (units.value.length > 0 ? units.value[0].code : ''),
    groupId: product.product_category_id,
    isActive: product.isActive,
    trackStock: product.trackStock,
    flexiblePrice: product.flexiblePrice,
    inventory_id: product.inventory_id || '',
    service_charge_percent: product.service_charge_percent || 0,
    tax_percent: product.tax_percent || 0,
    special_tax_percent: product.special_tax_percent || 0,
    note: product.note || '',
    imageFile: null,
    imageUrl: product.imageUrl || null,
    product_code: product.product_code || ''
  }
  showProductModal.value = true
}

const saveProduct = async () => {
  if (!newProduct.value.name || !newProduct.value.groupId) return
  
  const formData = new FormData()
  formData.append('product_category_id', newProduct.value.groupId)
  formData.append('name', newProduct.value.name)
  formData.append('price', newProduct.value.price)
  formData.append('currency', newProduct.value.currency || '')
  formData.append('is_active', newProduct.value.isActive ? 1 : 0)
  formData.append('track_stock', newProduct.value.trackStock ? 1 : 0)
  formData.append('flexible_price', newProduct.value.flexiblePrice ? 1 : 0)
  formData.append('inventory_id', newProduct.value.trackStock ? newProduct.value.inventory_id : '')
  formData.append('service_charge_percent', newProduct.value.service_charge_percent || 0)
  formData.append('tax_percent', newProduct.value.tax_percent || 0)
  formData.append('special_tax_percent', newProduct.value.special_tax_percent || 0)
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

const vFocus = {
  mounted: (el) => el.focus()
}
</script>

<style scoped>
/* Card Hover Lift */
.hk-card-lift {
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.25s ease;
}
.hk-card-lift:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 10px 25px -5px rgba(151, 213, 255, 0.35);
  border-color: var(--hk-primary);
}
.hk-card-lift:active {
  transform: translateY(-2px) scale(0.98);
}

/* Image Upload drag & drop dash hover animation */
.dashed-upload {
  border: 2px dashed #cbd5e1;
  transition: all 0.25s ease;
}
.dashed-upload:hover {
  border-color: var(--hk-primary-dark);
  background-color: var(--hk-primary-bg);
}

/* Pulse animation on "+" card button */
.animate-pulse-subtle {
  animation: pulseSubtle 2s infinite;
}
@keyframes pulseSubtle {
  0%, 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(151, 213, 255, 0.4); }
  50% { transform: scale(1.06); opacity: 0.95; box-shadow: 0 0 0 6px rgba(151, 213, 255, 0); }
}

/* Primary actions styling */
.btn-primary {
  background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5));
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
}
.btn-primary:hover {
  transform: translateY(-1px) scale(1.02);
  box-shadow: 0 4px 12px rgba(151, 213, 255, 0.4);
  filter: brightness(1.03);
}

/* hk-modal transitions */
.hk-modal-enter-active {
  animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
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
  max-height: 500px;
}
.hk-expand-enter-from, .hk-expand-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
