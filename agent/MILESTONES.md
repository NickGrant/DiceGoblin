# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 1 - Walking Skeleton

**Status:** Complete - User UAT Handoff

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
None active. The Milestone 1 implementation is handed to the user for manual UAT.

### Package Queue
1. ~~Inventory/classify prototype code for vNext reuse.~~ Complete.
2. ~~Fresh vNext database baseline.~~ Complete and architecturally approved.
3. ~~Authored content registry + client projection.~~ Complete and architecturally approved.
4. ~~vNext game bootstrap query.~~ Complete and architecturally approved.
5. ~~Persistent Phaser runtime mounted at `/game`.~~ Complete and architecturally approved.
6. ~~Phaser startup state + content compatibility gate.~~ Complete and architecturally approved.
7. ~~Minimal authoritative Camp.~~ Complete and architecturally approved.
8. ~~Responsive landscape host behavior.~~ Complete and architecturally/UX approved.
9. ~~Walking-skeleton end-to-end verification/closure.~~ Complete and architecturally approved.

### Verification Closure
Milestone 1 closure verified the fresh vNext database baseline, canonical authored-content projection, backend and frontend suites, production build, bundle budget, responsive captures, touch-first orientation behavior, startup negative paths, and a real browser -> PHP -> MySQL registration/bootstrap/Camp path with one gameplay bootstrap, one client-content load, matching content revisions, and no prototype `/profile` gameplay request.

The aggregate `npm run verify:full` command could not invoke host PHP in the verification environment because PHP was absent from the Windows PATH. Its required constituents were run successfully through the repository-supported Docker/frontend paths instead; no required Milestone 1 verification check was skipped.

### UAT Handoff
Milestone 1 is technically complete. Manual user UAT is the next activity and is post-milestone validation rather than an execution package.

Record UAT findings before promoting corrective work. Do not begin Milestone 2 (Warband) until the user completes this UAT handoff and explicitly proceeds to the next work.
