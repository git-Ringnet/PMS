<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { fetchCompanies, fetchBookers, fetchCustomerSources } from '@/services/company-service'
import AddCrmCustomerModal from '../../party/modals/AddCrmCustomerModal.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  initialData: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'save'])

const formData = ref({
  customerName: '',
  customerPhone: '',
  customerEmail: '',
  customerAddress: '',
  guestCount: 1,
  publicNote: '',
  companyId: null,
  customerSourceId: null,
  bookerId: null
})

const selectedCustomer = ref('walk_in')
const isAddCustomerModalOpen = ref(false)

const companies = ref([])
const bookers = ref([])
const sources = ref([])

const walkInDisplay = computed(() => {
  if (selectedCustomer.value === 'walk_in' && (formData.value.customerName || formData.value.customerPhone)) {
    return `Khách vãng lai - ${formData.value.customerName || formData.value.customerPhone}`
  }
  return 'Khách vãng lai'
})

onMounted(async () => {
  try {
    const [compRes, bookRes, sourceRes] = await Promise.all([
      fetchCompanies(),
      fetchBookers(),
      fetchCustomerSources()
    ])
    companies.value = compRes.data?.data || compRes.data || []
    bookers.value = bookRes.data?.data || bookRes.data || []
    sources.value = sourceRes.data?.data || sourceRes.data || []
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu khách hàng:', err)
  }
})

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.initialData) {
    formData.value.customerName = props.initialData.customerName || ''
    formData.value.customerPhone = props.initialData.customerPhone || ''
    formData.value.customerEmail = props.initialData.customerEmail || ''
    formData.value.customerAddress = props.initialData.customerAddress || ''
    formData.value.guestCount = props.initialData.guestCount || 1
    formData.value.publicNote = props.initialData.publicNote || ''
    formData.value.companyId = props.initialData.companyId || null
    formData.value.customerSourceId = props.initialData.customerSourceId || null
    formData.value.bookerId = props.initialData.bookerId || null
    
    if (props.initialData.companyId) {
      selectedCustomer.value = `company_${props.initialData.companyId}`
    } else if (props.initialData.customerSourceId) {
      selectedCustomer.value = `source_${props.initialData.customerSourceId}`
    } else if (props.initialData.bookerId) {
      selectedCustomer.value = `booker_${props.initialData.bookerId}`
    } else {
      selectedCustomer.value = 'walk_in'
    }
  }
})

watch(selectedCustomer, (val) => {
  if (val === 'walk_in') {
    formData.value.companyId = null
    formData.value.customerSourceId = null
    formData.value.bookerId = null
    // Giữ nguyên tên và SĐT nếu họ đã nhập từ AddCrmCustomerModal
  } else {
    const [type, idStr] = val.split('_')
    const id = Number(idStr)
    
    formData.value.companyId = type === 'company' ? id : null
    formData.value.customerSourceId = type === 'source' ? id : null
    formData.value.bookerId = type === 'booker' ? id : null
    
    let obj = null
    if (type === 'company') obj = companies.value.find(c => c.id === id)
    if (type === 'booker') obj = bookers.value.find(c => c.id === id)
    if (type === 'source') obj = sources.value.find(c => c.id === id)
    
    if (obj) {
      formData.value.customerName = obj.name
      formData.value.customerPhone = obj.phone || ''
      formData.value.customerEmail = obj.email || ''
      formData.value.customerAddress = obj.address || ''
    }
  }
})

const handleSaveCrmCustomer = (data) => {
  formData.value.customerName = data.name || data.customerName
  formData.value.customerPhone = data.phone || data.customerPhone || ''
  formData.value.customerEmail = data.email || data.customerEmail || ''
  formData.value.customerAddress = data.address || data.customerAddress || ''
  isAddCustomerModalOpen.value = false
  selectedCustomer.value = 'walk_in'
}

const handleSave = () => {
  emit('save', { ...formData.value })
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-700 text-lg">Khách hàng</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="p-6 flex flex-col gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Khách hàng</label>
          <div class="flex gap-2">
            <select v-model="selectedCustomer" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-sky-500 bg-amber-50/30">
              <option value="walk_in">{{ walkInDisplay }}</option>
              <optgroup v-if="companies.length" label="Công ty">
                <option v-for="c in companies" :key="'company_'+c.id" :value="'company_'+c.id">{{ c.name }}</option>
              </optgroup>
              <optgroup v-if="sources.length" label="Nguồn khách">
                <option v-for="s in sources" :key="'source_'+s.id" :value="'source_'+s.id">{{ s.name }}</option>
              </optgroup>
              <optgroup v-if="bookers.length" label="Người đặt phòng">
                <option v-for="b in bookers" :key="'booker_'+b.id" :value="'booker_'+b.id">{{ b.name }}</option>
              </optgroup>
            </select>
            <button v-if="selectedCustomer === 'walk_in'" @click="isAddCustomerModalOpen = true" class="bg-sky-400 hover:bg-sky-500 text-white w-10 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </button>
          </div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">SL khách</label>
          <input type="number" v-model="formData.guestCount" min="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-sky-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Ghi chú</label>
          <textarea v-model="formData.publicNote" rows="4" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-sky-500 resize-none"></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 flex justify-end gap-3 shrink-0">
        <button @click="emit('close')" class="px-6 py-2 bg-white text-slate-500 hover:text-slate-700 text-sm font-semibold transition-colors">
          Hủy
        </button>
        <button @click="handleSave" class="px-6 py-2 bg-sky-400 text-white hover:bg-sky-500 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center gap-1">
          Lưu
        </button>
      </div>
    </div>
    
    <AddCrmCustomerModal 
      :is-open="isAddCustomerModalOpen"
      :initial-data="{ name: formData.customerName, phone: formData.customerPhone, email: formData.customerEmail, address: formData.customerAddress }"
      @close="isAddCustomerModalOpen = false"
      @save="handleSaveCrmCustomer"
    />
  </div>
</template>
