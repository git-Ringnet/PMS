<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomClass;
use App\Models\RoomForm;
use App\Models\SalesInvoice;
use App\Models\SystemDateRoll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dòng 512: chuyển phòng phải chuyển cả khoản thanh toán (payments) và hóa đơn thanh toán (sales_invoices)
 * sang phòng mới, giống như bill dịch vụ. Khoản ở master (không gắn phòng) giữ nguyên.
 */
class RoomMovePaymentsRow512Test extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;
    private RoomClass $roomClass;
    private RoomForm $roomForm;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'move512']);
        $this->actingAs($this->user);
        DB::table('booking_statuses')->insertOrIgnore([
            ['id' => Booking::STATUS_RESERVATION, 'name' => 'Reservation'],
            ['id' => Booking::STATUS_CHECKIN, 'name' => 'Checked In'],
            ['id' => Booking::STATUS_CHECKOUT, 'name' => 'Checked Out'],
            ['id' => Booking::STATUS_DELETED, 'name' => 'Deleted'],
            ['id' => Booking::STATUS_NO_SHOW, 'name' => 'No Show'],
            ['id' => Booking::STATUS_TRANSFER, 'name' => 'Moved'],
        ]);
        SystemDateRoll::create(['system_date' => '2026-08-16 00:00:00', 'actual_date' => '2026-08-16 00:00:00', 'shift' => '1', 'username' => 'move512']);
        $this->booking = Booking::create([
            'booking_name' => 'Row 512', 'booking_date' => '2026-08-11', 'arrival_date' => '2026-08-11',
            'departure_date' => '2026-08-18', 'created_by' => 'move512', 'updated_by' => 'move512', 'status' => Booking::STATUS_CHECKIN,
        ]);
        $this->roomClass = RoomClass::create(['code' => 'M512', 'name' => 'Move 512', 'is_active' => true]);
        $this->roomForm = RoomForm::create(['name' => 'Move 512 Form']);
        foreach (['410', '907', '902'] as $number) {
            Room::create(['room_number' => $number, 'room_class_id' => $this->roomClass->id, 'room_form_id' => $this->roomForm->id, 'floor' => 4, 'status' => 'available']);
        }
        $role = Role::firstOrCreate(['code' => 'move512_role'], ['name' => 'Move 512', 'level' => 3, 'department_scope' => 'FO', 'is_active' => true]);
        $permission = Permission::firstOrCreate(['code' => 'fo.room.move'], ['name' => 'fo.room.move', 'module' => 'FO']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function room(string $number, array $guests): BookingRoom
    {
        $room = BookingRoom::create([
            'booking_id' => $this->booking->id, 'room_class_id' => $this->roomClass->id, 'RoomKind' => $this->roomForm->id,
            'room_number' => $number, 'arrival_date' => '2026-08-11', 'departure_date' => '2026-08-19', 'rate' => 1000000,
            'adults' => count($guests), 'status' => BookingRoom::STATUS_CHECKED_IN,
        ]);
        foreach ($guests as $i => $guest) {
            BookingRoomGuest::create(['booking_room_id' => $room->id, 'guest_id' => $guest->id, 'is_primary' => $i === 0, 'status' => BookingRoomGuest::STATUS_CHECKED_IN]);
        }

        return $room;
    }

    private function pay(BookingRoom $room, ?Guest $guest, float $amount): array
    {
        $payment = Payment::create([
            'booking_id' => $this->booking->id, 'booking_room_id' => $room->id, 'guest_id' => $guest?->id, 'date' => '2026-08-16',
            'amount' => $amount, 'folio_id' => 1, 'status' => Payment::STATUS_PAID, 'edit_flag' => 0,
        ]);
        $invoice = SalesInvoice::create([
            'booking_id' => $this->booking->id, 'booking_room_id' => $room->id, 'guest_room_id' => $room->id . ($guest?->id ?? ''),
            'guest_id' => $guest?->id, 'room' => 'R:' . $room->room_number, 'invoice_date' => '2026-08-16 10:00:00', 'amount' => $amount,
            'status' => 1, 'payment_code' => (string) $payment->id,
        ]);

        return [$payment, $invoice];
    }

    private function move(BookingRoom $room, array $body): void
    {
        $this->postJson("/api/bookings/{$this->booking->id}/rooms/{$room->id}/move", $body + ['reason' => 'test 512', 'is_change_rate' => false])
            ->assertSuccessful()->assertJsonPath('success', true);
    }

    public function test_full_move_carries_payments_and_sales_invoices_to_the_new_room(): void
    {
        $guest = Guest::create(['full_name' => 'Khach 512']);
        $source = $this->room('907', [$guest]);
        [$payment, $invoice] = $this->pay($source, $guest, 345000);
        [$masterPayment] = [Payment::create([
            'booking_id' => $this->booking->id, 'booking_room_id' => null, 'date' => '2026-08-16', 'amount' => 900000, 'folio_id' => 3, 'status' => Payment::STATUS_PAID, 'edit_flag' => 0,
        ])];

        $this->move($source, ['move_type' => 'available', 'target_room_number' => '410']);

        $target = BookingRoom::where('booking_id', $this->booking->id)->where('room_number', '410')->firstOrFail();
        $this->assertSame((string) $target->id, (string) $payment->fresh()->booking_room_id);
        $this->assertSame((string) $target->id, (string) $invoice->fresh()->booking_room_id);
        $this->assertSame($target->id . $guest->id, $invoice->fresh()->guest_room_id);
        $this->assertSame('R:410', $invoice->fresh()->room);
        $this->assertNull($masterPayment->fresh()->booking_room_id, 'Thanh toán ở master phải giữ nguyên');
    }

    public function test_partial_move_only_carries_payments_of_the_moved_guest(): void
    {
        $moved = Guest::create(['full_name' => 'Khach chuyen']);
        $stay = Guest::create(['full_name' => 'Khach o lai']);
        $source = $this->room('907', [$stay, $moved]);
        [$movedPayment, $movedInvoice] = $this->pay($source, $moved, 100000);
        [$stayPayment, $stayInvoice] = $this->pay($source, $stay, 200000);

        $this->move($source, ['move_type' => 'available', 'target_room_number' => '410', 'selected_guest_ids' => [$moved->id]]);

        $target = BookingRoom::where('booking_id', $this->booking->id)->where('room_number', '410')->firstOrFail();
        $this->assertSame((string) $target->id, (string) $movedPayment->fresh()->booking_room_id);
        $this->assertSame((string) $target->id, (string) $movedInvoice->fresh()->booking_room_id);
        $this->assertSame((string) $source->id, (string) $stayPayment->fresh()->booking_room_id);
        $this->assertSame((string) $source->id, (string) $stayInvoice->fresh()->booking_room_id);
    }

    public function test_merge_move_carries_payments_to_the_target_room(): void
    {
        $guestA = Guest::create(['full_name' => 'Khach A']);
        $guestB = Guest::create(['full_name' => 'Khach B']);
        $source = $this->room('907', [$guestA]);
        $target = $this->room('902', [$guestB]);
        [$payment, $invoice] = $this->pay($source, $guestA, 500000);
        [$targetPayment] = $this->pay($target, $guestB, 700000);

        $this->move($source, ['move_type' => 'merge', 'target_room_number' => '902']);

        $this->assertSame((string) $target->id, (string) $payment->fresh()->booking_room_id);
        $this->assertSame((string) $target->id, (string) $invoice->fresh()->booking_room_id);
        $this->assertSame((string) $target->id, (string) $targetPayment->fresh()->booking_room_id);
    }
}
