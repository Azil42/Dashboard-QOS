<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

/* ================= FILTER ================= */
$tanggal   = $_GET['tanggal'] ?? '';
$operator  = $_GET['operator'] ?? 'all';
$method    = $_GET['method'] ?? 'all';
$kota      = $_GET['kota'] ?? 'all';

$where = [];
$params = [];

if ($tanggal) { 
    $where[] = "measure_date = ?";
    $params[] = $tanggal; 
}
if ($operator != 'all') { 
    $where[] = "operator = ?"; 
    $params[] = $operator; 
}
if ($method != 'all') { 
    $where[] = "method = ?";
    $params[] = $method; 
}
if ($kota != 'all') { 
    $where[] = "kota = ?";
    $params[] = $kota; 
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/* ================= DATA PER HARI ================= */
$sqlHari = "
    SELECT 
        measure_date as tanggal,
        AVG(rsrp) as rsrp,
        AVG(rsrq) as rsrq,
        AVG(sinr) as sinr,
        COUNT(*) as jumlah_data
    FROM measurements
    $whereSQL
    GROUP BY measure_date
    ORDER BY tanggal DESC
    LIMIT 30
";

$stmt = $pdo->prepare($sqlHari);
$stmt->execute($params);
$perHari = $stmt->fetchAll();

/* ================= DATA PER OPERATOR ================= */
$sqlOp = "
    SELECT 
        operator,
        AVG(rsrp) as avg_rsrp,
        AVG(rsrq) as avg_rsrq,
        AVG(sinr) as avg_sinr,
        COUNT(*) as jumlah_data
    FROM measurements
    $whereSQL
    GROUP BY operator
    ORDER BY operator
";

$stmt = $pdo->prepare($sqlOp);
$stmt->execute($params);
$perOp = $stmt->fetchAll();

/* ================= DATA UNTUK CHART KATEGORI ================= */
$sqlKategori = "
    SELECT 
        CASE 
            WHEN rsrp >= -85 THEN 'Baik Sekali'
            WHEN rsrp >= -95 THEN 'Baik'
            WHEN rsrp >= -105 THEN 'Cukup'
            ELSE 'Kurang'
        END as kategori,
        COUNT(*) as jumlah
    FROM measurements
    $whereSQL
    GROUP BY 
        CASE 
            WHEN rsrp >= -85 THEN 'Baik Sekali'
            WHEN rsrp >= -95 THEN 'Baik'
            WHEN rsrp >= -105 THEN 'Cukup'
            ELSE 'Kurang'
        END
    ORDER BY 
        CASE 
            WHEN rsrp >= -85 THEN 1
            WHEN rsrp >= -95 THEN 2
            WHEN rsrp >= -105 THEN 3
            ELSE 4
        END
";

$stmt = $pdo->prepare($sqlKategori);
$stmt->execute($params);
$perKategori = $stmt->fetchAll();

/* ================= FILTER MASTER ================= */
$operators = $pdo->query("SELECT DISTINCT operator FROM measurements WHERE operator IS NOT NULL ORDER BY operator")->fetchAll();
$methods = $pdo->query("SELECT DISTINCT method FROM measurements WHERE method IS NOT NULL ORDER BY method")->fetchAll();
$kotas = $pdo->query("SELECT DISTINCT kota FROM measurements WHERE kota IS NOT NULL ORDER BY kota")->fetchAll();
$services = $pdo->query("SELECT DISTINCT service_type FROM measurements WHERE service_type IS NOT NULL ORDER BY service_type")->fetchAll();
$technologies = $pdo->query("SELECT DISTINCT technology FROM measurements WHERE technology IS NOT NULL ORDER BY technology")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kualitas Sinyal - Balmon Surabaya</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .filter-grid select,
        .filter-grid input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }
        
        .filter-grid button {
            background: linear-gradient(135deg, #0b3c68, #1e5fa3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            height: 42px;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #0b3c68;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Kualitas Sinyal</h1>
            <span>Monitoring RSRP, RSRQ & SINR</span>
        </div>
    </div>

    <!-- FILTER -->
    <div class="card filter-card">
        <form method="GET" class="filter-grid">
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" title="Filter berdasarkan tanggal">
            
            <select name="operator">
                <option value="all">Semua Operator</option>
                <?php foreach($operators as $o): ?>
                    <option value="<?= htmlspecialchars($o['operator']) ?>" <?= $operator == $o['operator'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($o['operator']) ?>
                    </option>
                <?php endforeach ?>
            </select>

            <select name="method">
                <option value="all">Semua Metode</option>
                <?php foreach($methods as $m): ?>
                    <option value="<?= htmlspecialchars($m['method']) ?>" <?= $method == $m['method'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['method']) ?>
                    </option>
                <?php endforeach ?>
            </select>

            <select name="kota">
                <option value="all">Semua Kota/Kab</option>
                <?php foreach($kotas as $k): ?>
                    <option value="<?= htmlspecialchars($k['kota']) ?>" <?= $kota == $k['kota'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['kota']) ?>
                    </option>
                <?php endforeach ?>
            </select>

            <button type="submit">Terapkan Filter</button>
            <a href="kualitas_sinyal.php" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px; text-align: center; text-decoration: none; color: #666; display: flex; align-items: center; justify-content: center;">
                Reset Filter
            </a>
        </form>
    </div>

    <!-- STATISTIK -->
    <div class="card-grid">
        <div class="stats-card">
            <div class="stat-value"><?= number_format(count($perHari)) ?></div>
            <div class="stat-label">Hari Pengukuran</div>
        </div>
        
        <div class="stats-card">
            <div class="stat-value"><?= number_format(array_sum(array_column($perHari, 'jumlah_data'))) ?></div>
            <div class="stat-label">Total Data</div>
        </div>
        
        <div class="stats-card">
            <div class="stat-value"><?= number_format(count($operators)) ?></div>
            <div class="stat-label">Operator</div>
        </div>
        
        <div class="stats-card">
            <div class="stat-value"><?= number_format(count($kotas)) ?></div>
            <div class="stat-label">Lokasi</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="card-grid">
        <div class="card">
            <h3>Tren RSRP per Hari</h3>
            <div class="chart-container">
                <canvas id="chartRsrpHari"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Tren RSRQ per Hari</h3>
            <div class="chart-container">
                <canvas id="chartRsrqHari"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Rata-rata Sinyal per Operator</h3>
            <div class="chart-container">
                <canvas id="chartOperator"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Distribusi Kategori RSRP</h3>
            <div class="chart-container">
                <canvas id="chartKategori"></canvas>
            </div>
        </div>
    </div>
    
    <!-- DATA TABLE -->
    <div class="card">
        <h3>Data Detail</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr style="background: #f1f1f1;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Tanggal</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">RSRP (dBm)</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">RSRQ (dB)</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">SINR (dB)</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Jumlah Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($perHari as $data): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;"><?= htmlspecialchars($data['tanggal']) ?></td>
                        <td style="padding: 10px;"><?= round($data['rsrp'], 2) ?></td>
                        <td style="padding: 10px;"><?= round($data['rsrq'], 2) ?></td>
                        <td style="padding: 10px;"><?= round($data['sinr'], 2) ?></td>
                        <td style="padding: 10px;"><?= $data['jumlah_data'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Data dari PHP ke JavaScript
const perHari = <?= json_encode($perHari) ?>;
const perOp = <?= json_encode($perOp) ?>;
const perKategori = <?= json_encode($perKategori) ?>;

// Chart RSRP per Hari
if (perHari.length > 0) {
    new Chart(document.getElementById('chartRsrpHari'), {
        type: 'line',
        data: {
            labels: perHari.map(d => d.tanggal),
            datasets: [{
                label: 'RSRP (dBm)',
                data: perHari.map(d => d.rsrp),
                borderColor: '#0b3c68',
                backgroundColor: 'rgba(11, 60, 104, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'dBm'
                    },
                    reverse: true
                }
            }
        }
    });
}

// Chart RSRQ per Hari
if (perHari.length > 0) {
    new Chart(document.getElementById('chartRsrqHari'), {
        type: 'line',
        data: {
            labels: perHari.map(d => d.tanggal),
            datasets: [{
                label: 'RSRQ (dB)',
                data: perHari.map(d => d.rsrq),
                borderColor: '#1e5fa3',
                backgroundColor: 'rgba(30, 95, 163, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'dB'
                    },
                    reverse: true
                }
            }
        }
    });
}

// Chart per Operator
if (perOp.length > 0) {
    new Chart(document.getElementById('chartOperator'), {
        type: 'bar',
        data: {
            labels: perOp.map(d => d.operator),
            datasets: [
                {
                    label: 'RSRP (dBm)',
                    data: perOp.map(d => d.avg_rsrp),
                    backgroundColor: '#0b3c68'
                },
                {
                    label: 'RSRQ (dB)',
                    data: perOp.map(d => d.avg_rsrq),
                    backgroundColor: '#1e5fa3'
                },
                {
                    label: 'SINR (dB)',
                    data: perOp.map(d => d.avg_sinr),
                    backgroundColor: '#3a8cdf'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true }
            }
        }
    });
}

// Chart Kategori
if (perKategori.length > 0) {
    new Chart(document.getElementById('chartKategori'), {
        type: 'pie',
        data: {
            labels: perKategori.map(d => d.kategori),
            datasets: [{
                data: perKategori.map(d => d.jumlah),
                backgroundColor: ['#2ecc71', '#3498db', '#f1c40f', '#e74c3c']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} data (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
} else {
    // Fallback jika tidak ada data kategori
    document.getElementById('chartKategori').innerHTML = 
        '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">Tidak ada data kategori</div>';
}
</script>

</body>
</html>