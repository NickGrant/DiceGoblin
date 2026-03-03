# Encounter Reward Surface Rules - MVP
----

Status: active  
Last Updated: 2026-03-03  
Owner: Systems Design + UX  
Depends On: `documentation/02-systems-mvp/03-encounter-scope.md`, `documentation/02-systems-mvp/04-loot-and-drop-scope.md`, `documentation/02-systems-mvp/06-run-resolution-scope.md`

## Purpose
- Define what the reward/claim surface must show by encounter type.
- Keep XP/reward messaging aligned with server behavior and MVP progression rules.

## Shared Surface Contract
All encounter outcomes should show:
- Encounter type (`combat|boss|loot|rest`).
- Claim status (`claimable|claimed`).
- Rewards payload summary from backend claim response.
- Updated run-unit-state summary when provided.

## Encounter-Type Rules

### Combat
- XP is shown as combat XP (`xp_total`) from claim response.
- XP application details must distinguish:
  - `applied_unit_instance_ids`
  - `ignored_at_cap_unit_instance_ids`
- Run-unit attrition updates are shown after claim.
- Victory: node progression callout indicates node cleared/unlocks may occur.
- Defeat with remaining units: surface explicitly states retry is available and no extra energy cost applies.
- Total defeat: surface indicates run failed and no further map progression.

### Boss
- Same as combat XP rules.
- Boss-specific reward items should be visually distinguished from standard rewards.
- Completion messaging should indicate run progression significance (for example terminal region completion when applicable).

### Loot
- No combat XP messaging.
- Show loot items/currency gains only.
- Surface should indicate immediate return to map after claim.

### Rest
- No combat XP messaging.
- Show recovery effects (HP restore/status cleanup/cooldown refresh) when applicable.
- Surface should indicate immediate return to map after claim.

## Explicit Messaging Rules
- Do not present Loot or Rest as awarding combat XP in MVP.
- Do not imply replay-derived rewards; rewards are claim-time authoritative payloads.
- If a claim is re-requested, UI should present identical claimed results (idempotent behavior).

## Error/Edge Handling
- `battle_not_completed`: show non-claimable state; no reward surface confirmation.
- `forbidden`: show ownership/session error and return path.
- `server_error`: show retry affordance with no duplicate-claim implication.

## Acceptance Criteria
- Reward surface content differs correctly by encounter type.
- XP messaging appears only for combat/boss outcomes.
- Defeat retry and terminal-fail outcomes use distinct, unambiguous copy.
- Repeated claim requests do not change displayed rewards or XP details.
