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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Handle JSON input for POST/PUT/DELETE
$input = getInput();

if ($method === 'GET') {
    switch ($action) {
        case 'tree':
        case 'list':
            $path = $_GET['path'] ?? $_GET['id'] ?? '/'; // jsTree sends 'id'
            if ($path === '#') $path = '/'; // jsTree root
            
            // If path is relative or empty, default to root. 
            // But wait, we have root access, so '/' means system root.
            // However, for Windows, '/' might be weird. 
            // Let's assume the user provides a full path or we default to project root if empty.
            // Actually, ARCHITECTURE.md says "Designed for root users with full system access".
            // So we should respect the path provided.
            
            // Fix for Windows paths if needed, but PHP handles / usually fine.
            // If path is empty, maybe default to C:/ or /?
            // Let's default to the project root for the initial view if not specified.
            if (empty($path) || $path === '/') {
                 $config = require __DIR__ . '/../config/config.php';
                 $path = $config['base_path'];
            }

            $files = $fileManager->listDirectory($path);
            
            if ($action === 'list') {
                jsonResponse(['path' => $path, 'files' => $files]);
            } else {
                jsonResponse($files);
            }
            break;

        case 'content':
            $path = $_GET['path'] ?? '';
            if (!$path) errorResponse("Path required");
            $content = $fileManager->getFileContent($path);
            if ($content === false) errorResponse("File not found or unreadable");
            jsonResponse(['content' => $content]);
            break;

        case 'raw':
            $path = $_GET['path'] ?? '';
            if (!$path) die("Path required");
            if (!file_exists($path)) die("File not found");
            
            $mime = mime_content_type($path);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
            break;

        case 'search':
            $query = $_GET['q'] ?? '';
            $path = $_GET['path'] ?? '';
            
            if (empty($path) || $path === '/') {
                 $config = require __DIR__ . '/../config/config.php';
                 $path = $config['base_path'];
            }
            
            if (!$query) {
                jsonResponse([]);
            }
            
            $results = $fileManager->searchFiles($path, $query);
            jsonResponse($results);
            break;
            
        default:
            errorResponse("Invalid action");
    }
} elseif ($method === 'POST') {
    // Check for action in POST data if not in URL
    $action = $action ?: ($input['action'] ?? '');

    switch ($action) {
        case 'create':
            $path = $input['path'] ?? '';
            $type = $input['type'] ?? 'file'; // file or directory
            if (!$path) errorResponse("Path required");
            
            if ($type === 'directory') {
                if ($fileManager->createDirectory($path)) jsonResponse(['success' => true]);
                else errorResponse("Failed to create directory");
            } else {
                if ($fileManager->createFile($path)) jsonResponse(['success' => true]);
                else errorResponse("Failed to create file");
            }
            break;

        case 'delete':
            $path = $input['path'] ?? '';
            if (!$path) errorResponse("Path required");
            if ($fileManager->deleteFile($path)) jsonResponse(['success' => true]);
            else errorResponse("Failed to delete");
            break;

        case 'rename':
        case 'move':
            $from = $input['from'] ?? '';
            $to = $input['to'] ?? '';
            if (!$from || !$to) errorResponse("Source and destination required");
            if ($fileManager->moveFile($from, $to)) jsonResponse(['success' => true]);
            else errorResponse("Failed to move/rename");
            break;

        case 'copy':
            $from = $input['from'] ?? '';
            $to = $input['to'] ?? '';
            if (!$from || !$to) errorResponse("Source and destination required");
            if ($fileManager->copyFile($from, $to)) jsonResponse(['success' => true]);
            else errorResponse("Failed to copy");
            break;

        case 'save':
            $path = $input['path'] ?? '';
            $content = $input['content'] ?? '';
            if (!$path) errorResponse("Path required");
            if ($fileManager->saveFile($path, $content)) jsonResponse(['success' => true]);
            else errorResponse("Failed to save");
            break;
            
        case 'chmod':
            $path = $input['path'] ?? '';
            $permissions = $input['permissions'] ?? '';
            if (!$path || !$permissions) errorResponse("Path and permissions required");
            if ($fileManager->changePermissions($path, $permissions)) jsonResponse(['success' => true]);
            else errorResponse("Failed to change permissions");
            break;

        default:
            errorResponse("Invalid action");
    }
} else {
    errorResponse("Method not allowed", 405);
}
