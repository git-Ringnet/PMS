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
    token: localStorage.getItem('pms_token') || sessionStorage.getItem('pms_token') || null,
    settings: {},
    loading: false,
    error: null,
    // ── Permission & Branch state ──────────────────────────────
    permissions: JSON.parse(localStorage.getItem('pms_permissions') || '[]'),
    branches: JSON.parse(localStorage.getItem('pms_branches') || '[]'),
    activeBranch: JSON.parse(localStorage.getItem('pms_active_branch') || 'null'),
    roles: JSON.parse(localStorage.getItem('pms_roles') || '[]'),
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,

    /** Kiểm tra user có quyền cụ thể không */
    hasPermission: (state) => (code) => {
      // Super admin có tất cả quyền
      if (state.roles.some(r => r.role_code === 'super_admin')) return true
      return state.permissions.includes(code)
    },

    /** Kiểm tra user có bất kỳ quyền nào trong danh sách */
    canAny: (state) => (codes) => {
      if (state.roles.some(r => r.role_code === 'super_admin')) return true
      return codes.some(c => state.permissions.includes(c))
    },

    isSuperAdmin: (state) => state.roles.some(r => r.role_code === 'super_admin'),
    isAdmin: (state) => state.roles.some(r => ['super_admin', 'branch_admin'].includes(r.role_code)),
  },

  actions: {
    async initialize() {
      if (this.token) {
        try {
          this.loading = true
          const response = await http.get('/me')
          this.user = response.data
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
        const { token, user, permissions, branches, active_branch, roles } = response.data

        this.token = token
        this.user = user
        this.permissions = permissions || []
        this.branches = branches || []
        this.activeBranch = active_branch || null
        this.roles = roles || []

        localStorage.setItem('pms_token', token)
        localStorage.setItem('pms_permissions', JSON.stringify(this.permissions))
        localStorage.setItem('pms_branches', JSON.stringify(this.branches))
        localStorage.setItem('pms_active_branch', JSON.stringify(this.activeBranch))
        localStorage.setItem('pms_roles', JSON.stringify(this.roles))
        if (this.activeBranch) {
          localStorage.setItem('selected_branch_id', this.activeBranch.id)
          localStorage.setItem('selected_branch_code', this.activeBranch.code)
        }

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

    /** Chuyển chi nhánh đang active */
    switchBranch(branch) {
      this.activeBranch = branch
      localStorage.setItem('pms_active_branch', JSON.stringify(branch))
      localStorage.setItem('selected_branch_id', branch.id)
      localStorage.setItem('selected_branch_code', branch.code)
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
        this.permissions = []
        this.branches = []
        this.activeBranch = null
        this.roles = []
        localStorage.removeItem('pms_token')
        localStorage.removeItem('pms_permissions')
        localStorage.removeItem('pms_branches')
        localStorage.removeItem('pms_active_branch')
        localStorage.removeItem('pms_roles')
        localStorage.removeItem('selected_branch_id')
        localStorage.removeItem('selected_branch_code')
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
