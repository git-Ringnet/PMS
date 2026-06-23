<script setup>
import { ref, computed, onMounted } from "vue";
import { VueDatePicker } from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

//======================= Props ========================//
const childTabs = ref([
  { title: "Đăng Ký", code: "register" },
  { title: "Phòng", code: "room" },
  { title: "Khách", code: "guest" },
]);
const stayStatus = ref([
  { title: "Total", code: "total" },
  { title: "Checked in", code: "checkedIn" },
  { title: "Checked out", code: "checkedOut" },
  { title: "In House", code: "inHouse" },
  { title: "Arrival", code: "arrival" },
  { title: "Departure", code: "departure" },
  { title: "Cancel", code: "cancel" },
]);
const searchByOptions = ref([
  { title: "Tất cả", code: "all" },
  { title: "Booking Code", code: "bookingCode" },
  { title: "Ref Code", code: "refCode" },
  { title: "Booking Name", code: "bookingName" },
  { title: "Booking Status", code: "bookingStatus" },
  { title: "Contact", code: "contact" },
  { title: "Booker", code: "booker" },
  { title: "Company", code: "company" },
  { title: "Market Segment", code: "marketSegment" },
  { title: "Source Code", code: "sourceCode" },
  { title: "Reg Date", code: "regDate" },
  { title: "User Sale", code: "userSale" },
]);
const filterOptions = ref([
  { title: "Check", code: "check" },
  { title: "Mã BK", code: "bookingCode" },
  { title: "Mã Tham Chiếu", code: "refCode" },
  { title: "Tên Đăng Ký", code: "regName" },
  { title: "Công Ty", code: "company" },
  { title: "Thị Trường", code: "market" },
  { title: "Ngày Đến", code: "checkIn" },
  { title: "Ngày Đi", code: "checkOut" },
  { title: "Đêm", code: "nights" },
  { title: "LP Khởi Tạo", code: "origRoomType" },
  { title: "LP Thực Tế", code: "actualRoomType" },
  { title: "Tổng", code: "total" },
  { title: "Đặt Cọc", code: "deposit" },
  { title: "Tình Trạng Lưu Trú", code: "stayStatus1" },
  { title: "Tình Trạng Lưu Trú", code: "stayStatus2" },
  { title: "Liên Hệ", code: "contact" },
  { title: "Ghi Chú", code: "note" },
  { title: "Ngày Đăng Ký", code: "regDate" },
  { title: "Người Bán", code: "salesPerson" },
  { title: "Người Tạo", code: "creator" },
]);
const registerTableColumns = ref([
  { title: "Mã BK", code: "bookingCode", isSort: true },
  { title: "Mã Tham Chiếu", code: "refCode", isSort: false },
  { title: "Tên Đăng Ký", code: "regName", isSort: false },
  { title: "Công Ty", code: "company", isSort: false },
  { title: "Thị Trường", code: "market", isSort: false },
  { title: "Ngày Đến", code: "checkIn", isSort: true },
  { title: "Ngày Đi", code: "checkOut", isSort: true },
  { title: "Đêm", code: "nights", isSort: true },
  { title: "LP Khởi Tạo", code: "origRoomType", isSort: false },
  { title: "LP Thực Tế", code: "actualRoomType", isSort: false },
  { title: "Tổng", code: "total", isSort: false },
  { title: "Đặt Cọc", code: "deposit", isSort: false },
  { title: "Tình Trạng Lưu Trú", code: "stayStatus1", isSort: false },
  { title: "Tình Trạng Lưu Trú", code: "stayStatus2", isSort: false },
  { title: "Liên Hệ", code: "contact", isSort: false },
  { title: "Ghi Chú", code: "note", isSort: false },
  { title: "Ngày Đăng Ký", code: "regDate", isSort: true },
  { title: "Người Bán", code: "salesPerson", isSort: true },
  { title: "Người Tạo", code: "creator", isSort: false },
]);
const roomTypeTableColumns = ref([
  { title: "Loại Phòng", code: "roomType" },
  { title: "#Phòng", code: "roomCount" },
  { title: "#N.Lớn", code: "adultsCount" },
  { title: "#T.Em", code: "childrenCount" },
  { title: "Ngày Đến", code: "checkIn" },
  { title: "Ngày Đi", code: "checkOut" },
  { title: "Mã Giá Phòng", code: "rateCode" },
  { title: "Giá Phòng", code: "rate" },
  { title: "Tổng", code: "total" },
]);
const roomTableColumns = ref([
  { title: "Phòng", code: "roomNumber" },
  { title: "Tình Trạng Phòng", code: "roomStatus" },
  { title: "Tên Khách", code: "guestName" },
  { title: "Ngày Đến", code: "checkIn" },
  { title: "Ngày Đi", code: "checkOut" },
  { title: "Số Đêm", code: "nights" },
  { title: "Giá Phòng", code: "rate" },
  { title: "Mã Giá Phòng", code: "rateCode" },
  { title: "Thêm Giường", code: "extraBed" },
  { title: "Giá TG", code: "extraBedPrice" },
  { title: "Người Lớn", code: "adults" },
  { title: "Trẻ Em", code: "children" },
  { title: "Ghi Chú", code: "note" },
  { title: "Tổng Dịch Vụ", code: "totalService" },
  { title: "Thanh Toán", code: "payment" },
  { title: "Giờ Đến", code: "checkInTime" },
  { title: "Giờ Đi", code: "checkOutTime" },
  { title: "Ngày Đăng Ký", code: "regDate" },
]);
const guestTableColumns = ref([
  { title: "Đăng Ký", code: "regCode" },
  { title: "Phòng", code: "roomNumber" },
  { title: "Tên Khách", code: "guestName" },
  { title: "Ngày Đến", code: "checkIn" },
  { title: "Ngày Đi", code: "checkOut" },
  { title: "Số Đêm", code: "nights" },
  { title: "Giá Phòng", code: "rate" },
  { title: "Mã Giá Phòng", code: "rateCode" },
  { title: "Công Ty DL", code: "travelAgency" },
  { title: "Loại Giấy Tờ", code: "idType" },
  { title: "Số Giấy Tờ", code: "idNumber" },
  { title: "Email", code: "email" },
  { title: "SĐT", code: "phone" },
  { title: "Ngày Sinh", code: "dob" },
  { title: "Quốc Tịch", code: "nationality" },
  { title: "Tỉnh Thành", code: "city" },
  { title: "Địa Chỉ", code: "address" },
  { title: "Visa", code: "visa" },
  { title: "Ngày Hết Hạn", code: "visaExpiry" },
  { title: "Ngày Nhập Cảnh", code: "entryDate" },
  { title: "Cửa Khẩu", code: "portOfEntry" },
]);
const guestHistoryTableColumns = ref([
  { title: "No", code: "no" },
  { title: "Mã đăng ký", code: "regCode" },
  { title: "Tên đăng ký", code: "regName" },
  { title: "Ngày đến", code: "checkIn" },
  { title: "Ngày đi", code: "checkOut" },
  { title: "Số đêm", code: "nights" },
  { title: "Loại phòng", code: "roomType" },
  { title: "Phòng", code: "roomNumber" },
  { title: "Giá phòng", code: "rate" },
  { title: "Doanh thu phòng", code: "roomRevenue" },
  { title: "Doanh thu khác", code: "otherRevenue" },
  { title: "Tổng doanh thu", code: "totalRevenue" },
]);
const today = new Date();

//======================= State ========================//
const selectedTab = ref(childTabs.value[0].code);
const tabRefs = ref([]);
const isFindByDate = ref(false);
const dateRange = ref([today, today]);
const activeModal = ref(null);
const selectedStayStatus = ref(null);
const selectedSearchBy = ref([]);
const selectedFilters = ref([
  "check",
  "bookingCode",
  "refCode",
  "regName",
  "company",
  "market",
  "checkIn",
  "checkOut",
  "nights",
  "origRoomType",
  "actualRoomType",
  "total",
  "deposit",
  "stayStatus1",
  "stayStatus2",
  "contact",
  "note",
  "regDate",
  "salesPerson",
  "creator",
]);
const isHoveringSelect = ref(false);
const roomTypeMockData = ref({});
const selectedRegister = ref([]);
const selectedRoomBooking = ref([]);
const selectedGuest = ref([]);

//======================= MOCK DATA ========================//

//---Register Tab---//
const guestNames = [
  "Walkin Guest",
  "Anh Kha",
  "NỘI BỘ CTY",
  "nội bộ công ty",
  "nội bộ kĩ thuật - anh AN",
  "Nguyễn Văn Sơn",
  "DU LỊCH HẢI YẾN - HÀ NỘI",
  "MAI THỊ XUÂN HƯƠNG",
  "Trần Văn Bình",
  "Lê Thị Hoa",
  "Phạm Minh Tuấn",
  "Hoàng Thị Lan",
  "Vũ Đức Anh",
  "Đặng Thị Mai",
];
const companies = [
  "KHÁCH LẺ",
  "DU LỊCH HẢI YẾN HN",
  "CÔNG TY ABC",
  "VIETRAVEL",
];
const markets = ["Free Individual Traveler", "Travel Agent", "Corporate"];
const statuses = [
  { title: "None Guaranteed", bgColor: "#2c4b5a" },
  { title: "Guaranteed", bgColor: "#4ce410" },
  { title: "Reservation", bgColor: "white" },
  { title: "Checked in", bgColor: "#4ce410" },
  { title: "Checked out", bgColor: "#cbcbcb" },
  { title: "Cancelled", bgColor: "#e5a896" },
];
const sellers = ["Nguyễn Văn A", "Trần Thị B", "Lê Văn C", "Phạm Thị D"];

const generateRegisterMockData = (count) => {
  return Array.from({ length: count }, (_, i) => {
    const dem = Math.floor(Math.random() * 6) + 1;
    const total = dem * 490000;
    return {
      _expanded: false,
      bookingCode: `GAL${i}`,
      refCode: Math.random() > 0.7 ? `REF${1000 + i}` : "",
      regName: guestNames[i % guestNames.length],
      company: companies[i % companies.length],
      market: markets[i % markets.length],
      checkIn: `${String((i % 28) + 1).padStart(2, "0")}/04/2025`,
      checkOut: `${String(((i + dem) % 28) + 1).padStart(2, "0")}/04/2025`,
      nights: dem,
      origRoomType: dem > 0 ? `DLXD (${(i % 3) + 1})` : "",
      actualRoomType: dem > 0 ? `DLXD (${(i % 3) + 1})` : "",
      total: total,
      deposit: Math.random() > 0.6 ? Math.floor(total * 0.3) : 0,
      stayStatus1: statuses[i % statuses.length],
      stayStatus2: statuses[i % statuses.length],
      contact:
        Math.random() > 0.3 ? `09${String(10000000 + i).slice(0, 8)}` : "",
      note: Math.random() > 0.8 ? "Khách VIP" : "",
      regDate: `${String((i % 28) + 1).padStart(2, "0")}/03/2025`,
      salesPerson: sellers[i % sellers.length],
      creator: "admin",
    };
  });
};
const registerTableData = ref(generateRegisterMockData(50));

const generateRoomTypeMockData = () => {
  const roomTypes = ["DLXD", "DLXDB", "SUPT", "DLXT", "FAM", "JST"];
  const rateCodes = ["DLXD01", "DLXDB01", "SUPT01", "DLXT01", "FAM01"];

  registerTableData.value.forEach((row) => {
    if (roomTypeMockData.value[row.bookingCode]) return;

    const count = Math.floor(Math.random() * 3);

    const rooms = Array.from({ length: count }, () => {
      const gia = (Math.floor(Math.random() * 10) + 5) * 100000;
      const dem = row.nights || 1;

      return {
        roomType: roomTypes[Math.floor(Math.random() * roomTypes.length)],
        roomCount: 1,
        adultsCount: Math.floor(Math.random() * 2) + 1,
        childrenCount: Math.floor(Math.random() * 2),
        checkIn: row.checkIn,
        checkOut: row.checkOut,
        rateCode: rateCodes[Math.floor(Math.random() * rateCodes.length)],
        rate: gia,
        total: gia * dem,
      };
    });

    roomTypeMockData.value[row.bookingCode] = rooms;
  });
};
generateRoomTypeMockData();

//---Room Tab---//
const roomStatuses = ["Phòng đã trả", "Đang ở", "Chờ nhận", "Đã đặt", "Trống"];
const roomTabGuestNames = [
  "Nguyễn Văn An",
  "Trần Thị Bình",
  "Lê Minh Tuấn",
  "Phạm Thị Hương",
  "Hoàng Văn Đức",
  "Đặng Thị Mai",
  "Vũ Minh Khoa",
  "Đào Hoàng Vương",
];
const rateCodes = ["FAM01", "DLXD01", "DLXDB01", "SUPT01", "DLXT01", "JST01"];
const notes = [
  "Khách VIP, yêu cầu phòng view biển",
  "Đoàn khách công ty, cần hóa đơn VAT",
  "Trẻ em dưới 6 tuổi miễn phí",
  "",
  "Out late 18h, phụ thu thêm",
  "",
];
const generateRoomTableData = (bookingCount) => {
  return Array.from({ length: bookingCount }, (_, i) => {
    // Logic tính toán ngày
    const checkIn = new Date(2025, 4 + (i % 3), (i % 28) + 1);
    const nights = Math.floor(Math.random() * 5) + 1;
    const checkOut = new Date(checkIn);
    checkOut.setDate(checkOut.getDate() + nights);

    const formatDate = (d) =>
      `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

    const fromDate = formatDate(checkIn);
    const toDate = formatDate(checkOut);
    const regDate = formatDate(
      new Date(checkIn.getTime() - 7 * 24 * 60 * 60 * 1000),
    );

    // Tạo danh sách phòng con cho mỗi booking
    const roomCount = Math.floor(Math.random() * 3) + 1;
    const rooms = Array.from({ length: roomCount }, (_, r) => {
      const rate = (Math.floor(Math.random() * 10) + 5) * 100000;
      const extraBed = Math.random() > 0.7 ? 1 : 0;
      const extraBedPrice = extraBed ? 150000 : 0;
      const totalService = (rate + extraBedPrice) * nights;

      return {
        roomNumber: String(100 + i * 10 + r),
        roomStatus:
          roomStatuses[Math.floor(Math.random() * roomStatuses.length)],
        guestName: guestNames[(i + r) % guestNames.length],
        checkIn: fromDate,
        checkOut: toDate,
        nights: nights,
        rate: rate,
        rateCode: rateCodes[Math.floor(Math.random() * rateCodes.length)],
        extraBed: extraBed,
        extraBedPrice: extraBedPrice,
        adults: Math.floor(Math.random() * 2) + 1,
        children: Math.floor(Math.random() * 3),
        note: Math.random() > 0.7 ? "View biển" : "",
        totalService: totalService,
        payment: Math.random() > 0.5 ? Math.floor(totalService * 0.5) : 0,
        checkInTime:
          Math.random() > 0.3
            ? `${String(10 + (i % 8)).padStart(2, "0")}:00`
            : "",
        checkOutTime: Math.random() > 0.3 ? "12:00" : "",
        regDate: regDate,
      };
    });

    return {
      id: `GAL${100 + i}`,
      bookingHeader: `Booking GAL${100 + i}: ${roomTabGuestNames[i % roomTabGuestNames.length]} _ ${fromDate}~${toDate}`,
      note: notes[i % notes.length],
      _expanded: true,
      rooms: rooms,
    };
  });
};
const roomTableData = ref(generateRoomTableData(50));

//---Guest Tab---//
const guestTabGuestNames = [
  "Mr. Guest 1",
  "Mr. Guest 2",
  "Mr. Guest 3",
  "Mr. Guest 4",
  "Mr. Guest 5",
];
const nationalities = [
  "Việt Nam",
  "Mỹ",
  "Pháp",
  "Nhật Bản",
  "Hàn Quốc",
  "Trung Quốc",
  "Úc",
  "Anh",
];
const provinces = [
  "Hà Nội",
  "TP. Hồ Chí Minh",
  "Đà Nẵng",
  "Hải Phòng",
  "Cần Thơ",
  "Nha Trang",
  "Huế",
];
const idTypes = ["CMND", "CCCD", "Hộ chiếu", "Visa"];
const travelCompanies = [
  "VIETRAVEL",
  "SAIGONTOURIST",
  "BUFFALO TOURS",
  "PEGAS",
  "",
  "",
  "",
];
const borders = [
  "Nội Bài",
  "Tân Sơn Nhất",
  "Đà Nẵng",
  "Móng Cái",
  "Lào Cai",
  "",
];
const generateGuestTableData = (count) => {
  const formatDate = (d) =>
    `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

  return Array.from({ length: count }, (_, i) => {
    const checkIn = new Date(2025, 4 + (i % 3), (i % 28) + 1);
    const nights = Math.floor(Math.random() * 5) + 1;
    const checkOut = new Date(checkIn);
    checkOut.setDate(checkOut.getDate() + nights);

    const birthDate = new Date(
      1970 + Math.floor(Math.random() * 35),
      Math.floor(Math.random() * 12),
      Math.floor(Math.random() * 28) + 1,
    );
    const expDate = new Date(
      2026 + Math.floor(Math.random() * 5),
      Math.floor(Math.random() * 12),
      Math.floor(Math.random() * 28) + 1,
    );
    const entryDate = new Date(checkIn);
    entryDate.setDate(entryDate.getDate() - Math.floor(Math.random() * 3));

    const isVietnam = Math.random() > 0.3;
    const nationality = isVietnam
      ? "Việt Nam"
      : nationalities[Math.floor(Math.random() * nationalities.length)];

    return {
      regCode: `GAL${100 + i}`,
      roomNumber: String(100 + i * 3),
      guestName: guestTabGuestNames[i % guestTabGuestNames.length],
      checkIn: formatDate(checkIn),
      checkOut: formatDate(checkOut),
      nights: nights,
      rate: (Math.floor(Math.random() * 10) + 5) * 100000,
      rateCode: rateCodes[Math.floor(Math.random() * rateCodes.length)],
      travelAgency:
        travelCompanies[Math.floor(Math.random() * travelCompanies.length)],
      idType: isVietnam ? (Math.random() > 0.5 ? "CCCD" : "CMND") : "Hộ chiếu",
      idNumber: String(Math.floor(Math.random() * 900000000) + 100000000),
      email: Math.random() > 0.4 ? `guest${i}@email.com` : "",
      phone: Math.random() > 0.3 ? `09${String(10000000 + i).slice(0, 8)}` : "",
      dob: formatDate(birthDate),
      nationality: nationality,
      city: isVietnam
        ? provinces[Math.floor(Math.random() * provinces.length)]
        : "",
      address: isVietnam
        ? `${Math.floor(Math.random() * 200) + 1} Đường Lê Lợi`
        : "",
      visa:
        !isVietnam && Math.random() > 0.5
          ? `VN${String(Math.floor(Math.random() * 900000) + 100000)}`
          : "",
      visaExpiry: !isVietnam ? formatDate(expDate) : "",
      entryDate: !isVietnam ? formatDate(entryDate) : "",
      portOfEntry: !isVietnam
        ? borders[Math.floor(Math.random() * borders.length)]
        : "",
    };
  });
};
const guestTableData = ref(generateGuestTableData(30));

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
const displaySearchBy = computed(() => {
  const selected = selectedSearchBy.value;
  const count = selected.length;

  if (count === 0) return 0;

  // Nếu có chọn 'all', hiển thị số lượng thực tế (tổng - 1)
  if (selected.includes("all")) {
    const realCount = count - 1;
    return realCount > 0 ? realCount : "Tất cả";
  }

  // Nếu chỉ chọn 1 mục duy nhất (không phải 'all')
  if (count === 1) {
    const found = searchByOptions.value.find((opt) => opt.code === selected[0]);
    return found ? found.title : 0;
  }

  // Nếu chọn nhiều mục nhưng không có 'all'
  return count;
});
const totalAmount = computed(() =>
  registerTableData.value.reduce((sum, row) => sum + (row.total || 0), 0),
);
const allRoomValues = computed(() =>
  roomTableData.value.flatMap((booking) =>
    booking.rooms.map((room) => `${booking.id}-${room.roomNumber}`),
  ),
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
const updateSelection = (changedCode) => {
  const isAll = changedCode === "all";
  const isSelected = selectedSearchBy.value.includes(changedCode);

  if (isAll) {
    // Nếu chọn 'Tất cả' -> tick hết, bỏ chọn 'Tất cả' -> bỏ tick hết
    selectedSearchBy.value = isSelected
      ? searchByOptions.value.map((o) => o.code)
      : [];
  } else {
    // Nếu chọn mục thường -> bỏ tick 'Tất cả' nếu không đủ các mục
    const allIndex = selectedSearchBy.value.indexOf("all");
    if (!isSelected && allIndex > -1) {
      selectedSearchBy.value.splice(allIndex, 1);
    } else if (
      isSelected &&
      selectedSearchBy.value.length === searchByOptions.value.length - 1
    ) {
      selectedSearchBy.value.push("all");
    }
  }
};
const handleChooseAllRegister = () => {
  if (selectedRegister.value.length === registerTableData.value.length) {
    selectedRegister.value = [];
  } else {
    selectedRegister.value = registerTableData.value.map(
      (row) => row.bookingCode,
    );
  }
};
const handleChooseAllRoomBooking = () => {
  if (selectedRoomBooking.value.length === allRoomValues.value.length) {
    selectedRoomBooking.value = [];
  } else {
    selectedRoomBooking.value = allRoomValues.value;
  }
};
const handleChooseAllGuest = () => {
  if (selectedGuest.value.length === guestTableData.value.length) {
    selectedGuest.value = [];
  } else {
    selectedGuest.value = guestTableData.value.map((row) => row.regCode);
  }
};
</script>

<template>
  <!-- CHILD TABS -->
  <div class="h-1/15 relative flex items-center mb-4 border-b border-gray-200">
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

  <!-- MAIN CONTENT -->
  <div
    class="flex flex-col h-14/15 p-3 overflow-hidden bg-slate-100 text-slate-800 text-s rounded-lg shadow"
  >
    <!-- HEADER -->
    <div
      class="flex h-1/5 py-2 px-10 justify-between items-center mb-3 border border-blue-300 rounded-lg"
    >
      <!-- HEADER LEFT -->
      <div class="flex h-full gap-2 justify-start items-center">
        <div
          @click="isFindByDate = isFindByDate === false ? true : false"
          class="relative h-7 rounded-full cursor-pointer transition-all duration-200 select-none flex items-center justify-between w-[150px]"
          :class="
            isFindByDate === true
              ? 'bg-blue-400 pl-2.5 pr-7'
              : 'bg-slate-200 pl-7 pr-2.5'
          "
        >
          <span
            class="text-[11px] font-bold flex-1 text-center transition-all duration-200"
            :class="isFindByDate === true ? 'text-white' : 'text-slate-500'"
          >
            Tìm theo ngày
          </span>

          <div
            class="absolute top-[3px] w-[22px] h-[22px] bg-white rounded-full shadow transition-all duration-200"
            :class="isFindByDate === true ? 'right-[3px]' : 'left-[3px]'"
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
          :disabled="!isFindByDate"
          class="w-60"
          :class="{ 'opacity-50 cursor-not-allowed': !isFindByDate }"
        />

        <div
          class="relative w-48"
          @mouseenter="isHoveringSelect = true"
          @mouseleave="isHoveringSelect = false"
        >
          <div
            @click.stop="toggleModal('stay-status')"
            class="h-8 w-full px-3 py-1 text-[11px] border border-gray-300 rounded flex items-center justify-between cursor-pointer bg-white hover:border-blue-400"
          >
            <input
              type="text"
              placeholder="Tình trạng lưu trú"
              class="cursor-pointer focus:cursor-text"
              :value="
                selectedStayStatus
                  ? stayStatus.find((s) => s.code === selectedStayStatus).title
                  : null
              "
            />

            <!-- Chevron - hiện khi KHÔNG hover -->
            <svg
              v-if="!selectedStayStatus || !isHoveringSelect"
              class="w-3 h-3 text-gray-500 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
              />
            </svg>

            <!-- X icon - hiện khi hover -->
            <svg
              v-else
              class="w-3 h-3 text-gray-500 absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
              @click.stop="selectedStayStatus = null"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </div>

          <div
            v-if="activeModal === 'stay-status'"
            v-click-outside="closeModal"
            class="absolute mt-1 w-full bg-white border border-gray-300 rounded shadow-lg z-50 py-1"
          >
            <div
              v-for="status in stayStatus"
              :key="status.code"
              @click="
                selectedStayStatus = status.code;
                closeModal();
              "
              class="px-4 py-2 text-[13px] cursor-pointer transition-colors"
              :class="
                selectedStayStatus === status.code
                  ? 'bg-sky-400 text-white'
                  : 'hover:bg-gray-100'
              "
            >
              {{ status.title }}
            </div>
          </div>
        </div>
      </div>

      <!-- HEADER RIGHT -->
      <div class="flex h-full w-full gap-2 justify-end items-center">
        <!-- BUTTON ACTIONS -->
        <button
          v-if="selectedTab == 'register'"
          class="flex justify-center items-center w-25 px-3 py-2 text-[11px] font-bold text-white bg-blue-400 rounded hover:bg-blue-600 cursor-pointer transition-all duration-200"
        >
          Nhân bản
        </button>

        <button
          class="flex justify-center items-center w-25 px-3 py-2 text-[11px] font-bold text-white bg-blue-400 rounded hover:bg-blue-600 cursor-pointer transition-all duration-200"
        >
          Tìm kiếm
        </button>

        <button
          class="flex justify-center items-center w-25 px-3 py-2 text-[11px] font-bold text-white bg-blue-400 rounded hover:bg-blue-600 cursor-pointer transition-all duration-200"
        >
          Thao tác
        </button>

        <!-- SEARCH BY -->
        <div class="flex gap-2 items-center w-40">
          <div class="relative">
            <div
              @click.stop="toggleModal('search-by')"
              class="h-9 w-full px-3 py-1 border border-gray-300 rounded flex items-center justify-between cursor-pointer bg-white"
            >
              <span class="text-[13px]">Tìm theo: {{ displaySearchBy }}</span>
              <svg
                class="w-4 h-4 text-gray-500"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  v-if="activeModal !== 'search-by'"
                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                />
                <path
                  v-else
                  d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                />
              </svg>
            </div>

            <!-- DROPDOWN -->
            <div
              v-if="activeModal === 'search-by'"
              v-click-outside="closeModal"
              class="absolute mt-1 w-50 right-0 bg-white border border-gray-300 rounded-lg shadow-xl z-50 p-2"
            >
              <div class="relative mb-2">
                <input
                  v-model="searchQuery"
                  type="text"
                  class="w-full pl-3 pr-8 py-1.5 border border-gray-300 rounded text-[13px] focus:outline-none"
                />
                <svg
                  class="absolute right-2 top-2 w-4 h-4 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  />
                </svg>
              </div>

              <div class="max-h-60 overflow-y-auto space-y-1">
                <label
                  v-for="opt in searchByOptions"
                  :key="opt.code"
                  class="flex items-center gap-3 px-2 py-1.5 hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    v-model="selectedSearchBy"
                    :value="opt.code"
                    @change="updateSelection(opt.code)"
                    class="rounded border-gray-300 w-5 h-5"
                  />
                  <span class="text-[13px] text-gray-700">{{ opt.title }}</span>
                </label>
              </div>

              <button
                @click="closeModal"
                class="w-full mt-2 bg-sky-400 hover:bg-sky-500 text-white py-1.5 rounded text-[13px] font-bold"
              >
                Lưu
              </button>
            </div>
          </div>

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

            <!-- DROPDOWN -->
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
                    class="rounded border-gray-300 text-sky-500 focus:ring-sky-500 w-5 h-5"
                  />
                  <span class="text-[13px] text-gray-700">{{ col.title }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REGISTER TAB -->
    <div v-if="selectedTab == 'register'" class="h-4/5">
      <div class="border border-gray-300 rounded-md overflow-hidden">
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
          <table
            class="border-separate border-spacing-0 text-xs w-max min-w-full"
          >
            <thead class="sticky top-0 z-10">
              <tr class="bg-[#edebeb]">
                <th
                  class="border-b border-r border-gray-300 py-2 w-px px-1 text-center text-center font-bold whitespace-nowrap"
                ></th>
                <th
                  class="border-b border-r border-gray-300 py-2 w-px px-1 text-center text-center font-bold whitespace-nowrap"
                >
                  <input
                    type="checkbox"
                    :checked="
                      selectedRegister.length === registerTableData.length &&
                      registerTableData.length > 0
                    "
                    :indeterminate="
                      selectedRegister.length > 0 &&
                      selectedRegister.length < registerTableData.length
                    "
                    @change="handleChooseAllRegister()"
                    class="w-5 h-5"
                  />
                </th>
                <th
                  v-for="col in registerTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 px-3 text-center font-bold whitespace-nowrap"
                >
                  <span class="inline-flex items-center gap-1">
                    {{ col.title }}
                    <svg
                      v-if="col.isSort"
                      class="w-3 h-3"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      viewBox="0 0 24 24"
                    >
                      <path d="M8 9l4-4 4 4M8 15l4 4 4-4" />
                    </svg>
                  </span>
                </th>
              </tr>
            </thead>

            <tbody>
              <template v-for="(row, index) in registerTableData" :key="index">
                <tr class="group hover:bg-sky-50 bg-white">
                  <template v-if="roomTypeMockData[row.bookingCode]?.length">
                    <td
                      class="border-b border-r border-gray-300 py-2 w-px px-1 text-center whitespace-nowrap align-center"
                    >
                      <button
                        class="flex items-center justify-center w-5 h-5 mx-auto bg-sky-500 cursor-pointer"
                        @click.stop="
                          row._expanded = !row._expanded;
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
                            :class="row._expanded ? 'rotate-0' : 'rotate-90'"
                          ></span>
                        </div>
                      </button>
                    </td>
                  </template>
                  <template v-else>
                    <td class="border-b border-r border-gray-300"></td>
                  </template>
                  <td
                    class="border-b border-r border-gray-300 py-2 w-px px-1 text-center whitespace-nowrap align-center"
                  >
                    <input
                      type="checkbox"
                      v-model="selectedRegister"
                      :value="row.bookingCode"
                      class="w-5 h-5 block mx-auto"
                    />
                  </td>
                  <td
                    v-for="col in registerTableColumns"
                    :key="col.code"
                    class="border-b border-r border-gray-300 py-2 px-3 whitespace-nowrap align-center"
                  >
                    <template
                      v-if="col.code === 'total' || col.code === 'deposit'"
                    >
                      {{ row[col.code].toLocaleString("vi-VN") }}
                    </template>
                    <template
                      v-else-if="
                        col.code === 'stayStatus1' || col.code === 'stayStatus2'
                      "
                    >
                      <span
                        class="inline-block px-2 py-1 rounded text-[11px] font-semibold border border-gray-300"
                        :style="{ backgroundColor: row[col.code].bgColor }"
                      >
                        {{ row[col.code].title }}
                      </span>
                    </template>
                    <template v-else>
                      <span class="whitespace-normal">{{ row[col.code] }}</span>
                    </template>
                  </td>
                </tr>

                <tr v-if="row._expanded" class="bg-slate-50">
                  <td :colspan="14" class="border-b border-gray-300 px-6 py-3">
                    <div class="flex justify-center">
                      <table class="border-separate border-spacing-0 text-xs">
                        <thead>
                          <tr class="bg-[#edebeb]">
                            <th
                              v-for="col in roomTypeTableColumns"
                              :key="col.code"
                              class="border border-gray-300 px-3 py-2 text-left font-bold whitespace-nowrap"
                            >
                              {{ col.title }}
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <template
                            v-if="roomTypeMockData[row.bookingCode]?.length"
                          >
                            <tr
                              v-for="(room, rIdx) in roomTypeMockData[
                                row.bookingCode
                              ]"
                              :key="rIdx"
                              class="bg-white hover:bg-sky-50"
                            >
                              <td
                                v-for="col in roomTypeTableColumns"
                                :key="col.code"
                                class="border border-gray-300 px-3 py-2 whitespace-nowrap"
                              >
                                <template
                                  v-if="
                                    col.code === 'rate' || col.code === 'total'
                                  "
                                >
                                  {{ room[col.code].toLocaleString("vi-VN") }}
                                </template>
                                <template v-else>
                                  {{ room[col.code] }}
                                </template>
                              </td>
                            </tr>

                            <!-- Tổng row -->
                            <tr class="font-bold">
                              <td class="border border-gray-300 px-3 py-2">
                                Tổng
                              </td>
                              <td class="border border-gray-300 px-3 py-2">
                                {{
                                  roomTypeMockData[row.bookingCode].reduce(
                                    (s, r) => s + r.roomCount,
                                    0,
                                  )
                                }}
                              </td>
                              <td class="border border-gray-300 px-3 py-2">
                                {{
                                  roomTypeMockData[row.bookingCode].reduce(
                                    (s, r) => s + r.adultsCount,
                                    0,
                                  )
                                }}
                              </td>
                              <td class="border border-gray-300 px-3 py-2">
                                {{
                                  roomTypeMockData[row.bookingCode].reduce(
                                    (s, r) => s + r.childrenCount,
                                    0,
                                  )
                                }}
                              </td>
                              <td
                                v-for="i in 5"
                                class="border border-gray-300 px-3 py-2"
                              ></td>
                            </tr>
                          </template>

                          <template v-else>
                            <tr>
                              <td
                                :colspan="roomTypeTableColumns.length"
                                class="border border-gray-300 px-3 py-4 text-center text-slate-400"
                              >
                                Không có dữ liệu
                              </td>
                            </tr>
                          </template>
                        </tbody>
                      </table>
                    </div>
                  </td>
                  <td
                    :colspan="registerTableColumns.length - 12"
                    class="border-b border-gray-300 px-6 py-3"
                  ></td>
                </tr>
              </template>

              <!-- Tổng row -->
              <tr class="sticky bottom-0 z-10 bg-white font-bold">
                <td class="border-r border-gray-300 px-3 py-2"></td>
                <td class="border-r border-gray-300 px-3 py-2"></td>
                <td class="border-r font-bold border-gray-300 px-3 py-2">
                  Tổng: {{ registerTableData.length }}
                </td>
                <td
                  v-for="col in registerTableColumns.slice(3)"
                  :key="col.code"
                  class="border-r border-gray-300 px-3 py-2"
                ></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ROOM TAB -->
    <div v-else-if="selectedTab == 'room'">
      <div class="border border-gray-300 rounded-md overflow-hidden">
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
          <table
            class="border-separate border-spacing-0 text-xs w-max min-w-full"
          >
            <thead class="sticky top-0 z-10">
              <tr class="bg-[#edebeb]">
                <th
                  class="border-b border-r border-gray-300 py-2 w-px px-1 text-center font-bold whitespace-nowrap"
                >
                  <input
                    type="checkbox"
                    :checked="
                      selectedRoomBooking.length ===
                        allRoomValues.length &&
                      selectedRoomBooking.length > 0
                    "
                    :indeterminate="
                      selectedRoomBooking.length > 0 &&
                      selectedRoomBooking.length < allRoomValues.length
                    "
                    @change="handleChooseAllRoomBooking()"
                    class="w-5 h-5"
                  />
                </th>
                <th
                  v-for="col in roomTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 px-3 text-center font-bold whitespace-nowrap"
                >
                  {{ col.title }}
                </th>
              </tr>
            </thead>

            <tbody>
              <template v-for="(booking, bIdx) in roomTableData" :key="bIdx">
                <!-- Booking header row (expand toggle) -->
                <tr
                  class="bg-slate-50 cursor-pointer hover:bg-sky-50"
                  @click="booking._expanded = !booking._expanded"
                >
                  <td
                    class="border-b border-r border-gray-300 px-1 py-1.5 text-center"
                  >
                    <button
                      class="flex items-center justify-center w-5 h-5 mx-auto bg-sky-500"
                    >
                      <div class="relative w-3 h-3">
                        <span
                          class="absolute top-1/2 left-0 w-full h-[1.5px] bg-white -translate-y-1/2"
                        ></span>
                        <span
                          class="absolute top-1/2 left-0 w-full h-[1.5px] bg-white -translate-y-1/2 transition-transform duration-200"
                          :class="booking._expanded ? 'rotate-0' : 'rotate-90'"
                        ></span>
                      </div>
                    </button>
                  </td>
                  <td
                    :colspan="roomTableColumns.length - 1"
                    class="border-b border-r border-gray-300 px-3 py-3"
                  >
                    <div class="font-bold text-slate-700">
                      {{ booking.bookingHeader }}
                    </div>
                    <div v-if="booking.note" class="text-slate-500 mt-0.5">
                      Ghi chú: {{ booking.note }}
                    </div>
                  </td>
                </tr>

                <!-- Room rows (hiện khi expanded) -->
                <template v-if="booking._expanded">
                  <tr
                    v-for="(room, rIdx) in booking.rooms"
                    :key="rIdx"
                    class="bg-slate-300 border border-white"
                  >
                    <td
                      class="border-b border-r border-white py-2 w-px px-1 text-center whitespace-nowrap"
                    >
                      <input
                        type="checkbox"
                        v-model="selectedRoomBooking"
                        :value="`${booking.id}-${room.roomNumber}`"
                        class="w-5 h-5 block mx-auto"
                      />
                    </td>
                    <td
                      v-for="col in roomTableColumns"
                      :key="col.code"
                      class="border-b border-r border-white py-2 px-3 whitespace-nowrap"
                    >
                      <template
                        v-if="
                          col.code === 'rate' ||
                          col.code === 'extraBedPrice' ||
                          col.code === 'totalService' ||
                          col.code === 'payment'
                        "
                      >
                        {{ room[col.code].toLocaleString("vi-VN") }}
                      </template>
                      <template v-else>
                        {{ room[col.code] }}
                      </template>
                    </td>
                  </tr>
                </template>
              </template>

              <!-- Tổng row -->
              <tr class="sticky bottom-0 z-10 bg-white font-bold">
                <td class="border-r border-gray-300 px-3 py-2"></td>
                <td class="border-r font-bold border-gray-300 px-3 py-2">
                  Tổng: {{ roomTableData.length }}
                </td>
                <td
                  v-for="i in roomTableColumns.slice(2)"
                  class="border-r border-gray-300 px-3 py-2"
                ></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- GUEST TAB -->
    <div v-else-if="selectedTab == 'guest'">
      <div class="border border-gray-300 rounded-md overflow-hidden">
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
          <table
            class="border-separate border-spacing-0 text-xs w-max min-w-full"
          >
            <thead class="sticky top-0 z-10">
              <tr class="bg-[#edebeb]">
                <th
                  class="border-b border-r border-gray-300 py-2 w-px px-1 text-center font-bold whitespace-nowrap"
                >
                  <input
                    :checked="selectedGuest.length > 0 && selectedGuest.length === guestTableData.length"
                    :indeterminate="selectedGuest.length > 0 && selectedGuest.length < guestTableData.length"
                    @change="handleChooseAllGuest()"
                    type="checkbox"
                    class="w-5 h-5"
                  />
                </th>
                <th
                  v-for="col in guestTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 px-3 text-center font-bold whitespace-nowrap"
                >
                  {{ col.title }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(guest, rIdx) in guestTableData"
                :key="rIdx"
                class="bg-slate-300 border border-white"
                @click.stop="toggleModal('guest-modal')"
              >
                <td
                  class="border-b border-r border-white py-2 w-px px-1 text-center whitespace-nowrap group hover:cursor-pointer"
                >
                  <input
                    type="checkbox"
                    v-model="selectedGuest"
                    :value="guest.regCode"
                    class="w-5 h-5 block mx-auto"
                    @click.stop
                  />
                </td>
                <td
                  v-for="col in guestTableColumns"
                  :key="col.code"
                  class="border-b border-r border-white py-2 px-3 whitespace-nowrap group hover:cursor-pointer"
                >
                  {{ guest[col.code] }}
                </td>
              </tr>

              <!-- Tổng row -->
              <tr class="sticky bottom-0 z-10 bg-white font-bold">
                <td class="border-r border-gray-300 px-3 py-2"></td>
                <td class="border-r font-bold border-gray-300 px-3 py-2">
                  Tổng: {{ guestTableData.length }}
                </td>
                <td
                  v-for="i in guestTableColumns.slice(2)"
                  class="border-r border-gray-300 px-3 py-2"
                ></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- GUEST MODAL -->
  <Transition name="slide-right">
    <div
      v-if="activeModal == 'guest-modal'"
      class="fixed inset-0 z-50 flex justify-end"
    >
      <!-- Backdrop -->
      <div class="absolute inset-0" @click="closeModal()"></div>

      <!-- Modal panel -->
      <div
        class="relative w-1/2 h-full bg-white shadow-xl overflow-y-auto flex flex-col"
      >
        <!-- Close button -->
        <button
          @click="closeModal()"
          class="absolute top-3 left-3 text-red-500 cursor-pointer bg-transparent border-none text-lg font-bold"
        >
          <svg
            class="w-5 h-5 transition-transform duration-200 hover:rotate-180"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
          >
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>

        <div class="px-8 py-10 flex flex-col gap-6">
          <!-- Guest Information -->
          <div>
            <h2 class="text-base font-bold text-slate-800 mb-3">
              Guest Information
            </h2>
            <div
              class="border border-slate-200 rounded-lg p-5 flex flex-col gap-4"
            >
              <!-- Avatar + Name row -->
              <div class="flex gap-5">
                <!-- Avatar -->
                <div
                  class="w-36 h-36 border border-slate-200 rounded-sm shrink-0 bg-slate-50"
                ></div>

                <!-- Name fields -->
                <div class="flex flex-col gap-3 flex-1">
                  <!-- Tên -->
                  <div>
                    <label class="block text-xs text-slate-500 mb-1">Tên</label>
                    <div class="flex gap-2">
                      <div class="relative w-24">
                        <select
                          class="w-full appearance-none border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white pr-7"
                        >
                          <option>Mr.</option>
                          <option>Mrs.</option>
                          <option>Ms.</option>
                          <option>Dr.</option>
                        </select>
                        <svg
                          class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          viewBox="0 0 24 24"
                        >
                          <path d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                      <input
                        type="text"
                        value="Guest 1"
                        class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-xs"
                      />
                      <input
                        type="text"
                        value="Male"
                        class="w-20 border border-slate-200 rounded-lg px-3 py-2 text-xs bg-slate-50"
                        readonly
                      />
                    </div>
                  </div>

                  <!-- Sinh nhật + Quốc gia -->
                  <div class="flex gap-3">
                    <div class="flex-1">
                      <label class="block text-xs text-slate-500 mb-1"
                        >Sinh nhật</label
                      >
                      <div
                        class="flex items-center border border-slate-200 rounded-lg px-3 py-2 gap-2"
                      >
                        <span class="text-xs text-slate-400">/ /</span>
                        <svg
                          class="w-4 h-4 text-green-500 ml-auto"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          viewBox="0 0 24 24"
                        >
                          <rect x="3" y="4" width="18" height="18" rx="2" />
                          <line x1="16" y1="2" x2="16" y2="6" />
                          <line x1="8" y1="2" x2="8" y2="6" />
                          <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                      </div>
                    </div>
                    <div class="flex-1">
                      <label class="block text-xs text-slate-500 mb-1"
                        >Quốc gia</label
                      >
                      <div class="relative">
                        <select
                          class="w-full appearance-none border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white pr-7"
                        >
                          <option>Vietnam ( Việt Nam )</option>
                          <option>USA ( Mỹ )</option>
                          <option>Japan ( Nhật Bản )</option>
                        </select>
                        <svg
                          class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          viewBox="0 0 24 24"
                        >
                          <path d="M19 9l-7 7-7-7" />
                        </svg>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Điện thoại + Địa chỉ -->
              <div class="flex gap-3">
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Điện thoại</label
                  >
                  <input
                    type="text"
                    placeholder="Phone number"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Địa chỉ</label
                  >
                  <input
                    type="text"
                    placeholder="Address"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
              </div>

              <!-- Email + Giấy tờ tùy thân -->
              <div class="flex gap-3">
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1">Email</label>
                  <input
                    type="email"
                    placeholder="Email"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Giấy tờ tùy thân</label
                  >
                  <div class="relative">
                    <select
                      class="w-full appearance-none border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white pr-7"
                    >
                      <option value="">Select Value</option>
                      <option>CMND</option>
                      <option>CCCD</option>
                      <option>Hộ chiếu</option>
                      <option>Visa</option>
                    </select>
                    <svg
                      class="w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      viewBox="0 0 24 24"
                    >
                      <path d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Số giấy tờ + Ngày hết hạn -->
              <div class="flex gap-3">
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Số giấy tờ</label
                  >
                  <input
                    type="text"
                    placeholder="Passport ID"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Ngày hết hạn</label
                  >
                  <div
                    class="flex items-center border border-slate-200 rounded-lg px-3 py-2 gap-2"
                  >
                    <span class="text-xs text-slate-400">/ /</span>
                    <svg
                      class="w-4 h-4 text-green-500 ml-auto"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      viewBox="0 0 24 24"
                    >
                      <rect x="3" y="4" width="18" height="18" rx="2" />
                      <line x1="16" y1="2" x2="16" y2="6" />
                      <line x1="8" y1="2" x2="8" y2="6" />
                      <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                  </div>
                </div>
              </div>

              <!-- Return Number + Return All Branches -->
              <div class="flex gap-3">
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Return Number</label
                  >
                  <input
                    type="number"
                    value="0"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-slate-500 mb-1"
                    >Return All Branches</label
                  >
                  <input
                    type="number"
                    value="0"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Note -->
          <div>
            <h2 class="text-base font-bold text-slate-800 mb-3">Note</h2>
            <textarea
              placeholder="Note"
              rows="4"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs resize-none"
            ></textarea>
          </div>

          <!-- Guest History -->
          <div>
            <h2 class="text-base font-bold text-slate-800 mb-3">
              Guest History
            </h2>
            <div class="border border-slate-300 rounded-md overflow-hidden">
              <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                <table
                  class="border-separate border-spacing-0 text-xs w-max min-w-full"
                >
                  <thead class="sticky top-0 z-10">
                    <tr class="bg-[#edebeb]">
                      <th
                        v-for="column in guestHistoryTableColumns"
                        class="px-3 py-2 text-left font-bold border-b border-r border-slate-300"
                      >
                        {{ column.title }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="text-slate-400">
                      <td
                        :colspan="guestHistoryTableColumns.length"
                        class="px-3 py-6 text-center"
                      >
                        Không có dữ liệu
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.3s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
  transform: translateX(100%);
}

.slide-right-enter-to,
.slide-right-leave-from {
  transform: translateX(0);
}
</style>
