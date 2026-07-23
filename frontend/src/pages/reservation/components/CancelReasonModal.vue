<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[480px] overflow-hidden border border-slate-200 flex flex-col"
    >
      <!-- MODAL HEADER -->
      <div class="bg-red-700 text-white flex justify-between items-center px-4 py-3 shrink-0 select-none">
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
          <i class="fa-solid fa-triangle-exclamation text-amber-300"></i>
          <span>{{ title || 'Xác nhận hủy đặt phòng' }}</span>
        </div>
        <button 
          class="hover:text-white bg-white/10 px-1.5 py-0.5 rounded-md cursor-pointer border-none text-white/80" 
          @click="close"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-slate-700">
        <div v-if="subTitle" class="p-3 bg-red-50 rounded-lg border border-red-100 text-red-700 text-xs font-medium">
          <i class="fa-solid fa-circle-info mr-1.5"></i>
          <span>{{ subTitle }}</span>
        </div>

        <div>
          <label class="block text-slate-600 mb-1.5 font-bold">
            Lý do hủy <span class="text-red-500">*</span>
          </label>
          <select 
            v-model="selectedReasonId"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 bg-white"
            @change="handleReasonChange"
          >
            <option value="" disabled>-- Chọn lý do hủy --</option>
            <option 
              v-for="item in reasons" 
              :key="item.id" 
              :value="item.id"
            >
              {{ item.name }} {{ item.description ? `(${item.description})` : '' }}
            </option>
          </select>
        </div>

        <!-- Ô NHẬP LÝ DO TỰ CHỌN NẾU LÀ OTHER HOẶC YÊU CẦU CHI TIẾT -->
        <div v-if="isOtherSelected || isRequireNote">
          <label class="block text-slate-600 mb-1.5 font-bold">
            Chi tiết lý do hủy {{ isOtherSelected ? '(Bắt buộc)' : '(Tùy chọn)' }} <span v-if="isOtherSelected" class="text-red-500">*</span>
          </label>
          <textarea 
            v-model="customNote"
            rows="3"
            placeholder="Vui lòng nhập chi tiết lý do hủy..."
            class="w-full border border-slate-300 rounded-lg p-2.5 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 bg-white resize-none"
          ></textarea>
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 flex justify-end space-x-2.5 shrink-0">
        <button 
          @click="close" 
          class="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-lg hover:bg-slate-100 cursor-pointer transition bg-white"
        >
          Bỏ qua
        </button>
        <button 
          @click="handleConfirm" 
          class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-4 py-2 rounded-lg cursor-pointer shadow-sm flex items-center space-x-1.5 transition border-none"
        >
          <i class="fa-solid fa-trash-can"></i>
          <span>Xác nhận hủy</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { fetchCancelReasons } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  title: String,
  subTitle: String
})

const emit = defineEmits(['update:show', 'confirm'])

const uiStore = useUiStore()

const reasons = ref([])
const selectedReasonId = ref('')
const customNote = ref('')

const isOtherSelected = computed(() => {
  if (!selectedReasonId.value) return false
  const found = reasons.value.find(r => r.id === selectedReasonId.value)
  if (!found) return false
  const nameLower = (found.name || '').toLowerCase()
  return nameLower.includes('other') || nameLower.includes('khác')
})

const isRequireNote = computed(() => {
  return isOtherSelected.value
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    selectedReasonId.value = ''
    customNote.value = ''
    await loadReasons()
  }
})

async function loadReasons() {
  try {
    const res = await fetchCancelReasons()
    if (res.data?.success) {
      reasons.value = res.data.data || []
    } else {
      reasons.value = res.data || []
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách lý do hủy:', err)
  }
}

function handleReasonChange() {
  if (!isOtherSelected.value) {
    const found = reasons.value.find(r => r.id === selectedReasonId.value)
    if (found && !customNote.value) {
      customNote.value = found.name
    }
  }
}

function close() {
  emit('update:show', false)
}

function handleConfirm() {
  if (!selectedReasonId.value) {
    uiStore.showToast('Vui lòng chọn lý do hủy phòng!', 'warning')
    return
  }

  if (isOtherSelected.value && !customNote.value.trim()) {
    uiStore.showToast('Vui lòng nhập chi tiết lý do hủy khi chọn nhóm lý do khác!', 'warning')
    return
  }

  const selectedReasonObj = reasons.value.find(r => r.id === selectedReasonId.value)
  const finalNote = customNote.value.trim() || selectedReasonObj?.name || ''

  emit('confirm', {
    cancel_reason_id: selectedReasonId.value,
    note: finalNote,
    reason_name: selectedReasonObj?.name
  })

  close()
}
</script>
