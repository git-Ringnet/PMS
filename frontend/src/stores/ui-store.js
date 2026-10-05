import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  // Toasts stack
  const toasts = ref([])

  // Modal confirmation dialog configuration
  const confirmState = ref({
    show: false,
    title: 'Xác nhận',
    message: '',
    confirmText: 'Xác nhận',
    cancelText: 'Hủy',
    resolve: null
  })

  // Modal alert dialog configuration
  const alertState = ref({
    show: false,
    title: 'Thông báo',
    message: '',
    confirmText: 'Đóng',
    resolve: null
  })

  /**
   * Display toast notification
   * @param {string} message - Notification text
   * @param {'success' | 'error' | 'warning' | 'info'} type - Type of toast
   * @param {number} duration - Auto dismiss timeout in ms
   */
  function showToast(message, type = 'success', duration = 3000) {
    const id = Date.now() + Math.random().toString(36).substring(2, 9)
    toasts.value.push({ id, message, type })
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }

  function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  /**
   * Promise-based confirmation popup trigger
   * @param {Object} options - Config object
   * @param {string} options.title - Header title
   * @param {string} options.message - Body content message
   * @param {string} [options.confirmText] - Button confirm label
   * @param {string} [options.cancelText] - Button cancel label
   * @returns {Promise<boolean>} Resolves to true if confirmed, false if cancelled
   */
  function confirm({ title = 'Xác nhận', message, confirmText = 'Xác nhận', cancelText = 'Hủy' }) {
    return new Promise((resolve) => {
      confirmState.value = {
        show: true,
        title,
        message,
        confirmText,
        cancelText,
        resolve
      }
    })
  }

  function handleConfirm(value) {
    if (confirmState.value.resolve) {
      confirmState.value.resolve(value)
    }
    confirmState.value.show = false
  }

  /**
   * Promise-based alert popup trigger
   * @param {Object|string} options - Config object or message string
   * @returns {Promise<void>} Resolves when closed
   */
  function alert(options) {
    let opts = typeof options === 'string' ? { message: options } : options
    return new Promise((resolve) => {
      alertState.value = {
        show: true,
        title: opts.title || 'Thông báo',
        message: opts.message,
        confirmText: opts.confirmText || 'Đóng',
        resolve
      }
    })
  }

  function handleAlert() {
    if (alertState.value.resolve) {
      alertState.value.resolve()
    }
    alertState.value.show = false
  }

  // Auth password confirmation dialog configuration (CheckAuthorization)
  const authModalState = ref({
    show: false,
    username: '',
    errorMessage: '',
    loading: false,
    resolve: null
  })

  let cachedCheckAuth = null
  let cachedCheckAuthTime = 0

  if (typeof window !== 'undefined') {
    window.addEventListener('hotel-config-updated', (e) => {
      if (e.detail?.name === 'CheckAuthorization') {
        cachedCheckAuth = null
      }
    })
  }

  async function getCheckAuthorizationSetting() {
    const now = Date.now()
    if (cachedCheckAuth !== null && now - cachedCheckAuthTime < 5000) {
      return cachedCheckAuth
    }
    try {
      const http = (await import('@/services/http')).default
      const res = await http.get('/hotel-configs', { params: { name: 'CheckAuthorization' } })
      const configs = res.data?.data || []
      const found = configs.find(c => c.name === 'CheckAuthorization')
      const val = found ? String(found.value).trim() : '0'
      cachedCheckAuth = val
      cachedCheckAuthTime = now
      return val
    } catch {
      return '0'
    }
  }

  /**
   * Request password authorization if CheckAuthorization != 0.
   * If CheckAuthorization == 0, returns true immediately.
   * @returns {Promise<boolean>} Resolves to true if authorized, false if cancelled
   */
  async function requestAuthorization() {
    const settingValue = await getCheckAuthorizationSetting()
    if (settingValue === '0' || settingValue === '' || settingValue === false || Number(settingValue) === 0) {
      return true
    }

    const { useAuthStore } = await import('@/stores/auth-store')
    const authStore = useAuthStore()
    const currentUsername = authStore.user?.username || 'admin'

    return new Promise((resolve) => {
      authModalState.value = {
        show: true,
        username: currentUsername,
        errorMessage: '',
        loading: false,
        resolve
      }
    })
  }

  function handleAuthSuccess() {
    if (authModalState.value.resolve) {
      authModalState.value.resolve(true)
    }
    authModalState.value.show = false
  }

  function handleAuthCancel() {
    if (authModalState.value.resolve) {
      authModalState.value.resolve(false)
    }
    authModalState.value.show = false
  }

  return {
    toasts,
    confirmState,
    alertState,
    authModalState,
    showToast,
    removeToast,
    confirm,
    handleConfirm,
    alert,
    handleAlert,
    requestAuthorization,
    handleAuthSuccess,
    handleAuthCancel,
    getCheckAuthorizationSetting
  }
})
