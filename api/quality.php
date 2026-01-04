<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();
    $machineId = request_machine_id();
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 8;
    $hours = max(1, min(168, $hours));

    [$qualityWhere, $qualityParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN ok_bit=1 THEN qty ELSE 0 END) AS ok_qty,
            SUM(CASE WHEN ok_bit=0 THEN qty ELSE 0 END) AS ng_qty,
            SUM(qty) as total_qty
        FROM production_quality_log
        WHERE recorded_at >= (NOW() - INTERVAL ? HOUR){$qualityWhere}
    ");
    $stmt->execute(array_merge([$hours], $qualityParams));
    $r = $stmt->fetch();

    $ok_qty = (int)($r['ok_qty'] ?? 0);
    $ng_qty = (int)($r['ng_qty'] ?? 0);
    $total_qty = (int)($r['total_qty'] ?? 0);
    $rate = $total_qty > 0 ? ($ok_qty / $total_qty) : 0;

    $metric = get_metric_info('QUALITY');

    json_out([
        'ok' => true,
        'metric' => $metric['name'],
        'metric_no' => $metric['no'],
        'machine_id' => $machineId,
        'hours_period' => $hours,
        'ok_quantity' => $ok_qty,
        'ng_quantity' => $ng_qty,
        'total_quantity' => $total_qty,
        'quality_rate' => round($rate * 100, 2),
        'data_type' => $metric['type'],
        'oee_aspect' => 'Quality'
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
