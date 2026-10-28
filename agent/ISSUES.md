# Active Execution Issue

## Milestone 11 - Knowledge and Objectives

### Milestone 11 Package 7 - Phaser Objectives surface + authoritative reconciliation

**Status:** In Progress
**Priority:** High

#### Problem

Packages 4-6 established the complete authoritative objective lifecycle: authored objectives, user-scoped mutable progress, gameplay-fact advancement, automatic completion, and canonical completion rewards. The client already has a strict lazy objective read/cache contract, but players have no Phaser surface for seeing objectives or their progress.

Package 7 must expose that accepted backend authority in the game client without creating client-side progress/completion logic or duplicating reward state.

#### Accepted baseline

- Milestones 1-10 are complete and passed manual UAT.
- Milestone 11 Packages 1-3 established Codex ownership/presentation and replayable knowledge-aware dialogue.
- Package 4 established the authoritative objective read model and lazy GameStore objective cache.
- Package 5 connected finalized combat, successful region-run completion, and dialogue completion to objective counters.
- Package 6 is approved through `cdaaee5f85b35557c5b249ade3b5eff72624e3bf` (implementation `c3e258cb1f4b1a23cd477b915c75fe6c4306a45f`).
- Package 6 automatically completes satisfied objectives and applies optional completion events through the canonical reward pipeline exactly once in the owning gameplay transaction.
- Objective read responses expose only player-safe `id`, `title`, `description`, `category`, `status`, and requirement `id`, `label`, `current`, `needed` facts. Requirement kinds/targets and completion/reward internals remain server-private.
- The GameStore objective collection is lazy, revision-aware, and authoritative; browser code must not fabricate counters, completion, or rewards.
- `documentation/07-development-path/vnext-phaser-client-architecture.md` is canonical for Phaser/runtime/responsive behavior; `documentation/04-ux/01-visual-design-guide.md` governs current visual direction.

#### Purpose

Add the smallest usable Phaser Objectives presentation integrated with Camp and the existing runtime/cache architecture.

Package 7 must:

1. provide an Objectives entry point from Camp;
2. present authoritative objective status and requirement progress from the existing lazy objective cache;
3. distinguish unstarted/active/completed objectives clearly without inferring state locally;
4. reconcile after authoritative player revision changes and recover from read errors/retries;
5. remain usable across Compact, Standard, and Wide layouts and obey the existing portrait gameplay gate;
6. preserve server-private objective wiring/reward configuration.

#### Required inventory before implementation

Inspect and record reuse/drop decisions for:
- Package 2 Codex Camp navigation/surface patterns;
- current `GameScene` mounted-surface lifecycle and return-to-Camp behavior;
- Package 4 objective contracts, RuntimeApiClient, GameStore lazy loading, stale/in-flight handling, and strict parser tests;
- player revision reconciliation after combat/run/dialogue mutations;
- current responsive helpers and Compact/Standard/Wide deterministic capture conventions;
- current visual-design guidance and existing Camp controls.

Prefer reuse of the Codex presentation/navigation architecture where semantically appropriate rather than introducing another client subsystem.

#### Canonical presentation semantics

- The Objectives surface is read-only presentation of backend authority.
- Render objective ordering exactly as supplied by the authoritative read contract unless an existing accepted client convention explicitly requires a server-projected sort key. Do not invent gameplay-priority ordering from hidden requirement types.
- Show each objective's player-facing title, description/category where useful, authoritative status, and requirement label/current/needed progress.
- `unstarted`, `active`, and `completed` come from the server response. Do not derive completion by checking whether counters equal targets.
- Completed objectives remain visible unless the accepted server projection excludes them; do not silently delete historical completed objectives client-side.
- Do not reveal requirement kind/target IDs, completion event IDs, reward definitions/probabilities, or other server-private configuration.
- Do not add objective claim buttons, progress controls, or reward application UI.

#### Camp and lifecycle integration

- Add a clear Objectives destination from Camp using the existing mounted Phaser surface/navigation conventions.
- Entering Objectives should lazy-load through the existing GameStore objective API/cache boundary.
- Returning to Camp must cleanly destroy Objectives-owned presentation/input objects and preserve persistent GameRuntime state.
- Re-entering while the cache is current should reuse authoritative cached data rather than issuing unnecessary reads.
- A stale cache caused by an accepted player revision must refresh before presenting stale progress as current.
- Recoverable API/read failure must present a usable error/retry path without fabricating objective data.
- Stale in-flight responses must not overwrite newer authoritative state.

#### Reconciliation requirements

- Combat/run/dialogue remain the mutation authorities. The Objectives screen must not subscribe to or replay gameplay facts itself.
- After an accepted mutation changes player revision, the established GameStore revision path should invalidate objective state. Opening/reloading Objectives then retrieves the authoritative projection.
- Exact replay/no-op mutations that do not change revision must not cause invented objective changes.
- Objective completion rewards are reconciled through existing authoritative wallet/unlock/Codex/inventory/unit/bootstrap/current-run paths; the Objectives screen does not locally apply or reconstruct them.
- Do not add a client objective mutation endpoint.

#### Responsive and interaction requirements

- Follow the accepted 1600x900 logical reference and Compact/Standard/Wide runtime classifications.
- Verify at least the canonical viewports: 844x390 Compact, 1600x900 Standard, and 2560x1080 Wide.
- Portrait touch-first gameplay remains blocked by the existing rotate-device gate; Package 7 must not bypass or duplicate that gate.
- Lists/details/progress must remain readable and navigable when objective or requirement counts exceed a single screen. Use deterministic paging/scrolling/navigation consistent with existing Phaser surfaces; do not assume the initial fixture count is the production maximum.
- Keyboard/pointer/touch behavior should follow existing mounted-surface conventions and clean up listeners on exit.
- Visual treatment should fit the current Camp/Codex direction without attempting the deferred game-wide visual overhaul.

#### Minimum proof

Prove at minimum:

1. Camp exposes an Objectives entry point and entering it lazy-loads the authoritative objective collection;
2. server ordering and player-facing fields are preserved;
3. unstarted, active, and completed objectives render distinctly from the authoritative `status` field;
4. requirement current/needed progress renders correctly without client completion inference;
5. completed objectives remain present and no claim/reward action is offered;
6. current cache is reused on re-entry while revision invalidation causes an authoritative refresh;
7. stale in-flight reads cannot overwrite newer state;
8. read failure exposes retry and successful retry recovers;
9. return to Camp cleans up Objectives presentation/input state;
10. Compact, Standard, and Wide deterministic verification remains usable, and portrait behavior remains governed by the existing gate;
11. browser/runtime code has no requirement-kind/target or completion-event/reward configuration dependency.

#### Verification requirements

Add focused frontend tests for:
- Camp -> Objectives -> Camp navigation/lifecycle;
- lazy first load and current-cache reuse;
- revision-stale reload behavior;
- stale in-flight response protection;
- error and retry;
- authoritative ordering/status rendering;
- requirement progress rendering;
- completed visibility and absence of claim/progress mutation actions;
- multi-objective/multi-requirement overflow navigation;
- cleanup of input/listeners/presentation on exit;
- Compact/Standard/Wide layout behavior;
- portrait gate compatibility;
- strict objective contract/private-field rejection remaining green.

Run applicable gates in `agent/QUALITY_GATES.md` and full `npm run verify:package` before review. Produce deterministic captures if the existing visual verification workflow supports this surface.

#### Scope exclusions

Do not add in Package 7:
- new backend objective semantics or requirement kinds;
- client-authored objective progress/completion;
- manual claim APIs or buttons;
- objective-specific reward application/reconstruction;
- generic history/player-progression state;
- dialogue history/story flags;
- Mystic Cave/onboarding;
- Swamp/Frog Kin or production Shrine/Chaos expansion;
- broad visual redesign;
- Milestone 12+ work.

#### Completion evidence required

Report:
- implementation SHA;
- Camp/navigation and mounted-surface design;
- objective cache/read reuse and reconciliation behavior;
- status/progress presentation and overflow behavior;
- error/retry and stale-read handling;
- responsive/portrait verification;
- proof that private objective wiring/reward configuration is absent from presentation logic;
- focused frontend results and deterministic capture results where applicable;
- full package-gate results;
- confirmation that no backend objective redesign, client mutation/claim authority, generic history/progression state, or M12+ work was introduced.

Leave Package 7 **In Progress** for review. Do not promote Package 8.

#### Package 7 implementation evidence (review candidate)

- Implementation: `d3da60c` on `vnext-game-overhaul`. Camp now has a seventh Objectives destination. `GameScene` owns its mounted screen and return path; leaving destroys its container, keyboard listener, and GameStore subscription.
- The screen calls the existing lazy `GameStore.loadObjectives`/`retryObjectives` path. Fresh cache is reused on re-entry; stale/revision changes hide old progress while reloading. GameStore's existing generation/epoch checks discard old in-flight responses. Read errors display Retry without objective data.
- Objective order, status, title, description, category, and requirement progress come only from the strict server projection. Completed entries remain selectable. Long lists and detail text use deterministic pages with pointer/touch buttons and keyboard navigation. No claim, progress mutation, or reward reconstruction is present. The screen imports only `ObjectiveEntry`; private requirement kind/target and completion/reward wiring are absent.
- Focused frontend: 65/65 Objectives, Camp, objective-contract, and GameStore tests passed. The final Escape-key cleanup was checked with 29/29 Objectives and Camp tests; GameScene retains Escape ownership.
- Deterministic captures passed: Objectives at 844x390 Compact, 1600x900 Standard, 2560x1080 Wide, 390x844 portrait (`gate=active`), and 1600x900 read error; Compact Camp with the new destination also passed. Capture files are under ignored `artifacts/screenshots/m11-p7-*`.
- Final complete `npm run verify:package`: PASS in 12m 34s, 795 backend tests / 5,759 assertions, 524 frontend tests, production build, bundle, content, syntax, context, docs, and diff checks. No backend or content files changed.
- No backend objective redesign, client mutation/claim authority, generic history/progression state, or Milestone 12+ work was introduced. Package 7 remains In Progress for architectural review.
