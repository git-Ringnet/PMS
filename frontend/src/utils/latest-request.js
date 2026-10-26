/**
 * Dòng 411: nhiều request tìm kiếm có thể chạy song song (mở modal, gõ từng ký tự,
 * đổi lọc). Mỗi lần gọi lấy một mã mới; chỉ response của mã mới nhất được áp dụng,
 * để response cũ về sau (ví dụ tìm "" trả toàn bộ booking) không ghi đè kết quả mới.
 */
export function createLatestRequestGuard() {
  let current = 0
  return {
    next: () => ++current,
    isLatest: id => id === current,
  }
}
