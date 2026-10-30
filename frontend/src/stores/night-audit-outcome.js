// Thời gian chờ riêng cho lệnh Sang ngày (mặc định http chỉ chờ 15 giây).
export const NIGHT_AUDIT_RUN_TIMEOUT_MS = 10 * 60 * 1000

/**
 * Lỗi có phải do API Sang ngày trả về (success=false) hay không.
 * Timeout, mất mạng, 502/504 của proxy... không có payload này nên chưa biết
 * server đã sang ngày hay chưa.
 */
export function isServerAuditResponse(err) {
  const data = err?.response?.data
  return Boolean(data && typeof data === 'object' && data.success === false)
}

/**
 * Kết quả thật của lần sang ngày dựa trên /night-audit/check-status.
 * Ngày hệ thống đã đổi so với lúc bấm = sang ngày thành công.
 * @returns {'running'|'succeeded'|'failed'}
 */
export function resolveAuditOutcome(status, baselineSystemDate) {
  if (!status || status.is_running) return 'running'
  if (baselineSystemDate && status.system_date) {
    return status.system_date !== baselineSystemDate ? 'succeeded' : 'failed'
  }
  return status.latest_run?.status === 'succeeded' ? 'succeeded' : 'failed'
}
