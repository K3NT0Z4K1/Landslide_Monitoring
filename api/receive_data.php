<?php

include "../config/db.php";

/* ---------------------------
   GET SENSOR DATA
--------------------------- */

$node = $_POST['node_id'] ?? null;
$temp = $_POST['temperature'] ?? null;
$hum  = $_POST['humidity'] ?? null;
$soil = $_POST['soil'] ?? null;
$rain = $_POST['rain'] ?? null;

/* ---------------------------
   VALIDATION
--------------------------- */

if(!$node){
    echo "ERROR: Node ID missing";
    exit;
}

/* ---------------------------
   INSERT SENSOR DATA
--------------------------- */

$stmt = $conn->prepare("
INSERT INTO sensor_readings
(node_id, temperature, humidity, soil_moisture, rainfall)
VALUES (?, ?, ?, ?, ?)
");

/* 
i = integer
d = decimal
*/

$stmt->bind_param("idiii",
    $node,
    $temp,
    $hum,
    $soil,
    $rain
);

$stmt->execute();

/* ---------------------------
   UPDATE NODE STATUS
--------------------------- */

$status = $conn->prepare("
UPDATE sensor_nodes
SET last_seen = NOW(), status='ACTIVE'
WHERE id=?
");

$status->bind_param("i",$node);
$status->execute();

/* ---------------------------
   RESPONSE
--------------------------- */

echo "DATA RECEIVED";

?>