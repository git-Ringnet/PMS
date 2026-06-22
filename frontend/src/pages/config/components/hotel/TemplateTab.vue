<script setup>
import { ref, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const templates = ref([])

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

const updateTemplateReport = async (template) => {
  try {
    await http.put(`/templates/${template.id}`, {
      group: template.group,
      name: template.name,
      report: template.report
    })
    uiStore.showToast(`Cập nhật báo cáo thành công!`, 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi cập nhật báo cáo cho mẫu', 'error')
  }
}

onMounted(() => {
  fetchTemplates()
})
</script>

<template>
  <div class="flex gap-6 items-stretch relative">
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
      <span
        class="text-xs font-black text-slate-400 uppercase tracking-widest px-2 pb-2 block border-b border-slate-200">Nhóm
        Mẫu</span>
      <button v-for="grp in templateGroups" :key="grp" @click="activeTemplateGroup = grp"
        class="w-full text-left px-3 py-2 rounded-lg font-bold text-xs border-none bg-transparent cursor-pointer transition-colors"
        :class="activeTemplateGroup === grp ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'text-slate-600 hover:bg-slate-100'">
        {{ grp }}
      </button>
    </div>

    <!-- Right panel: Template report list -->
    <div class="flex-1 flex flex-col gap-4">
      <span class="text-xs font-black text-slate-700 uppercase tracking-wider pb-2 border-b border-slate-100">
        Mẫu báo cáo tương thích cho nhóm: {{ activeTemplateGroup }}
      </span>

      <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Tên mẫu cấu hình</th>
              <th class="p-3">Tên báo cáo liên kết (Report template name)</th>
              <th class="p-3 text-right">Lưu cấu hình</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates.filter(item => item.group === activeTemplateGroup)" :key="t.id"
              class="border-b border-slate-100 hover:bg-slate-50/55">
              <td class="p-3 font-bold text-slate-800">{{ t.name }}</td>
              <td class="p-2">
                <input type="text" v-model="t.report" placeholder="Nhập tên report..."
                  class="border border-slate-200 rounded-lg px-3 py-1.5 text-xs w-full max-w-sm focus:outline-sky-500 font-mono font-semibold" />
              </td>
              <td class="p-3 text-right">
                <button @click="updateTemplateReport(t)"
                  class="px-3 py-1.5 bg-sky-100 hover:bg-sky-200 border border-sky-300 hover:border-sky-400 text-sky-600 hover:text-sky-700 font-extrabold rounded-lg text-[11px] shadow-2xs cursor-pointer transition-colors uppercase">
                  Cập nhật
                </button>
              </td>
            </tr>
            <tr v-if="templates.filter(item => item.group === activeTemplateGroup).length === 0">
              <td colspan="3" class="p-6 text-center text-slate-400 italic">Chưa có mẫu nào thuộc nhóm này.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
