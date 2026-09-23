# TWO_PERIOD_REVENUE — Báo cáo doanh thu hai giai đoạn

## Phạm vi và nguồn

- Workbook: Dòng 161, Sheet 4.
- Legacy: sp_217 của ProVistaArmyHotel; file ../doc_baocao/sql/ProVistaArmyHotel_sp_217.sql.
- Mã report: TWO_PERIOD_REVENUE.
- Mã data source: RPT_TWO_PERIOD_REVENUE.
- Template Designer: TWO_PERIOD_REVENUE_REFERENCE.
- Procedure mới: rpt_two_period_revenue, cài bởi migration 2026_09_21_190000_create_two_period_revenue_report.php.
- API/UI dùng catalogue, executor và template renderer báo cáo hiện có; không thêm route, component hay nhánh chung.

## Quy tắc legacy đã giữ

- Chỉ lấy dịch vụ đã gắn thanh toán (service_bills.PaymentId IS NOT NULL) và có hóa đơn liên quan.
- So sánh tháng/năm của ngày dịch vụ với ngày hóa đơn; chỉ lấy các dòng khác tháng hoặc khác năm.
- Mặc định lọc ngày theo ngày hóa đơn trong khoảng p_from_date đến hết p_to_date. p_show_dep_date = 0 chuyển sang lọc ngày dịch vụ bằng p_from_date, theo hai nhánh trong sp_217; tham số này được ẩn, mặc định 1.
- Loại phương thức miễn phí bằng payment_methods.is_free, tương đương cấu hình SP1326.HTMienPhi.
- Công thức giá gốc, phí dịch vụ, thuế đặc biệt, thuế VAT và doanh thu tổng dùng các trường snapshot của service_bills và công thức vw_044.
- p_services nhận danh sách mã dịch vụ CSV để hỗ trợ chọn nhiều dịch vụ. Phòng/booking, dịch vụ, bộ phận, outlet, người dùng và thứ tự sort lấy từ các cột/runtime lookup hiện có.
- Nhóm theo ngày bật thêm nhóm DateHDBH (ngày hóa đơn); tắt thì còn nhóm Outlet → Dịch vụ.
- Đăng ký bật nhóm RegisterID2; tắt thì giữ các bill ở dạng dòng chi tiết.
- Thêm DateHDDV — ngày post dịch vụ từ service_bills.Date — sau tên khách. Bảng có 17 cột hiển thị; alias nhóm/phụ trợ không hiện thành cột.
- Tổng theo Outlet và tổng cộng gồm Giá gốc, Phí dịch vụ, Thuế đặc biệt, Thuế và Doanh thu.

## Mapping runtime có căn cứ

| Dữ liệu | Mapping runtime | Căn cứ |
|---|---|---|
| Mã hóa đơn dịch vụ, ngày dịch vụ, mô tả, bộ phận, dịch vụ, outlet, người dùng, giờ và snapshot phí/thuế | service_bills.Ma, Date, DescriptionServive, DepartmentId, ServiceId, Outlet, Username, OpenTime, TotalAmount0, Amount, ServiceCharge, SpecialTax, Tax | Migration schema service_bills; mapping tài liệu service_bills.md. |
| Ngày hóa đơn | sales_invoices.invoice_date | SalesInvoice schema/model; cột legacy SP3003.Date được lưu ở invoice_date. |
| Hóa đơn runtime | service_bills.InvoiceId = sales_invoices.id | PaymentController ghi ID nội bộ vào InvoiceId; Eloquent relations và SalesInvoiceSettlementTest xác nhận. Procedure dùng đúng khóa runtime này. |
| Phương thức thanh toán | Ưu tiên sales_invoices.pack1; nếu rỗng, gom mã phương thức từ payments.payment_method_id theo payments.invoice_id | pack1 là snapshot legacy được migration hóa đơn bổ sung; liên kết runtime payments.invoice_id được model/controller dùng. Khi một hóa đơn có nhiều phương thức, hiển thị các mã phân cách bằng dấu phẩy. |
| Mã booking/phòng, ngày đến/đi | service_bills.RegisterID2 → bookings.id; fallback RentalRoomId2 → booking_rooms.id → booking_rooms.booking_id; ngày lấy từ phòng, fallback booking | Runtime ghi các ID hiện hành này trong các controller booking/service bill. |
| Công ty | bookings.company_id (fallback service_bills.CompanyId2) → companies.id/name | Schema runtime booking/company và dữ liệu bill hiện hành. |
| Nhãn outlet và dịch vụ | outlets.code/name; hotel_services.code/name | Lookup báo cáo hiện tại dùng các mã này. Tương đương đầy đủ SP5409.Name/SP1306.Service chưa được xác minh. |

## Filter và defaults

- Ngày: tháng hiện tại ($month_start đến $month_end).
- Bộ phận: service-departments.
- Outlet: outlets.
- Dịch vụ: multi-select, lookup hotel-services.
- Người dùng: users.
- Sắp xếp: allowlist Ma, Date, InvoiceId, Room; hướng ASC/DESC.
- Mặc định nhóm theo ngày tắt; nhóm theo đăng ký tắt; chế độ lọc ngày hóa đơn bật.

## Giới hạn và điểm chưa xác minh

- Mapping import SP3000.InvoiceId → sales_invoices.id hay sales_invoices.legacy_id chưa được xác minh trong .codex/docs/database_mapping/tables/sales_invoices.md. Runtime report hiện chỉ nối bằng ID nội bộ đã xác minh (si.id = sb.InvoiceId); dữ liệu import legacy cần chốt mapping trước khi khẳng định được bao phủ.
- Lookup dùng chung hotel-services hiện chủ động loại mã RM. Vì quy tắc dự án yêu cầu xin duyệt trước khi sửa file dùng chung, migration dùng lookup sẵn có và chưa thể chọn RM từ danh sách; cần phê duyệt thay đổi riêng trong ReportLookupController nếu báo cáo phải lọc được mã này.
- hotel_services.name/outlets.name chưa được đối chiếu dữ liệu thực tế với các nhãn legacy tương ứng. Mã nhóm dịch vụ và mã outlet được giữ làm khóa nhóm.
- Hóa đơn mới có thể có nhiều phương thức thanh toán; procedure gom các mã duy nhất từ các payment còn hiệu lực thành chuỗi. Legacy sp_217 lấy một giá trị SP3003.Pack1; xác nhận khác biệt này với nghiệp vụ trước khi coi hai trường hợp split payment tương đương hoàn toàn.
- Bộ lọc ngày dịch vụ trong nhánh p_show_dep_date = 0 dùng ngày lịch (DATE(service_bills.Date) = p_from_date) vì runtime lưu Date kiểu datetime. Chưa có fixture để so sánh các giá trị giờ khác 00:00 với SQL Server.

## Trạng thái triển khai

- Đã tạo reference template Designer v1, procedure/migration và tài liệu này.
- Chưa chạy migration hoặc thực thi procedure.
- Chưa thêm hoặc chạy test theo giới hạn công việc; chưa đối chiếu browser, preview, print hay export.
- Không sửa file dùng chung. Các file mới thuộc riêng báo cáo 161.
