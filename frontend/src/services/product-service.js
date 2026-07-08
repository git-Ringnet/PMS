import http from './http'

// ==================== PRODUCT CATEGORIES (Loại thực đơn) ====================
// Changed to call dedicated F&B API endpoints: /fb-product-categories
export const fetchProductCategories = () => http.get('/fb-product-categories')

export const fetchPromotions = () => http.get('/fb-promotions')


export const createProductCategory = (formData) => http.post('/fb-product-categories', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})

export const updateProductCategory = (id, formData) => http.post(`/fb-product-categories/${id}?_method=PUT`, formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})

export const deleteProductCategory = (id) => http.delete(`/fb-product-categories/${id}`)

// ==================== PRODUCTS (Thực đơn / Món ăn) ====================
// Changed to call dedicated F&B API endpoints: /fb-products
export const fetchProducts = () => http.get('/fb-products')

export const createProduct = (formData) => http.post('/fb-products', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})

export const updateProduct = (id, formData) => http.post(`/fb-products/${id}?_method=PUT`, formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})

export const deleteProduct = (id) => http.delete(`/fb-products/${id}`)

export const bulkToggleActiveProducts = (ids) => http.post('/fb-products/bulk-toggle-active', { ids })
export const bulkUpdateProductStatus = (payload) => http.post('/fb-products/bulk-update-status', payload)

// ==================== UNITS OF MEASURE (Đơn vị tính) ====================
export const fetchUnitsOfMeasure = () => http.get('/units-of-measure')
