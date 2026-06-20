import fs from "node:fs";
import path from "node:path";

const ROLE_CATALOG_PATH = path.join("agent", "ROLE_CATALOG.md");
const ROLE_CLARIFICATION_PATH = path.join("agent", "ROLE_CLARIFICATION.md");

main();

function main() {
  const { positionals, options } = parseArgs(process.argv.slice(2));
  const command = positionals[0];
  const subcommand = positionals[1];

  if (!command || options.help) {
    printUsage();
    process.exit(options.help ? 0 : 1);
  }

  const root = path.resolve(options.root ?? process.cwd());

  switch (`${command}:${subcommand ?? ""}`) {
    case "role:list":
      handleRoleList(root, options);
      return;
    case "role:show":
      handleRoleShow(root, options);
      return;
    case "role-clarification:add":
      handleRoleClarificationAdd(root, options);
      return;
    case "role-clarification:list":
      handleRoleClarificationList(root, options);
      return;
    default:
      fail(`Unknown command "${command}${subcommand ? ` ${subcommand}` : ""}".`);
  }
}

function printUsage() {
  console.log(`Usage:
  npm run agent:docs -- <command> <subcommand> [options]

Commands:
  role list
  role show --name "QA Lead"
  role-clarification add --name "QA Lead" --decision "..." --definition "..."
  role-clarification list [--limit 10]

Options:
  --json
  --root <path>`);
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

function handleRoleList(root, options) {
  const roles = parseRoleCatalog(readRequired(root, ROLE_CATALOG_PATH)).map((role) => ({
    name: role.name,
    description: role.description,
  }));
  emit(roles, options);
}

function handleRoleShow(root, options) {
  const name = cleanScalar(options.name);
  if (!name) {
    fail("Missing required --name for role show.");
  }

  const roles = parseRoleCatalog(readRequired(root, ROLE_CATALOG_PATH));
  const role = roles.find((entry) => entry.name.toLowerCase() === name.toLowerCase());
  if (!role) {
    fail(`Role "${name}" not found.`);
  }

  emit(role, options);
}

function handleRoleClarificationAdd(root, options) {
  const name = cleanScalar(options.name);
  const decision = cleanScalar(options.decision);
  const definition = cleanScalar(options.definition);

  if (!name || !decision || !definition) {
    fail("role-clarification add requires --name, --decision, and --definition.");
  }

  const clarificationPath = path.join(root, ROLE_CLARIFICATION_PATH);
  const existing = readRequired(root, ROLE_CLARIFICATION_PATH).trimEnd();
  const entry = [
    "- name: " + name,
    "  decision: " + decision,
    "  definition: " + definition,
  ].join("\n");

  const next = `${existing}\n${entry}\n`;
  fs.writeFileSync(clarificationPath, next, "utf8");

  emit({ added: { name, decision, definition } }, options);
}

function handleRoleClarificationList(root, options) {
  const limit = Number.parseInt(String(options.limit ?? ""), 10);
  const entries = parseRoleClarifications(readRequired(root, ROLE_CLARIFICATION_PATH));
  const sliced = Number.isFinite(limit) && limit > 0 ? entries.slice(-limit) : entries;
  emit(sliced, options);
}

function parseRoleCatalog(raw) {
  const marker = raw.indexOf("## Roles");
  if (marker < 0) {
    return [];
  }

  const body = raw.slice(marker + "## Roles".length).trim();
  return body
    .split(/\r?\n---\r?\n/g)
    .map((block) => block.trim())
    .filter(Boolean)
    .map(parseRoleBlock)
    .filter((role) => role.name);
}

function parseRoleBlock(block) {
  const lines = block.split(/\r?\n/);
  const role = {
    name: "",
    description: "",
    scope_boundary: "",
    authority_level: "",
    goals: [],
    constraints: [],
    risk_tolerance: [],
    style: [],
  };

  let currentListKey = null;

  for (const line of lines) {
    const keyValueMatch = line.match(/^([a-z_-]+):\s*(.*)$/);
    if (keyValueMatch) {
      const [, key, value] = keyValueMatch;
      currentListKey = null;

      if (["goals", "constraints", "risk-tolerance", "style"].includes(key)) {
        currentListKey = key;
        continue;
      }

      if (key === "name") role.name = value.trim();
      if (key === "description") role.description = value.trim();
      if (key === "scope_boundary") role.scope_boundary = value.trim();
      if (key === "authority_level") role.authority_level = value.trim();
      continue;
    }

    const listMatch = line.match(/^- (.+)$/);
    if (!listMatch || !currentListKey) {
      continue;
    }

    const item = listMatch[1].trim();
    if (currentListKey === "goals") role.goals.push(item);
    if (currentListKey === "constraints") role.constraints.push(item);
    if (currentListKey === "risk-tolerance") role.risk_tolerance.push(item);
    if (currentListKey === "style") role.style.push(item);
  }

  return role;
}

function parseRoleClarifications(raw) {
  const sectionStart = raw.indexOf("## Entries");
  if (sectionStart < 0) {
    return [];
  }

  const body = raw.slice(sectionStart + "## Entries".length).trim();
  const matches = body.match(/- name:[\s\S]*?(?=\r?\n- name:|\s*$)/g) ?? [];

  return matches.map((block) => {
    const name = block.match(/- name:\s*(.+)/)?.[1]?.trim() ?? "";
    const decision = block.match(/decision:\s*(.+)/)?.[1]?.trim() ?? "";
    const definition = block.match(/definition:\s*(.+)/)?.[1]?.trim() ?? "";
    return { name, decision, definition };
  });
}

function readRequired(root, relativePath) {
  const absolutePath = path.join(root, relativePath);
  if (!fs.existsSync(absolutePath)) {
    fail(`Missing required file ${relativePath}`);
  }
  return fs.readFileSync(absolutePath, "utf8");
}

function cleanScalar(value) {
  return String(value ?? "").replace(/\r/g, "").trim();
}

function emit(payload, options) {
  if (options.json) {
    console.log(JSON.stringify(payload, null, 2));
    return;
  }

  if (Array.isArray(payload)) {
    for (const entry of payload) {
      console.log(JSON.stringify(entry, null, 2));
    }
    return;
  }

  console.log(JSON.stringify(payload, null, 2));
}

function fail(message) {
  console.error(message);
  process.exit(1);
}
