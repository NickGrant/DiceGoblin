import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const required = ["AGENTS.md", "agent/ISSUES.md", "agent/MILESTONES.md", "README.md"];
const optional = ["agent/LLM_CONTEXT.md", "agent/ROLES.md", "agent/ISSUES_BACKLOG.md", "agent/MILESTONES_BACKLOG.md"];

const issueStatus = new Set(["open", "in progress", "blocked"]);
const issuePriority = new Set(["low", "medium", "high"]);

const milestoneStatus = new Set(["planned", "active", "blocked", "complete"]);

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

function getMarkdownField(block, label) {
  const m = block.match(new RegExp(`\\*\\*${label}:\\*\\*\\s*(.+)$`, "mi"));
  return m ? m[1].trim() : null;
}

function parseIssueSections(raw) {
  return raw
    .split(/\r?\n##\s+/)
    .slice(1)
    .flatMap((section) =>
      section
        .split(/\r?\n###\s+/)
        .slice(1)
        .map((entry) => entry.trim())
        .filter(Boolean),
    );
}

function parseMilestoneSections(raw) {
  return raw
    .split(/\r?\n##\s+/)
    .slice(1)
    .map((entry) => entry.trim())
    .filter(Boolean);
}

const errors = [];
const warnings = [];

for (const rel of required) {
  if (!readFileSafe(rel)) errors.push(`Missing required startup doc: ${rel}`);
}
for (const rel of optional) {
  if (!readFileSafe(rel)) warnings.push(`Optional doc not present: ${rel}`);
}

const issuesRaw = readFileSafe("agent/ISSUES.md");
if (issuesRaw) {
  const blocks = parseIssueSections(issuesRaw);
  for (const b of blocks) {
    const [titleLine] = b.split(/\r?\n/, 1);
    const title = titleLine ?? "<unknown>";
    const status = getMarkdownField(b, "Status");
    const priority = getMarkdownField(b, "Priority");

    if (!status || !issueStatus.has(status.toLowerCase())) errors.push(`agent/ISSUES.md: invalid status for "${title}": ${status}`);
    if (!priority || !issuePriority.has(priority.toLowerCase())) errors.push(`agent/ISSUES.md: invalid priority for "${title}": ${priority}`);
  }
}

const milestonesRaw = readFileSafe("agent/MILESTONES.md");
if (milestonesRaw) {
  const blocks = parseMilestoneSections(milestonesRaw);
  let activeCount = 0;
  for (const b of blocks) {
    const [nameLine] = b.split(/\r?\n/, 1);
    const name = nameLine ?? "<unknown>";
    const status = getMarkdownField(b, "Status");

    if (!status || !milestoneStatus.has(status.toLowerCase())) errors.push(`agent/MILESTONES.md: invalid status for "${name}": ${status}`);
    if (status?.toLowerCase() === "active") activeCount += 1;
  }
  if (activeCount > 1) errors.push("agent/MILESTONES.md: more than one milestone is marked Active");
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
