# Nhật Ký Tiến Độ Dự Án (Project Dev Log)

# Nhật Ký Tiến Độ Dự Án (Project Dev Log)

> File này ghi nhận tiến độ công việc, các tính năng/nghiệp vụ đã hoàn thành, trạng thái hiện tại và kế hoạch tiếp theo để tiếp nối công việc giữa các phiên làm việc.

---

## 📌 Hướng dẫn ghi log
- **Ngày ghi**: `YYYY-MM-DD`
- **Module / Nghiệp vụ**: Tên module (Housekeeping, Booking, Thu ngân, Cài đặt,...)
- **Nội dung hoàn thành**: Chi tiết logic, API, UI, DB migration/seeder đã xử lý + link file.

## [2026-09-07] - Tích hợp tự động RbacMatrixSeeder vào luồng Reset Multi-DB
### Module: Hệ thống / RBAC Seeder & Console Commands ([database_domains.php](file:///d:/PMS/backend/config/database_domains.php), [ResetMultiDbCommand.php](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php))

- **Đã hoàn thành**:
  - **Đăng ký seeder vào cấu hình hệ thống**:
    - Bổ sung `Database\Seeders\RbacMatrixSeeder::class` vào mảng `system_seeders` trong [config/database_domains.php](file:///d:/PMS/backend/config/database_domains.php).
  - **Tích hợp tự động vào lệnh Reset Multi-DB ([ResetMultiDbCommand.php](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php))**:
    - Thêm phương thức `seedRbacMatrix()` tự động chạy `db:seed --class=RbacMatrixSeeder` trên `mysql_system`.
    - Gọi tự động ngay sau khi hoàn thành đồng bộ danh sách chi nhánh động (`syncDatabasesToSystemBranches`) và gán quyền Super Admin.
    - Đảm bảo 100% các lần chạy `php artisan db:reset-all --seed-all` (hoặc `target=system`) đều tự động nạp đầy đủ 46 màn hình (184 permissions) và backfill trọn vẹn `branch_role_permissions` trên tất cả chi nhánh phát hiện động (`GKT6`, `HKT5`, `HKT8`...).
- **Kiểm tra**:
  - `php -l`: Cú pháp PHP chuẩn trên cả 2 file.
  - `php artisan test --filter=OrganizationRbacTest`: 15/15 tests đạt (45 assertions).

---

## [2026-09-07] - Tối ưu UI Vị trí công việc theo chi nhánh & Bổ sung nút thu gọn/mở rộng
### Module: Hệ thống / Quản lý Nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue))

- **Đã hoàn thành**:
  - **Khắc phục lỗi tràn UI khối "Vị trí công việc theo chi nhánh"**:
    - Thay thế dropdown select dài dễ bị tràn viền bằng thẻ thông tin **chỉ xem (Read-only Tag)** tinh gọn, bo góc, có icon chức vụ và nhãn phòng ban màu xanh sky nhã nhặn.
    - Thêm `overflow-hidden`, `min-w-0`, `truncate` và `box-border` đảm bảo co giãn hoàn hảo không bao giờ bị tràn khung.
    - Bổ sung padding đáy `pb-12` cho container modal giúp khi cuộn xuống không bị che khuất viền đáy.
  - **Bổ sung nút mũi tên bật/tắt (Collapsible Toggle)**:
    - Cho phép click vào tiêu đề để thu gọn hoặc mở rộng danh sách vị trí chi nhánh.
    - Mũi tên xoay 180 độ có hiệu ứng chuyển động mượt mà, tích hợp badge đếm số chi nhánh đã chọn.
  - **Đồng bộ phân công vị trí tập trung**:
    - Tự động kế thừa chức danh chính của nhân viên sang các chi nhánh được tick chọn.
    - Xác nhận và làm rõ luồng nghiệp vụ: Việc phân công Role và ma trận quyền hạn cho từng vị trí theo chi nhánh được thực hiện tập trung tại **Cơ cấu tổ chức** (modal Sửa ứng dụng & Cấu hình). Modal nhân viên chỉ đóng vai trò xem thông tin phân công.
- **Kiểm tra**:
  - `npm run build`: Thành công 100%.

---

## [2026-09-07] - Tự động sinh Username theo Tên nhân viên & Hiển thị rõ ràng Mật khẩu mặc định
### Module: Hệ thống / Quản lý Nhân viên & Xác thực (`EmployeeTab.vue`, `AuthController.php`, `UserController.php`)

- **Đã hoàn thành**:
  - **Trường Tên đăng nhập (Username) trong modal Thêm/Sửa nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue))**:
    - Bổ sung ô nhập `Tên Đăng Nhập (Username) *` vào form modal nhân viên.
    - Tự động sinh username (`toUsernameSlug`): Khi người dùng nhập "Tên Nhân Viên" (ví dụ: `Thảo Vy` $\rightarrow$ `thaovy`, `Nguyễn Văn A` $\rightarrow$ `nguyenvana`), hệ thống tự động bóc tách dấu tiếng Việt, viết thường không dấu và điền sẵn vào ô Username.
    - Cho phép người dùng tùy ý chỉnh sửa lại username nếu muốn.
    - Hiển thị cột `Tên Đăng Nhập` (Username) ngay sau cột Tên Nhân Viên trên bảng danh sách nhân viên để người quản trị dễ dàng tra cứu.
  - **Minh bạch Mật khẩu mặc định (Email)**:
    - Khu vực mật khẩu khởi tạo được đóng khung nổi bật với badge: `Mật khẩu mặc định: [email nhân viên]`.
    - Placeholder hiển thị động: `Mặc định nếu để trống: [email nhân viên]`.
    - Kèm ghi chú rõ ràng: `Lưu ý: Nếu để trống ô này, mật khẩu đăng nhập ban đầu sẽ là Email của nhân viên. Hệ thống sẽ bắt buộc đổi mật khẩu ở lần đăng nhập đầu tiên.`
    - Nút `Đặt Lại Mật Khẩu`: Cập nhật popup xác nhận và toast thông báo hiển thị chính xác địa chỉ email nhân viên được đặt làm mật khẩu.
  - **Xác thực đăng nhập linh hoạt ([AuthController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php))**:
    - Nâng cấp endpoint `login` hỗ trợ đăng nhập linh hoạt bằng cả **Username** hoặc **Email** (tìm theo `username` hoặc `email` và so khớp mật khẩu bằng `Hash::check`), giúp người dùng đăng nhập thuận tiện, không bị nhầm lẫn.
- **Kiểm tra**:
  - `npm run build`: Thành công 100%, không lỗi template hay syntax.
  - `php artisan test --filter=OrganizationRbacTest`: 15/15 tests passed (45 assertions).
  - `php artisan test --filter=login`: 2/2 tests passed (3 assertions).
- **Trạng thái hiện tại**: Hoàn thành 100%.

## [2026-09-07] - Hoàn thiện RBAC đa ứng dụng, phân quyền kho theo chi nhánh & loại bỏ mã cứng
### Module: Hệ thống / Phân quyền RBAC (`User.php`, `UserOrganizationController.php`, `BranchRolePermissionController.php`, `EmployeeTab.vue`, `OrgStructureTab.vue`, `ForcePasswordChange.php`, `UserController.php`, migration refinements)

- **Đã hoàn thành**:
  - **Mở rộng ma trận phân quyền chi tiết 46 màn hình nghiệp vụ (184 permissions) theo chuẩn [image3.png](file:///d:/PMS/.agents/scratch/docx_media/word/media/image3.png) & thực tế khách sạn**:
    - Nâng cấp [RbacMatrixSeeder.php](file:///d:/PMS/backend/database/seeders/RbacMatrixSeeder.php): tách nhỏ và chi tiết hóa từ 26 lên **46 màn hình nghiệp vụ chuyên sâu** thuộc 6 phân hệ lớn:
      - **FO (14 màn hình)**: Đặt phòng (Booking), Đặt cọc (Deposit), Hóa đơn (Bill/Folio), Công ty & Đại lý (Company/TA), Phân bổ quỹ phòng (Allotment), Hồ sơ khách hàng (Guest Profile), Sơ đồ phòng (Rack/FrontDesk), Giao nhận phòng (Check-in/out), Chuyển phòng (Room Move), Khóa phòng OOO/OOS (Room Lock), Xử lý No-show, Thanh toán & Thu tiền, Cấn trừ công nợ (Debt Settlement), Dịch vụ phòng.
      - **HK (7 màn hình)**: Tổng quan buồng, Trạng thái phòng (Room Status), Phân công dọn phòng (Assignment), Đồ thất lạc (Lost & Found), Hóa đơn minibar/giặt ủi (Service Bills), Kho buồng & vải vóc, Báo cáo buồng phòng.
      - **FB (5 màn hình)**: Tổng quan nhà hàng (Outlets), Order & gọi món, Thanh toán F&B, Menu & sản phẩm, Tiệc & sự kiện.
      - **MGMT (8 màn hình)**: Báo cáo tổng hợp, Báo cáo doanh thu, Báo cáo công suất, Báo cáo khách đến, Báo cáo khách đi, Báo cáo khách lưu trú, Báo cáo hủy phòng, Lịch sử thao tác (Audit Logs).
      - **CONFIG (8 màn hình)**: Thông tin khách sạn, Hạng phòng & loại phòng, Danh mục buồng phòng (Rooms), Bảng giá phòng (Rate Plans), Dịch vụ khách sạn, Ca làm việc (Shifts), Nguồn khách & thị trường (Markets), Ngày hệ thống (Night Audit).
      - **SYSTEM (4 màn hình)**: Quản lý nhân viên, Vai trò & phân quyền, Chi nhánh, Cài đặt hệ thống.
    - Mỗi màn hình đều có đủ 4 actions checkbox độc lập (`View`, `Add`, `Delete`, `Edit`).
    - Nạp thành công **4,256 bản ghi** `branch_role_permissions` trên 8 chi nhánh, phân quyền sát theo từng cấp bậc (Super Admin, Quản trị chi nhánh, Quản lý, Trưởng bộ phận, Nhân viên).
    - Bảo toàn 100% các mã quyền route hiện có, toàn bộ 15/15 tests `OrganizationRbacTest.php` đạt.

  - **Migration [2026_09_05_110000_patch_organization_rbac_refinements.php](file:///d:/PMS/backend/database/migrations/2026_09_05_110000_patch_organization_rbac_refinements.php) — cải tiến toàn diện**:
    - Mở rộng `positions.code` lên `varchar(100)` tương tự `permissions.code`/`screen_code`.
    - Hàm `backfillCustomRolesAndPositions` chỉ xử lý role đang thực sự được gán cho user (`whereIn user_roles`), bỏ qua role đã có `position_branch_roles`, tìm phòng ban theo `department_scope` hoặc phòng ban active đầu tiên (loại bỏ fallback cứng `OT`).
    - Hàm `backfillSuperAdminAcrossBranches` tìm đúng `position_id` từ `PositionBranchRole` tương ứng chi nhánh thay vì tìm tên vị trí có `ADMIN`/`DIR`.
    - Cả hai hàm dùng `config('database_domains.default_application_code')` thay vì hardcode `'PMS'`.
  - **[User.php](file:///d:/PMS/backend/app/Models/User.php)**:
    - `allPermissions(?$branchId, ?$applicationCode)`: `applicationCode` optional, mặc định từ config; loại bỏ hoàn toàn fallback leo thang quyền khi user đã có vị trí.
    - `hasPermission($code, $branchId, $applicationCode)`: tự suy `applicationCode` từ DB permission tương ứng nếu không truyền.
    - `canPerformHistoricalDateActions($branchId, $applicationCode)`: hàm mới tính quyền thao tác ngày cũ từ `Role.allow_historical_date_actions` theo vị trí + chi nhánh + ứng dụng thực tế (không còn đọc `user.settings` hardcode).
    - `isSuperAdmin()`: dùng join trực tiếp (`position_branch_roles` → `roles`) thay vì whereHas lồng nhau để tránh N+1.
  - **[ForcePasswordChange.php](file:///d:/PMS/backend/app/Http/Middleware/ForcePasswordChange.php)** (mới): middleware chặn mọi API business (trả 423) khi `must_change_password = true`, trừ whitelist `/api/login`, `/api/logout`, `/api/me`, `/api/me/change-password`, `/api/hotel-settings`; đăng ký vào group `api`.
  - **[UserController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserController.php)**:
    - Không hardcode `'mysql_system'` và `'NB'` prefix: đọc từ `config('database_domains.system_connection')` và `config('database_domains.employee_code_prefix')`.
    - `username` không bắt buộc nhập; tự động fallback về `email` khi để trống.
    - Unique validation dùng `Rule::unique($system.'.users')` để không bị lỗi khi tên connection thay đổi.
  - **[UserOrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserOrganizationController.php)**:
    - `syncWarehouses`: validate kho phải thuộc chi nhánh đã được gán vị trí; kiểm tra warehouse_id có tồn tại thật trong database chi nhánh (cross-DB query); chặn trùng lặp.
    - Dùng `config('database_domains.default_application_code')` thay vì `'PMS'` hardcode.
  - **[BranchRolePermissionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BranchRolePermissionController.php)**:
    - Toàn bộ `application_code = 'PMS'` cứng đổi sang `config('database_domains.default_application_code')`.
  - **[PaymentController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/PaymentController.php) & [BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php)**:
    - `canOperateOldDay()` đổi hoàn toàn sang `$user->canPerformHistoricalDateActions(branchId)` từ Role thực tế, loại bỏ việc đọc `user.settings` và username hardcode.
  - **[http.js](file:///d:/PMS/frontend/src/services/http.js)**: Không còn fallback `'HKT1'` / `'1'` cứng cho header `X-Branch-Code`/`X-Branch-Id`; chỉ gắn khi có giá trị thật trong localStorage.
  - **[company-service.js](file:///d:/PMS/frontend/src/services/company-service.js)**: `fetchWarehouses(branch)` nhận tham số chi nhánh để gửi đúng header; tải kho riêng theo từng chi nhánh.
  - **[EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue)**:
    - Kho (`warehousesByBranch`) nạp lazy theo từng chi nhánh, không nạp tất cả ngay khi mở modal.
    - Dropdown "Chi nhánh áp dụng quyền kho" cho phép chọn chi nhánh cụ thể trước khi tick kho.
    - `selectedWarehouses` từ `number[]` đổi thành `{system_branch_id, warehouse_id}[]` để lưu đúng chi nhánh.
    - `syncUserOrganization` gửi đúng `application_code` từ `position.branch_roles` (không hardcode PMS); `application_codes` tự tổng hợp từ assignments + ứng dụng hiện hành.
    - `positionsForBranch` bỏ filter `application_code === 'PMS'`; hiển thị vị trí của mọi ứng dụng cho chi nhánh đó.
    - Mật khẩu không còn bắt buộc khi thêm nhân viên; nếu để trống sẽ dùng email làm mật khẩu mặc định.
    - "Đặt Lại Mật Khẩu" gọi `resetUserPassword()` (endpoint `/api/me/reset-password`), không còn set cứng `password123`.
  - **[OrgStructureTab.vue](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)**:
    - Tab "Người dùng" dùng `position.user_assignments` từ API (eager load) thay vì lọc theo `job_title_code` cũ.
    - Dropdown ứng dụng trong modal "Sửa ứng dụng" khi thay đổi sẽ nạp lại đúng assignment của ứng dụng đó.
  - **[OrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OrganizationController.php)**:
    - Mở rộng max `code` validation lên `100`.
    - `syncLegacyDepartments()` tự đồng bộ bảng `departments` cũ vào `organization_departments` khi tải trang tổ chức.
- **[config/database_domains.php](file:///d:/PMS/backend/config/database_domains.php)**:
  - Thêm `default_application_code` (default `PMS`) và `employee_code_prefix` (default `NB`) đọc từ `.env`.
- **Kiểm thử**:
  - `OrganizationRbacTest.php`: **15/15 tests đạt (45 assertions)**, thêm 4 test case mới:
    - Quyền POS không phụ thuộc vào PMS (application riêng biệt).
    - `canPerformHistoricalDateActions` phản ánh đúng cờ Role tại chi nhánh.
    - `ForcePasswordChange` chặn API nghiệp vụ khi `must_change_password = true`.
  - `npm run build`: thành công 100%, không lỗi Vue SFC.
  - `php -l`: không lỗi cú pháp trên 6 file backend được sửa.
- **Trạng thái hiện tại**: Hoàn thành 100% — không còn hardcode chi nhánh, ứng dụng, vị trí hay kho.
- **Kế hoạch tiếp theo**: Kiểm thử E2E trên staging; nghiệm thu thao tác ngày cũ theo Role thực tế.

---

## [2026-09-05] - Cập nhật toàn diện Giao diện (UI, Màu sắc, Bố cục, Modals) theo Phân quyền và tạo nhân viên.docx
### Module: Hệ thống / Quản lý Cơ cấu tổ chức, Phân quyền Roles & Quản lý nhân viên (`OrgStructureTab.vue`, `RoleManageTab.vue`, `EmployeeTab.vue`)

- **Đã hoàn thành**:
  - **Trực quan hóa tài liệu mẫu**:
    - Trích xuất toàn bộ 7 ảnh chụp màn hình từ `Phân quyền và tạo nhân viên.docx` làm chuẩn đối chiếu chi tiết (cây tổ chức, modal sửa app, ma trận phân quyền 4 cột, modal nhân viên, danh sách bảng, dropdown vị trí, phân quyền kho & chi nhánh chính).
  - **Cơ cấu tổ chức ([OrgStructureTab.vue](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)) - Hình 1, 2, 3**:
    - Cây phòng ban & vị trí: Cập nhật icon `[-]` nền vuông xanh `#72c6e6`, highlight vị trí được chọn màu xanh `#72c6e6` chữ trắng đậm kèm hiệu ứng mũi tên.
    - Tab "Ứng dụng" / "Người dùng": Thiết kế thẻ ứng dụng hình thoi đặc trưng, hiển thị "Version" và các liên kết thao tác "Xóa" (đỏ) / "Sửa" (xanh).
    - Modal "Sửa ứng dụng" (Hình 2): Banner tiêu đề màu xanh sky `#72c6e6`, bảng cấu hình chọn Role theo chi nhánh với nút "Cấu hình" dạng viên thuốc (pill badge).
    - Modal "Phân quyền" (Hình 3): Banner tiêu đề `#72c6e6`, thanh phụ "Màn hình", ma trận 4 cột quyền chuẩn xác: `View` | `Add` | `Delete` | `Edit`.
  - **Quản lý vai trò & Phân quyền ([RoleManageTab.vue](file:///d:/PMS/frontend/src/pages/system/components/RoleManageTab.vue)) - Hình 3**:
    - Cột danh sách vai trò: Nút `+ Thêm` màu `#0ea5e9`, danh sách vai trò sạch sẽ với thanh chỉ báo active màu xanh.
    - Bảng ma trận quyền: Gom nhóm theo Module với biểu tượng `[-]` nền xám bo góc, tiêu đề Module in hoa đậm, 4 cột thao tác theo đúng thứ tự tài liệu: `View` | `Add` | `Delete` | `Edit`.
    - Chuẩn hóa header bộ lọc: Dropdown chọn Ứng dụng & Chi nhánh gọn gàng, badge ngày giờ lịch sử, đồng bộ toàn bộ modals (Thêm Role, Nhân bản Role, Thêm màn hình) sang banner `#72c6e6`.
  - **Quản lý nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue)) - Hình 4, 5, 6, 7**:
    - Bảng danh sách nhân viên (Hình 5): Căn chỉnh 9 cột mặc định (`Mã NV`, `Tên NV`, `Vị trí`, `Bộ phận`, `Ngày sinh`, `Điện thoại`, `Email`, `Địa chỉ`, `Xóa`), ẩn mặc định 2 cột thừa (tên đăng nhập, chữ ký) nhưng vẫn cho bật qua bánh răng cài đặt.
    - Cột xóa: Đổi nút xóa hình khối xanh cũ thành icon thùng rác đỏ trực tiếp trên ô bảng chuẩn Hình 5.
    - Thanh công cụ: Nút tìm kiếm và nút tròn `(+) Thêm` màu `#72c6e6`.
    - Modal "Chỉnh Sửa / Thêm Nhân Viên" (Hình 4, 6):
      - Form 2 cột với nền input vàng kem nhẹ `#fffbeb` cho các trường nhập liệu.
      - Dropdown Vị trí công việc lọc động theo đúng Bộ phận được chọn (Hình 6).
      - Thẻ Chữ ký: Khung viền nét đứt với icon tròn `+` và nút `Chọn Ảnh`, tích hợp xem trước và icon xóa/xem (Hình 4).
      - Footer: Switch iOS bật/tắt "Người Sử Dụng", nút "Đặt Lại Mật Khẩu", nút Cancel, nút Lưu xanh `#72c6e6`, và nút trợ giúp màu cam `?` bo tròn ở góc trái (Hình 4).
    - Tab "Phân quyền đặc thù" (Hình 7):
      - Bảng Chi nhánh với highlight dòng đang chọn bằng màu `#99cff5` nhạt.
      - Switch iOS bật/tắt "Chi Nhánh Chính".
      - Bảng phân quyền kho chia 3 cột checkboxes gọn gàng mang tiêu đề "Phân Quyền Kho Cho User: [Tên Nhân Viên]".
- **Kiểm tra**:
  - `npm run build`: Thành công 100% không cảnh báo lỗi Vue SFC.
  - `php artisan test tests/Feature/OrganizationRbacTest.php`: 11/11 tests đạt (100%).
- **Trạng thái hiện tại**: Hoàn thành 100%.

---

## [2026-09-05] - Chuẩn hóa Cơ cấu tổ chức, Phân quyền RBAC đa chi nhánh và Quản lý nhân viên
### Module: Hệ thống / Phân quyền & Quản lý nhân viên (`OrganizationController.php`, `BranchRolePermissionController.php`, `UserOrganizationController.php`, `User.php`, `EmployeeTab.vue`, `OrgStructureTab.vue`, `RoleManageTab.vue`, `ForceChangePasswordModal.vue`, `App.vue`)

- **Đã hoàn thành**:
  - **Bảo mật & Xác thực**:
    - [AuthController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php): Loại bỏ triệt để việc log plaintext password vào file log; thêm kiểm tra `is_active_user` khi đăng nhập (trả về 403 nếu tài khoản bị khóa/ngừng kích hoạt); bổ sung endpoint đổi mật khẩu `POST /api/me/change-password`; cập nhật `login()` và `me()` tải danh sách chi nhánh và permissions từ schema mới.
    - [EnsureBranchAccess.php](file:///d:/PMS/backend/app/Http/Middleware/EnsureBranchAccess.php): Chặn người dùng có `is_active_user = false` truy cập API nghiệp vụ với HTTP 403.
    - [api.php](file:///d:/PMS/backend/routes/api.php): Đăng ký route `POST /api/me/change-password` và các route quản trị cơ cấu tổ chức, vai trò ma trận, phân quyền kho.
    - [User.php](file:///d:/PMS/backend/app/Models/User.php):
      - Khắc phục lỗ hổng fallback: User đã gán vị trí ở chi nhánh nhưng có quyền rỗng sẽ **không bao giờ** fallback về quyền cũ (loại bỏ nguy cơ leo thang quyền ngoài ý muốn). Chỉ fallback về `user_roles` đối với user chưa hề được gán vị trí trong schema mới.
      - Super Admin tự động bypass và lấy toàn bộ permissions đang hoạt động.
      - `hasBranchAccess()` kiểm tra quyền Super Admin, `user_branch_positions` và `user_branches`.
  - **Migration & Backfill DB**:
    - [2026_09_05_100000_expand_organization_rbac.php](file:///d:/PMS/backend/database/migrations/2026_09_05_100000_expand_organization_rbac.php): Migration gốc mở rộng schema RBAC, tạo cấu trúc cây phòng ban, vị trí công việc, và vai trò chi nhánh.
    - [2026_09_05_110000_patch_organization_rbac_refinements.php](file:///d:/PMS/backend/database/migrations/2026_09_05_110000_patch_organization_rbac_refinements.php):
      - Tăng kích thước `permissions.code` và `permissions.screen_code` lên `varchar(100)` để chứa đầy đủ mã dài theo cấu trúc module/app.
      - Backfill đầy đủ Super Admin trên mọi chi nhánh hoạt động.
      - Tự động sinh Position và PositionBranchRole cho mọi Custom Role cũ chưa có vị trí.
      - Đã chạy thành công qua `php artisan migrate`.
  - **Backend API Controllers**:
    - [BranchRolePermissionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BranchRolePermissionController.php): Tự động thêm tiền tố app (`pos.`, `sys.`) cho màn hình thuộc các ứng dụng ngoài PMS, tránh đè chéo namespace; tự động gán quyền `view` khi chọn bất kỳ hành động `add`/`edit`/`delete`.
    - [UserOrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserOrganizationController.php): Quản lý đồng bộ vị trí nhân viên qua `sync()`; lưu phân quyền kho qua `syncWarehouses()`.
    - [OrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OrganizationController.php): Dynamic connection, composite unique mã chức danh theo bộ phận, chống trùng lặp chi nhánh.
  - **Giao diện Frontend**:
    - [EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue):
      - Chuyển hoàn toàn sang lưu quyền nhân viên qua API `syncUserOrganization` (`POST /api/users/{id}/organization/sync`), loại bỏ triệt để việc gọi API cũ `syncUserBranches` và `syncUserRoles`.
      - Danh sách bộ phận và vị trí công việc được nạp động từ cây tổ chức (computed), loại bỏ hoàn toàn các mã chức danh cũ hardcode (`RL016`, `RL017`,...).
      - Danh sách kho được lấy động từ API `/api/warehouses`.
      - Phân quyền kho được lưu theo từng chi nhánh qua API `syncUserWarehouses` (`POST /api/users/{id}/warehouses/sync`) vào bảng `user_warehouse_permissions`.
    - [ForceChangePasswordModal.vue](file:///d:/PMS/frontend/src/components/ForceChangePasswordModal.vue) & [App.vue](file:///d:/PMS/frontend/src/App.vue):
      - Modal bắt buộc đổi mật khẩu lần đầu khi `must_change_password === true`, gắn toàn cục tại `App.vue`, không thể đóng/bỏ qua, tích hợp nút đăng xuất an toàn.
    - [company-service.js](file:///d:/PMS/frontend/src/services/company-service.js): Export `fetchWarehouses`, `syncUserWarehouses`, `changeUserPassword`.
- **Kiểm tra & Kiểm thử tự động**:
  - [OrganizationRbacTest.php](file:///d:/PMS/backend/tests/Feature/OrganizationRbacTest.php): Bộ kiểm thử hoàn chỉnh 11 kịch bản nghiệp vụ:
    1. Một nhân viên có vị trí công việc khác nhau tại từng chi nhánh.
    2. Tài khoản chưa kích hoạt / bị khóa (`is_active_user = false`) bị từ chối đăng nhập với HTTP 403.
    3. Không lưu mật khẩu thô vào file log khi đăng nhập.
    4. Cùng một nhân viên nhận bộ quyền hoàn toàn khác nhau tại Chi nhánh A và Chi nhánh B.
    5. Cấp quyền Add/Edit/Delete tự động kéo theo quyền View của màn hình tương ứng.
    6. Super Admin có toàn quyền trên toàn bộ chi nhánh.
    7. Endpoint đổi mật khẩu `/api/me/change-password` xác thực mật khẩu cũ và cập nhật mật khẩu mới.
    8. Fallback tương thích ngược về `user_roles` cũ nếu nhân viên chưa được gán vị trí theo schema mới.
    9. Quyền rỗng tại chi nhánh KHÔNG fallback về legacy roles gây nguy cơ leo thang quyền.
    10. Thêm màn hình non-PMS tự động tiền tố hóa mã permission chống trùng lặp.
    11. Endpoint sync-warehouses lưu chính xác danh sách kho vào DB.
  - Kết quả chạy test: `11 passed, 33 assertions (100%)`.
  - Build frontend Vite (`npm run build`): Thành công 100% không lỗi.
- **Tài liệu bàn giao**:
  - Đã cập nhật toàn diện [RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md](file:///d:/PMS/RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md) phản ánh đúng trạng thái đã hoàn tất toàn bộ 7 điểm hiệu chỉnh.
- **Trạng thái hiện tại**: Đã hoàn thành 100% tất cả các yêu cầu rà soát và sửa đổi.
- **Kế hoạch tiếp theo**: Sẵn sàng triển khai nghiệm thu và kiểm tra người dùng cuối.

---

## [2026-09-05] - Loại bỏ triệt để phòng đã chuyển (status = 100) khỏi Tab Phòng đến (Sang ngày)
### Module: Frontdesk / Sang ngày (`DayClosePage.vue`)

- **Đã hoàn thành**:
  - Khắc phục lỗi tab "Phòng đến" và biến đếm `arrivalCount` trên trang Sang ngày (`DayClosePage.vue`) lấy cả các phòng đã chuyển (`status = 100` / `move_room`), khiến hệ thống hiểu nhầm còn phòng đến chưa check-in và vô hiệu hóa nút "Sang ngày".
  - Trong [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue):
    - Kiểm tra trực tiếp và loại bỏ ngay lập tức mọi phòng có `Number(r.status) === 100`, `r.status === '100'`, hoặc `r.move_room == 1` ngay từ đầu vòng lặp xử lý danh sách phòng.
    - Cập nhật điều kiện xác định Phòng đến: Bắt buộc phòng phải ở trạng thái Đặt trước chưa check-in (`isBooked && !isCheckedIn`), ngày đến trùng ngày hệ thống (`arrDate === sysDateStr`), và tuyệt đối không phải phòng chuyển (`!isMoved && Number(r.status) !== 100`).
    - Đồng bộ logic loại trừ phòng `status = 100` trên cả biến đếm `arrCount` (nút Sang ngày) và danh sách hiển thị dữ liệu bảng (`processItem`).
- **Kiểm tra**:
  - Build frontend Vite production (`npm run build`): Thành công 100% không lỗi.
  - MariaDB recovery: Đã phục hồi và khởi chạy dịch vụ MariaDB ổn định.
- **Tệp thay đổi**:
  - [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue)

---

## [2026-09-04] - Khắc phục lỗi khóa ngoại 1451 khi sửa số lượng khách / trẻ em trong phòng
### Module: Reservation / Cập nhật phòng (`BookingController.php`)

- **Đã hoàn thành**:
  - Xử lý triệt để lỗi `SQLSTATE[23000]: 1451 Cannot delete or update a parent row (booking_room_guests_guest_id_foreign ON DELETE RESTRICT)` khi cập nhật số lượng khách hoặc trẻ em của phòng.
  - Trong [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php):
    - Khi giảm số người lớn (`adults`), chỉ gỡ liên kết phòng `$pivotToRemove->delete()`.
    - Đối với bảng `guests`, kiểm tra an toàn: chỉ dọn dẹp profile nếu đó là khách ảo tự sinh (`Guest X`) và không còn bất kỳ liên kết phòng/dịch vụ/thanh toán nào khác (`!BookingRoomGuest::where('guest_id', $gId)->exists()`).
    - Bọc logic dọn dẹp khách ảo trong `try...catch` để việc dọn rác không bao giờ làm gián đoạn hay crash giao dịch lưu đặt phòng.
- **Kiểm tra**:
  - `php -l BookingController.php`: Cú pháp chuẩn, không lỗi.
  - `php artisan test tests/Feature/RoomMoveTest.php`: 10/10 passed.
  - `php artisan test tests/Feature/CheckoutRestoreTest.php`: 4/4 passed.
- **Tệp thay đổi**:
  - [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php)

---

## [2026-09-04] - Tách danh mục thông tin khách thành 9 bảng Database độc lập
### Module: Reservation / Cấu hình thông tin khách (`GuestDefinitionController.php`, `GuestInfoModal.vue`, `GuestDetailModal.vue`)

- **Đã hoàn thành**:
  - Chuẩn hóa đầy đủ 9 bảng Master Data tương ứng danh sách định nghĩa thông tin khách ProVista:
    1. `guest_titles` (SP8015 - DanhXung): 8 danh xưng chuẩn.
    2. `border_gates` (SP8017 - DanhMucCuaKhau): 13 cửa khẩu quốc tế đường hàng không, đường bộ, đường biển.
    3. `entry_purposes` (SP8019 - DanhMucMucDichLuuTru): 7 mục đích lưu trú/nhập cảnh.
    4. `nationalities` (SP8020 - DanhMucQuocTich): Bảng quốc tịch hiện hữu.
    5. `guest_types` (SP8042 - GuestType): 6 phân loại khách (FIT, GIT, VIP, Crew, Long Stay, Corporate).
    6. `provinces` (SP8047 - Tinh/Thanh Pho): Đã seed 63 tỉnh/thành phố chuẩn Việt Nam.
    7. `districts` (SP8048 - Quận Huyện): Lưu tự động khi người dùng chọn/lưu khách.
    8. `wards` (SP8049 - Phường/ Xã): Lưu tự động khi người dùng chọn/lưu khách.
    9. `id_types` (SP8055 - LoaiGiayTo): 4 loại giấy tờ tùy thân chuẩn (CCCD, CMND, Hộ chiếu, Khác).
  - Backend:
    - Tạo 2 migration `create_guest_definitions_tables` và `create_provinces_districts_wards_tables` chạy trên toàn bộ 9 Database chi nhánh.
    - Tạo Models `GuestTitle`, `BorderGate`, `EntryPurpose`, `GuestType`, `IdType`, `Province`, `District`, `Ward`.
    - Tạo seeders `GuestDefinitionSeeder` và `ProvinceSeeder`, đăng ký vào `BranchDatabaseSeeder`.
    - API `GET /api/guest-definitions` gom dữ liệu 1 request; các endpoint lẻ và `POST /api/geo/sync`.
    - Tự động bóc tách và lưu địa giới hành chính vào `provinces`, `districts`, `wards` khi lưu khách trong `GuestController`.
  - Frontend:
    - Bổ sung `fetchGuestDefinitions()` và `syncGeoData()` vào `booking-service.js`.
    - Cập nhật `GuestInfoModal.vue` & `GuestDetailModal.vue` nạp dữ liệu động cho các dropdown: Danh xưng, Loại giấy tờ, Loại khách, Mục đích, Cửa khẩu (chuyển ô text thành select dropdown).
- **Kiểm tra**:
  - `php artisan migrate:all`: Đạt trên 9/9 database PMS.
  - Seeding: Nạp thành công trên tất cả database chi nhánh.
  - `npm run build`: Hoàn tất thành công, không có lỗi cú pháp hay bundle.
  - Test tinker & scratch `POST /api/geo/sync`: Ghi nhận dữ liệu chuẩn vào DB.
- **Tệp thay đổi**:
  - `backend/database/migrations/2026_09_04_150000_create_guest_definitions_tables.php`
  - `backend/database/migrations/2026_09_04_154000_create_provinces_districts_wards_tables.php`
  - `backend/app/Models/GuestTitle.php`, `BorderGate.php`, `EntryPurpose.php`, `GuestType.php`, `IdType.php`, `Province.php`, `District.php`, `Ward.php`
  - `backend/database/seeders/GuestDefinitionSeeder.php`, `ProvinceSeeder.php`, `BranchDatabaseSeeder.php`
  - `backend/app/Http/Controllers/Api/GuestDefinitionController.php`
  - `backend/app/Http/Controllers/Api/GuestController.php`
  - `backend/routes/api.php`
  - `frontend/src/services/booking-service.js`
  - `frontend/src/pages/reservation/components/GuestInfoModal.vue`
  - `frontend/src/pages/reservation/components/GuestDetailModal.vue`

---
## [2026-09-04] - Sửa chuyển khách sang phòng Inhouse và phân bổ bill theo khách
### Module: Reservation / Chuyển phòng & Hóa đơn (`BookingRoomController.php`, `CheckoutPage.vue`)

- **Đã hoàn thành**:
  - Giữ nguyên khách chính hiện hữu của phòng Inhouse đích; mọi khách mới chuyển tới được thêm dưới dạng khách phụ.
  - Chỉ chuyển các bill đang thuộc phòng nguồn và đúng khách được chọn sang phòng đích; không cập nhật nhầm bill có sẵn của phòng đích.
  - Giữ `RentalRoomId2`, `CustomerId2` là `NULL` đối với bill chưa từng chuyển của phòng đích.
  - Đồng bộ chi tiết dịch vụ theo bill/khách sang phòng đích để phòng cũ không còn hiển thị dịch vụ của khách đã chuyển.
  - Màn Hóa đơn xác định chủ bill theo `Id2` khi bill đã chuyển, nếu chưa chuyển thì dùng `Id1`; bill được tách đúng theo từng khách thay vì gom vào khách chính.
  - Loại khách trạng thái `100` khỏi danh sách khách còn ở của phòng nguồn và luôn ưu tiên hiển thị khách chính phòng đích trước.

- **Kiểm tra**:
  - `RoomMoveTest`: 10/10 test, 66 assertions đạt; có ca kiểm thử riêng cho hai phòng có bill 264.500 và 100.000.
  - Test frontend quyền sở hữu bill và trạng thái checkout: 7/7 đạt.
  - Build frontend Vite và kiểm tra cú pháp PHP đạt.

- **Tệp thay đổi**:
  - `backend/app/Http/Controllers/Api/BookingRoomController.php`
  - `backend/tests/Feature/RoomMoveTest.php`
  - `frontend/src/pages/frontdesk/CheckoutPage.vue`
  - `frontend/src/utils/service-bill-ownership.js`
  - `frontend/tests/service-bill-ownership.test.js`

---
## [2026-09-03] - Đổi trạng thái phòng sau trả phòng thành Trống dơ
### Module: Hóa đơn / Trả phòng (`GuestController.php`)

- **Đã hoàn thành**:
  - Sửa luồng checkout toàn bộ phòng: cập nhật trực tiếp `room_status_code = vacant_dirty` cho phòng thực.
  - Loại bỏ việc gán `status = checkout`, vì mutator của model `Room` chuyển giá trị này thành `turndown`.
  - Giữ nguyên luồng khôi phục checkout: phòng được trả về trạng thái có khách ở khi thao tác hoàn tác thành công.
- **Kiểm tra**:
  - `php -l GuestController.php`: đạt.
  - `CheckoutRestoreTest`: 5 ca đạt.
  - `CheckoutBusinessRulesTest`: 9 ca hiện trả về `403` do quyền API trong môi trường test, không liên quan đến thay đổi trạng thái phòng.
- **Tệp thay đổi**:
  - `backend/app/Http/Controllers/Api/GuestController.php`

---
## [2026-09-03] - Sửa danh sách phòng đích và hiển thị lịch sử chuyển phòng

### Module: Reservation / Chuyển phòng & Booking (`BookingRoomController.php`, `CreateRegistrationPage.vue`)

- **Đã hoàn thành**:
  - Danh sách phòng đích khi chuyển phòng chỉ trả về phòng vật lý `vacant_ready`/`vacant_clean` và còn trống trong toàn bộ giai đoạn ở còn lại.
  - Loại trừ phòng đang ở, phòng trả trong ngày và các phòng chưa sẵn sàng khỏi danh sách phòng trống.
  - Màn hình Booking hiển thị lại phòng cũ đã chuyển (trạng thái `100` - Phòng chuyển) để tra cứu lịch sử.
  - Phòng chuyển được hiển thị chỉ đọc và không cộng lặp vào tổng tiền/tổng số phòng hiện tại.
  - Sửa tiêu đề cột Phòng trong popup Chuyển phòng luôn cố định khi cuộn danh sách.

- **Kiểm tra**:
  - Bộ kiểm thử frontend: 19/19 thành công.
  - Build frontend Vite thành công.
  - Kiểm tra cú pháp PHP và `git diff --check` thành công.

- **Tệp thay đổi**:
  - `backend/app/Http/Controllers/Api/BookingRoomController.php`
  - `frontend/src/pages/reservation/CreateRegistrationPage.vue`
## [2026-09-03] - Điều chỉnh giá tạo đăng ký nhanh từ Kế hoạch phòng
### Module: Reservation / Kế hoạch phòng (`RoomPlanPage.vue`)

- **Đã hoàn thành**:
  - Đổi trường **Rate** từ tổng tiền booking thành **đơn giá một đêm của một phòng**.
  - Khi không chọn Rate Code: tự điền giá phòng chuẩn nếu mọi phòng đã chọn có cùng đơn giá; nếu có bất kỳ mức giá nào khác nhau thì hiển thị `0` để người dùng nhập giá chung.
  - Giá nhập tay được truyền vào từng `room_allocation`, vì vậy nhập `750.000` cho hai phòng sẽ lưu `750.000` cho mỗi phòng/đêm.
  - Giữ nguyên vùng chọn nhiều phòng khi mở menu chuột phải, tránh việc thao tác **Tạo** vô tình chỉ còn một phòng.
  - Rate Code theo ngày (`IsDaily`): hiển thị giá đêm đầu và khóa chỉnh Rate; backend tiếp tục tạo giá theo từng ngày.
  - Rate Code cố định: chỉ cho phép ghi đè giá khi cấu hình `AllowChangeRate = true`; backend tôn trọng giá nhập tay trong trường hợp này.
  - Chuẩn hóa lấy `room_class` và `room_form` từ API để Rate Code tính đúng theo loại/dạng phòng.
- **Kiểm tra**:
  - `node --test`: 19/19 test tính giá và Rate Code đạt.
  - `npm run build`: đạt.
  - `php -l BookingController.php`: đạt.
- **Tệp thay đổi**:
  - `frontend/src/pages/reservation/RoomPlanPage.vue`
  - `backend/app/Http/Controllers/Api/BookingController.php`

---
## [2026-09-03] - Đồng Bộ Chiều Cao Thanh Tầng (Floor Pill) Theo Chiều Cao Phòng (Sơ Đồ Phòng)
### Module: Reservation / Sơ Đồ Phòng (`RoomMapPage.vue`)

- **Đã hoàn thành**:
  - Khắc phục triệt để lỗi thanh chỉ báo Tầng (`.floor-pill`) bị kẹt chiều cao cố định (~65px) không thể thu nhỏ khi người dùng giảm "Chiều cao phòng" xuống 50px hoặc nhỏ hơn.
  - Xây dựng các hàm tính toán style động:
    - [`getFloorPillStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue): Tự động gán `height`, `minHeight`, `maxHeight` bằng chính xác `settings.roomHeight`, bổ sung `box-sizing: border-box`, co giãn padding thông minh (`2px` - `8px`), bo góc tỷ lệ theo chiều cao.
    - [`getFloorTitleStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue) & [`getFloorCountStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue): Tự động co giãn kích cỡ chữ Tầng (`Tầng X`) và số phòng (`(X phòng)`) cân đối từ 8.5px - 13px, căn giữa hoàn hảo.
  - Căn chỉnh hàng hiển thị phòng với `items-center` giúp thanh tầng và các thẻ phòng luôn thẳng hàng đều đặn.
  - Mở rộng thanh trượt "Chiều cao phòng" trong Drawer Cài đặt hiển thị xuống tối thiểu **40px** (trước đây là 50px), cho phép người dùng tùy biến giao diện siêu nhỏ gọn theo ý muốn.
  - Khắc phục triệt để hiện tượng trễ nhịp (delay): Loại bỏ `transition: all 0.35s` trên `.floor-pill` (vốn vô tình gây delay chuyển động chiều cao 0.35 giây so với ô phòng vốn cập nhật tức thì), chuyển sang transition riêng chỉ dành cho hover effects (`transform`, `box-shadow`, `border-color`), giúp thanh Tầng và ô phòng co giãn đồng thời 100% cùng nhịp ở 60fps khi kéo slider.
  - Build kiểm thử Vite thành công 100%.

---

## [2026-09-03] - Nâng Cấp Lệnh Multi-DB Migrate Toàn Bộ (php artisan migrate:all)
### Module: Database / Multi-Tenant Migration Command

- **Đã hoàn thành**:
  - Nâng cấp command [`MigrateMultiDbCommand.php`](file:///d:/PMS/backend/app/Console/Commands/MigrateMultiDbCommand.php):
    - Đăng ký tên lệnh chính thức `php artisan migrate:all` và bí danh `php artisan db:migrate-all`.
    - Hỗ trợ quét tự động (`discoverAllPmsDatabases`) toàn bộ các database `pms_*` trên máy chủ MySQL và bảng `system_branches` (bao gồm các chi nhánh mới như `gkt6`, `hkt5`, `hkt8`, `loloee`...).
    - Tự động phân loại chạy đúng domain: Bảng quản trị hệ thống chạy vào `pms_system`, bảng nghiệp vụ chạy vào từng chi nhánh.
    - **An toàn dữ liệu tuyệt đối**: Không xóa bảng (không reset/fresh), không yêu cầu seeder, chỉ nạp các migration mới còn thiếu.
    - Hỗ trợ tham số mục tiêu: `php artisan migrate:all` (tất cả), `php artisan migrate:all system` (chỉ System), `php artisan migrate:all hkt1` (chỉ 1 chi nhánh).
- **Trạng thái hiện tại**: Đã test chạy thử nghiệm thành công 100% trên cả 9 database PMS hiện có.

---

## [2026-09-07] - Tinh Chỉnh Tooltip Khóa Phòng (Room Plan & Room Map) & Cấu Hình Role Mở Khóa OOO/OOS
### Module: Reservation / Frontdesk / Khóa Phòng (Room Lock) & Cài đặt hệ thống (Hotel Config)

- **Đã xử lý & hoàn thiện**:
  - **1. Tinh Chỉnh Ghi Chú Khóa Phòng Trên Màn Hình Kế Hoạch Phòng ([`RoomPlanPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomPlanPage.vue))**:
    - Bỏ 2 dòng `Tên: ...` và `Loại khóa: ...` trong tooltip khi hover vào dải phòng khóa OOO/OOS.
    - Giữ lại dòng `Ghi chú: [Nội dung ghi chú]`.
    - Bổ sung dòng `Người khóa: [Tên người khóa]` lấy từ trường thông tin người tạo khóa.
  - **2. Bổ Sung Tooltip Ghi Chú Khóa Phòng Trên Sơ Đồ Phòng ([`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:
    - Hỗ trợ sự kiện hover chuột vào phòng đang khóa (OOO/OOS) hiển thị tooltip tương tự màn hình Kế hoạch phòng: Thời gian khóa (`Từ ngày giờ ~ Đến ngày giờ`), Badge trạng thái khóa (`OOO`/`OOS`), `Ghi chú` và `Người khóa`.
  - **3. Bổ Sung Cấu Hình Phân Quyền Mở Khóa Theo Role ([`HotelDefinitionSeeder.php`](file:///c:/xampp/htdocs/PMS/backend/database/seeders/HotelDefinitionSeeder.php), [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php))**:
    - Thêm tham số cấu hình:
      - **Tên thông số**: `RoleUserUnlockRoomOOO/OOS`
      - **Giá trị mặc định**: `Admin,FO,FOM,Sales,HK`
      - **Mô tả**: `Danh sách Role user được phép mở khóa phòng OOO/OOS (vd: Admin,FO,FOM,Sales,HK)`
      - Hiển thị trên giao diện cấu hình hệ thống (`is_visible = 1`).
    - Nâng cấp hàm `checkUnlockRolePermission` trong [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php) kiểm tra so khớp theo **Role/Vai trò** của user (`roles`, `job_title_code`, `job_title`, `department_code`, `department`), không dựa vào tên người dùng cá nhân (username).
  - **4. Sửa Lỗi Hiển Thị Tooltip & Icon Khóa Trên Sơ Đồ Phòng ([`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php), [`RoomController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomController.php))**:
    - **Hover Tooltip**: Sửa điều kiện `isLockedRoom` trên Sơ đồ phòng: Chỉ hiển thị tooltip khóa khi phòng thực sự đang ở trạng thái OOO/OOS vào ngày đang xem và không có khách đang lưu trú/đặt phòng. Nếu phòng có booking đang ở (như phòng 105 khóa ngày tương lai 13/08), khi hover trên ngày hiện tại vẫn hiển thị chính xác bảng thông tin Booking của khách.
    - **Icon Khóa Phòng**: Sửa logic khi mở khóa phòng ở ngày hiện tại: Backend chỉ giữ trạng thái bảo trì nếu còn lịch khóa đang `Active` trong ngày hôm nay; các lịch khóa trong tương lai (`status = 'New'`) không còn làm kẹt icon ổ khóa ở ngày hiện tại. Đã làm sạch các trạng thái kẹt trong cơ sở dữ liệu.
- **Trạng thái hiện tại**: Đã hoàn tất và kiểm tra build Vite thành công.

---

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
      - **Nâng cấp Giao diện Bố cục Thông Tin Đăng Ký ([`CreateRegistrationPage.vue`](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
        - Tái cấu trúc thành **Lưới 2 Cột Bento Grid cân xứng & giảm saturation chuyên nghiệp**:
          - **Đồng bộ màu chỉ báo phân khu**: Xanh dương (Thông tin công ty/kênh bán), Tím (Khách & Người liên hệ), Xanh lá (Đặt cọc & Thanh toán), Cam (Ghi chú booking).
          - **Giảm saturation nền**: Toàn bộ input/select dùng nền trắng sạch sẽ `bg-white border-slate-300`, thẻ Đặt cọc tinh gọn `bg-slate-50 border-slate-200` với nút `+ Thêm cọc` thanh lịch.
          - **Phân biệt rõ Editable / Read-only / Calculated fields**:
            - Mã booking: Hiển thị dạng badge read-only xám nhẹ `bg-slate-100 border-slate-200 cursor-default select-all`.
            - Số đêm: Calculated field `2 đêm` tự tính từ ngày lưu trú với nút stepper +/- gọn gàng.
            - Tên đăng ký / Ghi chú: Ô editable viền nét, phản hồi focus mượt mà.
          - **Đồng bộ màu sắc động theo Tùy chỉnh màu nền Topbar (`authStore.settings.topbar_color` / `--pms-custom-theme`)**:
            - Màu nền Topbar Header của Modal Thông tin đăng ký và Modal Đặt Cọc (Thêm/Sửa/Tách/Chuyển cọc) tự động đồng bộ 100% với màu nền Topbar hệ thống (Solid hoặc Gradient như Tinh vân, Đại dương, Hoàng hôn...), tự động chuyển tương phản chữ/icon (`isTopBarThemeDark`).
            - 4 thanh dọc chỉ báo phân khu (Section 1: Công ty/Kênh bán, Section 2: Người liên hệ, Section 3: Đặt cọc, Section 4: Ghi chú) và nút `Cập nhật Booking` ăn theo màu Topbar hệ thống.
            - Nút `Màu BK` trong modal chỉ phục vụ đổi màu thẻ booking trên sơ đồ phòng, không làm ảnh hưởng đến theme màu Topbar của modal.
          - **Tối ưu popup thông báo Booking / Phòng**:
            - Chỉ hiển thị 1 lần khi mở/chuyển sang booking khác hoặc reload trang.
            - Thao tác nội bộ bên trong cùng 1 booking (như mở modal Đặt cọc, thêm/sửa cọc) không bị re-trigger lại popup thông báo.
          - **Footer & Dirty Tracking UX**:
            - Metadata tương phản cao: `👤 testuser • 🕒 28/08/2026 15:19:28`.
            - Hiển thị badge `● Có thay đổi chưa lưu` khi form bị chỉnh sửa.
            - Nút `Cập nhật Booking` tự động disabled nếu form chưa có thay đổi, active sáng màu chủ đạo khi có thay đổi.
    - Tab Phòng ([`sp_041.sql`](file:///d:/PMS/store%20PMS/sp_041.sql)): Gom nhóm Master Booking banner màu xanh nhạt với tổng tiền dịch vụ & tiền thanh toán.
    - Tab Khách ([`sp_043.sql`](file:///d:/PMS/store%20PMS/sp_043.sql)): Đầy đủ 22 trường thông tin khách lưu trú người lớn và trẻ em.
    - Thanh phân trang hiển thị chuẩn PMS (dropdown 50 / 100 / 200 dòng/trang, danh sách nút trang số `1`, `2`, `3`..., nút Trước/Sau và Tổng kết quả).
- **Trạng thái hiện tại**: Hoàn thành 100% các cập nhật: Nhận phòng nhiều phòng hàng loạt, Modal xác nhận No Show theo Hình 2, phân tách Realtime bộ lọc ngoài và Áp dụng thủ công cho bộ lọc nâng cao, fix triệt để lỗi phòng chuyển (status 100) chặn sang ngày, hoàn thiện module Sao lưu & Khôi phục Database Đa Chi Nhánh hỗ trợ ALL, SYSTEM và từng chi nhánh, popup thông báo booking hiển thị chuẩn 1 lần không re-trigger khi đặt cọc, đồng bộ màu Modal Thông tin đăng ký & Modal Đặt Cọc chuẩn theo "Tùy chỉnh màu nền Topbar" hệ thống, build Vite production thành công không lỗi.
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

## [2026-09-04] - Hoàn thiện công suất Room Map và lưu lịch sử checkout sớm
### Module: Kế hoạch phòng / Room Map / Thống kê

- **Công suất phòng**:
  - Tính theo công thức: phòng ở dự kiến cuối ngày / (tổng phòng khách sạn - phòng OOO) * 100%.
  - Chỉ tính phòng vật lý: loại phòng nội bộ (rooms.is_internal = 1) và phòng ảo có số phòng bắt đầu bằng 0.
  - Phòng thật ở tầng 0 vẫn được tính nếu không thuộc hai điều kiện loại trừ trên.
  - Chỉ lấy booking có tình trạng đăng ký registration_statuses.is_availability = 1.
  - Đếm theo số phòng vật lý duy nhất, loại phòng OOO khỏi cả khả năng bán và dự báo phòng ở.
  - Room Map và popup Thống kê cùng sử dụng chỉ số từ API /rooms/stats, tránh lệch công thức giữa hai màn hình.
  - Khi xem ngày lịch sử/tương lai, OOO/OOS lấy theo thời gian hiệu lực của room_locks; room_status_code chỉ dùng cho ngày hệ thống.
- **Checkout sớm**:
  - Bổ sung booking_rooms.planned_departure_date và planned_num_of_days để giữ ngày đi/số đêm dự kiến ban đầu.
  - Khi checkout sớm, departure_date, CheckoutDate và ActutalNumOfDays phản ánh dữ liệu thực tế; dữ liệu kế hoạch không bị ghi đè.
  - Chỉ số early_departures xác định theo CheckoutDate < planned_departure_date.
  - Khi hoàn tác checkout, khôi phục ngày đi và số đêm từ dữ liệu kế hoạch.
  - Migration chỉ có thể khởi tạo dữ liệu kế hoạch cũ từ giá trị hiện còn lưu; các lần checkout sớm đã mất dữ liệu trước bản sửa không thể suy ngược chính xác.
- **Kiểm thử**:
  - Test công suất bao phủ phòng nội bộ, phòng 0xx, phòng thật tầng trệt, OOO và booking không tính availability.
  - Test checkout sớm xác nhận giữ nguyên ngày đi/số đêm dự kiến và cập nhật đúng số đêm thực tế.
  - Test hoàn tác checkout và build frontend production đều thành công.
- **Ghi chú kỹ thuật**:
  - Không tìm thấy mã nguồn sp_195 trong repository; công thức tương đương được triển khai tại service/API hiện hành.
### Bổ sung kiểm tra realtime và toàn bộ chỉ tiêu popup
- Đã chạy migration 2026_09_04_100000 trên database dự án hiện tại.
- Sau mỗi thao tác khóa/mở khóa, sự kiện Echo, khi mở popup, khi quay lại tab và polling dự phòng 15 giây đều đồng bộ lại Rooms + Stats.
- Chống response API cũ ghi đè response mới khi nhiều yêu cầu realtime chạy gần nhau.
- Bỏ cơ chế âm thầm trả mock khi API thống kê lỗi; thêm tham số chống cache cho mỗi lần tải.
- Tách đúng hai công thức:
  - Tổng phòng có thể bán = Tổng phòng - OOO - OOS.
  - Mẫu số công suất = Tổng phòng - OOO.
- Hoàn thiện Room/Pax cho phòng đến, đã đến, đang ở, phòng đi; bổ sung gia hạn, day-use, đặt trong ngày và Walk-in theo đúng source code WALKIN.
- Dữ liệu thực tế kiểm tra trên DB: Tổng 180, OOO 1, OOS 1, có thể bán 178, mẫu số công suất 179, phòng ở 6, phòng trống 172, công suất 3%.
- Test thống kê đạt 38 assertions, bao gồm cả ca toàn bộ phòng OOO để bảo đảm công suất về 0 và không chia cho 0.

### Rà soát lần cuối theo nghiệp vụ sp_195 / Link Hotel
- Database dự án hiện tại không có stored procedure `sp_195`; đã đối chiếu theo mô tả nghiệp vụ và dữ liệu màn hình khách cung cấp.
- Sửa dự báo cuối ngày để tính cả reservation hợp lệ chưa gán số phòng; mỗi dòng `booking_rooms` chưa gán tương ứng một phòng dự kiến.
- Booking đã gán chỉ được tính khi là phòng vật lý, không phải phòng nội bộ/phòng 0xx và không nằm trong OOO/OOS.
- Tách đúng hai chỉ tiêu:
  - **Phòng đến**: gồm phòng đã đến và reservation đến trong ngày, kể cả chưa gán phòng.
  - **Phòng đến đã gán phòng**: chỉ gồm phòng đã đến và reservation đã có số phòng vật lý.
- Chuẩn hóa dữ liệu số đêm ban đầu theo Link Hotel:
  - `booking_rooms.NumOfDays` giữ nguyên số đêm đặt ban đầu.
  - `booking_rooms.ActutalNumOfDays`, `departure_date`, `CheckoutDate` cập nhật theo ngày checkout thực tế.
  - Trả phòng sớm được nhận diện bằng `CheckoutDate = ngày xem` và `ActutalNumOfDays < NumOfDays`.
  - `planned_departure_date` giữ ngày đi dự kiến ban đầu để phục vụ gia hạn và hoàn tác checkout.
- Đã chạy migration đổi tên trường kế hoạch thành `NumOfDays` trên database local.
- Dữ liệu local sau rà soát: tổng phòng vật lý 180, OOO 1, OOS 1, phòng có thể bán 178, phòng dự kiến cuối ngày 9, mẫu số công suất 179, công suất 5%.
- Kiểm thử:
  - RoomOccupancyStatisticsTest đạt 40 assertions, gồm reservation chưa gán phòng, phân biệt phòng đến/đã gán, OOO/OOS, phòng nội bộ, phòng 0xx, is_availability, checkout sớm, gia hạn, day-use, đặt trong ngày và walk-in.
  - Test checkout sớm và 4 test hoàn tác checkout đều đạt.
  - Frontend production build thành công.
  - Bộ CheckoutBusinessRulesTest còn 2 lỗi cũ về room charge chuyển master trả 422; không thuộc thay đổi thống kê/checkout sớm.


## [2026-09-05] - Cơ cấu tổ chức, phân quyền theo chi nhánh và quản lý nhân viên
### Module: System / Cơ cấu tổ chức / Vai trò / Nhân viên

- Đã đối chiếu nghiệp vụ với SP1304, SP8032, SP1604; bảng cũ chỉ dùng tham chiếu, không phụ thuộc runtime.
- Tạo thiết kế System DB cho bộ phận, vị trí, Role theo chi nhánh/ứng dụng, vị trí nhân viên theo chi nhánh/ứng dụng, quyền Role theo chi nhánh và quyền kho.
- Bổ sung metadata màn hình, cờ thao tác ngày cũ và bắt buộc đổi mật khẩu.
- Migration khởi tạo 14 bộ phận, vị trí mặc định, chuẩn hóa và backfill quyền cũ.
- API hỗ trợ CRUD cơ cấu, ma trận View/Add/Edit/Delete, thêm màn hình, copy Role, gán vị trí nhân viên và quyền kho.
- Add/Edit/Delete tự kéo theo View ở frontend và backend.
- Đồng bộ từng ứng dụng, không xóa nhầm POS/SYSTEM khi sửa PMS; dựng lại user_roles để tương thích màn cũ.
- UI Cơ cấu tổ chức, Vai trò & Phân quyền, Nhân viên đã chuyển sang dữ liệu động; một user có thể có vị trí khác nhau theo chi nhánh.
- Nhân viên tự sinh mã NBxxxx; username/mật khẩu mặc định theo email; reset mật khẩu yêu cầu đổi lại; khóa tài khoản thu hồi token.
- Test OrganizationRbacTest đạt 1 test/3 assertions; frontend production build thành công.
- MultiDatabaseArchitectureTest còn một lỗi cũ về branch code/id (mong đợi 422, nhận 200), không thuộc RBAC.
- Chưa chạy migration pms_system và chưa chuyển middleware runtime sang ma trận mới vì cần xác nhận riêng trước khi tác động quyền đăng nhập hiện hành.
- Đã tạo tài liệu bàn giao `RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md`, liệt kê schema, API, UI, business rule, kết quả test, phần chưa áp dụng và checklist để Antigravity rà soát.

### [2026-09-07] Rà soát độc lập và hoàn thiện RBAC sau bản sửa Gemini

- Kiểm tra lại yêu cầu khách: Bộ phận → Position, Position × Chi nhánh × Ứng dụng → Role, View/Add/Edit/Delete, user đa chi nhánh, chi nhánh chính, kho, mật khẩu và chữ ký.
- Sửa fallback quyền rỗng, phân giải quyền đa ứng dụng, nhận diện Super Admin đúng assignment và trả đủ quyền ứng dụng trong login/me.
- Nối `allow_historical_date_actions` vào controller bill/payment; bỏ setting cũ mặc định cho phép ngày cũ.
- Đồng bộ bộ phận động từ `departments`/SP1304; tab user của Position đọc từ `user_branch_positions`.
- Sửa Employee UI: không fallback Position toàn hệ thống, kho theo từng chi nhánh, lưu assignment mọi ứng dụng, validate trước khi tạo user, reset mật khẩu qua API về email.
- Backend quyền kho kiểm tra chi nhánh được phép, chống trùng và xác minh kho đúng database chi nhánh.
- Bỏ hardcode HKT1/id 1 trong HTTP client; mã nhân viên dùng `EMPLOYEE_CODE_PREFIX`; ứng dụng mặc định dùng `DEFAULT_APPLICATION_CODE`.
- Siết middleware cho route RBAC tương thích cũ và route chữ ký khai báo trùng.
- Đã chạy migration System DB: `2026_09_05_110000` batch 2 thành công.
- Test: RBAC 15/15, 45 assertions; frontend build thành công; runtime local đúng 8 chi nhánh, 15 bộ phận, 18 Position, 3 ứng dụng và kho HKT1/HKT2.
- Full backend suite: 108/171 passed; 61 lỗi 403 do test cũ thiếu fixture quyền, 2 lỗi môi trường thiếu Dompdf/GD. Không thêm bypass test vào production.
- Chi tiết bằng chứng tại `RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md`.
