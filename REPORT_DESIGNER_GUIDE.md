# Hướng dẫn Mẫu in và Báo cáo động

Tài liệu này mô tả kiến trúc, quy ước và quy trình tạo mẫu in nghiệp vụ hoặc báo cáo động trong PMS. Mục tiêu là để developer/DBA có thể viết thêm hoặc chỉnh sửa truy vấn trong MySQL Stored Procedure mà không phải hard-code câu truy vấn vào Laravel hoặc Vue.

> Phạm vi hiện tại: MySQL Stored Procedure, một result set, input parameter loại `IN`, thiết kế dạng Header/Detail/Footer và preview bằng dữ liệu thật.

## 0. Đọc phần này trước: hệ thống đang quản lý những gì?

Màn hình **Trung tâm mẫu & báo cáo** có ba khu vực. Ba khu vực liên quan với nhau nhưng không thay thế cho nhau:

| Khu vực | Câu hỏi khu vực này trả lời | Dữ liệu chính | Có tự xuất hiện thành báo cáo không? |
|---|---|---|---:|
| **1. Thư viện thiết kế** | Kết quả sẽ được trình bày như thế nào? | Template: bố cục, HTML, CSS, khổ giấy, field binding | Không |
| **2. Cấu hình mẫu in** | Khi bấm In ở một nghiệp vụ, khách sạn muốn dùng thiết kế nào? | Print slot → một template được chọn | Không phải báo cáo có bộ lọc |
| **3. Danh mục báo cáo** | Báo cáo nào được chạy, dùng Store nào, hỏi người dùng những điều kiện gì và nằm ở menu nào? | Report definition → Data Source + tham số + các template | Có, nếu được kích hoạt |

### 0.1. Thư viện thiết kế là gì?

**Thư viện thiết kế** là kho chứa các mẫu đầu ra tái sử dụng. Mỗi dòng trong thư viện là một bản thiết kế thuộc bảng `templates`.

Một thiết kế chứa:

- Tên và nhóm mẫu, ví dụ `Báo cáo phòng`, `Breakfast Ticket`, `Invoice`.
- Khổ giấy, chiều giấy và lề.
- Các block Header, Detail, Footer và cấu trúc bảng.
- HTML/CSS được compile từ cấu trúc kéo thả.
- Các binding như `{{hotel.name}}`, `{{parameters.p_from_date}}`, `{{row.RoomNumber}}`.
- Data Source dùng để lấy danh sách field khi thiết kế, nếu đây là mẫu báo cáo chạy Store.
- Phiên bản và lịch sử lưu.

Thư viện **không quyết định**:

- Mẫu có xuất hiện trên menu Báo cáo hay không.
- Form bên trái có các ô lọc nào.
- Store được chạy khi người dùng bấm **Hiển thị báo cáo** trong một báo cáo cụ thể.
- Mẫu nào là mặc định của một báo cáo hoặc một chức năng in.

Vì vậy, bấm **Tạo mẫu mới** chỉ tạo thêm một bản thiết kế. Muốn người dùng nhìn thấy nó trong Report Viewer, phải gán nó vào một mục ở **Danh mục báo cáo**. Muốn một nút In nghiệp vụ sử dụng nó, phải chọn nó ở **Cấu hình mẫu in**.

Các thao tác chính trong Thư viện thiết kế:

1. Chọn **Nhóm mẫu** ở cột trái.
2. Bấm **Tạo mẫu mới**, nhập tên.
3. Bấm **Sửa thiết kế** để chọn Store, kéo field, sửa bố cục, HTML/CSS và xem trước.
4. Dùng nút sao chép để nhân một mẫu chuẩn rồi điều chỉnh cho khách sạn khác.
5. Lưu draft trong lúc làm; khi ổn định thì lưu phiên bản.

> Nhóm mẫu chủ yếu dùng để tổ chức thư viện và giới hạn lựa chọn cho các vị trí mẫu in. Nhóm mẫu không phải là nhóm menu báo cáo. Hai nơi có thể cùng tên nhưng mang ý nghĩa khác nhau.

### 0.2. Danh mục báo cáo là gì?

**Danh mục báo cáo** là nơi tạo một chức năng báo cáo hoàn chỉnh. Mỗi mục thuộc bảng `report_definitions`.

Một mục danh mục báo cáo chứa:

- `code`: mã ổn định dùng trên URL và trong code, ví dụ `ARRIVING_ROOMS`.
- `name`: tên người dùng nhìn thấy, ví dụ **Báo cáo phòng đến**.
- `group`: nhóm menu, ví dụ **Báo cáo phòng**.
- `report_data_source_id`: nguồn Store cần chạy.
- `parameter_ui_schema`: cách hiển thị từng tham số Store thành ô nhập bên trái.
- Danh sách một hoặc nhiều template đầu ra được phép chọn.
- Một template mặc định.
- Trạng thái kích hoạt.
- Cấu hình vị trí và thứ tự trên menu hệ thống.

Danh mục báo cáo không chứa SQL và không chứa trực tiếp HTML/CSS. Nó đóng vai trò ghép các thành phần có sẵn lại:

```text
Report Definition
  ├── chạy 1 Report Data Source
  │     └── gọi 1 Stored Procedure rpt_*
  ├── sinh form từ parameter_ui_schema
  ├── cho chọn 1 hoặc nhiều Template đầu ra
  └── quyết định nhóm/vị trí trên menu
```

Ví dụ mục **Báo cáo phòng đến**:

```text
Mã báo cáo:       ARRIVING_ROOMS
Tên hiển thị:     Báo cáo phòng đến
Nhóm menu:        Báo cáo phòng
Nguồn dữ liệu:    ARRIVING_ROOMS → rpt_arriving_rooms
Form điều kiện:   Từ ngày, Đến ngày, Khu vực, Công ty, Đăng ký...
Mẫu đầu ra:       Báo cáo phòng đến - Mẫu tham chiếu
Menu:             Đăng ký phòng / Lễ tân
```

Khi mục này được kích hoạt, Report Viewer đọc cấu hình và tự thực hiện:

```text
Người dùng mở /reports?report=ARRIVING_ROOMS
                         ↓
Viewer đọc report_definitions
                         ↓
Sinh form bên trái từ parameter_ui_schema
                         ↓
Người dùng bấm "Hiển thị báo cáo"
                         ↓
Backend gọi Data Source → CALL rpt_arriving_rooms(...)
                         ↓
Nhận dataset rows/fields/summary
                         ↓
Render dataset bằng template mặc định
                         ↓
Hiển thị báo cáo, cho đổi mẫu, in/PDF hoặc tải CSV
```

### 0.3. Quan hệ giữa Store, Data Source, Template và Danh mục báo cáo

Đây là bốn lớp khác nhau:

| Lớp | Nơi lưu | Trách nhiệm | Ví dụ |
|---|---|---|---|
| Stored Procedure | MySQL | Chứa truy vấn và logic lọc dữ liệu | `rpt_arriving_rooms` |
| Report Data Source | `report_data_sources` | Đăng ký Store, lưu metadata tham số/field và tham số mẫu | `ARRIVING_ROOMS` |
| Template | `templates` | Trình bày dataset thành trang báo cáo | `Báo cáo phòng đến - Mẫu tham chiếu` |
| Report Definition | `report_definitions` | Tạo chức năng báo cáo, form, menu và chọn template | `ARRIVING_ROOMS` |

Quan hệ tổng quát:

```text
MySQL Stored Procedure
          1
          │ được đăng ký bởi
          ▼
Report Data Source
       1  │
          ├──────────────► nhiều Template có thể dùng để thiết kế
          │
          └──────────────► nhiều Report Definition có thể dùng
                                      │
                                      └── nhiều Template đầu ra
```

Một báo cáo phải có đúng một Data Source nhưng có thể có nhiều mẫu đầu ra. Ví dụ cùng dữ liệu phòng đến có thể có mẫu A4 dọc, A4 ngang hoặc mẫu riêng của từng khách sạn. Người dùng chọn mẫu ở ô **Mẫu đầu ra** mà không cần chạy lại Store; hệ thống render lại dataset đã tải.

Một template có thể được gán cho nhiều báo cáo. Tuy nhiên, các field mà template sử dụng phải tồn tại trong dataset của Data Source tương ứng. Thực tế vận hành nên gán template cho đúng nguồn đã dùng khi thiết kế để tránh các binding `row.*` bị rỗng.

### 0.3.1. Vì sao thấy chọn Store ở cả Designer và Danh mục báo cáo?

Đây là hai lựa chọn phục vụ **hai thời điểm khác nhau**, không phải hai Store khác nhau:

| Nơi chọn | Mục đích | Có quyết định Store khi người dùng bấm “Hiển thị báo cáo” không? |
|---|---|---:|
| **Thư viện thiết kế / Designer** | Cấp field để kéo thả, kiểm tra binding `row.*` và preview khi đang làm bố cục | Không |
| **Danh mục báo cáo** | Xác định Store chạy thật, sinh form tham số bên trái và dataset của Report Viewer | Có |

Ví dụ mẫu **Báo cáo phòng đến - Mẫu tham chiếu** chọn Data Source `ARRIVING_ROOMS` trong Designer để nhìn thấy các field như `row.BookingId`, `row.Room`, `row.GuestName`. Khi tạo mục danh mục **Báo cáo phòng đến**, mục này cũng chọn `ARRIVING_ROOMS`; đây là nguồn chạy thật để Viewer gọi `rpt_arriving_rooms(...)`.

Danh mục báo cáo cần giữ Data Source riêng vì một báo cáo có thể cho phép nhiều mẫu đầu ra cùng dùng một dataset:

```text
Report Definition: ARRIVING_ROOMS
  └── Data Source chạy thật: ARRIVING_ROOMS
        ├── Mẫu A4 dọc
        ├── Mẫu A4 ngang
        └── Mẫu riêng cho khách sạn A
```

Khi người dùng đổi **Mẫu đầu ra** trên Viewer, hệ thống chỉ render lại dataset đã tải bằng mẫu khác; Store không chạy lại. Nếu chỉ để Store nằm trong template, Viewer sẽ khó xác định một nguồn dữ liệu và một form tham số chung cho nhiều mẫu.

#### Quy tắc cấu hình để không bị chọn trùng hoặc chọn nhầm

1. **Chọn Data Source tại Danh mục báo cáo trước**. Đây là nguồn chính thức của báo cáo.
2. Chỉ gán các template được thiết kế với cùng Data Source đó. Ví dụ `ARRIVING_ROOMS` chỉ gán mẫu dùng `ARRIVING_ROOMS`.
3. Không gán mẫu `Registration Card`, `Invoice`, `Breakfast Ticket` hoặc mẫu dùng Store khác vào Báo cáo phòng đến, dù hệ thống có hiển thị trong danh sách chọn.
4. Nếu cần một giao diện mới nhưng cùng dữ liệu, nhân bản template phòng đến, giữ nguyên Data Source và thay đổi bố cục/cột/khổ giấy.
5. Nếu cần một báo cáo dùng Store khác, tạo Data Source và Report Definition mới; không đổi Store của mẫu cũ rồi gán lẫn vào báo cáo cũ.

> Cải tiến giao diện nên thực hiện sau: khi đã chọn Data Source tại Danh mục báo cáo, danh sách **Thiết kế hiển thị bên phải của Viewer** chỉ nên hiển thị template cùng `report_data_source_id`. Khi mở Designer từ một báo cáo, Store nên được tự điền hoặc chỉ đọc. Điều này làm giảm thao tác lặp lại nhưng không thay đổi kiến trúc dữ liệu.

### 0.4. Khi nào dùng khu vực nào?

| Mong muốn | Thao tác đúng |
|---|---|
| Chỉnh logo, màu, tiêu đề, cột hoặc khổ giấy của báo cáo | Sửa template trong **Thư viện thiết kế** |
| Tạo thêm mẫu A4 ngang nhưng dùng cùng dữ liệu | Nhân bản template trong **Thư viện thiết kế**, rồi gán thêm ở **Danh mục báo cáo** |
| Thêm/bớt điều kiện lọc hoặc đổi loại ô nhập | Sửa Store nếu cần tham số mới, đồng bộ Data Source, rồi sửa `parameter_ui_schema` trong **Danh mục báo cáo** |
| Thay đổi JOIN, công thức hoặc nguồn bảng | Sửa Stored Procedure trong MySQL, sau đó đồng bộ Data Source |
| Thêm một báo cáo hoàn toàn mới | Tạo Store → Data Source → Template → Report Definition |
| Đưa báo cáo sang nhóm menu khác | Sửa `group` và vị trí menu trong **Danh mục báo cáo** |
| Ẩn báo cáo khỏi menu nhưng chưa muốn xóa | Tắt **Hiện trên menu** hoặc tắt kích hoạt trong **Danh mục báo cáo** |
| Chọn mẫu Phiếu ăn sáng/Hóa đơn được dùng khi bấm In | **Cấu hình mẫu in** |

### 0.5. Điều gì xảy ra khi sửa hoặc xóa?

- Sửa một template sẽ làm thay đổi đầu ra ở mọi báo cáo/mẫu in đang sử dụng template đó sau khi phiên bản mới được lưu.
- Xóa một Report Definition chỉ xóa mục báo cáo, form và liên kết menu; Store, Data Source và các template vẫn được giữ.
- Template đang được Mẫu in hoặc Danh mục báo cáo sử dụng không thể bị xóa cho đến khi gỡ liên kết.
- Data Source đang được template hoặc Report Definition sử dụng sẽ bị API từ chối xóa với HTTP `409`; cần chuyển hoặc gỡ các liên kết trước.
- Đổi alias output của Store có thể làm template mất dữ liệu. Luôn đồng bộ schema và sửa binding sau khi đổi alias.
- `is_active=false` loại báo cáo khỏi danh sách Viewer vì Viewer gọi `GET /api/report-definitions?active_only=1`.
- `show_in_menu=false` chỉ ẩn lối tắt trên menu; Report Definition vẫn còn để quản trị hoặc mở theo luồng khác nếu đang kích hoạt.

### 0.6. Quy trình chuẩn để báo cáo xuất hiện trên giao diện

Một template nằm trong Thư viện thiết kế **chưa đủ** để xuất hiện thành báo cáo. Cần đủ toàn bộ chuỗi sau:

1. Store `rpt_*` tồn tại trong đúng database chi nhánh và chạy được.
2. Store được đăng ký thành một Data Source đang hoạt động.
3. Có ít nhất một template thiết kế đúng các field của Data Source.
4. Có một Report Definition chọn Data Source đó.
5. Report Definition đã cấu hình đủ các tham số Store trong `parameter_ui_schema`.
6. Template đã được tick trong danh sách mẫu đầu ra và có một mẫu mặc định.
7. Report Definition đang **Kích hoạt**.
8. Nếu muốn xuất hiện trên menu: bật **Hiện trên menu** và chọn ít nhất một khu vực.
9. Tải lại giao diện để menu đọc cấu hình mới.

Nếu chỉ hoàn thành bước 1–3, mẫu chỉ nằm trong Thư viện thiết kế và chưa có mục nào cho người dùng chạy báo cáo.

## 1. Tổng quan kiến trúc

```text
Database MySQL của chi nhánh
  └── Stored Procedure rpt_*
        ├── Input parameters
        └── Một SELECT result set
                 │
                 ▼
Report Procedure Catalog
  ├── Đọc Store từ INFORMATION_SCHEMA.ROUTINES
  └── Đọc parameters từ INFORMATION_SCHEMA.PARAMETERS
                 │
                 ▼
Report Data Source
  ├── Tên Store
  ├── Parameter schema
  ├── Field schema
  └── Sample parameters
                 │
                 ▼
Report Designer
  ├── Kéo field vào Header/Detail/Footer
  ├── Lưu cấu trúc JSON
  └── Preview bằng CALL rpt_*(...)
```

Nguyên tắc quan trọng:

- Stored Procedure là nơi chứa toàn bộ truy vấn nghiệp vụ báo cáo.
- Laravel không chứa câu `SELECT`, `JOIN` hoặc điều kiện riêng của từng report.
- Laravel chỉ discover metadata, validate parameters và thực thi `CALL` bằng parameter binding.
- Template tham chiếu Data Source và field alias, không tham chiếu trực tiếp bảng nghiệp vụ.
- Mỗi request chạy trên database chi nhánh đang được chọn bằng `X-Branch-Code` và `X-Branch-Id`.

## 2. Thành phần trong mã nguồn

### Backend

| Thành phần | Vị trí | Trách nhiệm |
|---|---|---|
| Cấu hình report | `backend/config/reporting.php` | Prefix Store và giới hạn số dòng |
| Procedure catalog | `backend/app/Services/Reports/ReportProcedureCatalogService.php` | Discover Store và parameters |
| Data executor | `backend/app/Services/Reports/ReportDataExecutorService.php` | Thực thi `CALL`, lấy rows và fields |
| Catalog API | `backend/app/Http/Controllers/Api/ReportProcedureController.php` | Danh sách, metadata và chạy thử Store chưa đăng ký |
| Data Source API | `backend/app/Http/Controllers/Api/ReportDataSourceController.php` | CRUD, chạy thử và refresh schema |
| Data Source model | `backend/app/Models/ReportDataSource.php` | Lưu cấu hình nguồn báo cáo |
| Template API | `backend/app/Http/Controllers/Api/TemplateController.php` | Lưu liên kết Data Source và preview report |
| Routes | `backend/routes/api.php` | Các endpoint report |
| Migration | `backend/database/migrations/2026_08_25_160000_create_report_data_sources_table.php` | Bảng Data Source và liên kết template |

### Frontend

| Thành phần | Vị trí | Trách nhiệm |
|---|---|---|
| Data Source Manager | `frontend/src/pages/config/components/hotel/ReportDataSourceManagerModal.vue` | Chọn Store, nhập sample parameters, chạy thử, đồng bộ schema |
| Template Designer | `frontend/src/pages/config/components/hotel/TemplateEditorModal.vue` | Chọn Data Source, kéo field, preview và lưu template |
| Danh sách template | `frontend/src/pages/config/components/DesignTemplateTab.vue` | Mở Data Source Manager và editor |

## 3. Quy ước bắt buộc khi viết Store báo cáo

### 3.1. Tên Stored Procedure

Tên phải bắt đầu bằng prefix:

```text
rpt_
```

Ví dụ hợp lệ:

```text
rpt_arriving_rooms
rpt_booking_revenue
rpt_room_status
rpt_cashier_shift
```

Ví dụ không được catalog tự động nhận:

```text
sp_001
getRevenue
delete_old_data
```

Prefix có thể thay đổi qua biến môi trường:

```env
REPORT_PROCEDURE_PREFIX=rpt_
```

### 3.2. Store phải là read-only

Store phải khai báo:

```sql
READS SQL DATA
```

hoặc:

```sql
NO SQL
```

Không dùng Store report để thực hiện:

- `INSERT`
- `UPDATE`
- `DELETE`
- `DROP`
- thay đổi trạng thái nghiệp vụ

Catalog hiện chỉ hiển thị Store `rpt_*` khai báo `READS SQL DATA` hoặc `NO SQL`.

### 3.3. Chỉ dùng parameter loại IN

Phiên bản hiện tại hỗ trợ:

```sql
IN p_from_date DATE,
IN p_to_date DATE,
IN p_status INT
```

Chưa hỗ trợ:

```sql
OUT p_total DECIMAL(15,2)
INOUT p_counter INT
```

Nếu cần trả tổng tiền hoặc số lượng, hãy đưa chúng thành cột trong result set hoặc tính ở `summary` trong phiên bản mở rộng sau.

### 3.4. Chỉ trả về một result set

Mỗi Store report nên có đúng một câu `SELECT` kết quả cuối cùng.

Không nên:

```sql
SELECT * FROM bookings;
SELECT * FROM payments;
```

Nên gom dữ liệu thành một result set duy nhất hoặc tách thành hai Data Source khác nhau.

### 3.5. Alias cột là contract của template

Ví dụ:

```sql
SELECT
    b.id AS BookingId,
    b.booking_name AS BookingName,
    b.arrival_date AS ArrivalDate,
    b.departure_date AS DepartureDate
FROM bookings AS b;
```

Template sẽ lưu binding như:

```text
row.BookingId
row.BookingName
row.ArrivalDate
```

Có thể thay đổi `JOIN`, điều kiện, công thức tính hoặc bảng nguồn mà không cần sửa template, miễn là giữ nguyên alias output.

Nếu đổi `BookingName` thành `GuestName`, các template cũ sử dụng `row.BookingName` sẽ mất dữ liệu. Khi refresh schema, hệ thống sẽ cảnh báo field đã bị xóa.

## 4. Mẫu Stored Procedure chuẩn

```sql
DROP PROCEDURE IF EXISTS rpt_booking_by_date;

CREATE PROCEDURE rpt_booking_by_date(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_status INT
)
READS SQL DATA
BEGIN
    SELECT
        b.id AS BookingId,
        b.booking_name AS BookingName,
        b.booking_date AS BookingDate,
        b.arrival_date AS ArrivalDate,
        b.departure_date AS DepartureDate,
        b.num_of_days AS NumberOfNights,
        b.status AS BookingStatus,
        b.payment_value AS DepositAmount,
        b.contact_phone AS ContactPhone
    FROM bookings AS b
    WHERE b.booking_date BETWEEN p_from_date AND p_to_date
      AND (p_status IS NULL OR b.status = p_status)
      AND b.deleted_at IS NULL
    ORDER BY b.booking_date, b.id;
END;
```

Lưu ý:

- Dùng alias tiếng Anh, không dấu và không có khoảng trắng.
- Kiểu dữ liệu output nên ổn định giữa các nhánh `IF/ELSE`.
- Nên luôn có `ORDER BY` rõ ràng nếu thứ tự dòng có ý nghĩa.
- Dùng `NULL` cho bộ lọc tùy chọn thay vì ghép SQL động khi có thể.
- Không nhận tên bảng, tên cột hoặc đoạn SQL từ parameter.

## 5. Store tham chiếu hiện có

Migration sau tạo Store, Data Source, danh mục và mẫu tham chiếu hoàn chỉnh:

```text
backend/database/migrations/2026_08_26_110000_create_arriving_rooms_report.php
```

Tên Store:

```text
rpt_arriving_rooms
```

Parameters:

```text
p_from_date DATE
p_to_date DATE
p_room_class_id BIGINT
p_registration_status_id BIGINT
p_area VARCHAR
p_company_id BIGINT
p_booking_id BIGINT
p_show_main_guest TINYINT
p_show_room_rate TINYINT
```

Store này là nền tảng kiểm thử toàn bộ quy trình Data Source, Designer, form tham số, menu và Viewer.

## 6. Quy trình tạo report mới

### Bước 1: Xác định contract

Trước khi viết Store, lập danh sách:

- Tên report.
- Bộ lọc/parameters.
- Các cột chi tiết.
- Các giá trị tổng hợp.
- Quyền người được xem.
- Phạm vi dữ liệu theo chi nhánh.

Ví dụ:

```text
Report: Doanh thu booking theo ngày
Store: rpt_booking_revenue
Parameters: p_from_date, p_to_date, p_company_id
Fields: BookingId, BookingName, RoomNumber, Revenue, Tax, Total
```

### Bước 2: Viết và kiểm tra Store

Viết Store bằng MySQL Workbench, DBeaver hoặc migration SQL.

Chạy thử trực tiếp:

```sql
CALL rpt_booking_by_date('2026-08-01', '2026-08-31', NULL);
```

Kiểm tra:

- Store không thay đổi dữ liệu.
- Parameters hoạt động đúng.
- Alias cột đúng contract.
- Không trả nhiều result set.
- Thời gian chạy phù hợp.
- Số dòng không quá lớn.

### Bước 3: Đăng ký Data Source

Trong PMS:

1. Vào trang **Thiết kế mẫu**.
2. Bấm **Nguồn dữ liệu Store**.
3. Chọn Store `rpt_*` ở panel bên trái.
4. Kiểm tra parameters hệ thống tự đọc.
5. Nhập sample parameters.
6. Bấm **Chạy thử**.
7. Kiểm tra số dòng và các field.
8. Nhập mã Data Source, ví dụ `BOOKING_REVENUE`.
9. Nhập tên hiển thị.
10. Bấm **Lưu và đồng bộ schema**.

### Bước 4: Tạo hoặc mở template

1. Tạo template mới hoặc mở template đang có.
2. Trong panel **Nguồn dữ liệu Store**, chọn Data Source.
3. Kiểm tra sample parameters.
4. Danh sách field sẽ được thay bằng metadata của Store.

### Bước 5: Thiết kế layout

Các binding chính:

```text
parameters.p_from_date
parameters.p_to_date
summary.row_count
rows
row.BookingId
row.BookingName
```

Cách dùng:

- Kéo parameter vào Header để hiển thị khoảng thời gian báo cáo.
- Kéo field `row.*` vào bảng trong Detail Band.
- Kéo `summary.row_count` vào Footer.
- Có thể kéo block giữa Header, Detail và Footer.
- Có thể bấm field để chèn binding vào block text đang chọn.

### Bước 6: Preview

1. Nhập parameters preview.
2. Chuyển sang tab Preview.
3. Backend chạy Store trên database chi nhánh hiện tại.
4. Renderer thay binding và lặp các dòng `rows`.

### Bước 7: Lưu

- Auto-save sử dụng `save_mode=draft`, không tăng version.
- **Lưu phiên bản** sử dụng `save_mode=version`, tăng version và tạo lịch sử.
- Nên preview trước khi lưu phiên bản chính thức.

## 7. Chỉnh sửa Store đã tồn tại

Quy trình an toàn:

1. Ghi lại danh sách alias hiện tại.
2. Sửa Store trong MySQL.
3. Chạy `CALL` trực tiếp để kiểm tra.
4. Vào **Nguồn dữ liệu Store**.
5. Chọn lại Store.
6. Nhập sample parameters phù hợp.
7. Bấm **Lưu và đồng bộ schema**.
8. Xem cảnh báo field thêm/mất.
9. Mở các template bị ảnh hưởng và sửa binding.
10. Preview rồi tạo version mới.

### Thay đổi an toàn

```sql
-- Giữ nguyên alias TotalAmount, chỉ đổi công thức
SUM(item.quantity * item.unit_price - item.discount) AS TotalAmount
```

### Thay đổi có thể làm hỏng template

```sql
-- Cũ
b.booking_name AS BookingName

-- Mới
b.booking_name AS GuestName
```

## 8. Cấu trúc dữ liệu runtime

Executor chuẩn hóa kết quả thành:

```json
{
  "parameters": {
    "p_from_date": "2026-08-01",
    "p_to_date": "2026-08-31"
  },
  "rows": [
    {
      "BookingId": 1001,
      "BookingName": "NGUYEN VAN A"
    }
  ],
  "summary": {
    "row_count": 1,
    "truncated": false
  },
  "fields": [
    {
      "name": "BookingId",
      "type": "integer",
      "nullable": false
    }
  ]
}
```

Trong template:

```html
<p>Từ ngày: {{parameters.p_from_date}}</p>
<p>Đến ngày: {{parameters.p_to_date}}</p>

<table>
    <tr class="pms-detail-row" data-source="rows">
        <td>{{row.BookingId}}</td>
        <td>{{row.BookingName}}</td>
    </tr>
</table>

<p>Tổng số dòng: {{summary.row_count}}</p>
```

Không nên chỉnh `content_html` trực tiếp nếu không cần thiết. Designer lưu layout trong `content_json` và compile HTML để preview/render.

## 9. API reference

Tất cả API yêu cầu Sanctum authentication và branch headers của PMS.

### Headers

```http
Authorization: Bearer {token}
Accept: application/json
X-Branch-Code: HKT1
X-Branch-Id: 1
```

### Danh sách Store khả dụng

```http
GET /api/report-procedures
```

Query tùy chọn:

```http
GET /api/report-procedures?search=arriving
```

### Metadata một Store

```http
GET /api/report-procedures/rpt_arriving_rooms
```

### Chạy thử Store chưa đăng ký

```http
POST /api/report-procedure-samples
Content-Type: application/json

{
  "procedure": "rpt_arriving_rooms",
  "parameters": {
    "p_from_date": "2026-08-26",
    "p_to_date": "2026-08-26",
    "p_room_class_id": null,
    "p_registration_status_id": null,
    "p_area": null,
    "p_company_id": null,
    "p_booking_id": null,
    "p_show_main_guest": 1,
    "p_show_room_rate": 1
  },
  "max_rows": 100
}
```

### CRUD Data Source

```http
GET    /api/report-data-sources
POST   /api/report-data-sources
GET    /api/report-data-sources/{id}
PUT    /api/report-data-sources/{id}
DELETE /api/report-data-sources/{id}
```

Payload tạo Data Source:

```json
{
  "code": "ARRIVING_ROOMS",
  "name": "Dữ liệu báo cáo phòng đến",
  "description": "Khách và phòng đến trong ngày được chọn",
  "object_name": "rpt_arriving_rooms",
  "sample_parameters": {
    "p_from_date": "2026-08-26",
    "p_to_date": "2026-08-26",
    "p_room_class_id": null,
    "p_registration_status_id": null,
    "p_area": null,
    "p_company_id": null,
    "p_booking_id": null,
    "p_show_main_guest": 1,
    "p_show_room_rate": 1
  },
  "max_rows": 1000,
  "is_active": true
}
```

### Chạy thử Data Source đã đăng ký

```http
POST /api/report-data-sources/{id}/samples

{
  "parameters": {
    "p_from_date": "2026-08-26",
    "p_to_date": "2026-08-26"
  }
}
```

### Refresh schema

```http
POST /api/report-data-sources/{id}/schema-refreshes

{
  "parameters": {
    "p_from_date": "2026-08-26",
    "p_to_date": "2026-08-26"
  }
}
```

Response có thông tin thay đổi:

```json
{
  "success": true,
  "data": {},
  "schema_changes": {
    "added_fields": ["NewField"],
    "removed_fields": ["OldField"],
    "affected_templates": [
      { "id": 10, "name": "Báo cáo doanh thu" }
    ]
  }
}
```

## 10. Database schema

Bảng `report_data_sources`:

| Cột | Ý nghĩa |
|---|---|
| `code` | Mã ổn định của Data Source |
| `name` | Tên hiển thị |
| `description` | Mô tả nghiệp vụ |
| `source_type` | Hiện tại là `procedure` |
| `schema_name` | Database/schema MySQL chứa Store |
| `object_name` | Tên Store, ví dụ `rpt_arriving_rooms` |
| `parameter_schema` | Metadata input parameters |
| `field_schema` | Metadata result fields |
| `sample_parameters` | Bộ tham số dùng refresh schema |
| `max_rows` | Số dòng tối đa executor trả về |
| `is_active` | Cho phép designer sử dụng |
| `last_discovered_at` | Lần đồng bộ schema gần nhất |

Các cột mới của `templates`:

| Cột | Ý nghĩa |
|---|---|
| `report_data_source_id` | Data Source template đang dùng |
| `parameter_defaults` | Tham số mặc định khi preview/render |

`template_versions` cũng lưu snapshot của Data Source và parameter defaults để rollback không bị mất liên kết.

Bảng `report_definitions`:

| Cột | Ý nghĩa |
|---|---|
| `code` | Mã báo cáo duy nhất, viết hoa và ổn định; được dùng trong URL `?report=CODE` |
| `name` | Tên hiển thị cho người dùng |
| `group` | Tên nhóm báo cáo trên menu; không phải nhóm template |
| `description` | Mô tả nghiệp vụ cho người quản trị |
| `report_data_source_id` | Data Source được chạy khi bấm Hiển thị báo cáo |
| `parameter_ui_schema` | Cấu hình chuyển tham số Store thành form lọc bên trái |
| `sort_order` | Thứ tự chung trong danh sách quản trị/Viewer |
| `is_active` | Cho phép Viewer tải và chạy báo cáo |
| `show_in_menu` | Có tạo lối tắt báo cáo trên menu hệ thống hay không |
| `menu_locations` | Các khu vực hiển thị: `reservation`, `frontdesk`, `housekeeping` |
| `menu_top_order` | Thứ tự của nút Báo cáo trong thanh menu khu vực |
| `menu_group_order` | Thứ tự nhóm báo cáo trong menu xổ xuống |
| `menu_item_order` | Thứ tự báo cáo trong cùng nhóm |

Bảng liên kết `report_definition_template`:

| Cột | Ý nghĩa |
|---|---|
| `report_definition_id` | Báo cáo sở hữu danh sách mẫu đầu ra |
| `template_id` | Template được phép chọn trong Viewer |
| `is_default` | Mẫu tự chọn khi mở báo cáo lần đầu |
| `sort_order` | Thứ tự trong dropdown Mẫu đầu ra |

Một báo cáo phải có ít nhất một dòng liên kết template. Chỉ một dòng của mỗi báo cáo nên có `is_default=true`. Backend sẽ chọn mẫu đầu tiên nếu không tìm thấy mẫu mặc định, nhưng không nên dựa vào fallback này trong cấu hình chính thức.

Cấu trúc một phần tử trong `parameter_ui_schema`:

```json
{
  "name": "p_company_id",
  "label": "Công ty / lữ hành",
  "control": "select",
  "required": false,
  "default": null,
  "options": [],
  "options_source": "companies"
}
```

| Thuộc tính | Ý nghĩa |
|---|---|
| `name` | Bắt buộc trùng tuyệt đối tên parameter của Stored Procedure |
| `label` | Nhãn tiếng Việt hiển thị trên Viewer |
| `control` | `text`, `number`, `date`, `date-range`, `datetime-local`, `select`, `radio`, `checkbox` hoặc `hidden` |
| `required` | Viewer kiểm tra có giá trị trước khi gọi execute |
| `default` | Giá trị mặc định; hỗ trợ `$today`, `$month_start`, `$month_end` |
| `options` | Danh sách lựa chọn tĩnh dạng `{label, value}` |
| `options_source` | Danh mục động hiện hỗ trợ: `areas`, `companies`, `bookings`, `room-classes`, `registration-statuses` |
| `range_end_parameter` | Chỉ dùng với `date-range`: tên parameter nhận ngày kết thúc; parameter này nên để `hidden` |

`parameter_schema` của Data Source mô tả **Store nhận gì**; `parameter_ui_schema` của Report Definition mô tả **người dùng nhập giá trị đó bằng giao diện nào**. Khi Store thêm tham số, cần đồng bộ Data Source rồi mở Danh mục báo cáo để cấu hình phần giao diện cho tham số mới.

`date-range` là một control giao diện gộp hai parameter Store. Ví dụ `p_from_date` dùng `control: "date-range"` và `range_end_parameter: "p_to_date"`; `p_to_date` dùng `control: "hidden"`. Viewer hiển thị một ô **Chọn ngày**, cho chọn preset như Hôm nay/Tuần này/Tháng này hoặc khoảng tùy chỉnh, rồi gửi cả hai giá trị vào Store khi bấm **Hiển thị báo cáo**. Preset **Hôm nay** lấy từ `GET /api/system-date` (ngày nghiệp vụ `system_date_rolls` của chi nhánh), không lấy theo đồng hồ máy người dùng.

### Nguồn của các option trong bộ lọc đầu vào

Store chỉ khai báo **tên và kiểu parameter**, ví dụ `p_area VARCHAR`, `p_company_id BIGINT` hoặc `p_from_date DATE`. Sau khi chọn Store trong Danh mục báo cáo, hệ thống tự tạo các dòng cấu hình tương ứng. Người quản trị quyết định nhãn, kiểu control, bắt buộc hay không và nguồn option của control đó.

```text
Store: p_company_id BIGINT
          ↓
Danh mục báo cáo: nhãn “Công ty / lữ hành”, control “Danh sách”
          ↓
options_source = companies
          ↓
Viewer gọi GET /api/report-lookups/companies
          ↓
Đọc danh mục companies của đúng database chi nhánh
          ↓
Dropdown trả value = company.id, label = “CODE - Tên công ty”
          ↓
Value được truyền vào p_company_id khi chạy Store
```

Có hai cách cấp option cho `select` hoặc `radio`:

1. **Danh mục động PMS**: chọn ở ô **Lấy lựa chọn từ danh mục PMS**. Viewer tải danh sách mới nhất mỗi khi mở báo cáo. Đây là cách dùng cho dữ liệu thay đổi theo chi nhánh như khu vực, công ty, đăng ký và loại phòng.
2. **Danh sách tĩnh**: không chọn danh mục PMS và nhập tại ô **Các lựa chọn cho dropdown** theo cú pháp `Nhãn=Giá trị`, ngăn cách bằng dấu phẩy hoặc xuống dòng. Ví dụ: `Tất cả=ALL, Đảm bảo=GUARANTEED, Chưa đảm bảo=UNGUARANTEED`.

Không tạo danh sách option bằng cách trả thêm result set từ Store. Phiên bản hiện tại chỉ nhận một result set dữ liệu báo cáo; dropdown được lấy qua lookup riêng để tránh Store báo cáo phải kiêm nhiệm dữ liệu danh mục.

Các `options_source` hiện có:

> Danh sách chọn ở ô **Lấy lựa chọn từ danh mục PMS** hiện là một **whitelist được khai báo trong mã nguồn**, không phải hệ thống tự quét tất cả bảng hoặc tất cả danh mục của PMS. Việc này là chủ đích: một parameter cần biết rõ bảng nguồn, điều kiện lọc, `value` gửi vào Store và `label` hiển thị; tự động đưa mọi bảng vào dropdown sẽ dễ lộ dữ liệu không phù hợp và không xác định được ý nghĩa nghiệp vụ.

> Cụ thể, danh sách nhãn trong màn hình quản trị được khai báo tại `frontend/src/pages/config/components/hotel/ReportDefinitionManagerModal.vue`; truy vấn lấy dữ liệu được whitelist tương ứng tại `backend/app/Http/Controllers/Api/ReportLookupController.php`. Hai nơi phải được bổ sung đồng thời khi thêm một nguồn mới.

| Giá trị cấu hình | Nhãn hiển thị | Nguồn dữ liệu thực tế | `value` gửi vào Store | `label` người dùng nhìn thấy |
|---|---|---|---|---|
| `areas` | Khu vực phòng | Các giá trị `rooms.area` khác rỗng, không trùng | Chuỗi khu vực | Tên khu vực |
| `companies` | Công ty / lữ hành | `companies`, chỉ lấy bản ghi `is_active=true` | `companies.id` | `code - name` |
| `bookings` | Đăng ký phòng | `bookings`, không bị xóa và trạng thái `0, 1, 2` | `bookings.id` | `id - booking_name (arrival_date)` |
| `room-classes` | Loại phòng | `room_classes`, chỉ lấy bản ghi `is_active=true` | `room_classes.id` | `code - name` |
| `registration-statuses` | Tình trạng đăng ký | `registration_statuses`, bỏ bản ghi `is_hidden=true` | `registration_statuses.id` | `vietnamese`, nếu rỗng dùng `name` |

Các lookup luôn chạy trên connection của chi nhánh hiện tại, do đó HKT1 chỉ thấy khu vực/công ty/đăng ký của HKT1; không đọc chéo HKT2.

Ví dụ cấu hình đúng cho báo cáo phòng đến:

| Parameter Store | Control trên Viewer | Nguồn option / mặc định | Giá trị truyền vào Store |
|---|---|---|---|
| `p_from_date` + `p_to_date` | `date-range` (một control) | `$today` cho cả hai, bắt buộc | Khoảng ngày `YYYY-MM-DD` đến `YYYY-MM-DD` |
| `p_room_class_id` | `select` | `room-classes`, không bắt buộc | ID loại phòng hoặc rỗng/null |
| `p_registration_status_id` | `select` | `registration-statuses`, không bắt buộc | ID tình trạng hoặc rỗng/null |
| `p_area` | `select` | `areas`, không bắt buộc | Chuỗi area hoặc rỗng/null để lấy tất cả |
| `p_company_id` | `select` | `companies`, không bắt buộc | ID công ty hoặc rỗng/null |
| `p_booking_id` | `select` | `bookings`, không bắt buộc | ID đăng ký hoặc rỗng/null |
| `p_show_main_guest` | `checkbox` | `true` | `1`/`true` hoặc `0`/`false`; executor chuẩn hóa boolean thành `1`/`0` trước khi gọi Store |
| `p_show_room_rate` | `checkbox` | `true` | `1` trả giá phòng; `0` trả ô giá trống; executor chuẩn hóa boolean thành `1`/`0` |

Khi cần thêm một option động mới, không sửa template và không thêm query vào Store. Cần thêm một lookup được whitelist trong `ReportLookupController`, thêm lựa chọn vào màn hình Danh mục báo cáo, sau đó cấu hình `options_source` cho parameter phù hợp. Nếu danh mục đó có nhiều dữ liệu, endpoint lookup phải có tìm kiếm/phân trang thay vì tải toàn bộ danh sách.

## 11. Multi-tenant và triển khai chi nhánh

Data Source, template và Store nằm trong database nghiệp vụ của từng chi nhánh. Vì vậy:

- `HKT1` chỉ nhìn thấy Store/Data Source của database HKT1.
- `HKT2` không đọc dữ liệu HKT1.
- Store cùng tên có thể được triển khai cho nhiều chi nhánh.
- Chi nhánh mới sẽ nhận migration branch trong quy trình provision database.

Bốn migration nền tảng cần chạy trên từng database chi nhánh hiện có:

```powershell
php artisan migrate `
  --database=mysql_hkt2 `
  --path=database/migrations/2026_08_25_160000_create_report_data_sources_table.php `
  --path=database/migrations/2026_08_25_170000_create_report_definitions_table.php `
  --path=database/migrations/2026_08_26_100000_create_print_template_slots_table.php `
  --path=database/migrations/2026_08_26_110000_create_arriving_rooms_report.php `
  --force
```

Thay `mysql_hkt2` bằng:

```text
mysql_hkt1
mysql_hkt2
mysql_hkt3
mysql_hkt4
```

Không chạy `migrate:fresh` trên môi trường có dữ liệu thật.

## 12. Giới hạn và bảo vệ tài nguyên

Cấu hình:

```env
REPORT_MAX_ROWS=1000
REPORT_MAX_ROWS_LIMIT=5000
```

- `REPORT_MAX_ROWS`: giới hạn mặc định của Data Source.
- `REPORT_MAX_ROWS_LIMIT`: giới hạn tối đa API chấp nhận.
- Preview trên UI nên dùng khoảng 100 dòng.
- Report lớn nên lọc bằng parameters thay vì lấy toàn bộ dữ liệu.

Khuyến nghị database:

- Tạo index cho các cột lọc và join thường dùng.
- Kiểm tra Store bằng `EXPLAIN` đối với câu `SELECT` chính.
- Không dùng vòng lặp cursor nếu có thể giải quyết bằng query tập hợp.
- Không nhận SQL thô từ frontend.
- Không dùng dynamic table/column name từ parameter.

## 13. Xử lý lỗi thường gặp

### Store không xuất hiện

Kiểm tra:

1. Tên có bắt đầu bằng `rpt_` không.
2. Store có nằm trong database chi nhánh đang chọn không.
3. Store có khai báo `READS SQL DATA` hoặc `NO SQL` không.
4. User MySQL của ứng dụng có quyền đọc `INFORMATION_SCHEMA` và `EXECUTE` không.

Kiểm tra trực tiếp:

```sql
SELECT ROUTINE_NAME, SQL_DATA_ACCESS
FROM INFORMATION_SCHEMA.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_TYPE = 'PROCEDURE'
  AND ROUTINE_NAME LIKE 'rpt\_%';
```

### Báo thiếu parameter

Ví dụ:

```text
Missing report parameter: p_to_date
```

Mọi parameter `IN` phải có key trong request, kể cả khi giá trị cho phép là `NULL`.

### Chạy trực tiếp được nhưng API lỗi

Kiểm tra:

- Thứ tự parameters.
- Kiểu ngày MySQL `YYYY-MM-DD`.
- Quyền `EXECUTE` của user ứng dụng.
- Store có nhiều result set không.
- Store có sử dụng temporary table/dynamic SQL đặc biệt không.
- Laravel log trong `backend/storage/logs`.

### Preview không có dữ liệu

Kiểm tra:

- Sample/default parameters.
- Chi nhánh đang chọn.
- Store có trả rows với chính bộ tham số đó không.
- Binding bảng có `data-source="rows"` không.
- Field trong bảng có dạng `row.FieldName` không.

### Template hiển thị field rỗng sau khi sửa Store

1. Refresh schema Data Source.
2. Xem danh sách `removed_fields`.
3. Mở template bị ảnh hưởng.
4. Thay binding cũ bằng alias mới.
5. Preview và lưu version mới.

### Không xóa được Data Source

Data Source đang được một hoặc nhiều template sử dụng. Hãy chuyển các template sang nguồn khác hoặc gỡ liên kết trước khi xóa.

## 14. Checklist tạo report mới

### Stored Procedure

- [ ] Tên bắt đầu bằng `rpt_`.
- [ ] Có `READS SQL DATA`.
- [ ] Chỉ có parameter `IN`.
- [ ] Chỉ trả một result set.
- [ ] Alias cột rõ ràng và ổn định.
- [ ] Không thay đổi dữ liệu.
- [ ] Có bộ lọc để giới hạn dữ liệu.
- [ ] Chạy thử trực tiếp thành công.

### Data Source

- [ ] Store xuất hiện trong Data Source Manager.
- [ ] Parameters được đọc đúng.
- [ ] Sample parameters hợp lệ.
- [ ] Chạy thử trả đúng field.
- [ ] Mã Data Source viết hoa, không dấu, dùng `_`.
- [ ] Schema được đồng bộ.

### Template

- [ ] Chọn đúng Data Source.
- [ ] Parameter mặc định hợp lệ.
- [ ] Detail table dùng nguồn `rows`.
- [ ] Các cột dùng `row.FieldName`.
- [ ] Preview có dữ liệu thật.
- [ ] Không còn binding field đã bị xóa.
- [ ] Lưu version kèm ghi chú thay đổi.

### Triển khai

- [ ] Migration chạy trên đúng chi nhánh.
- [ ] Store tồn tại trên database đích.
- [ ] User ứng dụng có quyền `EXECUTE`.
- [ ] Kiểm tra không đọc chéo chi nhánh.
- [ ] Kiểm tra hiệu năng với dữ liệu gần thực tế.

## 15. Trạng thái triển khai hiện tại

Đã có:

- MySQL Stored Procedure discovery.
- Parameter discovery.
- Generic executor với parameter binding.
- Data Source CRUD/test/schema refresh.
- Schema drift warning.
- Liên kết Data Source với template.
- Field list động.
- Preview bằng dữ liệu Store thật.
- Drag/drop field và block cơ bản.
- Draft không tạo version rác.

Chưa có hoặc cần hoàn thiện thêm:

- `OUT`/`INOUT` parameters.
- Nhiều result set.
- Undo/redo trong designer.
- Nested drag/drop hoàn chỉnh cho layout nhiều cột.
- Server-side compiler lấy `content_json` làm nguồn duy nhất.
- Xuất Excel `.xlsx` thực sự và PDF sinh trực tiếp từ server. Hiện tại Viewer đã có CSV và In/Save as PDF qua trình duyệt.
- Phân quyền riêng cho quản trị Store/Data Source.
- Query timeout cưỡng chế ở database level.

Khi mở rộng các phần trên, cần cập nhật lại tài liệu này cùng migration, API contract và checklist kiểm thử.
## 16. Danh mục báo cáo và màn hình Viewer

> Phần này mô tả lớp hoàn thiện được bổ sung sau nền tảng Store và Template. Store không còn gắn cứng trực tiếp với một màn hình; hệ thống dùng **Định nghĩa báo cáo** để ghép Store, bộ lọc và nhiều mẫu đầu ra.

### 16.1. Ba thành phần độc lập

1. **Nguồn dữ liệu Store** (`report_data_sources`): đăng ký MySQL Stored Procedure `rpt_*`, metadata tham số và danh sách field.
2. **Mẫu đầu ra** (`templates`): HTML/CSS và bố cục kéo thả; không chứa câu SQL.
3. **Định nghĩa báo cáo** (`report_definitions`): tên báo cáo trên menu, Store cần chạy, cấu hình bộ lọc và các mẫu được phép chọn.

Bảng `report_definition_template` là bảng liên kết nhiều-nhiều. Cột `is_default` xác định mẫu mở mặc định của riêng từng báo cáo. Không dùng `templates.is_default` để quyết định mẫu mặc định trong Report Viewer.

### 16.2. Tạo một báo cáo mới hoàn chỉnh

1. Tạo hoặc sửa Stored Procedure trong database chi nhánh. Store phải có tiền tố `rpt_` và khai báo `READS SQL DATA` hoặc `NO SQL`.
2. Vào **Cấu hình → Thiết kế mẫu → Nguồn dữ liệu Store**, chọn Store, nhập tham số mẫu, chạy thử và đăng ký nguồn.
3. Tạo ít nhất một mẫu đầu ra, chọn nguồn Store trong Designer, kéo field `row.*` vào bảng và lưu phiên bản.
4. Bấm **Danh mục báo cáo → Tạo báo cáo mới**.
5. Nhập mã/tên/nhóm, chọn Store, đặt nhãn và kiểu nhập cho từng tham số.
6. Chọn một hoặc nhiều mẫu đầu ra, đánh dấu một mẫu mặc định rồi lưu.
7. Mở menu **Báo cáo**, chọn báo cáo, nhập điều kiện và bấm **Hiển thị báo cáo**.

Khi người dùng đổi mẫu đầu ra sau khi đã tải dữ liệu, Viewer chỉ render lại dataset đang có và không chạy Store lần nữa. Khi thay đổi điều kiện và bấm **Hiển thị báo cáo**, Store được chạy lại.

### 16.3. API của lớp Report Viewer

- `GET /api/report-definitions?active_only=1`: danh mục báo cáo, bộ lọc và danh sách mẫu.
- `POST /api/report-definitions/{id}/execute`: chạy Store và render mẫu được chọn. Body gồm `parameters` và `template_id`.
- `POST /api/report-definitions/{id}/render`: render lại dataset bằng mẫu khác, không truy vấn database. Body gồm `data` và `template_id`.
- `POST|PUT|DELETE /api/report-definitions`: quản trị định nghĩa báo cáo.

Giá trị mặc định của tham số hỗ trợ `$today`, `$month_start`, `$month_end`; Viewer sẽ đổi các token này thành ngày theo máy người dùng trước khi gọi API.

### 16.4. Xuất báo cáo

- **In / PDF** gọi hộp thoại in của trình duyệt trên đúng mẫu HTML đang xem; chọn “Save as PDF” để lưu PDF.
- **CSV** xuất dataset gốc từ Store, phù hợp mở bằng Excel và không phụ thuộc bố cục mẫu.

### 16.5. Khi sửa Store

Giữ ổn định tên field nếu các mẫu đang dùng `{{row.FieldName}}`. Nếu đổi/xóa field, chạy **Đồng bộ cấu trúc** ở Nguồn dữ liệu Store và sửa các template được cảnh báo. Thêm tham số Store mới thì mở lại Danh mục báo cáo, chọn Store và cấu hình nhãn/kiểu nhập cho tham số đó.

### 16.6. Cấu hình vị trí báo cáo trên menu hệ thống

Mỗi định nghĩa báo cáo có thể tự quyết định vị trí hiển thị. Mở **Thiết kế biểu mẫu → Danh mục báo cáo**, chọn báo cáo và cấu hình phần **Vị trí trên menu hệ thống**:

- **Hiện trên menu**: tắt để báo cáo chỉ còn trong Report Viewer, không xuất hiện trên thanh menu.
- **Khu vực hiển thị**: Đăng ký phòng, Lễ tân và/hoặc Buồng phòng.
- **Vị trí nút Báo cáo**: số nhỏ được đặt về phía trái trước các menu có số lớn.
- **Thứ tự nhóm**: sắp xếp các nhóm như Báo cáo phòng, Báo cáo khách, Báo cáo hủy phòng.
- **Thứ tự báo cáo**: sắp xếp báo cáo trong cùng nhóm.

Menu được sinh trực tiếp từ `report_definitions`, không khai báo lại tên báo cáo trong Vue:

```text
BÁO CÁO
  └── report.group                 (thứ tự: menu_group_order)
        └── report.name            (thứ tự: menu_item_order)
              └── /reports?report=report.code
```

Các trường database liên quan:

```text
show_in_menu
menu_locations
menu_top_order
menu_group_order
menu_item_order
```

Sau khi lưu cấu hình, tải lại trang để `MainLayout` đọc danh mục mới. Chọn một mục trong menu sẽ mở đúng báo cáo bằng mã `report`, sau đó Viewer tự lấy Store, bộ lọc và mẫu mặc định đã gán.

### Ví dụ cấu hình menu giống nhóm “BÁO CÁO ĐĂNG KÝ”

Mỗi dòng bên dưới là **một Report Definition**, không phải một template. Tạo Store/Data Source/template phù hợp cho từng báo cáo, sau đó vào **Danh mục báo cáo** và cấu hình như bảng sau:

| Mã báo cáo | Tên báo cáo | Nhóm hiển thị (`group`) | Hiện tại | Thứ tự nút Báo cáo | Thứ tự nhóm | Thứ tự báo cáo |
|---|---|---|---|---:|---:|---:|
| `ARRIVING_ROOMS` | Báo cáo phòng đến | `BÁO CÁO ĐĂNG KÝ` | Đăng ký phòng | 20 | 10 | 10 |
| `DEPARTING_ROOMS` | Báo cáo phòng đi | `BÁO CÁO ĐĂNG KÝ` | Đăng ký phòng | 20 | 10 | 20 |
| `IN_HOUSE_ROOMS` | Báo cáo phòng ở | `BÁO CÁO ĐĂNG KÝ` | Đăng ký phòng | 20 | 10 | 30 |
| `DEPOSIT_REPORT` | Báo cáo đặt cọc | `BÁO CÁO ĐĂNG KÝ` | Đăng ký phòng | 20 | 10 | 40 |

Ở mỗi dòng, bật **Kích hoạt** và **Hiện trên menu**, chọn khu vực **Đăng ký phòng** (`reservation`). Tất cả dòng cùng `group = BÁO CÁO ĐĂNG KÝ` sẽ được gom vào một submenu; `menu_group_order=10` đưa nhóm này lên trước các nhóm có số lớn hơn; `menu_item_order` quyết định thứ tự Phòng đến → Phòng đi → Phòng ở… trong nhóm.

Kết quả menu được sinh theo quy tắc:

```text
BÁO CÁO
  └── BÁO CÁO ĐĂNG KÝ              (group = BÁO CÁO ĐĂNG KÝ)
        ├── BÁO CÁO PHÒNG ĐẾN      (menu_item_order = 10)
        ├── BÁO CÁO PHÒNG ĐI        (menu_item_order = 20)
        └── BÁO CÁO PHÒNG Ở         (menu_item_order = 30)
```

Không cần thêm tên report vào `MainLayout`/Vue. Sau khi lưu và tải lại trang, frontend gọi `GET /api/report-definitions?active_only=1`, lọc theo `show_in_menu` và `menu_locations`, rồi tự tạo menu.

## 17. Phân biệt Mẫu in và Báo cáo

Đây là quy tắc quan trọng nhất khi vận hành hệ thống:

| Nhu cầu | Chọn chức năng | Có bộ lọc bên trái? | Nguồn dữ liệu |
|---|---|---:|---|
| In phiếu ăn sáng, hóa đơn, phiếu đăng ký từ một nghiệp vụ đang mở | **Cấu hình mẫu in** | Không | Dữ liệu nghiệp vụ truyền vào lúc bấm In; có thể dùng Store nếu mẫu thực sự cần |
| Xem báo cáo phòng đến, doanh thu, khách sinh nhật theo điều kiện | **Danh mục báo cáo** | Có | Stored Procedure đã đăng ký trong Nguồn dữ liệu Store |
| Chỉnh logo, tiêu đề, cột, màu, khổ giấy của một đầu ra | **Thư viện thiết kế** | Không áp dụng | Template chỉ thiết kế giao diện, không quyết định menu hoặc bộ lọc |

Nói ngắn gọn:

```text
Mẫu in nghiệp vụ = Chức năng in + thiết kế được khách sạn chọn
Báo cáo dữ liệu   = Menu + form tham số + Store + một/nhiều thiết kế đầu ra
```

Không dùng `templates.is_default` để quyết định mẫu nào được in hoặc được mở trong báo cáo. Hai lựa chọn này được lưu riêng:

- Mẫu in đang chọn: `print_template_slots.template_id`.
- Mẫu mặc định của một báo cáo: `report_definition_template.is_default`.

### 17.1. Ba khu vực trên giao diện

Vào **Cấu hình → Trung tâm mẫu & báo cáo**:

1. **Thư viện thiết kế**: tạo mới, nhân bản và sửa HTML/CSS/bố cục. Một nhóm có thể có nhiều thiết kế để mỗi khách sạn lựa chọn.
2. **Cấu hình mẫu in**: mỗi dòng là một chức năng in cố định, ví dụ `BREAKFAST_TICKET`. Chọn thiết kế mà chức năng này phải dùng và bấm **Xem thử**.
3. **Danh mục báo cáo**: tạo báo cáo có menu, bộ tham số, Store, vị trí menu và các thiết kế đầu ra.

### 17.2. Luồng mẫu in nghiệp vụ

Ví dụ khách sạn muốn dùng thiết kế riêng cho Phiếu ăn sáng:

1. Tạo các thiết kế trong nhóm `Breakfast Ticket` tại **Thư viện thiết kế**.
2. Mở **Cấu hình mẫu in** → nhóm `Breakfast Ticket`.
3. Tại dòng **Phiếu ăn sáng**, chọn thiết kế muốn sử dụng.
4. Chức năng Phiếu ăn sáng gọi API bằng mã ổn định `BREAKFAST_TICKET` và truyền dữ liệu nghiệp vụ hiện tại.

```http
POST /api/print-template-slots/BREAKFAST_TICKET/render
Content-Type: application/json

{
  "data": {
    "guest": { "name": "Nguyễn Văn A" },
    "room": { "number": "506" },
    "breakfast": { "date": "2026-08-26", "adults": 2, "children": 1 }
  }
}
```

API tự tìm thiết kế khách sạn đã chọn, render HTML rồi trả về:

```json
{
  "success": true,
  "slot": { "code": "BREAKFAST_TICKET", "name": "Phiếu ăn sáng" },
  "template": { "id": 12, "name": "Phiếu ăn sáng Navy" },
  "html": "<!doctype html>..."
}
```

Nếu mẫu được liên kết với một Report Data Source và request không truyền `data`, API có thể nhận `parameters` rồi chạy Store của mẫu. Tuy nhiên với phiếu phát sinh từ màn hình nghiệp vụ, ưu tiên truyền đúng dữ liệu đang mở để tránh truy vấn lại và tránh in nhầm booking.

Các API cấu hình:

```http
GET /api/print-template-slots
PUT /api/print-template-slots/BREAKFAST_TICKET

{
  "template_id": 12
}
```

Các mã vị trí được tạo sẵn:

```text
BOOKING_CONFIRMATION
REGISTRATION_CARD
DEPOSIT_RECEIPT
PAYMENT_RECEIPT
ROOM_MORNING_WORKSHEET
INVOICE
BREAKFAST_TICKET
```

Muốn thêm chức năng in nghiệp vụ hoàn toàn mới, thêm một dòng có `code` duy nhất vào `print_template_slots`, tạo nhóm thiết kế tương ứng, rồi cho nút In của chức năng đó gọi endpoint `/render` bằng `code`.

### 17.3. Luồng báo cáo có tham số

Ví dụ **Báo cáo phòng đến**:

```text
Người dùng chọn ngày/loại phòng/trạng thái
                 ↓
Report Definition chuyển giá trị thành parameters
                 ↓
Report Data Source gọi rpt_arriving_rooms(...)
                 ↓
Dataset được render bằng template mặc định hoặc mẫu người dùng chọn
```

Form tham số không được tạo trong Designer. Designer chỉ chỉnh phần giấy báo cáo ở bên phải. Form bên trái được sinh từ `report_definitions.parameter_ui_schema`, gồm:

- `name`: phải trùng tên parameter của Stored Procedure.
- `label`: nhãn tiếng Việt hiển thị.
- `control`: `date`, `select`, `radio`, `checkbox`, `number`, `text`...
- `default`: giá trị mặc định hoặc `$today`, `$month_start`, `$month_end`.
- `options`: lựa chọn tĩnh cho select/radio; nhập theo dạng `Nhãn=Giá trị`.

Quy trình đúng để thêm một báo cáo:

1. Tạo `rpt_*` trong MySQL.
2. Đăng ký nó tại **Nguồn dữ liệu Store** và chạy thử.
3. Tạo một hoặc nhiều thiết kế đầu ra, liên kết đúng Store.
4. Vào **Danh mục báo cáo**, chọn Store, cấu hình từng tham số và gán thiết kế.
5. Cấu hình nhóm/vị trí menu và kích hoạt.
6. Vào menu Báo cáo, nhập điều kiện và kiểm tra kết quả.

### 17.4. Cấu trúc database bổ sung

Bảng `print_template_slots`:

| Cột | Ý nghĩa |
|---|---|
| `code` | Mã chức năng in ổn định dùng trong code PMS |
| `group` | Chỉ cho phép chọn template cùng nhóm |
| `name` | Tên chức năng in hiển thị cho quản trị viên |
| `template_id` | Thiết kế hiện đang được khách sạn chọn |
| `sort_order` | Thứ tự trên màn hình cấu hình |
| `is_active` | Cho phép sử dụng vị trí mẫu in |

Migration liên quan:

```text
backend/database/migrations/2026_08_26_100000_create_print_template_slots_table.php
```

Khi triển khai nhiều database chi nhánh, migration này cùng các migration `report_data_sources` và `report_definitions` phải được chạy trên từng database đích. Không dùng `migrate:fresh` trên hệ thống có dữ liệu thật.

## 18. Báo cáo mẫu: Báo cáo phòng đến

Migration sau tạo trọn bộ Store, Data Source, thiết kế A4 ngang và mục menu báo cáo:

```text
backend/database/migrations/2026_08_26_110000_create_arriving_rooms_report.php
```

Store MySQL:

```text
rpt_arriving_rooms
```

Đây là bản chuyển đổi từ SQL Server `dbo.sp_006`. Ánh xạ bảng chính:

| ProVista | PMS MySQL | Nội dung |
|---|---|---|
| `SP2100` | `booking_rooms` | Phòng trong đăng ký |
| `vw_001`, `SP2000` | `bookings` | Header đăng ký |
| `SP2200` | `booking_room_guests` | Khách người lớn trong phòng |
| `SP2300` | `guests` | Hồ sơ khách |
| `SP2400`, `SP2500` | `booking_children` | Trẻ em và phòng được gán |
| `SP1100` | `room_classes` | Loại phòng |
| `SP1000` | `rooms` | Phòng vật lý, khu vực và thứ tự |
| `SP1302` | `companies` | Công ty/lữ hành |
| `SP1311` | `registration_statuses` | Tình trạng đăng ký |
| `SP2107`, `SP1325` | `booking_room_special_requests`, `special_requests` | Yêu cầu đặc biệt |

> `2026_08_26_110000_create_arriving_rooms_report.php` không phải chỉ là Store và cũng không copy nguyên SQL Server. File này có bốn việc: (1) tạo MySQL Procedure `rpt_arriving_rooms`, (2) đăng ký nó thành Data Source `ARRIVING_ROOMS`, (3) tạo Report Definition/menu/form tham số, và (4) gán template tham chiếu. Phần `createProcedure()` được viết lại từ logic của `dbo.sp_006` và các ảnh báo cáo họ cung cấp, sau khi ánh xạ sang bảng/field hiện có của PMS MySQL.

> Vì hai database không có cấu trúc giống hệt nhau, đây là bản tương đương nghiệp vụ chứ không thể là bản sao 1:1. Khi đối chiếu thêm dữ liệu thật, cần tiếp tục kiểm tra các field mà ProVista có nhưng PMS MySQL chưa có hoặc đặt tên khác, rồi bổ sung mapping vào `rpt_arriving_rooms` nếu cần.

Các tham số:

```text
p_from_date
p_to_date
p_room_class_id
p_registration_status_id
p_area
p_company_id
p_booking_id
p_show_main_guest
p_show_room_rate
```

**Chọn ngày** là một control phạm vi ngày, gộp hai parameter `p_from_date` và `p_to_date`. Preset **Hôm nay** tự điền cùng một ngày; người dùng có thể chọn khoảng ngày rồi bấm **Áp dụng**. Báo cáo lấy các phòng có ngày đến nằm trong khoảng đó. Các dropdown để trống nghĩa là lấy tất cả. Bật **Chỉ hiển thị khách chính** để mỗi phòng chỉ có dòng khách đại diện; tắt để lấy thêm khách phụ và trẻ em. Tắt **Hiển thị giá phòng** để Store trả cột `Rate` rỗng; mẫu vẫn giữ vị trí cột Giá phòng để bố cục không thay đổi.

Các dropdown không lưu cứng ID vào template. Viewer gọi `GET /api/report-lookups/{lookup}` để đọc danh mục mới nhất từ database chi nhánh:

```text
areas
companies
bookings
room-classes
registration-statuses
```

Mở báo cáo qua:

```text
/reports?report=ARRIVING_ROOMS
```

Thiết kế mặc định hiển thị Mã đăng ký, Phòng, Loại phòng, Tên khách, Giới tính, Ngày đến, Ngày đi, Số đêm, Người lớn/Trẻ em, Giá phòng, Công ty và Yêu cầu đặc biệt. Có thể mở **Thư viện thiết kế → Báo cáo phòng đến - Mẫu tham chiếu** để kéo thả, thêm hoặc bỏ cột mà không sửa Store.

### 18.1. Mẫu tham chiếu có phân nhóm

Phiên bản 3.0 của mẫu Báo cáo phòng đến là mẫu chuẩn để nhân bản cho các báo cáo dạng danh sách có nhóm. Cấu trúc table block bổ sung:

```json
{
  "type": "table",
  "dataSource": "rows",
  "groupBy": "CompanyId",
  "subgroupBy": "RentalRoomId",
  "groupHeader": "<td>...</td>",
  "subgroupHeader": "<td>...</td>",
  "groupFooter": "<td>...</td>"
}
```

Các binding tổng hợp hỗ trợ:

```text
{{group.count}}
{{group.distinct.RentalRoomId}}
{{group.sum.Adult}}
{{row.Rate|number}}
{{row.Company|KHÁCH LẺ}}
```

- `group.count`: số dòng trong nhóm.
- `group.distinct.Field`: đếm giá trị không trùng.
- `group.sum.Field`: cộng cột số.
- `row.Field|number`: định dạng số theo dấu phân cách hàng nghìn.
- `row.Field|Giá trị mặc định`: dùng nội dung phía sau dấu `|` khi field rỗng.

Khi tạo báo cáo tương tự, nên nhân bản mẫu tham chiếu rồi đổi Store, field, `groupBy` và các cột thay vì dựng lại header/logo/bảng từ đầu.
