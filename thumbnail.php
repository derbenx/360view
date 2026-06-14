<?php
// thumbnail.php - Secure, crowd-sourced thumbnail management with mirrored folder structure

$baseDir = './360-8K';
$thumbsRootDir = './thumbs';

// Security: Ensure base directories exist
if (!is_dir($baseDir)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Base directory not found.";
    exit;
}
if (!is_dir($thumbsRootDir)) {
    mkdir($thumbsRootDir, 0755, true);
}

// Helper to get thumb path from source path
function getThumbPath($sourceFile, $thumbsRootDir, $baseDir) {
    $realBase = realpath($baseDir);
    $realFile = realpath($sourceFile);

    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        return false;
    }

    $relative = substr($realFile, strlen($realBase));
    return $thumbsRootDir . $relative;
}

// Handle POST for crowd-sourced generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $file = isset($input['file']) ? $input['file'] : '';
    $data = isset($input['data']) ? $input['data'] : '';

    // 1. Size Check: Reject if data is too large (100KB should be plenty for 150x100 JPG)
    if (!$file || !$data || strlen($data) > 150000) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid data or size too large']);
        exit;
    }

    $thumbPath = getThumbPath($file, $thumbsRootDir, $baseDir);
    if (!$thumbPath) {
        header("HTTP/1.1 403 Forbidden");
        exit;
    }

    // 2. Source file must exist and 3. Thumb must NOT already exist
    if (!file_exists($file) || file_exists($thumbPath)) {
        echo json_encode(['status' => 'exists_or_invalid_source']);
        exit;
    }

    // Ensure thumb subdirectory exists
    $thumbDir = dirname($thumbPath);
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    // Decode base64 data
    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $decodedData = base64_decode($data);

    if ($decodedData) {
        file_put_contents($thumbPath, $decodedData);
        echo json_encode(['status' => 'success']);
    } else {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid base64 data']);
    }
    exit;
}

// Handle GET for serving thumbnails
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (!$file) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$thumbPath = getThumbPath($file, $thumbsRootDir, $baseDir);
if ($thumbPath && file_exists($thumbPath)) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath);
    exit;
} else {
    header("HTTP/1.1 404 Not Found");
    exit;
}
?>