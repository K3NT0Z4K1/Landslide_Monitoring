<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

$counts = $conn->query("SELECT COUNT(*) AS total FROM alert_history")->fetch_assoc();
$unread_alerts = $counts['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Serial Monitor — SlopeGuard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="serial-page">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="34" height="34" viewBox="0 0 96 96" fill="none">
      <defs><clipPath id="sc2"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
      <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
      <g clip-path="url(#sc2)" opacity="0.65">
        <line x1="0" y1="96" x2="96" y2="0" stroke="#0e9fa0" stroke-width="7"/>
        <line x1="8" y1="104" x2="104" y2="8" stroke="#1ab8a0" stroke-width="6"/>
        <line x1="-8" y1="88" x2="88" y2="-8" stroke="#0a7a7b" stroke-width="6"/>
        <line x1="16" y1="112" x2="112" y2="16" stroke="#0e9fa0" stroke-width="5"/>
        <line x1="-16" y1="80" x2="80" y2="-16" stroke="#0a7a7b" stroke-width="4"/>
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
    <a href="index.php?node=<?= $node ?>" class="nav-item">
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
    <span class="nav-section-label">Tools</span>
    <a href="serial.php?node=<?= $node ?>" class="nav-item active">
      <i class='bx bx-terminal'></i> Serial Monitor
    </a>
    <a href="readings_summary.php?node=<?= $node ?>" class="nav-item">
      <i class='bx bx-bar-chart-alt-2'></i> Readings Summary
    </a>
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
      <h1>Serial Monitor</h1>
      <p>Live output from Master Node &middot; <?= date('l, F j Y') ?></p>
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

  <!-- ARDUINO IDE-STYLE SERIAL MONITOR -->
  <div class="ide-wrap">

    <!-- IDE WINDOW CHROME (title bar like Arduino IDE) -->
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
          COM3 / Master Node (ESP32)
        </span>
      </div>
    </div>

    <!-- IDE TOOLBAR (mimics Arduino IDE toolbar row) -->
    <div class="ide-toolbar">
      <div class="ide-toolbar-left">
        <!-- Autoscroll toggle -->
        <label class="ide-check-label">
          <input type="checkbox" id="autoscrollCheck" checked>
          <span>Autoscroll</span>
        </label>
        <!-- Show timestamp toggle -->
        <label class="ide-check-label">
          <input type="checkbox" id="timestampCheck" checked>
          <span>Show timestamp</span>
        </label>
      </div>
      <div class="ide-toolbar-right">
        <!-- Baud rate (display only) -->
        <div class="ide-select-group">
          <select class="ide-select" disabled>
            <option>115200 baud</option>
          </select>
        </div>
        <!-- Live indicator -->
        <div class="ide-live-chip" id="livechip">
          <span class="ide-live-dot" id="liveDot"></span>
          <span id="liveLabel">Live</span>
        </div>
        <!-- Pause -->
        <button class="ide-btn" id="pauseBtn" onclick="togglePause()" title="Pause / Resume">
          <i class='bx bx-pause' id="pauseIcon"></i>
        </button>
        <!-- Clear -->
        <button class="ide-btn" onclick="clearMonitor()" title="Clear output">
          <i class='bx bx-trash'></i>
        </button>
        <!-- Download -->
        <button class="ide-btn" onclick="downloadLog()" title="Save log">
          <i class='bx bx-download'></i>
        </button>
      </div>
    </div>

    <!-- TERMINAL OUTPUT AREA -->
    <div class="ide-output" id="ideOutput">
      <div class="ide-line sys">
        <span class="ide-ts">--:--:--</span>
        <span class="ide-txt">SlopeGuard Serial Monitor started. Connecting to Master Node...</span>
      </div>
      <div class="ide-line sys">
        <span class="ide-ts">--:--:--</span>
        <span class="ide-txt">Waiting for LoRa packets...</span>
      </div>
    </div>

    <!-- IDE STATUS BAR (bottom bar like Arduino IDE) -->
    <div class="ide-statusbar">
      <span id="lineCountEl">0 lines</span>
      <span class="ide-status-sep">|</span>
      <span id="lastRxEl">No data received</span>
      <span class="ide-status-sep">|</span>
      <span>Node <?= $node ?></span>
      <span class="ide-status-sep">|</span>
      <span>115200 baud</span>
      <span class="ide-status-sep" style="margin-left:auto">|</span>
      <span id="rxCountEl">RX: 0</span>
    </div>

  </div><!-- /ide-wrap -->
</div><!-- /main -->

<script>
const NODE_ID = <?= $node ?>;

/* Clock */
setInterval(() => { document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8); }, 1000);

/* State */
let paused      = false;
let lastId      = 0;
let lineCount   = 0;
let rxCount     = 0;
let logBuffer   = [];
const MAX_LINES = 800;

/* Pause / resume */
function togglePause() {
  paused = !paused;
  document.getElementById('pauseIcon').className = paused ? 'bx bx-play' : 'bx bx-pause';
  document.getElementById('liveLabel').textContent = paused ? 'Paused' : 'Live';
  const dot = document.getElementById('liveDot');
  dot.style.background = paused ? '#d97706' : '';
  dot.style.animation  = paused ? 'none' : '';
}

function clearMonitor() {
  document.getElementById('ideOutput').innerHTML = '';
  logBuffer   = [];
  lineCount   = 0;
  rxCount     = 0;
  updateStatus();
}

function downloadLog() {
  const blob = new Blob([logBuffer.join('\n')], { type: 'text/plain' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = 'slopeguard_serial_node' + NODE_ID + '_' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.txt';
  a.click();
  URL.revokeObjectURL(a.href);
}

function updateStatus() {
  document.getElementById('lineCountEl').textContent = lineCount + ' lines';
  document.getElementById('rxCountEl').textContent   = 'RX: ' + rxCount;
}

/* -----------------------------------------------
   Build lines exactly as Arduino IDE prints them
   Each DB row = one LoRa packet received.
   Output mirrors exactly what slog() prints in
   Master_Node.ino:
     --------------------
     Received : 1,22.30,59.00,99,0.00,WARNING
     RSSI     : -72
     Node ID  : 1
     Temp     : 22.30 C
     Humidity : 59.00 %
     Soil     : 99%
     Rain     : 0.00 mm
     Status   : WARNING
     Sending to server...
     HTTP Response : 200
     Server reply  : OK
----------------------------------------------- */
function buildLines(row) {
  const t    = parseFloat(row.temperature).toFixed(2);
  const h    = parseFloat(row.humidity).toFixed(2);
  const s    = row.soil_moisture;
  const r    = parseFloat(row.rainfall).toFixed(2);
  const st   = row.status;
  const rssi = row.rssi != null ? row.rssi : 'N/A';
  const raw  = row.raw_packet || `${row.node_id},${t},${h},${s},${r},${st}`;
  const stCls = st === 'DANGER' ? 'danger' : (st === 'WARNING' ? 'warn' : 'safe');

  return [
    { cls: 'sep',   txt: '--------------------' },
    { cls: 'recv',  txt: `Received : ${raw}` },
    { cls: 'meta',  txt: `RSSI     : ${rssi}` },
    { cls: 'field', txt: `Node ID  : ${row.node_id}` },
    { cls: 'field', txt: `Temp     : ${t} C` },
    { cls: 'field', txt: `Humidity : ${h} %` },
    { cls: 'field', txt: `Soil     : ${s}%` },
    { cls: 'field', txt: `Rain     : ${r} mm` },
    { cls: stCls,   txt: `Status   : ${st}` },
    { cls: 'sys',   txt: 'Sending to server...' },
    { cls: 'ok',    txt: 'HTTP Response : 200' },
    { cls: 'ok',    txt: 'Server reply  : OK' },
  ];
}

function appendEntries(entries) {
  if (!entries.length) return;

  const output     = document.getElementById('ideOutput');
  const showTs     = document.getElementById('timestampCheck').checked;
  const autoscroll = document.getElementById('autoscrollCheck').checked;

  /* Remove placeholder boot lines on first real data */
  output.querySelectorAll('.ide-line.sys').forEach(el => {
    if (el.querySelector('.ide-ts')?.textContent === '--:--:--') el.remove();
  });

  entries.forEach(row => {
    const ts = row.time || '--:--:--';
    rxCount++;

    buildLines(row).forEach(l => {
      const div = document.createElement('div');
      div.className = 'ide-line ' + l.cls + ' new';

      const tsEl = document.createElement('span');
      tsEl.className   = 'ide-ts';
      tsEl.textContent = ts;
      tsEl.style.display = showTs ? '' : 'none';

      const txtEl = document.createElement('span');
      txtEl.className   = 'ide-txt';
      txtEl.textContent = l.txt;

      div.appendChild(tsEl);
      div.appendChild(txtEl);
      output.appendChild(div);

      /* Remove .new after animation */
      setTimeout(() => div.classList.remove('new'), 400);

      logBuffer.push((showTs ? `[${ts}] ` : '') + l.txt);
      lineCount++;

      /* Trim old lines */
      while (output.children.length > MAX_LINES) {
        output.removeChild(output.firstChild);
      }
    });

    document.getElementById('lastRxEl').textContent =
      'Last RX: ' + new Date().toTimeString().slice(0,8);
  });

  updateStatus();
  if (autoscroll && !paused) output.scrollTop = output.scrollHeight;
}

/* Timestamp checkbox — show/hide ts column live */
document.getElementById('timestampCheck').addEventListener('change', function() {
  document.querySelectorAll('.ide-ts').forEach(el => {
    el.style.display = this.checked ? '' : 'none';
  });
});

/* Poll API */
function poll() {
  if (paused) return;
  fetch('../api/get_serial_log.php?node=' + NODE_ID + '&after_id=' + lastId + '&limit=20')
    .then(r => r.json())
    .then(rows => {
      if (!Array.isArray(rows) || !rows.length) return;
      lastId = rows[rows.length - 1].id || lastId;
      appendEntries(rows);
    })
    .catch(() => {});
}

/* Initial load */
function initMonitor() {
  fetch('../api/get_serial_log.php?node=' + NODE_ID + '&limit=30')
    .then(r => r.json())
    .then(rows => {
      if (!Array.isArray(rows) || !rows.length) return;
      lastId = rows[rows.length - 1].id || 0;
      appendEntries(rows);
    })
    .catch(() => {});
}

initMonitor();
setInterval(poll, 5000);
</script>
</body>
</html>
