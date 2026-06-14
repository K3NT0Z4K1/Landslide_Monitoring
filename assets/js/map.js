const map = L.map('map', { zoomControl: true }).setView([8.3695, 124.8679], 16);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
  maxZoom: 19
}).addTo(map);

let nodeLayer = L.layerGroup().addTo(map);

function getColor(alert, status) {
  if (status === 'OFFLINE') return { fill: '#9ca3af', border: '#6b7280' };
  if (alert  === 'DANGER')  return { fill: '#c0392b', border: '#922b21' };
  if (alert  === 'WARNING') return { fill: '#d97706', border: '#b45309' };
  return { fill: '#0e9fa0', border: '#0a7a7b' };
}

function getBadgeStyle(alert) {
  if (alert === 'DANGER')  return 'background:#fdf0ee;color:#c0392b;';
  if (alert === 'WARNING') return 'background:#fef9ee;color:#d97706;';
  return 'background:#e0f7f7;color:#0e6b6c;';
}

function loadNodes() {
  fetch('../api/get_nodes.php')
    .then(r => r.json())
    .then(nodes => {
      nodeLayer.clearLayers();
      nodes.forEach(node => {
        const c = getColor(node.alert, node.status);
        const marker = L.circleMarker([node.latitude, node.longitude], {
          radius: 11, color: c.border, fillColor: c.fill, fillOpacity: 0.85, weight: 2.5
        }).addTo(nodeLayer);

        marker.bindPopup(`
          <div style="font-family:'DM Sans',sans-serif;min-width:185px;padding:2px 0">
            <div style="font-weight:600;font-size:14px;color:#051414;margin-bottom:8px">${node.node_name}</div>
            <div style="font-size:12.5px;color:#0e3d3e;margin-bottom:3px">Location: ${node.location}</div>
            <div style="font-size:12.5px;color:#0e3d3e;margin-bottom:10px">Status: ${node.status}</div>
            <span style="display:inline-block;font-size:11px;font-weight:500;padding:3px 10px;border-radius:20px;${getBadgeStyle(node.alert)}">${node.alert ?? 'SAFE'}</span>
          </div>`, { maxWidth: 220 });

        if (node.alert === 'DANGER' || node.alert === 'WARNING') {
          L.circleMarker([node.latitude, node.longitude], {
            radius: 19, color: c.fill, fillColor: 'transparent', fillOpacity: 0, weight: 1.5, opacity: 0.35
          }).addTo(nodeLayer);
        }
      });
    })
    .catch(e => console.error(e));
}

const legend = L.control({ position: 'bottomright' });
legend.onAdd = function () {
  const div = L.DomUtil.create('div', 'map-legend');
  div.innerHTML = `
    <h4>Node Status</h4>
    <div><i style="background:#0e9fa0"></i> Safe</div>
    <div><i style="background:#d97706"></i> Warning</div>
    <div><i style="background:#c0392b"></i> Danger</div>
    <div><i style="background:#9ca3af"></i> Offline</div>
  `;
  return div;
};
legend.addTo(map);

loadNodes();
setInterval(loadNodes, 10000);

/* ── Sidebar open/close: disable map interaction on mobile ── */
function disableMapInteraction() {
  if (!map) return;
  map.dragging.disable();
  map.touchZoom.disable();
  map.doubleClickZoom.disable();
  map.scrollWheelZoom.disable();
  map.boxZoom.disable();
  map.keyboard.disable();
  if (map.tap) map.tap.disable();
  document.getElementById('map').style.pointerEvents = 'none';
}

function enableMapInteraction() {
  if (!map) return;
  map.dragging.enable();
  map.touchZoom.enable();
  map.doubleClickZoom.enable();
  map.scrollWheelZoom.enable();
  map.boxZoom.enable();
  map.keyboard.enable();
  if (map.tap) map.tap.enable();
  document.getElementById('map').style.pointerEvents = '';
}

/* toggleSidebar/closeSidebar defined inline in map.php */