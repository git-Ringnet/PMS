<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CheckoutPage from './CheckoutPage.vue'
import RoomMapPage from '@/pages/reservation/RoomMapPage.vue'

const route = useRoute()
const currentTab = computed(() => route.query.tab || 'room-map')
</script>

<template>
  <CheckoutPage v-if="currentTab === 'checkout'" />
  <RoomMapPage v-else />
</template>

