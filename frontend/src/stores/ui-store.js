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

  return {
    toasts,
    confirmState,
    showToast,
    removeToast,
    confirm,
    handleConfirm
  }
})
