# Nhật Ký Tiến Độ Dự Án (Project Dev Log)

> File này ghi nhận tiến độ công việc, các tính năng/nghiệp vụ đã hoàn thành, trạng thái hiện tại và kế hoạch tiếp theo để tiếp nối công việc giữa các phiên làm việc.

---

## 📌 Hướng dẫn ghi log
- **Ngày ghi**: `YYYY-MM-DD`
- **Module / Nghiệp vụ**: Tên module (Housekeeping, Booking, Thu ngân, Cài đặt,...)
- **Nội dung hoàn thành**: Chi tiết logic, API, UI, DB migration/seeder đã xử lý + link file.
- **Trạng thái hiện tại**: Đang dừng ở bước nào, cần lưu ý gì.
- **Kế hoạch tiếp theo**: Việc cần làm tiếp khi mở lại dự án.

## [2026-08-28] - Hoàn Thiện & Nâng Cấp Module Tìm Kiếm Chung Chuẩn Thiết Kế Mẫu
### Module: Frontdesk / Reservation - Tìm Kiếm Chung (`/frontdesk?tab=search`, `/reservation?tab=search`)

- **Đã hoàn thành toàn diện theo mẫu [`UI/TÌM KIẾM CHUNG.html`](file:///d:/PMS/UI/TÌM KIẾM CHUNG.html)**:
  - **1. Bộ Lọc Tùy Biến Kéo Thả (Drag & Drop Customizable Filter Bar)**:
    - Hỗ trợ đầy đủ tương tác kéo thả HTML5 (`draggable`) và nút chuyển đổi nhanh (`⇥` chuyển vào nâng cao, `⇤` đưa ra tìm nhanh) cho toàn bộ 16 trường nghiệp vụ ([`GeneralSearchPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/GeneralSearchPage.vue)).
    - Cố định thứ tự trường chuẩn (`FIELD_ORDER`: Mã BK, Tình trạng lưu trú, Ref Code, Booking Name, Booking Status, Contact, Booker, Company, Market Segment, Source Code, Reg Date, User Sale,...).
    - Tự động lưu cấu hình vị trí các trường theo từng tài khoản người dùng vào `localStorage` (`pms_general_search_layout_${userId}`).
    - Nút **Bộ lọc nâng cao** hiển thị badge đếm số lượng trường động tương ứng trong bảng điều kiện nâng cao (ví dụ `10`, `12`...).
  - **2. Bộ Chọn Ngày & Toggle Thông Minh**:
    - Nút Toggle switch "Tìm theo ngày" (`use_date`), tự động đồng bộ ngày nghiệp vụ từ hệ thống (`/system-date`).
    - Hiển thị khoảng ngày trực quan kèm nút mở lịch `📅` nhanh.
  - **3. Autocomplete & Gợi Ý Mã Booking (Mã BK)**:
    - Dropdown gợi ý tức thì khi gõ từ khóa Mã BK, hiển thị Mã BK, Tên BK và Mã tham chiếu kèm nút xóa nhanh `×`.
  - **4. Dropdown Quản Lý & Kéo Thả Sắp Xếp Vị Trí Cột (Columns Reordering)**:
    - Chuyển đổi thành **Dropdown menu gắn ngay dưới nút "⚙ Cột hiển thị"** (chuẩn theo ảnh mẫu 1).
    - Tích hợp checkbox ẩn/hiện và hỗ trợ **kéo thả (Drag & Drop) hoặc bấm nút mũi tên `▲`/`▼`** để thay đổi thứ tự các cột trực tiếp.
    - Toàn bộ bảng dữ liệu bên dưới tự động re-render và hiển thị các cột theo đúng thứ tự tùy biến của người dùng, tự động lưu vào `localStorage` (`pms_general_search_columns_${userId}_${tab}`).
  - **5. Bảng Dữ Liệu & Sub-table Phòng Con Gọn Gàng (Compact Sub-table)**:
    - Tinh chỉnh sub-table chi tiết phòng con khi bấm mở rộng `+` trong Tab Đăng Ký thành **bảng gọn gàng, kích thước nhỏ gọn** (chuẩn theo ảnh mẫu 3), không bị tràn 100% chiều ngang.
    - Gom nhóm phòng theo: `Loại Phòng`, `#Phòng`, `#N.Lớn`, `#T.Em`, `Ngày Đến`, `Ngày Đi`, `Mã Giá Phòng`, `Giá Phòng`, `Tổng`.
    - Bổ sung dòng **Tổng cộng ở đáy sub-table** (Tổng số phòng, tổng người lớn, tổng trẻ em, tổng tiền).
    - Bổ sung đầy đủ tính năng **sắp xếp cột (Sorting `↕` / `↑` / `↓`) cho cột `Đêm` / `Số đêm` (`nights`) và `Ngày đi` (`departure_date`)** trên cả 3 tab: **Đăng Ký**, **Phòng**, **Khách** đồng bộ cùng Backend và Frontend.
    - Xử lý **tự động xuống dòng & ngắt chuỗi dài không khoảng trắng (word break / line wrap)** kèm mở rộng không gian hiển thị cho các cột Tên đăng ký (`booking_name`, 240px - 400px), Tên khách (`guest_name`), Loại phòng khởi tạo / thực tế (`room_class_cell`, 170px - 300px), Công ty (`company`), Ghi chú (`note`), Địa chỉ (`address`)... giúp bảng rộng rãi, dễ đọc và không bị tràn kéo dài.
    - **Nút chức năng Top bar & Dropdown Thao tác chuyên biệt theo từng Tab (Ảnh 1, 2, 3, 4)**:
      - Nút **"Nhân bản"**: Chỉ hiển thị ở **Tab Đăng Ký** khi có checkbox được chọn (`tab === 'booking' && selectedCount > 0`), mở modal nhân bản `CopyModal` hỗ trợ chọn ngày đến mới và nhân bản tức thì.
      - Nút **"Thao tác"**: Luôn hiển thị trên thanh công cụ Top Bar. Dropdown menu thiết kế màu trắng sạch sẽ (`#ffffff`, border `#cbd5e1`), icon màu xanh dịu (`#2563eb`), hover êm dịu, không bị chói mắt.
      - Nút **"Nhân bản"**: Luôn hiển thị ở Tab Đăng Ký, tự động đổi màu xám (disabled) khi chưa chọn hoặc chọn nhiều hơn 1 checkbox.
      - **Chức năng "Nhận phòng" (Tab Phòng)**: Cho phép tích chọn **nhiều phòng cùng lúc** (`selectedCount >= 1`), kể cả các phòng chưa gán số phòng (trạng thái Đặt phòng `DP` / `0`), xác nhận nhận phòng hàng loạt và phản hồi chi tiết kết quả.
      - **Modal xác nhận "No Show" (Chuẩn Hình 2)**: Khi bấm `No Show Một Ngày` hoặc `No Show Giai Đoạn`, hiển thị modal popup xác nhận màu xanh chuẩn với 3 tùy chọn tính phí:
        1. `Tính phí tất cả` (`all_charged`)
        2. `Tính phí tiền phòng` (`room_only`)
        3. `không tính phí` (`no_charge`)
        Cùng 2 nút `[Không]` và `[Có]` để thực hiện xử lý no-show đúng tùy chọn tính phí.
      - **Phân tách cơ chế Bộ lọc nhanh vs Bộ lọc nâng cao**:
        - **Bộ lọc nhanh (ở ngoài)**: Khi người dùng nhập/chọn (Mã BK, Tình trạng, ngày...), hệ thống tự động tìm kiếm **Realtime** ngay tức thì.
        - **Bộ lọc nâng cao (ở trong khung Điều kiện nâng cao)**: Người dùng nhập/chọn các trường bên trong khung nâng cao sẽ **không bị realtime nhảy dữ liệu**, dữ liệu được lưu vào bản nháp (`advDraft`). Chỉ khi bấm nút **"Áp dụng"** (hoặc Enter) thì các bộ lọc này mới được thực thi tìm kiếm. Nút **"Xóa lọc"** làm sạch bộ lọc nâng cao.
      - **Submenu "No show"**: Hiển thị dạng flyout mở sang **bên trái** (`right: 100%`) và mũi tên `◀`, không còn bị tràn/che khuất khỏi mép phải màn hình.
      - **Chức năng "Hóa Đơn"**: Bổ sung hỗ trợ đầy đủ các tham số query (`bookingCode`, `booking_code`, `booking_id`, `roomId`, `room_id`) trong [`CheckoutPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/CheckoutPage.vue) và [`GeneralSearchPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/GeneralSearchPage.vue), giúp khi click mở đúng chính xác booking và phòng được chọn trên màn hình Hóa đơn/Checkout.
      - **Cơ chế giới hạn thao tác theo số lượng checkbox được chọn**:
        - Khi **chưa chọn dòng nào** (`selectedCount === 0`): Các chức năng cần dòng được chọn sẽ hiển thị màu xám disabled trong menu; nút Nhân bản ở ngoài xám disabled; nút Đồ thất lạc luôn click được.
        - Khi tích chọn **nhiều hơn 1 dòng** (`selectedCount > 1`), các chức năng đơn lẻ gồm: **Đăng Ký**, **Hóa Đơn**, **Thông Tin Khách**, **Nhân bản** sẽ tự động **chuyển màu xám (disabled, không cho click)**, riêng **Nhận phòng** và **No Show** cho phép thao tác nhiều phòng cùng lúc.
        - Khi tích chọn **đúng 1 dòng** (`selectedCount === 1`), tất cả chức năng đều sáng lên và hoạt động:
          - `Đăng Ký`: Điều hướng trực tiếp đến đúng phiếu đăng ký / `booking_id` / mã booking đã chọn (ví dụ `GAL1`) trên giao diện tạo/sửa đăng ký.
          - `Hóa Đơn`: Mở trực tiếp màn hình hóa đơn / thanh toán đúng booking và phòng đã chọn.
          - `Thông Tin Khách`: Mở modal chi tiết thông tin khách của booking.
          - `Nhận phòng`: Thực hiện nhận phòng nhanh cho phòng được chọn.
          - `Nhân bản`: Mở modal sao chép booking đã chọn sang ngày đến mới.
      - **Sao lưu & Khôi phục Database Đa Chi Nhánh (Multi-Database Backup & Restore)**:
        - Nâng cấp [`DatabaseBackupController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/DatabaseBackupController.php) và [`routes/api.php`](file:///d:/PMS/backend/routes/api.php): Hỗ trợ toàn diện 3 cấp độ phạm vi:
          1. **`ALL` (Toàn Bộ Hệ Thống)**: Tự động gom xuất và nạp toàn bộ Database Hệ Thống Chính (`pms_system`) + TẤT CẢ các Database Chi Nhánh con trong 1 file `.sql` duy nhất.
          2. **`SYSTEM` (Database Hệ Thống Quản Trị)**: Xuất và khôi phục riêng Database `pms_system` chứa dữ liệu người dùng, vai trò, chi nhánh...
          3. **Từng Chi Nhánh Con (`HKT1`, `HKT2`...)**: Xuất và khôi phục riêng lẻ từng database nghiệp vụ của chi nhánh đó.
        - **Khôi phục an toàn (Sanitization)**: Tự động loại bỏ các lệnh `CREATE DATABASE` và `USE \`...\`;` khi nạp chi nhánh đơn lẻ, hoặc tự định tuyến nạp theo từng DB khi nạp file tổng hợp `ALL`.
      - **Sửa lỗi hiển thị Thông Báo Booking trên Server ([`BookingNotificationController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingNotificationController.php) & [`CreateRegistrationPage.vue`](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
        - Mở rộng điều kiện lọc API `active`: Trả về toàn bộ thông báo của booking hoặc các thông báo bao quát thời gian lưu trú `arrival_date` $\rightarrow$ `departure_date`, không còn bị chặn khi ngày hiện tại của server khác với ngày tạo thông báo.
        - Kích hoạt gọi `loadActiveBookingNotifications()` ngay sau khi `loadBookings()` tải xong hoặc khi mở booking theo `bookingCode`, đảm bảo thông báo luôn tự động hiển thị popup khi vào xem booking.
        - Cập nhật [`BookingNotificationsModal.vue`](file:///d:/PMS/frontend/src/pages/reservation/components/BookingNotificationsModal.vue) tự động điền sẵn ngày bắt đầu và kết thúc mặc định theo đúng ngày checkIn / checkOut của booking.
    - Tab Phòng ([`sp_041.sql`](file:///d:/PMS/store%20PMS/sp_041.sql)): Gom nhóm Master Booking banner màu xanh nhạt với tổng tiền dịch vụ & tiền thanh toán.
    - Tab Khách ([`sp_043.sql`](file:///d:/PMS/store%20PMS/sp_043.sql)): Đầy đủ 22 trường thông tin khách lưu trú người lớn và trẻ em.
    - Thanh phân trang hiển thị chuẩn PMS (dropdown 50 / 100 / 200 dòng/trang, danh sách nút trang số `1`, `2`, `3`..., nút Trước/Sau và Tổng kết quả).
- **Trạng thái hiện tại**: Hoàn thành 100% các cập nhật: Nhận phòng nhiều phòng hàng loạt, Modal xác nhận No Show theo Hình 2, phân tách Realtime bộ lọc ngoài và Áp dụng thủ công cho bộ lọc nâng cao, fix triệt để lỗi phòng chuyển (status 100) chặn sang ngày, hoàn thiện module Sao lưu & Khôi phục Database Đa Chi Nhánh hỗ trợ ALL, SYSTEM và từng chi nhánh, sửa lỗi popup thông báo booking hiển thị tức thì trên cả local và server, build Vite production thành công không lỗi.
- **Kế hoạch tiếp theo**: Tiếp tục hỗ trợ kiểm thử và hoàn thiện các nghiệp vụ tiếp theo.

---

## [2026-08-24] - [Giai Đoạn 1] Xây Dựng Khung Nền Tảng Phân Quyền & Giao Diện Cấu Hình Nhân Viên
### Module: System / Quản Trị Nhân Viên & Phân Quyền (Bước 1)

- **Công việc đã làm ở Bước 1**:
  - **Backend API Routes**:
    - Khởi tạo các API endpoints phục vụ cấu hình phân quyền ([`RoleController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/RoleController.php), [`UserPermissionController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/UserPermissionController.php)).
    - Đăng ký middleware kiểm tra quyền cơ bản ([`RequirePermission.php`](file:///d:/PMS/backend/app/Http/Middleware/RequirePermission.php)).
  - **Frontend UI Setup**:
    - Dựng giao diện tab **Phân quyền đặc thù** trong [`EmployeeTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue) (bảng Chi nhánh, toggle Chi nhánh chính, danh sách kho).
    - Tạo composable [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js) và trang 403 [`ForbiddenPage.vue`](file:///d:/PMS/frontend/src/pages/ForbiddenPage.vue).
  - **Trạng thái**:
    - Mới chỉ là **bước đầu tiên (khung nền tảng kỹ thuật và UI mẫu)**, **chưa gán phân quyền thực tế** cho nhân viên nào.
    - Tất cả tài khoản hiện tại vẫn đang truy cập 100% tất cả các chức năng và chi nhánh bình thường.
    - Tạm dừng phần phân quyền tại đây để chuyển sang làm các nghiệp vụ khác.

---

## [2026-08-21] - Triển Khai Hệ Thống Phân Quyền Toàn Diện (RBAC & Multi-Branch Permissions)
### Module: RBAC / Authentication, Authorization & Phân Quyền Theo Chi Nhánh

- **Đã hoàn thành**:
  - **Backend Authorization & Permission Middleware**:
    - Tạo mới middleware [`RequirePermission.php`](file:///d:/PMS/backend/app/Http/Middleware/RequirePermission.php):
      - Tự động kiểm tra quyền user theo chi nhánh cụ thể từ request header/attributes (`_branch_id`).
      - Cho phép Super Admin bypass tự động.
      - Hỗ trợ nhiều permission với logic OR (`->middleware('permission:fo.booking.create,fo.booking.edit')`).
    - Đăng ký alias `permission` trong [`bootstrap/app.php`](file:///d:/PMS/backend/bootstrap/app.php).
    - Cập nhật [`AuthController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php) hàm `me()`: Trả về đầy đủ `permissions`, `branches`, `active_branch`, `roles` theo từng chi nhánh khi chuyển đổi hoặc refresh trang.
    - Bảo vệ toàn diện các API routes nhạy cảm trong [`api.php`](file:///d:/PMS/backend/routes/api.php) (Bookings, BookingRooms, Check-in, Check-out, Payments, HK Assignment, System Users, System Branches).
  - **Frontend Permission System & Composables**:
    - Nâng cấp [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js): Cung cấp `can()`, `canAny()`, `canAll()`, `isSuperAdmin`, `isAdmin`.
    - Nâng cấp [`auth-store.js`](file:///d:/PMS/frontend/src/stores/auth-store.js):
      - `initialize()`: Lấy và lưu trữ permissions/roles/branches tương ứng theo chi nhánh đang active.
      - `switchBranch()`: Tự động gọi API ngầm refresh lại quyền và roles tương ứng với chi nhánh vừa chọn.
    - **Frontend Route Guard**:
      - Cập nhật [`router/index.js`](file:///d:/PMS/frontend/src/router/index.js): Bổ sung `meta.permission` cho từng trang (`/reservation`, `/frontdesk`, `/housekeeping`, `/reports`, `/fnb/*`, `/system`). Tự động chuyển hướng về `/forbidden` khi không đủ quyền.
      - Tạo mới trang 403 cao cấp [`ForbiddenPage.vue`](file:///d:/PMS/frontend/src/pages/ForbiddenPage.vue).
    - **Topbar & Fine-grained UI Permission Guards**:
      - Cập nhật [`MainLayout.vue`](file:///d:/PMS/frontend/src/layouts/MainLayout.vue): Dropdown chi nhánh trên Header chỉ hiển thị các chi nhánh mà tài khoản được phân quyền trong `user_branches`.
      - Cập nhật [`HomePage.vue`](file:///d:/PMS/frontend/src/pages/HomePage.vue): Các thẻ ứng dụng (PMS, F&B, SYSTEM) tự động ẩn/hiện theo quyền của nhân viên.
      - Gắn `v-if="can(...)"` vào các nút hành động cốt lõi: Nhận phòng (`fo.checkin`), Thanh toán & Xóa thanh toán (`fo.payment.create`), Trả phòng (`fo.checkout`), Thanh toán FnB (`fb.payment`).

---

## [2026-08-21] - Tự Động Tạo Tenant Database Khi Thêm Chi Nhánh Mới (Auto Multi-Tenant Provisioning)
### Module: System / Multi-Database & Quản Lý Chi Nhánh

- **Đã hoàn thành**:
  - **Auto Tenant Database Provisioning Engine**:
    - Tạo mới [`TenantDatabaseService.php`](file:///d:/PMS/backend/app/Services/TenantDatabaseService.php):
      - Tự động thực thi SQL tạo Database MySQL `CREATE DATABASE IF NOT EXISTS pms_{code}`.
      - Tự động đăng ký Dynamic Connection vào Runtime Configuration của Laravel (`mysql_{code}`).
      - Tự động chạy toàn bộ migrations khởi tạo schema bảng cho chi nhánh mới.
      - Tự động seed dữ liệu mẫu vận hành chuẩn ban đầu (`DatabaseSeeder`).
      - Tối ưu tải dữ liệu Cơ Cấu Tổ Chức ([`OrgStructureTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)):
        - Chuyển `Promise.all` sang `Promise.allSettled` giúp giao diện không bị treo/trắng khi có request chậm hoặc timeout.
      - Nâng cấp tính năng Xóa Chi Nhánh ([`BranchManageTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/BranchManageTab.vue) & [`SystemBranchController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/SystemBranchController.php)):
        - Bổ sung Popup modal xác nhận phương thức xóa:
          1. **Chỉ xóa thông tin chi nhánh**: Xóa khỏi bảng quản trị, giữ lại MySQL Database để lưu trữ dữ liệu cũ.
          2. **Xóa chi nhánh & Xóa toàn bộ Database**: Tự động thực thi `DROP DATABASE` xóa sạch cơ sở dữ liệu chi nhánh trên MySQL server.
      - Nâng cấp lệnh `php artisan db:reset-all`:
        - Tự động quét toàn bộ cơ sở dữ liệu `pms_*` có trên máy chủ MySQL (`SHOW DATABASES LIKE 'pms_%'`) và bảng `system_branches` thay vì chỉ reset cứng 5 DB cũ.
        - Đăng ký kết nối động (`Dynamic Connection`) cho mọi database chi nhánh phát hiện được (ví dụ `pms_dai_luc`, `pms_hkt5`, `pms_gkt6`...) để thực hiện `migrate:fresh` và `db:seed`.
        - Bổ sung tùy chọn `--drop-extra` để dọn dẹp các database thử nghiệm rác không có trong danh sách chi nhánh quản lý.
  - **Đồng bộ Ngày Hệ Thống PMS (System Date)**:
    - [`BreakfastPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue): Sửa logic lấy ngày từ `res.data.data.system_date`, chuẩn hóa lấy đúng ngày nghiệp vụ khách sạn (09/08/2026).
    - [`ActivityLogTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/ActivityLogTab.vue): Đồng bộ ngày nghiệp vụ từ `/system-date` và mặc định xem "Tất cả".
  - **Dynamic Connection Switching**:
    - Cập nhật [`SwitchBranchDatabase.php`](file:///d:/PMS/backend/app/Http/Middleware/SwitchBranchDatabase.php): Hỗ trợ phân giải và thiết lập kết nối động theo mã chi nhánh bất kỳ mà **không cần dev phải khai báo tĩnh trong `config/database.php` hay `.env`**.
  - **System Branch Management Controller**:
    - Cập nhật [`SystemBranchController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/SystemBranchController.php): Tự động gọi `TenantDatabaseService::provisionBranch` khi tạo chi nhánh mới qua `store()`, thêm endpoint `POST /api/system-branches/{id}/provision` để chủ động tái khởi tạo/migrate lại database chi nhánh khi cần.
    - Cập nhật [`SystemBranch.php`](file:///d:/PMS/backend/app/Models/SystemBranch.php) và [`SystemBranchResource.php`](file:///d:/PMS/backend/app/Http/Resources/SystemBranchResource.php) trả về `db_connection`, `db_name`, `organization_type`.
  - **Tài liệu hướng dẫn**:
    - Cập nhật [`DATABASE_GUIDE.md`](file:///d:/PMS/DATABASE_GUIDE.md) bổ sung mục 5 hướng dẫn cơ chế Multi-Tenant Auto Provisioning.

- **🟡 Kế hoạch các giai đoạn tiếp theo (Next Phases)**:
  - **Phase 3: Route Guard Frontend (Bảo vệ đường dẫn)**: Kiểm tra quyền trong `router/index.js`.
  - **Phase 4: Fine-grained Permission UI (Ẩn/Hiện nút bấm theo quyền)**: Gắn `v-if="can('...')"` vào các nút nghiệp vụ.
  - **Phase 5: Lọc danh sách Chi nhánh Topbar theo Nhân viên**: Dropdown chọn chi nhánh trên Topbar chỉ hiển thị các chi nhánh user được cấp phép.

---

## [2026-08-20] - Multi-Database, Dynamic Org Structure & Hệ Thống Phân Quyền (RBAC)
### Module: System / Cơ Cấu Tổ Chức, Ứng Dụng & Phân Quyền

- **Đã hoàn thành**:
  - **Multi-Database Setup & Dynamic Tenant Switching**:
    - Thiết lập hệ thống 5 databases: `pms_system` (quản trị tập trung, auth, users, roles, permissions), `pms_hkt1` (Nha Trang), `pms_hkt2` (TP.HCM), `pms_hkt3` (Đà Nẵng), `pms_hkt4` (Hà Nội).
    - Cấu hình kết nối trong [`config/database.php`](file:///d:/PMS/backend/config/database.php) và [`.env`](file:///d:/PMS/backend/.env).
    - Thêm Middleware [`SwitchBranchDatabase.php`](file:///d:/PMS/backend/app/Http/Middleware/SwitchBranchDatabase.php) tự động chuyển connection DB theo `X-Branch-Code` / `X-Branch-Id` trên từng request nghiệp vụ.
    - Cố định [`PersonalAccessToken.php`](file:///d:/PMS/backend/app/Models/PersonalAccessToken.php), [`User.php`](file:///d:/PMS/backend/app/Models/User.php), [`Role.php`](file:///d:/PMS/backend/app/Models/Role.php), [`Permission.php`](file:///d:/PMS/backend/app/Models/Permission.php), [`UserBranch.php`](file:///d:/PMS/backend/app/Models/UserBranch.php), [`UserRole.php`](file:///d:/PMS/backend/app/Models/UserRole.php), [`SystemBranch.php`](file:///d:/PMS/backend/app/Models/SystemBranch.php), [`UserSetting.php`](file:///d:/PMS/backend/app/Models/UserSetting.php) trên kết nối `mysql_system` để token hợp lệ xuyên suốt mọi chi nhánh khi chuyển đổi.
    - Chuyển `pms_token` và trạng thái xác thực từ `sessionStorage` sang `localStorage` để duy trì phiên đăng nhập khi mở tab mới trong cùng trình duyệt.
    - Cập nhật [`http.js`](file:///d:/PMS/frontend/src/services/http.js) và [`MainLayout.vue`](file:///d:/PMS/frontend/src/layouts/MainLayout.vue) tự động truyền mã chi nhánh đã chọn lên Backend.
    - Thêm Artisan Command [`ResetMultiDbCommand.php`](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php) (`php artisan db:reset-all`) hỗ trợ reset nhanh toàn bộ hoặc từng DB riêng lẻ (`--branch=system`, `--branch=hkt1`, `--seed-all`).
    - Tạo tài liệu hướng dẫn quản trị database: [`DATABASE_GUIDE.md`](file:///d:/PMS/DATABASE_GUIDE.md).
  - **Database Migration, Seeder & Models**:
    - Migration [`2026_08_19_210000_create_roles_and_permissions_tables.php`](file:///d:/PMS/backend/database/migrations/2026_08_19_210000_create_roles_and_permissions_tables.php): `roles`, `permissions`, `role_permissions`, `user_branches`, `user_roles`, `primary_branch_id` trên `users`.
    - Migration [`2026_08_20_150000_update_department_code_length.php`](file:///d:/PMS/backend/database/migrations/2026_08_20_150000_update_department_code_length.php): Tăng độ dài `departments.code` lên 10 ký tự.
    - Models: [`Role.php`](file:///d:/PMS/backend/app/Models/Role.php), [`Permission.php`](file:///d:/PMS/backend/app/Models/Permission.php), [`UserBranch.php`](file:///d:/PMS/backend/app/Models/UserBranch.php), [`UserRole.php`](file:///d:/PMS/backend/app/Models/UserRole.php), [`Module.php`](file:///d:/PMS/backend/app/Models/Module.php), [`Department.php`](file:///d:/PMS/backend/app/Models/Department.php).
    - Helper methods trên [`User.php`](file:///d:/PMS/backend/app/Models/User.php): `allPermissions()`, `hasPermission()`, `hasBranchAccess()`, `isSuperAdmin()`.
    - Seeder [`RolePermissionSeeder.php`](file:///d:/PMS/backend/database/seeders/RolePermissionSeeder.php): 9 vai trò và 39 permissions.
    - Seeder [`ModuleSeeder.php`](file:///d:/PMS/backend/database/seeders/ModuleSeeder.php): Chuẩn hóa 3 ứng dụng cốt lõi `PROVISTA PMS`, `PROVISTA F&B`, `PROVISTA SYSTEM`.
    - Seeder [`DepartmentSeeder.php`](file:///d:/PMS/backend/database/seeders/DepartmentSeeder.php): Chuẩn hóa 4 bộ phận thực tế `BỘ PHẬN LỄ TÂN (FO)`, `BỘ PHẬN BUỒNG PHÒNG (HK)`, `QUẢN TRỊ HỆ THỐNG (SYS)`, `BỘ PHẬN F&B (FB)`.
  - **Backend Controllers & API Routes**:
    - [`RoleController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/RoleController.php): CRUD vai trò, lấy permissions, sync permissions.
    - [`UserPermissionController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/UserPermissionController.php): Lấy permissions user, sync chi nhánh được gán và sync vai trò theo chi nhánh.
    - [`DepartmentController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/DepartmentController.php): Lấy danh sách phòng ban, tạo phòng ban mới.
    - Route `GET /api/modules`: Trả về 3 ứng dụng chính đang hoạt động từ DB.
  - **Frontend UI & State**:
    - [`auth-store.js`](file:///d:/PMS/frontend/src/stores/auth-store.js): Quản trị permissions, branches, activeBranch, roles + getters `hasPermission`, `canAny`, `isSuperAdmin`, `isAdmin`, action `switchBranch`.
    - Composable [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js): Cung cấp helper `can(code)`, `canAny(codes)` cho Vue components.
    - [`EmployeeTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue): Hoàn thiện tab "Phân Quyền Đặc Thù" — checkbox gán chi nhánh được phép truy cập, chọn primary branch, dropdown gán vai trò tương ứng cho từng chi nhánh, hiển thị tổng hợp danh sách quyền thực tế.
    - [`OrgStructureTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue): Tải 100% dữ liệu động từ Database (bảng `departments`, `modules`, `roles`, `users`), hiển thị cây thư mục Cơ cấu tổ chức, danh sách 3 Ứng dụng Provista và danh sách Nhân sự theo bộ phận.
    - [`RoleManageTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/RoleManageTab.vue): Giao diện Quản lý vai trò & Ma trận checkbox phân quyền chi tiết theo từng module.
    - [`SystemPage.vue`](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue): Tích hợp 2 tab "Cơ cấu tổ chức" và "Vai trò & Phân quyền".

- **🟡 Kế hoạch các giai đoạn tiếp theo (Next Phases)**:
  - **Phase 3: Route Guard Frontend (Bảo vệ đường dẫn)**:
    - Bổ sung logic kiểm tra quyền trong [`frontend/src/router/index.js`](file:///d:/PMS/frontend/src/router/index.js) (ví dụ: Nhân viên Lễ tân không có quyền vào `/system` hoặc `/housekeeping`, tự động redirect về trang được phép hoặc thông báo 403).
  - **Phase 4: Fine-grained Permission UI (Ẩn/Hiện nút bấm theo quyền)**:
    - Gắn `v-if="can('...')"` vào các nút hành động nghiệp vụ quan trọng ở Frontdesk (Tạo đặt phòng, Check-in, Check-out, Thu tiền, Chuyển phòng, Hủy phòng, In phiếu ăn sáng...), Housekeeping và F&B.
  - **Phase 5: Lọc danh sách Chi nhánh Topbar theo Nhân viên**:
    - Dropdown chọn chi nhánh trên Topbar chỉ hiển thị các chi nhánh mà user đang đăng nhập được gán trong `user_branches` (tài khoản Super Admin được thấy và chuyển sang tất cả các chi nhánh).




### Module: Housekeeping / Quản lý tồn kho & Kiểm kê định kỳ
- **Đã hoàn thành**:
  - Khởi tạo file nhật ký tiến độ [.agents/DAILY_LOG.md](file:///d:/PMS/.agents/DAILY_LOG.md).
  - Cập nhật quy tắc tự động ghi chép và đọc lại tiến độ vào [.agents/AGENTS.md](file:///d:/PMS/.agents/AGENTS.md).
  - Sửa API [OutletController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OutletController.php) hàm `listHK()` truy vấn chuẩn từ bảng `housekeeping_outlets`.
  - Sửa frontend [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) modal Thêm/Sửa kho bind đúng `ol.code` và `ol.name`.
  - Nâng cấp API `getBill()` trong [InventoryLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryLogController.php) để map linh hoạt mã Outlet, lọc bỏ các hóa đơn buồng phòng đã hủy (`BillEdit = 1`, `Status = 3, 4`), chỉ tính các món hợp lệ (`Deleted = 0`), và tự động bổ sung sản phẩm bán vào phiếu kiểm kê nếu chưa có.
  - Hỗ trợ chọn **nhiều Outlet cho 1 kho** (Multi-select checkbox) ở frontend [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) và backend [WarehouseController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/WarehouseController.php) + [Warehouse.php](file:///d:/PMS/backend/app/Models/Warehouse.php), cho phép Get Bill gom hóa đơn từ tất cả các outlet đã gán.
  - Thêm nút **📋 Bill trực tiếp trên từng cột ngày** và nút **`📋 Lấy Bill Tháng` trên thanh công cụ** trong [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) cùng API [InventoryLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryLogController.php), cho phép 1 cú click tự động quét và đồng bộ hóa đơn xuất kho cho toàn bộ tất cả các ngày trong tháng.
  - Bổ sung tính năng **kết chuyển Tồn cuối tháng trước sang tháng mới** khi tạo phiếu kiểm kê định kỳ:
    - Trong modal Kiểm kê định kỳ, khi chọn tháng mới (ví dụ tháng 8) và bấm nút **`📊 Thống kê`**, hệ thống gọi API `POST /api/inventory/checks/sync-previous-month` trong [InventoryCheckController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryCheckController.php) để tự động tính Tồn cuối của từng sản phẩm ở tháng trước (Tồn ĐK + Nhập - Xuất - Chuyển) và điền vào 2 cột **Tồn đầu kỳ** và **Số lượng thực tế** của tháng mới.
    - Khi nhân viên sửa lại "Số lượng thực tế", hệ thống tự động tính **Số chênh lệch** = Thực tế - Tồn đầu kỳ, đồng thời ngoài bảng chính ưu tiên lấy **Số lượng thực tế sau kiểm kê** làm mốc Tồn đầu kỳ để tính toán Tồn cuối và phát sinh trong tháng.
    - Đồng bộ thứ tự sắp xếp sản phẩm trong modal Kiểm kê định kỳ luôn theo **Tên A-Z** tương đồng 1:1 với bảng chính bên ngoài, giúp đối chiếu dễ dàng.
  - Thêm dropdown menu khi hover vào mục **GIAO PHÒNG** trên thanh điều hướng chính trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue), bao gồm 6 mục:
    1. `SƠ ĐỒ PHÒNG`
    2. `NHẬN PHÒNG NHANH`
    3. `TẠO ĐĂNG KÝ`
    4. `ĐẶT CỌC`
    5. `TẠO THẺ KHÓA PHÒNG`
    6. `IN PHIẾU ĂN SÁNG`
- **Trạng thái hiện tại**: Hoàn thiện toàn bộ luồng Kiểm kê tồn kho, Get Bill, kết chuyển tồn cuối và menu dropdown Giao phòng ở phân hệ Lễ tân.

### Module: Frontdesk / In Phiếu Ăn Sáng (Breakfast Coupon - sp_035)
- **Đã hoàn thành**:
  - Xây dựng API `GET /api/breakfast/list` trong [BreakfastController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BreakfastController.php) và [api.php](file:///d:/PMS/backend/routes/api.php) mô phỏng logic truy vấn MySQL tương đương Stored Procedure `sp_035`:
    - Hỗ trợ lọc theo **Ngày ăn sáng** (`breakfast` - từ sáng ngày hôm sau ngày đến đến sáng ngày đi) hoặc **Ngày đến** (`arrival`).
    - Lọc chỉ lấy các phòng có ăn sáng (`is_breakfast = 1` hoặc có trẻ em ăn sáng).
    - Tính toán số lượng người lớn, trẻ em ăn sáng và danh sách toàn bộ các ngày ăn sáng hợp lệ trong chu kỳ lưu trú.
  - Tạo service [breakfast-service.js](file:///d:/PMS/frontend/src/services/breakfast-service.js) kết nối backend.
  - Xây dựng màn hình [BreakfastPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue):
    - Thanh bộ lọc khoảng ngày (Date Range Picker), dropdown chuyển đổi Ngày đến / Ngày ăn sáng, nút `Xem`, nút `In phiếu ăn sáng`.
    - Bảng dữ liệu thiết kế chuẩn cây phân cấp 3 tầng (**Tầng 1: Ngày** -> **Tầng 2: Booking** -> **Tầng 3: Các phòng/phiếu ăn sáng**) với nút thu gọn/mở rộng `+`/`-` màu xanh cyan đúng theo ảnh mẫu.
    - Chuyển đổi dropdown chọn ngày thành **Segmented Toggle Buttons** (`Ngày ăn sáng` / `Ngày đến`) to rõ, trực quan.
    - Cố định thanh **Tổng kết** (Tổng phòng, Tổng người lớn, Tổng trẻ em) luôn nằm ở đáy màn hình (Fixed bottom bar), không bị trôi nổi ở giữa bảng.
    - Nâng cấp nút **In phiếu ăn sáng** thành Modal tùy chọn 2 trong 2 (`1. In tất cả (In All)` hoặc `2. In theo giai đoạn ngày`), loại bỏ dropdown cũ.
    - Tích hợp bộ chọn ngày **Clickable Calendar Picker** (hiển thị `DD/MM/YYYY` kèm icon lịch 📅 $\rightarrow$ click vào là mở bảng chọn ngày (popup calendar) của trình duyệt ngay lập tức, không cần gõ phím).
    - Bộ lọc tìm kiếm nhanh trực tiếp trên từng cột (Mã đăng ký, Tên đăng ký, Phòng, Ngày đến, Ngày đi, Tên khách).
    - Chuẩn hóa định dạng chuỗi ngày `YYYY-MM-DD` tại [BreakfastController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BreakfastController.php) để loại bỏ hiện tượng lệch múi giờ (UTC ISO-8601 offset làm lùi 1 ngày) giữa bảng danh sách và phiếu in.
    - Sửa logic so khớp ngày ăn sáng của trẻ em (`booking_child_breakfast_details` / `booking_children`), nhận diện chính xác 100% số lượng suất ăn sáng của trẻ em (cả phụ thu và miễn phí) cho từng phòng và sinh đúng số lượng phiếu in tương ứng.
    - Chuẩn hóa phân tách trạng thái phòng theo đúng logic store [sp_035.sql](file:///d:/PMS/sp_035.sql):
      + Khi lọc **Ngày đến (`arrival`)**: Quét theo ngày đến `pt.ArrivalDate` trên bảng kế hoạch phòng, lấy mọi trạng thái (Đăng ký `0`, Đang ở `1`, Chuyển phòng `100`) để in phiếu trước đón khách.
    - Sửa lỗi upload logo công ty tại [SystemPage.vue](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue):
      + Tự động bỏ header `Content-Type: application/json` khi gửi `FormData` trong [http.js](file:///d:/PMS/frontend/src/services/http.js) để trình duyệt tạo `boundary` multipart chính xác.
      + Trả về đường dẫn ảnh tương đối `/uploads/business/...` trong [InfoBusinessResource.php](file:///d:/PMS/backend/app/Http/Resources/InfoBusinessResource.php) giúp tránh lỗi `https://localhost` và tải ảnh mượt mà qua proxy Vite.
    - Cập nhật phiếu in ăn sáng [BreakfastCouponPreview.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastCouponPreview.vue) và [BreakfastPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue) ưu tiên lấy Logo và Tên công ty từ cấu hình **Thông tin công ty (`/info-business`)** trong hệ thống (sửa lỗi thứ tự spread object bị ghi đè dữ liệu rỗng), kèm cơ chế fallback tự động hiển thị tên nếu chưa có ảnh.
  - Xây dựng modal [BreakfastPrintModal.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastPrintModal.vue) với giao diện thẻ chọn hình thức in và picker chọn ngày trực quan.
    - Nâng cấp [BreakfastCouponPreview.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastCouponPreview.vue) thành công cụ **Tùy biến Mẫu in Báo cáo / Phiếu Ăn Sáng linh hoạt**:
      + **Bố cục & Kích thước**: Tự do tùy chọn 1 Cột / 2 Cột / 3 Cột ngang, thanh trượt chỉnh chiều cao phiếu (180px - 320px), 4 cỡ chữ (Nhỏ, Chuẩn, Lớn, Rất lớn), 3 cỡ số phòng (Vừa, To nổi bật, Siêu to), kiểu viền (Nét liền, nét đứt, nét đôi) và độ dày viền.
      + **Tiêu đề & Nội dung trường**: Cho phép ẩn/hiện Logo, sửa Tên hiển thị đơn vị, sửa tiêu đề chính/phụ (VD: `PHIẾU ĂN SÁNG / BREAKFAST COUPON`), bật/tắt các trường Mã Booking, Tên khách, Số phòng, Ngày ăn sáng.
      + **Ghi chú chân trang**: Cho phép sửa nội dung dặn dò/điều khoản nhiều dòng hoặc tắt ghi chú.
      + **Lưu cấu hình tự động**: Tự động lưu cấu hình tùy chỉnh vào `localStorage` cho từng máy/khách sạn và hỗ trợ nút "Khôi phục gốc" khi cần.
    - Đảm bảo 100% dữ liệu (Tên khách sạn/công ty, Logo, Mã Booking, Số phòng, Tên khách, Ngày tháng, Số lượng khách) đều đọc động từ Database (`info_businesses`, `hotel_settings`, `bookings`, `booking_rooms`, `guests`), loại bỏ hoàn toàn các chuỗi text hardcode.
  - Liên kết điều hướng từ menu **GIAO PHÒNG -> IN PHIẾU ĂN SÁNG** trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue) và nhúng vào [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue).
- **Trạng thái hiện tại**: Hoàn thành toàn bộ chức năng Quản lý & In Phiếu Ăn Sáng, dữ liệu động 100% từ Database.

---

## [2026-08-18] - Hoàn thiện Module Danh Sách Công Việc (/frontdesk?tab=shift-work) kết nối Database thực tế
### Module: Frontdesk / Danh Sách Công Việc (Shift Work)
- **Đã hoàn thành**:
  - Xây dựng Backend Controller [ShiftWorkController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/ShiftWorkController.php) và khai báo các API endpoint tại [api.php](file:///d:/PMS/backend/routes/api.php):
    1. `GET /api/shift-work/arrivals`: Truy vấn danh sách phòng đến chuẩn theo Stored Procedure `sp_143` & `sp_147`, lọc theo ngày đến và trạng thái (Chưa nhận phòng / Đã nhận phòng / Tất cả). Nhóm theo Booking sắp xếp tăng dần, tính tiền cọc (`payments` where `pack2 = 'DPR'`), tổng tiền booking, tổng tiền phòng, và danh sách yêu cầu đặc biệt.
    2. `GET /api/shift-work/departures`: Truy vấn danh sách phòng đi chuẩn theo `sp_143`, lọc theo ngày đi và trạng thái (Chưa trả / Đã trả / Tất cả). Tính toán chính xác `Tổng dịch vụ` (`booking_room_services`) và `Tổng thanh toán` (`payments`) ở cả cấp độ từng phòng và dòng tiêu đề Master Booking.
    3. `GET /api/shift-work/pending`: Truy vấn đăng ký chờ xác nhận chuẩn theo `sp_141`, lọc trạng thái Non-guaranteed (`20` hoặc `bk_definite != 1`) trong khoảng ngày (mặc định: Ngày hệ thống $\rightarrow$ +3 ngày). Thống kê chi tiết số lượng từng loại phòng (VD: `SUPD (5), SUPT (1)`), tiền cọc và thông tin liên hệ.
    4. `PUT /api/shift-work/pending/{bookingId}/note`: Cập nhật ghi chú xác nhận của Sale trực tiếp vào trường `note` của Booking trong cơ sở dữ liệu.
    5. `GET /api/shift-work/shuttle`: Truy vấn thông tin đón tiễn sân bay (loại đón/tiễn, chuyến bay, giờ bay/giờ hẹn, số lượng khách, xe/tài xế, ghi chú) từ thông tin booking thực tế.
    6. `GET /api/shift-work/noshow`: Truy vấn danh sách phòng không đến chuẩn theo `sp_054`, bỏ cột Ngày xác nhận, hiển thị tiền phạt/tổng tiền, lý do, người tạo và ca.
    7. `GET /api/shift-work/birthdays`: Truy vấn danh sách khách lưu trú có ngày sinh nhật trong khoảng ngày xem (mặc định: Ngày hệ thống $\rightarrow$ +3 ngày) chuẩn theo `sp_111`.
  - Tạo service [shift-work-service.js](file:///d:/PMS/frontend/src/services/shift-work-service.js) kết nối toàn bộ 7 API endpoint của module.
  - Tái cấu trúc và hoàn thiện giao diện [ShiftWorkPage.vue](file:///d:/PMS/frontend/src/pages/reservation/ShiftWorkPage.vue):
    - Giữ nguyên thiết kế UI và hệ thống màu sắc/bố cục chuẩn theo yêu cầu.
    - Thay thế 100% dữ liệu mock/tĩnh bằng dữ liệu thật đọc từ Database qua API.
    - Tích hợp thanh toolbar: Bộ chọn ngày Calendar Popup (hiển thị `DD/MM/YYYY`), nút chọn nhanh `Hôm nay` & `Ngày mai`, dropdown lọc trạng thái cho từng tab, và ô tìm kiếm nhanh đa trường (mã booking, tên khách, số phòng, tên công ty).
    - Hỗ trợ lưu ghi chú Sale trực tiếp trên Tab Chờ xác nhận với nút Chỉnh sửa / Lưu.
    - Thanh tổng kết (Sticky footer stats) ở đáy màn hình tự động tính toán tổng số đăng ký, tổng số phòng, tổng đêm vắng, tổng lượt đưa đón,... theo dữ liệu thực tế.
    - Tích hợp loading spinner overlay và empty states khi không có bản ghi.
  - Build Vite kiểm thử thành công 100% (`npm run build` không phát sinh lỗi).
- **Trạng thái hiện tại**: Toàn bộ 6 tab thuộc module Danh Sách Công Việc đã hoạt động hoàn toàn với dữ liệu thực tế từ Database.
  - Sửa lỗi truy vấn quan hệ `roomRateCode` trên model `BookingRoom` chuyển sang cột trực tiếp `rate_code`.
  - Đồng bộ chuẩn ngày nghiệp vụ PMS (`SystemDateRoll`) cho cả Backend Controller và Frontend `ShiftWorkPage.vue`, đảm bảo hiển thị đúng booking ngày hệ thống hiện tại (`09/08/2026`).
  - Nâng cấp giao diện bảng dữ liệu (Table Layout) theo đúng 100% thiết kế từ ảnh mẫu của khách hàng:
    + Tiêu đề bảng nền xám nhẹ `#f1f5f9`, font chữ đậm rõ ràng kèm icon sắp xếp `⇅`.
    + Dòng phân nhóm Booking dạng banner dải liền (`colspan`) nền xanh nhạt `#edf5fc`, hiển thị nút thu gọn/mở rộng `+`/`-`, chuỗi thông tin Booking đầy đủ (mã, tên, ngày đến~ngày đi, số đêm, số phòng, ghi chú) ở bên trái và số liệu tài chính (`Đặt cọc : ...`, `Tổng tiền : ...` / `Tiền dịch vụ : ...`, `Tiền đã thanh toán : ...`) căn gọn gàng về bên phải.
    + Các dòng phòng con hiển thị chi tiết, sạch sẽ với đường viền mỏng và hiệu ứng hover nhẹ nhàng.
  - Sửa mapping trường Loại phòng (`roomType`) từ `room_classes.name` (thay vì `room_class_name`), giúp hiển thị chính xác tên loại phòng (`Superior Double`, `Superior Twin`,...) trên tất cả các tab.
  - Tối ưu hóa ô tìm kiếm nhanh: Tự động tìm kiếm tức thì khi gõ phím (Debounce 250ms), loại bỏ nút bấm rườm rà và tích hợp nút icon `x` bên trong ô nhập liệu để reset từ khóa nhanh chóng.
  - Gỡ bỏ badge số `2` màu đỏ trên nút menu `D.S Công Việc` trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue).
- **Tính năng Giao phòng nhanh / Nhận phòng nhanh trực tiếp từ Sơ đồ phòng (`RoomMapPage.vue`)**:
  - Tạo mới component [QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue) theo đúng 100% bố cục và màu sắc thiết kế mẫu:
    + Cột Thông tin: Ngày đến, Ngày đi, Loại phòng, Dạng phòng, Số phòng, Số đêm.
    + Thẻ Khách hàng: Người lớn, Trẻ em, công tắc **Ở theo giờ** (tự động chuyển `Ngày đi = Ngày đến`, `Số đêm = 0` khi bật và hoàn lại ngày tiếp theo khi tắt).
    + Cột Giá: Giá phòng, Mã giá phòng, Tăng/Giảm giá (% / VNĐ), Thêm giường, Giá thêm giường, nút mở modal Yêu cầu đặc biệt.
  - Kết nối sự kiện click chuột trái vào bất kỳ phòng trống nào trên sơ đồ hoặc chọn **Giao phòng nhanh** từ menu ngữ cảnh để mở modal điền sẵn thông tin phòng.
  - Tích hợp gọi API tạo Booking (`POST /api/bookings`) và tự động Check-in phòng ngay lập tức khi lưu, làm mới dữ liệu sơ đồ phòng realtime.
  - Sửa lỗi đóng/thoát modal **Yêu cầu đặc biệt**: Bổ sung emit `close` và hỗ trợ lưu/trả về danh sách yêu cầu đã chọn khi tạo mới phòng chưa có ID đặt phòng.
  - **Đồng bộ chuẩn hóa Loại phòng & Dạng phòng theo Bảng Giá phòng chuẩn**:
    + Tự động tải dữ liệu bảng Giá phòng chuẩn (`/standard-rates`).
    + Khi click phòng hoặc thay đổi Loại phòng: Tự động điền đúng Dạng phòng tương ứng (`Double`, `Twin`, `Family`, `King`,...), Giá phòng chuẩn (ví dụ `650.000 đ`, `540.000 đ`,...) và Giá thêm giường chuẩn (`300.000 đ`).
  - Sửa lỗi hiển thị danh mục Yêu cầu đặc biệt: Khắc phục sự cố không tải danh mục khi mở modal do thiếu `immediate: true` & `onMounted`, đồng thời tự động tick chọn yêu cầu đặc biệt vừa tạo mới và lưu đồng bộ vào phòng được nhận nhanh.
  - **Tối ưu trải nghiệm Modal Nhận phòng nhanh (`QuickAssignModal.vue`)**:
    + Bỏ lớp phủ làm mờ nền phía sau (`bg-transparent pointer-events-none`), cho phép quan sát trực tiếp sơ đồ phòng.
    + Cho phép nắm giữ thanh tiêu đề (Header) để kéo thả di chuyển modal linh hoạt.
    + Đồng bộ màu sắc Header và các nút hành động (Yêu cầu đặc biệt, Đóng, Lưu) theo đúng **Tùy chỉnh màu nền Topbar** của hệ thống (`themeBg`).
- **Kế hoạch tiếp theo**: Tiếp tục hỗ trợ người dùng kiểm tra các trường hợp nghiệp vụ tiếp theo.

---

## [2026-08-19] - Hoàn thiện toàn diện Hệ thống Lịch Sử Thao Tác (Activity Logs) cho toàn bộ phân hệ PMS
### Module: Frontdesk / Housekeeping / System - Lịch Sử Thao Tác (`/frontdesk?tab=history`, `/housekeeping?tab=history`, `/system?tab=activity-log`)
- **Đã hoàn thành**:
  - **Khắc phục sự cố MariaDB / MySQL**:
    - Sửa lỗi Aria checksum và cấp quyền máy chủ `1130` (`Host 'localhost' is not allowed to connect`), đảm bảo database hoạt động ổn định trên port 3306.
  - **Nâng cấp Backend Logging Engine ([ActivityLogService.php](file:///d:/PMS/backend/app/Services/ActivityLogService.php))**:
    - Bổ sung hàm `logBusiness()` tự động thu thập IP, thiết bị (User-Agent), User đăng nhập (kèm mã NV) và lưu log chi tiết.
    - Chuẩn hóa các helper format mô tả đầy đủ theo đúng văn phong nghiệp vụ khách sạn thực tế:
      - `logBookingCreated()`: Format `* Tạo Mới Đăng Ký {Mã_ĐK} : -Tên: {Tên_BK}, -Ngày đến: {Đến}, -Ngày đi: {Đi} ({Số_Đêm} đêm), -Phòng: {Phòng}, -Loại phòng: {Loại}, -Tổng tiền: {Tổng} đ, -Đặt cọc: {Cọc} đ, -Nguồn: {Nguồn}`
      - `logBookingUpdated()`: Format `* Cập Nhật Thông Tin Đăng Ký {Mã_ĐK} : {Chi_Tiết_Thay_Đổi}`
      - `logCheckIn()`: Format `Check in cho đăng ký {Mã_ĐK} - các phòng: {Phòng}`
      - `logCheckOut()`: Format `Check out cho đăng ký {Mã_ĐK} - các phòng: {Phòng}`
      - `logRoomMove()`: Format `Chuyển phòng: {Phòng_Cũ}({Khách}) -> {Phòng_Mới}({Khách}) Lý do: {Lý_Do}`
      - `logRoomUpgrade()`: Format `Nâng hạng phòng: {Phòng} ({Loại_Cũ} -> {Loại_Mới}) Lý do: {Lý_Do}`
      - `logRoomStatusChanged()`: Format `Phòng {Phòng} Đổi trạng thái: {Trạng_Thái_Cũ} -> {Trạng_Thái_Mới}`
      - `logRoomLock()`: Format `Khóa/Mở khóa phòng {Phòng}: {Lý_Do}`
      - `logServiceAction()`: Format `* Thêm dịch vụ phòng {Phòng} (ĐK {Mã_ĐK}): {Tên_Dịch_Vụ} (SL: {SL}, Đơn giá: {Đơn_Giá} đ, Thành tiền: {Thành_Tiền} đ)`
      - `logPaymentAction()`: Format `* Đặt cọc / Thanh toán đăng ký {Mã_ĐK} (Phòng {Phòng}): {Số_Tiền} đ, Phương thức: {PTTT}`
      - `logDayClose()`: Format `* Chạy sang ngày nghiệp vụ: {Ngày_Cũ} -> {Ngày_Mới}`
      - `logInventoryAction()`: Format `* Nhập/Xuất/Kiểm kê kho {Kho}: {Chi_Tiết}`
  - **Tích hợp kích hoạt Log tự động trên toàn bộ Controllers**:
    - [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php): Tích hợp trong `store`, `update`, `destroy`, `copy`.
    - [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php): Tích hợp trong `checkIn`, `moveRoom`, `upgrade`.
    - [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php): Tích hợp trong `checkoutBooking`, `addGuest`.
    - [PaymentController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/PaymentController.php): Tích hợp trong `store` (Đặt cọc & Thanh toán trước).
    - [RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php): Tích hợp trong `updateStatus` (Đổi trạng thái buồng phòng).
    - [BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php): Tích hợp trong `store` và `postHousekeepingBill`.
    - [api.php](file:///d:/PMS/backend/routes/api.php): Tích hợp trong `/system-date/roll` (Sang ngày / Night audit).
    - [ActivityLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/ActivityLogController.php): Tối ưu hóa truy vấn lọc đa trường (`registration_code`, `room_code`, `action`, `user_id`, `date_from`, `date_to`, `search`).
  - **Nâng cấp và Hoàn thiện Giao diện Frontend ([ActivityLogTab.vue](file:///d:/PMS/frontend/src/pages/system/components/ActivityLogTab.vue))**:
    - Thiết kế bảng hiển thị đầy đủ 11 cột chuẩn: **ID**, **Thời gian**, **Người dùng**, **Địa chỉ IP**, **Thiết bị**, **Phân hệ / Màn hình**, **Hành động**, **Mã đăng ký**, **Mã phòng**, **Mô tả chi tiết**, **Chi tiết**.
    - Định dạng cột **Mô tả chi tiết**: Tự động nhận diện và làm nổi bật tiêu đề nghiệp vụ (`*`), nhãn trường (`-Tên:`, `-Phòng:`, `-Giá:`, `-Tổng tiền:`, `-Đặt cọc:`, `Lý do:`), mũi tên chuyển đổi `➜`, xuống dòng rõ ràng, dễ đọc.
    - Bộ lọc nhanh (Quick Filter Chips): `Hôm nay`, `Hôm qua`, `7 ngày qua`, `Tháng này`, `Tất cả`.
    - Bộ lọc nâng cao: Từ ngày - Đến ngày, Mã đăng ký, Mã phòng, Phân loại Hành động (Tạo mới, Cập nhật, Nhận phòng, Trả phòng, Hủy, Khóa phòng, Thanh toán, Thêm dịch vụ, Sang ngày,...), Người dùng, Phân hệ/Màn hình, Tìm kiếm chung (Debounce tức thì).
    - Xuất file Excel/CSV chuẩn UTF-8 BOM, không lỗi font tiếng Việt.
    - Đồng bộ màu sắc giao diện theo Tùy chỉnh màu nền Topbar (`themeBg`).
    - Modal so sánh JSON Diff (Dữ liệu cũ vs Dữ liệu mới) chi tiết.
  - **Tích hợp Routing & Điều hướng**:
    - Cập nhật [FrontDeskPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/FrontDeskPage.vue) hỗ trợ tab `history` (`/frontdesk?tab=history`).
    - Cập nhật [HousekeepingPage.vue](file:///d:/PMS/frontend/src/pages/housekeeping/HousekeepingPage.vue) liên kết tab `history` hiển thị dữ liệu log thời gian thực.
    - Bổ sung menu item **LỊCH SỬ THAO TÁC** vào menu Lễ tân trên [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue).
- **Trạng thái hiện tại**: Hoàn thiện toàn bộ luồng Kiểm kê tồn kho, Get Bill, kết chuyển tồn cuối và menu dropdown Giao phòng ở phân hệ Lễ tân.

---

## [2026-08-26] - Khắc phục lỗi giao diện & tích hợp hệ thống Báo cáo Đa Tab (Multi-Tab Report Viewer)
### Module: Navigation / Reports Page
- **Đã hoàn thành**:
  - **Tích hợp Báo cáo trực tiếp vào Module (Frontdesk / Reservation)**:
    - Cập nhật [`FrontDeskPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/FrontDeskPage.vue) và [`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue) để import và render [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) khi `currentTab === 'reports'`.
    - Việc này giữ nguyên 100% thanh menu chính phía trên và thanh menu sub-navigation bên dưới của phân hệ Lễ tân/Đặt phòng khi người dùng xem báo cáo.
  - **Đồng bộ hóa Route Điều hướng**:
    - Thay đổi logic link trong dropdown báo cáo của [`MainLayout.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/layouts/MainLayout.vue) để trỏ đến `/${context}?tab=reports&report=${code}` thay vì redirect hẳn sang `/reports`.
  - **Xây dựng hệ thống Báo cáo Đa Tab (Multi-Tab System) & Cải tiến Template Báo cáo phòng đến**:
    - Nâng cấp [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) hỗ trợ mở nhiều tab báo cáo đồng thời, cho phép chuyển đổi qua lại linh hoạt hoặc đóng tab.
    - Đồng bộ hóa tab đang mở với query param `report` trên URL.
    - Thiết kế giao diện checkbox dạng toggle-switch màu xanh cyan chuẩn thiết kế mẫu.
    - **Nâng cấp công cụ gom nhóm dữ liệu (Grouping Engine)**: Cập nhật [`TemplateRendererService.php`](file:///c:/xampp/htdocs/PMS/backend/app/Services/TemplateRendererService.php) hỗ trợ gom nhóm 3 cấp (Date -> Company -> Booking) qua các thuộc tính `data-group-by`, `data-subgroup-by` và `data-subsubgroup-by`, đồng thời bổ sung các row template `pms-subsubgroup-header` và `pms-subsubgroup-note`.
    - **Cập nhật Stored Procedure & Template in**: Nâng cấp SP `rpt_arriving_rooms` để trả ra thêm cột `ArrivalDateGroup` phục vụ gom nhóm theo ngày. Đồng bộ và thiết kế lại template HTML/CSS của Báo cáo phòng đến chuẩn chỉnh theo đúng giao diện tham chiếu của khách hàng (hiển thị dòng Ngày màu đỏ đậm, bảng chia cột sắc nét, thông tin Ghi chú & Đăng ký hiển thị rõ ràng, mã Booking in màu xanh lá nổi bật, các dòng tổng cộng theo công ty căn lề chuẩn xác).
- **Trạng thái hiện tại**: Hệ thống báo cáo đa tab và template Báo cáo phòng đến mới đã hoàn thành, tích hợp mượt mà vào luồng Lễ tân/Đặt phòng.
- **Kế hoạch tiếp theo**: Hỗ trợ người dùng kiểm tra các lỗi hoặc tính năng tiếp theo.

---

## [2026-08-27] - Khắc phục lỗi lệch ngày bộ chọn thời gian & định dạng mẫu Báo cáo phòng đến
### Module: Reports / Bộ chọn thời gian & Báo cáo phòng đến

- **Đã hoàn thành**:
  - **Sửa lỗi lệch ngày bộ chọn thời gian**:
    - Nâng cấp [`ReportDateRangePicker.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/components/ReportDateRangePicker.vue) sử dụng hàm format local `YYYY-MM-DD` tự định nghĩa tránh bị lệch múi giờ so với ngày hệ thống do chuyển đổi qua `toISOString()`.
    - Bổ sung đầy đủ 15 mốc thời gian và sắp xếp theo đúng thứ tự trong ảnh yêu cầu (Hôm nay, Tuần này, Tháng này, Quý này, Năm này, Ngày mai, Tuần tiếp theo, Tháng tiếp theo, Quý tiếp theo, Năm tiếp theo, Hôm qua, Tuần trước, Tháng trước, Quý trước, Năm trước, Tùy chỉnh).
  - **Tối ưu xem trước mẫu in A4**:
    - Nâng cấp [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) tự động thu hẹp chiều rộng iframe preview về `max-w-[800px]` (tỷ lệ A4 dọc chuẩn) khi sử dụng mẫu in dọc (`portrait`), giúp hiển thị trực quan và tránh bị kéo giãn dẹt ngang.
  - **Định dạng bảng dữ liệu & Bổ sung bảng kê Loại phòng**:
    - Nâng cấp [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php): Bổ sung hàm tính toán tự động thống kê Loại phòng (`room_type_summary` và `room_type_summary_total`) lấy từ danh sách khách chính.
    - Cập nhật [`arriving_rooms_reference.php`](file:///c:/xampp/htdocs/PMS/backend/database/report_templates/arriving_rooms_reference.php): Định dạng lại bảng chính còn 10 cột, căn chỉnh lại độ rộng cột, đồng thời nhúng bảng **BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG** tổng hợp số lượng, đêm, người lớn/trẻ em và tỷ lệ phần trăm xuống cuối trang.
- **Trạng thái hiện tại**: Hoàn thành toàn bộ nghiệp vụ báo cáo phòng đi, phòng đến và sửa lỗi định dạng ngày.

---

## [2026-08-27] - Triển khai Báo cáo phòng đi (Departing Rooms Report) & Sửa định dạng ngày
### Module: Reports / Báo cáo phòng đi & phòng đến

- **Đã hoàn thành**:
  - **MySQL Stored Procedure**:
    - Tạo stored procedure `rpt_departing_rooms` (chuyển đổi từ `sp_008`) để truy vấn dữ liệu phòng đi từ các bảng `booking_rooms`, `bookings`, `booking_room_guests`, `guests`, `companies`, `registration_statuses`, và `booking_room_services`.
  - **Backend & Services**:
    - Tạo service [`DepartingRoomsSummaryService.php`](file:///c:/xampp/htdocs/PMS/backend/app/Services/Reports/DepartingRoomsSummaryService.php) tính toán tổng hợp "BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG" ở đáy trang.
    - Đăng ký service và cập nhật các luồng tính toán trong [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php).
    - **Sửa lỗi định dạng ngày**: Nâng cấp [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php) tự động phát hiện và format các tham số ngày dạng `YYYY-MM-DD` sang `DD/MM/YYYY` trước khi chuyển sang template render, giúp hiển thị định dạng ngày tháng tiếng Việt chuẩn trên cả hai báo cáo phòng đến và phòng đi.
  - **Thiết kế mẫu in (Reference Template)**:
    - Tạo cấu hình mẫu in tham chiếu [`departing_rooms_reference.php`](file:///c:/xampp/htdocs/PMS/backend/database/report_templates/departing_rooms_reference.php) với thiết kế A4 Portrait, hiển thị đầy đủ 10 cột dữ liệu, các hàng tổng cộng theo Công ty/Ngày/Giai đoạn, thông tin Notice và bảng thống kê loại phòng.
  - **Database Migration**:
    - Viết và chạy thành công migration [`2026_08_27_120000_create_departing_rooms_report.php`](file:///c:/xampp/htdocs/PMS/backend/database/migrations/2026_08_27_120000_create_departing_rooms_report.php) để tạo store và seed dữ liệu nguồn, template, và định nghĩa báo cáo động.
- **Trạng thái hiện tại**: Hoàn thành toàn bộ nghiệp vụ, định dạng ngày hiển thị chuẩn `dd/mm/YYYY`.
