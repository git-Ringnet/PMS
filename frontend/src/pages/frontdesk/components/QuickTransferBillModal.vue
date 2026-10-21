<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { HelpCircle, X, Inbox, ArrowRightLeft, Minus } from '@lucide/vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const props = defineProps({
  show: Boolean,
  targetLabel: { type: String, default: '' },
  candidates: { type: Array, default: () => [] },
  loading: Boolean,
  loadingText: { type: String, default: 'Đang xử lý...' },
})
const emit = defineEmits(['close', 'submit'])

const modalRef = ref(null)
const modalPosition = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 })

function clampModalPosition(x, y) {
  const modal = modalRef.value
  if (!modal) return { x, y }

  const margin = 16
  const width = modal.offsetWidth
  const height = modal.offsetHeight
  const baseLeft = (window.innerWidth - width) / 2
  const baseTop = (window.innerHeight - height) / 2
  const minX = margin - baseLeft
  const maxX = window.innerWidth - margin - width - baseLeft
  const minY = margin - baseTop
  const maxY = window.innerHeight - margin - height - baseTop

  return {
    x: minX <= maxX ? Math.min(Math.max(x, minX), maxX) : 0,
    y: minY <= maxY ? Math.min(Math.max(y, minY), maxY) : 0,
  }
}

function beginModalDrag(event) {
  if (event.button !== 0) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('pointermove', moveModal)
  window.addEventListener('pointerup', endModalDrag)
}

function moveModal(event) {
  if (!isDraggingModal.value) return
  modalPosition.value = clampModalPosition(
    dragStart.value.left + event.clientX - dragStart.value.x,
    dragStart.value.top + event.clientY - dragStart.value.y,
  )
}

function endModalDrag() {
  isDraggingModal.value = false
  window.removeEventListener('pointermove', moveModal)
  window.removeEventListener('pointerup', endModalDrag)
}

function resetModalPosition() {
  endModalDrag()
  modalPosition.value = { x: 0, y: 0 }
}

const selectedBillIds = ref([])
const collapsedGroups = ref({})

const close = () => { if (!props.loading) emit('close') }
function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) close()
}
watch(() => props.show, visible => {
  if (visible) {
    resetModalPosition()
    selectedBillIds.value = []
    collapsedGroups.value = {}
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    endModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
  }
})
onBeforeUnmount(() => {
  endModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})
const money = value => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(value) || 0)
const groupedCandidates = computed(() => {
  const groups = new Map()
  props.candidates.forEach(item => {
    const key = String(item.category || 'DV').toUpperCase()
    if (!groups.has(key)) groups.set(key, { key, label: item.category_label || key, items: [] })
    groups.get(key).items.push(item)
  })
  return [...groups.values()]
})
const isSelected = id => selectedBillIds.value.includes(id)
const toggle = (id, checked) => { selectedBillIds.value = checked ? [...new Set([...selectedBillIds.value, id])] : selectedBillIds.value.filter(value => value !== id) }
const isAllSelected = computed(() => props.candidates.length > 0 && props.candidates.every(item => isSelected(item.bill_id)))
const toggleAll = checked => { selectedBillIds.value = checked ? props.candidates.map(item => item.bill_id) : [] }
const isGroupSelected = group => group.items.length > 0 && group.items.every(item => isSelected(item.bill_id))
const toggleGroup = (group, checked) => group.items.forEach(item => toggle(item.bill_id, checked))
const toggleCollapsed = key => { collapsedGroups.value[key] = !collapsedGroups.value[key] }
const submit = () => { if (selectedBillIds.value.length && !props.loading) emit('submit', selectedBillIds.value) }
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 font-sans select-none">
    <div
      ref="modalRef"
      class="flex w-full max-w-[795px] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
      :style="{ transform: `translate(${modalPosition.x}px, ${modalPosition.y}px)`, transition: isDraggingModal ? 'none' : '' }"
    >
      <header
        class="flex items-center justify-between px-4 py-2.5 text-white cursor-move select-none touch-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @pointerdown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Tập Hợp Dịch Vụ</span>
        <div class="flex items-center gap-2">
          <HelpCircle class="h-4 w-4 text-white/80" />
          <button @pointerdown.stop :disabled="loading" @click="close" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer"><X class="h-4 w-4" /></button>
        </div>
      </header>
      <div class="space-y-3 p-4">
        <input :value="targetLabel" readonly class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 font-normal text-[#000000D9]" />
        <div class="relative min-h-[260px] max-h-[578px] overflow-auto rounded-lg border border-slate-200 bg-white">
          <table class="w-full border-collapse text-left text-xs">
            <thead class="sticky top-0 bg-slate-100 font-semibold text-[#000000D9]">
              <tr>
                <th class="w-10 border border-slate-200 p-2 text-center align-middle"></th>
                <th class="w-10 border border-slate-200 p-2 text-center align-middle"><input type="checkbox" :checked="isAllSelected" :disabled="loading || !candidates.length" @change="toggleAll($event.target.checked)" class="rounded border-slate-300" /></th>
                <th class="border border-slate-200 p-2 text-center align-middle">Mã Đăng Ký</th>
                <th class="border border-slate-200 p-2 text-center align-middle">Tên Khách</th>
                <th class="border border-slate-200 p-2 text-center align-middle">Phòng</th>
                <th class="border border-slate-200 p-2 text-center align-middle">Số Tiền</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in groupedCandidates" :key="group.key">
                <tr class="border-t border-slate-200 bg-slate-50/60">
                  <td class="border-b border-slate-200 p-2 text-center">
                    <button type="button" @click="toggleCollapsed(group.key)">
                      <Minus v-if="!collapsedGroups[group.key]" class="inline h-4 w-4 rounded bg-[#0788f5] p-0.5 text-white" />
                      <span v-else class="inline-block h-4 w-4 rounded bg-[#0788f5] text-center leading-4 text-white font-bold">+</span>
                    </button>
                  </td>
                  <td class="border-b border-slate-200 p-2 text-center"><input type="checkbox" :checked="isGroupSelected(group)" :disabled="loading" @change="toggleGroup(group, $event.target.checked)" class="rounded border-slate-300" /></td>
                  <td colspan="4" class="p-2 font-semibold text-[#000000D9] border-b border-slate-200">{{ group.label }}</td>
                </tr>
                <tr v-for="item in (collapsedGroups[group.key] ? [] : group.items)" :key="item.bill_id" class="border-b border-slate-100 bg-white hover:bg-slate-50 transition-colors">
                  <td class="border-slate-100"></td>
                  <td class="border-slate-100 p-2 text-center"><input type="checkbox" :checked="isSelected(item.bill_id)" :disabled="loading" @change="toggle(item.bill_id, $event.target.checked)" class="rounded border-slate-300" /></td>
                  <td class="border-slate-100 p-2 font-normal text-[#000000D9]">{{ item.booking_code }}</td>
                  <td class="border-slate-100 p-2 font-normal text-[#000000D9] whitespace-normal">{{ item.guest_name }}</td>
                  <td class="border-slate-100 p-2 font-normal text-[#000000D9]">{{ item.room_number }}</td>
                  <td class="p-2 text-right tabular-nums font-normal text-[#000000D9] border-slate-100">{{ money(item.amount) }}</td>
                </tr>
              </template>
            </tbody>
          </table>
          <div v-if="!loading && !candidates.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400"><Inbox class="mb-1 h-10 w-10 text-gray-300" /><span class="text-xs text-slate-400">Không có dữ liệu</span></div>
          <LoadingOverlay :show="loading" />
        </div>
      </div>
      <footer class="flex justify-end gap-2 border-t border-slate-200 p-3">
        <button :disabled="!selectedBillIds.length || loading" @click="submit" class="btn-pms-primary"><ArrowRightLeft class="h-4 w-4" />Chuyển Bill</button>
      </footer>
    </div>
  </div>
</template>
