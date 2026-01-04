<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min(100, $limit));

    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 0;
    $hours = max(0, min(168, $hours));

    $machineId = request_machine_id();
    [$machineWhere, $machineParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);
    $timeWhere = $hours > 0 ? " AND recorded_at >= (NOW() - INTERVAL ? HOUR)" : "";

    $stmt = $pdo->prepare("
        SELECT
            id,
            batch_no,
            qty,
            ok_bit,
            tread_type,
            shift,
            operator,
            recorded_at,
            DATE_FORMAT(recorded_at, '%H:%i:%s') AS time_only,
            DATE_FORMAT(recorded_at, '%d/%m/%Y') AS date_only
        FROM production_quality_log
        WHERE 1=1{$machineWhere}{$timeWhere}
        ORDER BY recorded_at DESC
        LIMIT {$limit}
    ");

    $params = $machineParams;
    if ($hours > 0) {
        $params[] = $hours;
    }

    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['qty'] = (int)($row['qty'] ?? 0);
        $row['ok_bit'] = (int)($row['ok_bit'] ?? 0);
        $items[] = $row;
    }

    json_out([
        'ok' => true,
        'machine_id' => $machineId,
        'limit' => $limit,
        'hours' => $hours,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
