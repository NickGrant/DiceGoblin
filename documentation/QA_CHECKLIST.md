# Documentation QA Checklist
----

Status: active  
Last Updated: 2026-03-02  
Owner: QA + Product  
Depends On: `documentation/README.md`, `documentation/STYLE_GUIDE.md`, `documentation/01-architecture/03-backend-api-contracts.md`

## Purpose
- Provide a repeatable check to catch code/documentation drift before and after implementation changes.

## When to Run
- Before merging changes that modify API routes, payloads, scene flow, run logic, or progression rules.
- After any documentation-only change touching architecture/contracts/system scope.
- During milestone handoff or release-readiness checks.

## Checklist

1. Route map verification
- Compare documented endpoints to `backend/public/index.php` route registration.
- Confirm method/path/version and implemented/planned labels are accurate.

2. Payload key verification
- Compare documented response keys with current controller/service outputs.
- Verify naming consistency (`squads` vs `teams`, run/map envelope shape, error code keys).

3. Mutating endpoint safeguards
- Confirm CSRF requirements in docs match implementation behavior.
- If implementation is temporarily non-compliant, ensure a "Known gap" note and active issue exist.

4. Scene flow verification
- Compare scene docs to `frontend/src/game/config.ts` and scene transitions in code.
- Ensure planned scenes are explicitly labeled as not implemented.

5. Systems consistency verification
- Cross-check encounter/combat/loot/run-resolution docs for XP/reward and attrition wording alignment.
- Ensure rest-node and run-snapshot editing rules are consistent across docs.

6. Terminology verification
- Confirm canonical product terms are used consistently where intended (`warband`/`squad`, run snapshot terminology).
- Record intentional technical exceptions (database table names, route compatibility names).

7. Formatting and encoding verification
- Ensure markdown is UTF-8 and free of mojibake artifacts.
- Verify high-impact docs include metadata (`Status`, `Last Updated`, `Owner`, `Depends On`).

## Output Requirements
- If checks pass: append a brief note in `documentation/CHANGELOG.md` for significant doc updates.
- If checks fail: open/update issues in `ISSUES.md` with `execution` and `ready` fields set appropriately.
