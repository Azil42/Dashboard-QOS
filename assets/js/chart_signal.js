fetch('../api/data_chart_signal.php')
  .then(r => r.json())
  .then(d => {
    new Chart(signalChart, {
      type: 'bar',
      data: {
        labels: d.map(x => x.operator),
        datasets: [
          { label: 'RSRP', data: d.map(x => x.rsrp) },
          { label: 'RSRQ', data: d.map(x => x.rsrq) },
          { label: 'SINR', data: d.map(x => x.sinr) }
        ]
      }
    });
  });
