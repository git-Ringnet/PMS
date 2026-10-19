<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth-store'

const router = useRouter()
const authStore = useAuthStore()

const username = ref('testuser')
const password = ref('PmsPass@123')
const showPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  if (!username.value || !password.value) {
    errorMessage.value = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.'
    return
  }
  try {
    loading.value = true
    errorMessage.value = ''
    await authStore.login(username.value, password.value)
    router.push('/')
  } catch (err) {
    errorMessage.value = authStore.error || 'Đăng nhập thất bại. Vui lòng kiểm tra lại.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative w-full h-screen flex items-center justify-center bg-[#f0f4f9] overflow-hidden font-sans">
    <!-- Background Dotted Wavy Map (giống HKT PMS) -->
    <img
      src="/login-bg-map.svg"
      alt="Background"
      class="absolute inset-0 w-full h-full object-cover pointer-events-none select-none opacity-90"
    />

    <!-- Subtle Center Lighting -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[520px] h-[520px] bg-sky-100/40 rounded-full blur-[100px] pointer-events-none"></div>

    <!-- Login Container (Form thu gọn chiều ngang gọn gàng, thanh thoát) -->
    <div class="relative z-10 w-full max-w-[360px] p-6 sm:p-7 mx-4 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-900/10 flex flex-col transition-all duration-300">
      
      <!-- Brand Logo & Header -->
      <div class="flex flex-col items-center mb-5 text-center">
        <img
          src="/HKT_LOGO.png"
          alt="PROVISTA PMS"
          class="w-14 h-14 object-contain mb-2.5 drop-shadow-md transition-transform duration-300 hover:scale-105"
        />
        <h1 class="text-lg sm:text-xl font-bold text-slate-800 tracking-tight">PROVISTA PMS</h1>
        <p class="text-xs text-slate-500 mt-1 font-normal">Hệ thống Quản lý Khách sạn Thông minh</p>
      </div>

      <!-- Display Alert Message -->
      <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-600 text-xs flex items-center gap-2 animate-pulse font-medium">
        <svg class="w-4 h-4 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Forms Wrapper -->
      <form @submit.prevent="handleLogin" class="flex flex-col gap-4">
        
        <!-- Standard Username & Password -->
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-700 tracking-wide">Tên Đăng Nhập</label>
            <div class="relative flex items-center">
              <input
                v-model="username"
                type="text"
                placeholder="Nhập tên đăng nhập..."
                class="w-full h-11 px-4 pl-10 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-xs placeholder:text-slate-400 focus:bg-white focus:outline-none focus:border-[#006bdb] focus:ring-2 focus:ring-[#006bdb]/20 transition-all font-normal"
              />
              <svg class="w-4 h-4 text-slate-400 absolute left-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-semibold text-slate-700 tracking-wide">Mật Khẩu</label>
            <div class="relative flex items-center">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                class="w-full h-11 px-4 pl-10 pr-10 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 text-xs placeholder:text-slate-400 focus:bg-white focus:outline-none focus:border-[#006bdb] focus:ring-2 focus:ring-[#006bdb]/20 transition-all font-normal"
              />
              <svg class="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <!-- Nút mắt ấn giữ để hiện mật khẩu -->
              <button
                type="button"
                tabindex="-1"
                class="absolute right-3 text-slate-400 hover:text-slate-600 focus:outline-none transition-colors p-1 flex items-center justify-center cursor-pointer select-none"
                @mousedown.prevent="showPassword = true"
                @mouseup.prevent="showPassword = false"
                @mouseleave="showPassword = false"
                @touchstart.prevent="showPassword = true"
                @touchend.prevent="showPassword = false"
                @touchcancel="showPassword = false"
                title="Nhấn và giữ để xem mật khẩu"
              >
                <!-- Icon mắt mở khi đang giữ và mắt đóng khi bình thường (từ @lucide/vue) -->
                <Eye v-if="showPassword" class="w-4 h-4 text-[#006bdb]" />
                <EyeOff v-else class="w-4 h-4 text-slate-400" />
              </button>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full h-11 bg-[#006bdb] hover:bg-[#005bbd] text-white font-semibold text-xs sm:text-sm tracking-wide rounded-xl cursor-pointer border-none shadow-md shadow-blue-600/20 hover:shadow-lg hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2 mt-3 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ loading ? 'Đang Đăng Nhập...' : 'Đăng Nhập' }}</span>
        </button>

      </form>

    </div>

    <!-- Hotline & Phone (Góc trái dưới giống HKT PMS) -->
    <div class="absolute bottom-5 left-6 sm:bottom-7 sm:left-8 flex items-center gap-2.5 z-10 select-none">
      <div class="w-8 h-8 rounded-full flex items-center justify-center">
        <img src="/phone-icon.svg" alt="Hotline" class="w-5 h-5 object-contain" />
      </div>
      <span class="text-base sm:text-lg font-semibold text-slate-800 tracking-wider font-sans">
        02 586 566 966
      </span>
    </div>

    <!-- Version (Góc phải dưới giống HKT PMS) -->
    <div class="absolute bottom-5 right-6 sm:bottom-7 sm:right-8 text-xs sm:text-[13px] text-slate-400 italic select-none">
      Version 6.0.8
    </div>
  </div>
</template>

<style scoped>
.animate-fade {
  animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
