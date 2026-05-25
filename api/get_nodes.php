<?php
include "../config/db.php";

$q = $conn->query("
  SELECT n.*, 
  (SELECT alert_level 
   FROM alerts 
   WHERE node_id=n.id 
   ORDER BY created_at DESC 
   LIMIT 1) as alert
  FROM sensor_nodes n
");

$nodes = [];
while($r = $q->fetch_assoc()){
  $nodes[] = $r;
}

echo json_encode($nodes);
