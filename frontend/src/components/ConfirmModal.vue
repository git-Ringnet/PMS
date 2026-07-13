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
      class="fixed inset-0 z-[1000000] flex items-center justify-center bg-black/40 backdrop-blur-[2px] p-4 animate-[fade_0.2s_ease-out]"
      @click="uiStore.handleConfirm(false)"
    >
      <div
        class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-[zoom_0.25s_cubic-bezier(0.34,1.56,0.64,1)] border border-slate-200"
        @click.stop
      >
        <!-- Content -->
        <div class="p-6 text-center">
          <!-- Warning Alert Icon -->
          <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-amber-200">
            <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>

          <!-- Title -->
          <h3 class="text-base font-extrabold text-slate-800 mb-1.5 select-none">
            {{ uiStore.confirmState.title }}
          </h3>

          <!-- Message -->
          <p class="text-xs text-slate-500 font-semibold leading-relaxed px-2 select-none">
            {{ uiStore.confirmState.message }}
          </p>
        </div>

        <!-- Action buttons -->
        <div class="flex border-t border-slate-100 bg-slate-50/50">
          <button
            @click="uiStore.handleConfirm(false)"
            class="flex-1 px-4 py-3 bg-transparent text-slate-600 hover:bg-slate-100/50 hover:text-slate-800 transition-colors cursor-pointer border-none border-r border-slate-100 text-xs font-bold"
          >
            {{ uiStore.confirmState.cancelText }}
          </button>
          
          <button
            @click="uiStore.handleConfirm(true)"
            class="flex-1 px-4 py-3 bg-transparent text-blue-600 hover:bg-slate-100/50 hover:text-blue-700 transition-colors cursor-pointer border-none text-xs font-extrabold focus:outline-none"
          >
            {{ uiStore.confirmState.confirmText }}
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
