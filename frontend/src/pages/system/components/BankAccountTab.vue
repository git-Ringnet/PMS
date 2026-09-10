<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  createBankAccount,
  deleteBankAccount,
  fetchBankAccountLookups,
  fetchBankAccounts,
  updateBankAccount,
} from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const accounts = ref([])
const search = ref('')
const group = ref(false)
const showForm = ref(false)
const formTab = ref('bank')
const editingId = ref(null)
const accountingAccounts = ref([])
const currencies = ref([])
const originalFormSnapshot = ref('')

const sortField = ref('id')
const sortDir = ref('desc')

const columns = ref([
  { id: 'code', label: 'Mã Tài Khoản', visible: true, sortable: true },
  { id: 'bank_account_number', label: 'Số Tài Khoản Ngân Hàng', visible: true, sortable: true },
  { id: 'accounting_account', label: 'Tài Khoản', visible: true, sortable: true },
  { id: 'currency_code', label: 'Mã Tiền Tệ', visible: true, sortable: true },
  { id: 'bank_name', label: 'Tên Ngân Hàng', visible: true, sortable: true },
  { id: 'opened_on', label: 'Ngày Mở', visible: true, sortable: true },
  { id: 'closed_on', label: 'Ngày Đóng', visible: true, sortable: true },
  { id: 'description', label: 'Diễn Giải', visible: true, sortable: false },
])

const isColumnSelectorOpen = ref(false)
const toggleColumnSelector = (e) => {
  e.stopPropagation()
  isColumnSelectorOpen.value = !isColumnSelectorOpen.value
}
const closePopovers = () => {
  isColumnSelectorOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', closePopovers)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closePopovers)
})

const isColumnVisible = (colId) => {
  const col = columns.value.find(c => c.id === colId)
  return col ? col.visible : true
}

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'desc'
  }
}

const sortedAccounts = computed(() => {
  let result = [...accounts.value]
  if (sortField.value) {
    const field = sortField.value
    const dir = sortDir.value === 'asc' ? 1 : -1
    result.sort((a, b) => {
      let valA = a[field] ?? ''
      let valB = b[field] ?? ''
      if (typeof valA === 'string') valA = valA.toLowerCase()
      if (typeof valB === 'string') valB = valB.toLowerCase()
      if (valA < valB) return -1 * dir
      if (valA > valB) return 1 * dir
      return 0
    })
  }
  return result
})

const blankForm = (isIntermediary = group.value) => ({
  code: '',
  bank_account_number: '',
  accounting_account: '',
  currency_code: '',
  bank_name: '',
  opened_on: '',
  closed_on: '',
  description: '',
  is_intermediary: Boolean(isIntermediary),
  is_active: true,
})

const form = ref(blankForm())
const isEditing = computed(() => Boolean(editingId.value))
const canManage = computed(() => authStore.isSuperAdmin || authStore.hasPermission('system.user.manage'))
const selectedAccountingAccount = computed(() =>
  accountingAccounts.value.find(item => item.code === form.value.accounting_account)
)
const selectedCurrency = computed(() =>
  currencies.value.find(item => item.code === form.value.currency_code)
)

async function loadAccounts() {
  loading.value = true
  try {
    const response = await fetchBankAccounts({
      is_intermediary: group.value,
      search: search.value.trim() || undefined,
    })
    accounts.value = response.data?.data || response.data || []
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tải tài khoản ngân hàng.', 'error')
  } finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const response = await fetchBankAccountLookups()
    const data = response.data?.data || {}
    accountingAccounts.value = data.accounting_accounts || []
    currencies.value = data.currencies || []
  } catch (error) {
    accountingAccounts.value = []
    currencies.value = []
    console.error('Không thể tải danh mục kế toán/tiền tệ:', error)
  }
}

onMounted(async () => {
  await Promise.all([loadAccounts(), loadLookups()])
})

watch(group, () => {
  if (!showForm.value) loadAccounts()
})

function switchGroup(value) {
  group.value = value
}

function handleClearSearch() {
  search.value = ''
  loadAccounts()
}

function openCreate() {
  if (!canManage.value) return
  editingId.value = null
  formTab.value = 'bank'
  form.value = blankForm(group.value)
  originalFormSnapshot.value = JSON.stringify(form.value)
  showForm.value = true
}

function openEdit(account) {
  editingId.value = account.id
  formTab.value = 'bank'
  form.value = {
    ...blankForm(account.is_intermediary),
    ...account,
    opened_on: account.opened_on ? String(account.opened_on).split('T')[0] : '',
    closed_on: account.closed_on ? String(account.closed_on).split('T')[0] : '',
    description: account.description || '',
    is_intermediary: Boolean(account.is_intermediary),
    is_active: account.is_active !== undefined ? Boolean(account.is_active) : true,
  }
  originalFormSnapshot.value = JSON.stringify(form.value)
  showForm.value = true
}

const isFormDirty = computed(() => showForm.value && originalFormSnapshot.value !== JSON.stringify(form.value))

async function closeForm() {
  if (saving.value) return
  if (isFormDirty.value) {
    const confirmed = await uiStore.confirm({
      title: 'Hủy thay đổi',
      message: 'Bạn có chắc chắn muốn đóng form và bỏ các thay đổi chưa lưu?',
      confirmText: 'Bỏ thay đổi',
      cancelText: 'Tiếp tục chỉnh sửa',
    })
    if (!confirmed) return
  }
  showForm.value = false
}

async function save() {
  if (!canManage.value) return
  const payload = {
    code: form.value.code.trim(),
    bank_account_number: String(form.value.bank_account_number || '').trim(),
    accounting_account: form.value.accounting_account || null,
    currency_code: form.value.currency_code || null,
    bank_name: form.value.bank_name.trim(),
    opened_on: form.value.opened_on || null,
    closed_on: form.value.closed_on || null,
    description: form.value.description.trim() || null,
    is_intermediary: Boolean(form.value.is_intermediary),
    is_active: Boolean(form.value.is_active),
  }
  if (!payload.code || !payload.bank_account_number || !payload.bank_name) {
    uiStore.showToast('Vui lòng nhập mã, số tài khoản và tên ngân hàng.', 'warning')
    formTab.value = 'bank'
    return
  }

  saving.value = true
  try {
    if (editingId.value) {
      await updateBankAccount(editingId.value, payload)
      uiStore.showToast('Đã cập nhật tài khoản ngân hàng.', 'success')
    } else {
      await createBankAccount(payload)
      uiStore.showToast('Đã thêm tài khoản ngân hàng.', 'success')
    }
    showForm.value = false
    await loadAccounts()
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể lưu tài khoản ngân hàng.', 'error')
  } finally {
    saving.value = false
  }
}

function nextFormTab() {
  if (!form.value.code.trim() || !String(form.value.bank_account_number || '').trim() || !form.value.bank_name.trim()) {
    uiStore.showToast('Vui lòng nhập mã, số tài khoản và tên ngân hàng trước khi tiếp tục.', 'warning')
    formTab.value = 'bank'
    return
  }
  formTab.value = 'tax'
}

function openCurrencyCatalog() {
  router.push({ path: '/config', query: { view: 'system', tab: 'TIỀN TỆ' } })
}

async function remove(account) {
  if (!canManage.value) return
  const confirmed = await uiStore.confirm({
    title: 'Xóa tài khoản ngân hàng',
    message: 'Bạn có chắc chắn muốn xóa tài khoản ' + account.code + '?',
    confirmText: 'Xóa',
    cancelText: 'Hủy',
  })
  if (!confirmed) return

  try {
    await deleteBankAccount(account.id)
    uiStore.showToast('Đã xóa tài khoản ngân hàng.', 'success')
    await loadAccounts()
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể xóa tài khoản ngân hàng.', 'error')
  }
}

function formatDate(value) {
  if (!value) return ''
  const parts = String(value).split('T')[0].split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return String(date.getDate()).padStart(2, '0') + '/' +
    String(date.getMonth() + 1).padStart(2, '0') + '/' + date.getFullYear()
}
</script>

<template>
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs select-text">
    <!-- Toolbar (Matching BranchManageTab & EmployeeTab) -->
    <div class="flex items-center justify-between mb-3 w-full gap-4 shrink-0">
      <!-- Left: Search Box -->
      <div class="flex items-center gap-1.5 flex-1 max-w-lg relative">
        <div class="relative flex-1 flex items-center">
          <input 
            v-model="search" 
            type="text" 
            placeholder="Tìm kiếm mã, số tài khoản, ngân hàng..." 
            class="w-full border border-slate-200 rounded-md p-1.5 pl-7 pr-6 focus:outline-sky-500 text-xs font-semibold text-slate-700 bg-white h-[30px]" 
            @keyup.enter="loadAccounts"
          />
          <svg class="w-3.5 h-3.5 absolute left-2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <button 
            v-if="search" 
            @click="handleClearSearch" 
            class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer text-xs"
          >
            ✕
          </button>
        </div>
        <button 
          @click="loadAccounts"
          class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center justify-center transition-colors shadow-xs h-[30px] whitespace-nowrap"
        >
          Tìm Kiếm
        </button>
      </div>

      <!-- Right: Subtabs & Action Buttons -->
      <div class="flex items-center gap-2 select-none">
        <!-- Subtabs toggle pills -->
        <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 mr-2">
          <button
            type="button"
            @click="switchGroup(false)"
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors cursor-pointer border-none"
            :class="!group ? 'bg-white text-sky-700 shadow-xs' : 'text-slate-500 hover:text-slate-800 bg-transparent'"
          >
            1. Ngân Hàng Thanh Toán
          </button>
          <button
            type="button"
            @click="switchGroup(true)"
            class="px-3 py-1 text-xs font-bold rounded-md transition-colors cursor-pointer border-none"
            :class="group ? 'bg-white text-sky-700 shadow-xs' : 'text-slate-500 hover:text-slate-800 bg-transparent'"
          >
            2. Ngân Hàng Trung Gian
          </button>
        </div>

        <!-- Add Button -->
        <button 
          v-if="canManage"
          @click="openCreate"
          class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md text-xs font-bold border-none cursor-pointer flex items-center gap-1 shadow-xs transition-colors h-[30px]"
        >
          <span class="inline-flex items-center justify-center border border-white rounded-full w-3.5 h-3.5 text-center text-[10px] font-extrabold leading-none">+</span>
          Thêm
        </button>

        <!-- Help Button -->
        <button 
          type="button"
          class="w-[30px] h-[30px] hover:bg-slate-50 text-slate-400 hover:text-slate-600 border border-slate-200 rounded flex items-center justify-center bg-white cursor-pointer transition-colors shrink-0" 
          title="Trợ giúp"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
          </svg>
        </button>

        <!-- Column Config Dropdown Trigger -->
        <div class="relative popover-container">
          <button 
            @click="toggleColumnSelector"
            class="w-[30px] h-[30px] hover:bg-slate-50 text-slate-400 hover:text-slate-600 border border-slate-200 rounded flex items-center justify-center bg-white cursor-pointer transition-colors shrink-0"
            title="Thiết lập cột"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.645-.869l.214-1.28z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>

          <!-- Column Popover Selector -->
          <div v-if="isColumnSelectorOpen" class="absolute right-0 top-full mt-1.5 z-40 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px]" @click.stop>
            <div class="font-bold text-slate-700 border-b border-slate-100 pb-1.5 mb-1.5 uppercase tracking-wider text-[10px]">
              Hiển thị cột
            </div>
            <div class="flex flex-col gap-1.5">
              <label v-for="c in columns" :key="c.id" class="flex items-center gap-2 cursor-pointer font-semibold text-slate-700">
                <input type="checkbox" v-model="c.visible" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5" />
                <span>{{ c.label }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-2xs flex-1 max-h-full">
      <table class="w-full text-left border-collapse text-xs">
        <thead class="bg-slate-100/90 border-b border-slate-200 text-slate-700 font-bold select-none h-9 sticky top-0 z-10">
          <tr>
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase text-center w-12 select-none">STT</th>
            <th 
              v-for="col in columns" 
              :key="col.id"
              v-show="col.visible"
              @click="col.sortable ? toggleSort(col.id) : null"
              class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase select-none transition-colors relative"
              :class="{'cursor-pointer hover:bg-slate-200': col.sortable}"
            >
              <div class="flex items-center gap-1.5">
                <span>{{ col.label }}</span>
                <span v-if="col.sortable && sortField === col.id" class="text-[9px] text-sky-500">
                  {{ sortDir === 'asc' ? '▲' : '▼' }}
                </span>
              </div>
            </th>
            <th class="p-2 border-r border-slate-200 text-slate-700 font-bold text-xs uppercase text-center w-16 select-none">Xóa</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.filter(c => c.visible).length + 2" class="p-8 text-center text-slate-400 text-xs font-semibold">Đang tải dữ liệu...</td>
          </tr>
          <tr v-else-if="sortedAccounts.length === 0">
            <td :colspan="columns.filter(c => c.visible).length + 2" class="p-8 text-center text-slate-400 text-xs font-semibold italic">Chưa có tài khoản ngân hàng.</td>
          </tr>
          <tr
            v-for="(account, index) in sortedAccounts"
            v-else
            :key="account.id"
            @dblclick="openEdit(account)"
            class="border-b border-slate-200 hover:bg-[#bdecfe]/50 cursor-pointer h-9 transition-colors font-medium select-text"
            :class="account.is_active === false ? 'text-slate-400' : ''"
            title="Nhấp đúp để sửa"
          >
            <td class="p-2 border-r border-slate-200 text-center text-slate-500 font-normal select-text">{{ index + 1 }}</td>
            <td v-show="isColumnVisible('code')" class="p-2 border-r border-slate-200 font-bold text-sky-700 select-text">
              <button 
                type="button" 
                @click.stop="openEdit(account)" 
                class="border-none bg-transparent p-0 font-bold text-inherit hover:underline cursor-pointer select-text"
              >
                {{ account.code }}
              </button>
            </td>
            <td v-show="isColumnVisible('bank_account_number')" class="p-2 border-r border-slate-200 font-mono text-slate-700 font-semibold select-text">{{ account.bank_account_number }}</td>
            <td v-show="isColumnVisible('accounting_account')" class="p-2 border-r border-slate-200 text-slate-600 font-normal select-text">{{ account.accounting_account || '-' }}</td>
            <td v-show="isColumnVisible('currency_code')" class="p-2 border-r border-slate-200 text-slate-600 font-normal select-text">{{ account.currency_code || '-' }}</td>
            <td v-show="isColumnVisible('bank_name')" class="p-2 border-r border-slate-200 text-slate-700 font-bold select-text">{{ account.bank_name }}</td>
            <td v-show="isColumnVisible('opened_on')" class="p-2 border-r border-slate-200 text-slate-600 font-normal select-text">{{ formatDate(account.opened_on) || '-' }}</td>
            <td v-show="isColumnVisible('closed_on')" class="p-2 border-r border-slate-200 text-slate-600 font-normal select-text">{{ formatDate(account.closed_on) || '-' }}</td>
            <td v-show="isColumnVisible('description')" class="p-2 border-r border-slate-200 text-slate-600 font-normal truncate max-w-[240px] select-text" :title="account.description || ''">
              {{ account.description || '-' }}
            </td>
            <td class="p-2 border-r border-slate-200 text-center select-none">
              <template v-if="canManage">
                <button 
                  type="button" 
                  @click.stop="remove(account)" 
                  class="p-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded cursor-pointer border-none transition-colors inline-flex items-center justify-center w-6 h-6" 
                  title="Xóa tài khoản"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Add/Edit (Matching BranchManageTab style) -->
    <div 
      v-if="showForm" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs" 
      @click.self="closeForm"
    >
      <div class="bg-white rounded-lg w-full max-w-2xl shadow-2xl overflow-hidden border border-slate-100 animate-in select-text">
        <!-- Header -->
        <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white border-b border-slate-200 select-none">
          <h2 class="text-sm font-bold tracking-wide">
            {{ isEditing ? (canManage ? 'Chỉnh Sửa Tài Khoản Ngân Hàng' : 'Xem Tài Khoản Ngân Hàng') : 'Thêm Tài Khoản Ngân Hàng' }}
          </h2>
          <button @click="closeForm" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
        </div>

        <!-- Subtabs -->
        <div class="flex border-b border-slate-200 bg-slate-50 px-5 pt-2 select-none">
          <button 
            type="button" 
            @click="formTab = 'bank'" 
            class="border-b-2 px-4 pb-2 text-xs font-bold transition-colors cursor-pointer border-none bg-transparent" 
            :class="formTab === 'bank' ? 'border-[#0ea5e9] text-[#0ea5e9]' : 'border-transparent text-slate-500 hover:text-slate-700'"
          >
            1. Tài Khoản Ngân Hàng
          </button>
          <button 
            type="button" 
            @click="formTab = 'tax'" 
            class="border-b-2 px-4 pb-2 text-xs font-bold transition-colors cursor-pointer border-none bg-transparent" 
            :class="formTab === 'tax' ? 'border-[#0ea5e9] text-[#0ea5e9]' : 'border-transparent text-slate-500 hover:text-slate-700'"
          >
            2. Thông Tin Hạch Toán Thuế/Phí Cà Thẻ
          </button>
        </div>

        <!-- Form Body -->
        <div class="p-5 flex flex-col gap-3 text-xs max-h-[75vh] overflow-y-auto">
          <div v-if="formTab === 'bank'" class="grid grid-cols-2 gap-3">
            <!-- Mã Tài Khoản -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Mã Tài Khoản Ngân Hàng <span class="text-rose-500">*</span></label>
              <input 
                v-model="form.code" 
                type="text" 
                maxlength="50" 
                placeholder="Ví dụ: VCB_01..." 
                class="border border-slate-200 bg-slate-50 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage || isEditing" 
              />
            </div>

            <!-- Số Tài Khoản -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Số Tài Khoản Ngân Hàng <span class="text-rose-500">*</span></label>
              <input 
                v-model="form.bank_account_number" 
                type="text" 
                maxlength="255" 
                placeholder="Nhập số tài khoản..." 
                class="border border-slate-200 rounded-md p-1.5 font-mono font-semibold text-xs focus:outline-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage" 
              />
            </div>

            <!-- Tên Ngân Hàng -->
            <div class="flex flex-col gap-1 col-span-2">
              <label class="font-bold text-slate-700">Tên Ngân Hàng <span class="text-rose-500">*</span></label>
              <input 
                v-model="form.bank_name" 
                type="text" 
                maxlength="255" 
                placeholder="Ví dụ: Vietcombank - CN Hà Nội..." 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage" 
              />
            </div>

            <!-- Tài Khoản Hạch Toán -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tài Khoản Hạch Toán</label>
              <select 
                v-model="form.accounting_account" 
                class="border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage"
              >
                <option value="">-- Chọn tài khoản --</option>
                <option v-for="item in accountingAccounts" :key="item.code" :value="item.code">
                  {{ item.code }} - {{ item.name }}
                </option>
              </select>
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Tên Tài Khoản Hạch Toán</label>
              <input 
                :value="selectedAccountingAccount?.name || '-'" 
                disabled 
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 text-xs text-slate-500 cursor-not-allowed" 
                aria-label="Tên tài khoản hạch toán" 
              />
            </div>

            <!-- Tiền tệ -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Tiền tệ</label>
              <div class="flex gap-1.5">
                <select 
                  v-model="form.currency_code" 
                  class="flex-1 border border-slate-200 rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500 bg-white disabled:cursor-not-allowed disabled:bg-slate-100" 
                  :disabled="!canManage"
                >
                  <option value="">-- Chọn tiền tệ --</option>
                  <option v-for="item in currencies" :key="item.code" :value="item.code">
                    {{ item.code }} - {{ item.name }}
                  </option>
                </select>
                <button 
                  v-if="canManage" 
                  type="button" 
                  @click="openCurrencyCatalog" 
                  class="w-[30px] h-[30px] border border-slate-200 bg-white hover:bg-slate-50 rounded flex items-center justify-center text-sky-600 cursor-pointer shrink-0 font-bold" 
                  title="Mở Danh mục tiền tệ"
                >
                  +
                </button>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-500">Tên Tiền Tệ</label>
              <input 
                :value="selectedCurrency?.name || '-'" 
                disabled 
                class="border border-slate-200 bg-slate-100 rounded-md p-1.5 text-xs text-slate-500 cursor-not-allowed" 
                aria-label="Tên tiền tệ" 
              />
            </div>

            <!-- Ngày Mở & Ngày Đóng -->
            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Mở</label>
              <input 
                v-model="form.opened_on" 
                type="date" 
                class="border border-slate-200 rounded-md p-1.5 text-xs focus:outline-sky-500 bg-white font-medium disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage" 
              />
            </div>

            <div class="flex flex-col gap-1">
              <label class="font-bold text-slate-700">Ngày Đóng</label>
              <input 
                v-model="form.closed_on" 
                type="date" 
                class="border border-slate-200 rounded-md p-1.5 text-xs focus:outline-sky-500 bg-white font-medium disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage" 
              />
            </div>

            <!-- Diễn giải -->
            <div class="flex flex-col gap-1 col-span-2">
              <label class="font-bold text-slate-700">Diễn Giải</label>
              <textarea 
                v-model="form.description" 
                rows="2" 
                maxlength="2000" 
                placeholder="Nhập ghi chú hoặc diễn giải..." 
                class="border border-slate-200 rounded-md p-1.5 text-xs focus:outline-sky-500 resize-none font-medium disabled:cursor-not-allowed disabled:bg-slate-100" 
                :disabled="!canManage"
              ></textarea>
            </div>

            <!-- Checkboxes -->
            <div class="col-span-2 flex items-center gap-6 pt-1 select-none">
              <label class="flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                <input 
                  v-model="form.is_intermediary" 
                  type="checkbox" 
                  class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4" 
                  :disabled="!canManage" 
                />
                <span>Tài Khoản Trung Gian</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                <input 
                  v-model="form.is_active" 
                  type="checkbox" 
                  class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4" 
                  :disabled="!canManage" 
                />
                <span>Hoạt động</span>
              </label>
            </div>
          </div>

          <!-- Tab 2 -->
          <div v-else class="min-h-[220px] flex items-center justify-center p-6 text-center select-none">
            <div class="border border-dashed border-slate-300 rounded-lg p-8 bg-slate-50 text-slate-500 text-xs">
              Tab thông tin hạch toán thuế/phí cà thẻ sẽ được cấu hình khi có đặc tả chi tiết từ kế toán.
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="bg-slate-50 px-5 py-3 border-t border-slate-200 flex items-center justify-end gap-2 select-none">
          <button 
            type="button" 
            @click="nextFormTab" 
            class="px-4 py-1.5 border border-slate-300 rounded text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 cursor-pointer transition-colors"
          >
            Tiếp
          </button>
          <button 
            type="button" 
            @click="closeForm" 
            class="px-4 py-1.5 border border-slate-300 rounded text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 cursor-pointer transition-colors"
          >
            Hủy
          </button>
          <button 
            v-if="canManage" 
            type="button" 
            @click="save" 
            :disabled="saving" 
            class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-xs font-bold border-none cursor-pointer transition-colors shadow-xs disabled:opacity-50"
          >
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
