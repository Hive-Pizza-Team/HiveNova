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
		var tex = new THREE.CanvasTexture(cv);
		tex.generateMipmaps = false;
		tex.minFilter = THREE.LinearFilter;
		tex.magFilter = THREE.LinearFilter;
		return tex;
	}

	/** Elongated missile: nose at top, exhaust trail at bottom (sprite.rotation aligns to path). */
	function makeMissileTexture() {
		var w = 64;
		var h = 160;
		var cv = document.createElement('canvas');
		cv.width = w;
		cv.height = h;
		var ctx = cv.getContext('2d');
		var cx = w / 2;

		/* soft exhaust bloom */
		var plume = ctx.createRadialGradient(cx, h * 0.88, 1, cx, h * 0.82, w * 0.48);
		plume.addColorStop(0, 'rgba(255,220,160,0.85)');
		plume.addColorStop(0.35, 'rgba(80,200,255,0.45)');
		plume.addColorStop(1, 'rgba(40,120,200,0)');
		ctx.fillStyle = plume;
		ctx.beginPath();
		ctx.arc(cx, h * 0.88, w * 0.48, 0, Math.PI * 2);
		ctx.fill();

		/* tapering ion trail */
		var trail = ctx.createLinearGradient(cx, h * 0.95, cx, h * 0.28);
		trail.addColorStop(0, 'rgba(60,180,255,0)');
		trail.addColorStop(0.25, 'rgba(100,220,255,0.4)');
		trail.addColorStop(0.7, 'rgba(220,245,255,0.85)');
		trail.addColorStop(1, 'rgba(255,255,255,0.95)');
		ctx.fillStyle = trail;
		ctx.beginPath();
		ctx.moveTo(cx - 1.2, h * 0.32);
		ctx.lineTo(cx + 1.2, h * 0.32);
		ctx.lineTo(cx + 5.5, h * 0.92);
		ctx.lineTo(cx - 5.5, h * 0.92);
		ctx.closePath();
		ctx.fill();

		/* fuselage */
		ctx.fillStyle = 'rgba(235,248,255,0.98)';
		ctx.beginPath();
		ctx.moveTo(cx, h * 0.06);
		ctx.lineTo(cx + 4.2, h * 0.26);
		ctx.lineTo(cx + 3.2, h * 0.48);
		ctx.lineTo(cx - 3.2, h * 0.48);
		ctx.lineTo(cx - 4.2, h * 0.26);
		ctx.closePath();
		ctx.fill();

		/* warhead tip */
		var tip = ctx.createLinearGradient(cx, h * 0.04, cx, h * 0.22);
		tip.addColorStop(0, 'rgba(255,255,255,1)');
		tip.addColorStop(1, 'rgba(180,230,255,0.9)');
		ctx.fillStyle = tip;
		ctx.beginPath();
		ctx.moveTo(cx, h * 0.04);
		ctx.lineTo(cx + 3.4, h * 0.2);
		ctx.lineTo(cx - 3.4, h * 0.2);
		ctx.closePath();
		ctx.fill();

		/* fins */
		ctx.fillStyle = 'rgba(160,220,255,0.75)';
		ctx.beginPath();
		ctx.moveTo(cx - 3, h * 0.42);
		ctx.lineTo(cx - 9, h * 0.55);
		ctx.lineTo(cx - 3, h * 0.5);
		ctx.closePath();
		ctx.fill();
		ctx.beginPath();
		ctx.moveTo(cx + 3, h * 0.42);
		ctx.lineTo(cx + 9, h * 0.55);
		ctx.lineTo(cx + 3, h * 0.5);
		ctx.closePath();
		ctx.fill();

		var tex = new THREE.CanvasTexture(cv);
		tex.generateMipmaps = false;
		tex.minFilter = THREE.LinearFilter;
		tex.magFilter = THREE.LinearFilter;
		return tex;
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
		/* Cap at 2 — mobile was stuck at 1 and looked soft on retina. */
		var pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
		var arcSegments = isNarrow ? 36 : 48;

		var scene = new THREE.Scene();
		var renderer = new THREE.WebGLRenderer({
			antialias: true,
			alpha: true,
			powerPreference: 'low-power',
		});
		renderer.setPixelRatio(pixelRatio);
		renderer.setClearColor(0x000000, 0);
		container.appendChild(renderer.domElement);

		/* —— layout: each universe = a labeled “continent” of galaxy nodes —— */
		var n = universes.length;
		var clusterR = isNarrow ? 22 : 32;
		var clusterGap = clusterR * 2.2;
		var origins = [];
		if (n === 1) {
			origins.push({ x: 0, y: 0 });
		} else {
			var orbit = Math.max(clusterGap * 0.5, (n * clusterGap) / (2 * Math.PI));
			for (var ui = 0; ui < n; ui++) {
				var a = (ui / n) * Math.PI * 2 - Math.PI / 2;
				origins.push({
					x: Math.cos(a) * orbit,
					y: Math.sin(a) * orbit * 0.72,
				});
			}
		}

		var maxGalaxyHint = 1;
		for (var ug = 0; ug < universes.length; ug++) {
			maxGalaxyHint = Math.max(maxGalaxyHint, parseInt(universes[ug].maxGalaxy, 10) || 1);
		}
		var ringR = clusterR * (0.42 + 0.08 * Math.min(maxGalaxyHint, 9)) * 1.05;
		var arcClear = Math.min(14, Math.max(5, ringR * 0.55));

		/* Tight AABB around clusters + arcs (uni name lives in the caption). */
		var bounds = { minX: Infinity, maxX: -Infinity, minY: Infinity, maxY: -Infinity };
		function expandBounds(x, y, pad) {
			bounds.minX = Math.min(bounds.minX, x - pad);
			bounds.maxX = Math.max(bounds.maxX, x + pad);
			bounds.minY = Math.min(bounds.minY, y - pad);
			bounds.maxY = Math.max(bounds.maxY, y + pad);
		}
		for (var oi = 0; oi < origins.length; oi++) {
			expandBounds(origins[oi].x, origins[oi].y, ringR + arcClear + 5);
		}
		var contentCX = (bounds.minX + bounds.maxX) * 0.5;
		var contentCY = (bounds.minY + bounds.maxY) * 0.5;
		var contentHalfW = Math.max(8, (bounds.maxX - bounds.minX) * 0.5);
		var contentHalfH = Math.max(8, (bounds.maxY - bounds.minY) * 0.5);
		var vizRadius = Math.max(contentHalfW, contentHalfH) * 1.4;

		var camera = new THREE.OrthographicCamera(-vizRadius, vizRadius, vizRadius, -vizRadius, 0.1, 100);
		camera.position.z = 50;

		function updateCamera() {
			var w = Math.max(1, container.clientWidth);
			var h = Math.max(1, container.clientHeight);
			var aspect = w / h;
			var margin = isNarrow ? 1.04 : 1.06;
			var halfW = contentHalfW * margin;
			var halfH = contentHalfH * margin;
			/* Fit content AABB as large as possible in the viewport. */
			if (halfW / halfH < aspect) {
				camera.top = contentCY + halfH;
				camera.bottom = contentCY - halfH;
				camera.left = contentCX - halfH * aspect;
				camera.right = contentCX + halfH * aspect;
			} else {
				camera.left = contentCX - halfW;
				camera.right = contentCX + halfW;
				camera.top = contentCY + halfW / aspect;
				camera.bottom = contentCY - halfW / aspect;
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
				opacity: 0.35,
				depthWrite: false,
			})));
		})();

		function makeStationTexture(hot, variant) {
			var size = 128;
			var cv = document.createElement('canvas');
			cv.width = cv.height = size;
			var ctx = cv.getContext('2d');
			var c = size / 2;
			variant = variant || 0;

			/* Bright filled map-icon — thin wire vanishes at this zoom. */
			var fill = hot ? '#ff9a4a' : '#6ad4ff';
			var fillDeep = hot ? '#c45a18' : '#1a6a90';
			var rim = hot ? '#ffe0b0' : '#e8f7ff';
			var core = hot ? '#fff2d0' : '#ffffff';

			ctx.lineCap = 'butt';
			ctx.lineJoin = 'miter';

			function pathPoly(r, sides, rot) {
				ctx.beginPath();
				for (var i = 0; i < sides; i++) {
					var a = rot + (i / sides) * Math.PI * 2;
					var x = c + Math.cos(a) * r;
					var y = c + Math.sin(a) * r;
					if (i === 0) ctx.moveTo(x, y);
					else ctx.lineTo(x, y);
				}
				ctx.closePath();
			}

			/* soft under-glow so the node always reads at distance */
			var glow = ctx.createRadialGradient(c, c, 4, c, c, c * 0.78);
			glow.addColorStop(0, hot ? 'rgba(255,140,60,0.55)' : 'rgba(80,200,255,0.45)');
			glow.addColorStop(0.55, hot ? 'rgba(255,120,40,0.18)' : 'rgba(60,160,220,0.14)');
			glow.addColorStop(1, 'rgba(0,0,0,0)');
			ctx.fillStyle = glow;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.78, 0, Math.PI * 2);
			ctx.fill();

			var sides = variant === 1 ? 6 : 8;
			var hubR = c * 0.34;
			var armLen = c * 0.58;
			var armW = variant === 2 ? 9 : 11;
			var arms = variant === 2 ? 6 : 4;
			var armRot = (variant * Math.PI) / 8;

			/* thick radial modules (filled bars, not hairlines) */
			for (var ai = 0; ai < arms; ai++) {
				var aa = armRot + (ai / arms) * Math.PI * 2;
				ctx.save();
				ctx.translate(c, c);
				ctx.rotate(aa);
				ctx.fillStyle = fill;
				ctx.fillRect(hubR * 0.55, -armW / 2, armLen - hubR * 0.55, armW);
				ctx.fillStyle = rim;
				ctx.fillRect(armLen - 7, -armW * 0.7, 8, armW * 1.4);
				ctx.restore();
			}

			/* outer docking ring — thick stroke */
			ctx.strokeStyle = fill;
			ctx.lineWidth = 5;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.62, 0, Math.PI * 2);
			ctx.stroke();
			ctx.strokeStyle = rim;
			ctx.lineWidth = 1.8;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.62, 0, Math.PI * 2);
			ctx.stroke();

			/* solid hub */
			pathPoly(hubR, sides, Math.PI / sides);
			ctx.fillStyle = fillDeep;
			ctx.fill();
			pathPoly(hubR, sides, Math.PI / sides);
			ctx.strokeStyle = rim;
			ctx.lineWidth = 3;
			ctx.stroke();

			pathPoly(hubR * 0.55, sides, Math.PI / sides);
			ctx.fillStyle = fill;
			ctx.fill();

			/* bright core */
			ctx.fillStyle = core;
			ctx.beginPath();
			ctx.arc(c, c, hot ? 7 : 6, 0, Math.PI * 2);
			ctx.fill();

			var tex = new THREE.CanvasTexture(cv);
			tex.generateMipmaps = false;
			tex.minFilter = THREE.LinearFilter;
			tex.magFilter = THREE.LinearFilter;
			tex.needsUpdate = true;
			return tex;
		}

		var stationTex = [
			makeStationTexture(false, 0),
			makeStationTexture(false, 1),
			makeStationTexture(false, 2),
		];
		var stationHotTex = [
			makeStationTexture(true, 0),
			makeStationTexture(true, 1),
			makeStationTexture(true, 2),
		];

		function makeReticleTexture(hot) {
			var size = 128;
			var cv = document.createElement('canvas');
			cv.width = cv.height = size;
			var ctx = cv.getContext('2d');
			var c = size / 2;
			var ink = hot ? 'rgba(255,150,70,' : 'rgba(90,220,255,';
			var inkCore = hot ? 'rgba(255,230,180,' : 'rgba(230,250,255,';

			var bloom = ctx.createRadialGradient(c, c, 2, c, c, c * 0.9);
			bloom.addColorStop(0, ink + '0.55)');
			bloom.addColorStop(0.45, ink + '0.2)');
			bloom.addColorStop(1, ink + '0)');
			ctx.fillStyle = bloom;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.9, 0, Math.PI * 2);
			ctx.fill();

			ctx.lineCap = 'butt';
			ctx.lineJoin = 'miter';

			ctx.strokeStyle = ink + '0.98)';
			ctx.lineWidth = 5;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.58, 0, Math.PI * 2);
			ctx.stroke();

			ctx.strokeStyle = inkCore + '0.9)';
			ctx.lineWidth = 2.4;
			ctx.beginPath();
			ctx.arc(c, c, c * 0.32, 0, Math.PI * 2);
			ctx.stroke();

			ctx.strokeStyle = ink + '0.95)';
			ctx.lineWidth = 3.5;
			var tickOut = c * 0.78;
			var tickIn = c * 0.48;
			[[0, -1], [0, 1], [-1, 0], [1, 0]].forEach(function (d) {
				ctx.beginPath();
				ctx.moveTo(c + d[0] * tickIn, c + d[1] * tickIn);
				ctx.lineTo(c + d[0] * tickOut, c + d[1] * tickOut);
				ctx.stroke();
			});

			ctx.fillStyle = inkCore + '1)';
			ctx.beginPath();
			var d = 8;
			ctx.moveTo(c, c - d);
			ctx.lineTo(c + d, c);
			ctx.lineTo(c, c + d);
			ctx.lineTo(c - d, c);
			ctx.closePath();
			ctx.fill();
			ctx.strokeStyle = ink + '0.85)';
			ctx.lineWidth = 1.5;
			ctx.stroke();

			var tex = new THREE.CanvasTexture(cv);
			tex.generateMipmaps = false;
			tex.minFilter = THREE.LinearFilter;
			tex.magFilter = THREE.LinearFilter;
			tex.needsUpdate = true;
			return tex;
		}

		var reticleTex = makeReticleTexture(true);
		var pulseTex = makeRadialTexture(128, [
			[0, 'rgba(255,255,255,0)'],
			[0.55, 'rgba(80,220,255,0)'],
			[0.72, 'rgba(80,220,255,0.95)'],
			[0.9, 'rgba(80,220,255,0.2)'],
			[1, 'rgba(0,0,0,0)'],
		]);
		var boltTex = makeMissileTexture();

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
			var attackEnds = {};

			(uni.fleets || []).forEach(function (f) {
				var sg = parseInt(f.startGroup, 10) || 1;
				var eg = parseInt(f.endGroup, 10) || 1;
				heat[sg] = (heat[sg] || 0) + 1;
				heat[eg] = (heat[eg] || 0) + 1;
				/* Any cross-galaxy flight gets source/dest reticles (not combat-only). */
				if (sg !== eg) {
					attackEnds[sg] = missionCategory(f.mission) === 'combat' ? 2 : Math.max(attackEnds[sg] || 0, 1);
					attackEnds[eg] = missionCategory(f.mission) === 'combat' ? 2 : Math.max(attackEnds[eg] || 0, 1);
				}
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
						opacity: 0.28,
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
					[0, 'rgba(30,90,140,0.14)'],
					[0.5, 'rgba(20,50,90,0.05)'],
					[1, 'rgba(0,0,0,0)'],
				]),
				transparent: true,
				depthWrite: false,
			}));
			wash.position.set(origin.x, origin.y, -1);
			wash.scale.set(clusterR * 2.4, clusterR * 2.4, 1);
			scene.add(wash);

			/* stations by default; reticles on every fleet source/dest */
			for (var gi = 0; gi < maxGalaxy; gi++) {
				var pos = nodes[gi];
				var activity = heat[gi + 1] || 0;
				var routeLevel = attackEnds[gi + 1] || 0;
				var isRoute = routeLevel > 0;
				var variant = gi % 3;
				var mat;
				var scale;
				if (isRoute) {
					mat = new THREE.SpriteMaterial({
						map: reticleTex,
						transparent: true,
						depthWrite: false,
						opacity: 1,
						depthTest: false,
					});
					scale = 11.5 + Math.min(3.5, activity * 0.7);
				} else {
					mat = new THREE.SpriteMaterial({
						map: activity >= 2 ? stationHotTex[variant] : stationTex[variant],
						transparent: true,
						depthWrite: false,
						opacity: 1,
						depthTest: false,
						rotation: (gi * 0.41 + uniIndex) % (Math.PI * 2),
					});
					scale = 8.5 + Math.min(3.5, activity * 0.8);
				}
				var sprite = new THREE.Sprite(mat);
				sprite.position.set(pos.x, pos.y, 0.6);
				sprite.renderOrder = 10;
				sprite.scale.set(scale, scale, 1);
				scene.add(sprite);
				markerIdle.push({
					sprite: sprite,
					mat: mat,
					base: scale,
					phase: gi * 0.73 + uniIndex,
					reticle: isRoute,
				});
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

				var boltMat = new THREE.SpriteMaterial({
					map: boltTex,
					transparent: true,
					depthWrite: false,
					opacity: 0.98,
					color: color,
					blending: THREE.AdditiveBlending,
				});
				var bolt = new THREE.Sprite(boltMat);
				/* narrow × long — reads as a dart/ICBM, not an orb */
				bolt.scale.set(isNarrow ? 3.4 : 4.2, isNarrow ? 8.5 : 10.5, 1);
				bolt.position.z = 0.35;
				scene.add(bolt);

				var duration = Math.max(3.5, Math.min(18, parseFloat(row.duration) || 8));
				movingBolts.push({
					sprite: bolt,
					mat: boltMat,
					curve: curve,
					duration: duration,
					startTime: Date.now() - fi * 180,
					end: end,
					color: color,
				});
			}
		}

		for (var i = 0; i < universes.length; i++) {
			addUniverseCluster(origins[i], universes[i], i);
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

		/* optional galaxy tags when few enough to read */
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

			if (!isNarrow && galaxyNodes.length <= 24) {
				labelCtx.font = '10px "Trebuchet MS", "Segoe UI", sans-serif';
				labelCtx.textAlign = 'center';
				labelCtx.textBaseline = 'middle';
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
					if (mark.reticle) {
						/* reticles pulse; stations yaw */
						var breathe = 1 + Math.sin(nowSec * 2.2 + mark.phase) * 0.08;
						var s = mark.base * breathe;
						mark.sprite.scale.set(s, s, 1);
					} else {
						mark.mat.rotation = mark.phase + nowSec * 0.08;
						mark.sprite.scale.set(mark.base, mark.base, 1);
					}
				}

				for (var bi = 0; bi < movingBolts.length; bi++) {
					var bolt = movingBolts[bi];
					var t = ((now - bolt.startTime) / 1000 % bolt.duration) / bolt.duration;
					bolt.sprite.position.copy(bolt.curve.getPoint(t));
					bolt.sprite.position.z = 0.35;
					/* nose follows curve tangent (texture points +Y) */
					var tan = bolt.curve.getTangent(t);
					bolt.mat.rotation = Math.atan2(tan.y, tan.x) - Math.PI / 2;
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
