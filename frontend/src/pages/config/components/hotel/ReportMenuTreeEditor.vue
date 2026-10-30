<script setup>
import { computed, ref } from 'vue'
import { ArrowDown, ArrowUp, ChevronDown, ChevronRight, FileText, Folder, FolderPlus, MoreHorizontal, Plus, Trash2 } from '@lucide/vue'

defineOptions({ name: 'ReportMenuTreeEditor' })

const props = defineProps({
  items: { type: Array, default: () => [] },
  path: { type: Array, default: () => [] },
  selectedPath: { type: Array, default: () => [] },
  collapsed: { type: Set, default: () => new Set() },
})

const emit = defineEmits(['add-group', 'add-report', 'open-report-definition', 'open-report-picker', 'select-target', 'rename-group', 'remove-node', 'move-sibling', 'toggle-collapse'])
const activeMenu = ref('')
const keyFor = path => path.join('.')
const isSelected = path => keyFor(path) === keyFor(props.selectedPath)
const listSize = computed(() => props.items.length)

const selectGroup = path => emit('select-target', path)
const toggleMenu = (path) => {
  const key = keyFor(path)
  activeMenu.value = activeMenu.value === key ? '' : key
}
const runAction = (action, path, ...args) => {
  activeMenu.value = ''
  emit(action, path, ...args)
}
const dropReport = (event, path) => {
  const reportId = Number(event.dataTransfer?.getData('application/x-pms-report-id'))
  if (reportId) emit('add-report', reportId, path)
}
</script>

<template>
  <div class="space-y-1.5" :class="path.length ? 'ml-3 border-l border-slate-200 pl-3' : ''">
    <div v-for="(item, index) in items" :key="item.type === 'group' ? `${keyFor([...path, index])}-${item.name}` : item.report_id"
      :data-report-menu-path="keyFor([...path, index])"
      class="relative rounded-lg border bg-white transition-colors"
      :class="item.type === 'group' && isSelected([...path, index]) ? 'border-sky-300 ring-1 ring-sky-200' : 'border-slate-200'">
      <div class="flex min-h-9 items-center gap-2 px-2.5 py-1.5"
        :class="item.type === 'group' ? 'cursor-pointer bg-slate-50 hover:bg-sky-50/60' : ''"
        @dragover.prevent="item.type === 'group'"
        @drop.prevent.stop="item.type === 'group' && dropReport($event, [...path, index])"
        @dblclick.stop="item.type === 'report' && emit('open-report-definition', item.report_id)"
        @click="item.type === 'group' && selectGroup([...path, index])">
        <button v-if="item.type === 'group'" type="button" class="border-none bg-transparent p-0 text-slate-500" @click.stop="emit('toggle-collapse', [...path, index])">
          <ChevronRight v-if="collapsed.has(keyFor([...path, index]))" class="h-4 w-4" />
          <ChevronDown v-else class="h-4 w-4" />
        </button>
        <span v-else class="w-4" />
        <Folder v-if="item.type === 'group'" class="h-4 w-4 shrink-0 text-sky-600" />
        <FileText v-else class="h-4 w-4 shrink-0 text-slate-400" />
        <span class="min-w-0 flex-1 truncate text-xs" :class="item.type === 'group' ? 'font-semibold text-[#000000D9]' : 'font-normal text-[#000000D9]'">{{ item.name }}</span>
        <span v-if="item.type === 'group'" class="rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">{{ (item.children || []).length }}</span>
        <span v-else class="hidden font-mono text-xs text-slate-400 xl:inline">{{ item.code }}</span>
        <span v-if="item.type === 'report' && item.is_active === false" class="rounded bg-amber-50 px-1 text-xs text-amber-700">Ngừng</span>
        <template v-if="item.type === 'report'">
          <button type="button" class="pms-tree-icon" :disabled="index === 0" title="Đưa lên" @click.stop="emit('move-sibling', [...path, index], -1)"><ArrowUp class="h-3.5 w-3.5" /></button>
          <button type="button" class="pms-tree-icon" :disabled="index === listSize - 1" title="Đưa xuống" @click.stop="emit('move-sibling', [...path, index], 1)"><ArrowDown class="h-3.5 w-3.5" /></button>
          <button type="button" class="pms-tree-icon hover:text-red-600" title="Gỡ báo cáo" @click.stop="emit('remove-node', [...path, index])"><Trash2 class="h-3.5 w-3.5" /></button>
        </template>
        <button v-else type="button" class="pms-tree-icon" title="Thao tác" @click.stop="toggleMenu([...path, index])"><MoreHorizontal class="h-4 w-4" /></button>
      </div>

      <div v-if="activeMenu === keyFor([...path, index])" class="absolute right-2 top-8 z-20 min-w-[168px] rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
        <button v-if="item.type === 'group'" type="button" class="pms-tree-menu-item" @click.stop="runAction('add-group', [...path, index])"><FolderPlus class="h-3.5 w-3.5" /> Thêm Danh Mục Con</button>
        <button v-if="item.type === 'group'" type="button" class="pms-tree-menu-item" @click.stop="runAction('open-report-picker', [...path, index])"><Plus class="h-3.5 w-3.5" /> Thêm Báo Cáo</button>
        <button v-if="item.type === 'group'" type="button" class="pms-tree-menu-item" @click.stop="runAction('rename-group', [...path, index])">Đổi Tên</button>
        <button type="button" class="pms-tree-menu-item" :disabled="index === 0" @click.stop="runAction('move-sibling', [...path, index], -1)">Đưa Lên</button>
        <button type="button" class="pms-tree-menu-item" :disabled="index === listSize - 1" @click.stop="runAction('move-sibling', [...path, index], 1)">Đưa Xuống</button>
        <button type="button" class="pms-tree-menu-item text-red-600 hover:bg-red-50" @click.stop="runAction('remove-node', [...path, index])">{{ item.type === 'group' ? 'Xóa Danh Mục' : 'Gỡ Báo Cáo' }}</button>
      </div>

      <div v-if="item.type === 'group' && !collapsed.has(keyFor([...path, index]))" class="border-t border-slate-100 py-2 pr-2">
        <ReportMenuTreeEditor
          :items="item.children || []"
          :path="[...path, index]"
          :selected-path="selectedPath"
          :collapsed="collapsed"
          @add-group="(...args) => emit('add-group', ...args)"
          @add-report="(...args) => emit('add-report', ...args)"
          @open-report-definition="(...args) => emit('open-report-definition', ...args)"
          @open-report-picker="(...args) => emit('open-report-picker', ...args)"
          @select-target="(...args) => emit('select-target', ...args)"
          @rename-group="(...args) => emit('rename-group', ...args)"
          @remove-node="(...args) => emit('remove-node', ...args)"
          @move-sibling="(...args) => emit('move-sibling', ...args)"
          @toggle-collapse="(...args) => emit('toggle-collapse', ...args)"
        />
        <button type="button" class="ml-3 mt-2 inline-flex h-8 items-center gap-1 rounded-md border border-dashed border-sky-300 bg-sky-50 px-2 text-xs font-semibold text-sky-700 hover:bg-sky-100" @click.stop="emit('add-group', [...path, index])">
          <FolderPlus class="h-3.5 w-3.5" /> Thêm Danh Mục Con
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pms-tree-icon { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 4px; background: transparent; padding: 3px; color: #64748b; cursor: pointer; }
.pms-tree-icon:hover { background: #e2e8f0; color: #334155; }
.pms-tree-menu-item { display: flex; width: 100%; align-items: center; gap: 6px; border: 0; border-radius: 4px; background: transparent; padding: 6px 8px; color: #000000D9; cursor: pointer; font-size: 12px; line-height: 18px; text-align: left; }
.pms-tree-menu-item:hover:not(:disabled) { background: #f1f5f9; }
.pms-tree-menu-item:disabled { cursor: default; opacity: .4; }
</style>
