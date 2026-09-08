<template>
  <div
    v-if="showModal"
    class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-900/70 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 animate-in mx-4">
      <!-- Header -->
      <div class="bg-gradient-to-r from-sky-500 to-sky-600 px-6 py-4 text-white">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-amber-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
          </svg>
          <h3 class="text-sm font-bold tracking-wide">Yêu Cầu Đổi Mật Khẩu Lần Đầu</h3>
        </div>
        <p class="text-[11px] text-sky-100 mt-1">
          Tài khoản của bạn cần thay đổi mật khẩu mặc định trước khi sử dụng hệ thống.
        </p>
      </div>

      <!-- Form Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4 text-xs">
        <div v-if="errorMessage" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-600 rounded-md font-medium text-[11px]">
          {{ errorMessage }}
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700">Mật khẩu hiện tại *</label>
          <input
            v-model="form.current_password"
            type="password"
            required
            placeholder="Nhập mật khẩu hiện tại..."
            class="border border-slate-300 rounded-md p-2 text-xs focus:outline-sky-500 bg-white"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700">Mật khẩu mới (từ 6 ký tự) *</label>
          <input
            v-model="form.new_password"
            type="password"
            required
            minlength="6"
            placeholder="Nhập mật khẩu mới..."
            class="border border-slate-300 rounded-md p-2 text-xs focus:outline-sky-500 bg-white"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700">Xác nhận mật khẩu mới *</label>
          <input
            v-model="form.new_password_confirmation"
            type="password"
            required
            minlength="6"
            placeholder="Nhập lại mật khẩu mới..."
            class="border border-slate-300 rounded-md p-2 text-xs focus:outline-sky-500 bg-white"
          />
        </div>

        <div class="pt-2 flex items-center justify-between">
          <button
            type="button"
            @click="handleLogout"
            class="text-xs text-slate-500 hover:text-slate-700 font-semibold underline bg-transparent border-none cursor-pointer"
          >
            Đăng xuất
          </button>

          <button
            type="submit"
            :disabled="loading"
            class="px-5 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-md font-bold text-xs cursor-pointer border-none shadow-sm transition-colors flex items-center gap-1.5 disabled:opacity-50"
          >
            <span v-if="loading" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>{{ loading ? 'Đang cập nhật...' : 'Đổi Mật Khẩu' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import { changeUserPassword } from '@/services/company-service'

const authStore = useAuthStore()
const uiStore = useUiStore()

// Bật từ custom event khi http interceptor bắt được 423
const forceShow = ref(false)

const onForceChangeEvent = () => {
  // Set cờ trực tiếp vào user nếu có
  if (authStore.user) {
    authStore.user.must_change_password = true
  }
  forceShow.value = true
}

onMounted(() => {
  window.addEventListener('pms:force-change-password', onForceChangeEvent)
})
onUnmounted(() => {
  window.removeEventListener('pms:force-change-password', onForceChangeEvent)
})

const loading = ref(false)
const errorMessage = ref('')
const form = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const showModal = computed(() => {
  // Hiện khi: (1) cờ store có must_change_password, hoặc (2) http interceptor bắt 423
  return forceShow.value ||
    (!!authStore.isAuthenticated && !!authStore.user && !!authStore.user.must_change_password)
})

const handleSubmit = async () => {
  errorMessage.value = ''
  if (!form.value.current_password) {
    errorMessage.value = 'Vui lòng nhập mật khẩu hiện tại.'
    return
  }
  if (!form.value.new_password || form.value.new_password.length < 6) {
    errorMessage.value = 'Mật khẩu mới phải từ 6 ký tự trở lên.'
    return
  }
  if (form.value.new_password !== form.value.new_password_confirmation) {
    errorMessage.value = 'Xác nhận mật khẩu mới không khớp.'
    return
  }

  loading.value = true
  try {
    const res = await changeUserPassword({
      current_password: form.value.current_password,
      new_password: form.value.new_password,
      new_password_confirmation: form.value.new_password_confirmation,
    })

    if (res.data && res.data.success) {
      if (authStore.user) {
        authStore.user.must_change_password = false
      }
      forceShow.value = false
      uiStore.showToast('Đổi mật khẩu thành công!', 'success')
      form.value.current_password = ''
      form.value.new_password = ''
      form.value.new_password_confirmation = ''
    }
  } catch (err) {
    console.error('Lỗi đổi mật khẩu:', err)
    errorMessage.value = err.response?.data?.message || 'Không thể đổi mật khẩu. Vui lòng kiểm tra lại.'
  } finally {
    loading.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  window.location.reload()
}
</script>

<style scoped>
.animate-in {
  animation: modalIn 0.2s ease-out forwards;
}
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
