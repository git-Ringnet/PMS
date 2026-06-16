<script setup>
import { ref, onMounted } from 'vue'
import { fetchBusinessInfo, updateBusinessInfo, uploadBusinessLogo, deleteBusinessLogo, fetchSystemBranches, createSystemBranch } from '@/services/company-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()
const loading = ref(false)
const branches = ref([])

const form = ref({
  id: null,
  company_name: '',
  bank_name: '',
  chairman: '',
  phone: '',
  email: '',
  director: '',
  address: '',
  system_branch_id: '',
  chief_accountant: '',
  logo_url: null,
  logo_path: null
})

// Quick create branch states
const isQuickBranchOpen = ref(false)
const quickBranchForm = ref({ code: '', name: '', tax_code: '', email: '', phone: '', address: '' })

onMounted(() => {
  loadData()
  loadBranches()
})

const loadBranches = async () => {
  try {
    const res = await fetchSystemBranches()
    branches.value = res.data.data || []
  } catch (err) {
    console.error('Error loading branches:', err)
  }
}

const loadData = async () => {
  loading.value = true
  try {
    const res = await fetchBusinessInfo()
    if (res.data?.data) {
      const d = res.data.data
      form.value = {
        id: d.id,
        company_name: d.company_name || '',
        bank_name: d.bank_name || '',
        chairman: d.chairman || '',
        phone: d.phone || '',
        email: d.email || '',
        director: d.director || '',
        address: d.address || '',
        system_branch_id: d.system_branch_id || '',
        chief_accountant: d.chief_accountant || '',
        logo_url: d.logo_url || null,
        logo_path: d.logo_path || null
      }
    }
  } catch (err) {
    console.error('Error loading business info:', err)
    uiStore.showToast('Không thể tải thông tin công ty', 'error')
  } finally {
    loading.value = false
  }
}

const handleSave = async () => {
  if (!form.value.company_name) {
    uiStore.showToast('Vui lòng nhập tên công ty', 'warning')
    return
  }
  loading.value = true
  try {
    const payload = {
      company_name: form.value.company_name,
      bank_name: form.value.bank_name || null,
      chairman: form.value.chairman || null,
      phone: form.value.phone || null,
      email: form.value.email || null,
      director: form.value.director || null,
      address: form.value.address || null,
      system_branch_id: form.value.system_branch_id || null,
      chief_accountant: form.value.chief_accountant || null
    }
    await updateBusinessInfo(payload)
    uiStore.showToast('Lưu thông tin công ty thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu thông tin'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

// Logo actions
const logoInput = ref(null)
const triggerLogoSelect = () => {
  logoInput.value.click()
}

const handleLogoUpload = async (e) => {
  const file = e.target.files[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    uiStore.showToast('Vui lòng chọn file ảnh hợp lệ', 'warning')
    return
  }
  
  loading.value = true
  const formData = new FormData()
  formData.append('logo', file)
  
  try {
    await uploadBusinessLogo(formData)
    uiStore.showToast('Tải lên logo thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || err.message || 'Lỗi khi tải logo lên'
    uiStore.showToast('Lỗi khi tải logo lên: ' + msg, 'error')
  } finally {
    loading.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

const handleDeleteLogo = async () => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa logo hiện tại?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  
  loading.value = true
  try {
    await deleteBusinessLogo()
    uiStore.showToast('Đã xóa logo thành công!', 'success')
    loadData()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa logo', 'error')
  } finally {
    loading.value = false
  }
}

// Quick create branch logic
const openQuickBranch = () => {
  quickBranchForm.value = { code: '', name: '', tax_code: '', email: '', phone: '', address: '' }
  isQuickBranchOpen.value = true
}

const saveQuickBranch = async () => {
  if (!quickBranchForm.value.name) {
    uiStore.showToast('Vui lòng nhập tên chi nhánh', 'warning')
    return
  }
  if (!quickBranchForm.value.code) {
    uiStore.showToast('Vui lòng nhập mã chi nhánh', 'warning')
    return
  }
  try {
    const res = await createSystemBranch(quickBranchForm.value)
    uiStore.showToast('Thêm chi nhánh thành công!', 'success')
    await loadBranches()
    if (res.data?.data?.id) {
      form.value.system_branch_id = res.data.data.id
    }
    isQuickBranchOpen.value = false
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra', 'error')
  }
}
</script>

<template>
  <div class="p-6 bg-white flex-1 flex flex-col overflow-auto text-xs relative select-none">
    <!-- Main content form layout -->
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-8 items-start w-full">
      <!-- Left Forms Grid (3 columns) -->
      <div class="xl:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-y-4 gap-x-6 w-full">
        <!-- Column 1 -->
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Tên Công Ty</label>
            <input 
              v-model="form.company_name" 
              type="text" 
              placeholder="Nhập tên công ty..." 
              class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Điện Thoại</label>
            <input 
              v-model="form.phone" 
              type="text" 
              placeholder="Số điện thoại..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Địa Chỉ</label>
            <input 
              v-model="form.address" 
              type="text" 
              placeholder="Địa chỉ trụ sở chính..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>
        </div>

        <!-- Column 2 -->
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Tên Ngân Hàng</label>
            <input 
              v-model="form.bank_name" 
              type="text" 
              placeholder="Nhập tên ngân hàng..." 
              class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Email</label>
            <input 
              v-model="form.email" 
              type="email" 
              placeholder="Email liên hệ..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Đơn Vị</label>
            <div class="flex items-center gap-1">
              <select 
                v-model="form.system_branch_id" 
                class="flex-1 border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs h-[30px]"
              >
                <option value="">Chọn đơn vị chi nhánh...</option>
                <option v-for="b in branches" :key="b.id" :value="b.id">
                  {{ b.code }} - {{ b.name }}
                </option>
              </select>
              <button 
                @click="openQuickBranch" 
                class="w-[30px] h-[30px] bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded font-bold text-base cursor-pointer border-none flex items-center justify-center transition-colors shadow-xs"
                title="Thêm nhanh chi nhánh"
              >
                +
              </button>
            </div>
          </div>
        </div>

        <!-- Column 3 -->
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Chủ Tịch</label>
            <input 
              v-model="form.chairman" 
              type="text" 
              placeholder="Tên chủ tịch..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Giám Đốc</label>
            <input 
              v-model="form.director" 
              type="text" 
              placeholder="Tên giám đốc..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>

          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Kế Toán Trưởng</label>
            <input 
              v-model="form.chief_accountant" 
              type="text" 
              placeholder="Tên kế toán trưởng..." 
              class="border border-slate-200 rounded-md p-1.5 font-semibold focus:outline-sky-500 text-xs bg-white" 
            />
          </div>
        </div>
      </div>

      <!-- Right Logo Box -->
      <div class="xl:col-span-1 w-full max-w-[240px] mx-auto xl:mx-0 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col select-none self-stretch">
        <div class="bg-slate-50 border-b border-slate-200 px-4 py-2 flex items-center justify-center font-bold text-slate-700">
          Logo
        </div>
        <div class="p-6 flex flex-col items-center justify-center gap-4 flex-1">
          <!-- Logo image preview circles -->
          <div 
            @click="triggerLogoSelect"
            class="w-28 h-28 border border-dashed border-slate-300 rounded-full flex items-center justify-center cursor-pointer overflow-hidden bg-slate-50 hover:bg-slate-100 transition-colors shadow-inner"
          >
            <img 
              v-if="form.logo_url" 
              :src="form.logo_url" 
              alt="Logo công ty" 
              class="w-full h-full object-contain"
            />
            <div v-else class="flex flex-col items-center justify-center text-slate-400 gap-1.5">
              <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375 0 11-.75 0 .375 0 01.75 0z" />
              </svg>
              <span class="text-[10px] font-semibold text-slate-400">Chọn Logo</span>
            </div>
          </div>
          <input 
            type="file" 
            ref="logoInput" 
            @change="handleLogoUpload" 
            class="hidden" 
            accept="image/*" 
          />

          <!-- Actions -->
          <div class="flex items-center gap-3">
            <button 
              @click="triggerLogoSelect"
              class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-full flex items-center justify-center cursor-pointer border-none transition-colors"
              title="Chọn ảnh logo"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            <button 
              v-if="form.logo_url"
              @click="handleDeleteLogo"
              class="p-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-full flex items-center justify-center cursor-pointer border-none transition-colors"
              title="Xóa logo hiện tại"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Save button toolbar -->
    <div class="mt-8 flex items-center justify-end border-t border-slate-100 pt-4 w-full">
      <button 
        @click="handleSave"
        class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1.5 shadow-sm transition-all duration-200 transform active:scale-95"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
        </svg>
        Lưu thông tin
      </button>
    </div>

    <!-- Quick Add Branch Dialog -->
    <div v-if="isQuickBranchOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs">
      <div class="bg-white rounded-xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100 animate-in">
        <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white">
          <h2 class="text-xs font-bold uppercase tracking-wider">Thêm nhanh chi nhánh</h2>
          <button @click="isQuickBranchOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light">✕</button>
        </div>
        <div class="p-5 flex flex-col gap-3">
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Mã Chi Nhánh *</label>
            <input v-model="quickBranchForm.code" type="text" placeholder="Nhập mã chi nhánh (ví dụ: HKT5)..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="font-bold text-slate-600">Tên Chi Nhánh *</label>
            <input v-model="quickBranchForm.name" type="text" placeholder="Nhập tên chi nhánh..." class="border border-slate-200 bg-[#fffbeb] rounded-md p-1.5 font-semibold text-xs focus:outline-sky-500" />
          </div>
        </div>
        <div class="bg-slate-50 px-5 py-3 flex items-center justify-end gap-2 border-t border-slate-100">
          <button @click="isQuickBranchOpen = false" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 transition-colors">
            ✕ Đóng
          </button>
          <button @click="saveQuickBranch" class="px-4 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs cursor-pointer border-none flex items-center gap-1 transition-colors">
            Lưu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
