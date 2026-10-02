<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import AlertModal from '@/components/AlertModal.vue'
import ForceChangePasswordModal from '@/components/ForceChangePasswordModal.vue'
import NightAuditProgressModal from '@/components/NightAuditProgressModal.vue'
import { computed, onMounted, onUnmounted } from 'vue'
import echo from '@/services/echo'
import http from '@/services/http'
import { useNightAuditStore } from '@/stores/night-audit-store'

const route = useRoute()
const nightAuditStore = useNightAuditStore()

// Trang không dùng layout (như Home, Login). Nếu route chưa load xong (!route.name), mặc định không render layout để tránh gọi API thừa.
const noLayout = computed(() => !route.name || !!route.meta.noLayout)

onMounted(async () => {
  // Lấy trạng thái ban đầu của Night Audit từ backend khi reload trang
  try {
    const res = await http.get('/hotel-settings')
    if (res.data && res.data.success && res.data.data) {
      if (res.data.data.is_night_audit_running && !nightAuditStore.isRunning) {
        nightAuditStore.handleRemoteStarted({ username: 'Hệ thống' })
      }
    }
  } catch (e) {
    console.error(e)
  }

  // Lắng nghe qua Echo cho tất cả các tài khoản đang đăng nhập
  if (echo) {
    echo.channel('pms-channel')
      .listen('.night.audit.updated', (e) => {
        if (e.status === 'started') {
          nightAuditStore.handleRemoteStarted(e.payload)
        } else if (e.status === 'completed') {
          nightAuditStore.handleRemoteCompleted(e.payload)
        } else if (e.status === 'failed') {
          nightAuditStore.handleRemoteFailed({
            error_message: e.message,
            ...e.payload
          })
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
  <ForceChangePasswordModal />

  <!-- Global Night Audit 18-Step Progress Modal (Hiển thị đồng bộ cho tất cả tài khoản) -->
  <NightAuditProgressModal />
</template>
