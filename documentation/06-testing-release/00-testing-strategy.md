---
Title: "vNext Testing Strategy"
Status: Canonical
Last Updated: 2026-09-10
Owner: QA + Engineering
Depends On:
  - agent/QUALITY_GATES.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 06-testing-release
Tags: [testing, vnext]
---

# vNext Testing Strategy

Verification should live at the architectural layer that owns the behavior and should grow with each walking slice.

## Persistent Gates
- **Fresh database:** vNext must repeatedly prove that a clean database can be created and boot the implemented slice without prototype migration history or a developer's accumulated state.
- **Backend:** commands/queries cover success plus auth, CSRF, ownership, validation, and transaction failure paths as appropriate.
- **Idempotency:** spending, durable asset creation, randomness, consumable use, run creation, promotion where retry-sensitive, and gameplay resolution must prove retries do not double-spend, duplicate, or reroll.
- **Authored content:** JSON receives structural and semantic/cross-reference validation. Broken stable IDs fail verification.
- **Client projection:** tests verify that client artifacts contain only allowlisted fields and do not expose known server-only reward probabilities, hidden pools/rules, or unrevealed content.
- **Phaser:** behavior/state tests cover runtime/navigation/cache logic; deterministic screenshots cover important presentation states.
- **Responsive:** important Phaser screens are reviewed at Compact landscape, 1600x900 reference, and Wide landscape compositions. Portrait mobile verifies the rotate-device gate and preserved runtime state.
- **Combat/run generation:** deterministic algorithms retain focused regression/simulation coverage as they are migrated.
- **Documentation:** references and accepted contracts remain consistent with implementation changes.

## Walking-Slice Rule
A milestone is not complete because its isolated backend or frontend layer exists. Its exit criterion must pass through the real client/server/storage path described by the roadmap.

## Current Commands
Use `agent/QUALITY_GATES.md` and repository scripts for current executable commands. Do not copy old command lists from Git history into new docs without verifying they still apply.

## Canonical Regression Suite

The discovered backend and frontend suites protect current vNext behavior. Keep deterministic algorithms, security and ownership failures, transaction rollback, idempotency, persistence boundaries, API envelope and secrecy checks, authored-content validation, and focused client/runtime behavior. Keep integrated walking-slice tests when they prove a contract across boundaries that isolated tests cannot; consolidate redundant copies of the same contract.

Exact authored graph assertions belong where the graph shape is an accepted product contract, such as the Farm tutorial path and Goblin promotion graph. Flexible catalogs and regional layouts should be checked by stable references and semantic invariants, including reachable branches, hazards, and dead ends, rather than total counts, complete unrelated ID lists, or incidental ordering.

Remove superseded prototype tests for completed milestones once their useful failure and retry cases exist in vNext coverage. Tests retained solely as migration evidence for unfinished milestones live in `backend/prototype-test-evidence/` or `frontend/prototype-test-evidence/`, outside canonical test discovery. They are not a passing regression gate against current vNext contracts. Reassess and remove or port them when the owning milestone implements its replacement. Git history preserves removed prototype tests.
