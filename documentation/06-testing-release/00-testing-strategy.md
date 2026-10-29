---
Title: "vNext Testing Strategy"
Status: Canonical
Last Updated: 2026-10-29
Owner: QA + Engineering
Depends On:
  - agent/QUALITY_GATES.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 06-testing-release
Tags: [testing, vnext]
---

# vNext Testing Strategy

Verification should live at the architectural layer that owns the behavior and should grow with each walking slice. The regression suite optimizes for behavior protection, diagnostic value, and execution cost rather than assertion or test counts.

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

## Test Value Rules

Every test should protect a distinct behavior or boundary. Prefer the narrowest layer that can prove the contract.

- **Do not test application data as behavior.** Functional tests must not fail merely because a balance value, display name, flexible catalog membership, reward amount, or other authored content changes. Use small purpose-built fixture definitions containing only the data required by the behavior under test.
- **Validate authored content separately.** Canonical content validation may prove schema, semantic invariants, cross-references, stable IDs, and explicitly accepted product contracts. It should not duplicate command/query behavior tests or freeze flexible content through exhaustive lists and counts.
- **Avoid redundant cross-layer coverage.** Exhaustive combinations belong at the owning unit/service layer. Controller/API integration tests should focus on transport, authentication/authorization, validation boundaries, persistence/transaction behavior, response envelopes, and one representative happy path where lower-level behavior is already covered.
- **Keep integration tests focused.** A test should have one primary reason to fail. Avoid scenarios that simultaneously verify unrelated rewards, progression, content composition, persistence, and response formatting when those contracts can be isolated.
- **Assert the contract, not incidental representation.** Prefer required fields, stable IDs, semantic invariants, and persisted effects over complete payload equality, incidental ordering, exact flexible catalog counts, or unrelated fields.
- **Keep mocks and fixtures slim.** Add only the definitions and persisted rows necessary to exercise the behavior. Reusing the full canonical content tree is acceptable only when the integration boundary itself is canonical-content loading or when a smaller registry cannot represent the contract without recreating production internals.
- **Retain expensive integration coverage when it buys boundary confidence.** Security/ownership failures, transaction rollback, idempotency, persistence boundaries, and true walking-slice contracts are high-value even when slower.

When two tests protect the same failure mode, keep the narrower/faster test unless the broader test proves a separate integration boundary.

## Walking-Slice Rule
A milestone is not complete because its isolated backend or frontend layer exists. Its exit criterion must pass through the real client/server/storage path described by the roadmap.

## Current Commands
Use `agent/QUALITY_GATES.md` and repository scripts for current executable commands. Do not copy old command lists from Git history into new docs without verifying they still apply.

## Canonical Regression Suite

The discovered backend and frontend suites protect current vNext behavior. Keep deterministic algorithms, security and ownership failures, transaction rollback, idempotency, persistence boundaries, API envelope and secrecy checks, authored-content validation, and focused client/runtime behavior. Keep integrated walking-slice tests when they prove a contract across boundaries that isolated tests cannot; consolidate redundant copies of the same contract.

Exact authored graph assertions belong where the graph shape is an accepted product contract, such as the Farm tutorial path and Goblin promotion graph. Flexible catalogs and regional layouts should be checked by stable references and semantic invariants, including reachable branches, hazards, and dead ends, rather than total counts, complete unrelated ID lists, or incidental ordering.

Remove superseded prototype tests for completed milestones once their useful failure and retry cases exist in vNext coverage. Tests retained solely as migration evidence for unfinished milestones live in `backend/prototype-test-evidence/` or `frontend/prototype-test-evidence/`, outside canonical test discovery. They are not a passing regression gate against current vNext contracts. Reassess and remove or port them when the owning milestone implements its replacement. Git history preserves removed prototype tests.
