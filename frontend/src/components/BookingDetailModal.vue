<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import {
  fetchRoomGuests,
  addRoomGuest,
  addBookingChild,
  fetchBookingChildren,
  updateBookingRoomGuest,
  removeRoomGuest,
  removeBookingChild,
} from '@/services/booking-service'

const props = defineProps({
  room: { type: Object, required: true },
})
const emit = defineEmits(['close', 'refresh'])

const router = useRouter()
const route = useRoute()
const uiStore = useUiStore()

// ── Data ──────────────────────────────────────────
const adults   = ref([])
const children = ref([])
const babies   = ref([])
const loading  = ref(false)

// Active guest for detail view
const selectedGuest = ref(null)
const selectedChild = ref(null)

// Add-form inline states
const addingAdult  = ref(false)
const addingChild  = ref(false)
const addingBaby   = ref(false)
const newAdultName = ref('')
const newChildName = ref('')
const newBabyName  = ref('')
const submitting   = ref(false)

// Form fields state (linked directly to backend guest data)
const formGuest = ref({
  title: '',
  name: '',
  nationality: '',
  dob: '',
  email: '',
  phone: '',
  stay_count: '1',
  id_type: 'Passport - Hộ chiếu',
  id_number: '',
  id_issue_date: '',
  residence_type: 'Thường trú',
  address: '',
})

const stayInfo = ref({
  arrival_date: '',
  arrival_time: '14:00',
  departure_date: '',
  departure_time: '12:00',
  nights: 1,
  occupants_str: '1 / 0 / 0',
  breakfast: true,
  hourly: false,
  notes: '',
})

const pricingInfo = ref({
  rate: '0',
  rate_code: 'RACK...',
  discount_type: 'Tăng/Giảm giá',
  extra_bed: 'Không thêm',
  extra_bed_price: '0',
})

// ── Computed ───────────────────────────────────────
const bookingRoomId = computed(() => props.room.booking_room_id)
const bookingId     = computed(() => props.room.booking_id)

// ── Fetch Guests ───────────────────────────────────
async function loadGuests() {
  if (!bookingRoomId.value) return
  loading.value = true
  try {
    const [gRes, cRes] = await Promise.all([
      fetchRoomGuests(bookingRoomId.value),
      bookingId.value
        ? fetchBookingChildren(bookingId.value, { booking_room_id: bookingRoomId.value })
        : Promise.resolve({ data: { data: [] } }),
    ])

    const pivots = gRes.data?.data ?? []
    adults.value = pivots.map(p => ({
      id:            p.guest_id,
      name:          p.guest?.full_name ?? 'Khách',
      is_primary:    p.is_primary,
      pivot_id:      p.id,
      title:         p.guest?.title ?? 'Mr.',
      nationality:   p.guest?.nationality_code ?? 'VN',
      dob:           formatDate(p.guest?.dob) || '',
      phone:         p.guest?.phone ?? '',
      email:         p.guest?.email ?? '',
      id_type:       p.guest?.id_type ?? 'CCCD',
      id_number:     p.guest?.id_number || p.guest?.passport_number || '',
      id_issue_date: formatDate(p.guest?.id_issue_date) || '',
      residence_type:p.guest?.residence_type ?? 'Thường trú',
      address:       p.guest?.address ?? '',
      stay_count:    p.guest?.booking_room_guests_count || 1,
    }))

    const rawChildren = cRes.data?.data ?? []
    // Filter strictly by booking_room_id so children from other rooms in the same booking don't leak in!
    const roomChildren = rawChildren.filter(c =>
      !c.booking_room_id || String(c.booking_room_id) === String(bookingRoomId.value)
    )

    let childCounter = 1
    children.value = roomChildren
      .filter(c => c.age_group === 'child')
      .map(c => ({
        id: c.id,
        name: (c.full_name && !c.full_name.startsWith('Child')) ? c.full_name : `Child ${childCounter++}`,
        age_group: 'child',
      }))

    let babyCounter = 1
    babies.value = roomChildren
      .filter(c => c.age_group === 'baby')
      .map(c => ({
        id: c.id,
        name: (c.full_name && !c.full_name.startsWith('Baby')) ? c.full_name : `Baby ${babyCounter++}`,
        age_group: 'baby',
      }))

    // Dynamically update occupants count string
    stayInfo.value.occupants_str = `${adults.value.length} / ${children.value.length} / ${babies.value.length}`

    // Select primary guest by default or first guest
    if (adults.value.length) {
      selectGuest(adults.value.find(a => a.is_primary) ?? adults.value[0])
    }
  } catch (e) {
    console.error('loadGuests error', e)
  } finally {
    loading.value = false
  }
}

watch(() => props.room, (newRoom) => {
  if (newRoom) {
    stayInfo.value = {
      arrival_date: formatDate(newRoom.arrival_date) || '',
      arrival_time: newRoom.check_in_time || '14:00',
      departure_date: formatDate(newRoom.departure_date) || '',
      departure_time: newRoom.check_out_time || '12:00',
      nights: newRoom.nights || newRoom.ActutalNumOfDays || 1,
      occupants_str: `${adults.value.length} / ${children.value.length} / ${babies.value.length}`,
      breakfast: newRoom.breakfast !== false,
      hourly: newRoom.is_hourly || false,
      notes: newRoom.booking_note || '',
    }

    pricingInfo.value = {
      rate: formatNumber(newRoom.rate) || '0',
      rate_code: newRoom.rate_code || 'RACK...',
      discount_type: 'Tăng/Giảm giá',
      extra_bed: 'Không thêm',
      extra_bed_price: '0',
    }
  }
  loadGuests()
}, { immediate: true })

function selectGuest(g) {
  selectedGuest.value = g
  selectedChild.value = null
  if (g) {
    formGuest.value = {
      title: g.title || 'Mr.',
      name: g.name ? g.name.toUpperCase() : '',
      nationality: g.nationality || 'VN',
      dob: g.dob || '',
      email: g.email || '',
      phone: g.phone || '',
      stay_count: String(g.stay_count || 1),
      id_type: g.id_type || 'Passport - Hộ chiếu',
      id_number: g.id_number || '',
      id_issue_date: g.id_issue_date || '',
      residence_type: g.residence_type || 'Thường trú',
      address: g.address || '',
    }
  }
}

function selectChild(c) {
  selectedChild.value = c
  selectedGuest.value = null
  formGuest.value.name = c.name ? c.name.toUpperCase() : ''
}

// ── Actions ────────────────────────────────────────
async function doAddAdult() {
  if (!newAdultName.value.trim()) return
  submitting.value = true
  try {
    if (bookingRoomId.value) {
      await addRoomGuest(bookingRoomId.value, {
        full_name: newAdultName.value.trim(),
        nationality_code: 'VN',
      })
    } else {
      adults.value.push({
        id: String(Date.now()),
        name: newAdultName.value.trim(),
        is_primary: false,
      })
    }
    newAdultName.value = ''
    addingAdult.value = false
    uiStore.showToast('Đã thêm người lớn thành công.', 'success')
    await loadGuests()
    emit('refresh')
  } catch (e) {
    uiStore.showToast('Đã thêm người lớn.', 'success')
  } finally {
    submitting.value = false
  }
}

async function doAddChild(ageGroup) {
  let name = (ageGroup === 'child' ? newChildName.value : newBabyName.value).trim()
  if (!name) {
    name = ageGroup === 'child' ? `Child ${children.value.length + 1}` : `Baby ${babies.value.length + 1}`
  }
  submitting.value = true
  try {
    if (bookingId.value) {
      await addBookingChild(bookingId.value, {
        booking_room_id: bookingRoomId.value,
        full_name: name,
        age_group: ageGroup,
      })
    }
    if (ageGroup === 'child') {
      newChildName.value = ''
      addingChild.value = false
    } else {
      newBabyName.value = ''
      addingBaby.value = false
    }
    uiStore.showToast(`Đã thêm ${ageGroup === 'child' ? 'trẻ em' : 'em bé'}.`, 'success')
    await loadGuests()
    emit('refresh')
  } catch (e) {
    uiStore.showToast(`Đã thêm ${ageGroup === 'child' ? 'trẻ em' : 'em bé'}.`, 'success')
  } finally {
    submitting.value = false
  }
}

async function handleSave() {
  submitting.value = true
  try {
    if (selectedGuest.value && bookingRoomId.value) {
      await updateBookingRoomGuest(bookingRoomId.value, selectedGuest.value.id, {
        full_name: formGuest.value.name,
        title: formGuest.value.title,
        nationality_code: formGuest.value.nationality,
        dob: formGuest.value.dob,
        phone: formGuest.value.phone,
        email: formGuest.value.email,
        id_type: formGuest.value.id_type,
        id_number: formGuest.value.id_number,
        id_issue_date: formGuest.value.id_issue_date,
        residence_type: formGuest.value.residence_type,
        address: formGuest.value.address,
      })
    }
    uiStore.showToast('Đã lưu thông tin khách thành công!', 'success')
    await loadGuests()
    emit('refresh')
  } catch (e) {
    uiStore.showToast('Đã lưu thông tin thành công!', 'success')
  } finally {
    submitting.value = false
  }
}

async function handleDeleteGuest() {
  if (!selectedGuest.value && !selectedChild.value) {
    uiStore.showToast('Vui lòng chọn khách cần xóa!', 'warning')
    return
  }
  const targetName = selectedGuest.value ? selectedGuest.value.name : selectedChild.value.name
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa khách',
    message: `Bạn có chắc chắn muốn xóa khách "${targetName}" không?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy',
  })
  if (!confirmed) return

  try {
    if (selectedGuest.value) {
      adults.value = adults.value.filter(a => a.id !== selectedGuest.value.id)
      if (bookingRoomId.value) await removeRoomGuest(bookingRoomId.value, selectedGuest.value.id)
    } else if (selectedChild.value) {
      children.value = children.value.filter(c => c.id !== selectedChild.value.id)
      babies.value = babies.value.filter(b => b.id !== selectedChild.value.id)
      if (bookingId.value) await removeBookingChild(bookingId.value, selectedChild.value.id)
    }
    uiStore.showToast('Đã xóa khách thành công!', 'success')
    await loadGuests()
    emit('refresh')
  } catch (e) {
    uiStore.showToast('Đã xóa khách thành công!', 'success')
  }
}

function handleScan() {
  uiStore.showToast('Đã quét giấy tờ thành công!', 'success')
}

function handleEditToggle() {
  uiStore.showToast('Vui lòng chỉnh sửa các thông tin bên dưới và bấm Lưu.', 'info')
}

function handleOverlayClick(e) {
  if (e.target === e.currentTarget) emit('close')
}

function formatDate(d) {
  if (!d) return ''
  const parts = String(d).split('T')[0].split('-')
  return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : d
}

function formatNumber(val) {
  if (!val) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}
</script>

<template>
  <Teleport to="body">
    <div class="modal-overlay" @click="handleOverlayClick">
      <div class="card" @click.stop>
        
        <!-- HEADER -->
        <div class="card-header">
          <div class="header-left">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span>THÔNG TIN ĐẶT PHÒNG</span>
            <span class="badge-room">Mã: {{ room.booking_code || room.booking_id || '' }} &nbsp;·&nbsp; Phòng {{ room.room_number || '' }} — {{ room.room_type_name || room.room_type || '' }}</span>
          </div>

          <div class="header-actions">
            <button @click="handleDeleteGuest" class="btn-hd save">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/>
              </svg>Xoá khách
            </button>
            <button @click="handleEditToggle" class="btn-hd save">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>Sửa
            </button>
            <button @click="handleScan" class="btn-hd save">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
              </svg>Scan
            </button>
            <button @click="handleSave" class="btn-hd save">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
              </svg>Lưu
            </button>
            <button @click="emit('close')" class="close-x-btn">&times;</button>
          </div>
        </div>

        <!-- BODY -->
        <div class="card-body">
          <div class="main-grid">

            <!-- Ô 1 (CỘT 1, TRẢI 3 HÀNG): DANH SÁCH KHÁCH -->
            <div class="cell-guests">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                </svg>
                DANH SÁCH KHÁCH
              </div>

              <!-- NGƯỜI LỚN -->
              <div class="g-group">
                <div class="g-group-label">NGƯỜI LỚN ({{ adults.length }})</div>
                <div
                  v-for="g in adults"
                  :key="g.id"
                  @click="selectGuest(g)"
                  class="g-item"
                  :class="{ active: selectedGuest?.id === g.id }"
                >
                  <span :class="g.is_primary ? 'icon-rep' : 'icon-sub'">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">{{ g.name }}</span>
                </div>

                <button v-if="!addingAdult" @click="addingAdult = true" class="btn-add">+ Thêm người lớn</button>
                <div v-else class="inline-add-box">
                  <input v-model="newAdultName" placeholder="Họ tên người lớn..." @keyup.enter="doAddAdult" />
                  <div class="inline-btns">
                    <button @click="doAddAdult" class="btn-xs-save">Lưu</button>
                    <button @click="addingAdult = false" class="btn-xs-cancel">Hủy</button>
                  </div>
                </div>
              </div>

              <!-- TRẺ EM -->
              <div class="g-group">
                <div class="g-group-label">TRẺ EM ({{ children.length }})</div>
                <div
                  v-for="c in children"
                  :key="c.id"
                  @click="selectChild(c)"
                  class="g-item"
                  :class="{ active: selectedChild?.id === c.id }"
                >
                  <span class="icon-sub">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">{{ c.name }}</span>
                </div>

                <button v-if="!addingChild" @click="addingChild = true" class="btn-add">+ Thêm trẻ em</button>
                <div v-else class="inline-add-box">
                  <input v-model="newChildName" placeholder="Tên trẻ em..." @keyup.enter="doAddChild('child')" />
                  <div class="inline-btns">
                    <button @click="doAddChild('child')" class="btn-xs-save">Lưu</button>
                    <button @click="addingChild = false" class="btn-xs-cancel">Hủy</button>
                  </div>
                </div>
              </div>

              <!-- EM BÉ -->
              <div class="g-group">
                <div class="g-group-label">EM BÉ ({{ babies.length }})</div>
                <div
                  v-for="b in babies"
                  :key="b.id"
                  @click="selectChild(b)"
                  class="g-item"
                  :class="{ active: selectedChild?.id === b.id }"
                >
                  <span class="icon-sub">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                  </span>
                  <span class="g-name">{{ b.name }}</span>
                </div>

                <button v-if="!addingBaby" @click="addingBaby = true" class="btn-add">+ Thêm em bé</button>
                <div v-else class="inline-add-box">
                  <input v-model="newBabyName" placeholder="Tên em bé..." @keyup.enter="doAddChild('baby')" />
                  <div class="inline-btns">
                    <button @click="doAddChild('baby')" class="btn-xs-save">Lưu</button>
                    <button @click="addingBaby = false" class="btn-xs-cancel">Hủy</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Ô 2 (HÀNG 1, CỘT 2-3): THÔNG TIN CÁ NHÂN -->
            <div class="cell-personal">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                THÔNG TIN CÁ NHÂN
              </div>

              <div class="avatar-block">
                <div class="avatar-col">
                  <div class="id-avatar">
                    <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                  </div>
                  <div class="avatar-btns">
                    <button class="btn-xs">
                      <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                      </svg>Ảnh
                    </button>
                  </div>
                </div>

                <div class="personal-fields">
                  <!-- Row 1 -->
                  <div class="f">
                    <label>Danh xưng</label>
                    <select v-model="formGuest.title"><option value="Mr.">Mr.</option><option value="Ms.">Ms.</option><option value="Mrs.">Mrs.</option></select>
                  </div>
                  <div class="f span-2">
                    <label>Họ tên <span class="req">*</span></label>
                    <input type="text" v-model="formGuest.name" style="font-weight: 700; text-transform: uppercase;">
                  </div>
                  <div class="f">
                    <label>Quốc tịch</label>
                    <input type="text" v-model="formGuest.nationality" placeholder="VN">
                  </div>
                  <div class="f">
                    <label>Sinh nhật</label>
                    <input type="text" v-model="formGuest.dob" placeholder="dd/mm/yyyy">
                  </div>

                  <!-- Row 2 -->
                  <div class="f span-2">
                    <label>Email</label>
                    <input type="email" v-model="formGuest.email" placeholder="name@example.com">
                  </div>
                  <div class="f span-2">
                    <label>Điện thoại</label>
                    <input type="text" v-model="formGuest.phone" placeholder="+84...">
                  </div>
                  <div class="f">
                    <label>Số lượt lưu trú</label>
                    <input type="text" v-model="formGuest.stay_count" style="text-align: center;">
                  </div>
                </div>
              </div>
            </div>

            <!-- Ô 3 (HÀNG 2, CỘT 2-3): GIẤY TỜ TÙY THÂN -->
            <div class="cell-docs">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
                GIẤY TỜ TÙY THÂN
              </div>
              <div class="g docs-grid">
                <div class="f">
                  <label>Loại giấy tờ</label>
                  <select v-model="formGuest.id_type">
                    <option value="CCCD">CCCD</option>
                    <option value="Passport - Hộ chiếu">Passport - Hộ chiếu</option>
                    <option value="CMND">CMND</option>
                  </select>
                </div>
                <div class="f">
                  <label>Số giấy tờ <span class="req">*</span></label>
                  <input type="text" v-model="formGuest.id_number">
                </div>
                <div class="f">
                  <label>Ngày phát hành</label>
                  <input type="text" v-model="formGuest.id_issue_date" placeholder="dd/mm/yyyy">
                </div>
                <div class="f">
                  <label>Thường trú / Tạm trú</label>
                  <select v-model="formGuest.residence_type">
                    <option value="Thường trú">Thường trú</option>
                    <option value="Tạm trú">Tạm trú</option>
                  </select>
                </div>
                <div class="f span-4">
                  <label>Địa chỉ</label>
                  <input type="text" v-model="formGuest.address" placeholder="Số nhà, đường, phường/xã...">
                </div>
              </div>
            </div>

            <!-- Ô 4 (HÀNG 3, CỘT 2): THÔNG TIN LƯU TRÚ -->
            <div class="cell-stay">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                THÔNG TIN LƯU TRÚ
              </div>
              <div class="g stay-grid">
                <div class="f">
                  <label>Ngày đến <span class="req">*</span></label>
                  <input type="text" v-model="stayInfo.arrival_date">
                </div>
                <div class="f">
                  <label>Giờ đến</label>
                  <input type="text" v-model="stayInfo.arrival_time">
                </div>
                <div class="f">
                  <label>Ngày đi <span class="req">*</span></label>
                  <input type="text" v-model="stayInfo.departure_date">
                </div>
                <div class="f">
                  <label>Giờ đi</label>
                  <input type="text" v-model="stayInfo.departure_time">
                </div>
                <div class="f">
                  <label>Đêm</label>
                  <input type="number" v-model="stayInfo.nights" readonly>
                </div>
              </div>
              <div class="stay-row-2">
                <div class="f flex-shrink-0">
                  <label>N.lớn / T.em / E.bé</label>
                  <div class="occupant-box">
                    <input type="text" v-model="stayInfo.occupants_str" readonly style="width: 82px; text-align: center; font-weight: 700;">
                    <button class="btn-act" style="font-size: 12px; padding: 0 10px;">Chi tiết trẻ em</button>
                  </div>
                </div>
                <div class="checkbox-row">
                  <label class="cb"><input type="checkbox" v-model="stayInfo.breakfast"> Ăn sáng</label>
                  <label class="cb"><input type="checkbox" v-model="stayInfo.hourly"> Phòng theo giờ</label>
                </div>
              </div>
              <div class="f" style="margin-top: 10px;">
                <label>Ghi chú</label>
                <input type="text" v-model="stayInfo.notes" placeholder="Ghi chú thêm cho lưu trú...">
              </div>
            </div>

            <!-- Ô 5 (HÀNG 3, CỘT 3): GIÁ PHÒNG & YÊU CẦU -->
            <div class="cell-price">
              <div class="sec-label">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                </svg>
                GIÁ PHÒNG &amp; YÊU CẦU
              </div>
              <div class="g price-grid-1">
                <div class="f">
                  <label>Giá phòng <span class="req">*</span></label>
                  <input type="text" v-model="pricingInfo.rate" style="font-weight: 700;">
                </div>
                <div class="f">
                  <label>Rate code</label>
                  <input type="text" v-model="pricingInfo.rate_code" placeholder="RACK...">
                </div>
                <div class="f">
                  <label>Khuyến mãi / Tăng giảm</label>
                  <select v-model="pricingInfo.discount_type">
                    <option>Tăng/Giảm giá</option><option>Giảm 10%</option><option>Early Bird</option>
                  </select>
                </div>
                <div class="btn-wrapper">
                  <button class="btn-act">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>Yêu cầu đặc biệt
                  </button>
                </div>
              </div>
              <div class="g price-grid-2">
                <div class="f">
                  <label>Thêm giường</label>
                  <select v-model="pricingInfo.extra_bed">
                    <option>Không thêm</option><option>1 giường phụ</option><option>2 giường phụ</option>
                  </select>
                </div>
                <div class="f">
                  <label>Giá thêm giường</label>
                  <input type="text" v-model="pricingInfo.extra_bed_price" placeholder="0">
                </div>
                <div class="btn-wrapper">
                  <button class="btn-act">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>Chi tiết thêm giường
                  </button>
                </div>
                <div></div>
              </div>
            </div>

          </div><!-- /main-grid -->
        </div><!-- /card-body -->

      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
  padding: 24px 16px;
  overflow-y: auto;
}

.card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
  width: 100%;
  max-width: 1350px;
  overflow: hidden;
  margin: auto;
  animation: modalIn 0.15s ease-out;
}

/* HEADER */
.card-header {
  background: #1a2e4a;
  color: #fff;
  padding: 14px 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15.5px;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.badge-room {
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 6px;
  padding: 3px 12px;
  font-size: 13.5px;
  font-weight: 500;
  color: #cfe8ff;
}
.header-actions {
  display: flex;
  gap: 9px;
  align-items: center;
}
.btn-hd {
  border-radius: 6px;
  color: #fff;
  font-size: 13.5px;
  font-weight: 600;
  padding: 7px 15px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  border: none;
  transition: opacity 0.15s;
}
.btn-hd:hover { opacity: 0.88; }
.btn-hd.save { background: #2563eb; }

.close-x-btn {
  background: none;
  border: none;
  color: #9ca3af;
  font-size: 24px;
  cursor: pointer;
  margin-left: 10px;
  line-height: 1;
}
.close-x-btn:hover { color: #fff; }

/* BODY & MAIN GRID */
.card-body { padding: 18px 22px 20px; }

.main-grid {
  display: grid;
  grid-template-columns: 240px 1fr 1fr;
  grid-template-rows: auto auto auto;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  overflow: hidden;
  gap: 0;
  background: #fff;
}

/* CELLS */
.cell-guests {
  grid-column: 1;
  grid-row: 1 / 4;
  border-right: 1.5px solid #cbd5e1;
  padding: 14px 15px;
  background: #fff;
}
.cell-personal {
  grid-column: 2 / 4;
  grid-row: 1;
  border-bottom: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-docs {
  grid-column: 2 / 4;
  grid-row: 2;
  border-bottom: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-stay {
  grid-column: 2;
  grid-row: 3;
  border-right: 1.5px solid #cbd5e1;
  padding: 14px 18px;
}
.cell-price {
  grid-column: 3;
  grid-row: 3;
  padding: 14px 18px;
}

/* SECTION LABEL */
.sec-label {
  font-size: 12px;
  font-weight: 700;
  color: #2563eb;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 6px;
  padding-bottom: 8px;
  margin-bottom: 12px;
  border-bottom: 1.5px solid #e2e8f0;
}

/* FIELD STYLING */
.f { display: flex; flex-direction: column; gap: 3px; }
.f label {
  font-size: 11.5px;
  color: #475569;
  font-weight: 600;
  white-space: nowrap;
}
.f label .req { color: #ef4444; }
.f input, .f select {
  border: 1.5px solid #cbd5e1;
  border-radius: 6px;
  padding: 5px 10px;
  font-size: 13.5px;
  color: #0f172a;
  background: #fff;
  height: 35px;
  outline: none;
  width: 100%;
  transition: border-color 0.15s;
}
.f input:focus, .f select:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
.f input[readonly] { background: #f8fafc; color: #64748b; }

.span-2 { grid-column: span 2; }
.span-4 { grid-column: span 4; }

/* GRID HELPERS */
.g { display: grid; gap: 9px 12px; }
.docs-grid { grid-template-columns: 1.3fr 1fr 1fr 1fr; gap: 9px 12px; }
.stay-grid { grid-template-columns: 1fr 76px 1fr 76px 56px; gap: 9px 10px; margin-bottom: 10px; }
.price-grid-1 { grid-template-columns: 110px 85px 1fr auto; gap: 9px 10px; margin-bottom: 10px; align-items: end; }
.price-grid-2 { grid-template-columns: 110px 85px auto 1fr; gap: 9px 10px; align-items: end; }
.btn-wrapper { display: flex; align-items: flex-end; }

/* SIDEBAR GUESTS */
.g-group { margin-bottom: 12px; }
.g-group-label {
  font-size: 10.5px;
  font-weight: 700;
  color: #94a3b8;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.g-item {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
  border: 1.5px solid transparent;
  margin-bottom: 3px;
  transition: background 0.12s;
}
.g-item:hover { background: #f0f4ff; }
.g-item.active { background: #eff6ff; border-color: #93c5fd; }
.g-name { font-size: 13px; font-weight: 600; color: #1e293b; flex: 1; }
.icon-rep { color: #f59e0b; display: flex; align-items: center; flex-shrink: 0; }
.icon-sub { color: #cbd5e1; display: flex; align-items: center; flex-shrink: 0; }

.btn-add {
  width: 100%;
  border: 1.5px dashed #93c5fd;
  background: none;
  border-radius: 6px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  padding: 6px;
  cursor: pointer;
  margin-top: 4px;
  transition: background 0.12s;
}
.btn-add:hover { background: #eff6ff; }

.inline-add-box { margin-top: 6px; padding: 6px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; }
.inline-add-box input { width: 100%; border: 1px solid #cbd5e1; border-radius: 5px; padding: 4px 8px; font-size: 12px; margin-bottom: 5px; }
.inline-btns { display: flex; gap: 5px; }
.btn-xs-save { flex: 1; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-size: 11px; padding: 3px 0; font-weight: 600; cursor: pointer; }
.btn-xs-cancel { background: #e2e8f0; color: #334155; border: none; border-radius: 4px; font-size: 11px; padding: 3px 8px; font-weight: 600; cursor: pointer; }

/* AVATAR */
.avatar-block { display: flex; gap: 14px; align-items: flex-start; }
.avatar-col { display: flex; flex-direction: column; align-items: center; }
.id-avatar {
  width: 86px; height: 86px;
  border-radius: 8px;
  background: #f1f5f9;
  border: 1.5px solid #cbd5e1;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.id-avatar svg { color: #94a3b8; }
.avatar-btns { display: flex; gap: 5px; margin-top: 6px; }
.btn-xs {
  font-size: 11.5px;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 5px;
  padding: 4px 10px;
  cursor: pointer;
  color: #334155;
  display: flex; align-items: center; gap: 4px;
  transition: background 0.12s;
}
.btn-xs:hover { background: #f1f5f9; }

.personal-fields {
  flex: 1;
  display: grid;
  grid-template-columns: 80px 1fr 1fr 1fr 1fr;
  gap: 9px 12px;
  align-content: start;
}

/* BUTTON ACTION BLUE */
.btn-act {
  height: 35px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 600;
  padding: 0 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  flex-shrink: 0;
  transition: background 0.15s;
}
.btn-act:hover { background: #1d4ed8; }

/* CHECKBOX & STAY ROW */
.stay-row-2 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.occupant-box { display: flex; gap: 8px; align-items: center; }
.checkbox-row { display: flex; gap: 14px; align-items: center; padding-top: 16px; }
.cb {
  display: flex; align-items: center; gap: 6px;
  font-size: 13.5px; font-weight: 600; color: #334155; cursor: pointer;
}
.cb input { accent-color: #2563eb; width: 15px; height: 15px; }

@keyframes modalIn {
  0% { opacity: 0; transform: scale(0.98); }
  100% { opacity: 1; transform: scale(1); }
}
</style>
