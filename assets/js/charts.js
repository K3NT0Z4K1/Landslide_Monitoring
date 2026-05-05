let tempChart, humChart, rainChart, soilChart;

function loadCharts(){

fetch("../api/get_history.php?node=" + NODE_ID)
.then(res => res.json())
.then(data => {

const labels = data.map(d => d.time);
const tempData = data.map(d => d.temperature);
const humData  = data.map(d => d.humidity);
const rainData = data.map(d => d.rainfall);
const soilData = data.map(d => d.soil_moisture);

/* destroy old charts */
if(tempChart) tempChart.destroy();
if(humChart) humChart.destroy();
if(rainChart) rainChart.destroy();
if(soilChart) soilChart.destroy();

/* shared style like your example chart */
const baseOptions = {
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
position:'top',
labels:{
font:{size:14, weight:'bold'}
}
}
},

scales:{
x:{
grid:{color:'#e5e7eb'},
ticks:{font:{size:12}}
},
y:{
grid:{color:'#e5e7eb'},
ticks:{font:{size:12}},
beginAtZero:false
}
}
};

/* ===== TEMPERATURE ===== */

tempChart = new Chart(document.getElementById("tempChart"),{
type:'line',
data:{
labels:labels,
datasets:[{
label:'Temperature (°C)',
data:tempData,
borderColor:'#e11d48',
backgroundColor:'#e11d48',
borderWidth:3,
pointRadius:4,
pointHoverRadius:6,
tension:0.3,
fill:false
}]
},
options:{
...baseOptions,
plugins:{
...baseOptions.plugins,
title:{
display:true,
text:'Temperature',
font:{size:18, weight:'bold'}
}
}
}
});

/* ===== HUMIDITY ===== */

humChart = new Chart(document.getElementById("humChart"),{
type:'line',
data:{
labels:labels,
datasets:[{
label:'Humidity (%)',
data:humData,
borderColor:'#1d4ed8',
backgroundColor:'#1d4ed8',
borderWidth:3,
pointRadius:4,
pointHoverRadius:6,
tension:0.3,
fill:false
}]
},
options:{
...baseOptions,
plugins:{
...baseOptions.plugins,
title:{
display:true,
text:'Humidity',
font:{size:18, weight:'bold'}
}
}
}
});

/* ===== RAINFALL ===== */

rainChart = new Chart(document.getElementById("rainChart"),{
type:'bar',
data:{
labels:labels,
datasets:[{
label:'Rainfall (mm)',
data:rainData,
backgroundColor:'#2563eb'
}]
},
options:{
...baseOptions,
plugins:{
...baseOptions.plugins,
title:{
display:true,
text:'Rainfall',
font:{size:18, weight:'bold'}
}
}
}
});

/* ===== SOIL MOISTURE ===== */

soilChart = new Chart(document.getElementById("soilChart"),{
type:'line',
data:{
labels:labels,
datasets:[{
label:'Soil Moisture',
data:soilData,
borderColor:'#16a34a',
backgroundColor:'#16a34a',
borderWidth:3,
pointRadius:4,
pointHoverRadius:6,
tension:0.3,
fill:false
}]
},
options:{
...baseOptions,
plugins:{
...baseOptions.plugins,
title:{
display:true,
text:'Soil Moisture',
font:{size:18, weight:'bold'}
}
}
}
});

})
.catch(err => console.error(err));

}

loadCharts();
setInterval(loadCharts,10000);