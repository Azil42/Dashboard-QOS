<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$upload_id = $_GET['upload_id'] ?? 0;

if (!$upload_id) {
    echo json_encode(['success' => false, 'message' => 'Upload ID tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            filename,
            processed_rows,
            total_rows,
            progress_percent,
            status,
            upload_type,
            uploaded_at
        FROM uploads 
        WHERE id = ? 
        AND uploaded_by = ?
    ");
    
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $stmt->execute([$upload_id, $admin_id]);
    $upload = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$upload) {
        echo json_encode(['success' => false, 'message' => 'Upload tidak ditemukan']);
        exit;
    }
    
    // Buat teks status
    $statusText = '';
    $upload_type_name = $upload['upload_type'] === 'drivetest' ? 'Drivetest' : 'Summary';
    
    if ($upload['status'] === 'processing') {
        $progress = $upload['progress_percent'] ?? 0;
        $processed = number_format($upload['processed_rows'] ?? 0);
        $total = number_format($upload['total_rows'] ?? 0);
        $statusText = "Memproses {$upload['filename']} ($progress%) - $processed/$total baris";
    } elseif ($upload['status'] === 'completed') {
        $statusText = "Selesai: {$upload['filename']}";
    } elseif ($upload['status'] === 'cancelled') {
        $statusText = "Dibatalkan: {$upload['filename']}";
    }
    
    echo json_encode([
        'success' => true,
        'upload_id' => $upload['id'],
        'filename' => $upload['filename'],
        'processed_rows' => (int)($upload['processed_rows'] ?? 0),
        'total_rows' => (int)($upload['total_rows'] ?? 0),
        'progress_percent' => (int)($upload['progress_percent'] ?? 0),
        'status' => $upload['status'],
        'upload_type' => $upload['upload_type'],
        'status_text' => $statusText,
        'uploaded_at' => $upload['uploaded_at']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>