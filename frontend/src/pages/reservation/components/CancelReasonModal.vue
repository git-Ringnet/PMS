<template>
  <div 
    v-if="show" 
    @click="close"
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
  >
    <div 
      @click.stop
      class="bg-white rounded-xl shadow-2xl w-full max-w-[420px] overflow-hidden border border-slate-200 flex flex-col relative"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)`, transition: isDraggingModal ? 'none' : '' }"
    >
      <!-- MODAL HEADER -->
      <div 
        class="flex justify-between items-center px-4 py-3 shrink-0 select-none cursor-move"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-bold text-xs uppercase tracking-wider">
          <span>{{ title || 'Bạn có chắc chắn muốn xóa phòng này?' }}</span>
        </div>
        <button 
          class="hover:bg-white/20 p-1 rounded-md cursor-pointer border-none transition flex items-center justify-center" 
          :style="{ color: 'inherit' }"
          @click="close"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </div>

      <!-- MODAL BODY -->
      <div class="p-5 flex flex-col gap-4 text-xs font-semibold text-slate-700">
        <div v-if="subTitle" class="p-3 bg-sky-50 rounded-lg border border-sky-100 text-sky-700 text-xs font-medium">
          <i class="fa-solid fa-circle-info mr-1.5 text-sky-500"></i>
          <span>{{ subTitle }}</span>
        </div>

        <div>
          <label class="block text-slate-600 mb-2 font-medium">
            Chọn lý do hủy đặt phòng
          </label>
          <select 
            v-model="selectedReasonId"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-[#72c0e5] bg-white cursor-pointer"
            @change="handleReasonChange"
          >
            <option value="" disabled>-- Chọn lý do hủy --</option>
            <option 
              v-for="item in reasons" 
              :key="item.id" 
              :value="item.id"
            >
              {{ item.name }}
            </option>
          </select>
        </div>

        <!-- Ô NHẬP LÝ DO TỰ CHỌN NẾU LÀ OTHER HOẶC YÊU CẦU CHI TIẾT -->
        <div v-if="isOtherSelected" class="mt-2">
          <input 
            type="text"
            v-model="customNote"
            placeholder="Vui lòng nhập chi tiết lý do hủy..."
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-[#72c0e5] bg-white"
          />
        </div>
      </div>

      <!-- MODAL FOOTER -->
      <div class="bg-white border-t border-slate-100 px-4 py-4 flex justify-center space-x-3 shrink-0">
        <button 
          @click="close" 
          class="bg-[#9ed0f0] hover:bg-[#83beeb] text-[#1c64a3] font-bold text-xs px-6 py-2 rounded-lg cursor-pointer transition border-none shadow-sm flex items-center justify-center"
        >
          Không
        </button>
        <button 
          @click="handleConfirm" 
          class="font-bold text-xs px-6 py-2 rounded-lg cursor-pointer transition border-none shadow-sm flex items-center justify-center hover:brightness-95"
          :style="{ background: 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }"
        >
          Có
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { fetchCancelReasons } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const props = defineProps({
  show: Boolean,
  title: String,
  subTitle: String
})

const emit = defineEmits(['update:show', 'confirm'])

const uiStore = useUiStore()
const authStore = useAuthStore()

const headerBgColor = computed(() => authStore.settings?.topbar_color || '#006bdb')

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

const reasons = ref([])
const selectedReasonId = ref('')
const customNote = ref('')

const isOtherSelected = computed(() => {
  if (!selectedReasonId.value) return false
  const found = reasons.value.find(r => r.id === selectedReasonId.value)
  if (!found) return false
  const name = found.name || ''
  return name === 'Other (Hotel)' || name === 'Other (Client)'
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    modalPos.value = { x: 0, y: 0 }
    selectedReasonId.value = ''
    customNote.value = ''
    await loadReasons()
  }
})

async function loadReasons() {
  try {
    const res = await fetchCancelReasons()
    let data = []
    if (res.data?.success) {
      data = res.data.data || []
    } else {
      data = res.data || []
    }
    reasons.value = data
    
    // Mặc định chọn lý do Other (Hotel)
    const defaultReason = data.find(r => r.name === 'Other (Hotel)')
    if (defaultReason) {
      selectedReasonId.value = defaultReason.id
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách lý do hủy:', err)
  }
}

function handleReasonChange() {
  if (!isOtherSelected.value) {
    const found = reasons.value.find(r => r.id === selectedReasonId.value)
    if (found) {
      customNote.value = found.name
    }
  } else {
    customNote.value = ''
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
    uiStore.showToast('Vui lòng nhập chi tiết lý do hủy!', 'warning')
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
