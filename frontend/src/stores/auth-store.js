import { defineStore } from 'pinia'
import http from '@/services/http'

const cleanOldLocalConfigs = () => {
  const oldKeys = [
    'pms_icon_size_g1', 'pms_icon_size_g2', 'pms_icon_size_g3', 'pms_icon_size_g4', 'pms_icon_size_g5',
    'pms_exact_position', 'pms_floor_orientation', 'pms_room_width', 'pms_room_height',
    'pms_room_map_auto_scale', 'pms_room_map_scale', 'pms_header_bg_color', 'pms_visible_columns'
  ]
  oldKeys.forEach(k => localStorage.removeItem(k))
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: sessionStorage.getItem('pms_token') || null,
    settings: {},
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
          // Đồng bộ settings của user nếu có
          this.settings = response.data.setting?.settings || {}
          cleanOldLocalConfigs()
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
        sessionStorage.setItem('pms_token', token)
        
        // Đồng bộ settings của user nếu có
        this.settings = user.setting?.settings || {}
        cleanOldLocalConfigs()
        
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
        this.settings = {}
        sessionStorage.removeItem('pms_token')
      }
    },

    async updateUserSettings(newSettings) {
      try {
        const response = await http.put('/user-settings', { settings: newSettings })
        if (response.data && response.data.success) {
          this.settings = response.data.data.settings || {}
          if (this.user) {
            if (!this.user.setting) this.user.setting = {}
            this.user.setting.settings = this.settings
          }
        }
        return this.settings
      } catch (err) {
        console.error('Lỗi khi cập nhật thiết lập người dùng', err)
        throw err
      }
    },
  },
})
