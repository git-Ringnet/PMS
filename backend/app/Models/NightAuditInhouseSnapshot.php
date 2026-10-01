<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditInhouseSnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_inhouse_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'rental_room_id',
        'customer_id',
        'status',
        'checkout_date',
        'checkout_time',
        'position_id',
        'user_checkin',
        'user_checkout',
        'breakfast',
        'position',
        'guest',
        'note',
        'country',
        'nationality',
        'nationality_name',
        'passport',
        'birthday',
        'guest_name',
        'address',
        'phone',
        'fax',
        'email',
        'issue_date',
        'visa',
        'visa_date',
        'booking_id',
        'arrival_date',
        'arrival_time',
        'num_of_days',
        'room',
        'adult',
        'child',
        'extra_bed',
        'rate',
        'room_rate_code',
        'breakfast_room',
        'breakfast_child',
        'room_kind',
        'room_type',
        'departure_date',
        'booking_name',
        'contact',
        'company',
        'company_id',
        'group_status',
        'orders',
        'house_use',
        'pack3',
        'guest_type',
        'day_use',
        'baby_cot',
        'gender',
    ];

    protected $casts = [
        'snapshot_date'  => 'date',
        'checkout_date'  => 'datetime',
        'birthday'       => 'date',
        'issue_date'     => 'date',
        'visa_date'      => 'date',
        'arrival_date'   => 'datetime',
        'departure_date' => 'datetime',
        'rate'           => 'decimal:2',
    ];

    /**
     * Danh sách trường nhạy cảm chứa PII cần ẩn nếu người dùng không đủ quyền.
     */
    public const SENSITIVE_PII_FIELDS = [
        'passport',
        'phone',
        'email',
        'fax',
        'address',
        'visa',
        'visa_date',
        'issue_date',
        'birthday',
    ];

    /**
     * Format snapshot với bộ lọc PII cho API.
     */
    public function toSafeArray(bool $canViewPii = false): array
    {
        $data = $this->toArray();
        if (!$canViewPii) {
            foreach (self::SENSITIVE_PII_FIELDS as $field) {
                if (isset($data[$field])) {
                    $data[$field] = '***';
                }
            }
        }
        return $data;
    }
}
