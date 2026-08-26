<script setup>
import { computed, onMounted, ref } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { Eye, Printer, X } from '@lucide/vue'

const uiStore = useUiStore()
const loading = ref(false)
const slots = ref([])
const templates = ref([])
const activeGroup = ref('')
const previewHtml = ref('')
const previewTitle = ref('')

const groups = computed(() => [...new Set(slots.value.map(slot => slot.group))])
const filteredSlots = computed(() => slots.value.filter(slot => slot.group === activeGroup.value))
const templatesFor = (slot) => templates.value.filter(template => template.group === slot.group)

const load = async () => {
  loading.value = true
  try {
    const [slotResponse, templateResponse] = await Promise.all([
      http.get('/print-template-slots'),
      http.get('/templates')
    ])
    slots.value = slotResponse.data.data || []
    templates.value = templateResponse.data.data || []
    if (!activeGroup.value || !groups.value.includes(activeGroup.value)) activeGroup.value = groups.value[0] || ''
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải cấu hình mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

const assignTemplate = async (slot) => {
  try {
    const response = await http.put(`/print-template-slots/${slot.code}`, {
      template_id: slot.template_id || null
    })
    const index = slots.value.findIndex(item => item.id === slot.id)
    if (index >= 0) slots.value[index] = response.data.data
    uiStore.showToast(`Đã chọn thiết kế cho “${slot.name}”`, 'success')
  } catch (error) {
    await load()
    uiStore.showToast(error.response?.data?.errors?.template_id?.[0] || error.response?.data?.message || 'Không thể chọn thiết kế', 'error')
  }
}

const preview = async (slot) => {
  if (!slot.template_id) {
    uiStore.showToast('Vị trí mẫu in này chưa được chọn thiết kế', 'warning')
    return
  }
  loading.value = true
  try {
    const response = await http.post(`/print-template-slots/${slot.code}/render`, {})
    previewHtml.value = response.data.html
    previewTitle.value = `${slot.name} — ${response.data.template.name}`
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xem thử mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="relative flex h-full min-h-[440px] gap-5">
    <div v-if="loading" class="absolute inset-0 z-20 flex items-center justify-center rounded-xl bg-white/70">
      <div class="h-7 w-7 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600"></div>
    </div>

    <aside class="w-64 shrink-0 rounded-xl border border-slate-200 bg-slate-50 p-4">
      <h3 class="border-b border-slate-200 px-2 pb-3 text-[11px] font-black uppercase tracking-widest text-slate-400">Nhóm chức năng in</h3>
      <button v-for="group in groups" :key="group" @click="activeGroup = group"
        class="mt-1 w-full rounded-lg border-none px-3 py-2 text-left text-xs font-bold"
        :class="activeGroup === group ? 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100' : 'bg-transparent text-slate-600 hover:bg-white'">
        {{ group }}
      </button>
    </aside>

    <main class="min-w-0 flex-1">
      <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
        <div class="flex items-start gap-2">
          <Printer class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
          <div>
            <h3 class="text-xs font-black text-blue-800">CẤU HÌNH MẪU IN NGHIỆP VỤ</h3>
            <p class="mt-1 text-[11px] font-semibold leading-relaxed text-blue-600">Mỗi dòng là một chức năng in có sẵn trong PMS. Chọn thiết kế khách sạn muốn sử dụng; đây không phải báo cáo có bộ lọc.</p>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full border-collapse text-left">
          <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
            <tr><th class="p-3">Chức năng in</th><th class="p-3">Thiết kế đang sử dụng</th><th class="w-28 p-3 text-center">Kiểm tra</th></tr>
          </thead>
          <tbody>
            <tr v-for="slot in filteredSlots" :key="slot.id" class="border-b border-slate-100 last:border-0">
              <td class="p-3">
                <b class="block text-xs text-slate-800">{{ slot.name }}</b>
                <code class="mt-1 block text-[9px] text-slate-400">{{ slot.code }}</code>
              </td>
              <td class="p-3">
                <select v-model="slot.template_id" @change="assignTemplate(slot)" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">
                  <option :value="null">-- Chưa chọn thiết kế --</option>
                  <option v-for="template in templatesFor(slot)" :key="template.id" :value="template.id">{{ template.name }} · v{{ template.version }}</option>
                </select>
                <p v-if="!templatesFor(slot).length" class="mt-1 text-[10px] text-amber-600">Chưa có thiết kế trong nhóm này. Hãy tạo tại Thư viện thiết kế.</p>
              </td>
              <td class="p-3 text-center">
                <button :disabled="!slot.template_id" @click="preview(slot)" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-bold text-slate-600 disabled:opacity-40"><Eye class="h-3.5 w-3.5" /> Xem thử</button>
              </td>
            </tr>
            <tr v-if="!filteredSlots.length"><td colspan="3" class="p-8 text-center text-xs italic text-slate-400">Nhóm này chưa có vị trí mẫu in.</td></tr>
          </tbody>
        </table>
      </div>
    </main>

    <div v-if="previewHtml" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/60 p-5">
      <div class="flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3"><b class="text-sm text-slate-800">{{ previewTitle }}</b><button @click="previewHtml = ''" class="rounded-lg border-none bg-slate-100 p-2 text-slate-500"><X class="h-4 w-4" /></button></header>
        <div class="flex-1 overflow-auto bg-slate-200 p-5"><iframe :srcdoc="previewHtml" class="mx-auto block min-h-[1050px] w-full max-w-[900px] border-0 bg-white shadow-xl"></iframe></div>
      </div>
    </div>
  </div>
</template>
