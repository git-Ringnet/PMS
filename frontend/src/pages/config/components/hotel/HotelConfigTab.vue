<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const hotelConfigs = ref([])
const searchConfigQuery = ref('')

const isEditMode = ref(false)
const isConfigModalOpen = ref(false)
const configFormState = reactive({
  id: null,
  name: '',
  value: '',
  description: ''
})

const fetchHotelConfigs = async () => {
  loading.value = true
  try {
    const res = await http.get('/hotel-configs')
    if (res.data && res.data.data) {
      hotelConfigs.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  } finally {
    loading.value = false
  }
}

const openAddConfigModal = () => {
  isEditMode.value = false
  Object.assign(configFormState, {
    id: null,
    name: '',
    value: '',
    description: ''
  })
  isConfigModalOpen.value = true
}

const openEditConfigModal = (config) => {
  isEditMode.value = true
  Object.assign(configFormState, {
    id: config.id,
    name: config.name,
    value: config.value,
    description: config.description
  })
  isConfigModalOpen.value = true
}

const saveConfig = async () => {
  if (!configFormState.name) {
    uiStore.showToast('Vui lòng nhập tên cấu hình', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/hotel-configs/${configFormState.id}`, configFormState)
      uiStore.showToast('Cập nhật cấu hình thành công!', 'success')
    } else {
      await http.post('/hotel-configs', configFormState)
      uiStore.showToast('Thêm cấu hình mới thành công!', 'success')
    }
    isConfigModalOpen.value = false
    fetchHotelConfigs()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu cấu hình'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteConfig = async (configId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa cấu hình này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  loading.value = true
  try {
    await http.delete(`/hotel-configs/${configId}`)
    uiStore.showToast('Xóa cấu hình thành công!', 'success')
    fetchHotelConfigs()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa cấu hình này', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchHotelConfigs()
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
      <button @click="openAddConfigModal"
        class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1.5 border-none cursor-pointer shadow-xs transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Thêm cấu hình
      </button>
      <div class="relative max-w-xs w-full">
        <input type="text" v-model="searchConfigQuery" placeholder="Tìm kiếm cấu hình..."
          class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm font-semibold focus:outline-sky-500 focus:bg-white" />
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
            <th class="p-3">Tên cấu hình</th>
            <th class="p-3">Giá trị</th>
            <th class="p-3">Mô tả</th>
            <th class="p-3 text-right">Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="cfg in hotelConfigs.filter(item => !searchConfigQuery || (item.name && item.name.toLowerCase().includes(searchConfigQuery.toLowerCase())) || (item.description && item.description.toLowerCase().includes(searchConfigQuery.toLowerCase())))"
            :key="cfg.id" @click="openEditConfigModal(cfg)"
            class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer">
            <td class="p-3 font-bold text-slate-800">{{ cfg.name }}</td>
            <td class="p-3 font-bold text-sky-700 font-mono">{{ cfg.value || '-' }}</td>
            <td class="p-3 text-slate-500 font-semibold text-xs leading-relaxed max-w-xs">{{ cfg.description || '-' }}</td>
            <td class="p-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click.stop="deleteConfig(cfg.id)"
                  class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="hotelConfigs.length === 0">
            <td colspan="4" class="p-6 text-center text-slate-400 italic">Chưa cấu hình thông số nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT CONFIG -->
    <div v-if="isConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa cấu hình' : 'Thêm cấu hình' }}</h2>
          <button @click="isConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="flex flex-col gap-1.5">
            <span>Tên cấu hình (Key)*</span>
            <input type="text" v-model="configFormState.name" :disabled="isEditMode"
              placeholder="AllowChangeRoomStatus..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm disabled:bg-slate-100 disabled:cursor-not-allowed" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Giá trị</span>
            <input type="text" v-model="configFormState.value" placeholder="1 hoặc 0 hoặc bỏ trống"
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Mô tả</span>
            <textarea v-model="configFormState.description" rows="3" placeholder="Chi tiết mô tả chức năng..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none"></textarea>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isConfigModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveConfig"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu cấu hình
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
