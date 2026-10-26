import { defineStore } from 'pinia'
import { ref } from 'vue'

const PMS_WORKSPACE_DATABASE = 'pms-report-workspaces'
const PMS_WORKSPACE_STORE = 'workspaces'
const PMS_WORKSPACE_KEY = 'all'

let databasePromise = null
let persistenceQueue = Promise.resolve()

const openWorkspaceDatabase = () => {
  if (typeof indexedDB === 'undefined') return Promise.resolve(null)
  if (databasePromise) return databasePromise

  databasePromise = new Promise((resolve, reject) => {
    const request = indexedDB.open(PMS_WORKSPACE_DATABASE, 1)
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(PMS_WORKSPACE_STORE)) {
        request.result.createObjectStore(PMS_WORKSPACE_STORE)
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })

  return databasePromise
}

const readPmsWorkspaces = async () => {
  const database = await openWorkspaceDatabase()
  if (!database) return {}

  return new Promise((resolve, reject) => {
    const request = database.transaction(PMS_WORKSPACE_STORE, 'readonly')
      .objectStore(PMS_WORKSPACE_STORE)
      .get(PMS_WORKSPACE_KEY)
    request.onsuccess = () => {
      const workspaces = request.result
      resolve(workspaces && typeof workspaces === 'object' && !Array.isArray(workspaces) ? workspaces : {})
    }
    request.onerror = () => reject(request.error)
  })
}

const writePmsWorkspaces = async (workspaces) => {
  const database = await openWorkspaceDatabase()
  if (!database) return

  await new Promise((resolve, reject) => {
    const transaction = database.transaction(PMS_WORKSPACE_STORE, 'readwrite')
    transaction.objectStore(PMS_WORKSPACE_STORE).put(workspaces, PMS_WORKSPACE_KEY)
    transaction.oncomplete = resolve
    transaction.onerror = () => reject(transaction.error)
    transaction.onabort = () => reject(transaction.error)
  })
}

const persistPmsWorkspaces = (workspaces) => {
  const snapshot = JSON.parse(JSON.stringify(workspaces))
  persistenceQueue = persistenceQueue
    .catch(() => undefined)
    .then(() => writePmsWorkspaces(snapshot))
  return persistenceQueue
}

export const useReportStore = defineStore('report', () => {
  // F&B uses these two fields. Keep PMS workspaces separate to avoid collisions.
  const openTabs = ref([])
  const activeTabId = ref('')
  const pmsWorkspaces = ref({})
  let hydrationPromise = null

  const hydratePmsWorkspaces = () => {
    if (hydrationPromise) return hydrationPromise
    hydrationPromise = readPmsWorkspaces()
      .then(workspaces => {
        pmsWorkspaces.value = workspaces
      })
      .catch(() => {
        pmsWorkspaces.value = {}
      })
    return hydrationPromise
  }

  const getPmsWorkspace = key => pmsWorkspaces.value[key] || null
  const savePmsWorkspace = (key, workspace) => {
    if (!key) return
    pmsWorkspaces.value[key] = workspace
    persistPmsWorkspaces(pmsWorkspaces.value)
  }
  const clearPmsWorkspace = (key) => {
    if (!key) return
    delete pmsWorkspaces.value[key]
    persistPmsWorkspaces(pmsWorkspaces.value)
  }

  return {
    openTabs,
    activeTabId,
    pmsWorkspaces,
    hydratePmsWorkspaces,
    getPmsWorkspace,
    savePmsWorkspace,
    clearPmsWorkspace
  }
})
