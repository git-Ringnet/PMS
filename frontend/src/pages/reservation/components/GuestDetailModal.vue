<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center">
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>

      <!-- Modal -->
      <div class="relative bg-white rounded shadow-xl w-[820px] max-h-[90vh] flex flex-col z-10"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-2 bg-[#243c5a] text-white rounded-t cursor-move"
          @mousedown="startDragModal">
          <span class="font-semibold text-xs tracking-wider">THÔNG TIN KHÁCH LƯU TRÚ</span>
          <button @click="$emit('close')" class="hover:text-red-200 transition-colors border-none bg-transparent cursor-pointer">
            <i class="fa-solid fa-xmark text-base"></i>
          </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 bg-gray-50 px-4 pt-2">
          <button
            v-for="tab in tabs" :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'px-5 py-2 text-[13px] font-semibold border rounded-t-md mr-1 transition-colors',
              activeTab === tab.key
                ? 'bg-white border-b-white text-[#243c5a] border-gray-300 -mb-px'
                : 'text-gray-500 border-transparent hover:text-gray-700'
            ]"
          >{{ tab.label }}</button>
        </div>

        <!-- Body -->
        <div class="overflow-y-auto flex-1 p-4">
          <!-- TAB 1: Thông tin chung -->
          <template v-if="activeTab === 'info'">
            <!-- Thông tin phòng -->
            <div class="mb-4">
              <div class="text-xs font-bold text-red-500 uppercase tracking-wider mb-2">Thông tin phòng *</div>
              <div class="flex gap-6 text-xs text-slate-700 bg-slate-50 rounded-lg px-4 py-2.5 border border-slate-200">
                <span><span class="text-slate-500 font-medium">Room No:</span> <b>{{ room?.room_number || '—' }}</b></span>
                <span><span class="text-slate-500 font-medium">Rate:</span> <b>{{ formatMoney(room?.rate) }}</b></span>
                <span><span class="text-slate-500 font-medium">Arrival:</span> <b>{{ formatDate(room?.arrival_date) }}</b></span>
                <span><span class="text-slate-500 font-medium">Departure:</span> <b>{{ formatDate(room?.departure_date) }}</b></span>
              </div>
            </div>

            <!-- Thông tin khách hàng -->
            <div class="mb-2">
              <div class="text-xs font-bold text-red-500 uppercase tracking-wider mb-3">Thông tin khách hàng *</div>
              <div class="grid grid-cols-3 gap-x-4 gap-y-2.5">
                <!-- Họ và tên -->
                <div class="col-span-1">
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Họ và tên</label>
                  <input v-model="form.full_name" type="text" placeholder="Họ và tên"
                    class="input-field" />
                </div>
                <!-- Ngày sinh -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ngày sinh</label>
                  <input v-model="form.dob" type="date" class="input-field" />
                </div>
                <!-- Danh xưng -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Danh xưng</label>
                  <select v-model="form.title" class="input-field">
                    <option value="">-- Chọn --</option>
                    <option v-for="t in titles" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>

                <!-- Loại khách -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Loại khách</label>
                  <select v-model="form.guest_type" class="input-field">
                    <option value="">Loại khách</option>
                    <option value="FIT">FIT</option>
                    <option value="GIT">GIT</option>
                    <option value="VIP">VIP</option>
                    <option value="Crew">Crew</option>
                    <option value="Long Stay">Long Stay</option>
                  </select>
                </div>
                <!-- Quốc tịch -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Quốc tịch</label>
                  <select v-model="form.nationality_code" class="input-field">
                    <option value="">-- Chọn --</option>
                    <option v-for="n in nationalities" :key="n.code" :value="n.code">{{ n.label }}</option>
                  </select>
                </div>
                <!-- Loại giấy tờ -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Loại giấy tờ</label>
                  <select v-model="form.id_type" class="input-field">
                    <option value="">Loại giấy tờ</option>
                    <option value="CCCD">CCCD</option>
                    <option value="CMND">CMND</option>
                    <option value="Hộ chiếu">Hộ chiếu</option>
                    <option value="Khác">Khác</option>
                  </select>
                </div>

                <!-- Số giấy tờ -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Số giấy tờ</label>
                  <input v-model="form.id_number" type="text" placeholder="Số giấy tờ" class="input-field" />
                </div>
                <!-- Ngày cấp -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ngày cấp</label>
                  <input v-model="form.id_issue_date" type="date" class="input-field" />
                </div>
                <!-- Ngày hết hạn giấy tờ -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ngày hết hạn</label>
                  <input v-model="form.passport_expiry" type="date" class="input-field" />
                </div>

                <!-- Địa chỉ -->
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Địa chỉ</label>
                  <input v-model="form.address" type="text" placeholder="Địa chỉ" class="input-field" />
                </div>
                <!-- Thường trú/Tạm trú -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Thường trú/Tạm trú</label>
                  <select v-model="form.residence_type" class="input-field">
                    <option value="">-- Chọn --</option>
                    <option value="Thường trú">Thường trú</option>
                    <option value="Tạm trú">Tạm trú</option>
                  </select>
                </div>

                <!-- Tỉnh/Thành phố -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Tỉnh/Thành phố</label>
                  <input v-model="form.province" type="text" placeholder="Tỉnh/Thành phố" class="input-field" />
                </div>
                <!-- Quận/Huyện -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Quận</label>
                  <input v-model="form.district" type="text" placeholder="Quận/Huyện" class="input-field" />
                </div>
                <!-- Phường/Xã -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Phường/ Xã</label>
                  <input v-model="form.ward" type="text" placeholder="Phường/Xã" class="input-field" />
                </div>

                <!-- Tạm trú đến -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Tạm trú đến</label>
                  <input v-model="form.temp_residence_to" type="date" class="input-field" />
                </div>
                <!-- Điện thoại -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Điện thoại</label>
                  <input v-model="form.phone" type="tel" placeholder="Điện thoại" class="input-field" />
                </div>
                <!-- Email -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                  <input v-model="form.email" type="email" placeholder="Email" class="input-field" />
                </div>

                <!-- Ghi chú -->
                <div class="col-span-3">
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ghi chú</label>
                  <textarea v-model="form.note" rows="2" placeholder="Ghi chú" class="input-field resize-none"></textarea>
                </div>
              </div>
            </div>
          </template>

          <!-- TAB 2: Thông tin xuất nhập cảnh -->
          <template v-else-if="activeTab === 'immigration'">
            <div class="mb-2">
              <div class="text-xs font-bold text-red-500 uppercase tracking-wider mb-3">Thông tin nhập cảnh *</div>
              <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">
                <!-- Số Visa -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Số Visa</label>
                  <input v-model="form.visa_no" type="text" placeholder="Số Visa" class="input-field" />
                </div>
                <!-- Cửa khẩu -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Cửa khẩu</label>
                  <input v-model="form.border_gate" type="text" placeholder="Cửa khẩu" class="input-field" />
                </div>

                <!-- Ngày nhập cảnh -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ngày nhập cảnh</label>
                  <input v-model="form.entry_date" type="date" class="input-field" />
                </div>
                <!-- Ngày hết hạn Visa -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Ngày hết hạn</label>
                  <input v-model="form.visa_expiry_date" type="date" class="input-field" />
                </div>

                <!-- Mục đích nhập cảnh -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Mục đích nhập cảnh</label>
                  <select v-model="form.entry_purpose" class="input-field">
                    <option value="">Mục đích nhập cảnh</option>
                    <option value="Du lịch">Du lịch</option>
                    <option value="Công tác">Công tác</option>
                    <option value="Thăm thân">Thăm thân</option>
                    <option value="Học tập">Học tập</option>
                    <option value="Đầu tư">Đầu tư</option>
                    <option value="Khác">Khác</option>
                  </select>
                </div>
                <!-- Công việc -->
                <div>
                  <label class="block text-xs font-semibold text-slate-600 mb-1">Công việc</label>
                  <input v-model="form.occupation" type="text" placeholder="Công việc" class="input-field" />
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 px-4 py-2.5 border-t border-gray-200 bg-gray-50 rounded-b">
          <button @click="handleScan" class="btn-secondary">
            <i class="fa-solid fa-camera mr-1.5"></i>Scan
          </button>
          <button @click="handleSave" :disabled="saving" class="btn-primary">
            <i class="fa-solid fa-floppy-disk mr-1.5"></i>{{ saving ? 'Đang lưu...' : 'Lưu' }}
          </button>
          <button @click="$emit('close')" class="btn-close">
            <i class="fa-solid fa-xmark mr-1.5"></i>Đóng
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { updateBookingRoomGuest, updateBookingChild } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  room: Object,       // { booking_room_id, room_number, room_class_name, arrival_date, departure_date, rate }
  guest: Object,      // guest hoặc child object
  guestType: {        // 'adult' | 'child'
    type: String,
    default: 'adult'
  }
})

const emit = defineEmits(['close', 'saved'])
const uiStore = useUiStore()
const saving = ref(false)
const activeTab = ref('info')

const tabs = [
  { key: 'info', label: 'Thông tin chung' },
  { key: 'immigration', label: 'Thông tin xuất nhập cảnh' },
]

const titles = ['Mr.', 'Mrs.', 'Ms.', 'Miss.', 'Kid.', 'Baby.', 'Dr.', 'Prof.']

const nationalities = [
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

const form = ref({})

function resetForm() {
  const g = props.guest || {}
  form.value = {
    full_name: g.full_name || '',
    title: g.title || 'Mr.',
    dob: g.dob ? g.dob.substring(0, 10) : '',
    nationality_code: g.nationality_code || 'VN',
    id_type: g.id_type || '',
    id_number: g.id_number || '',
    id_issue_date: g.id_issue_date ? g.id_issue_date.substring(0, 10) : '',
    passport_number: g.passport_number || '',
    passport_expiry: g.passport_expiry ? g.passport_expiry.substring(0, 10) : '',
    phone: g.phone || '',
    email: g.email || '',
    address: g.address || '',
    guest_type: g.guest_type || '',
    province: g.province || '',
    district: g.district || '',
    ward: g.ward || '',
    residence_type: g.residence_type || '',
    temp_residence_to: g.temp_residence_to ? g.temp_residence_to.substring(0, 10) : '',
    visa_no: g.visa_no || '',
    entry_date: g.entry_date ? g.entry_date.substring(0, 10) : '',
    visa_expiry_date: g.visa_expiry_date ? g.visa_expiry_date.substring(0, 10) : '',
    entry_purpose: g.entry_purpose || '',
    border_gate: g.border_gate || '',
    occupation: g.occupation || '',
    note: g.note || '',
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
    activeTab.value = 'info'
    modalPos.value = { x: 0, y: 0 }
    resetForm()
  }
})

watch(() => props.guest, () => {
  if (props.show) resetForm()
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('vi-VN')
}

function formatMoney(v) {
  if (!v) return '0'
  return Number(v).toLocaleString('vi-VN')
}

async function handleSave() {
  saving.value = true
  try {
    const payload = { ...form.value }
    let res
    if (props.guestType === 'adult') {
      res = await updateBookingRoomGuest(props.room.booking_room_id, props.guest.id, payload)
    } else {
      res = await updateBookingChild(props.guest.id, payload)
    }
    if (res.data?.success) {
      uiStore.showToast('Lưu thông tin khách thành công!', 'success')
      emit('saved', { ...props.guest, ...payload })
    } else {
      uiStore.showToast(res.data?.message || 'Lưu thất bại!', 'error')
    }
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra!', 'error')
  } finally {
    saving.value = false
  }
}

function handleScan() {
  uiStore.showToast('Tính năng Scan đang phát triển.', 'info')
}
</script>

<style scoped>
.input-field {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 13px;
  background-color: #ffffff;
  color: #1e293b;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.input-field:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.btn-primary {
  padding: 8px 16px;
  background-color: #243c5a;
  color: white;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  transition: background-color 150ms cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.btn-primary:hover {
  background-color: #1a2e47;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-secondary {
  padding: 8px 16px;
  background-color: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #334155;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  transition: background-color 150ms cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.btn-secondary:hover {
  background-color: #f1f5f9;
}
.btn-close {
  padding: 8px 16px;
  background-color: white;
  border: 1px solid #cbd5e1;
  color: #475569;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  transition: background-color 150ms cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.btn-close:hover {
  background-color: #f8fafc;
}
</style>
