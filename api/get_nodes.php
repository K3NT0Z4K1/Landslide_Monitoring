<?php
/* ============================================================
   SlopeGuard — Get All Sensor Nodes
   Used by: map.js
============================================================ */

include "../config/db.php";
header("Content-Type: application/json");

$result = $conn->query("
  SELECT id, node_name, latitude, longitude, location, status, alert, last_seen
  FROM sensor_nodes
  ORDER BY id ASC
");

$nodes = [];
while ($row = $result->fetch_assoc()) {
  $nodes[] = $row;
}

echo json_encode($nodes);
?>
