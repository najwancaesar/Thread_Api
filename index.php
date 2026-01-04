<?php
$pageTitle = 'Dashboard';
require_once 'includes/config.php';
$db = getDB();

$chartHours = 8;
$chartStart = (new DateTimeImmutable('now'))->modify('-' . ($chartHours - 1) . ' hours');
$chartStart = $chartStart->setTime((int)$chartStart->format('H'), 0, 0);

$chartLabels = [];
$productionSeries = [];
$qualitySeries = [];

$stmt = $db->prepare("
    SELECT
        DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') AS hour_key,
        SUM(qty) AS total_qty,
        SUM(CASE WHEN ok_bit=1 THEN qty ELSE 0 END) AS ok_qty
    FROM production_quality_log
    WHERE recorded_at >= ?
    GROUP BY hour_key
    ORDER BY hour_key ASC
");
$stmt->execute([$chartStart->format('Y-m-d H:i:s')]);
$hourRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hourMap = [];
foreach ($hourRows as $row) {
    $hourMap[$row['hour_key']] = [
        'total' => (int)($row['total_qty'] ?? 0),
        'ok' => (int)($row['ok_qty'] ?? 0),
    ];
}

for ($i = 0; $i < $chartHours; $i++) {
    $bucket = $chartStart->modify('+' . $i . ' hours');
    $key = $bucket->format('Y-m-d H:00:00');
    $label = $bucket->format('H:00');
    $total = $hourMap[$key]['total'] ?? 0;
    $ok = $hourMap[$key]['ok'] ?? 0;
    $rate = $total > 0 ? round(($ok / $total) * 100, 2) : 0;

    $chartLabels[] = $label;
    $productionSeries[] = $total;
    $qualitySeries[] = $rate;
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!-- Main Content -->
<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-tachometer-alt text-primary"></i> Dashboard Overview
            </h1>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
        
        <!-- Realtime Update Alert -->
        <div class="realtime-update" id="realtimeAlert">
            <i class="fas fa-sync-alt fa-spin"></i> 
            <strong>Realtime Update:</strong> Data akan diperbarui setiap 10 detik
            <button class="btn btn-sm btn-outline-warning float-end" onclick="updateDashboardData()">
                Update Sekarang
            </button>
        </div>
        
        <!-- OEE Metrics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dashboard-card card-availability">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">AVAILABILITY</h5>
                                <h2 class="mb-0" id="availabilityScore">0%</h2>
                                <p class="mb-0">Metric No 3 & 4</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-3x opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2" style="background: rgba(255,255,255,0.2);">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>Runtime</small>
                                <div id="runtimeTotal">0 hours</div>
                            </div>
                            <div class="col-6">
                                <small>Downtime</small>
                                <div id="downtimeTotal">0 hours</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dashboard-card card-performance">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">PERFORMANCE</h5>
                                <h2 class="mb-0" id="performanceScore">0%</h2>
                                <p class="mb-0">Metric No 6</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tachometer-alt fa-3x opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2" style="background: rgba(255,255,255,0.2);">
                        <div class="row text-center">
                            <div class="col-12">
                                <small>Total Production</small>
                                <div id="totalProduction">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dashboard-card card-quality">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">QUALITY</h5>
                                <h2 class="mb-0" id="qualityScore">0%</h2>
                                <p class="mb-0">Metric No 7</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2" style="background: rgba(255,255,255,0.2);">
                        <div class="row text-center">
                            <div class="col-6">
                                <small>OK</small>
                                <div id="okProduction">0</div>
                            </div>
                            <div class="col-6">
                                <small>NG</small>
                                <div id="ngProduction">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="dashboard-card card-oee">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">OVERALL OEE</h5>
                                <h2 class="mb-0" id="oeeScore">0%</h2>
                                <p class="mb-0">Overall Equipment Effectiveness</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-3x opacity-50"></i>
                            </div>
                        </div>
                        <hr class="my-2" style="background: rgba(255,255,255,0.2);">
                        <div class="text-center">
                            <small>Last 8 Hours</small>
                            <div>A × P × Q</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts and Machine Control -->
        <div class="row">
            <!-- Production Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Production Trend (Last 24 Hours)
                    </div>
                    <div class="card-body">
                        <canvas id="productionChart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Recent Production Log -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-history me-2"></i> Recent Production Log
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadProductionLog()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="productionTable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Batch</th>
                                        <th>Qty</th>
                                        <th>Status</th>
                                        <th>Shift</th>
                                        <th>Operator</th>
                                    </tr>
                                </thead>
                                <tbody id="productionTableBody">
                                    <!-- Data will be loaded via AJAX -->
                                    <tr>
                                        <td colspan="6" class="text-center">Loading data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Machine Control & Status -->
            <div class="col-lg-4">
                <!-- Machine Control Panel -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-gamepad me-2"></i> Machine Control Panel
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <h4 id="machineStatusText">LOADING...</h4>
                            <div class="status-indicator status-stopped" id="machineStatusIndicator" style="width: 20px; height: 20px;"></div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-machine btn-machine-on" id="btnMachineOn" onclick="controlMachine('ON')">
                                <i class="fas fa-play"></i> START MACHINE
                            </button>
                            <button class="btn btn-machine btn-machine-off" id="btnMachineOff" onclick="controlMachine('OFF')">
                                <i class="fas fa-stop"></i> STOP MACHINE
                            </button>
                        </div>
                        
                        <div class="mt-4">
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="number" class="form-control" id="runtimeDuration" placeholder="Duration (seconds)" value="300">
                                <span class="input-group-text">sec</span>
                            </div>
                            
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-box"></i></span>
                                <input type="number" class="form-control" id="productionQty" placeholder="Quantity" value="10">
                                <span class="input-group-text">pcs</span>
                            </div>
                            
                            <button class="btn btn-outline-primary w-100" onclick="startProduction()">
                                <i class="fas fa-play-circle"></i> Start Production Run
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i> System Status
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span><i class="fas fa-database"></i> Database</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span><i class="fas fa-plug"></i> API Connection</span>
                                <span class="badge bg-success" id="apiStatus">Online</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success" onclick="recordQualityCheck('OK')">
                                <i class="fas fa-check"></i> Record OK
                            </button>
                            <button class="btn btn-outline-danger" onclick="recordQualityCheck('NG')">
                                <i class="fas fa-times"></i> Record NG
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="qualityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Quality Check</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="qualityForm">
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="qualityQty" value="1" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Result</label>
                        <select class="form-select" id="qualityResult">
                            <option value="OK">OK - Good Quality</option>
                            <option value="NG">NG - Defective</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Defect Type (if NG)</label>
                        <select class="form-select" id="defectType">
                            <option value="NONE">None</option>
                            <option value="CRACK">Crack</option>
                            <option value="BUBBLE">Bubble</option>
                            <option value="DIMENSION">Dimension Issue</option>
                            <option value="TEMP">Temperature Issue</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="qualityNotes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitQualityCheck()">Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize charts
let productionChart;

function initCharts() {
    const ctx = document.getElementById('productionChart').getContext('2d');
    const chartLabels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_SLASHES); ?>;
    const productionSeries = <?php echo json_encode($productionSeries, JSON_UNESCAPED_SLASHES); ?>;
    const qualitySeries = <?php echo json_encode($qualitySeries, JSON_UNESCAPED_SLASHES); ?>;

    productionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Production (pcs)',
                data: productionSeries,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }, {
                label: 'Quality Rate (%)',
                data: qualitySeries,
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Load production log
function loadProductionLog() {
    $.ajax({
        url: (window.APP_BASE_PATH || '') + 'api/production_log.php?limit=10',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            const tableBody = $('#productionTableBody');
            tableBody.empty();

            if (!data.ok || !Array.isArray(data.items) || data.items.length === 0) {
                tableBody.html('<tr><td colspan="6" class="text-center">No production data found</td></tr>');
                return;
            }

            data.items.forEach(item => {
                const status = Number(item.ok_bit) === 1 ? 'OK' : 'NG';
                const batch = item.batch_no ? item.batch_no : `BATCH-${String(item.id).padStart(6, '0')}`;
                const shift = item.shift ? item.shift : '-';
                const operator = item.operator ? item.operator : '-';
                const row = `
                    <tr>
                        <td>${item.time_only || '-'}</td>
                        <td>${batch}</td>
                        <td>${item.qty || 0}</td>
                        <td>
                            <span class="badge ${status === 'OK' ? 'bg-success' : 'bg-danger'}">
                                ${status}
                            </span>
                        </td>
                        <td>${shift}</td>
                        <td>${operator}</td>
                    </tr>
                `;
                tableBody.append(row);
            });
        },
        error: function() {
            $('#productionTableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>');
        }
    });
}

// Machine control functions
function controlMachine(status) {
    const url = `${window.APP_BASE_PATH || ''}api/status.php?mode=control_update&status=${status}`;
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        beforeSend: function() {
            $('#btnMachineOn, #btnMachineOff').prop('disabled', true);
        },
        success: function(data) {
            if (data.ok) {
                alert(`Machine ${status} command sent successfully!`);
                updateDashboardData();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        },
        error: function() {
            alert('Network error. Please check connection.');
        },
        complete: function() {
            $('#btnMachineOn, #btnMachineOff').prop('disabled', false);
        }
    });
}

function startProduction() {
    const duration = $('#runtimeDuration').val();
    const qty = $('#productionQty').val();
    
    const url = `${window.APP_BASE_PATH || ''}api/insert.php?mode=input_status&status=ON&duration=${duration}&qty=${qty}`;
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        beforeSend: function() {
            $('.btn').prop('disabled', true);
        },
        success: function(data) {
            if (data.ok) {
                alert('Production started successfully!');
                updateDashboardData();
                loadProductionLog();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        },
        error: function() {
            alert('Network error. Please check connection.');
        },
        complete: function() {
            $('.btn').prop('disabled', false);
        }
    });
}

function recordQualityCheck(result) {
    $('#qualityResult').val(result);
    if (result === 'NG') {
        $('#defectType').show();
    } else {
        $('#defectType').hide();
    }
    $('#qualityModal').modal('show');
}

function submitQualityCheck() {
    const qty = $('#qualityQty').val();
    const result = $('#qualityResult').val();
    const defect = $('#defectType').val();
    const notes = $('#qualityNotes').val();
    
    let url = `${window.APP_BASE_PATH || ''}api/status.php?mode=input_tread_checked&result=${result}&qty=${qty}`;
    if (defect !== 'NONE') {
        url += `&defect=${defect}`;
    }
    
    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        beforeSend: function() {
            $('.modal-footer .btn').prop('disabled', true);
        },
        success: function(data) {
            if (data.ok) {
                alert('Quality check recorded successfully!');
                $('#qualityModal').modal('hide');
                updateDashboardData();
                loadProductionLog();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        },
        error: function() {
            alert('Network error. Please check connection.');
        },
        complete: function() {
            $('.modal-footer .btn').prop('disabled', false);
            $('#qualityForm')[0].reset();
        }
    });
}

// Update OEE scores
function updateOEEScores() {
    $.ajax({
        url: (window.APP_BASE_PATH || '') + 'api/oee.php?hours=8&ideal_rate_per_hour=60',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.ok) {
                $('#availabilityScore').text(data.availability.A + '%');
                $('#performanceScore').text(data.performance.P + '%');
                $('#qualityScore').text(data.quality.Q + '%');
                $('#oeeScore').text(data.oee + '%');
            }
        }
    });
}

// Enhanced update function
function updateDashboardData() {
    $.ajax({
        url: (window.APP_BASE_PATH || '') + 'api/status.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.ok && data.dashboard) {
                // Update machine status
                const machineStatus = data.dashboard.machine_status;
                const statusText = $('#machineStatusText');
                const statusIndicator = $('#machineStatusIndicator');
                
                if (machineStatus.monitoring && machineStatus.monitoring.status === 'RUNNING') {
                    statusText.text('RUNNING');
                    statusIndicator.removeClass('status-stopped').addClass('status-running');
                    $('#btnMachineOn').prop('disabled', true);
                    $('#btnMachineOff').prop('disabled', false);
                } else {
                    statusText.text('STOPPED');
                    statusIndicator.removeClass('status-running').addClass('status-stopped');
                    $('#btnMachineOn').prop('disabled', false);
                    $('#btnMachineOff').prop('disabled', true);
                }
                
                // Update production data
                const production = data.dashboard.production_summary;
                $('#totalProduction').text(production.total_production.total_tread || 0);
                $('#okProduction').text(production.quality_summary.ok_quantity || 0);
                $('#ngProduction').text(production.quality_summary.ng_quantity || 0);
                $('#qualityRate').text(production.quality_summary.quality_rate + '%' || '0%');
                
                // Update availability
                const availability = data.dashboard.availability_metrics;
                $('#runtimeTotal').text(availability.runtime.total_hours + ' hours');
                $('#downtimeTotal').text(availability.downtime.total_hours + ' hours');
                
                // Update API status
                $('#apiStatus').removeClass('bg-danger bg-warning').addClass('bg-success').text('Online');
                
                // Update OEE scores
                updateOEEScores();

                // Refresh production log for realtime updates
                loadProductionLog();
                
                // Show success alert
                const alertDiv = $('#realtimeAlert');
                alertDiv.removeClass('alert-danger').addClass('alert-success');
                alertDiv.html(`
                    <i class="fas fa-check-circle"></i> 
                    <strong>Updated Successfully:</strong> Dashboard data updated at ${new Date().toLocaleTimeString()}
                    <button class="btn btn-sm btn-outline-success float-end" onclick="updateDashboardData()">
                        Update Again
                    </button>
                `);
            }
        },
        error: function() {
            $('#apiStatus').removeClass('bg-success bg-warning').addClass('bg-danger').text('Offline');
            
            const alertDiv = $('#realtimeAlert');
            alertDiv.removeClass('alert-success').addClass('alert-danger');
            alertDiv.html(`
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Connection Error:</strong> Cannot connect to API server
                <button class="btn btn-sm btn-outline-danger float-end" onclick="updateDashboardData()">
                    Retry
                </button>
            `);
        }
    });
}

// Initialize everything
$(document).ready(function() {
    initCharts();
    loadProductionLog();
    updateDashboardData();
    updateOEEScores();
    
    // Update every 10 seconds
    setInterval(updateDashboardData, 10000);
    setInterval(updateOEEScores, 30000);
});
</script>

<?php
require_once 'includes/footer.php';
?>
