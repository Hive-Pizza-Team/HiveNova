#!/usr/bin/env node
/**
 * Batch Lighthouse audits for HiveNova ingame pages via Puppeteer + CDP.
 *
 * Logs in once, reuses the browser session (disableStorageReset), and writes
 * agent-friendly summaries under reports/lighthouse/<run-id>/.
 *
 * Usage:
 *   node scripts/lighthouse/audit.mjs
 *   node scripts/lighthouse/audit.mjs --pages overview,buildings --categories performance
 *   node scripts/lighthouse/audit.mjs --preset desktop --html
 *
 * Env: SMOKE_BASE_URL, ADMIN_NAME, ADMIN_PASSWORD
 *
 * Requires: npm install (puppeteer + lighthouse), PHP dev server on :8000
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const ROUTES_PATH = path.join(__dirname, 'routes.json');
const DEFAULT_OUT = path.join(ROOT, 'reports/lighthouse');

const OPPORTUNITY_AUDITS = [
	'render-blocking-resources',
	'unused-javascript',
	'unused-css-rules',
	'mainthread-work-breakdown',
	'bootup-time',
	'uses-text-compression',
	'modern-image-formats',
	'uses-rel-preconnect',
	'largest-contentful-paint-element',
];

const METRIC_AUDITS = [
	'first-contentful-paint',
	'largest-contentful-paint',
	'total-blocking-time',
	'cumulative-layout-shift',
	'speed-index',
	'interactive',
];

function parseArgs(argv) {
	const opts = {
		baseUrl: process.env.SMOKE_BASE_URL || 'http://localhost:8000',
		username: process.env.ADMIN_NAME || 'spacepizzadev',
		password: process.env.ADMIN_PASSWORD || '2hBR2wC0BcS^A%vsLvw9XgXy5$aBF*',
		pages: null,
		categories: ['performance'],
		preset: 'mobile',
		outputDir: DEFAULT_OUT,
		html: false,
		fullJson: false,
		headless: true,
		timeoutMs: 120_000,
	};
	for (let i = 2; i < argv.length; i++) {
		const arg = argv[i];
		if (arg === '--base-url' && argv[i + 1]) {
			opts.baseUrl = argv[++i];
		} else if (arg === '--username' && argv[i + 1]) {
			opts.username = argv[++i];
		} else if (arg === '--password' && argv[i + 1]) {
			opts.password = argv[++i];
		} else if (arg === '--pages' && argv[i + 1]) {
			opts.pages = new Set(argv[++i].split(',').map((s) => s.trim()).filter(Boolean));
		} else if (arg === '--categories' && argv[i + 1]) {
			opts.categories = argv[++i].split(',').map((s) => s.trim()).filter(Boolean);
		} else if (arg === '--preset' && argv[i + 1]) {
			opts.preset = argv[++i];
		} else if (arg === '--output-dir' && argv[i + 1]) {
			opts.outputDir = path.resolve(argv[++i]);
		} else if (arg === '--timeout' && argv[i + 1]) {
			opts.timeoutMs = parseInt(argv[++i], 10) || opts.timeoutMs;
		} else if (arg === '--html') {
			opts.html = true;
		} else if (arg === '--full-json') {
			opts.fullJson = true;
		} else if (arg === '--headed') {
			opts.headless = false;
		} else if (arg === '--help' || arg === '-h') {
			printHelp();
			process.exit(0);
		}
	}
	opts.baseUrl = opts.baseUrl.replace(/\/$/, '');
	return opts;
}

function printHelp() {
	console.log(`HiveNova Lighthouse batch audit

Options:
  --base-url URL       Default: http://localhost:8000 (or SMOKE_BASE_URL)
  --username USER      Default: spacepizzadev (or ADMIN_NAME)
  --password PASS      Default: local dev password (or ADMIN_PASSWORD)
  --pages a,b,c        Subset of route ids from scripts/lighthouse/routes.json
  --categories a,b     performance, accessibility, best-practices, seo (default: performance)
  --preset mobile|desktop   Lighthouse throttling preset (default: mobile)
  --output-dir DIR     Default: reports/lighthouse
  --html               Also write HTML report per page
  --full-json          Write full Lighthouse JSON per page (large)
  --headed             Show browser window
  --timeout MS         Per-page timeout (default: 120000)
`);
}

function loadRoutes(pageFilter) {
	const routes = JSON.parse(fs.readFileSync(ROUTES_PATH, 'utf8'));
	if (!pageFilter) {
		return routes;
	}
	return routes.filter((route) => pageFilter.has(route.id));
}

function runId() {
	return new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
}

function roundScore(score) {
	if (score == null || Number.isNaN(score)) {
		return null;
	}
	return Math.round(score * 100);
}

function pickAudit(audits, id) {
	const audit = audits[id];
	if (!audit) {
		return null;
	}
	return {
		id,
		title: audit.title,
		score: audit.score,
		displayValue: audit.displayValue ?? null,
		numericValue: audit.numericValue ?? null,
		description: audit.description ?? null,
	};
}

function summarizeLhr(lhr) {
	const categories = {};
	for (const [id, cat] of Object.entries(lhr.categories || {})) {
		categories[id] = roundScore(cat.score);
	}
	const audits = lhr.audits || {};
	const metrics = {};
	for (const id of METRIC_AUDITS) {
		const audit = audits[id];
		if (audit) {
			metrics[id] = {
				displayValue: audit.displayValue ?? null,
				numericValue: audit.numericValue ?? null,
			};
		}
	}
	const opportunities = OPPORTUNITY_AUDITS
		.map((id) => pickAudit(audits, id))
		.filter((item) => item && item.score != null && item.score < 0.99);
	return {
		finalUrl: lhr.finalUrl,
		fetchTime: lhr.fetchTime,
		categories,
		metrics,
		opportunities,
	};
}

function buildConfig(opts) {
	const settings = {
		onlyCategories: opts.categories,
		disableStorageReset: true,
		skipAboutBlank: true,
		throttlingMethod: 'simulate',
	};
	if (opts.preset === 'desktop') {
		settings.formFactor = 'desktop';
		settings.screenEmulation = { disabled: true };
	} else {
		settings.formFactor = 'mobile';
	}
	return {
		extends: 'lighthouse:default',
		settings,
	};
}

async function login(page, baseUrl, username, password) {
	await page.goto(`${baseUrl}/index.php?page=login`, {
		waitUntil: 'domcontentloaded',
		timeout: 60_000,
	});
	await page.waitForSelector('#username', { timeout: 15_000 });
	await page.click('#username', { clickCount: 3 });
	await page.type('#username', username, { delay: 0 });
	await page.click('#password', { clickCount: 3 });
	await page.type('#password', password, { delay: 0 });
	await Promise.all([
		page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60_000 }),
		page.click('#login input[type="submit"]'),
	]);
	const body = await page.content();
	if (body.includes('page=logout') || body.includes('game.php')) {
		return;
	}
	if (body.includes('loginError') || body.includes('name="password"')) {
		throw new Error('Login failed — check ADMIN_NAME / ADMIN_PASSWORD');
	}
}


async function runAuditForRoute(browser, lighthouse, config, baseUrl, route, opts, runDir) {
	const url = `${baseUrl}/${route.path}`;
	const page = await browser.newPage();
	page.setDefaultTimeout(opts.timeoutMs);
	const started = Date.now();
	const entry = {
		id: route.id,
		url,
		public: Boolean(route.public),
		status: 'pending',
		durationMs: null,
		error: null,
		summary: null,
	};

	try {
		const result = await lighthouse(url, {
			logLevel: 'error',
			output: opts.html ? ['json', 'html'] : 'json',
			maxWaitForLoad: opts.timeoutMs,
		}, config, page);

		const lhr = result.lhr;
		entry.summary = summarizeLhr(lhr);
		entry.status = 'ok';
		entry.durationMs = Date.now() - started;

		if (opts.fullJson) {
			const reportJson = Array.isArray(result.report) ? result.report[0] : result.report;
			fs.writeFileSync(path.join(runDir, `${route.id}.report.json`), reportJson);
		} else {
			fs.writeFileSync(
				path.join(runDir, `${route.id}.summary.json`),
				JSON.stringify(entry.summary, null, 2),
			);
		}
		if (opts.html) {
			const htmlReport = Array.isArray(result.report)
				? result.report.find((chunk) => chunk.trimStart().startsWith('<!'))
				: null;
			if (htmlReport) {
				fs.writeFileSync(path.join(runDir, `${route.id}.report.html`), htmlReport);
			}
		}

		return entry;
	} catch (err) {
		entry.status = 'error';
		entry.error = err instanceof Error ? err.message : String(err);
		entry.durationMs = Date.now() - started;
		return entry;
	} finally {
		await page.close().catch(() => {});
	}
}

async function main() {
	const opts = parseArgs(process.argv);
	const routes = loadRoutes(opts.pages);
	if (routes.length === 0) {
		console.error('No routes matched. Check --pages against scripts/lighthouse/routes.json');
		process.exit(1);
	}

	let puppeteer;
	let lighthouse;
	try {
		puppeteer = await import('puppeteer');
		lighthouse = (await import('lighthouse')).default;
	} catch {
		console.error('Missing dependencies. Run: npm install');
		process.exit(1);
	}

	const id = runId();
	const runDir = path.join(opts.outputDir, id);
	fs.mkdirSync(runDir, { recursive: true });

	const config = buildConfig(opts);

	const browser = await puppeteer.default.launch({
		headless: opts.headless ? 'shell' : false,
		args: [
			'--disable-extensions',
			'--no-first-run',
			'--disable-default-apps',
			'--disable-dev-shm-usage',
		],
	});

	const manifest = {
		runId: id,
		generatedAt: new Date().toISOString(),
		baseUrl: opts.baseUrl,
		preset: opts.preset,
		categories: opts.categories,
		routes: routes.map((r) => r.id),
		pages: {},
	};

	console.log(`Lighthouse audit run: ${id}`);
	console.log(`Base URL: ${opts.baseUrl}`);
	console.log(`Pages: ${routes.length}\n`);

	try {
		const loginPage = await browser.newPage();
		const needsLogin = routes.some((r) => !r.public);
		if (needsLogin) {
			console.log('Logging in…');
			await login(loginPage, opts.baseUrl, opts.username, opts.password);
			console.log('Login OK\n');
		}
		await loginPage.close();

		for (const route of routes) {
			process.stdout.write(`[${route.id}] `);
			const entry = await runAuditForRoute(
				browser,
				lighthouse,
				config,
				opts.baseUrl,
				route,
				opts,
				runDir,
			);
			manifest.pages[route.id] = entry;
			if (entry.status === 'ok') {
				const perf = entry.summary?.categories?.performance;
				const lcp = entry.summary?.metrics?.['largest-contentful-paint']?.displayValue;
				console.log(`OK  performance=${perf ?? '?'}  LCP=${lcp ?? '?'}`);
			} else {
				console.log(`FAIL  ${entry.error}`);
			}
		}
	} finally {
		await browser.close();
	}

	const summaryPath = path.join(runDir, 'summary.json');
	fs.writeFileSync(summaryPath, JSON.stringify(manifest, null, 2));

	const latestSummary = path.join(opts.outputDir, 'latest-summary.json');
	fs.writeFileSync(latestSummary, JSON.stringify(manifest, null, 2));

	const latestRun = path.join(opts.outputDir, 'latest-run.txt');
	fs.writeFileSync(latestRun, `${id}\n${runDir}\n`);

	console.log(`\nWrote ${summaryPath}`);
	console.log(`Latest pointer: ${latestSummary}`);
	console.log(`Summarize: node scripts/lighthouse/summarize.mjs latest`);
}

main().catch((err) => {
	console.error(err);
	process.exit(1);
});
