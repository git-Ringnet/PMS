<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import AlertModal from '@/components/AlertModal.vue'
import NightAuditProgressModal from '@/components/NightAuditProgressModal.vue'
import AuthPasswordModal from '@/components/AuthPasswordModal.vue'
import { computed, onMounted, onUnmounted, watch } from 'vue'
import echo from '@/services/echo'
import { useNightAuditStore } from '@/stores/night-audit-store'

const route = useRoute()
const nightAuditStore = useNightAuditStore()

// Trang không dùng layout (như Home, Login). Nếu route chưa load xong (!route.name), mặc định không render layout để tránh gọi API thừa.
const noLayout = computed(() => !route.name || !!route.meta.noLayout)

onMounted(async () => {
  // Lắng nghe qua Echo cho tất cả các tài khoản đang đăng nhập
  if (echo) {
    echo.channel('pms-channel')
      .listen('.night.audit.updated', (e) => {
        if (e.status === 'started' || e.status === 'progress') {
          nightAuditStore.handleProgressUpdate(e.payload)
        } else if (e.status === 'completed') {
          nightAuditStore.handleCompleted(e.payload)
        } else if (e.status === 'failed') {
          nightAuditStore.handleFailed({
            error_message: e.message,
            ...e.payload
          })
        }
      })
  }

  // Khởi tạo kiểm tra trạng thái từ backend (qua endpoint public)
  await nightAuditStore.checkCurrentStatus()
})

// Khi chuyển trang (route change), kiểm tra lại nếu đang trong phiên sang ngày
watch(() => route.path, async () => {
  if (nightAuditStore.isRunning) {
    await nightAuditStore.checkCurrentStatus()
  }
})

onUnmounted(() => {
  if (echo) {
    echo.channel('pms-channel').stopListening('.night.audit.updated')
  }
  nightAuditStore.stopPolling()
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

  <!-- Global Night Audit 18-Step Progress Modal (Hiển thị đồng bộ cho tất cả tài khoản) -->
  <NightAuditProgressModal />

  <!-- Global Authorization Password Modal (CheckAuthorization) -->
  <AuthPasswordModal />
</template>
