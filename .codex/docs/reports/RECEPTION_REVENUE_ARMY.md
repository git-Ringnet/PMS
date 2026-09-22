# RECEPTION_REVENUE_ARMY (Dòng 152)

## Nguồn legacy đã xác minh

- SQL Server: `ProVistaArmyHotel.dbo.sp_293`; bản workspace `.codex/docs/doc_baocao/sql/sp_293_full.sql` và `store PMS/sp_293_full.sql`.
- Bản Army của view: `.codex/docs/doc_baocao/sql/ProVistaArmyHotel_vw_018.sql` và `view PMS/ProVistaArmyHotel_vw_018.sql`.
- Bằng chứng nhóm/nhãn được người dùng trích xuất trực tiếp từ `SP1610`/`SP1602` ngày 2026-09-21.
- Mẫu SSMS đã ẩn danh được cung cấp trong hội thoại; chưa có tệp mẫu riêng trong repo để tự động đối chiếu.

## Runtime của PMS

- Report: `RECEPTION_REVENUE_ARMY`.
- Data source: `RPT_RECEPTION_REVENUE_ARMY`.
- Procedure: `rpt_reception_revenue_army`.
- Template mặc định: `RECEPTION_REVENUE_ARMY_REFERENCE`.
- Vị trí menu: `frontdesk` (catalog hiện chỉ chấp nhận `reservation`, `frontdesk`, `housekeeping`; chưa có vị trí riêng `cashier`).
- Filter UI: ngày dạng khoảng (`p_from_date`, `p_to_date`), một mã dịch vụ (`p_service`) và user (`p_user`). Mặc định ngày hôm nay, dịch vụ/người dùng để trống nghĩa là tất cả.
- `p_service` là scalar theo đúng legacy `@service varchar(10)`; filter so sánh mã chính xác, không phải multi-select.

## Contract đầu ra

Procedure trả đúng 21 alias legacy theo thứ tự:

`BookingId`, `Date`, `Room`, `ArrivalDate`, `DepartureDate`, `GuestName`, `DescriptionServive`, `OriginalRate`, `ServiceChargeAmount`, `SpecialTaxAmount`, `TaxAmount`, `Amount`, `PaymentMethod`, `Company`, `OpenTime`, `Description`, `DisplayName`, `ServiceId`, `FirstNameService`, `Name`, `NameVI`.

Template in 11 cột của UI; các trường `OriginalRate`, `ServiceChargeAmount`, `SpecialTaxAmount`, `TaxAmount`, `DisplayName`, `FirstNameService`, `Name`, `NameVI` vẫn có trong dataset để giữ contract và nhóm dữ liệu. Bảng có hai cấp nhóm theo `DisplayName` rồi `ServiceId`, tổng tiền theo dịch vụ, theo nhóm doanh thu và tổng cộng. Legacy `sp_293` giới hạn `DepartmentId` trong `FO/HK` độc lập với cấu hình dịch vụ; group được ghép theo mã dịch vụ.

## Cấu hình nhóm đã xác minh

| DisplayName | Service | Department | Name | NameVI |
|---|---|---|---|---|
| `RoomRevenue` | `RM` | `FO` | `Room Revenue` | `Doanh Thu Phòng` |
| `LaundryRevenue` | `LA` | `HK` | `Laundry Revenue` | `Doanh Thu Giặt Ủi` |
| `ServiceRevenue` | `BR,DO,EB,EI,EP,ER,KC,KE,LO,MB,MR,MS,PE,PU,TO,UP` | `FO` | `Service Revenue` | `Doanh Thu Dịch Vụ` |

Do schema PMS hiện chưa có bảng/cấu hình đã xác minh tương đương `SP1610`/`SP1602`, migration giữ đúng các giá trị được xác minh ở phạm vi procedure này. Chi tiết và điều kiện thay thế được ghi tại `.codex/docs/hardcoded/reception_revenue_army_report_configuration.md`.

## Mapping runtime được dùng

| Legacy | PMS mới | Căn cứ / cách dùng |
|---|---|---|
| `SP3000` | `service_bills` | Đã xác minh trong database mapping; lọc `Edit = 0`, ngày `Date`, `DepartmentId IN (FO, HK)`, mã dịch vụ cấu hình và `Username`. |
| `SP3001` | `service_bill_details` | Đã xác minh; cộng `OriginalRate`, `ServiceChargeAmount`, `SpecialTaxAmount`, `TaxAmount` theo `BillServiceId`. Nếu aggregate chi tiết rỗng thì dùng phép tính fallback theo đúng công thức `vw_018`. |
| `SP2100` | `booking_rooms` | Đã xác minh; liên kết bằng RentalRoomId gốc/hiện tại, lấy phòng, ngày đến/đi và BookingId. |
| `SP2000` | `bookings` | Đã xác minh; lấy booking, công ty theo `bookings.company_id` và ngày fallback. |
| `SP1302` | `companies` | Tên công ty theo quan hệ booking runtime đã xác minh. |
| `SP1306` | `hotel_services` | Tên dịch vụ theo mã `ServiceId`. |
| `SP3002` | `payments` | Phương thức/mô tả được nhóm distinct theo `payments.payment_id`; cách gắn bill tới PaymentID cần tiếp tục đối chiếu trên dữ liệu import thực tế. |
| `SP1322` | `hotel_settings` | Prefix mã đăng ký. |

Không thay khóa ID legacy bằng ID tự tăng của bảng khác. SQL dùng booking/room references đang lưu trên `service_bills`, không thêm khóa ngoại hoặc thay đổi schema.

## Designer và API

- Layout và kiểu bảng lưu trong `content_json`; `content_html` được tạo từ cùng cấu hình trong `backend/database/report_templates/reception_revenue_army_reference.php`.
- Header theo block columns 30% logo / 70% thông tin, divider, tiêu đề/kỳ; bảng ngang A4 11 cột; hai cấp nhóm và tổng phụ/tổng cộng nằm trong Detail Table.
- Footer có bảng tổng hợp riêng theo ảnh legacy, gồm `Doanh Thu Phòng`, `Doanh Thu Dịch Vụ` và dòng `Tổng`. Bảng lấy từ `revenue_summary`; `ReportDatasetEnricher` chỉ bổ sung nhánh cho `RPT_RECEPTION_REVENUE_ARMY`/`RECEPTION_REVENUE_ARMY`, không đổi 21 alias của procedure hoặc xử lý report khác.
- `Doanh Thu Phòng` cộng `Amount` với `DisplayName = RoomRevenue`; `Doanh Thu Dịch Vụ` là phần còn lại, gồm cả `LaundryRevenue`, để khớp bảng tổng hợp hai nhóm trên ảnh legacy. Bảng này luôn trả hai dòng kể cả khi tổng bằng 0.
- Bảng summary dùng table block Designer động, binding `row.GroupName`/`row.TotalAmount`; tổng cuối lấy `aggregate.revenue_summary.sum.TotalAmount`. `content_html` tiếp tục được sinh từ `content_json` trong provider `backend/database/report_templates/reception_revenue_army_reference.php`.
- API dùng catalog report hiện có; không có endpoint/controller/lookup dùng chung mới.
- Migration `backend/database/migrations/2026_09_21_180000_create_reception_revenue_army_report.php` đăng ký procedure, datasource, definition, template và pivot trên default DB cùng các connection branch đã cấu hình; connection trùng database được bỏ qua.

## Chưa được xác minh / giới hạn

- Cần chạy migration trên môi trường được giao và đối chiếu procedure MySQL với bản `sp_293` trên dữ liệu mẫu đã ẩn danh; hiện migration mới chỉ được tạo, chưa chạy.
- Phần liên kết `service_bills.PaymentId` với các dòng `payments.payment_id` được dựa trên luồng runtime đang có trong `BookingRoomServiceController`; dữ liệu import Army cần được đối chiếu cụ thể trước khi tuyên bố tương đương hoàn toàn.
- `sp_293` không khai báo `ORDER BY`; runtime sắp ổn định theo nhóm, dịch vụ, ngày, mã bill. Thứ tự nhóm cụ thể có thể khác thứ tự hiển thị vật lý trong SQL Server.
- Migration `2026_09_21_180000` chưa được chạy theo trạng thái workspace. Khi cài mới, migration lấy template đã cập nhật từ provider; nếu môi trường đã áp dụng migration trước đó, cần migration đồng bộ tiến về để cập nhật template đã lưu.
- Chưa nghiệm thu trên trình duyệt hoặc đối chiếu preview/in/export với SSMS; chưa chạy migration database.
