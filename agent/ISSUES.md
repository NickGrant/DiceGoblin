# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 10 - Focused manual UAT before Milestone 11

**Status:** In Progress
**Priority:** High

#### Problem

Milestone 10 technical implementation and automated verification are complete, but the player-facing run experience must pass focused manual UAT before the milestone can close and Milestone 11 begins.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Packages 1-9 are approved, with Package 9 approved at `88ca459864f0e545637ed13240e7c426717b6e38`.

The accepted Milestone 10 slice includes authoritative exploration-frontier branching, generic non-combat interactions, production Mountains Hazards, test-proven Shrine/Chaos families, run-scoped next-combat modifiers, contextual consumable/multi-step interactions, and Phaser RunScene integration. Farm remains the fixed tutorial. Mountains remains the only production branching/Hazard expansion in Milestone 10; production Shrine/Chaos remains reserved for Swamp/Milestone 13.

Package 9 completed integrated technical closure and test-suite rationalization. The canonical regression suite is now zero-skip; future Milestone 11-13 prototype migration evidence is explicitly outside canonical test discovery.

#### Purpose

Perform focused player-facing UAT against the production-composed Milestone 10 experience. This package is a manual acceptance gate, not an implementation package. Do not add Milestone 11 functionality while UAT is pending.

#### Manual UAT checklist

Verify on the current `vnext-game-overhaul` branch/build:

1. **Farm tutorial run**
   - Start a Farm run with a valid squad.
   - Confirm the run remains the fixed Combat -> Loot -> Rest -> Boss -> Exit path.
   - Complete the run and confirm node availability advances correctly, combat returns to the run map, Rest resolves correctly, Boss completes, and Exit returns safely to Camp.
   - Confirm completed combat/boss playback may be revisited without re-resolving gameplay.

2. **Mountains exploration run**
   - Start a Mountains run.
   - Confirm the map visibly branches and presents only currently available nodes as actionable.
   - Resolve one branch and confirm another already-available sibling remains available.
   - Confirm locked nodes cannot be entered early.
   - Exercise a production Hazard and confirm its authoritative result is presented and the run remains usable afterward.
   - Exercise an optional dead end if available and confirm it does not incorrectly terminate or consume other available routes.
   - Continue through Boss and Exit and confirm the run completes normally.

3. **Run persistence and navigation**
   - During an active run, return to Camp and re-enter the run; confirm topology, completed nodes, available frontier, unit HP, and any visible active run state remain coherent.
   - Reload the browser during an active run and confirm the same authoritative run state returns.
   - Confirm abandoning a run still requires confirmation and returns cleanly to Camp.

4. **Run supplies**
   - Open Supplies during a run.
   - If a healing consumable is owned, use it on an eligible participating unit and confirm inventory and HP reconcile correctly.
   - Confirm invalid/ineligible use is not presented as a successful mutation.

5. **Responsive/player-facing sanity**
   - Inspect the run experience at the normal desktop viewport used for play.
   - If practical, spot-check Compact landscape and Wide layout for readable topology/actions without overlap or inaccessible controls.
   - Confirm portrait touch-first presentation still shows the rotate-device gate rather than a broken gameplay layout.

#### Acceptance criteria

Manual UAT passes when the user confirms that the production Farm and Mountains run flows are functionally usable and no blocking regression is found in branching/frontier behavior, encounter interaction, combat return, persistence/reload, supplies, run completion, or responsive interaction.

Minor visual-polish observations may be recorded for the deferred visual/UI overhaul if they do not prevent play or obscure authoritative state/actions.

If UAT exposes a functional defect, keep Package 10 In Progress and correct only the defect required to satisfy the accepted Milestone 10 contracts. Run appropriate focused/full verification after any correction before repeating the affected UAT case.

#### UAT correction in progress: Camp facility access

The local account reset was complete, but Shop and Academy were always open in vNext. The user confirmed the original progression rule for this correction: Farm boss completion grants Shop access, and Academy access costs 250 Teeth in Shop. The correction authors the Farm reward and Shop offer, enforces both gates at the API and Phaser Camp boundaries, and keeps the account's current run untouched.

Implementation verification: focused affected backend integration suites passed (68 tests, 738 assertions); authored Shop/reward content suites passed (38 tests, 125 assertions); final `npm run verify:package` passed (backend 759 tests / 5,275 assertions; frontend 495 tests; content validation, production build, bundle, documentation, context, and diff gates passed). Deterministic locked Camp Compact and Shop Standard/Compact captures completed. During UAT, the existing local database volume was found to predate the canonical baseline: `run_nodes.encounter_step_id` and `run_modifiers` were missing. Those two canonical schema pieces were added to the local UAT database without resetting or modifying the active Farm run; its current-run query then returned the intact five-node Farm graph and participating unit HP. Package 10 remains In Progress pending repeat manual UAT.

#### UAT correction: victorious Combat/Boss material receipt projection

The Farm Boss victory committed successfully in the local UAT run, but the browser rejected its finalized `item_grants` field. The server's authoritative victory-material grant had been added after the strict Combat/Boss response parser and was not represented in that browser contract. The correction validates and projects only item ID, granted quantity, and owned quantity from the optional finalized grant list for both Combat and Boss, and invalidates a loaded inventory slice when new materials arrive. It preserves the existing boss battle, unlocks, and materials; no gameplay command was replayed or run state edited. The persisted UAT boss receipt showed the granted Shop/Mountains unlocks and Pig materials, and the boss node is completed with Exit available.

Verification: focused frontend suite passed (496 tests), including representative finalized Farm Combat/Boss material receipts, malformed/private grant rejection, and RunScene inventory invalidation. Full `npm run verify:package` passed: backend 759 tests / 5,275 assertions; frontend 496 tests; content, context, documentation, production build, bundle, and diff gates passed. Repeat Farm Boss playback/Exit UAT is pending; Package 10 remains In Progress.

#### Scope exclusions

Do not add Knowledge/Codex/Objectives, onboarding/Mystic Cave, Swamp/Frog Kin, production Shrine/Chaos, new encounter families, new modifier families, or broad visual redesign during this UAT package.

Do not begin or promote Milestone 11 until the user explicitly confirms that Milestone 10 UAT passes.
