<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'
import { useReportStore } from '@/stores/report-store'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { Database, Download, FileText, LoaderCircle, Play, Printer } from '@lucide/vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import ReportDateRangePicker from '@/components/ReportDateRangePicker.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import HousekeepingInvoiceFilters from '@/pages/reports/components/HousekeepingInvoiceFilters.vue'
import ReportViewer from '@/pages/reports/components/ReportViewer.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const reportStore = useReportStore()
const uiStore = useUiStore()
const loading = ref(false)
const systemDate = ref(authStore.systemDate || localStorage.getItem('pms_system_date') || '')
const reports = ref([])
const activeTabId = ref(null)
const openTabs = ref([])
const reportTabs = ref(null)
const temporaryReportCode = 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT'
const customDatePickerReportCodes = new Set(['REVENUE_ARMY', temporaryReportCode])
let workspaceSaveTimer = null

const pmsWorkspaceKey = computed(() => [
  authStore.user?.id || authStore.user?.username || 'anonymous',
  authStore.activeBranch?.id
    || authStore.activeBranch?.code
    || localStorage.getItem('selected_branch_id')
    || localStorage.getItem('selected_branch_code')
    || 'default',
  route.path,
].join(':'))

const cloneWorkspaceValue = value => {
  if (value === undefined) return undefined
  return JSON.parse(JSON.stringify(value))
}

const saveWorkspace = (key = pmsWorkspaceKey.value) => {
  if (!key) return
  reportStore.savePmsWorkspace(key, {
    activeTabId: activeTabId.value,
    openTabs: openTabs.value.map(tab => ({
      reportId: tab.report.id,
      code: tab.code,
      parameters: cloneWorkspaceValue(tab.parameters),
      parameterOptions: cloneWorkspaceValue(tab.parameterOptions),
      selectedTemplateId: tab.selectedTemplateId,
      dataset: cloneWorkspaceValue(tab.dataset),
      renderedHtml: tab.renderedHtml,
    })),
  })
}

const queueWorkspaceSave = () => {
  if (workspaceSaveTimer) window.clearTimeout(workspaceSaveTimer)
  workspaceSaveTimer = window.setTimeout(() => {
    workspaceSaveTimer = null
    saveWorkspace()
  }, 150)
}

const flushWorkspaceSave = () => {
  if (workspaceSaveTimer) {
    window.clearTimeout(workspaceSaveTimer)
    workspaceSaveTimer = null
  }
  saveWorkspace()
}

const handleReportTabsWheel = (event) => {
  const container = reportTabs.value
  if (!container || container.scrollWidth <= container.clientWidth || !event.deltaY) return
  event.preventDefault()
  container.scrollLeft += event.deltaY
}

const activeTab = computed(() => openTabs.value.find(t => t.id === activeTabId.value) || null)
const isWeeklyRoomReport = tab => tab?.code === 'WEEKLY_ROOM_REPORT'
const isVisibleReportParameter = (tab, parameter) => (
  parameter.control !== 'hidden'
  && (!isWeeklyRoomReport(tab) || parameter.name !== 'p_from_date' || tab.parameters.p_week_preset === 'custom')
)

const housekeepingInvoiceCodes = new Set([
  'LAUNDRY_INVOICES',
  'BREAKAGE_INVOICES',
  'MINIBAR_INVOICES',
  'LAUNDRY_INVOICES_BY_PRODUCT',
  'LAUNDRY_FREE_INVOICES',
  'BREAKAGE_INVOICES_BY_PRODUCT',
  'BREAKAGE_FREE_INVOICES',
  'MINIBAR_INVOICES_BY_PRODUCT',
  'MINIBAR_FREE_INVOICES',
])
const isHousekeepingInvoiceReport = (tab) => housekeepingInvoiceCodes.has(tab?.code)
const usesCustomDatePicker = (tab) => customDatePickerReportCodes.has(tab?.code)
const parameterOptions = (tab, name) => tab?.parameterOptions?.[name]
  || tab?.report?.parameter_ui_schema?.find(parameter => parameter.name === name)?.options
  || []
const submitHousekeepingInvoice = (tab, parameters) => {
  Object.assign(tab.parameters, parameters)
  executeTab(tab)
}

const activeTemplate = computed(() => {
  if (!activeTab.value || !activeTab.value.selectedTemplateId) return null
  return activeTab.value.report.templates.find(t => t.id === activeTab.value.selectedTemplateId) || null
})

const reportViewer = ref(null)

const localToday = () => {
  const date = new Date()
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

const fetchSystemDate = async () => {
  try {
    const response = await http.get('/system-date')
    const sysDate = response.data?.data?.system_date || localToday()
    systemDate.value = sysDate
    authStore.setSystemDate(sysDate)
  } catch {
    systemDate.value = authStore.systemDate || localToday()
  }
}

const loadParameterOptionsForTab = async (tab) => {
  const report = tab.report
  const dynamic = (report.parameter_ui_schema || []).filter(parameter => parameter.options_source)
  await Promise.all(dynamic.map(async parameter => {
    try {
      const response = await http.get(`/report-lookups/${parameter.options_source}`)
      tab.parameterOptions[parameter.name] = response.data.data || []
    } catch (error) {
      uiStore.showToast(`Không thể tải danh mục cho “${parameter.label}”`, 'warning')
    }
  }))
}

const loadReports = async ({ restore = true } = {}) => {
  loading.value = true
  try {
    const response = await http.get('/report-definitions', { params: { active_only: 1 } })
    const availableReports = response.data.data || []
    const hasReportPermission = authStore.isSuperAdmin
      || authStore.permissions.includes('mgmt.report.view')
    const isTemporaryReportEntry = route.query.report === temporaryReportCode && !hasReportPermission
    reports.value = isTemporaryReportEntry
      ? availableReports.filter(item => item.code === temporaryReportCode)
      : availableReports

    const restored = restoreWorkspace(restore ? reportStore.getPmsWorkspace(pmsWorkspaceKey.value) : null)

    // Check initial report code from URL
    const requestedCode = route.query.report
    if (requestedCode) {
      const report = reports.value.find(item => item.code === requestedCode)
      if (report) {
        openReportInTab(report)
        return
      }
    }

    if (restored) {
      const tab = openTabs.value.find(item => Number(item.id) === Number(activeTabId.value))
      if (tab && route.query.report !== tab.code) {
        router.replace({ path: route.path, query: { ...route.query, report: tab.code } })
      }
      return
    }

    // Default: Open the first report in dynamic menu if any
    if (reports.value.length > 0) {
      openReportInTab(reports.value[0])
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải danh mục báo cáo', 'error')
  } finally {
    loading.value = false
  }
}

const resolveDefault = (value) => {
  const baseDate = new Date(`${systemDate.value || localToday()}T00:00:00`)
  const localDate = (date) => {
    const offset = date.getTimezoneOffset() * 60000
    return new Date(date.getTime() - offset).toISOString().slice(0, 10)
  }
  if (value === '$today') return localDate(baseDate)
  if (value === '$yesterday') return localDate(new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate() - 1))
  if (value === '$month_start') return localDate(new Date(baseDate.getFullYear(), baseDate.getMonth(), 1))
  if (value === '$month_end') return localDate(new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0))
  return value ?? ''
}

const parseIsoDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return null
  const [year, month, day] = String(value).split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day))
}

const formatIsoDate = date => date.toISOString().slice(0, 10)
const addDays = (date, amount) => new Date(date.getTime() + amount * 86400000)
const mondayOf = date => addDays(date, date.getUTCDay() === 0 ? -6 : 1 - date.getUTCDay())

const vietnamToday = () => {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).formatToParts(new Date())
  const values = Object.fromEntries(parts.filter(part => part.type !== 'literal').map(part => [part.type, part.value]))
  return [values.year, values.month, values.day].join('-')
}

const applyWeeklyDateRange = (tab) => {
  if (!isWeeklyRoomReport(tab)) return
  const preset = tab.parameters.p_week_preset || 'this_week'
  const anchor = parseIsoDate(systemDate.value || vietnamToday()) || parseIsoDate(vietnamToday())
  let start = mondayOf(anchor)
  if (preset === 'last_week') start = addDays(start, -7)
  if (preset === 'next_week') start = addDays(start, 7)
  if (preset === 'custom') start = mondayOf(parseIsoDate(tab.parameters.p_from_date) || start)
  tab.parameters.p_from_date = formatIsoDate(start)
  tab.parameters.p_to_date = formatIsoDate(addDays(start, 6))
}

const handleReportParameterChange = (tab, parameter) => {
  if (isWeeklyRoomReport(tab) && parameter.name === 'p_week_preset') applyWeeklyDateRange(tab)
}

const defaultParametersForReport = (report) => {
  const defaultParams = {}
  for (const definition of report.parameter_ui_schema || []) {
    const resolved = resolveDefault(definition.default)
    const options = definition.options || []
    const firstOption = options[0]
    const legacyDefault = definition.name === 'p_sort_by'
      ? 'Room'
      : definition.name === 'p_order_by'
        ? 'ASC'
        : ''
    defaultParams[definition.name] = definition.control === 'multi-select'
      ? (Array.isArray(resolved) ? resolved : (resolved ? String(resolved).split(',').map(value => value.trim()).filter(Boolean) : []))
      : resolved || (
        ['select', 'radio'].includes(definition.control) && firstOption !== undefined
          ? (firstOption.value ?? firstOption)
          : legacyDefault || resolved
      )
  }
  return defaultParams
}

// Tab lưu từ phiên trước chỉ dùng lại khi tham số khớp cấu hình hiện tại; báo cáo đổi tham số
// (vd. dựng lại Store) thì lấy giá trị mặc định cho tham số mới và bỏ kết quả cũ đã dựng.
const snapshotMatchesReport = (report, snapshot) => {
  const saved = snapshot?.parameters
  if (!saved) return false
  const names = (report.parameter_ui_schema || []).map(item => item.name)
  return names.every(name => Object.hasOwn(saved, name)) && Object.keys(saved).every(name => names.includes(name))
}

const restoredParameters = (report, snapshot) => {
  const defaults = defaultParametersForReport(report)
  const saved = snapshot?.parameters || {}
  return Object.fromEntries(Object.keys(defaults).map(name => [name, Object.hasOwn(saved, name) ? saved[name] : defaults[name]]))
}

const createReportTab = (report, snapshot = null) => {
  const usable = snapshotMatchesReport(report, snapshot)
  return reactive({
  id: report.id,
  code: report.code,
  name: report.name,
  report,
  parameters: restoredParameters(report, snapshot),
  parameterOptions: {
    ...Object.fromEntries((report.parameter_ui_schema || []).map(parameter => [parameter.name, parameter.options || []])),
    ...(usable ? snapshot.parameterOptions : {}),
  },
  selectedTemplateId: report.templates.some(item => Number(item.id) === Number(snapshot?.selectedTemplateId))
    ? snapshot.selectedTemplateId
    : report.templates.find(item => item.is_default)?.id || report.templates[0]?.id || null,
  dataset: usable ? snapshot.dataset || null : null,
  renderedHtml: usable ? snapshot.renderedHtml || '' : '',
  executing: false,
  rendering: false,
  exporting: false,
  })
}

const restoreWorkspace = (workspace) => {
  if (!workspace?.openTabs?.length) return false
  const restoredTabs = workspace.openTabs.map(snapshot => {
    const report = reports.value.find(item => Number(item.id) === Number(snapshot.reportId))
    return report ? createReportTab(report, snapshot) : null
  }).filter(Boolean)
  if (!restoredTabs.length) return false

  openTabs.value = restoredTabs
  activeTabId.value = restoredTabs.some(tab => Number(tab.id) === Number(workspace.activeTabId))
    ? workspace.activeTabId
    : restoredTabs[0].id
  restoredTabs.forEach(tab => loadParameterOptionsForTab(tab))
  return true
}

const openReportInTab = (report) => {
  let tab = openTabs.value.find(t => t.id === report.id)
  if (!tab) {
    tab = createReportTab(report)

    openTabs.value.push(tab)
    loadParameterOptionsForTab(tab)
  }

  activeTabId.value = report.id
  if (route.query.report !== report.code) {
    router.replace({ path: route.path, query: { ...route.query, report: report.code } })
  }
}

const selectTab = (tabId) => {
  const tab = openTabs.value.find(t => t.id === tabId)
  if (tab) {
    activeTabId.value = tabId
    router.replace({ path: route.path, query: { ...route.query, report: tab.code } })
  }
}

const closeTab = (tabId) => {
  const index = openTabs.value.findIndex(t => t.id === tabId)
  if (index !== -1) {
    openTabs.value.splice(index, 1)

    if (activeTabId.value === tabId) {
      if (openTabs.value.length > 0) {
        const newActiveTab = openTabs.value[Math.min(index, openTabs.value.length - 1)]
        activeTabId.value = newActiveTab.id
        router.replace({ path: route.path, query: { ...route.query, report: newActiveTab.code } })
      } else {
        activeTabId.value = null
        const newQuery = { ...route.query }
        delete newQuery.report
        router.replace({ path: route.path, query: newQuery })
      }
    }
  }
}

const normalizeReportParameters = (tab) => {
  for (const definition of tab.report.parameter_ui_schema || []) {
    if (tab.parameters[definition.name] !== '' && tab.parameters[definition.name] !== null && tab.parameters[definition.name] !== undefined) continue

    const firstOption = (definition.options || [])[0]
    const legacyDefault = definition.name === 'p_sort_by'
      ? 'Room'
      : definition.name === 'p_order_by'
        ? 'ASC'
        : ''

    if (firstOption !== undefined) {
      tab.parameters[definition.name] = firstOption.value ?? firstOption
    } else if (legacyDefault) {
      tab.parameters[definition.name] = legacyDefault
    }
  }
}

const reportParametersPayload = (tab) => Object.fromEntries(
  Object.entries(tab.parameters).map(([name, value]) => {
    const definition = (tab.report.parameter_ui_schema || []).find(item => item.name === name)
    return [name, definition?.control === 'multi-select' && Array.isArray(value) ? value.join(',') : value]
  })
)

const hasEmptySelectOption = (tab, parameter) => (
  tab.parameterOptions[parameter.name] || parameter.options || []
).some(option => (option?.value ?? option) === '')

const multiSelectOptions = (tab, parameter) => tab.parameterOptions[parameter.name] || parameter.options || []

const multiSelectLabel = (tab, parameter) => {
  const selected = Array.isArray(tab.parameters[parameter.name]) ? tab.parameters[parameter.name] : []
  if (!selected.length) return '-- Chọn dịch vụ --'
  const options = multiSelectOptions(tab, parameter)
  const labels = selected.map(value => options.find(option => (option?.value ?? option) === value)?.label ?? value)
  return labels.length > 2 ? `${labels.slice(0, 2).join(', ')} (+${labels.length - 2})` : labels.join(', ')
}

const syncTemplateSummary = (tab, summary) => {
  if (!tab || !summary?.id) return
  const template = (tab.report.templates || []).find(item => Number(item.id) === Number(summary.id))
  if (template) Object.assign(template, summary)
}

const executeTab = async (tab) => {
  if (!tab || !tab.selectedTemplateId) return
  normalizeReportParameters(tab)
  applyWeeklyDateRange(tab)
  const missing = (tab.report.parameter_ui_schema || [])
    .filter(item => item.required && (tab.parameters[item.name] === '' || tab.parameters[item.name] === undefined || (item.control === 'multi-select' && (!Array.isArray(tab.parameters[item.name]) || tab.parameters[item.name].length === 0))))
  if (missing.length) {
    uiStore.showToast(`Vui lòng nhập: ${missing.map(item => item.label).join(', ')}`, 'warning')
    return
  }
  tab.executing = true
  try {
    const response = await http.post(`/report-definitions/${tab.report.id}/execute`, {
      parameters: reportParametersPayload(tab), template_id: tab.selectedTemplateId
    })
    syncTemplateSummary(tab, response.data.template)
    tab.dataset = response.data.data
    tab.renderedHtml = response.data.html
    uiStore.showToast(`Đã tải ${tab.dataset.summary?.row_count || 0} dòng dữ liệu từ Store`, 'success')
  } catch (error) {
    // Lỗi không có message từ API (mạng, quá thời gian, lỗi JS khi nhận kết quả) chỉ hiện câu chung; ghi console để tra cứu.
    console.error('[report] execute failed', tab.code, error)
    tab.dataset = null
    tab.renderedHtml = ''
    uiStore.showToast(error.response?.data?.message || 'Không thể chạy báo cáo. Kiểm tra Store và tham số.', 'error')
  } finally {
    tab.executing = false
  }
}

const changeTemplateForTab = async (tab) => {
  if (!tab || !tab.dataset || !tab.selectedTemplateId) return
  tab.rendering = true
  try {
    const response = await http.post(`/report-definitions/${tab.report.id}/render`, {
      template_id: tab.selectedTemplateId,
      data: tab.dataset
    })
    syncTemplateSummary(tab, response.data.template)
    tab.renderedHtml = response.data.html
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể đổi mẫu đầu ra', 'error')
  } finally {
    tab.rendering = false
  }
}

const printReportForTab = (tab) => {
  if (!tab || !tab.renderedHtml) return
  reportViewer.value?.printAll()
}

// Nhấp đúp mã BK trong báo cáo: mở màn hình đăng ký của booking ở tab mới để giữ nguyên kết quả báo cáo.
const openBookingFromReport = (bookingCode) => {
  const target = router.resolve({ path: '/reservation', query: { tab: 'create-res', bookingCode } })
  window.open(target.href, '_blank', 'noopener')
}

// Nhấp đúp ô số liệu có data-pms-drill (vd. Báo cáo tổng doanh thu): mở báo cáo đích với đúng tham số rồi chạy luôn.
const openReportFromDrill = async ({ report: code, parameters = {} }) => {
  const report = reports.value.find(item => item.code === code)
  if (!report) {
    uiStore.showToast('Bạn chưa có quyền xem báo cáo chi tiết hoặc báo cáo chưa được bật.', 'warning')
    return
  }
  openReportInTab(report)
  const tab = openTabs.value.find(item => item.id === report.id)
  if (!tab) return
  const nextParameters = { ...defaultParametersForReport(report) }
  for (const [name, value] of Object.entries(parameters)) {
    const definition = (report.parameter_ui_schema || []).find(item => item.name === name)
    if (!definition) continue
    if (definition.control === 'multi-select') {
      nextParameters[name] = String(value ?? '').split(',').map(item => item.trim()).filter(Boolean)
    } else if (definition.control === 'checkbox') {
      nextParameters[name] = Boolean(Number(value))
    } else {
      nextParameters[name] = value
    }
  }
  tab.parameters = nextParameters
  await executeTab(tab)
}

const exportReportForTab = async (tab, format) => {
  if (!tab?.dataset || !tab.selectedTemplateId || tab.exporting) return
  tab.exporting = true
  try {
    const response = await http.post(`/report-definitions/${tab.report.id}/exports`, {
      parameters: reportParametersPayload(tab),
      template_id: tab.selectedTemplateId,
      format
    }, { responseType: 'arraybuffer' })
    const extension = format === 'xlsx' ? 'xlsx' : format
    const mimeTypes = {
      pdf: 'application/pdf',
      xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    }
    const disposition = response.headers['content-disposition'] || ''
    const match = disposition.match(/filename="?([^";]+)"?/i)
    const url = URL.createObjectURL(new Blob([response.data], {
      type: response.headers['content-type'] || mimeTypes[format]
    }))
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = match?.[1] || `${tab.report.code}_${new Date().toISOString().slice(0, 10)}.${extension}`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || `Không thể xuất ${format.toUpperCase()}`, 'error')
  } finally {
    tab.exporting = false
  }
}

const csvCell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`
const downloadCsvForTab = (tab) => {
  if (!tab) return
  const rows = tab.dataset?.rows || []
  const fields = tab.dataset?.fields?.map(item => item.name) || Object.keys(rows[0] || {})
  if (!fields.length) {
    uiStore.showToast('Báo cáo không có dữ liệu để xuất CSV', 'warning')
    return
  }
  const content = [fields.map(csvCell).join(','), ...rows.map(row => fields.map(field => csvCell(row[field])).join(','))].join('\r\n')
  const blob = new Blob([`\ufeff${content}`], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = `${tab.report.code}_${new Date().toISOString().slice(0, 10)}.csv`
  anchor.click()
  URL.revokeObjectURL(url)
}

const refreshReportTab = async (tab) => {
  const response = await http.get(`/report-definitions/${tab.report.id}`)
  const report = response.data.data
  const templates = report.templates || []
  const selectedTemplate = templates.find(item => Number(item.id) === Number(tab.selectedTemplateId))

  tab.report = report
  tab.selectedTemplateId = selectedTemplate?.id
    || templates.find(item => item.is_default)?.id
    || templates[0]?.id
    || null

  const reportIndex = reports.value.findIndex(item => Number(item.id) === Number(report.id))
  if (reportIndex !== -1) reports.value[reportIndex] = report
  if (tab.dataset && tab.selectedTemplateId) await changeTemplateForTab(tab)
}

const handleTemplateSaved = async (event) => {
  const templateId = Number(event.detail?.templateId)
  if (!templateId) return

  const affectedTabs = openTabs.value.filter(tab => (
    tab.report.templates || []
  ).some(template => Number(template.id) === templateId))

  try {
    await Promise.all(affectedTabs.map(refreshReportTab))
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể cập nhật mẫu báo cáo vừa lưu', 'warning')
  }
}

// Watch active tab template change
watch(() => activeTab.value?.selectedTemplateId, (val, oldVal) => {
  if (val && oldVal && val !== oldVal) {
    changeTemplateForTab(activeTab.value)
  }
})

// Watch p_show_room_details for EXPECTED_BREAKFAST to auto switch templates
watch(() => activeTab.value?.parameters?.p_show_room_details, (showDetails) => {
  if (!['EXPECTED_BREAKFAST', 'EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'].includes(activeTab.value?.code)) return
  const templates = activeTab.value.report?.templates || []
  if (templates.length <= 1) return

  const target = templates.find(t =>
    showDetails
      ? (t.report?.includes('DETAIL') || t.name?.includes('Chi tiết'))
      : (t.report?.includes('SUMMARY') || t.report?.includes('_1_') || t.name?.includes('Tổng hợp'))
  )
  if (target && target.id !== activeTab.value.selectedTemplateId) {
    activeTab.value.selectedTemplateId = target.id
    if (activeTab.value.dataset) {
      executeTab(activeTab.value)
    }
  }
})

// Watch route parameter change
watch(() => route.query.report, code => {
  if (code) {
    const report = reports.value.find(item => item.code === code)
    if (report && report.id !== activeTabId.value) {
      openReportInTab(report)
    }
  } else {
    if (activeTab.value) {
      router.replace({ path: route.path, query: { ...route.query, report: activeTab.value.code } })
    }
  }
})

watch(openTabs, queueWorkspaceSave, { deep: true })

watch(pmsWorkspaceKey, async (nextKey, previousKey) => {
  if (!previousKey || nextKey === previousKey) return
  saveWorkspace(previousKey)
  reports.value = []
  openTabs.value = []
  activeTabId.value = null
  await loadReports({ restore: true })
})

onMounted(async () => {
  window.addEventListener('pms:report-template-saved', handleTemplateSaved)
  window.addEventListener('pagehide', flushWorkspaceSave)
  await reportStore.hydratePmsWorkspaces()
  await fetchSystemDate()
  await loadReports()
})

onBeforeUnmount(() => {
  flushWorkspaceSave()
  window.removeEventListener('pms:report-template-saved', handleTemplateSaved)
  window.removeEventListener('pagehide', flushWorkspaceSave)
})
</script>

<template>
  <div class="relative flex h-full min-h-0 bg-slate-100 text-[#000000D9]">
    <LoadingOverlay :show="loading || activeTab?.executing || activeTab?.rendering" />
    <main class="flex min-w-0 flex-1 flex-col">
      <!-- Tabs Bar -->
      <div v-if="openTabs.length > 0" class="h-11 shrink-0 border-b border-slate-200 bg-slate-50">
        <div ref="reportTabs" @wheel="handleReportTabsWheel" class="report-tabs flex h-full min-w-0 items-end gap-1 overflow-x-auto overflow-y-hidden px-4">
        <div v-for="tab in openTabs" :key="tab.id"
          @click="selectTab(tab.id)"
          class="-mb-px flex h-[36px] shrink-0 cursor-pointer items-center gap-2 rounded-t-lg border-x border-t border-b-transparent px-3 py-2 text-[12px] font-semibold transition-all"
          :class="activeTabId === tab.id
            ? 'bg-white border-slate-200 text-[#000000D9] shadow-xs relative z-10'
            : 'bg-slate-100/60 border-transparent text-[#000000D9] hover:bg-slate-100'"
        >
          <span class="whitespace-nowrap">{{ tab.name }}</span>
          <button @click.stop="closeTab(tab.id)" class="flex cursor-pointer items-center justify-center rounded-full border-none bg-transparent p-0.5 text-[#000000D9] hover:bg-slate-200">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        </div>
      </div>

      <!-- Tab Content Workspace -->
      <template v-if="activeTab">
        <header class="border-b border-slate-200 bg-white px-5 py-3">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="text-sm font-semibold text-[#000000D9]">{{ activeTab.name }}</h2>
              <p class="mt-0.5 flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                <Database class="h-3 w-3" /> {{ activeTab.report.report_data_source?.object_name }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-[12px] font-semibold uppercase text-[#000000D9]">Mẫu đầu ra
                <select v-model="activeTab.selectedTemplateId" class="ml-2 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-[#000000D9]">
                  <option v-for="template in activeTab.report.templates" :key="template.id" :value="template.id">
                    {{ template.name }}{{ template.is_default ? ' (Mặc định)' : '' }}
                  </option>
                </select>
              </label>
              <button :disabled="!activeTab.dataset || activeTab.exporting" @click="exportReportForTab(activeTab, 'xlsx')" class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-[#000000D9] disabled:opacity-40"><Download class="h-4 w-4" /> Excel</button>
              <button :disabled="!activeTab.renderedHtml" @click="printReportForTab(activeTab)" class="flex items-center gap-1 rounded-lg border-none bg-slate-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"><Printer class="h-4 w-4" /> In</button>
            </div>
          </div>
        </header>

        <div class="flex min-h-0 flex-1">
          <!-- Report parameters sidebar -->
          <aside class="w-[360px] shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-4">
            <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-[#000000D9]">Điều kiện báo cáo</h3>
            <HousekeepingInvoiceFilters
              v-if="isHousekeepingInvoiceReport(activeTab)"
              v-model="activeTab.parameters"
              :parameter-schema="activeTab.report.parameter_ui_schema || []"
              :system-date="systemDate"
              :shifts="parameterOptions(activeTab, 'p_shift')"
              :departments="parameterOptions(activeTab, 'p_department')"
              :users="parameterOptions(activeTab, 'p_user')"
              :sort-options="parameterOptions(activeTab, 'p_order_by')"
              :loading="activeTab.executing"
              :disabled="!activeTab.selectedTemplateId"
              @submit="submitHousekeepingInvoice(activeTab, $event)"
            />

            <template v-else>
              <div v-if="!(activeTab.report.parameter_ui_schema || []).filter(item => item.control !== 'hidden').length" class="mb-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-400">
                Báo cáo này không cần tham số.
              </div>

              <div v-for="parameter in (activeTab.report.parameter_ui_schema || []).filter(item => isVisibleReportParameter(activeTab, item))" :key="parameter.name" class="mb-3 block text-xs font-semibold text-[#000000D9]">
              <template v-if="parameter.control === 'checkbox'">
                <div class="flex h-8 items-center gap-2">
                  <button @click="activeTab.parameters[parameter.name] = !activeTab.parameters[parameter.name]" class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1" :class="activeTab.parameters[parameter.name] ? 'bg-sky-500' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform" :class="activeTab.parameters[parameter.name] ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                  </button>
                  <span>{{ parameter.label }} <span v-if="parameter.required" class="text-red-500">*</span></span>
                </div>
              </template>

              <template v-else>
                {{ parameter.label }} <span v-if="parameter.required" class="text-red-500">*</span>
              </template>

              <div v-if="parameter.control === 'radio'" class="mt-1 flex flex-wrap items-center gap-4 text-xs font-normal text-[#000000D9]">
                <label v-for="option in activeTab.parameterOptions[parameter.name] || parameter.options || []" :key="option.value ?? option" class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap">
                  <input v-model="activeTab.parameters[parameter.name]" type="radio" :name="`${activeTab.id}-${parameter.name}`" :value="option.value ?? option" class="h-4 w-4 border-sky-500 text-sky-600 focus:ring-sky-500" />
                  {{ option.label ?? option }}
                </label>
              </div>

              <details v-else-if="parameter.control === 'multi-select'" class="relative mt-1 font-normal">
                <summary class="flex min-h-9 cursor-pointer list-none items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-[#000000D9]" :class="parameter.required ? 'input-required' : ''">
                  <span class="truncate">{{ multiSelectLabel(activeTab, parameter) }}</span>
                  <span class="ml-2 text-slate-400">▾</span>
                </summary>
                <div class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                  <label v-for="option in multiSelectOptions(activeTab, parameter)" :key="option.value ?? option" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs text-[#000000D9] hover:bg-slate-50">
                    <input v-model="activeTab.parameters[parameter.name]" type="checkbox" :value="option.value ?? option" class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                    <span>{{ option.label ?? option }}</span>
                  </label>
                  <div v-if="!multiSelectOptions(activeTab, parameter).length" class="px-2 py-1 text-xs text-slate-400">Không có dịch vụ</div>
                </div>
              </details>

              <select v-else-if="parameter.control === 'select'" v-model="activeTab.parameters[parameter.name]" @change="handleReportParameterChange(activeTab, parameter)" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" :class="parameter.required ? 'input-required' : ''">
                <option v-if="!hasEmptySelectOption(activeTab, parameter)" value="">-- Chọn --</option>
                <option v-for="option in activeTab.parameterOptions[parameter.name] || parameter.options || []" :key="option.value ?? option" :value="option.value ?? option">
                  {{ option.label ?? option }}
                </option>
              </select>

              <ReportDateRangePicker
                v-else-if="parameter.control === 'date-range'"
                v-model:start-date="activeTab.parameters[parameter.name]"
                v-model:end-date="activeTab.parameters[parameter.range_end_parameter]"
                :system-date="systemDate"
                :required-highlight="parameter.required"
              />

              <SingleDatePicker
                v-else-if="parameter.control === 'date'"
                v-model="activeTab.parameters[parameter.name]"
                :start-date="activeTab.parameters[parameter.name] || systemDate"
                :input-class="parameter.required ? 'input-required' : ''"
                placeholder="dd/mm/yyyy"
                four-digit-year
              />
              <input v-else-if="parameter.control !== 'checkbox'" v-model="activeTab.parameters[parameter.name]" :type="parameter.control || 'text'" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-sky-400" :class="parameter.required ? 'input-required' : ''" />
              </div>

              <button :disabled="activeTab.executing || !activeTab.selectedTemplateId" @click="executeTab(activeTab)" class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border-none bg-sky-600 px-4 py-2.5 text-xs font-black text-white shadow-sm disabled:opacity-50">
                <LoaderCircle v-if="activeTab.executing" class="h-4 w-4 animate-spin" /><Play v-else class="h-4 w-4" />
                {{ activeTab.executing ? 'Đang tải dữ liệu...' : 'Hiển thị báo cáo' }}
              </button>
            </template>

            <div v-if="activeTab.dataset" class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-[10px] font-semibold text-emerald-700">
              {{ activeTab.dataset.summary?.row_count || 0 }} dòng · {{ activeTab.dataset.fields?.length || 0 }} cột
              <span v-if="activeTab.dataset.summary?.truncated" class="block text-amber-600">Dữ liệu đã bị giới hạn theo cấu hình Store.</span>
            </div>
          </aside>

          <!-- Report viewer -->
          <section class="relative flex min-w-0 flex-1 flex-col overflow-hidden bg-slate-200">
            <div v-if="activeTab.rendering" class="absolute inset-0 z-20 flex items-center justify-center bg-white/60">
              <LoaderCircle class="h-7 w-7 animate-spin text-sky-600" />
            </div>

            <div v-if="!activeTab.renderedHtml" class="flex h-full min-h-96 items-center justify-center p-6">
              <div class="text-center text-slate-400">
                <FileText class="mx-auto mb-3 h-12 w-12 opacity-30" />
                <p class="text-sm font-bold">Chọn điều kiện và bấm “Hiển thị báo cáo”</p>
                <p class="mt-1 text-xs">Store sẽ được chạy trên database của chi nhánh hiện tại.</p>
              </div>
            </div>

            <ReportViewer
              v-else
              ref="reportViewer"
              :html="activeTab.renderedHtml"
              :template="activeTemplate"
              @open-booking="openBookingFromReport"
              @open-report="openReportFromDrill"
            />
          </section>
        </div>
      </template>
      <div v-else class="flex flex-1 items-center justify-center text-sm text-slate-400">
        Chưa có báo cáo nào được mở. Vui lòng chọn báo cáo từ menu hệ thống.
      </div>
    </main>
  </div>
</template>

<style scoped>
.report-tabs {
  scrollbar-color: #94a3b8 transparent;
  scrollbar-width: thin;
}

.report-tabs::-webkit-scrollbar {
  height: 6px;
}

.report-tabs::-webkit-scrollbar-thumb {
  background: #94a3b8;
  border-radius: 9999px;
}

.report-tabs::-webkit-scrollbar-track {
  background: transparent;
}
</style>
