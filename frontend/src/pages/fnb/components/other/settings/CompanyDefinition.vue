<script setup>
import { ref, computed } from 'vue'

defineEmits(['back'])

const tabs = [
  'Công ty',
  'Thị trường',
  'Nguồn khách',
  'Chi nhánh',
  'Loại',
  'Loại công ty (Tiền tố)',
  'Ngành nghề'
]

const activeSubTab = ref('Công ty')

// Mock Companies list
const companies = ref([
  { id: 'CTY0001', name: 'KHÁCH LẺ', tradeName: 'KHÁCH LẺ', address: 'Khách hàng không lấy hoá đơn', taxCode: '', phone: '', email: '', source: 'Free Individual Traveler', market: 'Free Individual Traveler', ar: true, bankAccount: '', priceCode: '' },
  { id: 'CTY0002', name: 'AGODA', tradeName: 'AGODA', address: '', taxCode: '', phone: '', email: '', source: 'Online Travel Agent', market: 'Online Travel Agent', ar: false, bankAccount: '', priceCode: '' },
  { id: 'CTY0003', name: 'BOOKING.COM', tradeName: 'BOOKING.COM', address: '', taxCode: '', phone: '', email: '', source: 'Online Travel Agent', market: 'Online Travel Agent', ar: false, bankAccount: '', priceCode: '' },
  { id: 'CTY0004', name: 'TRIP.COM', tradeName: 'TRIP.COM', address: '', taxCode: '', phone: '', email: '', source: 'Online Travel Agent', market: 'Online Travel Agent', ar: false, bankAccount: '', priceCode: '' },
  { id: 'CTY0005', name: 'EXPEDIA', tradeName: 'EXPEDIA', address: '', taxCode: '', phone: '', email: '', source: 'Online Travel Agent', market: 'Online Travel Agent', ar: false, bankAccount: '', priceCode: '' }
])

const searchName = ref('')

const filteredCompanies = computed(() => {
  if (!searchName.value) return companies.value
  return companies.value.filter(c => c.name.toLowerCase().includes(searchName.value.toLowerCase()))
})

const handleAddCompany = () => {
  const name = prompt('Nhập tên công ty mới:')
  if (name) {
    const nextIdNum = companies.value.length + 1
    const id = `CTY000${nextIdNum}`
    companies.value.push({
      id,
      name: name.toUpperCase(),
      tradeName: name.toUpperCase(),
      address: '',
      taxCode: '',
      phone: '',
      email: '',
      source: 'Online Travel Agent',
      market: 'Online Travel Agent',
      ar: false,
      bankAccount: '',
      priceCode: ''
    })
  }
}

const handleDeleteCompany = (id) => {
  if (confirm(`Bạn có chắc chắn muốn xoá đối tác ${id}?`)) {
    companies.value = companies.value.filter(c => c.id !== id)
  }
}

const handleConfig = () => {
  alert('Đang mở cài đặt nâng cao...')
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center gap-2 mb-4 shrink-0">
      <button @click="$emit('back')" class="p-1.5 rounded-full hover:bg-slate-200 text-slate-600 transition active:scale-95" title="Quay lại">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-base font-bold text-slate-800">Định nghĩa công ty</h1>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 mb-5 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-400 shrink-0">
      <button
        v-for="tab in tabs"
        :key="tab"
        @click="activeSubTab = tab"
        class="pb-2 border-b-2 transition-all duration-200 relative"
        :class="activeSubTab === tab ? 'border-sky-500 text-sky-600' : 'border-transparent hover:text-slate-600'"
      >
        {{ tab }}
        <span v-if="activeSubTab === tab" class="absolute bottom-0 left-0 right-0 h-[2px] bg-sky-500 rounded-full"></span>
      </button>
    </div>

    <!-- Actions toolbar -->
    <div class="flex items-center justify-between mb-5 shrink-0">
      <button @click="handleAddCompany" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm hover:shadow active:scale-[0.98] transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Thêm
      </button>

      <!-- Advanced gear icon button -->
      <button @click="handleConfig" class="p-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 transition active:scale-95 shadow-sm" title="Thiết lập cột">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>
    </div>

    <!-- Table content enclosed in a card -->
    <div class="flex-1 bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
      <div class="flex-1 overflow-auto">
        <table class="w-full text-sm border-collapse whitespace-nowrap">
          <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
              <th class="px-4 py-3 text-left border-r border-slate-100 w-24">ID</th>
              <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[200px]">
                <div class="flex flex-col gap-1">
                  <span>Tên công ty</span>
                  <input type="text" v-model="searchName" placeholder="Lọc..." class="px-2 py-0.5 border border-slate-200 rounded text-xs bg-white focus:outline-none focus:ring-1 focus:ring-sky-200 font-normal w-full" />
                </div>
              </th>
              <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[160px]">Tên giao dịch</th>
              <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[250px]">Địa chỉ</th>
              <th class="px-4 py-3 text-left border-r border-slate-100">MST</th>
              <th class="px-4 py-3 text-left border-r border-slate-100">SĐT</th>
              <th class="px-4 py-3 text-left border-r border-slate-100">Email</th>
              <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[180px]">Nguồn khách</th>
              <th class="px-4 py-3 text-left border-r border-slate-100 min-w-[180px]">Thị trường</th>
              <th class="px-4 py-3 text-center border-r border-slate-100 w-20">AR</th>
              <th class="px-4 py-3 text-left border-r border-slate-100">Tài khoản ngân hàng</th>
              <th class="px-4 py-3 text-left border-r border-slate-100">Mã giá</th>
              <th class="px-4 py-3 text-center w-20">Xoá</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="c in filteredCompanies" :key="c.id" class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
              <td class="px-4 py-3 border-r border-slate-100 font-bold text-slate-500">{{ c.id }}</td>
              <td class="px-4 py-3 border-r border-slate-100 font-semibold text-slate-800">{{ c.name }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-600 font-medium">{{ c.tradeName }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-500 max-w-xs truncate" :title="c.address">{{ c.address || '—' }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-600 font-mono text-xs">{{ c.taxCode || '—' }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-600 text-xs">{{ c.phone || '—' }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-600 text-xs">{{ c.email || '—' }}</td>
              <td class="px-4 py-3 border-r border-slate-100">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-600">{{ c.source }}</span>
              </td>
              <td class="px-4 py-3 border-r border-slate-100">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-600">{{ c.market }}</span>
              </td>
              <td class="px-4 py-3 border-r border-slate-100 text-center">
                <button @click="c.ar = !c.ar" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none shrink-0" :class="c.ar ? 'bg-sky-500' : 'bg-slate-300'">
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm" :class="c.ar ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-500 text-xs">{{ c.bankAccount || '—' }}</td>
              <td class="px-4 py-3 border-r border-slate-100 text-slate-500 text-xs">{{ c.priceCode || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <button @click="handleDeleteCompany(c.id)" class="p-1 rounded-md text-rose-500 hover:bg-rose-50 transition active:scale-90" title="Xóa đối tác">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </td>
            </tr>
            <tr v-if="filteredCompanies.length === 0">
              <td colspan="13" class="px-4 py-8 text-center text-slate-400 italic">
                Không tìm thấy công ty nào phù hợp.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
