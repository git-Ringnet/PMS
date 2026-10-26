<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { Eye, Printer, X } from '@lucide/vue'

const uiStore = useUiStore()
const authStore = useAuthStore()
const loading = ref(false)
const slots = ref([])
const templates = ref([])
const activeGroup = ref('')
const previewHtml = ref('')
const previewTitle = ref('')

const topbarThemeBg = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null
let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
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

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL', 'IFRAME']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label, iframe')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && previewHtml.value) {
    previewHtml.value = ''
  }
}

const groups = computed(() => [...new Set(slots.value.map(slot => slot.group))])
const filteredSlots = computed(() => slots.value.filter(slot => slot.group === activeGroup.value))
const templatesFor = (slot) => templates.value.filter(template => template.group === slot.group)

const load = async () => {
  loading.value = true
  try {
    const [slotResponse, templateResponse] = await Promise.all([
      http.get('/print-template-slots'),
      http.get('/templates')
    ])
    slots.value = slotResponse.data.data || []
    templates.value = templateResponse.data.data || []
    if (!activeGroup.value || !groups.value.includes(activeGroup.value)) activeGroup.value = groups.value[0] || ''
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải cấu hình mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

const assignTemplate = async (slot) => {
  try {
    const response = await http.put(`/print-template-slots/${slot.code}`, {
      template_id: slot.template_id || null
    })
    const index = slots.value.findIndex(item => item.id === slot.id)
    if (index >= 0) slots.value[index] = response.data.data
    uiStore.showToast(slot.template_id ? `Đã chọn thiết kế cho “${slot.name}”` : `Đã bỏ chọn thiết kế cho “${slot.name}”`, 'success')
  } catch (error) {
    await load()
    uiStore.showToast(error.response?.data?.errors?.template_id?.[0] || error.response?.data?.message || 'Không thể chọn thiết kế', 'error')
  }
}

const clearTemplate = async (slot) => {
  slot.template_id = null
  await assignTemplate(slot)
}

const preview = async (slot) => {
  if (!slot.template_id) {
    uiStore.showToast('Vị trí mẫu in này chưa được chọn thiết kế', 'warning')
    return
  }
  loading.value = true
  try {
    const response = await http.post(`/print-template-slots/${slot.code}/render`, {})
    previewHtml.value = response.data.html
    previewTitle.value = `${slot.name} — ${response.data.template.name}`
    modalPos.value = { x: 0, y: 0 }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xem thử mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  window.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})
</script>

<template>
  <div class="relative flex h-full min-h-[440px] gap-5">
    <div v-if="loading" class="absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-white/70">
      <div class="h-7 w-7 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600"></div>
    </div>

    <!-- Aside Group List -->
    <aside class="w-56 shrink-0 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
      <h3 class="border-b border-slate-200 px-2 pb-2.5 text-xs font-semibold uppercase tracking-wider text-[#000000D9]">Nhóm Chức Năng In</h3>
      <div class="mt-2 flex flex-col gap-1">
        <button v-for="group in groups" :key="group" @click="activeGroup = group"
          class="w-full rounded-lg border-none px-3 py-2 text-left text-xs font-medium transition-colors cursor-pointer"
          :class="activeGroup === group ? 'bg-sky-50 text-[#006bdb] font-semibold ring-1 ring-inset ring-sky-200' : 'bg-transparent text-[#000000D9] hover:bg-white'">
          {{ group }}
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="min-w-0 flex-1 flex flex-col gap-3">
      <!-- Area Header Banner: Bỏ text ghi chú dài, đổi màu chữ tiêu đề khu vực -->
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-2.5 flex items-center gap-2.5">
        <Printer class="h-4 w-4 shrink-0 text-sky-600" />
        <h3 class="text-xs font-semibold uppercase tracking-wide text-[#000000D9]">Cấu Hình Mẫu In Nghiệp Vụ</h3>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-2xs">
        <table class="w-full border-collapse text-left text-xs">
          <thead>
            <tr class="bg-slate-50 text-[#000000D9] font-semibold text-xs">
              <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[200px]">Chức Năng In</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 min-w-[280px]">Thiết Kế Đang Sử Dụng</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-28">Kiểm Tra</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in filteredSlots" :key="slot.id" class="border-b border-slate-100 hover:bg-slate-50/55 text-[#000000D9]">
              <td class="p-2.5">
                <span class="block font-normal text-[#000000D9]">{{ slot.name }}</span>
                <code class="mt-0.5 block text-xs font-normal text-slate-400 font-mono">{{ slot.code }}</code>
              </td>
              <td class="p-2.5">
                <div class="relative flex items-center">
                  <select v-model="slot.template_id" @change="assignTemplate(slot)"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 pr-8 text-xs font-normal focus:outline-none focus:border-sky-500"
                    :class="!slot.template_id ? 'text-[#A8B0BF]' : 'text-[#000000D9]'">
                    <option :value="null" disabled hidden>-- Chưa chọn thiết kế --</option>
                    <option v-for="template in templatesFor(slot)" :key="template.id" :value="template.id" class="text-[#000000D9]">
                      {{ template.name }} · v{{ template.version }}
                    </option>
                  </select>
                  <button v-if="slot.template_id" type="button" @click.stop="clearTemplate(slot)"
                    class="absolute right-7 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
                    title="Xóa lựa chọn">✕</button>
                </div>
                <p v-if="!templatesFor(slot).length" class="mt-1 text-[11px] font-normal text-amber-600">
                  Chưa có thiết kế trong nhóm này. Hãy tạo tại Thư viện thiết kế.
                </p>
              </td>
              <td class="p-2.5 text-center">
                <button :disabled="!slot.template_id" @click="preview(slot)" class="btn-pms-secondary !h-7 !px-2.5 !text-xs disabled:opacity-40">
                  <Eye class="h-3.5 w-3.5" />
                  <span>Xem Thử</span>
                </button>
              </td>
            </tr>
            <tr v-if="!filteredSlots.length">
              <td colspan="3" class="p-8 text-center text-xs italic text-slate-400">Nhóm này chưa có vị trí mẫu in.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>

    <!-- Preview Modal -->
    <div v-if="previewHtml" class="fixed inset-0 z-60 flex items-center justify-center bg-black/55 backdrop-blur-xs p-4 select-none">
      <div class="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl border border-slate-200"
        :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
        <!-- Modal Header (Draggable, Title Case, Theme Sync) -->
        <header class="flex items-center justify-between px-5 py-3 text-white cursor-move select-none rounded-t-xl bg-[#006bdb]"
          @mousedown="startDragModal"
          :style="{ background: topbarThemeBg || 'var(--pms-custom-theme, #006bdb)', color: 'var(--pms-custom-theme-text, #ffffff)' }">
          <span class="text-xs font-semibold tracking-wide text-white">{{ previewTitle }}</span>
          <button @mousedown.stop @click="previewHtml = ''" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-base font-bold p-1">
            <X class="h-4 w-4" />
          </button>
        </header>
        <div class="flex-1 overflow-auto bg-slate-100 p-5">
          <iframe :srcdoc="previewHtml" class="mx-auto block min-h-[1050px] w-full max-w-[900px] border-0 bg-white shadow-xl rounded-lg"></iframe>
        </div>
      </div>
    </div>
  </div>
</template>
