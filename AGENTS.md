# Coding Agent Contract

## Purpose
This is the always-loaded contract for implementation work on `vnext-game-overhaul`. Keep other context demand-driven.

## Startup and Context Budget
At the start of a coding session or new work package:
1. Read `agent/ISSUES.md` for the single current execution target.
2. Read only the accepted decision/system docs needed to implement that target. Use `agent/CONTEXT_ROUTER.md` only when the correct source is unclear.
3. Inspect current source/tests for the subsystem being changed. When replacing prototype behavior, consult `documentation/07-development-path/vnext-prototype-code-disposition.md`.
4. Read `agent/QUALITY_GATES.md` before verification/completion.

Do **not** reread unchanged context on every conversational turn. Refresh execution/docs only when the user changes scope, the current package changes, the task crosses into another domain, a conflict/ambiguity appears, or prior context is no longer reliable.

`agent/MILESTONES.md`, the full roadmap, backlog files, role files, lore, UX, and broad documentation indexes are on-demand context, not startup context.

## Authority
1. Platform/system/developer instructions.
2. Explicit current user instructions.
3. This contract.
4. Accepted vNext decision documents and canonical system/technical docs.
5. Current execution issue.
6. Prototype source/tests as behavioral evidence only.

If sources conflict, do not silently choose the convenient interpretation. Accepted vNext decisions override prototype architecture. Git history is not design authority unless the user explicitly asks for historical recovery.

## Execution Rules
- Work one execution package at a time. Do not implement blocked/future milestone work except the minimum interface/null contract required by the current package.
- Implement the smallest complete vertical change that satisfies the current acceptance criteria; avoid speculative scaffolding and unrelated cleanup.
- Preserve useful tested algorithms/behavior while replacing obsolete boundaries according to the prototype disposition map.
- Do not add compatibility layers for rejected prototype behavior unless an accepted contract requires them.
- Do not add dependencies, change technology, alter persistent/API contracts, or invent player-facing behavior merely to unblock implementation. If an unresolved choice materially affects architecture, persistence, API semantics, economy/mechanics, or UX intent, surface it instead of guessing.
- Routine implementation choices inside accepted boundaries are the agent's responsibility; do not ask for approval for ordinary naming, refactoring, or test-structure decisions.
- Prefer simple concrete code over speculative abstraction. One responsibility per boundary; no god services, catch-all state, or framework/pattern adoption without a concrete need.
- Keep changes scoped. Remove superseded code when its replacement is proven and no active dependency remains; Git is the archive.

## Non-Negotiable vNext Boundaries
- Angular: public/auth/account shell and Phaser host only.
- Phaser: all gameplay presentation/navigation/client cache/API interaction after mount.
- PHP: authoritative gameplay, application commands/queries, transactions.
- MySQL: mutable player/runtime state only.
- JSON in Git: canonical authored gameplay content.
- Application operation: one complete player intention and one transaction owner.
- Repositories: persistence only; domain/engines remain computational where practical.
- Spending/random/durable gameplay commands: idempotent.

Detailed standards live in `documentation/08-operations/00-engineering-standards.md`; do not load it unless the current task needs detail beyond these boundaries.

## Completion Standard
Before declaring a package complete:
- satisfy every acceptance criterion in `agent/ISSUES.md`;
- run the applicable gates from `agent/QUALITY_GATES.md` and report failures accurately;
- update tests for changed behavior at the owning layer;
- update documentation only when the implemented contract changed or an accepted decision must be amended;
- remove obsolete paths only when the replacement is proven;
- leave later packages unimplemented.

Final reports should be concise: changed behavior, verification run/results, and any unresolved/blocking decision. Do not restate loaded documentation or narrate routine implementation steps.

## Headroom
- Invoke the repository-scoped Headroom CLI with `npm run headroom -- <command>`; repository-specific Headroom configuration, logs, and runtime state stay under ignored `.headroom/`.
- For local Codex CLI sessions, launch through `npm run headroom:codex -- -- [codex arguments]` so model traffic uses the Headroom proxy and compression markers remain retrievable through its MCP server.
- Use `npm run headroom:tools` to verify the bundled token-efficient repository tools and `npm run headroom:doctor` while a wrapped session is running to verify proxy/client routing.
