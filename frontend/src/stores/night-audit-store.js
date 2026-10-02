import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/services/http'
import { useAuthStore } from './auth-store'
import { useUiStore } from './ui-store'
import router from '@/router'

export const defaultAuditSteps = [
  { order: 1, code: 'PRE_CHECK', nameEn: 'Check In/Out Conditions', nameVi: 'Kiểm tra điều kiện phòng đến/đi', status: 'pending' },
  { order: 2, code: 'BACKUP_PRE', nameEn: 'Backup Data', nameVi: 'Khởi tạo sao lưu dữ liệu trước sang ngày', status: 'pending' },
  { order: 3, code: 'LOCK_SYSTEM', nameEn: 'Lock System', nameVi: 'Khóa hệ thống', status: 'pending' },
  { order: 4, code: 'CHECK_DATA', nameEn: 'Checking Data', nameVi: 'Kiểm tra dữ liệu phòng & hóa đơn', status: 'pending' },
  { order: 5, code: 'POST_ROOM_CHARGE', nameEn: 'Post Room Charge', nameVi: 'Tự động post tiền phòng', status: 'pending' },
  { order: 6, code: 'POST_OUTLET_BILL', nameEn: 'Post Check Out Bill OutLet', nameVi: 'Post hóa đơn outlet dịch vụ', status: 'pending' },
  { order: 7, code: 'UPDATE_SYSTEM_DATE', nameEn: 'Update System Date', nameVi: 'Cập nhật ngày hệ thống', status: 'pending' },
  { order: 8, code: 'WRITE_EOD_LOG', nameEn: 'Write End Of Day Log', nameVi: 'Ghi nhật ký đóng ngày', status: 'pending' },
  { order: 9, code: 'UPDATE_ROOM_STATUS', nameEn: 'Update Room Status', nameVi: 'Cập nhật trạng thái buồng phòng', status: 'pending' },
  { order: 10, code: 'PROCESS_FOLIO_CHARGES', nameEn: 'Process Folio Charges', nameVi: 'Đối chiếu phí dịch vụ', status: 'pending' },
  { order: 11, code: 'SPLIT_BILL', nameEn: 'Split Bill', nameVi: 'Tự động tách hóa đơn và cân đối', status: 'pending' },
  { order: 12, code: 'PROCESS_PENDING_OUTLET', nameEn: 'Process Pending Outlets', nameVi: 'Cập nhật bill outlet tồn đọng', status: 'pending' },
  { order: 13, code: 'UPDATE_CHECKOUT_BILL', nameEn: 'Update Check Out Bill', nameVi: 'Cập nhật trạng thái bill trả phòng', status: 'pending' },
  { order: 14, code: 'UNLOCK_SYSTEM', nameEn: 'UnLock System', nameVi: 'Mở khóa hệ thống', status: 'pending' },
  { order: 15, code: 'BACKUP_POST', nameEn: 'Back Up Data After Night Audit', nameVi: 'Lưu trữ các bản snapshot sao lưu (SP7000-SP7005)', status: 'pending' },
  { order: 16, code: 'VERIFY_INTEGRITY', nameEn: 'Verify Backup Integrity', nameVi: 'Kiểm tra tính toàn vẹn bản ghi snapshot', status: 'pending' },
  { order: 17, code: 'SYNC_ALLOTMENT', nameEn: 'Sync AV Allotment To Channel Manager', nameVi: 'Đồng bộ phòng trống lên Channel Manager', status: 'pending' },
  { order: 18, code: 'FINALIZE', nameEn: 'Finish End Day', nameVi: 'Hoàn tất đóng ngày & Giải phóng phiên', status: 'pending' },
]

export const useNightAuditStore = defineStore('nightAudit', () => {
  const authStore = useAuthStore()
  const uiStore = useUiStore()

  // State
  const showModal = ref(false)
  const isRunning = ref(false)
  const isInitiator = ref(false) // true nếu tab hiện tại là người bấm Sang ngày
  const auditRunStatus = ref('idle') // 'idle' | 'running' | 'succeeded' | 'failed'
  const currentStepIndex = ref(0)
  const progressPercent = ref(0)
  const executorUsername = ref('')
  const auditRunError = ref('')
  const auditErrorDetails = ref(null)
  const autoCloseCountdown = ref(10)
  const toggleShowDetails = ref(false)
  const auditSteps = ref(JSON.parse(JSON.stringify(defaultAuditSteps)))

  let autoCloseTimer = null
  let redirectTimer = null

  // Computed
  const currentRunningStepText = computed(() => {
    const s = defaultAuditSteps[currentStepIndex.value] || defaultAuditSteps[0]
    return `Step ${s.order}: ${s.nameEn}`
  })

  const currentFailedStepText = computed(() => {
    const s = defaultAuditSteps[currentStepIndex.value] || defaultAuditSteps[0]
    return `Step ${s.order}: ${s.nameEn} (Thất bại)`
  })

  const finishStepText = computed(() => {
    const now = new Date()
    const d = String(now.getDate()).padStart(2, '0')
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const y = now.getFullYear()
    const h = String(now.getHours()).padStart(2, '0')
    const min = String(now.getMinutes()).padStart(2, '0')
    const user = executorUsername.value || authStore.user?.username || authStore.user?.name || 'system'
    return `Step 18: Finish End Day At ${d}-${m}-${y} ${h}:${min}. User Login: ${user}`
  })

  function clearAllTimers() {
    if (autoCloseTimer) {
      clearInterval(autoCloseTimer)
      autoCloseTimer = null
    }
    if (redirectTimer) {
      clearTimeout(redirectTimer)
      redirectTimer = null
    }
  }

  function startAutoCloseCountdown() {
    clearAllTimers()
    autoCloseCountdown.value = 10
    autoCloseTimer = setInterval(() => {
      autoCloseCountdown.value--
      if (autoCloseCountdown.value <= 0) {
        closeModal()
      }
    }, 1000)
  }

  function closeModal() {
    clearAllTimers()
    showModal.value = false
    isRunning.value = false
    isInitiator.value = false
    auditRunStatus.value = 'idle'
    auditRunError.value = ''
    auditErrorDetails.value = null
    autoCloseCountdown.value = 10
    toggleShowDetails.value = false
    currentStepIndex.value = 0
    progressPercent.value = 0
    auditSteps.value = JSON.parse(JSON.stringify(defaultAuditSteps))
  }

  async function completeAndLogout() {
    clearAllTimers()
    redirectTimer = setTimeout(async () => {
      try {
        await authStore.logout()
      } catch (e) {
        console.error('Logout error:', e)
      }
      uiStore.showToast('Sang ngày thành công! Vui lòng đăng nhập lại.', 'success')
      router.push('/login')
      showModal.value = false
    }, 2500)
  }

  /**
   * Cập nhật từng bước chạy từ Backend (Server-driven realtime progress)
   */
  function handleProgressUpdate(payload = {}) {
    clearAllTimers()
    showModal.value = true
    isRunning.value = true
    auditRunStatus.value = 'running'
    auditRunError.value = ''
    auditErrorDetails.value = null

    if (payload?.username) {
      executorUsername.value = payload.username
    }

    const order = Number(payload?.step_order) || 1
    const idx = Math.max(0, Math.min(17, order - 1))
    currentStepIndex.value = idx

    if (payload?.percent !== undefined) {
      progressPercent.value = Number(payload.percent)
    } else {
      progressPercent.value = Math.max(5, Math.min(99, Math.round((order / 18) * 100)))
    }

    // Cập nhật trạng thái từng dòng bảng 18 bước
    auditSteps.value.forEach((s, i) => {
      if (i < idx) {
        s.status = 'succeeded'
      } else if (i === idx) {
        s.status = 'running'
      } else {
        s.status = 'pending'
      }
    })
  }

  /**
   * Nhận sự kiện hoàn tất Sang ngày từ Backend
   */
  async function handleCompleted(payload = {}) {
    clearAllTimers()
    showModal.value = true
    isRunning.value = false
    auditRunStatus.value = 'succeeded'
    currentStepIndex.value = 17
    progressPercent.value = 100

    if (payload?.username) {
      executorUsername.value = payload.username
    }

    auditSteps.value.forEach(s => {
      s.status = 'succeeded'
    })

    uiStore.showToast('Đã chuyển sang ngày tiếp theo thành công!', 'success')
    await completeAndLogout()
  }

  /**
   * Nhận sự kiện thất bại từ Backend
   */
  function handleFailed(errOrPayload = {}) {
    clearAllTimers()
    showModal.value = true
    isRunning.value = false
    auditRunStatus.value = 'failed'

    let errMsg = 'Có lỗi xảy ra khi chuyển ngày.'
    let failedStep = null
    let errorDetails = null

    if (errOrPayload?.response?.data) {
      const errData = errOrPayload.response.data
      errMsg = errData.message || errMsg
      failedStep = errData.failed_step
      errorDetails = errData.error_details
    } else {
      errMsg = errOrPayload?.error_message || errOrPayload?.message || errMsg
      failedStep = errOrPayload?.failed_step
      errorDetails = errOrPayload?.error_details
    }

    auditRunError.value = errMsg
    auditErrorDetails.value = errorDetails

    if (failedStep) {
      const stepIdx = defaultAuditSteps.findIndex(s => s.code === failedStep)
      if (stepIdx !== -1) {
        currentStepIndex.value = stepIdx
        progressPercent.value = Math.max(10, Math.round(((stepIdx + 1) / 18) * 100))
      }
      const tableStepIdx = auditSteps.value.findIndex(s => s.code === failedStep)
      if (tableStepIdx !== -1) {
        auditSteps.value[tableStepIdx].status = 'failed'
        auditSteps.value[tableStepIdx].error = errMsg
      }
    }

    uiStore.showToast(errMsg, 'error')
    startAutoCloseCountdown()
  }

  /**
   * Khởi chạy Sang ngày từ phía Initiator
   */
  async function triggerNightAudit({ occupiedToDirty = true, emptyToInspect = true } = {}) {
    clearAllTimers()
    isInitiator.value = true
    executorUsername.value = authStore.user?.username || authStore.user?.name || 'system'

    // Bật modal ngay từ bước 1
    handleProgressUpdate({
      username: executorUsername.value,
      step_order: 1,
      percent: 6
    })

    try {
      const res = await http.post('/night-audit/run', {
        occupied_to_dirty: occupiedToDirty,
        empty_to_inspect: emptyToInspect
      })

      if (res.data && res.data.success) {
        await handleCompleted(res.data)
        return { success: true, data: res.data }
      } else {
        const errMsg = res.data?.message || 'Không thể chuyển ngày hệ thống.'
        handleFailed({ message: errMsg })
        return { success: false, message: errMsg }
      }
    } catch (err) {
      handleFailed(err)
      return { success: false, error: err }
    }
  }

  // Compatibility aliases
  function handleRemoteStarted(payload = {}) {
    handleProgressUpdate(payload)
  }
  function handleRemoteCompleted(payload = {}) {
    handleCompleted(payload)
  }
  function handleRemoteFailed(payload = {}) {
    handleFailed(payload)
  }

  return {
    showModal,
    isRunning,
    isInitiator,
    auditRunStatus,
    currentStepIndex,
    progressPercent,
    executorUsername,
    auditRunError,
    auditErrorDetails,
    autoCloseCountdown,
    toggleShowDetails,
    auditSteps,
    currentRunningStepText,
    currentFailedStepText,
    finishStepText,
    triggerNightAudit,
    closeModal,
    handleProgressUpdate,
    handleCompleted,
    handleFailed,
    handleRemoteStarted,
    handleRemoteCompleted,
    handleRemoteFailed,
  }
})
