<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
  <div class="sidebar-content">
    <div class="sidebar-header p-3">
      <h5 class="text-center mb-0">
        <i class="fas fa-tachometer-alt"></i> Dashboard Menu
      </h5>
      <hr class="bg-light my-2">
    </div>

    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'index.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo appUrl('index.php'); ?>">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'control.php') ? 'active' : ''; ?>" href="<?php echo appUrl('pages/control.php'); ?>">
          <i class="fas fa-play-circle"></i> Machine Control
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'production.php') ? 'active' : ''; ?>" href="<?php echo appUrl('pages/production.php'); ?>">
          <i class="fas fa-boxes"></i> Production
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'quality.php') ? 'active' : ''; ?>" href="<?php echo appUrl('pages/quality.php'); ?>">
          <i class="fas fa-check-circle"></i> Quality Check
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'oee.php') ? 'active' : ''; ?>" href="<?php echo appUrl('pages/oee.php'); ?>">
          <i class="fas fa-chart-line"></i> OEE Analysis
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo appUrl('pages/reports.php'); ?>">
          <i class="fas fa-file-alt"></i> Reports
        </a>
      </li>

      <li class="nav-item mt-4">
        <div class="nav-link">
          <small class="text-white-50">METRICS</small>
        </div>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('index.php#machine-status'); ?>">
          <i class="fas fa-power-off"></i> Status Machine (No 1,2)
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('pages/oee.php#availability'); ?>">
          <i class="fas fa-clock"></i> Runtime (No 3)
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('pages/oee.php#availability'); ?>">
          <i class="fas fa-stop-circle"></i> Downtime (No 4)
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('pages/control.php'); ?>">
          <i class="fas fa-toggle-on"></i> Control (No 5)
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('pages/production.php'); ?>">
          <i class="fas fa-box"></i> Production (No 6)
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link" href="<?php echo appUrl('pages/quality.php'); ?>">
          <i class="fas fa-check-double"></i> Quality (No 7)
        </a>
      </li>

    </ul>
  </div>

  <div class="sidebar-footer p-3">
    <div class="text-center text-white-50 small">
      <i class="fas fa-database"></i> 3 Tables<br>
      <i class="fas fa-chart-bar"></i> 7 Metrics<br>
      <hr class="my-2" style="border-color: rgba(255,255,255,.15);">
      <div class="badge bg-success">Online</div>
      <div class="mt-1">Shift: <?php echo getShift(); ?></div>
    </div>
  </div>
</div>
