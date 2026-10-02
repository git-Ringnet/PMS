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

// Timeline 17 bước đầu kéo dài 7000ms (~7 giây) đồng bộ tuyệt đối theo thời gian thực
export const TIMELINE_DURATION_MS = 7000

export const stepSchedule = [
  { start: 0, end: 350, idx: 0, pct: 6 },
  { start: 350, end: 700, idx: 1, pct: 12 },
  { start: 700, end: 1050, idx: 2, pct: 18 },
  { start: 1050, end: 1450, idx: 3, pct: 24 },
  { start: 1450, end: 2050, idx: 4, pct: 36 },
  { start: 2050, end: 2400, idx: 5, pct: 42 },
  { start: 2400, end: 2800, idx: 6, pct: 48 },
  { start: 2800, end: 3150, idx: 7, pct: 54 },
  { start: 3150, end: 3600, idx: 8, pct: 60 },
  { start: 3600, end: 4000, idx: 9, pct: 66 },
  { start: 4000, end: 4600, idx: 10, pct: 76 },
  { start: 4600, end: 4950, idx: 11, pct: 81 },
  { start: 4950, end: 5300, idx: 12, pct: 86 },
  { start: 5300, end: 5650, idx: 13, pct: 90 },
  { start: 5650, end: 6300, idx: 14, pct: 94 },
  { start: 6300, end: 6650, idx: 15, pct: 97 },
  { start: 6650, end: 7000, idx: 16, pct: 99 },
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

  // Đồng bộ thời gian
  const startTimestamp = ref(0)
  const backendSuccessData = ref(null)
  const backendFailedData = ref(null)

  let autoCloseTimer = null
  let syncInterval = null
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
    if (syncInterval) {
      clearInterval(syncInterval)
      syncInterval = null
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
    backendSuccessData.value = null
    backendFailedData.value = null
    startTimestamp.value = 0
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
   * Vòng lặp đồng bộ thời gian thực chung cho TẤT CẢ các tài khoản
   */
  function startSynchronizedTimeline(startMs) {
    if (syncInterval) {
      clearInterval(syncInterval)
      syncInterval = null
    }

    startTimestamp.value = startMs || Date.now()
    showModal.value = true
    isRunning.value = true
    auditRunStatus.value = 'running'
    auditRunError.value = ''
    auditErrorDetails.value = null
    auditSteps.value = JSON.parse(JSON.stringify(defaultAuditSteps))
    toggleShowDetails.value = false

    syncInterval = setInterval(() => {
      // 1. Kiểm tra nếu có lỗi backend
      if (backendFailedData.value) {
        clearInterval(syncInterval)
        syncInterval = null
        applyFailedState(backendFailedData.value)
        return
      }

      // 2. Tính thời gian đã trôi qua kể từ mốc startTimestamp
      const elapsed = Date.now() - startTimestamp.value

      if (elapsed < TIMELINE_DURATION_MS) {
        // Trong khoảng 0 -> 7000ms: Cập nhật đúng bước theo timeline
        const current = stepSchedule.find(s => elapsed >= s.start && elapsed < s.end) || stepSchedule[0]
        currentStepIndex.value = current.idx
        progressPercent.value = current.pct
      } else {
        // Đã hoàn thành 17 bước (7000ms)
        currentStepIndex.value = 16
        progressPercent.value = 99

        // 3. Nếu backend đã báo thành công thì chuyển sang Step 18
        if (backendSuccessData.value) {
          clearInterval(syncInterval)
          syncInterval = null
          applySuccessState(backendSuccessData.value)
        }
      }
    }, 80)
  }

  async function applySuccessState(data) {
    currentStepIndex.value = 17
    progressPercent.value = 100
    auditRunStatus.value = 'succeeded'

    if (Array.isArray(data?.steps) && data.steps.length > 0) {
      auditSteps.value = data.steps.map(s => ({
        code: s.step_code,
        order: s.step_order,
        name: s.step_name,
        status: s.status,
        affected_rows: s.affected_rows,
        summary: s.summary,
        error: s.error_message,
      }))
    }

    uiStore.showToast('Đã chuyển sang ngày tiếp theo thành công!', 'success')
    await completeAndLogout()
  }

  function applyFailedState(errOrPayload) {
    auditRunStatus.value = 'failed'
    isRunning.value = false

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
   * Phía Initiator: Người bấm Sang ngày
   */
  async function triggerNightAudit({ occupiedToDirty = true, emptyToInspect = true } = {}) {
    clearAllTimers()
    isInitiator.value = true
    backendSuccessData.value = null
    backendFailedData.value = null
    executorUsername.value = authStore.user?.username || authStore.user?.name || 'system'

    const nowMs = Date.now()
    startSynchronizedTimeline(nowMs)

    try {
      const res = await http.post('/night-audit/run', {
        occupied_to_dirty: occupiedToDirty,
        empty_to_inspect: emptyToInspect
      })

      if (res.data && res.data.success) {
        backendSuccessData.value = res.data
        return { success: true, data: res.data }
      } else {
        const errMsg = res.data?.message || 'Không thể chuyển ngày hệ thống.'
        backendFailedData.value = { message: errMsg }
        return { success: false, message: errMsg }
      }
    } catch (err) {
      backendFailedData.value = err
      return { success: false, error: err }
    }
  }

  /**
   * Phía Remote: Nhận WebSocket sự kiện từ tài khoản khác đang chạy
   */
  function handleRemoteStarted(payload = {}) {
    if (isInitiator.value) return
    clearAllTimers()
    isInitiator.value = false
    backendSuccessData.value = null
    backendFailedData.value = null
    executorUsername.value = payload?.username || 'Hệ thống'

    const startMs = payload?.started_at ? Number(payload.started_at) : Date.now()
    startSynchronizedTimeline(startMs)
  }

  function handleRemoteCompleted(payload = {}) {
    if (isInitiator.value) return
    if (payload?.username) {
      executorUsername.value = payload.username
    }
    backendSuccessData.value = payload

    // Nếu chưa mở modal (ví dụ người dùng vừa login vào lúc đã xong):
    if (!showModal.value) {
      showModal.value = true
      isRunning.value = true
    }

    // Nếu đã hết thời gian 7000ms: hoàn tất ngay lập tức
    const elapsed = Date.now() - (payload?.started_at ? Number(payload.started_at) : startTimestamp.value)
    if (elapsed >= TIMELINE_DURATION_MS) {
      if (syncInterval) {
        clearInterval(syncInterval)
        syncInterval = null
      }
      applySuccessState(payload)
    }
    // Nếu chưa đủ 7000ms: vòng lặp syncInterval sẽ tiếp tục chạy mượt mà đến 7000ms rồi tự động hoàn tất!
  }

  function handleRemoteFailed(payload = {}) {
    if (isInitiator.value) return
    backendFailedData.value = payload
    if (!showModal.value) {
      showModal.value = true
    }
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
    handleRemoteStarted,
    handleRemoteCompleted,
    handleRemoteFailed,
  }
})
