<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FbOrder;
use App\Models\FbOrderItem;
use App\Models\FbTable;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogService;

class FbOrderController extends Controller
{
    /**
     * Get active orders (bills) for a table
     */
    public function getActiveOrders($tableId)
    {
        $orders = FbOrder::with('items.product.unit')->where('table_id', $tableId)
            ->whereIn('status', ['serving', 'waiting'])
            ->get();

        // Transform into the format expected by frontend
        $bills = $orders->map(function ($order) {
            $allItems = $order->items->map(function ($item) {
                return [
                    'id' => $item->id, // order item id
                    'parent_item_id' => $item->parent_item_id,
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'quantity' => (float)$item->quantity,
                    'price' => (float)$item->price,
                    'discount' => (float)$item->discount,
                    'surcharge' => (float)$item->surcharge,
                    'baseDiscount' => (float)$item->base_discount,
                    'baseSurcharge' => (float)$item->base_surcharge,
                    'note' => $item->note,
                    'product' => $item->product // original product details
                ];
            })->toArray();

            // Group into main items and sub_items
            $mainItems = [];
            $subItemsByParent = [];
            
            foreach ($allItems as $item) {
                if ($item['parent_item_id']) {
                    if (!isset($subItemsByParent[$item['parent_item_id']])) {
                        $subItemsByParent[$item['parent_item_id']] = [];
                    }
                    $subItemsByParent[$item['parent_item_id']][] = $item;
                } else {
                    $mainItems[] = $item;
                }
            }

            foreach ($mainItems as &$mainItem) {
                $mainItem['sub_items'] = $subItemsByParent[$mainItem['id']] ?? [];
            }

            return [
                'id' => $order->id,
                'name' => $order->name,
                'status' => $order->status,
                'guestCount' => $order->guest_count,
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'customerEmail' => $order->customer_email,
                'customerAddress' => $order->customer_address,
                'publicNote' => $order->public_note,
                'internalNote' => $order->internal_note,
                'internalNoteDiscount' => $order->internal_note_discount,
                'promotionId' => $order->promotion_id,
                'creator_id' => $order->creator_id,
                'items' => $mainItems
            ];
        });

        return response()->json($bills);
    }

    /**
     * Sync (Create/Update/Delete) orders for a table
     */
    public function syncOrders(Request $request, $tableId)
    {
        $bills = $request->input('bills', []);
        $deletedItems = $request->input('deleted_items', []);
        $deletedBills = $request->input('deleted_bills', []);
        $pendingActionLogs = $request->input('pending_action_logs', []);

        \Illuminate\Support\Facades\Log::info("SYNC_ORDERS: Payload received for table $tableId", ['bills' => $bills, 'deletedItems' => $deletedItems, 'deletedBills' => $deletedBills, 'pendingActionLogs' => $pendingActionLogs]);
        
        DB::beginTransaction();
        try {
            $table = FbTable::findOrFail($tableId);
            
            // Get original active orders and their items before modifications
            $oldOrdersWithItems = FbOrder::where('table_id', $tableId)
                ->whereIn('status', ['serving', 'waiting'])
                ->with('items')
                ->get();
            
            $oldSummaryLines = [];
            foreach ($oldOrdersWithItems as $o) {
                $oldSummaryLines[] = "• Đơn hàng: {$o->name} (Khách: {$o->guest_count})";
                foreach ($o->items as $it) {
                    $noteStr = $it->note ? " [Ghi chú: {$it->note}]" : "";
                    $surStr = $it->surcharge > 0 ? " [Phụ thu: " . number_format($it->surcharge) . " ₫]" : "";
                    $discStr = $it->discount > 0 ? " [Giảm giá: " . number_format($it->discount) . " ₫]" : "";
                    $oldSummaryLines[] = "  - " . $it->product_name . " x" . (float)$it->quantity . " (" . number_format($it->price) . " ₫)" . $discStr . $surStr . $noteStr;
                }
            }
            $oldDetailsSummary = count($oldSummaryLines) > 0 ? implode("\n", $oldSummaryLines) : "Trống";
            
            $changesToLog = [];
            
            // Process deleted bills
            if (is_array($deletedBills)) {
                foreach ($deletedBills as $delBill) {
                    $orderId = $delBill['id'] ?? null;
                    $reason = $delBill['reason'] ?? 'Không có lý do';
                    if ($orderId) {
                        $order = FbOrder::find($orderId);
                        if ($order) {
                            ActivityLogService::logDelete(
                                $request, 
                                $order, 
                                'fnb', 
                                'FbOrderController', 
                                "Xóa đơn hàng: " . $order->name . " - Lý do: " . $reason, 
                                $order->name
                            );
                            $order->status = 'cancelled';
                            $order->save();
                        }
                    }
                }
            }

            // Process deleted items
            if (is_array($deletedItems)) {
                foreach ($deletedItems as $delItem) {
                    $itemId = $delItem['id'] ?? null;
                    $reason = $delItem['reason'] ?? 'Không có lý do';
                    if ($itemId) {
                        $item = FbOrderItem::find($itemId);
                        if ($item) {
                            ActivityLogService::logDelete(
                                $request, 
                                $item, 
                                'fnb', 
                                'FbOrderController', 
                                "Xóa món: " . $item->product_name . " x" . (float)$item->quantity . " - Lý do: " . $reason,
                                $item->product_name
                            );
                            // We don't necessarily need to delete it here if the payload already omits it
                            // but if it's logged, it's good. The sync logic below will delete it anyway
                            // since it deletes all items and re-inserts.
                        }
                    }
                }
            }
            
            // Get existing active orders
            $existingOrders = FbOrder::where('table_id', $tableId)
                ->whereIn('status', ['serving', 'waiting'])
                ->get();
            
            $existingOrderIds = $existingOrders->pluck('id')->toArray();
            $incomingOrderIds = [];

            foreach ($bills as $index => $billData) {
                // If bill has an ID and it's a number (not a frontend generated temp ID), update it
                $orderId = (isset($billData['id']) && is_numeric($billData['id']) && in_array($billData['id'], $existingOrderIds)) ? $billData['id'] : null;
                
                if ($orderId) {
                    $incomingOrderIds[] = $orderId;
                    $order = FbOrder::find($orderId);
                } else {
                    $order = new FbOrder();
                    $order->table_id = $tableId;
                    $order->outlet_code = $table->location_id; // Using location_id as outlet_code for now based on previous structures
                }

                $order->name = $billData['name'] ?? ('Bill ' . ($index + 1));
                $order->status = $billData['status'] ?? 'serving';
                $order->guest_count = $billData['guestCount'] ?? 1;
                $order->customer_name = $billData['customerName'] ?? null;
                $order->customer_phone = $billData['customerPhone'] ?? null;
                $order->customer_email = $billData['customerEmail'] ?? null;
                $order->customer_address = $billData['customerAddress'] ?? null;
                $order->public_note = $billData['publicNote'] ?? null;
                $order->internal_note = $billData['internalNote'] ?? null;
                $order->internal_note_discount = $billData['internalNoteDiscount'] ?? null;
                $order->promotion_id = $billData['promotionId'] ?? null;
                $order->creator_id = $billData['creator_id'] ?? null; // Added creator_id logic
                
                // Calculate total from items
                $totalAmount = 0;
                $items = $billData['items'] ?? [];
                
                foreach ($items as $item) {
                    $p = $item['price'] ?? 0;
                    $q = $item['quantity'] ?? 1;
                    $d = $item['discount'] ?? 0;
                    $s = $item['surcharge'] ?? 0;
                    $totalAmount += ($p * $q) - $d + $s;
                }
                
                $order->total_amount = $totalAmount;
                $order->save();
                
                if (!$orderId) {
                    $incomingOrderIds[] = $order->id;
                }

                // Sync items
                // Fetch old items to compare before deleting
                $oldItems = FbOrderItem::where('order_id', $order->id)->get()->keyBy('id');
                
                // Delete existing items
                FbOrderItem::where('order_id', $order->id)->delete();
                
                // Re-insert items
                foreach ($items as $item) {
                    $productId = $item['product_id'] ?? ($item['product']['id'] ?? $item['id']);
                    $productName = $item['name'] ?? ($item['product']['name'] ?? 'Unknown');
                    $newQty = $item['quantity'] ?? 1;
                    $newDiscount = $item['discount'] ?? 0;
                    $newPrice = $item['price'] ?? 0;

                    // Check if this is an existing item or a new item
                    $isExistingItem = isset($item['id']) && is_numeric($item['id']) && $oldItems->has($item['id']);
                    
                    if ($isExistingItem) {
                        // Log updates for existing items
                        $oldItem = $oldItems->get($item['id']);
                        $newSurcharge = $item['surcharge'] ?? 0;
                        $newNote = $item['note'] ?? null;
                        
                        if (floatval($oldItem->quantity) != floatval($newQty) || floatval($oldItem->discount) != floatval($newDiscount) || floatval($oldItem->price) != floatval($newPrice) || floatval($oldItem->surcharge) != floatval($newSurcharge) || $oldItem->note !== $newNote) {
                            $changes = [];
                            if (floatval($oldItem->quantity) != floatval($newQty)) $changes[] = "Số lượng (" . floatval($oldItem->quantity) . " -> " . floatval($newQty) . ")";
                            if (floatval($oldItem->price) != floatval($newPrice)) $changes[] = "Giá (" . number_format($oldItem->price) . " -> " . number_format($newPrice) . ")";
                            if (floatval($oldItem->discount) != floatval($newDiscount)) $changes[] = "Giảm giá (" . number_format($oldItem->discount) . " -> " . number_format($newDiscount) . ")";
                            if (floatval($oldItem->surcharge) != floatval($newSurcharge)) $changes[] = "Phụ thu (" . number_format($oldItem->surcharge) . " -> " . number_format($newSurcharge) . ")";
                            if ($oldItem->note !== $newNote) $changes[] = "Ghi chú";
                            
                            $changeStr = implode(', ', $changes);
                            $changesToLog[] = "Cập nhật món {$productName} ({$changeStr}) trên hóa đơn {$order->name}";
                        }
                    } else {
                        $changesToLog[] = "Thêm món {$productName} x{$newQty} trên hóa đơn {$order->name}";
                    }
                    
                    $newItem = FbOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'quantity' => $newQty,
                        'price' => $newPrice,
                        'discount' => $newDiscount,
                        'surcharge' => $item['surcharge'] ?? 0,
                        'base_discount' => $item['baseDiscount'] ?? 0,
                        'base_surcharge' => $item['baseSurcharge'] ?? 0,
                        'note' => $item['note'] ?? null,
                    ]);

                    if (isset($item['sub_items']) && is_array($item['sub_items'])) {
                        $subItemInserts = [];
                        foreach ($item['sub_items'] as $subItem) {
                            $subProductId = $subItem['product_id'] ?? ($subItem['product']['id'] ?? $subItem['id']);
                            $subItemInserts[] = [
                                'order_id' => $order->id,
                                'parent_item_id' => $newItem->id,
                                'product_id' => $subProductId,
                                'product_name' => $subItem['name'] ?? ($subItem['product']['name'] ?? 'Unknown'),
                                'quantity' => $subItem['quantity'] ?? 1,
                                'price' => $subItem['price'] ?? 0,
                                'discount' => $subItem['discount'] ?? 0,
                                'surcharge' => $subItem['surcharge'] ?? 0,
                                'base_discount' => $subItem['baseDiscount'] ?? 0,
                                'base_surcharge' => $subItem['baseSurcharge'] ?? 0,
                                'note' => $subItem['note'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        if (count($subItemInserts) > 0) {
                            FbOrderItem::insert($subItemInserts);
                        }
                    }
                }
            }
            
            // Any existing orders that were not in incoming payload should be cancelled (if they were merged away)
            $ordersToDelete = array_diff($existingOrderIds, $incomingOrderIds);
            if (count($ordersToDelete) > 0) {
                FbOrder::whereIn('id', $ordersToDelete)->update(['status' => 'cancelled']);
            }
            
            // Update table status
            if (count($incomingOrderIds) > 0) {
                $table->status = 'serving';
            } else {
                $table->status = 'empty';
            }
            $table->save();
            
            // Log pending actions (split, merge)
            if (is_array($pendingActionLogs)) {
                foreach ($pendingActionLogs as $log) {
                    $desc = $log['description'] ?? 'Thao tác không xác định';
                    $reason = $log['reason'] ?? 'Không có lý do';
                    $changesToLog[] = "{$desc} (Lý do: {$reason})";
                }
            }

            // Refresh and load new state of orders with items
            $newOrdersWithItems = FbOrder::where('table_id', $tableId)
                ->whereIn('status', ['serving', 'waiting'])
                ->with('items')
                ->get();

            $newSummaryLines = [];
            foreach ($newOrdersWithItems as $o) {
                $newSummaryLines[] = "• Đơn hàng: {$o->name} (Khách: {$o->guest_count})";
                foreach ($o->items as $it) {
                    $noteStr = $it->note ? " [Ghi chú: {$it->note}]" : "";
                    $surStr = $it->surcharge > 0 ? " [Phụ thu: " . number_format($it->surcharge) . " ₫]" : "";
                    $discStr = $it->discount > 0 ? " [Giảm giá: " . number_format($it->discount) . " ₫]" : "";
                    $newSummaryLines[] = "  - " . $it->product_name . " x" . (float)$it->quantity . " (" . number_format($it->price) . " ₫)" . $discStr . $surStr . $noteStr;
                }
            }
            $newDetailsSummary = count($newSummaryLines) > 0 ? implode("\n", $newSummaryLines) : "Trống";

            // If we have changes, log exactly ONE combined entry
            if (!empty($changesToLog)) {
                $addedCount = 0;
                $updatedCount = 0;
                $splitCount = 0;
                $mergeCount = 0;
                $otherCount = 0;
                foreach ($changesToLog as $logLine) {
                    if (str_starts_with($logLine, "Thêm")) {
                        $addedCount++;
                    } elseif (str_starts_with($logLine, "Cập nhật")) {
                        $updatedCount++;
                    } elseif (str_contains($logLine, "Tách")) {
                        $splitCount++;
                    } elseif (str_contains($logLine, "Gộp")) {
                        $mergeCount++;
                    } else {
                        $otherCount++;
                    }
                }
                
                $summaryParts = [];
                if ($addedCount > 0) $summaryParts[] = "Thêm {$addedCount} món";
                if ($updatedCount > 0) $summaryParts[] = "Cập nhật {$updatedCount} món";
                if ($splitCount > 0) $summaryParts[] = "Tách bill";
                if ($mergeCount > 0) $summaryParts[] = "Gộp bill";
                if ($otherCount > 0) $summaryParts[] = "Thao tác khác ({$otherCount})";
                
                $descriptionSummary = implode(", ", $summaryParts);
                if (empty($descriptionSummary)) {
                    $descriptionSummary = "Thay đổi đơn hàng";
                }

                \App\Services\ActivityLogService::log([
                    'user_id' => $request->user()?->id,
                    'user_name' => $request->user()?->name ?? 'Hệ thống',
                    'employee_code' => $request->user()?->employee_code,
                    'action' => 'update',
                    'module' => 'fnb',
                    'component' => 'FbOrderController',
                    'description' => "Đồng bộ đơn hàng Bàn {$table->name}: {$descriptionSummary}",
                    'target_type' => 'FbTable',
                    'target_id' => $table->id,
                    'target_label' => $table->name,
                    'old_values' => [
                        'items' => $oldDetailsSummary
                    ],
                    'new_values' => [
                        'actions' => implode("\n", $changesToLog),
                        'items' => $newDetailsSummary
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_method' => $request->method(),
                    'request_url' => $request->fullUrl(),
                    'response_status' => 200,
                ]);
            }
            
            DB::commit();
            return response()->json(['message' => 'Orders synchronized successfully', 'table_status' => $table->status]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Order sync failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'Failed to sync orders', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search orders (bills)
     */
    public function search(Request $request)
    {
        $query = FbOrder::with(['table', 'creator'])->withCount('items');
        
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('outlet_code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 100);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Transfer orders from one table to another
     */
    public function transferTable(Request $request, $fromId, $toId)
    {
        try {
            DB::beginTransaction();

            $fromTable = FbTable::findOrFail($fromId);
            $toTable = FbTable::findOrFail($toId);

            if ($toTable->status !== 'empty' && $toTable->status !== 'Active') {
                return response()->json(['message' => 'Bàn đích đã có khách, không thể chuyển!'], 400);
            }

            // Move active orders
            $activeOrders = FbOrder::where('table_id', $fromId)
                ->whereIn('status', ['serving', 'waiting'])
                ->get();

            if ($activeOrders->isEmpty()) {
                return response()->json(['message' => 'Bàn hiện tại không có đơn hàng nào để chuyển!'], 400);
            }

            foreach ($activeOrders as $order) {
                $order->table_id = $toId;
                $order->save();
            }

            // Update statuses
            $fromTable->status = 'empty';
            $fromTable->save();

            $toTable->status = 'serving';
            $toTable->save();

            // Log activity
            $reason = $request->input('reason', 'Không có lý do');
            \App\Services\ActivityLogService::log([
                'user_id' => $request->user()?->id,
                'user_name' => $request->user()?->name ?? 'Hệ thống',
                'employee_code' => $request->user()?->employee_code,
                'action' => 'transfer',
                'module' => 'fnb',
                'component' => 'FbOrderController',
                'description' => "Chuyển bàn từ {$fromTable->name} sang {$toTable->name} - Lý do: {$reason}",
                'target_type' => 'FbTable',
                'target_id' => $fromTable->id,
                'target_label' => $fromTable->name,
                'old_values' => [
                    'table_name' => $fromTable->name,
                    'table_code' => $fromTable->table_code,
                    'status' => 'serving',
                ],
                'new_values' => [
                    'table_name' => $toTable->name,
                    'table_code' => $toTable->table_code,
                    'status' => 'serving',
                    'reason' => $reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'response_status' => 200,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Chuyển bàn thành công!',
                'data' => [
                    'from_table_id' => $fromId,
                    'to_table_id' => $toId,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Table transfer failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to transfer table', 'error' => $e->getMessage()], 500);
        }
    }

    public function transferItems(Request $request, $fromId, $toId)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric',
            'items.*.product_name' => 'nullable|string',
            'items.*.discount' => 'nullable|numeric',
            'items.*.surcharge' => 'nullable|numeric',
            'items.*.base_discount' => 'nullable|numeric',
            'items.*.base_surcharge' => 'nullable|numeric',
            'items.*.note' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $fromTable = FbTable::findOrFail($fromId);
            $toTable = FbTable::findOrFail($toId);

            if ($toTable->status !== 'empty' && $toTable->status !== 'Active' && $toTable->status !== 'serving') {
                return response()->json(['message' => 'Bàn đích đang ở trạng thái không thể nhận món!'], 400);
            }

            $fromOrder = FbOrder::where('table_id', $fromId)
                ->whereIn('status', ['serving', 'waiting'])
                ->first();

            if (!$fromOrder) {
                return response()->json(['message' => 'Bàn gốc không có hóa đơn đang mở!'], 400);
            }

            $toOrder = FbOrder::where('table_id', $toId)
                ->whereIn('status', ['serving', 'waiting'])
                ->first();

            if (!$toOrder) {
                $toOrder = FbOrder::create([
                    'table_id' => $toId,
                    'outlet_code' => $fromOrder->outlet_code,
                    'name' => 'Bill',
                    'status' => 'serving',
                ]);
            }

            foreach ($validated['items'] as $transferItem) {
                $fromItem = FbOrderItem::where('order_id', $fromOrder->id)
                    ->where('product_id', $transferItem['product_id'])
                    ->where('price', $transferItem['price'])
                    ->first();

                if ($fromItem) {
                    $transferQty = $transferItem['quantity'];
                    
                    if ($fromItem->quantity <= $transferQty) {
                        $fromItem->delete();
                    } else {
                        $fromItem->quantity -= $transferQty;
                        $fromItem->save();
                    }

                    $toItem = FbOrderItem::where('order_id', $toOrder->id)
                        ->where('product_id', $transferItem['product_id'])
                        ->where('price', $transferItem['price'])
                        ->first();

                    if ($toItem) {
                        $toItem->quantity += $transferQty;
                        $toItem->save();
                    } else {
                        FbOrderItem::create([
                            'order_id' => $toOrder->id,
                            'product_id' => $transferItem['product_id'],
                            'product_name' => $transferItem['product_name'] ?? $fromItem->product_name,
                            'quantity' => $transferQty,
                            'price' => $transferItem['price'],
                            'discount' => $transferItem['discount'] ?? 0,
                            'surcharge' => $transferItem['surcharge'] ?? 0,
                            'base_discount' => $transferItem['base_discount'] ?? 0,
                            'base_surcharge' => $transferItem['base_surcharge'] ?? 0,
                            'note' => $transferItem['note'] ?? $fromItem->note,
                        ]);
                    }
                }
            }

            $fromOrderRemaining = FbOrderItem::where('order_id', $fromOrder->id)->count();
            if ($fromOrderRemaining == 0) {
                $fromOrder->status = 'cancelled';
                $fromOrder->total_amount = 0;
                $fromOrder->save();
                $fromTable->status = 'empty';
            } else {
                $fromTable->status = 'serving';
                $fromOrder->total_amount = FbOrderItem::where('order_id', $fromOrder->id)
                    ->get()
                    ->sum(function ($item) {
                        return ($item->price * $item->quantity) - $item->discount + $item->surcharge;
                    });
                $fromOrder->save();
            }
            $fromTable->save();

            $toTable->status = 'serving';
            $toTable->save();

            $toOrder->total_amount = FbOrderItem::where('order_id', $toOrder->id)
                ->get()
                ->sum(function ($item) {
                    return ($item->price * $item->quantity) - $item->discount + $item->surcharge;
                });
            $toOrder->save();
            $toOrder->save();

            $reason = $request->input('reason', 'Không có lý do');
            $transferredNames = collect($validated['items'])->map(function($i) {
                return ($i['product_name'] ?? 'Món') . ' x' . ($i['quantity'] ?? 1);
            })->implode(', ');
            
            \App\Services\ActivityLogService::log([
                'user_id' => $request->user()?->id,
                'user_name' => $request->user()?->name ?? 'Hệ thống',
                'employee_code' => $request->user()?->employee_code,
                'action' => 'transfer',
                'module' => 'fnb',
                'component' => 'FbOrderController',
                'description' => "Chuyển món từ bàn {$fromTable->name} sang {$toTable->name} - Lý do: {$reason}",
                'target_type' => 'FbTable',
                'target_id' => $fromTable->id,
                'target_label' => $fromTable->name,
                'old_values' => [
                    'from_table' => $fromTable->name,
                    'items' => $transferredNames,
                ],
                'new_values' => [
                    'to_table' => $toTable->name,
                    'items' => $transferredNames,
                    'reason' => $reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'response_status' => 200,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Chuyển món thành công!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Transfer items failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to transfer items', 'error' => $e->getMessage()], 500);
        }
    }
}
