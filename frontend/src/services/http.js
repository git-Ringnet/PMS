import axios from 'axios'

const http = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor
http.interceptors.request.use(
  (config) => {
    const token = sessionStorage.getItem('pms_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    
    // Đính kèm ngôn ngữ hiện tại của người dùng vào header
    const lang = localStorage.getItem('pms_lang') || 'vi'
    config.headers['Accept-Language'] = lang
    config.headers['X-Language'] = lang
    
    return config
  },
  (error) => Promise.reject(error)
)

// Flag tránh redirect nhiều lần khi nhiều request 401 cùng lúc
let _isRedirectingToLogin = false

// Response interceptor
http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      const status = error.response.status

      // 401 = Token hết hạn / không hợp lệ (ví dụ: server migrate:fresh)
      // 419 = Session/CSRF expired
      if ((status === 401 || status === 419) && !_isRedirectingToLogin) {
        _isRedirectingToLogin = true

        // Xóa sạch toàn bộ auth state ở sessionStorage
        sessionStorage.removeItem('pms_token')
        sessionStorage.removeItem('pms_user')
        localStorage.removeItem('pms_lang')
        // Xóa các keys cấu hình cũ ở localStorage nếu cần
        Object.keys(localStorage)
          .filter(key => key.startsWith('pms_'))
          .forEach(key => localStorage.removeItem(key))

        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }

        // Reset flag sau 3 giây để tránh lock vĩnh viễn
        setTimeout(() => { _isRedirectingToLogin = false }, 3000)
      } else if (status === 403) {
        console.error('Không có quyền truy cập')
      } else if (status === 500) {
        console.error('Lỗi máy chủ')
      }
    }
    return Promise.reject(error)
  }
)

export default http
