# package-verification

## Purpose
Run and summarize the repository's standard package-level verification without flooding agent context with successful test/build logs.

## When To Use
- Before reporting an implementation package ready for architectural review.
- When reviewing whether a pushed package satisfies the standard repository gates.
- When a compact machine-readable verification result is preferable to individual command transcripts.

## Workflow
1. Read the active package's Verification section in `agent/ISSUES.md`.
2. Run:
   - `npm run verify:package`
3. Read:
   - `artifacts/verification/package-summary.json`
4. If a standard gate failed, inspect only that gate's referenced log under `artifacts/verification/`.
5. Run any specialized gates explicitly required by the active issue that are not part of `verify:package`.
6. Report:
   - overall standard verification result;
   - failed gates and concise cause;
   - specialized gates run and result;
   - unavailable gates explicitly as unavailable.

## Rules
- Do not paste successful logs into agent context.
- Do not rerun every standard gate individually after `verify:package` already passed it.
- Use `npm run verify:package -- --continue-on-failure` only when a complete failure inventory is more useful than fail-fast feedback.
- Never claim a specialized or unavailable gate passed because the standard package verifier passed.
- `agent/QUALITY_GATES.md` and the active issue remain authoritative; this skill only orchestrates them.
