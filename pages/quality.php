<?php
$pageTitle = 'Quality Control';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get database connection
$db = getDB();

// Get quality statistics
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('-7 days'));

// Today's quality stats
$stmt = $db->prepare("
    SELECT 
        SUM(qty) as total_qty,
        SUM(CASE WHEN ok_bit = 1 THEN qty ELSE 0 END) as ok_qty,
        SUM(CASE WHEN ok_bit = 0 THEN qty ELSE 0 END) as ng_qty,
        COUNT(*) as total_checks
    FROM production_quality_log 
    WHERE DATE(recorded_at) = ?
");
$stmt->execute([$today]);
$today_stats = $stmt->fetch();

// Weekly quality stats
$stmt = $db->prepare("
    SELECT 
        SUM(qty) as total_qty,
        SUM(CASE WHEN ok_bit = 1 THEN qty ELSE 0 END) as ok_qty,
        SUM(CASE WHEN ok_bit = 0 THEN qty ELSE 0 END) as ng_qty
    FROM production_quality_log 
    WHERE recorded_at >= ?
");
$stmt->execute([$week_start]);
$week_stats = $stmt->fetch();

// Defect analysis
$stmt = $db->prepare("
    SELECT 
        defect_type,
        COUNT(*) as count,
        SUM(qty) as total_qty
    FROM production_quality_log 
    WHERE ok_bit = 0 AND defect_type != 'NONE'
    GROUP BY defect_type
    ORDER BY count DESC
");
$stmt->execute();
$defects = $stmt->fetchAll();

// Get recent quality checks
$stmt = $db->prepare("
    SELECT p.*, 
           DATE_FORMAT(p.recorded_at, '%H:%i:%s') as time_only,
           DATE_FORMAT(p.recorded_at, '%d/%m/%Y') as date_only
    FROM production_quality_log p
    ORDER BY p.recorded_at DESC 
    LIMIT 20
");
$stmt->execute();
$recent_checks = $stmt->fetchAll();

// Calculate rates
$today_ok_rate = ($today_stats['total_qty'] > 0) ? round(($today_stats['ok_qty'] / $today_stats['total_qty']) * 100, 1) : 0;
$week_ok_rate = ($week_stats['total_qty'] > 0) ? round(($week_stats['ok_qty'] / $week_stats['total_qty']) * 100, 1) : 0;

function quality_daily_rates(PDO $db, int $days): array
{
    $days = max(1, $days);
    $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');

    $stmt = $db->prepare("
        SELECT DATE(recorded_at) AS day_key,
               SUM(qty) AS total_qty,
               SUM(CASE WHEN ok_bit = 1 THEN qty ELSE 0 END) AS ok_qty,
               SUM(CASE WHEN ok_bit = 0 THEN qty ELSE 0 END) AS ng_qty
        FROM production_quality_log
        WHERE recorded_at >= ?
        GROUP BY day_key
        ORDER BY day_key ASC
    ");
    $stmt->execute([$start->format('Y-m-d 00:00:00')]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[$row['day_key']] = [
            'total' => (int)($row['total_qty'] ?? 0),
            'ok' => (int)($row['ok_qty'] ?? 0),
            'ng' => (int)($row['ng_qty'] ?? 0),
        ];
    }

    $labels = [];
    $okRates = [];
    $defectRates = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $start->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $total = $map[$key]['total'] ?? 0;
        $ok = $map[$key]['ok'] ?? 0;
        $ng = $map[$key]['ng'] ?? 0;
        $okRate = $total > 0 ? round(($ok / $total) * 100, 2) : 0;
        $defectRate = $total > 0 ? round(($ng / $total) * 100, 2) : 0;

        $labels[] = $day->format('d/m');
        $okRates[] = $okRate;
        $defectRates[] = $defectRate;
    }

    return ['labels' => $labels, 'ok_rate' => $okRates, 'defect_rate' => $defectRates];
}

$qualityTrendData = [
    '7' => quality_daily_rates($db, 7),
    '30' => quality_daily_rates($db, 30),
    '90' => quality_daily_rates($db, 90),
];

$defectLabels = [];
$defectCounts = [];
foreach ($defects as $defect) {
    $defectLabels[] = $defect['defect_type'];
    $defectCounts[] = (int)($defect['count'] ?? 0);
}
if (!$defectLabels) {
    $defectLabels = ['NONE'];
    $defectCounts = [0];
}

$shiftTotals = [
    'A' => ['ok' => 0, 'ng' => 0],
    'B' => ['ok' => 0, 'ng' => 0],
    'C' => ['ok' => 0, 'ng' => 0],
];
$stmt = $db->prepare("
    SELECT shift,
           SUM(CASE WHEN ok_bit = 1 THEN qty ELSE 0 END) AS ok_qty,
           SUM(CASE WHEN ok_bit = 0 THEN qty ELSE 0 END) AS ng_qty
    FROM production_quality_log
    WHERE DATE(recorded_at) = ?
    GROUP BY shift
");
$stmt->execute([$today]);
foreach ($stmt->fetchAll() as $row) {
    $shift = strtoupper((string)$row['shift']);
    if (isset($shiftTotals[$shift])) {
        $shiftTotals[$shift]['ok'] = (int)($row['ok_qty'] ?? 0);
        $shiftTotals[$shift]['ng'] = (int)($row['ng_qty'] ?? 0);
    }
}

$hourStart = (new DateTimeImmutable('now'))->modify('-7 hours');
$hourStart = $hourStart->setTime((int)$hourStart->format('H'), 0, 0);
$stmt = $db->prepare("
    SELECT DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') AS hour_key,
           SUM(qty) AS total_qty,
           SUM(CASE WHEN ok_bit = 1 THEN qty ELSE 0 END) AS ok_qty
    FROM production_quality_log
    WHERE recorded_at >= ?
    GROUP BY hour_key
    ORDER BY hour_key ASC
");
$stmt->execute([$hourStart->format('Y-m-d H:i:s')]);
$hourRows = $stmt->fetchAll();
$hourMap = [];
foreach ($hourRows as $row) {
    $hourMap[$row['hour_key']] = [
        'total' => (int)($row['total_qty'] ?? 0),
        'ok' => (int)($row['ok_qty'] ?? 0),
    ];
}

$hourLabels = [];
$hourRates = [];
for ($i = 0; $i < 8; $i++) {
    $bucket = $hourStart->modify('+' . $i . ' hours');
    $key = $bucket->format('Y-m-d H:00:00');
    $total = $hourMap[$key]['total'] ?? 0;
    $ok = $hourMap[$key]['ok'] ?? 0;
    $rate = $total > 0 ? round(($ok / $total) * 100, 2) : 0;
    $hourLabels[] = $bucket->format('H:00');
    $hourRates[] = $rate;
}

$qualityLookup = [];
foreach ($recent_checks as $check) {
    $qualityLookup[$check['id']] = [
        'batch_no' => $check['batch_no'] ?? null,
        'qty' => (int)($check['qty'] ?? 0),
        'tread_type' => $check['tread_type'] ?? null,
        'shift' => $check['shift'] ?? null,
        'operator' => $check['operator'] ?? null,
        'ok_bit' => (int)($check['ok_bit'] ?? 0),
        'defect_type' => $check['defect_type'] ?? null,
        'defect_severity' => $check['defect_severity'] ?? null,
        'dimension_ok' => $check['dimension_ok'] ?? null,
        'temperature_ok' => $check['temperature_ok'] ?? null,
        'notes' => $check['notes'] ?? null,
        'recorded_at' => $check['recorded_at'] ?? null,
        'time_only' => $check['time_only'] ?? null,
        'date_only' => $check['date_only'] ?? null,
    ];
}
?>

<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-check-circle text-primary"></i> Quality Control Dashboard
            </h1>
            <div class="btn-group">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordQualityModal">
                    <i class="fas fa-plus-circle"></i> Record Quality Check
                </button>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Quality Metrics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Today's Quality Rate</h5>
                                <h2 class="mb-0"><?php echo $today_ok_rate; ?>%</h2>
                                <small class="text-muted">Metric No 7</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-3x text-success opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>OK</small>
                                <div class="h6 mb-0"><?php echo $today_stats['ok_qty'] ?? 0; ?></div>
                            </div>
                            <div class="col-6">
                                <small>NG</small>
                                <div class="h6 mb-0"><?php echo $today_stats['ng_qty'] ?? 0; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Weekly Quality Rate</h5>
                                <h2 class="mb-0"><?php echo $week_ok_rate; ?>%</h2>
                                <small class="text-muted">Last 7 Days</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-check fa-3x text-primary opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>OK</small>
                                <div class="h6 mb-0"><?php echo $week_stats['ok_qty'] ?? 0; ?></div>
                            </div>
                            <div class="col-6">
                                <small>NG</small>
                                <div class="h6 mb-0"><?php echo $week_stats['ng_qty'] ?? 0; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Defect Rate</h5>
                                <h2 class="mb-0">
                                    <?php
                                    $defect_rate = ($today_stats['total_qty'] > 0) ?
                                        round(($today_stats['ng_qty'] / $today_stats['total_qty']) * 100, 1) : 0;
                                    echo $defect_rate;
                                    ?>%
                                </h2>
                                <small class="text-muted">Today's Defects</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <small>Target: &lt; 2%</small>
                        <div class="progress mt-1" style="height: 6px;">
                            <div class="progress-bar bg-warning" style="width: <?php echo min($defect_rate, 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Quality Checks</h5>
                                <h2 class="mb-0"><?php echo $today_stats['total_checks'] ?? 0; ?></h2>
                                <small class="text-muted">Today's Checks</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clipboard-check fa-3x text-info opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <small>Avg: <?php echo round(($today_stats['total_checks'] ?? 0) / 8, 1); ?> checks/hour</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <!-- Quality Trend Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-chart-area me-2"></i> Quality Trend (Last 30 Days)
                        </div>
                        <select class="form-select form-select-sm w-auto" id="qualityPeriod" onchange="updateQualityChart()">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <canvas id="qualityTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Defect Analysis -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-bug me-2"></i> Defect Analysis
                    </div>
                    <div class="card-body">
                        <canvas id="defectChart" height="200"></canvas>
                        <div class="mt-3">
                            <h6>Top Defects:</h6>
                            <div class="list-group list-group-flush">
                                <?php if (empty($defects)): ?>
                                    <div class="text-muted">No defects recorded</div>
                                <?php else: ?>
                                    <?php foreach ($defects as $defect): ?>
                                        <div class="list-group-item d-flex justify-content-between">
                                            <span><?php echo $defect['defect_type']; ?></span>
                                            <span class="badge bg-danger"><?php echo $defect['count']; ?> cases</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quality Data Tables -->
        <div class="row">
            <!-- Recent Quality Checks -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-list-check me-2"></i> Recent Quality Checks
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="loadQualityData()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="showDefectReport()">
                                <i class="fas fa-file-alt"></i> Defect Report
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="qualityTable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Batch/Ref</th>
                                        <th>Quantity</th>
                                        <th>Result</th>
                                        <th>Defect Type</th>
                                        <th>Tread Type</th>
                                        <th>Shift</th>
                                        <th>Operator</th>
                                        <th>Dimensions</th>
                                        <th>Temperature</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_checks)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center">No quality checks found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_checks as $check): ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo $check['date_only']; ?></small><br>
                                                    <strong><?php echo $check['time_only']; ?></strong>
                                                </td>
                                                <td>
                                                    <?php if (!empty($check['batch_no'])): ?>
                                                        <?php echo htmlspecialchars($check['batch_no']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">QC-<?php echo str_pad($check['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $check['qty']; ?> pcs</span>
                                                </td>
                                                <td>
                                                    <?php if ($check['ok_bit'] == 1): ?>
                                                        <span class="badge bg-success">OK</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">NG</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($check['defect_type'] != 'NONE'): ?>
                                                        <span class="badge bg-warning"><?php echo $check['defect_type']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $check['tread_type']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">Shift <?php echo $check['shift'] ?? 'A'; ?></span>
                                                </td>
                                                <td><?php echo $check['operator'] ?? 'OP-01'; ?></td>
                                                <td>
                                                    <?php if (isset($check['dimension_ok']) && $check['dimension_ok'] == 0): ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($check['temperature_ok']) && $check['temperature_ok'] == 0): ?>
                                                        <i class="fas fa-times text-danger"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check text-success"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewQualityDetails(<?php echo $check['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="recheckQuality(<?php echo $check['id']; ?>)">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i> Quality by Shift
                    </div>
                    <div class="card-body">
                        <canvas id="qualityByShiftChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Hourly Quality Rate (Today)
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyQualityChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Record Quality Check Modal -->
<div class="modal fade" id="recordQualityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Quality Check</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="qualityCheckForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" class="form-control" id="qcBatchNo" placeholder="BATCH-001" required>
                            <small class="text-muted">Leave blank for auto-generate</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity Checked</label>
                            <input type="number" class="form-control" id="qcQty" min="1" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tread Type</label>
                            <select class="form-select" id="qcTreadType" required>
                                <option value="TREAD">Tread</option>
                                <option value="SIDEWALL">Sidewall</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quality Result</label>
                            <select class="form-select" id="qcResult" required onchange="toggleDefectFields()">
                                <option value="1">OK - Passed Quality Check</option>
                                <option value="0">NG - Failed Quality Check</option>
                            </select>
                        </div>

                        <!-- Defect Details (shown only when NG is selected) -->
                        <div class="col-12 mb-3" id="defectDetails" style="display: none;">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <i class="fas fa-exclamation-triangle"></i> Defect Details
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Defect Type</label>
                                            <select class="form-select" id="defectType">
                                                <option value="NONE">Select defect type...</option>
                                                <option value="CRACK">Crack/Fissure</option>
                                                <option value="BUBBLE">Air Bubble</option>
                                                <option value="DIMENSION">Dimension Out of Spec</option>
                                                <option value="TEMP">Temperature Issue</option>
                                                <option value="COLOR">Color Variation</option>
                                                <option value="SURFACE">Surface Imperfection</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Severity Level</label>
                                            <select class="form-select" id="defectSeverity">
                                                <option value="LOW">Low - Minor defect</option>
                                                <option value="MEDIUM">Medium - Requires rework</option>
                                                <option value="HIGH">High - Scrap material</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="dimensionIssue">
                                                <label class="form-check-label" for="dimensionIssue">
                                                    Dimension Issue
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="temperatureIssue">
                                                <label class="form-check-label" for="temperatureIssue">
                                                    Temperature Issue
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shift</label>
                            <select class="form-select" id="qcShift" required>
                                <option value="A">Shift A (06:00 - 14:00)</option>
                                <option value="B">Shift B (14:00 - 22:00)</option>
                                <option value="C">Shift C (22:00 - 06:00)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Operator</label>
                            <input type="text" class="form-control" id="qcOperator" value="OP-01" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Inspection Notes</label>
                            <textarea class="form-control" id="qcNotes" rows="3" placeholder="Additional inspection notes, observations, etc."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitQualityCheck()">Submit Quality Check</button>
            </div>
        </div>
    </div>
</div>

<!-- Quality Details Modal -->
<div class="modal fade" id="qualityDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quality Check Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qualityDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
    // Charts
    let qualityTrendChart, defectChart, qualityByShiftChart, hourlyQualityChart;
    const qualityTrendData = <?php echo json_encode($qualityTrendData, JSON_UNESCAPED_SLASHES); ?>;
    const defectLabels = <?php echo json_encode($defectLabels, JSON_UNESCAPED_SLASHES); ?>;
    const defectCounts = <?php echo json_encode($defectCounts, JSON_UNESCAPED_SLASHES); ?>;
    const shiftTotals = <?php echo json_encode($shiftTotals, JSON_UNESCAPED_SLASHES); ?>;
    const hourLabels = <?php echo json_encode($hourLabels, JSON_UNESCAPED_SLASHES); ?>;
    const hourRates = <?php echo json_encode($hourRates, JSON_UNESCAPED_SLASHES); ?>;
    const qualityLookup = <?php echo json_encode($qualityLookup, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function initCharts() {
        const initialTrend = qualityTrendData['30'] || { labels: [], ok_rate: [], defect_rate: [] };
        const shiftLabels = ['Shift A', 'Shift B', 'Shift C'];
        const shiftOkValues = [
            (shiftTotals.A && shiftTotals.A.ok) || 0,
            (shiftTotals.B && shiftTotals.B.ok) || 0,
            (shiftTotals.C && shiftTotals.C.ok) || 0
        ];
        const shiftNgValues = [
            (shiftTotals.A && shiftTotals.A.ng) || 0,
            (shiftTotals.B && shiftTotals.B.ng) || 0,
            (shiftTotals.C && shiftTotals.C.ng) || 0
        ];

        // Quality Trend Chart
        const trendCtx = document.getElementById('qualityTrendChart').getContext('2d');
        qualityTrendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: initialTrend.labels,
                datasets: [{
                    label: 'Quality Rate (%)',
                    data: initialTrend.ok_rate,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Defect Rate (%)',
                    data: initialTrend.defect_rate,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Rate (%)'
                        }
                    }
                }
            }
        });

        // Defect Chart
        const defectCtx = document.getElementById('defectChart').getContext('2d');
        defectChart = new Chart(defectCtx, {
            type: 'bar',
            data: {
                labels: defectLabels,
                datasets: [{
                    label: 'Defect Count',
                    data: defectCounts,
                    backgroundColor: '#e74c3c'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Quality by Shift Chart
        const shiftCtx = document.getElementById('qualityByShiftChart').getContext('2d');
        qualityByShiftChart = new Chart(shiftCtx, {
            type: 'bar',
            data: {
                labels: shiftLabels,
                datasets: [{
                    label: 'OK',
                    data: shiftOkValues,
                    backgroundColor: '#2ecc71'
                }, {
                    label: 'NG',
                    data: shiftNgValues,
                    backgroundColor: '#e74c3c'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Hourly Quality Chart
        const hourlyCtx = document.getElementById('hourlyQualityChart').getContext('2d');
        hourlyQualityChart = new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: 'Quality Rate (%)',
                    data: hourRates,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    function toggleDefectFields() {
        const result = $('#qcResult').val();
        if (result === '0') {
            $('#defectDetails').show();
        } else {
            $('#defectDetails').hide();
        }
    }

    function submitQualityCheck() {
        const qualityData = {
            batch_no: $('#qcBatchNo').val() || `QC-${Date.now()}`,
            qty: $('#qcQty').val(),
            tread_type: $('#qcTreadType').val(),
            ok_bit: $('#qcResult').val(),
            shift: $('#qcShift').val(),
            operator: $('#qcOperator').val(),
            notes: $('#qcNotes').val()
        };

        // Add defect data if NG
        if (qualityData.ok_bit === '0') {
            qualityData.defect_type = $('#defectType').val();
            qualityData.defect_severity = $('#defectSeverity').val();
            qualityData.dimension_ok = $('#dimensionIssue').is(':checked') ? 0 : 1;
            qualityData.temperature_ok = $('#temperatureIssue').is(':checked') ? 0 : 1;
        } else {
            // For OK results, explicitly default checks to OK
            qualityData.defect_type = 'NONE';
            qualityData.defect_severity = '';
            qualityData.dimension_ok = 1;
            qualityData.temperature_ok = 1;
        }

        // Validate
        if (!qualityData.qty || qualityData.qty < 1) {
            showNotification('Please enter valid quantity', 'warning');
            return;
        }

        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/status.php',
            method: 'POST',
            dataType: 'json',
            data: {
                mode: 'input_tread_checked',
                result: (qualityData.ok_bit === '1' ? 'OK' : 'NG'),
                qty: qualityData.qty,
                tread_type: qualityData.tread_type,
                batch_no: qualityData.batch_no,
                shift: qualityData.shift,
                operator: qualityData.operator,
                notes: qualityData.notes,
                defect: (qualityData.defect_type || 'NONE'),
                defect_severity: (qualityData.defect_severity || ''),
                dimension_ok: qualityData.dimension_ok,
                temperature_ok: qualityData.temperature_ok
            },
            beforeSend: function() {
                $('.modal-footer .btn').prop('disabled', true);
                showNotification('Submitting quality check...', 'info');
            },
            success: function(data) {
                if (data.ok) {
                    showNotification('Quality check recorded successfully!', 'success');
                    $('#recordQualityModal').modal('hide');
                    $('#qualityCheckForm')[0].reset();

                    // Reload page to show new data
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'danger');
                }
            },
            error: function() {
                showNotification('Network error. Please check connection.', 'danger');
            },
            complete: function() {
                $('.modal-footer .btn').prop('disabled', false);
            }
        });
    }

    function loadQualityData() {
        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/quality.php?hours=24',
            method: 'GET',
            dataType: 'json',
            beforeSend: function() {
                showNotification('Loading quality data...', 'info');
            },
            success: function(data) {
                if (data.ok) {
                    showNotification('Quality data loaded successfully!', 'success');
                    location.reload(); // Simple reload for demo
                }
            },
            error: function() {
                showNotification('Error loading quality data', 'danger');
            }
        });
    }

    function viewQualityDetails(checkId) {
        const check = qualityLookup[checkId];
        if (!check) {
            $('#qualityDetailsContent').html('<div class="text-center text-muted">Quality check not found.</div>');
            $('#qualityDetailsModal').modal('show');
            return;
        }

        const ref = check.batch_no ? check.batch_no : `QC-${String(checkId).padStart(6, '0')}`;
        const statusBadge = check.ok_bit === 1 ? 'bg-success' : 'bg-danger';
        const statusText = check.ok_bit === 1 ? 'OK' : 'NG';
        const defect = check.defect_type && check.defect_type !== 'NONE' ? check.defect_type : '-';
        const severity = check.defect_severity ? check.defect_severity : '-';
        const notes = check.notes ? check.notes : '-';

        const dimensionLabel = check.dimension_ok === 0 ? 'NO' : (check.dimension_ok === 1 ? 'YES' : '-');
        const temperatureLabel = check.temperature_ok === 0 ? 'NO' : (check.temperature_ok === 1 ? 'YES' : '-');

        const details = `
        <div class="text-center mb-3">
            <h4>${ref}</h4>
            <p class="text-muted">Detailed Inspection Report</p>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Quantity:</strong></td>
                                <td>${check.qty} pcs</td>
                            </tr>
                            <tr>
                                <td><strong>Tread Type:</strong></td>
                                <td>${check.tread_type || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>Shift:</strong></td>
                                <td>${check.shift ? 'Shift ' + check.shift : '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>Operator:</strong></td>
                                <td>${check.operator || '-'}</td>
                            </tr>
                            <tr>
                                <td><strong>Time:</strong></td>
                                <td>${check.date_only || ''} ${check.time_only || ''}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-check"></i> Inspection Results
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Overall Result:</h5>
                            <span class="badge ${statusBadge} fs-6">${statusText}</span>
                        </div>
                        
                        <div class="list-group">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Defect Type</span>
                                <span class="badge bg-warning">${defect}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Severity</span>
                                <span class="badge bg-secondary">${severity}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Dimension OK</span>
                                <span class="badge ${check.dimension_ok === 0 ? 'bg-danger' : (check.dimension_ok === 1 ? 'bg-success' : 'bg-secondary')}">${dimensionLabel}</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Temperature OK</span>
                                <span class="badge ${check.temperature_ok === 0 ? 'bg-danger' : (check.temperature_ok === 1 ? 'bg-success' : 'bg-secondary')}">${temperatureLabel}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sticky-note"></i> Inspection Notes
                    </div>
                    <div class="card-body">
                        <p class="mb-0">${notes}</p>
                    </div>
                </div>
            </div>
        </div>
    `;

        $('#qualityDetailsContent').html(details);
        $('#qualityDetailsModal').modal('show');
    }

    function recheckQuality(checkId) {
        if (confirm('Are you sure you want to re-check this quality inspection?')) {
            showNotification('Re-check initiated for QC #' + checkId, 'info');
            // In real implementation, this would create a new inspection record
        }
    }

    function showDefectReport() {
        window.open('#', '_blank'); // In real implementation, generate defect report
    }

    function updateQualityChart() {
        const period = $('#qualityPeriod').val();
        const next = qualityTrendData[period];
        if (!next) return;

        qualityTrendChart.data.labels = next.labels;
        qualityTrendChart.data.datasets[0].data = next.ok_rate;
        qualityTrendChart.data.datasets[1].data = next.defect_rate;
        qualityTrendChart.update();
    }

    function showNotification(message, type) {
        const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;

        $('#contentWrapper').prepend(alert);

        setTimeout(() => {
            $('.alert').alert('close');
        }, 3000);
    }

    // Initialize on page load
    $(document).ready(function() {
        initCharts();

        // Initialize defect fields visibility
        toggleDefectFields();
    });
</script>

<?php
require_once '../includes/footer.php';
?>
