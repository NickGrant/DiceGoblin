# Quality Gates
----

## Purpose
- Centralize verification, doc-hygiene, and intake rules that do not need to live in the always-loaded root agent contract.

## Verification Matrix
- Frontend/Phaser behavior changes:
  - run relevant frontend tests/build
  - do a brief manual UX sanity check
  - when visual evidence would help, use `skills/scene-screenshot/SKILL.md`
- Backend/PHP API changes:
  - run targeted endpoint validation
  - check JSON response contract behavior
- Data/schema changes:
  - validate migration order
  - verify required seed/artifact files exist
- Documentation-only changes:
  - run `npm.cmd run llm:check`
  - review reference consistency

## Verification Requirements
- After code changes, run relevant tests/builds when available.
- Report pass/fail status clearly.
- Use Docker for backend/PHP/database verification. If Docker is not running, ask the user to start Docker before attempting backend or database commands.
- Minimum pre-commit verification for mixed frontend/backend work:
  - `npm.cmd run llm:check`
  - `docker compose exec -T backend php vendor/bin/phpunit -c phpunit.xml.dist`
  - `npm.cmd --prefix frontend run test`
  - `npm.cmd --prefix frontend run build`
- If any build/test command fails:
  - report the failing command immediately
  - summarize the most actionable errors
  - state whether the failure looks pre-existing or introduced by the current change
- If a requested verification cannot be run, say why.

## Documentation Hygiene
- Keep active docs concise and current.
- Move historical or superseded detail to archive docs.
- When issue or milestone status changes, update only the minimum relevant active docs plus archive movement.
- If a documentation cleanup is directly related to the current task, fold it into the active change.
- If it is unrelated, prefer opening an issue instead of expanding scope.

## Context Budget Guardrails
- Treat context-budget limits as soft targets, not hard stop rules.
- Preferred targets:
  - `AGENTS.md` under ~220 lines
  - `agent/ROLES.md` under ~180 lines
  - `agent/ISSUES.md` under ~250 lines
  - `agent/MILESTONES.md` under ~120 lines
- Prefer archive movement and reference docs over duplicating policy in multiple active files.

## Feature Intake
- For new feature requests:
  - capture behavior, constraints, and success criteria
  - evaluate gaps in rules, state flow, UX, data, error handling, and testability
  - ask concise clarification questions until the request is implementation-ready
  - update relevant `documentation/` files when requirements are sufficiently defined
- Default UX/system-placement bias toward persistent management surfaces rather than temporary rest-node or run-scoped variants unless the user explicitly wants the run-scoped exception.

## Spec Activation
- Treat rebuild/spec/reference docs as inactive planning material unless the user explicitly declares them active execution artifacts.
- Do not treat creative exploration docs or asset bundles as implementation-authoritative by default.
- When the user introduces a new doc or asset bundle, ask then whether it should be treated as implementation-relevant or exploratory reference.
