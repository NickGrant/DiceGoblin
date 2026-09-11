# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 1 - Walking Skeleton

**Status:** Active

### Outcome
Prove the smallest real vNext path:

`authenticated Angular host -> persistent Phaser runtime -> client-safe authored content -> PHP bootstrap -> MySQL player state -> minimal Phaser Camp`

### Exit Criteria
- Fresh vNext database works without prototype migration history or SQL-authored gameplay catalogs.
- Server loads/validates canonical JSON; client receives only an allowlisted projection.
- `/game` mounts one persistent Phaser runtime; Angular does not orchestrate gameplay.
- Phaser startup verifies content compatibility, fetches authoritative bootstrap, and renders Camp.
- 1600x900 reference layout, Compact/Wide landscape behavior, and mobile portrait rotation gate work.
- Required backend/frontend/content/responsive gates pass.
- Superseded prototype paths are removed only after replacements are proven; later reuse candidates remain until their owning package.

### Related Issues
- Establish the fresh vNext database baseline

### Package Queue
Promote/decompose only the first unfinished package into `agent/ISSUES.md`:
1. ~~Inventory/classify prototype code for vNext reuse.~~ Complete.
2. **Fresh vNext database baseline.** Current.
3. Authored content registry + client projection.
4. vNext game bootstrap query.
5. Persistent Phaser runtime mounted at `/game`.
6. Phaser startup state + content compatibility gate.
7. Minimal authoritative Camp.
8. Responsive landscape host behavior.
9. Walking-skeleton end-to-end verification/closure.

Do not begin Milestone 2 (Warband) or later gameplay while Milestone 1 is active.
