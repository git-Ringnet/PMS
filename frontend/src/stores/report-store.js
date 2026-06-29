import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useReportStore = defineStore('report', () => {
  const openTabs = ref([])
  const activeTabId = ref('')

  return {
    openTabs,
    activeTabId
  }
})
