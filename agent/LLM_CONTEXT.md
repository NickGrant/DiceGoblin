# Context Manifest

## Purpose
Keep implementation context small. `AGENTS.md` defines startup, authority, execution, and completion rules; do not duplicate them here.

## Default Implementation Context
- `AGENTS.md` (automatically applicable project contract)
- `agent/ISSUES.md` (single current execution package)
- current source/tests directly touched by the package

Everything else is demand-driven.

## Load When Needed
- `agent/CONTEXT_ROUTER.md` — when the authoritative document for a question is unclear.
- `agent/QUALITY_GATES.md` — before verification/completion.
- `agent/MILESTONES.md` and the vNext roadmap — sequencing, package completion, or planning.
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — before replacing a prototype subsystem.
- Relevant accepted `vnext-*.md` decision docs — only for domains touched by the current package.
- System/content/UX/lore docs — only when their behavior or presentation is being changed.
- Role files — only when the user explicitly requests a role/review lens.
- Git history — only for explicit historical recovery/investigation.

## Exclude by Default
Do not recursively load documentation directories, future issue details, backlog files, role catalogs, `frontend/dist/`, `frontend/node_modules/`, `raw-assets/`, generated artifacts, or unrelated source.

## Refresh Triggers
Do not reread unchanged files each turn. Refresh context when the work package/scope changes, a referenced file was modified, a conflict appears, or prior context is no longer reliable.
