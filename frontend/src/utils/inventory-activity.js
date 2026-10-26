// Dòng 439: "Hoạt động nhiều nhất" tính theo số lần phát sinh, không theo tổng số lượng.

const ACTIVITY_FIELDS = ['receive', 'export', 'transfer']

/** Số lượt phát sinh của 1 sản phẩm: mỗi ô Nhập/Xuất/Chuyển có số lượng > 0 trong ngày là 1 lượt. */
export function countActivityEntries(productLogs) {
  return Object.values(productLogs || {}).reduce((count, dayLog) => (
    count + ACTIVITY_FIELDS.filter(field => Number(dayLog?.[field]) > 0).length
  ), 0)
}

/**
 * Sản phẩm có nhiều lượt phát sinh nhất trong tháng.
 * logsMap: { product_id: { 'YYYY-MM-DD': { receive, export, transfer } } }
 */
export function findMostActiveProduct(items, logsMap) {
  let best = null
  for (const item of items || []) {
    const count = countActivityEntries(logsMap?.[item.product_id])
    if (count > 0 && (!best || count > best.count)) best = { name: item.product_name, count }
  }
  return best || { name: '', count: 0 }
}
