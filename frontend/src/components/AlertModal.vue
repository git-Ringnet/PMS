<template>
  <div v-if="uiStore.alertState.show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-96 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
        <h3 class="font-bold text-slate-700">
          {{ uiStore.alertState.title }}
        </h3>
        <button @click="uiStore.handleAlert()" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 text-slate-600 text-sm">
        {{ uiStore.alertState.message }}
      </div>
      <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
        <button 
          @click="uiStore.handleAlert()" 
          class="px-4 py-1.5 rounded bg-sky-500 text-white hover:bg-sky-600 transition-colors font-medium text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
        >
          {{ uiStore.alertState.confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
</script>
