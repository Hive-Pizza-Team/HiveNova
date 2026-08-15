#!/usr/bin/env node
/**
 * Batch-render static planet JPGs from planet-viz-export.html via Puppeteer.
 * Includes special states unknown + destroyed and moon variants (see planet-image-catalog.js).
 * Usage: node scripts/dev/render-planet-images.mjs [--port 8765] [--textures a,b] [--jobs 6]
 */
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
const catalog = require('./planet-image-catalog.js');

const ROOT = path.resolve(__dirname, '../..');
const OUT_DIR = path.join(ROOT, 'styles/theme/hive/planeten');
const LITE_SIZE = 256;
const FULL_SIZE = 512;
const JPEG_QUALITY = 0.96;
const DEFAULT_JOBS = Math.min(6, Math.max(2, os.cpus()?.length || 4));

function parseArgs(argv) {
	const opts = { port: 8765, textures: null, jobs: DEFAULT_JOBS };
	for (let i = 2; i < argv.length; i++) {
		if (argv[i] === '--port' && argv[i + 1]) {
			opts.port = parseInt(argv[++i], 10);
		} else if (argv[i] === '--textures' && argv[i + 1]) {
			opts.textures = argv[++i].split(',').map((s) => s.trim()).filter(Boolean);
		} else if (argv[i] === '--jobs' && argv[i + 1]) {
			opts.jobs = Math.max(1, Math.min(16, parseInt(argv[++i], 10) || DEFAULT_JOBS));
		}
	}
	if (process.env.PLANET_RENDER_JOBS) {
		const envJobs = parseInt(process.env.PLANET_RENDER_JOBS, 10);
		if (envJobs > 0) {
			opts.jobs = Math.min(16, envJobs);
		}
	}
	return opts;
}

function outputPath(texture, mode) {
	if (mode === 'full') {
		return path.join(OUT_DIR, texture + '_hq.jpg');
	}
	return path.join(OUT_DIR, texture + '.jpg');
}

function dataUrlToBuffer(dataUrl) {
	const comma = dataUrl.indexOf(',');
	if (comma === -1) {
		throw new Error('Invalid data URL');
	}
	return Buffer.from(dataUrl.slice(comma + 1), 'base64');
}

async function renderOne(page, baseUrl, entry, mode, size) {
	const url = baseUrl
		+ '?texture=' + encodeURIComponent(entry.texture)
		+ '&mode=' + encodeURIComponent(mode)
		+ '&size=' + encodeURIComponent(String(size))
		+ '&quality=' + encodeURIComponent(String(JPEG_QUALITY));

	await page.goto(url, { waitUntil: 'load', timeout: 60000 });
	await page.waitForFunction(
		() => document.title === 'ready' && window.__planetExportDone === true,
		{ timeout: 60000 }
	);

	const dataUrl = await page.evaluate((quality) => {
		if (window.__planetExportDataUrl) {
			return window.__planetExportDataUrl;
		}
		const canvas = document.getElementById('overview-planet-canvas');
		if (!canvas || !window.HiveNovaOverviewPlanet) {
			return null;
		}
		return window.HiveNovaOverviewPlanet.captureStaticDataUrl(canvas, 'image/jpeg', quality);
	}, JPEG_QUALITY);

	if (!dataUrl || !dataUrl.startsWith('data:image/jpeg')) {
		throw new Error('Canvas capture failed for ' + entry.texture + ' (' + mode + ')');
	}

	const buf = dataUrlToBuffer(dataUrl);
	const minBytes = mode === 'lite' ? 3500 : 8000;
	if (buf.length < minBytes) {
		throw new Error('JPG too small (' + buf.length + ' bytes) — likely black frame for ' + entry.texture);
	}
	return buf;
}

function buildJobs(entries) {
	const jobs = [];
	for (const entry of entries) {
		jobs.push({ entry, mode: 'lite', size: LITE_SIZE });
		jobs.push({ entry, mode: 'full', size: FULL_SIZE });
	}
	return jobs;
}

async function runWorker(browser, baseUrl, jobs, stats, workerId, nextJobIndex) {
	const page = await browser.newPage();
	await page.setViewport({ width: Math.max(640, FULL_SIZE + 64), height: Math.max(640, FULL_SIZE + 64), deviceScaleFactor: 1 });

	try {
		while (true) {
			const jobIndex = nextJobIndex.value++;
			if (jobIndex >= jobs.length) {
				break;
			}
			const job = jobs[jobIndex];
			const label = job.entry.texture + ' (' + job.mode + ')';
			const outFile = outputPath(job.entry.texture, job.mode);
			process.stdout.write('[' + workerId + '] ' + label + '… ');
			try {
				const buf = await renderOne(page, baseUrl, job.entry, job.mode, job.size);
				fs.writeFileSync(outFile, buf);
				console.log('→ ' + path.relative(ROOT, outFile));
				stats.ok += 1;
			} catch (err) {
				console.log('FAILED: ' + err.message);
				stats.fail += 1;
			}
		}
	} finally {
		await page.close();
	}
}

async function main() {
	const opts = parseArgs(process.argv);
	const entries = opts.textures
		? opts.textures.map((t) => catalog.getByTexture(t)).filter(Boolean)
		: catalog.entries;

	if (!entries.length) {
		console.error('No catalog entries to render.');
		process.exit(1);
	}

	const jobs = buildJobs(entries);
	fs.mkdirSync(OUT_DIR, { recursive: true });

	let puppeteer;
	try {
		puppeteer = await import('puppeteer');
	} catch (err) {
		console.error('Puppeteer is required. Install with: npm install --save-dev puppeteer');
		console.error(err.message);
		process.exit(1);
	}

	const baseUrl = 'http://127.0.0.1:' + opts.port + '/scripts/dev/planet-viz-export.html';
	const jobCount = Math.min(opts.jobs, jobs.length);
	console.log('Rendering ' + jobs.length + ' images with ' + jobCount + ' parallel workers…');

	const browser = await puppeteer.default.launch({
		headless: true,
		args: [
			'--no-sandbox',
			'--disable-setuid-sandbox',
			'--enable-webgl',
			'--use-gl=angle',
			'--ignore-gpu-blocklist'
		]
	});

	const stats = { ok: 0, fail: 0 };
	const nextJobIndex = { value: 0 };
	const started = Date.now();

	await Promise.all(
		Array.from({ length: jobCount }, (_, i) =>
			runWorker(browser, baseUrl, jobs, stats, i + 1, nextJobIndex)
		)
	);

	await browser.close();
	const elapsed = ((Date.now() - started) / 1000).toFixed(1);
	console.log(
		'\nDone: ' + stats.ok + ' written, ' + stats.fail + ' failed in ' + elapsed + 's'
		+ ' → ' + path.relative(ROOT, OUT_DIR)
	);
	process.exit(stats.fail > 0 ? 1 : 0);
}

main().catch((err) => {
	console.error(err);
	process.exit(1);
});
