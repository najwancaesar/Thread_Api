<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();

    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 8;
    $hours = max(1, min(168, $hours));
    $machineId = request_machine_id();

    [$machineWhere, $machineParams] = build_machine_filter($pdo, 'machine_events', $machineId);
    [$qualityWhere, $qualityParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);

    // Latest monitoring status
    $stmt = $pdo->prepare("SELECT status_bit, recorded_at FROM machine_events WHERE metric_code = 'STATUS_MON'{$machineWhere} ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute($machineParams);
    $statusRow = $stmt->fetch();
    if (!$statusRow) {
        $stmt = $pdo->prepare("SELECT status_bit, recorded_at FROM machine_events WHERE metric_code = 'STATUS_HIST'{$machineWhere} ORDER BY recorded_at DESC LIMIT 1");
        $stmt->execute($machineParams);
        $statusRow = $stmt->fetch();
    }

    $monitoringStatus = 'UNKNOWN';
    $statusUpdatedAt = null;
    if ($statusRow) {
        $monitoringStatus = ((int)$statusRow['status_bit'] === 1) ? 'RUNNING' : 'STOPPED';
        $statusUpdatedAt = $statusRow['recorded_at'];
    }

    // Latest control status
    [$controlWhere, $controlParams] = build_machine_filter($pdo, 'machine_control', $machineId);
    $stmt = $pdo->prepare("SELECT status_bit, mode_bit, recorded_at FROM machine_control WHERE 1=1{$controlWhere} ORDER BY recorded_at DESC LIMIT 1");
    $stmt->execute($controlParams);
    $controlRow = $stmt->fetch();

    $controlStatus = $controlRow ? (((int)$controlRow['status_bit'] === 1) ? 'ON' : 'OFF') : null;
    $controlMode = $controlRow ? (((int)$controlRow['mode_bit'] === 1) ? 'AUTO' : 'MANUAL') : null;
    $controlUpdatedAt = $controlRow['recorded_at'] ?? null;

    // Availability
    $availabilitySource = 'machine_events_sum';
    $avail = compute_availability_seconds_from_status($pdo, $hours, $machineId);
    if (($avail['source'] ?? 'none') !== 'none') {
        $runtimeSeconds = (int)$avail['runtime_seconds'];
        $downtimeSeconds = (int)$avail['downtime_seconds'];
        $availabilitySource = $avail['source'] ?? 'status_history_diff';
        if (!empty($avail['status_metric_code'])) {
            $availabilitySource .= ':' . $avail['status_metric_code'];
        }
    } else {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS runtime_seconds FROM machine_events WHERE metric_code = 'RUNTIME' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
        $stmt->execute(array_merge([$hours], $machineParams));
        $runtimeSeconds = (int)$stmt->fetch()['runtime_seconds'];

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS downtime_seconds FROM machine_events WHERE metric_code = 'DOWNTIME' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
        $stmt->execute(array_merge([$hours], $machineParams));
        $downtimeSeconds = (int)$stmt->fetch()['downtime_seconds'];
    }

    // Production totals
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(value_int),0) AS total_prod FROM machine_events WHERE metric_code = 'PRODUCTION' AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}");
    $stmt->execute(array_merge([$hours], $machineParams));
    $totalProd = (int)$stmt->fetch()['total_prod'];

    // Quality summary
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN ok_bit=1 THEN qty ELSE 0 END) AS ok_qty,
            SUM(CASE WHEN ok_bit=0 THEN qty ELSE 0 END) AS ng_qty,
            SUM(qty) as total_qty
        FROM production_quality_log
        WHERE recorded_at >= (NOW() - INTERVAL ? HOUR){$qualityWhere}
    ");
    $stmt->execute(array_merge([$hours], $qualityParams));
    $qualityRow = $stmt->fetch() ?: [];

    $okQty = (int)($qualityRow['ok_qty'] ?? 0);
    $ngQty = (int)($qualityRow['ng_qty'] ?? 0);
    $totalQty = (int)($qualityRow['total_qty'] ?? 0);
    $qualityRate = $totalQty > 0 ? round(($okQty / $totalQty) * 100, 2) : 0;

    json_out([
        'ok' => true,
        'machine_id' => $machineId,
        'hours_period' => $hours,
        'as_of' => now(),
        'status' => [
            'monitoring' => [
                'status' => $monitoringStatus,
                'last_update' => $statusUpdatedAt,
            ],
            'control' => [
                'status' => $controlStatus,
                'mode' => $controlMode,
                'last_update' => $controlUpdatedAt,
            ],
        ],
        'availability' => [
            'runtime_seconds' => $runtimeSeconds,
            'downtime_seconds' => $downtimeSeconds,
            'runtime_hours' => round($runtimeSeconds / 3600, 2),
            'downtime_hours' => round($downtimeSeconds / 3600, 2),
            'source' => $availabilitySource,
        ],
        'production' => [
            'total_qty' => $totalProd,
        ],
        'quality' => [
            'ok_quantity' => $okQty,
            'ng_quantity' => $ngQty,
            'total_quantity' => $totalQty,
            'quality_rate' => $qualityRate,
        ],
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
