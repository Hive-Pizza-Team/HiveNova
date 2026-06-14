/**
 * Overview planet visualization — Three.js r149
 *
 * The game's planeten/*.jpg files are pre-rendered planet portraits (baked
 * shading + black background), so they cannot be used as sphere textures.
 * Instead we synthesize a seamless equirectangular surface procedurally from
 * the planet's biome (derived from its image name) and temperature, render city
 * lights on a night-side emissive overlay mesh, and add a fresnel atmosphere,
 * clouds, a solar-satellite ring, ship traffic and construction activity.
 *
 * Production behaviour: static JPG fallback only when WebGL fails or is unavailable;
 * while WebGL boots the frame stays on the dark background. Pauses when the canvas
 * leaves the viewport or the tab is hidden; respects prefers-reduced-motion.
 */
(function () {
	'use strict';

	var dataEl, canvas, fallbackImg, data;
	var reducedMotion, isMobile, dprCap, animating;
	var renderer, scene, camera;
	var planetAnchor, planetSystem, planetMesh, cloudMesh, atmosphereMesh;
	var satelliteMesh, shipGroup, constructionBeam, starField;
	var moonOrbitGroup, debrisOrbitGroup;
	var unknownScanGroup = null;
	var nightEmissiveMesh = null;
	var nightEmissiveUniforms = null;
	var sceneSunLight = null;
	var previewRenderer = null;
	var previewCanvasEl = null;
	var rafId = null;
	var clock;
	var viewportVisible = true;
	var intersectionObserver = null;
	var baseCameraZ = 3.4;
	var zoomFactor = 1;
	var zoomControlsEl = null;
	var ZOOM_MIN = 0.55;
	var ZOOM_MAX = 1.9;
	var ZOOM_STEP = 0.12;

	var PLANET_SPIN = 0.08;
	var CLOUD_SPIN = 0.11;
	var SATELLITE_SPIN = 0.04;
	var AXIAL_TILT = 0.22;
	// Default overview framing: outer atmosphere shell + ~32% margin per axis in the FOV.
	var PLANET_FRAME_RADIUS = 1.16;
	var DEFAULT_FRAME_PADDING = 1.40;

	// Coruscant-style ecumenopolis visuals scale with building-slot fill (fields.current / fields.max).
	// Set to false to restore the previous industrial-building city lights only.
	var BUILDUP_BY_SLOTS = true;

	var viewerOptions = {
		enableZoom: true,
		fixedSize: 0,
		preview: false,
		lite: false
	};
	var overviewMounted = false;

	function getCanvas2d(canvasEl, opts) {
		opts = opts || {};
		if (opts.willReadFrequently) {
			try {
				return canvasEl.getContext('2d', { willReadFrequently: true });
			} catch (e) {
				// older browsers
			}
		}
		return canvasEl.getContext('2d');
	}

	// ---- helpers -----------------------------------------------------------

	function finalizeCanvasTexture(tex, opts) {
		opts = opts || {};
		if (opts.srgb !== false && tex.encoding !== undefined) {
			tex.encoding = THREE.sRGBEncoding;
		}
		if (!opts.mipmap) {
			tex.generateMipmaps = false;
			tex.minFilter = THREE.LinearFilter;
			tex.magFilter = THREE.LinearFilter;
		}
		if (opts.wrapS) {
			tex.wrapS = opts.wrapS;
		}
		return tex;
	}

	function disposeMaterial(material) {
		if (!material) {
			return;
		}
		var maps = ['map', 'emissiveMap', 'bumpMap', 'normalMap', 'alphaMap', 'roughnessMap', 'metalnessMap'];
		for (var i = 0; i < maps.length; i++) {
			if (material[maps[i]]) {
				material[maps[i]].dispose();
			}
		}
		if (material.uniforms) {
			for (var key in material.uniforms) {
				var uniform = material.uniforms[key];
				if (uniform && uniform.value && uniform.value.isTexture) {
					uniform.value.dispose();
				}
			}
		}
		material.dispose();
	}

	// City/buildup emissive on a night-side overlay (Coruscant-style).
	var NIGHT_EMISSIVE_VERTEX_SHADER = [
		'varying vec2 vHiveNovaUv;',
		'varying vec3 vHiveNovaWorldNormal;',
		'void main() {',
		'  vHiveNovaUv = uv;',
		'  vHiveNovaWorldNormal = normalize(mat3(modelMatrix) * normal);',
		'  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);',
		'}'
	].join('\n');

	var NIGHT_EMISSIVE_FRAGMENT_SHADER = [
		'uniform sampler2D uEmissiveMap;',
		'uniform sampler2D uSurfaceMap;',
		'uniform vec3 uSunDirection;',
		'uniform float uIntensity;',
		'uniform float uTerminator;',
		'uniform float uDayBleed;',
		'uniform float uNightSurfaceGlow;',
		'varying vec2 vHiveNovaUv;',
		'varying vec3 vHiveNovaWorldNormal;',
		'void main() {',
		'  vec3 cityColor = texture2D(uEmissiveMap, vHiveNovaUv).rgb;',
		'  float cityLum = max(cityColor.r, max(cityColor.g, cityColor.b));',
		'  float sunFacing = dot(normalize(vHiveNovaWorldNormal), normalize(uSunDirection));',
		'  float night = 1.0 - smoothstep(-0.06, uTerminator, sunFacing);',
		'  float cityMask = mix(uDayBleed, 1.0, night);',
		'  vec3 city = cityColor * cityMask * uIntensity;',
		'  vec3 surf = texture2D(uSurfaceMap, vHiveNovaUv).rgb * uNightSurfaceGlow * night;',
		'  vec3 color = city + surf;',
		'  float bright = max(cityLum * cityMask * uIntensity, max(surf.r, max(surf.g, surf.b)));',
		'  if (bright < 0.0015) discard;',
		'  gl_FragColor = vec4(color, 1.0);',
		'}'
	].join('\n');

	function attachNightEmissiveOverlay(planetGeo, emissiveMap, surfaceMap, options) {
		options = options || {};
		nightEmissiveUniforms = {
			uEmissiveMap: { value: emissiveMap },
			uSurfaceMap: { value: surfaceMap },
			uSunDirection: { value: new THREE.Vector3(1, 0.4, 0.75).normalize() },
			uIntensity: { value: options.intensity !== undefined ? options.intensity : 1.0 },
			uTerminator: { value: options.terminator !== undefined ? options.terminator : 0.38 },
			uDayBleed: { value: options.dayBleed !== undefined ? options.dayBleed : 0.04 },
			uNightSurfaceGlow: { value: options.nightSurfaceGlow !== undefined ? options.nightSurfaceGlow : 0.22 }
		};
		if (sceneSunLight) {
			nightEmissiveUniforms.uSunDirection.value.copy(sceneSunLight.position).normalize();
		}
		var material = new THREE.ShaderMaterial({
			uniforms: nightEmissiveUniforms,
			vertexShader: NIGHT_EMISSIVE_VERTEX_SHADER,
			fragmentShader: NIGHT_EMISSIVE_FRAGMENT_SHADER,
			transparent: true,
			blending: THREE.CustomBlending,
			blendEquation: THREE.AddEquation,
			blendSrc: THREE.OneFactor,
			blendDst: THREE.OneFactor,
			depthWrite: false,
			toneMapped: false
		});
		nightEmissiveMesh = new THREE.Mesh(planetGeo, material);
		nightEmissiveMesh.scale.setScalar(1.0025);
		nightEmissiveMesh.renderOrder = 2;
		planetSystem.add(nightEmissiveMesh);
	}

	function disposeObject3D(root) {
		if (!root) {
			return;
		}
		root.traverse(function (child) {
			if (child.geometry) {
				child.geometry.dispose();
			}
			if (child.material) {
				if (Array.isArray(child.material)) {
					child.material.forEach(disposeMaterial);
				} else {
					disposeMaterial(child.material);
				}
			}
		});
	}

	function shouldAnimate() {
		return animating && viewportVisible && !document.hidden;
	}

	function startAnimationLoop() {
		if (!shouldAnimate() || !planetMesh || rafId !== null) {
			return;
		}
		clock.getDelta();
		animate();
	}

	function stopAnimationLoop() {
		if (rafId !== null) {
			cancelAnimationFrame(rafId);
			rafId = null;
		}
	}

	function setViewportVisible(next) {
		viewportVisible = next;
		if (shouldAnimate()) {
			startAnimationLoop();
		} else {
			stopAnimationLoop();
		}
	}

	function observeViewport() {
		if (!canvas || typeof IntersectionObserver === 'undefined') {
			return;
		}
		intersectionObserver = new IntersectionObserver(function (entries) {
			var entry = entries[0];
			if (!entry) {
				return;
			}
			setViewportVisible(entry.isIntersecting && entry.intersectionRatio > 0.02);
		}, { threshold: [0, 0.02, 0.1] });
		intersectionObserver.observe(canvas);
	}

	function markLoading(isLoading) {
		if (viewerOptions.preview || !canvas || !canvas.parentElement) {
			return;
		}
		var wrap = canvas.parentElement;
		if (isLoading) {
			wrap.classList.add('overview-planet-visual--loading');
			wrap.classList.remove('overview-planet-visual--ready', 'overview-planet-visual--fallback');
			ensureFallbackSrc();
		} else {
			wrap.classList.remove('overview-planet-visual--loading');
		}
	}

	function ensureFallbackSrc() {
		if (!fallbackImg) {
			return;
		}
		if (typeof OverviewPlanetLoaderUtils !== 'undefined'
			&& typeof OverviewPlanetLoaderUtils.resolveFallbackSrc === 'function') {
			OverviewPlanetLoaderUtils.resolveFallbackSrc(fallbackImg);
			return;
		}
		var hqSrc = fallbackImg.getAttribute('data-src-hq');
		var stdSrc = fallbackImg.getAttribute('data-src');
		if (hqSrc && !fallbackImg.getAttribute('src')) {
			fallbackImg.src = hqSrc;
			fallbackImg.onerror = function () {
				if (stdSrc) {
					fallbackImg.src = stdSrc;
				}
			};
			return;
		}
		if (stdSrc && !fallbackImg.getAttribute('src')) {
			fallbackImg.src = stdSrc;
		}
	}

	function showFallback() {
		if (!canvas || !canvas.parentElement) {
			return;
		}
		var wrap = canvas.parentElement;
		wrap.classList.remove('overview-planet-visual--loading', 'overview-planet-visual--ready');
		wrap.classList.add('overview-planet-visual--fallback');
		ensureFallbackSrc();
		if (fallbackImg) {
			fallbackImg.removeAttribute('aria-hidden');
		}
	}

	function markReady() {
		requestAnimationFrame(function () {
			if (renderer && scene && camera) {
				renderer.render(scene, camera);
			}
			if (canvas && canvas.parentElement) {
				var wrap = canvas.parentElement;
				wrap.classList.remove('overview-planet-visual--loading', 'overview-planet-visual--fallback');
				wrap.classList.add('overview-planet-visual--ready');
			}
			markLoading(false);
			if (fallbackImg) {
				fallbackImg.setAttribute('aria-hidden', 'true');
			}
		});
	}

	function applyCameraZoom() {
		if (!camera) { return; }
		camera.position.set(0, 0, baseCameraZ * zoomFactor);
		camera.lookAt(0, 0, 0);
		if (renderer && scene && (!animating || document.hidden)) {
			renderer.render(scene, camera);
		}
	}

	function updateZoomButtons() {
		if (!zoomControlsEl) { return; }
		var btnIn = zoomControlsEl.querySelector('[data-zoom="in"]');
		var btnOut = zoomControlsEl.querySelector('[data-zoom="out"]');
		if (btnIn) { btnIn.disabled = zoomFactor <= ZOOM_MIN + 0.001; }
		if (btnOut) { btnOut.disabled = zoomFactor >= ZOOM_MAX - 0.001; }
	}

	function setZoomFactor(next) {
		var clamped = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, next));
		if (Math.abs(clamped - zoomFactor) < 0.001) { return; }
		zoomFactor = clamped;
		applyCameraZoom();
		updateZoomButtons();
	}

	function adjustZoom(delta) {
		setZoomFactor(zoomFactor + delta);
	}

	function createZoomControls() {
		if (!viewerOptions.enableZoom || !canvas || !canvas.parentElement || zoomControlsEl) { return; }

		var wrap = document.createElement('div');
		wrap.className = 'overview-planet-zoom';
		wrap.setAttribute('role', 'group');
		wrap.setAttribute('aria-label', 'Planet zoom');

		var btnIn = document.createElement('button');
		btnIn.type = 'button';
		btnIn.className = 'overview-planet-zoom-btn';
		btnIn.setAttribute('data-zoom', 'in');
		btnIn.setAttribute('aria-label', 'Zoom in');
		btnIn.textContent = '+';
		btnIn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			adjustZoom(-ZOOM_STEP);
		});

		var btnOut = document.createElement('button');
		btnOut.type = 'button';
		btnOut.className = 'overview-planet-zoom-btn';
		btnOut.setAttribute('data-zoom', 'out');
		btnOut.setAttribute('aria-label', 'Zoom out');
		btnOut.textContent = '\u2212';
		btnOut.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			adjustZoom(ZOOM_STEP);
		});

		wrap.appendChild(btnIn);
		wrap.appendChild(btnOut);
		canvas.parentElement.appendChild(wrap);
		zoomControlsEl = wrap;
		updateZoomButtons();

		canvas.parentElement.addEventListener('wheel', function (e) {
			if (!renderer || !canvas.parentElement
				|| !canvas.parentElement.classList.contains('overview-planet-visual--ready')) {
				return;
			}
			e.preventDefault();
			adjustZoom(e.deltaY > 0 ? ZOOM_STEP : -ZOOM_STEP);
		}, { passive: false });
	}

	function sumLevels(obj, ids) {
		var total = 0;
		for (var i = 0; i < ids.length; i++) { total += obj[ids[i]] || 0; }
		return total;
	}

	function getBuildingLevel(id) {
		if (!data.buildings) {
			return 0;
		}
		return data.buildings[id] || data.buildings[String(id)] || 0;
	}

	function isMoonBody() {
		return Number(data.type) === 3;
	}

	function getMoonBasePlacement(level) {
		var rng = makeRng(hashString(
			'moonbase:' + (data.galaxy || 0) + ':' + (data.system || 0) + ':' + (data.planet || 0)
		));
		return {
			lat: 18 + rng() * 10,
			lon: -94 + rng() * 10,
			tier: Math.min(5, Math.max(1, Math.floor(level))),
			rng: rng
		};
	}

	function latLonToUv(lat, lon) {
		var u = ((lon + 180) % 360) / 360;
		var v = (90 - lat) / 180;
		return { u: u, v: v };
	}

	function moonBaseRegion(placement, W) {
		var uv = latLonToUv(placement.lat, placement.lon);
		return {
			cx: uv.u * W,
			cy: uv.v * (W / 2),
			radius: 42 + placement.tier * 13,
			crossesSeam: false
		};
	}

	function moonSeamOffsets(region, W) {
		if (region.cx - region.radius < 0 || region.cx + region.radius > W) {
			return [0, W, -W];
		}
		return [0];
	}

	// Darkens the moon surface into a metropolitan grey patch around the base site.
	function drawMoonCityPatch(ctx, placement, W) {
		var region = moonBaseRegion(placement, W);
		var offsets = moonSeamOffsets(region, W);
		for (var o = 0; o < offsets.length; o++) {
			var cx = region.cx + offsets[o];
			var grad = ctx.createRadialGradient(cx, region.cy, 0, cx, region.cy, region.radius);
			grad.addColorStop(0, 'rgba(38,42,52,0.88)');
			grad.addColorStop(0.55, 'rgba(46,50,62,0.62)');
			grad.addColorStop(1, 'rgba(0,0,0,0)');
			ctx.fillStyle = grad;
			ctx.beginPath();
			ctx.arc(cx, region.cy, region.radius, 0, Math.PI * 2);
			ctx.fill();
		}
	}

	// Localized city-lights emissive map, mirroring the planet urbanization look.
	function generateMoonBaseLights(placement, W, H) {
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		ctx.fillStyle = '#000';
		ctx.fillRect(0, 0, W, H);

		var region = moonBaseRegion(placement, W);
		var offsets = moonSeamOffsets(region, W);
		var tier = placement.tier;
		var ratio = Math.min(0.92, 0.34 + tier * 0.12);
		var rng = makeRng(hashString(
			'moonbaselights:' + (data.galaxy || 0) + ':' + (data.system || 0) + ':' + (data.planet || 0)
		));
		var mobileScale = isMobile ? 0.55 : 1;

		function pointInRegion() {
			var ang = rng() * Math.PI * 2;
			var rr = Math.sqrt(rng()) * region.radius * 0.94;
			return { x: region.cx + Math.cos(ang) * rr, y: region.cy + Math.sin(ang) * rr };
		}

		var pinCount = Math.floor((90 + tier * 150) * mobileScale);
		for (var p = 0; p < pinCount; p++) {
			var pt = pointInRegion();
			var warm = rng() < (0.84 - ratio * 0.1);
			var alpha = 0.4 + ratio * 0.6 + rng() * 0.2;
			var radius = 0.7 + rng() * 1.8;
			for (var po = 0; po < offsets.length; po++) {
				drawWarmGlow(ctx, pt.x + offsets[po], pt.y, radius, alpha, warm);
			}
		}

		var hubs = [{ x: region.cx, y: region.cy }];
		var subHubs = Math.max(0, tier - 1);
		for (var s = 0; s < subHubs; s++) {
			var hp = pointInRegion();
			hubs.push(hp);
		}
		for (var h = 0; h < hubs.length; h++) {
			for (var ho = 0; ho < offsets.length; ho++) {
				drawHubRings(ctx, hubs[h].x + offsets[ho], hubs[h].y, rng, ratio, W);
			}
		}

		if (hubs.length > 1) {
			for (var l = 0; l < hubs.length; l++) {
				var a = hubs[0];
				var b = hubs[l];
				for (var lo = 0; lo < offsets.length; lo++) {
					drawGridSegment(ctx, a.x + offsets[lo], a.y, b.x + offsets[lo], b.y, W, 0.2 + ratio * 0.4);
				}
			}
		}

		return new THREE.CanvasTexture(canvasEl);
	}

	function getBuildupRatio() {
		if (!BUILDUP_BY_SLOTS || !data.fields) {
			return null;
		}
		var max = data.fields.max || 0;
		if (max <= 0) {
			return 0;
		}
		return Math.max(0, Math.min(1, (data.fields.current || 0) / max));
	}

	// Eased 0–1 weight: subdued through ~40% fill, ramps toward ecumenopolis at 100%.
	function buildupVisualWeight(ratio) {
		var t = Math.max(0, Math.min(1, ratio));
		return t * t * (3 - 2 * t);
	}

	function hubCorridorAlpha(ratio) {
		return 0.08 + buildupVisualWeight(ratio) * 0.38;
	}

	function ringStrokeAlpha(ratio) {
		return 0.18 + buildupVisualWeight(ratio) * 0.52;
	}

	function isBuildableLand(elev, sea, u, v, ratio) {
		if (ratio >= 0.72 || !elev) {
			return true;
		}
		return elev(u, v) > sea + 0.02;
	}

	function drawWarmGlow(ctx, x, y, radius, alpha, warm) {
		var g = ctx.createRadialGradient(x, y, 0, x, y, radius);
		if (warm) {
			g.addColorStop(0, 'rgba(255,188,108,' + alpha + ')');
			g.addColorStop(0.35, 'rgba(255,128,48,' + (alpha * 0.5) + ')');
		} else {
			g.addColorStop(0, 'rgba(150,200,255,' + alpha + ')');
			g.addColorStop(0.35, 'rgba(80,140,220,' + (alpha * 0.45) + ')');
		}
		g.addColorStop(1, 'rgba(0,0,0,0)');
		ctx.fillStyle = g;
		ctx.beginPath();
		ctx.arc(x, y, radius, 0, Math.PI * 2);
		ctx.fill();
	}

	function drawHubRings(ctx, cx, cy, rng, ratio, W) {
		var weight = buildupVisualWeight(ratio);
		var ringCount = ratio < 0.35
			? 1 + Math.floor(rng() * 2)
			: ratio < 0.72
				? 2 + Math.floor(rng() * 2 + weight * 1.5)
				: 2 + Math.floor(rng() * 2 + weight * 3);
		var maxR = Math.min(W * 0.1, 16 + weight * 46);
		var alpha = ringStrokeAlpha(ratio);
		ctx.lineCap = 'round';
		for (var ri = 0; ri < ringCount; ri++) {
			var radius = maxR * (0.42 + (ri + 1) / (ringCount + 1) * 0.58);
			var start = rng() * Math.PI * 2;
			var span = Math.PI * (0.45 + rng() * (0.55 + weight * 0.45));
			ctx.beginPath();
			ctx.arc(cx, cy, radius, start, start + span);
			var warmth = 132 + Math.floor(rng() * 48);
			ctx.strokeStyle = 'rgba(255,' + warmth + ',' + Math.floor(24 + rng() * 24) + ',' + alpha + ')';
			ctx.lineWidth = 0.45 + weight * 1.35;
			ctx.stroke();
		}
	}

	function drawGridSegment(ctx, x1, y1, x2, y2, W, alpha) {
		if (Math.abs(x2 - x1) > W * 0.45) {
			return;
		}
		alpha = Math.min(0.62, alpha);
		ctx.beginPath();
		ctx.moveTo(x1, y1);
		ctx.lineTo(x2, y2);
		ctx.strokeStyle = 'rgba(255,152,58,' + alpha + ')';
		ctx.lineWidth = 0.35 + alpha * 0.45;
		ctx.stroke();
	}

	function drawHubCorridors(ctx, hubs, rng, ratio, W, mobileScale) {
		if (hubs.length < 2 || ratio < 0.32) {
			return;
		}
		var corridorAlpha = hubCorridorAlpha(ratio);
		var maxLinks = ratio < 0.62 ? 1 : ratio < 0.85 ? 2 : 3;

		function hubDistance(a, b) {
			var dx = a.x - b.x;
			var dy = (a.y - b.y) * 0.65;
			return dx * dx + dy * dy;
		}

		for (var hi = 0; hi < hubs.length; hi++) {
			var neighbors = hubs.slice();
			var home = neighbors.splice(hi, 1)[0];
			neighbors.sort(function (a, b) {
				return hubDistance(home, a) - hubDistance(home, b);
			});
			var links = Math.min(maxLinks, neighbors.length);
			for (var li = 0; li < links; li++) {
				drawGridSegment(
					ctx, home.x, home.y, neighbors[li].x, neighbors[li].y, W,
					corridorAlpha * (1 - li * 0.22)
				);
			}
		}

		if (ratio > 0.78 && hubs.length > 3) {
			var extra = Math.floor((ratio - 0.72) * 18 * mobileScale);
			for (var e = 0; e < extra; e++) {
				var a = hubs[Math.floor(rng() * hubs.length)];
				var b = hubs[Math.floor(rng() * hubs.length)];
				if (a === b) { continue; }
				drawGridSegment(ctx, a.x, a.y, b.x, b.y, W, corridorAlpha * 0.5);
			}
		}
	}

	function sumFleetExcludingSatellites(fleet) {
		var total = 0;
		for (var id in fleet) {
			if (Object.prototype.hasOwnProperty.call(fleet, id) && parseInt(id, 10) !== 212) {
				total += fleet[id];
			}
		}
		return total;
	}

	function hashString(str) {
		var h = 2166136261;
		str = String(str || 'planet');
		for (var i = 0; i < str.length; i++) {
			h ^= str.charCodeAt(i);
			h = Math.imul(h, 16777619);
		}
		return h >>> 0;
	}

	function makeRng(seed) {
		var s = seed >>> 0;
		return function () {
			s = (s + 0x6D2B79F5) | 0;
			var t = Math.imul(s ^ (s >>> 15), 1 | s);
			t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
			return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
		};
	}

	// Tiling (in u) fractal value noise for equirectangular maps.
	function buildFbm(seed, octaves, baseW, baseH) {
		var layers = [];
		var norm = 0;
		for (var o = 0; o < octaves; o++) {
			var gw = baseW << o;
			var gh = baseH << o;
			var rng = makeRng(seed + o * 1013904223);
			var g = new Float32Array(gw * gh);
			for (var i = 0; i < g.length; i++) { g[i] = rng(); }
			var amp = Math.pow(0.5, o);
			layers.push({ gw: gw, gh: gh, g: g, amp: amp });
			norm += amp;
		}
		return function (u, v) {
			var sum = 0;
			for (var k = 0; k < layers.length; k++) {
				var L = layers[k];
				var x = u * L.gw;
				var y = v * L.gh;
				var x0 = Math.floor(x);
				var y0 = Math.floor(y);
				var fx = x - x0;
				var fy = y - y0;
				var xa = ((x0 % L.gw) + L.gw) % L.gw;
				var xb = (xa + 1) % L.gw;
				var ya = Math.max(0, Math.min(L.gh - 1, y0));
				var yb = Math.max(0, Math.min(L.gh - 1, y0 + 1));
				var a = L.g[ya * L.gw + xa];
				var b = L.g[ya * L.gw + xb];
				var c = L.g[yb * L.gw + xa];
				var d = L.g[yb * L.gw + xb];
				var sx = fx * fx * (3 - 2 * fx);
				var sy = fy * fy * (3 - 2 * fy);
				var top = a + (b - a) * sx;
				var bot = c + (d - c) * sx;
				sum += (top + (bot - top) * sy) * L.amp;
			}
			return sum / norm;
		};
	}

	function getVizState() {
		return data && data.vizState ? data.vizState : null;
	}

	function classifyBiome(name, type) {
		if (type === 3) { return 'moon'; }
		name = (name || '').toLowerCase();
		if (name.indexOf('mond') === 0 || name.indexOf('moon') >= 0) { return 'moon'; }
		if (name.indexOf('gas') >= 0) { return 'gas'; }
		if (name.indexOf('eis') >= 0) { return 'ice'; }
		if (name.indexOf('wasser') >= 0) { return 'water'; }
		if (name.indexOf('wuest') >= 0 || name.indexOf('trocken') >= 0) { return 'desert'; }
		if (name.indexOf('dschjungel') >= 0 || name.indexOf('jungel') >= 0 || name.indexOf('jungle') >= 0) { return 'jungle'; }
		return 'terra';
	}

	function mix(a, b, t) { return a + (b - a) * t; }

	function hslToRgb(h, s, l) {
		h = (((h % 360) + 360) % 360) / 360;
		s = Math.max(0, Math.min(1, s));
		l = Math.max(0, Math.min(1, l));
		var r, g, b;
		if (s === 0) {
			r = g = b = l;
		} else {
			var hue2rgb = function (p, q, t) {
				if (t < 0) { t += 1; }
				if (t > 1) { t -= 1; }
				if (t < 1 / 6) { return p + (q - p) * 6 * t; }
				if (t < 1 / 2) { return q; }
				if (t < 2 / 3) { return p + (q - p) * (2 / 3 - t) * 6; }
				return p;
			};
			var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
			var p = 2 * l - q;
			r = hue2rgb(p, q, h + 1 / 3);
			g = hue2rgb(p, q, h);
			b = hue2rgb(p, q, h - 1 / 3);
		}
		return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
	}

	// Palettes are stored in HSL so each planet can be deterministically jittered.
	var BANDS = {
		lava:    { low: [16, 0.55, 0.07], land: [20, 0.55, 0.16], high: [32, 0.80, 0.40], land2: [8, 0.5, 0.12],  glow: [22, 1.0, 0.5],  atmo: [16, 0.8, 0.45] },
		desert:  { ocean: [195, 0.5, 0.30], shallow: [190, 0.5, 0.45], land: [35, 0.5, 0.42], high: [42, 0.45, 0.66], land2: [25, 0.45, 0.5], sea: 0.28, ice: 0.03, atmo: [38, 0.5, 0.6] },
		savanna: { ocean: [212, 0.6, 0.28], shallow: [202, 0.6, 0.44], land: [72, 0.42, 0.36], high: [58, 0.4, 0.58], land2: [46, 0.45, 0.42], sea: 0.45, ice: 0.05, atmo: [205, 0.5, 0.65] },
		terra:   { ocean: [218, 0.75, 0.20], shallow: [206, 0.7, 0.36], land: [138, 0.5, 0.24], high: [82, 0.4, 0.46], land2: [98, 0.45, 0.27], sea: 0.50, ice: 0.10, atmo: [210, 0.7, 0.62] },
		tundra:  { ocean: [210, 0.4, 0.30], shallow: [200, 0.4, 0.46], land: [150, 0.18, 0.40], high: [205, 0.12, 0.86], land2: [40, 0.14, 0.42], sea: 0.45, ice: 0.30, atmo: [200, 0.4, 0.7] },
		ice:     { ocean: [200, 0.45, 0.55], shallow: [196, 0.5, 0.72], land: [205, 0.22, 0.85], high: [205, 0.12, 0.98], land2: [210, 0.2, 0.8], sea: 0.50, ice: 0.55, atmo: [196, 0.5, 0.78] }
	};

	function tempBand(t) {
		if (t > 110) { return 'lava'; }
		if (t > 55) { return 'desert'; }
		if (t > 25) { return 'savanna'; }
		if (t > 0) { return 'terra'; }
		if (t > -45) { return 'tundra'; }
		return 'ice';
	}

	function computeStyle() {
		var seed = hashString(
			(data.texture || 'planet') + ':' + (data.galaxy || 0) + ':' + (data.system || 0) + ':' + (data.planet || 0)
				+ ':' + (getVizState() || '')
		);
		var rng = makeRng(seed);
		var vizState = getVizState();

		if (vizState === 'unknown') {
			return {
				biome: 'unknown',
				band: 'unknown',
				seed: seed,
				sizeScale: 1,
				baseW: 8,
				atmoRgb: [78, 92, 128]
			};
		}
		if (vizState === 'destroyed') {
			return {
				biome: 'destroyed',
				band: 'destroyed',
				seed: seed,
				sizeScale: 0.96 + (rng() - 0.5) * 0.05,
				baseW: 7,
				atmoRgb: [128, 82, 62],
				ocean: [42, 46, 52],
				shallow: [58, 54, 50],
				land: [54, 48, 42],
				land2: [38, 34, 30],
				high: [78, 72, 66],
				sea: Math.max(0.28, Math.min(0.48, 0.38 + (rng() - 0.5) * 0.08)),
				ice: 0
			};
		}

		var biome = classifyBiome(data.texture, data.type);
		var avgTemp = ((data.tempMin || 0) + (data.tempMax || 0)) / 2;

		var band;
		if (biome === 'moon') { band = 'moon'; }
		else if (biome === 'gas') { band = 'gas'; }
		else { band = tempBand(avgTemp); }

		var sizeFactor = 0;
		if (data.diameter) {
			sizeFactor = Math.max(-1, Math.min(1, (data.diameter - 12000) / 12000));
		}

		var style = {
			biome: biome,
			band: band,
			seed: seed,
			sizeScale: 1 + sizeFactor * 0.08,
			baseW: Math.max(5, Math.round(7 + sizeFactor * 2)),
			lava: band === 'lava'
		};

		if (band === 'moon') {
			style.atmoRgb = [138, 143, 160];
			return style;
		}
		if (band === 'gas') {
			var gasHues = [28, 36, 200, 165, 280, 48];
			style.gasHue = gasHues[seed % gasHues.length] + (rng() - 0.5) * 16;
			style.atmoRgb = hslToRgb(style.gasHue, 0.5, 0.6);
			return style;
		}

		var def = BANDS[band];
		var hueShift = (rng() - 0.5) * 26;
		var satJ = (rng() - 0.5) * 0.12;
		var lightJ = (rng() - 0.5) * 0.08;
		var jit = function (c) {
			return hslToRgb(c[0] + hueShift, c[1] + satJ, c[2] + lightJ);
		};

		var wet = 0;
		if (biome === 'water' || biome === 'jungle') { wet = 0.07; }
		if (biome === 'desert') { wet = -0.08; }

		if (style.lava) {
			style.low = jit(def.low);
			style.land = jit(def.land);
			style.high = jit(def.high);
			style.land2 = jit(def.land2);
			style.glow = hslToRgb(def.glow[0] + hueShift * 0.3, def.glow[1], def.glow[2]);
			style.sea = -1;
			style.ice = 0;
		} else {
			style.ocean = jit(def.ocean);
			style.shallow = jit(def.shallow);
			style.land = jit(def.land);
			style.land2 = jit(def.land2);
			style.high = jit(def.high);
			style.sea = Math.max(0.12, Math.min(0.78, def.sea + (rng() - 0.5) * 0.06 + wet - sizeFactor * 0.04));
			style.ice = Math.max(0, Math.min(0.62, def.ice + (rng() - 0.5) * 0.06 + Math.max(0, (10 - (data.tempMax || 0))) / 320));
		}
		style.atmoRgb = jit(def.atmo);
		return style;
	}

	function fillUnknown(px, seed, W, H) {
		var noise = buildFbm(seed, 5, 7, 4);
		var veil = buildFbm(seed + 9191, 3, 12, 6);
		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var n = noise(u, v);
				var haze = veil(u, v);
				var shade = 14 + n * 26 + haze * 10;
				var i = (y * W + x) * 4;
				px[i] = shade * 0.92;
				px[i + 1] = shade;
				px[i + 2] = shade * 1.08 + 6;
				px[i + 3] = 255;
			}
		}
	}

	function drawDestroyedEmbers(ctx, seed, W, H) {
		var rng = makeRng(seed + 8800);
		var count = Math.floor(W / 10);
		for (var i = 0; i < count; i++) {
			var cx = rng() * W;
			var cy = H * 0.1 + rng() * H * 0.8;
			var r = 4 + rng() * (W / 28);
			var warm = rng() < 0.72;
			var g = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
			if (warm) {
				g.addColorStop(0, 'rgba(255,150,60,0.95)');
				g.addColorStop(0.45, 'rgba(220,70,30,0.55)');
			} else {
				g.addColorStop(0, 'rgba(255,210,120,0.85)');
				g.addColorStop(0.45, 'rgba(180,90,40,0.45)');
			}
			g.addColorStop(1, 'rgba(0,0,0,0)');
			ctx.fillStyle = g;
			ctx.beginPath();
			ctx.arc(cx, cy, r, 0, Math.PI * 2);
			ctx.fill();
		}
	}

	function generateSurface(style, W, H) {
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');

		if (style.band === 'unknown') {
			var imgU = ctx.createImageData(W, H);
			fillUnknown(imgU.data, style.seed, W, H);
			ctx.putImageData(imgU, 0, 0);
			return { map: new THREE.CanvasTexture(canvasEl), elev: null, sea: 0, emissive: null };
		}
		if (style.band === 'destroyed') {
			var imgD = ctx.createImageData(W, H);
			var pxD = imgD.data;
			var elevD = buildFbm(style.seed, 6, style.baseW, Math.max(2, style.baseW >> 1));
			var detailD = buildFbm(style.seed + 7919, 4, style.baseW * 2, style.baseW);
			var emCanvasD = document.createElement('canvas');
			emCanvasD.width = W;
			emCanvasD.height = H;
			var emCtxD = emCanvasD.getContext('2d');
			emCtxD.fillStyle = '#000';
			emCtxD.fillRect(0, 0, W, H);

			for (var yD = 0; yD < H; yD++) {
				var vD = yD / H;
				for (var xD = 0; xD < W; xD++) {
					var uD = xD / W;
					var eD = elevD(uD, vD) * 0.82 + detailD(uD, vD) * 0.18;
					var rD, gD, bD;
					if (eD < style.sea) {
						var shD = Math.max(0, 1 - ((style.sea - eD) / style.sea) * 3);
						rD = mix(style.ocean[0], style.shallow[0], shD);
						gD = mix(style.ocean[1], style.shallow[1], shD);
						bD = mix(style.ocean[2], style.shallow[2], shD);
					} else {
						var landTD = (eD - style.sea) / (1 - style.sea);
						rD = mix(style.land[0], style.high[0], landTD);
						gD = mix(style.land[1], style.high[1], landTD);
						bD = mix(style.land[2], style.high[2], landTD);
						var scar = Math.max(0, 1 - Math.abs(detailD(uD, vD) - 0.22) / 0.12);
						if (scar > 0) {
							rD = mix(rD, 72, scar * 0.55);
							gD = mix(gD, 48, scar * 0.55);
							bD = mix(bD, 38, scar * 0.55);
						}
					}
					var iD = (yD * W + xD) * 4;
					pxD[iD] = rD;
					pxD[iD + 1] = gD;
					pxD[iD + 2] = bD;
					pxD[iD + 3] = 255;
				}
			}
			ctx.putImageData(imgD, 0, 0);
			drawCraters(ctx, style.seed, W, H);
			drawCraters(ctx, style.seed + 1337, W, H);
			drawDestroyedEmbers(emCtxD, style.seed, W, H);
			return {
				map: new THREE.CanvasTexture(canvasEl),
				elev: elevD,
				sea: style.sea,
				emissive: new THREE.CanvasTexture(emCanvasD)
			};
		}
		if (style.band === 'moon') {
			var imgM = ctx.createImageData(W, H);
			fillMoon(imgM.data, style.seed, W, H);
			ctx.putImageData(imgM, 0, 0);
			drawCraters(ctx, style.seed, W, H);
			var moonEmissive = null;
			if (style.moonBase) {
				drawMoonCityPatch(ctx, style.moonBase, W);
				moonEmissive = generateMoonBaseLights(style.moonBase, W, H);
			}
			return { map: new THREE.CanvasTexture(canvasEl), elev: null, sea: 0, emissive: moonEmissive };
		}
		if (style.band === 'gas') {
			var imgG = ctx.createImageData(W, H);
			fillGas(imgG.data, style.seed, W, H, style.gasHue);
			ctx.putImageData(imgG, 0, 0);
			return { map: new THREE.CanvasTexture(canvasEl), elev: null, sea: 0, emissive: null };
		}

		var img = ctx.createImageData(W, H);
		var px = img.data;
		var elev = buildFbm(style.seed, 6, style.baseW, Math.max(2, style.baseW >> 1));
		var detail = buildFbm(style.seed + 7919, 4, style.baseW * 2, style.baseW);
		var veg = buildFbm(style.seed + 4242, 4, style.baseW * 2, style.baseW);

		var emCanvas = null, emCtx = null, emPx = null;
		if (style.lava) {
			emCanvas = document.createElement('canvas');
			emCanvas.width = W;
			emCanvas.height = H;
			emCtx = emCanvas.getContext('2d');
			var emImg = emCtx.createImageData(W, H);
			emPx = emImg.data;
			emCanvas._img = emImg;
		}

		for (var y = 0; y < H; y++) {
			var v = y / H;
			var lat = Math.abs(v - 0.5) * 2;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var e = elev(u, v) * 0.82 + detail(u, v) * 0.18;
				var r, g, b;

				if (style.lava) {
					r = mix(style.low[0], style.high[0], e);
					g = mix(style.low[1], style.high[1], e);
					b = mix(style.low[2], style.high[2], e);
					if (emPx) {
						var glowAmt = Math.max(0, (0.34 - e) / 0.34);
						glowAmt = glowAmt * glowAmt;
						var ei = (y * W + x) * 4;
						emPx[ei] = style.glow[0];
						emPx[ei + 1] = style.glow[1];
						emPx[ei + 2] = style.glow[2];
						emPx[ei + 3] = Math.min(255, glowAmt * 255);
					}
				} else if (e < style.sea) {
					var sh = Math.max(0, 1 - ((style.sea - e) / style.sea) * 3);
					r = mix(style.ocean[0], style.shallow[0], sh);
					g = mix(style.ocean[1], style.shallow[1], sh);
					b = mix(style.ocean[2], style.shallow[2], sh);
				} else {
					var landT = (e - style.sea) / (1 - style.sea);
					var baseR = mix(style.land[0], style.high[0], landT);
					var baseG = mix(style.land[1], style.high[1], landT);
					var baseB = mix(style.land[2], style.high[2], landT);
					var vt = Math.max(0, Math.min(1, (veg(u, v) - 0.4) / 0.4)) * 0.6;
					r = mix(baseR, style.land2[0], vt);
					g = mix(baseG, style.land2[1], vt);
					b = mix(baseB, style.land2[2], vt);
				}

				if (!style.lava && style.ice > 0) {
					var capEdge = 1 - style.ice + (detail(u, v) - 0.5) * 0.12;
					if (lat > capEdge) {
						var ct = Math.min(1, (lat - capEdge) / Math.max(0.02, style.ice * 0.6));
						r = mix(r, 244, ct);
						g = mix(g, 248, ct);
						b = mix(b, 255, ct);
					}
				}

				if (style.buildupRatio > 0) {
					var br = style.buildupRatio;
					var urbanR = 24 + detail(u, v) * 26;
					var urbanG = 26 + detail(u, v) * 24;
					var urbanB = 34 + detail(u, v) * 22;
					if (style.lava) {
						var urbanBlend = br * 0.55;
						r = mix(r, urbanR, urbanBlend);
						g = mix(g, urbanG, urbanBlend);
						b = mix(b, urbanB, urbanBlend);
						var fissure = Math.max(0, (0.42 - e) / 0.42);
						if (fissure > 0) {
							var bleed = fissure * (0.38 + br * 0.22);
							r = mix(r, style.glow[0], bleed);
							g = mix(g, style.glow[1], bleed);
							b = mix(b, style.glow[2] * 0.55, bleed);
						}
						var warmSeep = Math.max(0, 1 - Math.abs(e - 0.28) / 0.18) * br * 0.2;
						if (warmSeep > 0) {
							r = mix(r, style.glow[0], warmSeep);
							g = mix(g, style.glow[1], warmSeep);
							b = mix(b, style.glow[2] * 0.45, warmSeep);
						}
					} else {
						var onWater = e < style.sea;
						var pave = onWater ? Math.max(0, (br - 0.55) / 0.45) : br;
						r = mix(r, urbanR, pave * 0.92);
						g = mix(g, urbanG, pave * 0.92);
						b = mix(b, urbanB, pave * 0.92);
					}
				}

				var i = (y * W + x) * 4;
				px[i] = r; px[i + 1] = g; px[i + 2] = b; px[i + 3] = 255;
			}
		}

		ctx.putImageData(img, 0, 0);
		var result = { map: new THREE.CanvasTexture(canvasEl), elev: elev, sea: style.sea, emissive: null };
		if (emCanvas) {
			emCtx.putImageData(emCanvas._img, 0, 0);
			result.emissive = new THREE.CanvasTexture(emCanvas);
		}
		return result;
	}

	function fillMoon(px, seed, W, H) {
		var base = buildFbm(seed, 6, 8, 4);
		var maria = buildFbm(seed + 333, 3, 4, 2);
		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var g = base(u, v);
				var m = maria(u, v);
				var shade = 120 + g * 90;
				if (m < 0.42) { shade *= 0.6; }
				shade = Math.max(40, Math.min(220, shade));
				var i = (y * W + x) * 4;
				px[i] = shade; px[i + 1] = shade; px[i + 2] = shade * 0.98; px[i + 3] = 255;
			}
		}
	}

	function drawCraters(ctx, seed, W, H) {
		var rng = makeRng(seed + 4711);
		var count = Math.floor(W / 14);
		for (var i = 0; i < count; i++) {
			var cx = rng() * W;
			var cy = H * 0.12 + rng() * H * 0.76;
			var r = 3 + rng() * (W / 36);
			var g = ctx.createRadialGradient(cx, cy, r * 0.2, cx, cy, r);
			g.addColorStop(0, 'rgba(0,0,0,0.28)');
			g.addColorStop(0.7, 'rgba(0,0,0,0.05)');
			g.addColorStop(0.85, 'rgba(255,255,255,0.18)');
			g.addColorStop(1, 'rgba(255,255,255,0)');
			ctx.fillStyle = g;
			ctx.beginPath();
			ctx.arc(cx, cy, r, 0, Math.PI * 2);
			ctx.fill();
		}
	}

	function fillGas(px, seed, W, H, baseHue) {
		var turb = buildFbm(seed, 5, 8, 4);
		var bands = 7 + (seed % 5);
		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var warp = (turb(u, v) - 0.5) * 0.35;
				var band = Math.sin((v + warp) * Math.PI * bands) * 0.5 + 0.5;
				var rgb = hslToRgb(baseHue + (band - 0.5) * 24, 0.45, 0.4 + band * 0.3);
				var i = (y * W + x) * 4;
				px[i] = rgb[0]; px[i + 1] = rgb[1]; px[i + 2] = rgb[2]; px[i + 3] = 255;
			}
		}
	}

	function generateCityLights(elev, sea, seed, W, H, industrial) {
		if (!elev || industrial <= 0) { return null; }
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		ctx.fillStyle = '#000';
		ctx.fillRect(0, 0, W, H);

		var rng = makeRng(seed + 9001);
		var clusters = Math.min(60, Math.max(6, Math.floor(industrial * 1.4)));
		var placed = 0;
		var attempts = 0;

		while (placed < clusters && attempts < clusters * 40) {
			attempts++;
			var u = rng();
			var v = 0.12 + rng() * 0.76;
			if (elev(u, v) <= sea + 0.02) { continue; }
			placed++;
			var cx = u * W;
			var cy = v * H;
			var members = 2 + Math.floor(rng() * 5);
			for (var m = 0; m < members; m++) {
				var dx = (rng() - 0.5) * W * 0.04;
				var dy = (rng() - 0.5) * H * 0.04;
				var r = 1.5 + rng() * 3;
				var warm = rng() < 0.75;
				var g = ctx.createRadialGradient(cx + dx, cy + dy, 0, cx + dx, cy + dy, r);
				if (warm) {
					g.addColorStop(0, 'rgba(255,220,150,0.95)');
				} else {
					g.addColorStop(0, 'rgba(150,200,255,0.95)');
				}
				g.addColorStop(1, 'rgba(0,0,0,0)');
				ctx.fillStyle = g;
				ctx.beginPath();
				ctx.arc(cx + dx, cy + dy, r, 0, Math.PI * 2);
				ctx.fill();
			}
		}

		return finalizeCanvasTexture(new THREE.CanvasTexture(canvasEl));
	}

	function generateBuildupLights(elev, sea, seed, W, H, ratio) {
		if (ratio <= 0.02) {
			return null;
		}

		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		ctx.fillStyle = '#000';
		ctx.fillRect(0, 0, W, H);

		var rng = makeRng(seed + 9001);
		var mobileScale = isMobile ? 0.55 : 1;
		var weight = buildupVisualWeight(ratio);
		var pinCap = isMobile ? 1100 : 2200;
		var pinCount = Math.min(
			pinCap,
			Math.floor((120 + weight * weight * 3200) * mobileScale)
		);
		var hubCount = ratio < 0.18 ? 0 : Math.floor((ratio - 0.15) * (24 + weight * 14) * mobileScale);
		var hubs = [];
		var attempts = 0;

		while (hubs.length < hubCount && attempts < hubCount * 30) {
			attempts++;
			var hu = rng();
			var hv = 0.08 + rng() * 0.84;
			if (!isBuildableLand(elev, sea, hu, hv, ratio)) {
				continue;
			}
			hubs.push({ u: hu, v: hv, x: hu * W, y: hv * H });
		}

		for (var p = 0; p < pinCount; p++) {
			var u = rng();
			var v = 0.06 + rng() * 0.88;
			if (!isBuildableLand(elev, sea, u, v, ratio)) {
				continue;
			}
			var px = u * W;
			var py = v * H;
			var warm = rng() < (0.82 - ratio * 0.08);
			var alpha = (0.32 + weight * 0.55 + rng() * 0.15) * (0.85 + ratio * 0.15);
			var radius = (0.7 + rng() * 1.8) * (0.8 + weight * 0.85);
			drawWarmGlow(ctx, px, py, radius, alpha, warm);
		}

		for (var h = 0; h < hubs.length; h++) {
			drawHubRings(ctx, hubs[h].x, hubs[h].y, rng, ratio, W);
			var satellites = 1 + Math.floor(rng() * 2 + weight * 2);
			for (var s = 0; s < satellites; s++) {
				var sx = hubs[h].x + (rng() - 0.5) * W * 0.035;
				var sy = hubs[h].y + (rng() - 0.5) * H * 0.05;
				drawWarmGlow(ctx, sx, sy, 2 + rng() * 3, 0.42 + weight * 0.38, true);
			}
		}

		drawHubCorridors(ctx, hubs, rng, ratio, W, mobileScale);

		if (ratio > 0.68) {
			var meshLines = Math.floor((ratio - 0.62) * 55 * mobileScale * weight);
			ctx.lineCap = 'round';
			for (var m = 0; m < meshLines; m++) {
				var mu = rng();
				var mv = 0.1 + rng() * 0.8;
				if (!isBuildableLand(elev, sea, mu, mv, ratio)) {
					continue;
				}
				var mx = mu * W;
				var my = mv * H;
				var angle = rng() * Math.PI;
				var len = W * (0.02 + rng() * 0.06 * weight);
				drawGridSegment(
					ctx,
					mx - Math.cos(angle) * len,
					my - Math.sin(angle) * len * 0.35,
					mx + Math.cos(angle) * len,
					my + Math.sin(angle) * len * 0.35,
					W,
					hubCorridorAlpha(ratio) * 0.65
				);
			}
		}

		if (ratio > 0.9) {
			var fineGrid = Math.floor((ratio - 0.88) * 90 * mobileScale);
			ctx.globalAlpha = 0.12 + weight * 0.14;
			for (var g = 0; g < fineGrid; g++) {
				var gu = rng();
				var gv = 0.08 + rng() * 0.84;
				var gx = gu * W;
				var gy = gv * H;
				var gLen = 4 + rng() * 14;
				if (rng() < 0.5) {
					drawGridSegment(ctx, gx, gy, gx + gLen, gy, W, 0.28);
				} else {
					drawGridSegment(ctx, gx, gy, gx, gy + gLen * 0.5, W, 0.28);
				}
			}
			ctx.globalAlpha = 1;
		}

		return finalizeCanvasTexture(new THREE.CanvasTexture(canvasEl));
	}

	function combineEmissiveMaps(baseTex, overlayTex, W, H, baseWeight, overlayWeight) {
		var out = document.createElement('canvas');
		out.width = W;
		out.height = H;
		var ctx = out.getContext('2d');
		var read = document.createElement('canvas');
		read.width = W;
		read.height = H;
		var rctx = getCanvas2d(read, { willReadFrequently: true });
		var sources = [
			{ tex: baseTex, weight: baseWeight },
			{ tex: overlayTex, weight: overlayWeight }
		];
		var outImg = ctx.createImageData(W, H);
		var outPx = outImg.data;

		for (var s = 0; s < sources.length; s++) {
			var img = sources[s].tex && sources[s].tex.image;
			if (!img || sources[s].weight <= 0) { continue; }
			rctx.clearRect(0, 0, W, H);
			rctx.drawImage(img, 0, 0, W, H);
			var layer = rctx.getImageData(0, 0, W, H);
			var weight = sources[s].weight;
			for (var i = 0; i < outPx.length; i += 4) {
				outPx[i] = Math.min(255, outPx[i] + layer.data[i] * weight);
				outPx[i + 1] = Math.min(255, outPx[i + 1] + layer.data[i + 1] * weight);
				outPx[i + 2] = Math.min(255, outPx[i + 2] + layer.data[i + 2] * weight);
				outPx[i + 3] = 255;
			}
		}

		ctx.putImageData(outImg, 0, 0);
		return finalizeCanvasTexture(new THREE.CanvasTexture(out));
	}

	function generateCloudTexture(seed, W, H) {
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		var img = ctx.createImageData(W, H);
		var px = img.data;
		var fbm = buildFbm(seed + 1234, 5, 6, 3);
		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var c = fbm(u, v);
				var a = Math.max(0, (c - 0.55) / 0.45);
				a = a * a;
				var i = (y * W + x) * 4;
				px[i] = 255; px[i + 1] = 255; px[i + 2] = 255;
				px[i + 3] = Math.min(255, a * 235);
			}
		}
		ctx.putImageData(img, 0, 0);
		return finalizeCanvasTexture(new THREE.CanvasTexture(canvasEl), { wrapS: THREE.RepeatWrapping });
	}

	function makeGlowTexture(r, g, b) {
		var size = 64;
		var cv = document.createElement('canvas');
		cv.width = size;
		cv.height = size;
		var ctx = cv.getContext('2d');
		var grad = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
		grad.addColorStop(0, 'rgba(' + r + ',' + g + ',' + b + ',1)');
		grad.addColorStop(0.4, 'rgba(' + r + ',' + g + ',' + b + ',0.4)');
		grad.addColorStop(1, 'rgba(' + r + ',' + g + ',' + b + ',0)');
		ctx.fillStyle = grad;
		ctx.fillRect(0, 0, size, size);
		return finalizeCanvasTexture(new THREE.CanvasTexture(cv));
	}

	function makeAtmosphere(color, strength, radius, falloff, coef) {
		var segments = isMobile ? 32 : 48;
		var geo = new THREE.SphereGeometry(radius, segments, segments);
		var mat = new THREE.ShaderMaterial({
			uniforms: {
				glowColor: { value: new THREE.Color(color) },
				strength: { value: strength },
				falloff: { value: falloff },
				coef: { value: coef }
			},
			vertexShader: [
				'varying vec3 vNormal;',
				'void main() {',
				'  vNormal = normalize(normalMatrix * normal);',
				'  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);',
				'}'
			].join('\n'),
			fragmentShader: [
				'uniform vec3 glowColor;',
				'uniform float strength;',
				'uniform float falloff;',
				'uniform float coef;',
				'varying vec3 vNormal;',
				'void main() {',
				'  float intensity = pow(coef - dot(vNormal, vec3(0.0, 0.0, 1.0)), falloff);',
				'  gl_FragColor = vec4(glowColor, 1.0) * clamp(intensity, 0.0, 1.0) * strength;',
				'}'
			].join('\n'),
			side: THREE.BackSide,
			blending: THREE.AdditiveBlending,
			transparent: true,
			depthWrite: false
		});
		return new THREE.Mesh(geo, mat);
	}

	function createStarfield() {
		var count = isMobile ? 280 : 600;
		var rng = makeRng(0xBADC0DE);
		var positions = [];
		var colors = [];
		var c = new THREE.Color();
		for (var i = 0; i < count; i++) {
			var r = 30 + rng() * 25;
			var theta = rng() * Math.PI * 2;
			var phi = Math.acos(2 * rng() - 1);
			positions.push(
				r * Math.sin(phi) * Math.cos(theta),
				r * Math.sin(phi) * Math.sin(theta),
				r * Math.cos(phi)
			);
			var tint = rng();
			if (tint < 0.15) { c.setRGB(0.7, 0.8, 1.0); }
			else if (tint < 0.28) { c.setRGB(1.0, 0.85, 0.7); }
			else { c.setRGB(1.0, 1.0, 1.0); }
			var bright = 0.5 + rng() * 0.5;
			colors.push(c.r * bright, c.g * bright, c.b * bright);
		}
		var geo = new THREE.BufferGeometry();
		geo.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geo.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
		return new THREE.Points(geo, new THREE.PointsMaterial({
			size: isMobile ? 0.18 : 0.14,
			vertexColors: true,
			transparent: true,
			opacity: 0.95,
			depthWrite: false,
			sizeAttenuation: true
		}));
	}

	function latLonToVec3(lat, lon, radius) {
		var phi = (90 - lat) * Math.PI / 180;
		var theta = (lon + 180) * Math.PI / 180;
		return new THREE.Vector3(
			-radius * Math.sin(phi) * Math.cos(theta),
			radius * Math.cos(phi),
			radius * Math.sin(phi) * Math.sin(theta)
		);
	}

	// ---- orbital objects ---------------------------------------------------

	function createSatellites() {
		var count = data.fleet[212] || 0;
		if (count <= 0) { return null; }

		var group = new THREE.Group();

		var geo = new THREE.BoxGeometry(0.05, 0.004, 0.08);
		var emissiveIntensity = 0.4 + Math.min(0.8, Math.log10(count + 1) * 0.2);
		var mat = new THREE.MeshStandardMaterial({
			color: 0x9bb6d6, emissive: 0x2a4f7a,
			emissiveIntensity: emissiveIntensity, metalness: 0.7, roughness: 0.3
		});
		var dummy = new THREE.Object3D();

		var perRingCap = isMobile ? 40 : 80;
		var perRing = Math.min(perRingCap, Math.max(12, Math.floor(12 + Math.log10(count + 1) * 18)));
		var ringCount = 1;
		if (count >= 500) { ringCount = 2; }
		if (count >= 2000) { ringCount = 3; }
		if (count >= 8000) { ringCount = 4; }

		for (var r = 0; r < ringCount; r++) {
			var ringRadius = 1.38 + r * 0.08;
			var tilt = (r - (ringCount - 1) / 2) * 0.18;
			var mesh = new THREE.InstancedMesh(geo, mat, perRing);
			for (var i = 0; i < perRing; i++) {
				var angle = (i / perRing) * Math.PI * 2 + r * 0.7;
				dummy.position.set(
					Math.cos(angle) * ringRadius,
					Math.sin(angle * 0.3) * 0.05 + Math.sin(tilt) * 0.12,
					Math.sin(angle) * ringRadius
				);
				dummy.lookAt(0, 0, 0);
				dummy.updateMatrix();
				mesh.setMatrixAt(i, dummy.matrix);
			}
			mesh.instanceMatrix.needsUpdate = true;
			mesh.rotation.x = tilt;
			group.add(mesh);
		}

		if (count >= 200) {
			var haloStrength = Math.min(1, Math.log10(count) / 4);
			var haloGeo = new THREE.RingGeometry(1.34, 1.58 + ringCount * 0.06, 64);
			var haloMat = new THREE.MeshBasicMaterial({
				color: 0x6aa8d8,
				transparent: true,
				opacity: 0.08 + haloStrength * 0.22,
				depthWrite: false,
				side: THREE.DoubleSide
			});
			var halo = new THREE.Mesh(haloGeo, haloMat);
			halo.rotation.x = Math.PI / 2;
			group.add(halo);
			group.userData.halo = halo;
			group.userData.haloBase = haloMat.opacity;
		}

		group.userData.ringCount = ringCount;
		return group;
	}

	function buildMoonTexture(seed) {
		var W = 512;
		var H = 256;
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		var img = ctx.createImageData(W, H);
		var px = img.data;
		var base = buildFbm(seed, 6, 8, 4);
		var maria = buildFbm(seed + 333, 3, 4, 2);
		var fine = buildFbm(seed + 777, 4, 16, 8);

		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var g = base(u, v);
				var m = maria(u, v);
				var f = fine(u, v);
				var shade = 148 + g * 55 + f * 18;
				if (m < 0.44) {
					shade *= 0.72;
				}
				shade = Math.max(70, Math.min(230, shade));
				var i = (y * W + x) * 4;
				px[i] = shade;
				px[i + 1] = shade;
				px[i + 2] = shade * 0.97;
				px[i + 3] = 255;
			}
		}
		ctx.putImageData(img, 0, 0);
		drawCraters(ctx, seed, W, H);

		var tex = new THREE.CanvasTexture(canvasEl);
		tex.encoding = THREE.sRGBEncoding;
		return tex;
	}

	function buildMoonBump(seed) {
		var W = 256;
		var H = 128;
		var canvasEl = document.createElement('canvas');
		canvasEl.width = W;
		canvasEl.height = H;
		var ctx = canvasEl.getContext('2d');
		var img = ctx.createImageData(W, H);
		var px = img.data;
		var noise = buildFbm(seed + 9000, 5, 8, 4);
		for (var y = 0; y < H; y++) {
			var v = y / H;
			for (var x = 0; x < W; x++) {
				var u = x / W;
				var b = Math.floor(noise(u, v) * 255);
				var i = (y * W + x) * 4;
				px[i] = b;
				px[i + 1] = b;
				px[i + 2] = b;
				px[i + 3] = 255;
			}
		}
		ctx.putImageData(img, 0, 0);
		return new THREE.CanvasTexture(canvasEl);
	}

	function applyMoonMaterial(moonMesh, seed) {
		var bump = buildMoonBump(seed);
		moonMesh.material = new THREE.MeshStandardMaterial({
			map: buildMoonTexture(seed),
			bumpMap: bump,
			bumpScale: 0.04,
			roughness: 0.95,
			metalness: 0.0,
			emissive: new THREE.Color(0x222222),
			emissiveIntensity: 0.35
		});
	}

	function cameraZForExtent(extent, padding) {
		var fovRad = (camera.fov * Math.PI) / 180;
		return (extent * (padding || 1.24)) / Math.tan(fovRad / 2);
	}

	function framePlanetAnchorContent(padding) {
		if (!planetAnchor || !camera || !planetMesh) {
			return;
		}
		planetAnchor.position.set(0, 0, 0);
		planetAnchor.updateMatrixWorld(true);

		// Frame the planet body + atmosphere; moon, debris, and satellite rings may clip.
		var visualRadius = PLANET_FRAME_RADIUS;
		if (planetSystem && planetSystem.scale) {
			visualRadius = planetSystem.scale.x * PLANET_FRAME_RADIUS;
		}

		baseCameraZ = cameraZForExtent(visualRadius, padding || DEFAULT_FRAME_PADDING);
		camera.position.set(0, 0, baseCameraZ * zoomFactor);
		camera.lookAt(0, 0, 0);
	}

	function computeSceneExtent(style, moonOrbit, hasDebris) {
		var scale = style.sizeScale;
		var extent = scale * 1.14;

		if (hasDebris) {
			extent = Math.max(extent, 1.56 + 0.44);
		}
		if (moonOrbit && moonOrbit.userData.extent) {
			extent = Math.max(extent, moonOrbit.userData.extent);
		}

		return extent;
	}

	function createMoonOrbit(moonData, style) {
		var orbit = new THREE.Group();
		var seed = hashString('moon:' + moonData.id);
		var scale = style.sizeScale;
		var planetR = scale;

		var planetD = Math.max(8000, data.diameter || 12800);
		var moonD = Math.max(2000, moonData.diameter || 7000);
		var sizeRatio = moonD / planetD;
		var moonRadius = Math.max(0.16, Math.min(0.24, sizeRatio * 0.55)) * scale;
		var orbitDist = planetR + moonRadius + scale * 0.36;

		var moon = new THREE.Mesh(
			new THREE.SphereGeometry(moonRadius, isMobile ? 32 : 48, isMobile ? 32 : 48),
			new THREE.MeshStandardMaterial({ color: 0xd0d0d0, roughness: 0.95, metalness: 0.0 })
		);

		var arm = new THREE.Group();
		moon.position.set(orbitDist, 0, 0);
		arm.add(moon);
		orbit.add(arm);

		orbit.rotation.x = 0.42;
		orbit.rotation.z = 0.1;
		// Start with the moon in a visible quadrant instead of off-screen.
		orbit.rotation.y = Math.PI * 0.28;
		orbit.userData.orbitSpeed = -0.016;
		orbit.userData.moonMesh = moon;
		orbit.userData.extent = orbitDist + moonRadius;
		orbit.renderOrder = 10;
		moon.renderOrder = 10;

		applyMoonMaterial(moon, seed);

		return orbit;
	}

	function createDebrisField(debris, seed) {
		var total = (debris.metal || 0) + (debris.crystal || 0);
		if (total <= 0) {
			return null;
		}

		var group = new THREE.Group();
		var rng = makeRng(seed + 5555);
		var count = Math.min(isMobile ? 45 : 110, Math.max(10, Math.floor(10 + Math.log10(total + 1) * 24)));
		var geo = new THREE.BoxGeometry(0.022, 0.016, 0.028);
		var mat = new THREE.MeshStandardMaterial({
			color: 0x9a8068,
			emissive: 0x4a3520,
			emissiveIntensity: 0.15 + Math.min(0.35, Math.log10(total + 1) * 0.08),
			metalness: 0.55,
			roughness: 0.85
		});
		var mesh = new THREE.InstancedMesh(geo, mat, count);
		var dummy = new THREE.Object3D();
		var innerR = 1.52;
		var spread = 0.38 + Math.min(0.25, Math.log10(total + 1) * 0.06);

		for (var i = 0; i < count; i++) {
			var angle = rng() * Math.PI * 2;
			var r = innerR + rng() * spread;
			var y = (rng() - 0.5) * 0.42;
			dummy.position.set(Math.cos(angle) * r, y, Math.sin(angle) * r);
			dummy.rotation.set(rng() * Math.PI, rng() * Math.PI, rng() * Math.PI);
			var s = 0.5 + rng() * 1.4;
			dummy.scale.set(s, s * (0.6 + rng() * 0.8), s);
			dummy.updateMatrix();
			mesh.setMatrixAt(i, dummy.matrix);
		}
		mesh.instanceMatrix.needsUpdate = true;
		group.add(mesh);

		group.rotation.x = -0.22;
		group.userData.orbitSpeed = 0.01;
		group.userData.tumbleSpeed = 0.04;
		group.userData.debrisMesh = mesh;
		return group;
	}

	function createShipTraffic() {
		var totalShips = sumFleetExcludingSatellites(data.fleet);
		if (totalShips <= 0) { return null; }
		var shipCount = Math.min(isMobile ? 8 : 18, Math.max(4, Math.floor(Math.log10(totalShips + 1) * 5)));
		var group = new THREE.Group();
		var cargoMat = new THREE.SpriteMaterial({ map: makeGlowTexture(90, 200, 255), transparent: true, depthWrite: false });
		var combatMat = new THREE.SpriteMaterial({ map: makeGlowTexture(255, 95, 75), transparent: true, depthWrite: false });
		for (var i = 0; i < shipCount; i++) {
			var sprite = new THREE.Sprite(Math.random() < 0.4 ? combatMat : cargoMat);
			sprite.scale.set(0.06, 0.06, 1);
			sprite.userData = {
				orbitRadius: 1.2 + Math.random() * 0.3,
				orbitTilt: (Math.random() - 0.5) * 1.4,
				orbitPhase: Math.random() * Math.PI * 2,
				orbitSpeed: 0.012 + Math.random() * 0.02
			};
			group.add(sprite);
		}
		return group;
	}

	function createDefensePlatforms() {
		var silo = data.buildings[44] || 0;
		var shield = data.buildings[42] || 0;
		if (silo === 0 && shield === 0) { return null; }
		var platformCount = Math.min(3, (silo > 0 ? 1 : 0) + (shield > 0 ? 2 : 0));
		var group = new THREE.Group();
		var geo = new THREE.SphereGeometry(0.03, 10, 10);
		var mat = new THREE.MeshStandardMaterial({
			color: 0x7a7a8c, emissive: 0x3a3a52, emissiveIntensity: 0.6, metalness: 0.85, roughness: 0.4
		});
		for (var i = 0; i < platformCount; i++) {
			var mesh = new THREE.Mesh(geo, mat);
			var angle = (i / platformCount) * Math.PI * 2 + 0.5;
			mesh.position.set(Math.cos(angle) * 1.24, 0.06 * i, Math.sin(angle) * 1.24);
			group.add(mesh);
		}
		return group;
	}

	function createConstructionBeam() {
		if (!data.queue || (!data.queue.building && !data.queue.hangar)) { return null; }
		var group = new THREE.Group();
		var surfacePoint = latLonToVec3(20, 45, 1.0);
		var orbitPoint = surfacePoint.clone().normalize().multiplyScalar(1.55);
		var beamGeo = new THREE.BufferGeometry().setFromPoints([orbitPoint, surfacePoint]);
		var beamMat = new THREE.LineBasicMaterial({
			color: data.queue.hangar ? 0x32d2ff : 0xffa032, transparent: true, opacity: 0.6, depthWrite: false
		});
		group.add(new THREE.Line(beamGeo, beamMat));
		var glowTex = makeGlowTexture(
			data.queue.hangar ? 50 : 255, data.queue.hangar ? 210 : 160, data.queue.hangar ? 255 : 50
		);
		var glow = new THREE.Sprite(new THREE.SpriteMaterial({ map: glowTex, transparent: true, depthWrite: false }));
		glow.position.copy(surfacePoint);
		glow.scale.set(0.16, 0.16, 1);
		group.add(glow);
		group.userData.glow = glow;
		return group;
	}

	// ---- scene -------------------------------------------------------------

	function measureSize() {
		if (viewerOptions.fixedSize > 0) {
			return viewerOptions.fixedSize;
		}
		var wrap = canvas.parentElement;
		if (!wrap) {
			return 280;
		}
		var rect = wrap.getBoundingClientRect();
		var cap = 280;
		// Always render a square buffer so the planet stays a perfect sphere.
		var side = Math.max(120, Math.round(Math.min(rect.width, rect.height || rect.width, cap)));
		return side;
	}

	function applySize() {
		var size = measureSize();
		canvas.style.width = '100%';
		canvas.style.height = '100%';
		renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, dprCap));
		renderer.setSize(size, size, false);
		camera.aspect = 1;
		camera.updateProjectionMatrix();
	}

	function createUnknownScanEffect() {
		var group = new THREE.Group();
		var ringMat = new THREE.MeshBasicMaterial({
			color: 0x7ec8ff,
			transparent: true,
			opacity: 0.62,
			side: THREE.DoubleSide,
			depthWrite: false,
			blending: THREE.AdditiveBlending
		});
		var scanArc = new THREE.Mesh(
			new THREE.RingGeometry(1.06, 1.11, 96, 1, 0, Math.PI * 0.55),
			ringMat
		);
		scanArc.rotation.x = Math.PI / 2;
		group.add(scanArc);

		var pingMat = ringMat.clone();
		pingMat.opacity = 0.35;
		var ping = new THREE.Mesh(new THREE.RingGeometry(1.03, 1.035, 64), pingMat);
		ping.rotation.x = Math.PI / 2;
		ping.userData.phase = 0;
		group.add(ping);

		var arc2 = new THREE.Mesh(
			new THREE.RingGeometry(1.14, 1.17, 64, 1, Math.PI * 0.2, Math.PI * 0.35),
			ringMat.clone()
		);
		arc2.material.opacity = 0.28;
		arc2.rotation.x = Math.PI / 2;
		arc2.rotation.z = Math.PI * 0.35;
		group.add(arc2);

		group.userData.scanArc = scanArc;
		group.userData.pingRing = ping;
		group.userData.tiltArc = arc2;
		return group;
	}

	function buildScene() {
		var style = computeStyle();
		var vizState = getVizState();
		var buildupRatio = getBuildupRatio();
		if (buildupRatio !== null) {
			style.buildupRatio = buildupRatio;
		}
		var texW = viewerOptions.lite ? 384 : (isMobile ? 512 : 1024);
		var texH = texW / 2;
		var detail = viewerOptions.lite ? 32 : (isMobile ? 48 : 64);

		var moonBaseLevel = getBuildingLevel(41);
		if (isMoonBody() && moonBaseLevel > 0) {
			style.moonBase = getMoonBasePlacement(moonBaseLevel);
		}

		var surface = generateSurface(style, texW, texH);
		var surfaceTex = surface.map;
		finalizeCanvasTexture(surfaceTex, { mipmap: true });
		if (renderer.capabilities.getMaxAnisotropy) {
			surfaceTex.anisotropy = Math.min(4, renderer.capabilities.getMaxAnisotropy());
		}

		var matOpts = { map: surfaceTex, roughness: 0.95, metalness: 0.02 };
		var useBuildup = buildupRatio !== null && buildupRatio > 0.02
			&& style.band !== 'unknown' && style.band !== 'destroyed';
		var nightOverlayTex = null;
		var nightOverlayOpts = null;

		if (style.band === 'unknown') {
			matOpts.roughness = 1;
			matOpts.metalness = 0;
		} else if (style.band === 'destroyed' && surface.emissive) {
			finalizeCanvasTexture(surface.emissive);
			matOpts.emissiveMap = surface.emissive;
			matOpts.emissive = new THREE.Color(0xffffff);
			matOpts.emissiveIntensity = 0.95;
			matOpts.roughness = 0.98;
			matOpts.metalness = 0.04;
		} else if (style.moonBase && surface.emissive) {
			finalizeCanvasTexture(surface.emissive);
			nightOverlayTex = surface.emissive;
			nightOverlayOpts = { intensity: 1.0 + style.moonBase.tier * 0.14 };
			matOpts.metalness = 0.3;
			matOpts.roughness = 0.78;
		} else if (useBuildup && style.band !== 'moon' && style.band !== 'gas') {
			var buildupLights = generateBuildupLights(surface.elev, surface.sea, style.seed, texW, texH, buildupRatio);
			if (buildupLights) {
				finalizeCanvasTexture(buildupLights);
				if (style.lava && surface.emissive) {
					finalizeCanvasTexture(surface.emissive);
					var lavaWeight = Math.max(0.42, 1 - buildupRatio * 0.58);
					matOpts.emissiveMap = surface.emissive;
					matOpts.emissive = new THREE.Color(0xffffff);
					matOpts.emissiveIntensity = 1.25 + buildupRatio * 1.55 + lavaWeight * 0.65;
					nightOverlayTex = buildupLights;
					nightOverlayOpts = {
						intensity: 0.95 + buildupRatio * 1.85,
						terminator: 0.42,
						dayBleed: 0.08,
						nightSurfaceGlow: 0
					};
				} else {
					nightOverlayTex = buildupLights;
					nightOverlayOpts = { intensity: 1.05 + buildupRatio * 1.75 };
				}
				matOpts.metalness = 0.08 + buildupRatio * 0.22;
				matOpts.roughness = 0.92 - buildupRatio * 0.18;
			}
		} else if (style.lava && surface.emissive) {
			finalizeCanvasTexture(surface.emissive);
			matOpts.emissiveMap = surface.emissive;
			matOpts.emissive = new THREE.Color(0xffffff);
			matOpts.emissiveIntensity = 1.5;
		} else if (style.band !== 'moon' && style.band !== 'gas'
			&& style.band !== 'unknown' && style.band !== 'destroyed') {
			var industrial = sumLevels(data.buildings, [1, 2, 3, 14]) + (data.buildings[33] || 0);
			var lightsTex = generateCityLights(surface.elev, surface.sea, style.seed, texW, texH, industrial);
			if (lightsTex) {
				finalizeCanvasTexture(lightsTex);
				nightOverlayTex = lightsTex;
				nightOverlayOpts = { intensity: 1.0 };
			}
		}

		var planetGeo = new THREE.SphereGeometry(1, detail, detail);
		planetMesh = new THREE.Mesh(planetGeo, new THREE.MeshStandardMaterial(matOpts));
		if (nightOverlayTex) {
			attachNightEmissiveOverlay(planetGeo, nightOverlayTex, surfaceTex, nightOverlayOpts);
		}
		if (style.band === 'destroyed') {
			planetMesh.userData.vizState = 'destroyed';
		}
		planetSystem.add(planetMesh);
		planetSystem.scale.setScalar(style.sizeScale);

		var atmoColor = new THREE.Color(style.atmoRgb[0] / 255, style.atmoRgb[1] / 255, style.atmoRgb[2] / 255);
		var atmoStrength = style.band === 'unknown' ? 0.55
			: (style.band === 'destroyed' ? 0.85
				: (style.band === 'moon' ? 0.6 : (style.band === 'gas' ? 1.4 : 1.7)));
		atmosphereMesh = makeAtmosphere(atmoColor, atmoStrength, 1.04, 7.0, 0.62);
		if (style.band === 'unknown') {
			atmosphereMesh.userData.vizState = 'unknown';
			atmosphereMesh.userData.baseStrength = atmoStrength;
		}
		planetSystem.add(atmosphereMesh);
		if (style.band !== 'moon' && style.band !== 'unknown') {
			var outerStrength = style.band === 'destroyed' ? 0.22
				: (style.band === 'gas' ? 0.5 : 0.45);
			planetSystem.add(makeAtmosphere(atmoColor, outerStrength, 1.16, 2.2, 0.8));
		}

		if (style.band === 'unknown') {
			unknownScanGroup = createUnknownScanEffect();
			planetSystem.add(unknownScanGroup);
		}

		if (style.band !== 'moon' && style.band !== 'gas' && !style.lava
			&& style.band !== 'unknown' && style.band !== 'destroyed') {
			var cloudOpacity = 0.85;
			if (buildupRatio !== null && buildupRatio > 0.55) {
				cloudOpacity = 0.85 * (1 - Math.min(1, (buildupRatio - 0.55) / 0.45));
			}
			if (cloudOpacity > 0.04) {
				var cloudTex = generateCloudTexture(style.seed, texW, texH);
				var cloudMat = new THREE.MeshStandardMaterial({
					map: cloudTex, transparent: true, opacity: cloudOpacity, depthWrite: false, roughness: 1, metalness: 0
				});
				cloudMesh = new THREE.Mesh(new THREE.SphereGeometry(1.012, detail, detail), cloudMat);
				planetSystem.add(cloudMesh);
			}
		}

		var hideActivity = vizState === 'unknown' || vizState === 'destroyed';

		if (data.type === 1 && data.moon && vizState !== 'unknown') {
			moonOrbitGroup = createMoonOrbit(data.moon, style);
			planetAnchor.add(moonOrbitGroup);
		}

		if (!viewerOptions.lite && !hideActivity) {
			satelliteMesh = createSatellites();
			if (satelliteMesh) { planetSystem.add(satelliteMesh); }

			shipGroup = createShipTraffic();
			if (shipGroup) { planetSystem.add(shipGroup); }

			var defenseGroup = createDefensePlatforms();
			if (defenseGroup) { planetSystem.add(defenseGroup); }

			constructionBeam = createConstructionBeam();
			if (constructionBeam) { planetSystem.add(constructionBeam); }
		}

		if (data.type === 1 && data.debris && vizState !== 'unknown') {
			debrisOrbitGroup = createDebrisField(data.debris, style.seed);
			if (debrisOrbitGroup) {
				planetAnchor.add(debrisOrbitGroup);
			}
		}

		var hasDebris = !!(data.type === 1 && data.debris && debrisOrbitGroup);
		framePlanetAnchorContent(DEFAULT_FRAME_PADDING);
		zoomFactor = 1;
		applyCameraZoom();

		markReady();
		if (!viewerOptions.preview) {
			createZoomControls();
			observeViewport();
		}
		if (animating) {
			clock.start();
			startAnimationLoop();
		} else {
			renderer.render(scene, camera);
		}
	}

	function resetSceneState() {
		planetAnchor = null;
		planetSystem = null;
		planetMesh = null;
		cloudMesh = null;
		atmosphereMesh = null;
		satelliteMesh = null;
		shipGroup = null;
		constructionBeam = null;
		starField = null;
		moonOrbitGroup = null;
		debrisOrbitGroup = null;
		unknownScanGroup = null;
		nightEmissiveMesh = null;
		nightEmissiveUniforms = null;
		sceneSunLight = null;
		zoomControlsEl = null;
		zoomFactor = 1;
	}

	function bootViewer(targetCanvas, planetData, options) {
		options = options || {};
		viewerOptions = {
			enableZoom: options.enableZoom !== false,
			fixedSize: options.fixedSize || 0,
			preview: !!options.preview,
			lite: !!options.lite,
			staticExport: !!options.staticExport
		};

		canvas = targetCanvas;
		data = planetData;
		fallbackImg = options.fallbackImg || null;
		reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		isMobile = viewerOptions.lite || window.innerWidth <= 699;
		dprCap = viewerOptions.lite ? 1.25 : (isMobile ? 1.5 : 2);
		animating = options.staticExport ? false : !reducedMotion;
		viewportVisible = true;
		clock = new THREE.Clock();

		var reusePreview = viewerOptions.preview
			&& !viewerOptions.staticExport
			&& previewRenderer
			&& previewCanvasEl === targetCanvas;

		if (reusePreview) {
			stopAnimationLoop();
			if (scene) {
				disposeObject3D(scene);
			}
			resetSceneState();
		} else if (viewerOptions.preview && previewRenderer) {
			previewRenderer.dispose();
			previewRenderer = null;
			previewCanvasEl = null;
		}

		scene = new THREE.Scene();
		scene.background = new THREE.Color(0x05060e);
		camera = new THREE.PerspectiveCamera(42, 1, 0.1, 200);
		camera.position.z = 3.4;

		var needsCaptureBuffer = viewerOptions.preview || viewerOptions.staticExport;
		if (reusePreview) {
			renderer = previewRenderer;
		} else {
			try {
				renderer = new THREE.WebGLRenderer({
					canvas: canvas,
					alpha: !viewerOptions.staticExport,
					antialias: !isMobile,
					preserveDrawingBuffer: needsCaptureBuffer,
					powerPreference: isMobile ? 'low-power' : 'default'
				});
			} catch (err) {
				if (viewerOptions.preview) {
					return false;
				}
				showFallback();
				return false;
			}
			if (viewerOptions.preview) {
				previewRenderer = renderer;
				previewCanvasEl = canvas;
			}
		}
		if (renderer.outputEncoding !== undefined) {
			renderer.outputEncoding = THREE.sRGBEncoding;
		}

		if (!viewerOptions.preview) {
			canvas.addEventListener('webglcontextlost', function (e) {
				e.preventDefault();
				stopAnimationLoop();
				showFallback();
			}, false);
		}

		applySize();

		starField = createStarfield();
		scene.add(starField);

		planetAnchor = new THREE.Group();
		scene.add(planetAnchor);

		planetSystem = new THREE.Group();
		planetSystem.rotation.z = AXIAL_TILT;
		planetAnchor.add(planetSystem);

		scene.add(new THREE.AmbientLight(0x33405c, 0.45));
		var sunLight = new THREE.DirectionalLight(0xfff4e6, 1.45);
		sunLight.position.set(3.5, 1.4, 2.6);
		scene.add(sunLight);
		sceneSunLight = sunLight;
		var rimLight = new THREE.DirectionalLight(0x4a78c8, 0.35);
		rimLight.position.set(-3, -1, -2.5);
		scene.add(rimLight);

		buildScene();
		return true;
	}

	function mountPreview(targetCanvas, planetData, options) {
		if (typeof THREE === 'undefined') {
			return false;
		}
		unmountPreview();
		options = options || {};
		var ok = bootViewer(targetCanvas, planetData, {
			enableZoom: false,
			fixedSize: options.size || 96,
			preview: true,
			lite: true
		});
		return ok;
	}

	function renderStatic(targetCanvas, planetData, options) {
		if (typeof THREE === 'undefined') {
			return Promise.reject(new Error('THREE unavailable'));
		}
		unmountPreview();
		options = options || {};
		var lite = options.lite !== false;
		var size = options.size || 256;
		var ok = bootViewer(targetCanvas, planetData, {
			enableZoom: false,
			fixedSize: size,
			preview: true,
			lite: lite,
			staticExport: true
		});
		if (!ok) {
			return Promise.reject(new Error('WebGL boot failed'));
		}
		return new Promise(function (resolve, reject) {
			var frames = 0;
			function finish() {
				try {
					if (renderer && scene && camera) {
						renderer.render(scene, camera);
					}
					var dataUrl = captureStaticDataUrl(targetCanvas, 'image/jpeg', 0.92);
					if (!dataUrl || dataUrl.length < 500) {
						reject(new Error('Static capture produced empty canvas'));
						return;
					}
					window.__planetExportDataUrl = dataUrl;
					window.__planetExportDone = true;
					document.title = 'ready';
					window.dispatchEvent(new CustomEvent('hiveNovaPlanetReady', {
						detail: { canvas: targetCanvas, mode: lite ? 'lite' : 'full' }
					}));
					resolve({ canvas: targetCanvas, mode: lite ? 'lite' : 'full', dataUrl: dataUrl });
				} catch (err) {
					reject(err);
				}
			}
			function waitFrame() {
				frames += 1;
				if (renderer && scene && camera) {
					renderer.render(scene, camera);
				}
				if (frames < 4) {
					requestAnimationFrame(waitFrame);
					return;
				}
				finish();
			}
			requestAnimationFrame(waitFrame);
		});
	}

	function captureStaticDataUrl(targetCanvas, mimeType, quality) {
		if (!targetCanvas) {
			return null;
		}
		mimeType = mimeType || 'image/jpeg';
		if (mimeType === 'image/jpeg') {
			return targetCanvas.toDataURL(mimeType, quality || 0.92);
		}
		return targetCanvas.toDataURL(mimeType);
	}

	function unmountPreview() {
		if (!viewerOptions.preview) {
			return;
		}
		stopAnimationLoop();
		if (intersectionObserver) {
			intersectionObserver.disconnect();
			intersectionObserver = null;
		}
		if (scene) {
			disposeObject3D(scene);
			scene = null;
		}
		resetSceneState();
		camera = null;
		canvas = null;
		data = null;
		fallbackImg = null;
		viewerOptions = {
			enableZoom: true,
			fixedSize: 0,
			preview: false,
			lite: false
		};
	}

	function init() {
		dataEl = document.getElementById('overview-planet-data');
		canvas = document.getElementById('overview-planet-canvas');
		fallbackImg = document.querySelector('.overview-planet-fallback');

		if (!dataEl || !canvas || typeof THREE === 'undefined') {
			showFallback();
			return;
		}

		try {
			data = JSON.parse(dataEl.textContent);
		} catch (e) {
			showFallback();
			return;
		}

		markLoading(true);
		overviewMounted = true;

		window.addEventListener('resize', onResize);

		requestAnimationFrame(function () {
			try {
				bootViewer(canvas, data, {
					enableZoom: true,
					preview: false,
					lite: window.innerWidth <= 699,
					fallbackImg: fallbackImg
				});
			} catch (err) {
				showFallback();
			}
		});
	}

	function onResize() {
		if (!renderer || viewerOptions.preview) { return; }
		var wasMobile = isMobile;
		isMobile = window.innerWidth <= 699;
		applySize();
		if (wasMobile !== isMobile || !animating) {
			renderer.render(scene, camera);
		}
	}

	function updateShips(t) {
		if (!shipGroup) { return; }
		shipGroup.children.forEach(function (sprite) {
			var ud = sprite.userData;
			var angle = ud.orbitPhase + t * ud.orbitSpeed;
			sprite.position.set(
				Math.cos(angle) * ud.orbitRadius,
				Math.sin(angle * 2 + ud.orbitTilt) * 0.16,
				Math.sin(angle) * ud.orbitRadius
			);
		});
	}

	function animate() {
		if (!shouldAnimate()) {
			rafId = null;
			return;
		}
		rafId = requestAnimationFrame(animate);

		var dt = Math.min(clock.getDelta(), 0.1);
		var t = clock.getElapsedTime();

		if (planetMesh) { planetMesh.rotation.y += PLANET_SPIN * dt; }
		if (cloudMesh) { cloudMesh.rotation.y += CLOUD_SPIN * dt; }
		if (starField) { starField.rotation.y += 0.004 * dt; }
		if (satelliteMesh) {
			if (satelliteMesh.userData.orbitSpeed) {
				satelliteMesh.rotation.y += satelliteMesh.userData.orbitSpeed * dt;
			} else {
				satelliteMesh.rotation.y += SATELLITE_SPIN * dt;
			}
			if (satelliteMesh.userData.halo) {
				var base = satelliteMesh.userData.haloBase || 0.2;
				satelliteMesh.userData.halo.material.opacity = base + Math.sin(t * 1.2) * 0.04;
			}
		}
		if (moonOrbitGroup) {
			moonOrbitGroup.rotation.y += moonOrbitGroup.userData.orbitSpeed * dt;
			if (moonOrbitGroup.userData.moonMesh) {
				moonOrbitGroup.userData.moonMesh.rotation.y += PLANET_SPIN * dt * 0.6;
			}
		}
		if (debrisOrbitGroup) {
			debrisOrbitGroup.rotation.y += debrisOrbitGroup.userData.orbitSpeed * dt;
			if (debrisOrbitGroup.userData.debrisMesh) {
				debrisOrbitGroup.userData.debrisMesh.rotation.x += debrisOrbitGroup.userData.tumbleSpeed * dt;
				debrisOrbitGroup.userData.debrisMesh.rotation.z += debrisOrbitGroup.userData.tumbleSpeed * dt * 0.7;
			}
		}
		if (constructionBeam && constructionBeam.userData.glow) {
			var pulse = 0.15 + Math.sin(t * 3) * 0.04;
			constructionBeam.userData.glow.scale.set(pulse, pulse, 1);
		}
		if (unknownScanGroup) {
			unknownScanGroup.rotation.y += 1.8 * dt;
			if (unknownScanGroup.userData.tiltArc) {
				unknownScanGroup.userData.tiltArc.rotation.y -= 0.9 * dt;
			}
			var pingRing = unknownScanGroup.userData.pingRing;
			if (pingRing) {
				pingRing.userData.phase = (pingRing.userData.phase || 0) + dt * 0.45;
				var pingT = pingRing.userData.phase % 1;
				pingRing.scale.setScalar(1 + pingT * 0.35);
				pingRing.material.opacity = 0.42 * (1 - pingT);
			}
		}
		if (atmosphereMesh && atmosphereMesh.userData.vizState === 'unknown') {
			var baseAtmo = atmosphereMesh.userData.baseStrength || 0.55;
			atmosphereMesh.material.uniforms.strength.value = baseAtmo + Math.sin(t * 2.2) * 0.12;
		}
		if (planetMesh && planetMesh.userData.vizState === 'destroyed' && planetMesh.material.emissiveIntensity !== undefined) {
			planetMesh.material.emissiveIntensity = 0.88 + Math.sin(t * 1.5) * 0.14;
		}
		if (nightEmissiveUniforms && sceneSunLight) {
			nightEmissiveUniforms.uSunDirection.value.copy(sceneSunLight.position).normalize();
		}

		updateShips(t);
		renderer.render(scene, camera);
	}

	function dispose() {
		if (viewerOptions.preview) {
			unmountPreview();
			return;
		}
		if (!overviewMounted) {
			return;
		}
		stopAnimationLoop();
		window.removeEventListener('resize', onResize);
		if (intersectionObserver) {
			intersectionObserver.disconnect();
			intersectionObserver = null;
		}
		if (scene) {
			disposeObject3D(scene);
		}
		if (renderer) {
			renderer.dispose();
			renderer = null;
		}
		if (previewRenderer) {
			previewRenderer.dispose();
			previewRenderer = null;
			previewCanvasEl = null;
		}
		resetSceneState();
		scene = null;
		camera = null;
		overviewMounted = false;
	}

	document.addEventListener('visibilitychange', function () {
		if (viewerOptions.preview) {
			return;
		}
		if (document.hidden) {
			stopAnimationLoop();
		} else if (planetMesh) {
			if (animating) {
				startAnimationLoop();
			} else if (renderer && scene) {
				renderer.render(scene, camera);
			}
		}
	});

	window.HiveNovaOverviewPlanet = {
		mountPreview: mountPreview,
		unmountPreview: unmountPreview,
		renderStatic: renderStatic,
		captureStaticDataUrl: captureStaticDataUrl
	};

	window.addEventListener('beforeunload', dispose);

	if (document.documentElement.getAttribute('data-planet-export') === '1') {
		return;
	}

	if (document.getElementById('overview-planet-data') && document.getElementById('overview-planet-canvas')) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	}
})();
