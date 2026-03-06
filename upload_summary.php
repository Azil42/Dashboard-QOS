<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Data Summary - Balmon Surabaya</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .upload-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .upload-icon {
            text-align: center;
            font-size: 70px;
            margin: 20px 0;
            color: #0b3c68;
        }
        
        .upload-instructions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #0b3c68;
        }
        
        .upload-instructions h4 {
            color: #0b3c68;
            margin-top: 0;
        }
        
        .upload-instructions ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        
        .upload-instructions li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .file-input {
            width: 100%;
            padding: 15px;
            border: 2px dashed #0b3c68;
            border-radius: 10px;
            background: #f8f9fa;
            text-align: center;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .file-input:hover {
            background: #e9ecef;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, #0b3c68, #1e5fa3);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            display: block;
            margin: 30px auto;
            transition: all 0.3s;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 60, 104, 0.3);
        }
        
        .upload-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .loading {
            text-align: center;
            color: #666;
            margin: 20px 0;
            display: none;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
    </style>
</head>
<body>

<?php include "sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Upload Data Pengukuran QoS</h1>
            <span>Unggah data ringkasan hasil pengujian QoS</span>
        </div>
    </div>

    <div class="upload-container">
        <!-- Status Messages -->
        <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-<?= $_GET['status'] == 'success' ? 'success' : 'error' ?>">
                <?php if ($_GET['status'] == 'success'): ?>
                    <h4>✅ Upload Berhasil!</h4>
                    <p>
                        Data compile summary berhasil diupload ke database.
                        <?php if (isset($_GET['count'])): ?>
                            <br><strong><?= htmlspecialchars($_GET['count']) ?> data</strong> telah diproses.
                        <?php endif; ?>
                    </p>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <h4>❌ Upload Gagal!</h4>
                    <p>
                        <?= isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Terjadi kesalahan saat mengupload file' ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="upload-icon">📁</div>
            <h3 style="text-align: center; color: #0b3c68; margin-bottom: 20px;">Upload File Compile Summary</h3>
            
            <div class="upload-instructions">
                <h4>Instruksi Upload Compile Summary:</h4>
                <ul>
                    <li><strong>Format file:</strong> .xlsx atau .xls</li>
                    <li><strong>Ukuran maksimal:</strong> 50MB</li>
                    <li><strong>Struktur kolom yang diperlukan:</strong>
                        <ul>
                            <li>Operator (Kolom M) </li>
                            <li>Jenis Test (Kolom E)</li>
                            <li>Kota/Kabupaten (Kolom D)</li>
                            <li>Tanggal Pengukuran (Kolom L)</li>
                            <li>Average Download Speed (Kolom BH)</li>
                            <li>Average Upload Speed (Kolom BM)</li>
                            <li>Ping Success Rate (Kolom BW)</li>
                            <li>Average Latency (Kolom BY)</li>
                            <li>YouTube Success Rate (Kolom CC)</li>
                            <li>Average TTFP (Kolom CD)</li>
                            <li>Average Visual Quality (Kolom CF)</li>
                            <li>Average RSRP (Kolom FH)</li>
                            <li>Average RSRQ (Kolom FJ)</li>
                            <li>Average SINR (Kolom FL)</li>
                        </ul>
                    </li>
                    <li>Pastikan data dimulai di baris ke-4</li>
                    <li>Data akan ditampilkan di halaman kualitas sinyal dan layanan</li>
                </ul>
            </div>

            <form method="POST" action="upload_process_summary.php" enctype="multipart/form-data" id="uploadForm">
                <input type="file" name="file_excel" id="file_excel" 
                       accept=".xlsx,.xls" required class="file-input">
                
                <button type="submit" class="upload-btn" id="submitBtn">
                    ⬆ Upload Data Summary
                </button>
                
                <div class="loading" id="loading">
                    <div style="margin-bottom: 10px;">⏳ Memproses data summary, harap tunggu...</div>
                    <div style="font-size: 14px; color: #888;">
                        Proses mungkin memakan waktu beberapa menit untuk data yang besar
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('file_excel');
    const submitBtn = document.getElementById('submitBtn');
    const loading = document.getElementById('loading');
    
    if (!fileInput.files.length) {
        alert('Silakan pilih file Excel terlebih dahulu');
        e.preventDefault();
        return false;
    }
    
    const file = fileInput.files[0];
    const maxSize = 50 * 1024 * 1024; // 50MB
    
    if (file.size > maxSize) {
        alert('File terlalu besar. Maksimal ukuran file adalah 50MB');
        e.preventDefault();
        return false;
    }
    
    // Validasi ekstensi file
    const validExtensions = ['.xlsx', '.xls'];
    const fileName = file.name.toLowerCase();
    const isValidExtension = validExtensions.some(ext => fileName.endsWith(ext));
    
    if (!isValidExtension) {
        alert('Format file tidak didukung. Harap upload file Excel (.xlsx atau .xls)');
        e.preventDefault();
        return false;
    }
    
    // Tampilkan loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Memproses...';
    loading.style.display = 'block';
    
    return true;
});

// Drag and drop functionality
const fileInput = document.getElementById('file_excel');
const fileInputArea = fileInput.parentElement;

fileInputArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileInputArea.style.backgroundColor = '#e9ecef';
    fileInputArea.style.borderColor = '#1e5fa3';
});

fileInputArea.addEventListener('dragleave', (e) => {
    e.preventDefault();
    fileInputArea.style.backgroundColor = '#f8f9fa';
    fileInputArea.style.borderColor = '#0b3c68';
});

fileInputArea.addEventListener('drop', (e) => {
    e.preventDefault();
    fileInputArea.style.backgroundColor = '#f8f9fa';
    fileInputArea.style.borderColor = '#0b3c68';
    
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        
        // Update tampilan nama file
        const fileName = fileInput.files[0].name;
        fileInputArea.innerHTML = `
            <div style="color: #0b3c68; font-weight: bold;">
                📄 ${fileName}
            </div>
            <div style="color: #666; font-size: 14px; margin-top: 5px;">
                Klik untuk mengganti file
            </div>
        `;
    }
});
</script>
</body>
</html>