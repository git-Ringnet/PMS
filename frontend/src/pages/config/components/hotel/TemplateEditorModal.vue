<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import {
  contentForTextStyle,
  mergeConfiguredStyles,
  normalizeElementTextStyle,
  scopedBlockTextStyleCss,
  styleObjectToCss,
} from '@/utils/report-designer-styles'
import {
  cloneDesignerBlock,
  createDesignerSnapshot,
  moveDesignerHistory,
  parseDesignerSnapshot,
  pushDesignerSnapshot,
} from '@/utils/report-designer-history'
import { 
  X, Save, Play, RefreshCw, FileText, Layers, History, Settings,
  Plus, Trash2, ArrowUp, ArrowDown, ChevronRight, Check, RotateCcw,
  AlignLeft, AlignCenter, AlignRight, AlignJustify, Bold
} from '@lucide/vue'

const props = defineProps({
  templateId: {
    type: Number,
    default: null
  },
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'saved'])

const notifyTemplateSaved = () => {
  window.dispatchEvent(new CustomEvent('pms:report-template-saved', {
    detail: { templateId: Number(props.templateId) }
  }))
}

const uiStore = useUiStore()
const loading = ref(false)
const saving = ref(false)
const template = ref(null)
const activeTab = ref('design') // 'design' | 'preview' | 'css' | 'versions'
const activeTextarea = ref(null)
const editingContent = ref('')
const editorMode = ref('visual') // 'visual' | 'code'

// Active cell editing states for static tables to prevent cursor jumps
const activeCell = ref(null)
const editingCellContent = ref('')
const quickFormatTarget = ref(null)
const selectedStaticCellKeys = ref([])
const staticStyleScope = ref('cell')
const staticStyleClipboard = ref(null)
const staticCellContextMenu = ref(null)

const onCellFocus = (cell) => {
  activeCell.value = cell
  editingCellContent.value = cell.content || ''
}

const onCellBlur = () => {
  activeCell.value = null
  editingCellContent.value = ''
  compileHtml()
}

// Version control state
const versions = ref([])
const note = ref('')
const showSaveModal = ref(false)

// Preview state
const previewHtml = ref('')
const loadingPreview = ref(false)
const draggedBlock = ref(null)

// Editor state
const selectedBand = ref('header') // 'header' | 'detail' | 'footer'
const selectedBlockId = ref(null)
const openCategories = ref({
  hotel: true,
  customer: true,
  booking: true,
  room: true,
  payment: true,
  registration: true,
  lists: true,
  parameters: true,
  summary: true
})
const dataSources = ref([])
const showLeftPanel = ref(true)
const showRightPanel = ref(true)
const canvasViewport = ref(null)
const canvasZoomMode = ref('fit')
const canvasZoom = ref(1)
const blockClipboard = ref(null)
const historyEntries = ref([])
const historyIndex = ref(-1)
const historyReady = ref(false)
const restoringHistory = ref(false)
let historyTimer = null
let generatedIdSequence = 0

const canUndo = computed(() => historyIndex.value > 0)
const canRedo = computed(() => historyIndex.value >= 0 && historyIndex.value < historyEntries.value.length - 1)

const createDesignerId = type => `${String(type || 'block').replace(/[^a-z0-9-]/gi, '-')}_${Date.now()}_${++generatedIdSequence}`

const pageDimensions = computed(() => {
  const dimensions = {
    A4: [210, 297],
    A5: [148, 210],
    Letter: [215.9, 279.4],
    Legal: [215.9, 355.6]
  }
  const [shortSide, longSide] = dimensions[template.value?.page_size] || dimensions.A4
  const landscape = template.value?.page_orientation === 'landscape'
  const widthMm = landscape ? longSide : shortSide
  const heightMm = landscape ? shortSide : longSide
  return {
    width: `${widthMm}mm`,
    height: `${heightMm}mm`,
    widthMm,
    heightMm
  }
})

const pageMargin = (value) => value ?? 10

const canvasFrameStyle = computed(() => ({
  width: `${pageDimensions.value.widthMm * canvasZoom.value}mm`,
  minHeight: `${pageDimensions.value.heightMm * canvasZoom.value}mm`
}))

const canvasStyle = computed(() => ({
  width: pageDimensions.value.width,
  minHeight: pageDimensions.value.height,
  transform: `scale(${canvasZoom.value})`,
  transformOrigin: 'top left',
  paddingTop: `${pageMargin(template.value?.margin_top)}mm`,
  paddingBottom: `${pageMargin(template.value?.margin_bottom)}mm`,
  paddingLeft: `${pageMargin(template.value?.margin_left)}mm`,
  paddingRight: `${pageMargin(template.value?.margin_right)}mm`
}))

const fitCanvasToViewport = () => {
  if (canvasZoomMode.value !== 'fit' || !canvasViewport.value) return

  const pageWidthPx = pageDimensions.value.widthMm * (96 / 25.4)
  const availableWidth = Math.max(0, canvasViewport.value.clientWidth - 48)
  canvasZoom.value = Math.min(1, Math.max(0.2, availableWidth / pageWidthPx))
}

const setCanvasZoom = (value) => {
  if (value === 'fit') {
    canvasZoomMode.value = 'fit'
    nextTick(fitCanvasToViewport)
    return
  }

  canvasZoomMode.value = 'manual'
  canvasZoom.value = value
}

const adjustCanvasZoom = (delta) => {
  canvasZoomMode.value = 'manual'
  canvasZoom.value = Math.min(1.25, Math.max(0.2, Math.round((canvasZoom.value + delta) * 100) / 100))
}

const toggleDesignerPanel = async (panel) => {
  if (panel === 'left') showLeftPanel.value = !showLeftPanel.value
  if (panel === 'right') showRightPanel.value = !showRightPanel.value
  await nextTick()
  fitCanvasToViewport()
}

const colorInputValue = (value, fallback) => /^#[0-9a-f]{6}$/i.test(String(value || ''))
  ? value
  : fallback

// Visual Blocks structure
const blocks = ref({
  header: [],
  detail: [],
  footer: []
})

const designerState = () => ({
  schemaVersion: 2,
  blocks: blocks.value,
  page: {
    size: template.value?.page_size || 'A4',
    orientation: template.value?.page_orientation || 'portrait',
    marginTop: template.value?.margin_top ?? 10,
    marginBottom: template.value?.margin_bottom ?? 10,
    marginLeft: template.value?.margin_left ?? 10,
    marginRight: template.value?.margin_right ?? 10,
  },
  css: template.value?.css || '',
  dataSourceId: template.value?.report_data_source_id || null,
  parameterDefaults: template.value?.parameter_defaults || {},
})

const resetDesignerHistory = () => {
  if (!template.value) return
  clearTimeout(historyTimer)
  historyEntries.value = [createDesignerSnapshot(designerState())]
  historyIndex.value = 0
  historyReady.value = true
}

const recordDesignerHistory = () => {
  if (!historyReady.value || restoringHistory.value || !template.value) return
  const result = pushDesignerSnapshot(
    historyEntries.value,
    historyIndex.value,
    createDesignerSnapshot(designerState()),
  )
  if (!result.changed) return
  historyEntries.value = result.entries
  historyIndex.value = result.index
}

const scheduleDesignerHistory = () => {
  if (!historyReady.value || restoringHistory.value) return
  clearTimeout(historyTimer)
  historyTimer = setTimeout(recordDesignerHistory, 350)
}

const restoreDesignerSnapshot = snapshot => {
  if (!snapshot || !template.value) return
  restoringHistory.value = true
  const state = parseDesignerSnapshot(snapshot)
  blocks.value = {
    header: (state.blocks?.header || []).map(normalizeBlock),
    detail: (state.blocks?.detail || []).map(normalizeBlock),
    footer: (state.blocks?.footer || []).map(normalizeBlock),
  }
  template.value.page_size = state.page?.size || 'A4'
  template.value.page_orientation = state.page?.orientation || 'portrait'
  template.value.margin_top = state.page?.marginTop ?? 10
  template.value.margin_bottom = state.page?.marginBottom ?? 10
  template.value.margin_left = state.page?.marginLeft ?? 10
  template.value.margin_right = state.page?.marginRight ?? 10
  template.value.css = state.css || ''
  template.value.report_data_source_id = state.dataSourceId || null
  template.value.parameter_defaults = state.parameterDefaults || {}
  selectedBlockId.value = null
  quickFormatTarget.value = null
  compileHtml()
  nextTick(() => {
    restoringHistory.value = false
    fitCanvasToViewport()
  })
}

const navigateDesignerHistory = direction => {
  clearTimeout(historyTimer)
  recordDesignerHistory()
  const result = moveDesignerHistory(historyEntries.value, historyIndex.value, direction)
  if (!result.changed) return
  historyIndex.value = result.index
  restoreDesignerSnapshot(result.snapshot)
}

const undoDesigner = () => navigateDesignerHistory(-1)
const redoDesigner = () => navigateDesignerHistory(1)

const getBlockScopeClass = (block) => {
  const safeId = String(block?.id || 'unknown').replace(/[^a-zA-Z0-9_-]/g, '-')
  return `pms-template-block-${safeId}`
}

const collectBlocks = (items) => items.flatMap((block) => {
  const nestedBlocks = block.type === 'columns'
    ? (block.columns || []).flatMap(column => collectBlocks(column.blocks || []))
    : []

  return [block, ...nestedBlocks]
})

const blockTypeLabel = type => ({
  text: 'Văn bản',
  image: 'Hình ảnh',
  table: 'Bảng chi tiết',
  'static-table': 'Bảng tĩnh',
  columns: 'Bố cục cột',
  divider: 'Đường kẻ',
  spacer: 'Khoảng trống',
  shape: 'Hình khối',
  'page-break': 'Ngắt trang',
})[type] || type

const explorerBlocks = band => {
  const flatten = (items, depth = 0) => items.flatMap(block => [
    { block, depth },
    ...(block.columns || []).flatMap(column => flatten(column.blocks || [], depth + 1)),
  ])
  return flatten(blocks.value[band])
}

const scopedBlockFontCss = computed(() => {
  const allBlocks = collectBlocks([
    ...blocks.value.header,
    ...blocks.value.detail,
    ...blocks.value.footer
  ])

  return allBlocks
    .map(block => {
      const selector = `.template-preview-canvas .${getBlockScopeClass(block)}`
      const fontSizeCss = block.style?.fontSize
        ? `${selector}, ${selector} *:not(button) { font-size: ${block.style.fontSize} !important; }`
        : ''
      const configuredTextCss = block.type === 'text'
        ? scopedBlockTextStyleCss(selector, block.style, block.textStyleOverrides)
        : ''
      return [fontSizeCss, configuredTextCss].filter(Boolean).join('\n')
    })
    .filter(Boolean)
    .join('\n')
})

const standardHeaderBandCss = `
.template-preview-canvas .report-header-band,
.template-preview-canvas .report-header { margin: 0 !important; }
.template-preview-canvas .report-header-band .hotel-header,
.template-preview-canvas .report-header .hotel-header {
  display: grid;
  grid-template-columns: 175px 1fr;
  align-items: center;
  min-height: 65px;
}
.template-preview-canvas .report-header-band .hotel-logo,
.template-preview-canvas .report-header .hotel-logo {
  display: flex;
  align-items: center;
  min-height: 55px;
}
.template-preview-canvas .report-header-band .hotel-logo img,
.template-preview-canvas .report-header .hotel-logo img {
  max-width: 120px;
  max-height: 55px;
  object-fit: contain;
}
.template-preview-canvas .report-header-band .hotel-information,
.template-preview-canvas .report-header .hotel-information {
  font-size: 9.5px !important;
  line-height: 1.8 !important;
  text-align: right !important;
}
.template-preview-canvas .report-header-band .hotel-meta,
.template-preview-canvas .report-header .hotel-meta,
.template-preview-canvas .report-header-band .hotel-header > div:not(.hotel-logo),
.template-preview-canvas .report-header .hotel-header > div:not(.hotel-logo) {
  font-size: 9.5px !important;
  line-height: 1.8 !important;
  text-align: right !important;
}
.template-preview-canvas .report-header-band .header-divider,
.template-preview-canvas .report-header .header-divider,
.template-preview-canvas .report-header-band hr,
.template-preview-canvas .report-header hr {
  margin: 0 0 6px !important;
  border: 0 !important;
  border-top: 1px solid #cbd5e1 !important;
}
.template-preview-canvas .report-header-band h1,
.template-preview-canvas .report-header h1 {
  margin: 0 !important;
  text-align: center !important;
  font-size: 18px !important;
  font-weight: 700 !important;
  line-height: 1.25 !important;
}
.template-preview-canvas .report-header-band .period,
.template-preview-canvas .report-header-band .report-period,
.template-preview-canvas .report-header .period,
.template-preview-canvas .report-header .report-period {
  margin: 4px 0 14px !important;
  text-align: center !important;
  font-size: 11px !important;
  font-weight: 400 !important;
  line-height: 1.25 !important;
}
.template-preview-canvas .report-header-band p,
.template-preview-canvas .report-header p {
  margin: 4px 0 14px !important;
  text-align: center !important;
  font-size: 11px !important;
  font-weight: 400 !important;
  line-height: 1.25 !important;
}
`

const scopedTemplateCss = computed(() => {
  const css = template.value?.css || ''
  const canvasReset = '.template-preview-canvas { max-width: none !important; }'
  if (!css.trim()) return `${standardHeaderBandCss}\n${canvasReset}`

  const scopedCss = css.replace(/([^{}]+)\{/g, (match, selectorText) => {
    const selectors = selectorText.trim()
    if (!selectors || selectors.startsWith('@')) return match

    const scoped = selectors.split(',').map(selector => {
      const value = selector.trim()
      if (!value) return value
      if (value === 'body') return '.template-preview-canvas'
      if (value.startsWith('body ')) return `.template-preview-canvas ${value.slice(5)}`
      return `.template-preview-canvas ${value}`
    }).join(', ')

    return `${scoped}{`
  })

  // Legacy templates may set body max-width: 210mm for A4 portrait. The
  // canvas itself represents the saved paper metadata, so it must not be
  // constrained when the user changes to landscape or another paper size.
  return `${scopedCss}\n${standardHeaderBandCss}\n${canvasReset}`
})

const defaultBlockStyle = {
  textAlign: 'left',
  fontSize: '13px',
  paddingTop: '0px',
  paddingBottom: '0px',
  paddingLeft: '0px',
  paddingRight: '0px',
  marginTop: '0px',
  marginBottom: '0px',
  color: '#1e293b',
  fontWeight: 'normal',
  whiteSpace: 'normal',
  backgroundColor: '',
  borderSide: 'all',
  borderStyle: 'none',
  borderWidth: '0px',
  borderColor: '#cbd5e1',
  borderRadius: '0px'
}

const fontSizeOptions = Array.from({ length: 99 }, (_, index) => 1 + index * 0.5)

const selectQuickFormatTarget = target => {
  if (target.kind !== 'static-cell') selectedStaticCellKeys.value = []
  quickFormatTarget.value = target
}

const closeQuickToolbar = () => {
  quickFormatTarget.value = null
  selectedBlockId.value = null
}

const staticCellPosition = target => ({
  row: Math.max(0, target?.block?.rows?.indexOf(target.row) ?? -1) + 1,
  column: Math.max(0, target?.row?.cells?.indexOf(target.cell) ?? -1) + 1,
})

const staticCellKey = (block, row, cell) => `${block?.id || 'static'}:${block?.rows?.indexOf(row) ?? -1}:${row?.cells?.indexOf(cell) ?? -1}`

const isStaticCellSelected = (block, row, cell) => selectedStaticCellKeys.value.includes(staticCellKey(block, row, cell))

const selectStaticCell = (event, block, row, cell) => {
  const key = staticCellKey(block, row, cell)
  const multiSelect = event?.ctrlKey || event?.metaKey
  if (multiSelect) {
    selectedStaticCellKeys.value = isStaticCellSelected(block, row, cell)
      ? selectedStaticCellKeys.value.filter(item => item !== key)
      : [...selectedStaticCellKeys.value, key]
  } else {
    selectedStaticCellKeys.value = [key]
  }
  quickFormatTarget.value = { kind: 'static-cell', block, row, cell }
}

const staticStyleTargets = () => {
  const target = quickFormatTarget.value
  if (!target || target.kind !== 'static-cell') return []
  const rows = target.block.rows || []
  const rowIndex = rows.indexOf(target.row)
  const columnIndex = target.row.cells.indexOf(target.cell)
  if (staticStyleScope.value === 'row') return target.row.cells.map(cell => ({ row: target.row, cell }))
  if (staticStyleScope.value === 'column') return rows.map(row => ({ row, cell: row.cells[columnIndex] })).filter(item => item.cell)
  if (staticStyleScope.value === 'table') return rows.flatMap(row => row.cells.map(cell => ({ row, cell })))
  if (staticStyleScope.value === 'selection') {
    return rows.flatMap(row => row.cells
      .filter(cell => isStaticCellSelected(target.block, row, cell))
      .map(cell => ({ row, cell })))
  }
  return [{ row: target.row, cell: target.cell }]
}

const staticStyleValue = (property) => {
  const target = quickFormatTarget.value
  if (!target?.cell) return ''
  target.cell.style = normalizeStaticTextStyle(target.cell.style)
  return target.cell.style[property] || ''
}

const applyStaticStyle = (property, value) => {
  staticStyleTargets().forEach(({ cell }) => {
    cell.style = normalizeStaticTextStyle(cell.style)
    cell.style[property] = value
  })
  compileHtml()
}

const applyStaticBorder = (property, value) => {
  ;['Top', 'Right', 'Bottom', 'Left'].forEach(side => applyStaticStyle(`border${side}${property}`, value))
}

const resetStaticStyle = () => {
  staticStyleTargets().forEach(({ cell }) => { cell.style = normalizeStaticTextStyle() })
  compileHtml()
}

const copyStaticStyle = () => {
  const target = quickFormatTarget.value
  if (!target?.cell) return
  staticStyleClipboard.value = { ...normalizeStaticTextStyle(target.cell.style) }
  uiStore.showToast('Đã sao chép định dạng ô', 'success')
}

const pasteStaticStyle = () => {
  if (!staticStyleClipboard.value) return
  staticStyleTargets().forEach(({ cell }) => {
    cell.style = { ...normalizeStaticTextStyle(), ...staticStyleClipboard.value }
  })
  compileHtml()
}

const isStaticCellCovered = (block, rowIndex, columnIndex) => (block.rows || []).some((row, sourceRowIndex) => row.cells?.some((cell, sourceColumnIndex) => {
  const rowSpan = Math.max(1, Number(cell.rowspan) || 1)
  const colSpan = Math.max(1, Number(cell.colspan) || 1)
  return (sourceRowIndex !== rowIndex || sourceColumnIndex !== columnIndex)
    && sourceRowIndex <= rowIndex && sourceColumnIndex <= columnIndex
    && sourceRowIndex + rowSpan > rowIndex && sourceColumnIndex + colSpan > columnIndex
}))

const selectedStaticCoordinates = () => {
  const target = quickFormatTarget.value
  if (!target?.block) return []
  return target.block.rows.flatMap((row, rowIndex) => row.cells.map((cell, columnIndex) => ({ row, cell, rowIndex, columnIndex }))
    .filter(item => isStaticCellSelected(target.block, item.row, item.cell)))
}

const mergeStaticCells = () => {
  const target = quickFormatTarget.value
  const selected = selectedStaticCoordinates()
  if (!target?.block || selected.length < 2) return uiStore.showToast('Chọn từ hai ô liền kề để gộp', 'warning')
  const rows = [...new Set(selected.map(item => item.rowIndex))]
  const columns = [...new Set(selected.map(item => item.columnIndex))]
  const isRectangle = selected.length === rows.length * columns.length
    && Math.max(...rows) - Math.min(...rows) + 1 === rows.length
    && Math.max(...columns) - Math.min(...columns) + 1 === columns.length
  if (!isRectangle || selected.some(item => (item.cell.colspan || 1) > 1 || (item.cell.rowspan || 1) > 1)) return uiStore.showToast('Vùng gộp phải là hình chữ nhật, chưa có ô gộp', 'warning')
  const anchor = selected.find(item => item.rowIndex === Math.min(...rows) && item.columnIndex === Math.min(...columns))
  anchor.cell.colspan = columns.length
  anchor.cell.rowspan = rows.length
  selectedStaticCellKeys.value = [staticCellKey(target.block, anchor.row, anchor.cell)]
  quickFormatTarget.value = { kind: 'static-cell', block: target.block, row: anchor.row, cell: anchor.cell }
  compileHtml()
}

const splitStaticCell = () => {
  const cell = quickFormatTarget.value?.cell
  if (!cell || ((cell.colspan || 1) === 1 && (cell.rowspan || 1) === 1)) return
  cell.colspan = 1
  cell.rowspan = 1
  compileHtml()
}

const openStaticCellContextMenu = (event, block, row, cell) => {
  event.preventDefault()
  selectStaticCell(event, block, row, cell)
  staticCellContextMenu.value = { x: event.clientX, y: event.clientY }
}

const closeStaticCellContextMenu = () => { staticCellContextMenu.value = null }

const captureTextSelection = () => {
  const selection = window.getSelection()
  if (selection && !selection.isCollapsed && selection.toString().trim()) {
    quickFormatTarget.value = { kind: 'text-selection' }
  }
}

const applyInlineSelectionStyle = (property, value) => {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return
  const range = selection.getRangeAt(0)
  const editable = range.commonAncestorContainer.parentElement?.closest?.('[contenteditable="true"]')
  if (!editable) return
  const span = document.createElement('span')
  span.style[property] = value
  span.appendChild(range.extractContents())
  range.insertNode(span)
  selection.removeAllRanges()
  const nextRange = document.createRange()
  nextRange.selectNodeContents(span)
  selection.addRange(nextRange)
  editable.dispatchEvent(new Event('input', { bubbles: true }))
}

const quickStyleObject = () => {
  const target = quickFormatTarget.value
  if (!target) return null
  if (target.kind === 'static-cell') {
    target.cell.style = normalizeStaticTextStyle(target.cell.style)
    return target.cell.style
  }
  if (target.kind === 'table-header') {
    target.column.headerStyle = normalizeElementTextStyle(target.column.headerStyle)
    return target.column.headerStyle
  }
  if (target.kind === 'table-cell') {
    target.column.cellStyle = normalizeElementTextStyle(target.column.cellStyle)
    return target.column.cellStyle
  }
  if (target.kind === 'custom-cell') return target.cell
  return null
}

const applyQuickStyle = (property, value) => {
  const style = quickStyleObject()
  if (!style) {
    if (['fontSize', 'color', 'backgroundColor'].includes(property)) {
      return applyInlineSelectionStyle(property, value)
    }
    const command = property === 'color' ? 'foreColor' : property === 'backgroundColor' ? 'hiliteColor' : property
    return formatText(command, value)
  }
  const styleProperty = { bold: 'fontWeight', italic: 'fontStyle', underline: 'textDecoration' }[property] || property
  style[styleProperty] = value || ({ bold: 'bold', italic: 'italic', underline: 'underline' }[property] || '')
  compileHtml()
}

const resetQuickStyle = () => {
  const style = quickStyleObject()
  if (!style) return
  if (quickFormatTarget.value.kind === 'custom-cell') {
    ;['fontSize', 'fontWeight', 'fontStyle', 'textDecoration', 'color', 'backgroundColor'].forEach(property => { style[property] = '' })
  } else {
    Object.assign(style, normalizeElementTextStyle())
  }
  compileHtml()
}

const normalizeStaticTextStyle = normalizeElementTextStyle

const parseGroupHeader = (html) => {
  const match = String(html || '').match(/^\s*<td\b([^>]*)>([\s\S]*)<\/td>\s*$/i)
  if (!match) return { content: '', className: '' }
  const classMatch = match[1].match(/\bclass="([^"]*)"/i)
  return { content: match[2], className: classMatch?.[1] || '' }
}

const normalizeBlock = (block) => {
  const normalized = {
    ...block,
    style: {
      ...defaultBlockStyle,
      ...(block?.style || {})
    }
  }

  if (normalized.type === 'columns' && Array.isArray(normalized.columns)) {
    normalized.columns = normalized.columns.map(column => ({
      ...column,
      blocks: Array.isArray(column.blocks)
        ? column.blocks.map(normalizeBlock)
        : []
    }))
  }

  if (normalized.type === 'static-table') {
    normalized.rows = (Array.isArray(normalized.rows) ? normalized.rows : []).map(row => ({
      ...row,
      style: normalizeStaticTextStyle(row.style),
      cells: (Array.isArray(row.cells) ? row.cells : []).map(cell => ({
        ...cell,
        colspan: Math.max(1, Number(cell.colspan) || 1),
        rowspan: Math.max(1, Number(cell.rowspan) || 1),
        style: normalizeStaticTextStyle(cell.style)
      }))
    }))
  }

  if (normalized.type === 'table') {
    const legacyFooter = normalized.tableFooter?.enabled
      ? [{
          id: `custom_row_${Date.now()}`,
          enabledBy: '',
          cells: [
            { id: `custom_cell_${Date.now()}_1`, type: 'text', content: normalized.tableFooter.label || 'Tổng', colspan: Math.max(1, (normalized.columns?.length || 1) - 1), align: 'right', format: '' },
            { id: `custom_cell_${Date.now()}_2`, type: 'binding', binding: normalized.tableFooter.value || 'summary.row_count', colspan: 1, align: 'center', format: '' }
          ]
        }]
      : []
    normalized.customRows = (Array.isArray(normalized.customRows) ? normalized.customRows : legacyFooter).map((row, rowIndex) => ({
      id: row.id || `custom_row_${rowIndex + 1}`,
      enabledBy: row.enabledBy || '',
      scope: ['table', 'group', 'detail'].includes(row.scope) ? row.scope : 'table',
      level: Math.max(0, Number(row.level) || 0),
      className: row.className || '',
      cells: (Array.isArray(row.cells) ? row.cells : []).map((cell, cellIndex) => ({
        id: cell.id || `custom_cell_${rowIndex + 1}_${cellIndex + 1}`,
        type: cell.type || 'text',
        content: cell.content || '',
        binding: cell.binding || '',
        aggregateField: cell.aggregateField || '',
        colspan: Math.max(1, Number(cell.colspan) || 1),
        align: cell.align || 'left',
        format: cell.format || '',
        className: cell.className || '',
        backgroundColor: cell.backgroundColor || '',
        color: cell.color || '',
        borderColor: cell.borderColor || '',
        fontSize: cell.fontSize || '',
        fontWeight: cell.fontWeight || ''
      }))
    }))
    normalized.columns = (normalized.columns || []).map(column => {
      const value = String(column.value || '')
      const hasNumberModifier = value.endsWith('|number')

      return {
        ...column,
        value: hasNumberModifier ? value.slice(0, -7) : value,
        format: column.format || (hasNumberModifier ? 'number' : ''),
        headerStyle: normalizeElementTextStyle(column.headerStyle),
        cellStyle: normalizeElementTextStyle(column.cellStyle)
      }
    })

    const legacyGroups = [
      { field: normalized.groupBy, header: normalized.groupHeader, enabledBy: normalized.groupEnabledBy },
      { field: normalized.subgroupBy, header: normalized.subgroupHeader },
      { field: normalized.subsubgroupBy, header: normalized.subsubgroupHeader }
    ].filter(group => group.field)
    const sourceGroups = Array.isArray(normalized.groups) && normalized.groups.length
      ? normalized.groups
      : legacyGroups

    normalized.groups = sourceGroups.map((group, index) => {
      const parsed = parseGroupHeader(group.header)
      return {
        id: group.id || `group_${index + 1}`,
        field: group.field || '',
        label: group.label ?? parsed.content ?? '',
        className: group.className ?? parsed.className ?? '',
        headerCells: (Array.isArray(group.headerCells) ? group.headerCells : []).map((cell, cellIndex) => ({
          id: cell.id || `group_cell_${index + 1}_${cellIndex + 1}`,
          type: cell.type || 'text',
          content: cell.content || '',
          binding: cell.binding || '',
          aggregateField: cell.aggregateField || '',
          colspan: Math.max(1, Number(cell.colspan) || 1),
          align: cell.align || 'left',
          format: cell.format || '',
          className: cell.className || '',
          backgroundColor: cell.backgroundColor || '',
          color: cell.color || '',
          borderColor: cell.borderColor || '',
          fontSize: cell.fontSize || '',
          fontWeight: cell.fontWeight || ''
        })),
        enabledBy: group.enabledBy || '',
        sort: String(group.sort || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC'
      }
    })
    normalized.groupBy = normalized.groups[0]?.field || ''
  }

  return normalized
}

// Variables dictionary for Field List
const staticFieldList = {
  hotel: [
    { label: 'Tên khách sạn', value: 'hotel.name' },
    { label: 'Địa chỉ', value: 'hotel.address' },
    { label: 'Số điện thoại', value: 'hotel.phone' },
    { label: 'Email', value: 'hotel.email' },
    { label: 'Logo', value: 'hotel.logo' }
  ],
  customer: [
    { label: 'Tên khách hàng', value: 'customer.name' },
    { label: 'Số điện thoại', value: 'customer.phone' },
    { label: 'Email', value: 'customer.email' },
    { label: 'Số giấy tờ (ID Card)', value: 'customer.id_card' }
  ],
  booking: [
    { label: 'Mã đặt phòng', value: 'booking.code' },
    { label: 'Ngày đến (Check-in)', value: 'booking.checkin_date' },
    { label: 'Ngày đi (Check-out)', value: 'booking.checkout_date' },
    { label: 'Số người lớn', value: 'booking.adults' },
    { label: 'Số trẻ em', value: 'booking.children' },
    { label: 'Số đêm lưu trú', value: 'booking.nights' }
  ],
  room: [
    { label: 'Số phòng', value: 'room.number' },
    { label: 'Hạng phòng', value: 'room.class' },
    { label: 'Giá phòng', value: 'room.price' }
  ],
  payment: [
    { label: 'Tiền cọc', value: 'payment.deposit' },
    { label: 'Tổng số tiền', value: 'payment.total' },
    { label: 'Phương thức', value: 'payment.method' }
  ],
  lists: [
    { label: 'Bảng dịch vụ (Services)', value: 'booking.services', isList: true },
    { label: 'Bảng phòng (Rooms)', value: 'booking.rooms', isList: true },
    { label: 'Bảng thanh toán (Payments)', value: 'booking.payments', isList: true }
  ],
  registration: [
    { label: 'Số đặt phòng (Confirmation No)', value: 'registration.confirmation_no' },
    { label: 'Tên công ty (Company)', value: 'registration.company' },
    { label: 'Họ tên khách (Full name)', value: 'registration.guest_name' },
    { label: 'Số CCCD/Hộ chiếu (ID/Passport No)', value: 'registration.id_passport' },
    { label: 'Quốc tịch (Nationality)', value: 'registration.nationality' },
    { label: 'Địa chỉ email (Email address)', value: 'registration.email' },
    { label: 'Số điện thoại (Phone number)', value: 'registration.phone' },
    { label: 'Ngày đến (Arrival date)', value: 'registration.arrival_date' },
    { label: 'Ngày đi (Departure date)', value: 'registration.departure_date' },
    { label: 'Hạng phòng (Room type)', value: 'registration.room_type' },
    { label: 'Số phòng (Room number)', value: 'registration.room_no' },
    { label: 'Số lượng phòng (No. of Room(s))', value: 'registration.no_rooms' },
    { label: 'Số lượng khách (No. of Guest(s))', value: 'registration.no_guests' },
    { label: 'Giá phòng (Room rate)', value: 'registration.room_rate' },
    { label: 'Số đêm (No. of Night(s))', value: 'registration.no_nights' },
    { label: 'Tiền đặt cọc (Deposit)', value: 'registration.deposit_method' },
    { label: 'Phương thức thanh toán (Payment method)', value: 'registration.payment_method' }
  ]
}

const selectedDataSource = computed(() => {
  return dataSources.value.find(source => source.id === template.value?.report_data_source_id) || null
})

const conditionalParameterOptions = computed(() => {
  return (selectedDataSource.value?.parameter_schema || [])
    .filter(parameter => ['bit', 'boolean', 'bool', 'tinyint'].includes(String(parameter.data_type || '').toLowerCase()))
    .map(parameter => ({
      label: parameter.name,
      value: `parameters.${parameter.name}`
    }))
})

const fieldList = computed(() => {
  if (!selectedDataSource.value) return staticFieldList

  const parameters = (selectedDataSource.value.parameter_schema || []).map(parameter => ({
    label: parameter.name,
    value: `parameters.${parameter.name}`
  }))

  return {
    parameters,
    summary: [
      { label: 'Số dòng kết quả', value: 'summary.row_count' }
    ],
    lists: [
      { label: `Dữ liệu ${selectedDataSource.value.name}`, value: 'rows', isList: true }
    ]
  }
})

const getListFields = (listValue) => {
  if (listValue === 'rows' && selectedDataSource.value) {
    return (selectedDataSource.value.field_schema || []).map(field => ({
      label: field.name,
      value: `row.${field.name}`
    }))
  }
  if (listValue === 'booking.services') {
    return [
      { label: 'Ngày dịch vụ', value: 'service.date' },
      { label: 'Tên dịch vụ', value: 'service.name' },
      { label: 'Phòng', value: 'service.room' },
      { label: 'Đơn giá', value: 'service.price' },
      { label: 'Số lượng', value: 'service.quantity' },
      { label: 'Thành tiền', value: 'service.amount' }
    ]
  }
  if (listValue === 'booking.rooms') {
    return [
      { label: 'Số phòng', value: 'room.room_number' },
      { label: 'Hạng phòng', value: 'room.room_class' },
      { label: 'Giá phòng', value: 'room.price' }
    ]
  }
  if (listValue === 'booking.payments') {
    return [
      { label: 'Ngày', value: 'payment.date' },
      { label: 'Giờ', value: 'payment.time' },
      { label: 'Phương thức', value: 'payment.method' },
      { label: 'Mã Ref', value: 'payment.ref' },
      { label: 'Số tiền', value: 'payment.amount' }
    ]
  }
  return []
}

const groupingFieldValue = (field) => {
  const value = String(field?.value || '')
  return value.startsWith('row.') ? value.substring(4) : value
}

const tableGroups = (block) => Array.isArray(block?.groups) ? block.groups : []
const tableCustomRows = (block) => Array.isArray(block?.customRows) ? block.customRows : []

const customCellContent = (cell, source = 'rows') => {
  const modifier = cell.format === 'number' ? '|number' : ''
  if (cell.type === 'binding') return `{{${cell.binding || 'summary.row_count'}${modifier}}}`
  if (cell.type === 'count') return `{{aggregate.${source}.count${modifier}}}`
  if (cell.type === 'sum') return `{{aggregate.${source}.sum.${cell.aggregateField || 'Total'}${modifier}}}`
  if (cell.type === 'distinct_count') return `{{aggregate.${source}.distinct_count.${cell.aggregateField || 'BookingId'}${modifier}}}`
  return cell.content || ''
}

const addTableCustomRow = (block) => {
  block.customRows = tableCustomRows(block)
  const stamp = Date.now()
  const columnCount = Math.max(1, block.columns?.length || 1)
  block.customRows.push({
    id: `custom_row_${stamp}`,
    enabledBy: '',
    scope: 'table',
    level: 0,
    className: '',
    cells: Array.from({ length: columnCount }, (_, index) => ({
      id: `custom_cell_${stamp}_${index + 1}`,
      type: 'text',
      content: '',
      binding: '',
      aggregateField: '',
      colspan: 1,
      align: block.columns?.[index]?.align || 'left',
      format: '',
      className: '',
      backgroundColor: '',
      color: '',
      borderColor: '',
      fontSize: '',
      fontWeight: ''
    }))
  })
  selectedBlockId.value = block.id
  compileHtml()
}

const addTableCustomCell = (row) => {
  row.cells.push({ id: `custom_cell_${Date.now()}`, type: 'text', content: '', binding: '', aggregateField: '', colspan: 1, align: 'left', format: '', className: '', backgroundColor: '', color: '', borderColor: '', fontSize: '', fontWeight: '' })
  compileHtml()
}

const removeTableCustomRow = (block, index) => {
  block.customRows.splice(index, 1)
  compileHtml()
}

const moveTableCustomRow = (block, index, offset) => {
  const target = index + offset
  if (target < 0 || target >= block.customRows.length) return
  const [row] = block.customRows.splice(index, 1)
  block.customRows.splice(target, 0, row)
  compileHtml()
}

const syncLegacyGroupFields = (block) => {
  const groups = tableGroups(block).filter(group => group.field)
  block.groupBy = groups[0]?.field || ''
  block.groupHeader = groups[0] ? `<td colspan="${Math.max(1, block.columns?.length || 1)}"${groups[0].className ? ` class="${groups[0].className}"` : ''}>${groups[0].label || `Nhóm: {{row.${groups[0].field}}}`}</td>` : ''
  block.groupEnabledBy = groups[0]?.enabledBy || ''
  block.subgroupBy = groups[1]?.field || ''
  block.subgroupHeader = groups[1] ? `<td colspan="${Math.max(1, block.columns?.length || 1)}"${groups[1].className ? ` class="${groups[1].className}"` : ''}>${groups[1].label || `Nhóm: {{row.${groups[1].field}}}`}</td>` : ''
  block.subsubgroupBy = groups[2]?.field || ''
  block.subsubgroupHeader = groups[2] ? `<td colspan="${Math.max(1, block.columns?.length || 1)}"${groups[2].className ? ` class="${groups[2].className}"` : ''}>${groups[2].label || `Nhóm: {{row.${groups[2].field}}}`}</td>` : ''
}

const toggleTableGrouping = (block, enabled) => {
  if (!enabled) {
    block.groups = []
  } else if (!tableGroups(block).length) {
    const field = groupingFieldValue(getListFields(block.dataSource)[0])
    block.groups = [{ id: `group_${Date.now()}`, field, label: `Nhóm: {{row.${field}}}`, className: '', enabledBy: '', sort: 'ASC' }]
  }
  syncLegacyGroupFields(block)
  compileHtml()
}

const addTableGroup = (block) => {
  const used = new Set(tableGroups(block).map(group => group.field))
  const available = getListFields(block.dataSource).map(groupingFieldValue)
  const field = available.find(value => !used.has(value)) || available[0] || ''
  block.groups.push({ id: `group_${Date.now()}`, field, label: `Nhóm: {{row.${field}}}`, className: '', enabledBy: '', sort: 'ASC' })
  syncLegacyGroupFields(block)
  compileHtml()
}

const removeTableGroup = (block, index) => {
  block.groups.splice(index, 1)
  syncLegacyGroupFields(block)
  compileHtml()
}

const moveTableGroup = (block, index, offset) => {
  const target = index + offset
  if (target < 0 || target >= block.groups.length) return
  const [group] = block.groups.splice(index, 1)
  block.groups.splice(target, 0, group)
  syncLegacyGroupFields(block)
  compileHtml()
}

const updateTableGroups = (block) => {
  syncLegacyGroupFields(block)
  compileHtml()
}

const groupHeaderPreview = (group) => {
  return group.label || `Nhóm: ${'{{'}row.${group.field}${'}}'}`
}

const ensureGroupHeaderCells = (block, group) => {
  if (Array.isArray(group.headerCells) && group.headerCells.length) return
  group.headerCells = [{
    id: `group_cell_${Date.now()}`,
    type: 'text',
    content: group.label || `NhÃ³m: {{row.${group.field}}}`,
    binding: '',
    aggregateField: '',
    colspan: Math.max(1, block.columns?.length || 1),
    align: 'left',
    format: '',
    className: group.className || '',
    backgroundColor: '',
    color: '',
    borderColor: '',
    fontSize: '',
    fontWeight: ''
  }]
}

const addGroupHeaderCell = (block, group) => {
  ensureGroupHeaderCells(block, group)
  group.headerCells.push({
    id: `group_cell_${Date.now()}`,
    type: 'text',
    content: '',
    binding: '',
    aggregateField: '',
    colspan: 1,
    align: 'left',
    format: '',
    className: '',
    backgroundColor: '',
    color: '',
    borderColor: '',
    fontSize: '',
    fontWeight: ''
  })
  updateTableGroups(block)
}

const removeGroupHeaderCell = (block, group, index) => {
  group.headerCells.splice(index, 1)
  updateTableGroups(block)
}

const customRowScopeLabel = (scope) => ({
  table: 'Toàn bảng',
  group: 'Theo cấp nhóm',
  detail: 'Theo từng dòng dữ liệu'
}[scope] || 'Toàn bảng')

const loadDataSources = async () => {
  try {
    const response = await http.get('/report-data-sources')
    dataSources.value = (response.data.data || []).filter(source => source.is_active)
  } catch (error) {
    console.error('Không thể tải nguồn dữ liệu báo cáo:', error)
  }
}

const onDataSourceChange = () => {
  const source = selectedDataSource.value
  template.value.parameter_defaults = source ? { ...(source.sample_parameters || {}) } : {}
  compileHtml()
}

// Strip HTML helper to edit plain text
const stripHtml = (html) => {
  if (!html) return ''
  let text = html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<\/div>/gi, '\n')
    .replace(/<\/h[1-6]>/gi, '\n')
    .replace(/<[^>]*>/g, '') // Remove HTML tags
  
  // Decode HTML entities
  const txt = document.createElement('textarea')
  txt.innerHTML = text
  text = txt.value
  
  return text.trim()
}

// Fetch template data
const loadTemplate = async () => {
  loading.value = true
  historyReady.value = false
  try {
    const res = await http.get(`/templates/${props.templateId}`)
    if (res.data && res.data.data) {
      template.value = res.data.data
      // API may return NULL for templates without saved parameter defaults.
      // Keep the editor bindings writable while the data source is loading.
      template.value.parameter_defaults = template.value.parameter_defaults || {}
      
      // Load blocks structure from JSON
      if (template.value.content_json) {
        const json = template.value.content_json
        blocks.value = {
          header: (json.header || []).map(normalizeBlock),
          detail: (json.detail || []).map(normalizeBlock),
          footer: (json.footer || []).map(normalizeBlock)
        }
      } else {
        // Fallback or empty structure
        blocks.value = { header: [], detail: [], footer: [] }
      }
      
      // Select first block if exists
      if (blocks.value.header.length > 0) {
        selectedBlockId.value = blocks.value.header[0].id
        selectedBand.value = 'header'
      } else if (blocks.value.detail.length > 0) {
        selectedBlockId.value = blocks.value.detail[0].id
        selectedBand.value = 'detail'
      } else if (blocks.value.footer.length > 0) {
        selectedBlockId.value = blocks.value.footer[0].id
        selectedBand.value = 'footer'
      } else {
        selectedBlockId.value = null
      }

      // Keep the runtime HTML synchronized with the loaded designer structure.
      // The designer is driven by content_json, while report preview/render uses content_html.
      compileHtml()
      resetDesignerHistory()
      await nextTick()
      fitCanvasToViewport()
    }
  } catch (err) {
    console.error('Lỗi tải mẫu in:', err)
    uiStore.showToast('Không thể tải dữ liệu mẫu in', 'error')
  } finally {
    loading.value = false
    await nextTick()
    fitCanvasToViewport()
  }
}

// Load versions
const loadVersions = async () => {
  try {
    const res = await http.get(`/templates/${props.templateId}/versions`)
    if (res.data && res.data.data) {
      versions.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi tải danh sách phiên bản:', err)
  }
}

// Render Preview — read current unsaved canvas blocks and styling live
const loadPreview = async () => {
  if (!template.value) return
  loadingPreview.value = true
  compileHtml() // Ensure latest blocks layout is compiled to content_html
  try {
    const res = await http.post(`/templates/${props.templateId}/preview`, {
      content_html: template.value.content_html,
      css: template.value.css || '',
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10,
      parameters: template.value.parameter_defaults || {}
    })
    if (res.data && res.data.html) {
      previewHtml.value = res.data.html
    }
  } catch (err) {
    console.error('Lỗi tải preview mẫu:', err)
  } finally {
    loadingPreview.value = false
  }
}

// Selected block getter & setter helper
// Selected block getter & setter helper (with recursive search for sub-blocks inside columns layout)
const findBlockLocation = id => {
  const findIn = (items, band, parent = null) => {
    for (let index = 0; index < items.length; index++) {
      const block = items[index]
      if (block.id === id) return { band, container: items, index, block, parent }
      for (const column of block.columns || []) {
        if (!Array.isArray(column.blocks)) continue
        const nested = findIn(column.blocks, band, block)
        if (nested) return nested
      }
    }
    return null
  }

  for (const band of ['header', 'detail', 'footer']) {
    const found = findIn(blocks.value[band], band)
    if (found) return found
  }
  return null
}

const selectedBlockLocation = computed(() => selectedBlockId.value
  ? findBlockLocation(selectedBlockId.value)
  : null)

const selectedBlock = computed(() => selectedBlockLocation.value?.block || null)

const selectDesignerBlock = (band, block) => {
  selectedBand.value = band
  selectedBlockId.value = block.id
}

const copySelectedBlock = () => {
  if (!selectedBlock.value) return
  blockClipboard.value = cloneDesignerBlock(selectedBlock.value, createDesignerId)
  uiStore.showToast('Đã sao chép phần tử', 'success')
}

const pasteDesignerBlock = () => {
  if (!blockClipboard.value) return
  const copy = cloneDesignerBlock(blockClipboard.value, createDesignerId)
  const location = selectedBlockLocation.value
  const target = location?.band === selectedBand.value ? location.container : blocks.value[selectedBand.value]
  const index = location?.band === selectedBand.value ? location.index + 1 : target.length
  target.splice(index, 0, copy)
  selectedBlockId.value = copy.id
  compileHtml()
}

const duplicateSelectedBlock = () => {
  if (!selectedBlock.value) return
  blockClipboard.value = cloneDesignerBlock(selectedBlock.value, createDesignerId)
  pasteDesignerBlock()
}

const deleteSelectedBlock = () => {
  const location = selectedBlockLocation.value
  if (!location || location.block.locked) return
  location.container.splice(location.index, 1)
  selectedBlockId.value = null
  quickFormatTarget.value = null
  compileHtml()
}

const toggleSelectedBlockVisibility = () => {
  if (!selectedBlock.value) return
  selectedBlock.value.visible = selectedBlock.value.visible === false
  compileHtml()
}

const toggleSelectedBlockLock = () => {
  if (!selectedBlock.value) return
  selectedBlock.value.locked = !selectedBlock.value.locked
  scheduleDesignerHistory()
}

// Columns Layout Sub-block helpers
const addSubBlock = (parentBlock, colIdx, type) => {
  const band = selectedBand.value
  const id = `${band}_sub_${type}_${Date.now()}`
  let subBlock = {
    id,
    type,
    style: {
      textAlign: 'left',
      fontSize: '12px',
      paddingTop: '2px',
      paddingBottom: '2px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '0px',
      marginBottom: '2px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'normal'
    }
  }
  
  if (type === 'text') {
    subBlock.content = 'Nhấp để sửa văn bản...'
  } else if (type === 'image') {
    subBlock.content = ''
    subBlock.imageUrl = ''
  } else if (type === 'spacer') {
    subBlock.height = 15
  } else if (type === 'divider') {
    subBlock.content = '<hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 5px 0;">'
  }
  
  if (!parentBlock.columns[colIdx].blocks) {
    parentBlock.columns[colIdx].blocks = []
  }
  parentBlock.columns[colIdx].blocks.push(subBlock)
  selectedBlockId.value = id
  compileHtml()
  
  if (type === 'image') {
    setTimeout(() => {
      triggerCanvasImageUpload(subBlock)
    }, 150)
  }
}

const deleteSubBlock = (parentBlock, colIdx, subBlockId) => {
  parentBlock.columns[colIdx].blocks = parentBlock.columns[colIdx].blocks.filter(b => b.id !== subBlockId)
  if (selectedBlockId.value === subBlockId) {
    selectedBlockId.value = null
  }
  compileHtml()
}

const moveSubBlock = (parentBlock, colIdx, index, direction) => {
  const subBlocks = parentBlock.columns[colIdx].blocks
  if (direction === 'up' && index > 0) {
    const temp = subBlocks[index]
    subBlocks[index] = subBlocks[index - 1]
    subBlocks[index - 1] = temp
  } else if (direction === 'down' && index < subBlocks.length - 1) {
    const temp = subBlocks[index]
    subBlocks[index] = subBlocks[index + 1]
    subBlocks[index + 1] = temp
  }
  compileHtml()
}

const setColumnWidths = (block, layout) => {
  if (layout === '50-50') {
    block.columns = [
      { width: '50%', blocks: block.columns[0]?.blocks || [] },
      { width: '50%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '30-70') {
    block.columns = [
      { width: '30%', blocks: block.columns[0]?.blocks || [] },
      { width: '70%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '70-30') {
    block.columns = [
      { width: '70%', blocks: block.columns[0]?.blocks || [] },
      { width: '30%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '33-33-33') {
    block.columns = [
      { width: '33.3%', blocks: block.columns[0]?.blocks || [] },
      { width: '33.3%', blocks: block.columns[1]?.blocks || [] },
      { width: '33.4%', blocks: block.columns[2]?.blocks || [] }
    ]
  }
  compileHtml()
}

const uploadingImage = ref(false)

const handleImageUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  const block = selectedBlock.value
  if (!block || block.type !== 'image') return

  const formData = new FormData()
  formData.append('image', file)

  uploadingImage.value = true
  try {
    const res = await http.post('/templates/upload-image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (res.data && res.data.success) {
      block.imageUrl = res.data.url
      compileHtml()
      uiStore.showToast('Tải ảnh lên thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi upload ảnh:', err)
    let errorMsg = 'Không thể tải ảnh lên. Vui lòng thử lại.'
    if (err.response && err.response.data && err.response.data.message) {
      errorMsg = err.response.data.message
    }
    uiStore.showToast(errorMsg, 'error')
  } finally {
    uploadingImage.value = false
    event.target.value = ''
  }
}

const triggerCanvasImageUpload = (block) => {
  const input = document.getElementById('file_input_' + block.id)
  if (input) {
    input.click()
  }
}

const handleCanvasImageUpload = async (event, block) => {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('image', file)
  
  uiStore.showToast('Đang tải ảnh lên...', 'info')
  try {
    const res = await http.post('/templates/upload-image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (res.data && res.data.success) {
      block.imageUrl = res.data.url
      block.content = '' // Clear variables link when custom image is uploaded
      compileHtml()
      uiStore.showToast('Tải ảnh lên thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi upload ảnh:', err)
    uiStore.showToast('Không thể tải ảnh lên. Vui lòng thử lại.', 'error')
  } finally {
    event.target.value = ''
  }
}

// Manage block addition
const addBlock = (type) => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  
  let newBlock = {
    id,
    type,
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '0px',
      marginBottom: '5px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'normal'
    }
  }
  
  if (type === 'text') {
    newBlock.content = 'Nhấp vào đây để sửa nội dung...'
  } else if (type === 'image') {
    newBlock.content = '' // bindings variable or direct image tag
    newBlock.imageUrl = ''
    newBlock.style.textAlign = 'center'
  } else if (type === 'divider') {
    newBlock.content = '<hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 10px 0;">'
  } else if (type === 'spacer') {
    newBlock.height = 20 // mm or px
  } else if (type === 'shape') {
    newBlock.height = 40
    newBlock.style.backgroundColor = '#ffffff'
    newBlock.style.borderStyle = 'solid'
    newBlock.style.borderWidth = '1px'
    newBlock.style.borderColor = '#94a3b8'
    newBlock.style.borderRadius = '0px'
  } else if (type === 'page-break') {
    newBlock.style.paddingTop = '0px'
    newBlock.style.paddingBottom = '0px'
    newBlock.style.marginBottom = '0px'
  } else if (type === 'table') {
    newBlock.isNew = true
    newBlock.dataSource = selectedDataSource.value ? 'rows' : 'booking.services'
    newBlock.tableType = 'dynamic'
    newBlock.rowsCount = 3
    newBlock.colsCount = 2
    newBlock.selectedFields = selectedDataSource.value
      ? getListFields('rows').slice(0, 4).map(field => field.value)
      : ['service.name', 'service.price', 'service.quantity', 'service.amount']
    newBlock.columns = []
    newBlock.customRows = []
    newBlock.style.marginTop = '10px'
    newBlock.style.marginBottom = '10px'
  } else if (type === 'columns') {
    newBlock.columns = [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_col1_txt_${Date.now()}`,
            type: 'text',
            content: 'Cột trái...',
            style: {
              textAlign: 'left',
              fontSize: '12px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_col2_txt_${Date.now() + 1}`,
            type: 'text',
            content: 'Cột phải...',
            style: {
              textAlign: 'left',
              fontSize: '12px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      }
    ]
    newBlock.style.marginTop = '10px'
    newBlock.style.marginBottom = '10px'
  }
  
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
  
  if (type === 'image') {
    setTimeout(() => {
      triggerCanvasImageUpload(newBlock)
    }, 150)
  }
}

// Reorder blocks
const moveBlock = (index, direction) => {
  const band = selectedBand.value
  const bandBlocks = blocks.value[band]
  if (bandBlocks[index]?.locked) return
  if (direction === 'up' && index > 0) {
    const temp = bandBlocks[index]
    bandBlocks[index] = bandBlocks[index - 1]
    bandBlocks[index - 1] = temp
  } else if (direction === 'down' && index < bandBlocks.length - 1) {
    const temp = bandBlocks[index]
    bandBlocks[index] = bandBlocks[index + 1]
    bandBlocks[index + 1] = temp
  }
  compileHtml()
}

const onBlockDragStart = (event, band, index) => {
  if (blocks.value[band]?.[index]?.locked) {
    event.preventDefault()
    return
  }
  draggedBlock.value = { band, index }
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('application/x-pms-report-block', 'true')
}

const onBlockDragEnd = () => {
  draggedBlock.value = null
}

const onBlockDrop = (event, targetBand, targetIndex) => {
  event.preventDefault()
  const field = event.dataTransfer.getData('application/x-pms-report-field')
  if (field) {
    addFieldBlock(targetBand, field, targetIndex)
    return
  }
  if (!draggedBlock.value) return

  const { band: sourceBand, index: sourceIndex } = draggedBlock.value
  const [block] = blocks.value[sourceBand].splice(sourceIndex, 1)
  let insertionIndex = targetIndex
  if (sourceBand === targetBand && sourceIndex < insertionIndex) insertionIndex--
  blocks.value[targetBand].splice(Math.max(0, insertionIndex), 0, block)
  selectedBand.value = targetBand
  selectedBlockId.value = block.id
  draggedBlock.value = null
  compileHtml()
}

const onFieldDragStart = (event, field) => {
  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData('application/x-pms-report-field', field.value)
  event.dataTransfer.setData('text/plain', field.value)
}

const onCanvasBlockDrop = (event, band, index, block) => {
  const field = event.dataTransfer.getData('application/x-pms-report-field')
  if (!field) {
    onBlockDrop(event, band, index)
    return
  }

  event.preventDefault()
  if (block.type === 'text') {
    block.content = `${block.content || ''} {{${field}}}`.trim()
    selectedBlockId.value = block.id
  } else if (block.type === 'table' && field.startsWith('row.')) {
    block.isNew = false
    block.dataSource = 'rows'
    block.columns = block.columns || []
    block.columns.push({ header: field.substring(4), value: field, width: 'auto', align: 'left' })
  } else {
    addFieldBlock(band, field, index + 1)
    return
  }
  compileHtml()
}

const addFieldBlock = (band, field, index = blocks.value[band].length) => {
  const id = `${band}_field_${Date.now()}`
  blocks.value[band].splice(index, 0, {
    id,
    type: 'text',
    content: `{{${field}}}`,
    style: {
      textAlign: 'left', fontSize: '13px', paddingTop: '5px', paddingBottom: '5px',
      marginBottom: '5px', color: '#1e293b', fontWeight: 'normal', whiteSpace: 'normal'
    }
  })
  selectedBand.value = band
  selectedBlockId.value = id
  compileHtml()
}

// Remove block
const deleteBlock = (band, id) => {
  const target = blocks.value[band].find(b => b.id === id)
  if (target?.locked) return
  blocks.value[band] = blocks.value[band].filter(b => b.id !== id)
  if (selectedBlockId.value === id) {
    selectedBlockId.value = null
  }
  compileHtml()
}

const onTextareaFocus = (e) => {
  activeTextarea.value = e.target
}

const insertHTMLAtCursor = (html) => {
  let sel, range;
  if (window.getSelection) {
    sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
      range = sel.getRangeAt(0);
      range.deleteContents();
      
      const el = document.createElement("div");
      el.innerHTML = html;
      const frag = document.createDocumentFragment();
      let node, lastNode;
      while ((node = el.firstChild)) {
        lastNode = frag.appendChild(node);
      }
      range.insertNode(frag);
      
      if (lastNode) {
        range = range.cloneRange();
        range.setStartAfter(lastNode);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    }
  }
}

const formatText = (command, value = null) => {
  document.execCommand(command, false, value)
  // Sync HTML to active contenteditable element by dispatching input event
  const activeEl = document.activeElement
  if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
    activeEl.dispatchEvent(new Event('input', { bubbles: true }))
  }
}

// Insert dynamic variable at cursor position or append to content
const insertVariable = (value) => {
  const block = selectedBlock.value
  if (!block) {
    uiStore.showToast('Vui lòng chọn một khối văn bản hoặc bảng trước khi chèn biến', 'warning')
    return
  }
  
  const placeholder = `{{${value}}}`
  // Inline styled span tag showing as a beautiful blue pill (User-friendly Tag)
  const varHtml = `<span class="pms-variable" style="background-color: #f0f9ff; color: #0369a1; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd; font-family: monospace; font-size: 11px; margin: 0 2px; display: inline-block; user-select: all;" contenteditable="false" data-val="${value}">${placeholder}</span>`
  
  if (block.type === 'text') {
    const activeEl = document.activeElement
    if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
      insertHTMLAtCursor(varHtml)
      block.content = activeEl.innerHTML
    } else if (activeTextarea.value) {
      const textarea = activeTextarea.value
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const text = block.content || ''
      block.content = text.substring(0, start) + placeholder + text.substring(end)
      setTimeout(() => {
        textarea.focus()
        textarea.selectionStart = textarea.selectionEnd = start + placeholder.length
      }, 50)
    } else {
      if (!block.content) block.content = ''
      block.content += ' ' + placeholder
    }
  } else if (block.type === 'image') {
    block.content = value
  }
  compileHtml()
}

// Quick HTML Formatting Tag insertion at cursor
const insertHtmlTag = (openTag, closeTag) => {
  const block = selectedBlock.value
  if (!block || !['text', 'static-table'].includes(block.type)) {
    uiStore.showToast('Vui lòng chọn một khối văn bản hoặc ô bảng trước khi định dạng', 'warning')
    return
  }

  const activeEl = document.activeElement
  if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
    // If editable div has focus, use selection wrap
    let sel = window.getSelection()
    if (sel.rangeAt && sel.rangeCount) {
      let range = sel.getRangeAt(0)
      let selectedText = range.toString()
      let wrapperHtml = openTag + selectedText + closeTag
      insertHTMLAtCursor(wrapperHtml)
      // Dispatch input event to trigger Vue listener to sync model and compile HTML!
      activeEl.dispatchEvent(new Event('input', { bubbles: true }))
    }
  } else if (activeTextarea.value) {
    const textarea = activeTextarea.value
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const text = block.content || ''
    const selectedText = text.substring(start, end)
    const replacement = openTag + selectedText + closeTag
    block.content = text.substring(0, start) + replacement + text.substring(end)
    
    setTimeout(() => {
      textarea.focus()
      textarea.selectionStart = start + openTag.length
      textarea.selectionEnd = start + openTag.length + selectedText.length
    }, 50)
  } else {
    block.content = (block.content || '') + openTag + closeTag
  }
  compileHtml()
}

// Add predefined block: Hotel Info
const addHotelInfoBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '25%',
        blocks: [
          {
            id: `${band}_hotel_logo_${Date.now()}`,
            type: 'image',
            content: 'hotel.logo',
            imageUrl: '',
            style: {
              textAlign: 'center',
              fontSize: '12px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      },
      {
        width: '75%',
        blocks: [
          {
            id: `${band}_hotel_info_${Date.now()}`,
            type: 'text',
            content: '<h2 style="margin:0;font-size:16px;font-weight:bold;">{{hotel.name}}</h2>\n<p style="margin:2px 0;font-size:11px;">Đ/C: {{hotel.address}}</p>\n<p style="margin:2px 0;font-size:11px;">SĐT: {{hotel.phone}} | Email: {{hotel.email}}</p>',
            style: {
              textAlign: 'left',
              fontSize: '12px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '10px',
      marginBottom: '15px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'normal'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Add predefined block: Customer & Booking Info
const addCustomerInfoBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_cust_${Date.now()}`,
            type: 'text',
            content: '<b>Khách hàng:</b> {{customer.name}}<br><b>Số điện thoại:</b> {{customer.phone}}<br><b>Địa chỉ email:</b> {{customer.email}}',
            style: {
              textAlign: 'left',
              fontSize: '11px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_book_${Date.now()}`,
            type: 'text',
            content: '<b>Mã đặt phòng:</b> {{booking.code}}<br><b>Ngày đến:</b> {{booking.checkin_date}}<br><b>Ngày đi:</b> {{booking.checkout_date}} ({{booking.nights}} đêm)',
            style: {
              textAlign: 'left',
              fontSize: '11px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '8px',
      paddingBottom: '8px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '10px',
      marginBottom: '10px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'normal',
      borderBottom: '1px solid #e2e8f0'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Add predefined block: Signature
const addSignatureBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_sig1_${Date.now()}`,
            type: 'text',
            content: '<p style="text-align:center;margin:0;"><b>Khách ký nhận</b><br><span style="font-size:10px;color:#94a3b8;font-style:italic;">(Ký và ghi rõ họ tên)</span></p>',
            style: {
              textAlign: 'center',
              fontSize: '12px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_sig2_${Date.now()}`,
            type: 'text',
            content: '<p style="text-align:center;margin:0;"><b>Nhân viên thực hiện</b><br><span style="font-size:10px;color:#94a3b8;font-style:italic;">(Ký và ghi rõ họ tên)</span></p>',
            style: {
              textAlign: 'center',
              fontSize: '12px',
              paddingTop: '2px',
              paddingBottom: '2px',
              paddingLeft: '0px',
              paddingRight: '0px',
              marginTop: '0px',
              marginBottom: '2px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'normal'
            }
          }
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '30px',
      marginBottom: '40px',
      color: '#1e293b',
      fontWeight: 'normal',
              whiteSpace: 'normal'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Table columns helpers
const addTableColumn = (block) => {
  const fields = getListFields(block.dataSource)
  block.columns.push({
    header: 'Cột mới',
    value: fields[0]?.value || '',
    width: 'auto',
    align: 'left',
    headerStyle: normalizeElementTextStyle(),
    cellStyle: normalizeElementTextStyle()
  })
  compileHtml()
}

const deleteTableColumn = (block, colIdx) => {
  if (block.columns.length <= 1) {
    uiStore.showToast('Không thể xóa cột cuối cùng của bảng', 'warning')
    return
  }
  block.columns.splice(colIdx, 1)
  compileHtml()
}

// Compile blocks JSON structures to plain HTML
const compileHtml = () => {
  let html = ''
  
  // 1. Process Header Band
  html += '<div class="report-header-band">\n'
  blocks.value.header.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  // 2. Process Detail Band
  html += '<div class="report-detail-band">\n'
  blocks.value.detail.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  // 3. Process Footer Band
  html += '<div class="report-footer-band">\n'
  blocks.value.footer.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  if (template.value) {
    template.value.content_html = html
  }
  scheduleDesignerHistory()
}

const compileBlockToHtml = (b) => {
  if (b.visible === false) return ''
  const originalStyles = b.style || {}
  const compiledStyles = { ...originalStyles }
  
  if (compiledStyles.borderSide && compiledStyles.borderSide !== 'all') {
    const side = compiledStyles.borderSide
    const sideCap = side.charAt(0).toUpperCase() + side.slice(1)
    
    if (compiledStyles.borderStyle) {
      compiledStyles[`border${sideCap}Style`] = compiledStyles.borderStyle
      delete compiledStyles.borderStyle
    }
    if (compiledStyles.borderWidth) {
      compiledStyles[`border${sideCap}Width`] = compiledStyles.borderWidth
      delete compiledStyles.borderWidth
    }
    if (compiledStyles.borderColor) {
      compiledStyles[`border${sideCap}Color`] = compiledStyles.borderColor
      delete compiledStyles.borderColor
    }
    
    const otherSides = ['top', 'bottom', 'left', 'right'].filter(s => s !== side)
    otherSides.forEach(s => {
      const sCap = s.charAt(0).toUpperCase() + s.slice(1)
      compiledStyles[`border${sCap}Style`] = 'none'
    })
    
    delete compiledStyles.borderSide
  } else {
    delete compiledStyles.borderSide
  }
  
  const styles = Object.entries(compiledStyles)
    .filter(([k, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${k.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${v}`)
    .join('; ')
  
  const blockScopeClass = getBlockScopeClass(b)
  const fontSizeOverride = b.style?.fontSize
    ? `<style>.${blockScopeClass}, .${blockScopeClass} * { font-size: ${b.style.fontSize} !important; }</style>\n`
    : ''
  const configuredTextOverride = b.type === 'text'
    ? scopedBlockTextStyleCss(`.${blockScopeClass}`, b.style, b.textStyleOverrides)
    : ''
  const configuredTextOverrideTag = configuredTextOverride
    ? `<style>${configuredTextOverride}</style>\n`
    : ''

  const conditionId = String(b.id || createDesignerId('condition')).replace(/[^a-zA-Z0-9_-]/g, '-')
  const conditionOpen = b.visibleWhen
    ? `<section class="pms-conditional-block" data-condition-id="${conditionId}" data-visible-by="${b.visibleWhen}" data-visible-when="${b.visibleWhenMode === 'falsy' ? 'falsy' : 'truthy'}">\n`
    : ''
  let blockHtml = `${conditionOpen}${fontSizeOverride}${configuredTextOverrideTag}<div id="${b.id}" class="${blockScopeClass}" style="${styles}">\n`
  
  if (b.type === 'text' || b.type === 'divider') {
    blockHtml += `  ${b.content || ''}\n`
  } else if (b.type === 'spacer') {
    blockHtml += `  <div style="height: ${b.height || 20}px;"></div>\n`
  } else if (b.type === 'shape') {
    blockHtml += `  <div aria-hidden="true" style="height: ${Math.max(1, Number(b.height) || 40)}px;"></div>\n`
  } else if (b.type === 'page-break') {
    blockHtml += '  <div class="pms-page-break" style="break-after: page; page-break-after: always; height: 0;"></div>\n'
  } else if (b.type === 'image') {
    if (b.imageUrl) {
      blockHtml += `  <img src="${b.imageUrl}" style="max-height: 80px; max-width: 100%;" alt="Image">\n`
    } else if (b.content) {
      blockHtml += `  {{${b.content}}}\n`
    } else {
      blockHtml += '  <!-- empty image block -->\n'
    }
  } else if (b.type === 'table') {
    const tableStyle = b.tableStyle || 'grid'
    let thStyle = 'padding: 6px 8px;'
    let tdStyle = 'padding: 6px 8px;'
    
    if (tableStyle === 'grid') {
      thStyle += ' border-bottom: 2px solid #cbd5e1; border-right: 1px solid #cbd5e1;'
      tdStyle += ' border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;'
    } else if (tableStyle === 'horizontal') {
      thStyle += ' border-bottom: 2px solid #cbd5e1;'
      tdStyle += ' border-bottom: 1px solid #e2e8f0;'
    } else {
      thStyle += ' border: none;'
      tdStyle += ' border: none;'
    }

    blockHtml += '  <table style="width: 100%; border-collapse: collapse; border: none;">\n'
    blockHtml += '    <thead>\n      <tr>\n'
    b.columns.forEach(col => {
      const headerStyle = mergeConfiguredStyles({ textAlign: col.align || 'left', fontWeight: 'bold' }, col.headerStyle)
      blockHtml += `        <th style="${thStyle} width: ${col.width || 'auto'}; ${styleObjectToCss(headerStyle, true)}">${contentForTextStyle(col.header, headerStyle)}</th>\n`
    })
    blockHtml += '      </tr>\n    </thead>\n'
    const groups = tableGroups(b).filter(group => group.field)
    const customRows = tableCustomRows(b)
    const groupCustomRows = customRows.filter(row => row.scope === 'group')
    const detailCustomRows = customRows.filter(row => row.scope === 'detail')
    const tableCustomRowsOnly = customRows.filter(row => row.scope !== 'group' && row.scope !== 'detail')
    const compileCustomRow = (row, className, attributes = '') => {
      const rowClass = row.className ? ` ${row.className}` : ''
      const visibleBy = row.enabledBy ? ` data-visible-by="${row.enabledBy}"` : ''
      let rowHtml = `      <tr class="${className}${rowClass}"${attributes}${visibleBy}>\n`
      row.cells.forEach(cell => {
        const cellClass = cell.className ? ` class="${cell.className}"` : ''
        const resolvedStyle = mergeConfiguredStyles({ textAlign: cell.align || 'left', fontWeight: 'bold' }, customTableCellTextStyle(cell))
        rowHtml += `        <td colspan="${Math.max(1, Number(cell.colspan) || 1)}"${cellClass} style="${tdStyle} ${styleObjectToCss(resolvedStyle, true)}">${contentForTextStyle(customCellContent(cell, b.dataSource || 'rows'), resolvedStyle)}</td>\n`
      })
      return rowHtml + '      </tr>\n'
    }
    if (groups.length) {
      blockHtml += `    <tbody class="pms-grouped-rows" data-source="${b.dataSource}" data-group-configured="1" data-group-by="${groups[0].field}">\n`
      groups.forEach((group, index) => {
        const enabledBy = group.enabledBy ? ` data-group-enabled-by="${group.enabledBy}"` : ''
        const className = group.className ? ` class="${group.className}"` : ''
        const label = group.label || `Nhóm: {{row.${group.field}}}`
        const cells = Array.isArray(group.headerCells) && group.headerCells.length
          ? group.headerCells.map(cell => {
              const cellClass = cell.className ? ` class="${cell.className}"` : ''
              const resolvedStyle = mergeConfiguredStyles({ textAlign: cell.align || 'left', fontWeight: 'bold' }, customTableCellTextStyle(cell))
              return `<td colspan="${Math.max(1, Number(cell.colspan) || 1)}"${cellClass} style="${tdStyle} ${styleObjectToCss(resolvedStyle, true)}">${contentForTextStyle(customCellContent(cell, b.dataSource || 'rows'), resolvedStyle)}</td>`
            }).join('')
          : `<td colspan="${Math.max(1, b.columns.length)}"${className}>${label}</td>`
        blockHtml += `      <tr class="pms-group-header" data-group-level="${index}" data-group-field="${group.field}" data-group-sort="${group.sort || 'ASC'}"${enabledBy}>${cells}</tr>\n`
      })
    } else {
      blockHtml += '    <tbody>\n'
    }

    blockHtml += `      <tr class="pms-detail-row"${groups.length ? '' : ` data-source="${b.dataSource}"`}>\n`
    b.columns.forEach(col => {
      const modifier = col.format === 'number' ? '|number' : ''
      const detailStyle = mergeConfiguredStyles({ textAlign: col.align || 'left' }, col.cellStyle)
      blockHtml += `        <td style="${tdStyle} ${styleObjectToCss(detailStyle, true)}">{{${col.value}${modifier}}}</td>\n`
    })
    blockHtml += '      </tr>\n'
    detailCustomRows.forEach(row => { blockHtml += compileCustomRow(row, 'pms-detail-custom-row') })
    groupCustomRows.forEach(row => { blockHtml += compileCustomRow(row, 'pms-group-custom-row', ` data-group-level="${Math.max(0, Number(row.level) || 0)}"`) })
    const hasOuterGroupCustomRow = groupCustomRows.some(row => Math.max(0, Number(row.level) || 0) === 0)
    if (groups.length && b.groupFooter && !hasOuterGroupCustomRow) blockHtml += `      <tr class="pms-group-footer">${b.groupFooter}</tr>\n`
    blockHtml += '    </tbody>\n'
    if (tableCustomRowsOnly.length) {
      blockHtml += '    <tfoot>\n'
      tableCustomRowsOnly.forEach(row => { blockHtml += compileCustomRow(row, 'pms-custom-row') })
      blockHtml += '    </tfoot>\n'
    }
    blockHtml += '  </table>\n'
  } else if (b.type === 'static-table') {
    const tableStyle = b.tableStyle || 'grid'
    let tdStyle = 'padding: 6px 8px;'
    
    if (tableStyle === 'grid') {
      tdStyle += ' border: 1px solid #cbd5e1;'
    } else if (tableStyle === 'horizontal') {
      tdStyle += ' border-bottom: 1px solid #cbd5e1; border-top: 1px solid #cbd5e1;'
    } else {
      tdStyle += ' border: none;'
    }

    blockHtml += '  <table style="width: 100%; border-collapse: collapse; border: none;">\n'
    blockHtml += '    <tbody>\n'
    if (b.rows) {
      b.rows.forEach((row, rowIndex) => {
        blockHtml += `      <tr style="${styleObjectToCss(row.style, true)}">\n`
        if (row.cells) {
          row.cells.forEach((cell, colIdx) => {
            if (isStaticCellCovered(b, rowIndex, colIdx)) return
            const col = b.columns && b.columns[colIdx] ? b.columns[colIdx] : {}
            const resolvedStyle = mergeConfiguredStyles(row.style, cell.style)
            const cellStyle = styleObjectToCss(resolvedStyle, true)
            const content = contentForTextStyle(cell.content, resolvedStyle)
            const colspan = Math.max(1, Number(cell.colspan) || 1)
            const rowspan = Math.max(1, Number(cell.rowspan) || 1)
            blockHtml += `        <td colspan="${colspan}" rowspan="${rowspan}" style="${tdStyle} width: ${col.width || 'auto'}; ${cellStyle}">${content}</td>\n`
          })
        }
        blockHtml += '      </tr>\n'
      })
    }
    blockHtml += '    </tbody>\n'
    blockHtml += '  </table>\n'
  } else if (b.type === 'columns') {
    blockHtml += '  <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">\n'
    blockHtml += '    <tr style="border: none;">\n'
    b.columns.forEach(col => {
      blockHtml += `      <td style="width: ${col.width || '50%'}; border: none; padding: 0; vertical-align: top;">\n`
      if (col.blocks) {
        col.blocks.forEach(subBlock => {
          blockHtml += compileBlockToHtml(subBlock)
        })
      }
      blockHtml += '      </td>\n'
    })
    blockHtml += '    </tr>\n'
    blockHtml += '  </table>\n'
  }
  
  blockHtml += '</div>\n'
  if (conditionOpen) blockHtml += `</section><!--pms-condition-end:${conditionId}-->\n`
  return blockHtml
}

const getBlockStyle = (b) => {
  if (!b.style) return {}
  const originalStyles = b.style
  const compiledStyles = { ...originalStyles }
  
  if (compiledStyles.borderSide && compiledStyles.borderSide !== 'all') {
    const side = compiledStyles.borderSide
    const sideCap = side.charAt(0).toUpperCase() + side.slice(1)
    
    if (compiledStyles.borderStyle) {
      compiledStyles[`border${sideCap}Style`] = compiledStyles.borderStyle
      delete compiledStyles.borderStyle
    }
    if (compiledStyles.borderWidth) {
      compiledStyles[`border${sideCap}Width`] = compiledStyles.borderWidth
      delete compiledStyles.borderWidth
    }
    if (compiledStyles.borderColor) {
      compiledStyles[`border${sideCap}Color`] = compiledStyles.borderColor
      delete compiledStyles.borderColor
    }
    
    const otherSides = ['top', 'bottom', 'left', 'right'].filter(s => s !== side)
    otherSides.forEach(s => {
      const sCap = s.charAt(0).toUpperCase() + s.slice(1)
      compiledStyles[`border${sCap}Style`] = 'none'
    })
    
    delete compiledStyles.borderSide
  } else {
    delete compiledStyles.borderSide
  }
  
  return compiledStyles
}

const getTableCellStyle = (block, col) => {
  const align = col.align || 'left'
  const borderStyle = block.tableStyle || 'grid'
  const baseFontSize = block.style?.fontSize ? { fontSize: block.style.fontSize } : {}
  
  if (borderStyle === 'horizontal') {
    return {
      textAlign: align,
      borderBottom: '1px solid #cbd5e1',
      borderRight: 'none',
      padding: '6px 8px',
      ...baseFontSize
    }
  } else if (borderStyle === 'none') {
    return {
      textAlign: align,
      border: 'none',
      padding: '6px 8px',
      ...baseFontSize
    }
  } else {
    return {
      textAlign: align,
      border: '1px solid #cbd5e1',
      padding: '6px 8px',
      ...baseFontSize
    }
  }
}

const applyBlockTextStyle = (property, value) => {
  const block = selectedBlock.value
  if (!block) return

  block.style[property] = value
  block.textStyleOverrides = {
    ...(block.textStyleOverrides || {}),
    [property]: true
  }

  if (block.type === 'table') {
    if (property === 'fontSize') {
      (block.columns || []).forEach(col => {
        if (col.headerStyle) col.headerStyle.fontSize = value
        if (col.cellStyle) col.cellStyle.fontSize = value
      })
      ;(block.groups || []).forEach(group => {
        (group.headerCells || []).forEach(cell => {
          if (!cell.style) cell.style = {}
          cell.style.fontSize = value
        })
      })
      ;(block.customRows || []).forEach(row => {
        (row.cells || []).forEach(cell => {
          if (!cell.style) cell.style = {}
          cell.style.fontSize = value
        })
      })
    }
  } else if (block.type === 'static-table') {
    if (property === 'fontSize') {
      (block.rows || []).forEach(row => {
        if (row.style) row.style.fontSize = value
        ;(row.cells || []).forEach(cell => {
          if (!cell.style) cell.style = {}
          cell.style.fontSize = value
        })
      })
    }
  }

  compileHtml()
}

const resetSelectedBlockToolbar = () => {
  const block = selectedBlock.value
  if (!block) return
  block.style = { ...defaultBlockStyle }
  block.textStyleOverrides = {}
  compileHtml()
}

const selectedBlockToolbarLabel = block => ({
  text: 'đoạn chữ',
  image: 'hình ảnh',
  divider: 'đường kẻ',
  shape: 'hình khối',
  spacer: 'khoảng trống',
  'page-break': 'ngắt trang',
  table: 'bảng chi tiết',
  'static-table': 'bảng tĩnh',
  columns: 'bố cục cột',
}[block?.type] || 'phần tử')

const getStaticTableCellStyle = (block, row, cell, column = {}) => styleObjectToCss({
  ...getTableCellStyle(block, column),
  ...mergeConfiguredStyles(row?.style, cell?.style)
}, true)

const staticCellContent = (cell, row) => {
  const content = String(cell?.content || '')
  return contentForTextStyle(content, mergeConfiguredStyles(row?.style, cell?.style))
}

const customTableCellTextStyle = cell => ({
  textAlign: cell.align || 'left',
  backgroundColor: cell.backgroundColor || undefined,
  color: cell.color || undefined,
  borderColor: cell.borderColor || undefined,
  fontSize: cell.fontSize || undefined,
  fontWeight: cell.fontWeight || undefined
})

const getCustomTableCellStyle = (block, cell, column = {}) => styleObjectToCss({
  ...getTableCellStyle(block, column),
  ...customTableCellTextStyle({ ...cell, align: cell.align || column.align || 'left' })
}, true)

const getTableDetailStyle = (block, col) => styleObjectToCss({
  ...getTableCellStyle(block, col),
  ...mergeConfiguredStyles({ textAlign: col.align || 'left' }, col.cellStyle)
}, true)

const getTableHeaderStyle = (block, col) => {
  const align = col.align || 'left'
  const borderStyle = block.tableStyle || 'grid'
  const baseFontSize = block.style?.fontSize ? { fontSize: block.style.fontSize } : {}
  
  if (borderStyle === 'horizontal') {
    return {
      textAlign: align,
      borderBottom: '2px solid #cbd5e1',
      borderRight: 'none',
      padding: '8px',
      ...baseFontSize,
      ...mergeConfiguredStyles({ fontWeight: 'bold' }, col.headerStyle)
    }
  } else if (borderStyle === 'none') {
    return {
      textAlign: align,
      border: 'none',
      padding: '8px',
      ...baseFontSize,
      ...mergeConfiguredStyles({ fontWeight: 'bold' }, col.headerStyle)
    }
  } else {
    return {
      textAlign: align,
      borderBottom: '2px solid #cbd5e1',
      borderRight: '1px solid #cbd5e1',
      padding: '8px',
      ...baseFontSize,
      ...mergeConfiguredStyles({ fontWeight: 'bold' }, col.headerStyle)
    }
  }
}

const onBorderSideChange = (block) => {
  if (!block.style.borderStyle) {
    block.style.borderStyle = 'solid'
  }
  if (!block.style.borderWidth) {
    block.style.borderWidth = '1px'
  }
  if (!block.style.borderColor) {
    block.style.borderColor = '#cbd5e1'
  }
  compileHtml()
}

const confirmTableSetup = (block) => {
  if (block.tableType === 'dynamic') {
    const allFields = getListFields(block.dataSource)
    block.columns = block.selectedFields.map(val => {
      const found = allFields.find(f => f.value === val)
      return {
        header: found ? found.label : 'Cột',
        value: val,
        width: 'auto',
        align: 'left',
        headerStyle: normalizeElementTextStyle(),
        cellStyle: normalizeElementTextStyle()
      }
    })
    if (block.columns.length === 0) {
      block.columns = [{ header: 'Cột mới', value: '', width: 'auto', align: 'left', headerStyle: normalizeElementTextStyle(), cellStyle: normalizeElementTextStyle() }]
    }
    block.customRows = Array.isArray(block.customRows) ? block.customRows : []
  } else {
    block.type = 'static-table'
    block.columns = Array.from({ length: block.colsCount }, () => ({
      width: `${Math.round(100 / block.colsCount)}%`
    }))
    block.rows = Array.from({ length: block.rowsCount }, () => ({
      style: normalizeStaticTextStyle(),
      cells: Array.from({ length: block.colsCount }, () => ({
        content: 'Nội dung ô...',
        style: normalizeStaticTextStyle()
      }))
    }))
  }
  block.isNew = false
  compileHtml()
}

const addStaticRow = (block) => {
  const colsCount = block.columns.length
  block.rows.push({
    style: normalizeStaticTextStyle(),
    cells: Array.from({ length: colsCount }, () => ({
      content: 'Nội dung ô...',
      style: normalizeStaticTextStyle()
    }))
  })
  compileHtml()
}

const deleteStaticRow = (block, rowIndex) => {
  if (block.rows.length <= 1) {
    uiStore.showToast('Không thể xóa hàng duy nhất của bảng', 'warning')
    return
  }
  block.rows.splice(rowIndex, 1)
  compileHtml()
}

const addStaticColumn = (block) => {
  block.columns.push({ width: 'auto' })
  const newColsCount = block.columns.length
  block.columns.forEach(col => {
    col.width = `${Math.round(100 / newColsCount)}%`
  })
  block.rows.forEach(row => {
    row.cells.push({ content: 'Nội dung ô...', style: normalizeStaticTextStyle() })
  })
  compileHtml()
}

const deleteStaticColumn = (block, colIndex) => {
  if (block.columns.length <= 1) {
    uiStore.showToast('Không thể xóa cột duy nhất của bảng', 'warning')
    return
  }
  block.columns.splice(colIndex, 1)
  const newColsCount = block.columns.length
  block.columns.forEach(col => {
    col.width = `${Math.round(100 / newColsCount)}%`
  })
  block.rows.forEach(row => {
    row.cells.splice(colIndex, 1)
  })
  compileHtml()
}

// Quick Save Draft (no version comment required, keeps user in sync)
const saveTemplateDraft = async () => {
  if (!template.value) return
  compileHtml()
  try {
    await http.put(`/templates/${props.templateId}`, {
      group: template.value.group,
      name: template.value.name,
      report: template.value.report,
      report_data_source_id: template.value.report_data_source_id || null,
      parameter_defaults: template.value.parameter_defaults || {},
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10,
      content_json: blocks.value,
      content_html: template.value.content_html,
      css: template.value.css || '',
      note: 'Bản nháp tự động',
      save_mode: 'draft'
    })
  } catch (err) {
    console.error('Lỗi lưu nháp:', err)
  }
}

// Final Save (with version notes)
const saveTemplateWithVersion = async () => {
  if (!template.value) return
  saving.value = true
  compileHtml()
  try {
    const res = await http.put(`/templates/${props.templateId}`, {
      group: template.value.group,
      name: template.value.name,
      report: template.value.report,
      report_data_source_id: template.value.report_data_source_id || null,
      parameter_defaults: template.value.parameter_defaults || {},
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10,
      content_json: blocks.value,
      content_html: template.value.content_html,
      css: template.value.css || '',
      note: note.value || 'Cập nhật mẫu biểu thiết kế trực quan',
      save_mode: 'version'
    })
    
    if (res.data && res.data.success) {
      uiStore.showToast('Lưu biểu mẫu và tạo phiên bản thành công!', 'success')
      showSaveModal.value = false
      note.value = ''
      await loadTemplate()
      await loadVersions()
      notifyTemplateSaved()
      emit('saved')
    }
  } catch (err) {
    console.error('Lỗi khi lưu biểu mẫu:', err)
    let errorMsg = 'Không thể lưu biểu mẫu. Vui lòng kiểm tra lại.'
    if (err.response && err.response.data) {
      if (err.response.data.errors) {
        errorMsg = Object.values(err.response.data.errors).flat().join(', ')
      } else if (err.response.data.message) {
        errorMsg = err.response.data.message
      }
    }
    uiStore.showToast(errorMsg, 'error')
  } finally {
    saving.value = false
  }
}

// Rollback version
const rollbackToVersion = async (versionId) => {
  if (!confirm('Bạn có chắc chắn muốn khôi phục về phiên bản này không? Mọi thay đổi chưa lưu hiện tại sẽ bị ghi đè.')) {
    return
  }
  
  loading.value = true
  try {
    const res = await http.post(`/templates/${props.templateId}/rollback`, {
      version_id: versionId
    })
    if (res.data && res.data.success) {
      uiStore.showToast('Khôi phục phiên bản thành công!', 'success')
      await loadTemplate()
      await loadVersions()
      notifyTemplateSaved()
      emit('saved')
      if (activeTab.value === 'design') {
        // Force refresh design UI
      }
    }
  } catch (err) {
    console.error('Lỗi khi rollback phiên bản:', err)
    uiStore.showToast('Không thể khôi phục phiên bản', 'error')
  } finally {
    loading.value = false
  }
}

// Watchers
watch(() => props.isOpen, (newVal) => {
  if (newVal && props.templateId) {
    activeTab.value = 'design'
    loadDataSources()
    loadTemplate()
    loadVersions()
  }
}, { immediate: true })

watch(activeTab, (newVal) => {
  if (newVal === 'preview') {
    loadPreview()
  }
  if (newVal === 'design') {
    nextTick(fitCanvasToViewport)
  }
})

watch(
  () => [template.value?.page_size, template.value?.page_orientation],
  () => nextTick(fitCanvasToViewport),
  { flush: 'post' }
)

watch(
  () => [
    template.value?.page_size,
    template.value?.page_orientation,
    template.value?.margin_top,
    template.value?.margin_bottom,
    template.value?.margin_left,
    template.value?.margin_right,
    template.value?.css,
    template.value?.report_data_source_id,
    template.value?.parameter_defaults,
  ],
  scheduleDesignerHistory,
  { deep: true },
)

watch(selectedBlockId, (newId) => {
  if (newId) {
    const block = selectedBlock.value
    if (block) {
      editingContent.value = block.content || ''
    }
  } else {
    editingContent.value = ''
  }
})

const isEditableTarget = target => target instanceof HTMLElement
  && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))

const onDesignerKeydown = event => {
  if (!props.isOpen || activeTab.value !== 'design') return
  const modifier = event.ctrlKey || event.metaKey
  const key = event.key.toLowerCase()

  if (modifier && key === 's') {
    event.preventDefault()
    showSaveModal.value = true
    return
  }
  if (isEditableTarget(event.target)) return
  if (modifier && key === 'z') {
    event.preventDefault()
    event.shiftKey ? redoDesigner() : undoDesigner()
  } else if (modifier && key === 'y') {
    event.preventDefault()
    redoDesigner()
  } else if (modifier && key === 'c' && selectedBlock.value) {
    event.preventDefault()
    copySelectedBlock()
  } else if (modifier && key === 'v' && blockClipboard.value) {
    event.preventDefault()
    pasteDesignerBlock()
  } else if (modifier && key === 'd' && selectedBlock.value) {
    event.preventDefault()
    duplicateSelectedBlock()
  } else if (['delete', 'backspace'].includes(key) && selectedBlock.value) {
    event.preventDefault()
    deleteSelectedBlock()
  }
}

onMounted(() => {
  window.addEventListener('resize', fitCanvasToViewport)
  window.addEventListener('keydown', onDesignerKeydown)
})

onBeforeUnmount(() => {
  clearTimeout(historyTimer)
  window.removeEventListener('resize', fitCanvasToViewport)
  window.removeEventListener('keydown', onDesignerKeydown)
})

// Trigger close
const handleClose = () => {
  emit('close')
}

// Select band helper
const selectBand = (band) => {
  selectedBand.value = band
  // Select first block in this band if exists
  if (blocks.value[band].length > 0) {
    selectedBlockId.value = blocks.value[band][0].id
  } else {
    selectedBlockId.value = null
  }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 backdrop-blur-xs">
    <div class="bg-white rounded-2xl w-full h-[95vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden max-w-[96vw]">
      <!-- 1. Header Bar of Editor -->
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center shrink-0">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl bg-sky-600 flex items-center justify-center text-white font-extrabold shadow-sm">
            PDF
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2" v-if="template">
              {{ template.name }} 
              <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-black uppercase tracking-wider">
                v{{ template.version }}
              </span>
            </h2>
            <p class="text-xs text-slate-400" v-if="template">Nhóm: {{ template.group }}</p>
          </div>
        </div>

        <!-- Middle Page Layout configurations -->
        <div class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-xl shadow-xs" v-if="template">
          <!-- Page size -->
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Khổ giấy:</span>
            <select v-model="template.page_size" class="text-xs border border-slate-200 rounded-lg px-2 py-1 font-semibold focus:outline-sky-500">
              <option value="A4">A4</option>
              <option value="A5">A5</option>
              <option value="Letter">Letter</option>
              <option value="Legal">Legal</option>
            </select>
          </div>
          <!-- Orientation -->
          <div class="flex items-center gap-1.5 border-l border-slate-200 pl-3">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Chiều:</span>
            <select v-model="template.page_orientation" class="text-xs border border-slate-200 rounded-lg px-2 py-1 font-semibold focus:outline-sky-500">
              <option value="portrait">Dọc (Portrait)</option>
              <option value="landscape">Ngang (Landscape)</option>
            </select>
          </div>
          <!-- Margins (mm) -->
          <div class="flex items-center gap-1 pl-3 border-l border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Lề (mm):</span>
            <div class="flex gap-1 items-center">
              <input type="number" v-model.number="template.margin_top" placeholder="Top" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Trên" />
              <input type="number" v-model.number="template.margin_bottom" placeholder="Bot" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Dưới" />
              <input type="number" v-model.number="template.margin_left" placeholder="Left" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Trái" />
              <input type="number" v-model.number="template.margin_right" placeholder="Right" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Phải" />
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2">
          <button @click="showSaveModal = true" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-xl shadow-sm hover:shadow-md transition-all cursor-pointer flex items-center gap-1.5 uppercase">
            <Save class="w-4 h-4" /> Lưu phiên bản
          </button>
          <button @click="handleClose" class="w-9 h-9 rounded-xl hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- 2. Tabs Bar (Design, Preview, CSS, Versions) -->
      <div class="border-b border-slate-200 px-6 bg-white shrink-0 flex items-center justify-between">
        <div class="flex gap-1.5 py-2">
          <button @click="activeTab = 'design'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'design' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <Layers class="w-4 h-4" /> Thiết kế Banded
          </button>
          <button @click="activeTab = 'preview'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'preview' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <FileText class="w-4 h-4" /> Xem trước in (PDF/Web)
          </button>
          <button @click="activeTab = 'css'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'css' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <Settings class="w-4 h-4" /> Custom CSS Styles
          </button>
          <button @click="activeTab = 'versions'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'versions' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <History class="w-4 h-4" /> Lịch sử phiên bản
          </button>
        </div>
        <div class="flex items-center gap-2">
          <button @click="toggleDesignerPanel('left')" class="px-2 py-1 rounded-md text-[10px] font-bold text-slate-500 hover:bg-slate-100 cursor-pointer border border-slate-200" :title="showLeftPanel ? 'Ẩn nguồn dữ liệu và hộp công cụ' : 'Hiện nguồn dữ liệu và hộp công cụ'">
            {{ showLeftPanel ? 'Ẩn panel trái' : 'Panel trái' }}
          </button>
          <button @click="toggleDesignerPanel('right')" class="px-2 py-1 rounded-md text-[10px] font-bold text-slate-500 hover:bg-slate-100 cursor-pointer border border-slate-200" :title="showRightPanel ? 'Ẩn bảng thuộc tính' : 'Hiện bảng thuộc tính'">
            {{ showRightPanel ? 'Ẩn thuộc tính' : 'Thuộc tính' }}
          </button>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">PMS Report Designer</span>
        </div>
      </div>

      <!-- 3. Loading Spinner -->
      <div v-if="loading" class="flex-1 flex flex-col items-center justify-center gap-3">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-sky-600"></div>
        <p class="text-xs text-slate-400 font-semibold">Đang chuẩn bị trình thiết kế...</p>
      </div>

      <!-- 4. Content Area -->
      <div v-else-if="template" class="flex-1 overflow-hidden flex items-stretch">
        
        <!-- ================== TAB 1: DESIGNER ================== -->
        <template v-if="activeTab === 'design'">
          <!-- Column 1: Field List (Left Panel) -->
          <div v-if="showLeftPanel" class="w-1/4 min-w-[240px] max-w-[360px] bg-slate-50 border-r border-slate-200 p-4 overflow-y-auto flex flex-col gap-4 select-none shrink-0">

            <!-- REPORT EXPLORER -->
            <div class="flex flex-col gap-2 rounded-xl border border-indigo-200 bg-white p-3 shadow-3xs">
              <span class="block border-b border-indigo-100 pb-1 text-[10px] font-black uppercase tracking-widest text-indigo-700">Report Explorer</span>
              <div v-for="band in ['header', 'detail', 'footer']" :key="band" class="flex flex-col gap-0.5">
                <button type="button" @click="selectBand(band)" class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-[10px] font-black uppercase text-slate-600 hover:bg-indigo-50">
                  <span>▾ {{ band === 'header' ? 'Report Header' : band === 'detail' ? 'Detail' : 'Report Footer' }}</span>
                  <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px]">{{ explorerBlocks(band).length }}</span>
                </button>
                <button v-for="item in explorerBlocks(band)" :key="item.block.id" type="button" @click="selectDesignerBlock(band, item.block)"
                  class="flex w-full items-center gap-1 rounded py-1 pr-1 text-left text-[10px] hover:bg-sky-50"
                  :class="selectedBlockId === item.block.id ? 'bg-sky-100 font-bold text-sky-800' : 'text-slate-600'"
                  :style="{ paddingLeft: `${10 + item.depth * 14}px` }">
                  <span class="w-3 text-center">{{ item.block.visible === false ? '○' : item.block.locked ? '▣' : '▪' }}</span>
                  <span class="min-w-0 flex-1 truncate">{{ blockTypeLabel(item.block.type) }}</span>
                  <span class="max-w-20 truncate font-mono text-[8px] text-slate-400">{{ item.block.id }}</span>
                </button>
              </div>
            </div>

            <!-- DYNAMIC MYSQL STORED PROCEDURE DATA SOURCE -->
            <div class="flex flex-col gap-2 bg-white rounded-xl p-3 border border-emerald-200 shadow-3xs">
              <span class="text-[10px] font-black text-emerald-700 uppercase tracking-widest pb-1 border-b border-emerald-100 block">Nguồn dữ liệu Store</span>
              <select v-model="template.report_data_source_id" @change="onDataSourceChange"
                class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-700 focus:outline-emerald-500">
                <option :value="null">Dữ liệu mặc định / mock</option>
                <option v-for="source in dataSources" :key="source.id" :value="source.id">
                  {{ source.name }} ({{ source.object_name }})
                </option>
              </select>
              <div v-if="selectedDataSource" class="space-y-2">
                <p class="text-[10px] text-slate-400">Tham số preview</p>
                <label v-for="parameter in selectedDataSource.parameter_schema || []" :key="parameter.name" class="block text-[10px] font-bold text-slate-500">
                  {{ parameter.name }}
                  <input v-model="template.parameter_defaults[parameter.name]"
                    :type="parameter.data_type === 'date' ? 'date' : 'text'"
                    class="mt-0.5 w-full rounded-md border border-slate-200 px-2 py-1.5 text-xs font-medium" />
                </label>
                <p class="rounded-md bg-emerald-50 px-2 py-1.5 text-[9px] font-semibold text-emerald-700">
                  {{ (selectedDataSource.field_schema || []).length }} field đã đồng bộ
                </p>
              </div>
            </div>
            
            <!-- TOOLBOX -->
            <div class="flex flex-col gap-2 bg-white rounded-xl p-3 border border-slate-200 shadow-3xs">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-100 block">Hộp Công Cụ Blocks</span>
              <div class="grid grid-cols-2 gap-1.5 pt-2">
                <button @click="addBlock('text')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">T</span> Văn Bản
                </button>
                <button @click="addBlock('image')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">📷</span> Hình Ảnh
                </button>
                <button @click="addBlock('table')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all col-span-2">
                  <span class="text-base mb-0.5">田</span> Bảng Chi Tiết (Detail Table)
                </button>
                <button @click="addBlock('divider')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">─</span> Đường Kẻ
                </button>
                <button @click="addBlock('spacer')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">↕</span> Khoảng Trống
                </button>
                <button @click="addBlock('shape')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">□</span> Hình Khối
                </button>
                <button @click="addBlock('page-break')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">↵</span> Ngắt Trang
                </button>
                <button @click="addBlock('columns')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all col-span-2">
                  <span class="text-base mb-0.5">◫</span> Bố Cục Cột (Columns Layout)
                </button>
              </div>
            </div>

            <!-- PREDEFINED BLOCKS -->
            <div class="flex flex-col gap-2 bg-white rounded-xl p-3 border border-slate-200 shadow-3xs">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-100 block">Khối Dựng Sẵn (Templates)</span>
              <div class="flex flex-col gap-1.5 pt-2">
                <button @click="addHotelInfoBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">🏢</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Thông tin Khách sạn</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Logo + Tên + Địa chỉ (2 cột)</p>
                  </div>
                </button>
                <button @click="addCustomerInfoBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">👤</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Thông tin Khách & Đặt phòng</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Thông tin khách hàng & checkin/out</p>
                  </div>
                </button>
                <button @click="addSignatureBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">✍️</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Khối Chữ ký phê duyệt</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Khách ký nhận + Lễ tân thực hiện</p>
                  </div>
                </button>
              </div>
            </div>

            <!-- FIELD LIST (DATA VARIABLES) -->
            <div class="flex flex-col gap-2 flex-1">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-200 block">Danh Sách Trường Dữ Liệu (Field List)</span>
              <p class="text-[10px] text-slate-400 italic">Chọn 1 khối rồi click biến để chèn nhanh</p>
              
              <!-- Accordion groups -->
              <div v-for="(fields, key) in fieldList" :key="key" class="border border-slate-200 rounded-lg bg-white overflow-hidden shadow-3xs">
                <button @click="openCategories[key] = !openCategories[key]" class="w-full flex justify-between items-center px-3 py-2 bg-slate-50/75 border-none font-bold text-xs text-slate-700 hover:bg-slate-50 cursor-pointer">
                  <span class="flex items-center gap-1.5 capitalize">
                    📁 {{ key === 'hotel' ? 'Khách Sạn' : key === 'customer' ? 'Khách Hàng' : key === 'booking' ? 'Đặt Phòng' : key === 'room' ? 'Hạng & Số Phòng' : key === 'payment' ? 'Thanh Toán' : key === 'registration' ? 'Phiếu Đăng Ký' : key === 'parameters' ? 'Tham Số Store' : key === 'summary' ? 'Tổng Hợp' : 'Bảng Dữ Liệu Lặp' }}
                  </span>
                  <ChevronRight class="w-3.5 h-3.5 transition-transform" :class="openCategories[key] ? 'rotate-90' : ''" />
                </button>
                
                <div v-if="openCategories[key]" class="p-1 border-t border-slate-100 flex flex-col gap-0.5 bg-white">
                  <button v-for="field in fields" :key="field.value" draggable="true"
                    @dragstart="onFieldDragStart($event, field)" @click="insertVariable(field.value)"
                    class="w-full text-left px-2.5 py-1.5 rounded-md hover:bg-sky-50 text-slate-600 hover:text-sky-700 text-xs font-semibold border-none bg-transparent flex justify-between items-center cursor-pointer transition-colors group">
                    <span>{{ field.label }}</span>
                    <span class="text-[9px] font-mono text-slate-400 group-hover:text-sky-500 font-bold">[{{ field.value }}]</span>
                  </button>
                </div>
              </div>
            </div>

          </div>

          <!-- Column 2: Banded Design Canvas (Middle Panel) -->
          <div ref="canvasViewport" class="flex-1 min-w-0 bg-slate-100 p-6 overflow-auto flex flex-col items-center">
            
            <!-- Band selector controls -->
            <div class="flex flex-wrap items-center justify-center gap-2 mb-4 select-none">
              <div class="flex bg-white p-1 border border-slate-200 rounded-xl shadow-xs gap-1">
              <button @click="selectBand('header')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'header' ? 'bg-amber-100 text-amber-800' : 'text-slate-500 hover:bg-slate-50'">
                Report Header Band
              </button>
              <button @click="selectBand('detail')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'detail' ? 'bg-sky-100 text-sky-800' : 'text-slate-500 hover:bg-slate-50'">
                Detail Band (Bảng chi tiết)
              </button>
              <button @click="selectBand('footer')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'footer' ? 'bg-emerald-100 text-emerald-800' : 'text-slate-500 hover:bg-slate-50'">
                Report Footer Band
              </button>
              </div>
              <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xs">
                <span class="px-1 text-[10px] font-bold uppercase text-slate-400">Thu phóng</span>
                <button @click="adjustCanvasZoom(-0.1)" class="h-7 w-7 rounded-md text-sm font-bold text-slate-600 hover:bg-slate-100 cursor-pointer border-none bg-transparent" title="Thu nhỏ">−</button>
                <button @click="setCanvasZoom('fit')" class="h-7 rounded-md px-2 text-[10px] font-bold cursor-pointer border-none" :class="canvasZoomMode === 'fit' ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100'">Vừa trang</button>
                <button v-for="zoom in [0.5, 0.75, 1]" :key="zoom" @click="setCanvasZoom(zoom)" class="h-7 rounded-md px-1.5 text-[10px] font-bold cursor-pointer border-none" :class="canvasZoomMode === 'manual' && canvasZoom === zoom ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100'">{{ Math.round(zoom * 100) }}%</button>
                <button @click="adjustCanvasZoom(0.1)" class="h-7 w-7 rounded-md text-sm font-bold text-slate-600 hover:bg-slate-100 cursor-pointer border-none bg-transparent" title="Phóng to">+</button>
                <span class="min-w-9 text-center text-[10px] font-bold text-slate-500">{{ Math.round(canvasZoom * 100) }}%</span>
              </div>
            </div>

            <div v-if="quickFormatTarget || selectedBlock" class="sticky top-2 z-30 flex max-w-full flex-wrap items-center justify-start gap-1 rounded-xl border border-sky-200 bg-white/95 p-2 shadow-lg backdrop-blur-sm" @mousedown.stop>
              <template v-if="quickFormatTarget?.kind === 'static-cell'">
                <span class="mr-1 text-[10px] font-bold text-sky-700">Ô {{ staticCellPosition(quickFormatTarget).row }}.{{ staticCellPosition(quickFormatTarget).column }}</span>
                <select :value="staticStyleValue('fontWeight')" @change="applyStaticStyle('fontWeight', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Độ đậm"><option value="">Kế thừa</option><option value="normal">Chữ thường</option><option value="bold">In đậm</option></select>
                <select :value="staticStyleValue('textAlign')" @change="applyStaticStyle('textAlign', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Căn lề"><option value="">Căn lề kế thừa</option><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select>
                <input :value="staticStyleValue('fontSize')" @input="applyStaticStyle('fontSize', $event.target.value)" class="h-7 w-24 rounded border border-slate-200 px-1 text-[10px]" placeholder="Cỡ chữ: 10px" title="Cỡ chữ" />
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]">Chữ<input type="color" :value="colorInputValue(staticStyleValue('color'), '#1e293b')" @input="applyStaticStyle('color', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]">Nền<input type="color" :value="colorInputValue(staticStyleValue('backgroundColor'), '#ffffff')" @input="applyStaticStyle('backgroundColor', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <select v-model="staticStyleScope" class="h-7 rounded border border-sky-200 bg-sky-50 px-1 text-[10px] font-bold text-sky-700" title="Phạm vi áp dụng"><option value="cell">Ô</option><option value="selection">Vùng chọn</option><option value="row">Hàng</option><option value="column">Cột</option><option value="table">Toàn bảng</option></select>
                <select :value="staticStyleValue('fontFamily')" @change="applyStaticStyle('fontFamily', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Phông chữ"><option value="">Phông kế thừa</option><option>Arial</option><option>Tahoma</option><option>Times New Roman</option><option>Verdana</option><option>Courier New</option></select>
                <button type="button" @click="applyStaticStyle('fontStyle', staticStyleValue('fontStyle') === 'italic' ? '' : 'italic')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs italic hover:bg-sky-50" title="In nghiêng">I</button>
                <button type="button" @click="applyStaticStyle('textDecoration', staticStyleValue('textDecoration') === 'underline' ? '' : 'underline')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs underline hover:bg-sky-50" title="Gạch chân">U</button>
                <select :value="staticStyleValue('verticalAlign')" @change="applyStaticStyle('verticalAlign', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Căn dọc"><option value="">Căn dọc</option><option value="top">Trên</option><option value="middle">Giữa</option><option value="bottom">Dưới</option></select>
                <input :value="staticStyleValue('lineHeight')" @input="applyStaticStyle('lineHeight', $event.target.value)" class="h-7 w-16 rounded border border-slate-200 px-1 text-[10px]" placeholder="Dòng" title="Chiều cao dòng" />
                <select :value="staticStyleValue('whiteSpace')" @change="applyStaticStyle('whiteSpace', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Ngắt dòng"><option value="">Ngắt dòng</option><option value="normal">Tự động</option><option value="nowrap">Không ngắt</option><option value="pre-wrap">Giữ xuống dòng</option></select>
                <input :value="staticStyleValue('paddingTop')" @input="applyStaticStyle('paddingTop', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Đệm trên" title="Đệm trên" />
                <input :value="staticStyleValue('paddingRight')" @input="applyStaticStyle('paddingRight', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Đệm phải" title="Đệm phải" />
                <input :value="staticStyleValue('paddingBottom')" @input="applyStaticStyle('paddingBottom', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Đệm dưới" title="Đệm dưới" />
                <input :value="staticStyleValue('paddingLeft')" @input="applyStaticStyle('paddingLeft', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Đệm trái" title="Đệm trái" />
                <select :value="staticStyleValue('borderTopStyle')" @change="applyStaticBorder('Style', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Kiểu đường viền"><option value="">Viền</option><option value="none">Không</option><option value="solid">Nét liền</option><option value="dashed">Nét đứt</option><option value="dotted">Nét chấm</option><option value="double">Nét đôi</option></select>
                <input :value="staticStyleValue('borderTopWidth')" @input="applyStaticBorder('Width', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="1px" title="Độ dày viền" />
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu viền">V<input type="color" :value="colorInputValue(staticStyleValue('borderTopColor'), '#cbd5e1')" @input="applyStaticBorder('Color', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <button type="button" @click="copyStaticStyle" class="h-7 rounded border border-slate-200 bg-white px-2 text-[10px] hover:bg-sky-50">Chép kiểu</button>
                <button type="button" :disabled="!staticStyleClipboard" @click="pasteStaticStyle" class="h-7 rounded border border-slate-200 bg-white px-2 text-[10px] hover:bg-sky-50 disabled:opacity-40">Dán kiểu</button>
                <button type="button" @click="mergeStaticCells" class="h-7 rounded border border-slate-200 bg-white px-2 text-[10px] hover:bg-sky-50">Gộp ô</button>
                <button type="button" @click="splitStaticCell" class="h-7 rounded border border-slate-200 bg-white px-2 text-[10px] hover:bg-sky-50">Tách ô</button>
              </template>
              <template v-else-if="quickFormatTarget">
                <span class="mr-1 text-[10px] font-bold text-sky-700">Đang chọn: {{ quickFormatTarget.kind === 'table-header' ? 'tiêu đề cột' : quickFormatTarget.kind === 'table-cell' ? 'ô dữ liệu' : quickFormatTarget.kind === 'custom-cell' ? 'ô tổng/nhóm' : 'đoạn chữ' }}</span>
                <button type="button" @mousedown.prevent="applyQuickStyle('bold')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs font-black hover:bg-sky-50" title="In đậm">B</button>
                <button type="button" @mousedown.prevent="applyQuickStyle('italic')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs italic hover:bg-sky-50" title="In nghiêng">I</button>
                <button type="button" @mousedown.prevent="applyQuickStyle('underline')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs underline hover:bg-sky-50" title="Gạch chân">U</button>
                <select @change="applyQuickStyle('fontSize', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Cỡ chữ"><option value="">Cỡ chữ</option><option v-for="size in fontSizeOptions" :key="size" :value="`${size}px`">{{ size }}px</option></select>
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu chữ">A<input type="color" @input="applyQuickStyle('color', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu nền">N<input type="color" @input="applyQuickStyle('backgroundColor', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
              </template>
              <template v-else-if="selectedBlock">
                <span class="mr-1 text-[10px] font-bold text-sky-700">Đang chọn {{ selectedBlockToolbarLabel(selectedBlock) }}</span>
                <button type="button" @click="applyBlockTextStyle('fontWeight', selectedBlock.style.fontWeight === 'bold' ? 'normal' : 'bold')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs font-black hover:bg-sky-50" :class="selectedBlock.style.fontWeight === 'bold' ? 'bg-sky-50 text-sky-700' : ''" title="In đậm">B</button>
                <button type="button" @click="applyBlockTextStyle('fontStyle', selectedBlock.style.fontStyle === 'italic' ? 'normal' : 'italic')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs italic hover:bg-sky-50" title="In nghiêng">I</button>
                <button type="button" @click="applyBlockTextStyle('textDecoration', selectedBlock.style.textDecoration === 'underline' ? 'none' : 'underline')" class="h-7 w-7 rounded border border-slate-200 bg-white text-xs underline hover:bg-sky-50" title="Gạch chân">U</button>
                <select :value="selectedBlock.style.fontFamily || ''" @change="applyBlockTextStyle('fontFamily', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Phông chữ"><option value="">Phông chữ</option><option>Arial</option><option>Tahoma</option><option>Times New Roman</option><option>Verdana</option><option>Courier New</option></select>
                <select :value="selectedBlock.style.fontSize || ''" @change="applyBlockTextStyle('fontSize', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Cỡ chữ"><option value="">Cỡ chữ</option><option v-for="size in fontSizeOptions" :key="size" :value="`${size}px`">{{ size }}px</option></select>
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu chữ">A<input type="color" :value="colorInputValue(selectedBlock.style.color, '#1e293b')" @input="applyBlockTextStyle('color', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu nền">N<input type="color" :value="colorInputValue(selectedBlock.style.backgroundColor, '#ffffff')" @input="applyBlockTextStyle('backgroundColor', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <select v-if="!['image', 'spacer', 'divider', 'shape', 'page-break'].includes(selectedBlock.type)" :value="selectedBlock.style.textAlign || 'left'" @change="applyBlockTextStyle('textAlign', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Căn ngang"><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option><option value="justify">Đều</option></select>
                <select v-if="!['image', 'spacer', 'divider', 'shape', 'page-break'].includes(selectedBlock.type)" :value="selectedBlock.style.verticalAlign || 'top'" @change="applyBlockTextStyle('verticalAlign', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Căn dọc"><option value="top">Trên</option><option value="middle">Giữa</option><option value="bottom">Dưới</option></select>
                <input v-if="!['image', 'spacer', 'divider', 'shape', 'page-break'].includes(selectedBlock.type)" :value="selectedBlock.style.lineHeight || ''" @input="applyBlockTextStyle('lineHeight', $event.target.value)" class="h-7 w-16 rounded border border-slate-200 px-1 text-[10px]" placeholder="Dòng" title="Chiều cao dòng" />
                <input v-if="['shape', 'spacer'].includes(selectedBlock.type)" type="number" min="1" :value="selectedBlock.height || 20" @input="selectedBlock.height = Number($event.target.value); compileHtml()" class="h-7 w-16 rounded border border-slate-200 px-1 text-[10px]" placeholder="Cao" title="Chiều cao" />
                <input :value="selectedBlock.style.paddingTop || ''" @input="applyBlockTextStyle('paddingTop', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="P trên" title="Padding trên" />
                <input :value="selectedBlock.style.paddingRight || ''" @input="applyBlockTextStyle('paddingRight', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="P phải" title="Padding phải" />
                <input :value="selectedBlock.style.paddingBottom || ''" @input="applyBlockTextStyle('paddingBottom', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="P dưới" title="Padding dưới" />
                <input :value="selectedBlock.style.paddingLeft || ''" @input="applyBlockTextStyle('paddingLeft', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="P trái" title="Padding trái" />
                <select :value="selectedBlock.style.borderStyle || 'none'" @change="applyBlockTextStyle('borderStyle', $event.target.value)" class="h-7 rounded border border-slate-200 px-1 text-[10px]" title="Kiểu viền"><option value="none">Không viền</option><option value="solid">Nét liền</option><option value="dashed">Nét đứt</option><option value="dotted">Nét chấm</option><option value="double">Nét đôi</option></select>
                <input :value="selectedBlock.style.borderWidth || ''" @input="applyBlockTextStyle('borderWidth', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Viền" title="Độ dày viền" />
                <label class="flex h-7 items-center gap-1 rounded border border-slate-200 px-1 text-[10px]" title="Màu viền">V<input type="color" :value="colorInputValue(selectedBlock.style.borderColor, '#cbd5e1')" @input="applyBlockTextStyle('borderColor', $event.target.value)" class="h-5 w-5 border-0 p-0" /></label>
                <input :value="selectedBlock.style.borderRadius || ''" @input="applyBlockTextStyle('borderRadius', $event.target.value)" class="h-7 w-14 rounded border border-slate-200 px-1 text-[10px]" placeholder="Bo góc" title="Bo góc" />
                <button type="button" @click="resetSelectedBlockToolbar" class="h-7 rounded border border-slate-200 bg-slate-50 px-2 text-[10px] text-slate-600 hover:bg-slate-100">Đặt lại</button>
              </template>
              <button v-if="quickFormatTarget" type="button" @click="resetQuickStyle" class="h-7 rounded border border-slate-200 bg-slate-50 px-2 text-[10px] text-slate-600 hover:bg-slate-100">Đặt lại</button>
              <button type="button" @click="closeQuickToolbar" class="h-7 rounded border-none bg-transparent px-1 text-xs text-slate-400 hover:text-red-500" title="Đóng thanh thuộc tính">×</button>
            </div>

            <div v-if="staticCellContextMenu" class="fixed z-50 w-40 rounded-lg border border-slate-200 bg-white p-1 text-xs shadow-xl" :style="{ left: `${staticCellContextMenu.x}px`, top: `${staticCellContextMenu.y}px` }" @mouseleave="closeStaticCellContextMenu">
              <button type="button" @click="copyStaticStyle(); closeStaticCellContextMenu()" class="w-full rounded px-2 py-1.5 text-left hover:bg-sky-50">Chép định dạng</button>
              <button type="button" :disabled="!staticStyleClipboard" @click="pasteStaticStyle(); closeStaticCellContextMenu()" class="w-full rounded px-2 py-1.5 text-left hover:bg-sky-50 disabled:opacity-40">Dán định dạng</button>
              <button type="button" @click="mergeStaticCells(); closeStaticCellContextMenu()" class="w-full rounded px-2 py-1.5 text-left hover:bg-sky-50">Gộp ô đã chọn</button>
              <button type="button" @click="splitStaticCell(); closeStaticCellContextMenu()" class="w-full rounded px-2 py-1.5 text-left hover:bg-sky-50">Tách ô</button>
              <button type="button" @click="resetStaticStyle(); closeStaticCellContextMenu()" class="w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Đặt lại định dạng</button>
            </div>

            <!-- Page Canvas Layout Representation -->
            <div class="canvas-scale-frame shrink-0" :style="canvasFrameStyle">
            <div class="template-preview-canvas bg-white shadow-lg border border-slate-300 relative flex flex-col"
              :style="canvasStyle">
              <component :is="'style'" v-if="scopedTemplateCss">{{ scopedTemplateCss }}</component>
              <component :is="'style'" v-if="scopedBlockFontCss">{{ scopedBlockFontCss }}</component>
              
              <!-- SECTION 1: HEADER BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mb-4 transition-all relative group/band"
                :class="[
                  selectedBand === 'header' ? 'border-amber-400 bg-amber-50/20' : 'border-slate-200',
                  blocks.header.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'header'" @dragover.prevent
                @drop="onBlockDrop($event, 'header', blocks.header.length)">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-amber-100 text-amber-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Report Header Band
                </span>

                <div v-if="blocks.header.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Header. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Header -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.header" :key="b.id" :draggable="!b.locked"
                    @dragstart="onBlockDragStart($event, 'header', idx)" @dragend="onBlockDragEnd"
                    @dragover.prevent @drop.stop="onCanvasBlockDrop($event, 'header', idx, b)"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'header'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="[selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white', b.visible === false ? 'opacity-45 border-dotted' : '', b.locked ? 'cursor-default' : '']">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type }}{{ b.locked ? ' · khóa' : '' }}{{ b.visible === false ? ' · ẩn khi in' : '' }}
                    </span>

                    <!-- Block drag/edit overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.header.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('header', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Block Visual Content -->
                    <div v-if="b.type === 'text'" :class="getBlockScopeClass(b)" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                        @focus="onTextareaFocus"
                        @mouseup="captureTextSelection"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Table Setup and Rendering -->
                    <div v-else-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <p v-if="tableGroups(b).length" class="mb-1 rounded bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">
                          <span v-for="(group, index) in tableGroups(b)" :key="group.id">
                            Cấp {{ index + 1 }}: {{ group.field }}<span v-if="group.enabledBy"> · Khi: {{ group.enabledBy }}</span><span v-if="index < tableGroups(b).length - 1"> → </span>
                          </span>
                        </p>
                        <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" @click.stop="selectQuickFormatTarget({ kind: 'table-header', block: b, column: col })" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" :style="styleObjectToCss(mergeConfiguredStyles({ textAlign: col.align || 'left', fontWeight: 'bold' }, col.headerStyle), true)" class="w-full bg-transparent border-none text-slate-800 focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(group, groupIndex) in tableGroups(b)" :key="`preview-group-${group.id}`" class="bg-amber-50 text-amber-700" :style="{ paddingLeft: `${groupIndex * 12}px` }">
                              <template v-if="group.headerCells?.length">
                                <td v-for="cell in group.headerCells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="border-b border-amber-200 px-2 py-1 font-bold" :class="cell.className" :style="getCustomTableCellStyle(b, cell, {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              </template>
                              <td v-else :colspan="b.columns.length + 1" class="border-b border-amber-200 px-2 py-1 text-left font-bold">
                                {{ groupHeaderPreview(group) }}
                              </td>
                            </tr>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" @click.stop="selectQuickFormatTarget({ kind: 'table-cell', block: b, column: col })" :style="getTableDetailStyle(b, col)" class="font-mono text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr v-for="(customRow, customRowIndex) in tableCustomRows(b)" :key="customRow.id" class="bg-slate-100 font-bold">
                              <td v-for="(cell, cellIndex) in customRow.cells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="px-2 py-1" :style="getCustomTableCellStyle(b, cell, b.columns[cellIndex] || {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              <td class="w-8 px-1 text-center" :style="getTableCellStyle(b, {})"><button type="button" @click.stop="removeTableCustomRow(b, customRowIndex)" class="border-none bg-transparent text-red-500">×</button></td>
                            </tr>
                            <tr class="bg-sky-50"><td :colspan="b.columns.length + 1" class="px-2 py-1 text-center"><button type="button" @click.stop="addTableCustomRow(b)" class="rounded border border-sky-200 bg-white px-2 py-0.5 text-[10px] font-black text-sky-700">+ Thêm hàng</button></td></tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>

                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx" v-if="!isStaticCellCovered(b, rIdx, cIdx)"
                              :colspan="cell.colspan || 1" :rowspan="cell.rowspan || 1"
                              :style="getStaticTableCellStyle(b, row, cell, b.columns[cIdx] || {})"
                              :class="['relative group/td p-0', isStaticCellSelected(b, row, cell) ? 'ring-2 ring-inset ring-sky-500' : '']">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @click.stop="selectStaticCell($event, b, row, cell)"
                                @contextmenu.stop="openStaticCellContextMenu($event, b, row, cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-inherit"
                                v-html="activeCell === cell ? editingCellContent : staticCellContent(cell, row)"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'shape'" :style="{ ...getBlockStyle(b), height: `${b.height || 40}px` }" class="min-h-px"></div>
                    <div v-else-if="b.type === 'page-break'" class="flex h-5 items-center gap-2 text-[9px] font-bold uppercase text-rose-500"><span class="h-px flex-1 border-t border-dashed border-rose-300"></span>Ngắt trang<span class="h-px flex-1 border-t border-dashed border-rose-300"></span></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
                    </div>
                    
                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'header'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'" :class="getBlockScopeClass(subBlock)" :style="getBlockStyle(subBlock)">
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                         @focus="onTextareaFocus"
                         @mouseup="captureTextSelection"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- SECTION 2: DETAIL BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mb-4 transition-all relative group/band"
                :class="[
                  selectedBand === 'detail' ? 'border-sky-400 bg-sky-50/20' : 'border-slate-200',
                  blocks.detail.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'detail'" @dragover.prevent
                @drop="onBlockDrop($event, 'detail', blocks.detail.length)">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-sky-100 text-sky-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Detail Band (Bảng lặp động)
                </span>

                <div v-if="blocks.detail.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Detail. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Detail -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.detail" :key="b.id" :draggable="!b.locked"
                    @dragstart="onBlockDragStart($event, 'detail', idx)" @dragend="onBlockDragEnd"
                    @dragover.prevent @drop.stop="onCanvasBlockDrop($event, 'detail', idx, b)"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'detail'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="[selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white', b.visible === false ? 'opacity-45 border-dotted' : '', b.locked ? 'cursor-default' : '']">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type === 'table' ? 'Detail Table' : b.type }}{{ b.locked ? ' · khóa' : '' }}{{ b.visible === false ? ' · ẩn khi in' : '' }}
                    </span>

                    <!-- Overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.detail.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('detail', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Table Block Rendering -->
                    <div v-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <p v-if="tableGroups(b).length" class="mb-1 rounded bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">
                          <span v-for="(group, index) in tableGroups(b)" :key="group.id">
                            Cấp {{ index + 1 }}: {{ group.field }}<span v-if="group.enabledBy"> · Khi: {{ group.enabledBy }}</span><span v-if="index < tableGroups(b).length - 1"> → </span>
                          </span>
                        </p>
                        <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" @click.stop="selectQuickFormatTarget({ kind: 'table-header', block: b, column: col })" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" :style="styleObjectToCss(mergeConfiguredStyles({ textAlign: col.align || 'left', fontWeight: 'bold' }, col.headerStyle), true)" class="w-full bg-transparent border-none text-slate-800 focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(group, groupIndex) in tableGroups(b)" :key="`preview-group-${group.id}`" class="bg-amber-50 text-amber-700" :style="{ paddingLeft: `${groupIndex * 12}px` }">
                              <template v-if="group.headerCells?.length">
                                <td v-for="cell in group.headerCells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="border-b border-amber-200 px-2 py-1 font-bold" :class="cell.className" :style="getCustomTableCellStyle(b, cell, {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              </template>
                              <td v-else :colspan="b.columns.length + 1" class="border-b border-amber-200 px-2 py-1 text-left font-bold">
                                {{ groupHeaderPreview(group) }}
                              </td>
                            </tr>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" @click.stop="selectQuickFormatTarget({ kind: 'table-cell', block: b, column: col })" :style="getTableDetailStyle(b, col)" class="font-mono text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr v-for="(customRow, customRowIndex) in tableCustomRows(b)" :key="customRow.id" class="bg-slate-100 font-bold">
                              <td v-for="(cell, cellIndex) in customRow.cells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="px-2 py-1" :style="getCustomTableCellStyle(b, cell, b.columns[cellIndex] || {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              <td class="w-8 px-1 text-center" :style="getTableCellStyle(b, {})"><button type="button" @click.stop="removeTableCustomRow(b, customRowIndex)" class="border-none bg-transparent text-red-500">×</button></td>
                            </tr>
                            <tr class="bg-sky-50"><td :colspan="b.columns.length + 1" class="px-2 py-1 text-center"><button type="button" @click.stop="addTableCustomRow(b)" class="rounded border border-sky-200 bg-white px-2 py-0.5 text-[10px] font-black text-sky-700">+ Thêm hàng</button></td></tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                    
                    <!-- Other Block Types -->
                    <div v-if="b.type === 'text'" :class="getBlockScopeClass(b)" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                        @focus="onTextareaFocus"
                        @mouseup="captureTextSelection"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx" v-if="!isStaticCellCovered(b, rIdx, cIdx)"
                              :colspan="cell.colspan || 1" :rowspan="cell.rowspan || 1"
                              :style="getStaticTableCellStyle(b, row, cell, b.columns[cIdx] || {})"
                              :class="['relative group/td p-0', isStaticCellSelected(b, row, cell) ? 'ring-2 ring-inset ring-sky-500' : '']">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @click.stop="selectStaticCell($event, b, row, cell)"
                                @contextmenu.stop="openStaticCellContextMenu($event, b, row, cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-inherit"
                                v-html="activeCell === cell ? editingCellContent : staticCellContent(cell, row)"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'shape'" :style="{ ...getBlockStyle(b), height: `${b.height || 40}px` }" class="min-h-px"></div>
                    <div v-else-if="b.type === 'page-break'" class="flex h-5 items-center gap-2 text-[9px] font-bold uppercase text-rose-500"><span class="h-px flex-1 border-t border-dashed border-rose-300"></span>Ngắt trang<span class="h-px flex-1 border-t border-dashed border-rose-300"></span></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
                    </div>

                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'detail'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'" :class="getBlockScopeClass(subBlock)" :style="getBlockStyle(subBlock)">
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                         @focus="onTextareaFocus"
                         @mouseup="captureTextSelection"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- SECTION 3: FOOTER BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mt-auto transition-all relative group/band"
                :class="[
                  selectedBand === 'footer' ? 'border-emerald-400 bg-emerald-50/20' : 'border-slate-200',
                  blocks.footer.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'footer'" @dragover.prevent
                @drop="onBlockDrop($event, 'footer', blocks.footer.length)">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Report Footer Band
                </span>

                <div v-if="blocks.footer.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Footer. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Footer -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.footer" :key="b.id" :draggable="!b.locked"
                    @dragstart="onBlockDragStart($event, 'footer', idx)" @dragend="onBlockDragEnd"
                    @dragover.prevent @drop.stop="onCanvasBlockDrop($event, 'footer', idx, b)"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'footer'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="[selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white', b.visible === false ? 'opacity-45 border-dotted' : '', b.locked ? 'cursor-default' : '']">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type }}{{ b.locked ? ' · khóa' : '' }}{{ b.visible === false ? ' · ẩn khi in' : '' }}
                    </span>

                    <!-- Overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.footer.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('footer', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Block Visual Content -->
                    <div v-if="b.type === 'text'" :class="getBlockScopeClass(b)" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                         @focus="onTextareaFocus"
                         @mouseup="captureTextSelection"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Table Setup and Rendering -->
                    <div v-else-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <p v-if="tableGroups(b).length" class="mb-1 rounded bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">
                          <span v-for="(group, index) in tableGroups(b)" :key="group.id">
                            Cấp {{ index + 1 }}: {{ group.field }}<span v-if="group.enabledBy"> · Khi: {{ group.enabledBy }}</span><span v-if="index < tableGroups(b).length - 1"> → </span>
                          </span>
                        </p>
                        <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" @click.stop="selectQuickFormatTarget({ kind: 'table-header', block: b, column: col })" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" :style="styleObjectToCss(mergeConfiguredStyles({ textAlign: col.align || 'left', fontWeight: 'bold' }, col.headerStyle), true)" class="w-full bg-transparent border-none text-slate-800 focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="(group, groupIndex) in tableGroups(b)" :key="`preview-group-${group.id}`" class="bg-amber-50 text-amber-700" :style="{ paddingLeft: `${groupIndex * 12}px` }">
                              <template v-if="group.headerCells?.length">
                                <td v-for="cell in group.headerCells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="border-b border-amber-200 px-2 py-1 font-bold" :class="cell.className" :style="getCustomTableCellStyle(b, cell, {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              </template>
                              <td v-else :colspan="b.columns.length + 1" class="border-b border-amber-200 px-2 py-1 text-left font-bold">
                                {{ groupHeaderPreview(group) }}
                              </td>
                            </tr>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" @click.stop="selectQuickFormatTarget({ kind: 'table-cell', block: b, column: col })" :style="getTableDetailStyle(b, col)" class="font-mono text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr v-for="(customRow, customRowIndex) in tableCustomRows(b)" :key="customRow.id" class="bg-slate-100 font-bold">
                              <td v-for="(cell, cellIndex) in customRow.cells" :key="cell.id" @click.stop="selectQuickFormatTarget({ kind: 'custom-cell', block: b, cell })" :colspan="cell.colspan" class="px-2 py-1" :style="getCustomTableCellStyle(b, cell, b.columns[cellIndex] || {})">{{ customCellContent(cell, b.dataSource) }}</td>
                              <td class="w-8 px-1 text-center" :style="getTableCellStyle(b, {})"><button type="button" @click.stop="removeTableCustomRow(b, customRowIndex)" class="border-none bg-transparent text-red-500">×</button></td>
                            </tr>
                            <tr class="bg-sky-50"><td :colspan="b.columns.length + 1" class="px-2 py-1 text-center"><button type="button" @click.stop="addTableCustomRow(b)" class="rounded border border-sky-200 bg-white px-2 py-0.5 text-[10px] font-black text-sky-700">+ Thêm hàng</button></td></tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>

                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table :class="['w-full border-collapse border-none', getBlockScopeClass(b)]" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx" v-if="!isStaticCellCovered(b, rIdx, cIdx)"
                              :colspan="cell.colspan || 1" :rowspan="cell.rowspan || 1"
                              :style="getStaticTableCellStyle(b, row, cell, b.columns[cIdx] || {})"
                              :class="['relative group/td p-0', isStaticCellSelected(b, row, cell) ? 'ring-2 ring-inset ring-sky-500' : '']">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @click.stop="selectStaticCell($event, b, row, cell)"
                                @contextmenu.stop="openStaticCellContextMenu($event, b, row, cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-inherit"
                                v-html="activeCell === cell ? editingCellContent : staticCellContent(cell, row)"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'shape'" :style="{ ...getBlockStyle(b), height: `${b.height || 40}px` }" class="min-h-px"></div>
                    <div v-else-if="b.type === 'page-break'" class="flex h-5 items-center gap-2 text-[9px] font-bold uppercase text-rose-500"><span class="h-px flex-1 border-t border-dashed border-rose-300"></span>Ngắt trang<span class="h-px flex-1 border-t border-dashed border-rose-300"></span></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
                    </div>

                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'footer'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'" :class="getBlockScopeClass(subBlock)" :style="getBlockStyle(subBlock)">
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                         @focus="onTextareaFocus"
                         @mouseup="captureTextSelection"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

            </div>
            </div>
          </div>

          <!-- Column 3: Properties Inspector Sidebar (Right Panel) -->
          <div v-if="showRightPanel" class="w-1/4 min-w-[240px] max-w-[360px] bg-slate-50 border-l border-slate-200 p-4 overflow-y-auto flex flex-col gap-4 select-none shrink-0">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-200 block">Bảng Thuộc Tính (Properties)</span>
            
            <div v-if="!selectedBlock" class="text-center py-8 text-slate-400 text-xs italic">
              Chọn một Block trên khu vực thiết kế để tùy chỉnh thuộc tính chi tiết.
            </div>

            <!-- Block Settings Forms -->
            <div v-else class="flex flex-col gap-4">
              <!-- Type Info -->
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase">Khối Đang Chọn:</span>
                <p class="text-xs font-black text-slate-700 mt-0.5 capitalize">{{ selectedBlock.type }} ({{ selectedBlock.id }})</p>
              </div>

              <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-3xs">
                <button type="button" @click="toggleSelectedBlockVisibility" class="rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold hover:bg-sky-50">
                  {{ selectedBlock.visible === false ? 'Hiện khi in' : 'Ẩn khi in' }}
                </button>
                <button type="button" @click="toggleSelectedBlockLock" class="rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold hover:bg-amber-50">
                  {{ selectedBlock.locked ? 'Mở khóa' : 'Khóa vị trí' }}
                </button>
                <button type="button" @click="duplicateSelectedBlock" class="rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold hover:bg-sky-50">Nhân bản</button>
                <button type="button" @click="deleteSelectedBlock" :disabled="selectedBlock.locked" class="rounded-lg border border-red-200 px-2 py-1.5 text-[10px] font-bold text-red-600 hover:bg-red-50 disabled:opacity-35">Xóa</button>
                <label class="col-span-2 flex flex-col gap-1 border-t border-slate-100 pt-2 text-[10px] font-bold text-slate-500">
                  Điều kiện hiển thị khi in
                  <select v-model="selectedBlock.visibleWhen" @change="compileHtml" class="rounded-lg border border-slate-200 p-1.5 text-[10px] font-semibold">
                    <option value="">Luôn hiển thị</option>
                    <option v-for="parameter in conditionalParameterOptions" :key="parameter.value" :value="parameter.value">{{ parameter.label }}</option>
                  </select>
                </label>
                <label v-if="selectedBlock.visibleWhen" class="col-span-2 flex items-center justify-between text-[10px] font-bold text-slate-500">
                  Trạng thái điều kiện
                  <select v-model="selectedBlock.visibleWhenMode" @change="compileHtml" class="rounded-lg border border-slate-200 p-1.5 text-[10px] font-semibold">
                    <option value="truthy">Đúng / Có / 1</option>
                    <option value="falsy">Sai / Không / 0</option>
                  </select>
                </label>
              </div>

              <!-- Alignment & Font properties -->
              <div class="flex flex-col gap-2.5 bg-white p-3 border border-slate-200 rounded-xl shadow-3xs">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Định dạng kiểu chữ (Styles)</span>
                
                <!-- Align text -->
                <div v-if="!['spacer', 'divider', 'shape', 'page-break'].includes(selectedBlock.type)" class="flex items-center justify-between mt-1.5">
                  <span class="text-xs text-slate-500">Căn lề:</span>
                  <div class="flex bg-slate-100 p-0.5 rounded-lg">
                    <button @click="applyBlockTextStyle('textAlign', 'left')" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'left' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignLeft class="w-3.5 h-3.5" />
                    </button>
                    <button @click="applyBlockTextStyle('textAlign', 'center')" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'center' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignCenter class="w-3.5 h-3.5" />
                    </button>
                    <button @click="applyBlockTextStyle('textAlign', 'right')" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'right' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignRight class="w-3.5 h-3.5" />
                    </button>
                    <button @click="applyBlockTextStyle('textAlign', 'justify')" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'justify' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignJustify class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Font size slider -->
                <div v-if="!['spacer', 'divider', 'image', 'shape', 'page-break'].includes(selectedBlock.type)" class="flex flex-col gap-1 mt-2">
                  <div class="flex justify-between items-center text-xs text-slate-500">
                    <span>Cỡ chữ:</span>
                    <span class="font-bold text-slate-700">{{ selectedBlock.style.fontSize }}</span>
                  </div>
                  <input type="range" min="1" max="50" step="0.5"
                    :value="parseFloat(selectedBlock.style.fontSize) || 13"
                    @input="applyBlockTextStyle('fontSize', `${$event.target.value}px`)"
                    class="w-full accent-sky-600" />
                </div>

                <!-- Color & Font-Weight -->
                <div v-if="!['spacer', 'divider', 'image', 'shape', 'page-break'].includes(selectedBlock.type)" class="grid grid-cols-2 gap-2 mt-2">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Màu chữ:</span>
                    <input type="color" :value="colorInputValue(selectedBlock.style.color, '#1e293b')" @input="applyBlockTextStyle('color', $event.target.value)" class="w-full h-8 border border-slate-200 rounded-lg cursor-pointer" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Đậm (Tất cả):</span>
                    <button @click="applyBlockTextStyle('fontWeight', selectedBlock.style.fontWeight === 'bold' ? 'normal' : 'bold')"
                      class="h-8 rounded-lg border font-bold text-xs flex items-center justify-center cursor-pointer transition-colors"
                      :class="selectedBlock.style.fontWeight === 'bold' ? 'bg-sky-50 border-sky-300 text-sky-700' : 'bg-slate-50 border-slate-200 text-slate-600'">
                      <Bold class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Formatting Toolbar for Highlighted Selection -->
                <div v-if="['text', 'static-table'].includes(selectedBlock.type)" class="flex flex-col gap-1.5 mt-2 pt-2 border-t border-slate-100">
                  <span class="text-[10px] text-slate-400 font-bold uppercase">Định dạng chữ bôi đen:</span>
                  <div class="flex flex-wrap gap-1 bg-slate-100 p-1.5 rounded-lg border border-slate-200 select-none">
                    <button @mousedown.prevent="formatText('bold')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs font-bold cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In đậm (Ctrl+B)">B</button>
                    <button @mousedown.prevent="formatText('italic')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs italic cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In nghiêng (Ctrl+I)">I</button>
                    <button @mousedown.prevent="formatText('underline')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs underline cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Gạch chân (Ctrl+U)">U</button>
                    <button @mousedown.prevent="insertHtmlTag('<br>', '')" class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Xuống dòng">br</button>
                    <button @mousedown.prevent="insertHtmlTag('<b>', '</b>')" class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[11px] font-bold cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Thẻ Bold">&lt;b&gt;</button>
                  </div>
                </div>

                <!-- Paddings & Margins -->
                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Top:</span>
                    <input type="text" v-model="selectedBlock.style.paddingTop" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.paddingBottom" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Left:</span>
                    <input type="text" v-model="selectedBlock.style.paddingLeft" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Right:</span>
                    <input type="text" v-model="selectedBlock.style.paddingRight" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Top:</span>
                    <input type="text" v-model="selectedBlock.style.marginTop" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.marginBottom" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Left:</span>
                    <input type="text" v-model="selectedBlock.style.marginLeft" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Right:</span>
                    <input type="text" v-model="selectedBlock.style.marginRight" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                </div>

                <!-- Borders & Backgrounds -->
                <div class="flex flex-col gap-2.5 mt-2 pt-2 border-t border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Khung viền & Nền</span>
                  
                  <!-- Background Color picker -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Màu nền:</span>
                    <div class="flex items-center gap-1">
                      <input type="color" :value="colorInputValue(selectedBlock.style.backgroundColor, '#ffffff')" @input="selectedBlock.style.backgroundColor = $event.target.value; compileHtml()" class="w-8 h-8 border border-slate-200 rounded cursor-pointer" />
                      <button @click="selectedBlock.style.backgroundColor = ''; compileHtml()" class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-500 rounded border-none cursor-pointer">Xóa</button>
                    </div>
                  </div>

                  <!-- Border apply side dropdown -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Cạnh áp dụng viền:</span>
                    <select v-model="selectedBlock.style.borderSide" @change="onBorderSideChange(selectedBlock)" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 focus:outline-sky-500">
                      <option value="all">Tất cả các cạnh</option>
                      <option value="top">Cạnh trên</option>
                      <option value="bottom">Cạnh dưới</option>
                      <option value="left">Cạnh trái</option>
                      <option value="right">Cạnh phải</option>
                    </select>
                  </div>

                  <!-- Border style dropdown -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Kiểu đường viền:</span>
                    <select v-model="selectedBlock.style.borderStyle" @change="compileHtml" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 focus:outline-sky-500">
                      <option value="none">Không viền (none)</option>
                      <option value="solid">Đường liền (solid)</option>
                      <option value="dashed">Đường đứt nét (dashed)</option>
                      <option value="dotted">Đường chấm (dotted)</option>
                      <option value="double">Đường đôi (double)</option>
                    </select>
                  </div>

                  <!-- Border width input -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Độ dày viền:</span>
                    <input type="text" v-model="selectedBlock.style.borderWidth" @input="compileHtml" class="w-20 text-center text-xs border border-slate-200 rounded py-0.5" placeholder="1px" />
                  </div>

                  <!-- Border color picker -->
                  <div class="flex items-center justify-between" v-if="selectedBlock.style.borderStyle && selectedBlock.style.borderStyle !== 'none'">
                    <span class="text-xs text-slate-500">Màu đường viền:</span>
                    <input type="color" :value="colorInputValue(selectedBlock.style.borderColor, '#cbd5e1')" @input="selectedBlock.style.borderColor = $event.target.value; compileHtml()" class="w-8 h-8 border border-slate-200 rounded cursor-pointer" />
                  </div>

                  <!-- Border radius input -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Bo góc bo viền:</span>
                    <input type="text" v-model="selectedBlock.style.borderRadius" @input="compileHtml" class="w-20 text-center text-xs border border-slate-200 rounded py-0.5" placeholder="0px" />
                  </div>
                </div>

              </div>

              <!-- CONTENT EDITORS BY BLOCK TYPE -->
              
              <!-- 1. Text content editor -->
              <div v-if="selectedBlock.type === 'text'" class="flex flex-col gap-2">
                <div class="flex justify-between items-center select-none">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Soạn thảo văn bản:</span>
                  <div class="flex bg-slate-100 p-0.5 rounded-md text-[9px] font-bold">
                    <button @click="editorMode = 'visual'" class="px-2 py-0.5 rounded transition-all cursor-pointer border-none" :class="editorMode === 'visual' ? 'bg-white shadow-3xs text-sky-600 font-bold' : 'text-slate-400'">Trực quan</button>
                    <button @click="editorMode = 'code'" class="px-2 py-0.5 rounded transition-all cursor-pointer border-none" :class="editorMode === 'code' ? 'bg-white shadow-3xs text-sky-600 font-bold' : 'text-slate-400'">HTML Code</button>
                  </div>
                </div>
                
                <!-- Quick HTML format buttons -->
                <div v-if="editorMode === 'visual'" class="flex flex-wrap gap-1 bg-slate-100 p-1.5 rounded-lg border border-slate-200">
                  <button @mousedown.prevent="formatText('bold')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] font-bold cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In đậm">B</button>
                  <button @mousedown.prevent="formatText('italic')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] italic cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In nghiêng">I</button>
                  <button @mousedown.prevent="formatText('underline')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] underline cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Gạch chân">U</button>
                  <button @mousedown.prevent="insertHtmlTag('<br>', '')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Xuống dòng">br</button>
                  <button @mousedown.prevent="insertHtmlTag('<p>', '</p>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Đoạn văn">p</button>
                  <button @mousedown.prevent="insertHtmlTag('<span style=\'font-size:16px;font-weight:bold;\'>', '</span>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Chữ lớn">Lớn</button>
                  <button @mousedown.prevent="insertHtmlTag('<span style=\'color:#ef4444;font-weight:bold;\'>', '</span>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-red-600 font-bold active:scale-95 transition-transform" title="Chữ đỏ">Đỏ</button>
                  <button @mousedown.prevent="formatText('justifyCenter')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Căn giữa">Giữa</button>
                  <button @mousedown.prevent="formatText('justifyLeft')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Căn trái">Trái</button>
                </div>

                <!-- Visual Editor -->
                <div v-if="editorMode === 'visual'"
                  contenteditable="true"
                  @input="selectedBlock.content = $event.target.innerHTML; compileHtml()"
                  @focus="onTextareaFocus"
                  @mouseup="captureTextSelection"
                  class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-sans leading-relaxed min-h-[200px] bg-white outline-none"
                  v-html="editingContent"
                ></div>

                <!-- Source HTML Code Editor -->
                <textarea v-else v-model="selectedBlock.content" rows="10" @input="compileHtml"
                  @focus="onTextareaFocus"
                  class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-mono leading-relaxed" 
                  placeholder="Viết nội dung văn bản (hỗ trợ các thẻ <b>, <i>, <p>...)"></textarea>
              </div>

              <div v-else-if="selectedBlock.type === 'shape'" class="flex flex-col gap-1">
                <span class="text-[10px] font-bold uppercase text-slate-400">Chiều cao hình khối (px):</span>
                <input type="number" min="1" v-model.number="selectedBlock.height" @input="compileHtml" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-bold focus:outline-sky-500" />
              </div>

              <div v-else-if="selectedBlock.type === 'page-break'" class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-[10px] text-rose-700">
                Nội dung sau phần tử này sẽ bắt đầu ở trang in kế tiếp.
              </div>

              <!-- 2. Spacer height editor -->
              <div v-else-if="selectedBlock.type === 'spacer'" class="flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Chiều cao khoảng trống (px):</span>
                <input type="number" v-model.number="selectedBlock.height" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-bold" />
              </div>

              <!-- 3. Image block settings -->
              <div v-else-if="selectedBlock.type === 'image'" class="flex flex-col gap-3">
                <!-- 3a. Upload new logo/image file -->
                <div class="flex flex-col gap-1.5">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Tải logo/ảnh lên (Upload):</span>
                  <div class="flex items-center gap-2">
                    <label class="px-3.5 py-2 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-black rounded-lg cursor-pointer transition-colors border border-sky-200 flex items-center gap-1.5 w-fit">
                      📷 Chọn tệp ảnh
                      <input type="file" @change="handleImageUpload" accept="image/*" class="hidden" />
                    </label>
                    <button v-if="selectedBlock.imageUrl" @click="selectedBlock.imageUrl = ''; selectedBlock.content = ''; compileHtml()" 
                      class="px-2.5 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-lg cursor-pointer transition-colors">
                      Xóa ảnh
                    </button>
                    <span v-if="uploadingImage" class="text-xs text-slate-400 font-semibold animate-pulse">Đang tải...</span>
                  </div>
                </div>

                <!-- 3b. Direct path input -->
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Hoặc đường dẫn ảnh trực tiếp:</span>
                  <input type="text" v-model="selectedBlock.imageUrl" @input="compileHtml" placeholder="/uploads/... or http://..." class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-mono" />
                </div>

                <!-- 3c. Dynamic binding variable selector -->
                <div class="flex flex-col gap-1" v-if="!selectedBlock.imageUrl">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Hoặc liên kết biến dữ liệu ảnh:</span>
                  <div class="flex gap-2">
                    <input type="text" :value="selectedBlock.content" placeholder="Chưa có liên kết biến (Ví dụ: hotel.logo)" class="flex-1 text-xs border border-slate-200 rounded-lg p-2 bg-slate-50 font-mono font-bold" readonly />
                    <button v-if="selectedBlock.content" @click="selectedBlock.content = ''; compileHtml()" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-lg cursor-pointer">Xóa</button>
                  </div>
                </div>
              </div>

              <!-- 4. Table Block column and fields mapping configuration -->
              <div v-else-if="selectedBlock.type === 'table' && !selectedBlock.isNew" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Nguồn dữ liệu bảng lặp:</span>
                  <select v-model="selectedBlock.dataSource" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option v-for="source in fieldList.lists" :key="source.value" :value="source.value">{{ source.label }} [{{ source.value }}]</option>
                  </select>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3">
                  <label class="flex cursor-pointer items-center justify-between gap-3">
                    <div>
                      <div class="text-xs font-black text-slate-700">Nhóm dữ liệu</div>
                      <div class="text-[10px] font-normal text-slate-500">Tạo dòng tiêu đề cho từng nhóm trong bảng.</div>
                    </div>
                    <input type="checkbox" :checked="tableGroups(selectedBlock).length > 0" @change="toggleTableGrouping(selectedBlock, $event.target.checked)" class="h-4 w-4 accent-sky-600" />
                  </label>
                </div>

                <div v-if="tableGroups(selectedBlock).length" class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3">
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="text-[10px] font-black uppercase text-slate-500">Các cấp nhóm</div>
                      <div class="text-[10px] text-slate-400">Thứ tự từ ngoài vào trong.</div>
                    </div>
                    <button type="button" @click="addTableGroup(selectedBlock)" class="rounded-lg border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-black text-sky-700">+ Thêm cấp nhóm</button>
                  </div>

                  <div v-for="(group, groupIndex) in tableGroups(selectedBlock)" :key="group.id" class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5">
                    <div class="flex items-center justify-between">
                      <span class="text-[10px] font-black text-slate-600">Cấp {{ groupIndex + 1 }}</span>
                      <div class="flex gap-1">
                        <button type="button" :disabled="groupIndex === 0" @click="moveTableGroup(selectedBlock, groupIndex, -1)" class="rounded border border-slate-200 bg-white px-1.5 text-xs disabled:opacity-30">↑</button>
                        <button type="button" :disabled="groupIndex === tableGroups(selectedBlock).length - 1" @click="moveTableGroup(selectedBlock, groupIndex, 1)" class="rounded border border-slate-200 bg-white px-1.5 text-xs disabled:opacity-30">↓</button>
                        <button type="button" @click="removeTableGroup(selectedBlock, groupIndex)" class="rounded border border-red-200 bg-red-50 px-1.5 text-xs text-red-600">×</button>
                      </div>
                    </div>

                    <select v-model="group.field" @change="updateTableGroups(selectedBlock)" class="w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-semibold">
                      <option v-for="field in getListFields(selectedBlock.dataSource)" :key="field.value" :value="groupingFieldValue(field)">
                        {{ field.label }} [{{ groupingFieldValue(field) }}]
                      </option>
                    </select>

                    <div class="grid grid-cols-2 gap-2">
                      <select v-model="group.enabledBy" @change="updateTableGroups(selectedBlock)" class="rounded-lg border border-slate-200 bg-white p-2 text-[11px]">
                        <option value="">Luôn nhóm</option>
                        <option v-for="parameter in conditionalParameterOptions" :key="parameter.value" :value="parameter.value">Khi {{ parameter.label }}</option>
                      </select>
                      <select v-model="group.sort" @change="updateTableGroups(selectedBlock)" class="rounded-lg border border-slate-200 bg-white p-2 text-[11px]">
                        <option value="ASC">Tăng dần</option>
                        <option value="DESC">Giảm dần</option>
                      </select>
                    </div>

                    <textarea v-model="group.label" @input="updateTableGroups(selectedBlock)" rows="2" class="w-full rounded-lg border border-slate-200 bg-white p-2 text-[11px] font-mono" :placeholder="`Nhóm: {{row.${group.field}}}`"></textarea>
                  </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-2">
                  <div class="mb-2 flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase text-slate-500">Các ô tiêu đề nhóm</span>
                    <button type="button" @click="addGroupHeaderCell(selectedBlock, tableGroups(selectedBlock)[0])" class="rounded border border-amber-200 bg-white px-2 py-1 text-[10px] font-bold text-amber-700">+ Thêm ô</button>
                  </div>
                  <div v-for="(cell, cellIndex) in (tableGroups(selectedBlock)[0]?.headerCells || [])" :key="cell.id" class="mb-2 rounded border border-slate-200 bg-white p-2">
                    <div class="mb-2 flex items-center justify-between"><span class="text-[10px] font-bold text-slate-500">Ô {{ cellIndex + 1 }}</span><button type="button" @click="removeGroupHeaderCell(selectedBlock, tableGroups(selectedBlock)[0], cellIndex)" class="text-xs text-red-600">Xóa</button></div>
                    <textarea v-if="cell.type === 'text'" v-model="cell.content" @input="updateTableGroups(selectedBlock)" rows="2" class="mb-2 w-full rounded border border-slate-200 p-2 text-[11px] font-mono" placeholder="Nội dung hoặc {{row.Field}}"></textarea>
                    <input v-else v-model="cell.binding" @input="updateTableGroups(selectedBlock)" class="mb-2 w-full rounded border border-slate-200 p-2 text-[11px] font-mono" placeholder="row.Field hoặc group.distinct.Field" />
                    <div class="grid grid-cols-3 gap-2"><select v-model="cell.type" @change="updateTableGroups(selectedBlock)" class="rounded border border-slate-200 p-1 text-[10px]"><option value="text">Văn bản</option><option value="binding">Binding</option><option value="count">Đếm</option><option value="distinct_count">Đếm khác nhau</option></select><input v-model.number="cell.colspan" @input="updateTableGroups(selectedBlock)" type="number" min="1" class="rounded border border-slate-200 p-1 text-[10px]" title="Colspan" /><select v-model="cell.align" @change="updateTableGroups(selectedBlock)" class="rounded border border-slate-200 p-1 text-[10px]"><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select></div>
                    <div class="mt-2 grid grid-cols-2 gap-2"><input v-model="cell.fontSize" @input="updateTableGroups(selectedBlock)" class="rounded border border-slate-200 p-1 text-[10px]" placeholder="Cỡ chữ: 12px" /><select v-model="cell.fontWeight" @change="updateTableGroups(selectedBlock)" class="rounded border border-slate-200 p-1 text-[10px]"><option value="">Mặc định (đậm)</option><option value="normal">Thường</option><option value="bold">Đậm</option></select></div>
                  </div>
                </div>

                <div class="flex flex-col gap-2 rounded-xl border border-sky-200 bg-sky-50/60 p-3">
                  <div class="flex items-center justify-between gap-2">
                    <div>
                      <div class="text-xs font-black text-slate-700">Hàng tùy chỉnh trong Detail Table</div>
                      <div class="text-[10px] text-slate-500">Mỗi hàng có nhiều ô và mỗi ô tự chọn nội dung hoặc phép tổng hợp.</div>
                    </div>
                    <button type="button" @click="addTableCustomRow(selectedBlock)" class="rounded-lg border border-sky-200 bg-white px-2 py-1 text-[10px] font-black text-sky-700">+ Thêm hàng</button>
                  </div>

                  <div v-for="(customRow, rowIndex) in tableCustomRows(selectedBlock)" :key="customRow.id" class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-2">
                    <div class="flex items-center justify-between">
                      <span class="text-[10px] font-black text-slate-600">Hàng {{ rowIndex + 1 }}</span>
                      <div class="flex gap-1">
                        <button type="button" :disabled="rowIndex === 0" @click="moveTableCustomRow(selectedBlock, rowIndex, -1)" class="rounded border border-slate-200 px-1.5 text-xs disabled:opacity-30">↑</button>
                        <button type="button" :disabled="rowIndex === tableCustomRows(selectedBlock).length - 1" @click="moveTableCustomRow(selectedBlock, rowIndex, 1)" class="rounded border border-slate-200 px-1.5 text-xs disabled:opacity-30">↓</button>
                        <button type="button" @click="removeTableCustomRow(selectedBlock, rowIndex)" class="rounded border border-red-200 bg-red-50 px-1.5 text-xs text-red-600">×</button>
                      </div>
                    </div>

                    <select v-model="customRow.enabledBy" @change="compileHtml" class="rounded-lg border border-slate-200 p-2 text-[11px]">
                      <option value="">Luôn hiển thị</option>
                      <option v-for="parameter in conditionalParameterOptions" :key="parameter.value" :value="parameter.value">Khi {{ parameter.label }}</option>
                    </select>

                    <div class="grid grid-cols-2 gap-2">
                      <select v-model="customRow.scope" @change="compileHtml" class="rounded-lg border border-slate-200 p-2 text-[11px]">
                        <option value="table">Toàn bảng</option>
                        <option value="group">Theo cấp nhóm</option>
                        <option value="detail">Theo từng dòng dữ liệu</option>
                      </select>
                      <select v-if="customRow.scope === 'group'" v-model.number="customRow.level" @change="compileHtml" class="rounded-lg border border-slate-200 p-2 text-[11px]">
                        <option v-for="(group, groupIndex) in tableGroups(selectedBlock)" :key="group.id" :value="groupIndex">Cấp {{ groupIndex + 1 }}</option>
                      </select>
                      <div v-else class="rounded-lg border border-slate-100 bg-slate-50 p-2 text-[10px] text-slate-500">
                        {{ customRowScopeLabel(customRow.scope) }}
                      </div>
                    </div>

                    <div v-for="(cell, cellIndex) in customRow.cells" :key="cell.id" class="flex flex-col gap-1.5 rounded-lg border border-slate-200 bg-slate-50 p-2">
                      <div class="flex items-center justify-between"><span class="text-[10px] font-bold">Ô {{ cellIndex + 1 }}</span><button type="button" :disabled="customRow.cells.length <= 1" @click="customRow.cells.splice(cellIndex, 1); compileHtml()" class="border-none bg-transparent text-red-500 disabled:opacity-30">×</button></div>
                      <select v-model="cell.type" @change="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]">
                        <option value="text">Văn bản / placeholder</option>
                        <option value="binding">Binding dữ liệu</option>
                        <option value="count">Đếm số dòng</option>
                        <option value="sum">Tổng theo trường</option>
                        <option value="distinct_count">Đếm không trùng</option>
                      </select>
                      <textarea v-if="cell.type === 'text'" v-model="cell.content" @input="compileHtml" rows="2" class="rounded border border-slate-200 bg-white p-1.5 text-[11px] font-mono" placeholder="Tổng hoặc {{parameters.p_type}}"></textarea>
                      <input v-if="cell.type === 'binding'" v-model="cell.binding" @input="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px] font-mono" placeholder="summary.row_count" />
                      <select v-if="['sum', 'distinct_count'].includes(cell.type)" v-model="cell.aggregateField" @change="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px] font-mono">
                        <option v-for="field in getListFields(selectedBlock.dataSource)" :key="field.value" :value="groupingFieldValue(field)">{{ field.label }} [{{ groupingFieldValue(field) }}]</option>
                      </select>
                      <div class="grid grid-cols-3 gap-1.5">
                        <input type="number" min="1" :max="selectedBlock.columns.length" v-model.number="cell.colspan" @input="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]" placeholder="Colspan" />
                        <select v-model="cell.align" @change="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]"><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select>
                        <select v-model="cell.format" @change="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]"><option value="">Mặc định</option><option value="number">Định dạng số</option></select>
                      </div>
                      <div class="grid grid-cols-3 gap-1.5">
                        <label class="flex flex-col gap-1 text-[9px] font-bold text-slate-500">Màu nền<input type="color" :value="cell.backgroundColor || '#ffffff'" @input="cell.backgroundColor = $event.target.value; compileHtml()" class="h-7 w-full rounded border border-slate-200 bg-white p-0.5" /></label>
                        <label class="flex flex-col gap-1 text-[9px] font-bold text-slate-500">Màu chữ<input type="color" :value="cell.color || '#1e293b'" @input="cell.color = $event.target.value; compileHtml()" class="h-7 w-full rounded border border-slate-200 bg-white p-0.5" /></label>
                        <label class="flex flex-col gap-1 text-[9px] font-bold text-slate-500">Màu viền<input type="color" :value="cell.borderColor || '#cbd5e1'" @input="cell.borderColor = $event.target.value; compileHtml()" class="h-7 w-full rounded border border-slate-200 bg-white p-0.5" /></label>
                      </div>
                      <div class="grid grid-cols-2 gap-1.5">
                        <input v-model="cell.fontSize" @input="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]" placeholder="Cỡ chữ: 12px" />
                        <select v-model="cell.fontWeight" @change="compileHtml" class="rounded border border-slate-200 bg-white p-1.5 text-[11px]"><option value="">Mặc định (đậm)</option><option value="normal">Thường</option><option value="bold">Đậm</option></select>
                      </div>
                      <button type="button" @click="cell.backgroundColor = ''; cell.color = ''; cell.borderColor = ''; compileHtml()" class="self-end border-none bg-transparent text-[9px] font-bold text-slate-500 underline">Đặt lại màu</button>
                    </div>
                    <button type="button" @click="addTableCustomCell(customRow)" class="rounded border border-dashed border-sky-300 bg-sky-50 px-2 py-1 text-[10px] font-bold text-sky-700">+ Thêm ô</button>
                  </div>
                </div>

                <!-- Table Style option -->
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Kiểu hiển thị đường kẻ:</span>
                  <select v-model="selectedBlock.tableStyle" @change="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="grid">Lưới đầy đủ (grid)</option>
                    <option value="horizontal">Chỉ kẻ dòng ngang (horizontal)</option>
                    <option value="none">Không kẻ đường viền (none)</option>
                  </select>
                </div>

                <!-- Columns manager -->
                <div class="flex flex-col gap-2">
                  <div class="flex justify-between items-center pb-1 border-b border-slate-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Cột của bảng</span>
                    <button @click="addTableColumn(selectedBlock)"
                      class="px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-600 text-[10px] font-black border-none rounded-lg cursor-pointer">
                      Thêm Cột
                    </button>
                  </div>
                  
                  <!-- Columns list items -->
                  <div class="flex flex-col gap-2.5 max-h-[250px] overflow-y-auto pr-1">
                    <div v-for="(col, cIdx) in selectedBlock.columns" :key="cIdx" class="border border-slate-200 rounded-lg p-2.5 bg-white shadow-3xs flex flex-col gap-1.5 relative group/col">
                      <!-- Delete Column -->
                      <button @click="selectedBlock.columns.splice(cIdx, 1)" class="absolute top-2 right-2 text-slate-400 hover:text-red-600 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                      
                      <!-- Col Header -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Tiêu đề cột:</span>
                        <input type="text" v-model="col.header" @input="compileHtml" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 font-bold" />
                      </div>
                      
                      <!-- Alignment -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Căn lề cột:</span>
                        <select v-model="col.align" @change="compileHtml" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 font-semibold">
                          <option value="left">Căn trái (Left)</option>
                          <option value="center">Căn giữa (Center)</option>
                          <option value="right">Căn phải (Right)</option>
                        </select>
                      </div>
                      
                      <!-- Col Value binding selector -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Biến dữ liệu ánh xạ:</span>
                        <select v-model="col.value" @change="compileHtml" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 font-mono">
                          <option v-for="f in getListFields(selectedBlock.dataSource)" :key="f.value" :value="f.value">
                            {{ f.label }} [{{ f.value }}]
                          </option>
                        </select>
                      </div>

                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Định dạng dữ liệu:</span>
                        <select v-model="col.format" @change="compileHtml" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 font-semibold">
                          <option value="">Mặc định</option>
                          <option value="number">Số - phân cách hàng nghìn</option>
                        </select>
                      </div>

                      <!-- Col Width -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Độ rộng (%):</span>
                        <input type="text" v-model="col.width" @input="compileHtml" class="text-[11px] border border-slate-200 rounded px-1.5 py-0.5 font-mono" placeholder="20% hoặc auto" />
                      </div>

                      <div class="mt-1 grid grid-cols-2 gap-2 border-t border-slate-100 pt-2">
                        <div class="flex flex-col gap-1 rounded border border-slate-100 p-1.5">
                          <span class="text-[9px] font-bold text-slate-500">STYLE TIÊU ĐỀ</span>
                          <select v-model="col.headerStyle.fontWeight" @change="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]"><option value="">Mặc định (đậm)</option><option value="normal">Thường</option><option value="bold">Đậm</option></select>
                          <select v-model="col.headerStyle.textAlign" @change="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]"><option value="">Căn lề theo cột</option><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select>
                          <input v-model="col.headerStyle.fontSize" @input="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]" placeholder="Cỡ chữ: 12px" />
                          <div class="flex gap-1"><input type="color" :value="colorInputValue(col.headerStyle.color, '#1e293b')" @input="col.headerStyle.color = $event.target.value; compileHtml()" class="h-7 min-w-0 flex-1 rounded border" title="Màu chữ" /><input type="color" :value="colorInputValue(col.headerStyle.backgroundColor, '#ffffff')" @input="col.headerStyle.backgroundColor = $event.target.value; compileHtml()" class="h-7 min-w-0 flex-1 rounded border" title="Màu nền" /></div>
                          <button type="button" @click="col.headerStyle = normalizeElementTextStyle(); compileHtml()" class="border-none bg-transparent text-[9px] text-slate-500 underline">Đặt lại</button>
                        </div>
                        <div class="flex flex-col gap-1 rounded border border-slate-100 p-1.5">
                          <span class="text-[9px] font-bold text-slate-500">STYLE DỮ LIỆU</span>
                          <select v-model="col.cellStyle.fontWeight" @change="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]"><option value="">Mặc định</option><option value="normal">Thường</option><option value="bold">Đậm</option></select>
                          <select v-model="col.cellStyle.textAlign" @change="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]"><option value="">Căn lề theo cột</option><option value="left">Trái</option><option value="center">Giữa</option><option value="right">Phải</option></select>
                          <input v-model="col.cellStyle.fontSize" @input="compileHtml" class="rounded border border-slate-200 p-1 text-[10px]" placeholder="Cỡ chữ: 12px" />
                          <div class="flex gap-1"><input type="color" :value="colorInputValue(col.cellStyle.color, '#1e293b')" @input="col.cellStyle.color = $event.target.value; compileHtml()" class="h-7 min-w-0 flex-1 rounded border" title="Màu chữ" /><input type="color" :value="colorInputValue(col.cellStyle.backgroundColor, '#ffffff')" @input="col.cellStyle.backgroundColor = $event.target.value; compileHtml()" class="h-7 min-w-0 flex-1 rounded border" title="Màu nền" /></div>
                          <button type="button" @click="col.cellStyle = normalizeElementTextStyle(); compileHtml()" class="border-none bg-transparent text-[9px] text-slate-500 underline">Đặt lại</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 4b. Static Table configuration -->
              <div v-else-if="selectedBlock.type === 'static-table'" class="flex flex-col gap-3">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Quản lý dòng & cột:</span>
                <div class="grid grid-cols-2 gap-2">
                  <button @click="addStaticRow(selectedBlock)" class="px-2.5 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-lg border border-sky-200 cursor-pointer">
                    + Thêm hàng
                  </button>
                  <button @click="addStaticColumn(selectedBlock)" class="px-2.5 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-lg border border-sky-200 cursor-pointer">
                    + Thêm cột
                  </button>
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Kiểu hiển thị đường kẻ:</span>
                  <select v-model="selectedBlock.tableStyle" @change="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="grid">Lưới đầy đủ (grid)</option>
                    <option value="horizontal">Chỉ kẻ dòng ngang (horizontal)</option>
                    <option value="none">Không kẻ đường viền (none)</option>
                  </select>
                </div>

                <!-- Custom width inputs for columns -->
                <div class="flex flex-col gap-2 mt-2">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Độ rộng các cột:</span>
                  <div class="flex flex-col gap-2 max-h-[150px] overflow-y-auto">
                    <div v-for="(col, cIdx) in selectedBlock.columns" :key="cIdx" class="flex justify-between items-center p-2 bg-white border border-slate-200 rounded-lg text-xs">
                      <span class="font-bold text-slate-600">Cột {{ cIdx + 1 }}:</span>
                      <div class="flex items-center gap-1.5">
                        <input type="text" v-model="col.width" @input="compileHtml" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono" placeholder="50%" />
                        <button @click="deleteStaticColumn(selectedBlock, cIdx)" class="px-1.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[10px] border border-red-200 cursor-pointer font-bold">×</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-2 rounded-lg border border-sky-200 bg-sky-50 p-2 text-[11px] text-sky-800">
                  Click trực tiếp vào ô trên canvas để chỉnh thuộc tính ô tại thanh công cụ phía trên. Danh sách hàng/ô không hiển thị ở đây để hỗ trợ bảng lớn.
                </div>
              </div>

              <!-- 5. Columns Layout Block configuration -->
              <div v-else-if="selectedBlock.type === 'columns'" class="flex flex-col gap-3">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Bố cục chia cột (Layout):</span>
                <div class="grid grid-cols-2 gap-2">
                  <button @click="setColumnWidths(selectedBlock, '50-50')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '50%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    50% - 50%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '30-70')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '30%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    30% - 70%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '70-30')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '70%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    70% - 30%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '33-33-33')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 3 ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    33% - 33% - 33%
                  </button>
                </div>
              </div>

            </div>
          </div>
        </template>

        <!-- ================== TAB 2: LIVE PREVIEW ================== -->
        <template v-else-if="activeTab === 'preview'">
          <div class="flex-1 bg-slate-200 p-6 overflow-y-auto flex flex-col items-center">
            <!-- Iframe container with print simulation borders -->
            <div class="flex justify-between items-center max-w-full mb-3 shrink-0" :style="{ width: pageDimensions.width }">
              <span class="text-xs text-slate-500 font-bold">Xem trước thực tế (A4/A5 preview)</span>
              <button @click="loadPreview" class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold flex items-center gap-1 cursor-pointer transition-colors shadow-3xs">
                <RefreshCw class="w-3.5 h-3.5" :class="loadingPreview ? 'animate-spin' : ''" /> Làm mới
              </button>
            </div>
            
            <div v-if="loadingPreview" class="shrink-0 bg-white shadow-lg border border-slate-300 flex flex-col items-center justify-center gap-3" :style="{ width: pageDimensions.width, height: pageDimensions.height }">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
              <p class="text-xs text-slate-400 italic">Đang biên dịch và render dữ liệu giả lập từ hệ thống...</p>
            </div>
            
            <iframe v-else-if="previewHtml" :srcdoc="previewHtml" 
              class="shrink-0 bg-white shadow-lg border border-slate-300 rounded-sm transition-all"
              :style="{
                width: pageDimensions.width,
                minHeight: pageDimensions.height
              }"></iframe>
          </div>
        </template>

        <!-- ================== TAB 3: CUSTOM CSS ================== -->
        <template v-else-if="activeTab === 'css'">
          <div class="flex-1 bg-slate-50 p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-700 uppercase">Mã CSS Tùy Biến (Custom CSS Styles)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Các class định nghĩa ở đây sẽ được nạp và áp dụng trực tiếp lên bản in. Ví dụ: <code>h1 { font-family: monospace; }</code></p>
              </div>
            </div>
            <textarea v-if="template" v-model="template.css" rows="20"
              class="flex-1 text-xs border border-slate-200 rounded-2xl p-4 focus:outline-sky-500 font-mono leading-relaxed bg-white shadow-2xs resize-none" 
              placeholder="/* Nhập CSS của bạn ở đây... */&#10;h1 {&#10;    color: #0284c7;&#10;    font-size: 24px;&#10;}&#10;table {&#10;    margin-top: 15px;&#10;}"></textarea>
          </div>
        </template>

        <!-- ================== TAB 4: VERSIONS ================== -->
        <template v-else-if="activeTab === 'versions'">
          <div class="flex-1 bg-slate-50 p-6 overflow-y-auto flex flex-col gap-4">
            <h3 class="text-sm font-bold text-slate-700 uppercase pb-2 border-b border-slate-200">Lịch sử thay đổi mẫu biểu</h3>
            
            <div class="overflow-hidden border border-slate-200 rounded-xl bg-white shadow-2xs">
              <table class="w-full text-sm text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                    <th class="p-3">Phiên bản</th>
                    <th class="p-3">Ghi chú thay đổi</th>
                    <th class="p-3">Người cập nhật</th>
                    <th class="p-3">Thời gian tạo</th>
                    <th class="p-3 text-right">Khôi phục (Rollback)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="v in versions" :key="v.id" class="border-b border-slate-100 hover:bg-slate-50/50">
                    <td class="p-3 font-bold text-sky-600 font-mono">v{{ v.version }}</td>
                    <td class="p-3 text-slate-700 font-semibold">{{ v.note }}</td>
                    <td class="p-3 text-slate-500">{{ v.updater ? v.updater.name : 'Hệ thống' }}</td>
                    <td class="p-3 text-slate-400 font-semibold text-xs">{{ new Date(v.created_at).toLocaleString() }}</td>
                    <td class="p-3 text-right">
                      <button @click="rollbackToVersion(v.id)" 
                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-sky-50 border border-slate-200 hover:border-sky-300 text-slate-600 hover:text-sky-700 font-extrabold rounded-lg text-[10px] cursor-pointer transition-colors flex items-center gap-1.5 ml-auto uppercase shadow-3xs">
                        <RotateCcw class="w-3.5 h-3.5" /> Khôi phục
                      </button>
                    </td>
                  </tr>
                  <tr v-if="versions.length === 0">
                    <td colspan="5" class="p-6 text-center text-slate-400 italic">Chưa ghi nhận lịch sử phiên bản nào.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>

      </div>

      <div v-else class="flex-1 flex flex-col items-center justify-center gap-3">
        <p class="text-xs text-slate-400 font-semibold">Chưa có dữ liệu mẫu biểu.</p>
      </div>
    </div>
  </div>

  <!-- Save Overlay Dialog (Notes input for version control) -->
  <div v-if="showSaveModal" class="fixed inset-0 bg-slate-900/60 z-60 flex items-center justify-center p-4 backdrop-blur-xs">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 border border-slate-200 shadow-2xl flex flex-col gap-4 animate-scale-up">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-800 uppercase flex items-center gap-1.5">
          <Save class="w-4 h-4 text-sky-600" /> Nhập ghi chú phiên bản
        </h3>
        <button @click="showSaveModal = false" class="text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer">
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="flex flex-col gap-1.5">
        <span class="text-[10px] font-bold text-slate-400 uppercase">Mô tả lý do thay đổi (Note):</span>
        <input type="text" v-model="note" placeholder="Ví dụ: Thay đổi cỡ chữ, định dạng lại bảng..." 
          class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-semibold"
          @keyup.enter="saveTemplateWithVersion" />
        <p class="text-[10px] text-slate-400 leading-normal">
          Nhập mô tả sẽ giúp đội ngũ kỹ thuật dễ dàng phân biệt và tìm lại lịch sử biểu mẫu khi cần rollback.
        </p>
      </div>

      <div class="flex gap-2 justify-end pt-2 border-t border-slate-100">
        <button @click="showSaveModal = false" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-black rounded-xl cursor-pointer">
          HỦY BỎ
        </button>
        <button @click="saveTemplateWithVersion" :disabled="saving" 
          class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-xl cursor-pointer flex items-center gap-1.5 disabled:opacity-50">
          <Check class="w-4 h-4" /> {{ saving ? 'ĐANG LƯU...' : 'XÁC NHẬN LƯU' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped adjustments for smooth ranges */
input[type="range"] {
  height: 6px;
  border-radius: 4px;
}
.report-header-band, .report-detail-band, .report-footer-band {
  width: 100%;
}
</style>
