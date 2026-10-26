<?php

namespace App\Services;

use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dòng 451: lọc "Tình trạng lưu trú" của Tìm kiếm chung theo nghiệp vụ Link Hotel
 * (cột Arrival/Departure/CheckIn/CheckOut/InHouse/Cancel trong sp_041, sp_043).
 *
 * Không bật ngày (@all = 1) lọc theo tình trạng phòng; bật ngày thì kèm điều kiện
 * ngày của từng loại. Áp dụng trên booking_rooms.
 */
class StayStatusFilter
{
    public const CATEGORIES = ['arrival', 'departure', 'checkin', 'checkout', 'inhouse', 'cancel'];

    private ?string $systemDate = null;

    public function apply(Builder $rooms, string $category, ?string $fromDate, ?string $toDate): void
    {
        $all = !$fromDate || !$toDate;
        $from = $all ? null : Carbon::parse($fromDate)->toDateString();
        $to = $all ? null : Carbon::parse($toDate)->toDateString();
        $sysDate = $this->systemDate();
        $status = 'booking_rooms.status';
        $arrival = 'booking_rooms.arrival_date';
        $departure = 'booking_rooms.departure_date';

        $rooms->where(function (Builder $q) use ($category, $all, $from, $to, $sysDate, $status, $arrival, $departure) {
            match ($category) {
                // Đến: phòng chưa check in, bật ngày thì ngày đến trong khoảng tìm.
                'arrival' => $all
                    ? $q->where($status, BookingRoom::STATUS_BOOKED)
                    : $q->where($status, BookingRoom::STATUS_BOOKED)->whereDate($arrival, '>=', $from)->whereDate($arrival, '<=', $to),
                // Đi: phòng chưa check out, bật ngày thì ngày đi trong khoảng tìm.
                'departure' => $all
                    ? $q->where($status, BookingRoom::STATUS_CHECKED_IN)
                    : $q->whereIn($status, [BookingRoom::STATUS_BOOKED, BookingRoom::STATUS_CHECKED_IN])->whereDate($departure, '>=', $from)->whereDate($departure, '<=', $to),
                // Đã đến: phòng đã check in (gồm đã trả, đã chuyển), bật ngày thì đến trong khoảng tìm.
                'checkin' => $all
                    ? $q->whereIn($status, [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT, BookingRoom::STATUS_MOVED])
                    : $q->whereIn($status, [BookingRoom::STATUS_CHECKED_IN, BookingRoom::STATUS_CHECKED_OUT])->whereDate($arrival, '>=', $from)->whereDate($arrival, '<=', $to),
                // Đã đi: phòng đã check out, bật ngày thì ngày đi trong khoảng tìm.
                'checkout' => $all
                    ? $q->whereIn($status, [BookingRoom::STATUS_CHECKED_OUT, BookingRoom::STATUS_MOVED])
                    : $q->where($status, BookingRoom::STATUS_CHECKED_OUT)->whereDate($departure, '>=', $from)->whereDate($departure, '<=', $to),
                'inhouse' => $this->applyInHouse($q, $all, $from, $to, $sysDate, $status, $arrival, $departure),
                // Đã hủy: bật ngày thì ngày hủy phòng trong khoảng tìm (SP8052.CancelDate).
                'cancel' => $all
                    ? $q->where($status, BookingRoom::STATUS_CANCELLED)
                    : $q->where($status, BookingRoom::STATUS_CANCELLED)->whereHas('cancelLogs', fn (Builder $log) => $log
                        ->where('cancel_type', 'room')->whereDate('cancelled_at', '>=', $from)->whereDate('cancelled_at', '<=', $to)),
            };
        });
    }

    /**
     * Đang ở: không bật ngày là phòng đang ở. Bật ngày: tìm đúng ngày hệ thống thì phòng
     * đang ở; ngoài ra là phòng có đêm lưu trú trong khoảng tìm (legacy dựa trên đêm RM
     * của func_054: phòng đang ở, hoặc đêm trước ngày hệ thống của phòng đã trả).
     */
    private function applyInHouse(Builder $q, bool $all, ?string $from, ?string $to, string $sysDate, string $status, string $arrival, string $departure): void
    {
        if ($all) {
            $q->where($status, BookingRoom::STATUS_CHECKED_IN);
            return;
        }

        $q->where(function (Builder $inHouse) use ($from, $to, $sysDate, $status, $arrival, $departure) {
            if ($from === $to && $to === $sysDate) {
                $inHouse->orWhere($status, BookingRoom::STATUS_CHECKED_IN);
            }
            // Có ít nhất một đêm d trong [from, to] với arrival <= d < departure.
            $inHouse->orWhere(fn (Builder $stay) => $stay->where($status, BookingRoom::STATUS_CHECKED_IN)
                ->whereDate($arrival, '<=', $to)->whereDate($departure, '>', $from));
            // Phòng đã trả: chỉ tính các đêm trước ngày hệ thống.
            if ($from < $sysDate) {
                $inHouse->orWhere(fn (Builder $stay) => $stay->where($status, BookingRoom::STATUS_CHECKED_OUT)
                    ->whereDate($arrival, '<=', $to)->whereDate($arrival, '<', $sysDate)->whereDate($departure, '>', $from));
            }
        });
    }

    public function systemDate(): string
    {
        if ($this->systemDate === null) {
            $value = DB::table('system_date_rolls')->orderByDesc('id')->value('system_date');
            $this->systemDate = $value ? Carbon::parse($value)->toDateString() : now('Asia/Ho_Chi_Minh')->toDateString();
        }

        return $this->systemDate;
    }
}
