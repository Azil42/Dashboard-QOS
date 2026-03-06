function loadQoSChart() {
  const o = operator.value;
  const m = method.value;
  const s = start.value;
  const e = end.value;

  fetch(`../api/data_chart_qos.php?operator=${o}&method=${m}&start=${s}&end=${e}`)
    .then(r => r.json())
    .then(data => {

      const labels = data.map(d => d.service_type);
      const download = data.map(d => d.download_speed);
      const upload = data.map(d => d.upload_speed);
      const latency = data.map(d => d.latency);
      const success = data.map(d => d.success_rate);

      // DOWNLOAD & UPLOAD
      new Chart(document.getElementById('speedChart'), {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'Download (Mbps)', data: download },
            { label: 'Upload (Mbps)', data: upload }
          ]
        },
        options: {
          plugins: {
            tooltip: {
              callbacks: {
                label: ctx => `${ctx.dataset.label}: ${ctx.raw.toFixed(1)}`
              }
            }
          }
        }
      });

      // LATENCY
      new Chart(document.getElementById('latencyChart'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Latency (ms)',
            data: latency
          }]
        }
      });

      // SUCCESS RATE
      new Chart(document.getElementById('successChart'), {
        type: 'doughnut',
        data: {
          labels,
          datasets: [{
            data: success
          }]
        }
      });
    });
}

loadQoSChart();
