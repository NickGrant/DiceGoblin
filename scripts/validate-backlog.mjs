import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const issuesPath = path.join(root, "agent", "ISSUES.md");
const milestonesPath = path.join(root, "agent", "MILESTONES.md");

const allowedIssueStatus = new Set(["open", "in progress", "blocked"]);
const allowedPriority = new Set(["low", "medium", "high"]);
const allowedMilestoneStatus = new Set(["planned", "active", "complete", "blocked"]);

function parseIssueSections(raw) {
  const sections = [];
  const milestoneSections = raw.split(/\r?\n##\s+/).slice(1);

  for (const section of milestoneSections) {
    const [milestoneNameLine, ...rest] = section.split(/\r?\n/);
    const milestoneName = (milestoneNameLine ?? "").trim();
    const body = rest.join("\n");

    for (const issueEntry of body.split(/\r?\n###\s+/).slice(1)) {
      const trimmed = issueEntry.trim();
      if (!trimmed) continue;
      sections.push({
        milestoneName,
        raw: trimmed,
      });
    }
  }

  return sections;
}

function parseMilestoneSections(raw) {
  return raw
    .split(/\r?\n##\s+/)
    .slice(1)
    .map((section) => section.trim())
    .filter(Boolean);
}

function markdownField(block, key) {
  const m = block.match(new RegExp(`\\*\\*${key}:\\*\\*\\s*(.+)$`, "mi"));
  return m ? m[1].trim() : null;
}

function normalizeIssueTitle(title) {
  return title.replace(/^[A-Z0-9-]+:\s*/, "").replace(/[.]+$/, "").trim();
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
  const m = block.match(/^###\s+Related Issues\s*\r?\n([\s\S]*?)(?:\r?\n##\s|\r?\n###\s|$)/m);
  if (!m) return [];
  return m[1]
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.startsWith("- "))
    .map((line) => line.slice(2).trim().replace(/^[A-Z0-9-]+:\s*/, ""))
    .filter(Boolean);
}

function fail(errors) {
  console.error("Backlog validation failed:");
  errors.forEach((e) => console.error(`- ${e}`));
  process.exit(1);
}

const errors = [];

if (!fs.existsSync(issuesPath)) errors.push("Missing agent/ISSUES.md");
if (!fs.existsSync(milestonesPath)) errors.push("Missing agent/MILESTONES.md");
if (errors.length) fail(errors);

const issuesRaw = fs.readFileSync(issuesPath, "utf8");
const milestonesRaw = fs.readFileSync(milestonesPath, "utf8");

const issueBlocks = parseIssueSections(issuesRaw);
const milestoneBlocks = parseMilestoneSections(milestonesRaw);

const issueTitles = [];
const normalizedIssueTitles = [];
for (const entry of issueBlocks) {
  const [title] = entry.raw.split(/\r?\n/, 1);
  if (!title) {
    errors.push("Issue block missing title");
    continue;
  }
  if (issueTitles.includes(title)) errors.push(`Duplicate issue title: ${title}`);
  issueTitles.push(title);
  normalizedIssueTitles.push(normalizeIssueTitle(title));

  const status = markdownField(entry.raw, "Status");
  const priority = markdownField(entry.raw, "Priority");
  const milestone = entry.milestoneName;
  const description = entry.raw.match(/####\s+Problem/i) ? "present" : null;

  if (!status || !allowedIssueStatus.has(status.toLowerCase())) errors.push(`Issue "${title}" has invalid status: ${status}`);
  if (!priority || !allowedPriority.has(priority.toLowerCase())) errors.push(`Issue "${title}" has invalid priority: ${priority}`);
  if (!description) errors.push(`Issue "${title}" missing description`);
  if (!milestone) errors.push(`Issue "${title}" missing milestone field`);

  for (const dep of parseIssueTitlesList(entry.raw, "blocked_by")) {
    if (dep.startsWith("<")) continue;
    if (!issueTitles.includes(dep) && !issuesRaw.includes(`title: ${dep}`)) {
      errors.push(`Issue "${title}" blocked_by references unknown issue: ${dep}`);
    }
  }
  for (const dep of parseIssueTitlesList(entry.raw, "enables")) {
    if (dep.startsWith("<")) continue;
    if (!issueTitles.includes(dep) && !issuesRaw.includes(`title: ${dep}`)) {
      errors.push(`Issue "${title}" enables references unknown issue: ${dep}`);
    }
  }
}

const milestoneNames = [];
let activeCount = 0;

for (const b of milestoneBlocks) {
  const [name] = b.split(/\r?\n/, 1);
  if (!name) {
    errors.push("Milestone block missing name");
    continue;
  }
  if (milestoneNames.includes(name)) errors.push(`Duplicate milestone name: ${name}`);
  milestoneNames.push(name);

  const status = markdownField(b, "Status");
  const issues = parseMilestoneIssues(b);

  if (!status || !allowedMilestoneStatus.has(status.toLowerCase())) errors.push(`Milestone "${name}" has invalid status: ${status}`);
  if (status?.toLowerCase() === "active") activeCount += 1;

  if (issues.length === 0 && status?.toLowerCase() !== "planned") {
    errors.push(`Milestone "${name}" has no related issues but is not marked Planned`);
  }
  for (const issueTitle of issues) {
    if (!normalizedIssueTitles.includes(normalizeIssueTitle(issueTitle))) {
      errors.push(`Milestone "${name}" references missing issue: ${issueTitle}`);
    }
  }
}

if (activeCount > 1) errors.push("More than one milestone is marked Active");

for (const entry of issueBlocks) {
  const [title] = entry.raw.split(/\r?\n/, 1);
  const milestone = entry.milestoneName;
  if (!title || !milestone) continue;
  if (!milestoneNames.includes(milestone)) {
    errors.push(`Issue "${title}" points to unknown milestone: ${milestone}`);
  }
}

if (errors.length) fail(errors);
console.log("Backlog validation passed.");
