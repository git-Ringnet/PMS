/**
 * Dòng 543: thao tác ở màn khác (vd Sang ngày: noshow, late check-in) báo cho các tab đang mở đăng ký
 * tải lại booking ngay, không cần F5. Dùng chung kênh 'pms-room-updates' mà màn Đăng ký đang nghe.
 */
export function notifyBookingsUpdated(bookingIds, Channel = globalThis.BroadcastChannel) {
  if (typeof Channel !== 'function') return 0
  const ids = [...new Set((Array.isArray(bookingIds) ? bookingIds : [bookingIds])
    .filter(id => id !== null && id !== undefined && String(id).trim() !== ''))]
  if (ids.length === 0) return 0
  let channel = null
  try {
    channel = new Channel('pms-room-updates')
    ids.forEach(bookingId => channel.postMessage({ type: 'booking-updated', bookingId, timestamp: Date.now() }))
    channel.postMessage('rooms-updated')
  } catch (err) {
    console.warn('notifyBookingsUpdated broadcast error:', err)
  } finally {
    try { channel?.close() } catch { /* bỏ qua */ }
  }
  return ids.length
}
