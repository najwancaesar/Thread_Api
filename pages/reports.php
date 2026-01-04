<?php
$pageTitle = 'Reports & Analytics';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Date ranges
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$week_start = date('Y-m-d', strtotime('-7 days'));
$month_start = date('Y-m-01');
$last_month_start = date('Y-m-01', strtotime('-1 month'));

// Report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'daily';
$report_date = isset($_GET['date']) ? $_GET['date'] : $today;
?>

<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-file-alt text-primary"></i> Reports & Analytics
            </h1>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="generateReport()">
                    <i class="fas fa-file-export"></i> Generate Report
                </button>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Report Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="reportFilterForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" onchange="changeReportType()">
                            <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                            <option value="shift" <?php echo $report_type == 'shift' ? 'selected' : ''; ?>>Shift Report</option>
                            <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                            <option value="custom" <?php echo $report_type == 'custom' ? 'selected' : ''; ?>>Custom Report</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="dateField">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="reportDate" value="<?php echo $report_date; ?>">
                    </div>
                    
                    <div class="col-md-3" id="customRangeField" style="display: none;">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="startDate" value="<?php echo $week_start; ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" id="endDate" value="<?php echo $today; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" id="reportFormat">
                            <option value="html">Web View</option>
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel Spreadsheet</option>
                            <option value="csv">CSV File</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" onclick="loadReport()">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Report Cards -->
        <div class="row mb-4">
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="?type=daily&date=<?php echo $today; ?>" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                        <h6 class="card-title">Today's Report</h6>
                        <small class="text-muted"><?php echo date('d M Y'); ?></small>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="?type=daily&date=<?php echo $yesterday; ?>" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-minus fa-2x text-warning mb-2"></i>
                        <h6 class="card-title">Yesterday</h6>
                        <small class="text-muted"><?php echo date('d M Y', strtotime('-1 day')); ?></small>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="?type=weekly" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-week fa-2x text-success mb-2"></i>
                        <h6 class="card-title">This Week</h6>
                        <small class="text-muted"><?php echo date('d M', strtotime('-7 days')); ?> - <?php echo date('d M Y'); ?></small>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="?type=monthly" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                        <h6 class="card-title">This Month</h6>
                        <small class="text-muted"><?php echo date('M Y'); ?></small>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="#" onclick="showShiftReport('A')" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-sun fa-2x text-warning mb-2"></i>
                        <h6 class="card-title">Shift A</h6>
                        <small class="text-muted">06:00 - 14:00</small>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-2 col-md-4 col-6 mb-4">
                <a href="#" onclick="generateOEEReport()" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x text-danger mb-2"></i>
                        <h6 class="card-title">OEE Report</h6>
                        <small class="text-muted">Performance Analysis</small>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Report Content -->
        <div class="row">
            <!-- Report Summary -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-chart-bar me-2"></i> 
                            <span id="reportTitle"><?php echo ucfirst($report_type); ?> Report</span>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="printReport()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="downloadReport()">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="reportContent">
                            <!-- Report will be loaded here -->
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                <h4>Select a report to view</h4>
                                <p class="text-muted">Use the filters above to generate a report</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Charts -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-chart-area me-2"></i> Report Analytics
                    </div>
                    <div class="card-body">
                        <canvas id="reportChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Report Templates & History -->
            <div class="col-lg-4">
                <!-- Report Templates -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-list me-2"></i> Report Templates
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('production')">
                                <i class="fas fa-boxes text-primary me-2"></i>
                                Production Summary
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('quality')">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Quality Analysis
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('downtime')">
                                <i class="fas fa-stopwatch text-warning me-2"></i>
                                Downtime Analysis
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('oee')">
                                <i class="fas fa-chart-line text-info me-2"></i>
                                OEE Performance
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('shift')">
                                <i class="fas fa-clock text-secondary me-2"></i>
                                Shift Performance
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" onclick="loadTemplate('maintenance')">
                                <i class="fas fa-wrench text-danger me-2"></i>
                                Maintenance Log
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Reports -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i> Recent Reports
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small>Daily Report</small><br>
                                    <strong>29/12/2025</strong>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewReport('daily_20251229')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="downloadReportFile('daily_20251229')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small>Weekly Report</small><br>
                                    <strong>Week 52, 2025</strong>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewReport('weekly_2025w52')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="downloadReportFile('weekly_2025w52')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small>Shift A Report</small><br>
                                    <strong>29/12/2025</strong>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewReport('shiftA_20251229')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="downloadReportFile('shiftA_20251229')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report Statistics -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-2"></i> Report Statistics
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <h1>24</h1>
                            <small class="text-muted">Reports this month</small>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-4">
                                <small>Daily</small>
                                <div class="h5 mb-0">15</div>
                            </div>
                            <div class="col-4">
                                <small>Weekly</small>
                                <div class="h5 mb-0">4</div>
                            </div>
                            <div class="col-4">
                                <small>Monthly</small>
                                <div class="h5 mb-0">1</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Viewer Modal -->
<div class="modal fade" id="reportViewerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewerTitle">Report Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewerContent">
                <!-- Report content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printModalReport()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Chart instance
let reportChart;

function initChart() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    reportChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Production (pcs)',
                data: [850, 920, 810, 950, 870, 0, 0],
                backgroundColor: '#3498db'
            }, {
                label: 'Quality Rate (%)',
                data: [95, 96, 94, 97, 95, 0, 0],
                backgroundColor: '#2ecc71',
                type: 'line',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Production (pcs)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Quality (%)'
                    }
                }
            }
        }
    });
}

function changeReportType() {
    const type = $('#reportType').val();
    
    if (type === 'custom') {
        $('#dateField').hide();
        $('#customRangeField').show();
    } else {
        $('#dateField').show();
        $('#customRangeField').hide();
    }
    
    // Update report title
    $('#reportTitle').text(type.charAt(0).toUpperCase() + type.slice(1) + ' Report');
}

function loadReport() {
    const type = $('#reportType').val();
    const date = $('#reportDate').val();
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    const format = $('#reportFormat').val();
    
    showNotification('Loading report...', 'info');
    
    // Generate report content based on type
    let reportContent = '';
    let reportData = {};
    
    switch(type) {
        case 'daily':
            reportContent = generateDailyReport(date);
            reportData = getDailyReportData(date);
            break;
        case 'shift':
            reportContent = generateShiftReport(date);
            reportData = getShiftReportData(date);
            break;
        case 'weekly':
            reportContent = generateWeeklyReport();
            reportData = getWeeklyReportData();
            break;
        case 'monthly':
            reportContent = generateMonthlyReport();
            reportData = getMonthlyReportData();
            break;
        case 'custom':
            reportContent = generateCustomReport(startDate, endDate);
            reportData = getCustomReportData(startDate, endDate);
            break;
    }
    
    // Update report content
    $('#reportContent').html(reportContent);
    
    // Update chart if data available
    if (reportData.chartData) {
        updateReportChart(reportData.chartData);
    }
    
    showNotification('Report loaded successfully!', 'success');
}

function generateReport() {
    const format = $('#reportFormat').val();
    
    if (format === 'html') {
        loadReport();
    } else {
        // Generate and download file
        const filename = `report_${new Date().toISOString().slice(0,10)}.${format}`;
        showNotification(`Generating ${format.toUpperCase()} report: ${filename}`, 'info');
        
        // Simulate download
        setTimeout(() => {
            showNotification(`${format.toUpperCase()} report generated and downloaded!`, 'success');
        }, 2000);
    }
}

function generateDailyReport(date) {
    const formattedDate = new Date(date).toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    return `
        <div class="report-header text-center mb-4">
            <h3>Daily Production Report</h3>
            <h5>${formattedDate}</h5>
            <hr>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Total Production</h6>
                        <h2>450</h2>
                        <small>pcs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Quality Rate</h6>
                        <h2>96.7%</h2>
                        <small>OK: 435 | NG: 15</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Runtime</h6>
                        <h2>7.0</h2>
                        <small>hours</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>OEE</h6>
                        <h2>78.2%</h2>
                        <small>Overall Effectiveness</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-industry"></i> Production by Shift
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Shift</th>
                                    <th>Production</th>
                                    <th>Quality</th>
                                    <th>Runtime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>A (06:00-14:00)</td>
                                    <td>200 pcs</td>
                                    <td>97.0%</td>
                                    <td>3.5h</td>
                                </tr>
                                <tr>
                                    <td>B (14:00-22:00)</td>
                                    <td>180 pcs</td>
                                    <td>96.5%</td>
                                    <td>3.0h</td>
                                </tr>
                                <tr>
                                    <td>C (22:00-06:00)</td>
                                    <td>70 pcs</td>
                                    <td>95.7%</td>
                                    <td>0.5h</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle"></i> Downtime Summary
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Reason</th>
                                    <th>Duration</th>
                                    <th>Shift</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Maintenance</td>
                                    <td>45 min</td>
                                    <td>A</td>
                                </tr>
                                <tr>
                                    <td>Material Change</td>
                                    <td>30 min</td>
                                    <td>B</td>
                                </tr>
                                <tr>
                                    <td>Power Issue</td>
                                    <td>15 min</td>
                                    <td>C</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-list"></i> Production Batches
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Batch No</th>
                                <th>Qty</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Operator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>08:30:11</td>
                                <td>BATCH-20251229-001</td>
                                <td>50 pcs</td>
                                <td>TREAD</td>
                                <td><span class="badge bg-success">OK</span></td>
                                <td>OP-01</td>
                            </tr>
                            <tr>
                                <td>10:15:22</td>
                                <td>BATCH-20251229-002</td>
                                <td>75 pcs</td>
                                <td>SIDEWALL</td>
                                <td><span class="badge bg-success">OK</span></td>
                                <td>OP-01</td>
                            </tr>
                            <tr>
                                <td>14:30:33</td>
                                <td>BATCH-20251229-003</td>
                                <td>60 pcs</td>
                                <td>TREAD</td>
                                <td><span class="badge bg-danger">NG</span></td>
                                <td>OP-02</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-sticky-note"></i> Summary & Recommendations
                </div>
                <div class="card-body">
                    <h6>Key Highlights:</h6>
                    <ul>
                        <li>Daily production target achieved (450/450 pcs)</li>
                        <li>Quality rate above target (96.7% vs 95% target)</li>
                        <li>Shift C production lower than expected - investigate</li>
                        <li>One NG batch detected - root cause analysis needed</li>
                    </ul>
                    
                    <h6>Recommendations:</h6>
                    <ol>
                        <li>Optimize Shift C performance through additional training</li>
                        <li>Review NG batch from 14:30 for process improvement</li>
                        <li>Schedule preventive maintenance for extruder motor</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <div class="report-footer text-center mt-4">
            <hr>
            <p class="text-muted">
                Generated on ${new Date().toLocaleString('id-ID')} | 
                Thread Extruder Monitoring System
            </p>
        </div>
    `;
}

function generateShiftReport(date) {
    return `
        <div class="text-center py-5">
            <i class="fas fa-clock fa-4x text-warning mb-3"></i>
            <h4>Shift Report</h4>
            <p class="text-muted">Select a shift from the quick cards above</p>
        </div>
    `;
}

function showShiftReport(shift) {
    const shiftInfo = {
        'A': { name: 'Shift A', hours: '06:00 - 14:00', color: 'warning' },
        'B': { name: 'Shift B', hours: '14:00 - 22:00', color: 'info' },
        'C': { name: 'Shift C', hours: '22:00 - 06:00', color: 'secondary' }
    };
    
    const info = shiftInfo[shift];
    
    const content = `
        <div class="report-header text-center mb-4">
            <h3>${info.name} Production Report</h3>
            <h5>${info.hours} | ${new Date().toLocaleDateString('id-ID')}</h5>
            <hr>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Shift Production</h6>
                        <h2>200</h2>
                        <small>pcs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>Quality Rate</h6>
                        <h2>97.0%</h2>
                        <small>OK: 194 | NG: 6</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>OEE</h6>
                        <h2>82.5%</h2>
                        <small>Shift Effectiveness</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users"></i> Shift Team
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Operator</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Team Leader</td>
                            <td>OP-01</td>
                            <td>06:00</td>
                            <td>14:00</td>
                            <td><span class="badge bg-success">Completed</span></td>
                        </tr>
                        <tr>
                            <td>Operator</td>
                            <td>OP-02</td>
                            <td>06:00</td>
                            <td>14:00</td>
                            <td><span class="badge bg-success">Completed</span></td>
                        </tr>
                        <tr>
                            <td>Quality Inspector</td>
                            <td>OP-03</td>
                            <td>06:00</td>
                            <td>14:00</td>
                            <td><span class="badge bg-success">Completed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    $('#viewerTitle').text(`${info.name} Report`);
    $('#viewerContent').html(content);
    $('#reportViewerModal').modal('show');
}

function generateWeeklyReport() {
    return generateDailyReport(new Date().toISOString().slice(0, 10)); // Simplified for demo
}

function generateMonthlyReport() {
    return generateDailyReport(new Date().toISOString().slice(0, 10)); // Simplified for demo
}

function generateCustomReport(startDate, endDate) {
    return `
        <div class="text-center py-5">
            <i class="fas fa-calendar-alt fa-4x text-info mb-3"></i>
            <h4>Custom Report: ${startDate} to ${endDate}</h4>
            <p class="text-muted">Report for selected date range</p>
        </div>
    `;
}

function getDailyReportData(date) {
    return {
        chartData: {
            labels: ['Shift A', 'Shift B', 'Shift C'],
            datasets: [{
                label: 'Production',
                data: [200, 180, 70],
                backgroundColor: '#3498db'
            }]
        }
    };
}

function updateReportChart(data) {
    if (reportChart) {
        reportChart.data = data;
        reportChart.update();
    }
}

function loadTemplate(template) {
    const templates = {
        production: 'Production Summary Template loaded',
        quality: 'Quality Analysis Template loaded',
        downtime: 'Downtime Analysis Template loaded',
        oee: 'OEE Performance Template loaded',
        shift: 'Shift Performance Template loaded',
        maintenance: 'Maintenance Log Template loaded'
    };
    
    showNotification(templates[template], 'info');
    $('#reportContent').html(`
        <div class="text-center py-5">
            <i class="fas fa-clipboard-list fa-4x text-primary mb-3"></i>
            <h4>${template.charAt(0).toUpperCase() + template.slice(1)} Report Template</h4>
            <p class="text-muted">Configure and generate your report</p>
        </div>
    `);
}

function viewReport(reportId) {
    // In real implementation, fetch report from server
    showNotification(`Loading report ${reportId}...`, 'info');
    
    setTimeout(() => {
        $('#viewerTitle').text('Report: ' + reportId);
        $('#viewerContent').html(`
            <div class="text-center py-5">
                <i class="fas fa-file-pdf fa-4x text-danger mb-3"></i>
                <h4>Report: ${reportId}</h4>
                <p class="text-muted">This is a sample report viewer</p>
                <p>In the real system, this would display the actual report content.</p>
            </div>
        `);
        $('#reportViewerModal').modal('show');
    }, 1000);
}

function downloadReportFile(reportId) {
    showNotification(`Downloading report ${reportId}...`, 'info');
    
    setTimeout(() => {
        showNotification(`Report ${reportId} downloaded successfully!`, 'success');
    }, 1500);
}

function printReport() {
    window.print();
}

function downloadReport() {
    const format = $('#reportFormat').val();
    showNotification(`Downloading report as ${format.toUpperCase()}...`, 'info');
    
    setTimeout(() => {
        showNotification(`Report downloaded as ${format.toUpperCase()}!`, 'success');
    }, 2000);
}

function printModalReport() {
    const printContent = $('#viewerContent').html();
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Report Print</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function generateOEEReport() {
    window.open('oee.php', '_blank');
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
    initChart();
    changeReportType(); // Initialize field visibility
    
    // Load report if parameters in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('type') || urlParams.has('date')) {
        loadReport();
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>