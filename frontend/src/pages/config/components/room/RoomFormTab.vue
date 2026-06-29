<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const roomForms = ref([])

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
})

onBeforeUnmount(() => {
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
  isEditRoomFormMode.value = false
  currentRoomFormId.value = null
  Object.assign(roomFormStateData, {
    name: '',
    max_adults: 0
  })
  isRoomFormModalOpen.value = true
}

const openEditRoomFormModal = (rf) => {
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
      <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Dạng phòng (Giường)</h3>
      <button @click="openAddRoomFormModal"
        class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+
        Thêm dạng</button>
    </div>
    <table class="w-full text-sm text-left border-collapse">
      <thead>
        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
          <th class="p-3">Dạng phòng</th>
          <th class="p-3">Người lớn</th>
          <th class="p-3 text-right">Hành động</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="rf in paginatedRoomForms" :key="rf.id" @click="openEditRoomFormModal(rf)"
          class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
          <td class="p-3 font-bold text-slate-800">{{ rf.name }}</td>
          <td class="p-3 font-bold text-slate-600">{{ rf.max_adults }}</td>
          <td class="p-3 text-right">
            <button @click.stop="deleteRoomForm(rf.id)"
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
      <button @click="formPage = Math.max(1, formPage - 1)" :disabled="formPage === 1"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &lt;
      </button>

      <button v-for="p in totalFormPages" :key="p" @click="formPage = p"
        class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
        :class="formPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
        {{ p }}
      </button>

      <button @click="formPage = Math.min(totalFormPages, formPage + 1)" :disabled="formPage === totalFormPages"
        class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold">
        &gt;
      </button>

      <select v-model="formPageSize" @change="formPage = 1"
        class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400">
        <option :value="10">10 / page</option>
        <option :value="25">25 / page</option>
        <option :value="50">50 / page</option>
        <option :value="100">100 / page</option>
      </select>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT ROOM FORM -->
    <div v-if="isRoomFormModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider text-white">
            {{ isEditRoomFormMode ? 'Chỉnh sửa dạng phòng' : 'Thêm dạng phòng' }}
          </h2>
          <button @click="isRoomFormModalOpen = false"
            class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-6 text-sm text-slate-700 font-bold">
          <div class="grid grid-cols-[100px_1fr] items-center gap-4">
            <span class="font-bold text-slate-600 text-xs uppercase">DẠNG PHÒNG*</span>
            <input type="text" v-model="roomFormStateData.name" placeholder="Nhập tên dạng phòng..."
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
          </div>

          <div class="grid grid-cols-[100px_1fr] items-center gap-4">
            <span class="font-bold text-slate-600 text-xs uppercase">Người lớn</span>
            <input type="number" v-model="roomFormStateData.max_adults" min="0"
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" />
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
          <button @click="isRoomFormModalOpen = false"
            class="px-5 py-2 bg-slate-200 hover:bg-slate-350 text-slate-600 rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors">
            Đóng
          </button>
          <button @click="saveRoomForm"
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
