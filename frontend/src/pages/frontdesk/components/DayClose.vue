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
])

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
</script>

<template>
  <!-- CHILD TABS -->
  <div
    class="h-1/10 flex justify-between items-center mb-4 border-b border-gray-200"
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
        class="flex gap-2 items-center group p-3 rounded-md bg-sky-300 hover:bg-sky-400 border-none cursor-pointer"
      >
        <svg
          class="w-5 h-5 text-white calendar-icon"
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
        <span class="text-xs font-bold text-white">Sang ngày</span>
      </button>
    </div>

    <!-- RIGHT SIDE -->
    <div>
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

button:hover .calendar-icon {
  animation: wiggle 0.6s ease-in-out infinite;
}
</style>
