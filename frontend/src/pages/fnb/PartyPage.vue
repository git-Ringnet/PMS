<template>
  <div class="flex flex-col h-[calc(100vh-48px)] bg-slate-50 overflow-hidden font-sans relative">
    <!-- Top Tab Switcher -->
    <div class="flex border-b border-slate-200 bg-white px-6 pt-3 shrink-0 shadow-sm z-10">
      <button 
        v-for="tab in tabs" 
        :key="tab.key" 
        @click="activeTab = tab.key"
        :class="activeTab === tab.key ? 'border-sky-500 text-sky-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700'"
        class="px-5 py-2.5 border-b-2 font-bold text-xs cursor-pointer transition-all uppercase tracking-wider h-[38px] flex items-center justify-center"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Active Tab: Danh sách đặt tiệc -->
    <div v-if="activeTab === 'list'" class="flex-1 flex flex-col overflow-hidden">
      <!-- Toolbar / Filters -->
      <div class="flex items-center justify-between gap-4 py-3 px-6 shrink-0 flex-wrap bg-white border-b border-slate-200 shadow-xs">
        <div class="flex items-center gap-2.5 flex-wrap">
          <DateRangePicker v-model="dateRange" />
          
          <select 
            v-model="statusFilter"
            class="py-1 px-2.5 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 bg-white focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-50 h-[32px] w-[140px] cursor-pointer"
          >
            <option value="ALL">Tất cả trạng thái</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="serving">Đang phục vụ</option>
            <option value="completed">Đã hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
          
          <button 
            @click="toggleCancelled" 
            :class="showCancelled ? 'bg-rose-50 border-rose-500 text-rose-600' : 'border-rose-300 text-rose-500 hover:bg-rose-50'"
            class="px-3.5 py-1 border rounded-lg text-xs font-bold transition-all cursor-pointer h-[32px] flex items-center justify-center gap-1.5"
          >
            <span class="w-2 h-2 rounded-full bg-rose-500" v-if="showCancelled"></span>
            Tiệc đã hủy
          </button>
        </div>
        
        <button 
          @click="openAddPartyModal" 
          class="bg-[#54b4eb] hover:bg-[#43a1d9] text-white px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all cursor-pointer shadow-sm h-[32px]"
        >
          <span class="text-base font-bold leading-none">+</span>
          Thêm
        </button>
      </div>

      <!-- Table Container -->
      <div class="flex-1 overflow-auto bg-white scrollbar-thin">
        <table class="w-full text-[13px] text-left border-collapse min-w-[1600px]">
          <thead class="sticky top-0 z-20 bg-slate-50 border-b border-slate-200 text-[13px]">
            <tr class="text-slate-600 font-bold">
              <th class="py-3 px-3 w-10 text-center border-r border-slate-200 relative"></th>
              <th :style="colWidths.code ? { width: colWidths.code + 'px', minWidth: colWidths.code + 'px' } : {}" class="py-3 px-3 w-24 border-r border-slate-200 relative group">
                Mã Tiệc
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'code')"></div>
              </th>
              <th :style="colWidths.name ? { width: colWidths.name + 'px', minWidth: colWidths.name + 'px' } : {}" class="py-3 px-3 min-w-[180px] border-r border-slate-200 relative group">
                Tên Tiệc
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'name')"></div>
              </th>
              <th :style="colWidths.date ? { width: colWidths.date + 'px', minWidth: colWidths.date + 'px' } : {}" class="py-3 px-3 w-32 border-r border-slate-200 relative group">
                Ngày Đến
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'date')"></div>
              </th>
              <th :style="colWidths.time ? { width: colWidths.time + 'px', minWidth: colWidths.time + 'px' } : {}" class="py-3 px-3 w-32 border-r border-slate-200 relative group">
                Thời Gian
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'time')"></div>
              </th>
              <th :style="colWidths.customer ? { width: colWidths.customer + 'px', minWidth: colWidths.customer + 'px' } : {}" class="py-3 px-3 min-w-[200px] border-r border-slate-200 relative group">
                Tên Khách
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'customer')"></div>
              </th>
              <th :style="colWidths.company ? { width: colWidths.company + 'px', minWidth: colWidths.company + 'px' } : {}" class="py-3 px-3 min-w-[150px] border-r border-slate-200 relative group">
                Công Ty
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'company')"></div>
              </th>
              <th :style="colWidths.location ? { width: colWidths.location + 'px', minWidth: colWidths.location + 'px' } : {}" class="py-3 px-3 min-w-[300px] border-r border-slate-200 relative group">
                Địa Điểm Phục Vụ
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'location')"></div>
              </th>
              <th :style="colWidths.tables ? { width: colWidths.tables + 'px', minWidth: colWidths.tables + 'px' } : {}" class="py-3 px-3 w-20 text-center border-r border-slate-200 relative group">
                SL Bàn
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'tables')"></div>
              </th>
              <th :style="colWidths.guests ? { width: colWidths.guests + 'px', minWidth: colWidths.guests + 'px' } : {}" class="py-3 px-3 w-36 text-center border-r border-slate-200 relative group">
                SL Khách (NL/TE)
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'guests')"></div>
              </th>
              <th :style="colWidths.extra ? { width: colWidths.extra + 'px', minWidth: colWidths.extra + 'px' } : {}" class="py-3 px-3 w-24 text-center border-r border-slate-200 relative group">
                Phát Sinh
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'extra')"></div>
              </th>
              <th :style="colWidths.subCount ? { width: colWidths.subCount + 'px', minWidth: colWidths.subCount + 'px' } : {}" class="py-3 px-3 w-24 text-center border-r border-slate-200 relative group">
                SL Tiệc CT
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'subCount')"></div>
              </th>
              <th :style="colWidths.total ? { width: colWidths.total + 'px', minWidth: colWidths.total + 'px' } : {}" class="py-3 px-3 w-36 text-right border-r border-slate-200 relative group">
                Tổng tiền
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'total')"></div>
              </th>
              <th :style="colWidths.deposit ? { width: colWidths.deposit + 'px', minWidth: colWidths.deposit + 'px' } : {}" class="py-3 px-3 w-36 text-right border-r border-slate-200 relative group">
                Tổng tiền cọc
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'deposit')"></div>
              </th>
              <th :style="colWidths.status ? { width: colWidths.status + 'px', minWidth: colWidths.status + 'px' } : {}" class="py-3 px-3 w-36 text-center border-r border-slate-200 relative group">
                Trạng thái
                <div class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-sky-400 z-10 group-hover:bg-slate-300" @mousedown.prevent="startResize($event, 'status')"></div>
              </th>
              <th class="py-3 px-3 w-24 text-center relative">Hành Động</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="party in paginatedParties" :key="party.code">
              <!-- Main Row -->
              <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer" @dblclick="editParty(party)">
                <td 
                  class="py-2 px-3 text-center border-r border-slate-100 cursor-pointer text-slate-400 hover:text-sky-600 transition-colors" 
                  @click="party.expanded = !party.expanded"
                >
                  <span class="inline-block transition-transform text-[8px]" :class="party.expanded ? 'rotate-90' : ''">▶</span>
                </td>
                <td class="py-2 px-3 border-r border-slate-100 font-bold text-sky-600 cursor-pointer hover:underline" @click="editParty(party)">{{ party.code }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-slate-800 font-semibold">{{ party.name }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-slate-600">{{ party.arrivalDate }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-slate-600 font-medium text-center">{{ party.time }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-slate-800 font-semibold">{{ party.customer }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-slate-600">{{ party.company }}</td>
                <td class="py-2.5 px-3 border-r border-slate-100 text-left text-slate-600 font-medium">
                  <div v-for="sub in party.subParties" :key="sub.id" class="whitespace-nowrap text-[13px] leading-tight mb-1 last:mb-0">
                    <span class="font-bold text-slate-700">{{ sub.outlet || '?' }}</span>
                    <span v-if="sub.location"> - {{ sub.location }}</span>
                    <span v-if="sub.arrivalTime" class="text-slate-400"> · {{ sub.arrivalTime }}{{ sub.departureTime ? ' - ' + sub.departureTime : '' }}</span>
                  </div>
                  <div v-if="!party.subParties || party.subParties.length === 0" class="text-slate-400 text-center font-bold">—</div>
                </td>
                <td class="py-2 px-3 border-r border-slate-100 text-center text-slate-800 font-bold">{{ party.tablesCount }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-center text-slate-800 font-semibold">{{ party.guestsCount }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-center text-slate-400 font-bold">{{ party.additionalCost }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-center text-slate-800 font-bold">{{ party.detailsCount }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-right text-emerald-700 font-extrabold">{{ formatCurrency(party.totalAmount) }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-right text-amber-700 font-extrabold">{{ formatCurrency(party.depositAmount) }}</td>
                <td class="py-2 px-3 border-r border-slate-100 text-center">
                  <span 
                    :class="getStatusBadgeClass(party.status)" 
                    class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold tracking-wide uppercase inline-block shadow-xs border"
                  >
                    {{ getStatusLabel(party.status) }}
                  </span>
                </td>
                <td class="py-2 px-3 text-center">
                  <button @click="editParty(party)" class="text-sky-600 hover:underline font-bold bg-transparent border-none cursor-pointer">Chi tiết</button>
                </td>
              </tr>
              <!-- Expanded Detail Row -->
              <tr v-show="party.expanded" class="bg-slate-50/50">
                <td class="py-2 px-3 border-r border-slate-100"></td>
                <td colspan="15" class="p-3.5 border-b border-slate-200">
                  <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm text-slate-700 flex flex-col gap-3 max-w-4xl">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                      <span class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                        <span class="w-1.5 h-3 bg-sky-500 rounded-sm"></span>
                        Thông tin chi tiết thực đơn đặt tiệc
                      </span>
                      <span class="text-[10px] bg-sky-50 border border-sky-100 text-sky-700 px-2 py-0.5 rounded font-bold">Thực đơn chi tiết ({{ party.detailsCount }})</span>
                    </div>
                    <div class="grid grid-cols-3 gap-6 text-xs">
                      <div><strong class="text-slate-400 font-semibold block uppercase text-[10px] tracking-wider mb-0.5">Tiêu đề tiệc:</strong> <span class="font-semibold text-slate-800">{{ party.name }}</span></div>
                      <div><strong class="text-slate-400 font-semibold block uppercase text-[10px] tracking-wider mb-0.5">Thời gian đến:</strong> <span class="font-semibold text-slate-800">{{ party.arrivalDate }}</span></div>
                      <div><strong class="text-slate-400 font-semibold block uppercase text-[10px] tracking-wider mb-0.5">Thông tin liên hệ:</strong> <span class="font-semibold text-slate-800">{{ party.customer }}</span></div>
                    </div>
                    <div class="pt-1.5 text-xs">
                      <strong class="text-slate-400 font-semibold block uppercase text-[10px] tracking-wider mb-2">Chi tiết phục vụ thực đơn:</strong>
                      <div v-if="party.subParties && party.subParties.length" class="flex flex-col gap-2">
                        <div v-for="sub in party.subParties" :key="sub.id" class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 flex flex-col gap-1">
                          <div class="flex items-center gap-2 font-bold text-slate-700 text-[11px] mb-1">
                            <span class="bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded">{{ sub.bookingCode }}</span>
                            <span v-if="sub.outlet || sub.location" class="text-slate-500">· {{ sub.outlet }} {{ sub.location ? '- ' + sub.location : '' }}</span>
                            <span v-if="sub.arrivalTime" class="text-slate-500">· {{ sub.arrivalTime }} {{ sub.departureTime ? '- ' + sub.departureTime : '' }}</span>
                          </div>
                          <span class="font-medium text-slate-600 italic leading-relaxed text-[11px]">
                            {{ (sub.menuItems || []).map(item => `${item.name} (x${item.quantity})`).join(', ') || 'Chưa có món ăn' }}
                          </span>
                        </div>
                      </div>
                      <span v-else class="font-medium text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-100 block italic leading-relaxed">
                        Chưa có thực đơn
                      </span>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <!-- Empty state -->
            <tr v-if="filteredParties.length === 0">
              <td colspan="16" class="py-16 text-center text-slate-400 bg-white">
                <div class="flex flex-col items-center justify-center gap-3">
                  <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center shadow-inner border border-slate-100 text-slate-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-slate-800 font-bold text-sm mb-0.5">Chưa có tiệc/sự kiện</h3>
                    <p class="text-xs text-slate-500 max-w-sm">Không tìm thấy dữ liệu tiệc nào khớp với điều kiện lọc của bạn.</p>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bottom Pagination/Toolbar -->
      <div class="flex items-center justify-between py-2 px-6 border-t border-slate-200 shrink-0 text-[11px] bg-slate-50 shadow-inner">
        <div class="flex gap-2">
          <button @click="expandAll(true)" class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 font-bold shadow-2xs cursor-pointer transition-all">Mở rộng tất cả</button>
          <button @click="expandAll(false)" class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 font-bold shadow-2xs cursor-pointer transition-all">Thu gọn tất cả</button>
          <span class="self-center ml-3 text-slate-500 font-bold">Trang {{ currentPage }}/{{ totalPages }} - Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }}–{{ Math.min(currentPage * itemsPerPage, filteredParties.length) }}/{{ filteredParties.length }}</span>
        </div>
        <div class="flex items-center gap-4 text-slate-600">
          <div class="bg-sky-50 border border-sky-100 text-sky-700 px-3 py-1 rounded-lg font-extrabold">
            Tổng cộng: <span>{{ filteredParties.length }}</span>
          </div>
          <select v-model="itemsPerPage" @change="handleItemsPerPageChange" class="border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-700 font-semibold focus:outline-none cursor-pointer">
            <option :value="20">20 / trang</option>
            <option :value="50">50 / trang</option>
          </select>
          <div class="flex gap-1">
            <button @click="prevPage" :disabled="currentPage === 1" :class="{'cursor-not-allowed text-slate-400': currentPage === 1, 'text-slate-600 hover:bg-slate-100 cursor-pointer': currentPage > 1}" class="w-6.5 h-6.5 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition-colors">
              <span class="text-sm">‹</span>
            </button>
            <button class="w-6.5 h-6.5 flex items-center justify-center border border-sky-500 rounded-lg bg-white text-sky-600 font-extrabold">
              <span>{{ currentPage }}</span>
            </button>
            <button @click="nextPage" :disabled="currentPage === totalPages" :class="{'cursor-not-allowed text-slate-400': currentPage === totalPages, 'text-slate-600 hover:bg-slate-100 cursor-pointer': currentPage < totalPages}" class="w-6.5 h-6.5 flex items-center justify-center border border-slate-200 rounded-lg bg-white transition-colors">
              <span class="text-sm">›</span>
            </button>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="font-bold">Đến</span>
            <input type="text" @keyup.enter="goToPage" class="w-9 border border-slate-200 rounded-lg py-0.5 text-center bg-white focus:outline-none text-[11px] font-bold" />
          </div>
        </div>
      </div>
    </div>

    <!-- Active Tab: Lịch đặt tiệc -->
    <div v-if="activeTab === 'calendar'" class="flex-1 flex flex-col overflow-hidden bg-white">
      <!-- Calendar Filters (Matching exactly image 1, 2, 3) -->
      <div class="flex items-center justify-between gap-4 py-3 px-6 shrink-0 flex-wrap border-b border-slate-200 bg-white">
        <div class="flex items-center gap-2.5 flex-wrap">
          <!-- Date Picker Button (changes label based on calendarSubView) -->
          <div v-if="calendarSubView === 'range'" class="flex items-center gap-2">
            <input type="date" v-model="calendarFilters.rangeStartDate" class="border border-slate-200 rounded-lg bg-slate-50 px-2 py-1 text-xs font-bold text-gray-700 h-[32px] cursor-pointer outline-none focus:border-sky-500" />
            <span class="text-slate-400 text-xs font-bold">→</span>
            <input type="date" v-model="calendarFilters.rangeEndDate" class="border border-slate-200 rounded-lg bg-slate-50 px-2 py-1 text-xs font-bold text-gray-700 h-[32px] cursor-pointer outline-none focus:border-sky-500" />
          </div>
          <div v-else class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 px-3 py-1 shadow-sm h-[32px] cursor-pointer overflow-hidden hover:bg-slate-100 transition-colors">
            <span class="text-gray-700 text-xs font-bold mr-1.5">{{ getCalendarDateLabel }}</span>
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <input v-if="calendarSubView === 'month'" type="month" v-model="nativeDateValue" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
            <input v-else-if="calendarSubView === 'week' || calendarSubView === 'list'" type="week" v-model="nativeDateValue" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
            <input v-else type="date" v-model="nativeDateValue" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
          </div>

          <!-- Search Input -->
          <div class="relative w-56 h-[32px]">
            <input 
              type="text" 
              v-model="calendarFilters.search"
              placeholder="Tìm: mã, tên booking, khách, SĐT..."
              class="w-full h-full border border-slate-200 rounded-lg pl-3 pr-2 text-xs font-semibold text-slate-700 focus:outline-none focus:border-sky-500 bg-slate-50/50"
            />
          </div>

          <!-- Select controls -->
          <select v-model="calendarFilters.outlet" class="py-1 px-2 border border-slate-200 rounded-lg text-xs font-semibold text-slate-500 bg-white focus:outline-none h-[32px] cursor-pointer">
            <option value="">Tất cả outlet</option>
            <option v-for="out in outletOptions" :key="out.id" :value="out.code">{{ out.name }}</option>
          </select>
          <select v-model="calendarFilters.area" :disabled="!calendarFilters.outlet" :class="!calendarFilters.outlet ? 'bg-slate-100 cursor-not-allowed opacity-60' : 'bg-white cursor-pointer'" class="py-1 px-2 border border-slate-200 rounded-lg text-xs font-semibold text-slate-500 focus:outline-none h-[32px]">
            <option value="">Tất cả khu vực</option>
            <option v-for="area in filteredAreaOptions" :key="area.id" :value="area.name">{{ area.name }}</option>
          </select>
          <select v-model="calendarFilters.status" class="py-1 px-2 border border-slate-200 rounded-lg text-xs font-semibold text-slate-500 bg-white focus:outline-none h-[32px] cursor-pointer">
            <option value="ALL">Tất cả trạng thái</option>
            <option value="confirmed">Đã xác nhận</option>
            <option value="serving">Đang phục vụ</option>
            <option value="completed">Đã hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>

        <!-- View Switchers (Tháng, Tuần, Ngày, Danh sách, Khoảng) -->
        <div class="flex items-center gap-3">
          <div class="flex items-center p-0.5 bg-slate-100 rounded-lg text-xs font-bold border border-slate-200">
            <button 
              @click="calendarSubView = 'month'"
              :class="calendarSubView === 'month' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
              class="px-3.5 py-1 rounded-md transition-all cursor-pointer text-[11px]"
            >
              Tháng
            </button>
            <button 
              @click="calendarSubView = 'week'"
              :class="calendarSubView === 'week' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
              class="px-3.5 py-1 rounded-md transition-all cursor-pointer text-[11px]"
            >
              Tuần
            </button>
            <button 
              @click="calendarSubView = 'day'"
              :class="calendarSubView === 'day' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
              class="px-3.5 py-1 rounded-md transition-all cursor-pointer text-[11px]"
            >
              Ngày
            </button>
            <button
              @click="calendarSubView = 'list'"
              :class="calendarSubView === 'list' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
              class="px-3.5 py-1 rounded-md transition-all cursor-pointer text-[11px]"
            >
              Danh sách
            </button>
            <button
              @click="calendarSubView = 'range'"
              :class="calendarSubView === 'range' ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
              class="px-3.5 py-1 rounded-md transition-all cursor-pointer text-[11px]"
            >
              Khoảng
            </button>
          </div>
          <span class="text-slate-700 text-xs font-bold whitespace-nowrap">{{ getStatsLabel }}</span>
        </div>
      </div>

      <!-- Quick time navigator toolbar -->
      <div class="flex items-center justify-between py-2.5 px-6 border-b border-slate-150 bg-white">
        <div class="flex gap-1 items-center">
          <button @click="navigateCalendar(-1)" class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-lg bg-white text-slate-600 hover:bg-slate-50 cursor-pointer font-bold">‹</button>
          <button @click="navigateCalendar(1)" class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-lg bg-white text-slate-600 hover:bg-slate-50 cursor-pointer font-bold">›</button>
          <button @click="navigateToday" class="px-3.5 py-1 bg-sky-500 hover:bg-sky-600 text-white text-xs font-bold rounded-lg cursor-pointer transition-all border border-sky-600 shadow-sm ml-1.5 h-[28px] flex items-center justify-center">Hôm nay</button>
        </div>
        <h2 class="text-sm font-extrabold text-slate-800 tracking-wide uppercase">{{ getCalendarPeriodTitle }}</h2>
        <div class="w-[120px]"></div> <!-- Spacer to center title -->
      </div>

      <!-- CALENDAR SUBVIEW 1: MONTH VIEW -->
      <div v-if="calendarSubView === 'month'" class="flex-1 overflow-y-auto scrollbar-thin">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 font-extrabold text-slate-700 text-[11px] py-2 text-center uppercase tracking-wide">
          <div>Thứ 2</div>
          <div>Thứ 3</div>
          <div>Thứ 4</div>
          <div>Thứ 5</div>
          <div>Thứ 6</div>
          <div class="text-blue-600">Thứ 7</div>
          <div class="text-rose-600">CN</div>
        </div>
        <div class="grid grid-cols-7 text-slate-700 border-collapse">
          <div 
            v-for="cell in monthCells" 
            :key="cell.id" 
            class="border-r border-b border-slate-200 min-h-[140px] p-2 flex flex-col gap-1.5 relative hover:bg-slate-50/30 transition-colors"
            @dragover.prevent
            @drop="onDrop($event, cell)"
          >
            <!-- Day Number -->
            <div class="flex justify-between items-center">
              <span 
                :class="[
                  cell.isCurrentMonth ? 'text-slate-800' : 'text-slate-300 font-normal',
                  cell.isToday ? 'bg-sky-500 text-white w-5 h-5 rounded-full flex items-center justify-center font-bold shadow-xs border border-sky-600' : 'font-bold'
                ]"
                class="text-xs"
              >
                {{ cell.dayNum }}
              </span>
            </div>

            <!-- Nested Bookings list in Month view (matching image 1) -->
            <div class="flex flex-col gap-2.5 overflow-y-auto max-h-[120px] scrollbar-none mt-1">
              <div v-for="b in cell.bookings" :key="b.code" 
                :class="['border border-slate-200 rounded-lg p-2 bg-white shadow-2xs hover:shadow-md transition-shadow relative', getStatusBorderClass(b.status)]"
                :draggable="b.status === 'confirmed'"
                @dragstart="onDragStart($event, b)"
                @dblclick.stop="editParty(b)"
                @mouseenter="showHover($event, b)"
                @mousemove="updateHover($event)"
                @mouseleave="hideHover"
              >
                <!-- Header of the booking -->
                <div class="flex items-center justify-between mb-1.5 gap-1 cursor-grab active:cursor-grabbing">
                  <div class="flex gap-1 items-center min-w-0">
                    <span :class="getStatusBadgeBgClass(b.status)" class="px-1.5 py-0.5 rounded text-[8px] font-extrabold text-white shrink-0 shadow-2xs uppercase">
                      {{ b.code }}
                    </span>
                    <span class="font-extrabold text-slate-800 text-[11px] truncate">{{ b.customer }}</span>
                  </div>
                  <div class="flex items-center gap-1 shrink-0 text-[9px] font-bold">
                    <span class="text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded-full border border-slate-100 whitespace-nowrap">{{ b.itemsCount }} tiệc</span>
                    <span class="text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full border border-amber-100 font-extrabold whitespace-nowrap">{{ formatNumber(b.totalAmount) }} đ</span>
                  </div>
                </div>
                <!-- Sub events detail list inside cell -->
                <div class="flex flex-col gap-1.5 border-t border-slate-50 pt-1.5">
                  <div v-for="sub in b.subEvents" :key="sub.id" class="text-[9px] text-slate-600 leading-tight">
                    <div class="flex items-center gap-1 mb-1">
                      <span class="text-emerald-500 font-extrabold select-none shrink-0 text-[12px] leading-none mb-0.5">•</span>
                      <span class="bg-slate-400 text-white px-1 py-0.5 rounded text-[8px] font-extrabold">{{ sub.id }}</span>
                      <span class="font-bold text-slate-700">{{ sub.time ? sub.time.replace(' - ', ' \u2192 ') : '' }}</span>
                      <span class="font-bold text-slate-500">{{ sub.tables }} bàn</span>
                      <span class="font-extrabold text-amber-700 ml-auto">{{ formatNumber(sub.price) }} đ</span>
                    </div>
                    <div class="flex items-center gap-1 ml-2 mt-0.5">
                      <span class="text-slate-700 font-bold text-[10px] truncate">{{ sub.guest }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CALENDAR SUBVIEW 2: WEEK VIEW (Matching image 2) -->
      <div v-else-if="calendarSubView === 'week'" class="flex-1 overflow-auto scrollbar-thin">
        <table class="w-full text-xs text-left border-collapse border border-slate-200 min-w-[1200px] h-full">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold text-center">
              <th class="py-3 px-3 w-32 border-r border-slate-250 uppercase font-extrabold text-[10px] tracking-wider bg-amber-50/50">Điểm bán hàng</th>
              <th class="py-3 px-3 w-32 border-r border-slate-200 uppercase font-extrabold text-[10px] tracking-wider bg-slate-50">Khu vực</th>
              <!-- Days columns -->
              <th 
                v-for="d in weekDays" 
                :key="d.date"
                :class="d.isToday ? 'bg-[#e3f2fd] text-sky-700 font-extrabold border-sky-200' : 'bg-amber-50/30'"
                class="py-2.5 px-3 border-r border-slate-200 min-w-[140px] text-center"
              >
                <div class="text-[10px] text-slate-400 font-semibold uppercase mb-0.5">{{ d.dayLabel }}</div>
                <div class="text-xs font-bold">{{ d.date }}</div>
                <div v-if="d.isToday" class="text-[8px] bg-sky-500 text-white rounded px-1.5 py-0.5 inline-block font-extrabold tracking-wide mt-1 uppercase shadow-2xs">Hôm nay</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <!-- Loop through sales points & areas -->
            <template v-for="(sp, spIdx) in weekSalesPoints" :key="sp.name">
              <tr 
                v-for="(area, areaIdx) in sp.areas" 
                :key="area"
                class="border-b border-slate-300"
              >
                <!-- Sales Point Column (span multiple rows) -->
                <td 
                  v-if="areaIdx === 0" 
                  :rowspan="sp.areas.length"
                  :class="sp.bgClass"
                  class="py-3 px-4 font-bold text-center border-r border-slate-250 text-slate-800 text-[11px] uppercase tracking-wide shadow-xs shrink-0"
                >
                  {{ sp.name }}
                </td>
                
                <!-- Area Column -->
                <td :class="sp.areaBgClass" class="py-3 px-4 font-bold border-r border-slate-300 text-slate-700 text-[11px] uppercase shrink-0">
                  {{ area }}
                </td>

                <!-- Render week cells for each day -->
                <td 
                  v-for="d in weekDays" 
                  :key="'cell-'+area+'-'+d.date"
                  :class="d.isToday ? 'bg-sky-50/10' : ''"
                  class="p-2 border-r border-slate-300 min-h-[120px] align-top"
                >
                  <div class="flex flex-col gap-2">
                    <div 
                      v-for="card in getWeekCards(area, d.date)" 
                      :key="card.subId || card.id"
                      :class="['border border-slate-200 bg-white rounded-lg p-2 shadow-2xs hover:shadow-md transition-shadow flex flex-col gap-1.5 text-[10px] text-slate-700 cursor-pointer relative', getStatusBorderClass(card.status)]"
                      @dblclick.stop="editParty(card)"
                      @mouseenter="showHover($event, card)"
                      @mousemove="updateHover($event)"
                      @mouseleave="hideHover"
                    >
                      <!-- Card Time & Header -->
                      <div class="flex items-center justify-between border-b border-slate-50 pb-1">
                        <span class="font-extrabold text-slate-800">{{ card.time }}</span>
                        <span :class="getStatusBadgeBgClass(card.status)" class="px-1.5 py-0.5 rounded text-[8px] font-extrabold text-white shadow-2xs uppercase shrink-0">
                          {{ card.displayCode || card.bookingCode }}
                        </span>
                      </div>
                      
                      <!-- Booking Name -->
                      <div v-if="card.bookingName" class="font-bold text-slate-800 text-[11px] truncate mt-0.5" :title="card.bookingName">
                        {{ card.bookingName }}
                      </div>

                      <!-- Card Guest & Phone -->
                      <div class="font-bold text-slate-700 flex items-center justify-between gap-1">
                        <div class="flex items-center gap-1 truncate">
                          <span class="text-slate-400">👤</span>
                          <span class="truncate" :title="card.customer">{{ card.customer }}</span>
                        </div>
                        <div v-if="card.pax" class="text-slate-500 text-[9px] shrink-0 font-semibold bg-slate-50 px-1 py-0.5 rounded">
                          {{ card.pax }} khách
                        </div>
                      </div>
                      
                      <div v-if="card.phone" class="flex items-center gap-1 text-[9px] font-semibold text-slate-500">
                        <span class="text-slate-400">📞</span>
                        <span>{{ card.phone }}</span>
                      </div>

                      <!-- Card Table / Room No -->
                      <div class="flex justify-between items-center text-[9px] font-semibold text-slate-500 bg-slate-50 p-1 rounded border border-slate-100/50 gap-1 mt-1 shrink-0 truncate">
                        <span>Bàn: <strong class="text-slate-800">{{ card.table }}</strong></span>
                        <span class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded border border-amber-100 font-extrabold text-[10px] shrink-0">{{ card.roomCode }}</span>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- CALENDAR SUBVIEW 3: DAY VIEW (Matching image 3) -->
      <div v-else-if="calendarSubView === 'day'" class="flex-1 overflow-auto scrollbar-thin">
        <div class="flex flex-col min-w-[1200px] min-h-full relative">
          <!-- Timeline Header Row -->
          <div class="flex border-b border-slate-200 bg-slate-50 shrink-0 h-10 align-middle sticky top-0 z-20">
            <div class="w-32 border-r border-slate-250 shrink-0 font-extrabold text-[10px] text-slate-600 text-center uppercase flex items-center justify-center bg-amber-50/50">Điểm bán hàng</div>
            <div class="w-32 border-r border-slate-200 shrink-0 font-extrabold text-[10px] text-slate-600 text-center uppercase flex items-center justify-center bg-slate-50">Khu vực</div>
            <!-- Timeline hours column headers -->
            <div class="flex-1 flex text-[10px] font-bold text-slate-400 items-center justify-between px-2.5">
              <div v-for="hour in hours" :key="hour" class="text-center w-12 font-bold">{{ hour }}</div>
            </div>
          </div>

          <!-- Timeline Grid Body -->
          <div class="flex-1 flex flex-col relative">
            <!-- Red line wrapper (aligned to time track) -->
            <div class="absolute top-0 bottom-0 left-64 right-0 pointer-events-none z-30">
              <div 
                v-if="isTodaySelected"
                class="absolute top-0 bottom-0 border-l-2 border-red-500 flex flex-col items-center"
                :style="{ left: currentTimeLeft }"
              >
                <div class="bg-red-500 text-white text-[8px] px-1.5 py-0.5 rounded font-extrabold shadow-sm z-40 select-none sticky top-0" style="transform: translateX(-50%); margin-top: -10px;">
                  {{ currentTimeString }}
                </div>
              </div>
            </div>

            <!-- Loop through sales points & areas -->
            <template v-for="sp in weekSalesPoints" :key="sp.name">
              <div class="flex border-b border-slate-200 relative items-stretch">
                <!-- Outlet Label Column (Merged) -->
                <div :class="sp.bgClass" class="w-32 border-r border-slate-300 shrink-0 font-extrabold text-[11px] uppercase tracking-wide flex flex-col items-center justify-center text-center">
                  {{ sp.name }}
                </div>
                
                <div class="flex-1 flex flex-col">
                  <div 
                    v-for="(area, idx) in sp.areas" 
                    :key="area"
                    class="flex min-h-[140px] relative items-stretch border-slate-200"
                    :class="{ 'border-b': idx !== sp.areas.length - 1 }"
                  >
                    <!-- Area Label Column -->
                    <div :class="sp.areaBgClass" class="w-32 border-r border-slate-300 shrink-0 font-bold text-slate-700 text-[11px] uppercase flex items-center justify-center text-center">
                      {{ area }}
                    </div>

                    <!-- Grid Hour Columns Background lines -->
                    <div class="flex-1 flex relative items-stretch">
                      <div v-for="h in hours" :key="'line-'+h" class="w-[5.88%] border-r border-slate-300 h-full shrink-0 pointer-events-none"></div>
                      
                      <!-- Plotted cards absolutely based on hours -->
                      <div 
                        v-for="card in getDayCards(area)" 
                        :key="card.subId || card.id"
                        :style="getDayCardStyle(card.startHour, card.endHour)"
                        :class="['absolute top-3 bottom-3 border bg-white rounded-lg p-2.5 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between text-[10px] text-slate-700 min-w-0 overflow-hidden cursor-pointer', getStatusBorderClass(card.status)]"
                        @dblclick.stop="editParty(card)"
                        @mouseenter="showHover($event, card)"
                        @mousemove="updateHover($event)"
                        @mouseleave="hideHover"
                      >
                        <div class="flex items-center justify-between border-b border-slate-100 pb-1 shrink-0">
                          <div class="flex items-center gap-1.5 truncate">
                            <span class="font-extrabold text-slate-800 truncate">{{ card.time }}</span>
                            <span :class="card.status === 'completed' ? 'text-sky-600' : (card.status === 'serving' ? 'text-amber-600' : (card.status === 'cancelled' ? 'text-rose-600' : 'text-emerald-600'))" class="uppercase font-extrabold text-[8px] truncate">
                              [{{ card.status === 'completed' ? 'HT' : (card.status === 'serving' ? 'ĐPV' : (card.status === 'cancelled' ? 'HUỶ' : 'XN')) }}]
                            </span>
                          </div>
                          <span class="text-[10px] font-black text-sky-700 bg-sky-100 px-1.5 py-0.5 rounded truncate max-w-[90px]" :title="card.displayCode">{{ card.displayCode }}</span>
                        </div>
                        
                        <div v-if="card.bookingName" class="font-bold text-slate-800 text-[11px] truncate mt-0.5 shrink-0" :title="card.bookingName">
                          {{ card.bookingName }}
                        </div>

                        <div class="font-bold text-slate-700 flex items-center justify-between gap-1 mt-0.5 shrink-0">
                          <div class="flex items-center gap-1 truncate">
                            <span class="text-slate-400 shrink-0">👤</span>
                            <span class="truncate" :title="card.customer">{{ card.customer }}</span>
                          </div>
                          <div v-if="card.pax" class="text-slate-500 text-[9px] shrink-0 font-semibold bg-slate-50 px-1 py-0.5 rounded">
                            {{ card.pax }} khách
                          </div>
                        </div>

                        <div v-if="card.phone" class="flex items-center gap-1 text-[9px] font-semibold text-slate-500 shrink-0">
                          <span class="text-slate-400">📞</span>
                          <span>{{ card.phone }}</span>
                        </div>

                        <div class="flex justify-between items-center text-[9px] font-semibold text-slate-500 bg-slate-50 p-1 rounded border border-slate-100/50 gap-1 shrink-0 truncate">
                          <span class="truncate">Bàn: <strong class="text-slate-800">{{ card.table }}</strong></span>
                          <span class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded border border-amber-100 font-extrabold text-[10px] shrink-0">{{ card.roomCode }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- CALENDAR SUBVIEW 4: LIST VIEW (Danh sách - Matching image 1) -->
      <div v-else-if="calendarSubView === 'list'" class="flex-1 overflow-y-auto scrollbar-thin">
        <div v-for="group in listViewGroups" :key="group.outlet">
          <!-- Outlet header row -->
          <div class="flex items-center justify-between px-6 py-2.5 bg-rose-100 border-b border-rose-200 sticky top-0 z-10">
            <span class="font-extrabold text-rose-800 uppercase text-xs tracking-wide">{{ group.outlet }}</span>
            <span class="text-[10px] font-bold text-rose-700 bg-rose-200 px-2.5 py-0.5 rounded-full border border-rose-300">{{ group.totalItems }} tiệc</span>
          </div>

          <!-- Sub-groups by area -->
          <div v-for="areaGroup in group.areas" :key="areaGroup.area">
            <!-- Area sub-header -->
            <div class="flex items-center justify-between px-8 py-1.5 bg-amber-50/70 border-b border-slate-200">
              <span class="font-bold text-slate-600 text-xs uppercase">{{ areaGroup.area }}</span>
              <span class="text-[10px] font-bold text-slate-500">{{ areaGroup.items.length }}</span>
            </div>

            <!-- Event rows -->
            <div
              v-for="item in areaGroup.items"
              :key="item.id"
              @dblclick="editParty(item.originalParty)"
              class="flex items-center gap-4 px-8 py-2 border-b border-slate-100 hover:bg-slate-50 transition-colors text-xs cursor-pointer"
            >
              <!-- Green dot -->
              <span class="w-2 h-2 rounded-full shrink-0" :class="item.dotColor"></span>

              <!-- Date -->
              <span class="text-slate-500 font-semibold w-24 shrink-0">{{ item.date }}</span>

              <!-- Time -->
              <span class="text-slate-700 font-bold w-24 shrink-0">{{ item.time }}</span>

              <!-- Booking ID badge -->
              <span :class="getBookingTagClass(item.bookingCode)" class="px-2 py-0.5 rounded text-[9px] font-extrabold text-white shadow-2xs uppercase shrink-0">
                {{ item.id }}
              </span>

              <!-- Customer name -->
              <span class="font-bold text-slate-800 flex-1 min-w-0 truncate">{{ item.customer }}</span>

              <!-- Right info -->
              <div class="flex items-center gap-3 shrink-0 text-[10px] font-semibold text-slate-500">
                <span>♦ {{ item.outlet }} - {{ item.area }}</span>
                <span class="flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                  {{ item.tables }} bàn
                </span>
                <span class="flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  {{ item.guests }} khách
                </span>
                <span
                  :class="{
                    'bg-emerald-100 text-emerald-700 border-emerald-300': item.status === 'confirmed',
                    'bg-amber-100 text-amber-700 border-amber-300': item.status === 'serving',
                    'bg-sky-100 text-sky-700 border-sky-300': item.status === 'completed'
                  }"
                  class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase border tracking-wide"
                >
                  {{ item.status === 'confirmed' ? 'ĐÃ XÁC NHẬN' : item.status === 'serving' ? 'ĐANG PHỤC VỤ' : 'HOÀN THÀNH' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CALENDAR SUBVIEW 5: RANGE VIEW (Khoảng - Matching image 2) -->
      <div v-else-if="calendarSubView === 'range'" class="flex-1 overflow-auto scrollbar-thin">
        <table class="text-xs border-collapse w-full min-w-[1000px]">
          <thead class="sticky top-0 z-20">
            <tr class="bg-slate-50 border-b border-slate-200">
              <!-- Booking info column header -->
              <th class="py-2.5 px-4 text-left font-extrabold text-slate-600 uppercase text-[10px] tracking-wider border-r border-slate-200 w-56 bg-slate-50">
                Booking - {{ rangeBookings.length }} dòng
              </th>
              <!-- Day column headers -->
              <th
                v-for="d in rangeDays"
                :key="d.dateKey"
                :class="d.isToday ? 'bg-[#e3f2fd] text-sky-700' : 'bg-amber-50/40 text-slate-600'"
                class="py-2 px-2 text-center font-bold text-[10px] border-r border-slate-200 min-w-[110px]"
              >
                <div class="font-extrabold uppercase" :class="d.dayColor">{{ d.dayLabel }}</div>
                <div class="text-sm font-extrabold">{{ d.dayNum }}</div>
                <div class="text-[9px] text-slate-400 font-semibold">{{ d.month }}/{{ d.year }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="booking in rangeBookings"
              :key="booking.code"
              class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors"
            >
              <!-- Booking left sidebar cell -->
              <td class="py-3 px-4 border-r border-slate-200 align-top">
                <div class="flex items-center gap-2 mb-1">
                  <span :class="getBookingTagClass(booking.code)" class="px-2 py-0.5 rounded text-[9px] font-extrabold text-white shadow-2xs uppercase">
                    {{ booking.code }}
                  </span>
                  <span class="font-extrabold text-slate-800 truncate text-xs">{{ booking.name }}</span>
                </div>
                <div class="text-[10px] text-slate-500 font-semibold truncate">{{ booking.customer }}</div>
                <div class="text-[10px] text-slate-400 font-bold mt-0.5">{{ booking.itemsCount }} tiệc</div>
              </td>
              <!-- Day cells for this booking row -->
              <td
                v-for="d in rangeDays"
                :key="booking.code + '-' + d.dateKey"
                :class="d.isToday ? 'bg-sky-50/20' : ''"
                class="py-2 px-2 border-r border-slate-200 align-top min-h-[56px]"
              >
                <!-- Show event pill if this booking has an event on this day -->
                <div
                  v-for="ev in getRangeEvents(booking.code, d.fullDateStr)"
                  :key="ev.id"
                  :class="getRangeEventBg(booking.code)"
                  class="rounded px-2 py-1.5 text-[9px] font-bold flex items-center gap-1 border shadow-2xs mb-1 cursor-pointer hover:opacity-90 transition-opacity"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                  <span>{{ ev.time }}</span>
                  <span class="font-extrabold truncate">{{ ev.id }}</span>
                  <span class="opacity-70">{{ ev.tables }} bàn</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Active Tab: Lên tiệc -->
    <div v-if="activeTab === 'pipeline'" class="flex-1 flex flex-col overflow-hidden bg-slate-50">

      <!-- Stats Bar -->
      <div class="flex shrink-0 border-b border-slate-200 bg-white">
        <div class="flex-1 flex items-center gap-3 px-5 py-3.5 border-r border-slate-200 border-l-4 border-l-sky-500">
          <span class="text-2xl font-extrabold text-sky-600">{{ todayParties.length }}</span>
          <span class="text-xs text-slate-500 font-semibold leading-tight">Tiệc hôm nay</span>
        </div>
        <div class="flex-1 flex items-center gap-3 px-5 py-3.5 border-r border-slate-200 border-l-4 border-l-purple-500">
          <span class="text-2xl font-extrabold text-purple-600">{{ servingCount }}</span>
          <span class="text-xs text-slate-500 font-semibold leading-tight">Đang phục vụ</span>
        </div>
        <div class="flex-1 flex items-center gap-3 px-5 py-3.5 border-r border-slate-200 bg-amber-50 border-l-4 border-l-amber-400">
          <span class="text-2xl font-extrabold text-amber-600">{{ upcomingCount }}</span>
          <span class="text-xs text-amber-700 font-semibold leading-tight">Sắp tới giờ (≤1h)</span>
        </div>
        <div class="flex-1 flex items-center gap-3 px-5 py-3.5 border-r border-slate-200 border-l-4 border-l-rose-500">
          <span class="text-2xl font-extrabold text-rose-600">{{ overdueCount }}</span>
          <span class="text-xs text-slate-500 font-semibold leading-tight">Quá giờ chưa xong</span>
        </div>
        <div class="flex-1 flex items-center gap-3 px-5 py-3.5 border-l-4 border-l-sky-300">
          <span class="text-2xl font-extrabold text-slate-600">{{ completedTodayCount }}</span>
          <span class="text-xs text-slate-500 font-semibold leading-tight">Đã hoàn thành</span>
        </div>
      </div>

      <!-- Sub-Tab Bar + Toolbar -->
      <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 shrink-0">
        <!-- Sub tabs -->
        <div class="flex">
          <button
            v-for="sub in pipelineSubTabs"
            :key="sub.key"
            @click="pipelineSubView = sub.key"
            :class="pipelineSubView === sub.key ? 'border-sky-500 text-sky-600 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="px-4 py-2.5 border-b-2 text-xs font-bold cursor-pointer transition-all uppercase tracking-wide"
          >
            {{ sub.label }}
          </button>
        </div>
        <!-- Toolbar -->
        <div class="flex items-center gap-2">
          <div class="relative h-[30px]">
            <input
              type="text"
              placeholder="Tìm: mã, tên booking, khách..."
              class="h-full border border-slate-200 rounded-lg pl-3 pr-2 text-[11px] font-semibold text-slate-700 focus:outline-none focus:border-sky-500 bg-slate-50/50 w-52"
            />
          </div>
          <select class="border border-slate-200 rounded-lg px-2 text-[11px] font-semibold text-slate-500 bg-white focus:outline-none h-[30px] cursor-pointer">
            <option>Tất cả outlet</option>
          </select>
          <select class="border border-slate-200 rounded-lg px-2 text-[11px] font-semibold text-slate-500 bg-white focus:outline-none h-[30px] cursor-pointer">
            <option>Tất cả trạng thái</option>
          </select>
          <button class="flex items-center gap-1.5 bg-sky-500 hover:bg-sky-600 text-white text-[11px] font-extrabold px-3 h-[30px] rounded-lg transition-all shadow-sm cursor-pointer">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Auto 30s
          </button>
        </div>
      </div>

      <!-- Danh sách subview -->
      <div v-if="pipelineSubView === 'list'" class="flex-1 flex overflow-hidden">
        <!-- LEFT PANEL: List -->
        <div class="w-[220px] shrink-0 flex flex-col border-r border-slate-200 bg-white overflow-hidden">
          <!-- Panel Header -->
          <div class="flex items-center justify-between px-3 py-2.5 border-b border-slate-200 bg-slate-50 shrink-0">
            <span class="text-xs font-extrabold text-slate-700">Lên tiệc hôm nay</span>
            <span class="text-[10px] bg-sky-500 text-white px-2 py-0.5 rounded-full font-bold shadow-sm">{{ todayParties.length }}</span>
          </div>
          <!-- Filters -->
          <div class="flex flex-col gap-1.5 px-2 pt-2 pb-1 shrink-0 border-b border-slate-100">
            <select class="border border-slate-200 rounded-lg px-2 text-[10px] font-semibold text-slate-500 bg-white h-[26px] focus:outline-none cursor-pointer w-full">
              <option>Tất cả outlet</option>
            </select>
            <select class="border border-slate-200 rounded-lg px-2 text-[10px] font-semibold text-slate-500 bg-white h-[26px] focus:outline-none cursor-pointer w-full">
              <option>Tất cả khu vực</option>
            </select>
            <select class="border border-slate-200 rounded-lg px-2 text-[10px] font-semibold text-slate-500 bg-white h-[26px] focus:outline-none cursor-pointer w-full">
              <option>Tất cả trạng thái</option>
            </select>
            <input
              type="text"
              placeholder="Tìm: mã, tên booking, khách, SĐT..."
              class="border border-slate-200 rounded-lg px-2 text-[10px] font-semibold text-slate-500 h-[26px] focus:outline-none focus:border-sky-500 w-full bg-slate-50"
            />
          </div>
          <!-- Card List -->
          <div class="flex-1 overflow-y-auto scrollbar-thin flex flex-col gap-1 p-2">
            <div
              v-for="item in todayParties"
              :key="item.id"
              @click="selectedPipelineItem = item"
              :class="selectedPipelineItem?.id === item.id ? 'border-sky-400 bg-sky-50/50 shadow-md' : 'border-slate-200 hover:border-sky-300 hover:bg-slate-50'"
              class="border rounded-xl p-2.5 cursor-pointer transition-all"
            >
              <!-- Badge row -->
              <div class="flex items-center justify-between mb-1.5">
                <span :class="getBookingTagClass(item.bookingCode)" class="px-2 py-0.5 rounded text-[8px] font-extrabold text-white uppercase shadow-2xs">
                  {{ item.id }}
                </span>
                <span
                  :class="{
                    'bg-emerald-100 text-emerald-700 border-emerald-300': item.status === 'confirmed',
                    'bg-amber-100 text-amber-700 border-amber-300': item.status === 'serving',
                    'bg-sky-100 text-sky-700 border-sky-300': item.status === 'completed'
                  }"
                  class="text-[8px] font-extrabold px-2 py-0.5 rounded border uppercase tracking-wide"
                >
                  {{ item.status === 'confirmed' ? 'Đã xác nhận' : item.status === 'serving' ? 'Đang phục vụ' : 'Hoàn thành' }}
                </span>
              </div>
              <!-- Name -->
              <div class="font-extrabold text-slate-800 text-[11px] truncate mb-0.5">{{ item.name }}</div>
              <!-- Time + Outlet -->
              <div class="text-[9px] text-slate-500 font-semibold truncate">{{ item.time }} · {{ item.outlet }} · {{ item.area }}</div>
              <!-- Tables + Price -->
              <div class="flex items-center justify-between mt-1.5 text-[9px] font-bold">
                <span class="text-slate-600">{{ item.tables }} bàn</span>
                <span class="text-emerald-700 font-extrabold">{{ formatCurrency(item.price) }}</span>
              </div>
              <!-- Status tag -->
              <div v-if="item.tag" class="mt-1.5 text-[9px] font-bold text-sky-600">• {{ item.tag }}</div>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL: Detail -->
        <div class="flex-1 flex items-center justify-center bg-slate-50/50">
          <div v-if="!selectedPipelineItem" class="text-center text-slate-400">
            <div class="w-16 h-16 mx-auto mb-3 bg-white rounded-full flex items-center justify-center border border-slate-200 shadow-inner">
              <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
            </div>
            <p class="text-xs font-semibold">Chọn một tiệc để xem chi tiết</p>
          </div>
          <div v-else class="w-full h-full p-6 overflow-y-auto">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 max-w-2xl mx-auto">
              <div class="flex items-start justify-between mb-4 border-b border-slate-100 pb-3">
                <div>
                  <span :class="getBookingTagClass(selectedPipelineItem.bookingCode)" class="px-2.5 py-0.5 rounded text-[10px] font-extrabold text-white uppercase shadow-sm inline-block mb-1.5">
                    {{ selectedPipelineItem.id }}
                  </span>
                  <h3 class="font-extrabold text-slate-800 text-base">{{ selectedPipelineItem.name }}</h3>
                  <p class="text-xs text-slate-500 font-semibold">{{ selectedPipelineItem.time }} · {{ selectedPipelineItem.outlet }} · {{ selectedPipelineItem.area }}</p>
                </div>
                <span
                  :class="{
                    'bg-emerald-100 text-emerald-700 border-emerald-300': selectedPipelineItem.status === 'confirmed',
                    'bg-amber-100 text-amber-700 border-amber-300': selectedPipelineItem.status === 'serving',
                    'bg-sky-100 text-sky-700 border-sky-300': selectedPipelineItem.status === 'completed'
                  }"
                  class="text-[11px] font-extrabold px-3 py-1 rounded-full border uppercase tracking-wide"
                >
                  {{ selectedPipelineItem.status === 'confirmed' ? 'Đã xác nhận' : selectedPipelineItem.status === 'serving' ? 'Đang phục vụ' : 'Hoàn thành' }}
                </span>
              </div>
              <div class="grid grid-cols-2 gap-4 text-xs">
                <div><span class="text-slate-400 block font-semibold uppercase text-[10px] tracking-wider mb-0.5">Số bàn</span><span class="font-bold text-slate-800">{{ selectedPipelineItem.tables }} bàn</span></div>
                <div><span class="text-slate-400 block font-semibold uppercase text-[10px] tracking-wider mb-0.5">Tổng tiền</span><span class="font-extrabold text-emerald-700">{{ formatCurrency(selectedPipelineItem.price) }}</span></div>
                <div><span class="text-slate-400 block font-semibold uppercase text-[10px] tracking-wider mb-0.5">Outlet</span><span class="font-bold text-slate-800">{{ selectedPipelineItem.outlet }}</span></div>
                <div><span class="text-slate-400 block font-semibold uppercase text-[10px] tracking-wider mb-0.5">Khu vực</span><span class="font-bold text-slate-800">{{ selectedPipelineItem.area }}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Kanban subview (existing board) -->
      <div v-else-if="pipelineSubView === 'kanban'" class="flex-1 p-5 overflow-hidden flex flex-col">
        <div class="flex-1 grid grid-cols-4 gap-4 overflow-hidden">
          <div v-for="col in pipelineColumns" :key="col.status" class="bg-slate-100 rounded-xl border border-slate-200/60 p-3 flex flex-col overflow-hidden shadow-2xs">
            <div class="flex items-center justify-between mb-3 shrink-0">
              <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wide flex items-center gap-1.5">
                <span class="w-1.5 h-3.5 rounded-sm" :class="col.color"></span>
                {{ col.label }}
              </span>
              <span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-bold">{{ getPipelineItems(col.status).length }}</span>
            </div>
            <div class="flex-1 overflow-y-auto flex flex-col gap-2.5 p-0.5 scrollbar-thin">
              <div v-for="party in getPipelineItems(col.status)" :key="'pipe-'+party.code" class="bg-white p-3 rounded-lg border border-slate-200 shadow-2xs flex flex-col gap-2 hover:shadow-md transition-shadow cursor-grab">
                <div class="flex items-center justify-between">
                  <span class="text-[9px] font-bold text-sky-600">{{ party.code }}</span>
                  <span class="text-[9px] font-bold text-slate-400">{{ party.arrivalDate }}</span>
                </div>
                <h4 class="text-xs font-bold text-slate-800 line-clamp-1">{{ party.name }}</h4>
                <p class="text-[10px] text-slate-500 font-semibold line-clamp-1 border-t border-slate-50 pt-1.5">Khách: {{ party.customer.split(' - ')[0] }}</p>
                <div class="flex justify-between items-center mt-1 text-[9px] font-bold">
                  <span class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded-md border border-emerald-100">{{ formatCurrency(party.totalAmount) }}</span>
                  <span class="text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded-md border border-slate-100">Bàn: {{ party.tablesCount }}</span>
                </div>
              </div>
              <div v-if="getPipelineItems(col.status).length === 0" class="flex-grow flex items-center justify-center py-10 border-2 border-dashed border-slate-200 rounded-lg text-slate-400 text-xs italic">Trống</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline subview -->
      <div v-else-if="pipelineSubView === 'timeline'" class="flex-1 flex items-center justify-center bg-slate-50 text-slate-400 text-sm font-semibold">
        <div class="text-center">
          <svg class="w-12 h-12 mx-auto mb-3 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Timeline đang phát triển
        </div>
      </div>
    </div>

    <!-- Modals -->
    <AddPartyModal 
      ref="addPartyModalRef"
      :isOpen="isAddPartyModalOpen" 
      :partyId="selectedPartyId"
      @close="closeAddPartyModal" 
      @save="handleSaveParty" 
      @refresh="loadParties"
    />

    <!-- Hover Tooltip -->
    <Teleport to="body">
    <div v-if="hoverInfo.visible && hoverInfo.booking" 
      :style="{ 
        left: Math.min(hoverInfo.x + 15, windowWidth - 360) + 'px', 
        top: hoverInfo.y > windowHeight / 2 ? 'auto' : Math.min(hoverInfo.y + 15, windowHeight - 200) + 'px',
        bottom: hoverInfo.y > windowHeight / 2 ? Math.max(windowHeight - hoverInfo.y + 15, 10) + 'px' : 'auto'
      }" 
      class="fixed z-[9999] bg-[#1e293b] text-white shadow-2xl border border-slate-700 rounded-xl overflow-hidden w-[340px] pointer-events-none flex flex-col"
    >
      <!-- Header -->
      <div class="flex justify-between items-center px-3 py-2 border-b border-slate-700">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full" :class="getStatusBadgeBgClass(hoverInfo.booking.status)"></div>
          <span class="text-[11px] font-bold text-slate-200">{{ hoverInfo.booking.timeStr || '10:00 -> 11:00' }}</span>
        </div>
        <div class="bg-[#84cc16] text-[#1e293b] text-[10px] font-extrabold px-1.5 py-0.5 rounded shadow-2xs">
          {{ hoverInfo.booking.code }}
        </div>
      </div>
      
      <!-- Body (Detailed List) -->
      <div class="p-3 flex flex-col gap-1.5 text-[11px]">
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Tên booking</span>
          <span class="font-bold text-white leading-tight">{{ hoverInfo.booking.name }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Công ty</span>
          <span class="font-bold text-white uppercase leading-tight">{{ hoverInfo.booking.company }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Tên khách</span>
          <span class="font-bold text-white leading-tight">{{ hoverInfo.booking.guestName }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">SĐT</span>
          <span class="font-bold text-white">{{ hoverInfo.booking.phone }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Khách</span>
          <span class="font-bold text-white">{{ hoverInfo.booking.totalGuests }} ({{ hoverInfo.booking.totalAdults }} NL + {{ hoverInfo.booking.totalChildren }} TE)</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Số bàn</span>
          <span class="font-bold text-white">{{ hoverInfo.booking.totalTables }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Outlet</span>
          <span class="font-bold text-white">{{ hoverInfo.booking.outlet }}</span>
        </div>
        <div class="grid grid-cols-[85px_1fr] gap-2 items-start">
          <span class="text-slate-400 font-medium">Trạng thái</span>
          <span class="font-bold" :class="hoverInfo.booking.status === 'confirmed' ? 'text-emerald-400' : (hoverInfo.booking.status === 'serving' ? 'text-amber-400' : (hoverInfo.booking.status === 'completed' ? 'text-sky-400' : 'text-rose-400'))">
            {{ hoverInfo.booking.statusText }}
          </span>
        </div>
      </div>


      
      <!-- Bottom Note -->
      <div class="px-3 py-1.5 border-t border-slate-700 bg-slate-900/50 text-[9px] italic text-slate-400 text-center">
        Nhấn đúp để xem chi tiết - Kéo để dời ngày
      </div>
    </div>
    </Teleport>
    <LoadingOverlay :show="isLoading" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import DateRangePicker from '@/components/DateRangePicker.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const windowWidth = ref(window.innerWidth)
const windowHeight = ref(window.innerHeight)

const onResize = () => {
  windowWidth.value = window.innerWidth
  windowHeight.value = window.innerHeight
}

let timeInterval = null
onMounted(() => {
  window.addEventListener('resize', onResize)
  updateCurrentTime()
  timeInterval = setInterval(updateCurrentTime, 60000)
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  if (timeInterval) clearInterval(timeInterval)
})
import AddPartyModal from './components/party/modals/AddPartyModal.vue'
import { fetchParties, createParty, updateParty, getParty } from '@/services/fb-party-service'
import { fetchOutlets, fetchFbLocations } from '@/services/outlet-service'
// State
const uiStore = useUiStore()
const route = useRoute()
const router = useRouter()
const activeTab = ref(route.query.tab || 'list')
const calendarSubView = ref(route.query.view || 'month')

watch([activeTab, calendarSubView], ([newTab, newView]) => {
  if (newTab !== route.query.tab || newView !== route.query.view) {
    router.push({ query: { ...route.query, tab: newTab, view: newView } })
  }
})

watch(() => route.query, (newQuery) => {
  if (newQuery.tab && newQuery.tab !== activeTab.value) {
    activeTab.value = newQuery.tab
  }
  if (newQuery.view && newQuery.view !== calendarSubView.value) {
    calendarSubView.value = newQuery.view
  }
}, { deep: true })
const today = new Date()
const todayStr = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`
const dateRange = ref({ start: todayStr, end: todayStr })
const isAddPartyModalOpen = ref(false)
const addPartyModalRef = ref(null)

const currentTimeString = ref('')
const currentTimeLeft = ref('0%')
const isTodaySelected = computed(() => {
  const d = calendarFilters.value.currentDate
  if (!d) return false
  const now = new Date()
  return d.getDate() === now.getDate() && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
})

const updateCurrentTime = () => {
  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  currentTimeString.value = `${hours}:${minutes}`
  
  const h = now.getHours()
  const m = now.getMinutes()
  const totalHours = 17
  let decimalHour = h + m / 60
  
  if (decimalHour < 6) decimalHour = 6
  if (decimalHour > 23) decimalHour = 23
  
  const leftPercent = ((decimalHour - 6) / totalHours) * 100
  currentTimeLeft.value = `${leftPercent}%`
}

const handleRouteChange = async (to, from) => {
  if (isAddPartyModalOpen.value && addPartyModalRef.value) {
    if (addPartyModalRef.value.isDirty) {
      const confirmed = await uiStore.confirm({
        title: 'Cảnh báo chưa lưu',
        message: 'Bạn có thay đổi chưa được lưu trong form. Bạn có chắc chắn muốn rời đi và hủy bỏ thay đổi này không?'
      })
      if (!confirmed) {
        return false
      }
    }
    closeAddPartyModal()
  }
  return true
}

onBeforeRouteLeave(handleRouteChange)
onBeforeRouteUpdate(handleRouteChange)

const statusFilter = ref('ALL')
const showCancelled = ref(false)
const parties = ref([])
const isLoading = ref(false)
const selectedPartyId = ref(null)

// Calendar Filters
const calendarFilters = ref({
  search: '',
  outlet: '',
  area: '',
  status: 'ALL',
  currentDate: new Date(),
  rangeStartDate: new Date().toISOString().substring(0, 10),
  rangeEndDate: new Date(Date.now() + 30 * 86400000).toISOString().substring(0, 10)
})

const outletOptions = ref([])
const areaOptions = ref([])

const filteredAreaOptions = computed(() => {
  if (!calendarFilters.value.outlet) return areaOptions.value
  return areaOptions.value.filter(a => a.outlet_code === calendarFilters.value.outlet)
})

watch(() => calendarFilters.value.outlet, () => {
  calendarFilters.value.area = ''
})

const loadFilterOptions = async () => {
  try {
    const [resOutlets, resAreas] = await Promise.all([
      fetchOutlets(),
      fetchFbLocations()
    ])
    outletOptions.value = Array.isArray(resOutlets.data) ? resOutlets.data : (resOutlets.data?.data || [])
    areaOptions.value = Array.isArray(resAreas.data) ? resAreas.data : (resAreas.data?.data || [])
  } catch (err) {
    console.error('Lỗi tải danh sách outlet/area:', err)
  }
}

const openAddPartyModal = () => {
  selectedPartyId.value = null
  isAddPartyModalOpen.value = true
}

const editParty = (party) => {
  selectedPartyId.value = party.id
  isAddPartyModalOpen.value = true
}

const closeAddPartyModal = () => {
  selectedPartyId.value = null
  isAddPartyModalOpen.value = false
}

const formatDateForApi = (dateStr) => {
  if (!dateStr) return null
  if (dateStr.includes('/')) {
    const [d, m, y] = dateStr.split('/')
    return `${y}-${m}-${d}`
  }
  return dateStr
}

const loadParties = async () => {
  isLoading.value = true
  try {
    let filters = {}
    if (activeTab.value === 'list') {
      filters = {
        status: statusFilter.value,
        start_date: dateRange.value.start ? formatDateForApi(dateRange.value.start) : null,
        end_date: dateRange.value.end ? formatDateForApi(dateRange.value.end) : null,
      }
    } else if (activeTab.value === 'calendar') {
      const d = calendarFilters.value.currentDate
      let start = null
      let end = null
      if (calendarSubView.value === 'month') {
        start = new Date(d.getFullYear(), d.getMonth(), 1)
        end = new Date(d.getFullYear(), d.getMonth() + 1, 0)
      } else if (calendarSubView.value === 'week' || calendarSubView.value === 'list') {
        const day = d.getDay()
        const diff = d.getDate() - day + (day === 0 ? -6 : 1)
        start = new Date(d.getFullYear(), d.getMonth(), diff)
        end = new Date(start)
        end.setDate(end.getDate() + 6)
      } else if (calendarSubView.value === 'day') {
        start = new Date(d)
        end = new Date(d)
      } else if (calendarSubView.value === 'range') {
        start = new Date(calendarFilters.value.rangeStartDate || Date.now())
        end = new Date(calendarFilters.value.rangeEndDate || (Date.now() + 30 * 86400000))
        if (start > end) {
          const temp = start;
          start = end;
          end = temp;
        }
      }
      
      filters = {
        status: calendarFilters.value.status,
        search: calendarFilters.value.search,
        outlet_code: calendarFilters.value.outlet,
        fb_location_id: calendarFilters.value.area,
        start_date: start ? `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}` : null,
        end_date: end ? `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}` : null,
      }
    }
    
    const res = await fetchParties(filters)
    let fetchedParties = res.data || []
    
    if (filters.status === 'ALL') {
      fetchedParties = fetchedParties.filter(p => p.status !== 'cancelled')
    }
    
    parties.value = fetchedParties.map(p => ({ ...p, expanded: false }))
  } catch (err) {
    console.error('Lỗi tải danh sách tiệc:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadFilterOptions()
  loadParties()
})

watch([statusFilter, dateRange], () => {
  if (activeTab.value === 'list') loadParties()
}, { deep: true })

let searchTimeout = null
watch(() => calendarFilters.value, () => {
  if (activeTab.value === 'calendar') {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      loadParties()
    }, 400)
  }
}, { deep: true })

watch([activeTab, calendarSubView], () => {
  loadParties()
})

// Lên tiệc state
const pipelineSubView = ref('list')
const selectedPipelineItem = ref(null)

const pipelineSubTabs = [
  { key: 'list', label: 'Danh sách' },
  { key: 'kanban', label: 'Kanban' },
  { key: 'timeline', label: 'Timeline' }
]

// Today's party event items (for Lên tiệc danh sách)
const todayParties = [
  { id: 'AR21-01', bookingCode: 'AR21', name: 'Test', time: '08:53 - 09:53', outlet: 'Nhà Hàng', area: 'NHÀ HÀNG', tables: 1, price: 135000, status: 'confirmed', tag: 'đang trong giờ tiệc' },
  { id: 'AR20-01', bookingCode: 'AR20', name: 'Test', time: '08:53 - 09:53', outlet: 'Nhà Hàng', area: 'NHÀ HÀNG', tables: 1, price: 135000, status: 'confirmed', tag: null },
  { id: 'AR22-01', bookingCode: 'AR22', name: 'Long Test Double', time: '10:04 - 11:04', outlet: 'Nhà Hàng', area: 'Chưa phân khu vực', tables: 1, price: 1200000, status: 'confirmed', tag: null },
  { id: 'AR7-01',  bookingCode: 'AR7',  name: 'thảo', time: '17:05 - 22:05', outlet: 'Nhà Hàng', area: 'NHÀ HÀNG', tables: 1, price: 585000, status: 'confirmed', tag: 'sắp tới giờ (≤1h)' }
]

const servingCount = computed(() => todayParties.filter(p => p.status === 'serving').length)
const upcomingCount = computed(() => todayParties.filter(p => p.tag === 'sắp tới giờ (≤1h)').length)
const overdueCount = computed(() => 0)
const completedTodayCount = computed(() => todayParties.filter(p => p.status === 'completed').length)

const tabs = [
  { key: 'list', label: 'Danh sách đặt tiệc' },
  { key: 'calendar', label: 'Lịch đặt tiệc' },
  { key: 'pipeline', label: 'Lên tiệc' }
]

const pipelineColumns = [
  { status: 'serving', label: 'Đang phục vụ', color: 'bg-amber-400' },
  { status: 'confirmed', label: 'Đã xác nhận', color: 'bg-emerald-500' },
  { status: 'completed', label: 'Đã hoàn thành', color: 'bg-sky-500' },
  { status: 'cancelled', label: 'Đã hủy', color: 'bg-rose-500' }
]

const monthCells = computed(() => {
  const d = calendarFilters.value.currentDate
  const year = d.getFullYear()
  const month = d.getMonth()
  
  const firstDayOfMonth = new Date(year, month, 1)
  const lastDayOfMonth = new Date(year, month + 1, 0)
  
  let firstDayIndex = firstDayOfMonth.getDay() - 1
  if (firstDayIndex === -1) firstDayIndex = 6 // Sunday
  
  const cells = []
  
  const prevMonthLastDay = new Date(year, month, 0).getDate()
  for (let i = firstDayIndex - 1; i >= 0; i--) {
    const dayNum = prevMonthLastDay - i
    cells.push({ id: `prev-${dayNum}`, dayNum, isCurrentMonth: false, bookings: [] })
  }
  
  const today = new Date()
  for (let i = 1; i <= lastDayOfMonth.getDate(); i++) {
    const dateStr = `${String(i).padStart(2, '0')}/${String(month + 1).padStart(2, '0')}/${year}`
    
    const dayBookingsMap = {}
    parties.value.filter(p => p.arrivalDate === dateStr).forEach(p => {
      if (!dayBookingsMap[p.code]) {
        dayBookingsMap[p.code] = {
          id: p.id,
          code: p.code,
          customer: p.customer,
          itemsCount: p.detailsCount || 1,
          totalAmount: p.totalAmount,
          status: p.status,
          subEvents: []
        }
      }
      dayBookingsMap[p.code].subEvents.push({
        id: p.id,
        time: p.time,
        tables: p.tablesCount,
        price: p.totalAmount,
        area: p.area,
        guest: p.customer
      })
    })
    
    cells.push({ 
      id: `current-${i}`, 
      dateStr: dateStr,
      dayNum: i, 
      isCurrentMonth: true, 
      isToday: i === today.getDate() && month === today.getMonth() && year === today.getFullYear(),
      bookings: Object.values(dayBookingsMap)
    })
  }
  
  const remainingCells = 42 - cells.length
  for (let i = 1; i <= remainingCells; i++) {
    cells.push({ id: `next-${i}`, dayNum: i, isCurrentMonth: false, bookings: [] })
  }
  
  return cells
})

// 2. Week View data (image 2)
const weekDays = computed(() => {
  const d = calendarFilters.value.currentDate
  const day = d.getDay()
  const diff = d.getDate() - day + (day === 0 ? -6 : 1)
  const monday = new Date(d.getFullYear(), d.getMonth(), diff)
  
  const days = []
  const dayLabels = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy']
  const today = new Date()
  
  for (let i = 0; i < 7; i++) {
    const cur = new Date(monday)
    cur.setDate(monday.getDate() + i)
    const dateStr = `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')}/${cur.getFullYear()}`
    
    days.push({
      date: dateStr,
      dayLabel: dayLabels[cur.getDay()],
      isToday: cur.getDate() === today.getDate() && cur.getMonth() === today.getMonth() && cur.getFullYear() === today.getFullYear()
    })
  }
  return days
})

const weekSalesPoints = computed(() => {
  if (!outletOptions.value || outletOptions.value.length === 0) return []
  const sps = []
  outletOptions.value.forEach((outlet, index) => {
    if (calendarFilters.value.outlet && outlet.code !== calendarFilters.value.outlet) return;

    let areas = []
    if (Array.isArray(areaOptions.value)) {
      areas = areaOptions.value.filter(a => a.outlet_code === outlet.code).map(a => a.name)
    }
    
    if (calendarFilters.value.area) {
      areas = areas.filter(a => a === calendarFilters.value.area);
    }
    
    if (areas.length === 0 && !calendarFilters.value.area) areas.push('Chưa phân khu vực')
    if (areas.length === 0 && calendarFilters.value.area) return;
    
    const bgClasses = [
      { sp: 'bg-emerald-50 text-emerald-800', area: 'bg-[#e3f2fd] text-sky-800' },
      { sp: 'bg-amber-50 text-amber-800', area: 'bg-purple-50 text-purple-800' },
      { sp: 'bg-rose-50 text-rose-800', area: 'bg-orange-50 text-orange-800' },
      { sp: 'bg-sky-50 text-sky-800', area: 'bg-blue-50 text-blue-800' }
    ]
    const c = bgClasses[index % bgClasses.length]
    
    sps.push({
      name: outlet.name,
      bgClass: c.sp,
      areaBgClass: c.area,
      areas: areas
    })
  })
  return sps
})

const getSubPartyDynamicStatus = (parentStatus, subStatus, subDateStr, subTimeStr) => {
  if (subStatus === 'completed' || parentStatus === 'completed') return 'completed'
  if (subStatus === 'cancelled' || parentStatus === 'cancelled') return 'cancelled'
  
  if (!subDateStr) return 'confirmed'
  
  let arrivalDate = subDateStr
  if (arrivalDate.includes('/')) {
    const [d, m, y] = arrivalDate.split('/')
    arrivalDate = `${y}-${m}-${d}`
  }

  let startTimeStr = '00:00'
  let endTimeStr = '23:59'

  if (subTimeStr) {
    const parts = subTimeStr.split('-')
    if (parts.length === 2) {
      startTimeStr = parts[0].trim()
      endTimeStr = parts[1].trim()
    }
  }

  const startTime = new Date(`${arrivalDate}T${startTimeStr}:00`).getTime()
  const endTime = new Date(`${arrivalDate}T${endTimeStr}:00`).getTime()
  const now = new Date().getTime()

  if (now > endTime) {
    return 'completed'
  } else if (now >= startTime && now <= endTime) {
    return 'serving'
  }
  return 'confirmed'
}

const parseCustomerInfo = (customerStr) => {
  let guestName = customerStr || 'Khách'
  let phone = ''
  
  if (customerStr && customerStr.includes(' - ')) {
    const parts = customerStr.split(' - ')
    if (parts.length >= 3) {
      guestName = parts[1].trim()
      phone = parts.slice(2).join(' - ').trim()
    } else if (parts.length === 2) {
      guestName = parts[0].trim()
      phone = parts[1].trim()
    }
  }
  return { guestName, phone }
}

const getWeekCards = (area, date) => {
  const cards = []
  parties.value.forEach(p => {
    const subParties = (p.subParties && p.subParties.length > 0) ? p.subParties : [{
      ...p,
      location: p.area,
      code: p.code
    }]
    
    subParties.forEach((sub, idx) => {
      let subDate = sub.arrivalDate || p.arrivalDate
      if (subDate && subDate.includes('-')) {
        const [y, m, d] = subDate.split('-')
        subDate = `${d}/${m}/${y}`
      }
      if (subDate === date && (sub.location || sub.area || p.area || 'Chưa phân khu vực') === area) {
        let timeStr = sub.time || (sub.arrivalTime ? `${sub.arrivalTime} - ${sub.departureTime}` : p.time || '12:00 - 14:00')
        const custInfo = parseCustomerInfo(sub.customer || sub.guest || p.customer)
        const pax = (sub.adults || 0) + (sub.children || 0) || sub.guestsCount || sub.guests || p.guestsCount || p.guests || 1

        cards.push({
          id: p.id,
          subId: sub.id || `${p.id}-${idx}`,
          bookingCode: p.code,
          displayCode: sub.bookingCode || sub.code || p.code,
          time: timeStr,
          bookingName: sub.name || p.name || p.bookingName,
          customer: custInfo.guestName,
          phone: custInfo.phone || sub.phone || p.phone,
          pax: pax,
          table: sub.tables || p.tablesCount || 'A1',
          roomCode: sub.bookingCode || sub.id || p.id,
          status: getSubPartyDynamicStatus(p.status, sub.status, subDate, timeStr),
          menuItems: sub.menuItems || p.menuItems || []
        })
      }
    })
  })
  return cards
}

// 3. Day View timeline details (image 3)
const hours = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00']

// Column resizing logic
const colWidths = ref({})
const resizingCol = ref(null)
const startX = ref(0)
const startWidth = ref(0)

const startResize = (e, colId) => {
  resizingCol.value = colId
  startX.value = e.clientX
  const th = e.target.closest('th')
  startWidth.value = th.getBoundingClientRect().width
  
  document.addEventListener('mousemove', onMouseMove)
  document.addEventListener('mouseup', onMouseUp)
}

const onMouseMove = (e) => {
  if (!resizingCol.value) return
  const diff = e.clientX - startX.value
  colWidths.value[resizingCol.value] = Math.max(50, startWidth.value + diff)
}

const onMouseUp = () => {
  resizingCol.value = null
  document.removeEventListener('mousemove', onMouseMove)
  document.removeEventListener('mouseup', onMouseUp)
}

const getDayCards = (area) => {
  const d = calendarFilters.value.currentDate
  const dateStr = `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
  
  const cards = []
  parties.value.forEach(p => {
    const subParties = (p.subParties && p.subParties.length > 0) ? p.subParties : [{
      ...p,
      location: p.area,
      code: p.code
    }]
    
    subParties.forEach((sub, idx) => {
      let subDate = sub.arrivalDate || p.arrivalDate
      if (subDate && subDate.includes('-')) {
        const [y, m, d] = subDate.split('-')
        subDate = `${d}/${m}/${y}`
      }
      if (subDate === dateStr && (sub.location || sub.area || p.area || 'Chưa phân khu vực') === area) {
        let timeStr = sub.time || (sub.arrivalTime ? `${sub.arrivalTime} - ${sub.departureTime}` : p.time || '12:00 - 14:00')
        
        let startHour = 6
        let endHour = 7
        const parts = timeStr.split(' - ')
        if (parts.length === 2) {
          const s = parts[0].split(':')
          const e = parts[1].split(':')
          startHour = parseInt(s[0] || 6) + parseInt(s[1] || 0)/60
          endHour = parseInt(e[0] || 7) + parseInt(e[1] || 0)/60
        }
        
        const custInfo = parseCustomerInfo(sub.customer || sub.guest || p.customer)
        const pax = (sub.adults || 0) + (sub.children || 0) || sub.guestsCount || sub.guests || p.guestsCount || p.guests || 1

        cards.push({
          id: p.id,
          subId: sub.id || `${p.id}-${idx}`,
          bookingCode: p.code,
          displayCode: sub.bookingCode || sub.code || p.code,
          time: timeStr,
          startHour,
          endHour,
          bookingName: sub.name || p.name || p.bookingName,
          customer: custInfo.guestName,
          phone: custInfo.phone || sub.phone || p.phone,
          pax: pax,
          table: sub.tables || p.tablesCount || 'A1',
          roomCode: sub.bookingCode || sub.id || p.id,
          status: getSubPartyDynamicStatus(p.status, sub.status, subDate, timeStr),
          menuItems: sub.menuItems || p.menuItems || []
        })
      }
    })
  })
  return cards
}

const getDayCardStyle = (start, end) => {
  // Timeline starts at 06:00, which maps to 0% offset.
  // Each hour takes up 100% / 17
  const totalHours = 17
  const safeStart = Math.max(6, Math.min(23, start))
  const safeEnd = Math.max(6, Math.min(23, end))
  
  const leftPercent = ((safeStart - 6) / totalHours) * 100
  const widthPercent = ((safeEnd - safeStart) / totalHours) * 100
  return {
    left: `${leftPercent}%`,
    width: `${widthPercent}%`,
    zIndex: 10
  }
}

const nativeDateValue = computed({
  get: () => {
    const d = calendarFilters.value.currentDate
    if (!d) return ''
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    if (calendarSubView.value === 'month') return `${y}-${m}`
    if (calendarSubView.value === 'week' || calendarSubView.value === 'list') {
      const dateCopy = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
      const dayNum = dateCopy.getUTCDay() || 7
      dateCopy.setUTCDate(dateCopy.getUTCDate() + 4 - dayNum)
      const yearStart = new Date(Date.UTC(dateCopy.getUTCFullYear(), 0, 1))
      const week = Math.ceil((((dateCopy - yearStart) / 86400000) + 1) / 7)
      return `${dateCopy.getUTCFullYear()}-W${String(week).padStart(2, '0')}`
    }
    return `${y}-${m}-${day}`
  },
  set: (val) => {
    if (val) {
      if (val.length === 7 && !val.includes('W')) {
        calendarFilters.value.currentDate = new Date(`${val}-01`)
      } else if (val.includes('W')) {
        const [y, w] = val.split('-W')
        const simple = new Date(y, 0, 1 + (w - 1) * 7)
        const dayOfWeek = simple.getDay()
        const diff = simple.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1)
        calendarFilters.value.currentDate = new Date(simple.getFullYear(), simple.getMonth(), diff)
      } else {
        calendarFilters.value.currentDate = new Date(val)
      }
    }
  }
})

const navigateToday = () => {
  calendarFilters.value.currentDate = new Date()
}

const getStatusBadgeBgClass = (status) => {
  if (status === 'confirmed') return 'bg-emerald-500'
  if (status === 'serving') return 'bg-amber-500'
  if (status === 'completed') return 'bg-sky-500'
  if (status === 'cancelled') return 'bg-rose-500'
  return 'bg-slate-500'
}

const getStatusBorderClass = (status) => {
  if (status === 'confirmed') return 'border-l-4 border-l-emerald-500'
  if (status === 'serving') return 'border-l-4 border-l-amber-500'
  if (status === 'completed') return 'border-l-4 border-l-sky-500'
  if (status === 'cancelled') return 'border-l-4 border-l-rose-500'
  return 'border-l-4 border-l-slate-500'
}

const hoverInfo = ref({
  visible: false,
  x: 0,
  y: 0,
  booking: null
})

const showHover = (e, item) => {
  const code = item.bookingCode || item.code
  if (!code) {
    hoverInfo.value = { visible: true, x: e.clientX, y: e.clientY, booking: item }
    return
  }
  
  const relatedParties = parties.value.filter(p => (p.code === code || p.bookingCode === code))
  if (!relatedParties.length) {
    hoverInfo.value = { visible: true, x: e.clientX, y: e.clientY, booking: item }
    return
  }
  
  const parent = relatedParties[0]
  
  // Parse customer string: "Company - Name - Phone"
  let company = 'KHÁCH LẺ'
  let guestName = item.customer || parent.customer || parent.name || 'Khách'
  let phone = item.phone || 'Chưa cập nhật'
  
  if (!item.customer && parent.customer && parent.customer.includes(' - ')) {
    const parts = parent.customer.split(' - ')
    if (parts.length >= 3) {
      company = parts[0]
      guestName = parts[1]
      phone = parts.slice(2).join(' - ')
    } else if (parts.length === 2) {
      guestName = parts[0]
      phone = parts[1]
    }
  }

  const statusMap = {
    'confirmed': 'Đã xác nhận',
    'serving': 'Đang phục vụ',
    'completed': 'Đã hoàn thành',
    'cancelled': 'Đã hủy'
  }
  const displayStatus = item.status || parent.status || 'confirmed'
  const statusText = statusMap[displayStatus] || 'Đã xác nhận'

  // Use subParties array if it exists, otherwise fall back to matching parties
  const rawSubEvents = (parent.subParties && parent.subParties.length > 0) ? parent.subParties : relatedParties
  
  const itemsCount = parent.detailsCount || parent.itemsCount || rawSubEvents.length
  
  let totalGuests = item.pax || 0
  let totalTables = item.table || 0
  let totalAdults = item.pax || 0
  let totalChildren = 0
  
  if (!totalGuests) {
    rawSubEvents.forEach(p => {
      totalGuests += (p.guestsCount || p.guests || 1)
      totalAdults += (p.adults || p.guestsCount || p.guests || 1)
      totalChildren += (p.children || 0)
    })
  }
  
  if (!totalTables) {
    rawSubEvents.forEach(p => {
      totalTables += (p.tablesCount || p.tables || 1)
    })
  }

  // Format time display
  let timeStr = item.time || parent.time || '10:00 - 11:00'
  if (parent.arrivalDate && timeStr.includes(' - ')) {
    const dStr = parent.arrivalDate.substring(0, 5)
    const parts = timeStr.split(' - ')
    if (parts.length === 2) {
      timeStr = `${dStr} ${parts[0].trim()} \u2192 ${dStr} ${parts[1].trim()}`
    }
  }

  hoverInfo.value = {
    visible: true,
    x: e.clientX,
    y: e.clientY,
    booking: {
      code: item.displayCode || item.bookingCode || code, // Show sub-event code if available
      name: item.bookingName || parent.name || 'Tiệc',
      customer: guestName,
      company,
      guestName,
      phone,
      status: displayStatus,
      statusText,
      itemsCount,
      totalGuests,
      totalTables,
      totalAdults,
      totalChildren,
      outlet: item.outlet || item.area || parent.outlet || parent.area || 'Nhà Hàng',
      timeStr,
      menuItems: item.menuItems || parent.menuItems || []
    }
  }
}

const updateHover = (e) => {
  if (hoverInfo.value.visible) {
    hoverInfo.value.x = e.clientX
    hoverInfo.value.y = e.clientY
  }
}

const hideHover = () => {
  hoverInfo.value.visible = false
}

// DRAG AND DROP CALENDAR
const draggedParty = ref(null)

const onDragStart = (e, party) => {
  hideHover()
  if (party.status !== 'confirmed') {
    e.preventDefault()
    return
  }
  draggedParty.value = party
}

const onDrop = async (e, cell) => {
  if (!draggedParty.value) return
  if (!cell.isCurrentMonth || !cell.dateStr) return
  
  const targetDateStr = cell.dateStr
  const [day, month, year] = targetDateStr.split('/')
  const targetDate = new Date(`${year}-${month}-${day}`)
  targetDate.setHours(0, 0, 0, 0)
  
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  if (targetDate < today) {
    uiStore.alert('Không thể chuyển tiệc về ngày trong quá khứ!')
    draggedParty.value = null
    return
  }

  if (targetDate.getTime() === today.getTime()) {
    const subEvents = draggedParty.value.subEvents || [];
    if (subEvents.length > 0) {
      let earliestTime = null;
      subEvents.forEach(sub => {
        if (sub.time) {
          const startTimeStr = sub.time.split('-')[0].trim();
          if (!earliestTime || startTimeStr < earliestTime) {
            earliestTime = startTimeStr;
          }
        }
      });
      if (earliestTime) {
        const [hour, min] = earliestTime.split(':');
        const earliestDateTime = new Date();
        earliestDateTime.setHours(parseInt(hour, 10), parseInt(min, 10), 0, 0);
        
        if (new Date() > earliestDateTime) {
          uiStore.alert('Giờ hiện tại đã qua giờ bắt đầu sớm nhất của tiệc. Không thể chuyển!');
          draggedParty.value = null;
          return;
        }
      }
    }
  }

  try {
    const subEvents = draggedParty.value.subEvents
    if (subEvents && subEvents.length > 0) {
      const promises = subEvents.map(async sub => {
        const res = await getParty(sub.id)
        const fullParty = res.data?.data
        if (!fullParty) return null
        
        fullParty.arrivalDate = targetDateStr
        if (fullParty.subParties && fullParty.subParties.length > 0) {
          fullParty.subParties.forEach(sp => {
            sp.arrivalDate = targetDateStr
          })
        }
        
        const payload = {
          partyName: fullParty.partyName,
          arrivalDate: formatDateForApi(fullParty.arrivalDate),
          confirmationType: fullParty.confirmationType,
          confirmationDate: fullParty.confirmationDate ? formatDateForApi(fullParty.confirmationDate) : null,
          saleStaff: fullParty.saleStaff,
          company: fullParty.company,
          customer: fullParty.customer,
          email: fullParty.email,
          note: fullParty.note,
          vatNote: fullParty.vatNote,
          status: fullParty.status,
          subParties: (fullParty.subParties || []).map(sp => ({
            arrivalDate: formatDateForApi(sp.arrivalDate),
            arrivalTime: sp.arrivalTime,
            departureTime: sp.departureTime,
            adults: sp.adults,
            children: sp.children,
            tables: sp.tables,
            extra: sp.extra,
            outlet: sp.outlet,
            location: sp.location,
            partyType: sp.partyType,
            groupCode: sp.groupCode,
            note: sp.note,
            deposits: (sp.deposits || []).map(dep => ({
              date: formatDateForApi(dep.date),
              method: dep.method,
              amount: Number(dep.amount || 0),
              status: dep.status || 'active',
              note: dep.note
            })),
            menuItems: (sp.menuItems || []).map(item => ({
              product_id: item.product_id,
              name: item.name,
              quantity: Number(item.quantity || 1),
              unit: item.unit,
              price: Number(item.price || 0),
              discount: Number(item.discount || 0),
              note: item.note
            }))
          }))
        }
        return updateParty(sub.id, payload)
      })
      await Promise.all(promises)
      uiStore.alert('Cập nhật ngày tiệc thành công!')
      loadParties()
    }
  } catch (error) {
    console.error(error)
    uiStore.alert('Lỗi khi cập nhật ngày tiệc: ' + (error.response?.data?.message || error.message))
  } finally {
    draggedParty.value = null
  }
}

const navigateCalendar = (step) => {
  const d = new Date(calendarFilters.value.currentDate)
  if (calendarSubView.value === 'month') {
    d.setMonth(d.getMonth() + step)
  } else if (calendarSubView.value === 'week' || calendarSubView.value === 'list') {
    d.setDate(d.getDate() + (step * 7))
  } else if (calendarSubView.value === 'day') {
    d.setDate(d.getDate() + step)
  } else if (calendarSubView.value === 'range') {
    d.setDate(d.getDate() + (step * 30))
  }
  calendarFilters.value.currentDate = d
}

// Calendar Subview computed helper properties
const getCalendarPeriodTitle = computed(() => {
  const d = calendarFilters.value.currentDate
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const y = d.getFullYear()
  const dateStr = `${String(d.getDate()).padStart(2, '0')}/${m}/${y}`
  
  if (calendarSubView.value === 'month') return `Tháng ${d.getMonth() + 1} Năm ${y}`
  if (calendarSubView.value === 'week' || calendarSubView.value === 'list') {
    const dateCopy = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
    const dayNum = dateCopy.getUTCDay() || 7
    dateCopy.setUTCDate(dateCopy.getUTCDate() + 4 - dayNum)
    const yearStart = new Date(Date.UTC(dateCopy.getUTCFullYear(), 0, 1))
    const week = Math.ceil((((dateCopy - yearStart) / 86400000) + 1) / 7)
    return `Tuần ${week} – ${dateCopy.getUTCFullYear()}`
  }
  if (calendarSubView.value === 'range') return `Từ ${dateStr}`
  
  const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy']
  return `${days[d.getDay()]}, ${dateStr}`
})

const getCalendarDateLabel = computed(() => {
  const d = calendarFilters.value.currentDate
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const y = d.getFullYear()
  const dateStr = `${String(d.getDate()).padStart(2, '0')}/${m}/${y}`
  
  if (calendarSubView.value === 'month') return `${m}/${y}`
  if (calendarSubView.value === 'week' || calendarSubView.value === 'list') {
    const dateCopy = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
    const dayNum = dateCopy.getUTCDay() || 7
    dateCopy.setUTCDate(dateCopy.getUTCDate() + 4 - dayNum)
    const yearStart = new Date(Date.UTC(dateCopy.getUTCFullYear(), 0, 1))
    const week = Math.ceil((((dateCopy - yearStart) / 86400000) + 1) / 7)
    return `Tuần ${week} / ${dateCopy.getUTCFullYear()}`
  }
  if (calendarSubView.value === 'range') return `${dateStr} → ...`
  return dateStr
})

const getStatsLabel = computed(() => {
  const numBookings = parties.value.length;
  const numTiecs = parties.value.reduce((acc, p) => acc + (p.subParties ? p.subParties.length : (p.detailsCount || 1)), 0);
  return `${numBookings} booking ${numTiecs} tiệc`;
})

// ---- LIST VIEW DATA (Danh sách - Image 1) ----
const listViewGroups = computed(() => {
  const groupsMap = {}
  
  parties.value.forEach(p => {
    const pairs = [];
    if (p.subParties && p.subParties.length > 0) {
      p.subParties.forEach(sub => {
        pairs.push({
          outlet: sub.outlet || 'Chưa phân outlet',
          area: sub.location || 'Chưa phân khu vực'
        });
      });
    } else {
      pairs.push({ outlet: p.outlet || 'Chưa phân outlet', area: p.area || 'Chưa phân khu vực' });
    }

    const uniquePairs = [];
    const keys = new Set();
    pairs.forEach(pair => {
      const k = pair.outlet + '|' + pair.area;
      if (!keys.has(k)) { keys.add(k); uniquePairs.push(pair); }
    });

    uniquePairs.forEach(pair => {
      const outletName = pair.outlet;
      const areaName = pair.area;

      if (calendarFilters.value.outlet) {
        const selectedOutlet = outletOptions.value.find(o => o.code === calendarFilters.value.outlet);
        if (selectedOutlet && outletName !== selectedOutlet.name) return;
      }
      if (calendarFilters.value.area && areaName !== calendarFilters.value.area) return;

      if (!groupsMap[outletName]) {
        groupsMap[outletName] = { outlet: outletName, totalItems: 0, areasMap: {} }
      }
      const group = groupsMap[outletName]
      
      if (!group.areasMap[areaName]) {
        group.areasMap[areaName] = { area: areaName, items: [] }
      }
      const areaGroup = group.areasMap[areaName]
      
      let dotColor = 'bg-slate-500'
      if (p.status === 'confirmed') dotColor = 'bg-emerald-500'
      else if (p.status === 'serving') dotColor = 'bg-amber-500'
      else if (p.status === 'completed') dotColor = 'bg-sky-500'
      else if (p.status === 'cancelled') dotColor = 'bg-rose-500'
      
      areaGroup.items.push({
        id: p.id,
        bookingCode: p.code,
        date: p.arrivalDate,
        time: p.time || '12:00 - 14:00',
        customer: p.customer,
        outlet: outletName,
        area: areaName,
        tables: p.tablesCount || 1,
        guests: p.guestsCount || 1,
        status: p.status,
        dotColor,
        originalParty: p
      })
    })
  })
  
  Object.values(groupsMap).forEach(g => {
    let total = 0;
    Object.values(g.areasMap).forEach(a => total += a.items.length);
    g.totalItems = total;
  });

  return Object.values(groupsMap).map(g => ({
    outlet: g.outlet,
    totalItems: g.totalItems,
    areas: Object.values(g.areasMap)
  }))
})

// ---- RANGE VIEW DATA (Khoảng - Image 2) ----
const rangeDays = computed(() => {
  const start = new Date(calendarFilters.value.rangeStartDate || Date.now())
  const end = new Date(calendarFilters.value.rangeEndDate || (Date.now() + 30 * 86400000))
  
  // ensure start <= end
  let realStart = start
  let realEnd = end
  if (start > end) {
    realStart = end
    realEnd = start
  }
  
  const days = []
  const dayLabels = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']
  const today = new Date()
  
  const diffTime = Math.abs(realEnd - realStart)
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  // limit to max 90 days to avoid performance issues
  const totalDays = Math.min(diffDays + 1, 90)
  
  for (let i = 0; i < totalDays; i++) {
    const cur = new Date(realStart)
    cur.setDate(cur.getDate() + i)
    
    const isToday = cur.getDate() === today.getDate() && cur.getMonth() === today.getMonth() && cur.getFullYear() === today.getFullYear()
    let dayColor = ''
    if (cur.getDay() === 0) dayColor = 'text-rose-600'
    else if (cur.getDay() === 6) dayColor = 'text-blue-600'
    if (isToday) dayColor = 'text-sky-700'
    
    days.push({
      dateKey: `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')}`,
      fullDateStr: `${String(cur.getDate()).padStart(2, '0')}/${String(cur.getMonth() + 1).padStart(2, '0')}/${cur.getFullYear()}`,
      matchDate: `${cur.getFullYear()}-${String(cur.getMonth() + 1).padStart(2, '0')}-${String(cur.getDate()).padStart(2, '0')}`,
      dayLabel: dayLabels[cur.getDay()],
      dayNum: cur.getDate(),
      month: String(cur.getMonth() + 1).padStart(2, '0'),
      year: cur.getFullYear(),
      isToday,
      dayColor
    })
  }
  return days
})

const rangeBookings = computed(() => {
  const bookingsMap = {}
  parties.value.forEach(p => {
    if (!bookingsMap[p.code]) {
      bookingsMap[p.code] = {
        code: p.code,
        name: p.name || 'Tiệc',
        customer: p.customer,
        itemsCount: 1
      }
    } else {
      bookingsMap[p.code].itemsCount++
    }
  })
  return Object.values(bookingsMap)
})

const getRangeEvents = (bookingCode, fullDateStr) => {
  return parties.value.filter(p => p.code === bookingCode && p.arrivalDate === fullDateStr).map(p => ({
    id: p.id,
    time: p.time ? p.time.split(' - ')[0] : '12:00',
    tables: p.tablesCount || 1
  }))
}

const getRangeEventBg = (code) => {
  if (!code) return 'bg-slate-100 text-slate-800 border-slate-200'
  const num = Number(code.replace(/\D/g, '')) || 0
  const bgs = [
    'bg-orange-100 text-orange-800 border-orange-200',
    'bg-blue-100 text-blue-800 border-blue-200',
    'bg-purple-100 text-purple-800 border-purple-200',
    'bg-emerald-100 text-emerald-800 border-emerald-200',
    'bg-rose-100 text-rose-800 border-rose-200',
    'bg-sky-100 text-sky-800 border-sky-200',
    'bg-amber-100 text-amber-800 border-amber-200'
  ]
  return bgs[num % bgs.length]
}

// Filtering logic
const filteredParties = computed(() => {
  return parties.value.filter(party => {
    // 1. Handle "Tiệc đã hủy" toggle
    if (showCancelled.value) {
      if (party.status !== 'cancelled') return false
    } else {
      // Default: don't show cancelled unless toggle is on or statusFilter is explicitly set to cancelled
      if (party.status === 'cancelled' && statusFilter.value !== 'cancelled') return false
    }

    // 2. Handle Status Dropdown Filter
    if (statusFilter.value !== 'ALL') {
      if (party.status !== statusFilter.value) return false
    }

    return true
  })
})

const toggleCancelled = () => {
  showCancelled.value = !showCancelled.value
}

// Pagination logic
const currentPage = ref(1)
const itemsPerPage = ref(20)

const totalPages = computed(() => {
  return Math.ceil(filteredParties.value.length / itemsPerPage.value) || 1
})

const paginatedParties = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredParties.value.slice(start, end)
})

const handleItemsPerPageChange = (e) => {
  currentPage.value = 1
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const goToPage = (e) => {
  let val = parseInt(e.target.value)
  if (isNaN(val) || val < 1) val = 1
  if (val > totalPages.value) val = totalPages.value
  currentPage.value = val
  e.target.value = ''
}

watch(filteredParties, () => {
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value || 1
  }
})

// UI methods
const toggleCancelledWithReset = () => {
  showCancelled.value = !showCancelled.value
  if (showCancelled.value) {
    statusFilter.value = 'ALL'
  }
}

const expandAll = (val) => {
  parties.value.forEach(p => {
    p.expanded = val
  })
}

const handleSaveParty = async (partyData) => {
  try {
    const payload = {
      partyName: partyData.partyName,
      arrivalDate: formatDateForApi(partyData.arrivalDate),
      confirmationType: partyData.confirmationType,
      confirmationDate: partyData.confirmationDate ? formatDateForApi(partyData.confirmationDate) : null,
      saleStaff: partyData.saleStaff,
      company: partyData.company,
      customer: partyData.customer,
      email: partyData.email,
      note: partyData.note,
      vatNote: partyData.vatNote,
      subParties: (partyData.subParties || []).map(sub => ({
        arrivalDate: formatDateForApi(sub.arrivalDate),
        arrivalTime: sub.arrivalTime,
        departureTime: sub.departureTime,
        adults: sub.adults,
        children: sub.children,
        tables: sub.tables,
        extra: sub.extra,
        outlet: sub.outlet,
        location: sub.location,
        partyType: sub.partyType,
        groupCode: sub.groupCode,
        note: sub.note,
        deposits: (sub.deposits || []).map(dep => ({
          date: formatDateForApi(dep.date),
          method: dep.method,
          amount: Number(dep.amount || 0),
          note: dep.note
        })),
        menuItems: (sub.menuItems || []).map(item => ({
          product_id: item.product_id,
          name: item.name,
          quantity: Number(item.quantity || 1),
          unit: item.unit,
          price: Number(item.price || 0),
          note: item.note
        }))
      }))
    }

    if (partyData.id) {
      await updateParty(partyData.id, payload)
      closeAddPartyModal()
    } else {
      const res = await createParty(payload)
      const newId = res.data?.id
      if (newId) {
        selectedPartyId.value = newId
      } else {
        closeAddPartyModal()
      }
    }
    await loadParties()
  } catch (err) {
    console.error('Lỗi lưu tiệc:', err)
    uiStore.alert('Lỗi lưu tiệc: ' + (err.response?.data?.message || err.message))
  }
}

// Color coding tag helper
const getBookingTagClass = (code) => {
  // AR3, AR21, AR7, v.v.
  const num = Number(code.replace(/\D/g, ''))
  const colors = [
    'bg-[#ef6c00] text-white', // orange
    'bg-[#1976d2] text-white', // blue
    'bg-[#7b1fa2] text-white', // purple
    'bg-[#388e3c] text-white', // green
    'bg-[#c2185b] text-white', // rose
    'bg-[#0284c7] text-white'  // sky
  ]
  return colors[num % colors.length]
}

// Helpers
const formatCurrency = (val) => {
  if (val == null) return '0 ₫'
  return Number(val).toLocaleString('vi-VN') + ' ₫'
}

const formatNumber = (val) => {
  if (val == null) return '0'
  return Math.round(val).toLocaleString('vi-VN')
}

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'confirmed':
      return 'bg-emerald-50 text-emerald-600 border-emerald-250'
    case 'serving':
      return 'bg-amber-50 text-amber-600 border-amber-250'
    case 'completed':
      return 'bg-sky-50 text-sky-600 border-sky-250'
    case 'cancelled':
      return 'bg-rose-50 text-rose-600 border-rose-250'
    default:
      return 'bg-slate-50 text-slate-600 border-slate-200'
  }
}

const getStatusLabel = (status) => {
  switch (status) {
    case 'confirmed':
      return 'Đã xác nhận'
    case 'serving':
      return 'Đang phục vụ'
    case 'completed':
      return 'Đã hoàn thành'
    case 'cancelled':
      return 'Đã hủy'
    default:
      return 'Không xác định'
  }
}

const getPipelineItems = (status) => {
  return parties.value.filter(p => p.status === status)
}
</script>

<style scoped>
/* Custom slim scrollbars */
.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: #f1f5f9;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
</style>
