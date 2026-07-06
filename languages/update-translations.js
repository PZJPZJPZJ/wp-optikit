/**
 * WP OptiKit — Translation compiler
 *
 * Reads every .po file in this directory and compiles a matching .mo binary.
 *
 * Usage:
 *   cd languages
 *   npm install
 *   npm run build
 */

const fs = require('fs');
const path = require('path');
const { po, mo } = require('gettext-parser');

const LANG_DIR = __dirname;

const poFiles = fs.readdirSync(LANG_DIR).filter(f => f.endsWith('.po'));

if (poFiles.length === 0) {
  console.log('No .po files found.');
  process.exit(0);
}

for (const poFile of poFiles) {
  const poPath = path.join(LANG_DIR, poFile);
  const moPath = path.join(LANG_DIR, poFile.replace(/\.po$/, '.mo'));

  const raw = fs.readFileSync(poPath, 'utf8');
  const parsed = po.parse(raw);

  // Write back normalized .po
  const poOutput = po.compile(parsed, { foldLength: 79 });
  fs.writeFileSync(poPath, poOutput);

  // Compile .mo binary
  const moOutput = mo.compile(parsed);
  fs.writeFileSync(moPath, moOutput);

  const translationCount = Object.keys(
    parsed.translations[''] ?? {}
  ).filter(k => k !== '').length;

  console.log(`✓ ${poFile} → ${translationCount} translations → ${poFile.replace(/\.po$/, '.mo')}`);
}

console.log('\nDone.');
