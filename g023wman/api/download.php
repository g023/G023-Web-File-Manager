<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ZipManager.php';

$path = $_GET['path'] ?? '';
if (!$path) die("Path required");

if (is_dir($path)) {
    // Download directory as zip
    $zipName = basename($path) . '.zip';
    $tempZip = sys_get_temp_dir() . '/' . uniqid('zip_') . '.zip';
    
    if (ZipManager::createZip([$path], $tempZip)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($tempZip));
        readfile($tempZip);
        unlink($tempZip);
        exit;
    } else {
        die("Failed to create zip archive");
    }
} elseif (file_exists($path)) {
    // Download single file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
} else {
    die("File not found");
}
