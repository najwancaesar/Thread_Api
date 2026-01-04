<?php
$pageTitle = 'Machine Control';
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="content-wrapper" id="contentWrapper">
    <div class="container-fluid">
        <h1 class="h3 mb-4">
            <i class="fas fa-gamepad text-primary"></i> Machine Control
        </h1>

        <div class="row">
            <div class="col-lg-8">
                <!-- Real-time Control Panel -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sliders-h me-2"></i> Real-time Control Panel
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-4">
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <i class="fas fa-power-off fa-3x mb-3 text-success"></i>
                                    <h5>Power Status</h5>
                                    <h3 id="powerStatus">ON</h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <i class="fas fa-cogs fa-3x mb-3 text-info"></i>
                                    <h5>Operation Mode</h5>
                                    <h3 id="operationMode">AUTO</h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <i class="fas fa-temperature-high fa-3x mb-3 text-warning"></i>
                                    <h5>Temperature</h5>
                                    <h3 id="temperature">185°C</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Control Buttons -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-success btn-lg w-100 py-3" onclick="sendControl('start')">
                                    <i class="fas fa-play-circle me-2"></i> START EXTRUDER
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-danger btn-lg w-100 py-3" onclick="sendControl('stop')">
                                    <i class="fas fa-stop-circle me-2"></i> STOP EXTRUDER
                                </button>
                            </div>
                        </div>

                        <!-- Manual Controls -->
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-hand-paper me-2"></i> Manual Controls
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-primary w-100" onclick="sendControl('motor_on')">
                                            <i class="fas fa-cog"></i> Motor ON
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-danger w-100" onclick="sendControl('motor_off')">
                                            <i class="fas fa-cog"></i> Motor OFF
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-warning w-100" onclick="sendControl('emergency')">
                                            <i class="fas fa-exclamation-triangle"></i> Emergency Stop
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-info w-100" onclick="sendControl('conveyor_on')">
                                            <i class="fas fa-belt"></i> Conveyor ON
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-info w-100" onclick="sendControl('conveyor_off')">
                                            <i class="fas fa-belt"></i> Conveyor OFF
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-outline-secondary w-100" onclick="sendControl('reset')">
                                            <i class="fas fa-redo"></i> Reset Alarms
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i> Control History
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Command</th>
                                        <th>Status</th>
                                        <th>Operator</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="controlHistory">
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Parameter Settings -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sliders-h me-2"></i> Machine Parameters
                    </div>
                    <div class="card-body">
                        <form id="parameterForm">
                            <div class="mb-3">
                                <label class="form-label">Extruder Speed (%)</label>
                                <input type="range" class="form-range" id="speedRange" min="0" max="100" value="75">
                                <div class="d-flex justify-content-between">
                                    <small>0%</small>
                                    <span id="speedValue">75%</span>
                                    <small>100%</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Temperature Setpoint (°C)</label>
                                <input type="number" class="form-control" id="temperatureSet" value="185" min="150" max="250">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pressure Limit (Bar)</label>
                                <input type="number" class="form-control" id="pressureLimit" value="120" min="80" max="200">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Production Rate (pcs/hour)</label>
                                <input type="number" class="form-control" id="productionRate" value="60" min="10" max="120">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Batch Size</label>
                                <input type="number" class="form-control" id="batchSize" value="1000" min="100" max="10000">
                            </div>

                            <button type="button" class="btn btn-primary w-100" onclick="updateParameters()">
                                <i class="fas fa-save me-2"></i> Update Parameters
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Alarm Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle me-2"></i> Alarm Status
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="alarmList">
                            <!-- Alarms loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Control functions
    function sendControl(command) {
        let apiUrl = '';
        let message = '';

        switch (command) {
            case 'start':
                apiUrl = (window.APP_BASE_PATH || '') + 'api/status.php?mode=control_update&status=ON';
                message = 'Starting extruder...';
                break;
            case 'stop':
                apiUrl = (window.APP_BASE_PATH || '') + 'api/status.php?mode=control_update&status=OFF';
                message = 'Stopping extruder...';
                break;
            default:
                alert('Command ' + command + ' will be implemented in PLC integration');
                return;
        }

        if (confirm('Are you sure you want to ' + command + ' the machine?')) {
            $.ajax({
                url: apiUrl,
                method: 'GET',
                dataType: 'json',
                beforeSend: function() {
                    showNotification('Sending command...', 'info');
                },
                success: function(data) {
                    if (data.ok) {
                        showNotification('Command executed successfully!', 'success');
                        updateControlStatus();
                    } else {
                        showNotification('Error: ' + (data.error || 'Unknown error'), 'danger');
                    }
                },
                error: function() {
                    showNotification('Network error. Please check connection.', 'danger');
                }
            });
        }
    }

    function updateParameters() {
        const params = {
            speed: $('#speedValue').text(),
            temperature: $('#temperatureSet').val(),
            pressure: $('#pressureLimit').val(),
            rate: $('#productionRate').val(),
            batch: $('#batchSize').val()
        };

        showNotification('Parameters updated: ' + JSON.stringify(params), 'success');
        // In real implementation, this would send to PLC/API
    }

    function updateControlStatus() {
        // Fetch current machine status
        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/status.php?mode=control_baca',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.ok) {
                    $('#powerStatus').text(data.control_status);
                    $('#operationMode').text(data.control_mode);

                    // Update button states
                    if (data.control_status === 'ON') {
                        $('.btn-success').prop('disabled', true);
                        $('.btn-danger').prop('disabled', false);
                    } else {
                        $('.btn-success').prop('disabled', false);
                        $('.btn-danger').prop('disabled', true);
                    }
                }
            }
        });
    }

    // Initialize
    $(document).ready(function() {
        // Update speed value display
        $('#speedRange').on('input', function() {
            $('#speedValue').text(this.value + '%');
        });

        updateControlStatus();

        // Load control history
        loadControlHistory();

        // Simulate temperature updates
        setInterval(function() {
            const temp = 185 + Math.floor(Math.random() * 5) - 2;
            $('#temperature').text(temp + '°C');
        }, 5000);
    });

    function loadControlHistory() {
        const tableBody = $('#controlHistory');
        tableBody.empty();

        $.ajax({
            url: (window.APP_BASE_PATH || '') + 'api/status.php?mode=control_history&limit=5',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (!data || !data.ok || !Array.isArray(data.items)) {
                    tableBody.append('<tr><td colspan="5" class="text-center text-muted">No history available</td></tr>');
                    return;
                }

                if (data.items.length === 0) {
                    tableBody.append('<tr><td colspan="5" class="text-center text-muted">No history available</td></tr>');
                    return;
                }

                data.items.forEach(item => {
                    const status = item.status || 'OFF';
                    const mode = item.mode || 'AUTO';
                    const time = item.recorded_at ? new Date(item.recorded_at.replace(' ', 'T')) : null;
                    const timeText = time ? time.toLocaleString() : (item.recorded_at || '-');

                    const statusClass = status === 'ON' ? 'success' : 'secondary';
                    const cmd = status === 'ON' ? 'START' : 'STOP';

                    const row = `
                        <tr>
                            <td>${timeText}</td>
                            <td><code>${cmd}</code></td>
                            <td><span class="badge bg-${statusClass}">${status} (${mode})</span></td>
                            <td>System</td>
                            <td><small>-</small></td>
                        </tr>
                    `;
                    tableBody.append(row);
                });
            },
            error: function() {
                tableBody.append('<tr><td colspan="5" class="text-center text-danger">Failed to load history</td></tr>');
            }
        });
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
</script>

<?php
require_once '../includes/footer.php';
?>