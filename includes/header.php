<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' | ' . (isset($pageTitle) ? $pageTitle : 'Dashboard'); ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery (required by inline scripts) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Fallback to jsDelivr if code.jquery.com is blocked/unavailable -->
    <script>
        window.jQuery || document.write('\x3Cscript src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js">\x3C/script>');
    </script>

    <!-- Custom CSS -->
    <link href="<?php echo appUrl('assets/css/style.css'); ?>" rel="stylesheet">
    <link href="<?php echo appUrl('assets/css/dashboard.css'); ?>" rel="stylesheet">
    <link href="<?php echo appUrl('assets/css/charts.css'); ?>" rel="stylesheet">
    <link href="<?php echo appUrl('assets/css/responsive.css'); ?>" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo appUrl('assets/images/favicon.ico'); ?>">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" id="appNavbar">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>

            <a class="navbar-brand" href="<?php echo appUrl('index.php'); ?>">
                <i class="fas fa-industry"></i> <?php echo SITE_NAME; ?>
            </a>

            <div class="navbar-nav ms-auto">
                <div class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <?php echo MACHINE_NAME; ?>
                        <span class="status-indicator <?php echo getMachineStatus() ? 'status-running' : 'status-stopped'; ?>"></span>
                        <?php echo getMachineStatus() ? 'RUNNING' : 'STOPPED'; ?>
                    </span>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> Operator
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Make base path available for all inline scripts and main.js
        window.APP_BASE_PATH = <?php echo json_encode(APP_BASE_PATH); ?>;
        window.IS_DASHBOARD = <?php echo json_encode(basename($_SERVER['PHP_SELF']) === 'index.php'); ?>;
    </script>

    <!-- Compact topbar (no duplicate page title) -->
<div class="topbar container-fluid py-2" id="appTopbar" style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border-color);">

        <div class="d-flex justify-content-end align-items-center flex-wrap gap-2">
            <small class="text-muted me-auto"><?php echo date('l, d M Y'); ?></small>
            <form class="d-flex" role="search" onsubmit="return false;">
                <input class="form-control form-control-sm" id="globalSearch" placeholder="Search...">
                <button class="btn btn-sm btn-outline-primary ms-2" type="button" onclick="performGlobalSearch()"><i class="fas fa-search"></i></button>
            </form>
            <div class="text-end small">
                <div>Shift: <?php echo getShift(); ?></div>
                <div id="headerClock"><?php echo date('H:i:s'); ?></div>
            </div>
        </div>
    </div>

    <script>
        function performGlobalSearch() {
            var q = document.getElementById('globalSearch').value.trim();
            if (!q) return;
            window.location.href = window.APP_BASE_PATH + 'pages/reports.php?search=' + encodeURIComponent(q);
        }

        function updateHeaderClock() {
            var el = document.getElementById('headerClock');
            if (!el) return;
            var now = new Date();
            var s = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0') + ':' + now.getSeconds().toString().padStart(2, '0');
            el.textContent = s;
        }
        setInterval(updateHeaderClock, 1000);
        document.addEventListener('DOMContentLoaded', updateHeaderClock);
    </script>

<script>
(function () {
  // Keep CSS --navbar-height in sync with the real navbar height.
  function setNavbarHeight() {
    const nav = document.getElementById('appNavbar');
    if (!nav) return;
    document.documentElement.style.setProperty('--navbar-height', nav.offsetHeight + 'px');
  }
  window.addEventListener('load', setNavbarHeight);
  window.addEventListener('resize', setNavbarHeight);
  document.addEventListener('DOMContentLoaded', setNavbarHeight);
})();
</script>

<!-- NOTE: Do NOT close </body> here. It is closed in includes/footer.php -->
