<script setup>
import { ref, reactive, onMounted } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const hotelServices = ref([])

const departments = [
  'Restaurant/Nhà Hàng',
  'Reception/ Lê Tân',
  'House Keeping/Buồng Phòng',
  'Spa'
]

const synthesisReports = ref([
  { id: 1, code: 'DT', name: 'Báo cáo tổng doanh thu', description: 'Báo cáo tổng doanh thu', is_hidden: false },
  { id: 2, code: 'CP', name: 'Báo cáo chi phí hoạt động', description: 'Báo cáo chi phí hoạt động chi tiết', is_hidden: false },
  { id: 3, code: 'LN', name: 'Báo cáo lợi nhuận gộp', description: 'Báo cáo lợi nhuận gộp tạm tính', is_hidden: true }
])
const selectedReportId = ref(1)

const isReportConfigModalOpen = ref(false)
const isEditReportConfigMode = ref(false)
const reportConfigFormState = reactive({
  id: null,
  code: '',
  name: '',
  description: '',
  is_hidden: false
})

const openAddReportConfigModal = () => {
  isEditReportConfigMode.value = false
  Object.assign(reportConfigFormState, {
    id: null,
    code: '',
    name: '',
    description: '',
    is_hidden: false
  })
  isReportConfigModalOpen.value = true
}

const openEditReportConfigModal = (report) => {
  isEditReportConfigMode.value = true
  Object.assign(reportConfigFormState, {
    id: report.id,
    code: report.code,
    name: report.name,
    description: report.description,
    is_hidden: !!report.is_hidden
  })
  isReportConfigModalOpen.value = true
}

const saveReportConfig = () => {
  if (!reportConfigFormState.code || !reportConfigFormState.name) {
    uiStore.showToast('Vui lòng nhập mã và tên báo cáo', 'warning')
    return
  }
  if (isEditReportConfigMode.value) {
    const idx = synthesisReports.value.findIndex(r => r.id === reportConfigFormState.id)
    if (idx !== -1) {
      synthesisReports.value[idx] = { ...synthesisReports.value[idx], ...reportConfigFormState }
    }
    uiStore.showToast('Cập nhật báo cáo thành công!', 'success')
  } else {
    const newId = Date.now()
    synthesisReports.value.push({
      id: newId,
      ...reportConfigFormState
    })
    selectedReportId.value = newId
    uiStore.showToast('Thêm báo cáo mới thành công!', 'success')
  }
  isReportConfigModalOpen.value = false
}

const deleteReportConfig = async (reportId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa báo cáo này cùng toàn bộ thiết lập?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const idx = synthesisReports.value.findIndex(r => r.id === reportId)
  if (idx !== -1) {
    synthesisReports.value.splice(idx, 1)
    synthesisReportLines.value = synthesisReportLines.value.filter(l => l.report_id !== reportId)
    if (selectedReportId.value === reportId) {
      selectedReportId.value = synthesisReports.value[0]?.id || null
    }
    uiStore.showToast('Xóa báo cáo thành công!', 'success')
  }
}

const synthesisReportLines = ref([
  {
    id: 1,
    report_id: 1,
    line_no: '01',
    line_code: 'REV_ROOM',
    line_desc: 'Báo cáo tổng doanh thu',
    level: 1,
    operator: '+',
    line_id_pm: '',
    departments: 'Reception/ Lê Tân',
    outlets: '',
    area_code: '',
    service_code: '',
    color: '#5cbeff',
    is_printed: true,
    is_bold: true
  }
])

const selectedReportLineId = ref(null)

const reportLineFormState = reactive({
  line_no: '',
  line_code: '',
  line_desc: '',
  level: 1,
  operator: '+',
  line_id_pm: '',
  departments: '',
  outlets: '',
  area_code: '',
  service_code: '',
  color: '#5cbeff',
  is_printed: true,
  is_bold: false
})

const selectReportLine = (line) => {
  selectedReportLineId.value = line.id
  Object.assign(reportLineFormState, {
    line_no: line.line_no,
    line_code: line.line_code,
    line_desc: line.line_desc,
    level: line.level,
    operator: line.operator,
    line_id_pm: line.line_id_pm,
    departments: line.departments,
    outlets: line.outlets,
    area_code: line.area_code,
    service_code: line.service_code,
    color: line.color,
    is_printed: !!line.is_printed,
    is_bold: !!line.is_bold
  })
}

const clearReportLineForm = () => {
  selectedReportLineId.value = null
  Object.assign(reportLineFormState, {
    line_no: '',
    line_code: '',
    line_desc: '',
    level: 1,
    operator: '+',
    line_id_pm: '',
    departments: '',
    outlets: '',
    area_code: '',
    service_code: '',
    color: '#5cbeff',
    is_printed: true,
    is_bold: false
  })
}

const addReportLine = () => {
  clearReportLineForm()
  const activeLines = synthesisReportLines.value.filter(l => l.report_id === selectedReportId.value)
  const nextNo = String(activeLines.length + 1).padStart(2, '0')
  reportLineFormState.line_no = nextNo
  uiStore.showToast('Vui lòng cấu hình dòng báo cáo mới ở bảng bên trái', 'info')
}

const saveReportLine = () => {
  if (!selectedReportId.value) {
    uiStore.showToast('Vui lòng chọn hoặc thêm một báo cáo trước', 'warning')
    return
  }
  if (!reportLineFormState.line_no || !reportLineFormState.line_desc) {
    uiStore.showToast('Vui lòng nhập mã dòng và diễn giải dòng', 'warning')
    return
  }

  if (selectedReportLineId.value) {
    const idx = synthesisReportLines.value.findIndex(l => l.id === selectedReportLineId.value)
    if (idx !== -1) {
      synthesisReportLines.value[idx] = {
        ...synthesisReportLines.value[idx],
        ...reportLineFormState
      }
      uiStore.showToast('Cập nhật dòng báo cáo thành công!', 'success')
    }
  } else {
    const newId = Date.now()
    synthesisReportLines.value.push({
      id: newId,
      report_id: selectedReportId.value,
      ...reportLineFormState
    })
    selectedReportLineId.value = newId
    uiStore.showToast('Thêm dòng báo cáo mới thành công!', 'success')
  }
}

const deleteReportLine = async () => {
  if (!selectedReportLineId.value) {
    uiStore.showToast('Vui lòng chọn một dòng báo cáo ở bảng dưới để xóa', 'warning')
    return
  }
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dòng báo cáo này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  const idx = synthesisReportLines.value.findIndex(l => l.id === selectedReportLineId.value)
  if (idx !== -1) {
    synthesisReportLines.value.splice(idx, 1)
    clearReportLineForm()
    uiStore.showToast('Xóa dòng báo cáo thành công!', 'success')
  }
}

const fetchHotelServices = async () => {
  try {
    const res = await http.get('/hotel-services')
    if (res.data && res.data.data) {
      hotelServices.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi khi tải danh sách dịch vụ:', err)
  }
}

onMounted(() => {
  fetchHotelServices()
})
</script>

<template>
  <div class="flex flex-col gap-6 relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[400px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Top bar layout: Select synthesis report -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-wrap gap-4">
      <div class="flex items-center gap-3">
        <span class="text-xs font-black text-slate-600 uppercase">Chọn báo cáo tổng hợp:</span>
        <select v-model="selectedReportId"
          class="border border-slate-200 rounded-lg px-3 py-1.5 bg-white text-sm font-bold text-slate-700 focus:outline-sky-500 cursor-pointer min-w-[200px]">
          <option v-for="rep in synthesisReports" :key="rep.id" :value="rep.id">{{ rep.code }} - {{ rep.name }}
          </option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <button @click="openAddReportConfigModal"
          class="px-3.5 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
          Thêm báo cáo mới
        </button>
        <button
          @click="selectedReportId && openEditReportConfigModal(synthesisReports.find(r => r.id === selectedReportId))"
          :disabled="!selectedReportId"
          class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
          Sửa báo cáo
        </button>
        <button @click="deleteReportConfig(selectedReportId)" :disabled="!selectedReportId"
          class="px-3.5 py-1.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white font-bold text-xs rounded-lg border-none cursor-pointer shadow-xs transition-colors">
          Xóa báo cáo
        </button>
      </div>
    </div>

    <!-- Inner Config content split: Left forms and Right lines table -->
    <div class="flex flex-col lg:flex-row gap-6 items-stretch">
      <!-- Left side layout: Configure Report Line Form -->
      <div
        class="w-full lg:w-1/3 bg-slate-50 border border-slate-200 rounded-xl p-5 shadow-inner flex flex-col gap-4">
        <span class="text-xs font-black text-slate-500 uppercase tracking-wide border-b border-slate-200 pb-2">
          {{ selectedReportLineId ? 'Cấu hình dòng báo cáo' : 'Thêm dòng báo cáo' }}
        </span>

        <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
          <div class="flex flex-col gap-1">
            <span>Số dòng*</span>
            <input type="text" v-model="reportLineFormState.line_no"
              class="border border-slate-200 bg-yellow-50 rounded px-2.5 py-1.5 focus:outline-sky-500 font-bold" />
          </div>
          <div class="flex flex-col gap-1">
            <span>Mã dòng*</span>
            <input type="text" v-model="reportLineFormState.line_code"
              class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-bold" />
          </div>
        </div>

        <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
          <span>Diễn giải dòng*</span>
          <input type="text" v-model="reportLineFormState.line_desc"
            class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-semibold" />
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
          <div class="flex flex-col gap-1">
            <span>Level</span>
            <select v-model="reportLineFormState.level"
              class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
              <option :value="1">1</option>
              <option :value="2">2</option>
              <option :value="3">3</option>
              <option :value="4">4</option>
            </select>
          </div>
          <div class="flex flex-col gap-1">
            <span>Toán tử</span>
            <select v-model="reportLineFormState.operator"
              class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
              <option value="+">+</option>
              <option value="-">-</option>
              <option value="*">*</option>
              <option value="/">/</option>
            </select>
          </div>
        </div>

        <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
          <span>Line ID PM</span>
          <input type="text" v-model="reportLineFormState.line_id_pm"
            class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500 font-mono" />
        </div>

        <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
          <span>Bộ phận liên kết</span>
          <select v-model="reportLineFormState.departments"
            class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
            <option value="">-- Bỏ trống --</option>
            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs font-bold text-slate-600">
          <div class="flex flex-col gap-1">
            <span>Outlets</span>
            <input type="text" v-model="reportLineFormState.outlets"
              class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500" />
          </div>
          <div class="flex flex-col gap-1">
            <span>Khu vực (Area)</span>
            <input type="text" v-model="reportLineFormState.area_code"
              class="border border-slate-200 rounded px-2.5 py-1.5 focus:outline-sky-500" />
          </div>
        </div>

        <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
          <span>Mã dịch vụ khách sạn</span>
          <select v-model="reportLineFormState.service_code"
            class="border border-slate-200 bg-white rounded px-2 py-1.5 focus:outline-sky-500 cursor-pointer">
            <option value="">-- Bỏ trống --</option>
            <option v-for="srv in hotelServices" :key="srv.id" :value="srv.code">{{ srv.code }} - {{ srv.name }}
            </option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-4 pt-1 text-xs font-bold text-slate-600 select-none">
          <div class="flex items-center justify-between">
            <span>Có in ra</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="reportLineFormState.is_printed" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>

          <div class="flex items-center justify-between">
            <span>Chữ in đậm</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="reportLineFormState.is_bold" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <div class="flex flex-col gap-1 text-xs font-bold text-slate-600">
          <span>Màu sắc hiển thị</span>
          <div class="flex gap-2">
            <input type="color" v-model="reportLineFormState.color"
              class="w-8 h-8 rounded cursor-pointer border-none bg-transparent" />
            <input type="text" v-model="reportLineFormState.color"
              class="border border-slate-200 rounded px-2 py-1.5 focus:outline-sky-500 flex-1 font-mono uppercase font-bold" />
          </div>
        </div>
      </div>

      <!-- Right side layout: Synthesis Report Lines Table -->
      <div class="flex-1 flex flex-col gap-4">
        <span class="text-xs font-black text-slate-700 uppercase tracking-wider">
          Danh sách dòng báo cáo thiết lập
        </span>

        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-2xs max-h-[500px]">
          <table class="w-full text-sm text-left border-collapse min-w-[900px]">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th class="p-3 w-12 text-center">#</th>
                <th class="p-3 w-16 text-center">Số dòng</th>
                <th class="p-3 w-24">Mã dòng</th>
                <th class="p-3 min-w-[150px]">Diễn giải</th>
                <th class="p-3 text-center">Level</th>
                <th class="p-3 text-center">Toán tử</th>
                <th class="p-3">Line ID PM</th>
                <th class="p-3">Bộ phận</th>
                <th class="p-3">Outlets</th>
                <th class="p-3">Area</th>
                <th class="p-3">Mã DV</th>
                <th class="p-3 text-right w-24">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in synthesisReportLines.filter(l => l.report_id === selectedReportId)" :key="line.id"
                @click="selectReportLine(line)" class="border-b border-slate-100 cursor-pointer transition-colors"
                :class="selectedReportLineId === line.id ? 'bg-sky-50 ring-1 ring-inset ring-sky-200' : 'hover:bg-slate-50/55'">
                <td class="p-3 text-slate-400 text-center font-mono">{{ line.id }}</td>
                <td class="p-3 text-center font-extrabold text-slate-700 font-mono">{{ line.line_no }}</td>
                <td class="p-3 font-bold text-slate-800" :style="{ color: line.color }">{{ line.line_code }}</td>
                <td class="p-3" :class="line.is_bold ? 'font-black text-slate-900' : 'text-slate-600 font-medium'">
                  {{ line.line_desc }}
                </td>
                <td class="p-3 text-center">
                  <span
                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-slate-100 font-black text-[10px] text-slate-500">{{
                      line.level }}</span>
                </td>
                <td class="p-3 text-center font-black text-slate-700 font-mono">{{ line.operator }}</td>
                <td class="p-3 text-slate-500 font-mono font-semibold">{{ line.line_id_pm || '-' }}</td>
                <td class="p-3 text-slate-500">{{ line.departments || '-' }}</td>
                <td class="p-3 text-slate-500">{{ line.outlets || '-' }}</td>
                <td class="p-3 text-slate-500 font-semibold">{{ line.area_code || '-' }}</td>
                <td class="p-3 font-bold text-sky-700 text-center">{{ line.service_code || '-' }}</td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-2 text-xs font-extrabold uppercase">
                    <span v-if="line.is_printed"
                      class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 text-[10px]">In</span>
                    <span v-if="line.is_bold"
                      class="px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-[10px]">Đậm</span>
                  </div>
                </td>
              </tr>
              <tr v-if="synthesisReportLines.filter(l => l.report_id === selectedReportId).length === 0"
                class="text-center">
                <td colspan="12" class="p-12 text-slate-400 italic">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <svg class="w-12 h-12 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-3.879a1.5 1.5 0 00-1.06.44l-2.122 2.12a1.5 1.5 0 01-1.06.44H9.782a1.5 1.5 0 01-1.06-.44L6.6 13.44a1.5 1.5 0 00-1.06-.44H2" />
                    </svg>
                    <span>No data</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Buttons under bottom table -->
        <div class="flex items-center gap-2 mt-2">
          <button @click="addReportLine"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center justify-center"
            title="Thêm dòng mới">
            +
          </button>
          <button @click="deleteReportLine"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center justify-center"
            title="Xóa dòng">
            -
          </button>
          <button
            @click="selectedReportLineId && selectReportLine(synthesisReportLines.find(l => l.id === selectedReportLineId))"
            :disabled="!selectedReportLineId"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] disabled:opacity-50 text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
            Sửa
          </button>
          <button @click="deleteReportLine" :disabled="!selectedReportLineId"
            class="px-4 py-2 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
            Xóa
          </button>
          <button @click="saveReportLine"
            class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold cursor-pointer transition-colors flex items-center gap-1 shadow-xs">
            Lưu
          </button>
        </div>
      </div>
    </div>

    <!-- OVERLAY MODAL: ADD / EDIT SYNTHESIS REPORT -->
    <div v-if="isReportConfigModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none">
      <div
        class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
          <h2 class="text-base font-black uppercase tracking-wider">{{ isEditReportConfigMode ? 'Sửa báo cáo' : 'Thêm báo cáo mới' }}</h2>
          <button @click="isReportConfigModalOpen = false"
            class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black">
            ✕
          </button>
        </div>

        <!-- Modal Body Form -->
        <div class="p-6 flex flex-col gap-4 text-sm font-bold text-slate-600">
          <div class="grid grid-cols-3 gap-4">
            <div class="flex flex-col gap-1.5 col-span-1">
              <span>Mã báo cáo*</span>
              <input type="text" v-model="reportConfigFormState.code" placeholder="DT"
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-bold" />
            </div>
            <div class="flex flex-col gap-1.5 col-span-2">
              <span>Tên báo cáo*</span>
              <input type="text" v-model="reportConfigFormState.name" placeholder="Báo cáo doanh thu..."
                class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm font-semibold" />
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Diễn giải</span>
            <textarea v-model="reportConfigFormState.description" rows="2" placeholder="Diễn giải thêm..."
              class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm resize-none font-semibold"></textarea>
          </div>
          <div class="flex items-center justify-between border border-slate-100 rounded-lg p-3 bg-slate-50 mt-2">
            <span>Ẩn báo cáo này</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="reportConfigFormState.is_hidden" class="sr-only peer">
              <div
                class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500">
              </div>
            </label>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isReportConfigModalOpen = false"
            class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors">
            Đóng
          </button>
          <button @click="saveReportConfig"
            class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors">
            Lưu báo cáo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
