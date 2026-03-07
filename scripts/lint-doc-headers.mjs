import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const docsRoot = path.join(root, "documentation");
const requiredHeaders = ["Status:", "Last Updated:", "Owner:", "Depends On:"];
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

  if (missing.length === 0) return null;
  return { rel, missing };
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
  console.log(`- ${warning.rel}: missing ${warning.missing.join(", ")}`);
}
console.log("Doc header lint is warning-only and does not fail the build.");
process.exit(0);
