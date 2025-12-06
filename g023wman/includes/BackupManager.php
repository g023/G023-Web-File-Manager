<?php

class BackupManager {
  private $db;
  private $maxVersions = 50; // Maximum versions to keep per file
  
  public function __construct($db) {
    $this->db = $db;
  }
  
  public function createBackup($filePath, $operation = 'edit') {
    if (!file_exists($filePath)) {
      return false;
    }
    
    // Get current version number
    $version = $this->getNextVersion($filePath);
    
    // Read and compress file content
    $content = file_get_contents($filePath);
    $compressed = gzcompress($content);
    
    // Calculate checksum
    $checksum = hash('sha256', $content);
    
    // Store backup in database
    $stmt = $this->db->prepare("
      INSERT INTO file_backups 
      (original_path, version, content, compression_type, original_size, compressed_size, checksum, operation) 
      VALUES (?, ?, ?, 'gzip', ?, ?, ?, ?)
    ");
    
    $stmt->bindValue(1, $filePath, SQLITE3_TEXT);
    $stmt->bindValue(2, $version, SQLITE3_INTEGER);
    $stmt->bindValue(3, $compressed, SQLITE3_BLOB);
    $stmt->bindValue(4, strlen($content), SQLITE3_INTEGER);
    $stmt->bindValue(5, strlen($compressed), SQLITE3_INTEGER);
    $stmt->bindValue(6, $checksum, SQLITE3_TEXT);
    $stmt->bindValue(7, $operation, SQLITE3_TEXT);
    
    $success = $stmt->execute();
    
    if ($success) {
      $backupId = $this->db->lastInsertRowID();
      $this->storeBackupMetadata($backupId, $filePath);
      $this->cleanupOldVersions($filePath);
      return true;
    }
    
    return false;
  }
  
  public function restoreBackup($backupId, $targetPath = null) {
    $stmt = $this->db->prepare("SELECT * FROM file_backups WHERE id = ?");
    $stmt->bindValue(1, $backupId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $backup = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$backup) {
      return false;
    }
    
    // Decompress content
    $content = gzuncompress($backup['content']);
    
    // Verify checksum
    if (hash('sha256', $content) !== $backup['checksum']) {
      throw new Exception('Backup integrity check failed');
    }
    
    // Restore to target path or original path
    $restorePath = $targetPath ?: $backup['original_path'];
    
    // Create directory if it doesn't exist
    $dir = dirname($restorePath);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    
    return file_put_contents($restorePath, $content) !== false;
  }
  
  public function listBackups($filePath = null) {
    if ($filePath) {
      $stmt = $this->db->prepare("
        SELECT b.*, m.file_type, m.permissions, m.owner, m.group_name 
        FROM file_backups b 
        LEFT JOIN backup_metadata m ON b.id = m.backup_id 
        WHERE b.original_path = ? 
        ORDER BY b.version DESC
      ");
      $stmt->bindValue(1, $filePath, SQLITE3_TEXT);
      $result = $stmt->execute();
    } else {
      $result = $this->db->query("
        SELECT b.*, m.file_type, m.permissions, m.owner, m.group_name 
        FROM file_backups b 
        LEFT JOIN backup_metadata m ON b.id = m.backup_id 
        ORDER BY b.created_at DESC 
        LIMIT 100
      ");
    }
    
    $backups = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      // Don't send content blob in list
      unset($row['content']);
      $backups[] = $row;
    }
    return $backups;
  }
  
  public function deleteBackup($backupId) {
    $stmt = $this->db->prepare("DELETE FROM file_backups WHERE id = ?");
    $stmt->bindValue(1, $backupId, SQLITE3_INTEGER);
    return $stmt->execute();
  }
  
  public function getBackupStats() {
    $stats = $this->db->querySingle("
      SELECT 
        COUNT(*) as total_backups,
        SUM(compressed_size) as total_size,
        COUNT(DISTINCT original_path) as unique_files
      FROM file_backups
    ", true);
    
    return $stats;
  }
  
  private function getNextVersion($filePath) {
    $stmt = $this->db->prepare("SELECT MAX(version) as max_version FROM file_backups WHERE original_path = ?");
    $stmt->bindValue(1, $filePath, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return ($result['max_version'] ?? 0) + 1;
  }
  
  private function storeBackupMetadata($backupId, $filePath) {
    if (!file_exists($filePath)) {
      return;
    }
    
    $stat = stat($filePath);
    $stmt = $this->db->prepare("
      INSERT INTO backup_metadata 
      (backup_id, original_path, file_type, permissions, owner, group_name) 
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bindValue(1, $backupId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $filePath, SQLITE3_TEXT);
    $stmt->bindValue(3, filetype($filePath), SQLITE3_TEXT);
    $stmt->bindValue(4, substr(sprintf('%o', $stat['mode']), -4), SQLITE3_TEXT);
    
    // posix functions might not be available on Windows
    $owner = function_exists('posix_getpwuid') ? (posix_getpwuid($stat['uid'])['name'] ?? 'unknown') : 'unknown';
    $group = function_exists('posix_getgrgid') ? (posix_getgrgid($stat['gid'])['name'] ?? 'unknown') : 'unknown';
    
    $stmt->bindValue(5, $owner, SQLITE3_TEXT);
    $stmt->bindValue(6, $group, SQLITE3_TEXT);
    
    $stmt->execute();
  }
  
  private function cleanupOldVersions($filePath) {
    // Keep only the most recent versions
    $stmt = $this->db->prepare("
      DELETE FROM file_backups 
      WHERE original_path = ? AND id NOT IN (
        SELECT id FROM file_backups 
        WHERE original_path = ? 
        ORDER BY version DESC 
        LIMIT ?
      )
    ");
    $stmt->bindValue(1, $filePath, SQLITE3_TEXT);
    $stmt->bindValue(2, $filePath, SQLITE3_TEXT);
    $stmt->bindValue(3, $this->maxVersions, SQLITE3_INTEGER);
    $stmt->execute();
  }
}
