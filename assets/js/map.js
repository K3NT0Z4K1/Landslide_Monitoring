/* ===== INITIALIZE MAP ===== */
const map = L.map('map', {
  zoomControl: true,
  scrollWheelZoom: true
}).setView([8.2495, 124.7541], 15);

/* ===== TILE LAYER ===== */
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
  maxZoom: 19
}).addTo(map);

let nodeLayer = L.layerGroup().addTo(map);

/* ===== MARKER COLOR ===== */
function getColor(node) {
  if (node.status === "OFFLINE") return { fill: "#9ca3af", border: "#6b7280" };
  if (node.alert === "DANGER")   return { fill: "#c0392b", border: "#922b21" };
  if (node.alert === "WARNING")  return { fill: "#d97706", border: "#b45309" };
  return { fill: "#2d7a4f", border: "#1e5c3a" };
}

function getBadgeClass(alert) {
  if (alert === "DANGER")  return "danger";
  if (alert === "WARNING") return "warning";
  return "normal";
}

/* ===== LOAD NODES ===== */
function loadNodes() {
  fetch("../api/get_nodes.php")
    .then(res => res.json())
    .then(nodes => {
      nodeLayer.clearLayers();

      nodes.forEach(node => {
        const c = getColor(node);
        const alertLabel = node.alert ?? "NORMAL";
        const cls = getBadgeClass(alertLabel);

        const marker = L.circleMarker([node.latitude, node.longitude], {
          radius: 11,
          color: c.border,
          fillColor: c.fill,
          fillOpacity: 0.85,
          weight: 2.5
        }).addTo(nodeLayer);

        marker.bindPopup(`
          <div style="font-family:'DM Sans',sans-serif;min-width:180px;padding:4px 0">
            <div style="font-weight:600;font-size:14px;color:#0f2218;margin-bottom:8px">
              ${node.node_name}
            </div>
            <div style="font-size:12.5px;color:#3d6048;margin-bottom:4px">
              📍 ${node.location}
            </div>
            <div style="font-size:12.5px;color:#3d6048;margin-bottom:8px">
              🔌 ${node.status}
            </div>
            <span style="
              display:inline-flex;align-items:center;gap:5px;
              font-size:12px;font-weight:500;padding:3px 10px;border-radius:20px;
              background:${cls==='danger'?'#fdf0ee':cls==='warning'?'#fef9ee':'#eaf4ee'};
              color:${cls==='danger'?'#c0392b':cls==='warning'?'#d97706':'#2d7a4f'};
            ">
              ${alertLabel}
            </span>
          </div>
        `, { maxWidth: 220 });

        /* Pulse ring for danger */
        if (alertLabel === "DANGER" || alertLabel === "WARNING") {
          L.circleMarker([node.latitude, node.longitude], {
            radius: 18,
            color: c.fill,
            fillColor: 'transparent',
            fillOpacity: 0,
            weight: 1.5,
            opacity: 0.4
          }).addTo(nodeLayer);
        }
      });
    })
    .catch(err => console.error("Map error:", err));
}

/* ===== LEGEND ===== */
const legend = L.control({ position: "bottomright" });

legend.onAdd = function () {
  const div = L.DomUtil.create("div", "map-legend");
  div.innerHTML = `
    <h4>Node Status</h4>
    <div><i style="background:#2d7a4f;border-radius:50%;width:10px;height:10px;display:inline-block;margin-right:7px;vertical-align:middle"></i>Normal</div>
    <div><i style="background:#d97706;border-radius:50%;width:10px;height:10px;display:inline-block;margin-right:7px;vertical-align:middle"></i>Warning</div>
    <div><i style="background:#c0392b;border-radius:50%;width:10px;height:10px;display:inline-block;margin-right:7px;vertical-align:middle"></i>Danger</div>
    <div><i style="background:#9ca3af;border-radius:50%;width:10px;height:10px;display:inline-block;margin-right:7px;vertical-align:middle"></i>Offline</div>
  `;
  return div;
};

legend.addTo(map);

loadNodes();
setInterval(loadNodes, 10000);