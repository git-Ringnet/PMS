-- COMPANY OCCUPANCY - DỮ LIỆU TEST TẠM CHO XAMPP / phpMyAdmin
--
-- Cách dùng:
-- 1. Chọn database branch cần test, mặc định dự án dùng pms_hkt1.
-- 2. Chạy phần SETUP bên dưới.
-- 3. Chạy phần VERIFY để gọi 2 procedure và đối soát.
-- 4. Sau khi test xong chạy phần CLEANUP.
--
-- Điều kiện:
-- - Đã chạy migration 2026_11_04_120000_implement_company_occupancy_snapshot_report.php.
-- - Bảng master rooms, room_forms, booking_statuses đã có dữ liệu.
-- - Script không sửa CompanyOccupancyRuntimeSeeder.
-- - Sau setup, tạo snapshot cho ngày quá khứ bằng NightAuditSnapshotService trước khi đối chiếu.
-- - Mã bắt đầu bằng XAMPP_OCC_ chỉ dành cho dữ liệu test của script này.

/* ================================================================
   SETUP - CHẠY PHẦN NÀY TRƯỚC
   ================================================================ */

SET @test_system_date = '2026-08-20';
SET @test_from_date = '2026-08-19';
SET @test_to_date = '2026-08-20';

-- Kiểm tra ngày vận hành hiện tại trước khi chạy.
SELECT name, value
FROM hotel_configs
WHERE LOWER(name) IN ('systemdate', 'operationdate');

-- Master tối thiểu dùng cho booking test.
INSERT INTO markets (code, name, created_at, updated_at)
VALUES ('XAMPP_OCC_COR', 'Test Corporate', NOW(), NOW())
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @market_id = LAST_INSERT_ID();

INSERT INTO customer_sources (code, name, created_at, updated_at)
VALUES ('XAMPP_OCC_DIRECT', 'Test Direct', NOW(), NOW())
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @source_id = LAST_INSERT_ID();

INSERT INTO room_classes (name, code, color, is_active, orders, created_at, updated_at)
VALUES ('Test Occupancy Room', 'XAMPP_OCC_ROOM', '#dbeafe', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @room_class_id = LAST_INSERT_ID();

SELECT id INTO @room_form_id
FROM room_forms
ORDER BY id
LIMIT 1;

SELECT @market_id AS market_id, @source_id AS source_id, @room_class_id AS room_class_id, @room_form_id AS room_form_id;

-- Hai công ty để kiểm tra nhóm, tổng và tỷ lệ doanh thu.
INSERT INTO companies (
    code, name, customer_source_id, market_id, sync_acc, max_debt, is_active, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_A', 'XAMPP Test Company A', @source_id, @market_id, 0, 0, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @company_a_id = LAST_INSERT_ID();

INSERT INTO companies (
    code, name, customer_source_id, market_id, sync_acc, max_debt, is_active, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_B', 'XAMPP Test Company B', @source_id, @market_id, 0, 0, 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @company_b_id = LAST_INSERT_ID();

-- Phòng test. Nếu phòng đã tồn tại theo mã này, script tái sử dụng phòng đó.
INSERT INTO rooms (
    room_number, room_form_id, room_class_id, max_guests, floor, area,
    extra_beds_limit, grid_row, grid_column, orders, is_internal, room_status_code,
    created_at, updated_at
)
VALUES (
    'XAMPP-OCC-01', @room_form_id, @room_class_id, 3, 'X', 'XAMPP',
    1, 0, 0, NULL, 0, 'vacant_ready', NOW(), NOW()
)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

INSERT INTO rooms (
    room_number, room_form_id, room_class_id, max_guests, floor, area,
    extra_beds_limit, grid_row, grid_column, orders, is_internal, room_status_code,
    created_at, updated_at
)
VALUES (
    'XAMPP-OCC-02', @room_form_id, @room_class_id, 3, 'X', 'XAMPP',
    1, 0, 0, NULL, 0, 'vacant_ready', NOW(), NOW()
)
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

-- Booking quá khứ: tiền phòng lấy từ service_bills + room_night_bills.
INSERT INTO bookings (
    booking_name, arrival_date, departure_date, num_of_days, booking_date,
    status, is_git, breakfast_included, company_id, market_id, customer_source_id,
    external_booking_code, sales_person, created_by, module, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_PAST_A', DATE_SUB(@test_system_date, INTERVAL 2 DAY), @test_system_date, 2, DATE_SUB(@test_system_date, INTERVAL 3 DAY),
    0, 0, 1, @company_a_id, @market_id, @source_id,
    'XAMPP-REF-PA', 'XAMPP_SALE', 'xampp', 'RS', NOW(), NOW()
);
SET @booking_past_a_id = LAST_INSERT_ID();

INSERT INTO booking_rooms (
    id, booking_id, room_number, room_class_id, arrival_date, departure_date, ActutalNumOfDays,
    rate, rate_code, breakfast, adults, babies, children_qty, extra_bed_qty, extra_bed_rate,
    status, created_by, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_BR_PAST_A', @booking_past_a_id, 'XAMPP-OCC-01', @room_class_id,
    DATE_SUB(@test_system_date, INTERVAL 2 DAY), @test_system_date, 2,
    1200000, 'XAMPP_CORP', 1, 2, 0, 1, 1, 150000,
    0, 'xampp', NOW(), NOW()
);

-- Booking quá khứ thứ hai: công ty khác để kiểm tra grouping.
INSERT INTO bookings (
    booking_name, arrival_date, departure_date, num_of_days, booking_date,
    status, is_git, breakfast_included, company_id, market_id, customer_source_id,
    external_booking_code, sales_person, created_by, module, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_PAST_B', DATE_SUB(@test_system_date, INTERVAL 2 DAY), @test_system_date, 2, DATE_SUB(@test_system_date, INTERVAL 3 DAY),
    0, 0, 1, @company_b_id, @market_id, @source_id,
    'XAMPP-REF-PB', 'XAMPP_SALE', 'xampp', 'RS', NOW(), NOW()
);
SET @booking_past_b_id = LAST_INSERT_ID();

INSERT INTO booking_rooms (
    id, booking_id, room_number, room_class_id, arrival_date, departure_date, ActutalNumOfDays,
    rate, rate_code, breakfast, adults, babies, children_qty, extra_bed_qty, extra_bed_rate,
    status, created_by, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_BR_PAST_B', @booking_past_b_id, 'XAMPP-OCC-02', @room_class_id,
    DATE_SUB(@test_system_date, INTERVAL 2 DAY), @test_system_date, 2,
    900000, 'XAMPP_CORP', 1, 1, 0, 0, 0, 0,
    0, 'xampp', NOW(), NOW()
);

-- Booking tương lai: dữ liệu lấy trực tiếp từ bookings + booking_rooms.
INSERT INTO bookings (
    booking_name, arrival_date, departure_date, num_of_days, booking_date,
    status, is_git, breakfast_included, company_id, market_id, customer_source_id,
    external_booking_code, sales_person, created_by, module, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_FUTURE_A', @test_system_date, DATE_ADD(@test_system_date, INTERVAL 2 DAY), 2, DATE_SUB(@test_system_date, INTERVAL 1 DAY),
    0, 0, 1, @company_a_id, @market_id, @source_id,
    'XAMPP-REF-FA', 'XAMPP_SALE', 'xampp', 'RS', NOW(), NOW()
);
SET @booking_future_a_id = LAST_INSERT_ID();

INSERT INTO booking_rooms (
    id, booking_id, room_number, room_class_id, arrival_date, departure_date, ActutalNumOfDays,
    rate, rate_code, breakfast, adults, babies, children_qty, extra_bed_qty, extra_bed_rate,
    status, created_by, created_at, updated_at
)
VALUES (
    'XAMPP_OCC_BR_FUTURE_A', @booking_future_a_id, 'XAMPP-OCC-01', @room_class_id,
    @test_system_date, DATE_ADD(@test_system_date, INTERVAL 2 DAY), 2,
    1500000, 'XAMPP_CORP', 1, 2, 0, 1, 0, 0,
    0, 'xampp', NOW(), NOW()
);

-- Guest test để detail có nationality.
INSERT INTO guests (id, full_name, nationality_code, guest_type, guest_status, created_at, updated_at)
VALUES
    ('XAMPP_OCC_G001', 'XAMPP Guest A', 'VNM', 'COR', 0, NOW(), NOW()),
    ('XAMPP_OCC_G002', 'XAMPP Guest B', 'KOR', 'COR', 0, NOW(), NOW()),
    ('XAMPP_OCC_G003', 'XAMPP Guest C', 'JPN', 'COR', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), nationality_code = VALUES(nationality_code), updated_at = NOW();

INSERT INTO booking_room_guests (booking_room_id, guest_id, is_primary, status, created_at, updated_at)
VALUES
    ('XAMPP_OCC_BR_PAST_A', 'XAMPP_OCC_G001', 1, 0, NOW(), NOW()),
    ('XAMPP_OCC_BR_PAST_B', 'XAMPP_OCC_G002', 1, 0, NOW(), NOW()),
    ('XAMPP_OCC_BR_FUTURE_A', 'XAMPP_OCC_G003', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), status = VALUES(status), updated_at = NOW();

-- Service bills của ngày quá khứ = ngày trước SystemDate.
SET @past_bill_date = DATE_SUB(@test_system_date, INTERVAL 1 DAY);

INSERT INTO service_bills (
    Date, OpenTime, Guest, DepartmentId, ServiceId, DescriptionServive, Quantity, Amount,
    Currency, Exchange, Edit, Folio, RegisterId1, RentalRoomId1, CompanyId1,
    RegisterID2, RentalRoomId2, CompanyId2, Username, Status, Outlet,
    Year, Month, Day, CreatedUser, CreatedDate, CreatedHour, created_at, updated_at
)
VALUES (
    CONCAT(@past_bill_date, ' 12:00:00'), '12:00', 'XAMPP Guest A', 'FO', 'RM', 'XAMPP RM A', 1, 1200000,
    'VND', 1, 0, '1', @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id,
    @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id, 'xampp', 1, 'FO',
    YEAR(@past_bill_date), MONTH(@past_bill_date), DAY(@past_bill_date), 'xampp', NOW(), '12:00', NOW(), NOW()
);
SET @rm_a_bill_id = LAST_INSERT_ID();

INSERT INTO room_night_bills (
    bill_id, adult, child, is_room_night, breakfast_amount, extrabed_amount, date,
    room, room_type_id, rate_code, rate, created_at, updated_at
)
VALUES (
    @rm_a_bill_id, 2, 1, 1, 100000, 150000, @past_bill_date,
    'XAMPP-OCC-01', @room_class_id, 'XAMPP_CORP', 1200000, NOW(), NOW()
);

INSERT INTO service_bills (
    Date, OpenTime, Guest, DepartmentId, ServiceId, DescriptionServive, Quantity, Amount,
    Currency, Exchange, Edit, Folio, RegisterId1, RentalRoomId1, CompanyId1,
    RegisterID2, RentalRoomId2, CompanyId2, Username, Status, Outlet,
    Year, Month, Day, CreatedUser, CreatedDate, CreatedHour, created_at, updated_at
)
VALUES
(
    CONCAT(@past_bill_date, ' 12:01:00'), '12:01', 'XAMPP Guest A', 'FO', 'EB', 'XAMPP EB A', 1, 150000,
    'VND', 1, 0, '1', @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id,
    @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id, 'xampp', 1, 'FO',
    YEAR(@past_bill_date), MONTH(@past_bill_date), DAY(@past_bill_date), 'xampp', NOW(), '12:01', NOW(), NOW()
),
(
    CONCAT(@past_bill_date, ' 12:02:00'), '12:02', 'XAMPP Guest A', 'FO', 'ER', 'XAMPP ER A', 1, 50000,
    'VND', 1, 0, '1', @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id,
    @booking_past_a_id, 'XAMPP_OCC_BR_PAST_A', @company_a_id, 'xampp', 1, 'FO',
    YEAR(@past_bill_date), MONTH(@past_bill_date), DAY(@past_bill_date), 'xampp', NOW(), '12:02', NOW(), NOW()
);

INSERT INTO service_bills (
    Date, OpenTime, Guest, DepartmentId, ServiceId, DescriptionServive, Quantity, Amount,
    Currency, Exchange, Edit, Folio, RegisterId1, RentalRoomId1, CompanyId1,
    RegisterID2, RentalRoomId2, CompanyId2, Username, Status, Outlet,
    Year, Month, Day, CreatedUser, CreatedDate, CreatedHour, created_at, updated_at
)
VALUES (
    CONCAT(@past_bill_date, ' 13:00:00'), '13:00', 'XAMPP Guest B', 'FO', 'RM', 'XAMPP RM B', 1, 900000,
    'VND', 1, 0, '1', @booking_past_b_id, 'XAMPP_OCC_BR_PAST_B', @company_b_id,
    @booking_past_b_id, 'XAMPP_OCC_BR_PAST_B', @company_b_id, 'xampp', 1, 'FO',
    YEAR(@past_bill_date), MONTH(@past_bill_date), DAY(@past_bill_date), 'xampp', NOW(), '13:00', NOW(), NOW()
);
SET @rm_b_bill_id = LAST_INSERT_ID();

INSERT INTO room_night_bills (
    bill_id, adult, child, is_room_night, breakfast_amount, extrabed_amount, date,
    room, room_type_id, rate_code, rate, created_at, updated_at
)
VALUES (
    @rm_b_bill_id, 1, 0, 1, 0, 0, @past_bill_date,
    'XAMPP-OCC-02', @room_class_id, 'XAMPP_CORP', 900000, NOW(), NOW()
);

INSERT INTO service_bills (
    Date, OpenTime, Guest, DepartmentId, ServiceId, DescriptionServive, Quantity, Amount,
    Currency, Exchange, Edit, Folio, RegisterId1, RentalRoomId1, CompanyId1,
    RegisterID2, RentalRoomId2, CompanyId2, Username, Status, Outlet,
    Year, Month, Day, CreatedUser, CreatedDate, CreatedHour, created_at, updated_at
)
VALUES (
    CONCAT(@past_bill_date, ' 13:01:00'), '13:01', 'XAMPP Guest B', 'FO', 'ER', 'XAMPP ER B', 1, 30000,
    'VND', 1, 0, '1', @booking_past_b_id, 'XAMPP_OCC_BR_PAST_B', @company_b_id,
    @booking_past_b_id, 'XAMPP_OCC_BR_PAST_B', @company_b_id, 'xampp', 1, 'FO',
    YEAR(@past_bill_date), MONTH(@past_bill_date), DAY(@past_bill_date), 'xampp', NOW(), '13:01', NOW(), NOW()
);

COMMIT;

/* ================================================================
   VERIFY - CHẠY SAU SETUP
   ================================================================ */

SELECT 'SETUP SUMMARY' AS section, @test_from_date AS from_date, @test_to_date AS to_date;

CALL rpt_company_occupancy(
    @test_from_date, @test_to_date, '', '', '', '', 'none', 1
);

CALL rpt_company_occupancy_detail(
    @test_from_date, @test_to_date, '', '', '', '', ''
);

-- Kiểm tra nhanh các booking/bill test đã được ghi nhận.
SELECT b.id, b.booking_name, c.code AS company_code, b.arrival_date, b.departure_date
FROM bookings b
LEFT JOIN companies c ON c.id = b.company_id
WHERE b.booking_name LIKE 'XAMPP_OCC_%'
ORDER BY b.id;

SELECT sb.Ma, sb.ServiceId, sb.Amount, sb.Date, sb.RentalRoomId1
FROM service_bills sb
WHERE sb.RentalRoomId1 LIKE 'XAMPP_OCC_%'
ORDER BY sb.Ma;

/* ================================================================
   CLEANUP - CHỈ CHẠY SAU KHI TEST XONG
   ================================================================ */

-- Xóa dữ liệu phát sinh; không xóa dữ liệu booking thật ngoài prefix XAMPP_OCC_.
-- Bỏ comment từng câu lệnh bên dưới khi cần cleanup.

-- DELETE FROM service_bill_details
-- WHERE Ma IN (
--     SELECT Ma FROM (
--         SELECT Ma FROM service_bills WHERE RentalRoomId1 LIKE 'XAMPP_OCC_%'
--     ) AS test_bills
-- );

-- DELETE FROM room_night_bills
-- WHERE bill_id IN (
--     SELECT Ma FROM (
--         SELECT Ma FROM service_bills WHERE RentalRoomId1 LIKE 'XAMPP_OCC_%'
--     ) AS test_bills
-- );

-- DELETE FROM service_bills WHERE RentalRoomId1 LIKE 'XAMPP_OCC_%';
-- DELETE FROM booking_room_guests WHERE booking_room_id LIKE 'XAMPP_OCC_%';
-- DELETE FROM booking_rooms WHERE id LIKE 'XAMPP_OCC_%';
-- DELETE FROM bookings WHERE booking_name LIKE 'XAMPP_OCC_%';
-- DELETE FROM guests WHERE id LIKE 'XAMPP_OCC_%';
-- DELETE FROM rooms WHERE room_number LIKE 'XAMPP-OCC-%';
-- DELETE FROM companies WHERE code LIKE 'XAMPP_OCC_%';
-- DELETE FROM markets WHERE code = 'XAMPP_OCC_COR';
-- DELETE FROM customer_sources WHERE code = 'XAMPP_OCC_DIRECT';
-- DELETE FROM room_classes WHERE code = 'XAMPP_OCC_ROOM';

