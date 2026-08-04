<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import AlertModal from '@/components/AlertModal.vue'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import echo from '@/services/echo'
import http from '@/services/http'

const route = useRoute()

// Trang không dùng layout (như Home, Login). Nếu route chưa load xong (!route.name), mặc định không render layout để tránh gọi API thừa.
const noLayout = computed(() => !route.name || !!route.meta.noLayout)

const isNightAuditRunning = ref(false)
const nightAuditMessage = ref('')

onMounted(async () => {
  // Lấy trạng thái ban đầu của Night Audit từ backend khi reload trang
  try {
    const res = await http.get('/hotel-settings')
    if (res.data && res.data.success && res.data.data) {
      isNightAuditRunning.value = !!res.data.data.is_night_audit_running
      if (isNightAuditRunning.value) {
        nightAuditMessage.value = 'Hệ thống đang trong quá trình sang ngày mới. Vui lòng đợi...'
      }
    }
  } catch (e) {
    console.error(e)
  }

  // Lắng nghe qua Echo
  if (echo) {
    echo.channel('pms-channel')
      .listen('.night.audit.updated', (e) => {
        if (e.status === 'started') {
          isNightAuditRunning.value = true
          nightAuditMessage.value = e.message || 'Hệ thống đang tiến hành sang ngày mới...'
        } else if (e.status === 'completed') {
          isNightAuditRunning.value = false
          // Reload để lấy ngày hệ thống mới
          window.location.reload()
        } else if (e.status === 'failed') {
          isNightAuditRunning.value = false
          alert('Chuyển ngày hệ thống thất bại: ' + e.message)
        }
      })
  }
})

onUnmounted(() => {
  if (echo) {
    echo.channel('pms-channel').stopListening('.night.audit.updated')
  }
})
</script>

<template>
  <div v-if="noLayout">
    <router-view />
  </div>
  <MainLayout v-else>
    <router-view />
  </MainLayout>

  <!-- Global UI Elements -->
  <ToastContainer />
  <ConfirmModal />
  <AlertModal />

  <!-- Global System Lock Overlay during Night Audit -->
  <div v-if="isNightAuditRunning" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[9999] flex flex-col items-center justify-center text-white">
    <div class="bg-slate-900/90 p-8 rounded-lg border border-slate-800 shadow-2xl flex flex-col items-center max-w-md text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mb-5"></div>
      <h3 class="text-base font-bold mb-2 tracking-wide">HỆ THỐNG ĐANG SANG NGÀY</h3>
      <p class="text-xs text-slate-400 font-medium leading-relaxed">{{ nightAuditMessage }}</p>
    </div>
  </div>
</template>
