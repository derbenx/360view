/**
 * thumbsup.js - Handles client-side thumbnail generation and uploading.
 * This script is conditionally loaded only when thumbnails are missing.
 */

window.generateAndUploadThumbnail = function(imageElement, sourcePath) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = 150;
    canvas.height = 100;

    // Fill background
    ctx.fillStyle = '#333333';
    ctx.fillRect(0, 0, 150, 100);

    // Calculate aspect ratio preserve
    const imgAspect = imageElement.width / imageElement.height;
    const targetAspect = 150 / 100;
    let drawWidth, drawHeight, x, y;

    if (imgAspect > targetAspect) {
        drawWidth = 150;
        drawHeight = 150 / imgAspect;
        x = 0;
        y = (100 - drawHeight) / 2;
    } else {
        drawHeight = 100;
        drawWidth = 100 * imgAspect;
        y = 0;
        x = (150 - drawWidth) / 2;
    }

    ctx.drawImage(imageElement, x, y, drawWidth, drawHeight);

    // Convert to base64
    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);

    // Upload
    fetch('thumbnail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            file: sourcePath,
            data: dataUrl
        })
    })
    .then(res => res.json())
    .then(data => {
        console.log('Thumbnail upload status:', data.status);
        // Update the UI tile with the new thumbnail
        if (data.status === 'success') {
            const tile = document.querySelector(`.tile[data-src="${CSS.escape(sourcePath)}"]`);
            if (tile) {
                tile.removeAttribute('data-needs-thumb');
                const img = tile.querySelector('img');
                if (img) {
                    img.src = 'thumbnail.php?file=' + encodeURIComponent(sourcePath) + '&t=' + Date.now();
                } else {
                    const newImg = document.createElement('img');
                    newImg.src = 'thumbnail.php?file=' + encodeURIComponent(sourcePath) + '&t=' + Date.now();
                    newImg.alt = tile.getAttribute('data-filename');
                    newImg.loading = 'lazy';
                    newImg.onload = () => {
                        tile.querySelector('.placeholder').style.display = 'none';
                        tile.querySelector('.tile-image-container').appendChild(newImg);
                    };
                }
            }
        }
    })
    .catch(err => console.error('Error uploading thumbnail:', err));
};