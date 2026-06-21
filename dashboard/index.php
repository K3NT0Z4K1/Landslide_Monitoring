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

$node_labels = [1 => "Node 1 — Lower Slope A", 2 => "Node 2 — Lower Slope B", 3 => "Node 3 — Lower Slope C"];
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
  <style>
    /* ── Dashboard top row: stat cards + serial monitor ── */
    .dashboard-top-row {
      display: grid;
      /* Stat cards: fixed 380px. Serial monitor: fills rest, max 680px so it never dominates */
      grid-template-columns: 380px minmax(0, 680px);
      gap: 18px;
      align-items: start;
    }

    /* Stat cards stacked vertically */
    .stat-col {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .stat-col .stat-card {
      padding: 14px 18px;
      border-radius: var(--r);
    }

    .stat-col .stat-card::before {
      border-radius: var(--r) var(--r) 0 0;
    }

    .stat-col .stat-label {
      margin-bottom: 6px;
      font-size: 10.5px;
    }

    .stat-col .stat-value {
      font-size: 26px;
    }

    .stat-col .stat-value.risk {
      font-size: 15px;
    }

    .stat-col .stat-unit {
      font-size: 11px;
      margin-top: 3px;
    }

    /* Serial monitor — tall enough to read, never unbounded */
    .ide-wrap--inline {
      margin: 0 !important;
      min-height: 0;
      height: 420px;
      max-height: 420px;
      width: 100%;
      display: flex;
      flex-direction: column;
    }

    .ide-wrap--inline .ide-output {
      font-size: 11.5px;
      line-height: 1.6;
    }

    .ide-wrap--inline .ide-line {
      padding: 0 12px;
      min-height: 19px;
    }

    .ide-wrap--inline .ide-ts {
      min-width: 54px;
      width: 54px;
      font-size: 10px;
    }

    /* Tablet: collapse to single column, stat cards go horizontal */
    @media (max-width: 960px) {
      .dashboard-top-row {
        grid-template-columns: 1fr;
      }

      .stat-col {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
      }

      .ide-wrap--inline {
        height: 320px;
        max-height: 320px;
      }
    }

    /* Mobile: two-column stat cards, shorter serial */
    @media (max-width: 500px) {
      .stat-col {
        grid-template-columns: repeat(2, 1fr);
      }

      .ide-wrap--inline {
        height: 260px;
        max-height: 260px;
      }
    }

    /* ── Desktop (≥1024px) only: balanced 2x2 stat grid + serial monitor ──
       Matches reference layout: Temp/Rainfall row 1, Humidity/Risk row 2,
       Soil Moisture alone on row 3 (last cell adapts), Serial Monitor fills
       the full height of that block on the right.
       Everything below 1024px (base rules above + the two media blocks
       above this one) is completely untouched. */
    @media (min-width: 1024px) {
      .dashboard-top-row {
        grid-template-columns: minmax(300px, 560px) minmax(360px, 1fr);
        align-items: stretch;
      }

      .stat-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: repeat(3, 1fr);
        gap: 12px;
      }

      /* Visual reorder only — DOM order, IDs, and JS bindings are untouched.
         CSS `order` lets grid auto-placement land each card in the cell
         shown in the reference image without moving any markup. */
      .stat-col .stat-card:nth-child(1) { order: 1; } /* Temperature */
      .stat-col .stat-card:nth-child(2) { order: 3; } /* Humidity */
      .stat-col .stat-card:nth-child(3) { order: 5; } /* Soil Moisture */
      .stat-col .stat-card:nth-child(4) { order: 2; } /* Rainfall */
      .stat-col .stat-card:nth-child(5) { order: 4; } /* Landslide Risk */

      .stat-col .stat-card {
        padding: 16px 18px;
      }

      .stat-col .stat-value {
        font-size: 24px;
      }

      .stat-col .stat-value.risk {
        font-size: 15px;
      }
    }
  </style>
</head>

<body>

  <?php $active_page = 'dashboard';
  require_once "_sidebar.php"; ?>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
          <i class='bx bx-menu'></i>
        </button>
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
            <option value="1" <?= $node == 1 ? 'selected' : '' ?>>Node 1</option>
            <option value="2" <?= $node == 2 ? 'selected' : '' ?>>Node 2</option>
            <option value="3" <?= $node == 3 ? 'selected' : '' ?>>Node 3</option>
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

      <!-- STAT CARDS + SERIAL MONITOR -->
      <div class="dashboard-top-row">

        <!-- STAT CARDS (stacked vertically, 5 cards) -->
        <div class="stat-col">
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

        <!-- INLINE SERIAL MONITOR -->
        <div class="ide-wrap ide-wrap--inline">

          <!-- Title bar -->
          <div class="ide-titlebar">
            <div class="ide-titlebar-left">
              <div class="ide-dot red"></div>
              <div class="ide-dot yellow"></div>
              <div class="ide-dot green"></div>
              <span class="ide-title-text">Serial Monitor</span>
            </div>
            <div class="ide-titlebar-right">
              <span class="ide-port-chip">
                <i class='bx bx-usb'></i>
                COM3 / Master Node
              </span>
            </div>
          </div>

          <!-- Toolbar -->
          <div class="ide-toolbar">
            <div class="ide-toolbar-left">
              <label class="ide-check-label">
                <input type="checkbox" id="autoscrollCheck" checked>
                <span>Autoscroll</span>
              </label>
              <label class="ide-check-label">
                <input type="checkbox" id="timestampCheck" checked>
                <span>Timestamp</span>
              </label>
            </div>
            <div class="ide-toolbar-right">
              <div class="ide-live-chip" id="liveChip">
                <span class="ide-live-dot" id="liveDot"></span>
                <span id="liveLabel">Live</span>
              </div>
              <button class="ide-btn" onclick="togglePause()" title="Pause / Resume">
                <i class='bx bx-pause' id="pauseIcon"></i>
              </button>
              <button class="ide-btn" onclick="clearMonitor()" title="Clear">
                <i class='bx bx-trash'></i>
              </button>
              <button class="ide-btn" onclick="downloadLog()" title="Save log">
                <i class='bx bx-download'></i>
              </button>
            </div>
          </div>

          <!-- Scrollable output -->
          <div class="ide-output" id="ideOutput">
            <div class="ide-line sys">
              <span class="ide-ts">--:--:--</span>
              <span class="ide-txt">SlopeGuard Serial Monitor ready...</span>
            </div>
            <div class="ide-line sys">
              <span class="ide-ts">--:--:--</span>
              <span class="ide-txt">Waiting for LoRa packets...</span>
            </div>
          </div>

          <!-- Status bar -->
          <div class="ide-statusbar">
            <span id="lineCountEl">0 lines</span>
            <span class="ide-status-sep">|</span>
            <span id="lastRxEl">No data received</span>
            <span class="ide-status-sep">|</span>
            <span>Node <?= $node ?></span>
            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
              <span class="ide-status-sep">|</span>
              <span id="rxCountEl">RX: 0</span>
              <span class="ide-status-sep">|</span>
              <span>115200 baud</span>
            </div>
          </div>

        </div><!-- /ide-wrap--inline -->

      </div><!-- /dashboard-top-row -->

      <!-- CHARTS -->
      <div class="two-col">
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title"><i class='bx bx-line-chart'></i> Temperature &amp; Humidity</div>
            <span class="panel-badge teal">Live</span>
          </div>
          <div class="panel-body">
            <div class="chart-wrap"><canvas id="tempChart"></canvas></div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title"><i class='bx bx-cloud-rain'></i> Rainfall</div>
            <span class="panel-badge teal">Live</span>
          </div>
          <div class="panel-body">
            <div class="chart-wrap"><canvas id="rainChart"></canvas></div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-landscape'></i> Soil Moisture</div>
          <span class="panel-badge teal">Live</span>
        </div>
        <div class="panel-body">
          <div class="chart-wrap"><canvas id="soilChart"></canvas></div>
        </div>
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
          <div class="level-item">
            <div class="level-dot" style="background:#0e9fa0"></div> Safe — soil &le;50%, rain &le;10 mm
          </div>
          <div class="level-item">
            <div class="level-dot" style="background:#d97706"></div> Warning — soil &gt;50%, rain &gt;10 mm
          </div>
          <div class="level-item">
            <div class="level-dot" style="background:#c0392b"></div> Danger — soil &gt;80%, rain &gt;25 mm
          </div>
        </div>
      </div>

    </div>
  </div>

  <script>
    const NODE_ID = <?= $node ?>;
    setInterval(() => {
      document.getElementById('clock').textContent = new Date().toTimeString().slice(0, 8);
    }, 1000);

    function loadLive() {
      fetch('../api/get_latest.php?node=' + NODE_ID).then(r => r.json()).then(d => {
        if (!d) return;
        document.getElementById('temp').textContent = parseFloat(d.temperature).toFixed(1);
        document.getElementById('humidity').textContent = parseFloat(d.humidity).toFixed(1);
        document.getElementById('soil').textContent = d.soil_moisture;
        document.getElementById('rain').textContent = parseFloat(d.rainfall).toFixed(2);
        document.getElementById('risk').textContent = d.status;
      }).catch(e => console.error(e));
    }
    setInterval(loadLive, 5000);

    function exportData(format) {
      fetch('../api/get_history.php?node=' + NODE_ID + '&limit=20').then(r => r.json()).then(data => {
        const date = new Date().toISOString().slice(0, 10);
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
      a.href = URL.createObjectURL(new Blob([content], {
        type: mime
      }));
      a.download = filename;
      a.click();
      URL.revokeObjectURL(a.href);
    }
  </script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="../assets/js/charts.js"></script>
  

  <script>
    /* ── Inline Serial Monitor ── */
    let serialPaused = false;
    let serialLastId = 0;
    let serialLineCount = 0;
    let serialRxCount = 0;
    let serialLogBuffer = [];
    const SERIAL_MAX_LINES = 400;

    function togglePause() {
      serialPaused = !serialPaused;
      document.getElementById('pauseIcon').className = serialPaused ? 'bx bx-play' : 'bx bx-pause';
      document.getElementById('liveLabel').textContent = serialPaused ? 'Paused' : 'Live';
      const chip = document.getElementById('liveChip');
      const dot = document.getElementById('liveDot');
      chip.style.color = serialPaused ? '#d97706' : '';
      chip.style.borderColor = serialPaused ? 'rgba(217,119,6,0.3)' : '';
      chip.style.background = serialPaused ? 'rgba(217,119,6,0.1)' : '';
      dot.style.background = serialPaused ? '#d97706' : '';
      dot.style.animationPlayState = serialPaused ? 'paused' : 'running';
    }

    function clearMonitor() {
      document.getElementById('ideOutput').innerHTML = '';
      serialLogBuffer = [];
      serialLineCount = 0;
      serialRxCount = 0;
      updateSerialStatus();
    }

    function downloadLog() {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([serialLogBuffer.join('\n')], {
        type: 'text/plain'
      }));
      a.download = 'slopeguard_serial_node' + NODE_ID + '_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.txt';
      a.click();
      URL.revokeObjectURL(a.href);
    }

    function updateSerialStatus() {
      document.getElementById('lineCountEl').textContent = serialLineCount + ' lines';
      document.getElementById('rxCountEl').textContent = 'RX: ' + serialRxCount;
    }

    function buildSerialLines(row) {
      const t = parseFloat(row.temperature).toFixed(2);
      const h = parseFloat(row.humidity).toFixed(2);
      const s = row.soil_moisture;
      const r = parseFloat(row.rainfall).toFixed(2);
      const st = row.status;
      const rssi = (row.rssi !== null && row.rssi !== undefined && row.rssi !== '') ? row.rssi : 'N/A';
      const raw = row.raw_packet || `${row.node_id},${t},${h},${s},${r},${st}`;
      const stCls = st === 'DANGER' ? 'danger' : (st === 'WARNING' ? 'warn' : 'safe');
      return [{
          cls: 'sep',
          txt: '--------------------'
        },
        {
          cls: 'recv',
          txt: `Received : ${raw}`
        },
        {
          cls: 'meta',
          txt: `RSSI     : ${rssi}`
        },
        {
          cls: 'field',
          txt: `Node ID  : ${row.node_id}`
        },
        {
          cls: 'field',
          txt: `Temp     : ${t} C`
        },
        {
          cls: 'field',
          txt: `Humidity : ${h} %`
        },
        {
          cls: 'field',
          txt: `Soil     : ${s}%`
        },
        {
          cls: 'field',
          txt: `Rain     : ${r} mm`
        },
        {
          cls: stCls,
          txt: `Status   : ${st}`
        },
        {
          cls: 'sys',
          txt: 'Sending to server...'
        },
        {
          cls: 'ok',
          txt: 'HTTP Response : 200'
        },
        {
          cls: 'ok',
          txt: 'Server reply  : OK'
        },
      ];
    }

    function appendSerialEntries(entries) {
      if (!entries.length) return;
      const output = document.getElementById('ideOutput');
      const showTs = document.getElementById('timestampCheck').checked;
      const autoscroll = document.getElementById('autoscrollCheck').checked;

      output.querySelectorAll('.ide-line.sys').forEach(el => {
        if (el.querySelector('.ide-ts')?.textContent === '--:--:--') el.remove();
      });

      entries.forEach(row => {
        const ts = row.time || '--:--:--';
        serialRxCount++;
        buildSerialLines(row).forEach(l => {
          const div = document.createElement('div');
          div.className = `ide-line ${l.cls} new`;
          const tsEl = document.createElement('span');
          tsEl.className = 'ide-ts';
          tsEl.textContent = ts;
          tsEl.style.display = showTs ? '' : 'none';
          const txtEl = document.createElement('span');
          txtEl.className = 'ide-txt';
          txtEl.textContent = l.txt;
          div.appendChild(tsEl);
          div.appendChild(txtEl);
          output.appendChild(div);
          setTimeout(() => div.classList.remove('new'), 500);
          serialLogBuffer.push((showTs ? `[${ts}]  ` : '') + l.txt);
          serialLineCount++;
          while (output.children.length > SERIAL_MAX_LINES) output.removeChild(output.firstChild);
        });
        document.getElementById('lastRxEl').textContent = 'Last RX: ' + new Date().toTimeString().slice(0, 8);
      });

      updateSerialStatus();
      if (autoscroll && !serialPaused) output.scrollTop = output.scrollHeight;
    }

    document.getElementById('timestampCheck').addEventListener('change', function() {
      document.querySelectorAll('#ideOutput .ide-ts').forEach(el => {
        el.style.display = this.checked ? '' : 'none';
      });
    });

    function serialPoll() {
      if (serialPaused) return;
      fetch('../api/get_serial_log.php?node=' + NODE_ID + '&after_id=' + serialLastId + '&limit=20')
        .then(r => r.json())
        .then(rows => {
          if (!Array.isArray(rows) || !rows.length) return;
          serialLastId = rows[rows.length - 1].id || serialLastId;
          appendSerialEntries(rows);
        }).catch(() => {});
    }

    function serialInit() {
      fetch('../api/get_serial_log.php?node=' + NODE_ID + '&limit=20')
        .then(r => r.json())
        .then(rows => {
          if (!Array.isArray(rows) || !rows.length) return;
          serialLastId = rows[rows.length - 1].id || 0;
          appendSerialEntries(rows);
        }).catch(() => {});
    }

    serialInit();
    setInterval(serialPoll, 5000);

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