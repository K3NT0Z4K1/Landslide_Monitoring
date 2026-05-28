<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

/* Latest reading */
$stmt = $conn->prepare("
  SELECT * FROM sensor_readings
  WHERE node_id = ? ORDER BY created_at DESC LIMIT 1
");
$stmt->bind_param("i", $node);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();

/* Last 10 readings for table */
$stmt2 = $conn->prepare("
  SELECT temperature, humidity, soil_moisture, rainfall, status, created_at
  FROM sensor_readings
  WHERE node_id = ? ORDER BY created_at DESC LIMIT 10
");
$stmt2->bind_param("i", $node);
$stmt2->execute();
$readings = $stmt2->get_result();

/* Alert history last 100 */
$alertHistory = $conn->query("
  SELECT ah.*, n.node_name
  FROM alert_history ah
  LEFT JOIN sensor_nodes n ON n.id = ah.node_id
  ORDER BY ah.created_at DESC
  LIMIT 100
");

/* Alert counts */
$counts = $conn->query("
  SELECT
    COUNT(*) AS total,
    SUM(status = 'DANGER')  AS danger_count,
    SUM(status = 'WARNING') AS warning_count
  FROM alert_history
")->fetch_assoc();

/* -----------------------------------------------
   ALERT SUMMARY  — last 50 readings for digest
   Grouped to detect consecutive status runs
----------------------------------------------- */
$summaryRows = $conn->query("
  SELECT status, created_at
  FROM sensor_readings
  ORDER BY created_at DESC
  LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

/* Build compact run-length-encoded digest */
$runs = [];
foreach ($summaryRows as $r) {
  $s = $r['status'];
  $t = $r['created_at'];
  if (empty($runs) || $runs[count($runs)-1]['status'] !== $s) {
    $runs[] = ['status' => $s, 'count' => 1, 'start' => $t, 'end' => $t];
  } else {
    $runs[count($runs)-1]['count']++;
    $runs[count($runs)-1]['end'] = $t;
  }
}
/* Keep latest 6 runs for sidebar */
$runs = array_slice($runs, 0, 6);

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

$node_labels = [
  1 => "Node 1 — Lower Slope A",
  2 => "Node 2 — Lower Slope B",
  3 => "Node 3 — Lower Slope C"
];

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

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="34" height="34" viewBox="0 0 96 96" fill="none">
      <defs><clipPath id="sc2"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
      <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
      <g clip-path="url(#sc2)" opacity="0.65">
        <line x1="0"   y1="96"  x2="96"  y2="0"   stroke="#0e9fa0" stroke-width="7"/>
        <line x1="8"   y1="104" x2="104" y2="8"   stroke="#1ab8a0" stroke-width="6"/>
        <line x1="-8"  y1="88"  x2="88"  y2="-8"  stroke="#0a7a7b" stroke-width="6"/>
        <line x1="16"  y1="112" x2="112" y2="16"  stroke="#0e9fa0" stroke-width="5"/>
        <line x1="-16" y1="80"  x2="80"  y2="-16" stroke="#0a7a7b" stroke-width="4"/>
      </g>
      <g clip-path="url(#sc2)">
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
    <a href="index.php?node=<?= $node ?>" class="nav-item active">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item">
      <i class='bx bx-map-alt'></i> Sensor Map
    </a>
    <a href="alerts.php" class="nav-item">
      <i class='bx bx-bell'></i> Alert History
      <?php if ($unread_alerts > 0): ?>
        <span class="nav-alert-count"><?= $unread_alerts ?></span>
      <?php endif; ?>
    </a>

    <!-- =============================================
         ALERT SUMMARY WIDGET (small red box)
         Shows run-length digest of last 50 readings
    ============================================= -->
    <div class="sidebar-summary-card">
      <div class="sidebar-summary-header">
        <i class='bx bx-bar-chart-alt-2'></i>
        <span>Alert Summary</span>
        <span class="sidebar-summary-live-dot"></span>
      </div>
      <div class="sidebar-summary-body" id="sidebarSummaryBody">
        <?php if (empty($runs)): ?>
          <div class="summary-empty">No data yet</div>
        <?php else: ?>
          <?php foreach ($runs as $run):
            $sc = strtolower($run['status']);
            $sc = ($sc === 'safe') ? 'normal' : $sc;
            $icon = ($sc === 'danger') ? 'bx-error' : (($sc === 'warning') ? 'bx-error-circle' : 'bx-check-circle');
            /* Format time range */
            $tEnd   = date('H:i', strtotime($run['end']));
            $tStart = date('H:i', strtotime($run['start']));
            $range  = ($tEnd === $tStart) ? $tEnd : $tEnd . '–' . $tStart;
            $label  = ucfirst(strtolower($run['status']));
            $plural = $run['count'] > 1 ? $run['count'] . ' readings' : '1 reading';
          ?>
          <div class="summary-run <?= $sc ?>">
            <i class='bx <?= $icon ?>'></i>
            <div class="summary-run-body">
              <span class="summary-run-label"><?= $label ?></span>
              <span class="summary-run-meta"><?= $range ?> &middot; <?= $plural ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <!-- END ALERT SUMMARY WIDGET -->

  </nav>

  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <i class='bx bx-log-out'></i> Sign Out
    </a>
  </div>
</aside>

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

  <!-- ALERT BANNER -->
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

    <!-- =============================================
         SERIAL MONITOR PANEL (big red box)
         Mirrors exactly what Arduino IDE Serial Monitor
         shows — fetched live from DB via API
    ============================================= -->
    <div class="panel serial-monitor-panel">
      <div class="panel-header">
        <div class="panel-title">
          <i class='bx bx-terminal'></i> Serial Monitor
          <span class="serial-port-label">COM / USB &rarr; Master Node</span>
        </div>
        <div class="serial-monitor-controls">
          <span class="serial-baud">115200 baud</span>
          <span class="panel-badge teal" id="serialStatusBadge">Live</span>
          <button class="serial-btn" id="serialPauseBtn" onclick="toggleSerialPause()" title="Pause / Resume">
            <i class='bx bx-pause' id="serialPauseIcon"></i>
          </button>
          <button class="serial-btn" onclick="clearSerialMonitor()" title="Clear">
            <i class='bx bx-trash'></i>
          </button>
          <button class="serial-btn" onclick="downloadSerialLog()" title="Download log">
            <i class='bx bx-download'></i>
          </button>
        </div>
      </div>
      <div class="serial-monitor-body" id="serialMonitor">
        <div class="serial-line system">
          <span class="serial-ts">--:--:--</span>
          <span class="serial-txt">Connecting to SlopeGuard Master Node...</span>
        </div>
        <div class="serial-line system">
          <span class="serial-ts">--:--:--</span>
          <span class="serial-txt">Waiting for data...</span>
        </div>
      </div>
      <div class="serial-monitor-footer">
        <span id="serialLineCount">0 lines</span>
        <span class="serial-footer-sep">&middot;</span>
        <span id="serialLastReceived">No data yet</span>
        <span class="serial-footer-sep">&middot;</span>
        <label class="serial-autoscroll-label">
          <input type="checkbox" id="serialAutoscroll" checked> Autoscroll
        </label>
      </div>
    </div>
    <!-- END SERIAL MONITOR PANEL -->

    <!-- READINGS TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title"><i class='bx bx-table'></i> Recent Sensor Readings</div>
        <div class="export-wrap">
          <span class="panel-badge teal">Last 10 entries</span>
          <button class="export-btn" onclick="exportData('csv')">
            <i class='bx bx-download'></i> CSV
          </button>
          <button class="export-btn" onclick="exportData('json')">
            <i class='bx bx-code-alt'></i> JSON
          </button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Date &amp; Time</th>
            <th>Temp (°C)</th>
            <th>Humidity (%)</th>
            <th>Soil (%)</th>
            <th>Rainfall (mm)</th>
            <th>Status</th>
          </tr>
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

  </div><!-- /page-content -->
</div><!-- /main -->

<script>
const NODE_ID = <?= $node ?>;

/* ---- Clock ---- */
setInterval(() => {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}, 1000);

/* ---- Live stat cards ---- */
function loadLive() {
  fetch('../api/get_latest.php?node=' + NODE_ID)
    .then(r => r.json())
    .then(d => {
      if (!d) return;
      document.getElementById('temp').textContent     = parseFloat(d.temperature).toFixed(1);
      document.getElementById('humidity').textContent = parseFloat(d.humidity).toFixed(1);
      document.getElementById('soil').textContent     = d.soil_moisture;
      document.getElementById('rain').textContent     = parseFloat(d.rainfall).toFixed(2);
      document.getElementById('risk').textContent     = d.status;
    })
    .catch(e => console.error(e));
}
setInterval(loadLive, 5000);

/* ================================================================
   SERIAL MONITOR
   Polls /api/get_serial_log.php which returns last N rows,
   then renders them exactly like Arduino IDE Serial Monitor output.
================================================================ */
let serialPaused    = false;
let serialLineCount = 0;
let serialLog       = [];       // full log buffer
let serialLastId    = 0;        // track last DB row id to append only new lines
const SERIAL_MAX    = 500;      // max lines kept in DOM

function toggleSerialPause() {
  serialPaused = !serialPaused;
  const icon = document.getElementById('serialPauseIcon');
  const badge = document.getElementById('serialStatusBadge');
  icon.className = serialPaused ? 'bx bx-play' : 'bx bx-pause';
  badge.textContent = serialPaused ? 'Paused' : 'Live';
  badge.style.background = serialPaused ? 'var(--warn-bg)' : '';
  badge.style.color      = serialPaused ? 'var(--warn)' : '';
}

function clearSerialMonitor() {
  document.getElementById('serialMonitor').innerHTML = '';
  serialLog = [];
  serialLineCount = 0;
  updateSerialFooter();
}

function downloadSerialLog() {
  const text = serialLog.join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
  a.download = 'slopeguard_serial_' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.txt';
  a.click();
  URL.revokeObjectURL(a.href);
}

function updateSerialFooter() {
  document.getElementById('serialLineCount').textContent = serialLineCount + ' lines';
}

/**
 * Append a single line to the serial monitor DOM.
 * Mirrors Arduino IDE format:
 *   --------------------
 *   Received : 1,22.30,59.00,0,0.00,SAFE
 *   RSSI     : -67
 *   Node ID  : 1
 *   Temp     : 22.30 C
 *   Humidity : 59.00 %
 *   Soil     : 0%
 *   Rain     : 0.00 mm
 *   Status   : SAFE
 *   Sending to server...
 *   HTTP Response : 200
 *   Server reply  : OK
 */
function appendSerialLines(entries) {
  const monitor = document.getElementById('serialMonitor');
  const autoscroll = document.getElementById('serialAutoscroll').checked;

  /* Remove placeholder lines on first real data */
  const placeholders = monitor.querySelectorAll('.serial-line.system');
  if (placeholders.length && entries.length) {
    placeholders.forEach(p => p.remove());
  }

  entries.forEach(row => {
    const ts = row.time || '--:--:--';
    const t  = parseFloat(row.temperature).toFixed(2);
    const h  = parseFloat(row.humidity).toFixed(2);
    const s  = row.soil_moisture;
    const r  = parseFloat(row.rainfall).toFixed(2);
    const st = row.status;
    const rssi = row.rssi || 'N/A';
    const raw  = row.raw_packet || `${row.node_id},${t},${h},${s},${r},${st}`;

    const lines = [
      { cls: 'sep',    txt: '--------------------' },
      { cls: 'recv',   txt: `Received : ${raw}` },
      { cls: 'meta',   txt: `RSSI     : ${rssi}` },
      { cls: 'field',  txt: `Node ID  : ${row.node_id}` },
      { cls: 'field',  txt: `Temp     : ${t} C` },
      { cls: 'field',  txt: `Humidity : ${h} %` },
      { cls: 'field',  txt: `Soil     : ${s}%` },
      { cls: 'field',  txt: `Rain     : ${r} mm` },
      { cls: st === 'DANGER' ? 'status-danger' : (st === 'WARNING' ? 'status-warn' : 'status-safe'),
        txt: `Status   : ${st}` },
      { cls: 'sys',    txt: 'Sending to server...' },
      { cls: 'ok',     txt: 'HTTP Response : 200' },
      { cls: 'ok',     txt: `Server reply  : OK` },
    ];

    lines.forEach(l => {
      const div = document.createElement('div');
      div.className = `serial-line ${l.cls}`;

      const tsSpan = document.createElement('span');
      tsSpan.className = 'serial-ts';
      tsSpan.textContent = ts;

      const txtSpan = document.createElement('span');
      txtSpan.className = 'serial-txt';
      txtSpan.textContent = l.txt;

      div.appendChild(tsSpan);
      div.appendChild(txtSpan);
      monitor.appendChild(div);

      serialLog.push(`[${ts}] ${l.txt}`);
      serialLineCount++;

      /* Trim DOM if too long */
      while (monitor.children.length > SERIAL_MAX) {
        monitor.removeChild(monitor.firstChild);
      }
    });

    /* Update footer */
    const now = new Date();
    document.getElementById('serialLastReceived').textContent =
      'Last: ' + now.toTimeString().slice(0,8);
  });

  updateSerialFooter();

  if (autoscroll && !serialPaused) {
    monitor.scrollTop = monitor.scrollHeight;
  }
}

/* Poll the API for new rows */
function pollSerial() {
  if (serialPaused) return;

  fetch('../api/get_serial_log.php?node=' + NODE_ID + '&after_id=' + serialLastId + '&limit=20')
    .then(r => r.json())
    .then(rows => {
      if (!Array.isArray(rows) || rows.length === 0) return;
      /* Update cursor */
      serialLastId = rows[rows.length - 1].id || serialLastId;
      appendSerialLines(rows);
    })
    .catch(e => {
      /* Show connection error line once per failure */
      if (!document._serialErrShown) {
        document._serialErrShown = true;
        appendSerialLines([]);
        const monitor = document.getElementById('serialMonitor');
        const div = document.createElement('div');
        div.className = 'serial-line error';
        div.innerHTML = `<span class="serial-ts">${new Date().toTimeString().slice(0,8)}</span><span class="serial-txt">ERROR: Cannot reach API — ${e.message}</span>`;
        monitor.appendChild(div);
        if (document.getElementById('serialAutoscroll').checked) monitor.scrollTop = monitor.scrollHeight;
        setTimeout(() => { document._serialErrShown = false; }, 10000);
      }
    });
}

/* Initial load — fetch last 30 rows so monitor isn't empty on page load */
function initSerial() {
  fetch('../api/get_serial_log.php?node=' + NODE_ID + '&limit=30')
    .then(r => r.json())
    .then(rows => {
      if (!Array.isArray(rows) || rows.length === 0) return;
      serialLastId = rows[rows.length - 1].id || 0;
      appendSerialLines(rows);
    })
    .catch(() => {});
}

initSerial();
setInterval(pollSerial, 5000);   /* Poll every 5 seconds */

/* ================================================================
   ALERT SUMMARY SIDEBAR — refresh via API every 30 s
================================================================ */
function refreshSummary() {
  fetch('../api/get_alert_summary.php?limit=50')
    .then(r => r.json())
    .then(runs => {
      if (!Array.isArray(runs) || runs.length === 0) return;
      const body = document.getElementById('sidebarSummaryBody');
      if (!body) return;
      body.innerHTML = '';
      runs.slice(0, 6).forEach(run => {
        const sc   = run.status === 'SAFE' ? 'normal' : run.status.toLowerCase();
        const icon = sc === 'danger' ? 'bx-error' : (sc === 'warning' ? 'bx-error-circle' : 'bx-check-circle');
        const label = run.status.charAt(0) + run.status.slice(1).toLowerCase();
        const plural = run.count > 1 ? run.count + ' readings' : '1 reading';
        body.innerHTML += `
          <div class="summary-run ${sc}">
            <i class='bx ${icon}'></i>
            <div class="summary-run-body">
              <span class="summary-run-label">${label}</span>
              <span class="summary-run-meta">${run.time_range} &middot; ${plural}</span>
            </div>
          </div>`;
      });
    })
    .catch(() => {});
}

setInterval(refreshSummary, 30000);

/* ================================================================
   EXPORT
================================================================ */
function exportData(format) {
  fetch('../api/get_history.php?node=' + NODE_ID + '&limit=20')
    .then(r => r.json())
    .then(data => {
      const date = new Date().toISOString().slice(0,10);
      const filename = 'slopeguard_node' + NODE_ID + '_' + date;
      if (format === 'csv') {
        let csv = 'Date & Time,Temperature (°C),Humidity (%),Soil Moisture (%),Rainfall (mm),Status\n';
        data.forEach(r => {
          csv += `"${r.datetime}",${r.temperature},${r.humidity},${r.soil_moisture},${r.rainfall},${r.status}\n`;
        });
        download(csv, filename + '.csv', 'text/csv');
      } else {
        download(JSON.stringify(data, null, 2), filename + '.json', 'application/json');
      }
    });
}

function download(content, filename, mime) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([content], { type: mime }));
  a.download = filename;
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/charts.js"></script>

</body>
</html>
