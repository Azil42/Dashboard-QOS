<?php
session_start();
require "../config/database.php";

if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$upload_id = $_POST['upload_id'] ?? 0;
$upload_type = $_POST['upload_type'] ?? 'drivetest';

if (!$upload_id) {
    echo json_encode(['success' => false, 'message' => 'Upload ID tidak valid']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update status upload menjadi cancelled
    $stmt = $pdo->prepare("
        UPDATE uploads 
        SET status = 'cancelled', 
            cancelled_at = NOW(),
            progress_percent = 0
        WHERE id = ? 
        AND status = 'processing'
        AND uploaded_by = ?
    ");
    
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $stmt->execute([$upload_id, $admin_id]);
    
    // Hapus data berdasarkan upload_type
    if ($upload_type === 'drivetest') {
        $deleteStmt = $pdo->prepare("DELETE FROM measurements WHERE upload_id = ?");
        $deleteStmt->execute([$upload_id]);
    } elseif ($upload_type === 'compile_summary') {
        $deleteStmt = $pdo->prepare("DELETE FROM compile_summary WHERE upload_id = ?");
        $deleteStmt->execute([$upload_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Upload berhasil dibatalkan'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Gagal membatalkan upload: ' . $e->getMessage()
    ]);
}
?>