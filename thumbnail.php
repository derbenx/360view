<?php
// thumbnail.php - Secure, crowd-sourced thumbnail management with mirrored folder structure
$debugLog = 1;

if ($debugLog) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_error.log');
}

$baseDir = __DIR__ . '/360-8K';
$thumbsRootDir = __DIR__ . '/thumbs';

if ($debugLog) error_log("--- thumbnail.php request (" . $_SERVER['REQUEST_METHOD'] . ") ---");

// Security: Ensure base directories exist
if (!is_dir($baseDir)) {
    header("HTTP/1.1 500 Internal Server Error");
    if ($debugLog) error_log("CRITICAL: Base directory not found at $baseDir");
    echo json_encode(['error' => 'Base directory not found']);
    exit;
}
if (!is_dir($thumbsRootDir)) {
    if (!mkdir($thumbsRootDir, 0755, true)) {
        if ($debugLog) error_log("ERROR: Failed to create thumbs root directory: $thumbsRootDir");
    } else {
        if ($debugLog) error_log("INFO: Created thumbs root directory: $thumbsRootDir");
    }
}

// Helper to get thumb path from source path
function getThumbPath($sourceFile, $thumbsRootDir, $baseDir, $debugLog) {
    $realBase = realpath($baseDir);
    $realFile = realpath($sourceFile);

    if ($debugLog) error_log("Path resolving: source=$sourceFile, realBase=$realBase, realFile=" . ($realFile ?: 'FALSE'));

    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        if ($debugLog) error_log("SECURITY: Path violation or invalid source file: $sourceFile");
        return false;
    }

    $relative = substr($realFile, strlen($realBase));
    $target = $thumbsRootDir . $relative;
    if ($debugLog) error_log("Target thumb path resolved: $target");
    return $target;
}

// Handle POST for crowd-sourced generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $file = isset($input['file']) ? $input['file'] : '';
    $data = isset($input['data']) ? $input['data'] : '';

    if ($debugLog) error_log("POST Request: file=$file, dataLength=" . strlen($data));

    if (!$file || !$data || strlen($data) > 250000) {
        header("HTTP/1.1 400 Bad Request");
        $err = "Invalid input or data too large (" . strlen($data) . ")";
        if ($debugLog) error_log("ERROR: $err");
        echo json_encode(['error' => $err]);
        exit;
    }

    $thumbPath = getThumbPath($file, $thumbsRootDir, $baseDir, $debugLog);
    if (!$thumbPath) {
        header("HTTP/1.1 403 Forbidden");
        if ($debugLog) error_log("ERROR: getThumbPath returned false");
        echo json_encode(['error' => 'Path forbidden or invalid']);
        exit;
    }

    if (!file_exists($file)) {
        header("HTTP/1.1 404 Not Found");
        if ($debugLog) error_log("ERROR: Source file does not exist: $file");
        echo json_encode(['error' => 'Source file not found']);
        exit;
    }

    if (file_exists($thumbPath)) {
        if ($debugLog) error_log("INFO: Thumbnail already exists: $thumbPath");
        echo json_encode(['status' => 'exists']);
        exit;
    }

    $thumbDir = dirname($thumbPath);
    if (!is_dir($thumbDir)) {
        if (!mkdir($thumbDir, 0755, true)) {
            if ($debugLog) error_log("ERROR: Failed to create directory: $thumbDir");
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to create thumb directory']);
            exit;
        }
        if ($debugLog) error_log("INFO: Created sub-directory: $thumbDir");
    }

    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $decodedData = base64_decode($data);

    if ($decodedData) {
        if (file_put_contents($thumbPath, $decodedData)) {
            if ($debugLog) error_log("SUCCESS: Thumbnail saved to $thumbPath");
            echo json_encode(['status' => 'success', 'path' => $thumbPath]);
        } else {
            if ($debugLog) error_log("ERROR: file_put_contents failed for $thumbPath");
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['error' => 'Failed to write file']);
        }
    } else {
        if ($debugLog) error_log("ERROR: Base64 decode failed");
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Invalid base64 data']);
    }
    exit;
}

// Handle GET for serving thumbnails
$file = isset($_GET['file']) ? $_GET['file'] : '';
if ($debugLog) error_log("GET Request: file=$file");

if (!$file) {
    header("HTTP/1.1 404 Not Found");
    if ($debugLog) error_log("ERROR: No file specified in GET");
    exit;
}

$thumbPath = getThumbPath($file, $thumbsRootDir, $baseDir, $debugLog);
if ($thumbPath && file_exists($thumbPath)) {
    if ($debugLog) error_log("INFO: Serving thumbnail: $thumbPath");
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath);
    exit;
} else {
    if ($debugLog) error_log("INFO: Thumbnail NOT found: " . ($thumbPath ?: 'FALSE'));
    header("HTTP/1.1 404 Not Found");
    exit;
}
?>