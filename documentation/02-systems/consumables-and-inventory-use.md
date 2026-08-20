---
Title: "Consumables and Inventory Use"
Status: Canonical
Last Updated: 2026-08-20
Owner: Systems Design + Engineering
Depends On:
  - documentation/03-content/10-items-and-consumables.md
  - backend/src/Services/ConsumableItemService.php
  - backend/src/Services/ItemInventoryService.php
Category: 02-systems
Tags:
  - systems
  - consumables
---

# Consumables and Inventory Use

## Current Runtime

Consumables are spendable `items` rows owned through `user_items`. `ConsumableItemService` validates item category, spendability, target eligibility, and transactional spending.

Healing consumables are used from the run map against run-unit state. Energy consumables are used from global command controls and restore account energy.

## Acquisition

The current introductory path is Llamaver's two Mountains traveler encounters:

- energy first: unlocks the consumables feature and grants `travel_ration`
- health second: grants `field_poultice`

These scenes use separate seen-state suppression so the tutorial is split across two interactions.

## Backend Boundary

The backend owns whether an item is spendable, which effect category it belongs to, whether the target is valid, and the final updated quantity or energy/HP value.

## Known Gaps

- Renewable acquisition for Field Poultice and Travel Ration needs final tuning.
- Inventory inspection exists through profile/command surfaces, but a richer dedicated consumable inventory remains a possible UX improvement.
