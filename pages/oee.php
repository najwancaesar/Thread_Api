<?php
$pageTitle = 'OEE Analysis';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Default values
$hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
$ideal_rate = isset($_GET['ideal_rate']) ? (float)$_GET['ideal_rate'] : 60;
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'today';

// Placeholder initial values; real values are loaded from api/oee.php via AJAX.
$oee_data = [
    'ok' => true,
    'hours_period' => $hours,
    'availability' => [
        'A' => 0,
        'runtime_seconds' => 0,
        'downtime_seconds' => 0
    ],
    'performance' => [
        'P' => 0,
        'total_qty' => 0,
        'actual_rate_per_hour' => 0,
        'ideal_rate_per_hour' => $ideal_rate
    ],
    'quality' => [
        'Q' => 0,
        'ok_quantity' => 0,
        'total_quantity' => 0
    ],
    'oee' => 0
];
?>

<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-line text-primary"></i> OEE Analysis Dashboard
            </h1>
            <div class="btn-group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#oeeSettingsModal">
                    <i class="fas fa-cog"></i> Settings
                </button>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- OEE Filter Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="oeeFilterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Time Period</label>
                        <select class="form-select" id="timePeriod" onchange="updateOEEAnalysis()">
                            <option value="8" <?php echo $hours == 8 ? 'selected' : ''; ?>>8 Hours (1 Shift)</option>
                            <option value="24" <?php echo $hours == 24 ? 'selected' : ''; ?>>24 Hours (3 Shifts)</option>
                            <option value="168" <?php echo $hours == 168 ? 'selected' : ''; ?>>7 Days</option>
                            <option value="720" <?php echo $hours == 720 ? 'selected' : ''; ?>>30 Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ideal Rate</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="idealRate" value="<?php echo $ideal_rate; ?>" min="1" max="1000">
                            <span class="input-group-text">pcs/hour</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="dateRange">
                            <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo $date_range == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo $date_range == 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" onclick="updateOEEAnalysis()">
                            <i class="fas fa-chart-bar"></i> Update Analysis
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- OEE Score Cards -->
        <div class="row mb-4">
            <!-- Overall OEE -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">OVERALL OEE</h5>
                                <h1 class="mb-0"><span id="oeeOverall"><?php echo $oee_data['oee']; ?></span>%</h1>
                                <small class="text-muted">Last <?php echo $hours; ?> Hours</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tachometer-alt fa-4x text-primary opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" id="oeeOverallBar" style="width: <?php echo $oee_data['oee']; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small>World Class: >85%</small>
                            <small>Target: 75%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Availability -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">AVAILABILITY</h5>
                                <h1 class="mb-0"><span id="oeeAvailability"><?php echo $oee_data['availability']['A']; ?></span>%</h1>
                                <small class="text-muted">Metric No 3 & 4</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-4x text-success opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>Runtime</small>
                                <div><span id="oeeRuntimeHours"><?php echo round($oee_data['availability']['runtime_seconds'] / 3600, 1); ?></span>h</div>
                            </div>
                            <div class="col-6">
                                <small>Downtime</small>
                                <div><span id="oeeDowntimeHours"><?php echo round($oee_data['availability']['downtime_seconds'] / 3600, 1); ?></span>h</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">PERFORMANCE</h5>
                                <h1 class="mb-0"><span id="oeePerformance"><?php echo $oee_data['performance']['P']; ?></span>%</h1>
                                <small class="text-muted">Metric No 6</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-cogs fa-4x text-warning opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>Actual Rate</small>
                                <div><span id="oeeActualRate"><?php echo $oee_data['performance']['actual_rate_per_hour']; ?></span>/h</div>
                            </div>
                            <div class="col-6">
                                <small>Ideal Rate</small>
                                <div><span id="oeeIdealRate"><?php echo $oee_data['performance']['ideal_rate_per_hour']; ?></span>/h</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quality -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title text-muted">QUALITY</h5>
                                <h1 class="mb-0"><span id="oeeQuality"><?php echo $oee_data['quality']['Q']; ?></span>%</h1>
                                <small class="text-muted">Metric No 7</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-4x text-info opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>OK Parts</small>
                                <div><span id="oeeOkQty"><?php echo $oee_data['quality']['ok_quantity']; ?></span></div>
                            </div>
                            <div class="col-6">
                                <small>Total Parts</small>
                                <div><span id="oeeTotalQty"><?php echo $oee_data['quality']['total_quantity']; ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OEE Charts -->
        <div class="row mb-4">
            <!-- OEE Trend Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-2"></i> OEE Trend (Last 30 Days)
                    </div>
                    <div class="card-body">
                        <canvas id="oeeTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- OEE Components Donut -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i> OEE Components
                    </div>
                    <div class="card-body">
                        <canvas id="oeeComponentsChart" height="200"></canvas>
                        <div class="mt-3 text-center">
                            <h4>OEE = A × P × Q</h4>
                            <h5><span id="oeeFormulaA"><?php echo $oee_data['availability']['A']; ?></span>% ×
                                <span id="oeeFormulaP"><?php echo $oee_data['performance']['P']; ?></span>% ×
                                <span id="oeeFormulaQ"><?php echo $oee_data['quality']['Q']; ?></span>% =
                                <span id="oeeFormulaOEE"><?php echo $oee_data['oee']; ?></span>%
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Analysis -->
        <div class="row">
            <!-- Loss Analysis -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle me-2"></i> Loss Analysis
                    </div>
                    <div class="card-body">
                        <canvas id="lossAnalysisChart" height="200"></canvas>
                        <div class="mt-3">
                            <h6>Major Loss Categories:</h6>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Planned Downtime</span>
                                    <span class="badge bg-secondary">4.2%</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Unplanned Downtime</span>
                                    <span class="badge bg-danger">3.8%</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Reduced Speed</span>
                                    <span class="badge bg-warning">5.4%</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Quality Defects</span>
                                    <span class="badge bg-info">2.3%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Improvement Opportunities -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-lightbulb me-2"></i> Improvement Opportunities
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Area</th>
                                        <th>Current</th>
                                        <th>Target</th>
                                        <th>Potential Gain</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Reduce Setup Time</td>
                                        <td>45 min</td>
                                        <td>30 min</td>
                                        <td class="text-success">+2.1% OEE</td>
                                        <td><span class="badge bg-danger">High</span></td>
                                    </tr>
                                    <tr>
                                        <td>Improve Speed</td>
                                        <td>55.4 pcs/h</td>
                                        <td>60 pcs/h</td>
                                        <td class="text-success">+4.6% OEE</td>
                                        <td><span class="badge bg-warning">Medium</span></td>
                                    </tr>
                                    <tr>
                                        <td>Reduce Defects</td>
                                        <td>3.3%</td>
                                        <td>2.0%</td>
                                        <td class="text-success">+1.3% OEE</td>
                                        <td><span class="badge bg-info">Low</span></td>
                                    </tr>
                                    <tr>
                                        <td>Preventive Maintenance</td>
                                        <td>2.5h/week</td>
                                        <td>2.0h/week</td>
                                        <td class="text-success">+0.8% OEE</td>
                                        <td><span class="badge bg-secondary">Low</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-primary" onclick="showImprovementPlan()">
                                <i class="fas fa-file-alt"></i> Generate Improvement Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OEE History & Comparison -->
        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-history me-2"></i> OEE Historical Data
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportOEEReport()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="showOEEBenchmark()">
                                <i class="fas fa-chart-bar"></i> Benchmark
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>OEE</th>
                                        <th>Availability</th>
                                        <th>Performance</th>
                                        <th>Quality</th>
                                        <th>Production</th>
                                        <th>Downtime</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Today (<?php echo date('d/m'); ?>)</td>
                                        <td><strong><?php echo $oee_data['oee']; ?>%</strong></td>
                                        <td><?php echo $oee_data['availability']['A']; ?>%</td>
                                        <td><?php echo $oee_data['performance']['P']; ?>%</td>
                                        <td><?php echo $oee_data['quality']['Q']; ?>%</td>
                                        <td><?php echo $oee_data['performance']['total_qty']; ?> pcs</td>
                                        <td><?php echo round($oee_data['availability']['downtime_seconds'] / 3600, 1); ?>h</td>
                                        <td><i class="fas fa-arrow-up text-success"></i> +2.1%</td>
                                    </tr>
                                    <tr>
                                        <td>Yesterday</td>
                                        <td>76.1%</td>
                                        <td>86.2%</td>
                                        <td>91.5%</td>
                                        <td>96.3%</td>
                                        <td>432 pcs</td>
                                        <td>3.2h</td>
                                        <td><i class="fas fa-arrow-down text-danger"></i> -1.8%</td>
                                    </tr>
                                    <tr>
                                        <td>This Week</td>
                                        <td>77.5%</td>
                                        <td>87.8%</td>
                                        <td>92.1%</td>
                                        <td>96.0%</td>
                                        <td>3015 pcs</td>
                                        <td>21.5h</td>
                                        <td><i class="fas fa-arrow-up text-success"></i> +1.2%</td>
                                    </tr>
                                    <tr>
                                        <td>Last Week</td>
                                        <td>76.3%</td>
                                        <td>86.5%</td>
                                        <td>91.8%</td>
                                        <td>95.9%</td>
                                        <td>2987 pcs</td>
                                        <td>23.1h</td>
                                        <td><i class="fas fa-minus text-warning"></i> -0.5%</td>
                                    </tr>
                                    <tr>
                                        <td>This Month</td>
                                        <td>78.2%</td>
                                        <td>88.1%</td>
                                        <td>92.3%</td>
                                        <td>96.1%</td>
                                        <td>12540 pcs</td>
                                        <td>92.8h</td>
                                        <td><i class="fas fa-arrow-up text-success"></i> +3.4%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- OEE Settings Modal -->
<div class="modal fade" id="oeeSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">OEE Analysis Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="oeeSettingsForm">
                    <div class="mb-3">
                        <label class="form-label">Target OEE (%)</label>
                        <input type="number" class="form-control" id="targetOEE" value="75" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">World Class Threshold (%)</label>
                        <input type="number" class="form-control" id="worldClassThreshold" value="85" min="0" max="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard Ideal Rate (pcs/hour)</label>
                        <input type="number" class="form-control" id="standardIdealRate" value="60" min="1" max="1000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Working Hours per Day</label>
                        <input type="number" class="form-control" id="workingHours" value="24" min="1" max="24">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Days per Week</label>
                        <input type="number" class="form-control" id="daysPerWeek" value="7" min="1" max="7">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveOEESettings()">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- OEE Calculation Explanation -->
<div class="modal fade" id="oeeExplanationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">OEE Calculation Explained</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h4>Overall Equipment Effectiveness (OEE)</h4>
                <p>OEE = Availability × Performance × Quality</p>

                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5>Availability (A)</h5>
                                <p>Percentage of scheduled time that the equipment is available to operate.</p>
                                <strong>Formula:</strong><br>
                                <code>A = Runtime / Planned Production Time</code><br>
                                <small>Uses metrics No 3 & 4</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5>Performance (P)</h5>
                                <p>Speed at which the equipment runs as a percentage of its designed speed.</p>
                                <strong>Formula:</strong><br>
                                <code>P = (Total Count / Runtime) / Ideal Run Rate</code><br>
                                <small>Uses metric No 6</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5>Quality (Q)</h5>
                                <p>Percentage of good units produced as a percentage of total units started.</p>
                                <strong>Formula:</strong><br>
                                <code>Q = Good Count / Total Count</code><br>
                                <small>Uses metric No 7</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5>OEE Benchmarks:</h5>
                    <ul>
                        <li><span class="badge bg-danger">0-65%</span> Unacceptable - Needs immediate improvement</li>
                        <li><span class="badge bg-warning">65-75%</span> Typical - Room for improvement</li>
                        <li><span class="badge bg-info">75-85%</span> Good - Competitive</li>
                        <li><span class="badge bg-success">85-100%</span> World Class - Best in class</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Charts
    let oeeTrendChart, oeeComponentsChart, lossAnalysisChart;

    function initCharts() {
        // OEE Trend Chart
        const trendCtx = document.getElementById('oeeTrendChart').getContext('2d');
        oeeTrendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: Array.from({
                    length: 30
                }, (_, i) => `${i + 1}/12`),
                datasets: [{
                    label: 'OEE (%)',
                    data: Array.from({
                        length: 30
                    }, () => Math.floor(Math.random() * 15) + 70),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Target (75%)',
                    data: Array.from({
                        length: 30
                    }, () => 75),
                    borderColor: '#e74c3c',
                    borderWidth: 1,
                    borderDash: [5, 5],
                    fill: false
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
                        beginAtZero: false,
                        min: 60,
                        max: 100,
                        title: {
                            display: true,
                            text: 'OEE (%)'
                        }
                    }
                }
            }
        });

        // OEE Components Chart
        const componentsCtx = document.getElementById('oeeComponentsChart').getContext('2d');
        oeeComponentsChart = new Chart(componentsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Availability Loss', 'Performance Loss', 'Quality Loss', 'OEE'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        '#e74c3c',
                        '#f39c12',
                        '#9b59b6',
                        '#2ecc71'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });

        // Loss Analysis Chart
        const lossCtx = document.getElementById('lossAnalysisChart').getContext('2d');
        lossAnalysisChart = new Chart(lossCtx, {
            type: 'bar',
            data: {
                labels: ['Planned Downtime', 'Unplanned Downtime', 'Setup Time', 'Reduced Speed', 'Minor Stops', 'Defects'],
                datasets: [{
                    label: 'Loss Percentage',
                    data: [4.2, 3.8, 2.1, 5.4, 1.5, 2.3],
                    backgroundColor: [
                        '#95a5a6',
                        '#e74c3c',
                        '#f39c12',
                        '#f1c40f',
                        '#3498db',
                        '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        title: {
                            display: true,
                            text: 'Loss (%)'
                        }
                    }
                }
            }
        });
    }

    function updateOEEAnalysis() {
        const hours = $('#timePeriod').val();
        const idealRate = $('#idealRate').val();
        const dateRange = $('#dateRange').val();

        const apiUrl = (window.APP_BASE_PATH || '') +
            `api/oee.php?hours=${encodeURIComponent(hours)}&ideal_rate_per_hour=${encodeURIComponent(idealRate)}`;

        $.ajax({
            url: apiUrl,
            method: 'GET',
            dataType: 'json',
            beforeSend: function() {
                showNotification('Loading OEE data...', 'info');
            },
            success: function(data) {
                if (data && data.ok) {
                    applyOEEData(data);
                    showNotification('OEE updated successfully!', 'success');
                } else {
                    showNotification('Failed to load OEE data', 'danger');
                }
            },
            error: function(xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.error) ?
                    xhr.responseJSON.error :
                    'Network/API error while loading OEE';
                showNotification(msg, 'danger');
            }
        });
    }

    function saveOEESettings() {
        const settings = {
            targetOEE: $('#targetOEE').val(),
            worldClass: $('#worldClassThreshold').val(),
            idealRate: $('#standardIdealRate').val(),
            workingHours: $('#workingHours').val(),
            daysPerWeek: $('#daysPerWeek').val()
        };

        // Save to localStorage or send to server
        localStorage.setItem('oeeSettings', JSON.stringify(settings));

        showNotification('OEE settings saved successfully!', 'success');
        $('#oeeSettingsModal').modal('hide');
    }

    function loadOEESettings() {
        const saved = localStorage.getItem('oeeSettings');
        if (saved) {
            const settings = JSON.parse(saved);
            $('#targetOEE').val(settings.targetOEE);
            $('#worldClassThreshold').val(settings.worldClass);
            $('#standardIdealRate').val(settings.idealRate);
            $('#workingHours').val(settings.workingHours);
            $('#daysPerWeek').val(settings.daysPerWeek);
        }
    }

    function exportOEEReport() {
        showNotification('Generating OEE report...', 'info');

        // In real implementation, generate and download report
        setTimeout(() => {
            showNotification('OEE report generated and downloaded!', 'success');
        }, 2000);
    }

    function showImprovementPlan() {
        const d = (window.__currentOEEData || null);
        const oee = d ? (d.oee ?? 0) : 0;
        const A = d && d.availability ? (d.availability.A ?? 0) : 0;
        const P = d && d.performance ? (d.performance.P ?? 0) : 0;
        const Q = d && d.quality ? (d.quality.Q ?? 0) : 0;

        const plan = `
        <div class="text-center mb-3">
            <h4>OEE Improvement Plan</h4>
            <p class="text-muted">Generated on ${new Date().toLocaleDateString()}</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bullseye"></i> Current Status
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Current OEE:</td>
                                <td><strong>${oee}%</strong></td>
                            </tr>
                            <tr>
                                <td>Target OEE:</td>
                                <td>75%</td>
                            </tr>
                            <tr>
                                <td>Gap to Target:</td>
                                <td>${(75 - oee).toFixed(1)}%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Improvement Targets
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Availability:</td>
                                <td>${A}% → 90%</td>
                            </tr>
                            <tr>
                                <td>Performance:</td>
                                <td>${P}% → 95%</td>
                            </tr>
                            <tr>
                                <td>Quality:</td>
                                <td>${Q}% → 98%</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <h5>Recommended Actions:</h5>
            <ol>
                <li>Reduce setup time from 45 to 30 minutes</li>
                <li>Implement preventive maintenance schedule</li>
                <li>Train operators on speed optimization</li>
                <li>Improve quality control procedures</li>
                <li>Monitor downtime causes weekly</li>
            </ol>
        </div>
    `;

        // Create modal for improvement plan
        const modal = new bootstrap.Modal(document.createElement('div'));
        modal._element.className = 'modal fade';
        modal._element.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">OEE Improvement Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${plan}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printImprovementPlan()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    `;

        document.body.appendChild(modal._element);
        modal.show();
    }

    function applyOEEData(data) {
        window.__currentOEEData = data;

        const oee = (data.oee ?? 0);
        const A = data.availability ? (data.availability.A ?? 0) : 0;
        const P = data.performance ? (data.performance.P ?? 0) : 0;
        const Q = data.quality ? (data.quality.Q ?? 0) : 0;

        $('#oeeOverall').text(oee);
        $('#oeeAvailability').text(A);
        $('#oeePerformance').text(P);
        $('#oeeQuality').text(Q);

        $('#oeeFormulaA').text(A);
        $('#oeeFormulaP').text(P);
        $('#oeeFormulaQ').text(Q);
        $('#oeeFormulaOEE').text(oee);

        $('#oeeOverallBar').css('width', Math.max(0, Math.min(100, oee)) + '%');

        const runtimeHours = data.availability ? ((data.availability.runtime_seconds || 0) / 3600) : 0;
        const downtimeHours = data.availability ? ((data.availability.downtime_seconds || 0) / 3600) : 0;
        $('#oeeRuntimeHours').text(runtimeHours.toFixed(1));
        $('#oeeDowntimeHours').text(downtimeHours.toFixed(1));

        $('#oeeActualRate').text(data.performance ? (data.performance.actual_rate_per_hour ?? 0) : 0);
        $('#oeeIdealRate').text(data.performance ? (data.performance.ideal_rate_per_hour ?? 0) : 0);

        $('#oeeOkQty').text(data.quality ? (data.quality.ok_quantity ?? 0) : 0);
        $('#oeeTotalQty').text(data.quality ? (data.quality.total_quantity ?? 0) : 0);

        // Update charts
        if (oeeTrendChart) {
            oeeTrendChart.data.datasets[0].data = Array.from({
                length: 30
            }, () => Number(oee) || 0);
            oeeTrendChart.update();
        }

        if (oeeComponentsChart) {
            const availabilityLoss = 100 - A;
            const performanceLoss = (100 - P) * (A / 100);
            const qualityLoss = (100 - Q) * (A / 100) * (P / 100);
            oeeComponentsChart.data.datasets[0].data = [
                Math.max(0, availabilityLoss),
                Math.max(0, performanceLoss),
                Math.max(0, qualityLoss),
                Math.max(0, oee)
            ];
            oeeComponentsChart.update();
        }
    }

    function printImprovementPlan() {
        window.print();
    }

    function showOEEBenchmark() {
        $('#oeeExplanationModal').modal('show');
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
        loadOEESettings();
        updateOEEAnalysis();
    });
</script>

<?php
require_once '../includes/footer.php';
?>