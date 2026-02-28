<?php
$targetDir = "uploads/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (isset($_FILES["file"])) {
    $fileName = basename($_FILES["file"]["name"]);
    $targetFile = $targetDir . $fileName;
    
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
        // Simpan ke database atau file JSON
        $dbFile = 'fileDB.json';
        $db = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];
        
        $db[] = [
            'id' => uniqid(),
            'name' => $fileName,
            'path' => $targetFile,
            'size' => $_FILES["file"]["size"],
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($dbFile, json_encode($db, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true, 'file' => [
            'name' => $fileName,
            'url' => $targetFile,
            'size' => $_FILES["file"]["size"]
        ]]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
}

<?php
$dbFile = 'fileDB.json';
if (file_exists($dbFile)) {
    $files = json_decode(file_get_contents($dbFile), true);
    echo json_encode($files);
} else {
    echo '[]';
}
<?php
if (isset($_GET['id'])) {
    $dbFile = 'fileDB.json';
    $files = json_decode(file_get_contents($dbFile), true);
    
    $fileIndex = -1;
    foreach ($files as $i => $file) {
        if ($file['id'] == $_GET['id']) {
            $fileIndex = $i;
            break;
        }
    }
    
    if ($fileIndex !== -1) {
        unlink($files[$fileIndex]['path']);
        array_splice($files, $fileIndex, 1);
        file_put_contents($dbFile, json_encode($files, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'File not found']);
    }
}
?>
