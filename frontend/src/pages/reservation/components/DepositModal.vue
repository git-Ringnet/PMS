<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
  >
    <div 
      class="w-full max-w-5xl bg-white shadow-2xl rounded-2xl overflow-visible border border-slate-200 flex flex-col max-h-[90vh]"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
        
        <!-- HEADER -->
        <div 
          class="flex justify-between items-center px-4 py-2 border-b border-black/10 cursor-move select-none transition-all duration-300"
          :style="{ background: topbarThemeBg }"
          :class="isTopBarThemeDark ? 'text-white' : 'text-slate-900'"
          @mousedown="startDragModal"
        >
            <div class="flex items-center space-x-2">
                <div class="p-1.5 rounded-lg" :class="isTopBarThemeDark ? 'bg-white/10 text-blue-200' : 'bg-black/10 text-slate-800'">
                    <i class="fa-solid fa-file-invoice-dollar text-xs"></i>
                </div>
                <span class="font-bold text-xs tracking-wide uppercase">{{ depositForm.id ? 'Sửa đặt cọc' : 'Thêm đặt cọc' }}</span>
            </div>
            <button @click="close" class="transition p-1.5 rounded-lg cursor-pointer border-none bg-transparent" :class="isTopBarThemeDark ? 'text-slate-300 hover:text-white hover:bg-white/10' : 'text-slate-700 hover:text-black hover:bg-black/10'">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- SCROLLABLE CONTENT -->
        <div class="overflow-y-auto p-4 bg-white flex flex-col space-y-3 shrink-0">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tên đăng ký</label>
                    <div class="relative">
                        <select disabled class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs font-medium bg-slate-50 text-slate-800 appearance-none focus:outline-none shadow-sm cursor-not-allowed">
                            <option>{{ bookingName || 'Chưa có tên' }}</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Phòng (Đặt cọc riêng cho phòng)</label>
                    <div class="relative">
                        <select 
                          v-model="depositForm.bookingRoomId"
                          @change="handleRoomChange"
                          :disabled="isEditing"
                          :class="{ 'opacity-60 cursor-not-allowed bg-slate-100': isEditing }"
                          class="w-full border border-blue-200 rounded-lg px-3 h-[30px] text-xs font-medium bg-blue-50/70 text-slate-800 appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option :value="null">-- Đặt cọc cho toàn bộ phiếu đăng ký --</option>
                            <option v-for="r in availableRooms" :key="getBookingRoomId(r)" :value="getBookingRoomId(r)">
                                Phòng {{ r.room_number || r.roomNumber || r.room?.room_number || r.id }}
                            </option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
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
                          @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                          :disabled="isEditing"
                          :class="{ 'opacity-60 cursor-not-allowed bg-slate-100': isEditing }"
                          class="w-full border border-blue-200 rounded-lg px-3 h-[30px] text-xs font-bold bg-blue-50/70 text-black focus:outline-none focus:border-blue-500 shadow-sm"
                        >
                        <div class="absolute right-1 top-0.5 flex flex-col" v-if="!isEditing">
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
                          @change="handlePaymentMethodChange"
                          class="w-full border border-blue-200 rounded-lg px-3 h-full text-xs font-medium bg-blue-50/70 text-black appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option :value="null" disabled class="text-slate-400 font-normal bg-slate-100">Phương thức đặt cọc</option>
                            <option v-for="pm in filteredPaymentMethods" :key="pm.id" :value="pm.code || pm.id">{{ pm.name }}</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Tài khoản ngân hàng</label>
                    <div class="relative">
                        <select 
                          v-model="depositForm.bankAccountId"
                          :disabled="isEditing"
                          :class="{ 'opacity-60 cursor-not-allowed bg-slate-100': isEditing }"
                          class="w-full border border-slate-300 rounded-lg px-3 h-[30px] text-xs bg-white text-slate-800 appearance-none focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer"
                        >
                            <option :value="null">-- Không chọn tài khoản --</option>
                            <option v-for="account in activeBankAccounts" :key="account.id" :value="account.id">
                              {{ account.code }} - {{ account.bank_account_number }} - {{ account.bank_name }}
                            </option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 pointer-events-none text-[10px]"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-500 font-semibold mb-0.5">Ngày <span class="text-rose-500">*</span></label>
                    <div 
                      class="flex items-center space-x-2 border border-slate-300 rounded-lg px-3 h-[30px] bg-white shadow-sm text-xs font-medium text-slate-800 relative cursor-pointer"
                      :class="{ 'opacity-60 bg-slate-100 cursor-not-allowed': isEditing }"
                      @click="openDatePicker"
                    >
                        <input 
                          ref="dateInputRef"
                          type="date" 
                          v-model="depositForm.date" 
                          :disabled="isEditing"
                          :min="minDepositDate"
                          class="date-span-input flex-1 text-left w-full border-none focus:outline-none bg-transparent cursor-pointer"
                        />
                        <i class="fa-regular fa-calendar-days text-blue-500 cursor-pointer" :class="{ 'opacity-50 cursor-not-allowed': isEditing }" @click.stop="openDatePicker" title="Chọn ngày"></i>
                        <i @click.stop="copyToClipboard(depositForm.date)" class="fa-regular fa-copy text-slate-400 hover:text-slate-600 cursor-pointer" title="Sao chép ngày"></i>
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
                    <div class="border border-dashed border-slate-300 rounded-lg h-[56px] bg-slate-50 flex items-center justify-center hover:bg-slate-100 hover:border-blue-400 transition cursor-pointer relative overflow-hidden group shadow-sm" :class="{ 'cursor-not-allowed opacity-60': isEditing }">
                        <input v-if="!depositForm.image && !isEditing" :key="fileInputKey" type="file" @change="handleDepositImageUpload" class="absolute inset-0 opacity-0 cursor-pointer z-10" accept="image/*">
                        <div class="flex flex-col items-center space-y-1" v-if="!depositForm.image">
                            <i class="fa-solid fa-cloud-arrow-up text-slate-400 group-hover:text-blue-500 transition text-xs"></i>
                            <span class="text-[10px] text-slate-500 font-medium group-hover:text-blue-600 transition">Nhấp để tải ảnh lên hoặc kéo thả vào đây</span>
                        </div>
                        <div class="flex items-center space-x-2 p-1" v-else>
                            <img :src="getImageUrl(depositForm.image)" class="h-10 w-10 object-cover rounded border cursor-pointer hover:opacity-85 transition z-20" @click.stop="openImage(getImageUrl(depositForm.image))" @error="$event.target.classList.add('hidden')" title="Nhấp để xem ảnh lớn" />
                            <div class="flex flex-col z-20">
                                <span class="text-[10px] text-green-600 font-bold">Hình ảnh đã chọn</span>
                                <button v-if="!isEditing" type="button" @click.stop="depositForm.image = null; selectedFile = null" class="text-[9px] text-rose-500 hover:text-rose-700 font-semibold underline mt-0.5 border-none bg-transparent cursor-pointer text-left">
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
                    Danh sách đặt cọc <span v-if="selectedRoomNumber" class="text-blue-600 font-bold ml-1.5 normal-case">(Phòng {{ selectedRoomNumber }})</span> <span class="text-rose-500 ml-1">*</span>
                </h3>
                
                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <span class="text-[11px] text-slate-500 font-medium">Hiển thị xoá</span>
                        <button 
                          type="button"
                          @click="showDeleted = !showDeleted"
                          :disabled="isEditing"
                          class="relative inline-flex h-4 w-8 shrink-0 rounded-full border border-transparent transition-colors duration-200 ease-in-out focus:outline-none shadow-inner"
                          :class="[showDeleted ? 'bg-blue-600' : 'bg-slate-300', isEditing ? 'cursor-not-allowed opacity-60' : 'cursor-pointer']"
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
                                  :disabled="isEditing"
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
                          :class="{ 'bg-blue-50/30': selectedDepositIds.includes(dep.id), 'bg-rose-50/40 text-rose-700': dep.amount < 0 || dep.edit_flag === 1 }"
                        >
                            <td class="p-2 text-center align-middle">
                                <input 
                                  type="checkbox" 
                                  :value="dep.id" 
                                  v-model="selectedDepositIds"
                                  :disabled="isEditing"
                                  class="rounded border-slate-300 font-normal"
                                >
                            </td>
                            <td class="p-2 font-medium text-slate-800 align-middle">{{ dep.date }}</td>
                            <td class="p-2 text-slate-600 align-middle">{{ dep.time }}</td>
                            <td class="p-2 text-slate-800 align-middle">{{ paymentMethods.find(x => x.code === dep.paymentMethodId || String(x.id) === String(dep.paymentMethodId))?.name || dep.paymentMethodId || 'BT' }}</td>
                            <td class="p-2 text-slate-600 align-middle">
                                <span v-if="dep.roomNumber" class="inline-block bg-blue-50 text-blue-700 text-[10px] font-bold px-1.5 py-0.5 rounded border border-blue-200 mr-1.5">
                                    Phòng {{ dep.roomNumber }}
                                </span>
                                <span>{{ dep.note }}</span>
                            </td>
                            <td class="p-2 text-right font-mono font-semibold align-middle" :class="dep.amount < 0 ? 'text-rose-600' : 'text-slate-900'">{{ dep.amount.toLocaleString('en-US') }}</td>
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
                                        <img
                                          v-if="img && img !== 'Chứng từ' && !hasReceiptImageError(dep.id, iIdx)"
                                          :src="getImageUrl(img)"
                                          class="w-full h-full object-cover"
                                          @error="markReceiptImageError(dep.id, iIdx)"
                                        />
                                        <div v-else class="w-full h-full flex items-center justify-center bg-slate-100 text-[8px] font-bold text-slate-500 text-center leading-tight px-0.5">
                                            {{ img && img !== 'Chứng từ' ? 'Không tải được chứng từ' : 'Ảnh' }}
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
                <button type="button" @click="splitDeposit" :disabled="isSubmitting || isEditing" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting || isEditing }" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-code-branch text-[10px]"></i>
                    <span>Tách</span>
                </button>
                <button type="button" @click="transferDeposit" :disabled="isSubmitting || isEditing" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting || isEditing }" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-arrow-right-arrow-left text-[10px]"></i>
                    <span>Chuyển</span>
                </button>
            </div>

            <div class="flex items-center space-x-2 ml-auto">
                <button type="button" v-if="depositForm.id" @click="resetForm(); selectedDepositIds = []" :disabled="isSubmitting" class="px-4 py-1.5 bg-slate-500 hover:bg-slate-600 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Quay lại</span>
                </button>
                <button type="button" @click="deleteDeposits" :disabled="isSubmitting || isEditing" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting || isEditing }" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                    <span>Xóa</span>
                </button>
                <button type="button" @click="editDeposit" :disabled="isSubmitting || isEditing" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting || isEditing }" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                    <span>Sửa</span>
                </button>
                <button type="button" @click="saveDeposit" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition text-xs shadow-sm flex items-center space-x-1.5 cursor-pointer border-none">
                    <i class="fa-regular fa-floppy-disk text-[10px]"></i>
                    <span>Lưu</span>
                </button>
                <button type="button" v-if="!isEditing" @click="addDeposit" :disabled="isSubmitting" :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }" class="px-5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition flex items-center space-x-1.5 shadow-md text-xs tracking-wide cursor-pointer border-none">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Thêm</span>
                </button>
            </div>
        </div>

        <!-- CUSTOM SPLIT DEPOSIT MODAL -->
        <div v-if="isSplitOpen" class="fixed inset-0 bg-black/60 z-[100000] flex items-center justify-center p-4 backdrop-blur-xs" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden border border-slate-200 flex flex-col animate-in fade-in duration-200">
                <div 
                  class="flex justify-between items-center px-4 py-2.5 border-b border-black/10 transition-all duration-300"
                  :style="{ background: topbarThemeBg }"
                  :class="isTopBarThemeDark ? 'text-white' : 'text-slate-900'"
                >
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-scissors text-xs" :class="isTopBarThemeDark ? 'text-blue-200' : 'text-slate-800'"></i>
                        <span class="font-bold text-xs uppercase tracking-wide">Tách đặt cọc</span>
                    </div>
                    <button @click="isSplitOpen = false" class="transition cursor-pointer border-none bg-transparent" :class="isTopBarThemeDark ? 'text-slate-300 hover:text-white' : 'text-slate-700 hover:text-black'">
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
                    
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-600">Chế độ tách:</span>
                        <div class="flex space-x-2">
                            <button 
                              type="button" 
                              @click="setSplitMode(2)"
                              :class="splitMode === 2 ? 'bg-blue-600 text-white font-bold border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-300'"
                              class="px-2.5 py-1 text-[11px] rounded border transition cursor-pointer"
                            >
                              Tách đôi (1/2)
                            </button>
                            <button 
                              type="button" 
                              @click="setSplitMode(3)"
                              :class="splitMode === 3 ? 'bg-blue-600 text-white font-bold border-blue-600' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-300'"
                              class="px-2.5 py-1 text-[11px] rounded border transition cursor-pointer"
                            >
                              Tách ba (1/3)
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-700">Số tiền 1</span>
                        <div class="relative flex-1 max-w-[240px]">
                            <input 
                              type="text" 
                              :value="formatCurrencyInput(splitAmount1)"
                              @input="e => handleSplitAmount1Input(e.target.value)"
                              @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
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
                              @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                              class="w-full border border-slate-300 rounded-lg pl-3 pr-12 h-[32px] text-xs text-right font-bold bg-white text-slate-800 focus:outline-none focus:border-blue-500 shadow-sm"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">VND</span>
                        </div>
                    </div>
                    <div v-if="splitMode === 3" class="flex items-center justify-between gap-4">
                        <span class="text-xs font-bold text-slate-700">Số tiền 3</span>
                        <div class="relative flex-1 max-w-[240px]">
                            <input 
                              type="text" 
                              :value="formatCurrencyInput(splitAmount3)"
                              @input="e => handleSplitAmount3Input(e.target.value)"
                              @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
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
            <div class="w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-visible border border-slate-200 flex flex-col animate-in fade-in duration-200 relative">
                <div 
                  class="flex justify-between items-center px-4 py-2.5 border-b border-black/10 rounded-t-xl transition-all duration-300"
                  :style="{ background: topbarThemeBg }"
                  :class="isTopBarThemeDark ? 'text-white' : 'text-slate-900'"
                >
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-arrow-right-arrow-left text-xs" :class="isTopBarThemeDark ? 'text-blue-200' : 'text-slate-800'"></i>
                        <span class="font-bold text-xs uppercase tracking-wide">Chuyển đặt cọc</span>
                    </div>
                    <button @click="isTransferOpen = false" class="transition cursor-pointer border-none bg-transparent" :class="isTopBarThemeDark ? 'text-slate-300 hover:text-white' : 'text-slate-700 hover:text-black'">
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
                              @click="handleSearchFocus"
                              class="fa-solid fa-chevron-down absolute right-3 top-2.5 text-slate-400 text-[10px] cursor-pointer"
                            ></i>
                            <!-- Dropdown list: booking và các phòng/khách tương ứng như luồng chuyển ở Hóa đơn. -->
                            <div 
                              v-if="showSearchDropdown && transferOptions.length > 0" 
                              class="absolute left-0 right-0 top-full mt-1 max-h-[240px] overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-xl z-[100010] py-1 select-none"
                            >
                                <div 
                                  v-for="opt in transferOptions" 
                                  :key="opt.key"
                                  @mousedown="selectTargetBookingOption(opt)"
                                  class="px-3.5 py-1.5 hover:bg-sky-50/80 cursor-pointer text-left flex items-center border-b border-slate-100 last:border-0 transition"
                                >
                                    <template v-if="opt.type === 'booking'">
                                      <span class="font-black text-slate-800 text-xs tracking-wider w-12 shrink-0">BKK:</span>
                                      <span class="font-bold text-slate-800 text-xs truncate">{{ opt.code }} - {{ opt.name }}</span>
                                    </template>
                                    <template v-else-if="opt.type === 'room'">
                                      <div class="flex items-center text-xs pl-6 w-full">
                                        <span class="font-bold text-slate-800 min-w-[48px] text-right pr-1">{{ opt.roomNumber }}</span>
                                        <span class="text-slate-400 font-normal px-2">|</span>
                                        <span class="font-medium text-slate-700 truncate">Toàn bộ phòng</span>
                                      </div>
                                    </template>
                                    <template v-else-if="opt.type === 'guest'">
                                      <div class="flex items-center text-xs pl-6 w-full">
                                        <span class="font-bold text-slate-800 min-w-[48px] text-right pr-1">{{ opt.roomNumber }}</span>
                                        <span class="text-slate-400 font-normal px-2">|</span>
                                        <span :class="opt.isPrimary ? 'font-bold text-slate-900' : 'font-medium text-slate-700'" class="truncate">
                                          {{ opt.guestName }}
                                        </span>
                                      </div>
                                    </template>
                                </div>
                            </div>
                            <div 
                              v-else-if="showSearchDropdown && transferDestSearch && !isSearchingDest && transferOptions.length === 0" 
                              class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl z-[100010] p-3 text-center text-xs text-slate-400 italic"
                            >
                                Không tìm thấy đặt phòng ở trạng thái Đăng ký hoặc Đang ở
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
                <div class="bg-slate-50 px-5 py-3 border-t border-slate-200 flex justify-end gap-2 rounded-b-xl">
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
                    <div v-if="imagePreviewError" class="px-10 py-12 text-center text-sm text-slate-500">
                      Không thể tải ảnh chứng từ.
                    </div>
                    <img v-else :src="previewImageUrl" class="max-w-full max-h-[80vh] object-contain rounded" @error="imagePreviewError = true" />
                </div>
            </div>
        </div>
        <!-- Delete reason form required by the payment reversal API -->
        <div v-if="isDeleteReasonOpen" class="fixed inset-0 bg-black/60 z-[2100000] flex items-center justify-center p-4" @click.self="closeDeleteReason">
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800">Lý do xóa đặt cọc</h3>
                    <button type="button" @click="closeDeleteReason" class="border-none bg-transparent text-slate-500 hover:text-slate-800 cursor-pointer">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="p-4">
                    <div class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
                      Đã chọn <strong class="text-slate-800">{{ deleteTargetIds.length }}</strong> khoản,
                      tổng <strong class="text-slate-800">{{ formatCurrencyInput(deleteTargetTotal) }} {{ activeCurrency.code || 'VND' }}</strong>
                      trong booking <strong class="text-slate-800">{{ bookingCode || bookingName || 'hiện tại' }}</strong>.
                    </div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                      Vui lòng nhập lý do <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                      v-model="deleteReason"
                      maxlength="1000"
                      rows="4"
                      autofocus
                      placeholder="Nhập lý do xóa/đối trừ..."
                      class="w-full border border-slate-300 rounded-lg p-2.5 text-sm text-slate-800 resize-none focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    ></textarea>
                    <div class="mt-1 text-right text-[10px] text-slate-400">{{ deleteReason.length }}/1000</div>
                </div>
                <div class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex justify-end gap-2">
                    <button type="button" @click="closeDeleteReason" class="px-4 py-1.5 bg-white border border-slate-300 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer">Hủy</button>
                    <button type="button" @click="confirmDelete" :disabled="!deleteReason.trim() || isSubmitting" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-semibold border-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Xóa đặt cọc</button>
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
  fetchBookings,
  fetchSystemDate,
  fetchHotelSettings
} from '@/services/booking-service'
import { fetchBankAccounts } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  bookingName: String,
  bookingCode: String,
  departmentId: { type: String, default: 'MR' },
  paymentMethods: Array,
  currenciesList: Array,
  deposits: Array,
  rooms: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:show', 'update:deposits', 'update:paymentValue'])

const uiStore = useUiStore()
const authStore = useAuthStore()

// Dynamic Theme topbar background sync
const topbarThemeBg = computed(() => {
  return authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)'
})

const isTopBarThemeDark = computed(() => {
  const bg = authStore.settings?.topbar_color
  if (!bg) return true
  if (bg.includes('linear-gradient')) return true
  if (bg.startsWith('#')) {
    const hex = bg.substring(1)
    const rgb = parseInt(hex.length === 3 ? hex.split('').map(c => c + c).join('') : hex, 16)
    if (isNaN(rgb)) return true
    const r = (rgb >> 16) & 0xff
    const g = (rgb >> 8) & 0xff
    const b = (rgb >> 0) & 0xff
    const brightness = (r * 299 + g * 587 + b * 114) / 1000
    return brightness < 185
  }
  return true
})

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

const localDeposits = ref([])
const selectedDepositIds = ref([])
const isSubmitting = ref(false)

// Hiển thị xóa toggle state
const showDeleted = ref(false)
const isDeleteReasonOpen = ref(false)
const deleteReason = ref('')
const deleteTargetIds = ref([])
const deleteTargetTotal = computed(() => localDeposits.value
  .filter(deposit => deleteTargetIds.value.includes(deposit.id))
  .reduce((sum, deposit) => sum + Number(deposit.amount || 0), 0))

// File upload state
const selectedFile = ref(null)
const fileInputKey = ref(0)
const previewImageUrl = ref(null)
const imagePreviewError = ref(false)
const receiptImageErrors = ref({})
const dateInputRef = ref(null)
const bankAccounts = ref([])
const activeBankAccounts = computed(() => (bankAccounts.value || []).filter(account => account.is_active !== false && !account.is_intermediary))

async function loadBankAccounts() {
  try {
    const response = await fetchBankAccounts({ is_intermediary: false, is_active: true })
    bankAccounts.value = response.data?.data || response.data || []
  } catch (error) {
    // Bank selection is optional; an unavailable catalogue must not block
    // adding a cash deposit or opening the existing deposit history.
    bankAccounts.value = []
    console.error('Không thể tải danh sách tài khoản ngân hàng:', error)
  }
}

function openDatePicker() {
  if (depositForm.value?.id) return
  if (dateInputRef.value) {
    if (typeof dateInputRef.value.showPicker === 'function') {
      try {
        dateInputRef.value.showPicker()
      } catch (e) {
        dateInputRef.value.focus()
      }
    } else {
      dateInputRef.value.focus()
    }
  }
}

// System date state
const systemDate = ref(new Date().toISOString().split('T')[0])
const oldDayRuleSubjects = ref('')

// Custom Split Modal States
const isSplitOpen = ref(false)
const splitMode = ref(2)
const splitOriginalAmount = ref(0)
const splitAmount1 = ref(0)
const splitAmount2 = ref(0)
const splitAmount3 = ref(0)

function setSplitMode(mode) {
  splitMode.value = mode
  if (mode === 2) {
    splitAmount1.value = Math.floor(splitOriginalAmount.value / 2)
    splitAmount2.value = splitOriginalAmount.value - splitAmount1.value
    splitAmount3.value = 0
  } else if (mode === 3) {
    const part = Math.floor(splitOriginalAmount.value / 3)
    splitAmount1.value = part
    splitAmount2.value = part
    splitAmount3.value = splitOriginalAmount.value - part * 2
  }
}

// Custom Transfer Modal States
const isTransferOpen = ref(false)
const transferAmount = ref(0)
const transferDestRoomId = ref(null)
const transferDestGuestId = ref(null)
const transferDestBooking = ref(null)
const destBookingName = ref('')
const isSearchingDest = ref(false)
const transferDestSearch = ref('')
const isFocused = ref(false)
const showSearchDropdown = computed(() => {
  return isFocused.value
})
const searchResults = ref([])
let transferSearchRequestId = 0

const transferOptions = computed(() => {
  const query = (transferDestSearch.value || '').trim().toLowerCase()
  const list = []

  for (const booking of searchResults.value) {
    const code = String(booking.booking_code || '').toLowerCase()
    const name = String(booking.booking_name || booking.guest_name || '').toLowerCase()
    const bookingMatches = !query || code.includes(query) || name.includes(query)
    const roomOptions = []

    for (const room of Array.isArray(booking.booking_rooms) ? booking.booking_rooms : []) {
      const roomNumber = String(room.room_number || room.room?.room_number || 'Chưa xếp')
      const roomMatches = !query || roomNumber.toLowerCase().includes(query)
      const eligibleRoom = room.status === undefined || room.status === null || [0, 1].includes(Number(room.status))
      if (!eligibleRoom) continue
      const guests = Array.isArray(room.guests) && room.guests.length > 0
        ? room.guests
        : [{ guest_id: null, guest_name: 'Khách chưa đặt tên', is_primary: true }]

      const matchingGuests = guests.filter(guest => {
        const guestName = String(guest.guest?.full_name || guest.guest_name || 'Khách chưa đặt tên').trim()
        return !query || bookingMatches || roomMatches || guestName.toLowerCase().includes(query)
      })
      if (!bookingMatches && !roomMatches && matchingGuests.length === 0) continue

      roomOptions.push({
        key: `room_${booking.id}_${room.id || roomNumber}`,
        type: 'room',
        booking,
        bookingRoomId: room.id,
        roomNumber,
      })

      for (const guest of matchingGuests) {
        const guestName = String(guest.guest?.full_name || guest.guest_name || 'Khách chưa đặt tên').trim()
        roomOptions.push({
          key: `guest_${booking.id}_${room.id || roomNumber}_${guest.guest_id || guest.guest?.id || guest.id || guestName}`,
          type: 'guest',
          booking,
          bookingRoomId: room.id,
          guestId: guest.guest_id || guest.guest?.id || guest.id || null,
          roomNumber,
          guestName,
          isPrimary: Boolean(guest.is_primary),
        })
      }
    }

    if (bookingMatches || roomOptions.length > 0) {
      list.push({
        key: `bkk_${booking.id}`,
        type: 'booking',
        booking,
        code: booking.booking_code || `BK-${booking.id}`,
        name: booking.booking_name || booking.guest_name || 'Chưa có tên',
      })
      list.push(...roomOptions)
    }
  }
  return list
})

const activeCurrency = computed(() => {
  return props.currenciesList?.find(c => c.is_main) || { code: 'VND', decimals_to_round: 0 }
})

// The rule value is a comma-separated list, e.g. admin,FOM,ACC.
const canOperateOldDay = computed(() => {
  const allowed = String(oldDayRuleSubjects.value || '')
    .split(/[,;|]+/)
    .map(value => value.trim().toLowerCase())
    .filter(Boolean)
  const user = authStore.user || {}
  const identifiers = [
    user.username,
    user.job_title_code,
    user.job_title,
    ...(authStore.roles || []).map(role => role.role_code)
  ]
    .filter(Boolean)
    .map(value => String(value).trim().toLowerCase())

  return allowed.some(value => identifiers.includes(value))
})
const isEditing = computed(() => Boolean(depositForm.value.id))
const minDepositDate = computed(() => {
  return canOperateOldDay.value ? null : systemDate.value
})

async function loadOperationalSettings() {
  try {
    const [systemDateResponse, hotelSettingsResponse] = await Promise.all([
      fetchSystemDate(),
      fetchHotelSettings()
    ])
    if (systemDateResponse.data?.data?.system_date) {
      systemDate.value = systemDateResponse.data.data.system_date
    }
    oldDayRuleSubjects.value = hotelSettingsResponse.data?.data?.RuleUserCorrectOrPostBillPaymentOldDay || ''
  } catch (err) {
    oldDayRuleSubjects.value = ''
    console.error('Failed to load operational settings:', err)
  }
}
// Filter payment methods: not group 4, not group 5, not is_inactive
const filteredPaymentMethods = computed(() => {
  return (props.paymentMethods || []).filter(pm => {
    return pm.payment_group !== 4 && pm.payment_group !== 5 && !pm.is_inactive
  })
})

function getBookingRoomId(r) {
  if (!r) return null
  if (r.booking_room_id) return r.booking_room_id
  if (r.bookingRoomId) return r.bookingRoomId
  if (r.dbId) return r.dbId
  if (r.roomId) return r.roomId
  if (r.rawRoom && r.rawRoom.id) return r.rawRoom.id
  if (r.rawRoom && r.rawRoom.booking_room_id) return r.rawRoom.booking_room_id
  if (r.id !== undefined && r.id !== null) {
    const sId = String(r.id)
    if (sId.startsWith('R') && sId.length > 1) {
      return sId.substring(1)
    }
    return r.id
  }
  return null
}

// Danh sách các phòng khả dụng để chọn cọc riêng
const availableRooms = computed(() => {
  if (!props.rooms || !Array.isArray(props.rooms)) return []
  return props.rooms
})

// Lấy số phòng đang chọn (nếu có)
const selectedRoomNumber = computed(() => {
  if (!depositForm.value.bookingRoomId) return null
  const targetRoom = availableRooms.value.find(r => String(getBookingRoomId(r)) === String(depositForm.value.bookingRoomId))
  return targetRoom ? (targetRoom.room_number || targetRoom.roomNumber || targetRoom.room?.room_number || targetRoom.id) : null
})

// Hiển thị danh sách cọc: lọc theo phòng được chọn (nếu chọn phòng cụ thể) và trạng thái hiển thị xóa
const visibleDeposits = computed(() => {
  let list = localDeposits.value.filter(dep => (
    String(dep.pack2 || '').toUpperCase() === 'DPR'
    && String(dep.pack4 || '').toUpperCase() !== 'PY'
  ))
  if (!showDeleted.value) {
    list = list.filter(dep => dep.edit_flag === 0 && dep.status !== 3)
  }
  if (depositForm.value.bookingRoomId) {
    list = list.filter(dep => String(dep.bookingRoomId) === String(depositForm.value.bookingRoomId))
  }
  // Keep the original positive line and its negative reversal adjacent.
  return list.sort((a, b) => {
    const aGroup = a.reversal_ref || a.id
    const bGroup = b.reversal_ref || b.id
    if (aGroup !== bGroup) return Number(aGroup) - Number(bGroup)
    return Number(b.amount) - Number(a.amount)
  })
})
const depositForm = ref({
  id: null,
  bookingRoomId: null,
  amount: 0,
  paymentMethodId: null,
  bankAccountId: null,
  date: systemDate.value,
  note: '',
  recipient: 'Admin',
  image: null
})

function updateAutoNote() {
  if (depositForm.value.id) return
  const pmId = depositForm.value.paymentMethodId
  const pm = (props.paymentMethods || []).find(x => x.code === pmId || String(x.id) === String(pmId))
  const pmName = pm ? pm.name : ''
  
  let roomTag = ''
  if (depositForm.value.bookingRoomId) {
    const targetRoom = availableRooms.value.find(r => String(getBookingRoomId(r)) === String(depositForm.value.bookingRoomId))
    if (targetRoom) {
      const rNo = targetRoom.room_number || targetRoom.roomNumber || targetRoom.room?.room_number
      if (rNo) roomTag = ` - Phòng ${rNo}`
    }
  }
  depositForm.value.note = `Deposit (${pmName || 'Tiền mặt'})${roomTag}`
}

function handlePaymentMethodChange() {
  updateAutoNote()
}

function handleRoomChange() {
  updateAutoNote()
}

// Auto-fill note based on payment method selection
watch(() => depositForm.value.paymentMethodId, (newPmId) => {
  handlePaymentMethodChange()
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
    await loadOperationalSettings()
    await loadBankAccounts()
    resetForm()
    selectedDepositIds.value = []
    showDeleted.value = false
    receiptImageErrors.value = {}
    isDeleteReasonOpen.value = false
    deleteReason.value = ''
    deleteTargetIds.value = []
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
  
  const firstPm = filteredPaymentMethods.value?.[0]
  const defaultPmId = firstPm ? (firstPm.code || firstPm.id) : null
  const defaultPm = firstPm ? (props.paymentMethods || []).find(x => x.code === defaultPmId || String(x.id) === String(defaultPmId)) : null
  const defaultNote = defaultPm ? `Deposit (${defaultPm.name})` : (firstPm ? `Deposit (${firstPm.name})` : '')

  depositForm.value = {
    id: null,
    bookingRoomId: null,
    amount: 0,
    paymentMethodId: defaultPmId,
    bankAccountId: null,
    date: systemDate.value,
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

async function syncDepositsFromBackend(dispatchEvents = false) {
  if (!props.bookingId) return
  try {
    const res = await fetchPayments(props.bookingId)
    const paymentsList = res.data?.data || res.data || []
    receiptImageErrors.value = {}
    
    localDeposits.value = paymentsList.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      bookingRoomId: p.booking_room_id || null,
      roomNumber: p.booking_room?.room_number || p.booking_room?.room?.room_number || null,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: p.currency || activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: p.image_url || p.image_path ? [p.image_url || p.image_path] : [],
      status: p.status,
      edit_flag: p.edit_flag,
      deleted_at: p.deleted_at || null,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account,
      bankAccountId: p.bank_account_id || p.bank_account?.id || null,
      bankAccount: p.bank_account || null,
      departmentId: p.department_id || null,
      outlet: p.outlet || null,
      currencyCode: p.currency || null,
      pack2: p.pack2,
      pack4: p.pack4
    }))

    const activeDeposits = localDeposits.value.filter(p =>
      Number(p.edit_flag ?? 0) === 0
        && !p.deleted_at
        && String(p.pack2 || '').toUpperCase() === 'DPR'
    )
    const totalValue = activeDeposits.reduce((sum, d) => sum + Number(d.amount), 0)

    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    if (dispatchEvents) {
      window.dispatchEvent(new CustomEvent('deposit-updated'))
      window.dispatchEvent(new CustomEvent('booking-updated'))
    }
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
  const rawPath = String(path).replaceAll(String.fromCharCode(92), '/')
  if (/^(blob:|data:|https?:\/\/)/i.test(rawPath)) return rawPath
  const cleanPath = rawPath.replace(/^\/+/, '').replace(/^api\//i, '')
  if (/^(storage|uploads)\//i.test(cleanPath)) return '/' + cleanPath
  // Use the same origin in deployed environments. Vite proxies /storage in
  // development, avoiding a hard-coded localhost host for uploaded receipts.
  return '/storage/' + cleanPath
}

function receiptImageKey(paymentId, imageIndex) {
  return String(paymentId) + ':' + String(imageIndex)
}

function hasReceiptImageError(paymentId, imageIndex) {
  return Boolean(receiptImageErrors.value[receiptImageKey(paymentId, imageIndex)])
}

function markReceiptImageError(paymentId, imageIndex) {
  receiptImageErrors.value = {
    ...receiptImageErrors.value,
    [receiptImageKey(paymentId, imageIndex)]: true,
  }
}

function openImage(url) {
  if (url) {
    imagePreviewError.value = false
    previewImageUrl.value = url
  }
}

async function addDeposit() {
  if (isSubmitting.value) return
  if (!depositForm.value.amount || depositForm.value.amount <= 0) {
    uiStore.showToast('Vui lòng nhập số tiền đặt cọc hợp lệ!', 'warning')
    return
  }
  if (!depositForm.value.note || !depositForm.value.note.trim()) {
    uiStore.showToast('Vui lòng nhập mô tả!', 'warning')
    return
  }

  // Check rule tạo cọc ngày cũ
  if (depositForm.value.date < systemDate.value && !canOperateOldDay.value) {
    uiStore.showToast('Tài khoản không được phân quyền thêm cọc cho ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay)!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận thêm đặt cọc',
    message: `Bạn có chắc chắn muốn thêm khoản đặt cọc ${Number(depositForm.value.amount).toLocaleString('en-US')} VND này?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  
  isSubmitting.value = true
  try {
    if (props.bookingId) {
      const formData = new FormData()
      formData.append('date', depositForm.value.date)
      formData.append('amount', Number(depositForm.value.amount))
      formData.append('payment_method_id', depositForm.value.paymentMethodId)
      if (depositForm.value.bookingRoomId) {
        formData.append('booking_room_id', depositForm.value.bookingRoomId)
      }
      formData.append('description', depositForm.value.note)
      const selectedBank = activeBankAccounts.value.find(account => String(account.id) === String(depositForm.value.bankAccountId))
      if (selectedBank) {
        formData.append('bank_account_id', String(selectedBank.id))
        formData.append('debit_account', selectedBank.accounting_account || '')
      }
      formData.append('currency', activeCurrency.value.code || 'VND')
      formData.append('department_id', props.departmentId || 'MR')
      if (selectedFile.value) {
        formData.append('image', selectedFile.value)
      }
      await createPayment(props.bookingId, formData)
      await syncDepositsFromBackend(true)
      uiStore.showToast('Đã thêm đặt cọc mới thành công!', 'success')
      resetForm()
    } else {
      const now = new Date()
      const timeStr = now.toTimeString().split(' ')[0].substring(0, 5)
      
      let rNo = null
      if (depositForm.value.bookingRoomId) {
        const targetRoom = availableRooms.value.find(r => String(getBookingRoomId(r)) === String(depositForm.value.bookingRoomId))
        if (targetRoom) {
          rNo = targetRoom.room_number || targetRoom.roomNumber || targetRoom.room?.room_number
        }
      }

      const newDep = {
        id: Date.now(),
        date: depositForm.value.date.split('-').reverse().join('/'),
        time: timeStr,
        paymentMethodId: depositForm.value.paymentMethodId,
        bookingRoomId: depositForm.value.bookingRoomId || null,
        roomNumber: rNo,
        note: depositForm.value.note,
        amount: Number(depositForm.value.amount),
        currency: activeCurrency.value.code || 'VND',
        recipient: depositForm.value.recipient || 'Admin',
        images: depositForm.value.image ? [depositForm.value.image] : [],
        status: 1,
        edit_flag: 0,
        reversal_ref: null,
        bankAccountId: depositForm.value.bankAccountId || null,
        debit_account: activeBankAccounts.value.find(account => String(account.id) === String(depositForm.value.bankAccountId))?.accounting_account || null,
        pack2: 'DPR'
      }
      
      localDeposits.value.push(newDep)
      const totalValue = localDeposits.value.reduce((sum, d) => sum + d.amount, 0)
      emit('update:deposits', localDeposits.value)
      emit('update:paymentValue', totalValue)
      
      resetForm()
      uiStore.showToast('Đã thêm đặt cọc mới!', 'success')
    }
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể thêm cọc!', 'error')
  } finally {
    isSubmitting.value = false
  }
}

function editDeposit() {
  if (isEditing.value) return
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
      bookingRoomId: dep.bookingRoomId || null,
      amount: dep.amount,
      paymentMethodId: dep.paymentMethodId,
      bankAccountId: dep.bankAccountId || null,
      date: dateVal,
      note: dep.note,
      recipient: dep.recipient,
      image: dep.images?.[0] || null
    }
  }
}

async function saveDeposit() {
  if (isSubmitting.value) return
  if (!depositForm.value.id) {
    await addDeposit()
    return
  }
  if (!depositForm.value.note || !depositForm.value.note.trim()) {
    uiStore.showToast('Vui lòng nhập mô tả!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận cập nhật đặt cọc',
    message: 'Bạn có chắc chắn muốn lưu các thay đổi cho khoản đặt cọc này?',
    confirmText: 'Đồng ý',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  
  isSubmitting.value = true
  try {
    if (props.bookingId) {
      const formData = new FormData()
      formData.append('_method', 'PUT')
      formData.append('payment_method_id', depositForm.value.paymentMethodId)
      formData.append('description', depositForm.value.note)
      await updatePayment(depositForm.value.id, formData)
      await syncDepositsFromBackend(true)
      uiStore.showToast('Cập nhật đặt cọc thành công!', 'success')
      resetForm()
      selectedDepositIds.value = []
    } else {
      const idx = localDeposits.value.findIndex(d => d.id === depositForm.value.id)
      if (idx !== -1) {
        localDeposits.value[idx].paymentMethodId = depositForm.value.paymentMethodId
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
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể lưu cọc!', 'error')
  } finally {
    isSubmitting.value = false
  }
}

async function deleteDeposits() {
  if (isSubmitting.value || isEditing.value) return
  if (selectedDepositIds.value.length === 0) {
    uiStore.showToast('Vui lòng chọn các cọc muốn xóa!', 'warning')
    return
  }

  // Kiểm tra rule xóa cọc ngày cũ
  for (const depId of selectedDepositIds.value) {
    const dep = localDeposits.value.find(d => d.id === depId)
    if (dep) {
      let depDate = dep.date
      if (depDate.includes('/')) {
        depDate = depDate.split('/').reverse().join('-')
      }
      if (depDate < systemDate.value && !canOperateOldDay.value) {
        uiStore.showToast('Bạn không có quyền xóa cọc phát sinh ở ngày cũ (RuleUserCorrectOrPostBillPaymentOldDay)! Chỉ được xóa cọc có ngày = ngày hệ thống.', 'warning')
        return
      }
    }
  }

  if (!props.bookingId) {
    localDeposits.value = localDeposits.value.filter(d => !selectedDepositIds.value.includes(d.id))
    const totalValue = localDeposits.value
      .filter(d => d.edit_flag === 0 && String(d.pack2 || '').toUpperCase() === 'DPR')
      .reduce((sum, d) => sum + Number(d.amount || 0), 0)
    emit('update:deposits', localDeposits.value)
    emit('update:paymentValue', totalValue)
    selectedDepositIds.value = []
    uiStore.showToast('Đã xóa cọc thành công!', 'success')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Hủy/Xóa đặt cọc',
    message: 'Bạn có chắc chắn muốn xóa đặt cọc đã chọn?',
    confirmText: 'Tiếp tục',
    cancelText: 'Quay lại'
  })
  if (!confirmed) return

  deleteTargetIds.value = [...selectedDepositIds.value]
  deleteReason.value = ''
  isDeleteReasonOpen.value = true
}

function closeDeleteReason() {
  if (isSubmitting.value) return
  isDeleteReasonOpen.value = false
  deleteReason.value = ''
  deleteTargetIds.value = []
}

async function confirmDelete() {
  const reason = deleteReason.value.trim()
  if (!reason) {
    uiStore.showToast('Vui lòng nhập lý do xóa đặt cọc!', 'warning')
    return
  }
  if (reason.length > 1000) {
    uiStore.showToast('Lý do xóa không được vượt quá 1000 ký tự!', 'warning')
    return
  }

  isSubmitting.value = true
  try {
    const targetIds = [...deleteTargetIds.value]
    const deletedIds = []
    const failedDeletes = []
    for (const depId of targetIds) {
      try {
        await deletePayment(depId, { reason })
        deletedIds.push(depId)
      } catch (err) {
        failedDeletes.push({ id: depId, error: err })
      }
    }
    await syncDepositsFromBackend(true)
    selectedDepositIds.value = failedDeletes.map(item => item.id)
    if (failedDeletes.length > 0) {
      const firstError = failedDeletes[0].error
      const message = firstError.response?.data?.message || 'Không thể xóa một hoặc nhiều khoản cọc.'
      uiStore.showToast(
        `Đã xóa ${deletedIds.length}/${targetIds.length} khoản cọc. ${message}`,
        'warning'
      )
    } else {
      uiStore.showToast('Đã xóa đặt cọc thành công!', 'success')
    }
    isDeleteReasonOpen.value = false
    deleteReason.value = ''
    deleteTargetIds.value = []
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa cọc!', 'error')
  } finally {
    isSubmitting.value = false
  }
}

function handleSplitAmount1Input(val) {
  const cleaned = cleanCurrencyValue(val)
  splitAmount1.value = Math.min(cleaned, splitOriginalAmount.value)
  if (splitMode.value === 2) {
    splitAmount2.value = Math.max(0, splitOriginalAmount.value - splitAmount1.value)
    splitAmount3.value = 0
  } else {
    const remain = Math.max(0, splitOriginalAmount.value - splitAmount1.value)
    splitAmount2.value = Math.floor(remain / 2)
    splitAmount3.value = remain - splitAmount2.value
  }
}

function handleSplitAmount2Input(val) {
  const cleaned = cleanCurrencyValue(val)
  splitAmount2.value = Math.min(cleaned, splitOriginalAmount.value)
  if (splitMode.value === 2) {
    splitAmount1.value = Math.max(0, splitOriginalAmount.value - splitAmount2.value)
    splitAmount3.value = 0
  } else {
    const remain = Math.max(0, splitOriginalAmount.value - splitAmount2.value)
    splitAmount1.value = Math.floor(remain / 2)
    splitAmount3.value = remain - splitAmount1.value
  }
}

function handleSplitAmount3Input(val) {
  const cleaned = cleanCurrencyValue(val)
  splitAmount3.value = Math.min(cleaned, splitOriginalAmount.value)
  const remain = Math.max(0, splitOriginalAmount.value - splitAmount3.value)
  splitAmount1.value = Math.floor(remain / 2)
  splitAmount2.value = remain - splitAmount1.value
}

async function splitDeposit() {
  if (isEditing.value) return
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
  setSplitMode(2)
  isSplitOpen.value = true
}

async function confirmSplit() {
  if (isEditing.value) return
  let amounts = []
  if (splitMode.value === 2) {
    amounts = [splitAmount1.value, splitAmount2.value]
  } else {
    amounts = [splitAmount1.value, splitAmount2.value, splitAmount3.value]
  }

  const total = amounts.reduce((sum, a) => sum + a, 0)
  if (Math.abs(total - splitOriginalAmount.value) > 0.01) {
    uiStore.showToast('Tổng các số tiền tách phải bằng số tiền gốc!', 'warning')
    return
  }
  if (amounts.some(a => a <= 0)) {
    uiStore.showToast('Tất cả số tiền tách phải lớn hơn 0!', 'warning')
    return
  }

  const targetId = selectedDepositIds.value[0]
  try {
    await splitPayment(targetId, { amounts, department_id: props.departmentId || 'MR' })
    await syncDepositsFromBackend(true)
    uiStore.showToast(`Tách cọc thành công (${amounts.length} phần)!`, 'success')
    selectedDepositIds.value = []
    isSplitOpen.value = false
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Không thể tách cọc!', 'error')
  }
}

async function handleSearchBookingInput(query) {
  transferDestSearch.value = query
  // Tải đầy đủ booking một lần rồi lọc cục bộ cả mã/tên booking, số phòng
  // và tên khách. API chỉ tìm được booking-level nên gửi query lên server
  // sẽ làm mất các phòng/khách hợp lệ khi người dùng gõ số phòng hoặc tên khách.
  if (searchResults.value.length > 0) return

  isSearchingDest.value = true
  const requestId = ++transferSearchRequestId
  try {
    const params = { status: '0,1', limit: 100 }
    const res = await fetchBookings(params)
    const bookings = res.data?.data || res.data || []
    if (requestId !== transferSearchRequestId) return
    searchResults.value = bookings.filter(b => String(b.id) !== String(props.bookingId) && (Number(b.status) === 0 || Number(b.status) === 1))
  } catch (err) {
    console.error(err)
    if (requestId === transferSearchRequestId) searchResults.value = []
  } finally {
    if (requestId === transferSearchRequestId) isSearchingDest.value = false
  }
}

function handleSearchFocus() {
  isFocused.value = true
  if (searchResults.value.length === 0 && !isSearchingDest.value) {
    handleSearchBookingInput(transferDestSearch.value || '')
  }
}

function selectTargetBookingOption(opt) {
  const b = opt.booking
  transferDestBooking.value = b
  transferDestRoomId.value = opt.type === 'room' || opt.type === 'guest' ? opt.bookingRoomId : null
  transferDestGuestId.value = opt.type === 'guest' ? opt.guestId : null
  if (opt.type === 'guest') {
    transferDestSearch.value = `${opt.roomNumber} | ${opt.guestName}`
    destBookingName.value = `Khách nhận: ${opt.guestName} (P.${opt.roomNumber} - ${b.booking_code})`
  } else if (opt.type === 'room') {
    transferDestSearch.value = `${opt.roomNumber} | Toàn bộ phòng`
    destBookingName.value = `Phòng nhận: ${opt.roomNumber} (${b.booking_code})`
  } else {
    transferDestRoomId.value = null
    transferDestGuestId.value = null
    transferDestSearch.value = `${opt.code} - ${opt.name}`
    destBookingName.value = `Booking nhận: ${opt.code} - ${opt.name}`
  }
  isFocused.value = false
}

function clearTransferSelection() {
  transferDestSearch.value = ''
  transferDestBooking.value = null
  transferDestRoomId.value = null
  transferDestGuestId.value = null
  destBookingName.value = ''
  handleSearchBookingInput('')
}

async function transferDeposit() {
  if (isEditing.value) return
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
  transferDestSearch.value = ''
  transferDestBooking.value = null
  transferDestRoomId.value = null
  transferDestGuestId.value = null
  destBookingName.value = ''
  searchResults.value = []
  isTransferOpen.value = true

  isSearchingDest.value = true
  const requestId = ++transferSearchRequestId
  try {
    const res = await fetchBookings({ limit: 100, status: '0,1' })
    const bookings = res.data?.data || res.data || []
    if (requestId !== transferSearchRequestId) return
    searchResults.value = bookings.filter(b => String(b.id) !== String(props.bookingId) && (Number(b.status) === 0 || Number(b.status) === 1))
  } catch (err) {
    console.error(err)
  } finally {
    if (requestId === transferSearchRequestId) isSearchingDest.value = false
  }
}

async function confirmTransfer() {
  if (isEditing.value) return
  if (!transferDestBooking.value) {
    uiStore.showToast('Vui lòng chọn mã booking nhận!', 'warning')
    return
  }
  const targetId = selectedDepositIds.value[0]
  const payload = {
    target_booking_id: transferDestBooking.value.id,
    department_id: props.departmentId || 'MR',
  }
  if (transferDestRoomId.value) payload.target_room_id = transferDestRoomId.value
  if (transferDestGuestId.value) payload.target_guest_id = transferDestGuestId.value
  try {
    await transferPayment(targetId, payload)
    await syncDepositsFromBackend(true)
    uiStore.showToast(`Đã chuyển cọc sang booking ${transferDestBooking.value.booking_code} thành công!`, 'success')
    selectedDepositIds.value = []
    isTransferOpen.value = false
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi chuyển cọc!', 'error')
  }
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  let str = String(val).replace(/[^\d.-]/g, '');
  if (!str) return '';
  
  let parts = str.split('.');
  if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
  parts[0] = Number(parts[0]).toLocaleString('en-US');
  return parts.join('.');
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0;
  const cleanStr = String(val).replace(/,/g, '');
  return Number(cleanStr) || 0;
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
  uiStore.showToast('Đã copy ngày đặt cọc!', 'success')
}
</script>
