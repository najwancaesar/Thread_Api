<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = require __DIR__ . '/config.php';
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

// Helper untuk mapping metrik
function get_metric_info($code)
{
    $metrics = [
        'STATUS_HIST' => ['no' => 1, 'name' => 'Status Mesin (Historis)', 'type' => 'Historis'],
        'STATUS_MON' => ['no' => 2, 'name' => 'Status Mesin (Monitoring)', 'type' => 'Monitoring'],
        'RUNTIME' => ['no' => 3, 'name' => 'Waktu Operasi Extruder', 'type' => 'Historis'],
        'DOWNTIME' => ['no' => 4, 'name' => 'Total Downtime Extruder', 'type' => 'Monitoring'],
        'CONTROL' => ['no' => 5, 'name' => 'Pengaktifan Mesin', 'type' => 'Kontrol'],
        'PRODUCTION' => ['no' => 6, 'name' => 'Jumlah Produksi Aktual', 'type' => 'Historis'],
        'QUALITY' => ['no' => 7, 'name' => 'Jumlah Produk OK/NG', 'type' => 'Historis']
    ];

    return $metrics[$code] ?? ['no' => 0, 'name' => $code, 'type' => 'Unknown'];
}

function request_machine_id(?string $fallback = 'EXTRUDER_01'): ?string
{
    $raw = null;
    if (array_key_exists('machine_id', $_REQUEST)) {
        $raw = $_REQUEST['machine_id'];
    } elseif (array_key_exists('machine', $_REQUEST)) {
        $raw = $_REQUEST['machine'];
    }

    if ($raw !== null) {
        $raw = trim((string)$raw);
        if ($raw === '') $raw = null;
    }

    return $raw ?? $fallback;
}

function build_machine_filter(PDO $pdo, string $table, ?string $machineId, string $alias = ''): array
{
    if (!$machineId) return ['', []];

    $cols = table_columns($pdo, $table);
    if (!isset($cols['machine_id'])) return ['', []];

    $prefix = $alias !== '' ? $alias . '.' : '';
    return [" AND {$prefix}machine_id = ?", [$machineId]];
}

function table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) return $cache[$key];

    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['Field'])) $cols[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        // If the table doesn't exist or permission issues, return empty list.
        $cols = [];
    }

    return $cache[$key] = $cols;
}

function insert_row_schema_aware(PDO $pdo, string $table, array $data): int
{
    $cols = table_columns($pdo, $table);
    $filtered = [];
    foreach ($data as $k => $v) {
        if (isset($cols[$k])) $filtered[$k] = $v;
    }

    if (!$filtered) {
        throw new RuntimeException("No insertable columns for table {$table}");
    }

    $fields = array_keys($filtered);
    $placeholders = array_map(fn($f) => ':' . $f, $fields);
    $sql = "INSERT INTO `{$table}` (" . implode(',', array_map(fn($f) => "`{$f}`", $fields)) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    foreach ($filtered as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}

function compute_availability_seconds_from_status(PDO $pdo, int $hours, ?string $machineId = 'EXTRUDER_01'): array
{
    $hours = max(1, min(168, $hours));

    if ($machineId !== null) {
        $machineId = trim((string)$machineId);
        if ($machineId === '') $machineId = null;
    }

    $machineEventsCols = table_columns($pdo, 'machine_events');
    $hasMachineId = isset($machineEventsCols['machine_id']);

    $start = (new DateTimeImmutable('now'))->modify("-{$hours} hours");
    $startStr = $start->format('Y-m-d H:i:s');

    // Prefer STATUS_HIST; fallback to STATUS_MON if missing.
    $metricCode = 'STATUS_HIST';
    $metricCodeCols = $machineEventsCols;

    $whereMachine = ($hasMachineId && $machineId) ? " AND machine_id = :machine_id" : "";

    $stmt = $pdo->prepare(
        "SELECT status_bit, recorded_at
         FROM machine_events
         WHERE metric_code = :metric_code
           AND recorded_at < :start_at" . $whereMachine .
            " ORDER BY recorded_at DESC
          LIMIT 1"
    );
    $stmt->bindValue(':metric_code', $metricCode);
    $stmt->bindValue(':start_at', $startStr);
    if ($hasMachineId && $machineId) $stmt->bindValue(':machine_id', $machineId);
    $stmt->execute();
    $prior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare(
        "SELECT status_bit, recorded_at
         FROM machine_events
         WHERE metric_code = :metric_code
           AND recorded_at >= :start_at" . $whereMachine .
            " ORDER BY recorded_at ASC"
    );
    $stmt->bindValue(':metric_code', $metricCode);
    $stmt->bindValue(':start_at', $startStr);
    if ($hasMachineId && $machineId) $stmt->bindValue(':machine_id', $machineId);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no STATUS_HIST data, try STATUS_MON.
    if (!$prior && !$events) {
        $metricCode = 'STATUS_MON';

        $stmt = $pdo->prepare(
            "SELECT status_bit, recorded_at
             FROM machine_events
             WHERE metric_code = :metric_code
               AND recorded_at < :start_at" . $whereMachine .
                " ORDER BY recorded_at DESC
              LIMIT 1"
        );
        $stmt->bindValue(':metric_code', $metricCode);
        $stmt->bindValue(':start_at', $startStr);
        if ($hasMachineId && $machineId) $stmt->bindValue(':machine_id', $machineId);
        $stmt->execute();
        $prior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare(
            "SELECT status_bit, recorded_at
             FROM machine_events
             WHERE metric_code = :metric_code
               AND recorded_at >= :start_at" . $whereMachine .
                " ORDER BY recorded_at ASC"
        );
        $stmt->bindValue(':metric_code', $metricCode);
        $stmt->bindValue(':start_at', $startStr);
        if ($hasMachineId && $machineId) $stmt->bindValue(':machine_id', $machineId);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $runtime = 0;
    $downtime = 0;

    if (!$prior && !$events) {
        return [
            'runtime_seconds' => 0,
            'downtime_seconds' => 0,
            'source' => 'none',
            'status_metric_code' => $metricCode,
        ];
    }

    $cursor = strtotime($startStr);
    $nowTs = time();
    $status = $prior ? (int)$prior['status_bit'] : (int)($events[0]['status_bit'] ?? 0);

    foreach ($events as $e) {
        $t = strtotime($e['recorded_at']);
        if ($t < $cursor) continue;
        $delta = $t - $cursor;
        if ($delta > 0) {
            if ($status === 1) $runtime += $delta;
            else $downtime += $delta;
        }
        $status = (int)$e['status_bit'];
        $cursor = $t;
    }

    if ($nowTs > $cursor) {
        $delta = $nowTs - $cursor;
        if ($status === 1) $runtime += $delta;
        else $downtime += $delta;
    }

    return [
        'runtime_seconds' => max(0, (int)$runtime),
        'downtime_seconds' => max(0, (int)$downtime),
        'source' => 'status_history_diff',
        'status_metric_code' => $metricCode,
    ];
}

function compute_runtime_downtime_range(PDO $pdo, string $start, string $end, string $machineId): array {
    $use = 'STATUS_HIST';

    $check = $pdo->prepare("SELECT 1 FROM machine_events WHERE metric_code='STATUS_HIST' AND machine_id=? LIMIT 1");
    $check->execute([$machineId]);
    if (!$check->fetchColumn()) {
        $use = 'STATUS_MON';
    }

    // status terakhir sebelum start
    $stmtPrev = $pdo->prepare("SELECT status_bit FROM machine_events 
        WHERE metric_code=? AND machine_id=? AND recorded_at < ?
        ORDER BY recorded_at DESC LIMIT 1");
    $stmtPrev->execute([$use, $machineId, $start]);
    $prev = $stmtPrev->fetch();
    $currentStatus = $prev ? (int)$prev['status_bit'] : 0;

    $stmt = $pdo->prepare("SELECT status_bit, recorded_at FROM machine_events
        WHERE metric_code=? AND machine_id=? AND recorded_at >= ? AND recorded_at <= ?
        ORDER BY recorded_at ASC");
    $stmt->execute([$use, $machineId, $start, $end]);
    $rows = $stmt->fetchAll();

    $runtime = 0;
    $downtime = 0;

    $cursor = strtotime($start);
    $endTs = strtotime($end);

    foreach ($rows as $r) {
        $t = strtotime($r['recorded_at']);
        $delta = max(0, $t - $cursor);

        if ($currentStatus === 1) $runtime += $delta;
        else $downtime += $delta;

        $currentStatus = (int)$r['status_bit'];
        $cursor = $t;
    }

    $tail = max(0, $endTs - $cursor);
    if ($currentStatus === 1) $runtime += $tail;
    else $downtime += $tail;

    return [
        'runtime_seconds' => $runtime,
        'downtime_seconds' => $downtime,
        'total_seconds' => $runtime + $downtime,
        'source' => $use,
    ];
}

