# AGENTS FILE
----

## Purpose
This file defines the always-loaded project contract for coding agents working on the `vnext-game-overhaul` branch.

## Startup Behavior
On each new user turn:
- read `agent/LLM_CONTEXT.md`, `agent/ISSUES.md`, and `agent/MILESTONES.md`;
- use `agent/CONTEXT_ROUTER.md` for additional context;
- use `documentation/07-development-path/vnext-game-overhaul.md` as the implementation roadmap;
- load only the narrow accepted vNext decision/system documents needed for the task.

Treat `agent/ISSUES.md` and `agent/MILESTONES.md` as active execution state. Backlog files are planning-only and currently contain no independent product requirements.

## Authority Rules
- Accepted vNext decision documents override prototype implementation shape.
- The active documentation tree contains current intent; Git history is the archive.
- Do not search Git history or resurrect deleted docs as design authority unless the user explicitly asks to recover/reconsider old behavior.
- Prototype source/tests may be inspected as migration evidence for useful algorithms/content, but they do not override accepted vNext decisions.
- A removed catalog or design document is not permission to invent replacement behavior; reconcile historical/source evidence deliberately when that milestone reaches implementation.

## Canonical References
- Repository overview: `README.md`
- Documentation map: `documentation/README.md`
- vNext roadmap: `documentation/07-development-path/vnext-game-overhaul.md`
- Agent workspace: `agent/README.md`
- Context policy: `agent/LLM_CONTEXT.md`
- Context router: `agent/CONTEXT_ROUTER.md`
- Active execution: `agent/ISSUES.md`, `agent/MILESTONES.md`
- Sequencing workflow: `agent/BACKLOG_OPERATIONS.md`
- Verification: `agent/QUALITY_GATES.md`
- Roles when explicitly needed: `agent/ROLES.md`, `agent/ROLE_CATALOG.md`

## Execution Defaults
- Work from the active/next vNext milestone and its execution-ready issues.
- Build milestone-sized vertical capabilities rather than horizontally porting the prototype.
- Keep documentation/tests aligned with accepted behavior.
- Remove obsolete compatibility surfaces when their vNext replacement is proven instead of preserving them for historical reasons.
- Prefer current repository verification scripts; if a documented command is stale, fix the documentation rather than preserving the obsolete workflow.

## Instruction Precedence
Follow platform/system/developer instructions, then this repository contract, then active project/role guidance, then the user's task details.
