/**
 * Dòng 446 / 549: lưu đăng ký có thể bị API chặn vì đổi tình trạng đăng ký làm
 * over phòng. AllowOverRoomTypeRoomKind = 1 thì API trả 409 + needs_over_confirm:
 * hỏi người dùng, chọn Có thì gửi lại với force_over, chọn Không thì báo hủy để
 * màn hình hoàn tác. AllowOverRoomTypeRoomKind = 0 trả 422, lỗi được ném ra nguyên.
 *
 * save(extraPayload) gọi API lưu; confirm(message) trả về true/false.
 */
export const OVER_CANCELLED_MESSAGE = 'Đã hủy cập nhật, tình trạng đăng ký giữ nguyên.'

export function isOverConfirmResponse(err) {
  return err?.response?.status === 409 && Boolean(err.response.data?.needs_over_confirm)
}

export async function saveWithOverConfirm(save, confirm) {
  try {
    return await save({})
  } catch (err) {
    if (!isOverConfirmResponse(err)) throw err
    const accepted = await confirm(err.response.data.message || 'Loại phòng đang bị over bạn có muốn tiếp tục')
    if (!accepted) {
      // Cùng dạng lỗi axios để các khối catch hiện thông báo và hoàn tác như lỗi lưu.
      const cancelled = new Error(OVER_CANCELLED_MESSAGE)
      cancelled.overCancelled = true
      cancelled.response = { data: { message: OVER_CANCELLED_MESSAGE } }
      throw cancelled
    }
    return await save({ force_over: true })
  }
}
