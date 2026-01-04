<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();
    
    // === MENGGUNAKAN PARAMETER MODE ===
    if (isset($_GET['mode'])) {
        $mode = $_GET['mode'];
        
        switch ($mode) {
            case 'input_status':
                if (!isset($_GET['status'])) {
                    json_out(['ok' => false, 'error' => 'Parameter status required (ON/OFF)'], 400);
                }
                
                $status = strtoupper($_GET['status']);
                $status_bit = ($status == 'ON') ? 1 : 0;
                
                $pdo->beginTransaction();
                
                // 1. Status Monitoring (No 2)
                $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, status_bit, recorded_at) VALUES('STATUS_MON', ?, ?)");
                $stmt->execute([$status_bit, now()]);
                
                // 2. Status Historis (No 1)
                $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, status_bit, recorded_at) VALUES('STATUS_HIST', ?, ?)");
                $stmt->execute([$status_bit, now()]);
                
                // 3. Kontrol Mesin (No 5)
                $mode_bit = isset($_GET['mode_bit']) ? (int)$_GET['mode_bit'] : 1;
                $stmt = $pdo->prepare("INSERT INTO machine_control(status_bit, mode_bit, recorded_at) VALUES(?, ?, ?)");
                $stmt->execute([$status_bit, $mode_bit, now()]);
                
                if ($status == 'ON') {
                    // 4. Runtime (No 3)
                    $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 5;
                    $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, value_int, recorded_at) VALUES('RUNTIME', ?, ?)");
                    $stmt->execute([$duration, now()]);
                    
                    // 5. Produksi dengan Quality (No 6 & 7)
                    $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
                    $ok_bit = isset($_GET['ok']) ? (int)$_GET['ok'] : 1;
                    
                    if ($qty > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO production_quality_log(qty, ok_bit, tread_type, recorded_at) 
                            VALUES(?, ?, 'TREAD', ?)
                        ");
                        $stmt->execute([$qty, $ok_bit, now()]);
                    }
                    
                    $inserted = [
                        'status' => 'ON',
                        'runtime_seconds' => $duration,
                        'production_qty' => $qty,
                        'quality' => $ok_bit ? 'OK' : 'NG'
                    ];
                } else {
                    // 6. Downtime (No 4)
                    $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 10;
                    $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, value_int, recorded_at) VALUES('DOWNTIME', ?, ?)");
                    $stmt->execute([$duration, now()]);
                    
                    $inserted = [
                        'status' => 'OFF',
                        'downtime_seconds' => $duration
                    ];
                }
                
                $pdo->commit();
                
                json_out([
                    'ok' => true,
                    'message' => 'Semua metrik berhasil direkam',
                    'inserted' => $inserted,
                    'metrics_recorded' => $status == 'ON' ? [1, 2, 3, 5, 6, 7] : [1, 2, 4, 5],
                    'timestamp' => now()
                ]);
                break;
                
            case 'input_xray_checked':
                if (!isset($_GET['result'])) {
                    json_out(['ok' => false, 'error' => 'Parameter result required (OK/NG)'], 400);
                }
                
                $result = strtoupper($_GET['result']);
                $ok_bit = ($result == 'OK') ? 1 : 0;
                $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
                
                $stmt = $pdo->prepare("
                    INSERT INTO production_quality_log(qty, ok_bit, tread_type, recorded_at) 
                    VALUES(?, ?, 'TREAD', ?)
                ");
                $stmt->execute([$qty, $ok_bit, now()]);
                
                $metric = get_metric_info('QUALITY');
                
                json_out([
                    'ok' => true,
                    'metric' => $metric['name'],
                    'metric_no' => $metric['no'],
                    'result' => $result,
                    'quantity' => $qty,
                    'timestamp' => now()
                ]);
                break;
                
            default:
                json_out(['ok' => false, 'error' => 'Mode tidak valid'], 400);
        }
    } 
    
    // === LEGACY MODE ===
    else {
        $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
        $mode_bit = isset($_GET['mode']) ? (int)$_GET['mode'] : 1;
        $sec = isset($_GET['sec']) ? (int)$_GET['sec'] : 5;
        $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 1;

        $status = $status ? 1 : 0;
        $mode_bit = $mode_bit ? 1 : 0;
        $ok = $ok ? 1 : 0;
        $sec = max(1, min(3600, $sec));
        $qty = max(0, min(1000, $qty));

        $pdo->beginTransaction();

        // 1. Kontrol (No 5)
        $stmt = $pdo->prepare("INSERT INTO machine_control(status_bit, mode_bit, recorded_at) VALUES(?,?,?)");
        $stmt->execute([$status, $mode_bit, now()]);

        // 2. Status Monitoring (No 2) dan Historis (No 1)
        $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, status_bit, recorded_at) VALUES('STATUS_MON', ?, ?)");
        $stmt->execute([$status, now()]);
        
        $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, status_bit, recorded_at) VALUES('STATUS_HIST', ?, ?)");
        $stmt->execute([$status, now()]);

        if ($status === 1) {
            // 3. Runtime (No 3)
            $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, value_int, recorded_at) VALUES('RUNTIME', ?, ?)");
            $stmt->execute([$sec, now()]);

            // 4. Produksi & Quality (No 6 & 7)
            if ($qty > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO production_quality_log(qty, ok_bit, tread_type, recorded_at) 
                    VALUES(?, ?, 'TREAD', ?)
                ");
                $stmt->execute([$qty, $ok, now()]);
            }
        } else {
            // 5. Downtime (No 4)
            $stmt = $pdo->prepare("INSERT INTO machine_events(metric_code, value_int, recorded_at) VALUES('DOWNTIME', ?, ?)");
            $stmt->execute([$sec, now()]);
        }

        $pdo->commit();

        json_out([
            'ok' => true,
            'inserted' => [
                'status_bit' => $status,
                'mode_bit' => $mode_bit,
                'sec' => $sec,
                'qty' => $qty,
                'ok_bit' => $ok,
            ],
            'metrics_mapped' => [
                'status_historis' => 'No 1',
                'status_monitoring' => 'No 2',
                'control' => 'No 5',
                'runtime_downtime' => $status ? 'No 3' : 'No 4',
                'production_quality' => $status ? 'No 6 & 7' : null
            ],
            'database_tables' => 3
        ]);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}