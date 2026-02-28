<?php
$dbFile = 'fileDB.json';
$files = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

if (isset($_GET['id'])) {
    $fileId = $_GET['id'];
    $file = null;
    
    foreach ($files as $f) {
        if ($f['id'] == $fileId) {
            $file = $f;
            break;
        }
    }
    
    if ($file) {
        if (file_exists($file['path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['name']) . '"');
            header('Content-Length: ' . filesize($file['path']));
            readfile($file['path']);
            exit;
        }
    }
    
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}
?>
