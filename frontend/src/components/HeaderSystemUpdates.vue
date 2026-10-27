<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useAuthStore } from '@/stores/auth-store'
import { useDraggableModal } from '@/composables/useDraggableModal'
import {
  fetchSystemUpdates,
  markSystemUpdatesRead,
  createSystemUpdate,
  deleteSystemUpdate
} from '@/services/alert-service'

defineProps({
  isDark: { type: Boolean, default: true }
})

const authStore = useAuthStore()
const { position: modalPos, isDragging, startDrag, resetPosition } = useDraggableModal()
const themeBg = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')

const items = ref([])
const unread = ref(0)
const canManage = ref(false)
const isOpen = ref(false)
const rootRef = ref(null)

const isEditorOpen = ref(false)
const editorContent = ref('')
const editorError = ref('')
const isSaving = ref(false)

async function loadUpdates() {
  try {
    const res = await fetchSystemUpdates()
    items.value = res.data?.data || []
    unread.value = res.data?.meta?.unread || 0
    canManage.value = Boolean(res.data?.meta?.can_manage)
  } catch (error) {
    console.error('Không tải được nội dung cập nhật hệ thống:', error)
  }
}

async function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) await loadUpdates()
}

// Bấm vào một nội dung thì mục đó chuyển sang đã đọc (đổi màu ngay, số trên chuông giảm)
async function markItemRead(item) {
  if (item.isRead) return
  item.isRead = true
  unread.value = Math.max(0, unread.value - 1)
  try {
    await markSystemUpdatesRead([item.id])
  } catch (error) {
    item.isRead = false
    unread.value += 1
    console.error('Không đánh dấu đã đọc được:', error)
  }
}

async function markAllRead() {
  const unreadItems = items.value.filter(item => !item.isRead)
  if (unreadItems.length === 0) return
  try {
    await markSystemUpdatesRead()
    unreadItems.forEach(item => { item.isRead = true })
    unread.value = 0
  } catch (error) {
    console.error('Không đánh dấu đã đọc được:', error)
  }
}

function openEditor() {
  if (!canManage.value) return
  editorContent.value = ''
  editorError.value = ''
  resetPosition()
  isEditorOpen.value = true
  isOpen.value = false
}

async function saveUpdate() {
  if (!editorContent.value.trim()) {
    editorError.value = 'Vui lòng nhập nội dung cập nhật.'
    return
  }
  isSaving.value = true
  editorError.value = ''
  try {
    await createSystemUpdate({ content: editorContent.value })
    isEditorOpen.value = false
    await loadUpdates()
  } catch (error) {
    editorError.value = error?.response?.data?.message || 'Không lưu được nội dung cập nhật.'
  } finally {
    isSaving.value = false
  }
}

async function removeUpdate(item) {
  if (!window.confirm(`Xóa nội dung cập nhật ngày ${item.updateDate}?`)) return
  try {
    await deleteSystemUpdate(item.id)
    await loadUpdates()
  } catch (error) {
    console.error('Không xóa được nội dung cập nhật:', error)
  }
}

function handleKeydown(event) {
  // Admin nhập nội dung bằng Ctrl + PageUp (một số trình duyệt dành phím này để đổi tab)
  if (event.ctrlKey && event.key === 'PageUp' && canManage.value) {
    event.preventDefault()
    openEditor()
  }
}

function handleClickOutside(event) {
  if (isOpen.value && rootRef.value && !rootRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  loadUpdates()
  window.addEventListener('keydown', handleKeydown)
  document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
  <div ref="rootRef" class="relative shrink-0 flex items-center">
    <button
      type="button"
      title="Thông báo cập nhật hệ thống"
      @click="toggleDropdown"
      class="relative p-0.5 rounded bg-transparent border-none cursor-pointer flex items-center justify-center shrink-0 transition-colors duration-200"
      :class="isDark ? 'text-white hover:bg-white/15' : 'text-gray-900 dark:text-white hover:bg-black/10'"
    >
      <svg class="w-4 h-4" :class="unread > 0 ? 'bell-ring' : ''" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
      </svg>
      <span
        v-if="unread > 0"
        class="absolute -top-1.5 -right-1.5 min-w-[15px] h-[15px] px-[3px] rounded-full bg-red-500 text-white text-[9px] font-bold leading-[15px] text-center ring-2 ring-white/80 shadow"
      >{{ unread > 99 ? '99+' : unread }}</span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 top-full mt-2 w-[360px] max-h-[70vh] overflow-auto bg-white border border-slate-200 rounded-lg shadow-xl z-[100] text-slate-800"
    >
      <div class="flex items-center justify-between px-3 py-2 border-b border-slate-100 sticky top-0 bg-white">
        <span class="text-xs font-bold">Cập nhật hệ thống</span>
        <div class="flex items-center gap-3">
          <button
            v-if="unread > 0"
            type="button"
            class="text-[11px] font-semibold text-slate-500 bg-transparent border-none cursor-pointer hover:text-slate-800"
            @click="markAllRead"
          >
            Đã đọc tất cả
          </button>
          <button
            v-if="canManage"
            type="button"
            class="text-[11px] font-semibold text-sky-600 bg-transparent border-none cursor-pointer hover:text-sky-800"
            @click="openEditor"
          >
            + Thêm nội dung
          </button>
        </div>
      </div>

      <p v-if="items.length === 0" class="p-4 text-xs text-slate-400 text-center">Chưa có nội dung cập nhật.</p>

      <div
        v-for="item in items"
        :key="item.id"
        class="px-3 py-2 border-b border-slate-100 text-xs cursor-pointer transition-colors"
        :class="item.isRead ? 'bg-white hover:bg-slate-50' : 'bg-[#cfe3e8] hover:bg-[#c3dae0]'"
        :title="item.isRead ? '' : 'Bấm để đánh dấu đã đọc'"
        @click="markItemRead(item)"
      >
        <div class="flex items-start justify-between gap-2">
          <span :class="item.isRead ? 'font-semibold' : 'font-bold'">#Nội dung cập nhật ngày {{ item.updateDate }}</span>
          <span class="text-slate-500 shrink-0">{{ item.createdAt }}</span>
        </div>
        <p class="mt-1 whitespace-pre-wrap break-words leading-relaxed">{{ item.content }}</p>
        <button
          v-if="canManage"
          type="button"
          class="mt-1 text-[11px] text-red-500 bg-transparent border-none cursor-pointer p-0 hover:text-red-700"
          @click.stop="removeUpdate(item)"
        >
          Xóa
        </button>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="isEditorOpen" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/20" @mousedown.self="isEditorOpen = false">
      <div
        class="bg-white rounded-xl shadow-2xl w-[min(560px,94vw)] overflow-hidden border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)`, transition: isDragging ? 'none' : '' }"
      >
        <div
          class="flex items-center justify-between px-4 h-10 text-white text-xs font-bold select-none cursor-move"
          :style="{ background: themeBg }"
          @mousedown="startDrag"
        >
          <span>Nhập nội dung cập nhật hệ thống</span>
          <button type="button" class="border-none bg-transparent text-white cursor-pointer text-lg leading-none hover:bg-white/15 rounded px-1" @click="isEditorOpen = false">&times;</button>
        </div>
        <div class="p-4 flex flex-col gap-2">
          <textarea
            v-model="editorContent"
            rows="8"
            maxlength="5000"
            placeholder="- Booking: fix lỗi gán trùng số phòng&#10;- Báo cáo: thêm cột..."
            class="w-full border border-slate-300 rounded p-2 text-xs focus:outline-none focus:border-sky-500"
          ></textarea>
          <p v-if="editorError" class="text-xs text-red-600">{{ editorError }}</p>
          <div class="flex justify-end gap-2">
            <button type="button" class="h-8 px-4 rounded border border-slate-300 bg-white text-xs cursor-pointer" @click="isEditorOpen = false">Hủy</button>
            <button
              type="button"
              class="h-8 px-4 rounded-lg text-white text-xs font-semibold border-none cursor-pointer hover:opacity-90"
              :style="{ background: themeBg }"
              :disabled="isSaving"
              @click="saveUpdate"
            >
              Lưu
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.bell-ring {
  transform-origin: 50% 0;
  animation: bell-ring 2.4s ease-in-out infinite;
}

@keyframes bell-ring {
  0%, 60%, 100% { transform: rotate(0); }
  66% { transform: rotate(14deg); }
  72% { transform: rotate(-12deg); }
  78% { transform: rotate(8deg); }
  84% { transform: rotate(-6deg); }
  90% { transform: rotate(0); }
}

@media (prefers-reduced-motion: reduce) {
  .bell-ring { animation: none; }
}
</style>
