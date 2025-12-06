<?php
// Database connection
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $config = require __DIR__ . '/config.php';
        $dbPath = $config['db_path'];
        
        // Ensure db directory exists
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        try {
            $this->connection = new SQLite3($dbPath);
            $this->connection->enableExceptions(true);
            $this->initializeTables();
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function initializeTables() {
        // Create tables if they don't exist
        $queries = [
            "CREATE TABLE IF NOT EXISTS file_metadata (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path TEXT UNIQUE NOT NULL,
                size INTEGER,
                modified_time DATETIME,
                permissions TEXT,
                mime_type TEXT,
                thumbnail_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS app_config (
                config_key TEXT PRIMARY KEY,
                config_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS ui_state (
                state_key TEXT PRIMARY KEY,
                state_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS file_backups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_path TEXT NOT NULL,
                backup_path TEXT,
                version INTEGER NOT NULL,
                content BLOB,
                compression_type TEXT DEFAULT 'gzip',
                original_size INTEGER,
                compressed_size INTEGER,
                checksum TEXT,
                operation TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(original_path, version)
            )",
            "CREATE TABLE IF NOT EXISTS backup_metadata (
                backup_id INTEGER PRIMARY KEY,
                original_path TEXT NOT NULL,
                file_type TEXT,
                permissions TEXT,
                owner TEXT,
                group_name TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (backup_id) REFERENCES file_backups(id) ON DELETE CASCADE
            )"
        ];

        foreach ($queries as $query) {
            $this->connection->exec($query);
        }
    }
}
