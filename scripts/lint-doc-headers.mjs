import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const docsRoot = path.join(root, "documentation");
const requiredHeaders = ["Status:", "Last Updated:", "Owner:", "Depends On:"];
const encodingCorruptionChecks = [
  { label: "misdecoded em-dash sequence", regex: /\u00E2\u20AC\u201D/g },
  { label: "misdecoded apostrophe sequence", regex: /\u00E2\u20AC\u2122/g },
  { label: "misdecoded quote sequence", regex: /\u00E2\u20AC[\u0153\u009D]/g },
  { label: "misdecoded arrow sequence", regex: /\u00E2\u2020\u201C/g },
  { label: "suspicious mojibake starter (Ã)", regex: /\u00C3[\u0080-\u00BF]/g },
];
const excludes = new Set([
  "documentation/archive",
  "documentation/08-json-schema",
]);

function walk(dir, out = []) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const abs = path.join(dir, entry.name);
    const rel = path.relative(root, abs).replace(/\\/g, "/");
    if (entry.isDirectory()) {
      if (excludes.has(rel)) continue;
      walk(abs, out);
      continue;
    }
    if (entry.isFile() && entry.name.endsWith(".md")) {
      out.push(abs);
    }
  }
  return out;
}

function lintFile(absPath) {
  const rel = path.relative(root, absPath).replace(/\\/g, "/");
  const raw = fs.readFileSync(absPath, "utf8");
  const headerWindow = raw.split(/\r?\n/).slice(0, 24).join("\n");
  const missing = requiredHeaders.filter((header) => !headerWindow.includes(header));
  const encodingHits = [];

  for (const check of encodingCorruptionChecks) {
    check.regex.lastIndex = 0;
    const matches = raw.match(check.regex);
    if (matches && matches.length > 0) {
      encodingHits.push(`${check.label} x${matches.length}`);
    }
  }

  if (missing.length === 0 && encodingHits.length === 0) return null;
  return { rel, missing, encodingHits };
}

if (!fs.existsSync(docsRoot)) {
  console.log("Doc header lint skipped: documentation/ not found.");
  process.exit(0);
}

const markdownFiles = walk(docsRoot);
const warnings = markdownFiles
  .map((abs) => lintFile(abs))
  .filter((w) => w !== null);

if (warnings.length === 0) {
  console.log(`Doc header lint passed (${markdownFiles.length} files checked).`);
  process.exit(0);
}

console.log(`Doc header lint warning (${warnings.length} files):`);
for (const warning of warnings) {
  const parts = [];
  if (warning.missing.length > 0) {
    parts.push(`missing ${warning.missing.join(", ")}`);
  }
  if (warning.encodingHits.length > 0) {
    parts.push(`encoding flags: ${warning.encodingHits.join("; ")}`);
  }
  console.log(`- ${warning.rel}: ${parts.join(" | ")}`);
}
console.log("Doc header lint is warning-only and does not fail the build.");
process.exit(0);
