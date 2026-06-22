<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const shifts = ref([])

const isEditMode = ref(false)
const isShiftModalOpen = ref(false)
const shiftFormState = reactive({
  id: null,
  name: '',
  start_time: '',
  end_time: ''
})

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
  if (shiftFormState.name === '' || !shiftFormState.start_time || !shiftFormState.end_time) {
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

    <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
      <button @click="openAddShiftModal"
        class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Thêm ca làm việc
      </button>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th class="p-3">Tên ca làm việc</th>
            <th class="p-3">Thời gian bắt đầu</th>
            <th class="p-3">Thời gian kết thúc</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sh in shifts" :key="sh.id" @click="openEditShiftModal(sh)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ sh.name }}</td>
            <td class="p-3 font-bold text-slate-600 font-mono">{{ sh.start_time }}</td>
            <td class="p-3 font-bold text-slate-600 font-mono">{{ sh.end_time }}</td>
            <td class="p-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click.stop="deleteShift(sh.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa ca' : 'Thêm ca' }}</h2>
          <button @click="isShiftModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên ca làm việc*</span>
            <input type="text" v-model="shiftFormState.name" placeholder="Ví dụ: 0, 1, 2, Hành chính"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Thời gian bắt đầu*</span>
              <input type="time" v-model="shiftFormState.start_time"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Thời gian kết thúc*</span>
              <input type="time" v-model="shiftFormState.end_time"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isShiftModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveShift"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu ca
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
