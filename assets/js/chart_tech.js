function loadTechChart() {
  const o = operator.value;
  const m = method.value;
  const s = start.value;
  const e = end.value;

  fetch(`../api/data_chart_tech.php?operator=${o}&method=${m}&start=${s}&end=${e}`)
    .then(r => r.json())
    .then(d => {

      new Chart(document.getElementById('techChart'), {
        type: 'bar',
        data: {
          labels: d.map(x => x.technology),
          datasets: [
            { label: 'RSRP (dBm)', data: d.map(x => x.rsrp) },
            { label: 'RSRQ (dB)', data: d.map(x => x.rsrq) },
            { label: 'SINR (dB)', data: d.map(x => x.sinr) }
          ]
        },
        options: {
          plugins: {
            tooltip: {
              callbacks: {
                label: c => `${c.dataset.label}: ${c.raw.toFixed(1)}`
              }
            }
          }
        }
      });

    });
}

loadTechChart();
