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

// Thumbnail generation - one per request
$thumbGenerated = false;
foreach ($allFiles as $file) {
    // Use a hash of the file path to avoid collisions with same filenames in different folders
    $thumbName = md5($file) . '.jpg';
    $thumbPath = $thumbsDir . '/' . $thumbName;

    if (!file_exists($thumbPath) && !$thumbGenerated && extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($file);
            $imagick->thumbnailImage(150, 100, true, true);

            // Create a canvas with the target size and a grey background
            $canvas = new Imagick();
            $canvas->newImage(150, 100, new ImagickPixel('#333333'));
            $canvas->setImageFormat('jpg');

            // Center the thumbnail on the canvas
            $geometry = $imagick->getImageGeometry();
            $x = (150 - $geometry['width']) / 2;
            $y = (100 - $geometry['height']) / 2;

            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $x, $y);
            $canvas->writeImage($thumbPath);

            $imagick->clear();
            $canvas->clear();
            $thumbGenerated = true;
        } catch (Exception $e) {
            // Silently fail if there's an issue with one image
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
                <div class="tile open-image" data-src="<?php echo htmlspecialchars($file); ?>" data-filename="<?php echo htmlspecialchars($fileName); ?>" data-thumb="<?php echo htmlspecialchars($thumbsDir . '/' . md5($file) . '.jpg'); ?>">
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
</body>
</html>