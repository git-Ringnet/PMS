# Kế hoạch triển khai snapshot, backup và rollback khi sang ngày

> **Mục đích:** tài liệu giao việc cho Gemini 3.8 Flash triển khai Night Audit trên MySQL của PMS.
>
> **Trạng thái khảo sát:** đã đối chiếu schema legacy trong repo, các script khách gửi, migrations/mã MySQL và luồng Night Audit hiện có. Chưa xác minh được schema runtime trên MySQL thật; trước khi sửa, phải kiểm tra database/tenant đang kết nối và trạng thái migrations.
>
> **Vị trí tài liệu:** đặt cạnh tài liệu schema legacy để tiện đối chiếu. Các file SP7000–SP7005 và SP8051 là mô tả SQL Server của khách; không sửa chúng và không dùng T-SQL trực tiếp.

---

## 1. Mục tiêu

Triển khai phần lưu snapshot khi đóng ngày, tích hợp với luồng Night Audit hiện tại:

1. Chụp dữ liệu đã chốt của ngày đang đóng vào các bảng snapshot MySQL.
2. Kiểm tra precondition, kết quả post bill, snapshot và ngày đích trước khi hoàn thành.
3. Chỉ commit dữ liệu nghiệp vụ, snapshot và ngày hệ thống khi mọi bước bắt buộc thành công.
4. Nếu lỗi trước commit, rollback toàn bộ thay đổi trong lần chạy; ghi trạng thái thất bại và giải phóng khóa chạy.
5. Chặn hai tiến trình sang ngày chạy đồng thời, tránh post bill hoặc tạo snapshot trùng.
6. Lưu lịch sử các lần chạy và tiến độ thực tế từng bước.
7. Giữ snapshot theo ngày kể cả khi booking/khách thay đổi về sau.

## 2. Phân biệt ba loại backup

Không gộp ba mục sau:

- **Snapshot Night Audit:** bảng tương đương SP7000–SP7005 lưu dữ liệu nghiệp vụ/tổng hợp theo ngày. Đây là nội dung cần bổ sung.
- **Rollback lần sang ngày:** transaction MySQL trên đúng database chi nhánh. Nếu lỗi trước commit thì rollback bills, trạng thái, ngày hệ thống và snapshots trong cùng lần chạy. SP700x không phải bản sao đầy đủ để phục hồi toàn database.
- **Backup vật lý toàn database:** dự án đã có DatabaseBackupController với API export SQL ra luồng tải xuống và API import file SQL. Đây là chức năng quản trị toàn database; không gọi importDatabase tự động trong Night Audit.

Nếu cần khôi phục database sau khi đã commit hoặc khi máy chủ/database hỏng, phải thiết kế riêng nơi lưu backup vật lý, lịch, retention, quyền truy cập và quy trình restore. Không dùng API import database để mô phỏng rollback một lần Night Audit.

## 3. Tài liệu đã đối chiếu

### SQL Server khách gửi — chỉ làm tài liệu nghiệp vụ

- Sang ngày/sp_199 (insert vào bảng inhouse back up sp7001).sql
- Sang ngày/sp_027 (insert bảng sp7003).sql
- Sang ngày/hàm tính toán liên quan đến store sp_027 - khi sang ngày để insert vào bảng sp7003.sql
- Sang ngày/sp_148 (insert vào bảng sp7000 khi sang ngày).sql
- Sang ngày/sp245 - hàm tính toán dữ liệu backup lúc sang ngày lưu vào bảng sp7005 để thống kê về loại phòng.sql
- Sang ngày/LOG TỪ SQL PROFILER - TIẾN TRÌNH SANG NGÀY HỆ THỐNG.sql
- Sang ngày/Sang ngày.mp4

### Schema legacy trong repo

- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7000.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7001.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7002.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7003.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7004.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP7005.md
- old_database_struct/db_schema/ProVistaDTXHotel/tables/SP8051.md

### MySQL hiện tại cần đọc trước khi sửa

- backend/app/Http/Controllers/Api/NightAuditController.php: runNightAudit có precheck, post room/service bill, ghi system_date_rolls, đổi trạng thái phòng và room locks; hiện dùng DB::transaction.
- backend/database/migrations/2026_06_15_120000_create_system_date_rolls_table.php: system_date_rolls đã tồn tại nhưng chưa có unique key cho ngày hệ thống.
- backend/app/Models/SystemDateRoll.php: model ngày hệ thống.
- frontend/src/pages/frontdesk/DayClosePage.vue: UI Sang ngày hiện có, trạng thái chạy và cảnh báo chạy lại.
- backend/tests/Feature/NightAuditTest.php: test hiện có.
- backend/app/Http/Controllers/Api/DatabaseBackupController.php và backend/routes/api.php: export/import database toàn hệ thống/chi nhánh, tách khỏi rollback Night Audit.
- Nguồn dữ liệu cần đối chiếu: bookings, booking_rooms, booking_room_guests, guests, companies, rooms, room_classes, room_night_bills, service_bills, service_bill_details, booking_room_services, room_locks và cấu hình doanh thu/phòng.
- Báo cáo MySQL có liên quan: rpt_inhouse_guests, rpt_room_forecast, rpt_room_rate_statistics, rpt_expected_room_revenue_night_audit. Báo cáo động không tự thay thế snapshot cố định.

## 4. Bảng nào cần bổ sung

Repo đã có mô tả schema legacy cho **cả SP7000–SP7005 và SP8051**. Không tạo thêm tài liệu giả cho bảng SQL Server. Bổ sung bảng MySQL bằng Laravel migrations.

Trong migrations repo chưa thấy snapshot table MySQL tương đương SP7000–SP7005. Cần tạo các bảng sau bằng tên nghiệp vụ, không dùng tên SP700x:

| Bảng khách | Dữ liệu | Bảng MySQL đề nghị | Quyết định |
|---|---|---|---|
| SP7000 | Năng suất công ty/đại lý theo ngày, segment/source/sales/area và doanh thu | night_audit_agency_productivity_snapshots | Bổ sung; sp_148 có producer ghi SP7000. |
| SP7001 | Snapshot chi tiết khách/phòng in-house theo ngày | night_audit_inhouse_snapshots | Bổ sung; sp_199 có producer. Có PII, phải giới hạn quyền và retention. |
| SP7002 | KPI đại lý: phòng, room-night, khách/guest-night, phần trăm và doanh thu | night_audit_agency_productivity_kpi_snapshots | Bổ sung storage để bao phủ schema khách. Schema legacy ghi 0 dòng; gói script không có producer. Chỉ bật ghi khi tìm thấy logic tương đương hoặc được khách xác nhận. |
| SP7003 | Forecast tổng ngày: đến/đi, khách/phòng ở, FOC/HU, room sales, doanh thu, ADR, phòng khả dụng/OOO | night_audit_room_sales_forecast_snapshots | Bổ sung; sp_027 gọi helper sp_023. |
| SP7004 | Forecast detail gồm RM/EB/ER/BF/EP/US/EE/EL và KPI ngày | night_audit_room_sales_forecast_detail_snapshots | Bổ sung storage vì có schema và Profiler xóa theo ngày. Schema ghi 0 dòng, chưa thấy writer. Chỉ bật ghi khi xác minh được mapping service-code/công thức. |
| SP7005 | Inventory, OOO, phòng khả dụng, đêm, ADR, revenue và occupancy theo room type | night_audit_room_type_snapshots | Bổ sung; sp_245 có nhánh Save ghi SP7005. |
| SP8051 | Ngày hệ thống, ngày thực tế, ca, username; SystemDate không được trùng | Dùng system_date_rolls hiện có | Không tạo bảng trùng. Kiểm tra dữ liệu rồi bổ sung unique business date an toàn. |

**Điều kiện hoàn tất:** tìm kiếm toàn repo để xác nhận SP7002/SP7004 không có producer MySQL hiện hữu. Nếu không tìm thấy công thức có căn cứ, tạo storage nhưng đánh dấu hai step chưa cấu hình; không bịa dữ liệu 0 và không tuyên bố snapshot đầy đủ. Báo rõ đầu vào legacy còn thiếu.

## 5. Thiết kế chung cho bảng snapshot

Mỗi bảng cần:

- id khóa chính; night_audit_run_id; snapshot_date là ngày nghiệp vụ đang đóng; created_at/updated_at.
- Source IDs cần để truy nguồn, dạng nullable; không đặt FK đến bảng booking/guest/company để snapshot vẫn còn sau khi dữ liệu nghiệp vụ đổi hoặc bị xóa.
- Index theo snapshot_date, night_audit_run_id và chiều tra cứu thường dùng.
- Khóa chống duplicate theo run và khóa tự nhiên từng bảng. Không dựa vào unique composite có nhiều cột nullable mà MySQL vẫn cho phép duplicate.
- Tiền dùng DECIMAL precision đủ cho PMS; phần trăm dùng DECIMAL; số lượng dùng integer. Không hạ tiền về integer chỉ vì một số cột legacy lưu int.
- Tên cột snake_case có nghĩa; insert/update phải ghi rõ danh sách cột, không phụ thuộc thứ tự SELECT *.
- Chụp dữ liệu dạng denormalized để giữ nguyên trạng thái tại thời điểm đóng ngày.

### 5.1 SP7000 → night_audit_agency_productivity_snapshots

Granularity: ngày + công ty/đại lý và các chiều grouping có trong phép tính thực tế.

Lưu nhóm cột:

- company_id, company_name, market_segment_id, source_code, user_sale, area_id.
- num_of_rooms, room_nights, foc, house_use, guest_nights, num_of_guests.
- room_revenue (RM), extra_bed_revenue (EB), extra_rollaway_revenue (ER), total_revenue.
- revenue_per_room_night, revenue_percent, average_revenue, rav, rav3.

Đối chiếu bookings/companies, booking_rooms, service_bills, room_night_bills, cấu hình segment/source/sales/area và quy tắc doanh thu. sp_148 xác nhận ý nghĩa nhóm cột và ngày; chỉ port ý nghĩa nghiệp vụ, không port cú pháp.

### 5.2 SP7001 → night_audit_inhouse_snapshots

Map đủ 53 trường schema legacy về snake_case, giữ các nhóm sau nếu cần cho nghiệp vụ và được phép lưu:

- Ngày snapshot, booking_room/guest/booking IDs, status.
- Phòng, room kind/type, room rate/code, adult/child/extra bed, số đêm.
- Ngày/giờ đến và đi, user check-in/out, breakfast phòng/khách.
- Guest/title/name, booking name/contact, company/company_id, group status, orders, house-use/day-use/baby-cot/guest type.
- Nationality/country, birthday, gender, passport, address, phone, fax, email, issue/visa fields, note/position.

Nguồn: booking_rooms, bookings, booking_room_guests, guests, rooms, room_classes/room_forms, companies, nationalities và billing/config liên quan. rpt_inhouse_guests tính động và không mặc định có đủ trường; không coi báo cáo này là snapshot. Không trả passport/contact cho role không được phép; xác định retention cho PII.

### 5.3 SP7002 → night_audit_agency_productivity_kpi_snapshots

Lưu: snapshot_date, company_id, travel_agency, no_of_rooms, room_nights, room_nights_percent, no_of_guests, guest_nights, guests_percent, revenue, revenue_percent, average_revenue, rav, foc, house_use.

Không suy diễn mẫu số của các tỷ lệ từ tên cột. Tìm report consumer/producer và định nghĩa hiện hữu; nếu không có thì yêu cầu khách xác nhận trước khi bật tính toán.

### 5.4 SP7003 → night_audit_room_sales_forecast_snapshots

Lưu các chỉ số:

- dep_adult, dep_child, dep_rooms; arr_adult, arr_child, arr_rooms.
- occ_adult, occ_child, occ_rooms; house_use, foc_all, foc, foc_owner.
- room_sales, extra_bed, revenue, avg_rate, avg_rate_2, room_available.
- percent_occupancy, percent_occupancy_2, baby_cot, rm, eb, er, ooo_room.

SP7003 legacy có lỗi chính tả RoomAvible; MySQL dùng room_available. Đối chiếu rpt_room_forecast và phép tính sp_023; cần rõ chỉ tiêu tương đương. Tính theo closing date truyền tường minh, không để query đọc nhầm ngày hệ thống mới.

### 5.5 SP7004 → night_audit_room_sales_forecast_detail_snapshots

Lưu: snapshot_date, rm, eb, er, bf, ep, us, ee, el, occ_rooms, house_use, foc_all, foc, foc_owner, room_sales, extra_bed, revenue, avg_rate, avg_rate_2, room_available, percent_occupancy, percent_occupancy_2, baby_cot.

Xác minh BF/EP/US/EE/EL với service catalog, bills và cấu hình doanh thu. Không gán mã không rõ vào RM hoặc doanh thu tổng.

### 5.6 SP7005 → night_audit_room_type_snapshots

Một dòng theo ngày + room type:

- room_type_id nếu có; room_type_name; room_type_code.
- inventory, ooo, room_available, no_of_night, adr, revenue, revenue_percent, occupancy_percent.

Đối chiếu rooms, room_classes/room_forms, room_locks/OOO, booking_rooms, room-night/service bills và cấu hình inventory. sp_245 đọc OOO lịch sử từ SP7005 trong một nhánh; phải giữ ý nghĩa lịch sử, không chỉ tính OOO trạng thái hiện tại. Cột legacy % on Rev và %OCC đổi tên dễ hiểu.

## 6. Điều phối lần chạy và step log

Bổ sung migration cho hai bảng:

### night_audit_runs

Mỗi lần chạy lưu:

- id, source_system_date, target_system_date, actual_started_at, actual_finished_at, shift, username.
- status: running, succeeded, failed, recovery_required.
- idempotency_key/run token, error_code, error_message đã sanitize, metadata JSON không chứa PII thừa.
- Index ngày/status/started_at; unique idempotency key.
- Phân biệt precheck failure với runtime failure nếu phù hợp.

Tạo run trạng thái RUNNING trước transaction nghiệp vụ để record còn tồn tại khi transaction rollback. Nếu transaction thất bại, rollback dữ liệu rồi ghi FAILED bằng transaction riêng. Nếu process chết, phát hiện run stale theo lease/started_at và chuyển recovery_required hoặc cho phép người có quyền xử lý; không để khóa treo vô thời hạn.

### night_audit_run_steps

Mỗi dòng lưu run_id, step_code, thứ tự, status, started_at/finished_at, affected_rows, summary/checks và error đã sanitize. Có FK nội bộ đến run và index run_id/status. Không log passport, contact hay payload khách. Dùng dữ liệu này hiển thị tiến độ thật cho UI.

## 7. Trình tự nghiệp vụ

1. Xác định tenant/branch connection và ngày hệ thống hiện tại; khóa đồng thời trên đúng database chi nhánh.
2. Tạo run RUNNING và step log.
3. Precheck phòng đến/đi còn pending; không chạy tiếp nếu vi phạm.
4. Mở transaction trên cùng MySQL connection với các model nghiệp vụ.
5. Đọc lại ngày hiện tại bằng lockForUpdate; xác nhận ngày request và chưa có run thành công trùng.
6. Chạy nghiệp vụ hiện có: post room-night/service bill, trạng thái phòng, room lock và các bước hiện tại khác; giữ nguyên business behavior.
7. Tính snapshot theo closing date, sau các bước post bill mà snapshot cần đọc.
8. Ghi snapshot trước khi insert SystemDateRoll mới, hoặc đảm bảo mọi calculator nhận ngày đóng rõ ràng. Không cho report/procedure tự đọc ngày mới do SystemDateRoll đã chuyển.
9. Ghi SP7000/1/3/5; ghi SP7002/4 chỉ khi tìm được phép tính hợp lệ. Thay snapshot cùng business date trong transaction nếu rerun được cho phép; không sinh duplicate.
10. Đối chiếu ngày, row counts, tổng tiền, room counts, FOC/HU/OOO và business invariants. Lệch thì throw để rollback.
11. Cập nhật room/lock theo logic hiện tại; tạo SystemDateRoll mới cuối transaction sau khi snapshot/validation đạt.
12. Commit; ghi run SUCCEEDED/finished_at, giải phóng is_night_audit_running và phát completed event sau commit.
13. Khi có lỗi: rollback; ghi FAILED ngoài transaction; giải phóng flag/lock trong finally; phát failed event sau khi trạng thái cập nhật.

Tôn trọng timezone Asia/Ho_Chi_Minh và quy tắc ca hiện hành. Không tuyên bố transaction bảo vệ database khác hoặc side effect ngoài database. Broadcast/event bên ngoài phải chạy afterCommit.

## 8. system_date_rolls / SP8051

Không tạo bảng MySQL SP8051 mới. Dùng system_date_rolls làm lịch sử ngày hệ thống:

- Lưu đủ system_date, actual_date, shift, username.
- Xác định system_date có luôn chuẩn hóa về 00:00:00 không.
- Bảo đảm một SystemDate duy nhất theo ngày lịch, không chỉ unique timestamp nếu giờ có thể khác.
- Trước khi unique migration, kiểm tra duplicate ở database runtime; không xóa/ghép log tự động. Nếu có duplicate, báo danh sách và fail an toàn.
- Dùng row lock và kiểm tra ngày hiện tại trong transaction để ngăn hai request cùng roll.
- Không đổi ngữ nghĩa cảnh báo already_rolled_today nếu nó khác với việc ngày hệ thống từng tồn tại.

## 9. UI/API

- Giữ POST /api/night-audit/run và options occupied_to_dirty, empty_to_inspect tương thích.
- Trả run_id, source_date, target_date, status, current_step, tiến độ thực tế, warnings/skipped sections và message an toàn.
- Tích hợp DayClosePage.vue cùng event hiện có; không giả lập phần trăm.
- Khi lỗi, chỉ rõ step và cách xử lý, không trả stack trace/SQL/PII.
- Thêm endpoint trạng thái/lịch sử run nếu cần, theo convention permission hiện tại.
- Nếu SP7002/SP7004 chưa có producer, UI phải ghi “chưa cấu hình phép tính”, không đánh dấu snapshot đầy đủ.

## 10. Quy tắc migration và phạm vi sửa

- Tạo migrations MySQL trong backend/database/migrations cho snapshot/run/step và thay đổi an toàn cho system_date_rolls.
- Tạo Model/Service/API/UI cần thiết theo convention repo.
- Không sửa schema markdown SQL Server SP7000–SP7005/SP8051 để biến thành MySQL.
- Không chạy/chuyển nguyên ALTER PROC, T-SQL, SQL Server function hoặc Profiler script của khách.
- Không gọi DatabaseBackupController importDatabase để rollback Night Audit.
- Snapshot không FK đến booking/guest/company có thể bị xóa; chỉ FK nội bộ snapshot đến run.
- Kiểm tra migrations trên database chi nhánh sạch và đã tồn tại.
- Migration duplicate/unique phải fail rõ, không tự xóa dữ liệu.
- Giữ đúng mô hình multi-tenant: snapshot/run thuộc database chi nhánh, không ghi PII vào mysql_system.

## 11. Bảo mật và vận hành

- Hạn chế đọc in-house snapshot theo quyền PII.
- Không log dữ liệu nhận dạng nhạy cảm.
- Chốt retention cho snapshot khách; snapshot tổng hợp có thể có chính sách khác.
- Username lấy từ user xác thực, không tin dữ liệu client gửi.
- Force retry cần quyền phù hợp, audit log và đối chiếu ngày.
- Run lease/stale recovery phải xử lý được process bị kill.
- Kiểm tra bảng tham gia dùng InnoDB. Nếu thao tác non-transactional hoặc ngoài MySQL, mô tả phương án bù trừ; không tuyên bố rollback toàn phần.

## 12. Kế hoạch kiểm chứng

Cập nhật backend/tests/Feature/NightAuditTest.php và chạy test liên quan sau khi triển khai:

1. Thành công: snapshots đúng closing date, run/step đủ, system date tăng một ngày.
2. Dữ liệu nguồn đổi về sau nhưng snapshot vẫn giữ giá trị cũ.
3. Precheck fail: không post bill, không snapshot, ngày hệ thống không đổi; run được ghi nhận.
4. Inject lỗi sau post bill nhưng trước commit: bill, trạng thái, snapshot, SystemDateRoll rollback hết; run vẫn FAILED và flag được giải phóng.
5. Lỗi khi ghi bất kỳ snapshot nào: không còn snapshot một phần.
6. Hai request đồng thời: chỉ một request giữ lock và post bill; request kia nhận conflict/đang chạy.
7. Retry sau lỗi: không duplicate bill/snapshot/SystemDateRoll.
8. Retry sau thành công hoặc response bị mất: idempotency ngăn roll ngoài ý muốn.
9. Dữ liệu chỉ vào tenant hiện tại, không vào mysql_system/tenant khác.
10. SP7002/SP7004 thiếu phép tính: không bịa dữ liệu, báo trạng thái chưa cấu hình, không trả completed đầy đủ.
11. API không lộ PII cho role không được phép.
12. Migration database sạch chạy được; duplicate SystemDate trong database hiện có báo lỗi trước unique constraint.
13. Không dùng import database thật để kiểm thử restore.

## 13. Tiêu chí nghiệm thu

Chỉ đánh dấu hoàn thành khi:

- Có migration các snapshot table đã thống nhất và chúng được tạo ở đúng branch database.
- SP7000, SP7001, SP7003, SP7005 được tính từ MySQL đúng ngày đóng, có kiểm tra tổng.
- SP7002 và SP7004 có producer/công thức có căn cứ; nếu chưa có thì trạng thái thiếu cấu hình được báo minh bạch, không bị đánh dấu completed.
- Lỗi trước commit hoàn nguyên toàn bộ dữ liệu nghiệp vụ và snapshot của run.
- Không chạy đồng thời hoặc lặp post bill/snapshot ngoài ý muốn.
- system_date_rolls vẫn là nguồn ngày hệ thống, có unique business date sau khi kiểm tra duplicate an toàn.
- UI hiển thị trạng thái/step thực và tương thích các lựa chọn hiện tại.
- Test hiện hữu và mới đạt.
- Không có T-SQL khách bị chạy hoặc chép nguyên xi vào MySQL.
- Báo cáo triển khai liệt kê files/migrations/tests và công thức còn cần khách xác nhận.

## 14. Đầu vào legacy còn thiếu

Gói hiện tại không có procedure ghi SP7002/SP7004; hai schema này ghi 0 dòng. Ảnh thư mục nhắc Backup_QuaKhu_SP7005.sql nhưng file không có trong thư mục Sang ngày. Profiler gọi sp_211 nhưng định nghĩa procedure không có trong gói.

Gemini phải tìm toàn repo trước. Nếu vẫn không thấy, ghi rõ các đầu vào thiếu; không tự tạo công thức, không đoán nơi lưu file backup. Có thể hoàn thiện storage/run/rollback và snapshot có producer rõ, nhưng SP7002/SP7004 phải còn trạng thái chờ xác nhận và không được coi là đã hoàn chỉnh.

---

## Prompt giao Gemini 3.8 Flash

Đọc toàn bộ file PLAN_MYSQL_NIGHT_AUDIT_BACKUP.md và các tài liệu tham chiếu được liệt kê trước khi sửa. Kiểm tra schema/migrations MySQL, database tenant và code Night Audit hiện tại. Tạo migrations cho các snapshot/run/step table còn thiếu, tích hợp snapshot vào Night Audit bằng transaction, khóa chạy và idempotency, cập nhật UI/API và tests theo tiêu chí nghiệm thu. SQL Server scripts/schema của khách chỉ là reference; tuyệt đối không chạy hoặc port nguyên T-SQL. Không bịa công thức SP7002/SP7004 nếu không tìm được căn cứ. Không dùng importDatabase để rollback Night Audit. Cuối cùng báo files changed, migrations, test commands/results và các đầu vào nghiệp vụ còn thiếu.

