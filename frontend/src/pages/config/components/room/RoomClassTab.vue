<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null
let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
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

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isRoomClassModalOpen.value) {
    isRoomClassModalOpen.value = false
  }
}

const roomClasses = ref([])
const roomClassGroups = ref([])

const classPage = ref(1)
const classPageSize = ref(25)
const classSearchQuery = ref('')

watch(classSearchQuery, () => {
  classPage.value = 1
})

const filteredRoomClasses = computed(() => {
  if (!classSearchQuery.value) return roomClasses.value
  const query = classSearchQuery.value.trim().toLowerCase()
  return roomClasses.value.filter(rc =>
    rc.name.toLowerCase().includes(query) ||
    (rc.code || '').toLowerCase().includes(query) ||
    (rc.group || '').toLowerCase().includes(query)
  )
})

const paginatedRoomClasses = computed(() => {
  const start = (classPage.value - 1) * classPageSize.value
  return filteredRoomClasses.value.slice(start, start + classPageSize.value)
})
const totalClassPages = computed(() => Math.ceil(filteredRoomClasses.value.length / classPageSize.value) || 1)

// Room Class Modal State
const isRoomClassModalOpen = ref(false)
const isEditRoomClassMode = ref(false)
const currentRoomClassId = ref(null)
const roomClassImageInput = ref(null)
const roomClassFormState = reactive({
  name: '',
  code: '',
  color: '#ffffff',
  is_active: true,
  room_class_group_id: '',
  notes: '',
  image: null,
  imagePreview: null
})

let bc = null

onMounted(async () => {
  fetchRoomClassGroups()
  fetchRoomClasses()
  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
  }
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
  if (bc) {
    bc.close()
  }
})

const fetchRoomClassGroups = async () => {
  try {
    const res = await http.get('/room-class-groups')
    roomClassGroups.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải danh sách nhóm loại phòng:', err)
  }
}

const fetchRoomClasses = async () => {
  loading.value = true
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải tên loại phòng:', err)
  } finally {
    loading.value = false
  }
}

const openAddRoomClassModal = () => {
  modalPos.value = { x: 0, y: 0 }
  isEditRoomClassMode.value = false
  currentRoomClassId.value = null
  Object.assign(roomClassFormState, {
    name: '',
    code: '',
    color: '#ffffff',
    is_active: true,
    room_class_group_id: roomClassGroups.value[0]?.id || '',
    notes: '',
    image: null,
    imagePreview: null
  })
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
  isRoomClassModalOpen.value = true
}

const openEditRoomClassModal = (rc) => {
  modalPos.value = { x: 0, y: 0 }
  isEditRoomClassMode.value = true
  currentRoomClassId.value = rc.id
  Object.assign(roomClassFormState, {
    name: rc.name,
    code: rc.code,
    color: rc.color || '#ffffff',
    is_active: rc.is_active,
    room_class_group_id: rc.room_class_group_id || '',
    notes: rc.notes || '',
    image: null,
    imagePreview: rc.image_url || null
  })
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
  isRoomClassModalOpen.value = true
}

const triggerRoomClassImageUpload = () => {
  if (roomClassImageInput.value) {
    roomClassImageInput.value.click()
  }
}

const handleRoomClassImageUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    uiStore.showToast('Vui lòng chỉ chọn các định dạng file ảnh!', 'warning')
    if (roomClassImageInput.value) {
      roomClassImageInput.value.value = ''
    }
    return
  }

  roomClassFormState.image = file
  const reader = new FileReader()
  reader.onload = (event) => {
    roomClassFormState.imagePreview = event.target.result
  }
  reader.readAsDataURL(file)
}

const removeRoomClassImage = () => {
  roomClassFormState.image = null
  roomClassFormState.imagePreview = null
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
}

const getErrorMessage = (err, defaultMsg = 'Có lỗi xảy ra') => {
  if (err.response?.status === 422 && err.response?.data?.errors) {
    const errors = err.response.data.errors
    const messages = []

    for (const key in errors) {
      if (Array.isArray(errors[key])) {
        errors[key].forEach(msg => {
          let translated = msg
          if (msg.toLowerCase().includes('already been taken') || msg.toLowerCase().includes('đã tồn tại') || msg.toLowerCase().includes('đã được chọn')) {
            if (key === 'code') {
              translated = 'Mã đã tồn tại trong hệ thống'
            } else if (key === 'name') {
              translated = 'Tên đã tồn tại trong hệ thống'
            } else {
              translated = 'Dữ liệu này đã tồn tại trong hệ thống'
            }
          } else if (msg.toLowerCase().includes('field is required') || msg.toLowerCase().includes('bắt buộc')) {
            translated = 'Trường thông tin này là bắt buộc'
          }
          messages.push(translated)
        })
      }
    }
    if (messages.length > 0) {
      return messages.join(', ')
    }
  }
  return err.response?.data?.message || err.message || defaultMsg
}

const saveRoomClass = async () => {
  if (!roomClassFormState.name || !roomClassFormState.code) {
    uiStore.showToast('Vui lòng điền các trường bắt buộc (*)', 'warning')
    return
  }
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('name', roomClassFormState.name)
    formData.append('code', roomClassFormState.code)
    formData.append('color', roomClassFormState.color)
    formData.append('is_active', roomClassFormState.is_active ? '1' : '0')
    if (roomClassFormState.room_class_group_id) {
      formData.append('room_class_group_id', roomClassFormState.room_class_group_id)
    }
    formData.append('notes', roomClassFormState.notes)
    if (roomClassFormState.image) {
      formData.append('image', roomClassFormState.image)
    }

    if (isEditRoomClassMode.value) {
      formData.append('_method', 'PUT')
      await http.post(`/room-classes/${currentRoomClassId.value}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Cập nhật loại phòng thành công!', 'success')
    } else {
      await http.post('/room-classes', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Thêm loại phòng thành công!', 'success')
    }
    isRoomClassModalOpen.value = false
    fetchRoomClasses()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = getErrorMessage(err, 'Có lỗi xảy ra khi lưu loại phòng')
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoomClass = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa loại phòng này? Tất cả các phòng thuộc loại phòng này cũng sẽ bị xóa.',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/room-classes/${id}`)
    uiStore.showToast('Xóa loại phòng thành công!', 'success')
    fetchRoomClasses()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể xóa loại phòng này'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const toggleRoomClassActive = async (rc) => {
  try {
    const updatedStatus = !rc.is_active
    await http.put(`/room-classes/${rc.id}`, {
      name: rc.name,
      code: rc.code,
      color: rc.color,
      is_active: updatedStatus ? '1' : '0',
      group: rc.group
    })
    rc.is_active = updatedStatus
    uiStore.showToast('Cập nhật trạng thái sử dụng thành công!', 'success')
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể cập nhật trạng thái sử dụng'
    uiStore.showToast(errorMsg, 'error')
    fetchRoomClasses()
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 relative h-full">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-2 flex-wrap gap-2 shrink-0">
      <div class="flex items-center gap-2">
        <button @click="openAddRoomClassModal" class="btn-pms-primary">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>Thêm Loại Phòng</span>
        </button>
      </div>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="classSearchQuery" placeholder="Tìm tên, mã loại phòng..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-8 h-8 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <button v-if="classSearchQuery" @click="classSearchQuery = ''"
          class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto border border-slate-200 rounded-xl bg-white shadow-2xs flex-1 min-h-0">
      <table class="w-full text-xs text-left border-collapse">
        <thead class="sticky top-0 bg-slate-50 z-10">
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 min-w-[180px]">Tên Loại Phòng</th>
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 w-28">Tên Viết Tắt</th>
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 w-28">Màu Sắc</th>
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 w-28">Có Sử Dụng</th>
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 min-w-[160px]">Nhóm Loại Phòng</th>
            <th class="px-2.5 py-1.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rc in paginatedRoomClasses" :key="rc.id" @click="openEditRoomClassModal(rc)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="px-2.5 py-1.5 font-semibold text-slate-800">{{ rc.name }}</td>
            <td class="px-2.5 py-1.5 font-normal text-slate-600 text-center">{{ rc.code }}</td>
            <td class="px-2.5 py-1.5 text-center">
              <div class="flex items-center justify-center gap-2">
                <span class="w-4 h-4 rounded-full border border-slate-200 block shadow-2xs"
                  :style="{ backgroundColor: rc.color }"></span>
                <span class="font-mono text-slate-500 text-xs">{{ rc.color }}</span>
              </div>
            </td>
            <td class="px-2.5 py-1.5 text-center">
              <label @click.stop class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" :checked="rc.is_active" @change="toggleRoomClassActive(rc)"
                  class="sr-only peer" />
                <div
                  class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
                </div>
              </label>
            </td>
            <td class="px-2.5 py-1.5 text-slate-600 font-normal">{{ rc.group || '-' }}</td>
            <td class="px-2.5 py-1.5 text-center">
              <button @click.stop="deleteRoomClass(rc.id)"
                class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                title="Xóa loại phòng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="paginatedRoomClasses.length === 0">
            <td colspan="6" class="p-6 text-center text-slate-400 italic">Không tìm thấy loại phòng nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center justify-end gap-2 mt-2 select-none shrink-0">
      <button @click="classPage = Math.max(1, classPage - 1)" :disabled="classPage === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">
        &lt;
      </button>

      <button v-for="p in totalClassPages" :key="p" @click="classPage = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
        :class="classPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
        {{ p }}
      </button>

      <button @click="classPage = Math.min(totalClassPages, classPage + 1)"
        :disabled="classPage === totalClassPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">
        &gt;
      </button>

      <select v-model="classPageSize" @change="classPage = 1"
        class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
        <option :value="10">10 / trang</option>
        <option :value="25">25 / trang</option>
        <option :value="50">50 / trang</option>
        <option :value="100">100 / trang</option>
      </select>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT ROOM CLASS -->
    <div v-if="isRoomClassModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-2 sm:p-4">
      <div
        class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 max-h-[calc(100vh-1rem)] sm:max-h-[calc(100vh-2rem)] flex flex-col"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header -->
        <div class="px-5 py-2.5 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb] shrink-0"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditRoomClassMode ? 'Chỉnh Sửa Loại Phòng' : 'Thêm Loại Phòng' }}
          </h2>
          <button @mousedown.stop @click="isRoomClassModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-4 sm:p-4.5 flex flex-col gap-2.5 overflow-y-auto max-h-[88vh] text-xs font-semibold text-[#000000D9]">
          <div class="flex flex-col gap-1">
            <label>Tên Loại Phòng <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" v-model="roomClassFormState.name" placeholder="Nhập tên loại phòng..."
                class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
              <button v-if="roomClassFormState.name" @click="roomClassFormState.name = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label>Tên Viết Tắt <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" v-model="roomClassFormState.code" placeholder="Nhập tên viết tắt..."
                class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
              <button v-if="roomClassFormState.code" @click="roomClassFormState.code = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <div class="flex items-center justify-between py-0.5 select-none">
            <span>Có Sử Dụng</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="roomClassFormState.is_active" class="sr-only peer" />
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>

          <div class="flex flex-col gap-1">
            <label>Nhóm Loại Phòng</label>
            <select v-model="roomClassFormState.room_class_group_id"
              class="border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500">
              <option value="" disabled>Chọn nhóm loại phòng</option>
              <option v-for="g in roomClassGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label>Màu Sắc</label>
            <div class="flex gap-2 items-center">
              <div class="relative flex-1">
                <input type="text" v-model="roomClassFormState.color" placeholder="#ffffff"
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 pr-7 font-mono text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 bg-white" />
                <button v-if="roomClassFormState.color" @click="roomClassFormState.color = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
              </div>
              <input type="color" v-model="roomClassFormState.color"
                class="w-8 h-8 p-0 border border-slate-200 cursor-pointer rounded bg-transparent" />
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label>Ghi Chú</label>
            <div class="relative">
              <textarea v-model="roomClassFormState.notes" rows="2" placeholder="Nhập ghi chú..."
                class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 resize-none bg-white"></textarea>
              <button v-if="roomClassFormState.notes" @click="roomClassFormState.notes = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <!-- Hidden input for file upload -->
          <input type="file" ref="roomClassImageInput" @change="handleRoomClassImageUpload" accept="image/*"
            class="hidden" />

          <!-- Upload area -->
          <div @click="triggerRoomClassImageUpload"
            class="border-2 border-dashed border-slate-200 rounded-lg p-2.5 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition-colors min-h-[64px]">
            <div v-if="!roomClassFormState.imagePreview"
              class="flex flex-col items-center justify-center text-slate-400 gap-1">
              <span class="text-xs font-semibold">+ Tải ảnh đại diện</span>
            </div>
            <div v-else class="relative w-full h-full flex items-center justify-center">
              <img :src="roomClassFormState.imagePreview" class="max-h-20 rounded object-contain" />
              <button type="button" @click.stop="removeRoomClassImage"
                class="absolute -top-2 -right-2 bg-red-500 text-white w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shadow hover:bg-red-600 cursor-pointer border-none">
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-2.5 flex items-center justify-end border-t border-slate-200 shrink-0">
          <button @click="saveRoomClass" class="btn-pms-primary">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
