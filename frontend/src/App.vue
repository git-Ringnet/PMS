<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import AlertModal from '@/components/AlertModal.vue'
import { computed } from 'vue'

const route = useRoute()

// Trang không dùng layout (như Home, Login). Nếu route chưa load xong (!route.name), mặc định không render layout để tránh gọi API thừa.
const noLayout = computed(() => !route.name || !!route.meta.noLayout)
</script>

<template>
  <div v-if="noLayout">
    <router-view />
  </div>
  <MainLayout v-else>
    <router-view v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </MainLayout>

  <!-- Global UI Elements -->
  <ToastContainer />
  <ConfirmModal />
  <AlertModal />
</template>
