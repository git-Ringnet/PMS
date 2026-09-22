<?php
$sqlDir = 'C:/Users/Nguyen Tho Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql';
if (!is_dir($sqlDir)) {
    mkdir($sqlDir, 0777, true);
}

$databases = ['ProVistaNavyHotel', 'ProVistaDTXHotel', 'ProVistaArmyHotel'];
$procs = ['sp_076', 'sp_279', 'sp_078', 'sp_078_Division', 'sp1322', 'sp1500'];

foreach ($databases as $db) {
    echo "=== Database: $db ===\n";
    foreach ($procs as $p) {
        $outFile = "$sqlDir/{$db}_{$p}.sql";
        $cmd = "sqlcmd -S .\\MSSQLSERVER01 -E -C -d $db -Q \"EXEC sp_helptext '$p'\" -h -1 -w 8000 -W";
        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            if ($output && stripos($output, 'CREATE') !== false) {
                file_put_contents($outFile, $output);
                echo "  [OK] Saved $p from $db (" . strlen($output) . " bytes) -> {$db}_{$p}.sql\n";
            } else {
                echo "  [--] Not found: $p in $db\n";
            }
        }
    }
}
