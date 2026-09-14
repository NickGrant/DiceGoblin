# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 3 - Enter Farm

**Status:** Active

### Related Issues
- Complete Enter Farm integrated verification and closure

Milestone 1 - Walking Skeleton is complete and passed manual user UAT.

Milestone 2 - Warband is complete and passed manual user UAT on 2026-09-13.

The major game-wide visual/UI overhaul remains intentionally deferred. Milestone 3 continues to prioritize functional clarity, responsive correctness, authoritative state, and architectural integrity rather than final presentation fidelity.

### Outcome
Allow the player to spend Energy once to create and enter a real persistent Farm run, leave/reload/resume the same generated map, abandon it authoritatively, and lock participating Warband configuration while the run is active:

`Camp -> authoritative run start -> Energy spend + persisted Farm graph -> RunScene -> resumable Farm map -> abandon/resume`

Combat and node resolution are deliberately not part of this milestone. They begin in Milestone 4.

### Exit Criteria
- The fresh vNext baseline includes normalized run persistence using authored stable IDs rather than SQL gameplay catalogs.
- Farm generation content is canonical Git JSON with semantic validation and an explicit client-exposure boundary.
- Farm generation is deterministic and infrastructure-free; persistence/player/Energy/HTTP ownership remains outside the generator.
- One active run per user is enforced while terminal run history remains possible.
- `POST /api/v1/runs` validates authenticated player state, active squad/configuration, Energy, authored content, and active-run absence before committing.
- Run creation is idempotent, persists the complete graph/participation transactionally, spends Energy exactly once only on commit, maintains the regeneration anchor correctly, and increments `player_revision` exactly once.
- `GET /api/v1/runs/current` returns the persisted authoritative client-safe active aggregate without regeneration or private generation leakage.
- `POST /api/v1/runs/:runId/abandon` terminalizes the owned active run, retains history, refunds no Energy, and has deliberate retry/non-disclosure semantics.
- Bootstrap carries only the compact active-run summary needed for startup routing.
- Active-run backend locks prevent participating squad switching/formation/deletion and participating unit loadout/dice mutation while allowing harmless naming changes.
- The mounted Phaser runtime routes returning active runs directly to `RunScene`; Camp supports Start/Resume; GameScene/RunScene switching does not remount the runtime or globally refresh authority.
- The Farm map renders the authoritative persisted nodes/edges/positions/statuses and authored client-safe presentation without reconstructing the known five-node topology.
- Node selection is presentation-only; no Milestone 4 node resolution/combat/rewards behavior is fabricated.
- Return to Camp keeps the run active; Resume sends no new start; browser reload resumes the same persisted run.
- Abandon requires explicit confirmation, reconciles only after authoritative success, preserves Energy/Warband state, and returns to Camp.
- Compact/Standard/Wide and touch portrait-gate behavior remain usable and preserve authoritative/local presentation state.
- The complete slice passes fresh-database, authored-content, backend, frontend, Energy/idempotency, configuration-lock, responsive-capture, security, reload/resume, real-stack, and manual UAT verification.

### Package Queue
1. ~~Active-run persistence foundation.~~ Complete and architecturally approved at `0ea5f652af397061190f9cb11c71cb1f6d1d1bb7`.
2. ~~Farm authored run content + deterministic generator adaptation.~~ Complete and architecturally approved at `e5429bb29f2ed23c4a31d5a7e077dfa2ec123d02`.
3. ~~Authoritative run start + Energy spend + idempotency.~~ Complete and architecturally approved at `056c4171e2b060c36351d086e57d59b44f371cd9`.
4. ~~Current-run query + abandon + bootstrap summary + Warband active-run locks.~~ Complete and architecturally approved at `265e1557d21117f281a6d0dca525cde1bbf43802`.
5. ~~Phaser RunScene lifecycle + Camp start/resume navigation.~~ Complete and architecturally approved after implementation `bd97d496434a41d32d84b9bbf93f7b8f1806e353` and correction `af3cbb972a6e3cfbb0bfba20cea27cc0a8c175c8`.
6. ~~Phaser Farm map + abandon/resume UX.~~ Complete and architecturally approved at `496f2b90cd8be264f39ba676373846bf18c249eb`.
7. **Enter Farm integrated verification/closure.** Current.

### Package Review Workflow
For each package:
1. coding agent implements only the current `agent/ISSUES.md` package and leaves it in review state;
2. architectural review evaluates pushed changes against accepted contracts and package acceptance criteria;
3. corrections remain in the same package until approved;
4. planning records the package complete and promotes exactly one next package.

Do not implement later milestones early merely because their eventual shape is known.

### Sequencing Decisions
- Package 1 established the normalized run persistence boundary. Cross-owner squad/unit relationships remain application-level validation and corruption must fail as integrity/non-disclosure errors.
- Package 2 moved Farm topology into private canonical JSON and a pure `FixedGraphRunGenerator`; only safe `run_node_type.*` presentation reaches the browser. Its fresh-generation availability validator is not a persisted-progress validator.
- Package 3 established atomic start. The client submits only region; the server chooses/validates the active squad, spends canonical 10 Energy exactly once, persists graph/participation, increments revision once, and finalizes the idempotency receipt in the same transaction.
- Package 4 established persisted current-run reads, natural retry-safe abandon, compact bootstrap active-run state, and player-row-first active-run configuration locks. Cosmetic squad/unit naming remains legal.
- Package 5 established mounted Phaser lifecycle. Start/Resume/Return/reload use the same runtime/store/canvas. Ambiguous starts preserve one idempotency key; definitive server success that cannot reconcile requires reload rather than another start.
- Package 5 exposes only canonical `run_energy_cost` as safe presentation data. Server cost remains authoritative.
- Package 6 renders only returned persisted graph state. Returned positions/edges drive layout; authored node types drive labels/icons; local selection never resolves a node or changes authority.
- Package 6 reconciles abandon from the strict authoritative response, preserving Energy, active squad, and Warband caches. Ambiguous abandon retries the same run ID; reconciliation disagreement requires reload.
- Exact combat stat/HP initialization, node completion/unlocking, battles/playback, rewards, interactive Rest/Loot/Boss/Exit mechanics, and run modifiers remain deferred to their owning milestones.

### UAT Sequencing
Manual user UAT occurs after Package 7 is technically complete and passes final architectural review.

Do not begin Milestone 4 - Combat until Milestone 3 UAT is complete and its findings are resolved or deliberately deferred.
