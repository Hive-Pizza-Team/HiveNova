{block name="content"}

<div id="threejs-container">
<style>
        body { margin: 0; }
        #threejs-container { width: 100%; height: 100vh; }
</style>

<script src="scripts/threejs/three.min.js"></script>

<script>
    const scene = new THREE.Scene();
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

    const width = document.getElementById('threejs-container').offsetWidth;
    const height = document.getElementById('threejs-container').offsetHeight;
    renderer.setSize(width, height);

    document.getElementById('threejs-container').appendChild(renderer.domElement);

    // Triangle group positions
    const circleGroups = [
        { x: 0, y: 20 },    // Top center
        { x: -45, y: -20 }, // Bottom left
        { x: 45, y: -20 }   // Bottom right
    ];

    const numCirclesPerGroup = 300;
    const pointsPerCircle = 15;

    // Adjusted camera to fit all groups
    const camera = new THREE.OrthographicCamera(-80, 80, 60, -60, 0.1, 100);
    camera.position.z = 100;

    // Create static points visualization
    const geometry = new THREE.BufferGeometry();
    const positions = [];
    const colors = [];

    circleGroups.forEach((offset, groupIndex) => {
        for (let i = 0; i < numCirclesPerGroup; i++) {
            const radius = (i + 1) * 0.1;
            const color = new THREE.Color().setHSL(
                (groupIndex * numCirclesPerGroup + i) / (numCirclesPerGroup * 3),
                1, 0.5
            );

            for (let j = 0; j < pointsPerCircle; j++) {
                const angle = (j / pointsPerCircle) * 2 * Math.PI;
                const x = offset.x + radius * Math.cos(angle);
                const y = offset.y + radius * Math.sin(angle);
                positions.push(x, y, 0);
                colors.push(color.r, color.g, color.b);
            }
        }
    });

    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));

    const pointMaterial = new THREE.PointsMaterial({
        size: 0.2,
        vertexColors: true
    });

    const points = new THREE.Points(geometry, pointMaterial);
    scene.add(points);

    // Store moving objects
    const movingObjects = [];

    // Create sprite for each fleet
    function createMovingObject(start, end, duration = 5, color = 0xff0000) {

        const spriteMaterial = new THREE.SpriteMaterial({ color });
        const sprite = new THREE.Sprite(spriteMaterial);
        sprite.scale.set(1.0, 1.0, 1);
        scene.add(sprite);

        movingObjects.push({
            sprite,
            start,
            end,
            duration,
            startTime: Date.now()
        });
    }

    const data = {$fleetsJson};

    // Clear existing objects
    movingObjects.forEach(obj => scene.remove(obj.sprite));
    movingObjects.length = 0;

    data.forEach(row => {
        row.startGroup  = parseInt(row.startGroup);
        row.startCircle = parseInt(row.startCircle);
        row.startPoint = parseInt(row.startPoint);
        row.endGroup  = parseInt(row.endGroup);
        row.endCircle = parseInt(row.endCircle);
        row.endPoint = parseInt(row.endPoint);
        row.duration = parseFloat(row.duration);

        const startGroup = circleGroups[row.startGroup - 1];
        const endGroup = circleGroups[row.endGroup - 1];

        const startRadius = (row.startCircle + 1) * 0.1;
        const startAngle = (row.startPoint / pointsPerCircle) * 2 * Math.PI;

        const endRadius = (row.endCircle + 1) * 0.1;
        const endAngle = (row.endPoint / pointsPerCircle) * 2 * Math.PI;

        const start = new THREE.Vector3(
            startGroup.x + startRadius * Math.cos(startAngle),
            startGroup.y + startRadius * Math.sin(startAngle),
            0
        );

        const end = new THREE.Vector3(
            endGroup.x + endRadius * Math.cos(endAngle),
            endGroup.y + endRadius * Math.sin(endAngle),
            0
        );

        createMovingObject(
            start,
            end,
            row.duration,
            row.color ? parseInt(row.color.replace('#', '0x'), 16) : 0xff0000
        );
    });

    // Set black background
    renderer.setClearColor(0x000000, 0.5);

    // Animation loop
    function animate() {
        requestAnimationFrame(animate);
        const now = Date.now();

        movingObjects.forEach(obj => {
            const elapsed = (now - obj.startTime) / 1000; // Convert to seconds
            const t = (elapsed % obj.duration) / obj.duration;
            obj.sprite.position.copy(obj.start.clone().lerp(obj.end, t));
        });

        renderer.render(scene, camera);
    }

    animate();

    // Responsive resizing
    window.addEventListener('resize', () => {
        camera.left = -80 * window.innerWidth/window.innerHeight;
        camera.right = 80 * window.innerWidth/window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
</script>
{/block}