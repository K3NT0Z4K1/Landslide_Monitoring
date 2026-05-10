<?php require_once "../auth/auth_check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sensor Map — GeoWatch</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    #map {
      height: calc(100vh - 60px);
      border-radius: 0;
    }
  </style>
</head>
<body>

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
    <a href="index.php" class="nav-item">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item active">
      <i class='bx bx-map-alt'></i> Sensor Map
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
      <h1>Sensor Map</h1>
      <p>Live node locations &amp; alert status &mdash; Davao Region</p>
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
function updateClock() {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}
setInterval(updateClock, 1000);
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/map.js"></script>

</body>
</html>