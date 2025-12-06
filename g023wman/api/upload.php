<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/file_ops.php';

try {
    $db = Database::getInstance()->getConnection();
    $fileManager = new FileManager($db);
} catch (Exception $e) {
    errorResponse("Database error: " . $e->getMessage(), 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetDir = $_POST['path'] ?? '';
    if (!$targetDir) errorResponse("Target path required");
    
    if (!is_dir($targetDir)) errorResponse("Target is not a directory");
    
    if (!empty($_FILES['file'])) {
        $file = $_FILES['file'];
        $targetFile = $targetDir . '/' . basename($file['name']);
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            jsonResponse(['success' => true]);
        } else {
            errorResponse("Failed to move uploaded file");
        }
    } else {
        errorResponse("No file uploaded");
    }
} else {
    errorResponse("Method not allowed", 405);
}
