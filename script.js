AFRAME.registerComponent('exit-vr-on-button', {
    init: function () {
        this.el.addEventListener('bbuttondown', function () {
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
    const overlay = document.getElementById('overlay');
    const scene = document.getElementById('vr-scene');

    document.getElementById('back-button').addEventListener('click', () => {
        overlay.classList.add('hidden');
    });

    document.querySelectorAll('.open-image').forEach(item => {
        item.addEventListener('click', e => {
            const src = e.target.getAttribute('data-src');
            const fileName = src.split('/').pop().toUpperCase();

            loadingOverlay.classList.remove('hidden');
            progressBar.style.width = '0%';

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

            // 2. Load the file as a Blob to get progress
            const fileLoader = new THREE.FileLoader();
            fileLoader.setResponseType('blob');
            fileLoader.load(
                src,
                (blob) => {
                    const url = URL.createObjectURL(blob);
                    const textureLoader = new THREE.TextureLoader();
                    textureLoader.load(url, (texture) => {
                        URL.revokeObjectURL(url); // Clean up
                        texture.colorSpace = THREE.SRGBColorSpace;
                        setupScene(texture, fileName);
                    });
                },
                (xhr) => {
                    if (xhr.lengthComputable) {
                        const percentComplete = (xhr.loaded / xhr.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                },
                (err) => {
                    console.error('An error happened', err);
                    loadingOverlay.classList.add('hidden');
                    alert('Error loading image.');
                }
            );

            function setupScene(texture, fileName) {
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
                        mesh.layers.enable(0); // Show in mono too
                    });

                    rightEyeSky.addEventListener('loaded', () => {
                        const mesh = rightEyeSky.getObject3D('mesh');
                        // Use a clone for the second material to have different offset
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
                loadingOverlay.classList.add('hidden');

                if (scene.resize) scene.resize();
                scene.play();
                if (scene.render) scene.render();
            }
        });
    });
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
