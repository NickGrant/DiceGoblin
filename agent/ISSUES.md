# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 5 - Phaser onboarding presentation and fresh-player routing

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-4 established the authoritative Mystic Cave onboarding lifecycle end to end: ordered Whim dialogue/Codex knowledge, atomic Basic Goblin starting-Warband provisioning, `complete` derived from durable unit ownership, and normal bootstrap/Farm eligibility after completion. Those server/runtime contracts are not yet presented as a player-facing onboarding flow in Phaser. Package 5 must mount the Mystic Cave onboarding experience in the persistent game client and route fresh, interrupted, ready-to-provision, completed, and returning players from authoritative state without creating client-local tutorial authority.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
- Package 2 is approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02` (implementation `7661b012aeb5eb58a99c751d0d1eaf6157e89fae`).
- Package 3 is approved through evidence `2ef9aeca75280b7279c5f4bea4ae6260bcd7932a` (implementation `978db58e030b00f339574778ad8a7ce6072a31a4`).
- Package 4 is approved through evidence `045c5446b7bb6c016c530461e040ab38177706f0` (implementation `1f1be8cffa6492960befb9c680294e464175da49`).
- `GET /api/v1/onboarding` is authoritative for `required`, `in_progress`, `ready_to_provision`, and `complete`, and exposes only the current player-safe dialogue identity when applicable.
- Existing dialogue read/completion contracts execute the current Whim conversation and grant meaningful Codex knowledge through normal authority.
- Existing provisioning is the separate authenticated, CSRF-protected, idempotent mutation that turns `ready_to_provision` into a valid starting Warband; unit ownership then makes onboarding `complete`.
- Existing bootstrap exposes the active squad, Farm availability, revision, and normal gameplay state needed after completion.
- Package 4 proved no additional transition endpoint, completion flag, or runtime projection is required.
- Wrong Machine onboarding is knowledge-only. `capability.wrong_machine_access`, physical possession, reconstruction, Swamp/Frog Kin, and later provenance remain deferred.
- Phaser is the gameplay client and Angular remains the host/auth/account shell.
- The major game-wide visual/UI overhaul remains deferred; this package still must be responsive, legible, and usable under the accepted viewport model.

#### Purpose
Create the player-facing Mystic Cave onboarding presentation and fresh-player routing in the persistent Phaser runtime. The client must render and execute the authoritative current onboarding state, survive reload/reconnect, provision only when the server says `ready_to_provision`, and enter the existing Camp/Farm lifecycle once the server says `complete`. This is presentation/orchestration over accepted backend authority, not a new progression system.

#### Canonical Package 5 semantics
1. **Server state chooses the route.** On game entry/reconciliation, read authoritative onboarding state. `required`/`in_progress` route to Mystic Cave interaction presentation; `ready_to_provision` routes to the provisioning step; `complete` routes to the established normal Camp/game state. Do not infer completion from local storage, scene history, cached dialogue, or client-owned flags.
2. **Current interaction only.** For dialogue states, render only the current dialogue identified by the authoritative onboarding response and fetched through the existing dialogue contract. Do not embed the onboarding step sequence or future dialogue IDs in Phaser.
3. **Normal dialogue mutation.** Submit choices through the existing dialogue completion API with normal idempotency/reconciliation behavior. After success, refresh authoritative onboarding/Codex state rather than locally advancing a tutorial index.
4. **Provisioning remains explicit and separate.** At `ready_to_provision`, present the player-facing establishment/provision action and call the existing provisioning mutation. Do not synthesize the starting unit client-side or couple provisioning to dialogue completion.
5. **Complete means normal gameplay.** After provisioning/reconciliation returns `complete`, transition into the existing Camp/Farm lifecycle using the persistent runtime and bootstrap state. Do not create a second post-tutorial home screen or onboarding-only Camp state.
6. **Reconnect/resume.** Reload at `required`, `in_progress`, `ready_to_provision`, or `complete` must reproduce the correct presentation from backend authority. A failed/read-retry state must not corrupt or skip onboarding.
7. **Stale response safety.** Follow existing revision/cache reconciliation rules. Older onboarding/dialogue/provision responses must not overwrite newer authoritative state or route a player backward/forward incorrectly.
8. **Wrong Machine boundary.** Presentation may show the authored Wrong Machine knowledge/dialogue, but must not imply possession/access, expose reconstruction controls, or introduce Swamp/Frog Kin mechanics.
9. **Responsive behavior.** Implement the onboarding surface under the accepted Phaser responsive model: logical 1600x900; Compact when CSS height <=599 or safe logical width <1440; Wide when safe width >=1920 and CSS height >=720; Standard otherwise. Verify 844x390, 1600x900, 2560x1080, and 390x844 portrait. Portrait touch gameplay remains blocked by the rotate-device treatment while preserving state.
10. **Persistent runtime ownership.** Reuse the accepted `GameRuntime`/scene/cache/navigation/input/responsive boundaries. Do not introduce Angular gameplay ownership or a parallel onboarding state manager.
11. **No tutorial history.** Do not add local-storage tutorial completion, `has_seen_dialogue`, generic story/history state, client-authored progression, or another durable marker.
12. **Visual scope discipline.** Use the current accepted visual language/assets where available and create only the minimal onboarding-specific presentation needed. Do not turn Package 5 into the deferred broad visual overhaul.

#### Required implementation inventory
Before changing the client, inspect the persistent `GameRuntime`, GameScene/Camp routing, bootstrap/cache/revision handling, dialogue API/runtime methods, onboarding API/runtime methods, provisioning API/runtime methods, responsive coordinator/viewport rules, deterministic capture hooks, and existing loading/error/retry patterns. Reuse existing primitives wherever possible.

#### Minimum proof
1. A fresh player entering the game is routed to the first Mystic Cave Whim interaction from authoritative `required` state.
2. Completing the first conversation refreshes authority and presents the second conversation from `in_progress`; no client tutorial index advances it.
3. A reload after the first conversation resumes the second interaction.
4. Completing final knowledge presents the provisioning step from `ready_to_provision`, with no automatic provisioning.
5. Provisioning calls the existing mutation once and reconciles to `complete` with the server-provided starting Warband/bootstrap state.
6. A reload at `ready_to_provision` remains on provisioning; a reload after completion enters normal Camp/gameplay.
7. Existing returning/completed players bypass Mystic Cave onboarding and continue into normal gameplay without knowledge backfill.
8. Dialogue/provision failure provides a usable retry path without local progress corruption or duplicate grants.
9. Stale/out-of-order client responses cannot route the player contrary to newer revision/authoritative state.
10. Wrong Machine presentation grants/shows knowledge only and exposes no access/reconstruction behavior.
11. Compact, Standard, Wide, and portrait-blocked states are legible and preserve interaction state appropriately.
12. Existing Camp, Codex, Objectives, Warband, run, and battle routing remain regression-safe.

#### Verification
Add focused Phaser/runtime/API tests for authoritative route selection, dialogue refresh, provisioning separation, reload/resume, completed-player bypass, retry/error handling, and stale-response protection. Add deterministic captures for the onboarding surface at the required viewport matrix, including portrait rotate-device behavior. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`.

#### Scope exclusions
- new backend onboarding/progression authority unless a concrete integration defect proves one is required;
- new tutorial/history/story/completion flags;
- client-local durable onboarding state;
- physical Wrong Machine access/reconstruction;
- Swamp/Frog Kin implementation or Wrong Machine theft/recovery;
- broad Camp/game visual redesign;
- Milestone 13+ work.

#### Completion evidence required
Record implementation SHA; client/runtime inventory and reuse decisions; exact authoritative route mapping for all onboarding statuses; dialogue/provisioning orchestration; revision/cache/stale-response behavior; reload/reconnect/retry evidence; returning-player bypass; Wrong Machine knowledge-only confirmation; responsive/capture evidence at 844x390, 1600x900, 2560x1080, and 390x844; focused tests; full package gates; and confirmation no local tutorial authority or deferred visual/M13 scope was introduced.

Leave Package 5 **In Progress** for architectural review. Do not promote Package 6 automatically from the coding agent.
