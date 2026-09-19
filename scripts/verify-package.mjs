import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";

const root = process.cwd();
const npmBin = process.platform === "win32" ? "npm.cmd" : "npm";
const continueOnFailure = process.argv.includes("--continue-on-failure");
const outputDir = path.join(root, "artifacts", "verification");
const summaryPath = path.join(outputDir, "package-summary.json");
const maxBuffer = 64 * 1024 * 1024;

const gates = [
  { id: "llm-check", label: "LLM/context", command: npmBin, args: ["run", "llm:check"] },
  { id: "docs-lint", label: "Docs lint", command: npmBin, args: ["run", "docs:lint"] },
  { id: "content-validate", label: "Content", command: npmBin, args: ["run", "content:validate"] },
  { id: "backend-tests", label: "Backend", command: npmBin, args: ["run", "test:backend"] },
  {
    id: "frontend-tests",
    label: "Frontend",
    command: npmBin,
    args: process.env.CI
      ? ["--prefix", "frontend", "run", "test", "--", "--browsers=ChromeHeadless"]
      : ["run", "test:frontend"],
  },
  { id: "frontend-build", label: "Frontend build", command: npmBin, args: ["run", "build:frontend"] },
  { id: "bundle-check", label: "Bundle", command: npmBin, args: ["run", "bundle:check"] },
  { id: "diff-check", label: "Diff whitespace", command: "git", args: ["diff", "--check"] },
];

fs.rmSync(outputDir, { recursive: true, force: true });
fs.mkdirSync(outputDir, { recursive: true });

const startedAt = Date.now();
const results = [];
let blocked = false;

for (const gate of gates) {
  if (blocked) {
    results.push({ id: gate.id, label: gate.label, status: "skipped", exit_code: null, duration_ms: 0, log_path: null });
    continue;
  }

  const result = runGate(gate);
  results.push(result);
  printResult(result);

  if (result.status === "fail" && !continueOnFailure) blocked = true;
}

const summary = {
  schema_version: 1,
  generated_at: new Date().toISOString(),
  branch: gitValue(["branch", "--show-current"]) || null,
  head: gitValue(["rev-parse", "HEAD"]) || null,
  overall_status: results.some((result) => result.status === "fail") ? "fail" : "pass",
  duration_ms: Date.now() - startedAt,
  continue_on_failure: continueOnFailure,
  gates: results,
};

fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2) + "\n", "utf8");
console.log(`Summary: ${path.relative(root, summaryPath).replaceAll("\\", "/")}`);
console.log(`Overall: ${summary.overall_status.toUpperCase()} (${formatDuration(summary.duration_ms)})`);

process.exit(summary.overall_status === "pass" ? 0 : 1);

function runGate(gate) {
  const started = Date.now();
  const invocation = [gate.command, ...gate.args].join(" ");
  const spawned = spawnSync(gate.command, gate.args, {
    cwd: root,
    encoding: "utf8",
    maxBuffer,
    shell: process.platform === "win32",
    env: process.env,
  });

  const stdout = spawned.stdout ?? "";
  const stderr = spawned.stderr ?? "";
  const combined = [stdout, stderr].filter(Boolean).join(stdout && stderr ? "\n" : "");
  const logPath = path.join(outputDir, `${gate.id}.log`);
  fs.writeFileSync(logPath, `> ${invocation}\n\n${combined}`, "utf8");

  const exitCode = spawned.status ?? 1;
  const parsed = parseTestCounts(combined);
  const result = {
    id: gate.id,
    label: gate.label,
    status: exitCode === 0 ? "pass" : "fail",
    exit_code: exitCode,
    duration_ms: Date.now() - started,
    log_path: path.relative(root, logPath).replaceAll("\\", "/"),
    ...(parsed.tests !== null ? { test_count: parsed.tests } : {}),
    ...(parsed.assertions !== null ? { assertion_count: parsed.assertions } : {}),
  };

  if (spawned.error) {
    fs.appendFileSync(logPath, `\nPROCESS ERROR: ${spawned.error.message}\n`, "utf8");
  }

  if (result.status === "fail") printFailureTail(combined, gate.label);
  return result;
}

function parseTestCounts(raw) {
  const text = stripAnsi(raw);
  const phpOk = text.match(/OK\s*\((\d+) tests?,\s*(\d+) assertions?\)/i);
  if (phpOk) return { tests: Number(phpOk[1]), assertions: Number(phpOk[2]) };

  const phpSummary = text.match(/Tests:\s*(\d+)(?:,\s*Assertions:\s*(\d+))?/i);
  if (phpSummary) return { tests: Number(phpSummary[1]), assertions: phpSummary[2] ? Number(phpSummary[2]) : null };

  const karma = [...text.matchAll(/Executed\s+(\d+)\s+of\s+(\d+)(?:\s+SUCCESS)?/gi)].at(-1);
  if (karma) return { tests: Number(karma[2]), assertions: null };

  return { tests: null, assertions: null };
}

function printResult(result) {
  const counts = result.test_count !== undefined
    ? ` · ${result.test_count} tests${result.assertion_count !== undefined ? ` / ${result.assertion_count} assertions` : ""}`
    : "";
  console.log(`${result.status === "pass" ? "PASS" : result.status.toUpperCase()}  ${result.label} · ${formatDuration(result.duration_ms)}${counts}`);
}

function printFailureTail(raw, label) {
  const lines = stripAnsi(raw).trim().split(/\r?\n/).filter(Boolean);
  const tail = lines.slice(-30);
  if (tail.length === 0) return;
  console.error(`\n--- ${label} failure tail ---`);
  console.error(tail.join("\n"));
  console.error("--- full output saved in artifacts/verification ---\n");
}

function gitValue(args) {
  const result = spawnSync("git", args, { cwd: root, encoding: "utf8", shell: process.platform === "win32" });
  return result.status === 0 ? (result.stdout ?? "").trim() : "";
}

function stripAnsi(value) {
  return value.replace(/[\u001B\u009B][[\]()#;?]*(?:(?:(?:[a-zA-Z\d]*(?:;[-a-zA-Z\d\/#&.:=?%@~_]+)*)?\u0007)|(?:(?:\d{1,4}(?:[;:]\d{0,4})*)?[\dA-PR-TZcf-nq-uy=><~]))/g, "");
}

function formatDuration(ms) {
  if (ms < 1000) return `${ms}ms`;
  const seconds = Math.round(ms / 100) / 10;
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  return `${minutes}m ${Math.round(seconds % 60)}s`;
}
