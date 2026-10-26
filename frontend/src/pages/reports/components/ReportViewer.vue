<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ChevronFirst, ChevronLast, ChevronLeft, ChevronRight, File, Files, LoaderCircle, Printer, ZoomIn, ZoomOut } from '@lucide/vue'
import {
  PAGE_GAP,
  ZOOM_LEVELS,
  clampPage,
  nextZoom,
  pagePitch,
  paginateReportDocument,
  paperMetrics,
  printStyles,
  viewerStyles,
} from './reportPagination'

const props = defineProps({
  html: { type: String, default: '' },
  template: { type: Object, default: null },
})
const emit = defineEmits(['open-booking'])

const frame = ref(null)
const scroller = ref(null)
const stage = ref(null)
const pageCount = ref(1)
const currentPage = ref(1)
const viewMode = ref('continuous')
const zoomSetting = ref('125')
const paginating = ref(false)
const printing = ref(false)
const viewportSize = ref({ width: 0, height: 0 })
let resizeObserver = null
let loadToken = 0

const paper = computed(() => paperMetrics(props.template))
const pitch = computed(() => pagePitch(paper.value, PAGE_GAP))
const documentHeight = computed(() => pageCount.value * pitch.value - PAGE_GAP)
const zoom = computed(() => {
  const availableWidth = Math.max(200, viewportSize.value.width - 48)
  const availableHeight = Math.max(200, viewportSize.value.height - 48)
  if (zoomSetting.value === 'width') return availableWidth / paper.value.width
  if (zoomSetting.value === 'page') return Math.min(availableWidth / paper.value.width, availableHeight / paper.value.height)
  return Number(zoomSetting.value) / 100
})
const zoomPercent = computed(() => Math.round(zoom.value * 100))
const pageOptions = computed(() => Array.from({ length: pageCount.value }, (_, index) => index + 1))

const stageStyle = computed(() => ({
  width: `${paper.value.width * zoom.value}px`,
  height: `${(viewMode.value === 'single' ? paper.value.height : documentHeight.value) * zoom.value}px`,
}))
const frameStyle = computed(() => {
  const offset = viewMode.value === 'single' ? (currentPage.value - 1) * pitch.value * zoom.value : 0
  return {
    width: `${paper.value.width}px`,
    height: `${documentHeight.value}px`,
    transform: `translateY(-${offset}px) scale(${zoom.value})`,
    transformOrigin: 'top left',
  }
})

const waitForAssets = async (doc) => {
  const pendingImages = [...doc.images].filter(image => !image.complete)
  await Promise.all(pendingImages.map(image => new Promise(resolve => {
    image.addEventListener('load', resolve, { once: true })
    image.addEventListener('error', resolve, { once: true })
  })))
  try {
    await doc.fonts?.ready
  } catch {
    // Font loading only affects measurement accuracy.
  }
}

const bodyPadding = (doc) => {
  const style = doc.defaultView.getComputedStyle(doc.body)
  return {
    top: parseFloat(style.paddingTop) || 0,
    right: parseFloat(style.paddingRight) || 0,
    bottom: parseFloat(style.paddingBottom) || 0,
    left: parseFloat(style.paddingLeft) || 0,
  }
}

const appendStyle = (doc, css) => {
  const style = doc.createElement('style')
  style.textContent = css
  doc.head.appendChild(style)
}

const handleDoubleClick = (event) => {
  const target = event.target?.closest?.('[data-pms-booking]')
  const bookingCode = target?.getAttribute('data-pms-booking')?.trim()
  if (!bookingCode) return
  event.preventDefault()
  emit('open-booking', bookingCode)
}

const handleFrameLoad = async () => {
  const doc = frame.value?.contentDocument
  if (!doc?.body || !props.html) return
  const token = ++loadToken
  paginating.value = true
  try {
    appendStyle(doc, viewerStyles(paper.value, PAGE_GAP))
    await waitForAssets(doc)
    if (token !== loadToken) return
    pageCount.value = paginateReportDocument(doc, paper.value, PAGE_GAP).pageCount
    currentPage.value = clampPage(currentPage.value, pageCount.value)
    doc.addEventListener('dblclick', handleDoubleClick)
  } finally {
    if (token === loadToken) paginating.value = false
  }
}

const scrollToPage = (page) => {
  if (viewMode.value !== 'continuous' || !scroller.value || !stage.value) return
  scroller.value.scrollTo({ top: stage.value.offsetTop + (page - 1) * pitch.value * zoom.value - 16 })
}

const goToPage = (page) => {
  currentPage.value = clampPage(page, pageCount.value)
  scrollToPage(currentPage.value)
}

const syncCurrentPage = () => {
  if (viewMode.value !== 'continuous' || !scroller.value || !stage.value) return
  const position = scroller.value.scrollTop - stage.value.offsetTop + scroller.value.clientHeight * 0.3
  currentPage.value = clampPage(Math.floor(position / (pitch.value * zoom.value)) + 1, pageCount.value)
}

const changeZoom = (direction) => {
  zoomSetting.value = String(nextZoom(zoomPercent.value, direction))
}

const setViewMode = async (mode) => {
  viewMode.value = mode
  await nextTick()
  if (mode === 'continuous') scrollToPage(currentPage.value)
  else scroller.value?.scrollTo({ top: 0 })
}

const printReport = async (pageIndex = null) => {
  if (!props.html || printing.value) return
  printing.value = true
  const host = document.createElement('iframe')
  host.setAttribute('aria-hidden', 'true')
  host.tabIndex = -1
  Object.assign(host.style, {
    position: 'fixed',
    left: '-10000px',
    top: '0',
    width: `${paper.value.width}px`,
    height: `${paper.value.height}px`,
    border: '0',
  })
  const cleanup = () => {
    host.remove()
    printing.value = false
  }
  try {
    await new Promise((resolve) => {
      host.addEventListener('load', resolve, { once: true })
      host.srcdoc = props.html
      document.body.appendChild(host)
    })
    const doc = host.contentDocument
    await waitForAssets(doc)
    const padding = bodyPadding(doc)
    paginateReportDocument(doc, paper.value, 0)
    doc.body.style.removeProperty('min-height')
    appendStyle(doc, printStyles(paper.value, padding, pageIndex))
    host.contentWindow.addEventListener('afterprint', () => window.setTimeout(cleanup, 0), { once: true })
    host.contentWindow.focus()
    host.contentWindow.print()
    window.setTimeout(() => host.isConnected && cleanup(), 60000)
  } catch (error) {
    cleanup()
    throw error
  }
}

const printAll = () => printReport(null)
const printCurrentPage = () => printReport(currentPage.value - 1)

watch(() => props.html, () => {
  currentPage.value = 1
  pageCount.value = 1
  scroller.value?.scrollTo({ top: 0 })
})
watch(zoom, () => nextTick(() => scrollToPage(currentPage.value)))

onMounted(() => {
  if (!scroller.value) return
  resizeObserver = new ResizeObserver(([entry]) => {
    viewportSize.value = { width: entry.contentRect.width, height: entry.contentRect.height }
  })
  resizeObserver.observe(scroller.value)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  frame.value?.contentDocument?.removeEventListener('dblclick', handleDoubleClick)
})

defineExpose({ printAll, printCurrentPage })
</script>

<template>
  <section class="relative flex min-h-0 min-w-0 flex-1 flex-col bg-slate-200">
    <div class="flex h-11 shrink-0 flex-wrap items-center justify-center gap-1 border-b border-slate-300 bg-white px-3 text-xs text-slate-700">
      <button type="button" class="report-viewer-icon" :disabled="currentPage <= 1" title="Trang đầu" @click="goToPage(1)"><ChevronFirst class="h-4 w-4" /></button>
      <button type="button" class="report-viewer-icon" :disabled="currentPage <= 1" title="Trang trước" @click="goToPage(currentPage - 1)"><ChevronLeft class="h-4 w-4" /></button>
      <select :value="currentPage" class="h-8 w-28 rounded-lg border border-slate-200 bg-white px-2 text-xs" title="Chọn trang" @change="goToPage($event.target.value)">
        <option v-for="page in pageOptions" :key="page" :value="page">{{ page }} / {{ pageCount }}</option>
      </select>
      <button type="button" class="report-viewer-icon" :disabled="currentPage >= pageCount" title="Trang kế tiếp" @click="goToPage(currentPage + 1)"><ChevronRight class="h-4 w-4" /></button>
      <button type="button" class="report-viewer-icon" :disabled="currentPage >= pageCount" title="Trang cuối" @click="goToPage(pageCount)"><ChevronLast class="h-4 w-4" /></button>

      <span class="mx-2 h-5 w-px bg-slate-200" />
      <button type="button" class="report-viewer-icon" :class="viewMode === 'single' ? 'report-viewer-icon-active' : ''" title="Xem từng trang" @click="setViewMode('single')"><File class="h-4 w-4" /></button>
      <button type="button" class="report-viewer-icon" :class="viewMode === 'continuous' ? 'report-viewer-icon-active' : ''" title="Xem nhiều trang" @click="setViewMode('continuous')"><Files class="h-4 w-4" /></button>

      <span class="mx-2 h-5 w-px bg-slate-200" />
      <button type="button" class="report-viewer-icon" :disabled="zoomPercent <= ZOOM_LEVELS[0]" title="Thu nhỏ" @click="changeZoom(-1)"><ZoomOut class="h-4 w-4" /></button>
      <select v-model="zoomSetting" class="h-8 w-32 rounded-lg border border-slate-200 bg-white px-2 text-xs" title="Tỷ lệ hiển thị">
        <option v-for="level in ZOOM_LEVELS" :key="level" :value="String(level)">{{ `${level}%` }}</option>
        <option value="width">Vừa chiều rộng</option>
        <option value="page">Vừa trang</option>
      </select>
      <button type="button" class="report-viewer-icon" :disabled="zoomPercent >= ZOOM_LEVELS[ZOOM_LEVELS.length - 1]" title="Phóng to" @click="changeZoom(1)"><ZoomIn class="h-4 w-4" /></button>

      <span class="mx-2 h-5 w-px bg-slate-200" />
      <button type="button" class="btn-pms-secondary" :disabled="printing || paginating" @click="printAll"><Printer class="h-4 w-4" /> In tất cả</button>
      <button type="button" class="btn-pms-secondary" :disabled="printing || paginating" @click="printCurrentPage"><Printer class="h-4 w-4" /> In trang đang xem</button>
    </div>

    <div ref="scroller" class="relative min-h-0 flex-1 overflow-auto p-6" @scroll="syncCurrentPage">
      <div v-if="paginating" class="pointer-events-none absolute right-4 top-4 z-10 flex items-center gap-2 rounded-lg bg-white/90 px-3 py-1.5 text-xs text-slate-600 shadow">
        <LoaderCircle class="h-4 w-4 animate-spin text-sky-600" /> Đang chia trang...
      </div>
      <div ref="stage" class="relative mx-auto overflow-hidden" :style="stageStyle">
        <iframe
          ref="frame"
          :srcdoc="html"
          title="Nội dung báo cáo"
          class="absolute left-0 top-0 block border-0 bg-transparent [filter:drop-shadow(0_1px_4px_rgba(15,23,42,0.25))]"
          :style="frameStyle"
          @load="handleFrameLoad"
        />
      </div>
    </div>
  </section>
</template>

<style scoped>
.report-viewer-icon {
  display: inline-flex;
  height: 2rem;
  width: 2rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  border: 1px solid transparent;
  background: transparent;
  color: #334155;
  cursor: pointer;
}
.report-viewer-icon:hover:not(:disabled) {
  background: #f1f5f9;
}
.report-viewer-icon:disabled {
  cursor: default;
  opacity: 0.35;
}
.report-viewer-icon-active {
  border-color: #bae6fd;
  background: #e0f2fe;
  color: #0369a1;
}
</style>
