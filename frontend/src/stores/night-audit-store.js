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
  let animationInterval = null
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

  const stepTimeline = [
    { idx: 0, pct: 6, wait: 350 },
    { idx: 1, pct: 12, wait: 350 },
    { idx: 2, pct: 18, wait: 350 },
    { idx: 3, pct: 24, wait: 400 },
    { idx: 4, pct: 36, wait: 600 },
    { idx: 5, pct: 42, wait: 350 },
    { idx: 6, pct: 48, wait: 400 },
    { idx: 7, pct: 54, wait: 350 },
    { idx: 8, pct: 60, wait: 450 },
    { idx: 9, pct: 66, wait: 400 },
    { idx: 10, pct: 76, wait: 600 },
    { idx: 11, pct: 81, wait: 350 },
    { idx: 12, pct: 86, wait: 350 },
    { idx: 13, pct: 90, wait: 350 },
    { idx: 14, pct: 94, wait: 650 },
    { idx: 15, pct: 97, wait: 350 },
    { idx: 16, pct: 99, wait: 350 },
  ]

  // Cleanup helper
  function clearAllTimers() {
    if (autoCloseTimer) {
      clearInterval(autoCloseTimer)
      autoCloseTimer = null
    }
    if (animationInterval) {
      clearInterval(animationInterval)
      animationInterval = null
    }
    if (redirectTimer) {
      clearTimeout(redirectTimer)
      redirectTimer = null
    }
  }

  // Đếm ngược 10s tự động đóng modal khi lỗi
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

  // Đóng modal quay lại màn hình làm việc
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
  }

  // Tự động đăng xuất sau khi hoàn tất thành công
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
   * Chạy tiến trình phía Initiator (tài khoản trực tiếp bấm nút Sang ngày)
   */
  async function triggerNightAudit({ occupiedToDirty = true, emptyToInspect = true } = {}) {
    clearAllTimers()
    showModal.value = true
    isRunning.value = true
    isInitiator.value = true
    auditRunStatus.value = 'running'
    auditRunError.value = ''
    auditErrorDetails.value = null
    auditSteps.value = JSON.parse(JSON.stringify(defaultAuditSteps))
    currentStepIndex.value = 0
    progressPercent.value = 5
    executorUsername.value = authStore.user?.username || authStore.user?.name || 'system'
    toggleShowDetails.value = false

    let backendResult = null
    let backendError = null

    // Gửi request API
    http.post('/night-audit/run', {
      occupied_to_dirty: occupiedToDirty,
      empty_to_inspect: emptyToInspect
    }).then(res => {
      backendResult = res
    }).catch(err => {
      backendError = err
    })

    // Animation chạy tuần tự các bước
    for (let i = 0; i < stepTimeline.length; i++) {
      if (backendError) break
      const item = stepTimeline[i]
      currentStepIndex.value = item.idx
      progressPercent.value = item.pct
      await new Promise(resolve => setTimeout(resolve, item.wait))
    }

    // Đợi backend hoàn tất
    while (!backendResult && !backendError) {
      await new Promise(resolve => setTimeout(resolve, 200))
    }

    if (backendError) {
      handleAuditError(backendError)
      return { success: false, error: backendError }
    }

    if (backendResult.data && backendResult.data.success) {
      currentStepIndex.value = 17
      progressPercent.value = 100
      auditRunStatus.value = 'succeeded'
      if (Array.isArray(backendResult.data.steps) && backendResult.data.steps.length > 0) {
        auditSteps.value = backendResult.data.steps.map(s => ({
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
      return { success: true, data: backendResult.data }
    } else {
      const errMsg = backendResult.data?.message || 'Không thể chuyển ngày hệ thống.'
      handleAuditError({ response: { data: { message: errMsg } } })
      return { success: false, message: errMsg }
    }
  }

  function handleAuditError(err) {
    auditRunStatus.value = 'failed'
    isRunning.value = false
    const errData = err.response?.data
    const failedStep = errData?.failed_step
    const errMsg = errData?.message || 'Có lỗi xảy ra khi chuyển ngày.'
    auditRunError.value = errMsg
    auditErrorDetails.value = errData?.error_details || null

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
   * Xử lý WebSocket Realtime cho các tài khoản KHÁC đang đăng nhập
   */
  async function handleRemoteStarted(payload = {}) {
    if (isInitiator.value) return // Bỏ qua nếu tab này là tab bấm Sang ngày
    clearAllTimers()
    showModal.value = true
    isRunning.value = true
    auditRunStatus.value = 'running'
    auditRunError.value = ''
    auditErrorDetails.value = null
    auditSteps.value = JSON.parse(JSON.stringify(defaultAuditSteps))
    currentStepIndex.value = 0
    progressPercent.value = 5
    executorUsername.value = payload?.username || 'Hệ thống'
    toggleShowDetails.value = false

    // Animation chạy đồng bộ
    for (let i = 0; i < stepTimeline.length; i++) {
      if (auditRunStatus.value !== 'running') break
      const item = stepTimeline[i]
      currentStepIndex.value = item.idx
      progressPercent.value = item.pct
      await new Promise(resolve => setTimeout(resolve, item.wait))
    }
  }

  async function handleRemoteCompleted(payload = {}) {
    if (isInitiator.value) return
    clearAllTimers()
    if (!showModal.value) showModal.value = true
    currentStepIndex.value = 17
    progressPercent.value = 100
    auditRunStatus.value = 'succeeded'
    executorUsername.value = payload?.username || executorUsername.value
    await completeAndLogout()
  }

  function handleRemoteFailed(payload = {}) {
    if (isInitiator.value) return
    clearAllTimers()
    if (!showModal.value) showModal.value = true
    auditRunStatus.value = 'failed'
    isRunning.value = false
    executorUsername.value = payload?.username || executorUsername.value
    auditRunError.value = payload?.error_message || 'Sang ngày thất bại.'
    auditErrorDetails.value = payload?.error_details || null

    const failedStep = payload?.failed_step
    if (failedStep) {
      const stepIdx = defaultAuditSteps.findIndex(s => s.code === failedStep)
      if (stepIdx !== -1) {
        currentStepIndex.value = stepIdx
        progressPercent.value = Math.max(10, Math.round(((stepIdx + 1) / 18) * 100))
      }
      const tableStepIdx = auditSteps.value.findIndex(s => s.code === failedStep)
      if (tableStepIdx !== -1) {
        auditSteps.value[tableStepIdx].status = 'failed'
        auditSteps.value[tableStepIdx].error = auditRunError.value
      }
    }

    startAutoCloseCountdown()
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
