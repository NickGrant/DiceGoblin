# Quality Gates

## Rule
Use targeted checks during implementation; run the applicable package-level gates once the package is otherwise complete. Do not repeatedly run the full suite after routine edits.

If a documented command is stale because the package intentionally replaces that infrastructure, update the command/script as part of the package rather than preserving compatibility solely for the gate.

## Core Commands
- Agent/context integrity: `npm run llm:check`
- Documentation headers: `npm run docs:lint`
- Backend suite: `npm run test:backend` (Docker variant: `npm run test:backend:docker`)
- Frontend suite: `npm run test:frontend`
- Frontend production build: `npm run build:frontend`
- Frontend bundle budget when bundle/runtime dependencies change: `npm run bundle:check`
- Full cross-stack gate when warranted: `npm run verify:full`
- Deterministic Phaser capture: `npm run capture:scene -- ...` using the relevant scene/fixture arguments

Repository scripts in `package.json` are the executable source of truth when a specialized domain gate is needed.

## Package Gates by Change Type
- **Backend/API:** targeted tests while editing; before completion run relevant backend suite. Verify auth, CSRF, ownership, validation, transaction rollback, and idempotency when applicable.
- **Schema/data:** prove an empty database initializes via the current vNext baseline and relevant backend persistence tests pass. Prototype migration history and SQL-authored gameplay catalogs must not be required.
- **Frontend/Phaser:** targeted tests while editing; before completion run frontend tests + production build. Run bundle check if dependencies/bundle composition changed.
- **Visual/layout:** deterministic capture plus Compact landscape, 1600x900 reference, and Wide landscape review for affected screens. Verify portrait rotate-device behavior only when host/orientation/layout work can affect it.
- **Authored content:** structural/reference/stable-ID validation plus client-projection allowlist/secrecy checks.
- **Spending/random/durable gameplay commands:** prove retry idempotency: no double spend, duplicate durable assets, or reroll of finalized results.
- **Combat/run generation:** retain deterministic regression/simulation coverage when migrating algorithms; use specialized `package.json` scripts only for the affected subsystem.
- **Documentation/agent-only:** `npm run llm:check` and `npm run docs:lint` as applicable; inspect references changed by the edit.

## Reuse Gate
When replacing prototype behavior, follow `documentation/07-development-path/vnext-prototype-code-disposition.md` and inspect the current implementation/tests before deleting it. Do not rewrite a tested algorithm merely to fit new file organization, and do not preserve obsolete orchestration merely because its tests exist.

## Failure Policy
- A new failure caused by the package blocks completion.
- A demonstrably pre-existing unrelated failure must be reported with evidence; do not expand package scope to repair it unless necessary.
- Never report a gate as passed unless it was actually run successfully.

## Completion Evidence
Final report should name only the meaningful gates run and their result. Do not paste successful logs or provide a test-by-test narrative unless requested.
