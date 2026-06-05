let tempChart, rainChart, soilChart;

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color       = '#4a8a8b';

const baseOpts = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: {
      position: 'top',
      labels: { font: { size: 11, weight: '500' }, usePointStyle: true, pointStyleWidth: 7, padding: 14 }
    },
    tooltip: {
      backgroundColor: 'rgba(5,20,20,0.92)',
      titleColor: '#a8ede6', bodyColor: '#e0f7f7',
      borderColor: 'rgba(14,159,160,0.2)', borderWidth: 1,
      padding: 10, cornerRadius: 8
    }
  },
  scales: {
    x: { grid: { color: 'rgba(14,159,160,0.07)' }, ticks: { font: { size: 10 }, color: '#4a8a8b' }, border: { color: 'rgba(14,159,160,0.1)' } },
    y: { grid: { color: 'rgba(14,159,160,0.07)' }, ticks: { font: { size: 10 }, color: '#4a8a8b' }, border: { color: 'rgba(14,159,160,0.1)' }, beginAtZero: false }
  }
};

function initCharts() {
  const el = Array(10).fill('--'), ed = Array(10).fill(null);

  tempChart = new Chart(document.getElementById('tempChart'), {
    type: 'line',
    data: {
      labels: [...el],
      datasets: [
        { label: 'Temperature (°C)', data: [...ed], borderColor: '#c0392b', backgroundColor: 'rgba(192,57,43,0.07)', borderWidth: 2.5, pointRadius: 3, tension: 0.4, fill: true, yAxisID: 'y'  },
        { label: 'Humidity (%)',      data: [...ed], borderColor: '#1d4ed8', backgroundColor: 'rgba(29,78,216,0.05)',  borderWidth: 2.5, pointRadius: 3, tension: 0.4, fill: true, yAxisID: 'y1' }
      ]
    },
    options: {
      ...baseOpts,
      scales: {
        ...baseOpts.scales,
        y:  { ...baseOpts.scales.y, position: 'left',  title: { display: true, text: '°C', color: '#4a8a8b', font: { size: 10 } } },
        y1: { ...baseOpts.scales.y, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: '#4a8a8b', font: { size: 10 } } }
      }
    }
  });

  rainChart = new Chart(document.getElementById('rainChart'), {
    type: 'bar',
    data: {
      labels: [...el],
      datasets: [{ label: 'Rainfall (mm)', data: [...ed], backgroundColor: 'rgba(14,107,108,0.55)', borderColor: '#0e6b6c', borderWidth: 1.5, borderRadius: 4 }]
    },
    options: { ...baseOpts }
  });

  soilChart = new Chart(document.getElementById('soilChart'), {
    type: 'line',
    data: {
      labels: [...el],
      datasets: [{ label: 'Soil Moisture (%)', data: [...ed], borderColor: '#0e9fa0', backgroundColor: 'rgba(14,159,160,0.08)', borderWidth: 2.5, pointRadius: 3, tension: 0.4, fill: true }]
    },
    options: { ...baseOpts }
  });
}

function loadCharts() {
  fetch('../api/get_history.php?node=' + NODE_ID + '&limit=20')
    .then(r => r.json())
    .then(data => {
      const labels = data.map(d => d.datetime ? d.datetime.slice(11, 19) : d.time);
      const tData  = data.map(d => parseFloat(d.temperature));
      const hData  = data.map(d => parseFloat(d.humidity));
      const rData  = data.map(d => parseFloat(d.rainfall));
      const sData  = data.map(d => parseFloat(d.soil_moisture));

      tempChart.data.labels = labels;
      tempChart.data.datasets[0].data = tData;
      tempChart.data.datasets[1].data = hData;

      rainChart.data.labels = labels;
      rainChart.data.datasets[0].data = rData;
      rainChart.data.datasets[0].backgroundColor = rData.map(v =>
        v > 25 ? 'rgba(192,57,43,0.72)' :
        v > 10 ? 'rgba(217,119,6,0.72)'  :
                 'rgba(14,107,108,0.62)'
      );

      soilChart.data.labels = labels;
      soilChart.data.datasets[0].data = sData;

      tempChart.update('none');
      rainChart.update('none');
      soilChart.update('none');
    })
    .catch(e => console.error(e));
}

initCharts();
loadCharts();
setInterval(loadCharts, 10000);
