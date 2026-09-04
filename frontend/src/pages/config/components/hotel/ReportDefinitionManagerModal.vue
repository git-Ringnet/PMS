<script setup>
import { computed, reactive, ref, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { FilePlus2, Save, Trash2, X } from '@lucide/vue'

const props = defineProps({ isOpen: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'changed'])
const uiStore = useUiStore()
const loading = ref(false)
const reports = ref([])
const sources = ref([])
const templates = ref([])
const selectedId = ref(null)
const form = reactive({
  code: '', name: '', group: 'Report', description: '', report_data_source_id: '',
  parameter_ui_schema: [], template_ids: [], default_template_id: '', sort_order: 0, is_active: true,
  show_in_menu: true, menu_locations: ['reservation'], menu_top_order: 20,
  menu_group_order: 0, menu_item_order: 0
})

const selectedSource = computed(() => sources.value.find(item => item.id === Number(form.report_data_source_id)))
const selectedTemplates = computed(() => templates.value.filter(item => form.template_ids.includes(item.id)))

const blank = () => {
  selectedId.value = null
  Object.assign(form, {
    code: '', name: '', group: 'Report', description: '', report_data_source_id: '',
    parameter_ui_schema: [], template_ids: [], default_template_id: '', sort_order: reports.value.length, is_active: true,
    show_in_menu: true, menu_locations: ['reservation'], menu_top_order: 20,
    menu_group_order: 0, menu_item_order: reports.value.length
  })
}

const load = async () => {
  loading.value = true
  try {
    const [reportRes, sourceRes, templateRes] = await Promise.all([
      http.get('/report-definitions'), http.get('/report-data-sources'), http.get('/templates')
    ])
    reports.value = reportRes.data.data || []
    sources.value = (sourceRes.data.data || []).filter(item => item.is_active)
    templates.value = templateRes.data.data || []
    if (selectedId.value) {
      const fresh = reports.value.find(item => item.id === selectedId.value)
      if (fresh) edit(fresh)
    }
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải cấu hình báo cáo', 'error')
  } finally {
    loading.value = false
  }
}

const edit = (report) => {
  selectedId.value = report.id
  Object.assign(form, {
    code: report.code,
    name: report.name,
    group: report.group || 'Report',
    description: report.description || '',
    report_data_source_id: report.report_data_source_id,
    parameter_ui_schema: (report.parameter_ui_schema || []).map(item => ({ ...item })),
    template_ids: (report.templates || []).map(item => item.id),
    default_template_id: report.templates?.find(item => item.is_default)?.id || report.templates?.[0]?.id || '',
    sort_order: report.sort_order || 0,
    is_active: report.is_active,
    show_in_menu: report.show_in_menu ?? true,
    menu_locations: [...(report.menu_locations || ['reservation'])],
    menu_top_order: report.menu_top_order ?? 20,
    menu_group_order: report.menu_group_order ?? 0,
    menu_item_order: report.menu_item_order ?? 0
  })
}

const syncParameters = () => {
  const old = new Map(form.parameter_ui_schema.map(item => [item.name, item]))
  form.parameter_ui_schema = (selectedSource.value?.parameter_schema || []).map(parameter => {
    const saved = old.get(parameter.name) || {}
    return {
      name: parameter.name,
      label: saved.label || parameter.name,
      control: saved.control || inputControl(parameter.data_type),
      required: saved.required ?? true,
      default: saved.default ?? selectedSource.value?.sample_parameters?.[parameter.name] ?? '',
      options: saved.options || [],
      options_source: saved.options_source || ''
    }
  })
}

const inputControl = (dataType) => {
  const type = String(dataType || '').toLowerCase()
  if (type === 'date') return 'date'
  if (['datetime', 'timestamp'].includes(type)) return 'datetime-local'
  if (['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double'].includes(type)) return 'number'
  return 'text'
}

const optionText = (parameter) => (parameter.options || [])
  .map(option => typeof option === 'object' ? `${option.label}=${option.value}` : String(option))
  .join(', ')

const updateOptions = (parameter, value) => {
  parameter.options = String(value || '').split(/[,\n]/).map(item => item.trim()).filter(Boolean).map(item => {
    const [label, ...valueParts] = item.split('=')
    return { label: label.trim(), value: (valueParts.join('=') || label).trim() }
  })
}

const toggleTemplate = (id) => {
  const index = form.template_ids.indexOf(id)
  if (index >= 0) {
    form.template_ids.splice(index, 1)
    if (form.default_template_id === id) form.default_template_id = form.template_ids[0] || ''
  } else {
    form.template_ids.push(id)
    if (!form.default_template_id) form.default_template_id = id
  }
}

const toggleMenuLocation = (location) => {
  const index = form.menu_locations.indexOf(location)
  if (index >= 0) form.menu_locations.splice(index, 1)
  else form.menu_locations.push(location)
}

const save = async () => {
  if (!form.code || !form.name || !form.report_data_source_id || !form.template_ids.length) {
    uiStore.showToast('Cần nhập mã, tên, chọn Store và ít nhất một mẫu đầu ra', 'warning')
    return
  }
  if (form.show_in_menu && !form.menu_locations.length) {
    uiStore.showToast('Báo cáo đang hiện trên menu nên cần chọn ít nhất một khu vực', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      ...form,
      code: form.code.toUpperCase().replace(/[^A-Z0-9_]/g, '_'),
      report_data_source_id: Number(form.report_data_source_id),
      default_template_id: Number(form.default_template_id || form.template_ids[0]),
      description: form.description || null
    }
    const response = selectedId.value
      ? await http.put(`/report-definitions/${selectedId.value}`, payload)
      : await http.post('/report-definitions', payload)
    selectedId.value = response.data.data.id
    await load()
    emit('changed')
    uiStore.showToast('Đã lưu báo cáo và danh sách mẫu đầu ra', 'success')
  } catch (error) {
    const errors = error.response?.data?.errors
    uiStore.showToast(errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Không thể lưu báo cáo'), 'error')
  } finally {
    loading.value = false
  }
}

const remove = async () => {
  if (!selectedId.value) return
  const confirmed = await uiStore.confirm({ message: `Xóa báo cáo "${form.name}"? Các mẫu thiết kế vẫn được giữ lại.` })
  if (!confirmed) return
  try {
    await http.delete(`/report-definitions/${selectedId.value}`)
    blank()
    await load()
    emit('changed')
    uiStore.showToast('Đã xóa báo cáo', 'success')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa báo cáo', 'error')
  }
}

watch(() => props.isOpen, open => { if (open) { blank(); load() } })
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
    <div class="flex h-[90vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
          <h3 class="text-sm font-black text-slate-800">DANH MỤC BÁO CÁO</h3>
          <p class="mt-1 text-[11px] font-semibold text-slate-400">Gán Store, cấu hình bộ lọc và chọn các mẫu đầu ra cho từng báo cáo.</p>
        </div>
        <button class="rounded-lg border-none bg-slate-100 p-2 text-slate-500" @click="emit('close')"><X class="h-4 w-4" /></button>
      </header>

      <div class="flex min-h-0 flex-1">
        <aside class="w-72 shrink-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-3">
          <button class="mb-3 flex w-full items-center justify-center gap-2 rounded-lg border-none bg-sky-600 px-3 py-2 text-xs font-black text-white" @click="blank">
            <FilePlus2 class="h-4 w-4" /> TẠO BÁO CÁO MỚI
          </button>
          <button v-for="report in reports" :key="report.id" @click="edit(report)"
            class="mb-1 w-full rounded-lg border px-3 py-2 text-left"
            :class="selectedId === report.id ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-transparent bg-transparent text-slate-600 hover:bg-white'">
            <span class="block text-xs font-extrabold">{{ report.name }}</span>
            <span class="text-[10px] font-mono opacity-60">{{ report.code }} · {{ report.templates.length }} mẫu</span>
          </button>
        </aside>

        <main class="flex-1 overflow-y-auto p-5">
          <div class="grid grid-cols-2 gap-4">
            <label class="text-xs font-bold text-slate-600">Mã báo cáo
              <input v-model="form.code" placeholder="VD: GUEST_BIRTHDAY" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-xs uppercase" />
            </label>
            <label class="text-xs font-bold text-slate-600">Tên báo cáo
              <input v-model="form.name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
            </label>
            <label class="text-xs font-bold text-slate-600">Nhóm hiển thị
              <input v-model="form.group" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs" />
            </label>
            <label class="text-xs font-bold text-slate-600">Stored Procedure
              <select v-model="form.report_data_source_id" @change="syncParameters" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                <option value="">-- Chọn nguồn Store --</option>
                <option v-for="source in sources" :key="source.id" :value="source.id">{{ source.name }} ({{ source.object_name }})</option>
              </select>
            </label>
          </div>
          <label class="mt-4 block text-xs font-bold text-slate-600">Mô tả
            <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs"></textarea>
          </label>

          <div class="mt-4 grid grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-3 rounded-xl border border-sky-100 bg-sky-50/50 p-4 text-center">
            <div><b class="block text-xs text-sky-800">1. Form tham số</b><small class="text-[10px] text-sky-600">Sinh từ tham số Store bên dưới</small></div>
            <span class="text-sky-300">→</span>
            <div><b class="block text-xs text-sky-800">2. Chạy Store</b><small class="text-[10px] text-sky-600">Lấy dữ liệu theo giá trị người dùng chọn</small></div>
            <span class="text-sky-300">→</span>
            <div><b class="block text-xs text-sky-800">3. Hiện thiết kế</b><small class="text-[10px] text-sky-600">Render mẫu đã gán ở phần cuối</small></div>
          </div>

          <section class="mt-5 rounded-xl border border-violet-200 bg-violet-50/30">
            <div class="flex items-center justify-between border-b border-violet-100 px-4 py-3">
              <div>
                <h4 class="text-[11px] font-black uppercase text-violet-700">Vị trí trên menu hệ thống</h4>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-400">Thay đổi tại đây sẽ cập nhật menu Báo cáo bên ngoài sau khi tải lại trang.</p>
              </div>
              <label class="text-xs font-bold text-slate-600"><input v-model="form.show_in_menu" type="checkbox" /> Hiện trên menu</label>
            </div>
            <div :class="['p-4', !form.show_in_menu && 'pointer-events-none opacity-45']">
              <p class="mb-2 text-[10px] font-black uppercase text-slate-500">Hiển thị tại khu vực</p>
              <div class="mb-4 flex flex-wrap gap-2">
                <label v-for="location in [
                  { value: 'reservation', label: 'Đăng ký phòng' },
                  { value: 'frontdesk', label: 'Lễ tân' },
                  { value: 'housekeeping', label: 'Buồng phòng' }
                ]" :key="location.value" class="cursor-pointer rounded-lg border bg-white px-3 py-2 text-xs font-bold"
                  :class="form.menu_locations.includes(location.value) ? 'border-violet-300 text-violet-700' : 'border-slate-200 text-slate-500'">
                  <input class="mr-1.5" type="checkbox" :checked="form.menu_locations.includes(location.value)" @change="toggleMenuLocation(location.value)" />{{ location.label }}
                </label>
              </div>
              <div class="grid grid-cols-3 gap-3">
                <label class="text-[10px] font-bold uppercase text-slate-500">Vị trí nút Báo cáo
                  <input v-model.number="form.menu_top_order" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" />
                  <small class="mt-1 block normal-case text-slate-400">Số nhỏ nằm bên trái trước.</small>
                </label>
                <label class="text-[10px] font-bold uppercase text-slate-500">Thứ tự nhóm
                  <input v-model.number="form.menu_group_order" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" />
                  <small class="mt-1 block normal-case text-slate-400">Sắp xếp nhóm “{{ form.group }}”.</small>
                </label>
                <label class="text-[10px] font-bold uppercase text-slate-500">Thứ tự báo cáo
                  <input v-model.number="form.menu_item_order" type="number" min="0" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" />
                  <small class="mt-1 block normal-case text-slate-400">Sắp xếp trong cùng nhóm.</small>
                </label>
              </div>
            </div>
          </section>

          <section class="mt-5 rounded-xl border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-[11px] font-black uppercase text-slate-600">Bộ lọc đầu vào lấy từ tham số Store</div>
            <div v-if="!form.parameter_ui_schema.length" class="p-4 text-xs italic text-slate-400">Store này không có tham số hoặc chưa được chọn.</div>
            <div v-for="parameter in form.parameter_ui_schema" :key="parameter.name" class="grid grid-cols-[1fr_1.2fr_1fr_1fr_70px] items-center gap-2 border-b border-slate-100 p-3 last:border-0">
              <code class="text-[11px] text-sky-700">{{ parameter.name }}</code>
              <input v-model="parameter.label" placeholder="Nhãn hiển thị" class="rounded border border-slate-200 px-2 py-1.5 text-xs" />
              <select v-model="parameter.control" class="rounded border border-slate-200 bg-white px-2 py-1.5 text-xs">
                <option value="text">Chữ</option><option value="number">Số</option><option value="date">Ngày</option><option value="date-range">Phạm vi ngày</option><option value="multi-select">Nhiều lựa chọn</option>
                <option value="datetime-local">Ngày giờ</option><option value="select">Danh sách</option><option value="checkbox">Bật / tắt</option><option value="hidden">Ẩn</option>
              </select>
              <input v-model="parameter.default" placeholder="Mặc định" class="rounded border border-slate-200 px-2 py-1.5 text-xs" />
              <label class="text-[10px] text-slate-500"><input v-model="parameter.required" type="checkbox" /> Bắt buộc</label>
              <div v-if="parameter.control === 'select' || parameter.control === 'multi-select'" class="col-span-5 rounded-lg bg-slate-50 p-2">
                <label class="mb-2 block text-[10px] font-bold text-slate-500">Lấy lựa chọn từ danh mục PMS
                  <select v-model="parameter.options_source" class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs">
                    <option value="">Nhập danh sách tĩnh bên dưới</option>
                    <option value="areas">Khu vực phòng</option>
                    <option value="companies">Công ty / lữ hành</option>
                    <option value="bookings">Đăng ký phòng</option>
                    <option value="room-classes">Loại phòng</option>
                    <option value="registration-statuses">Tình trạng đăng ký</option>
                    <option value="hotel-services">Dịch vụ khách sạn</option>
                  </select>
                </label>
                <label class="text-[10px] font-bold text-slate-500">Các lựa chọn cho dropdown
                  <input :disabled="!!parameter.options_source" :value="optionText(parameter)" @change="updateOptions(parameter, $event.target.value)" placeholder="Ví dụ: Tất cả=ALL, Đảm bảo=GUARANTEED" class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs disabled:bg-slate-100" />
                </label>
              </div>
              <div v-else-if="parameter.control === 'date-range'" class="col-span-5 rounded-lg bg-slate-50 p-2">
                <label class="block text-[10px] font-bold text-slate-500">Tham số ngày kết thúc
                  <select v-model="parameter.range_end_parameter" class="mt-1 w-full rounded border border-slate-200 bg-white px-2 py-1.5 text-xs">
                    <option value="">-- Chọn tham số kết thúc --</option>
                    <option v-for="endParameter in form.parameter_ui_schema.filter(item => item.name !== parameter.name)" :key="endParameter.name" :value="endParameter.name">{{ endParameter.name }}</option>
                  </select>
                  <small class="mt-1 block normal-case font-medium text-slate-400">Tham số này nên đặt control “Ẩn” để Viewer chỉ hiển thị một bộ chọn phạm vi ngày.</small>
                </label>
              </div>
            </div>
          </section>

          <section class="mt-5 rounded-xl border border-slate-200">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-[11px] font-black uppercase text-slate-600">Thiết kế hiển thị bên phải của Viewer</div>
            <div class="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto p-3">
              <label v-for="template in templates" :key="template.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 p-2 hover:bg-slate-50">
                <input type="checkbox" :checked="form.template_ids.includes(template.id)" @change="toggleTemplate(template.id)" />
                <span class="min-w-0 flex-1"><b class="block truncate text-xs text-slate-700">{{ template.name }}</b><small class="text-[10px] text-slate-400">{{ template.group }} · v{{ template.version }}</small></span>
                <input v-if="form.template_ids.includes(template.id)" v-model="form.default_template_id" type="radio" :value="template.id" title="Mẫu mặc định" />
              </label>
            </div>
            <p v-if="selectedTemplates.length" class="border-t border-slate-100 px-4 py-2 text-[10px] text-slate-400">Đã chọn {{ selectedTemplates.length }} mẫu; nút tròn là mẫu mặc định.</p>
          </section>

          <div class="mt-5 flex items-center justify-between">
            <label class="text-xs font-bold text-slate-600"><input v-model="form.is_active" type="checkbox" /> Cho phép sử dụng báo cáo</label>
            <div class="flex gap-2">
              <button v-if="selectedId" @click="remove" class="flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-600"><Trash2 class="h-4 w-4" /> Xóa</button>
              <button :disabled="loading" @click="save" class="flex items-center gap-1 rounded-lg border-none bg-sky-600 px-4 py-2 text-xs font-black text-white disabled:opacity-50"><Save class="h-4 w-4" /> Lưu báo cáo</button>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>
