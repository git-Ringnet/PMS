<script setup>
// Dòng 328: chọn vai trò được xem một báo cáo tại từng chi nhánh (đồng bộ với màn Phân quyền theo rule, phân hệ REPORT).
import { computed, ref, watch } from 'vue'
import http from '@/services/http'
import { fetchSystemBranchesList } from '@/services/company-service'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import { ShieldCheck, X } from '@lucide/vue'

const props = defineProps({ report: { type: Object, default: null } })
const emit = defineEmits(['close'])
const authStore = useAuthStore()
const uiStore = useUiStore()

const branches = ref([])
const branchId = ref('')
const roles = ref([])
const permissionCode = ref('')
const loading = ref(false)
const saving = ref(false)
const grantedCount = computed(() => roles.value.filter(role => role.granted).length)
const allChecked = computed(() => roles.value.length > 0 && grantedCount.value === roles.value.length)

const loadRoles = async () => {
  if (!props.report || !branchId.value) return
  loading.value = true
  try {
    const response = await http.get(`/report-definitions/${props.report.id}/role-access`, { params: { system_branch_id: branchId.value } })
    roles.value = response.data.data.roles || []
    permissionCode.value = response.data.data.permission_code
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải quyền xem báo cáo', 'error')
  } finally {
    loading.value = false
  }
}

watch(() => props.report, async (report) => {
  if (!report) return
  roles.value = []
  if (!branches.value.length) {
    try {
      const response = await fetchSystemBranchesList()
      branches.value = response.data.data || []
    } catch {
      branches.value = []
    }
  }
  branchId.value = branches.value.find(item => item.id === authStore.activeBranch?.id)?.id || branches.value[0]?.id || ''
  await loadRoles()
}, { immediate: true })

const toggleAll = (checked) => roles.value.forEach(role => { role.granted = checked })

const save = async () => {
  saving.value = true
  try {
    const response = await http.put(`/report-definitions/${props.report.id}/role-access`, {
      system_branch_id: branchId.value,
      role_ids: roles.value.filter(role => role.granted).map(role => role.id)
    })
    roles.value = response.data.data.roles || roles.value
    uiStore.showToast('Đã lưu quyền xem báo cáo', 'success')
    emit('close')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu quyền xem báo cáo', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="report" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4" @mousedown.self="emit('close')">
    <div class="flex max-h-[80vh] w-full max-w-md flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
      <header class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <div class="min-w-0">
          <h3 class="flex items-center gap-1.5 text-sm font-black text-slate-800"><ShieldCheck class="h-4 w-4 text-emerald-600" /> Quyền xem báo cáo</h3>
          <p class="mt-0.5 truncate text-xs font-semibold text-slate-600">{{ report.name }}</p>
          <p class="text-[10px] font-mono text-slate-400">{{ permissionCode }}</p>
        </div>
        <button class="rounded-lg border-none bg-slate-100 p-1.5 text-slate-500" @click="emit('close')"><X class="h-4 w-4" /></button>
      </header>

      <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2">
        <span class="text-[11px] font-bold text-slate-500">Chi nhánh:</span>
        <select v-model="branchId" class="flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs" @change="loadRoles">
          <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
        </select>
      </div>

      <div class="flex-1 overflow-y-auto px-4 py-2">
        <div v-if="loading" class="py-8 text-center text-xs text-slate-400">Đang tải...</div>
        <template v-else>
          <label class="flex items-center gap-2 border-b border-slate-100 py-2 text-xs font-bold text-slate-700">
            <input type="checkbox" class="h-4 w-4 accent-sky-500" :checked="allChecked" @change="toggleAll($event.target.checked)" />
            Chọn tất cả vai trò
            <span class="ml-auto text-[10px] font-semibold text-slate-400">{{ grantedCount }}/{{ roles.length }} được xem</span>
          </label>
          <label v-for="role in roles" :key="role.id" class="flex cursor-pointer items-center gap-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
            <input v-model="role.granted" type="checkbox" class="h-4 w-4 accent-sky-500" />
            <span class="flex-1">{{ role.name }} <small class="text-[10px] text-slate-400">{{ role.code }}</small></span>
            <span v-if="!role.is_active" class="text-[10px] text-slate-400">ngừng dùng</span>
          </label>
          <p class="mt-2 text-[10px] text-slate-400">Super Administrator luôn xem được mọi báo cáo. Có thể chỉnh tương tự ở Phân quyền theo rule, phân hệ REPORT.</p>
        </template>
      </div>

      <footer class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
        <button class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600" @click="emit('close')">Đóng</button>
        <button :disabled="saving || loading" class="rounded-lg border-none px-4 py-1.5 text-xs font-black text-white disabled:opacity-50"
          :style="{ background: authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)' }" @click="save">Lưu quyền</button>
      </footer>
    </div>
  </div>
</template>
