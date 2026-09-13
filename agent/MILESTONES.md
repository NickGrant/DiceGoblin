# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 2 - Warband

**Status:** Technical Complete - Awaiting Manual UAT

### Related Issues
- None active. Package implementation and integrated technical closure are complete.

Milestone 1 - Walking Skeleton is complete and passed manual user UAT. Its major visual-quality finding is intentionally deferred to the later game-wide visual/UI overhaul; Milestone 2 likewise prioritizes functional clarity and consistency rather than final presentation fidelity.

### Outcome
Establish the durable player-owned combat-configuration domain and make it fully usable through Phaser:

`authored unit/dice definitions -> owned unit/dice persistence -> saved squads -> authoritative lazy queries/commands -> Phaser Warband -> squad configuration -> unit loadout/dice configuration`

### Exit Criteria
- The fresh vNext baseline includes normalized owned-unit, owned-dice, squad, ability-loadout, and dice-binding persistence using authored stable IDs rather than SQL gameplay catalogs.
- Canonical JSON/ContentRegistry includes the authored unit/kin/ability/dice definitions required by the Milestone 2 Warband slice with structural and semantic cross-reference validation and an explicit browser-exposure boundary.
- Authoritative domain queries provide compact unit, dice, and squad collections plus full unit detail without reviving a catch-all profile payload.
- A controlled development/UAT fixture path can create representative owned Warband state without adding production starter-pack provisioning; normal account creation remains unchanged until onboarding owns it in Milestone 12.
- Squad commands create, atomically replace, activate, and delete saved squads with nine positions, ownership validation, authoritative responses, and `player_revision` updates.
- Unit configuration commands support rename and atomic complete ability-order/dice-binding loadout replacement with ownership, ability, die, slot, and configuration validation. Promotion/progression transactions remain deferred to Milestone 8.
- Bootstrap includes the active-squad state required for initial Camp presentation once an active squad exists, while complete collections remain lazy domains.
- Phaser `GameScene` owns navigation between Camp and Warband without restoring Angular gameplay pages or remounting the persistent runtime.
- GameStore/API client support lazy Warband domain loading and authoritative cache replacement after commands; no mutation relies on a global profile refresh.
- Phaser Warband exposes the owned-unit roster, owned dice, saved squads, active-squad state, and drill-down to configuration surfaces with functional responsive behavior.
- Phaser squad configuration supports the fixed 3x3/nine-position formation, saved squad lifecycle, membership/position editing, and active-squad switching using complete authoritative squad mutations.
- Phaser unit configuration supports unit detail, rename, ordered equipped abilities, and exact owned-die bindings using an editable local draft whose committed state changes only after server acceptance.
- Active-run mutation locking is not fabricated before run persistence exists. Milestone 2 command boundaries permit Milestone 3 to add authoritative active-run checks without redesigning the Warband contracts.
- Superseded unrouted Angular Warband, Dice, Unit Detail, and Squad Detail pages and their page-only Dice/Squad services are retired; prototype evidence needed by later milestones remains unregistered/unreachable from live vNext gameplay composition.
- Milestone 2 passes fresh-database, authored-content, backend, frontend, production-build/bundle, responsive-capture, security, and real-stack verification appropriate to the completed Warband slice before manual user UAT.

### Package Queue
1. ~~Warband persistence foundation.~~ Complete and architecturally approved.
2. ~~Warband authored content + validation/projection.~~ Complete and architecturally approved.
3. ~~Authoritative Warband read APIs + controlled development/UAT fixtures.~~ Complete and architecturally approved.
4. ~~Squad commands + active-squad bootstrap integration.~~ Complete and architecturally approved.
5. ~~Unit rename + atomic loadout/dice-binding command.~~ Complete and architecturally approved.
6. ~~Phaser Warband navigation + lazy read/cache surfaces.~~ Complete and architecturally approved.
7. ~~Phaser squad editor + activation/lifecycle flows.~~ Complete and architecturally approved after correction pass `a8dc9448adece3a3da1251842fcbdefcd5f89f95`.
8. ~~Phaser unit detail + loadout/dice configuration flows.~~ Complete and architecturally approved at `f65356bf16854c334415ba14e964d11f6c45cd0e`.
9. ~~Warband integrated verification/closure.~~ Complete and architecturally approved at `c72c2611d7e14d1d3151f42c05f791e4f1405c2d`.

### Technical Closure Evidence
Package 9 verified the complete Milestone 2 slice from fresh database/account through controlled Warband fixture, real `/game` startup, lazy Warband reads, squad lifecycle, unit detail/rename/loadout/exact-die configuration, authoritative cache reconciliation, and deliberate reload/re-read through real PHP/MySQL.

The closure matrix included fresh DB/schema validation, deterministic authored-content revision/projection checks, 242 backend tests / 1309 assertions with 75 intentionally skipped prototype-schema tests, 53 focused Warband backend tests / 555 assertions, 347 frontend tests, 60 focused Package 8 tests, production build and bundle check, cross-player security checks, `player_revision` behavior, stale/integrity recovery, and Compact/Standard/Wide capture inspection plus touch portrait gating.

The aggregate `verify:full` command remains host-limited because PHP is absent from the host `PATH`; its required constituents were executed separately using supported Docker/root-mounted paths. This is an environment limitation rather than a Milestone 2 product defect.

### UAT Sequencing
Manual user UAT is now the only remaining Milestone 2 gate.

The deferred major visual/UI overhaul remains out of Milestone 2. Functional screens should be clear, responsive, and reasonably consistent, but final production visual fidelity remains deliberately deferred.

Do not begin Milestone 3 - Enter Farm until Milestone 2 UAT is complete and its findings are resolved or deliberately deferred. After UAT passes, mark Milestone 2 complete/UAT passed and only then decompose/promote the first Milestone 3 execution package.
