<?php require_once "../auth/auth_check.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sensor Map — SlopeGuard</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>#map { height: calc(100vh - 58px); }</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <svg width="34" height="34" viewBox="0 0 96 96" fill="none">
      <defs><clipPath id="sc4"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
      <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
      <g clip-path="url(#sc4)" opacity="0.65">
        <line x1="0"   y1="96"  x2="96"  y2="0"   stroke="#0e9fa0" stroke-width="7"/>
        <line x1="8"   y1="104" x2="104" y2="8"   stroke="#1ab8a0" stroke-width="6"/>
        <line x1="-8"  y1="88"  x2="88"  y2="-8"  stroke="#0a7a7b" stroke-width="6"/>
        <line x1="16"  y1="112" x2="112" y2="16"  stroke="#0e9fa0" stroke-width="5"/>
        <line x1="-16" y1="80"  x2="80"  y2="-16" stroke="#0a7a7b" stroke-width="4"/>
      </g>
      <g clip-path="url(#sc4)">
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
    <a href="index.php" class="nav-item">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item active">
      <i class='bx bx-map-alt'></i> Sensor Map
    </a>
    <a href="alerts.php" class="nav-item">
      <i class='bx bx-bell'></i> Alert History
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
setInterval(() => {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0,8);
}, 1000);
</script>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="../assets/js/map.js"></script>

</body>
</html>