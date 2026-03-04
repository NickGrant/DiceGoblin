# UX & Debug Scope — MVP

Status: active  
Last Updated: 2026-03-04  
Owner: UX + Frontend  
Depends On: `documentation/03-ux/02-warband-management.md`, `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`


This document defines the **authoritative UX surface area and debug/operator tooling** for the Dice Goblins MVP. Any screen, navigation behavior, UI affordance, or debug feature not listed here is **out of scope** for MVP.

---

## 1. Design Goals

The MVP UX and debug scope must:
- Keep scene flow simple and deterministic
- Ensure players can understand outcomes (especially combat)
- Provide sufficient developer tooling to tune content and diagnose bugs
- Avoid UI complexity that creates state-management risk (e.g., modal stacks, back navigation)

---

## 2. Screen List (MVP)

The following screens/scenes are required for MVP:

- Landing / Login
- Home
- Region Select (Mountains, Swamps)
- Run Map (Exploration)
- Encounter Start / Squad Select (if applicable)
- Combat Viewer (live + replay)
- Loot Claim / Rewards
- Rest Management (active run rest workflow)
- End of Run Summary (completed / failed / abandoned)
- Warband Management (unit list)
- Unit Details
- Dice Inventory
- Dice Details

Notes:
- Screen list is considered complete for MVP.
- Promotion actions are embedded in Unit Details (not a standalone promotion scene).

---

## 3. Navigation & Scene Flow Constraints

### 3.1 Full Scenes Only

- All UI is implemented as **full scenes**.
- No modal stacks are required in MVP.

### 3.2 No Back Buttons

- The MVP does not include back buttons.
- Navigation is via explicit forward actions (e.g., buttons such as Continue / Claim / Start Run).

### 3.3 Quit Run Location

- **Quit / Abandon Run** is only available from the **Run Map** scene.
- Quit/Abandon is not available from combat, loot, rest, or boss scenes.

---

## 4. Combat Viewer Contract

Combat is server-resolved once per fight; the client renders the results.

### 4.1 Playback Modes

The combat viewer supports:
- **Autoplay**
- **Pause/Resume**
- **Speed controls:** 1× / 2× / 4×
- **Step-through controls:** next event / previous event
- **Skip to Outcome**

### 4.2 Minimum Combat HUD

The combat viewer must display:
- HP bars for all units
- Status effect icons for: Poison, Bolstered, Sleep
- A readable representation of damage instances (log line and/or floating text)
- A scrollable combat log panel (collapsible on mobile)

### 4.3 Replay Rules

- Replays use the stored battle log.
- No client-side re-simulation.

---

## 5. Run Map Information Requirements

The Run Map must display:
- Node type icons: combat / loot / rest / boss / exit
- Node status: locked / available / cleared
- Warband condition summary:
  - **Undefeated units / total units**
- Current energy (current / max)
- Exit node visual treatment that is distinct from standard combat/loot/rest/boss nodes (for example, portal/door motif).

Explicitly excluded:
- Displaying run energy cost already paid

---

## 6. Unit & Dice Information Requirements

### 6.1 Unit Details

Unit details must include:
- Tier, level, XP (XP is progress within the current level), and max level cap
- Equipped dice list (with affixes)
- Abilities (2 active + up to 2 passives)
- Unit details should display XP progress toward next level when not at max level.
- At max level, XP progress is hidden or shown as "MAX".

If a run is active, unit details should also show (read-only):
- Current run HP
- Current run status effects
- Defeated flag

Promotion behavior:
- Promotion CTA is embedded in unit details.
- Promotion is enabled between runs and during active-run rest workflow.
- Promotion is disabled in active-run non-rest contexts.

### 6.2 Dice Details

Dice details must include:
- Die size (d4–d10)
- Rarity (common/uncommon/rare)
- Slot capacity
- Affix list with values and clear labeling of conditional affixes

---

## 7. Debug / Operator Tooling (Dev Mode)

### 7.1 Enablement

- Debug mode is enabled via an **environment flag** (not a key chord).

### 7.2 Debug Overlay (Read-Only)

When enabled, the UI may display:
- `run_id`
- `node_id`
- `encounter_template.slug`
- `seed`
- `battle_id`

### 7.3 Dev Panel Actions

When enabled, the dev panel supports:
- Grant currency
- Grant a die (choose sides + rarity)
- Grant a unit (choose `unit_type`)
- Force boss region-item drop (for Tier 3 testing)
- Export battle log JSON (copy to clipboard)

---

## 8. Explicit Non-Goals

The MVP UX/debug scope does **not** include:
- Accessibility pass beyond baseline readability
- Sound / music mixing UI
- Modal dialogs / nested navigation stacks
- In-production cheat menus
- Analytics dashboards

---

## 9. MVP Validation Criteria

UX/debug scope is MVP-complete when:
- Players can navigate the full loop without back navigation
- Quit/Abandon is accessible from the Run Map only
- Combat playback is usable for both players (autoplay) and devs (step-through)
- The Run Map communicates progression and attrition clearly
- Rest workflow includes a summary surface that shows per-unit healing and progression deltas.
- Run-end summary uses one shared shell for completed/failed/abandoned outcomes with different messaging.
- Debug tooling is sufficient to reproduce issues and validate drop/promotion paths

---

This document is considered **locked** for MVP unless explicitly revised.

Related:
- Detailed warband interaction contract: `documentation/03-ux/02-warband-management.md`
