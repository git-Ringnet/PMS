# REVENUE_ARMY — Row 150

## Trạng thái triển khai

- Đã tạo reference template Designer v1 tại `backend/database/report_templates/revenue_army_reference.php`.
- Chưa đăng ký report/data source và chưa tạo MySQL procedure. Việc tích hợp bị chặn cho tới khi xác minh được mapping khóa owner và số tiền giữa dữ liệu runtime/import với các nguồn legacy bên dưới.
- Chưa chạy migration, preview, export hay đối chiếu dữ liệu.

## Định danh và nguồn legacy

- Report code: `REVENUE_ARMY`; data source dự kiến: `RPT_REVENUE_ARMY`; template: `REVENUE_ARMY_REFERENCE`.
- Nguồn thẩm quyền: `store PMS/sp_292_full.sql`, `function_sql/ProVistaArmyHotel_func_054.sql`, `function_sql/ProVistaArmyHotel_func_076.sql`, `function_sql/ProVistaArmyHotel_func_077.sql`.
- Store Army `sp_292` có tham số ngày, công ty và booking. `@prefix` được khai báo nhưng không được dùng trong kết quả.
- `sp_292` gọi `func_054` cho ngày báo cáo và khoảng từ ngày đến sớm nhất của các booking khớp tới ngày báo cáo. `PrevDay` là doanh thu của các ngày trong khoảng đó trước ngày báo cáo, không phải cửa sổ cố định 30 ngày.
- `func_054` tách dữ liệu theo `SP1500.SystemDate`: ngày trước ngày hệ thống dựa trên bill thực; từ ngày hệ thống trở đi tính tiền phòng/dịch vụ dự kiến dựa trên phòng, giá, cấu hình tự động và quy tắc khuyến mại/voucher, qua `func_076` và `func_077`.
- Các nhóm doanh thu trong `sp_292`: `RM` tiền phòng; `EB,EP,ER,KC,UP,EI,LO` phụ trội phòng; `BD,BF` phụ thu ăn sáng; `LA` giặt; `MB` minibar; `BR` bể vỡ; `FB` nhà hàng; mã còn lại trừ các nhóm trên vào dịch vụ khác. `BK` không thuộc nhóm bể vỡ trong store này.
- Payment legacy: `CA` tiền mặt; `BT`/`CD` chuyển khoản; `AC` công nợ; `HH` hoa hồng. Các mã khác không được khấu trừ khi tính `InhouseRoom`.
- `InhouseRoom = max(0, TotalRevenue - Cash - BankTransfer - CityLedger - Commission)`.
- Kết quả store gồm 22 trường. Template có thêm `Index` làm số thứ tự trình bày; trường này không có trong result set legacy.

## Hợp đồng template hiện tại

- A4 ngang; lề 8/5/8/5 mm.
- Bộ lọc dự kiến theo tài liệu legacy: `p_date` (mặc định `$today`), `p_company_id` (0 = tất cả), `p_booking_id` (0 = tất cả).
- Header hai tầng, nhóm thu trong ngày, nhóm phương thức thanh toán, tổng cuối bảng và chữ ký được cấu hình trong `content_json`; `content_html` được biên dịch từ các Designer blocks cùng file.
- Template hiện dùng alias gần với result set legacy (`Ma`, `BookingName`, `Company`, các cột doanh thu và thanh toán) để tránh tuyên bố đã có procedure chuyển đổi.

## Mapping runtime và điểm chưa xác minh

| Legacy source | Ứng viên runtime | Tình trạng |
|---|---|---|
| `SP2000` booking | `bookings` | Bảng nghiệp vụ tương ứng đã được ghi nhận; khóa booking legacy so với khóa runtime ở dữ liệu import chưa xác minh đủ cho join report. |
| `SP2100` room rental | `booking_rooms` | Bảng nghiệp vụ tương ứng đã được ghi nhận; xác minh riêng mapping `Ma`/`BookingId` cho cả dữ liệu hiện tại và import trước khi dùng làm owner join. |
| `SP1302` company | `companies` | Bảng tương ứng đã xác minh; filter phải dùng khóa công ty trong booking runtime, không giả định mã company trong bill là FK runtime. |
| `SP3000` service bills | `service_bills` | Nhiều cột legacy được giữ nguyên. Tuy nhiên `sp_292/func_054` cộng `TotalAmount0`; runtime mới thường ghi `Amount`, còn `TotalAmount0` nullable. Nguồn số tiền thống nhất cho report chưa xác minh. |
| `SP3004` room-night metadata | `room_night_bills` | Mapping `BillId` và `IsRoomNight` đã xác minh cho một số luồng; chưa xác minh đủ dữ liệu theo ngày/rate để thay toàn bộ nhánh `func_054`. |
| `SP2102` automatic room services | `booking_room_services` | Mapping khái niệm đã xác minh; chưa chứng minh các dòng runtime bao phủ đầy đủ promotion, voucher, extra bed/child và các ngoại lệ mà `func_054`/`func_077` tính. |
| `SP3002` payments | `payments` | Bảng/các mã payment có tài liệu mapping; mapping khóa booking/phòng và cách tính cùng snapshot `TotalAmount0` của mọi dòng legacy chưa xác minh cho report này. |
| `SP1500.SystemDate` | `system_date_rolls` | Có quan hệ chức năng với ngày hệ thống nhưng tương đương dữ liệu/phạm vi Army cần được xác nhận trước khi tái tạo điểm cắt quá khứ/dự kiến. |
| `func_076`/`func_077` | room-rate plans, room services và các dữ liệu phụ trợ | Chưa xác minh phép ánh xạ đầy đủ cho công thức giá, phụ trội, package, voucher và dịch vụ gán theo phòng. |

Không thêm join hoặc fallback `COALESCE` giữa các cột tiền chỉ dựa trên tên tương tự. Việc dùng `Amount` thay `TotalAmount0`, hoặc coi khóa legacy là ID runtime, có thể làm sai số liệu và import.

## Tệp liên quan và phạm vi

- Reference template riêng: `backend/database/report_templates/revenue_army_reference.php`.
- Tài liệu evidence gốc: `.codex/docs/doc_baocao/dong_150_bao_cao_doanh_thu_army.md` và thư mục `.codex/docs/doc_baocao/sql/`.
- Chưa sửa file dùng chung, schema, model, controller, route, adapter hoặc Designer toàn cục.
- Chưa thêm entry trong `.codex/PROJECT_MEMORY.md`; đây là ghi chú của report riêng để người phụ trách tích hợp tiếp tục sau khi mapping được xác minh.
