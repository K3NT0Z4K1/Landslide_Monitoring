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
  <style>
    /* ── Lock page to viewport so nothing overflows ── */
    html, body {
      height: 100%;
      overflow: hidden;
    }

    /* ── Main area sits right of sidebar, full height ── */
    .main {
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: #1a2424;   /* dark bg so page never shows white */
    }

    /* ── Topbar stays its natural height ── */
    .topbar { flex-shrink: 0; }

    /* ── IDE wrapper fills ALL remaining space below topbar ── */
    .ide-wrap {
      flex: 1 1 0;
      min-height: 0;           /* critical — lets flex child shrink below content */
      display: flex;
      flex-direction: column;
      margin: 14px 20px 16px;
      border-radius: 10px;
      overflow: hidden;
      border: 1px solid #253535;
      box-shadow: 0 8px 40px rgba(0,0,0,0.45);
    }

    /* ── Title bar (macOS-style chrome) ── */
    .ide-titlebar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 38px;
      padding: 0 14px;
      background: #131f1f;
      border-bottom: 1px solid #1e3333;
      flex-shrink: 0;
      user-select: none;
    }
    .ide-titlebar-left { display: flex; align-items: center; gap: 7px; }
    .ide-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .ide-dot.red    { background: #ff5f57; }
    .ide-dot.yellow { background: #febc2e; }
    .ide-dot.green  { background: #28c840; }
    .ide-title-text {
      margin-left: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 12.5px;
      font-weight: 500;
      color: #6aacac;
      letter-spacing: 0.02em;
    }
    .ide-titlebar-right { display: flex; align-items: center; gap: 8px; }
    .ide-port-chip {
      display: flex;
      align-items: center;
      gap: 5px;
      font-family: 'DM Mono', monospace;
      font-size: 10.5px;
      color: #3a8080;
      background: rgba(14,159,160,0.07);
      border: 1px solid rgba(14,159,160,0.12);
      padding: 2px 10px;
      border-radius: 4px;
    }
    .ide-port-chip i { font-size: 13px; }

    /* ── Toolbar ── */
    .ide-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 42px;
      padding: 0 14px;
      background: #182828;
      border-bottom: 1px solid #1e3333;
      flex-shrink: 0;
      gap: 10px;
    }
    .ide-toolbar-left  { display: flex; align-items: center; gap: 18px; }
    .ide-toolbar-right { display: flex; align-items: center; gap: 7px; }

    .ide-check-label {
      display: flex; align-items: center; gap: 6px;
      font-family: 'DM Sans', sans-serif;
      font-size: 12px; color: #6aacac;
      cursor: pointer; user-select: none;
    }
    .ide-check-label input[type="checkbox"] {
      accent-color: #0e9fa0;
      width: 13px; height: 13px; cursor: pointer;
    }

    .ide-select {
      background: #131f1f;
      border: 1px solid #2a4040;
      color: #4a8888;
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 4px;
      outline: none;
      cursor: default;
    }

    .ide-live-chip {
      display: flex; align-items: center; gap: 5px;
      font-family: 'DM Mono', monospace;
      font-size: 11px; color: #1ab8a0;
      background: rgba(26,184,160,0.1);
      border: 1px solid rgba(26,184,160,0.22);
      padding: 3px 10px; border-radius: 4px;
    }
    .ide-live-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: #1ab8a0;
      animation: idePulse 1.4s ease infinite;
    }
    @keyframes idePulse {
      0%,100%{ opacity:1; transform:scale(1); }
      50%    { opacity:0.3; transform:scale(0.75); }
    }

    .ide-btn {
      display: flex; align-items: center; justify-content: center;
      width: 30px; height: 30px;
      border-radius: 5px;
      border: 1px solid #2a4040;
      background: #131f1f;
      color: #3a7070;
      cursor: pointer;
      transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .ide-btn i { font-size: 15px; }
    .ide-btn:hover { background: #1e3535; color: #1ab8a0; border-color: rgba(26,184,160,0.3); }
    .ide-btn:active { transform: scale(0.93); }

    /* ── Output scrollable area — takes all remaining flex space ── */
    .ide-output {
      flex: 1 1 0;
      min-height: 0;           /* critical */
      overflow-y: auto;
      background: #0c1818;
      font-family: 'DM Mono', monospace;
      font-size: 12.5px;
      line-height: 1.7;
      padding: 6px 0 6px;
    }
    .ide-output::-webkit-scrollbar { width: 7px; }
    .ide-output::-webkit-scrollbar-track { background: #0c1818; }
    .ide-output::-webkit-scrollbar-thumb { background: #1e3535; border-radius: 4px; }
    .ide-output::-webkit-scrollbar-thumb:hover { background: #285050; }

    /* ── Lines ── */
    .ide-line {
      display: flex;
      align-items: baseline;
      gap: 14px;
      padding: 0 18px;
      min-height: 21px;
    }
    .ide-line:hover { background: rgba(14,159,160,0.04); }

    .ide-ts {
      font-size: 10.5px;
      color: #254545;
      min-width: 64px;
      width: 64px;
      flex-shrink: 0;
      user-select: none;
    }
    .ide-txt { color: #c0e0e0; flex: 1; }

    /* Line type colours */
    .ide-line.sep    .ide-txt { color: #1a3535; }
    .ide-line.recv   .ide-txt { color: #6ecece; font-weight: 500; }
    .ide-line.meta   .ide-txt { color: #306060; }
    .ide-line.field  .ide-txt { color: #a8d4d4; }
    .ide-line.sys    .ide-txt { color: #2e6060; font-style: italic; }
    .ide-line.ok     .ide-txt { color: #1ab8a0; }
    .ide-line.error  .ide-txt { color: #e05c4a; }
    .ide-line.safe   .ide-txt { color: #1ab8a0; font-weight: 600; }
    .ide-line.warn   .ide-txt { color: #e8a020; font-weight: 600; }
    .ide-line.danger .ide-txt { color: #e05c4a; font-weight: 600; }

    /* New-line flash */
    @keyframes ideIn { from{background:rgba(14,159,160,0.14);} to{background:transparent;} }
    .ide-line.new { animation: ideIn 0.5s ease both; }

    /* ── Status bar ── */
    .ide-statusbar {
      display: flex;
      align-items: center;
      gap: 10px;
      height: 27px;
      padding: 0 18px;
      background: #0a1414;
      border-top: 1px solid #162828;
      font-family: 'DM Mono', monospace;
      font-size: 10.5px;
      color: #2a5858;
      flex-shrink: 0;
    }
    .ide-status-sep { color: #1a3030; }
    .ide-status-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
  </style>
</head>
<body>

<?php $active_page = 'serial'; require_once "_sidebar.php"; ?>

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

  <!-- ══════════════════════════════════════════════
       ARDUINO IDE SERIAL MONITOR WINDOW
  ══════════════════════════════════════════════ -->
  <div class="ide-wrap">

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
          COM3 / Master Node (ESP32)
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
          <span>Show timestamp</span>
        </label>
      </div>
      <div class="ide-toolbar-right">
        <select class="ide-select" disabled>
          <option>115200 baud</option>
        </select>
        <div class="ide-live-chip" id="liveChip">
          <span class="ide-live-dot" id="liveDot"></span>
          <span id="liveLabel">Live</span>
        </div>
        <button class="ide-btn" onclick="togglePause()" title="Pause / Resume">
          <i class='bx bx-pause' id="pauseIcon"></i>
        </button>
        <button class="ide-btn" onclick="clearMonitor()" title="Clear output">
          <i class='bx bx-trash'></i>
        </button>
        <button class="ide-btn" onclick="downloadLog()" title="Save log">
          <i class='bx bx-download'></i>
        </button>
      </div>
    </div>

    <!-- Scrollable terminal output -->
    <div class="ide-output" id="ideOutput">
      <div class="ide-line sys">
        <span class="ide-ts">--:--:--</span>
        <span class="ide-txt">SlopeGuard Serial Monitor ready. Connecting to Master Node...</span>
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
      <div class="ide-status-right">
        <span class="ide-status-sep">|</span>
        <span id="rxCountEl">RX: 0</span>
        <span class="ide-status-sep">|</span>
        <span>115200 baud</span>
      </div>
    </div>

  </div><!-- /ide-wrap -->
</div><!-- /main -->

<script>
const NODE_ID = <?= $node ?>;

setInterval(() => {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}, 1000);

let paused    = false;
let lastId    = 0;
let lineCount = 0;
let rxCount   = 0;
let logBuffer = [];
const MAX_LINES = 800;

function togglePause() {
  paused = !paused;
  document.getElementById('pauseIcon').className = paused ? 'bx bx-play' : 'bx bx-pause';
  document.getElementById('liveLabel').textContent = paused ? 'Paused' : 'Live';
  const chip = document.getElementById('liveChip');
  const dot  = document.getElementById('liveDot');
  chip.style.color            = paused ? '#d97706' : '';
  chip.style.borderColor      = paused ? 'rgba(217,119,6,0.3)' : '';
  chip.style.background       = paused ? 'rgba(217,119,6,0.1)' : '';
  dot.style.background        = paused ? '#d97706' : '';
  dot.style.animationPlayState= paused ? 'paused' : 'running';
}

function clearMonitor() {
  document.getElementById('ideOutput').innerHTML = '';
  logBuffer = []; lineCount = 0; rxCount = 0;
  updateStatus();
}

function downloadLog() {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([logBuffer.join('\n')], { type: 'text/plain' }));
  a.download = 'slopeguard_serial_node' + NODE_ID + '_' +
    new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.txt';
  a.click();
  URL.revokeObjectURL(a.href);
}

function updateStatus() {
  document.getElementById('lineCountEl').textContent = lineCount + ' lines';
  document.getElementById('rxCountEl').textContent   = 'RX: ' + rxCount;
}

/* Build the exact same lines that Master_Node.ino slog() prints */
function buildLines(row) {
  const t    = parseFloat(row.temperature).toFixed(2);
  const h    = parseFloat(row.humidity).toFixed(2);
  const s    = row.soil_moisture;
  const r    = parseFloat(row.rainfall).toFixed(2);
  const st   = row.status;
  const rssi = (row.rssi !== null && row.rssi !== undefined && row.rssi !== '') ? row.rssi : 'N/A';
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

  /* Remove boot placeholder lines on first real data */
  output.querySelectorAll('.ide-line.sys').forEach(el => {
    if (el.querySelector('.ide-ts')?.textContent === '--:--:--') el.remove();
  });

  entries.forEach(row => {
    const ts = row.time || '--:--:--';
    rxCount++;

    buildLines(row).forEach(l => {
      const div    = document.createElement('div');
      div.className = `ide-line ${l.cls} new`;

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

      setTimeout(() => div.classList.remove('new'), 500);
      logBuffer.push((showTs ? `[${ts}]  ` : '') + l.txt);
      lineCount++;

      while (output.children.length > MAX_LINES)
        output.removeChild(output.firstChild);
    });

    document.getElementById('lastRxEl').textContent =
      'Last RX: ' + new Date().toTimeString().slice(0,8);
  });

  updateStatus();
  if (autoscroll && !paused)
    output.scrollTop = output.scrollHeight;
}

/* Timestamp toggle — show/hide live */
document.getElementById('timestampCheck').addEventListener('change', function() {
  document.querySelectorAll('#ideOutput .ide-ts').forEach(el => {
    el.style.display = this.checked ? '' : 'none';
  });
});

/* Poll API for new rows */
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

/* Initial load — last 30 rows so terminal isn't empty */
function init() {
  fetch('../api/get_serial_log.php?node=' + NODE_ID + '&limit=30')
    .then(r => r.json())
    .then(rows => {
      if (!Array.isArray(rows) || !rows.length) return;
      lastId = rows[rows.length - 1].id || 0;
      appendEntries(rows);
    })
    .catch(() => {});
}

init();
setInterval(poll, 5000);
</script>


</body>
</html>
