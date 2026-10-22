/**
 * Đường dẫn ảnh lưu ở storage/app/public (ảnh sản phẩm, outlet...) để hiển thị trên trình duyệt.
 * Luôn trả về đường dẫn tương đối /storage/... để chạy đúng trên mọi domain (local và server),
 * không gắn cứng http://localhost:8000.
 */
export function storageImageUrl(url) {
  if (!url) return null
  const clean = String(url).trim()
  if (!clean) return null
  if (clean.startsWith('/storage/')) return clean
  if (clean.startsWith('storage/')) return '/' + clean
  const match = clean.match(/\/storage\/.+$/i)
  if (match) return match[0]
  if (!clean.startsWith('http') && !clean.startsWith('/')) {
    return '/storage/' + clean
  }
  return clean
}
