/**
 * Fleet viz map — loads Three.js after first paint / idle.
 * Config: #viz-config JSON { threeSrc, maxGalaxy, maxSystem, maxPlanets, fleets }
 */
(function () {
	'use strict';

	function readConfig() {
		var tag = document.getElementById('viz-config');
		if (!tag) {
			return null;
		}
		try {
			return JSON.parse(tag.textContent);
		} catch (e) {
			return null;
		}
	}

	function schedule(fn) {
		if ('requestIdleCallback' in window) {
			requestIdleCallback(fn, { timeout: 500 });
		} else {
			setTimeout(fn, 0);
		}
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var el = document.createElement('script');
			el.src = src;
			el.async = true;
			el.onload = function () { resolve(); };
			el.onerror = function () { reject(new Error('Failed to load ' + src)); };
			document.head.appendChild(el);
		});
	}

	function bootViz(cfg) {
		var container = document.getElementById('threejs-container');
		if (!container || typeof THREE === 'undefined') {
			return;
		}

		var isMobile = window.matchMedia('(max-width: 699px)').matches;

		function positionContainer() {
			var menuFixed = document.querySelector('menu .fixed');
			if (window.innerWidth <= 699 || !menuFixed) {
				Object.assign(container.style, { top: '0', left: '0', width: '100vw', height: '100vh' });
			} else {
				var left = Math.round(menuFixed.getBoundingClientRect().right);
				var top = 100;
				Object.assign(container.style, {
					top: top + 'px',
					left: left + 'px',
					width: (window.innerWidth - left) + 'px',
					height: (window.innerHeight - top) + 'px',
				});
			}
		}
		positionContainer();

		var maxGalaxy = cfg.maxGalaxy;
		var maxSystem = cfg.maxSystem;
		var maxPlanets = cfg.maxPlanets;
		var systemStep = isMobile ? 3 : 1;
		var planetStep = isMobile ? 3 : 1;
		var pixelRatio = Math.min(window.devicePixelRatio || 1, isMobile ? 1 : 1.5);
		var arcSegments = isMobile ? 16 : 48;
		var glowSize = isMobile ? 32 : 64;

		var scene = new THREE.Scene();
		var renderer = new THREE.WebGLRenderer({
			antialias: !isMobile,
			alpha: true,
			powerPreference: isMobile ? 'low-power' : 'default',
		});

		renderer.setPixelRatio(pixelRatio);
		renderer.setSize(container.offsetWidth, container.offsetHeight);
		container.appendChild(renderer.domElement);

		var layoutRadius = Math.max(30, maxGalaxy * 8);
		var circleGroups = [];
		for (var i = 0; i < maxGalaxy; i++) {
			var angle = (i / maxGalaxy) * 2 * Math.PI - Math.PI / 2;
			circleGroups.push({
				x: layoutRadius * Math.cos(angle),
				y: layoutRadius * Math.sin(angle),
			});
		}

		var vizRadius = layoutRadius + maxSystem * 0.1 + 5;
		var camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius, -vizRadius, 0.1, 100);
		camera.position.z = 100;

		var zoomLevel = 1.0;
		var panX = 0;
		var panY = 0;

		function updateCamera() {
			var aspect = container.offsetWidth / container.offsetHeight;
			var r = vizRadius / zoomLevel;
			camera.left = -r;
			camera.right = r;
			camera.top = r / aspect;
			camera.bottom = -r / aspect;
			camera.position.x = panX;
			camera.position.y = panY;
			camera.updateProjectionMatrix();
		}
		updateCamera();

		var totalPoints = maxGalaxy * Math.ceil(maxSystem / systemStep) * Math.ceil(maxPlanets / planetStep);
		var positions = new Float32Array(totalPoints * 3);
		var colors = new Float32Array(totalPoints * 3);
		var tmpColor = new THREE.Color();
		var idx = 0;

		for (var g = 0; g < maxGalaxy; g++) {
			var offset = circleGroups[g];
			for (var si = 0; si < maxSystem; si += systemStep) {
				var radius = (si + 1) * 0.1;
				tmpColor.setHSL((g * maxSystem + si) / (maxSystem * maxGalaxy), 1, 0.5);
				var cr = tmpColor.r;
				var cg = tmpColor.g;
				var cb = tmpColor.b;
				var ringOffset = si * 2.399963;
				for (var j = 0; j < maxPlanets; j += planetStep) {
					var a = (j / maxPlanets) * 2 * Math.PI + ringOffset;
					positions[idx] = offset.x + radius * Math.cos(a);
					positions[idx + 1] = offset.y + radius * Math.sin(a);
					positions[idx + 2] = 0;
					colors[idx] = cr;
					colors[idx + 1] = cg;
					colors[idx + 2] = cb;
					idx += 3;
				}
			}
		}

		var geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
		scene.add(new THREE.Points(geometry, new THREE.PointsMaterial({ size: 0.2 * pixelRatio, vertexColors: true })));

		var labelCanvas = document.createElement('canvas');
		labelCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;';
		container.appendChild(labelCanvas);
		var labelCtx = labelCanvas.getContext('2d');
		var tmpVec = new THREE.Vector3();

		function drawLabels() {
			var w = container.offsetWidth;
			var h = container.offsetHeight;
			var dpr = pixelRatio;
			labelCanvas.width = w * dpr;
			labelCanvas.height = h * dpr;
			labelCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
			labelCtx.font = 'bold 12px sans-serif';
			labelCtx.textAlign = 'center';
			labelCtx.textBaseline = 'middle';
			for (var gi = 0; gi < maxGalaxy; gi++) {
				tmpVec.set(circleGroups[gi].x, circleGroups[gi].y, 0).project(camera);
				var sx = (tmpVec.x + 1) / 2 * w;
				var sy = (1 - tmpVec.y) / 2 * h;
				var label = 'G' + (gi + 1);
				var tw = labelCtx.measureText(label).width;
				labelCtx.fillStyle = 'rgba(0,0,0,0.55)';
				labelCtx.fillRect(sx - tw / 2 - 3, sy - 8, tw + 6, 16);
				labelCtx.fillStyle = '#ffffff';
				labelCtx.fillText(label, sx, sy);
			}
		}

		drawLabels();

		function makeGlowTexture(r, g, b) {
			var sz = glowSize;
			var cv = document.createElement('canvas');
			cv.width = cv.height = sz;
			var ctx = cv.getContext('2d');
			var half = sz / 2;
			function rgba(alpha) {
				return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
			}
			var grad = ctx.createRadialGradient(half, half, 0, half, half, half);
			grad.addColorStop(0, rgba(1));
			grad.addColorStop(0.25, rgba(0.85));
			grad.addColorStop(0.6, rgba(0.25));
			grad.addColorStop(1, rgba(0));
			ctx.fillStyle = grad;
			ctx.fillRect(0, 0, sz, sz);
			return new THREE.CanvasTexture(cv);
		}

		var COMBAT_MISSIONS = { 1: 1, 2: 1, 8: 1, 9: 1, 10: 1 };
		var CARGO_MISSIONS = { 3: 1, 4: 1, 6: 1 };
		function missionCategory(m) {
			m = parseInt(m, 10) || 0;
			if (COMBAT_MISSIONS[m]) return 'combat';
			if (CARGO_MISSIONS[m]) return 'cargo';
			if (m === 5) return 'spy';
			return 'other';
		}

		var fleetMat = {
			combat: new THREE.SpriteMaterial({ map: makeGlowTexture(255, 60, 50), transparent: true, depthWrite: false }),
			cargo: new THREE.SpriteMaterial({ map: makeGlowTexture(50, 210, 255), transparent: true, depthWrite: false }),
			spy: new THREE.SpriteMaterial({ map: makeGlowTexture(255, 235, 50), transparent: true, depthWrite: false }),
			other: new THREE.SpriteMaterial({ map: makeGlowTexture(180, 100, 255), transparent: true, depthWrite: false }),
		};

		var arcMat = {
			combat: new THREE.LineBasicMaterial({ color: 0xFF3C32, transparent: true, opacity: 0.6, depthWrite: false }),
			cargo: new THREE.LineBasicMaterial({ color: 0x32D2FF, transparent: true, opacity: 0.6, depthWrite: false }),
			spy: new THREE.LineBasicMaterial({ color: 0xFFEB32, transparent: true, opacity: 0.5, depthWrite: false }),
			other: new THREE.LineBasicMaterial({ color: 0xB464FF, transparent: true, opacity: 0.5, depthWrite: false }),
		};

		var SIZE_SCALE = [0, 1.0, 1.8, 3.0, 4.8, 7.2];
		var movingObjects = [];

		function createMovingObject(start, end, duration, mission, sizeClass) {
			var cat = missionCategory(mission);
			var mid = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
			var dir = new THREE.Vector3().subVectors(end, start);
			var perp = new THREE.Vector3(-dir.y, dir.x, 0).normalize();
			var ctrl = mid.clone().addScaledVector(perp, dir.length() * 0.45);
			var curve = new THREE.QuadraticBezierCurve3(start, ctrl, end);

			var arcGeo = new THREE.BufferGeometry().setFromPoints(curve.getPoints(arcSegments));
			scene.add(new THREE.Line(arcGeo, arcMat[cat]));

			var sprite = new THREE.Sprite(fleetMat[cat]);
			var s = SIZE_SCALE[Math.min(Math.max(parseInt(sizeClass, 10) || 1, 1), 5)];
			sprite.scale.set(s, s, 1);
			scene.add(sprite);
			movingObjects.push({ sprite: sprite, curve: curve, duration: duration, startTime: Date.now() });
		}

		var TWO_PI = 2 * Math.PI;
		var fleets = cfg.fleets || [];
		for (var fi = 0; fi < fleets.length; fi++) {
			var row = fleets[fi];
			var startGroup = circleGroups[parseInt(row.startGroup, 10) - 1];
			var endGroup = circleGroups[parseInt(row.endGroup, 10) - 1];
			if (!startGroup || !endGroup) continue;

			var startCircle = parseInt(row.startCircle, 10);
			var endCircle = parseInt(row.endCircle, 10);
			var startPoint = parseInt(row.startPoint, 10);
			var endPoint = parseInt(row.endPoint, 10);

			var startRadius = (startCircle + 1) * 0.1;
			var startAngle = (startPoint / maxPlanets) * TWO_PI;
			var endRadius = (endCircle + 1) * 0.1;
			var endAngle = (endPoint / maxPlanets) * TWO_PI;

			createMovingObject(
				new THREE.Vector3(startGroup.x + startRadius * Math.cos(startAngle), startGroup.y + startRadius * Math.sin(startAngle), 0),
				new THREE.Vector3(endGroup.x + endRadius * Math.cos(endAngle), endGroup.y + endRadius * Math.sin(endAngle), 0),
				parseFloat(row.duration) || 5,
				row.mission,
				row.sizeClass
			);
		}

		renderer.setClearColor(0x000000, 0.5);

		function animate() {
			requestAnimationFrame(animate);
			var now = Date.now();
			for (var mi = 0; mi < movingObjects.length; mi++) {
				var obj = movingObjects[mi];
				var t = ((now - obj.startTime) / 1000 % obj.duration) / obj.duration;
				obj.sprite.position.copy(obj.curve.getPoint(t));
			}
			renderer.render(scene, camera);
		}

		animate();

		container.style.cursor = 'grab';

		function worldPerPixel() {
			return (2 * vizRadius / zoomLevel) / container.offsetWidth;
		}

		container.addEventListener('wheel', function (e) {
			e.preventDefault();
			zoomLevel *= e.deltaY < 0 ? 1.15 : 1 / 1.15;
			zoomLevel = Math.max(0.3, Math.min(10, zoomLevel));
			updateCamera();
			drawLabels();
		}, { passive: false });

		var isDragging = false;
		var lastMouse = { x: 0, y: 0 };
		container.addEventListener('mousedown', function (e) {
			isDragging = true;
			lastMouse = { x: e.clientX, y: e.clientY };
			container.style.cursor = 'grabbing';
		});
		window.addEventListener('mousemove', function (e) {
			if (!isDragging) return;
			panX -= (e.clientX - lastMouse.x) * worldPerPixel();
			panY += (e.clientY - lastMouse.y) * worldPerPixel();
			lastMouse = { x: e.clientX, y: e.clientY };
			updateCamera();
			drawLabels();
		});
		window.addEventListener('mouseup', function () {
			isDragging = false;
			container.style.cursor = 'grab';
		});

		var lastPinchDist = null;
		var lastTouch = null;
		container.addEventListener('touchstart', function (e) {
			if (e.touches.length === 2) {
				lastPinchDist = Math.hypot(
					e.touches[0].clientX - e.touches[1].clientX,
					e.touches[0].clientY - e.touches[1].clientY
				);
				lastTouch = null;
			} else if (e.touches.length === 1) {
				lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
			}
		}, { passive: true });
		container.addEventListener('touchmove', function (e) {
			if (e.touches.length === 2) {
				var dist = Math.hypot(
					e.touches[0].clientX - e.touches[1].clientX,
					e.touches[0].clientY - e.touches[1].clientY
				);
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
		container.addEventListener('touchend', function () {
			lastPinchDist = null;
			lastTouch = null;
		});

		setInterval(function () {
			location.reload();
		}, 60000);

		window.addEventListener('resize', function () {
			positionContainer();
			updateCamera();
			renderer.setSize(container.offsetWidth, container.offsetHeight);
			drawLabels();
		});
	}

	function start() {
		var cfg = readConfig();
		var container = document.getElementById('threejs-container');
		if (!cfg || !container || !cfg.threeSrc) {
			return;
		}

		schedule(function () {
			loadScript(cfg.threeSrc)
				.then(function () {
					bootViz(cfg);
				})
				.catch(function () {
					container.textContent = '3D map unavailable';
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
