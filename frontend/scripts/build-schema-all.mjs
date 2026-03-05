import fs from "node:fs";
import path from "node:path";
import strip from "strip-comments";

const FRONTEND_DIR = process.cwd();

// Adjust paths relative to /frontend
const MIGRATIONS_DIR = path.resolve(FRONTEND_DIR, "../backend/migrations");
const OUT_ALL_FILE = path.resolve(FRONTEND_DIR, "../backend/migrations/schema_all.sql");
const OUT_UPDATE_FILE = path.resolve(FRONTEND_DIR, "../backend/migrations/schema_update.sql");

// Match numbered migrations like 001_init.sql, 010-add.sql, etc.
const MIGRATION_RE = /^(\d+).*\.sql$/i;

function listMigrations(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });

  const migrations = entries
    .filter((e) => e.isFile())
    .map((e) => e.name)
    .filter((name) => MIGRATION_RE.test(name))
    .map((name) => {
      const n = Number(name.match(MIGRATION_RE)[1]);
      return { n, name, fullPath: path.join(dir, name) };
    })
    .sort((a, b) => a.n - b.n);

  if (migrations.length === 0) {
    throw new Error(`No numbered .sql migrations found in ${dir}`);
  }

  // detect duplicates
  for (let i = 1; i < migrations.length; i++) {
    if (migrations[i].n === migrations[i - 1].n) {
      throw new Error(
        `Duplicate migration number ${migrations[i].n}: ${migrations[i - 1].name} and ${migrations[i].name}`
      );
    }
  }

  return migrations;
}

function stripSqlComments(sql) {
  // 1) strip /* ... */ block comments and # comments using strip-comments
  // strip() does not reliably remove SQL "--" comments, so we do that ourselves.
  let cleaned = strip(sql, { preserveNewlines: true });

  // 2) remove SQL "-- ..." line comments (best-effort)
  // This is a pragmatic approach; if you have "--" inside string literals, prefer a real SQL parser.
  cleaned = cleaned
    .split("\n")
    .map((line) => line.replace(/^\s*--.*$/, ""))
    .join("\n");

  // 3) collapse excessive blank lines and trim
  cleaned = cleaned.replace(/\n{3,}/g, "\n\n").trim() + "\n";
  return cleaned;
}

function build() {
  const migrations = listMigrations(MIGRATIONS_DIR);

  const parts = [];
  parts.push("-- AUTO-GENERATED FILE. DO NOT EDIT.\n");
  parts.push(`-- Source: ${MIGRATIONS_DIR}\n\n`);

  for (const m of migrations) {
    const raw = fs.readFileSync(m.fullPath, "utf8");
    const cleaned = stripSqlComments(raw);

    if (!cleaned.trim()) continue;

    parts.push(`-- BEGIN MIGRATION: ${m.name}\n`);
    parts.push(cleaned);
    if (!cleaned.endsWith("\n")) parts.push("\n");
    parts.push(`-- END MIGRATION: ${m.name}\n\n`);
  }

  const output = parts.join("").trimEnd() + "\n";
  fs.writeFileSync(OUT_ALL_FILE, output, "utf8");
  fs.writeFileSync(OUT_UPDATE_FILE, output, "utf8");
  console.log(`Wrote ${OUT_ALL_FILE}`);
  console.log(`Wrote ${OUT_UPDATE_FILE}`);
}

build();
