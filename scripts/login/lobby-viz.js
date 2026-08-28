/**
 * Galaxy→galaxy lobby map — dark field, glowing arcs between galaxies.
 * Config: #lobby-viz-config { threeSrc, universes: [{ id, name, maxGalaxy, maxSystem, maxPlanets, fleets }] }
 *
 * Galaxies are nodes. Fleets are glowing arcs between galaxies.
 * Open universes sit as labeled clusters on one dark field.
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

	function makeRadialTexture(size, stops) {
		var cv = document.createElement('canvas');
		cv.width = cv.height = size;
		var ctx = cv.getContext('2d');
		var half = size / 2;
		var grad = ctx.createRadialGradient(half, half, 0, half, half, half);
		for (var i = 0; i < stops.length; i++) {
			grad.addColorStop(stops[i][0], stops[i][1]);
		}
		ctx.fillStyle = grad;
		ctx.fillRect(0, 0, size, size);
		return new THREE.CanvasTexture(cv);
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
		var pixelRatio = Math.min(window.devicePixelRatio || 1, isNarrow ? 1 : 1.5);
		var arcSegments = isNarrow ? 28 : 48;

		var scene = new THREE.Scene();
		var renderer = new THREE.WebGLRenderer({
			antialias: !isNarrow,
			alpha: true,
			powerPreference: 'low-power',
		});
		renderer.setPixelRatio(pixelRatio);
		renderer.setClearColor(0x000000, 0);
		container.appendChild(renderer.domElement);

		/* —— layout: each universe = a labeled “continent” of galaxy nodes —— */
		var n = universes.length;
		var clusterR = isNarrow ? 20 : 30;
		var clusterGap = clusterR * 2.35;
		var origins = [];
		if (n === 1) {
			origins.push({ x: 0, y: isNarrow ? 1.5 : 2 });
		} else {
			var orbit = Math.max(clusterGap * 0.55, (n * clusterGap) / (2 * Math.PI));
			for (var ui = 0; ui < n; ui++) {
				var a = (ui / n) * Math.PI * 2 - Math.PI / 2;
				origins.push({
					x: Math.cos(a) * orbit,
					y: Math.sin(a) * orbit * 0.72,
				});
			}
		}

		var vizRadius = 0;
		for (var oi = 0; oi < origins.length; oi++) {
			var d = Math.hypot(origins[oi].x, origins[oi].y) + clusterR + 8;
			if (d > vizRadius) {
				vizRadius = d;
			}
		}
		/* pad for universe labels under each cluster */
		vizRadius = Math.max(vizRadius, clusterR + 16) + (isNarrow ? 6 : 4);

		var camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius, -vizRadius, 0.1, 100);
		camera.position.z = 50;

		function updateCamera() {
			var w = Math.max(1, container.clientWidth);
			var h = Math.max(1, container.clientHeight);
			var aspect = w / h;
			var view = vizRadius * (isNarrow ? 1.18 : 1.1);
			/* Fit the map to the shorter axis so wide/short mobile boxes don't clip top/bottom. */
			if (aspect >= 1) {
				camera.top = view;
				camera.bottom = -view;
				camera.left = -view * aspect;
				camera.right = view * aspect;
			} else {
				camera.left = -view;
				camera.right = view;
				camera.top = view / aspect;
				camera.bottom = -view / aspect;
			}
			camera.updateProjectionMatrix();
			renderer.setSize(w, h, false);
		}
		updateCamera();

		/* —— sparse background stars —— */
		(function addBackdropStars() {
			var count = isNarrow ? 120 : 220;
			var pos = new Float32Array(count * 3);
			for (var i = 0; i < count; i++) {
				pos[i * 3] = (Math.random() - 0.5) * vizRadius * 2.6;
				pos[i * 3 + 1] = (Math.random() - 0.5) * vizRadius * 2.6;
				pos[i * 3 + 2] = -2;
			}
			var geo = new THREE.BufferGeometry();
			geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
			scene.add(new THREE.Points(geo, new THREE.PointsMaterial({
				color: 0x6a8aaa,
				size: 0.35 * pixelRatio,
				transparent: true,
				opacity: 0.55,
				depthWrite: false,
			})));
		})();

		function makeMarkerTexture(hot) {
			var size = 128;
			var cv = document.createElement('canvas');
			cv.width = cv.height = size;
			var ctx = cv.getContext('2d');
			var c = size / 2;
			var ink = hot ? 'rgba(255,150,70,' : 'rgba(90,220,255,';
			var inkCore = hot ? 'rgba(255,230,180,' : 'rgba(230,250,255,';

			/* soft bloom (kept subtle) */
			var bloom = ctx.createRadialGradient(c, c, 2, c, c, c * 0.92);
			bloom.addColorStop(0, ink + '0.35)');
			bloom.addColorStop(0.45, ink + '0.12)');
			bloom.addColorStop(1, ink + '0)');
			ctx.fillStyle = bloom;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.92, 0, Math.PI * 2);
			ctx.fill();

			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';

			/* outer ring */
			ctx.strokeStyle = ink + '0.95)';
			ctx.lineWidth = hot ? 3.2 : 2.4;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.62, 0, Math.PI * 2);
			ctx.stroke();

			/* inner ring */
			ctx.strokeStyle = inkCore + '0.85)';
			ctx.lineWidth = 1.5;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.34, 0, Math.PI * 2);
			ctx.stroke();

			/* reticle ticks */
			ctx.strokeStyle = ink + '0.9)';
			ctx.lineWidth = 1.8;
			var tickOut = c * 0.78;
			var tickIn = c * 0.52;
			[[0, -1], [0, 1], [-1, 0], [1, 0]].forEach(function (d) {
				ctx.beginPath();
				ctx.moveTo(c + d[0] * tickIn, c + d[1] * tickIn);
				ctx.lineTo(c + d[0] * tickOut, c + d[1] * tickOut);
				ctx.stroke();
			});

			/* diamond core */
			ctx.fillStyle = inkCore + '1)';
			ctx.beginPath();
			var d = hot ? 7 : 5.5;
			ctx.moveTo(c, c - d);
			ctx.lineTo(c + d, c);
			ctx.lineTo(c, c + d);
			ctx.lineTo(c - d, c);
			ctx.closePath();
			ctx.fill();
			ctx.strokeStyle = ink + '0.7)';
			ctx.lineWidth = 1;
			ctx.stroke();

			return new THREE.CanvasTexture(cv);
		}

		var nodeTex = makeMarkerTexture(false);
		var nodeHotTex = makeMarkerTexture(true);
		var pulseTex = makeRadialTexture(128, [
			[0, 'rgba(255,255,255,0)'],
			[0.55, 'rgba(80,220,255,0)'],
			[0.72, 'rgba(80,220,255,0.95)'],
			[0.9, 'rgba(80,220,255,0.2)'],
			[1, 'rgba(0,0,0,0)'],
		]);
		var boltTex = makeRadialTexture(48, [
			[0, 'rgba(255,255,255,1)'],
			[0.25, 'rgba(200,240,255,0.95)'],
			[0.65, 'rgba(100,200,255,0.3)'],
			[1, 'rgba(0,0,0,0)'],
		]);

		var ARC_COLORS = {
			combat: 0xff7a45,
			cargo: 0x5ad8ff,
			spy: 0xffe066,
			other: 0xd09cff,
		};
		var COMBAT = { 1: 1, 2: 1, 8: 1, 9: 1, 10: 1 };
		var CARGO = { 3: 1, 4: 1, 6: 1 };
		function missionCategory(m) {
			m = parseInt(m, 10) || 0;
			if (COMBAT[m]) return 'combat';
			if (CARGO[m]) return 'cargo';
			if (m === 5) return 'spy';
			return 'other';
		}

		var movingBolts = [];
		var pulses = [];
		var galaxyNodes = [];
		var markerIdle = [];

		function galaxyPosition(origin, galaxyIndex, maxGalaxy) {
			var g = Math.max(1, maxGalaxy);
			var angle = (galaxyIndex / g) * Math.PI * 2 - Math.PI / 2;
			var radius = clusterR * (0.42 + 0.08 * Math.min(g, 9));
			// slight irregularity so clusters feel organic (continent-like)
			var wobble = 0.88 + ((galaxyIndex * 17) % 7) * 0.03;
			return {
				x: origin.x + Math.cos(angle) * radius * wobble,
				y: origin.y + Math.sin(angle) * radius * wobble * 0.92,
			};
		}

		function addUniverseCluster(origin, uni, uniIndex) {
			var maxGalaxy = Math.max(1, parseInt(uni.maxGalaxy, 10) || 1);
			var nodes = [];
			var heat = {};

			(uni.fleets || []).forEach(function (f) {
				var sg = parseInt(f.startGroup, 10) || 1;
				var eg = parseInt(f.endGroup, 10) || 1;
				heat[sg] = (heat[sg] || 0) + 1;
				heat[eg] = (heat[eg] || 0) + 1;
			});

			/* constellation outline — thin cyan links between neighboring galaxies */
			var outlinePts = [];
			for (var g = 0; g < maxGalaxy; g++) {
				var p = galaxyPosition(origin, g, maxGalaxy);
				nodes.push(p);
				outlinePts.push(new THREE.Vector3(p.x, p.y, -0.5));
			}
			if (outlinePts.length > 2) {
				/* filled continent-like body */
				var shape = new THREE.Shape();
				shape.moveTo(outlinePts[0].x - origin.x, outlinePts[0].y - origin.y);
				for (var si = 1; si < outlinePts.length; si++) {
					shape.lineTo(outlinePts[si].x - origin.x, outlinePts[si].y - origin.y);
				}
				shape.closePath();
				var fill = new THREE.Mesh(
					new THREE.ShapeGeometry(shape),
					new THREE.MeshBasicMaterial({
						color: 0x0a2a40,
						transparent: true,
						opacity: 0.55,
						depthWrite: false,
					})
				);
				fill.position.set(origin.x, origin.y, -1.2);
				scene.add(fill);

				var closed = outlinePts.slice();
				closed.push(outlinePts[0].clone());
				var outlineGeo = new THREE.BufferGeometry().setFromPoints(closed);
				scene.add(new THREE.Line(outlineGeo, new THREE.LineBasicMaterial({
					color: 0x3ec8ff,
					transparent: true,
					opacity: 0.55,
					depthWrite: false,
				})));
			}

			/* soft region wash */
			var wash = new THREE.Sprite(new THREE.SpriteMaterial({
				map: makeRadialTexture(128, [
					[0, 'rgba(30,90,140,0.22)'],
					[0.5, 'rgba(20,50,90,0.08)'],
					[1, 'rgba(0,0,0,0)'],
				]),
				transparent: true,
				depthWrite: false,
			}));
			wash.position.set(origin.x, origin.y, -1);
			wash.scale.set(clusterR * 2.4, clusterR * 2.4, 1);
			scene.add(wash);

			/* galaxy markers — reticle nodes, not soft orbs */
			for (var gi = 0; gi < maxGalaxy; gi++) {
				var pos = nodes[gi];
				var activity = heat[gi + 1] || 0;
				var hot = activity >= 2;
				var sprite = new THREE.Sprite(new THREE.SpriteMaterial({
					map: hot ? nodeHotTex : nodeTex,
					transparent: true,
					depthWrite: false,
					opacity: 1,
				}));
				var scale = 4.4 + Math.min(3.6, activity * 0.85);
				sprite.position.set(pos.x, pos.y, 0.1);
				sprite.scale.set(scale, scale, 1);
				scene.add(sprite);
				markerIdle.push({ sprite: sprite, base: scale, phase: gi * 0.73 + uniIndex });
				galaxyNodes.push({
					x: pos.x,
					y: pos.y,
					label: 'G' + (gi + 1),
					uniName: String(uni.name || ('Uni ' + uni.id)),
					uniIndex: uniIndex,
					galaxy: gi + 1,
				});
			}

			/* fleet arcs galaxy → galaxy */
			var fleets = uni.fleets || [];
			var maxArcs = isNarrow ? 28 : 50;
			for (var fi = 0; fi < fleets.length && fi < maxArcs; fi++) {
				var row = fleets[fi];
				var startIdx = (parseInt(row.startGroup, 10) || 1) - 1;
				var endIdx = (parseInt(row.endGroup, 10) || 1) - 1;
				if (startIdx < 0 || endIdx < 0 || startIdx >= nodes.length || endIdx >= nodes.length) {
					continue;
				}
				if (startIdx === endIdx) {
					continue; // same galaxy — skip for clearer galaxy↔galaxy read
				}

				var start = new THREE.Vector3(nodes[startIdx].x, nodes[startIdx].y, 0);
				var end = new THREE.Vector3(nodes[endIdx].x, nodes[endIdx].y, 0);
				var cat = missionCategory(row.mission);
				var mid = new THREE.Vector3().addVectors(start, end).multiplyScalar(0.5);
				var dir = new THREE.Vector3().subVectors(end, start);
				var perp = new THREE.Vector3(-dir.y, dir.x, 0);
				if (perp.lengthSq() > 0.0001) {
					perp.normalize();
				}
				var lift = Math.min(18, Math.max(4, dir.length() * 0.35));
				var ctrl = mid.clone().addScaledVector(perp, lift * (fi % 2 === 0 ? 1 : -1));
				var curve = new THREE.QuadraticBezierCurve3(start, ctrl, end);

				var color = ARC_COLORS[cat];
				scene.add(new THREE.Line(
					new THREE.BufferGeometry().setFromPoints(curve.getPoints(arcSegments)),
					new THREE.LineBasicMaterial({
						color: color,
						transparent: true,
						opacity: 0.55,
						depthWrite: false,
					})
				));
				/* brighter core streak */
				scene.add(new THREE.Line(
					new THREE.BufferGeometry().setFromPoints(curve.getPoints(Math.max(8, arcSegments / 2))),
					new THREE.LineBasicMaterial({
						color: 0xffffff,
						transparent: true,
						opacity: 0.22,
						depthWrite: false,
					})
				));

				var bolt = new THREE.Sprite(new THREE.SpriteMaterial({
					map: boltTex,
					transparent: true,
					depthWrite: false,
					opacity: 0.95,
				}));
				bolt.scale.set(2.4, 2.4, 1);
				scene.add(bolt);

				var duration = Math.max(3.5, Math.min(18, parseFloat(row.duration) || 8));
				movingBolts.push({
					sprite: bolt,
					curve: curve,
					duration: duration,
					startTime: Date.now() - fi * 180,
					end: end,
					color: color,
				});
			}

			return { nodes: nodes, name: String(uni.name || ('Uni ' + uni.id)) };
		}

		var clusters = [];
		for (var i = 0; i < universes.length; i++) {
			clusters.push(addUniverseCluster(origins[i], universes[i], i));
		}

		function spawnPulse(at, colorHex) {
			var mat = new THREE.SpriteMaterial({
				map: pulseTex,
				transparent: true,
				depthWrite: false,
				opacity: 0.9,
				color: colorHex,
			});
			var sprite = new THREE.Sprite(mat);
			sprite.position.copy(at);
			sprite.position.z = 0.2;
			sprite.scale.set(1.5, 1.5, 1);
			scene.add(sprite);
			pulses.push({ sprite: sprite, mat: mat, born: Date.now(), life: 900 });
		}

		/* labels: universe names + a few galaxy tags when zoomed enough (always show uni) */
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

			/* universe titles */
			labelCtx.font = '600 13px "Trebuchet MS", "Segoe UI", sans-serif';
			labelCtx.textAlign = 'center';
			labelCtx.textBaseline = 'middle';
			for (var ci = 0; ci < clusters.length; ci++) {
				/* labels under the cluster so mobile top isn't clipped */
				tmpVec.set(origins[ci].x, origins[ci].y - clusterR * 1.12, 0).project(camera);
				var sx = (tmpVec.x + 1) / 2 * w;
				var sy = (1 - tmpVec.y) / 2 * h;
				sy = Math.min(h - 14, Math.max(14, sy));
				sx = Math.min(w - 8, Math.max(8, sx));
				var label = clusters[ci].name;
				var tw = labelCtx.measureText(label).width;
				labelCtx.fillStyle = 'rgba(0, 0, 0, 0.55)';
				labelCtx.fillRect(sx - tw / 2 - 7, sy - 9, tw + 14, 18);
				labelCtx.strokeStyle = 'rgba(70, 210, 255, 0.45)';
				labelCtx.strokeRect(sx - tw / 2 - 7, sy - 9, tw + 14, 18);
				labelCtx.fillStyle = '#c8ecff';
				labelCtx.fillText(label, sx, sy);
			}

			/* galaxy numbers — only when few enough to read */
			if (!isNarrow && galaxyNodes.length <= 24) {
				labelCtx.font = '10px "Trebuchet MS", "Segoe UI", sans-serif';
				labelCtx.fillStyle = 'rgba(160, 210, 230, 0.7)';
				for (var ni = 0; ni < galaxyNodes.length; ni++) {
					var node = galaxyNodes[ni];
					tmpVec.set(node.x, node.y - 2.2, 0).project(camera);
					var nx = (tmpVec.x + 1) / 2 * w;
					var ny = (1 - tmpVec.y) / 2 * h;
					labelCtx.fillText(node.label, nx, ny);
				}
			}
		}

		drawLabels();
		renderer.render(scene, camera);

		var lastPulseAt = {};
		var raf = 0;
		function animate() {
			raf = requestAnimationFrame(animate);
			var now = Date.now();

			if (!reduceMotion) {
				var nowSec = now / 1000;
				for (var mi = 0; mi < markerIdle.length; mi++) {
					var mark = markerIdle[mi];
					var breathe = 1 + Math.sin(nowSec * 1.4 + mark.phase) * 0.06;
					var s = mark.base * breathe;
					mark.sprite.scale.set(s, s, 1);
				}

				for (var bi = 0; bi < movingBolts.length; bi++) {
					var bolt = movingBolts[bi];
					var t = ((now - bolt.startTime) / 1000 % bolt.duration) / bolt.duration;
					bolt.sprite.position.copy(bolt.curve.getPoint(t));
					/* pulse when bolt nears destination */
					if (t > 0.92) {
						var key = bi + ':' + Math.floor((now - bolt.startTime) / (bolt.duration * 1000));
						if (!lastPulseAt[key]) {
							lastPulseAt[key] = true;
							spawnPulse(bolt.end, bolt.color);
						}
					}
				}

				for (var pi = pulses.length - 1; pi >= 0; pi--) {
					var pulse = pulses[pi];
					var age = now - pulse.born;
					var u = age / pulse.life;
					if (u >= 1) {
						scene.remove(pulse.sprite);
						pulse.mat.dispose();
						pulses.splice(pi, 1);
						continue;
					}
					var s = 1.5 + u * 10;
					pulse.sprite.scale.set(s, s, 1);
					pulse.mat.opacity = 0.85 * (1 - u);
				}
			}

			renderer.render(scene, camera);
		}
		animate();

		var resizeTimer = 0;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				updateCamera();
				drawLabels();
				renderer.render(scene, camera);
			}, 80);
		});

		container.classList.add('is-ready');
	}

	function start() {
		var cfg = readConfig();
		var container = document.getElementById('lobby-viz');
		if (!cfg || !container || !cfg.threeSrc) {
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
