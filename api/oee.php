<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();

    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
    $hours = max(1, min(168, $hours));
    $ideal = isset($_GET['ideal_rate_per_hour']) ? (float)$_GET['ideal_rate_per_hour'] : 60.0;
    $ideal = max(1, $ideal);

    $machineId = request_machine_id();
    $avail = compute_availability_seconds_from_status($pdo, $hours, $machineId);

    $availabilitySource = 'machine_events_sum';
    if (($avail['source'] ?? 'none') !== 'none') {
        $runtime = (int)$avail['runtime_seconds'];
        $downtime = (int)$avail['downtime_seconds'];
        $availabilitySource = $avail['source'] ?? 'status_history_diff';
        if (!empty($avail['status_metric_code'])) {
            $availabilitySource .= ':' . $avail['status_metric_code'];
        }
    } else {
        [$machineWhere, $machineParams] = build_machine_filter($pdo, 'machine_events', $machineId);
        $rt = $pdo->prepare("
            SELECT COALESCE(SUM(value_int),0) AS s
            FROM machine_events
            WHERE metric_code = 'RUNTIME'
              AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}
        ");
        $dt = $pdo->prepare("
            SELECT COALESCE(SUM(value_int),0) AS s
            FROM machine_events
            WHERE metric_code = 'DOWNTIME'
              AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}
        ");
        $rt->execute(array_merge([$hours], $machineParams));
        $dt->execute(array_merge([$hours], $machineParams));
        $runtime = (int)$rt->fetch()['s'];
        $downtime = (int)$dt->fetch()['s'];
    }

    $den = $runtime + $downtime;
    $A = $den > 0 ? ($runtime / $den) : 0;

    // Production & Quality (No 6 & 7)
    [$qualityWhere, $qualityParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);
    $pq = $pdo->prepare("
        SELECT
            SUM(qty) as total_qty,
            SUM(CASE WHEN ok_bit=1 THEN qty ELSE 0 END) as ok_qty
        FROM production_quality_log
        WHERE recorded_at >= (NOW()-INTERVAL ? HOUR){$qualityWhere}
    ");
    $pq->execute(array_merge([$hours], $qualityParams));
    $prod = $pq->fetch();

    $totalQty = (int)($prod['total_qty'] ?? 0);
    $okQty = (int)($prod['ok_qty'] ?? 0);

    $runtimeHours = $runtime / 3600.0;
    $actualRate = $runtimeHours > 0 ? ($totalQty / $runtimeHours) : 0;
    $P = $actualRate / $ideal;

    // Quality
    $Q = $totalQty > 0 ? ($okQty / $totalQty) : 0;

    $OEE = $A * $P * $Q;

    json_out([
        'ok' => true,
        'machine_id' => $machineId,
        'hours_period' => $hours,
        'availability' => [
            'A' => round($A * 100, 2),
            'metric_no' => [3, 4],
            'runtime_seconds' => $runtime,
            'downtime_seconds' => $downtime,
            'total_seconds' => $den,
            'source' => $availabilitySource,
            'description' => 'Waktu Operasi vs Total Waktu'
        ],
        'performance' => [
            'P' => round($P * 100, 2),
            'metric_no' => 6,
            'total_qty' => $totalQty,
            'actual_rate_per_hour' => round($actualRate, 2),
            'ideal_rate_per_hour' => $ideal,
            'runtime_hours' => round($runtimeHours, 2),
            'description' => 'Produksi Aktual vs Ideal'
        ],
        'quality' => [
            'Q' => round($Q * 100, 2),
            'metric_no' => 7,
            'ok_quantity' => $okQty,
            'total_quantity' => $totalQty,
            'description' => 'Produk OK vs Total Produk'
        ],
        'oee' => round($OEE * 100, 2),
        'metrics_used' => [
            ['no' => 3, 'name' => 'Waktu Operasi Extruder', 'table' => 'machine_events'],
            ['no' => 4, 'name' => 'Total Downtime Extruder', 'table' => 'machine_events'],
            ['no' => 6, 'name' => 'Jumlah Produksi Aktual', 'table' => 'production_quality_log'],
            ['no' => 7, 'name' => 'Jumlah Produk OK/NG', 'table' => 'production_quality_log']
        ],
        'database_tables' => 3
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
