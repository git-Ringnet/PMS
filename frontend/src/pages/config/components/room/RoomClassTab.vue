<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)

const roomClasses = ref([])
const roomClassGroups = ref([])

const classPage = ref(1)
const classPageSize = ref(25)

const paginatedRoomClasses = computed(() => {
  const start = (classPage.value - 1) * classPageSize.value
  return roomClasses.value.slice(start, start + classPageSize.value)
})
const totalClassPages = computed(() => Math.ceil(roomClasses.value.length / classPageSize.value) || 1)

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
})

onBeforeUnmount(() => {
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
  <div class="overflow-x-auto relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-4">
      <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Tên loại phòng</h3>
      <button @click="openAddRoomClassModal"
        class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+
        Thêm loại</button>
    </div>
    <table class="w-full text-sm text-left border-collapse">
      <thead>
        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
          <th class="p-3">Tên loại phòng</th>
          <th class="p-3">Tên viết tắt</th>
          <th class="p-3">Màu sắc</th>
          <th class="p-3">Có sử dụng</th>
          <th class="p-3">Nhóm loại phòng</th>
          <th class="p-3 text-right">Hành động</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="rc in paginatedRoomClasses" :key="rc.id" @click="openEditRoomClassModal(rc)"
          class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
          <td class="p-3 font-bold text-slate-800">{{ rc.name }}</td>
          <td class="p-3 font-semibold text-slate-500">{{ rc.code }}</td>
          <td class="p-3">
            <div class="flex items-center gap-2">
              <span class="w-4 h-4 rounded-full border border-slate-200 block shadow-xs"
                :style="{ backgroundColor: rc.color }"></span>
              <span class="font-mono text-slate-400">{{ rc.color }}</span>
            </div>
          </td>
          <td class="p-3">
            <label @click.stop class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" :checked="rc.is_active" @change="toggleRoomClassActive(rc)"
                class="sr-only peer" />
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </td>
          <td class="p-3 text-slate-500 font-semibold uppercase text-xs">{{ rc.group }}</td>
          <td class="p-3 text-right">
            <button @click.stop="deleteRoomClass(rc.id)"
              class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" />
              </svg>
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination controls -->
    <div class="flex items-center justify-end gap-2 mt-4 select-none">
      <button @click="classPage = Math.max(1, classPage - 1)" :disabled="classPage === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &lt;
      </button>

      <button v-for="p in totalClassPages" :key="p" @click="classPage = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="classPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
        {{ p }}
      </button>

      <button @click="classPage = Math.min(totalClassPages, classPage + 1)"
        :disabled="classPage === totalClassPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &gt;
      </button>

      <select v-model="classPageSize" @change="classPage = 1"
        class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
        <option :value="10">10 / page</option>
        <option :value="25">25 / page</option>
        <option :value="50">50 / page</option>
        <option :value="100">100 / page</option>
      </select>
    </div>

    <!-- OVERLAY MODAL: ADD ROOM CLASS -->
    <div v-if="isRoomClassModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider text-white">{{ isEditRoomClassMode ? 'Chỉnh sửa loại phòng' : 'Thêm loại phòng' }}</h2>
          <button @click="isRoomClassModalOpen = false"
            class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 overflow-y-auto max-h-[75vh] text-sm text-slate-700 font-bold">
          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 uppercase text-xs">TÊN LOẠI PHÒNG*</span>
            <input type="text" v-model="roomClassFormState.name" placeholder="Nhập tên loại phòng..."
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 uppercase text-xs">Tên viết tắt*</span>
            <input type="text" v-model="roomClassFormState.code" placeholder="Nhập tên viết tắt..."
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
          </div>

          <div class="flex items-center gap-4 py-1.5 select-none">
            <span class="font-bold text-slate-600 uppercase text-xs">Có sử dụng</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="roomClassFormState.is_active" class="sr-only peer" />
              <div
                class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-400">
              </div>
            </label>
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 uppercase text-xs">Nhóm loại phòng</span>
            <select v-model="roomClassFormState.room_class_group_id"
              class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
              <option value="" disabled>Chọn nhóm loại phòng</option>
              <option v-for="g in roomClassGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 uppercase text-xs">Màu sắc</span>
            <div class="flex gap-2 items-center">
              <input type="text" v-model="roomClassFormState.color" placeholder="#ffffff"
                class="flex-1 border border-slate-200 rounded-lg p-2.5 font-mono focus:outline-sky-400 focus:bg-white text-sm" />
              <input type="color" v-model="roomClassFormState.color"
                class="w-10 h-10 p-0 border-none cursor-pointer rounded bg-transparent" />
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 uppercase text-xs">Ghi chú</span>
            <textarea v-model="roomClassFormState.notes" rows="2" placeholder="Nhập ghi chú..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-400 resize-none font-semibold text-sm"></textarea>
          </div>

          <!-- Hidden input for file upload -->
          <input type="file" ref="roomClassImageInput" @change="handleRoomClassImageUpload" accept="image/*"
            class="hidden" />

          <!-- Upload area -->
          <div @click="triggerRoomClassImageUpload"
            class="border-2 border-dashed border-slate-300 rounded-lg p-6 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition-colors"
            style="min-height: 120px;">
            <div v-if="!roomClassFormState.imagePreview"
              class="flex flex-col items-center justify-center text-slate-400 gap-1.5">
              <span class="text-xl font-bold">+ Upload</span>
            </div>
            <div v-else class="relative w-full h-full flex items-center justify-center">
              <img :src="roomClassFormState.imagePreview" class="max-h-28 rounded object-contain" />
              <button type="button" @click.stop="removeRoomClassImage"
                class="absolute -top-2 -right-2 bg-red-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shadow hover:bg-red-600 cursor-pointer border-none">
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
          <button @click="isRoomClassModalOpen = false"
            class="px-5 py-2 bg-slate-200 hover:bg-slate-350 text-slate-600 rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors">
            Đóng
          </button>
          <button @click="saveRoomClass"
            class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs">
            Lưu
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
