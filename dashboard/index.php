<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

/* Latest reading */
$stmt = $conn->prepare("SELECT * FROM sensor_readings WHERE node_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $node);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();

/* Last 10 readings for table */
$stmt2 = $conn->prepare("SELECT temperature, humidity, soil_moisture, rainfall, status, created_at FROM sensor_readings WHERE node_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt2->bind_param("i", $node);
$stmt2->execute();
$readings = $stmt2->get_result();

/* Alert counts */
$counts = $conn->query("SELECT COUNT(*) AS total, SUM(status='DANGER') AS danger_count, SUM(status='WARNING') AS warning_count FROM alert_history")->fetch_assoc();

$soil   = $latest['soil_moisture'] ?? 0;
$rain   = $latest['rainfall']      ?? 0;
$status = $latest['status']        ?? 'SAFE';

$alert_class = $status === 'DANGER' ? 'danger' : ($status === 'WARNING' ? 'warning' : 'normal');
$alert_icon  = $status === 'DANGER' ? 'bx-error' : ($status === 'WARNING' ? 'bx-error-circle' : 'bx-check-shield');
$alert_msg   = $status === 'DANGER'
  ? 'Imminent landslide conditions detected. Evacuate at-risk zones immediately.'
  : ($status === 'WARNING'
    ? 'Elevated soil moisture and rainfall detected. Monitor closely.'
    : 'All sensor readings are within safe thresholds.');

$node_labels = [1=>"Node 1 — Lower Slope A",2=>"Node 2 — Lower Slope B",3=>"Node 3 — Lower Slope C"];
$unread_alerts = $counts['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — SlopeGuard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php $active_page = 'dashboard'; require_once "_sidebar.php"; ?>

<!-- MAIN -->
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
      <form method="GET" class="node-select-wrap">
        <i class='bx bx-radio-circle-marked'></i>
        <select name="node" onchange="this.form.submit()">
          <option value="1" <?= $node==1?'selected':'' ?>>Node 1</option>
          <option value="2" <?= $node==2?'selected':'' ?>>Node 2</option>
          <option value="3" <?= $node==3?'selected':'' ?>>Node 3</option>
        </select>
      </form>
    </div>
  </header>

  <div class="alert-banner <?= $alert_class ?> fade-in">
    <span class="alert-pulse"></span>
    <i class='bx <?= $alert_icon ?>'></i>
    <div><strong><?= $status ?></strong> &mdash; <?= $alert_msg ?></div>
  </div>

  <div class="page-content">

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label"><i class='bx bx-thermometer'></i> Temperature</div>
        <div class="stat-value" id="temp"><?= $latest['temperature'] ?? '--' ?></div>
        <div class="stat-unit">Degrees Celsius (°C)</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><i class='bx bx-droplet'></i> Humidity</div>
        <div class="stat-value" id="humidity"><?= $latest['humidity'] ?? '--' ?></div>
        <div class="stat-unit">Relative Humidity (%)</div>
      </div>
      <div class="stat-card <?= $soil > 80 ? 'danger-card' : ($soil > 50 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label"><i class='bx bx-landscape'></i> Soil Moisture</div>
        <div class="stat-value <?= $soil > 80 ? 'danger' : ($soil > 50 ? 'warn' : 'ok') ?>" id="soil"><?= $latest['soil_moisture'] ?? '--' ?></div>
        <div class="stat-unit">Percentage (%)</div>
      </div>
      <div class="stat-card <?= $rain > 25 ? 'danger-card' : ($rain > 10 ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label"><i class='bx bx-cloud-rain'></i> Rainfall</div>
        <div class="stat-value <?= $rain > 25 ? 'danger' : ($rain > 10 ? 'warn' : 'ok') ?>" id="rain"><?= $latest['rainfall'] ?? '--' ?></div>
        <div class="stat-unit">Millimeters / hour (mm)</div>
      </div>
      <div class="stat-card <?= $alert_class === 'danger' ? 'danger-card' : ($alert_class === 'warning' ? 'warn-card' : 'ok-card') ?>">
        <div class="stat-label"><i class='bx bx-shield-quarter'></i> Landslide Risk</div>
        <div class="stat-value risk <?= $alert_class === 'danger' ? 'danger' : ($alert_class === 'warning' ? 'warn' : 'ok') ?>" id="risk"><?= $status ?></div>
        <div class="stat-unit"><?= $node_labels[$node] ?></div>
      </div>
    </div>

    <!-- CHARTS -->
    <div class="two-col">
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-line-chart'></i> Temperature &amp; Humidity</div>
          <span class="panel-badge teal">Live</span>
        </div>
        <div class="panel-body"><div class="chart-wrap"><canvas id="tempChart"></canvas></div></div>
      </div>
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-cloud-rain'></i> Rainfall</div>
          <span class="panel-badge teal">Live</span>
        </div>
        <div class="panel-body"><div class="chart-wrap"><canvas id="rainChart"></canvas></div></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><i class='bx bx-landscape'></i> Soil Moisture</div>
        <span class="panel-badge teal">Live</span>
      </div>
      <div class="panel-body"><div class="chart-wrap"><canvas id="soilChart"></canvas></div></div>
    </div>

    <!-- READINGS TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><i class='bx bx-table'></i> Recent Sensor Readings</div>
        <div class="export-wrap">
          <span class="panel-badge teal">Last 10 entries</span>
          <button class="export-btn" onclick="exportData('csv')"><i class='bx bx-download'></i> CSV</button>
          <button class="export-btn" onclick="exportData('json')"><i class='bx bx-code-alt'></i> JSON</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr><th>Date &amp; Time</th><th>Temp (°C)</th><th>Humidity (%)</th><th>Soil (%)</th><th>Rainfall (mm)</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php while ($row = $readings->fetch_assoc()):
            $s  = $row['status'];
            $bc = $s === 'DANGER' ? 'danger' : ($s === 'WARNING' ? 'warning' : 'normal');
          ?>
          <tr>
            <td class="mono"><?= $row['created_at'] ?></td>
            <td><?= $row['temperature'] ?></td>
            <td><?= $row['humidity'] ?></td>
            <td><?= $row['soil_moisture'] ?></td>
            <td><?= $row['rainfall'] ?></td>
            <td><span class="badge <?= $bc ?>"><?= $s ?></span></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <div class="level-guide">
        <div class="level-item"><div class="level-dot" style="background:#0e9fa0"></div> Safe — soil &le;50%, rain &le;10 mm</div>
        <div class="level-item"><div class="level-dot" style="background:#d97706"></div> Warning — soil &gt;50%, rain &gt;10 mm</div>
        <div class="level-item"><div class="level-dot" style="background:#c0392b"></div> Danger — soil &gt;80%, rain &gt;25 mm</div>
      </div>
    </div>

  </div>
</div>

<script>
const NODE_ID = <?= $node ?>;
setInterval(() => { document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8); }, 1000);

function loadLive() {
  fetch('../api/get_latest.php?node=' + NODE_ID).then(r => r.json()).then(d => {
    if (!d) return;
    document.getElementById('temp').textContent     = parseFloat(d.temperature).toFixed(1);
    document.getElementById('humidity').textContent = parseFloat(d.humidity).toFixed(1);
    document.getElementById('soil').textContent     = d.soil_moisture;
    document.getElementById('rain').textContent     = parseFloat(d.rainfall).toFixed(2);
    document.getElementById('risk').textContent     = d.status;
  }).catch(e => console.error(e));
}
setInterval(loadLive, 5000);

function exportData(format) {
  fetch('../api/get_history.php?node=' + NODE_ID + '&limit=20').then(r => r.json()).then(data => {
    const date = new Date().toISOString().slice(0,10);
    const filename = 'slopeguard_node' + NODE_ID + '_' + date;
    if (format === 'csv') {
      let csv = 'Date & Time,Temperature (°C),Humidity (%),Soil Moisture (%),Rainfall (mm),Status\n';
      data.forEach(r => { csv += `"${r.datetime}",${r.temperature},${r.humidity},${r.soil_moisture},${r.rainfall},${r.status}\n`; });
      download(csv, filename + '.csv', 'text/csv');
    } else { download(JSON.stringify(data, null, 2), filename + '.json', 'application/json'); }
  });
}
function download(content, filename, mime) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type: mime }));
  a.download = filename; a.click(); URL.revokeObjectURL(a.href);
}
</script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/charts.js"></script>
</body>
</html>
