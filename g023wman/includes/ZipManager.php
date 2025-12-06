<?php
class ZipManager {
  public static function extractZip($zipPath, $extractTo) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) === TRUE) {
      $zip->extractTo($extractTo);
      $zip->close();
      return true;
    }
    return false;
  }
  
  public static function createZip($files, $zipPath) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
      foreach ($files as $file) {
        if (is_dir($file)) {
          self::addDirectoryToZip($zip, $file, basename($file));
        } else {
          if (file_exists($file)) {
              $zip->addFile($file, basename($file));
          }
        }
      }
      $zip->close();
      return true;
    }
    return false;
  }
  
  private static function addDirectoryToZip($zip, $dir, $zipDir) {
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
      if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($dir) + 1);
        $zip->addFile($filePath, $zipDir . '/' . $relativePath);
      }
    }
  }
}
