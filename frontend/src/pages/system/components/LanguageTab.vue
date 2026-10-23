<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { getBuiltinPairs } from '@/utils/i18n'
import { refreshDictionary, SOURCE_LANG } from '@/utils/i18n-dictionary'
import { openLocation } from '@/utils/translator'
import {
  fetchTranslationAreas,
  fetchTranslationEntries,
  saveTranslationEntries,
  createTranslationEntry,
  deleteTranslationEntry,
  syncTranslationDefaults,
  exportTranslations,
  importTranslations,
  fetchAllLanguages,
  createLanguage,
  updateLanguage,
  deleteLanguage
} from '@/services/i18n-service'

const uiStore = useUiStore()
const authStore = useAuthStore()
const canEdit = computed(() => authStore.hasPermission('system.language.edit'))
const canCreate = computed(() => authStore.hasPermission('system.language.create'))
const canDelete = computed(() => authStore.hasPermission('system.language.delete'))

const SEP = ' › '
const UNPLACED = '__unplaced'

function errorText(error, fallback) {
  const errors = error?.response?.data?.errors
  return error?.response?.data?.message || (errors ? Object.values(errors).flat()[0] : '') || fallback
}

// ─── Ngôn ngữ ────────────────────────────────────────────────────────────────
const allLanguages = ref([])
const activeLanguages = computed(() => allLanguages.value.filter(l => l.is_active))
const targetLanguages = computed(() => activeLanguages.value.filter(l => !l.is_default))

async function loadLanguages() {
  const res = await fetchAllLanguages()
  allLanguages.value = res.data.data || []
  if (!previewLang.value || !activeLanguages.value.some(l => l.code === previewLang.value)) {
    previewLang.value = targetLanguages.value[0]?.code || SOURCE_LANG
  }
}

// ─── Cây vị trí ─────────────────────────────────────────────────────────────
const areas = ref([])
const totals = ref({ total: 0, unplaced: 0 })
const selectedArea = ref('')
const expanded = ref({ Menu: true })

const tree = computed(() => {
  const root = { children: new Map() }
  for (const item of areas.value) {
    const parts = item.area.split(SEP)
    let node = root
    let path = ''
    parts.forEach((part, index) => {
      path = index ? path + SEP + part : part
      if (!node.children.has(part)) {
        node.children.set(part, { name: part, path, total: 0, missing: 0, sort: Infinity, children: new Map() })
      }
      node = node.children.get(part)
      node.total += item.total
      node.missing += item.missing
      node.sort = Math.min(node.sort, item.sort_order || 0)
    })
  }
  const toList = (node, depth) => [...node.children.values()]
    .sort((a, b) => {
      if (depth === 0) {
        const rank = n => (n.name === 'Menu' ? 0 : n.name === 'Thanh tiêu đề' ? 1 : 2)
        if (rank(a) !== rank(b)) return rank(a) - rank(b)
      }
      return (a.sort - b.sort) || a.name.localeCompare(b.name, 'vi')
    })
    .map(child => ({ ...child, depth, children: toList(child, depth + 1) }))
  return toList(root, 0)
})

const visibleTree = computed(() => {
  const rows = []
  const visit = nodes => {
    for (const node of nodes) {
      rows.push(node)
      if (node.children.length && expanded.value[node.path]) visit(node.children)
    }
  }
  visit(tree.value)
  return rows
})

function selectArea(path) {
  selectedArea.value = path
  if (path && path !== UNPLACED) expanded.value[path] = true
}

function toggleNode(node) {
  expanded.value[node.path] = !expanded.value[node.path]
}

async function loadAreas() {
  const res = await fetchTranslationAreas()
  areas.value = res.data.data.areas || []
  totals.value = { total: res.data.data.total, unplaced: res.data.data.unplaced }
}

// ─── Danh sách dòng dịch ─────────────────────────────────────────────────────
const entries = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const search = ref('')
const missingLang = ref('')
const onlyModified = ref(false)
const page = ref(1)
const drafts = ref({}) // { [entryId]: { [lang]: text } }

function originalValue(entry, code) {
  return entry.values?.[code]?.value ?? ''
}

function defaultValue(entry, code) {
  if (code === SOURCE_LANG) return entry.source
  return entry.values?.[code]?.default ?? ''
}

function draftValue(entry, code) {
  return drafts.value[entry.id]?.[code] ?? originalValue(entry, code)
}

function setDraft(entry, code, text) {
  if (!drafts.value[entry.id]) drafts.value[entry.id] = {}
  drafts.value[entry.id][code] = text
  if (text === originalValue(entry, code)) {
    delete drafts.value[entry.id][code]
    if (!Object.keys(drafts.value[entry.id]).length) delete drafts.value[entry.id]
  }
}

function isChanged(entry, code) {
  return drafts.value[entry.id]?.[code] !== undefined
}

/** Chữ thực sự hiển thị cho một ngôn ngữ (bản sửa → mặc định → chữ gốc). */
function shownText(entry, code) {
  return draftValue(entry, code) || defaultValue(entry, code) || (code === SOURCE_LANG ? entry.source : '')
}

const changeCount = computed(() => Object.values(drafts.value).reduce((n, langs) => n + Object.keys(langs).length, 0))

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; loadEntries() }, 350)
})
watch([selectedArea, missingLang, onlyModified], () => { page.value = 1; loadEntries() })

async function loadEntries() {
  if (changeCount.value && !(await uiStore.confirm({
    title: 'Bỏ thay đổi chưa lưu?',
    message: `Bạn có ${changeCount.value} ô đã sửa nhưng chưa lưu. Tiếp tục sẽ bỏ các thay đổi này.`,
    confirmText: 'Bỏ thay đổi'
  }))) return
  drafts.value = {}
  loading.value = true
  try {
    const res = await fetchTranslationEntries({
      area: selectedArea.value || undefined,
      search: search.value.trim() || undefined,
      missing: missingLang.value || undefined,
      modified: onlyModified.value ? 1 : undefined,
      page: page.value
    })
    entries.value = res.data.data || []
    meta.value = res.data.meta
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không tải được danh sách bản dịch.'), 'error')
  } finally {
    loading.value = false
  }
}

function goPage(next) {
  if (next < 1 || next > meta.value.last_page) return
  page.value = next
  loadEntries()
}

const saving = ref(false)
async function saveAll() {
  if (!changeCount.value) return
  saving.value = true
  try {
    const items = Object.entries(drafts.value).map(([id, values]) => ({ id: Number(id), values }))
    const res = await saveTranslationEntries(items)
    const updated = new Map((res.data.data || []).map(e => [e.id, e]))
    entries.value = entries.value.map(e => updated.get(e.id) || e)
    drafts.value = {}
    uiStore.showToast(`Đã lưu ${items.length} dòng dịch. Người dùng tải lại trang sẽ thấy chữ mới.`, 'success')
    await Promise.all([loadAreas(), refreshDictionary({ force: true })])
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không lưu được bản dịch.'), 'error')
  } finally {
    saving.value = false
  }
}

function discardAll() {
  drafts.value = {}
}

async function removeEntry(entry) {
  if (!(await uiStore.confirm({
    title: 'Xóa dòng dịch',
    message: `Xóa "${entry.source}" và toàn bộ bản dịch của nó? Nếu chữ này vẫn còn trên màn hình, nó sẽ hiển thị lại chữ gốc.`
  }))) return
  try {
    await deleteTranslationEntry(entry.id)
    entries.value = entries.value.filter(e => e.id !== entry.id)
    delete drafts.value[entry.id]
    await loadAreas()
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xóa được dòng dịch.'), 'error')
  }
}

function viewLocation(entry) {
  const location = entry.locations.find(l => l.route) || entry.locations[0]
  if (!location?.route) {
    uiStore.showToast('Chưa biết chữ này nằm ở màn hình nào. Bật "Chế độ dịch" rồi mở màn hình có chữ này để hệ thống ghi nhận.', 'warning')
    return
  }
  openLocation(location.route, entry.source)
}

// ─── Xem trước menu ────────────────────────────────────────────────────────
const previewLang = ref('')
const isMenuArea = computed(() => selectedArea.value === 'Menu' || selectedArea.value.startsWith('Menu' + SEP))

/** Các mục menu nằm ngay dưới nhánh đang chọn, theo thứ tự thật trên thanh menu. */
const menuPreview = computed(() => {
  if (!isMenuArea.value) return []
  return entries.value.filter(entry => entry.locations.some(l => l.area === selectedArea.value))
})

// ─── Thêm dòng thủ công ─────────────────────────────────────────────────────
const showAddModal = ref(false)
const addForm = ref({ source: '', note: '' })
const addError = ref('')

function openAddModal() {
  addForm.value = { source: '', note: '' }
  addError.value = ''
  showAddModal.value = true
}

async function submitAdd() {
  if (!addForm.value.source.trim()) {
    addError.value = 'Vui lòng nhập chữ gốc tiếng Việt.'
    return
  }
  try {
    const res = await createTranslationEntry({ source: addForm.value.source, note: addForm.value.note || null })
    showAddModal.value = false
    search.value = res.data.data.source
    uiStore.showToast('Đã thêm dòng dịch. Nhập bản dịch rồi bấm Lưu.', 'success')
    await loadAreas()
  } catch (error) {
    addError.value = errorText(error, 'Không thêm được dòng dịch.')
  }
}

// ─── Quản lý ngôn ngữ ───────────────────────────────────────────────────────
const showLangModal = ref(false)
const langForm = ref({ code: '', name: '', flag: '' })
const langError = ref('')

function openLangModal() {
  langForm.value = { code: '', name: '', flag: '' }
  langError.value = ''
  showLangModal.value = true
}

async function addLanguage() {
  langError.value = ''
  if (!langForm.value.code.trim() || !langForm.value.name.trim()) {
    langError.value = 'Vui lòng nhập mã và tên ngôn ngữ.'
    return
  }
  try {
    await createLanguage({
      code: langForm.value.code.trim(),
      name: langForm.value.name.trim(),
      flag: langForm.value.flag.trim() || null,
      is_active: true
    })
    langForm.value = { code: '', name: '', flag: '' }
    await Promise.all([loadLanguages(), refreshDictionary({ force: true })])
    uiStore.showToast('Đã thêm ngôn ngữ. Ngôn ngữ mới đã có trong nút chọn ngôn ngữ trên thanh tiêu đề.', 'success')
  } catch (error) {
    langError.value = errorText(error, 'Không thêm được ngôn ngữ.')
  }
}

async function saveLanguage(lang, patch) {
  try {
    await updateLanguage(lang.id, { name: lang.name, flag: lang.flag, is_active: lang.is_active, sort_order: lang.sort_order, ...patch })
    await Promise.all([loadLanguages(), refreshDictionary({ force: true })])
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không lưu được ngôn ngữ.'), 'error')
    await loadLanguages()
  }
}

async function removeLanguage(lang) {
  if (!(await uiStore.confirm({
    title: 'Xóa ngôn ngữ',
    message: `Xóa ngôn ngữ "${lang.name}" và TOÀN BỘ bản dịch của ngôn ngữ này? Nếu chỉ muốn tạm ẩn, hãy tắt "Đang dùng".`,
    confirmText: 'Xóa'
  }))) return
  try {
    await deleteLanguage(lang.id)
    await Promise.all([loadLanguages(), refreshDictionary({ force: true })])
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xóa được ngôn ngữ.'), 'error')
  }
}

// ─── Import / Export ─────────────────────────────────────────────────────────
const fileInput = ref(null)
const importing = ref(false)

async function doExport() {
  try {
    const res = await exportTranslations({ area: selectedArea.value && selectedArea.value !== UNPLACED ? selectedArea.value : undefined })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `ban-dich-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không xuất được file.'), 'error')
  }
}

async function onImportFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  importing.value = true
  try {
    const res = await importTranslations(file)
    uiStore.showToast(`Đã nhập ${res.data.data.updated} ô bản dịch từ file.`, 'success')
    await Promise.all([loadAreas(), refreshDictionary({ force: true })])
    drafts.value = {}
    await loadEntries()
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không nhập được file.'), 'error')
  } finally {
    importing.value = false
  }
}

// ─── Nạp bản dịch có sẵn trong code (i18n.js) ──────────────────────────────
async function syncBuiltinDefaults() {
  if (!canEdit.value) return
  const pairs = getBuiltinPairs()
  const signature = `${pairs.length}:${pairs.reduce((n, p) => n + p.source.length + Object.values(p.values).join('').length, 0)}`
  try {
    if (localStorage.getItem('pms_i18n_builtin_sig') === signature) return
  } catch {
    // vẫn đồng bộ
  }
  try {
    await syncTranslationDefaults(pairs)
    try { localStorage.setItem('pms_i18n_builtin_sig', signature) } catch { /* bỏ qua */ }
  } catch (error) {
    console.warn('Không đồng bộ được bản dịch có sẵn:', error?.message || error)
  }
}

onMounted(async () => {
  try {
    await syncBuiltinDefaults()
    await Promise.all([loadLanguages(), loadAreas()])
    await loadEntries()
  } catch (error) {
    uiStore.showToast(errorText(error, 'Không tải được dữ liệu ngôn ngữ.'), 'error')
  }
})
</script>

<template>
  <div class="flex-1 flex flex-col overflow-hidden bg-white text-slate-800 text-xs">
    <!-- Thanh trên: ngôn ngữ + thao tác -->
    <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-slate-200 bg-white">
      <h2 class="text-base font-bold mr-2">Ngôn ngữ &amp; Bản dịch</h2>
      <div class="flex flex-wrap items-center gap-1.5" data-no-translate>
        <span
          v-for="lang in allLanguages"
          :key="lang.code"
          class="px-2 py-0.5 rounded-full border text-[11px] font-semibold"
          :class="lang.is_active ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-400 line-through'"
        >{{ lang.flag }} {{ lang.name }}</span>
      </div>
      <button
        v-if="canEdit || canCreate"
        class="px-2.5 py-1 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 font-semibold"
        @click="openLangModal"
      >⚙ Quản lý ngôn ngữ</button>

      <div class="ml-auto flex items-center gap-2">
        <button v-if="canCreate" class="px-2.5 py-1 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 font-semibold" @click="openAddModal">+ Thêm dòng</button>
        <button class="px-2.5 py-1 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 font-semibold" @click="doExport">Xuất Excel (CSV)</button>
        <button
          v-if="canEdit"
          class="px-2.5 py-1 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 font-semibold disabled:opacity-50"
          :disabled="importing"
          @click="fileInput?.click()"
        >{{ importing ? 'Đang nhập...' : 'Nhập từ file' }}</button>
        <input ref="fileInput" type="file" accept=".csv,text/csv" class="hidden" @change="onImportFile" />
      </div>
    </div>

    <div class="flex-1 flex overflow-hidden">
      <!-- Cây vị trí: sắp đúng theo menu / màn hình thật -->
      <aside class="w-64 shrink-0 border-r border-slate-200 overflow-y-auto bg-slate-50/50 py-2" data-no-translate>
        <button
          class="w-full text-left px-3 py-1.5 border-none cursor-pointer flex items-center justify-between"
          :class="selectedArea === '' ? 'bg-sky-100 text-sky-700 font-bold' : 'bg-transparent hover:bg-slate-100'"
          @click="selectArea('')"
        >
          <span>Tất cả</span><span class="text-slate-400">{{ totals.total }}</span>
        </button>

        <div v-for="node in visibleTree" :key="node.path" class="flex items-center" :style="{ paddingLeft: `${8 + node.depth * 14}px` }">
          <button
            class="w-4 shrink-0 border-none bg-transparent cursor-pointer text-slate-400 p-0"
            :class="node.children.length ? '' : 'invisible'"
            @click="toggleNode(node)"
          >{{ expanded[node.path] ? '▾' : '▸' }}</button>
          <button
            class="flex-1 min-w-0 text-left pl-1 pr-3 py-1.5 border-none cursor-pointer flex items-center justify-between gap-2 rounded-l"
            :class="selectedArea === node.path ? 'bg-sky-100 text-sky-700 font-bold' : 'bg-transparent hover:bg-slate-100'"
            :title="node.path"
            @click="selectArea(node.path)"
          >
            <span class="truncate">{{ node.name }}</span>
            <span class="shrink-0 flex items-center gap-1">
              <span v-if="node.missing" class="px-1 rounded bg-amber-100 text-amber-700 text-[10px]" title="Số dòng còn thiếu bản dịch">{{ node.missing }}</span>
              <span class="text-slate-400">{{ node.total }}</span>
            </span>
          </button>
        </div>

        <button
          class="w-full text-left px-3 py-1.5 mt-1 border-none cursor-pointer flex items-center justify-between"
          :class="selectedArea === UNPLACED ? 'bg-sky-100 text-sky-700 font-bold' : 'bg-transparent hover:bg-slate-100 text-slate-500'"
          title="Chữ chưa biết nằm ở màn hình nào (vd bản dịch có sẵn trong code, dòng thêm tay)"
          @click="selectArea(UNPLACED)"
        >
          <span>Chưa xác định vị trí</span><span class="text-slate-400">{{ totals.unplaced }}</span>
        </button>

        <p class="px-3 pt-3 text-[11px] text-slate-400 leading-snug">
          Danh sách vị trí được ghi nhận tự động khi người có quyền bật nút <b>Chế độ dịch</b> trên thanh tiêu đề và mở các màn hình.
        </p>
      </aside>

      <!-- Bảng dịch -->
      <main class="flex-1 flex flex-col overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-slate-100">
          <input
            v-model="search"
            placeholder="Tìm theo chữ đang thấy trên màn hình hoặc bản dịch..."
            class="w-80 h-8 px-2.5 border border-slate-300 rounded-md focus:outline-none focus:border-sky-500"
          />
          <label class="flex items-center gap-1.5">
            Chỉ dòng chưa dịch sang
            <select v-model="missingLang" class="h-8 border border-slate-300 rounded-md px-1.5 bg-white">
              <option value="">— (tắt)</option>
              <option v-for="lang in targetLanguages" :key="lang.code" :value="lang.code">{{ lang.name }}</option>
            </select>
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer select-none">
            <input v-model="onlyModified" type="checkbox" /> Chỉ chữ đã sửa
          </label>
          <span class="ml-auto text-slate-400">{{ meta.total }} dòng</span>
        </div>

        <!-- Xem trước thanh menu -->
        <div v-if="isMenuArea && menuPreview.length" class="px-4 py-2 border-b border-slate-100 bg-slate-50" data-no-translate>
          <div class="flex items-center gap-2 mb-1.5 text-[11px] text-slate-500">
            <span class="font-semibold">Xem trước menu bằng</span>
            <select v-model="previewLang" class="h-6 border border-slate-300 rounded px-1 bg-white text-[11px]">
              <option v-for="lang in activeLanguages" :key="lang.code" :value="lang.code">{{ lang.name }}</option>
            </select>
            <span>— đổi chữ trong bảng là thanh menu dưới đây đổi theo.</span>
          </div>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-1.5 rounded-md text-white text-xs" style="background: var(--pms-custom-theme, #006bdb)">
            <span v-for="entry in menuPreview" :key="entry.id" class="whitespace-nowrap" :class="shownText(entry, previewLang) ? '' : 'opacity-60 italic'">
              {{ shownText(entry, previewLang) || entry.source }}
            </span>
          </div>
        </div>

        <div class="flex-1 overflow-auto">
          <table class="w-full border-collapse">
            <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600">
              <tr>
                <th class="text-left font-bold px-3 py-2 border-b border-slate-200 w-[26%]">Chữ gốc / Vị trí</th>
                <th v-for="lang in activeLanguages" :key="lang.code" class="text-left font-bold px-2 py-2 border-b border-slate-200">
                  <span data-no-translate>{{ lang.flag }} {{ lang.name }}</span>
                  <span v-if="lang.is_default" class="font-normal text-slate-400"> (sửa chữ gốc)</span>
                </th>
                <th class="w-16 border-b border-slate-200"></th>
              </tr>
            </thead>
            <tbody data-no-translate>
              <tr v-if="loading">
                <td :colspan="activeLanguages.length + 2" class="py-8 text-center text-slate-400">Đang tải...</td>
              </tr>
              <tr v-else-if="!entries.length">
                <td :colspan="activeLanguages.length + 2" class="py-8 text-center text-slate-400">
                  Không có dòng nào. Bật <b>Chế độ dịch</b> trên thanh tiêu đề rồi mở các màn hình để hệ thống ghi nhận chữ.
                </td>
              </tr>
              <tr v-for="entry in entries" v-else :key="entry.id" class="align-top hover:bg-sky-50/40 border-b border-slate-100">
                <td class="px-3 py-1.5">
                  <div class="font-semibold text-slate-800 break-words">{{ entry.source }}</div>
                  <div v-if="entry.is_pattern" class="text-[10px] text-violet-600">Mẫu câu có biến — giữ nguyên {…} trong bản dịch</div>
                  <div
                    v-for="loc in entry.locations.slice(0, 2)"
                    :key="loc.area"
                    class="text-[10.5px] text-slate-400 truncate"
                    :title="loc.area"
                  >📍 {{ loc.area }}</div>
                  <div v-if="entry.locations.length > 2" class="text-[10.5px] text-slate-400">+{{ entry.locations.length - 2 }} vị trí khác</div>
                  <div v-if="entry.note" class="text-[10.5px] text-slate-400 italic truncate">{{ entry.note }}</div>
                </td>
                <td v-for="lang in activeLanguages" :key="lang.code" class="px-2 py-1.5">
                  <div class="flex items-center gap-1">
                    <input
                      :value="draftValue(entry, lang.code)"
                      :placeholder="defaultValue(entry, lang.code) || '(chưa dịch)'"
                      :readonly="!canEdit"
                      class="w-full min-w-[120px] h-7 px-2 border rounded focus:outline-none focus:border-sky-500"
                      :class="[
                        isChanged(entry, lang.code) ? 'border-amber-400 bg-amber-50' : 'border-slate-200',
                        !draftValue(entry, lang.code) && !defaultValue(entry, lang.code) && !lang.is_default ? 'bg-rose-50/60' : ''
                      ]"
                      @input="setDraft(entry, lang.code, $event.target.value)"
                    />
                    <button
                      v-if="canEdit && draftValue(entry, lang.code)"
                      class="shrink-0 border-none bg-transparent cursor-pointer text-slate-400 hover:text-sky-600 text-sm"
                      :title="`Khôi phục mặc định: ${defaultValue(entry, lang.code) || '(trống)'}`"
                      @click="setDraft(entry, lang.code, '')"
                    >↺</button>
                  </div>
                </td>
                <td class="px-2 py-1.5 whitespace-nowrap text-right">
                  <button class="border-none bg-transparent cursor-pointer text-sky-600 hover:text-sky-800 text-sm" title="Xem vị trí trên màn hình thật" @click="viewLocation(entry)">👁</button>
                  <button v-if="canDelete" class="border-none bg-transparent cursor-pointer text-slate-400 hover:text-red-600 text-sm ml-1" title="Xóa dòng dịch" @click="removeEntry(entry)">🗑</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Thanh lưu + phân trang -->
        <div class="flex items-center gap-3 px-4 py-2 border-t border-slate-200 bg-white">
          <div class="flex items-center gap-1">
            <button class="px-2 py-1 rounded border border-slate-300 bg-white cursor-pointer disabled:opacity-40" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">‹</button>
            <span class="px-2">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="px-2 py-1 rounded border border-slate-300 bg-white cursor-pointer disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">›</button>
          </div>
          <span class="text-slate-400">Ô để trống = dùng chữ mặc định (chữ mờ). Ô đỏ nhạt = chưa có bản dịch.</span>
          <div v-if="canEdit" class="ml-auto flex items-center gap-2">
            <span v-if="changeCount" class="text-amber-700 font-semibold">{{ changeCount }} ô chưa lưu</span>
            <button v-if="changeCount" class="px-3 py-1.5 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50" @click="discardAll">Hủy thay đổi</button>
            <button
              class="px-4 py-1.5 rounded-md border-none bg-sky-600 text-white font-bold cursor-pointer hover:bg-sky-700 disabled:opacity-40"
              :disabled="!changeCount || saving"
              @click="saveAll"
            >{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
          </div>
        </div>
      </main>
    </div>

    <!-- Modal quản lý ngôn ngữ -->
    <div v-if="showLangModal" class="fixed inset-0 z-[200] bg-black/30 flex items-center justify-center" @click.self="showLangModal = false">
      <div class="w-[560px] max-w-[95vw] bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <h3 class="text-sm font-bold">Quản lý ngôn ngữ</h3>
          <button class="border-none bg-transparent cursor-pointer text-lg text-slate-400 hover:text-slate-700" @click="showLangModal = false">×</button>
        </div>
        <div class="p-4 space-y-3 max-h-[70vh] overflow-y-auto">
          <table class="w-full border-collapse" data-no-translate>
            <thead class="text-slate-500">
              <tr>
                <th class="text-left py-1 font-semibold">Mã</th>
                <th class="text-left py-1 font-semibold">Tên hiển thị</th>
                <th class="text-left py-1 font-semibold w-16">Cờ</th>
                <th class="text-left py-1 font-semibold w-14">Thứ tự</th>
                <th class="text-center py-1 font-semibold">Đang dùng</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lang in allLanguages" :key="lang.id" class="border-t border-slate-100">
                <td class="py-1.5 font-mono">{{ lang.code }}</td>
                <td class="py-1.5 pr-2"><input v-model="lang.name" :disabled="!canEdit" class="w-full h-7 px-2 border border-slate-200 rounded" @change="saveLanguage(lang)" /></td>
                <td class="py-1.5 pr-2"><input v-model="lang.flag" :disabled="!canEdit" class="w-full h-7 px-2 border border-slate-200 rounded" @change="saveLanguage(lang)" /></td>
                <td class="py-1.5 pr-2"><input v-model.number="lang.sort_order" type="number" :disabled="!canEdit" class="w-full h-7 px-1 border border-slate-200 rounded" @change="saveLanguage(lang)" /></td>
                <td class="py-1.5 text-center">
                  <input type="checkbox" :checked="lang.is_active" :disabled="!canEdit || lang.is_default" @change="saveLanguage(lang, { is_active: $event.target.checked })" />
                </td>
                <td class="py-1.5 text-right">
                  <span v-if="lang.is_default" class="text-[10px] text-slate-400">Ngôn ngữ gốc</span>
                  <button v-else-if="canDelete" class="border-none bg-transparent cursor-pointer text-slate-400 hover:text-red-600" @click="removeLanguage(lang)">Xóa</button>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="canCreate" class="border-t border-slate-200 pt-3">
            <div class="font-semibold mb-2">Thêm ngôn ngữ mới</div>
            <div class="flex items-center gap-2">
              <input v-model="langForm.code" placeholder="Mã: ja, km, ko, zh-CN" class="w-36 h-8 px-2 border border-slate-300 rounded" />
              <input v-model="langForm.name" placeholder="Tên: 日本語, ខ្មែរ..." class="flex-1 h-8 px-2 border border-slate-300 rounded" />
              <input v-model="langForm.flag" placeholder="Cờ 🇯🇵" class="w-20 h-8 px-2 border border-slate-300 rounded" />
              <button class="px-3 h-8 rounded-md border-none bg-sky-600 text-white font-bold cursor-pointer hover:bg-sky-700" @click="addLanguage">Thêm</button>
            </div>
            <p v-if="langError" class="mt-2 text-red-600 font-semibold">{{ langError }}</p>
            <p class="mt-2 text-[11px] text-slate-400">Ngôn ngữ mới chưa dịch chữ nào sẽ tạm hiển thị tiếng Việt. Dịch dần trong bảng, bằng Chế độ dịch, hoặc nhập từ file Excel.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal thêm dòng thủ công -->
    <div v-if="showAddModal" class="fixed inset-0 z-[200] bg-black/30 flex items-center justify-center" @click.self="showAddModal = false">
      <div class="w-[480px] max-w-[95vw] bg-white rounded-xl shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <h3 class="text-sm font-bold">Thêm dòng dịch</h3>
          <button class="border-none bg-transparent cursor-pointer text-lg text-slate-400 hover:text-slate-700" @click="showAddModal = false">×</button>
        </div>
        <div class="p-4 space-y-3">
          <label class="block">
            <span class="font-semibold">Chữ gốc tiếng Việt (đúng như trên màn hình / mẫu in)</span>
            <input v-model="addForm.source" class="mt-1 w-full h-8 px-2 border border-slate-300 rounded" placeholder="vd: Còn {n} phòng trống" />
          </label>
          <p class="text-[11px] text-slate-500 leading-snug">
            Câu có phần thay đổi (số, tên...) thì thay phần đó bằng <code>{tên}</code>. Ví dụ <code>Còn {n} phòng trống</code>
            → bản dịch <code>{n} rooms left</code>.
          </p>
          <label class="block">
            <span class="font-semibold">Ghi chú</span>
            <input v-model="addForm.note" class="mt-1 w-full h-8 px-2 border border-slate-300 rounded" />
          </label>
          <p v-if="addError" class="text-red-600 font-semibold">{{ addError }}</p>
          <div class="flex justify-end gap-2">
            <button class="px-3 py-1.5 rounded-md border border-slate-300 bg-white cursor-pointer" @click="showAddModal = false">Hủy</button>
            <button class="px-4 py-1.5 rounded-md border-none bg-sky-600 text-white font-bold cursor-pointer" @click="submitAdd">Thêm</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
