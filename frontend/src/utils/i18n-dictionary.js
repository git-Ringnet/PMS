import { ref } from 'vue'
import http from '@/services/http'

/**
 * Từ điển đa ngôn ngữ tải từ Cấu hình hệ thống › Ngôn ngữ.
 * Khóa là chữ gốc tiếng Việt đang viết trong code / mẫu in, giá trị là bản dịch của ngôn ngữ đang chọn.
 * Với tiếng Việt, từ điển chỉ chứa các chữ khách sạn đã sửa lại.
 */

export const SOURCE_LANG = 'vi'

const LANGUAGES_KEY = 'pms_i18n_languages'
const DICT_KEY_PREFIX = 'pms_i18n_dict_'

const DEFAULT_LANGUAGES = [
  { code: 'vi', name: 'Tiếng Việt', flag: '🇻🇳', is_default: true },
  { code: 'en', name: 'English', flag: '🇬🇧', is_default: false }
]

function readJson(key) {
  try {
    const raw = localStorage.getItem(key)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function writeJson(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // Bộ nhớ trình duyệt đầy / bị chặn: vẫn chạy được, chỉ không có bản lưu sẵn cho lần sau
  }
}

export function activeLangCode() {
  try {
    return localStorage.getItem('pms_lang') || SOURCE_LANG
  } catch {
    return SOURCE_LANG
  }
}

/** Danh sách ngôn ngữ đang bật (dropdown chọn ngôn ngữ trên header). */
export const languages = ref(readJson(LANGUAGES_KEY) || DEFAULT_LANGUAGES)

/** Tăng mỗi khi từ điển đổi, để các computed dùng t() tự cập nhật. */
export const dictionaryRevision = ref(0)

let version = null
let entries = new Map()
let patterns = []
let reverse = new Map()
const listeners = new Set()

export function normalizeText(text) {
  return String(text ?? '').replace(/\s+/g, ' ').trim()
}

function escapeRegex(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// Mẫu câu gom theo 2 ký tự đầu của phần chữ cố định để không phải thử hết mọi mẫu với mỗi đoạn chữ
let patternBuckets = new Map()
let looseFirstPatterns = [] // mẫu bắt đầu bằng {biến}
const resultCache = new Map()
const RESULT_CACHE_LIMIT = 20000

function applyDictionary(data) {
  version = data?.version ?? null
  entries = new Map(Object.entries(data?.entries || {}))
  patterns = Object.entries(data?.patterns || {})
    // Mẫu chỉ toàn {biến} sẽ khớp mọi đoạn chữ → bỏ qua
    .filter(([source]) => /\p{L}/u.test(source.replace(/\{[a-zA-Z0-9_]+\}/g, '')))
    .map(([source, value]) => {
    const names = [...source.matchAll(/\{([a-zA-Z0-9_]+)\}/g)].map(m => m[1])
    const body = escapeRegex(source).replace(/\\\{[a-zA-Z0-9_]+\\\}/g, '(.*?)')
    const prefix = source.split('{')[0]
    // Mẫu có ít chữ cố định (vd "{0} ngày") rất dễ khớp nhầm cả câu dài → giới hạn giá trị biến tối đa 3 từ
    const literalWords = source.replace(/\{[a-zA-Z0-9_]+\}/g, ' ').trim().split(/\s+/).filter(Boolean).length
    const literalLength = source.replace(/\{[a-zA-Z0-9_]+\}/g, '').length
    return { regex: new RegExp(`^${body}$`, 'u'), names, value, prefix, literalLength, maxCaptureWords: literalWords < 3 ? 3 : Infinity }
  })
    // Mẫu cụ thể hơn (nhiều chữ cố định hơn) được thử trước: "Tình trạng: {0} ({1})" trước "Tình trạng: {0}"
    .sort((a, b) => b.literalLength - a.literalLength)
  patternBuckets = new Map()
  looseFirstPatterns = []
  for (const pattern of patterns) {
    if (pattern.prefix.length >= 2) {
      const key = pattern.prefix.slice(0, 2)
      if (!patternBuckets.has(key)) patternBuckets.set(key, [])
      patternBuckets.get(key).push(pattern)
    } else {
      looseFirstPatterns.push(pattern)
    }
  }
  resultCache.clear()
  reverse = new Map()
  for (const [source, value] of entries) {
    if (!reverse.has(value)) reverse.set(value, source)
  }
  dictionaryRevision.value++
  listeners.forEach(fn => {
    try { fn() } catch (e) { console.error(e) }
  })
}

// Nạp ngay bản lưu sẵn để màn hình hiển thị đúng ngôn ngữ từ lần vẽ đầu tiên
applyDictionary(readJson(DICT_KEY_PREFIX + activeLangCode()))

export function onDictionaryChange(fn) {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

export function hasDictionary() {
  return entries.size > 0 || patterns.length > 0
}

/** Tải lại danh sách ngôn ngữ và từ điển từ server (chỉ tải lại khi có thay đổi). */
export async function refreshDictionary({ force = false } = {}) {
  const lang = activeLangCode()
  try {
    const [langRes, dictRes] = await Promise.all([
      http.get('/i18n/languages'),
      http.get(`/i18n/dictionary/${encodeURIComponent(lang)}`, { params: force ? {} : { v: version || undefined } })
    ])
    const list = langRes.data?.data
    if (Array.isArray(list) && list.length) {
      languages.value = list
      writeJson(LANGUAGES_KEY, list)
    }
    const data = dictRes.data?.data
    if (data && !data.unchanged) {
      writeJson(DICT_KEY_PREFIX + lang, data)
      applyDictionary(data)
    }
  } catch (error) {
    // Không tải được thì giữ bản lưu sẵn / chữ gốc, không làm gián đoạn nghiệp vụ
    console.warn('Không tải được từ điển ngôn ngữ:', error?.message || error)
  }
}

/** Tra chính xác một chữ gốc (đã chuẩn hóa). */
export function lookupExact(source) {
  const key = normalizeText(source)
  return key ? (entries.get(key) ?? null) : null
}

/**
 * Dịch một đoạn chữ hiển thị, giữ nguyên khoảng trắng đầu/cuối.
 * Trả về null nếu không có bản dịch.
 */
export function translateText(text) {
  if (!text || (!entries.size && !patterns.length)) return null
  const match = /^(\s*)([\s\S]*?)(\s*)$/.exec(text)
  const core = normalizeText(match[2])
  if (!core || !/\p{L}/u.test(core)) return null

  let out = resultCache.get(core)
  if (out === undefined) {
    out = translateCore(core)
    if (resultCache.size >= RESULT_CACHE_LIMIT) resultCache.clear()
    resultCache.set(core, out)
  }
  return out == null ? null : match[1] + out + match[3]
}

function applyPattern(pattern, core) {
  const found = pattern.regex.exec(core)
  if (!found) return null
  if (found.slice(1).some(part => part.trim().split(/\s+/).length > pattern.maxCaptureWords)) return null
  let out = pattern.value
  pattern.names.forEach((name, i) => {
    // Giá trị biến cũng là chữ đã có bản dịch (vd tên trạng thái) thì dịch luôn
    const value = found[i + 1]
    out = out.split(`{${name}}`).join(entries.get(value.trim()) ?? value)
  })
  return out
}

function translateCore(core) {
  const direct = entries.get(core)
  if (direct != null) return direct

  // Nhãn kèm dấu ở cuối ("Ngày đến:", "Họ tên *") dùng chung bản dịch với nhãn không dấu
  const suffix = /^(.*?\p{L}.*?)(\s*[:：*]+)$/u.exec(core)
  if (suffix && entries.has(suffix[1])) return entries.get(suffix[1]) + suffix[2]

  for (const pattern of patternBuckets.get(core.slice(0, 2)) || []) {
    const out = applyPattern(pattern, core)
    if (out != null) return out
  }
  for (const pattern of looseFirstPatterns) {
    const out = applyPattern(pattern, core)
    if (out != null) return out
  }
  return translateSegments(core)
}

// Đoạn chữ ghép động ("Trạng thái: Đang ở (5)", "Phòng 101 / Đã trả") → dịch từng phần đã biết
const SEGMENT_SPLIT = /(\s*[:|/•·–]\s*|\s+-\s+|\s*[()[\]]\s*)/

function translateSegments(core) {
  const parts = core.split(SEGMENT_SPLIT)
  if (parts.length < 3) return null
  let changed = false
  const out = parts.map((part, i) => {
    if (i % 2 === 1 || !/\p{L}/u.test(part)) return part
    const exact = entries.get(part.trim())
    if (exact == null) return part
    changed = true
    return exact
  })
  return changed ? out.join('') : null
}

/** Đổi chữ đang hiển thị (có thể đã dịch) về chữ gốc tiếng Việt nếu biết. */
export function toSource(text) {
  const key = normalizeText(text)
  return reverse.get(key) ?? builtinReverse.get(key) ?? key
}

// Bản dịch có sẵn trong i18n.js (vd t('menu.checkIn') → "Check In") cũng cần đổi ngược về chữ gốc
let builtinReverse = new Map()
export function registerBuiltinReverse(pairs) {
  builtinReverse = new Map()
  for (const pair of pairs) {
    for (const value of Object.values(pair.values || {})) {
      const key = normalizeText(value)
      if (key && !builtinReverse.has(key)) builtinReverse.set(key, pair.source)
    }
  }
}
