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
  { title: "Checked in", code: "checked-in" },
  { title: "Checked out", code: "checked-out" },
  { title: "In House", code: "in-house" },
  { title: "Arrival", code: "arrival" },
  { title: "Departure", code: "departure" },
  { title: "Cancel", code: "cancel" },
]);
const searchByOptions = ref([
  { title: "Tất cả", code: "all" },
  { title: "Booking Code", code: "booking-code" },
  { title: "Ref Code", code: "ref-code" },
  { title: "Booking Name", code: "booking-name" },
  { title: "Booking Status", code: "booking-status" },
  { title: "Contact", code: "contact" },
  { title: "Booker", code: "booker" },
  { title: "Company", code: "company" },
  { title: "Market Segment", code: "market-segment" },
  { title: "Source Code", code: "source-code" },
  { title: "Reg Date", code: "reg-date" },
  { title: "User Sale", code: "user-sale" },
]);
const filterOptions = ref([
  { title: "Check", code: "check" },
  { title: "Mã BK", code: "ma-bk" },
  { title: "Mã Tham Chiếu", code: "ma-tham-chieu" },
  { title: "Tên Đăng Ký", code: "ten-dang-ky" },
  { title: "Công Ty", code: "cong-ty" },
  { title: "Thị Trường", code: "thi-truong" },
  { title: "Ngày Đến", code: "ngay-den" },
  { title: "Ngày Đi", code: "ngay-di" },
  { title: "Đêm", code: "dem" },
  { title: "LP Khởi Tạo", code: "lp-khoi-tao" },
  { title: "LP Thực Tế", code: "lp-thuc-te" },
  { title: "Tổng", code: "tong" },
  { title: "Đặt Cọc", code: "dat-coc" },
  { title: "Tình Trạng Lưu Trú", code: "tinh-trang-luu-tru" },
  { title: "Tình Trạng Lưu Trú", code: "tinh-trang-luu-tru" },
  { title: "Liên Hệ", code: "lien-he" },
  { title: "Ghi Chú", code: "ghi-chu" },
  { title: "Ngày Đăng Ký", code: "ngay-dang-ky" },
  { title: "Người Bán", code: "nguoi-ban" },
  { title: "Người Tạo", code: "nguoi-tao" },
]);
const registerTableColumns = ref([
  { title: "", code: "more", isSort: false },
  { title: "Check", code: "check", isSort: false },
  { title: "Mã BK", code: "ma-bk", isSort: true },
  { title: "Mã Tham Chiếu", code: "ma-tham-chieu", isSort: false },
  { title: "Tên Đăng Ký", code: "ten-dang-ky", isSort: false },
  { title: "Công Ty", code: "cong-ty", isSort: false },
  { title: "Thị Trường", code: "thi-truong", isSort: false },
  { title: "Ngày Đến", code: "ngay-den", isSort: true },
  { title: "Ngày Đi", code: "ngay-di", isSort: true },
  { title: "Đêm", code: "dem", isSort: true },
  { title: "LP Khởi Tạo", code: "lp-khoi-tao", isSort: false },
  { title: "LP Thực Tế", code: "lp-thuc-te", isSort: false },
  { title: "Tổng", code: "tong", isSort: false },
  { title: "Đặt Cọc", code: "dat-coc", isSort: false },
  { title: "Tình Trạng Lưu Trú", code: "tinh-trang-luu-tru", isSort: false },
  { title: "Tình Trạng Lưu Trú", code: "tinh-trang-luu-tru", isSort: false },
  { title: "Liên Hệ", code: "lien-he", isSort: false },
  { title: "Ghi Chú", code: "ghi-chu", isSort: false },
  { title: "Ngày Đăng Ký", code: "ngay-dang-ky", isSort: true },
  { title: "Người Bán", code: "nguoi-ban", isSort: true },
  { title: "Người Tạo", code: "nguoi-tao", isSort: false },
]);
const roomTypeTableColumns = ref([
  { title: "Loại Phòng", code: "loai-phong" },
  { title: "#Phòng", code: "#-phong" },
  { title: "#N.Lớn", code: "#-nguoi-lon" },
  { title: "#T.Em", code: "#-tre-em" },
  { title: "Ngày Đến", code: "ngay-den" },
  { title: "Ngày Đi", code: "ngay-di" },
  { title: "Mã Giá Phòng", code: "ma-gia-phong" },
  { title: "Giá Phòng", code: "gia-phong" },
  { title: "Tổng", code: "tong" },
]);
const roomTableColumns = ref([
  { title: "Check", code: "check" },
  { title: "Phòng", code: "phong" },
  { title: "Tình Trạng Phòng", code: "tinh-trang-phong" },
  { title: "Tên Khách", code: "ten-khach" },
  { title: "Ngày Đến", code: "ngay-den" },
  { title: "Ngày Đi", code: "ngay-di" },
  { title: "Số Đêm", code: "so-dem" },
  { title: "Giá Phòng", code: "gia-phong" },
  { title: "Mã Giá Phòng", code: "ma-gia-phong" },
  { title: "Thêm Giường", code: "them-giuong" },
  { title: "Giá TG", code: "gia-tg" },
  { title: "Người Lớn", code: "nguoi-lon" },
  { title: "Trẻ Em", code: "tre-em" },
  { title: "Ghi Chú", code: "ghi-chu" },
  { title: "Tổng Dịch Vụ", code: "tong-dich-vu" },
  { title: "Thanh Toán", code: "thanh-toan" },
  { title: "Giờ Đến", code: "gio-den" },
  { title: "Giờ Đi", code: "gio-di" },
  { title: "Ngày Đăng Ký", code: "ngay-dang-ky" },
]);
const guestTableColumns = ref([
  { title: "Check", code: "check" },
  { title: "Đăng Ký", code: "dang-ky" },
  { title: "Phòng", code: "phong" },
  { title: "Tên Khách", code: "ten-khach" },
  { title: "Ngày Đến", code: "ngay-den" },
  { title: "Ngày Đi", code: "ngay-di" },
  { title: "Số Đêm", code: "so-dem" },
  { title: "Giá Phòng", code: "gia-phong" },
  { title: "Mã Giá Phòng", code: "ma-gia-phong" },
  { title: "Công Ty DL", code: "cong-ty-dl" },
  { title: "Loại Giấy Tờ", code: "loai-giay-to" },
  { title: "Số Giấy Tờ", code: "so-giay-to" },
  { title: "Email", code: "email" },
  { title: "SĐT", code: "sdt" },
  { title: "Ngày Sinh", code: "ngay-sinh" },
  { title: "Quốc Tịch", code: "quoc-tich" },
  { title: "Tỉnh Thành", code: "tinh-thanh" },
  { title: "Địa Chỉ", code: "dia-chi" },
  { title: "Visa", code: "visa" },
  { title: "Ngày Hết Hạn", code: "ngay-het-han" },
  { title: "Ngày Nhập Cảnh", code: "ngay-nhap-canh" },
  { title: "Cửa Khẩu", code: "cua-khau" },
]);
const guestHistoryTableColumns = ref([
  { title: "No", code: "no" },
  { title: "Mã đăng ký", code: "ma-dang-ky" },
  { title: "Tên đăng ký", code: "ten-dang-ky" },
  { title: "Ngày đến", code: "ngay-den" },
  { title: "Ngày đi", code: "ngay-di" },
  { title: "Số đêm", code: "so-dem" },
  { title: "Loại phòng", code: "loai-phong" },
  { title: "Phòng", code: "phong" },
  { title: "Giá phòng", code: "gia-phong" },
  { title: "Doanh thu phòng", code: "doanh-thu-phong" },
  { title: "Doanh thu khác", code: "doanh-thu-khac" },
  { title: "Tổng doanh thu", code: "tong-doanh-thu" },
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
   "ma-bk",
  "ma-tham-chieu",
  "ten-dang-ky",
  "cong-ty",
  "thi-truong",
  "ngay-den",
   "ngay-di",
  "dem",
  "lp-khoi-tao",
  "lp-thuc-te",
  "tong",
  "dat-coc",
  "tinh-trang-luu-tru",
  "tinh-trang-luu-tru",
  "lien-he",
  "ghi-chu",
  "ngay-dang-ky",
  "nguoi-ban",
  "nguoi-tao",
]);
const isHoveringSelect = ref(false);
const roomTypeMockData = ref({});

//======================= MOCK DATA ========================//
// Register Tab
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

const generateMockData = (count) => {
  const data = [];
  for (let i = 1; i <= count; i++) {
    const dem = Math.floor(Math.random() * 6);
    const tong = dem * 490000 * (Math.floor(Math.random() * 3) + 1);
    data.push({
      _expanded: false,
      check: false,
      "ma-bk": `GAL${i}`,
      "ma-tham-chieu": Math.random() > 0.7 ? `REF${1000 + i}` : "",
      "ten-dang-ky": guestNames[i % guestNames.length],
      "cong-ty": companies[i % companies.length],
      "thi-truong": markets[i % markets.length],
      "ngay-den": `${String((i % 28) + 1).padStart(2, "0")}/04/2025`,
      "ngay-di": `${String(((i + dem) % 28) + 1).padStart(2, "0")}/04/2025`,
      dem,
      "lp-khoi-tao": dem > 0 ? `DLXD (${(i % 3) + 1})` : "",
      "lp-thuc-te": dem > 0 ? `DLXD (${(i % 3) + 1})` : "",
      tong,
      "dat-coc": Math.random() > 0.6 ? Math.floor(tong * 0.3) : 0,
      "tinh-trang-luu-tru": statuses[i % statuses.length],
      "lien-he":
        Math.random() > 0.3 ? `09${String(10000000 + i).slice(0, 8)}` : "",
      "ghi-chu": Math.random() > 0.8 ? "Khách VIP" : "",
      "ngay-dang-ky": `${String((i % 28) + 1).padStart(2, "0")}/03/2025`,
      "nguoi-ban": sellers[i % sellers.length],
      "nguoi-tao": "admin",
    });
  }
  return data;
};
const generateRoomTypeMockData = () => {
  const roomTypes = ["DLXD", "DLXDB", "SUPT", "DLXT", "FAM", "JST"];
  const rateCodes = ["DLXD01", "DLXDB01", "SUPT01", "DLXT01", "FAM01"];

  registerTableData.value.forEach((row) => {
    if (roomTypeMockData[row["ma-bk"]]) return; // skip nếu đã có data thủ công

    const count = Math.floor(Math.random() * 3); // 0, 1, hoặc 2
    const rooms = [];

    for (let i = 0; i < count; i++) {
      const gia = (Math.floor(Math.random() * 10) + 5) * 100000;
      const dem = row.dem || 1;
      rooms.push({
        "loai-phong": roomTypes[Math.floor(Math.random() * roomTypes.length)],
        "#-phong": 1,
        "#-nguoi-lon": Math.floor(Math.random() * 2) + 1,
        "#-tre-em": Math.floor(Math.random() * 2),
        "ngay-den": row["ngay-den"],
        "ngay-di": row["ngay-di"],
        "ma-gia-phong": rateCodes[Math.floor(Math.random() * rateCodes.length)],
        "gia-phong": gia,
        tong: gia * dem,
      });
    }

    roomTypeMockData.value[row["ma-bk"]] = rooms;
  });
};
const registerTableData = ref(generateMockData(50));
generateRoomTypeMockData();

// Room Tab
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
  const data = [];

  for (let i = 1; i <= bookingCount; i++) {
    const checkIn = new Date(2025, 4 + (i % 3), (i % 28) + 1);
    const nights = Math.floor(Math.random() * 5) + 1;
    const checkOut = new Date(checkIn);
    checkOut.setDate(checkOut.getDate() + nights);

    const formatDate = (d) =>
      `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

    const guestName = roomTabGuestNames[i % roomTabGuestNames.length];
    const fromDate = formatDate(checkIn);
    const toDate = formatDate(checkOut);

    const roomCount = Math.floor(Math.random() * 3) + 1;
    const rooms = [];

    for (let r = 0; r < roomCount; r++) {
      const giaPhong = (Math.floor(Math.random() * 10) + 5) * 100000;
      const themGiuong = Math.random() > 0.7 ? 1 : 0;
      const giaTG = themGiuong ? 150000 : 0;
      const nguoiLon = Math.floor(Math.random() * 2) + 1;
      const treEm = Math.floor(Math.random() * 3);
      const tongDichVu = (giaPhong + giaTG) * nights;
      const thanhToan = Math.random() > 0.5 ? Math.floor(tongDichVu * 0.5) : 0;

      rooms.push({
        check: false,
        phong: String(100 + i * 10 + r),
        "tinh-trang-phong":
          roomStatuses[Math.floor(Math.random() * roomStatuses.length)],
        "ten-khach": guestNames[(i + r) % guestNames.length],
        "ngay-den": fromDate,
        "ngay-di": toDate,
        "so-dem": nights,
        "gia-phong": giaPhong,
        "ma-gia-phong": rateCodes[Math.floor(Math.random() * rateCodes.length)],
        "them-giuong": themGiuong,
        "gia-tg": giaTG,
        "nguoi-lon": nguoiLon,
        "tre-em": treEm,
        "ghi-chu": Math.random() > 0.7 ? "View biển" : "",
        "tong-dich-vu": tongDichVu,
        "thanh-toan": thanhToan,
        "gio-den":
          Math.random() > 0.3
            ? `${String(10 + (i % 8)).padStart(2, "0")}:00`
            : "",
        "gio-di": Math.random() > 0.3 ? "12:00" : "",
        "ngay-dang-ky": formatDate(
          new Date(checkIn.getTime() - 7 * 24 * 60 * 60 * 1000),
        ),
      });
    }

    data.push({
      id: `GAL${100 + i}`,
      bookingHeader: `Booking GAL${100 + i}: ${guestName} _ ${fromDate}~${toDate} _ Room Night: ${nights}`,
      note: notes[i % notes.length],
      _expanded: true,
      rooms,
    });
  }

  return data;
};
const roomTableData = ref(generateRoomTableData(50));

// Guest Tab
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
  const data = [];

  const formatDate = (d) =>
    `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;

  for (let i = 1; i <= count; i++) {
    const checkIn = new Date(2025, 4 + (i % 3), (i % 28) + 1);
    const nights = Math.floor(Math.random() * 5) + 1;
    const checkOut = new Date(checkIn);
    checkOut.setDate(checkOut.getDate() + nights);

    const birthYear = 1970 + Math.floor(Math.random() * 35);
    const birthDate = new Date(
      birthYear,
      Math.floor(Math.random() * 12),
      Math.floor(Math.random() * 28) + 1,
    );

    const expYear = 2026 + Math.floor(Math.random() * 5);
    const expDate = new Date(
      expYear,
      Math.floor(Math.random() * 12),
      Math.floor(Math.random() * 28) + 1,
    );

    const entryDate = new Date(checkIn);
    entryDate.setDate(entryDate.getDate() - Math.floor(Math.random() * 3));

    const isVietnam = Math.random() > 0.3;
    const nationality = isVietnam
      ? "Việt Nam"
      : nationalities[Math.floor(Math.random() * nationalities.length)];

    data.push({
      check: false,
      "dang-ky": `GAL${100 + i}`,
      phong: String(100 + i * 3),
      "ten-khach": guestTabGuestNames[i % guestTabGuestNames.length],
      "ngay-den": formatDate(checkIn),
      "ngay-di": formatDate(checkOut),
      "so-dem": nights,
      "gia-phong": (Math.floor(Math.random() * 10) + 5) * 100000,
      "ma-gia-phong": rateCodes[Math.floor(Math.random() * rateCodes.length)],
      "cong-ty-dl":
        travelCompanies[Math.floor(Math.random() * travelCompanies.length)],
      "loai-giay-to": isVietnam
        ? Math.random() > 0.5
          ? "CCCD"
          : "CMND"
        : "Hộ chiếu",
      "so-giay-to": String(Math.floor(Math.random() * 900000000) + 100000000),
      email: Math.random() > 0.4 ? `guest${i}@email.com` : "",
      sdt: Math.random() > 0.3 ? `09${String(10000000 + i).slice(0, 8)}` : "",
      "ngay-sinh": formatDate(birthDate),
      "quoc-tich": nationality,
      "tinh-thanh": isVietnam
        ? provinces[Math.floor(Math.random() * provinces.length)]
        : "",
      "dia-chi": isVietnam
        ? `${Math.floor(Math.random() * 200) + 1} Đường Lê Lợi`
        : "",
      visa:
        !isVietnam && Math.random() > 0.5
          ? `VN${String(Math.floor(Math.random() * 900000) + 100000)}`
          : "",
      "ngay-het-han": !isVietnam ? formatDate(expDate) : "",
      "ngay-nhap-canh": !isVietnam ? formatDate(entryDate) : "",
      "cua-khau": !isVietnam
        ? borders[Math.floor(Math.random() * borders.length)]
        : "",
    });
  }

  return data;
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
  registerTableData.value.reduce((sum, row) => sum + (row["tong"] || 0), 0),
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
        <div class="relative w-40">
          <div class="flex gap-2 items-center">
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
                  v-if="!isSearchByDropdownOpen"
                  d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                />
                <path
                  v-else
                  d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                />
              </svg>
            </div>

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
          </div>

          <!-- DROPDOWN -->
          <div
            v-if="activeModal === 'search-by'"
            v-click-outside="closeModal"
            class="absolute mt-1 w-50 bg-white border border-gray-300 rounded-lg shadow-xl z-50 p-2"
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
    </div>

    <!-- REGISTER TAB -->
    <div v-if="selectedTab == 'register'" class="h-4/5">
      <div class="border border-gray-300 rounded-md overflow-hidden">
        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
          <table
            class="border-separate border-spacing-0 text-xs w-max min-w-full"
          >
            <thead class="sticky top-0 z-10">
              <tr class="bg-slate-100">
                <th
                  v-for="col in registerTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 text-center font-bold whitespace-nowrap"
                  :class="
                    col.code === 'check' || col.code === 'more'
                      ? 'w-px px-1 text-center'
                      : 'px-3'
                  "
                >
                  <template v-if="col.code === 'check'">
                    <input type="checkbox" class="w-5 h-5" />
                  </template>
                  <template v-else>
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
                  </template>
                </th>
              </tr>
            </thead>

            <tbody>
              <template v-for="(row, index) in registerTableData" :key="index">
                <tr class="group hover:bg-sky-50 bg-white">
                  <td
                    v-for="col in registerTableColumns"
                    :key="col.code"
                    class="border-b border-r border-gray-300 py-2 whitespace-nowrap align-center"
                    :class="
                      col.code === 'check' || col.code === 'more'
                        ? 'w-px px-1 text-center'
                        : 'px-3'
                    "
                  >
                    <template
                      v-if="
                        col.code === 'more' &&
                        roomTypeMockData[row['ma-bk']]?.length
                      "
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
                    </template>
                    <template v-if="col.code === 'check'">
                      <input
                        type="checkbox"
                        v-model="row.check"
                        class="w-5 h-5 block mx-auto cursor-pointer"
                      />
                    </template>
                    <template
                      v-else-if="col.code === 'tong' || col.code === 'dat-coc'"
                    >
                      {{ row[col.code].toLocaleString("vi-VN") }}
                    </template>
                    <template v-else-if="col.code === 'tinh-trang-luu-tru'">
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
                          <tr class="bg-slate-200">
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
                            v-if="roomTypeMockData[row['ma-bk']]?.length"
                          >
                            <tr
                              v-for="(room, rIdx) in roomTypeMockData[
                                row['ma-bk']
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
                                    col.code === 'gia-phong' ||
                                    col.code === 'tong'
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
                                  roomTypeMockData[row["ma-bk"]].reduce(
                                    (s, r) => s + r["#-phong"],
                                    0,
                                  )
                                }}
                              </td>
                              <td class="border border-gray-300 px-3 py-2">
                                {{
                                  roomTypeMockData[row["ma-bk"]].reduce(
                                    (s, r) => s + r["#-nguoi-lon"],
                                    0,
                                  )
                                }}
                              </td>
                              <td class="border border-gray-300 px-3 py-2">
                                {{
                                  roomTypeMockData[row["ma-bk"]].reduce(
                                    (s, r) => s + r["#-tre-em"],
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
                    :colspan="registerTableColumns.length - 14"
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
                  v-for="col in registerTableColumns.slice(2)"
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
              <tr class="bg-slate-100">
                <th
                  v-for="col in roomTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 text-center font-bold whitespace-nowrap"
                  :class="col.code === 'check' ? 'w-px px-1' : 'px-3'"
                >
                  <template v-if="col.code === 'check'">
                    <input type="checkbox" class="w-5 h-5" />
                  </template>
                  <template v-else>{{ col.title }}</template>
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
                      v-for="col in roomTableColumns"
                      :key="col.code"
                      class="border-b border-r border-white py-2 whitespace-nowrap"
                      :class="
                        col.code === 'check' ? 'w-px px-1 text-center' : 'px-3'
                      "
                    >
                      <template v-if="col.code === 'check'">
                        <input
                          type="checkbox"
                          v-model="room.check"
                          class="w-5 h-5 block mx-auto"
                        />
                      </template>
                      <template
                        v-else-if="
                          col.code === 'gia-phong' ||
                          col.code === 'gia-tg' ||
                          col.code === 'tong-dich-vu' ||
                          col.code === 'thanh-toan'
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
              <tr class="bg-slate-100">
                <th
                  v-for="col in guestTableColumns"
                  :key="col.code"
                  class="border-b border-r border-gray-300 py-2 text-center font-bold whitespace-nowrap"
                  :class="col.code === 'check' ? 'w-px px-1' : 'px-3'"
                >
                  <template v-if="col.code === 'check'">
                    <input type="checkbox" class="w-5 h-5" />
                  </template>
                  <template v-else>{{ col.title }}</template>
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
                  v-for="col in guestTableColumns"
                  :key="col.code"
                  class="border-b border-r border-white py-2 whitespace-nowrap group hover:cursor-pointer"
                  :class="
                    col.code === 'check' ? 'w-px px-1 text-center' : 'px-3'
                  "
                >
                  <template v-if="col.code === 'check'">
                    <input
                      type="checkbox"
                      v-model="guest.check"
                      class="w-5 h-5 block mx-auto"
                    />
                  </template>
                  <template v-else>
                    {{ guest[col.code] }}
                  </template>
                </td>
              </tr>

              <!-- Tổng row -->
              <tr class="sticky bottom-0 z-10 bg-white font-bold">
                <td class="border-r border-gray-300 px-3 py-2"></td>
                <td class="border-r font-bold border-gray-300 px-3 py-2">
                  Tổng: {{ guestTableColumns.length }}
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
            <div class="border border-slate-200 rounded-md overflow-hidden">
              <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                <table
                  class="border-separate border-spacing-0 text-xs w-max min-w-full"
                >
                  <thead class="sticky top-0 z-10">
                    <tr
                      class="bg-slate-100"
                    >
                      <th
                        v-for="column in guestHistoryTableColumns"
                        class="px-3 py-2 text-left font-bold border-b border-r border-slate-200"
                      >
                        <td>
                          {{ column.title }}
                        </td>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="text-slate-400">
                      <td colspan="6" class="px-3 py-6 text-center">
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
