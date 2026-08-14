<script setup>
import { ref } from 'vue'
import http from '@/services/http'

const isExporting = ref(false)
const isImporting = ref(false)
const selectedFile = ref(null)
const confirmRestore = ref(false)
const message = ref(null)
const errorMsg = ref(null)
const fileInputRef = ref(null)

const handleExport = async () => {
  isExporting.value = true
  message.value = null
  errorMsg.value = null

  try {
    const response = await http.get('/system/database/export', {
      responseType: 'blob',
      timeout: 180000 // 3 minutes timeout for DB export
    })

    // Create download link
    const blob = new Blob([response.data], { type: 'application/sql' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    
    // Extract filename from header if available, or generate default
    const contentDisposition = response.headers['content-disposition']
    let filename = `pms_backup_${new Date().toISOString().slice(0, 10)}.sql`
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

    message.value = 'Tải bản sao lưu database (.sql) thành công!'
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

const handleImport = async () => {
  if (!selectedFile.value) {
    errorMsg.value = 'Vui lòng chọn file .sql để khôi phục!'
    return
  }

  if (!confirmRestore.value) {
    errorMsg.value = 'Vui lòng xác nhận bạn đồng ý ghi đè dữ liệu.'
    return
  }

  if (!confirm('CẢNH BÁO: Việc khôi phục database sẽ GHI ĐÈ TOÀN BỘ dữ liệu hiện tại của hệ thống!\n\nBạn có chắc chắn muốn tiếp tục không?')) {
    return
  }

  isImporting.value = true
  message.value = null
  errorMsg.value = null

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)

    const response = await http.post('/system/database/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      timeout: 300000 // 5 minutes timeout for DB import
    })

    if (response.data.success) {
      message.value = response.data.message || 'Khôi phục database thành công!'
      selectedFile.value = null
      confirmRestore.value = false
      if (fileInputRef.value) fileInputRef.value.value = ''
    } else {
      errorMsg.value = response.data.message || 'Khôi phục database thất bại.'
    }
  } catch (err) {
    console.error('Import DB error:', err)
    errorMsg.value = 'Lỗi khôi phục database: ' + (err.response?.data?.message || err.message)
  } finally {
    isImporting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 max-w-5xl mx-auto p-2">
    <!-- Success Alert -->
    <div v-if="message" class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-xs">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-sm font-semibold">{{ message }}</span>
      </div>
      <button @click="message = null" class="text-emerald-500 hover:text-emerald-800 bg-transparent border-none cursor-pointer">✕</button>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl flex items-center justify-between shadow-xs">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-semibold">{{ errorMsg }}</span>
      </div>
      <button @click="errorMsg = null" class="text-rose-500 hover:text-rose-800 bg-transparent border-none cursor-pointer">✕</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- SECTION 1: EXPORT DATABASE -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between gap-6">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800 m-0">Sao lưu (Export Database)</h3>
              <p class="text-xs text-slate-500 m-0 mt-0.5">Xuất toàn bộ cấu trúc & dữ liệu hệ thống ra file `.sql`</p>
            </div>
          </div>

          <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-xs text-slate-600 space-y-2 mb-4">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Định dạng file xuất: <strong>.sql</strong></span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-sky-500"></span>
              <span>Bao gồm cấu trúc bảng, khóa ngoại & dữ liệu hiện tại</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
              <span>Khuyến nghị thực hiện sao lưu định kỳ để bảo đảm an toàn dữ liệu</span>
            </div>
          </div>
        </div>

        <button
          @click="handleExport"
          :disabled="isExporting"
          class="w-full py-3 px-4 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-bold rounded-xl border-none cursor-pointer flex items-center justify-center gap-2 shadow-sm disabled:opacity-60 transition-all text-sm"
        >
          <svg v-if="isExporting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>{{ isExporting ? 'Đang tạo bản sao lưu...' : 'Tải file Sao lưu (.sql)' }}</span>
        </button>
      </div>

      <!-- SECTION 2: IMPORT DATABASE -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between gap-6">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 shrink-0">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-800 m-0">Khôi phục (Import Database)</h3>
              <p class="text-xs text-slate-500 m-0 mt-0.5">Phục hồi toàn bộ dữ liệu từ file sao lưu `.sql`</p>
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
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800 space-y-2">
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="checkbox" v-model="confirmRestore" class="mt-0.5 accent-amber-600" />
              <span class="font-medium">Tôi xác nhận hiểu rằng dữ liệu hiện tại sẽ bị GHI ĐỀ hoàn toàn.</span>
            </label>
          </div>
        </div>

        <button
          @click="handleImport"
          :disabled="isImporting || !selectedFile || !confirmRestore"
          class="w-full py-3 px-4 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold rounded-xl border-none cursor-pointer flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all text-sm"
        >
          <svg v-if="isImporting" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>{{ isImporting ? 'Đang khôi phục database...' : 'Bắt đầu Khôi phục Database' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
