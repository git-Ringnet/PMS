<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const roomForms = ref([])

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
  if (e.key === 'Escape' && isRoomFormModalOpen.value) {
    isRoomFormModalOpen.value = false
  }
}

const formPage = ref(1)
const formPageSize = ref(25)

const paginatedRoomForms = computed(() => {
  const start = (formPage.value - 1) * formPageSize.value
  return roomForms.value.slice(start, start + formPageSize.value)
})
const totalFormPages = computed(() => Math.ceil(roomForms.value.length / formPageSize.value) || 1)

// Room Form Modal State
const isRoomFormModalOpen = ref(false)
const isEditRoomFormMode = ref(false)
const currentRoomFormId = ref(null)
const roomFormStateData = reactive({
  name: '',
  max_adults: 0
})

let bc = null

onMounted(() => {
  fetchRoomForms()
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

const fetchRoomForms = async () => {
  loading.value = true
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dạng phòng:', err)
  } finally {
    loading.value = false
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
            if (key === 'name') {
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

const openAddRoomFormModal = () => {
  modalPos.value = { x: 0, y: 0 }
  isEditRoomFormMode.value = false
  currentRoomFormId.value = null
  Object.assign(roomFormStateData, {
    name: '',
    max_adults: 0
  })
  isRoomFormModalOpen.value = true
}

const openEditRoomFormModal = (rf) => {
  modalPos.value = { x: 0, y: 0 }
  isEditRoomFormMode.value = true
  currentRoomFormId.value = rf.id
  Object.assign(roomFormStateData, {
    name: rf.name,
    max_adults: rf.max_adults
  })
  isRoomFormModalOpen.value = true
}

const saveRoomForm = async () => {
  if (!roomFormStateData.name) {
    uiStore.showToast('Vui lòng nhập tên dạng phòng (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditRoomFormMode.value) {
      await http.put(`/room-forms/${currentRoomFormId.value}`, roomFormStateData)
      uiStore.showToast('Cập nhật dạng phòng thành công!', 'success')
    } else {
      await http.post('/room-forms', roomFormStateData)
      uiStore.showToast('Thêm dạng phòng thành công!', 'success')
    }
    isRoomFormModalOpen.value = false
    fetchRoomForms()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = getErrorMessage(err, 'Có lỗi xảy ra khi lưu dạng phòng')
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoomForm = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dạng phòng này? Tất cả các phòng thuộc dạng này cũng sẽ bị xóa.',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/room-forms/${id}`)
    uiStore.showToast('Xóa dạng phòng thành công!', 'success')
    fetchRoomForms()
    if (bc) {
      bc.postMessage('rooms-updated')
    }
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể xóa dạng phòng này'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4 relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <button @click="openAddRoomFormModal" class="btn-pms-primary">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span>Thêm Dạng Phòng</span>
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">Dạng Phòng</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-36">Người Lớn Tối Đa</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rf in paginatedRoomForms" :key="rf.id" @click="openEditRoomFormModal(rf)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="p-2.5 font-semibold text-slate-800">{{ rf.name }}</td>
            <td class="p-2.5 font-normal text-slate-700 text-center">{{ rf.max_adults }}</td>
            <td class="p-2.5 text-center">
              <button @click.stop="deleteRoomForm(rf.id)"
                class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                title="Xóa dạng phòng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
          <tr v-if="paginatedRoomForms.length === 0">
            <td colspan="3" class="p-6 text-center text-slate-400 italic">Không tìm thấy dạng phòng nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination controls -->
    <div class="flex items-center justify-end gap-2 mt-4 select-none">
      <button @click="formPage = Math.max(1, formPage - 1)" :disabled="formPage === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">
        &lt;
      </button>

      <button v-for="p in totalFormPages" :key="p" @click="formPage = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-xs"
        :class="formPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
        {{ p }}
      </button>

      <button @click="formPage = Math.min(totalFormPages, formPage + 1)" :disabled="formPage === totalFormPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold text-xs">
        &gt;
      </button>

      <select v-model="formPageSize" @change="formPage = 1"
        class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
        <option :value="10">10 / trang</option>
        <option :value="25">25 / trang</option>
        <option :value="50">50 / trang</option>
        <option :value="100">100 / trang</option>
      </select>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT ROOM FORM -->
    <div v-if="isRoomFormModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditRoomFormMode ? 'Chỉnh Sửa Dạng Phòng' : 'Thêm Dạng Phòng' }}
          </h2>
          <button @mousedown.stop @click="isRoomFormModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-[#000000D9]">
          <div class="flex flex-col gap-1">
            <label>Tên Dạng Phòng <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" v-model="roomFormStateData.name" placeholder="Nhập tên dạng phòng..."
                class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
              <button v-if="roomFormStateData.name" @click="roomFormStateData.name = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>

          <div class="flex flex-col gap-1">
            <label>Số Lượng Người Lớn Tối Đa</label>
            <input type="number" v-model.number="roomFormStateData.max_adults" min="0"
              class="w-full border border-slate-200 rounded-lg p-2 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 bg-white" />
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveRoomForm" class="btn-pms-primary">
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

