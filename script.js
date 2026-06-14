// Config
const ENABLE_THUMBNAILS = true;
const DOWNLOAD_TIMEOUT = 15000; // 15 seconds before considering a stall
const MAX_RETRIES = 3;

AFRAME.registerComponent('exit-vr-on-button', {
    init: function () {
        this.el.addEventListener('bbuttondown', () => {
            console.log('B-button pressed: Exiting VR/Viewer');
            const scene = document.querySelector('a-scene');
            if (scene.is('vr-mode')) {
                scene.exitVR();
            }
            document.getElementById('overlay').classList.add('hidden');
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const loadingOverlay = document.getElementById('loading-overlay');
    const progressBar = document.getElementById('progress-bar');
    const loadingStatus = document.getElementById('loading-status');
    const overlay = document.getElementById('overlay');
    const scene = document.getElementById('vr-scene');
    const vrButtonContainer = document.getElementById('vr-button-container');

    let currentFileLoader = null;
    let retryCount = 0;

    console.log('script.js: DOMContentLoaded. ENABLE_THUMBNAILS =', ENABLE_THUMBNAILS);

    // --- Thumbnails ---
    if (ENABLE_THUMBNAILS) {
        document.querySelectorAll('.tile').forEach(tile => {
            const thumbSrc = tile.getAttribute('data-thumb');
            const filename = tile.getAttribute('data-filename');
            const imgContainer = tile.querySelector('.tile-image-container');

            const img = document.createElement('img');
            img.src = thumbSrc;
            img.alt = filename;
            img.loading = 'lazy';
            img.onload = () => {
                imgContainer.querySelector('.placeholder').style.display = 'none';
                imgContainer.appendChild(img);
            };
            img.onerror = () => {
                console.warn(`Thumbnail not found for ${filename}. Keeping placeholder.`);
            };
        });
    } else {
        console.log('Thumbnails disabled. Strictly showing placeholders in gallery.');
    }

    // --- VR Button Management ---
    function checkVRSupport() {
        if (navigator.xr) {
            navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
                console.log('WebXR immersive-vr supported:', supported);
                if (supported) {
                    createVRButton();
                }
            });
        } else {
            console.log("WebXR (navigator.xr) not available in this browser.");
        }
    }

    function createVRButton() {
        vrButtonContainer.innerHTML = '';
        const btn = document.createElement('button');
        btn.textContent = 'Enter VR';
        btn.id = 'custom-vr-button';
        btn.style.cssText = `
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
            pointer-events: auto;
        `;
        btn.addEventListener('click', () => {
            console.log('Entering VR mode...');
            scene.enterVR();
        });
        vrButtonContainer.appendChild(btn);
    }

    checkVRSupport();

    // --- UI Logic ---
    function closeViewer() {
        console.log('Closing 360 viewer');
        if (overlay.classList.contains('hidden')) return;

        overlay.classList.add('hidden');
        if (scene.is('vr-mode')) {
            scene.exitVR();
        }
    }

    document.getElementById('back-button').addEventListener('click', () => {
        if (window.history.state && window.history.state.viewerOpen) {
            window.history.back();
        } else {
            closeViewer();
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state || !event.state.viewerOpen) {
            closeViewer();
        }
    });

    document.getElementById('cancel-load').addEventListener('click', () => {
        console.log('Loading cancelled by user');
        if (currentFileLoader) {
            currentFileLoader = null;
        }
        loadingOverlay.classList.add('hidden');
    });

    document.querySelectorAll('.open-image').forEach(item => {
        item.addEventListener('click', e => {
            const tile = e.currentTarget;
            const src = tile.getAttribute('data-src');
            const fileName = tile.getAttribute('data-filename').toUpperCase();
            const needsThumb = tile.getAttribute('data-needs-thumb') === 'true';

            console.log('Tile clicked:', { src, fileName, needsThumb });

            requestOrientationPermission();
            loadImageWithRetry(src, fileName, needsThumb);
        });
    });

    function requestOrientationPermission() {
        if (typeof DeviceOrientationEvent !== 'undefined' &&
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            console.log('Requesting DeviceOrientation permission...');
            DeviceOrientationEvent.requestPermission()
                .then(permissionState => {
                    console.log('DeviceOrientation permission state:', permissionState);
                })
                .catch(err => console.error('Permission request error:', err));
        }
    }

    function loadImageWithRetry(src, fileName, needsThumb = false) {
        loadingOverlay.classList.remove('hidden');
        document.getElementById('cancel-load').classList.remove('hidden');
        loadingStatus.textContent = "Loading image...";
        progressBar.style.width = '0%';
        retryCount = 0;

        const performLoad = (url) => {
            console.log('Starting image load:', url);
            const loader = new THREE.FileLoader();
            currentFileLoader = loader;
            loader.setResponseType('blob');

            let timeoutId = setTimeout(() => {
                if (currentFileLoader === loader) {
                    console.warn("Download stalled (timeout), retrying...");
                    handleRetry();
                }
            }, DOWNLOAD_TIMEOUT);

            loader.load(
                url,
                (blob) => {
                    clearTimeout(timeoutId);
                    if (currentFileLoader !== loader) return;

                    console.log('Blob loaded successfully. Size:', blob.size);
                    const blobUrl = URL.createObjectURL(blob);
                    const textureLoader = new THREE.TextureLoader();
                    textureLoader.load(
                        blobUrl,
                        (texture) => {
                            console.log('Texture created successfully.');
                            URL.revokeObjectURL(blobUrl);
                            texture.colorSpace = THREE.SRGBColorSpace;

                            if (needsThumb && typeof window.generateAndUploadThumbnail === 'function') {
                                window.generateAndUploadThumbnail(texture.image, src);
                            }

                            setupScene(texture, fileName);
                            loadingOverlay.classList.add('hidden');
                        },
                        undefined,
                        (err) => {
                            console.error('TextureLoader error:', err);
                            loadingOverlay.classList.add('hidden');
                            alert('Failed to process image texture.');
                        }
                    );
                },
                (xhr) => {
                    if (xhr.lengthComputable) {
                        const percentComplete = (xhr.loaded / xhr.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => {
                            if (currentFileLoader === loader) {
                                console.warn("Download stalled (no progress), retrying...");
                                handleRetry();
                            }
                        }, DOWNLOAD_TIMEOUT);
                    }
                },
                (err) => {
                    clearTimeout(timeoutId);
                    if (currentFileLoader !== loader) return;
                    console.error('FileLoader error:', err);
                    handleRetry();
                }
            );

            function handleRetry() {
                if (retryCount < MAX_RETRIES) {
                    retryCount++;
                    console.log(`Retry attempt ${retryCount}/${MAX_RETRIES} for ${src}`);
                    loadingStatus.textContent = `Stalled. Retry ${retryCount}/${MAX_RETRIES}...`;
                    performLoad(src + '?t=' + Date.now());
                } else {
                    console.error('Maximum retries reached.');
                    alert('Failed to load image after multiple attempts.');
                    loadingOverlay.classList.add('hidden');
                }
            }
        };

        performLoad(src);
    }

    function setupScene(texture, fileName) {
        console.log('Setting up 360 scene for:', fileName);

        const oldSkies = scene.querySelectorAll('a-sky');
        oldSkies.forEach(sky => {
            const mesh = sky.getObject3D('mesh');
            if (mesh && mesh.material) {
                if (mesh.material.map) mesh.material.map.dispose();
                mesh.material.dispose();
            }
            sky.parentNode.removeChild(sky);
        });

        const isOU = fileName.includes('_OU');
        const isUO = fileName.includes('_UO');

        if (isOU || isUO) {
            console.log('Detected Stereo (Over-Under) image');
            const leftEyeSky = document.createElement('a-sky');
            leftEyeSky.setAttribute('radius', '5000');
            scene.appendChild(leftEyeSky);

            const rightEyeSky = document.createElement('a-sky');
            rightEyeSky.setAttribute('radius', '5000');
            scene.appendChild(rightEyeSky);

            leftEyeSky.addEventListener('loaded', () => {
                const mesh = leftEyeSky.getObject3D('mesh');
                mesh.material.map = texture;
                mesh.material.map.repeat.set(1, 0.5);
                mesh.material.map.offset.set(0, isOU ? 0.5 : 0);
                mesh.material.needsUpdate = true;
                mesh.layers.set(1);
                mesh.layers.enable(0);
            });

            rightEyeSky.addEventListener('loaded', () => {
                const mesh = rightEyeSky.getObject3D('mesh');
                mesh.material.map = texture.clone();
                mesh.material.map.repeat.set(1, 0.5);
                mesh.material.map.offset.set(0, isOU ? 0 : 0.5);
                mesh.material.needsUpdate = true;
                mesh.layers.set(2);
            });

            const updateCameraLayers = () => {
                const camera = scene.camera;
                if (!camera) return;
                if (scene.is('vr-mode')) {
                    camera.layers.enable(1);
                    camera.layers.enable(2);
                } else {
                    camera.layers.disable(1);
                    camera.layers.disable(2);
                    camera.layers.enable(0);
                }
            };
            scene.addEventListener('enter-vr', updateCameraLayers);
            scene.addEventListener('exit-vr', updateCameraLayers);
        } else {
            console.log('Detected Mono (360) image');
            const newSky = document.createElement('a-sky');
            scene.appendChild(newSky);
            newSky.addEventListener('loaded', () => {
                const mesh = newSky.getObject3D('mesh');
                mesh.material.map = texture;
                mesh.material.needsUpdate = true;
            });
        }

        overlay.classList.remove('hidden');
        window.history.pushState({ viewerOpen: true }, "");

        if (scene.resize) scene.resize();
        scene.play();
        if (scene.render) scene.render();
    }
});

AFRAME.registerComponent('thumbstick-rotate', {
    init: function () {
        this.axis = { x: 0, y: 0 };
        this.isGripping = false;
        this.el.addEventListener('gripdown', () => { this.isGripping = true; });
        this.el.addEventListener('gripup', () => { this.isGripping = false; });
        this.el.addEventListener('thumbstickmoved', (evt) => {
            this.axis.x = evt.detail.x;
            this.axis.y = evt.detail.y;
        });
    },
    tick: function () {
        if (!this.isGripping) return;
        const skies = document.querySelectorAll('a-sky');
        skies.forEach(sky => {
            const rotation = sky.getAttribute('rotation');
            if (Math.abs(this.axis.x) > 0.10) {
                rotation.y -= this.axis.x * 1.5;
                sky.setAttribute('rotation', rotation);
            }
        });
    }
});