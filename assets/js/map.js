const map = L.map('map', {
  zoomControl: true,
  scrollWheelZoom: true
}).setView([8.2495, 124.7541], 15);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
  maxZoom: 19
}).addTo(map);

let nodeLayer = L.layerGroup().addTo(map);

function getColor(node) {
  if (node.status === "OFFLINE") return { fill: "#9ca3af", border: "#6b7280" };
  if (node.alert === "DANGER")   return { fill: "#c0392b", border: "#922b21" };
  if (node.alert === "WARNING")  return { fill: "#d97706", border: "#b45309" };
  return { fill: "#2d7a4f", border: "#1e5c3a" };
}

function getBadgeStyle(alert) {
  if (alert === "DANGER")  return "background:#fdf0ee;color:#c0392b;";
  if (alert === "WARNING") return "background:#fef9ee;color:#d97706;";
  return "background:#eaf4ee;color:#2d7a4f;";
}

function loadNodes() {
  fetch("../api/get_nodes.php")
    .then(res => res.json())
    .then(nodes => {
      nodeLayer.clearLayers();

      nodes.forEach(node => {
        const c = getColor(node);
        const alertLabel = node.alert ?? "NORMAL";
        const badgeStyle = getBadgeStyle(alertLabel);

        const marker = L.circleMarker([node.latitude, node.longitude], {
          radius: 11,
          color: c.border,
          fillColor: c.fill,
          fillOpacity: 0.85,
          weight: 2.5
        }).addTo(nodeLayer);

        marker.bindPopup(`
          <div style="font-family:'DM Sans',sans-serif;min-width:190px;padding:2px 0">
            <div style="font-weight:600;font-size:14px;color:#0f2218;margin-bottom:8px">
              ${node.node_name}
            </div>
            <div style="font-size:12.5px;color:#3d6048;margin-bottom:3px">
              Location: ${node.location}
            </div>
            <div style="font-size:12.5px;color:#3d6048;margin-bottom:10px">
              Status: ${node.status}
            </div>
            <span style="
              display:inline-block;
              font-size:11.5px;font-weight:500;
              padding:3px 10px;border-radius:20px;
              ${badgeStyle}
            ">${alertLabel}</span>
          </div>
        `, { maxWidth: 230 });

        /* Outer ring for elevated alerts */
        if (alertLabel === "DANGER" || alertLabel === "WARNING") {
          L.circleMarker([node.latitude, node.longitude], {
            radius: 19,
            color: c.fill,
            fillColor: 'transparent',
            fillOpacity: 0,
            weight: 1.5,
            opacity: 0.35
          }).addTo(nodeLayer);
        }
      });
    })
    .catch(err => console.error("Map error:", err));
}

/* Legend */
const legend = L.control({ position: "bottomright" });

legend.onAdd = function () {
  const div = L.DomUtil.create("div", "map-legend");
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

loadNodes();
setInterval(loadNodes, 10000);