<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomService;
use App\Models\HotelSetting;
use App\Models\ServiceBill;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingPrintController extends Controller
{
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

        // 2. Danh sách ServiceBills do Lễ tân / Outlets post phát sinh cho booking hoặc các phòng
        $allBookingRoomIds = $booking->bookingRooms->pluck('id')->toArray();
        $targetRoomIds = $bookingRooms->pluck('id')->toArray();

        $foBills = ServiceBill::query()
            ->where(function ($q) use ($booking, $allBookingRoomIds) {
                $q->where('RegisterId1', $booking->id)
                  ->orWhere('RegisterID2', $booking->id)
                  ->orWhereIn('RentalRoomId1', $allBookingRoomIds)
                  ->orWhereIn('RentalRoomId2', $allBookingRoomIds);
            })
            ->where('Status', '!=', 3) // Loại trừ bill đã hủy
            ->orderBy('Date')
            ->orderBy('OpenTime')
            ->get();

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

            // Dịch vụ của phòng này (từ booking_room_services)
            $roomServicesList = [];
            $roomRateSum = 0;

            foreach ($br->services as $svc) {
                $svcDate = $svc->service_date ? Carbon::parse($svc->service_date)->format('d/m/Y') : $ciStr;
                $isRoom = (int)$svc->is_room === 1;
                $qty = (float)($svc->quantity ?: 1);
                $rate = (float)($svc->rate ?: 0);
                $amt = (float)($svc->total_amount ?: ($qty * $rate));

                if ($isRoom) {
                    $totalRoomAmount += $amt;
                    $roomRateSum += $amt;
                } else {
                    $totalSaleServicesAmount += $amt;
                }

                $svcItem = [
                    'id'            => 'sale_' . $svc->id,
                    'source'        => 'sale',
                    'type'          => $isRoom ? 'room' : 'service',
                    'is_room'       => $isRoom,
                    'room_id'       => $br->id,
                    'room_number'   => $roomNum,
                    'code'          => $svc->service_code ?: ($isRoom ? 'ROOM' : 'EXTRA'),
                    'name'          => $svc->service_name ?: ($isRoom ? 'Tiền phòng ' . $roomClassName : 'Dịch vụ bổ sung'),
                    'date'          => $svcDate,
                    'time'          => '',
                    'quantity'      => $qty,
                    'unit_price'    => $rate,
                    'amount'        => $amt,
                    'department'    => $svc->department ?: ($isRoom ? 'FO' : 'OTHER'),
                    'note'          => $svc->note ?: '',
                ];

                $roomServicesList[] = $svcItem;
                $allServices[] = $svcItem;
            }

            // Đơn giá trung bình / ngày
            $avgRate = $nights > 0 ? round($roomRateSum / $nights) : $roomRateSum;

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

        // 4. Thêm các dịch vụ Lễ tân post (ServiceBills) vào danh sách allServices
        $totalFoServicesAmount = 0;
        foreach ($foBills as $bill) {
            $billDate = $bill->Date ? Carbon::parse($bill->Date)->format('d/m/Y') : '';
            $billAmt = (float)$bill->Amount;
            $totalFoServicesAmount += $billAmt;

            // Tìm số phòng tương ứng nếu có
            $matchedRoom = $bookingRooms->firstWhere('id', $bill->RentalRoomId1) 
                        ?: $bookingRooms->firstWhere('id', $bill->RentalRoomId2);
            $billRoomNum = $matchedRoom ? ($matchedRoom->room?->room_number ?: $matchedRoom->room_number) : ($bill->Guest ?: '---');

            $allServices[] = [
                'id'            => 'fo_' . $bill->Ma,
                'source'        => 'fo',
                'type'          => 'service',
                'is_room'       => false,
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
}
