<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class SecurityTimeWindow extends Model
{
    use UsesSystemConnection;

    protected $table = 'security_time_windows';

    protected $fillable = ['days', 'start_time', 'end_time'];

    /** @return int[] Ngày trong tuần được phép (1 = Thứ hai ... 7 = Chủ nhật) */
    public function dayNumbers(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->days))));
    }
}
