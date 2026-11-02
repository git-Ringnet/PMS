<script setup>
// Dòng 327: cấu hình menu báo cáo theo từng module.
// Bên trái: toàn bộ báo cáo hệ thống. Bên phải: cây danh mục và báo cáo.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import { FolderPlus, Plus, RefreshCw, Save, Search, ShieldCheck, X } from '@lucide/vue'
import ReportRoleAccessModal from './ReportRoleAccessModal.vue'
import ReportMenuTreeEditor from './ReportMenuTreeEditor.vue'
import {
  addGroup, cloneTree, insertReport, moveSibling, placedReportIds, removeNode, renameGroup, toPayload, validateTree
} from '@/utils/report-menu-tree.js'

const props = defineProps({ isOpen: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'changed', 'open-report-definition'])
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
const selectedGroup = ref([])
const collapsed = ref(new Set())
const roleAccessReport = ref(null)
const reportPickerPath = ref(null)
const reportPickerSearch = ref('')
const reportPickerIds = ref([])

const tree = computed(() => trees.value[activeLocation.value] || [])
const placed = computed(() => placedReportIds(tree.value))
const unplacedReports = computed(() => reports.value.filter(report => !placed.value.has(report.id)))
const isDirty = (location) => JSON.stringify(toPayload(trees.value[location])) !== JSON.stringify(toPayload(savedTrees.value[location]))
const dirty = computed(() => isDirty(activeLocation.value))
const activeLabel = computed(() => locations.value.find(item => item.code === activeLocation.value)?.label || '')
const selectedGroupLabel = computed(() => {
  let items = tree.value
  const names = []
  for (const index of selectedGroup.value) {
    const item = items?.[index]
    if (!item || item.type !== 'group') return 'Gốc menu'
    names.push(item.name)
    items = item.children || []
  }
  return names.length ? names.join(' › ') : 'Gốc menu'
})

const normalize = text => String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase()
const filteredReports = computed(() => {
  const keyword = normalize(search.value.trim())
  return reports.value.filter(report => (!keyword || normalize(`${report.name} ${report.code}`).includes(keyword))
    && (!onlyUnplaced.value || !placed.value.has(report.id)))
})
const pickerReports = computed(() => {
  const keyword = normalize(reportPickerSearch.value.trim())
  return unplacedReports.value.filter(report => !keyword || normalize(`${report.name} ${report.code}`).includes(keyword))
})

const setTree = (next) => { trees.value = { ...trees.value, [activeLocation.value]: next } }
const revealSelectedGroup = async (path) => {
  await nextTick()
  document.querySelector(`[data-report-menu-path="${path.join('.')}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const groupAtPath = (path) => {
  let items = tree.value
  let item = null
  for (const index of path) {
    item = items?.[index]
    items = item?.children || []
  }
  return item
}

const load = async () => {
  loading.value = true
  try {
    const response = await http.get('/report-menu')
    const data = response.data.data
    locations.value = data.locations || []
    reports.value = data.reports || []
    trees.value = Object.fromEntries(Object.entries(data.trees || {}).map(([code, items]) => [code, cloneTree(items)]))
    savedTrees.value = Object.fromEntries(Object.entries(data.trees || {}).map(([code, items]) => [code, cloneTree(items)]))
    selectedGroup.value = []
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
  selectedGroup.value = []
}

// ---- Thao tác nhóm / báo cáo ----
const addMenuGroup = (parentPath = []) => {
  const next = addGroup(tree.value, parentPath)
  const parentItems = parentPath.reduce((items, index) => items[index]?.children || [], next)
  const index = parentItems.length - 1
  const path = [...parentPath, index]
  setTree(next)
  selectedGroup.value = path
  revealSelectedGroup(path)
  uiStore.showToast('Đã thêm danh mục mới. Chọn dấu … để đổi tên.', 'success')
}

const startRenameGroup = async (path) => {
  const name = window.prompt('Tên danh mục báo cáo', groupAtPath(path)?.name || '')
  if (name === null) return
  setTree(renameGroup(tree.value, path, name))
}

const deleteNode = async (path) => {
  const parent = path.slice(0, -1).reduce((items, index) => items[index].children, tree.value)
  const item = parent[path.at(-1)]
  if (item?.type === 'group' && item.children?.length) {
    const ok = await uiStore.confirm({
      title: 'Xóa danh mục báo cáo',
      message: `Xóa danh mục "${item.name}"? Các mục con sẽ được chuyển lên cấp cha.`,
      confirmText: 'Xóa danh mục',
      cancelText: 'Hủy'
    })
    if (!ok) return
  }
  setTree(removeNode(tree.value, path))
  selectedGroup.value = []
}

const addReport = (report) => {
  if (placed.value.has(report.id)) return
  setTree(insertReport(tree.value, report, selectedGroup.value))
}

const addReportsToGroup = (reportIds, targetPath) => {
  const selectedReports = reports.value.filter(report => reportIds.includes(report.id))
  const next = selectedReports.reduce((items, report) => insertReport(items, report, targetPath), tree.value)
  setTree(next)
  selectedGroup.value = targetPath
  uiStore.showToast(`Đã thêm ${selectedReports.length} báo cáo vào danh mục.`, 'success')
}

const openReportPicker = (path) => {
  reportPickerPath.value = path
  reportPickerSearch.value = ''
  reportPickerIds.value = []
}

const togglePickerReport = (reportId, checked) => {
  const ids = new Set(reportPickerIds.value)
  checked ? ids.add(reportId) : ids.delete(reportId)
  reportPickerIds.value = [...ids]
}

const confirmReportPicker = () => {
  if (!reportPickerPath.value || !reportPickerIds.value.length) return
  addReportsToGroup(reportPickerIds.value, reportPickerPath.value)
  reportPickerPath.value = null
}

const addDroppedReport = (reportId, targetPath) => {
  const report = reports.value.find(item => item.id === reportId)
  if (!report || placed.value.has(reportId)) return
  setTree(insertReport(tree.value, report, targetPath))
  selectedGroup.value = targetPath
  uiStore.showToast('Đã thêm báo cáo vào danh mục.', 'success')
}

const startReportDrag = (event, report) => {
  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData('application/x-pms-report-id', String(report.id))
}

const toggleCollapse = (path) => {
  const key = path.join('.')
  const next = new Set(collapsed.value)
  next.has(key) ? next.delete(key) : next.add(key)
  collapsed.value = next
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

const handleKeydown = (event) => {
  if (event.key !== 'Escape') return
  if (reportPickerPath.value) {
    reportPickerPath.value = null
    return
  }
  if (props.isOpen) close()
}

onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="flex h-[92vh] w-[96vw] max-w-[1800px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
      <header class="flex items-center justify-between gap-4 px-5 py-3 text-white" :style="{ background: themeColor }">
        <div>
          <h3 class="text-sm font-semibold">CẤU HÌNH MENU BÁO CÁO</h3>
          <p class="mt-0.5 text-xs text-white/80">Sắp xếp danh mục và báo cáo hiển thị trên menu Báo cáo của từng module.</p>
        </div>
        <button class="rounded-lg border border-white/30 bg-white/15 p-2 text-white hover:bg-white/25" @click="close"><X class="h-4 w-4" /></button>
      </header>

      <div class="flex min-h-0 flex-1">
        <!-- Bên trái: danh mục báo cáo hệ thống -->
        <aside class="flex w-80 shrink-0 flex-col border-r border-slate-200 bg-slate-50">
          <div class="space-y-2 border-b border-slate-200 p-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-black text-slate-700">Tất cả báo cáo ({{ reports.length }})</span>
              <label class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                <input v-model="onlyUnplaced" type="checkbox" class="accent-sky-500" /> Chưa có ở {{ activeLabel }}
              </label>
            </div>
            <div class="relative">
              <Search class="absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="search" placeholder="Tìm báo cáo theo tên / mã" class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-2 text-xs" />
            </div>
            <p class="text-xs text-slate-400">
              Bấm <b>+</b> để thêm vào danh mục đích đang chọn.
            </p>
          </div>
          <div class="flex-1 overflow-y-auto p-2">
            <div v-for="report in filteredReports" :key="report.id"
              :draggable="!placed.has(report.id)"
              @dragstart="!placed.has(report.id) && startReportDrag($event, report)"
              class="group mb-1 flex items-center gap-2 rounded-lg border bg-white px-2 py-1.5"
              :class="placed.has(report.id) ? 'border-transparent opacity-60' : 'cursor-grab border-slate-200 hover:border-sky-300'">
              <div class="min-w-0 flex-1">
                <div class="truncate text-xs font-semibold text-slate-700" :title="report.name">{{ report.name }}</div>
                <div class="flex items-center gap-1 text-xs text-slate-400">
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
              <span v-else class="px-1 text-xs font-semibold text-sky-600">Đã xếp</span>
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
            <span v-if="dirty" class="text-xs font-semibold text-amber-600">Có thay đổi chưa lưu</span>
            <div class="ml-auto flex gap-2">
              <button class="btn-pms-secondary flex items-center gap-1" @click="addMenuGroup(selectedGroup)">
                <FolderPlus class="h-3.5 w-3.5" /> Thêm danh mục
              </button>
              <button class="btn-pms-secondary flex items-center gap-1" @click="refresh">
                <RefreshCw class="h-3.5 w-3.5" /> Làm mới
              </button>
              <button :disabled="!dirty || saving" class="btn-pms-primary flex items-center gap-1 disabled:opacity-40" @click="save">
                <Save class="h-3.5 w-3.5" /> Lưu cấu hình
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto bg-slate-50/50 p-4">
            <div v-if="loading" class="py-16 text-center text-xs text-slate-400">Đang tải cấu hình...</div>
            <template v-else>
              <div class="mb-3 flex items-center justify-between gap-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2">
                <div class="min-w-0">
                  <div class="text-xs font-semibold text-[#000000D9]">Đích Thêm Báo Cáo</div>
                  <div class="truncate text-xs text-sky-700">{{ selectedGroupLabel }}</div>
                </div>
                <button type="button" class="btn-pms-secondary shrink-0" @click="selectedGroup = []">Chọn Gốc Menu</button>
              </div>
              <div v-if="tree.length" class="rounded-lg border border-slate-200 bg-white p-3" @click.self="selectedGroup = []">
                <ReportMenuTreeEditor
                  :items="tree"
                  :selected-path="selectedGroup"
                  :collapsed="collapsed"
                  @add-group="addMenuGroup"
                  @add-report="addDroppedReport"
                  @open-report-definition="reportId => emit('open-report-definition', reportId)"
                  @open-report-picker="openReportPicker"
                  @select-target="path => selectedGroup = path"
                  @rename-group="startRenameGroup"
                  @remove-node="deleteNode"
                  @move-sibling="(path, step) => setTree(moveSibling(tree, path, step))"
                  @toggle-collapse="toggleCollapse"
                />
              </div>
              <div v-else class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center text-xs text-slate-400">
                Menu Báo cáo của {{ activeLabel }} đang trống. Bấm “Thêm danh mục” hoặc thêm báo cáo từ bên trái.
              </div>
            </template>
          </div>
        </main>
      </div>
    </div>

    <div v-if="reportPickerPath" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4" @mousedown.self="reportPickerPath = null">
      <div class="flex max-h-[80vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
        <header class="flex items-center justify-between gap-3 px-4 py-3 text-white" :style="{ background: themeColor }">
          <div>
            <h4 class="text-xs font-semibold">Thêm Báo Cáo Vào Danh Mục</h4>
            <p class="mt-0.5 text-xs text-white/80">{{ groupAtPath(reportPickerPath)?.name }}</p>
          </div>
          <button type="button" class="rounded-lg border border-white/30 bg-white/15 p-2 text-white hover:bg-white/25" @click="reportPickerPath = null"><X class="h-4 w-4" /></button>
        </header>
        <div class="min-h-0 flex-1 p-4">
          <input v-model="reportPickerSearch" class="h-8 w-full rounded-lg border border-slate-200 px-3 text-xs" placeholder="Tìm báo cáo theo tên hoặc mã" />
          <div class="mt-3 max-h-[48vh] space-y-1 overflow-y-auto pr-1">
            <label v-for="report in pickerReports" :key="report.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs hover:border-sky-300 hover:bg-sky-50">
              <input type="checkbox" :checked="reportPickerIds.includes(report.id)" class="accent-sky-600" @change="togglePickerReport(report.id, $event.target.checked)" />
              <span class="min-w-0 flex-1 truncate font-normal text-[#000000D9]">{{ report.name }}</span>
              <span class="font-mono text-xs text-slate-400">{{ report.code }}</span>
            </label>
            <p v-if="!pickerReports.length" class="py-8 text-center text-xs text-slate-400">Không còn báo cáo chưa xếp phù hợp.</p>
          </div>
        </div>
        <footer class="flex items-center justify-end border-t border-slate-200 p-3">
          <button type="button" class="btn-pms-primary" :disabled="!reportPickerIds.length" @click="confirmReportPicker">Thêm {{ reportPickerIds.length || '' }} Báo Cáo</button>
        </footer>
      </div>
    </div>

    <ReportRoleAccessModal :report="roleAccessReport" @close="roleAccessReport = null" />
  </div>
</template>
