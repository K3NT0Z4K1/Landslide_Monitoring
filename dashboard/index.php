<?php
require_once "../auth/auth_check.php";
require_once "../config/db.php";

/* ------------------------------
   Validate Sensor Node
--------------------------------*/
$node = isset($_GET['node']) ? (int)$_GET['node'] : 1;
if ($node < 1 || $node > 3) {
    $node = 1;
}

/* ------------------------------
   Fetch Recent Sensor Data
--------------------------------*/
$stmt = $conn->prepare("
SELECT temperature, humidity, soil_moisture, rainfall, created_at
FROM sensor_readings
WHERE node_id = ?
ORDER BY created_at DESC
LIMIT 10
");

$stmt->bind_param("i", $node);
$stmt->execute();
$data = $stmt->get_result();

/* Latest Reading */
$latest = $data->fetch_assoc();
$data->data_seek(0);

/* ------------------------------
   ALERT LEVEL LOGIC
--------------------------------*/
$soil = $latest['soil_moisture'] ?? 0;
$rain = $latest['rainfall'] ?? 0;

$alert = "NORMAL";
$alert_color = "green";

if($soil > 700 && $rain > 20){
$alert = "HIGH RISK";
$alert_color = "red";
}
elseif($soil > 500 && $rain > 10){
$alert = "WARNING";
$alert_color = "orange";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Landslide Monitoring Dashboard</title>

<link rel="stylesheet" href="../assets/css/style.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

<header class="top-header">

<h1>⛰ Landslide Monitoring System</h1>

<a href="../auth/logout.php" class="logout-btn">Logout</a>

</header>

<main class="container">

<!-- NODE SELECT -->
<section class="controls">

<form method="GET">

<label>Sensor Node:</label>

<select name="node" onchange="this.form.submit()">

<option value="1" <?= $node==1?'selected':'' ?>>Node 1</option>
<option value="2" <?= $node==2?'selected':'' ?>>Node 2</option>
<option value="3" <?= $node==3?'selected':'' ?>>Node 3</option>

</select>

</form>

</section>

<!-- DASHBOARD CARDS -->

<section class="grid">

<div class="card">
<h3>🌡 Temperature</h3>
<p id="temp"><?= $latest['temperature'] ?? '--' ?> °C</p>
</div>

<div class="card">
<h3>💧 Humidity</h3>
<p id="humidity"><?= $latest['humidity'] ?? '--' ?> %</p>
</div>

<div class="card">
<h3>🌱 Soil Moisture</h3>
<p id="soil"><?= $latest['soil_moisture'] ?? '--' ?></p>
</div>

<div class="card">
<h3>🌧 Rainfall</h3>
<p id="rain"><?= $latest['rainfall'] ?? '--' ?> mm</p>
</div>

<div class="card alert-card <?= $alert_color ?>">
<h3>🚨 Landslide Risk</h3>
<p><?= $alert ?></p>
</div>

<div class="card prediction">
<h3>🧠 AI Prediction</h3>
<p id="prediction">Analyzing...</p>
</div>

</section>

<!-- CHARTS -->

<section class="charts">

<canvas id="tempChart"></canvas>
<canvas id="rainChart"></canvas>
<canvas id="soilChart"></canvas>

</section>

<!-- TABLE -->

<section class="table-section">

<h2>📊 Recent Sensor Data</h2>

<table>

<thead>

<tr>
<th>Date & Time</th>
<th>Temp</th>
<th>Humidity</th>
<th>Soil</th>
<th>Rain</th>
</tr>

</thead>

<tbody>

<?php while($row = $data->fetch_assoc()): ?>

<tr>

<td><?= $row['created_at'] ?></td>
<td><?= $row['temperature'] ?></td>
<td><?= $row['humidity'] ?></td>
<td><?= $row['soil_moisture'] ?></td>
<td><?= $row['rainfall'] ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</section>

</main>

<script>
const NODE_ID = <?= $node ?>;
</script>

<script src="../assets/js/charts.js"></script>
<script src="../assets/js/app.js"></script>

</body>
</html>