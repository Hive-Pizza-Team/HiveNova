/**
 * Minimal multi-universe fleet viz for the public lobby hero.
 * Config: #lobby-viz-config JSON { threeSrc, universes: [{ id, name, maxGalaxy, maxSystem, maxPlanets, fleets }] }
 */
(function () {
	'use strict';

	function readConfig() {
		var tag = document.getElementById('lobby-viz-config');
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
			requestIdleCallback(fn, { timeout: 800 });
		} else {
			setTimeout(fn, 50);
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

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function bootLobbyViz(cfg) {
		var container = document.getElementById('lobby-viz');
		if (!container || typeof THREE === 'undefined') {
			return;
		}

		var universes = cfg.universes || [];
		if (!universes.length) {
			container.classList.add('is-empty');
			return;
		}

		var reduceMotion = prefersReducedMotion();
		var isNarrow = window.matchMedia('(max-width: 699px)').matches;
		var pixelRatio = Math.min(window.devicePixelRatio || 1, isNarrow ? 1 : 1.25);
		var systemStep = isNarrow ? 5 : 3;
		var planetStep = isNarrow ? 5 : 3;
		var arcSegments = isNarrow ? 12 : 24;
		var glowSize = isNarrow ? 24 : 40;

		var scene = new THREE.Scene();
		var renderer = new THREE.WebGLRenderer({
			antialias: !isNarrow,
			alpha: true,
			powerPreference: 'low-power',
		});
		renderer.setPixelRatio(pixelRatio);
		renderer.setClearColor(0x000000, 0);
		container.appendChild(renderer.domElement);

		function clusterRadius(uni) {
			var g = Math.max(1, uni.maxGalaxy || 1);
			var s = Math.max(1, uni.maxSystem || 1);
			return Math.max(18, g * 5.5) + s * 0.08 + 4;
		}

		var n = universes.length;
		var gap = 10;
		var radii = universes.map(clusterRadius);
		var totalWidth = radii.reduce(function (sum, r) { return sum + r * 2; }, 0) + gap * Math.max(0, n - 1);
		var cursor = -totalWidth / 2;
		var origins = [];
		for (var ui = 0; ui < n; ui++) {
			var r = radii[ui];
			origins.push({ x: cursor + r, y: 0, r: r });
			cursor += r * 2 + gap;
		}

		var vizRadius = Math.max(totalWidth / 2 + 8, 40);
		var camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius, -vizRadius, 0.1, 100);
		camera.position.z = 100;

		function updateCamera() {
			var w = Math.max(1, container.clientWidth);
			var h = Math.max(1, container.clientHeight);
			var aspect = w / h;
			var pad = 1.08;
			camera.left = -vizRadius * pad;
			camera.right = vizRadius * pad;
			camera.top = (vizRadius * pad) / aspect;
			camera.bottom = -(vizRadius * pad) / aspect;
			camera.updateProjectionMatrix();
			renderer.setSize(w, h, false);
		}
		updateCamera();

		var tmpColor = new THREE.Color();
		var TWO_PI = 2 * Math.PI;

		function galaxyCenters(maxGalaxy, layoutRadius) {
			var groups = [];
			for (var i = 0; i < maxGalaxy; i++) {
				var angle = (i / maxGalaxy) * TWO_PI - Math.PI / 2;
				groups.push({
					x: layoutRadius * Math.cos(angle),
					y: layoutRadius * Math.sin(angle),
				});
			}
			return groups;
		}

		function addStarfield(origin, uni) {
			var maxGalaxy = uni.maxGalaxy;
			var maxSystem = uni.maxSystem;
			var maxPlanets = uni.maxPlanets;
			var layoutRadius = Math.max(18, maxGalaxy * 5.5);
			var groups = galaxyCenters(maxGalaxy, layoutRadius);
			var total = maxGalaxy * Math.ceil(maxSystem / systemStep) * Math.ceil(maxPlanets / planetStep);
			var positions = new Float32Array(total * 3);
			var colors = new Float32Array(total * 3);
			var idx = 0;
			for (var g = 0; g < maxGalaxy; g++) {
				var offset = groups[g];
				for (var si = 0; si < maxSystem; si += systemStep) {
					var radius = (si + 1) * 0.08;
					tmpColor.setHSL((0.55 + g * 0.07 + (uni.id || 0) * 0.11) % 1, 0.55, 0.45);
					var cr = tmpColor.r;
					var cg = tmpColor.g;
					var cb = tmpColor.b;
					var ringOffset = si * 2.399963;
					for (var j = 0; j < maxPlanets; j += planetStep) {
						var a = (j / maxPlanets) * TWO_PI + ringOffset;
						positions[idx] = origin.x + offset.x + radius * Math.cos(a);
						positions[idx + 1] = origin.y + offset.y + radius * Math.sin(a);
						positions[idx + 2] = 0;
						colors[idx] = cr;
						colors[idx + 1] = cg;
						colors[idx + 2] = cb;
						idx += 3;
					}
				}
			}
			var geometry = new THREE.BufferGeometry();
			geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions.subarray(0, idx), 3));
			geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors.subarray(0, idx), 3));
			scene.add(new THREE.Points(geometry, new THREE.PointsMaterial({
				size: 0.22 * pixelRatio,
				vertexColors: true,
				transparent: true,
				opacity: 0.85,
				depthWrite: false,
			})));
			return { groups: groups, layoutRadius: layoutRadius, maxPlanets: maxPlanets };
		}

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
			grad.addColorStop(0.3, rgba(0.7));
			grad.addColorStop(1, rgba(0));
			ctx.fillStyle = grad;
			ctx.fillRect(0, 0, sz, sz);
			return new THREE.CanvasTexture(cv);
		}

		var COMBAT = { 1: 1, 2: 1, 8: 1, 9: 1, 10: 1 };
		var CARGO = { 3: 1, 4: 1, 6: 1 };
		function missionCategory(m) {
			m = parseInt(m, 10) || 0;
			if (COMBAT[m]) return 'combat';
			if (CARGO[m]) return 'cargo';
			if (m === 5) return 'spy';
			return 'other';
		}

		var fleetMat = {
			combat: new THREE.SpriteMaterial({ map: makeGlowTexture(255, 70, 80), transparent: true, depthWrite: false }),
			cargo: new THREE.SpriteMaterial({ map: makeGlowTexture(70, 200, 255), transparent: true, depthWrite: false }),
			spy: new THREE.SpriteMaterial({ map: makeGlowTexture(255, 220, 80), transparent: true, depthWrite: false }),
			other: new THREE.SpriteMaterial({ map: makeGlowTexture(180, 120, 255), transparent: true, depthWrite: false }),
		};
		var arcMat = {
			combat: new THREE.LineBasicMaterial({ color: 0xff4650, transparent: true, opacity: 0.45, depthWrite: false }),
			cargo: new THREE.LineBasicMaterial({ color: 0x46c8ff, transparent: true, opacity: 0.45, depthWrite: false }),
			spy: new THREE.LineBasicMaterial({ color: 0xffdc50, transparent: true, opacity: 0.4, depthWrite: false }),
			other: new THREE.LineBasicMaterial({ color: 0xb478ff, transparent: true, opacity: 0.4, depthWrite: false }),
		};
		var SIZE_SCALE = [0, 0.9, 1.5, 2.4, 3.6, 5.2];
		var movingObjects = [];

		function addFleets(origin, layout, uni) {
			var fleets = uni.fleets || [];
			var maxPlanets = layout.maxPlanets || 15;
			for (var fi = 0; fi < fleets.length; fi++) {
				var row = fleets[fi];
				var startGroup = layout.groups[parseInt(row.startGroup, 10) - 1];
				var endGroup = layout.groups[parseInt(row.endGroup, 10) - 1];
				if (!startGroup || !endGroup) {
					continue;
				}
				var startRadius = (parseInt(row.startCircle, 10) + 1) * 0.08;
				var endRadius = (parseInt(row.endCircle, 10) + 1) * 0.08;
				var startAngle = (parseInt(row.startPoint, 10) / maxPlanets) * TWO_PI;
				var endAngle = (parseInt(row.endPoint, 10) / maxPlanets) * TWO_PI;
				var start = new THREE.Vector3(
					origin.x + startGroup.x + startRadius * Math.cos(startAngle),
					origin.y + startGroup.y + startRadius * Math.sin(startAngle),
					0
				);
				var end = new THREE.Vector3(
					origin.x + endGroup.x + endRadius * Math.cos(endAngle),
					origin.y + endGroup.y + endRadius * Math.sin(endAngle),
					0
				);
				var cat = missionCategory(row.mission);
				var mid = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
				var dir = new THREE.Vector3().subVectors(end, start);
				var perp = new THREE.Vector3(-dir.y, dir.x, 0).normalize();
				var ctrl = mid.clone().addScaledVector(perp, dir.length() * 0.4);
				var curve = new THREE.QuadraticBezierCurve3(start, ctrl, end);
				scene.add(new THREE.Line(
					new THREE.BufferGeometry().setFromPoints(curve.getPoints(arcSegments)),
					arcMat[cat]
				));
				var sprite = new THREE.Sprite(fleetMat[cat]);
				var s = SIZE_SCALE[Math.min(Math.max(parseInt(row.sizeClass, 10) || 1, 1), 5)];
				sprite.scale.set(s, s, 1);
				sprite.position.copy(curve.getPoint(0.35));
				scene.add(sprite);
				movingObjects.push({
					sprite: sprite,
					curve: curve,
					duration: Math.max(4, parseFloat(row.duration) || 8),
					startTime: Date.now() - fi * 400,
				});
			}
		}

		var labelCanvas = document.createElement('canvas');
		labelCanvas.className = 'lobby-viz-labels';
		container.appendChild(labelCanvas);
		var labelCtx = labelCanvas.getContext('2d');
		var tmpVec = new THREE.Vector3();

		function drawLabels() {
			var w = container.clientWidth;
			var h = container.clientHeight;
			var dpr = pixelRatio;
			labelCanvas.width = Math.max(1, Math.floor(w * dpr));
			labelCanvas.height = Math.max(1, Math.floor(h * dpr));
			labelCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
			labelCtx.clearRect(0, 0, w, h);
			labelCtx.font = '600 12px "Trebuchet MS", "Segoe UI", sans-serif';
			labelCtx.textAlign = 'center';
			labelCtx.textBaseline = 'middle';
			for (var i = 0; i < universes.length; i++) {
				tmpVec.set(origins[i].x, origins[i].y - origins[i].r * 0.92, 0).project(camera);
				var sx = (tmpVec.x + 1) / 2 * w;
				var sy = (1 - tmpVec.y) / 2 * h;
				var label = String(universes[i].name || ('Uni ' + universes[i].id));
				var tw = labelCtx.measureText(label).width;
				labelCtx.fillStyle = 'rgba(5, 14, 24, 0.7)';
				labelCtx.fillRect(sx - tw / 2 - 6, sy - 9, tw + 12, 18);
				labelCtx.strokeStyle = 'rgba(227, 19, 55, 0.45)';
				labelCtx.strokeRect(sx - tw / 2 - 6, sy - 9, tw + 12, 18);
				labelCtx.fillStyle = '#f2f7fc';
				labelCtx.fillText(label, sx, sy);
			}
		}

		for (var i = 0; i < universes.length; i++) {
			var layout = addStarfield(origins[i], universes[i]);
			addFleets(origins[i], layout, universes[i]);
		}

		drawLabels();
		renderer.render(scene, camera);

		var raf = 0;
		function animate() {
			raf = requestAnimationFrame(animate);
			if (!reduceMotion) {
				var now = Date.now();
				for (var mi = 0; mi < movingObjects.length; mi++) {
					var obj = movingObjects[mi];
					var t = ((now - obj.startTime) / 1000 % obj.duration) / obj.duration;
					obj.sprite.position.copy(obj.curve.getPoint(t));
				}
			}
			renderer.render(scene, camera);
		}
		animate();

		var resizeTimer = 0;
		function onResize() {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				updateCamera();
				drawLabels();
				renderer.render(scene, camera);
			}, 80);
		}
		window.addEventListener('resize', onResize);

		container.classList.add('is-ready');
	}

	function start() {
		var cfg = readConfig();
		var container = document.getElementById('lobby-viz');
		if (!cfg || !container || !cfg.threeSrc) {
			return;
		}
		if (prefersReducedMotion() && !(cfg.universes && cfg.universes.length)) {
			return;
		}

		schedule(function () {
			loadScript(cfg.threeSrc)
				.then(function () {
					bootLobbyViz(cfg);
				})
				.catch(function () {
					container.classList.add('is-fallback');
				});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
