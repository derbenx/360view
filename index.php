<?php
$dir = './360-8K';
$thumbsDir = './thumbs';
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
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
                $files[] = $path;
            }
        }
    }
    return $files;
}

$allFiles = scanAllFiles($dir);
sort($allFiles);

// Check if any thumbnails are missing to conditionally load thumbsup.js
$needsThumbsup = false;
foreach ($allFiles as $file) {
    $realBase = realpath($dir);
    $realFile = realpath($file);
    if ($realFile !== false && strpos($realFile, $realBase) === 0) {
        $relative = substr($realFile, strlen($realBase));
        $thumbPath = './thumbs' . $relative;
        if (!file_exists($thumbPath)) {
            $needsThumbsup = true;
            break;
        }
    }
}
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
            <?php foreach($allFiles as $file):
                $fileName = basename($file);
                $relativeDir = trim(str_replace('./360-8K', '', dirname($file)), '/');
            ?>
                <div class="tile open-image" data-src="<?php echo htmlspecialchars($file); ?>" data-filename="<?php echo htmlspecialchars($fileName); ?>" data-thumb="thumbnail.php?file=<?php echo urlencode($file); ?>">
                    <div class="tile-image-container">
                        <div class="placeholder"></div>
                    </div>
                    <div class="tile-info">
                        <span class="tile-name"><?php echo htmlspecialchars($fileName); ?></span>
                        <?php if ($relativeDir): ?>
                            <span class="tile-path"><?php echo htmlspecialchars($relativeDir); ?></span>
                        <?php endif; ?>
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

    <script src="script.js"></script>
    <?php if ($needsThumbsup): ?>
        <script src="thumbsup.js"></script>
    <?php endif; ?>
</body>
</html>