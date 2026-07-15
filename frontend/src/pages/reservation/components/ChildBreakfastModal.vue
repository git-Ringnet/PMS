<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs select-none"
    @click.self="close"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[1000px] overflow-hidden border border-gray-300 flex flex-col max-h-[90vh]"
    >
      <!-- HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0">
        <div class="flex items-center space-x-2 font-semibold text-sm uppercase tracking-wider">
          <i class="fa-solid fa-mug-saucer text-sky-300"></i>
          <span>Chi tiết ăn sáng - PHÒNG {{ room?.roomNumber || 'CHƯA GÁN' }} ({{ room?.type }})</span>
        </div>
        <div class="flex items-center space-x-2">
          <!-- Help Icon -->
          <i class="fa-solid fa-circle-question text-slate-300 hover:text-white text-lg cursor-pointer transition mr-1" title="Hướng dẫn tính phụ phí"></i>
          <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
            <i class="fa-solid fa-xmark text-red-400"></i>
          </button>
        </div>
      </div>

      <!-- BODY -->
      <div class="flex-1 overflow-y-auto p-5 space-y-4">
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-10 space-y-2">
          <i class="fa-solid fa-circle-notch fa-spin text-sky-500 text-3xl"></i>
          <span class="text-xs text-slate-500 font-semibold">Đang tải chi tiết ăn sáng trẻ em...</span>
        </div>

        <div v-else-if="localChildren.length === 0" class="bg-slate-50 border border-slate-200 rounded-lg p-8 text-center text-slate-500 text-xs">
          <i class="fa-solid fa-circle-info text-slate-400 text-lg mb-1 block"></i>
          Phòng này chưa đăng ký trẻ em hoặc em bé nào trong thông tin khách hàng.
        </div>

        <div v-else class="border border-slate-200 rounded-lg overflow-hidden">
          <table class="w-full border-collapse text-left text-xs">
            <thead>
              <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold h-10">
                <th class="p-3 w-[250px]">Ngày</th>
                <th class="p-3 w-[160px] text-right">Thành Tiền</th>
                <th class="p-3 w-[110px] text-center">Ăn sáng</th>
                <th class="p-3 w-[110px] text-center">Miễn phí</th>
                <th class="p-3 w-[110px] text-center">Phụ Phí</th>
                <th class="p-3 w-[130px] text-center">FIT/GIT</th>
              </tr>
            </thead>
            <tbody>
              <!-- EMBE (BABY) SECTION -->
              <template v-if="babies.length > 0">
                <!-- Group Header -->
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold h-9">
                  <td colspan="6" class="p-3 pr-4">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center cursor-pointer" @click="isBabyGroupExpanded = !isBabyGroupExpanded">
                        <i 
                          class="fa-solid mr-2 text-sky-600"
                          :class="isBabyGroupExpanded ? 'fa-square-minus' : 'fa-square-plus'"
                        ></i>
                        <span>Em bé</span>
                      </div>
                      <span class="text-[10px] text-slate-400 font-normal">({{ babies.length }} bé)</span>
                    </div>
                  </td>
                </tr>

                <!-- Baby Rows -->
                <template v-if="isBabyGroupExpanded" v-for="child in babies" :key="child.id">
                  <!-- Parent Row -->
                  <tr class="border-b border-slate-200 h-12 hover:bg-slate-50/50">
                    <td class="p-3 pl-6 font-semibold text-slate-700">
                      <div class="flex items-center cursor-pointer" @click="toggleExpand(child.id)">
                        <i 
                          class="fa-solid mr-2 text-blue-500"
                          :class="isExpanded(child.id) ? 'fa-square-minus' : 'fa-square-plus'"
                        ></i>
                        <span>{{ child.full_name }}</span>
                      </div>
                    </td>
                    <!-- Parent Amount (Bulk) -->
                    <td class="p-3 text-right">
                      <div class="flex items-center justify-end">
                        <input 
                          type="text" 
                          :value="formatCurrency(child.amount)"
                          @input="e => onParentFieldChange(child, 'amount', cleanCurrency(e.target.value))"
                          class="w-28 text-right border border-slate-300 rounded-md py-1 px-2 focus:outline-none focus:ring-1 focus:ring-sky-500 font-semibold text-slate-800 text-xs"
                          placeholder="0"
                        />
                        <span class="ml-1.5 text-[10px] text-slate-400 font-bold">đ</span>
                      </div>
                    </td>
                    <!-- Parent Breakfast Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.breakfast"
                          @change="e => onParentFieldChange(child, 'breakfast', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent Free Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.is_free"
                          @change="e => onParentFieldChange(child, 'is_free', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent Extra Charge Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.is_extra_charge"
                          @change="e => onParentFieldChange(child, 'is_extra_charge', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent FIT/GIT Toggle -->
                    <td class="p-3 text-center flex justify-center items-center h-12">
                      <div 
                        @click="onParentFieldChange(child, 'is_room', !child.is_room)"
                        class="w-14 h-5 rounded-full cursor-pointer relative transition-colors duration-200 flex items-center justify-between px-2 text-[9px] font-black text-white select-none shadow-inner"
                        :class="child.is_room ? 'bg-sky-500' : 'bg-slate-400'"
                      >
                        <span class="z-10" :class="child.is_room ? 'text-white' : 'text-slate-300/40'">FIT</span>
                        <span class="z-10" :class="!child.is_room ? 'text-white' : 'text-slate-300/40'">GIT</span>
                        <div 
                          class="absolute w-3.5 h-3.5 bg-white rounded-full transition-transform duration-200 shadow-md top-[3px] left-[3px]" 
                          :style="{ transform: child.is_room ? 'translateX(30px)' : 'translateX(0)' }"
                        ></div>
                      </div>
                    </td>
                  </tr>

                  <!-- Child Date Rows (Expanded) -->
                  <template v-if="isExpanded(child.id)" v-for="d in child.breakfast_details" :key="d.id">
                    <tr class="bg-slate-50/30 border-b border-slate-100 h-10 text-[11px]">
                      <td class="p-2 pl-12 font-mono text-slate-500">
                        <i class="fa-regular fa-calendar-days mr-1.5 text-slate-400"></i>
                        {{ formatDateVi(d.service_date) }}
                      </td>
                      <!-- Amount -->
                      <td class="p-2 text-right">
                        <div class="flex items-center justify-end">
                          <input 
                            type="text" 
                            :value="formatCurrency(d.amount)"
                            @input="e => onDetailFieldChange(child, d, 'amount', cleanCurrency(e.target.value))"
                            class="w-24 text-right border border-slate-200 rounded-md py-0.5 px-1.5 focus:outline-none focus:ring-1 focus:ring-sky-400 text-[11px] text-slate-600 font-semibold"
                            placeholder="0"
                          />
                          <span class="ml-1 text-[9px] text-slate-400">đ</span>
                        </div>
                      </td>
                      <!-- Breakfast -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.breakfast"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- Free -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.is_free"
                            @change="e => onDetailFieldChange(child, d, 'is_free', e.target.checked)"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- Extra Charge -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.is_extra_charge"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- FIT/GIT -->
                      <td class="p-2 text-center flex justify-center items-center h-10">
                        <div 
                          @click="d.is_room = !d.is_room"
                          class="w-12 h-4.5 rounded-full cursor-pointer relative transition-colors duration-200 flex items-center justify-between px-2 text-[8px] font-black text-white select-none shadow-inner"
                          :class="d.is_room ? 'bg-sky-400' : 'bg-slate-350'"
                        >
                          <span class="z-10" :class="d.is_room ? 'text-white' : 'text-slate-300/40'">FIT</span>
                          <span class="z-10" :class="!d.is_room ? 'text-white' : 'text-slate-300/40'">GIT</span>
                          <div 
                            class="absolute w-3 h-3 bg-white rounded-full transition-transform duration-200 shadow-md top-[3px] left-[3px]" 
                            :style="{ transform: d.is_room ? 'translateX(26px)' : 'translateX(0)' }"
                          ></div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </template>
              </template>

              <!-- TRE EM (CHILD) SECTION -->
              <template v-if="childrenList.length > 0">
                <!-- Group Header -->
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold h-9">
                  <td colspan="6" class="p-3 pr-4">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center cursor-pointer" @click="isChildGroupExpanded = !isChildGroupExpanded">
                        <i 
                          class="fa-solid mr-2 text-sky-600"
                          :class="isChildGroupExpanded ? 'fa-square-minus' : 'fa-square-plus'"
                        ></i>
                        <span>Trẻ em</span>
                      </div>
                      <span class="text-[10px] text-slate-400 font-normal">({{ childrenList.length }} trẻ)</span>
                    </div>
                  </td>
                </tr>

                <!-- Child Rows -->
                <template v-if="isChildGroupExpanded" v-for="child in childrenList" :key="child.id">
                  <!-- Parent Row -->
                  <tr class="border-b border-slate-200 h-12 hover:bg-slate-50/50">
                    <td class="p-3 pl-6 font-semibold text-slate-700">
                      <div class="flex items-center cursor-pointer" @click="toggleExpand(child.id)">
                        <i 
                          class="fa-solid mr-2 text-blue-500"
                          :class="isExpanded(child.id) ? 'fa-square-minus' : 'fa-square-plus'"
                        ></i>
                        <span>{{ child.full_name }}</span>
                      </div>
                    </td>
                    <!-- Parent Amount (Bulk) -->
                    <td class="p-3 text-right">
                      <div class="flex items-center justify-end">
                        <input 
                          type="text" 
                          :value="formatCurrency(child.amount)"
                          @input="e => onParentFieldChange(child, 'amount', cleanCurrency(e.target.value))"
                          class="w-28 text-right border border-slate-300 rounded-md py-1 px-2 focus:outline-none focus:ring-1 focus:ring-sky-500 font-semibold text-slate-800 text-xs"
                          placeholder="0"
                        />
                        <span class="ml-1.5 text-[10px] text-slate-400 font-bold">đ</span>
                      </div>
                    </td>
                    <!-- Parent Breakfast Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.breakfast"
                          @change="e => onParentFieldChange(child, 'breakfast', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent Free Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.is_free"
                          @change="e => onParentFieldChange(child, 'is_free', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent Extra Charge Toggle -->
                    <td class="p-3 text-center">
                      <label class="relative inline-flex items-center cursor-pointer select-none">
                        <input 
                          type="checkbox" 
                          :checked="child.is_extra_charge"
                          @change="e => onParentFieldChange(child, 'is_extra_charge', e.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-500 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                      </label>
                    </td>
                    <!-- Parent FIT/GIT Toggle -->
                    <td class="p-3 text-center flex justify-center items-center h-12">
                      <div 
                        @click="onParentFieldChange(child, 'is_room', !child.is_room)"
                        class="w-14 h-5 rounded-full cursor-pointer relative transition-colors duration-200 flex items-center justify-between px-2 text-[9px] font-black text-white select-none shadow-inner"
                        :class="child.is_room ? 'bg-sky-500' : 'bg-slate-400'"
                      >
                        <span class="z-10" :class="child.is_room ? 'text-white' : 'text-slate-300/40'">FIT</span>
                        <span class="z-10" :class="!child.is_room ? 'text-white' : 'text-slate-300/40'">GIT</span>
                        <div 
                          class="absolute w-3.5 h-3.5 bg-white rounded-full transition-transform duration-200 shadow-md top-[3px] left-[3px]" 
                          :style="{ transform: child.is_room ? 'translateX(30px)' : 'translateX(0)' }"
                        ></div>
                      </div>
                    </td>
                  </tr>

                  <!-- Child Date Rows (Expanded) -->
                  <template v-if="isExpanded(child.id)" v-for="d in child.breakfast_details" :key="d.id">
                    <tr class="bg-slate-50/30 border-b border-slate-100 h-10 text-[11px]">
                      <td class="p-2 pl-12 font-mono text-slate-500">
                        <i class="fa-regular fa-calendar-days mr-1.5 text-slate-400"></i>
                        {{ formatDateVi(d.service_date) }}
                      </td>
                      <!-- Amount -->
                      <td class="p-2 text-right">
                        <div class="flex items-center justify-end">
                          <input 
                            type="text" 
                            :value="formatCurrency(d.amount)"
                            @input="e => onDetailFieldChange(child, d, 'amount', cleanCurrency(e.target.value))"
                            class="w-24 text-right border border-slate-200 rounded-md py-0.5 px-1.5 focus:outline-none focus:ring-1 focus:ring-sky-400 text-[11px] text-slate-600 font-semibold"
                            placeholder="0"
                          />
                          <span class="ml-1 text-[9px] text-slate-400">đ</span>
                        </div>
                      </td>
                      <!-- Breakfast -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.breakfast"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- Free -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.is_free"
                            @change="e => onDetailFieldChange(child, d, 'is_free', e.target.checked)"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- Extra Charge -->
                      <td class="p-2 text-center">
                        <label class="relative inline-flex items-center cursor-pointer scale-90 select-none">
                          <input 
                            type="checkbox" 
                            v-model="d.is_extra_charge"
                            class="sr-only peer"
                          />
                          <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-sky-400 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                        </label>
                      </td>
                      <!-- FIT/GIT -->
                      <td class="p-2 text-center flex justify-center items-center h-10">
                        <div 
                          @click="d.is_room = !d.is_room"
                          class="w-12 h-4.5 rounded-full cursor-pointer relative transition-colors duration-200 flex items-center justify-between px-2 text-[8px] font-black text-white select-none shadow-inner"
                          :class="d.is_room ? 'bg-sky-400' : 'bg-slate-350'"
                        >
                          <span class="z-10" :class="d.is_room ? 'text-white' : 'text-slate-300/40'">FIT</span>
                          <span class="z-10" :class="!d.is_room ? 'text-white' : 'text-slate-300/40'">GIT</span>
                          <div 
                            class="absolute w-3 h-3 bg-white rounded-full transition-transform duration-200 shadow-md top-[3px] left-[3px]" 
                            :style="{ transform: d.is_room ? 'translateX(26px)' : 'translateX(0)' }"
                          ></div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Note Text -->
        <div class="bg-slate-50 rounded-xl p-4 text-[11px] text-slate-600 space-y-1.5 border border-slate-200">
          <p class="font-extrabold text-slate-800 flex items-center">
            <i class="fa-solid fa-circle-info text-sky-600 mr-1.5 text-xs"></i>
            Khi trẻ em có ăn sáng - "Phụ phí" ăn sáng được tính như sau:
          </p>
          <div class="pl-5 space-y-0.5">
            <p><strong class="text-sky-600">ON</strong> - Thu thêm tiền ăn sáng theo giá ăn sáng đã nhập</p>
            <p><strong class="text-slate-500">OFF</strong> - Không thu thêm tiền ăn sáng của khách, tiền ăn sáng sẽ được phân bổ từ giá phòng để phục vụ báo cáo nội bộ</p>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="bg-slate-50 border-t border-slate-200 px-4 py-3 shrink-0 flex items-center justify-end space-x-2">
        <button
          @click="save"
          class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4.5 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 border-none shadow-sm"
          :disabled="isLoading || localChildren.length === 0"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
        <button
          @click="revert"
          class="bg-white border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold text-xs px-4.5 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 shadow-sm"
          :disabled="isLoading || localChildren.length === 0"
        >
          <i class="fa-solid fa-rotate-left"></i>
          <span>Quay lại</span>
        </button>
        <button
          @click="close"
          class="bg-slate-500 hover:bg-slate-600 text-white font-bold text-xs px-4.5 py-2 rounded-lg cursor-pointer transition flex items-center space-x-1.5 border-none shadow-sm"
        >
          <i class="fa-solid fa-xmark"></i>
          <span>Đóng</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { fetchBookingChildren, updateChildBreakfastDetail } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  room: Object,
  bookingId: [Number, String]
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()
const isLoading = ref(false)
const localChildren = ref([])
const initialData = ref([])
const expandedChildren = ref([])

// Collapsible Group Headers
const isBabyGroupExpanded = ref(true)
const isChildGroupExpanded = ref(true)

watch(() => props.show, async (newVal) => {
  if (newVal) {
    expandedChildren.value = []
    isBabyGroupExpanded.value = true
    isChildGroupExpanded.value = true
    await loadData()
  }
})

async function loadData() {
  if (!props.bookingId) return
  isLoading.value = true
  try {
    const res = await fetchBookingChildren(props.bookingId)
    if (res.data?.success) {
      const targetRoomId = props.room?.bookingRoomId
      const filtered = (res.data.data || []).filter(c => c.booking_room_id === targetRoomId)
      
      localChildren.value = filtered.map(c => {
        const details = (c.breakfast_details || []).map(d => ({
          id: d.id,
          booking_child_id: d.booking_child_id,
          service_date: d.service_date,
          breakfast: !!d.breakfast,
          is_free: !!d.is_free,
          is_extra_charge: !!d.is_extra_charge,
          is_room: !!d.is_room,
          amount: Number(d.amount) || 0
        }))
        
        // Use first date values or defaults for bulk fields
        const first = details[0] || {}
        return {
          id: c.id,
          full_name: c.full_name || (c.age_group === 'baby' ? 'Infant' : 'Child'),
          age_group: c.age_group || 'child',
          breakfast: first.breakfast !== undefined ? first.breakfast : false,
          is_free: first.is_free !== undefined ? first.is_free : false,
          is_extra_charge: first.is_extra_charge !== undefined ? first.is_extra_charge : false,
          is_room: first.is_room !== undefined ? first.is_room : true,
          amount: first.amount !== undefined ? first.amount : 90000,
          breakfast_details: details
        }
      })
      
      // Auto expand children that have details
      localChildren.value.forEach(child => {
        if (child.breakfast_details.length > 0) {
          expandedChildren.value.push(child.id)
        }
      })

      initialData.value = JSON.parse(JSON.stringify(localChildren.value))
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải chi tiết ăn sáng trẻ em!', 'error')
  } finally {
    isLoading.value = false
  }
}

const babies = computed(() => localChildren.value.filter(c => c.age_group === 'baby'))
const childrenList = computed(() => localChildren.value.filter(c => c.age_group === 'child'))

function toggleExpand(childId) {
  const index = expandedChildren.value.indexOf(childId)
  if (index > -1) {
    expandedChildren.value.splice(index, 1)
  } else {
    expandedChildren.value.push(childId)
  }
}

function isExpanded(childId) {
  return expandedChildren.value.includes(childId)
}

function onParentFieldChange(child, field, value) {
  child[field] = value
  child.breakfast_details.forEach(d => {
    d[field] = value
    if (field === 'is_free' && value) {
      d.amount = 0
    }
  })
  if (field === 'is_free' && value) {
    child.amount = 0
  }
}

function onDetailFieldChange(child, detail, field, value) {
  detail[field] = value
  if (field === 'is_free' && value) {
    detail.amount = 0
  }
}

function formatCurrency(val) {
  if (!val && val !== 0) return ''
  const num = Number(String(val).replace(/[^0-9.]/g, ''))
  if (isNaN(num)) return ''
  return num.toLocaleString('en-US')
}

function cleanCurrency(val) {
  if (!val) return 0
  return Number(String(val).replace(/,/g, '')) || 0
}

function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

function close() {
  emit('update:show', false)
}

function revert() {
  localChildren.value = JSON.parse(JSON.stringify(initialData.value))
  uiStore.showToast('Đã hoàn tác các thay đổi chưa lưu!', 'info')
}

async function save() {
  isLoading.value = true
  try {
    let successCount = 0
    let failCount = 0
    
    const allDetails = []
    localChildren.value.forEach(child => {
      child.breakfast_details.forEach(d => {
        allDetails.push({ childId: child.id, detail: d })
      })
    })

    if (allDetails.length === 0) {
      uiStore.showToast('Không có ngày ăn sáng nào để lưu!', 'warning')
      isLoading.value = false
      return
    }

    await Promise.all(allDetails.map(async (item) => {
      try {
        const payload = {
          breakfast: item.detail.breakfast,
          is_free: item.detail.is_free,
          is_extra_charge: item.detail.is_extra_charge,
          is_room: item.detail.is_room,
          amount: item.detail.amount
        }
        await updateChildBreakfastDetail(item.childId, item.detail.id, payload)
        successCount++
      } catch (err) {
        console.error(err)
        failCount++
      }
    }))

    if (successCount > 0) {
      uiStore.showToast(`Lưu chi tiết ăn sáng thành công cho ${successCount} ngày!${failCount > 0 ? ` (Thất bại ${failCount})` : ''}`, 'success')
      emit('saved')
      close()
    } else {
      uiStore.showToast('Không lưu được dòng ăn sáng nào!', 'error')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Có lỗi xảy ra khi lưu chi tiết ăn sáng!', 'error')
  } finally {
    isLoading.value = false
  }
}
</script>
