import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const distDir = path.join(root, "frontend", "dist");
const warningBytes = 1_200_000;

if (!fs.existsSync(distDir)) {
  console.log("Bundle size check skipped: frontend/dist not found.");
  process.exit(0);
}

function collectJsFiles(directory) {
  const entries = fs.readdirSync(directory, { withFileTypes: true });
  const results = [];

  for (const entry of entries) {
    const abs = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      results.push(...collectJsFiles(abs));
      continue;
    }

    if (!entry.isFile() || !entry.name.endsWith(".js")) {
      continue;
    }

    results.push(abs);
  }

  return results;
}

const assets = collectJsFiles(distDir)
  .filter((file) => {
    const name = path.basename(file);
    return name.startsWith("main-") || name.startsWith("index-");
  })
  .map((abs) => ({
    name: path.relative(path.join(root, "frontend"), abs),
    bytes: fs.statSync(abs).size,
  }))
  .sort((a, b) => b.bytes - a.bytes);

if (assets.length === 0) {
  console.log("Bundle size check skipped: no main/index frontend bundles found.");
  process.exit(0);
}

const largest = assets[0];
const kib = (largest.bytes / 1024).toFixed(2);
console.log(`Largest frontend bundle: ${largest.name} (${kib} KiB).`);

if (largest.bytes > warningBytes) {
  const thresholdKib = (warningBytes / 1024).toFixed(2);
  console.log(
    `WARNING: bundle exceeds warning threshold (${thresholdKib} KiB). Consider code splitting/manual chunks.`
  );
} else {
  console.log("Bundle size check passed within warning threshold.");
}

process.exit(0);
