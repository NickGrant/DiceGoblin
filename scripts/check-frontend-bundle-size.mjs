import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const assetsDir = path.join(root, "frontend", "dist", "assets");
const warningBytes = 1_200_000;

if (!fs.existsSync(assetsDir)) {
  console.log("Bundle size check skipped: frontend/dist/assets not found.");
  process.exit(0);
}

const assets = fs.readdirSync(assetsDir)
  .filter((name) => name.startsWith("index-") && name.endsWith(".js"))
  .map((name) => {
    const abs = path.join(assetsDir, name);
    const bytes = fs.statSync(abs).size;
    return { name, bytes };
  })
  .sort((a, b) => b.bytes - a.bytes);

if (assets.length === 0) {
  console.log("Bundle size check skipped: no index-*.js assets found.");
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
