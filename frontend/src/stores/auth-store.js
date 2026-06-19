import { defineStore } from 'pinia'
import http from '@/services/http'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('pms_token') || null,
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
  },

  actions: {
    async initialize() {
      if (this.token) {
        try {
          this.loading = true
          const response = await http.get('/me')
          this.user = response.data
        } catch (err) {
          console.error('Không thể xác thực user hiện tại', err)
          this.logout()
        } finally {
          this.loading = false
        }
      }
    },

    async login(username, password) {
      try {
        this.loading = true
        this.error = null
        const response = await http.post('/login', { username, password })
        const { token, user } = response.data
        
        this.token = token
        this.user = user
        localStorage.setItem('pms_token', token)
        
        return user
      } catch (err) {
        this.error = err.response?.data?.message || 'Đăng nhập thất bại. Vui lòng kiểm tra lại.'
        throw err
      } finally {
        this.loading = false
      }
    },



    async logout() {
      try {
        if (this.token) {
          await http.post('/logout')
        }
      } catch (err) {
        console.error('Lỗi khi gọi logout ở backend', err)
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('pms_token')
      }
    },
  },
})
