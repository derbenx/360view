<?php
// thumbnail.php - Secure, crowd-sourced thumbnail management with mirrored folder structure
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

$baseDir = __DIR__ . '/360-8K';
$thumbsRootDir = __DIR__ . '/thumbs';

// Security: Ensure base directories exist
if (!is_dir($baseDir)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['error' => 'Base directory not found']);
    exit;
}
if (!is_dir($thumbsRootDir)) {
    if (!mkdir($thumbsRootDir, 0755, true)) {
        error_log("Failed to create thumbs root directory: $thumbsRootDir");
    }
}

// Helper to get thumb path from source path
function getThumbPath($sourceFile, $thumbsRootDir, $baseDir) {
    // sourceFile comes in as ./360-8K/path/to/file.jpg
    // We want to resolve it against the real base
    $realBase = realpath($baseDir);
    $realFile = realpath($sourceFile);

    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        error_log("Security/Path issue: Source $sourceFile, RealFile " . ($realFile ?: 'false') . ", RealBase $realBase");
        return false;
    }

    $relative = substr($realFile, strlen($realBase));
    return $thumbsRootDir . $relative;
}

// Handle POST for crowd-sourced generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $file = isset($input['file']) ? $input['file'] : '';
    $data = isset($input['data']) ? $input['data'] : '';

    if (!$file || !$data || strlen($data) > 250000) { // Bumped to 250KB just in case
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid data or size too large (' . strlen($data) . ')']);
        exit;
    }

    $thumbPath = getThumbPath($file, $thumbsRootDir, $baseDir);
    if (!$thumbPath) {
        header("HTTP/1.1 403 Forbidden");
        echo json_encode(['error' => 'Path forbidden or invalid']);
        exit;
    }

    // Source file must exist and Thumb must NOT already exist
    // Note: We use the raw $file for file_exists because realpath() was used inside getThumbPath
    if (!file_exists($file)) {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Source file not found']);
        exit;
    }

    if (file_exists($thumbPath)) {
        echo json_encode(['status' => 'exists']);
        exit;
    }

    // Ensure thumb subdirectory exists
    $thumbDir = dirname($thumbPath);
    if (!is_dir($thumbDir)) {
        if (!mkdir($thumbDir, 0755, true)) {
            error_log("Failed to create directory: $thumbDir");
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to create thumb directory']);
            exit;
        }
    }

    // Decode base64 data
    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $decodedData = base64_decode($data);

    if ($decodedData) {
        if (file_put_contents($thumbPath, $decodedData)) {
            echo json_encode(['status' => 'success', 'path' => $thumbPath]);
        } else {
            error_log("Failed to write thumbnail to: $thumbPath");
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to write file']);
        }
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