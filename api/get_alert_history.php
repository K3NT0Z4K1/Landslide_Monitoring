<?php
/* ============================================================
   SlopeGuard — Get Alert History
   Used by: alert history tab (script.js)
============================================================ */

include "../config/db.php";
header("Content-Type: application/json");

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$stmt = $conn->prepare("
  SELECT
    ah.id,
    ah.node_id,
    n.node_name,
    ah.soil_moisture,
    ah.rainfall,
    ah.status,
    ah.created_at AS datetime
  FROM alert_history ah
  LEFT JOIN sensor_nodes n ON n.id = ah.node_id
  ORDER BY ah.created_at DESC
  LIMIT ?
");

$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
  $data[] = $row;
}

echo json_encode($data);
?>
