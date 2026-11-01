# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 1 - Opening progression implementation audit

**Status:** In Progress
**Priority:** High

#### Problem
Milestones 10-12 established and accepted the generic run/combat/progression systems, Codex/dialogue/objective systems, and Mystic Cave onboarding lifecycle before the opening campaign's exact narrative progression was made canonical. The canonical story is now recorded in `documentation/01-lore/01-story-and-biome-progression.md` and requires specific player-facing and durable progression beats across Farm and Mountains before Swamp development begins.

Milestone 13 had been activated for planning, but its coding agent had not started implementation. M13 is therefore deferred cleanly while this milestone audits and aligns the already-implemented opening path.

#### Canonical target flow
1. Start -> The Whim is the only meaningful option.
2. The Whim turns the player from Chaos into goblin form, establishes the initial Basic Goblin Warband, and grants Farm access.
3. The Whim sends the player to the Mudking to rescue the Tooth Collector.
4. First Mudking victory rescues the Tooth Collector and makes the Shop available.
5. After the Tooth Collector is recovered, a Whim beat asks the player to find the missing Wrong Machine, points toward the kobolds, and unlocks Mountains.
6. Mountains continue the Wrong Machine trail and contain the player's first direct conversation with The Archivist.
7. First Mountains boss victory establishes that the Wrong Machine has been moved to the Swamps and unlocks Swamps.
8. At the M13 handoff, Wrong Machine access is still locked and Archivist/Order combat troops have not yet been encountered.

M13 owns the first Archivist-troop combat encounters in Swamp, Swamp boss recovery of the Wrong Machine, Frog Kin integration, first reconstruction follow-up, and broader-map opening.

#### Purpose
Perform an evidence-based audit of the current vNext implementation against the canonical target flow. Determine what is already correct, what is missing, what is incorrectly sequenced, and which accepted architectural mechanisms should own each correction. The output of this package is a concrete correction plan and any necessary updates to the remaining M12.5 package scopes.

This is an audit package. Do not perform a broad production rewrite as part of discovery. Small documentation/test-fixture corrections are acceptable only when necessary to make the audit reproducible; gameplay corrections belong in the follow-on packages unless an issue is truly trivial and isolated.

#### Required implementation inventory
Use `agent/CONTEXT_ROUTER.md` and inspect the current branch, not historical assumptions. Trace at minimum:

1. **Mystic Cave completion and Farm access**
   - authored onboarding content;
   - starting Warband provisioning;
   - Farm availability/unlock source;
   - Phaser transition from onboarding to Camp/Farm.

2. **Farm first-victory progression**
   - Farm run graph and Mudking boss;
   - first-victory/terminal reward definitions;
   - Tooth Collector representation, if any;
   - Shop availability/gating and whether it is currently unconditional;
   - Farm dialogue/Codex/objective content associated with Mudking/Tooth Collector;
   - repeat Farm behavior and idempotent permanent rewards.

3. **Post-Farm Whim beat and Mountains availability**
   - current Mountains unlock source/prerequisites;
   - any Whim dialogue after Farm/Mudking completion;
   - whether the Wrong Machine search is currently presented to the player;
   - whether Mountains can become available before the intended player-facing beat;
   - durable state used to represent the resulting progression.

4. **Mountains narrative progression**
   - Mountains graph, encounters, boss, terminal rewards, dialogue/Codex/objectives;
   - current Wrong Machine trail references;
   - any existing Archivist dialogue/presentation and where it occurs;
   - confirm no canonical path already fights Archivist/Order troops before Swamp;
   - current Swamp unlock source/prerequisites, including whether any placeholder region/unlock identity exists.

5. **Client/runtime presentation**
   - Camp destinations and locked/unlocked presentation for Shop and regions;
   - dialogue surfaces used during/after runs;
   - reload/reconnect behavior around first-victory and follow-up dialogue beats;
   - whether player-facing routing can express the canonical sequence without a new client authority.

6. **Authority and persistence**
   - existing permanent unlock/reward identities suitable for Shop, Mountains, and Swamps;
   - dialogue/Codex eligibility mechanisms suitable for the Whim and Archivist beats;
   - whether any current implementation relies on obsolete story/history/seen-state patterns that conflict with the accepted vNext progression model;
   - player revision/idempotency behavior for the mutations that will need correction.

7. **Tests and fixtures**
   - tests that currently encode opening-progression assumptions;
   - distinguish production structural validation from frozen behavior fixtures;
   - identify tests that will need intentional updates versus tests that reveal a regression risk.

#### Canonical/architectural constraints
- `documentation/01-lore/01-story-and-biome-progression.md` owns story order and story-gated progression.
- `documentation/01-lore/00-world-and-lore.md` owns setting fundamentals.
- Use current vNext reward/unlock, progression, dialogue/Codex, authored-content, storage/API, and Phaser architecture. Do not create a parallel story-state subsystem.
- No generic `story_progress`, chapter counter, `has_seen_dialogue`, tutorial-history, or catch-all progression table/field.
- Prefer durable result state: Shop/region/capability access as permanent unlocks where appropriate; Codex only for retained player-facing knowledge; dialogue eligibility/presentation through accepted dialogue authority.
- The first direct player/Archivist conversation belongs in Mountains.
- Archivist/Order troops are first fought in Swamp, not Mountains.
- `capability.wrong_machine_access` must remain absent throughout M12.5. Its canonical grant point is first successful Swamp boss defeat in M13.
- Do not implement Frog Kin, Swamp combat content, Wrong Machine recovery, first-reconstruction follow-up, or broader-map opening in M12.5.
- Do not treat prototype behavior as current authority.

#### Required audit output
Record the audit in this issue's completion evidence (or a narrowly linked canonical planning document if the findings are too large) with a table or equivalent inventory containing, for each canonical beat:
- target behavior;
- current implementation behavior;
- status: already correct / missing / incorrectly sequenced / conflicting;
- current owning content/code paths;
- intended accepted mechanism for correction;
- recommended M12.5 package owner;
- tests/verification affected.

At minimum explicitly answer:
1. Is Farm currently unlocked by onboarding in the correct durable way?
2. Does first Mudking victory currently rescue/represent the Tooth Collector?
3. Is Shop currently gated by Tooth Collector recovery or available too early?
4. What currently unlocks Mountains?
5. Is there currently a post-Farm Whim interaction capable of owning the Mountains unlock beat?
6. Where, if anywhere, is the Archivist currently presented directly to the player?
7. What currently unlocks Swamp, or what existing progression identity should do so?
8. Can the canonical flow be expressed entirely through accepted existing systems?
9. Which planned Packages 2-5 remain necessary, and should any be merged/split based on evidence?

#### Minimum proof
1. The audit traces the real fresh-player progression from registration/onboarding through current Farm and Mountains implementation with concrete file/content/test references.
2. Every canonical beat through the M13 handoff is classified against current behavior.
3. No M13 Swamp implementation is introduced.
4. No new generic story-state architecture is proposed without first proving existing accepted systems cannot express the requirement.
5. Remaining M12.5 package scopes are updated if the audit shows the provisional queue is inaccurate.
6. Relevant existing tests are run as needed to validate claims; a full package verification is not required for a pure audit unless source/production content is changed.

#### Scope exclusions
- implementing the Farm correction itself except trivial audit-enabling fixes;
- implementing post-Farm Whim progression;
- implementing Archivist Mountains dialogue;
- implementing Mountains boss/Swamp unlock corrections;
- any Swamp/Frogmen/Archivist-troop content;
- Wrong Machine access/reconstruction changes;
- Frog Kin;
- broader-map opening;
- broad visual redesign;
- Milestone 13 parity work;
- Milestone 14 hardening/cutover.

#### Completion evidence required
Record the audit implementation/evidence SHA if files are changed; exact current source/content paths inspected; the canonical-beat discrepancy inventory; relevant focused test commands/results; recommended corrections and package ownership; any proposed consolidation/splitting of Packages 2-5; and explicit confirmation that no M13 implementation or generic story-state architecture was introduced.

Leave Package 1 **In Progress** for architectural review. Do not activate Package 2 automatically from the coding agent.
