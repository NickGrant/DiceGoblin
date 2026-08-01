# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## First Pig Kin Demo Release

### Complete first Pig Kin critical-path UAT

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
The next formal demo should stop at the moment the player creates their first Pig Kin. The full path needs one fresh-account UAT pass after the remaining demo fixes land.

#### Acceptance Criteria

- A fresh account reaches Mystic Cave, Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin reconstruction.
- Required Pig Kin materials, Raw Chaos, and catalyst progress cannot be lost to random chance or unclear gating.
- First-time and repeat-run story beats do not replay in a confusing order.
- Rewards, stolen pages, unlocks, items, Raw Chaos, and Pig Kin creation are visible at the expected moments.
- Any blocker is logged as a high-priority issue with reproduction notes.
- The demo branch/build receives a final pass using the release gate checklist.

### Finish required demo dialogue and repeat-run story clarity

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
The demo path needs all required story and post-Wrong-Machine dialogue to carry the player cleanly to the first Pig Kin without relying on outside explanation.

#### Acceptance Criteria

- Required Farm, Mountains, Swamps, Wrong Machine recovery, Mystic Cave return, and first Pig Kin dialogue exists and loads.
- The Whim and relevant biome characters have appropriate post-Wrong-Machine dialogue.
- One-time dialogue and repeat-run dialogue are clearly separated.
- Dialogue-driven codex/stolen-page unlocks appear in the right run results.
- Missing scripts fail gracefully in development and are covered by tests or data validation.

### Rework objectives for demo guidance

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
Objectives should guide the player toward the next critical Pig Kin demo action without cluttering Home with the full objective backlog.

#### Acceptance Criteria

- Home shows only the current objective.
- The full objective list moves to the codex or another appropriate reference surface.
- Objective text covers the demo chain through first Pig Kin reconstruction.
- Completed or unavailable objectives do not remain as distracting Home UI.
- Objective state is backend-authoritative enough to survive refresh and repeat runs.

### Validate and revise chaos nodes for demo

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
Chaos nodes are central to Raw Chaos and the Wrong Machine loop, but their reels, rewards, combat effects, and one-fight duration need focused verification before demo release.

#### Acceptance Criteria

- Chaos reel results affect the intended single fight only.
- Enemy family, encounter shape, rule, and reward reels all apply according to their authored meanings.
- Raw Chaos rewards remain gated behind Wrong Machine unlock rules.
- Battle logs expose enough data for QA to verify applied chaos effects.
- Chaos nodes do not create progression dead ends or repeated reward exploits.

### Finish hazard behavior needed for demo UAT

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
Generated hazards need enough real behavior and visibility to support demo UAT without turning optional branches into confusing punishment.

#### Acceptance Criteria

- Automatic generated hazard effects apply exactly once and are visible in claim results.
- Delayed hazard effects appear as active run effects and are consumed after the next eligible combat.
- Choice and mitigation hazards are either implemented with clear UI or held out of weighted generation for the demo.
- A hazard sampler or evidence packet exists for Farm, Mountains, and Swamps.
- Hazard balance notes are captured before final demo tuning.

### Verify consumable unlocks, inventory, and balance

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
Healing and energy consumables exist, but the demo needs verification that unlock timing, inventory UI, and scarcity support the first Pig Kin path.

#### Acceptance Criteria

- Consumable item unlocks happen at intended points.
- The player can inspect owned consumables in an inventory UI.
- Healing consumables work between encounters and do not erase rest-node value.
- Energy consumables work from the intended surfaces and do not bypass demo pacing.
- Consumable use is transactional, capped where applicable, and covered by tests.

### Rework Wrong Machine first-reconstruction UI

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
The Wrong Machine is the demo finale, so its UI needs to make eligibility, costs, Raw Chaos, lineage unlocks, and Pig Kin creation feel deliberate and understandable.

#### Acceptance Criteria

- The first Pig Kin reconstruction flow is clear without debug/dev framing.
- Required materials, catalyst, and Raw Chaos are readable before confirmation.
- Missing requirements clearly tell the player what to do next.
- Successful reconstruction shows the lineage unlock and granted Pig Kin unit.
- Duplicate reconstruction remains idempotent and does not spend materials.

### Stabilize academy promotion flow for demo

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
Academy promotion UI is currently difficult to use and visually out of step with the shop-style progression surfaces.

#### Acceptance Criteria

- Academy layout is reworked toward the shop UI style.
- Promotion options are readable, selectable, and confirmable.
- Unit tiers and requirements are visible without noisy placeholder copy.
- Existing academy unlock and research behavior remains intact.
- Promotion flow is usable on desktop and mobile.

### Polish warband and unit management for demo

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
Warband, squad, and unit screens need enough polish to support demo preparation and first Pig Kin review.

#### Acceptance Criteria

- Warband filtering, squad membership visibility, and unit card density support fast squad setup.
- Unit detail screens explain stats clearly and avoid legacy splice terminology.
- Squad editing remains usable when the available-unit list is long.
- Pig Kin identity is visible after reconstruction.
- Dice/unit/inventory pagination remains intact.

### Review guide and codex navigation/content

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
Guide and codex surfaces should explain the demo game clearly, and their navigation should feel consistent.

#### Acceptance Criteria

- Guide content accurately covers map nodes, starter classes, combat/action timing, dice, rewards, hazards, shrines, chaos, consumables, and first Pig Kin goals.
- Guide side navigation works.
- Codex navigation is restyled to match the guide navigation pattern.
- Codex entries earned during the demo path are discoverable.
- Outdated or duplicate guide/codex copy is removed.

### Convert run-node resolution toward modal presentation

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
Most non-map node results should resolve in modal-style presentations so run flow feels lighter and less like a full page transition for simple outcomes.

#### Acceptance Criteria

- Candidate node types for modal resolution are identified.
- Non-combat node result UI uses modal presentation where appropriate.
- Combat and chaos playback keep enough space for readable logs and animations.
- Claim, retry, refresh, and error states remain safe.
- Mobile layout is checked for modal overflow and action accessibility.

### Enable post-Wrong-Machine Farm generated-map behavior

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** Medium

#### Problem
The Farm can stay tutorial-fixed early, but after Wrong Machine recovery it should move toward the shared generated-map experience for repeat play.

#### Acceptance Criteria

- Farm remains appropriate for first-time onboarding before Wrong Machine recovery.
- After Wrong Machine unlock, Farm run generation can use the generated map engine or an equivalent repeat-run profile.
- Generated Farm maps preserve boss/material guarantees needed for Pig Kin progression.
- The shared map renderer handles the Farm repeat-run graph without special-case UI.
- Tests or simulation gates cover boss reachability and required reward protection.

### Complete demo release hardening

**Milestone:** First Pig Kin Demo Release
**Status:** Open
**Priority:** High

#### Problem
Before the formal demo handoff, `main`, production migrations, generated artifacts, debug toggles, and release evidence need one final reconciliation.

#### Acceptance Criteria

- `main` is synced and contains all intended demo PRs.
- Production-required migrations are listed and verified.
- Generated frontend artifacts are intentionally included or intentionally omitted according to release policy.
- Debug/dev surfaces are disabled in the release environment.
- Automated backend/frontend gates pass or accepted exceptions are documented.
- Formal demo notes record the build ref, known issues, and UAT evidence location.

## Codex Discovery Reward Rework

### Add durable Codex entry ownership and profile payload support

**Milestone:** Codex Discovery Reward Rework
**Status:** Open
**Priority:** High

#### Problem
Codex discovery is currently inferred from several unrelated profile fields. The loot-based model needs durable ownership by entry type/key.

#### Acceptance Criteria

- A durable Codex ownership store supports entry categories for enemies, biomes, features, unit types, kin, affixes, items, and lore.
- Existing feature, unit type, dialogue, lineage, region, dice affix, and inventory data can seed or map to Codex ownership without duplicate visible entries.
- Profile/Codex payloads expose owned entries and locked placeholders separately enough for the UI to render both.
- Data migration/backfill is forward-only and safe for existing accounts.

### Award enemy and biome Codex pages through combat/run rewards

**Milestone:** Codex Discovery Reward Rework
**Status:** Open
**Priority:** High

#### Problem
Enemy and biome Codex pages should feel stolen from the field rather than automatically inferred from biome completion.

#### Acceptance Criteria

- Only enemy copies actually defeated in a victorious combat can roll enemy Codex drops.
- Each defeated copy rolls independently, but already-owned enemy entries never drop again.
- Common enemy rates are tuned so typical 2-3 copy encounters take roughly 3-4 similar groups on average to discover.
- Boss enemy pages and biome pages are awarded together on the first completed run for that biome.
- New Codex drops appear in combat/run rewards and run summary without duplicating previously owned pages.

### Award Codex entries from unlock and item acquisition events

**Milestone:** Codex Discovery Reward Rework
**Status:** Open
**Priority:** High

#### Problem
Unlockable game systems should document themselves at the moment the player actually receives the thing.

#### Acceptance Criteria

- Feature entries unlock when the feature unlock is granted.
- Unit type entries unlock when the player first owns a unit of that type.
- Kin entries unlock when the player first owns a unit with that kin.
- Affix entries unlock when a die with that affix is earned or purchased.
- Item entries unlock when the player receives the first copy of that item.
- Lore entries continue to unlock when the relevant dialogue/stolen page is encountered.

### Expand Codex and reward UI for useful discovered entries

**Milestone:** Codex Discovery Reward Rework
**Status:** Open
**Priority:** Medium

#### Problem
Codex entries need to become useful records, not just names, while locked entries should preserve discovery goals.

#### Acceptance Criteria

- Locked Codex entries render as placeholders rather than disappearing.
- Enemy entries show useful discovered data, including biome, role, stats, and abilities.
- Biome, feature, unit type, kin, affix, item, and lore entries render from the unified Codex ownership model.
- Reward screens distinguish newly stolen/found Codex pages from ordinary loot.
- Tests cover locked placeholders, newly awarded entries, and no-repeat behavior.
