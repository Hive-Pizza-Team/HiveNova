{block name="content"}

<div id="threejs-container">
<style>
        body { margin: 0; }
        #threejs-container { width: 100%; height: 100vh; height: 100dvh; touch-action: none; }
</style>

<script src="scripts/threejs/three.min.js"></script>

<script>
    const scene = new THREE.Scene();
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

    const container = document.getElementById('threejs-container');
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
    const camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius * 0.75, -vizRadius * 0.75, 0.1, 100);
    camera.position.z = 100;

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
        camera.updateProjectionMatrix();
        renderer.setSize(container.offsetWidth, container.offsetHeight);
    });
</script>
{/block}
