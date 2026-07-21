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
            $query->where(function ($q) use ($request) {
                $q->whereBetween('arrival_date', [$request->start_date, $request->end_date])
                  ->orWhereHas('subParties', function($q2) use ($request) {
                      $q2->whereBetween('arrival_date', [$request->start_date, $request->end_date]);
                  });
            });
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

        $completedIds = [];
        $servingIds = [];

        // Định dạng dữ liệu trả về giống cấu trúc frontend mong đợi
        $formatted = $parties->map(function ($party) use (&$completedIds, &$servingIds) {
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
                        $startDt = Carbon::parse($arrDateStr . ' ' . $sub->arrival_time);
                        if (!$earliestDatetime || $startDt->lt($earliestDatetime)) {
                            $earliestDatetime = $startDt;
                        }
                    }

                    if ($sub->departure_time) {
                        $endDt = Carbon::parse($arrDateStr . ' ' . $sub->departure_time);
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
                $now = Carbon::now();
                
                if ($now->gt($latestDatetime)) {
                    $status = 'completed';
                    $completedIds[] = $party->id;
                } elseif ($now->between($earliestDatetime, $latestDatetime) && $status === 'confirmed') {
                    $status = 'serving';
                    $servingIds[] = $party->id;
                }
            } elseif (in_array($status, ['confirmed', 'serving']) && !$earliestDatetime) {
                // Fallback nếu không có giờ
                $now = Carbon::now();
                $arrivalDate = Carbon::parse($party->arrival_date);
                if ($arrivalDate->isPast() && !$arrivalDate->isToday()) {
                    $status = 'completed';
                    $completedIds[] = $party->id;
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
                'arrivalDate' => Carbon::parse($party->arrival_date)->format('d/m/Y'),
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

        if (!empty($completedIds)) {
            FbParty::whereIn('id', $completedIds)->update(['status' => 'completed']);
        }
        if (!empty($servingIds)) {
            FbParty::whereIn('id', $servingIds)->update(['status' => 'serving']);
        }

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
                    
                    // Kiểm tra trùng lịch
                    $conflictMsg = $this->checkSubPartyConflictInternal(
                        $subArrivalDate,
                        $subData['outlet'] ?? null,
                        $subData['location'] ?? null,
                        $subData['arrivalTime'] ?? null,
                        $subData['departureTime'] ?? null
                    );
                    if ($conflictMsg) {
                        throw new \Exception($conflictMsg);
                    }

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

            \App\Services\ActivityLogService::logCreate(
                $request,
                $party,
                'fnb',
                'FbPartyController',
                "Tạo mới đặt tiệc: {$party->party_name}",
                $party->party_code
            );

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
                'arrivalDate' => Carbon::parse($party->arrival_date)->format('Y-m-d'),
                'confirmationType' => $party->confirmation_type,
                'confirmationDate' => $party->confirmation_date ? Carbon::parse($party->confirmation_date)->format('Y-m-d') : null,
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
            $oldParty = FbParty::with(['subParties.items', 'payments'])->find($id);
            $oldValues = [
                'party_name' => $oldParty->party_name,
                'arrival_date' => $oldParty->arrival_date instanceof \DateTimeInterface ? $oldParty->arrival_date->format('Y-m-d') : (string)$oldParty->arrival_date,
                'confirmation_type' => $oldParty->confirmation_type,
                'confirmation_date' => $oldParty->confirmation_date instanceof \DateTimeInterface ? $oldParty->confirmation_date->format('Y-m-d') : (string)$oldParty->confirmation_date,
                'sale_staff' => $oldParty->sale_staff,
                'company' => $oldParty->company,
                'customer' => $oldParty->customer,
                'email' => $oldParty->email,
                'note' => $oldParty->note,
                'vat_note' => $oldParty->vat_note,
                'status' => $oldParty->status,
                'sub_parties' => $this->serializeSubParties($oldParty->subParties),
                'deposits' => $this->serializeDeposits($oldParty->payments),
            ];

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

            // Xóa các items của subParties trước để tránh rác DB
            $subPartyIds = $party->subParties()->pluck('id');
            FbPartyItem::whereIn('sub_party_id', $subPartyIds)->delete();

            $party->subParties()->delete();
            $party->payments()->delete();

            if (!empty($request->subParties) && is_array($request->subParties)) {
                foreach ($request->subParties as $subData) {
                    $subArrivalDate = isset($subData['arrivalDate']) ? $this->parseDate($subData['arrivalDate']) : $arrivalDate;
                    
                    // Kiểm tra trùng lịch
                    $conflictMsg = $this->checkSubPartyConflictInternal(
                        $subArrivalDate,
                        $subData['outlet'] ?? null,
                        $subData['location'] ?? null,
                        $subData['arrivalTime'] ?? null,
                        $subData['departureTime'] ?? null
                    );
                    if ($conflictMsg) {
                        throw new \Exception($conflictMsg);
                    }

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

            \App\Services\ActivityLogService::log([
                'user_id' => $request->user()?->id,
                'user_name' => $request->user()?->name ?? 'Hệ thống',
                'employee_code' => $request->user()?->employee_code,
                'action' => 'update',
                'module' => 'fnb',
                'component' => 'FbPartyController',
                'description' => "Cập nhật đặt tiệc: {$party->party_name}",
                'target_type' => 'FbParty',
                'target_id' => $party->id,
                'target_label' => $party->party_code,
                'old_values' => $oldValues,
                'new_values' => [
                    'party_name' => $party->party_name,
                    'arrival_date' => $party->arrival_date instanceof \DateTimeInterface ? $party->arrival_date->format('Y-m-d') : (string)$party->arrival_date,
                    'confirmation_type' => $party->confirmation_type,
                    'confirmation_date' => $party->confirmation_date instanceof \DateTimeInterface ? $party->confirmation_date->format('Y-m-d') : (string)$party->confirmation_date,
                    'sale_staff' => $party->sale_staff,
                    'company' => $party->company,
                    'customer' => $party->customer,
                    'email' => $party->email,
                    'note' => $party->note,
                    'vat_note' => $party->vat_note,
                    'status' => $party->status,
                    'sub_parties' => $this->serializeSubParties($party->fresh(['subParties.items'])->subParties),
                    'deposits' => $this->serializeDeposits($party->fresh(['payments'])->payments),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'response_status' => 200,
            ]);

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

    public function cancel(Request $request, $id)
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
        
        $reason = $request->input('reason', 'Không có lý do');
        \App\Services\ActivityLogService::logDelete(
            $request,
            $party,
            'fnb',
            'FbPartyController',
            "Huỷ đặt tiệc: {$party->party_name} - Lý do: {$reason}",
            $party->party_code
        );

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

        // Log hoàn thành tiệc con
        \App\Services\ActivityLogService::log([
            'user_id' => request()->user()?->id,
            'user_name' => request()->user()?->name ?? 'Hệ thống',
            'employee_code' => request()->user()?->employee_code,
            'action' => 'update',
            'module' => 'fnb',
            'component' => 'FbPartyController',
            'description' => "Hoàn thành tiệc con: {$subParty->outlet} - {$subParty->location}",
            'target_type' => 'FbSubParty',
            'target_id' => $subParty->id,
            'target_label' => $party->party_name,
            'old_values' => ['status' => 'serving'],
            'new_values' => [
                'status' => 'completed',
                'outlet' => $subParty->outlet,
                'location' => $subParty->location,
                'arrival_date' => $subParty->arrival_date,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_method' => request()->method(),
            'request_url' => request()->fullUrl(),
            'response_status' => 200,
        ]);

        $allCompleted = !FbSubParty::where('party_id', $partyId)
            ->where('status', '!=', 'completed')
            ->exists();

        if ($allCompleted) {
            $party->update(['status' => 'completed']);

            // Log hoàn thành toàn bộ đặt tiệc
            \App\Services\ActivityLogService::log([
                'user_id' => request()->user()?->id,
                'user_name' => request()->user()?->name ?? 'Hệ thống',
                'employee_code' => request()->user()?->employee_code,
                'action' => 'update',
                'module' => 'fnb',
                'component' => 'FbPartyController',
                'description' => "Hoàn thành toàn bộ đặt tiệc: {$party->party_name}",
                'target_type' => 'FbParty',
                'target_id' => $party->id,
                'target_label' => $party->party_code,
                'old_values' => ['status' => $party->getOriginal('status') ?? 'serving'],
                'new_values' => [
                    'status' => 'completed',
                    'party_name' => $party->party_name,
                    'party_code' => $party->party_code,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_method' => request()->method(),
                'request_url' => request()->fullUrl(),
                'response_status' => 200,
            ]);
        }

        return response()->json([
            'success' => true, 
            'message' => 'Đã hoàn thành tiệc con.',
            'partyCompleted' => $allCompleted
        ]);
    }

    private function checkSubPartyConflictInternal($arrivalDate, $outlet, $location, $arrivalTime, $departureTime, $excludeId = null, $outletName = null)
    {
        if (!$arrivalTime || !$departureTime || !$outlet || !$location) {
            return null; 
        }

        $date = $this->parseDate($arrivalDate);
        $start = Carbon::parse($date . ' ' . $arrivalTime);
        $end = Carbon::parse($date . ' ' . $departureTime);

        $query = FbSubParty::where('arrival_date', $date)
            ->where(function($q) use ($outlet, $outletName) {
                $q->where('outlet', $outlet);
                if (!empty($outletName)) {
                    $q->orWhere('outlet', $outletName);
                }
            })
            ->where('location', $location)
            ->where('status', '!=', 'cancelled');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflicts = $query->get();

        foreach ($conflicts as $conflict) {
            if (!$conflict->arrival_time || !$conflict->departure_time) {
                continue;
            }
            $cStart = Carbon::parse($date . ' ' . $conflict->arrival_time);
            $cEnd = Carbon::parse($date . ' ' . $conflict->departure_time);

            if ($start->lt($cEnd) && $end->gt($cStart)) {
                return "Bị trùng lịch với tiệc: {$conflict->booking_code} ({$conflict->arrival_time} - {$conflict->departure_time})";
            }
        }

        return null;
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

    private function serializeSubParties($subParties)
    {
        $lines = [];
        foreach ($subParties as $sub) {
            if (is_array($sub)) {
                $code = $sub['bookingCode'] ?? '?';
                $adults = $sub['adults'] ?? 1;
                $children = $sub['children'] ?? 0;
                $tables = $sub['tables'] ?? 1;
                $outletId = $sub['outlet'] ?? null;
                $outlet = \App\Models\Outlet::where('id', $outletId)->value('name') ?? $outletId;
                $location = $sub['location'] ?? '';
                
                $itemsStr = '';
                if (!empty($sub['menuItems']) && is_array($sub['menuItems'])) {
                    $itemNames = [];
                    foreach ($sub['menuItems'] as $item) {
                        $itemNames[] = "    + " . ($item['name'] ?? 'Món') . ' x' . ($item['quantity'] ?? 1);
                    }
                    $itemsStr = "\n  * Món ăn:\n" . implode("\n", $itemNames);
                }
                
                $lines[] = "• Tiệc con {$code}:\n  * Khách: {$adults}NL, {$children}TE, {$tables} bàn\n  * Điểm: {$outlet} ({$location}){$itemsStr}";
            } else {
                $code = $sub->booking_code;
                $adults = $sub->adults;
                $children = $sub->children;
                $tables = $sub->tables;
                $outlet = $sub->outletModel ? $sub->outletModel->name : $sub->outlet;
                $location = $sub->location;
                
                $itemNames = [];
                foreach ($sub->items as $item) {
                    $itemNames[] = "    + " . $item->name . ' x' . (float)$item->quantity;
                }
                $itemsStr = count($itemNames) > 0 ? "\n  * Món ăn:\n" . implode("\n", $itemNames) : '';
                
                $lines[] = "• Tiệc con {$code}:\n  * Khách: {$adults}NL, {$children}TE, {$tables} bàn\n  * Điểm: {$outlet} ({$location}){$itemsStr}";
            }
        }
        return count($lines) > 0 ? implode("\n\n", $lines) : 'Trống';
    }

    private function serializeDeposits($deposits)
    {
        $lines = [];
        foreach ($deposits as $dep) {
            if (is_array($dep)) {
                $date = $dep['date'] ?? '';
                $method = $dep['method'] ?? '';
                $amount = $dep['amount'] ?? 0;
                $note = $dep['note'] ?? '';
                $lines[] = "- Đặt cọc: " . number_format($amount) . " ₫ bằng {$method} ngày {$date}" . ($note ? " ({$note})" : "");
            } else {
                $date = $dep->payment_date ? $dep->payment_date->format('Y-m-d') : '';
                $method = $dep->payment_method;
                $amount = $dep->amount;
                $note = $dep->note;
                $lines[] = "- Đặt cọc: " . number_format($amount) . " ₫ bằng {$method} ngày {$date}" . ($note ? " ({$note})" : "");
            }
        }
        return count($lines) > 0 ? implode("\n", $lines) : 'Trống';
    }
}
