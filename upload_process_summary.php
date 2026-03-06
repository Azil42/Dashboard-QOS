<?php
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '2048M');

session_start();
require "../config/database.php";
require dirname(__DIR__) . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;

/* ======================
   SECURITY
====================== */
if (!isset($_SESSION['admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
    header("Location: upload_summary.php?status=error&message=File tidak valid");
    exit;
}

$adminId  = $_SESSION['admin_id'] ?? 1;
$file     = $_FILES['file_excel']['tmp_name'];
$filename = $_FILES['file_excel']['name'];

/* ======================
   VALIDASI FILE
====================== */
$allowed = ['xlsx','xls','xlsm'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    header("Location: upload_summary.php?status=error&message=Format file tidak didukung");
    exit;
}

/* ======================
   HELPER
====================== */
function parseExcelDate($v) {
    if (empty($v)) return null;
    if (is_numeric($v)) {
        return date('Y-m-d', ($v - 25569) * 86400);
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

function val($v) {
    if ($v === null || $v === '') return 0;
    if (is_numeric($v)) return (float)$v;
    if (preg_match('/-?\d+(\.\d+)?/', $v, $m)) return (float)$m[0];
    return 0;
}

function updateProgress($pdo, $uploadId, $processed, $total) {
    $percent = min(95, round(($processed / $total) * 95));
    $stmt = $pdo->prepare("
        UPDATE uploads 
        SET processed_rows=?, total_rows=?, progress_percent=?
        WHERE id=?
    ");
    $stmt->execute([$processed, $total, $percent, $uploadId]);
}

/* ======================
   PROSES UTAMA
====================== */
try {
    $pdo->beginTransaction();

    // create upload log
    $stmt = $pdo->prepare("
        INSERT INTO uploads 
        (filename, uploaded_by, upload_type, status, uploaded_at)
        VALUES (?, ?, 'compile_summary', 'processing', NOW())
    ");
    $stmt->execute([$filename, $adminId]);
    $uploadId = $pdo->lastInsertId();

    // load excel
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getSheetByName('CompileSummary')
        ?: $spreadsheet->getActiveSheet();

    $startRow = 4;
    $lastRow  = $sheet->getHighestRow();
    $total    = $lastRow - $startRow + 1;

    updateProgress($pdo, $uploadId, 0, $total);

    // prepare insert
    $insert = $pdo->prepare("
        INSERT INTO compile_summary (
            operator, jenis_tes, kota_kabupaten, measure_date,
            avg_download, avg_upload, ping_success_rate, avg_latency,
            youtube_sr, avg_ttfp, avg_visual_quality,
            avg_rsrp, avg_rsrq, avg_sinr,
            upload_id, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    $done = 0;

    for ($row = $startRow; $row <= $lastRow; $row++) {

        if ($done % 100 === 0) {
            updateProgress($pdo, $uploadId, $done, $total);
        }

        $operator = trim($sheet->getCellByColumnAndRow(13,$row)->getValue());
        $kota     = trim($sheet->getCellByColumnAndRow(4,$row)->getValue());

        if ($operator === '' || $kota === '') continue;

        $op = strtoupper($operator);
        if (strpos($op,'TELKOM') !== false) $operator='Telkomsel';
        elseif (strpos($op,'INDOSAT')!==false) $operator='Indosat';
        elseif (strpos($op,'XL')!==false) $operator='XL';

        $jenis = strtoupper($sheet->getCellByColumnAndRow(5,$row)->getValue());
        $jenis = strpos($jenis,'DT')!==false ? 'DT' : 'ST';

        $insert->execute([
            $operator,
            $jenis,
            trim(str_ireplace(['KOTA ','KAB. ','KABUPATEN ','KAB '],'',$kota)),
            parseExcelDate($sheet->getCellByColumnAndRow(12,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(60,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(65,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(75,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(77,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(81,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(82,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(84,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(164,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(166,$row)->getValue()),
            val($sheet->getCellByColumnAndRow(168,$row)->getValue()),
            $uploadId
        ]);

        $done++;
    }

    $pdo->commit();

    $pdo->prepare("
        UPDATE uploads 
        SET status='completed', progress_percent=100 
        WHERE id=?
    ")->execute([$uploadId]);

    header("Location: upload_summary.php?status=success&id=$uploadId");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if (isset($uploadId)) {
        $pdo->prepare("UPDATE uploads SET status='cancelled' WHERE id=?")
            ->execute([$uploadId]);
    }

    $msg = urlencode($e->getMessage());
    header("Location: upload_summary.php?status=error&message=$msg");
    exit;
}
