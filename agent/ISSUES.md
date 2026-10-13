# Active Execution Issue

## Milestone 10 - Run Encounter Depth

### Milestone 10 Package 8 - Phaser RunScene branching/encounter interaction integration

**Status:** In Progress
**Priority:** High

#### Problem

Packages 1-7 now provide the authoritative backend contracts for exploration-frontier branching, generic non-combat interactions, production Mountains Hazards, test-proven Shrine/Chaos families, run-scoped modifiers, contextual inventory actions, and reload-safe multi-step encounter progress. The remaining player-facing gap is to ensure `RunScene` presents and reconciles these contracts coherently as one run experience rather than as backend-only capabilities or isolated test fixtures.

#### Accepted baseline

Milestones 1-9 are complete. Milestone 10 Packages 1-7 are approved through the Package 7 verification correction `bad02a8b80b18d7689faaa6b42db92ffc45395e6`.

The backend/MySQL run state remains authoritative. `GET /api/v1/runs/current` supplies the persisted exploration graph/frontier, player-visible interaction projection, active run modifiers, and current encounter step where applicable. Run-node mutation uses the existing resolution endpoint and finalized idempotency receipts. The client must reconcile from server authority rather than predict route availability, hidden effects, random outcomes, item eligibility, modifier consumption, or step transitions.

Production region vocabulary remains:
- Farm: Combat, Loot, Rest, Boss, Exit; fixed linear tutorial.
- Mountains: Combat, Loot, Rest, Hazard, Boss, Exit; branching/dead ends.
- Shrine and Chaos production content remain reserved for Swamp/Milestone 13.

Package 8 must not add speculative production Shrine/Chaos content merely to create screenshots.

#### Purpose

Complete the generic Phaser `RunScene` integration needed to make the accepted Milestone 10 run model usable and understandable to players.

The scene must correctly present multiple simultaneously available nodes, distinguish unavailable/completed/available topology, render the generic non-combat interaction surface including multiple actions and intermediate steps, submit authorized actions once, and reconcile authoritative results/reloads without family-specific Shrine/Chaos dispatch.

This is integration/presentation work, not the deferred game-wide visual redesign.

#### Required inventory before implementation

Before changing `RunScene`, inspect:
- the current RunScene/map model and tests;
- the shared GameRuntime/current-run cache and mutation reconciliation path;
- current generic non-combat action UI introduced in earlier packages;
- deterministic capture/debug fixtures already used for RunScene;
- the accepted responsive/orientation contract in `documentation/07-development-path/vnext-phaser-client-architecture.md`;
- Package 1-7 backend/client contracts.

Reuse current runtime/input/layout primitives. Do not create a second run-state store or family-specific encounter UI architecture.

#### Canonical interaction requirements

- Render the authoritative graph and all currently available frontier nodes. Resolving one available node must not visually remove another still-available node.
- Locked nodes are visible only to the extent already allowed by the accepted current-run projection and must not be interactable.
- Completed nodes remain distinguishable from available/locked nodes without becoming actionable again.
- Converging paths and optional dead ends must render from authoritative edges; do not infer a single route from node list order.
- Generic non-combat interaction uses projected title/body/prompt/actions only. Do not branch client behavior on Rest/Hazard/Shrine/Chaos family.
- Multiple actions render generically from server order and stable action identity.
- Intermediate interaction responses keep the node unresolved and trigger authoritative reconciliation so the next step/copy/actions appear after reload/re-entry.
- Contextual actions are shown only when projected by the server. Do not infer item eligibility from local inventory.
- While a mutation is pending, prevent duplicate submissions for that interaction.
- Random/hidden outcomes remain server-owned. The client may present finalized player-visible result facts but must not know outcome weights or hidden effect configuration.
- Active run modifiers may be presented using the accepted narrow projection. Do not expose authored tuning that the API intentionally withholds.
- Combat/Boss navigation continues through the accepted battle flow; returning from battle must refresh/reconcile the current run/frontier/modifier state.
- Terminal run behavior remains authoritative and must not leave stale actionable nodes on screen.

#### Responsive/presentation scope

Use the accepted logical 1600x900 reference and responsive classes:
- Compact: CSS height <=599 or safe logical width <1440.
- Standard: normal reference composition.
- Wide: safe logical width >=1920 and CSS height >=720.
- Portrait touch-first gameplay remains blocked by the rotate-device presentation while preserving runtime state.

Package 8 may make focused layout changes necessary for branching maps and encounter interaction usability. Do not perform the deferred global art/UI redesign.

The scene should remain usable with more than one available node and with interaction copy/actions of reasonable authored lengths. Avoid fixed positioning that only works for the current Mountains node count.

#### Test requirements

Prefer behavior/invariant tests over snapshots of incidental content. Do not add assertions for total catalog counts or exact unrelated content lists.

Prove at minimum:
- a branching current-run payload renders more than one available node as independently actionable;
- choosing one available node and reconciling the response preserves another still-available frontier node;
- locked and completed nodes are not actionable;
- edges/topology render from authoritative graph relationships rather than assumed sequential order;
- a generic Rest/Hazard interaction renders projected copy/actions without family-specific dispatch;
- multiple actions render and submit their stable action IDs correctly;
- a contextual action absent from the projection is not fabricated from inventory state;
- an intermediate `interaction_step` response does not mark the node completed locally and reconciliation renders the persisted next step;
- duplicate input is blocked while mutation is pending;
- exact server errors return the scene to a usable reconciled state rather than leaving input permanently disabled;
- finalized random encounter facts can be presented without exposing hidden outcome weights/effects;
- modifier projection can be displayed/reconciled without exposing hidden attack-bonus tuning;
- returning from Combat/Boss refreshes current-run state;
- terminal completion/failure removes stale run actions;
- Compact, Standard, and Wide deterministic captures remain legible for the affected RunScene states;
- portrait rotate-device behavior/state preservation remains green if affected by the changes.

Run applicable gates from `agent/QUALITY_GATES.md`.

#### Test-suite hygiene note

A repository-wide test audit performed when Package 7 closed found substantial retained prototype and brittle content-snapshot coverage. Do not expand those patterns in Package 8. Package 9 technical closure will include rationalization of the canonical suite: completed-prototype tests that are permanently skipped should be removed once their accepted vNext replacement coverage exists; whole-catalog counts and unrelated exact content inventories should be replaced with targeted invariants; exact authored graph assertions should remain only where the graph itself is a canonical product contract.

#### Scope exclusions

Do not add:
- production Shrine/Chaos content to Farm or Mountains;
- Swamp/Frog Kin;
- new encounter families or effect scripting;
- additional run-modifier families;
- new persistence/state architecture;
- Knowledge/Codex/Objectives;
- onboarding/Mystic Cave;
- Milestone 11+ functionality;
- broad/global visual redesign.

#### Completion evidence

Report:
- implementation SHA;
- RunScene/runtime inventory performed before implementation;
- branching/frontier presentation and reconciliation behavior;
- generic encounter action rendering/submission behavior;
- intermediate-step reconciliation behavior;
- pending-input/error recovery behavior;
- Combat/Boss return reconciliation;
- modifier/result presentation behavior if changed;
- proof no family-specific Shrine/Chaos client dispatch was introduced;
- focused frontend/runtime test results;
- deterministic Compact/Standard/Wide captures and portrait verification where applicable;
- backend regression result if shared contracts changed;
- full applicable package gates;
- confirmation no production Shrine/Chaos, Swamp, broad visual redesign, or Milestone 11+ scope was introduced.

Leave Package 8 **In Progress** for architectural review. Do not promote Package 9 yourself.
