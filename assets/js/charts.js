let tempChart, humChart, rainChart, soilChart;

/* ===== SHARED CHART DEFAULTS ===== */
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#7a9e85';

const baseOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: {
      position: 'top',
      labels: {
        font: { size: 12, weight: '500' },
        usePointStyle: true,
        pointStyleWidth: 8,
        padding: 16,
      }
    },
    tooltip: {
      backgroundColor: 'rgba(17,34,24,0.92)',
      titleColor: '#c2e0cc',
      bodyColor: '#e8f4ec',
      borderColor: 'rgba(140,196,160,0.2)',
      borderWidth: 1,
      padding: 12,
      cornerRadius: 8,
    }
  },
  scales: {
    x: {
      grid: { color: 'rgba(30,61,40,0.08)' },
      ticks: { font: { size: 11 }, color: '#7a9e85' },
      border: { color: 'rgba(30,61,40,0.1)' }
    },
    y: {
      grid: { color: 'rgba(30,61,40,0.08)' },
      ticks: { font: { size: 11 }, color: '#7a9e85' },
      border: { color: 'rgba(30,61,40,0.1)' },
      beginAtZero: false
    }
  }
};

function loadCharts() {
  fetch("../api/get_history.php?node=" + NODE_ID)
    .then(res => res.json())
    .then(data => {

      const labels   = data.map(d => d.time);
      const tempData = data.map(d => parseFloat(d.temperature));
      const humData  = data.map(d => parseFloat(d.humidity));
      const rainData = data.map(d => parseFloat(d.rainfall));
      const soilData = data.map(d => parseFloat(d.soil_moisture));

      if (tempChart) tempChart.destroy();
      if (humChart)  humChart.destroy();
      if (rainChart) rainChart.destroy();
      if (soilChart) soilChart.destroy();

      /* ===== TEMPERATURE + HUMIDITY (combined) ===== */
      tempChart = new Chart(document.getElementById("tempChart"), {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Temperature (°C)',
              data: tempData,
              borderColor: '#c0392b',
              backgroundColor: 'rgba(192,57,43,0.08)',
              borderWidth: 2.5,
              pointRadius: 3,
              pointHoverRadius: 6,
              tension: 0.4,
              fill: true,
              yAxisID: 'y'
            },
            {
              label: 'Humidity (%)',
              data: humData,
              borderColor: '#1d4ed8',
              backgroundColor: 'rgba(29,78,216,0.06)',
              borderWidth: 2.5,
              pointRadius: 3,
              pointHoverRadius: 6,
              tension: 0.4,
              fill: true,
              yAxisID: 'y1'
            }
          ]
        },
        options: {
          ...baseOptions,
          scales: {
            ...baseOptions.scales,
            y: {
              ...baseOptions.scales.y,
              position: 'left',
              title: { display: true, text: '°C', color: '#7a9e85', font: { size: 11 } }
            },
            y1: {
              ...baseOptions.scales.y,
              position: 'right',
              grid: { drawOnChartArea: false },
              title: { display: true, text: '%', color: '#7a9e85', font: { size: 11 } }
            }
          }
        }
      });

      /* ===== RAINFALL ===== */
      rainChart = new Chart(document.getElementById("rainChart"), {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Rainfall (mm)',
            data: rainData,
            backgroundColor: rainData.map(v =>
              v > 20 ? 'rgba(192,57,43,0.75)' :
              v > 10 ? 'rgba(217,119,6,0.75)' :
              'rgba(45,90,58,0.65)'
            ),
            borderColor: rainData.map(v =>
              v > 20 ? '#c0392b' : v > 10 ? '#d97706' : '#2d5a3a'
            ),
            borderWidth: 1.5,
            borderRadius: 4,
          }]
        },
        options: { ...baseOptions }
      });

      /* ===== SOIL MOISTURE ===== */
      soilChart = new Chart(document.getElementById("soilChart"), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Soil Moisture',
            data: soilData,
            borderColor: '#2d7a4f',
            backgroundColor: 'rgba(45,122,79,0.10)',
            borderWidth: 2.5,
            pointRadius: 3,
            pointHoverRadius: 6,
            tension: 0.4,
            fill: true,
            segment: {
              borderColor: ctx => {
                const v = ctx.p1.parsed.y;
                return v > 700 ? '#c0392b' : v > 500 ? '#d97706' : '#2d7a4f';
              }
            }
          }]
        },
        options: {
          ...baseOptions,
          plugins: {
            ...baseOptions.plugins,
            annotation: {
              annotations: {
                warnLine: {
                  type: 'line',
                  yMin: 500, yMax: 500,
                  borderColor: 'rgba(217,119,6,0.5)',
                  borderWidth: 1.5,
                  borderDash: [6, 4],
                  label: { content: 'Warning', display: true, color: '#d97706', font: { size: 10 } }
                },
                dangerLine: {
                  type: 'line',
                  yMin: 700, yMax: 700,
                  borderColor: 'rgba(192,57,43,0.5)',
                  borderWidth: 1.5,
                  borderDash: [6, 4],
                  label: { content: 'Danger', display: true, color: '#c0392b', font: { size: 10 } }
                }
              }
            }
          }
        }
      });

    })
    .catch(err => console.error("Chart error:", err));
}

loadCharts();
setInterval(loadCharts, 10000);