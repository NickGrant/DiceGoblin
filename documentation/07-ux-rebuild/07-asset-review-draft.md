# Asset Review Draft (Component-First)
----

Status: draft  
Last Updated: 2026-03-09  
Owner: UX + Art + Frontend  
Depends On: `documentation/07-ux-rebuild/06-master-ui-asset-list.md`, `documentation/03-ux/01-visual-design-guide.md`

## Goal
- Provide a lightweight approval checkpoint before asset production.

## Locked Decisions
- Planning model: component-first, then scene mapping.
- Canonical style source: `documentation/03-ux/01-visual-design-guide.md`.
- Runtime assets register through `frontend/public/assets/packs/ui.json`.

## Canonical Source Of Truth
- Asset inventory: `documentation/07-ux-rebuild/06-master-ui-asset-list.md`
- Generation prompts: `documentation/07-ux-rebuild/08-missing-asset-descriptions-and-prompts.md`

## Review Scope
- Validate key names and family grouping.
- Validate which families need state variants (`base`, `hover`, `pressed`, `disabled`).
- Validate icon delivery strategy (individual files vs atlas).
- Validate first consumer component for each new key.

## Outcome
- `approved` or `changes requested`
- notes:
