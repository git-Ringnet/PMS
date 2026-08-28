<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { Database, Download, FileText, LoaderCircle, Play, Printer } from '@lucide/vue'
import ReportDateRangePicker from '@/components/ReportDateRangePicker.vue'

const router = useRouter()
const route = useRoute()
const uiStore = useUiStore()
const loading = ref(false)
const systemDate = ref('')
const reports = ref([])
const activeTabId = ref(null)
const openTabs = ref([])

const activeTab = computed(() => openTabs.value.find(t => t.id === activeTabId.value) || null)

const localToday = () => {
  const date = new Date()
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 10)
}

const fetchSystemDate = async () => {
  try {
    const response = await http.get('/system-date')
    systemDate.value = response.data?.data?.system_date || localToday()
  } catch {
    systemDate.value = localToday()
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

const loadReports = async () => {
  loading.value = true
  try {
    const response = await http.get('/report-definitions', { params: { active_only: 1 } })
    reports.value = response.data.data || []

    // Check initial report code from URL
    const requestedCode = route.query.report
    if (requestedCode) {
      const report = reports.value.find(item => item.code === requestedCode)
      if (report) {
        openReportInTab(report)
        return
      }
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
  if (value === '$month_start') return localDate(new Date(baseDate.getFullYear(), baseDate.getMonth(), 1))
  if (value === '$month_end') return localDate(new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0))
  return value ?? ''
}

const openReportInTab = (report) => {
  let tab = openTabs.value.find(t => t.id === report.id)
  if (!tab) {
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
      // Existing report definitions may have an empty persisted default even
      // though the parameter is required. Keep execution usable by selecting
      // the first configured option (e.g. OOS sort = Room).
      defaultParams[definition.name] = resolved || (
        ['select', 'radio'].includes(definition.control) && firstOption !== undefined
          ? (firstOption.value ?? firstOption)
          : legacyDefault || resolved
      )
    }

    tab = reactive({
      id: report.id,
      code: report.code,
      name: report.name,
      report: report,
      parameters: defaultParams,
      parameterOptions: Object.fromEntries((report.parameter_ui_schema || []).map(p => [p.name, p.options || []])),
      selectedTemplateId: report.templates.find(item => item.is_default)?.id || report.templates[0]?.id || null,
      dataset: null,
      renderedHtml: '',
      executing: false,
      rendering: false
    })

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

const executeTab = async (tab) => {
  if (!tab || !tab.selectedTemplateId) return
  normalizeReportParameters(tab)
  const missing = (tab.report.parameter_ui_schema || [])
    .filter(item => item.required && (tab.parameters[item.name] === '' || tab.parameters[item.name] === undefined))
  if (missing.length) {
    uiStore.showToast(`Vui lòng nhập: ${missing.map(item => item.label).join(', ')}`, 'warning')
    return
  }
  tab.executing = true
  try {
    const response = await http.post(`/report-definitions/${tab.report.id}/execute`, {
      parameters: { ...tab.parameters }, template_id: tab.selectedTemplateId
    })
    tab.dataset = response.data.data
    tab.renderedHtml = response.data.html
    uiStore.showToast(`Đã tải ${tab.dataset.summary?.row_count || 0} dòng dữ liệu từ Store`, 'success')
  } catch (error) {
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
    tab.renderedHtml = response.data.html
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể đổi mẫu đầu ra', 'error')
  } finally {
    tab.rendering = false
  }
}

const printReportForTab = (tab) => {
  if (!tab || !tab.renderedHtml) return
  const iframe = document.querySelector('iframe')
  if (iframe) {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()
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

// Watch active tab template change
watch(() => activeTab.value?.selectedTemplateId, (val, oldVal) => {
  if (val && oldVal && val !== oldVal) {
    changeTemplateForTab(activeTab.value)
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

onMounted(async () => {
  await fetchSystemDate()
  await loadReports()
})
</script>

<template>
  <div class="flex h-full min-h-0 bg-slate-100 text-slate-800">
    <main class="flex min-w-0 flex-1 flex-col">
      <!-- Tabs Bar -->
      <div v-if="openTabs.length > 0" class="flex items-end bg-slate-50 border-b border-slate-200 px-4 h-11 shrink-0 gap-1 overflow-x-auto scrollbar-none">
        <div v-for="tab in openTabs" :key="tab.id"
          @click="selectTab(tab.id)"
          class="flex items-center gap-2 px-3 py-2 rounded-t-lg border-t border-x text-[11px] font-black transition-all cursor-pointer h-[36px] border-b-transparent -mb-[1px]"
          :class="activeTabId === tab.id
            ? 'bg-white border-slate-200 text-sky-600 shadow-xs relative z-10'
            : 'bg-slate-100/60 border-transparent text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
        >
          <span class="whitespace-nowrap uppercase tracking-wider">{{ tab.name }}</span>
          <button @click.stop="closeTab(tab.id)" class="p-0.5 rounded-full hover:bg-slate-200 text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer flex items-center justify-center">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Tab Content Workspace -->
      <template v-if="activeTab">
        <header class="border-b border-slate-200 bg-white px-5 py-3">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="text-sm font-black text-slate-800">{{ activeTab.name }}</h2>
              <p class="mt-0.5 flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                <Database class="h-3 w-3" /> {{ activeTab.report.report_data_source?.object_name }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-[10px] font-bold uppercase text-slate-500">Mẫu đầu ra
                <select v-model="activeTab.selectedTemplateId" class="ml-2 min-w-52 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">
                  <option v-for="template in activeTab.report.templates" :key="template.id" :value="template.id">
                    {{ template.name }}{{ template.is_default ? ' (Mặc định)' : '' }}
                  </option>
                </select>
              </label>
              <button :disabled="!activeTab.renderedHtml" @click="downloadCsvForTab(activeTab)" class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 disabled:opacity-40"><Download class="h-4 w-4" /> CSV</button>
              <button :disabled="!activeTab.renderedHtml" @click="printReportForTab(activeTab)" class="flex items-center gap-1 rounded-lg border-none bg-slate-800 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"><Printer class="h-4 w-4" /> In / PDF</button>
            </div>
          </div>
        </header>

        <div class="flex min-h-0 flex-1">
          <!-- Report parameters sidebar -->
          <aside class="w-[360px] shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-4">
            <h3 class="mb-4 text-[11px] font-black uppercase tracking-wider text-slate-600">Điều kiện báo cáo</h3>
            <div v-if="!(activeTab.report.parameter_ui_schema || []).filter(item => item.control !== 'hidden').length" class="mb-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-400">
              Báo cáo này không cần tham số.
            </div>

            <div v-for="parameter in (activeTab.report.parameter_ui_schema || []).filter(item => item.control !== 'hidden')" :key="parameter.name" class="mb-3 block text-[11px] font-bold text-slate-600">
              {{ parameter.label }} <span v-if="parameter.required" class="text-red-500">*</span>

              <select v-if="['select', 'radio'].includes(parameter.control)" v-model="activeTab.parameters[parameter.name]" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                <option value="">-- Chọn --</option>
                <option v-for="option in activeTab.parameterOptions[parameter.name] || parameter.options || []" :key="option.value ?? option" :value="option.value ?? option">
                  {{ option.label ?? option }}
                </option>
              </select>

              <ReportDateRangePicker
                v-else-if="parameter.control === 'date-range'"
                v-model:start-date="activeTab.parameters[parameter.name]"
                v-model:end-date="activeTab.parameters[parameter.range_end_parameter]"
                :system-date="systemDate"
              />

              <!-- Checkbox stylized as toggle switch -->
              <div v-else-if="parameter.control === 'checkbox'" class="flex items-center justify-between mt-1 h-8">
                <button @click="activeTab.parameters[parameter.name] = !activeTab.parameters[parameter.name]" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 shrink-0" :class="activeTab.parameters[parameter.name] ? 'bg-sky-500' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="activeTab.parameters[parameter.name] ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>

              <input v-else-if="parameter.control === 'date'" v-model="activeTab.parameters[parameter.name]" type="date" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-sky-400" />
              <input v-else v-model="activeTab.parameters[parameter.name]" :type="parameter.control || 'text'" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-sky-400" />
            </div>

            <button :disabled="activeTab.executing || !activeTab.selectedTemplateId" @click="executeTab(activeTab)" class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border-none bg-sky-600 px-4 py-2.5 text-xs font-black text-white shadow-sm disabled:opacity-50">
              <LoaderCircle v-if="activeTab.executing" class="h-4 w-4 animate-spin" /><Play v-else class="h-4 w-4" />
              {{ activeTab.executing ? 'Đang tải dữ liệu...' : 'Hiển thị báo cáo' }}
            </button>

            <div v-if="activeTab.dataset" class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-[10px] font-semibold text-emerald-700">
              {{ activeTab.dataset.summary?.row_count || 0 }} dòng · {{ activeTab.dataset.fields?.length || 0 }} cột
              <span v-if="activeTab.dataset.summary?.truncated" class="block text-amber-600">Dữ liệu đã bị giới hạn theo cấu hình Store.</span>
            </div>
          </aside>

          <!-- Report viewer -->
          <section class="relative flex-1 overflow-auto bg-slate-200 p-6">
            <div v-if="activeTab.rendering" class="absolute inset-0 z-10 flex items-center justify-center bg-white/60">
              <LoaderCircle class="h-7 w-7 animate-spin text-sky-600" />
            </div>
            
            <div v-if="!activeTab.renderedHtml" class="flex h-full min-h-96 items-center justify-center">
              <div class="text-center text-slate-400">
                <FileText class="mx-auto mb-3 h-12 w-12 opacity-30" />
                <p class="text-sm font-bold">Chọn điều kiện và bấm “Hiển thị báo cáo”</p>
                <p class="mt-1 text-xs">Store sẽ được chạy trên database của chi nhánh hiện tại.</p>
              </div>
            </div>

            <iframe v-else ref="reportFrame" :srcdoc="activeTab.renderedHtml" title="Nội dung báo cáo" class="mx-auto block min-h-[1120px] w-full max-w-[1120px] border-0 bg-white shadow-xl" />
          </section>
        </div>
      </template>
      <div v-else class="flex flex-1 items-center justify-center text-sm text-slate-400">
        Chưa có báo cáo nào được mở. Vui lòng chọn báo cáo từ menu hệ thống.
      </div>
    </main>
  </div>
</template>
