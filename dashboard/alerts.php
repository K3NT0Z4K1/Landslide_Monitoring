<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

/* Alert counts */
$counts = $conn->query("
  SELECT
    COUNT(*) AS total,
    SUM(status = 'DANGER')  AS danger_count,
    SUM(status = 'WARNING') AS warning_count
  FROM alert_history
")->fetch_assoc();

/* Full alert history */
$alertHistory = $conn->query("
  SELECT ah.*, n.node_name
  FROM alert_history ah
  LEFT JOIN sensor_nodes n ON n.id = ah.node_id
  ORDER BY ah.created_at DESC
  LIMIT 200
");

$unread_alerts = $counts['total'] ?? 0;
$active_page   = 'alerts';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Alert History — SlopeGuard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body>

  <?php require_once "_sidebar.php"; ?>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
          <i class='bx bx-menu'></i>
        </button>
        <h1>Alert History</h1>
        <p>All recorded warnings and danger events &middot; <?= date('l, F j Y') ?></p>
      </div>
      <div class="topbar-right">
        <div class="topbar-time">
          <i class='bx bx-time-five'></i>
          <span id="clock"><?= date('H:i:s') ?></span>
        </div>
      </div>
    </header>

    <div class="page-content">

      <!-- SUMMARY STRIP -->
      <div class="alert-summary-strip">
        <div class="alert-sum-item">
          <div class="alert-sum-num"><?= number_format($counts['total'] ?? 0) ?></div>
          <div class="alert-sum-lbl">Total Alerts</div>
        </div>
        <div class="alert-sum-divider"></div>
        <div class="alert-sum-item">
          <div class="alert-sum-num warn"><?= number_format($counts['warning_count'] ?? 0) ?></div>
          <div class="alert-sum-lbl">Warnings</div>
        </div>
        <div class="alert-sum-divider"></div>
        <div class="alert-sum-item">
          <div class="alert-sum-num danger"><?= number_format($counts['danger_count'] ?? 0) ?></div>
          <div class="alert-sum-lbl">Danger Events</div>
        </div>
      </div>

      <!-- ALERT TABLE -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-bell'></i> All Alert Events</div>
          <span class="panel-badge teal">Last 200 entries</span>
        </div>
        <?php if ($alertHistory->num_rows === 0): ?>
          <div class="empty-state">
            <i class='bx bx-check-shield'></i>
            <p>No alerts recorded yet — all readings have been safe.</p>
          </div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Date &amp; Time</th>
                <th>Node</th>
                <th>Soil (%)</th>
                <th>Rainfall (mm)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $alertHistory->fetch_assoc()):
                $s  = $row['status'];
                $bc = $s === 'DANGER' ? 'danger' : 'warning';
              ?>
                <tr>
                  <td class="mono"><?= $row['created_at'] ?></td>
                  <td><?= htmlspecialchars($row['node_name'] ?? 'Node ' . $row['node_id']) ?></td>
                  <td><?= $row['soil_moisture'] ?></td>
                  <td><?= $row['rainfall'] ?></td>
                  <td><span class="badge <?= $bc ?>"><?= $s ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>
  </div>


  <script>
    setInterval(() => {
      document.getElementById('clock').textContent = new Date().toTimeString().slice(0, 8);
    }, 1000);

    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarBackdrop').classList.toggle('open');
    }

    function closeSidebar() {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebarBackdrop').classList.remove('open');
    }
  </script>
</body>

</html>