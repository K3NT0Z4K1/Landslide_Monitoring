import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getDatabase, ref, onValue, query, orderByChild, limitToLast }
  from "https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js";

const firebaseConfig = {
  apiKey:            "AIzaSyAz_06xdYHF1LMugG5Xi48hYuYqnJBv_gc",
  authDomain:        "landslide-monitoring-da1f4.firebaseapp.com",
  databaseURL:       "https://landslide-monitoring-da1f4-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId:         "landslide-monitoring-da1f4",
  storageBucket:     "landslide-monitoring-da1f4.firebasestorage.app",
  messagingSenderId: "288832658463",
  appId:             "1:288832658463:web:4cc222ea4ff596d0dc092a",
  measurementId:     "G-HEV4KGDKXC"
};

const firebaseApp = initializeApp(firebaseConfig);
const db = getDatabase(firebaseApp);

const SOIL_WARN=500, SOIL_DANGER=700, RAIN_WARN=10, RAIN_DANGER=20;

function getRiskLevel(soil, rain) {
  if (soil > SOIL_DANGER && rain > RAIN_DANGER) return 'danger';
  if (soil > SOIL_WARN   && rain > RAIN_WARN)   return 'warning';
  return 'normal';
}
function getRiskLabel(l) {
  return l === 'danger' ? 'HIGH RISK' : l === 'warning' ? 'WARNING' : 'NORMAL';
}

/* ===== LOGIN / LOGOUT ===== */

window.handleLogin = function () {
  const u   = document.getElementById('username').value.trim();
  const p   = document.getElementById('password').value;
  const err = document.getElementById('login-error');
  if (u === 'admin' && p === 'admin123') {
    document.getElementById('login-page').style.display = 'none';
    document.getElementById('app').style.display = 'block';
    err.style.display = 'none';
    initApp();
  } else {
    err.style.display = 'flex';
  }
};

window.handleLogout = function () {
  document.getElementById('app').style.display = 'none';
  document.getElementById('login-page').style.display = 'flex';
  document.getElementById('username').value = '';
  document.getElementById('password').value = '';
  unsubscribeAll();
};

document.getElementById('password').addEventListener('keydown', e => { if (e.key === 'Enter') window.handleLogin(); });
document.getElementById('username').addEventListener('keydown', e => { if (e.key === 'Enter') window.handleLogin(); });

/* ===== TAB SWITCHING ===== */

let topbarSub = '';

window.switchTab = function (tab, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  btn.classList.add('active');

  const titles = { dashboard: 'Dashboard', map: 'Sensor Map', alerts: 'Alert History' };
  const subs   = {
    dashboard: topbarSub,
    map:       'Live node locations & alert status',
    alerts:    'WARNING and DANGER events log'
  };

  document.getElementById('topbar-title').textContent = titles[tab];
  document.getElementById('topbar-sub').textContent   = subs[tab];
  document.getElementById('node-select-wrap').style.display = tab === 'dashboard' ? 'flex' : 'none';

  if (tab === 'map') setTimeout(() => leafletMap.invalidateSize(), 100);
};

/* ===== CLOCK ===== */

function updateClock() {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0, 8);
}

/* ===== CHARTS ===== */

let tempChart, rainChart, soilChart;
Chart.defaults.font.family = "'DM Sans',sans-serif";
Chart.defaults.color = '#4a8a8b';

const baseOpts = {
  responsive: true, maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { position: 'top', labels: { font: { size: 11, weight: '500' }, usePointStyle: true, pointStyleWidth: 7, padding: 14 } },
    tooltip: { backgroundColor: 'rgba(5,20,20,0.92)', titleColor: '#a8ede6', bodyColor: '#e0f7f7', borderColor: 'rgba(14,159,160,0.2)', borderWidth: 1, padding: 10, cornerRadius: 8 }
  },
  scales: {
    x: { grid: { color: 'rgba(14,159,160,0.07)' }, ticks: { font: { size: 10 }, color: '#4a8a8b' }, border: { color: 'rgba(14,159,160,0.1)' } },
    y: { grid: { color: 'rgba(14,159,160,0.07)' }, ticks: { font: { size: 10 }, color: '#4a8a8b' }, border: { color: 'rgba(14,159,160,0.1)' }, beginAtZero: false }
  }
};

function initCharts() {
  const el = Array(10).fill('--'), ed = Array(10).fill(null);
  tempChart = new Chart(document.getElementById('tempChart'), {
    type: 'line',
    data: { labels: [...el], datasets: [
      { label: 'Temperature (°C)', data: [...ed], borderColor: '#c0392b', backgroundColor: 'rgba(192,57,43,0.07)', borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true, yAxisID: 'y' },
      { label: 'Humidity (%)',     data: [...ed], borderColor: '#1d4ed8', backgroundColor: 'rgba(29,78,216,0.05)',  borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true, yAxisID: 'y1' }
    ]},
    options: { ...baseOpts, scales: { ...baseOpts.scales,
      y:  { ...baseOpts.scales.y, position: 'left',  title: { display: true, text: '°C', color: '#4a8a8b', font: { size: 10 } } },
      y1: { ...baseOpts.scales.y, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: '#4a8a8b', font: { size: 10 } } }
    }}
  });
  rainChart = new Chart(document.getElementById('rainChart'), {
    type: 'bar',
    data: { labels: [...el], datasets: [{ label: 'Rainfall (mm)', data: [...ed], backgroundColor: 'rgba(14,107,108,0.55)', borderColor: '#0e6b6c', borderWidth: 1.5, borderRadius: 4 }] },
    options: { ...baseOpts }
  });
  soilChart = new Chart(document.getElementById('soilChart'), {
    type: 'line',
    data: { labels: [...el], datasets: [{ label: 'Soil Moisture', data: [...ed], borderColor: '#0e9fa0', backgroundColor: 'rgba(14,159,160,0.08)', borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true }] },
    options: { ...baseOpts }
  });
}

function updateCharts(readings) {
  const labels = readings.map(r => r.time);
  const tD = readings.map(r => r.temperature);
  const hD = readings.map(r => r.humidity);
  const rD = readings.map(r => r.rainfall);
  const sD = readings.map(r => r.soil_moisture);
  tempChart.data.labels = labels;
  tempChart.data.datasets[0].data = tD;
  tempChart.data.datasets[1].data = hD;
  rainChart.data.labels = labels;
  rainChart.data.datasets[0].data = rD;
  rainChart.data.datasets[0].backgroundColor = rD.map(v =>
    v > RAIN_DANGER ? 'rgba(192,57,43,0.72)' : v > RAIN_WARN ? 'rgba(217,119,6,0.72)' : 'rgba(14,107,108,0.62)'
  );
  soilChart.data.labels = labels;
  soilChart.data.datasets[0].data = sD;
  tempChart.update('none'); rainChart.update('none'); soilChart.update('none');
}

/* ===== MAP ===== */

let leafletMap, nodeLayer;

function initMap() {
  leafletMap = L.map('map', { zoomControl: true }).setView([8.2495, 124.7541], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors', maxZoom: 19
  }).addTo(leafletMap);
  nodeLayer = L.layerGroup().addTo(leafletMap);
  const legend = L.control({ position: 'bottomright' });
  legend.onAdd = function () {
    const div = L.DomUtil.create('div', 'map-legend');
    div.innerHTML = `<h4>Node Status</h4>
      <div><i style="background:#0e9fa0"></i> Normal</div>
      <div><i style="background:#d97706"></i> Warning</div>
      <div><i style="background:#c0392b"></i> Danger</div>
      <div><i style="background:#9ca3af"></i> Offline</div>`;
    return div;
  };
  legend.addTo(leafletMap);
}

function getNodeColor(alert, status) {
  if (status === 'OFFLINE') return { fill: '#9ca3af', border: '#6b7280' };
  if (alert === 'DANGER')   return { fill: '#c0392b', border: '#922b21' };
  if (alert === 'WARNING')  return { fill: '#d97706', border: '#b45309' };
  return { fill: '#0e9fa0', border: '#0a7a7b' };
}

function getBadgeStyle(alert) {
  if (alert === 'DANGER')  return 'background:#fdf0ee;color:#c0392b;';
  if (alert === 'WARNING') return 'background:#fef9ee;color:#d97706;';
  return 'background:#e0f7f7;color:#0e6b6c;';
}

function refreshMapMarkers(nodes) {
  nodeLayer.clearLayers();
  nodes.forEach(node => {
    const c = getNodeColor(node.alert, node.status);
    const marker = L.circleMarker([node.lat, node.lng], { radius: 11, color: c.border, fillColor: c.fill, fillOpacity: 0.85, weight: 2.5 }).addTo(nodeLayer);
    marker.bindPopup(`
      <div style="font-family:'DM Sans',sans-serif;min-width:185px;padding:2px 0">
        <div style="font-weight:600;font-size:14px;color:#051414;margin-bottom:8px">${node.name}</div>
        <div style="font-size:12.5px;color:#0e3d3e;margin-bottom:3px">Location: ${node.location}</div>
        <div style="font-size:12.5px;color:#0e3d3e;margin-bottom:10px">Status: ${node.status}</div>
        <span style="display:inline-block;font-size:11px;font-weight:500;padding:3px 10px;border-radius:20px;${getBadgeStyle(node.alert)}">${node.alert}</span>
      </div>`, { maxWidth: 220 });
    if (node.alert === 'DANGER' || node.alert === 'WARNING') {
      L.circleMarker([node.lat, node.lng], { radius: 19, color: c.fill, fillColor: 'transparent', fillOpacity: 0, weight: 1.5, opacity: 0.35 }).addTo(nodeLayer);
    }
  });
}

/* ===== ALERT BANNER ===== */

function updateAlertBanner(level, label) {
  const banner = document.getElementById('alert-banner');
  const icon   = document.getElementById('alert-icon');
  const text   = document.getElementById('alert-text');
  banner.className = 'alert-banner ' + level + ' fade-in';
  const icons = { normal: 'bx-check-shield', warning: 'bx-error-circle', danger: 'bx-error' };
  const msgs  = {
    normal:  'All sensor readings are within safe thresholds.',
    warning: 'Elevated soil moisture and rainfall detected. Monitor closely.',
    danger:  'Imminent landslide conditions detected. Evacuate at-risk zones immediately.'
  };
  icon.className = 'bx ' + icons[level];
  text.innerHTML = `<strong>${label}</strong> &mdash; ${msgs[level]}`;
}

/* ===== STAT CARDS ===== */

function updateStatCards(d, nodeId) {
  const soil  = parseFloat(d.soil_moisture);
  const rain  = parseFloat(d.rainfall);
  const level = getRiskLevel(soil, rain);
  const label = getRiskLabel(level);
  document.getElementById('temp').textContent     = parseFloat(d.temperature).toFixed(1);
  document.getElementById('humidity').textContent = parseFloat(d.humidity).toFixed(1);
  document.getElementById('soil').textContent     = soil;
  document.getElementById('rain').textContent     = parseFloat(rain).toFixed(2);
  const riskEl = document.getElementById('risk-value');
  riskEl.textContent = label;
  riskEl.className   = 'stat-value risk ' + (level === 'normal' ? 'ok' : level === 'warning' ? 'warn' : 'danger');
  document.getElementById('risk-node').textContent  = 'Node ' + nodeId;
  document.getElementById('soil-card').className    = 'stat-card ' + (soil > SOIL_DANGER ? 'danger-card' : soil > SOIL_WARN ? 'warn-card' : 'ok-card');
  document.getElementById('rain-card').className    = 'stat-card ' + (rain > RAIN_DANGER ? 'danger-card' : rain > RAIN_WARN ? 'warn-card' : 'ok-card');
  document.getElementById('risk-card').className    = 'stat-card ' + (level === 'danger' ? 'danger-card' : level === 'warning' ? 'warn-card' : 'ok-card');
  document.getElementById('soil').className         = 'stat-value ' + (soil > SOIL_DANGER ? 'danger' : soil > SOIL_WARN ? 'warn' : 'ok');
  document.getElementById('rain').className         = 'stat-value ' + (rain > RAIN_DANGER ? 'danger' : rain > RAIN_WARN ? 'warn' : 'ok');
  updateAlertBanner(level, label);
}

/* ===== READINGS TABLE ===== */

function updateTable(readings) {
  const tbody = document.getElementById('readings-body');
  if (!readings.length) {
    tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class='bx bx-data'></i><p>No sensor readings yet. Awaiting data from nodes.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = [...readings].reverse().map(r => {
    const s  = r.soil_moisture, rn = r.rainfall;
    const lv = getRiskLevel(s, rn);
    const lb = getRiskLabel(lv);
    const cls = lv === 'normal' ? 'normal' : lv === 'warning' ? 'warning' : 'danger';
    return `<tr>
      <td class="mono">${r.datetime || r.time || '--'}</td>
      <td>${parseFloat(r.temperature).toFixed(1)}</td>
      <td>${parseFloat(r.humidity).toFixed(1)}</td>
      <td>${s}</td>
      <td>${parseFloat(rn).toFixed(2)}</td>
      <td><span class="badge ${cls}">${lb}</span></td>
    </tr>`;
  }).join('');
}

/* ===== ALERT HISTORY TABLE ===== */

function updateAlertHistoryTable(entries) {
  const tbody = document.getElementById('alert-history-body');
  const badge = document.getElementById('alert-count-badge');
  const navCount = document.getElementById('nav-alert-count');

  if (!entries.length) {
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><i class='bx bx-bell-off'></i><p>No alert events recorded yet. System is monitoring.</p></div></td></tr>`;
    badge.textContent = 'Live';
    badge.className   = 'panel-badge teal';
    navCount.style.display = 'none';
    updateAlertSummary([]);
    return;
  }

  /* Sort newest first */
  const sorted = [...entries].sort((a, b) => b.timestamp - a.timestamp);

  tbody.innerHTML = sorted.map(e => {
    const cls = e.status === 'DANGER' ? 'danger' : 'warning';
    const dt  = new Date(e.timestamp).toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    return `<tr>
      <td class="mono">${dt}</td>
      <td>Node ${e.node_id}</td>
      <td>${e.soil_moisture}%</td>
      <td>${parseFloat(e.rainfall).toFixed(2)}</td>
      <td><span class="badge ${cls}">${e.status}</span></td>
    </tr>`;
  }).join('');

  /* Update badge count */
  badge.textContent = entries.length + ' event' + (entries.length !== 1 ? 's' : '');
  badge.className   = 'panel-badge ' + (entries.some(e => e.status === 'DANGER') ? 'red' : 'amber');

  /* Nav pill count */
  navCount.textContent = entries.length > 99 ? '99+' : entries.length;
  navCount.style.display = 'inline-block';

  updateAlertSummary(sorted);
}

function updateAlertSummary(entries) {
  const total   = entries.length;
  const dangers = entries.filter(e => e.status === 'DANGER').length;
  const warns   = entries.filter(e => e.status === 'WARNING').length;
  document.getElementById('sum-total').textContent   = total;
  document.getElementById('sum-danger').textContent  = dangers;
  document.getElementById('sum-warning').textContent = warns;

  if (entries.length > 0) {
    const last = entries[0]; /* already sorted newest first */
    const ago  = timeSince(last.timestamp);
    document.getElementById('sum-last').textContent = ago;
  } else {
    document.getElementById('sum-last').textContent = '--';
  }
}

function timeSince(ts) {
  const seconds = Math.floor((Date.now() - ts) / 1000);
  if (seconds < 60)   return seconds + 's ago';
  if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
  if (seconds < 86400)return Math.floor(seconds / 3600) + 'h ago';
  return Math.floor(seconds / 86400) + 'd ago';
}

/* ===== EXPORT ===== */

/* Cache the last loaded readings so export always reflects what's on screen */
let currentReadingsCache = [];
let currentNodeCache = 1;

function getExportFilename(nodeId, ext) {
  const d = new Date();
  const date = d.getFullYear() + '-' +
    String(d.getMonth() + 1).padStart(2, '0') + '-' +
    String(d.getDate()).padStart(2, '0');
  return `slopeguard_node${nodeId}_${date}.${ext}`;
}

window.exportData = function (format) {
  if (!currentReadingsCache.length) {
    alert('No readings loaded yet. Wait for data to arrive from Firebase.');
    return;
  }

  const nodeId   = currentNodeCache;
  const readings = [...currentReadingsCache]; /* newest-first for export */

  let content, mime, filename;

  if (format === 'csv') {
    const header = 'Date & Time,Temperature (°C),Humidity (%),Soil Moisture (%),Rainfall (mm),Status';
    const rows = readings.map(r => {
      const lv  = getRiskLevel(r.soil_moisture, r.rainfall);
      const lbl = getRiskLabel(lv);
      /* Wrap datetime in quotes to handle commas */
      return [
        `"${r.datetime || r.time || '--'}"`,
        parseFloat(r.temperature).toFixed(1),
        parseFloat(r.humidity).toFixed(1),
        r.soil_moisture,
        parseFloat(r.rainfall).toFixed(2),
        lbl
      ].join(',');
    });
    content  = [header, ...rows].join('\r\n');
    mime     = 'text/csv;charset=utf-8;';
    filename = getExportFilename(nodeId, 'csv');

  } else {
    /* JSON — clean array of raw reading objects */
    const clean = readings.map(r => ({
      datetime:     r.datetime || r.time || '--',
      node_id:      r.node_id  || nodeId,
      temperature:  parseFloat(r.temperature),
      humidity:     parseFloat(r.humidity),
      soil_moisture: r.soil_moisture,
      rainfall:     parseFloat(r.rainfall),
      status:       r.status || getRiskLabel(getRiskLevel(r.soil_moisture, r.rainfall)),
      timestamp:    r.timestamp
    }));
    content  = JSON.stringify(clean, null, 2);
    mime     = 'application/json;charset=utf-8;';
    filename = getExportFilename(nodeId, 'json');
  }

  /* Trigger download */
  const blob = new Blob([content], { type: mime });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};



let unsubLatest = null, unsubHistory = null, unsubNodes = null, unsubAlerts = null;

function unsubscribeAll() {
  if (unsubLatest)  unsubLatest();
  if (unsubHistory) unsubHistory();
  if (unsubNodes)   unsubNodes();
  if (unsubAlerts)  unsubAlerts();
}

function listenToNode(nodeId) {
  if (unsubLatest)  unsubLatest();
  if (unsubHistory) unsubHistory();

  const latestRef = query(ref(db, 'sensor_readings/node_' + nodeId), orderByChild('timestamp'), limitToLast(1));
  unsubLatest = onValue(latestRef, snap => {
    if (!snap.exists()) return;
    updateStatCards(Object.values(snap.val())[0], nodeId);
  });

  const histRef = query(ref(db, 'sensor_readings/node_' + nodeId), orderByChild('timestamp'), limitToLast(20));
  unsubHistory = onValue(histRef, snap => {
    if (!snap.exists()) return;
    const readings = Object.values(snap.val())
      .sort((a, b) => a.timestamp - b.timestamp)
      .map(r => ({
        ...r,
        time:     new Date(r.timestamp).toTimeString().slice(0, 5),
        datetime: new Date(r.timestamp).toLocaleString()
      }));
    /* Keep cache in sync for export */
    currentReadingsCache = [...readings].reverse(); /* newest first */
    currentNodeCache     = nodeId;
    updateCharts(readings);
    updateTable(readings);
  });
}

function listenToNodes() {
  const fallback = [
    { name: 'Node 1', location: 'Lower Slope A', lat: 8.2489, lng: 124.7532, status: 'ACTIVE', alert: 'NORMAL' },
    { name: 'Node 2', location: 'Lower Slope B', lat: 8.2495, lng: 124.7541, status: 'ACTIVE', alert: 'NORMAL' },
    { name: 'Node 3', location: 'Lower Slope C', lat: 8.2501, lng: 124.7550, status: 'ACTIVE', alert: 'NORMAL' },
  ];
  unsubNodes = onValue(ref(db, 'sensor_nodes'), snap => {
    if (!snap.exists()) { refreshMapMarkers(fallback); return; }
    const nodes = Object.entries(snap.val()).map(([k, v]) => ({
      name: v.name || k, location: v.location || '',
      lat: v.lat || 8.2495, lng: v.lng || 124.7541,
      status: v.status || 'ACTIVE', alert: v.alert || 'NORMAL'
    }));
    refreshMapMarkers(nodes);
  }, () => refreshMapMarkers(fallback));
}

function listenToAlertHistory() {
  /* Listen to all alert_history entries, ordered by timestamp, latest 100 */
  const alertRef = query(
    ref(db, 'alert_history'),
    orderByChild('timestamp'),
    limitToLast(100)
  );

  unsubAlerts = onValue(alertRef, snap => {
    if (!snap.exists()) {
      updateAlertHistoryTable([]);
      return;
    }
    const entries = Object.values(snap.val());
    updateAlertHistoryTable(entries);
  });
}

/* ===== NODE SELECT ===== */

window.onNodeChange = function () {
  listenToNode(document.getElementById('node-select').value);
};

/* ===== INIT ===== */

function initApp() {
  updateClock();
  setInterval(updateClock, 1000);
  /* Refresh "last event" time display every minute */
  setInterval(() => {
    const lastEl = document.getElementById('sum-last');
    if (lastEl && lastEl.dataset.ts) {
      lastEl.textContent = timeSince(parseInt(lastEl.dataset.ts));
    }
  }, 60000);

  const now = new Date();
  topbarSub = 'Welcome back, Admin \u00b7 ' + now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  document.getElementById('topbar-sub').textContent = topbarSub;

  initCharts();
  initMap();
  listenToNode(1);
  listenToNodes();
  listenToAlertHistory();
}