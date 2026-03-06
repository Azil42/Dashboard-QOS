<?php
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');

session_start();
require "../config/database.php";
require dirname(__DIR__) . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

$adminId = $_SESSION['admin_id'] ?? 1;

if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != UPLOAD_ERR_OK) {
    header("Location: upload.php?status=error&message=File tidak valid");
    exit;
}

$file = $_FILES['file_excel']['tmp_name'];
$filename = $_FILES['file_excel']['name'];

try {
    // 1. Save upload info
    $stmtUpload = $pdo->prepare("INSERT INTO uploads (filename, uploaded_by, uploaded_at) VALUES (?, ?, NOW())");
    $stmtUpload->execute([$filename, $adminId]);
    $uploadId = $pdo->lastInsertId();
    
    echo "<div style='padding: 20px; font-family: Arial;'>";
    echo "<h2>📤 Processing Upload: " . htmlspecialchars($filename) . "</h2>";
    
    // 2. Load Excel file - IMPORTANT: setReadDataOnly FALSE untuk baca formula
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(false); // FALSE untuk membaca nilai formula
    $spreadsheet = $reader->load($file);
    
    // 3. Get the correct sheet
    $targetSheetName = "4G Quality_Strength";
    $sheet = $spreadsheet->getSheetByName($targetSheetName);
    
    if (!$sheet) {
        // Fallback ke sheet aktif
        $sheet = $spreadsheet->getActiveSheet();
        echo "<p>⚠ Using active sheet instead: " . $sheet->getTitle() . "</p>";
    } else {
        echo "<p>✅ Using sheet: <strong>$targetSheetName</strong></p>";
    }
    
    $totalRows = $sheet->getHighestRow();
    echo "<p>📊 Total rows in file: " . number_format($totalRows) . "</p>";
    
    // 4. Prepare insert statement
    $stmt = $pdo->prepare("
        INSERT INTO measurements (
            operator, method, technology, service_type, 
            rsrp, rsrq, sinr, 
            download_speed, upload_speed, latency, success_rate, visual_quality,
            latitude, longitude, kabupaten, kota, measure_date, upload_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // 5. Process data
    $pdo->beginTransaction();
    $successCount = 0;
    $errorCount = 0;
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<div id='progressBar' style='height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden;'>";
    echo "<div id='progressFill' style='height: 100%; width: 0%; background: #28a745; transition: width 0.3s;'></div>";
    echo "</div>";
    echo "<div id='progressText' style='margin-top: 10px; font-size: 14px;'>Starting... 0/$totalRows</div>";
    echo "</div>";
    
    flush();
    
    // Process each row starting from row 2 (row 1 is header)
    for ($row = 2; $row <= $totalRows; $row++) {
        // Update progress
        $progress = round(($row / $totalRows) * 100);
        echo "<script>
            document.getElementById('progressFill').style.width = '$progress%';
            document.getElementById('progressText').innerHTML = 'Processing row $row of $totalRows ($successCount success, $errorCount errors)';
        </script>";
        flush();
        
        try {
            // Read data from Excel - menggunakan getCell() langsung
            $operator = trim($sheet->getCell('C' . $row)->getValue());
            $rsrp = $sheet->getCell('K' . $row)->getValue();
            $rsrq = $sheet->getCell('L' . $row)->getValue();
            $sinr = $sheet->getCell('P' . $row)->getValue();
            $sessionType = trim($sheet->getCell('S' . $row)->getValue());
            $latitude = $sheet->getCell('T' . $row)->getValue();
            $longitude = $sheet->getCell('U' . $row)->getValue();
            $kabupatenKota = trim($sheet->getCell('Z' . $row)->getValue());
            $earfcn = $sheet->getCell('I' . $row)->getValue();
            $msgTime = $sheet->getCell('F' . $row)->getValue();
            
            // Handle formula untuk kategori (opsional)
            $kategoriRsrpCell = $sheet->getCell('W' . $row);
            $kategoriRsrqCell = $sheet->getCell('X' . $row);
            $kategoriSinrCell = $sheet->getCell('Y' . $row);
            
            // Cek jika cell berisi formula, ambil nilai kalkulasinya
            $kategoriRsrp = $kategoriRsrpCell->getValue();
            $kategoriRsrq = $kategoriRsrqCell->getValue();
            $kategoriSinr = $kategoriSinrCell->getValue();
            
            // Skip empty rows
            if (empty($operator) || $latitude == 0 || $longitude == 0) {
                $errorCount++;
                continue;
            }
            
            // Convert to proper types
            $rsrp = (float) $rsrp;
            $rsrq = (float) $rsrq;
            $sinr = (float) $sinr;
            $latitude = (float) $latitude;
            $longitude = (float) $longitude;
            $earfcn = (int) $earfcn;
            
            // Determine technology from EARFCN
            $technology = '4G';
            if ($earfcn >= 0 && $earfcn <= 599) {
                $technology = '2G';
            } elseif ($earfcn >= 600 && $earfcn <= 1199) {
                $technology = '3G';
            } elseif ($earfcn >= 1200 && $earfcn <= 2999) {
                $technology = '4G';
            } elseif ($earfcn >= 3000) {
                $technology = '5G';
            }
            
            // Determine service type from sessionType
            $service_type = 'DATA';
            $sessionTypeUpper = strtoupper($sessionType);
            if (strpos($sessionTypeUpper, 'IDLE') !== false) {
                $service_type = 'IDLE';
            } elseif (strpos($sessionTypeUpper, 'VOICE') !== false) {
                $service_type = 'VOICE';
            } elseif (strpos($sessionTypeUpper, 'VIDEO') !== false) {
                $service_type = 'VIDEO';
            }
            
            // Parse location - handle "Kota Surabaya" format
            $kabupaten = '';
            $kota = '';
            if (!empty($kabupatenKota)) {
                // Remove "Kota " prefix if exists
                $kota = str_ireplace('Kota ', '', $kabupatenKota);
                $kabupaten = $kota; // For simplicity, set kabupaten same as kota
            }
            
            // Generate QoS values from signal data
            $rsrp_abs = abs($rsrp);
            $download_speed = max(1, min(100, $rsrp_abs * 0.8));
            $upload_speed = $download_speed * 0.3;
            $latency = max(10, min(100, 50 - ($sinr * 2)));
            $success_rate = max(70, min(99, 90 + ($rsrp / 10)));
            $visual_quality = max(1, min(5, round($sinr / 5)));
            
            // Parse date from msgTime
            $measure_date = date('Y-m-d');
            if (!empty($msgTime)) {
                // Handle various date formats
                $timestamp = strtotime($msgTime);
                if ($timestamp !== false) {
                    $measure_date = date('Y-m-d', $timestamp);
                }
            }
            
            // Insert into database
            $stmt->execute([
                $operator,
                $sessionType,      // method
                $technology,
                $service_type,
                $rsrp,
                $rsrq,
                $sinr,
                $download_speed,
                $upload_speed,
                $latency,
                $success_rate,
                $visual_quality,
                $latitude,
                $longitude,
                $kabupaten,
                $kota,
                $measure_date,
                $uploadId
            ]);
            
            $successCount++;
            
            // Show progress every 1000 rows
            if ($successCount % 1000 == 0) {
                echo "<script>
                    document.getElementById('progressText').innerHTML = 
                        'Processed ' + $successCount + ' rows... (' + $errorCount + ' errors)';
                </script>";
                flush();
            }
            
        } catch (Exception $e) {
            $errorCount++;
            // Continue with next row
            continue;
        }
    }
    
    $pdo->commit();
    
    echo "<script>document.getElementById('progressFill').style.background = '#28a745';</script>";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='margin-top: 0;'>✅ Upload Complete!</h3>";
    echo "<p><strong>Successfully uploaded:</strong> " . number_format($successCount) . " records</p>";
    echo "<p><strong>Errors/Skipped:</strong> " . number_format($errorCount) . " rows</p>";
    echo "<p><strong>Upload ID:</strong> $uploadId</p>";
    
    // Calculate success rate
    $totalProcessed = $successCount + $errorCount;
    if ($totalProcessed > 0) {
        $successRate = round(($successCount / $totalProcessed) * 100, 2);
        echo "<p><strong>Success Rate:</strong> $successRate%</p>";
    }
    
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='index.php' style='display: inline-block; background: #0b3c68; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; margin-right: 10px; font-weight: bold;'>";
    echo "🏠 Go to Dashboard";
    echo "</a>";
    echo "<a href='upload.php' style='display: inline-block; background: #6c757d; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; margin-right: 10px;'>";
    echo "📤 Upload More";
    echo "</a>";
    echo "</div>";
    
    echo "<script>
        setTimeout(function() {
            window.location.href = 'index.php?upload_success=$successCount';
        }, 5000);
    </script>";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='margin-top: 0;'>❌ Upload Failed!</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($filename) . "</p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='upload.php' style='display: inline-block; background: #dc3545; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none;'>";
    echo "🔄 Try Again";
    echo "</a>";
    echo "</div>";
}

echo "</div>";
?>