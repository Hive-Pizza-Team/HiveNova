{block name="content"}

<div id="threejs-container">
<style>
        body { margin: 0; }
        #threejs-container { position: fixed; touch-action: none; }
</style>

<script src="scripts/threejs/three.min.js"></script>

<script>
    const container = document.getElementById('threejs-container');

    function positionContainer() {
        const menuFixed = document.querySelector('menu .fixed');
        if (window.innerWidth <= 699 || !menuFixed) {
            // Mobile: full viewport, nav is not fixed/overlapping
            Object.assign(container.style, { top: '0', left: '0', width: '100vw', height: '100vh' });
        } else {
            const left = Math.round(menuFixed.getBoundingClientRect().right);
            const top = 100;
            Object.assign(container.style, {
                top:    top + 'px',
                left:   left + 'px',
                width:  (window.innerWidth  - left) + 'px',
                height: (window.innerHeight - top)  + 'px',
            });
        }
    }
    positionContainer();

    const scene = new THREE.Scene();
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(container.offsetWidth, container.offsetHeight);
    container.appendChild(renderer.domElement);

    const maxGalaxy  = {$maxGalaxy};
    const maxSystem  = {$maxSystem};
    const maxPlanets = {$maxPlanets};

    // Build galaxy centre positions arranged in a circle
    const layoutRadius = Math.max(30, maxGalaxy * 8);
    const circleGroups = [];
    for (let i = 0; i < maxGalaxy; i++) {
        const angle = (i / maxGalaxy) * 2 * Math.PI - Math.PI / 2;
        circleGroups.push({
            x: layoutRadius * Math.cos(angle),
            y: layoutRadius * Math.sin(angle)
        });
    }

    const vizRadius = layoutRadius + maxSystem * 0.1 + 5;
    const camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius, -vizRadius, 0.1, 100);
    camera.position.z = 100;

    function updateCamera() {
        const aspect = container.offsetWidth / container.offsetHeight;
        camera.top    =  vizRadius / aspect;
        camera.bottom = -vizRadius / aspect;
        camera.updateProjectionMatrix();
    }
    updateCamera();

    // Build static background points using pre-allocated typed arrays
    const totalPoints = maxGalaxy * maxSystem * maxPlanets;
    const positions = new Float32Array(totalPoints * 3);
    const colors    = new Float32Array(totalPoints * 3);
    const tmpColor  = new THREE.Color();
    let idx = 0;

    for (let g = 0; g < maxGalaxy; g++) {
        const offset = circleGroups[g];
        for (let i = 0; i < maxSystem; i++) {
            const radius = (i + 1) * 0.1;
            tmpColor.setHSL((g * maxSystem + i) / (maxSystem * maxGalaxy), 1, 0.5);
            const cr = tmpColor.r, cg = tmpColor.g, cb = tmpColor.b;
            for (let j = 0; j < maxPlanets; j++) {
                const angle = (j / maxPlanets) * 2 * Math.PI;
                positions[idx]     = offset.x + radius * Math.cos(angle);
                positions[idx + 1] = offset.y + radius * Math.sin(angle);
                positions[idx + 2] = 0;
                colors[idx]     = cr;
                colors[idx + 1] = cg;
                colors[idx + 2] = cb;
                idx += 3;
            }
        }
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setAttribute('color',    new THREE.Float32BufferAttribute(colors, 3));
    scene.add(new THREE.Points(geometry, new THREE.PointsMaterial({ size: 0.2 * window.devicePixelRatio, vertexColors: true })));

    // Galaxy label overlay
    const labelCanvas = document.createElement('canvas');
    labelCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;';
    container.appendChild(labelCanvas);
    const labelCtx = labelCanvas.getContext('2d');

    const tmpVec = new THREE.Vector3();

    function drawLabels() {
        const w = container.offsetWidth, h = container.offsetHeight;
        const dpr = window.devicePixelRatio;
        labelCanvas.width  = w * dpr;
        labelCanvas.height = h * dpr;
        labelCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
        labelCtx.font = 'bold 12px sans-serif';
        labelCtx.textAlign = 'center';
        labelCtx.textBaseline = 'middle';
        for (let g = 0; g < maxGalaxy; g++) {
            tmpVec.set(circleGroups[g].x, circleGroups[g].y, 0).project(camera);
            const sx = (tmpVec.x + 1) / 2 * w;
            const sy = (1 - tmpVec.y) / 2 * h;
            const label = 'G' + (g + 1);
            const tw = labelCtx.measureText(label).width;
            labelCtx.fillStyle = 'rgba(0,0,0,0.55)';
            labelCtx.fillRect(sx - tw / 2 - 3, sy - 8, tw + 6, 16);
            labelCtx.fillStyle = '#ffffff';
            labelCtx.fillText(label, sx, sy);
        }
    }

    drawLabels();

    // Fleet sprites
    const movingObjects = [];

    function createMovingObject(start, end, duration, color) {
        const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ color }));
        scene.add(sprite);
        movingObjects.push({ sprite, start, end, duration, startTime: Date.now() });
    }

    const TWO_PI = 2 * Math.PI;
    {$fleetsJson}.forEach(row => {
        const startGroup = circleGroups[parseInt(row.startGroup) - 1];
        const endGroup   = circleGroups[parseInt(row.endGroup)   - 1];
        if (!startGroup || !endGroup) return; // skip out-of-range galaxies

        const startCircle = parseInt(row.startCircle);
        const endCircle   = parseInt(row.endCircle);
        const startPoint  = parseInt(row.startPoint);
        const endPoint    = parseInt(row.endPoint);

        const startRadius = (startCircle + 1) * 0.1;
        const startAngle  = (startPoint / maxPlanets) * TWO_PI;
        const endRadius   = (endCircle   + 1) * 0.1;
        const endAngle    = (endPoint    / maxPlanets) * TWO_PI;

        createMovingObject(
            new THREE.Vector3(startGroup.x + startRadius * Math.cos(startAngle), startGroup.y + startRadius * Math.sin(startAngle), 0),
            new THREE.Vector3(endGroup.x   + endRadius   * Math.cos(endAngle),   endGroup.y   + endRadius   * Math.sin(endAngle),   0),
            parseFloat(row.duration) || 5,
            row.color ? parseInt(row.color.replace('#', ''), 16) : 0xff0000
        );
    });

    renderer.setClearColor(0x000000, 0.5);

    function animate() {
        requestAnimationFrame(animate);
        const now = Date.now();
        for (let i = 0; i < movingObjects.length; i++) {
            const obj = movingObjects[i];
            const t = ((now - obj.startTime) / 1000 % obj.duration) / obj.duration;
            obj.sprite.position.lerpVectors(obj.start, obj.end, t);
        }
        renderer.render(scene, camera);
    }

    animate();

    window.addEventListener('resize', () => {
        positionContainer();
        updateCamera();
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        drawLabels();
    });
</script>
{/block}
