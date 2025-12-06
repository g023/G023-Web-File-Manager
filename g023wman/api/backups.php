<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BackupManager.php';

try {
    $db = Database::getInstance()->getConnection();
    $backupManager = new BackupManager($db);
} catch (Exception $e) {
    errorResponse("Database error: " . $e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = getInput();

if ($method === 'GET') {
    if ($action === 'stats') {
        jsonResponse($backupManager->getBackupStats());
    } else {
        $path = $_GET['path'] ?? null;
        jsonResponse($backupManager->listBackups($path));
    }
} elseif ($method === 'POST') {
    if ($action === 'restore') {
        $id = $input['id'] ?? '';
        if (!$id) errorResponse("Backup ID required");
        
        try {
            if ($backupManager->restoreBackup($id)) {
                jsonResponse(['success' => true]);
            } else {
                errorResponse("Failed to restore backup");
            }
        } catch (Exception $e) {
            errorResponse($e->getMessage());
        }
    } else {
        errorResponse("Invalid action");
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) errorResponse("Backup ID required");
    
    if ($backupManager->deleteBackup($id)) {
        jsonResponse(['success' => true]);
    } else {
        errorResponse("Failed to delete backup");
    }
} else {
    errorResponse("Method not allowed", 405);
}
