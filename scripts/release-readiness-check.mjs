import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const full = process.argv.includes("--full");
const npmBin = process.platform === "win32" ? "npm.cmd" : "npm";

const requiredFiles = [
  "agent/ISSUES.md",
  "agent/MILESTONES.md",
  "documentation/07-development-path/2026-07-25-completion-analysis.md",
  "documentation/06-testing-release/07-july-roadmap-uat-balance-checklist.md",
];

const quickCommands = [
  [npmBin, ["run", "startup:check"]],
  [npmBin, ["run", "backlog:validate"]],
  [npmBin, ["run", "docs:lint"]],
];

const fullCommands = [
  [npmBin, ["run", "test:db:reset:docker"]],
  [npmBin, ["run", "test:backend:docker"]],
  [npmBin, ["--prefix", "frontend", "run", "test", "--", "--watch=false", "--browsers=ChromeHeadless"]],
  [npmBin, ["run", "build:frontend"]],
  [npmBin, ["run", "run-patterns:gate:mountains:v2:docker"]],
  [npmBin, ["run", "run-patterns:gate:swamps:v2:docker"]],
];

function run(command, args, options = {}) {
  const label = [command, ...args].join(" ");
  console.log(`\n> ${label}`);
  const useShell = process.platform === "win32" && command.endsWith(".cmd");
  const result = spawnSync(useShell ? label : command, useShell ? [] : args, {
    cwd: root,
    stdio: "inherit",
    shell: useShell,
    ...options,
  });

  if (result.status !== 0) {
    throw new Error(`Command failed: ${label}`);
  }
}

function capture(command, args) {
  const useShell = process.platform === "win32" && command.endsWith(".cmd");
  const result = spawnSync(useShell ? [command, ...args].join(" ") : command, useShell ? [] : args, {
    cwd: root,
    encoding: "utf8",
    shell: useShell,
  });

  if (result.status !== 0) {
    throw new Error(`Command failed: ${[command, ...args].join(" ")}`);
  }

  return result.stdout.trim();
}

function assertRequiredFiles() {
  const missing = requiredFiles.filter((file) => !fs.existsSync(path.join(root, file)));
  if (missing.length > 0) {
    throw new Error(`Missing release-readiness references:\n- ${missing.join("\n- ")}`);
  }
}

function assertGeneratedArtifactHygiene() {
  const distStatus = capture("git", ["status", "--porcelain", "--", "frontend/dist"]);
  if (distStatus !== "") {
    throw new Error(`Generated frontend artifacts have uncommitted changes:\n${distStatus}`);
  }
}

function assertWorktreeHygiene() {
  const status = capture("git", ["status", "--porcelain"]);
  const nonGeneratedChanges = status
    .split("\n")
    .map((line) => line.trimEnd())
    .filter((line) => line !== "")
    .filter((line) => !line.includes("frontend/dist/") && !line.endsWith("frontend/dist"));

  if (nonGeneratedChanges.length > 0) {
    throw new Error(`Release readiness requires a clean source worktree:\n${nonGeneratedChanges.join("\n")}`);
  }
}

function printContext() {
  const branch = capture("git", ["branch", "--show-current"]) || "(detached)";
  const head = capture("git", ["rev-parse", "--short", "HEAD"]);
  console.log(`Release readiness check`);
  console.log(`Branch: ${branch}`);
  console.log(`HEAD: ${head}`);
  console.log(`Mode: ${full ? "full" : "quick"}`);
}

try {
  printContext();
  assertRequiredFiles();
  assertGeneratedArtifactHygiene();
  assertWorktreeHygiene();

  for (const [command, args] of quickCommands) {
    run(command, args);
  }

  if (full) {
    for (const [command, args] of fullCommands) {
      run(command, args);
    }
  } else {
    console.log("\nQuick readiness checks passed. Run `npm.cmd run release:check:full` before final release handoff.");
  }
} catch (error) {
  console.error("\nRelease readiness check failed.");
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
}
