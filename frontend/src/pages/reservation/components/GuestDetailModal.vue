<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[10000] flex items-start justify-center pt-6 overflow-y-auto pb-6">
      <!-- Overlay -->
      <div class="fixed inset-0 bg-black/55 backdrop-blur-[1px]" @click="$emit('close')"></div>

      <!-- Modal -->
      <div class="modal relative z-10 w-[960px] max-w-[98vw] flex flex-col font-sans text-[13px] my-auto"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        
        <!-- Header -->
        <div class="m-head select-none cursor-move" @mousedown="startDragModal">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-id-card text-[#B9CDF8]"></i>
            <h1 id="ttl">Thông tin khách</h1>
            <span class="chip b ml-2">{{ guestType === 'adult' ? 'Người lớn' : 'Trẻ em' }}</span>
          </div>

          <div class="flex items-center gap-1.5 ml-auto">
            <button type="button" @click="$emit('close')" class="x" aria-label="Đóng">×</button>
          </div>
        </div>

        <!-- Body -->
        <div class="m-body overflow-y-auto max-h-[calc(90vh-105px)]">
          <!-- Booking strip -->
          <div class="stay">
            <div class="flex items-center gap-4">
              <div class="room">
                <small>Phòng</small>
                <span>{{ room?.room_number || '—' }}</span>
              </div>
              <div class="st font-medium text-sm text-[#DCE6FF] border-l border-white/20 pl-4">
                {{ room?.room_class_name || 'Phòng lưu trú' }}
              </div>
            </div>
            
            <div class="flex items-center gap-6">
              <div class="kv text-right">
                Rate
                <b>{{ formatMoney(room?.rate) }} <span class="text-[10px] text-[#9FB2D6] font-normal">VND</span></b>
              </div>
              <div class="kv text-right">
                Arrival
                <b>{{ formatDate(room?.arrival_date) }}</b>
              </div>
              <div class="arrow text-[#5C7BB8] text-xs">➔</div>
              <div class="kv text-right">
                Departure
                <b>{{ formatDate(room?.departure_date) }}</b>
              </div>
              <span class="badge">{{ calculateNights(room) }} đêm</span>
            </div>
          </div>

          <!-- Photo column -->
          <aside class="side">
            <div class="photo">
              <div
                class="drop"
                :class="{ 'has': guestPhotos.length > 0, 'over': isDragOverPhoto }"
                @click="triggerPhotoClick"
                @dragenter.prevent="isDragOverPhoto = true"
                @dragover.prevent="isDragOverPhoto = true"
                @dragleave.prevent="isDragOverPhoto = false"
                @drop.prevent="handlePhotoDrop"
                title="Kéo thả hoặc bấm để thêm ảnh"
              >
                <img v-if="guestPhotos.length > 0" :src="formatPhotoUrl(guestPhotos[curPhotoIdx])" alt="Ảnh khách" />
                
                <div class="ph">
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                  <div>
                    <b>Ảnh khách</b><br>
                    Kéo thả hoặc bấm để chọn<br>
                    <span style="opacity:.7">JPG · PNG · ≤ 5 MB / ảnh</span>
                  </div>
                </div>

                <span v-if="guestPhotos.length > 0" class="cnt">{{ curPhotoIdx + 1 }}/{{ guestPhotos.length }}</span>
                <button v-if="guestPhotos.length > 0" type="button" class="rm" @click.stop="removeCurrentPhoto" title="Xoá ảnh này">×</button>
                <input ref="photoInputRef" type="file" accept="image/*" multiple hidden @change="handlePhotoUpload" />
              </div>

              <!-- Thumbs -->
              <div v-if="guestPhotos.length > 0" class="thumbs">
                <div
                  v-for="(p, i) in guestPhotos"
                  :key="i"
                  class="th"
                  :class="{ 'on': i === curPhotoIdx }"
                  @click="curPhotoIdx = i"
                >
                  <img :src="formatPhotoUrl(p)" alt="thumb" />
                </div>
                <div class="th add" @click="triggerPhotoInput" title="Thêm ảnh">＋</div>
              </div>

              <!-- Action buttons -->
              <div class="acts">
                <button type="button" @click="triggerPhotoInput">
                  <svg viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                  Chụp
                </button>
                <button type="button" @click="triggerPhotoInput">
                  <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M4 16l5-5 4 4 3-3 4 4"/><circle cx="16" cy="8" r="1.5"/></svg>
                  Tải lên
                </button>
              </div>
              <div class="hint">Bấm ảnh nhỏ để xem lớn · Hỗ trợ tối đa 5MB</div>
            </div>
          </aside>

          <!-- Main sections -->
          <div class="main">
            <!-- SECTION 1: Cá nhân -->
            <section class="sec">
              <h3>Thông tin cá nhân <span class="req">*</span></h3>
              <div class="g c12">
                <div class="f s2">
                  <label>Danh xưng</label>
                  <select v-model="form.title">
                    <option value="">-- Chọn --</option>
                    <option v-for="t in titles" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>

                <div class="f s6">
                  <label>Họ và tên <span class="req">*</span></label>
                  <input v-model="form.full_name" type="text" placeholder="HỌ VÀ TÊN" style="text-transform:uppercase" />
                </div>

                <div class="f s4">
                  <label>Loại khách</label>
                  <select v-model="form.guest_type">
                    <option value="">-- Loại khách --</option>
                    <option v-for="gt in guestTypes" :key="gt.id" :value="getGuestTypeValue(gt)">{{ gt.name }}</option>
                    <option v-if="form.guest_type && !guestTypes.some(gt => getGuestTypeValue(gt) === form.guest_type || gt.name === form.guest_type)" :value="form.guest_type">{{ form.guest_type }}</option>
                  </select>
                </div>

                <div class="f s4">
                  <label>Ngày sinh</label>
                  <div class="wi d" @click="triggerDatePicker($event)">
                    <input v-model="form.dob" type="date" @click="$event.target.showPicker?.()" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  </div>
                </div>

                <div class="f s8">
                  <label>Quốc tịch <span class="req">*</span></label>
                  <select v-model="form.nationality_code">
                    <option value="">-- Chọn quốc tịch --</option>
                    <option v-for="n in nationalities" :key="n.code" :value="n.code">{{ n.label }}</option>
                  </select>
                </div>
              </div>
            </section>

            <!-- SECTION 2: Giấy tờ tùy thân -->
            <section class="sec">
              <h3>Giấy tờ tùy thân <span class="req">*</span></h3>
              <div class="g c12">
                <div class="f s3">
                  <label>Loại giấy tờ</label>
                  <select v-model="form.id_type">
                    <option value="">-- Loại giấy tờ --</option>
                    <option v-for="it in idTypes" :key="it.id" :value="getIdTypeValue(it)">{{ it.name }}</option>
                    <option v-if="form.id_type && !idTypes.some(it => getIdTypeValue(it) === form.id_type || it.name === form.id_type)" :value="form.id_type">{{ form.id_type }}</option>
                  </select>
                </div>

                <div class="f s3">
                  <label>Số giấy tờ <span class="req">*</span></label>
                  <input v-model="form.id_number" type="text" placeholder="Số CCCD / Passport" />
                </div>

                <div class="f s3">
                  <label>Ngày cấp</label>
                  <div class="wi d" @click="triggerDatePicker($event)">
                    <input v-model="form.id_issue_date" type="date" @click="$event.target.showPicker?.()" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  </div>
                </div>

                <div class="f s3">
                  <label>Ngày hết hạn</label>
                  <div class="wi d" @click="triggerDatePicker($event)">
                    <input v-model="form.passport_expiry" type="date" @click="$event.target.showPicker?.()" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  </div>
                  <div v-if="isPassportExpiredBeforeDeparture" class="msg err">
                    Giấy tờ hết hạn trước ngày trả phòng
                  </div>
                </div>

                <div class="f s3 vf">
                  <label>Ngày nhập cảnh</label>
                  <div class="wi d" @click="triggerDatePicker($event)">
                    <input v-model="form.entry_date" type="date" @click="$event.target.showPicker?.()" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  </div>
                </div>

                <div class="f s3 vf">
                  <label>Cửa khẩu</label>
                  <select v-model="form.border_gate">
                    <option value="">— Chọn cửa khẩu —</option>
                    <option v-for="bg in borderGates" :key="bg.id" :value="bg.name">{{ bg.name }}</option>
                    <option v-if="form.border_gate && !borderGateNames.includes(form.border_gate)" :value="form.border_gate">{{ form.border_gate }}</option>
                  </select>
                </div>

                <div class="f s3 vf">
                  <label>Mục đích nhập cảnh</label>
                  <select v-model="form.entry_purpose">
                    <option value="">— Chọn mục đích —</option>
                    <option v-for="ep in entryPurposes" :key="ep.id" :value="ep.name">{{ ep.name }}</option>
                    <option v-if="form.entry_purpose && !entryPurposes.some(ep => ep.name === form.entry_purpose)" :value="form.entry_purpose">{{ form.entry_purpose }}</option>
                  </select>
                </div>

                <div class="f s3">
                  <label>Hình thức cư trú</label>
                  <select v-model="form.residence_type">
                    <option value="">— Chọn hình thức —</option>
                    <option v-for="rt in residenceTypes" :key="rt.code || rt.name" :value="rt.name_new_form || rt.name">{{ rt.name_new_form || rt.name }}</option>
                    <option v-if="form.residence_type && !residenceTypes.some(rt => (rt.name_new_form || rt.name) === form.residence_type)" :value="form.residence_type">{{ form.residence_type }}</option>
                  </select>
                </div>

                <div class="f s3">
                  <label>Tạm trú đến</label>
                  <div class="wi d" @click="triggerDatePicker($event)">
                    <input v-model="form.temp_residence_to" type="date" @click="$event.target.showPicker?.()" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                  </div>
                </div>

                <div class="f s3 vf">
                  <label>Số Visa</label>
                  <input v-model="form.visa_no" type="text" placeholder="Số visa / thẻ tạm trú" />
                </div>
              </div>
            </section>

            <!-- SECTION 3: Liên hệ & địa chỉ -->
            <section class="sec">
              <h3>Liên hệ &amp; địa chỉ</h3>
              <div class="g c12">
                <div class="f s4">
                  <label>Điện thoại</label>
                  <div class="wi">
                    <input v-model="form.phone" type="tel" placeholder="+84 …" />
                    <svg viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                  </div>
                </div>

                <div class="f s8">
                  <label>Email</label>
                  <div class="wi">
                    <input v-model="form.email" type="email" placeholder="name@mail.com" />
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                  </div>
                </div>

                <div class="f s12">
                  <label>Địa chỉ</label>
                  <input v-model="form.address" type="text" placeholder="Số nhà, tên đường, khu dân cư…" />
                </div>

                <div class="f s4">
                  <label>Tỉnh / Thành phố</label>
                  <select v-model="form.province" @change="onProvinceChange(form.province)">
                    <option value="">— Chọn tỉnh thành —</option>
                    <option v-for="p in provincesList" :key="p.code" :value="p.name">{{ p.name }}</option>
                  </select>
                </div>

                <div class="f s4">
                  <label>Quận / Huyện</label>
                  <select v-model="form.district" @change="onDistrictChange(form.district)" :disabled="!form.province">
                    <option value="">— Chọn quận huyện —</option>
                    <option v-for="d in currentDistricts" :key="d.code" :value="d.name">{{ d.name }}</option>
                  </select>
                </div>

                <div class="f s4">
                  <label>Phường / Xã</label>
                  <select v-model="form.ward" :disabled="!form.district">
                    <option value="">— Chọn phường xã —</option>
                    <option v-for="w in currentWards" :key="w.code" :value="w.name">{{ w.name }}</option>
                  </select>
                </div>
              </div>
            </section>

            <!-- SECTION 4: Ghi chú -->
            <section class="sec">
              <h3>Ghi chú</h3>
              <div class="f">
                <textarea v-model="form.note" placeholder="Yêu cầu đặc biệt, lưu ý sở thích, chế độ ăn hoặc phục vụ phòng…"></textarea>
              </div>
            </section>
          </div>
        </div>

        <!-- Footer -->
        <div class="m-foot select-none">
          <div class="meta">
            <span>Cập nhật: <b>{{ auditTime }}</b> · bởi <b>{{ auditUser }}</b></span>
          </div>

          <button type="button" @click="handleSave" :disabled="saving" class="btn p">
            <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
            <span>{{ saving ? 'Đang lưu...' : 'Lưu' }}</span>
            <kbd class="ml-1 text-[10px] opacity-75">Ctrl+S</kbd>
          </button>

          <button type="button" @click="$emit('close')" class="btn g">
            <i class="fa-solid fa-xmark mr-1"></i>Đóng
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import {
  updateBookingRoomGuest,
  updateBookingChild,
  fetchNationalities,
  fetchGuestDefinitions,
  syncGeoData,
  uploadGuestAvatar
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

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
const authStore = useAuthStore()
const saving = ref(false)

const guestPhotos = ref([])
const curPhotoIdx = ref(0)
const isDragOverPhoto = ref(false)
const photoInputRef = ref(null)

// Geography data
const provincesList = ref([])
const districtsCache = ref({})
const wardsCache = ref({})
const currentDistricts = ref([])
const currentWards = ref([])

// Master Data Definitions
const guestDefinitions = ref({
  titles: [],
  border_gates: [],
  entry_purposes: [],
  guest_types: [],
  id_types: [],
  residence_types: [],
})

const titles = computed(() => {
  if (guestDefinitions.value.titles?.length > 0) {
    return guestDefinitions.value.titles.map(t => t.name)
  }
  return ['Boy.', 'Girl.', 'Inf', 'Kid.', 'Mr.', 'Ms.']
})

const borderGates = computed(() => guestDefinitions.value.border_gates || [])
const borderGateNames = computed(() => borderGates.value.map(g => g.name))
const entryPurposes = computed(() => guestDefinitions.value.entry_purposes || [])
const guestTypes = computed(() => guestDefinitions.value.guest_types || [])
const idTypes = computed(() => guestDefinitions.value.id_types || [])
const residenceTypes = computed(() => {
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

const nationalities = ref([])

async function loadNationalities() {
  if (nationalities.value.length > 0) return
  try {
    const res = await fetchNationalities()
    if (res.data?.success) {
      const list = res.data.data || []
      nationalities.value = list.map(item => ({
        code: item.asm_code || item.nationality_id || '',
        label: `${item.nationality_id || item.asm_code || '—'} - ${item.asm_name || item.nationality_name || ''}`
      })).filter(item => item.code !== '')
    }
  } catch (err) {
    console.error('Lỗi tải danh sách quốc tịch:', err)
  }
}

// Open API Geography
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

async function onProvinceChange(provinceName) {
  form.value.province = provinceName
  form.value.district = ''
  form.value.ward = ''
  currentDistricts.value = []
  currentWards.value = []
  if (provinceName) {
    currentDistricts.value = await fetchDistricts(provinceName)
    syncGeoData({ province: provinceName }).catch(() => {})
  }
}

async function onDistrictChange(districtName) {
  form.value.district = districtName
  form.value.ward = ''
  currentWards.value = []
  if (form.value.province && districtName) {
    currentWards.value = await fetchWards(form.value.province, districtName)
    syncGeoData({ province: form.value.province, district: districtName }).catch(() => {})
  }
}

const form = ref({})

async function resetForm() {
  const g = props.guest || {}
  form.value = {
    full_name: g.full_name || '',
    title: g.title || 'Mr.',
    dob: g.dob ? g.dob.substring(0, 10) : '',
    nationality_code: g.nationality_code || 'VN',
    id_type: g.id_type || 'CCCD',
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

  if (g.avatar) {
    guestPhotos.value = [g.avatar]
  } else {
    guestPhotos.value = []
  }
  curPhotoIdx.value = 0

  if (g.province) {
    currentDistricts.value = await fetchDistricts(g.province)
    if (g.district) {
      currentWards.value = await fetchWards(g.province, g.district)
    } else {
      currentWards.value = []
    }
  } else {
    currentDistricts.value = []
    currentWards.value = []
  }
}

const isVietnameseGuest = computed(() => {
  const c = (form.value.nationality_code || '').toUpperCase()
  return c === 'VN' || c === 'VNM' || c === 'VIETNAM' || c === 'VIỆT NAM'
})

const isPassportExpiredBeforeDeparture = computed(() => {
  if (!form.value.passport_expiry || !props.room?.departure_date) return false
  const exp = new Date(form.value.passport_expiry)
  const dep = new Date(props.room.departure_date)
  return exp < dep
})

// Photo management
function triggerPhotoClick() {
  if (guestPhotos.value.length === 0) {
    triggerPhotoInput()
  } else {
    curPhotoIdx.value = (curPhotoIdx.value + 1) % guestPhotos.value.length
  }
}

function triggerPhotoInput() {
  if (photoInputRef.value) photoInputRef.value.click()
}

function handlePhotoUpload(e) {
  const files = e.target.files
  if (!files || files.length === 0) return
  addPhotos(files)
  e.target.value = ''
}

function handlePhotoDrop(e) {
  isDragOverPhoto.value = false
  if (e.dataTransfer?.files?.length > 0) {
    addPhotos(e.dataTransfer.files)
  }
}

function formatPhotoUrl(p) {
  if (!p) return ''
  if (p.startsWith('data:') || p.startsWith('blob:') || p.startsWith('http://') || p.startsWith('https://')) {
    return p
  }
  return '/' + p.replace(/^\/+/, '')
}

async function addPhotos(files) {
  const imageFiles = Array.from(files).filter(f => f.type.startsWith('image/'))
  for (const f of imageFiles) {
    if (f.size > 5 * 1024 * 1024) {
      uiStore.showToast(`${f.name} vượt quá 5 MB`, 'warning')
      continue
    }
    const previewUrl = URL.createObjectURL(f)
    guestPhotos.value.push(previewUrl)
    curPhotoIdx.value = guestPhotos.value.length - 1

    if (props.guest?.id && props.guestType === 'adult') {
      try {
        const formData = new FormData()
        formData.append('avatar', f)
        const res = await uploadGuestAvatar(props.guest.id, formData)
        if (res.data?.success && res.data?.avatar) {
          const avatarUrl = res.data.avatar
          const idx = guestPhotos.value.indexOf(previewUrl)
          if (idx !== -1) {
            guestPhotos.value[idx] = avatarUrl
          }
          form.value.avatar = avatarUrl
          uiStore.showToast('Tải ảnh đại diện lên thành công', 'success')
        }
      } catch (uploadErr) {
        console.error('Lỗi tải ảnh đại diện:', uploadErr)
        uiStore.showToast(uploadErr.response?.data?.message || 'Không thể lưu ảnh lên máy chủ', 'error')
      }
    }
  }
}

function removeCurrentPhoto() {
  if (guestPhotos.value.length === 0) return
  guestPhotos.value.splice(curPhotoIdx.value, 1)
  curPhotoIdx.value = Math.max(0, Math.min(curPhotoIdx.value, guestPhotos.value.length - 1))
  const remaining = guestPhotos.value[0]
  if (remaining && !remaining.startsWith('blob:') && !remaining.startsWith('data:')) {
    form.value.avatar = remaining
  } else if (guestPhotos.value.length === 0) {
    form.value.avatar = null
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

function handleKeyDown(e) {
  if (props.show && e.ctrlKey && e.key.toLowerCase() === 's') {
    e.preventDefault()
    handleSave()
  }
}

watch(() => props.show, (v) => {
  if (v) {
    modalPos.value = { x: 0, y: 0 }
    resetForm()
    loadNationalities()
    loadGuestDefinitions()
    loadProvinces()
  }
})

watch(() => props.guest, () => {
  if (props.show) resetForm()
})

onMounted(() => {
  loadNationalities()
  loadGuestDefinitions()
  loadProvinces()
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})

function formatDate(d) {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString('vi-VN')
  } catch { return d }
}

function formatDateTime(d) {
  if (!d) return ''
  try {
    const dt = new Date(d)
    const pad = n => String(n).padStart(2, '0')
    const day = pad(dt.getDate())
    const month = pad(dt.getMonth() + 1)
    const year = dt.getFullYear()
    const hours = pad(dt.getHours())
    const minutes = pad(dt.getMinutes())
    return `${day}/${month}/${year} ${hours}:${minutes}`
  } catch {
    return d
  }
}

const auditTime = computed(() => {
  const t = props.guest?.updated_at || props.guest?.created_at
  if (!t) return formatDateTime(new Date())
  return formatDateTime(t)
})

const auditUser = computed(() => {
  return props.guest?.checkin_by || props.guest?.created_by || authStore.user?.username || authStore.user?.name || 'FO.Lan'
})

function triggerDatePicker(e) {
  const container = e.currentTarget
  const input = container.querySelector('input[type="date"]')
  if (input) {
    if (typeof input.showPicker === 'function') {
      try {
        input.showPicker()
      } catch {
        input.focus()
      }
    } else {
      input.focus()
    }
  }
}

function formatMoney(v) {
  if (!v) return '0'
  return Number(v).toLocaleString('vi-VN')
}

function calculateNights(room) {
  if (!room?.arrival_date || !room?.departure_date) return 1
  const a = new Date(room.arrival_date)
  const d = new Date(room.departure_date)
  const diff = Math.round((d - a) / (1000 * 60 * 60 * 24))
  return diff > 0 ? diff : 1
}

async function handleSave() {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (props.guestType === 'child') {
      delete payload.avatar
    } else {
      if (form.value.avatar && !form.value.avatar.startsWith('data:') && !form.value.avatar.startsWith('blob:') && form.value.avatar.length <= 255) {
        payload.avatar = form.value.avatar
      } else if (guestPhotos.value.length === 0) {
        payload.avatar = null
      } else {
        // Tránh gửi chuỗi base64/blob vượt quá 255 ký tự làm lỗi validation
        delete payload.avatar
      }
    }
    let res
    if (props.guestType === 'adult') {
      res = await updateBookingRoomGuest(props.room.booking_room_id, props.guest.id, payload)
    } else {
      res = await updateBookingChild(props.guest.id, payload)
    }
    if (res.data?.success) {
      uiStore.showToast('Lưu thông tin khách thành công!', 'success')
      emit('saved', { ...props.guest, ...payload, avatar: form.value.avatar })
    } else {
      uiStore.showToast(res.data?.message || 'Lưu thất bại!', 'error')
    }
  } catch (err) {
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra!', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
:deep(:root), .modal {
  --navy: #1E2D4A;
  --blue: #2F6FED;
  --blue-soft: #EAF1FF;
  --blue-line: #B9CDF8;
  --bg: #F3F5F9;
  --card: #ffffff;
  --line: #DDE3EC;
  --line-strong: #C5CDDA;
  --txt: #1B2433;
  --muted: #6B7686;
  --label: #4A5568;
  --red: #D93025;
  --green: #188A4B;
  --amber: #B5730E;
  --r: 6px;
}

.modal {
  background: var(--bg);
  border-radius: 10px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
  color: var(--txt);
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.m-head {
  background: var(--navy);
  color: #fff;
  padding: 10px 18px;
  border-radius: 10px 10px 0 0;
  display: flex;
  align-items: center;
  gap: 12px;
}
.m-head h1 {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}
.chip {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 9px;
  border-radius: 99px;
  background: rgba(255, 255, 255, 0.12);
  color: #DCE6FF;
}
.chip.b {
  background: var(--blue);
  color: #ffffff;
}
.head-btn {
  font-size: 11px;
  font-weight: 500;
  padding: 4px 9px;
  border-radius: 5px;
  background: rgba(255, 255, 255, 0.1);
  color: #e2e8f0;
  border: 1px solid rgba(255, 255, 255, 0.15);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
}
.head-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}
.m-head .x {
  margin-left: 4px;
  width: 26px;
  height: 26px;
  border: 0;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.m-head .x:hover {
  background: rgba(220, 38, 38, 0.7);
}

.m-body {
  padding: 14px 18px;
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 14px;
  align-items: start;
}

.stay {
  grid-column: 1 / -1;
  background: var(--navy);
  color: #fff;
  border-radius: var(--r);
  padding: 8px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}
.stay .room {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.5px;
  line-height: 1.1;
}
.stay .room small {
  display: block;
  font-size: 10px;
  font-weight: 500;
  color: #9FB2D6;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  margin-bottom: 2px;
}
.stay .st {
  font-size: 12px;
  color: #9FB2D6;
}
.stay .kv {
  font-size: 10.5px;
  color: #9FB2D6;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.stay .kv b {
  display: block;
  font-size: 13px;
  color: #fff;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0;
  margin-top: 1px;
}
.stay .badge {
  background: var(--blue);
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
  color: #fff;
}

.side {
  display: flex;
  flex-direction: column;
  gap: 10px;
  position: sticky;
  top: 0;
}
.photo {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--r);
  padding: 10px;
}
.photo .drop {
  position: relative;
  aspect-ratio: 3/4;
  border: 1.5px dashed var(--blue-line);
  border-radius: 5px;
  background: var(--blue-soft);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: 0.15s;
}
.photo .drop:hover, .photo .drop.over {
  border-color: var(--blue);
  background: #DFE9FF;
}
.photo .drop img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.photo .drop.has .ph {
  display: none;
}
.photo .ph {
  text-align: center;
  color: var(--blue);
  font-size: 11px;
  line-height: 1.4;
  padding: 10px;
}
.photo .ph svg {
  width: 34px;
  height: 34px;
  margin-bottom: 6px;
  stroke: var(--blue);
  fill: none;
  stroke-width: 1.5;
  margin-left: auto;
  margin-right: auto;
}
.photo .drop .rm {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 22px;
  height: 22px;
  border: 0;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  line-height: 1;
  display: none;
}
.photo .drop.has:hover .rm {
  display: block;
}
.photo .drop .cnt {
  position: absolute;
  left: 6px;
  bottom: 6px;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 99px;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
}
.thumbs {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 5px;
  margin-top: 6px;
}
.th {
  position: relative;
  aspect-ratio: 1;
  border-radius: 4px;
  overflow: hidden;
  border: 2px solid transparent;
  cursor: pointer;
  background: var(--bg);
}
.th img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.th.on {
  border-color: var(--blue);
}
.th.add {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1.5px dashed var(--line-strong);
  color: var(--muted);
  font-size: 18px;
}
.th.add:hover {
  border-color: var(--blue);
  color: var(--blue);
}
.photo .acts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-top: 8px;
}
.photo .acts button {
  font: inherit;
  font-size: 11px;
  font-weight: 500;
  padding: 6px 4px;
  border: 1px solid var(--line-strong);
  border-radius: 4px;
  background: #fff;
  color: var(--label);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}
.photo .acts button:hover {
  border-color: var(--blue);
  color: var(--blue);
}
.photo .acts svg {
  width: 13px;
  height: 13px;
  stroke: currentColor;
  fill: none;
  stroke-width: 1.8;
}
.photo .hint {
  font-size: 10px;
  color: var(--muted);
  margin-top: 6px;
  text-align: center;
}

.main {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}
.sec {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--r);
  padding: 10px 14px 12px;
}
.sec > h3 {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: var(--navy);
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 9px;
}
.sec > h3::after {
  content: "";
  flex: 1;
  height: 1px;
  background: var(--line);
}
.sec > h3 .req {
  color: var(--red);
  font-weight: 700;
}
.g {
  display: grid;
  gap: 8px 12px;
}
.c12 {
  grid-template-columns: repeat(12, 1fr);
}
.f {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.f label {
  font-size: 11px;
  font-weight: 500;
  color: var(--label);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.f label .req {
  color: var(--red);
}
.f input, .f select, .f textarea {
  font: inherit;
  color: var(--txt);
  height: 30px;
  border: 1px solid var(--line-strong);
  border-radius: 4px;
  padding: 0 9px;
  background: #fff;
  width: 100%;
  outline: 0;
  transition: 0.12s;
  box-sizing: border-box;
}
.f textarea {
  height: auto;
  padding: 6px 9px;
  resize: vertical;
  min-height: 52px;
}
.f input:focus, .f select:focus, .f textarea:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(47, 111, 237, 0.15);
}
.f input::placeholder {
  color: #A6AFBD;
}
.f select {
  appearance: none;
  padding-right: 24px;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%236B7686' stroke-width='1.5'/%3E%3C/svg%3E") no-repeat right 8px center;
}
.f .wi {
  position: relative;
}
.f .wi input {
  padding-left: 28px;
}
.f .wi svg {
  position: absolute;
  left: 8px;
  top: 50%;
  transform: translateY(-50%);
  width: 14px;
  height: 14px;
  stroke: #8A94A6;
  fill: none;
  stroke-width: 1.7;
  pointer-events: none;
}
.f .wi.d {
  position: relative;
  cursor: pointer;
}
.f .wi.d input {
  padding-left: 9px;
  padding-right: 30px;
  cursor: pointer;
}
.f .wi.d input::-webkit-calendar-picker-indicator {
  cursor: pointer;
  position: absolute;
  right: 0;
  top: 0;
  width: 32px;
  height: 100%;
  opacity: 0;
  z-index: 2;
}
.f .wi.d svg {
  left: auto;
  right: 8px;
  pointer-events: none;
  z-index: 1;
}

.s2 { grid-column: span 2; }
.s3 { grid-column: span 3; }
.s4 { grid-column: span 4; }
.s5 { grid-column: span 5; }
.s6 { grid-column: span 6; }
.s8 { grid-column: span 8; }
.s12 { grid-column: span 12; }

.msg {
  font-size: 10.5px;
  color: var(--muted);
  margin-top: 2px;
}
.msg.err {
  color: var(--red);
  font-weight: 500;
}

.m-foot {
  background: #fff;
  border-top: 1px solid var(--line);
  padding: 10px 18px;
  border-radius: 0 0 10px 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.m-foot .meta {
  font-size: 11px;
  color: var(--muted);
  margin-right: auto;
}
.m-foot .meta b {
  color: var(--navy);
}
.btn {
  font: inherit;
  font-weight: 500;
  height: 32px;
  padding: 0 16px;
  border-radius: 5px;
  border: 1px solid var(--line-strong);
  background: #fff;
  color: var(--label);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.12s ease;
}
.btn:hover {
  border-color: var(--blue);
  color: var(--blue);
}
.btn.p {
  background: var(--blue);
  border-color: var(--blue);
  color: #fff;
}
.btn.p:hover {
  background: #2560D6;
}
.btn.g {
  color: var(--navy);
}
.btn svg {
  width: 14px;
  height: 14px;
  stroke: currentColor;
  fill: none;
  stroke-width: 2;
}

@media (max-width: 760px) {
  .m-body {
    grid-template-columns: 1fr;
  }
  .side {
    position: static;
  }
  .stay {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
}
</style>
