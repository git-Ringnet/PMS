<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { Eye, EyeOff, X } from '@lucide/vue'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'

const uiStore = useUiStore()
const passwordInput = ref('')
const showPassword = ref(false)
const passwordInputRef = ref(null)

watch(() => uiStore.authModalState.show, (isOpen) => {
  if (isOpen) {
    passwordInput.value = ''
    showPassword.value = false
    uiStore.authModalState.errorMessage = ''
    nextTick(() => {
      passwordInputRef.value?.focus()
    })
  }
})

async function handleSubmit() {
  const pwd = passwordInput.value.trim()
  if (!pwd) {
    uiStore.authModalState.errorMessage = 'Vui lòng nhập mật khẩu.'
    passwordInputRef.value?.focus()
    return
  }

  uiStore.authModalState.loading = true
  uiStore.authModalState.errorMessage = ''

  try {
    const res = await http.post('/me/verify-password', { password: pwd })
    if (res.data?.success) {
      uiStore.handleAuthSuccess()
    } else {
      uiStore.authModalState.errorMessage = res.data?.message || 'Đăng nhập không thành công'
      passwordInputRef.value?.select()
    }
  } catch (err) {
    uiStore.authModalState.errorMessage = err.response?.data?.message || 'Đăng nhập không thành công'
    passwordInputRef.value?.select()
  } finally {
    uiStore.authModalState.loading = false
  }
}

function handleClose() {
  if (uiStore.authModalState.loading) return
  uiStore.handleAuthCancel()
}

function handleKeyDown(e) {
  if (!uiStore.authModalState.show) return
  if (e.key === 'Escape') {
    handleClose()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="uiStore.authModalState.show"
      class="fixed inset-0 z-[99999999] flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 select-none animate-[fade_0.15s_ease-out]"
      @click="handleClose"
    >
      <div
        class="bg-white rounded-2xl shadow-2xl w-full max-w-[340px] overflow-hidden border border-slate-200 relative p-6 animate-[zoom_0.2s_cubic-bezier(0.34,1.56,0.64,1)]"
        @click.stop
      >
        <!-- Close Button X -->
        <button
          type="button"
          @click="handleClose"
          :disabled="uiStore.authModalState.loading"
          class="absolute top-3.5 right-3.5 text-slate-400 hover:text-rose-500 transition-colors p-1 rounded-md cursor-pointer border-none bg-transparent"
        >
          <X class="w-4 h-4" />
        </button>

        <!-- Brand Logo & Header -->
        <div class="flex flex-col items-center justify-center mb-4 text-center">
          <div class="w-12 h-12 flex items-center justify-center">
            <svg viewBox="0 0 100 100" class="w-11 h-11 drop-shadow-sm" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M50 14 L82 46 L68 60 L50 42 L32 60 L18 46 Z" fill="#38BDF8" />
              <path d="M50 86 L18 54 L32 40 L50 58 L68 40 L82 54 Z" fill="#0284C7" />
            </svg>
          </div>
          <span class="text-[11px] font-black tracking-widest text-[#0284c7] mt-1 uppercase">PROVILEN</span>
        </div>

        <!-- Title -->
        <div class="mb-4">
          <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Đăng nhập</h2>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-3.5">
          <!-- Username (Readonly) -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tên đăng nhập</label>
            <input
              type="text"
              :value="uiStore.authModalState.username"
              disabled
              readonly
              class="w-full h-8 px-2.5 rounded-md border border-slate-300 bg-slate-100 text-slate-700 text-xs font-semibold cursor-not-allowed select-none outline-none"
            />
          </div>

          <!-- Password Input -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Mật khẩu</label>
            <div class="relative flex items-center">
              <input
                ref="passwordInputRef"
                v-model="passwordInput"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Nhập mật khẩu..."
                :disabled="uiStore.authModalState.loading"
                class="w-full h-8 px-2.5 pr-8 rounded-md border border-slate-300 text-slate-800 text-xs focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500/30 transition-all"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                tabindex="-1"
                class="absolute right-2 text-slate-400 hover:text-slate-600 cursor-pointer border-none bg-transparent p-0.5"
              >
                <Eye v-if="showPassword" class="w-3.5 h-3.5" />
                <EyeOff v-else class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Error message -->
            <div
              v-if="uiStore.authModalState.errorMessage"
              class="text-[11px] font-semibold text-rose-600 mt-1.5 animate-in fade-in"
            >
              {{ uiStore.authModalState.errorMessage }}
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button
              type="submit"
              :disabled="uiStore.authModalState.loading"
              class="w-full h-9 rounded-lg bg-[#38bdf8] hover:bg-[#0ea5e9] active:bg-[#0284c7] text-white font-bold text-xs shadow-md transition-all flex items-center justify-center cursor-pointer border-none disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="uiStore.authModalState.loading" class="inline-block animate-spin mr-2">⟳</span>
              <span>Đăng nhập</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
