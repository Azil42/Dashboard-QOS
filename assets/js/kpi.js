fetch('../api/data_kpi.php')
  .then(r => r.json())
  .then(d => {
    document.getElementById('kpi-rsrp').innerText = d.rsrp.toFixed(1) + ' dBm';
    document.getElementById('kpi-dl').innerText = d.dl.toFixed(1) + ' Mbps';
    document.getElementById('kpi-lat').innerText = d.lat.toFixed(1) + ' ms';
    document.getElementById('kpi-sr').innerText = d.sr.toFixed(1) + ' %';
  });
