<script setup>
import { ref, onMounted, computed } from 'vue'
import http from '@/services/http'

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

const openRestoreConfirm = () => {
  if (!selectedFile.value) {
    errorMsg.value = 'Vui lòng chọn file .sql để khôi phục!'
    return
  }
  if (!confirmRestore.value) {
    errorMsg.value = 'Vui lòng tích chọn xác nhận đồng ý ghi đè dữ liệu.'
    return
  }
  showConfirmModal.value = true
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

onMounted(() => {
  const savedBranchCode = localStorage.getItem('selected_branch_code') || 'HKT1'
  selectedBranchCode.value = savedBranchCode
  fetchDatabaseInfo()
})
</script>

<template>
  <div class="flex flex-col gap-6 max-w-6xl mx-auto p-2">
    <!-- Success Alert -->
    <div v-if="message" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-sm font-semibold">{{ message }}</span>
      </div>
      <button @click="message = null" class="text-emerald-500 hover:text-emerald-800 bg-transparent border-none cursor-pointer">✕</button>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-semibold">{{ errorMsg }}</span>
      </div>
      <button @click="errorMsg = null" class="text-rose-500 hover:text-rose-800 bg-transparent border-none cursor-pointer">✕</button>
    </div>

    <!-- BANNER: CHỌN PHẠM VI SAO LƯU & KHÔI PHỤC -->
    <div class="bg-slate-900 rounded-2xl p-5 text-white shadow-md border border-slate-700/80">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-start md:items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-400 shrink-0 mt-0.5 md:mt-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
            </svg>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-300">Quản trị Cơ sở Dữ liệu Đa Chi nhánh</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Multi-Database Active
              </span>
            </div>
            <h2 class="text-base md:text-lg font-bold text-white m-0 mt-1">
              Đang chọn: <span class="text-amber-300">{{ selectedBranchObj.name }}</span>
            </h2>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-slate-300">
              <span class="inline-flex items-center gap-1">
                <span class="text-slate-400">Mã:</span>
                <strong class="text-white font-mono bg-slate-800 px-1.5 py-0.5 rounded border border-slate-700">{{ selectedBranchObj.code }}</strong>
              </span>
              <span class="text-slate-500">•</span>
              <span class="inline-flex items-center gap-1">
                <span class="text-slate-400">Database:</span>
                <code class="bg-black/40 px-2 py-0.5 rounded text-sky-300 font-mono border border-slate-700/50">{{ selectedBranchObj.database }}</code>
              </span>
              <span class="text-slate-500">•</span>
              <span>Số bảng: <strong class="text-white font-semibold">{{ selectedBranchObj.table_count || 0 }} bảng</strong></span>
              <span class="text-slate-500">•</span>
              <span>Dung lượng: <strong class="text-white font-semibold">{{ selectedBranchObj.size_mb ? selectedBranchObj.size_mb.toFixed(2) : '0.00' }} MB</strong></span>
            </div>
          </div>
        </div>

        <!-- Scope Selector Dropdown -->
        <div class="flex items-center gap-2 bg-slate-800 p-2 rounded-xl border border-slate-700 shrink-0">
          <label class="text-xs font-semibold text-slate-300 whitespace-nowrap pl-1">Phạm vi:</label>
          <select
            v-model="selectedBranchCode"
            @change="onBranchChange"
            class="bg-slate-900 text-white text-xs font-semibold px-3 py-2 rounded-lg border border-indigo-500/50 focus:border-indigo-400 focus:outline-none cursor-pointer max-w-[280px]"
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
          class="w-full py-3.5 px-4 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-bold rounded-xl border-none cursor-pointer flex items-center justify-center gap-2 shadow-sm disabled:opacity-60 transition-all text-sm"
        >
          <svg v-if="isExporting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>{{ isExporting ? 'Đang tạo bản sao lưu...' : `Tải file Sao lưu (.sql) - [${selectedBranchObj.code}]` }}</span>
        </button>
      </div>

      <!-- SECTION 2: IMPORT DATABASE -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between gap-6 hover:border-amber-300 transition-all">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800 m-0">Khôi phục (Import Database)</h3>
              <p class="text-xs text-slate-500 m-0 mt-0.5">Phục hồi dữ liệu vào <strong>{{ selectedBranchObj.name }}</strong></p>
            </div>
          </div>

          <!-- Target Scope Notice -->
          <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-3 text-xs mb-3 flex items-center justify-between">
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
            class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer transition-colors mb-3"
            :class="selectedFile ? 'border-sky-400 bg-sky-50/50' : 'border-slate-300 hover:border-sky-400 bg-slate-50/50'"
          >
            <input
              ref="fileInputRef"
              type="file"
              accept=".sql"
              class="hidden"
              @change="handleFileSelect"
            />
            <div v-if="selectedFile" class="flex items-center justify-center gap-2 text-sky-700 font-bold text-xs">
              <svg class="w-5 h-5 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span>{{ selectedFile.name }} ({{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB)</span>
            </div>
            <div v-else class="flex flex-col items-center gap-1 text-slate-500 text-xs">
              <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <span>Nhấn để chọn file hoặc Kéo & Thả file <strong>.sql</strong> vào đây</span>
            </div>
          </div>

          <!-- Warning Checkbox -->
          <div class="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-800 space-y-1">
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmRestore" class="mt-0.5 accent-rose-600" />
              <span class="font-medium">
                Tôi xác nhận hiểu rằng toàn bộ dữ liệu của <strong>[{{ selectedBranchObj.name }} - {{ selectedBranchObj.database }}]</strong> sẽ bị GHI ĐÈ hoàn toàn.
              </span>
            </label>
          </div>
        </div>

        <button
          @click="openRestoreConfirm"
          :disabled="isImporting || !selectedFile || !confirmRestore"
          class="w-full py-3.5 px-4 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold rounded-xl border-none cursor-pointer flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all text-sm"
        >
          <svg v-if="isImporting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>{{ isImporting ? 'Đang khôi phục database...' : `Khôi phục vào [${selectedBranchObj.code}]` }}</span>
        </button>
      </div>
    </div>

    <!-- OVERVIEW TABLE: TỔNG QUAN CÁC DATABASE TRONG HỆ THỐNG -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
      <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/70">
        <div>
          <h4 class="text-sm font-bold text-slate-800 m-0">Tổng quan Cơ sở Dữ liệu Hệ thống & Các Chi nhánh</h4>
          <p class="text-xs text-slate-500 m-0 mt-0.5">Danh sách các database quản trị và database chi nhánh trong hệ thống PMS</p>
        </div>
        <button
          @click="fetchDatabaseInfo"
          :disabled="isLoading"
          class="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 cursor-pointer flex items-center gap-1.5 shadow-2xs"
        >
          <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>Làm mới</span>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-100/80 text-slate-600 font-bold border-b border-slate-200">
              <th class="py-2.5 px-4">Mã</th>
              <th class="py-2.5 px-4">Tên Cơ Sở Dữ Liệu / Chi Nhánh</th>
              <th class="py-2.5 px-4">Tên Database</th>
              <th class="py-2.5 px-4 text-center">Số Bảng</th>
              <th class="py-2.5 px-4 text-right">Dung Lượng</th>
              <th class="py-2.5 px-4 text-center">Trạng Thái</th>
              <th class="py-2.5 px-4 text-center">Thao Tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <!-- ROW 1: TẤT CẢ (ALL) -->
            <tr
              class="hover:bg-slate-50 transition-colors bg-indigo-50/30"
              :class="{ 'bg-indigo-100/60 font-bold': selectedBranchCode === 'ALL' }"
            >
              <td class="py-2.5 px-4 font-mono font-bold text-indigo-700">
                <span class="flex items-center gap-1.5">
                  <span v-if="selectedBranchCode === 'ALL'" class="w-2 h-2 rounded-full bg-indigo-600"></span>
                  <span>ALL</span>
                </span>
              </td>
              <td class="py-2.5 px-4 text-indigo-950 font-bold">
                🌐 Toàn Bộ Hệ Thống & Tất Cả Chi Nhánh
              </td>
              <td class="py-2.5 px-4 font-mono text-indigo-700">ALL (Multi-DB)</td>
              <td class="py-2.5 px-4 text-center font-bold text-indigo-900">{{ summaryAll.table_count || 0 }}</td>
              <td class="py-2.5 px-4 text-right font-mono font-semibold text-indigo-900">{{ summaryAll.size_mb ? summaryAll.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="py-2.5 px-4 text-center">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 border border-indigo-300">
                  Tổng hợp
                </span>
              </td>
              <td class="py-2.5 px-4 text-center">
                <button
                  v-if="selectedBranchCode !== 'ALL'"
                  @click="selectTarget('ALL')"
                  class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded font-semibold text-[11px] border border-indigo-200 cursor-pointer"
                >
                  Chọn ALL
                </button>
                <span v-else class="text-[11px] font-bold text-indigo-700">Đang chọn</span>
              </td>
            </tr>

            <!-- ROW 2: SYSTEM DB -->
            <tr
              class="hover:bg-slate-50 transition-colors bg-slate-50/50"
              :class="{ 'bg-indigo-50/50 font-bold': selectedBranchCode === 'SYSTEM' }"
            >
              <td class="py-2.5 px-4 font-mono font-bold text-slate-800">
                <span class="flex items-center gap-1.5">
                  <span v-if="selectedBranchCode === 'SYSTEM'" class="w-2 h-2 rounded-full bg-indigo-600"></span>
                  <span>SYSTEM</span>
                </span>
              </td>
              <td class="py-2.5 px-4 text-slate-800 font-semibold">
                ⚙️ Database Hệ Thống Chính (Users, Roles, Chi nhánh...)
              </td>
              <td class="py-2.5 px-4 font-mono text-sky-700">{{ systemDb.database || 'pms_system' }}</td>
              <td class="py-2.5 px-4 text-center font-bold text-slate-700">{{ systemDb.table_count || 0 }}</td>
              <td class="py-2.5 px-4 text-right font-mono text-slate-700">{{ systemDb.size_mb ? systemDb.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="py-2.5 px-4 text-center">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 border border-sky-300">
                  Hệ thống
                </span>
              </td>
              <td class="py-2.5 px-4 text-center">
                <button
                  v-if="selectedBranchCode !== 'SYSTEM'"
                  @click="selectTarget('SYSTEM')"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-sky-50 text-sky-700 rounded font-semibold text-[11px] border border-slate-200 cursor-pointer"
                >
                  Chọn System
                </button>
                <span v-else class="text-[11px] font-bold text-indigo-600">Đang chọn</span>
              </td>
            </tr>

            <!-- ROWS FOR BRANCHES -->
            <tr
              v-for="b in branches"
              :key="b.id"
              class="hover:bg-slate-50 transition-colors"
              :class="{ 'bg-indigo-50/40 font-semibold': b.code.toUpperCase() === selectedBranchCode.toUpperCase() }"
            >
              <td class="py-2.5 px-4 font-mono font-bold text-slate-800">
                <span class="flex items-center gap-1.5">
                  <span v-if="b.code.toUpperCase() === selectedBranchCode.toUpperCase()" class="w-2 h-2 rounded-full bg-indigo-600"></span>
                  <span>{{ b.code }}</span>
                </span>
              </td>
              <td class="py-2.5 px-4 text-slate-700">🏨 {{ b.name }}</td>
              <td class="py-2.5 px-4 font-mono text-sky-700">{{ b.database }}</td>
              <td class="py-2.5 px-4 text-center font-bold text-slate-700">{{ b.table_count || 0 }}</td>
              <td class="py-2.5 px-4 text-right font-mono text-slate-700">{{ b.size_mb ? b.size_mb.toFixed(2) : '0.00' }} MB</td>
              <td class="py-2.5 px-4 text-center">
                <span
                  v-if="b.status === 'ready'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-300"
                >
                  Sẵn sàng
                </span>
                <span
                  v-else-if="b.status === 'empty'"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-300"
                >
                  Trống
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-300"
                >
                  Mất kết nối
                </span>
              </td>
              <td class="py-2.5 px-4 text-center">
                <button
                  v-if="b.code.toUpperCase() !== selectedBranchCode.toUpperCase()"
                  @click="selectTarget(b.code)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-indigo-50 text-indigo-600 hover:text-indigo-800 rounded font-semibold text-[11px] border border-slate-200 cursor-pointer"
                >
                  Chọn chi nhánh này
                </button>
                <span v-else class="text-[11px] font-bold text-indigo-600">Đang chọn</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL XÁC NHẬN KHÔI PHỤC NGUY HIỂM -->
    <div
      v-if="showConfirmModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 animate-fade-in"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-rose-200">
        <div class="flex items-center gap-3 text-rose-600 mb-3">
          <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center shrink-0">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-rose-700 m-0">Xác nhận Ghi đè Database!</h3>
            <p class="text-xs text-rose-500 m-0">Hành động này không thể hoàn tác</p>
          </div>
        </div>

        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-xs text-slate-700 space-y-2 mb-4">
          <p class="m-0 font-medium">Bạn chuẩn bị khôi phục file <strong>{{ selectedFile?.name }}</strong> vào:</p>
          <div class="p-2 bg-white rounded-lg border border-rose-200 font-mono text-xs">
            <div>Phạm vi: <strong class="text-rose-700">{{ selectedBranchObj.name }} ({{ selectedBranchObj.code }})</strong></div>
            <div>Database đích: <strong class="text-rose-700">{{ selectedBranchObj.database }}</strong></div>
          </div>
          <p class="m-0 text-rose-800 font-bold">
            ⚠️ Toàn bộ dữ liệu hiện tại của phạm vi này sẽ bị XÓA và THAY THẾ bằng dữ liệu trong file sao lưu!
          </p>
        </div>

        <div class="flex items-center justify-end gap-3">
          <button
            @click="showConfirmModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs border border-slate-300 cursor-pointer"
          >
            Hủy bỏ
          </button>
          <button
            @click="executeImport"
            class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs border-none cursor-pointer shadow-sm"
          >
            Đồng ý Ghi đè & Khôi phục
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
