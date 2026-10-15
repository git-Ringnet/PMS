<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import http from '@/services/http'
import { useAuthStore } from '@/stores/auth-store'

const authStore = useAuthStore()
const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

// Draggable modal position
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

let latestX = 0
let latestY = 0

function dragModal(moveEvent) {
  if (!isDraggingModal.value) return
  latestX = moveEvent.clientX
  latestY = moveEvent.clientY
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = latestX - dragStart.x
    modalPos.value.y = latestY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  e.preventDefault()
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  latestX = e.clientX
  latestY = e.clientY

  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && showConfirmModal.value) {
    showConfirmModal.value = false
  }
}

const isLoading = ref(false)
const isExporting = ref(false)
const isImporting = ref(false)
const selectedFile = ref(null)
const confirmRestore = ref(false)
const message = ref(null)
const errorMsg = ref(null)
const fileInputRef = ref(null)

// Multi-database / Multi-branch states
const branches = ref([])
const systemDb = ref({
  code: 'SYSTEM',
  name: 'Database Hệ Thống Chính (Users, Roles, Chi nhánh...)',
  database: 'pms_system',
  table_count: 0,
  size_mb: 0,
})
const summaryAll = ref({
  code: 'ALL',
  name: 'Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh',
  database: 'ALL (Multi-Database)',
  table_count: 0,
  size_mb: 0,
})
const selectedBranchCode = ref('HKT1')

// Modal xác nhận khôi phục
const showConfirmModal = ref(false)

const openRestoreConfirmModal = () => {
  modalPos.value = { x: 0, y: 0 }
  showConfirmModal.value = true
}

const selectedBranchObj = computed(() => {
  if (selectedBranchCode.value === 'ALL') {
    return {
      code: 'ALL',
      name: 'Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh',
      database: 'Toàn bộ Databases',
      table_count: summaryAll.value.table_count,
      size_mb: summaryAll.value.size_mb,
      is_all: true,
    }
  }
  if (selectedBranchCode.value === 'SYSTEM') {
    return {
      code: 'SYSTEM',
      name: 'Database Hệ Thống Chính (Users, Roles, Chi nhánh)',
      database: systemDb.value.database || 'pms_system',
      table_count: systemDb.value.table_count,
      size_mb: systemDb.value.size_mb,
      is_system: true,
    }
  }
  const found = branches.value.find(b => b.code.toUpperCase() === selectedBranchCode.value.toUpperCase())
  if (found) return found
  return {
    code: selectedBranchCode.value,
    name: `Chi nhánh ${selectedBranchCode.value}`,
    database: `pms_${selectedBranchCode.value.toLowerCase()}`,
    table_count: 0,
    size_mb: 0,
  }
})

const fetchDatabaseInfo = async () => {
  isLoading.value = true
  try {
    const res = await http.get('/system/database/info', {
      params: { branch_code: selectedBranchCode.value }
    })
    if (res.data && res.data.success && res.data.data) {
      branches.value = res.data.data.branches || []
      if (res.data.data.system_db) {
        systemDb.value = res.data.data.system_db
      }
      if (res.data.data.summary_all) {
        summaryAll.value = res.data.data.summary_all
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải thông tin database:', err)
  } finally {
    isLoading.value = false
  }
}

const onBranchChange = () => {
  message.value = null
  errorMsg.value = null
  selectedFile.value = null
  confirmRestore.value = false
  if (fileInputRef.value) fileInputRef.value.value = ''
  fetchDatabaseInfo()
}

const selectTarget = (code) => {
  selectedBranchCode.value = code
  onBranchChange()
}

const handleExport = async () => {
  isExporting.value = true
  message.value = null
  errorMsg.value = null

  try {
    const targetCode = selectedBranchCode.value
    const response = await http.get('/system/database/export', {
      params: { branch_code: targetCode },
      responseType: 'blob',
      timeout: 600000 // 10 minutes for full DB export
    })

    const blob = new Blob([response.data], { type: 'application/sql' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    
    // Lấy tên file từ header hoặc tạo tên chuẩn
    const contentDisposition = response.headers['content-disposition'] || response.headers['Content-Disposition']
    let filename = `pms_${targetCode}_backup_${new Date().toISOString().slice(0, 10)}.sql`
    if (contentDisposition) {
      const match = contentDisposition.match(/filename="?([^"]+)"?/)
      if (match && match[1]) {
        filename = match[1]
      }
    }
    
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    message.value = `Đã xuất bản sao lưu database cho [${selectedBranchObj.value.name}] (.sql) thành công!`
  } catch (err) {
    console.error('Export DB error:', err)
    errorMsg.value = 'Không thể xuất database: ' + (err.response?.data?.message || err.message)
  } finally {
    isExporting.value = false
  }
}

const handleFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (!file.name.endsWith('.sql')) {
      errorMsg.value = 'Vui lòng chọn file có định dạng .sql'
      selectedFile.value = null
      return
    }
    selectedFile.value = file
    errorMsg.value = null
  }
}

const handleDrop = (event) => {
  event.preventDefault()
  const file = event.dataTransfer.files[0]
  if (file) {
    if (!file.name.endsWith('.sql')) {
      errorMsg.value = 'Vui lòng chọn file có định dạng .sql'
      selectedFile.value = null
      return
    }
    selectedFile.value = file
    errorMsg.value = null
  }
}

const triggerFileInput = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click()
  }
}


const executeImport = async () => {
  showConfirmModal.value = false
  isImporting.value = true
  message.value = null
  errorMsg.value = null

  try {
    const targetCode = selectedBranchCode.value
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('branch_code', targetCode)

    const response = await http.post('/system/database/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-Branch-Code': targetCode,
      },
      timeout: 900000 // 15 minutes timeout for full DB import
    })

    if (response.data && response.data.success) {
      message.value = response.data.message || `Khôi phục database [${targetCode}] thành công!`
      selectedFile.value = null
      confirmRestore.value = false
      if (fileInputRef.value) fileInputRef.value.value = ''
      // Cập nhật lại thông tin bảng & dung lượng sau khi import
      await fetchDatabaseInfo()
    } else {
      errorMsg.value = response.data?.message || 'Khôi phục database thất bại.'
    }
  } catch (err) {
    console.error('Import DB error:', err)
    errorMsg.value = 'Lỗi khôi phục database: ' + (err.response?.data?.message || err.message)
  } finally {
    isImporting.value = false
  }
}

const openRestoreConfirm = () => {
  if (!selectedFile.value) {
    errorMsg.value = 'Vui lòng chọn file .sql để khôi phục!'
    return
  }
  if (!confirmRestore.value) {
    errorMsg.value = 'Vui lòng tích chọn xác nhận đồng ý ghi đè dữ liệu.'
    return
  }
  modalPos.value = { x: 0, y: 0 }
  showConfirmModal.value = true
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
  const savedBranchCode = localStorage.getItem('selected_branch_code') || 'HKT1'
  selectedBranchCode.value = savedBranchCode
  fetchDatabaseInfo()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  stopDragModal()
})
</script>

<template>
  <div class="flex-1 min-h-0 overflow-y-auto h-full p-2 font-roboto text-xs">
    <div class="flex flex-col gap-4 max-w-6xl mx-auto pb-8">
      <!-- Success Alert -->
    <div v-if="message" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-xs font-medium">{{ message }}</span>
      </div>
      <button @click="message = null" class="text-emerald-500 hover:text-emerald-800 bg-transparent border-none cursor-pointer">✕</button>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-2.5 rounded-xl flex items-start justify-between shadow-xs animate-fade-in">
      <div class="flex items-start gap-2.5 overflow-hidden">
        <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-xs font-medium max-h-48 overflow-y-auto break-all whitespace-pre-wrap">{{ errorMsg }}</div>
      </div>
      <button @click="errorMsg = null" class="text-rose-500 hover:text-rose-800 bg-transparent border-none cursor-pointer shrink-0 ml-2">✕</button>
    </div>

    <!-- BANNER: CHỌN PHẠM VI SAO LƯU & KHÔI PHỤC (Light theme, high contrast) -->
    <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs text-gray-900">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-start md:items-center gap-3.5">
          <div class="w-10 h-10 rounded-lg bg-sky-50 border border-sky-200 flex items-center justify-center text-sky-600 shrink-0 mt-0.5 md:mt-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold uppercase tracking-wide text-gray-900">Quản Trị Cơ Sở Dữ Liệu Đa Chi Nhánh</span>
              <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Multi-Database Active
              </span>
            </div>
            <h2 class="text-sm font-semibold text-gray-900 m-0 mt-1">
              Đang chọn: <span class="text-sky-700 font-bold">{{ selectedBranchObj.name }}</span>
            </h2>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-700 font-medium">
              <span class="inline-flex items-center gap-1">
                <span class="text-gray-500">Mã:</span>
                <strong class="text-gray-900 font-mono bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">{{ selectedBranchObj.code }}</strong>
              </span>
              <span class="text-slate-300">•</span>
              <span class="inline-flex items-center gap-1">
                <span class="text-gray-500">Database:</span>
                <code class="bg-slate-100 px-2 py-0.5 rounded text-sky-700 font-mono border border-slate-200">{{ selectedBranchObj.database }}</code>
              </span>
              <span class="text-slate-300">•</span>
              <span>Số bảng: <strong class="text-gray-900 font-semibold">{{ selectedBranchObj.table_count || 0 }} bảng</strong></span>
              <span class="text-slate-300">•</span>
              <span>Dung lượng: <strong class="text-gray-900 font-semibold">{{ selectedBranchObj.size_mb ? selectedBranchObj.size_mb.toFixed(2) : '0.00' }} MB</strong></span>
            </div>
          </div>
        </div>

        <!-- Scope Selector Dropdown -->
        <div class="flex items-center gap-2 bg-slate-50 p-2 rounded-lg border border-slate-200 shrink-0">
          <label class="text-xs font-semibold text-gray-900 whitespace-nowrap pl-1">Phạm Vi:</label>
          <select
            v-model="selectedBranchCode"
            @change="onBranchChange"
            class="bg-white text-gray-900 text-xs font-medium px-3 py-1.5 rounded border border-slate-200 focus:outline-sky-400 cursor-pointer max-w-[280px]"
          >
            <optgroup label="🌐 Toàn Bộ Hệ Thống">
              <option value="ALL">ALL - Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh</option>
            </optgroup>
            <optgroup label="⚙️ Quản Trị Hệ Thống">
              <option value="SYSTEM">SYSTEM - Database Hệ Thống Chính (pms_system)</option>
            </optgroup>
            <optgroup label="🏨 Từng Chi Nhánh Con">
              <option v-for="b in branches" :key="b.id" :value="b.code">
                {{ b.code }} - {{ b.name }} ({{ b.database }})
              </option>
            </optgroup>
          </select>
        </div>
      </div>
    </div>

    <!-- MAIN ACTIONS: EXPORT & IMPORT -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- SECTION 1: EXPORT DATABASE -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between gap-6 hover:border-sky-300 transition-all">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800 m-0">Sao lưu (Export Database)</h3>
              <p class="text-xs text-slate-500 m-0 mt-0.5">Xuất dữ liệu <strong>{{ selectedBranchObj.name }}</strong> ra file `.sql`</p>
            </div>
          </div>

          <div class="bg-sky-50/60 border border-sky-100 rounded-xl p-4 text-xs text-slate-700 space-y-2.5 mb-4">
            <div class="flex items-center justify-between pb-2 border-b border-sky-100 font-medium">
              <span class="text-slate-500">Phạm vi sao lưu:</span>
              <strong class="text-sky-900">{{ selectedBranchObj.name }}</strong>
            </div>
            <div class="flex items-center justify-between pb-2 border-b border-sky-100 font-medium">
              <span class="text-slate-500">Database đích:</span>
              <code class="bg-white px-2 py-0.5 rounded text-sky-700 border border-sky-200 font-mono">{{ selectedBranchObj.database }}</code>
            </div>
            <div class="flex items-center gap-2 pt-0.5 text-slate-600">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <span v-if="selectedBranchObj.is_all">Xuất toàn bộ Database hệ thống + TẤT CẢ các chi nhánh trong 1 file SQL</span>
              <span v-else-if="selectedBranchObj.is_system">Xuất các bảng hệ thống chung (users, roles, permissions, branches...)</span>
              <span v-else>Bao gồm cấu trúc bảng, khóa ngoại và toàn bộ dữ liệu nghiệp vụ của chi nhánh</span>
            </div>
            <div class="flex items-center gap-2 text-slate-600">
              <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
              <span>Tên file tự động: <code>pms_{{ selectedBranchObj.code }}_backup_*.sql</code></span>
            </div>
          </div>
        </div>

        <button
          @click="handleExport"
          :disabled="isExporting"
          class="btn-pms-primary w-full h-9 px-4 text-xs font-semibold flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
        >
          <svg v-if="isExporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>{{ isExporting ? 'Đang tạo bản sao lưu...' : `Tải File Sao Lưu (.sql) - [${selectedBranchObj.code}]` }}</span>
        </button>
      </div>

      <!-- SECTION 2: IMPORT DATABASE -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-5 flex flex-col justify-between gap-5 hover:border-amber-300 transition-all">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 shrink-0 border border-amber-200">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
            </div>
            <div>
              <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wide m-0">Khôi Phục (Import Database)</h3>
              <p class="text-xs text-gray-500 m-0 mt-0.5">Phục hồi dữ liệu vào <strong>{{ selectedBranchObj.name }}</strong></p>
            </div>
          </div>

          <!-- Target Scope Notice -->
          <div class="bg-amber-50/70 border border-amber-200 rounded-lg p-2.5 text-xs mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2 text-amber-900 font-semibold">
              <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Đích khôi phục: <strong>{{ selectedBranchObj.name }}</strong> (<code>{{ selectedBranchObj.database }}</code>)</span>
            </div>
          </div>

          <!-- Drag and Drop Area -->
          <div
            @dragover.prevent
            @drop="handleDrop"
            @click="triggerFileInput"
            class="border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition-colors mb-3"
            :class="selectedFile ? 'border-sky-400 bg-sky-50/50' : 'border-slate-300 hover:border-sky-400 bg-slate-50/50'"
          >
            <input
              ref="fileInputRef"
              type="file"
              accept=".sql"
              class="hidden"
              @change="handleFileSelect"
            />
            <div v-if="selectedFile" class="flex items-center justify-center gap-2 text-sky-700 font-semibold text-xs">
              <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>{{ selectedFile.name }} ({{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB)</span>
            </div>
            <div v-else class="flex flex-col items-center gap-1 text-slate-500 text-xs">
              <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <span>Nhấn để chọn file hoặc Kéo & Thả file <strong>.sql</strong> vào đây</span>
            </div>
          </div>

          <!-- Warning Checkbox -->
          <div class="bg-rose-50 border border-rose-200 rounded-lg p-2.5 text-xs text-rose-800 space-y-1">
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmRestore" class="mt-0.5 accent-rose-600" />
              <span class="font-medium text-rose-900">
                Tôi xác nhận hiểu rằng toàn bộ dữ liệu của <strong>[{{ selectedBranchObj.name }} - {{ selectedBranchObj.database }}]</strong> sẽ bị GHI ĐÈ hoàn toàn.
              </span>
            </label>
          </div>
        </div>

        <button
          @click="openRestoreConfirm"
          :disabled="isImporting || !selectedFile || !confirmRestore"
          class="btn-pms-danger w-full h-9 px-4 text-xs font-semibold flex items-center justify-center gap-2 cursor-pointer shadow-xs disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="isImporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>{{ isImporting ? 'Đang khôi phục database...' : `Khôi Phục Vào [${selectedBranchObj.code}]` }}</span>
        </button>
      </div>
    </div>

    <!-- OVERVIEW TABLE: TỔNG QUAN CÁC DATABASE TRONG HỆ THỐNG -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
      <div class="p-3 border-b border-slate-200 flex items-center justify-between bg-slate-50/70">
        <div>
          <h4 class="text-xs font-semibold text-gray-900 uppercase tracking-wide m-0">Tổng Quan Cơ Sở Dữ Liệu Hệ Thống & Các Chi Nhánh</h4>
          <p class="text-xs text-gray-500 m-0 mt-0.5">Danh sách các database quản trị và database chi nhánh trong hệ thống PMS</p>
        </div>
        <button
          @click="fetchDatabaseInfo"
          :disabled="isLoading"
          class="btn-pms-secondary h-7 px-2.5 text-[11px] font-medium flex items-center gap-1.5 cursor-pointer shadow-3xs"
        >
          <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>Làm Mới</span>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 text-gray-900 font-semibold border-b border-slate-200">
              <th class="p-2.5 text-center align-middle border border-slate-200 w-20">Mã</th>
              <th class="p-2.5 text-center align-middle border border-slate-200">Tên Cơ Sở Dữ Liệu / Chi Nhánh</th>
              <th class="p-2.5 text-center align-middle border border-slate-200">Tên Database</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-24">Số Bảng</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-28">Dung Lượng</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-28">Trạng Thái</th>
              <th class="p-2.5 text-center align-middle border border-slate-200 w-32">Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- ROW 1: TẤT CẢ (ALL) -->
            <tr
              class="hover:bg-slate-50 transition-colors bg-sky-50/20"
              :class="{ 'bg-sky-50 font-semibold': selectedBranchCode === 'ALL' }"
            >
              <td class="p-2.5 text-center font-mono font-semibold text-sky-800 border border-slate-200">
                <span class="flex items-center justify-center gap-1.5">
                  <span v-if="selectedBranchCode === 'ALL'" class="w-2 h-2 rounded-full bg-sky-600"></span>
                  <span>ALL</span>
                </span>
              </td>
              <td class="p-2.5 text-gray-900 font-semibold border border-slate-200">
                🌐 Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh
              </td>
              <td class="p-2.5 font-mono text-sky-700 border border-slate-200">ALL (Multi-DB)</td>
              <td class="p-2.5 text-center font-semibold text-gray-900 border border-slate-200">{{ summaryAll.table_count || 0 }}</td>
              <td class="p-2.5 text-right font-mono font-semibold text-gray-900 border border-slate-200">{{ summaryAll.size_mb ? summaryAll.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="p-2.5 text-center border border-slate-200">
                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                  Tổng hợp
                </span>
              </td>
              <td class="p-2.5 text-center border border-slate-200">
                <button
                  v-if="selectedBranchCode !== 'ALL'"
                  @click="selectTarget('ALL')"
                  class="btn-pms-secondary h-7 px-2.5 text-[11px] font-medium cursor-pointer"
                >
                  Chọn ALL
                </button>
                <span v-else class="text-[11px] font-semibold text-sky-700">Đang chọn</span>
              </td>
            </tr>

            <!-- ROW 2: SYSTEM DB -->
            <tr
              class="hover:bg-slate-50 transition-colors"
              :class="{ 'bg-sky-50 font-semibold': selectedBranchCode === 'SYSTEM' }"
            >
              <td class="p-2.5 text-center font-mono font-semibold text-gray-900 border border-slate-200">
                <span class="flex items-center justify-center gap-1.5">
                  <span v-if="selectedBranchCode === 'SYSTEM'" class="w-2 h-2 rounded-full bg-sky-600"></span>
                  <span>SYSTEM</span>
                </span>
              </td>
              <td class="p-2.5 text-gray-900 font-semibold border border-slate-200">
                ⚙️ Database Hệ Thống Chính (Users, Roles, Chi nhánh...)
              </td>
              <td class="p-2.5 font-mono text-sky-700 border border-slate-200">{{ systemDb.database || 'pms_system' }}</td>
              <td class="p-2.5 text-center font-semibold text-gray-900 border border-slate-200">{{ systemDb.table_count || 0 }}</td>
              <td class="p-2.5 text-right font-mono text-gray-900 border border-slate-200">{{ systemDb.size_mb ? systemDb.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="p-2.5 text-center border border-slate-200">
                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Hệ thống
                </span>
              </td>
              <td class="p-2.5 text-center border border-slate-200">
                <button
                  v-if="selectedBranchCode !== 'SYSTEM'"
                  @click="selectTarget('SYSTEM')"
                  class="btn-pms-secondary h-7 px-2.5 text-[11px] font-medium cursor-pointer"
                >
                  Chọn System
                </button>
                <span v-else class="text-[11px] font-semibold text-sky-700">Đang chọn</span>
              </td>
            </tr>

            <!-- ROWS FOR BRANCHES -->
            <tr
              v-for="b in branches"
              :key="b.id"
              class="hover:bg-slate-50 transition-colors"
              :class="{ 'bg-sky-50 font-semibold': b.code.toUpperCase() === selectedBranchCode.toUpperCase() }"
            >
              <td class="p-2.5 text-center font-mono font-semibold text-gray-900 border border-slate-200">
                <span class="flex items-center justify-center gap-1.5">
                  <span v-if="b.code.toUpperCase() === selectedBranchCode.toUpperCase()" class="w-2 h-2 rounded-full bg-sky-600"></span>
                  <span>{{ b.code }}</span>
                </span>
              </td>
              <td class="p-2.5 text-gray-900 border border-slate-200">🏨 {{ b.name }}</td>
              <td class="p-2.5 font-mono text-sky-700 border border-slate-200">{{ b.database }}</td>
              <td class="p-2.5 text-center font-semibold text-gray-900 border border-slate-200">{{ b.table_count || 0 }}</td>
              <td class="p-2.5 text-right font-mono text-gray-900 border border-slate-200">{{ b.size_mb ? b.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="p-2.5 text-center border border-slate-200">
                <span
                  v-if="b.status === 'ready'"
                  class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                >
                  Sẵn sàng
                </span>
                <span
                  v-else-if="b.status === 'empty'"
                  class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                >
                  Trống
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200"
                >
                  Mất kết nối
                </span>
              </td>
              <td class="p-2.5 text-center border border-slate-200">
                <button
                  v-if="b.code.toUpperCase() !== selectedBranchCode.toUpperCase()"
                  @click="selectTarget(b.code)"
                  class="btn-pms-secondary h-7 px-2.5 text-[11px] font-medium cursor-pointer"
                >
                  Chọn chi nhánh
                </button>
                <span v-else class="text-[11px] font-semibold text-sky-700">Đang chọn</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL XÁC NHẬN KHÔI PHỤC NGUY HIỂM -->
    <Teleport to="body">
      <div
        v-if="showConfirmModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/40" @click="showConfirmModal = false"></div>
        <div class="relative bg-white rounded-xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col"
          :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }">
          <div class="flex items-center justify-between px-5 py-2.5 bg-rose-600 text-white shrink-0 cursor-move select-none"
            @mousedown="startDragModal">
            <span class="text-xs font-semibold tracking-wide text-white">Xác Nhận Ghi Đè Database!</span>
            <button @click="showConfirmModal = false"
              class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-5 flex flex-col gap-3 font-roboto text-xs">
            <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 text-xs text-slate-800 space-y-2">
              <p class="m-0 font-medium">Bạn chuẩn bị khôi phục file <strong>{{ selectedFile?.name }}</strong> vào:</p>
              <div class="p-2 bg-white rounded border border-rose-200 font-mono text-xs">
                <div>Phạm vi: <strong class="text-rose-700">{{ selectedBranchObj.name }} ({{ selectedBranchObj.code }})</strong></div>
                <div>Database đích: <strong class="text-rose-700">{{ selectedBranchObj.database }}</strong></div>
              </div>
              <p class="m-0 text-rose-800 font-semibold">
                ⚠️ Toàn bộ dữ liệu hiện tại của phạm vi này sẽ bị XÓA và THAY THẾ bằng dữ liệu trong file sao lưu!
              </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
              <button
                @click="showConfirmModal = false"
                class="btn-pms-secondary h-8 px-3 text-xs font-medium cursor-pointer"
              >
                Hủy Bỏ
              </button>
              <button
                @click="executeImport"
                class="btn-pms-danger h-8 px-4 text-xs font-medium cursor-pointer shadow-xs"
              >
                Đồng Ý Ghi Đè & Khôi Phục
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
    </div>
  </div>
</template>
