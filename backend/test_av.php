<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$s = new App\Services\RoomAvailabilityService();
$rc = App\Models\RoomClass::first();
if ($rc) {
    echo "RoomClass: " . $rc->name . PHP_EOL;
    echo "TotalRooms: " . $s->getTotalRooms($rc->id) . PHP_EOL;
    echo "Locked: " . $s->getLockedCount($rc->id, '2026-07-07', '2026-07-09') . PHP_EOL;
    echo "Booked: " . $s->getBookedCount($rc->id, '2026-07-07', '2026-07-09') . PHP_EOL;
} else {
    echo "No room class found" . PHP_EOL;
}
