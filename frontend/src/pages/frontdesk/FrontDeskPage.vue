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
const activeContentComponent = computed(() => {
  if (currentTab.value === 'checkout') return CheckoutPage
  if (currentTab.value === 'day-close') return DayClosePage
  if (currentTab.value === 'reports') return ReportsPage
  if (currentTab.value === 'history') return ActivityLogTab
  if (currentTab.value === 'search') return GeneralSearchPage
  return RoomMapPage
})

const activeContentKey = computed(() => {
  if (activeContentComponent.value === RoomMapPage) return 'room-map'
  if (activeContentComponent.value === ActivityLogTab) return 'history'
  if (activeContentComponent.value === GeneralSearchPage) return 'search'
  return currentTab.value
})
</script>

<template>
  <KeepAlive :include="['RoomMapPage', 'ActivityLogTab', 'GeneralSearchPage']" :max="3">
    <component :is="activeContentComponent" :key="activeContentKey" />
  </KeepAlive>
  <SplitOldServicesModal v-if="currentTab === 'split-old-services'" @close="$router.replace({ path: '/frontdesk', query: { tab: 'room-map' } })" />
</template>

