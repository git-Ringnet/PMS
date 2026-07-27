<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'save'])

const activeTab = ref('info') // 'info' or 'price'

// Form data (mock)
const formData = ref({
  code: '',
  name: '',
  nameEn: '',
  menuType: '',
  serviceGroup: '',
  unit: '',
  printer: '',
  price: 0,
  inventory: false,
  alcoholic: false,
  isSelling: true
})

</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col overflow-hidden max-h-[90vh]">
      <!-- Header -->
      <div class="px-5 py-4 bg-[#f8f9fa] border-b border-slate-200 flex justify-between items-center shrink-0">
        <h3 class="font-bold text-slate-800 text-lg">Thêm thực đơn</h3>
        <button @click="emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-auto flex flex-col bg-white">
        <!-- Tabs -->
        <div class="flex border-b border-slate-200 px-5 pt-3 gap-6 shrink-0 bg-[#f8f9fa]">
          <button 
            class="pb-3 text-sm font-medium transition-colors relative"
            :class="activeTab === 'info' ? 'text-sky-500' : 'text-slate-500 hover:text-slate-700'"
            @click="activeTab = 'info'"
          >
            Thông tin
            <div v-if="activeTab === 'info'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-sky-500 rounded-t"></div>
          </button>
          <button 
            class="pb-3 text-sm font-medium transition-colors relative"
            :class="activeTab === 'price' ? 'text-sky-500' : 'text-slate-500 hover:text-slate-700'"
            @click="activeTab = 'price'"
          >
            Đơn giá
            <div v-if="activeTab === 'price'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-sky-500 rounded-t"></div>
          </button>
        </div>

        <!-- Tab Content: Info -->
        <div v-if="activeTab === 'info'" class="p-6 grid grid-cols-3 gap-6 flex-1 overflow-auto">
          <div class="col-span-2 grid grid-cols-2 gap-x-6 gap-y-5">
            <!-- Left Column of Info -->
            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Mã <span class="text-red-500">*</span></label>
              <div class="relative">
                <input type="text" class="w-full pl-3 pr-8 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 bg-[#f8f9fa]" />
                <button class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
              </div>
            </div>
            
            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Tên <span class="text-red-500">*</span></label>
              <input type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500" />
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Tên tiếng Anh</label>
              <input type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500" />
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Loại thực đơn <span class="text-red-500">*</span></label>
              <select class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 bg-white">
                <option value=""></option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Nhóm dịch vụ <span class="text-red-500">*</span></label>
              <select class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 bg-white">
                <option value=""></option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">ĐVT</label>
              <select class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 bg-white">
                <option value=""></option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Máy in</label>
              <select class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 bg-white">
                <option value=""></option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-700">Đơn giá</label>
              <div class="relative">
                <input type="text" value="0" class="w-full pl-3 pr-8 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-sky-500 text-right" />
                <button class="absolute right-2 top-2.5 text-sky-400 hover:text-sky-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </button>
              </div>
            </div>

            <!-- Toggles -->
            <div class="col-span-2 flex gap-8 mt-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" class="sr-only" v-model="formData.inventory">
                  <div class="block w-10 h-6 rounded-full transition-colors" :class="formData.inventory ? 'bg-sky-500' : 'bg-slate-300'"></div>
                  <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="formData.inventory ? 'translate-x-4' : ''"></div>
                </div>
                <span class="text-sm font-medium text-slate-700">Tồn kho</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <div class="relative">
                  <input type="checkbox" class="sr-only" v-model="formData.alcoholic">
                  <div class="block w-10 h-6 rounded-full transition-colors" :class="formData.alcoholic ? 'bg-sky-500' : 'bg-slate-300'"></div>
                  <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="formData.alcoholic ? 'translate-x-4' : ''"></div>
                </div>
                <span class="text-sm font-medium text-slate-700">Có cồn</span>
              </label>
            </div>
          </div>

          <!-- Right Column: Image Upload -->
          <div class="col-span-1">
            <div class="border-2 border-dashed border-slate-300 rounded-xl h-48 flex flex-col items-center justify-center bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer group">
              <div class="w-12 h-12 bg-white rounded-full shadow-sm flex items-center justify-center mb-3 group-hover:shadow-md transition-shadow">
                <svg class="w-6 h-6 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
              </div>
              <span class="text-sm text-slate-500 font-medium">Tải hình ảnh lên</span>
            </div>
          </div>
        </div>

        <!-- Tab Content: Price -->
        <div v-if="activeTab === 'price'" class="p-6 flex-1 overflow-auto bg-white">
          <!-- Outlet Blocks -->
          <div class="space-y-6">
            <!-- Hội Nghị Block -->
            <div class="border-b border-slate-200 pb-6">
              <!-- Block Header -->
              <div class="flex items-end gap-8 mb-4">
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Mở bán</label>
                  <label class="flex items-center cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" class="sr-only" checked>
                      <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                      <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                    </div>
                  </label>
                </div>
                <div class="space-y-2 w-48">
                  <label class="text-sm text-slate-700">Outlet</label>
                  <input type="text" readonly value="Hội Nghị" class="w-full px-3 py-1.5 bg-slate-100 border border-slate-200 rounded text-sm text-slate-700 focus:outline-none" />
                </div>
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Cập nhật giá outlet</label>
                  <div class="flex justify-center">
                    <label class="flex items-center cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" class="sr-only" checked>
                        <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                      </div>
                    </label>
                  </div>
                </div>
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Cập nhật giá combo</label>
                  <div class="flex justify-center">
                    <label class="flex items-center cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" class="sr-only" checked>
                        <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Price Inputs -->
              <div class="space-y-4">
                <!-- Outlet Row -->
                <div class="flex items-center gap-4">
                  <div class="w-16 font-bold text-sm text-slate-800">Outlet<span class="text-red-500">*</span></div>
                  <div class="flex-1 grid grid-cols-5 gap-4">
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Giá gốc</label>
                      <div class="relative">
                        <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px] hover:bg-sky-400">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px] hover:bg-sky-400">▼</button>
                        </div>
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Phí dịch vụ</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="5" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Thuế Đặc Biệt</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="0" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">VAT</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="8" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Đơn giá</label>
                      <div class="relative">
                        <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Combo Row -->
                <div class="flex items-center gap-4">
                  <div class="w-16 font-bold text-sm text-slate-800">Combo<span class="text-red-500">*</span></div>
                  <div class="flex-1 grid grid-cols-5 gap-4">
                    <div class="relative">
                      <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                      <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                        <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                        <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="5" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="0" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="8" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="relative">
                      <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                      <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                        <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                        <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Nhà Hàng Block -->
            <div class="border-b border-slate-200 pb-6">
              <!-- Block Header -->
              <div class="flex items-end gap-8 mb-4">
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Mở bán</label>
                  <label class="flex items-center cursor-pointer">
                    <div class="relative">
                      <input type="checkbox" class="sr-only" checked>
                      <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                      <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                    </div>
                  </label>
                </div>
                <div class="space-y-2 w-48">
                  <label class="text-sm text-slate-700">Outlet</label>
                  <input type="text" readonly value="Nhà Hàng" class="w-full px-3 py-1.5 bg-slate-100 border border-slate-200 rounded text-sm text-slate-700 focus:outline-none" />
                </div>
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Cập nhật giá outlet</label>
                  <div class="flex justify-center">
                    <label class="flex items-center cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" class="sr-only" checked>
                        <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                      </div>
                    </label>
                  </div>
                </div>
                <div class="space-y-2">
                  <label class="text-sm text-slate-700">Cập nhật giá combo</label>
                  <div class="flex justify-center">
                    <label class="flex items-center cursor-pointer">
                      <div class="relative">
                        <input type="checkbox" class="sr-only" checked>
                        <div class="block w-12 h-7 rounded-full bg-sky-300"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-5 h-5 rounded-full translate-x-5"></div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Price Inputs -->
              <div class="space-y-4">
                <!-- Outlet Row -->
                <div class="flex items-center gap-4">
                  <div class="w-16 font-bold text-sm text-slate-800">Outlet<span class="text-red-500">*</span></div>
                  <div class="flex-1 grid grid-cols-5 gap-4">
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Giá gốc</label>
                      <div class="relative">
                        <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                        </div>
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Phí dịch vụ</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="5" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Thuế Đặc Biệt</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="0" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">VAT</label>
                      <div class="flex gap-2">
                        <div class="relative w-1/2">
                          <input type="text" value="8" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                          <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                            <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                            <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                          </div>
                        </div>
                        <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                      </div>
                    </div>
                    <div class="space-y-1">
                      <label class="text-xs text-slate-600">Đơn giá</label>
                      <div class="relative">
                        <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Combo Row -->
                <div class="flex items-center gap-4">
                  <div class="w-16 font-bold text-sm text-slate-800">Combo<span class="text-red-500">*</span></div>
                  <div class="flex-1 grid grid-cols-5 gap-4">
                    <div class="relative">
                      <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                      <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                        <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                        <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                      </div>
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="5" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="0" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="flex gap-2">
                      <div class="relative w-1/2">
                        <input type="text" value="8" class="w-full px-2 py-1.5 border border-slate-200 rounded text-sm text-center" />
                        <div class="absolute right-0.5 top-0.5 bottom-0.5 flex flex-col justify-between">
                          <button class="bg-sky-300 text-white rounded-t px-1 py-0.5 text-[8px]">▲</button>
                          <button class="bg-sky-300 text-white rounded-b px-1 py-0.5 text-[8px]">▼</button>
                        </div>
                      </div>
                      <input type="text" value="0" readonly class="w-1/2 px-2 py-1.5 bg-slate-50 border border-slate-200 rounded text-sm text-center text-slate-400" />
                    </div>
                    <div class="relative">
                      <input type="text" value="0" class="w-full px-3 py-1.5 border border-slate-200 rounded text-sm text-center" />
                      <div class="absolute right-1 top-1 bottom-1 flex flex-col justify-between">
                        <button class="bg-sky-300 text-white rounded-t px-1.5 py-0.5 text-[10px]">▲</button>
                        <button class="bg-sky-300 text-white rounded-b px-1.5 py-0.5 text-[10px]">▼</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 bg-[#f8f9fa] border-t border-slate-200 flex justify-end gap-3 shrink-0">
        <button @click="emit('close')" class="px-5 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium transition-colors">
          Hủy
        </button>
        <button @click="emit('save')" class="px-5 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-medium transition-colors">
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Smooth transition for toggle dots */
.dot {
  transition: transform 0.2s ease-in-out;
}
</style>
