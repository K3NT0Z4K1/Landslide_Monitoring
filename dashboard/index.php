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

if ($soil > 700 && $rain > 20) {
  $alert = "HIGH RISK";
  $alert_class = "danger";
  $alert_msg = "Imminent landslide conditions detected. Evacuate at-risk zones immediately.";
} elseif ($soil > 500 && $rain > 10) {
  $alert = "WARNING";
  $alert_class = "warning";
  $alert_msg = "Elevated soil moisture and rainfall detected. Monitor closely.";
}

$node_labels = [1 => "Node 1 — Lower Slope A", 2 => "Node 2 — Lower Slope B", 3 => "Node 3 — Lower Slope C"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — Landslide Monitoring</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">⛰️</span>
    <h2>Landslide<br>Monitor</h2>
    <span>Davao Region</span>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php" class="nav-item active">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="map.php" class="nav-item">
      <span class="nav-icon">🗺</span> Sensor Map
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <span>🚪</span> Logout
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<div class="main">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <h1>Dashboard</h1>
      <p>Welcome back, Admin &middot; <?= date('D, M d Y') ?></p>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="clock"><?= date('H:i:s') ?></span>
      <form method="GET" class="node-select">
        <span>Node:</span>
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
    <strong><?= $alert ?></strong> — <?= $alert_msg ?>
  </div>

  <!-- PAGE CONTENT -->
  <div class="page-content">

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">🌡 Temperature</div>
        <div class="stat-value" id="temp"><?= $latest['temperature'] ?? '--' ?></div>
        <div class="stat-unit">degrees Celsius</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">💧 Humidity</div>
        <div class="stat-value" id="humidity"><?= $latest['humidity'] ?? '--' ?></div>
        <div class="stat-unit">percent (%)</div>
      </div>
      <div class="stat-card <?= $soil > 700 ? 'danger-card' : ($soil > 500 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">🌱 Soil Moisture</div>
        <div class="stat-value <?= $soil > 700 ? 'danger' : ($soil > 500 ? 'warn' : 'ok') ?>" id="soil"><?= $latest['soil_moisture'] ?? '--' ?></div>
        <div class="stat-unit">ADC reading</div>
      </div>
      <div class="stat-card <?= $rain > 20 ? 'danger-card' : ($rain > 10 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">🌧 Rainfall</div>
        <div class="stat-value <?= $rain > 20 ? 'danger' : ($rain > 10 ? 'warn' : 'ok') ?>" id="rain"><?= $latest['rainfall'] ?? '--' ?></div>
        <div class="stat-unit">millimeters (mm)</div>
      </div>
      <div class="stat-card <?= $alert_class === 'danger' ? 'danger-card' : ($alert_class === 'warning' ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label">🚨 Landslide Risk</div>
        <div class="stat-value <?= $alert_class === 'danger' ? 'danger' : ($alert_class === 'warning' ? 'warn' : 'ok') ?>" style="font-size:20px;font-family:'DM Sans',sans-serif;font-weight:600;letter-spacing:0" id="alert"><?= $alert ?></div>
        <div class="stat-unit"><?= $node_labels[$node] ?></div>
      </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="two-col">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-icon">🌡</div> Temperature & Humidity
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
            <div class="panel-icon">🌧</div> Rainfall
          </div>
          <span class="panel-badge green">Live</span>
        </div>
        <div class="panel-body">
          <div class="chart-wrap"><canvas id="rainChart"></canvas></div>
        </div>
      </div>
    </div>

    <!-- SOIL CHART + MAP -->
    <div class="two-col">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">
            <div class="panel-icon">🌱</div> Soil Moisture
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
            <div class="panel-icon">🗺</div> Sensor Locations
          </div>
          <span class="panel-badge green">3 nodes active</span>
        </div>
        <div id="map"></div>
      </div>
    </div>

    <!-- RECENT DATA TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">
          <div class="panel-icon">📋</div> Recent Sensor Readings
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
          <?php while($row = $data->fetch_assoc()):
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
        <div class="level-item"><div class="level-dot" style="background:#2d7a4f"></div> Normal (soil ≤500, rain ≤10mm)</div>
        <div class="level-item"><div class="level-dot" style="background:#d97706"></div> Warning (soil >500, rain >10mm)</div>
        <div class="level-item"><div class="level-dot" style="background:#c0392b"></div> High Risk (soil >700, rain >20mm)</div>
      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main -->

<script>
const NODE_ID = <?= $node ?>;

// Clock
function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent =
    now.toTimeString().slice(0,8);
}
setInterval(updateClock, 1000);
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/map.js"></script>
<script src="../assets/js/charts.js"></script>
<script src="../assets/js/app.js"></script>

</body>
</html>