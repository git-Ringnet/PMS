<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\Laravel\Reverb\ApplicationManagerServiceProvider::class);
        $this->app->register(\Laravel\Reverb\ReverbServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || true) {
            URL::forceScheme('https');
        }
        // Đăng ký Event Listener toàn cục để tự động bắt các thay đổi dữ liệu của Eloquent
        \Illuminate\Support\Facades\Event::listen('eloquent.*', function ($eventName, array $data) {
            static $dispatchedReservations = [];
            static $dispatchedRooms = [];
            static $roomNumberToIdMap = [];

            if (!str_contains($eventName, 'eloquent.created:') && 
                !str_contains($eventName, 'eloquent.updated:') && 
                !str_contains($eventName, 'eloquent.deleted:')) {
                return;
            }

            $model = $data[0] ?? null;
            if (!$model || !($model instanceof \Illuminate\Database\Eloquent\Model)) {
                return;
            }

            // Bỏ qua ActivityLog để tránh lặp vô hạn
            if ($model instanceof \App\Models\ActivityLog) {
                return;
            }

            $action = '';
            if (str_contains($eventName, '.created:')) {
                $action = 'created';
            } elseif (str_contains($eventName, '.updated:')) {
                $action = 'updated';
            } elseif (str_contains($eventName, '.deleted:')) {
                $action = 'deleted';
            }

            $targetId = $model->getKey();
            $targetType = class_basename($model);

            // Phát Broadcast Event Realtime cho các model quan trọng
            try {
                if (in_array($targetType, ['Room', 'RoomLock', 'BookingRoom', 'Booking', 'BookingRoomService', 'Payment', 'BookingRoomGuest', 'Guest'])) {
                    if ($targetType === 'Room') {
                        if (!in_array($targetId, $dispatchedRooms)) {
                            $dispatchedRooms[] = $targetId;
                            event(new \App\Events\RoomStatusUpdated($targetId, $model->status ?? null, "Room {$targetId} updated"));
                        }
                    } elseif ($targetType === 'RoomLock') {
                        $roomNumber = $model->room_number;
                        $roomId = null;
                        if ($roomNumber) {
                            if (isset($roomNumberToIdMap[$roomNumber])) {
                                $roomId = $roomNumberToIdMap[$roomNumber];
                            } else {
                                $room = \App\Models\Room::where('room_number', $roomNumber)->first();
                                $roomId = $room ? $room->id : null;
                                if ($roomId) {
                                    $roomNumberToIdMap[$roomNumber] = $roomId;
                                }
                            }
                        }
                        if ($roomId && !in_array($roomId, $dispatchedRooms)) {
                            $dispatchedRooms[] = $roomId;
                            event(new \App\Events\RoomStatusUpdated($roomId, null, "Room {$model->room_number} lock changed"));
                        }
                    } elseif ($targetType === 'BookingRoom') {
                        $roomNumber = $model->room_number;
                        $roomId = null;
                        if ($roomNumber) {
                            if (isset($roomNumberToIdMap[$roomNumber])) {
                                $roomId = $roomNumberToIdMap[$roomNumber];
                            } else {
                                $room = \App\Models\Room::where('room_number', $roomNumber)->first();
                                $roomId = $room ? $room->id : null;
                                if ($roomId) {
                                    $roomNumberToIdMap[$roomNumber] = $roomId;
                                }
                            }
                        }
                        if ($roomId && !in_array($roomId, $dispatchedRooms)) {
                            $dispatchedRooms[] = $roomId;
                            event(new \App\Events\RoomStatusUpdated($roomId, null, "Booking room changed"));
                        }
                        
                        $bookingId = $model->booking_id;
                        if ($bookingId && !in_array($bookingId, $dispatchedReservations)) {
                            $dispatchedReservations[] = $bookingId;
                            event(new \App\Events\ReservationUpdated($bookingId, $action, "Booking room {$action}"));
                        }
                    } elseif ($targetType === 'Booking') {
                        if (!in_array($targetId, $dispatchedReservations)) {
                            $dispatchedReservations[] = $targetId;
                            event(new \App\Events\ReservationUpdated($targetId, $action, "Booking {$action}"));
                        }
                    } elseif ($targetType === 'BookingRoomService') {
                        $bookingRoom = $model->bookingRoom;
                        if ($bookingRoom && $bookingRoom->booking_id) {
                            $bookingId = $bookingRoom->booking_id;
                            if (!in_array($bookingId, $dispatchedReservations)) {
                                $dispatchedReservations[] = $bookingId;
                                event(new \App\Events\ReservationUpdated($bookingId, 'updated', "Service {$action}"));
                            }
                        }
                    } elseif ($targetType === 'Payment') {
                        $bookingId = $model->booking_id;
                        if ($bookingId && !in_array($bookingId, $dispatchedReservations)) {
                            $dispatchedReservations[] = $bookingId;
                            event(new \App\Events\ReservationUpdated($bookingId, $action, "Payment {$action}"));
                        }
                    } elseif ($targetType === 'BookingRoomGuest') {
                        if ($model->booking_room_id) {
                            $bookingRoom = \App\Models\BookingRoom::find($model->booking_room_id);
                            if ($bookingRoom && $bookingRoom->booking_id) {
                                $bookingId = $bookingRoom->booking_id;
                                if (!in_array($bookingId, $dispatchedReservations)) {
                                    $dispatchedReservations[] = $bookingId;
                                    event(new \App\Events\ReservationUpdated($bookingId, $action, "Guest pivot {$action}"));
                                }
                            }
                        }
                    } elseif ($targetType === 'Guest') {
                        $bookingRoomIds = \App\Models\BookingRoomGuest::where('guest_id', $model->id)->pluck('booking_room_id');
                        $bookingIds = \App\Models\BookingRoom::whereIn('id', $bookingRoomIds)->pluck('booking_id')->unique();
                        foreach ($bookingIds as $bId) {
                            if (!in_array($bId, $dispatchedReservations)) {
                                $dispatchedReservations[] = $bId;
                                event(new \App\Events\ReservationUpdated($bId, $action, "Guest info {$action}"));
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Realtime broadcast error: " . $e->getMessage());
            }

            // Kiểm tra chạy trong console cho Activity Log (CLI không cần ghi Activity Log)
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();
            if (!$request) {
                return;
            }
            
            // Tìm nhãn đại diện cho Model
            $targetLabel = null;
            foreach (['name', 'title', 'code', 'username', 'label', 'display_name', 'room_code', 'registration_code'] as $field) {
                if (isset($model->{$field})) {
                    $targetLabel = $model->{$field};
                    break;
                }
            }

            $oldValues = null;
            $newValues = null;
            $skipFields = ['updated_at', 'created_at', 'remember_token', 'password'];

            if ($action === 'created') {
                $newValues = [];
                foreach ($model->getAttributes() as $key => $val) {
                    if (in_array($key, $skipFields)) continue;
                    $newValues[$key] = $val;
                }
            } elseif ($action === 'updated') {
                $changes = $model->getChanges();
                $original = $model->getOriginal();
                $oldValues = [];
                $newValues = [];
                foreach ($changes as $key => $newVal) {
                    if (in_array($key, $skipFields)) continue;
                    $oldVal = $original[$key] ?? null;
                    $isDifferent = (is_array($oldVal) || is_array($newVal))
                        ? (json_encode($oldVal) !== json_encode($newVal))
                        : ((string)$oldVal !== (string)$newVal);

                    if ($isDifferent) {
                        $oldValues[$key] = $oldVal;
                        $newValues[$key] = $newVal;
                    }
                }
                if (empty($newValues)) {
                    return;
                }
            } elseif ($action === 'deleted') {
                $oldValues = [];
                foreach ($model->getOriginal() as $key => $val) {
                    if (in_array($key, $skipFields)) continue;
                    $oldValues[$key] = $val;
                }
            }

            $request->attributes->set('_last_model_change', [
                'target_id' => $targetId,
                'target_type' => $targetType,
                'target_label' => $targetLabel,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);
        });
    }
}
