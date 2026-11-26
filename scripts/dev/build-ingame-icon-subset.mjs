#!/usr/bin/env node
/**
 * Verify ingame Font Awesome subset matches template usage AND that the
 * served woff/woff2 fonts contain every icon codepoint in ingame-icons.css.
 *
 * Usage: node scripts/dev/build-ingame-icon-subset.mjs
 *
 * #364 shipped a 14-glyph woff/woff2 subset (ttf/eot/svg stay full). After
 * adding an icon to ingame-icons.css, regenerate those two files from the
 * in-repo full TTF (Font Awesome 5.15.1), then re-run this script:
 *
 *   pyftsubset styles/resource/css/fontawesome/webfonts/fa-solid-900.ttf \
 *     --unicodes=U+F005,U+F015,... \
 *     --flavor=woff --with-zopfli --drop-tables+=FFTM \
 *     --output-file=styles/resource/css/fontawesome/webfonts/fa-solid-900.woff
 *   pyftsubset ... --flavor=woff2 --drop-tables+=FFTM \
 *     --output-file=styles/resource/css/fontawesome/webfonts/fa-solid-900.woff2
 *
 * Unicodes come from the .fa-*:before rules in ingame-icons.css (this script
 * prints them when a glyph is missing).
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const SUBSET = path.join(ROOT, 'styles/resource/css/fontawesome/css/ingame-icons.css');
const FONT_DIR = path.join(ROOT, 'styles/resource/css/fontawesome/webfonts');
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
  const found = new Map();
  const re = /\.fa-([a-z0-9-]+):before\s*\{\s*content:\s*'\\([0-9a-fA-F]+)'/g;
  let match;
  while ((match = re.exec(css)) !== null) {
    found.set(match[1], parseInt(match[2], 16));
  }
  return found;
}

function readU16(buf, offset) {
  return buf.readUInt16BE(offset);
}

function readU32(buf, offset) {
  return buf.readUInt32BE(offset);
}

function parseCmapCodepoints(cmap) {
  const version = readU16(cmap, 0);
  const numTables = readU16(cmap, 2);
  void version;
  const cps = new Set();
  for (let i = 0; i < numTables; i++) {
    const recOff = 4 + i * 8;
    const offset = readU32(cmap, recOff + 4);
    if (offset + 2 > cmap.length) continue;
    const format = readU16(cmap, offset);
    if (format === 4) {
      const segCount = readU16(cmap, offset + 6) / 2;
      const endOff = offset + 14;
      const startOff = endOff + segCount * 2 + 2;
      const deltaOff = startOff + segCount * 2;
      const rangeOff = deltaOff + segCount * 2;
      for (let s = 0; s < segCount; s++) {
        const start = readU16(cmap, startOff + s * 2);
        const end = readU16(cmap, endOff + s * 2);
        const idDelta = cmap.readInt16BE(deltaOff + s * 2);
        const idRangeOffset = readU16(cmap, rangeOff + s * 2);
        for (let c = start; c <= end; c++) {
          let gid;
          if (idRangeOffset === 0) {
            gid = (c + idDelta) & 0xffff;
          } else {
            const glyphOff = rangeOff + s * 2 + idRangeOffset + (c - start) * 2;
            if (glyphOff + 2 > cmap.length) continue;
            const raw = readU16(cmap, glyphOff);
            gid = raw === 0 ? 0 : (raw + idDelta) & 0xffff;
          }
          if (gid !== 0) cps.add(c);
        }
      }
    } else if (format === 12) {
      const nGroups = readU32(cmap, offset + 12);
      for (let g = 0; g < nGroups; g++) {
        const go = offset + 16 + g * 12;
        const start = readU32(cmap, go);
        const end = readU32(cmap, go + 4);
        for (let c = start; c <= end; c++) cps.add(c);
      }
    }
  }
  return cps;
}

function tablesFromWoff(buf) {
  if (buf.toString('ascii', 0, 4) !== 'wOFF') {
    throw new Error('not a wOFF font');
  }
  const numTables = readU16(buf, 12);
  const tables = {};
  for (let i = 0; i < numTables; i++) {
    const off = 44 + i * 20;
    const tag = buf.toString('ascii', off, off + 4);
    const offset = readU32(buf, off + 4);
    const compLength = readU32(buf, off + 8);
    const origLength = readU32(buf, off + 12);
    let data = buf.subarray(offset, offset + compLength);
    if (compLength < origLength) {
      data = zlib.inflateSync(data);
    }
    if (data.length !== origLength) {
      throw new Error(`woff table ${tag} length mismatch`);
    }
    tables[tag] = data;
  }
  return tables;
}

const WOFF2_TAGS = [
  'cmap','head','hhea','hmtx','maxp','name','OS/2','post',
  'cvt ','fpgm','glyf','loca','prep','CFF ','VORG','EBDT',
  'EBLC','gasp','hdmx','kern','LTSH','PCLT','VDMX','vhea',
  'vmtx','BASE','GDEF','GPOS','GSUB','EBSC','JSTF','MATH',
  'CBDT','CBLC','COLR','CPAL','SVG ','sbix','acnt','avar',
  'bdat','bloc','bsln','cvar','fdsc','feat','fmtx','fvar',
  'gvar','hsty','just','lcar','mort','morx','opbd','prop',
  'trak','Zapf','Silf','Glat','Gloc','Feat','Sill',
];

function readBase128(buf, offset) {
  let result = 0;
  for (let i = 0; i < 5; i++) {
    if (offset >= buf.length) throw new Error('base128 truncated');
    const b = buf[offset++];
    // WOFF2 UIntBase128 is big-endian 7-bit groups
    if (result & 0xfe000000) throw new Error('base128 overflow');
    result = (result << 7) | (b & 0x7f);
    if ((b & 0x80) === 0) return [result, offset];
  }
  throw new Error('invalid base128');
}

function tablesFromWoff2(buf) {
  if (buf.toString('ascii', 0, 4) !== 'wOF2') {
    throw new Error('not a wOF2 font');
  }
  const numTables = readU16(buf, 12);
  const totalCompressedSize = readU32(buf, 20);
  let offset = 48;
  const dir = [];
  for (let i = 0; i < numTables; i++) {
    const flags = buf[offset++];
    const tagBits = flags & 0x3f;
    const transform = flags >> 6;
    let tag;
    if (tagBits === 0x3f) {
      tag = buf.toString('ascii', offset, offset + 4);
      offset += 4;
    } else {
      tag = WOFF2_TAGS[tagBits];
    }
    let origLength;
    [origLength, offset] = readBase128(buf, offset);
    const isTransformed = (tag === 'glyf' || tag === 'loca')
      ? transform !== 3
      : transform !== 0;
    let transformLength = origLength;
    if (isTransformed) {
      [transformLength, offset] = readBase128(buf, offset);
    }
    dir.push({ tag, origLength, transformLength, isTransformed });
  }
  const compressed = buf.subarray(offset, offset + totalCompressedSize);
  const decompressed = zlib.brotliDecompressSync(compressed);
  const tables = {};
  let pos = 0;
  for (const entry of dir) {
    const size = entry.isTransformed ? entry.transformLength : entry.origLength;
    tables[entry.tag] = decompressed.subarray(pos, pos + size);
    pos += size;
  }
  return tables;
}

function codepointsFromFont(filePath) {
  const buf = fs.readFileSync(filePath);
  const sig = buf.toString('ascii', 0, 4);
  let tables;
  if (sig === 'wOFF') tables = tablesFromWoff(buf);
  else if (sig === 'wOF2') tables = tablesFromWoff2(buf);
  else throw new Error(`${path.basename(filePath)}: unsupported font signature ${JSON.stringify(sig)}`);
  if (!tables.cmap) throw new Error(`${path.basename(filePath)}: no cmap table`);
  return parseCmapCodepoints(tables.cmap);
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
const missingCss = [...used].filter((icon) => !subsetIcons.has(icon)).sort();
const extra = [...subsetIcons.keys()].filter((icon) => !used.has(icon)).sort();

console.log('Ingame FA icons in templates:', [...used].sort().join(', ') || '(none)');
console.log('Subset defines:', [...subsetIcons.keys()].sort().join(', '));

let failed = false;

if (missingCss.length) {
  console.error('Missing from ingame-icons.css:', missingCss.join(', '));
  failed = true;
}

if (extra.length) {
  console.warn('Unused in templates (kept for stability):', extra.join(', '));
}

const requiredCodepoints = new Map();
for (const [name, cp] of subsetIcons) {
  requiredCodepoints.set(name, cp);
}

const fontFiles = ['fa-solid-900.woff', 'fa-solid-900.woff2'];
for (const name of fontFiles) {
  const fontPath = path.join(FONT_DIR, name);
  const present = codepointsFromFont(fontPath);
  const missingGlyphs = [...requiredCodepoints.entries()]
    .filter(([, cp]) => !present.has(cp))
    .map(([icon, cp]) => `${icon} (U+${cp.toString(16).toUpperCase().padStart(4, '0')})`);
  console.log(`${name} glyphs checked: ${present.size} codepoints`);
  if (missingGlyphs.length) {
    console.error(`Missing from ${name}:`, missingGlyphs.join(', '));
    failed = true;
  }
}

if (failed) {
  process.exit(1);
}

console.log('OK — subset CSS and woff/woff2 cover all ingame template icons.');
