<?php
$dir = './360-8K'; 
$files = array_diff(scandir($dir), array('.', '..'));

$phpFeedback = [];
$thumbsDir = './thumbs';
if (!is_dir($thumbsDir)) {
    if (mkdir($thumbsDir, 0755, true)) {
        $phpFeedback[] = "Thumbs directory created successfully.";
    } else {
        $error = error_get_last();
        $phpFeedback[] = "Error creating thumbs directory: " . ($error ? $error['message'] : 'unknown error');
    }
} else {
    $phpFeedback[] = "Thumbs directory already exists.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>360 Viewer</title>
    <script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div id="loading-overlay" class="hidden">
        <div id="loading-content">
            <p>Please wait, loading...</p>
            <div id="progress-bar-container">
                <div id="progress-bar"></div>
            </div>
        </div>
    </div>

    <ul>
        <?php foreach($files as $file): ?>
            <li>
                <a class="open-image" data-src="<?php echo $dir . '/' . $file; ?>">
                    <?php echo htmlspecialchars($file); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>



<div id="preload-container" style="display:none;"></div>

<div id="overlay" class="hidden">
    <div id="back-button" style="position:absolute; top:20px; left:20px; z-index:10000; background:white; padding:10px; cursor:pointer;">Back to Gallery</div>
    
<a-scene embedded id="vr-scene">
    
    <a-entity camera look-controls></a-entity>
 
    <a-entity 
    id="right-hand" 
    oculus-touch-controls="hand: right" 
    thumbstick-rotate 
    exit-vr-on-button>
    </a-entity>
    <a-entity 
    id="left-hand" 
    oculus-touch-controls="hand: left" 
    thumbstick-rotate 
    exit-vr-on-button>
    </a-entity>

</a-scene>
</div>

    <script src="script.js"></script>
    <script>
        <?php foreach ($phpFeedback as $message): ?>
            console.log(<?php echo json_encode('PHP: ' . $message); ?>);
        <?php endforeach; ?>
    </script>
</body>
</html>