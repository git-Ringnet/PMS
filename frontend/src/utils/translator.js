import { createApp, reactive, ref } from 'vue'
import http from '@/services/http'
import TranslationEditor from '@/components/TranslationEditor.vue'
import {
  hasDictionary,
  normalizeText,
  onDictionaryChange,
  refreshDictionary,
  toSource,
  translateText
} from './i18n-dictionary'

/**
 * Lớp dịch giao diện gắn thêm, không cần sửa các module hiện có:
 * - Thay chữ gốc tiếng Việt trên màn hình bằng bản dịch (nội dung chữ + placeholder/title/aria-label).
 * - Chế độ dịch: người có quyền bấm vào chữ trên màn hình để sửa bản dịch ngay tại chỗ.
 * - Ghi nhận vị trí của chữ (màn hình / popup / menu) để trang quản lý hiển thị theo cây.
 * Khi đang dùng tiếng Việt và không có chữ nào bị sửa, lớp này không chạy.
 */

const SKIP_SELECTOR = 'script,style,textarea,noscript,code,pre,[contenteditable="true"],[contenteditable=""],[data-no-translate],.notranslate'
const ATTRS = ['placeholder', 'title', 'aria-label']
const EDIT_KEY = 'pms_i18n_edit'
const FIND_KEY = 'pms_i18n_find'

const textState = new WeakMap() // Text node -> { src, out }
const attrState = new WeakMap() // Element -> { [attr]: { src, out } }

let installed = false
let observer = null
let editAllowed = false

export const isEditMode = ref(false)
export const editorState = reactive({ open: false, source: '', area: '', route: '', anchor: null, notice: '' })

// ─── Dịch DOM ──────────────────────────────────────────────────────────────

function isSkipped(el) {
  return !el || !!el.closest(SKIP_SELECTOR)
}

function processText(node) {
  const parent = node.parentElement
  const raw = node.nodeValue
  if (!parent || !raw || isSkipped(parent)) return
  const state = textState.get(node)
  const ownWrite = state && raw === state.out
  if (!ownWrite && !/\p{L}/u.test(raw)) {
    textState.delete(node)
    return
  }

  const src = ownWrite ? state.src : raw
  const out = translateText(src)
  if (out != null) {
    // <option> không có value thì trình duyệt lấy chữ hiển thị làm giá trị (v-model, submit form)
    // → giữ giá trị là chữ gốc, chỉ đổi chữ hiển thị
    if (parent.tagName === 'OPTION' && !parent.hasAttribute('value') && !('_value' in parent)) {
      parent.setAttribute('value', normalizeText(parent.textContent))
    }
    textState.set(node, { src, out })
    if (raw !== out) node.nodeValue = out
  } else {
    if (ownWrite) node.nodeValue = src // bản dịch vừa bị xóa → trả lại chữ gốc
    textState.delete(node)
  }

  if (isEditMode.value) markEditable(parent, src)
}

function processAttrs(el) {
  if (isSkipped(el)) return
  for (const attr of ATTRS) {
    if (!el.hasAttribute(attr)) continue
    const raw = el.getAttribute(attr)
    const states = attrState.get(el) || {}
    const state = states[attr]
    const ownWrite = state && raw === state.out
    const src = ownWrite ? state.src : raw
    const out = translateText(src)
    if (out != null) {
      states[attr] = { src, out }
      attrState.set(el, states)
      if (raw !== out) el.setAttribute(attr, out)
    } else {
      if (ownWrite) el.setAttribute(attr, src)
      delete states[attr]
    }
    if (isEditMode.value && attr === 'placeholder' && /\p{L}/u.test(src || '')) {
      el.setAttribute('data-i18n-placeholder', normalizeText(toSource(src)))
      collect(toSource(src), el)
    }
  }
}

function walk(root) {
  if (!root) return
  if (root.nodeType === Node.TEXT_NODE) {
    processText(root)
    return
  }
  if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return
  if (root.nodeType === Node.ELEMENT_NODE) {
    if (isSkipped(root)) return
    processAttrs(root)
  }
  root.querySelectorAll?.('[placeholder],[title],[aria-label]').forEach(processAttrs)
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  let node = walker.nextNode()
  while (node) {
    processText(node)
    node = walker.nextNode()
  }
}

function onMutations(mutations) {
  for (const m of mutations) {
    if (m.type === 'characterData') processText(m.target)
    else if (m.type === 'attributes') processAttrs(m.target)
    else m.addedNodes.forEach(walk)
  }
}

function shouldRun() {
  return hasDictionary() || isEditMode.value
}

/** Bật/tắt theo dõi DOM tùy theo có từ điển / chế độ dịch, rồi dịch lại toàn màn hình. */
function sync() {
  if (!document.body) return
  if (shouldRun() && !observer) {
    observer = new MutationObserver(onMutations)
    observer.observe(document.body, {
      subtree: true,
      childList: true,
      characterData: true,
      attributes: true,
      attributeFilter: ATTRS
    })
  }
  if (observer) walk(document.body)
  if (!shouldRun() && observer) {
    observer.disconnect()
    observer = null
  }
}

// ─── Vị trí của chữ ──────────────────────────────────────────────────────────

/** Chữ gốc của một phần tử (đổi ngược các đoạn đã dịch). */
function sourceTextOf(el) {
  if (!el) return ''
  const parts = []
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT)
  let node = walker.nextNode()
  while (node) {
    const state = textState.get(node)
    parts.push(state && node.nodeValue === state.out ? state.src : node.nodeValue)
    node = walker.nextNode()
  }
  return toSource(normalizeText(parts.join(' '))).slice(0, 80)
}

function pageLabel() {
  const title = (document.title || '').split(' - ')[0].trim() || 'Màn hình'
  const tab = new URLSearchParams(location.search).get('tab')
  return tab ? `${title} › ${tab}` : title
}

export function describeArea(el) {
  if (el.closest('header, nav')) return 'Thanh tiêu đề'
  const parts = [pageLabel()]
  const dialog = el.closest('[role="dialog"], .fixed.inset-0, [class*="modal"], [class*="Modal"]')
  if (dialog) {
    const title = dialog.querySelector('h1, h2, h3, h4')
    parts.push('Popup' + (title ? ` ${sourceTextOf(title)}` : ''))
  } else if (el.closest('thead')) {
    parts.push('Tiêu đề bảng')
  } else if (el.closest('aside')) {
    parts.push('Thanh bên')
  }
  return parts.join(' › ')
}

function currentRoute() {
  return location.pathname + location.search
}

// ─── Ghi nhận chữ & vị trí (chỉ người có quyền sửa bản dịch) ─────────────────

const collected = new Set()
const menuSources = new Set()
let queue = []
let flushTimer = null

function scheduleFlush() {
  if (flushTimer) return
  flushTimer = setTimeout(flushQueue, 3000)
}

async function flushQueue() {
  flushTimer = null
  if (!queue.length) return
  const items = queue.splice(0, 500)
  try {
    await http.post('/i18n/collect', { items })
  } catch {
    // Mất kết nối / không đủ quyền: bỏ qua, lần sau bật chế độ dịch sẽ ghi lại
  }
  if (queue.length) scheduleFlush()
}

function isCollectable(source) {
  if (!source || source.length > 300) return false
  if (!/\p{L}/u.test(source)) return false
  if (/^[\d\s.,:/\-+%()]+$/.test(source)) return false
  // Dữ liệu thay đổi theo thời điểm (ngày giờ, "Ca: 2", "10/181") không phải nhãn giao diện
  if (/\d{1,2}[/.-]\d{1,2}([/.-]\d{2,4})?/.test(source) || /\d{1,2}:\d{2}/.test(source)) return false
  if (/:\s*[\d.,]+$/.test(source)) return false
  return true
}

function collect(source, el) {
  if (!editAllowed || !isCollectable(source)) return
  if (menuSources.has(source) && el.closest('header, nav')) return
  if (el.closest('tbody td')) return // ô dữ liệu bảng (tên khách, số phòng...) không cần ghi nhận
  const area = describeArea(el)
  const key = `${source}|${area}`
  if (collected.has(key)) return
  collected.add(key)
  queue.push({ source, area, route: currentRoute() })
  scheduleFlush()
}

function markEditable(el, src) {
  const source = normalizeText(toSource(src))
  if (!isCollectable(source)) return
  if (el.getAttribute('data-i18n-src') !== source) el.setAttribute('data-i18n-src', source)
  collect(source, el)
}

/**
 * Ghi nhận cấu trúc menu để trang quản lý hiển thị đúng như thanh menu thật.
 * items: [{ name, dropdown: [{ name, children: [{ name }] }] }]
 */
export function registerMenu(items, moduleLabel) {
  if (!editAllowed || !Array.isArray(items)) return
  const list = []
  let sort = 0
  const visit = (nodes, path) => {
    for (const node of nodes || []) {
      if (!node?.name) continue
      const source = normalizeText(toSource(node.name))
      if (!isCollectable(source)) continue
      menuSources.add(source)
      const area = ['Menu', moduleLabel, ...path].filter(Boolean).join(' › ')
      list.push({ source, area, route: node.route || currentRoute(), sort: ++sort })
      visit(node.dropdown || node.children, [...path, source])
    }
  }
  visit(items, [])

  const signature = JSON.stringify(list.map(i => [i.source, i.area]))
  const storageKey = `pms_i18n_menu_${moduleLabel || ''}`
  try {
    if (sessionStorage.getItem(storageKey) === signature) return
    sessionStorage.setItem(storageKey, signature)
  } catch {
    // tiếp tục gửi
  }
  queue.push(...list)
  scheduleFlush()
}

// ─── Chế độ dịch ─────────────────────────────────────────────────────────────

export function setTranslationEditAllowed(allowed) {
  editAllowed = !!allowed
  if (!editAllowed && isEditMode.value) setEditMode(false)
  if (editAllowed && sessionStorage.getItem(EDIT_KEY) === '1' && !isEditMode.value) setEditMode(true)
}

export function canEditTranslations() {
  return editAllowed
}

export function setEditMode(on) {
  isEditMode.value = !!on && editAllowed
  try {
    sessionStorage.setItem(EDIT_KEY, isEditMode.value ? '1' : '0')
  } catch {
    // không lưu được trạng thái thì chỉ áp dụng cho trang hiện tại
  }
  document.documentElement.classList.toggle('i18n-edit-mode', isEditMode.value)
  if (!isEditMode.value) editorState.open = false
  sync()
}

export function toggleEditMode() {
  setEditMode(!isEditMode.value)
}

function editableTarget(target) {
  if (!(target instanceof Element) || target.closest('[data-i18n-editor]')) return null
  const placeholderEl = target.closest('[data-i18n-placeholder]')
  if (placeholderEl && (placeholderEl === target)) {
    return { el: placeholderEl, source: placeholderEl.getAttribute('data-i18n-placeholder') }
  }
  const el = target.closest('[data-i18n-src]')
  return el ? { el, source: el.getAttribute('data-i18n-src') } : null
}

function interceptPointer(event) {
  if (!isEditMode.value) return
  const hit = editableTarget(event.target)
  if (!hit) return
  event.preventDefault()
  event.stopPropagation()
  event.stopImmediatePropagation()
  if (event.type === 'click') openEditor(hit.source, hit.el)
}

export function openEditor(source, el) {
  editorState.source = source
  editorState.area = el ? describeArea(el) : ''
  editorState.route = currentRoute()
  editorState.anchor = el ? el.getBoundingClientRect().toJSON() : null
  editorState.open = true
}

/** Sau khi lưu bản dịch: tải lại từ điển và dịch lại màn hình ngay. */
export async function reloadTranslations() {
  await refreshDictionary({ force: true })
  sync()
}

// ─── "Xem vị trí" từ trang quản lý ───────────────────────────────────────────

/** Mở màn hình ở tab mới và tô sáng chữ cần tìm. */
export function openLocation(route, source) {
  try {
    localStorage.setItem(FIND_KEY, JSON.stringify({ source, at: Date.now() }))
  } catch {
    // vẫn mở trang, chỉ không tô sáng được
  }
  window.open(route || '/', '_blank')
}

function findElementBySource(source) {
  const target = normalizeText(source)
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT)
  let node = walker.nextNode()
  while (node) {
    const state = textState.get(node)
    const original = normalizeText(state && node.nodeValue === state.out ? state.src : node.nodeValue)
    if (original && (original === target || toSource(original) === target) && node.parentElement && !isSkipped(node.parentElement)) {
      return node.parentElement
    }
    node = walker.nextNode()
  }
  return document.querySelector(`[placeholder="${CSS.escape(target)}"]`)
}

function runPendingFind() {
  let request = null
  try {
    request = JSON.parse(localStorage.getItem(FIND_KEY) || 'null')
    localStorage.removeItem(FIND_KEY)
  } catch {
    return
  }
  if (!request?.source || Date.now() - request.at > 60000) return

  let tries = 0
  const timer = setInterval(() => {
    tries++
    const el = findElementBySource(request.source)
    const visible = el && el.getClientRects().length > 0
    if (visible || tries > 30) {
      clearInterval(timer)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        el.classList.add('i18n-find-flash')
        setTimeout(() => el.classList.remove('i18n-find-flash'), 6000)
      } else {
        editorState.notice = `Không thấy chữ "${request.source}" trên màn hình. Chữ có thể nằm trong popup hoặc menu con — hãy mở popup/menu đó.`
      }
    }
  }, 500)
}

// ─── Cài đặt ────────────────────────────────────────────────────────────────

function injectStyles() {
  const style = document.createElement('style')
  style.setAttribute('data-i18n-style', '')
  style.textContent = `
html.i18n-edit-mode [data-i18n-src], html.i18n-edit-mode [data-i18n-placeholder] {
  outline: 1px dashed rgba(245, 158, 11, .9); outline-offset: 1px; cursor: pointer !important;
}
html.i18n-edit-mode [data-i18n-src]:hover, html.i18n-edit-mode [data-i18n-placeholder]:hover {
  outline: 2px solid #f59e0b; background-color: rgba(251, 191, 36, .18);
}
.i18n-find-flash { outline: 3px solid #ef4444 !important; outline-offset: 2px; animation: i18n-find-flash .8s ease-in-out 6; }
@keyframes i18n-find-flash { 50% { outline-color: transparent; } }
`
  document.head.appendChild(style)
}

function mountEditor() {
  const host = document.createElement('div')
  host.setAttribute('data-i18n-editor', '')
  host.setAttribute('data-no-translate', '')
  document.body.appendChild(host)
  createApp(TranslationEditor).mount(host)
}

export function installTranslator() {
  if (installed || typeof document === 'undefined') return
  installed = true
  if (import.meta.env.VITE_I18N_ENABLED === 'false') return

  injectStyles()
  mountEditor()
  onDictionaryChange(sync)
  ;['click', 'mousedown', 'pointerdown', 'mouseup'].forEach(type => window.addEventListener(type, interceptPointer, true))

  patchNativeDialogs()
  sync()
  refreshDictionary()
  runPendingFind()
}

/** alert()/confirm()/prompt() gốc của trình duyệt không nằm trong DOM nên dịch nội dung trước khi hiện. */
function patchNativeDialogs() {
  const translateMessage = message => {
    if (typeof message !== 'string') return message
    return message.split('\n').map(line => translateText(line) ?? line).join('\n')
  }
  for (const name of ['alert', 'confirm', 'prompt']) {
    const original = window[name]
    if (typeof original !== 'function') continue
    window[name] = function (message, ...rest) {
      return original.call(window, translateMessage(message), ...rest)
    }
  }
}
