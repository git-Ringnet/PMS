<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs"
    @click.self="close"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-[750px] overflow-hidden border border-gray-300 flex flex-col max-h-[80vh]">
      <!-- MODAL HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0">
        <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
          <i class="fa-solid fa-trash-can text-red-300"></i>
          <span v-if="allRooms.length <= 1">Xóa dịch vụ bổ sung - PHÒNG {{ room?.roomNumber || 'CHƯA GÁN' }}</span>
          <span v-else>Xóa dịch vụ bổ sung - {{ allRooms.length }} PHÒNG ĐÃ CHỌN</span>
        </div>
        <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- ROOM TABS (when multiple rooms) -->
      <div v-if="allRooms.length > 1" class="flex border-b border-slate-200 bg-slate-50 overflow-x-auto shrink-0">
        <button
          v-for="r in allRooms"
          :key="r.bookingRoomId"
          @click="selectRoomTab(r)"
          class="px-3 py-1.5 text-[11px] font-semibold border-r border-slate-200 whitespace-nowrap transition"
          :class="activeRoomTab?.bookingRoomId === r.bookingRoomId
            ? 'bg-white text-sky-700 border-b-2 border-b-sky-500'
            : 'text-slate-500 hover:bg-slate-100'"
        >
          Phòng {{ r.roomNumber || r.bookingRoomId }}
          <span class="ml-1 text-[10px] text-slate-400">({{ roomServicesMap[r.bookingRoomId]?.length || 0 }})</span>
        </button>
        <button
          @click="activeRoomTab = null"
          class="px-3 py-1.5 text-[11px] font-semibold whitespace-nowrap transition"
          :class="activeRoomTab === null
            ? 'bg-white text-sky-700 border-b-2 border-b-sky-500'
            : 'text-slate-500 hover:bg-slate-100'"
        >
          Tất cả ({{ allServicesCount }})
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="flex-1 overflow-y-auto p-4">
        <!-- Loading state -->
        <div v-if="isLoading" class="flex items-center justify-center py-8">
          <i class="fa-solid fa-spinner fa-spin text-sky-500 text-xl mr-2"></i>
          <span class="text-slate-500 text-sm">Đang tải danh sách dịch vụ...</span>
        </div>

        <!-- Empty state -->
        <div v-else-if="displayedServices.length === 0" class="text-center py-8 text-slate-400 italic text-sm">
          <i class="fa-solid fa-circle-info mr-2"></i>
          {{ allRooms.length > 1 ? 'Các phòng đã chọn chưa có dịch vụ bổ sung nào.' : 'Phòng này chưa có dịch vụ bổ sung nào.' }}
        </div>

        <!-- Services list -->
        <div v-else>
          <div class="text-xs text-slate-500 mb-3 font-semibold">
            Chọn dịch vụ cần xóa ({{ selectedIds.length }}/{{ displayedServices.length }} đã chọn):
          </div>

          <!-- Select All -->
          <label class="flex items-center gap-2 p-2 mb-2 border border-slate-200 bg-slate-50 rounded-lg cursor-pointer hover:bg-slate-100 transition">
            <input
              type="checkbox"
              :checked="selectedIds.length === displayedServices.filter(s => isServiceDeletable(s)).length && displayedServices.filter(s => isServiceDeletable(s)).length > 0"
              :indeterminate="selectedIds.length > 0 && selectedIds.length < displayedServices.filter(s => isServiceDeletable(s)).length"
              :disabled="displayedServices.filter(s => isServiceDeletable(s)).length === 0"
              @change="toggleAll"
              class="w-3.5 h-3.5"
            />
            <span class="text-xs font-bold text-slate-700">Chọn tất cả dịch vụ</span>
          </label>

          <!-- Grouped by room then service_code -->
          <template v-for="roomGroup in groupedByRoom" :key="roomGroup.roomId">
            <!-- Room header (only when viewing all) -->
            <div v-if="allRooms.length > 1 && activeRoomTab === null" class="text-[10px] font-bold text-sky-700 uppercase mb-1 mt-3 px-1 tracking-wider">
              Phòng {{ roomGroup.roomNumber }}
            </div>

            <div v-for="group in roomGroup.serviceGroups" :key="roomGroup.roomId + '_' + group.code" class="mb-3 border border-slate-200 rounded-lg overflow-hidden">
              <!-- Group header -->
              <label class="flex items-center gap-2 px-3 py-2 bg-slate-100 border-b border-slate-200 cursor-pointer hover:bg-slate-200 transition">
                <input
                  type="checkbox"
                  :checked="isGroupAllSelected(group)"
                  :indeterminate="isGroupPartialSelected(group)"
                  :disabled="group.items.filter(item => isServiceDeletable(item)).length === 0"
                  @change="e => toggleGroup(group, e.target.checked)"
                  class="w-3.5 h-3.5"
                />
                <span class="text-xs font-bold text-slate-800">{{ group.name }}</span>
                <span class="text-[9px] text-slate-400 font-mono ml-1">{{ group.code }}</span>
                <span class="ml-auto text-[10px] text-slate-500 font-semibold">{{ group.items.length }} ngày</span>
              </label>

              <!-- Group items -->
              <div class="divide-y divide-slate-100">
                <label
                  v-for="svc in group.items"
                  :key="svc.id"
                  class="flex items-center gap-2 px-4 py-1.5 transition"
                  :class="isServiceDeletable(svc) ? 'cursor-pointer hover:bg-slate-50' : 'opacity-60 cursor-not-allowed bg-slate-50/30'"
                >
                  <input
                    type="checkbox"
                    :value="svc.id"
                    v-model="selectedIds"
                    :disabled="!isServiceDeletable(svc)"
                    class="w-3.5 h-3.5"
                  />
                  <span class="text-[11px] text-slate-600 font-mono flex-1">{{ formatDate(svc.service_date) }}</span>
                  <span class="text-[11px] text-slate-700 font-semibold">x{{ parseFloat(svc.quantity) }}</span>
                  <span class="text-[11px] text-sky-700 font-bold ml-2">{{ Number(svc.rate).toLocaleString('en-US') }} VND</span>
                  <span class="text-[10px] text-slate-400 ml-2">({{ Number(svc.quantity * svc.rate).toLocaleString('en-US') }})</span>
                  <span v-if="svc.is_posted === 1" class="ml-2 text-[9px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Đã post</span>
                  <span v-else-if="!isServiceDeletable(svc)" class="ml-2 text-[9px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200">Quá khứ</span>
                </label>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-2.5 shrink-0 flex items-center justify-between">
        <div class="text-xs text-slate-500">
          <span v-if="selectedIds.length > 0" class="text-red-600 font-bold">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
            Sẽ xóa {{ selectedIds.length }} dòng dịch vụ
          </span>
          <span v-else class="italic">Chưa chọn dịch vụ nào</span>
        </div>
        <div class="flex items-center space-x-2">
          <button
            @click="close"
            class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-100 font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition"
          >
            Hủy
          </button>
          <button
            @click="confirmDelete"
            :disabled="selectedIds.length === 0 || isDeleting"
            class="bg-red-500 hover:bg-red-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 border-none"
          >
            <i v-if="isDeleting" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-trash-can"></i>
            <span>{{ isDeleting ? 'Đang xóa...' : 'Xóa đã chọn' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import {
  fetchBookingRoomServices,
  deleteBookingRoomServicesBulk
} from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  room: Object,
  targetRooms: { type: Array, default: () => [] },
  systemDate: String
})

const emit = defineEmits(['update:show', 'deleted'])

const uiStore = useUiStore()

// All rooms to operate on (multi-room support)
const allRooms = computed(() => {
  if (props.targetRooms && props.targetRooms.length > 0) return props.targetRooms
  if (props.room) return [props.room]
  return []
})

// Map roomId -> services[]
const roomServicesMap = ref({})
const selectedIds = ref([])
const isLoading = ref(false)
const isDeleting = ref(false)
const activeRoomTab = ref(null) // null = show all

const allServicesCount = computed(() => {
  return Object.values(roomServicesMap.value).flat().length
})

const displayedServices = computed(() => {
  if (activeRoomTab.value === null) {
    return Object.values(roomServicesMap.value).flat()
  }
  return roomServicesMap.value[activeRoomTab.value.bookingRoomId] || []
})

// Group by room then service_code
const groupedByRoom = computed(() => {
  const roomsToShow = activeRoomTab.value === null ? allRooms.value : [activeRoomTab.value]
  return roomsToShow
    .filter(r => r.bookingRoomId)
    .map(r => {
      const services = roomServicesMap.value[r.bookingRoomId] || []
      const map = {}
      services.forEach(svc => {
        if (!map[svc.service_code]) {
          map[svc.service_code] = {
            code: svc.service_code,
            name: svc.service_name || svc.service_code,
            items: []
          }
        }
        map[svc.service_code].items.push(svc)
      })
      return {
        roomId: r.bookingRoomId,
        roomNumber: r.roomNumber || r.bookingRoomId,
        serviceGroups: Object.values(map)
      }
    })
    .filter(rg => rg.serviceGroups.length > 0)
})

watch(() => props.show, async (newVal) => {
  if (newVal && allRooms.value.length > 0) {
    isLoading.value = true
    selectedIds.value = []
    roomServicesMap.value = {}
    activeRoomTab.value = null

    try {
      for (const r of allRooms.value) {
        if (!r.bookingRoomId) continue
        const res = await fetchBookingRoomServices(r.bookingRoomId)
        const services = res.data?.data || []
        roomServicesMap.value[r.bookingRoomId] = services.filter(
          svc => !['EB', 'RM', 'BD', 'ROOM_CHARGE'].includes(svc.service_code)
        )
      }
    } catch (err) {
      console.error(err)
      uiStore.showToast('Lỗi khi tải danh sách dịch vụ!', 'error')
    } finally {
      isLoading.value = false
    }
  }
})

function selectRoomTab(r) {
  activeRoomTab.value = r
  // Deselect IDs not belonging to this room
  const roomSvcIds = (roomServicesMap.value[r.bookingRoomId] || []).map(s => s.id)
  selectedIds.value = selectedIds.value.filter(id => roomSvcIds.includes(id))
}

function close() {
  emit('update:show', false)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

function formatLocalYYYYMMDD(dVal) {
  if (!dVal) return ''
  const d = new Date(dVal)
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function isServiceDeletable(svc) {
  if (!svc) return false
  if (svc.is_posted === 1) return false
  const svcDate = formatLocalYYYYMMDD(svc.service_date)
  const systemDate = formatLocalYYYYMMDD(props.systemDate)
  return svcDate >= systemDate
}

function toggleAll(e) {
  if (e.target.checked) {
    selectedIds.value = displayedServices.value.filter(s => isServiceDeletable(s)).map(s => s.id)
  } else {
    selectedIds.value = []
  }
}

function isGroupAllSelected(group) {
  const deletableItems = group.items.filter(item => isServiceDeletable(item))
  if (deletableItems.length === 0) return false
  return deletableItems.every(item => selectedIds.value.includes(item.id))
}

function isGroupPartialSelected(group) {
  const deletableItems = group.items.filter(item => isServiceDeletable(item))
  const hasSelected = deletableItems.some(item => selectedIds.value.includes(item.id))
  return hasSelected && !isGroupAllSelected(group)
}

function toggleGroup(group, checked) {
  const deletableIds = group.items.filter(item => isServiceDeletable(item)).map(i => i.id)
  if (checked) {
    const toAdd = deletableIds.filter(id => !selectedIds.value.includes(id))
    selectedIds.value = [...selectedIds.value, ...toAdd]
  } else {
    selectedIds.value = selectedIds.value.filter(id => !deletableIds.includes(id))
  }
}

async function confirmDelete() {
  if (selectedIds.value.length === 0) return

  uiStore.confirm({
    title: 'Xác nhận xóa dịch vụ',
    message: `Bạn có chắc chắn muốn xóa ${selectedIds.value.length} dòng dịch vụ đã chọn?`,
    confirmText: 'Đồng ý xóa',
    cancelText: 'Hủy'
  }).then(async (confirmed) => {
    if (!confirmed) return
    isDeleting.value = true
    try {
      // Group selected IDs by room
      let totalDeleted = 0
      for (const r of allRooms.value) {
        if (!r.bookingRoomId) continue
        const roomSvcIds = (roomServicesMap.value[r.bookingRoomId] || []).map(s => s.id)
        const toDelete = selectedIds.value.filter(id => roomSvcIds.includes(id))
        if (toDelete.length === 0) continue
        await deleteBookingRoomServicesBulk(r.bookingRoomId, { service_ids: toDelete })
        totalDeleted += toDelete.length
      }
      uiStore.showToast(`Đã xóa ${totalDeleted} dịch vụ thành công!`, 'success')
      close()
      emit('deleted')
    } catch (err) {
      console.error(err)
      uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa dịch vụ!', 'error')
    } finally {
      isDeleting.value = false
    }
  })
}
</script>
