<script setup>
import { computed, ref, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import TemplateEditorModal from './hotel/TemplateEditorModal.vue'
import ReportDataSourceManagerModal from './hotel/ReportDataSourceManagerModal.vue'
import ReportDefinitionManagerModal from './hotel/ReportDefinitionManagerModal.vue'
import TemplateTab from './hotel/TemplateTab.vue'
import { 
  Plus, Copy, Trash2, Edit3, Database, Files, LayoutTemplate, Printer, ListFilter
} from '@lucide/vue'

const uiStore = useUiStore()
const loading = ref(false)
const templates = ref([])
const activeWorkspace = ref('library')

// Active filtering group
const activeTemplateGroup = ref('Booking Confirmation')
const baseTemplateGroups = [
  'Booking Confirmation',
  'Registration Card',
  'Deposit',
  'Receipt',
  'Room Morning Worksheet',
  'Invoice',
  'Total revenue report',
  'Breakfast Ticket',
  'Report'
]
const templateGroups = computed(() => [
  ...new Set([
    ...baseTemplateGroups,
    ...templates.value.map(template => template.group).filter(Boolean)
  ])
])

// Modal editor state
const isEditorOpen = ref(false)
const selectedTemplateId = ref(null)
const isDataSourceManagerOpen = ref(false)
const isReportManagerOpen = ref(false)

// Creation form state
const showCreateForm = ref(false)
const newTemplateName = ref('')

const fetchTemplates = async () => {
  loading.value = true
  try {
    const res = await http.get('/templates')
    if (res.data && res.data.data) {
      templates.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách mẫu báo cáo:', err)
  } finally {
    loading.value = false
  }
}

// Create new template
const createTemplate = async () => {
  if (!newTemplateName.value.trim()) {
    uiStore.showToast('Vui lòng nhập tên mẫu biểu', 'warning')
    return
  }
  
  try {
    const res = await http.post('/templates', {
      group: activeTemplateGroup.value,
      name: newTemplateName.value.trim()
    })
    if (res.data && res.data.success) {
      uiStore.showToast('Tạo mới mẫu biểu thành công!', 'success')
      newTemplateName.value = ''
      showCreateForm.value = false
      fetchTemplates()
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi tạo mới mẫu biểu', 'error')
  }
}

// Duplicate template
const duplicateTemplate = async (id) => {
  try {
    const res = await http.post(`/templates/${id}/duplicate`)
    if (res.data && res.data.success) {
      uiStore.showToast('Sao chép mẫu biểu thành công!', 'success')
      fetchTemplates()
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi sao chép mẫu biểu', 'error')
  }
}

// Delete template
const deleteTemplate = async (template) => {
  if (!confirm(`Bạn có chắc muốn xóa mẫu biểu "${template.name}"?`)) {
    return
  }
  
  try {
    const res = await http.delete(`/templates/${template.id}`)
    if (res.data && res.data.success) {
      uiStore.showToast('Xóa mẫu biểu thành công!', 'success')
      fetchTemplates()
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi khi xóa mẫu biểu', 'error')
  }
}

// Open visual editor modal
const openVisualEditor = (id) => {
  selectedTemplateId.value = id
  isEditorOpen.value = true
}

onMounted(() => {
  fetchTemplates()
})
</script>

<template>
  <div class="mb-5 shrink-0 rounded-xl border border-slate-200 bg-white p-2">
    <div class="grid grid-cols-3 gap-2">
      <button @click="activeWorkspace = 'library'" class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left"
        :class="activeWorkspace === 'library' ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-transparent bg-white text-slate-500 hover:bg-slate-50'">
        <LayoutTemplate class="h-5 w-5" /><span><b class="block text-xs uppercase">1. Thư viện thiết kế</b><small class="mt-0.5 block text-[10px] font-semibold opacity-70">Tạo và sửa bố cục đầu ra</small></span>
      </button>
      <button @click="activeWorkspace = 'print-slots'" class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left"
        :class="activeWorkspace === 'print-slots' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-transparent bg-white text-slate-500 hover:bg-slate-50'">
        <Printer class="h-5 w-5" /><span><b class="block text-xs uppercase">2. Cấu hình mẫu in</b><small class="mt-0.5 block text-[10px] font-semibold opacity-70">Chọn phiếu ăn sáng, hóa đơn...</small></span>
      </button>
      <button @click="activeWorkspace = 'reports'" class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left"
        :class="activeWorkspace === 'reports' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-transparent bg-white text-slate-500 hover:bg-slate-50'">
        <ListFilter class="h-5 w-5" /><span><b class="block text-xs uppercase">3. Danh mục báo cáo</b><small class="mt-0.5 block text-[10px] font-semibold opacity-70">Store, tham số, menu và mẫu báo cáo</small></span>
      </button>
    </div>
  </div>

  <div v-if="activeWorkspace === 'library'" class="flex gap-6 items-stretch relative h-full">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Left panel: Group list -->
    <div class="w-1/4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex flex-col gap-1.5 shrink-0">
      <span class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Nhóm Mẫu</span>
      <button v-for="grp in templateGroups" :key="grp" @click="activeTemplateGroup = grp"
        class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
        :class="activeTemplateGroup === grp ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
        {{ grp }}
      </button>
    </div>

    <!-- Right panel: Template report list -->
    <div class="flex-1 flex flex-col gap-4">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100">
        <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
          Thiết kế mẫu cho nhóm: {{ activeTemplateGroup }}
        </span>
        
        <!-- Data source and template actions -->
        <div class="flex items-center gap-2">
          <button @click="isDataSourceManagerOpen = true"
            class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-extrabold rounded-lg flex items-center gap-1 cursor-pointer transition-colors border border-emerald-200 uppercase">
            <Database class="w-4 h-4" /> Nguồn dữ liệu Store
          </button>
          <button @click="showCreateForm = !showCreateForm"
            class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold rounded-lg flex items-center gap-1 cursor-pointer transition-colors shadow-2xs border-none uppercase">
            <Plus class="w-4 h-4" /> {{ showCreateForm ? 'ĐÓNG FORM' : 'TẠO MẪU MỚI' }}
          </button>
        </div>
      </div>

      <!-- Quick Create Form -->
      <div v-if="showCreateForm" class="p-4 border border-sky-100 rounded-xl bg-sky-50/30 flex gap-3 items-end shadow-2xs">
        <div class="flex-1 flex flex-col gap-1">
          <span class="text-[10px] font-bold text-sky-700 uppercase">Tên biểu mẫu mới:</span>
          <input type="text" v-model="newTemplateName" placeholder="Ví dụ: Booking Confirmation Starlet..." 
            class="border border-slate-200 bg-white rounded-lg px-3 py-1.5 text-xs w-full focus:outline-sky-500 font-bold" />
        </div>
        <button @click="createTemplate" 
          class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold rounded-lg cursor-pointer transition-colors border-none uppercase">
          TẠO MẪU
        </button>
      </div>

      <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3 w-1/4">Tên thiết kế</th>
              <th class="p-3 w-12 text-center">Version</th>
              <th class="p-3 w-1/4">Nguồn dữ liệu thiết kế</th>
              <th class="p-3 w-36 text-center">Sử dụng tại</th>
              <th class="p-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates.filter(item => item.group === activeTemplateGroup)" :key="t.id"
              class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3">
                <p class="font-bold text-slate-800">{{ t.name }}</p>
                <p class="text-[10px] text-slate-400 font-mono mt-0.5" v-if="t.page_size">
                  Khổ: {{ t.page_size }} ({{ t.page_orientation === 'portrait' ? 'Dọc' : 'Ngang' }})
                </p>
              </td>
              
              <td class="p-3 text-center">
                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-bold font-mono">
                  v{{ t.version }}
                </span>
              </td>
              
              <td class="p-3">
                <span v-if="t.report_data_source" class="rounded bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">{{ t.report_data_source.name }}</span>
                <span v-else class="text-[10px] italic text-slate-400">Không có Store — nhận dữ liệu từ chức năng in</span>
              </td>
              
              <td class="p-3 text-center">
                <span class="text-[10px] font-semibold leading-relaxed text-slate-400">Chọn tại<br><b class="text-slate-600">Mẫu in / Báo cáo</b></span>
              </td>

              <!-- Action buttons: Edit, Duplicate, Delete -->
              <td class="p-3 text-right">
                <div class="flex gap-1.5 justify-end">
                  <button @click="openVisualEditor(t.id)" 
                    class="px-2.5 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 font-extrabold rounded-lg text-[10px] cursor-pointer transition-colors uppercase inline-flex items-center gap-1.5 border-none shadow-3xs">
                    <Edit3 class="w-3.5 h-3.5" /> Sửa thiết kế
                  </button>
                  <button @click="duplicateTemplate(t.id)" 
                    class="p-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg text-slate-500 cursor-pointer" title="Sao chép nhân bản">
                    <Copy class="w-3.5 h-3.5" />
                  </button>
                  <button @click="deleteTemplate(t)" 
                    class="p-1.5 bg-slate-50 hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg text-slate-400 hover:text-red-600 cursor-pointer"
                    title="Xóa mẫu">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="templates.filter(item => item.group === activeTemplateGroup).length === 0">
              <td colspan="5" class="p-6 text-center text-slate-400 italic">Chưa có mẫu nào thuộc nhóm này. Hãy bấm tạo mẫu mới ở trên.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Visual Editor Modal Overlay -->
    <TemplateEditorModal
      v-if="isEditorOpen && selectedTemplateId"
      :template-id="selectedTemplateId" 
      :is-open="isEditorOpen" 
      @close="isEditorOpen = false" 
      @saved="fetchTemplates" />
    <ReportDataSourceManagerModal
      :is-open="isDataSourceManagerOpen"
      @close="isDataSourceManagerOpen = false"
      @changed="fetchTemplates" />
  </div>

  <TemplateTab v-else-if="activeWorkspace === 'print-slots'" />

  <div v-else class="flex min-h-[440px] items-center justify-center rounded-xl border border-violet-100 bg-violet-50/30 p-8">
    <div class="max-w-2xl text-center">
      <Files class="mx-auto h-12 w-12 text-violet-400" />
      <h2 class="mt-4 text-lg font-black text-slate-800">DANH MỤC BÁO CÁO CÓ THAM SỐ</h2>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">Tại đây cấu hình báo cáo xuất hiện trên menu, Store cần chạy, các ô lọc bên trái và thiết kế dùng để hiển thị kết quả. Đây là báo cáo dữ liệu, không phải phiếu in nghiệp vụ.</p>
      <button @click="isReportManagerOpen = true" class="mt-5 inline-flex items-center gap-2 rounded-lg border-none bg-violet-600 px-5 py-2.5 text-xs font-black text-white"><ListFilter class="h-4 w-4" /> Mở danh mục báo cáo</button>
    </div>
  </div>

  <ReportDefinitionManagerModal
    :is-open="isReportManagerOpen"
    @close="isReportManagerOpen = false"
    @changed="fetchTemplates" />
</template>
