<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { X, Calendar, RotateCcw, Filter } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  serviceOptions: { type: Array, default: () => [] },
  departmentOptions: { type: Array, default: () => [] },
  folioOptions: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'filter'])

const startDate = ref('')
const endDate = ref('')
const displayFilter = ref('unpaid') // 'unpaid' | 'vat' | 'unprinted_vat' | 'paid' | 'deleted'

const serviceCodeChecked = ref(false)
const serviceCode = ref('')
const serviceFolio = ref('')

const deptChecked = ref(false)
const department = ref('')

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
  if (event.button !== 0 || event.target.closest('button, input, select, textarea')) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('mousemove', onModalDrag)
  window.addEventListener('mouseup', stopModalDrag)
}

function onModalDrag(event) {
  if (!isDraggingModal.value) return
  const rawX = dragStart.value.left + (event.clientX - dragStart.value.x)
  const rawY = dragStart.value.top + (event.clientY - dragStart.value.y)
  modalPosition.value = clampModalPosition(rawX, rawY)
}

function stopModalDrag() {
  if (!isDraggingModal.value) return
  isDraggingModal.value = false
  window.removeEventListener('mousemove', onModalDrag)
  window.removeEventListener('mouseup', stopModalDrag)
}

const handleClose = () => {
  emit('close')
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) handleClose()
}

watch(() => props.show, visible => {
  if (visible) {
    modalPosition.value = { x: 0, y: 0 }
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    stopModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
  }
})

onBeforeUnmount(() => {
  stopModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})

const reset = () => {
  startDate.value = ''
  endDate.value = ''
  displayFilter.value = 'unpaid'
  serviceCodeChecked.value = false
  serviceCode.value = ''
  serviceFolio.value = ''
  deptChecked.value = false
  department.value = ''
  emit('reset')
}

const applyFilter = () => {
  emit('filter', {
    startDate: startDate.value,
    endDate: endDate.value,
    displayFilter: displayFilter.value,
    serviceCode: serviceCodeChecked.value ? serviceCode.value : '',
    folio: serviceFolio.value,
    department: deptChecked.value ? department.value : ''
  })
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4 font-sans select-none">
    <div
      ref="modalRef"
      class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden border border-gray-300 flex flex-col text-xs"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      
      <!-- Header -->
      <div
        class="text-white px-4 py-2.5 flex items-center justify-between font-semibold cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Lọc Dịch Vụ</span>
        <button @click="handleClose" class="rounded p-0.5 text-white transition hover:bg-white/20 active:scale-90 cursor-pointer"><X class="w-4 h-4" /></button>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-4 max-h-[80vh] overflow-y-auto">
        
        <!-- Row 1: Ngày bắt đầu & Ngày kết thúc -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-gray-700 mb-1">Ngày bắt đầu</label>
            <div class="relative">
              <input type="date" v-model="startDate" class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-gray-700 mb-1">Ngày kết thúc</label>
            <div class="relative">
              <input type="date" v-model="endDate" class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 1: Chỉ hiển thị (Radio group) -->
        <div class="grid grid-cols-12 gap-2">
          <label class="col-span-3 font-bold text-gray-700">Chỉ hiển thị</label>
          <div class="col-span-9 space-y-2">
            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="unpaid" class="text-sky-500 border-gray-300" />
              <span>Dịch vụ chưa thanh toán</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="vat" class="text-sky-500 border-gray-300" />
              <span>In VAT</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="unprinted_vat" class="text-sky-500 border-gray-300" />
              <span>Hoá đơn chưa in VAT</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="paid" class="text-sky-500 border-gray-300" />
              <span>Đã thanh toán</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer font-medium text-gray-700">
              <input type="radio" v-model="displayFilter" value="deleted" class="text-sky-500 border-gray-300" />
              <span>Đã xoá</span>
            </label>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 2: Lọc theo dịch vụ -->
        <div class="space-y-1.5">
          <label class="block font-bold text-gray-700">Lọc theo dịch vụ</label>
          <div class="border border-gray-300 rounded overflow-hidden">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-[#f0f2ea] border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1.5 border-r border-gray-300 w-24">Code</th>
                  <th class="p-1.5 border-r border-gray-300">Dịch vụ</th>
                  <th class="p-1.5 w-32">Folio</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-gray-200">
                  <td class="p-1.5 text-center border-r border-gray-300">
                    <input type="checkbox" v-model="serviceCodeChecked" class="rounded border-gray-300" />
                  </td>
                  <td class="p-1.5 font-semibold border-r border-gray-300">
                    <select v-model="serviceCode" class="w-full bg-transparent focus:outline-none" :disabled="!serviceCodeChecked">
                      <option value="">Tất cả</option>
                      <option v-for="code in serviceOptions" :key="code" :value="code">{{ code }}</option>
                    </select>
                  </td>
                  <td class="p-1.5 border-r border-gray-300">Minibar/Phí Minibar</td>
                  <td class="p-1">
                    <select v-model="serviceFolio" class="w-full p-1 bg-white border border-gray-300 rounded text-gray-500 focus:outline-none">
                      <option value="">Tất cả Folio</option>
                      <option v-for="folio in folioOptions" :key="folio" :value="String(folio)">Folio {{ folio }}</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="h-16 bg-white"></div>
          </div>
        </div>

        <div class="border-t border-gray-200"></div>

        <!-- Section 3: Lọc theo bộ phận -->
        <div class="space-y-1.5">
          <label class="block font-bold text-gray-700">Lọc theo bộ phận</label>
          <div class="border border-gray-300 rounded overflow-hidden">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-[#f0f2ea] border-b border-gray-300 text-gray-700 font-semibold">
                <tr>
                  <th class="p-1.5 w-8 text-center border-r border-gray-300">
                    <input type="checkbox" class="rounded border-gray-300" />
                  </th>
                  <th class="p-1.5 border-r border-gray-300 w-32">Bộ phận</th>
                  <th class="p-1.5">Bộ phận</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-gray-200">
                  <td class="p-1.5 text-center border-r border-gray-300">
                    <input type="checkbox" v-model="deptChecked" class="rounded border-gray-300" />
                  </td>
                  <td class="p-1.5 font-semibold border-r border-gray-300">
                    <select v-model="department" class="w-full bg-transparent focus:outline-none" :disabled="!deptChecked">
                      <option value="">Tất cả</option>
                      <option v-for="dept in departmentOptions" :key="dept" :value="dept">{{ dept }}</option>
                    </select>
                  </td>
                  <td class="p-1.5">Reception/ Lễ Tân</td>
                </tr>
              </tbody>
            </table>
            <div class="h-16 bg-white"></div>
          </div>
        </div>

      </div>

      <!-- Footer Actions -->
      <div class="border-t border-gray-300 p-3 flex justify-end items-center gap-2 bg-gray-50">
        <button 
          @click="handleClose"
          class="btn-pms-close"
        >
          <X class="w-4 h-4" />
          <span>Đóng</span>
        </button>

        <button 
          @click="reset"
          class="btn-pms-secondary"
        >
          <RotateCcw class="w-4 h-4" />
          <span>Khôi Phục</span>
        </button>

        <button 
          @click="applyFilter"
          class="btn-pms-primary"
        >
          <Filter class="w-4 h-4" />
          <span>Lọc</span>
        </button>
      </div>

    </div>
  </div>
</template>
