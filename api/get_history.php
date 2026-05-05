<?php

/* ===============================
   API: Get Sensor History
   Used by: charts.js
================================ */

require_once "../config/db.php";

header("Content-Type: application/json");

/* ===============================
   VALIDATE INPUT
================================ */

$node  = isset($_GET['node'])  ? (int)$_GET['node']  : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($node < 1 || $node > 3) {
    echo json_encode(["error" => "Invalid node"]);
    exit;
}

/* ===============================
   FETCH DATA
================================ */

$sql = "
SELECT
    temperature,
    humidity,
    soil_moisture,
    rainfall,
    DATE_FORMAT(created_at,'%H:%i') AS time
FROM sensor_readings
WHERE node_id = ?
ORDER BY created_at DESC
LIMIT ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["error" => "Query failed"]);
    exit;
}

$stmt->bind_param("ii", $node, $limit);
$stmt->execute();

$result = $stmt->get_result();

/* ===============================
   BUILD ARRAY
================================ */

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

/* reverse so chart shows old → new */

$data = array_reverse($data);

/* ===============================
   RETURN JSON
================================ */

echo json_encode($data);

?>