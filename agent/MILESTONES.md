# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 3 - Enter Farm

**Status:** Active

### Related Issues
- Establish active-run persistence foundation

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13 after all nine implementation/closure packages passed architectural review. The verified slice includes fresh Warband persistence/content, authoritative reads and mutations, persistent Phaser Warband navigation, squad lifecycle/configuration, individual unit rename/loadout/exact-die configuration, cache reconciliation, responsive behavior, cross-player security, and real PHP/MySQL persistence proof.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 3 should continue prioritizing functional clarity, responsive correctness, and architectural integrity rather than final presentation fidelity.

### Outcome
Allow the player to spend Energy once to create and enter a real persistent Farm run, leave/reload/resume the same generated map, abandon it authoritatively, and lock participating Warband configuration while the run is active:

`Camp -> authoritative run start -> Energy spend + persisted Farm graph -> RunScene -> resumable Farm map -> abandon/resume`

Combat and node resolution are deliberately not part of this milestone. They begin in Milestone 4.

### Exit Criteria
- The fresh vNext baseline includes the concrete run persistence needed for a resumable generated run, using authored stable IDs rather than SQL run/region/encounter catalogs.
- Farm run-generation definitions required by this slice are canonical Git-tracked JSON with structural/semantic validation and an explicit client-exposure boundary.
- Useful deterministic Farm graph-generation behavior is adapted behind the accepted vNext generator boundary without retaining HTTP, SQL transaction, player-state, or authored-catalog ownership inside the generator.
- A player can have at most one active run; run ownership and lifecycle are authoritative server state.
- `POST /api/v1/runs` validates the authenticated player, Farm eligibility, active squad, participating units/configuration, Energy, authored content, and active-run absence before committing.
- Run creation is idempotent for retries, persists the complete generated graph/participation state transactionally, spends Energy exactly once only when creation succeeds, updates the Energy regeneration anchor correctly, and increments `player_revision` exactly once.
- Failed generation/validation/persistence spends no Energy, creates no partial run, and does not change revision.
- The run captures/retains enough generated state to resume the same graph after reload without regenerating a different map.
- `GET /api/v1/runs/current` returns the authoritative active-run aggregate required by the current Farm map without leaking hidden generated information the player is not yet entitled to know.
- `POST /api/v1/runs/:runId/abandon` authoritatively terminates the owned active run with no Energy refund; repeat/foreign/stale attempts fail or no-op according to one deliberate contract without leaking another player's state.
- Bootstrap exposes enough active-run summary state to route a returning player correctly without turning bootstrap into the complete run payload.
- While a run is active, server-side Warband commands prevent active-squad switching, participating squad membership/position changes, and participating unit loadout/dice changes. The backend owns this lock; the client is not the authority.
- Phaser adds a persistent `RunScene` owned by the existing runtime. Camp can start/resume a run; leaving/reloading `/game` resumes the same active run without Angular gameplay routing or Phaser remount architecture regressions.
- Phaser renders a functional responsive Farm run map from the authoritative run aggregate, including generated nodes/edges, availability/completion state, current position/progression cues as applicable, and an explicit abandon flow.
- No Milestone 4 combat/node-resolution implementation is fabricated merely to make the map clickable. Unsupported unresolved nodes are presented as future gameplay boundaries rather than client-authoritative completions.
- The slice passes fresh-database, authored-content, backend, frontend, Energy/idempotency, configuration-lock, responsive-capture, security, reload/resume, and real-stack verification before manual UAT.

### Package Queue
Promote/decompose only the first unfinished package into `agent/ISSUES.md`:
1. **Active-run persistence foundation.** Current.
2. Farm authored run content + deterministic generator adaptation.
3. Authoritative run start + Energy spend + idempotency.
4. Current-run query + abandon + bootstrap summary + Warband active-run locks.
5. Phaser RunScene lifecycle + Camp start/resume navigation.
6. Phaser Farm map + abandon/resume UX.
7. Enter Farm integrated verification/closure.

### Package Review Workflow
For each package:
1. coding agent implements only the current `agent/ISSUES.md` package and leaves it in review state;
2. architectural review evaluates pushed changes against accepted contracts and package acceptance criteria;
3. corrections remain in the same package until approved;
4. planning records the package complete and promotes exactly one next package.

Do not implement later packages early merely because their eventual shape is known.

### Sequencing Decisions
- Package 1 is persistence only. The retained prototype `RunGraphGenerator` still owns PDO/catalog concerns and must not shape the vNext schema by accident.
- Package 2 adapts useful deterministic generation behavior and authored Farm definitions before run start begins consuming them.
- Package 3 is the first run-creation transaction and owns Energy/idempotency semantics.
- Package 4 introduces the authoritative active-run configuration lock now that real run state exists. Earlier Warband commands were intentionally structured to accept this policy without redesign.
- Exact resolved combat-stat formulas remain deferred. Milestone 3 must not invent progression/stat math merely to pre-stage Milestone 4 combat. Persist only run-unit participation/state that can be represented honestly at this stage; finalize combat HP initialization when the combat milestone owns the required stat resolver unless an already-canonical resolver is deliberately established.
- `run_modifiers`, battles/playback, reward resolution, interactive Rest/Chaos state, and node completion mechanics are added only when their owning milestones concretely require them.

### UAT Sequencing
Manual user UAT occurs after Package 7 is technically complete and passes final architectural review.

Do not begin Milestone 4 - Combat until Milestone 3 UAT is complete and its findings are resolved or deliberately deferred.
