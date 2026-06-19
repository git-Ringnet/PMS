<script setup>
import { ref, computed } from "vue";
import { VueDatePicker } from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

//==================== STATE ====================//
const roomStatistics = ref(false);
const today = new Date();
const dateRange = ref([today, today]);
const manageRoomTableRows = ref([
    "Tổng Phòng",
    "Phòng Khóa Hư Hỏng",
    "Phòng Khóa Dịch Vụ",
    "Tổng Phòng Có Thể Bán",
    "Đặt Phòng Đảm Bảo",
    "Đặt Phòng Chưa Đảm Bảo",
    "Đăng ký series",
    "Allotment",
    "Phòng Ở",
    "Phòng Ở (Không Gồm Nội Bộ)",
    "Phòng Ở (Không Gồm Nội Bộ, MP)",
    "Phòng Miễn Phí",
    "Phòng Nội Bộ",
    "Công Suất Phòng",
    "Công Suất (Không Gồm Nội Bộ)",
    "Giá Phòng Trung Bình (Không Gồm Nội Bộ)",
    "Giá Phòng Trung Bình (Không Gồm Nội Bộ, MP)",
    "Khách Ở",
    "Phòng Đến",
    "Khách Đến",
    "Phòng Đi",
    "Khách Đi",
    "Doanh Phu Tiền Phòng (Gồm Ăn Sáng)",
    "Thêm Giường",
    "Phòng Khách Đi-Đến Liên Tục",
    "Phòng Nghỉ Sớm",
    "Phòng Trả Sớm",
    "Phòng Khách Không Đến",
    "Phòng Hủy",
    "Khách Đến Không Đặt Phòng Trước",
    "Phòng Còn Trống",
])
</script>

<template>
  <div
    class="flex flex-col h-full p-3 overflow-hidden bg-slate-100 text-slate-800 text-xs rounded-lg shadow"
  >
    <!-- HEADER -->
    <div class="flex gap-5 h-1/10 py-3 items-center">
      <div
        @click="roomStatistics = roomStatistics === false ? true : false"
        class="relative h-7 rounded-full cursor-pointer transition-all duration-200 select-none flex items-center justify-between w-[150px]"
        :class="
          roomStatistics === true
            ? 'bg-blue-400 pl-2.5 pr-7'
            : 'bg-slate-200 pl-7 pr-2.5'
        "
      >
        <!-- Text -->
        <span
          class="text-[11px] font-bold flex-1 text-center transition-all duration-200"
          :class="roomStatistics === true ? 'text-white' : 'text-slate-500'"
        >
          {{ roomStatistics === false ? "Thống kê phòng" : "??" }}
        </span>

        <!-- Thumb -->
        <div
          class="absolute top-[3px] w-[22px] h-[22px] bg-white rounded-full shadow transition-all duration-200"
          :class="roomStatistics === true ? 'right-[3px]' : 'left-[3px]'"
        ></div>
      </div>

      <VueDatePicker
        v-model="dateRange"
        range
        :time-config="{ enableTimePicker: false }"
        :format-locale="vi"
        :formats="{ input: 'dd.MM.yyyy' }"
        :text-input="{ rangeSeparator: ' ~ ' }"
        :clearable="false"
      />

      <label class="flex items-center gap-2">
        <input type="checkbox" class="w-5 h-5" />
        <span class="text-[11px]">DT không gồm thuế</span>
      </label>

      <button
        class="flex gap-2 justify-center items-center h-full w-25 px-3 py-1 text-[11px] font-bold text-white bg-blue-400 rounded hover:bg-blue-600 cursor-pointer transition-all duration-200"
      >
        <span
          class="w-5 h-5 rounded-full bg-white flex items-center justify-center"
        >
          <svg
            class="w-3 h-3 text-sky-400"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <circle cx="11" cy="11" r="6" />
            <line x1="3" y1="21" x2="7.35" y2="16.65" />
          </svg>
        </span>
        Xem
      </button>

      <button
        class="flex gap-2 justify-center items-center h-full w-25 px-3 py-1 text-[11px] font-bold text-white bg-blue-400 rounded hover:bg-blue-600 cursor-pointer transition-all duration-200"
      >
        <svg
          class="w-3.5 h-3.5 text-slate-400"
          fill="none"
          stroke="white"
          stroke-width="2"
          viewBox="0 0 24 24"
        >
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        Xuất file
      </button>
    </div>

    <!-- MAIN CONTENT -->
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse border border-gray-300">
        <thead>
          <tr class="bg-gray-100 border-b border-gray-300">
            <th class="p-3 border-r border-gray-300"></th>
            <th class="p-3 border-r border-gray-300 text-center">
              T2<br />15/06
            </th>
            <th class="p-3 text-center">Tổng cộng</th>
          </tr>
        </thead>
        <tbody class="text-sm">
          <tr class="border-b border-gray-300 font-bold">
            <td class="p-3 border-r border-gray-300">Tổng Phòng</td>
            <td class="p-3 border-r border-gray-300 text-center">131</td>
            <td class="p-3 text-center">131</td>
          </tr>
          <tr class="border-b border-gray-300">
            <td class="p-3 border-r border-gray-300 pl-6">
              Phòng Khóa Hư Hỏng
            </td>
            <td class="p-3 border-r border-gray-300 text-center">1</td>
            <td class="p-3 text-center">1</td>
          </tr>
          <tr class="border-b border-gray-300">
            <td class="p-3 border-r border-gray-300 pl-6">
              Phòng Khóa Dịch Vụ
            </td>
            <td class="p-3 border-r border-gray-300 text-center">1</td>
            <td class="p-3 text-center">1</td>
          </tr>
          <tr class="border-b border-gray-300 font-bold">
            <td class="p-3 border-r border-gray-300">Tổng Phòng Có Thể Bán</td>
            <td class="p-3 border-r border-gray-300 text-center">130</td>
            <td class="p-3 text-center">130</td>
          </tr>
          <tr class="border-b border-gray-300 font-bold text-red-600">
            <td class="p-3 border-r border-gray-300">Phòng Ở</td>
            <td class="p-3 border-r border-gray-300 text-center">88</td>
            <td class="p-3 text-center">88</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.custom-datepicker {
  --dp-primary-color: #38bdf8;
  --dp-border-color: #e2e8f0;
  --dp-border-color-hover: #38bdf8;
  --dp-cell-border-radius: 8px;
  --dp-font-family: inherit;
  --dp-font-size: 12px;
  --dp-input-padding: 6px 30px 6px 12px;
}
</style>
