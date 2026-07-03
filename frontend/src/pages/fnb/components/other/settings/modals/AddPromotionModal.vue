<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import http from '@/services/http'
import SelectPromotionItemsModal from './SelectPromotionItemsModal.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  promotion: {
    type: Object,
    default: null
  },
  outletsList: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'save'])

const name = ref('')
const outletId = ref(null) // null = Tất cả
const companyId = ref(null)
const customerSourceId = ref(null)
const selectedCustomerType = ref('') // 'company:ID' or 'source:ID'
const discountMode = ref('discount_percent')
const discountValue = ref(0)
const isAutoApply = ref(false)
const isActive = ref(true)
const startDate = ref('')
const endDate = ref('')
const applyByTime = ref(false)
const startTime = ref('')
const endTime = ref('')
const applyTo = ref('all') // 'all' or 'specific'

const promotionProducts = ref([])
const showSelectItemsModal = ref(false)

const companiesList = ref([])
const sourcesList = ref([])

onMounted(async () => {
    try {
        const [compRes, srcRes] = await Promise.all([
            http.get('/companies', { params: { per_page: 1000 } }),
            http.get('/customer-sources', { params: { per_page: 1000 } })
        ]);
        companiesList.value = compRes.data?.data || compRes.data || [];
        sourcesList.value = srcRes.data?.data || srcRes.data || [];
    } catch (e) {
        console.error('Lỗi tải danh sách công ty/nguồn khách:', e)
    }
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    if (props.promotion) {
      name.value = props.promotion.name || ''
      outletId.value = props.promotion.outlet_id || null
      isAutoApply.value = !!props.promotion.is_auto_apply
      isActive.value = props.promotion.is_active !== false
      startDate.value = props.promotion.start_date ? props.promotion.start_date.substring(0, 10) : ''
      endDate.value = props.promotion.end_date ? props.promotion.end_date.substring(0, 10) : ''
      applyByTime.value = !!props.promotion.apply_by_time
      startTime.value = props.promotion.start_time ? props.promotion.start_time.substring(0,5) : ''
      endTime.value = props.promotion.end_time ? props.promotion.end_time.substring(0,5) : ''
      applyTo.value = props.promotion.is_all_product ? 'all' : 'specific'
      
      if (props.promotion.discount_percent > 0) {
        discountMode.value = 'discount_percent'
        discountValue.value = props.promotion.discount_percent
      } else if (props.promotion.increase_percent > 0) {
        discountMode.value = 'increase_percent'
        discountValue.value = props.promotion.increase_percent
      } else if (props.promotion.discount_amount > 0) {
        discountMode.value = 'discount_amount'
        discountValue.value = props.promotion.discount_amount
      } else if (props.promotion.increase_amount > 0) {
        discountMode.value = 'increase_amount'
        discountValue.value = props.promotion.increase_amount
      } else {
        discountMode.value = 'discount_percent'
        discountValue.value = 0
      }

      if (props.promotion.company_id) {
          selectedCustomerType.value = `company:${props.promotion.company_id}`
      } else if (props.promotion.customer_source_id) {
          selectedCustomerType.value = `source:${props.promotion.customer_source_id}`
      } else {
          selectedCustomerType.value = ''
      }

      promotionProducts.value = (props.promotion.products || []).map(p => ({
          ...p.product,
          fb_product_id: p.fb_product_id
      }))
    } else {
      name.value = ''
      outletId.value = null
      selectedCustomerType.value = ''
      discountMode.value = 'discount_percent'
      discountValue.value = 0
      isAutoApply.value = false
      isActive.value = true
      startDate.value = ''
      endDate.value = ''
      applyByTime.value = false
      startTime.value = ''
      endTime.value = ''
      applyTo.value = 'all'
      promotionProducts.value = []
    }
  }
})

const handleSave = () => {
    let company_id = null;
    let customer_source_id = null;

    if (selectedCustomerType.value) {
        const [type, id] = selectedCustomerType.value.split(':');
        if (type === 'company') company_id = id;
        if (type === 'source') customer_source_id = id;
    }

    const payload = {
        name: name.value,
        outlet_id: outletId.value || null,
        company_id,
        customer_source_id,
        is_auto_apply: isAutoApply.value,
        is_active: isActive.value,
        start_date: startDate.value || null,
        end_date: endDate.value || null,
        apply_by_time: applyByTime.value,
        start_time: applyByTime.value ? startTime.value || null : null,
        end_time: applyByTime.value ? endTime.value || null : null,
        is_all_product: applyTo.value === 'all',
        discount_percent: discountMode.value === 'discount_percent' ? discountValue.value : 0,
        increase_percent: discountMode.value === 'increase_percent' ? discountValue.value : 0,
        discount_amount: discountMode.value === 'discount_amount' ? discountValue.value : 0,
        increase_amount: discountMode.value === 'increase_amount' ? discountValue.value : 0,
        products: applyTo.value === 'specific' ? promotionProducts.value.map(p => p.id || p.fb_product_id) : []
    }

    emit('save', { id: props.promotion?.id, ...payload })
}

const addSelectedItems = (items) => {
    const newItems = items.filter(i => !promotionProducts.value.find(p => p.id === i.id || p.fb_product_id === i.id))
    promotionProducts.value = [...promotionProducts.value, ...newItems]
}

const removeProduct = (idx) => {
    promotionProducts.value.splice(idx, 1)
}

const getBasePrice = (item) => {
    if (!outletId.value) return Number(item.price || 0);
    const outletPrices = item.outlet_prices || item.outletPrices;
    if (!outletPrices) return Number(item.price || 0);
    
    const op = outletPrices.find(op => op.outlet_id == outletId.value);
    if (op) {
        const isCombo = item.is_combo == 1 || item.is_combo === true;
        if (isCombo && op.update_combo_price && Number(op.combo_price) > 0) {
            return Number(op.combo_price);
        }
        if (Number(op.price) > 0) {
            return Number(op.price);
        }
    }
    return Number(item.price || 0);
}

const calculateNewPrice = (item) => {
    if (!item) return 0;
    const price = getBasePrice(item);
    const val = Number(discountValue.value);
    
    if (discountMode.value === 'discount_percent') {
        return price - (price * val / 100);
    } else if (discountMode.value === 'increase_percent') {
        return price + (price * val / 100);
    } else if (discountMode.value === 'discount_amount') {
        return Math.max(0, price - val);
    } else if (discountMode.value === 'increase_amount') {
        return price + val;
    }
    return price;
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="$emit('close')"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-[1100px] max-w-full flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">
          {{ promotion ? 'Cập nhật chương trình khuyến mãi' : 'Định nghĩa chương trình khuyến mãi' }}
        </h3>
        <button @click="$emit('close')" class="p-1 hover:bg-slate-100 rounded-lg transition-colors">
          <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-auto p-6 flex flex-col gap-6">
          <div class="w-full space-y-4">
            <div class="grid grid-cols-4 gap-4">
                <div class="col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Tên chương trình <span class="text-red-500">*</span></label>
                    <input v-model="name" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                </div>
                
                <div class="col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Khu vực</label>
                    <select v-model="outletId" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500">
                        <option :value="null">Tất cả</option>
                        <option v-for="ot in outletsList" :key="ot.id" :value="ot.id">{{ ot.name }}</option>
                    </select>
                </div>

                <div class="col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Chọn công ty / Khách hàng</label>
                    <select v-model="selectedCustomerType" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500">
                        <option value="">Tất cả</option>
                        <optgroup label="Công ty">
                            <option v-for="comp in companiesList" :key="'company:'+comp.id" :value="'company:'+comp.id">{{ comp.name }}</option>
                        </optgroup>
                        <optgroup label="Nguồn khách">
                            <option v-for="src in sourcesList" :key="'source:'+src.id" :value="'source:'+src.id">{{ src.name }}</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="col-span-2 lg:col-span-1 flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Điều chỉnh giá</label>
                        <select v-model="discountMode" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500">
                            <option value="discount_percent">Giảm giá theo %</option>
                            <option value="discount_amount">Giảm giá tiền mặt</option>
                            <option value="increase_percent">Tăng giá theo %</option>
                            <option value="increase_amount">Tăng giá tiền mặt</option>
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Giá trị</label>
                        <input v-model="discountValue" type="number" min="0" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
                    </div>
                </div>

                <div class="col-span-2 lg:col-span-1 flex items-center mt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="isAutoApply" class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                    <span class="ml-2 text-xs font-medium text-slate-700">Tự động áp dụng khi tạo đơn</span>
                    </label>
                </div>

                <div class="col-span-2 lg:col-span-1 flex items-center mt-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="isActive" class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                    <span class="ml-2 text-xs font-medium text-slate-700">Áp dụng</span>
                    </label>
                </div>

                <div class="col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Từ ngày</label>
                    <input v-model="startDate" type="date" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500" />
                </div>
                
                <div class="col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Đến ngày</label>
                    <input v-model="endDate" type="date" class="w-full px-3 py-2 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500" />
                </div>

                <div class="col-span-4 flex flex-wrap gap-4 items-center">
                    <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="applyByTime" class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
                    <span class="ml-2 text-xs font-medium text-slate-700">Áp dụng theo khung giờ</span>
                    </label>

                    <div v-if="applyByTime" class="flex gap-4 ml-4">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-700">Từ giờ</label>
                            <input v-model="startTime" type="time" class="px-2 py-1 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500" />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-700">Đến giờ</label>
                            <input v-model="endTime" type="time" class="px-2 py-1 border border-slate-200 rounded-md text-sm focus:outline-none focus:border-sky-500" />
                        </div>
                    </div>
                </div>

                <div class="col-span-4 mt-2 pt-4 border-t border-slate-100">
                    <label class="block text-xs font-medium text-slate-700 mb-2">Áp dụng với:</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                            <input type="radio" v-model="applyTo" value="all" class="text-sky-500 focus:ring-sky-500"> Tất cả
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                            <input type="radio" v-model="applyTo" value="specific" class="text-sky-500 focus:ring-sky-500"> Sản phẩm
                        </label>
                    </div>
                </div>
            </div>
          </div>

          <div v-if="applyTo === 'specific'" class="w-full border-t border-slate-100 pt-4 flex flex-col">
              <div class="flex justify-between items-center mb-3">
                  <h4 class="text-sm font-medium text-slate-700">Sản phẩm áp dụng</h4>
                  <button @click="showSelectItemsModal = true" class="text-xs bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-3 py-1.5 rounded transition">
                      Thêm
                  </button>
              </div>
              <div class="flex-1 overflow-auto border border-slate-200 rounded-md">
                  <table class="w-full text-xs text-left text-slate-600">
                      <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                          <tr>
                              <th class="px-2 py-2 border-r border-slate-200">Mã</th>
                              <th class="px-2 py-2 border-r border-slate-200">Tên</th>
                              <th class="px-2 py-2 border-r border-slate-200 text-right">Đơn giá</th>
                              <th class="px-2 py-2 border-r border-slate-200 text-right">Giảm giá</th>
                              <th class="px-2 py-2 border-r border-slate-200 text-right">Đơn giá mới</th>
                              <th class="px-2 py-2 w-8 text-center"></th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr v-for="(item, idx) in promotionProducts" :key="item.id || item.fb_product_id" class="border-b border-slate-100 hover:bg-slate-50">
                              <td class="px-2 py-2 border-r border-slate-100">{{ item.product_code || item.code }}</td>
                              <td class="px-2 py-2 border-r border-slate-100 truncate max-w-[100px]" :title="item.name">{{ item.name }}</td>
                              <td class="px-2 py-2 border-r border-slate-100 text-right">{{ getBasePrice(item).toLocaleString() }}</td>
                              <td class="px-2 py-2 border-r border-slate-100 text-right text-red-500">{{ (getBasePrice(item) - calculateNewPrice(item)).toLocaleString() }}</td>
                              <td class="px-2 py-2 border-r border-slate-100 text-right font-medium text-[#78C5E7]">{{ calculateNewPrice(item).toLocaleString() }}</td>
                              <td class="px-2 py-2 text-center">
                                  <button @click="removeProduct(idx)" class="text-red-500 hover:text-red-700" title="Xóa">
                                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                  </button>
                              </td>
                          </tr>
                          <tr v-if="promotionProducts.length === 0">
                              <td colspan="5" class="px-2 py-8 text-center text-slate-400">Chưa có sản phẩm nào</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-xl shrink-0">
        <button @click="$emit('close')" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 transition-colors">
          Hủy
        </button>
        <button @click="handleSave" class="px-4 py-2 text-sm font-medium text-white bg-[#78C5E7] border border-transparent rounded-lg hover:bg-[#60b3d6] focus:outline-none focus:ring-2 focus:ring-[#78C5E7] focus:ring-offset-2 transition-colors">
          Lưu
        </button>
      </div>
    </div>

    <!-- Select items modal -->
    <SelectPromotionItemsModal 
        v-if="showSelectItemsModal"
        :show="showSelectItemsModal"
        @close="showSelectItemsModal = false"
        @select="addSelectedItems"
    />
  </div>
</template>
