<?php
require_once __DIR__ . '/BackupManager.php';

class FileManager {
  private $db;
  private $backupManager;
  
  public function __construct($db) {
    $this->db = $db;
    $this->backupManager = new BackupManager($db);
  }
  
  public function listDirectory($path) {
    $files = [];
    
    if (is_dir($path)) {
      $iterator = new DirectoryIterator($path);
      foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDot()) continue;
        
        $files[] = [
          'id' => $path . '/' . $fileInfo->getFilename(), // Use path as ID for jsTree
          'text' => $fileInfo->getFilename(), // Use text for jsTree
          'name' => $fileInfo->getFilename(),
          'path' => $path . '/' . $fileInfo->getFilename(),
          'type' => $fileInfo->isDir() ? 'directory' : 'file',
          'size' => $fileInfo->getSize(),
          'modified' => $fileInfo->getMTime(),
          'permissions' => substr(sprintf('%o', $fileInfo->getPerms()), -4),
          'children' => $fileInfo->isDir() ? $this->hasChildren($fileInfo->getPathname()) : false
        ];
      }
    }
    
    return $files;
  }

  private function hasChildren($path) {
      if (!is_dir($path)) return false;
      $handle = @opendir($path);
      if ($handle === false) return false;
      
      while (false !== ($entry = readdir($handle))) {
          if ($entry !== '.' && $entry !== '..') {
              closedir($handle);
              return true;
          }
      }
      closedir($handle);
      return false;
  }
  
  public function createDirectory($path) {
    return mkdir($path, 0755, true);
  }
  
  public function createFile($path, $content = '') {
      return file_put_contents($path, $content) !== false;
  }

  public function deleteFile($path) {
    // Create backup before deletion
    if (file_exists($path) && !is_dir($path)) {
      $this->backupManager->createBackup($path, 'delete');
    }
    
    if (is_dir($path)) {
      return $this->deleteDirectory($path);
    } else {
      return unlink($path);
    }
  }
  
  private function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
  }

  public function moveFile($from, $to) {
    // Create backup before moving
    if (file_exists($from) && !is_dir($from)) {
      $this->backupManager->createBackup($from, 'move');
    }
    
    return rename($from, $to);
  }
  
  public function copyFile($from, $to) {
      if (is_dir($from)) {
          return $this->copyDirectory($from, $to);
      } else {
          return copy($from, $to);
      }
  }

  private function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                $this->copyDirectory($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
    return true;
  }

  public function changePermissions($path, $permissions) {
    return chmod($path, octdec($permissions));
  }
  
  public function saveFile($path, $content) {
    // Create backup before saving if file exists
    if (file_exists($path)) {
      $this->backupManager->createBackup($path, 'edit');
    }
    
    return file_put_contents($path, $content) !== false;
  }

  public function getFileContent($path) {
      if (file_exists($path) && !is_dir($path)) {
          return file_get_contents($path);
      }
      return false;
  }

  public function searchFiles($path, $query) {
      $results = [];
      $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::SELF_FIRST
      );

      foreach ($iterator as $file) {
          if (stripos($file->getFilename(), $query) !== false) {
              // jsTree search ajax expects IDs of nodes to open.
              // If we match a file /A/B/C.txt, we need to open /A and /A/B.
              // Actually, jsTree needs the ID of the node itself to show it.
              // And if it's lazy loaded, it needs to know to load the parents.
              // If we return the ID of the match, jsTree will try to reveal it.
              $results[] = $file->getPathname();
          }
      }
      
      // We should also return unique parent paths to ensure they are opened?
      // jsTree usually handles opening parents if the ID structure allows or if we provide them.
      // But let's just return the matches first.
      return array_values(array_unique($results));
  }
}
