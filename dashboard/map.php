<?php require_once "../auth/auth_check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sensor Map — Landslide Monitoring</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    #map { height: calc(100vh - 60px); border-radius: 0; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">⛰️</span>
    <h2>Landslide<br>Monitor</h2>
    <span>Davao Region</span>
  </div>
  <nav class="sidebar-nav">
    <a href="index.php" class="nav-item">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="map.php" class="nav-item active">
      <span class="nav-icon">🗺</span> Sensor Map
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <span>🚪</span> Logout
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="topbar-left">
      <h1>Sensor Map</h1>
      <p>Live node locations &amp; alert status</p>
    </div>
    <div class="topbar-right">
      <span class="topbar-time" id="clock"><?= date('H:i:s') ?></span>
    </div>
  </header>

  <div id="map"></div>
</div>

<script>
function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent = now.toTimeString().slice(0,8);
}
setInterval(updateClock, 1000);
</script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/map.js"></script>

</body>
</html>