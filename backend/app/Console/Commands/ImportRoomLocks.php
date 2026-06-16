<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;
use App\Models\RoomLock;

class ImportRoomLocks extends Command
{
    protected $signature = 'import:room-locks';
    protected $description = 'Import dữ liệu phòng khóa từ file CSV xuất từ SQL Server';

    public function handle()
    {
        $this->info('Bắt đầu import phòng khóa từ CSV...');

        // 1. IMPORT PHÒNG HỎNG (OOO) TỪ FILE SP4001.csv
        $oooPath = storage_path('app/imports/SP4001.csv');
        if (file_exists($oooPath)) {
            $this->info('Đang đọc file SP4001.csv (OOO)...');
            $file = fopen($oooPath, 'r');
            $count = 0;

            // Duyệt từng dòng dữ liệu (Không có dòng tiêu đề header)
            while (($row = fgetcsv($file)) !== FALSE) {
                // Kiểm tra xem dòng có đầy đủ dữ liệu tối thiểu hay không
                if (count($row) < 5) {
                    continue;
                }

                $roomCode = trim($row[1]);
                $fromDate = !empty($row[2]) ? substr(trim($row[2]), 0, 10) : null;
                $toDate   = !empty($row[3]) ? substr(trim($row[3]), 0, 10) : null;
                $reason   = !empty($row[4]) ? trim($row[4]) : 'Phòng hỏng';
                $username = !empty($row[5]) ? trim($row[5]) : 'NB0016';

                $room = Room::where('room_number', $roomCode)->first();
                if ($room) {
                    RoomLock::create([
                        'room_id'    => $room->id,
                        'start_date' => $fromDate,
                        'end_date'   => $toDate,
                        'reason'     => $reason,
                        'lock_type'  => 'OOO',
                        'status'     => 'New',
                        'username'   => $username,
                        'is_active'  => true,
                    ]);
                    $count++;
                }
            }
            fclose($file);
            $this->info("Đã import {$count} phòng hỏng (OOO) thành công!");
        } else {
            $this->error("Không tìm thấy file OOO: {$oooPath}");
        }

        // 2. IMPORT PHÒNG KHÓA TẠM (OOS) TỪ FILE SP4002.csv
        $oosPath = storage_path('app/imports/SP4002.csv');
        if (file_exists($oosPath)) {
            $this->info('Đang đọc file SP4002.csv (OOS)...');
            $file = fopen($oosPath, 'r');
            $count = 0;

            // Duyệt từng dòng dữ liệu (Không có dòng tiêu đề header)
            while (($row = fgetcsv($file)) !== FALSE) {
                if (count($row) < 8) {
                    continue;
                }

                $roomCode = trim($row[1]);
                $fromDate = !empty($row[2]) ? substr(trim($row[2]), 0, 10) : null;
                $toDate   = !empty($row[4]) ? substr(trim($row[4]), 0, 10) : null;
                $reason   = !empty($row[6]) ? trim($row[6]) : 'Khóa tạm';
                $username = !empty($row[7]) ? trim($row[7]) : 'NB0016';

                $room = Room::where('room_number', $roomCode)->first();
                if ($room) {
                    RoomLock::create([
                        'room_id'    => $room->id,
                        'start_date' => $fromDate,
                        'end_date'   => $toDate,
                        'reason'     => $reason,
                        'lock_type'  => 'OOS',
                        'status'     => 'New',
                        'username'   => $username,
                        'is_active'  => true,
                    ]);
                    $count++;
                }
            }
            fclose($file);
            $this->info("Đã import {$count} phòng khóa tạm (OOS) thành công!");
        } else {
            $this->error("Không tìm thấy file OOS: {$oosPath}");
        }

        $this->info('Đã hoàn thành import phòng khóa!');
    }
}
