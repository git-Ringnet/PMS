<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div class="w-full max-w-5xl bg-white shadow-2xl rounded-2xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]">
        
        <!-- HEADER -->
        <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 border-b border-[#1a2d42]">
            <div class="flex items-center space-x-2">
                <div class="bg-blue-400/20 p-1.5 rounded-lg">
                    <i class="fa-solid fa-file-invoice-dollar text-blue-200 text-xs"></i>
                </div>
                <span class="font-bold text-xs tracking-wide uppercase">Thêm đặt cọc</span>
            </div>
            <button @click="close" class="text-slate-300 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10 cursor-pointer border-none bg-transparent">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- SCROLLABLE CONTENT -->
        <div class="overflow-y-auto p-4 bg-white flex flex-col space-y-3 shrink-0">
            
            <div class="w-1/2 pr-2">
                <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tên đăng ký</label>
                <div class="relative">
                    <select disabled class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs font-medium bg-slate-50 text-slate-800 appearance-none focus:outline-none shadow-sm cursor-not-allowed">
                        <option>{{ bookingName || 'Chưa có tên' }}</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Số tiền <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <input 
                          type="text" 
                          :value="formatCurrencyInput(depositForm.amount)"
                          @input="e => depositForm.amount = cleanCurrencyValue(e.target.value)"
                          class="w-full border border-blue-200 rounded-lg px-3 h-[30px] text-xs font-bold bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm"
                        >
                        <div class="absolute right-1 top-0.5 flex flex-col">
                            <button type="button" @click="depositForm.amount++" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-up"></i></button>
                            <button type="button" @click="depositForm.amount = Math.max(0, depositForm.amount - 1)" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-down"></i></button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Phương thức đặt cọc <span class="text-rose-500">*</span></label>
                    <div class="relative h-[30px]">
                        <select 
                          v-model="depositForm.paymentMethodId"
                          class="w-full border border-blue-200 rounded-lg px-3 h-full text-xs font-medium bg-blue-50/70 text-black appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option :value="null" disabled class="text-slate-400 font-normal bg-slate-100">Phương thức đặt cọc</option>
                            <option v-for="pm in filteredPaymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tài khoản ngân hàng</label>
                    <div class="relative">
                        <select 
                          v-model="depositForm.bankAccountId"
                          class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs bg-white text-slate-800 appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option value="Tài khoản ngân hàng" disabled class="text-slate-400 font-normal bg-slate-100">Tài khoản ngân hàng</option>
                            <option value="Vietcombank - 1012345678">Vietcombank - 1012345678</option>
                            <option value="BIDV - 2012345678">BIDV - 2012345678</option>
                            <option value="Techcombank - 3012345678">Techcombank - 3012345678</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Ngày <span class="text-rose-500">*</span></label>
                    <div class="flex items-center space-x-2 border border-slate-300 rounded-lg px-3 h-[30px] bg-white shadow-sm text-xs font-medium text-slate-800 relative">
                        <input 
                          type="date" 
                          v-model="depositForm.date" 
                          class="date-span-input flex-1 text-left w-full border-none focus:outline-none bg-transparent"
                        />
                        <i class="fa-regular fa-calendar-days text-blue-500 pointer-events-none"></i>
                        <i @click="copyToClipboard(depositForm.date)" class="fa-regular fa-copy text-slate-400 hover:text-slate-600 cursor-pointer"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Mô tả</label>
                    <textarea 
                      v-model="depositForm.note"
                      placeholder="Nhập mô tả..." 
                      class="w-full border border-blue-200 rounded-lg p-2 text-xs font-medium bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm h-[56px] resize-none"
                    ></textarea>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Lưu hình ảnh (Chứng từ / Biên lai)</label>
                    <div class="border border-dashed border-slate-300 rounded-lg h-[56px] bg-slate-50 flex items-center justify-center hover:bg-slate-100 hover:border-blue-400 transition cursor-pointer relative overflow-hidden group shadow-sm">
                        <input v-if="!depositForm.image" :key="fileInputKey" type="file" @change="handleDepositImageUpload" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*">
                        <div class="flex flex-col items-center space-y-1" v-if="!depositForm.image">
                            <i class="fa-solid fa-cloud-arrow-up text-slate-400 group-hover:text-blue-500 transition text-xs"></i>
                            <span class="text-[10px] text-slate-500 font-medium group-hover:text-blue-600 transition">Nhấp để tải ảnh lên hoặc kéo thả vào đây</span>
                        </div>
                        <div class="flex items-center space-x-2 p-1" v-else>
                            <img :src="getImageUrl(depositForm.image)" class="h-10 w-10 object-cover rounded border cursor-pointer hover:opacity-85 transition z-20" @click.stop="openImage(getImageUrl(depositForm.image))" title="Nhấp để xem ảnh lớn" />
                            <div class="flex flex-col z-20">
                                <span class="text-[10px] text-green-600 font-bold">Hình ảnh đã chọn</span>
                                <button type="button" @click.stop="depositForm.image = null; selectedFile = null" class="text-[9px] text-rose-500 hover:text-rose-700 font-semibold underline mt-0.5 border-none bg-transparent cursor-pointer text-left">
                                    Xóa ảnh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE AND LIST -->
        <div class="bg-slate-50 p-4 border-t border-slate-200 flex-1 flex flex-col overflow-y-auto">
            
            <div class="flex justify-between items-end mb-1.5 shrink-0">
                <h3 class="font-bold text-slate-800 text-[11px] uppercase tracking-wider flex items-center">
                    Danh sách đặt cọc <span class="text-rose-500 ml-1">*</span>
                </h3>
                
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-[11px] text-slate-500 font-medium">Hiển thị xoá</span>
                        <button 
                          type="button"
                          @click="showDeleted = !showDeleted"
                          class="relative inline-flex h-4 w-8 shrink-0 cursor-pointer rounded-full border border-transparent transition-colors duration-200 ease-in-out focus:outline-none shadow-inner"
                          :class="showDeleted ? 'bg-blue-600' : 'bg-slate-300'"
                        >
                          <span 
                            class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                            :class="showDeleted ? 'translate-x-4' : 'translate-x-0'"
                          ></span>
                        </button>
                    </div>
                    <button class="text-slate-400 hover:text-blue-600 transition border-none bg-transparent cursor-pointer">
                        <i class="fa-solid fa-sliders text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm mb-1">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                            <th class="p-2 w-10 text-center">
                                <input 
                                  type="checkbox" 
                                  class="rounded border-slate-300 font-normal"
                                  :checked="selectedDepositIds.length === visibleDeposits?.length && visibleDeposits?.length > 0"
                                  @change="selectedDepositIds = $event.target.checked ? visibleDeposits.map(d => d.id) : []"
                                >
                            </th>
                            <th class="p-2 min-w-[80px]">Ngày</th>
                            <th class="p-2 min-w-[60px]">Giờ</th>
                            <th class="p-2 min-w-[130px]">Phương thức thanh toán</th>
                            <th class="p-2 min-w-[150px]">Mô tả</th>
                            <th class="p-2 min-w-[90px] text-right">Số tiền</th>
                            <th class="p-2 min-w-[60px] text-center">Tiền tệ</th>
                            <th class="p-2 min-w-[110px]">Người nhận</th>
                            <th class="p-2 min-w-[100px] text-center">Chứng từ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr 
                          v-for="dep in visibleDeposits" 
                          :key="dep.id" 
                          class="border-b border-slate-100 hover:bg-slate-50/80 transition"
                          :class="{ 'bg-blue-50/30': selectedDepositIds.includes(dep.id) }"
                        >
                            <td class="p-2 text-center align-middle">
                                <input 
                                  type="checkbox" 
                                  :value="dep.id" 
                                  v-model="selectedDepositIds"
                                  class="rounded border-slate-300 font-normal"
                                >
                            </td>
                            <td class="p-2 font-medium text-slate-800 align-middle">{{ dep.date }}</td>
                            <td class="p-2 text-slate-600 align-middle">{{ dep.time }}</td>
                            <td class="p-2 text-slate-800 align-middle">{{ paymentMethods.find(x => x.id === dep.paymentMethodId)?.name || 'BT' }}</td>
                            <td class="p-2 text-slate-600 align-middle">{{ dep.note }}</td>
                            <td class="p-2 text-right font-mono font-semibold text-slate-900 align-middle">{{ dep.amount.toLocaleString('vi-VN') }}</td>
                            <td class="p-2 text-center text-slate-500 align-middle">{{ dep.currency }}</td>
                            <td class="p-2 text-slate-700 font-medium align-middle">{{ dep.recipient }}</td>
                            <td class="p-2 text-center align-middle">
                                <div class="flex items-center justify-center space-x-1.5">
                                    <div 
                                      v-for="(img, iIdx) in (dep.images || [])" 
                                      :key="iIdx"
                                      class="relative group w-7 h-7 rounded border border-slate-200 overflow-hidden shadow-sm bg-white cursor-pointer flex-shrink-0"
                                      @click="openImage(getImageUrl(img))"
                                      title="Nhấp để xem chứng từ"
                                    >
                                        <img :src="getImageUrl(img)" class="w-full h-full object-cover" v-if="img && img !== 'Chứng từ'" />
                                        <div class="w-full h-full flex items-center justify-center bg-slate-100 text-[8px] font-bold text-slate-500 uppercase" v-else>
                                            Ảnh
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!visibleDeposits || visibleDeposits.length === 0" class="border-b border-slate-100">
                            <td colspan="9" class="p-4 text-center text-slate-400 italic">Chưa có thông tin đặt cọc.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FOOTER ACTIONS -->
        <div class="bg-white border-t border-slate-200 p-2.5 px-4 flex justify-between items-center shrink-0">
            
            <div class="flex items-center space-x-2" v-if="!showDeleted">
                <button type="button" @click="splitDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-code-branch text-[10px]"></i>
                    <span>Tách</span>
                </button>
                <button type="button" @click="transferDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                    <span>Chuyển</span>
                </button>
            </div>

            <div class="flex items-center space-x-2 ml-auto">
                <button type="button" @click="deleteDeposits" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                    <span>Xóa</span>
                </button>
                <button type="button" @click="editDeposit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                    <span>Sửa</span>
                </button>
                <button type="button" @click="saveDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-regular fa-floppy-disk text-[10px]"></i>
                    <span>Lưu</span>
                </button>
                <button type="button" @click="addDeposit" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center space-x-1.5 shadow-md text-xs tracking-wide cursor-pointer border-none">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Thêm</span>
                </button>
            </div>
        </div>

        <!-- CUSTOM SPLIT DEPOSIT MODAL -->
        <div v-if="isSplitOpen" class="fixed inset-0 bg-black/60 z-[100000] flex items-center justify-center p-4 backdrop-blur-xs" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col animate-in fade-in duration-200">
                <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2.5 border-b border-[#1a2d42]">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-scissors text-blue-200 text-xs"></i>
                        <span class="font-bold text-xs uppercase tracking-wide">Tách đặt cọc</span>
                    </div>
                    <button @click="isSplitOpen = false" class="text-slate-300 hover:text-white transition cursor-pointer border-none bg-transparent">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
                <div class="p-5 flex flex-col space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-600">Số tiền gốc</span>
                        <div class="relative flex-1 max-w-[240px]">
                            <input 
                              type="text" 
                              readonly 
                              :value="formatCurrencyInput(splitOriginalAmount) + ' VND'"
                              class="w-full border border-slate-200 rounded-lg px-3 h-[32px] text-xs text-right font-bold bg-slate-50 text-slate-400 outline-none cursor-not-allowed"
                            />
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-700">Số tiền 1</span>
                        <div class="relative flex-1 max-w-[240px]">
                            <input 
                              type="text" 
                              :value="formatCurrencyInput(splitAmount1)"
                              @input="e => handleSplitAmount1Input(e.target.value)"
                              class="w-full border border-slate-300 rounded-lg pl-3 pr-12 h-[32px] text-xs text-right font-bold bg-white text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">VND</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-700">Số tiền 2</span>
                        <div class="relative flex-1 max-w-[240px]">
                            <input 
                              type="text" 
                              :value="formatCurrencyInput(splitAmount2)"
                              @input="e => handleSplitAmount2Input(e.target.value)"
                              class="w-full border border-slate-300 rounded-lg pl-3 pr-12 h-[32px] text-xs text-right font-bold bg-white text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">VND</span>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-5 py-3 border-t border-slate-200 flex justify-end gap-2">
                    <button type="button" @click="isSplitOpen = false" class="px-4 py-1.5 bg-[#e2e8f0] hover:bg-slate-200 text-slate-700 font-bold rounded-lg transition text-xs cursor-pointer border-none">
                        Hủy
                    </button>
                    <button type="button" @click="confirmSplit" class="px-4 py-1.5 bg-[#3b82f6] hover:bg-[#2563eb] text-white font-bold rounded-lg transition text-xs shadow-md flex items-center cursor-pointer border-none">
                        <i class="fa-solid fa-check mr-1.5"></i> Xác nhận
                    </button>
                </div>
            </div>
        </div>

        <!-- CUSTOM TRANSFER DEPOSIT MODAL -->
        <div v-if="isTransferOpen" class="fixed inset-0 bg-black/60 z-[100000] flex items-center justify-center p-4 backdrop-blur-xs" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div class="w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col animate-in fade-in duration-200">
                <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2.5 border-b border-[#1a2d42]">
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-arrow-right-arrow-left text-blue-200 text-xs"></i>
                        <span class="font-bold text-xs uppercase tracking-wide">Chuyển đặt cọc</span>
                    </div>
                    <button @click="isTransferOpen = false" class="text-slate-300 hover:text-white transition cursor-pointer border-none bg-transparent">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
                <div class="p-5 flex flex-col space-y-4">
                    <!-- Information summary -->
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-lg border border-slate-100 text-xs font-semibold">
                        <div class="flex flex-col space-y-1 border-r border-slate-200 pr-2">
                            <span class="text-[9px] uppercase text-slate-400 font-bold tracking-wider">Bên chuyển cọc</span>
                            <div class="text-slate-800 font-bold truncate">{{ props.bookingName || 'Chưa có tên' }}</div>
                            <div class="text-[10px] text-slate-500 font-semibold">Mã BK: {{ props.bookingCode }}</div>
                        </div>
                        <div class="flex flex-col space-y-1 pl-2">
                            <span class="text-[9px] uppercase text-slate-400 font-bold tracking-wider">Bên nhận cọc</span>
                            <template v-if="transferDestBooking">
                                <div class="text-green-600 font-bold truncate">{{ transferDestBooking.booking_name || 'Chưa có tên' }}</div>
                                <div class="text-[10px] text-slate-500 font-semibold">Mã BK: {{ transferDestBooking.booking_code }}</div>
                            </template>
                            <template v-else>
                                <div class="text-slate-400 italic">Vui lòng chọn booking nhận...</div>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-700">Số tiền chuyển</span>
                        <div class="relative flex-1 max-w-[260px]">
                            <input 
                              type="text" 
                              readonly 
                              :value="formatCurrencyInput(transferAmount) + ' VND'"
                              class="w-full border border-slate-200 rounded-lg px-3 h-[32px] text-xs text-right font-bold bg-slate-50 text-slate-400 outline-none cursor-not-allowed"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between gap-4 relative">
                        <span class="text-xs font-bold text-slate-700">Mã booking nhận</span>
                        <div class="relative flex-1 max-w-[260px]">
                            <input 
                              type="text" 
                              v-model="transferDestSearch"
                              @focus="handleSearchFocus"
                              @click="handleSearchFocus"
                              @blur="setTimeout(() => { isFocused = false }, 250)"
                              @input="e => handleSearchBookingInput(e.target.value)"
                              placeholder="Tìm theo mã hoặc tên khách nhận..."
                              class="w-full border border-slate-300 rounded-lg pl-3 pr-8 h-[32px] text-xs font-semibold bg-white text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm"
                            />
                            <i 
                              v-if="transferDestSearch"
                              @click="clearTransferSelection"
                              class="fa-solid fa-xmark absolute right-3 top-2.5 text-slate-400 hover:text-rose-500 cursor-pointer text-xs animate-in fade-in"
                            ></i>
                            <i 
                              v-else
                              class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 text-[10px] pointer-events-none"
                            ></i>
                            <!-- Dropdown list overlay -->
                            <div 
                              v-if="showSearchDropdown && searchResults.length > 0" 
                              class="absolute left-0 right-0 top-full mt-1 max-h-[160px] overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-xl z-[100010] py-1"
                            >
                                <div 
                                  v-for="b in searchResults" 
                                  :key="b.id"
                                  @mousedown="selectTargetBooking(b)"
                                  class="px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-left flex flex-col gap-0.5 border-b border-slate-100 last:border-0"
                                >
                                    <div class="text-xs font-bold text-slate-800">{{ b.booking_code }} - {{ b.booking_name }}</div>
                                    <div class="text-[9px] text-slate-400 font-semibold">
                                      Phòng: {{ b.booking_rooms?.[0]?.room_number || 'Chưa xếp phòng' }} | Ngày đến: {{ formatArrivalDate(b.arrival_date) }}
                                    </div>
                                </div>
                            </div>
                            <div 
                              v-else-if="showSearchDropdown && transferDestSearch && !isSearchingDest && searchResults.length === 0" 
                              class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl z-[100010] p-3 text-center text-xs text-slate-400 italic"
                            >
                                Không tìm thấy đặt phòng phù hợp
                            </div>
                        </div>
                    </div>

                    <!-- Search status detail indicator -->
                    <div class="flex justify-end pr-1">
                        <span 
                          v-if="destBookingName" 
                          class="text-[10px] font-bold"
                          :class="transferDestBooking ? 'text-green-600' : 'text-rose-500'"
                        >
                            {{ destBookingName }}
                        </span>
                    </div>
                </div>
                <div class="bg-slate-50 px-5 py-3 border-t border-slate-200 flex justify-end gap-2">
                    <button type="button" @click="isTransferOpen = false" class="px-4 py-1.5 bg-[#e2e8f0] hover:bg-slate-200 text-slate-700 font-bold rounded-lg transition text-xs cursor-pointer border-none">
                        Hủy
                    </button>
                    <button type="button" @click="confirmTransfer" class="px-4 py-1.5 bg-[#3b82f6] hover:bg-[#2563eb] text-white font-bold rounded-lg transition text-xs shadow-md flex items-center cursor-pointer border-none" :disabled="!transferDestBooking">
                        <i class="fa-solid fa-check mr-1.5"></i> Xác nhận
                    </button>
                </div>
            </div>
        </div>
        <!-- Image Preview Lightbox Modal -->
        <div v-if="previewImageUrl" class="fixed inset-0 bg-black/80 z-[2000000] flex items-center justify-center p-4 backdrop-blur-sm animate-in fade-in duration-200" @click="previewImageUrl = null">
            <div class="relative max-w-4xl max-h-[90vh] bg-white rounded-lg p-2 shadow-2xl animate-in zoom-in duration-200 flex flex-col" @click.stop>
                <button @click="previewImageUrl = null" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-rose-600 hover:bg-rose-700 text-white flex items-center justify-center shadow-lg transition cursor-pointer border-none z-50">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
                <div class="overflow-auto max-h-[85vh] flex items-center justify-center rounded">
                    <img :src="previewImageUrl" class="max-w-full max-h-[80vh] object-contain rounded" />
                </div>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {
  fetchPayments,
  createPayment,
  updatePayment,
  deletePayment,
  splitPayment,
  transferPayment,
  fetchBookings
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  bookingName: String,
  bookingCode: String,
  paymentMethods: Array,
  currenciesList: Array,
  deposits: Array
})

const emit = defineEmits(['update:show', 'update:deposits', 'update:paymentValue'])

const uiStore = useUiStore()

const localDeposits = ref([])
const selectedDepositIds = ref([])

// Hiển thị xóa toggle state
const showDeleted = ref(false)

// File upload state
const selectedFile = ref(null)
const fileInputKey = ref(0)
const previewImageUrl = ref(null)

// Custom Split Modal States
const isSplitOpen = ref(false)
const splitOriginalAmount = ref(0)
const splitAmount1 = ref(0)
const splitAmount2 = ref(0)

// Custom Transfer Modal States
const isTransferOpen = ref(false)
const transferAmount = ref(0)
const transferDestCode = ref('')
const transferDestBooking = ref(null)
const destBookingName = ref('')
const isSearchingDest = ref(false)
const transferDestSearch = ref('')
const isFocused = ref(false)
const showSearchDropdown = computed(() => {
  return isFocused.value && transferDestSearch.value.trim().length > 0
})
const searchResults = ref([])

const activeCurrency = computed(() => {
  return props.currenciesList?.find(c => c.is_main) || { code: 'VND', decimals_to_round: 0 }
})

// Filter payment methods: not group 4, not group 5, not is_inactive
const filteredPaymentMethods = computed(() => {
  return (props.paymentMethods || []).filter(pm => {
    return pm.payment_group !== 4 && pm.payment_group !== 5 && !pm.is_inactive
  })
})

const visibleDeposits = computed(() => {
  if (showDeleted.value) {
    return localDeposits.value.filter(dep => dep.status === 3)
  }
  return localDeposits.value.filter(dep => dep.edit_flag === 0 && dep.status !== 3)
})

const depositForm = ref({
  id: null,
  amount: 0,
  paymentMethodId: null,
  bankAccountId: 'Tài khoản ngân hàng',
  date: new Date().toISOString().split('T')[0],
  note: '',
  recipient: 'Admin',
  image: null
})

// Auto-fill note based on payment method selection
watch(() => depositForm.value.paymentMethodId, (newPmId) => {
  if (newPmId) {
    const pm = props.paymentMethods?.find(x => x.id === newPmId)
    if (pm) {
      depositForm.value.note = `Deposit (${pm.name})`
    }
  }
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    resetForm()
    selectedDepositIds.value = []
    showDeleted.value = false
    if (props.bookingId) {
      await syncDepositsFromBackend()
    } else {
      localDeposits.value = JSON.parse(JSON.stringify(props.deposits || []))
    }
  }
})

function close() {
  emit('update:show', false)
}

function resetForm() {
  selectedFile.value = null
  fileInputKey.value++
  
  const defaultPmId = filteredPaymentMethods.value?.[0]?.id || null
  const defaultPm = props.paymentMethods?.find(x => x.id === defaultPmId)
  const defaultNote = defaultPm ? `Deposit (${defaultPm.name})` : ''

  depositForm.value = {
    id: null,
    amount: 0,
    paymentMethodId: defaultPmId,
    bankAccountId: 'Tài khoản ngân hàng',
    date: new Date().toISOString().split('T')[0],
    note: defaultNote,
    recipient: 'Admin',
    image: null
  }
}

function parseApiDate(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  if (dateStr.includes('T')) {
    const d = new Date(dateStr)
    if (!isNaN(d)) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  return dateStr.substring(0, 10)
}

async function syncDepositsFromBackend() {
  if (!props.bookingId) return
  try {
    const res = await fetchPayments(props.bookingId)
    const paymentsList = res.data?.data || res.data || []
    
    localDeposits.value = paymentsList.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: p.image_path ? [p.image_path] : [],
      status: p.status,
      edit_flag: p.edit_flag,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account,
      pack2: p.pack2
    }))

    const activeDeposits = localDeposits.value.filter(p => p.edit_flag === 0 && p.pack2 === 'DPR')
    const totalValue = activeDeposits.reduce((sum, d) => sum + Number(d.amount), 0)

    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
  } catch (err) {
    console.error('Lỗi đồng bộ cọc:', err)
  }
}

function handleDepositImageUpload(event) {
  const file = event.target.files[0]
  if (file) {
    selectedFile.value = file
    depositForm.value.image = URL.createObjectURL(file)
  }
}

function getImageUrl(path) {
  if (!path) return ''
  if (path.startsWith('blob:') || path.startsWith('data:')) return path
  const baseUrl = import.meta.env.VITE_PROXY_TARGET || ''
  return `${baseUrl}/storage/${path}`
}

function openImage(url) {
  if (url) previewImageUrl.value = url
}

async function addDeposit() {
  if (!depositForm.value.amount || depositForm.value.amount <= 0) {
    uiStore.showToast('Vui lòng nhập số tiền đặt cọc hợp lệ!', 'warning')
    return
  }
  if (!depositForm.value.note || !depositForm.value.note.trim()) {
    uiStore.showToast('Vui lòng nhập mô tả!', 'warning')
    return
  }
  
  if (props.bookingId) {
    try {
      const formData = new FormData()
      formData.append('date', depositForm.value.date)
      formData.append('amount', Number(depositForm.value.amount))
      formData.append('payment_method_id', depositForm.value.paymentMethodId)
      formData.append('description', depositForm.value.note)
      formData.append('debit_account', depositForm.value.bankAccountId || 'Tài khoản ngân hàng')
      if (selectedFile.value) {
        formData.append('image', selectedFile.value)
      }
      await createPayment(props.bookingId, formData)
      await syncDepositsFromBackend()
      uiStore.showToast('Đã thêm đặt cọc mới thành công!', 'success')
      resetForm()
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể thêm cọc!', 'error')
    }
  } else {
    const now = new Date()
    const timeStr = now.toTimeString().split(' ')[0].substring(0, 5)
    
    const newDep = {
      id: Date.now(),
      date: depositForm.value.date.split('-').reverse().join('/'),
      time: timeStr,
      paymentMethodId: depositForm.value.paymentMethodId,
      note: depositForm.value.note,
      amount: Number(depositForm.value.amount),
      currency: activeCurrency.value.code || 'VND',
      recipient: depositForm.value.recipient || 'Admin',
      images: depositForm.value.image ? [depositForm.value.image] : [],
      status: 1,
      edit_flag: 0,
      reversal_ref: null,
      debit_account: depositForm.value.bankAccountId || 'Tài khoản ngân hàng',
      pack2: 'DPR'
    }
    
    localDeposits.value.push(newDep)
    const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    
    resetForm()
    uiStore.showToast('Đã thêm đặt cọc mới!', 'success')
  }
}

function editDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để sửa!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = localDeposits.value.find(d => d.id === targetId)
  if (dep) {
    if (dep.edit_flag !== 0 || dep.status === 3) {
      uiStore.showToast('Không thể sửa cọc đã bị xóa!', 'warning')
      return
    }
    let dateVal = dep.date
    if (dateVal.includes('/')) {
      dateVal = dateVal.split('/').reverse().join('-')
    }
    depositForm.value = {
      id: dep.id,
      amount: dep.amount,
      paymentMethodId: dep.paymentMethodId,
      bankAccountId: dep.bankAccountId || 'Tài khoản ngân hàng',
      date: dateVal,
      note: dep.note,
      recipient: dep.recipient,
      image: dep.images?.[0] || null
    }
  }
}

async function saveDeposit() {
  if (!depositForm.value.id) {
    close()
    return
  }
  if (!depositForm.value.note || !depositForm.value.note.trim()) {
    uiStore.showToast('Vui lòng nhập mô tả!', 'warning')
    return
  }
  
  if (props.bookingId) {
    try {
      const formData = new FormData()
      formData.append('_method', 'PUT')
      formData.append('date', depositForm.value.date)
      formData.append('amount', Number(depositForm.value.amount))
      formData.append('payment_method_id', depositForm.value.paymentMethodId)
      formData.append('description', depositForm.value.note)
      formData.append('debit_account', depositForm.value.bankAccountId)
      if (selectedFile.value) {
        formData.append('image', selectedFile.value)
      }
      await updatePayment(depositForm.value.id, formData)
      await syncDepositsFromBackend()
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
      resetForm()
    } catch (err) {
      uiStore.showToast(err.response?.data?.message || 'Không thể lưu cọc!', 'error')
    }
  } else {
    const idx = localDeposits.value.findIndex(d => d.id === depositForm.value.id)
    if (idx !== -1) {
      localDeposits.value[idx].amount = Number(depositForm.value.amount)
      localDeposits.value[idx].paymentMethodId = depositForm.value.paymentMethodId
      localDeposits.value[idx].date = depositForm.value.date.split('-').reverse().join('/')
      localDeposits.value[idx].note = depositForm.value.note
      localDeposits.value[idx].images = depositForm.value.image ? [depositForm.value.image] : []
      
      const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
      emit('update:deposits', localDeposits.value)
      emit('update:paymentValue', totalValue)
      
      resetForm()
      selectedDepositIds.value = []
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
    }
  }
}

async function deleteDeposits() {
  if (selectedDepositIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn các cọc muốn xóa!', 'warning')
    return
  }
  
  if (props.bookingId) {
    uiStore.confirm({
      title: 'Hủy/Xóa đặt cọc',
      message: 'Bạn có chắc chắn muốn xóa đặt cọc này?',
      confirmText: 'Đồng ý',
      cancelText: 'Quay lại'
    }).then(async confirmed => {
      if (!confirmed) return
      try {
        for (const depId of selectedDepositIds.value) {
          await deletePayment(depId)
        }
        await syncDepositsFromBackend()
        uiStore.showToast('Đã xóa đặt cọc thành công!', 'success')
        selectedDepositIds.value = []
      } catch (err) {
        uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa cọc!', 'error')
      }
    })
  } else {
    localDeposits.value = localDeposits.value.filter(d => !selectedDepositIds.value.includes(d.id))
    const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    selectedDepositIds.value = []
    uiStore.showToast('Đã xóa cọc thành công!', 'success')
  }
}

function handleSplitAmount1Input(val) {
  const cleaned = cleanCurrencyValue(val)
  splitAmount1.value = Math.min(cleaned, splitOriginalAmount.value)
  splitAmount2.value = Math.max(0, splitOriginalAmount.value - splitAmount1.value)
}

function handleSplitAmount2Input(val) {
  const cleaned = cleanCurrencyValue(val)
  splitAmount2.value = Math.min(cleaned, splitOriginalAmount.value)
  splitAmount1.value = Math.max(0, splitOriginalAmount.value - splitAmount2.value)
}

async function splitDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để tách!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = localDeposits.value.find(d => d.id === targetId)
  if (!dep) return

  if (dep.edit_flag !== 0 || dep.status === 3) {
    uiStore.showToast('Không thể tách cọc đã bị xóa!', 'warning')
    return
  }

  if (!props.bookingId) {
    uiStore.showToast('Chỉ có thể tách cọc của booking đã lưu.', 'warning')
    return
  }

  splitOriginalAmount.value = dep.amount
  splitAmount1.value = Math.floor(dep.amount / 2)
  splitAmount2.value = dep.amount - splitAmount1.value
  isSplitOpen.value = true
}

async function confirmSplit() {
  const total = splitAmount1.value + splitAmount2.value
  if (total !== splitOriginalAmount.value) {
    uiStore.showToast('Tổng hai số tiền tách phải bằng số tiền gốc!', 'warning')
    return
  }
  if (splitAmount1.value <= 0 || splitAmount2.value <= 0) {
    uiStore.showToast('Số tiền tách phải lớn hơn 0!', 'warning')
    return
  }

  const targetId = selectedDepositIds.value[0]
  try {
    await splitPayment(targetId, { amounts: [splitAmount1.value, splitAmount2.value] })
    await syncDepositsFromBackend()
    uiStore.showToast('Tách cọc thành công!', 'success')
    selectedDepositIds.value = []
    isSplitOpen.value = false
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể tách cọc!', 'error')
  }
}

async function handleSearchBookingInput(query) {
  transferDestSearch.value = query
  if (!query || query.trim().length < 1) {
    searchResults.value = []
    destBookingName.value = ''
    transferDestBooking.value = null
    return
  }
  isSearchingDest.value = true
  try {
    const res = await fetchBookings({ search: query.trim() })
    const bookings = res.data?.data || res.data || []
    searchResults.value = bookings.filter(b => b.id !== props.bookingId)
  } catch (err) {
    console.error(err)
    searchResults.value = []
  } finally {
    isSearchingDest.value = false
  }
}

function handleSearchFocus() {
  isFocused.value = true
}

function selectTargetBooking(b) {
  transferDestBooking.value = b
  transferDestCode.value = b.booking_code
  transferDestSearch.value = b.booking_code
  isFocused.value = false
  destBookingName.value = `Khách nhận: ${b.booking_name || 'Không rõ'} (${b.booking_rooms?.[0]?.room_number ? 'Phòng ' + b.booking_rooms[0].room_number : 'Chưa xếp phòng'})`
}

function clearTransferSelection() {
  transferDestSearch.value = ''
  transferDestBooking.value = null
  destBookingName.value = ''
  searchResults.value = []
}

function formatArrivalDate(dateStr) {
  if (!dateStr) return ''
  const match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (match) {
    const [_, year, month, day] = match
    return `${day}/${month}/${year}`
  }
  return dateStr
}

async function transferDeposit() {
  if (selectedDepositIds.value.length !== 1) {
    uiStore.showToast('Vui lòng chọn duy nhất 1 cọc để chuyển!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const dep = localDeposits.value.find(d => d.id === targetId)
  if (!dep) return

  if (dep.edit_flag !== 0 || dep.status === 3) {
    uiStore.showToast('Không thể chuyển cọc đã bị xóa!', 'warning')
    return
  }

  if (!props.bookingId) {
    uiStore.showToast('Chỉ có thể chuyển cọc của booking đã lưu.', 'warning')
    return
  }

  transferAmount.value = dep.amount
  transferDestCode.value = ''
  transferDestSearch.value = ''
  transferDestBooking.value = null
  destBookingName.value = ''
  searchResults.value = []
  isTransferOpen.value = true

  isSearchingDest.value = true
  try {
    const res = await fetchBookings({ limit: 100 })
    const bookings = res.data?.data || res.data || []
    searchResults.value = bookings.filter(b => b.id !== props.bookingId)
  } catch (err) {
    console.error(err)
  } finally {
    isSearchingDest.value = false
  }
}

async function confirmTransfer() {
  if (!transferDestBooking.value) {
    uiStore.showToast('Vui lòng chọn mã booking nhận!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  try {
    await transferPayment(targetId, { target_booking_id: transferDestBooking.value.id })
    await syncDepositsFromBackend()
    uiStore.showToast(`Đã chuyển cọc sang booking ${transferDestBooking.value.booking_code} thành công!`, 'success')
    selectedDepositIds.value = []
    isTransferOpen.value = false
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi chuyển cọc!', 'error')
  }
}

function formatCurrencyInput(val) {
  if (!val && val !== 0) return ''
  return Number(val).toLocaleString('vi-VN')
}

function cleanCurrencyValue(val) {
  if (!val) return 0
  return Number(val.replace(/\./g, '').replace(/,/g, ''))
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  uiStore.showToast('Đã copy ngày đặt cọc!', 'success')
}
</script>
