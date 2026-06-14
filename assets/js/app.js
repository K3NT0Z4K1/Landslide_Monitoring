/* =========================
   THRESHOLD CONSTANTS
========================= */

const SOIL_WARNING = 500;
const SOIL_DANGER  = 700;
const RAIN_WARNING = 10;
const RAIN_DANGER  = 20;

/* =========================
   LOAD LIVE SENSOR DATA
========================= */

function loadData() {

fetch("../api/get_latest.php?node=" + NODE_ID)

.then(res => res.json())

.then(data => {

if (!data || data.error) return;

/* ----- UPDATE SENSOR VALUES ----- */

document.getElementById("temp").innerText =
data.temperature + " °C";

document.getElementById("humidity").innerText =
data.humidity + " %";

document.getElementById("soil").innerText =
data.soil_moisture;

document.getElementById("rain").innerText =
data.rainfall + " mm";

/* =========================
   ALERT LEVEL LOGIC
========================= */

const alertBox = document.getElementById("alert");

let soil = parseFloat(data.soil_moisture);
let rain = parseFloat(data.rainfall);

/* HIGH RISK */

if (soil >= SOIL_DANGER && rain >= RAIN_DANGER) {

alertBox.innerText = "🔴 HIGH RISK";
alertBox.className = "alert-card red";

}

/* WARNING */

else if (soil >= SOIL_WARNING && rain >= RAIN_WARNING) {

alertBox.innerText = "🟠 WARNING";
alertBox.className = "alert-card orange";

}

/* NORMAL */

else {

alertBox.innerText = "🟢 NORMAL";
alertBox.className = "alert-card green";

}

})

.catch(err => console.error("Live data error:", err));

}

/* =========================
   AUTO REFRESH
========================= */

loadData();
setInterval(loadData, 3000);

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarBackdrop').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarBackdrop').classList.remove('open');
}