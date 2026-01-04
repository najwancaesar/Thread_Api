<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();

    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 8;
    $hours = max(1, min(168, $hours));

    $machineId = request_machine_id();
    $avail = compute_availability_seconds_from_status($pdo, $hours, $machineId);

    $source = 'machine_events_sum';
    if (($avail['source'] ?? 'none') !== 'none') {
        $down = (int)$avail['downtime_seconds'];
        $source = $avail['source'] ?? 'status_history_diff';
        if (!empty($avail['status_metric_code'])) {
            $source .= ':' . $avail['status_metric_code'];
        }
    } else {
        [$machineWhere, $machineParams] = build_machine_filter($pdo, 'machine_events', $machineId);
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(value_int),0) AS downtime_sec
            FROM machine_events
            WHERE metric_code='DOWNTIME'
              AND recorded_at >= (NOW() - INTERVAL ? HOUR){$machineWhere}
        ");
        $params = array_merge([$hours], $machineParams);
        $stmt->execute($params);
        $down = (int)$stmt->fetch()['downtime_sec'];
    }

    $metric = get_metric_info('DOWNTIME');

    json_out([
        'ok' => true,
        'metric' => $metric['name'],
        'metric_no' => $metric['no'],
        'machine_id' => $machineId,
        'hours_period' => $hours,
        'downtime_seconds' => $down,
        'downtime_hours' => round($down / 3600, 2),
        'source' => $source,
        'data_type' => $metric['type'],
        'oee_aspect' => 'Availability'
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
