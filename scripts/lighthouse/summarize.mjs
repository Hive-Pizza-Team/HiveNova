#!/usr/bin/env node
/**
 * Print a compact Lighthouse run summary for agents / terminal diffing.
 *
 * Usage:
 *   node scripts/lighthouse/summarize.mjs latest
 *   node scripts/lighthouse/summarize.mjs reports/lighthouse/2026-06-14T12-00-00
 *   node scripts/lighthouse/summarize.mjs --compare latest prev-run-id
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const DEFAULT_DIR = path.join(ROOT, 'reports/lighthouse');

function resolveRunDir(arg) {
	if (!arg || arg === 'latest') {
		const latest = path.join(DEFAULT_DIR, 'latest-summary.json');
		if (!fs.existsSync(latest)) {
			throw new Error('No latest run. Run scripts/lighthouse/run-audit.sh first.');
		}
		const manifest = JSON.parse(fs.readFileSync(latest, 'utf8'));
		return { dir: path.join(DEFAULT_DIR, manifest.runId), manifest };
	}
	const dir = path.isAbsolute(arg) ? arg : path.join(DEFAULT_DIR, arg);
	const summaryPath = path.join(dir, 'summary.json');
	if (!fs.existsSync(summaryPath)) {
		throw new Error(`Missing summary.json in ${dir}`);
	}
	return { dir, manifest: JSON.parse(fs.readFileSync(summaryPath, 'utf8')) };
}

function pad(str, width) {
	const s = String(str ?? '');
	return s.length >= width ? s : s + ' '.repeat(width - s.length);
}

function formatTable(manifest) {
	const rows = Object.entries(manifest.pages || {});
	const headers = ['page', 'perf', 'LCP', 'TBT', 'CLS', 'status'];
	const lines = [headers.join('\t')];
	for (const [id, entry] of rows) {
		const s = entry.summary;
		const perf = s?.categories?.performance ?? '';
		const lcp = s?.metrics?.['largest-contentful-paint']?.displayValue ?? '';
		const tbt = s?.metrics?.['total-blocking-time']?.displayValue ?? '';
		const cls = s?.metrics?.['cumulative-layout-shift']?.displayValue ?? '';
		lines.push([id, perf, lcp, tbt, cls, entry.status].join('\t'));
	}
	return lines.join('\n');
}

function printOpportunities(manifest, limit = 5) {
	console.log('\nTop opportunities (first failing pages):');
	let shown = 0;
	for (const [id, entry] of Object.entries(manifest.pages || {})) {
		if (entry.status !== 'ok' || !entry.summary?.opportunities?.length) {
			continue;
		}
		console.log(`\n${id}:`);
		for (const opp of entry.summary.opportunities.slice(0, 3)) {
			console.log(`  - ${opp.title}: ${opp.displayValue ?? opp.score}`);
		}
		shown++;
		if (shown >= limit) {
			break;
		}
	}
}

function compareRuns(current, previous) {
	console.log('Performance delta (current - previous):');
	const ids = new Set([
		...Object.keys(current.pages || {}),
		...Object.keys(previous.pages || {}),
	]);
	const header = `${pad('page', 18)} ${pad('was', 5)} ${pad('now', 5)} ${pad('delta', 6)}`;
	console.log(header);
	for (const id of [...ids].sort()) {
		const a = current.pages?.[id]?.summary?.categories?.performance;
		const b = previous.pages?.[id]?.summary?.categories?.performance;
		if (a == null && b == null) {
			continue;
		}
		const delta = a != null && b != null ? a - b : null;
		const deltaStr = delta == null ? '—' : (delta > 0 ? `+${delta}` : String(delta));
		console.log(`${pad(id, 18)} ${pad(b ?? '—', 5)} ${pad(a ?? '—', 5)} ${pad(deltaStr, 6)}`);
	}
}

function parseArgs(argv) {
	const opts = { compare: null, run: 'latest' };
	for (let i = 2; i < argv.length; i++) {
		if (argv[i] === '--compare' && argv[i + 1] && argv[i + 2]) {
			opts.compare = { current: argv[++i], previous: argv[++i] };
		} else if (!argv[i].startsWith('-')) {
			opts.run = argv[i];
		}
	}
	return opts;
}

function main() {
	const opts = parseArgs(process.argv);
	if (opts.compare) {
		const cur = resolveRunDir(opts.compare.current).manifest;
		const prev = resolveRunDir(opts.compare.previous).manifest;
		console.log(`Compare ${cur.runId} vs ${prev.runId}`);
		compareRuns(cur, prev);
		return;
	}

	const { dir, manifest } = resolveRunDir(opts.run);
	console.log(`Run: ${manifest.runId}`);
	console.log(`Dir: ${dir}`);
	console.log(`Preset: ${manifest.preset}  Categories: ${(manifest.categories || []).join(', ')}`);
	console.log('');
	console.log(formatTable(manifest));
	printOpportunities(manifest);
}

main();
