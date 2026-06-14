<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) $node = 1;

$counts = $conn->query("SELECT COUNT(*) AS total FROM alert_history")->fetch_assoc();
$unread_alerts = $counts['total'] ?? 0;
$active_page   = 'map';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sensor Map — SlopeGuard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
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
        <h1>Sensor Map</h1>
        <p>Real-time node locations &middot; <?= date('l, F j Y') ?></p>
      </div>
      <div class="topbar-right">
        <div class="topbar-time">
          <i class='bx bx-time-five'></i>
          <span id="clock"><?= date('H:i:s') ?></span>
        </div>
      </div>
    </header>

    <div id="map"></div>
  </div>

  <script>
    setInterval(() => {
      document.getElementById('clock').textContent = new Date().toTimeString().slice(0, 8);
    }, 1000);
    /* toggleSidebar / closeSidebar defined in map.js (includes map interaction lock) */
  </script>
  <script>
    /* Sidebar functions defined early so onclick attributes work immediately */
    function toggleSidebar() {
      const sidebar  = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      const isOpen   = sidebar.classList.toggle('open');
      backdrop.classList.toggle('open', isOpen);
      if (typeof disableMapInteraction === 'function') {
        isOpen ? disableMapInteraction() : enableMapInteraction();
      }
    }
    function closeSidebar() {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebarBackdrop').classList.remove('open');
      if (typeof enableMapInteraction === 'function') enableMapInteraction();
    }
  </script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="../assets/js/map.js"></script>

</body>

</html>