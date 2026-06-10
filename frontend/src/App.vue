<script setup>
import { useRoute } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { computed } from 'vue'

const route = useRoute()

// Trang chủ không dùng layout
const isHomePage = computed(() => route.name === 'Home')
</script>

<template>
  <div v-if="isHomePage">
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
</template>
