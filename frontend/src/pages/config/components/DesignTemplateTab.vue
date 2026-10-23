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
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: `Bạn có chắc muốn xóa mẫu biểu "${template.name}"?`,
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  
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
  <div class="mb-4 shrink-0 rounded-xl border border-slate-200 bg-white p-2 font-roboto text-xs">
    <div class="grid grid-cols-3 gap-2">
      <button @click="activeWorkspace = 'library'" class="flex items-center gap-3 rounded-lg border px-4 py-2.5 text-left transition-colors cursor-pointer"
        :class="activeWorkspace === 'library' ? 'border-sky-300 bg-sky-50 text-sky-800' : 'border-transparent bg-white text-slate-600 hover:bg-slate-50'">
        <LayoutTemplate class="h-4 w-4" />
        <div>
          <b class="block text-xs font-semibold uppercase">1. Thư Viện Thiết Kế</b>
          <span class="mt-0.5 block text-[11px] font-normal text-slate-500">Tạo và sửa bố cục đầu ra</span>
        </div>
      </button>
      <button @click="activeWorkspace = 'print-slots'" class="flex items-center gap-3 rounded-lg border px-4 py-2.5 text-left transition-colors cursor-pointer"
        :class="activeWorkspace === 'print-slots' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-transparent bg-white text-slate-600 hover:bg-slate-50'">
        <Printer class="h-4 w-4" />
        <div>
          <b class="block text-xs font-semibold uppercase">2. Cấu Hình Mẫu In</b>
          <span class="mt-0.5 block text-[11px] font-normal text-slate-500">Chọn phiếu ăn sáng, hóa đơn...</span>
        </div>
      </button>
      <button @click="activeWorkspace = 'reports'" class="flex items-center gap-3 rounded-lg border px-4 py-2.5 text-left transition-colors cursor-pointer"
        :class="activeWorkspace === 'reports' ? 'border-violet-300 bg-violet-50 text-violet-800' : 'border-transparent bg-white text-slate-600 hover:bg-slate-50'">
        <ListFilter class="h-4 w-4" />
        <div>
          <b class="block text-xs font-semibold uppercase">3. Danh Mục Báo Cáo</b>
          <span class="mt-0.5 block text-[11px] font-normal text-slate-500">Store, tham số, menu và mẫu báo cáo</span>
        </div>
      </button>
    </div>
  </div>

  <div v-if="activeWorkspace === 'library'" class="flex gap-4 items-stretch relative h-full font-roboto text-xs">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Left panel: Group list -->
    <div class="w-1/4 bg-white rounded-xl p-3 border border-slate-200 flex flex-col gap-1 shrink-0 shadow-2xs">
      <span class="text-xs font-semibold text-[#000000D9] uppercase tracking-wide px-2 pb-2 block border-b border-slate-100">Nhóm Mẫu</span>
      <div class="overflow-y-auto flex-1 flex flex-col gap-0.5 mt-1">
        <button v-for="grp in templateGroups" :key="grp" @click="activeTemplateGroup = grp"
          class="w-full text-left px-3 py-2 rounded-lg text-xs border-none bg-transparent cursor-pointer transition-colors"
          :class="activeTemplateGroup === grp ? 'bg-sky-50 text-[#006bdb] font-semibold ring-1 ring-inset ring-sky-200' : 'text-[#000000D9] font-normal hover:bg-slate-50'">
          {{ grp }}
        </button>
      </div>
    </div>

    <!-- Right panel: Template report list -->
    <div class="flex-1 flex flex-col gap-3 min-h-0">
      <div class="flex justify-between items-center pb-2 border-b border-slate-200 shrink-0">
        <span class="text-xs font-semibold text-[#000000D9] uppercase tracking-wide">
          Thiết Kế Mẫu Cho Nhóm: {{ activeTemplateGroup }}
        </span>
        
        <!-- Data source and template actions -->
        <div class="flex items-center gap-2">
          <button @click="isDataSourceManagerOpen = true"
            class="btn-pms-secondary h-8 px-3 text-xs flex items-center gap-1.5 font-medium cursor-pointer shadow-xs">
            <Database class="w-3.5 h-3.5" />
            <span>Nguồn Dữ Liệu Store</span>
          </button>
          <button @click="showCreateForm = !showCreateForm"
            class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 font-medium cursor-pointer shadow-xs">
            <Plus class="w-3.5 h-3.5" />
            <span>{{ showCreateForm ? 'Đóng Form' : 'Tạo Mẫu Mới' }}</span>
          </button>
        </div>
      </div>

      <!-- Quick Create Form -->
      <div v-if="showCreateForm" class="p-3 border border-sky-200 rounded-xl bg-sky-50/40 flex gap-3 items-end shadow-2xs shrink-0">
        <div class="flex-1 flex flex-col gap-1">
          <label class="text-xs font-semibold text-[#000000D9]">Tên Biểu Mẫu Mới <span class="text-rose-500">*</span></label>
          <div class="relative">
            <input type="text" v-model="newTemplateName" placeholder="Ví dụ: Booking Confirmation Starlet..." 
              class="border bg-[#FFF8DB] border-[#F1DD8A] focus:border-[#eab308] rounded px-2.5 py-1.5 text-xs w-full font-normal text-[#000000D9] focus:outline-none transition-colors" />
            <button v-if="newTemplateName" @click="newTemplateName = ''" type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">✕</button>
          </div>
        </div>
        <button @click="createTemplate" 
          class="btn-pms-primary h-8 px-4 text-xs flex items-center gap-1.5 font-medium cursor-pointer shadow-xs">
          <Plus class="w-3.5 h-3.5" />
          <span>Tạo Mẫu</span>
        </button>
      </div>

      <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs flex-1">
        <table class="w-full text-xs text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-[#000000D9] font-semibold sticky top-0 z-10">
              <th class="p-2.5 text-center align-middle border border-slate-200 w-1/4">Tên Thiết Kế</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-20">Phiên Bản</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-1/4">Nguồn Dữ Liệu Thiết Kế</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-36">Sử Dụng Tại</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-32">Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates.filter(item => item.group === activeTemplateGroup)" :key="t.id"
              class="border-b border-slate-100 hover:bg-slate-50/70 text-[#000000D9]">
              <td class="p-2.5 border-r border-slate-100">
                <p class="font-normal text-[#000000D9]">{{ t.name }}</p>
                <p class="text-[10px] text-slate-500 font-mono mt-0.5" v-if="t.page_size">
                  Khổ: {{ t.page_size }} ({{ t.page_orientation === 'portrait' ? 'Dọc' : 'Ngang' }})
                </p>
              </td>
              
              <td class="p-2.5 border-r border-slate-100 text-center">
                <span class="px-2 py-0.5 rounded bg-slate-100 text-[#000000D9] text-[11px] font-normal font-mono">
                  v{{ t.version }}
                </span>
              </td>
              
              <td class="p-2.5 border-r border-slate-100">
                <span v-if="t.report_data_source" class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-normal text-emerald-800 border border-emerald-200">
                  {{ t.report_data_source.name }}
                </span>
                <span v-else class="text-[11px] italic text-slate-400">Không có Store — nhận dữ liệu từ chức năng in</span>
              </td>
              
              <td class="p-2.5 border-r border-slate-100 text-center">
                <span class="text-[11px] font-normal leading-relaxed text-[#000000D9]">Chọn tại<br><b class="font-semibold text-slate-800">Mẫu in / Báo cáo</b></span>
              </td>

              <!-- Action buttons: Edit, Duplicate, Delete -->
              <td class="p-2.5 text-center whitespace-nowrap">
                <div class="flex gap-1.5 justify-center items-center">
                  <button @click="openVisualEditor(t.id)" 
                    class="btn-pms-primary h-7 px-2 text-[11px] flex items-center gap-1 font-medium cursor-pointer shadow-3xs"
                    title="Sửa thiết kế">
                    <Edit3 class="w-3 h-3" />
                    <span>Sửa</span>
                  </button>
                  <button @click="duplicateTemplate(t.id)" 
                    class="p-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded text-slate-600 hover:text-slate-900 cursor-pointer transition-colors"
                    title="Sao chép nhân bản">
                    <Copy class="w-3.5 h-3.5" />
                  </button>
                  <button @click="deleteTemplate(t)" 
                    class="p-1.5 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-200 rounded text-rose-500 hover:text-rose-700 cursor-pointer transition-colors"
                    title="Xóa mẫu">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="templates.filter(item => item.group === activeTemplateGroup).length === 0">
              <td colspan="5" class="p-6 text-center text-gray-500 font-medium">Chưa có mẫu nào thuộc nhóm này. Hãy bấm tạo mẫu mới ở trên.</td>
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
