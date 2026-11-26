import { storageImageUrl } from './storage-url.js'

/**
 * Dòng 534: ảnh hiển thị cho outlet buồng phòng và sản phẩm của outlet.
 * Sản phẩm chưa có ảnh riêng dùng ảnh outlet (giống màn Tạo menu); không có ảnh nào thì null để hiện icon mặc định.
 */
export function outletImage(outlet) {
  return storageImageUrl(outlet?.image_url || outlet?.image)
}

export function productDisplayImage(product, outlet) {
  return storageImageUrl(product?.image) || outletImage(outlet)
}
