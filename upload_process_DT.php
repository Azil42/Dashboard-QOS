<?php
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '2048M');

session_start();
require "../config/database.php";
require dirname(__DIR__) . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

// ======================
// SECURITY CHECK
// ======================
if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
    header("Location: upload_DT.php?status=error&message=File tidak valid");
    exit;
}

$adminId  = $_SESSION['admin_id'] ?? 1;
$file     = $_FILES['file_excel']['tmp_name'];
$filename = $_FILES['file_excel']['name'];

// Validasi ekstensi
$allowedExtensions = ['xlsx', 'xls', 'xlsm'];
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    header("Location: upload_DT.php?status=error&message=Format file tidak didukung");
    exit;
}

// ======================
// PRECHECK UNTUK AJAX
// ======================
if (isset($_GET['precheck'])) {
    header('Content-Type: text/plain');
    
    try {
        // Cek upload aktif
        $check = $pdo->prepare("SELECT id FROM uploads WHERE uploaded_by = ? AND status = 'processing' LIMIT 1");
        $check->execute([$adminId]);
        
        if ($check->fetch()) {
            echo "error=Masih ada upload yang berjalan";
            exit;
        }
        
        // Buat record upload
        $stmt = $pdo->prepare("
            INSERT INTO uploads (filename, uploaded_by, upload_type, status, uploaded_at) 
            VALUES (?, ?, 'drivetest', 'processing', NOW())
        ");
        $stmt->execute([$filename, $adminId]);
        $uploadId = $pdo->lastInsertId();
        
        echo "upload_id=" . $uploadId;
        exit;
    } catch (Exception $e) {
        echo "error=" . $e->getMessage();
        exit;
    }
}

// ======================
// HELPER FUNCTIONS
// ======================
function parseExcelDate($value) {
    if (empty($value)) {
        return date('Y-m-d H:i:s');
    }
    
    if (is_numeric($value)) {
        $unix = ($value - 25569) * 86400;
        return date('Y-m-d H:i:s', $unix);
    }
    
    $ts = strtotime($value);
    return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
}

function checkIfCancelled($pdo, $uploadId) {
    $check = $pdo->prepare("SELECT status FROM uploads WHERE id = ?");
    $check->execute([$uploadId]);
    $result = $check->fetch();
    return $result && $result['status'] === 'cancelled';
}

function updateProgress($pdo, $uploadId, $processed, $total, $progress) {
    $stmt = $pdo->prepare("
        UPDATE uploads 
        SET processed_rows = ?, total_rows = ?, progress_percent = ? 
        WHERE id = ?
    ");
    $stmt->execute([$processed, $total, $progress, $uploadId]);
}

// ======================
// PROSES UPLOAD UTAMA
// ======================
try {
    // Mulai transaction
    $pdo->beginTransaction();
    
    // Buat record upload
    $uploadId = $_POST['upload_id'] ?? null;
    if (!$uploadId) {
        $stmt = $pdo->prepare("
            INSERT INTO uploads (filename, uploaded_by, upload_type, status, uploaded_at) 
            VALUES (?, ?, 'drivetest', 'processing', NOW())
        ");
        $stmt->execute([$filename, $adminId]);
        $uploadId = $pdo->lastInsertId();
    }
    
    // Load Excel
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $totalRows = $sheet->getHighestDataRow();
    
    if ($totalRows <= 1) {
        throw new Exception("File Excel kosong atau hanya header");
    }
    
    $dataRows = $totalRows - 1;
    
    // Update total rows
    updateProgress($pdo, $uploadId, 0, $dataRows, 5);
    
    // Prepare query
    $insert = $pdo->prepare("
        INSERT INTO measurements (
            operator, rsrp, rsrq, sinr,
            latitude, longitude, kabupaten_kota,
            measure_time, upload_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $success = $skip = $error = 0;
    $lastUpdate = 0;
    
    // ======================
    // LOOP DATA
    // ======================
    for ($row = 2; $row <= $totalRows; $row++) {
        $currentRow = $row - 1;
        
        // Cek cancel setiap 100 baris
        if ($row % 100 === 0 && checkIfCancelled($pdo, $uploadId)) {
            $pdo->rollBack();
            $update = $pdo->prepare("UPDATE uploads SET status = 'cancelled' WHERE id = ?");
            $update->execute([$uploadId]);
            throw new Exception("Upload dibatalkan");
        }
        
        // Update progress setiap 100 baris
        if ($currentRow - $lastUpdate >= 100 || $row == $totalRows) {
            $lastUpdate = $currentRow;
            $progress = min(95, round(($currentRow / $dataRows) * 95));
            updateProgress($pdo, $uploadId, $currentRow, $dataRows, $progress);
        }
        
        try {
            // Ambil data dari Excel
            $operator  = trim($sheet->getCell('C' . $row)->getValue());
            $rsrp      = $sheet->getCell('K' . $row)->getValue();
            $rsrq      = $sheet->getCell('L' . $row)->getValue();
            $sinr      = $sheet->getCell('P' . $row)->getValue();
            $latitude  = $sheet->getCell('T' . $row)->getValue();
            $longitude = $sheet->getCell('U' . $row)->getValue();
            $kabkota   = trim($sheet->getCell('Z' . $row)->getValue());
            $msgTime   = $sheet->getCell('F' . $row)->getValue();
            
            // Validasi
            if (empty($operator) || $latitude == 0 || $longitude == 0) {
                $skip++;
                continue;
            }
            
            // Standardize operator
            $op = strtoupper($operator);
            if (strpos($op, 'TSEL') !== false || strpos($op, 'TELKOM') !== false) {
                $operator = 'TSEL';
            } elseif (strpos($op, 'ISAT') !== false || strpos($op, 'INDOSAT') !== false) {
                $operator = 'ISAT';
            } elseif (strpos($op, 'XL') !== false || strpos($op, 'AXIS') !== false) {
                $operator = 'XL';
            }
            
            // Clean kabupaten/kota
            $kabkota = trim(str_ireplace(['KOTA ', 'KAB. ', 'KABUPATEN ', 'KAB '], '', $kabkota));
            
            // Parse waktu
            $measureTime = parseExcelDate($msgTime);
            
            // Insert ke database
            $insert->execute([
                $operator,
                is_numeric($rsrp) ? (float)$rsrp : 0,
                is_numeric($rsrq) ? (float)$rsrq : 0,
                is_numeric($sinr) ? (float)$sinr : 0,
                (float)$latitude,
                (float)$longitude,
                $kabkota,
                $measureTime,
                $uploadId
            ]);
            
            $success++;
            
        } catch (Exception $e) {
            $error++;
            error_log("Error pada baris $row: " . $e->getMessage());
            continue;
        }
    }
    
    $pdo->commit();
    
    // Update status selesai
    $update = $pdo->prepare("UPDATE uploads SET status = 'completed', progress_percent = 100 WHERE id = ?");
    $update->execute([$uploadId]);
    
    // Redirect
    header("Location: upload_DT.php?status=success&count=$success&id=$uploadId");
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if (isset($uploadId) && $uploadId) {
        $update = $pdo->prepare("UPDATE uploads SET status = 'cancelled' WHERE id = ?");
        $update->execute([$uploadId]);
    }
    
    $message = urlencode($e->getMessage());
    header("Location: upload_DT.php?status=error&message=$message");
    exit;
}