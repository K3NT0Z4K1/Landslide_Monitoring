<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

$stmt = $conn->prepare("
  SELECT temperature, humidity, soil_moisture, rainfall, created_at
  FROM sensor_readings
  WHERE node_id = ?
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->bind_param("i", $node);
$stmt->execute();
$data = $stmt->get_result();
$latest = $data->fetch_assoc();
$data->data_seek(0);

$soil = $latest['soil_moisture'] ?? 0;
$rain = $latest['rainfall'] ?? 0;

$alert       = "NORMAL";
$alert_class = "normal";
$alert_msg   = "All sensor readings are within safe thresholds.";
$alert_icon  = "bx-check-shield";

if ($soil > 700 && $rain > 20) {
  $alert = "HIGH RISK";
  $alert_class = "danger";
  $alert_msg   = "Imminent landslide conditions detected. Evacuate at-risk zones immediately.";
  $alert_icon  = "bx-error";
} elseif ($soil > 500 && $rain > 10) {
  $alert = "WARNING";
  $alert_class = "warning";
  $alert_msg   = "Elevated soil moisture and rainfall detected. Monitor closely.";
  $alert_icon  = "bx-error-circle";
}

$node_labels = [
  1 => "Node 1 — Lower Slope A",
  2 => "Node 2 — Lower Slope B",
  3 => "Node 3 — Lower Slope C"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — GeoWatch</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" class="sidebar-logo-icon">
      <rect width="72" height="72" rx="14" fill="#1a3323"/>
      <polygon points="10,56 30,24 50,40 62,30 72,44 72,56" fill="#2d5a3a"/>
      <polygon points="4,56 24,32 44,56" fill="#3d7a50"/>
      <polygon points="30,56 50,30 72,56" fill="#2d5a3a"/>
      <line x1="0" y1="56" x2="72" y2="56" stroke="#5a9e6f" stroke-width="1.5"/>
      <path d="M 53 19 A 8 8 0 0 1 61 27" fill="none" stroke="#8cc4a0" stroke-width="2.5" stroke-linecap="round"/>
      <path d="M 49 15 A 14 14 0 0 1 65 29" fill="none" stroke="#8cc4a0" stroke-width="1.8" stroke-linecap="round" opacity="0.55"/>
      <circle cx="53" cy="19" r="2.5" fill="#8cc4a0"/>
    </svg>
    <div class="sidebar-logo-text">
      <h2>GeoWatch</h2>
      <span>Monitoring System</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <span class="nav-section-label">Main</span>
    <a href="index.php" class="nav-item active">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item">
      <i class='bx bx-map-alt'></i> Sensor Map
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <i class='bx bx-log-out'></i> Sign Out
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

  <header class="topbar">
    <div class="topbar-left">
      <h1>Dashboard</h1>
      <p>Welcome back, Admin &middot; <?= date('l, F j Y') ?></p>
    </div>
    <div class="topbar-right">
      <div class="topbar-time">
        <i class='bx bx-time-five'></i>
        <span id="clock"><?= date('H:i:s') ?></span>
      </div>
      <form method="GET" class="node-select">
        <i class='bx bx-radio-circle-marked'></i>
        <select name="node" onchange="this.form.submit()">
          <option value="1" <?= $node==1?'selected':'' ?>>Node 1</option>
          <option value="2" <?= $node==2?'selected':'' ?>>Node 2</option>
          <option value="3" <?= $node==3?'selected':'' ?>>Node 3</option>
        </select>
      </form>
    </div>
  </header>

  <!-- ALERT BANNER -->
  <div class="alert-banner <?= $alert_class ?> fade-in">
    <span class="alert-pulse"></span>
    <i class='bx <?= $alert_icon ?>'></i>
    <div>
      <strong><?= $alert ?></strong> &mdash; <?= $alert_msg ?>
    </div>
  </div>

  <div class="page-content">

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">
          <i class='bx bx-thermometer'></i> Temperature
        </div>
        <div class="stat-value" id="temp"><?= $latest['temperature'] ?? '--' ?></div>
        <div class="stat-unit">Degrees Celsius (°C)</div>
      </div>

      <div class="stat-card">
        <div class="stat-label">
          <i class='bx bx-droplet'></i> Humidity
        </div>
        <div class="stat-value" id="humidity"><?= $latest['humidity'] ?? '--' ?></div>
        <div class="stat-unit">Relative Humidity (%)</div>
      </div>

      <div class="stat-card <?= $soil > 700 ? 'danger-card' : ($soil > 500 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">
          <i class='bx bx-landscape'></i> Soil Moisture
        </div>
        <div class="stat-value <?= $soil > 700 ? 'danger' : ($soil > 500 ? 'warn' : 'ok') ?>" id="soil"><?= $latest['soil_moisture'] ?? '--' ?></div>
        <div class="stat-unit">ADC Reading</div>
      </div>

      <div class="stat-card <?= $rain > 20 ? 'danger-card' : ($rain > 10 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">
          <i class='bx bx-cloud-rain'></i> Rainfall
        </div>
        <div class="stat-value <?= $rain > 20 ? 'danger' : ($rain > 10 ? 'warn' : 'ok') ?>" id="rain"><?= $latest['rainfall'] ?? '--' ?></div>
        <div class="stat-unit">Millimeters (mm)</div>
      </div>

      <div class="stat-card <?= $alert_class === 'danger' ? 'danger-card' : ($alert_class === 'warning' ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">
          <i class='bx bx-shield-quarter'></i> Landslide Risk
        </div>
        <div class="stat-value risk-value <?= $alert_class === 'danger' ? 'danger' : ($alert_class === 'warning' ? 'warn' : 'ok') ?>" id="alert"><?= $alert ?></div>
        <div class="stat-unit"><?= $node_labels[$node] ?></div>
      </div>
    </div>

    <!-- CHARTS -->
    <div class="two-col">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <i class='bx bx-line-chart'></i> Temperature &amp; Humidity
          </div>
          <span class="panel-badge green">Live</span>
        </div>
        <div class="panel-body">
          <div class="chart-wrap"><canvas id="tempChart"></canvas></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <i class='bx bx-cloud-rain'></i> Rainfall
          </div>
          <span class="panel-badge green">Live</span>
        </div>
        <div class="panel-body">
          <div class="chart-wrap"><canvas id="rainChart"></canvas></div>
        </div>
      </div>
    </div>

    <div class="two-col">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <i class='bx bx-landscape'></i> Soil Moisture
          </div>
          <span class="panel-badge green">Live</span>
        </div>
        <div class="panel-body">
          <div class="chart-wrap"><canvas id="soilChart"></canvas></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <i class='bx bx-map-alt'></i> Sensor Locations
          </div>
          <span class="panel-badge green">3 nodes active</span>
        </div>
        <div id="map"></div>
      </div>
    </div>

    <!-- TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">
          <i class='bx bx-table'></i> Recent Sensor Readings
        </div>
        <span class="panel-badge green">Last 10 entries</span>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Temp (°C)</th>
            <th>Humidity (%)</th>
            <th>Soil Moisture</th>
            <th>Rainfall (mm)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $data->fetch_assoc()):
            $rs = $row['soil_moisture']; $rr = $row['rainfall'];
            $rb = ($rs > 700 && $rr > 20) ? 'danger' : (($rs > 500 && $rr > 10) ? 'warning' : 'normal');
            $rl = ($rs > 700 && $rr > 20) ? 'High Risk' : (($rs > 500 && $rr > 10) ? 'Warning' : 'Normal');
          ?>
          <tr>
            <td class="mono"><?= $row['created_at'] ?></td>
            <td><?= $row['temperature'] ?></td>
            <td><?= $row['humidity'] ?></td>
            <td><?= $row['soil_moisture'] ?></td>
            <td><?= $row['rainfall'] ?></td>
            <td><span class="badge <?= $rb ?>"><?= $rl ?></span></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <div class="level-guide">
        <div class="level-item">
          <div class="level-dot" style="background:#2d7a4f"></div> Normal — soil &le;500, rain &le;10 mm
        </div>
        <div class="level-item">
          <div class="level-dot" style="background:#d97706"></div> Warning — soil &gt;500, rain &gt;10 mm
        </div>
        <div class="level-item">
          <div class="level-dot" style="background:#c0392b"></div> High Risk — soil &gt;700, rain &gt;20 mm
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const NODE_ID = <?= $node ?>;
function updateClock() {
  const n = new Date();
  document.getElementById('clock').textContent = n.toTimeString().slice(0,8);
}
setInterval(updateClock, 1000);
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/map.js"></script>
<script src="../assets/js/charts.js"></script>
<script src="../assets/js/app.js"></script>

</body>
</html>