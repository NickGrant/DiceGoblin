# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 5 - Mountains boss and visible Swamp handoff

**Status:** In Progress
**Priority:** High

#### Problem
Package 4 now carries the Wrong Machine investigation through the Mountains and introduces The Archivist directly before the existing kobold boss. The boss conclusion still does not perform the canonical handoff: first successful Mountains boss victory must establish that the Wrong Machine was moved to the Swamps and grant durable Swamp region access.

Milestone 13 owns actual Swamp gameplay. Package 5 therefore needs to represent Swamps as the next unlocked destination without making a Swamp run startable before its content exists.

#### Canonical target
- The player reaches the Mountains boss after learning that the Wrong Machine passed through the kobolds and after directly meeting The Archivist.
- First successful Mountains boss victory establishes that the Wrong Machine was moved to the Swamps.
- That first victory grants `unlock.region.swamps` exactly once through normal reward/unlock authority.
- Swamps become visible/unlocked in player-facing progression after the boss conclusion.
- Swamp run entry remains unavailable until Milestone 13 supplies the canonical Swamp run/content implementation; an owned future-region unlock must not manufacture a missing run generator.
- `capability.wrong_machine_access` remains absent. The player knows where to search next but has not recovered the Machine.
- No Archivist/Order combat troops, Frogmen, Frog Kin, or other Swamp gameplay are introduced here.

#### Accepted baseline
- Package 4 is accepted through implementation `ae75a0a2b1c2e1cf544998135aa2fe1db761cda7` and evidence `cf9b99cba3f2aab7acb48a95fe6d6df11a228a6b`.
- Mountains contain `codex.mountains_kobold_trail` and `codex.mountains_archivist` through run-only dialogue nodes.
- The Archivist conversation withholds the Machine's destination so the boss conclusion can own the Swamp revelation.
- Mountains combat remains kobold-only.
- The existing Mountains boss reward is XP-only at the Package 4 baseline.
- No Swamp unlock or Wrong Machine capability is currently granted.

#### Required implementation
1. **Boss conclusion knowledge.** Author the minimum player-facing result needed for first Mountains boss victory to establish that the Wrong Machine was moved to the Swamps. Use meaningful Codex/dialogue/reward concepts already accepted; do not add generic story flags or history state.
2. **Durable Swamp unlock.** Add `unlock.region.swamps` through canonical authored progression/reward content and grant it on the first successful Mountains boss resolution through existing unique reward semantics.
3. **First/repeat semantics.** The Swamp unlock and any one-time retained revelation must be unique/idempotent. Repeat Mountains boss victories remain valid but do not restage the discovery as new or duplicate progression.
4. **Visible but not startable.** Project the owned Swamp region appropriately so Camp/map progression can show it as unlocked/next. Do not create a fake/placeholder Swamp run graph solely to make start authorization pass. Starting Swamps before M13 content exists must fail safely/authoritatively.
5. **No Wrong Machine recovery.** Do not grant `unlock.capability.wrong_machine_access`, reconstruct kin, or imply that the player possesses/controls the Machine. The result is location knowledge plus access to the next region only.
6. **M13 boundary.** Do not add Swamp encounters, Frogmen, Archivist/Order troops, Swamp boss behavior, Frog Kin, reconstruction, first Machine-use follow-up, or broader-map opening.
7. **Revision/idempotency/reload.** Preserve player revision, exact-key replay, reward uniqueness, reconnect/reload, and repeat-run semantics. A reload after the first boss victory must preserve the Swamp unlock/revelation.
8. **Safe projection.** Browser-facing state may reveal that Swamps are unlocked and that the Machine was moved there, but must not leak future Swamp topology, enemies, boss rewards, Wrong Machine recovery mechanics, or M13-only content.

#### Architectural constraints
- `documentation/01-lore/01-story-and-biome-progression.md` owns the narrative order.
- Follow `agent/CONTEXT_ROUTER.md` for progression, rewards/unlocks, authored content, API/storage, Phaser/runtime, and testing authority.
- Backend/MySQL remains authoritative; Phaser renders authoritative progression state.
- Authored identities/content belong in canonical JSON.
- Reuse the existing boss reward, unique reward, region unlock, Codex, bootstrap/projection, and run-start boundaries.
- Do not add a generic story/progression store.
- Do not weaken run-start authorization merely because the Swamp unlock can now be owned before its run implementation exists.

#### Minimum proof
1. Before successful Mountains boss completion, `unlock.region.swamps` is absent and Swamps are not projected as unlocked.
2. First successful Mountains boss completion grants the Swamp revelation/knowledge as designed and `unlock.region.swamps` exactly once.
3. Exact-key replay returns the original result without duplicate unlock/knowledge/revision mutation.
4. Repeat Mountains runs/boss victories remain valid and do not duplicate the one-time progression result.
5. Reload/reconnect preserves the durable Swamp unlock and retained revelation.
6. Swamps are visible/unlocked in appropriate player-facing progression after the boss.
7. Attempting to start a Swamp run before M13 gameplay exists fails safely and does not create/mutate a run.
8. `capability.wrong_machine_access` remains absent.
9. Mountains combat remains kobold-only; no Archivist/Order troops or Frogmen are introduced.
10. No Frog Kin, reconstruction, broader-map opening, or other M13 gameplay is introduced.
11. Existing Farm/Post-Farm Whim/Mountains narrative, boss, repeatability, region authority, reward/idempotency, and client projection regressions pass.

#### Verification
Run focused backend tests for Mountains boss first/repeat resolution, reward uniqueness, Codex/unlock state, exact-key replay, revision, bootstrap/region projection, reload, and rejected Swamp run start without a generator. Run focused frontend tests for region/Camp projection if client behavior changes. Run applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure` because this package changes production progression/content.

#### Scope exclusions
- Swamp run graph or playable Swamp content;
- Frogmen or Archivist/Order combat troops;
- Swamp boss behavior;
- Wrong Machine capability access/recovery/reconstruction;
- Frog Kin;
- first Wrong Machine-use follow-up;
- broader-map opening;
- broad visual redesign;
- M13 parity work or M14 hardening.

#### Completion evidence required
Record implementation SHA(s); exact Mountains boss reward/content and Swamp identity paths changed; how the boss establishes the Swamp destination; proof `unlock.region.swamps` is first-victory/idempotent; how unlocked-but-not-yet-playable Swamp projection works; proof Swamp start fails safely without M13 content; proof Wrong Machine access remains absent; exact-key/revision/repeat/reload results; focused backend/frontend counts; content revision if changed; and full package-gate results.

Leave Package 5 **In Progress** for architectural review. Do not activate Package 6 automatically from the coding agent.
