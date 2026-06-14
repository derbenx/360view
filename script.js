AFRAME.registerComponent('exit-vr-on-button', {
    init: function () {
        // This listens for the 'b' button specifically
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
    // Back Button Listener
    document.getElementById('back-button').addEventListener('click', () => {
        document.getElementById('overlay').classList.add('hidden');
    });

    // Image List Click Handler
    document.querySelectorAll('.open-image').forEach(item => {
        item.addEventListener('click', e => {
            const src = e.target.getAttribute('data-src');
            const overlay = document.getElementById('overlay');
            const scene = document.getElementById('vr-scene');

            // 1. Remove old sky
            const oldSky = document.getElementById('vr-sky');
 if (oldSky) {
    const oldMaterial = oldSky.getObject3D('mesh').material;
    if (oldMaterial.map) {
        oldMaterial.map.dispose(); // Free up GPU memory
    }
    oldSky.remove();
}
// 1. Create the sky, but keep it invisible initially
const newSky = document.createElement('a-sky');
newSky.setAttribute('id', 'vr-sky');
newSky.setAttribute('visible', 'false'); // Hide until ready
scene.appendChild(newSky);

// 2. Load the texture
const textureLoader = new THREE.TextureLoader();
textureLoader.load(src, (texture) => {
    texture.colorSpace = THREE.SRGBColorSpace;
    
    // 3. Set material and reveal
    newSky.setAttribute('material', { src: texture });
    newSky.setAttribute('visible', 'true'); // Only show when the texture is ready
    
    overlay.classList.remove('hidden');

    const sceneEl = document.getElementById('vr-scene');
    
    // Force A-Frame to refresh its internal camera and renderer
    if (sceneEl.resize) {
        sceneEl.resize(); // Recalculates canvas size[cite: 2]
    }
    
    // Explicitly re-inject the scene into the render loop
    sceneEl.play(); 
    
    // Force a re-render to align the stereo eyes
    if (sceneEl.render) {
        sceneEl.render(); // Forces frame update[cite: 2]
    }
}); // End textureLoader callback
        }); // End click listener
    }); // End forEach
}); // End DOMContentLoaded

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
        console.log (Math.abs(this.axis.x), Math.abs(this.axis.y) );
        const sky = document.getElementById('vr-sky');
        if (sky) {
            // Apply rotation based on cached axis data
            const rotation = sky.getAttribute('rotation');
        if (Math.abs(this.axis.x) > 0.10) { rotation.y -= this.axis.x * 1.5; } 
        //if (Math.abs(this.axis.y) > 0.25) { rotation.x -= this.axis.y * 2; }
            sky.setAttribute('rotation', rotation);
        }
    }
});