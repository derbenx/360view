/**
 * thumbsup.js - Handles client-side thumbnail generation and uploading.
 * This script is conditionally loaded only when thumbnails are missing.
 */

window.generateAndUploadThumbnail = function(imageElement, sourcePath) {
    console.log('thumbsup.js: Starting generation for', sourcePath);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = 150;
    canvas.height = 100;

    // Fill background
    ctx.fillStyle = '#333333';
    ctx.fillRect(0, 0, 150, 100);

    // Calculate aspect ratio preserve
    const imgWidth = imageElement.naturalWidth || imageElement.width;
    const imgHeight = imageElement.naturalHeight || imageElement.height;

    if (!imgWidth || !imgHeight) {
        console.error('thumbsup.js: Invalid image dimensions', imgWidth, imgHeight);
        return;
    }

    const imgAspect = imgWidth / imgHeight;
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
    console.log('thumbsup.js: Data URL generated, size:', dataUrl.length);

    // Upload
    fetch('thumbnail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            file: sourcePath,
            data: dataUrl
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => { throw new Error(text || response.statusText) });
        }
        return response.json();
    })
    .then(data => {
        console.log('thumbsup.js: Upload successful', data);
        // Update the UI tile with the new thumbnail
        if (data.status === 'success') {
            const tile = document.querySelector(`.tile[data-src="${CSS.escape(sourcePath)}"]`);
            if (tile) {
                tile.removeAttribute('data-needs-thumb');
                const img = tile.querySelector('img');
                // Use the direct thumbnail URL returned or pre-calculated
                const thumbUrl = tile.getAttribute('data-thumb');
                const finalThumbUrl = thumbUrl + '?t=' + Date.now();

                if (img) {
                    img.src = finalThumbUrl;
                } else {
                    const newImg = document.createElement('img');
                    newImg.src = finalThumbUrl;
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
    .catch(err => {
        console.error('thumbsup.js: Upload failed', err);
    });
};