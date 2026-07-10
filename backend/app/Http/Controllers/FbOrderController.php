<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FbOrder;
use App\Models\FbOrderItem;
use App\Models\FbTable;
use Illuminate\Support\Facades\DB;

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
        \Illuminate\Support\Facades\Log::info("SYNC_ORDERS: Payload received for table $tableId", ['bills' => $bills]);
        
        DB::beginTransaction();
        try {
            $table = FbTable::findOrFail($tableId);
            
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
                // Delete existing items
                FbOrderItem::where('order_id', $order->id)->delete();
                
                // Re-insert items
                foreach ($items as $item) {
                    $productId = $item['product_id'] ?? ($item['product']['id'] ?? $item['id']);
                    
                    $newItem = FbOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'product_name' => $item['name'] ?? ($item['product']['name'] ?? 'Unknown'),
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $item['price'] ?? 0,
                        'discount' => $item['discount'] ?? 0,
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
            
            DB::commit();
            return response()->json(['message' => 'Orders synchronized successfully', 'table_status' => $table->status]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Order sync failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'Failed to sync orders', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Transfer orders from one table to another
     */
    public function transferTable($fromId, $toId)
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
