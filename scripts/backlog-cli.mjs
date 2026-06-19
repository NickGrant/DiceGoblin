import fs from "node:fs";
import path from "node:path";
import { spawnSync } from "node:child_process";

const ACTIVE_ISSUES_PATH = path.join("agent", "ISSUES.md");
const BACKLOG_ISSUES_PATH = path.join("agent", "ISSUES_BACKLOG.md");
const ARCHIVE_ISSUES_PATH = path.join("agent", "ISSUES_ARCHIVE.md");
const ACTIVE_MILESTONES_PATH = path.join("agent", "MILESTONES.md");
const BACKLOG_MILESTONES_PATH = path.join("agent", "MILESTONES_BACKLOG.md");
const ARCHIVE_MILESTONES_PATH = path.join("agent", "MILESTONES_ARCHIVE.md");

const SOURCE_TO_PATH = {
  issue: {
    active: ACTIVE_ISSUES_PATH,
    backlog: BACKLOG_ISSUES_PATH,
    archive: ARCHIVE_ISSUES_PATH,
  },
  milestone: {
    active: ACTIVE_MILESTONES_PATH,
    backlog: BACKLOG_MILESTONES_PATH,
    archive: ARCHIVE_MILESTONES_PATH,
  },
};

const ISSUE_STATUS_BY_SOURCE = {
  active: new Set(["Open", "In Progress", "Blocked", "Complete"]),
  backlog: new Set(["open", "in progress", "blocked", "complete", "unstarted"]),
  archive: new Set(["open", "in progress", "blocked", "complete", "unstarted"]),
};

const ISSUE_PRIORITIES = new Set(["low", "medium", "high"]);
const MILESTONE_STATUS_BY_SOURCE = {
  active: new Set(["Planned", "Active", "Blocked", "Complete"]),
  backlog: new Set(["planned", "active", "blocked", "complete", "not-started"]),
  archive: new Set(["planned", "active", "blocked", "complete", "not-started"]),
};

main();

function main() {
  const { positionals, options } = parseArgs(process.argv.slice(2));
  const command = positionals[0];

  if (!command || options.help) {
    printUsage();
    process.exit(options.help ? 0 : 1);
  }

  const root = path.resolve(options.root ?? process.cwd());
  const backlog = loadBacklog(root);

  try {
    switch (command) {
      case "list":
        handleList(backlog, options);
        return;
      case "get":
        handleGet(backlog, options);
        return;
      case "add":
        handleAdd(backlog, options, root);
        return;
      case "update":
        handleUpdate(backlog, options, root);
        return;
      case "move":
        handleMove(backlog, options, root);
        return;
      case "delete":
        handleDelete(backlog, options, root);
        return;
      case "complete":
        handleComplete(backlog, options, root);
        return;
      case "set-active-milestone":
        handleSetActiveMilestone(backlog, options, root);
        return;
      case "validate":
        handleValidate(root);
        return;
      default:
        fail(`Unknown command "${command}".`);
    }
  } catch (error) {
    fail(error instanceof Error ? error.message : String(error));
  }
}

function printUsage() {
  console.log(`Usage:
  npm run backlog -- <command> [options]

Commands:
  list
  get
  add
  update
  move
  delete
  complete
  set-active-milestone
  validate

Common selectors:
  --type issue|milestone
  --source active|backlog|archive|all
  --id <issue id>
  --ids <comma,separated,issue,ids>
  --title <issue title>
  --name <milestone name>
  --input <json file>
  --json
  --dry-run

Examples:
  npm run backlog -- list --type issue --source active --json
  npm run backlog -- get --type issue --id UPR-003 --json
  npm run backlog -- update --type issue --id UPR-004 --status "In Progress"
  npm run backlog -- complete --type issue --id UPR-004
  npm run backlog -- move --type issue --id UPR-004 --to archive
  npm run backlog -- set-active-milestone --name "Unit Progression Rework"
  npm run backlog -- add --type issue --source active --input new-issue.json`);
}

function parseArgs(argv) {
  const positionals = [];
  const options = {};

  for (let index = 0; index < argv.length; index += 1) {
    const token = argv[index];
    if (!token.startsWith("--")) {
      positionals.push(token);
      continue;
    }

    const key = token.slice(2);
    const next = argv[index + 1];
    if (next === undefined || next.startsWith("--")) {
      options[key] = true;
      continue;
    }

    options[key] = next;
    index += 1;
  }

  return { positionals, options };
}

function handleList(backlog, options) {
  const type = normalizeType(options.type);
  const source = normalizeSource(options.source ?? "all");
  const entries = collectEntries(backlog, type, source).filter((entry) => matchesFilters(entry, options));
  emit(entries.map(toSerializableEntry), options);
}

function handleGet(backlog, options) {
  const type = normalizeType(options.type);
  const source = normalizeSource(options.source ?? "all");
  const matches = findEntries(backlog, type, source, selectorsFromOptions(type, options));
  if (matches.length !== 1) {
    fail(`Expected exactly one ${type}, found ${matches.length}.`);
  }
  emit(toSerializableEntry(matches[0]), options);
}

function handleAdd(backlog, options, root) {
  const type = normalizeType(options.type);
  const source = normalizeWritableSource(options.source);
  const payload = readJsonInput(options.input);
  const entry = normalizeAddedEntry(type, source, payload);

  if (type === "issue") {
    assertUniqueIssue(backlog, entry);
    addIssue(backlog, source, entry);
  } else {
    assertUniqueMilestone(backlog, entry);
    addMilestone(backlog, source, entry);
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ added: toSerializableEntry(entry) }, options);
}

function handleUpdate(backlog, options, root) {
  const type = normalizeType(options.type);
  const source = normalizeSource(options.source ?? "all");
  const matches = findEntries(backlog, type, source, selectorsFromOptions(type, options));
  if (matches.length === 0) {
    fail(`No ${type} entries matched the provided selector.`);
  }

  for (const entry of matches) {
    applyUpdates(entry, type, options);
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ updated: matches.map(toSerializableEntry) }, options);
}

function handleMove(backlog, options, root) {
  const type = normalizeType(options.type);
  const source = normalizeWritableSource(options.source ?? "all");
  const target = normalizeWritableSource(options.to);
  if (source === target) {
    fail("Source and destination are the same.");
  }

  const matches = findEntries(backlog, type, source, selectorsFromOptions(type, options));
  if (matches.length === 0) {
    fail(`No ${type} entries matched the provided selector.`);
  }

  for (const entry of matches) {
    removeEntry(backlog, entry);
    entry.source = target;
    if (type === "issue") {
      addIssue(backlog, target, entry);
    } else {
      addMilestone(backlog, target, entry);
    }
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ moved: matches.map(toSerializableEntry), destination: target }, options);
}

function handleDelete(backlog, options, root) {
  const type = normalizeType(options.type);
  const source = normalizeSource(options.source ?? "all");
  const matches = findEntries(backlog, type, source, selectorsFromOptions(type, options));
  if (matches.length === 0) {
    fail(`No ${type} entries matched the provided selector.`);
  }

  for (const entry of matches) {
    removeEntry(backlog, entry);
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ deleted: matches.map(toSerializableEntry) }, options);
}

function handleComplete(backlog, options, root) {
  const type = normalizeType(options.type);
  const source = normalizeSource(options.source ?? "all");
  const matches = findEntries(backlog, type, source, selectorsFromOptions(type, options));
  if (matches.length === 0) {
    fail(`No ${type} entries matched the provided selector.`);
  }

  for (const entry of matches) {
    entry.status = type === "issue" ? normalizeIssueStatus("Complete", entry.source) : normalizeMilestoneStatus("Complete", entry.source);
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ completed: matches.map(toSerializableEntry) }, options);
}

function handleSetActiveMilestone(backlog, options, root) {
  const name = cleanScalar(options.name);
  if (!name) {
    fail("Missing required --name for set-active-milestone.");
  }

  let found = false;
  for (const milestone of backlog.milestones.active.sections) {
    milestone.status = normalizeMilestoneStatus(milestone.name === name ? "Active" : milestone.status === "Active" ? "Planned" : milestone.status, "active");
    if (milestone.name === name) {
      milestone.status = "Active";
      found = true;
    }
  }

  if (!found) {
    fail(`Could not find active milestone named "${name}".`);
  }

  if (persistBacklog(backlog, options, root)) {
    return;
  }
  emit({ activeMilestone: name }, options);
}

function handleValidate(root) {
  const result = spawnSync(process.execPath, [path.join(root, "scripts", "validate-backlog.mjs")], {
    cwd: root,
    encoding: "utf8",
    stdio: "inherit",
  });
  process.exit(result.status ?? 1);
}

function selectorsFromOptions(type, options) {
  const ids = parseCsv(options.ids);
  if (options.id) {
    ids.push(options.id);
  }

  const titles = parseCsv(options.titles);
  if (options.title) {
    titles.push(options.title);
  }

  const names = parseCsv(options.names);
  if (options.name) {
    names.push(options.name);
  }

  if (type === "issue" && ids.length === 0 && titles.length === 0) {
    fail("Issue commands require --id/--ids or --title/--titles.");
  }
  if (type === "milestone" && names.length === 0) {
    fail("Milestone commands require --name/--names.");
  }

  return {
    ids: ids.map((value) => cleanScalar(value)).filter(Boolean),
    titles: titles.map((value) => cleanScalar(value)).filter(Boolean),
    names: names.map((value) => cleanScalar(value)).filter(Boolean),
  };
}

function parseCsv(value) {
  if (!value || typeof value !== "string") {
    return [];
  }

  return value
    .split(",")
    .map((entry) => entry.trim())
    .filter(Boolean);
}

function normalizeType(value) {
  const normalized = cleanScalar(value).toLowerCase();
  if (normalized === "issue" || normalized === "issues") {
    return "issue";
  }
  if (normalized === "milestone" || normalized === "milestones") {
    return "milestone";
  }
  fail(`Unsupported type "${value}". Use issue or milestone.`);
}

function normalizeSource(value) {
  const normalized = cleanScalar(value).toLowerCase();
  if (["active", "backlog", "archive", "all"].includes(normalized)) {
    return normalized;
  }
  fail(`Unsupported source "${value}". Use active, backlog, archive, or all.`);
}

function normalizeWritableSource(value) {
  const normalized = normalizeSource(value);
  if (normalized === "all") {
    fail("Writable commands require a concrete source, not all.");
  }
  return normalized;
}

function matchesFilters(entry, options) {
  if (options.status && cleanScalar(entry.status).toLowerCase() !== cleanScalar(options.status).toLowerCase()) {
    return false;
  }
  if (options.priority && "priority" in entry && cleanScalar(entry.priority ?? "").toLowerCase() !== cleanScalar(options.priority).toLowerCase()) {
    return false;
  }
  if (options.milestone && "milestone" in entry && cleanScalar(entry.milestone ?? "").toLowerCase() !== cleanScalar(options.milestone).toLowerCase()) {
    return false;
  }
  return true;
}

function findEntries(backlog, type, source, selectors) {
  return collectEntries(backlog, type, source).filter((entry) => {
    if (type === "issue") {
      const idMatch = selectors.ids.length > 0 && selectors.ids.includes(entry.id);
      const titleMatch = selectors.titles.length > 0 && selectors.titles.includes(entry.title);
      return idMatch || titleMatch;
    }

    return selectors.names.includes(entry.name);
  });
}

function collectEntries(backlog, type, source) {
  const sources = source === "all" ? ["active", "backlog", "archive"] : [source];
  const out = [];
  for (const entrySource of sources) {
    const container = type === "issue" ? backlog.issues[entrySource] : backlog.milestones[entrySource];
    if (entrySource === "active") {
      if (type === "issue") {
        for (const section of container.sections) {
          for (const issue of section.issues) {
            out.push(issue);
          }
        }
      } else {
        for (const section of container.sections) {
          out.push(section);
        }
      }
      continue;
    }

    for (const entry of container.entries) {
      out.push(entry);
    }
  }
  return out;
}

function loadBacklog(root) {
  return {
    root,
    issues: {
      active: parseActiveIssuesDoc(readRequired(root, ACTIVE_ISSUES_PATH)),
      backlog: parseRecordDoc(readRequired(root, BACKLOG_ISSUES_PATH), "issue", "backlog"),
      archive: parseRecordDoc(readRequired(root, ARCHIVE_ISSUES_PATH), "issue", "archive"),
    },
    milestones: {
      active: parseActiveMilestonesDoc(readRequired(root, ACTIVE_MILESTONES_PATH)),
      backlog: parseRecordDoc(readRequired(root, BACKLOG_MILESTONES_PATH), "milestone", "backlog"),
      archive: parseRecordDoc(readRequired(root, ARCHIVE_MILESTONES_PATH), "milestone", "archive"),
    },
  };
}

function readRequired(root, relativePath) {
  const absolutePath = path.join(root, relativePath);
  if (!fs.existsSync(absolutePath)) {
    fail(`Missing required file ${relativePath}`);
  }
  return fs.readFileSync(absolutePath, "utf8");
}

function parseActiveIssuesDoc(raw) {
  const headerEnd = raw.search(/^##\s+/m);
  const header = headerEnd >= 0 ? raw.slice(0, headerEnd).trimEnd() : raw.trimEnd();
  const sections = [];
  const sectionMatches = Array.from(raw.matchAll(/^##\s+(.+)$/gm));

  for (let sectionIndex = 0; sectionIndex < sectionMatches.length; sectionIndex += 1) {
    const match = sectionMatches[sectionIndex];
    const sectionStart = match.index ?? 0;
    const sectionEnd = sectionMatches[sectionIndex + 1]?.index ?? raw.length;
    const sectionRaw = raw.slice(sectionStart, sectionEnd).trim();
    const lines = sectionRaw.split(/\r?\n/);
    const name = lines[0].replace(/^##\s+/, "").trim();
    const body = lines.slice(1).join("\n");
    const issues = [];
    const issueMatches = Array.from(body.matchAll(/^###\s+(.+)$/gm));

    for (let issueIndex = 0; issueIndex < issueMatches.length; issueIndex += 1) {
      const issueMatch = issueMatches[issueIndex];
      const issueStart = issueMatch.index ?? 0;
      const issueEnd = issueMatches[issueIndex + 1]?.index ?? body.length;
      const issueRaw = body.slice(issueStart, issueEnd).trim();
      const issueLines = issueRaw.split(/\r?\n/);
      const heading = issueLines[0].replace(/^###\s+/, "").trim();
      const { id, title } = splitIssueHeading(heading);
      const content = issueLines.slice(1).join("\n").trim();
      issues.push(normalizeActiveIssueEntry(name, heading, id, title, content));
    }

    sections.push({
      kind: "issue",
      source: "active",
      name,
      issues,
    });
  }

  return { header, sections };
}

function parseActiveMilestonesDoc(raw) {
  const headerEnd = raw.search(/^##\s+/m);
  const header = headerEnd >= 0 ? raw.slice(0, headerEnd).trimEnd() : raw.trimEnd();
  const sections = [];
  const sectionMatches = Array.from(raw.matchAll(/^##\s+(.+)$/gm));

  for (let sectionIndex = 0; sectionIndex < sectionMatches.length; sectionIndex += 1) {
    const match = sectionMatches[sectionIndex];
    const sectionStart = match.index ?? 0;
    const sectionEnd = sectionMatches[sectionIndex + 1]?.index ?? raw.length;
    const sectionRaw = raw.slice(sectionStart, sectionEnd).trim();
    const lines = sectionRaw.split(/\r?\n/);
    const name = lines[0].replace(/^##\s+/, "").trim();
    const content = lines.slice(1).join("\n").trim();
    sections.push(normalizeActiveMilestoneEntry(name, content));
  }

  return { header, sections };
}

function parseRecordDoc(raw, type, source) {
  const firstRecordIndex = raw.search(/^\s*(---\s*$|(?:id|title|name):\s+)/m);
  const header = firstRecordIndex >= 0 ? raw.slice(0, firstRecordIndex).trimEnd() : raw.trimEnd();
  const body = firstRecordIndex >= 0 ? raw.slice(firstRecordIndex) : "";
  const blocks = body
    .split(/\r?\n---\r?\n/g)
    .map((block) => block.trim())
    .filter(Boolean)
    .map((block) => (block.startsWith("---") ? block.replace(/^---\s*/, "") : block))
    .filter(Boolean);

  const entries = blocks.map((block) => {
    const record = parseRecordBlock(block);
    return type === "issue"
      ? normalizeRecordIssueEntry(record, source)
      : normalizeRecordMilestoneEntry(record, source);
  });

  return { header, entries };
}

function parseRecordBlock(block) {
  const lines = block.split(/\r?\n/);
  const record = {};
  let index = 0;

  while (index < lines.length) {
    const line = lines[index];
    const keyMatch = line.match(/^([a-zA-Z_]+):\s*(.*)$/);
    if (!keyMatch) {
      index += 1;
      continue;
    }

    const key = keyMatch[1];
    const rawValue = keyMatch[2];

    if (rawValue === "|") {
      index += 1;
      const buffer = [];
      while (index < lines.length && (lines[index].startsWith("  ") || lines[index] === "")) {
        buffer.push(lines[index].replace(/^  /, ""));
        index += 1;
      }
      record[key] = buffer.join("\n").trimEnd();
      continue;
    }

    if (rawValue === "") {
      index += 1;
      const items = [];
      while (index < lines.length && lines[index].startsWith("  - ")) {
        items.push(lines[index].replace(/^  - /, "").trim());
        index += 1;
      }
      if (items.length > 0) {
        record[key] = items;
        continue;
      }

      record[key] = "";
      continue;
    }

    record[key] = rawValue.trim();
    index += 1;
  }

  return record;
}

function normalizeActiveIssueEntry(milestone, heading, id, title, content) {
  return {
    kind: "issue",
    source: "active",
    milestone,
    heading,
    id,
    title,
    status: normalizeIssueStatus(readMarkdownField(content, "Status") ?? "Open", "active"),
    priority: normalizePriority(readMarkdownField(content, "Priority") ?? "Medium"),
    problem: extractMarkdownSectionBody(content, "Problem", 4),
    acceptanceCriteria: extractMarkdownBullets(content, "Acceptance Criteria", 4),
    currentCodeReferences: extractMarkdownBullets(content, "Current Code References", 4),
    resolution: extractMarkdownSectionBody(content, "Resolution", 4),
    content,
  };
}

function normalizeActiveMilestoneEntry(name, content) {
  return {
    kind: "milestone",
    source: "active",
    name,
    status: normalizeMilestoneStatus(readMarkdownField(content, "Status") ?? "Planned", "active"),
    purpose: readMarkdownField(content, "Purpose") ?? "",
    goals: extractMarkdownBullets(content, "Goals", 3),
    currentCodeContext: extractMarkdownSectionBody(content, "Current Code Context", 3),
    exitCriteria: extractMarkdownBullets(content, "Exit Criteria", 3),
    relatedIssues: extractMarkdownBullets(content, "Related Issues", 3),
    resolution: extractMarkdownSectionBody(content, "Resolution", 3),
    content,
  };
}

function normalizeRecordIssueEntry(record, source) {
  const id = cleanScalar(record.id ?? "");
  const title = cleanScalar(record.title ?? "");
  return {
    kind: "issue",
    source,
    id,
    title,
    heading: id ? `${id}: ${title}` : title,
    milestone: cleanScalar(record.milestone ?? ""),
    status: normalizeIssueStatus(record.status ?? "open", source),
    priority: normalizePriority(record.priority ?? "medium"),
    problem: cleanMultiline(record.problem ?? record.description ?? ""),
    acceptanceCriteria: normalizeStringList(record.acceptance_criteria),
    currentCodeReferences: normalizeStringList(record.current_code_references),
    resolution: cleanMultiline(record.resolution ?? ""),
    execution: cleanScalar(record.execution ?? ""),
    ready: cleanScalar(record.ready ?? ""),
  };
}

function normalizeRecordMilestoneEntry(record, source) {
  return {
    kind: "milestone",
    source,
    name: cleanScalar(record.name ?? ""),
    status: normalizeMilestoneStatus(record.status ?? "planned", source),
    purpose: cleanMultiline(record.purpose ?? record.description ?? ""),
    goals: normalizeStringList(record.goals),
    currentCodeContext: cleanMultiline(record.current_code_context ?? ""),
    exitCriteria: normalizeStringList(record.exit_criteria),
    relatedIssues: normalizeStringList(record.related_issues ?? record.issues),
    resolution: cleanMultiline(record.resolution ?? record.Resolution ?? ""),
    executionWindow: cleanScalar(record.execution_window ?? ""),
    isCurrent: cleanScalar(record.is_current ?? ""),
  };
}

function normalizeAddedEntry(type, source, payload) {
  if (type === "issue") {
    const id = cleanScalar(payload.id ?? "");
    const title = cleanScalar(payload.title ?? "");
    const milestone = cleanScalar(payload.milestone ?? "");
    if (!title) {
      fail("Issue add payload requires title.");
    }
    if (source === "active" && (!id || !milestone)) {
      fail("Active issue add payload requires id and milestone.");
    }

    return {
      kind: "issue",
      source,
      id,
      title,
      heading: id ? `${id}: ${title}` : title,
      milestone,
      status: normalizeIssueStatus(payload.status ?? (source === "active" ? "Open" : "open"), source),
      priority: normalizePriority(payload.priority ?? "medium"),
      problem: cleanMultiline(payload.problem ?? payload.description ?? ""),
      acceptanceCriteria: normalizeStringList(payload.acceptanceCriteria ?? payload.acceptance_criteria),
      currentCodeReferences: normalizeStringList(payload.currentCodeReferences ?? payload.current_code_references),
      resolution: cleanMultiline(payload.resolution ?? ""),
      execution: cleanScalar(payload.execution ?? ""),
      ready: cleanScalar(payload.ready ?? ""),
      content: "",
    };
  }

  const name = cleanScalar(payload.name ?? "");
  if (!name) {
    fail("Milestone add payload requires name.");
  }

  return {
    kind: "milestone",
    source,
    name,
    status: normalizeMilestoneStatus(payload.status ?? (source === "active" ? "Planned" : "planned"), source),
    purpose: cleanMultiline(payload.purpose ?? payload.description ?? ""),
    goals: normalizeStringList(payload.goals),
    currentCodeContext: cleanMultiline(payload.currentCodeContext ?? payload.current_code_context ?? ""),
    exitCriteria: normalizeStringList(payload.exitCriteria ?? payload.exit_criteria),
    relatedIssues: normalizeStringList(payload.relatedIssues ?? payload.related_issues ?? payload.issues),
    resolution: cleanMultiline(payload.resolution ?? ""),
    executionWindow: cleanScalar(payload.executionWindow ?? payload.execution_window ?? ""),
    isCurrent: cleanScalar(payload.isCurrent ?? payload.is_current ?? ""),
    content: "",
  };
}

function addIssue(backlog, source, entry) {
  if (source === "active") {
    const sections = backlog.issues.active.sections;
    let section = sections.find((candidate) => candidate.name === entry.milestone);
    if (!section) {
      section = { kind: "issue", source: "active", name: entry.milestone, issues: [] };
      sections.push(section);
    }
    section.issues.push(entry);
    return;
  }

  backlog.issues[source].entries.push(entry);
}

function addMilestone(backlog, source, entry) {
  if (source === "active") {
    backlog.milestones.active.sections.push(entry);
    return;
  }

  backlog.milestones[source].entries.push(entry);
}

function applyUpdates(entry, type, options) {
  if (options.status) {
    entry.status = type === "issue"
      ? normalizeIssueStatus(options.status, entry.source)
      : normalizeMilestoneStatus(options.status, entry.source);
  }

  if (type === "issue") {
    if (options.priority) {
      entry.priority = normalizePriority(options.priority);
    }
    if (options.milestone) {
      entry.milestone = cleanScalar(options.milestone);
    }
    if (options.problem) {
      entry.problem = cleanMultiline(options.problem);
    }
    if (options.resolution) {
      entry.resolution = cleanMultiline(options.resolution);
    }
    return;
  }

  if (options.purpose) {
    entry.purpose = cleanMultiline(options.purpose);
  }
  if (options.resolution) {
    entry.resolution = cleanMultiline(options.resolution);
  }
}

function removeEntry(backlog, entry) {
  if (entry.kind === "issue") {
    if (entry.source === "active") {
      for (const section of backlog.issues.active.sections) {
        const index = section.issues.indexOf(entry);
        if (index >= 0) {
          section.issues.splice(index, 1);
          return;
        }
      }
      return;
    }

    const entries = backlog.issues[entry.source].entries;
    const index = entries.indexOf(entry);
    if (index >= 0) {
      entries.splice(index, 1);
    }
    return;
  }

  if (entry.source === "active") {
    const index = backlog.milestones.active.sections.indexOf(entry);
    if (index >= 0) {
      backlog.milestones.active.sections.splice(index, 1);
    }
    return;
  }

  const entries = backlog.milestones[entry.source].entries;
  const index = entries.indexOf(entry);
  if (index >= 0) {
    entries.splice(index, 1);
  }
}

function persistBacklog(backlog, options, root) {
  const writes = [
    [ACTIVE_ISSUES_PATH, renderActiveIssuesDoc(backlog.issues.active)],
    [BACKLOG_ISSUES_PATH, renderRecordDoc(backlog.issues.backlog.header, backlog.issues.backlog.entries, "issue")],
    [ARCHIVE_ISSUES_PATH, renderRecordDoc(backlog.issues.archive.header, backlog.issues.archive.entries, "issue")],
    [ACTIVE_MILESTONES_PATH, renderActiveMilestonesDoc(backlog.milestones.active)],
    [BACKLOG_MILESTONES_PATH, renderRecordDoc(backlog.milestones.backlog.header, backlog.milestones.backlog.entries, "milestone")],
    [ARCHIVE_MILESTONES_PATH, renderRecordDoc(backlog.milestones.archive.header, backlog.milestones.archive.entries, "milestone")],
  ];

  if (options["dry-run"]) {
    emit({
      dryRun: true,
      writes: writes.map(([relativePath, content]) => ({
        path: relativePath,
        preview: content.slice(0, 400),
      })),
    }, { json: true });
    return true;
  }

  for (const [relativePath, content] of writes) {
    fs.writeFileSync(path.join(root, relativePath), content, "utf8");
  }

  return false;
}

function renderActiveIssuesDoc(doc) {
  const sections = doc.sections
    .filter((section) => section.issues.length > 0)
    .map((section) => {
      const issues = section.issues.map(renderActiveIssueEntry).join("\n\n");
      return `## ${section.name}\n\n${issues}`;
    })
    .join("\n\n");

  return `${doc.header.trimEnd()}\n\n${sections}\n`;
}

function renderActiveMilestonesDoc(doc) {
  const sections = doc.sections
    .map((entry) => renderActiveMilestoneEntry(entry))
    .join("\n\n");

  return `${doc.header.trimEnd()}\n\n${sections}\n`;
}

function renderActiveIssueEntry(entry) {
  const content = [
    `**Milestone:** ${entry.milestone}  `,
    `**Status:** ${normalizeIssueStatus(entry.status, "active")}  `,
    `**Priority:** ${capitalize(entry.priority)}\n`,
    `#### Problem\n`,
    entry.problem.trim() || "TBD.",
    "",
    `#### Acceptance Criteria\n`,
    entry.acceptanceCriteria.length > 0 ? entry.acceptanceCriteria.map((line) => `- ${line}`).join("\n") : "- TBD.",
    "",
    `#### Current Code References\n`,
    entry.currentCodeReferences.length > 0 ? entry.currentCodeReferences.map((line) => `- \`${line.replace(/^`|`$/g, "")}\``).join("\n") : "- None yet.",
  ];

  if (entry.resolution) {
    content.push("", "#### Resolution", "", entry.resolution.trim());
  }

  return `### ${entry.id ? `${entry.id}: ${entry.title}` : entry.title}\n\n${content.join("\n")}`;
}

function renderActiveMilestoneEntry(entry) {
  const content = [
    `**Status:** ${normalizeMilestoneStatus(entry.status, "active")}  `,
    `**Purpose:** ${entry.purpose.trim() || "TBD."}\n`,
    `### Goals\n`,
    entry.goals.length > 0 ? entry.goals.map((line) => `- ${line}`).join("\n") : "- TBD.",
    "",
    `### Current Code Context\n`,
    entry.currentCodeContext.trim() || "TBD.",
    "",
    `### Exit Criteria\n`,
    entry.exitCriteria.length > 0 ? entry.exitCriteria.map((line) => `- ${line}`).join("\n") : "- TBD.",
    "",
    `### Related Issues\n`,
    entry.relatedIssues.length > 0 ? entry.relatedIssues.map((line) => `- ${line}`).join("\n") : "- None yet.",
  ];

  if (entry.resolution) {
    content.push("", "### Resolution", "", entry.resolution.trim());
  }

  return `## ${entry.name}\n\n${content.join("\n")}`;
}

function renderRecordDoc(header, entries, type) {
  const blocks = entries.map((entry) => renderRecordEntry(entry, type)).join("\n\n");
  if (!blocks) {
    return `${header.trimEnd()}\n`;
  }
  return `${header.trimEnd()}\n\n${blocks}\n`;
}

function renderRecordEntry(entry, type) {
  const lines = ["---"];

  if (type === "issue") {
    if (entry.id) lines.push(`id: ${entry.id}`);
    lines.push(`title: ${entry.title}`);
    lines.push(`status: ${normalizeIssueStatus(entry.status, entry.source)}`);
    lines.push(`priority: ${entry.priority}`);
    if (entry.execution) lines.push(`execution: ${entry.execution}`);
    if (entry.ready) lines.push(`ready: ${entry.ready}`);
    lines.push(`milestone: ${entry.milestone || "unassigned"}`);
    lines.push(renderBlockField("description", entry.problem));
    lines.push(renderListField("acceptance_criteria", entry.acceptanceCriteria));
    lines.push(renderListField("current_code_references", entry.currentCodeReferences));
    if (entry.resolution) {
      lines.push(renderBlockField("resolution", entry.resolution));
    }
  } else {
    lines.push(`name: ${entry.name}`);
    lines.push(`status: ${normalizeMilestoneStatus(entry.status, entry.source)}`);
    if (entry.executionWindow) lines.push(`execution_window: ${entry.executionWindow}`);
    if (entry.isCurrent) lines.push(`is_current: ${entry.isCurrent}`);
    lines.push(renderListField("issues", entry.relatedIssues));
    lines.push(renderBlockField("description", entry.purpose));
    lines.push(renderListField("goals", entry.goals));
    lines.push(renderBlockField("current_code_context", entry.currentCodeContext));
    lines.push(renderListField("exit_criteria", entry.exitCriteria));
    if (entry.resolution) {
      lines.push(renderBlockField("resolution", entry.resolution));
    }
  }

  return lines.filter(Boolean).join("\n");
}

function renderBlockField(key, value) {
  const cleaned = cleanMultiline(value);
  if (!cleaned) {
    return `${key}:`;
  }

  if (!cleaned.includes("\n")) {
    return `${key}: ${cleaned}`;
  }

  const indented = cleaned
    .split("\n")
    .map((line) => `  ${line}`)
    .join("\n");
  return `${key}: |\n${indented}`;
}

function renderListField(key, items) {
  const values = normalizeStringList(items);
  if (values.length === 0) {
    return `${key}:`;
  }

  return `${key}:\n${values.map((item) => `  - ${item}`).join("\n")}`;
}

function splitIssueHeading(heading) {
  const match = heading.match(/^([A-Z0-9-]+):\s+(.+)$/);
  if (!match) {
    return {
      id: "",
      title: heading.trim(),
    };
  }
  return {
    id: match[1].trim(),
    title: match[2].trim(),
  };
}

function readMarkdownField(content, label) {
  const match = content.match(new RegExp(`\\*\\*${escapeRegExp(label)}:\\*\\*\\s*(.+)$`, "mi"));
  return match ? match[1].trim() : null;
}

function extractMarkdownSectionBody(content, heading, level) {
  const hashes = "#".repeat(level);
  const lines = content.replace(/\r/g, "").split("\n");
  const needle = `${hashes} ${heading}`.toLowerCase();
  const startIndex = lines.findIndex((line) => line.trim().toLowerCase() === needle);
  if (startIndex < 0) {
    return "";
  }

  const buffer = [];
  for (let index = startIndex + 1; index < lines.length; index += 1) {
    const line = lines[index];
    if (line.startsWith(`${hashes} `)) {
      break;
    }
    buffer.push(line);
  }

  return buffer.join("\n").trim();
}

function extractMarkdownBullets(content, heading, level) {
  const body = extractMarkdownSectionBody(content, heading, level);
  return body
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line.startsWith("- "))
    .map((line) => line.slice(2).trim())
    .map((line) => line.replace(/^`|`$/g, ""));
}

function normalizeIssueStatus(value, source) {
  const normalized = cleanScalar(value).toLowerCase();
  if (normalized === "open") return source === "active" ? "Open" : "open";
  if (normalized === "in progress") return source === "active" ? "In Progress" : "in progress";
  if (normalized === "blocked") return source === "active" ? "Blocked" : "blocked";
  if (normalized === "complete") return source === "active" ? "Complete" : "complete";
  if (normalized === "unstarted") return source === "active" ? "Open" : "unstarted";
  fail(`Unsupported issue status "${value}" for ${source}.`);
}

function normalizeMilestoneStatus(value, source) {
  const normalized = cleanScalar(value).toLowerCase();
  if (normalized === "planned") return source === "active" ? "Planned" : "planned";
  if (normalized === "active") return source === "active" ? "Active" : "active";
  if (normalized === "blocked") return source === "active" ? "Blocked" : "blocked";
  if (normalized === "complete") return source === "active" ? "Complete" : "complete";
  if (normalized === "not-started") return source === "active" ? "Planned" : "not-started";
  fail(`Unsupported milestone status "${value}" for ${source}.`);
}

function normalizePriority(value) {
  const normalized = cleanScalar(value).toLowerCase();
  if (!ISSUE_PRIORITIES.has(normalized)) {
    fail(`Unsupported issue priority "${value}".`);
  }
  return normalized;
}

function normalizeStringList(value) {
  if (Array.isArray(value)) {
    return value.map((entry) => cleanScalar(entry)).filter(Boolean);
  }
  if (typeof value === "string") {
    return value
      .split(/\r?\n/)
      .map((entry) => entry.trim())
      .filter(Boolean);
  }
  return [];
}

function cleanScalar(value) {
  return String(value ?? "").replace(/\r/g, "").trim();
}

function cleanMultiline(value) {
  return String(value ?? "")
    .replace(/\r/g, "")
    .split("\n")
    .map((line) => line.replace(/\s+$/g, ""))
    .join("\n")
    .trim();
}

function capitalize(value) {
  const cleaned = cleanScalar(value);
  return cleaned ? cleaned.charAt(0).toUpperCase() + cleaned.slice(1) : cleaned;
}

function assertUniqueIssue(backlog, entry) {
  if (entry.id && collectEntries(backlog, "issue", "all").some((candidate) => candidate.id === entry.id)) {
    fail(`Issue id "${entry.id}" already exists.`);
  }
  if (collectEntries(backlog, "issue", "all").some((candidate) => candidate.title === entry.title)) {
    fail(`Issue title "${entry.title}" already exists.`);
  }
}

function assertUniqueMilestone(backlog, entry) {
  if (collectEntries(backlog, "milestone", "all").some((candidate) => candidate.name === entry.name)) {
    fail(`Milestone name "${entry.name}" already exists.`);
  }
}

function readJsonInput(filePath) {
  if (!filePath) {
    fail("Missing required --input JSON file.");
  }
  const raw = fs.readFileSync(path.resolve(filePath), "utf8");
  return JSON.parse(raw);
}

function emit(payload, options) {
  if (options.json) {
    console.log(JSON.stringify(payload, null, 2));
    return;
  }

  if (typeof payload === "string") {
    console.log(payload);
    return;
  }

  console.log(JSON.stringify(payload, null, 2));
}

function toSerializableEntry(entry) {
  if (entry.kind === "issue") {
    return {
      kind: entry.kind,
      source: entry.source,
      id: entry.id,
      title: entry.title,
      milestone: entry.milestone,
      status: entry.status,
      priority: entry.priority,
      acceptanceCriteria: entry.acceptanceCriteria,
      currentCodeReferences: entry.currentCodeReferences,
      resolution: entry.resolution,
    };
  }

  return {
    kind: entry.kind,
    source: entry.source,
    name: entry.name,
    status: entry.status,
    purpose: entry.purpose,
    relatedIssues: entry.relatedIssues,
    resolution: entry.resolution,
  };
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function fail(message) {
  console.error(message);
  process.exit(1);
}
