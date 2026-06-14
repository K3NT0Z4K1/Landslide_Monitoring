<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

/* Alert counts for sidebar badge */
$counts = $conn->query("SELECT COUNT(*) AS total FROM alert_history")->fetch_assoc();
$unread_alerts = $counts['total'] ?? 0;

/* -----------------------------------------------
   Per-node reading stats
----------------------------------------------- */
$statsStmt = $conn->prepare("
  SELECT
    COUNT(*)                         AS total,
    SUM(status = 'SAFE')             AS safe_count,
    SUM(status = 'WARNING')          AS warn_count,
    SUM(status = 'DANGER')           AS danger_count,
    ROUND(AVG(temperature), 2)       AS avg_temp,
    ROUND(AVG(humidity), 2)          AS avg_hum,
    ROUND(AVG(soil_moisture), 2)     AS avg_soil,
    ROUND(AVG(rainfall), 2)          AS avg_rain,
    MIN(created_at)                  AS first_reading,
    MAX(created_at)                  AS last_reading
  FROM sensor_readings
  WHERE node_id = ?
");
$statsStmt->bind_param("i", $node);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

/* -----------------------------------------------
   Last 100 readings for run-length digest
----------------------------------------------- */
$runStmt = $conn->prepare("
  SELECT status, created_at
  FROM sensor_readings
  WHERE node_id = ?
  ORDER BY created_at DESC
  LIMIT 100
");
$runStmt->bind_param("i", $node);
$runStmt->execute();
$runRows = $runStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* Build runs */
$runs = [];
foreach ($runRows as $r) {
  $s = $r['status'];
  $t = $r['created_at'];
  if (empty($runs) || $runs[count($runs) - 1]['status'] !== $s) {
    $runs[] = ['status' => $s, 'count' => 1, 'start' => $t, 'end' => $t];
  } else {
    $runs[count($runs) - 1]['count']++;
    $runs[count($runs) - 1]['end'] = $t;
  }
}

/* -----------------------------------------------
   Hourly breakdown — last 12 hours
----------------------------------------------- */
$hourlyStmt = $conn->prepare("
  SELECT
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour_slot,
    COUNT(*)                     AS total,
    SUM(status = 'SAFE')         AS safe_c,
    SUM(status = 'WARNING')      AS warn_c,
    SUM(status = 'DANGER')       AS danger_c
  FROM sensor_readings
  WHERE node_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
  GROUP BY hour_slot
  ORDER BY hour_slot DESC
  LIMIT 12
");
$hourlyStmt->bind_param("i", $node);
$hourlyStmt->execute();
$hourly = $hourlyStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$node_labels = [1 => "Node 1 — Lower Slope A", 2 => "Node 2 — Lower Slope B", 3 => "Node 3 — Lower Slope C"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Readings Summary — SlopeGuard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ── Sensor Averages grid ── */
    .summary-averages-grid {
      display: grid !important;
      grid-template-columns: repeat(6, 1fr) !important;
      border-top: 1px solid rgba(14, 159, 160, 0.08);
    }

    @media (max-width: 1100px) {
      .summary-averages-grid {
        grid-template-columns: repeat(3, 1fr) !important;
      }
    }

    @media (max-width: 640px) {
      .summary-averages-grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
    }

    .avg-item {
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      padding: 26px 12px !important;
      text-align: center !important;
      border-right: 1px solid rgba(14, 159, 160, 0.08);
      gap: 0 !important;
    }

    .avg-item:last-child {
      border-right: none;
    }

    .avg-item i {
      font-size: 22px !important;
      color: var(--t400, #0e9fa0) !important;
      display: block !important;
      margin-bottom: 10px !important;
    }

    .avg-val {
      font-size: 20px !important;
      font-weight: 400 !important;
      font-family: 'DM Mono', monospace !important;
      color: #051414 !important;
      letter-spacing: -0.01em !important;
      line-height: 1.2 !important;
    }

    .avg-lbl {
      font-size: 10.5px !important;
      color: #4a8a8b !important;
      margin-top: 5px !important;
      font-weight: 500 !important;
      letter-spacing: 0.05em !important;
      text-transform: uppercase !important;
    }

    /* ── Hourly bar ── */
    .hour-bar {
      display: flex;
      height: 12px;
      border-radius: 3px;
      overflow: hidden;
      background: rgba(14, 159, 160, 0.08);
      min-width: 80px;
    }

    .hour-bar-seg {
      height: 100%;
    }

    .hour-bar-seg.safe {
      background: #0e9fa0;
    }

    .hour-bar-seg.warn {
      background: #d97706;
    }

    .hour-bar-seg.dang {
      background: #c0392b;
    }
  </style>
</head>

<body>

  <?php $active_page = 'summary';
  require_once "_sidebar.php"; ?>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-hamburger" onclick="toggleSidebar()">
          <i class='bx bx-menu'></i>
        </button>
        <h1>Readings Summary</h1>
        <p><?= $node_labels[$node] ?> &middot; <?= date('l, F j Y') ?></p>
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

    <div class="page-content">

      <!-- OVERVIEW STAT CARDS -->
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label"><i class='bx bx-data'></i> Total Readings</div>
          <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
          <div class="stat-unit">All-time entries</div>
        </div>
        <div class="stat-card ok-card">
          <div class="stat-label"><i class='bx bx-check-shield'></i> Safe</div>
          <div class="stat-value ok"><?= number_format($stats['safe_count'] ?? 0) ?></div>
          <div class="stat-unit">
            <?php
            $pct = $stats['total'] > 0 ? round(($stats['safe_count'] / $stats['total']) * 100) : 0;
            echo $pct . '% of all readings';
            ?>
          </div>
        </div>
        <div class="stat-card warn-card">
          <div class="stat-label"><i class='bx bx-error-circle'></i> Warning</div>
          <div class="stat-value warn"><?= number_format($stats['warn_count'] ?? 0) ?></div>
          <div class="stat-unit">
            <?php
            $pct = $stats['total'] > 0 ? round(($stats['warn_count'] / $stats['total']) * 100) : 0;
            echo $pct . '% of all readings';
            ?>
          </div>
        </div>
        <div class="stat-card danger-card">
          <div class="stat-label"><i class='bx bx-error'></i> Danger</div>
          <div class="stat-value danger"><?= number_format($stats['danger_count'] ?? 0) ?></div>
          <div class="stat-unit">
            <?php
            $pct = $stats['total'] > 0 ? round(($stats['danger_count'] / $stats['total']) * 100) : 0;
            echo $pct . '% of all readings';
            ?>
          </div>
        </div>
      </div>

      <!-- AVERAGES ROW -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-stats'></i> Sensor Averages — All Time</div>
          <span class="panel-badge teal"><?= $node_labels[$node] ?></span>
        </div>
        <div class="summary-averages-grid">
          <div class="avg-item">
            <i class='bx bx-thermometer'></i>
            <div class="avg-val"><?= $stats['avg_temp'] ?? '--' ?>°C</div>
            <div class="avg-lbl">Avg Temperature</div>
          </div>
          <div class="avg-item">
            <i class='bx bx-droplet'></i>
            <div class="avg-val"><?= $stats['avg_hum'] ?? '--' ?>%</div>
            <div class="avg-lbl">Avg Humidity</div>
          </div>
          <div class="avg-item">
            <i class='bx bx-landscape'></i>
            <div class="avg-val"><?= $stats['avg_soil'] ?? '--' ?>%</div>
            <div class="avg-lbl">Avg Soil Moisture</div>
          </div>
          <div class="avg-item">
            <i class='bx bx-cloud-rain'></i>
            <div class="avg-val"><?= $stats['avg_rain'] ?? '--' ?> mm</div>
            <div class="avg-lbl">Avg Rainfall</div>
          </div>
          <div class="avg-item">
            <i class='bx bx-calendar-check'></i>
            <div class="avg-val mono" style="font-size:13px"><?= $stats['first_reading'] ? date('M d, H:i', strtotime($stats['first_reading'])) : '--' ?></div>
            <div class="avg-lbl">First Reading</div>
          </div>
          <div class="avg-item">
            <i class='bx bx-calendar'></i>
            <div class="avg-val mono" style="font-size:13px"><?= $stats['last_reading'] ? date('M d, H:i', strtotime($stats['last_reading'])) : '--' ?></div>
            <div class="avg-lbl">Last Reading</div>
          </div>
        </div>
      </div>

      <!-- STATUS RUN DIGEST -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-list-ul'></i> Status Run Digest</div>
          <span class="panel-badge teal">Last 100 readings</span>
        </div>
        <div class="runs-table-wrap">
          <?php if (empty($runs)): ?>
            <div class="empty-state"><i class='bx bx-data'></i>
              <p>No readings recorded yet.</p>
            </div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Status</th>
                  <th>Readings</th>
                  <th>From</th>
                  <th>To</th>
                  <th>Duration</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($runs as $i => $run):
                  $st  = $run['status'];
                  $bc  = $st === 'DANGER' ? 'danger' : ($st === 'WARNING' ? 'warning' : 'normal');
                  $icon = $st === 'DANGER' ? 'bx-error' : ($st === 'WARNING' ? 'bx-error-circle' : 'bx-check-circle');
                  $tFrom = date('H:i:s', strtotime($run['end']));   /* newest = end since rows are DESC */
                  $tTo   = date('H:i:s', strtotime($run['start'])); /* oldest = start */
                  /* Duration in seconds */
                  $diffSec = abs(strtotime($run['end']) - strtotime($run['start']));
                  $durStr  = $diffSec < 60 ? $diffSec . 's' : floor($diffSec / 60) . 'm ' . ($diffSec % 60) . 's';

                  $desc = '';
                  if ($st === 'SAFE')    $desc = 'All sensors within safe thresholds.';
                  if ($st === 'WARNING') $desc = 'Elevated soil moisture or rainfall detected.';
                  if ($st === 'DANGER')  $desc = 'Critical conditions — immediate attention required.';
                ?>
                  <tr>
                    <td class="mono" style="color:var(--muted)"><?= $i + 1 ?></td>
                    <td>
                      <span class="badge <?= $bc ?>">
                        <?= $st ?>
                      </span>
                    </td>
                    <td class="mono"><?= $run['count'] ?></td>
                    <td class="mono"><?= $tFrom ?></td>
                    <td class="mono"><?= $tTo ?></td>
                    <td class="mono"><?= $run['count'] > 1 ? $durStr : '—' ?></td>
                    <td style="font-size:12.5px;color:var(--text2)"><?= $desc ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- HOURLY BREAKDOWN -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class='bx bx-time'></i> Hourly Breakdown</div>
          <span class="panel-badge teal">Last 12 hours</span>
        </div>
        <?php if (empty($hourly)): ?>
          <div class="empty-state"><i class='bx bx-time'></i>
            <p>No data in the last 12 hours.</p>
          </div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Hour</th>
                <th>Total</th>
                <th><span class="badge normal" style="font-size:10px">Safe</span></th>
                <th><span class="badge warning" style="font-size:10px">Warning</span></th>
                <th><span class="badge danger" style="font-size:10px">Danger</span></th>
                <th>Breakdown</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hourly as $h):
                $total  = max($h['total'], 1);
                $safePct = round(($h['safe_c'] / $total) * 100);
                $warnPct = round(($h['warn_c'] / $total) * 100);
                $dangPct = round(($h['danger_c'] / $total) * 100);
              ?>
                <tr>
                  <td class="mono"><?= date('H:00', strtotime($h['hour_slot'])) ?></td>
                  <td class="mono"><?= $h['total'] ?></td>
                  <td class="mono" style="color:var(--ok)"><?= $h['safe_c'] ?></td>
                  <td class="mono" style="color:var(--warn)"><?= $h['warn_c'] ?></td>
                  <td class="mono" style="color:var(--danger)"><?= $h['danger_c'] ?></td>
                  <td>
                    <div class="hour-bar">
                      <?php if ($safePct > 0): ?>
                        <div class="hour-bar-seg safe" style="width:<?= $safePct ?>%" title="Safe: <?= $safePct ?>%"></div>
                      <?php endif; ?>
                      <?php if ($warnPct > 0): ?>
                        <div class="hour-bar-seg warn" style="width:<?= $warnPct ?>%" title="Warning: <?= $warnPct ?>%"></div>
                      <?php endif; ?>
                      <?php if ($dangPct > 0): ?>
                        <div class="hour-bar-seg dang" style="width:<?= $dangPct ?>%" title="Danger: <?= $dangPct ?>%"></div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
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