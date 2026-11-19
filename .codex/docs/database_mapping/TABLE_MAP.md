# Legacy To New Table Map

## Day Use report mapping

- `SP2100` -> `booking_rooms`: detail row, Day Use/date/status/user filters, and separate adult/baby/child counts.
- `SP2000` -> `bookings`: booking ID/status and booking note.
- `SP1000` -> `rooms`: verified `is_internal` exclusion; room number `0%` exclusion remains a legacy report rule.
- `SP1100` -> `room_classes`: room type display code.
- `SP1302` -> `companies`: company/travel agency display name.
- `SP1322` -> `hotel_settings`: booking ID prefix.

## Company occupancy report snapshot mapping (verified 2026-11-04)

- `night_audit_company_occupancy_daily_snapshots` và `night_audit_company_occupancy_snapshots` là fact runtime mới, không có bảng legacy để import trực tiếp.
- Hành vi legacy được đối chiếu với `sp_055`, `sp_078` và `func_store148`; đây là nguồn quy tắc báo cáo, không xác lập khóa import.
- Ngày trước `SystemDate` chỉ đọc snapshot sau Night Audit. Ngày hiện tại/tương lai đọc dữ liệu vận hành mới. Không được fallback lịch sử sang booking/bill live.
- Khi import lịch sử, `booking_id`/dimension runtime không được suy diễn làm khóa map legacy. Trạng thái: `Chưa xác minh` cho backfill/import snapshot cho đến khi đối chiếu được từng ngày với output legacy.

## Expected breakfast report mapping (verified 2026-09-16)

- `SP2100` -> `booking_rooms`: phòng thuê, ngày đến/đi, giờ đến, trạng thái, cờ ăn sáng và mã booking.
- `SP2200` -> `booking_room_guests`, `SP2300` -> `guests`, `SP8020` -> `nationalities`, `SP1302` -> `companies`: khách chính, số người lớn, quốc tịch và công ty.
- `SP2400`/`SP2500`/`SP2401` -> `booking_children`/`booking_room_children`/`booking_child_breakfast_details`: dòng trẻ em, lịch phòng và cờ/giá ăn sáng theo ngày; logic chi tiết tương đương `func_012`.
- `SP2102` -> `booking_room_services`, `SP1600` -> `hotel_configs`: dịch vụ ăn sáng ngoài và danh sách mã cấu hình.
- `SP4003` -> `late_checkins`, `SP3000` -> `service_bills`, `SP3004` -> `room_night_bills`: xác định phòng late check-in có room-night thực.
- `SP1311` -> `registration_statuses`, `SP1000` -> `rooms`: lọc availability strict và loại phòng nội bộ. `vw_001` là view legacy ghép `SP2000`/`SP1311`/`SP1302`, không phải bảng import riêng.
- Các mapping trên chỉ mô tả nguồn đã kiểm chứng cho report `EXPECTED_BREAKFAST`; không tạo thêm khóa import hoặc suy diễn quan hệ chưa xác minh.

- 2026-08-21: Dòng 140 runtime: `booking_room_guests.status` là trạng thái theo phòng/lần lưu trú; `guests.guest_status` được tổng hợp từ toàn bộ liên kết của khách bằng `GuestStatusSyncService`. Ý nghĩa đầy đủ của mã legacy `SP2300.GuestStatus` vẫn `Chưa xác minh` cho import production.

- 2026-08-21: Dòng 137 xác nhận `bookings.registration_status_id` chỉ lưu `registration_statuses.id`; mã legacy `SP2000.Status`/`SP1311.BookingStatusId` phải tra qua `registration_statuses.booking_status_id`. Runtime dùng `App\\Services\\RegistrationStatusMapper`; chưa có import production tự động vì chưa có nguồn legacy runtime được cấu hình.
- 2026-08-21: Dòng 137 xác nhận `bookings.registration_status_id` chỉ lưu `registration_statuses.id`; mã legacy `SP2000.Status`/`SP1311.BookingStatusId` phải tra qua `registration_statuses.booking_status_id`. Runtime dùng `App\\Services\\RegistrationStatusMapper`; chưa có import production tự động vì chưa có nguồn legacy runtime được cấu hình.
- 2026-08-19: `payments`, `payment_debt_settlements`, `booking_room_services`, `service_bills` và `housekeeping_service_bills` có thêm `user_id` nullable FK tới `users.id`; đây là khóa runtime mới, không dùng để suy diễn mapping ID legacy.
- 2026-08-19: `bookings.module` chuẩn hóa mã module `SALE/FO/HK`; backfill `reservation/reception/housekeeping` về mã tương ứng. Permission/API vẫn normalize alias cũ để tương thích import và dữ liệu tồn tại.
- 2026-08-21: `users` và các bảng quyền/tài khoản runtime dùng `mysql_system`; các cột `user_id`, `updated_by`, `sales_person_id`, `created_by` ở database chi nhánh chỉ giữ scalar ID và không dùng FK tới `users` cục bộ. Validation thao tác dùng `mysql_system.users`; các bảng quyền tương ứng trong `pms_system` vẫn giữ FK tới User dùng chung.
- 2026-08-21: Ownership migration/seed được điều khiển bởi `database_domains.php`; bảng chưa xác minh mặc định thuộc Branch DB và chưa bị di chuyển/xóa.
- 2026-09-10: Bổ sung vùng lưu nullable cho các cột report/import đã xác minh của `SP3000`, `SP3001`, `SP6000`; thêm `sales_invoices` cho tập cột `SP3003` cần bởi `sp_212`. `SP3002.Ma`, `SP3007.Ma`, `SP1302.Ma` được giữ riêng tại `legacy_id`; không đổi khóa runtime và không tạo FK từ khóa legacy chưa xác minh.

## PMS Và Cấu Hình

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `report_definitions` | Chưa xác minh | Chưa xác minh | Metadata runtime của báo cáo; `menu_locations.management` là cấu hình menu mới, không dùng làm khóa import và không suy diễn bảng legacy tương ứng. |
| `report_menu_nodes` | Chưa xác minh | Chưa xác minh | Cấu hình cây menu runtime theo module, dùng `parent_id`; không import trực tiếp và chưa có mapping legacy đã xác minh. |

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `hotel_settings` | `SP1322` | Đã xác minh | Thông tin khách sạn; giữ mã legacy nếu có nhiều cơ sở. |
| `departments` | `SP1304` | Đã xác minh | Map `SP1304.Ma` sang `departments.code`; ID mới dùng làm FK. |
| `hotel_services` | `SP1306` | Một phần | Map mã dịch vụ và tỷ lệ thuế/phí; `department_id` tra bằng mã bộ phận. |
| `department_hotel_service` | `SP1305` | Đã xác minh | Pivot bộ phận-dịch vụ; tra cả hai ID mới bằng mã legacy. |
| `shifts` | `SP1319` | Một phần | Cần xác minh quy tắc mã ca và khung giờ trước import. |
| `hotel_configs` | `SP1600` | Một phần | Chỉ import thông số có chức năng tương ứng ở hệ thống mới. Các migration `2026_09_30_110000_add_checkout_314_315_316_configs` và `2026_10_01_090000_add_service_bill_price_adjustment_role_config` bổ sung role allowlists runtime với giá trị fail-closed nếu thiếu; `ShowVirtualRoom` bổ sung mặc định `1` để bảo toàn lưới Kế hoạch phòng khi nâng cấp. `CompanyDefault` bổ sung rỗng theo từng branch, chỉ giữ `companies.id` runtime cho Booking/Nhận phòng nhanh; `SystemThemeTopbarColor` bổ sung `#006bdb` làm màu thanh hệ thống dùng chung; `CreateCompanyRequiredField` bổ sung rỗng theo từng branch, chỉ áp dụng validation runtime Công ty. Key legacy và giá trị import tương ứng `Chưa xác minh`. Không ghi đè cấu hình branch hoặc xem key mới là mapping legacy đã xác minh. |
| `branches` | Chưa xác minh | Chưa xác minh | Không suy diễn từ `branches_total`. |
| `branches_total` | Chưa xác minh | Chưa xác minh | Workbook ghi ChiNhanhKhachSan nhưng chưa có mã bảng legacy. |
| `system_branches` | Chưa xác minh | Chưa xác minh | Cần phân biệt với `branches` và `branches_total`. |
| `info_businesses` | Chưa xác minh | Chưa xác minh | Chưa tìm thấy mapping legacy đủ căn cứ. |
| `templates` | Chưa xác minh | Chưa xác minh | Không map trực tiếp sang `SP8050`; chức năng khác nhau. |
| `template_versions` | Chưa xác minh | Chưa xác minh | Dữ liệu phiên bản mới; thường không import. |
| `activity_logs` | Chưa xác minh | Chưa xác minh | Chỉ import nếu cần lịch sử thao tác. |
| `users` | `SP1800` | Một phần | Không import mật khẩu nguyên trạng; cần chiến lược tài khoản riêng. |
| `user_settings` | `SP1601` | Một phần | Chỉ map thiết lập UI được hỗ trợ. Migration `2026_10_26_121000_remove_user_topbar_color_settings` xóa riêng key JSON `settings.topbar_color` vì màu nền đã trở thành cấu hình hệ thống; không đổi các key UI khác. Key legacy tương ứng `Chưa xác minh`; không dùng key đã xóa để import. |
| `system_date_rolls` | `SP1500`, `SP8051` | Một phần | Phân biệt ngày hệ thống hiện tại và lịch sử sang ngày. |

## Phòng Và Giá

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `room_class_groups` | `SP1101` | Đã xác minh | Nhóm loại phòng. |
| `room_classes` | `SP1100` | Đã xác minh | `RoomType` → `name`, `ShortName` → `code`; giữ mã legacy để map booking. |
| `room_forms` | `SP1200` | Đã xác minh | Dạng phòng. |
| `rooms` | `SP1000` | Đã xác minh | Số phòng và trạng thái; loại trừ phòng ảo theo rule import được duyệt. `SP1000.DoNotDisturb` → `is_do_not_disturb` đã xác minh theo tên/cấu trúc cột. `SP1000.CleanRequest` → `is_housekeeping_service` và nguồn legacy của `is_priority_cleaning` là `Chưa xác minh`; không dùng các cờ này làm import key. Room `is_internal=1` không có BookingRoom đang hoạt động được dùng để tạo folio dịch vụ runtime; không đổi mapping phòng. Seeder test có thể reset `room_status_code` về `vacant_ready`; không áp dụng cho import legacy/production. |
| `standard_rates` | `SP8038` | Đã xác minh | Giá chuẩn theo loại/dạng phòng. |
| `room_statuses` | `SP1313` | Đã xác minh | Giữ đúng mã trạng thái nghiệp vụ legacy. |
| `room_status_change_logs` | `SP8065` | Đã xác minh | Giữ `SP8065.Id` tại `legacy_id`; `StatusFrom/StatusTo` map sang `room_statuses.id`, mã chưa map được giữ riêng; `business_date` là ngày nghiệp vụ dùng cho report; runtime ghi audit cho thao tác đổi trạng thái đơn/hàng loạt. |
| `room_locks` | `SP4001`, `SP4002` | Một phần | Gộp OOO/OOS; set `lock_type` theo bảng nguồn. |
| `room_do_not_move_locks` | `SP8022` | Đã xác minh | Khóa không cho chuyển/gỡ số phòng. |
| `room_rate_codes` | `SP1340` | Đã xác minh | Mã giá phòng. |
| `room_rate_plans` | `SP1341` | Đã xác minh | Chi tiết rate plan. |
| `room_rate_daily_mappings` | `SP1342` | Đã xác minh | Giá theo ngày. |
| `service_packages` | `SP1317`, `SP1330` | Một phần | Runtime tách header gói từ nhóm `PackageCode`; cấu trúc header và khóa import chưa xác minh, không import tự động. |
| `service_package_items` | `SP1317` | Một phần | `PackageCode`, `DetailCode`, `ServiceCode`, số lượng, giá, thuế/phí, `OrderDay`, `Outlet`, ghi chú có tương ứng theo cấu trúc; khóa gói/header và quy tắc import còn chưa xác minh. |

## Booking Và Khách

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `booking_statuses` | `SP1309` | Đã xác minh | Các mã `0,1,2,3,4,100` phải được giữ nguyên. |
| `registration_statuses` | `SP1311` | Đã xác minh | Map `BookingStatusId`, availability và cutoff. |
| `bookings` | `SP2000` | Đã xác minh | Cần giữ legacy ID để map toàn bộ bảng con; `module` runtime lưu mã `SALE/FO/HK`; `is_service_only` là cờ runtime không map legacy. Migration/API chỉ khởi tạo Booking kỹ thuật cho Room `0%` có `is_internal=1` và chưa có folio; ID/name mới không đại diện bản ghi SP2000 và không thay import key. |
| `booking_rooms` | `SP2100` | Đã xác minh | Giữ legacy room-rental ID; map booking/phòng theo dữ liệu legacy; `Username` → `created_by`; migration/API khởi tạo dòng folio runtime cho Room `0%` có `is_internal=1` chưa có liên kết active, không gắn bill legacy; `room_class_id` lấy từ Room và không ghi đè dữ liệu import hiện có. |
| `guests` | `SP2300` | Đã xác minh | Giữ mã khách legacy; chuẩn hóa danh mục quốc tịch/giấy tờ riêng. |
| `booking_room_guests` | `SP2200` | Đã xác minh | Map phòng thuê và khách bằng legacy ID. |
| `booking_children` | `SP2400` | Một phần | Hồ sơ trẻ em; giữ mã trẻ legacy để liên kết dữ liệu phòng và ăn sáng. |
| `booking_room_children` | `SP2500` | Một phần | Liên kết trẻ em-phòng thuê và lịch sử đến/đi; các cột original/breakfast/infant chưa xác minh đầy đủ. |
| `booking_child_breakfast_details` | `SP2401` | Đã xác minh | Giữ ngày, giá phụ thu và FIT/GIT. |
| `booking_room_services` | `SP2102` | Đã xác minh | RM/EB/BD/dịch vụ setup; giữ `IsRoom`, ngày, trạng thái post và mã bộ phận `FO/HK`; RM theo ngày là nguồn tính tiền dự kiến của báo cáo No-show và dịch vụ bổ sung dùng `service_date` để lọc. |
| `special_requests` | `SP1325` | Đã xác minh | Danh mục yêu cầu đặc biệt. |
| `booking_room_special_requests` | `SP2107` | Đã xác minh | Pivot phòng thuê-yêu cầu. |
| `cancel_reasons` | `SP1334` | Đã xác minh | Danh mục lý do hủy. |
| `booking_cancel_logs` | `SP8052`, `SP8053` | Một phần | Đã xác minh dữ liệu ngày/giờ, lý do, username và loại room/booking; khóa legacy-to-new, ca và user/reason ID vẫn chưa xác minh. |
| `late_checkins` | `SP4003` | Đã xác minh | Phòng late check-in. |
| `noshow_logs` | `SP4000` | Đã xác minh | Phòng no-show. |
| `nationalities` | `SP8020` | Đã xác minh | Map theo mã quốc tịch, không theo tên hiển thị. |
| `companies` | `SP1302` | Đã xác minh | Công ty/đối tác; `SP1302.Ma` lưu nullable tại `legacy_id`, không thay `companies.id`. Import vận hành XLSX/XLS/CSV của màn hình chỉ xử lý danh mục theo Mã/Tên, không xác lập khóa import production mới. |
| `bookers` | `SP1328` | Đã xác minh | Người liên hệ. |
| `markets` | `SP1308` | Đã xác minh | Market segment. |
| `customer_sources` | `SP8037` | Đã xác minh | Source code. |

## Hóa Đơn Và Thanh Toán

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `service_bills` | `SP3000` | Đã xác minh | Giữ `Ma`, ngày dịch vụ, mã bộ phận `DepartmentId`, thuế/phí snapshot, chủ sở hữu gốc/hiện tại, status và audit chuyển/hủy; `employee_code` là runtime nullable, không suy diễn khi import. |
| `service_bill_details` | `SP3001` | Đã xác minh | Giữ khóa kép `BillServiceId + Ma` và chi tiết thuế/phí. |
| `payments` | `SP3002` | Đã xác minh | `SP3002.Ma` lưu tại `legacy_id`; giữ DPR/AP, owner hiện tại, payment/reversal, VAT và snapshot AC cần cho `sp_212`. |
| `sales_invoices` | `SP3003` | Một phần | Lưu tập cột header đã xác minh cần cho `sp_212`; các owner/key legacy là scalar nullable, chưa tạo FK runtime. |
| `room_night_bills` | `SP3004` | Đã xác minh | `BillId` → `bill_id`; `IsRoomNight` → `is_room_night`. |
| `payment_debt_settlements` | `SP3007` | Một phần | `SP3007.Ma` lưu tại `legacy_id`, `PaymentTableID` giữ tại `legacy_payment_table_id`; liên kết runtime cần đối chiếu khi import. |
| `payment_methods` | `SP1326` | Đã xác minh | Giữ mã phương thức; nhóm tiền mặt/thẻ/công nợ/miễn phí. |
| `currencies` | `SP1329` | Đã xác minh | Hiện runtime chủ yếu VND nhưng vẫn giữ mã. |
| `units_of_measure` | `SP5401` | Đã xác minh | Dùng chung cho sản phẩm/dịch vụ. Export Menu HK chỉ xuất mã/tên làm sheet tra cứu; import runtime xác thực mã, không import/thay đổi danh mục này. |

## Housekeeping

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `housekeeping_service_bills` | `SP6000` | Đã xác minh | Giữ mã bill HK và liên kết `SP3000` qua `BillServiceId`. |
| `housekeeping_service_bill_details` | `SP6001` | Đã xác minh | Giữ khóa kép bill-detail và thông tin giảm/tăng giá. |
| `housekeeping_outlets` | `SP5409` | Một phần | Chỉ import outlet HK; cờ hiển thị và ba tỷ lệ mặc định là runtime mới, không suy luận từ `SP5409`/`SP5410`. |
| `product_categories` | `SP6002` | Đã xác minh | Nhóm sản phẩm HK. Export Menu HK dùng `product_categories.id` runtime làm Mã Nhóm tham chiếu; import runtime chỉ xác thực ID, không tạo/sửa nhóm. |
| `products` | `SP6005` | Đã xác minh | Sản phẩm HK; giữ `OpenKey`, giá, thuế và đơn vị. Import/export Excel runtime không xác lập khóa import production từ `SP6005`; cột `products.currency` đang lưu mã đơn vị tính cho Menu HK. |
| `inventories` | `SP6007` | Một phần | Cần xác minh mô hình kho mới trước import tồn. |
| `lost_and_found_items` | `SP1333` | Đã xác minh | Lost & Found. |

## F&B

| Bảng mới | Bảng legacy | Trạng thái | Ghi chú import |
|---|---|---|---|
| `outlets` | `SP5409` | Đã xác minh | Chỉ outlet F&B. |
| `fb_locations` | `SP5405` | Đã xác minh | Khu vực theo outlet. |
| `fb_tables` | `SP5400` | Đã xác minh | Bàn theo location. |
| `fb_product_categories` | `SP5404` | Đã xác minh | Nhóm món. |
| `fb_products` | `SP5300` | Đã xác minh | Danh mục món. |
| `fb_product_outlets` | `SP5410` | Đã xác minh | Giá và mở bán theo outlet. |
| `fb_combos` | `SP5408` | Đã xác minh | Quan hệ món cha-con combo. |
| `fb_printers` | `SP5415` | Đã xác minh | Cấu hình máy in; cần cấu hình lại thiết bị thực tế. |
| `fb_print_logs` | `SP5414` | Một phần | Chỉ import nếu cần lịch sử in/chuyển bàn. |
| `fb_orders` | `SP5000` | Đã xác minh | Bill F&B; bảo toàn trạng thái thanh toán/xóa. |
| `fb_order_items` | `SP5100` | Đã xác minh | Chi tiết món và trạng thái hủy. |
| `fb_promotions` | `SP5403` | Một phần | Cần xác minh loại đơn hàng và promotion trước import. |
| `fb_promotion_products` | Chưa xác minh | Chưa xác minh | Chưa xác định chắc bảng pivot legacy. |
| `fb_parties` | `SP5424` | Một phần | Workbook có mapping nhưng schema legacy local chưa có file `SP5424.md`. |
| `fb_sub_parties` | `SP5422` | Đã xác minh | Tiệc con. |
| `fb_party_items` | `SP5122` | Một phần | Workbook có mapping nhưng schema legacy local chưa có file `SP5122.md`. |
| `fb_party_payments` | `SP3002` | Một phần | Thanh toán tiệc dùng chung payment legacy; tránh import trùng. |

## Bảng Hạ Tầng Không Import Trực Tiếp

| Bảng mới | Trạng thái | Ghi chú |
|---|---|---|
| `migrations` | Không import | Laravel quản lý. |
| `cache` | Không import | Dữ liệu tạm. |
| `cache_locks` | Không import | Khóa cache runtime. |
| `jobs` | Không import | Hàng đợi runtime. |
| `job_batches` | Không import | Batch hàng đợi runtime. |
| `failed_jobs` | Không import | Log job lỗi của runtime mới. |
| `sessions` | Không import | Phiên đăng nhập mới. |
| `password_reset_tokens` | Không import | Dữ liệu xác thực mới. |
| `personal_access_tokens` | Không import | Token xác thực mới. |
