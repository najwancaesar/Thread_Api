<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();
    $machineId = request_machine_id();

    // === NEW ENDPOINTS FROM IMAGE ===

    // 1. control_baca (baca dari mesin untuk kontrol)
    if (isset($_GET['mode']) && $_GET['mode'] == 'control_baca') {
        [$controlWhere, $controlParams] = build_machine_filter($pdo, 'machine_control', $machineId);
        $stmt = $pdo->prepare("
            SELECT status_bit, mode_bit, recorded_at 
            FROM machine_control
            WHERE 1=1{$controlWhere}
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute($controlParams);
        $control = $stmt->fetch();

        $response = [
            'ok' => true,
            'mode' => 'control_baca',
            'control_status' => $control ? ((int)$control['status_bit'] == 1 ? 'ON' : 'OFF') : 'OFF',
            'control_mode' => $control ? ((int)$control['mode_bit'] == 1 ? 'AUTO' : 'MANUAL') : 'AUTO',
            'last_update' => $control ? $control['recorded_at'] : null
        ];

        json_out($response);
    }

    // 2. control_update (kirim dari web untuk kontrol)
    elseif (isset($_GET['mode']) && $_GET['mode'] == 'control_update' && isset($_GET['status'])) {
        $status = strtoupper($_GET['status']);
        $status_bit = ($status == 'ON') ? 1 : 0;
        $mode_bit = isset($_GET['mode_bit']) ? (int)$_GET['mode_bit'] : 1;

        insert_row_schema_aware($pdo, 'machine_control', [
            'status_bit' => $status_bit,
            'mode_bit' => $mode_bit,
            'machine_id' => $machineId,
            'recorded_at' => now(),
        ]);

        // Also record as machine event
        $stmt = $pdo->prepare("
            INSERT INTO machine_events(metric_code, status_bit, machine_id, recorded_at) 
            VALUES('STATUS_MON', ?, ?, ?)
        ");
        $stmt->execute([$status_bit, $machineId, now()]);

        // Record historical status too (needed for runtime/downtime calculations)
        $stmt = $pdo->prepare("
            INSERT INTO machine_events(metric_code, status_bit, machine_id, recorded_at) 
            VALUES('STATUS_HIST', ?, ?, ?)
        ");
        $stmt->execute([$status_bit, $machineId, now()]);

        json_out([
            'ok' => true,
            'mode' => 'control_update',
            'message' => 'Machine control updated successfully',
            'status' => $status,
            'mode_setting' => $mode_bit == 1 ? 'AUTO' : 'MANUAL',
            'timestamp' => now()
        ]);
    }

    // 3. input_tread_checked (X-Ray quality check)
    elseif (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'input_tread_checked' && isset($_REQUEST['result'])) {
        $result = strtoupper((string)$_REQUEST['result']);
        $ok_bit = ($result == 'OK') ? 1 : 0;
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1;
        $defect_type = isset($_REQUEST['defect']) ? (string)$_REQUEST['defect'] : 'NONE';

        $tread_type = isset($_REQUEST['tread_type']) ? strtoupper((string)$_REQUEST['tread_type']) : 'TREAD';
        if (!in_array($tread_type, ['TREAD', 'SIDEWALL'], true)) $tread_type = 'TREAD';

        $batch_no = isset($_REQUEST['batch_no']) ? trim((string)$_REQUEST['batch_no']) : '';
        $shift = isset($_REQUEST['shift']) ? strtoupper((string)$_REQUEST['shift']) : '';
        $operator = isset($_REQUEST['operator']) ? trim((string)$_REQUEST['operator']) : '';
        $notes = isset($_REQUEST['notes']) ? trim((string)$_REQUEST['notes']) : '';
        $defect_severity = isset($_REQUEST['defect_severity']) ? strtoupper((string)$_REQUEST['defect_severity']) : null;

        // Avoid (int)'' => 0 which breaks OK submissions
        $dimension_ok = null;
        if (array_key_exists('dimension_ok', $_REQUEST) && $_REQUEST['dimension_ok'] !== '') {
            $dimension_ok = (int)$_REQUEST['dimension_ok'];
        }
        $temperature_ok = null;
        if (array_key_exists('temperature_ok', $_REQUEST) && $_REQUEST['temperature_ok'] !== '') {
            $temperature_ok = (int)$_REQUEST['temperature_ok'];
        }

        $qty = max(1, min(1000, $qty));
        $defect_type = $defect_type !== '' ? $defect_type : 'NONE';

        // For OK results, force checks to OK and clear defect fields
        if ($ok_bit === 1) {
            $defect_type = 'NONE';
            $defect_severity = null;
            $dimension_ok = 1;
            $temperature_ok = 1;
        }

        $insertId = insert_row_schema_aware($pdo, 'production_quality_log', [
            'batch_no' => $batch_no !== '' ? $batch_no : null,
            'qty' => $qty,
            'ok_bit' => $ok_bit,
            'tread_type' => $tread_type,
            'machine_id' => $machineId,
            'defect_type' => $defect_type,
            'defect_severity' => $defect_severity,
            'shift' => $shift !== '' ? $shift : null,
            'operator' => $operator !== '' ? $operator : null,
            'notes' => $notes !== '' ? $notes : null,
            'dimension_ok' => $dimension_ok,
            'temperature_ok' => $temperature_ok,
            'recorded_at' => now(),
        ]);

        // If OK, also add to production count
        if ($result == 'OK') {
            $stmt = $pdo->prepare("
                INSERT INTO machine_events(metric_code, value_int, machine_id, description, recorded_at) 
                VALUES('PRODUCTION', ?, ?, 'Tread production from X-Ray check', ?)
            ");
            $stmt->execute([$qty, $machineId, now()]);
        }

        json_out([
            'ok' => true,
            'mode' => 'input_tread_checked',
            'message' => 'Tread quality check recorded',
            'result' => $result,
            'quantity' => $qty,
            'tread_type' => $tread_type,
            'batch_no' => $batch_no !== '' ? $batch_no : null,
            'operator' => $operator !== '' ? $operator : null,
            'notes' => $notes !== '' ? $notes : null,
            'defect_type' => $defect_type,
            'insert_id' => $insertId,
            'timestamp' => now()
        ]);
    }

    // 3b. control_history (latest control rows)
    elseif (isset($_GET['mode']) && $_GET['mode'] === 'control_history') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $limit = max(1, min(50, $limit));

        [$controlWhere, $controlParams] = build_machine_filter($pdo, 'machine_control', $machineId);
        $stmt = $pdo->prepare(
            "SELECT status_bit, mode_bit, recorded_at
             FROM machine_control
             WHERE 1=1{$controlWhere}
             ORDER BY recorded_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute($controlParams);
        $rows = $stmt->fetchAll();

        $items = array_map(function ($r) {
            $status = ((int)($r['status_bit'] ?? 0) === 1) ? 'ON' : 'OFF';
            $mode = ((int)($r['mode_bit'] ?? 1) === 1) ? 'AUTO' : 'MANUAL';
            return [
                'recorded_at' => $r['recorded_at'] ?? null,
                'status' => $status,
                'mode' => $mode,
            ];
        }, $rows);

        json_out([
            'ok' => true,
            'mode' => 'control_history',
            'limit' => $limit,
            'items' => $items,
        ]);
    }

    // 4. input_status (kirim dari mesin pencatatan)
    elseif (isset($_GET['mode']) && $_GET['mode'] == 'input_status' && isset($_GET['status'])) {
        $status = strtoupper($_GET['status']);
        $status_bit = ($status == 'ON') ? 1 : 0;
        $duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 0;
        $duration = max(0, min(86400, $duration));

        $pdo->beginTransaction();

        // Insert to machine_events as STATUS_MON
        $stmt = $pdo->prepare("
            INSERT INTO machine_events(metric_code, status_bit, machine_id, recorded_at) 
            VALUES('STATUS_MON', ?, ?, ?)
        ");
        $stmt->execute([$status_bit, $machineId, now()]);

        // Also insert to STATUS_HIST for historical record
        $stmt = $pdo->prepare("
            INSERT INTO machine_events(metric_code, status_bit, machine_id, recorded_at) 
            VALUES('STATUS_HIST', ?, ?, ?)
        ");
        $stmt->execute([$status_bit, $machineId, now()]);

        if ($status == 'ON') {
            if ($duration > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO machine_events(metric_code, value_int, machine_id, description, recorded_at) 
                    VALUES('RUNTIME', ?, ?, 'Machine runtime from input_status', ?)
                ");
                $stmt->execute([$duration, $machineId, now()]);
            }

            // Record production if quantity provided
            $qty = isset($_GET['qty']) ? (int)$_GET['qty'] : 0;
            if ($qty > 0) {
                insert_row_schema_aware($pdo, 'production_quality_log', [
                    'qty' => $qty,
                    'ok_bit' => 1,
                    'tread_type' => 'TREAD',
                    'machine_id' => $machineId,
                    'recorded_at' => now(),
                ]);

                // Also record as production metric
                $stmt = $pdo->prepare("
                    INSERT INTO machine_events(metric_code, value_int, machine_id, description, recorded_at) 
                    VALUES('PRODUCTION', ?, ?, 'Production from input_status', ?)
                ");
                $stmt->execute([$qty, $machineId, now()]);
            }

            $message = $duration > 0
                ? 'Machine ON status recorded with ' . $duration . ' seconds runtime'
                : 'Machine ON status recorded';
            if ($qty > 0) {
                $message .= ' and ' . $qty . ' pieces production';
            }
        } else {
            if ($duration > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO machine_events(metric_code, value_int, machine_id, description, recorded_at) 
                    VALUES('DOWNTIME', ?, ?, 'Machine downtime from input_status', ?)
                ");
                $stmt->execute([$duration, $machineId, now()]);
            }

            $message = $duration > 0
                ? 'Machine OFF status recorded with ' . $duration . ' seconds downtime'
                : 'Machine OFF status recorded';
        }

        $pdo->commit();

        json_out([
            'ok' => true,
            'mode' => 'input_status',
            'message' => $message,
            'status' => $status,
            'duration' => $duration ?? 0,
            'production_qty' => $qty ?? 0,
            'timestamp' => now()
        ]);
    }

    // === EXISTING DASHBOARD ENDPOINT ===

    else {
        // Dashboard summary endpoint
        // Provide machine status, production summary and availability metrics
        // Period window (hours) for summary
        $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        $hours = max(1, min(168, $hours));

        [$machineWhere, $machineParams] = build_machine_filter($pdo, 'machine_events', $machineId);
        [$qualityWhere, $qualityParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);

        // Machine monitoring status (latest STATUS_MON, fallback STATUS_HIST)
        $stmt = $pdo->prepare("SELECT status_bit, recorded_at FROM machine_events WHERE metric_code = 'STATUS_MON'{$machineWhere} ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute($machineParams);
        $statusRow = $stmt->fetch();
        if (!$statusRow) {
            $stmt = $pdo->prepare("SELECT status_bit, recorded_at FROM machine_events WHERE metric_code = 'STATUS_HIST'{$machineWhere} ORDER BY recorded_at DESC LIMIT 1");
            $stmt->execute($machineParams);
            $statusRow = $stmt->fetch();
        }
        $monitoring_status = 'UNKNOWN';
        $last_update = null;
        if ($statusRow) {
            $monitoring_status = ((int)$statusRow['status_bit'] === 1) ? 'RUNNING' : 'STOPPED';
            $last_update = $statusRow['recorded_at'];
        }

        // Production totals (machine_events.PRODUCTION)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS total_prod FROM machine_events WHERE metric_code = 'PRODUCTION' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
        $stmt->execute(array_merge([$hours], $machineParams));
        $total_prod = (int)$stmt->fetch()['total_prod'];

        // Quality summary from production_quality_log
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) AS ok_qty FROM production_quality_log WHERE ok_bit = 1 AND recorded_at >= (NOW() - INTERVAL ? HOUR){$qualityWhere}");
        $stmt->execute(array_merge([$hours], $qualityParams));
        $ok_qty = (int)$stmt->fetch()['ok_qty'];

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) AS ng_qty FROM production_quality_log WHERE ok_bit = 0 AND recorded_at >= (NOW() - INTERVAL ? HOUR){$qualityWhere}");
        $stmt->execute(array_merge([$hours], $qualityParams));
        $ng_qty = (int)$stmt->fetch()['ng_qty'];

        $total_checked = $ok_qty + $ng_qty;
        $quality_rate = $total_checked > 0 ? round(($ok_qty / $total_checked) * 100, 2) : 0;

        // Runtime and downtime (value_int assumed in seconds)
        $availabilitySource = 'machine_events_sum';
        $avail = compute_availability_seconds_from_status($pdo, $hours, $machineId);
        if (($avail['source'] ?? 'none') !== 'none') {
            $runtime_seconds = (int)$avail['runtime_seconds'];
            $downtime_seconds = (int)$avail['downtime_seconds'];
            $availabilitySource = $avail['source'] ?? 'status_history_diff';
            if (!empty($avail['status_metric_code'])) {
                $availabilitySource .= ':' . $avail['status_metric_code'];
            }
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS runtime_seconds FROM machine_events WHERE metric_code = 'RUNTIME' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
            $stmt->execute(array_merge([$hours], $machineParams));
            $runtime_seconds = (int)$stmt->fetch()['runtime_seconds'];

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS downtime_seconds FROM machine_events WHERE metric_code = 'DOWNTIME' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
            $stmt->execute(array_merge([$hours], $machineParams));
            $downtime_seconds = (int)$stmt->fetch()['downtime_seconds'];
        }

        $runtime_hours = round($runtime_seconds / 3600, 2);
        $downtime_hours = round($downtime_seconds / 3600, 2);

        $response = [
            'ok' => true,
            'machine_id' => $machineId,
            'hours_period' => $hours,
            'dashboard' => [
                'machine_status' => [
                    'monitoring' => [
                        'status' => $monitoring_status,
                        'last_update' => $last_update
                    ]
                ],
                'production_summary' => [
                    'total_production' => ['total_tread' => $total_prod],
                    'quality_summary' => [
                        'ok_quantity' => $ok_qty,
                        'ng_quantity' => $ng_qty,
                        'quality_rate' => $quality_rate
                    ]
                ],
                'availability_metrics' => [
                    'runtime' => ['total_hours' => $runtime_hours],
                    'downtime' => ['total_hours' => $downtime_hours],
                    'source' => $availabilitySource
                ]
            ]
        ];

        json_out($response);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
