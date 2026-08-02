---
Title: "Wrong Machine and Kin"
Status: Legacy Reference
Last Updated: 2026-08-01
Owner: Systems Design + Engineering
Depends On:
  - documentation/01-lore/00-world-and-lore.md
  - documentation/02-systems/mvp-reference/02-units-and-progression.md
  - documentation/02-systems/mvp-reference/04-loot-and-drop-scope.md
  - documentation/07-development-path/2026-07-25-roadmap.md
Category: 02-systems
Tags:
  - systems
  - mvp-reference
---

# Wrong Machine and Kin

## Purpose

- Define the current implementation contract for goblins, goblin-kin, lineages, and the Wrong Machine.
- Keep new work aligned on kin terminology while legacy storage fields are renamed through focused compatibility slices.
- Document the first Pig Kin reconstruction path and the rules future kin unlocks should follow.

## Terminology

- **Goblin** is the baseline player unit identity.
- **Goblin-kin** is the approved player-facing term for inherited goblin forms created or restored through the Wrong Machine.
- **Kin** is the short form used when context is already clear.
- **Lineage** is the account-level unlock track for a kin.
- **Basic Goblin** is the implicit default lineage available to every account.
- **Pig Kin** is the first explicit kin lineage and the first Wrong Machine reconstruction target.

Legacy `splice_variant` names may still appear in storage, migrations, or compatibility fields until a dedicated rename migration lands. New UI copy, docs, API additions, services, and issues should use goblin, goblin-kin, kin, or lineage language.

## State Ownership

- Basic Goblin is implicit and should not require a stored unlock row.
- Explicit lineage unlocks are stored as account unlocks in the `lineage` namespace.
- Owned lineage data should be exposed through profile and debug/dev catalog surfaces.
- Unit kin identity remains attached to the unit instance and is preserved through promotion.
- Recruitment and random unit rewards should select kin only from lineages the account owns, unless the reward payload explicitly grants a specific kin.

## Wrong Machine Contract

The Wrong Machine is backend-authoritative. The frontend may show preview data and submit the selected reconstruction, but the backend owns eligibility, costs, spending, unlocks, and unit grants.

Current reconstruction rules:

- A reconstruction unlocks a lineage.
- Unlocking a lineage immediately grants one usable unit of that kin.
- Pig Kin is the first supported reconstruction.
- Reconstruction spends Raw Chaos and progression items transactionally.
- Required materials and catalysts are consumed only on successful first reconstruction.
- Duplicate reconstruction requests for an already-owned lineage are idempotent and must not spend materials or grant duplicate tutorial units.
- Evolving an existing specific unit into a kin is deliberately deferred.

## Pig Kin First Reconstruction

Pig Kin is the protected introductory reconstruction target.

The current implementation direction is:

- Pig Kin materials originate from Farm pig-family content.
- The first Pig Kin cost uses Raw Chaos, common pig materials, and a boss catalyst.
- If the player reaches the tutorial without enough required materials, a one-time introductory bundle may be granted by reward claim or tutorial progression.
- After Pig Kin is unlocked, Pig Kin can appear in future eligible recruitment and reward pools.
- Pig Kin should be more likely from Farm-native rewards than from unrelated regions.

## Item and Reward Rules

New progression rewards should use the generic item inventory foundation rather than adding new `region_items` behavior.

Required progression items follow these rules:

- Required boss catalysts must be protected by first-clear, deterministic progress, or another non-random guarantee.
- Common lineage materials may be random, but repeated eligible encounters should produce visible progress.
- Materials earned before the Wrong Machine unlock remain valid afterward.
- Spending must never allow negative quantities.
- Reward claim remains the preferred point for durable item, unlock, and one-time bundle grants.

## Current Progression Reward Audit

The first reconstruction path is Pig Kin. Its required materials are currently protected by Farm pig-family combat rewards:

| Region | Current required material role | Protection status |
| --- | --- | --- |
| Farm | Pig Ear lineage material from pig-family victories | Deterministic on eligible pig-family combat and boss victories. |
| Farm | Mudking Crown Fragment boss catalyst | Guaranteed on Mudking boss victory. |
| Mountains | No required first-reconstruction material currently assigned | No campaign-critical material depends on Mountains random drops yet. |
| Swamps | No required first-reconstruction material currently assigned | Wrong Machine unlock is story/progression gated, not item-drop gated. |

Future Mountains or Swamps lineage materials should use the same generic item inventory path and must declare their protection rule before becoming required for campaign progression.

## Current Random Reward Pool Audit

Random unit grants are user-aware:

- Basic Goblin is always eligible through the implicit default lineage.
- Explicit lineages enter the random pool only after the account owns that lineage.
- Authored reward payloads that name a specific kin bypass the random pool and may grant that kin directly.

This keeps special story or reconstruction rewards authorable without letting locked kin appear in ordinary recruitment rolls.

## Balance Boundaries

Kin identity should remain smaller than class identity.

- Basic Goblins must remain viable.
- A kin should offer flavor, small stat identity, or bounded passive behavior without replacing class and promotion choices.
- Kin unlocks should expand recruitment variety rather than create mandatory best-in-slot progression.
- Future kin passives should be added through a reviewed shared vocabulary instead of bespoke one-off combat hooks.

## Deferred Work

- Execute the dedicated legacy splice storage retirement plan in `documentation/02-systems/mvp-reference/16-legacy-splice-storage-retirement.md`.
- Add player-facing Wrong Machine UI beyond debug/dev testing surfaces.
- Add specific-unit evolution into a kin.
- Define the full kin passive vocabulary and balance budget.
- Add additional reconstructed kin beyond Pig Kin.
- Generalize the item inventory into healing and energy consumables.

## Validation Rules

This system is aligned when:

- new implementation uses kin and lineage terminology
- Basic Goblin is always available without explicit unlock storage
- profile/debug surfaces show owned lineages
- random kin rewards are gated by owned lineages
- explicit kin reward payloads remain supported
- reconstruction is transactional and idempotent
- required progression materials are protected from random-drop failure
- old migrations remain untouched
