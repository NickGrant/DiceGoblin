# Programming Agent Handoff

Use this file as the stable entry point for implementation work on `vnext-game-overhaul`.

## Start Here

1. Pull the latest `vnext-game-overhaul`.
2. Read `AGENTS.md`.
3. Read the single active package in `agent/ISSUES.md`.
4. Inspect only the source/tests needed for that package.
5. Use `agent/CONTEXT_ROUTER.md` only when additional authority is needed.
6. Read `agent/QUALITY_GATES.md` before verification.

Do not copy the issue into a separate plan. The repository is the specification.

## Execution

- Implement only the active package or explicit review findings in `agent/ISSUES.md`.
- If a `Review Findings` section exists, address those findings before doing any additional package work.
- Keep changes as small as possible while satisfying the issue.
- Do not implement the next package, milestone, speculative infrastructure, or unrelated cleanup.
- Do not change architecture, persistence/API contracts, mechanics, economy, or UX beyond accepted repository authority.
- Routine implementation/refactoring/test decisions inside the accepted boundaries are yours to make.
- GitHub source on the working branch is authoritative; do not rely on stale attached ZIP/reference copies.

## Planning Files

Implementation agents do not promote work.

- Leave the active issue `In Progress` after pushing.
- Do not mark the package complete.
- Do not promote the next package.
- Do not update `agent/MILESTONES.md` or the roadmap unless the user explicitly asks.
- Architectural review owns package approval and promotion.

## Verification

Run the targeted checks while working and the applicable package gates before reporting completion.

Never claim a gate passed unless you actually ran it successfully.

If a gate cannot be run, say so. If a failure is pre-existing and unrelated, report the evidence rather than expanding scope automatically.

## Push and Report

Commit and push the implementation to `vnext-game-overhaul`.

Keep the final report concise:

- exact commit SHA;
- meaningful behavior changed;
- meaningful tests/gates run and whether they passed;
- any remaining blocker or uncertainty.

Do not restate the issue or narrate routine implementation steps.

## Minimal Handoff Prompt

A normal handoff should need only:

> On `vnext-game-overhaul`, follow `agent/PROGRAMMING_AGENT.md` and implement the current `agent/ISSUES.md`. Push the changes and report the SHA.
