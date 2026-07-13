<script setup>
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
</script>

<template>
  <Teleport to="body">
    <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[99999999] flex flex-col items-center gap-2 max-w-sm w-full pointer-events-none">
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2 w-full items-center">
        <div
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-3.5 rounded-xl border shadow-lg bg-white/95 backdrop-blur-sm transition-all duration-300 relative overflow-hidden w-full max-w-sm"
        :class="[
          toast.type === 'success' ? 'border-green-200 bg-green-50/95 text-green-800' : '',
          toast.type === 'error' ? 'border-red-200 bg-red-50/95 text-red-800' : '',
          toast.type === 'warning' ? 'border-amber-200 bg-amber-50/95 text-amber-800' : '',
          toast.type === 'info' ? 'border-blue-200 bg-blue-50/95 text-blue-800' : '',
        ]"
      >
        <!-- Icon -->
        <span class="shrink-0 mt-0.5">
          <!-- Success SVG -->
          <svg v-if="toast.type === 'success'" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <!-- Error SVG -->
          <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <!-- Warning SVG -->
          <svg v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
          </svg>
          <!-- Info SVG -->
          <svg v-else class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </span>

        <!-- Message -->
        <div class="flex-1 text-xs font-semibold leading-relaxed">
          {{ toast.message }}
        </div>

        <!-- Dismiss button -->
        <button
          @click="uiStore.removeToast(toast.id)"
          class="shrink-0 p-0.5 rounded hover:bg-black/5 text-slate-400 hover:text-slate-600 transition-colors border-none bg-transparent cursor-pointer"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.18s cubic-bezier(0.25, 1, 0.5, 1);
}
.toast-leave-active {
  transition: all 0.15s cubic-bezier(0.25, 1, 0.5, 1);
}
.toast-enter-from {
  transform: translateY(-15px) scale(0.98);
  opacity: 0;
}
.toast-leave-to {
  transform: translateY(-8px) scale(0.98);
  opacity: 0;
}
</style>
