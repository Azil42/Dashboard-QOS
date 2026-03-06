const map = L.map('map').setView([-7.25, 112.75], 10);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

let markers = [];

function loadData() {
  markers.forEach(m => map.removeLayer(m));
  markers = [];

  const o = operator.value;
  const m = method.value;
  const s = start.value;
  const e = end.value;

  fetch(`../api/data_map.php?operator=${o}&method=${m}&start=${s}&end=${e}`)
    .then(r => r.json())
    .then(data => {
      data.forEach(d => {
        let color =
          d.rsrp >= -80 ? 'green' :
          d.rsrp >= -90 ? 'lime' :
          d.rsrp >= -100 ? 'orange' : 'red';

        let mk = L.circleMarker([d.latitude, d.longitude], {
          radius: 6, color
        }).addTo(map);

        markers.push(mk);
      });
    });
}

loadData();
