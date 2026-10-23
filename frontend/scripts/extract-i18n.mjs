/**
 * Quét toàn bộ frontend/src lấy chữ tiếng Việt hiển thị trên giao diện (template, popup, thông báo...)
 * để nạp vào Cấu hình hệ thống › Ngôn ngữ.
 *
 *   node scripts/extract-i18n.mjs
 *
 * Kết quả: backend/database/data/i18n/ui-strings.json — sau đó chạy `php artisan i18n:sync` ở backend.
 * Chạy lại sau mỗi đợt cập nhật giao diện để bổ sung chữ mới (không mất bản dịch đã có).
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { parse as parseSfc } from 'vue/compiler-sfc'
import { parse as parseJs, parseExpression } from '@babel/parser'

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const SRC = path.join(ROOT, 'src')
const OUT = path.resolve(ROOT, '../backend/database/data/i18n/ui-strings.json')

const VI_CHARS = /[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i
const MAX_LENGTH = 300
// Chữ tiếng Việt không dấu hay dùng làm nhãn (không nhận ra được bằng dấu tiếng Việt)
const UNACCENTED_LABELS = new Set(['STT', 'SL', 'In', 'Xem', 'SĐT'])
// Thuộc tính không phải chữ hiển thị
const SKIP_ATTRS = new Set(['class', 'style', 'id', 'key', 'ref', 'name', 'type', 'src', 'href', 'to', 'value', 'for', 'role', 'tab', 'd', 'viewBox'])

// Thư mục → tên module hiển thị trên cây vị trí + màn hình mở khi bấm "Xem vị trí"
const MODULES = [
  ['pages/reservation', 'Đặt phòng', '/reservation'],
  ['pages/frontdesk', 'Lễ tân', '/frontdesk'],
  ['pages/housekeeping', 'Buồng phòng', '/housekeeping'],
  ['pages/reports', 'Báo cáo quản lý', '/reports'],
  ['pages/config', 'Cấu hình', '/config'],
  ['pages/system', 'Cấu hình hệ thống', '/system'],
  ['pages/fnb', 'F&B', '/fnb'],
  ['pages/FnbPages', 'F&B', '/fnb'],
  ['pages/LoginPage', 'Đăng nhập', '/login'],
  ['pages/HomePage', 'Trang chủ', '/'],
  ['pages', 'Trang khác', '/'],
  ['components', 'Dùng chung', '/frontdesk'],
  ['layouts', 'Khung giao diện', '/frontdesk'],
  ['', 'Thông báo hệ thống', '/frontdesk']
]

const items = new Map() // source -> { source, is_pattern, locations: Map(area -> route) }

function normalize(text) {
  return String(text).replace(/\s+/g, ' ').trim()
}

function locate(file) {
  const rel = path.relative(SRC, file).split(path.sep).join('/')
  const [, name, route] = MODULES.find(([prefix]) => rel.startsWith(prefix))
  const component = path.basename(file).replace(/\.(vue|js)$/, '')
  return { area: `${name} › ${component}`, route }
}

function add(source, location, isPattern = false) {
  const text = normalize(source)
  if (!text || text.length > MAX_LENGTH) return
  if (!UNACCENTED_LABELS.has(text) && !VI_CHARS.test(text.replace(/\{[^}]*\}/g, ''))) return
  if (/<\/?[a-z][^>]*>/i.test(text)) return // chuỗi HTML (mẫu in viết trong code) không phải nhãn đơn
  if (/^(select|insert|update|delete)\s/i.test(text)) return
  if (!items.has(text)) items.set(text, { source: text, is_pattern: isPattern, locations: new Map() })
  items.get(text).locations.set(location.area, location.route)
}

// ─── JS: chuỗi '...' "..." `...${x}...` ────────────────────────────────────────

function walkJs(node, location, parent) {
  if (!node || typeof node.type !== 'string') return
  if (node.type === 'CallExpression') {
    const callee = node.callee
    // Bỏ qua console.*, import(), require()
    if (callee?.type === 'MemberExpression' && callee.object?.name === 'console') return
    if (callee?.type === 'Import') return
  }
  if (node.type === 'ImportDeclaration' || node.type === 'ExportAllDeclaration') return

  if (node.type === 'StringLiteral') {
    const isObjectKey = parent?.type === 'ObjectProperty' && parent.key === node && !parent.computed
    if (!isObjectKey) add(node.value, location)
    return
  }
  if (node.type === 'TemplateLiteral') {
    let text = ''
    node.quasis.forEach((quasi, i) => {
      text += quasi.value.cooked ?? quasi.value.raw
      if (i < node.expressions.length) text += `{${i}}`
    })
    add(text, location, node.expressions.length > 0)
    node.expressions.forEach(expr => walkJs(expr, location, node))
    return
  }

  for (const key of Object.keys(node)) {
    if (key === 'loc' || key === 'start' || key === 'end' || key === 'leadingComments' || key === 'trailingComments' || key === 'extra') continue
    const value = node[key]
    if (Array.isArray(value)) value.forEach(child => walkJs(child, location, node))
    else if (value && typeof value.type === 'string') walkJs(value, location, node)
  }
}

function scanScript(code, location) {
  try {
    walkJs(parseJs(code, { sourceType: 'module', plugins: ['jsx', 'typescript'], errorRecovery: true }).program, location)
  } catch (error) {
    console.warn(`  ! Không đọc được script: ${location.area} (${error.message})`)
  }
}

function scanExpression(code, location) {
  if (!code || !/['"`]/.test(code)) return
  try {
    walkJs(parseExpression(code, { plugins: ['typescript'] }), location)
  } catch {
    // biểu thức v-for / v-slot đặc biệt: thử như câu lệnh
    try { walkJs(parseJs(`(${code})`, { errorRecovery: true }).program, location) } catch { /* bỏ qua */ }
  }
}

// ─── Template Vue ───────────────────────────────────────────────────────────

const NODE = { ELEMENT: 1, TEXT: 2, COMMENT: 3, INTERPOLATION: 5 }

function scanTemplate(nodes, location) {
  // Chữ thường + {{ }} liền nhau thành MỘT đoạn chữ khi chạy (vd "Tổng: {{ n }} phòng" → "Tổng: {0} phòng")
  let run = []
  const flush = () => {
    if (!run.length) return
    let text = ''
    let index = 0
    for (const part of run) {
      if (part.type === NODE.TEXT) text += part.content
      else text += `{${index++}}`
    }
    if (index === 0) add(text, location)
    else if (run.some(p => p.type === NODE.TEXT && VI_CHARS.test(p.content))) add(text, location, true)
    run = []
  }

  for (const node of nodes || []) {
    if (node.type === NODE.TEXT || node.type === NODE.INTERPOLATION) {
      run.push(node)
      if (node.type === NODE.INTERPOLATION) scanExpression(node.content?.content, location)
      continue
    }
    flush()
    if (node.type !== NODE.ELEMENT) continue
    if (node.tag === 'style' || node.tag === 'script') continue
    for (const prop of node.props || []) {
      if (prop.type === 6 && prop.value && !SKIP_ATTRS.has(prop.name)) add(prop.value.content, location)
      else if (prop.type === 7 && prop.exp) scanExpression(prop.exp.content, location)
    }
    scanTemplate(node.children, location)
  }
  flush()
}

// ─── Chạy ─────────────────────────────────────────────────────────────────

function listFiles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) return listFiles(full)
    return /\.(vue|js)$/.test(entry.name) ? [full] : []
  })
}

const files = listFiles(SRC).filter(f => !f.endsWith(path.join('utils', 'i18n.js')))
for (const file of files) {
  const code = fs.readFileSync(file, 'utf8')
  const location = locate(file)
  if (file.endsWith('.vue')) {
    const { descriptor } = parseSfc(code, { filename: file })
    if (descriptor.template?.ast) scanTemplate(descriptor.template.ast.children, location)
    if (descriptor.script) scanScript(descriptor.script.content, location)
    if (descriptor.scriptSetup) scanScript(descriptor.scriptSetup.content, location)
  } else {
    scanScript(code, location)
  }
}

const output = [...items.values()]
  .map(item => ({
    source: item.source,
    is_pattern: item.is_pattern,
    locations: [...item.locations].map(([area, route]) => ({ area, route }))
  }))
  .sort((a, b) => a.source.localeCompare(b.source, 'vi'))

fs.mkdirSync(path.dirname(OUT), { recursive: true })
fs.writeFileSync(OUT, JSON.stringify({ generated_at: new Date().toISOString(), count: output.length, items: output }, null, 1) + '\n')
console.log(`Đã quét ${files.length} file, lấy ${output.length} chữ (${output.filter(i => i.is_pattern).length} mẫu câu có biến) → ${path.relative(process.cwd(), OUT)}`)
