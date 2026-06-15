<?php
// index.php - Main Gallery
$debugLog = 1;
if ($debugLog) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_error.log');
    error_log("--- index.php reload ---");
}

$baseDir = __DIR__ . '/360-8K';
$thumbsRootDir = __DIR__ . '/thumbs';

// Ensure thumbs root directory exists
if (!is_dir($thumbsRootDir)) {
    @mkdir($thumbsRootDir, 0777, true);
}

function scanAllFiles($dir) {
    $files = [];
    if (!is_dir($dir)) return [];
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $files = array_merge($files, scanAllFiles($path));
        } else {
            if (preg_match('/\.(jpg|jpeg|png|webp|jfif)$/i', $item)) {
                $files[] = realpath($path);
            }
        }
    }
    return $files;
}

// Navigation and Path Security
$currentRelDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$currentRelDir = str_replace(['..', '\\'], ['', '/'], $currentRelDir);
$currentRelDir = trim($currentRelDir, '/');

$realBase = realpath($baseDir);
$targetDir = realpath($realBase . ($currentRelDir ? '/' . $currentRelDir : ''));

if ($targetDir === false || strpos($targetDir, $realBase) !== 0) {
    $targetDir = $realBase;
    $currentRelDir = '';
}

// Scanning Logic
function getDirContents($dir, $realBase) {
    $files = [];
    $folders = [];
    if (!is_dir($dir)) return [[], []];

    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        $realPath = realpath($path);
        if ($realPath === false) continue;

        if (is_dir($realPath)) {
            $folders[] = [
                'name' => $item,
                'path' => $realPath,
                'relative' => trim(substr($realPath, strlen($realBase)), DIRECTORY_SEPARATOR)
            ];
        } else {
            if (preg_match('/\.(jpg|jpeg|png|webp|jfif)$/i', $item)) {
                $files[] = $realPath;
            }
        }
    }
    return [$folders, $files];
}

list($currentFolders, $currentFiles) = getDirContents($targetDir, $realBase);
sort($currentFolders);
sort($currentFiles);

$allFiles = scanAllFiles($baseDir);

// Thumbnail Cleanup Routine with Whitelist
function cleanupThumbs($thumbsDir, $allSourceFiles, $realBase, $debugLog) {
    if (!is_dir($thumbsDir)) return;

    $whitelist = ['back.jpg', 'folder.jpg'];
    $realThumbsBase = realpath($thumbsDir);
    if (!$realThumbsBase) return;

    // Convert allSourceFiles to an associative array for O(1) lookup
    $sourceLookup = array_flip($allSourceFiles);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realThumbsBase, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getRealPath();
        $filename = $item->getFilename();

        if ($item->isDir()) {
            $files = scandir($path);
            if (count($files) === 2) { // only . and ..
                if ($debugLog) error_log("Cleaning up empty thumb dir: $path");
                @rmdir($path);
            }
        } else {
            if (in_array($filename, $whitelist)) continue;

            $relative = substr($path, strlen($realThumbsBase));
            $sourceFile = realpath($realBase . $relative);

            if ($sourceFile === false || !isset($sourceLookup[$sourceFile])) {
                if ($debugLog) error_log("Cleaning up orphaned thumb: $path");
                @unlink($path);
            }
        }
    }
}

cleanupThumbs($thumbsRootDir, $allFiles, $realBase, $debugLog);

// Check for missing thumbnails to conditionally load thumbsup.js
$needsThumbsup = false;
foreach ($currentFiles as $file) {
    $relative = substr($file, strlen($realBase));
    $thumbPath = $thumbsRootDir . $relative;
    if (!file_exists($thumbPath)) {
        $needsThumbsup = true;
        break;
    }
}
if ($debugLog) error_log("needsThumbsup result: " . ($needsThumbsup ? 'TRUE' : 'FALSE'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>360 Viewer</title>
    <script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dark-theme">
    <header id="header">
        <h1>360 Viewer</h1>
        <p id="blurb">
            Drag to look around. VR button appears at top when a headset is detected.
            Stereo images (_OU/_UO) take longer to load.
        </p>
    </header>

    <main id="gallery-container">
        <div id="gallery-grid">
            <?php if ($currentRelDir):
                $parentDir = dirname($currentRelDir);
                if ($parentDir === '.') $parentDir = '';
                $backThumb = './thumbs/back.jpg';
                $hasBackThumb = file_exists(__DIR__ . '/' . $backThumb);
            ?>
                <div class="tile folder-tile" data-path="<?php echo htmlspecialchars($parentDir); ?>">
                    <div class="tile-image-container">
                        <div class="placeholder"></div>
                        <?php if ($hasBackThumb): ?>
                            <img src="<?php echo htmlspecialchars($backThumb); ?>" alt="Back"
                                 onload="this.previousElementSibling.style.display='none'; this.style.display='block';">
                        <?php endif; ?>
                    </div>
                    <div class="tile-info">
                        <span class="tile-name">.. Back</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach($currentFolders as $folder):
                $folderThumb = './thumbs/folder.jpg';
                $hasFolderThumb = file_exists(__DIR__ . '/' . $folderThumb);
            ?>
                <div class="tile folder-tile" data-path="<?php echo htmlspecialchars(str_replace('\\', '/', $folder['relative'])); ?>">
                    <div class="tile-image-container">
                        <div class="placeholder"></div>
                        <?php if ($hasFolderThumb): ?>
                            <img src="<?php echo htmlspecialchars($folderThumb); ?>" alt="Folder"
                                 onload="this.previousElementSibling.style.display='none'; this.style.display='block';">
                        <?php endif; ?>
                    </div>
                    <div class="tile-info">
                        <span class="tile-name"><?php echo htmlspecialchars($folder['name']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach($currentFiles as $file):
                $fileName = basename($file);
                $relative = substr($file, strlen($realBase));
                $normalizedRel = str_replace('\\', '/', $relative);
                $thumbUrl = './thumbs' . $normalizedRel;
                $hasThumb = file_exists(__DIR__ . '/' . $thumbUrl);
                $webPath = './360-8K' . $normalizedRel;
            ?>
                <div class="tile open-image"
                     data-src="<?php echo htmlspecialchars($webPath); ?>"
                     data-filename="<?php echo htmlspecialchars($fileName); ?>"
                     data-thumb="<?php echo htmlspecialchars($thumbUrl); ?>"
                     data-needs-thumb="<?php echo $hasThumb ? 'false' : 'true'; ?>">
                    <div class="tile-image-container">
                        <div class="placeholder"></div>
                        <?php if ($hasThumb): ?>
                            <img src="<?php echo htmlspecialchars($thumbUrl); ?>"
                                 alt="<?php echo htmlspecialchars($fileName); ?>"
                                 onload="this.previousElementSibling.style.display='none'; this.style.display='block';">
                        <?php endif; ?>
                    </div>
                    <div class="tile-info">
                        <span class="tile-name"><?php echo htmlspecialchars($fileName); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="loading-overlay" class="hidden">
        <div id="loading-content">
            <p id="loading-status">Loading image...</p>
            <div id="progress-bar-container">
                <div id="progress-bar"></div>
            </div>
            <button id="cancel-load" class="hidden">Cancel</button>
        </div>
    </div>

    <div id="overlay" class="hidden">
        <div id="ui-layer">
            <button id="back-button">Back to Gallery</button>
            <div id="vr-button-container"></div>
        </div>

        <a-scene embedded id="vr-scene" vr-mode-ui="enabled: false" device-orientation-permission-ui="enabled: false">
            <a-entity camera look-controls="magicWindowTrackingEnabled: true; touchEnabled: true; mouseEnabled: true"></a-entity>

            <a-entity id="right-hand" oculus-touch-controls="hand: right" thumbstick-rotate exit-vr-on-button></a-entity>
            <a-entity id="left-hand" oculus-touch-controls="hand: left" thumbstick-rotate exit-vr-on-button></a-entity>
        </a-scene>
    </div>

    <script>
        const DEBUG = <?php echo $debugLog ? 'true' : 'false'; ?>;
        function debugLog(...args) {
            if (DEBUG) console.log(...args);
        }
    </script>
    <script src="script.js"></script>
    <?php if ($needsThumbsup): ?>
        <script src="thumbsup.js"></script>
    <?php endif; ?>
</body>
</html>