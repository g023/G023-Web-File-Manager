<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/StateManager.php';

try {
    $db = Database::getInstance()->getConnection();
    $stateManager = new StateManager($db);
} catch (Exception $e) {
    errorResponse("Database error: " . $e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = getInput();

if ($method === 'GET') {
    $key = $_GET['key'] ?? '';
    if ($key) {
        jsonResponse($stateManager->getState($key));
    } else {
        jsonResponse($stateManager->getAllState());
    }
} elseif ($method === 'POST') {
    $key = $input['key'] ?? '';
    $value = $input['value'] ?? null;
    
    if (!$key) errorResponse("Key required");
    
    if ($stateManager->saveState($key, $value)) {
        jsonResponse(['success' => true]);
    } else {
        errorResponse("Failed to save state");
    }
} elseif ($method === 'DELETE') {
    if ($stateManager->clearState()) {
        jsonResponse(['success' => true]);
    } else {
        errorResponse("Failed to clear state");
    }
} else {
    errorResponse("Method not allowed", 405);
}
