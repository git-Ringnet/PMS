<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelSetting;
use App\Models\ServiceBill;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BookingPrintController extends Controller
{
    /** Mã dịch vụ được coi là tiền phòng (giống màn hình đăng ký). */
    private const ROOM_CHARGE_CODES = ['RM', 'ROOM_CHARGE', 'ER'];

    /**
     * Lấy dữ liệu tổng hợp đầy đủ cho các tác vụ in ấn Booking:
     * 1. Xác nhận đặt phòng (Booking Confirmation VN/ENG)
     * 2. Phiếu đăng ký khách (Guest Registration Card)
     * 3. Hóa đơn tạm tính (Pro-forma Invoice)
     */
    public function getPrintData(Request $request, string|int $id): JsonResponse
    {
        $bookingId = is_numeric($id) ? (int)$id : (int)preg_replace('/\D/', '', (string)$id);

        $booking = Booking::query()
            ->with([
                'bookingRooms.room',
                'bookingRooms.roomClass',
                'bookingRooms.guests',
                'bookingRooms.services',
                'company',
                'booker',
                'paymentMethod',
                'payments.paymentMethod',
            ])
            ->where('id', $bookingId)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin đặt phòng.',
            ], 404);
        }

        $hotel = HotelSetting::first();
        $currentUser = Auth::user();

        // 1. Lọc phòng theo query nếu người dùng chỉ chọn một số phòng
        $selectedRoomIds = $request->input('room_ids');
        if (is_string($selectedRoomIds)) {
            $selectedRoomIds = array_filter(array_map('trim', explode(',', $selectedRoomIds)));
        }

        $bookingRooms = $booking->bookingRooms;
        if (!empty($selectedRoomIds) && is_array($selectedRoomIds)) {
            $filteredRooms = $bookingRooms->filter(function ($r) use ($selectedRoomIds) {
                return in_array($r->id, $selectedRoomIds) || in_array((string)$r->id, $selectedRoomIds);
            });
            if ($filteredRooms->isNotEmpty()) {
                $bookingRooms = $filteredRooms;
            }
        }

        // 2. Danh sách ServiceBills còn hiệu lực của booking / các phòng đang in
        // (bill đã hủy hoặc bill điều chỉnh Edit = 1 không in).
        $targetRoomIds = $bookingRooms->pluck('id')->map(fn ($id) => (string) $id)->all();
        $allBookingRoomIds = $booking->bookingRooms->pluck('id')->toArray();

        $foBills = ServiceBill::query()
            ->where(function ($q) use ($booking, $allBookingRoomIds) {
                $q->where('RegisterId1', $booking->id)
                  ->orWhere('RegisterID2', $booking->id)
                  ->orWhereIn('RentalRoomId1', $allBookingRoomIds)
                  ->orWhereIn('RentalRoomId2', $allBookingRoomIds);
            })
            ->whereNotIn('Status', [3, 4])
            ->where(fn ($q) => $q->whereNull('Edit')->orWhere('Edit', '!=', 1))
            ->orderBy('Date')
            ->orderBy('OpenTime')
            ->get()
            // Bill của phòng không được chọn thì không in; bill chung của booking (không gắn phòng) vẫn in.
            ->filter(function (ServiceBill $bill) use ($targetRoomIds) {
                $billRoomIds = array_filter([(string) $bill->RentalRoomId1, (string) $bill->RentalRoomId2]);
                return empty($billRoomIds) || array_intersect($billRoomIds, $targetRoomIds);
            })
            ->values();

        $systemDate = app(RoomAvailabilityService::class)->getSystemDate()->toDateString();
        $handledBillIds = [];

        // 3. Chuẩn hóa dữ liệu từng phòng
        $formattedRooms = [];
        $roomDetailLines = [];
        $allServices = [];

        $totalRoomAmount = 0;
        $totalSaleServicesAmount = 0;

        foreach ($bookingRooms as $br) {
            $roomNum = $br->room?->room_number ?: ($br->room_number ?: '---');
            $roomClassName = $br->roomClass?->name ?: 'Tiêu chuẩn';
            $roomClassCode = $br->roomClass?->code ?: '';

            $ci = $br->check_in_date ?: $br->arrival_date ?: $booking->arrival_date;
            $co = $br->check_out_date ?: $br->departure_date ?: $booking->departure_date;
            $ciStr = $ci ? Carbon::parse($ci)->format('d/m/Y') : '';
            $coStr = $co ? Carbon::parse($co)->format('d/m/Y') : '';

            $nights = $br->nights ?: ($ci && $co ? max(1, Carbon::parse($ci)->diffInDays(Carbon::parse($co))) : 1);
            $adults = (int)($br->adults ?: 1);
            $children = (int)($br->children_qty ?: 0);

            // Tìm khách chính đại diện phòng
            $primaryGuest = $br->guests->first();
            $guestName = $br->customer_name ?: ($primaryGuest?->name ?: $booking->booking_name);
            $guestPhone = $primaryGuest?->phone ?: $booking->booker?->phone;
            $guestEmail = $primaryGuest?->email ?: $booking->booker?->email;
            $guestIdPassport = $primaryGuest?->id_passport ?: '';
            $guestNationality = $primaryGuest?->nationality ?: 'Việt Nam';

            $roomServicesList = [];
            $roomRateSum = 0;

            // 3a. Tiền phòng theo từng đêm (dòng 348, 349) - giống chi tiết giá ở màn hình đăng ký:
            //  - Đêm đã post bill tiền phòng: lấy theo bill.
            //  - Đêm chưa post: lấy dòng RM đã lưu của đêm đó, không có thì lấy giá phòng (booking_rooms.rate).
            //  - Đêm quá khứ chưa có bill: không tính.
            $roomChargeLines = $this->roomChargeLines($br, $foBills, $systemDate, $roomNum, $roomClassName, $handledBillIds);
            foreach ($roomChargeLines as $line) {
                $totalRoomAmount += $line['amount'];
                $roomRateSum += $line['amount'];
                $roomServicesList[] = $line;
                $allServices[] = $line;
            }

            // 3b. Dịch vụ bổ sung đã setup trong booking, chưa post bill.
            // Dòng đã post được in theo bill ở bước 4 để không bị cộng hai lần.
            foreach ($br->services as $svc) {
                $code = strtoupper((string) $svc->service_code);
                if (in_array($code, self::ROOM_CHARGE_CODES, true)) {
                    continue;
                }
                if ((int) $svc->is_posted === 1 || $svc->service_bill_id) {
                    continue;
                }
                $svcDateIso = $svc->service_date ? Carbon::parse($svc->service_date)->toDateString() : null;
                if ($svcDateIso && $svcDateIso < $systemDate) {
                    continue;
                }
                // Giá sửa sau khi đã post (dòng 351) chỉ áp dụng khi bill cũ bị xóa.
                if ($svcDateIso && $this->hasActivePostedService($br, $svc, $svcDateIso, $foBills)) {
                    continue;
                }

                $qty = (float)($svc->quantity ?: 1);
                $rate = (float)($svc->rate ?: 0);
                $amt = (float)($svc->total_amount ?: ($qty * $rate));
                $totalSaleServicesAmount += $amt;

                $svcItem = [
                    'id'            => 'sale_' . $svc->id,
                    'source'        => 'sale',
                    'type'          => 'service',
                    'is_room'       => false,
                    'room_id'       => $br->id,
                    'room_number'   => $roomNum,
                    'code'          => $svc->service_code ?: 'EXTRA',
                    'name'          => $svc->service_name ?: 'Dịch vụ bổ sung',
                    'date'          => $svcDateIso ? Carbon::parse($svcDateIso)->format('d/m/Y') : $ciStr,
                    'time'          => '',
                    'quantity'      => $qty,
                    'unit_price'    => $rate,
                    'amount'        => $amt,
                    'department'    => $svc->department ?: 'OTHER',
                    'note'          => $svc->note ?: '',
                ];

                $roomServicesList[] = $svcItem;
                $allServices[] = $svcItem;
            }

            // Giá phòng/đêm in trên phiếu đăng ký: trung bình tiền phòng các đêm
            $avgRate = count($roomChargeLines) > 0
                ? round($roomRateSum / count($roomChargeLines))
                : (float) ($br->rate ?? 0);

            $formattedRooms[] = [
                'id'                => $br->id,
                'room_number'       => $roomNum,
                'room_class_id'     => $br->room_class_id,
                'room_class_name'   => $roomClassName,
                'room_class_code'   => $roomClassCode,
                'arrival_date'      => $ciStr,
                'departure_date'    => $coStr,
                'nights'            => $nights,
                'adults'            => $adults,
                'children'          => $children,
                'customer_name'     => $guestName,
                'phone'             => $guestPhone,
                'email'             => $guestEmail,
                'id_passport'       => $guestIdPassport,
                'nationality'       => $guestNationality,
                'rate'              => $avgRate,
                'total_amount'      => $roomRateSum,
                'services'          => $roomServicesList,
            ];

            // Dòng hiển thị tổng hợp cho Booking Confirmation
            $roomDetailLines[] = [
                'checkin'     => $ciStr,
                'checkout'    => $coStr,
                'service'     => $roomClassName,
                'room_number' => $roomNum,
                'quantity'    => 1,
                'nights'      => $nights,
                'guests'      => $adults + $children,
                'unit_price'  => $avgRate,
                'amount'      => $roomRateSum,
            ];
        }

        // 4. Các bill Lễ tân / Outlets post (ngoài tiền phòng đã lấy ở bước 3a)
        $totalFoServicesAmount = 0;
        foreach ($foBills as $bill) {
            if (isset($handledBillIds[(string) $bill->Ma])) {
                continue;
            }
            $billDate = $bill->Date ? Carbon::parse($bill->Date)->format('d/m/Y') : '';
            $billAmt = (float)$bill->Amount;
            $isRoomBill = $this->isRoomChargeBill($bill);

            // Tìm số phòng tương ứng nếu có
            $matchedRoom = $bookingRooms->firstWhere('id', $bill->RentalRoomId1)
                        ?: $bookingRooms->firstWhere('id', $bill->RentalRoomId2);
            $billRoomNum = $matchedRoom ? ($matchedRoom->room?->room_number ?: $matchedRoom->room_number) : ($bill->Guest ?: '---');

            if ($isRoomBill) {
                $totalRoomAmount += $billAmt;
            } else {
                $totalFoServicesAmount += $billAmt;
            }

            $allServices[] = [
                'id'            => 'fo_' . $bill->Ma,
                'source'        => 'fo',
                'type'          => $isRoomBill ? 'room' : 'service',
                'is_room'       => $isRoomBill,
                'room_id'       => $bill->RentalRoomId1 ?: $bill->RentalRoomId2,
                'room_number'   => $billRoomNum,
                'code'          => $bill->ServiceId ?: 'SRV',
                'name'          => $bill->DescriptionServive ?: ($bill->ServiceId ?: 'Dịch vụ lễ tân'),
                'date'          => $billDate,
                'time'          => $bill->OpenTime ?: '',
                'quantity'      => (float)($bill->Quantity ?: 1),
                'unit_price'    => (float)($bill->Amount / max(1, (float)($bill->Quantity ?: 1))),
                'amount'        => $billAmt,
                'department'    => $bill->DepartmentId ?: 'FO',
                'note'          => $bill->Pack1 ?: '',
            ];
        }

        // 5. Tiền đặt cọc & thanh toán
        $totalDeposit = 0;
        $paymentsList = [];
        foreach ($booking->payments as $p) {
            $pAmt = (float)$p->amount;
            $totalDeposit += $pAmt;
            $paymentsList[] = [
                'id'          => $p->id,
                'date'        => $p->payment_date ? Carbon::parse($p->payment_date)->format('d/m/Y') : '',
                'amount'      => $pAmt,
                'method_name' => $p->paymentMethod?->name ?: 'Chuyển khoản',
                'description' => $p->description ?: '',
                'is_deposit'  => $p->pack2 === 'DPR',
            ];
        }

        // Tính toán công nợ
        $totalBooking = $totalRoomAmount + $totalSaleServicesAmount;
        $totalAll = $totalBooking + $totalFoServicesAmount;
        $balance = max(0, $totalAll - $totalDeposit);

        // Đọc số tiền bằng chữ
        $amountInWords = SalesInvoiceController::numberToVietnameseWords($totalAll);
        $balanceInWords = SalesInvoiceController::numberToVietnameseWords($balance);

        $confirmDateFormatted = $booking->confirm_date ? Carbon::parse($booking->confirm_date)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
        $arrivalFormatted = $booking->arrival_date ? Carbon::parse($booking->arrival_date)->format('d/m/Y') : '';
        $departureFormatted = $booking->departure_date ? Carbon::parse($booking->departure_date)->format('d/m/Y') : '';

        return response()->json([
            'success' => true,
            'data'    => [
                'hotel' => [
                    'name'                => $hotel?->hotel_name ?: 'NAVY HOTEL',
                    'name_en'             => $hotel?->hotel_name1 ?: ($hotel?->hotel_name ?: 'NAVY HOTEL'),
                    'address'             => $hotel?->address ?: '',
                    'address_en'          => $hotel?->address1 ?: ($hotel?->address ?: ''),
                    'phone'               => $hotel?->phone ?: '',
                    'email'               => $hotel?->email ?: '',
                    'website'             => $hotel?->website ?: '',
                    'tax_code'            => $hotel?->tax_code ?: '',
                    'logo_url'            => $hotel?->logo_url ?: '',
                    'bank_account_name'   => $hotel?->account_name ?: ($hotel?->hotel_name ?: ''),
                    'bank_account_number' => $hotel?->account ?: '',
                    'bank_name'           => $hotel?->bank ?: '',
                ],
                'booking' => [
                    'id'               => $booking->id,
                    'booking_code'     => $booking->booking_code ?: ('BK' . $booking->id),
                    'booking_name'     => $booking->booking_name ?: '',
                    'arrival_date'     => $arrivalFormatted,
                    'departure_date'   => $departureFormatted,
                    'nights'           => max(1, (int)$booking->nights),
                    'confirm_date'     => $confirmDateFormatted,
                    'company_name'     => $booking->company?->name ?: '',
                    'company_code'     => $booking->company?->code ?: '',
                    'company_address'  => $booking->company?->address ?: '',
                    'company_tax_code' => $booking->company?->tax_code ?: '',
                    'company_phone'    => $booking->company?->phone ?: '',
                    'company_email'    => $booking->company?->email ?: '',
                    'booker_name'      => $booking->booker?->name ?: ($booking->booking_name ?: ''),
                    'booker_phone'     => $booking->booker?->phone ?: '',
                    'booker_email'     => $booking->booker?->email ?: '',
                    'confirmed_by'     => $currentUser?->name ?: ($currentUser?->username ?: 'Lễ tân'),
                    'special_requests' => $booking->special_requests ?: '',
                    'notes'            => $booking->notes ?: '',
                    'payment_method'   => $booking->paymentMethod?->name ?: 'Bank transfer / Chuyển khoản',
                    'tour_code'        => $booking->tour_code ?: '',
                ],
                'rooms'            => $formattedRooms,
                'room_lines'       => $roomDetailLines,
                'services'         => $allServices,
                'payments'         => $paymentsList,
                'summary'          => [
                    'total_room'          => $totalRoomAmount,
                    'total_sale_service'  => $totalSaleServicesAmount,
                    'total_booking'       => $totalBooking,
                    'total_fo_service'    => $totalFoServicesAmount,
                    'total_all'           => $totalAll,
                    'deposit'             => $totalDeposit,
                    'balance'             => $balance,
                    'amount_in_words'     => $amountInWords,
                    'balance_in_words'    => $balanceInWords,
                ],
            ],
        ]);
    }

    /**
     * Tiền phòng từng đêm của một phòng, khớp chi tiết giá ở màn hình đăng ký.
     * Bill tiền phòng đã dùng được đánh dấu vào $handledBillIds để không in lại ở mục dịch vụ.
     *
     * @param array<string, bool> $handledBillIds
     * @return list<array<string, mixed>>
     */
    private function roomChargeLines(
        BookingRoom $br,
        Collection $foBills,
        string $systemDate,
        string $roomNum,
        string $roomClassName,
        array &$handledBillIds
    ): array {
        $roomBills = $foBills->filter(fn (ServiceBill $bill) => $this->isRoomChargeBill($bill)
            && in_array((string) $br->id, [(string) $bill->RentalRoomId1, (string) $bill->RentalRoomId2], true));

        $lines = [];
        $billLine = function (ServiceBill $bill) use ($br, $roomNum, &$handledBillIds): array {
            $handledBillIds[(string) $bill->Ma] = true;
            $qty = (float) ($bill->Quantity ?: 1);
            $amount = (float) $bill->Amount;

            return $this->roomLine('fo_' . $bill->Ma, 'fo', $br, $roomNum, $bill->ServiceId ?: BookingRoomService::CODE_ROOM,
                $bill->DescriptionServive ?: 'Tiền phòng', Carbon::parse($bill->Date)->toDateString(), $qty,
                $qty > 0 ? $amount / $qty : $amount, $amount);
        };

        $arrival = $br->arrival_date ? Carbon::parse($br->arrival_date)->startOfDay() : null;
        $departure = $br->departure_date ? Carbon::parse($br->departure_date)->startOfDay() : null;
        if ($arrival && $departure && $br->is_day_use && $departure->equalTo($arrival)) {
            $departure = $arrival->copy()->addDay();
        }

        if ($arrival && $departure) {
            for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
                $dateStr = $date->toDateString();
                $nightBills = $roomBills->filter(fn (ServiceBill $bill) => $bill->Date
                    && Carbon::parse($bill->Date)->toDateString() === $dateStr
                    && !isset($handledBillIds[(string) $bill->Ma]));

                if ($nightBills->isNotEmpty()) {
                    foreach ($nightBills as $bill) {
                        $lines[] = $billLine($bill);
                    }
                    continue;
                }
                if ($dateStr < $systemDate) {
                    continue;
                }

                $plan = $br->services->first(fn (BookingRoomService $svc) => in_array(strtoupper((string) $svc->service_code), self::ROOM_CHARGE_CODES, true)
                    && $svc->service_date && Carbon::parse($svc->service_date)->toDateString() === $dateStr
                    && (int) $svc->is_posted !== 1 && !$svc->service_bill_id);
                $rate = $plan ? (float) $plan->rate : (float) ($br->rate ?? 0);
                $qty = $plan ? (float) ($plan->quantity ?: 1) : 1.0;

                $lines[] = $this->roomLine($plan ? 'sale_' . $plan->id : 'room_' . $br->id . '_' . $dateStr, 'sale', $br, $roomNum,
                    BookingRoomService::CODE_ROOM, $plan?->service_name ?: 'Tiền phòng ' . $roomClassName, $dateStr, $qty, $rate, $qty * $rate);
            }
        }

        // Bill tiền phòng nằm ngoài giai đoạn ở (đổi ngày sau khi đã post) vẫn là tiền phòng đã chạy.
        foreach ($roomBills as $bill) {
            if (!isset($handledBillIds[(string) $bill->Ma])) {
                $lines[] = $billLine($bill);
            }
        }

        return $lines;
    }

    private function roomLine(string $id, string $source, BookingRoom $br, string $roomNum, string $code, string $name,
        string $dateStr, float $qty, float $unitPrice, float $amount): array
    {
        return [
            'id'          => $id,
            'source'      => $source,
            'type'        => 'room',
            'is_room'     => true,
            'room_id'     => $br->id,
            'room_number' => $roomNum,
            'code'        => $code,
            'name'        => $name,
            'date'        => Carbon::parse($dateStr)->format('d/m/Y'),
            'time'        => '',
            'quantity'    => $qty,
            'unit_price'  => $unitPrice,
            'amount'      => $amount,
            'department'  => 'FO',
            'note'        => '',
        ];
    }

    private function isRoomChargeBill(ServiceBill $bill): bool
    {
        return in_array(strtoupper((string) $bill->ServiceId), self::ROOM_CHARGE_CODES, true);
    }

    /**
     * Dịch vụ cùng mã/ngày đã có bill còn hiệu lực thì dòng kế hoạch chưa post
     * (giá sửa sau khi post) không được in thêm.
     */
    private function hasActivePostedService(BookingRoom $br, BookingRoomService $svc, string $dateStr, Collection $foBills): bool
    {
        $activeBillIds = $foBills->pluck('Ma')->map(fn ($id) => (string) $id)->all();

        return $br->services->contains(fn (BookingRoomService $other) => $other->id !== $svc->id
            && $other->service_code === $svc->service_code
            && $other->service_bill_id
            && in_array((string) $other->service_bill_id, $activeBillIds, true)
            && $other->service_date && Carbon::parse($other->service_date)->toDateString() === $dateStr);
    }
}
