# Active Milestone

Read this for sequencing/planning or when closing/promoting an execution package. Normal implementation should use `agent/ISSUES.md` instead.

## Milestone 2 - Warband

**Status:** Active

### Related Issues
- Establish Phaser unit detail, rename, loadout, and dice-binding flows

Milestone 1 - Walking Skeleton is complete and passed manual user UAT. Its major visual-quality finding is intentionally deferred to the later game-wide visual/UI overhaul; Milestone 2 should prioritize functional clarity and consistency rather than final presentation fidelity.

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
- Active-run mutation locking is not fabricated before run persistence exists. Milestone 2 command boundaries must permit Milestone 3 to add authoritative active-run checks without redesigning the Warband contracts.
- Existing prototype Unit/Dice/Team repositories, Angular Warband pages, profile synchronization, and related tests are mined for useful behavior but are not target architecture. Retire only live/superseded paths whose vNext replacement is proven; preserve later reuse evidence until its owning package.
- Milestone 2 passes fresh-database, authored-content, backend, frontend, production-build/bundle, responsive-capture, and real-stack verification appropriate to the completed Warband slice before manual user UAT.

### Package Queue
Promote/decompose only the first unfinished package into `agent/ISSUES.md`:
1. ~~Warband persistence foundation.~~ Complete and architecturally approved.
2. ~~Warband authored content + validation/projection.~~ Complete and architecturally approved.
3. ~~Authoritative Warband read APIs + controlled development/UAT fixtures.~~ Complete and architecturally approved.
4. ~~Squad commands + active-squad bootstrap integration.~~ Complete and architecturally approved.
5. ~~Unit rename + atomic loadout/dice-binding command.~~ Complete and architecturally approved.
6. ~~Phaser Warband navigation + lazy read/cache surfaces.~~ Complete and architecturally approved.
7. ~~Phaser squad editor + activation/lifecycle flows.~~ Complete and architecturally approved after correction pass `a8dc9448adece3a3da1251842fcbdefcd5f89f95`.
8. **Phaser unit detail + loadout/dice configuration flows.** Current.
9. Warband integrated verification/closure.

### Package Review Workflow
For each package:
1. coding agent implements only the current `agent/ISSUES.md` package and leaves it in review state;
2. architectural review evaluates the pushed changes against accepted contracts and the package acceptance criteria;
3. corrections are returned to the same package until approved;
4. planning records the package complete and promotes exactly one next package.

Do not implement later packages early merely because their eventual shape is known.

### UAT Sequencing
Manual user UAT occurs after package 9 is technically complete and passes final architectural review. UAT is milestone-level validation rather than an acceptance criterion inside packages 1-9.

The deferred major visual/UI overhaul remains out of Milestone 2. Functional screens should be clear, responsive, and reasonably consistent, but do not spend package scope pursuing final production visual fidelity.

Do not begin Milestone 3 - Enter Farm until Milestone 2 UAT is complete and its findings are resolved or deliberately deferred.
