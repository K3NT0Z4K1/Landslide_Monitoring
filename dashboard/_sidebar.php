<?php
/**
 * _sidebar.php — shared sidebar include
 * Usage: require_once "_sidebar.php";
 * Expects: $node (int), $unread_alerts (int), $active_page (string)
 * $active_page values: 'dashboard' | 'map' | 'alerts' | 'serial' | 'summary'
 */
$active_page = $active_page ?? 'dashboard';
$node        = $node        ?? 1;
$unread_alerts = $unread_alerts ?? 0;
?>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <svg width="34" height="34" viewBox="0 0 96 96" fill="none">
      <defs><clipPath id="sc2"><path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z"/></clipPath></defs>
      <path d="M48 6 L90 22 L90 58 Q90 80 48 92 Q6 80 6 58 L6 22 Z" fill="#0d2a2b" stroke="#0e9fa0" stroke-width="2.5"/>
      <g clip-path="url(#sc2)" opacity="0.65">
        <line x1="0"   y1="96"  x2="96"  y2="0"   stroke="#0e9fa0" stroke-width="7"/>
        <line x1="8"   y1="104" x2="104" y2="8"    stroke="#1ab8a0" stroke-width="6"/>
        <line x1="-8"  y1="88"  x2="88"  y2="-8"   stroke="#0a7a7b" stroke-width="6"/>
        <line x1="16"  y1="112" x2="112" y2="16"   stroke="#0e9fa0" stroke-width="5"/>
        <line x1="-16" y1="80"  x2="80"  y2="-16"  stroke="#0a7a7b" stroke-width="4"/>
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

    <a href="index.php?node=<?= $node ?>" class="nav-item <?= $active_page==='dashboard' ? 'active' : '' ?>">
      <i class='bx bx-home-alt-2'></i> Dashboard
    </a>
    <a href="map.php" class="nav-item <?= $active_page==='map' ? 'active' : '' ?>">
      <i class='bx bx-map-alt'></i> Sensor Map
    </a>
    <a href="alerts.php" class="nav-item <?= $active_page==='alerts' ? 'active' : '' ?>">
      <i class='bx bx-bell'></i> Alert History
      <?php if ($unread_alerts > 0): ?>
        <span class="nav-alert-count"><?= $unread_alerts ?></span>
      <?php endif; ?>
    </a>

    <span class="nav-section-label">Tools</span>

    <a href="readings_summary.php?node=<?= $node ?>" class="nav-item <?= $active_page==='summary' ? 'active' : '' ?>">
      <i class='bx bx-bar-chart-alt-2'></i> Readings Summary
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-btn">
      <i class='bx bx-log-out'></i> Sign Out
    </a>
  </div>
</aside>