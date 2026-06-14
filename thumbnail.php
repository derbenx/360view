<?php
// thumbnail.php - Handle thumbnail serving and crowd-sourced generation
$thumbsDir = './thumbs';
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

// Handle POST for crowd-sourced generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $file = isset($input['file']) ? $input['file'] : '';
    $data = isset($input['data']) ? $input['data'] : '';

    if (!$file || !$data) {
        header("HTTP/1.1 400 Bad Request");
        exit;
    }

    // Security: Ensure the file is within the 360-8K directory
    $realBase = realpath('./360-8K');
    $realFile = realpath($file);
    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        header("HTTP/1.1 403 Forbidden");
        exit;
    }

    $thumbName = md5($file) . '.jpg';
    $thumbPath = $thumbsDir . '/' . $thumbName;

    // Only save if it doesn't exist
    if (!file_exists($thumbPath)) {
        // Decode base64 data
        $data = str_replace('data:image/jpeg;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        $decodedData = base64_decode($data);
        file_put_contents($thumbPath, $decodedData);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'exists']);
    }
    exit;
}

// Handle GET for serving thumbnails
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (!$file || !file_exists($file)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$thumbName = md5($file) . '.jpg';
$thumbPath = $thumbsDir . '/' . $thumbName;

if (file_exists($thumbPath)) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath);
    exit;
} else {
    // If it doesn't exist, we just return 404.
    // The client will generate and upload it when they load the full image.
    header("HTTP/1.1 404 Not Found");
    exit;
}
?>