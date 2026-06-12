<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('test@example.com')
const password = ref('password')
const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  if (!email.value || !password.value) {
    errorMessage.value = 'Vui lòng nhập đầy đủ email và mật khẩu.'
    return
  }
  try {
    loading.value = true
    errorMessage.value = ''
    await authStore.login(email.value, password.value)
    router.push('/')
  } catch (err) {
    errorMessage.value = authStore.error || 'Đăng nhập thất bại. Vui lòng kiểm tra lại.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative w-full h-screen flex items-center justify-center bg-slate-900 overflow-hidden font-sans">
    <!-- Visual Gradient Background Blobs -->
    <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] rounded-full bg-gradient-to-tr from-[#0ea5e9]/20 to-[#6366f1]/20 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[600px] h-[600px] rounded-full bg-gradient-to-bl from-[#d946ef]/15 to-[#3b82f6]/20 blur-[120px] pointer-events-none"></div>

    <!-- Background Grid Lines -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.15),rgba(255,255,255,0))]"></div>

    <!-- Login Container -->
    <div class="relative z-10 w-full max-w-[450px] p-8 mx-4 bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl flex flex-col transition-all duration-500">
      
      <!-- Brand Logo & Header -->
      <div class="flex flex-col items-center mb-8 text-center">
        <div class="w-14 h-14 bg-gradient-to-br from-[#0ea5e9] to-[#2563eb] flex items-center justify-center rounded-2xl rotate-45 transform-gpu overflow-hidden shadow-lg shadow-blue-500/20 mb-4 transition-transform hover:scale-105">
          <div class="w-7 h-7 bg-white -rotate-45 transform-gpu flex items-center justify-center rounded-sm">
            <svg class="w-4 h-4 text-[#0ea5e9]" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        <h1 class="text-2xl font-black text-white tracking-tight">PMS SYSTEM</h1>
        <p class="text-xs text-slate-400 mt-1">Hệ thống Quản lý Khách sạn Thông minh</p>
      </div>

      <!-- Display Alert Message -->
      <div v-if="errorMessage" class="mb-5 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-200 text-xs flex items-center gap-2 animate-pulse">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Forms Wrapper -->
      <form @submit.prevent="handleLogin" class="flex flex-col gap-4">
        
        <!-- Standard Email & Password -->
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-[11px] font-bold text-slate-400 tracking-wider">EMAIL ĐĂNG NHẬP</label>
            <div class="relative flex items-center">
              <input
                v-model="email"
                type="email"
                placeholder="test@example.com"
                class="w-full h-11 px-4 pl-10 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-[#0ea5e9] focus:ring-1 focus:ring-[#0ea5e9]/30 transition-all"
              />
              <svg class="w-4 h-4 text-slate-500 absolute left-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
              </svg>
            </div>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="text-[11px] font-bold text-slate-400 tracking-wider">MẬT KHẨU</label>
            <div class="relative flex items-center">
              <input
                v-model="password"
                type="password"
                placeholder="••••••••"
                class="w-full h-11 px-4 pl-10 rounded-xl bg-white/5 border border-white/10 text-white text-xs placeholder-slate-500 focus:outline-none focus:border-[#0ea5e9] focus:ring-1 focus:ring-[#0ea5e9]/30 transition-all"
              />
              <svg class="w-4 h-4 text-slate-500 absolute left-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full h-11 bg-gradient-to-r from-[#0ea5e9] to-[#2563eb] hover:from-[#0284c7] hover:to-[#1d4ed8] text-white font-extrabold text-xs tracking-wider rounded-xl cursor-pointer border-none shadow-lg shadow-blue-500/10 hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-2 mt-4 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>{{ loading ? 'ĐANG ĐĂNG NHẬP...' : 'XÁC THỰC & ĐĂNG NHẬP' }}</span>
        </button>

      </form>

      <!-- Test Account Info -->
      <div class="mt-6 pt-5 border-t border-white/10 flex flex-col gap-1 items-center">
        <span class="text-[10px] font-bold text-slate-500 tracking-wider">TÀI KHOẢN DÙNG THỬ (TEST)</span>
        <span class="text-xs text-slate-300 font-semibold tracking-wide bg-white/5 px-3 py-1.5 rounded-lg border border-white/5 mt-1 select-all cursor-pointer" title="Click để chọn">
          test@example.com / password
        </span>
      </div>

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
