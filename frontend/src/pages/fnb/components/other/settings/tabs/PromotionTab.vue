<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'
import AddPromotionModal from '../modals/AddPromotionModal.vue'
import ConfirmReasonModal from '@/pages/fnb/components/restaurant/modals/ConfirmReasonModal.vue'
import { fetchOutlets } from '@/services/outlet-service'

const uiStore = useUiStore()
const isLoading = ref(false)

const promotions = ref([])
const outletsList = ref([])

const showAddModal = ref(false)
const selectedPromotion = ref(null)

const loadPromotions = async () => {
    try {
        isLoading.value = true
        const res = await http.get('/fb-promotions', { params: { per_page: 1000 } })
        promotions.value = res.data?.data || res.data || []
    } catch (e) {
        uiStore.showToast('Lỗi tải danh sách khuyến mãi', 'error')
    } finally {
        isLoading.value = false
    }
}

onMounted(async () => {
    isLoading.value = true
    try {
        const outRes = await fetchOutlets()
        outletsList.value = outRes.data?.data || outRes.data || []
    } catch (e) {
        console.error(e)
    }
    await loadPromotions()
    isLoading.value = false
})

const openAddModal = () => {
    selectedPromotion.value = null
    showAddModal.value = true
}

const editPromotion = (promo) => {
    selectedPromotion.value = JSON.parse(JSON.stringify(promo))
    showAddModal.value = true
}

const savePromotion = async (data) => {
    try {
        isLoading.value = true
        if (data.id) {
            await http.put(`/fb-promotions/${data.id}`, data)
            uiStore.showToast('Cập nhật khuyến mãi thành công', 'success')
        } else {
            await http.post('/fb-promotions', data)
            uiStore.showToast('Tạo khuyến mãi thành công', 'success')
        }
        showAddModal.value = false
        await loadPromotions()
    } catch (e) {
        uiStore.showToast('Lỗi lưu khuyến mãi', 'error')
    } finally {
        isLoading.value = false
    }
}

const showReasonModal = ref(false)
const cancelTargetData = ref(null)

const deletePromotion = (id, e) => {
  if (e) e.stopPropagation()
  cancelTargetData.value = id
  showReasonModal.value = true
}

const handleConfirmDelete = async (reason) => {
  showReasonModal.value = false
  try {
      isLoading.value = true
      await http.delete(`/fb-promotions/${cancelTargetData.value}`, { data: { reason } })
      uiStore.showToast('Xóa khuyến mãi thành công', 'success')
      await loadPromotions()
  } catch (e) {
      uiStore.showToast('Lỗi xóa khuyến mãi', 'error')
  } finally {
      isLoading.value = false
  }
}

const formatDate = (dateStr) => {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN')
}
</script>

<template>
  <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm flex flex-col overflow-hidden relative w-full">
    <!-- Loading overlay -->
    <div v-if="isLoading" class="absolute inset-0 z-50 flex items-center justify-center bg-white/50 backdrop-blur-xs">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>
    <!-- Toolbar -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center shrink-0">
      <button @click="openAddModal" class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded-md text-sm font-medium transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Thêm
      </button>
    </div>

    <!-- Table view -->
    <div class="flex-1 overflow-auto flex flex-col">
      <table class="w-full text-sm border-collapse whitespace-nowrap text-left text-slate-600">
        <thead class="sticky top-0 z-10 bg-slate-50">
          <tr class="border-b border-slate-200 text-xs font-semibold text-slate-700">
            <th class="px-6 py-3 w-1/4 border-r border-slate-100">Tên chương trình</th>
            <th class="px-6 py-3 w-1/4 text-center border-r border-slate-100">Ngày bắt đầu</th>
            <th class="px-6 py-3 w-1/4 text-center border-r border-slate-100">Ngày kết thúc</th>
            <th class="px-6 py-3 w-1/4 text-center">Trạng thái</th>
            <th class="px-6 py-3 w-16"></th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-for="item in promotions" :key="item.id" class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors cursor-pointer" @click="editPromotion(item)">
            <td class="px-6 py-3 font-medium text-slate-800 border-r border-slate-100">{{ item.name }}</td>
            <td class="px-6 py-3 text-center border-r border-slate-100">{{ formatDate(item.start_date) }}</td>
            <td class="px-6 py-3 text-center border-r border-slate-100">{{ formatDate(item.end_date) }}</td>
            <td class="px-6 py-3 text-center">
              <span v-if="item.is_active" class="px-2 py-1 bg-sky-50 text-sky-500 border border-sky-200 rounded text-xs font-medium">Đang áp dụng</span>
              <span v-else class="px-2 py-1 bg-slate-50 text-slate-500 border border-slate-200 rounded text-xs font-medium">Ngừng áp dụng</span>
            </td>
            <td class="px-6 py-3 text-center">
              <button @click.stop="deletePromotion(item.id)" class="text-white bg-red-400 hover:bg-red-500 p-1.5 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      
      <!-- Empty State -->
      <div v-if="promotions.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-slate-300">
        <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-100 mb-3 shadow-inner">
          <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
        </div>
        <span class="text-xs font-semibold uppercase tracking-wide">Trống</span>
      </div>
    </div>

    <!-- Modals -->
    <AddPromotionModal 
      :show="showAddModal"
      :promotion="selectedPromotion"
      :outletsList="outletsList"
      @close="showAddModal = false"
      @save="savePromotion"
    />
    <ConfirmReasonModal
      :is-open="showReasonModal"
      title="Xác nhận xóa"
      @close="showReasonModal = false"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>
