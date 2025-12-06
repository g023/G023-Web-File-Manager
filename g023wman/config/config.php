<?php
// Main configuration // realpath is the path of this file, so ../ would be up one level from the ./config folder
return [
    'base_path' => realpath(__DIR__ . '/../../'), // Root of the project
    'upload_path' => realpath(__DIR__ . '/../uploads/'),
    'temp_path' => realpath(__DIR__ . '/../temp/'),
    'db_path' => __DIR__ . '/../db/filemanager.db',
    'app_name' => 'Web File Manager',
    'version' => '0.0.1'
];
