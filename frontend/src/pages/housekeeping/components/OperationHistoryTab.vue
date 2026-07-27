<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans">
    
    <!-- Quick Filter Chips -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mr-1">Lọc nhanh:</span>
      <button 
        v-for="chip in [
          { label: 'Tất cả', value: 'Tất cả', icon: '📋' },
          { label: 'Hôm nay', value: 'Hôm nay', icon: '📅' },
          { label: 'Tuần này', value: 'Tuần này', icon: '📆' },
          { label: 'Lỗi phát sinh', value: 'Lỗi', icon: '⚠️' },
          { label: 'CRUD (Thao tác dữ liệu)', value: 'CRUD', icon: '⚙️' }
        ]" 
        :key="chip.value"
        @click="quickFilter = chip.value"
        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 border cursor-pointer"
        :class="quickFilter === chip.value 
          ? 'bg-[var(--hk-primary-light)] text-slate-800 border-[var(--hk-primary)] shadow-sm scale-[1.02] font-bold' 
          : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
      >
        <span>{{ chip.icon }}</span>
        <span>{{ chip.label }}</span>
      </button>
    </div>

    <!-- Top Filters Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-5 shrink-0 relative z-20">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <h2 class="text-base font-bold text-slate-800">Bộ Lọc Tìm Kiếm</h2>
          <button 
            @click="isFilterExpanded = !isFilterExpanded"
            class="p-1 hover:bg-slate-100 rounded-full transition-colors border-none bg-transparent cursor-pointer text-slate-500"
            title="Ẩn/Hiện bộ lọc"
          >
            <ChevronDown class="w-4 h-4 transition-transform duration-250" :class="{'rotate-180': isFilterExpanded}" />
          </button>
        </div>
        <div class="flex items-center gap-3">
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-750 px-4 py-2.5 rounded-lg transition-all text-[13px] font-semibold flex items-center gap-2 shadow-sm cursor-pointer">
            <Download class="w-4 h-4 text-slate-500" />
            Xuất Excel
          </button>
          <button 
            @click="executeSearch"
            class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 px-6 py-2.5 rounded-lg transition-all text-[13px] font-bold shadow-sm flex items-center gap-2 cursor-pointer border-none"
          >
            <Search v-if="!isSearching" class="w-4 h-4" stroke-width="2.5" />
            <svg v-else class="animate-spin h-4 w-4 text-slate-800" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            {{ isSearching ? 'Đang tìm...' : 'Tìm kiếm' }}
          </button>
        </div>
      </div>

      <Transition name="hk-expand">
        <div v-show="isFilterExpanded" class="pt-4 border-t border-slate-100 mt-4">
          <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            
            <!-- Date Range -->
            <div class="col-span-1 flex flex-col gap-1.5 relative" ref="datePickerWrapper">
              <label class="text-[12px] font-semibold text-slate-500">Thời gian</label>
              <div class="relative flex items-center cursor-pointer" @click="openDatePicker">
                <input 
                  type="text" 
                  readonly
                  :value="`${formatDate(fromDate)} ~ ${formatDate(toDate)}`"
                  class="w-full border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 cursor-pointer transition-all font-semibold"
                />
                <Calendar class="w-4 h-4 text-sky-500 absolute right-3 pointer-events-none" />
              </div>

              <!-- Date Picker Popover -->
              <div 
                v-if="showDatePicker"
                class="absolute top-[calc(100%+4px)] left-0 w-[380px] bg-white border border-slate-200 rounded-xl shadow-xl z-30 p-5"
              >
                <div class="flex flex-col gap-4">
                  <div>
                    <label class="text-[12px] font-bold text-slate-800 mb-1.5 block">Phạm vi ngày</label>
                    
                    <div class="relative" ref="dropdownWrapper">
                      <div 
                        @click="showDropdown = !showDropdown"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 cursor-pointer flex justify-between items-center hover:border-sky-400 transition-colors"
                      >
                        <span>{{ getTempSelectedLabel() }}</span>
                        <ChevronDown class="w-4 h-4 text-slate-500" />
                      </div>
                      
                      <div 
                        v-if="showDropdown"
                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-40 max-h-48 overflow-y-auto"
                      >
                        <ul class="py-1">
                          <li 
                            v-for="opt in dateRangeOptions" 
                            :key="opt.value"
                            @click.stop="selectDateRange(opt.value)"
                            class="px-4 py-2 text-[13px] text-slate-700 hover:bg-sky-50 hover:text-sky-600 cursor-pointer transition-colors"
                            :class="{'bg-sky-50 text-sky-600 font-medium': tempDateRangeType === opt.value}"
                          >
                            {{ opt.label }}
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <div class="relative flex-1 min-w-0">
                      <input 
                        type="date" 
                        v-model="tempFromDate"
                        class="w-full border border-slate-300 rounded-lg px-2 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all min-w-0"
                        @change="tempDateRangeType = 'custom'"
                      />
                    </div>
                    <span class="text-slate-400 font-medium shrink-0">~</span>
                    <div class="relative flex-1 min-w-0">
                      <input 
                        type="date" 
                        v-model="tempToDate"
                        class="w-full border border-slate-300 rounded-lg px-2 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all min-w-0"
                        @change="tempDateRangeType = 'custom'"
                      />
                    </div>
                  </div>

                  <div class="border-t border-slate-100 pt-4 mt-2 flex justify-end">
                    <button 
                      @click="applyDateRange"
                      class="bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 px-5 py-2 rounded-lg flex items-center gap-2 transition-colors text-[13px] font-semibold shadow-sm border-none cursor-pointer"
                    >
                      Áp dụng
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mã đăng ký -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Mã đăng ký</label>
              <input 
                type="text" 
                data-hk-search
                v-model="searchRegCode"
                placeholder="Nhập mã đăng ký..."
                class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium"
              />
            </div>

            <!-- Mã phòng -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Mã phòng</label>
              <input 
                type="text" 
                v-model="searchRoomCode"
                placeholder="Nhập mã phòng..."
                class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium"
              />
            </div>

            <!-- Người dùng -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Người dùng</label>
              <input 
                type="text" 
                v-model="searchUser"
                placeholder="Tên user..."
                class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium"
              />
            </div>

            <!-- Hành động -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Hành động</label>
              <div class="relative">
                <select v-model="selectedAction" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all appearance-none bg-white cursor-pointer font-medium">
                  <option value="">Tất cả hành động</option>
                  <option value="Tạo mới">Tạo mới</option>
                  <option value="Cập nhật">Cập nhật</option>
                  <option value="Xóa">Xóa</option>
                  <option value="Print">Print</option>
                  <option value="Lỗi">Lỗi</option>
                </select>
                <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" />
              </div>
            </div>

            <!-- Màn hình -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Màn hình</label>
              <input 
                type="text" 
                v-model="searchScreen"
                placeholder="Tên màn hình..."
                class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium"
              />
            </div>

            <!-- Trình duyệt -->
            <div class="col-span-1 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Trình duyệt</label>
              <div class="relative">
                <select v-model="selectedBrowser" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all appearance-none bg-white cursor-pointer font-medium">
                  <option value="">Tất cả trình duyệt</option>
                  <option value="Chrome">Chrome</option>
                  <option value="Firefox">Firefox</option>
                  <option value="Edge">Edge</option>
                  <option value="Safari">Safari</option>
                </select>
                <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" />
              </div>
            </div>

            <!-- Tìm kiếm Mô tả -->
            <div class="col-span-2 flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold text-slate-500">Mô tả chi tiết</label>
              <input 
                type="text" 
                v-model="searchQuery"
                placeholder="Tìm nội dung chi tiết thao tác..."
                class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-medium"
              />
            </div>

            <!-- Reset Button inside panel -->
            <div class="col-span-1 flex items-end justify-start">
              <button 
                @click="resetAllFilters"
                class="px-4 py-2 border border-rose-350 text-rose-500 hover:bg-rose-50 rounded-lg text-xs font-bold transition-all duration-200 cursor-pointer bg-white"
              >
                Reset Bộ Lọc
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </div>

    <!-- Table Container -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max">
          <thead class="bg-slate-50 text-slate-650 text-[12px] font-bold border-b border-slate-200 sticky top-0 uppercase tracking-wider">
            <tr>
              <th class="py-3 px-4 border-r border-slate-200 w-16 text-center">ID</th>
              <th class="py-3 px-4 border-r border-slate-200">Thời gian</th>
              
              <!-- Column Popovers with transitions -->
              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('browser')">
                  <span>Trình duyệt</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'browser'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[200px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <select v-model="selectedBrowser" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all cursor-pointer mb-2 font-medium">
                      <option value="">Tất cả trình duyệt</option>
                      <option value="Chrome">Chrome</option>
                      <option value="Firefox">Firefox</option>
                      <option value="Edge">Edge</option>
                      <option value="Safari">Safari</option>
                    </select>
                    <button @click="activeSearchColumn = null" class="w-full bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-1.5 rounded-lg text-[12px] font-bold text-center transition-colors border-none cursor-pointer">
                      Áp dụng
                    </button>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('screen')">
                  <span>Màn hình</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'screen'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[220px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <input 
                      type="text" 
                      v-model="searchScreen"
                      placeholder="Tìm màn hình..." 
                      class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] mb-2 font-medium"
                    />
                    <button @click="activeSearchColumn = null" class="w-full bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-1.5 rounded-lg text-[12px] font-bold text-center transition-colors border-none cursor-pointer">
                      Áp dụng
                    </button>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('user')">
                  <span>Người dùng</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'user'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[220px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <input 
                      type="text" 
                      v-model="searchUser"
                      placeholder="Tìm user..." 
                      class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] mb-2 font-medium"
                    />
                    <button @click="activeSearchColumn = null" class="w-full bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-1.5 rounded-lg text-[12px] font-bold text-center transition-colors border-none cursor-pointer">
                      Áp dụng
                    </button>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4 border-r border-slate-200">Ngày</th>

              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('action')">
                  <span>Hành động</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'action'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[200px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <select v-model="selectedAction" class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all cursor-pointer mb-2 font-medium">
                      <option value="">Tất cả hành động</option>
                      <option value="Tạo mới">Tạo mới</option>
                      <option value="Cập nhật">Cập nhật</option>
                      <option value="Xóa">Xóa</option>
                      <option value="Print">Print</option>
                      <option value="Lỗi">Lỗi</option>
                    </select>
                    <button @click="activeSearchColumn = null" class="w-full bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-1.5 rounded-lg text-[12px] font-bold text-center transition-colors border-none cursor-pointer">
                      Áp dụng
                    </button>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('regCode')">
                  <span>Mã đăng ký</span>
                  <Search class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'regCode'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[280px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4 flex flex-col gap-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <input 
                      type="text" 
                      v-model="searchRegCode"
                      placeholder="Nhập mã đăng ký..." 
                      class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] w-full text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold"
                    />
                    <div class="flex items-center gap-2">
                      <button @click="activeSearchColumn = null" class="flex-1 bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-2 rounded-lg text-[13px] font-bold flex items-center justify-center gap-2 transition-colors border-none cursor-pointer">
                        <Search class="w-4 h-4" stroke-width="2.5" />
                        Tìm kiếm
                      </button>
                      <button @click="searchRegCode = ''; activeSearchColumn = null" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg text-[13px] font-semibold transition-colors border-none cursor-pointer">
                        Làm mới
                      </button>
                    </div>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('roomCode')">
                  <span>Mã phòng</span>
                  <Search class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <Transition name="hk-dropdown">
                  <div 
                    v-if="activeSearchColumn === 'roomCode'"
                    class="absolute top-[calc(100%+4px)] left-0 w-[280px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4 flex flex-col gap-3 font-normal normal-case tracking-normal"
                    @click.stop
                  >
                    <input 
                      type="text" 
                      v-model="searchRoomCode"
                      placeholder="Nhập mã phòng..." 
                      class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] w-full text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all font-semibold"
                    />
                    <div class="flex items-center gap-2">
                      <button @click="activeSearchColumn = null" class="flex-1 bg-[var(--hk-primary-dark)] hover:brightness-95 text-slate-800 py-2 rounded-lg text-[13px] font-bold flex items-center justify-center gap-2 transition-colors border-none cursor-pointer">
                        <Search class="w-4 h-4" stroke-width="2.5" />
                        Tìm kiếm
                      </button>
                      <button @click="searchRoomCode = ''; activeSearchColumn = null" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg text-[13px] font-semibold transition-colors border-none cursor-pointer">
                        Làm mới
                      </button>
                    </div>
                  </div>
                </Transition>
              </th>

              <th class="py-3 px-4">Mô tả</th>
            </tr>
          </thead>
          <tbody>
            <!-- Search Spinner Skeleton Rows -->
            <template v-if="isSearching">
              <tr v-for="i in 5" :key="'skeleton-'+i" class="animate-pulse">
                <td class="py-4 px-4 text-center border-b border-slate-100"><div class="h-4 w-6 bg-slate-200 rounded mx-auto"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-20 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-16 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-24 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-28 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-20 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-16 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-12 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-12 bg-slate-200 rounded"></div></td>
                <td class="py-4 px-4 border-b border-slate-100"><div class="h-4 w-64 bg-slate-200 rounded"></div></td>
              </tr>
            </template>

            <!-- Empty State -->
            <template v-else-if="filteredData.length === 0">
              <tr>
                <td colspan="10" class="p-20 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="bg-slate-50 rounded-full p-4 mb-3 border border-slate-100">
                      <Inbox class="w-10 h-10 text-slate-300" stroke-width="1.5" />
                    </div>
                    <h3 class="text-[14px] font-bold text-slate-700 mb-1">Không có dữ liệu lịch sử</h3>
                    <p class="text-[12px] text-slate-500">Hãy thử thay đổi bộ lọc hoặc từ khóa tìm kiếm.</p>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Data Rows -->
            <template v-else>
              <tr 
                v-for="item in filteredData" 
                :key="item.id" 
                class="border-b border-slate-100 hover:bg-[rgba(151,213,255,0.08)] transition-colors group cursor-pointer text-sm"
              >
                <td class="py-2.5 px-4 text-center border-r border-slate-200 font-semibold text-slate-500">{{ item.id }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 font-semibold text-slate-600">{{ item.time }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 text-slate-700">
                  <span class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded text-xs text-slate-600 font-semibold">{{ item.browser }}</span>
                </td>
                <td class="py-2.5 px-4 border-r border-slate-200 text-slate-800 font-semibold">{{ item.screen }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 text-slate-700 font-semibold">{{ item.user }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 text-slate-650">{{ formatDate(item.date) }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 text-center">
                  <span v-if="item.action === 'Tạo mới'" class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-250 rounded text-xs font-bold">{{ item.action }}</span>
                  <span v-else-if="item.action === 'Cập nhật'" class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 rounded text-xs font-bold">{{ item.action }}</span>
                  <span v-else-if="item.action === 'Xóa'" class="px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-250 rounded text-xs font-bold">{{ item.action }}</span>
                  <span v-else-if="item.action === 'Lỗi'" class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-250 rounded text-xs font-bold animate-pulse">{{ item.action }}</span>
                  <span v-else class="px-2 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 rounded text-xs font-semibold">{{ item.action }}</span>
                </td>
                <td class="py-2.5 px-4 border-r border-slate-200 font-bold text-slate-850">{{ item.regCode || '-' }}</td>
                <td class="py-2.5 px-4 border-r border-slate-200 font-bold text-slate-850">{{ item.roomCode || '-' }}</td>
                <td class="py-2.5 px-4 text-slate-650 text-xs truncate max-w-[280px]" :title="item.description">{{ item.description }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Calendar, ChevronDown, Download, Filter, Search, Inbox } from '@lucide/vue'

// --- Mock Data ---
const mockData = ref([
  {
    id: 1,
    time: '10:02:15',
    browser: 'Chrome',
    screen: 'Đồ thất lạc',
    user: 'Nguyễn Văn A',
    date: '2026-06-19',
    action: 'Cập nhật',
    regCode: '5197',
    roomCode: '302',
    description: 'Cập nhật trạng thái Trả khách cho Macbook Air M1'
  },
  {
    id: 2,
    time: '09:45:00',
    browser: 'Firefox',
    screen: 'Dịch vụ BP',
    user: 'Trần Thị B',
    date: '2026-06-19',
    action: 'Tạo mới',
    regCode: '5407',
    roomCode: '403',
    description: 'Tạo hóa đơn dịch vụ minibar nước ngọt phòng 403'
  },
  {
    id: 3,
    time: '08:30:22',
    browser: 'Edge',
    screen: 'In phân công',
    user: 'Lê C',
    date: '2026-06-19',
    action: 'Print',
    regCode: '',
    roomCode: '',
    description: 'In danh sách phân công tầng 4'
  },
  {
    id: 4,
    time: '16:15:10',
    browser: 'Chrome',
    screen: 'Tồn kho',
    user: 'Trần Thị B',
    date: '2026-06-18',
    action: 'Cập nhật',
    regCode: '',
    roomCode: '',
    description: 'Cập nhật tồn cuối tháng sản phẩm Khăn tắm'
  },
  {
    id: 5,
    time: '14:20:05',
    browser: 'Safari',
    screen: 'Menu SP',
    user: 'Nguyễn Văn A',
    date: '2026-06-18',
    action: 'Xóa',
    regCode: '',
    roomCode: '',
    description: 'Xóa sản phẩm cũ Trà Xanh khỏi Amenity'
  },
  {
    id: 6,
    time: '11:05:40',
    browser: 'Chrome',
    screen: 'Đồ thất lạc',
    user: 'Lê C',
    date: '2026-06-17',
    action: 'Lỗi',
    regCode: '',
    roomCode: '',
    description: 'Không tải được danh sách hình ảnh đồ thất lạc'
  }
])

// --- Filter panel expand state ---
const isFilterExpanded = ref(true)

// --- Searching indicator state ---
const isSearching = ref(false)
const executeSearch = () => {
  isSearching.value = true
  setTimeout(() => {
    isSearching.value = false
  }, 500)
}

// --- Date Picker Logic ---
const showDatePicker = ref(false)
const showDropdown = ref(false)
const datePickerWrapper = ref(null)
const dropdownWrapper = ref(null)

const getToday = () => {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().split('T')[0]
}

const fromDate = ref('2026-06-17')
const toDate = ref('2026-06-19')
const tempFromDate = ref('2026-06-17')
const tempToDate = ref('2026-06-19')
const dateRangeType = ref('custom')
const tempDateRangeType = ref('custom')

const dateRangeOptions = [
  { label: 'Hôm nay', value: 'today' },
  { label: 'Tuần này', value: 'this_week' },
  { label: 'Tháng này', value: 'this_month' },
  { label: 'Ngày mai', value: 'tomorrow' },
  { label: 'Tuần tiếp theo', value: 'next_week' },
  { label: 'Tháng tiếp theo', value: 'next_month' },
  { label: 'Hôm qua', value: 'yesterday' },
  { label: 'Tuần trước', value: 'last_week' },
  { label: 'Tháng trước', value: 'last_month' },
  { label: 'Tùy chỉnh', value: 'custom' },
]

const getTempSelectedLabel = () => {
  const opt = dateRangeOptions.find(o => o.value === tempDateRangeType.value)
  return opt ? opt.label : 'Tùy chỉnh'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}/${m}/${y}`
}

const openDatePicker = () => {
  if (!showDatePicker.value) {
    tempFromDate.value = fromDate.value
    tempToDate.value = toDate.value
    tempDateRangeType.value = dateRangeType.value
    showDatePicker.value = true
  } else {
    showDatePicker.value = false
  }
}

const selectDateRange = (value) => {
  tempDateRangeType.value = value
  showDropdown.value = false
  
  const d = new Date()
  let start = new Date(d)
  let end = new Date(d)
  
  const getMonday = (date) => {
    const day = date.getDay()
    const diff = date.getDate() - day + (day === 0 ? -6 : 1)
    return new Date(date.setDate(diff))
  }
  
  const getFirstDayOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1)
  const getLastDayOfMonth = (date) => new Date(date.getFullYear(), date.getMonth() + 1, 0)

  switch(value) {
    case 'today': break
    case 'yesterday':
      start.setDate(start.getDate() - 1); end.setDate(end.getDate() - 1); break
    case 'tomorrow':
      start.setDate(start.getDate() + 1); end.setDate(end.getDate() + 1); break
    case 'this_week':
      start = getMonday(new Date(d)); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'last_week':
      start = getMonday(new Date(d)); start.setDate(start.getDate() - 7); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'next_week':
      start = getMonday(new Date(d)); start.setDate(start.getDate() + 7); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'this_month':
      start = getFirstDayOfMonth(new Date(d)); end = getLastDayOfMonth(new Date(d)); break
    case 'last_month':
      start = getFirstDayOfMonth(new Date(d)); start.setMonth(start.getMonth() - 1); end = getLastDayOfMonth(new Date(start)); break
    case 'next_month':
      start = getFirstDayOfMonth(new Date(d)); start.setMonth(start.getMonth() + 1); end = getLastDayOfMonth(new Date(start)); break
    case 'custom': return
  }
  
  start.setMinutes(start.getMinutes() - start.getTimezoneOffset())
  end.setMinutes(end.getMinutes() - end.getTimezoneOffset())
  
  tempFromDate.value = start.toISOString().split('T')[0]
  tempToDate.value = end.toISOString().split('T')[0]
}

const applyDateRange = () => {
  fromDate.value = tempFromDate.value
  toDate.value = tempToDate.value
  dateRangeType.value = tempDateRangeType.value
  showDatePicker.value = false
  executeSearch()
}

// --- Active Filter and Search States ---
const searchQuery = ref('')
const searchRegCode = ref('')
const searchRoomCode = ref('')
const searchUser = ref('')
const searchScreen = ref('')
const selectedAction = ref('')
const selectedBrowser = ref('')
const quickFilter = ref('Tất cả')

watch([searchQuery, searchRegCode, searchRoomCode, searchUser, searchScreen, selectedAction, selectedBrowser, quickFilter], () => {
  executeSearch()
})

// --- Filter Logic ---
const filteredData = computed(() => {
  let result = [...mockData.value]

  // Date range filter
  if (fromDate.value) {
    result = result.filter(item => item.date >= fromDate.value)
  }
  if (toDate.value) {
    result = result.filter(item => item.date <= toDate.value)
  }

  // 1. Text description search
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    result = result.filter(item => item.description && item.description.toLowerCase().includes(q))
  }

  // 2. Browser filter
  if (selectedBrowser.value) {
    result = result.filter(item => item.browser === selectedBrowser.value)
  }

  // 3. Action filter
  if (selectedAction.value) {
    result = result.filter(item => item.action === selectedAction.value)
  }

  // 4. RegCode filter
  if (searchRegCode.value.trim()) {
    const rc = searchRegCode.value.toLowerCase().trim()
    result = result.filter(item => item.regCode && item.regCode.toLowerCase().includes(rc))
  }

  // 5. RoomCode filter
  if (searchRoomCode.value.trim()) {
    const r = searchRoomCode.value.toLowerCase().trim()
    result = result.filter(item => item.roomCode && item.roomCode.toLowerCase().includes(r))
  }

  // 6. User filter
  if (searchUser.value.trim()) {
    const u = searchUser.value.toLowerCase().trim()
    result = result.filter(item => item.user && item.user.toLowerCase().includes(u))
  }

  // 7. Screen filter
  if (searchScreen.value.trim()) {
    const s = searchScreen.value.toLowerCase().trim()
    result = result.filter(item => item.screen && item.screen.toLowerCase().includes(s))
  }

  // 8. Quick filter chips
  if (quickFilter.value !== 'Tất cả') {
    const today = getToday()
    if (quickFilter.value === 'Hôm nay') {
      result = result.filter(item => item.date === today)
    } else if (quickFilter.value === 'Tuần này') {
      const d = new Date()
      const day = d.getDay()
      const diffToMonday = d.getDate() - day + (day === 0 ? -6 : 1)
      const startOfWeek = new Date(d.setDate(diffToMonday)).toISOString().split('T')[0]
      const endOfWeek = new Date(d.setDate(diffToMonday + 6)).toISOString().split('T')[0]
      result = result.filter(item => item.date >= startOfWeek && item.date <= endOfWeek)
    } else if (quickFilter.value === 'Lỗi') {
      result = result.filter(item => item.action === 'Lỗi')
    } else if (quickFilter.value === 'CRUD') {
      result = result.filter(item => ['Tạo mới', 'Cập nhật', 'Xóa'].includes(item.action))
    }
  }

  return result
})

const resetAllFilters = () => {
  searchQuery.value = ''
  searchRegCode.value = ''
  searchRoomCode.value = ''
  searchUser.value = ''
  searchScreen.value = ''
  selectedAction.value = ''
  selectedBrowser.value = ''
  quickFilter.value = 'Tất cả'
  fromDate.value = '2026-06-17'
  toDate.value = '2026-06-19'
  executeSearch()
}

// --- Search Popover Logic ---
const activeSearchColumn = ref(null)

const toggleSearchPopover = (col) => {
  if (activeSearchColumn.value === col) {
    activeSearchColumn.value = null
  } else {
    activeSearchColumn.value = col
  }
}

// --- Click Outside Logic ---
const closeClickOutside = (e) => {
  if (showDropdown.value && dropdownWrapper.value && !dropdownWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (showDatePicker.value && datePickerWrapper.value && !datePickerWrapper.value.contains(e.target)) {
    showDatePicker.value = false
  }
  if (activeSearchColumn.value) {
    const isInsideTh = e.target.closest('th')
    if (!isInsideTh) {
      activeSearchColumn.value = null
    }
  }
}

onMounted(() => {
  document.addEventListener('click', closeClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeClickOutside)
})
</script>

<style scoped>
.hk-expand-enter-active, .hk-expand-leave-active {
  transition: all 0.3s ease;
  max-height: 500px;
}
.hk-expand-enter-from, .hk-expand-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  margin-top: 0 !important;
}
</style>
