<script setup>
import { computed, reactive, ref, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { Database, Play, RefreshCw, Trash2, X } from '@lucide/vue'

const props = defineProps({
  isOpen: { type: Boolean, default: false }
})
const emit = defineEmits(['close', 'changed'])
const uiStore = useUiStore()
const loading = ref(false)
const testing = ref(false)
const procedures = ref([])
const sources = ref([])
const metadata = ref(null)
const sampleResult = ref(null)
const form = reactive({
  code: '',
  name: '',
  description: '',
  object_name: '',
  sample_parameters: {},
  max_rows: 1000,
  is_active: true
})

const selectedSource = computed(() => sources.value.find(source => source.object_name === form.object_name))

const loadData = async () => {
  loading.value = true
  try {
    const [catalogResponse, sourcesResponse] = await Promise.all([
      http.get('/report-procedures'),
      http.get('/report-data-sources')
    ])
    procedures.value = catalogResponse.data.data || []
    sources.value = sourcesResponse.data.data || []
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải danh sách Stored Procedure', 'error')
  } finally {
    loading.value = false
  }
}

const selectProcedure = async () => {
  metadata.value = null
  sampleResult.value = null
  if (!form.object_name) return
  try {
    const response = await http.get(`/report-procedures/${encodeURIComponent(form.object_name)}`)
    metadata.value = response.data.data
    const existing = sources.value.find(source => source.object_name === form.object_name)
    if (existing) {
      Object.assign(form, {
        code: existing.code,
        name: existing.name,
        description: existing.description || '',
        sample_parameters: { ...(existing.sample_parameters || {}) },
        max_rows: existing.max_rows || 1000,
        is_active: existing.is_active
      })
    } else {
      form.code = form.object_name.toUpperCase()
      form.name = form.object_name
      form.description = metadata.value.description || ''
      form.sample_parameters = {}
      form.max_rows = 1000
      form.is_active = true
    }
    for (const parameter of metadata.value.parameters || []) {
      if (!(parameter.name in form.sample_parameters)) form.sample_parameters[parameter.name] = ''
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể đọc metadata của Store', 'error')
  }
}

const inputType = (dataType) => {
  const type = String(dataType || '').toLowerCase()
  if (['date', 'datetime', 'timestamp'].includes(type)) return type === 'date' ? 'date' : 'datetime-local'
  if (['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double'].includes(type)) return 'number'
  return 'text'
}

const runSample = async () => {
  if (!form.object_name) return
  testing.value = true
  try {
    const response = await http.post('/report-procedure-samples', {
      procedure: form.object_name,
      parameters: form.sample_parameters,
      max_rows: Math.min(form.max_rows || 1000, 100)
    })
    sampleResult.value = response.data.data
    uiStore.showToast(`Store chạy thành công: ${sampleResult.value.summary.row_count} dòng`, 'success')
  } catch (error) {
    sampleResult.value = null
    uiStore.showToast(error.response?.data?.message || 'Không thể chạy thử Store', 'error')
  } finally {
    testing.value = false
  }
}

const saveSource = async () => {
  if (!form.object_name || !form.code || !form.name) {
    uiStore.showToast('Vui lòng chọn Store và nhập mã, tên nguồn dữ liệu', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      code: form.code.toUpperCase().replace(/[^A-Z0-9_]/g, '_'),
      name: form.name,
      description: form.description || null,
      object_name: form.object_name,
      sample_parameters: form.sample_parameters,
      max_rows: form.max_rows,
      is_active: form.is_active
    }
    let response
    let refreshResponse = null
    if (selectedSource.value) {
      response = await http.put(`/report-data-sources/${selectedSource.value.id}`, payload)
      refreshResponse = await http.post(`/report-data-sources/${selectedSource.value.id}/schema-refreshes`, {
        parameters: form.sample_parameters
      })
    } else {
      response = await http.post('/report-data-sources', payload)
      if (!(response.data.data?.field_schema || []).length) {
        await http.post(`/report-data-sources/${response.data.data.id}/schema-refreshes`, {
          parameters: form.sample_parameters
        })
      }
    }
    await loadData()
    emit('changed')
    const removedFields = refreshResponse?.data?.schema_changes?.removed_fields || []
    if (removedFields.length) {
      uiStore.showToast(`Store đã mất field: ${removedFields.join(', ')}. Hãy kiểm tra các template liên quan.`, 'warning')
    } else {
      uiStore.showToast('Đã lưu và đồng bộ cấu trúc nguồn dữ liệu', 'success')
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu nguồn dữ liệu', 'error')
  } finally {
    loading.value = false
  }
}

const deleteSource = async () => {
  if (!selectedSource.value) return
  const confirmed = await uiStore.confirm({ message: `Xóa nguồn dữ liệu "${selectedSource.value.name}"?` })
  if (!confirmed) return
  try {
    await http.delete(`/report-data-sources/${selectedSource.value.id}`)
    form.object_name = ''
    metadata.value = null
    sampleResult.value = null
    await loadData()
    emit('changed')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa nguồn dữ liệu', 'error')
  }
}

watch(() => props.isOpen, open => {
  if (open) loadData()
})
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="flex h-[88vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
      <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div class="flex items-center gap-2">
          <Database class="h-5 w-5 text-sky-600" />
          <div>
            <h3 class="text-sm font-black text-slate-800">Nguồn dữ liệu Stored Procedure</h3>
            <p class="text-[11px] font-semibold text-slate-400">Tự động nhận các MySQL Store có tiền tố rpt_ trong chi nhánh hiện tại.</p>
          </div>
        </div>
        <button class="rounded-lg border-none bg-transparent p-2 text-slate-400 hover:bg-slate-100" @click="emit('close')"><X class="h-5 w-5" /></button>
      </header>

      <div class="grid min-h-0 flex-1 grid-cols-[280px_1fr]">
        <aside class="overflow-y-auto border-r border-slate-200 bg-slate-50 p-4">
          <p class="mb-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Stored Procedure khả dụng</p>
          <button v-for="procedure in procedures" :key="procedure.object_name"
            class="mb-1.5 w-full rounded-lg border px-3 py-2 text-left text-xs font-bold transition-colors"
            :class="form.object_name === procedure.object_name ? 'border-sky-300 bg-sky-50 text-sky-700' : 'border-slate-200 bg-white text-slate-600 hover:border-sky-200'"
            @click="form.object_name = procedure.object_name; selectProcedure()">
            <span class="block font-mono">{{ procedure.object_name }}</span>
            <span v-if="sources.some(source => source.object_name === procedure.object_name)" class="mt-1 block text-[9px] font-black uppercase text-emerald-600">Đã đăng ký</span>
          </button>
          <p v-if="!loading && procedures.length === 0" class="rounded-lg border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400">Chưa có Store rpt_* trong database này.</p>
        </aside>

        <main class="overflow-y-auto p-5">
          <div v-if="!metadata" class="flex h-full items-center justify-center text-sm font-semibold text-slate-400">Chọn một Stored Procedure để cấu hình.</div>
          <div v-else class="space-y-5">
            <section class="grid grid-cols-2 gap-4 rounded-xl border border-slate-200 p-4">
              <label class="text-xs font-bold text-slate-600">Mã nguồn
                <input v-model="form.code" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs uppercase" />
              </label>
              <label class="text-xs font-bold text-slate-600">Tên hiển thị
                <input v-model="form.name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
              </label>
              <label class="col-span-2 text-xs font-bold text-slate-600">Mô tả
                <input v-model="form.description" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
              </label>
            </section>

            <section class="rounded-xl border border-slate-200 p-4">
              <div class="mb-3 flex items-center justify-between">
                <div><p class="text-xs font-black text-slate-700">Tham số chạy thử</p><p class="text-[10px] text-slate-400">Dùng để phát hiện các cột Store trả về.</p></div>
                <button :disabled="testing" class="flex items-center gap-1 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-700" @click="runSample">
                  <Play class="h-3.5 w-3.5" /> {{ testing ? 'Đang chạy...' : 'Chạy thử' }}
                </button>
              </div>
              <div v-if="metadata.parameters.length" class="grid grid-cols-2 gap-3">
                <label v-for="parameter in metadata.parameters" :key="parameter.name" class="text-[11px] font-bold text-slate-600">
                  {{ parameter.name }} <span class="font-mono text-[9px] text-slate-400">{{ parameter.database_type }}</span>
                  <input v-model="form.sample_parameters[parameter.name]" :type="inputType(parameter.data_type)" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
                </label>
              </div>
              <p v-else class="text-xs text-slate-400">Store không có tham số đầu vào.</p>
            </section>

            <section v-if="sampleResult" class="rounded-xl border border-emerald-200 bg-emerald-50/30 p-4">
              <p class="mb-2 text-xs font-black text-emerald-700">Kết quả: {{ sampleResult.summary.row_count }} dòng</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="field in sampleResult.fields" :key="field.name" class="rounded-md border border-emerald-200 bg-white px-2 py-1 font-mono text-[10px] text-emerald-700">{{ field.name }}</span>
              </div>
            </section>

            <footer class="flex justify-between border-t border-slate-100 pt-4">
              <button v-if="selectedSource" class="flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-600" @click="deleteSource"><Trash2 class="h-3.5 w-3.5" /> Xóa nguồn</button>
              <span v-else></span>
              <button :disabled="loading" class="flex items-center gap-1 rounded-lg border-none bg-sky-600 px-4 py-2 text-xs font-black text-white" @click="saveSource"><RefreshCw class="h-3.5 w-3.5" /> Lưu và đồng bộ schema</button>
            </footer>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>
