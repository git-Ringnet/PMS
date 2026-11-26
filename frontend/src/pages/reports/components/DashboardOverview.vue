<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { BedDouble, Coffee, DoorOpen, LoaderCircle, LogIn, RefreshCw, Search, X } from '@lucide/vue'

const emit = defineEmits(['open-booking'])
const uiStore = useUiStore()

const CURRENT_COLOR = '#2F6FED'
const PREVIOUS_COLOR = '#A3B1C6'
const DEPARTMENT_COLORS = ['#2F6FED', '#1BAF7A', '#E8A33D', '#8B5CF6', '#E34948']

const loading = ref(false)
const data = ref(null)
const revenuePeriod = ref('yesterday')
const forecastPeriod = ref('day')
const includeBreakfast = ref(true)
const topGroup = ref('company')

const numberFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
const formatNumber = (value) => numberFormatter.format(Math.round(Number(value) || 0))
const formatPercent = (value) => `${(Number(value) || 0).toFixed(2)}%`
const formatDate = (value) => {
  if (!value) return ''
  const [y, m, d] = String(value).slice(0, 10).split('-')
  return `${d}/${m}/${y}`
}

const loadOverview = async () => {
  loading.value = true
  try {
    const response = await http.get('/dashboard/overview')
    data.value = response.data?.data || null
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải dữ liệu tổng quan', 'error')
  } finally {
    loading.value = false
  }
}

const activity = computed(() => data.value?.activity || null)
const percentOf = (part, total) => (total > 0 ? Math.min(100, Math.round((part / total) * 100)) : 0)

// ── Buồng phòng ──
const housekeepingLabels = {
  ready: 'Phòng sẵn sàng',
  clean: 'Phòng sạch',
  dirty: 'Phòng dơ',
  ooo: 'Phòng sửa chữa (OOO)',
  oos: 'Phòng dịch vụ (OOS)',
  other: 'Trạng thái khác',
}
const housekeepingRows = computed(() => data.value?.housekeeping || [])

// ── Doanh thu theo tháng (sp_131) ──
const chartBox = ref(null)
const chartWidth = ref(600)
const CHART_HEIGHT = 240
const PAD = { left: 56, right: 12, top: 16, bottom: 28 }
let resizeObserver = null

const monthly = computed(() => data.value?.revenue_monthly?.months || [])
const chartScale = computed(() => {
  const values = monthly.value.flatMap(item => [item.current, item.previous])
  let max = Math.max(0, ...values)
  let min = Math.min(0, ...values)
  if (max === min) max = min + 1_000_000
  const rawStep = (max - min) / 4
  const magnitude = 10 ** Math.floor(Math.log10(rawStep))
  const step = [1, 2, 2.5, 5, 10].map(f => f * magnitude).find(s => s >= rawStep) || rawStep
  const top = Math.ceil(max / step) * step
  const bottom = Math.floor(min / step) * step
  const ticks = []
  for (let v = bottom; v <= top + step / 2; v += step) ticks.push(v)
  const plotHeight = CHART_HEIGHT - PAD.top - PAD.bottom
  const y = (v) => PAD.top + plotHeight * (1 - (v - bottom) / (top - bottom))
  return { ticks, y }
})
const chartBars = computed(() => {
  const count = monthly.value.length || 1
  const plotWidth = Math.max(0, chartWidth.value - PAD.left - PAD.right)
  const groupWidth = plotWidth / count
  const barWidth = Math.max(4, Math.min(22, (groupWidth - 10) / 2))
  const { y } = chartScale.value
  const zero = y(0)
  const bar = (value, x, color, key) => {
    const top = y(Math.max(0, value))
    const height = Math.abs(y(value) - zero)
    return { key, x, y: top, width: barWidth, height, color, value }
  }
  return monthly.value.map((item, index) => {
    const center = PAD.left + groupWidth * index + groupWidth / 2
    return {
      month: item.month,
      center,
      groupX: PAD.left + groupWidth * index,
      groupWidth,
      previous: bar(item.previous, center - barWidth - 1, PREVIOUS_COLOR, 'previous'),
      current: bar(item.current, center + 1, CURRENT_COLOR, 'current'),
      item,
    }
  })
})
const formatAxis = (value) => `${formatNumber(value / 1_000_000)}`
const hoveredMonth = ref(null)
const tooltipStyle = computed(() => {
  if (!hoveredMonth.value) return {}
  const left = hoveredMonth.value.center + 16
  const flip = left + 200 > chartWidth.value
  return flip
    ? { right: `${chartWidth.value - hoveredMonth.value.center + 16}px`, top: '24px' }
    : { left: `${left}px`, top: '24px' }
})
const revenueYear = computed(() => data.value?.revenue_monthly?.year || '')

// ── Cơ cấu doanh thu (sp_117) ──
const revenuePeriods = [
  { key: 'yesterday', label: 'Hôm qua' },
  { key: 'week', label: 'Tuần này' },
  { key: 'month', label: 'Tháng này' },
]
const revenueSummary = computed(() => data.value?.revenue_summary?.[revenuePeriod.value] || null)
const departmentRows = computed(() => {
  const rows = revenueSummary.value?.departments || []
  const maxAbs = Math.max(1, ...rows.map(row => Math.abs(row.total)))
  return rows.map((row, index) => ({
    ...row,
    color: DEPARTMENT_COLORS[index % DEPARTMENT_COLORS.length],
    width: Math.round((Math.abs(row.total) / maxAbs) * 100),
  }))
})

// ── Dự đoán bán phòng (sp_136) ──
const forecastPeriods = [
  { key: 'day', label: 'Ngày' },
  { key: 'month', label: 'Tháng này' },
  { key: 'year', label: 'Năm' },
]
const forecastWindow = computed(() => data.value?.forecast?.[forecastPeriod.value] || null)
const forecast = computed(() => {
  const window = forecastWindow.value
  if (!window) return null
  return window[includeBreakfast.value ? 'with_breakfast' : 'without_breakfast']
})
const forecastPeriodLabel = computed(() => {
  const window = forecastWindow.value
  if (!window) return ''
  return window.from === window.to ? formatDate(window.from) : `${formatDate(window.from)} - ${formatDate(window.to)}`
})
const DONUT_RADIUS = 45
const DONUT_LENGTH = 2 * Math.PI * DONUT_RADIUS
const donutSold = computed(() => {
  const rate = Math.max(0, Math.min(100, Number(forecast.value?.occupancy_rate) || 0))
  return (rate / 100) * DONUT_LENGTH
})

// ── Top 5 ──
const topGroups = [
  { key: 'company', label: 'Công ty' },
  { key: 'market', label: 'Thị trường' },
  { key: 'nationality', label: 'Quốc tịch' },
]
const topRows = computed(() => forecastWindow.value?.top?.[topGroup.value] || [])

// ── Popup chi tiết ──
const popup = ref(null)
const popupRows = ref([])
const popupLoading = ref(false)
const popupFilter = ref('all')
const popupSearch = ref('')
const popupConfig = {
  arrivals: { title: 'Khách đến hôm nay', pending: 'Chờ nhận phòng', done: 'Đã nhận phòng' },
  departures: { title: 'Khách đi hôm nay', pending: 'Chưa trả phòng', done: 'Đã trả phòng' },
  inhouse: { title: 'Khách đang ở', pending: '', done: '' },
}
const statusLabels = { 0: 'Đã đặt', 1: 'Đang ở', 2: 'Đã trả phòng' }

const openPopup = async (type) => {
  popup.value = type
  popupFilter.value = 'all'
  popupSearch.value = ''
  popupRows.value = []
  popupLoading.value = true
  try {
    const response = await http.get('/dashboard/activity-rooms', { params: { type } })
    if (popup.value === type) popupRows.value = response.data?.data || []
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải danh sách chi tiết', 'error')
  } finally {
    popupLoading.value = false
  }
}
const closePopup = () => { popup.value = null }
const popupDoneCount = computed(() => popupRows.value.filter(row => row.done).length)
const filteredPopupRows = computed(() => {
  const keyword = popupSearch.value.trim().toLowerCase()
  return popupRows.value.filter((row) => {
    if (popupFilter.value === 'pending' && row.done) return false
    if (popupFilter.value === 'done' && !row.done) return false
    if (!keyword) return true
    return [row.booking_code, row.reference_code, row.booking_name, row.guest_name, row.company, row.room_number]
      .some(value => String(value || '').toLowerCase().includes(keyword))
  })
})

const handleKeydown = (event) => {
  if (event.key === 'Escape' && popup.value) closePopup()
}

watch(chartBox, (element) => {
  resizeObserver?.disconnect()
  if (!element) return
  chartWidth.value = element.clientWidth || 600
  resizeObserver = new ResizeObserver(entries => {
    chartWidth.value = entries[0]?.contentRect?.width || chartWidth.value
  })
  resizeObserver.observe(element)
})

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  loadOverview()
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  resizeObserver?.disconnect()
})

defineExpose({ reload: loadOverview })
</script>

<template>
  <div class="dashboard relative h-full min-h-0 overflow-y-auto bg-slate-100 p-4 text-xs text-[#000000D9]">
    <div class="mb-3 flex items-center gap-3">
      <h2 class="flex-1 text-sm font-semibold">Tổng quan</h2>
      <span v-if="data" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-600">
        Ngày hệ thống: <strong class="text-[#000000D9]">{{ formatDate(data.system_date) }}</strong>
      </span>
      <button type="button" class="btn-pms-secondary flex h-8 items-center gap-1 rounded-lg px-3 text-xs font-semibold" :disabled="loading" @click="loadOverview">
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> Làm mới
      </button>
    </div>

    <div v-if="loading && !data" class="flex h-64 items-center justify-center text-slate-500">
      <LoaderCircle class="mr-2 h-5 w-5 animate-spin" /> Đang tải dữ liệu tổng quan...
    </div>

    <template v-else-if="data">
      <!-- Hàng 1: Buồng phòng + Hoạt động hôm nay -->
      <div class="dash-grid">
        <section class="dash-card">
          <div class="section-head"><span class="section-title">Buồng phòng</span></div>
          <div class="bp-row bp-head">
            <span>Trạng thái</span>
            <span class="text-right text-[#E34948]">Có khách</span>
            <span class="text-right text-[#1BAF7A]">Trống</span>
          </div>
          <div v-for="row in housekeepingRows" :key="row.key" class="bp-row">
            <span>{{ housekeepingLabels[row.key] }}</span>
            <span class="text-right font-semibold" :class="row.occ ? 'text-[#E34948]' : 'text-slate-500'">{{ formatNumber(row.occ) }}</span>
            <span class="text-right font-semibold text-[#1BAF7A]">{{ formatNumber(row.vac) }}</span>
          </div>
        </section>

        <section class="dash-card">
          <div class="section-head"><span class="section-title">Hoạt động hôm nay</span></div>
          <div class="grid grid-cols-2 gap-2.5">
            <div class="act-tile">
              <button type="button" class="act-detail" @click="openPopup('arrivals')">Chi tiết</button>
              <div class="act-label"><LogIn class="h-4 w-4" /> Phòng đến</div>
              <div class="act-value">{{ formatNumber(activity.arrivals_pending) }} <span class="act-total">/ {{ formatNumber(activity.arrivals_total) }}</span></div>
              <div class="act-sub">Đã nhận phòng: {{ formatNumber(activity.arrivals_checked_in) }}</div>
              <div class="progress"><div class="progress-fill bg-[#1BAF7A]" :style="{ width: `${percentOf(activity.arrivals_checked_in, activity.arrivals_total)}%` }"></div></div>
            </div>
            <div class="act-tile">
              <button type="button" class="act-detail" @click="openPopup('departures')">Chi tiết</button>
              <div class="act-label"><DoorOpen class="h-4 w-4" /> Phòng đi</div>
              <div class="act-value">{{ formatNumber(activity.departures_pending) }} <span class="act-total">/ {{ formatNumber(activity.departures_total) }}</span></div>
              <div class="act-sub">Đã trả phòng: {{ formatNumber(activity.departures_checked_out) }}</div>
              <div class="progress"><div class="progress-fill bg-[#E8A33D]" :style="{ width: `${percentOf(activity.departures_checked_out, activity.departures_total)}%` }"></div></div>
            </div>
            <div class="act-tile">
              <button type="button" class="act-detail" @click="openPopup('inhouse')">Chi tiết</button>
              <div class="act-label"><BedDouble class="h-4 w-4" /> Phòng đang ở</div>
              <div class="act-value">{{ formatNumber(activity.inhouse_current) }} <span class="act-total">/ {{ formatNumber(activity.inhouse_projected) }}</span></div>
              <div class="act-sub">Hiện tại / Dự kiến cuối ngày</div>
              <div class="act-sub">Số khách đang ở: {{ formatNumber(activity.inhouse_current_pax) }}</div>
            </div>
            <div class="act-tile">
              <div class="act-label"><Coffee class="h-4 w-4" /> Ăn sáng</div>
              <div class="act-value">{{ formatNumber(activity.breakfast.rooms_with) }} <span class="act-total">/ {{ formatNumber(activity.breakfast.rooms_without) }}</span></div>
              <div class="act-sub">Phòng có ăn sáng / Không ăn sáng</div>
              <div class="act-sub">Người lớn: <strong>{{ formatNumber(activity.breakfast.adults) }}</strong> · Trẻ em: <strong>{{ formatNumber(activity.breakfast.children) }}</strong></div>
            </div>
          </div>
        </section>
      </div>

      <!-- Hàng 2: Doanh thu theo tháng + Cơ cấu doanh thu -->
      <div class="dash-grid">
        <section class="dash-card flex flex-col">
          <div class="section-head">
            <span class="section-title">Doanh thu theo tháng</span>
            <div class="flex items-center gap-4 text-slate-600">
              <span class="flex items-center gap-1.5"><i class="legend-dot" :style="{ background: CURRENT_COLOR }"></i>Năm {{ revenueYear }}</span>
              <span class="flex items-center gap-1.5"><i class="legend-dot" :style="{ background: PREVIOUS_COLOR }"></i>Năm {{ revenueYear - 1 }}</span>
            </div>
          </div>
          <div ref="chartBox" class="relative flex-1" @mouseleave="hoveredMonth = null">
            <svg :width="chartWidth" :height="CHART_HEIGHT" class="block" role="img" aria-label="Doanh thu theo tháng">
              <text :x="PAD.left" y="10" class="axis-text" text-anchor="start">(Triệu VND)</text>
              <g v-for="tick in chartScale.ticks" :key="tick">
                <line :x1="PAD.left" :x2="chartWidth - PAD.right" :y1="chartScale.y(tick)" :y2="chartScale.y(tick)"
                  :stroke="tick === 0 ? '#94A3B8' : '#E2E8F0'" :stroke-dasharray="tick === 0 ? '' : '3 3'" stroke-width="1" />
                <text :x="PAD.left - 6" :y="chartScale.y(tick) + 4" class="axis-text" text-anchor="end">{{ formatAxis(tick) }}</text>
              </g>
              <g v-for="group in chartBars" :key="group.month">
                <rect :x="group.groupX" :y="PAD.top" :width="group.groupWidth" :height="CHART_HEIGHT - PAD.top - PAD.bottom"
                  :fill="hoveredMonth?.month === group.month ? 'rgba(47,111,237,0.06)' : 'transparent'"
                  @mouseenter="hoveredMonth = group" />
                <rect v-for="bar in [group.previous, group.current]" :key="bar.key" :x="bar.x" :y="bar.y" :width="bar.width"
                  :height="Math.max(bar.height, bar.value ? 1 : 0)" :fill="bar.color" rx="3" class="pointer-events-none" />
                <text :x="group.center" :y="CHART_HEIGHT - 8" class="axis-text" text-anchor="middle">{{ String(group.month).padStart(2, '0') }}</text>
              </g>
            </svg>
            <div v-if="hoveredMonth" class="chart-tooltip" :style="tooltipStyle">
              <div class="mb-1 font-semibold">Tháng {{ hoveredMonth.month }}</div>
              <div class="flex items-center gap-1.5"><i class="legend-dot" :style="{ background: CURRENT_COLOR }"></i>Năm {{ revenueYear }}: <strong>{{ formatNumber(hoveredMonth.item.current) }}</strong></div>
              <div class="flex items-center gap-1.5"><i class="legend-dot" :style="{ background: PREVIOUS_COLOR }"></i>Năm {{ revenueYear - 1 }}: <strong>{{ formatNumber(hoveredMonth.item.previous) }}</strong></div>
            </div>
          </div>
        </section>

        <section class="dash-card">
          <div class="section-head">
            <span class="section-title">Cơ cấu doanh thu</span>
            <div class="tab-group">
              <button v-for="period in revenuePeriods" :key="period.key" type="button" class="tab-btn"
                :class="{ active: revenuePeriod === period.key }" @click="revenuePeriod = period.key">{{ period.label }}</button>
            </div>
          </div>
          <template v-if="revenueSummary">
            <div class="mb-3">
              <div class="text-slate-500">Tổng (VND) · {{ formatDate(revenueSummary.from) }} - {{ formatDate(revenueSummary.to) }}</div>
              <div class="text-[22px] font-bold tabular-nums">{{ formatNumber(revenueSummary.total) }}</div>
            </div>
            <div class="flex flex-col gap-2.5">
              <div v-for="row in departmentRows" :key="row.code" class="flex items-center gap-2.5">
                <span class="w-36 shrink-0 truncate text-slate-600" :title="row.name">{{ row.name }}</span>
                <div class="h-2 flex-1 overflow-hidden rounded bg-slate-200"><div class="h-full rounded" :style="{ width: `${row.width}%`, background: row.color }"></div></div>
                <span class="w-28 shrink-0 text-right font-semibold tabular-nums">{{ formatNumber(row.total) }}</span>
              </div>
              <div v-if="!departmentRows.length" class="py-2 text-slate-400">Chưa phát sinh doanh thu trong kỳ.</div>
            </div>
            <div class="mt-3 border-t border-slate-200 pt-3">
              <div class="mb-2 font-semibold text-slate-600">Thanh toán</div>
              <div class="flex flex-col gap-2">
                <div class="pay-row border-[#1BAF7A] bg-[#E6F7F2] text-[#13805A]">
                  <span class="font-semibold">Đã thanh toán</span>
                  <span class="text-[13px] font-bold tabular-nums">{{ formatNumber(revenueSummary.paid) }}</span>
                </div>
                <div class="pay-row border-[#E8A33D] bg-[#FFF4DC] text-[#9A6400]">
                  <span class="font-semibold">Chưa thanh toán</span>
                  <span class="text-[13px] font-bold tabular-nums">{{ formatNumber(revenueSummary.unpaid) }}</span>
                </div>
              </div>
            </div>
          </template>
        </section>
      </div>

      <!-- Hàng 3: Dự đoán bán phòng + Top 5 -->
      <div class="dash-grid">
        <section class="dash-card flex flex-col gap-3">
          <div class="flex flex-wrap items-center gap-2.5">
            <span class="section-title">Dự đoán bán phòng</span>
            <div class="tab-group">
              <button v-for="period in forecastPeriods" :key="period.key" type="button" class="tab-btn"
                :class="{ active: forecastPeriod === period.key }" @click="forecastPeriod = period.key">{{ period.label }}</button>
            </div>
            <label class="ml-auto flex cursor-pointer items-center gap-2 text-slate-600">
              DT phòng bao gồm ăn sáng
              <span class="toggle"><input v-model="includeBreakfast" type="checkbox"><span class="toggle-slider"></span></span>
            </label>
          </div>
          <div class="text-slate-500">{{ forecastPeriodLabel }}</div>
          <div v-if="forecast" class="forecast-body">
            <div class="flex min-w-36 flex-col gap-1.5">
              <div class="fc-stat">Phòng bán<strong>{{ formatPercent(forecast.occupancy_rate) }}</strong></div>
              <div class="fc-stat">Tổng đặt phòng<strong>{{ formatNumber(forecastWindow.bookings) }}</strong></div>
              <div class="fc-stat">Phòng / SL khách<strong>{{ formatNumber(forecast.occupied) }} / {{ formatNumber(forecast.guests) }}</strong></div>
              <div class="fc-stat">SL đăng ký được tạo<strong>{{ formatNumber(forecastWindow.created_bookings) }}</strong></div>
            </div>
            <div class="relative flex shrink-0 items-center justify-center">
              <svg width="128" height="128" viewBox="0 0 120 120" role="img" aria-label="Tỷ lệ phòng bán">
                <circle cx="60" cy="60" :r="DONUT_RADIUS" fill="none" stroke="#C9D8F5" stroke-width="16" />
                <circle cx="60" cy="60" :r="DONUT_RADIUS" fill="none" :stroke="CURRENT_COLOR" stroke-width="16"
                  :stroke-dasharray="`${donutSold} ${DONUT_LENGTH}`" transform="rotate(-90 60 60)" />
              </svg>
              <div class="absolute text-center leading-tight">
                <div class="text-lg font-bold">{{ formatPercent(forecast.occupancy_rate) }}</div>
                <div class="text-slate-500">Bán phòng</div>
              </div>
            </div>
            <div class="flex flex-1 flex-col justify-center gap-2">
              <div class="fc-metric"><i class="legend-dot bg-[#C9D8F5]"></i><span class="flex-1 text-slate-600">Phòng trống có thể bán</span><span class="fc-val">{{ formatNumber(forecast.available) }}</span></div>
              <div class="fc-metric"><i class="legend-dot bg-[#E87BA4]"></i><span class="flex-1 text-slate-600">Phòng chưa bán</span><span class="fc-val">{{ formatNumber(forecast.unsold) }}</span></div>
              <div class="fc-metric"><i class="legend-dot bg-[#E8A33D]"></i><span class="flex-1 text-slate-600">Doanh thu phòng</span><span class="fc-val">{{ formatNumber(forecast.revenue) }}</span></div>
              <div class="fc-metric"><i class="legend-dot bg-[#1BAF7A]"></i><span class="flex-1 text-slate-600">Giá phòng trung bình</span><span class="fc-val">{{ formatNumber(forecast.average_rate) }}</span></div>
              <div class="fc-metric"><i class="legend-dot bg-[#E34948]"></i><span class="flex-1 text-slate-600">DT phòng / Tổng phòng KS</span><span class="fc-val">{{ formatNumber(forecast.revpar) }}</span></div>
            </div>
          </div>
          <div class="flex gap-4 border-t border-slate-200 pt-2 text-slate-600">
            <span class="flex items-center gap-1.5"><i class="legend-dot bg-[#C9D8F5]"></i>Phòng trống</span>
            <span class="flex items-center gap-1.5"><i class="legend-dot" :style="{ background: CURRENT_COLOR }"></i>Phòng bán</span>
          </div>
        </section>

        <section class="dash-card">
          <div class="section-head">
            <div>
              <div class="section-title">Top 5 công suất phòng</div>
              <div class="mt-0.5 text-slate-500">Đêm phòng · {{ forecastPeriodLabel }}</div>
            </div>
            <div class="tab-group">
              <button v-for="group in topGroups" :key="group.key" type="button" class="tab-btn"
                :class="{ active: topGroup === group.key }" @click="topGroup = group.key">{{ group.label }}</button>
            </div>
          </div>
          <div class="flex flex-col gap-2.5">
            <div v-for="(row, index) in topRows" :key="row.name" class="flex items-center gap-2">
              <span class="top-rank">{{ index + 1 }}</span>
              <span class="w-44 shrink-0 truncate" :title="row.name" data-no-translate>{{ row.name }}</span>
              <div class="h-2 flex-1 overflow-hidden rounded bg-slate-200"><div class="h-full rounded" :style="{ width: `${row.share}%`, background: CURRENT_COLOR }"></div></div>
              <span class="w-14 shrink-0 text-right font-semibold tabular-nums">{{ row.share }}%</span>
              <span class="w-16 shrink-0 text-right tabular-nums text-slate-500">{{ formatNumber(row.room_nights) }} đêm</span>
            </div>
            <div v-if="!topRows.length" class="py-4 text-center text-slate-400">Chưa có dữ liệu trong kỳ.</div>
          </div>
        </section>
      </div>
    </template>

    <!-- Popup chi tiết -->
    <div v-if="popup" class="fixed inset-0 z-[200] flex items-start justify-center overflow-y-auto bg-slate-900/40 px-4 py-6" @click.self="closePopup">
      <div class="w-full max-w-5xl overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex items-center gap-3 px-5 py-3 text-white" style="background: var(--pms-custom-theme, #006bdb)">
          <div class="flex-1">
            <div class="text-sm font-semibold">{{ popupConfig[popup].title }}</div>
            <div class="text-white/80">{{ formatDate(data?.system_date) }}</div>
          </div>
          <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 hover:bg-white/25" @click="closePopup"><X class="h-4 w-4" /></button>
        </div>
        <div class="border-b border-slate-200 p-4">
          <div class="grid grid-cols-2 gap-2.5 md:grid-cols-4">
            <template v-if="popup === 'inhouse'">
              <div class="kpi border-[#2F6FED]"><div class="kpi-label">Phòng đang ở</div><div class="kpi-val">{{ formatNumber(activity.inhouse_current) }}</div></div>
              <div class="kpi border-[#1BAF7A]"><div class="kpi-label">Dự kiến cuối ngày</div><div class="kpi-val">{{ formatNumber(activity.inhouse_projected) }}</div></div>
              <div class="kpi border-[#E8A33D]"><div class="kpi-label">Tổng phòng KS</div><div class="kpi-val">{{ formatNumber(activity.total_rooms) }}</div></div>
              <div class="kpi border-[#2F6FED]"><div class="kpi-label">Công suất dự kiến</div><div class="kpi-val">{{ formatPercent(activity.occupancy_rate) }}</div></div>
            </template>
            <template v-else>
              <div class="kpi border-[#2F6FED]"><div class="kpi-label">Tổng</div><div class="kpi-val">{{ formatNumber(popupRows.length) }}</div></div>
              <div class="kpi border-[#1BAF7A]"><div class="kpi-label">{{ popupConfig[popup].done }}</div><div class="kpi-val text-[#13805A]">{{ formatNumber(popupDoneCount) }}</div></div>
              <div class="kpi border-[#E8A33D]"><div class="kpi-label">{{ popupConfig[popup].pending }}</div><div class="kpi-val text-[#9A6400]">{{ formatNumber(popupRows.length - popupDoneCount) }}</div></div>
            </template>
          </div>
        </div>
        <div class="p-4">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1">
              <Search class="pointer-events-none absolute top-2 left-2.5 h-4 w-4 text-[#A8B0BF]" />
              <input v-model="popupSearch" type="text" placeholder="Tìm phòng, tên khách, mã đăng ký..."
                class="h-8 w-full rounded-lg border border-slate-200 pr-3 pl-8 text-xs font-normal text-[#000000D9] outline-none placeholder:text-[#A8B0BF] focus:border-[#2F6FED]">
            </div>
            <template v-if="popup !== 'inhouse'">
              <button v-for="filter in [{ key: 'all', label: 'Tất cả' }, { key: 'pending', label: popupConfig[popup].pending }, { key: 'done', label: popupConfig[popup].done }]"
                :key="filter.key" type="button" class="filter-btn" :class="{ active: popupFilter === filter.key }" @click="popupFilter = filter.key">{{ filter.label }}</button>
            </template>
          </div>
          <div class="max-h-[55vh] overflow-auto rounded-lg border border-slate-200">
            <table class="w-full border-collapse text-xs">
              <thead class="sticky top-0 bg-slate-50">
                <tr>
                  <th>Mã đăng ký</th><th>Mã tham chiếu</th><th>Tên đăng ký / Khách</th><th>Công ty</th><th>Trạng thái</th>
                  <th>Ngày đến</th><th>Ngày đi</th><th>Phòng</th><th class="text-right">Người lớn / Trẻ em</th><th>Ghi chú</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="popupLoading"><td colspan="10" class="py-8 text-center text-slate-500"><LoaderCircle class="mr-1 inline h-4 w-4 animate-spin" /> Đang tải...</td></tr>
                <tr v-else-if="!filteredPopupRows.length"><td colspan="10" class="py-8 text-center text-slate-400">Không có dữ liệu.</td></tr>
                <template v-else>
                <tr v-for="row in filteredPopupRows" :key="row.id" class="hover:bg-slate-50">
                  <td><button type="button" class="font-semibold text-[#2F6FED] hover:underline" @click="emit('open-booking', row.booking_code)">{{ row.booking_code }}</button></td>
                  <td data-no-translate>{{ row.reference_code || '-' }}</td>
                  <td data-no-translate><div class="font-semibold">{{ row.booking_name || '-' }}</div><div class="text-slate-500">{{ row.guest_name }}<template v-if="row.nationality"> · {{ row.nationality }}</template></div></td>
                  <td data-no-translate>{{ row.company || '-' }}</td>
                  <td><span class="status-chip" :class="row.done ? 'done' : 'pending'">{{ statusLabels[row.status] || row.status }}</span></td>
                  <td>{{ formatDate(row.arrival_date) }}</td>
                  <td>{{ formatDate(row.departure_date) }}</td>
                  <td><div class="font-semibold">{{ row.room_number || '-' }}</div><div class="text-slate-500" data-no-translate>{{ row.room_class }}</div></td>
                  <td class="text-right tabular-nums">{{ row.adults }} / {{ row.children }}</td>
                  <td class="max-w-48 truncate" :title="row.note" data-no-translate>{{ row.note || '-' }}</td>
                </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dash-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
@media (max-width: 1100px) { .dash-grid { grid-template-columns: minmax(0, 1fr); } }
.dash-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px; min-width: 0; }
.section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.section-title { font-size: 13px; font-weight: 600; color: #000000D9; }

.bp-row { display: grid; grid-template-columns: 1fr 80px 80px; padding: 8px 2px; border-bottom: 1px solid #E2E8F0; font-size: 12px; }
.bp-row:last-child { border-bottom: none; }
.bp-head { font-weight: 600; color: #64748B; padding-top: 0; }

.act-tile { position: relative; background: #F1F5F9; border-radius: 8px; padding: 12px; }
.act-label { display: flex; align-items: center; gap: 6px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.act-value { font-size: 24px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
.act-total { font-size: 14px; font-weight: 500; color: #64748B; }
.act-sub { color: #64748B; margin-top: 4px; }
.act-detail { position: absolute; top: 8px; right: 8px; font-size: 12px; font-weight: 600; color: #2F6FED; background: #E8EFFD; border-radius: 6px; padding: 2px 8px; }
.act-detail:hover { background: #D4E2FB; }
.progress { height: 5px; border-radius: 3px; background: #E2E8F0; margin-top: 8px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 3px; }

.tab-group { display: flex; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; }
.tab-btn { padding: 5px 12px; font-size: 12px; font-weight: 600; color: #475569; background: #fff; }
.tab-btn + .tab-btn { border-left: 1px solid #E2E8F0; }
.tab-btn.active { background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #fff); }

.legend-dot { display: inline-block; width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
.axis-text { font-size: 12px; fill: #64748B; }
.chart-tooltip { position: absolute; pointer-events: none; background: #1E2D4A; color: #fff; border-radius: 8px; padding: 8px 12px; line-height: 1.6; white-space: nowrap; box-shadow: 0 4px 16px rgba(0,0,0,.18); z-index: 10; }

.pay-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 8px; border-left: 3px solid; }

.toggle { position: relative; width: 34px; height: 18px; display: inline-block; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: #CBD5E1; border-radius: 18px; transition: background .2s; }
.toggle-slider::before { content: ''; position: absolute; width: 12px; height: 12px; border-radius: 50%; background: #fff; top: 3px; left: 3px; transition: transform .2s; }
.toggle input:checked + .toggle-slider { background: var(--pms-custom-theme, #006bdb); }
.toggle input:checked + .toggle-slider::before { transform: translateX(16px); }

.forecast-body { display: flex; gap: 14px; align-items: stretch; flex-wrap: wrap; }
.fc-stat { background: var(--pms-custom-theme, #006bdb); color: var(--pms-custom-theme-text, #fff); border-radius: 8px; padding: 6px 12px; font-size: 12px; line-height: 1.4; }
.fc-stat strong { display: block; font-size: 15px; font-weight: 700; }
.fc-metric { display: flex; align-items: center; gap: 8px; }
.fc-val { font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; background: #F1F5F9; border-radius: 6px; padding: 2px 10px; min-width: 96px; text-align: right; }

.top-rank { width: 22px; height: 22px; border-radius: 50%; background: #E8EFFD; color: #2F6FED; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.kpi { background: #F1F5F9; border-radius: 8px; padding: 10px 12px; border-left: 3px solid; }
.kpi-label { font-weight: 600; color: #475569; margin-bottom: 4px; }
.kpi-val { font-size: 22px; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }

.filter-btn { height: 32px; padding: 0 12px; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; color: #475569; background: #fff; }
.filter-btn.active { border-color: #2F6FED; color: #2F6FED; background: #E8EFFD; }

th { text-align: left; font-weight: 600; color: #475569; padding: 8px 10px; border-bottom: 1px solid #E2E8F0; white-space: nowrap; }
td { padding: 8px 10px; border-bottom: 1px solid #E2E8F0; vertical-align: middle; }
.status-chip { display: inline-block; padding: 1px 8px; border-radius: 6px; font-weight: 600; white-space: nowrap; }
.status-chip.done { background: #E6F7F2; color: #13805A; }
.status-chip.pending { background: #FFF4DC; color: #9A6400; }
</style>
