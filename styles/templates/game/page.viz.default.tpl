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

    let zoomLevel = 1.0;
    let panX = 0, panY = 0;

    function updateCamera() {
        const aspect = container.offsetWidth / container.offsetHeight;
        const r = vizRadius / zoomLevel;
        camera.left   = -r;
        camera.right  =  r;
        camera.top    =  r / aspect;
        camera.bottom = -r / aspect;
        camera.position.x = panX;
        camera.position.y = panY;
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
            const ringOffset = i * 2.399963; // golden angle in radians — desynchs rings
            for (let j = 0; j < maxPlanets; j++) {
                const angle = (j / maxPlanets) * 2 * Math.PI + ringOffset;
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
    // Glow texture: radial gradient on a small canvas
    function makeGlowTexture(r, g, b) {
        const sz = 64, cv = document.createElement('canvas');
        cv.width = cv.height = sz;
        const ctx = cv.getContext('2d'), half = sz / 2;
        const rgba = (a) => 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
        const grad = ctx.createRadialGradient(half, half, 0, half, half, half);
        grad.addColorStop(0,    rgba(1));
        grad.addColorStop(0.25, rgba(0.85));
        grad.addColorStop(0.6,  rgba(0.25));
        grad.addColorStop(1,    rgba(0));
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, sz, sz);
        return new THREE.CanvasTexture(cv);
    }

    // Mission categories → colour
    // combat:  attack (1), ACS attack (2), moon destroy (8), ACS defend (9), missile (10)
    // cargo:   transport (3), deploy (4), colony (6)
    // spy:     spy (5)
    // other:   recycle (7), expedition (15), hold (16), …
    const COMBAT_MISSIONS = new Set([1, 2, 8, 9, 10]);
    const CARGO_MISSIONS  = new Set([3, 4, 6]);
    function missionCategory(m) {
        m = parseInt(m) || 0;
        if (COMBAT_MISSIONS.has(m)) return 'combat';
        if (CARGO_MISSIONS.has(m))  return 'cargo';
        if (m === 5)                return 'spy';
        return 'other';
    }

    const fleetMat = {
        combat: new THREE.SpriteMaterial({ map: makeGlowTexture(255, 60,  50),  transparent: true, depthWrite: false }),
        cargo:  new THREE.SpriteMaterial({ map: makeGlowTexture(50,  210, 255), transparent: true, depthWrite: false }),
        spy:    new THREE.SpriteMaterial({ map: makeGlowTexture(255, 235, 50),  transparent: true, depthWrite: false }),
        other:  new THREE.SpriteMaterial({ map: makeGlowTexture(180, 100, 255), transparent: true, depthWrite: false }),
    };

    const arcMat = {
        combat: new THREE.LineBasicMaterial({ color: 0xFF3C32, transparent: true, opacity: 0.6, depthWrite: false }),
        cargo:  new THREE.LineBasicMaterial({ color: 0x32D2FF, transparent: true, opacity: 0.6, depthWrite: false }),
        spy:    new THREE.LineBasicMaterial({ color: 0xFFEB32, transparent: true, opacity: 0.5, depthWrite: false }),
        other:  new THREE.LineBasicMaterial({ color: 0xB464FF, transparent: true, opacity: 0.5, depthWrite: false }),
    };

    // Log₁₀ size classes 1–5: 1–9, 10–99, 100–999, 1000–9999, 10 000+
    const SIZE_SCALE = [0, 1.0, 1.8, 3.0, 4.8, 7.2];

    const movingObjects = [];

    function createMovingObject(start, end, duration, mission, sizeClass) {
        const cat = missionCategory(mission);

        // Quadratic Bézier arc — control point offset perpendicular to the route
        const mid  = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
        const dir  = new THREE.Vector3().subVectors(end, start);
        const perp = new THREE.Vector3(-dir.y, dir.x, 0).normalize();
        const ctrl = mid.clone().addScaledVector(perp, dir.length() * 0.45);
        const curve = new THREE.QuadraticBezierCurve3(start, ctrl, end);

        const arcGeo = new THREE.BufferGeometry().setFromPoints(curve.getPoints(48));
        scene.add(new THREE.Line(arcGeo, arcMat[cat]));

        const sprite = new THREE.Sprite(fleetMat[cat]);
        const s      = SIZE_SCALE[Math.min(Math.max(parseInt(sizeClass) || 1, 1), 5)];
        sprite.scale.set(s, s, 1);
        scene.add(sprite);
        movingObjects.push({ sprite, curve, duration, startTime: Date.now() });
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
            row.mission,
            row.sizeClass
        );
    });

    renderer.setClearColor(0x000000, 0.5);

    function animate() {
        requestAnimationFrame(animate);
        const now = Date.now();
        for (let i = 0; i < movingObjects.length; i++) {
            const obj = movingObjects[i];
            const t = ((now - obj.startTime) / 1000 % obj.duration) / obj.duration;
            obj.sprite.position.copy(obj.curve.getPoint(t));
        }
        renderer.render(scene, camera);
    }

    animate();

    container.style.cursor = 'grab';

    function worldPerPixel() {
        return (2 * vizRadius / zoomLevel) / container.offsetWidth;
    }

    // Zoom — mouse wheel
    container.addEventListener('wheel', (e) => {
        e.preventDefault();
        zoomLevel *= e.deltaY < 0 ? 1.15 : 1 / 1.15;
        zoomLevel = Math.max(0.3, Math.min(10, zoomLevel));
        updateCamera();
        drawLabels();
    }, { passive: false });

    // Pan — mouse drag
    let isDragging = false, lastMouse = { x: 0, y: 0 };
    container.addEventListener('mousedown', (e) => {
        isDragging = true;
        lastMouse = { x: e.clientX, y: e.clientY };
        container.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        panX -= (e.clientX - lastMouse.x) * worldPerPixel();
        panY += (e.clientY - lastMouse.y) * worldPerPixel();
        lastMouse = { x: e.clientX, y: e.clientY };
        updateCamera();
        drawLabels();
    });
    window.addEventListener('mouseup', () => {
        isDragging = false;
        container.style.cursor = 'grab';
    });

    // Zoom + pan — touch
    let lastPinchDist = null, lastTouch = null;
    container.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            lastPinchDist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY);
            lastTouch = null;
        } else if (e.touches.length === 1) {
            lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
        }
    }, { passive: true });
    container.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2) {
            const dist = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY);
            if (lastPinchDist) {
                zoomLevel *= dist / lastPinchDist;
                zoomLevel = Math.max(0.3, Math.min(10, zoomLevel));
            }
            lastPinchDist = dist;
            updateCamera();
            drawLabels();
        } else if (e.touches.length === 1 && lastTouch) {
            panX -= (e.touches[0].clientX - lastTouch.x) * worldPerPixel();
            panY += (e.touches[0].clientY - lastTouch.y) * worldPerPixel();
            lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            updateCamera();
            drawLabels();
        }
    }, { passive: true });
    container.addEventListener('touchend', () => { lastPinchDist = null; lastTouch = null; });

    setInterval(() => location.reload(), 60000);

    window.addEventListener('resize', () => {
        positionContainer();
        updateCamera();
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        drawLabels();
    });
</script>
{/block}
