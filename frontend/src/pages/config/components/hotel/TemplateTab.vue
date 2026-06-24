<script setup>
import { ref, computed, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const templates = ref([])

// Template groups with Vietnamese labels
const templateGroups = [
  { group: 'Booking Confirmation', label: 'Xác nhận đặt phòng' },
  { group: 'Registration Card', label: 'Thẻ đăng ký' },
  { group: 'Deposit', label: 'Phiếu đặt cọc' },
  { group: 'Room Morning Worksheet', label: 'Bảng kê phòng buổi sáng' },
  { group: 'Invoice', label: 'Hóa đơn thanh toán' },
  { group: 'Total revenue report', label: 'Báo cáo doanh thu tổng hợp' },
  { group: 'Breakfast Ticket', label: 'Vé ăn sáng' },
  { group: 'Report', label: 'Báo cáo chung' }
]

// Active tab state
const activeGroup = ref(templateGroups[0].group)

// Filtered templates for the active tab
const filteredTemplates = computed(() => {
  return templates.value.filter(t => t.group === activeGroup.value)
})

// Count of templates per group (for badge)
const getGroupCount = (groupName) => {
  return templates.value.filter(t => t.group === groupName).length
}

// Find the default template ID for the active group
const defaultTemplateId = computed(() => {
  const def = filteredTemplates.value.find(t => t.is_default)
  return def ? def.id : ''
})

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

// Handle selecting a report as default
const onSelectDefault = async (templateId) => {
  if (!templateId) return

  loading.value = true
  try {
    const res = await http.post(`/templates/${templateId}/make-default`)
    if (res.data && res.data.success) {
      const activeLabel = templateGroups.find(g => g.group === activeGroup.value)?.label || activeGroup.value
      uiStore.showToast(`Đã gán mặc định cho nhóm "${activeLabel}" thành công!`, 'success')
      await fetchTemplates()
    } else {
      uiStore.showToast(res.data.message || 'Lỗi khi gán mẫu mặc định', 'error')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Lỗi khi gán mẫu mặc định', 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchTemplates()
})
</script>

<template>
  <div class="relative min-h-[400px]">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[300px] rounded-xl">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Header Section -->
    <div class="flex justify-between items-start pb-3 border-b border-slate-100 mb-5">
      <div>
        <span class="text-xs font-black text-slate-700 uppercase tracking-wider block">
          CẤU HÌNH MẪU BIỂU MẶC ĐỊNH
        </span>
        <span class="text-[11px] text-slate-400 font-bold mt-1 block">
          Chọn nhóm biểu mẫu, sau đó chọn báo cáo mặc định sẽ xuất khi in/xuất trong hệ thống PMS.
        </span>
      </div>
    </div>

    <!-- Group Tabs -->
    <div class="border-b border-slate-200 mb-0">
      <div class="flex flex-wrap gap-0">
        <button
          v-for="g in templateGroups"
          :key="g.group"
          @click="activeGroup = g.group"
          class="group relative px-4 py-2.5 text-xs font-bold border-none bg-transparent cursor-pointer transition-all duration-200 whitespace-nowrap"
          :class="activeGroup === g.group
            ? 'text-sky-700'
            : 'text-slate-400 hover:text-slate-600'"
        >
          <span class="flex items-center gap-1.5">
            {{ g.label }}
            <span
              v-if="getGroupCount(g.group) > 0"
              class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-black leading-none transition-colors duration-200"
              :class="activeGroup === g.group
                ? 'bg-sky-100 text-sky-700'
                : 'bg-slate-100 text-slate-400 group-hover:bg-slate-200 group-hover:text-slate-500'"
            >
              {{ getGroupCount(g.group) }}
            </span>
          </span>
          <!-- Active tab indicator -->
          <span
            v-if="activeGroup === g.group"
            class="absolute bottom-0 left-2 right-2 h-[2.5px] bg-sky-500 rounded-t-full"
          ></span>
        </button>
      </div>
    </div>

    <!-- Template List for Active Group -->
    <div class="border border-t-0 border-slate-200/80 rounded-b-xl bg-white shadow-2xs overflow-hidden">
      <!-- Group context header -->
      <div class="px-5 py-3 bg-slate-50/60 border-b border-slate-100 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <span class="px-2 py-0.5 rounded bg-slate-200/70 text-slate-500 text-[10px] font-black font-mono uppercase tracking-wider">
            {{ activeGroup }}
          </span>
          <span class="text-[11px] text-slate-400 font-bold">
            {{ filteredTemplates.length }} báo cáo
          </span>
        </div>
        <div v-if="defaultTemplateId" class="flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-[11px] font-bold text-emerald-600">Đã thiết lập mặc định</span>
        </div>
        <div v-else class="flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
          <span class="text-[11px] font-bold text-amber-500">Chưa thiết lập mặc định</span>
        </div>
      </div>

      <!-- Templates table -->
      <table class="w-full text-sm text-left border-collapse" v-if="filteredTemplates.length > 0">
        <thead>
          <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px] tracking-wider">
            <th class="p-3.5 pl-5 w-16">#</th>
            <th class="p-3.5 w-2/5">Tên báo cáo</th>
            <th class="p-3.5 w-1/5">Mã báo cáo (Report)</th>
            <th class="p-3.5 w-20 text-center">Phiên bản</th>
            <th class="p-3.5 w-1/4 text-center">Mặc định</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(t, idx) in filteredTemplates"
            :key="t.id"
            class="border-b border-slate-50 transition-colors duration-150"
            :class="t.is_default
              ? 'bg-emerald-50/40 hover:bg-emerald-50/60'
              : 'hover:bg-slate-50/60'"
          >
            <!-- Index -->
            <td class="p-3.5 pl-5">
              <span class="text-slate-300 text-xs font-bold font-mono">{{ idx + 1 }}</span>
            </td>

            <!-- Name -->
            <td class="p-3.5">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-800 text-[13px]">{{ t.name }}</span>
                <span v-if="t.is_default"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[9px] font-black uppercase tracking-wider">
                  ✓ Mặc định
                </span>
              </div>
            </td>

            <!-- Report code -->
            <td class="p-3.5">
              <span v-if="t.report" class="px-2 py-1 rounded bg-slate-100 text-slate-500 text-[11px] font-mono font-semibold">
                {{ t.report }}
              </span>
              <span v-else class="text-[11px] text-slate-300 italic">— Không có</span>
            </td>

            <!-- Version -->
            <td class="p-3.5 text-center">
              <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-bold font-mono">
                v{{ t.version }}
              </span>
            </td>

            <!-- Set default action -->
            <td class="p-3.5 text-center">
              <button
                v-if="!t.is_default"
                @click="onSelectDefault(t.id)"
                class="px-3 py-1.5 border border-slate-200 hover:border-sky-300 hover:bg-sky-50 text-slate-500 hover:text-sky-700 font-bold rounded-lg text-[11px] cursor-pointer transition-all duration-200 bg-white inline-flex items-center gap-1.5 shadow-3xs"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Chọn làm mặc định
              </button>
              <span v-else class="inline-flex items-center gap-1 text-emerald-600 text-[11px] font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Đang áp dụng
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty state -->
      <div v-else class="py-12 flex flex-col items-center justify-center gap-3">
        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
          <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <p class="text-xs text-slate-400 font-bold">Chưa có báo cáo nào thuộc nhóm này.</p>
        <p class="text-[11px] text-slate-300">Hãy vào trang <span class="font-bold text-slate-400">Thiết kế mẫu</span> để tạo báo cáo mới.</p>
      </div>
    </div>
  </div>
</template>
