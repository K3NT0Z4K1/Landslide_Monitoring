<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$alertHistory = $conn->query("
  SELECT ah.*, n.node_name
  FROM alert_history ah
  LEFT JOIN sensor_nodes n ON n.id = ah.node_id
  ORDER BY ah.created_at DESC
  LIMIT 100
");

$counts = $conn->query("
  SELECT
    COUNT(*) AS total,
    SUM(status = 'DANGER')  AS danger_count,
    SUM(status = 'WARNING') AS warning_count,
    MAX(created_at)         AS last_event
  FROM alert_history
")->fetch_assoc();

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
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

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="34" height="34" viewBox="0 0 96 96" fill="none">
      <defs><clipPath id="sc3"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
      <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
      <g clip-path="url(#sc3)" opacity="0.65">
        <line x1="0"   y1="96"  x2="96"  y2="0"   stroke="#0e9fa0" stroke-width="7"/>
        <line x1="8"   y1="104" x2="104" y2="8"   stroke="#1ab8a0" stroke-width="6"/>
        <line x1="-8"  y1="88"  x2="88"  y2="-8"  stroke="#0a7a7b" stroke-width="6"/>
        <line x1="16"  y1="112" x2="112" y2="16"  stroke="#0e9fa0" stroke-width="5"/>
        <line x1="-16" y1="80"  x2="80"  y2="-16" stroke="#0a7a7b" stroke-width="4"/>
      </g>
      <g clip-path="url(#sc3)">
        <polygon points="32,74 48,42 64,74" fill="#0d2a2b" opacity="0.96"/>
        <polygon points="22,74 34,54 46,74" fill="#0d2a2b" opacity="0.9"/>
        <polygon points="44,45 48,36 52,45 48,42" fill="#e0f7f7" opacity="0.9"/>
      </g>
      <circle cx="10" cy="20" r="3" fill="#0e9fa0" opacity="0.75"/>
      <circle cx="86" cy="20" r="3" fill="#0e9fa0" opacity="0.75"/>
    </svg>
    <div class="sidebar-logo-text">
      <h2>SlopeGuard</h2>
      <span>Early Warning System</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <span class="nav-section-label">Main</span>
    <a href="index.php" class="nav-item">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item">
      <i class='bx bx-map-alt'></i> Sensor Map
    </a>
    <a href="alerts.php" class="nav-item active">
      <i class='bx bx-bell'></i> Alert History
      <?php if ($counts['total'] > 0): ?>
        <span class="nav-alert-count"><?= $counts['total'] ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <i class='bx bx-log-out'></i> Sign Out
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <h1>Alert History</h1>
      <p>All WARNING and DANGER events &mdash; last 100 entries</p>
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
        <div class="alert-sum-num"><?= $counts['total'] ?? 0 ?></div>
        <div class="alert-sum-lbl">Total Events</div>
      </div>
      <div class="alert-sum-divider"></div>
      <div class="alert-sum-item">
        <div class="alert-sum-num danger"><?= $counts['danger_count'] ?? 0 ?></div>
        <div class="alert-sum-lbl">Danger</div>
      </div>
      <div class="alert-sum-divider"></div>
      <div class="alert-sum-item">
        <div class="alert-sum-num warn"><?= $counts['warning_count'] ?? 0 ?></div>
        <div class="alert-sum-lbl">Warning</div>
      </div>
      <div class="alert-sum-divider"></div>
      <div class="alert-sum-item">
        <div class="alert-sum-num small"><?= $counts['last_event'] ? date('M d, H:i', strtotime($counts['last_event'])) : '--' ?></div>
        <div class="alert-sum-lbl">Last Event</div>
      </div>
    </div>

    <!-- ALERT TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><i class='bx bx-bell'></i> Alert Event Log</div>
        <div class="export-wrap">
          <span class="panel-badge red"><?= $counts['total'] ?? 0 ?> events</span>
          <button class="export-btn" onclick="exportAlerts('csv')">
            <i class='bx bx-download'></i> CSV
          </button>
          <button class="export-btn" onclick="exportAlerts('json')">
            <i class='bx bx-code-alt'></i> JSON
          </button>
        </div>
      </div>

      <?php if ($alertHistory->num_rows === 0): ?>
        <div class="empty-state">
          <i class='bx bx-check-shield'></i>
          <p>No alert events recorded yet. System is operating normally.</p>
        </div>
      <?php else: ?>
        <table class="data-table" id="alert-table">
          <thead>
            <tr>
              <th>Date &amp; Time</th>
              <th>Node</th>
              <th>Soil Moisture (%)</th>
              <th>Rainfall (mm)</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $alertHistory->fetch_assoc()):
              $bc = $row['status'] === 'DANGER' ? 'danger' : 'warning';
            ?>
            <tr>
              <td class="mono"><?= $row['created_at'] ?></td>
              <td><?= htmlspecialchars($row['node_name'] ?? 'Node ' . $row['node_id']) ?></td>
              <td><?= $row['soil_moisture'] ?></td>
              <td><?= number_format($row['rainfall'], 2) ?></td>
              <td><span class="badge <?= $bc ?>"><?= $row['status'] ?></span></td>
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
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}, 1000);

function exportAlerts(format) {
  const rows = document.querySelectorAll('#alert-table tbody tr');
  const date = new Date().toISOString().slice(0,10);
  const filename = 'slopeguard_alerts_' + date;

  if (format === 'csv') {
    let csv = 'Date & Time,Node,Soil Moisture (%),Rainfall (mm),Status\n';
    rows.forEach(row => {
      const cells = row.querySelectorAll('td');
      csv += `"${cells[0].textContent}","${cells[1].textContent}",${cells[2].textContent},${cells[3].textContent},${cells[4].textContent.trim()}\n`;
    });
    download(csv, filename + '.csv', 'text/csv');
  } else {
    const data = [];
    rows.forEach(row => {
      const cells = row.querySelectorAll('td');
      data.push({
        datetime:     cells[0].textContent,
        node:         cells[1].textContent,
        soil_moisture:cells[2].textContent,
        rainfall:     cells[3].textContent,
        status:       cells[4].textContent.trim()
      });
    });
    download(JSON.stringify(data, null, 2), filename + '.json', 'application/json');
  }
}

function download(content, filename, mime) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type: mime }));
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>

</body>
</html>