<template>
  <div class="flex flex-col h-[calc(100vh-48px)] bg-slate-50 overflow-hidden font-sans">
    <!-- Bento Stats -->
    <div class="grid grid-cols-5 gap-4 px-6 py-4 shrink-0 bg-slate-50 border-b border-slate-200">
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tổng đơn</div>
          <div class="text-xl font-bold text-slate-800">{{ stats.totalOrders }}</div>
        </div>
        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Đã thanh toán</div>
          <div class="text-xl font-bold text-emerald-600">{{ stats.paidOrders }}</div>
        </div>
        <div class="w-9 h-9 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Chưa thanh toán</div>
          <div class="text-xl font-bold text-orange-600">{{ stats.unpaidOrders }}</div>
        </div>
        <div class="w-9 h-9 rounded-full bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-500 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tổng tiền</div>
          <div class="text-xl font-bold text-sky-700">{{ formatCurrency(stats.totalAmount) }}</div>
        </div>
        <div class="w-9 h-9 rounded-full bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)] border border-slate-100 flex items-center justify-between hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
        <div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tổng khách</div>
          <div class="text-xl font-bold text-slate-700">{{ stats.totalGuests }}</div>
        </div>
        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
      </div>
    </div>

    <!-- Actions Bar -->
    <div class="flex items-center justify-between px-6 py-3.5 shrink-0 bg-white border-b border-slate-200 shadow-sm z-10">
      <div class="flex items-center gap-3">
        <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all duration-200 shadow-sm shadow-blue-100 hover:shadow-blue-200 border-none cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          Lọc dữ liệu
        </button>
        <div class="relative group">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-slate-400 group-focus-within:text-sky-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input type="text" v-model="searchQuery" @input="handleSearchInput" class="pl-9 pr-3 py-1.5 w-64 border border-slate-200 rounded-lg text-xs text-slate-700 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all placeholder-slate-400 font-semibold" placeholder="Tìm kiếm mã đơn, quầy..." />
        </div>
      </div>
      <button class="bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-200 px-4 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-all duration-200 shadow-xs cursor-pointer">
        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Xuất Excel
      </button>
    </div>

    <!-- Table Container -->
    <div class="flex-1 flex flex-col bg-white overflow-hidden mx-6 mt-6 mb-2 rounded-xl border border-slate-200 shadow-sm relative">
      <div class="flex-1 overflow-auto relative scrollbar-thin">
        <table class="w-max min-w-full text-xs text-left border-collapse">
          <thead class="sticky top-0 z-20 bg-slate-100 border-b border-slate-200">
            <tr class="text-slate-500 uppercase tracking-wider font-bold">
              <th class="sticky left-0 z-30 bg-slate-100 border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs w-[140px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-1 cursor-pointer hover:text-slate-800 transition-colors">
                  Mã tham chiếu
                  <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </div>
              </th>
              <th class="sticky left-[140px] z-30 bg-slate-100 border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs w-[120px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-1 cursor-pointer hover:text-slate-800 transition-colors">
                  Mã đơn
                  <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </div>
              </th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[120px]">Mã hoá đơn</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[130px]">Mã thanh toán</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[150px]">Quầy</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Bàn</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Phòng</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[130px] text-right">Tổng tiền</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[100px] text-center">Số khách</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[100px] text-center">SL món</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Ngày tạo</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[100px]">Ca</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Tên khách</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Nhân viên tạo</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[160px]">Thu ngân</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[150px]">Trạng thái</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[120px]">HTTT</th>
              <th class="border-b border-slate-200 px-4 py-2.5 font-bold text-slate-500 uppercase tracking-wider text-xs min-w-[130px]">Ghi chú</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="orders.length === 0 && !loading">
              <td colspan="18" class="p-0 h-[400px]">
                <div class="flex flex-col items-center justify-center h-full text-slate-400 gap-3">
                  <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center shadow-inner border border-slate-100">
                    <svg class="w-8 h-8 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                  </div>
                  <div class="text-center">
                    <h3 class="text-slate-800 font-bold text-sm mb-0.5">Chưa có dữ liệu</h3>
                    <p class="text-xs text-slate-500 max-w-sm">Không tìm thấy đơn hàng nào khớp với điều kiện lọc của bạn. Hãy thử thay đổi bộ lọc.</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-50 transition-colors">
              <td class="sticky left-0 z-10 bg-white border-b border-slate-100 px-4 py-2 text-sky-600 font-medium">#{{ order.id }}</td>
              <td class="sticky left-[140px] z-10 bg-white border-b border-slate-100 px-4 py-2">{{ order.name }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2">{{ order.outlet_code || 'N/A' }}</td>
              <td class="border-b border-slate-100 px-4 py-2">{{ order.table?.name || 'N/A' }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2 text-right font-medium text-emerald-600">{{ formatCurrency(order.total_amount) }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-center">{{ order.guest_count || 0 }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-center">{{ order.items_count || 0 }}</td>
              <td class="border-b border-slate-100 px-4 py-2">{{ formatDate(order.created_at) }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2">{{ order.customer_name || 'Khách lẻ' }}</td>
              <td class="border-b border-slate-100 px-4 py-2">{{ order.creator?.name || 'N/A' }}</td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold" 
                      :class="{
                        'bg-blue-100 text-blue-700': order.status === 'serving',
                        'bg-emerald-100 text-emerald-700': order.status === 'paid',
                        'bg-slate-100 text-slate-700': order.status === 'cancelled'
                      }">
                  {{ order.status === 'serving' ? 'Đang phục vụ' : (order.status === 'paid' ? 'Đã thanh toán' : 'Đã hủy') }}
                </span>
              </td>
              <td class="border-b border-slate-100 px-4 py-2 text-slate-400">N/A</td>
              <td class="border-b border-slate-100 px-4 py-2 max-w-[130px] truncate" :title="order.public_note || ''">{{ order.public_note || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination & Bottom Actions -->
    <div class="flex items-center justify-between shrink-0 px-6 py-3 bg-slate-50 border-t border-slate-200 text-xs">
      <!-- Bottom Buttons -->
      <div class="flex items-center gap-3">
        <button class="bg-white border border-slate-200 text-slate-400 px-3 py-1.5 rounded-lg font-bold flex items-center gap-1.5 cursor-not-allowed">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          Xóa đơn
        </button>
        <button class="bg-white border border-slate-200 text-slate-400 px-3 py-1.5 rounded-lg font-bold flex items-center gap-1.5 cursor-not-allowed">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
          Sửa đơn
        </button>
        <button class="bg-white border border-slate-200 text-slate-400 px-3 py-1.5 rounded-lg font-bold flex items-center gap-1.5 cursor-not-allowed">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          Lịch sử
        </button>
      </div>

      <!-- Pagination -->
      <div class="flex items-center gap-4 text-slate-600">
        <span class="font-bold">{{ pagination.total > 0 ? (pagination.current_page - 1) * pagination.per_page + 1 : 0 }}-{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} trên {{ pagination.total }} đơn</span>
        <div class="flex gap-1.5">
          <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <button class="w-7 h-7 flex items-center justify-center border border-[#7ec0f3] rounded-md bg-[#e0f2fe] text-sky-700 font-bold shadow-sm">{{ pagination.current_page }}</button>
          <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="w-7 h-7 flex items-center justify-center border border-slate-200 rounded-md bg-white text-slate-400 hover:text-slate-600 hover:bg-slate-50 disabled:opacity-50 shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
        <select v-model="pagination.per_page" @change="fetchOrders(1)" class="border border-slate-200 rounded-md px-2 py-1 bg-white text-slate-700 text-xs font-semibold focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-200 shadow-sm">
          <option :value="20">20 / trang</option>
          <option :value="50">50 / trang</option>
          <option :value="100">100 / trang</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/http'
import { format } from 'date-fns'

const orders = ref([])
const loading = ref(false)
const searchQuery = ref('')
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 100,
  total: 0
})

const stats = computed(() => {
  return {
    totalOrders: pagination.value.total,
    paidOrders: orders.value.filter(o => o.status === 'paid').length,
    unpaidOrders: orders.value.filter(o => o.status !== 'paid' && o.status !== 'cancelled').length,
    totalAmount: orders.value.reduce((sum, o) => sum + (Number(o.total_amount) || 0), 0),
    totalGuests: orders.value.reduce((sum, o) => sum + (Number(o.guest_count) || 0), 0)
  }
})

const fetchOrders = async (page = 1) => {
  loading.value = true
  try {
    const response = await api.get('/fnb/orders', {
      params: {
        page: page,
        per_page: pagination.value.per_page,
        search: searchQuery.value
      }
    })
    orders.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total
    }
  } catch (error) {
    console.error('Error fetching orders:', error)
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchOrders(page)
  }
}

let searchTimeout = null
const handleSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchOrders(1)
  }, 500)
}

const formatCurrency = (value) => {
  if (!value) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return format(new Date(dateString), 'dd/MM/yyyy HH:mm')
}

onMounted(() => {
  fetchOrders()
})
</script>

<style scoped>
</style>
