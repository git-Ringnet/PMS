<script setup>
import { ref, computed } from "vue";
import { VueDatePicker } from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

//==================== STATE ====================//
const roomStatistics = ref(false);
const today = new Date();
const dateRange = ref([today, today]);
const manageRoomTableRows = ref([
  { title: "Tổng Phòng", style: "bold" },
  { title: "Phòng Khóa Hư Hỏng", style: "normal" },
  { title: "Phòng Khóa Dịch Vụ", style: "normal" },
  { title: "Tổng Phòng Có Thể Bán", style: "bold" },
  { title: "Đặt Phòng Đảm Bảo", style: "normal" },
  { title: "Đặt Phòng Chưa Đảm Bảo", style: "normal" },
  { title: "Đăng ký series", style: "normal" },
  { title: "Allotment", style: "normal" },
  { title: "Phòng Ở", style: "bold-red" },
  { title: "Phòng Ở (Không Gồm Nội Bộ)", style: "normal" },
  { title: "Phòng Ở (Không Gồm Nội Bộ, MP)", style: "normal" },
  { title: "Phòng Miễn Phí", style: "bold" },
  { title: "Phòng Nội Bộ", style: "bold" },
  { title: "Công Suất Phòng", style: "normal" },
  { title: "Công Suất (Không Gồm Nội Bộ)", style: "normal" },
  { title: "Giá Phòng Trung Bình (Không Gồm Nội Bộ)", style: "normal" },
  { title: "Giá Phòng Trung Bình (Không Gồm Nội Bộ, MP)", style: "normal" },
  { title: "Khách Ở", style: "normal" },
  { title: "Phòng Đến", style: "bold-red" },
  { title: "Khách Đến", style: "normal" },
  { title: "Phòng Đi", style: "bold-red" },
  { title: "Khách Đi", style: "normal" },
  { title: "Doanh Phu Tiền Phòng (Gồm Ăn Sáng)", style: "bold" },
  { title: "Thêm Giường", style: "normal" },
  { title: "Phòng Khách Đi-Đến Liên Tục", style: "normal" },
  { title: "Phòng Nghỉ Sớm", style: "normal" },
  { title: "Phòng Trả Sớm", style: "normal" },
  { title: "Phòng Khách Không Đến", style: "normal" },
  { title: "Phòng Hủy", style: "normal" },
  { title: "Khách Đến Không Đặt Phòng Trước", style: "normal" },
  { title: "Phòng Còn Trống", style: "bold-red" },
]);

//==================== APPLY STYLE FOR TABLE ROWS ====================//
const tbody = document.querySelector("#manageRoomTable tbody");

// Hàm xác định class dựa trên style
const getRowClass = (style) => {
  if (style === "bold") return "font-bold";
  if (style === "bold-red") return "font-bold text-red-600";
  return "";
};
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
        <span
          class="text-[11px] font-bold flex-1 text-center transition-all duration-200"
          :class="roomStatistics === true ? 'text-white' : 'text-slate-500'"
        >
          Thống kê phòng
        </span>

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
            class="w-3 h-3 text-sky-500"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M3 21l5.197-5.197m0 0A7.5 7.5 0 1018.804 5.196a7.5 7.5 0 00-10.607 10.607z"
            />
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
    <div class="h-9/10">
      <div class="w-fit h-[500px] border border-gray-300 overflow-y-auto">
        <table class="border-collapse border-spacing-0">
          <thead class="sticky top-0 z-10">
            <tr class="bg-gray-200 font-bold">
              <th class="p-3 w-64 border-b border-r border-gray-300"></th>
              <th
                class="p-3 w-32 border-b border-r border-gray-300 text-center"
              >
                T2<br />15/06
              </th>
              <th class="p-3 w-32 border-b border-gray-300 text-center">
                Tổng cộng
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(row, index) in manageRoomTableRows"
              :key="index"
              :class="[
                'group bg-white border-b border-gray-300',
                getRowClass(row.style),
              ]"
            >
              <td class="p-3 w-64 border-r border-gray-300">
                {{ row.title }}
              </td>
              <td
                class="p-3 w-32 border-r border-gray-300 text-center group-hover:bg-blue-50 transition-colors"
              >
                {{ row.value }}
              </td>
              <td
                class="p-3 w-32 text-center group-hover:bg-blue-50 transition-colors"
              >
                {{ row.value }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
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
