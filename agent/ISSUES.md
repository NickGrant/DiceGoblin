# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 3 - Mountains authored run graph/events/rewards + terminal lifecycle

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Package 1 Mountains combat foundation is approved at `4adff479b4c10af40057f1f93088c0930e5894d8`.

Package 2 region-neutral Boss/Exit contracts are approved at `a667ee703da5f868fd65218199ad271553110bc6` with MySQL/Docker proof reported against planning head `bd7e39b609b1ebe95b1f99fee22c80d739e05114`:
- DB provision passed;
- DB reset from `backend/migrations/vnext_baseline.sql` passed;
- Boss integration: 12 tests / 111 assertions / 0 skipped;
- Exit/Loot/Rest integration: 17 tests / 143 assertions / 0 skipped;
- full Docker backend: 590 tests / 2,475 assertions / 148 skipped;
- no defects exposed.

#### Problem

Mountains now has canonical kobold combat content, but it is not yet a playable authored run:
- `region.mountains` has no `run_generation_id`;
- there are no vNext Mountains event/reward definitions;
- no persisted Mountains graph currently proves that Combat/Loot/Rest/Boss/Exit reuse the Farm-established lifecycle;
- public run start intentionally still rejects Mountains until Package 4.

#### Goal

Author the smallest complete Mountains run that proves the second region can use the existing content -> fixed graph -> persistence -> node resolution -> battle playback -> reward -> terminal Exit pipeline without a parallel region-specific implementation.

Do **not** make Mountains selectable/startable from Camp yet. Package 4 owns unlock-aware start authorization and region selection.

#### Canonical Mountains run

Add `run_generation.mountains` using `fixed_graph_v1` and point `region.mountains.run_generation_id` to it.

Use this linear authored graph:

1. `combat_1`
   - type: `run_node_type.combat`
   - encounter: `encounter.mountains_kobold_combat_1`
   - position: column 0, row 1
2. `loot`
   - type: `run_node_type.loot`
   - event: `event.mountains_loot_completed`
   - position: column 1, row 1
3. `combat_2`
   - type: `run_node_type.combat`
   - encounter: `encounter.mountains_kobold_combat_2`
   - position: column 2, row 1
4. `rest`
   - type: `run_node_type.rest`
   - no encounter/event
   - position: column 3, row 1
5. `combat_3`
   - type: `run_node_type.combat`
   - encounter: `encounter.mountains_kobold_combat_3`
   - position: column 4, row 1
6. `boss`
   - type: `run_node_type.boss`
   - encounter: `encounter.mountains_kobold_boss_1`
   - event: `event.mountains_boss_completed`
   - position: column 5, row 1
7. `exit`
   - type: `run_node_type.exit`
   - no encounter/event
   - position: column 6, row 1

Edges are exactly:
`combat_1 -> loot -> combat_2 -> rest -> combat_3 -> boss -> exit`.

The start node is `combat_1`.

This is intentionally a simple fixed graph. Do not port the prototype pattern-V1/V2 branching generator or its hazards/chaos/shrine breadth in this milestone.

#### Mountains authored events/rewards

Add stable Mountains event/reward definitions through the existing reward pipeline.

**Loot**
- event: `event.mountains_loot_completed`
- reward definition: `reward_definition.mountains_loot_completed`
- deterministic reward: **8 Teeth**
- probability: 10000 basis points

The amount deliberately matches the current Farm proof; economy tuning is not a Milestone 6 objective.

**Boss**
- event: `event.mountains_boss_completed`
- reward definition: `reward_definition.mountains_boss_completed`
- deterministic reward: **16 XP per participating unit**
- probability: 10000 basis points
- **no unlock reward**

Do not unlock Swamps here. Package 2 explicitly supports XP-only Boss results with `unlocks: []`; Mountains should exercise that real path.

#### Runtime/lifecycle constraints

Reuse the existing runtime without region-specific handlers:
- Combat nodes use the existing authoritative combat-node resolution and persisted playback pipeline.
- Loot uses the existing generic Loot handler.
- Rest uses the existing generic full-recovery handler.
- Boss uses the generic Package 2 Boss reward projection.
- Exit uses the structural Package 2 terminal Exit validation.
- Node completion/unlocking remains direct outgoing-edge progression.
- Defeat/stalemate still fails the active run through the existing combat lifecycle.
- Successful Exit completes the run and increments player revision once.
- No Mountains-specific endpoint, controller, repository, scene, or handler is allowed.

#### Start boundary

Do not change `StartRunCommand::validateRegion()` in this package.

Mountains must remain unavailable through the public `POST /api/v1/runs` path until Package 4 makes start authorization unlock-aware.

For Package 3 integration coverage, construct/persist the Mountains graph through the existing authored content + `FixedGraphRunGenerator` + run persistence boundary (or an equivalent test fixture that does not weaken production authorization).

#### Client/content projection

Once `region.mountains` has a validated `run_generation_id`, it is expected to appear in the safe projected region catalog.

That does **not** make it player-startable yet. Do not add a Camp Mountains button, region picker, or start request in this package.

No private event/reward/run-generation definitions should be exposed to the browser.

#### Required tests

Content/run generation:
- Mountains region resolves `run_generation.mountains`;
- generated graph has exactly the seven authored nodes and six edges above;
- exact node identities, encounter/event IDs, positions, start availability, and locked descendants validate;
- all three combat encounters and the Boss remain region-compatible;
- client region projection contains Mountains while event/reward/run-generation internals remain private;
- Farm authored content/run generation remains unchanged.

MySQL-backed lifecycle:
- persist a Mountains run through the existing generator/persistence boundary without relaxing public StartRun authorization;
- prove `combat_1 -> loot -> combat_2 -> rest -> combat_3 -> boss -> exit` unlocks only the direct next node;
- Loot grants exactly 8 Teeth once and same-key replay does not regrant;
- Rest fully restores participating run HP;
- each Combat/Boss persists battle identity/playback through the shared pipeline;
- Mountains Boss grants exactly 16 XP per participant and returns `unlocks: []`;
- Boss retry/replay does not regrant XP;
- Exit completes the Mountains run through structural terminal validation, unlocks no child, and exact same-key replay is stable;
- successful Mountains completion does not add a Swamps unlock;
- failure in a Mountains combat/Boss follows the existing failed-run lifecycle;
- ownership/idempotency/rollback invariants remain intact.

Frontend:
- current-run parsing/rendering accepts the seven-node Mountains fixed graph without Farm-specific assumptions;
- RunScene uses existing generic labels/actions for Combat, Loot, Rest, Boss, Exit;
- BattleScene presents Mountains XP-only Boss rewards correctly;
- no region-selection/start UI is added yet.

#### Verification

Run:
- `npm run verify:package`;
- focused content/run-generation tests;
- focused Mountains lifecycle tests;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- applicable MySQL-backed focused tests;
- `npm run test:backend:docker`.

Report test/assertion/skipped counts where available.

#### Out of scope

- changing public run-start authorization;
- Camp region selection;
- Swamps unlock/content;
- Lizard Kin;
- Wrong Machine recovery;
- prototype branching run-pattern generator;
- hazards/shrines/Chaos nodes;
- economy tuning beyond the explicit deterministic rewards above;
- broad UI/visual overhaul.

#### Completion

Implement only Package 3. Leave it **In Progress** for architectural review. Do not promote Package 4 or make Mountains publicly startable.
