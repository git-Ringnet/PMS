<script setup>
// Dòng 327: cấu hình menu báo cáo theo từng module.
// Bên trái: toàn bộ báo cáo hệ thống. Bên phải: nhóm báo cáo và báo cáo con, kéo thả để sắp xếp.
import { computed, ref, watch } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import {
  ArrowDown, ArrowUp, ChevronDown, ChevronRight, FolderPlus, GripVertical, Plus, RefreshCw, Save, Search, ShieldCheck, Trash2, X
} from '@lucide/vue'
import ReportRoleAccessModal from './ReportRoleAccessModal.vue'
import {
  cloneTree, insertReport, moveNode, placedReportIds, removeNode, toPayload, validateTree
} from '@/utils/report-menu-tree.js'

const props = defineProps({ isOpen: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'changed'])
const authStore = useAuthStore()
const uiStore = useUiStore()
const themeColor = computed(() => authStore.themeColor || 'var(--pms-custom-theme, #006bdb)')

const MODULE_SHORT = { reservation: 'ĐK', frontdesk: 'LT', housekeeping: 'BP' }

const loading = ref(false)
const saving = ref(false)
const locations = ref([])
const reports = ref([])
const trees = ref({})
const savedTrees = ref({})
const activeLocation = ref('reservation')
const search = ref('')
const onlyUnplaced = ref(false)
const selectedGroup = ref(null)
const collapsed = ref(new Set())
const editingGroup = ref(null)
const roleAccessReport = ref(null)
// Ô đổi tên nhóm hiện ra thì focus và chọn sẵn nội dung
const vFocus = { mounted: el => { el.focus(); el.select() } }

const tree = computed(() => trees.value[activeLocation.value] || [])
const placed = computed(() => placedReportIds(tree.value))
const isDirty = (location) => JSON.stringify(toPayload(trees.value[location])) !== JSON.stringify(toPayload(savedTrees.value[location]))
const dirty = computed(() => isDirty(activeLocation.value))
const activeLabel = computed(() => locations.value.find(item => item.code === activeLocation.value)?.label || '')

const normalize = text => String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase()
const filteredReports = computed(() => {
  const keyword = normalize(search.value.trim())
  return reports.value.filter(report => (!keyword || normalize(`${report.name} ${report.code}`).includes(keyword))
    && (!onlyUnplaced.value || !placed.value.has(report.id)))
})

const setTree = (next) => { trees.value = { ...trees.value, [activeLocation.value]: next } }

const load = async () => {
  loading.value = true
  try {
    const response = await http.get('/report-menu')
    const data = response.data.data
    locations.value = data.locations || []
    reports.value = data.reports || []
    trees.value = Object.fromEntries(Object.entries(data.trees || {}).map(([code, items]) => [code, cloneTree(items)]))
    savedTrees.value = Object.fromEntries(Object.entries(data.trees || {}).map(([code, items]) => [code, cloneTree(items)]))
    selectedGroup.value = null
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải cấu hình menu báo cáo', 'error')
  } finally {
    loading.value = false
  }
}

watch(() => props.isOpen, open => {
  if (open) {
    activeLocation.value = 'reservation'
    search.value = ''
    load()
  }
}, { immediate: true })

const confirmDiscard = async (message) => {
  const dirtyLocations = locations.value.filter(item => isDirty(item.code))
  if (!dirtyLocations.length) return true
  return uiStore.confirm({
    title: 'Chưa lưu cấu hình',
    message: `${message} Thay đổi ở ${dirtyLocations.map(item => item.label).join(', ')} sẽ bị bỏ.`,
    confirmText: 'Bỏ thay đổi',
    cancelText: 'Ở lại'
  })
}

const close = async () => {
  if (await confirmDiscard('Đóng màn cấu hình?')) emit('close')
}

const refresh = async () => {
  if (await confirmDiscard('Tải lại dữ liệu?')) load()
}

const switchLocation = (code) => {
  activeLocation.value = code
  selectedGroup.value = null
  editingGroup.value = null
}

// ---- Thao tác nhóm / báo cáo ----
const addGroup = () => {
  const names = new Set(tree.value.filter(item => item.type === 'group').map(item => item.name.toLowerCase()))
  let name = 'Nhóm báo cáo mới'
  for (let i = 2; names.has(name.toLowerCase()); i++) name = `Nhóm báo cáo mới ${i}`
  setTree([...cloneTree(tree.value), { type: 'group', name, children: [] }])
  selectedGroup.value = tree.value.length - 1
  editingGroup.value = tree.value.length - 1
}

const renameGroup = (index, name) => {
  const next = cloneTree(tree.value)
  next[index].name = name
  setTree(next)
}

const deleteGroup = async (index) => {
  const group = tree.value[index]
  if (group.children.length) {
    const ok = await uiStore.confirm({
      title: 'Xóa nhóm báo cáo',
      message: `Xóa nhóm "${group.name}"? ${group.children.length} báo cáo trong nhóm sẽ bị gỡ khỏi menu ${activeLabel.value}.`,
      confirmText: 'Xóa nhóm',
      cancelText: 'Hủy'
    })
    if (!ok) return
  }
  setTree(removeNode(tree.value, { parent: null, index }))
  selectedGroup.value = null
}

const removeReport = (parent, index) => setTree(removeNode(tree.value, { parent, index }))

const moveBy = (parent, index, step) => {
  const list = parent === null ? tree.value : tree.value[parent].children
  const target = index + step
  if (target < 0 || target >= list.length) return
  setTree(moveNode(tree.value, { parent, index }, { parent, index: step > 0 ? index + 2 : index - 1 }))
  if (parent === null && selectedGroup.value === index) selectedGroup.value = target
}

const addReport = (report) => {
  if (placed.value.has(report.id)) return
  const group = selectedGroup.value !== null && tree.value[selectedGroup.value]?.type === 'group' ? selectedGroup.value : null
  setTree(insertReport(tree.value, report, { parent: group, index: Infinity }))
}

const toggleCollapse = (index) => {
  const next = new Set(collapsed.value)
  next.has(index) ? next.delete(index) : next.add(index)
  collapsed.value = next
}

// ---- Kéo thả ----
const dragging = ref(null)
const dropTarget = ref(null)

const startDragNode = (event, parent, index) => {
  dragging.value = { kind: 'node', from: { parent, index }, type: parent === null ? tree.value[index].type : 'report' }
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', 'node')
}
const startDragReport = (event, report) => {
  if (placed.value.has(report.id)) { event.preventDefault(); return }
  dragging.value = { kind: 'report', report }
  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData('text/plain', 'report')
}
const endDrag = () => { dragging.value = null; dropTarget.value = null }
const isGroupDrag = computed(() => dragging.value?.kind === 'node' && dragging.value.type === 'group')

const lowerHalf = event => {
  const rect = event.currentTarget.getBoundingClientRect()
  return event.clientY > rect.top + rect.height / 2
}

// Rê lên một dòng báo cáo (trong nhóm hoặc cấp gốc)
const overReport = (event, parent, index) => {
  if (!dragging.value) return
  if (isGroupDrag.value && parent !== null) {
    dropTarget.value = { parent: null, index: parent + (lowerHalf(event) ? 1 : 0) }
  } else {
    dropTarget.value = { parent, index: index + (lowerHalf(event) ? 1 : 0) }
  }
}
// Rê lên tiêu đề nhóm: báo cáo thì vào đầu nhóm, nhóm thì đặt trước / sau nhóm
const overGroupHeader = (event, index) => {
  if (!dragging.value) return
  dropTarget.value = isGroupDrag.value
    ? { parent: null, index: index + (lowerHalf(event) ? 1 : 0) }
    : { parent: index, index: 0 }
}
const overGroupEnd = (index) => {
  if (!dragging.value) return
  dropTarget.value = isGroupDrag.value
    ? { parent: null, index: index + 1 }
    : { parent: index, index: tree.value[index].children.length }
}
const overRootEnd = () => {
  if (dragging.value) dropTarget.value = { parent: null, index: tree.value.length }
}
const isDropAt = (parent, index) => dropTarget.value && dropTarget.value.parent === parent && dropTarget.value.index === index

const drop = () => {
  const target = dropTarget.value
  const source = dragging.value
  endDrag()
  if (!target || !source) return
  if (source.kind === 'report') {
    setTree(insertReport(tree.value, source.report, target))
  } else {
    setTree(moveNode(tree.value, source.from, target))
  }
}

// ---- Lưu ----
const save = async () => {
  const error = validateTree(tree.value)
  if (error) {
    uiStore.showToast(error, 'warning')
    return
  }
  saving.value = true
  try {
    const location = activeLocation.value
    const response = await http.put(`/report-menu/${location}`, { tree: toPayload(tree.value) })
    const otherDirty = Object.fromEntries(locations.value
      .filter(item => item.code !== location && isDirty(item.code))
      .map(item => [item.code, cloneTree(trees.value[item.code])]))
    await load()
    trees.value = { ...trees.value, ...otherDirty }
    activeLocation.value = location
    emit('changed')
    window.dispatchEvent(new CustomEvent('report-menu-updated'))
    uiStore.showToast(response.data.message || 'Đã lưu cấu hình menu báo cáo', 'success')
  } catch (error) {
    const errors = error.response?.data?.errors
    uiStore.showToast(errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Không thể lưu cấu hình'), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="flex h-[92vh] w-full max-w-[1400px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3">
        <div>
          <h3 class="text-sm font-black text-slate-800">CẤU HÌNH MENU BÁO CÁO</h3>
          <p class="mt-0.5 text-[11px] font-semibold text-slate-400">Kéo báo cáo từ danh sách bên trái vào nhóm bên phải; kéo nhóm / báo cáo để đổi thứ tự hiển thị trên menu Báo cáo của từng module.</p>
        </div>
        <button class="rounded-lg border-none bg-slate-100 p-2 text-slate-500" @click="close"><X class="h-4 w-4" /></button>
      </header>

      <div class="flex min-h-0 flex-1">
        <!-- Bên trái: danh mục báo cáo hệ thống -->
        <aside class="flex w-80 shrink-0 flex-col border-r border-slate-200 bg-slate-50">
          <div class="space-y-2 border-b border-slate-200 p-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-black text-slate-700">Tất cả báo cáo ({{ reports.length }})</span>
              <label class="flex items-center gap-1 text-[10px] font-semibold text-slate-500">
                <input v-model="onlyUnplaced" type="checkbox" class="accent-sky-500" /> Chưa có ở {{ activeLabel }}
              </label>
            </div>
            <div class="relative">
              <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="search" placeholder="Tìm báo cáo theo tên / mã" class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-7 pr-2 text-xs" />
            </div>
            <p class="text-[10px] text-slate-400">
              Bấm <b>+</b> để thêm vào {{ selectedGroup !== null && tree[selectedGroup]?.type === 'group' ? `nhóm "${tree[selectedGroup].name}"` : 'cuối menu' }}, hoặc kéo thả sang bên phải.
            </p>
          </div>
          <div class="flex-1 overflow-y-auto p-2">
            <div v-for="report in filteredReports" :key="report.id"
              :draggable="!placed.has(report.id)"
              @dragstart="startDragReport($event, report)" @dragend="endDrag"
              class="group mb-1 flex items-center gap-2 rounded-lg border bg-white px-2 py-1.5"
              :class="placed.has(report.id) ? 'border-transparent opacity-60' : 'cursor-grab border-slate-200 hover:border-sky-300'">
              <GripVertical class="h-3.5 w-3.5 shrink-0" :class="placed.has(report.id) ? 'text-slate-200' : 'text-slate-400'" />
              <div class="min-w-0 flex-1">
                <div class="truncate text-xs font-semibold text-slate-700" :title="report.name">{{ report.name }}</div>
                <div class="flex items-center gap-1 text-[10px] text-slate-400">
                  <span class="truncate font-mono">{{ report.code }}</span>
                  <span v-for="code in report.locations" :key="code"
                    class="rounded px-1 font-bold"
                    :class="code === activeLocation ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-500'">{{ MODULE_SHORT[code] }}</span>
                  <span v-if="!report.is_active" class="rounded bg-amber-50 px-1 font-bold text-amber-600">Ngừng</span>
                </div>
              </div>
              <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600" title="Quyền xem báo cáo theo vai trò" @click="roleAccessReport = report">
                <ShieldCheck class="h-3.5 w-3.5" />
              </button>
              <button v-if="!placed.has(report.id)" class="rounded border-none bg-transparent p-1 text-slate-400 hover:bg-sky-50 hover:text-sky-600" :title="`Thêm vào menu ${activeLabel}`" @click="addReport(report)">
                <Plus class="h-3.5 w-3.5" />
              </button>
              <span v-else class="px-1 text-[10px] font-bold text-sky-600">Đã xếp</span>
            </div>
            <p v-if="!filteredReports.length" class="py-6 text-center text-xs italic text-slate-400">Không có báo cáo phù hợp</p>
          </div>
        </aside>

        <!-- Bên phải: cấu hình nhóm và báo cáo con theo module -->
        <main class="flex min-w-0 flex-1 flex-col">
          <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-2">
            <div class="flex rounded-lg bg-slate-100 p-0.5">
              <button v-for="location in locations" :key="location.code" @click="switchLocation(location.code)"
                class="rounded-md border-none px-3 py-1.5 text-xs font-bold"
                :class="activeLocation === location.code ? 'bg-white text-slate-800 shadow-xs' : 'bg-transparent text-slate-500'">
                {{ location.label }}<span v-if="isDirty(location.code)" class="ml-1 text-amber-500">●</span>
              </button>
            </div>
            <span v-if="dirty" class="text-[11px] font-semibold text-amber-600">Có thay đổi chưa lưu</span>
            <div class="ml-auto flex gap-2">
              <button class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50" @click="addGroup">
                <FolderPlus class="h-3.5 w-3.5" /> Thêm nhóm
              </button>
              <button class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50" @click="refresh">
                <RefreshCw class="h-3.5 w-3.5" /> Làm mới
              </button>
              <button :disabled="!dirty || saving" class="flex items-center gap-1 rounded-lg border-none px-4 py-1.5 text-xs font-black text-white disabled:opacity-40"
                :style="{ background: themeColor }" @click="save">
                <Save class="h-3.5 w-3.5" /> Lưu cấu hình
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto bg-slate-50/50 p-4" @dragover.prevent @drop.prevent="drop">
            <div v-if="loading" class="py-16 text-center text-xs text-slate-400">Đang tải cấu hình...</div>
            <template v-else>
              <div v-if="!tree.length" class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center text-xs text-slate-400">
                Menu Báo cáo của {{ activeLabel }} đang trống. Bấm “Thêm nhóm” hoặc kéo báo cáo từ bên trái vào đây.
              </div>

              <template v-for="(item, index) in tree" :key="`${activeLocation}-${item.type === 'group' ? `g${index}` : `r${item.report_id}`}`">
                <div v-if="isDropAt(null, index)" class="mb-1 h-1 rounded-full bg-sky-500"></div>

                <!-- Nhóm báo cáo -->
                <section v-if="item.type === 'group'" class="mb-2 rounded-xl border bg-white"
                  :class="selectedGroup === index ? 'border-sky-300 ring-2 ring-sky-100' : 'border-slate-200'">
                  <div draggable="true" @dragstart="startDragNode($event, null, index)" @dragend="endDrag"
                    @dragover.prevent="overGroupHeader($event, index)" @click="selectedGroup = index"
                    class="flex cursor-grab items-center gap-2 rounded-t-xl bg-slate-100/80 px-3 py-2">
                    <GripVertical class="h-4 w-4 text-slate-400" />
                    <button class="border-none bg-transparent p-0 text-slate-500" @click.stop="toggleCollapse(index)">
                      <ChevronRight v-if="collapsed.has(index)" class="h-4 w-4" /><ChevronDown v-else class="h-4 w-4" />
                    </button>
                    <input v-if="editingGroup === index" v-focus :value="item.name"
                      class="flex-1 rounded border border-sky-300 bg-white px-2 py-0.5 text-xs font-bold"
                      @input="renameGroup(index, $event.target.value)" @blur="editingGroup = null" @keydown.enter="editingGroup = null" @click.stop />
                    <span v-else class="flex-1 text-xs font-black text-slate-800" title="Bấm đúp để đổi tên" @dblclick.stop="editingGroup = index">{{ item.name || '(chưa đặt tên)' }}</span>
                    <span class="text-[10px] font-semibold text-slate-400">{{ item.children.length }} báo cáo</span>
                    <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-sky-600" title="Đổi tên nhóm" @click.stop="editingGroup = index">✎</button>
                    <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="index === 0" title="Lên" @click.stop="moveBy(null, index, -1)"><ArrowUp class="h-3.5 w-3.5" /></button>
                    <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="index === tree.length - 1" title="Xuống" @click.stop="moveBy(null, index, 1)"><ArrowDown class="h-3.5 w-3.5" /></button>
                    <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-red-600" title="Xóa nhóm" @click.stop="deleteGroup(index)"><Trash2 class="h-3.5 w-3.5" /></button>
                  </div>

                  <div v-if="!collapsed.has(index)" class="space-y-1 p-2 pl-8">
                    <template v-for="(child, childIndex) in item.children" :key="child.report_id">
                      <div v-if="isDropAt(index, childIndex)" class="h-1 rounded-full bg-sky-500"></div>
                      <div draggable="true" @dragstart.stop="startDragNode($event, index, childIndex)" @dragend="endDrag"
                        @dragover.prevent.stop="overReport($event, index, childIndex)"
                        class="flex cursor-grab items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 hover:border-sky-200">
                        <GripVertical class="h-3.5 w-3.5 text-slate-300" />
                        <span class="flex-1 text-xs text-slate-700">{{ child.name }}
                          <span v-if="child.is_active === false" class="ml-1 rounded bg-amber-50 px-1 text-[10px] font-bold text-amber-600">Ngừng sử dụng</span>
                        </span>
                        <span class="font-mono text-[10px] text-slate-400">{{ child.code }}</span>
                        <button class="rounded border-none bg-transparent p-1 text-slate-300 hover:text-slate-700 disabled:opacity-30" :disabled="childIndex === 0" @click="moveBy(index, childIndex, -1)"><ArrowUp class="h-3 w-3" /></button>
                        <button class="rounded border-none bg-transparent p-1 text-slate-300 hover:text-slate-700 disabled:opacity-30" :disabled="childIndex === item.children.length - 1" @click="moveBy(index, childIndex, 1)"><ArrowDown class="h-3 w-3" /></button>
                        <button class="rounded border-none bg-transparent p-1 text-slate-300 hover:text-red-600" title="Gỡ khỏi menu" @click="removeReport(index, childIndex)"><X class="h-3.5 w-3.5" /></button>
                      </div>
                    </template>
                    <div v-if="isDropAt(index, item.children.length)" class="h-1 rounded-full bg-sky-500"></div>
                    <div @dragover.prevent.stop="overGroupEnd(index)"
                      class="rounded-lg border border-dashed py-1.5 text-center text-[10px]"
                      :class="dragging ? 'border-sky-300 text-sky-500' : 'border-transparent text-slate-300'">
                      {{ item.children.length ? 'Thả vào cuối nhóm' : 'Kéo báo cáo vào nhóm này' }}
                    </div>
                  </div>
                </section>

                <!-- Báo cáo nằm trực tiếp ở menu (không thuộc nhóm) -->
                <div v-else draggable="true" @dragstart="startDragNode($event, null, index)" @dragend="endDrag"
                  @dragover.prevent.stop="overReport($event, null, index)"
                  class="mb-2 flex cursor-grab items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 hover:border-sky-200">
                  <GripVertical class="h-4 w-4 text-slate-400" />
                  <span class="flex-1 text-xs font-semibold text-slate-700">{{ item.name }}
                    <span v-if="item.is_active === false" class="ml-1 rounded bg-amber-50 px-1 text-[10px] font-bold text-amber-600">Ngừng sử dụng</span>
                  </span>
                  <span class="font-mono text-[10px] text-slate-400">{{ item.code }}</span>
                  <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="index === 0" @click="moveBy(null, index, -1)"><ArrowUp class="h-3.5 w-3.5" /></button>
                  <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="index === tree.length - 1" @click="moveBy(null, index, 1)"><ArrowDown class="h-3.5 w-3.5" /></button>
                  <button class="rounded border-none bg-transparent p-1 text-slate-400 hover:text-red-600" title="Gỡ khỏi menu" @click="removeReport(null, index)"><X class="h-3.5 w-3.5" /></button>
                </div>
              </template>

              <div v-if="isDropAt(null, tree.length)" class="mb-1 h-1 rounded-full bg-sky-500"></div>
              <div v-if="tree.length" @dragover.prevent.stop="overRootEnd"
                class="rounded-xl border border-dashed py-3 text-center text-[11px]"
                :class="dragging ? 'border-sky-300 text-sky-500' : 'border-slate-200 text-slate-300'">
                Thả vào đây để đặt ở cuối menu (không thuộc nhóm)
              </div>
            </template>
          </div>
        </main>
      </div>
    </div>

    <ReportRoleAccessModal :report="roleAccessReport" @close="roleAccessReport = null" />
  </div>
</template>
