<?php
// thumbnail.php - Handle large image thumbnail generation
ini_set('memory_limit', '512M'); // Increase memory limit for large images
set_time_limit(60); // Allow more time for processing

$thumbsDir = './thumbs';
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if (!$file || !file_exists($file)) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

// Security: Ensure the file is within the 360-8K directory
$realBase = realpath('./360-8K');
$realFile = realpath($file);
if (strpos($realFile, $realBase) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

$thumbName = md5($file) . '.jpg';
$thumbPath = $thumbsDir . '/' . $thumbName;

// If thumbnail already exists, serve it
if (file_exists($thumbPath)) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($thumbPath));
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath);
    exit;
}

// Otherwise, generate it if Imagick is available
if (extension_loaded('imagick')) {
    try {
        $imagick = new Imagick($file);

        // For very large images, we might want to sample them down first if possible
        // but for 360 images (equirectangular), standard thumbnailing is fine.
        $imagick->thumbnailImage(150, 100, true, true);

        $canvas = new Imagick();
        $canvas->newImage(150, 100, new ImagickPixel('#333333'));
        $canvas->setImageFormat('jpg');

        $geometry = $imagick->getImageGeometry();
        $x = (150 - $geometry['width']) / 2;
        $y = (100 - $geometry['height']) / 2;

        $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $x, $y);

        // Save to disk for future requests
        $canvas->writeImage($thumbPath);

        // Serve to user
        header('Content-Type: image/jpeg');
        echo $canvas->getImageBlob();

        $imagick->clear();
        $canvas->clear();
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
    }
} else {
    header("HTTP/1.1 500 Internal Server Error");
}
?>