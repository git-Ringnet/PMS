<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9990] flex items-start justify-center pt-6">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/45" @click="$emit('close')"></div>

      <!-- Modal -->
      <div class="relative bg-white rounded-lg shadow-2xl w-[98vw] max-w-[1600px] max-h-[90vh] flex flex-col z-10 border border-slate-300"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2 bg-[#243c5a] text-white rounded-t-lg cursor-move"
          @mousedown="startDragModal">
          <div class="flex items-center space-x-2 font-semibold text-xs tracking-wider">
            <i class="fa-solid fa-users text-blue-300"></i>
            <span>THÔNG TIN KHÁCH TRONG PHÒNG</span>
          </div>
          <div class="flex items-center gap-1.5">
            <!-- Quay lại (Cancel Edit) -->
            <button v-if="isEditing" @click="cancelEditing" class="header-btn bg-slate-600 text-white border-slate-500 hover:bg-slate-500">
              <i class="fa-solid fa-rotate-left mr-1"></i>Quay lại
            </button>
            <!-- Lưu (Save Edit) -->
            <button v-if="isEditing" @click="saveChanges" :disabled="saving" class="header-btn bg-sky-600 text-white border-sky-500 hover:bg-sky-500">
              <i class="fa-solid fa-floppy-disk mr-1"></i>{{ saving ? 'Đang lưu...' : 'Lưu' }}
            </button>

            <!-- Chỉnh sửa -->
            <button v-if="!isEditing" @click="startEditing" class="header-btn">
              <i class="fa-solid fa-pen-to-square mr-1"></i>Chỉnh sửa
            </button>
            <!-- Scan -->
            <button v-if="!isEditing" @click="handleScan" class="header-btn">
              <i class="fa-solid fa-camera mr-1"></i>Scan
            </button>
            <!-- Xuất Excel -->
            <button v-if="!isEditing" @click="handleExportExcel" class="header-btn">
              <i class="fa-solid fa-file-excel mr-1"></i>Xuất Excel
            </button>
            <!-- Cài đặt cột -->
            <button v-if="!isEditing" @click="showColSettings = !showColSettings" class="header-btn" title="Cài đặt cột">
              <i class="fa-solid fa-sliders mr-1"></i>Cài đặt
            </button>
            <button @click="$emit('close')" class="hover:text-white ml-2 bg-red-500/20 px-1.5 py-0.5 rounded cursor-pointer border-none"><i class="fa-solid fa-xmark text-red-400"></i></button>
          </div>
        </div>

        <!-- Column Settings Dropdown -->
        <div v-if="showColSettings" class="absolute top-10 right-4 z-50 bg-white border border-slate-200 rounded shadow-lg p-3 w-72">
          <div class="text-[11px] font-semibold text-slate-700 mb-2">Hiển thị / Ẩn cột</div>
          <div class="grid grid-cols-2 gap-1 max-h-72 overflow-y-auto">
            <label v-for="col in allColumns" :key="col.key" class="flex items-center gap-1.5 text-[11px] cursor-pointer py-0.5 text-slate-600 hover:text-slate-800">
              <input type="checkbox" v-model="col.visible" class="rounded border-slate-300 text-blue-600" />
              {{ col.label }}
            </label>
          </div>
          <button @click="showColSettings = false" class="mt-2 w-full text-center text-[11px] text-blue-600 hover:underline border-none bg-transparent cursor-pointer">Đóng</button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-16 text-slate-500 text-xs">
          <i class="fa-solid fa-spinner fa-spin mr-2"></i>Đang tải thông tin khách...
        </div>

        <!-- Table -->
        <div v-else class="overflow-auto flex-1">
          <table class="w-full text-[13px] border-collapse min-w-max">
            <thead class="sticky top-0 z-10 bg-slate-50">
              <tr>
                <th class="py-3 px-3 border-b border-r border-slate-200 text-center font-semibold text-slate-600 bg-slate-100/80 w-12">STT</th>
                <th v-for="col in visibleColumns" :key="col.key"
                  class="py-3 px-3 border-b border-r border-slate-200 text-left font-semibold text-slate-600 bg-slate-100/80 whitespace-nowrap"
                  :style="col.width ? `width:${col.width}` : ''">
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(roomGroup, gi) in (isEditing ? editData : guestData)" :key="gi">
                <!-- Room group header -->
                <tr class="bg-slate-100/60 font-bold border-b border-slate-200">
                  <td :colspan="visibleColumns.length + 1" class="py-2.5 px-4 text-[13px] text-slate-800 bg-[#f1f5f9]">
                    <i class="fa-solid fa-hotel mr-1.5 text-slate-500"></i>Room: {{ roomGroup.room_number || '(Chưa gán số)' }}
                    ({{ roomGroup.guests.length + roomGroup.children.length }} khách)
                    - {{ roomGroup.room_class_name }}
                  </td>
                </tr>
                <!-- Adult guests -->
                <tr
                  v-for="(guest, idx) in roomGroup.guests"
                  :key="'g-' + guest.id"
                  @click="!isEditing && openGuestDetail(roomGroup, guest, 'adult')"
                  class="border-b border-slate-150 transition-colors"
                  :class="[
                    idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/30',
                    isEditing ? '' : 'hover:bg-slate-50 cursor-pointer'
                  ]"
                >
                  <td class="py-2.5 px-3 border-r border-slate-200 text-center text-slate-500 font-semibold">{{ idx + 1 }}</td>
                  <td v-for="col in visibleColumns" :key="col.key" class="py-2 px-2 border-r border-slate-200 whitespace-nowrap text-slate-700">
                    <!-- KHI ĐANG CHỈNH SỬA -->
                    <template v-if="isEditing">
                      <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                      
                      <!-- Title dropdown -->
                      <template v-if="col.key === 'title'">
                        <select v-model="guest.title" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="t in titlesList" :key="t" :value="t">{{ t }}</option>
                        </select>
                      </template>

                      <!-- Nationality dropdown -->
                      <template v-if="col.key === 'nationality_code'">
                        <select v-model="guest.nationality_code" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="n in nationalitiesList" :key="n.code" :value="n.code">{{ n.label }}</option>
                        </select>
                      </template>

                      <!-- ID type dropdown -->
                      <template v-if="col.key === 'id_type'">
                        <select v-model="guest.id_type" class="table-input">
                          <option value="">Loại</option>
                          <option value="CCCD">CCCD</option>
                          <option value="CMND">CMND</option>
                          <option value="Hộ chiếu">Hộ chiếu</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </template>

                      <!-- Residence type dropdown -->
                      <template v-if="col.key === 'residence_type'">
                        <select v-model="guest.residence_type" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option value="Thường trú">Thường trú</option>
                          <option value="Tạm trú">Tạm trú</option>
                        </select>
                      </template>

                      <!-- Guest type dropdown -->
                      <template v-if="col.key === 'guest_type'">
                        <select v-model="guest.guest_type" class="table-input">
                          <option value="">Loại khách</option>
                          <option value="FIT">FIT</option>
                          <option value="GIT">GIT</option>
                          <option value="VIP">VIP</option>
                          <option value="Crew">Crew</option>
                          <option value="Long Stay">Long Stay</option>
                        </select>
                      </template>

                      <!-- Entry purpose dropdown -->
                      <template v-if="col.key === 'entry_purpose'">
                        <select v-model="guest.entry_purpose" class="table-input">
                          <option value="">Mục đích</option>
                          <option value="Du lịch">Du lịch</option>
                          <option value="Công tác">Công tác</option>
                          <option value="Thăm thân">Thăm thân</option>
                          <option value="Học tập">Học tập</option>
                          <option value="Đầu tư">Đầu tư</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </template>

                      <!-- Province dropdown -->
                      <template v-if="col.key === 'province'">
                        <select v-model="guest.province" @change="handleProvinceChange(`g-${guest.id}`, guest, guest.province)" class="table-input select-geo">
                          <option value="">-- Chọn --</option>
                          <option v-for="p in provincesList" :key="p.code" :value="p.name">{{ p.name }}</option>
                        </select>
                      </template>

                      <!-- District dropdown -->
                      <template v-if="col.key === 'district'">
                        <select v-model="guest.district" @change="handleDistrictChange(`g-${guest.id}`, guest, guest.district)" class="table-input select-geo" :disabled="!guest.province">
                          <option value="">-- Chọn --</option>
                          <option v-for="d in (districtsForLine[`g-${guest.id}`] || [])" :key="d.code" :value="d.name">{{ d.name }}</option>
                        </select>
                      </template>

                      <!-- Ward dropdown -->
                      <template v-if="col.key === 'ward'">
                        <select v-model="guest.ward" class="table-input select-geo" :disabled="!guest.district">
                          <option value="">-- Chọn --</option>
                          <option v-for="w in (wardsForLine[`g-${guest.id}`] || [])" :key="w.code" :value="w.name">{{ w.name }}</option>
                        </select>
                      </template>

                      <!-- Date Fields -->
                      <template v-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)">
                        <input v-model="guest[col.key]" type="date" class="table-input py-0.5" />
                      </template>

                      <!-- Text fields -->
                      <template v-if="['full_name', 'id_number', 'phone', 'email', 'visa_no', 'border_gate', 'note', 'address'].includes(col.key)">
                        <input v-model="guest[col.key]" type="text" class="table-input" />
                      </template>
                    </template>

                    <!-- KHI CHỈ XEM -->
                    <template v-else>
                      <div class="truncate-cell text-[12.5px]" :style="col.width ? `max-width:${col.width}` : ''" :title="getDisplayTitle(guest, col)">
                        <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                        <template v-else-if="col.key === 'nationality_code'">{{ getNationalityLabel(guest.nationality_code) }}</template>
                        <template v-else-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)">
                          {{ formatDate(guest[col.key]) }}
                        </template>
                        <template v-else>{{ guest[col.key] || '—' }}</template>
                      </div>
                    </template>
                  </td>
                </tr>
                <!-- Children -->
                <tr
                  v-for="(child, cidx) in roomGroup.children"
                  :key="'c-' + child.id"
                  @click="!isEditing && openGuestDetail(roomGroup, child, 'child')"
                  class="border-b border-slate-150 transition-colors"
                  :class="[
                    (roomGroup.guests.length + cidx) % 2 === 0 ? 'bg-white' : 'bg-slate-50/30',
                    isEditing ? '' : 'hover:bg-slate-50 cursor-pointer'
                  ]"
                >
                  <td class="py-2.5 px-3 border-r border-slate-200 text-center text-slate-500 font-semibold">{{ roomGroup.guests.length + cidx + 1 }}</td>
                  <td v-for="col in visibleColumns" :key="col.key" class="py-2 px-2 border-r border-slate-200 whitespace-nowrap text-slate-700">
                    <!-- KHI ĐANG CHỈNH SỬA TRẺ EM -->
                    <template v-if="isEditing">
                      <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                      
                      <!-- Title dropdown -->
                      <template v-if="col.key === 'title'">
                        <select v-model="child.title" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="t in titlesList" :key="t" :value="t">{{ t }}</option>
                        </select>
                      </template>

                      <!-- Nationality dropdown -->
                      <template v-if="col.key === 'nationality_code'">
                        <select v-model="child.nationality_code" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="n in nationalitiesList" :key="n.code" :value="n.code">{{ n.label }}</option>
                        </select>
                      </template>

                      <!-- ID type dropdown -->
                      <template v-if="col.key === 'id_type'">
                        <select v-model="child.id_type" class="table-input">
                          <option value="">Loại</option>
                          <option value="CCCD">CCCD</option>
                          <option value="CMND">CMND</option>
                          <option value="Hộ chiếu">Hộ chiếu</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </template>

                      <!-- Residence type dropdown -->
                      <template v-if="col.key === 'residence_type'">
                        <select v-model="child.residence_type" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option value="Thường trú">Thường trú</option>
                          <option value="Tạm trú">Tạm trú</option>
                        </select>
                      </template>

                      <!-- Guest type dropdown -->
                      <template v-if="col.key === 'guest_type'">
                        <select v-model="child.guest_type" class="table-input">
                          <option value="">Loại khách</option>
                          <option value="FIT">FIT</option>
                          <option value="GIT">GIT</option>
                          <option value="VIP">VIP</option>
                          <option value="Crew">Crew</option>
                          <option value="Long Stay">Long Stay</option>
                        </select>
                      </template>

                      <!-- Entry purpose dropdown -->
                      <template v-if="col.key === 'entry_purpose'">
                        <select v-model="child.entry_purpose" class="table-input">
                          <option value="">Mục đích</option>
                          <option value="Du lịch">Du lịch</option>
                          <option value="Công tác">Công tác</option>
                          <option value="Thăm thân">Thăm thân</option>
                          <option value="Học tập">Học tập</option>
                          <option value="Đầu tư">Đầu tư</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </template>

                      <!-- Province dropdown -->
                      <template v-if="col.key === 'province'">
                        <select v-model="child.province" @change="handleProvinceChange(`c-${child.id}`, child, child.province)" class="table-input select-geo">
                          <option value="">-- Chọn --</option>
                          <option v-for="p in provincesList" :key="p.code" :value="p.name">{{ p.name }}</option>
                        </select>
                      </template>

                      <!-- District dropdown -->
                      <template v-if="col.key === 'district'">
                        <select v-model="child.district" @change="handleDistrictChange(`c-${child.id}`, child, child.district)" class="table-input select-geo" :disabled="!child.province">
                          <option value="">-- Chọn --</option>
                          <option v-for="d in (districtsForLine[`c-${child.id}`] || [])" :key="d.code" :value="d.name">{{ d.name }}</option>
                        </select>
                      </template>

                      <!-- Ward dropdown -->
                      <template v-if="col.key === 'ward'">
                        <select v-model="child.ward" class="table-input select-geo" :disabled="!child.district">
                          <option value="">-- Chọn --</option>
                          <option v-for="w in (wardsForLine[`c-${child.id}`] || [])" :key="w.code" :value="w.name">{{ w.name }}</option>
                        </select>
                      </template>

                      <!-- Date Fields -->
                      <template v-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)">
                        <input v-model="child[col.key]" type="date" class="table-input py-0.5" />
                      </template>

                      <!-- Text fields -->
                      <template v-if="['full_name', 'id_number', 'phone', 'email', 'visa_no', 'border_gate', 'note', 'address'].includes(col.key)">
                        <input v-model="child[col.key]" type="text" class="table-input" />
                      </template>
                    </template>

                    <!-- KHI CHỈ XEM -->
                    <template v-else>
                      <div class="truncate-cell text-[12.5px]" :style="col.width ? `max-width:${col.width}` : ''" :title="getDisplayTitle(child, col)">
                        <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                        <template v-else-if="col.key === 'nationality_code'">{{ getNationalityLabel(child.nationality_code) }}</template>
                        <template v-else-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)">
                          {{ formatDate(child[col.key]) }}
                        </template>
                        <template v-else>{{ child[col.key] || '—' }}</template>
                      </div>
                    </template>
                  </td>
                </tr>
                <!-- Empty row if no guests -->
                <tr v-if="roomGroup.guests.length === 0 && roomGroup.children.length === 0">
                  <td :colspan="visibleColumns.length + 1" class="py-3 px-4 text-center text-slate-400 text-[13px] italic border-b border-slate-200">
                    Chưa có thông tin khách
                  </td>
                </tr>
              </template>
              <!-- Overall empty -->
              <tr v-if="guestData.length === 0">
                <td :colspan="visibleColumns.length + 1" class="py-16 text-center text-slate-400 text-[13px]">
                  Chưa có dữ liệu. Vui lòng lưu thông tin đăng ký trước.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer -->
        <div class="flex justify-end px-4 py-2.5 border-t border-slate-200 bg-slate-50 rounded-b-lg">
          <button @click="$emit('close')" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-[13px] rounded-md hover:bg-slate-50 transition-colors shadow-xs cursor-pointer font-medium">
            <i class="fa-solid fa-xmark mr-1.5"></i>Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Scan Modal -->
  <Teleport to="body">
    <div v-if="showScanModal" class="fixed inset-0 z-[10000] flex items-center justify-center">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/45" @click="closeScanModal"></div>

      <!-- Content -->
      <div class="relative bg-white rounded shadow-2xl w-[450px] flex flex-col z-10 overflow-hidden font-sans border border-slate-200">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2.5 bg-[#243c5a] text-white select-none">
          <div class="flex items-center gap-1.5 font-semibold text-xs tracking-wider">
            <i class="fa-solid fa-camera text-blue-300"></i>
            <span>QUÉT CCCD / VNeID</span>
          </div>
          <button @click="closeScanModal" class="text-slate-300 hover:text-white border-none bg-transparent cursor-pointer">
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>

        <!-- Tab header -->
        <div class="border-b border-slate-200 bg-slate-50 px-4 pt-1 flex">
          <div class="px-4 py-2 text-[12.5px] font-bold text-[#0f7d8c] border-b-2 border-[#0f7d8c] cursor-pointer">
            QR Scanner
          </div>
        </div>

        <!-- Body -->
        <div class="p-4 flex flex-col gap-3 text-slate-800 text-[12.5px] overflow-y-auto max-h-[75vh]">
          
          <!-- Scan Input Box -->
          <div class="relative">
            <input 
              ref="scanInputRef"
              v-model="scanRawText"
              @keydown.enter="handleScanSubmit"
              type="text"
              placeholder="Nhấp vào đây và quét mã CCCD..."
              class="w-full border border-slate-300 rounded px-3 py-2 text-xs focus:outline-none focus:border-[#0f7d8c] focus:ring-1 focus:ring-[#0f7d8c] shadow-inner bg-slate-50/50"
            />
          </div>

          <!-- Target Guest Selection -->
          <div class="bg-slate-50 border border-slate-200 p-2.5 rounded flex flex-col gap-2">
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Điền thông tin vào khách hàng:</label>
              <select v-model="targetScanGuestKey" class="w-full border border-slate-300 rounded px-2 py-1 text-xs bg-white text-slate-700 focus:outline-none focus:border-[#0f7d8c]">
                <option v-for="opt in getScanTargetOptions()" :key="opt.key" :value="opt.key">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            
            <div class="flex items-center justify-between mt-1">
              <label class="flex items-center gap-1.5 cursor-pointer text-slate-600 select-none">
                <input type="checkbox" v-model="scanContinuous" class="rounded text-[#0f7d8c] focus:ring-[#0f7d8c] accent-[#0f7d8c]" />
                <span>Quét liên tục</span>
              </label>
              
              <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span class="text-[11px] text-emerald-600 font-semibold">ASM Scanner đang chạy</span>
              </div>
            </div>
          </div>

          <!-- Device Selector -->
          <div class="flex flex-col gap-1">
            <div class="font-bold text-slate-700 text-xs">Thiết bị</div>
            <select v-model="selectedScanDevice" class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs bg-white text-slate-700 focus:outline-none focus:border-[#0f7d8c]">
              <option value="barcode">Máy quét mã vạch USB (Giả lập bàn phím)</option>
              <option value="camera" disabled>Camera máy tính (Chưa kết nối)</option>
            </select>
          </div>

          <!-- Instruction Text -->
          <p class="text-slate-500 text-[11.5px] leading-relaxed italic bg-slate-50 p-2 border border-slate-200 rounded">
            Sử dụng máy quét mã vạch để thực hiện quét mã CCCD trên CCCD vật lý hoặc trên thẻ căn cước điện tử, hoặc quét mã CCCD trên ứng dụng VNeID của khách. Nhấn để thực hiện.
          </p>

          <!-- Guideline Steps -->
          <div class="flex flex-col gap-2.5 mt-1 border-t border-slate-100 pt-3">
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 flex items-center justify-center bg-[#0f7d8c] text-white rounded-full font-bold text-xs shrink-0">1</span>
              <span>Cắm thiết bị quét mã với máy tính.</span>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 flex items-center justify-center bg-[#0f7d8c] text-white rounded-full font-bold text-xs shrink-0">2</span>
              <span>Thiết lập cài đặt thiết bị lần đầu tiên: <a href="#" class="text-[#0f7d8c] hover:underline font-semibold">Xem hướng dẫn</a></span>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 flex items-center justify-center bg-[#0f7d8c] text-white rounded-full font-bold text-xs shrink-0">3</span>
              <span>Cài đặt và khởi động <a href="#" class="text-[#0f7d8c] hover:underline font-bold">ứng dụng ASM Scanner</a></span>
            </div>
            <div class="flex items-center gap-3">
              <span class="w-6 h-6 flex items-center justify-center bg-[#0f7d8c] text-white rounded-full font-bold text-xs shrink-0">4</span>
              <span>Quét mã CCCD hoặc mã định danh điện tử</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end px-4 py-2.5 border-t border-slate-200 bg-slate-50">
          <button @click="closeScanModal" class="px-4 py-1.5 bg-[#0f7d8c] hover:bg-[#0b5c67] text-white text-xs font-bold rounded shadow-sm transition-colors cursor-pointer border-none flex items-center gap-1.5">
            <i class="fa-solid fa-circle-xmark"></i> Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { fetchBookingGuests, initBookingGuests, bulkUpdateBookingGuests } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import GuestDetailModal from './GuestDetailModal.vue'

const props = defineProps({
  show: Boolean,
  bookingId: [Number, String],
})

const emit = defineEmits(['close', 'saved'])
const uiStore = useUiStore()

const loading = ref(false)
const saving = ref(false)
const isEditing = ref(false)
const guestData = ref([])
const editData = ref([])
const showColSettings = ref(false)
const showDetailModal = ref(false)
const selectedRoom = ref(null)
const selectedGuest = ref(null)
const selectedGuestType = ref('adult')

// Tỉnh/quận/xã cache cho từng dòng
const provincesList = ref([])
const districtsCache = ref({})
const wardsCache = ref({})
const districtsForLine = ref({})
const wardsForLine = ref({})

// ==================== CỘT ====================
const allColumns = ref([
  { key: 'room_number',      label: 'Số phòng',         visible: true,  width: '70px' },
  { key: 'title',            label: 'Danh xưng',        visible: true,  width: '75px' },
  { key: 'full_name',        label: 'Họ và tên',        visible: true,  width: '140px' },
  { key: 'dob',              label: 'Ngày sinh',        visible: true,  width: '90px' },
  { key: 'nationality_code', label: 'Quốc tịch',        visible: true,  width: '160px' },
  { key: 'id_type',          label: 'Loại giấy tờ',    visible: true,  width: '100px' },
  { key: 'id_number',        label: 'Số giấy tờ',      visible: true,  width: '110px' },
  { key: 'passport_expiry',  label: 'Ngày hết hạn',    visible: true,  width: '95px' },
  { key: 'province',         label: 'Tỉnh thành',       visible: true,  width: '110px' },
  { key: 'district',         label: 'Quận/ Huyện',     visible: true,  width: '100px' },
  { key: 'ward',             label: 'Phường/ Xã',      visible: true,  width: '100px' },
  { key: 'phone',            label: 'Điện thoại',       visible: true,  width: '100px' },
  { key: 'email',            label: 'Email',             visible: true,  width: '140px' },
  { key: 'address',          label: 'Địa chỉ',          visible: true,  width: '160px' },
  { key: 'guest_type',       label: 'Loại khách',       visible: true,  width: '80px' },
  { key: 'visa_no',          label: 'Số Visa',           visible: true,  width: '90px' },
  { key: 'residence_type',   label: 'Thường trú/Tạm trú', visible: true, width: '120px' },
  { key: 'temp_residence_to',label: 'Tạm trú đến',     visible: true,  width: '95px' },
  { key: 'entry_date',       label: 'Ngày nhập cảnh',  visible: true,  width: '100px' },
  { key: 'visa_expiry_date', label: 'Ngày hết hạn',    visible: true,  width: '100px' },
  { key: 'entry_purpose',    label: 'Mục đích nhập cảnh', visible: true,  width: '130px' },
  { key: 'border_gate',      label: 'Cửa khẩu',        visible: true,  width: '100px' },
  { key: 'note',             label: 'Ghi chú',          visible: true,  width: '120px' },
])

const visibleColumns = computed(() => allColumns.value.filter(c => c.visible))

const titlesList = ['Mr.', 'Mrs.', 'Ms.', 'Miss.', 'Kid.', 'Baby.', 'Dr.', 'Prof.']

// ==================== NATIONALITIES ====================
const nationalitiesList = [
  { code: 'VN', label: 'VNM - Vietnam ( Việt Nam )' },
  { code: 'US', label: 'USA - United States ( Mỹ )' },
  { code: 'CN', label: 'CHN - China ( Trung Quốc )' },
  { code: 'KR', label: 'KOR - Korea ( Hàn Quốc )' },
  { code: 'JP', label: 'JPN - Japan ( Nhật Bản )' },
  { code: 'FR', label: 'FRA - France ( Pháp )' },
  { code: 'DE', label: 'DEU - Germany ( Đức )' },
  { code: 'GB', label: 'GBR - United Kingdom ( Anh )' },
  { code: 'AU', label: 'AUS - Australia ( Úc )' },
  { code: 'SG', label: 'SGP - Singapore' },
  { code: 'TH', label: 'THA - Thailand ( Thái Lan )' },
  { code: 'MY', label: 'MYS - Malaysia' },
  { code: 'RU', label: 'RUS - Russia ( Nga )' },
]

const nationalityMap = {
  VN: 'Vietnam ( Việt Nam )',
  US: 'United States ( Mỹ )',
  CN: 'China ( Trung Quốc )',
  KR: 'Korea ( Hàn Quốc )',
  JP: 'Japan ( Nhật Bản )',
  FR: 'France ( Pháp )',
  DE: 'Germany ( Đức )',
  GB: 'United Kingdom ( Anh )',
  AU: 'Australia ( Úc )',
  SG: 'Singapore',
  TH: 'Thailand ( Thái Lan )',
  MY: 'Malaysia',
  RU: 'Russia ( Nga )',
}

function getNationalityLabel(code) {
  if (!code) return '—'
  return nationalityMap[code] || code
}

function formatDate(d) {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString('vi-VN')
  } catch { return d }
}

// ==================== LOAD DATA ====================
async function loadGuests() {
  if (!props.bookingId) return
  loading.value = true
  try {
    // Tự động init guests nếu chưa có
    await initBookingGuests(props.bookingId)
    const res = await fetchBookingGuests(props.bookingId)
    if (res.data?.success) {
      guestData.value = res.data.data || []
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải thông tin khách!', 'error')
  } finally {
    loading.value = false
  }
}

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

watch(() => props.show, (v) => {
  if (v) {
    modalPos.value = { x: 0, y: 0 }
    if (props.bookingId) loadGuests()
  }
})

// ==================== GEOGRAPHY API CALLS ====================
async function loadProvinces() {
  if (provincesList.value.length > 0) return
  try {
    const res = await fetch('https://provinces.open-api.vn/api/p/')
    const data = await res.json()
    provincesList.value = data.map(p => ({ code: p.code, name: p.name }))
  } catch (err) {
    console.error("Lỗi fetch tỉnh thành:", err)
    provincesList.value = [
      { code: 1, name: "Thành phố Hà Nội" },
      { code: 79, name: "Thành phố Hồ Chí Minh" },
      { code: 48, name: "Thành phố Đà Nẵng" },
      { code: 56, name: "Tỉnh Khánh Hòa" },
      { code: 68, name: "Tỉnh Lâm Đồng" },
      { code: 31, name: "Tỉnh Hải Phòng" },
      { code: 74, name: "Tỉnh Bình Dương" },
      { code: 77, name: "Tỉnh Bà Rịa - Vũng Tàu" },
      { code: 92, name: "Thành phố Cần Thơ" }
    ]
  }
}

async function fetchDistricts(provinceName) {
  if (!provinceName) return []
  if (districtsCache.value[provinceName]) return districtsCache.value[provinceName]
  
  const prov = provincesList.value.find(p => p.name === provinceName)
  if (!prov) return []
  
  try {
    const res = await fetch(`https://provinces.open-api.vn/api/p/${prov.code}?depth=2`)
    const data = await res.json()
    const list = (data.districts || []).map(d => ({ code: d.code, name: d.name }))
    districtsCache.value[provinceName] = list
    return list
  } catch (err) {
    console.error("Lỗi fetch quận huyện:", err)
    return []
  }
}

async function fetchWards(provinceName, districtName) {
  if (!districtName) return []
  const cacheKey = `${provinceName}_${districtName}`
  if (wardsCache.value[cacheKey]) return wardsCache.value[cacheKey]
  
  const dists = districtsCache.value[provinceName] || []
  const dist = dists.find(d => d.name === districtName)
  if (!dist) return []
  
  try {
    const res = await fetch(`https://provinces.open-api.vn/api/d/${dist.code}?depth=2`)
    const data = await res.json()
    const list = (data.wards || []).map(w => ({ code: w.code, name: w.name }))
    wardsCache.value[cacheKey] = list
    return list
  } catch (err) {
    console.error("Lỗi fetch phường xã:", err)
    return []
  }
}

async function initGeoForLine(lineKey, provinceName, districtName) {
  if (provinceName) {
    districtsForLine.value[lineKey] = await fetchDistricts(provinceName)
  }
  if (provinceName && districtName) {
    wardsForLine.value[lineKey] = await fetchWards(provinceName, districtName)
  }
}

async function handleProvinceChange(lineKey, row, newProvinceName) {
  row.province = newProvinceName
  row.district = ''
  row.ward = ''
  districtsForLine.value[lineKey] = []
  wardsForLine.value[lineKey] = []
  if (newProvinceName) {
    districtsForLine.value[lineKey] = await fetchDistricts(newProvinceName)
  }
}

async function handleDistrictChange(lineKey, row, newDistrictName) {
  row.district = newDistrictName
  row.ward = ''
  wardsForLine.value[lineKey] = []
  if (row.province && newDistrictName) {
    wardsForLine.value[lineKey] = await fetchWards(row.province, newDistrictName)
  }
}

function fixDateFields(row) {
  const dateFields = ['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date']
  dateFields.forEach(f => {
    if (row[f]) {
      row[f] = row[f].substring(0, 10)
    } else {
      row[f] = ''
    }
  })
}

// ==================== ACTIONS ====================
async function startEditing() {
  loading.value = true
  try {
    await loadProvinces()
    editData.value = JSON.parse(JSON.stringify(guestData.value))
    
    // Khởi tạo geo data cho từng dòng
    for (const group of editData.value) {
      for (const guest of group.guests) {
        const key = `g-${guest.id}`
        fixDateFields(guest)
        await initGeoForLine(key, guest.province, guest.district)
      }
      for (const child of group.children) {
        const key = `c-${child.id}`
        fixDateFields(child)
        await initGeoForLine(key, child.province, child.district)
      }
    }
    isEditing.value = true
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể chuẩn bị dữ liệu chỉnh sửa!', 'error')
  } finally {
    loading.value = false
  }
}

function cancelEditing() {
  isEditing.value = false
  editData.value = []
}

async function saveChanges() {
  saving.value = true
  try {
    const allGuests = []
    const allChildren = []
    
    for (const group of editData.value) {
      allGuests.push(...group.guests)
      allChildren.push(...group.children)
    }
    
    const res = await bulkUpdateBookingGuests(props.bookingId, {
      guests: allGuests,
      children: allChildren
    })
    
    if (res.data?.success) {
      uiStore.showToast('Cập nhật thông tin khách thành công!', 'success')
      // Đồng bộ về guestData
      guestData.value = JSON.parse(JSON.stringify(editData.value))
      isEditing.value = false
      emit('saved')
      const bc1 = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('pms-room-updates') : null
      if (bc1) bc1.postMessage('rooms-updated')
      const bc2 = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('pms-channel') : null
      if (bc2) bc2.postMessage('rooms-updated')
    } else {
      uiStore.showToast(res.data?.message || 'Lưu thất bại!', 'error')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra!', 'error')
  } finally {
    saving.value = false
  }
}

function openGuestDetail(room, guest, type) {
  selectedRoom.value = room
  selectedGuest.value = guest
  selectedGuestType.value = type
  showDetailModal.value = true
}

function handleGuestSaved(updatedGuest) {
  showDetailModal.value = false
  for (const group of guestData.value) {
    if (selectedGuestType.value === 'adult') {
      const idx = group.guests.findIndex(g => g.id === updatedGuest.id)
      if (idx !== -1) { 
        group.guests[idx] = { ...group.guests[idx], ...updatedGuest }
        emit('saved')
        break 
      }
    } else {
      const idx = group.children.findIndex(c => c.id === updatedGuest.id)
      if (idx !== -1) { 
        group.children[idx] = { ...group.children[idx], ...updatedGuest }
        emit('saved')
        break 
      }
    }
  }
}

const showScanModal = ref(false)
const scanRawText = ref('')
const targetScanGuestKey = ref('')
const scanContinuous = ref(true)
const selectedScanDevice = ref('barcode')
const scanInputRef = ref(null)

function handleScan() {
  scanRawText.value = ''
  showScanModal.value = true
  
  const opts = getScanTargetOptions()
  const emptyOpt = opts.find(o => o.isEmpty)
  if (emptyOpt) {
    targetScanGuestKey.value = emptyOpt.key
  } else if (opts.length > 0) {
    targetScanGuestKey.value = opts[0].key
  }
  
  nextTick(() => {
    if (scanInputRef.value) {
      scanInputRef.value.focus()
    }
  })
}

function closeScanModal() {
  showScanModal.value = false
  scanRawText.value = ''
}

function getScanTargetOptions() {
  const options = []
  const data = isEditing.value ? editData.value : guestData.value
  
  data.forEach(group => {
    group.guests.forEach((guest, index) => {
      options.push({
        key: `g-${guest.id}`,
        label: `Room ${group.room_number || '—'} | Adult - ${guest.full_name || `Khách ${index + 1} (Trống)`}`,
        isEmpty: !guest.full_name || guest.full_name.startsWith('Guest '),
        group,
        row: guest,
        type: 'adult'
      })
    })
    group.children.forEach((child, index) => {
      options.push({
        key: `c-${child.id}`,
        label: `Room ${group.room_number || '—'} | Child - ${child.full_name || `Trẻ em ${index + 1} (Trống)`}`,
        isEmpty: !child.full_name,
        group,
        row: child,
        type: 'child'
      })
    })
  })
  return options
}

function parseCccdDate(str) {
  if (!str || str.length !== 8) return null
  const day = str.substring(0, 2)
  const month = str.substring(2, 4)
  const year = str.substring(4, 8)
  return `${year}-${month}-${day}`
}

function parseAddressDetails(addressStr) {
  if (!addressStr) return { province: '', district: '', ward: '', address: '' }
  const cleanStr = addressStr.trim()
  const parts = cleanStr.split(',').map(s => s.trim())
  
  let province = ''
  let district = ''
  let ward = ''
  
  if (parts.length >= 1) province = parts[parts.length - 1]
  if (parts.length >= 2) district = parts[parts.length - 2]
  if (parts.length >= 3) ward = parts[parts.length - 3]
  
  return { province, district, ward, address: cleanStr }
}

async function handleScanSubmit() {
  const rawText = scanRawText.value.trim()
  scanRawText.value = ''
  
  if (!rawText) return
  
  const parts = rawText.split('|')
  if (parts.length < 5) {
    uiStore.showToast('Mã quét không đúng định dạng CCCD Việt Nam!', 'warning')
    return
  }
  
  const idNumber = parts[0]
  const fullName = parts[2]
  const dobRaw = parts[3]
  const gender = parts[4]
  const addressRaw = parts[5]
  const idIssueRaw = parts[6]
  
  const dob = parseCccdDate(dobRaw)
  const idIssueDate = parseCccdDate(idIssueRaw)
  const title = gender === 'Nam' ? 'Mr.' : (gender === 'Nữ' ? 'Mrs.' : 'Mr.')
  
  const { province, district, ward, address } = parseAddressDetails(addressRaw)
  
  const options = getScanTargetOptions()
  const targetOpt = options.find(o => o.key === targetScanGuestKey.value)
  
  if (!targetOpt) {
    uiStore.showToast('Không tìm thấy dòng khách để điền dữ liệu!', 'error')
    return
  }
  
  const row = targetOpt.row
  row.full_name = fullName
  row.id_number = idNumber
  row.id_type = 'CCCD'
  if (dob) row.dob = dob
  row.title = title
  row.address = address
  row.nationality_code = 'VN'
  row.province = province
  row.district = district
  row.ward = ward
  if (idIssueDate) row.id_issue_date = idIssueDate
  
  await initGeoForLine(targetOpt.key, province, district)
  
  if (!isEditing.value) {
    try {
      const isAdult = targetOpt.type === 'adult'
      await bulkUpdateBookingGuests(props.bookingId, {
        guests: isAdult ? [row] : [],
        children: !isAdult ? [row] : []
      })
      emit('saved')
      uiStore.showToast(`Quét CCCD của khách ${fullName} thành công!`, 'success')
    } catch (err) {
      console.error(err)
      uiStore.showToast('Lỗi khi lưu dữ liệu quét!', 'error')
    }
  } else {
    uiStore.showToast(`Đã điền thông tin quét của khách ${fullName} vào bảng.`, 'success')
  }
  
  if (scanContinuous.value) {
    const nextEmptyOpt = options.find(o => o.isEmpty && o.key !== targetScanGuestKey.value)
    if (nextEmptyOpt) {
      targetScanGuestKey.value = nextEmptyOpt.key
    }
  }
  
  nextTick(() => {
    if (scanInputRef.value) {
      scanInputRef.value.focus()
    }
  })
}

function handleExportExcel() {
  try {
    let html = `<meta charset="utf-8"><table>`
    html += `<tr>`
    visibleColumns.value.forEach(col => {
      html += `<th style="background-color: #38bdf8; color: #000000; font-weight: bold; padding: 6px; border: 1px solid #cbd5e1;">${col.label}</th>`
    })
    html += `</tr>`
    
    guestData.value.forEach(group => {
      group.guests.forEach(guest => {
        html += `<tr>`
        visibleColumns.value.forEach(col => {
          let val = ''
          if (col.key === 'room_number') val = group.room_number || ''
          else if (col.key === 'nationality_code') val = getNationalityLabel(guest.nationality_code)
          else if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
            val = formatDate(guest[col.key])
          } else val = guest[col.key] || ''
          html += `<td style="padding: 4px; border: 1px solid #e2e8f0;">${val}</td>`
        })
        html += `</tr>`
      })
      
      group.children.forEach(child => {
        html += `<tr>`
        visibleColumns.value.forEach(col => {
          let val = ''
          if (col.key === 'room_number') val = group.room_number || ''
          else if (col.key === 'nationality_code') val = getNationalityLabel(child.nationality_code)
          else if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
            val = formatDate(child[col.key])
          } else val = child[col.key] || ''
          html += `<td style="padding: 4px; border: 1px solid #e2e8f0;">${val}</td>`
        })
        html += `</tr>`
      })
    })
    
    html += `</table>`
    
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `Danh_Sach_Khach_Booking_${props.bookingId || 'Export'}.xls`
    a.click()
    URL.revokeObjectURL(url)
    uiStore.showToast('Xuất Excel thành công!', 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi xuất file Excel!', 'error')
  }
}

function getDisplayTitle(row, col) {
  if (col.key === 'nationality_code') return getNationalityLabel(row.nationality_code)
  if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
    return formatDate(row[col.key])
  }
  return row[col.key] || ''
}
</script>

<style scoped>
.header-btn {
  padding: 4px 10px;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: #e2e8f0;
  transition: all 150ms ease;
  border: 1px solid rgba(255, 255, 255, 0.15);
  cursor: pointer;
}
.header-btn:hover {
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
}
.table-input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 2.5px 5px;
  font-size: 12.5px;
  background-color: #ffffff;
  color: #1e293b;
  min-height: 25px;
  height: 25px;
  box-sizing: border-box;
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
}
.table-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.select-geo {
  max-width: 130px;
  text-overflow: ellipsis;
}
.truncate-cell {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}
.scan-box-wrapper {
  background: #f8fafc;
  border-radius: 8px;
  padding: 12px;
}
</style>
