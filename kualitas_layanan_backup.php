<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

/* ================= FILTER ================= */
$operator   = $_GET['operator'] ?? 'all';
$method     = $_GET['method'] ?? 'all';
$service    = $_GET['service'] ?? 'all';
$technology = $_GET['technology'] ?? 'all';
$kota       = $_GET['kota'] ?? 'all';
$startDate  = $_GET['start'] ?? '';
$endDate    = $_GET['end'] ?? '';

$where = [];
$params = [];

if ($operator != 'all') {
    $where[] = "operator = ?";
    $params[] = $operator;
}
if ($method != 'all') {
    $where[] = "method = ?";
    $params[] = $method;
}
if ($service != 'all') {
    $where[] = "service_type = ?";
    $params[] = $service;
}
if ($technology != 'all') {
    $where[] = "technology = ?";
    $params[] = $technology;
}
if ($kota != 'all') {
    $where[] = "kota = ?";
    $params[] = $kota;
}
if ($startDate && $endDate) {
    $where[] = "measure_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

/* ================= DATA PER OPERATOR ================= */
$sqlOperator = "
    SELECT 
        operator,
        AVG(download_speed) AS download,
        AVG(upload_speed) AS upload,
        AVG(latency) AS latency,
        AVG(success_rate) AS success_rate,
        AVG(visual_quality) AS visual_quality,
        COUNT(*) as total_tests
    FROM measurements
    $whereSQL
    GROUP BY operator
    ORDER BY operator
";

$stmt = $pdo->prepare($sqlOperator);
$stmt->execute($params);
$dataOperator = $stmt->fetchAll();

/* ================= DATA PER SERVICE TYPE ================= */
$sqlService = "
    SELECT 
        service_type,
        AVG(download_speed) AS download,
        AVG(upload_speed) AS upload,
        AVG(latency) AS latency,
        AVG(success_rate) AS success_rate,
        COUNT(*) as total_tests
    FROM measurements
    $whereSQL
    GROUP BY service_type
    ORDER BY service_type
";

$stmt = $pdo->prepare($sqlService);
$stmt->execute($params);
$dataService = $stmt->fetchAll();

/* ================= DATA PER HARI ================= */
$sqlHari = "
    SELECT 
        measure_date as tanggal,
        AVG(download_speed) AS download,
        AVG(upload_speed) AS upload,
        AVG(latency) AS latency,
        AVG(success_rate) AS success_rate,
        COUNT(*) as total_tests
    FROM measurements
    $whereSQL
    GROUP BY measure_date
    ORDER BY measure_date DESC
    LIMIT 14
";

$stmt = $pdo->prepare($sqlHari);
$stmt->execute($params);
$dataHari = $stmt->fetchAll();

/* ================= FILTER MASTER ================= */
$operators = $pdo->query("SELECT DISTINCT operator FROM measurements WHERE operator IS NOT NULL ORDER BY operator")->fetchAll();
$methods = $pdo->query("SELECT DISTINCT method FROM measurements WHERE method IS NOT NULL ORDER BY method")->fetchAll();
$services = $pdo->query("SELECT DISTINCT service_type FROM measurements WHERE service_type IS NOT NULL ORDER BY service_type")->fetchAll();
$technologies = $pdo->query("SELECT DISTINCT technology FROM measurements WHERE technology IS NOT NULL ORDER BY technology")->fetchAll();
$kotas = $pdo->query("SELECT DISTINCT kota FROM measurements WHERE kota IS NOT NULL ORDER BY kota")->fetchAll();

/* ================= STATISTIK ================= */
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_tests,
        COUNT(DISTINCT operator) as operator_count,
        COUNT(DISTINCT service_type) as service_count,
        COUNT(DISTINCT kota) as kota_count,
        AVG(download_speed) as avg_download,
        AVG(upload_speed) as avg_upload,
        AVG(latency) as avg_latency,
        AVG(success_rate) as avg_success
    FROM measurements
    $whereSQL
")->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kualitas Layanan - Balmon Surabaya</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main {
            padding: 30px;
        }
        
        .topbar {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .topbar h1 {
            color: #0b3c68;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .topbar span {
            color: #666;
            font-size: 15px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-grid select,
        .filter-grid input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-grid button {
            background: linear-gradient(135deg, #0b3c68, #1e5fa3);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            color: #0b3c68;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
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
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th {
            background: #f1f1f1;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            color: #0b3c68;
        }
        
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Kualitas Layanan</h1>
            <span>Monitoring Speedtest, Download, Upload, Latency, YouTube & Success Rate</span>
        </div>
    </div>

    <!-- FILTER -->
    <div class="card">
        <h3>Filter Data</h3>
        <form method="GET" class="filter-grid">
            <select name="operator">
                <option value="all">Semua Operator</option>
                <?php foreach($operators as $op): ?>
                    <option value="<?= htmlspecialchars($op['operator']) ?>" <?= $operator == $op['operator'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($op['operator']) ?>
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

            <select name="service">
                <option value="all">Semua Service</option>
                <?php foreach($services as $s): ?>
                    <option value="<?= htmlspecialchars($s['service_type']) ?>" <?= $service == $s['service_type'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['service_type']) ?>
                    </option>
                <?php endforeach ?>
            </select>

            <select name="technology">
                <option value="all">Semua Teknologi</option>
                <?php foreach($technologies as $t): ?>
                    <option value="<?= htmlspecialchars($t['technology']) ?>" <?= $technology == $t['technology'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['technology']) ?>
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

            <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" title="Tanggal mulai">
            <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" title="Tanggal akhir">

            <button type="submit">Terapkan Filter</button>
            <a href="kualitas_layanan.php" style="padding: 10px; border: 1px solid #ddd; border-radius: 8px; text-align: center; text-decoration: none; color: #666; display: flex; align-items: center; justify-content: center;">
                Reset Filter
            </a>
        </form>
    </div>

    <!-- STATISTIK -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total_tests'] ?? 0) ?></div>
            <div class="stat-label">Total Pengujian</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['operator_count'] ?? 0) ?></div>
            <div class="stat-label">Operator</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['avg_download'] ?? 0, 1) ?> Mbps</div>
            <div class="stat-label">Rata² Download</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['avg_latency'] ?? 0, 1) ?> ms</div>
            <div class="stat-label">Rata² Latency</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['avg_success'] ?? 0, 1) ?>%</div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="card-grid">
        <div class="card">
            <h3>Kualitas Layanan per Operator</h3>
            <div class="chart-container">
                <canvas id="chartOperator"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Kualitas Layanan per Service Type</h3>
            <div class="chart-container">
                <canvas id="chartService"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Trend Download Speed per Hari</h3>
            <div class="chart-container">
                <canvas id="chartTrend"></canvas>
            </div>
        </div>

        <div class="card">
            <h3>Success Rate per Operator</h3>
            <div class="chart-container">
                <canvas id="chartSuccess"></canvas>
            </div>
        </div>
    </div>

    <!-- TABEL DATA -->
    <div class="card">
        <h3>Detail Data per Operator</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Operator</th>
                        <th>Download (Mbps)</th>
                        <th>Upload (Mbps)</th>
                        <th>Latency (ms)</th>
                        <th>Success Rate (%)</th>
                        <th>Visual Quality</th>
                        <th>Total Tests</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dataOperator as $row): 
                        // Tentukan status berdasarkan success rate
                        $status_class = '';
                        $status_text = '';
                        if ($row['success_rate'] >= 95) {
                            $status_class = 'badge-success';
                            $status_text = 'Excellent';
                        } elseif ($row['success_rate'] >= 85) {
                            $status_class = 'badge-warning';
                            $status_text = 'Good';
                        } else {
                            $status_class = 'badge-danger';
                            $status_text = 'Poor';
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['operator']) ?></strong></td>
                        <td><?= number_format($row['download'] ?? 0, 2) ?></td>
                        <td><?= number_format($row['upload'] ?? 0, 2) ?></td>
                        <td><?= number_format($row['latency'] ?? 0, 1) ?></td>
                        <td><?= number_format($row['success_rate'] ?? 0, 1) ?>%</td>
                        <td><?= number_format($row['visual_quality'] ?? 0, 1) ?>/5</td>
                        <td><?= number_format($row['total_tests'] ?? 0) ?></td>
                        <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Data dari PHP ke JavaScript
const dataOperator = <?= json_encode($dataOperator) ?>;
const dataService = <?= json_encode($dataService) ?>;
const dataHari = <?= json_encode($dataHari) ?>;

// Chart 1: Kualitas Layanan per Operator
if (dataOperator.length > 0) {
    new Chart(document.getElementById('chartOperator'), {
        type: 'bar',
        data: {
            labels: dataOperator.map(d => d.operator),
            datasets: [
                {
                    label: 'Download (Mbps)',
                    data: dataOperator.map(d => d.download),
                    backgroundColor: '#1abc9c',
                    borderColor: '#1abc9c',
                    borderWidth: 1
                },
                {
                    label: 'Upload (Mbps)',
                    data: dataOperator.map(d => d.upload),
                    backgroundColor: '#9b59b6',
                    borderColor: '#9b59b6',
                    borderWidth: 1
                },
                {
                    label: 'Latency (ms)',
                    data: dataOperator.map(d => d.latency),
                    backgroundColor: '#e74c3c',
                    borderColor: '#e74c3c',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                title: {
                    display: true,
                    text: 'Kualitas Layanan per Operator'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Speed (Mbps)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Latency (ms)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

// Chart 2: Kualitas Layanan per Service Type
if (dataService.length > 0) {
    new Chart(document.getElementById('chartService'), {
        type: 'radar',
        data: {
            labels: ['Download', 'Upload', 'Latency', 'Success Rate'],
            datasets: dataService.map((service, index) => ({
                label: service.service_type,
                data: [
                    service.download || 0,
                    service.upload || 0,
                    service.latency || 0,
                    service.success_rate || 0
                ],
                borderColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12'][index % 4],
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                pointBackgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12'][index % 4]
            }))
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                title: {
                    display: true,
                    text: 'Perbandingan per Jenis Layanan'
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 20
                    }
                }
            }
        }
    });
}

// Chart 3: Trend Download Speed per Hari
if (dataHari.length > 0) {
    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: {
            labels: dataHari.map(d => d.tanggal),
            datasets: [
                {
                    label: 'Download Speed (Mbps)',
                    data: dataHari.map(d => d.download),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Upload Speed (Mbps)',
                    data: dataHari.map(d => d.upload),
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true },
                title: {
                    display: true,
                    text: 'Trend Speed per Hari'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Speed (Mbps)'
                    }
                }
            }
        }
    });
}

// Chart 4: Success Rate per Operator
if (dataOperator.length > 0) {
    new Chart(document.getElementById('chartSuccess'), {
        type: 'doughnut',
        data: {
            labels: dataOperator.map(d => d.operator),
            datasets: [{
                data: dataOperator.map(d => d.success_rate),
                backgroundColor: [
                    '#3498db', '#2ecc71', '#e74c3c', '#f39c12', 
                    '#9b59b6', '#1abc9c', '#34495e', '#d35400'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true, position: 'right' },
                title: {
                    display: true,
                    text: 'Success Rate Distribution'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw.toFixed(1)}%`;
                        }
                    }
                }
            }
        }
    });
}

// Jika tidak ada data, tampilkan pesan
if (dataOperator.length === 0) {
    document.querySelector('.card-grid').innerHTML = `
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            <h3>📊 Tidak Ada Data</h3>
            <p>Upload data terlebih dahulu untuk melihat visualisasi</p>
            <a href="upload.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #0b3c68; color: white; text-decoration: none; border-radius: 8px;">
                Upload Data
            </a>
        </div>
    `;
}
</script>

</body>
</html>