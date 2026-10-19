<template>
  <div class="relative h-full min-h-0">
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">
    
    <!-- Filters Grid Panel -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4 flex flex-col gap-3 shrink-0">
      <div class="flex flex-wrap items-end gap-3">
        <!-- Left side: Date Range -->
        <div class="flex flex-col gap-1.5 relative w-[240px]" ref="datePickerWrapper">
          <label class="text-xs font-semibold text-[#000000D9]">Thời gian</label>
          <div class="relative flex items-center cursor-pointer" @click="openDatePicker">
            <input 
              type="text" 
              readonly
              :value="`${formatDate(fromDate)} ~ ${formatDate(toDate)}`"
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-8 text-xs text-[#000000D9] bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 cursor-pointer transition-all shadow-sm font-normal"
            />
            <button 
              type="button" 
              @click.stop="openDatePicker"
              class="absolute right-2 text-slate-400 hover:text-blue-600 transition-colors p-0.5 cursor-pointer"
              title="Chọn khoảng ngày"
            >
              <Calendar class="w-4 h-4" />
            </button>
          </div>

          <!-- Date Picker Popover -->
          <Transition name="hk-dropdown">
            <div 
              v-if="showDatePicker"
              class="absolute top-[calc(100%+4px)] left-0 w-[350px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4"
            >
              <div class="flex flex-col gap-4">
                <div>
                  <label class="text-xs font-semibold text-[#000000D9] mb-1.5 block">Phạm vi ngày</label>
                  
                  <!-- Custom Select Dropdown -->
                  <div class="relative" ref="dropdownWrapper">
                    <div 
                      @click="showDropdown = !showDropdown"
                      class="h-8 border border-slate-300 rounded-lg px-3 text-xs text-[#000000D9] cursor-pointer flex justify-between items-center hover:border-blue-500 transition-colors bg-white font-normal shadow-sm"
                    >
                      <span>{{ getTempSelectedLabel() }}</span>
                      <ChevronDown class="w-4 h-4 text-slate-400" />
                    </div>
                    
                    <!-- Dropdown List -->
                    <Transition name="hk-dropdown">
                      <div 
                        v-if="showDropdown"
                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto hk-scroll"
                      >
                        <ul class="py-1">
                          <li 
                            v-for="opt in dateRangeOptions" 
                            :key="opt.value"
                            @click.stop="selectDateRange(opt.value)"
                            class="px-4 py-2 text-xs text-[#000000D9] hover:bg-sky-50 hover:text-blue-600 cursor-pointer transition-colors"
                            :class="{'bg-sky-50 text-blue-600 font-semibold': tempDateRangeType === opt.value}"
                          >
                            {{ opt.label }}
                          </li>
                        </ul>
                      </div>
                    </Transition>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <div class="relative flex-1 min-w-0">
                    <SingleDatePicker
                      v-model="tempFromDate"
                      :start-date="tempFromDate || systemDate"
                      placeholder="dd/mm/yyyy"
                      four-digit-year
                      @change="tempDateRangeType = 'custom'"
                    />
                  </div>
                  <span class="text-slate-400 font-medium shrink-0">~</span>
                  <div class="relative flex-1 min-w-0">
                    <SingleDatePicker
                      v-model="tempToDate"
                      :start-date="tempToDate || tempFromDate || systemDate"
                      placeholder="dd/mm/yyyy"
                      four-digit-year
                      @change="tempDateRangeType = 'custom'"
                    />
                  </div>
                </div>

                <div class="border-t border-slate-100 pt-3 flex justify-end">
                  <button 
                    @click="applyDateRange"
                    class="btn-pms-primary h-8 px-4 text-xs font-semibold flex items-center gap-1.5 cursor-pointer"
                  >
                    <CheckCircle2 class="w-4 h-4" />
                    Áp dụng
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Mã ĐK filter -->
        <div class="flex flex-col gap-1.5 w-[110px] relative">
          <label class="text-xs font-semibold text-[#000000D9]">Mã ĐK</label>
          <div class="relative flex items-center">
            <input 
              type="text" 
              data-hk-search
              v-model="filterState.regCode"
              placeholder="Mã ĐK..."
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs text-[#000000D9] placeholder:text-[#A8B0BF] bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm font-normal"
            />
            <button 
              v-if="filterState.regCode" 
              type="button" 
              @click="filterState.regCode = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
              title="Xóa nhanh"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Phòng filter -->
        <div class="flex flex-col gap-1.5 w-[115px] relative">
          <label class="text-xs font-semibold text-[#000000D9]">Phòng</label>
          <div class="relative flex items-center">
            <select 
              v-model="filterState.room"
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm cursor-pointer font-normal appearance-none"
              :class="filterState.room ? 'text-[#000000D9]' : 'text-[#A8B0BF]'"
            >
              <option value="" disabled hidden>Tất cả</option>
              <option v-for="r in filterOptions.rooms" :key="r" :value="r" class="text-[#000000D9]">Phòng {{ r }}</option>
            </select>
            <ChevronDown v-if="!filterState.room" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
            <button 
              v-else 
              type="button" 
              @click="filterState.room = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
              title="Xóa nhanh"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Bộ phận filter -->
        <div class="flex flex-col gap-1.5 w-[120px] relative">
          <label class="text-xs font-semibold text-[#000000D9]">Bộ phận</label>
          <div class="relative flex items-center">
            <select 
              v-model="filterState.dept"
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm cursor-pointer font-normal appearance-none"
              :class="filterState.dept ? 'text-[#000000D9]' : 'text-[#A8B0BF]'"
            >
              <option value="" disabled hidden>Tất cả</option>
              <option v-for="dept in filterOptions.departments" :key="dept" :value="dept" class="text-[#000000D9]">{{ dept }}</option>
            </select>
            <ChevronDown v-if="!filterState.dept" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
            <button 
              v-else 
              type="button" 
              @click="filterState.dept = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
              title="Xóa nhanh"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Người dùng filter -->
        <div class="flex flex-col gap-1.5 w-[130px] relative">
          <label class="text-xs font-semibold text-[#000000D9]">Người dùng</label>
          <div class="relative flex items-center">
            <select 
              v-model="filterState.user"
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm cursor-pointer font-normal appearance-none"
              :class="filterState.user ? 'text-[#000000D9]' : 'text-[#A8B0BF]'"
            >
              <option value="" disabled hidden>Tất cả</option>
              <option v-for="user in filterOptions.users" :key="user" :value="user" class="text-[#000000D9]">{{ user }}</option>
            </select>
            <ChevronDown v-if="!filterState.user" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
            <button 
              v-else 
              type="button" 
              @click="filterState.user = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
              title="Xóa nhanh"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Outlet / Khu vực filter -->
        <div class="flex flex-col gap-1.5 w-[115px] relative">
          <label class="text-xs font-semibold text-[#000000D9]">Khu vực</label>
          <div class="relative flex items-center">
            <select 
              v-model="filterState.zone"
              class="w-full h-8 border border-slate-300 rounded-lg pl-2.5 pr-7 text-xs bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm cursor-pointer font-normal appearance-none"
              :class="filterState.zone ? 'text-[#000000D9]' : 'text-[#A8B0BF]'"
            >
              <option value="" disabled hidden>Tất cả</option>
              <option v-for="outlet in filterOptions.outlets" :key="outlet" :value="outlet" class="text-[#000000D9]">{{ outlet }}</option>
            </select>
            <ChevronDown v-if="!filterState.zone" class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" />
            <button 
              v-else 
              type="button" 
              @click="filterState.zone = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
              title="Xóa nhanh"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Right Side buttons -->
        <div class="flex items-center gap-2">
          <button @click="triggerSearch" class="btn-pms-primary h-8 px-4 text-xs font-semibold shadow-sm cursor-pointer flex items-center justify-center min-w-[70px]">
            <span v-if="isSearching" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></span>
            <span>Xem</span>
          </button>
          <button 
            v-if="canPostHousekeepingBill"
            @click="openAddModal"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 h-8 rounded-lg flex items-center transition-all text-xs font-semibold shadow-sm gap-1.5 cursor-pointer"
          >
            <Plus class="w-3.5 h-3.5" stroke-width="2.5" />
            Thêm
          </button>
        </div>
      </div>

      <!-- Trạng thái Chip buttons row -->
      <div class="flex items-center gap-2 border-t border-slate-100 pt-2.5 text-xs">
        <span class="text-xs font-semibold text-[#000000D9] mr-1">Trạng thái:</span>
        <button 
          v-for="st in [
            { label: 'Tất cả', value: 'all' },
            { label: 'Đã thanh toán', value: 'paid' },
            { label: 'Chưa thanh toán', value: 'unpaid' },
            { label: 'Đã xóa', value: 'deleted' }
          ]"
          :key="st.value"
          @click="filterState.status = st.value"
          class="px-3 py-1 rounded-full text-xs transition-all duration-200 cursor-pointer border"
          :class="filterState.status === st.value
            ? 'bg-blue-50 text-blue-700 border-blue-500 font-semibold shadow-sm'
            : 'bg-white text-[#000000D9] border-slate-200 hover:bg-slate-50'"
        >
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max text-xs">
          <thead class="bg-slate-100/90 text-[#000000D9] text-xs font-semibold border-b border-slate-200 sticky top-0 z-10">
            <tr>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">STT</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Ngày - Giờ</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Phòng</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Dịch Vụ</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold min-w-[220px]">Mô Tả Sản Phẩm</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Giá</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Miễn Phí</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Mã TT</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Ghi Chú</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Bộ Phận</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Người Dùng</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Khu Vực</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Đã Xóa</th>
              <th class="py-2.5 px-3 border border-slate-200 text-center align-middle font-semibold">Thao Tác</th>
            </tr>
          </thead>

          <tbody v-if="isSearching">
            <!-- loading skeleton state -->
            <tr v-for="i in 5" :key="'sk-'+i" class="animate-pulse">
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-8 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-24 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-16 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-16 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-40"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-16 ml-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-6 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-14 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-10 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-12 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-20 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-12 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-6 mx-auto"></div></td>
              <td class="py-4 px-4 border-slate-200"><div class="h-3.5 bg-slate-200 rounded w-16 mx-auto"></div></td>
            </tr>
          </tbody>

          <tbody v-else-if="!hasLoadedData" class="divide-y divide-slate-100">
            <tr>
              <td colspan="14" class="p-20 text-center bg-white border-slate-200">
                <div class="flex flex-col items-center justify-center gap-3">
                  <div class="bg-slate-50 rounded-full p-4 border border-slate-100 animate-bounce">
                    <Inbox class="w-10 h-10 text-slate-350" stroke-width="1.5" />
                  </div>
                  <h3 class="text-[14px] font-bold text-slate-700">Chưa tải dữ liệu</h3>
                  <p class="text-[12px] text-slate-500 max-w-sm mx-auto leading-relaxed">
                    Hãy bấm nút <strong class="text-[var(--hk-primary-dark)]">Xem</strong> ở góc phải trên để tải dữ liệu hóa đơn dịch vụ buồng phòng.
                  </p>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="filteredInvoices.length === 0">
            <tr>
              <td colspan="14" class="p-20 text-center bg-white text-slate-400 border-slate-200">
                <div class="flex flex-col items-center justify-center gap-3">
                  <span class="text-4xl">🔍</span>
                  <p class="font-bold text-slate-700 text-[14px]">Không tìm thấy hóa đơn phù hợp</p>
                  <p class="text-[12px] text-slate-500">Thử thay đổi bộ lọc hoặc điều kiện thời gian.</p>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else class="divide-y divide-slate-100">
            <tr 
              v-for="(inv, index) in filteredInvoices" 
              :key="inv.id"
              class="transition-colors cursor-pointer"
              :class="inv.isDeleted 
                ? 'invoice-deleted italic text-red-600 bg-red-50/50 hover:bg-red-50/80 font-normal' 
                : 'text-[#000000D9] hover:bg-slate-50/80 font-normal'"
              @click="openInvoiceDetail(inv)"
            >
              <!-- 1. STT -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal" :class="inv.isDeleted ? 'text-red-600' : 'text-[#000000D9]'">{{ index + 1 }}</td>
              
              <!-- 2. Ngày - Giờ -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal">{{ inv.time || '—' }}</td>
              
              <!-- 3. Phòng -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal" :class="!inv.isDeleted ? 'text-blue-600' : 'text-red-600'">
                {{ inv.room ? 'Phòng ' + inv.room : '—' }}
              </td>
              
              <!-- 4. Dịch vụ -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal">
                {{ inv.service || inv.zone || inv.dept || '—' }}
              </td>

              <!-- 5. Mô tả sản phẩm (danh sách tên + số lượng x{sl}) -->
              <td class="py-2.5 px-3 border-b border-slate-100 text-left whitespace-normal">
                <div v-if="inv.products && inv.products.length" class="flex flex-col gap-1">
                  <div 
                    v-for="(p, pIdx) in inv.products" 
                    :key="pIdx"
                    class="text-xs leading-snug flex items-center justify-between gap-2 font-normal"
                  >
                    <span>{{ p.name }}</span>
                    <span class="text-xs shrink-0 font-normal" :class="inv.isDeleted ? 'text-red-400' : 'text-slate-500'">×{{ p.quantity }}</span>
                  </div>
                </div>
                <span v-else class="text-[#A8B0BF]">—</span>
              </td>

              <!-- 6. Giá -->
              <td class="py-2.5 px-3 text-right border-b border-slate-100 font-normal" :class="inv.isDeleted ? 'text-red-600' : 'text-[#000000D9]'">
                {{ formatCurrency(inv.price) }}
              </td>

              <!-- 7. Miễn phí (checkbox) -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100">
                <input 
                  type="checkbox" 
                  :checked="inv.isFree" 
                  disabled 
                  class="w-4 h-4 rounded border-slate-300 text-blue-600 pointer-events-none accent-blue-600 align-middle"
                />
              </td>

              <!-- 8. Mã TT -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal text-xs">{{ inv.payCode || '—' }}</td>

              <!-- 9. Ghi Chú (thay thế Mã HĐ Tay) -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 text-xs font-normal max-w-[160px] truncate" :title="inv.note">
                {{ inv.note || '—' }}
              </td>

              <!-- 10. Bộ phận -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="getDeptClass(inv.dept)">
                  {{ inv.dept || 'HK' }}
                </span>
              </td>

              <!-- 11. Người dùng -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal">{{ inv.user || '—' }}</td>

              <!-- 12. Khu vực -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100 font-normal">{{ inv.zone || '—' }}</td>

              <!-- 13. Đã xóa (checkbox) -->
              <td class="py-2.5 px-3 text-center border-b border-slate-100">
                <input 
                  type="checkbox" 
                  :checked="inv.isDeleted" 
                  disabled 
                  class="w-4 h-4 rounded border-slate-300 text-red-600 pointer-events-none accent-red-600 align-middle"
                />
              </td>

              <!-- 14. Thao tác (In / Xóa) -->
              <td class="py-2 px-3 text-center border-b border-slate-100" @click.stop>
                <div class="flex items-center justify-center gap-1.5">
                  <button 
                    type="button"
                    @click.stop="printInvoice(inv)"
                    title="In hóa đơn"
                    class="p-1.5 rounded-md bg-white text-slate-700 hover:bg-slate-50 border border-slate-300 transition-colors cursor-pointer flex items-center justify-center shadow-xs"
                  >
                    <Printer class="w-3.5 h-3.5" />
                  </button>
                  <button 
                    type="button"
                    v-if="inv.canCancel && !inv.isDeleted"
                    @click.stop="openCancelModal(inv)"
                    title="Hủy hóa đơn"
                    class="p-1.5 rounded-md bg-red-50 text-red-600 hover:bg-red-100 transition-colors border border-red-200 cursor-pointer flex items-center justify-center shadow-xs"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Thêm Dịch Vụ BP Modal -->
    <Transition name="hk-modal">
      <div 
        v-if="showAddModal && canPostHousekeepingBill"
        class="fixed inset-0 z-[100] flex justify-center items-center bg-slate-900/50 backdrop-blur-sm"
        @click.self="showAddModal = false"
      >
        <div class="bg-white rounded-xl shadow-2xl flex flex-col w-[95vw] h-[95vh] max-w-[1400px] overflow-hidden border border-slate-200 transform transition-all">
          <!-- Modal Header with Theme Color -->
          <div 
            class="px-5 py-3 flex justify-between items-center shrink-0 text-white" 
            :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
          >
            <h3 class="text-sm font-semibold tracking-wide text-white">Thêm Hóa Đơn Dịch Vụ Buồng Phòng</h3>
            <div class="flex items-center gap-2">
              <button 
                @click="showAddModal = false"
                class="text-white hover:bg-white/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"
                title="Đóng (Esc)"
              >
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>
          
          <!-- Modal Body (PostBillHousekeepingTab) -->
          <div class="flex-1 overflow-hidden bg-slate-50">
            <PostBillHousekeepingTab />
          </div>
        </div>
      </div>
    </Transition>

    <!-- Drawer Chi Tiết Hóa Đơn -->
    <Transition name="hk-drawer">
      <div v-if="detailTarget" class="fixed inset-0 z-[110] bg-slate-900/25 backdrop-blur-xs" @click.self="closeInvoiceDetail">
        <div class="fixed right-0 top-0 flex h-full w-full max-w-[560px] flex-col overflow-hidden bg-white shadow-2xl">
          <!-- Header with Theme Color -->
          <div 
            class="flex items-center justify-between px-5 py-3 text-white" 
            :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
          >
            <h3 class="text-sm font-semibold text-white tracking-wide">Chi Tiết Hóa Đơn</h3>
            <button 
              type="button" 
              class="rounded-full p-1.5 text-white hover:bg-white/10 transition-colors cursor-pointer border-none bg-transparent" 
              @click="closeInvoiceDetail"
              title="Đóng (Esc)"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
          
          <div class="flex-1 overflow-auto p-5">
            <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
              <div><span class="font-semibold text-[#000000D9]">Mã hóa đơn:</span> <span class="font-normal text-[#000000D9]">#{{ detailTarget.id }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Phòng:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.room || '—' }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Mã ĐK:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.regCode || '—' }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Tên khách:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.guestName || '—' }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Ngày giờ:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.time || '—' }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Bộ phận:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.dept || '—' }}</span></div>
              <div class="sm:col-span-2"><span class="font-semibold text-[#000000D9]">Ghi chú:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.note || '—' }}</span></div>
              <div><span class="font-semibold text-[#000000D9]">Miễn phí:</span> <span class="font-normal text-[#000000D9]">{{ detailTarget.isFree ? 'Có' : 'Không' }}</span></div>
            </div>
            
            <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
              <table class="w-full border-collapse text-xs">
                <thead class="bg-slate-100 text-[#000000D9]">
                  <tr>
                    <th class="border border-slate-200 px-3 py-2 text-center align-middle font-semibold">STT</th>
                    <th class="border border-slate-200 px-3 py-2 text-center align-middle font-semibold">Sản Phẩm</th>
                    <th class="border border-slate-200 px-3 py-2 text-center align-middle font-semibold">Số Lượng</th>
                    <th class="border border-slate-200 px-3 py-2 text-center align-middle font-semibold">Đơn Giá</th>
                    <th class="border border-slate-200 px-3 py-2 text-center align-middle font-semibold">Số Tiền</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-normal text-[#000000D9]">
                  <tr v-for="(item, index) in detailTarget.products" :key="`${detailTarget.id}-${index}`">
                    <td class="px-3 py-2 text-center border-b border-slate-100">{{ index + 1 }}</td>
                    <td class="px-3 py-2 border-b border-slate-100">{{ item.name || '—' }}</td>
                    <td class="px-3 py-2 text-right border-b border-slate-100">{{ item.quantity || 0 }}</td>
                    <td class="px-3 py-2 text-right border-b border-slate-100">{{ formatCurrency(item.rate || 0) }}</td>
                    <td class="px-3 py-2 text-right border-b border-slate-100">{{ formatCurrency(item.amount || 0) }}</td>
                  </tr>
                  <tr v-if="!detailTarget.products.length">
                    <td colspan="5" class="px-3 py-6 text-center text-[#A8B0BF] border-b border-slate-100">Không có chi tiết sản phẩm</td>
                  </tr>
                  <tr class="border-t border-slate-200 font-semibold bg-slate-50">
                    <td colspan="4" class="px-3 py-2 text-left text-[#000000D9]">Tổng tiền</td>
                    <td class="px-3 py-2 text-right text-blue-600">{{ formatCurrency(detailTarget.price) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="flex items-center justify-end gap-2 border-t border-slate-200 p-4">
            <button 
              type="button" 
              class="btn-pms-primary h-8 px-4 text-xs font-semibold" 
              @click="printInvoice(detailTarget)"
            >
              <Printer class="w-3.5 h-3.5" />
              <span>In</span>
            </button>
            <button 
              v-if="detailTarget.canCancel && !detailTarget.isDeleted && canCancelHousekeepingBill" 
              type="button" 
              class="btn-pms-danger h-8 px-4 text-xs font-semibold" 
              @click="openCancelModal(detailTarget)"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>Hủy hóa đơn</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal Xác Nhận Hủy/Xóa Hóa Đơn -->
    <Transition name="hk-modal">
      <div 
        v-if="cancelTarget && canCancelHousekeepingBill" 
        class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" 
        @click.self="closeCancelModal"
      >
        <div class="w-full max-w-md rounded-xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
          <!-- Header with Theme Color -->
          <div 
            class="flex items-center justify-between px-5 py-3 text-white" 
            :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
          >
            <h3 class="text-sm font-semibold text-white tracking-wide">Xác Nhận Xóa Hóa Đơn</h3>
            <button 
              type="button" 
              class="rounded-full p-1.5 text-white hover:bg-white/10 transition-colors cursor-pointer border-none bg-transparent" 
              @click="closeCancelModal"
              title="Đóng (Esc)"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-5 flex flex-col gap-3">
            <label class="text-xs font-semibold text-[#000000D9]">
              Lý do xóa <span class="text-red-500">*</span>
            </label>
            <div class="relative flex items-center">
              <input 
                v-model="cancelReason" 
                type="text" 
                maxlength="255" 
                autofocus
                class="w-full h-8 rounded-lg border px-3 pr-7 text-xs text-[#000000D9] placeholder:text-[#A8B0BF] bg-[#FFF8DB] border-[#F1DD8A] focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500" 
                placeholder="Nhập lý do xóa hóa đơn..." 
              />
              <button 
                v-if="cancelReason" 
                type="button" 
                @click="cancelReason = ''"
                class="absolute right-2 text-slate-400 hover:text-slate-600 p-0.5 cursor-pointer"
                title="Xóa nhanh"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex justify-end gap-2 border-t border-slate-100 p-4">
            <button 
              type="button" 
              class="btn-pms-close h-8 px-4 text-xs font-semibold cursor-pointer" 
              @click="closeCancelModal"
            >
              <X class="w-3.5 h-3.5" />
              <span>Đóng</span>
            </button>
            <button 
              type="button" 
              class="btn-pms-danger h-8 px-4 text-xs font-semibold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" 
              :disabled="isCancelling || !cancelReason.trim()" 
              @click="cancelInvoice"
            >
              <Trash2 class="w-3.5 h-3.5" />
              <span>{{ isCancelling ? 'Đang xử lý...' : 'Xác nhận xóa' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Calendar, Plus, Inbox, ChevronDown, CheckCircle2, X, HelpCircle, Printer, Trash2 } from '@lucide/vue'
import PostBillHousekeepingTab from './PostBillHousekeepingTab.vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { usePermission } from '@/composables/usePermission'
import SingleDatePicker from '@/components/SingleDatePicker.vue'

const route = useRoute()
const uiStore = useUiStore()
const authStore = useAuthStore()
const { can } = usePermission()
const canPostHousekeepingBill = computed(() => can('hk.service.bill') || can('fo.service.add') || can('fo.service.edit'))
const canCancelHousekeepingBill = computed(() => can('hk.service.delete'))

// Mock Invoice data
const mockInvoices = ref([
  { id: 101, regCode: '5197', room: '402', time: '19-06-2026 09:30', isFree: false, price: 45000, payCode: 'PAY-101', manualCode: 'M-129', user: 'Nguyễn Văn A', isDeleted: false, dept: 'Minibar', zone: 'Khu A' },
  { id: 102, regCode: '5407', room: '403', time: '19-06-2026 10:15', isFree: false, price: 120000, payCode: '', manualCode: '', user: 'Trần Thị B', isDeleted: false, dept: 'Giặt ủi', zone: 'Khu B' },
  { id: 103, regCode: '5408', room: '405', time: '18-06-2026 14:00', isFree: true, price: 15000, payCode: 'FREE-103', manualCode: '', user: 'Lê Văn C', isDeleted: false, dept: 'Minibar', zone: 'Khu A' },
  { id: 104, regCode: '5330', room: '406', time: '17-06-2026 16:45', isFree: false, price: 50000, payCode: 'PAY-104', manualCode: 'M-130', user: 'Nguyễn Văn A', isDeleted: true, dept: 'Hàng đền bù', zone: 'Khu C' },
  { id: 105, regCode: '5041', room: '407', time: '15-06-2026 11:20', isFree: false, price: 150000, payCode: 'PAY-105', manualCode: '', user: 'Trần Thị B', isDeleted: false, dept: 'Hàng đền bù', zone: 'Khu B' }
])

const invoicesList = ref([])
const detailTarget = ref(null)
const cancelTarget = ref(null)
const cancelReason = ref('')
const isCancelling = ref(false)

const filterOptions = computed(() => ({
  rooms: [...new Set(invoicesList.value.map(item => item.room).filter(Boolean))].sort(),
  departments: [...new Set(invoicesList.value.map(item => item.dept).filter(Boolean))].sort(),
  users: [...new Set(invoicesList.value.map(item => item.user).filter(Boolean))].sort(),
  outlets: [...new Set(invoicesList.value.map(item => item.zone).filter(Boolean))].sort()
}))

const filterState = ref({
  regCode: '',
  room: '',
  status: 'all', // 'all', 'paid', 'unpaid', 'deleted'
  dept: '',      // '' = tất cả
  user: '',      // '' = tất cả
  zone: ''       // '' = tất cả
})

const hasLoadedData = ref(false)
const isSearching = ref(false)

const triggerSearch = async () => {
  isSearching.value = true
  try {
    const { data } = await http.get('/housekeeping/service-bills', { params: {
      date_from: fromDate.value,
      date_to: toDate.value,
      booking_code: filterState.value.regCode || undefined,
      room_no: filterState.value.room || undefined,
      department: filterState.value.dept || undefined,
      username: filterState.value.user || undefined,
      outlet: filterState.value.zone || undefined,
      status: filterState.value.status
    } })
    invoicesList.value = (data.data || []).map(item => ({
      id: item.id, regCode: item.booking_code || '', guestName: item.guest_name || '', room: item.room_no || '', products: item.products || [], note: item.note || '',
      time: item.date ? `${item.date.split('-').reverse().join('-')} ${item.time || ''}` : '',
      isFree: item.is_free, price: item.amount, payCode: item.payment_id || '',
      manualCode: item.manual_invoice_code || '', user: item.username || '',
      service: item.service || item.outlet || item.department || 'Dịch vụ HK',
      isDeleted: item.is_deleted, canCancel: item.can_cancel === true, dept: item.department || '', zone: item.outlet || ''
    }))
  } finally {
    isSearching.value = false
    hasLoadedData.value = true
  }
}

onMounted(async () => {
  try {
    const { data } = await http.get('/system-date')
    const businessDate = data?.data?.system_date
    if (businessDate) {
      systemDate.value = businessDate
      authStore.setSystemDate(businessDate)
      fromDate.value = businessDate
      toDate.value = businessDate
      tempFromDate.value = businessDate
      tempToDate.value = businessDate
    }
  } finally {
    triggerSearch()
  }
})

watch(() => route.query.triggerSearch, (newVal) => {
  if (newVal === 'true') {
    triggerSearch()
  }
})

watch(filterState, () => {
  triggerSearch()
}, { deep: true })

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

const systemDate = ref(authStore.systemDate || localStorage.getItem('pms_system_date') || getToday())
const fromDate = ref(systemDate.value)
const toDate = ref(systemDate.value)
const tempFromDate = ref(systemDate.value)
const tempToDate = ref(systemDate.value)
const dateRangeType = ref('today')

const tempDateRangeType = ref('today')

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
  
  const d = new Date(`${systemDate.value}T00:00:00`)
  let start = new Date(d)
  let end = new Date(d)
  
  const getMonday = (date) => {
    const day = date.getDay()
    const diff = date.getDate() - day + (day === 0 ? -6 : 1)
    return new Date(date.setDate(diff))
  }
  
  const getFirstDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1)
  }
  
  const getLastDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0)
  }

  switch(value) {
    case 'today':
      break
    case 'yesterday':
      start.setDate(start.getDate() - 1)
      end.setDate(end.getDate() - 1)
      break
    case 'tomorrow':
      start.setDate(start.getDate() + 1)
      end.setDate(end.getDate() + 1)
      break
    case 'this_week':
      start = getMonday(new Date(d))
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'last_week':
      start = getMonday(new Date(d))
      start.setDate(start.getDate() - 7)
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'next_week':
      start = getMonday(new Date(d))
      start.setDate(start.getDate() + 7)
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'this_month':
      start = getFirstDayOfMonth(new Date(d))
      end = getLastDayOfMonth(new Date(d))
      break
    case 'last_month':
      start = getFirstDayOfMonth(new Date(d))
      start.setMonth(start.getMonth() - 1)
      end = getLastDayOfMonth(new Date(start))
      break
    case 'next_month':
      start = getFirstDayOfMonth(new Date(d))
      start.setMonth(start.getMonth() + 1)
      end = getLastDayOfMonth(new Date(start))
      break
    case 'custom':
      return
  }
  
  start.setMinutes(start.getMinutes() - start.getTimezoneOffset())
  end.setMinutes(end.getMinutes() - end.getTimezoneOffset())
  
  tempFromDate.value = start.toISOString().split('T')[0]
  tempToDate.value = end.toISOString().split('T')[0]
}

watch(tempFromDate, (newVal) => {
  if (newVal && tempToDate.value && newVal > tempToDate.value) {
    tempToDate.value = newVal
  }
})

const applyDateRange = () => {
  if (tempFromDate.value && tempToDate.value && tempFromDate.value > tempToDate.value) {
    tempToDate.value = tempFromDate.value
  }
  fromDate.value = tempFromDate.value
  toDate.value = tempToDate.value
  dateRangeType.value = tempDateRangeType.value
  showDatePicker.value = false
  triggerSearch()
}

// Close popovers on click outside
const closeClickOutside = (e) => {
  if (showDropdown.value && dropdownWrapper.value && !dropdownWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (showDatePicker.value && datePickerWrapper.value && !datePickerWrapper.value.contains(e.target)) {
    showDatePicker.value = false
  }
}

// Modal Logic
const showAddModal = ref(false)

const parseInvoiceDate = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string') return new Date(0)
  const [dPart] = dateStr.trim().split(' ')
  if (!dPart) return new Date(0)
  const parts = dPart.split('-')
  if (parts.length !== 3) return new Date(0)
  const [d, m, y] = parts
  const parsed = new Date(Number(y), Number(m) - 1, Number(d))
  return isNaN(parsed.getTime()) ? new Date(0) : parsed
}

// Invoices computation
const filteredInvoices = computed(() => {
  return invoicesList.value.filter(inv => {
    // 1. Time range filter
    const invDate = parseInvoiceDate(inv.time)
    const start = new Date(fromDate.value)
    const end = new Date(toDate.value)
    
    // Reset hours for date comparison
    invDate.setHours(0,0,0,0)
    start.setHours(0,0,0,0)
    end.setHours(0,0,0,0)
    
    if (invDate < start || invDate > end) return false

    // 2. RegCode filter
    if (filterState.value.regCode && !inv.regCode.toLowerCase().includes(filterState.value.regCode.toLowerCase())) return false

    // 3. Room filter
    if (filterState.value.room && inv.room !== filterState.value.room) return false

    // 4. Dept filter
    if (filterState.value.dept && inv.dept !== filterState.value.dept) return false

    // 5. User filter
    if (filterState.value.user && inv.user !== filterState.value.user) return false

    // 6. Zone filter
    if (filterState.value.zone && inv.zone !== filterState.value.zone) return false

    // 7. Status filter
    if (filterState.value.status === 'deleted') {
      return inv.isDeleted
    }
    // All other statuses ('all', 'paid', 'unpaid') hide deleted bills
    if (inv.isDeleted) return false

    if (filterState.value.status === 'paid') {
      return inv.payCode !== ''
    }
    if (filterState.value.status === 'unpaid') {
      return inv.payCode === ''
    }

    return true
  })
})

const getDeptClass = (dept) => {
  if (dept === 'Minibar') return 'bg-amber-100 text-amber-800 border border-amber-200'
  if (dept === 'Giặt ủi') return 'bg-blue-100 text-blue-800 border border-blue-200'
  return 'bg-rose-100 text-rose-800 border border-rose-200'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN').format(value)
}

const openAddModal = () => {
  if (!canPostHousekeepingBill.value) return
  showAddModal.value = true
}

const openCancelModal = (invoice) => {
  if (!canCancelHousekeepingBill.value) return
  cancelTarget.value = invoice
  cancelReason.value = ''
}

const openInvoiceDetail = (invoice) => {
  detailTarget.value = invoice
}

const closeInvoiceDetail = () => {
  if (!cancelTarget.value) detailTarget.value = null
}

const closeCancelModal = () => {
  if (isCancelling.value) return
  cancelTarget.value = null
  cancelReason.value = ''
}

const cancelInvoice = async () => {
  if (!canCancelHousekeepingBill.value || !cancelTarget.value || !cancelReason.value.trim()) return
  isCancelling.value = true
  try {
    await http.post(`/housekeeping/service-bills/${cancelTarget.value.id}/cancel`, { reason: cancelReason.value.trim() })
    uiStore.showToast('Đã xóa hóa đơn và tạo dòng âm đối trừ.', 'success')
    cancelTarget.value = null
    cancelReason.value = ''
    detailTarget.value = null
    await triggerSearch()
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa hóa đơn.', 'error')
  } finally {
    isCancelling.value = false
  }
}

const printInvoice = (invoice) => {
  const rows = (invoice.products || []).map(item => `
    <tr>
      <td>${item.name || '—'}</td>
      <td style="text-align:center">${item.quantity || 0}</td>
      <td style="text-align:right">${formatCurrency(item.rate || 0)}</td>
      <td style="text-align:right">${formatCurrency(item.amount || 0)}</td>
    </tr>
  `).join('') || '<tr><td colspan="4" style="text-align:center;color:#888;padding:16px;">Không có chi tiết sản phẩm</td></tr>'

  const printWindow = window.open('', '_blank', 'width=850,height=650')
  if (!printWindow) {
    uiStore.showToast('Trình duyệt đã chặn cửa sổ in. Vui lòng cho phép popup.', 'warning')
    return
  }
  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Hóa đơn #${invoice.id}</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 28px; color: #1e293b; margin: 0; }
    .header { border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 20px; }
    h2 { margin: 0 0 6px 0; color: #0284c7; font-size: 20px; text-transform: uppercase; }
    .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 13px; margin-bottom: 20px; }
    .meta-item { display: flex; flex-direction: column; }
    .meta-label { font-weight: bold; color: #64748b; font-size: 11px; text-transform: uppercase; }
    .meta-value { font-size: 13px; color: #0f172a; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
    th, td { border: 1px solid #cbd5e1; padding: 8px 12px; }
    th { background: #f8fafc; font-weight: bold; text-transform: uppercase; font-size: 11px; color: #475569; }
    .total-row { font-weight: bold; font-size: 14px; background: #f1f5f9; }
    .footer { margin-top: 40px; display: flex; justify-content: space-between; font-size: 12px; color: #475569; }
    .sign-box { text-align: center; width: 180px; }
    .sign-box .sign-title { font-weight: bold; margin-bottom: 60px; }
    @media print {
      body { padding: 0; }
      @page { margin: 15mm; }
    }
  </style>
</head>
<body>
  <div class="header">
    <h2>Hóa Đơn Dịch Vụ Buồng Phòng</h2>
    <div style="font-size: 12px; color: #64748b;">Mã hóa đơn: #${invoice.id}</div>
  </div>

  <div class="meta-grid">
    <div class="meta-item"><span class="meta-label">Mã ĐK</span><span class="meta-value">${invoice.regCode || '—'}</span></div>
    <div class="meta-item"><span class="meta-label">Phòng</span><span class="meta-value">${invoice.room ? 'Phòng ' + invoice.room : '—'}</span></div>
    <div class="meta-item"><span class="meta-label">Khách hàng</span><span class="meta-value">${invoice.guestName || '—'}</span></div>
    <div class="meta-item"><span class="meta-label">Ngày giờ</span><span class="meta-value">${invoice.time || '—'}</span></div>
    <div class="meta-item"><span class="meta-label">Dịch vụ</span><span class="meta-value">${invoice.service || invoice.dept || '—'}</span></div>
    <div class="meta-item"><span class="meta-label">Người lập</span><span class="meta-value">${invoice.user || '—'}</span></div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="text-align:left">Sản phẩm</th>
        <th style="text-align:center;width:80px">Số lượng</th>
        <th style="text-align:right;width:120px">Đơn giá</th>
        <th style="text-align:right;width:140px">Thành tiền</th>
      </tr>
    </thead>
    <tbody>
      ${rows}
      <tr class="total-row">
        <td colspan="3" style="text-align:right">Tổng cộng:</td>
        <td style="text-align:right">${formatCurrency(invoice.price)}</td>
      </tr>
    </tbody>
  </table>

  <div class="footer">
    <div class="sign-box">
      <div class="sign-title">Khách hàng</div>
      <div>(Ký và ghi rõ họ tên)</div>
    </div>
    <div class="sign-box">
      <div class="sign-title">Nhân viên thực hiện</div>
      <div>(Ký và ghi rõ họ tên)</div>
    </div>
  </div>
</body>
</html>`

  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()
  printWindow.focus()
  setTimeout(() => {
    printWindow.print()
  }, 350)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape') {
    if (cancelTarget.value) {
      closeCancelModal()
    } else if (detailTarget.value) {
      closeInvoiceDetail()
    } else if (showDatePicker.value) {
      showDatePicker.value = false
    } else if (showAddModal.value) {
      showAddModal.value = false
    }
  }
}

onMounted(() => {
  document.addEventListener('click', closeClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<style scoped>
/* Scoped custom variables and transitions */
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
.btn-primary:active {
  transform: translateY(0);
}

/* Modal transition */
.hk-modal-enter-active {
  animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-modal-leave-active {
  animation: hkModalOut 0.2s ease-in forwards;
}
.hk-drawer-enter-active,
.hk-drawer-leave-active {
  transition: opacity 0.25s ease;
}
.hk-drawer-enter-active > div:last-child,
.hk-drawer-leave-active > div:last-child {
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-drawer-enter-from,
.hk-drawer-leave-to {
  opacity: 0;
}
.hk-drawer-enter-from > div:last-child,
.hk-drawer-leave-to > div:last-child {
  transform: translateX(100%);
}
@keyframes hkModalIn {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
@keyframes hkModalOut {
  from {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
  to {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
}

/* Dropdown transition */
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


/* A cancelled bill is shown as its positive and reversal entries. Keep every
   label visually distinct from a normal bill: red, italic, and never bold. */
.invoice-deleted td,
.invoice-deleted td * {
  color: #dc2626 !important;
  font-style: italic;
  font-weight: 400 !important;
}
</style>
