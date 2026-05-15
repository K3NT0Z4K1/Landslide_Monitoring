/* ============================================================
   AUTH
============================================================ */
const ADMIN_USER = "admin";
const ADMIN_PASS = "admin123";

function handleLogin() {
  const u = document.getElementById('username').value.trim();
  const p = document.getElementById('password').value;
  const err = document.getElementById('login-error');

  if (u === ADMIN_USER && p === ADMIN_PASS) {
    document.getElementById('login-page').style.display = 'none';
    document.getElementById('app').style.display = 'block';
    err.style.display = 'none';
    initApp();
  } else {
    err.style.display = 'flex';
  }
}

function handleLogout() {
  document.getElementById('app').style.display = 'none';
  document.getElementById('login-page').style.display = 'flex';
  document.getElementById('username').value = '';
  document.getElementById('password').value = '';
}

// Allow Enter key on login
document.getElementById('password').addEventListener('keydown', e => {
  if (e.key === 'Enter') handleLogin();
});

document.getElementById('username').addEventListener('keydown', e => {
  if (e.key === 'Enter') handleLogin();
});

/* ============================================================
   TAB NAVIGATION
============================================================ */
function switchTab(tab, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  document.getElementById('tab-' + tab).classList.add('active');
  btn.classList.add('active');

  const titles = { dashboard: 'Dashboard', map: 'Sensor Map' };
  const subs   = { dashboard: 'Welcome back, Admin', map: 'Live node locations & alert status' };

  document.getElementById('topbar-title').textContent = titles[tab];
  document.getElementById('topbar-sub').textContent   = subs[tab];
  document.getElementById('node-select-wrap').style.display = tab === 'dashboard' ? 'flex' : 'none';

  if (tab === 'map') {
    setTimeout(() => map.invalidateSize(), 100);
  }
}

/* ============================================================
   CLOCK
============================================================ */
function updateClock() {
  document.getElementById('clock').textContent = new Date().toTimeString().slice(0, 8);
}

/* ============================================================
   THRESHOLDS
============================================================ */
const SOIL_WARN   = 500;
const SOIL_DANGER = 700;
const RAIN_WARN   = 10;
const RAIN_DANGER = 20;

function getRiskLevel(soil, rain) {
  if (soil > SOIL_DANGER && rain > RAIN_DANGER) return 'danger';
  if (soil > SOIL_WARN   && rain > RAIN_WARN)   return 'warning';
  return 'normal';
}

function getRiskLabel(level) {
  if (level === 'danger')  return 'HIGH RISK';
  if (level === 'warning') return 'WARNING';
  return 'NORMAL';
}

/* ============================================================
   CHARTS
============================================================ */
let tempChart, rainChart, soilChart;

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#7a9e85';

const baseOpts = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: {
      position: 'top',
      labels: { font: { size: 11, weight: '500' }, usePointStyle: true, pointStyleWidth: 7, padding: 14 }
    },
    tooltip: {
      backgroundColor: 'rgba(17,34,24,0.92)',
      titleColor: '#c2e0cc',
      bodyColor: '#e8f4ec',
      borderColor: 'rgba(140,196,160,0.2)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
    }
  },
  scales: {
    x: {
      grid: { color: 'rgba(30,61,40,0.07)' },
      ticks: { font: { size: 10 }, color: '#7a9e85' },
      border: { color: 'rgba(30,61,40,0.1)' }
    },
    y: {
      grid: { color: 'rgba(30,61,40,0.07)' },
      ticks: { font: { size: 10 }, color: '#7a9e85' },
      border: { color: 'rgba(30,61,40,0.1)' },
      beginAtZero: false
    }
  }
};

function initCharts() {
  const emptyLabels = Array(10).fill('--');
  const emptyData   = Array(10).fill(null);

  tempChart = new Chart(document.getElementById('tempChart'), {
    type: 'line',
    data: {
      labels: emptyLabels,
      datasets: [
        {
          label: 'Temperature (°C)',
          data: emptyData,
          borderColor: '#c0392b',
          backgroundColor: 'rgba(192,57,43,0.07)',
          borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true, yAxisID: 'y'
        },
        {
          label: 'Humidity (%)',
          data: emptyData,
          borderColor: '#1d4ed8',
          backgroundColor: 'rgba(29,78,216,0.05)',
          borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true, yAxisID: 'y1'
        }
      ]
    },
    options: {
      ...baseOpts,
      scales: {
        ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position: 'left',  title: { display: true, text: '°C', color: '#7a9e85', font: { size: 10 } } },
        y1: { ...baseOpts.scales.y, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: '#7a9e85', font: { size: 10 } } }
      }
    }
  });

  rainChart = new Chart(document.getElementById('rainChart'), {
    type: 'bar',
    data: {
      labels: emptyLabels,
      datasets: [{ label: 'Rainfall (mm)', data: emptyData, backgroundColor: 'rgba(45,90,58,0.55)', borderColor: '#2d5a3a', borderWidth: 1.5, borderRadius: 4 }]
    },
    options: { ...baseOpts }
  });

  soilChart = new Chart(document.getElementById('soilChart'), {
    type: 'line',
    data: {
      labels: emptyLabels,
      datasets: [{
        label: 'Soil Moisture',
        data: emptyData,
        borderColor: '#2d7a4f',
        backgroundColor: 'rgba(45,122,79,0.08)',
        borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 5, tension: 0.4, fill: true
      }]
    },
    options: { ...baseOpts }
  });
}

/* ============================================================
   MAP
============================================================ */
let map, nodeLayer;

function initMap() {
  map = L.map('map', { zoomControl: true }).setView([8.2495, 124.7541], 15);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  nodeLayer = L.layerGroup().addTo(map);

  /* Placeholder markers from DB seed data */
  const nodes = [
    { id: 1, name: 'Node 1', location: 'Lower Slope A', lat: 8.2489, lng: 124.7532, status: 'ACTIVE', alert: 'NORMAL' },
    { id: 2, name: 'Node 2', location: 'Lower Slope B', lat: 8.2495, lng: 124.7541, status: 'ACTIVE', alert: 'NORMAL' },
    { id: 3, name: 'Node 3', location: 'Lower Slope C', lat: 8.2501, lng: 124.7550, status: 'ACTIVE', alert: 'NORMAL' },
  ];

  nodes.forEach(node => placeMarker(node));

  const legend = L.control({ position: 'bottomright' });
  legend.onAdd = function () {
    const div = L.DomUtil.create('div', 'map-legend');
    div.innerHTML = `
      <h4>Node Status</h4>
      <div><i style="background:#2d7a4f"></i> Normal</div>
      <div><i style="background:#d97706"></i> Warning</div>
      <div><i style="background:#c0392b"></i> Danger</div>
      <div><i style="background:#9ca3af"></i> Offline</div>
    `;
    return div;
  };
  legend.addTo(map);
}

function getNodeColor(alert, status) {
  if (status === 'OFFLINE') return { fill: '#9ca3af', border: '#6b7280' };
  if (alert === 'DANGER')   return { fill: '#c0392b', border: '#922b21' };
  if (alert === 'WARNING')  return { fill: '#d97706', border: '#b45309' };
  return { fill: '#2d7a4f', border: '#1e5c3a' };
}

function getBadgeStyle(alert) {
  if (alert === 'DANGER')  return 'background:#fdf0ee;color:#c0392b;';
  if (alert === 'WARNING') return 'background:#fef9ee;color:#d97706;';
  return 'background:#eaf4ee;color:#2d7a4f;';
}

function placeMarker(node) {
  const c = getNodeColor(node.alert, node.status);
  const marker = L.circleMarker([node.lat, node.lng], {
    radius: 11, color: c.border, fillColor: c.fill, fillOpacity: 0.85, weight: 2.5
  }).addTo(nodeLayer);

  marker.bindPopup(`
    <div style="font-family:'DM Sans',sans-serif;min-width:185px;padding:2px 0">
      <div style="font-weight:600;font-size:14px;color:#0f2218;margin-bottom:8px">${node.name}</div>
      <div style="font-size:12.5px;color:#3d6048;margin-bottom:3px">Location: ${node.location}</div>
      <div style="font-size:12.5px;color:#3d6048;margin-bottom:10px">Status: ${node.status}</div>
      <span style="display:inline-block;font-size:11px;font-weight:500;padding:3px 10px;border-radius:20px;${getBadgeStyle(node.alert)}">${node.alert}</span>
    </div>
  `, { maxWidth: 220 });

  if (node.alert === 'DANGER' || node.alert === 'WARNING') {
    L.circleMarker([node.lat, node.lng], {
      radius: 19, color: c.fill, fillColor: 'transparent', fillOpacity: 0, weight: 1.5, opacity: 0.35
    }).addTo(nodeLayer);
  }
}

/* ============================================================
   NODE SELECT
============================================================ */
function onNodeChange() {
  // Will connect to Firebase later
  console.log('Node changed to:', document.getElementById('node-select').value);
}

/* ============================================================
   INIT APP
============================================================ */
function initApp() {
  updateClock();
  setInterval(updateClock, 1000);

  const now = new Date();
  document.getElementById('topbar-sub').textContent =
    'Welcome back, Admin \u00b7 ' + now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

  initCharts();
  initMap();
}