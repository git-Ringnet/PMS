<script setup>
import { computed } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  logItem: Object
})
const emit = defineEmits(['close'])

const fieldLabels = {
  name: 'Tên / Tên món',
  party_name: 'Tên tiệc',
  quantity: 'Số lượng',
  price: 'Đơn giá / Giá',
  discount: 'Giảm giá',
  note: 'Ghi chú',
  status: 'Trạng thái',
  arrival_date: 'Ngày đến',
  arrival_time: 'Giờ đến',
  departure_time: 'Giờ đi',
  customer: 'Khách hàng',
  company: 'Công ty',
  email: 'Email',
  phone: 'Số điện thoại',
  adults: 'Người lớn',
  children: 'Trẻ em',
  table_id: 'Mã Bàn',
  location_id: 'Mã Khu vực',
  outlet_id: 'Mã Điểm bán',
  category_id: 'Mã Danh mục',
  product_id: 'Mã Món ăn',
  is_active: 'Trạng thái HĐ',
  description: 'Mô tả',
  sub_party_id: 'Mã Tiệc con',
  id: 'ID',
  reason: 'Lý do',
  code: 'Mã code',
  type: 'Loại',
  address: 'Địa chỉ',
  party_code: 'Mã tiệc',
  order_id: 'Mã hoá đơn',
  guest_count: 'Số lượng khách',
  customer_name: 'Tên khách',
  customer_phone: 'SĐT khách',
  customer_email: 'Email khách',
  customer_address: 'Địa chỉ khách',
  public_note: 'Ghi chú (In bill)',
  internal_note: 'Ghi chú nội bộ',
  internal_note_discount: 'Ghi chú giảm giá',
  promotion_id: 'Mã khuyến mãi',
  sub_parties: 'Danh sách tiệc con',
  items: 'Danh sách món',
  amount: 'Số tiền',
  payment_method: 'P.Thức T.Toán',
  start_date: 'Ngày bắt đầu',
  end_date: 'Ngày kết thúc',
  product_name: 'Tên món',
  surcharge: 'Phụ thu',
  bill_name: 'Tên bill',
  table_name: 'Tên bàn',
  table_code: 'Mã bàn',
  from_table: 'Bàn gốc',
  to_table: 'Bàn đích',
  outlet: 'Outlet',
  location: 'Khu vực',
  base_discount: 'Giảm giá gốc',
  base_surcharge: 'Phụ thu gốc',
  total_amount: 'Tổng tiền',
  product_count: 'Số sản phẩm',
  outlets: 'Danh sách outlet',
  deposits: 'Danh sách đặt cọc',
  actions: 'Chi tiết thao tác',
}

const getLabel = (key) => {
  return fieldLabels[key] || key
}

const formatValue = (val, key) => {
  if (val === undefined || val === null || val === '') return '-'
  if (Array.isArray(val)) {
    if (val.length === 0) return 'Trống'
    if (key === 'sub_parties') return `Gồm ${val.length} tiệc con`
    if (key === 'items' || key === 'products') return `Gồm ${val.length} món`
    return `Danh sách ${val.length} mục`
  }
  if (typeof val === 'object') {
    return 'Có thay đổi dữ liệu chi tiết'
  }
  return val
}

const changes = computed(() => {
  const oldVal = props.logItem?.old_values || {}
  const newVal = props.logItem?.new_values || {}
  
  // Collect all unique keys from both objects
  const keys = new Set([...Object.keys(oldVal), ...Object.keys(newVal)])
  
  const result = []
  for (const key of keys) {
    // Ignore internal technical fields not meant for user
    if (['id', 'created_at', 'updated_at', 'deleted_at', 'creator_id', 'party_id', 'sub_party_id', 'location_id', 'outlet_id', 'category_id', 'product_id', 'table_id'].includes(key)) continue;

    const vOld = oldVal[key]
    const vNew = newVal[key]
    
    // Check if values are different
    const isChanged = JSON.stringify(vOld) !== JSON.stringify(vNew)
    
    // Sort: changed fields first, then unchanged
    result.push({
      key,
      label: getLabel(key),
      oldValue: vOld,
      newValue: vNew,
      isChanged
    })
  }
  // Sort changed fields to top
  result.sort((a, b) => (b.isChanged ? 1 : 0) - (a.isChanged ? 1 : 0))
  return result
})

</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
        <h3 class="text-lg font-semibold text-slate-800">Chi tiết thay đổi</h3>
        <button @click="emit('close')" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-lg transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 overflow-y-auto flex-1 bg-white">
        <div class="mb-5 text-sm text-slate-700 bg-sky-50 px-4 py-3 rounded-lg border border-sky-100 flex gap-3 items-start">
          <svg class="w-5 h-5 text-sky-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <div>
            <div class="font-medium text-sky-900 mb-1">Thao tác: {{ logItem?.description || '-' }}</div>
            <div class="text-sky-700 text-xs">Loại: <strong class="uppercase">{{ logItem?.action }}</strong> &nbsp;|&nbsp; Thời gian: {{ logItem?.created_time }} {{ logItem?.created_date }}</div>
          </div>
        </div>
        
        <div v-if="changes.length === 0" class="text-center py-10 bg-slate-50 rounded-lg border border-slate-100 text-slate-500 text-sm">
          Không có chi tiết dữ liệu thay đổi nào để hiển thị.
        </div>
        
        <div v-else class="border border-slate-200 rounded-lg overflow-hidden shadow-sm">
          <table class="w-full text-left border-collapse text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="px-4 py-3 font-semibold text-slate-700 w-1/4">Trường dữ liệu</th>
                <th class="px-4 py-3 font-semibold text-slate-700 w-[37.5%] border-l border-slate-200">Dữ liệu Cũ</th>
                <th class="px-4 py-3 font-semibold text-slate-700 w-[37.5%] border-l border-slate-200">Dữ liệu Mới</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(field, idx) in changes" 
                :key="idx" 
                class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50/50 transition-colors"
                :class="{'bg-amber-50/60': field.isChanged}"
              >
                <td class="px-4 py-3 border-r border-slate-100 align-top">
                  <div class="font-medium text-slate-800">{{ field.label }}</div>
                  <div class="text-xs text-slate-400 font-mono mt-0.5">{{ field.key }}</div>
                </td>
                <td class="px-4 py-3 border-r border-slate-100 align-top">
                  <div 
                    v-if="field.oldValue !== undefined && field.oldValue !== null" 
                    class="break-all whitespace-pre-wrap"
                    :class="{'line-through text-slate-400': field.isChanged, 'text-slate-700': !field.isChanged}"
                  >
                    {{ formatValue(field.oldValue, field.key) }}
                  </div>
                  <span v-else class="text-slate-300 italic">-</span>
                </td>
                <td class="px-4 py-3 align-top">
                  <div 
                    v-if="field.newValue !== undefined && field.newValue !== null" 
                    class="break-all whitespace-pre-wrap font-medium"
                    :class="{'text-emerald-600': field.isChanged, 'text-slate-800': !field.isChanged}"
                  >
                    {{ formatValue(field.newValue, field.key) }}
                  </div>
                  <span v-else class="text-slate-300 italic">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
        <button @click="emit('close')" class="px-5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-100 active:scale-95 transition-all shadow-sm">
          Đóng
        </button>
      </div>
    </div>
  </div>
</template>
