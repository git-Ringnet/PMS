<script setup>
import { ref, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import TemplateEditorModal from './hotel/TemplateEditorModal.vue'
import { 
  Plus, Copy, Trash2, Edit3, CheckCircle, Star 
} from '@lucide/vue'

const uiStore = useUiStore()
const loading = ref(false)
const templates = ref([])

// Active filtering group
const activeTemplateGroup = ref('Booking Confirmation')
const templateGroups = [
  'Booking Confirmation',
  'Registration Card',
  'Deposit',
  'Room Morning Worksheet',
  'Invoice',
  'Total revenue report',
  'Breakfast Ticket',
  'Report'
]

// Modal editor state
const isEditorOpen = ref(false)
const selectedTemplateId = ref(null)

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

// Quick update of report name
const updateTemplateReport = async (template) => {
  try {
    await http.put(`/templates/${template.id}`, {
      group: template.group,
      name: template.name,
      report: template.report,
      page_size: template.page_size || 'A4',
      page_orientation: template.page_orientation || 'portrait',
      margin_top: template.margin_top ?? 10,
      margin_bottom: template.margin_bottom ?? 10,
      margin_left: template.margin_left ?? 10,
      margin_right: template.margin_right ?? 10,
      content_json: template.content_json,
      content_html: template.content_html,
      css: template.css,
      note: 'Cập nhật tên báo cáo liên kết nhanh'
    })
    uiStore.showToast(`Cập nhật báo cáo liên kết thành công!`, 'success')
    fetchTemplates()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật báo cáo cho mẫu', 'error')
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

// Make default
const makeDefault = async (id) => {
  try {
    const res = await http.post(`/templates/${id}/make-default`)
    if (res.data && res.data.success) {
      uiStore.showToast('Đã kích hoạt mẫu mặc định cho nhóm!', 'success')
      fetchTemplates()
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi kích hoạt mẫu mặc định', 'error')
  }
}

// Delete template
const deleteTemplate = async (template) => {
  if (template.is_default) {
    uiStore.showToast('Không thể xóa mẫu đang là mặc định!', 'warning')
    return
  }
  
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
    uiStore.showToast('Lỗi khi xóa mẫu biểu', 'error')
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
  <div class="flex gap-6 items-stretch relative h-full">
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
        
        <!-- Create new button -->
        <button @click="showCreateForm = !showCreateForm" 
          class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold rounded-lg flex items-center gap-1 cursor-pointer transition-colors shadow-2xs border-none uppercase">
          <Plus class="w-4 h-4" /> {{ showCreateForm ? 'ĐÓNG FORM' : 'TẠO MẪU MỚI' }}
        </button>
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
              <th class="p-3 w-1/4">Tên cấu hình</th>
              <th class="p-3 w-12 text-center">Version</th>
              <th class="p-3 w-1/4">Tên mã báo cáo (Mã code)</th>
              <th class="p-3 w-28 text-center">Trạng thái</th>
              <th class="p-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates.filter(item => item.group === activeTemplateGroup)" :key="t.id"
              class="border-b border-slate-100 hover:bg-slate-50/55"
              :class="t.is_default ? 'bg-sky-50/15' : ''">
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
              
              <td class="p-2">
                <div class="flex gap-1.5 items-center">
                  <input type="text" v-model="t.report" placeholder="Ví dụ: InvoiceGalliot..."
                    class="border border-slate-200 rounded-lg px-2.5 py-1 text-xs w-full max-w-[180px] focus:outline-sky-500 font-mono font-semibold" />
                  <button @click="updateTemplateReport(t)"
                    class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-lg border-none cursor-pointer" title="Lưu nhanh">
                    ✓
                  </button>
                </div>
              </td>
              
              <td class="p-3 text-center">
                <span v-if="t.is_default" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider shadow-3xs">
                  <CheckCircle class="w-3.5 h-3.5" /> Mặc định
                </span>
                <button v-else @click="makeDefault(t.id)" 
                  class="px-2.5 py-1 border border-slate-200 hover:border-amber-300 hover:bg-amber-50 text-slate-500 hover:text-amber-700 font-extrabold rounded-lg text-[10px] cursor-pointer transition-colors uppercase inline-flex items-center gap-1 shadow-3xs bg-white">
                  <Star class="w-3.5 h-3.5" /> Kích hoạt
                </button>
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
                    :disabled="t.is_default"
                    class="p-1.5 bg-slate-50 hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-lg text-slate-400 hover:text-red-600 cursor-pointer disabled:opacity-40" 
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
      :template-id="selectedTemplateId" 
      :is-open="isEditorOpen" 
      @close="isEditorOpen = false" 
      @saved="fetchTemplates" />
  </div>
</template>
