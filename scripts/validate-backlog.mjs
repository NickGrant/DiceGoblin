import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const issuesPath = path.join(root, "ISSUES.md");
const milestonesPath = path.join(root, "MILESTONES.md");

const allowedIssueStatus = new Set(["unstarted", "in-progress", "reopened", "blocked"]);
const allowedPriority = new Set(["low", "medium", "high"]);
const allowedExecution = new Set(["active", "deferred"]);
const allowedReady = new Set(["yes", "no"]);
const allowedMilestoneStatus = new Set(["not-started", "in-progress", "complete", "blocked"]);
const allowedWindow = new Set(["open", "closed"]);
const allowedCurrent = new Set(["yes", "no"]);

function parseBlocks(raw) {
  return raw
    .split(/\r?\n---\r?\n/g)
    .map((b) => b.trim())
    .filter((b) => /^title:\s+/m.test(b) || /^name:\s+/m.test(b));
}

function field(block, key) {
  const m = block.match(new RegExp(`^${key}:\\s*(.+)$`, "m"));
  return m ? m[1].trim() : null;
}

function parseIssueTitlesList(block, key) {
  const m = block.match(new RegExp(`^${key}:\\s*\\r?\\n([\\s\\S]*?)(?:\\r?\\n[a-z_]+:\\s|$)`, "m"));
  if (!m) return [];
  return m[1]
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.startsWith("- "))
    .map((line) => line.slice(2).trim())
    .filter(Boolean);
}

function parseMilestoneIssues(block) {
  const m = block.match(/^issues:\s*\r?\n([\s\S]*?)(?:\r?\ndescription:|\r?\nentry_criteria:|\r?\nexit_criteria:|$)/m);
  if (!m) return [];
  return m[1]
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.startsWith("- "))
    .map((line) => line.slice(2).trim())
    .filter(Boolean);
}

function fail(errors) {
  console.error("Backlog validation failed:");
  errors.forEach((e) => console.error(`- ${e}`));
  process.exit(1);
}

const errors = [];

if (!fs.existsSync(issuesPath)) errors.push("Missing ISSUES.md");
if (!fs.existsSync(milestonesPath)) errors.push("Missing MILESTONES.md");
if (errors.length) fail(errors);

const issuesRaw = fs.readFileSync(issuesPath, "utf8");
const milestonesRaw = fs.readFileSync(milestonesPath, "utf8");

const issueBlocks = parseBlocks(issuesRaw).filter((b) => /^title:\s+/m.test(b));
const milestoneBlocks = parseBlocks(milestonesRaw)
  .filter((b) => /^name:\s+/m.test(b))
  .filter((b) => !/^name:\s*<milestone name>/m.test(b));

const issueTitles = [];
for (const b of issueBlocks) {
  const title = field(b, "title");
  if (!title) {
    errors.push("Issue block missing title");
    continue;
  }
  if (issueTitles.includes(title)) errors.push(`Duplicate issue title: ${title}`);
  issueTitles.push(title);

  const status = field(b, "status");
  const priority = field(b, "priority");
  const execution = field(b, "execution");
  const ready = field(b, "ready");
  const description = field(b, "description");
  const milestone = field(b, "milestone");

  if (!status || !allowedIssueStatus.has(status)) errors.push(`Issue "${title}" has invalid status: ${status}`);
  if (!priority || !allowedPriority.has(priority)) errors.push(`Issue "${title}" has invalid priority: ${priority}`);
  if (!execution || !allowedExecution.has(execution)) errors.push(`Issue "${title}" has invalid execution: ${execution}`);
  if (!ready || !allowedReady.has(ready)) errors.push(`Issue "${title}" has invalid ready: ${ready}`);
  if (!description) errors.push(`Issue "${title}" missing description`);
  if (!milestone) errors.push(`Issue "${title}" missing milestone field`);

  for (const dep of parseIssueTitlesList(b, "blocked_by")) {
    if (dep.startsWith("<")) continue;
    if (!issueTitles.includes(dep) && !issuesRaw.includes(`title: ${dep}`)) {
      errors.push(`Issue "${title}" blocked_by references unknown issue: ${dep}`);
    }
  }
  for (const dep of parseIssueTitlesList(b, "enables")) {
    if (dep.startsWith("<")) continue;
    if (!issueTitles.includes(dep) && !issuesRaw.includes(`title: ${dep}`)) {
      errors.push(`Issue "${title}" enables references unknown issue: ${dep}`);
    }
  }
}

const milestoneNames = [];
let currentYesCount = 0;

for (const b of milestoneBlocks) {
  const name = field(b, "name");
  if (!name) {
    errors.push("Milestone block missing name");
    continue;
  }
  if (milestoneNames.includes(name)) errors.push(`Duplicate milestone name: ${name}`);
  milestoneNames.push(name);

  const status = field(b, "status");
  const window = field(b, "execution_window");
  const current = field(b, "is_current");
  const issues = parseMilestoneIssues(b);

  if (!status || !allowedMilestoneStatus.has(status)) errors.push(`Milestone "${name}" has invalid status: ${status}`);
  if (!window || !allowedWindow.has(window)) errors.push(`Milestone "${name}" has invalid execution_window: ${window}`);
  if (!current || !allowedCurrent.has(current)) errors.push(`Milestone "${name}" has invalid is_current: ${current}`);
  if (current === "yes") currentYesCount += 1;

  if (issues.length === 0 && status !== "not-started") {
    errors.push(`Milestone "${name}" has no issues but status is not-started requirement is violated`);
  }
  for (const issueTitle of issues) {
    if (!issueTitles.includes(issueTitle)) {
      errors.push(`Milestone "${name}" references missing issue: ${issueTitle}`);
    }
  }
}

if (currentYesCount > 1) errors.push("More than one milestone has is_current: yes");

for (const b of issueBlocks) {
  const title = field(b, "title");
  const milestone = field(b, "milestone");
  if (!title || !milestone || milestone === "unassigned") continue;
  if (!milestoneNames.includes(milestone)) {
    errors.push(`Issue "${title}" points to unknown milestone: ${milestone}`);
  }
}

if (errors.length) fail(errors);
console.log("Backlog validation passed.");
