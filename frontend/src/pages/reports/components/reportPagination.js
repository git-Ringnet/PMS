// Phân trang HTML báo cáo trong iframe xem trước để hiển thị/in theo từng trang giấy.
export const PIXELS_PER_MILLIMETRE = 96 / 25.4
export const PAGE_GAP = 16
export const ZOOM_LEVELS = [50, 75, 100, 125, 150, 200]

const PAPER_SIZES = {
  A4: [210, 297],
  A5: [148, 210],
  Letter: [215.9, 279.4],
  Legal: [215.9, 355.6],
}

const SPACER_CLASS = 'pms-page-spacer'
const REPEAT_CLASS = 'pms-page-repeat'
const SKIPPED_TAGS = new Set(['STYLE', 'SCRIPT', 'TEMPLATE', 'LINK', 'META', 'TITLE'])
const TABLE_SECTION_TAGS = new Set(['TABLE', 'THEAD', 'TBODY', 'TFOOT'])
const BLOCK_SELECTOR = 'tr, table, div, p, h1, h2, h3, h4, h5, h6, ul, ol, section, hr'

export function paperMetrics(template) {
  const [shortSide, longSide] = PAPER_SIZES[template?.page_size] || PAPER_SIZES.A4
  const landscape = template?.page_orientation === 'landscape'
  const [widthMm, heightMm] = landscape ? [longSide, shortSide] : [shortSide, longSide]

  return {
    widthMm,
    heightMm,
    width: widthMm * PIXELS_PER_MILLIMETRE,
    height: heightMm * PIXELS_PER_MILLIMETRE,
  }
}

export const pagePitch = (paper, gap) => paper.height + gap

export function pageCountFor(contentHeight, paper, gap) {
  return Math.max(1, Math.ceil((contentHeight + gap - 0.5) / pagePitch(paper, gap)))
}

export function clampPage(page, pageCount) {
  return Math.min(Math.max(1, Math.round(Number(page) || 1)), Math.max(1, pageCount))
}

export function nextZoom(current, direction) {
  const levels = direction > 0 ? ZOOM_LEVELS : [...ZOOM_LEVELS].reverse()
  return levels.find(level => (direction > 0 ? level > current : level < current)) ?? levels[levels.length - 1]
}

const isHeaderRow = row => row.parentElement?.tagName === 'THEAD'
  || (row.cells.length > 0 && [...row.cells].every(cell => cell.tagName === 'TH'))

function headerRowsFor(row) {
  const table = row.closest('table')
  if (!table || isHeaderRow(row)) return []
  const rows = [...table.rows].filter(item => item.closest('table') === table)
  const leading = []
  for (const item of rows) {
    if (!isHeaderRow(item)) break
    leading.push(item)
  }
  return leading.includes(row) ? [] : leading
}

// Các phần tử không được cắt ngang khi sang trang: dòng bảng và khối lá.
function collectBreakCandidates(element, view, output) {
  for (const child of element.children) {
    if (SKIPPED_TAGS.has(child.tagName) || child.classList.contains(SPACER_CLASS)) continue
    const style = view.getComputedStyle(child)
    if (style.display === 'none' || style.position === 'absolute' || style.position === 'fixed') continue
    if (child.tagName === 'TR') {
      output.push(child)
    } else if (TABLE_SECTION_TAGS.has(child.tagName)) {
      collectBreakCandidates(child, view, output)
    } else if (!['flex', 'inline-flex', 'grid', 'inline-grid'].includes(style.display) && child.querySelector(BLOCK_SELECTOR)) {
      collectBreakCandidates(child, view, output)
    } else {
      output.push(child)
    }
  }
}

function createSpacer(doc, before, height) {
  let spacer
  if (before.tagName === 'TR') {
    spacer = doc.createElement('tr')
    const cell = doc.createElement('td')
    cell.colSpan = Math.max(1, ...[...before.closest('table').rows].map(row => [...row.cells].reduce((sum, item) => sum + (item.colSpan || 1), 0)))
    for (const property of ['padding', 'border', 'background']) cell.style.setProperty(property, property === 'background' ? 'transparent' : '0', 'important')
    spacer.appendChild(cell)
  } else {
    spacer = doc.createElement('div')
  }
  spacer.className = SPACER_CLASS
  spacer.setAttribute('aria-hidden', 'true')
  setSpacerHeight(spacer, height)
  before.parentNode.insertBefore(spacer, before)
  return spacer
}

function setSpacerHeight(spacer, height) {
  const target = spacer.tagName === 'TR' ? spacer.firstElementChild : spacer
  const value = `${Math.max(0, height)}px`
  target.style.setProperty('height', value, 'important')
  target.style.setProperty('min-height', value, 'important')
  target.style.setProperty('margin', '0', 'important')
}

const documentTop = (element, view) => element.getBoundingClientRect().top + view.scrollY

/**
 * Chèn khoảng đệm trước dòng/khối bị tràn để mỗi trang bắt đầu ở bội số của
 * (chiều cao giấy + gap); lặp lại tiêu đề bảng ở đầu trang mới.
 */
export function paginateReportDocument(doc, paper, gap = PAGE_GAP) {
  const view = doc.defaultView
  const body = doc.body
  if (!view || !body) return { pageCount: 1, gap }

  doc.querySelectorAll(`.${SPACER_CLASS}, .${REPEAT_CLASS}`).forEach(node => node.remove())
  const bodyStyle = view.getComputedStyle(body)
  const marginTop = parseFloat(bodyStyle.paddingTop) || 0
  const marginBottom = parseFloat(bodyStyle.paddingBottom) || 0
  const pitch = pagePitch(paper, gap)
  const candidates = []
  collectBreakCandidates(body, view, candidates)

  let page = 0
  for (const element of candidates) {
    if (!element.isConnected) continue
    const rect = element.getBoundingClientRect()
    if (rect.height <= 0) continue
    const top = rect.top + view.scrollY
    const bottom = rect.bottom + view.scrollY
    while (top >= page * pitch + paper.height - marginBottom) page += 1
    const pageStart = page * pitch + marginTop
    if (bottom <= page * pitch + paper.height - marginBottom + 0.5 || top <= pageStart + 1) continue

    const nextStart = (page + 1) * pitch + marginTop
    const spacer = createSpacer(doc, element, nextStart - top)
    const drift = nextStart - documentTop(element, view)
    if (Math.abs(drift) > 0.5) setSpacerHeight(spacer, nextStart - top + drift)

    if (element.tagName === 'TR') {
      for (const header of headerRowsFor(element)) {
        const clone = header.cloneNode(true)
        clone.classList.add(REPEAT_CLASS)
        element.parentNode.insertBefore(clone, element)
      }
    }
    page += 1
  }

  const pageCount = pageCountFor(doc.documentElement.scrollHeight, paper, gap)
  body.style.setProperty('min-height', `${pageCount * pitch - gap}px`, 'important')
  return { pageCount, gap, marginTop, marginBottom }
}

export function viewerStyles(paper, gap = PAGE_GAP) {
  const height = paper.height
  return `
    html { background: repeating-linear-gradient(to bottom, #ffffff 0, #ffffff ${height}px, transparent ${height}px, transparent ${height + gap}px) !important; overflow: hidden !important; }
    body { background: transparent !important; box-sizing: border-box !important; margin: 0 !important; }
    .pms-booking-ref[data-pms-booking]:not([data-pms-booking=""]) { cursor: pointer; }
    .pms-booking-ref[data-pms-booking]:not([data-pms-booking=""]):hover { color: #0369a1; text-decoration: underline; }
  `
}

/** CSS in theo đúng phân trang xem trước; pageIndex chỉ in một trang. */
export function printStyles(paper, padding, pageIndex = null) {
  const singlePage = pageIndex === null ? '' : `
      html { height: ${paper.height}px !important; overflow: hidden !important; }
      body { height: ${(pageIndex + 1) * paper.height}px !important; overflow: hidden !important; margin-top: -${pageIndex * paper.height}px !important; }
  `
  return `
    @page { size: ${paper.widthMm}mm ${paper.heightMm}mm; margin: 0; }
    @media print {
      html, body { background: #ffffff !important; }
      body { margin: 0 !important; min-height: 0 !important; box-sizing: border-box !important; padding: ${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px !important; }
      tr { page-break-inside: auto !important; }
      thead { display: table-row-group !important; }
      ${singlePage}
    }
  `
}
