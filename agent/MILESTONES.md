# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 3 - Enter Farm

**Status:** UAT corrections implemented; focused manual recheck pending

### Related Issues
- None. Package 8 passed architectural review; the user is rechecking the two corrected UAT findings.

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 3 prioritized functional clarity, responsive correctness, authoritative state, and architectural integrity rather than final presentation fidelity.

### Outcome
Allow the player to spend Energy once to create and enter a real persistent Farm run, leave/reload/resume the same generated map, abandon it authoritatively, and lock participating Warband configuration while the run is active:

`Camp -> authoritative run start -> Energy spend + persisted Farm graph -> RunScene -> resumable Farm map -> abandon/resume`

Combat and node resolution are deliberately not part of this milestone. They begin in Milestone 4 only after Milestone 3 manual UAT passes.

### Technical Closure
Package 7 integrated closure at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f` passed architectural review on 2026-09-13.

The verified slice includes:
- fresh normalized run persistence with one-active-run enforcement and retained terminal history;
- canonical private Farm generation and safe public run-node/cost projection;
- deterministic infrastructure-free Farm graph generation;
- transactional idempotent run start with authoritative Energy spend and revision handling;
- persisted current-run reads and retry-safe no-refund abandon;
- active-run Warband configuration locks;
- compact bootstrap active-run routing state;
- persistent Phaser Camp/RunScene start, return, resume, and reload lifecycle;
- persisted graph-driven Farm map presentation with presentation-only node selection;
- authoritative abandon confirmation/reconciliation;
- cross-player non-disclosure and real PHP/MySQL/browser lifecycle proof;
- Compact/Standard/Wide/portrait-gate verification.

The aggregate `npm run verify:full` could not complete on the Windows host because host PHP is unavailable. Its required constituents were run successfully through their supported host or Docker paths. GitHub has no attached CI status for the closure commit, so the architectural approval is based on the committed implementation/verification coverage plus the reported executed gates, without representing the unavailable aggregate as passed.

### Manual UAT Findings and Correction
Manual UAT identified two usability defects:
- interactive Phaser controls did not consistently change the desktop cursor to indicate clickability;
- active-run Warband configuration locks were enforced correctly by the backend, but the client allowed known-invalid squad/unit configuration attempts before explaining the lock.

Package 8 corrected both findings and passed architectural review at `038a6ce081fa3b735f627095db372db24b39f79e` on 2026-09-14. The correction adds a shared pointer-cursor affordance for actionable live Phaser controls, derives participating squad/unit presentation locks from coherent bootstrap state without another API call, proactively disables known-invalid active-run configuration actions, preserves legal squad/unit renaming, and maps backend race/stale-tab lock responses to understandable player-facing messages. Backend Package 4 authority remains unchanged.

A separate environment-gated repeatable UAT Warband seed helper at `02201376ff8f4b1b45966eaaff8685aa5ee650bc` was merged during the correction work. It is UAT tooling, not part of the Package 8 product behavior or backend lock semantics.

### Exit Criteria
- The fresh vNext baseline includes normalized run persistence using authored stable IDs rather than SQL gameplay catalogs.
- Farm generation content is canonical Git JSON with semantic validation and an explicit client-exposure boundary.
- Farm generation is deterministic and infrastructure-free; persistence/player/Energy/HTTP ownership remains outside the generator.
- One active run per user is enforced while terminal run history remains possible.
- Run start is authoritative, idempotent, transactional, spends Energy exactly once, and preserves correct regeneration-anchor/revision semantics.
- Current-run reads return persisted client-safe state without regeneration/private generation leakage.
- Abandon terminalizes the owned run, preserves history, refunds no Energy, and has deliberate retry/non-disclosure semantics.
- Bootstrap carries only compact active-run routing state.
- Active-run backend locks protect participating combat configuration while harmless naming remains legal.
- Camp/RunScene Start/Resume/Return/reload uses one mounted Phaser runtime/store/canvas.
- Farm map rendering is driven by persisted returned nodes/edges/positions/statuses and authored projected presentation.
- Node selection remains presentation-only; node resolution/combat/rewards are not fabricated.
- Abandon confirmation/reconciliation preserves Energy/Warband state and returns to Camp only after authoritative success.
- Responsive and touch portrait-gate behavior remains usable.
- Interactive Phaser controls provide a consistent pointer affordance on fine-pointer/desktop input when actionable, while disabled/informational elements do not falsely advertise clickability.
- During an active run, Warband proactively communicates and disables known-invalid participating squad/unit configuration actions while preserving allowed name-only/rename behavior; backend lock errors remain understandable fallback protection.
- Technical verification is complete. The focused manual recheck of the two corrected UAT findings is the only remaining Milestone 3 exit criterion.

### Package Queue
1. ~~Active-run persistence foundation.~~ Complete and architecturally approved at `0ea5f652af397061190f9cb11c71cb1f6d1d1bb7`.
2. ~~Farm authored run content + deterministic generator adaptation.~~ Complete and architecturally approved at `e5429bb29f2ed23c4a31d5a7e077dfa2ec123d02`.
3. ~~Authoritative run start + Energy spend + idempotency.~~ Complete and architecturally approved at `056c4171e2b060c36351d086e57d59b44f371cd9`.
4. ~~Current-run query + abandon + bootstrap summary + Warband active-run locks.~~ Complete and architecturally approved at `265e1557d21117f281a6d0dca525cde1bbf43802`.
5. ~~Phaser RunScene lifecycle + Camp start/resume navigation.~~ Complete and architecturally approved after implementation `bd97d496434a41d32d84b9bbf93f7b8f1806e353` and correction `af3cbb972a6e3cfbb0bfba20cea27cc0a8c175c8`.
6. ~~Phaser Farm map + abandon/resume UX.~~ Complete and architecturally approved at `496f2b90cd8be264f39ba676373846bf18c249eb`.
7. ~~Enter Farm integrated verification/closure.~~ Complete and architecturally approved at `c456d983b1afaf36c0dc9e069b3e35cfcdf6957f`.
8. ~~UAT interaction affordances + active-run lock presentation.~~ Complete and architecturally approved at `038a6ce081fa3b735f627095db372db24b39f79e`.

### UAT Sequencing
Manual user UAT remains the active Milestone 3 activity, limited to a focused recheck of Package 8's two corrections.

Do not promote or begin Milestone 4 - Combat until the focused recheck passes and Milestone 3 UAT is formally closed.
