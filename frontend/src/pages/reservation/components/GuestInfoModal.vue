<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9990] flex items-start justify-center pt-6 overflow-y-auto pb-6">
      <!-- Overlay -->
      <div class="fixed inset-0 bg-black/45 backdrop-blur-[1px]" @click="$emit('close')"></div>

      <!-- Modal -->
      <div class="modal relative z-10 w-[98vw] max-w-[1550px] max-h-[92vh] flex flex-col font-sans text-[13px] my-auto bg-white rounded-lg shadow-2xl border border-slate-300"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        
        <!-- ==================== HEADER ==================== -->
        <div class="flex items-center justify-between px-4 py-2.5 bg-[#1E2D4A] text-white rounded-t-lg select-none cursor-move"
          @mousedown="startDragModal">
          <div class="flex items-center gap-2 font-bold text-xs tracking-wider uppercase">
            <i class="fa-solid fa-users text-[#B9CDF8] text-sm"></i>
            <span>THÔNG TIN KHÁCH TRONG PHÒNG</span>
          </div>

          <div class="flex items-center gap-1.5">
            <!-- Chỉnh sửa trực tiếp trên bảng -->
            <button v-if="!isEditing" @click="startEditing" type="button" class="header-btn" title="Chỉnh sửa trực tiếp trên bảng">
              <i class="fa-solid fa-pen-to-square mr-1"></i>Chỉnh sửa
            </button>
            <button v-if="isEditing" @click="cancelEditing" type="button" class="header-btn bg-slate-600 text-white hover:bg-slate-500">
              <i class="fa-solid fa-rotate-left mr-1"></i>Quay lại
            </button>
            <button v-if="isEditing" @click="saveChanges" :disabled="saving" type="button" class="header-btn bg-[#2F6FED] text-white hover:bg-[#2560D6]">
              <i class="fa-solid fa-floppy-disk mr-1"></i>{{ saving ? 'Đang lưu...' : 'Lưu bảng' }}
            </button>

            <!-- Scan CCCD / VNeID -->
            <button v-if="!isEditing" @click="handleScan" type="button" class="header-btn" title="Quét CCCD / VNeID">
              <i class="fa-solid fa-camera mr-1"></i>Scan
            </button>

            <!-- Xuất Excel -->
            <button v-if="!isEditing" @click="handleExportExcel" type="button" class="header-btn" title="Xuất file Excel">
              <i class="fa-solid fa-file-excel mr-1"></i>Xuất Excel
            </button>

            <!-- Cài đặt cột -->
            <button v-if="!isEditing" @click="showColSettings = !showColSettings" type="button" class="header-btn" title="Hiển thị / ẩn cột">
              <i class="fa-solid fa-sliders mr-1"></i>Cài đặt
            </button>

            <!-- Nút đóng [X] -->
            <button @click="$emit('close')" type="button" class="hover:bg-red-500/30 ml-2 px-1.5 py-0.5 rounded cursor-pointer border-none bg-transparent text-red-300 hover:text-white transition-colors">
              <i class="fa-solid fa-xmark text-sm"></i>
            </button>
          </div>
        </div>

        <!-- Dropdown Cài đặt ẩn / hiện cột -->
        <div v-if="showColSettings" class="absolute top-11 right-4 z-50 bg-white border border-slate-200 rounded-lg shadow-xl p-3 w-72">
          <div class="text-[11px] font-semibold text-slate-700 mb-2">Hiển thị / Ẩn cột dữ liệu</div>
          <div class="grid grid-cols-2 gap-1 max-h-72 overflow-y-auto">
            <label v-for="col in allColumns" :key="col.key" class="flex items-center gap-1.5 text-[11px] cursor-pointer py-0.5 text-slate-600 hover:text-slate-800">
              <input type="checkbox" v-model="col.visible" class="rounded border-slate-300 text-blue-600" />
              {{ col.label }}
            </label>
          </div>
          <button @click="showColSettings = false" class="mt-2 w-full text-center text-[11px] text-blue-600 hover:underline border-none bg-transparent cursor-pointer">Đóng</button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 text-slate-500 bg-white">
          <i class="fa-solid fa-spinner fa-spin text-2xl text-[#2F6FED] mb-2"></i>
          <span class="text-xs font-medium">Đang tải thông tin khách...</span>
        </div>

        <!-- ==================== TABLE VIEW (MÀN HÌNH CHÍNH) ==================== -->
        <div v-else class="overflow-auto flex-1 bg-white">
          <table class="w-full text-[12.5px] border-collapse min-w-max">
            <thead class="sticky top-0 z-10 bg-slate-100/90 border-b border-slate-200 text-slate-700">
              <tr>
                <th class="py-2.5 px-3 border-r border-slate-200 text-center font-semibold w-12 bg-slate-100">STT</th>
                <th class="py-2.5 px-3 border-r border-slate-200 text-center font-semibold w-24 bg-slate-100">Thao tác</th>
                <th v-for="col in visibleColumns" :key="col.key"
                  class="py-2.5 px-3 border-r border-slate-200 text-left font-semibold whitespace-nowrap bg-slate-100"
                  :style="col.width ? `width:${col.width}` : ''">
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(roomGroup, gi) in (isEditing ? editData : guestData)" :key="gi">
                <!-- Room group header -->
                <tr class="bg-slate-100/80 font-bold border-b border-slate-200">
                  <td :colspan="visibleColumns.length + 2" class="py-2 px-4 text-[12.5px] text-[#1E2D4A] bg-[#eef2f6]">
                    <i class="fa-solid fa-hotel mr-1.5 text-slate-500"></i>Room: {{ roomGroup.room_number || '(Chưa gán)' }}
                    ({{ (roomGroup.guests || []).length + (roomGroup.children || []).length }} khách)
                    - {{ roomGroup.room_class_name }}
                  </td>
                </tr>

                <!-- Adult guests -->
                <tr
                  v-for="(guest, idx) in roomGroup.guests"
                  :key="'g-' + guest.id"
                  @dblclick="!isEditing && openGuestDetail(roomGroup, guest, 'adult')"
                  class="border-b border-slate-200 transition-colors"
                  :class="[
                    idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40',
                    isEditing ? '' : 'hover:bg-blue-50/70 cursor-pointer'
                  ]"
                  title="Nhấp đúp chuột (Double click) để mở thẻ thông tin chi tiết khách"
                >
                  <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-500 font-semibold">{{ idx + 1 }}</td>
                  
                  <!-- Cột thao tác: Thẻ khách -->
                  <td class="py-1 px-2 border-r border-slate-200 text-center">
                    <button
                      type="button"
                      @click.stop="openGuestDetail(roomGroup, guest, 'adult')"
                      class="px-2 py-0.5 text-xs bg-blue-50 text-blue-600 hover:bg-blue-100 rounded border border-blue-200 cursor-pointer inline-flex items-center gap-1 font-medium transition-colors"
                      title="Mở Thẻ thông tin khách"
                    >
                      <i class="fa-solid fa-id-card text-xs"></i>
                      <span>Thẻ khách</span>
                    </button>
                  </td>

                  <td v-for="col in visibleColumns" :key="col.key" class="py-1.5 px-2 border-r border-slate-200 whitespace-nowrap text-slate-700">
                    <!-- Khi đang chỉnh sửa trực tiếp trên bảng -->
                    <template v-if="isEditing">
                      <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                      
                      <!-- Title dropdown -->
                      <template v-else-if="col.key === 'title'">
                        <select v-model="guest.title" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="t in titlesList" :key="t" :value="t">{{ t }}</option>
                        </select>
                      </template>

                      <!-- Nationality dropdown -->
                      <template v-else-if="col.key === 'nationality_code'">
                        <select v-model="guest.nationality_code" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="n in nationalitiesList" :key="n.code" :value="n.code">{{ n.label }}</option>
                        </select>
                      </template>

                      <!-- ID type dropdown -->
                      <template v-else-if="col.key === 'id_type'">
                        <select v-model="guest.id_type" class="table-input">
                          <option value="">Loại</option>
                          <option v-for="it in idTypesList" :key="it.id" :value="getIdTypeValue(it)">{{ it.name }}</option>
                          <option v-if="guest.id_type && !idTypesList.some(it => getIdTypeValue(it) === guest.id_type || it.name === guest.id_type)" :value="guest.id_type">{{ guest.id_type }}</option>
                        </select>
                      </template>

                      <!-- Residence type dropdown -->
                      <template v-else-if="col.key === 'residence_type'">
                        <select v-model="guest.residence_type" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="rt in residenceTypesList" :key="rt.code || rt.name" :value="rt.name_new_form || rt.name">{{ rt.name_new_form || rt.name }}</option>
                          <option v-if="guest.residence_type && !residenceTypesList.some(rt => (rt.name_new_form || rt.name) === guest.residence_type)" :value="guest.residence_type">{{ guest.residence_type }}</option>
                        </select>
                      </template>

                      <!-- Guest type dropdown -->
                      <template v-else-if="col.key === 'guest_type'">
                        <select v-model="guest.guest_type" class="table-input">
                          <option value="">Loại</option>
                          <option v-for="gt in guestTypesList" :key="gt.id" :value="getGuestTypeValue(gt)">{{ gt.name }}</option>
                          <option v-if="guest.guest_type && !guestTypesList.some(gt => getGuestTypeValue(gt) === guest.guest_type || gt.name === guest.guest_type)" :value="guest.guest_type">{{ guest.guest_type }}</option>
                        </select>
                      </template>

                      <!-- Entry purpose dropdown -->
                      <template v-else-if="col.key === 'entry_purpose'">
                        <select v-model="guest.entry_purpose" class="table-input">
                          <option value="">Mục đích</option>
                          <option v-for="ep in entryPurposesList" :key="ep.id" :value="ep.name">{{ ep.name }}</option>
                          <option v-if="guest.entry_purpose && !entryPurposesList.some(ep => ep.name === guest.entry_purpose)" :value="guest.entry_purpose">{{ guest.entry_purpose }}</option>
                        </select>
                      </template>

                      <!-- Border gate dropdown -->
                      <template v-else-if="col.key === 'border_gate'">
                        <select v-model="guest.border_gate" class="table-input">
                          <option value="">-- Cửa khẩu --</option>
                          <option v-for="bg in borderGatesList" :key="bg.id" :value="bg.name">{{ bg.name }}</option>
                          <option v-if="guest.border_gate && !borderGateNames.includes(guest.border_gate)" :value="guest.border_gate">{{ guest.border_gate }}</option>
                        </select>
                      </template>

                      <!-- Province dropdown -->
                      <template v-else-if="col.key === 'province'">
                        <select v-model="guest.province" @change="handleProvinceChange(`g-${guest.id}`, guest, guest.province)" class="table-input select-geo">
                          <option value="">-- Chọn --</option>
                          <option v-for="p in provincesList" :key="p.code" :value="p.name">{{ p.name }}</option>
                        </select>
                      </template>

                      <!-- District dropdown -->
                      <template v-else-if="col.key === 'district'">
                        <select v-model="guest.district" @change="handleDistrictChange(`g-${guest.id}`, guest, guest.district)" class="table-input select-geo" :disabled="!guest.province">
                          <option value="">-- Chọn --</option>
                          <option v-for="d in (districtsForLine[`g-${guest.id}`] || [])" :key="d.code" :value="d.name">{{ d.name }}</option>
                        </select>
                      </template>

                      <!-- Ward dropdown -->
                      <template v-else-if="col.key === 'ward'">
                        <select v-model="guest.ward" class="table-input select-geo" :disabled="!guest.district">
                          <option value="">-- Chọn --</option>
                          <option v-for="w in (wardsForLine[`g-${guest.id}`] || [])" :key="w.code" :value="w.name">{{ w.name }}</option>
                        </select>
                      </template>

                      <!-- Date Fields -->
                      <template v-else-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)">
                        <input v-model="guest[col.key]" type="date" class="table-input" />
                      </template>

                      <!-- Text fields -->
                      <template v-else>
                        <input v-model="guest[col.key]" type="text" class="table-input" />
                      </template>
                    </template>

                    <!-- Khi chỉ xem -->
                    <template v-else>
                      <div class="truncate-cell text-[12px]" :style="col.width ? `max-width:${col.width}` : ''" :title="getDisplayTitle(guest, col)">
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

                <!-- Child guests -->
                <tr
                  v-for="(child, cidx) in roomGroup.children"
                  :key="'c-' + child.id"
                  @dblclick="!isEditing && openGuestDetail(roomGroup, child, 'child')"
                  class="border-b border-slate-200 transition-colors"
                  :class="[
                    (roomGroup.guests.length + cidx) % 2 === 0 ? 'bg-white' : 'bg-slate-50/40',
                    isEditing ? '' : 'hover:bg-blue-50/70 cursor-pointer'
                  ]"
                  title="Nhấp đúp chuột (Double click) để mở thẻ thông tin chi tiết trẻ em"
                >
                  <td class="py-2 px-3 border-r border-slate-200 text-center text-slate-500 font-semibold">{{ roomGroup.guests.length + cidx + 1 }}</td>
                  
                  <!-- Cột thao tác: Thẻ trẻ em -->
                  <td class="py-1 px-2 border-r border-slate-200 text-center">
                    <button
                      type="button"
                      @click.stop="openGuestDetail(roomGroup, child, 'child')"
                      class="px-2 py-0.5 text-xs bg-purple-50 text-purple-600 hover:bg-purple-100 rounded border border-purple-200 cursor-pointer inline-flex items-center gap-1 font-medium transition-colors"
                      title="Mở Thẻ thông tin trẻ em"
                    >
                      <i class="fa-solid fa-child text-xs"></i>
                      <span>Thẻ trẻ</span>
                    </button>
                  </td>

                  <td v-for="col in visibleColumns" :key="col.key" class="py-1.5 px-2 border-r border-slate-200 whitespace-nowrap text-slate-700">
                    <template v-if="isEditing">
                      <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                      <template v-else-if="col.key === 'title'">
                        <select v-model="child.title" class="table-input">
                          <option value="">-- Chọn --</option>
                          <option v-for="t in titlesList" :key="t" :value="t">{{ t }}</option>
                        </select>
                      </template>
                      <template v-else-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to'].includes(col.key)">
                        <input v-model="child[col.key]" type="date" class="table-input" />
                      </template>
                      <template v-else>
                        <input v-model="child[col.key]" type="text" class="table-input" />
                      </template>
                    </template>
                    <template v-else>
                      <div class="truncate-cell text-[12px]">
                        <template v-if="col.key === 'room_number'">{{ roomGroup.room_number || '—' }}</template>
                        <template v-else-if="col.key === 'nationality_code'">{{ getNationalityLabel(child.nationality_code) }}</template>
                        <template v-else-if="['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to'].includes(col.key)">
                          {{ formatDate(child[col.key]) }}
                        </template>
                        <template v-else>{{ child[col.key] || '—' }}</template>
                      </div>
                    </template>
                  </td>
                </tr>

                <!-- Trống -->
                <tr v-if="(roomGroup.guests || []).length === 0 && (roomGroup.children || []).length === 0">
                  <td :colspan="visibleColumns.length + 2" class="py-3 px-4 text-center text-slate-400 text-[13px] italic border-b border-slate-200">
                    Chưa có thông tin khách trong phòng này
                  </td>
                </tr>
              </template>

              <!-- Overall empty -->
              <tr v-if="guestData.length === 0">
                <td :colspan="visibleColumns.length + 2" class="py-16 text-center text-slate-400 text-[13px]">
                  Chưa có dữ liệu. Vui lòng lưu thông tin đăng ký trước.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ==================== FOOTER ==================== -->
        <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-200 bg-slate-50 rounded-b-lg select-none">
          <div class="flex items-center gap-2 text-xs text-slate-500">
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium text-[11.5px] border border-blue-200/60">
              💡 Mẹo: Nhấp đúp chuột (Double click) vào bất kỳ dòng nào để mở Thẻ thông tin khách
            </span>
          </div>

          <button @click="$emit('close')" type="button" class="px-4 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs rounded-md hover:bg-slate-50 transition-colors shadow-xs cursor-pointer font-medium flex items-center gap-1.5">
            <i class="fa-solid fa-xmark"></i>Đóng
          </button>
        </div>

      </div>
    </div>
  </Teleport>

  <!-- ==================== GUEST DETAIL MODAL (THẺ CHI TIẾT THEO MẪU HTML) ==================== -->
  <GuestDetailModal
    :show="showDetailModal"
    :room="selectedRoom"
    :guest="selectedGuest"
    :guest-type="selectedGuestType"
    @close="showDetailModal = false"
    @saved="handleGuestSaved"
  />

  <!-- ==================== SCAN MODAL (CCCD / VNeID) ==================== -->
  <Teleport to="body">
    <div v-if="showScanModal" class="fixed inset-0 z-[10000] flex items-center justify-center">
      <div class="absolute inset-0 bg-black/45" @click="closeScanModal"></div>

      <div class="relative bg-white rounded-lg shadow-2xl w-[460px] flex flex-col z-10 overflow-hidden font-sans border border-slate-200">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2.5 bg-[#1E2D4A] text-white select-none">
          <div class="flex items-center gap-2 font-semibold text-xs tracking-wider">
            <i class="fa-solid fa-camera text-[#B9CDF8]"></i>
            <span>QUÉT CCCD / VNeID</span>
          </div>
          <button @click="closeScanModal" class="text-slate-300 hover:text-white border-none bg-transparent cursor-pointer text-lg leading-none">
            ×
          </button>
        </div>

        <!-- Tab header -->
        <div class="border-b border-slate-200 bg-slate-50 px-4 pt-1 flex">
          <div class="px-4 py-2 text-[12.5px] font-bold text-[#2F6FED] border-b-2 border-[#2F6FED] cursor-pointer">
            QR Scanner
          </div>
        </div>

        <!-- Body -->
        <div class="p-4 flex flex-col gap-3 text-slate-800 text-[12.5px] overflow-y-auto max-h-[75vh]">
          <div class="relative">
            <input 
              ref="scanInputRef"
              v-model="scanRawText"
              @keydown.enter="handleScanSubmit"
              type="text"
              placeholder="Nhấp vào đây và quét mã CCCD..."
              class="w-full border border-slate-300 rounded-md px-3 py-2 text-xs focus:outline-none focus:border-[#2F6FED] focus:ring-2 focus:ring-[#2F6FED]/20 bg-slate-50/60 font-mono"
            />
          </div>

          <!-- Target Guest Selection -->
          <div class="bg-slate-50 border border-slate-200 p-2.5 rounded-lg flex flex-col gap-2">
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1 uppercase tracking-wider">Điền thông tin vào khách hàng:</label>
              <select v-model="targetScanGuestKey" class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs bg-white text-slate-700 focus:outline-none focus:border-[#2F6FED]">
                <option v-for="opt in getScanTargetOptions()" :key="opt.key" :value="opt.key">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            
            <div class="flex items-center justify-between mt-1">
              <label class="flex items-center gap-1.5 cursor-pointer text-slate-600 select-none">
                <input type="checkbox" v-model="scanContinuous" class="rounded text-[#2F6FED] focus:ring-[#2F6FED]" />
                <span>Quét liên tục</span>
              </label>
              
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span class="text-[11px] text-emerald-600 font-semibold">ASM Scanner đang chạy</span>
              </div>
            </div>
          </div>

          <!-- Device Selector -->
          <div class="flex flex-col gap-1">
            <div class="font-bold text-slate-700 text-xs">Thiết bị</div>
            <select v-model="selectedScanDevice" class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs bg-white text-slate-700 focus:outline-none focus:border-[#2F6FED]">
              <option value="barcode">Máy quét mã vạch USB (Giả lập bàn phím)</option>
              <option value="camera" disabled>Camera máy tính (Chưa kết nối)</option>
            </select>
          </div>

          <!-- Guideline -->
          <p class="text-slate-500 text-[11.5px] leading-relaxed italic bg-slate-50 p-2 border border-slate-200 rounded">
            Sử dụng máy quét mã vạch để thực hiện quét mã QR trên thẻ CCCD hoặc trên ứng dụng VNeID của khách. Nhấn Enter sau khi quét.
          </p>
        </div>

        <!-- Footer -->
        <div class="flex justify-end px-4 py-2.5 border-t border-slate-200 bg-slate-50">
          <button @click="closeScanModal" class="px-4 py-1.5 bg-[#2F6FED] hover:bg-[#2560D6] text-white text-xs font-semibold rounded-md transition-colors cursor-pointer border-none flex items-center gap-1.5">
            <i class="fa-solid fa-circle-xmark"></i> Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import {
  fetchBookingGuests,
  initBookingGuests,
  bulkUpdateBookingGuests,
  fetchNationalities,
  fetchGuestDefinitions,
  syncGeoData
} from '@/services/booking-service'
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

// State Thẻ chi tiết khách
const showDetailModal = ref(false)
const selectedRoom = ref(null)
const selectedGuest = ref(null)
const selectedGuestType = ref('adult')

// Tỉnh/quận/xã cache cho từng dòng khi chỉnh sửa inline
const provincesList = ref([])
const districtsCache = ref({})
const wardsCache = ref({})
const districtsForLine = ref({})
const wardsForLine = ref({})

// Columns
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

// Master Data Definitions
const guestDefinitions = ref({
  titles: [],
  border_gates: [],
  entry_purposes: [],
  guest_types: [],
  id_types: [],
  residence_types: [],
})

const titlesList = computed(() => {
  if (guestDefinitions.value.titles?.length > 0) {
    return guestDefinitions.value.titles.map(t => t.name)
  }
  return ['Boy.', 'Girl.', 'Inf', 'Kid.', 'Mr.', 'Ms.']
})

const borderGatesList = computed(() => guestDefinitions.value.border_gates || [])
const borderGateNames = computed(() => borderGatesList.value.map(g => g.name))
const entryPurposesList = computed(() => guestDefinitions.value.entry_purposes || [])
const guestTypesList = computed(() => guestDefinitions.value.guest_types || [])
const idTypesList = computed(() => guestDefinitions.value.id_types || [])
const residenceTypesList = computed(() => {
  if (guestDefinitions.value.residence_types?.length > 0) {
    return guestDefinitions.value.residence_types
  }
  return [
    { code: '1', name: 'Địa chỉ thường trú', name_new_form: 'Thường trú' },
    { code: '2', name: 'Địa chỉ tạm trú', name_new_form: 'Tạm trú' },
    { code: '3', name: 'Địa chỉ khác', name_new_form: 'Khác' },
  ]
})

function getIdTypeValue(it) {
  if (!it) return ''
  const upper = (it.code || '').toUpperCase()
  if (upper === 'PASSPORT') return 'Hộ chiếu'
  if (upper === 'OTHER') return 'Khác'
  return it.code || it.name
}

function getGuestTypeValue(gt) {
  if (!gt) return ''
  if (gt.code === 'CREW') return 'Crew'
  if (gt.code === 'LONGSTAY') return 'Long Stay'
  return gt.code
}

async function loadGuestDefinitions() {
  try {
    const res = await fetchGuestDefinitions()
    if (res.data?.success) {
      guestDefinitions.value = res.data.data || {}
    }
  } catch (err) {
    console.error('Lỗi tải danh mục thông tin khách:', err)
  }
}

// Nationalities
const nationalitiesList = ref([])
const nationalityMap = ref({})

async function loadNationalities() {
  if (nationalitiesList.value.length > 0) return
  try {
    const res = await fetchNationalities()
    if (res.data?.success) {
      const list = res.data.data || []
      nationalitiesList.value = list.map(item => ({
        code: item.asm_code || item.nationality_id || '',
        label: `${item.nationality_id || item.asm_code || '—'} - ${item.asm_name || item.nationality_name || ''}`
      })).filter(item => item.code !== '')

      const map = {}
      list.forEach(item => {
        const c = item.asm_code || item.nationality_id
        if (c) map[c] = item.asm_name || item.nationality_name
      })
      nationalityMap.value = map
    }
  } catch (err) {
    console.error('Lỗi tải danh sách quốc tịch:', err)
  }
}

function getNationalityLabel(code) {
  if (!code) return '—'
  return nationalityMap.value[code] || code
}

function formatDate(d) {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString('vi-VN')
  } catch { return d }
}

function getDisplayTitle(row, col) {
  if (col.key === 'nationality_code') return getNationalityLabel(row.nationality_code)
  if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
    return formatDate(row[col.key])
  }
  return row[col.key] || ''
}

// Load Guests
async function loadGuests() {
  if (!props.bookingId) return
  loading.value = true
  try {
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

// ==================== MỞ THẺ CHI TIẾT KHÁCH ====================
function openGuestDetail(roomGroup, guest, type) {
  selectedRoom.value = roomGroup
  selectedGuest.value = guest
  selectedGuestType.value = type
  showDetailModal.value = true
}

function handleGuestSaved(updatedGuest) {
  showDetailModal.value = false
  for (const group of guestData.value) {
    if (selectedGuestType.value === 'adult') {
      const idx = (group.guests || []).findIndex(g => g.id === updatedGuest.id)
      if (idx !== -1) {
        Object.assign(group.guests[idx], updatedGuest)
        break
      }
    } else {
      const idx = (group.children || []).findIndex(c => c.id === updatedGuest.id)
      if (idx !== -1) {
        Object.assign(group.children[idx], updatedGuest)
        break
      }
    }
  }
  emit('saved')
  notifyBroadcast()
}

function notifyBroadcast() {
  const bc1 = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('pms-room-updates') : null
  if (bc1) bc1.postMessage('rooms-updated')
  const bc2 = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('pms-channel') : null
  if (bc2) bc2.postMessage('rooms-updated')
}

// ==================== GEOGRAPHY API ====================
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
    syncGeoData({ province: newProvinceName }).catch(() => {})
  }
}

async function handleDistrictChange(lineKey, row, newDistrictName) {
  row.district = newDistrictName
  row.ward = ''
  wardsForLine.value[lineKey] = []
  if (row.province && newDistrictName) {
    wardsForLine.value[lineKey] = await fetchWards(row.province, newDistrictName)
    syncGeoData({ province: row.province, district: newDistrictName }).catch(() => {})
  }
}

function fixDateFields(row) {
  const dateFields = ['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date']
  dateFields.forEach(f => {
    if (row[f]) row[f] = row[f].substring(0, 10)
    else row[f] = ''
  })
}

// Inline edit actions
async function startEditing() {
  loading.value = true
  try {
    await loadProvinces()
    editData.value = JSON.parse(JSON.stringify(guestData.value))
    for (const group of editData.value) {
      for (const guest of group.guests || []) {
        const key = `g-${guest.id}`
        fixDateFields(guest)
        await initGeoForLine(key, guest.province, guest.district)
      }
      for (const child of group.children || []) {
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
      allGuests.push(...(group.guests || []))
      allChildren.push(...(group.children || []))
    }
    const res = await bulkUpdateBookingGuests(props.bookingId, {
      guests: allGuests,
      children: allChildren
    })
    if (res.data?.success) {
      uiStore.showToast('Cập nhật thông tin khách thành công!', 'success')
      guestData.value = JSON.parse(JSON.stringify(editData.value))
      isEditing.value = false
      emit('saved')
      notifyBroadcast()
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

// ==================== SCAN MODAL ====================
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
    if (scanInputRef.value) scanInputRef.value.focus()
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
    (group.guests || []).forEach((guest, index) => {
      options.push({
        key: `g-${guest.id}`,
        label: `Room ${group.room_number || '—'} | Adult - ${guest.full_name || `Khách ${index + 1} (Trống)`}`,
        isEmpty: !guest.full_name || guest.full_name.startsWith('Guest '),
        group,
        row: guest,
        type: 'adult'
      })
    });
    (group.children || []).forEach((child, index) => {
      options.push({
        key: `c-${child.id}`,
        label: `Room ${group.room_number || '—'} | Child - ${child.full_name || `Trẻ em ${index + 1} (Trống)`}`,
        isEmpty: !child.full_name,
        group,
        row: child,
        type: 'child'
      })
    });
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
      notifyBroadcast()
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
    if (scanInputRef.value) scanInputRef.value.focus()
  })
}

// ==================== EXPORT EXCEL ====================
function handleExportExcel() {
  try {
    let html = `<meta charset="utf-8"><table><tr>`
    visibleColumns.value.forEach(col => {
      html += `<th style="background-color: #1E2D4A; color: #ffffff; font-weight: bold; padding: 8px; border: 1px solid #cbd5e1;">${col.label}</th>`
    })
    html += `</tr>`
    
    guestData.value.forEach(group => {
      (group.guests || []).forEach(guest => {
        html += `<tr>`
        visibleColumns.value.forEach(col => {
          let val = ''
          if (col.key === 'room_number') val = group.room_number || ''
          else if (col.key === 'nationality_code') val = getNationalityLabel(guest.nationality_code)
          else if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
            val = formatDate(guest[col.key])
          } else val = guest[col.key] || ''
          html += `<td style="padding: 6px; border: 1px solid #e2e8f0;">${val}</td>`
        })
        html += `</tr>`
      });

      (group.children || []).forEach(child => {
        html += `<tr>`
        visibleColumns.value.forEach(col => {
          let val = ''
          if (col.key === 'room_number') val = group.room_number || ''
          else if (col.key === 'nationality_code') val = getNationalityLabel(child.nationality_code)
          else if (['dob', 'id_issue_date', 'passport_expiry', 'temp_residence_to', 'entry_date', 'visa_expiry_date'].includes(col.key)) {
            val = formatDate(child[col.key])
          } else val = child[col.key] || ''
          html += `<td style="padding: 6px; border: 1px solid #e2e8f0;">${val}</td>`
        })
        html += `</tr>`
      });
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

// Draggable Modal
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
    loadNationalities()
    loadGuestDefinitions()
    if (props.bookingId) loadGuests()
  }
})

onMounted(() => {
  loadNationalities()
  loadGuestDefinitions()
})
</script>

<style scoped>
.header-btn {
  padding: 4px 9px;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 5px;
  font-size: 11px;
  font-weight: 500;
  color: #e2e8f0;
  transition: all 150ms ease;
  border: 1px solid rgba(255, 255, 255, 0.15);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
}
.header-btn:hover {
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
}
.table-input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 2px 5px;
  font-size: 12px;
  background-color: #ffffff;
  color: #1e293b;
  min-height: 25px;
  height: 25px;
  box-sizing: border-box;
}
.table-input:focus {
  outline: none;
  border-color: #2F6FED;
  box-shadow: 0 0 0 2px rgba(47, 111, 237, 0.15);
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
</style>
