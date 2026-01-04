<?php
$pageTitle = 'Production Monitoring';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get database connection
$db = getDB();

// Get production data
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$week_start = date('Y-m-d', strtotime('-7 days'));

// Today's production
$stmt = $db->prepare("SELECT SUM(qty) as total_today FROM production_quality_log WHERE DATE(recorded_at) = ?");
$stmt->execute([$today]);
$today_production = $stmt->fetch()['total_today'] ?? 0;

// Yesterday's production
$stmt = $db->prepare("SELECT SUM(qty) as total_yesterday FROM production_quality_log WHERE DATE(recorded_at) = ?");
$stmt->execute([$yesterday]);
$yesterday_production = $stmt->fetch()['total_yesterday'] ?? 0;

// Weekly production
$stmt = $db->prepare("SELECT SUM(qty) as total_week FROM production_quality_log WHERE recorded_at >= ?");
$stmt->execute([$week_start]);
$week_production = $stmt->fetch()['total_week'] ?? 0;

// Monthly production (current month)
$month_start = date('Y-m-01');
$stmt = $db->prepare("SELECT SUM(qty) as total_month FROM production_quality_log WHERE recorded_at >= ?");
$stmt->execute([$month_start]);
$month_production = $stmt->fetch()['total_month'] ?? 0;

// Get production by shift
$shifts = ['A', 'B', 'C'];
$shift_production = [];
foreach ($shifts as $shift) {
    $stmt = $db->prepare("
        SELECT SUM(qty) as total 
        FROM production_quality_log 
        WHERE shift = ? AND DATE(recorded_at) = ?
    ");
    $stmt->execute([$shift, $today]);
    $shift_production[$shift] = $stmt->fetch()['total'] ?? 0;
}

// Get latest production batches
$stmt = $db->prepare("
    SELECT p.*, 
           DATE_FORMAT(p.recorded_at, '%H:%i:%s') as time_only,
           DATE_FORMAT(p.recorded_at, '%d/%m/%Y') as date_only
    FROM production_quality_log p
    ORDER BY p.recorded_at DESC 
    LIMIT 20
");
$stmt->execute();
$recent_batches = $stmt->fetchAll();

function production_daily_totals(PDO $db, int $days): array
{
    $days = max(1, $days);
    $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');

    $stmt = $db->prepare("
        SELECT DATE(recorded_at) AS day_key, SUM(qty) AS total_qty
        FROM production_quality_log
        WHERE recorded_at >= ?
        GROUP BY day_key
        ORDER BY day_key ASC
    ");
    $stmt->execute([$start->format('Y-m-d 00:00:00')]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $map[$row['day_key']] = (int)($row['total_qty'] ?? 0);
    }

    $labels = [];
    $values = [];
    for ($i = 0; $i < $days; $i++) {
        $day = $start->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $labels[] = $day->format('d/m');
        $values[] = $map[$key] ?? 0;
    }

    return ['labels' => $labels, 'values' => $values];
}

$productionTrendData = [
    '7' => production_daily_totals($db, 7),
    '30' => production_daily_totals($db, 30),
    '90' => production_daily_totals($db, 90),
];

$treadTotals = ['TREAD' => 0, 'SIDEWALL' => 0];
$treadStart = (new DateTimeImmutable('today'))->modify('-6 days');
$stmt = $db->prepare("
    SELECT tread_type, SUM(qty) AS total_qty
    FROM production_quality_log
    WHERE recorded_at >= ?
    GROUP BY tread_type
");
$stmt->execute([$treadStart->format('Y-m-d 00:00:00')]);
foreach ($stmt->fetchAll() as $row) {
    $type = strtoupper((string)$row['tread_type']);
    if (isset($treadTotals[$type])) {
        $treadTotals[$type] = (int)($row['total_qty'] ?? 0);
    }
}

$hourStart = (new DateTimeImmutable('now'))->modify('-7 hours');
$hourStart = $hourStart->setTime((int)$hourStart->format('H'), 0, 0);
$stmt = $db->prepare("
    SELECT DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') AS hour_key,
           SUM(qty) AS total_qty
    FROM production_quality_log
    WHERE recorded_at >= ?
    GROUP BY hour_key
    ORDER BY hour_key ASC
");
$stmt->execute([$hourStart->format('Y-m-d H:i:s')]);
$hourRows = $stmt->fetchAll();
$hourMap = [];
foreach ($hourRows as $row) {
    $hourMap[$row['hour_key']] = (int)($row['total_qty'] ?? 0);
}

$hourLabels = [];
$hourValues = [];
for ($i = 0; $i < 8; $i++) {
    $bucket = $hourStart->modify('+' . $i . ' hours');
    $key = $bucket->format('Y-m-d H:00:00');
    $hourLabels[] = $bucket->format('H:00');
    $hourValues[] = $hourMap[$key] ?? 0;
}

$batchLookup = [];
foreach ($recent_batches as $batch) {
    $batchLookup[$batch['id']] = [
        'batch_no' => $batch['batch_no'] ?? null,
        'qty' => (int)($batch['qty'] ?? 0),
        'tread_type' => $batch['tread_type'] ?? null,
        'shift' => $batch['shift'] ?? null,
        'operator' => $batch['operator'] ?? null,
        'ok_bit' => (int)($batch['ok_bit'] ?? 0),
        'defect_type' => $batch['defect_type'] ?? null,
        'notes' => $batch['notes'] ?? null,
        'recorded_at' => $batch['recorded_at'] ?? null,
        'time_only' => $batch['time_only'] ?? null,
        'date_only' => $batch['date_only'] ?? null,
    ];
}
?>

<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-boxes text-primary"></i> Production Monitoring
            </h1>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportData('pdf')"><i class="fas fa-file-pdf"></i> PDF Report</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('excel')"><i class="fas fa-file-excel"></i> Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('csv')"><i class="fas fa-file-csv"></i> CSV</a></li>
                </ul>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Production Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Today's Production</h5>
                                <h2 class="mb-0"><?php echo number_format($today_production); ?></h2>
                                <small class="text-muted">Metric No 6</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-day fa-3x text-primary opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <small>Yesterday: <?php echo number_format($yesterday_production); ?></small>
                            <?php if ($yesterday_production > 0): ?>
                                <?php
                                $change = $today_production - $yesterday_production;
                                $percent = ($yesterday_production > 0) ? round(($change / $yesterday_production) * 100, 1) : 0;
                                ?>
                                <small class="<?php echo ($change >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <i class="fas fa-arrow-<?php echo ($change >= 0) ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs($percent); ?>%
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">This Week</h5>
                                <h2 class="mb-0"><?php echo number_format($week_production); ?></h2>
                                <small class="text-muted">Last 7 Days</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-week fa-3x text-success opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <small>Daily Average: <?php echo number_format(round($week_production / 7)); ?> pcs</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">This Month</h5>
                                <h2 class="mb-0"><?php echo number_format($month_production); ?></h2>
                                <small class="text-muted">Month to Date</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-alt fa-3x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <?php
                        $days_in_month = date('t');
                        $current_day = date('j');
                        $projected = ($month_production / $current_day) * $days_in_month;
                        ?>
                        <small>Projected: <?php echo number_format(round($projected)); ?> pcs</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">Production Rate</h5>
                                <h2 class="mb-0"><?php echo round($today_production / 8, 1); ?></h2>
                                <small class="text-muted">pcs/hour (Today)</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tachometer-alt fa-3x text-info opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <small>Target: 60 pcs/hour</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Graphs -->
        <div class="row mb-4">
            <!-- Production Trend Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-chart-line me-2"></i> Production Trend (Last 7 Days)
                        </div>
                        <select class="form-select form-select-sm w-auto" id="chartPeriod" onchange="updateProductionChart()">
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <canvas id="productionTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Shift Production -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-clock me-2"></i> Today's Production by Shift
                    </div>
                    <div class="card-body">
                        <canvas id="shiftChart" height="200"></canvas>
                        <div class="row text-center mt-3">
                            <?php foreach ($shifts as $shift): ?>
                                <div class="col-4">
                                    <div class="p-2 border rounded">
                                        <small>Shift <?php echo $shift; ?></small>
                                        <div class="h5 mb-0"><?php echo number_format($shift_production[$shift]); ?></div>
                                        <small>pcs</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Data -->
        <div class="row">
            <!-- Recent Production Batches -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-history me-2"></i> Recent Production Batches
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="loadProductionData()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addBatchModal">
                                <i class="fas fa-plus"></i> Add Batch
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="productionTable">
                                <thead>
                                    <tr>
                                        <th>Batch No</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Quantity</th>
                                        <th>Shift</th>
                                        <th>Operator</th>
                                        <th>Tread Type</th>
                                        <th>Quality Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_batches)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No production data found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_batches as $batch): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($batch['batch_no'])): ?>
                                                        <?php echo htmlspecialchars($batch['batch_no']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">BATCH-<?php echo str_pad($batch['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $batch['date_only']; ?></td>
                                                <td><?php echo $batch['time_only']; ?></td>
                                                <td>
                                                    <span class="badge bg-primary rounded-pill"><?php echo $batch['qty']; ?> pcs</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">Shift <?php echo $batch['shift'] ?? 'A'; ?></span>
                                                </td>
                                                <td><?php echo $batch['operator'] ?? 'OP-01'; ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $batch['tread_type']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($batch['ok_bit'] == 1): ?>
                                                        <span class="badge bg-success">OK</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">NG</span>
                                                        <?php if ($batch['defect_type'] != 'NONE'): ?>
                                                            <small class="text-muted d-block">(<?php echo $batch['defect_type']; ?>)</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewBatchDetails(<?php echo $batch['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editBatch(<?php echo $batch['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Production pagination">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#">Previous</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Statistics -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i> Production by Tread Type
                    </div>
                    <div class="card-body">
                        <canvas id="treadTypeChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Hourly Production (Today)
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyProductionChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Production Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="batchForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Number</label>
                            <input type="text" class="form-control" id="batchNo" placeholder="BATCH-001" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="batchQty" min="1" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tread Type</label>
                            <select class="form-select" id="treadType" required>
                                <option value="TREAD">Tread</option>
                                <option value="SIDEWALL">Sidewall</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shift</label>
                            <select class="form-select" id="batchShift" required>
                                <option value="A">Shift A (06:00 - 14:00)</option>
                                <option value="B">Shift B (14:00 - 22:00)</option>
                                <option value="C">Shift C (22:00 - 06:00)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Operator</label>
                            <input type="text" class="form-control" id="operator" value="OP-01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quality Status</label>
                            <select class="form-select" id="qualityStatus" required>
                                <option value="1">OK - Good Quality</option>
                                <option value="0">NG - Defective</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="batchNotes" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveBatch()">Save Batch</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Batch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="batchDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
    // Charts
    let productionTrendChart, shiftChart, treadTypeChart, hourlyProductionChart;
    const productionTrendData = <?php echo json_encode($productionTrendData, JSON_UNESCAPED_SLASHES); ?>;
    const treadTypeTotals = <?php echo json_encode($treadTotals, JSON_UNESCAPED_SLASHES); ?>;
    const hourlyLabels = <?php echo json_encode($hourLabels, JSON_UNESCAPED_SLASHES); ?>;
    const hourlyValues = <?php echo json_encode($hourValues, JSON_UNESCAPED_SLASHES); ?>;
    const batchLookup = <?php echo json_encode($batchLookup, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function initCharts() {
        const initialTrend = productionTrendData['7'] || { labels: [], values: [] };

        // Production Trend Chart
        const trendCtx = document.getElementById('productionTrendChart').getContext('2d');
        productionTrendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: initialTrend.labels,
                datasets: [{
                    label: 'Production (pcs)',
                    data: initialTrend.values,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity (pcs)'
                        }
                    }
                }
            }
        });

        // Shift Chart
        const shiftCtx = document.getElementById('shiftChart').getContext('2d');
        shiftChart = new Chart(shiftCtx, {
            type: 'doughnut',
            data: {
                labels: ['Shift A', 'Shift B', 'Shift C'],
                datasets: [{
                    data: [
                        <?php echo $shift_production['A']; ?>,
                        <?php echo $shift_production['B']; ?>,
                        <?php echo $shift_production['C']; ?>
                    ],
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Tread Type Chart
        const treadCtx = document.getElementById('treadTypeChart').getContext('2d');
        treadTypeChart = new Chart(treadCtx, {
            type: 'pie',
            data: {
                labels: ['Tread', 'Sidewall'],
                datasets: [{
                    data: [treadTypeTotals.TREAD || 0, treadTypeTotals.SIDEWALL || 0],
                    backgroundColor: ['#3498db', '#9b59b6']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Hourly Production Chart
        const hourlyCtx = document.getElementById('hourlyProductionChart').getContext('2d');
        hourlyProductionChart = new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Production (pcs)',
                    data: hourlyValues,
                    backgroundColor: '#2ecc71',
                    borderColor: '#27ae60',
                    borderWidth: 1
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
    }

    function updateProductionChart() {
        const period = $('#chartPeriod').val();
        const next = productionTrendData[period];
        if (!next) return;

        productionTrendChart.data.labels = next.labels;
        productionTrendChart.data.datasets[0].data = next.values;
        productionTrendChart.update();
    }

    function loadProductionData() {
        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/production.php?hours=24',
            method: 'GET',
            dataType: 'json',
            beforeSend: function() {
                showNotification('Loading production data...', 'info');
            },
            success: function(data) {
                if (data.ok) {
                    showNotification('Production data loaded successfully!', 'success');
                    // Update UI with new data
                    location.reload(); // Simple reload for demo
                }
            },
            error: function() {
                showNotification('Error loading production data', 'danger');
            }
        });
    }

    function saveBatch() {
        const batchData = {
            batch_no: $('#batchNo').val(),
            qty: $('#batchQty').val(),
            tread_type: $('#treadType').val(),
            shift: $('#batchShift').val(),
            operator: $('#operator').val(),
            ok_bit: $('#qualityStatus').val(),
            notes: $('#batchNotes').val()
        };

        // Validate
        if (!batchData.batch_no || !batchData.qty) {
            showNotification('Please fill in all required fields', 'warning');
            return;
        }

        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/production.php',
            method: 'POST',
            dataType: 'json',
            data: {
                mode: 'add_batch',
                batch_no: batchData.batch_no,
                qty: batchData.qty,
                tread_type: batchData.tread_type,
                shift: batchData.shift,
                operator: batchData.operator,
                ok_bit: batchData.ok_bit,
                notes: batchData.notes
            },
            beforeSend: function() {
                showNotification('Saving batch...', 'info');
                $('#addBatchModal .btn').prop('disabled', true);
            },
            success: function(resp) {
                if (resp && resp.ok) {
                    showNotification('Batch saved successfully', 'success');
                    $('#addBatchModal').modal('hide');
                    $('#batchForm')[0].reset();
                    setTimeout(() => loadProductionData(), 300);
                } else {
                    showNotification('Error: ' + ((resp && resp.error) ? resp.error : 'Unknown error'), 'danger');
                }
            },
            error: function(xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Network/API error';
                showNotification(msg, 'danger');
            },
            complete: function() {
                $('#addBatchModal .btn').prop('disabled', false);
            }
        });
    }

    function viewBatchDetails(batchId) {
        const batch = batchLookup[batchId];
        if (!batch) {
            $('#batchDetailsContent').html('<div class="text-center text-muted">Batch not found.</div>');
            $('#batchDetailsModal').modal('show');
            return;
        }

        const batchNo = batch.batch_no || `BATCH-${String(batchId).padStart(6, '0')}`;
        const statusBadge = batch.ok_bit === 1 ? 'bg-success' : 'bg-danger';
        const statusText = batch.ok_bit === 1 ? 'OK' : 'NG';
        const recordedAt = batch.recorded_at ? batch.recorded_at : '-';
        const notes = batch.notes ? batch.notes : '-';
        const defect = batch.defect_type && batch.defect_type !== 'NONE' ? ` (${batch.defect_type})` : '';

        const details = `
        <div class="text-center mb-3">
            <h4>${batchNo}</h4>
            <p class="text-muted">Production Batch Details</p>
        </div>
        
        <div class="row">
            <div class="col-6 mb-2">
                <strong>Quantity:</strong>
                <div>${batch.qty} pcs</div>
            </div>
            <div class="col-6 mb-2">
                <strong>Tread Type:</strong>
                <div>${batch.tread_type || '-'}</div>
            </div>
            <div class="col-6 mb-2">
                <strong>Shift:</strong>
                <div>${batch.shift ? 'Shift ' + batch.shift : '-'}</div>
            </div>
            <div class="col-6 mb-2">
                <strong>Operator:</strong>
                <div>${batch.operator || '-'}</div>
            </div>
            <div class="col-6 mb-2">
                <strong>Status:</strong>
                <div><span class="badge ${statusBadge}">${statusText}${defect}</span></div>
            </div>
            <div class="col-6 mb-2">
                <strong>Time:</strong>
                <div>${batch.time_only || '-'}</div>
            </div>
            <div class="col-12 mb-2">
                <strong>Recorded At:</strong>
                <div>${recordedAt}</div>
            </div>
            <div class="col-12">
                <strong>Notes:</strong>
                <div class="border rounded p-2 mt-1">${notes}</div>
            </div>
        </div>
    `;

        $('#batchDetailsContent').html(details);
        $('#batchDetailsModal').modal('show');
    }

    function editBatch(batchId) {
        // In real implementation, load batch data for editing
        showNotification('Edit feature for batch ' + batchId + ' will be implemented soon', 'info');
    }

    function exportData(format) {
        showNotification('Exporting production data as ' + format.toUpperCase() + '...', 'info');

        // In real implementation, this would generate and download the file
        setTimeout(() => {
            showNotification(format.toUpperCase() + ' export completed!', 'success');
        }, 1500);
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
    });
</script>

<?php
require_once '../includes/footer.php';
?>
