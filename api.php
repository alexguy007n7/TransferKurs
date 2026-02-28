<?php
header('Content-Type: application/json');

$targetDir = "uploads/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}
$dbFile = 'fileDB.json';
$files = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_FILES['file'])) {
                echo json_encode(['error' => 'No file uploaded']);
                break;
            }
            $fileName = basename($_FILES['file']['name']);
            $targetFile = $targetDir . uniqid() . '_' . $fileName;
            $expiryMinutes = isset($_POST['expiry']) ? intval($_POST['expiry']) : 0;
            $expiresAt = null;
            if ($expiryMinutes > 0) {
                $expiresAt = date('Y-m-d H:i:s', time() + $expiryMinutes * 60);
            }
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                $fileData = [
                    'id' => uniqid(),
                    'name' => $fileName,
                    'path' => $targetFile,
                    'size' => $_FILES['file']['size'],
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt
                ];
                $files[] = $fileData;
                file_put_contents($dbFile, json_encode($files, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true, 'file' => [
                    'id' => $fileData['id'],
                    'name' => $fileData['name'],
                    'url' => $fileData['path'],
                    'download_url' => 'download.php?id=' . $fileData['id'],
                    'size' => $fileData['size'],
                    'uploaded_at' => $fileData['uploaded_at'],
                    'expires_at' => $fileData['expires_at']
                ]]);
            } else {
                echo json_encode(['error' => 'Upload failed']);
            }
        }
        break;

    case 'list':
        $response = [];
        foreach ($files as $file) {
            $response[] = [
                'id' => $file['id'],
                'name' => $file['name'],
                'url' => $file['path'],
                'download_url' => 'download.php?id=' . $file['id'],
                'size' => $file['size'],
                'uploaded_at' => $file['uploaded_at'],
                'expires_at' => $file['expires_at']
            ];
        }
        echo json_encode($response);
        break;

    case 'delete':
        if (isset($_GET['id'])) {
            $fileId = $_GET['id'];
            $index = -1;
            foreach ($files as $i => $file) {
                if ($file['id'] == $fileId) {
                    $index = $i;
                    break;
                }
            }
            if ($index !== -1) {
                unlink($files[$index]['path']);
                array_splice($files, $index, 1);
                file_put_contents($dbFile, json_encode($files, JSON_PRETTY_PRINT));
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'File not found']);
            }
        } else {
            echo json_encode(['error' => 'No ID provided']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
