#!/usr/bin/env node
/**
 * Verify ingame Font Awesome subset matches template usage.
 * Usage: node scripts/dev/build-ingame-icon-subset.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const SUBSET = path.join(ROOT, 'styles/resource/css/fontawesome/css/ingame-icons.css');
const TEMPLATE_DIR = path.join(ROOT, 'styles/templates/game');

const ICON_RE = /\b(?:fa[srb]?|fa)\s+fa-([a-z0-9-]+)/g;

function collectIconsFromFile(filePath) {
  const text = fs.readFileSync(filePath, 'utf8');
  const icons = new Set();
  let match;
  while ((match = ICON_RE.exec(text)) !== null) {
    icons.add(match[1]);
  }
  return icons;
}

function walkTemplates(dir, files = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walkTemplates(full, files);
    } else if (entry.name.endsWith('.tpl')) {
      files.push(full);
    }
  }
  return files;
}

function iconsInSubset(css) {
  const found = new Set();
  const re = /\.fa-([a-z0-9-]+):before/g;
  let match;
  while ((match = re.exec(css)) !== null) {
    found.add(match[1]);
  }
  return found;
}

const templates = walkTemplates(TEMPLATE_DIR, []);
const used = new Set();
for (const tpl of templates) {
  for (const icon of collectIconsFromFile(tpl)) {
    used.add(icon);
  }
}

const subsetCss = fs.readFileSync(SUBSET, 'utf8');
const subsetIcons = iconsInSubset(subsetCss);
const missing = [...used].filter((icon) => !subsetIcons.has(icon)).sort();
const extra = [...subsetIcons].filter((icon) => !used.has(icon)).sort();

console.log('Ingame FA icons in templates:', [...used].sort().join(', ') || '(none)');
console.log('Subset defines:', [...subsetIcons].sort().join(', '));

if (missing.length) {
  console.error('Missing from ingame-icons.css:', missing.join(', '));
  process.exit(1);
}

if (extra.length) {
  console.warn('Unused in templates (kept for stability):', extra.join(', '));
}

console.log('OK — subset covers all ingame template icons.');
