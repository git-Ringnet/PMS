<script setup>
import { ref, computed, onMounted } from 'vue'
import TimePicker24h from '@/components/TimePicker24h.vue'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { useDraggableModal } from '@/composables/useDraggableModal'
import { fetchUsers } from '@/services/company-service'
import {
  fetchSecuritySettings,
  updateSecuritySettings,
  createAllowedIp,
  updateAllowedIp,
  deleteAllowedIp,
  createTimeWindow,
  updateTimeWindow,
  deleteTimeWindow,
  addExemptUsers,
  removeExemptUser
} from '@/services/security-service'

const uiStore = useUiStore()
const authStore = useAuthStore()
const themeBg = computed(() => authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

const loading = ref(false)
const settings = ref({ autoLogoutEnabled: false, autoLogoutHours: 5, ipRestrictionEnabled: false, timeRestrictionEnabled: false })
const hoursInput = ref(5)
const ips = ref([])
const timeWindows = ref([])
const exemptUsers = ref([])
const myIp = ref('')
const showMyIp = ref(false)

const DAYS = [
  { value: 1, label: 'Thứ hai' },
  { value: 2, label: 'Thứ ba' },
  { value: 3, label: 'Thứ tư' },
  { value: 4, label: 'Thứ năm' },
  { value: 5, label: 'Thứ sáu' },
  { value: 6, label: 'Thứ bảy' },
  { value: 7, label: 'Chủ nhật' }
]

function applySnapshot(data) {
  settings.value = data.settings
  hoursInput.value = data.settings.autoLogoutHours
  ips.value = data.ips || []
  timeWindows.value = data.timeWindows || []
  exemptUsers.value = data.exemptUsers || []
  myIp.value = data.myIp || ''
}

function errorText(error, fallback) {
  const errors = error?.response?.data?.errors
  return error?.response?.data?.message || (errors ? Object.values(errors).flat()[0] : '') || fallback
}

async function load() {
  loading.value = true
  try {
    const res = await fetchSecuritySettings()
    applySnapshot(res.data.data)
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không tải được cấu hình bảo mật.'), 'error')
  } finally {
    loading.value = false
  }
}

async function saveSettings(payload, successMessage) {
  try {
    const res = await updateSecuritySettings(payload)
    applySnapshot(res.data.data)
    if (successMessage) uiStore.showToast(successMessage, 'success')
    return true
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không lưu được cấu hình.'), 'error')
    await load()
    return false
  }
}

// ---- Tự động đăng xuất ----
const toggleAutoLogout = () => saveSettings({ auto_logout_enabled: !settings.value.autoLogoutEnabled })

async function saveHours() {
  const hours = Number(hoursInput.value)
  if (!Number.isInteger(hours) || hours < 1 || hours > 720) {
    uiStore.showToast('Số giờ phải là số nguyên từ 1 đến 720.', 'warning')
    hoursInput.value = settings.value.autoLogoutHours
    return
  }
  if (hours === settings.value.autoLogoutHours) return
  await saveSettings({ auto_logout_hours: hours }, 'Đã lưu thời gian tự động đăng xuất.')
}

// ---- Giới hạn theo IP / thời gian ----
const toggleIpRestriction = () => saveSettings({ ip_restriction_enabled: !settings.value.ipRestrictionEnabled })
const toggleTimeRestriction = () => saveSettings({ time_restriction_enabled: !settings.value.timeRestrictionEnabled })

// ---- Modal dùng chung: kéo thả, màu theo Topbar ----
const ipModal = useDraggableModal()
const timeModal = useDraggableModal()
const userModal = useDraggableModal()

// ---- Địa chỉ IP ----
const showIpModal = ref(false)
const ipForm = ref({ id: null, name: '', ip: '' })
const ipError = ref('')

function openIpModal(item = null) {
  ipForm.value = item ? { id: item.id, name: item.name, ip: item.ip } : { id: null, name: '', ip: '' }
  ipError.value = ''
  ipModal.resetPosition()
  showIpModal.value = true
}

async function saveIp() {
  if (!ipForm.value.name.trim() || !ipForm.value.ip.trim()) {
    ipError.value = 'Vui lòng nhập tên địa chỉ và IP.'
    return
  }
  try {
    const payload = { name: ipForm.value.name.trim(), ip: ipForm.value.ip.trim() }
    const res = ipForm.value.id ? await updateAllowedIp(ipForm.value.id, payload) : await createAllowedIp(payload)
    applySnapshot(res.data.data)
    showIpModal.value = false
  } catch (error) {
    ipError.value = errorText(error, 'Không lưu được địa chỉ IP.')
  }
}

async function removeIp(item) {
  if (!(await uiStore.confirm({ title: 'Xóa địa chỉ IP', message: `Xóa địa chỉ "${item.name}" (${item.ip})?` }))) return
  try {
    const res = await deleteAllowedIp(item.id)
    applySnapshot(res.data.data)
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xóa được địa chỉ IP.'), 'error')
  }
}

// ---- Khoảng thời gian ----
const showTimeModal = ref(false)
const timeForm = ref({ id: null, days: [], startTime: '00:00', endTime: '23:59' })
const timeError = ref('')
const allDaysChecked = computed({
  get: () => timeForm.value.days.length === DAYS.length,
  set: (checked) => { timeForm.value.days = checked ? DAYS.map(day => day.value) : [] }
})

function openTimeModal(item = null) {
  timeForm.value = item
    ? { id: item.id, days: [...item.days], startTime: item.startTime, endTime: item.endTime }
    : { id: null, days: [], startTime: '00:00', endTime: '23:59' }
  timeError.value = ''
  timeModal.resetPosition()
  showTimeModal.value = true
}

function describeWindow(item) {
  const days = item.days.length === DAYS.length
    ? 'Tất cả các ngày trong tuần'
    : DAYS.filter(day => item.days.includes(day.value)).map(day => day.label).join(', ')
  return `${days}: ${item.startTime} - ${item.endTime}`
}

async function saveTimeWindow() {
  if (timeForm.value.days.length === 0) {
    timeError.value = 'Vui lòng chọn ít nhất một ngày trong tuần.'
    return
  }
  try {
    const payload = { days: timeForm.value.days, start_time: timeForm.value.startTime, end_time: timeForm.value.endTime }
    const res = timeForm.value.id ? await updateTimeWindow(timeForm.value.id, payload) : await createTimeWindow(payload)
    applySnapshot(res.data.data)
    showTimeModal.value = false
  } catch (error) {
    timeError.value = errorText(error, 'Không lưu được khoảng thời gian.')
  }
}

async function removeTimeWindow(item) {
  if (!(await uiStore.confirm({ title: 'Xóa khoảng thời gian', message: `Xóa "${describeWindow(item)}"?` }))) return
  try {
    const res = await deleteTimeWindow(item.id)
    applySnapshot(res.data.data)
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xóa được khoảng thời gian.'), 'error')
  }
}

// ---- Người dùng không bị giới hạn ----
const showUserModal = ref(false)
const userSearch = ref('')
const userRows = ref([])
const userTotal = ref(0)
const selectedUserIds = ref([])
const userLoading = ref(false)

async function searchUsers() {
  userLoading.value = true
  try {
    const res = await fetchUsers({ search: userSearch.value || undefined, per_page: 100, sort_field: 'employee_code', sort_dir: 'desc' })
    userRows.value = res.data?.data || []
    userTotal.value = res.data?.meta?.total ?? userRows.value.length
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không tải được danh sách nhân viên.'), 'error')
  } finally {
    userLoading.value = false
  }
}

async function openUserModal() {
  selectedUserIds.value = []
  userSearch.value = ''
  userModal.resetPosition()
  showUserModal.value = true
  await searchUsers()
}

const exemptIds = computed(() => new Set(exemptUsers.value.map(user => user.id)))
const selectableRows = computed(() => userRows.value.filter(user => !exemptIds.value.has(user.id)))
const allSelected = computed({
  get: () => selectableRows.value.length > 0 && selectableRows.value.every(user => selectedUserIds.value.includes(user.id)),
  set: (checked) => { selectedUserIds.value = checked ? selectableRows.value.map(user => user.id) : [] }
})

async function saveExemptUsers() {
  if (selectedUserIds.value.length === 0) {
    showUserModal.value = false
    return
  }
  try {
    const res = await addExemptUsers(selectedUserIds.value)
    applySnapshot(res.data.data)
    showUserModal.value = false
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không thêm được người dùng.'), 'error')
  }
}

async function removeUser(user) {
  if (!(await uiStore.confirm({ title: 'Bỏ miễn giới hạn', message: `Bỏ "${user.name}" khỏi danh sách không bị giới hạn?` }))) return
  try {
    const res = await removeExemptUser(user.id)
    applySnapshot(res.data.data)
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xóa được người dùng.'), 'error')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex-1 overflow-auto p-6 bg-white text-slate-800 text-sm">
    <div v-if="loading" class="text-xs text-slate-400">Đang tải cấu hình...</div>

    <!-- Tự động đăng xuất -->
    <section class="max-w-4xl">
      <h2 class="text-2xl font-bold mb-3">Tự động đăng xuất</h2>
      <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
        <button type="button" role="switch" :aria-checked="settings.autoLogoutEnabled"
          class="relative w-11 h-6 rounded-full border-none cursor-pointer transition-colors"
          :class="settings.autoLogoutEnabled ? 'bg-sky-500' : 'bg-slate-300'" @click="toggleAutoLogout">
          <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all" :class="settings.autoLogoutEnabled ? 'left-[22px]' : 'left-0.5'"></span>
        </button>
        <span class="font-semibold">Tự động đăng xuất</span>
      </label>
      <div v-if="settings.autoLogoutEnabled" class="flex items-center gap-2 mt-3 text-xs">
        <span>Toàn bộ tài khoản sẽ tự động đăng xuất sau</span>
        <input v-model.number="hoursInput" type="number" min="1" max="720"
          class="w-24 h-8 border border-slate-300 rounded px-2 text-sm" @change="saveHours" @keyup.enter="saveHours" />
        <span>giờ</span>
      </div>
      <p v-if="settings.autoLogoutEnabled" class="mt-2 text-xs text-slate-500">Tính từ thời điểm người dùng đăng nhập, cứ sau khoảng được thiết lập sẽ tự động đăng xuất. Áp dụng cho mọi tài khoản.</p>
    </section>

    <hr class="my-6 border-slate-200" />

    <!-- Giới hạn truy cập -->
    <section class="max-w-4xl">
      <h2 class="text-2xl font-bold mb-3">Giới hạn truy cập</h2>
      <p class="text-xs text-slate-500 mb-3">Quản trị hệ thống luôn được truy cập và không bị các giới hạn dưới đây.</p>

      <!-- Theo IP -->
      <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
        <button type="button" role="switch" :aria-checked="settings.ipRestrictionEnabled"
          class="relative w-11 h-6 rounded-full border-none cursor-pointer transition-colors"
          :class="settings.ipRestrictionEnabled ? 'bg-sky-500' : 'bg-slate-300'" @click="toggleIpRestriction">
          <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all" :class="settings.ipRestrictionEnabled ? 'left-[22px]' : 'left-0.5'"></span>
        </button>
        <span class="font-semibold">Giới hạn theo địa chỉ IP</span>
      </label>
      <div v-if="settings.ipRestrictionEnabled">
        <p class="mt-2 text-xs text-slate-500">Tính năng giúp thiết lập các địa chỉ IP được phép truy cập vào hệ thống (nhập IP đơn hoặc dải CIDR, ví dụ 192.168.1.0/24).</p>
        <div class="flex items-center gap-5 mt-2 text-xs">
          <button type="button" class="flex items-center gap-1.5 text-sky-600 bg-transparent border-none cursor-pointer p-0 hover:text-sky-800" @click="openIpModal()">
            <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-xs leading-4 text-center">+</span> Thêm địa chỉ IP
          </button>
          <button type="button" class="flex items-center gap-1.5 text-sky-600 bg-transparent border-none cursor-pointer p-0 hover:text-sky-800" @click="showMyIp = !showMyIp">
            <span class="w-4 h-4 rounded-full bg-sky-200 text-sky-700 text-[10px] leading-4 text-center">◉</span> Xem địa chỉ IP của tôi
          </button>
          <span v-if="showMyIp" class="text-base font-bold text-slate-800">: {{ myIp }}</span>
        </div>

        <table class="w-full mt-3 border border-slate-200 border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 h-9 text-left font-bold">
              <th class="px-3 border border-slate-200">Tên địa chỉ</th>
              <th class="px-3 border border-slate-200">IP</th>
              <th class="w-28 border border-slate-200"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="ips.length === 0"><td colspan="3" class="p-5 text-center text-amber-600 text-xs">Chưa có địa chỉ IP nào: nhân viên (trừ quản trị hệ thống và người dùng được miễn) sẽ không truy cập được. Hãy thêm địa chỉ IP.</td></tr>
            <tr v-for="item in ips" :key="item.id" class="h-9 even:bg-slate-50">
              <td class="px-3 border border-slate-200">{{ item.name }}</td>
              <td class="px-3 border border-slate-200">{{ item.ip }}</td>
              <td class="px-3 border border-slate-200 text-center text-xs">
                <button type="button" class="text-red-500 underline bg-transparent border-none cursor-pointer" @click="removeIp(item)">Xóa</button>
                <button type="button" class="text-sky-600 underline bg-transparent border-none cursor-pointer ml-2" @click="openIpModal(item)">Sửa</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Theo thời gian -->
      <label class="flex items-center gap-3 cursor-pointer select-none w-fit mt-6">
        <button type="button" role="switch" :aria-checked="settings.timeRestrictionEnabled"
          class="relative w-11 h-6 rounded-full border-none cursor-pointer transition-colors"
          :class="settings.timeRestrictionEnabled ? 'bg-sky-500' : 'bg-slate-300'" @click="toggleTimeRestriction">
          <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all" :class="settings.timeRestrictionEnabled ? 'left-[22px]' : 'left-0.5'"></span>
        </button>
        <span class="font-semibold">Giới hạn theo thời gian</span>
      </label>
      <div v-if="settings.timeRestrictionEnabled">
        <p class="mt-2 text-xs text-slate-500">Thiết lập các khoảng thời gian mà người dùng được phép truy cập vào hệ thống (giờ Việt Nam).</p>
        <button type="button" class="flex items-center gap-1.5 mt-2 text-xs text-sky-600 bg-transparent border-none cursor-pointer p-0 hover:text-sky-800" @click="openTimeModal()">
          <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-xs leading-4 text-center">+</span> Thêm thời gian
        </button>

        <table class="w-full mt-3 border border-slate-200 border-collapse text-sm">
          <thead>
            <tr class="bg-slate-50 h-9 text-left font-bold">
              <th class="px-3 border border-slate-200">Khoảng thời gian</th>
              <th class="w-28 border border-slate-200"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="timeWindows.length === 0"><td colspan="2" class="p-5 text-center text-amber-600 text-xs">Chưa có khoảng thời gian nào: nhân viên (trừ quản trị hệ thống và người dùng được miễn) sẽ không truy cập được. Hãy thêm khoảng thời gian.</td></tr>
            <tr v-for="item in timeWindows" :key="item.id" class="h-9 even:bg-slate-50">
              <td class="px-3 border border-slate-200">{{ describeWindow(item) }}</td>
              <td class="px-3 border border-slate-200 text-center text-xs">
                <button type="button" class="text-red-500 underline bg-transparent border-none cursor-pointer" @click="removeTimeWindow(item)">Xóa</button>
                <button type="button" class="text-sky-600 underline bg-transparent border-none cursor-pointer ml-2" @click="openTimeModal(item)">Sửa</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Người dùng không bị giới hạn -->
      <h3 class="mt-6 font-bold">Danh sách người dùng không bị giới hạn quyền</h3>
      <button type="button" class="flex items-center gap-1.5 mt-2 text-xs text-sky-600 bg-transparent border-none cursor-pointer p-0 hover:text-sky-800" @click="openUserModal">
        <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-xs leading-4 text-center">+</span> Thêm người dùng
      </button>
      <table v-if="exemptUsers.length" class="w-full mt-3 border border-slate-200 border-collapse text-sm">
        <thead>
          <tr class="bg-slate-50 h-9 text-left font-bold">
            <th class="px-3 border border-slate-200 w-28">Mã nhân viên</th>
            <th class="px-3 border border-slate-200">Tên nhân viên</th>
            <th class="px-3 border border-slate-200">Email</th>
            <th class="px-3 border border-slate-200">Vị trí công việc</th>
            <th class="w-20 border border-slate-200"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in exemptUsers" :key="user.id" class="h-9 even:bg-slate-50">
            <td class="px-3 border border-slate-200">{{ user.employee_code }}</td>
            <td class="px-3 border border-slate-200">{{ user.name }}</td>
            <td class="px-3 border border-slate-200">{{ user.email }}</td>
            <td class="px-3 border border-slate-200">{{ user.job_title }}</td>
            <td class="px-3 border border-slate-200 text-center text-xs">
              <button type="button" class="text-red-500 underline bg-transparent border-none cursor-pointer" @click="removeUser(user)">Xóa</button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Modal: Thêm / sửa địa chỉ IP -->
    <Teleport to="body">
      <div v-if="showIpModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/30" @mousedown.self="showIpModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-[min(520px,94vw)] overflow-hidden"
          :style="{ transform: `translate(${ipModal.position.value.x}px, ${ipModal.position.value.y}px)` }">
          <div class="flex items-center justify-between px-4 h-10 text-white text-sm font-bold select-none cursor-move" :style="{ background: themeBg }" @mousedown="ipModal.startDrag">
            <span>{{ ipForm.id ? 'Sửa địa chỉ IP' : 'Thêm địa chỉ IP' }}</span>
            <button type="button" class="border-none bg-transparent text-white cursor-pointer text-xl leading-none" @click="showIpModal = false">&times;</button>
          </div>
          <div class="p-5 flex flex-col gap-3 text-sm">
            <label class="flex flex-col gap-1">Tên địa chỉ
              <input v-model="ipForm.name" maxlength="100" class="h-9 border border-slate-300 rounded px-2 bg-yellow-50" @keyup.enter="saveIp" />
            </label>
            <label class="flex flex-col gap-1">IP
              <input v-model="ipForm.ip" maxlength="64" placeholder="113.161.185.53 hoặc 192.168.1.0/24" class="h-9 border border-slate-300 rounded px-2 bg-yellow-50" @keyup.enter="saveIp" />
            </label>
            <p v-if="ipError" class="text-xs text-red-600">{{ ipError }}</p>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 px-5 rounded-lg bg-slate-100 border-none cursor-pointer font-semibold" @click="showIpModal = false">Không</button>
              <button type="button" class="h-9 px-6 rounded-lg text-white border-none cursor-pointer font-semibold hover:opacity-90" :style="{ background: themeBg }" @click="saveIp">Có</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Thêm / sửa khoảng thời gian -->
    <Teleport to="body">
      <div v-if="showTimeModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/30" @mousedown.self="showTimeModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-[min(720px,94vw)] overflow-hidden"
          :style="{ transform: `translate(${timeModal.position.value.x}px, ${timeModal.position.value.y}px)` }">
          <div class="flex items-center justify-between px-4 h-10 text-white text-sm font-bold select-none cursor-move" :style="{ background: themeBg }" @mousedown="timeModal.startDrag">
            <span>{{ timeForm.id ? 'Sửa thời gian' : 'Thêm thời gian' }}</span>
            <button type="button" class="border-none bg-transparent text-white cursor-pointer text-xl leading-none" @click="showTimeModal = false">&times;</button>
          </div>
          <div class="p-5 flex flex-col gap-3 text-sm">
            <h4 class="font-bold">Các ngày trong tuần</h4>
            <label class="flex items-center gap-2 cursor-pointer w-fit"><input v-model="allDaysChecked" type="checkbox" class="w-4 h-4" /> Tất cả các ngày trong tuần</label>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
              <label v-for="day in DAYS" :key="day.value" class="flex items-center gap-2 cursor-pointer">
                <input v-model="timeForm.days" type="checkbox" :value="day.value" class="w-4 h-4" /> {{ day.label }}
              </label>
            </div>
            <h4 class="font-bold mt-2">Khoảng thời gian</h4>
            <div class="flex items-center gap-3">
              <div class="w-32"><TimePicker24h v-model="timeForm.startTime" default-time="00:00" /></div>
              <span>~</span>
              <div class="w-32"><TimePicker24h v-model="timeForm.endTime" default-time="23:59" /></div>
            </div>
            <p v-if="timeError" class="text-xs text-red-600">{{ timeError }}</p>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" class="h-9 px-5 rounded-lg bg-slate-100 border-none cursor-pointer font-semibold" @click="showTimeModal = false">Cancel</button>
              <button type="button" class="h-9 px-6 rounded-lg text-white border-none cursor-pointer font-semibold hover:opacity-90" :style="{ background: themeBg }" @click="saveTimeWindow">Lưu</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal: Thêm người dùng -->
    <Teleport to="body">
      <div v-if="showUserModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/30" @mousedown.self="showUserModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-[min(1000px,94vw)] max-h-[88vh] flex flex-col overflow-hidden"
          :style="{ transform: `translate(${userModal.position.value.x}px, ${userModal.position.value.y}px)` }">
          <div class="flex items-center justify-between px-4 h-10 text-white text-sm font-bold select-none cursor-move shrink-0" :style="{ background: themeBg }" @mousedown="userModal.startDrag">
            <span>Thêm người dùng</span>
            <button type="button" class="border-none bg-transparent text-white cursor-pointer text-xl leading-none" @click="showUserModal = false">&times;</button>
          </div>
          <div class="p-5 flex flex-col gap-3 text-sm min-h-0">
            <div class="flex gap-2">
              <input v-model="userSearch" placeholder="Tìm theo mã, tên, email..." class="flex-1 h-9 border border-slate-300 rounded px-3" @keyup.enter="searchUsers" />
              <button type="button" class="h-9 px-5 rounded-lg text-white border-none cursor-pointer font-semibold hover:opacity-90" :style="{ background: themeBg }" @click="searchUsers">Tìm kiếm</button>
            </div>
            <div class="overflow-auto border border-slate-200 rounded min-h-[200px]">
              <table class="w-full border-collapse text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="h-9 text-left font-bold">
                    <th class="w-10 px-3 border border-slate-200"><input v-model="allSelected" type="checkbox" class="w-4 h-4" /></th>
                    <th class="px-3 border border-slate-200">Mã nhân viên</th>
                    <th class="px-3 border border-slate-200">Tên nhân viên</th>
                    <th class="px-3 border border-slate-200">Email</th>
                    <th class="px-3 border border-slate-200">Vị trí công việc</th>
                    <th class="px-3 border border-slate-200">Bộ phận</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!userLoading && userRows.length === 0"><td colspan="6" class="p-5 text-center text-slate-400 text-xs">Không có nhân viên.</td></tr>
                  <tr v-for="user in userRows" :key="user.id" class="h-9 even:bg-slate-50" :class="exemptIds.has(user.id) ? 'opacity-50' : ''">
                    <td class="px-3 border border-slate-200">
                      <input v-model="selectedUserIds" type="checkbox" :value="user.id" :disabled="exemptIds.has(user.id)" class="w-4 h-4" />
                    </td>
                    <td class="px-3 border border-slate-200">{{ user.employee_code }}</td>
                    <td class="px-3 border border-slate-200">{{ user.name }}</td>
                    <td class="px-3 border border-slate-200">{{ user.email }}</td>
                    <td class="px-3 border border-slate-200">{{ user.job_title }}</td>
                    <td class="px-3 border border-slate-200">{{ user.department }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-sm">Tổng cộng: <span class="text-orange-500 font-semibold">{{ userTotal }}</span></p>
            <div class="flex justify-end gap-2">
              <button type="button" class="h-9 px-5 rounded-lg bg-slate-100 border-none cursor-pointer font-semibold" @click="showUserModal = false">Cancel</button>
              <button type="button" class="h-9 px-6 rounded-lg text-white border-none cursor-pointer font-semibold hover:opacity-90" :style="{ background: themeBg }" @click="saveExemptUsers">Lưu</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
