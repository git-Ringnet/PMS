<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const shifts = ref([])

const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

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

const isEditMode = ref(false)
const isShiftModalOpen = ref(false)
const shiftFormState = reactive({
  id: null,
  name: '',
  start_time: '',
  end_time: ''
})

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isShiftModalOpen.value) {
    isShiftModalOpen.value = false
  }
}

const fetchShifts = async () => {
  loading.value = true
  try {
    const res = await http.get('/shifts')
    if (res.data && res.data.data) {
      shifts.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách ca làm việc:', err)
  } finally {
    loading.value = false
  }
}

const openAddShiftModal = () => {
  modalPos.value = { x: 0, y: 0 }
  isEditMode.value = false
  Object.assign(shiftFormState, {
    id: null,
    name: '',
    start_time: '',
    end_time: ''
  })
  isShiftModalOpen.value = true
}

const openEditShiftModal = (shift) => {
  modalPos.value = { x: 0, y: 0 }
  isEditMode.value = true
  const formatTime = (timeStr) => {
    if (!timeStr) return ''
    const parts = timeStr.split(':')
    if (parts.length >= 2) {
      return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`
    }
    return timeStr
  }
  Object.assign(shiftFormState, {
    id: shift.id,
    name: shift.name,
    start_time: formatTime(shift.start_time),
    end_time: formatTime(shift.end_time)
  })
  isShiftModalOpen.value = true
}

const saveShift = async () => {
  if (!shiftFormState.name.trim() || !shiftFormState.start_time || !shiftFormState.end_time) {
    uiStore.showToast('Vui lòng nhập đầy đủ thông tin ca làm việc', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/shifts/${shiftFormState.id}`, shiftFormState)
      uiStore.showToast('Cập nhật ca làm việc thành công!', 'success')
    } else {
      await http.post('/shifts', shiftFormState)
      uiStore.showToast('Thêm ca làm việc mới thành công!', 'success')
    }
    isShiftModalOpen.value = false
    fetchShifts()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu ca làm việc'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteShift = async (shiftId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa ca làm việc này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/shifts/${shiftId}`)
    uiStore.showToast('Xóa ca làm việc thành công!', 'success')
    fetchShifts()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa ca làm việc này', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchShifts()
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})
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
      <button @click="openAddShiftModal" class="btn-pms-primary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        <span>Thêm Ca Làm Việc</span>
      </button>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-xs text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
            <th class="p-2.5 text-center align-middle border border-slate-200">Tên Ca Làm Việc</th>
            <th class="p-2.5 text-center align-middle border border-slate-200">Thời Gian Bắt Đầu</th>
            <th class="p-2.5 text-center align-middle border border-slate-200">Thời Gian Kết Thúc</th>
            <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Hành Động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sh in shifts" :key="sh.id" @click="openEditShiftModal(sh)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer text-[#000000D9]">
            <td class="p-2.5 font-semibold text-slate-800 text-center">{{ sh.name }}</td>
            <td class="p-2.5 font-normal text-slate-700 text-center font-mono">{{ sh.start_time }}</td>
            <td class="p-2.5 font-normal text-slate-700 text-center font-mono">{{ sh.end_time }}</td>
            <td class="p-2.5 text-center">
              <div class="flex items-center justify-center">
                <button @click.stop="deleteShift(sh.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer transition-colors"
                  title="Xóa">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="shifts.length === 0">
            <td colspan="4" class="p-6 text-center text-slate-400 italic">Chưa cấu hình ca làm việc nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SHIFT -->
    <div v-if="isShiftModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none p-4">
      <div
        class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <div class="px-5 py-3 flex items-center justify-between text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <h2 class="text-xs font-semibold tracking-wide text-white">
            {{ isEditMode ? 'Chỉnh Sửa Ca Làm Việc' : 'Thêm Ca Làm Việc' }}
          </h2>
          <button @mousedown.stop @click="isShiftModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-[#000000D9]">
          <div class="flex flex-col gap-1">
            <label>Tên Ca Làm Việc <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" v-model="shiftFormState.name" placeholder="Ví dụ: 0, 1, 2, Hành chính"
                class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] placeholder:text-[#A8B0BF] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
              <button v-if="shiftFormState.name" @click="shiftFormState.name = ''"
                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
              <label>Thời Gian Bắt Đầu <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="time" v-model="shiftFormState.start_time"
                  class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                <button v-if="shiftFormState.start_time" @click="shiftFormState.start_time = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
              </div>
            </div>
            <div class="flex flex-col gap-1">
              <label>Thời Gian Kết Thúc <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="time" v-model="shiftFormState.end_time"
                  class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg p-2 pr-7 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
                <button v-if="shiftFormState.end_time" @click="shiftFormState.end_time = ''"
                  class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs">✕</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer: Bỏ nút Đóng, chỉ giữ nút Lưu ca -->
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end border-t border-slate-200">
          <button @click="saveShift" class="btn-pms-primary">
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
