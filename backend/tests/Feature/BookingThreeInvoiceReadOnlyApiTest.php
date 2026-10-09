<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingThreeInvoiceReadOnlyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_invoice_viewer_cannot_write_using_shared_endpoints_or_forged_frontdesk_flags(): void
    {
        $viewer = User::factory()->create();
        $role = Role::create(['code' => 'booking3_sale_viewer', 'name' => 'Sale invoice viewer', 'level' => 3, 'department_scope' => 'SALE', 'is_active' => true]);
        foreach (['fo.booking.edit', 'fo.service.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $viewer->roles()->attach($role->id);
        $this->actingAs($viewer);

        $tables = ['service_bills', 'booking_room_services', 'payments', 'booking_rooms', 'room_do_not_move_locks'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $requests = [
            ['POST', '/api/booking-rooms/G0000001/services'],
            ['DELETE', '/api/booking-rooms/G0000001/services/bulk'],
            ['POST', '/api/booking-rooms/G0000001/services/cancel'],
            ['PATCH', '/api/booking-rooms/G0000001/services/folio'],
            ['POST', '/api/booking-rooms/G0000001/services/quick-transfer'],
            ['POST', '/api/booking-rooms/G0000001/services/split-folio'],
            ['PATCH', '/api/service-bills/1/description'],
            ['POST', '/api/booking-room-services/post-fo-service-bill'],
            ['POST', '/api/booking-room-services/post-room-charge'],
            ['POST', '/api/booking-room-services/post-housekeeping-bill'],
            ['POST', '/api/housekeeping/service-bills/1/cancel'],
            ['POST', '/api/bookings/D0000001/payments'],
            ['POST', '/api/bookings/D0000001/settle-payment'],
            ['PUT', '/api/payments/1'],
            ['DELETE', '/api/payments/1'],
            ['PATCH', '/api/payments/1/folio'],
            ['POST', '/api/payments/1/split'],
            ['PATCH', '/api/bookings/D0000001/no-post'],
            ['PATCH', '/api/booking-rooms/G0000001/no-post'],
            ['POST', '/api/bookings/D0000001/checkout'],
            ['POST', '/api/bookings/D0000001/restore-checkout'],
            ['POST', '/api/booking-rooms/G0000001/checkout'],
            ['POST', '/api/booking-rooms/G0000001/restore-checkout'],
            ['POST', '/api/booking-rooms/G0000001/guests/K000000001/checkout'],
        ];
        foreach ($requests as [$method, $url]) {
            $response = $this->json($method, $url . '?module=fo&readOnly=false', [
                'current_module' => 'FO', 'module' => 'FO', 'readOnly' => false,
                'booking_id' => 'D0000001', 'booking_room_id' => 'G0000001',
                'service_code' => 'RM', 'rate' => 1000000, 'quantity' => 1,
                'date' => '2026-08-16', 'amount' => 1000000, 'no_post' => true,
            ]);
            $this->assertSame(403, $response->status(), $method . ' ' . $url . ': ' . $response->getContent());
            $after = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
            $this->assertSame($before, $after, $method . ' ' . $url . ' must not change invoice state');
        }
    }
}
