<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CheckoutPage from './CheckoutPage.vue'
import DayClosePage from './DayClosePage.vue'
import RoomMapPage from '@/pages/reservation/RoomMapPage.vue'
import ActivityLogTab from '@/pages/system/components/ActivityLogTab.vue'
import ReportsPage from '@/pages/reports/ReportsPage.vue'
import GeneralSearchPage from './GeneralSearchPage.vue'
import SplitOldServicesModal from './components/SplitOldServicesModal.vue'
const route = useRoute()
const currentTab = computed(() => route.query.tab || 'room-map')
</script>

<template>
  <CheckoutPage v-if="currentTab === 'checkout'" />
  <DayClosePage v-else-if="currentTab === 'day-close'" />
  <ActivityLogTab v-else-if="currentTab === 'history'" />
  <ReportsPage v-else-if="currentTab === 'reports'" />
  <GeneralSearchPage v-else-if="currentTab === 'search'" />
  <RoomMapPage v-else />
  <SplitOldServicesModal v-if="currentTab === 'split-old-services'" @close="$router.replace({ path: '/frontdesk', query: { tab: 'room-map' } })" />
</template>

