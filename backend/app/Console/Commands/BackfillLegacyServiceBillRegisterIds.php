<?php

namespace App\Console\Commands;

use App\Models\ServiceBill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu import từ phần mềm cũ: bill post tại phòng lưu RegisterID1,2 = NULL và chỉ nhận diện bằng RentalRoomId.
 * Hệ thống mới lọc bill theo booking qua RegisterId1/RegisterID2, nên điền lại mã booking từ booking_rooms
 * của phòng tương ứng. Folio phòng ảo (booking_rooms.booking_id NULL) giữ nguyên NULL.
 */
class BackfillLegacyServiceBillRegisterIds extends Command
{
    protected $signature = 'services:backfill-legacy-register-ids
                            {--apply : Ghi dữ liệu (mặc định chỉ chạy thử và báo số dòng)}';

    protected $description = 'Điền RegisterId1/RegisterID2 cho bill import từ hệ thống cũ (bill phòng có RegisterID NULL) theo booking của phòng.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $counts = ['RegisterId1' => 0, 'RegisterID2' => 0];
        // Bill trỏ tới phòng không có trong booking_rooms: thường do import thiếu phòng thuê, cần rà lại.
        $missingRoomBillIds = [];

        foreach ([['RegisterId1', 'RentalRoomId1'], ['RegisterID2', 'RentalRoomId2']] as [$registerColumn, $roomColumn]) {
            ServiceBill::query()
                ->whereNull($registerColumn)
                ->whereNotNull($roomColumn)
                ->whereNotIn($roomColumn, ['', '0'])
                ->select(['Ma', $roomColumn])
                ->chunkById(500, function ($bills) use ($registerColumn, $roomColumn, $apply, &$counts, &$missingRoomBillIds) {
                    // booking_id NULL = folio phòng ảo: phòng tồn tại nhưng giữ RegisterID NULL.
                    $bookingIdsByRoom = DB::table('booking_rooms')
                        ->whereIn('id', $bills->pluck($roomColumn)->unique()->all())
                        ->pluck('booking_id', 'id');

                    foreach ($bills as $bill) {
                        $roomId = (string) $bill->{$roomColumn};
                        if (!$bookingIdsByRoom->has($roomId)) {
                            $missingRoomBillIds[$bill->Ma] = true;
                            continue;
                        }
                        $bookingId = $bookingIdsByRoom[$roomId];
                        if ($bookingId === null) {
                            continue;
                        }
                        $counts[$registerColumn]++;
                        if ($apply) {
                            ServiceBill::whereKey($bill->Ma)->whereNull($registerColumn)->update([$registerColumn => $bookingId]);
                        }
                    }
                }, 'Ma');
        }

        $this->info(sprintf(
            '%s: RegisterId1 %d dòng, RegisterID2 %d dòng.',
            $apply ? 'Đã cập nhật' : 'Chạy thử (thêm --apply để ghi)',
            $counts['RegisterId1'],
            $counts['RegisterID2']
        ));

        if ($missingRoomBillIds !== []) {
            $sample = implode(', ', array_slice(array_keys($missingRoomBillIds), 0, 20));
            $this->warn(sprintf(
                'Có %d bill trỏ tới phòng không tồn tại trong booking_rooms (chưa điền được mã booking). Mã bill (tối đa 20): %s',
                count($missingRoomBillIds),
                $sample
            ));
        }

        return self::SUCCESS;
    }
}
