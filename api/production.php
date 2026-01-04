<?php
require __DIR__ . '/db.php';

try {
    $pdo = db();
    $machineId = request_machine_id();

    // Insert production batch (used by UI modal)
    if (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'add_batch') {
        $batch_no = isset($_REQUEST['batch_no']) ? trim((string)$_REQUEST['batch_no']) : '';
        $qty = isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 0;
        $tread_type = isset($_REQUEST['tread_type']) ? strtoupper((string)$_REQUEST['tread_type']) : 'TREAD';
        $shift = isset($_REQUEST['shift']) ? strtoupper((string)$_REQUEST['shift']) : '';
        $operator = isset($_REQUEST['operator']) ? trim((string)$_REQUEST['operator']) : '';
        $ok_bit = isset($_REQUEST['ok_bit']) ? (int)$_REQUEST['ok_bit'] : 1;
        $notes = isset($_REQUEST['notes']) ? trim((string)$_REQUEST['notes']) : '';

        $qty = max(1, min(100000, $qty));
        $ok_bit = $ok_bit ? 1 : 0;
        if (!in_array($tread_type, ['TREAD', 'SIDEWALL'], true)) $tread_type = 'TREAD';

        $insertId = insert_row_schema_aware($pdo, 'production_quality_log', [
            'batch_no' => $batch_no !== '' ? $batch_no : null,
            'qty' => $qty,
            'ok_bit' => $ok_bit,
            'tread_type' => $tread_type,
            'machine_id' => $machineId,
            'shift' => $shift !== '' ? $shift : null,
            'operator' => $operator !== '' ? $operator : null,
            'notes' => $notes !== '' ? $notes : null,
            'defect_type' => $ok_bit ? 'NONE' : 'UNKNOWN',
            'recorded_at' => now(),
        ]);

        json_out([
            'ok' => true,
            'mode' => 'add_batch',
            'insert_id' => $insertId,
            'inserted' => [
                'batch_no' => $batch_no !== '' ? $batch_no : null,
                'qty' => $qty,
                'ok_bit' => $ok_bit,
                'tread_type' => $tread_type,
                'shift' => $shift !== '' ? $shift : null,
                'operator' => $operator !== '' ? $operator : null,
                'notes' => $notes !== '' ? $notes : null,
            ],
            'timestamp' => now(),
        ]);
    }
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 8;
    $hours = max(1, min(168, $hours));

    [$qualityWhere, $qualityParams] = build_machine_filter($pdo, 'production_quality_log', $machineId);
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qty),0) AS total_qty
        FROM production_quality_log
        WHERE recorded_at >= (NOW() - INTERVAL ? HOUR){$qualityWhere}
    ");
    $stmt->execute(array_merge([$hours], $qualityParams));
    $qty = (int)$stmt->fetch()['total_qty'];

    $metric = get_metric_info('PRODUCTION');

    json_out([
        'ok' => true,
        'metric' => $metric['name'],
        'metric_no' => $metric['no'],
        'machine_id' => $machineId,
        'hours_period' => $hours,
        'total_qty' => $qty,
        'data_type' => $metric['type'],
        'oee_aspect' => 'Performance'
    ]);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
