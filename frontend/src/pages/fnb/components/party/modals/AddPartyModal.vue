<script setup>
import { ref } from 'vue'
import AddRevenueGroupModal from './AddRevenueGroupModal.vue'
import AddCrmCustomerModal from './AddCrmCustomerModal.vue'
import AddTravelCompanyModal from './AddTravelCompanyModal.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'save', 'addProduct'])

// Nested Modals State
const isAddRevenueGroupOpen = ref(false)
const isAddCrmCustomerOpen = ref(false)
const isAddTravelCompanyOpen = ref(false)

// Form State
const form = ref({
  shift: '',
  outlet: '',
  revenueGroup: '',
  customerName: '',
  phone: '',
  travelCompany: '',
  arrivalDate: '',
  arrivalTime: '',
  departureTime: '',
  guestCount: 1,
  tableCount: 1,
  partyType: '',
  status: 'Mới',
  taxCode: '',
  invoiceName: '',
  invoiceAddress: '',
  note: '',
  internalNote: ''
})

const errors = ref({})
const hasAttemptedSave = ref(false)

// Deposit State
const deposits = ref([])
const newDeposit = ref({
  date: '',
  method: '',
  amount: ''
})
const depositErrors = ref({})

const addDeposit = () => {
  depositErrors.value = {}
  if (!newDeposit.value.date) depositErrors.value.date = true
  if (!newDeposit.value.method) depositErrors.value.method = true
  if (!newDeposit.value.amount) depositErrors.value.amount = true
  
  if (Object.keys(depositErrors.value).length > 0) return
  
  deposits.value.push({ ...newDeposit.value })
  newDeposit.value = { date: '', method: '', amount: '' }
}

const removeDeposit = (index) => {
  deposits.value.splice(index, 1)
}

const handleSave = () => {
  hasAttemptedSave.value = true
  errors.value = {}
  
  if (!form.value.shift) errors.value.shift = true
  if (!form.value.outlet) errors.value.outlet = true
  if (!form.value.customerName) errors.value.customerName = true
  
  if (Object.keys(errors.value).length > 0) {
    return
  }
  
  emit('save', { ...form.value, deposits: deposits.value })
  handleClose()
}

const handleClose = () => {
  emit('close')
  hasAttemptedSave.value = false
  errors.value = {}
}

const increment = (field) => form.value[field]++
const decrement = (field) => { if (form.value[field] > 1) form.value[field]-- }
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[50] flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200 my-auto">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80 shrink-0">
        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
          <svg class="w-6 h-6 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
          Thêm đặt tiệc
        </h2>
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors p-2 rounded-lg hover:bg-slate-100">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
      
      <!-- Body -->
      <div class="p-6 overflow-y-auto flex-1 space-y-8 bg-white">
        <!-- Error Banner -->
        <div v-if="hasAttemptedSave && Object.keys(errors).length > 0" class="bg-red-50 text-red-600 p-4 rounded-lg flex items-start gap-3 border border-red-100">
          <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <div>
            <p class="font-semibold text-sm">Vui lòng điền đầy đủ các thông tin bắt buộc:</p>
            <ul class="list-disc ml-5 mt-1 text-sm">
              <li v-if="errors.shift">Vui lòng chọn Ca</li>
              <li v-if="errors.outlet">Vui lòng chọn Outlet</li>
              <li v-if="errors.customerName">Vui lòng nhập Tên khách hàng</li>
            </ul>
          </div>
        </div>

        <section>
          <h3 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">Thông tin chung</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ca <span class="text-red-500">*</span></label>
              <select v-model="form.shift" :class="{'border-red-500 ring-1 ring-red-500': errors.shift}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm bg-white">
                <option value="">Chọn ca</option>
                <option value="Sáng">Sáng</option>
                <option value="Trưa">Trưa</option>
                <option value="Tối">Tối</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Outlet <span class="text-red-500">*</span></label>
              <select v-model="form.outlet" :class="{'border-red-500 ring-1 ring-red-500': errors.outlet}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm bg-white">
                <option value="">Chọn outlet</option>
                <option value="Nhà Hàng A">Nhà Hàng A</option>
                <option value="Nhà Hàng B">Nhà Hàng B</option>
              </select>
            </div>
            <div class="lg:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nhóm doanh thu</label>
              <div class="flex items-center gap-2">
                <select v-model="form.revenueGroup" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm bg-white">
                  <option value="">Chọn nhóm doanh thu</option>
                  <option v-if="form.revenueGroup" :value="form.revenueGroup">{{ form.revenueGroup }}</option>
                </select>
                <button @click="isAddRevenueGroupOpen = true" class="w-9 h-9 flex items-center justify-center shrink-0 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-100 transition-colors border border-sky-100">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </button>
              </div>
            </div>

            <div class="lg:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tên khách hàng (CRM) <span class="text-red-500">*</span></label>
              <div class="flex items-center gap-2">
                <input v-model="form.customerName" :class="{'border-red-500 ring-1 ring-red-500': errors.customerName}" type="text" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập tên khách hàng" />
                <button @click="isAddCrmCustomerOpen = true" class="w-9 h-9 flex items-center justify-center shrink-0 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-100 transition-colors border border-sky-100">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Số điện thoại</label>
              <input v-model="form.phone" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập số điện thoại" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Trạng thái</label>
              <select v-model="form.status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm bg-white">
                <option value="Mới">Mới</option>
                <option value="Xác nhận">Xác nhận</option>
                <option value="Hoàn thành">Hoàn thành</option>
                <option value="Hủy">Hủy</option>
              </select>
            </div>

            <div class="lg:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tên công ty du lịch</label>
              <div class="flex items-center gap-2">
                <input v-model="form.travelCompany" type="text" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Chọn hoặc nhập tên công ty" />
                <button @click="isAddTravelCompanyOpen = true" class="w-9 h-9 flex items-center justify-center shrink-0 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-100 transition-colors border border-sky-100">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Loại tiệc</label>
              <select v-model="form.partyType" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm bg-white">
                <option value="">Chọn loại tiệc</option>
                <option value="Sinh nhật">Sinh nhật</option>
                <option value="Cưới hỏi">Cưới hỏi</option>
                <option value="Hội nghị">Hội nghị</option>
                <option value="Khác">Khác</option>
              </select>
            </div>
            <div class="col-span-1 hidden lg:block"></div> <!-- Spacer -->

            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ngày đến</label>
              <input v-model="form.arrivalDate" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Giờ đến</label>
              <input v-model="form.arrivalTime" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Giờ đi (Dự kiến)</label>
              <input v-model="form.departureTime" type="time" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
            </div>
            <div class="col-span-1 hidden lg:block"></div>

            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Số lượng khách</label>
              <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden bg-white">
                <button @click="decrement('guestCount')" class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 border-r border-slate-300 transition-colors">-</button>
                <input v-model="form.guestCount" type="number" min="1" class="w-full px-3 py-2 text-center focus:outline-none text-sm" />
                <button @click="increment('guestCount')" class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 border-l border-slate-300 transition-colors">+</button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Số lượng bàn</label>
              <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden bg-white">
                <button @click="decrement('tableCount')" class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 border-r border-slate-300 transition-colors">-</button>
                <input v-model="form.tableCount" type="number" min="1" class="w-full px-3 py-2 text-center focus:outline-none text-sm" />
                <button @click="increment('tableCount')" class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 border-l border-slate-300 transition-colors">+</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Hóa đơn / Ghi chú -->
        <section>
           <h3 class="text-lg font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">Thông tin hóa đơn & Ghi chú</h3>
           <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mã số thuế</label>
                <input v-model="form.taxCode" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
              </div>
              <div class="lg:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tên xuất hóa đơn</label>
                <input v-model="form.invoiceName" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
              </div>
              <div class="lg:col-span-3">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Địa chỉ xuất hóa đơn</label>
                <input v-model="form.invoiceAddress" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" />
              </div>
              
              <div class="lg:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ghi chú</label>
                <textarea v-model="form.note" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Nhập nội dung ghi chú..."></textarea>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Ghi chú nội bộ</label>
                <textarea v-model="form.internalNote" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all text-sm" placeholder="Ghi chú nội bộ..."></textarea>
              </div>
           </div>
        </section>

        <!-- Quản lý đặt cọc -->
        <section>
          <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-2">
            <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">Quản lý đặt cọc</h3>
          </div>
          
          <!-- Add Deposit Form -->
          <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-4 flex flex-wrap lg:flex-nowrap items-end gap-4">
            <div class="w-full lg:w-40">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Ngày cọc <span class="text-red-500">*</span></label>
              <input v-model="newDeposit.date" type="date" :class="{'border-red-500': depositErrors.date}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none text-sm" />
            </div>
            <div class="w-full lg:w-48">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Phương thức <span class="text-red-500">*</span></label>
              <select v-model="newDeposit.method" :class="{'border-red-500': depositErrors.method}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none text-sm bg-white">
                <option value="">Chọn PT</option>
                <option value="Tiền mặt">Tiền mặt</option>
                <option value="Chuyển khoản">Chuyển khoản</option>
                <option value="Thẻ tín dụng">Thẻ tín dụng</option>
              </select>
            </div>
            <div class="flex-1 w-full">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Số tiền <span class="text-red-500">*</span></label>
              <input v-model="newDeposit.amount" type="number" :class="{'border-red-500': depositErrors.amount}" placeholder="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none text-sm" />
            </div>
            <div class="w-full lg:w-auto">
              <button @click="addDeposit" class="w-full lg:w-auto px-4 py-2 text-sm font-medium text-white bg-teal-500 rounded-lg hover:bg-teal-600 transition-all shadow-sm">
                Thêm vào danh sách
              </button>
            </div>
          </div>
          
          <!-- Deposit List -->
          <div v-if="deposits.length > 0" class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm text-left text-slate-600">
              <thead class="text-xs uppercase bg-slate-100 text-slate-500">
                <tr>
                  <th class="px-4 py-3 font-semibold">STT</th>
                  <th class="px-4 py-3 font-semibold">Ngày cọc</th>
                  <th class="px-4 py-3 font-semibold">Phương thức</th>
                  <th class="px-4 py-3 font-semibold text-right">Số tiền</th>
                  <th class="px-4 py-3 font-semibold text-center w-20">Xóa</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200">
                <tr v-for="(dep, index) in deposits" :key="index" class="bg-white hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3">{{ index + 1 }}</td>
                  <td class="px-4 py-3 font-medium">{{ dep.date }}</td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-slate-100 rounded text-xs font-medium">{{ dep.method }}</span>
                  </td>
                  <td class="px-4 py-3 text-right font-semibold text-emerald-600">{{ Number(dep.amount).toLocaleString('vi-VN') }} ₫</td>
                  <td class="px-4 py-3 text-center">
                    <button @click="removeDeposit(index)" class="text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center py-8 bg-slate-50/50 rounded-lg border border-dashed border-slate-300">
            <p class="text-sm text-slate-400">Chưa có dữ liệu đặt cọc</p>
          </div>
        </section>
      </div>
      
      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0">
        <button class="px-5 py-2.5 text-sm font-semibold text-sky-600 bg-sky-50 border border-sky-200 rounded-lg hover:bg-sky-100 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Thêm sản phẩm
        </button>
        <div class="flex items-center gap-3">
          <button @click="handleClose" class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-all shadow-sm">
            Hủy
          </button>
          <button @click="handleSave" class="px-5 py-2.5 text-sm font-semibold text-white bg-sky-500 rounded-lg hover:bg-sky-600 transition-all shadow-sm shadow-sky-200 hover:shadow-sky-300">
            Lưu
          </button>
        </div>
      </div>
    </div>

    <AddRevenueGroupModal :isOpen="isAddRevenueGroupOpen" @close="isAddRevenueGroupOpen = false" @save="(val) => { form.revenueGroup = val.name; isAddRevenueGroupOpen = false }" />
    <AddCrmCustomerModal :isOpen="isAddCrmCustomerOpen" @close="isAddCrmCustomerOpen = false" @save="(val) => { form.customerName = val.name; form.phone = val.phone; isAddCrmCustomerOpen = false }" />
    <AddTravelCompanyModal :isOpen="isAddTravelCompanyOpen" @close="isAddTravelCompanyOpen = false" @save="(val) => { form.travelCompany = val.name; isAddTravelCompanyOpen = false }" />
  </div>
</template>
