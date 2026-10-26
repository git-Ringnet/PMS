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
const cachedContentComponent = computed(() => {
  if (currentTab.value === 'history') return ActivityLogTab
  if (currentTab.value === 'search') return GeneralSearchPage
  return RoomMapPage
})
</script>

<template>
  <CheckoutPage v-if="currentTab === 'checkout'" />
  <DayClosePage v-else-if="currentTab === 'day-close'" />
  <ReportsPage v-else-if="currentTab === 'reports'" />
  <KeepAlive><component v-if="!['checkout', 'day-close', 'reports'].includes(currentTab)" :is="cachedContentComponent" /></KeepAlive>
  <SplitOldServicesModal v-if="currentTab === 'split-old-services'" @close="$router.replace({ path: '/frontdesk', query: { tab: 'room-map' } })" />
</template>

