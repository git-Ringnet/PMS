<script setup>
import { ref } from 'vue'
import { HelpCircle, X, Calendar, Plus, Info } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  bookingInfo: {
    type: String,
    default: 'BK GAL6131 - KHÁCH LẺ - Test 1'
  }
})

const emit = defineEmits(['close', 'submit'])

const activeTab = ref('service') // 'service' | 'room'

// Form state - Tab 1 (Dịch vụ)
const serviceDate = ref('09 / 07 / 2026 ~ 09 / 07 / 2026')
const currency = ref('VND')
const folio = ref('1')
const selectedService = ref('')
const quantity = ref(1)
const description = ref('')
const unitPrice = ref(0)
const preTaxPrice = ref(0)
const totalPrice = ref(0)

// Form state - Tab 2 (Tiền phòng)
const roomAutoType = ref('Giá tương ứng cho mỗi phòng')
const roomAutoToggle = ref(false)
const roomPreTaxPrice = ref(0)
const roomTotalPrice = ref(0)
const roomDescription = ref('Dịch vụ phòng nghỉ')

const handleClose = () => {
  emit('close')
}

const handleSubmit = () => {
  emit('submit', {
    activeTab: activeTab.value,
    serviceDate: serviceDate.value,
    currency: currency.value,
    folio: folio.value,
    selectedService: selectedService.value,
    quantity: quantity.value,
    description: description.value,
    unitPrice: unitPrice.value,
    totalPrice: totalPrice.value
  })
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-300 flex flex-col text-xs">
      
      <!-- Header -->
      <div class="bg-[#7dd3fc] text-white px-3 py-2 flex items-center justify-between font-semibold">
        <span class="text-sm font-bold">Thêm dịch vụ</span>
        <div class="flex items-center gap-2">
          <button class="hover:text-gray-100"><HelpCircle class="w-4 h-4" /></button>
          <button @click="handleClose" class="hover:text-gray-100"><X class="w-4 h-4" /></button>
        </div>
      </div>

      <!-- Body Content -->
      <div class="p-4 space-y-3">
        <!-- Đăng ký Textbox -->
        <div>
          <label class="block font-bold text-gray-700 mb-1">Đăng ký</label>
          <input 
            type="text" 
            :value="bookingInfo" 
            readonly 
            class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-gray-800 font-semibold"
          />
        </div>

        <!-- Sub Tabs (Dịch vụ | Tiền phòng) -->
        <div class="border-b border-gray-200 flex gap-4">
          <button 
            @click="activeTab = 'service'"
            :class="[
              activeTab === 'service' ? 'border-b-2 border-sky-500 text-sky-600 font-bold' : 'text-gray-500 hover:text-gray-700',
              'pb-2 px-1 text-xs transition-colors'
            ]"
          >
            Dịch vụ
          </button>
          <button 
            @click="activeTab = 'room'"
            :class="[
              activeTab === 'room' ? 'border-b-2 border-sky-500 text-sky-600 font-bold' : 'text-gray-500 hover:text-gray-700',
              'pb-2 px-1 text-xs transition-colors'
            ]"
          >
            Tiền phòng
          </button>
        </div>

        <!-- TAB 1: DỊCH VỤ -->
        <div v-if="activeTab === 'service'" class="border border-gray-300 rounded-lg p-3 space-y-3 bg-gray-50/50">
          <!-- Row 1: Ngày, Tiền tệ, Folio -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-2 font-bold text-gray-700">Ngày</label>
            <div class="col-span-5 relative">
              <input 
                type="text" 
                v-model="serviceDate"
                class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" 
              />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2" />
            </div>

            <div class="col-span-5 flex items-center justify-end gap-3">
              <div class="flex items-center gap-1">
                <span class="font-medium text-gray-700">Tiền tệ</span>
                <div class="flex items-center gap-1 bg-white border border-gray-300 px-2 py-1 rounded">
                  <span class="w-3 h-3 bg-red-600 rounded-full flex items-center justify-center text-[8px] text-yellow-300">★</span>
                  <select v-model="currency" class="bg-transparent focus:outline-none font-semibold">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>

              <div class="flex items-center gap-1">
                <span class="font-medium text-gray-700">Folio</span>
                <select v-model="folio" class="bg-[#fef9c3] border border-gray-300 px-2 py-1 rounded font-bold">
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="A">A</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Row 2: Dịch vụ, Số lượng -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-2 font-bold text-gray-700">Dịch vụ</label>
            <div class="col-span-6">
              <select v-model="selectedService" class="w-full bg-[#fef9c3] border border-gray-300 px-2.5 py-1 rounded text-gray-500">
                <option value="">Select Value</option>
              </select>
            </div>

            <div class="col-span-4 flex items-center justify-end gap-2">
              <label class="font-bold text-gray-700">Số lượng</label>
              <input 
                type="number" 
                v-model="quantity" 
                class="w-20 bg-[#fef9c3] border border-gray-300 px-2 py-1 rounded text-center font-bold" 
              />
            </div>
          </div>

          <!-- Row 3: Mô tả -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-2 font-bold text-gray-700">Mô tả</label>
            <div class="col-span-10">
              <input 
                type="text" 
                v-model="description"
                class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded focus:outline-none focus:border-sky-500" 
              />
            </div>
          </div>

          <!-- Inner Card Box: Đơn giá, Giá trước thuế, Tổng tiền -->
          <div class="border border-gray-300 rounded p-3 bg-white grid grid-cols-2 gap-4 relative">
            <div>
              <label class="block font-bold text-gray-700 mb-1">Đơn giá</label>
              <input 
                type="text" 
                v-model="unitPrice" 
                class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-bold" 
              />
            </div>
            <div class="space-y-2">
              <div>
                <label class="block font-bold text-gray-700 mb-1">Giá trước thuế</label>
                <input 
                  type="text" 
                  v-model="preTaxPrice" 
                  class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-bold" 
                />
              </div>
              <div>
                <label class="block font-bold text-gray-700 mb-1">Tổng tiền</label>
                <input 
                  type="text" 
                  v-model="totalPrice" 
                  class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-bold" 
                />
              </div>
            </div>

            <!-- Info Icon -->
            <Info class="w-4 h-4 text-sky-400 absolute right-2 bottom-2" />
          </div>
        </div>

        <!-- TAB 2: TIỀN PHÒNG -->
        <div v-else class="border border-gray-300 rounded-lg p-3 space-y-3 bg-gray-50/50">
          <!-- Row 1: Ngày, Tiền tệ, Folio -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-2 font-bold text-gray-700">Ngày</label>
            <div class="col-span-5 relative">
              <input 
                type="text" 
                v-model="serviceDate"
                class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded text-xs focus:outline-none focus:border-sky-500" 
              />
              <Calendar class="w-3.5 h-3.5 text-emerald-600 absolute right-2 top-1/2 -translate-y-1/2" />
            </div>

            <div class="col-span-5 flex items-center justify-end gap-3">
              <div class="flex items-center gap-1">
                <span class="font-medium text-gray-700">Tiền tệ</span>
                <div class="flex items-center gap-1 bg-white border border-gray-300 px-2 py-1 rounded">
                  <span class="w-3 h-3 bg-red-600 rounded-full flex items-center justify-center text-[8px] text-yellow-300">★</span>
                  <select v-model="currency" class="bg-transparent focus:outline-none font-semibold">
                    <option value="VND">VND</option>
                  </select>
                </div>
              </div>

              <div class="flex items-center gap-1">
                <span class="font-medium text-gray-700">Folio</span>
                <select v-model="folio" class="bg-[#fef9c3] border border-gray-300 px-2 py-1 rounded font-bold">
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="A">A</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Row 2: Tiền phòng tự động -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-3 font-bold text-gray-700">Tiền phòng tự động</label>
            <div class="col-span-5">
              <input 
                type="text" 
                v-model="roomAutoType"
                readonly
                class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded text-xs text-gray-700" 
              />
            </div>
            <div class="col-span-4 flex items-center justify-end gap-2">
              <span class="font-bold text-gray-700">Tiền phòng tự động</span>
              <button 
                @click="roomAutoToggle = !roomAutoToggle"
                :class="[roomAutoToggle ? 'bg-sky-500' : 'bg-gray-300', 'w-9 h-5 rounded-full transition-colors relative']"
              >
                <div :class="[roomAutoToggle ? 'translate-x-4' : 'translate-x-0.5', 'w-4 h-4 bg-white rounded-full transition-transform']"></div>
              </button>
            </div>
          </div>

          <!-- Fieldset Box: Nhập tiền phòng -->
          <fieldset class="border border-gray-300 rounded p-3 bg-white relative">
            <legend class="px-2 font-bold text-gray-500 text-xs">Nhập tiền phòng</legend>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-gray-500 mb-1">Giá trước thuế</label>
                <input 
                  type="text" 
                  v-model="roomPreTaxPrice" 
                  class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-bold" 
                />
              </div>
              <div>
                <label class="block font-bold text-gray-500 mb-1">Tổng tiền</label>
                <input 
                  type="text" 
                  v-model="roomTotalPrice" 
                  class="w-full px-2.5 py-1 bg-gray-100 border border-gray-300 rounded font-bold" 
                />
              </div>
            </div>
            <Info class="w-4 h-4 text-sky-400 absolute right-2 bottom-2" />
          </fieldset>

          <!-- Row 3: Mô tả -->
          <div class="grid grid-cols-12 gap-3 items-center">
            <label class="col-span-2 font-bold text-gray-700">Mô tả</label>
            <div class="col-span-10">
              <input 
                type="text" 
                v-model="roomDescription"
                class="w-full px-2.5 py-1 bg-white border border-gray-300 rounded focus:outline-none focus:border-sky-500" 
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Divider & Actions -->
      <div class="border-t border-gray-300 p-3 flex justify-end bg-gray-50">
        <button 
          @click="handleSubmit" 
          class="bg-[#38bdf8] hover:bg-sky-500 text-white px-4 py-1.5 rounded flex items-center gap-1.5 font-bold shadow-xs transition-colors cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Thêm</span>
        </button>
      </div>

    </div>
  </div>
</template>
