<script setup>
import { ref, computed, onMounted } from "vue";

//======================= Props ========================//
const childTabs = ref([
  { title: "Phòng đang ở", code: "staying-room" },
  { title: "Phòng đến", code: "arrival-room" },
  { title: "Phòng đi", code: "empty-room" },
  { title: "Ở theo giờ", code: "hourly-room" },
]);
const filterOptions = ref([
  { title: "Đăng ký", code: "dang-ky" },
  { title: "VAT", code: "vat" },
  { title: "Phòng", code: "phong" },
  { title: "Loại phòng", code: "loai-phong" },
  { title: "Khách", code: "khach" },
  { title: "Phòng đến", code: "phong-den" },
  { title: "Đêm", code: "dem" },
  { title: "Phòng đi", code: "phong-di" },
  { title: "Ăn sáng", code: "an-sang" },
  { title: "Người lớn", code: "nguoi-lon" },
  { title: "Trẻ em", code: "tre-em" },
  { title: "Mã giá phòng", code: "ma-gia-phong" },
  { title: "Giá", code: "gia" },
  { title: "Thêm giường", code: "them-giuong" },
  { title: "Giá thêm giường", code: "gia-them-giuong" },
]);
const tableColumns = ref([
  { title: "Đăng ký", code: "registration" },
  { title: "VAT", code: "vat" },
  { title: "Phòng", code: "room" },
  { title: "Loại Phòng", code: "roomType" },
  { title: "Khách", code: "guest" },
  { title: "Phòng đến", code: "checkInDate" },
  { title: "Đêm", code: "nights" },
  { title: "Phòng đi", code: "checkOutDate" },
  { title: "Ăn Sáng", code: "breakfast" },
  { title: "Người lớn", code: "adults" },
  { title: "Trẻ em", code: "children" },
  { title: "Mã giá phòng", code: "roomRateCode" },
  { title: "Giá", code: "price" },
  { title: "Thêm giường", code: "extraBed" },
  { title: "Giá thêm giường", code: "extraBedPrice" },
]);

//======================= State ========================//
const selectedTab = ref(childTabs.value[0].code);
const tabRefs = ref([]);
const activeModal = ref(null);
const selectedFilters = ref([
  "dang-ky",
  "vat",
  "phong",
  "loai-phong",
  "khach",
  "phong-den",
  "dem",
  "phong-di",
  "an-sang",
  "nguoi-lon",
  "tre-em",
  "ma-gia-phong",
  "gia",
  "them-giuong",
  "gia-them-giuong",
]);
const selectedCompany = ref([]);
const selectedBooking = ref([]);
const isDirtyRoom = ref(true);
const isWaitToCheckRoom = ref(true);

//======================= Mock Data ========================//
const companies = [
  "PEGAS",
  "VIETRAVEL",
  "SAIGONTOURIST",
  "KHÁCH LẺ",
  "BUFFALO TOURS",
];
const roomTypes = [
  "Deluxe Twin with Balcony",
  "Deluxe Double City view",
  "Deluxe Twin City View",
  "Superior Room",
  "Junior Suite",
];
const guestNames = [
  "Ms.VINOKUROVA MARINA",
  "Mr.Guest 1",
  "Mr.Nguyen Van An",
  "Ms.Tran Thi Binh",
  "Mr.Le Minh Tuan",
  "Ms.Pham Thi Huong",
];
const rateCodes = ["RACK01", "CORP01", "AGENT01", "OTA01", "PROMO01"];

const generateMockData = () => {
  return companies.map((company) => {
    const rowCount = Math.floor(Math.random() * 6) + 3;
    const rows = Array.from({ length: rowCount }, (_, i) => {
      const checkIn = new Date(2026, 5, (i % 20) + 1);
      const nights = Math.floor(Math.random() * 12) + 1;
      const checkOut = new Date(checkIn);
      checkOut.setDate(checkOut.getDate() + nights);

      const formatDate = (d) =>
        `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

      const price = (Math.floor(Math.random() * 15) + 5) * 100000;
      const hasExtraBed = Math.random() > 0.7;

      return {
        registration: `GAL${1000 + Math.floor(Math.random() * 9000)}`,
        vat: true,
        room: String(100 + Math.floor(Math.random() * 20) * 10 + (i % 9) + 1),
        roomType: roomTypes[Math.floor(Math.random() * roomTypes.length)],
        guest: guestNames[Math.floor(Math.random() * guestNames.length)],
        checkInDate: formatDate(checkIn),
        nights: nights,
        checkOutDate: formatDate(checkOut),
        breakfast: true,
        adults: Math.floor(Math.random() * 2) + 1,
        children: Math.random() > 0.6 ? Math.floor(Math.random() * 2) + 1 : 0,
        roomRateCode: rateCodes[Math.floor(Math.random() * rateCodes.length)],
        price: price,
        extraBed: hasExtraBed ? 1 : 0,
        extraBedPrice: hasExtraBed ? 150000 : 0,
      };
    });

    return { company, rows, _expanded: true };
  });
};

const tableData = ref(generateMockData());

//======================= Computed ========================//
const sliderStyle = computed(() => {
  const tabs = childTabs.value;

  if (!Array.isArray(tabs) || tabs.length === 0) {
    return { width: "0px", left: "0px" };
  }

  const index = tabs.findIndex((t) => t.code === selectedTab.value);
  const targetTab = tabRefs.value[index];

  if (!targetTab) return { width: "0px", left: "0px" };

  return {
    width: `${targetTab.offsetWidth}px`,
    left: `${targetTab.offsetLeft}px`,
  };
});
const isAllCompanySelected = computed(
  () =>
    selectedCompany.value.length === companies.length && companies.length > 0,
);
const isIndeterminateCompany = computed(
  () =>
    selectedCompany.value.length > 0 &&
    selectedCompany.value.length < companies.length,
);

//======================= Mounted ========================//
const vClickOutside = {
  mounted: (el, binding) => {
    el.clickOutsideEvent = (event) => {
      if (!(el == event.target || el.contains(event.target))) {
        binding.value();
      }
    };
    document.body.addEventListener("click", el.clickOutsideEvent);
  },
  unmounted: (el) => {
    document.body.removeEventListener("click", el.clickOutsideEvent);
  },
};

//======================= Modal Logic ========================//
const toggleModal = (modalName) => {
  activeModal.value = activeModal.value === modalName ? null : modalName;
};
const closeModal = () => {
  activeModal.value = null;
};

//======================= Function ========================//
const toggleAllCompany = () => {
  if (selectedCompany.value.length === companies.length) {
    selectedCompany.value = [];
    selectedBooking.value = [];
  } else {
    selectedCompany.value = [...companies];
    selectedBooking.value = tableData.value.flatMap((group) =>
      group.rows.map((row) => row.registration),
    );
  }
};
const handleSelectCompany = (group) => {
  if (selectedCompany.value.includes(group.company)) {
    const bookings = group.rows.map((row) => row.registration);
    selectedBooking.value = [
      ...new Set([...selectedBooking.value, ...bookings]),
    ];
  } else {
    const bookings = group.rows.map((row) => row.registration);
    selectedBooking.value = selectedBooking.value.filter(
      (b) => !bookings.includes(b),
    );
  }
};
const isCompanyIndeterminate = (group) => {
  const bookings = group.rows.map((r) => r.registration);
  const selected = bookings.filter((b) => selectedBooking.value.includes(b));
  return selected.length > 0 && selected.length < bookings.length;
};
</script>

<template>
  <!-- HEADER -->
  <div
    class="h-1/10 flex justify-between items-center mb-4 border-b border-gray-200 shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1)]"
  >
    <!-- LEFT SIDE -->
    <div class="flex gap-15 items-center">
      <div class="relative">
        <button
          v-for="(tab, index) in childTabs"
          :key="tab.code"
          @click="selectedTab = tab.code"
          :ref="
            (el) => {
              if (el) tabRefs[index] = el;
            }
          "
          class="px-4 py-2 text-sm font-medium transition-colors duration-300 z-10 cursor-pointer"
          :class="
            selectedTab === tab.code
              ? 'text-blue-300'
              : 'text-gray-600 hover:text-gray-800'
          "
        >
          {{ tab.title }}
        </button>

        <div
          class="absolute bottom-0 h-1 bg-blue-300 transition-all duration-300 ease-in-out"
          :style="sliderStyle"
        ></div>
      </div>

      <button
        class="flex gap-2 items-center group p-3 rounded-md bg-[#90afc0] border-none cursor-pointer hover:shadow-lg"
      >
        <svg
          class="w-5 h-5 text-slate-200/60 icon"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          viewBox="0 0 24 24"
        >
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
          <line
            x1="9"
            y1="15"
            x2="9"
            y2="15"
            stroke-linecap="round"
            stroke-width="3"
          />
          <line
            x1="12"
            y1="15"
            x2="12"
            y2="15"
            stroke-linecap="round"
            stroke-width="3"
          />
          <line
            x1="15"
            y1="15"
            x2="15"
            y2="15"
            stroke-linecap="round"
            stroke-width="3"
          />
        </svg>
        <span class="text-xs font-bold text-slate-200/60">Sang ngày</span>
      </button>
    </div>

    <!-- RIGHT SIDE -->
    <div class="flex gap-5 items-center">
      <div class="relative">
        <div @click.stop="toggleModal('filter')" class="cursor-pointer">
          <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="4" y1="6" x2="20" y2="6" />
            <circle cx="10" cy="6" r="2" fill="currentColor" />

            <line x1="4" y1="12" x2="20" y2="12" />
            <circle cx="16" cy="12" r="2" fill="currentColor" />

            <line x1="4" y1="18" x2="20" y2="18" />
            <circle cx="8" cy="18" r="2" fill="currentColor" />
          </svg>
        </div>

        <!-- Dropdown -->
        <div
          v-if="activeModal === 'filter'"
          v-click-outside="closeModal"
          class="absolute mt-1 w-64 right-0 bg-white border border-gray-300 rounded-lg shadow-xl z-50 p-3"
        >
          <div class="max-h-60 overflow-y-auto space-y-2">
            <label
              v-for="col in filterOptions"
              :key="col.code"
              class="flex items-center gap-3 py-1 hover:bg-gray-50 cursor-pointer"
            >
              <input
                type="checkbox"
                v-model="selectedFilters"
                :value="col.code"
                class="w-4 h-4 rounded border-gray-300 text-sky-500 focus:ring-sky-500 w-5 h-5"
              />
              <span class="text-[13px] text-gray-700">{{ col.title }}</span>
            </label>
          </div>
        </div>
      </div>

      <template v-if="selectedTab === 'staying-room'">
        <!-- Phòng đến circle -->
        <div
          class="flex flex-col items-center justify-center w-20 h-20 text-center text-xs rounded-full z-50 cursor-pointer"
          style="background-color: #919f72"
          @click="selectedTab = 'arrival-room'"
        >
          <span class="text-white">Phòng đến</span>
          <span class="text-white"><strong>13</strong></span>
        </div>

        <!-- Phòng đi circle -->
        <div
          class="flex flex-col items-center justify-center w-20 h-20 text-center text-xs rounded-full z-50 cursor-pointer"
          style="background-color: #a95c5c"
          @click="selectedTab = 'empty-room'"
        >
          <span class="text-white">Phòng đi</span>
          <span class="text-white"><strong>11</strong></span>
        </div>
      </template>
    </div>
  </div>

  <!-- CONTENT -->
  <div
    class="flex flex-col gap-5 h-9/10 p-3 overflow-hidden bg-slate-100 text-slate-800 text-s rounded-lg shadow"
  >
    <div
      class="overflow-x-auto max-h-[400px] overflow-y-auto border border-gray-300 rounded-md"
    >
      <table class="border-separate border-spacing-0 text-xs w-max min-w-full">
        <thead class="sticky top-0 z-10">
          <tr class="bg-[#edebeb]">
            <th
              class="border-b border-r border-gray-300 h-15 min-w-15 py-2 text-center font-bold whitespace-nowrap"
              colspan="2"
            >
              <input
                :checked="isAllCompanySelected"
                :ref="
                  (el) => {
                    if (el) el.indeterminate = isIndeterminateCompany;
                  }
                "
                @change="toggleAllCompany()"
                type="checkbox"
                class="w-5 h-5"
              />
            </th>
            <th
              v-for="col in tableColumns"
              :key="col.code"
              class="border-b border-r border-gray-300 h-15 min-w-15 py-2 text-center font-bold whitespace-nowrap"
            >
              <span class="inline-flex items-center gap-1">
                {{ col.title }}
              </span>
            </th>
          </tr>
        </thead>

        <tbody>
          <template
            v-if="tableData?.length"
            v-for="group in tableData"
            :key="group.company"
          >
            <!-- Group header -->
            <tr class="bg-slate-100">
              <td class="border-b border-gray-300">
                <button
                  class="flex items-center justify-center w-5 h-5 mx-auto bg-sky-500 cursor-pointer"
                  @click.stop="
                    group._expanded = !group._expanded;
                    toggleModal('show-more');
                  "
                >
                  <div class="relative w-3 h-3">
                    <!-- Thanh ngang (luôn hiển thị) -->
                    <span
                      class="absolute top-1/2 left-0 w-full h-[1.5px] bg-white -translate-y-1/2"
                    ></span>
                    <!-- Thanh dọc (xoay thành ngang khi expanded) -->
                    <span
                      class="absolute top-1/2 left-0 w-full h-[1.5px] bg-white -translate-y-1/2 transition-transform duration-200"
                      :class="group._expanded ? 'rotate-0' : 'rotate-90'"
                    ></span>
                  </div>
                </button>
              </td>
              <td class="border-b border-r border-gray-300">
                <input
                  type="checkbox"
                  v-model="selectedCompany"
                  :value="group.company"
                  :ref="el => { if (el) el.indeterminate = isCompanyIndeterminate(group) }"
                  @change="handleSelectCompany(group)"
                  class="w-5 h-5 block mx-auto"
                />
              </td>
              <td
                :colspan="tableColumns.length - 1"
                class="border-b border-gray-300 px-3 py-2 font-bold text-sm"
              >
                Công ty: {{ group.company }}
              </td>
            </tr>

            <!-- Data rows -->
            <template v-if="group._expanded">
              <tr v-for="(row, idx) in group.rows" :key="idx">
                <td
                  class="border-b border-r border-gray-300 px-3 py-4 whitespace-nowrap"
                  colspan="2"
                >
                  <input
                    v-model="selectedBooking"
                    :value="row.registration"
                    type="checkbox"
                    class="w-5 h-5 block mx-auto"
                  />
                </td>
                <td
                  v-for="col in tableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 px-3 py-4 whitespace-nowrap"
                >
                  <template
                    v-if="col.code === 'vat' || col.code === 'breakfast'"
                  >
                    <!-- Toggle switch -->
                    <div class="flex justify-center">
                      <div
                        class="relative w-12 h-6 rounded-full cursor-not-allowed transition-colors duration-200"
                        :class="row[col.code] ? 'bg-sky-400' : 'bg-gray-300'"
                      >
                        <div
                          class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-200"
                          :class="row[col.code] ? 'left-6.5' : 'left-0.5'"
                        ></div>
                      </div>
                    </div>
                  </template>
                  <template
                    v-else-if="
                      col.code === 'price' || col.code === 'extraBedPrice'
                    "
                  >
                    {{
                      row[col.code] ? row[col.code].toLocaleString("vi-VN") : ""
                    }}
                  </template>
                  <template v-else>
                    {{ row[col.code] }}
                  </template>
                </td>
              </tr>
            </template>
          </template>
          <template v-else>
            <tr class="bg-slate-100">
              <td
                :colspan="tableColumns.length + 2"
                class="border-b border-r border-gray-300 px-3 py-4 whitespace-nowrap items-center text-center"
              >
                <span class="text-slate-400">No data</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="flex justify-between text-xs">
      <!-- CHECKBOX -->
      <template v-if="selectedTab !== 'hourly-room'">
        <div class="flex gap-2 items-center">
          <input type="checkbox" v-model="isDirtyRoom" class="w-5 h-5" />
          <span class="font-bold"> Phòng đang ở -> Phòng dơ </span>

          <input type="checkbox" v-model="isWaitToCheckRoom" class="w-5 h-5" />
          <span class="font-bold">
            Phòng trống sẵn sàng -> Phòng chờ kiểm tra
          </span>
        </div>
      </template>

      <!-- BUTTON BY TAB -->
      <template v-if="selectedTab === 'staying-room'">
        <div class="flex gap-10">
          <button
            class="bg-sky-300 p-2 rounded-md flex gap-2 hover:shadow-lg cursor-pointer"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="white"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="w-4 h-4 text-white icon"
            >
              <path
                d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
              />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <line x1="10" y1="9" x2="8" y2="9" />
            </svg>
            <span class="font-bold text-white">
              Báo cáo dự kiến doanh thu tiền phòng
            </span>
          </button>

          <button
            class="bg-[#90afc0] p-2 rounded-md flex gap-2 hover:shadow-lg cursor-pointer"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="w-4 h-4 text-slate-200/60 icon"
            >
              <rect x="2" y="5" width="20" height="14" rx="2" />
              <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
            <span class="font-bold text-slate-200/60">Tiền phòng</span>
          </button>
        </div>
      </template>

      <template v-if="selectedTab === 'arrival-room'">
        <div class="flex gap-10">
          <button
            class="bg-[#90afc0] p-2 rounded-md flex gap-2 hover:shadow-lg cursor-pointer items-center"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="w-4 h-4 text-slate-200/60 icon"
            >
              <path
                d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"
              />
              <path
                d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"
              />
              <line x1="1" y1="1" x2="23" y2="23" />
            </svg>
            <span class="font-bold text-slate-200/60">
              Cập nhật ngày đến (No Show One Day)
            </span>
          </button>

          <button
            class="bg-[#90afc0] p-2 rounded-md flex gap-2 hover:shadow-lg cursor-pointer items-center"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="w-4 h-4 text-slate-200/60 icon"
            >
              <path
                d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"
              />
              <path
                d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"
              />
              <line x1="1" y1="1" x2="23" y2="23" />
            </svg>
            <span class="font-bold text-slate-200/60">
              Khách không đến (No Show)
            </span>
          </button>
        </div>
      </template>

      <template v-if="selectedTab === 'empty-room'">
        <button
          class="bg-[#90afc0] p-2 rounded-md flex gap-2 hover:shadow-lg cursor-pointer items-center"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="w-4 h-4 text-slate-200/60 icon"
          >
            <rect x="3" y="4" width="18" height="17" rx="2" />
            <line x1="3" y1="9" x2="21" y2="9" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <path d="M10 16l1.5-1.5 3-3-1.5-1.5-3 3L10 16z" />
            <path d="M13.5 11.5l1.5-1.5" />
          </svg>
          <span class="font-bold text-slate-200/60"> Gia hạn đêm phòng </span>
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
@keyframes wiggle {
  0%,
  100% {
    transform: rotate(0deg);
  }
  20% {
    transform: rotate(-10deg);
  }
  40% {
    transform: rotate(10deg);
  }
  60% {
    transform: rotate(-10deg);
  }
  80% {
    transform: rotate(10deg);
  }
}

button:hover .icon {
  color: white;
  animation: wiggle 0.6s ease-in-out infinite;
}
</style>
