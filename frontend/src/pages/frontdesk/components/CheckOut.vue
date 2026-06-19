<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from "vue";
import { VueDatePicker } from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

//================= STATE =================//
// Sidebar actions
const sidebarActions = [
  { icon: "plus-circle", label: "Thêm dịch vụ", danger: false },
  { icon: "split", label: "Tách dịch vụ", danger: false },
  { icon: "transfer", label: "Chuyển dịch vụ", danger: false },
  { icon: "bundle", label: "Tập hợp DV", danger: false },
  { divider: true },
  { icon: "print", label: "In hoá đơn", hasArrow: true, danger: false },
  { icon: "vat", label: "In VAT", danger: false },
  { icon: "cancel-vat", label: "Huỷ VAT", danger: true },
  { divider: true },
  { icon: "delete-service", label: "Xoá dịch vụ", danger: true },
  { icon: "delete-payment", label: "Xoá thanh toán", danger: true },
  { divider: true },
  { icon: "prepay", label: "Thanh toán trư...", danger: false },
  { icon: "pay", label: "Thanh toán", danger: false },
  { icon: "filter", label: "Lọc", danger: false },
  { divider: true },
  { icon: "checkout", label: "Trả phòng", danger: true },
];

const activeFilter = ref("Đăng ký hiện tại");
const filterOptions = ["Đăng ký hiện tại", "Tất cả đăng ký"];
const showAllGuests = ref(false);
const selectedRegistration = ref(null);
const activeFolio = ref("A");
const folios = ["A", "1", "2", "3"];
const noteText = ref("");
const searchQuery = ref("");
const today = new Date();
const dateRange = ref([today, today]);

const showFilterPanel = ref(false);
const activeTab = ref("current");
const dateRangeType = ref("Tùy chỉnh");
const checkInOutDate = ref(false);
const filterFromDate = ref("");
const filterToDate = ref("");
const filterPanelRef = ref(null);
const registerTabs = [
  { key: "current", label: "Đăng ký hiện tại" },
  { key: "old", label: "Đăng ký cũ" },
  { key: "virtual", label: "Phòng ảo" },
];
const dateRangeTypes = [
  "Hôm nay",
  "Tuần này",
  "Tháng này",
  "Quý này",
  "Năm nay",
  "Ngày mai",
  "Tuần tiếp theo",
  "Tháng tiếp theo",
  "Quý tiếp theo",
  "Năm tiếp theo",
  "Hôm qua",
  "Tuần trước",
  "Tháng trước",
  "Quý trước",
  "Năm trước",
  "Tùy chỉnh",
];

//================= REGISTER PICKER =================//
const handleClickOutsideFilter = (event) => {
  if (filterPanelRef.value && !filterPanelRef.value.contains(event.target)) {
    showFilterPanel.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutsideFilter);
});
onUnmounted(() => {
  document.removeEventListener("click", handleClickOutsideFilter);
});

const closeFilterPanel = () => {
  showFilterPanel.value = false;
};

const applyFilterPanel = () => {
  showFilterPanel.value = false;
};

//================= FORMAT =================//
const formatDateRange = (dates) => {
  if (!dates || !dates[0] || !dates[1]) return "";
  const pad = (n) => String(n).padStart(2, "0");
  const fmt = (d) =>
    `${pad(d.getDate())} / ${pad(d.getMonth() + 1)} / ${d.getFullYear()}`;
  return `${fmt(dates[0])} ~ ${fmt(dates[1])}`;
};

//================= WATCH =================//
watch(checkInOutDate, (newVal) => {
  if (newVal) {
    const today = new Date().toISOString().split("T")[0];
    filterFromDate.value = today;
    filterToDate.value = today;

    dateRangeType.value = dateRangeTypes[0];
  } else {
    filterFromDate.value = "";
    filterToDate.value = "";
    dateRangeType.value = "Tùy chỉnh";
  }
});
</script>

<template>
  <div
    class="flex h-full overflow-hidden bg-slate-100 text-slate-800 text-xs rounded-lg shadow"
  >
    <!-- LEFT SIDEBAR -->
    <aside
      class="w-32 shrink-0 bg-white border-r border-slate-200 flex flex-col py-2 mr-1 overflow-y-auto"
    >
      <template v-for="(action, idx) in sidebarActions" :key="idx">
        <div v-if="action.divider" class="h-px bg-slate-200 my-1 mx-3" />
        <button
          v-else
          class="flex items-center gap-2 px-3 py-2 w-full text-left border-none bg-transparent cursor-pointer transition-colors hover:bg-slate-50 rounded-none"
          :class="
            action.danger ? 'text-red-500 hover:bg-red-50' : 'text-slate-700'
          "
        >
          <!-- Icons by type -->
          <span class="w-4 h-4 flex items-center justify-center shrink-0">
            <!-- plus-circle -->
            <svg
              v-if="action.icon === 'plus-circle'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="16" />
              <line x1="8" y1="12" x2="16" y2="12" />
            </svg>
            <!-- split -->
            <svg
              v-else-if="action.icon === 'split'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M16 3h5v5M8 3H3v5M3 21l18-18M3 16v5h5M16 21h5v-5" />
            </svg>
            <!-- transfer -->
            <svg
              v-else-if="action.icon === 'transfer'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" />
            </svg>
            <!-- bundle -->
            <svg
              v-else-if="action.icon === 'bundle'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <rect x="2" y="7" width="20" height="14" rx="2" />
              <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
            </svg>
            <!-- print -->
            <svg
              v-else-if="action.icon === 'print'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <polyline points="6 9 6 2 18 2 18 9" />
              <path
                d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"
              />
              <rect x="6" y="14" width="12" height="8" />
            </svg>
            <!-- vat -->
            <svg
              v-else-if="action.icon === 'vat'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="9" y1="15" x2="15" y2="15" />
            </svg>
            <!-- cancel-vat -->
            <svg
              v-else-if="action.icon === 'cancel-vat'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
            </svg>
            <!-- delete-service / delete-payment -->
            <svg
              v-else-if="
                action.icon === 'delete-service' ||
                action.icon === 'delete-payment'
              "
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <polyline points="3 6 5 6 21 6" />
              <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
              <path d="M10 11v6M14 11v6" />
              <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
            </svg>
            <!-- prepay -->
            <svg
              v-else-if="action.icon === 'prepay'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M12 2a10 10 0 100 20A10 10 0 0012 2z" />
              <path d="M12 6v6l4 2" />
            </svg>
            <!-- pay -->
            <svg
              v-else-if="action.icon === 'pay'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <rect x="2" y="5" width="20" height="14" rx="2" />
              <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
            <!-- filter -->
            <svg
              v-else-if="action.icon === 'filter'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
            </svg>
            <!-- checkout -->
            <svg
              v-else-if="action.icon === 'checkout'"
              class="w-4 h-4"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </span>
          <span class="text-[11px] font-semibold leading-tight">{{
            action.label
          }}</span>
          <svg
            v-if="action.hasArrow"
            class="w-3 h-3 ml-auto"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </button>
      </template>
    </aside>

    <div class="flex flex-col flex-1 overflow-hidden">
      <!-- Top bar -->
      <div
        class="flex items-center gap-2 px-3 py-2 bg-white border-b border-slate-200 shrink-0"
      >
        <div
          class="flex flex-1 items-center gap-2 flex-1 border border-slate-200 rounded-lg px-3 py-1.5 bg-slate-50"
        >
          <svg
            class="w-3.5 h-3.5 text-slate-400"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search"
            class="bg-transparent border-none outline-none text-xs flex-1 text-slate-700"
          />
        </div>

        <div class="relative w-1/5" ref="filterPanelRef">
          <button
            @click="showFilterPanel = !showFilterPanel"
            class="flex w-2/3 justify-between items-center gap-1.5 border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-semibold bg-sky-50 text-sky-600 cursor-pointer"
          >
            {{ registerTabs.find((t) => t.key === activeTab)?.label }}
            <svg
              class="w-3 h-3"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M8 9l4-4 4 4M8 15l4 4 4-4" />
            </svg>
          </button>

          <div
            v-if="showFilterPanel"
            class="absolute w-[400px] z-20 mt-2 w-80 bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden"
          >
            <!-- Tabs -->
            <div class="flex gap-1 p-1.5 bg-slate-50">
              <div
                v-for="tab in registerTabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="flex-1 text-center px-2 py-2 rounded-lg text-xs font-semibold cursor-pointer"
                :class="
                  activeTab === tab.key
                    ? 'bg-sky-100 text-sky-600'
                    : 'text-slate-500'
                "
              >
                {{ tab.label }}
              </div>
            </div>

            <div class="p-4">
              <!-- Date range type -->
              <label class="block text-xs font-semibold text-slate-700 mb-2"
                >Phạm vi ngày</label
              >
              <div class="relative mb-4">
                <select
                  v-model="dateRangeType"
                  :disabled="!checkInOutDate || activeTab === 'virtual'"
                  :class="{
                    'cursor-not-allowed bg-slate-200 text-slate-300':
                      !checkInOutDate || activeTab === 'virtual',
                  }"
                  class="w-full appearance-none pr-8 border border-slate-200 rounded-lg px-3 py-2 text-xs"
                >
                  <option
                    v-for="type in dateRangeTypes"
                    :key="type"
                    :value="type"
                  >
                    {{ type }}
                  </option>
                </select>
                <svg
                  class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path d="M19 9l-7 7-7-7" />
                </svg>
              </div>

              <!-- Date range inputs -->
              <div class="flex items-center gap-2 mb-4">
                <label
                  v-if="activeTab !== 'virtual'"
                  class="flex flex-1 items-center gap-1.5 text-xs text-slate-700 w-24 shrink-0"
                >
                  <input
                    type="checkbox"
                    v-model="checkInOutDate"
                    class="w-5 h-5 accent-sky-400"
                  />
                  Ngày đi ĐK
                </label>
                <div
                  :class="{
                    'cursor-not-allowed bg-slate-200 text-slate-300':
                      !checkInOutDate || activeTab === 'virtual',
                  }"
                  class="flex-1 flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-400"
                >
                  <input
                    type="date"
                    v-model="filterFromDate"
                    :disabled="!checkInOutDate || activeTab === 'virtual'"
                    :class="{
                      'cursor-not-allowed bg-slate-200 text-slate-300':
                        !checkInOutDate || activeTab === 'virtual',
                    }"
                    class="border-none outline-none text-xs w-full"
                  />
                </div>
                <div
                  :class="{
                    'cursor-not-allowed bg-slate-200 text-slate-300':
                      !checkInOutDate || activeTab === 'virtual',
                  }"
                  class="flex-1 flex items-center justify-between border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-400"
                >
                  <input
                    type="date"
                    v-model="filterToDate"
                    :disabled="!checkInOutDate || activeTab === 'virtual'"
                    :class="{
                      'cursor-not-allowed bg-slate-200 text-slate-300':
                        !checkInOutDate || activeTab === 'virtual',
                    }"
                    class="border-none outline-none text-xs w-full bg-transparent"
                  />
                </div>
              </div>

              <div class="border-t border-slate-200 -mx-4 mb-3"></div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 px-4 pb-4">
              <button
                @click="closeFilterPanel"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-500 cursor-pointer"
              >
                <svg
                  class="w-3.5 h-3.5"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <circle cx="12" cy="12" r="10" />
                  <line x1="15" y1="9" x2="9" y2="15" />
                  <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
                Đóng
              </button>
              <button
                @click="applyFilterPanel"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-sky-100 text-sky-600 cursor-pointer"
              >
                <svg
                  class="w-3.5 h-3.5"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <circle cx="12" cy="12" r="10" />
                  <path d="M9 12l2 2 4-4" />
                </svg>
                Áp dụng
              </button>
            </div>
          </div>
        </div>

        <label
          class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer ml-auto"
        >
          <input
            type="checkbox"
            v-model="showAllGuests"
            class="rounded border-slate-300 h-5 w-5 accent-sky-400"
          />
          Xem tất cả khách trong phòng
        </label>
      </div>

      <!-- MAIN CONTENT -->
      <div class="flex-1 overflow-hidden">
        <div
          class="grid grid-cols-5 gap-2 h-full"
          style="grid-template-rows: 3fr 2fr"
        >
          <!-- Registration -->
          <div
            class="col-span-3 row-span-1 bg-white border border-slate-200 rounded-lg flex flex-col overflow-hidden"
          >
            <table class="w-full text-xs text-left border-collapse shrink-0">
              <thead>
                <tr
                  class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase"
                >
                  <th class="px-3 py-2 w-24">Mã ĐK</th>
                  <th class="px-3 py-2 w-24">Phòng</th>
                  <th class="px-3 py-2">Tên nhóm/khách</th>
                  <th class="px-3 py-2 w-28 text-right">Tổng dịch vụ</th>
                </tr>
              </thead>
            </table>
            <div class="flex-1 flex items-center justify-center">
              <div class="flex flex-col items-center gap-2 text-slate-300">
                <svg
                  class="w-10 h-10"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"
                  />
                </svg>
                <span class="text-xs">No data</span>
              </div>
            </div>
          </div>

          <!-- Form Panel -->
          <div
            class="col-span-2 row-span-1 bg-white border border-slate-200 rounded-lg flex flex-col overflow-hidden"
          >
            <!-- Top info bar -->
            <div class="flex items-center gap-2 px-3 py-2">
              <span class="text-slate-500 font-semibold text-xs"
                >Đăng ký -</span
              >

              <label
                class="flex items-center gap-1.5 text-slate-400 text-xs cursor-pointer"
              >
                <input
                  type="checkbox"
                  class="rounded border-slate-300 h-5 w-5 accent-sky-400"
                />
                No post
              </label>

              <div class="ml-auto flex items-center gap-2">
                <button
                  class="px-3 py-1.5 bg-sky-400 hover:bg-sky-500 text-white rounded-lg text-xs font-bold border-none cursor-pointer flex items-center gap-1.5"
                >
                  HĐ chữ ký điện tử
                </button>
                <button
                  class="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 cursor-pointer bg-white"
                >
                  <svg
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                  >
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Form fields -->
            <div class="flex flex-col gap-2 px-3 shrink-0">
              <div class="flex gap-2">
                <input
                  type="text"
                  placeholder=""
                  class="flex-1 w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 bg-slate-50"
                />

                <VueDatePicker
                  v-model="dateRange"
                  range
                  :time-config="{ enableTimePicker: false }"
                  :format-locale="vi"
                  :formats="{ input: 'dd.MM.yyyy' }"
                  :text-input="{ rangeSeparator: ' ~ ' }"
                  :clearable="false"
                />
              </div>

              <div class="flex gap-2">
                <div class="relative flex-1">
                  <select
                    class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold bg-white focus:outline-sky-400 appearance-none text-slate-400"
                  >
                    <option>Tên khách</option>
                  </select>
                  <div
                    class="pointer-events-none absolute right-2 top-2 text-slate-400"
                  >
                    <svg
                      class="w-3.5 h-3.5"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      viewBox="0 0 24 24"
                    >
                      <path d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
                <input
                  type="text"
                  placeholder="Phòng"
                  class="w-1/2 px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 text-slate-400"
                />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1"
                  >Ghi chú</label
                >
                <textarea
                  v-model="noteText"
                  rows="2"
                  class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-sky-400 resize-none bg-white"
                ></textarea>
              </div>
            </div>

            <!-- Folio -->
            <div class="px-3 py-3">
              <div class="text-xs font-semibold text-slate-500 mb-2">Folio</div>
              <div class="relative mb-2">
                <div
                  class="absolute -top-2 left-4 px-2 bg-white text-slate-500 font-bold text-sm z-10 rounded-sm"
                >
                  A
                </div>
                <div
                  class="h-9 bg-sky-200 border border-sky-300 rounded-lg flex items-center justify-center font-bold text-slate-700"
                >
                  0
                </div>
              </div>
              <div class="grid grid-cols-3 gap-3 pb-2">
                <div
                  v-for="i in 3"
                  :key="i"
                  class="relative h-10 bg-slate-100 border border-slate-300 rounded-lg flex items-center justify-center"
                >
                  <span
                    class="absolute -top-2 left-3 px-2 bg-white text-slate-400 text-sm rounded-sm z-10"
                    >{{ i }}</span
                  >
                  <span class="font-bold text-slate-600">0</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Service Table -->
          <div
            class="col-span-3 row-span-1 bg-white border border-slate-200 rounded-lg flex flex-col overflow-hidden"
          >
            <table class="w-full text-xs text-left border-collapse shrink-0">
              <thead>
                <tr
                  class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase"
                >
                  <th class="px-3 py-2 w-6">
                    <input
                      type="checkbox"
                      class="rounded border-slate-300 h-5 w-5"
                    />
                  </th>
                  <th class="px-3 py-2 w-28">Ngày/giờ</th>
                  <th class="px-3 py-2 w-24">Dịch vụ</th>
                  <th class="px-3 py-2">Mô tả</th>
                  <th class="px-3 py-2 w-20">Bộ phận</th>
                  <th class="px-3 py-2 w-24 text-right">Số Tiền</th>
                  <th class="px-3 py-2 w-10 text-center">SL</th>
                  <th class="px-3 py-2 w-14">Đơn vị</th>
                  <th class="px-3 py-2 w-14">Mã TT</th>
                </tr>
              </thead>
            </table>
            <div
              class="flex-1 flex items-center justify-center overflow-y-auto"
            >
              <div class="flex flex-col items-center gap-2 text-slate-300">
                <svg
                  class="w-10 h-10"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"
                  />
                </svg>
                <span class="text-xs">No data</span>
              </div>
            </div>
            <div
              class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-t border-slate-200 shrink-0"
            >
              <svg
                class="w-4 h-4 text-slate-400"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
              </svg>
              <span class="font-bold text-slate-600 text-xs">Tổng cộng</span>
              <span class="ml-auto font-black text-slate-800 text-xs">0</span>
            </div>
          </div>

          <!-- Payment Table -->
          <div
            class="col-span-2 row-span-1 bg-white border border-slate-200 rounded-lg flex flex-col overflow-hidden"
          >
            <table class="w-full text-xs text-left border-collapse shrink-0">
              <thead>
                <tr
                  class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase"
                >
                  <th class="px-3 py-2 w-6">
                    <input type="checkbox" class="rounded border-slate-300" />
                  </th>
                  <th class="px-3 py-2 w-24">Ngày/giờ</th>
                  <th class="px-3 py-2">Bộ phận</th>
                  <th class="px-3 py-2">Mô tả</th>
                  <th class="px-3 py-2 w-14">HTTT</th>
                  <th class="px-3 py-2 w-20 text-right">Số tiền</th>
                  <th class="px-3 py-2 w-6">Đ</th>
                </tr>
              </thead>
            </table>
            <div
              class="flex-1 flex items-center justify-center overflow-y-auto"
            >
              <div class="flex flex-col items-center gap-2 text-slate-300">
                <svg
                  class="w-10 h-10"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"
                  />
                </svg>
                <span class="text-xs">No data</span>
              </div>
            </div>
            <div
              class="flex items-center gap-2 px-3 py-2 bg-slate-50 border-t border-slate-200 shrink-0"
            >
              <svg
                class="w-4 h-4 text-slate-400"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
              </svg>
              <span class="font-bold text-slate-600 text-xs">Tổng cộng</span>
              <span class="ml-auto font-black text-slate-800 text-xs">0</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
