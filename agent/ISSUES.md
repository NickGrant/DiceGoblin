# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 5 - Mountains integrated verification/closure

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Package 1 Mountains combat foundation is approved at `4adff479b4c10af40057f1f93088c0930e5894d8`.

Package 2 region-neutral Boss/Exit contracts are approved at `a667ee703da5f868fd65218199ad271553110bc6`.

Package 3 Mountains authored run graph/events/rewards + terminal lifecycle is approved at `f06e150e68ef39c7eeab1299a61d00a6dd2f9bea`.

Package 4 unlock-aware multi-region run start + Camp region selection/resume is approved at `739276df0207c1ce0e845bd938546645f5f387b2`, with closure proof:
- GitHub Full Verification: backend 792 tests / 1,626 assertions; frontend 475 tests; all standard gates PASS;
- MySQL bootstrap: 6 tests / 51 assertions / 0 skipped;
- MySQL run start: 28 tests / 255 assertions / 0 skipped;
- full Docker backend: 599 tests / 2,527 assertions / 150 skipped;
- no defects exposed.

The subsequent branch changes through `9de8b8966b41f0be9f27202085a690792e72c48f` affect planning/tooling only and do not change Package 4 gameplay/application behavior. Full Verification remains green there.

#### Problem

Close Milestone 6 technically by proving the **integrated second-region path** through the accepted production composition, not by adding another feature.

Farm and Mountains are already independently covered. This package must prove that the pieces compose as one progression path:

`Farm completion -> Mountains unlock/availability -> Mountains public start -> full Mountains lifecycle -> terminal return to Camp`.

No new gameplay system, region, reward type, endpoint, repository layer, scene, or speculative abstraction belongs in this package.

#### Primary closure proof

Add or refactor a MySQL-backed integration test so one scenario uses the **production service composition** and proves the transition across the complete accepted boundaries.

Prefer a dedicated closure test or refactor `MountainsRunLifecycleTest`. Do not manually persist a Mountains graph as the primary success path now that Package 4 supports authorized Mountains start.

The integrated success scenario must prove:

1. Create/provision a real vNext player/warband fixture.
2. Before ownership, Mountains is not available/startable.
3. Complete the Farm progression required to grant `unlock.region.mountains` through the accepted reward/lifecycle path.
4. Complete Farm Exit and obtain authoritative post-run bootstrap state.
5. Bootstrap reports:
   - no active run;
   - owned Mountains unlock;
   - `available_region_ids = ['region.the_farm', 'region.mountains']`.
6. Start `region.mountains` through the normal production run-start boundary rather than manual graph insertion.
7. The persisted Mountains run is the canonical seven-node authored graph:
   `combat_1 -> loot -> combat_2 -> rest -> combat_3 -> boss -> exit`.
8. Resolve the complete Mountains run through the existing shared node-resolution/combat/reward pipeline.
9. Mountains Loot grants exactly 8 Teeth once.
10. Mountains Rest uses the existing full-recovery behavior.
11. All three kobold Combat nodes and the Chief Engineer Boss use the normal battle persistence/playback identity path.
12. Mountains Boss grants exactly 16 XP per participating unit and `unlocks: []`.
13. Mountains Exit completes the run through the generic terminal contract.
14. Final authoritative bootstrap has:
    - no active run;
    - Farm and Mountains still available;
    - no Swamps unlock/availability;
    - durable Mountains XP/Teeth effects retained.
15. Same-key replay/idempotency guarantees used along the path remain stable; do not create a claim/reroll/double-grant path.

Use existing production commands/controllers/services from `ControllerServiceFactory` wherever practical. Avoid rebuilding a parallel dependency graph inside the closure test merely to make the scenario pass.

#### Existing Mountains lifecycle test

The current `MountainsRunLifecycleTest` was written before public Mountains start existed and therefore manually persists the Mountains graph.

Update that coverage so the primary successful Mountains lifecycle path begins from an **authorized normal Mountains start**. Retain focused defeat/Boss-failure tests if they remain valuable, but remove obsolete assertions/comments that imply Mountains must remain globally unavailable.

Do not weaken the locked-Mountains negative coverage from Package 4: Mountains must still reject a player who does not own the authored unlock.

#### Region-generalization audit

Audit current production vNext paths for residual Farm-specific executable assumptions.

At minimum inspect:
- `backend/src/Application`
- `backend/src/Repositories`
- `backend/src/RunGeneration`
- `frontend/src/app/game/runtime`
- `frontend/src/app/game/scenes`
- `frontend/src/app/game/screens`

A Farm identity/string is acceptable in:
- canonical Farm authored content;
- Farm-specific tests/fixtures;
- simulation/debug tooling whose purpose is explicitly Farm-specific.

It is **not** acceptable in shared production behavior where Mountains proves the assumption false.

Current architectural review found no `region.the_farm` or Farm-specific string in production backend application code or the Phaser game runtime. Do not manufacture abstractions just to remove legitimate Farm test/content names. If the audit remains clean, record it through tests/docs rather than changing production code.

#### Frontend closure

No new client feature is expected.

Re-run and preserve coverage proving:
- authoritative bootstrap region availability;
- Camp only offers authorized projected regions;
- Mountains selection sends `region.mountains`;
- ambiguous start retry retains region + idempotency key;
- active Mountains resume does not POST another start;
- RunScene renders Mountains without Farm-specific copy;
- Mountains Exit reconciliation returns to Camp safely.

Only add frontend implementation code if an integrated verification case exposes a concrete defect.

#### Verification

Run all applicable closure gates:

- `npm run verify:package`
- `npm run test:db:provision:docker`
- `npm run test:db:reset:docker`
- focused integrated region-generalization/Mountains lifecycle test(s)
- focused `GameBootstrapControllerTest.php`
- focused `RunStartControllerTest.php`
- focused frontend Camp/RunScene lifecycle tests
- `npm run test:backend:docker`

Also run any existing supported browser/runtime closure probe that is materially relevant, but do not revive prototype run-pattern infrastructure or add a new debug endpoint merely for this package.

Report:
- test/assertion/skipped counts where available;
- the integrated closure scenario result;
- any remaining Farm-specific production assumption found by the audit.

#### Closure criteria

Package 5 is complete when the automated evidence demonstrates that Mountains is a genuine second region using the accepted architecture end to end, with no known Farm-only production assumption required for its lifecycle.

After architectural review, promote only Package 6: focused manual UAT.

#### Out of scope

- Swamps content or unlock;
- Lizard Kin restoration;
- Wrong Machine recovery;
- economy/inventory;
- Academy/progression breadth;
- new node types;
- branching/prototype run-pattern generators;
- visual redesign;
- balance tuning beyond detecting a blocking deterministic failure;
- broad CI/tooling redesign unrelated to proving this milestone.

#### Completion

Implement only Package 5. Leave it **In Progress** for architectural review. Do not promote Package 6 yourself.
