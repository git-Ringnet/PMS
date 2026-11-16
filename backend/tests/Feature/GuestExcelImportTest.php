<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** TC_LT_27 (dòng 369): nhập thông tin khách bằng Excel. */
class GuestExcelImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'BookingStatusSeeder']);
    }

    /** File CSV (UTF-8 có BOM, như Excel lưu) từ các dòng. */
    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'guest') . '.csv';
        $out = fopen($path, 'w');
        fwrite($out, "ï»¿");
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        return new UploadedFile($path, 'khach.csv', null, null, true);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'booking_name' => 'IMPORT', 'arrival_date' => '2026-09-10', 'departure_date' => '2026-09-12',
            'num_of_days' => 2, 'booking_date' => '2026-09-10', 'created_by' => 'x',
        ]);
    }

    public function test_import_preview_maps_columns_and_normalizes_dates(): void
    {
        $this->actingAs(User::factory()->withRole('super_admin')->create());
        $booking = $this->booking();

        $file = $this->csv([
            ['Số Phòng', 'Danh Xưng', 'Họ Và Tên', 'Ngày Sinh', 'Số Giấy Tờ', 'Điện thoại'],
            ['101', 'Mr.', 'NGUYEN VAN A', '15/05/1990', '012345678901', '0912345678'],
            ['', '', '', '', '', ''],
            ['102', 'Mrs.', 'TRAN THI B', '1992-01-02', '098765', ''],
            ['103', 'Mr.', '', '', '999', ''],
        ]);

        $response = $this->postJson("/api/bookings/{$booking->id}/guests/import-preview", ['file' => $file])->assertOk();

        $this->assertSame(1, $response->json('skipped'));
        $this->assertSame([
            ['room_number' => '101', 'title' => 'Mr.', 'full_name' => 'NGUYEN VAN A', 'dob' => '1990-05-15', 'id_number' => '012345678901', 'phone' => '0912345678'],
            ['room_number' => '102', 'title' => 'Mrs.', 'full_name' => 'TRAN THI B', 'dob' => '1992-01-02', 'id_number' => '098765'],
        ], $response->json('data'));
    }

    public function test_import_rejects_file_without_name_column(): void
    {
        $this->actingAs(User::factory()->withRole('super_admin')->create());
        $booking = $this->booking();

        $this->postJson("/api/bookings/{$booking->id}/guests/import-preview", ['file' => $this->csv([['A', 'B'], ['1', '2']])])
            ->assertStatus(422);
    }

    public function test_import_reads_the_html_xls_file_exported_by_the_guest_screen(): void
    {
        $this->actingAs(User::factory()->withRole('super_admin')->create());
        $booking = $this->booking();
        $path = tempnam(sys_get_temp_dir(), 'guest') . '.xls';
        file_put_contents($path, '<meta charset="utf-8"><table><tr><th>Số Phòng</th><th>Họ Và Tên</th><th>Ngày Sinh</th></tr><tr><td>205</td><td>LE VAN C</td><td>03/04/1985</td></tr></table>');

        $response = $this->postJson("/api/bookings/{$booking->id}/guests/import-preview", ['file' => new UploadedFile($path, 'export.xls', null, null, true)])->assertOk();

        $this->assertSame([['room_number' => '205', 'full_name' => 'LE VAN C', 'dob' => '1985-04-03']], $response->json('data'));
    }

    public function test_template_download_has_the_same_headers_as_export(): void
    {
        $this->actingAs(User::factory()->withRole('super_admin')->create());

        $this->get('/api/guests/import-template')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="mau_nhap_khach.csv"');
    }

    public function test_guest_and_child_dates_are_serialized_without_timezone_shift(): void
    {
        $guest = \App\Models\Guest::create(['full_name' => 'DOB TEST', 'dob' => '1990-05-15']);
        $this->assertSame('1990-05-15', $guest->fresh()->toArray()['dob']);

        $child = new \App\Models\BookingChild(['dob' => '2020-01-02']);
        $this->assertSame('2020-01-02', $child->toArray()['dob']);
    }

    public function test_saving_the_guest_table_without_changes_writes_no_generic_log(): void
    {
        $this->actingAs(User::factory()->withRole('super_admin')->create());
        $booking = $this->booking();
        $before = \App\Models\ActivityLog::count();

        $this->postJson("/api/bookings/{$booking->id}/bulk-update-guests", ['guests' => [], 'children' => []])->assertSuccessful();

        $this->assertSame($before, \App\Models\ActivityLog::count());
    }
}
