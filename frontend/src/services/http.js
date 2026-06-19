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
    const token = localStorage.getItem('pms_token')
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

// Response interceptor
http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      switch (error.response.status) {
        case 401:
          localStorage.removeItem('pms_token')
          window.location.href = '/login'
          break
        case 403:
          console.error('Không có quyền truy cập')
          break
        case 500:
          console.error('Lỗi máy chủ')
          break
      }
    }
    return Promise.reject(error)
  }
)

export default http
