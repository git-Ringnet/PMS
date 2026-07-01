<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import AddOutletModal from '../modals/AddOutletModal.vue'
import { fetchOutlets, createOutlet, updateOutlet, deleteOutlet, reorderOutlets } from '@/services/outlet-service'

const uiStore = useUiStore()

const outlets = ref([])
const allowSort = ref(false)
const dragIndex = ref(null)
const isLoading = ref(false)

const isModalOpen = ref(false)
const selectedOutlet = ref(null)

const loadOutlets = async () => {
  isLoading.value = true
  try {
    const res = await fetchOutlets()
    outlets.value = res.data
    window.dispatchEvent(new CustomEvent('outlet-updated'))
  } catch (error) {
    uiStore.showToast('Lỗi khi tải danh sách outlets', 'error')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadOutlets()
})

const openAddModal = () => {
  selectedOutlet.value = null
  isModalOpen.value = true
}

const openEditModal = (item) => {
  selectedOutlet.value = item
  isModalOpen.value = true
}

const handleSave = async (formData) => {
  try {
    if (selectedOutlet.value) {
      const confirm = await uiStore.confirm({
        title: 'Xác nhận cập nhật',
        message: `Bạn có chắc chắn muốn lưu thay đổi cho outlet "${selectedOutlet.value.name}"?`
      })
      if (!confirm) return
      await updateOutlet(selectedOutlet.value.id, formData)
      uiStore.showToast('Cập nhật outlet thành công', 'success')
    } else {
      await createOutlet(formData)
      uiStore.showToast('Thêm outlet thành công', 'success')
    }
    isModalOpen.value = false
    await loadOutlets()
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Có lỗi xảy ra'
    uiStore.showToast(errorMsg, 'error')
  }
}

const handleDelete = async (id) => {
  const confirm = await uiStore.confirm({
    title: 'Xác nhận xóa outlet',
    message: 'Bạn có chắc chắn muốn xóa outlet này?'
  })
  if (!confirm) return
  try {
    await deleteOutlet(id)
    uiStore.showToast('Xóa outlet thành công', 'success')
    await loadOutlets()
  } catch (error) {
    uiStore.showToast('Lỗi khi xóa outlet', 'error')
  }
}

const toggleActive = async (item) => {
  try {
    await updateOutlet(item.id, {
      ...item,
      is_active: !item.is_active
    })
    uiStore.showToast('Cập nhật trạng thái thành công', 'success')
    await loadOutlets()
  } catch (error) {
    uiStore.showToast('Lỗi khi cập nhật trạng thái', 'error')
  }
}

const onDragStart = (index, event) => {
  dragIndex.value = index
  event.dataTransfer.effectAllowed = 'move'
}

const onDrop = async (index, event) => {
  if (dragIndex.value === null || dragIndex.value === index) return
  
  const list = [...outlets.value]
  const draggedItem = list.splice(dragIndex.value, 1)[0]
  list.splice(index, 0, draggedItem)
  
  outlets.value = list
  dragIndex.value = null
  
  const orders = outlets.value.map((item, idx) => ({
    id: item.id,
    order_index: idx
  }))
  
  try {
    await reorderOutlets(orders)
    uiStore.showToast('Cập nhật thứ tự thành công', 'success')
    await loadOutlets()
  } catch (error) {
    uiStore.showToast('Lỗi khi cập nhật thứ tự', 'error')
    await loadOutlets()
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm relative">
    <!-- Loading Overlay -->
    <div v-if="isLoading" class="absolute inset-0 bg-white/80 backdrop-blur-[2px] flex items-center justify-center z-50 transition-all duration-200">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- Actions -->
    <div class="flex items-center gap-4 mb-4">
      <button @click="openAddModal" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Thêm
      </button>
      <div class="flex items-center gap-2 bg-slate-200/50 px-3 py-1.5 rounded-full cursor-pointer hover:bg-slate-200 transition" @click="allowSort = !allowSort">
        <div class="relative w-8 h-4 bg-slate-300 rounded-full transition-colors duration-200" :class="{'bg-sky-450 bg-sky-400': allowSort}">
          <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-4': allowSort}"></div>
        </div>
        <span class="text-sm text-slate-600 font-medium select-none">{{ allowSort ? 'Cho phép sắp xếp' : 'Không cho phép sắp xếp' }}</span>
      </div>
    </div>

    <!-- Table -->
    <div class="border border-slate-200 rounded-lg overflow-hidden">
      <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-slate-700 font-medium border-b border-slate-200">
          <tr>
            <th class="py-3 px-4 flex items-center gap-1">
              Mã
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </th>
            <th class="py-3 px-4">
              <div class="flex items-center gap-1">
                Tên
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="py-3 px-4">
              <div class="flex items-center gap-1">
                Bộ phận
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="py-3 px-4">
              <div class="flex items-center gap-1">
                Dịch vụ
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
            </th>
            <th class="py-3 px-4 w-24">Kích hoạt</th>
            <th v-if="allowSort" class="py-3 px-4 w-24 text-center">Thứ tự</th>
            <th class="py-3 px-4 w-24 text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr 
            v-for="(item, index) in outlets" 
            :key="item.id" 
            class="hover:bg-slate-50/50 transition duration-150"
            :draggable="allowSort"
            @dragstart="onDragStart(index, $event)"
            @dragover.prevent
            @drop="onDrop(index, $event)"
            :class="{'bg-sky-50/10 cursor-move': allowSort}"
          >
            <td class="py-3 px-4 font-medium">{{ item.code }}</td>
            <td class="py-3 px-4">{{ item.name }}</td>
            <td class="py-3 px-4">{{ item.department ? item.department.name : (item.department_code || '') }}</td>
            <td class="py-3 px-4">{{ item.service ? item.service.name : (item.service_code || '') }}</td>
            <td class="py-3 px-4">
              <div class="relative w-10 h-5 bg-slate-300 rounded-full transition-colors duration-200 cursor-pointer" :class="{'bg-[#78C5E7]': item.is_active}" @click="toggleActive(item)">
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition-transform duration-200" :class="{'translate-x-5': item.is_active}"></div>
              </div>
            </td>
            <td v-if="allowSort" class="py-3 px-4 text-center select-none">
              <div class="flex items-center justify-center text-slate-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                </svg>
              </div>
            </td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button @click="openEditModal(item)" class="text-sky-500 hover:text-sky-700 transition" title="Sửa">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <button @click="handleDelete(item.id)" class="text-red-500 hover:text-red-700 transition" title="Xóa">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="outlets.length === 0">
            <td :colspan="allowSort ? 7 : 6" class="py-6 text-center text-slate-400">Không có dữ liệu outlet nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Modal -->
    <AddOutletModal
      :is-open="isModalOpen"
      :outlet="selectedOutlet"
      @close="isModalOpen = false"
      @save="handleSave"
    />
  </div>
</template>
