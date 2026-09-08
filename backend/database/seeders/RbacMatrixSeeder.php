<?php

namespace Database\Seeders;

use App\Models\BranchRolePermission;
use App\Models\OrganizationDepartment;
use App\Models\Permission;
use App\Models\Position;
use App\Models\PositionBranchRole;
use App\Models\Role;
use App\Models\SystemBranch;
use App\Models\User;
use App\Models\UserBranchPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $system = config('database_domains.system_connection', 'mysql_system');
        $this->command?->info("Using system connection: {$system}");

        // ── 1. Định nghĩa chi tiết 46 Màn hình nghiệp vụ với đủ 4 actions ──
        $screenDefinitions = [
            // ==========================================
            // FO - LỄ TÂN & ĐẶT PHÒNG (RESERVATION & FRONTDESK)
            // ==========================================
            [
                'module' => 'FO',
                'screen_code' => 'fo.booking',
                'screen_name' => 'Đặt phòng (Booking)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.booking.view',   'name' => 'Xem danh sách đặt phòng'],
                    'add'    => ['code' => 'fo.booking.create', 'name' => 'Tạo đặt phòng mới'],
                    'edit'   => ['code' => 'fo.booking.edit',   'name' => 'Sửa thông tin đặt phòng'],
                    'delete' => ['code' => 'fo.booking.cancel', 'name' => 'Hủy đặt phòng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.deposit',
                'screen_name' => 'Đặt cọc (Deposit)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.deposit.view',   'name' => 'Xem danh sách đặt cọc'],
                    'add'    => ['code' => 'fo.deposit.create', 'name' => 'Tạo phiếu đặt cọc'],
                    'edit'   => ['code' => 'fo.deposit.edit',   'name' => 'Sửa phiếu đặt cọc'],
                    'delete' => ['code' => 'fo.deposit.delete', 'name' => 'Hủy phiếu đặt cọc'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.bill',
                'screen_name' => 'Hóa đơn phòng (Bill / Folio)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.bill.view',   'name' => 'Xem hóa đơn phòng'],
                    'add'    => ['code' => 'fo.bill.create', 'name' => 'Tạo hóa đơn / post charge'],
                    'edit'   => ['code' => 'fo.bill.edit',   'name' => 'Chỉnh sửa hóa đơn'],
                    'delete' => ['code' => 'fo.bill.delete', 'name' => 'Xóa / hủy hóa đơn'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.company',
                'screen_name' => 'Công ty & Đại lý (Company / TA)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.company.view',   'name' => 'Xem danh sách công ty/đại lý'],
                    'add'    => ['code' => 'fo.company.create', 'name' => 'Thêm mới công ty/đại lý'],
                    'edit'   => ['code' => 'fo.company.edit',   'name' => 'Sửa thông tin công ty/đại lý'],
                    'delete' => ['code' => 'fo.company.delete', 'name' => 'Xóa công ty/đại lý'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.allotment',
                'screen_name' => 'Phân bổ quỹ phòng (Allotment)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.allotment.view',   'name' => 'Xem phân bổ allotment'],
                    'add'    => ['code' => 'fo.allotment.create', 'name' => 'Tạo allotment mới'],
                    'edit'   => ['code' => 'fo.allotment.edit',   'name' => 'Sửa cấu hình allotment'],
                    'delete' => ['code' => 'fo.allotment.delete', 'name' => 'Xóa allotment'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.guest',
                'screen_name' => 'Tìm kiếm & Hồ sơ khách (Guest Profile)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.guest.view',   'name' => 'Xem thông tin khách hàng'],
                    'add'    => ['code' => 'fo.guest.create', 'name' => 'Thêm mới hồ sơ khách'],
                    'edit'   => ['code' => 'fo.guest.edit',   'name' => 'Sửa thông tin khách hàng'],
                    'delete' => ['code' => 'fo.guest.delete', 'name' => 'Xóa hồ sơ khách hàng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.frontdesk',
                'screen_name' => 'Sơ đồ phòng (Room Rack / FrontDesk)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.frontdesk.view',   'name' => 'Xem sơ đồ phòng FrontDesk'],
                    'add'    => ['code' => 'fo.frontdesk.add',    'name' => 'Gán phòng nhanh từ sơ đồ'],
                    'edit'   => ['code' => 'fo.frontdesk.edit',   'name' => 'Cập nhật trạng thái sơ đồ'],
                    'delete' => ['code' => 'fo.frontdesk.delete', 'name' => 'Xóa gán phòng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.checkin_checkout',
                'screen_name' => 'Giao nhận phòng (Check-in/out)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.checkin_checkout.view', 'name' => 'Xem trạng thái giao nhận phòng'],
                    'add'    => ['code' => 'fo.checkin',               'name' => 'Check-in / Giao phòng'],
                    'edit'   => ['code' => 'fo.checkin_checkout.edit', 'name' => 'Cập nhật giờ nhận/trả'],
                    'delete' => ['code' => 'fo.checkout',              'name' => 'Check-out / Trả phòng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.room_move',
                'screen_name' => 'Chuyển phòng (Room Move)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.room_move.view',   'name' => 'Xem lịch sử chuyển phòng'],
                    'add'    => ['code' => 'fo.room.move',        'name' => 'Thực hiện chuyển phòng'],
                    'edit'   => ['code' => 'fo.room_move.edit',   'name' => 'Điều chỉnh thông tin chuyển phòng'],
                    'delete' => ['code' => 'fo.room_move.delete', 'name' => 'Hủy lệnh chuyển phòng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.room_lock',
                'screen_name' => 'Khóa phòng OOO / OOS (Room Lock)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.room_lock.view',   'name' => 'Xem danh sách khóa phòng'],
                    'add'    => ['code' => 'fo.room_lock.create', 'name' => 'Khóa phòng OOO/OOS'],
                    'edit'   => ['code' => 'fo.room_lock.edit',   'name' => 'Sửa lệnh khóa phòng'],
                    'delete' => ['code' => 'fo.room_lock.delete', 'name' => 'Mở khóa phòng'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.noshow',
                'screen_name' => 'Xử lý No-show',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.noshow.view',     'name' => 'Xem danh sách No-show'],
                    'add'    => ['code' => 'fo.booking.noshow',  'name' => 'Đánh dấu No-show'],
                    'edit'   => ['code' => 'fo.noshow.edit',     'name' => 'Cấn trừ phí No-show'],
                    'delete' => ['code' => 'fo.noshow.cancel',   'name' => 'Hủy đánh dấu No-show'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.payment',
                'screen_name' => 'Thanh toán & Thu tiền (Payment)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.payment.view',   'name' => 'Xem danh sách thanh toán'],
                    'add'    => ['code' => 'fo.payment.create', 'name' => 'Thu tiền / Tạo thanh toán'],
                    'edit'   => ['code' => 'fo.payment.edit',   'name' => 'Sửa thanh toán'],
                    'delete' => ['code' => 'fo.payment.delete', 'name' => 'Hủy / hoàn thanh toán'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.debt_settlement',
                'screen_name' => 'Cấn trừ công nợ (Debt Settlement)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.debt.view',   'name' => 'Xem công nợ khách hàng'],
                    'add'    => ['code' => 'fo.debt.create', 'name' => 'Lập phiếu cấn trừ công nợ'],
                    'edit'   => ['code' => 'fo.debt.edit',   'name' => 'Sửa phiếu công nợ'],
                    'delete' => ['code' => 'fo.debt.delete', 'name' => 'Hủy phiếu công nợ'],
                ],
            ],
            [
                'module' => 'FO',
                'screen_code' => 'fo.service',
                'screen_name' => 'Dịch vụ phòng (Room Service)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fo.service.view',   'name' => 'Xem danh sách dịch vụ phòng'],
                    'add'    => ['code' => 'fo.service.add',    'name' => 'Thêm dịch vụ vào phòng'],
                    'edit'   => ['code' => 'fo.service.edit',   'name' => 'Sửa phí dịch vụ phòng'],
                    'delete' => ['code' => 'fo.service.delete', 'name' => 'Hủy dịch vụ phòng'],
                ],
            ],

            // ==========================================
            // HK - BUỒNG PHÒNG (HOUSEKEEPING)
            // ==========================================
            [
                'module' => 'HK',
                'screen_code' => 'hk.overview',
                'screen_name' => 'Tổng quan buồng phòng',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.view',            'name' => 'Xem tổng quan buồng phòng'],
                    'add'    => ['code' => 'hk.overview.add',    'name' => 'Thêm khu vực buồng phòng'],
                    'edit'   => ['code' => 'hk.overview.edit',   'name' => 'Cập nhật cấu hình buồng'],
                    'delete' => ['code' => 'hk.overview.delete', 'name' => 'Xóa cấu hình buồng'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.room_status',
                'screen_name' => 'Trạng thái phòng (Room Status)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.room_status.view',   'name' => 'Xem trạng thái phòng'],
                    'add'    => ['code' => 'hk.room_status.add',    'name' => 'Đổi trạng thái Clean/Dirty'],
                    'edit'   => ['code' => 'hk.room.status',        'name' => 'Cập nhật trạng thái phòng'],
                    'delete' => ['code' => 'hk.room_status.delete', 'name' => 'Khôi phục trạng thái cũ'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.assign',
                'screen_name' => 'Phân công dọn phòng (Assignment)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.assign.view',   'name' => 'Xem danh sách phân công'],
                    'add'    => ['code' => 'hk.assign.create', 'name' => 'Tạo lượt phân công'],
                    'edit'   => ['code' => 'hk.assign',        'name' => 'Phân công nhân viên buồng'],
                    'delete' => ['code' => 'hk.assign.delete', 'name' => 'Hủy phân công dọn phòng'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.lost_found',
                'screen_name' => 'Quản lý đồ thất lạc (Lost & Found)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.lost_found.view',   'name' => 'Xem danh sách đồ thất lạc'],
                    'add'    => ['code' => 'hk.lost_found.create', 'name' => 'Tiếp nhận đồ thất lạc'],
                    'edit'   => ['code' => 'hk.lost_found.manage', 'name' => 'Quản lý / trả đồ thất lạc'],
                    'delete' => ['code' => 'hk.lost_found.delete', 'name' => 'Hủy / xóa đồ thất lạc'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.service',
                'screen_name' => 'Hóa đơn dịch vụ HK (Minibar/Laundry)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.service.view',   'name' => 'Xem hóa đơn dịch vụ HK'],
                    'add'    => ['code' => 'hk.service.bill',   'name' => 'Lập hóa đơn giặt ủi/minibar'],
                    'edit'   => ['code' => 'hk.service.edit',   'name' => 'Sửa hóa đơn HK'],
                    'delete' => ['code' => 'hk.service.delete', 'name' => 'Hủy hóa đơn HK'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.warehouse',
                'screen_name' => 'Kho buồng phòng & Vải vóc',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.warehouse.view',   'name' => 'Xem tồn kho buồng phòng'],
                    'add'    => ['code' => 'hk.warehouse.import', 'name' => 'Nhập kho buồng phòng'],
                    'edit'   => ['code' => 'hk.warehouse.manage', 'name' => 'Quản lý xuất/nhập kho HK'],
                    'delete' => ['code' => 'hk.warehouse.delete', 'name' => 'Xuất hủy kho HK'],
                ],
            ],
            [
                'module' => 'HK',
                'screen_code' => 'hk.report',
                'screen_name' => 'Báo cáo buồng phòng',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'hk.report.view',   'name' => 'Xem báo cáo buồng phòng'],
                    'add'    => ['code' => 'hk.report.create', 'name' => 'Xuất báo cáo buồng phòng'],
                    'edit'   => ['code' => 'hk.report.edit',   'name' => 'Sửa mẫu báo cáo buồng'],
                    'delete' => ['code' => 'hk.report.delete', 'name' => 'Xóa báo cáo buồng'],
                ],
            ],

            // ==========================================
            // FB - NHÀ HÀNG & F&B
            // ==========================================
            [
                'module' => 'FB',
                'screen_code' => 'fb.overview',
                'screen_name' => 'Tổng quan Nhà Hàng (Outlets)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fb.view',            'name' => 'Xem tổng quan nhà hàng'],
                    'add'    => ['code' => 'fb.overview.add',    'name' => 'Thêm cơ sở outlet'],
                    'edit'   => ['code' => 'fb.overview.edit',   'name' => 'Sửa thông tin outlet'],
                    'delete' => ['code' => 'fb.overview.delete', 'name' => 'Xóa cơ sở outlet'],
                ],
            ],
            [
                'module' => 'FB',
                'screen_code' => 'fb.order',
                'screen_name' => 'Quản lý Order & Gọi món',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fb.order.view',   'name' => 'Xem danh sách order'],
                    'add'    => ['code' => 'fb.order.create', 'name' => 'Tạo order / gọi món'],
                    'edit'   => ['code' => 'fb.order.edit',   'name' => 'Sửa món / thêm món order'],
                    'delete' => ['code' => 'fb.order.cancel', 'name' => 'Hủy order / trả món'],
                ],
            ],
            [
                'module' => 'FB',
                'screen_code' => 'fb.payment',
                'screen_name' => 'Thanh toán F&B (Cashier)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fb.payment.view',   'name' => 'Xem thanh toán F&B'],
                    'add'    => ['code' => 'fb.payment',        'name' => 'Thanh toán hóa đơn F&B'],
                    'edit'   => ['code' => 'fb.payment.edit',   'name' => 'Sửa thanh toán / giảm giá'],
                    'delete' => ['code' => 'fb.payment.delete', 'name' => 'Hủy thanh toán F&B'],
                ],
            ],
            [
                'module' => 'FB',
                'screen_code' => 'fb.menu',
                'screen_name' => 'Menu & Sản phẩm (Products)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fb.menu.view',   'name' => 'Xem danh mục thực đơn'],
                    'add'    => ['code' => 'fb.menu.create', 'name' => 'Thêm món ăn / sản phẩm'],
                    'edit'   => ['code' => 'fb.menu.manage', 'name' => 'Sửa thực đơn & giá bán'],
                    'delete' => ['code' => 'fb.menu.delete', 'name' => 'Xóa món khỏi thực đơn'],
                ],
            ],
            [
                'module' => 'FB',
                'screen_code' => 'fb.party',
                'screen_name' => 'Tiệc & Sự kiện (Events & Parties)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'fb.party.view',   'name' => 'Xem danh sách tiệc'],
                    'add'    => ['code' => 'fb.party.create', 'name' => 'Đặt tiệc / sự kiện mới'],
                    'edit'   => ['code' => 'fb.party.manage', 'name' => 'Cập nhật lịch đặt tiệc'],
                    'delete' => ['code' => 'fb.party.delete', 'name' => 'Hủy đặt tiệc'],
                ],
            ],

            // ==========================================
            // MGMT - BÁO CÁO & QUẢN TRỊ KINH DOANH
            // ==========================================
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.report',
                'screen_name' => 'Báo cáo quản lý tổng hợp',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.report.view',   'name' => 'Xem báo cáo tổng hợp'],
                    'add'    => ['code' => 'mgmt.report.export', 'name' => 'Xuất file báo cáo'],
                    'edit'   => ['code' => 'mgmt.report.edit',   'name' => 'Tùy chỉnh mẫu báo cáo'],
                    'delete' => ['code' => 'mgmt.report.delete', 'name' => 'Xóa mẫu báo cáo'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.revenue',
                'screen_name' => 'Báo cáo doanh thu (Revenue)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.revenue.view',   'name' => 'Xem báo cáo doanh thu'],
                    'add'    => ['code' => 'mgmt.revenue.export', 'name' => 'Xuất báo cáo doanh thu'],
                    'edit'   => ['code' => 'mgmt.revenue.edit',   'name' => 'Cấu hình chỉ số doanh thu'],
                    'delete' => ['code' => 'mgmt.revenue.delete', 'name' => 'Xóa chỉ số báo cáo'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.occupancy',
                'screen_name' => 'Báo cáo công suất phòng (Occupancy)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.occupancy.view',   'name' => 'Xem công suất phòng'],
                    'add'    => ['code' => 'mgmt.occupancy.export', 'name' => 'Xuất báo cáo công suất'],
                    'edit'   => ['code' => 'mgmt.occupancy.edit',   'name' => 'Cấu hình phân tích công suất'],
                    'delete' => ['code' => 'mgmt.occupancy.delete', 'name' => 'Xóa cấu hình'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.arriving',
                'screen_name' => 'Báo cáo khách đến (Arriving Guests)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.arriving.view',   'name' => 'Xem báo cáo khách đến'],
                    'add'    => ['code' => 'mgmt.arriving.export', 'name' => 'Xuất danh sách khách đến'],
                    'edit'   => ['code' => 'mgmt.arriving.edit',   'name' => 'Lọc tiêu chí khách đến'],
                    'delete' => ['code' => 'mgmt.arriving.delete', 'name' => 'Xóa bộ lọc'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.departing',
                'screen_name' => 'Báo cáo khách đi (Departing Guests)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.departing.view',   'name' => 'Xem báo cáo khách đi'],
                    'add'    => ['code' => 'mgmt.departing.export', 'name' => 'Xuất danh sách khách đi'],
                    'edit'   => ['code' => 'mgmt.departing.edit',   'name' => 'Lọc tiêu chí khách đi'],
                    'delete' => ['code' => 'mgmt.departing.delete', 'name' => 'Xóa bộ lọc'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.inhouse',
                'screen_name' => 'Báo cáo khách lưu trú (In-house Guests)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.inhouse.view',   'name' => 'Xem danh sách khách lưu trú'],
                    'add'    => ['code' => 'mgmt.inhouse.export', 'name' => 'Xuất danh sách khách lưu trú'],
                    'edit'   => ['code' => 'mgmt.inhouse.edit',   'name' => 'Lọc theo hạng phòng/quốc tịch'],
                    'delete' => ['code' => 'mgmt.inhouse.delete', 'name' => 'Xóa bộ lọc'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.cancelled',
                'screen_name' => 'Báo cáo hủy đặt phòng (Cancelled)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.cancelled.view',   'name' => 'Xem báo cáo hủy phòng'],
                    'add'    => ['code' => 'mgmt.cancelled.export', 'name' => 'Xuất danh sách hủy phòng'],
                    'edit'   => ['code' => 'mgmt.cancelled.edit',   'name' => 'Thống kê lý do hủy phòng'],
                    'delete' => ['code' => 'mgmt.cancelled.delete', 'name' => 'Xóa tiêu chí thống kê'],
                ],
            ],
            [
                'module' => 'MGMT',
                'screen_code' => 'mgmt.activity_log',
                'screen_name' => 'Lịch sử thao tác hệ thống (Audit Logs)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'mgmt.activity_log',        'name' => 'Xem nhật ký thao tác'],
                    'add'    => ['code' => 'mgmt.activity_log.export', 'name' => 'Xuất file nhật ký thao tác'],
                    'edit'   => ['code' => 'mgmt.activity_log.edit',   'name' => 'Cấu hình thời gian lưu log'],
                    'delete' => ['code' => 'mgmt.activity_log.delete', 'name' => 'Xóa log cũ'],
                ],
            ],

            // ==========================================
            // CONFIG - CẤU HÌNH KHÁCH SẠN
            // ==========================================
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.hotel',
                'screen_name' => 'Cài đặt thông tin khách sạn',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.hotel.view',   'name' => 'Xem thông tin khách sạn'],
                    'add'    => ['code' => 'config.hotel.add',    'name' => 'Thêm thông tin tiện ích'],
                    'edit'   => ['code' => 'config.hotel.edit',   'name' => 'Sửa thông tin khách sạn'],
                    'delete' => ['code' => 'config.hotel.delete', 'name' => 'Khôi phục mặc định'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.room_classes',
                'screen_name' => 'Hạng phòng & Loại phòng',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.room_classes.view',   'name' => 'Xem hạng phòng'],
                    'add'    => ['code' => 'config.room_classes.create', 'name' => 'Thêm hạng phòng mới'],
                    'edit'   => ['code' => 'config.room_classes.edit',   'name' => 'Sửa hạng phòng'],
                    'delete' => ['code' => 'config.room_classes.delete', 'name' => 'Xóa hạng phòng'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.rooms',
                'screen_name' => 'Danh mục buồng phòng (Rooms)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.rooms.view',   'name' => 'Xem danh sách phòng'],
                    'add'    => ['code' => 'config.rooms.create', 'name' => 'Thêm phòng mới'],
                    'edit'   => ['code' => 'config.rooms.edit',   'name' => 'Sửa thông tin số phòng'],
                    'delete' => ['code' => 'config.rooms.delete', 'name' => 'Xóa phòng'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.rates',
                'screen_name' => 'Bảng giá & Kế hoạch giá (Rate Plans)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.rates.view',   'name' => 'Xem bảng giá phòng'],
                    'add'    => ['code' => 'config.rates.create', 'name' => 'Tạo bảng giá / giá chuẩn'],
                    'edit'   => ['code' => 'config.rates.edit',   'name' => 'Cập nhật chính sách giá'],
                    'delete' => ['code' => 'config.rates.delete', 'name' => 'Xóa bảng giá'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.services',
                'screen_name' => 'Danh mục dịch vụ khách sạn',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.services.view',   'name' => 'Xem danh mục dịch vụ'],
                    'add'    => ['code' => 'config.services.create', 'name' => 'Thêm dịch vụ mới'],
                    'edit'   => ['code' => 'config.services.edit',   'name' => 'Sửa đơn giá dịch vụ'],
                    'delete' => ['code' => 'config.services.delete', 'name' => 'Xóa dịch vụ'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.shifts',
                'screen_name' => 'Danh mục ca làm việc (Shifts)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.shifts.view',   'name' => 'Xem danh sách ca'],
                    'add'    => ['code' => 'config.shifts.create', 'name' => 'Tạo ca làm việc'],
                    'edit'   => ['code' => 'config.shifts.edit',   'name' => 'Cập nhật giờ ca'],
                    'delete' => ['code' => 'config.shifts.delete', 'name' => 'Xóa ca làm việc'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.markets',
                'screen_name' => 'Nguồn khách & Thị trường (Markets)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.markets.view',   'name' => 'Xem thị trường & nguồn khách'],
                    'add'    => ['code' => 'config.markets.create', 'name' => 'Thêm nguồn khách mới'],
                    'edit'   => ['code' => 'config.markets.edit',   'name' => 'Sửa mã nguồn/thị trường'],
                    'delete' => ['code' => 'config.markets.delete', 'name' => 'Xóa nguồn khách'],
                ],
            ],
            [
                'module' => 'CONFIG',
                'screen_code' => 'config.date_roll',
                'screen_name' => 'Ngày hệ thống & Đóng ngày (Night Audit)',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'config.date_roll.view',   'name' => 'Xem ngày làm việc hệ thống'],
                    'add'    => ['code' => 'config.date_roll.roll',   'name' => 'Thực hiện chuyển ngày (Roll)'],
                    'edit'   => ['code' => 'config.date_roll.edit',   'name' => 'Điều chỉnh ngày nghiệp vụ'],
                    'delete' => ['code' => 'config.date_roll.delete', 'name' => 'Khôi phục ngày trước đó'],
                ],
            ],

            // ==========================================
            // SYSTEM - HỆ THỐNG QUẢN TRỊ
            // ==========================================
            [
                'module' => 'SYSTEM',
                'screen_code' => 'system.user',
                'screen_name' => 'Quản lý nhân viên & Tài khoản',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'system.user.view',   'name' => 'Xem danh sách nhân viên'],
                    'add'    => ['code' => 'system.user.create', 'name' => 'Thêm nhân viên mới'],
                    'edit'   => ['code' => 'system.user.manage', 'name' => 'Quản lý thông tin nhân viên'],
                    'delete' => ['code' => 'system.user.delete', 'name' => 'Khóa / xóa tài khoản nhân viên'],
                ],
            ],
            [
                'module' => 'SYSTEM',
                'screen_code' => 'system.role',
                'screen_name' => 'Vai trò & Phân quyền',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'system.role.view',   'name' => 'Xem danh sách vai trò'],
                    'add'    => ['code' => 'system.role.create', 'name' => 'Tạo vai trò mới'],
                    'edit'   => ['code' => 'system.role.manage', 'name' => 'Phân quyền chi tiết vai trò'],
                    'delete' => ['code' => 'system.role.delete', 'name' => 'Xóa vai trò'],
                ],
            ],
            [
                'module' => 'SYSTEM',
                'screen_code' => 'system.branch',
                'screen_name' => 'Quản lý chi nhánh khách sạn',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'system.branch.view',   'name' => 'Xem danh sách chi nhánh'],
                    'add'    => ['code' => 'system.branch.create', 'name' => 'Thêm chi nhánh mới'],
                    'edit'   => ['code' => 'system.branch.manage', 'name' => 'Sửa thông tin chi nhánh'],
                    'delete' => ['code' => 'system.branch.delete', 'name' => 'Ngừng hoạt động chi nhánh'],
                ],
            ],
            [
                'module' => 'SYSTEM',
                'screen_code' => 'system.setting',
                'screen_name' => 'Cài đặt hệ thống toàn cục',
                'app' => 'PMS',
                'actions' => [
                    'view'   => ['code' => 'system.setting',        'name' => 'Xem cấu hình hệ thống'],
                    'add'    => ['code' => 'system.setting.create', 'name' => 'Thêm cấu hình mới'],
                    'edit'   => ['code' => 'system.setting.edit',   'name' => 'Sửa thông số hệ thống'],
                    'delete' => ['code' => 'system.setting.delete', 'name' => 'Xóa thông số'],
                ],
            ],
        ];

        $order = 1;
        $createdPermissionIds = [];
        $codeToIdMap = [];

        foreach ($screenDefinitions as $screen) {
            foreach (['view', 'add', 'delete', 'edit'] as $action) {
                $actInfo = $screen['actions'][$action];
                $perm = Permission::on($system)->updateOrCreate(
                    ['code' => $actInfo['code']],
                    [
                        'name' => $actInfo['name'],
                        'module' => $screen['module'],
                        'application_code' => $screen['app'],
                        'screen_code' => $screen['screen_code'],
                        'screen_name' => $screen['screen_name'],
                        'action' => $action,
                        'screen_type' => 'screen',
                        'sort_order' => $order++,
                    ]
                );
                $createdPermissionIds[] = $perm->id;
                $codeToIdMap[$actInfo['code']] = $perm->id;
            }
        }

        $this->command?->info("Updated/Created " . count($createdPermissionIds) . " permissions across " . count($screenDefinitions) . " detailed screens.");

        // ── 2. Thiết lập ánh xạ quyền mặc định cho từng Role ──
        $allPermissionIds = $createdPermissionIds;

        $roleMappings = [
            'super_admin' => $allPermissionIds,

            'branch_admin' => [
                // FO - Full
                'fo.booking.view', 'fo.booking.create', 'fo.booking.edit', 'fo.booking.cancel',
                'fo.deposit.view', 'fo.deposit.create', 'fo.deposit.edit', 'fo.deposit.delete',
                'fo.bill.view', 'fo.bill.create', 'fo.bill.edit', 'fo.bill.delete',
                'fo.company.view', 'fo.company.create', 'fo.company.edit', 'fo.company.delete',
                'fo.allotment.view', 'fo.allotment.create', 'fo.allotment.edit', 'fo.allotment.delete',
                'fo.guest.view', 'fo.guest.create', 'fo.guest.edit', 'fo.guest.delete',
                'fo.frontdesk.view', 'fo.frontdesk.add', 'fo.frontdesk.edit', 'fo.frontdesk.delete',
                'fo.checkin_checkout.view', 'fo.checkin', 'fo.checkin_checkout.edit', 'fo.checkout',
                'fo.room_move.view', 'fo.room.move', 'fo.room_move.edit', 'fo.room_move.delete',
                'fo.room_lock.view', 'fo.room_lock.create', 'fo.room_lock.edit', 'fo.room_lock.delete',
                'fo.noshow.view', 'fo.booking.noshow', 'fo.noshow.edit', 'fo.noshow.cancel',
                'fo.payment.view', 'fo.payment.create', 'fo.payment.edit', 'fo.payment.delete',
                'fo.debt.view', 'fo.debt.create', 'fo.debt.edit', 'fo.debt.delete',
                'fo.service.view', 'fo.service.add', 'fo.service.edit', 'fo.service.delete',
                // HK - Full
                'hk.view', 'hk.overview.add', 'hk.overview.edit', 'hk.overview.delete',
                'hk.room_status.view', 'hk.room_status.add', 'hk.room.status', 'hk.room_status.delete',
                'hk.assign.view', 'hk.assign.create', 'hk.assign', 'hk.assign.delete',
                'hk.lost_found.view', 'hk.lost_found.create', 'hk.lost_found.manage', 'hk.lost_found.delete',
                'hk.service.view', 'hk.service.bill', 'hk.service.edit', 'hk.service.delete',
                'hk.warehouse.view', 'hk.warehouse.import', 'hk.warehouse.manage', 'hk.warehouse.delete',
                'hk.report.view', 'hk.report.create', 'hk.report.edit', 'hk.report.delete',
                // FB - Full
                'fb.view', 'fb.overview.add', 'fb.overview.edit', 'fb.overview.delete',
                'fb.order.view', 'fb.order.create', 'fb.order.edit', 'fb.order.cancel',
                'fb.payment.view', 'fb.payment', 'fb.payment.edit', 'fb.payment.delete',
                'fb.menu.view', 'fb.menu.create', 'fb.menu.manage', 'fb.menu.delete',
                'fb.party.view', 'fb.party.create', 'fb.party.manage', 'fb.party.delete',
                // MGMT - Full
                'mgmt.report.view', 'mgmt.revenue.view', 'mgmt.occupancy.view',
                'mgmt.arriving.view', 'mgmt.departing.view', 'mgmt.inhouse.view', 'mgmt.cancelled.view',
                'mgmt.activity_log',
                // CONFIG - Full
                'config.hotel.view', 'config.hotel.edit',
                'config.room_classes.view', 'config.room_classes.create', 'config.room_classes.edit', 'config.room_classes.delete',
                'config.rooms.view', 'config.rooms.create', 'config.rooms.edit', 'config.rooms.delete',
                'config.rates.view', 'config.rates.create', 'config.rates.edit', 'config.rates.delete',
                'config.services.view', 'config.services.create', 'config.services.edit', 'config.services.delete',
                'config.shifts.view', 'config.shifts.create', 'config.shifts.edit', 'config.shifts.delete',
                'config.markets.view', 'config.markets.create', 'config.markets.edit', 'config.markets.delete',
                'config.date_roll.view', 'config.date_roll.roll', 'config.date_roll.edit',
                // SYSTEM
                'system.user.view', 'system.user.manage',
            ],

            'mgmt' => [
                'fo.booking.view', 'fo.deposit.view', 'fo.bill.view', 'fo.company.view', 'fo.allotment.view',
                'fo.guest.view', 'fo.frontdesk.view', 'fo.checkin_checkout.view', 'fo.room_move.view',
                'fo.room_lock.view', 'fo.noshow.view', 'fo.payment.view', 'fo.debt.view', 'fo.service.view',
                'hk.view', 'hk.room_status.view', 'hk.assign.view', 'hk.lost_found.view', 'hk.service.view', 'hk.warehouse.view', 'hk.report.view',
                'fb.view', 'fb.order.view', 'fb.payment.view', 'fb.menu.view', 'fb.party.view',
                'mgmt.report.view', 'mgmt.report.export',
                'mgmt.revenue.view', 'mgmt.revenue.export',
                'mgmt.occupancy.view', 'mgmt.occupancy.export',
                'mgmt.arriving.view', 'mgmt.arriving.export',
                'mgmt.departing.view', 'mgmt.departing.export',
                'mgmt.inhouse.view', 'mgmt.inhouse.export',
                'mgmt.cancelled.view', 'mgmt.cancelled.export',
                'mgmt.activity_log',
                'config.hotel.view', 'config.room_classes.view', 'config.rooms.view', 'config.rates.view',
                'config.services.view', 'config.shifts.view', 'config.markets.view', 'config.date_roll.view',
                'system.user.view',
            ],

            'fo_manager' => [
                'fo.booking.view', 'fo.booking.create', 'fo.booking.edit', 'fo.booking.cancel',
                'fo.deposit.view', 'fo.deposit.create', 'fo.deposit.edit', 'fo.deposit.delete',
                'fo.bill.view', 'fo.bill.create', 'fo.bill.edit', 'fo.bill.delete',
                'fo.company.view', 'fo.company.create', 'fo.company.edit', 'fo.company.delete',
                'fo.allotment.view', 'fo.allotment.create', 'fo.allotment.edit', 'fo.allotment.delete',
                'fo.guest.view', 'fo.guest.create', 'fo.guest.edit', 'fo.guest.delete',
                'fo.frontdesk.view', 'fo.frontdesk.add', 'fo.frontdesk.edit', 'fo.frontdesk.delete',
                'fo.checkin_checkout.view', 'fo.checkin', 'fo.checkin_checkout.edit', 'fo.checkout',
                'fo.room_move.view', 'fo.room.move', 'fo.room_move.edit', 'fo.room_move.delete',
                'fo.room_lock.view', 'fo.room_lock.create', 'fo.room_lock.edit', 'fo.room_lock.delete',
                'fo.noshow.view', 'fo.booking.noshow', 'fo.noshow.edit', 'fo.noshow.cancel',
                'fo.payment.view', 'fo.payment.create', 'fo.payment.edit', 'fo.payment.delete',
                'fo.debt.view', 'fo.debt.create', 'fo.debt.edit', 'fo.debt.delete',
                'fo.service.view', 'fo.service.add', 'fo.service.edit', 'fo.service.delete',
                'mgmt.arriving.view', 'mgmt.departing.view', 'mgmt.inhouse.view', 'mgmt.cancelled.view',
                'mgmt.activity_log',
            ],

            'fo_staff' => [
                'fo.booking.view', 'fo.booking.create', 'fo.booking.edit',
                'fo.deposit.view', 'fo.deposit.create',
                'fo.bill.view', 'fo.bill.create', 'fo.bill.edit',
                'fo.company.view',
                'fo.guest.view', 'fo.guest.create', 'fo.guest.edit',
                'fo.frontdesk.view',
                'fo.checkin_checkout.view', 'fo.checkin', 'fo.checkout',
                'fo.room_move.view', 'fo.room.move',
                'fo.room_lock.view',
                'fo.noshow.view',
                'fo.payment.view', 'fo.payment.create',
                'fo.service.view', 'fo.service.add',
                'mgmt.arriving.view', 'mgmt.departing.view', 'mgmt.inhouse.view',
            ],

            'hk_manager' => [
                'hk.view', 'hk.overview.add', 'hk.overview.edit', 'hk.overview.delete',
                'hk.room_status.view', 'hk.room_status.add', 'hk.room.status', 'hk.room_status.delete',
                'hk.assign.view', 'hk.assign.create', 'hk.assign', 'hk.assign.delete',
                'hk.lost_found.view', 'hk.lost_found.create', 'hk.lost_found.manage', 'hk.lost_found.delete',
                'hk.service.view', 'hk.service.bill', 'hk.service.edit', 'hk.service.delete',
                'hk.warehouse.view', 'hk.warehouse.import', 'hk.warehouse.manage', 'hk.warehouse.delete',
                'hk.report.view', 'hk.report.create', 'hk.report.edit', 'hk.report.delete',
                'fo.frontdesk.view',
                'mgmt.activity_log',
            ],

            'hk_staff' => [
                'hk.view',
                'hk.room_status.view', 'hk.room.status',
                'hk.assign.view',
                'hk.lost_found.view', 'hk.lost_found.create',
                'hk.service.view', 'hk.service.bill',
                'fo.frontdesk.view',
            ],

            'fb_manager' => [
                'fb.view', 'fb.overview.add', 'fb.overview.edit', 'fb.overview.delete',
                'fb.order.view', 'fb.order.create', 'fb.order.edit', 'fb.order.cancel',
                'fb.payment.view', 'fb.payment', 'fb.payment.edit', 'fb.payment.delete',
                'fb.menu.view', 'fb.menu.create', 'fb.menu.manage', 'fb.menu.delete',
                'fb.party.view', 'fb.party.create', 'fb.party.manage', 'fb.party.delete',
                'mgmt.activity_log',
            ],

            'fb_staff' => [
                'fb.view',
                'fb.order.view', 'fb.order.create', 'fb.order.edit',
                'fb.payment.view', 'fb.payment',
                'fb.menu.view',
            ],
        ];

        // ── 3. Đồng bộ role_permissions (bảng gốc) ──
        $roles = Role::on($system)->get()->keyBy('code');
        foreach ($roleMappings as $roleCode => $perms) {
            $role = $roles->get($roleCode);
            if (!$role) continue;

            $ids = is_array($perms) && isset($perms[0]) && is_int($perms[0])
                ? $perms
                : collect($perms)->map(fn($c) => $codeToIdMap[$c] ?? null)->filter()->values()->all();

            $role->permissions()->sync($ids);
        }

        // ── 4. Backfill branch_role_permissions cho TẤT CẢ chi nhánh ──
        $branches = SystemBranch::on($system)->get();
        $this->command?->info("Backfilling branch_role_permissions for " . $branches->count() . " branches...");

        DB::connection($system)->transaction(function () use ($system, $branches, $roles, $roleMappings, $codeToIdMap) {
            foreach ($branches as $branch) {
                foreach ($roleMappings as $roleCode => $perms) {
                    $role = $roles->get($roleCode);
                    if (!$role) continue;

                    $ids = is_array($perms) && isset($perms[0]) && is_int($perms[0])
                        ? $perms
                        : collect($perms)->map(fn($c) => $codeToIdMap[$c] ?? null)->filter()->values()->all();

                    // Đảm bảo có View cho mọi Add/Edit/Delete
                    $viewIds = Permission::on($system)
                        ->whereIn('id', $ids)
                        ->where('action', '!=', 'view')
                        ->pluck('screen_code')
                        ->filter();
                    $extraViewIds = Permission::on($system)
                        ->whereIn('screen_code', $viewIds)
                        ->where('action', 'view')
                        ->pluck('id')
                        ->all();

                    $finalIds = array_unique(array_merge($ids, $extraViewIds));

                    foreach ($finalIds as $pId) {
                        BranchRolePermission::on($system)->updateOrCreate(
                            [
                                'system_branch_id' => $branch->id,
                                'role_id' => $role->id,
                                'permission_id' => $pId,
                            ],
                            []
                        );
                    }
                }
            }
        });

        $this->command?->info("✅ Backfill branch_role_permissions complete: " . BranchRolePermission::on($system)->count() . " records.");

        // ── 5. Đảm bảo Position & PositionBranchRole cho Super Admin & các vị trí mặc định ──
        $defaultPositions = [
            ['dept' => 'SA', 'code' => 'SUPER_ADMIN',  'name' => 'Super Administrator',   'role' => 'super_admin'],
            ['dept' => 'SA', 'code' => 'BRANCH_ADMIN', 'name' => 'Quản Trị Chi Nhánh',    'role' => 'branch_admin'],
            ['dept' => 'EI', 'code' => 'MGMT',         'name' => 'Quản Lý',               'role' => 'mgmt'],
            ['dept' => 'FO', 'code' => 'FOM',          'name' => 'Trưởng Lễ Tân',         'role' => 'fo_manager'],
            ['dept' => 'FO', 'code' => 'FO',           'name' => 'Nhân Viên Lễ Tân',      'role' => 'fo_staff'],
            ['dept' => 'HK', 'code' => 'HKM',          'name' => 'Trưởng Buồng Phòng',    'role' => 'hk_manager'],
            ['dept' => 'HK', 'code' => 'HK',           'name' => 'Nhân Viên Buồng Phòng', 'role' => 'hk_staff'],
            ['dept' => 'FB', 'code' => 'FBM',          'name' => 'Trưởng Nhà Hàng',       'role' => 'fb_manager'],
            ['dept' => 'FB', 'code' => 'FB',           'name' => 'Nhân Viên Nhà Hàng',    'role' => 'fb_staff'],
        ];

        foreach ($defaultPositions as $dp) {
            $dept = OrganizationDepartment::on($system)->where('code', $dp['dept'])->first();
            if (!$dept) continue;

            $pos = Position::on($system)->firstOrCreate(
                ['code' => $dp['code'], 'organization_department_id' => $dept->id],
                ['name' => $dp['name'], 'is_active' => true, 'sort_order' => 1]
            );

            $role = $roles->get($dp['role']);
            if (!$role) continue;

            foreach ($branches as $branch) {
                PositionBranchRole::on($system)->firstOrCreate(
                    [
                        'position_id' => $pos->id,
                        'system_branch_id' => $branch->id,
                        'application_code' => 'PMS',
                    ],
                    [
                        'role_id' => $role->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        // ── 6. Gán Super Admin cho user Super Admin ──
        $superRole = $roles->get('super_admin');
        if ($superRole) {
            $superPos = Position::on($system)->where('code', 'SUPER_ADMIN')->first();
            $superUsers = User::on($system)->whereHas('roles', fn($q) => $q->where('roles.id', $superRole->id))->get();
            if ($superUsers->isEmpty()) {
                $superUsers = User::on($system)->where('username', 'testuser')->orWhere('email', 'test@example.com')->get();
            }

            foreach ($superUsers as $u) {
                foreach ($branches as $branch) {
                    UserBranchPosition::on($system)->firstOrCreate(
                        [
                            'user_id' => $u->id,
                            'system_branch_id' => $branch->id,
                            'application_code' => 'PMS',
                        ],
                        [
                            'position_id' => $superPos?->id ?? 1,
                        ]
                    );
                }
            }
        }

        $this->command?->info("✅ RBAC Matrix Seeder executed successfully!");
    }
}
