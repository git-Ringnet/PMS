<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

function handleKeyDown(e) {
  if (!uiStore.confirmState.show) return
  if (e.key === 'Escape') {
    uiStore.handleConfirm(false)
  } else if (e.key === 'Enter') {
    uiStore.handleConfirm(true)
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
      v-if="uiStore.confirmState.show"
      class="fixed inset-0 z-[9999999] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 animate-[fade_0.2s_ease-out]"
      @click="uiStore.handleConfirm(false)"
    >
      <div
        class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-[380px] overflow-hidden animate-[zoom_0.25s_cubic-bezier(0.34,1.56,0.64,1)] border border-slate-200/90 dark:border-slate-800"
        @click.stop
      >
        <!-- Modal Content -->
        <div class="p-6 text-center">
          <!-- Icon Container -->
          <div class="relative mx-auto w-14 h-14 flex items-center justify-center mb-3">
            <div class="absolute inset-0 rounded-full bg-amber-400/20 animate-ping opacity-25"></div>
            <div class="relative w-12 h-12 rounded-full bg-amber-50 border-2 border-amber-200/80 flex items-center justify-center shadow-xs">
              <i class="fa-solid fa-triangle-exclamation text-amber-500 text-xl"></i>
            </div>
          </div>

          <!-- Title -->
          <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 tracking-tight">
            {{ uiStore.confirmState.title || 'Xác nhận' }}
          </h3>

          <!-- Message -->
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed mt-2 px-1">
            {{ uiStore.confirmState.message }}
          </p>
        </div>

        <!-- Action buttons -->
        <div class="px-6 pb-6 pt-1 flex items-center gap-3">
          <button
            type="button"
            @click="uiStore.handleConfirm(false)"
            class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-xl transition text-xs cursor-pointer border-none shadow-xs active:scale-98"
          >
            {{ uiStore.confirmState.cancelText || 'Hủy bỏ' }}
          </button>
          
          <button
            type="button"
            @click="uiStore.handleConfirm(true)"
            class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold rounded-xl transition text-xs cursor-pointer border-none shadow-md shadow-blue-500/25 active:scale-98 flex items-center justify-center gap-1.5"
          >
            <span>{{ uiStore.confirmState.confirmText || 'Đồng ý' }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
@keyframes fade {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes zoom {
  from {
    opacity: 0;
    transform: scale(0.92) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
