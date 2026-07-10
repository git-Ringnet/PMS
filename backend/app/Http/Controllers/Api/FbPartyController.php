<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbParty;
use App\Models\FbSubParty;
use App\Models\FbPartyItem;
use App\Models\FbPartyPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FbPartyController extends Controller
{
    public function index(Request $request)
    {
        $query = FbParty::with([
            'subParties.items',
            'payments',
            'subParties.outletModel'
        ]);

        // Lọc theo trạng thái
        if ($request->filled('status')) {
            if ($request->status !== 'ALL') {
                $query->where('status', $request->status);
            } else {
                // Nếu là ALL thì không hiện các tiệc bị hủy
                $query->where('status', '!=', 'cancelled');
            }
        }

        // Lọc theo khoảng ngày (arrival_date)
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('arrival_date', [$request->start_date, $request->end_date]);
        }

        // Lọc theo từ khóa tìm kiếm
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('party_name', 'like', "%{$search}%")
                  ->orWhere('party_code', 'like', "%{$search}%")
                  ->orWhere('customer', 'like', "%{$search}%");
            });
        }

        // Lọc theo outlet
        if ($request->filled('outlet_code')) {
            $outlet = \App\Models\Outlet::where('code', $request->outlet_code)->first();
            $query->whereHas('subParties', function($q) use ($request, $outlet) {
                $q->where(function($q3) use ($request, $outlet) {
                    $q3->whereHas('outletModel', function($q2) use ($request) {
                        $q2->where('code', $request->outlet_code);
                    });
                    if ($outlet) {
                        $q3->orWhere('outlet', $outlet->name);
                        $q3->orWhere('outlet', $outlet->id);
                    }
                });
            });
        }

        // Lọc theo khu vực (location)
        if ($request->filled('fb_location_id')) {
            $query->whereHas('subParties', function($q) use ($request) {
                $q->where('location', $request->fb_location_id);
            });
        }

        $parties = $query->orderBy('created_at', 'desc')->get();

        // Định dạng dữ liệu trả về giống cấu trúc frontend mong đợi
        $formatted = $parties->map(function ($party) {
            $totalAmount = 0;
            $tablesCount = 0;
            $guestsCount = 0;
            $detailsCount = 0;

            $earliestDatetime = null;
            $latestDatetime = null;

            foreach ($party->subParties as $sub) {
                $tablesCount += $sub->tables;
                $guestsCount += ($sub->adults + $sub->children);
                $detailsCount += $sub->items->count();
                
                if ($sub->arrival_date) {
                    $arrDateStr = is_string($sub->arrival_date) ? substr($sub->arrival_date, 0, 10) : $sub->arrival_date->format('Y-m-d');
                    
                    if ($sub->arrival_time) {
                        $startDt = \Carbon\Carbon::parse($arrDateStr . ' ' . $sub->arrival_time);
                        if (!$earliestDatetime || $startDt->lt($earliestDatetime)) {
                            $earliestDatetime = $startDt;
                        }
                    }

                    if ($sub->departure_time) {
                        $endDt = \Carbon\Carbon::parse($arrDateStr . ' ' . $sub->departure_time);
                        if (!$latestDatetime || $endDt->gt($latestDatetime)) {
                            $latestDatetime = $endDt;
                        }
                    }
                }

                foreach ($sub->items as $item) {
                    $totalAmount += ($item->price * $item->quantity);
                }
            }

            $depositAmount = $party->payments->where('status', '!=', 'cancelled')->sum('amount');

            $timeStr = '—';
            if ($earliestDatetime && $latestDatetime) {
                if ($earliestDatetime->isSameDay($latestDatetime)) {
                    $timeStr = $earliestDatetime->format('H:i') . ' - ' . $latestDatetime->format('H:i');
                } else {
                    $timeStr = $earliestDatetime->format('H:i d/m') . ' - ' . $latestDatetime->format('H:i d/m');
                }
            } elseif ($earliestDatetime) {
                $timeStr = $earliestDatetime->format('H:i') . ' - ?';
            }

            // Tự động cập nhật trạng thái dựa vào thời gian thực tế
            $status = $party->status;
            if (in_array($status, ['confirmed', 'serving']) && $earliestDatetime && $latestDatetime) {
                $now = \Carbon\Carbon::now();
                
                if ($now->gt($latestDatetime)) {
                    $status = 'completed';
                    $party->update(['status' => 'completed']);
                } elseif ($now->between($earliestDatetime, $latestDatetime) && $status === 'confirmed') {
                    $status = 'serving';
                    $party->update(['status' => 'serving']);
                }
            } elseif (in_array($status, ['confirmed', 'serving']) && !$earliestDatetime) {
                // Fallback nếu không có giờ
                $now = \Carbon\Carbon::now();
                $arrivalDate = \Carbon\Carbon::parse($party->arrival_date->format('Y-m-d'));
                if ($arrivalDate->isPast() && !$arrivalDate->isToday()) {
                    $status = 'completed';
                    $party->update(['status' => 'completed']);
                }
            }

            $outletsArray = $party->subParties->map(function ($sub) {
                return $sub->outletModel?->name ?? $sub->outlet;
            })->filter(fn($val) => !empty($val) && $val !== '—')->unique()->values()->all();

            $areasArray = $party->subParties->map(function ($sub) {
                return $sub->location;
            })->filter(fn($val) => !empty($val) && $val !== '—')->unique()->values()->all();

            return [
                'id' => $party->id,
                'code' => $party->party_code,
                'name' => $party->party_name,
                'arrivalDate' => $party->arrival_date->format('d/m/Y'),
                'time' => $timeStr,
                'customer' => $party->customer,
                'company' => $party->company,
                'outlets' => empty($outletsArray) ? ['—'] : $outletsArray,
                'areas' => empty($areasArray) ? ['—'] : $areasArray,
                'outlet' => empty($outletsArray) ? '—' : implode(', ', $outletsArray),
                'area' => empty($areasArray) ? '—' : implode(', ', $areasArray),
                'tablesCount' => $tablesCount,
                'guestsCount' => $guestsCount,
                'additionalCost' => $party->subParties->sum('extra'),
                'detailsCount' => $detailsCount,
                'totalAmount' => $totalAmount,
                'depositAmount' => $depositAmount,
                'status' => $status,
                'email' => $party->email,
                'note' => $party->note,
                'vatNote' => $party->vat_note,
                'subParties' => $party->subParties->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'bookingCode' => $sub->booking_code,
                        'arrivalDate' => $sub->arrival_date->format('d/m/Y'),
                        'arrivalTime' => $sub->arrival_time ? substr($sub->arrival_time, 0, 5) : '',
                        'departureTime' => $sub->departure_time ? substr($sub->departure_time, 0, 5) : '',
                        'adults' => $sub->adults,
                        'children' => $sub->children,
                        'tables' => $sub->tables,
                        'extra' => $sub->extra,
                        'outlet_id' => $sub->outlet,
                        'outlet' => $sub->outletModel ? $sub->outletModel->name : $sub->outlet,
                        'location' => $sub->location,
                        'partyType' => $sub->party_type,
                        'groupCode' => $sub->group_code,
                        'note' => $sub->note,
                        'menuItems' => $sub->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'name' => $item->name,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'price' => $item->price,
                                'discount' => $item->discount,
                                'note' => $item->note,
                            ];
                        })
                    ];
                })
            ];
        });

        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'partyName' => 'required|string|max:255',
            'arrivalDate' => 'required|string',
            'confirmationType' => 'nullable|string',
            'confirmationDate' => 'nullable|string',
            'saleStaff' => 'nullable|string',
            'company' => 'nullable|string',
            'customer' => 'nullable|string',
            'email' => 'nullable|string|email',
            'note' => 'nullable|string',
            'vatNote' => 'nullable|string',
            'subParties' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Sinh mã tiệc tự động: PT-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $randomSuffix = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $partyCode = "PT-{$datePrefix}-{$randomSuffix}";

            // Định dạng ngày
            $arrivalDate = $this->parseDate($request->arrivalDate);
            $confirmationDate = $request->confirmationDate ? $this->parseDate($request->confirmationDate) : null;

            // Tạo tiệc chính
            $party = FbParty::create([
                'party_code' => $partyCode,
                'party_name' => $request->partyName,
                'arrival_date' => $arrivalDate,
                'confirmation_type' => $request->confirmationType ?? 'byDate',
                'confirmation_date' => $confirmationDate,
                'sale_staff' => $request->saleStaff,
                'company' => $request->company ?? 'KHÁCH LẺ',
                'customer' => $request->customer,
                'email' => $request->email,
                'note' => $request->note,
                'vat_note' => $request->vatNote,
                'status' => 'confirmed'
            ]);

            // Xử lý tiệc con
            if (!empty($request->subParties) && is_array($request->subParties)) {
                foreach ($request->subParties as $subData) {
                    $subArrivalDate = isset($subData['arrivalDate']) ? $this->parseDate($subData['arrivalDate']) : $arrivalDate;
                    
                    // Sinh mã booking cho tiệc con tự động
                    $subRandom = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
                    $bookingCode = "S{$subRandom}";

                    $subParty = FbSubParty::create([
                        'party_id' => $party->id,
                        'booking_code' => $bookingCode,
                        'arrival_date' => $subArrivalDate,
                        'arrival_time' => $subData['arrivalTime'] ?? null,
                        'departure_time' => $subData['departureTime'] ?? null,
                        'adults' => $subData['adults'] ?? 1,
                        'children' => $subData['children'] ?? 0,
                        'tables' => $subData['tables'] ?? 1,
                        'extra' => $subData['extra'] ?? 0,
                        'outlet' => $subData['outlet'] ?? null,
                        'location' => $subData['location'] ?? null,
                        'party_type' => $subData['partyType'] ?? null,
                        'group_code' => $subData['groupCode'] ?? null,
                        'note' => $subData['note'] ?? null,
                    ]);

                    // Xử lý món ăn tiệc con
                    if (!empty($subData['menuItems']) && is_array($subData['menuItems'])) {
                        foreach ($subData['menuItems'] as $item) {
                            FbPartyItem::create([
                                'sub_party_id' => $subParty->id,
                                'product_id' => $item['product_id'] ?? null,
                                'name' => $item['name'],
                                'quantity' => $item['quantity'] ?? 1,
                                'unit' => $item['unit'] ?? 'Phần',
                                'price' => $item['price'] ?? 0,
                                'discount' => $item['discount'] ?? 0,
                                'note' => $item['note'] ?? null,
                            ]);
                        }
                    }

                    // Xử lý đặt cọc
                    if (!empty($subData['deposits']) && is_array($subData['deposits'])) {
                        foreach ($subData['deposits'] as $dep) {
                            $depDate = isset($dep['date']) ? $this->parseDate($dep['date']) : Carbon::now()->format('Y-m-d');
                            FbPartyPayment::create([
                                'party_id' => $party->id,
                                'sub_party_id' => $subParty->id,
                                'payment_date' => $depDate,
                                'payment_method' => $dep['method'],
                                'amount' => $dep['amount'] ?? 0,
                                'note' => $dep['note'] ?? null,
                                'status' => $dep['status'] ?? 'active',
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo đặt tiệc thành công.',
                'id' => $party->id,
                'party_code' => $partyCode
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi tạo tiệc: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $party = FbParty::with(['subParties.items', 'payments', 'subParties.outletModel'])->find($id);
        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đặt tiệc.'
            ], 404);
        }

        $tablesCount = 0;
        $guestsCount = 0;
        $detailsCount = 0;
        $totalAmount = 0;

        foreach ($party->subParties as $sub) {
            $tablesCount += $sub->tables;
            $guestsCount += ($sub->adults + $sub->children);
            $detailsCount += $sub->items->count();
            
            foreach ($sub->items as $item) {
                $totalAmount += ($item->price * $item->quantity);
            }
        }

        $depositAmount = $party->payments->where('status', '!=', 'cancelled')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $party->id,
                'partyCode' => $party->party_code,
                'partyName' => $party->party_name,
                'arrivalDate' => $party->arrival_date->format('Y-m-d'),
                'confirmationType' => $party->confirmation_type,
                'confirmationDate' => $party->confirmation_date ? $party->confirmation_date->format('Y-m-d') : null,
                'saleStaff' => $party->sale_staff,
                'company' => $party->company,
                'customer' => $party->customer,
                'email' => $party->email,
                'note' => $party->note,
                'vatNote' => $party->vat_note,
                'status' => $party->status,
                'totalAmount' => $totalAmount,
                'depositAmount' => $depositAmount,
                'subParties' => $party->subParties->map(function ($sub) use ($party) {
                    $subDeposits = $party->payments->where('sub_party_id', $sub->id);
                    return [
                        'id' => $sub->id,
                        'bookingCode' => $sub->booking_code,
                        'arrivalDate' => $sub->arrival_date->format('Y-m-d'),
                        'arrivalTime' => $sub->arrival_time ? substr($sub->arrival_time, 0, 5) : '',
                        'departureTime' => $sub->departure_time ? substr($sub->departure_time, 0, 5) : '',
                        'adults' => $sub->adults,
                        'children' => $sub->children,
                        'tables' => $sub->tables,
                        'extra' => $sub->extra,
                        'outlet_id' => $sub->outlet,
                        'outlet' => $sub->outletModel ? $sub->outletModel->name : $sub->outlet,
                        'location' => $sub->location,
                        'partyType' => $sub->party_type,
                        'groupCode' => $sub->group_code,
                        'note' => $sub->note,
                        'deposits' => $subDeposits->map(function ($dep) {
                            return [
                                'id' => $dep->id,
                                'date' => $dep->payment_date->format('Y-m-d'),
                                'method' => $dep->payment_method,
                                'amount' => $dep->amount,
                                'note' => $dep->note,
                                'status' => $dep->status
                            ];
                        })->values(),
                        'menuItems' => $sub->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'name' => $item->name,
                                'quantity' => $item->quantity,
                                'unit' => $item->unit,
                                'price' => $item->price,
                                'discount' => $item->discount,
                                'note' => $item->note,
                            ];
                        })->values()
                    ];
                })->values()
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $party = FbParty::find($id);
        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đặt tiệc.'
            ], 404);
        }

        $validated = $request->validate([
            'partyName' => 'required|string|max:255',
            'arrivalDate' => 'required|string',
            'confirmationType' => 'nullable|string',
            'confirmationDate' => 'nullable|string',
            'saleStaff' => 'nullable|string',
            'company' => 'nullable|string',
            'customer' => 'nullable|string',
            'email' => 'nullable|string|email',
            'note' => 'nullable|string',
            'vatNote' => 'nullable|string',
            'status' => 'nullable|string',
            'subParties' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $arrivalDate = $this->parseDate($request->arrivalDate);
            $confirmationDate = $request->confirmationDate ? $this->parseDate($request->confirmationDate) : null;

            $updateData = [
                'party_name' => $request->partyName,
                'arrival_date' => $arrivalDate,
                'confirmation_type' => $request->confirmationType ?? 'byDate',
                'confirmation_date' => $confirmationDate,
                'sale_staff' => $request->saleStaff,
                'company' => $request->company ?? 'KHÁCH LẺ',
                'customer' => $request->customer,
                'email' => $request->email,
                'note' => $request->note,
                'vat_note' => $request->vatNote,
            ];
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            $party->update($updateData);

            $party->subParties()->delete();
            $party->payments()->delete();

            if (!empty($request->subParties) && is_array($request->subParties)) {
                foreach ($request->subParties as $subData) {
                    $subArrivalDate = isset($subData['arrivalDate']) ? $this->parseDate($subData['arrivalDate']) : $arrivalDate;
                    $bookingCode = $subData['bookingCode'] ?? ('S' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT));

                    $subParty = FbSubParty::create([
                        'party_id' => $party->id,
                        'booking_code' => $bookingCode,
                        'arrival_date' => $subArrivalDate,
                        'arrival_time' => $subData['arrivalTime'] ?? null,
                        'departure_time' => $subData['departureTime'] ?? null,
                        'adults' => $subData['adults'] ?? 1,
                        'children' => $subData['children'] ?? 0,
                        'tables' => $subData['tables'] ?? 1,
                        'extra' => $subData['extra'] ?? 0,
                        'outlet' => $subData['outlet'] ?? null,
                        'location' => $subData['location'] ?? null,
                        'party_type' => $subData['partyType'] ?? null,
                        'group_code' => $subData['groupCode'] ?? null,
                        'note' => $subData['note'] ?? null,
                    ]);

                    if (!empty($subData['menuItems']) && is_array($subData['menuItems'])) {
                        foreach ($subData['menuItems'] as $item) {
                            FbPartyItem::create([
                                'sub_party_id' => $subParty->id,
                                'product_id' => $item['product_id'] ?? null,
                                'name' => $item['name'],
                                'quantity' => $item['quantity'] ?? 1,
                                'unit' => $item['unit'] ?? 'Phần',
                                'price' => $item['price'] ?? 0,
                                'discount' => $item['discount'] ?? 0,
                                'note' => $item['note'] ?? null,
                            ]);
                        }
                    }

                    if (!empty($subData['deposits']) && is_array($subData['deposits'])) {
                        foreach ($subData['deposits'] as $dep) {
                            $depDate = isset($dep['date']) ? $this->parseDate($dep['date']) : Carbon::now()->format('Y-m-d');
                            FbPartyPayment::create([
                                'party_id' => $party->id,
                                'sub_party_id' => $subParty->id,
                                'payment_date' => $depDate,
                                'payment_method' => $dep['method'],
                                'amount' => $dep['amount'] ?? 0,
                                'note' => $dep['note'] ?? null,
                                'status' => $dep['status'] ?? 'active',
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật đặt tiệc thành công.',
                'party_code' => $party->party_code
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi cập nhật tiệc: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseDate($dateStr)
    {
        if (str_contains($dateStr, '/')) {
            return Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
        }
        return Carbon::parse($dateStr)->format('Y-m-d');
    }

    public function cancel($id)
    {
        $party = FbParty::find($id);
        if (!$party) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đặt tiệc.'], 404);
        }

        $depositAmount = $party->payments()->where('status', '!=', 'cancelled')->sum('amount');
        if ($depositAmount > 0) {
            return response()->json(['success' => false, 'message' => 'Không thể huỷ tiệc đã có tiền cọc.'], 400);
        }

        $party->update(['status' => 'cancelled']);
        
        return response()->json(['success' => true, 'message' => 'Đã huỷ tiệc thành công.']);
    }

    public function completeSubParty($partyId, $subPartyId)
    {
        $party = FbParty::find($partyId);
        if (!$party) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đặt tiệc.'], 404);
        }

        $subParty = FbSubParty::where('party_id', $partyId)->find($subPartyId);
        if (!$subParty) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tiệc con.'], 404);
        }

        $subParty->update(['status' => 'completed']);

        $allCompleted = !FbSubParty::where('party_id', $partyId)
            ->where('status', '!=', 'completed')
            ->exists();

        if ($allCompleted) {
            $party->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true, 
            'message' => 'Đã hoàn thành tiệc con.',
            'partyCompleted' => $allCompleted
        ]);
    }

    public function checkConflict(Request $request)
    {
        $request->validate([
            'arrival_date' => 'required',
            'outlet_id' => 'required',
            'location' => 'required',
            'arrival_time' => 'required',
            'departure_time' => 'required'
        ]);

        $date = $this->parseDate($request->arrival_date);
        $start = Carbon::parse($date . ' ' . $request->arrival_time);
        $end = Carbon::parse($date . ' ' . $request->departure_time);

        $query = FbSubParty::where('arrival_date', $date)
            ->where(function($q) use ($request) {
                $q->where('outlet', $request->outlet_id);
                if ($request->has('outlet_name') && !empty($request->outlet_name)) {
                    $q->orWhere('outlet', $request->outlet_name);
                }
            })
            ->where('location', $request->location)
            ->where('status', '!=', 'cancelled');

        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $conflicts = $query->get();

        foreach ($conflicts as $conflict) {
            if (!$conflict->arrival_time || !$conflict->departure_time) {
                continue;
            }
            $cStart = Carbon::parse($date . ' ' . $conflict->arrival_time);
            $cEnd = Carbon::parse($date . ' ' . $conflict->departure_time);

            // Check overlap
            if ($start->lt($cEnd) && $end->gt($cStart)) {
                return response()->json([
                    'conflict' => true,
                    'message' => "Bị trùng lịch với tiệc: {$conflict->booking_code} ({$conflict->arrival_time} - {$conflict->departure_time})"
                ]);
            }
        }

        return response()->json([
            'conflict' => false,
            'message' => 'Không trùng lịch.'
        ]);
    }
}
