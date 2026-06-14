// Config
const ENABLE_THUMBNAILS = false;
const THUMBS_DIR = 'thumbs/';
const DOWNLOAD_TIMEOUT = 15000; // 15 seconds before considering a stall
const MAX_RETRIES = 3;

AFRAME.registerComponent('exit-vr-on-button', {
    init: function () {
        this.el.addEventListener('bbuttondown', () => {
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

    // --- Thumbnails ---
    if (ENABLE_THUMBNAILS) {
        document.querySelectorAll('.tile').forEach(tile => {
            const filename = tile.getAttribute('data-filename');
            const imgContainer = tile.querySelector('.tile-image-container');
            const img = document.createElement('img');
            img.src = THUMBS_DIR + filename;
            img.alt = filename;
            img.loading = 'lazy';
            img.onload = () => {
                imgContainer.querySelector('.placeholder').style.display = 'none';
                imgContainer.appendChild(img);
            };
            img.onerror = () => {
                // Keep the placeholder if thumbnail fails
                console.warn(`Thumbnail not found for ${filename}`);
            };
        });
    }

    // --- VR Button Management ---
    function checkVRSupport() {
        if (navigator.xr) {
            navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
                if (supported) {
                    createVRButton();
                } else {
                    console.log("VR Session not supported");
                }
            });
        } else {
            console.log("WebXR not available");
        }
    }

    function createVRButton() {
        // Clear container
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
        `;
        btn.addEventListener('click', () => {
            scene.enterVR();
        });
        vrButtonContainer.appendChild(btn);
    }

    checkVRSupport();

    // --- UI Logic ---
    document.getElementById('back-button').addEventListener('click', () => {
        overlay.classList.add('hidden');
        if (scene.is('vr-mode')) {
            scene.exitVR();
        }
    });

    document.getElementById('cancel-load').addEventListener('click', () => {
        if (currentFileLoader) {
            // THREE.FileLoader doesn't have an abort() but we can ignore the results
            currentFileLoader = null;
        }
        loadingOverlay.classList.add('hidden');
    });

    document.querySelectorAll('.open-image').forEach(item => {
        item.addEventListener('click', e => {
            const tile = e.currentTarget;
            const src = tile.getAttribute('data-src');
            const fileName = tile.getAttribute('data-filename').toUpperCase();

            // Request orientation permission for mobile "Magic Window"
            requestOrientationPermission();

            loadImageWithRetry(src, fileName);
        });
    });

    function requestOrientationPermission() {
        if (typeof DeviceOrientationEvent !== 'undefined' &&
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            DeviceOrientationEvent.requestPermission()
                .then(permissionState => {
                    if (permissionState === 'granted') {
                        console.log("Orientation permission granted");
                    }
                })
                .catch(console.error);
        }
    }

    function loadImageWithRetry(src, fileName) {
        loadingOverlay.classList.remove('hidden');
        document.getElementById('cancel-load').classList.remove('hidden');
        loadingStatus.textContent = "Loading image...";
        progressBar.style.width = '0%';
        retryCount = 0;

        const performLoad = (url) => {
            const loader = new THREE.FileLoader();
            currentFileLoader = loader;
            loader.setResponseType('blob');

            let timeoutId = setTimeout(() => {
                if (currentFileLoader === loader) {
                    console.warn("Download stalled, retrying...");
                    handleRetry();
                }
            }, DOWNLOAD_TIMEOUT);

            loader.load(
                url,
                (blob) => {
                    clearTimeout(timeoutId);
                    if (currentFileLoader !== loader) return;

                    const blobUrl = URL.createObjectURL(blob);
                    const textureLoader = new THREE.TextureLoader();
                    textureLoader.load(blobUrl, (texture) => {
                        URL.revokeObjectURL(blobUrl);
                        texture.colorSpace = THREE.SRGBColorSpace;
                        setupScene(texture, fileName);
                        loadingOverlay.classList.add('hidden');
                    });
                },
                (xhr) => {
                    if (xhr.lengthComputable) {
                        const percentComplete = (xhr.loaded / xhr.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                        // Reset timeout on progress
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
                    console.error('Error loading image:', err);
                    handleRetry();
                }
            );

            function handleRetry() {
                if (retryCount < MAX_RETRIES) {
                    retryCount++;
                    loadingStatus.textContent = `Stalled. Retry ${retryCount}/${MAX_RETRIES}...`;
                    performLoad(src + '?t=' + Date.now()); // cache bust
                } else {
                    alert('Failed to load image after multiple attempts.');
                    loadingOverlay.classList.add('hidden');
                }
            }
        };

        performLoad(src);
    }

    function setupScene(texture, fileName) {
        // 1. Remove old sky entities
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
            const newSky = document.createElement('a-sky');
            scene.appendChild(newSky);
            newSky.addEventListener('loaded', () => {
                const mesh = newSky.getObject3D('mesh');
                mesh.material.map = texture;
                mesh.material.needsUpdate = true;
            });
        }

        overlay.classList.remove('hidden');
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