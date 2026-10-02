<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import AlertModal from '@/components/AlertModal.vue'
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
  // Lấy trạng thái ban đầu của Night Audit từ backend khi reload trang hoặc login
  try {
    const res = await http.get('/night-audit/check-status')
    if (res.data?.success && res.data?.data) {
      const data = res.data.data
      // CHỈ kích hoạt nếu Night Audit THỰC SỰ ĐANG CHẠY ngay lúc này
      if (data.is_running && !nightAuditStore.isRunning) {
        const latestRun = data.latest_run
        nightAuditStore.handleProgressUpdate({
          username: latestRun?.username || 'Hệ thống',
          step_order: 1,
          percent: 10,
        })
      }
    }
  } catch (e) {
    // Bỏ qua nếu lỗi mạng hoặc endpoint chưa sẵn sàng
  }

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

  <!-- Global Night Audit 18-Step Progress Modal (Hiển thị đồng bộ cho tất cả tài khoản) -->
  <NightAuditProgressModal />
</template>
