<script setup>
import { ref, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { useHkStore } from '@/stores/hk-store'
import {
  Save, RotateCcw, Plus, Trash2, ArrowUp, ArrowDown,
  Layers, FileText, CheckCircle2, AlertCircle, Settings2
} from '@lucide/vue'

const uiStore = useUiStore()
const hkStore = useHkStore()

const activeSubTab = ref('symbols') // 'symbols' | 'print_cols'
const activeTemplate = ref('worksheet') // 'worksheet' | 'supervisor'
const loading = ref(false)

// State load từ API
const symbols = ref([])
const printCols = ref([])

// Form thêm cột mới
const newColName = ref('')
const newColParentLabel = ref('')
const newColWidth = ref('')

const hkSymbolsList = computed(() => symbols.value.filter(s => s.group === 'hk'))
const bookingSymbolsList = computed(() => symbols.value.filter(s => s.group === 'booking'))
const extraSymbolsList = computed(() => symbols.value.filter(s => s.group === 'extra'))

const currentTemplateCols = computed(() => {
  return printCols.value
    .filter(c => c.template === activeTemplate.value)
    .sort((a, b) => a.sort_order - b.sort_order)
})

onMounted(() => {
  fetchConfig()
})

async function fetchConfig() {
  loading.value = true
  try {
    const res = await http.get('/hk-config')
    symbols.value = res.data.symbols || []
    printCols.value = res.data.printCols || []
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải cấu hình', 'error')
  } finally {
    loading.value = false
  }
}

// ─────────────────────────────────────────────────────────────
// Ký hiệu & Trạng thái
// ─────────────────────────────────────────────────────────────
async function saveSymbols() {
  loading.value = true
  try {
    await http.put('/hk-config/symbols', { symbols: symbols.value })
    uiStore.showToast('Lưu cấu hình ký hiệu thành công!', 'success')
    await hkStore.loadHkConfig() // Refresh store
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể lưu ký hiệu', 'error')
  } finally {
    loading.value = false
  }
}

// ─────────────────────────────────────────────────────────────
// Mẫu in (Print Columns)
// ─────────────────────────────────────────────────────────────
function moveCol(index, direction) {
  const templateCols = [...currentTemplateCols.value]
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= templateCols.length) return

  // Hoán đổi vị trí
  const temp = templateCols[index]
  templateCols[index] = templateCols[targetIndex]
  templateCols[targetIndex] = temp

  // Cập nhật lại sort_order
  templateCols.forEach((col, idx) => {
    col.sort_order = idx + 1
  })

  // Cập nhật lại danh sách tổng
  const otherTemplateCols = printCols.value.filter(c => c.template !== activeTemplate.value)
  printCols.value = [...otherTemplateCols, ...templateCols]
}

// Xóa cột mở rộng
function removeCol(id, label) {
  printCols.value = printCols.value.filter(c => !(c.template === activeTemplate.value && c.label === label))
  // Cập nhật lại sort_order
  const templateCols = currentTemplateCols.value
  templateCols.forEach((col, idx) => {
    col.sort_order = idx + 1
  })
}

// Thêm cột mở rộng
function addCol() {
  const name = newColName.value.trim()
  if (!name) {
    uiStore.showToast('Vui lòng nhập tên cột', 'warning')
    return
  }

  // Check trùng
  const dup = currentTemplateCols.value.some(c => c.label.toLowerCase() === name.toLowerCase())
  if (dup) {
    uiStore.showToast('Tên cột đã tồn tại trong mẫu in này', 'warning')
    return
  }

  const newCol = {
    id: 'temp-' + Math.random().toString(36).substring(2, 9),
    template: activeTemplate.value,
    label: name,
    parent_label: newColParentLabel.value.trim() || null,
    width: newColWidth.value.trim() || null,
    is_fixed: false,
    sort_order: currentTemplateCols.value.length + 1
  }

  printCols.value.push(newCol)
  newColName.value = ''
  newColParentLabel.value = ''
  newColWidth.value = ''
  uiStore.showToast('Đã thêm cột mới vào danh sách nháp', 'success')
}

// Thêm cột con trực tiếp dưới một cột khác
function addChildCol(index) {
  const parentCol = currentTemplateCols.value[index]
  if (!parentCol) return

  let parentLabel = parentCol.parent_label
  if (!parentLabel) {
    parentLabel = parentCol.label || 'NHÓM MỚI'
    parentCol.parent_label = parentLabel
    parentCol.label = 'Cột 1'
  }

  const newCol = {
    id: 'temp-' + Math.random().toString(36).substring(2, 9),
    template: activeTemplate.value,
    label: 'Cột mới',
    parent_label: parentLabel,
    width: parentCol.width || '',
    is_fixed: false,
    sort_order: parentCol.sort_order + 1
  }

  const otherTemplateCols = printCols.value.filter(c => c.template !== activeTemplate.value)
  const templateCols = [...currentTemplateCols.value]
  templateCols.splice(index + 1, 0, newCol)

  templateCols.forEach((col, idx) => {
    col.sort_order = idx + 1
  })

  printCols.value = [...otherTemplateCols, ...templateCols]
  uiStore.showToast(`Đã thêm cột con dưới nhóm "${parentLabel}"`, 'success')
}

async function savePrintCols() {
  loading.value = true
  try {
    await http.put('/hk-config/print-cols', {
      template: activeTemplate.value,
      cols: currentTemplateCols.value
    })
    uiStore.showToast('Lưu mẫu in thành công!', 'success')
    await hkStore.loadHkConfig() // Refresh store
    await fetchConfig() // Reload from DB
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể lưu cấu hình mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

// ─────────────────────────────────────────────────────────────
// Reset mặc định toàn bộ
// ─────────────────────────────────────────────────────────────
async function confirmReset() {
  const ok = await uiStore.confirm({
    title: 'Khôi phục cấu hình mặc định',
    message: 'Hệ thống sẽ reset toàn bộ ký hiệu phòng và các cột trong cả 2 mẫu in về trạng thái gốc của hệ thống. Bạn có muốn tiếp tục?',
    confirmText: 'Khôi phục',
    cancelText: 'Hủy'
  })
  if (!ok) return

  loading.value = true
  try {
    await http.post('/hk-config/reset')
    uiStore.showToast('Đã khôi phục cấu hình mặc định thành công!', 'success')
    await hkStore.loadHkConfig()
    await fetchConfig()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể khôi phục cấu hình', 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="p-6 bg-slate-50 flex-1 flex flex-col overflow-auto text-xs relative select-none">
    
    <!-- Top Settings Banner/Header -->
    <div class="mb-5 bg-white border border-slate-200/80 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-sky-50 rounded-lg flex items-center justify-center text-sky-600">
          <Settings2 :size="18" />
        </div>
        <div>
          <h2 class="text-sm font-bold text-slate-800 tracking-wide">CẤU HÌNH HOUSEKEEPING LINH ĐỘNG</h2>
          <p class="text-[10px] text-slate-500 mt-0.5">Tùy biến các ký hiệu tình trạng buồng phòng và định nghĩa cột cho các mẫu in.</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button 
          @click="confirmReset"
          class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg transition-colors border border-slate-300/50"
        >
          <RotateCcw :size="13" />
          <span>Khôi phục mặc định</span>
        </button>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="flex items-center border-b border-slate-200 mb-5 gap-1">
      <button 
        @click="activeSubTab = 'symbols'"
        class="px-4 py-2 border-b-2 font-bold transition-all flex items-center gap-1.5 -mb-[2px]"
        :class="activeSubTab === 'symbols' 
          ? 'border-sky-500 text-sky-600 bg-sky-50/40 rounded-t-lg' 
          : 'border-transparent text-slate-500 hover:text-slate-800'"
      >
        <Layers :size="14" />
        Ký hiệu trạng thái & Đặt phòng
      </button>
      <button 
        @click="activeSubTab = 'print_cols'"
        class="px-4 py-2 border-b-2 font-bold transition-all flex items-center gap-1.5 -mb-[2px]"
        :class="activeSubTab === 'print_cols' 
          ? 'border-sky-500 text-sky-600 bg-sky-50/40 rounded-t-lg' 
          : 'border-transparent text-slate-500 hover:text-slate-800'"
      >
        <FileText :size="14" />
        Định nghĩa cột mẫu in
      </button>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-50 rounded-xl">
      <div class="flex flex-col items-center gap-2">
        <svg class="animate-spin h-7 w-7 text-sky-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-xs font-bold text-slate-600">Đang tải cấu hình...</span>
      </div>
    </div>

    <!-- CONTENT TAB 1: KÝ HIỆU -->
    <div v-if="activeSubTab === 'symbols'" class="flex-1 flex flex-col gap-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <!-- Housekeeping symbols card -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200/80 flex items-center justify-between">
            <span class="font-bold text-slate-800">Ký hiệu vệ sinh buồng phòng (HK Status)</span>
            <span class="text-[10px] text-slate-400 font-medium">Lưu trữ Real-time & Giao việc</span>
          </div>
          <div class="p-4 overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-slate-100 text-slate-400 font-bold text-[10px] uppercase">
                  <th class="pb-2">Trạng thái gốc</th>
                  <th class="pb-2">Mã hiển thị</th>
                  <th class="pb-2">Nhãn tiếng Việt</th>
                  <th class="pb-2 text-center w-16">Màu</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="sym in hkSymbolsList" :key="sym.id" class="hover:bg-slate-50/40">
                  <td class="py-2.5 font-mono text-[10.5px] text-slate-500 font-bold">{{ sym.status_key }}</td>
                  <td class="py-2.5 pr-2">
                    <input v-model="sym.code" type="text" class="w-full max-w-[80px] border border-slate-200 rounded px-1.5 py-1 text-xs font-bold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                  </td>
                  <td class="py-2.5 pr-2">
                    <input v-model="sym.label" type="text" class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-semibold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                  </td>
                  <td class="py-2.5 text-center">
                    <div class="inline-flex items-center gap-1.5">
                      <input v-model="sym.color" type="color" class="w-6 h-6 p-0 border-0 rounded cursor-pointer overflow-hidden" />
                      <span class="font-mono text-[10px] text-slate-400 hidden sm:inline">{{ sym.color }}</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Booking & Extra symbols card -->
        <div class="flex flex-col gap-6">
          
          <!-- Booking symbols -->
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200/80 flex items-center justify-between">
              <span class="font-bold text-slate-800">Ký hiệu trạng thái đặt phòng (Booking Status)</span>
              <span class="text-[10px] text-slate-400 font-medium">CI / CO / LCO...</span>
            </div>
            <div class="p-4 overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-slate-400 font-bold text-[10px] uppercase">
                    <th class="pb-2">Trạng thái đặt phòng</th>
                    <th class="pb-2">Mã hiển thị</th>
                    <th class="pb-2">Nhãn tiếng Việt</th>
                    <th class="pb-2 text-center w-16">Màu</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="sym in bookingSymbolsList" :key="sym.id" class="hover:bg-slate-50/40">
                    <td class="py-2.5 font-mono text-[10.5px] text-slate-500 font-bold">{{ sym.status_key }}</td>
                    <td class="py-2.5 pr-2">
                      <input v-model="sym.code" type="text" class="w-full max-w-[80px] border border-slate-200 rounded px-1.5 py-1 text-xs font-bold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" placeholder="N/A" />
                    </td>
                    <td class="py-2.5 pr-2">
                      <input v-model="sym.label" type="text" class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-semibold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                    </td>
                    <td class="py-2.5 text-center">
                      <div class="inline-flex items-center gap-1.5" v-if="sym.color !== null">
                        <input v-model="sym.color" type="color" class="w-6 h-6 p-0 border-0 rounded cursor-pointer overflow-hidden" />
                      </div>
                      <span v-else class="text-slate-400">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Extra info symbols -->
          <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200/80 flex items-center justify-between">
              <span class="font-bold text-slate-800">Ký hiệu bổ sung (Extra Info)</span>
              <span class="text-[10px] text-slate-400 font-medium">Giường phụ, người lớn...</span>
            </div>
            <div class="p-4 overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-slate-100 text-slate-400 font-bold text-[10px] uppercase">
                    <th class="pb-2">Loại bổ sung</th>
                    <th class="pb-2">Ký hiệu trên worksheet</th>
                    <th class="pb-2">Nhãn tiếng Việt</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="sym in extraSymbolsList" :key="sym.id" class="hover:bg-slate-50/40">
                    <td class="py-2.5 font-mono text-[10.5px] text-slate-500 font-bold">{{ sym.status_key }}</td>
                    <td class="py-2.5 pr-2">
                      <input v-model="sym.code" type="text" class="w-full max-w-[120px] border border-slate-200 rounded px-1.5 py-1 text-xs font-bold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                    </td>
                    <td class="py-2.5 pr-2">
                      <input v-model="sym.label" type="text" class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-semibold focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

      <!-- Action save bottom -->
      <div class="flex items-center justify-end gap-2 bg-white border border-slate-200 p-3 rounded-xl shadow-sm">
        <span class="text-[10px] text-slate-400 italic mr-auto flex items-center gap-1">
          <AlertCircle :size="12" /> Lưu ý: Thay đổi ký hiệu sẽ áp dụng trực tiếp cho tất cả các giao diện và lượt in ấn mới.
        </span>
        <button 
          @click="saveSymbols"
          class="flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-lg transition-colors border-none cursor-pointer"
        >
          <Save :size="14" />
          <span>Lưu cấu hình ký hiệu</span>
        </button>
      </div>
    </div>

    <!-- CONTENT TAB 2: CẤU HÌNH CỘT MẪU IN -->
    <div v-if="activeSubTab === 'print_cols'" class="flex-1 flex flex-col gap-6">
      
      <!-- Sub headers selector -->
      <div class="flex gap-2">
        <button 
          @click="activeTemplate = 'worksheet'"
          class="px-4 py-2 border rounded-lg font-bold transition-all"
          :class="activeTemplate === 'worksheet' 
            ? 'border-sky-500 bg-sky-50 text-sky-700 shadow-sm' 
            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
        >
          Mẫu in Worksheet (Nhân viên buồng phòng)
        </button>
        <button 
          @click="activeTemplate = 'supervisor'"
          class="px-4 py-2 border rounded-lg font-bold transition-all"
          :class="activeTemplate === 'supervisor' 
            ? 'border-sky-500 bg-sky-50 text-sky-700 shadow-sm' 
            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
        >
          Mẫu in Supervisor Check List
        </button>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        
        <!-- Columns table list -->
        <div class="xl:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-4 py-3 bg-slate-50/80 border-b border-slate-200/80 flex items-center justify-between">
            <span class="font-bold text-slate-800">Danh sách cột mẫu in (Thứ tự từ trái sang phải)</span>
            <span class="text-[10px] text-slate-400 font-bold bg-slate-200/60 px-2 py-0.5 rounded-full">
              {{ currentTemplateCols.length }} Cột
            </span>
          </div>
          <div class="p-4 overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-slate-100 text-slate-400 font-bold text-[10px] uppercase">
                  <th class="pb-2 w-10 text-center">STT</th>
                  <th class="pb-2">Tên cột</th>
                  <th class="pb-2">Cột cha / Nhóm</th>
                  <th class="pb-2 w-24">Độ rộng</th>
                  <th class="pb-2 w-28 text-center">Thuộc tính</th>
                  <th class="pb-2 text-center w-24">Thứ tự</th>
                  <th class="pb-2 text-center w-12">Xóa</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(col, index) in currentTemplateCols" :key="col.id || col.label" class="hover:bg-slate-50/40">
                  <td class="py-2 text-center text-slate-400 font-bold">{{ index + 1 }}</td>
                  <td class="py-2 pr-2">
                    <div class="flex flex-col gap-1">
                      <input 
                        v-model="col.label" 
                        type="text" 
                        :disabled="col.is_fixed"
                        class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-bold focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500" 
                      />
                      <button 
                        v-if="!col.is_fixed"
                        @click="addChildCol(index)"
                        class="self-start text-[9px] text-sky-600 hover:text-sky-800 font-bold flex items-center gap-0.5 border-0 bg-transparent p-0 cursor-pointer"
                      >
                        <Plus :size="10" />
                        <span>Thêm cột con</span>
                      </button>
                    </div>
                  </td>
                  <td class="py-2 pr-2">
                    <input 
                      v-model="col.parent_label" 
                      type="text" 
                      placeholder="Không có"
                      :disabled="col.is_fixed"
                      class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-bold focus:border-sky-500 focus:ring-1 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500" 
                    />
                  </td>
                  <td class="py-2 pr-2">
                    <input 
                      v-model="col.width" 
                      type="text" 
                      placeholder="Tự động"
                      class="w-full border border-slate-200 rounded px-1.5 py-1 text-xs font-mono focus:border-sky-500 focus:ring-1 focus:ring-sky-500" 
                    />
                  </td>
                  <td class="py-2 text-center">
                    <span 
                      v-if="col.is_fixed" 
                      class="px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 font-bold rounded-full text-[9px] uppercase tracking-wider"
                    >
                      Bắt buộc
                    </span>
                    <span 
                      v-else 
                      class="px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-600 font-bold rounded-full text-[9px] uppercase tracking-wider"
                    >
                      Mở rộng
                    </span>
                  </td>
                  <td class="py-2 text-center">
                    <div class="inline-flex items-center gap-1">
                      <button 
                        @click="moveCol(index, -1)" 
                        :disabled="index === 0"
                        class="p-1 text-slate-500 hover:text-sky-600 hover:bg-slate-100 rounded disabled:opacity-30 disabled:hover:bg-transparent"
                      >
                        <ArrowUp :size="12" />
                      </button>
                      <button 
                        @click="moveCol(index, 1)" 
                        :disabled="index === currentTemplateCols.length - 1"
                        class="p-1 text-slate-500 hover:text-sky-600 hover:bg-slate-100 rounded disabled:opacity-30 disabled:hover:bg-transparent"
                      >
                        <ArrowDown :size="12" />
                      </button>
                    </div>
                  </td>
                  <td class="py-2 text-center">
                    <button 
                      @click="removeCol(col.id, col.label)"
                      :disabled="col.is_fixed"
                      class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md disabled:opacity-30 disabled:hover:bg-transparent"
                    >
                      <Trash2 :size="13" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add custom column box -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col gap-4">
          <h3 class="font-bold text-slate-800 text-xs flex items-center gap-1.5 border-b border-slate-100 pb-2">
            <Plus :size="14" class="text-sky-500" />
            <span>Thêm cột mở rộng mới</span>
          </h3>
          <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-600">Tên nhãn cột</label>
              <input 
                v-model="newColName" 
                type="text" 
                placeholder="VD: NƯỚC FREE, DÉP, BÀN CHẢI..." 
                class="border border-slate-200 rounded-md p-2 font-bold focus:outline-sky-500" 
              />
              <span class="text-[9px] text-slate-400">Có thể dùng dấu `\n` để xuống dòng trong tiêu đề in.</span>
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-600">Cột cha / Nhóm cột (Nếu có)</label>
              <input 
                v-model="newColParentLabel" 
                type="text" 
                placeholder="VD: GIỜ, DRAP, BỌC, KHĂN CÁC LOẠI..." 
                class="border border-slate-200 rounded-md p-2 font-bold focus:outline-sky-500" 
              />
              <span class="text-[9px] text-slate-400">Để trống nếu cột không thuộc nhóm nào.</span>
            </div>
            
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-600">Độ rộng của cột (Width)</label>
              <input 
                v-model="newColWidth" 
                type="text" 
                placeholder="Ví dụ: 30px hoặc để trống" 
                class="border border-slate-200 rounded-md p-2 font-mono focus:outline-sky-500" 
              />
            </div>

            <button 
              @click="addCol"
              class="mt-2 w-full flex items-center justify-center gap-1.5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition-colors border-none cursor-pointer"
            >
              <Plus :size="14" />
              <span>Thêm cột nháp</span>
            </button>
          </div>

          <!-- Preview Notice -->
          <div class="bg-sky-50/50 border border-sky-100 p-3 rounded-lg flex gap-2">
            <AlertCircle :size="14" class="text-sky-600 shrink-0 mt-0.5" />
            <div class="text-[10px] text-sky-700 leading-normal font-semibold">
              Cột nháp sau khi thêm cần bấm <strong class="text-sky-800">Lưu cấu hình</strong> ở góc dưới để lưu vào cơ sở dữ liệu.
            </div>
          </div>
        </div>

      </div>

      <!-- Action save bottom -->
      <div class="flex items-center justify-end gap-2 bg-white border border-slate-200 p-3 rounded-xl shadow-sm">
        <span class="text-[10px] text-slate-400 italic mr-auto flex items-center gap-1">
          <CheckCircle2 :size="12" class="text-emerald-500" /> Sắp xếp thứ tự cột bằng cách bấm nút mũi tên lên/xuống.
        </span>
        <button 
          @click="savePrintCols"
          class="flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-lg transition-colors border-none cursor-pointer"
        >
          <Save :size="14" />
          <span>Lưu cấu hình mẫu in</span>
        </button>
      </div>

    </div>

  </div>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera */
input[type="color"]::-webkit-color-swatch-wrapper {
  padding: 0;
}
input[type="color"]::-webkit-color-swatch {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
}
</style>
