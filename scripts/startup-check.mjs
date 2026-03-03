import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const required = ["AGENTS.md", "ISSUES.md", "MILESTONES.md", "README.md"];
const optional = ["LLM_CONTEXT.md", "ROLES.md", "ISSUES_BACKLOG.md", "MILESTONES_BACKLOG.md"];

const issueStatus = new Set(["unstarted", "in-progress", "reopened", "blocked"]);
const issuePriority = new Set(["low", "medium", "high"]);
const issueExecution = new Set(["active", "deferred"]);
const issueReady = new Set(["yes", "no"]);

const milestoneStatus = new Set(["not-started", "in-progress", "complete", "blocked"]);
const milestoneWindow = new Set(["open", "closed"]);
const milestoneCurrent = new Set(["yes", "no"]);

function readFileSafe(rel) {
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) return null;
  return fs.readFileSync(abs, "utf8");
}

function parseBlocks(raw, key) {
  return raw
    .split(/\r?\n---\r?\n/g)
    .map((b) => b.trim())
    .filter((b) => new RegExp(`^${key}:\\s+`, "m").test(b));
}

function getField(block, key) {
  const m = block.match(new RegExp(`^${key}:\\s*(.+)$`, "m"));
  return m ? m[1].trim() : null;
}

const errors = [];
const warnings = [];

for (const rel of required) {
  if (!readFileSafe(rel)) errors.push(`Missing required startup doc: ${rel}`);
}
for (const rel of optional) {
  if (!readFileSafe(rel)) warnings.push(`Optional doc not present: ${rel}`);
}

const issuesRaw = readFileSafe("ISSUES.md");
if (issuesRaw) {
  const blocks = parseBlocks(issuesRaw, "title");
  for (const b of blocks) {
    const title = getField(b, "title") ?? "<unknown>";
    const status = getField(b, "status");
    const priority = getField(b, "priority");
    const execution = getField(b, "execution");
    const ready = getField(b, "ready");

    if (!status || !issueStatus.has(status)) errors.push(`ISSUES.md: invalid status for "${title}": ${status}`);
    if (!priority || !issuePriority.has(priority)) errors.push(`ISSUES.md: invalid priority for "${title}": ${priority}`);
    if (!execution || !issueExecution.has(execution)) errors.push(`ISSUES.md: invalid execution for "${title}": ${execution}`);
    if (!ready || !issueReady.has(ready)) errors.push(`ISSUES.md: invalid ready for "${title}": ${ready}`);
  }
}

const milestonesRaw = readFileSafe("MILESTONES.md");
if (milestonesRaw) {
  const blocks = parseBlocks(milestonesRaw, "name");
  let currentYes = 0;
  for (const b of blocks) {
    const name = getField(b, "name") ?? "<unknown>";
    if (name.startsWith("<")) continue;

    const status = getField(b, "status");
    const window = getField(b, "execution_window");
    const current = getField(b, "is_current");

    if (!status || !milestoneStatus.has(status)) errors.push(`MILESTONES.md: invalid status for "${name}": ${status}`);
    if (!window || !milestoneWindow.has(window)) errors.push(`MILESTONES.md: invalid execution_window for "${name}": ${window}`);
    if (!current || !milestoneCurrent.has(current)) errors.push(`MILESTONES.md: invalid is_current for "${name}": ${current}`);
    if (current === "yes") currentYes += 1;
  }
  if (currentYes > 1) errors.push("MILESTONES.md: more than one milestone has is_current: yes");
}

if (warnings.length) {
  console.log("Startup check warnings:");
  for (const w of warnings) console.log(`- ${w}`);
}

if (errors.length) {
  console.error("Startup check failed:");
  for (const e of errors) console.error(`- ${e}`);
  process.exit(1);
}

console.log("Startup check passed.");
