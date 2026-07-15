<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[99998] flex items-center justify-center p-4 backdrop-blur-xs select-none"
    @click.self="close"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-[680px] overflow-hidden border border-gray-300 flex flex-col relative">
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0">
        <div class="flex items-center space-x-2 font-semibold text-sm uppercase tracking-wider">
          <i class="fa-solid fa-star text-amber-300"></i>
          <span>Yêu cầu đặc biệt - PHÒNG {{ room?.roomNumber || 'CHƯA GÁN' }}</span>
        </div>
        <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
          <i class="fa-solid fa-xmark text-red-400 text-lg"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-4 space-y-4 flex flex-col min-h-0">
        <!-- Search bar -->
        <div class="relative shrink-0">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <i class="fa-solid fa-magnifying-glass text-xs"></i>
          </span>
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Tìm kiếm yêu cầu đặc biệt..."
            class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition-all"
          />
        </div>

        <!-- Table Listing with 5-7 row scroll constraint (approx 280px max-height) -->
        <div class="border border-slate-200 rounded-lg overflow-hidden flex flex-col min-h-0">
          <div class="overflow-y-auto max-h-[280px]">
            <table class="w-full border-collapse text-left text-xs table-fixed">
              <colgroup>
                <col style="width: 40px;" />
                <col style="width: 120px;" />
                <col style="width: auto;" />
                <col style="width: 70px;" />
              </colgroup>
              <thead>
                <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold h-9 sticky top-0 z-10 select-none">
                  <th class="p-2 text-center border-r border-slate-200">
                    <input
                      type="checkbox"
                      :checked="isAllSelected"
                      @change="toggleSelectAll"
                      class="w-3.5 h-3.5 cursor-pointer rounded"
                    />
                  </th>
                  <th class="p-2 border-r border-slate-200">Mã</th>
                  <th class="p-2 border-r border-slate-200">Tên yêu cầu đặc biệt</th>
                  <th class="p-2 text-center">Xóa</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoading" class="h-32 text-center text-slate-400">
                  <td colspan="4">
                    <div class="flex flex-col items-center justify-center space-y-2">
                      <i class="fa-solid fa-circle-notch fa-spin text-sky-500 text-2xl"></i>
                      <span class="text-xs font-semibold">Đang tải danh mục yêu cầu đặc biệt...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="filteredCatalog.length === 0" class="h-20 text-center text-slate-400 italic text-xs">
                  <td colspan="4">Không tìm thấy yêu cầu đặc biệt nào.</td>
                </tr>
                <tr
                  v-else
                  v-for="item in filteredCatalog"
                  :key="item.id"
                  class="border-b border-slate-100 hover:bg-slate-50/70 h-10 transition-colors font-semibold"
                  :class="{ 'bg-sky-50/30': selectedIds.includes(item.id) }"
                >
                  <td class="p-2 text-center border-r border-slate-100">
                    <input
                      type="checkbox"
                      :value="item.id"
                      v-model="selectedIds"
                      class="w-3.5 h-3.5 cursor-pointer"
                    />
                  </td>
                  <td class="p-2 border-r border-slate-100 font-mono text-[10px] text-slate-500 select-all">{{ item.code }}</td>
                  <td class="p-2 border-r border-slate-100 text-slate-700 text-xs">{{ item.name }}</td>
                  <td class="p-2 text-center">
                    <button
                      type="button"
                      @click="deleteMasterRequest(item)"
                      :disabled="isDeletingMaster"
                      class="w-7 h-7 flex items-center justify-center bg-blue-500 hover:bg-blue-600 disabled:bg-slate-300 text-white rounded cursor-pointer transition border-none shadow-xs mx-auto"
                      title="Xóa khỏi hệ thống"
                    >
                      <i class="fa-solid fa-trash-can text-[10px]"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 shrink-0 flex items-center justify-between">
        <!-- Bottom Left: Tạo mới -->
        <div>
          <button
            @click="showCreateModal = true"
            class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 font-bold text-xs px-3.5 py-1.5 rounded-lg cursor-pointer transition flex items-center space-x-1.5"
          >
            <i class="fa-solid fa-square-plus text-sky-500"></i>
            <span>Tạo mới</span>
          </button>
        </div>

        <!-- Bottom Right: Đóng & Lưu -->
        <div class="flex items-center space-x-2">
          <button
            @click="close"
            class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition"
          >
            Đóng
          </button>
          <button
            @click="save"
            :disabled="isSaving || isLoading"
            class="bg-sky-500 hover:bg-sky-600 disabled:bg-slate-300 text-white font-bold text-xs px-5 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 border-none shadow-sm"
          >
            <i v-if="isSaving" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-floppy-disk"></i>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>

    <!-- NESTED CREATION POPUP MODAL (AddSpecialRequestModal) -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/60 z-[99999] flex items-center justify-center p-4"
      @click.self="cancelCreate"
    >
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-[340px] overflow-hidden border border-gray-300 flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <!-- HEADER -->
        <div class="bg-blue-500 text-white flex justify-between items-center px-4 py-2.5 shrink-0">
          <span class="font-bold text-xs uppercase tracking-wider">Thêm yêu cầu đặc biệt</span>
          <button class="hover:text-white bg-transparent border-none cursor-pointer flex items-center bg-transparent" @click="cancelCreate">
            <i class="fa-solid fa-xmark text-white text-lg"></i>
          </button>
        </div>

        <!-- BODY -->
        <div class="p-4 space-y-4 text-left">
          <!-- Code field -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Mã</label>
            <input
              type="text"
              v-model="newRequestForm.code"
              placeholder="Nhập mã (ví dụ: HM, HF)"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500 uppercase"
            />
          </div>

          <!-- Name field -->
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Yêu cầu đặc biệt</label>
            <input
              type="text"
              v-model="newRequestForm.name"
              placeholder="Nhập tên yêu cầu đặc biệt"
              class="w-full border border-slate-300 rounded px-2.5 py-1.5 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- FOOTER -->
        <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 shrink-0 flex items-center justify-end space-x-2">
          <button
            @click="cancelCreate"
            class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition bg-transparent"
          >
            Đóng
          </button>
          <button
            @click="submitCreate"
            :disabled="isCreatingMaster || !newRequestForm.name"
            class="bg-blue-500 hover:bg-blue-600 disabled:bg-slate-300 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition border-none flex items-center space-x-1.5"
          >
            <i v-if="isCreatingMaster" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-floppy-disk"></i>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
  fetchSpecialRequestsCatalog,
  createSpecialRequestMaster,
  deleteSpecialRequestMaster,
  syncBookingRoomSpecialRequests
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  room: Object
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()

const searchQuery = ref('')
const catalog = ref([])
const selectedIds = ref([])
const isLoading = ref(false)
const isSaving = ref(false)

// Master CRUD overlay state
const showCreateModal = ref(false)
const isCreatingMaster = ref(false)
const isDeletingMaster = ref(false)
const newRequestForm = ref({
  name: '',
  code: ''
})

// Load master catalog and currently assigned room special requests
async function loadData() {
  if (!props.room) return
  isLoading.value = true
  try {
    const catalogRes = await fetchSpecialRequestsCatalog()
    catalog.value = catalogRes.data?.data || []

    // Extract already assigned special requests
    if (props.room.specialRequests) {
      selectedIds.value = props.room.specialRequests.map(r => r.special_request_id || r.specialRequest?.id)
    } else {
      selectedIds.value = []
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải danh sách yêu cầu đặc biệt!', 'error')
  } finally {
    isLoading.value = false
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    loadData()
    showCreateModal.value = false
    searchQuery.value = ''
    clearCreateForm()
  }
})

const filteredCatalog = computed(() => {
  if (!searchQuery.value) return catalog.value
  const q = searchQuery.value.toLowerCase().trim()
  return catalog.value.filter(item =>
    item.name.toLowerCase().includes(q) ||
    item.code.toLowerCase().includes(q)
  )
})

// Checkbox helpers
const isAllSelected = computed(() => {
  if (filteredCatalog.value.length === 0) return false
  return filteredCatalog.value.every(item => selectedIds.value.includes(item.id))
})

function toggleSelectAll(event) {
  const checked = event.target.checked
  const filteredIds = filteredCatalog.value.map(item => item.id)
  if (checked) {
    filteredIds.forEach(id => {
      if (!selectedIds.value.includes(id)) {
        selectedIds.value.push(id)
      }
    })
  } else {
    selectedIds.value = selectedIds.value.filter(id => !filteredIds.includes(id))
  }
}

// Master CRUD operations
function clearCreateForm() {
  newRequestForm.value = { name: '', code: '' }
}

function cancelCreate() {
  showCreateModal.value = false
  clearCreateForm()
}

async function submitCreate() {
  if (!newRequestForm.value.name.trim()) return
  isCreatingMaster.value = true
  try {
    await createSpecialRequestMaster({
      name: newRequestForm.value.name.trim(),
      code: newRequestForm.value.code.trim().toUpperCase()
    })
    uiStore.showToast('Tạo mới yêu cầu đặc biệt thành công!', 'success')
    clearCreateForm()
    showCreateModal.value = false
    await loadData()
  } catch (err) {
    console.error(err)
    const errMsg = err.response?.data?.message || 'Không thể tạo mới yêu cầu đặc biệt!'
    uiStore.showToast(errMsg, 'error')
  } finally {
    isCreatingMaster.value = false
  }
}

async function deleteMasterRequest(item) {
  if (!confirm(`Bạn có chắc chắn muốn xóa yêu cầu "${item.name}" ra khỏi hệ thống? Tất cả phòng đang sử dụng yêu cầu này cũng sẽ bị gỡ.`)) return
  isDeletingMaster.value = true
  try {
    await deleteSpecialRequestMaster(item.id)
    uiStore.showToast('Đã xóa yêu cầu đặc biệt khỏi hệ thống!', 'success')
    selectedIds.value = selectedIds.value.filter(id => id !== item.id)
    await loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa yêu cầu đặc biệt này!', 'error')
  } finally {
    isDeletingMaster.value = false
  }
}

// Sync selections and Save
async function save() {
  if (!props.room || !props.room.bookingRoomId) {
    uiStore.showToast('Phòng chưa được thiết lập đặt phòng hợp lệ!', 'warning')
    return
  }
  isSaving.value = true
  try {
    const res = await syncBookingRoomSpecialRequests(props.room.bookingRoomId, {
      special_request_ids: selectedIds.value
    })
    const updatedSpecialRequests = res.data?.data || []
    emit('saved', updatedSpecialRequests)
    uiStore.showToast('Đã cập nhật yêu cầu đặc biệt thành công!', 'success')
    close()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi lưu yêu cầu đặc biệt!', 'error')
  } finally {
    isSaving.value = false
  }
}

function close() {
  emit('update:show', false)
}
</script>
