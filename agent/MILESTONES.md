# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 1 - Walking Skeleton

**Status:** Active - Final Closure Package

### Outcome
Prove the smallest real vNext path:

`authenticated Angular host -> persistent Phaser runtime -> client-safe authored content -> PHP bootstrap -> MySQL player state -> minimal Phaser Camp`

### Exit Criteria
- Fresh vNext database works without prototype migration history or SQL-authored gameplay catalogs.
- Server loads/validates canonical JSON; client receives only an allowlisted projection.
- `/game` mounts one persistent Phaser runtime; Angular does not orchestrate gameplay.
- Phaser startup verifies content compatibility, fetches authoritative bootstrap, and renders Camp.
- 1600x900 reference layout, Compact/Wide landscape behavior, safe insets, and touch-first mobile portrait rotation gate work.
- Authenticated default gameplay entry is `/game`; obsolete Angular gameplay routes/profile orchestration no longer operate as a parallel live game client while useful prototype source remains available for later migration work.
- Required backend/frontend/content/responsive/full-stack gates pass with fresh-state evidence where supported by repository tooling.
- Superseded prototype paths are removed only after replacements are proven; later reuse candidates remain until their owning package.

### Related Issues
- Verify and close the vNext walking skeleton

### Package Queue
Promote/decompose only the first unfinished package into `agent/ISSUES.md`:
1. ~~Inventory/classify prototype code for vNext reuse.~~ Complete.
2. ~~Fresh vNext database baseline.~~ Complete and architecturally approved.
3. ~~Authored content registry + client projection.~~ Complete and architecturally approved.
4. ~~vNext game bootstrap query.~~ Complete and architecturally approved.
5. ~~Persistent Phaser runtime mounted at `/game`.~~ Complete and architecturally approved.
6. ~~Phaser startup state + content compatibility gate.~~ Complete and architecturally approved.
7. ~~Minimal authoritative Camp.~~ Complete and architecturally approved.
8. ~~Responsive landscape host behavior.~~ Complete and architecturally/UX approved.
9. **Walking-skeleton end-to-end verification/closure.** Current.

### UAT Sequencing
Manual user UAT is deferred until Milestone 1 is technically complete. Package 9 must first prove the integrated walking skeleton, retire only conclusively superseded live prototype wiring, and pass final architectural review. After package 9 is approved, planning will mark Milestone 1 complete and hand the resulting build to the user for UAT. UAT findings may create follow-up work; they do not belong inside the coding-agent closure package itself.

Do not begin Milestone 2 (Warband) or later gameplay while Milestone 1 is active.