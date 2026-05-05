/* =========================
   INITIALIZE MAP
========================= */
const map = L.map('map').setView([8.2495, 124.7541], 15);

/* =========================
   MAP TILE LAYER
========================= */
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

/* =========================
   MARKER GROUP
========================= */
let nodeLayer = L.layerGroup().addTo(map);

/* =========================
   GET MARKER COLOR
========================= */
function getMarkerColor(node) {
  if (node.status === "OFFLINE") return "gray";
  if (node.alert === "DANGER") return "red";
  if (node.alert === "WARNING") return "orange";
  return "green";
}

/* =========================
   LOAD NODE LOCATIONS
========================= */
function loadNodes() {

  fetch("../api/get_nodes.php")
    .then(res => res.json())
    .then(nodes => {

      nodeLayer.clearLayers();

      nodes.forEach(node => {

        const color = getMarkerColor(node);

        const marker = L.circleMarker(
          [node.latitude, node.longitude],
          {
            radius: 10,
            color: color,
            fillColor: color,
            fillOpacity: 0.8
          }
        ).addTo(nodeLayer);

        marker.bindPopup(`
          <strong>${node.node_name}</strong><br>
          📍 ${node.location}<br>
          🔌 Status: ${node.status}<br>
          🚨 Alert: ${node.alert ?? "NORMAL"}
        `);
      });

    })
    .catch(err => console.error("Map loading error:", err));
}

/* =========================
   MAP LEGEND
========================= */
const legend = L.control({ position: "bottomright" });

legend.onAdd = function () {
  const div = L.DomUtil.create("div", "map-legend");
  div.innerHTML = `
    <h4>Node Status</h4>
    <i style="background:green"></i> Normal<br>
    <i style="background:orange"></i> Warning<br>
    <i style="background:red"></i> Danger<br>
    <i style="background:gray"></i> Offline
  `;
  return div;
};

legend.addTo(map);

/* =========================
   AUTO REFRESH
========================= */
loadNodes();
setInterval(loadNodes, 10000);
