<?php
$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Initialize database to ensure tables exist
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Error initializing application: " . $e->getMessage());
}

// Include the main template
require_once __DIR__ . '/templates/main.php';
