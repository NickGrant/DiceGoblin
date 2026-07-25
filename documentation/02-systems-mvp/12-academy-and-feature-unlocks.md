# Academy and Feature Unlocks

Status: active  
Last Updated: 2026-06-21  
Owner: Systems Design + UX  
Depends On: `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/01-architecture/03-backend-api-contracts.md`

## Purpose

- Centralize the current academy, promotion, and feature-unlock rules.
- Reduce the need to reconstruct academy behavior from shop, guide, and progression docs.

## Academy Access

- The academy is a feature unlock, not a default starting screen.
- Unlock key: `academy`
- Current shop cost: 250 soft currency

Once unlocked, the academy becomes the player-facing home for:

- Tier I unit-type research
- promotion planning
- capstone selection for mastered units

## Academy Unit-Type Research

The academy catalog exposes Tier I and Tier II unit-family unlocks.

Current starter family set:

- `frontline_bruiser_t1`
- `frontline_guardian_t1`
- `backline_marksman_t1`
- `support_banner_t1`
- `control_saboteur_t1`

Unlocking a unit type makes that family available for future recruitment surfaces such as the shop.

Tier I unit-family research is currently teeth-gated only. Tier II research remains visible in the same catalog, but it is locked until the player has completed at least one run. The backend returns each research item's `is_available` state and requirement rows so the UI can explain locked options without becoming authoritative.

Current gameplay-gated research requirement:

- Tier II unit-type research: complete any run

## Promotion Workspace

The academy promotion flow currently lets the player:

- pick a promotable primary unit
- pick two secondary same-type, same-tier units to consume
- preview chain and sideways promotion options
- choose a required capstone first when the current class is mastered but not yet finalized
- progress Tier II chain classes into Tier III only after mastery

## Capstone Behavior

Current capstone state meanings:

- `none`: no capstone is authored for the current class
- `unearned`: the unit can promote now, but doing so skips the current class capstone
- `ready_to_select`: the unit mastered the class and must choose a capstone before promotion
- `selected`: the capstone is already locked and will carry forward

Tier III chain destinations are terminal classes with their own mastery capstone choices. Current Tier III chain coverage is Juggernaut, Ironwall, Sharpshot, Warchanter, and Venomwright.

## Feature Unlock Catalog

The current shop feature unlock list includes:

- `academy`
- `bigger_squad`
- `biggerest_squad`
- `shop_discount`
- `sell_bonus`
- `market_mastery`
- `second_daily_deal`

## Feature Unlock Effects

- `academy`: enables academy route and systems
- `bigger_squad`: raises squad cap from 4 to 6
- `biggerest_squad`: raises squad cap from 6 to 9
- `shop_discount`: improves future shop pricing
- `sell_bonus`: improves dice sale returns
- `market_mastery`: strengthens both economy upgrades after prerequisites are met
- `second_daily_deal`: adds a second daily-deal slot

## Feature Unlock Prerequisites

Current prerequisite rules surfaced in player-facing copy:

- `bigger_squad`: no prerequisite
- `biggerest_squad`: requires `bigger_squad`
- `market_mastery`: requires both `shop_discount` and `sell_bonus`

## Expanded Tree Direction

The academy should keep growing as a medium-term progression tree connected to the main loop rather than a separate currency sink. Near-term branches can use existing gameplay facts:

- bounties can unlock additional bounty slots, categories, or reroll controls
- splice research can reveal variant tendencies or provide limited recruitment steering
- future crafting can unlock dice salvage, fabrication, or probability-appraisal tools

These branches should stay backend-authored and expose visible requirement rows in their catalogs.

## Active Run Restrictions

Academy actions are blocked for units currently locked into an active run snapshot.

That includes:

- promotion
- capstone selection
- loadout-affecting mutations handled through unit systems

## Validation Rules

This doc is aligned when:

- academy is documented as a purchased feature unlock
- Tier I/Tier II research and promotion are clearly separated but housed together
- current feature unlock keys and prerequisites match the live shop/catalog behavior
