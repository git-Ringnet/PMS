<template>
  <Transition name="modal">
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal"></div>

      <!-- Modal Content -->
      <div class="bg-white rounded-xl shadow-2xl w-[90vw] max-w-md flex flex-col relative overflow-hidden animate-in zoom-in-95 duration-200">
        <!-- Header -->
        <div class="px-5 py-4 flex items-center gap-3 border-b border-slate-100 bg-slate-50">
          <div :class="[
            'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
            isWarning ? 'bg-amber-100 text-amber-500' : 'bg-blue-100 text-blue-500'
          ]">
            <!-- Warning Icon -->
            <svg v-if="isWarning" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <!-- Info Icon -->
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="font-bold text-slate-800 text-lg">{{ title }}</h3>
        </div>

        <!-- Body -->
        <div class="p-5">
          <p class="text-slate-600 mb-5 leading-relaxed">
            {{ message }}
          </p>

          <div v-if="requireReason" class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">
              Lý do <span class="text-rose-500">*</span>
            </label>
            <textarea
              v-model="reason"
              rows="3"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
              placeholder="Nhập lý do thực hiện thao tác..."
              @keyup.enter.ctrl="handleConfirm"
            ></textarea>
            <p v-if="showError && !reason.trim()" class="text-xs text-rose-500 font-medium">
              Vui lòng nhập lý do để tiếp tục.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-200 transition-colors"
            @click="closeModal"
          >
            Hủy
          </button>
          <button
            type="button"
            class="px-5 py-2 rounded-lg text-sm font-semibold text-white shadow-sm transition-colors"
            :class="[
              isWarning 
                ? 'bg-rose-500 hover:bg-rose-600' 
                : 'bg-blue-600 hover:bg-blue-700'
            ]"
            @click="handleConfirm"
          >
            {{ confirmText || 'Xác nhận' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: 'Xác nhận'
  },
  message: {
    type: String,
    default: 'Bạn có chắc chắn muốn thực hiện thao tác này?'
  },
  requireReason: {
    type: Boolean,
    default: false
  },
  isWarning: {
    type: Boolean,
    default: false
  },
  confirmText: {
    type: String,
    default: 'Xác nhận'
  }
})

const emit = defineEmits(['close', 'confirm'])

const reason = ref('')
const showError = ref(false)

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    reason.value = ''
    showError.value = false
  }
})

const closeModal = () => {
  emit('close')
}

const handleConfirm = () => {
  if (props.requireReason && !reason.value.trim()) {
    showError.value = true
    return
  }
  emit('confirm', reason.value.trim())
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
